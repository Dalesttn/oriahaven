<?php
/**
 * The directory's top-level categories, and the drill-down beneath them.
 *
 * The practice taxonomy grew a term at a time and reads like it: seventeen
 * flat entries, "Sound & float" beside "Allied Health", nothing telling a
 * visitor which are big and which are corners. This gives it a top level
 * worth showing — thirteen categories, each of which opens onto what
 * narrows it.
 *
 * Three things make that cheap.
 *
 * Slugs never change. /practice/{slug}/ is live and being crawled, so a
 * category is an existing term wearing a better name wherever one exists;
 * only genuinely new groupings mint a slug.
 *
 * Grouping is free. The practice rewrite is flat — 'rewrite' sets a slug
 * and with_front, and nothing else — so a child keeps /practice/yoga/ even
 * after Yoga gains a parent. Adding parents changes no URL and moves no
 * listing; parents inherit through term ancestry.
 *
 * And a category can be defined without being shown. Beauty and Longevity
 * are written down, seeded, and hidden until they have listings of their
 * own. An empty shelf in primary navigation is worse than a thin landing
 * page: every visitor sees it, and it teaches them the categories are
 * decorative.
 */

declare(strict_types=1);

namespace Oria\Core\Categories;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DATA_FILE = 'data/categories.json';
const META_EMOJI = 'oria_emoji';
const META_TOP   = 'oria_top_level';
const CACHE_KEY  = 'oria_category_counts_v1';
const SPEC_CACHE = 'oria_category_specs_v1';

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_categories_sync', __NAMESPACE__ . '\handle_sync' );

	foreach ( array( 'save_post_listing', 'deleted_post', 'set_object_terms', 'edited_term', 'created_term', 'delete_term' ) as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\flush' );
	}
}

function flush(): void {
	delete_transient( CACHE_KEY );
	delete_transient( SPEC_CACHE );
}

/**
 * The intended shape, as written down.
 *
 * @return array{min: int, categories: array<int, array<string, mixed>>}
 */
function plan(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$path = ORIA_CORE_DIR . DATA_FILE;
	if ( ! is_readable( $path ) ) {
		return $cache = array( 'min' => 3, 'categories' => array() );
	}
	$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_array( $json ) ) {
		return $cache = array( 'min' => 3, 'categories' => array() );
	}

	return $cache = array(
		'min'        => max( 0, (int) ( $json['min_listings'] ?? 3 ) ),
		'categories' => array_values( (array) ( $json['categories'] ?? array() ) ),
	);
}

function minimum(): int {
	return (int) apply_filters( 'oria_category_min_listings', plan()['min'] );
}

/**
 * Published listings per practice term, children included.
 *
 * Counts listings only — practice is attached to events too, so the stored
 * term count answers a different question from the one the sidebar asks.
 * One aggregate query for the whole taxonomy, rolled up through ancestry.
 *
 * @return array<int, int>
 */
