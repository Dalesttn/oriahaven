<?php
/**
 * Taxonomies: practice, and a single hierarchical area tree.
 */

declare(strict_types=1);

namespace Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRACTICE  = 'practice';
const AREA      = 'area';
const SPECIALTY = 'specialty';

function register(): void {
	register_practice();
	register_area();
	register_specialty();
}

/**
 * What the practice is: meditation, breathwork, yoga, mindfulness, sound,
 * retreats. Hierarchical so it behaves like categories in the admin — a
 * checkbox list rather than a free-text tag box, which stops the taxonomy
 * filling up with near-duplicate terms.
 */
function register_practice(): void {
	register_taxonomy(
		PRACTICE,
		array( 'listing', 'event' ),
		array(
			'labels'            => array(
				'name'          => __( 'Practices', 'oria' ),
				'singular_name' => __( 'Practice', 'oria' ),
				'menu_name'     => __( 'Practices', 'oria' ),
				'all_items'     => __( 'All practices', 'oria' ),
				'edit_item'     => __( 'Edit practice', 'oria' ),
				'add_new_item'  => __( 'Add practice', 'oria' ),
				'search_items'  => __( 'Search practices', 'oria' ),
				'not_found'     => __( 'No practices found', 'oria' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => 'practice',
				'with_front' => false,
			),
		)
	);
}

/**
 * Where it is. One hierarchical tree rather than separate region and suburb
 * taxonomies: regions are the parent terms, suburbs are their children.
 *
 * That means assigning a listing to "Fremantle" automatically implies
 * "Fremantle & South" through term ancestry — no second field to keep in sync,
 * and no way for the two to contradict each other.
 */
function register_area(): void {
	register_taxonomy(
		AREA,
		array( 'listing', 'event' ),
		array(
			'labels'            => array(
				'name'          => __( 'Areas', 'oria' ),
				'singular_name' => __( 'Area', 'oria' ),
				'menu_name'     => __( 'Areas', 'oria' ),
				'all_items'     => __( 'All areas', 'oria' ),
				'edit_item'     => __( 'Edit area', 'oria' ),
				'add_new_item'  => __( 'Add area', 'oria' ),
				'parent_item'   => __( 'Region', 'oria' ),
				'search_items'  => __( 'Search areas', 'oria' ),
				'not_found'     => __( 'No areas found', 'oria' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'         => 'area',
				'with_front'   => false,
				'hierarchical' => true,
			),
		)
	);
}

/**
 * The exact modality on offer: acupuncture, remedial massage, homeopathy.
 * A third browse dimension — practices are the dozen broad doors, specialties
 * are the precise thing someone searched for. Hierarchical only for the
 * checkbox UI; the tree is kept flat.
 *
 * URLs are /{city}/{slug}/ — "/perth/acupuncture/". They used to come from
 * registering the taxonomy with rewrite slug 'perth', which baked the city
 * into the permalink structure as a constant: a second city would have meant
 * Sydney clinics living under /perth/, or a duplicate taxonomy.
 *
 * The rewrite is off here and handled in Cities\route() instead, which
 * matches any registered city slug and sets oria_city alongside the term.
 * Perth is the first city, so every existing URL is byte-identical — a
 * structural fix that costs no redirects.
 */
function register_specialty(): void {
	register_taxonomy(
		SPECIALTY,
		array( 'listing' ),
		array(
			'labels'            => array(
				'name'          => __( 'Specialties', 'oria' ),
				'singular_name' => __( 'Specialty', 'oria' ),
				'menu_name'     => __( 'Specialties', 'oria' ),
				'all_items'     => __( 'All specialties', 'oria' ),
				'edit_item'     => __( 'Edit specialty', 'oria' ),
				'add_new_item'  => __( 'Add specialty', 'oria' ),
				'search_items'  => __( 'Search specialties', 'oria' ),
				'not_found'     => __( 'No specialties found', 'oria' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			// See Cities\route(). A rewrite here would re-introduce the very
			// constant this change exists to remove.
			'rewrite'           => false,
		)
	);
}

/**
 * The region a suburb term belongs to, or the term itself if it is already a
 * top-level region.
 */
function region_for( \WP_Term $term ): \WP_Term {
	if ( 0 === $term->parent ) {
		return $term;
	}

	$ancestors = get_ancestors( $term->term_id, AREA, 'taxonomy' );
	$top       = end( $ancestors );
	$region    = $top ? get_term( $top, AREA ) : null;

	return $region instanceof \WP_Term ? $region : $term;
}

/** Suburb terms only — the children of any region. */
function suburbs(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => AREA,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	return array_values(
		array_filter(
			$terms,
			static fn( \WP_Term $t ): bool => 0 !== $t->parent
		)
	);
}
