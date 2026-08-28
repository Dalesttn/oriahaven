<?php
/**
 * The blocks a guide uses to hand its reader to the directory.
 *
 * Shortcodes rather than hand-pasted HTML, because the listings move: a
 * studio closes, a price changes, a new one opens in Subiaco -- and a
 * guide quoting last month's directory is exactly the stale SEO page the
 * journal exists to not be. Each render reads the live directory.
 *
 * [oria_listings svc="reformer-pilates" n="4"]  cards for practices
 *     offering the service, best-rated first.
 * [oria_suburbs svc="reformer-pilates" cat="fitness"]  links to the
 *     category x suburb pages that actually contain these practices.
 */

declare(strict_types=1);

namespace Oria\Core\GuideBlocks;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_shortcode( 'oria_listings', __NAMESPACE__ . '\listings' );
	add_shortcode( 'oria_suburbs', __NAMESPACE__ . '\suburbs' );
}

/** Listings carrying any of the given service slugs, best-rated first. */
function ids_for( array $svc_slugs, int $limit ): array {
	$q = new \WP_Query(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array(
				array( 'taxonomy' => 'service', 'field' => 'slug', 'terms' => $svc_slugs ),
			),
		)
	);
	$ids = array_map( 'intval', $q->posts );

	// Rating carries the sort; unrated listings still appear, after.
	usort(
		$ids,
		static function ( int $a, int $b ): int {
			$ra = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( $a ) : array( 'rating' => 0, 'count' => 0 );
			$rb = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( $b ) : array( 'rating' => 0, 'count' => 0 );
			return ( $rb['rating'] <=> $ra['rating'] ) ?: ( $rb['count'] <=> $ra['count'] );
		}
	);
	return array_slice( $ids, 0, $limit );
}

function listings( $atts ): string {
	$a    = shortcode_atts( array( 'svc' => '', 'n' => 4 ), $atts );
	$svcs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $a['svc'] ) ) );
	if ( ! $svcs ) {
		return '';
	}
	$ids = ids_for( $svcs, max( 1, min( 6, (int) $a['n'] ) ) );
	if ( ! $ids ) {
		return '';
	}

	$out = '<div class="guidecards">';
	foreach ( $ids as $id ) {
		$name   = function_exists( '\Oria\Theme\ptitle' ) ? \Oria\Theme\ptitle( $id ) : get_the_title( $id );
		$img    = function_exists( '\Oria\Theme\listing_image' ) ? \Oria\Theme\listing_image( $id ) : '';
		$rated  = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( $id ) : array( 'rating' => 0, 'count' => 0 );
		$suburb = '';
		foreach ( wp_get_post_terms( $id, 'area' ) as $t ) {
			if ( $t->parent ) {
				$suburb = html_entity_decode( $t->name, ENT_QUOTES );
				break;
			}
		}
		$from = (float) get_field( 'price_from', $id );

		$out .= '<a class="guidecard" href="' . esc_url( get_permalink( $id ) ) . '">';
		if ( $img ) {
			$out .= '<span class="guidecard__media"><img class="guidecard__img" src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async"></span>';
		}
		$out .= '<span class="guidecard__body">';
		$out .= '<b class="guidecard__name">' . esc_html( $name ) . '</b>';
		if ( $suburb ) {
			$out .= '<span class="guidecard__meta">' . esc_html( $suburb ) . '</span>';
		}
		if ( $rated['rating'] > 0 ) {
			/* The practice's own Google rating, reproduced -- same source every card on the site uses. */
			$out .= '<span class="guidecard__meta">&#9733; ' . esc_html( number_format_i18n( $rated['rating'], 1 ) ) . ' (' . esc_html( number_format_i18n( $rated['count'] ) ) . ')</span>';
		}
		if ( $from > 0 ) {
			$out .= '<span class="guidecard__meta">' . sprintf( esc_html__( 'From $%s', 'oria' ), esc_html( number_format_i18n( $from ) ) ) . '</span>';
		}
		$out .= '<span class="guidecard__go">' . esc_html__( 'View practice', 'oria' ) . ' &rarr;</span>';
		$out .= '</span></a>';
	}
	$out .= '</div>';
	return $out;
}

function suburbs( $atts ): string {
	$a    = shortcode_atts( array( 'svc' => '', 'cat' => '' ), $atts );
	$svcs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $a['svc'] ) ) );
	$cat  = sanitize_title( (string) $a['cat'] );
	if ( ! $svcs || '' === $cat ) {
		return '';
	}
	$term = get_term_by( 'slug', $cat, Taxonomies\PRACTICE );
	if ( ! $term instanceof \WP_Term ) {
		return '';
	}

	// Suburbs come from the listings themselves, so a link never points at
	// an empty page.
	$seen = array();
	foreach ( ids_for( $svcs, 50 ) as $id ) {
		foreach ( wp_get_post_terms( $id, 'area' ) as $t ) {
			if ( $t->parent && ! isset( $seen[ $t->slug ] ) ) {
				$seen[ $t->slug ] = html_entity_decode( $t->name, ENT_QUOTES );
			}
		}
	}
	if ( ! $seen ) {
		return '';
	}
	asort( $seen );

	$out = '<div class="guideburbs">';
	foreach ( $seen as $slug => $name ) {
		$url  = home_url( '/practices/' . $term->slug . '/' . $slug . '/' );
		$out .= '<a class="pill pill--sand" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
	}
	$out .= '</div>';
	return $out;
}

bootstrap();