function counts(): array {
	$cached = get_transient( CACHE_KEY );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;

	$terms = get_terms( array( 'taxonomy' => Taxonomies\PRACTICE, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tt.term_id AS term_id, COUNT( p.ID ) AS n
			   FROM {$wpdb->term_taxonomy} tt
			   JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
			   JOIN {$wpdb->posts} p ON p.ID = tr.object_id
			  WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status = %s
			  GROUP BY tt.term_id",
			Taxonomies\PRACTICE,
			'listing',
			'publish'
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	$parent = array();
	foreach ( $terms as $term ) {
		$parent[ (int) $term->term_id ] = (int) $term->parent;
	}

	$totals = array_fill_keys( array_keys( $parent ), 0 );
	foreach ( (array) $rows as $row ) {
		$current = (int) $row['term_id'];
		$hops    = 0;
		while ( isset( $totals[ $current ] ) && $hops < 20 ) {
			$totals[ $current ] += (int) $row['n'];
			$current             = (int) ( $parent[ $current ] ?? 0 );
			$hops++;
		}
	}

	/*
	 * A listing in two children of one parent must not be counted twice in
	 * the parent, so any term with children is re-counted as a real union.
	 * The rollup above is right for leaves and generous for parents.
	 */
	foreach ( $totals as $term_id => $n ) {
		$kids = get_term_children( (int) $term_id, Taxonomies\PRACTICE );
		if ( is_wp_error( $kids ) || ! $kids ) {
			continue;
		}
		$q = new \WP_Query(
			array(
				'post_type'      => 'listing',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => false,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array( 'taxonomy' => Taxonomies\PRACTICE, 'field' => 'term_id', 'terms' => array_merge( array( (int) $term_id ), array_map( 'intval', $kids ) ) ),
				),
			)
		);
		$totals[ $term_id ] = (int) $q->found_posts;
	}

	set_transient( CACHE_KEY, $totals, 12 * HOUR_IN_SECONDS );
	return $totals;
}

function depth( int $term_id ): int {
	$all = counts();
	return (int) ( $all[ $term_id ] ?? 0 );
}

/* ------------------------------------------------------------- installing */

/**
 * Bring the taxonomy into line with the plan, without moving a listing.
 *
 * Renames where a slug already exists, creates where it does not, and
 * reparents the children named in the plan. Never deletes: a term absent
 * from the file may still be attached to listings, which is a decision for
 * a person rather than a sync.
 *
 * @return array{renamed: int, created: int, reparented: int, unchanged: int, intros: int, notes: array<int, string>}
 */
function sync(): array {
	$out = array( 'renamed' => 0, 'created' => 0, 'reparented' => 0, 'unchanged' => 0, 'intros' => 0, 'notes' => array() );

	foreach ( plan()['categories'] as $row ) {
		$slug = (string) ( $row['slug'] ?? '' );
		$name = (string) ( $row['name'] ?? '' );
		if ( '' === $slug || '' === $name ) {
			continue;
		}

		$term = get_term_by( 'slug', $slug, Taxonomies\PRACTICE );

		if ( ! $term instanceof \WP_Term ) {
			$made = wp_insert_term( $name, Taxonomies\PRACTICE, array( 'slug' => $slug ) );
			if ( is_wp_error( $made ) ) {
				$out['notes'][] = sprintf( '%s: %s', $slug, $made->get_error_message() );
				continue;
			}
			$term_id = (int) $made['term_id'];
			$out['created']++;
		} else {
			$term_id = (int) $term->term_id;
			// Decoded, because WordPress stores "Yoga & Pilates" with an
			// encoded ampersand and a raw comparison would rename forever.
			if ( wp_specialchars_decode( $term->name, ENT_QUOTES ) !== $name ) {
				wp_update_term( $term_id, Taxonomies\PRACTICE, array( 'name' => $name ) );
				$out['renamed']++;
			} else {
				$out['unchanged']++;
			}
		}

		update_term_meta( $term_id, META_EMOJI, (string) ( $row['emoji'] ?? '' ) );
		update_term_meta( $term_id, META_TOP, 1 );
		update_term_meta( $term_id, 'oria_reserved', ! empty( $row['reserved'] ) ? 1 : 0 );

		/*
		 * Landing copy, but never over the top of copy that already exists.
		 * Ten categories were written before this file did, in wp-admin,
		 * and a sync that flattened somebody's editing to ship a default
		 * would be the last time anyone trusted the button. Seeds the
		 * empty ones — which is the new categories — and leaves the rest.
		 */
		$intro = intro_for( $slug );
		if ( '' !== $intro && '' === trim( (string) get_term_meta( $term_id, 'landing_intro', true ) ) ) {
			update_term_meta( $term_id, 'landing_intro', $intro );
			$out['intros']++;
		}

		foreach ( (array) ( $row['children'] ?? array() ) as $child_slug ) {
			$child = get_term_by( 'slug', (string) $child_slug, Taxonomies\PRACTICE );
			if ( ! $child instanceof \WP_Term ) {
				$out['notes'][] = sprintf( 'child not found: %s', $child_slug );
				continue;
			}
			if ( (int) $child->parent !== $term_id ) {
				wp_update_term( (int) $child->term_id, Taxonomies\PRACTICE, array( 'parent' => $term_id ) );
				$out['reparented']++;
			}
			update_term_meta( (int) $child->term_id, META_TOP, 0 );
		}
	}

	flush();
	return $out;
}

/* ---------------------------------------------------------------- reading */

/**
 * The categories to show, deepest first, with what sits beneath each.
 *
 * @return array<int, array{term: \WP_Term, emoji: string, count: int, children: array<int, \WP_Term>}>
 */
function navigation(): array {
	$min = minimum();
	$out = array();

	foreach ( plan()['categories'] as $row ) {
		$term = get_term_by( 'slug', (string) ( $row['slug'] ?? '' ), Taxonomies\PRACTICE );
		if ( ! $term instanceof \WP_Term ) {
			continue;
		}
		$n = depth( (int) $term->term_id );
		if ( $n < $min ) {
			continue; // reserved, or simply not there yet.
		}

		$kids = get_terms(
			array(
				'taxonomy'   => Taxonomies\PRACTICE,
				'hide_empty' => false,
				'parent'     => (int) $term->term_id,
			)
		);

		$out[] = array(
			'term'     => $term,
			'emoji'    => (string) get_term_meta( (int) $term->term_id, META_EMOJI, true ),
			'count'    => $n,
			'children' => is_wp_error( $kids ) ? array() : $kids,
		);
	}

	usort( $out, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] );
	return $out;
}

