<?php
/**
 * Create the app entries from data/apps-seed.json.
 *
 * Usage (from the WordPress root, on the server):
 *   php wp-content/plugins/oria-apps/tools/seed-apps.php            # dry run
 *   php wp-content/plugins/oria-apps/tools/seed-apps.php --apply    # creates drafts
 *   php wp-content/plugins/oria-apps/tools/seed-apps.php --apply --update
 *   php wp-content/plugins/oria-apps/tools/seed-apps.php --apply --publish
 *
 * Drafts by default: an app page carries prices and claims about someone
 * else's product, so the default is that a person reads it first. --publish
 * says that reading has happened and publishes as it goes. --update
 * refreshes an app this script created before; without it an existing slug
 * is left alone, so a re-run cannot quietly overwrite an editor's changes.
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

$apply   = in_array( '--apply', $argv ?? array(), true );
$update  = in_array( '--update', $argv ?? array(), true );
$publish = in_array( '--publish', $argv ?? array(), true );

$file = ORIA_APPS_DIR . 'data/apps-seed.json';
$json = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions

if ( ! is_array( $json ) || empty( $json['apps'] ) ) {
	fwrite( STDERR, "Cannot read {$file}\n" );
	exit( 1 );
}

if ( $apply ) {
	echo $publish ? "APPLY — apps are created and published\n\n" : "APPLY — apps are created as drafts\n\n";
} else {
	echo "DRY RUN — nothing is written. Add --apply to create.\n\n";
}

$checked = (string) ( $json['_checked'] ?? gmdate( 'Y-m-d' ) );
$made    = 0;
$changed = 0;
$skipped = 0;

foreach ( $json['apps'] as $app ) {
	$slug     = (string) ( $app['slug'] ?? '' );
	$existing = '' !== $slug ? get_page_by_path( $slug, OBJECT, Data\CPT ) : null;

	if ( $existing && ! $update ) {
		printf( "  = %-22s already here (#%d, %s) — pass --update to refresh\n", $slug, $existing->ID, $existing->post_status );
		$skipped++;
		continue;
	}

	printf( "  %s %-22s %s\n", $existing ? '~' : '+', $slug, (string) $app['title'] );
	printf( "      %s · %s\n", implode( ', ', (array) $app['categories'] ), (string) $app['pricing_model'] );

	if ( ! $apply ) {
		continue;
	}

	$post = array(
		'post_type'    => Data\CPT,
		'post_title'   => (string) $app['title'],
		'post_name'    => $slug,
		'post_excerpt' => (string) ( $app['excerpt'] ?? '' ),
	);

	if ( $existing ) {
		$post['ID'] = $existing->ID;
		// Status is otherwise left exactly as the editor set it: an app they
		// have already published must not drop back to draft on a refresh.
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
		printf( "      ! failed: %s\n", $id->get_error_message() );
		continue;
	}
	$id = (int) $id;

	wp_set_object_terms( $id, (array) $app['categories'], Data\TAX );

	$meta = array(
		'app_tagline'            => (string) ( $app['tagline'] ?? '' ),
		'developer_name'         => (string) ( $app['developer'] ?? '' ),
		'official_website'       => (string) ( $app['website'] ?? '' ),
		'ios_url'                => (string) ( $app['ios'] ?? '' ),
		'android_url'            => (string) ( $app['android'] ?? '' ),
		'best_for'               => array_values( (array) ( $app['best_for'] ?? array() ) ),
		'oria_take'              => (string) ( $app['take'] ?? '' ),
		'pricing_model'          => (string) ( $app['pricing_model'] ?? 'unknown' ),
		'starting_price'         => (string) ( $app['starting_price'] ?? '' ),
		'free_version_available' => ! empty( $app['free_version'] ) ? 1 : 0,
		'free_trial'             => (string) ( $app['free_trial'] ?? '' ),
		'pricing_notes'          => (string) ( $app['pricing_notes'] ?? '' ),
		'platforms'              => array_values( (array) ( $app['platforms'] ?? array() ) ),
		'last_verified'          => $checked,
		'primary_source_url'     => (string) ( $app['sources'][0] ?? '' ),
		'secondary_source_url'   => (string) ( $app['sources'][1] ?? '' ),
		'affiliate_available'    => 0,
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}

	// Repeaters, written the way ACF stores them: a count on the parent key
	// and one row per index. Old rows are cleared first so a refresh that
	// removes a point does not leave it behind.
	// The first category in the file is the app's main one: the order in
	// the seed is editorial, where the order WordPress returns is not.
	$cats = array_values( (array) ( $app['categories'] ?? array() ) );
	if ( $cats ) {
		update_post_meta( $id, 'primary_category', (string) $cats[0] );
		update_post_meta( $id, '_primary_category', 'field_oria_app_primary_cat' );
	}

	$icon = icon( $id, $slug );
	if ( '' !== $icon ) {
		printf( "      icon: %s\n", $icon );
	}

	repeater( $id, 'key_features', 'text', (array) ( $app['features'] ?? array() ) );
	repeater( $id, 'pros', 'text', (array) ( $app['pros'] ?? array() ) );
	repeater( $id, 'considerations', 'text', (array) ( $app['cons'] ?? array() ) );
	faq( $id, (array) ( $app['faq'] ?? array() ) );

	printf( "      saved #%d (%s)\n", $id, get_post_status( $id ) );
}

/**
 * Put the app's icon in the media library and attach it to the entry.
 *
 * The file ships in the plugin, so the same icon lands on every
 * environment without anyone uploading anything. Skipped when the entry
 * already has an icon -- an editor who replaced it should not have their
 * choice overwritten by a re-run -- and skipped silently when the file is
 * not there, because an app without an icon still renders.
 *
 * These are the apps' own icons, stored locally rather than hotlinked, and
 * used to identify the app being reviewed.
 */
