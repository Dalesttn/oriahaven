<?php
/**
 * /offers/ -- every live, checked wellness offer in Perth on one page
 * (Phase 5 of the Oct 2026 brief; approved by Dale 13 Oct 2026).
 *
 * Read from the same records as the category pages (Theme\active_offer() via
 * Offers\among()), drawn with the same card (template-parts/offer-card.php),
 * so an offer reads the same everywhere. Order is editorial and never paid:
 * newest-checked first, then soonest to end. Paid promotions (offer-
 * promotions.php) appear once, in their own band above the list, each card
 * labelled "Promoted", and are not repeated inside it.
 *
 * ?type=<practice slug> narrows to one category. Those variants are
 * canonicalised to /offers/ and noindexed, so the hub is one page to search
 * engines. With no live offers at all the hub 404s rather than show an
 * empty commercial page; editors can still preview it.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\OffersHub;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PATH = 'offers';
const QV   = 'oria_offers_hub';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', static fn( array $v ): array => array_merge( $v, array( QV ) ) );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_action( 'template_redirect', __NAMESPACE__ . '\gate' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\seo_title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\seo_description' );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\seo_canonical' );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\seo_robots' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );
}
bootstrap();

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QV . '=1', 'top' );
	if ( get_option( 'oria_offers_hub_rewrite' ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'oria_offers_hub_rewrite', '1' );
	}
}

function url( string $type = '' ): string {
	$u = home_url( '/' . PATH . '/' );
	return '' !== $type ? add_query_arg( 'type', $type, $u ) : $u;
}

function is_hub(): bool {
	return (bool) get_query_var( QV );
}

function fix_query( \WP_Query $q ): void {
	if ( $q->is_main_query() && $q->get( QV ) ) {
		$q->is_home = $q->is_front_page = $q->is_archive = $q->is_singular = $q->is_404 = false;
		$q->set( 'posts_per_page', 1 );
	}
}

/* ------------------------------------------------------------------- data */

/**
 * Every live offer on a published listing, in editorial order, each with
 * its listing's primary category. Cached for the request.
 *
 * @return list<array{id:int, offer:array, cat:string}>
 */
function rows(): array {
	static $rows = null;
	if ( null !== $rows ) {
		return $rows;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids  = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'offer_title' AND meta_value <> ''" ) );
	$rows = array();
	foreach ( \Oria\Core\Offers\among( $ids, 500 ) as $r ) {
		if ( PostTypes\LISTING !== get_post_type( (int) $r['id'] ) ) {
			continue;
		}
		$term      = function_exists( '\Oria\Core\Categories\primary_for' ) ? \Oria\Core\Categories\primary_for( (int) $r['id'] ) : null;
		$r['cat']  = $term instanceof \WP_Term ? (string) $term->slug : '';
		$rows[]    = $r;
	}
	return $rows;
}

function published(): bool {
	return (bool) rows();
}

/** The category chips: only categories that have a live offer, busiest first. */
function chips(): array {
	$n = array();
	foreach ( rows() as $r ) {
		if ( '' !== $r['cat'] ) {
			$n[ $r['cat'] ] = ( $n[ $r['cat'] ] ?? 0 ) + 1;
		}
	}
	arsort( $n );
	$out = array();
	foreach ( $n as $slug => $count ) {
		$t = get_term_by( 'slug', (string) $slug, 'practice' );
		if ( $t instanceof \WP_Term ) {
			$out[] = array( 'slug' => (string) $slug, 'name' => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ), 'count' => $count );
		}
	}
	return $out;
}

/** The ?type= filter, only if it is a category that has offers. */
function current_type(): string {
	$t = sanitize_key( (string) ( $_GET['type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only filter.
	foreach ( chips() as $c ) {
		if ( $c['slug'] === $t ) {
			return $t;
		}
	}
	return '';
}

/* ------------------------------------------------------------------ serve */

function serving(): bool {
	return is_hub() && ( published() || current_user_can( 'edit_posts' ) );
}

function gate(): void {
	if ( ! is_hub() || published() || current_user_can( 'edit_posts' ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
}

function template( string $t ): string {
	return serving() ? ( locate_template( array( 'oria-offers.php' ) ) ?: $t ) : $t;
}

function seo_title( $t ) {
	return serving() ? __( 'Wellness Offers in Perth: Intro Deals & Specials | Oria Haven', 'oria' ) : $t;
}

function core_title( array $parts ): array {
	if ( serving() ) {
		$parts['title'] = __( 'Wellness offers in Perth', 'oria' );
	}
	return $parts;
}

function seo_description( $d ) {
	return serving() ? __( 'Current intro offers and specials from Perth wellness businesses: yoga, Pilates, saunas, massage and more, each read from the business’s own site with the date it was checked.', 'oria' ) : $d;
}

function seo_canonical( $c ) {
	return serving() ? url() : $c;
}

function seo_robots( $r ) {
	return serving() && '' !== current_type() ? 'noindex, follow' : $r;
}
