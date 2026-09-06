<?php
/**
 * /explore/{city}/{category}/{specialty}/
 *
 * The addressing the directory is moving to, added alongside the old routes
 * rather than replacing them. Both answer for now: nothing 404s, nothing is
 * canonical yet, and the new tree can be walked and checked before it
 * carries anything. Phase 5 flips the URL builders; phase 6 retires the old
 * addresses to 301s.
 *
 * Every rule reuses the query vars the existing routes already set —
 * oria_hub, oria_practice_v2, oria_facet — so the templates, the facet
 * resolver and the city scoping all work here without knowing this file
 * exists. The one thing these rules add is `oria_city`, which is what makes
 * /explore/margaret-river/spa/ a Margaret River page rather than a Perth
 * one: Cities\current() reads that var, and the filtering built on top of
 * it follows automatically.
 *
 * WHY THE PATTERNS CANNOT COLLIDE. Each rule is anchored and pinned to an
 * exact segment count, and the city alternation comes from cities.json
 * rather than being a catch-all, so an unknown first segment falls through
 * to a 404 instead of being handed to the practice query and quietly
 * rendering the wrong thing. That is the same reasoning cities.php uses for
 * /{city}/{specialty}/.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Explore;

use Oria\Core\Cities;
use Oria\Core\PostTypes;
use Oria\Core\PracticesIndex;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The root segment. */
const PATH = 'explore';
const SITEMAP = 'explore'; // /explore-sitemap.xml -- the city hubs

/** Bumped when the rules change, so they re-flush without a manual step. */
const VERSION_OPTION = 'oria_explore_rules_v';
const VERSION        = '2';

function bootstrap(): void {
	// The listing archive is the directory, and it now lives at /explore/.
	add_filter( 'post_type_archive_link', __NAMESPACE__ . '\archive_link', 10, 2 );
	// A city directory is its own page: its own canonical, its own title.
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical' );
	/*
	 * og:url answers from the same source as the canonical.
	 *
	 * Seven modules here override wpseo_canonical to point a custom route
	 * at its real address. None of them overrode og:url, so Open Graph
	 * kept answering from the main query -- on a facet page that meant
	 * advertising the old /practice/{category}/ URL, which is now a 301
	 * and was never that page. Same question, same answer.
	 */
	add_filter( 'wpseo_opengraph_url', __NAMESPACE__ . '\canonical' );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );
	// Priority 7: ahead of cities.php (8) and the hub (10), so the longer
	// /explore/ forms are matched before the shorter city forms are tried.
	add_action( 'init', __NAMESPACE__ . '\route', 7 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 20 );
	/*
	 * The city hubs had no sitemap of their own and belonged to nobody
	 * else's: Yoast builds one per taxonomy and per post type, and
	 * /explore/perth/ is neither. Twelve sitemaps, 912 URLs, and the top of
	 * the whole new address structure was in none of them -- Search Console
	 * reported /explore/perth/ as "URL is unknown to Google" five days after
	 * the migration.
	 */
	add_action( 'init', __NAMESPACE__ . '\register_sitemap', 20 );
	add_filter( 'wpseo_sitemap_index', __NAMESPACE__ . '\sitemap_index' );
}

function route(): void {
	$cities = Cities\slug_pattern();

	// The directory itself.
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?post_type=' . PostTypes\LISTING, 'top' );
	add_rewrite_rule(
		'^' . PATH . '/page/([0-9]{1,})/?$',
		'index.php?post_type=' . PostTypes\LISTING . '&paged=$matches[1]',
		'top'
	);

	/*
	 * A city's own directory -- the same page /explore/ renders, holding
	 * only that city's listings. It was the category index; the directory
	 * is what belongs at an address a person picks a region to reach.
	 */
	add_rewrite_rule(
		'^' . PATH . '/(' . $cities . ')/?$',
		'index.php?post_type=' . PostTypes\LISTING . '&' . Cities\QUERY_VAR . '=$matches[1]',
		'top'
	);
	add_rewrite_rule(
		'^' . PATH . '/(' . $cities . ')/page/([0-9]{1,})/?$',
		'index.php?post_type=' . PostTypes\LISTING . '&' . Cities\QUERY_VAR . '=$matches[1]&paged=$matches[2]',
		'top'
	);

	// A category within a city.
	add_rewrite_rule(
		'^' . PATH . '/(' . $cities . ')/([^/]+)/?$',
		'index.php?practice=$matches[2]&' . PracticesIndex\V2_VAR . '=1&' . Cities\QUERY_VAR . '=$matches[1]',
		'top'
	);
	add_rewrite_rule(
		'^' . PATH . '/(' . $cities . ')/([^/]+)/page/([0-9]{1,})/?$',
		'index.php?practice=$matches[2]&' . PracticesIndex\V2_VAR . '=1&' . Cities\QUERY_VAR . '=$matches[1]&paged=$matches[3]',
		'top'
	);

	/*
	 * A specialty, or any other facet, within that category. The fourth
	 * segment stays polymorphic — resolve_facet() already sniffs service,
	 * specialty, audience, format, price and area in that order — and the
	 * specialty simply becomes the canonical case among them.
	 */
	add_rewrite_rule(
		'^' . PATH . '/(' . $cities . ')/([^/]+)/([^/]+)/?$',
		'index.php?practice=$matches[2]&' . PracticesIndex\V2_VAR . '=1&' . PracticesIndex\FACET_VAR . '=$matches[3]&' . Cities\QUERY_VAR . '=$matches[1]',
		'top'
	);
}

