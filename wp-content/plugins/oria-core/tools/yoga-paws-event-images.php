<?php
/**
 * Featured images for the Yoga Paws What's On events.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/yoga-paws-event-images.php
 *
 * Run after add-yoga-paws.php and the events import. Every event linked to
 * the Yoga Paws listing (the `listing` meta) that has no image gets one of
 * Yoga Paws' own session photos, used with their permission (via Dale),
 * cropped landscape for the cards, so five dates show five different photos.
 * Events are taken in date order and the photos cycle. Safe to rerun: events that
 * already have an image are left alone, so later months can use it too.
 *
 * @package Oria\Core
 */

defined( 'ABSPATH' ) || exit;

$listing = get_page_by_path( 'yoga-paws-perth', OBJECT, 'listing' );
if ( ! $listing instanceof WP_Post ) {
	WP_CLI::error( 'Run add-yoga-paws.php first: the Yoga Paws listing does not exist.' );
}
$lid = (int) $listing->ID;

// Landscape (3:2) crops of their photos, made for the event cards: a
// portrait photo is shown whole as a "poster" with bars either side, so the
// listing's portrait shots are not reused here. Uploaded once, remembered.
$pool = array_values( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $lid, '_oria_yp_event_photos', true ) ) ), 'wp_attachment_is_image' ) );
if ( ! $pool ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$files = array(
		'yoga-paws-event-1.webp' => 'A guest cuddling a golden retriever puppy at a Yoga Paws session',
		'yoga-paws-event-2.webp' => 'A spaniel puppy standing on a yoga mat during a Yoga Paws class',
		'yoga-paws-event-3.webp' => 'A dachshund puppy resting on a guest\'s lap during a Yoga Paws class',
		'yoga-paws-event-4.webp' => 'A bulldog puppy sitting on a yoga mat at a Yoga Paws session',
		'yoga-paws-event-5.webp' => 'A guest holding a fluffy puppy at a Yoga Paws session',
	);
	foreach ( $files as $file => $alt ) {
		$src = __DIR__ . '/assets/' . $file;
		if ( ! is_readable( $src ) ) {
			continue;
		}
		$tmp = wp_tempnam( $file );
		copy( $src, $tmp );
		$att = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), $lid, 'Yoga Paws puppy yoga' );
		if ( is_wp_error( $att ) ) {
			WP_CLI::warning( $file . ': ' . $att->get_error_message() );
			continue;
		}
		update_post_meta( (int) $att, '_wp_attachment_image_alt', $alt );
		update_post_meta( (int) $att, '_oria_image_source', 'yoga-paws.com (used with permission, via Dale)' );
		$pool[] = (int) $att;
	}
	update_post_meta( $lid, '_oria_yp_event_photos', implode( ',', $pool ) );
}
if ( ! $pool ) {
	WP_CLI::error( 'No Yoga Paws event photos found to use.' );
}

$events = get_posts(
	array(
		'post_type'      => 'event',
		'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
		'posts_per_page' => -1,
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array( array( 'key' => 'listing', 'value' => (string) $lid ) ),
	)
);

$set = 0;
$i   = 0;
foreach ( $events as $e ) {
	$att = $pool[ $i % count( $pool ) ];
	++$i;
	if ( has_post_thumbnail( $e ) ) {
		continue;
	}
	set_post_thumbnail( $e->ID, $att );
	do_action( 'litespeed_purge_post', $e->ID );
	WP_CLI::log( sprintf( '  + #%d %s (%s): photo #%d', $e->ID, $e->post_title, get_post_meta( $e->ID, 'event_start', true ), $att ) );
	++$set;
}

WP_CLI::success( sprintf( '%d of %d Yoga Paws events given a photo.', $set, count( $events ) ) );
