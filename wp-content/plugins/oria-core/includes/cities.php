<?php
/**
 * The city, as a variable rather than a word.
 *
 * `/perth/acupuncture/` looked multi-city and was not. The taxonomy was
 * registered with rewrite slug 'perth' — a constant. Acupuncture is not a
 * Perth thing; the term is universal and only the listings behind it are
 * local. Under that scheme a second city meant either Sydney clinics
 * appearing beneath /perth/, or a duplicate taxonomy.
 *
 * This turns the constant into a variable whose first value is 'perth'.
 * Every one of the existing specialty URLs stays byte-identical, because
 * Perth is the default city and the default city has no distinguishing
 * behaviour — it is simply first. /sydney/acupuncture/ then costs nothing.
 *
 * Nothing here changes what any URL resolves to today. That is the point:
 * this is the change that has to happen before the corpus grows, and it is
 * the one that can happen without a single redirect.
 *
 * RESOLUTION ORDER for the current city:
 *   1. an explicit ?oria_city, set by the specialty route
 *   2. the city an area term belongs to, once areas carry one
 *   3. the default
 *
 * Step 2 is deliberately written now and inert until the area tree gains a
 * city level. A resolver that only learns about cities after the terms
 * exist is a resolver nobody remembers to update.
 */

declare(strict_types=1);

namespace Oria\Core\Cities;

use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_city';
const DATA_FILE = 'data/cities.json';

function bootstrap(): void {
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'init', __NAMESPACE__ . '\route', 8 );
	add_filter( 'term_link', __NAMESPACE__ . '\specialty_link', 10, 3 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 20 );
	add_action( 'pre_get_posts', __NAMESPACE__ . '\scope_archives' );
}

/**
 * Keep a category archive inside its city.
 *
 * The templates build their own sets and are filtered there, but the main
 * query feeds things no template touches -- item_list_schema() reads
 * $GLOBALS['wp_query']->posts, and on /practices/spa/ it was advertising
 * seven Margaret River businesses in the page's ItemList.
 *
 * The city is read off the query rather than through current(), which
 * leans on get_query_var() and the queried object; neither is settled
 * while pre_get_posts is running.
 *
 * Area archives are skipped: an area is already narrower than its city,
 * and a southern suburb's own page must keep showing its own listings.
 * The listing post type archive is skipped too -- /directory/ is the
 * whole-corpus view by design.
 */
