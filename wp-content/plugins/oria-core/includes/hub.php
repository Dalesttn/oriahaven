<?php
/**
 * The /perth/ hub: one page that links to every practice, specialty and
 * area in the directory.
 *
 * Its job is crawl depth. Without it, a specialty term with three
 * listings is four or five clicks from the home page and Google may
 * simply never spend the budget; from a hub linked in the footer,
 * everything is two. It earns its keep for readers too — it is the only
 * page that shows the whole shape of the directory at once.
 *
 * Built as a route rather than a WP page on purpose: a route ships in
 * git and appears on production the moment the code lands, where a page
 * would have to be recreated by hand in every environment.
 */

declare(strict_types=1);

namespace Oria\Core\Hub;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_hub';
const PATH      = 'perth';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 10 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );

	add_filter( 'wpseo_title', __NAMESPACE__ . '\title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description' );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );
}

/**
 * /perth/ only. The trailing-segment form /perth/{slug}/ already belongs
 * to the specialty taxonomy, and this rule cannot match it — the pattern
 * ends after the slash.
 */
function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QUERY_VAR . '=1', 'top' );
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

function is_hub(): bool {
	return (bool) get_query_var( QUERY_VAR );
}

/**
 * A rule with no post parameters lands WordPress on the home query. Undo
 * that so nothing downstream treats the hub as the front page, and keep
 * the pointless post fetch to a minimum.
 */
function fix_query( \WP_Query $q ): void {
	if ( ! $q->is_main_query() || ! $q->get( QUERY_VAR ) ) {
		return;
	}
	$q->is_home         = false;
	$q->is_front_page   = false;
	$q->is_archive      = false;
	$q->is_singular     = false;
	$q->is_404          = false;
	$q->set( 'posts_per_page', 1 );
}

function template( string $template ): string {
	if ( ! is_hub() ) {
		return $template;
	}
	$found = locate_template( array( 'oria-perth-hub.php' ) );
	return $found ?: $template;
}

/* -------------------------------------------------------------------- seo */

// Was 67 characters and truncating in the SERP; "modality" was the word
// carrying the least weight, so it went.
function title( $title ) {
	return is_hub() ? sprintf( 'Wellness in Perth — every practice and suburb | %s', get_bloginfo( 'name' ) ) : $title;
}

function core_title( array $parts ): array {
	if ( is_hub() ) {
		$parts['title'] = __( 'Wellness in Perth — every practice and suburb', 'oria' );
	}
	return $parts;
}

function description( $desc ) {
	if ( ! is_hub() ) {
		return $desc;
	}
	// Under 160 characters: Google truncates past roughly that, and this one
	// was running to 180. The counts grow, so the sentence needs slack too.
	return sprintf(
		'Browse every wellness practice in Perth by type, modality or suburb — %d listings across %d categories, checked by hand. Real prices and timetables.',
		count_listings(),
		count( practices() )
	);
}

function canonical( $url ) {
	return is_hub() ? home_url( '/' . PATH . '/' ) : $url;
}

/* ------------------------------------------------------------------- data */

function count_listings(): int {
	$counts = wp_count_posts( 'listing' );
	return (int) ( $counts->publish ?? 0 );
}

/**
 * Practice terms that actually hold listings, largest first — the order
 * a reader would want, and the order that puts our strongest pages
 * nearest the top of the link list.
 *
 * @return list<\WP_Term>
 */
function practices(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$terms = get_terms(
		array(
			'taxonomy'   => Taxonomies\PRACTICE,
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	return $cache = is_wp_error( $terms ) ? array() : $terms;
}

/** @return list<\WP_Term> */
function specialties( int $min = 1 ): array {
	$terms = get_terms(
		array(
			'taxonomy'   => Taxonomies\SPECIALTY,
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	return array_values( array_filter( $terms, static fn( $t ) => $t->count >= $min ) );
}

/**
 * Regions, each with the suburbs under it that hold listings.
 *
 * @return list<array{region: \WP_Term, suburbs: list<\WP_Term>}>
 */
function areas(): array {
	$regions = get_terms( array( 'taxonomy' => Taxonomies\AREA, 'parent' => 0, 'hide_empty' => false ) );
	if ( is_wp_error( $regions ) ) {
		return array();
	}
	$out = array();
	foreach ( $regions as $region ) {
		$kids = get_terms(
			array(
				'taxonomy'   => Taxonomies\AREA,
				'parent'     => $region->term_id,
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		$kids = is_wp_error( $kids ) ? array() : $kids;
		if ( $kids || $region->count > 0 ) {
			$out[] = array( 'region' => $region, 'suburbs' => $kids );
		}
	}
	return $out;
}