/**
 * The specialties actually present in a category, commonest first.
 *
 * Counted from the listings rather than taken from the taxonomy, because
 * the question a visitor is asking on a category page is "what is in
 * here", not "what exists somewhere". A specialty nobody in this category
 * offers would be a filter that empties the page.
 *
 * @return array<int, array{term: \WP_Term, count: int}>
 */
function specialties_for( \WP_Term $category, int $limit = 14 ): array {
	/*
	 * One cache for the whole taxonomy rather than one per category, so
	 * the existing flush() clears it along with everything else. Per-term
	 * transients would survive a flush and quietly go stale.
	 */
	$all = get_transient( SPEC_CACHE );
	$all = is_array( $all ) ? $all : array();
	$tid = (int) $category->term_id;

	if ( isset( $all[ $tid ] ) ) {
		return hydrate( (array) $all[ $tid ], $limit );
	}

	// A parent answers for its children too, the same way its count does.
	$terms = array( (int) $category->term_id );
	foreach ( (array) get_term_children( (int) $category->term_id, \Oria\Core\Taxonomies\PRACTICE ) as $child ) {
		$terms[] = (int) $child;
	}

	$ids = get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array( 'taxonomy' => \Oria\Core\Taxonomies\PRACTICE, 'field' => 'term_id', 'terms' => $terms ),
			),
		)
	);

	$tally = array();
	foreach ( $ids as $id ) {
		foreach ( (array) wp_get_post_terms( (int) $id, \Oria\Core\Taxonomies\SPECIALTY ) as $spec ) {
			$tally[ (int) $spec->term_id ] = ( $tally[ (int) $spec->term_id ] ?? 0 ) + 1;
		}
	}
	arsort( $tally );

	$all[ $tid ] = $tally;
	set_transient( SPEC_CACHE, $all, 12 * HOUR_IN_SECONDS );

	return hydrate( $tally, $limit );
}

/**
 * Turn a term_id => count tally into terms, dropping any since deleted.
 *
 * @param array<int, int> $tally
 * @return array<int, array{term: \WP_Term, count: int}>
 */
function hydrate( array $tally, int $limit ): array {
	$out = array();
	foreach ( $tally as $id => $n ) {
		$term = get_term( (int) $id, \Oria\Core\Taxonomies\SPECIALTY );
		if ( $term instanceof \WP_Term ) {
			$out[] = array( 'term' => $term, 'count' => (int) $n );
		}
	}
	return array_slice( $out, 0, $limit );
}

