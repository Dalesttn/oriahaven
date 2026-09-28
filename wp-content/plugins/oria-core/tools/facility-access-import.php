<?php
/**
 * Apply researched facility-access facts (data/facility-access/*.json) to
 * the matching listings.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/facility-access-import.php steam-room          (dry run)
 *   wp eval-file wp-content/plugins/oria-core/tools/facility-access-import.php steam-room apply    (write)
 *
 * - Matches each venue by listing slug AND its website's host, so a record
 *   for another branch is never written to. Works on any install: nothing
 *   depends on local post IDs.
 * - Replaces only this facility's row; other facility rows are kept.
 * - Skips a row an editor has checked more recently than the data file
 *   (their correction wins) and says so.
 * - Adds the facility's service term when the row is confirmed; never
 *   removes a term.
 * - Before writing, saves every touched listing's previous rows and terms
 *   to wp-content/uploads/oria-rollback/ for rollback.
 * - A rerun with the same file reports "unchanged" and writes nothing.
 *
 * @package Oria\Core
 */

use Oria\Core\FacilityAccess as FA;

$facility = sanitize_key( (string) ( $args[0] ?? '' ) );
$apply    = 'apply' === (string) ( $args[1] ?? '' );
$file     = ORIA_CORE_DIR . 'data/facility-access/' . $facility . '.json';
if ( '' === $facility || ! is_readable( $file ) ) {
	WP_CLI::error( 'Usage: facility-access-import.php <facility> [apply]; no data file at ' . $file );
}
$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
if ( ! is_array( $data ) || empty( $data['venues'] ) ) {
	WP_CLI::error( 'Data file has no venues.' );
}
$service = (string) ( $data['service_term'] ?? $facility );
WP_CLI::log( ( $apply ? 'APPLY' : 'DRY RUN' ) . " -- {$facility}, " . count( $data['venues'] ) . ' venues, data checked ' . ( $data['checked'] ?? '?' ) );

$host = static fn( string $u ): string => preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) ) );

/** The repeater row in ACF's field-key form. */
$to_acf = static function ( array $r ) use ( $facility ): array {
	$k   = 'field_oria_fa_';
	$row = array( $k . 'facility' => $facility );
	foreach ( array( 'status', 'setting', 'casual', 'condition', 'booking', 'swimwear', 'towels', 'quiet', 'accessibility', 'accessibility_url', 'book_url', 'rules_url', 'notice', 'evidence' ) as $f ) {
		$row[ $k . $f ] = (string) ( $r[ $f ] ?? '' );
	}
	$row[ $k . 'min_age' ]      = isset( $r['min_age'] ) && null !== $r['min_age'] ? (string) (int) $r['min_age'] : '';
	$row[ $k . 'includes' ]     = implode( "\n", (array) ( $r['includes'] ?? array() ) );
	$row[ $k . 'essentials' ]   = implode( "\n", (array) ( $r['essentials'] ?? array() ) );
	$row[ $k . 'sources' ]      = implode( "\n", (array) ( $r['sources'] ?? array() ) );
	$row[ $k . 'checked' ]      = str_replace( '-', '', (string) ( $r['checked'] ?? '' ) );
	$row[ $k . 'notice_until' ] = str_replace( '-', '', (string) ( $r['notice_until'] ?? '' ) );
	$row[ $k . 'offers' ]       = array();
	foreach ( (array) ( $r['offers'] ?? array() ) as $o ) {
		$row[ $k . 'offers' ][] = array(
			$k . 'o_product'    => (string) ( $o['product'] ?? '' ),
			$k . 'o_kind'       => (string) ( $o['kind'] ?? '' ),
			$k . 'o_price'      => isset( $o['price'] ) && null !== $o['price'] ? (string) $o['price'] : '',
			$k . 'o_basis'      => (string) ( $o['basis'] ?? '' ),
			$k . 'o_people'     => (string) (int) ( $o['people'] ?? 1 ),
			$k . 'o_duration'   => isset( $o['duration'] ) && null !== $o['duration'] ? (string) (int) $o['duration'] : '',
			$k . 'o_conditions' => (string) ( $o['conditions'] ?? '' ),
			$k . 'o_main'       => ! empty( $o['main'] ) ? 1 : 0,
		);
	}
	return $row;
};

