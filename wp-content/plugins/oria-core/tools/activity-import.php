<?php
/**
 * Apply researched joining details (data/activity/<file>.json) to activity
 * listings: pickleball, running groups and the like.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/activity-import.php <file>          (dry run)
 *   wp eval-file wp-content/plugins/oria-core/tools/activity-import.php <file> apply    (write)
 *
 * The public-source rule, kept exactly: research never overwrites.
 *  - An EMPTY field on an existing listing is filled.
 *  - A field that already holds something different is left alone and the
 *    researched value goes into the listing's "Proposed from public
 *    sources" box (_oria_src_proposal), for an editor to accept.
 *  - A venue with "mode": "propose" gets a proposal only, nothing filled
 *    (for research the editor should judge first).
 *  - A missing venue is created as a DRAFT in the research workflow, held
 *    by the publish guard until reviewed.
 *  - A pinned Google place is set only when the listing has none, and turns
 *    Places on so its photos show (through the API, as the site already does).
 * Idempotent: a rerun reports "unchanged".
 *
 * @package Oria\Core
 */

$name  = sanitize_key( (string) ( $args[0] ?? '' ) );
$apply = 'apply' === (string) ( $args[1] ?? '' );
$file  = ORIA_CORE_DIR . 'data/activity/' . $name . '.json';
if ( '' === $name || ! is_readable( $file ) ) {
	WP_CLI::error( 'Usage: activity-import.php <data file> [apply]; no file at ' . $file );
}
$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
if ( ! is_array( $data ) || empty( $data['venues'] ) ) {
	WP_CLI::error( 'Data file has no venues.' );
}
$batch   = (string) ( $data['batch'] ?? $name );
$checked = (string) ( $data['checked'] ?? gmdate( 'Y-m-d' ) );
WP_CLI::log( ( $apply ? 'APPLY' : 'DRY RUN' ) . " -- {$name}, " . count( $data['venues'] ) . ' venues' );

// Plain fields: meta name => ACF field key.
$fields = array(
	'join_method'   => 'field_oria_join_method',
	'join_url'      => 'field_oria_join_url',
	'cost_status'   => 'field_oria_cost_status',
	'price_note'    => 'field_oria_price_note',
	'schedule_text' => 'field_oria_schedule_text',
	'meeting_point' => 'field_oria_meeting_point',
	'beginner'      => 'field_oria_beginner',
	'come_alone'    => 'field_oria_come_alone',
	'address'       => 'field_oria_address',
	'phone'         => 'field_oria_phone',
	'website'       => 'field_oria_website',
);

$n = array( 'filled' => 0, 'proposed' => 0, 'created' => 0, 'pinned' => 0, 'unchanged' => 0 );

