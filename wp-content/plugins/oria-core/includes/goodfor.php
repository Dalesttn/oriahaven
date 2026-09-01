<?php
/**
 * The discovery chips — twelve wants a visitor can browse by.
 *
 * A thin loader over data/goodfor.json, which is where the actual thinking
 * lives: every label is the visitor's own want ("I want to move"), mapped to
 * specialty slugs the directory filter engine already understands. Nothing
 * here tags a listing, writes meta, or claims anything about any business —
 * a chip is a saved set of filters with a friendlier name.
 *
 * Why not "Good for" badges on listings themselves? Because "good for sleep"
 * pinned to a named business is a therapeutic claim made on its behalf,
 * which this directory makes nowhere — see data/reasons.json for the full
 * statement of that rule. Browsing by want is the compliant half of the
 * same idea, and the half that actually helps someone choose.
 */

declare(strict_types=1);

namespace Oria\Core\GoodFor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every chip, in display order.
 *
 * @return array<int, array{slug: string, label: string, color: string, line: string, specs: array<int, string>}>
 */
function labels(): array {
	static $labels = null;
	if ( null !== $labels ) {
		return $labels;
	}
	$labels = array();
	$path   = ORIA_CORE_DIR . 'data/goodfor.json';
	if ( is_readable( $path ) ) {
		$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( is_array( $json ) && is_array( $json['labels'] ?? null ) ) {
			foreach ( $json['labels'] as $row ) {
				if ( ! is_array( $row ) || empty( $row['slug'] ) || empty( $row['label'] ) || empty( $row['specs'] ) ) {
					continue;
				}
				$labels[] = array(
					'slug'  => (string) $row['slug'],
					'label' => (string) $row['label'],
					'color' => (string) ( $row['color'] ?? '#333' ),
					'line'  => (string) ( $row['line'] ?? '' ),
					'specs' => array_values( array_map( 'strval', (array) $row['specs'] ) ),
				);
			}
		}
	}
	return $labels;
}

/**
 * Where a want points when it has to be a link rather than a filter toggle
 * (the homepage has no results engine to drive).
 *
 * The directory ANDs `spec` against `svc`, so a URL naming both kinds
 * intersects two sets instead of widening one. Same rule as gfBoxesFor()
 * in app.js: take whichever taxonomy holds more of this want's vocabulary
 * and send only that.
 */
/**
 * How many of these listings each want reaches.
 *
 * A want is a set of specialty and service slugs; a listing counts once
 * for a want if it carries any of them. Pass the ids the page is showing
 * and the chip row can drop the wants that would filter to nothing --
 * twelve chips over seventeen listings in Margaret River, most of them
 * leading to an empty grid.
 *
 * @param list<int> $ids
 * @return array<string, int>
 */
function counts( array $ids, int $min = 3 ): array {
	if ( ! $ids ) {
		return array();
	}
	if ( function_exists( '\Oria\Theme\prime_listing_terms' ) ) {
		\Oria\Theme\prime_listing_terms( $ids );
	}

	$out = array();
	foreach ( $ids as $id ) {
		$slugs = array();
		foreach ( array( 'service', 'specialty' ) as $tax ) {
			foreach ( (array) get_the_terms( (int) $id, $tax ) as $term ) {
				if ( $term instanceof \WP_Term ) {
					$slugs[ $term->slug ] = true;
				}
			}
		}
		foreach ( labels() as $want ) {
			foreach ( (array) $want['specs'] as $slug ) {
				if ( isset( $slugs[ $slug ] ) ) {
					$out[ $want['slug'] ] = ( $out[ $want['slug'] ] ?? 0 ) + 1;
					break;
				}
			}
		}
	}

	// A want behind one or two places is a curiosity, not a way in.
	return array_filter( $out, static fn( int $n ): bool => $n >= $min );
}

function filter_url( array $want ): string {
	$spec = array();
	$svc  = array();
	/*
	 * Which taxonomy owns a slug never changes within a request, and the
	 * home page asks the same question for every want tag -- 134 term
	 * lookups for twelve answers.
	 */
	static $kind_of = array();

	foreach ( $want['specs'] as $slug ) {
		if ( ! isset( $kind_of[ $slug ] ) ) {
			if ( get_term_by( 'slug', $slug, 'specialty' ) ) {
				$kind_of[ $slug ] = 'spec';
			} elseif ( get_term_by( 'slug', $slug, 'service' ) ) {
				$kind_of[ $slug ] = 'svc';
			} else {
				$kind_of[ $slug ] = '';
			}
		}
		if ( 'spec' === $kind_of[ $slug ] ) {
			$spec[] = $slug;
		} elseif ( 'svc' === $kind_of[ $slug ] ) {
			$svc[] = $slug;
		}
	}
	$use  = count( $spec ) >= count( $svc ) ? $spec : $svc;
	$kind = count( $spec ) >= count( $svc ) ? 'spec' : 'svc';
	// The directory's own address, so this follows it rather than pinning
	// a copy of it here.
	$base = get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' );
	if ( ! $use ) {
		return $base;
	}

	return add_query_arg( $kind, rawurlencode( implode( ',', $use ) ), $base );
}

/**
 * The want-tags for one listing, derived from its specialty terms.
 *
 * Same rule as gfTags() in app.js: the labels whose spec sets overlap the
 * listing's own specialties, most-overlapping first, capped. Derived and
 * never stored, so the two card renderers cannot drift apart.
 *
 * @return array<int, array{slug: string, label: string, color: string}>
 */
function for_listing( int $listing_id, int $limit = 3 ): array {
	// Specialties AND services: allied professions (podiatry, orthotics)
	// live in the service vocabulary, and a want set may name either kind.
	$have = array();
	foreach ( array( 'specialty', 'service' ) as $tax ) {
		$terms = get_the_terms( $listing_id, $tax );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$have += array_fill_keys( wp_list_pluck( $terms, 'slug' ), true );
		}
	}
	if ( ! $have ) {
		return array();
	}

	$scored = array();
	foreach ( labels() as $i => $row ) {
		$hits = count( array_filter( $row['specs'], static fn( string $s ): bool => isset( $have[ $s ] ) ) );
		if ( $hits > 0 ) {
			$scored[] = array( 'hits' => $hits, 'i' => $i, 'row' => $row );
		}
	}
	usort( $scored, static fn( array $a, array $b ): int => ( $b['hits'] <=> $a['hits'] ) ?: ( $a['i'] <=> $b['i'] ) );

	return array_map(
		static fn( array $x ): array => array(
			'slug'  => $x['row']['slug'],
			'label' => $x['row']['label'],
			'color' => $x['row']['color'],
		),
		array_slice( $scored, 0, $limit )
	);
}

