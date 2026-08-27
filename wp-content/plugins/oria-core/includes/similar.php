<?php
/**
 * Practices worth offering next to the one being read.
 *
 * The sidebar has always carried a "Nearby in {region}" rail, and it picked
 * its three with orderby => rand inside the region. A region is most of a
 * third of the metro, so a yoga studio in East Fremantle could recommend an
 * acupuncture clinic in Bibra Lake, and did.
 *
 * What makes two practices alike here is knowable and already stored:
 *
 *   the same kind of thing   practice terms, on 100% of listings
 *   the same treatments      service and specialty terms, on 92%
 *   near enough to go to     coordinates, on 100% since the geocoding pass
 *
 * So they are scored on all three rather than shuffled. Distance is real
 * kilometres, not "same region" — Bicton and Balcatta share a region and are
 * an hour apart, which is the whole reason the geo pass happened.
 *
 * Deliberately ranked, never filtered. A hard requirement on shared services
 * would leave the rail empty for a practice that is the only one of its kind
 * in Perth, and an empty rail is worse than a loosely related one.
 */

declare(strict_types=1);

namespace Oria\Core\Similar;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How long a computed rail stays good for. Listings change slowly. */
const CACHE_TTL = 12 * HOUR_IN_SECONDS;

/**
 * Geo\position() returns [lat =>, lng =>, precision =>]; distance_km() wants
 * an indexed [lat, lng]. km_from_cbd() converts on the way in, and this file
 * did not — so every distance came back 0 and silently stopped contributing
 * to the ranking at all.
 *
 * @return array{0: float, 1: float}|null
 */
function pair( ?array $p ): ?array {
	return $p ? array( (float) $p['lat'], (float) $p['lng'] ) : null;
}

/**
 * Terms of one taxonomy on a post, as a slug lookup.
 *
 * @return array<string, true>
 */
function slugs( int $post_id, string $tax ): array {
	$t = wp_get_post_terms( $post_id, $tax, array( 'fields' => 'slugs' ) );
	return is_wp_error( $t ) ? array() : array_fill_keys( $t, true );
}

/**
 * The candidate pool: everything sharing a practice, plus everything in the
 * same region.
 *
 * Scoring all 356 on every page load would be honest and slow. These two
 * groups hold every listing that could plausibly score well — anything in
 * neither shares no category and is not nearby, which is precisely what the
 * score is looking for.
 *
 * @return list<int>
 */
function candidates( int $listing_id ): array {
	$practices = wp_get_post_terms( $listing_id, Taxonomies\PRACTICE, array( 'fields' => 'ids' ) );
	$areas     = wp_get_post_terms( $listing_id, Taxonomies\AREA, array( 'fields' => 'ids' ) );

	$ids = array();
	foreach ( array(
		array( Taxonomies\PRACTICE, is_wp_error( $practices ) ? array() : $practices ),
		array( Taxonomies\AREA, is_wp_error( $areas ) ? array() : $areas ),
	) as $pair ) {
		list( $tax, $terms ) = $pair;
		if ( ! $terms ) {
			continue;
		}
		$found = get_posts(
			array(
				'post_type'      => 'listing',
				'post_status'    => 'publish',
				'posts_per_page' => 60,
				'fields'         => 'ids',
				'post__not_in'   => array( $listing_id ),
				'orderby'        => 'modified',
				'tax_query'      => array(
					array(
						'taxonomy'         => $tax,
						'field'            => 'term_id',
						'terms'            => $terms,
						'include_children' => true,
					),
				),
			)
		);
		foreach ( $found as $f ) {
			$ids[ (int) $f ] = true;
		}
	}
	return array_keys( $ids );
}

/**
 * Ranked practices like this one.
 *
 * @return list<int> Post IDs, best first.
 */
function listings_for( int $listing_id, int $limit = 3 ): array {
	$key    = 'oria_similar_' . $listing_id . '_' . $limit;
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$mine = array(
		'practice'  => slugs( $listing_id, Taxonomies\PRACTICE ),
		'service'   => slugs( $listing_id, 'service' ),
		'specialty' => slugs( $listing_id, Taxonomies\SPECIALTY ),
	);
	$here = function_exists( '\Oria\Core\Geo\position' ) ? pair( \Oria\Core\Geo\position( $listing_id ) ) : null;

	$scored = array();
	foreach ( candidates( $listing_id ) as $id ) {
		$score = 0;

		// The same kind of thing, first and heaviest.
		$shared_practice = count( array_intersect_key( $mine['practice'], slugs( $id, Taxonomies\PRACTICE ) ) );
		$score          += min( $shared_practice, 2 ) * 5;

		// Then the same work: two remedial massage clinics are more alike
		// than two things that merely share "Massage & Bodywork".
		$score += min( count( array_intersect_key( $mine['service'], slugs( $id, 'service' ) ) ), 3 ) * 3;
		$score += min( count( array_intersect_key( $mine['specialty'], slugs( $id, Taxonomies\SPECIALTY ) ) ), 3 ) * 2;

		// Then whether somebody would actually go. Real kilometres: a shared
		// region says almost nothing across a metro this wide.
		$km = null;
		if ( $here && function_exists( '\Oria\Core\Geo\position' ) ) {
			$there = pair( \Oria\Core\Geo\position( (int) $id ) );
			if ( $there ) {
				$km = \Oria\Core\Geo\distance_km( $here, $there );
				if ( $km <= 3 )       { $score += 6; }
				elseif ( $km <= 7 )   { $score += 4; }
				elseif ( $km <= 15 )  { $score += 2; }
				elseif ( $km > 40 )   { $score -= 3; }
			}
		}

		// A rating breaks ties. It never creates a match — a well-reviewed
		// dentist is still not a yoga studio.
		$rated = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( (int) $id ) : array( 'rating' => 0 );
		if ( ( $rated['rating'] ?? 0 ) >= 4.5 ) {
			$score += 1;
		}

		if ( $score <= 0 ) {
			continue;
		}
		$scored[] = array( 'id' => (int) $id, 'score' => $score, 'km' => $km );
	}

	usort(
		$scored,
		static function ( array $a, array $b ): int {
			if ( $a['score'] !== $b['score'] ) {
				return $b['score'] <=> $a['score'];
			}
			// Same score: the closer one, and a known distance beats none.
			$ka = null === $a['km'] ? PHP_FLOAT_MAX : $a['km'];
			$kb = null === $b['km'] ? PHP_FLOAT_MAX : $b['km'];
			return $ka <=> $kb;
		}
	);

	$out = array_slice( wp_list_pluck( $scored, 'id' ), 0, max( 0, $limit ) );
	set_transient( $key, $out, CACHE_TTL );
	return $out;
}

/** Kilometres between two listings, or null when either lacks coordinates. */
function km_between( int $a, int $b ): ?float {
	if ( ! function_exists( '\Oria\Core\Geo\position' ) ) {
		return null;
	}
	$pa = \Oria\Core\Geo\position( $a );
	$pb = \Oria\Core\Geo\position( $b );
	$pa = pair( $pa );
	$pb = pair( $pb );
	return ( $pa && $pb ) ? \Oria\Core\Geo\distance_km( $pa, $pb ) : null;
}
