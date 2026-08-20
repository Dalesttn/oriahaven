<?php
/**
 * Inserting the city into the area tree.
 *
 * The tree is two levels — eight regions at the root, seventy-eight suburbs
 * beneath them — and the region slugs are `central`, `north`, `west`,
 * `southeast`. Sydney has all four. `/area/central/` cannot mean two things,
 * so a second city is impossible until a city level exists.
 *
 * The `area` taxonomy is registered with rewrite.hierarchical => true, which
 * means WordPress builds `/area/{city}/{region}/{suburb}/` from the term
 * tree on its own. No routing code is needed: reparent the eight roots under
 * a city term and every URL follows. That is why this is a migration rather
 * than a rewrite.
 *
 * WHAT IT COSTS. Eighty-six URLs move and need a 301 each. Those pages are
 * the least-indexed part of the site — the reason to do this now rather than
 * later is that the equity at risk is close to its historic minimum and
 * rises with every listing added.
 *
 * THE ORDER MATTERS. Every old URL is captured before a single term is
 * reparented, because get_term_link() answers differently the instant the
 * parent changes and the old path is then unrecoverable. This is the same
 * mistake the specialty merge had to avoid, and the same one that turned the
 * CBD rename into a split — a migration that forgets a redirect cannot be
 * repaired after the fact.
 *
 * IDEMPOTENT. Roots already under the city are skipped, so a half-finished
 * run can simply be run again.
 */

declare(strict_types=1);

namespace Oria\Core\MigrateCity;

use Oria\Core\Cities;
use Oria\Core\Redirects;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Work out every move without making one.
 *
 * @return array{ok: bool, error: string, city: array<string,mixed>, city_term_id: int,
 *               creating: bool, roots: list<\WP_Term>, moves: list<array{term: \WP_Term, from: string}>}
 */
function plan( string $city_slug ): array {
	$out = array(
		'ok' => false, 'error' => '', 'city' => array(), 'city_term_id' => 0,
		'creating' => false, 'roots' => array(), 'moves' => array(),
	);

	$city = Cities\get( $city_slug );
	if ( null === $city ) {
		$out['error'] = sprintf( 'No city "%s" in data/cities.json.', $city_slug );
		return $out;
	}
	$out['city'] = $city;

	$city_term = get_term_by( 'slug', $city_slug, Taxonomies\AREA );

	/*
	 * A term with the city's slug that is not at the root is the CBD trap:
	 * before the rename, "Perth" was a suburb of Perth Central. Reparenting
	 * regions under a suburb would produce /area/central/perth/central/ and
	 * a tree that cannot be undone by inspection.
	 */
	if ( $city_term instanceof \WP_Term && 0 !== $city_term->parent ) {
		$out['error'] = sprintf(
			'An area term already uses slug "%s" and it is not a root term (#%d, parent #%d). Rename or merge it first.',
			$city_slug,
			$city_term->term_id,
			$city_term->parent
		);
		return $out;
	}

	$out['creating']     = ! $city_term instanceof \WP_Term;
	$out['city_term_id'] = $city_term instanceof \WP_Term ? (int) $city_term->term_id : 0;

	$roots = get_terms(
		array(
			'taxonomy'   => Taxonomies\AREA,
			'parent'     => 0,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $roots ) ) {
		$out['error'] = $roots->get_error_message();
		return $out;
	}

	// Everything at the root except the city itself.
	$roots = array_values(
		array_filter(
			$roots,
			static fn( \WP_Term $t ): bool => $t->slug !== $city_slug
		)
	);
	$out['roots'] = $roots;

	/*
	 * Capture the current URL of every term that will move: the roots and
	 * all their descendants. Read now, while the tree still says what it has
	 * always said.
	 */
	$moves = array();
	foreach ( $roots as $root ) {
		$moves[] = array( 'term' => $root, 'from' => (string) get_term_link( $root ) );

		$kids = get_terms(
			array(
				'taxonomy'   => Taxonomies\AREA,
				'child_of'   => $root->term_id,
				'hide_empty' => false,
			)
		);
		foreach ( is_wp_error( $kids ) ? array() : $kids as $kid ) {
			$moves[] = array( 'term' => $kid, 'from' => (string) get_term_link( $kid ) );
		}
	}

	$out['moves'] = $moves;
	$out['ok']    = true;

	return $out;
}

/**
 * Do it.
 *
 * @return array{ok: bool, error: string, moved: int, redirects: int, city_term_id: int}
 */
