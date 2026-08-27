<?php
/**
 * Area pages earn their place in the index by having something on them.
 *
 * The area taxonomy generates a page per suburb whether or not a single
 * practice is listed there. Measured against production, 54 of 111 area
 * pages had no listings at all and 30 more had exactly one — a heading,
 * an empty results list, and fourteen thousand words of navigation and
 * search index identical to every other page on the site. Google found
 * all 111 through the sitemap and declined to crawl them, which is the
 * correct reading of that set.
 *
 * The cost is not confined to those pages. A sitemap where half the URLs
 * are empty teaches Google the sitemap is a poor guide to what deserves
 * attention, and crawl priority falls for everything in it — the likeliest
 * reason /about/, a real page, was also sitting uncrawled.
 *
 * So a suburb page is indexable once it has MIN_LISTINGS practices, and
 * noindexed until then. Nothing is stored and nothing is flagged: the
 * count is read live, so a page publishes itself the moment listings are
 * imported and needs no second visit from anybody.
 *
 * Deliberately not a redirect. A 301 is cached hard by browsers and taken
 * by Google as a permanent statement, so a suburb that filled next week
 * would keep bouncing visitors long afterwards. Removing a noindex has no
 * such stickiness — the page is eligible again on the next crawl.
 */

declare(strict_types=1);

namespace Oria\Core\AreaDepth;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Practices needed before an area page is worth indexing. */
const MIN_LISTINGS = 3;

const CACHE_KEY = 'oria_area_depth_v1';

function bootstrap(): void {
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\robots' );
	add_filter( 'wp_robots', __NAMESPACE__ . '\wp_robots_thin' );
	add_filter( 'wpseo_exclude_from_sitemap_by_term_ids', __NAMESPACE__ . '\exclude_from_sitemap' );

	// The counts are only as good as their last invalidation, and each of
	// these can change what a suburb page contains.
	$hooks = array( 'save_post_listing', 'deleted_post', 'set_object_terms', 'edited_term', 'created_term', 'delete_term' );
	foreach ( $hooks as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\flush' );
	}
}

function flush(): void {
	delete_transient( CACHE_KEY );
}

/** The threshold, filterable so it can be tuned without a deploy. */
function minimum(): int {
	return max( 0, (int) apply_filters( 'oria_area_min_listings', MIN_LISTINGS ) );
}

/**
 * Published listings per area term, descendants included.
 *
 * $term->count is unusable here: the area taxonomy is attached to events
 * as well as listings, so the stored count answers a different question
 * from the one the page asks. This counts listings only, in one query for
 * the whole taxonomy rather than one per term — the sitemap builder asks
 * about all 111 at once.
 *
 * @return array<int, int> term_id => count including children
 */
