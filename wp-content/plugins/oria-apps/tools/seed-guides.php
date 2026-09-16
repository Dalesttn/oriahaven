<?php
/**
 * Create the editorial app collections from data/guides-seed.json.
 *
 * Usage (from the WordPress root, on the server):
 *   php wp-content/plugins/oria-apps/tools/seed-guides.php            # dry run
 *   php wp-content/plugins/oria-apps/tools/seed-guides.php --apply    # creates a draft
 *   php wp-content/plugins/oria-apps/tools/seed-guides.php --apply --update
 *   php wp-content/plugins/oria-apps/tools/seed-guides.php --apply --publish
 *
 * Same rules as the app seeder: a draft unless --publish, and an existing
 * guide is left alone unless --update, so a re-run cannot overwrite an
 * editor's reordering.
 *
 * A pick whose app is missing is reported and skipped rather than written
 * as an empty row — a collection that silently loses its third entry is
 * how a published page ends up saying "10 best" above nine apps. Run the
 * app seeder first.
 *
 * Dry run is the default and writes nothing.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

use Oria\Apps\Data;
use Oria\Apps\Guides;

$apply   = in_array( '--apply', $argv ?? array(), true );
$update  = in_array( '--update', $argv ?? array(), true );
$publish = in_array( '--publish', $argv ?? array(), true );

$file = ORIA_APPS_DIR . 'data/guides-seed.json';
$json = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions

if ( ! is_array( $json ) || empty( $json['guides'] ) ) {
	fwrite( STDERR, "Cannot read {$file}\n" );
	exit( 1 );
}

if ( $apply ) {
	echo $publish ? "APPLY — guides are created and published\n\n" : "APPLY — guides are created as drafts\n\n";
} else {
	echo "DRY RUN — nothing is written. Add --apply to create.\n\n";
}

$checked = (string) ( $json['_checked'] ?? gmdate( 'Y-m-d' ) );
$made    = 0;
$changed = 0;
$skipped = 0;
$missing = 0;

foreach ( $json['guides'] as $guide ) {
	$slug     = (string) ( $guide['slug'] ?? '' );
	$existing = '' !== $slug ? get_page_by_path( $slug, OBJECT, Guides\CPT ) : null;

	if ( $existing && ! $update ) {
		printf( "  = %-30s already here (#%d, %s) — pass --update to refresh\n", $slug, $existing->ID, $existing->post_status );
		$skipped++;
		continue;
	}

	printf( "  %s %-30s %s\n", $existing ? '~' : '+', $slug, (string) $guide['title'] );

	// Resolve every pick before writing anything, so a half-seeded guide
	// is never the outcome of a missing app.
	$picks = array();
	foreach ( (array) ( $guide['picks'] ?? array() ) as $pick ) {
		$app_slug = (string) ( $pick['app'] ?? '' );
		$app      = '' !== $app_slug ? get_page_by_path( $app_slug, OBJECT, Data\CPT ) : null;

		if ( ! $app instanceof WP_Post ) {
			printf( "      ! %-22s no such app — run seed-apps.php first\n", $app_slug );
			$missing++;
			continue;
		}
		if ( 'publish' !== $app->post_status ) {
			printf( "      ! %-22s is a %s, so it will not show on the guide\n", $app_slug, $app->post_status );
		}
		$picks[] = array(
			'id'       => (int) $app->ID,
			'best_for' => (string) ( $pick['best_for'] ?? '' ),
			'blurb'    => (string) ( $pick['blurb'] ?? '' ),
		);
	}

	printf( "      %d picks, %d questions\n", count( $picks ), count( (array) ( $guide['faq'] ?? array() ) ) );

	if ( ! $apply ) {
		continue;
	}

	$post = array(
		'post_type'    => Guides\CPT,
		'post_title'   => (string) $guide['title'],
		'post_name'    => $slug,
		'post_excerpt' => (string) ( $guide['excerpt'] ?? '' ),
	);

	if ( $existing ) {
		$post['ID'] = $existing->ID;
		// Otherwise the status is left as the editor set it: a guide they
		// have published must not drop back to draft on a refresh.
		if ( $publish ) {
			$post['post_status'] = 'publish';
		}
		$id = wp_update_post( $post, true );
		$changed++;
	} else {
		$post['post_status'] = $publish ? 'publish' : 'draft';
		$id                  = wp_insert_post( $post, true );
		$made++;
	}

	if ( is_wp_error( $id ) ) {
		printf( "      ! %s\n", $id->get_error_message() );
		continue;
	}
	$id = (int) $id;

	update_post_meta( $id, 'guide_subtitle', (string) ( $guide['subtitle'] ?? '' ) );
	update_post_meta( $id, '_guide_subtitle', 'field_oria_guide_subtitle' );
	update_post_meta( $id, 'guide_intro', (string) ( $guide['intro'] ?? '' ) );
	update_post_meta( $id, '_guide_intro', 'field_oria_guide_intro' );
	update_post_meta( $id, 'guide_checked', $checked );
	update_post_meta( $id, '_guide_checked', 'field_oria_guide_checked' );

	$hero = hero( $id, $slug );
	if ( '' !== $hero ) {
		printf( "      header picture: %s\n", $hero );
	}

	write_picks( $id, $picks );
	write_faq( $id, (array) ( $guide['faq'] ?? array() ) );

	printf( "      #%d written\n", $id );
}

echo "\n";
if ( $apply ) {
	printf( "Created %d, refreshed %d, left alone %d.\n", $made, $changed, $skipped );
	if ( $missing ) {
		printf( "%d picks could not be matched to an app. Run seed-apps.php, then re-run this with --update.\n", $missing );
	}
	echo $publish
		? "They are live. The order in the file is the order readers see.\n"
		: "They are drafts. Read one through, then publish.\n";
} else {
	printf( "Would create %d, refresh %d, leave alone %d.\n", count( $json['guides'] ) - $skipped, 0, $skipped );
}

/**
 * Put the guide's header picture in the media library and set it as the
 * featured image.
 *
 * The file ships in the plugin, so the same picture lands on every
 * environment without anyone uploading anything -- the same arrangement
 * the app icons use. Skipped when the guide already has one, because an
 * editor who replaced it should not have their choice undone by a re-run,
 * and skipped silently when there is no file, because a guide without a
 * header picture renders the plain header instead.
 *
 * It is already 1920x1080, which is what `oria-wide` crops to, so nothing
 * is re-cropped on the way in.
 */
