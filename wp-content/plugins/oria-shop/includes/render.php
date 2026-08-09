<?php
/**
 * Rendering: the [wellness_products] shortcode and the band templates
 * pull from the engine and draw branded cards — no product images (Amazon
 * only licenses images through the PA-API, which isn't unlocked yet), so
 * the cards are typographic: category, title, brand, blurb, price, button.
 *
 * [wellness_products category="meditation-cushions,journals" limit="4"]
 * [wellness_products automatic="true"]   ← resolves from the current page.
 */

declare(strict_types=1);

namespace Oria\Shop\Render;

use Oria\Shop\Data;
use Oria\Shop\Engine;
use Oria\Shop\Track;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_shortcode( 'wellness_products', __NAMESPACE__ . '\shortcode' );
}

/** @param array<string, string>|string $atts */
function shortcode( $atts ): string {
	$atts = shortcode_atts(
		array(
			'category'  => '',
			'limit'     => '',
			'automatic' => '',
			'heading'   => '',
		),
		(array) $atts
	);

	$limit = (int) $atts['limit'];
	if ( '' !== $atts['category'] ) {
		$ids      = Engine\ids_from_slugs( array_filter( array_map( 'trim', explode( ',', (string) $atts['category'] ) ) ) );
		$products = Engine\products( $ids, $limit );
	} else {
		$products = auto_products( $limit );
	}

	return band( $products, (string) $atts['heading'] );
}

/** Products implied by the page being viewed — pinned first on practices. */
function auto_products( int $limit = 0 ): array {
	if ( is_singular( 'listing' ) || is_singular( 'event' ) ) {
		$terms = wp_get_post_terms( get_the_ID(), 'practice' );
		if ( ! is_wp_error( $terms ) && $terms ) {
			return Engine\products_for_practice( $terms[0], $limit );
		}
		return array();
	}
	if ( is_singular( 'post' ) ) {
		return Engine\products( Engine\categories_for_post( (int) get_the_ID() ), $limit );
	}
	if ( is_tax( 'practice' ) ) {
		$term = get_queried_object();
		return $term instanceof \WP_Term ? Engine\products_for_practice( $term, $limit ) : array();
	}
	return array();
}

/**
 * The band: heading, card grid, disclosure. Empty product list renders
 * nothing at all — no headings over empty space, no disclosure without
 * links to disclose.
 *
 * @param array<int, array<string, string>> $products
 */
function band( array $products, string $heading = '' ): string {
	if ( ! $products ) {
		return '';
	}
	$heading = $heading ?: __( 'Products to support your practice', 'oria' );

	$out  = '<div class="shopband">';
	$out .= '<div class="row-between" style="margin-bottom:1rem"><h2 class="h3" style="margin:0">' . esc_html( $heading ) . '</h2>';
	$out .= '<a class="btn btn--ghost btn--sm btn--plain" href="' . esc_url( home_url( '/shop/' ) ) . '">' . esc_html__( 'Shop all', 'oria' ) . '</a></div>';
	$out .= '<div class="prodgrid">';
	foreach ( $products as $p ) {
		$out .= card( $p );
	}
	$out .= '</div>';
	$out .= '<p class="shopband__disclosure">' . esc_html( Data\disclosure() ) . '</p>';
	$out .= '</div>';

	Track\impressions( $products );
	return $out;
}

/** @param array<string, string> $p */
function card( array $p ): string {
	$out = '<div class="prodcard" data-oshop-product="' . esc_attr( $p['id'] ) . '" data-oshop-cat="' . esc_attr( $p['category'] ) . '">';
	if ( '' !== ( $p['image'] ?? '' ) ) {
		// Only ever an API-provided URL (PA-API mode) — never a scraped one.
		$out .= '<span class="prodcard__img"><img src="' . esc_url( $p['image'] ) . '" alt="" loading="lazy"></span>';
	}
	if ( '' !== $p['category'] ) {
		$out .= '<span class="prodcard__cat micro">' . esc_html( $p['category'] ) . '</span>';
	}
	$out .= '<b class="prodcard__name">' . esc_html( $p['title'] ) . '</b>';
	if ( '' !== $p['brand'] ) {
		$out .= '<em class="prodcard__brand">' . esc_html( $p['brand'] ) . '</em>';
	}
	if ( '' !== $p['blurb'] ) {
		$out .= '<p class="prodcard__blurb">' . esc_html( $p['blurb'] ) . '</p>';
	}
	$out .= '<div class="prodcard__foot">';
	if ( '' !== $p['price'] ) {
		/* translators: %s: approximate price */
		$out .= '<span class="prodcard__price">' . esc_html( sprintf( __( 'around %s', 'oria' ), $p['price'] ) ) . '</span>';
	}
	$out .= '<a class="btn btn--dark btn--sm" href="' . esc_url( $p['url'] ) . '" target="_blank" rel="sponsored nofollow noopener" data-oshop-click="' . esc_attr( $p['id'] ) . '">'
		. esc_html__( 'View on Amazon', 'oria' ) . ' ' . \Oria\Theme\arrow() . '</a>';
	$out .= '</div></div>';
	return $out;
}

/** Automatic band for templates: resolves context, renders, or ''. */
function auto_band( string $heading = '' ): string {
	return band( auto_products(), $heading );
}
