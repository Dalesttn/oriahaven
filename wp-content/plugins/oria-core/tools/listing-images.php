<?php
/**
 * Give listings a stock photo (Pexels) from data/images/<file>.json.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/listing-images.php <file>          (dry run)
 *   wp eval-file wp-content/plugins/oria-core/tools/listing-images.php <file> apply    (write)
 *
 * - Never replaces an image a listing already has (an owner's or editor's
 *   photo always wins).
 * - "only_if_no_google": a listing whose pinned Google place supplies real
 *   photos keeps those; a listing image would otherwise hide them, because
 *   the site shows the featured image first.
 * - Downloads the photo into the media library and credits the
 *   photographer (_oria_image_source / caption). Pexels photos are free to
 *   use; the credit is a courtesy.
 * Idempotent: a listing with an image reports "has an image".
 *
 * @package Oria\Core
 */

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$name  = sanitize_key( (string) ( $args[0] ?? '' ) );
$apply = 'apply' === (string) ( $args[1] ?? '' );
$file  = ORIA_CORE_DIR . 'data/images/' . $name . '.json';
if ( '' === $name || ! is_readable( $file ) ) {
	WP_CLI::error( 'Usage: listing-images.php <data file> [apply]; no file at ' . $file );
}
$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
WP_CLI::log( ( $apply ? 'APPLY' : 'DRY RUN' ) . " -- {$name}" );

$n = array( 'set' => 0, 'kept' => 0, 'google' => 0, 'missing' => 0 );
foreach ( (array) ( $data['listings'] ?? array() ) as $row ) {
	$slug = (string) $row['slug'];
	$post = get_page_by_path( $slug, OBJECT, 'listing' );
	if ( ! $post instanceof WP_Post ) {
		WP_CLI::warning( "{$slug}: no listing -- run its import first." );
		++$n['missing'];
		continue;
	}
	$id = (int) $post->ID;
	if ( has_post_thumbnail( $id ) ) {
		WP_CLI::log( "  = {$slug}: has an image -- kept" );
		++$n['kept'];
		continue;
	}
	if ( ! empty( $row['only_if_no_google'] ) && function_exists( '\Oria\Core\Places\photos_for' ) && '1' === (string) get_post_meta( $id, '_oria_places_ok', true ) ) {
		$g = \Oria\Core\Places\photos_for( $id );
		if ( ! empty( $g['urls'] ) ) {
			WP_CLI::log( "  = {$slug}: has " . count( $g['urls'] ) . ' Google photos of the venue -- kept, no stock photo' );
			++$n['google'];
			continue;
		}
	}
	if ( ! $apply ) {
		WP_CLI::log( "  + {$slug}: would set Pexels #{$row['pexels_id']} by {$row['photographer']}" );
		++$n['set'];
		continue;
	}
	$att = media_sideload_image( (string) $row['src'], $id, (string) $row['alt'], 'id' );
	if ( is_wp_error( $att ) ) {
		WP_CLI::warning( "{$slug}: download failed -- " . $att->get_error_message() );
		continue;
	}
	$att = (int) $att;
	update_post_meta( $att, '_wp_attachment_image_alt', (string) $row['alt'] );
	update_post_meta( $att, '_oria_image_source', (string) $row['page'] );
	wp_update_post( array( 'ID' => $att, 'post_excerpt' => sprintf( 'Photo: %s on Pexels (stock photo, not the venue)', (string) $row['photographer'] ) ) );
	set_post_thumbnail( $id, $att );
	WP_CLI::log( "  + {$slug}: set Pexels #{$row['pexels_id']} (attachment #{$att})" );
	++$n['set'];
}

WP_CLI::success( sprintf( '%s: %d set, %d already had an image, %d kept their Google photos, %d missing.', $apply ? 'Applied' : 'Dry run', $n['set'], $n['kept'], $n['google'], $n['missing'] ) );
