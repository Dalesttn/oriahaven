<?php
/**
 * Landing pages for the kinds of event people search for by name.
 *
 * "Free wellness events Perth" and "sound bath Perth" are things typed into
 * Google; a filtered archive URL is not an answer to them, and the brief is
 * explicit that arbitrary filter combinations must not become pages.
 *
 * So a small, hand-written set instead, each one gated on its own
 * inventory: a collection is live only while it holds at least `min`
 * upcoming events. Below that it 404s rather than publishing an empty
 * page, and it leaves the sitemap on its own. The gate is the same idea
 * the facet pages use -- the site refuses to publish a page it cannot
 * fill, without anybody having to remember to check.
 *
 * Intros are hand-written and qualitative. The counts, prices and dates
 * on the page come from the events themselves, so nothing here goes stale.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\EventCollections;

use Oria\Core\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_event_collection';
const SITEMAP   = 'event-collection';
/** How many events a landing page needs before it exists at all. */
const FLOOR     = 3;
/** Most events a landing page shows before it asks you to browse. */
const MAX_SHOWN = 24;

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\routes' );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_var' );
	add_action( 'template_include', __NAMESPACE__ . '\template' );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\seo_title', 20 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\seo_description', 20 );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\seo_canonical', 20 );
	add_action( 'init', __NAMESPACE__ . '\register_sitemap', 20 );
	// The index filter is added unconditionally, like compare.php's: it runs
	// long after init and decides for itself whether there is anything to
	// advertise. Adding it from inside register_sitemap() meant an early
	// return there silently dropped the whole sitemap.
	add_filter( 'wpseo_sitemap_index', __NAMESPACE__ . '\sitemap_index' );
}

/** @return array<int, array<string, mixed>> */
function all(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$file = ORIA_CORE_DIR . 'data/event-collections.json';
	$raw  = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled data file.
	$rows = is_array( $raw ) && isset( $raw['collections'] ) ? (array) $raw['collections'] : array();

	$cache = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || empty( $row['slug'] ) ) {
			continue;
		}
		$cache[ sanitize_title( (string) $row['slug'] ) ] = $row;
	}
	return $cache;
}

/** One collection by slug, or null. */
function get( string $slug ): ?array {
	$all = all();
	return $all[ $slug ] ?? null;
}

function routes(): void {
	foreach ( array_keys( all() ) as $slug ) {
		add_rewrite_rule( '^' . preg_quote( $slug, '#' ) . '/?$', 'index.php?' . QUERY_VAR . '=' . $slug, 'top' );
	}
}

