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