function icon( int $id, string $slug ): string {
	if ( (int) get_post_meta( $id, 'app_logo', true ) ) {
		return '';
	}

	$file = ORIA_APPS_DIR . 'assets/icons/' . $slug . '.webp';
	if ( ! is_readable( $file ) ) {
		return '';
	}

	// An icon already imported for this app on an earlier run: reuse it
	// rather than filling the library with copies.
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_oria_app_icon', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( $existing ) {
		update_post_meta( $id, 'app_logo', (int) $existing[0] );
		update_post_meta( $id, '_app_logo', 'field_oria_app_logo' );
		return 'reused #' . (int) $existing[0];
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$uploaded = wp_upload_bits( $slug . '-icon.webp', null, (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! empty( $uploaded['error'] ) ) {
		return 'failed: ' . $uploaded['error'];
	}

	$attachment = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => get_the_title( $id ) . ' icon',
			'post_status'    => 'inherit',
		),
		$uploaded['file'],
		$id
	);
	if ( is_wp_error( $attachment ) || ! $attachment ) {
		return 'failed to attach';
	}

	wp_update_attachment_metadata( (int) $attachment, wp_generate_attachment_metadata( (int) $attachment, $uploaded['file'] ) );
	update_post_meta( (int) $attachment, '_oria_app_icon', $slug );
	// Decorative next to the app's own name, so the alt text stays empty
	// rather than repeating it to a screen reader.
	update_post_meta( (int) $attachment, '_wp_attachment_image_alt', '' );

	update_post_meta( $id, 'app_logo', (int) $attachment );
	update_post_meta( $id, '_app_logo', 'field_oria_app_logo' );

	return 'imported #' . (int) $attachment;
}

/**
 * @param list<string> $values
 */
function repeater( int $id, string $field, string $sub, array $values ): void {
	$old = (int) get_post_meta( $id, $field, true );
	for ( $i = 0; $i < max( $old, count( $values ) ); $i++ ) {
		delete_post_meta( $id, $field . '_' . $i . '_' . $sub );
		delete_post_meta( $id, '_' . $field . '_' . $i . '_' . $sub );
	}
	list( $parent_key, $sub_key ) = keys_for( $field );
	foreach ( array_values( $values ) as $i => $value ) {
		update_post_meta( $id, $field . '_' . $i . '_' . $sub, (string) $value );
		update_post_meta( $id, '_' . $field . '_' . $i . '_' . $sub, $sub_key );
	}
	update_post_meta( $id, $field, count( $values ) );
	update_post_meta( $id, '_' . $field, $parent_key );
}

/** @param list<array{question:string, answer:string}> $items */
function faq( int $id, array $items ): void {
	$old = (int) get_post_meta( $id, 'faq', true );
	for ( $i = 0; $i < max( $old, count( $items ) ); $i++ ) {
		delete_post_meta( $id, 'faq_' . $i . '_question' );
		delete_post_meta( $id, 'faq_' . $i . '_answer' );
	}
	foreach ( array_values( $items ) as $i => $item ) {
		update_post_meta( $id, 'faq_' . $i . '_question', (string) ( $item['question'] ?? '' ) );
		update_post_meta( $id, 'faq_' . $i . '_answer', (string) ( $item['answer'] ?? '' ) );
		update_post_meta( $id, '_faq_' . $i . '_question', 'field_oria_app_faq_q' );
		update_post_meta( $id, '_faq_' . $i . '_answer', 'field_oria_app_faq_a' );
	}
	update_post_meta( $id, 'faq', count( $items ) );
	update_post_meta( $id, '_faq', 'field_oria_app_faq' );
}

/**
 * The ACF keys for a repeater and its one sub-field.
 *
 * Neither is derivable from the meta name -- the field group calls the
 * rows feature/pro/con while the meta keys are key_features/pros/
 * considerations -- and getting them wrong stores the values where the
 * editor cannot see them, which is worse than not storing them at all.
 *
 * @return array{0: string, 1: string} [parent key, sub-field key]
 */
function keys_for( string $field ): array {
	$map = array(
		'key_features'   => array( 'field_oria_app_features', 'field_oria_app_feature_text' ),
		'pros'           => array( 'field_oria_app_pros', 'field_oria_app_pro_text' ),
		'considerations' => array( 'field_oria_app_cons', 'field_oria_app_con_text' ),
	);
	return $map[ $field ] ?? array( 'field_oria_app_' . $field, 'field_oria_app_' . $field . '_text' );
}

echo "\n";
if ( $apply ) {
	printf( "Created %d, refreshed %d, left alone %d.\n", $made, $changed, $skipped );
	echo $publish
		? "They are live. Each one shows the date it was last checked.\n"
		: "They are drafts. Read each one, then publish.\n";
} else {
	printf( "Would create or refresh %d, leave alone %d. Re-run with --apply.\n", count( $json['apps'] ) - $skipped, $skipped );
}
