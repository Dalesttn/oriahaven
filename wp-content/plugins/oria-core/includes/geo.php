<?php
/**
 * Coordinates for listings, and distance from them.
 *
 * Geocoded from OpenStreetMap via Nominatim, NOT from Google Places. The Maps
 * Platform terms let you keep a place_id indefinitely and almost nothing else,
 * so caching Google's coordinates in post meta would breach them — see the
 * note at the top of places.php, which already draws that line for photos.
 * Nominatim's data is ODbL: storing it is allowed, provided OpenStreetMap is
 * credited wherever the derived figures appear.
 *
 * Precision is recorded alongside every coordinate, because half these
 * listings have no street address and land on a suburb centroid instead. A
 * distance derived from a centroid is a real answer to "roughly how far out
 * is this", and a lie if presented as the distance to the door.
 */

declare(strict_types=1);

namespace Oria\Core\Geo;

use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_LAT       = 'geo_lat';
const META_LNG       = 'geo_lng';
const META_PRECISION = 'geo_precision';   // address | suburb
const META_STAMP     = 'geo_at';

/** Perth GPO, Forrest Place — the point "from the CBD" is measured to. */
const CBD = array( -31.9535, 115.8570 );

/** Nominatim asks for a real User-Agent and no more than one call a second. */
const AGENT    = 'OriaHavenDirectory/1.0 (https://oriahaven.com.au; geocoding own listings)';
const ENDPOINT = 'https://nominatim.openstreetmap.org/search';
const PAUSE    = 1.1;

/** Credit required by ODbL wherever a derived distance is shown. */
function attribution(): string {
	return __( 'Distances calculated from OpenStreetMap data.', 'oria' );
}

/* --------------------------------------------------------------- reading */

/**
 * One listing's stored position.
 *
 * @return array{lat: float, lng: float, precision: string}|null
 */
function position( int $post_id ): ?array {
	$lat = get_post_meta( $post_id, META_LAT, true );
	$lng = get_post_meta( $post_id, META_LNG, true );
	if ( '' === $lat || '' === $lng || null === $lat || null === $lng ) {
		return null;
	}
	return array(
		'lat'       => (float) $lat,
		'lng'       => (float) $lng,
		'precision' => (string) ( get_post_meta( $post_id, META_PRECISION, true ) ?: 'suburb' ),
	);
}

/** Great-circle kilometres between two [lat, lng] pairs. */
function distance_km( array $a, array $b ): float {
	$r  = 6371.0;
	$p1 = deg2rad( (float) $a[0] );
	$p2 = deg2rad( (float) $b[0] );
	$dp = deg2rad( (float) $b[0] - (float) $a[0] );
	$dl = deg2rad( (float) $b[1] - (float) $a[1] );
	$h  = sin( $dp / 2 ) ** 2 + cos( $p1 ) * cos( $p2 ) * sin( $dl / 2 ) ** 2;
	return 2 * $r * asin( min( 1.0, sqrt( $h ) ) );
}

/** Kilometres from the CBD, or null when the listing has no position. */
/**
 * The centre a listing's distance should be measured from.
 *
 * Its own city's, from cities.json. CBD stays the fallback for a listing
 * with no city -- and for the whole site before cities existed.
 *
 * @return array{0: float, 1: float}
 */
function centre_for( int $post_id ): array {
	if ( ! function_exists( '\Oria\Core\Cities\for_area' ) ) {
		return CBD;
	}

	$terms = get_the_terms( $post_id, Taxonomies\AREA );
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$city = \Oria\Core\Cities\for_area( $term );
		if ( is_array( $city ) && isset( $city['centre']['lat'], $city['centre']['lng'] ) ) {
			return array( (float) $city['centre']['lat'], (float) $city['centre']['lng'] );
		}
	}

	return CBD;
}

/** The name of the place that distance is measured from. */
function centre_name( int $post_id ): string {
	if ( function_exists( '\Oria\Core\Cities\for_area' ) ) {
		$terms = get_the_terms( $post_id, Taxonomies\AREA );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$city = \Oria\Core\Cities\for_area( $term );
			if ( is_array( $city ) && ! empty( $city['slug'] ) && 'perth' !== $city['slug'] ) {
				return \Oria\Core\Cities\name( $city );
			}
		}
	}

	return __( 'the CBD', 'oria' );
}

