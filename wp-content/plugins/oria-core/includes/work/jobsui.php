<?php
/**
 * Work in Wellness: what the jobs page (2026-10 redesign) asks of the data.
 *
 *   jobs_url()           one place that builds every filter, chip, sort and
 *                        shortcut link, keeping the rest of the search
 *   profession_counts()  open jobs per profession under the current filters,
 *                        from ONE query (not one per profession)
 *   place_phrase()       "Perth & surrounds" / "Fremantle" -- the area the
 *                        count describes, city-aware
 *   active_filters()     the chips: label + link that removes just that one
 *
 * FILTER LOGIC. Different filters combine with AND. Within one dimension
 * there is one value (one profession, one area, one employment type), so the
 * question of OR does not arise.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Query keys the jobs page carries in its URL, in a stable order. */
const JOB_KEYS = array( 'q', 'profession', 'area', 'type', 'paid', 'remote', 'weekend', 'evening', 'saved', 'sort' );

/**
 * The jobs list URL for the current search, with $change applied.
 * A value of '' or false removes that key. 'pg' is always dropped (any change
 * of search starts again at page one) unless $change sets it.
 */
function jobs_url( array $f, array $change = array() ): string {
	$state = array();
	foreach ( JOB_KEYS as $k ) {
		$v = array_key_exists( $k, $change ) ? $change[ $k ] : ( $f[ $k ] ?? '' );
		if ( true === $v ) {
			$v = '1';
		}
		if ( '' !== (string) $v && false !== $v && null !== $v ) {
			$state[ $k ] = (string) $v;
		}
	}
	if ( isset( $change['pg'] ) && (int) $change['pg'] > 1 ) {
		$state['pg'] = (int) $change['pg'];
	}
	// Default sort is newest: never written into the URL.
	if ( isset( $state['sort'] ) && ! in_array( $state['sort'], array( 'closing', 'pay' ), true ) ) {
		unset( $state['sort'] );
	}
	$city = (string) ( $f['city'] ?? '' );
	// A bare profession with nothing else is its own page (/jobs/category/yoga-teacher/).
	if ( array( 'profession' ) === array_keys( $state ) ) {
		return list_url( 'jobs', $city, $state['profession'] );
	}
	return add_query_arg( array_map( 'rawurlencode', $state ), list_url( 'jobs', $city ) );
}

/** How many filters beyond keyword/place are on (for "Filters (2)"). */
function filter_count( array $f ): int {
	$n = 0;
	foreach ( array( 'profession', 'type', 'remote', 'weekend', 'evening' ) as $k ) {
		$n += ( ! empty( $f[ $k ] ) ) ? 1 : 0;
	}
	return $n;
}

/**
 * Open jobs per profession slug for the current search with the profession
 * itself left out -- so a shortcut's count says what clicking it would show.
 *
 * @return array<string, int> slug => count, most first
 */
function profession_counts( array $f ): array {
	$f2 = $f;
	unset( $f2['profession'], $f2['page'] );
	$f2['fields'] = 'ids';
	$ids = array_map( 'intval', query( JOB, $f2, 500 )->posts );
	if ( ! $ids ) {
		return array();
	}
	$counts = array();
	foreach ( wp_get_object_terms( $ids, PROFESSION, array( 'fields' => 'all_with_object_id' ) ) as $t ) {
		$counts[ $t->slug ] = ( $counts[ $t->slug ] ?? 0 ) + 1;
	}
	arsort( $counts );
	return $counts;
}

/** The area a count describes: the chosen area, or "{City} & surrounds". */
function place_phrase( array $f ): string {
	if ( ! empty( $f['area'] ) ) {
		$t = get_term_by( 'slug', (string) $f['area'], 'area' );
		if ( $t ) {
			return html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
		}
	}
	$city = ! empty( $f['city'] ) && function_exists( '\Oria\Core\Cities\get' ) ? \Oria\Core\Cities\get( (string) $f['city'] ) : null;
	if ( $city ) {
		/* translators: %s: city */
		return sprintf( __( '%s & surrounds', 'oria' ), (string) $city['name'] );
	}
	// No place chosen: the search covers every live city, so name them all.
	$live = function_exists( '\Oria\Core\Cities\live' ) ? array_values( \Oria\Core\Cities\live() ) : array();
	if ( 1 === count( $live ) ) {
		/* translators: %s: city */
		return sprintf( __( '%s & surrounds', 'oria' ), (string) $live[0]['name'] );
	}
	$names = array_map( static fn( $c ) => (string) $c['name'], $live );
	if ( count( $names ) > 1 ) {
		$last = array_pop( $names );
		return implode( ', ', $names ) . ' & ' . $last;
	}
	return __( 'All areas', 'oria' );
}

/**
 * The applied-filter chips: each one's label and the link that removes it.
 *
 * @return list<array{label:string, url:string}>
 */
function active_filters( array $f ): array {
	$chips = array();
	$add   = static function ( string $key, string $label ) use ( &$chips, $f ): void {
		$chips[] = array( 'label' => $label, 'url' => jobs_url( $f, array( $key => '' ) ) );
	};
	if ( '' !== (string) ( $f['q'] ?? '' ) ) {
		/* translators: %s: search words */
		$add( 'q', sprintf( __( '“%s”', 'oria' ), (string) $f['q'] ) );
	}
	if ( ! empty( $f['profession'] ) && ( $t = get_term_by( 'slug', (string) $f['profession'], PROFESSION ) ) ) {
		$add( 'profession', $t->name );
	}
	if ( ! empty( $f['area'] ) && ( $t = get_term_by( 'slug', (string) $f['area'], 'area' ) ) ) {
		$add( 'area', html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ) );
	}
	if ( ! empty( $f['type'] ) && isset( employment_types()[ (string) $f['type'] ] ) ) {
		$add( 'type', employment_types()[ (string) $f['type'] ] );
	}
	foreach ( array( 'paid' => __( 'Pay shown', 'oria' ), 'remote' => __( 'Remote or hybrid', 'oria' ), 'weekend' => __( 'Weekend work', 'oria' ), 'evening' => __( 'Evening work', 'oria' ), 'saved' => __( 'Saved jobs', 'oria' ) ) as $k => $label ) {
		if ( ! empty( $f[ $k ] ) ) {
			$add( $k, $label );
		}
	}
	return $chips;
}

/** Posted date as people read it: "Posted today", "Posted 9 days ago", then "Posted 3 Sep". */
function posted_label( int $id ): string {
	$t = (int) get_post_time( 'U', true, $id );
	if ( ! $t ) {
		return '';
	}
	$days = (int) floor( ( strtotime( 'today', time() ) - strtotime( 'today', $t ) ) / DAY_IN_SECONDS );
	if ( $days <= 0 ) {
		return __( 'Posted today', 'oria' );
	}
	if ( 1 === $days ) {
		return __( 'Posted yesterday', 'oria' );
	}
	if ( $days < 14 ) {
		/* translators: %d: days */
		return sprintf( __( 'Posted %d days ago', 'oria' ), $days );
	}
	/* translators: %s: date */
	return sprintf( __( 'Posted %s', 'oria' ), wp_date( 'j M', $t ) );
}
