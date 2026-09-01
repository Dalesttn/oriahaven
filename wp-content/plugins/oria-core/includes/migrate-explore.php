<?php
/**
 * Moving the directory under /explore/{city}/{category}/{specialty}/.
 *
 * WHY A CAPTURE STEP AT ALL. Every old address is computed from live terms
 * and live routing rules. The instant a rewrite rule changes,
 * get_term_link() answers differently and the old path is unrecoverable —
 * the same trap migrate-city.php records in its own header, and the reason
 * that migration captured before it reparented anything. So this file runs
 * in two halves that are deliberately separate:
 *
 *   capture — work out every old => new pair and archive it. Writes nothing
 *             that changes the site. Safe to run repeatedly.
 *   serve   — promote chosen pairs into the live 301 map.
 *
 * Capturing is the irreversible moment; serving is a switch. Ninety days of
 * Search Console says 52 of the moving URLs hold ~94% of the impressions
 * and the rest hold almost nothing, so the intent is to capture everything
 * and serve the shortlist — but if something unexpected sinks later, the
 * archive still holds the answer and `serve --all` turns it on.
 *
 * THE SPECIALTY RULE. A specialty appears under several categories in the
 * listing data, so /practices/yoga/cold-plunge/ and /practices/spa/
 * cold-plunge/ are today two addresses for one thing. Both are sent to the
 * specialty's declared home — data/specialty-homes.json — which is what
 * collapses that duplication rather than carrying it across.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\MigrateExplore;

use Oria\Core\Cities;
use Oria\Core\PracticesIndex;
use Oria\Core\Redirects;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where the full captured map lives. Not autoloaded: read only by CLI. */
const ARCHIVE = 'oria_explore_urls';

/** The new root segment. */
const BASE = 'explore';

/** Path helpers -------------------------------------------------------- */

function norm( string $path ): string {
	$path = (string) wp_parse_url( $path, PHP_URL_PATH );
	$path = '/' . trim( $path, '/' );

	return '/' === $path ? '/' : $path . '/';
}

/** /explore/perth/spa/sauna/ from its parts, skipping empty ones. */
function build( string ...$segments ): string {
	$segments = array_values( array_filter( $segments, static fn( $s ) => '' !== $s ) );

	return norm( '/' . BASE . '/' . implode( '/', $segments ) );
}

/**
 * The new address for a city + category + trailing segment.
 *
 * Drops the trailing segment when it repeats the category. Two specialties
 * share a slug with their home category -- yoga and nutrition -- and
 * /explore/perth/yoga/yoga/ is not a narrower page than
 * /explore/perth/yoga/, it is the same set said twice.
 */
function target( string $city, string $cat, string $tail = '' ): string {
	if ( $tail === $cat ) {
		$tail = '';
	}

	return build( $city, $cat, $tail );
}

/**
 * Every facet URL that answers 200 today, and the category it should land
 * under.
 *
 * Deliberately not sitemap_entries(): that list holds only the canonical
 * owner of each facet, because it exists to say what should be indexed.
 * Google indexed the copies anyway — /practices/yoga/cold-plunge/ earns
 * impressions — and a URL that answers today needs an answer tomorrow.
 *
 * The target category is the specialty's declared home where the facet is a
 * specialty, which is precisely what folds those copies into one page.
 * Everything else keeps the category it was filed under.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function facet_paths(): array {
	$out = array();

	foreach ( get_terms( array( 'taxonomy' => Taxonomies\PRACTICE, 'hide_empty' => false ) ) as $practice ) {
		if ( ! $practice instanceof \WP_Term ) {
			continue;
		}
		$ids = function_exists( '\Oria\Core\Intents\listings_in' )
			? \Oria\Core\Intents\listings_in( $practice )
			: array();
		if ( ! $ids ) {
			continue;
		}

		// The slugs a person could reach on this category, the same way the
		// sitemap walk gathers them.
		$candidates = array();
		foreach ( $ids as $id ) {
			foreach ( array( 'service', Taxonomies\SPECIALTY, Taxonomies\AREA ) as $tax ) {
				if ( ! taxonomy_exists( $tax ) ) {
					continue;
				}
				$terms = wp_get_post_terms( (int) $id, $tax );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $t ) {
					$candidates[ $t->slug ] = true;
					if ( Taxonomies\AREA === $tax && $t->parent ) {
						$p = get_term( (int) $t->parent, Taxonomies\AREA );
						if ( $p instanceof \WP_Term ) {
							$candidates[ $p->slug ] = true;
						}
					}
				}
			}
		}

		/*
		 * The facets that are not taxonomy terms on a listing: the audience
		 * vocabulary, and the fixed online/free pair. resolve_facet() answers
		 * for these too, so a walk that only reads a listing's own terms
		 * misses them -- /practices/meditation/free/ is a live, ranking page.
		 */
		if ( taxonomy_exists( 'audience' ) ) {
			foreach ( get_terms( array( 'taxonomy' => 'audience', 'hide_empty' => false ) ) as $aud ) {
				if ( $aud instanceof \WP_Term ) {
					$candidates[ $aud->slug ] = true;
				}
			}
		}
		$candidates['online'] = true;
		$candidates['free']   = true;

		$base = PracticesIndex\category_url( $practice );
		foreach ( array_keys( $candidates ) as $slug ) {
			$f = PracticesIndex\resolve_facet( $practice, $slug );
			if ( null === $f ) {
				continue;
			}
			// Zero listings already 404s, so there is nothing to carry.
			if ( ! PracticesIndex\facet_ids( $practice, $f ) ) {
				continue;
			}

			$fslug = (string) $f['slug'];
			$path  = norm( $base . $fslug . '/' );
			$home  = PracticesIndex\specialty_home( $fslug );
			if ( '' !== $home ) {
				$out[ $path ] = array( $home, $fslug );
				continue;
			}

			/*
			 * Not a specialty. Keep the owner's category where the resolver
			 * names one, so a non-owner copy and its owner agree on where
			 * they are going.
			 */
			$owner = norm( PracticesIndex\facet_canonical_url( $practice, $f ) );
			$segs  = array_values( array_filter( explode( '/', trim( $owner, '/' ) ) ) );
			$cat   = count( $segs ) >= 2 ? $segs[1] : $practice->slug;
			$out[ $path ] = array( $cat, $fslug );
		}
	}

	return $out;
}

