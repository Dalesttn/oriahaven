<?php
/**
 * Light Divine (Eileen Cullen, City Beach): the owner's two corrections,
 * from her email of 23 Nov 2026.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/fix-light-divine.php
 *
 * 1. Name: "Light Divine", not "Light Divine Wellness Centre" -- she is a
 *    sole practitioner. The address (light-divine-wellness) stays.
 * 2. Google: the listing was matched to "Colleen Pearce Sound healer Perth"
 *    and showed Colleen's reviews. The wrong record, reviews and place ID
 *    are cleared and the place is looked up again by the corrected name and
 *    City Beach. Only a result whose Google name contains "Light Divine" is
 *    accepted; it is then pinned, its reviews fetched once (reviews are
 *    otherwise frozen) and its photos downloaded. Anything else and the
 *    Google section is switched off for the listing -- she asked for
 *    nothing rather than somebody else's reviews.
 *
 * Safe to rerun.
 *
 * @package Oria\Core
 */

use Oria\Core\Places;

defined( 'ABSPATH' ) || exit;

$post = get_page_by_path( 'light-divine-wellness', OBJECT, 'listing' );
if ( ! $post instanceof WP_Post ) {
	WP_CLI::error( 'No light-divine-wellness listing.' );
}
$id = (int) $post->ID;

// 1. The name.
wp_update_post( array( 'ID' => $id, 'post_title' => 'Light Divine' ) );
WP_CLI::log( 'Name: Light Divine' );

// 2. Clear the wrong business's data.
$old = get_post_meta( $id, Places\META_CACHE, true );
WP_CLI::log( 'Was linked to: ' . ( is_array( $old ) ? (string) ( $old['place_name'] ?? '?' ) : 'nothing' ) );
delete_post_meta( $id, Places\META_CACHE );
delete_post_meta( $id, Places\META_REVIEWS );
delete_post_meta( $id, Places\META_LOCAL );
delete_post_meta( $id, '_oria_places_ok' );
update_field( 'google_place_id', '', $id );
delete_transient( 'oria_places_backoff_' . $id );

$hide = static function ( string $why ) use ( $id ): void {
	update_field( 'places_hide', 1, $id );
	clean_post_cache( $id );
	do_action( 'litespeed_purge_post', $id );
	WP_CLI::warning( $why . ' -- Google section switched off for this listing, as the owner asked.' );
};

// 3. Look the business up again, by its corrected name and suburb.
$record = Places\fetch( $id, '', true );
if ( null === $record ) {
	$hide( 'Google returned no matching place' );
	return;
}
if ( false === stripos( (string) $record['place_name'], 'light divine' ) ) {
	update_field( 'google_place_id', '', $id );
	$hide( 'Google offered "' . $record['place_name'] . '", not Light Divine' );
	return;
}
$place_id = trim( (string) get_field( 'google_place_id', $id ) );
update_post_meta( $id, Places\META_CACHE, $record );
update_post_meta( $id, '_oria_places_ok', '1' ); // checked by hand: her own profile
update_field( 'places_hide', 0, $id );
WP_CLI::log( sprintf( 'Now linked to: %s (%s), %.1f stars from %d reviews', $record['place_name'], $place_id, $record['rating'], $record['count'] ) );

// 4. Her reviews, fetched once (the routine fetch is frozen).
$r = wp_remote_get(
	sprintf( Places\DETAILS_URL, rawurlencode( $place_id ) ),
	array(
		'timeout' => 10,
		'headers' => array(
			'X-Goog-Api-Key'   => Places\server_key(),
			'X-Goog-FieldMask' => 'reviews',
		),
	)
);
$body = Places\decode( $r );
if ( null !== $body ) {
	$items = (array) ( Places\pack( $body )['reviews'] ?? array() );
	update_post_meta( $id, Places\META_REVIEWS, array( 'items' => $items, 'ts' => time() ) );
	WP_CLI::log( sprintf( 'Reviews saved: %d', count( $items ) ) );
} else {
	WP_CLI::log( 'Reviews call failed: the listing shows the rating without review text.' );
}

// 5. Her photos, unless she has uploaded her own.
if ( ! has_post_thumbnail( $id ) && ! get_post_meta( $id, 'gallery', true ) ) {
	$ph = Places\download_photos( $id );
	WP_CLI::log( 'Photos: ' . $ph['status'] . ( $ph['saved'] ? " ({$ph['saved']})" : '' ) );
}

clean_post_cache( $id );
do_action( 'litespeed_purge_post', $id );
WP_CLI::success( sprintf( '#%d is "Light Divine", linked to its own Google profile. %s', $id, get_permalink( $id ) ) );