/**
 * The services that narrow a category, for the second level.
 *
 * Reads the many-to-many membership the service vocabulary already
 * records, and shows only services a visitor could actually land on.
 *
 * @return array<int, \WP_Term>
 */
function services_for( \WP_Term $category ): array {
	if ( ! function_exists( '\Oria\Core\Services\vocabulary' ) ) {
		return array();
	}

	// A child category answers for itself; a parent answers for its children too.
	$slugs = array( $category->slug );
	foreach ( (array) get_term_children( (int) $category->term_id, Taxonomies\PRACTICE ) as $child_id ) {
		$child = get_term( (int) $child_id );
		if ( $child instanceof \WP_Term ) {
			$slugs[] = $child->slug;
		}
	}

	$out = array();
	foreach ( \Oria\Core\Services\vocabulary() as $service ) {
		if ( ! array_intersect( $slugs, $service['categories'] ) ) {
			continue;
		}
		$term = get_term_by( 'slug', $service['slug'], \Oria\Core\Services\TAXONOMY );
		if ( $term instanceof \WP_Term && $term->count > 0 ) {
			$out[] = $term;
		}
	}

	usort( $out, static fn( \WP_Term $a, \WP_Term $b ): int => $b->count <=> $a->count );
	return $out;
}

/**
 * Landing copy for a category, from data/category-intros.json.
 *
 * A separate file from the categories themselves: this one is prose and
 * runs to a couple of thousand characters a category, and mixing it into
 * the config would make the config unreadable for the sake of one import.
 */
function intro_for( string $slug ): string {
	static $intros = null;
	if ( null === $intros ) {
		$intros = array();
		$path   = ORIA_CORE_DIR . 'data/category-intros.json';
		if ( is_readable( $path ) ) {
			$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( is_array( $json ) ) {
				$intros = (array) ( $json['intros'] ?? array() );
			}
		}
	}
	return trim( (string) ( $intros[ $slug ] ?? '' ) );
}

/**
 * The one evocative line under a category page's H1, or '' for none.
 *
 * Same file as the intros so the copy lives in one place. Room-language
 * only — what you do there, never what it does for you — because this
 * line sits above structured data and the TGA rule applies to it as much
 * as to anything else on the page.
 */
function tagline_for( string $slug ): string {
	static $lines = null;
	if ( null === $lines ) {
		$lines = array();
		$path  = ORIA_CORE_DIR . 'data/category-intros.json';
		if ( is_readable( $path ) ) {
			$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( is_array( $json ) ) {
				$lines = (array) ( $json['taglines'] ?? array() );
			}
		}
	}
	return trim( (string) ( $lines[ $slug ] ?? '' ) );
}

/**
 * The six homepage families over the 23 categories, from data/families.json.
 *
 * Presentation only: every category keeps its own term, URL and count, and
 * a family is just a heading a visitor can scan. Any category absent from
 * the file is reported by the caller rather than silently dropped — a new
 * category must be filed, not lost.
 *
 * @return array<int, array{slug: string, name: string, line: string, cats: array<int, string>}>
 */
function families(): array {
	static $fams = null;
	if ( null !== $fams ) {
		return $fams;
	}
	$fams = array();
	$path = ORIA_CORE_DIR . 'data/families.json';
	if ( is_readable( $path ) ) {
		$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( is_array( $json ) && is_array( $json['families'] ?? null ) ) {
			foreach ( $json['families'] as $row ) {
				if ( empty( $row['slug'] ) || empty( $row['name'] ) || empty( $row['cats'] ) ) {
					continue;
				}
				$fams[] = array(
					'slug' => (string) $row['slug'],
					'name' => (string) $row['name'],
					'line' => (string) ( $row['line'] ?? '' ),
					'want' => (string) ( $row['want'] ?? '' ),
					'cats' => array_values( array_map( 'strval', (array) $row['cats'] ) ),
				);
			}
		}
	}
	return $fams;
}