foreach ( $data['venues'] as $v ) {
	$slug = (string) $v['slug'];
	$f    = (array) ( $v['fields'] ?? array() );
	$post = get_page_by_path( $slug, OBJECT, 'listing' );

	if ( ! $post instanceof WP_Post ) {
		if ( empty( $v['create'] ) ) {
			WP_CLI::warning( "{$slug}: no listing and nothing to create -- skipped." );
			continue;
		}
		$c = (array) $v['create'];
		if ( ! $apply ) {
			WP_CLI::log( "  + {$slug}: would create DRAFT \"{$c['title']}\" with " . count( array_filter( $f ) ) . ' joining fields' . ( ! empty( $v['place_id'] ) ? ' and a pinned Google place' : '' ) );
			++$n['created'];
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'listing', 'post_status' => 'draft', 'post_title' => (string) $c['title'], 'post_name' => $slug, 'post_excerpt' => (string) ( $c['excerpt'] ?? '' ) ), true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( "{$slug}: " . $id->get_error_message() );
			continue;
		}
		update_field( 'field_oria_kind', (string) ( $c['kind'] ?? 'group' ), $id );
		wp_set_object_terms( $id, (array) $c['practice'], 'practice' );
		wp_set_object_terms( $id, array_values( array_filter( (array) $c['areas'] ) ), 'area' );
		update_post_meta( $id, '_oria_src_batch', $batch );
		update_post_meta( $id, '_oria_src_key', $slug );
		update_post_meta( $id, '_oria_review', 'scraped' );
		if ( ! empty( $c['flags'] ) ) {
			update_post_meta( $id, '_oria_src_flags', wp_slash( (string) $c['flags'] ) );
		}
		$post = get_post( (int) $id );
		++$n['created'];
		WP_CLI::log( "  + {$slug} (#{$id}): created DRAFT, held for review" );
	}
	$id      = (int) $post->ID;
	$mode    = (string) ( $v['mode'] ?? 'fill' );
	$fill    = array();
	$propose = array();

	foreach ( $fields as $meta => $key ) {
		if ( ! isset( $f[ $meta ] ) || '' === trim( (string) $f[ $meta ] ) || 'unknown' === $f[ $meta ] ) {
			continue;
		}
		$have = trim( (string) get_post_meta( $id, $meta, true ) );
		$want = trim( (string) $f[ $meta ] );
		if ( $have === $want ) {
			continue;
		}
		if ( '' === $have && 'fill' === $mode ) {
			$fill[ $meta ] = $want;
		} else {
			$propose[ $meta ] = $want;
		}
	}

	// Detail rows: add labels the listing lacks; a differing value is a proposal.
	$rows = array();
	$have_rows = array();
	$count = (int) get_post_meta( $id, 'activity_details', true );
	for ( $i = 0; $i < $count; $i++ ) {
		$have_rows[ strtolower( trim( (string) get_post_meta( $id, "activity_details_{$i}_label", true ) ) ) ] = trim( (string) get_post_meta( $id, "activity_details_{$i}_value", true ) );
	}
	$new_rows = array();
	foreach ( (array) ( $v['details'] ?? array() ) as $d ) {
		$label = trim( (string) $d['label'] );
		$value = trim( (string) $d['value'] );
		$k     = strtolower( $label );
		if ( '' === $label || '' === $value ) {
			continue;
		}
		if ( ! isset( $have_rows[ $k ] ) && 'fill' === $mode ) {
			$new_rows[] = array( 'label' => $label, 'value' => $value );
		} elseif ( ( $have_rows[ $k ] ?? null ) !== $value ) {
			$propose['details'][] = array( 'label' => $label, 'value' => $value );
		}
	}

	// Values the research flagged for confirmation: proposal only, always.
	foreach ( (array) ( $v['propose_details'] ?? array() ) as $d ) {
		$propose['details'][] = array( 'label' => (string) $d['label'], 'value' => (string) $d['value'] );
	}

	// The proposal in the box's own shape, one entry per batch.
	$box   = json_decode( (string) get_post_meta( $id, '_oria_src_proposal', true ), true );
	$box   = is_array( $box ) ? $box : array();
	$plain = array( 'join_method', 'join_url', 'cost_status', 'price_note', 'schedule_text', 'details' );
	$other = array_diff_key( $propose, array_flip( $plain ) );
	$entry = $propose ? array(
		'at'         => $checked,
		'join'       => array( 'method' => $propose['join_method'] ?? '', 'url' => $propose['join_url'] ?? '' ),
		'cost'       => $propose['cost_status'] ?? '',
		'price_note' => $propose['price_note'] ?? '',
		'schedule'   => $propose['schedule_text'] ?? '',
		'details'    => array_merge(
			(array) ( $propose['details'] ?? array() ),
			array_map( static fn( string $k, string $val ): array => array( 'label' => ucfirst( str_replace( '_', ' ', $k ) ), 'value' => $val ), array_keys( $other ), array_values( $other ) )
		),
		'sources'    => (array) ( $v['sources'] ?? array() ),
	) : array();
	if ( $entry && ( $box[ $batch ] ?? null ) == $entry ) { // phpcs:ignore Universal.Operators.StrictComparisons
		$propose = array(); // already proposed, word for word
	}

	$pin = (string) ( $v['place_id'] ?? '' );
	$pin = ( '' !== $pin && '' === trim( (string) get_post_meta( $id, 'google_place_id', true ) ) ) ? $pin : '';

	if ( ! $fill && ! $new_rows && ! $propose && '' === $pin ) {
		WP_CLI::log( "  = {$slug} (#{$id}): unchanged" );
		++$n['unchanged'];
		continue;
	}
	WP_CLI::log(
		"  ~ {$slug} (#{$id}): "
		. ( $fill ? 'fill ' . implode( ', ', array_keys( $fill ) ) . '; ' : '' )
		. ( $new_rows ? '+' . count( $new_rows ) . ' detail rows; ' : '' )
		. ( $propose ? 'propose ' . implode( ', ', array_keys( $propose ) ) . '; ' : '' )
		. ( '' !== $pin ? 'pin Google place' : '' )
	);
	if ( ! $apply ) {
		$n['filled']   += (int) (bool) ( $fill || $new_rows );
		$n['proposed'] += (int) (bool) $propose;
		$n['pinned']   += (int) ( '' !== $pin );
		continue;
	}

	foreach ( $fill as $meta => $want ) {
		update_field( $fields[ $meta ], $want, $id );
	}
	if ( $new_rows ) {
		$all = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$all[] = array( 'field_oria_ad_label' => (string) get_post_meta( $id, "activity_details_{$i}_label", true ), 'field_oria_ad_value' => (string) get_post_meta( $id, "activity_details_{$i}_value", true ) );
		}
		foreach ( $new_rows as $r ) {
			$all[] = array( 'field_oria_ad_label' => $r['label'], 'field_oria_ad_value' => $r['value'] );
		}
		update_field( 'field_oria_activity_details', $all, $id );
	}
	if ( $fill || $new_rows ) {
		update_post_meta( $id, '_oria_src_checked', $checked );
		update_post_meta( $id, '_oria_src_urls', wp_slash( wp_json_encode( (array) ( $v['sources'] ?? array() ) ) ) );
		++$n['filled'];
	}
	if ( $propose ) {
		$box[ $batch ] = $entry;
		update_post_meta( $id, '_oria_src_proposal', wp_slash( wp_json_encode( $box ) ) );
		++$n['proposed'];
	}
	if ( '' !== $pin ) {
		update_field( 'google_place_id', $pin, $id );
		update_post_meta( $id, '_oria_places_ok', '1' );
		delete_post_meta( $id, '_oria_places_v5' );
		++$n['pinned'];
	}
	clean_post_cache( $id );
}

WP_CLI::success( sprintf( '%s: %d created, %d filled, %d with proposals, %d Google places pinned, %d unchanged.', $apply ? 'Applied' : 'Dry run', $n['created'], $n['filled'], $n['proposed'], $n['pinned'], $n['unchanged'] ) );
