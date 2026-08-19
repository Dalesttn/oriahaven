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

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_categories_sync', __NAMESPACE__ . '\handle_sync' );

	foreach ( array( 'save_post_listing', 'deleted_post', 'set_object_terms', 'edited_term', 'created_term', 'delete_term' ) as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\flush' );
	}
}

function flush(): void {
	delete_transient( CACHE_KEY );
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
 * @return array{renamed: int, created: int, reparented: int, unchanged: int, notes: array<int, string>}
 */
function sync(): array {
	$out = array( 'renamed' => 0, 'created' => 0, 'reparented' => 0, 'unchanged' => 0, 'notes' => array() );

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
		$css .= sprintf(
			'.pill--cat-%1$s{background:%2$s;border-color:transparent;color:%3$s;font-weight:600}',
			preg_replace( '/[^a-z0-9_-]/', '', $slug ),
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
