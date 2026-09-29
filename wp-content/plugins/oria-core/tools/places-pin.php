<?php
/**
 * Pin a listing to the right Google place, so its profile shows that
 * place's photos (through the Places API, as the site already does) instead
 * of trusting the name match -- which picked the wrong business for 4 of 8
 * research-batch listings.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/places-pin.php <slug>
 *       Lists up to five Google candidates (id, name, address) for the
 *       listing's name and address. Changes nothing.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/places-pin.php <slug> "<search text>"
 *       The same, with your own search text (e.g. the Google Maps name).
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/places-pin.php <slug> pin <place_id>
 *       Saves that place ID, turns Google Places on for the listing (the
 *       "Places ok" box in its research panel) and clears its cached
 *       place data so the next view fetches the pinned place.
 *
 * Needs the server key (ORIA_GOOGLE_SERVER_KEY), so run it on the server.
 *
 * @package Oria\Core
 */

use Oria\Core\Places;

$slug = sanitize_title( (string) ( $args[0] ?? '' ) );
$post = '' !== $slug ? get_page_by_path( $slug, OBJECT, 'listing' ) : null;
if ( ! $post instanceof WP_Post ) {
	WP_CLI::error( 'Usage: places-pin.php <listing slug> [search text | pin <place_id>]' );
}
$id = (int) $post->ID;

if ( 'pin' === (string) ( $args[1] ?? '' ) ) {
	$place = trim( (string) ( $args[2] ?? '' ) );
	if ( ! preg_match( '/^[A-Za-z0-9_-]{10,}$/', $place ) ) {
		WP_CLI::error( 'That does not look like a Google place ID.' );
	}
	update_field( 'google_place_id', $place, $id );
	update_post_meta( $id, '_oria_places_ok', '1' );
	delete_post_meta( $id, Places\META_CACHE );
	delete_transient( 'oria_places_backoff_' . $id );
	clean_post_cache( $id );
	WP_CLI::success( "{$slug} (#{$id}) pinned to {$place}; Google Places is on for it. View the profile once to fetch the photos, then purge the page cache." );
	return;
}

$key = Places\server_key();
if ( '' === $key ) {
	WP_CLI::error( 'No Google server key on this install (ORIA_GOOGLE_SERVER_KEY). Run this on the server.' );
}
$query = trim( (string) ( $args[1] ?? '' ) );
if ( '' === $query ) {
	$query = $post->post_title;
	$addr  = (string) get_post_meta( $id, 'address', true );
	if ( '' !== $addr ) {
		$query .= ', ' . $addr;
	}
}

$response = wp_remote_post(
	Places\SEARCH_URL,
	array(
		'timeout' => 10,
		'headers' => array(
			'Content-Type'     => 'application/json',
			'X-Goog-Api-Key'   => $key,
			'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.photos.name',
		),
		'body'    => (string) wp_json_encode( array( 'textQuery' => $query, 'regionCode' => 'AU', 'pageSize' => 5 ) ),
	)
);
$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
if ( empty( $body['places'] ) ) {
	WP_CLI::error( 'Google returned no candidates for: ' . $query );
}

WP_CLI::log( "Candidates for {$slug} (#{$id}), searched as: {$query}" );
foreach ( $body['places'] as $i => $p ) {
	WP_CLI::log(
		sprintf(
			'  %d. %s -- %s (%d photos)%s    id: %s',
			$i + 1,
			(string) ( $p['displayName']['text'] ?? '?' ),
			(string) ( $p['formattedAddress'] ?? '?' ),
			count( (array) ( $p['photos'] ?? array() ) ),
			"\n",
			(string) ( $p['id'] ?? '?' )
		)
	);
}
WP_CLI::log( 'Check the name AND address against the venue, then: places-pin.php ' . $slug . ' pin <id>' );
