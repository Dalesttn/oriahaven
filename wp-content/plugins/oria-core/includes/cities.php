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
	add_rewrite_rule(
		'^(' . slug_pattern() . ')/([^/]+)/?$',
		'index.php?' . Taxonomies\SPECIALTY . '=$matches[2]&' . QUERY_VAR . '=$matches[1]',
		'top'
	);
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

/* -------------------------------------------------------------- shortcuts */

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
