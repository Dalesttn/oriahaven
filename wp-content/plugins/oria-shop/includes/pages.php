<?php
/**
 * Shop pages: the category address, and what search engines are told.
 *
 * /shop/{category}/ is a real address for a shelf -- the same template as
 * /shop/, arriving with that category selected and headed by the copy an
 * editor wrote for it. It is indexable only when that copy exists: a shelf
 * with an introduction is a page worth sending someone to, a bare filter is
 * not, and the bare one carries noindex and a canonical back to /shop/ so
 * the two never compete. Categories grow into pages by having something
 * written about them, not by being switched on.
 *
 * The shop's own title and description, and an ItemList of what is shown,
 * live here too. No Product schema: these are affiliate recommendations
 * with approximate prices, and a Product node without a real offer earns
 * warnings rather than results.
 */

declare(strict_types=1);

namespace Oria\Shop\Pages;

use Oria\Shop\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_shop_cat';
const PAGE      = 'shop';
const REWRITE_V = '1';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 10 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\not_found', 5 );

	add_filter( 'wp_robots', __NAMESPACE__ . '\wp_robots' );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\yoast_robots', 20 );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical', 20 );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 20 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 20 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description', 20 );
	add_action( 'wp_footer', __NAMESPACE__ . '\schema', 20 );
}

/* ---------------------------------------------------------------- routes */

function route(): void {
	// The page is still the page: pagename=shop resolves it, the extra var
	// tells the template which shelf to open.
	add_rewrite_rule( '^' . PAGE . '/([a-z0-9-]+)/?$', 'index.php?pagename=' . PAGE . '&' . QUERY_VAR . '=$matches[1]', 'top' );
}

function maybe_flush(): void {
	if ( get_option( 'oria_shop_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_shop_rewrite_v', REWRITE_V );
	}
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/** Are we on /shop/ (either address)? */
function is_shop(): bool {
	return is_page( PAGE );
}

/** The category term a /shop/{category}/ address names, or null on /shop/. */
function category(): ?\WP_Term {
	if ( ! is_shop() ) {
		return null;
	}
	$slug = sanitize_title( (string) get_query_var( QUERY_VAR ) );
	if ( '' === $slug ) {
		return null;
	}
	$term = get_term_by( 'slug', $slug, Data\TAX );
	return $term instanceof \WP_Term ? $term : null;
}

/** A category address that names nothing is a 404, not an empty shop. */
function is_missing_category(): bool {
	return is_shop() && '' !== (string) get_query_var( QUERY_VAR ) && null === category();
}

/** /shop/not-a-shelf/ is a 404, not an empty shop wearing the wrong address. */
function not_found(): void {
	if ( ! is_missing_category() ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}

/** A shelf is a page when an editor has introduced it. */
function is_landing( ?\WP_Term $term ): bool {
	return $term instanceof \WP_Term && '' !== trim( (string) get_term_meta( $term->term_id, 'intro', true ) );
}

function shop_url(): string {
	$page = get_page_by_path( PAGE );
	return $page instanceof \WP_Post ? (string) get_permalink( $page ) : home_url( '/' . PAGE . '/' );
}

function category_url( \WP_Term $term ): string {
	return trailingslashit( shop_url() ) . $term->slug . '/';
}

/* ------------------------------------------------------------------- seo */

function wp_robots( array $r ): array {
	$term = category();
	if ( $term && ! is_landing( $term ) ) {
		$r['noindex'] = true;
		unset( $r['nofollow'] );
	}
	return $r;
}

function yoast_robots( $robots ) {
	$term = category();
	return ( $term && ! is_landing( $term ) ) ? 'noindex, follow' : $robots;
}

function canonical( $url ) {
	$term = category();
	if ( ! $term ) {
		return $url;
	}
	return is_landing( $term ) ? category_url( $term ) : shop_url();
}

function heading( ?\WP_Term $term = null ): string {
	if ( $term ) {
		$h = trim( (string) get_term_meta( $term->term_id, 'heading', true ) );
		return '' !== $h ? $h : $term->name;
	}
	return __( 'Shop wellness products worth knowing about', 'oria' );
}

function title( string $title ): string {
	if ( ! is_shop() ) {
		return $title;
	}
	$term = category();
	if ( $term ) {
		/* translators: %s: category name */
		return sprintf( __( '%s | Oria Haven Shop', 'oria' ), heading( $term ) );
	}
	// Only when nobody typed one into Yoast.
	if ( '' !== (string) get_post_meta( (int) get_queried_object_id(), '_yoast_wpseo_title', true ) ) {
		return $title;
	}
	return __( 'Wellness Products Australia | Curated Wellness Shop | Oria Haven', 'oria' );
}

function core_title( array $parts ): array {
	if ( is_shop() ) {
		$term           = category();
		$parts['title'] = $term ? heading( $term ) : __( 'Wellness Products Australia', 'oria' );
	}
	return $parts;
}

function description( string $desc ): string {
	if ( ! is_shop() ) {
		return $desc;
	}
	$term = category();
	if ( $term ) {
		$intro = trim( (string) get_term_meta( $term->term_id, 'intro', true ) );
		return '' !== $intro ? wp_trim_words( $intro, 28, '…' ) : $desc;
	}
	return '' !== $desc ? $desc : __( 'Discover hand-picked wellness products for meditation, relaxation, yoga, sound healing, sleep and everyday wellbeing. Curated by Oria Haven.', 'oria' );
}

/**
 * The template says which products it drew; the ItemList describes those.
 *
 * @var list<array<string,mixed>>|null
 */
$GLOBALS['oria_shop_list'] = $GLOBALS['oria_shop_list'] ?? null;

/** @param list<array<string,mixed>> $rows */
function register_list( array $rows ): void {
	$GLOBALS['oria_shop_list'] = $rows;
}

function schema(): void {
	$rows = $GLOBALS['oria_shop_list'] ?? null;
	if ( ! is_shop() || ! is_array( $rows ) || count( $rows ) < 2 ) {
		return;
	}
	$term  = category();
	$items = array();
	foreach ( $rows as $i => $r ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => wp_specialchars_decode( (string) $r['title'] ),
			'url'      => (string) $r['url'],
		);
	}
	$graph = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'@id'             => ( $term ? category_url( $term ) : shop_url() ) . '#products',
		'name'            => $term ? heading( $term ) : __( 'Wellness products', 'oria' ),
		'numberOfItems'   => count( $items ),
		'itemListElement' => $items,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}