/** @param string[] $vars @return string[] */
function query_var( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/** The slug being viewed, or ''. */
function current(): string {
	$slug = (string) get_query_var( QUERY_VAR );
	return '' !== $slug && null !== get( $slug ) ? $slug : '';
}

/**
 * The slug being viewed as a real page.
 *
 * Below the floor the route still resolves -- that is how it knows to send
 * a 404 -- but nothing about the collection should reach the head of that
 * 404: a title and description for a page that is answering "not found"
 * is the soft-404 signal this whole gate exists to avoid.
 */
function current_live(): string {
	$slug = current();
	return '' !== $slug && live( $slug ) ? $slug : '';
}

/**
 * The events on a collection page.
 *
 * Asked of the one events service, so a landing page cannot disagree with
 * the archive about what is coming up or what has been cancelled.
 *
 * @return int[]
 */
function event_ids( string $slug, int $limit = MAX_SHOWN ): array {
	$row = get( $slug );
	if ( null === $row ) {
		return array();
	}

	$filter = (array) ( $row['filter'] ?? array() );
	$args   = array( 'limit' => $limit );
	foreach ( array( 'event_type', 'area', 'practice' ) as $key ) {
		if ( ! empty( $filter[ $key ] ) ) {
			$args[ $key ] = (string) $filter[ $key ];
		}
	}

	$ids = Events\upcoming( $args );

	// Price is not a taxonomy, so it filters here -- on the same rule the
	// archive's chips use, free meaning free or by donation.
	if ( 'free' === (string) ( $filter['price'] ?? '' ) ) {
		$ids = array_values(
			array_filter(
				$ids,
				static function ( int $id ): bool {
					$price = (string) get_post_meta( $id, 'price', true );
					return '' !== trim( $price ) && (bool) preg_match( '/free|donation|^\$?0(\.00)?$/i', $price );
				}
			)
		);
	}

	return $ids;
}

/** Is this collection carrying enough to be a page at all? */
function live( string $slug ): bool {
	$row = get( $slug );
	if ( null === $row ) {
		return false;
	}
	$min = max( 1, (int) ( $row['min'] ?? FLOOR ) );
	return count( event_ids( $slug, $min ) ) >= $min;
}

function url( string $slug ): string {
	return home_url( '/' . $slug . '/' );
}

/* ---------------------------------------------------------------- render */

function template( string $template ): string {
	$slug = current();
	if ( '' === $slug ) {
		return $template;
	}

	/*
	 * Below the floor the page does not exist. A 404 is the honest answer:
	 * publishing "Free wellness events in Perth" over an empty row would
	 * tell a visitor there are none, which is a different claim and
	 * usually a wrong one.
	 */
	if ( ! live( $slug ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return get_query_template( '404' );
	}

	$found = locate_template( array( 'event-collection.php' ) );
	return $found ?: $template;
}

/* ------------------------------------------------------------------- seo */

function seo_title( $title ) {
	$slug = current_live();
	$row  = '' !== $slug ? get( $slug ) : null;
	return $row ? sprintf( '%s | Oria Haven', (string) $row['title'] ) : $title;
}

function seo_description( $desc ) {
	$slug = current_live();
	$row  = '' !== $slug ? get( $slug ) : null;
	if ( ! $row ) {
		return $desc;
	}
	// The intro's first sentence, clamped -- written for people first.
	$intro = (string) ( $row['intro'] ?? '' );
	$first = trim( (string) strtok( $intro, '.' ) );
	return '' !== $first ? rtrim( mb_substr( $first, 0, 155 ) ) . '.' : $desc;
}

function seo_canonical( $canonical ) {
	$slug = current_live();
	return '' !== $slug ? url( $slug ) : $canonical;
}

/* --------------------------------------------------------------- sitemap */

function register_sitemap(): void {
	if ( ! isset( $GLOBALS['wpseo_sitemaps'] ) || ! method_exists( $GLOBALS['wpseo_sitemaps'], 'register_sitemap' ) ) {
		return;
	}
	$GLOBALS['wpseo_sitemaps']->register_sitemap( SITEMAP, __NAMESPACE__ . '\build_sitemap' );
}

/** @return string[] */
function live_slugs(): array {
	$out = array();
	foreach ( array_keys( all() ) as $slug ) {
		if ( live( $slug ) ) {
			$out[] = $slug;
		}
	}
	return $out;
}

function sitemap_index( $index ) {
	$slugs = live_slugs();
	if ( ! $slugs ) {
		return $index;
	}
	$newest = 0;
	foreach ( $slugs as $slug ) {
		foreach ( event_ids( $slug, 1 ) as $id ) {
			$newest = max( $newest, (int) get_post_modified_time( 'U', true, $id ) );
		}
	}
	return $index . '<sitemap><loc>' . esc_url( home_url( '/' . SITEMAP . '-sitemap.xml' ) ) . '</loc>'
		. '<lastmod>' . esc_html( gmdate( 'c', $newest ?: time() ) ) . '</lastmod></sitemap>' . "\n";
}

function build_sitemap(): void {
	$sm = $GLOBALS['wpseo_sitemaps'] ?? null;
	if ( ! $sm || ! isset( $sm->renderer ) ) {
		return;
	}
	$links = array();
	foreach ( live_slugs() as $slug ) {
		$newest = 0;
		foreach ( event_ids( $slug ) as $id ) {
			$newest = max( $newest, (int) get_post_modified_time( 'U', true, $id ) );
		}
		$links[] = array(
			'loc' => url( $slug ),
			'mod' => gmdate( 'c', $newest ?: time() ),
		);
	}
	$sm->set_sitemap( $sm->renderer->get_sitemap( $links, SITEMAP, 0 ) );
}