function scope_archives( \WP_Query $q ): void {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	$slug = (string) $q->get( QUERY_VAR );

	/*
	 * Category and specialty archives are always one city's. The listing
	 * archive is only a city's when the URL says so: /explore/ is the whole
	 * corpus on purpose, /explore/perth/ is not.
	 */
	$scoped = $q->is_tax( array( Taxonomies\PRACTICE, Taxonomies\SPECIALTY ) )
		|| ( $q->is_post_type_archive( PostTypes\LISTING ) && '' !== $slug );
	if ( ! $scoped ) {
		return;
	}

	$city   = ( '' !== $slug && exists( $slug ) ) ? (array) get( $slug ) : default_city();
	$clause = tax_clause( $city );
	if ( ! $clause ) {
		return;
	}

	$tq   = (array) $q->get( 'tax_query' );
	$tq[] = $clause;
	$q->set( 'tax_query', $tq ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
}

/**
 * /{city}/{specialty}/
 *
 * Registered above the taxonomy's own rules and below the /{city}/ hub,
 * which is an exact match and cannot be shadowed by this.
 *
 * The alternation is built from the city list rather than being a catch-all
 * ([^/]+), so an unknown first segment falls through to a 404 instead of
 * being handed to the specialty query and silently rendering the wrong
 * thing. It also leaves the pattern space free for the area tree, which
 * takes /{city}/{region}/{suburb}/ next.
 */
function route(): void {
	/*
	 * PHASE 6: the old addresses are retired. Nothing is registered here
	 * any more, so these paths 404 -- and the 301 map, which runs on
	 * template_redirect before the 404 is rendered, carries the ones
	 * worth carrying. Restoring them is re-adding the rules below.
	 */
	// add_rewrite_rule( '^(' . slug_pattern() . ')/([^/]+)/?$', ... );
}

/**
 * Rebuild rewrite rules when the city list changes.
 *
 * Keyed on the city slugs themselves. Adding a city to the JSON changes the
 * rule's alternation, and a rule that is not flushed is a 404 on every URL
 * for the new city — the sort of thing that looks like a data problem for a
 * day before anyone checks the rewrites.
 */
function maybe_flush(): void {
	$fingerprint = implode( ',', slugs() );

	if ( get_option( 'oria_cities_v' ) !== $fingerprint ) {
		flush_rewrite_rules();
		update_option( 'oria_cities_v', $fingerprint );
	}
}

/**
 * Rebuild specialty permalinks as /{city}/{slug}/.
 *
 * The taxonomy is registered with rewrite => false, so WordPress produces
 * the ugly ?specialty= form and this replaces it. Terms are city-agnostic —
 * acupuncture is acupuncture — so the link uses whichever city the request
 * is about, falling back to the default. That is what keeps every existing
 * /perth/ link identical while the code stops assuming Perth.
 *
 * @param string $link
 * @param \WP_Term $term
 * @param string $taxonomy
 */
function specialty_link( $link, $term, $taxonomy ): string {
	if ( Taxonomies\SPECIALTY !== $taxonomy || ! $term instanceof \WP_Term ) {
		return (string) $link;
	}

	/*
	 * /explore/{city}/{category}/{specialty}/ — the category being the one
	 * declared in data/specialty-homes.json, not whichever page you happen
	 * to be on. That is what keeps one specialty to one address: 79 of 90
	 * appear under several categories in the listing data.
	 *
	 * No home means no page, and the old flat address is the honest answer
	 * until one is declared.
	 */
	if ( function_exists( '\Oria\Core\PracticesIndex\specialty_home' )
		&& function_exists( '\Oria\Core\Explore\base_url' ) ) {
		$home = \Oria\Core\PracticesIndex\specialty_home( $term->slug );
		if ( '' !== $home ) {
			/*
			 * A specialty that shares its category's slug is that category,
			 * and three of them answer on a shorter segment the intent
			 * registry claims. Both come from the map: asking the resolver
			 * here cost 227 queries per link and a category page renders
			 * dozens of them.
			 */
			$tail = $home === $term->slug ? '' : \Oria\Core\PracticesIndex\specialty_slug( $term->slug ) . '/';

			return \Oria\Core\Explore\base_url() . $home . '/' . $tail;
		}
	}

	return user_trailingslashit( home_url( '/' . path() . '/' . $term->slug ) );
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/**
 * Every city, keyed by slug.
 *
 * @return array<string, array<string, mixed>>
 */
function all(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$cache = array();
	$path  = ORIA_CORE_DIR . DATA_FILE;

	if ( ! is_readable( $path ) ) {
		return $cache;
	}

	$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	foreach ( (array) ( $json['cities'] ?? array() ) as $row ) {
		$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		if ( '' !== $slug ) {
			$row['slug']    = $slug;
			$cache[ $slug ] = $row;
		}
	}

	return $cache;
}

/** @return list<string> */
function slugs(): array {
	return array_keys( all() );
}

function exists( string $slug ): bool {
	return isset( all()[ sanitize_title( $slug ) ] );
}

/** @return array<string, mixed>|null */
function get( string $slug ): ?array {
	return all()[ sanitize_title( $slug ) ] ?? null;
}

/**
 * The city assumed when nothing identifies one.
 *
 * Falls back to the first city rather than to a literal 'perth', so a file
 * that forgets the default flag degrades to something sensible instead of
 * to a city that might not be listed any more.
 *
 * @return array<string, mixed>
 */
function default_city(): array {
	foreach ( all() as $city ) {
		if ( ! empty( $city['default'] ) ) {
			return $city;
		}
	}

	$first = all();
	return (array) ( reset( $first ) ?: array( 'slug' => 'perth', 'name' => 'Perth' ) );
}

/**
 * The city this request is about.
 *
 * @return array<string, mixed>
 */
function current(): array {
	$var = (string) get_query_var( QUERY_VAR );
	if ( '' !== $var && exists( $var ) ) {
		return (array) get( $var );
	}

	$obj = get_queried_object();
	if ( $obj instanceof \WP_Term && Taxonomies\AREA === $obj->taxonomy ) {
		$city = for_area( $obj );
		if ( null !== $city ) {
			return $city;
		}
	}

	/*
	 * A listing belongs to wherever it is. Without this every category and
	 * specialty link on a Margaret River profile pointed back into Perth,
	 * and the page said "everywhere else in Perth that offers it" under a
	 * sauna parked at the Prevelly rivermouth.
	 *
	 * Memoised: current() is asked repeatedly per request, and this is the
	 * one branch that costs a term read.
	 */
	if ( $obj instanceof \WP_Post && PostTypes\LISTING === $obj->post_type ) {
		static $for_post = array();
		if ( ! isset( $for_post[ $obj->ID ] ) ) {
			$found = null;
			$terms = get_the_terms( $obj->ID, Taxonomies\AREA );
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$found = for_area( $term );
				if ( null !== $found ) {
					break;
				}
			}
			$for_post[ $obj->ID ] = $found;
		}
		if ( null !== $for_post[ $obj->ID ] ) {
			return $for_post[ $obj->ID ];
		}
	}

	return default_city();
}

/**
 * The city an area term sits under.
 *
 * Returns null while the area tree is still two levels deep — regions at
 * the root, suburbs beneath. Once a city level is inserted, the root
 * ancestor is the city and this starts answering without further changes
 * here.
 *
 * @return array<string, mixed>|null
 */
function for_area( \WP_Term $term ): ?array {
	if ( exists( $term->slug ) ) {
		return get( $term->slug );
	}

	foreach ( (array) get_ancestors( $term->term_id, Taxonomies\AREA, 'taxonomy' ) as $id ) {
		$anc = get_term( (int) $id, Taxonomies\AREA );
		if ( $anc instanceof \WP_Term && exists( $anc->slug ) ) {
			return get( $anc->slug );
		}
	}

	return null;
}

/**
 * Every area term id belonging to a city: the city term and its descendants.
 *
 * The piece the original city work left out. Naming a page after a city is
 * cosmetic on its own -- /margaret-river/infrared-sauna/ still listed thirty
 * Perth saunas -- so the query needs the same answer the title does.
 *
 * Returns an empty array when the city has no area term, and callers treat
 * that as "do not filter": a city halfway through being set up should show
 * everything rather than nothing.
 *
 * @return list<int>
 */
function area_ids( ?array $city = null ): array {
	static $cache = array();

	$city = $city ?: current();
	$slug = (string) ( $city['slug'] ?? '' );
	if ( '' === $slug ) {
		return array();
	}
	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}

	$term = get_term_by( 'slug', $slug, Taxonomies\AREA );
	if ( ! $term instanceof \WP_Term ) {
		return $cache[ $slug ] = array();
	}

	$ids = array( (int) $term->term_id );
	foreach ( (array) get_term_children( (int) $term->term_id, Taxonomies\AREA ) as $child ) {
		$ids[] = (int) $child;
	}

	return $cache[ $slug ] = $ids;
}

