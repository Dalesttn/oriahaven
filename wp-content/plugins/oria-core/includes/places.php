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
/* Reviews live in their own row with their own timestamp: they are fetched
   on a profile view rather than on every refresh, so they expire on their
   own clock. Same 29-day ceiling -- Google's caching rule applies to them
   exactly as it does to the rest of the record. */
const META_REVIEWS = '_oria_places_reviews_v1';
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

	if ( hidden( $post_id ) ) {
		return null;
	}

	$place_id = function_exists( 'get_field' ) ? trim( (string) get_field( 'google_place_id', $post_id ) ) : '';


	$cache = get_post_meta( $post_id, META_CACHE, true );
	if ( is_array( $cache )
		&& isset( $cache['ts'], $cache['names'] )
		&& ( time() - (int) $cache['ts'] ) < CACHE_DAYS * DAY_IN_SECONDS ) {

		/*
		 * A record written before the match check existed can still be the
		 * wrong business. Once it carries a place_name -- which every record
		 * does after one refresh -- it can be judged, and a mismatch is
		 * withheld rather than shown.
		 *
		 * Records with no place_name pass through: that is absence of
		 * evidence, and suppressing 338 listings' photos on a field that was
		 * never populated would be the larger fault.
		 */
		$claimed = (string) ( $cache['place_name'] ?? '' );
		if ( '' !== $claimed
			&& ! name_matches( (string) get_post_field( 'post_title', $post_id, 'raw' ), $claimed ) ) {
			return null;
		}

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
	if ( ! enabled() || hidden( $post_id ) ) {
		return array();
	}

	$cache = get_post_meta( $post_id, META_REVIEWS, true );
	if ( is_array( $cache ) && isset( $cache['ts'] )
		&& ( time() - (int) $cache['ts'] ) < CACHE_DAYS * DAY_IN_SECONDS ) {
		return (array) ( $cache['items'] ?? array() );
	}

	/*
	 * The main record is asked for first because it carries the place-name
	 * check: reviews belonging to a business we matched wrongly must never
	 * reach the page, and resolving it also fills in a missing place ID.
	 */
	$record = data_for( $post_id );
	if ( null === $record ) {
		return array();
	}

	$place_id = function_exists( 'get_field' ) ? trim( (string) get_field( 'google_place_id', $post_id ) ) : '';
	if ( '' === $place_id || get_transient( 'oria_places_rev_backoff_' . $post_id ) ) {
		return array();
	}

	$items = fetch_reviews( $place_id );
	if ( null === $items ) {
		set_transient( 'oria_places_rev_backoff_' . $post_id, 1, DAY_IN_SECONDS );
		return array();
	}

	update_post_meta( $post_id, META_REVIEWS, array( 'items' => $items, 'ts' => time() ) );
	return $items;
}

/**
 * One Details call for review text alone.
 *
 * Kept apart from fetch() on purpose. Reviews are the priciest field Google
 * sells, and the daily warm walks every listing whether or not anyone reads
 * it -- so the warm gets everything else, and this runs only when somebody
 * actually opens a profile. Null means the call failed, which is not the
 * same as a place with no reviews (an empty array).
 *
 * @return array<int, array<string, mixed>>|null
 */
