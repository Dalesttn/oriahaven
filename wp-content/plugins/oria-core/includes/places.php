<?php
/**
 * Google Places photos for listings that have none of their own.
 *
 * Shaped by Google's terms rather than convenience:
 *  - the place ID may be stored indefinitely (it lives in an ACF field the
 *    admin can see and correct);
 *  - photo references must not be cached beyond 30 days, so they live in
 *    post meta with a timestamp and are re-fetched after 29;
 *  - photos must be shown with their author attributions, which are cached
 *    alongside the references and rendered under the gallery.
 *
 * The lookup (server key) happens in PHP; the actual image bytes are fetched
 * by the visitor's browser straight from Google (browser key in the media
 * URL), so nothing is ever copied into the media library.
 */

declare(strict_types=1);

namespace Oria\Core\Places;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* v5: the record gained the opening hours; the new key retires older
   cache entries cleanly, and the warm cron walks every listing onto it. */
const META_CACHE   = '_oria_places_v5';
const CACHE_DAYS   = 29;
const MAX_PHOTOS   = 3;
const SEARCH_URL   = 'https://places.googleapis.com/v1/places:searchText';
const DETAILS_URL  = 'https://places.googleapis.com/v1/places/%s';
const MEDIA_URL    = 'https://places.googleapis.com/v1/%s/media';

function opt( string $name ): string {
	return function_exists( 'get_field' ) ? (string) ( get_field( $name, 'option' ) ?: '' ) : '';
}

/**
 * The browser (markup-visible) key. A wp-config constant beats the admin
 * field, keeping the secret out of the database entirely:
 *
 *     define( 'ORIA_GOOGLE_BROWSER_KEY', '...' );
 */
function browser_key(): string {
	if ( defined( 'ORIA_GOOGLE_BROWSER_KEY' ) && is_string( ORIA_GOOGLE_BROWSER_KEY ) ) {
		return ORIA_GOOGLE_BROWSER_KEY;
	}
	return opt( 'google_maps_api_key' );
}

/**
 * The server key. Never rendered into markup anywhere.
 *
 *     define( 'ORIA_GOOGLE_SERVER_KEY', '...' );
 */
function server_key(): string {
	if ( defined( 'ORIA_GOOGLE_SERVER_KEY' ) && is_string( ORIA_GOOGLE_SERVER_KEY ) ) {
		return ORIA_GOOGLE_SERVER_KEY;
	}
	return opt( 'google_places_server_key' );
}

function enabled(): bool {
	return '' !== server_key()
		&& (bool) opt( 'places_photos_enable' );
}

/**
 * The cached Places record for a listing — fetching or refreshing it when
 * needed. Photos and rating share one record and one API call.
 *
 * @return array{names: string[], attributions: array, rating: float, count: int, maps_uri: string, ts: int}|null
 */
function data_for( int $post_id, bool $may_fetch = true ): ?array {
	if ( ! enabled() ) {
		return null;
	}

	$place_id = function_exists( 'get_field' ) ? trim( (string) get_field( 'google_place_id', $post_id ) ) : '';
	if ( 'off' === strtolower( $place_id ) ) {
		return null;
	}

	$cache = get_post_meta( $post_id, META_CACHE, true );
	if ( is_array( $cache )
		&& isset( $cache['ts'], $cache['names'] )
		&& ( time() - (int) $cache['ts'] ) < CACHE_DAYS * DAY_IN_SECONDS ) {
		return $cache;
	}

	if ( ! $may_fetch ) {
		return null;
	}

	// A failed fetch backs off for a day rather than retrying every pageview.
	if ( get_transient( 'oria_places_backoff_' . $post_id ) ) {
		return null;
	}

	$fresh = fetch( $post_id, $place_id );
	if ( null === $fresh ) {
		set_transient( 'oria_places_backoff_' . $post_id, 1, DAY_IN_SECONDS );
		return null;
	}

	update_post_meta( $post_id, META_CACHE, $fresh );
	return $fresh;
}

/**
 * The first photo for a listing CARD. Cached data is free; uncached listings
 * may trigger at most a couple of live fetches per pageview, so a directory
 * of 150 listings warms itself gradually instead of hanging one visitor on
 * hundreds of API calls. Profile views (photos_for) always fetch.
 */
function card_photo( int $post_id ): string {
	static $fetch_budget = 2;

	$cache = data_for( $post_id, false );
	if ( null === $cache && $fetch_budget > 0 ) {
		--$fetch_budget;
		$cache = data_for( $post_id, true );
	}

	return $cache ? (string) ( $cache['uris'][0] ?? '' ) : '';
}