/**
 * Every old address and where it goes.
 *
 * Built from live data, so it is only correct while the old routes are
 * still in place. That is the whole reason this runs first.
 *
 * @return array<string, string>
 */
function pairs(): array {
	$out     = array();
	$default = (string) ( Cities\default_city()['slug'] ?? 'perth' );

	// The directory root, and the category index that lists every category.
	$out[ norm( '/directory/' ) ]  = build();
	$out[ norm( '/practices/' ) ]  = build( $default );

	// City hubs. /perth/ has the most traffic of anything that moves.
	foreach ( Cities\all() as $city ) {
		$slug = (string) ( $city['slug'] ?? '' );
		if ( '' !== $slug ) {
			$out[ norm( '/' . $slug . '/' ) ] = build( $slug );
		}
	}

	// Category pages. These were always the default city's.
	foreach ( get_terms( array( 'taxonomy' => Taxonomies\PRACTICE, 'hide_empty' => false ) ) as $practice ) {
		if ( ! $practice instanceof \WP_Term ) {
			continue;
		}
		$out[ norm( PracticesIndex\category_url( $practice ) ) ] = build( $default, $practice->slug );
	}

	/*
	 * Specialty pages, one per city. The home category comes from the map
	 * rather than from the URL, so every city sends a specialty to the same
	 * category and the address stays canonical across all of them.
	 */
	foreach ( get_terms( array( 'taxonomy' => Taxonomies\SPECIALTY, 'hide_empty' => false ) ) as $spec ) {
		if ( ! $spec instanceof \WP_Term ) {
			continue;
		}
		$home = PracticesIndex\specialty_home( $spec->slug );
		if ( '' === $home ) {
			continue; // no home, no page — and so nothing to point at
		}
		foreach ( Cities\all() as $city ) {
			$slug = (string) ( $city['slug'] ?? '' );
			if ( '' !== $slug ) {
				$out[ norm( '/' . $slug . '/' . $spec->slug . '/' ) ] = target( $slug, $home, $spec->slug );
			}
		}
	}

	/*
	 * Facet pages, from the same list the facet sitemap advertises — what
	 * we actually asked Google to index rather than every combination the
	 * router would answer.
	 *
	 * A facet that is a specialty goes to that specialty's home, which is
	 * how /practices/yoga/cold-plunge/ and /practices/spa/cold-plunge/ stop
	 * being two pages. Anything else — a service, suburb, audience, price
	 * or format — keeps the category it was filed under.
	 */
	foreach ( facet_paths() as $path => $parts ) {
		$out[ $path ] = target( $default, $parts[0], $parts[1] );
	}

	// A pair that does not move is not a redirect.
	foreach ( $out as $from => $to ) {
		if ( norm( $from ) === norm( $to ) ) {
			unset( $out[ $from ] );
		}
	}

	return $out;
}

/**
 * Archive the map. Changes nothing the site serves.
 *
 * @return array{count: int, written: bool}
 */