function counts(): array {
	$cached = get_transient( CACHE_KEY );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;

	$terms = get_terms(
		array(
			'taxonomy'   => Taxonomies\AREA,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- aggregate over the
	// whole taxonomy; the per-term alternative is 111 WP_Query objects.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tt.term_id AS term_id, COUNT( p.ID ) AS n
			   FROM {$wpdb->term_taxonomy} tt
			   JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
			   JOIN {$wpdb->posts} p ON p.ID = tr.object_id
			  WHERE tt.taxonomy = %s
			    AND p.post_type = %s
			    AND p.post_status = %s
			  GROUP BY tt.term_id",
			Taxonomies\AREA,
			'listing',
			'publish'
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	$direct = array();
	foreach ( (array) $rows as $row ) {
		$direct[ (int) $row['term_id'] ] = (int) $row['n'];
	}

	$parent = array();
	foreach ( $terms as $term ) {
		$parent[ (int) $term->term_id ] = (int) $term->parent;
	}

	/*
	 * Roll each term's own listings up through its ancestors so a region
	 * reports everything beneath it. Walking upwards from each term needs
	 * no recursion; the hop guard is there because a corrupted parent
	 * chain would otherwise loop forever.
	 */
	$totals = array_fill_keys( array_keys( $parent ), 0 );
	foreach ( $direct as $term_id => $n ) {
		$current = (int) $term_id;
		$hops    = 0;
		while ( isset( $totals[ $current ] ) && $hops < 20 ) {
			$totals[ $current ] += $n;
			$current             = (int) ( $parent[ $current ] ?? 0 );
			$hops++;
		}
	}

	set_transient( CACHE_KEY, $totals, 12 * HOUR_IN_SECONDS );
	return $totals;
}

/** Published listings in this area, descendants included. */
function depth( int $term_id ): int {
	$all = counts();
	return (int) ( $all[ $term_id ] ?? 0 );
}

/** Too few practices to be worth a page of its own. */
function is_thin( int $term_id ): bool {
	return depth( $term_id ) < minimum();
}

/**
 * The suburbs worth linking to sitewide: the busiest, and only ones that
 * are actually published.
 *
 * Never returns a thin suburb. A noindex page is one this plugin has
 * decided is not worth Google's attention, and putting it in the footer of
 * every page argues the opposite on every crawl.
 *
 * @return list<\WP_Term> Busiest first, name as the tiebreak.
 */
function popular( int $limit = 12 ): array {
	$terms = get_terms(
		array(
			'taxonomy'   => Taxonomies\AREA,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	$rows = array();
	foreach ( $terms as $term ) {
		// is_suburb() rather than a parent test: a region also has a parent,
		// and a list headed "Popular suburbs" that opens with Perth Central
		// is not a list of suburbs.
		if ( ! Taxonomies\is_suburb( $term ) ) {
			continue;
		}
		$n = depth( (int) $term->term_id );
		if ( $n < minimum() ) {
			continue; // noindexed; the footer must not argue with that
		}
		$rows[] = array( 'term' => $term, 'n' => $n );
	}

	usort(
		$rows,
		static function ( array $a, array $b ): int {
			return $b['n'] <=> $a['n']
				?: strcasecmp( $a['term']->name, $b['term']->name );
		}
	);

	$out = array();
	foreach ( array_slice( $rows, 0, max( 0, $limit ) ) as $row ) {
		$out[] = $row['term'];
	}
	return $out;
}

/** The area term being viewed, if this request is an area archive. */
function current_term(): ?\WP_Term {
	if ( ! is_tax( Taxonomies\AREA ) ) {
		return null;
	}
	$term = get_queried_object();
	return $term instanceof \WP_Term ? $term : null;
}

function viewing_thin(): bool {
	$term = current_term();
	return $term instanceof \WP_Term && is_thin( (int) $term->term_id );
}

/**
 * 'follow' rather than 'none': the practices on a thin page are real pages
 * that should still receive the link.
 */
function robots( $robots ) {
	return viewing_thin() ? 'noindex, follow' : $robots;
}

function wp_robots_thin( array $r ): array {
	if ( viewing_thin() ) {
		$r['noindex'] = true;
		$r['follow']  = true;
	}
	return $r;
}

/**
 * Keep the sitemap and the page telling the same story.
 *
 * Yoast builds its sitemap from stored data and never runs the runtime
 * robots filter above, so without this the site would noindex a page while
 * continuing to advertise it — the same mismatch seo.php guards against
 * for taxonomy titles.
 *
 * @param mixed $ids
 * @return array<int, int>
 */
function exclude_from_sitemap( $ids ): array {
	$ids = is_array( $ids ) ? $ids : array();
	$min = minimum();
	foreach ( counts() as $term_id => $n ) {
		if ( $n < $min ) {
			$ids[] = (int) $term_id;
		}
	}
	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/**
 * Suburbs near this one that do have practices, so a thin page is a way
 * onwards rather than a dead end for whoever arrives from a bookmark.
 *
 * @return array<int, \WP_Term>
 */
function siblings_with_listings( \WP_Term $term, int $limit = 6 ): array {
	if ( ! function_exists( '\Oria\Core\Taxonomies\region_for' ) ) {
		return array();
	}
	$region = Taxonomies\region_for( $term );
	if ( ! $region instanceof \WP_Term ) {
		return array();
	}

	$siblings = get_terms(
		array(
			'taxonomy'   => Taxonomies\AREA,
			'hide_empty' => false,
			'parent'     => (int) $region->term_id,
			'exclude'    => array( (int) $term->term_id ),
		)
	);
	if ( is_wp_error( $siblings ) || ! $siblings ) {
		return array();
	}

	$min = minimum();
	$out = array();
	foreach ( $siblings as $sibling ) {
		if ( depth( (int) $sibling->term_id ) >= $min ) {
			$out[] = $sibling;
		}
	}

	usort(
		$out,
		static fn( \WP_Term $a, \WP_Term $b ): int => depth( (int) $b->term_id ) <=> depth( (int) $a->term_id )
	);
	return array_slice( $out, 0, $limit );
}