/* ------------------------------------------------------- cards and colour */

/**
 * The top-level categories a listing belongs to, in plan order.
 *
 * The card used to print whichever practice term came back first, which
 * was fine while the taxonomy was flat and is not now: a meditation studio
 * would have shown "Meditation classes" where the sidebar, the filters and
 * the URL all say "Mind & Mental Wellbeing". This walks each term up to its
 * top level and de-duplicates, so a practice in two children of one parent
 * shows that parent once rather than twice.
 *
 * @return array<int, array{term: \WP_Term, emoji: string}>
 */
function top_for( int $listing_id, int $limit = 2 ): array {
	$terms = wp_get_post_terms( $listing_id, Taxonomies\PRACTICE );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	$tops = array();
	foreach ( $terms as $term ) {
		$current = $term;
		$hops    = 0;
		while ( $current instanceof \WP_Term && $current->parent && $hops < 20 ) {
			$parent  = get_term( (int) $current->parent, Taxonomies\PRACTICE );
			$current = $parent instanceof \WP_Term ? $parent : $current;
			$hops++;
		}
		if ( $current instanceof \WP_Term ) {
			$tops[ $current->slug ] = $current;
		}
	}

	// Plan order, so cards agree with the sidebar rather than with whatever
	// order the database happened to return.
	$out = array();
	foreach ( plan()['categories'] as $row ) {
		$slug = (string) ( $row['slug'] ?? '' );
		if ( isset( $tops[ $slug ] ) ) {
			$out[] = array(
				'term'  => $tops[ $slug ],
				'emoji' => (string) ( $row['emoji'] ?? '' ),
			);
		}
	}

	return array_slice( $out, 0, max( 1, $limit ) );
}


/**
 * The one category a listing is most about.
 *
 * wp_get_post_terms() orders by name, so taking its first element picks a
 * category by alphabet — Fremantle Yoga Centre came out as "Sound & float"
 * because S beats Y. top_for() already ranks properly, by the plan in
 * categories.json, so this is only a name for asking it for one.
 */
function primary_for( int $listing_id ): ?\WP_Term {
	$top = top_for( $listing_id, 1 );
	return $top ? $top[0]['term'] : null;
}

/**
 * The icon for a category, as inline SVG.
 *
 * Drawn artwork first, from assets/icons/{slug}.svg — flat two-tone in the
 * brand's green and gold. These carry their own colour, so unlike the line
 * glyphs below they do not take the category tint; the rail sits them on a
 * neutral dot and leaves the tint to the hover wash.
 *
 * Inlined rather than linked: fifteen files is fifteen requests, each one
 * about 2KB, and inline means no flash of nothing while they load. Read
 * once per request and held, because the sidebar asks for all of them.
 *
 * The single-colour glyphs remain as the fallback. A category added
 * tomorrow has no artwork yet and should still draw something rather than
 * leave a hole.
 */
function icon( string $slug ): string {
	static $art = array();

	$safe = preg_replace( '/[^a-z0-9_-]/', '', $slug );
	if ( ! array_key_exists( $safe, $art ) ) {
		$file        = ORIA_CORE_DIR . 'assets/icons/' . $safe . '.svg';
		$art[ $safe ] = is_readable( $file )
			? trim( (string) file_get_contents( $file ) ) // phpcs:ignore WordPress.WP.AlternativeFunctions
			: '';
	}
	if ( '' !== $art[ $safe ] ) {
		return $art[ $safe ];
	}

	return glyph( $safe );
}

/**
 * The fallback: one line glyph per category, single-colour.
 *
 * Inherits the surrounding colour through currentColor, which the drawn
 * artwork cannot. Kept for any category without artwork of its own.
 */