/**
 * Flush when the rules or the city list change.
 *
 * The fingerprint carries the city slugs as well as the version, because
 * adding a city changes every pattern above and the rules would otherwise
 * keep the old alternation until someone remembered to flush by hand.
 */
function maybe_flush(): void {
	$fingerprint = VERSION . ':' . implode( ',', Cities\slugs() );

	if ( get_option( VERSION_OPTION ) !== $fingerprint ) {
		flush_rewrite_rules();
		update_option( VERSION_OPTION, $fingerprint );
	}
}

/**
 * The city whose directory is being viewed, or null on /explore/.
 *
 * @return array<string, mixed>|null
 */
function current_city_archive(): ?array {
	if ( ! is_post_type_archive( PostTypes\LISTING ) ) {
		return null;
	}
	$slug = (string) get_query_var( Cities\QUERY_VAR );

	return ( '' !== $slug && Cities\exists( $slug ) ) ? (array) Cities\get( $slug ) : null;
}

/**
 * Self-canonical, not the generic directory.
 *
 * get_post_type_archive_link() answers /explore/ for every listing archive,
 * so both city pages were telling Google they were copies of it.
 */
function canonical( $url ) {
	$city = current_city_archive();

	return $city ? base_url( $city ) : $url;
}

/** "Wellness in Margaret River", not "Perth wellness directory". */
function title( $title ) {
	$city = current_city_archive();
	if ( ! $city ) {
		return $title;
	}

	return sprintf(
		/* translators: 1: city name, 2: site name. */
		__( 'Wellness in %1$s — every practice, checked by hand | %2$s', 'oria' ),
		Cities\name( $city ),
		get_bloginfo( 'name' )
	);
}

/** The same, for core's title when Yoast is not answering. */
function core_title( array $parts ): array {
	$city = current_city_archive();
	if ( $city ) {
		/* translators: %s: city name. */
		$parts['title'] = sprintf( __( 'Wellness in %s — every practice, checked by hand', 'oria' ), Cities\name( $city ) );
	}

	return $parts;
}

/** @param string $desc */
function description( $desc ) {
	$city = current_city_archive();
	if ( ! $city ) {
		return $desc;
	}

	return sprintf(
		/* translators: %s: the region, e.g. "the Margaret River region". */
		__( 'Every wellness practice we list across %s — massage, saunas, yoga, breathwork and day spas. Real prices and timetables, each one checked by a person.', 'oria' ),
		Cities\metro( $city )
	);
}

/** The root every new address is built from: /explore/{city}/.
 *
 * One function, so the category builder, the specialty filter and anything
 * added later cannot disagree about the shape.
 */
function base_url( ?array $city = null ): string {
	$city = $city ?: Cities\current();
	$slug = (string) ( $city['slug'] ?? '' );

	return home_url( '/' . PATH . '/' . ( '' !== $slug ? $slug . '/' : '' ) );
}

/** /directory/ becomes /explore/. */
function archive_link( $link, $post_type ) {
	return PostTypes\LISTING === $post_type ? home_url( '/' . PATH . '/' ) : $link;
}

/**
 * The same page, in another city.
 *
 * Keeps as much of where you are as that city can honour, and gives up one
 * segment at a time rather than all of them: a facet that city has no
 * listings for falls back to the category, a category it has nothing in
 * falls back to its overview. A switcher that lands on a 404 is worse than
 * one that lands you a level up.
 *
 * @param array<string, mixed> $city
 */
