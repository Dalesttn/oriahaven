<?php
/**
 * Which neighbourhood is this page about?
 *
 * A visitor can land on a Fremantle studio or a Fremantle event and never
 * learn that a Fremantle guide exists -- 24 checked places, local day plans,
 * transport notes, what is on this week. This resolves any listing or event
 * to its area, so the components that offer that guide can be written once
 * and dropped anywhere.
 *
 * Deliberately thin. The counting already exists in \Oria\Core\AreaDepth,
 * cached across the whole taxonomy in one query, and the editorial copy and
 * photographs live in the theme's area-guides.json. This joins them up; it
 * does not keep its own list of suburbs, URLs or statistics.
 *
 * Two rules keep it honest:
 *  - An area with too few places to deserve a page of its own is already
 *    noindexed by AreaDepth. It is not offered here either -- promoting a
 *    page we tell Google to ignore is an argument with ourselves.
 *  - When nothing resolves, callers get null and render nothing. There is
 *    no guessed URL and no "explore the area" pointing at a 404.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\AreaContext;

use Oria\Core\AreaDepth;
use Oria\Core\Events;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CACHE_PREFIX = 'oria_area_ctx_';

/**
 * The area a post belongs to, or null.
 *
 * Listings and events both carry `area` terms, so both resolve the same
 * way: the most specific term wins, because "Fremantle" is a more useful
 * offer than "Fremantle & South". An event's venue string is never parsed
 * -- if the structured term is missing, the answer is no.
 */
function for_post( int $post_id ): ?array {
	$terms = wp_get_post_terms( $post_id, Taxonomies\AREA );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return null;
	}

	$pick = null;
	foreach ( $terms as $term ) {
		// A child term is a suburb; a parent is the region it sits in.
		if ( $term->parent ) {
			$pick = $term;
			break;
		}
		if ( null === $pick ) {
			$pick = $term;
		}
	}

	return $pick ? for_term( $pick ) : null;
}

/**
 * The context for one area term, or null when it is not worth offering.
 *
 * @return array{term:\WP_Term, name:string, region:string, url:string, places:int, practices:int, events:int, next_event:int}|null
 */
function for_term( \WP_Term $term ): ?array {
	if ( AreaDepth\is_thin( $term->term_id ) ) {
		return null;
	}

	$key    = CACHE_PREFIX . $term->term_id;
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		$cached['term'] = $term;
		return $cached;
	}

	$url = get_term_link( $term );
	if ( is_wp_error( $url ) ) {
		return null;
	}

	$region = '';
	if ( $term->parent ) {
		$parent = get_term( $term->parent, Taxonomies\AREA );
		$region = $parent instanceof \WP_Term ? $parent->name : '';
	}

	$events = function_exists( '\Oria\Core\Events\for_area' ) ? Events\for_area( $term->slug, 12 ) : array();

	$out = array(
		'name'       => $term->name,
		'slug'       => $term->slug,
		'region'     => $region,
		'url'        => (string) $url,
		'places'     => AreaDepth\depth( $term->term_id ),
		'practices'  => practice_count( $term ),
		'events'     => count( $events ),
		'next_event' => $events ? (int) $events[0] : 0,
	);

	// Twelve hours, and dropped whenever AreaDepth drops its own counts --
	// the same edits change both.
	set_transient( $key, $out, 12 * HOUR_IN_SECONDS );

	$out['term'] = $term;
	return $out;
}

/* ------------------------------------------------------- sets of areas */

/**
 * Every suburb worth offering, richest first.
 *
 * AreaDepth\popular() already applies the rules -- suburbs only, never a
 * region, and nothing under the indexing floor -- so this adds the
 * context each one needs and nothing else. Cached whole: resolving forty
 * suburbs one at a time is nine queries each, which no page should pay.
 *
 * @return array<int, array> Rows as for_term() returns them.
 */
function catalogue( ?array $city = null ): array {
	$slug   = (string) ( $city['slug'] ?? '' );
	$key    = CACHE_PREFIX . 'cat_' . ( '' !== $slug ? $slug : 'all' );
	$cached = get_transient( $key );

	if ( ! is_array( $cached ) ) {
		/*
		 * Counted in bulk, not one suburb at a time. Resolving forty
		 * areas through for_term() is around five hundred queries and
		 * two and a half seconds, which somebody would eventually pay
		 * for on a cold cache. Two passes over the relationships give
		 * the same numbers.
		 */
		$events    = event_counts();
		$practices = practice_counts();
		$cached    = array();

		foreach ( AreaDepth\popular( 200 ) as $term ) {
			if ( '' !== $slug && function_exists( '\Oria\Core\Cities\for_area' ) ) {
				$in = \Oria\Core\Cities\for_area( $term );
				if ( (string) ( $in['slug'] ?? '' ) !== $slug ) {
					continue;
				}
			}
			$url = get_term_link( $term );
			if ( is_wp_error( $url ) ) {
				continue;
			}
			$region = '';
			if ( $term->parent ) {
				$parent = get_term( $term->parent, Taxonomies\AREA );
				$region = $parent instanceof \WP_Term ? $parent->name : '';
			}
			$cached[] = array(
				'name'       => $term->name,
				'slug'       => $term->slug,
				'region'     => $region,
				'url'        => (string) $url,
				'places'     => AreaDepth\depth( (int) $term->term_id ),
				'practices'  => (int) ( $practices[ $term->term_id ] ?? 0 ),
				'events'     => (int) ( $events[ $term->term_id ] ?? 0 ),
				'next_event' => 0,
				'term_id'    => (int) $term->term_id,
			);
		}
		set_transient( $key, $cached, 12 * HOUR_IN_SECONDS );
	}

	// The term objects come back from WP's own cache, so they are cheap
	// and never stale the way a serialised copy would be.
	$out = array();
	foreach ( $cached as $row ) {
		$term = get_term( (int) $row['term_id'], Taxonomies\AREA );
		if ( $term instanceof \WP_Term ) {
			$row['term'] = $term;
			$out[]       = $row;
		}
	}
	return $out;
}