/**
 * Photo media URLs plus attributions for a listing, or an empty set.
 *
 * @return array{urls: string[], attributions: array<int, array{name: string, uri: string}>}
 */
function photos_for( int $post_id, int $width = 1200 ): array {
	$cache = data_for( $post_id );
	return $cache
		? build( $cache, $width )
		: array( 'urls' => array(), 'attributions' => array() );
}

/**
 * The place's top Google reviews (up to five, as the API supplies them).
 *
 * @return array<int, array{author: string, author_uri: string, avatar: string, rating: float, when: string, text: string}>
 */
function reviews_for( int $post_id ): array {
	$cache = data_for( $post_id );
	return $cache ? (array) ( $cache['reviews'] ?? array() ) : array();
}

/**
 * The place's opening hours, one line per weekday exactly as Google words
 * them ("Monday: 9:00 am \u{2013} 5:00 pm"). Empty when the place lists none.
 *
 * @return string[]
 */
function hours_for( int $post_id ): array {
	$cache = data_for( $post_id );
	return $cache ? array_values( array_map( 'strval', (array) ( $cache['hours'] ?? array() ) ) ) : array();
}

/**
 * The listing's Google rating, labelled data for the profile header.
 *
 * @return array{rating: float, count: int, uri: string}
 */
function rating_for( int $post_id, bool $may_fetch = true ): array {
	$cache = data_for( $post_id, $may_fetch );
	return array(
		'rating' => $cache ? (float) ( $cache['rating'] ?? 0 ) : 0.0,
		'count'  => $cache ? (int) ( $cache['count'] ?? 0 ) : 0,
		'uri'    => $cache ? (string) ( $cache['maps_uri'] ?? '' ) : '',
	);
}

/**
 * Photo URLs come straight from the cache: they were resolved server-side at
 * fetch time into googleusercontent URIs that carry NO API key, so nothing
 * secret ever reaches the page markup. $width is fixed at fetch time.
 *
 * @param array $cache The cached record.
 */
function build( array $cache, int $width ): array {
	return array(
		'urls'         => array_slice( (array) ( $cache['uris'] ?? array() ), 0, MAX_PHOTOS ),
		'attributions' => (array) ( $cache['attributions'] ?? array() ),
	);
}

/**
 * Exchange a photo resource name for its short-term public image URI.
 * skipHttpRedirect makes the endpoint answer with JSON instead of a 302,
 * and the URI it returns is key-less.
 */
function resolve_photo_uri( string $name, string $key ): string {
	$response = wp_remote_get(
		add_query_arg(
			array(
				'maxWidthPx'       => 1600,
				'skipHttpRedirect' => 'true',
			),
			sprintf( MEDIA_URL, $name )
		),
		array(
			'timeout' => 8,
			'headers' => array( 'X-Goog-Api-Key' => $key ),
		)
	);
	$body = decode( $response );
	return is_array( $body ) ? (string) ( $body['photoUri'] ?? '' ) : '';
}

/**
 * Resolve the place (if needed) and pull its photo references.
 *
 * @return array{names: string[], attributions: array<int, array{name: string, uri: string}>, ts: int}|null
 */
function fetch( int $post_id, string $place_id ): ?array {
	$key = server_key();

	if ( '' === $place_id ) {
		$query = (string) get_post_field( 'post_title', $post_id, 'raw' );
		$addr  = function_exists( 'get_field' ) ? (string) get_field( 'address', $post_id ) : '';
		if ( $addr ) {
			$query .= ', ' . $addr;
		}

		$response = wp_remote_post(
			SEARCH_URL,
			array(
				'timeout' => 8,
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => $key,
					'X-Goog-FieldMask' => 'places.id,places.photos,places.rating,places.userRatingCount,places.googleMapsUri,places.reviews,places.regularOpeningHours',
				),
				'body'    => (string) wp_json_encode(
					array(
						'textQuery'  => $query,
						'regionCode' => 'AU',
						'pageSize'   => 1,
					)
				),
			)
		);

		$body = decode( $response );
		if ( null === $body || empty( $body['places'][0]['id'] ) ) {
			return null;
		}

		$place_id = (string) $body['places'][0]['id'];
		if ( function_exists( 'update_field' ) ) {
			update_field( 'google_place_id', $place_id, $post_id );
		}

		return with_uris( pack( (array) $body['places'][0] ), $key );
	}

	// Known place ID: details call.
	$response = wp_remote_get(
		sprintf( DETAILS_URL, rawurlencode( $place_id ) ),
		array(
			'timeout' => 8,
			'headers' => array(
				'X-Goog-Api-Key'   => $key,
				'X-Goog-FieldMask' => 'photos,rating,userRatingCount,googleMapsUri,reviews,regularOpeningHours',
			),
		)
	);

	$body = decode( $response );
	if ( null === $body ) {
		return null;
	}

	return with_uris( pack( $body ), $key );
}