function run( string $city_slug ): array {
	$p = plan( $city_slug );

	if ( ! $p['ok'] ) {
		return array( 'ok' => false, 'error' => $p['error'], 'moved' => 0, 'redirects' => 0, 'city_term_id' => 0 );
	}

	$city_id = $p['city_term_id'];

	if ( 0 === $city_id ) {
		$made = wp_insert_term(
			(string) ( $p['city']['name'] ?? $city_slug ),
			Taxonomies\AREA,
			array( 'slug' => $city_slug, 'parent' => 0 )
		);
		if ( is_wp_error( $made ) ) {
			return array( 'ok' => false, 'error' => $made->get_error_message(), 'moved' => 0, 'redirects' => 0, 'city_term_id' => 0 );
		}
		$city_id = (int) $made['term_id'];
	}

	foreach ( $p['roots'] as $root ) {
		wp_update_term( (int) $root->term_id, Taxonomies\AREA, array( 'parent' => $city_id ) );
	}

	// The whole tree's links are stale until the cache is cleared, and a
	// redirect written from a stale link points at the URL we just left.
	clean_taxonomy_cache( Taxonomies\AREA );
	delete_option( Taxonomies\AREA . '_children' );
	_get_term_hierarchy( Taxonomies\AREA );

	$redirects = 0;
	foreach ( $p['moves'] as $move ) {
		$fresh = get_term( (int) $move['term']->term_id, Taxonomies\AREA );
		if ( ! $fresh instanceof \WP_Term ) {
			continue;
		}
		$to = (string) get_term_link( $fresh );
		if ( '' !== $to && Redirects\add( $move['from'], $to ) ) {
			$redirects++;
		}
	}

	/*
	 * The city term now has an archive of its own at /area/perth/, which is
	 * a second page about Perth beside the /perth/ hub — the duplicate-entity
	 * problem the specialty merge just cleaned up. The hub is by far the
	 * richer page, so the archive points at it.
	 */
	$city_term = get_term( $city_id, Taxonomies\AREA );
	if ( $city_term instanceof \WP_Term ) {
		$archive = (string) get_term_link( $city_term );
		if ( '' !== $archive && Redirects\add( $archive, home_url( '/' . $city_slug . '/' ) ) ) {
			$redirects++;
		}
	}

	flush_rewrite_rules();

	return array(
		'ok'           => true,
		'error'        => '',
		'moved'        => count( $p['moves'] ),
		'redirects'    => $redirects,
		'city_term_id' => $city_id,
	);
}

/* ------------------------------------------------------------------- CLI */

if ( defined( 'WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command(
		'oria migrate-city',
		/**
		 * Insert the city level into the area tree.
		 */
		new class() {
			/**
			 * ## OPTIONS
			 *
			 * [--city=<slug>]
			 * : City slug from data/cities.json. Defaults to the default city.
			 *
			 * [--dry-run]
			 * : Print every move and every redirect without writing anything.
			 *
			 * ## EXAMPLES
			 *
			 *     wp oria migrate-city --dry-run
			 *     wp oria migrate-city
			 */
			public function __invoke( array $args, array $assoc ): void {
				$slug = (string) ( $assoc['city'] ?? Cities\path( Cities\default_city() ) );
				$dry  = isset( $assoc['dry-run'] );

				$p = plan( $slug );

				if ( ! $p['ok'] ) {
					\WP_CLI::error( $p['error'] );
				}

				if ( ! $p['roots'] ) {
					\WP_CLI::success( 'Nothing at the root to move — the tree already has its city level.' );
					return;
				}

				\WP_CLI::log( sprintf(
					'City term "%s": %s',
					$slug,
					$p['creating'] ? 'will be created' : sprintf( 'exists (#%d)', $p['city_term_id'] )
				) );
				\WP_CLI::log( sprintf( '%d regions move under it, carrying %d terms in total.', count( $p['roots'] ), count( $p['moves'] ) ) );
				\WP_CLI::log( '' );

				$home = untrailingslashit( home_url() );
				foreach ( $p['moves'] as $move ) {
					$from = str_replace( $home, '', $move['from'] );
					// The new path is the old one with the city inserted after
					// the /area/ prefix — predictable enough to preview.
					$to   = preg_replace( '#^/area/#', '/area/' . $slug . '/', $from );
					\WP_CLI::log( sprintf( '  %-44s -> %s', $from, $to ) );
				}

				\WP_CLI::log( '' );
				\WP_CLI::log( sprintf( '  %-44s -> /%s/   (city archive folds into the hub)', '/area/' . $slug . '/', $slug ) );

				if ( $dry ) {
					\WP_CLI::log( '' );
					\WP_CLI::log( '- DRY RUN: nothing written -' );
					return;
				}

				$r = run( $slug );

				if ( ! $r['ok'] ) {
					\WP_CLI::error( $r['error'] );
				}

				\WP_CLI::success( sprintf(
					'%d terms moved under city #%d. %d redirects recorded. Rewrite rules flushed.',
					$r['moved'],
					$r['city_term_id'],
					$r['redirects']
				) );
			}
		}
	);
}