function switch_url( array $city ): string {
	$base = base_url( $city );
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$segs = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );

	// Not inside /explore/ at all: the city's overview is the honest answer.
	if ( ! $segs || PATH !== $segs[0] || ! isset( $segs[1] ) ) {
		return $base;
	}

	$cat   = $segs[2] ?? '';
	$facet = $segs[3] ?? '';
	if ( '' === $cat ) {
		return $base;
	}

	$term = get_term_by( 'slug', $cat, Taxonomies\PRACTICE );
	if ( ! $term instanceof \WP_Term ) {
		return $base;
	}

	/*
	 * Does that category hold anything in the target city? listings_in() is
	 * the whole corpus, so it has to be narrowed the way the page would be.
	 */
	$ids = function_exists( '\Oria\Core\Intents\listings_in' )
		? \Oria\Core\Intents\listings_in( $term )
		: array();
	if ( function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
		$ids = \Oria\Core\Cities\filter_ids( $ids, $city );
	}
	if ( ! $ids ) {
		return $base;
	}

	if ( '' === $facet ) {
		return $base . $cat . '/';
	}

	// The facet has to resolve AND hold something once the city is applied.
	$f = PracticesIndex\resolve_facet( $term, $facet );
	if ( is_array( $f ) ) {
		$fids = PracticesIndex\facet_ids( $term, $f );
		if ( function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
			$fids = \Oria\Core\Cities\filter_ids( $fids, $city );
		}
		if ( $fids ) {
			return $base . $cat . '/' . $f['slug'] . '/';
		}
	}

	return $base . $cat . '/';
}

/**
 * Every city, with where this page would be in it and how much it holds.
 *
 * @return list<array{name: string, url: string, current: bool, count: int}>
 */
function city_options(): array {
	$now = (string) ( Cities\current()['slug'] ?? '' );
	$out = array();

	foreach ( Cities\all() as $city ) {
		$slug = (string) ( $city['slug'] ?? '' );
		if ( '' === $slug ) {
			continue;
		}

		$clause = Cities\tax_clause( $city );
		$args   = array(
			'post_type'              => PostTypes\LISTING,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		);
		if ( $clause ) {
			$args['tax_query'] = array( $clause ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$out[] = array(
			'name'    => Cities\name( $city ),
			'url'     => switch_url( $city ),
			'current' => $slug === $now,
			'count'   => count( get_posts( $args ) ),
		);
	}

	return $out;
}

/** Is the current request one of the new addresses? */
function is_explore(): bool {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );

	return (bool) preg_match( '~^/' . PATH . '(/|$)~', $path );
}


/* ---------------------------------------------------------------- sitemap */

/**
 * The addresses this module owns: the directory root and one hub per city.
 *
 * Everything below them is already covered -- the category pages ride
 * Yoast's practice sitemap, the facets have their own -- so this is a short
 * list of the parents those all hang off.
 *
 * @return list<array{loc: string}>
 */
function sitemap_entries(): array {
	$out = array( array( 'loc' => home_url( '/' . PATH . '/' ) ) );

	foreach ( Cities\all() as $city ) {
		if ( '' === (string) ( $city['slug'] ?? '' ) ) {
			continue;
		}
		$loc = base_url( $city );
		if ( ! in_array( $loc, array_column( $out, 'loc' ), true ) ) {
			$out[] = array( 'loc' => $loc );
		}
	}

	return $out;
}

function register_sitemap(): void {
	if ( ! isset( $GLOBALS['wpseo_sitemaps'] ) || ! method_exists( $GLOBALS['wpseo_sitemaps'], 'register_sitemap' ) ) {
		return;
	}
	$GLOBALS['wpseo_sitemaps']->register_sitemap( SITEMAP, __NAMESPACE__ . '\build_sitemap' );
}

function build_sitemap(): void {
	$sm = $GLOBALS['wpseo_sitemaps'] ?? null;
	if ( ! $sm || ! isset( $sm->renderer ) ) {
		return;
	}
	$links = array();
	foreach ( sitemap_entries() as $e ) {
		$links[] = array( 'loc' => $e['loc'], 'mod' => gmdate( 'c' ) );
	}
	$sm->set_sitemap( $sm->renderer->get_sitemap( $links, SITEMAP, 1 ) );
}

/** Only advertise the sitemap when it has something in it. */
function sitemap_index( $xml ) {
	if ( ! sitemap_entries() ) {
		return $xml;
	}
	return $xml . sprintf(
		"<sitemap><loc>%s</loc><lastmod>%s</lastmod></sitemap>\n",
		esc_url( home_url( '/' . SITEMAP . '-sitemap.xml' ) ),
		esc_html( gmdate( 'c' ) )
	);
}