function glyph( string $slug ): string {
	static $paths = array(
		'yoga'        => '<circle cx="12" cy="5.5" r="2.4"/><path d="M12 9v5m0 0-4.5 4.5M12 14l4.5 4.5M6 12h12"/>',
		'fitness'     => '<path d="M4 9v6M7 7v10M17 7v10M20 9v6M7 12h10"/>',
		'bodywork'    => '<path d="M8 13V6.5a1.5 1.5 0 0 1 3 0V12m0-1.5a1.5 1.5 0 0 1 3 0V12m0-1a1.5 1.5 0 0 1 3 0v4a5 5 0 0 1-5 5h-1a5 5 0 0 1-5-5v-1a1.6 1.6 0 0 1 2.7-1.2L8 13"/>',
		'mind'        => '<path d="M15.5 20v-2.2a5.5 5.5 0 0 0 3-4.9c0-3.4-2.9-6.1-6.4-5.8A6 6 0 0 0 7 16.3V20"/><path d="M12 13.5a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Z"/>',
		'natural'     => '<path d="M5 19c0-7 4.5-12 14-12 0 8-4.5 12-11 12H5Z"/><path d="M8 17c2-3.5 4.5-6 8-7.5"/>',
		'nutrition'   => '<path d="M3.5 11h17a8.5 8.5 0 0 1-17 0Z"/><path d="M9 7.5c0-1.5 1.2-2 1.2-3.5M13.5 7.5c0-1.5 1.2-2 1.2-3.5"/>',
		'spa'         => '<path d="M12 3.5c3 3.6 4.5 6.2 4.5 8.4a4.5 4.5 0 1 1-9 0c0-2.2 1.5-4.8 4.5-8.4Z"/><path d="M4 20c1.6-1.2 2.9-1.2 4.5 0s2.9 1.2 4.5 0 2.9-1.2 4.5 0"/>',
		'family'      => '<path d="M12 20s-7-4.4-7-9a3.9 3.9 0 0 1 7-2.4A3.9 3.9 0 0 1 19 11c0 4.6-7 9-7 9Z"/>',
		'energy'      => '<path d="M12 3v3.5M12 17.5V21M3 12h3.5M17.5 12H21M5.6 5.6l2.5 2.5M15.9 15.9l2.5 2.5M18.4 5.6l-2.5 2.5M8.1 15.9l-2.5 2.5"/><circle cx="12" cy="12" r="2.6"/>',
		'experiences' => '<path d="M3 18l5.5-8 4 5.5 2.5-3.5L21 18H3Z"/><circle cx="7.5" cy="6.5" r="2"/>',
		'allied'      => '<path d="M6 3v5a4.5 4.5 0 0 0 9 0V3"/><path d="M6 3H4.5M15 3h1.5M10.5 12.5v2a4.5 4.5 0 0 0 9 0v-1"/><circle cx="19.5" cy="11.5" r="2"/>',
		'creative'    => '<path d="M12 3.5a8.5 8.5 0 1 0 0 17c1.4 0 2-.9 2-1.8 0-1.5-1.4-1.7-1.4-3 0-.9.8-1.7 1.8-1.7h1.8a4.3 4.3 0 0 0 4.3-4.3c0-3.4-3.8-6.2-8.5-6.2Z"/><circle cx="8" cy="9" r="1.1"/><circle cx="12.5" cy="7" r="1.1"/><circle cx="16.5" cy="9.5" r="1.1"/>',
		'community'   => '<circle cx="9" cy="8.5" r="2.6"/><circle cx="16.5" cy="10" r="2.1"/><path d="M4 19a5 5 0 0 1 10 0M14.5 19a4 4 0 0 1 5.5-3.7"/>',
		'beauty'      => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/><path d="M18 16.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7.7-1.8Z"/>',
		'longevity'   => '<path d="M12 20.5s-3-6.5-3-10a3 3 0 0 1 6 0c0 3.5-3 10-3 10Z"/><path d="M9 13.5 5.5 15l1 4M15 13.5 18.5 15l-1 4"/><circle cx="12" cy="8" r="1.2"/>',
		'breathwork'  => '<path d="M3 8h9.5a2.4 2.4 0 1 0-2.4-2.4M3 12h13.5a2.4 2.4 0 1 1-2.4 2.4M3 16h7a2 2 0 1 1-2 2"/>',
		'meditation'  => '<circle cx="12" cy="5.8" r="2.2"/><path d="M9.2 12.3c.8-1.7 1.7-2.5 2.8-2.5s2 .8 2.8 2.5M6.5 17.5c1-2.3 3-3.6 5.5-3.6s4.5 1.3 5.5 3.6M4.5 17.5h15"/>',
		'mindfulness' => '<path d="M3.5 12s3.4-5 8.5-5 8.5 5 8.5 5-3.4 5-8.5 5-8.5-5-8.5-5Z"/><circle cx="12" cy="12" r="2.2"/>',
		'nature'      => '<path d="M12 3.5l5 6.5h-3l4 5h-4.5l3 4.5H7.5l3-4.5H6l4-5H7l5-6.5Z"/><path d="M12 19.5V21"/>',
		'retreats'    => '<path d="M4 11l8-6.5L20 11"/><path d="M6.5 9.5V19h11V9.5"/><path d="M10 19v-4.5h4V19"/>',
		'recovery'    => '<path d="M3 18V8M3 14.5h18V18M3 12.5h7.5a2 2 0 0 1 2 2"/><circle cx="6.5" cy="10.5" r="1.5"/><path d="M16 5h3l-3 3h3"/>',
		'sound'       => '<circle cx="7" cy="12" r="2.2"/><path d="M12.5 8a5.5 5.5 0 0 1 0 8M15.8 5.2a10 10 0 0 1 0 13.6"/>',
	);

	$d = $paths[ $slug ] ?? '<circle cx="12" cy="12" r="7"/>';

	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" '
		. 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

/** Map of category slug => tint, for the front end and the card script. */
function tints(): array {
	$out = array();
	foreach ( plan()['categories'] as $row ) {
		$slug = (string) ( $row['slug'] ?? '' );
		$tint = (array) ( $row['tint'] ?? array() );
		if ( '' !== $slug && ! empty( $tint['bg'] ) ) {
			$out[ $slug ] = array(
				'bg' => (string) $tint['bg'],
				'fg' => (string) ( $tint['fg'] ?? '#26332F' ),
			);
		}
	}
	return $out;
}

/**
 * One rule per category, printed once.
 *
 * Generated from the same file the categories come from, so a new category
 * cannot arrive with no colour and fall back to looking like a mistake.
 */
function tint_css(): string {
	$css = '';
	foreach ( tints() as $slug => $tint ) {
		$safe = preg_replace( '/[^a-z0-9_-]/', '', $slug );

		// The filled chip on a listing card.
		$css .= sprintf(
			'.pill--cat-%1$s{background:%2$s;border-color:transparent;color:%3$s;font-weight:600}',
			$safe,
			$tint['bg'],
			$tint['fg']
		);

		/*
		 * And the pair as custom properties, for anything that wants the
		 * colour without the filled background — the sidebar rail paints
		 * nothing at rest and washes in the tint on hover.
		 */
		$css .= sprintf(
			'.cat-%1$s{--cat-bg:%2$s;--cat-fg:%3$s}',
			$safe,
			$tint['bg'],
			$tint['fg']
		);
	}
	return $css;
}

/* ------------------------------------------------------------------ admin */

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=listing',
		__( 'Directory categories', 'oria' ),
		__( 'Directory categories', 'oria' ),
		'manage_options',
		'oria-categories',
		__NAMESPACE__ . '\render'
	);
}

