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

