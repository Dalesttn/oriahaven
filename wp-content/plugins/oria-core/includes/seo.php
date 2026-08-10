<?php
/**
 * SEO landing-page plumbing.
 *
 * Two families of indexable pages on top of the directory:
 *
 *   /perth/{specialty}/            "Acupuncture in Perth"
 *   /practice/{practice}/{area}/   "Breathwork in Fremantle & South",
 *                                  "Bodywork & Massage in Joondalup"
 *
 * The combo route reuses the practice taxonomy archive with an extra
 * `oria_area` query var; the template locks both facets in the directory
 * engine. Titles and meta descriptions are written here (via Yoast's
 * filters, falling back to core document titles), and combos with no
 * matching listings are marked noindex so thin pages never enter the index.
 */

declare(strict_types=1);

namespace Oria\Core\Seo;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR  = 'oria_area';
const REWRITE_V  = '3'; // 3: /perth/ hub route added.

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\add_routes', 10 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 20 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\validate_combo' );
	add_action( 'template_redirect', __NAMESPACE__ . '\legacy_events_url' );

	// Yoast when present, core titles as the fallback.
	add_filter( 'wpseo_title', __NAMESPACE__ . '\seo_title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\seo_description' );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\seo_canonical' );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\seo_robots' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );
	add_filter( 'wpseo_opengraph_image', __NAMESPACE__ . '\og_image' );
}

function add_routes(): void {
	// /practice/{practice}/{area}/ — area may be a region or a suburb term.
	add_rewrite_rule(
		'^practice/([^/]+)/([^/]+)/?$',
		'index.php?practice=$matches[1]&' . QUERY_VAR . '=$matches[2]',
		'top'
	);
}

/** Bare /events/ was the archive before it moved to /whats-on-perth/. */
function legacy_events_url(): void {
	if ( is_404() && '/events/' === trailingslashit( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ) ) {
		wp_safe_redirect( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ), 301 );
		exit;
	}
}

/** Rules changed? Flush once, not on every load. */
function maybe_flush(): void {
	if ( get_option( 'oria_seo_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_seo_rewrite_v', REWRITE_V );
	}
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/* ------------------------------------------------------------ combo state */

/** The area term for the current combo page, or null. */
function combo_area(): ?\WP_Term {
	if ( ! is_tax( Taxonomies\PRACTICE ) ) {
		return null;
	}
	$slug = (string) get_query_var( QUERY_VAR );
	if ( '' === $slug ) {
		return null;
	}
	$term = get_term_by( 'slug', $slug, Taxonomies\AREA );
	return $term instanceof \WP_Term ? $term : null;
}

/** How many published listings match the current combo. */
function combo_count(): int {
	static $count = null;
	if ( null !== $count ) {
		return $count;
	}
	$practice = get_queried_object();
	$area     = combo_area();
	if ( ! $practice instanceof \WP_Term || ! $area instanceof \WP_Term ) {
		return $count = 0;
	}
	$q = new \WP_Query(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'tax_query'      => array(
				array( 'taxonomy' => Taxonomies\PRACTICE, 'field' => 'term_id', 'terms' => $practice->term_id ),
				array( 'taxonomy' => Taxonomies\AREA, 'field' => 'term_id', 'terms' => $area->term_id ),
			),
		)
	);
	return $count = (int) $q->found_posts;
}

/** An unknown area slug on a combo URL 301s to the plain practice page. */
function validate_combo(): void {
	if ( ! is_tax( Taxonomies\PRACTICE ) || '' === (string) get_query_var( QUERY_VAR ) ) {
		return;
	}
	if ( null === combo_area() ) {
		$practice = get_queried_object();
		$link     = $practice instanceof \WP_Term ? get_term_link( $practice ) : home_url( '/' );
		wp_safe_redirect( is_string( $link ) ? $link : home_url( '/' ), 301 );
		exit;
	}
}

/* ------------------------------------------------------------------ names */

function decoded( \WP_Term $term ): string {
	return wp_specialchars_decode( $term->name, ENT_QUOTES );
}

/* ----------------------------------------------------------------- titles */

function seo_title( $title ) {
	$area = combo_area();
	if ( $area ) {
		$practice = get_queried_object();
		return sprintf( '%s in %s | %s', decoded( $practice ), decoded( $area ), get_bloginfo( 'name' ) );
	}
	if ( is_tax( Taxonomies\SPECIALTY ) ) {
		return sprintf( '%s in Perth | %s', decoded( get_queried_object() ), get_bloginfo( 'name' ) );
	}
	// The event archive title now lives in Yoast's own settings, so it stays
	// editable in the admin rather than being overridden from here.
	if ( is_singular( 'listing' ) && '' === (string) get_post_meta( get_the_ID(), '_yoast_wpseo_title', true ) ) {
		$context = listing_context( get_the_ID() );
		if ( '' !== $context ) {
			return sprintf( '%s — %s | %s', decoded_title( get_the_ID() ), $context, get_bloginfo( 'name' ) );
		}
	}
	return $title;
}