function handle_sync(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot do that.', 'oria' ) );
	}
	check_admin_referer( 'oria_categories_sync' );

	set_transient( 'oria_categories_report', sync(), HOUR_IN_SECONDS );
	wp_safe_redirect( admin_url( 'edit.php?post_type=listing&page=oria-categories&synced=1' ) );
	exit;
}

/** The sidebar as it will appear, before anybody has to look at the site. */
function render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'Directory categories', 'oria' ) . '</h1>';

	printf(
		'<p class="description" style="max-width:74ch">%s</p>',
		esc_html(
			sprintf(
				/* translators: %d: minimum listings */
				__( 'The top level of the directory, read from data/categories.json. A category needs %d listings to appear — the rest are defined and waiting, because an empty category in the main navigation is seen by every visitor and teaches them the categories are decorative. Syncing renames and regroups terms; it never moves a listing or changes a URL.', 'oria' ),
				minimum()
			)
		)
	);

	printf(
		'<form method="post" action="%s">%s<input type="hidden" name="action" value="oria_categories_sync">'
		. '<p><button class="button button-primary">%s</button></p></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'oria_categories_sync', '_wpnonce', true, false ),
		esc_html__( 'Sync categories', 'oria' )
	);

	$report = get_transient( 'oria_categories_report' );
	if ( is_array( $report ) ) {
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: created, 2: renamed, 3: reparented, 4: unchanged */
					__( '%1$d created, %2$d renamed, %3$d regrouped, %4$d already correct.', 'oria' ),
					$report['created'],
					$report['renamed'],
					$report['reparented'],
					$report['unchanged']
				)
			)
		);
		foreach ( (array) ( $report['notes'] ?? array() ) as $note ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $note ) );
		}
	}

	echo '<h2>' . esc_html__( 'Showing', 'oria' ) . '</h2>';
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th style="width:20em">' . esc_html__( 'Category', 'oria' ) . '</th>';
	echo '<th style="width:7em">' . esc_html__( 'Listings', 'oria' ) . '</th>';
	echo '<th>' . esc_html__( 'Opens onto', 'oria' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( navigation() as $cat ) {
		$sub = array();
		foreach ( $cat['children'] as $child ) {
			$sub[] = wp_specialchars_decode( $child->name, ENT_QUOTES );
		}
		$chips = array();
		foreach ( array_slice( services_for( $cat['term'] ), 0, 8 ) as $service ) {
			$chips[] = sprintf( '%s (%d)', wp_specialchars_decode( $service->name, ENT_QUOTES ), $service->count );
		}

		printf(
			'<tr><td><strong>%s %s</strong>%s</td><td>%d</td><td class="description">%s</td></tr>',
			esc_html( $cat['emoji'] ),
			esc_html( wp_specialchars_decode( $cat['term']->name, ENT_QUOTES ) ),
			$sub ? '<br><span class="description">' . esc_html( implode( ' · ', $sub ) ) . '</span>' : '',
			(int) $cat['count'],
			esc_html( $chips ? implode( ', ', $chips ) : '—' )
		);
	}
	echo '</tbody></table>';

	$hidden = array();
	$shown  = array_map( static fn( array $c ): string => $c['term']->slug, navigation() );
	foreach ( plan()['categories'] as $row ) {
		$slug = (string) ( $row['slug'] ?? '' );
		if ( in_array( $slug, $shown, true ) ) {
			continue;
		}
		$term     = get_term_by( 'slug', $slug, Taxonomies\PRACTICE );
		$hidden[] = array(
			'name'  => (string) ( $row['name'] ?? $slug ),
			'count' => $term instanceof \WP_Term ? depth( (int) $term->term_id ) : 0,
			'note'  => (string) ( $row['note'] ?? '' ),
		);
	}

	if ( $hidden ) {
		echo '<h2>' . esc_html__( 'Defined but not showing', 'oria' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:80ch"><tbody>';
		foreach ( $hidden as $row ) {
			printf(
				'<tr><td style="width:20em"><strong>%s</strong></td><td style="width:7em">%s</td><td class="description">%s</td></tr>',
				esc_html( $row['name'] ),
				esc_html( sprintf( _n( '%d listing', '%d listings', $row['count'], 'oria' ), $row['count'] ) ),
				esc_html( $row['note'] )
			);
		}
		echo '</tbody></table>';
		printf( '<p class="description">%s</p>', esc_html__( 'Each appears on its own the moment it has the listings. Nothing to press.', 'oria' ) );
	}

	echo '</div>';
}