function fetch_reviews( string $place_id ): ?array {
	$response = wp_remote_get(
		sprintf( DETAILS_URL, rawurlencode( $place_id ) ),
		array(
			'timeout' => 8,
			'headers' => array(
				'X-Goog-Api-Key'   => server_key(),
				'X-Goog-FieldMask' => 'reviews',
			),
		)
	);

	$body = decode( $response );
	if ( null === $body ) {
		return null;
	}

	$packed = pack( $body );
	return (array) ( $packed['reviews'] ?? array() );
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
 * Whether the place is open right now, from the cached structured periods.
 * Cache-only on purpose: this is called per map pin, and a map of 350 pins
 * must never turn into 350 API calls. null means "we don't know" -- no
 * cached record, or the place lists no hours -- and the popup then simply
 * says nothing, which is honest.
 *
 * Google's periods: day 0 = Sunday; a 24/7 place is one open point with no
 * close. Overnight spans close on a later day, so everything is minutes on
 * a week-long clock, wrapped once.
 */
function open_now( int $post_id ): ?bool {
	$cache = data_for( $post_id, false );
	if ( ! $cache || empty( $cache['periods'] ) || ! is_array( $cache['periods'] ) ) {
		return null;
	}
	$now  = (int) current_time( 'w' ) * 1440 + (int) current_time( 'G' ) * 60 + (int) current_time( 'i' );
	$week = 7 * 1440;
	foreach ( $cache['periods'] as $period ) {
		$open = $period['open'] ?? null;
		if ( ! is_array( $open ) ) {
			continue;
		}
		if ( empty( $period['close'] ) ) {
			return true; // open around the clock
		}
		$close = $period['close'];
		$a     = (int) ( $open['day'] ?? 0 ) * 1440 + (int) ( $open['hour'] ?? 0 ) * 60 + (int) ( $open['minute'] ?? 0 );
		$b     = (int) ( $close['day'] ?? 0 ) * 1440 + (int) ( $close['hour'] ?? 0 ) * 60 + (int) ( $close['minute'] ?? 0 );
		if ( $b <= $a ) {
			$b += $week; // spans the week boundary
		}
		if ( ( $now >= $a && $now < $b ) || ( $now + $week >= $a && $now + $week < $b ) ) {
			return true;
		}
	}
	return false;
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
					// displayName is the point of this change: without it the
					// response never says which business was matched.
					// No places.reviews: review text is an Atmosphere field and
					// prices the whole call a tier higher. reviews_for() asks
					// for it separately, and only when a profile is rendered.
					'X-Goog-FieldMask' => 'places.id,places.displayName,places.photos,places.rating,places.userRatingCount,places.googleMapsUri,places.regularOpeningHours',
				),
				'body'    => (string) wp_json_encode(
					array(
						'textQuery'  => $query,
						'regionCode' => 'AU',
						// Five, not one. The right business is often behind a
						// larger neighbour at the same address rather than
						// absent, and one candidate leaves nothing to choose.
						'pageSize'   => 5,
					)
				),
			)
		);

		$body = decode( $response );
		if ( null === $body || empty( $body['places'] ) ) {
			return null;
		}

		/*
		 * The first candidate whose name plausibly matches, rather than simply
		 * the first. No match means no record: an empty profile is a smaller
		 * fault than a confident one describing somebody else.
		 */
		$title = (string) get_post_field( 'post_title', $post_id, 'raw' );
		$match = null;
		foreach ( (array) $body['places'] as $candidate ) {
			if ( empty( $candidate['id'] ) ) {
				continue;
			}
			if ( name_matches( $title, (string) ( $candidate['displayName']['text'] ?? '' ) ) ) {
				$match = (array) $candidate;
				break;
			}
		}

		if ( null === $match ) {
			/*
			 * Back off rather than retry on every view, and leave the field
			 * empty so a human can set the right ID. The names that were
			 * rejected are logged, because "why has this listing no photos"
			 * is otherwise unanswerable.
			 */
			set_transient( 'oria_places_backoff_' . $post_id, 1, DAY_IN_SECONDS );
			$seen = array();
			foreach ( (array) $body['places'] as $candidate ) {
				$seen[] = (string) ( $candidate['displayName']['text'] ?? '?' );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					sprintf(
						'[oria places] no name match for "%s" (#%d); Google offered: %s',
						$title,
						$post_id,
						implode( ' | ', $seen )
					)
				);
			}
			return null;
		}

		$place_id = (string) $match['id'];
		if ( function_exists( 'update_field' ) ) {
			update_field( 'google_place_id', $place_id, $post_id );
		}

		return with_uris( pack( $match ), $key );
	}

	// Known place ID: details call.
	$response = wp_remote_get(
		sprintf( DETAILS_URL, rawurlencode( $place_id ) ),
		array(
			'timeout' => 8,
			'headers' => array(
				'X-Goog-Api-Key'   => $key,
				// displayName here too: the warm() job refreshes forty records a
				// day through this path, so every existing record picks up a
				// name within ten days and becomes checkable.
				'X-Goog-FieldMask' => 'displayName,photos,rating,userRatingCount,googleMapsUri,regularOpeningHours',
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
/**
 * Does this Google place plausibly name the business we asked about?
 *
 * The text search is given "{title}, {address}" and, until now, was handed
 * pageSize 1 and a field mask with no displayName in it -- so the code took
 * result one and never learned what it had matched. Where a business shares
 * a street address with a larger neighbour, Google returns the neighbour.
 * Hyper O2, a hyperbaric oxygen studio, carried Kelso Medical Group's three
 * stars and seventy-two reviews about doctors running late, until the owner
 * wrote in.
 *
 * Matching on words rather than the whole string, because the two forms are
 * rarely identical: "Yoga Lab" against "YOGALAB Fremantle" is the same place,
 * "Hyper O2" against "Kelso Medical Group" is not. One shared distinctive
 * word is enough; the words that would make everything match are dropped.
 *
 * Deliberately permissive. A false reject costs a listing its photos until
 * somebody sets the ID by hand, which is visible and recoverable. A false
 * accept publishes another business's reputation, which is neither.
 */
function name_words( string $s ): array {
	static $stop = array(
		'the','and','for','with','wa','au','perth','australia','pty','ltd','inc',
		'group','centre','center','clinic','studio','wellness','health','therapy',
		'therapies','co','company','services','service','australia','of','at','in',
	);
	$s = strtolower( (string) preg_replace( '/[^a-z0-9 ]/i', ' ', $s ) );
	$w = preg_split( '/\s+/', $s, -1, PREG_SPLIT_NO_EMPTY );

	return array_values(
		array_filter(
			(array) $w,
			static fn( string $x ): bool => strlen( $x ) > 2 && ! in_array( $x, $stop, true )
		)
	);
}

function name_matches( string $listing, string $place ): bool {
	if ( '' === trim( $place ) ) {
		return true; // Nothing to judge on; the old behaviour, not a new reject.
	}

	$a = name_words( $listing );
	$b = name_words( $place );
	if ( ! $a || ! $b ) {
		return true;
	}
	if ( array_intersect( $a, $b ) ) {
		return true;
	}

	/*
	 * Spacing differs more often than spelling -- "Rec Lab" and "Reclab" are
	 * one business -- so compare the letters with the gaps taken out before
	 * deciding two names have nothing in common.
	 */
	$ja = str_replace( ' ', '', implode( ' ', $a ) );
	$jb = str_replace( ' ', '', implode( ' ', $b ) );

	return '' !== $ja && '' !== $jb
		&& ( false !== strpos( $ja, $jb ) || false !== strpos( $jb, $ja ) );
}

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
		// What Google thinks this place is called. Stored so a record can be
		// audited later without another API call, and so data_for() can
		// refuse a mismatch it inherited from before this check existed.
		'place_name'   => (string) ( $place['displayName']['text'] ?? '' ),
		'attributions' => array_values( $attr ),
		'hours'        => array_values( array_map( 'strval', (array) ( $place['regularOpeningHours']['weekdayDescriptions'] ?? array() ) ) ),
		'periods'      => array_values( (array) ( $place['regularOpeningHours']['periods'] ?? array() ) ),
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
 * so hours (and everything else in the record) exist without waiting for
 * someone to visit each profile.
 *
 * It no longer walks the whole directory. Refreshing all 441 listings every
 * 29 days bought Google data for hundreds of pages nobody opened that month,
 * and every refresh also re-resolves up to three photo URLs. The warm now
 * covers what the money is actually for: listings somebody looked at in the
 * last 30 days, and listings a practitioner pays for -- where stale hours
 * are our problem rather than a hypothetical visitor's.
 *
 * Nothing is lost on the rest. A cold listing's record simply expires, and
 * the next real visit fetches it through card_photo() or photos_for() as it
 * always did.
 */
function bootstrap(): void {
	add_action( 'oria_places_warm', __NAMESPACE__ . '\\warm' );
	add_filter( 'post_row_actions', __NAMESPACE__ . '\row_actions', 10, 2 );
	add_action( 'admin_post_oria_places_toggle', __NAMESPACE__ . '\toggle' );
	add_action( 'admin_notices', __NAMESPACE__ . '\toggle_notice' );
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
	$skipped = 0;
	foreach ( $ids as $id ) {
		if ( ! $id ) {
			continue;
		}
		if ( ! worth_warming( (int) $id ) ) {
			++$skipped;
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
	return array( 'fetched' => $fresh, 'fresh' => $had + $fresh, 'skipped' => $skipped );
}

/**
 * Whether the daily warm should spend an API call on this listing.
 *
 * Read in last month, or paid for. The window is deliberately generous --
 * one view keeps a listing warm for the next 30 days, so a page that gets
 * occasional traffic never falls out -- and the filter lets a site decide
 * otherwise without touching this file.
 */
function worth_warming( int $post_id ): bool {
	$warm = false;

	if ( function_exists( '\Oria\Core\Ownership\is_paid' ) && \Oria\Core\Ownership\is_paid( $post_id ) ) {
		$warm = true;
	} elseif ( function_exists( '\Oria\Core\Analytics\total' ) ) {
		$warm = \Oria\Core\Analytics\total( $post_id, 'view', 30 ) > 0;
	} else {
		$warm = true; // No analytics to judge by: behave as before.
	}

	return (bool) apply_filters( 'oria_places_worth_warming', $warm, $post_id );
}


/* ------------------------------------------------------- turning it off */

/**
 * Has somebody said this listing should show no Google data?
 *
 * Two ways of saying it, because the first one was a secret. The only switch
 * used to be the word "off" typed into the place ID box -- undiscoverable,
 * and worse, the obvious alternative of CLEARING that box does nothing: an
 * empty value falls through to whatever was cached last. Both of us got that
 * wrong on the same listing on the same afternoon.
 *
 * The checkbox is the switch now. The string still answers, so nothing set by
 * hand before today quietly turns itself back on.
 */
function hidden( int $post_id ): bool {
	if ( ! function_exists( 'get_field' ) ) {
		return false;
	}
	if ( (bool) get_field( 'places_hide', $post_id ) ) {
		return true;
	}
	// Other modules may hold a listing back (Sources: unconfirmed research drafts).
	if ( (bool) apply_filters( 'oria_places_hidden', false, $post_id ) ) {
		return true;
	}

	return 'off' === strtolower( trim( (string) get_field( 'google_place_id', $post_id ) ) );
}

/**
 * The same switch from the listings list, because discovering that a rating
 * belongs to somebody else happens in batches: an owner writes in, and then
 * you want to check the eight others that look like it. Opening each listing,
 * finding the Google tab and saving is four steps too many once the answer is
 * already known.
 *
 * @param array<string, string> $actions
 * @param mixed                 $post
 * @return array<string, string>
 */
function row_actions( $actions, $post ) {
	if ( ! $post instanceof \WP_Post || \Oria\Core\PostTypes\LISTING !== $post->post_type ) {
		return $actions;
	}
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=oria_places_toggle&post=' . (int) $post->ID ),
		'oria_places_toggle_' . (int) $post->ID
	);

	$actions['oria_places'] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( $url ),
		hidden( (int) $post->ID )
			? esc_html__( 'Show Google data', 'oria' )
			: esc_html__( 'Hide Google data', 'oria' )
	);

	return $actions;
}

function toggle(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

	if ( ! $post_id
		|| ! current_user_can( 'edit_post', $post_id )
		|| ! isset( $_GET['_wpnonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'oria_places_toggle_' . $post_id ) ) {
		wp_die(
			esc_html__( 'That link has expired. Go back to the listings and try again.', 'oria' ),
			'',
			array( 'response' => 403 )
		);
	}

	$now = ! hidden( $post_id );
	if ( function_exists( 'update_field' ) ) {
		update_field( 'places_hide', $now, $post_id );
		/*
		 * Switching it back on has to clear the legacy string too, or the
		 * checkbox reads "showing" while hidden() still answers true and the
		 * page stays blank with nothing on screen to explain why.
		 */
		if ( ! $now && 'off' === strtolower( trim( (string) get_field( 'google_place_id', $post_id ) ) ) ) {
			update_field( 'google_place_id', '', $post_id );
		}
	}

	wp_safe_redirect(
		add_query_arg(
			'oria_places',
			$now ? 'hidden' : 'shown',
			wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . \Oria\Core\PostTypes\LISTING )
		)
	);
	exit;
}

function toggle_notice(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$state = isset( $_GET['oria_places'] ) ? sanitize_key( wp_unslash( (string) $_GET['oria_places'] ) ) : '';
	if ( 'hidden' !== $state && 'shown' !== $state ) {
		return;
	}
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html(
			'hidden' === $state
				? __( 'Google reviews and photos removed from that listing. Everything else on it is untouched.', 'oria' )
				: __( 'Google reviews and photos switched back on for that listing.', 'oria' )
		)
	);
}