function km_from_cbd( int $post_id ): ?float {
	$p = position( $post_id );
	return $p ? distance_km( array( $p['lat'], $p['lng'] ), centre_for( $post_id ) ) : null;
}

/**
 * The distance as a reader should see it.
 *
 * Rounded to the honest resolution rather than the available one: a suburb
 * centroid does not know it is 6.13km from anywhere, so it says 6km. Under
 * two kilometres the number stops being the interesting part.
 */
function label( int $post_id ): string {
	$km = km_from_cbd( $post_id );
	if ( null === $km ) {
		return '';
	}
	$where = centre_name( $post_id );
	if ( $km < 1.5 ) {
		/* translators: %s: the town or city centre. */
		return __( 'the CBD', 'oria' ) === $where
			? __( 'In the CBD', 'oria' )
			: sprintf( __( 'In %s', 'oria' ), $where );
	}
	$p = position( $post_id );
	$n = ( 'address' === ( $p['precision'] ?? '' ) && $km < 10 )
		? number_format_i18n( round( $km, 1 ), 1 )
		: number_format_i18n( round( $km ) );

	/* translators: 1: distance in kilometres, 2: the town or city centre. */
	return sprintf( __( '%1$s km from %2$s', 'oria' ), $n, $where );
}

/* ------------------------------------------------------------- geocoding */

/**
 * Strip what confuses a geocoder: a unit, suite or shop prefix, and the
 * "3/53" form. Nominatim matches the street number; the unit is noise that
 * makes the whole query fail rather than degrade.
 */
function clean_address( string $address ): string {
	$a = trim( $address );

	/*
	 * Anything in brackets is a note written for a human — "(also Inglewood)",
	 * "(studio address shared on booking)" — and a geocoder cannot place it.
	 * Fifteen production listings failed on this alone. A semicolon introduces
	 * the same kind of aside: "…, Trigg; also Fremantle and Victoria Park".
	 */
	$a = (string) preg_replace( '/\([^)]*\)/', ' ', $a );
	$a = (string) preg_replace( '/;.*$/', '', $a );

	/*
	 * Prefixes that name a room or a host venue rather than a place. The "u"
	 * alternative must be followed by a digit — "U2, 45 Central Walk" is a
	 * unit, "Upper Swan Road" is not, and a bare /^u/ eats the second one.
	 */
	$a = (string) preg_replace( '/^(?:unit|suite|shop|level|ste|room|rm)\s*[\w-]+[,\/ ]+/i', '', $a );
	$a = (string) preg_replace( '/^u\s*\d+[\w-]*[,\/ ]+/i', '', $a );
	$a = (string) preg_replace( '/^(?:sessions?|consults?|classes|clinic)\s+at\s+/i', '', $a );
	$a = (string) preg_replace( '/^\d+\s*\/\s*/', '', $a );

	$a = (string) preg_replace( '/\s*,\s*/', ', ', $a );
	$a = (string) preg_replace( '/\s{2,}/', ' ', $a );
	return trim( $a, " ,\t\n\r\0\x0B" );
}

/** Does this query still name a street number, or only a suburb? */
function has_street_number( string $query ): bool {
	$body = (string) preg_replace( '/\b(?:WA|Western Australia)\b\s*\d{4}\s*$/i', '', $query );
	$body = (string) preg_replace( '/\b\d{4}\b\s*$/', '', $body );
	return (bool) preg_match( '/(?:^|,\s*)\d+[A-Za-z]?\s+\S/', trim( $body ) );
}

/**
 * Progressively simpler forms of one address, most specific first.
 *
 * A geocoder answers "33 Moore Street, East Perth" and gives up on
 * "Claisebrook Lotteries House, 33 Moore Street, East Perth" — the building
 * name is the part it cannot place, and it is always at the front. Dropping
 * leading comma-segments one at a time turns a miss into a hit without
 * inventing anything: every attempt is a suffix of the address as written.
 *
 * @return list<string>
 */