/**
 * Upcoming events per area term, in two queries rather than one per area.
 *
 * @return array<int, int> term_id => count
 */
function event_counts(): array {
	$ids = function_exists( '\Oria\Core\Events\upcoming' ) ? Events\upcoming( array( 'limit' => 300 ) ) : array();
	if ( ! $ids ) {
		return array();
	}
	$terms = wp_get_object_terms( $ids, Taxonomies\AREA, array( 'fields' => 'all_with_object_id' ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$n = array();
	foreach ( $terms as $term ) {
		$n[ (int) $term->term_id ] = ( $n[ (int) $term->term_id ] ?? 0 ) + 1;
	}
	return $n;
}

/**
 * Kinds of practice per area term, from one pass over every listing.
 *
 * @return array<int, int> term_id => count of distinct practices
 */
function practice_counts(): array {
	$ids = get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	if ( ! $ids ) {
		return array();
	}

	$terms = wp_get_object_terms( $ids, array( Taxonomies\AREA, Taxonomies\PRACTICE ), array( 'fields' => 'all_with_object_id' ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$areas_of     = array();
	$practices_of = array();
	foreach ( $terms as $term ) {
		$post = (int) $term->object_id;
		if ( Taxonomies\AREA === $term->taxonomy ) {
			$areas_of[ $post ][] = (int) $term->term_id;
		} else {
			$practices_of[ $post ][] = (int) $term->term_id;
		}
	}

	$seen = array();
	foreach ( $areas_of as $post => $area_ids ) {
		foreach ( $area_ids as $area_id ) {
			foreach ( $practices_of[ $post ] ?? array() as $practice_id ) {
				$seen[ $area_id ][ $practice_id ] = true;
			}
		}
	}

	$n = array();
	foreach ( $seen as $area_id => $set ) {
		$n[ $area_id ] = count( $set );
	}
	return $n;
}

/**
 * The suburbs beside this one.
 *
 * Adjacency we can defend: the siblings under the same region, which is
 * how the taxonomy already groups places that are a short drive apart.
 * Nothing here measures distance -- we have no distance between two
 * suburbs, only between two addresses, and guessing would put Yanchep
 * next to Fremantle on a bad day.
 */
function nearby( \WP_Term $term, int $limit = 6 ): array {
	if ( ! function_exists( '\Oria\Core\AreaDepth\siblings_with_listings' ) ) {
		return array();
	}
	$out = array();
	foreach ( AreaDepth\siblings_with_listings( $term, $limit ) as $sibling ) {
		$row = for_term( $sibling );
		if ( $row ) {
			$out[] = $row;
		}
	}
	return $out;
}

/**
 * The one area a set of places actually clusters in, or null.
 *
 * For the pages that show a chosen set -- a Best Of guide, a comparison,
 * a finder result -- where noticing that most of them are in one suburb
 * is a genuinely useful thing to say, and saying it about a scattered set
 * is noise. Both bars have to clear: a share of the set, and a floor, so
 * "two of three" in a tiny list does not become a claim about a
 * neighbourhood.
 *
 * @param array<int, int> $post_ids Listings, events, anything with areas.
 */
function dominant( array $post_ids, float $share = 0.4, int $min = 3 ): ?array {
	$post_ids = array_values( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) );
	if ( count( $post_ids ) < $min ) {
		return null;
	}

	$tally = array();
	foreach ( $post_ids as $id ) {
		$area = for_post( $id );
		if ( $area ) {
			$tally[ $area['slug'] ] = ( $tally[ $area['slug'] ] ?? 0 ) + 1;
		}
	}
	if ( ! $tally ) {
		return null;
	}

	arsort( $tally );
	$slug = (string) array_key_first( $tally );
	$n    = (int) $tally[ $slug ];
	if ( $n < $min || $n / count( $post_ids ) < $share ) {
		return null;
	}

	$term = get_term_by( 'slug', $slug, Taxonomies\AREA );
	if ( ! $term instanceof \WP_Term ) {
		return null;
	}
	$out = for_term( $term );
	if ( $out ) {
		$out['here'] = $n;
	}
	return $out;
}

/**
 * A handful worth putting in front of somebody who has not chosen yet.
 *
 * Explicitly not the longest lists. A neighbourhood earns a card by being
 * somewhere to spend an afternoon: a photograph of its own, something on
 * this week, a line somebody wrote about it. Size only breaks ties, and
 * no region takes more than two places, or the homepage becomes a list of
 * one corner of the city.
 */
function featured( int $limit = 6, ?array $city = null ): array {
	$rows = catalogue( $city );
	if ( ! $rows ) {
		return array();
	}

	foreach ( $rows as $i => $row ) {
		$term  = $row['term'];
		$score = 0;
		if ( '' !== image( $term )['src'] && has_own_image( $term ) ) {
			$score += 2;
		}
		if ( $row['events'] > 0 ) {
			$score += 2;
		}
		if ( '' !== tagline( $term ) ) {
			$score += 2;
		}
		$score           += min( 3, (int) floor( $row['places'] / 5 ) );
		$rows[ $i ]['_s'] = $score;
	}

	usort(
		$rows,
		static fn( array $a, array $b ): int => $b['_s'] <=> $a['_s'] ?: $b['places'] <=> $a['places']
	);

	$out = array();
	$per = array();
	foreach ( $rows as $row ) {
		$region = (string) $row['region'];
		if ( ( $per[ $region ] ?? 0 ) >= 2 ) {
			continue;
		}
		$per[ $region ] = ( $per[ $region ] ?? 0 ) + 1;
		unset( $row['_s'] );
		$out[] = $row;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/** Whether the photograph on an area is the area's own, not its region's. */
function has_own_image( \WP_Term $term ): bool {
	if ( ! function_exists( '\Oria\V4\Area\hero' ) ) {
		return false;
	}
	$hero = \Oria\V4\Area\hero( $term, null, null );
	return ! empty( $hero['own'] );
}

/**
 * How many kinds of practice an area holds.
 *
 * One query for the term's listings, then their practice terms in one more
 * -- not one query per listing, which is what a loop over a suburb page
 * would otherwise become.
 */
function practice_count( \WP_Term $term ): int {
	$ids = get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array(
				array(
					'taxonomy'         => Taxonomies\AREA,
					'field'            => 'term_id',
					'terms'            => $term->term_id,
					'include_children' => true,
				),
			),
		)
	);
	if ( ! $ids ) {
		return 0;
	}

	$terms = wp_get_object_terms( $ids, Taxonomies\PRACTICE, array( 'fields' => 'ids' ) );
	return is_wp_error( $terms ) ? 0 : count( array_unique( $terms ) );
}

/** Drop one area's cached context; the counts behind it live in AreaDepth. */
function flush( $thing = 0 ): void {
	$terms = get_terms( array( 'taxonomy' => Taxonomies\AREA, 'hide_empty' => false, 'fields' => 'ids' ) );
	if ( is_wp_error( $terms ) ) {
		return;
	}
	foreach ( $terms as $id ) {
		delete_transient( CACHE_PREFIX . (int) $id );
	}

	delete_transient( CACHE_PREFIX . 'cat_all' );
	if ( function_exists( '\Oria\Core\Cities\slugs' ) ) {
		foreach ( \Oria\Core\Cities\slugs() as $slug ) {
			delete_transient( CACHE_PREFIX . 'cat_' . $slug );
		}
	}
}

function bootstrap(): void {
	// The same edits that change AreaDepth's counts change these.
	foreach ( array( 'save_post_listing', 'save_post_event', 'deleted_post', 'set_object_terms', 'edited_term', 'delete_term' ) as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\flush' );
	}
}

/* ------------------------------------------------------------------ copy */

/**
 * A line about the area, from the theme's own guide entry.
 *
 * Editorial copy lives with the theme that publishes it, so this reads the
 * guide when the active theme has one and says nothing when it does not.
 * Nothing here is generated: a suburb without a written tagline gets the
 * plain count sentence instead.
 */
function tagline( \WP_Term $term ): string {
	if ( ! function_exists( '\Oria\V4\Area\guide' ) ) {
		return '';
	}
	$guide = \Oria\V4\Area\guide( $term );
	return trim( (string) ( $guide['tagline'] ?? '' ) );
}

/**
 * The area photograph, at the size a card needs rather than a hero's.
 *
 * @return array{src:string, alt:string}
 */
function image( \WP_Term $term ): array {
	if ( ! function_exists( '\Oria\V4\Area\hero' ) ) {
		return array( 'src' => '', 'alt' => '' );
	}
	$region = null;
	if ( $term->parent ) {
		$parent = get_term( $term->parent, Taxonomies\AREA );
		$region = $parent instanceof \WP_Term ? $parent : null;
	}
	$hero = \Oria\V4\Area\hero( $term, $region, null );

	// The narrow rendition where there is one: a sidebar card has no use
	// for a 1920px hero, and the brief is explicit about not shipping one.
	return array(
		'src' => (string) ( $hero['phone'] ?: $hero['mid'] ?: $hero['wide'] ?? '' ),
		'alt' => (string) ( $hero['alt'] ?? '' ),
	);
}
