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
 * Depth in the area tree, now that there is a city above the regions.
 *
 * Before the city migration the tree was two levels and "root" and "region"
 * were the same thing — which is why seven call sites asked for
 * `parent => 0` and region_for() took the topmost ancestor. Both answers
 * became wrong the moment a city was inserted: every region gained a parent,
 * so region pages started being treated as suburbs, and region_for() began
 * returning "Perth" for everything.
 *
 * These three functions are the only place that knows the shape of the tree.
 * Call them instead of counting parents.
 */

/** A city is a root term whose slug is a registered city. */
function is_city( \WP_Term $term ): bool {
	return 0 === (int) $term->parent
		&& function_exists( '\Oria\Core\Cities\exists' )
		&& \Oria\Core\Cities\exists( $term->slug );
}

/**
 * A region sits directly under a city — or at the root on a site whose tree
 * has not been migrated yet, which keeps this correct on both.
 */
function is_region( \WP_Term $term ): bool {
	if ( is_city( $term ) ) {
		return false;
	}

	if ( 0 === (int) $term->parent ) {
		return true;
	}

	$parent = get_term( (int) $term->parent, AREA );
	return $parent instanceof \WP_Term && is_city( $parent );
}

/** Anything below a region. */
function is_suburb( \WP_Term $term ): bool {
	return ! is_city( $term ) && ! is_region( $term );
}

/**
 * Every region, whatever depth the tree happens to be.
 *
 * @return list<\WP_Term>
 */
function regions( array $args = array() ): array {
	$query = array_merge(
		array( 'taxonomy' => AREA, 'hide_empty' => false ),
		$args
	);

	/*
	 * `parent` must be absent, not null. WP_Term_Query tests it with
	 * `'' !== $args['parent']`, so a null casts to 0 and quietly restricts
	 * the query to root terms — which after the city migration is the city
	 * alone, and is_region() then filters that out too. The function
	 * returned an empty array and every region list on the site went blank.
	 */
	unset( $query['parent'], $query['child_of'] );

	$terms = get_terms( $query );

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	return array_values( array_filter( $terms, __NAMESPACE__ . '\is_region' ) );
}

/**
 * The region a term belongs to, or the term itself if it is already one.
 *
 * Walks up to the first ancestor that is a region rather than to the topmost
 * ancestor, which is now the city.
 */
function region_for( \WP_Term $term ): \WP_Term {
	if ( is_region( $term ) || is_city( $term ) ) {
		return $term;
	}

	foreach ( (array) get_ancestors( $term->term_id, AREA, 'taxonomy' ) as $id ) {
		$anc = get_term( (int) $id, AREA );
		if ( $anc instanceof \WP_Term && is_region( $anc ) ) {
			return $anc;
		}
	}

	return $term;
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