function query_variants( string $address ): array {
	$parts = array_values( array_filter( array_map( 'trim', explode( ',', $address ) ) ) );
	$out   = array();
	/*
	 * All the way down to the final segment, which is almost always
	 * "Suburb WA 6000" and is the fallback that was missing. Stopping two
	 * segments short meant a two-part address like "Applecross Community
	 * Village, Applecross WA" produced exactly one attempt — the one that
	 * fails — and never tried the suburb on its own.
	 */
	for ( $i = 0; $i < count( $parts ); $i++ ) {
		$candidate = implode( ', ', array_slice( $parts, $i ) );
		if ( '' !== $candidate ) {
			$out[] = $candidate;
		}
	}
	return $out ? $out : array( $address );
}

/**
 * What to ask about a listing, and how precise the answer will be.
 *
 * Falls back to the suburb term, which every listing has even when the
 * address field is empty — 53 of them are, and a suburb centroid still
 * answers "how far out is this".
 *
 * @return array{query: string, precision: string}|null
 */
function query_for( int $post_id ): ?array {
	$address = clean_address( (string) get_field( 'address', $post_id ) );

	/*
	 * A street number means the geocoder can find a building rather than a
	 * boundary. Testing for any digit was not enough: every address ends in
	 * a postcode, so "Wanneroo WA 6065" claimed address precision on the
	 * strength of its own postcode. Strip the state and postcode first, then
	 * look for a number that begins a line or precedes a word.
	 */
	if ( '' !== $address ) {
		$body = (string) preg_replace( '/\b(?:WA|Western Australia)\b\s*\d{4}\s*$/i', '', $address );
		$body = (string) preg_replace( '/\b\d{4}\b\s*$/', '', $body );
		if ( preg_match( '/(?:^|,\s*)\d+[A-Za-z]?\s+\S/', trim( $body ) ) ) {
			return array( 'query' => $address, 'precision' => 'address' );
		}
		// Text but no street number — still worth asking, still only as
		// precise as the suburb it names.
		return array( 'query' => $address, 'precision' => 'suburb' );
	}

	$terms  = wp_get_post_terms( $post_id, Taxonomies\AREA );
	$suburb = null;
	foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
		if ( $t->parent ) {
			$suburb = $t;
			break;
		}
		$suburb = $suburb ?: $t;
	}
	if ( ! $suburb ) {
		return null;
	}

	$name = wp_specialchars_decode( $suburb->name, ENT_QUOTES );
	return array( 'query' => $name . ', Western Australia', 'precision' => 'suburb' );
}

/**
 * Ask Nominatim. Returns [lat, lng] or null.
 *
 * Bounded to Western Australia by viewbox as well as country code: "Cottesloe"
 * and "Fremantle" both exist elsewhere, and a listing silently placed in NSW
 * would produce a confident, wrong distance.
 */
function lookup( string $query ): ?array {
	$url = add_query_arg(
		array(
			'format'       => 'jsonv2',
			'limit'        => 1,
			'countrycodes' => 'au',
			'viewbox'      => '112.9,-35.2,129.0,-13.6',
			'bounded'      => 1,
			'q'            => $query,
		),
		ENDPOINT
	);

	$res = wp_remote_get( $url, array( 'timeout' => 20, 'headers' => array( 'User-Agent' => AGENT ) ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}

	$rows = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $rows ) || ! $rows ) {
		return null;
	}
	$hit = $rows[0];
	if ( ! isset( $hit['lat'], $hit['lon'] ) ) {
		return null;
	}
	return array( (float) $hit['lat'], (float) $hit['lon'] );
}

