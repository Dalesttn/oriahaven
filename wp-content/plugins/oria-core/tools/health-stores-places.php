<?php
/**
 * Pin the 12 Health Food Stores (batch 2026-10-09-health-stores) to their
 * Google places and turn Google photos/reviews on for them.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/health-stores-places.php
 *
 * Each place ID was matched on 9 Oct 2026 by name AND street address
 * against the store's own website (one candidate each, via places-pin.php).
 * Same effect as places-pin.php <slug> pin <id>. Safe to rerun.
 *
 * @package Oria\Core
 */

use Oria\Core\Places;

defined( 'ABSPATH' ) || exit;

$pins = array(
	'earth-wholefoods-joondalup'             => 'ChIJR9wBcWhTzSsRhBtY_epY0Kc', // 3/25 Delage St, Joondalup
	'the-little-big-store-wangara'           => 'ChIJt4U2PAmtMioRIjy0IXdUny8', // 1/53 Prindiville Dr, Wangara
	'dunn-walton-doubleview'                 => 'ChIJdeKzU_yuMioR-ZZvbIO1_C4', // 8/257 Scarborough Beach Rd, Doubleview
	'scoop-wholefoods-cottesloe-cottesloe'   => 'ChIJOy8GMLWlMioRtP6h70j9nR4', // 460 Stirling Hwy (Google: Peppermint Grove)
	'loose-produce-victoria-park'            => 'ChIJnfw0VFm7MioRK0E6cA23dG0', // 171 Albany Hwy, Victoria Park
	'replenish-kalamunda'                    => 'ChIJpXOuSLzAMioRJEYq8LhWlw8', // Shop 12/12 Barber St, Kalamunda
	'wasteless-pantry-mundaring-mundaring'   => 'ChIJZS9hfB1CrwERdNGre8aWKfU', // 17/7295 Great Eastern Hwy, Mundaring
	'bassendean-wasteless-pantry-bassendean' => 'ChIJlbb0RGC5MioRW9gzBq-Oj2g', // Shop 15, 2 West Rd, Bassendean
	'the-organic-collective-hamilton-hill'   => 'ChIJ6xorMEKiMioR4W9GjKMdrkI', // 1-3 Greenslade St, Hamilton Hill
	'sams-health-emporium-willetton'         => 'ChIJvzSbOGu9MioRsmWiUOJtse8', // Southlands, 45 Burrendah Blvd, Willetton
	'pantryman-mandurah'                     => 'ChIJQycYu8d5MioRiW4oyFWNgs4', // Shop G020/330 Pinjarra Rd, Mandurah
	'the-wellness-store-mandurah'            => 'ChIJPbFOf1F_MioRNQ2XA9WXzXY', // 106 Anstruther Rd, Mandurah
);

$done = 0;
foreach ( $pins as $slug => $place ) {
	$post = get_page_by_path( $slug, OBJECT, 'listing' );
	if ( ! $post instanceof WP_Post ) {
		WP_CLI::warning( "{$slug}: listing not found -- skipped" );
		continue;
	}
	$id = (int) $post->ID;
	update_field( 'google_place_id', $place, $id );
	update_post_meta( $id, '_oria_places_ok', '1' );
	delete_post_meta( $id, Places\META_CACHE );
	delete_transient( 'oria_places_backoff_' . $id );
	clean_post_cache( $id );
	do_action( 'litespeed_purge_post', $id );
	WP_CLI::log( "  pinned {$slug} (#{$id})" );
	$done++;
}
WP_CLI::success( "{$done} store(s) pinned; Google photos and reviews are on. View each profile once (or wait for the next visit) to fetch the photos, then purge the page cache." );