function capture( bool $dry = false ): array {
	/*
	 * Once phase 5 flips the builders, category_url() answers with the NEW
	 * address and pairs() would record /explore/... on both sides -- a map
	 * of nothing, written over the only copy of the real one. Refuse.
	 */
	$probe = get_terms( array( 'taxonomy' => Taxonomies\PRACTICE, 'hide_empty' => false, 'number' => 1 ) );
	if ( is_array( $probe ) && isset( $probe[0] ) && $probe[0] instanceof \WP_Term ) {
		if ( false !== strpos( PracticesIndex\category_url( $probe[0] ), '/' . BASE . '/' ) ) {
			return array(
				'count'   => 0,
				'written' => false,
				'error'   => 'The URL builders already point at /' . BASE . '/. Capture had to run before they were flipped; the existing archive is the only copy.',
			);
		}
	}

	$map = pairs();
	if ( ! $dry ) {
		update_option( ARCHIVE, $map, false );
	}

	return array( 'count' => count( $map ), 'written' => ! $dry );
}

/** @return array<string, string> */
function archive(): array {
	$map = get_option( ARCHIVE, array() );

	return is_array( $map ) ? $map : array();
}

/**
 * Promote archived pairs into the live 301 map.
 *
 * Redirects\add() refuses a self-redirect and repoints anything already
 * aimed at the old URL, so running this twice is harmless.
 *
 * @param list<string>|null $only Paths to serve, or null for all of them.
 * @return array{served: int, skipped: int, missing: list<string>}
 */
function serve( ?array $only = null, bool $dry = false ): array {
	$map     = archive();
	$served  = 0;
	$skipped = 0;
	$missing = array();

	$wanted = null === $only ? array_keys( $map ) : array_map( __NAMESPACE__ . '\norm', $only );

	foreach ( $wanted as $from ) {
		if ( ! isset( $map[ $from ] ) ) {
			$missing[] = $from;
			continue;
		}
		if ( $dry ) {
			++$served;
			continue;
		}
		if ( Redirects\add( $from, $map[ $from ] ) ) {
			++$served;
		} else {
			++$skipped;
		}
	}

	return array( 'served' => $served, 'skipped' => $skipped, 'missing' => $missing );
}

/* ------------------------------------------------------------------- CLI */

if ( defined( 'WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command(
		'oria migrate-explore',
		/**
		 * Capture and serve the /explore/ URL moves.
		 */
		new class() {
			/**
			 * ## OPTIONS
			 *
			 * <action>
			 * : capture, show, or serve.
			 *
			 * [--dry-run]
			 * : Work everything out and write nothing.
			 *
			 * [--all]
			 * : serve: promote every archived pair, not just the shortlist.
			 *
			 * [--from=<file>]
			 * : serve: a JSON file holding the paths to serve.
			 *
			 * ## EXAMPLES
			 *
			 *     wp oria migrate-explore capture --dry-run
			 *     wp oria migrate-explore capture
			 *     wp oria migrate-explore show
			 *     wp oria migrate-explore serve --from=split_final.json
			 */
			public function __invoke( array $args, array $assoc ): void {
				$action = (string) ( $args[0] ?? '' );
				$dry    = isset( $assoc['dry-run'] );

				if ( 'capture' === $action ) {
					$r = capture( $dry );
					if ( isset( $r['error'] ) ) {
						\WP_CLI::error( $r['error'] );
					}
					\WP_CLI::log( sprintf( '%d URLs captured.', $r['count'] ) );
					if ( $dry ) {
						\WP_CLI::warning( 'DRY RUN — nothing written.' );
						return;
					}
					\WP_CLI::success( sprintf( 'Archived to the %s option. Nothing redirects yet.', ARCHIVE ) );
					return;
				}

				if ( 'show' === $action ) {
					$map = archive();
					if ( ! $map ) {
						\WP_CLI::error( 'Nothing archived. Run capture first.' );
					}
					foreach ( $map as $from => $to ) {
						\WP_CLI::log( sprintf( '%-52s -> %s', $from, $to ) );
					}
					\WP_CLI::log( sprintf( "\n%d pairs.", count( $map ) ) );
					return;
				}

				if ( 'serve' === $action ) {
					$only = null;
					if ( isset( $assoc['from'] ) ) {
						$raw = (string) file_get_contents( (string) $assoc['from'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
						$j   = json_decode( $raw, true );
						$only = is_array( $j['redirect'] ?? null ) ? $j['redirect'] : ( is_array( $j ) ? $j : array() );
					} elseif ( ! isset( $assoc['all'] ) ) {
						\WP_CLI::error( 'Give --from=<file> or --all. Serving everything is a decision, not a default.' );
					}

					$r = serve( $only, $dry );
					\WP_CLI::log( sprintf( 'served %d, unchanged %d, not in archive %d', $r['served'], $r['skipped'], count( $r['missing'] ) ) );
					foreach ( array_slice( $r['missing'], 0, 10 ) as $m ) {
						\WP_CLI::warning( 'not archived: ' . $m );
					}
					if ( $dry ) {
						\WP_CLI::warning( 'DRY RUN — nothing written.' );
						return;
					}
					\WP_CLI::success( 'Live redirect map updated.' );
					return;
				}

				\WP_CLI::error( 'Unknown action. Use capture, show or serve.' );
			}
		}
	);
}