/** Geocode one listing and store the result. */
function geocode( int $post_id ): ?array {
	$q = query_for( $post_id );
	if ( null === $q ) {
		return null;
	}
	$hit  = null;
	$used = $q['query'];
	foreach ( query_variants( $q['query'] ) as $i => $variant ) {
		if ( $i > 0 ) {
			// Every retry is another call, so it waits like the first one did.
			usleep( (int) ( PAUSE * 1000000 ) );
		}
		$hit = lookup( $variant );
		if ( null !== $hit ) {
			$used = $variant;
			break;
		}
	}
	if ( null === $hit ) {
		return null;
	}

	/*
	 * Precision describes what was actually found, not what was asked for.
	 * "45 Central Walk, Joondalup WA 6027" falls back to "Joondalup WA 6027"
	 * — a suburb centroid — and calling that address-precision would put a
	 * to-the-door figure on a page that measured to the suburb.
	 */
	$precision = ( 'address' === $q['precision'] && ! has_street_number( $used ) )
		? 'suburb'
		: $q['precision'];

	update_post_meta( $post_id, META_LAT, (string) $hit[0] );
	update_post_meta( $post_id, META_LNG, (string) $hit[1] );
	update_post_meta( $post_id, META_PRECISION, $precision );
	update_post_meta( $post_id, META_STAMP, current_time( 'mysql' ) );

	return array( 'lat' => $hit[0], 'lng' => $hit[1], 'precision' => $precision, 'query' => $used );
}

/* ------------------------------------------------------------------- cli */

if ( defined( 'WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command(
		'oria geocode',
		/**
		 * Fill in coordinates for listings, from OpenStreetMap.
		 *
		 * Rate-limited to one call a second, which is Nominatim's published
		 * ceiling for automated use. Re-runs skip anything already placed, so
		 * the command is cheap to repeat after an import.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Report what would be looked up without calling anything or writing.
		 *
		 * [--force]
		 * : Re-geocode listings that already have coordinates.
		 *
		 * [--limit=<n>]
		 * : Stop after this many listings.
		 *
		 * ## EXAMPLES
		 *
		 *     wp oria geocode --dry-run
		 *     wp oria geocode
		 *     wp oria geocode --force --limit=20
		 */
		function ( array $args, array $assoc ): void {
			$dry   = isset( $assoc['dry-run'] );
			$force = isset( $assoc['force'] );
			$limit = isset( $assoc['limit'] ) ? max( 0, (int) $assoc['limit'] ) : 0;

			$ids = get_posts(
				array(
					'post_type'      => PostTypes\LISTING,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);

			$done = 0;
			$hit  = 0;
			$miss = 0;
			$skip = 0;
			$byp  = array( 'address' => 0, 'suburb' => 0 );

			foreach ( $ids as $id ) {
				$id = (int) $id;
				if ( ! $force && null !== position( $id ) ) {
					$skip++;
					continue;
				}
				if ( $limit && $done >= $limit ) {
					break;
				}

				$q = query_for( $id );
				if ( null === $q ) {
					\WP_CLI::warning( sprintf( '%s: no address and no suburb — skipped.', get_the_title( $id ) ) );
					$miss++;
					continue;
				}

				if ( $dry ) {
					\WP_CLI::log( sprintf( '  would look up (%s): %s  <- %s', $q['precision'], $q['query'], get_the_title( $id ) ) );
					$done++;
					$byp[ $q['precision'] ]++;
					continue;
				}

				$got = geocode( $id );
				$done++;
				if ( null === $got ) {
					\WP_CLI::warning( sprintf( 'no match: %s (%s)', get_the_title( $id ), $q['query'] ) );
					$miss++;
				} else {
					$hit++;
					$byp[ $got['precision'] ]++;
					\WP_CLI::log(
						sprintf(
							'  %-42s %8.4f %8.4f  %5.1f km  (%s)',
							substr( (string) get_the_title( $id ), 0, 42 ),
							$got['lat'],
							$got['lng'],
							distance_km( array( $got['lat'], $got['lng'] ), CBD ),
							$got['precision']
						)
					);
				}

				usleep( (int) ( PAUSE * 1000000 ) );
			}

			\WP_CLI::success(
				sprintf(
					'%s%d processed, %d placed, %d without a match, %d already had coordinates. By precision: %d address, %d suburb.',
					$dry ? '[dry-run] ' : '',
					$done,
					$hit,
					$miss,
					$skip,
					$byp['address'],
					$byp['suburb']
				)
			);
		}
	);
}