/** Resolve every photo name in a packed record to its key-less URI. */
function with_uris( array $record, string $key ): array {
	$record['uris'] = array();
	foreach ( (array) $record['names'] as $name ) {
		$uri = resolve_photo_uri( (string) $name, $key );
		if ( '' !== $uri ) {
			$record['uris'][] = $uri;
		}
	}
	return $record;
}

/** @param array|\WP_Error $response */
function decode( $response ): ?array {
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	return is_array( $body ) ? $body : null;
}

/**
 * Reduce the API's place object to what we store: photo resource names, a
 * de-duplicated attribution list, and the rating trio.
 *
 * @return array{names: string[], attributions: array<int, array{name: string, uri: string}>, rating: float, count: int, maps_uri: string, ts: int}
 */
function pack( array $place ): array {
	$names = array();
	$attr  = array();
	foreach ( array_slice( (array) ( $place['photos'] ?? array() ), 0, MAX_PHOTOS ) as $photo ) {
		if ( empty( $photo['name'] ) ) {
			continue;
		}
		$names[] = (string) $photo['name'];
		foreach ( (array) ( $photo['authorAttributions'] ?? array() ) as $author ) {
			$display = (string) ( $author['displayName'] ?? '' );
			if ( '' !== $display && ! isset( $attr[ $display ] ) ) {
				$attr[ $display ] = array(
					'name' => $display,
					'uri'  => (string) ( $author['uri'] ?? '' ),
				);
			}
		}
	}
	// The API supplies at most five reviews per place; keep author details so
	// each one can be attributed and linked as Google's terms require.
	$reviews = array();
	foreach ( (array) ( $place['reviews'] ?? array() ) as $review ) {
		$text = (string) ( $review['text']['text'] ?? '' );
		if ( '' === trim( $text ) ) {
			continue; // Star-only reviews add nothing worth rendering.
		}
		$reviews[] = array(
			'author'     => (string) ( $review['authorAttribution']['displayName'] ?? __( 'A Google user', 'oria' ) ),
			'author_uri' => (string) ( $review['authorAttribution']['uri'] ?? '' ),
			'avatar'     => (string) ( $review['authorAttribution']['photoUri'] ?? '' ),
			'rating'     => (float) ( $review['rating'] ?? 0 ),
			'when'       => (string) ( $review['relativePublishTimeDescription'] ?? '' ),
			'text'       => $text,
		);
	}

	return array(
		'names'        => $names,
		'attributions' => array_values( $attr ),
		'hours'        => array_values( array_map( 'strval', (array) ( $place['regularOpeningHours']['weekdayDescriptions'] ?? array() ) ) ),
		'rating'       => (float) ( $place['rating'] ?? 0 ),
		'count'        => (int) ( $place['userRatingCount'] ?? 0 ),
		'maps_uri'     => (string) ( $place['googleMapsUri'] ?? '' ),
		'reviews'      => array_slice( $reviews, 0, 5 ),
		'ts'           => time(),
	);
}

/* ------------------------------------------------------------------ warm */

/**
 * A daily walk that refreshes the stalest Places records, forty at a time,
 * so hours (and everything else in the record) exist for every listing
 * without waiting for someone to visit each profile. Forty a day covers
 * the whole directory inside ten days and then keeps every record inside
 * the 29-day cache window forever after.
 */
function bootstrap(): void {
	add_action( 'oria_places_warm', __NAMESPACE__ . '\\warm' );
	add_action( 'init', static function (): void {
		if ( enabled() && ! wp_next_scheduled( 'oria_places_warm' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'oria_places_warm' );
		}
	} );
}

function warm( int $budget = 40 ): array {
	$fresh = 0;
	$had   = 0;
	if ( ! enabled() ) {
		return array( 'fetched' => 0, 'fresh' => 0 );
	}
	$ids = get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	foreach ( $ids as $id ) {
		if ( ! $id ) {
			continue;
		}
		$cache = get_post_meta( (int) $id, META_CACHE, true );
		if ( is_array( $cache ) && isset( $cache['ts'] )
			&& ( time() - (int) $cache['ts'] ) < ( CACHE_DAYS - 2 ) * DAY_IN_SECONDS ) {
			++$had;
			continue; // still comfortably fresh
		}
		if ( $budget <= 0 ) {
			break;
		}
		--$budget;
		if ( null !== data_for( (int) $id, true ) ) {
			++$fresh;
		}
	}
	return array( 'fetched' => $fresh, 'fresh' => $had + $fresh );
}