/** "Bodywork & Massage in Fremantle" for a listing, or ''. */
function listing_context( int $id ): string {
	$practice = wp_get_post_terms( $id, 'practice' )[0] ?? null;
	$suburb   = '';
	foreach ( wp_get_post_terms( $id, 'area' ) as $term ) {
		if ( $term->parent ) {
			$suburb = wp_specialchars_decode( $term->name );
			break;
		}
	}
	if ( ! $practice instanceof \WP_Term ) {
		return '' !== $suburb ? $suburb : '';
	}
	$name = wp_specialchars_decode( $practice->name );
	return '' !== $suburb ? sprintf( '%s in %s', $name, $suburb ) : $name;
}

function decoded_title( int $id ): string {
	return wp_specialchars_decode( (string) get_post_field( 'post_title', $id, 'raw' ) );
}

/** A first-160-characters description for entities without a hand-written one. */
function entity_description( int $id ): string {
	if ( 'event' === get_post_type( $id ) ) {
		$text = (string) get_field( 'event_description', $id );
	} else {
		// Listings keep their blurb in the excerpt; content is the long form.
		$text = (string) get_post_field( 'post_content', $id, 'raw' )
			?: (string) get_post_field( 'post_excerpt', $id, 'raw' );
	}
	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) ?? '' );
	if ( '' === $text ) {
		return '';
	}
	return mb_strlen( $text ) > 158 ? mb_substr( $text, 0, 157 ) . '…' : $text;
}

function seo_description( $desc ) {
	$area = combo_area();
	if ( $area ) {
		$practice = get_queried_object();
		return sprintf(
			'Compare %1$s in %2$s: real timetables, prices and contact details for every practice we\'ve verified. Independent Perth wellness directory.',
			strtolower( decoded( $practice ) ),
			decoded( $area )
		);
	}
	if ( is_tax( Taxonomies\SPECIALTY ) ) {
		$term = get_queried_object();
		return $term->description
			? wp_specialchars_decode( $term->description, ENT_QUOTES )
			: sprintf( 'Find %s across the Perth metro — timetables, prices and verified contact details.', strtolower( decoded( $term ) ) );
	}
	if ( ( is_singular( 'listing' ) || is_singular( 'event' ) ) && ! $desc ) {
		$auto = entity_description( (int) get_the_ID() );
		if ( '' !== $auto ) {
			return $auto;
		}
	}
	if ( is_post_type_archive( 'event' ) && ! $desc ) {
		return __( 'Every wellness workshop, sitting and session across the Perth metro, updated daily — filter by date, suburb, type and price.', 'oria' );
	}
	if ( is_front_page() && ! $desc ) {
		return __( "Perth's independent wellness directory: meditation, yoga, breathwork, massage and more — verified practices, honest prices, and what's on this weekend.", 'oria' );
	}
	if ( is_post_type_archive( 'listing' ) && ! $desc ) {
		return __( 'Browse every meditation studio, yoga school, breathwork facilitator and wellness practice in Perth — checked by hand, with real timetables and prices.', 'oria' );
	}
	return $desc;
}

/** Listings have no featured image; their gallery's lead photo shares well. */
function og_image( $image ) {
	if ( is_singular( 'listing' ) && ! $image ) {
		$gallery = array_values( array_filter( array_map( 'intval', (array) ( get_field( 'gallery', get_the_ID() ) ?: array() ) ) ) );
		if ( $gallery ) {
			return (string) ( wp_get_attachment_image_url( $gallery[0], 'large' ) ?: $image );
		}
	}
	return $image;
}

function seo_canonical( $canonical ) {
	$area = combo_area();
	if ( $area ) {
		$practice = get_queried_object();
		return home_url( '/practice/' . $practice->slug . '/' . $area->slug . '/' );
	}
	return $canonical;
}

/** Empty combos never enter the index; everything else follows Yoast. */
function seo_robots( $robots ) {
	if ( combo_area() && 0 === combo_count() ) {
		return 'noindex, follow';
	}
	return $robots;
}

/** Core <title> fallback for the same pages when Yoast is inactive. */
function core_title( array $parts ): array {
	$area = combo_area();
	if ( $area ) {
		$parts['title'] = sprintf( '%s in %s', decoded( get_queried_object() ), decoded( $area ) );
	} elseif ( is_tax( Taxonomies\SPECIALTY ) ) {
		$parts['title'] = sprintf( '%s in Perth', decoded( get_queried_object() ) );
	} elseif ( is_post_type_archive( 'event' ) ) {
		$parts['title'] = __( "What's on in Perth — wellness workshops & events", 'oria' );
	} elseif ( is_singular( 'listing' ) ) {
		$context = listing_context( (int) get_the_ID() );
		if ( '' !== $context ) {
			$parts['title'] = sprintf( '%s — %s', decoded_title( (int) get_the_ID() ), $context );
		}
	}
	return $parts;
}