/** A stored row back into ACF field-key form, to keep it on rewrite. */
$keep_acf = static function ( array $r ) use ( $to_acf ): array {
	$acf = $to_acf( $r );
	$acf['field_oria_fa_facility'] = $r['facility'];
	return $acf;
};

/** Comparable form of the stored row, for the "unchanged" check. */
$norm = static fn( array $r ): string => wp_json_encode( array_diff_key( $r, array( 'index' => 1 ) ) );

$rollback = array();
$summary  = array( 'written' => 0, 'unchanged' => 0, 'skipped' => 0, 'missing' => 0, 'terms' => 0 );

foreach ( $data['venues'] as $v ) {
	$slug = (string) ( $v['slug'] ?? '' );
	$post = get_page_by_path( $slug, OBJECT, 'listing' );
	if ( ! $post instanceof WP_Post && ! empty( $v['create'] ) ) {
		/*
		 * A venue the directory does not have yet: created as a DRAFT in the
		 * public-source research workflow (Sources: batch, review
		 * "scraped"), so the publish guard holds it until an editor has
		 * reviewed it in the listing's "Public-source research" box. Google
		 * Places stays off until they tick it. Matched by slug on a rerun,
		 * so it is never created twice.
		 */
		$c = (array) $v['create'];
		if ( ! $apply ) {
			WP_CLI::log( "  + {$slug}: would create DRAFT listing \"{$c['title']}\" ({$c['suburb']}, region {$c['region']}) and its {$facility} row" );
			++$summary['written'];
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'listing',
				'post_status'  => 'draft',
				'post_title'   => (string) $c['title'],
				'post_name'    => $slug,
				'post_excerpt' => (string) ( $c['excerpt'] ?? '' ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( "{$slug}: could not create -- " . $id->get_error_message() );
			++$summary['skipped'];
			continue;
		}
		update_field( 'field_oria_website', (string) ( $v['website'] ?? '' ), $id );
		update_field( 'field_oria_address', (string) $c['suburb'] . ' WA', $id );
		update_field( 'field_oria_kind', (string) ( $c['kind'] ?? 'place' ), $id );
		update_field( 'field_oria_join_url', (string) ( $c['join_url'] ?? '' ), $id );
		wp_set_object_terms( $id, array( (string) $c['practice'] ), 'practice' );
		$areas = array_values( array_filter( array( (string) $c['region'], (string) ( $c['suburb_term'] ?? '' ) ) ) );
		wp_set_object_terms( $id, $areas, 'area' );
		$batch = (string) ( $data['batch'] ?? gmdate( 'Y-m-d' ) . '-' . $facility );
		update_post_meta( $id, '_oria_src_batch', $batch );
		update_post_meta( $id, '_oria_src_key', $slug );
		update_post_meta( $id, '_oria_review', 'scraped' );
		update_post_meta( $id, '_oria_src_checked', (string) ( $data['checked'] ?? '' ) );
		update_post_meta( $id, '_oria_src_evidence', wp_slash( wp_json_encode( (array) ( $c['evidence'] ?? array() ) ) ) );
		update_post_meta( $id, '_oria_src_urls', wp_slash( wp_json_encode( (array) ( $v['row']['sources'] ?? array() ) ) ) );
		WP_CLI::log( "  + {$slug} (#{$id}): created DRAFT listing, held for review" );
		$rollback[ $slug ] = array( 'id' => (int) $id, 'created' => true );
		$post = get_post( (int) $id );
	}
	if ( ! $post instanceof WP_Post ) {
		WP_CLI::warning( "{$slug}: no listing with this slug -- skipped (create it through the listing workflow first)." );
		++$summary['missing'];
		continue;
	}
	$id   = (int) $post->ID;
	$want = $host( (string) ( $v['website'] ?? '' ) );
	$have = $host( (string) get_post_meta( $id, 'website', true ) );
	if ( '' !== $want && '' !== $have && $want !== $have ) {
		WP_CLI::warning( "{$slug} (#{$id}): website on file is {$have}, data is for {$want} -- skipped as a possible other branch." );
		++$summary['skipped'];
		continue;
	}

	$row     = (array) $v['row'];
	$current = FA\facility( $id, $facility );
	if ( $current && '' !== $current['checked'] && '' !== (string) ( $row['checked'] ?? '' ) && $current['checked'] > (string) $row['checked'] ) {
		WP_CLI::warning( "{$slug} (#{$id}): stored row checked {$current['checked']} is newer than the data ({$row['checked']}) -- an editor's correction wins; skipped." );
		++$summary['skipped'];
		continue;
	}

	// Rebuild the repeater: every other facility row as it is, this one new.
	$rows = array();
	foreach ( FA\rows( $id ) as $r ) {
		if ( $r['facility'] !== $facility ) {
			$rows[] = $keep_acf( $r );
		}
	}
	$rows[] = $to_acf( $row );

	// What would change, in words.
	$changes = array();
	if ( ! $current ) {
		$changes[] = 'new ' . $facility . ' row';
	} else {
		foreach ( array( 'status', 'setting', 'casual', 'condition', 'booking', 'min_age', 'swimwear', 'towels', 'quiet', 'book_url', 'notice', 'checked' ) as $f ) {
			$old = (string) ( $current[ $f ] ?? '' );
			$new = (string) ( $row[ $f ] ?? '' );
			if ( $old !== $new ) {
				$changes[] = "{$f}: '{$old}' -> '{$new}'";
			}
		}
		// Every offer field: a changed basis or condition is a change too.
		$offer_key = static fn( $o ): array => array(
			(string) ( $o['product'] ?? '' ),
			(string) ( $o['kind'] ?? '' ),
			isset( $o['price'] ) && null !== $o['price'] && '' !== $o['price'] ? (float) $o['price'] : null,
			(string) ( $o['basis'] ?? '' ),
			max( 1, (int) ( $o['people'] ?? 1 ) ),
			isset( $o['duration'] ) && null !== $o['duration'] && '' !== $o['duration'] ? (int) $o['duration'] : null,
			(string) ( $o['conditions'] ?? '' ),
			! empty( $o['main'] ),
		);
		$old_o = wp_json_encode( array_map( $offer_key, $current['offers'] ) );
		$new_o = wp_json_encode( array_map( $offer_key, (array) ( $row['offers'] ?? array() ) ) );
		if ( $old_o !== $new_o ) {
			$changes[] = 'offers: ' . $old_o . ' -> ' . $new_o;
		}
		foreach ( array( 'includes', 'essentials', 'sources' ) as $f ) {
			if ( implode( '|', $current[ $f ] ) !== implode( '|', (array) ( $row[ $f ] ?? array() ) ) ) {
				$changes[] = "{$f} updated";
			}
		}
	}
	$add_term = 'confirmed' === ( $row['status'] ?? '' ) && '' !== $service && ! has_term( $service, 'service', $id );
	if ( $add_term ) {
		$changes[] = "+ service term '{$service}'";
	}

	if ( ! $changes ) {
		WP_CLI::log( "  = {$slug} (#{$id}): unchanged" );
		++$summary['unchanged'];
		continue;
	}
	WP_CLI::log( "  ~ {$slug} (#{$id}): " . implode( '; ', $changes ) );

	if ( $apply ) {
		$rollback[ $slug ] = array(
			'id'       => $id,
			'rows'     => FA\rows( $id ),
			'services' => wp_get_post_terms( $id, 'service', array( 'fields' => 'slugs' ) ),
		);
		update_field( 'field_oria_fa_rows', $rows, $id );
		if ( $add_term ) {
			wp_set_post_terms( $id, array( $service ), 'service', true );
			++$summary['terms'];
		}
		clean_post_cache( $id );
	}
	++$summary['written'];
}

if ( $apply && $rollback ) {
	$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'oria-rollback';
	wp_mkdir_p( $dir );
	$path = $dir . '/facility-access-' . $facility . '-' . gmdate( 'Ymd-His' ) . '.json';
	file_put_contents( $path, wp_json_encode( $rollback, JSON_PRETTY_PRINT ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	WP_CLI::log( 'Rollback snapshot: ' . $path );
	if ( function_exists( '\Oria\Core\PracticesIndex\flush_sitemap_cache' ) ) {
		\Oria\Core\PracticesIndex\flush_sitemap_cache();
	}
}

WP_CLI::success(
	sprintf(
		'%s: %d to write, %d unchanged, %d skipped, %d missing; %d service terms added.',
		$apply ? 'Applied' : 'Dry run',
		$summary['written'],
		$summary['unchanged'],
		$summary['skipped'],
		$summary['missing'],
		$summary['terms']
	)
);