/**
 * A tax_query clause restricting results to one city, or nothing at all.
 *
 * Written to be dropped straight into an existing tax_query. Returns an
 * empty array when the city cannot be resolved, so `array_filter` or a
 * simple merge leaves the query untouched rather than impossible.
 *
 * @return array<string, mixed>
 */
function tax_clause( ?array $city = null ): array {
	$ids = area_ids( $city );
	if ( ! $ids ) {
		return array();
	}
	return array(
		'taxonomy'         => Taxonomies\AREA,
		'field'            => 'term_id',
		'terms'            => $ids,
		'include_children' => false,
	);
}

/**
 * Narrow a list of listing ids to one city, keeping the order it arrived in.
 *
 * The practices pages build their set through Intents and facet resolvers
 * rather than a WP_Query we can add a clause to, so they need the filter as
 * a second pass. Order is the whole value of what they hand over -- it is a
 * ranking, not a bag -- so this intersects rather than re-queries.
 *
 * An unresolvable city filters nothing, matching tax_clause().
 *
 * @param list<int> $ids
 * @return list<int>
 */
function filter_ids( array $ids, ?array $city = null ): array {
	$clause = tax_clause( $city );
	if ( ! $clause || ! $ids ) {
		return array_values( $ids );
	}

	$keep = get_posts(
		array(
			'post_type'              => PostTypes\LISTING,
			'post_status'            => 'publish',
			'post__in'               => array_map( 'intval', $ids ),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'tax_query'              => array( $clause ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$keep = array_flip( array_map( 'intval', (array) $keep ) );

	return array_values(
		array_filter( $ids, static fn( $id ): bool => isset( $keep[ (int) $id ] ) )
	);
}

/* -------------------------------------------------------------- shortcuts */

/**
 * The prose for this city's directory page, paragraph by paragraph.
 *
 * Lives in cities.json with the rest of what a city is, so writing one for
 * a new region is the same act as adding the region. A city without any is
 * not a broken city -- the page simply carries no read-up.
 *
 * @return list<string>
 */
function read_up( ?array $city = null ): array {
	$city = $city ?? current();
	$rows = $city['read_up'] ?? array();

	if ( ! is_array( $rows ) ) {
		return array();
	}

	return array_values( array_filter( array_map( 'strval', $rows ) ) );
}

/** The display name — "Perth". */
function name( ?array $city = null ): string {
	$city = $city ?? current();
	return (string) ( $city['name'] ?? 'Perth' );
}

/** "the Perth metro", for sentences that need the wider area. */
function metro( ?array $city = null ): string {
	$city = $city ?? current();
	return (string) ( $city['metro'] ?? 'the ' . name( $city ) . ' metro' );
}

/** "Perth, Western Australia" — the schema.org AdministrativeArea. */
function region( ?array $city = null ): string {
	$city = $city ?? current();
	return (string) ( $city['region'] ?? name( $city ) );
}

/**
 * The path prefix for a city's own pages: "perth".
 *
 * Not "/perth/" and not trailing-slashed, because every caller wants to
 * build something different on the end of it.
 */
function path( ?array $city = null ): string {
	$city = $city ?? current();
	return (string) ( $city['slug'] ?? 'perth' );
}

/** A regex alternation of every city slug, for rewrite rules. */
function slug_pattern(): string {
	$slugs = array_map( 'preg_quote', slugs() );
	return $slugs ? implode( '|', $slugs ) : 'perth';
}
