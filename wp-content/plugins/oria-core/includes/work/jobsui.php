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

/* ------------------------------------------------- one job (detail page) */

/** Job boards an external job can come from, as their own names. */
const SOURCES = array( 'seek' => 'SEEK', 'indeed' => 'Indeed', 'jora' => 'Jora', 'ethicaljobs' => 'Ethical Jobs', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook' );

/** "SEEK" for a job imported from SEEK; '' for anything else (native, or a source we don't name). */
function source_name( int $id ): string {
	return meta( $id, 'external' ) ? ( SOURCES[ (string) meta( $id, 'source' ) ] ?? '' ) : '';
}

/** The host an external application link goes to ("seek.com.au"), or ''. */
function apply_host( int $id ): string {
	if ( 'url' !== (string) meta( $id, 'apply_method', 'oria' ) ) {
		return '';
	}
	return (string) preg_replace( '/^www\./', '', (string) wp_parse_url( (string) meta( $id, 'apply_url' ), PHP_URL_HOST ) );
}

/**
 * The closing date people can rely on, or 0. A native job closes on Oria at
 * its expiry -- the employer chose that run. An imported job's expiry is
 * Oria's own 30-day assumption unless the ad stated a date, so it is not
 * shown as "Applications close".
 */
function closes_at( int $id ): int {
	$exp = (int) meta( $id, 'expires', 0 );
	if ( ! $exp ) {
		return 0;
	}
	return ( ! meta( $id, 'external' ) || meta( $id, 'closes_stated' ) ) ? $exp : 0;
}

/** Where the work is: the named sites when the ad gave them ("Jolimont & Kinross"), else the suburb. */
function work_places( int $id ): string {
	$named = trim( (string) meta( $id, 'locations' ) );
	return '' !== $named ? $named : place_label( $id );
}

/**
 * Requirements as list items, when the text is a list: one per line, or
 * semicolon-separated on a single line (how imported ads store them).
 * Anything else stays one paragraph -- returned as a single item.
 *
 * @return list<string>
 */
function quals_items( string $text ): array {
	$text  = trim( $text );
	$lines = preg_split( '/\R+/', $text ) ?: array();
	if ( count( $lines ) < 2 && substr_count( $text, ';' ) >= 1 ) {
		$lines = explode( ';', $text );
	}
	$items = array();
	foreach ( $lines as $l ) {
		$l = trim( (string) preg_replace( '/^[\s\-\*•·]+/u', '', $l ) );
		if ( '' !== $l ) {
			$items[] = mb_strtoupper( mb_substr( $l, 0, 1 ) ) . mb_substr( $l, 1 );
		}
	}
	return $items;
}

/**
 * Up to $n genuinely related open jobs: same profession, the same region
 * first. Never pads with other professions or closed roles.
 *
 * @return list<int>
 */
function related_jobs( int $id, int $n = 3 ): array {
	$prof = term( $id, PROFESSION );
	if ( ! $prof ) {
		return array();
	}
	$ids  = array_values( array_diff( array_map( 'intval', query( JOB, array( 'profession' => $prof->slug, 'fields' => 'ids' ), 30 )->posts ), array( $id ) ) );
	$mine = city_of( $id );
	// The region a job sits in: a suburb's parent (city > region > suburb), or the region itself.
	$reg = static function ( int $p ): int {
		$s = suburb( $p );
		if ( ! $s ) {
			return 0;
		}
		$up = get_ancestors( $s->term_id, 'area', 'taxonomy' );
		return count( $up ) >= 2 ? (int) $up[0] : (int) $s->term_id;
	};
	$here = $reg( $id );
	usort(
		$ids,
		static function ( int $a, int $b ) use ( $reg, $here, $mine ): int {
			$score = static fn( int $p ): int => ( $here && $reg( $p ) === $here ? 2 : 0 ) + ( $mine && ( city_of( $p )['slug'] ?? '' ) === ( $mine['slug'] ?? '' ) ? 1 : 0 );
			return $score( $b ) <=> $score( $a );
		}
	);
	return array_slice( $ids, 0, $n );
}