function hero( int $id, string $slug ): string {
	if ( has_post_thumbnail( $id ) ) {
		return '';
	}

	$file = ORIA_APPS_DIR . 'assets/heroes/' . $slug . '.webp';
	if ( ! is_readable( $file ) ) {
		return '';
	}

	// Imported once on an earlier run: reuse it rather than filling the
	// library with copies.
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_oria_guide_hero', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( $existing ) {
		set_post_thumbnail( $id, (int) $existing[0] );
		return 'reused #' . (int) $existing[0];
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$uploaded = wp_upload_bits( $slug . '-hero.webp', null, (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! empty( $uploaded['error'] ) ) {
		return 'failed: ' . $uploaded['error'];
	}

	$attachment = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => get_the_title( $id ) . ' header',
			'post_status'    => 'inherit',
		),
		$uploaded['file'],
		$id
	);
	if ( is_wp_error( $attachment ) || ! $attachment ) {
		return 'failed to attach';
	}

	wp_update_attachment_metadata( (int) $attachment, wp_generate_attachment_metadata( (int) $attachment, $uploaded['file'] ) );
	update_post_meta( (int) $attachment, '_oria_guide_hero', $slug );
	// Decoration behind a headline that already says what the page is, so
	// the alt text stays empty rather than describing furniture.
	update_post_meta( (int) $attachment, '_wp_attachment_image_alt', '' );

	set_post_thumbnail( $id, (int) $attachment );

	return 'imported #' . (int) $attachment;
}

/**
 * The picks repeater: the ordering an editor sees and can drag.
 *
 * Old rows are cleared first. Without that, shortening a list from ten to
 * eight leaves rows 8 and 9 in the database while the count says eight —
 * invisible until someone lengthens the list again and the old rows
 * reappear halfway down.
 *
 * @param list<array{id:int, best_for:string, blurb:string}> $picks
 */
function write_picks( int $id, array $picks ): void {
	$was = (int) get_post_meta( $id, 'picks', true );
	for ( $i = 0; $i < max( $was, count( $picks ) ); $i++ ) {
		foreach ( array( 'app', 'best_for', 'blurb' ) as $sub ) {
			delete_post_meta( $id, 'picks_' . $i . '_' . $sub );
			delete_post_meta( $id, '_picks_' . $i . '_' . $sub );
		}
	}

	$keys = array(
		'app'      => 'field_oria_guide_pick_app',
		'best_for' => 'field_oria_guide_pick_best_for',
		'blurb'    => 'field_oria_guide_pick_blurb',
	);

	foreach ( $picks as $i => $pick ) {
		update_post_meta( $id, 'picks_' . $i . '_app', $pick['id'] );
		update_post_meta( $id, 'picks_' . $i . '_best_for', $pick['best_for'] );
		update_post_meta( $id, 'picks_' . $i . '_blurb', $pick['blurb'] );
		foreach ( $keys as $sub => $key ) {
			update_post_meta( $id, '_picks_' . $i . '_' . $sub, $key );
		}
	}

	update_post_meta( $id, 'picks', count( $picks ) );
	update_post_meta( $id, '_picks', 'field_oria_guide_picks' );
}

/** @param list<array{question?:string, answer?:string}> $rows */
function write_faq( int $id, array $rows ): void {
	$was = (int) get_post_meta( $id, 'faq', true );
	for ( $i = 0; $i < max( $was, count( $rows ) ); $i++ ) {
		foreach ( array( 'question', 'answer' ) as $sub ) {
			delete_post_meta( $id, 'faq_' . $i . '_' . $sub );
			delete_post_meta( $id, '_faq_' . $i . '_' . $sub );
		}
	}

	foreach ( $rows as $i => $row ) {
		update_post_meta( $id, 'faq_' . $i . '_question', (string) ( $row['question'] ?? '' ) );
		update_post_meta( $id, 'faq_' . $i . '_answer', (string) ( $row['answer'] ?? '' ) );
		update_post_meta( $id, '_faq_' . $i . '_question', 'field_oria_guide_faq_q' );
		update_post_meta( $id, '_faq_' . $i . '_answer', 'field_oria_guide_faq_a' );
	}

	update_post_meta( $id, 'faq', count( $rows ) );
	update_post_meta( $id, '_faq', 'field_oria_guide_faq' );
}
