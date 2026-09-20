<?php
/**
 * The Wellness Passport: badges a member earns by trying places.
 *
 * Badges are calculated from Activity, never awarded by hand, and the rules
 * live in one array here rather than in any template. Six to start, each
 * about exploring -- a first visit, three kinds of practice, three suburbs
 * -- and none about doing more of anything. There is deliberately no score,
 * no percentage and no streak: this is a record of places explored, not a
 * measure of a person.
 *
 * The date a badge was earned is kept in user meta so it survives the
 * member later removing a tried place. A badge, once earned, stays.
 */

declare(strict_types=1);

namespace Oria\Core\Passport;

use Oria\Core\Activity;
use Oria\Core\Categories;
use Oria\Core\GoodFor;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META = 'oria_badges';

/**
 * Which listings count towards the themed badges, by the taxonomies the
 * directory already has: the top-level practice, and the "want" chips
 * derived from specialties and services (GoodFor). A listing is in a set
 * if either matches, so a yoga studio counts as movement whatever its
 * chips say, and a sound bath counts as calm without being a "practice".
 */
const SETS = array(
	'calm'     => array( 'practices' => array( 'mind', 'energy' ), 'wants' => array( 'relax', 'reset', 'wind-down', 'stillness' ) ),
	'move'     => array( 'practices' => array( 'yoga', 'fitness' ), 'wants' => array( 'move' ) ),
	'recovery' => array( 'practices' => array( 'spa', 'bodywork' ), 'wants' => array( 'recover', 'recharge', 'hands-on' ) ),
);

const BADGES = array(
	'first_step'        => array(
		'label'     => 'First Step',
		'blurb'     => 'Your Oria journey has begun.',
		'hint'      => 'Try your first wellness experience.',
		'type'      => 'count',
		'threshold' => 1,
	),
	'wellness_explorer' => array(
		'label'     => 'Wellness Explorer',
		'blurb'     => 'Three different kinds of wellness, tried.',
		'hint'      => 'Try three different kinds of practice — yoga, a sauna and a massage, say.',
		'type'      => 'types',
		'threshold' => 3,
	),
	'calm_seeker'       => array(
		'label'     => 'Calm Seeker',
		'blurb'     => 'Three places that slow the week down.',
		'hint'      => 'Try three experiences that are about relaxing, resetting or sitting still.',
		'type'      => 'set',
		'set'       => 'calm',
		'threshold' => 3,
	),
	'move_more'         => array(
		'label'     => 'Move More',
		'blurb'     => 'Three ways of moving, explored.',
		'hint'      => 'Try three movement experiences — yoga, Pilates, a hike, a class.',
		'type'      => 'set',
		'set'       => 'move',
		'threshold' => 3,
	),
	'recovery_explorer' => array(
		'label'     => 'Recovery Explorer',
		'blurb'     => 'Heat, cold and hands: three recovery experiences.',
		'hint'      => 'Try three recovery experiences — a sauna, an ice bath, a massage, a float.',
		'type'      => 'set',
		'set'       => 'recovery',
		'threshold' => 3,
	),
	'perth_explorer'    => array(
		'label'     => 'Perth Explorer',
		'blurb'     => 'Three suburbs, three experiences.',
		'hint'      => 'Try experiences in three different suburbs.',
		'type'      => 'suburbs',
		'threshold' => 3,
	),
);

/* -------------------------------------------------------- listing facts */

/**
 * What a listing contributes to the passport: its kind, its wants, where.
 *
 * @return array{practice:string, wants:list<string>, suburb:string}
 */
function profile( int $listing_id ): array {
	static $memo = array();
	if ( isset( $memo[ $listing_id ] ) ) {
		return $memo[ $listing_id ];
	}
	$tops     = function_exists( '\Oria\Core\Categories\top_for' ) ? Categories\top_for( $listing_id, 1 ) : array();
	$practice = $tops && isset( $tops[0]['term'] ) ? (string) $tops[0]['term']->slug : '';

	$wants = array();
	foreach ( function_exists( '\Oria\Core\GoodFor\for_listing' ) ? GoodFor\for_listing( $listing_id, 5 ) : array() as $w ) {
		$wants[] = (string) $w['slug'];
	}

	$suburb = '';
	$areas  = get_the_terms( $listing_id, Taxonomies\AREA );
	foreach ( is_array( $areas ) ? $areas : array() as $t ) {
		if ( $t instanceof \WP_Term && $t->parent ) {
			$suburb = $t->slug;
			break;
		}
	}
	return $memo[ $listing_id ] = array( 'practice' => $practice, 'wants' => $wants, 'suburb' => $suburb );
}

function in_set( string $set, array $p ): bool {
	$rule = SETS[ $set ] ?? null;
	if ( ! $rule ) {
		return false;
	}
	return in_array( $p['practice'], $rule['practices'], true ) || (bool) array_intersect( $p['wants'], $rule['wants'] );
}

/* --------------------------------------------------------------- progress */

/**
 * The numbers a badge rule reads, from the member's tried list.
 *
 * @return array{experiences:int, types:int, suburbs:int, sets:array<string,int>}
 */
function stats( int $user_id ): array {
	$tried    = Activity\ids( $user_id, Activity\TRIED );
	$types    = array();
	$suburbs  = array();
	$sets     = array_fill_keys( array_keys( SETS ), 0 );
	foreach ( $tried as $id ) {
		$p = profile( $id );
		if ( '' !== $p['practice'] ) {
			$types[ $p['practice'] ] = true;
		}
		if ( '' !== $p['suburb'] ) {
			$suburbs[ $p['suburb'] ] = true;
		}
		foreach ( array_keys( SETS ) as $set ) {
			if ( in_set( $set, $p ) ) {
				$sets[ $set ]++;
			}
		}
	}
	return array(
		'experiences' => count( $tried ),
		'types'       => count( $types ),
		'suburbs'     => count( $suburbs ),
		'sets'        => $sets,
	);
}

function progress_of( array $badge, array $stats ): int {
	switch ( $badge['type'] ) {
		case 'count':
			return $stats['experiences'];
		case 'types':
			return $stats['types'];
		case 'suburbs':
			return $stats['suburbs'];
		case 'set':
			return (int) ( $stats['sets'][ $badge['set'] ] ?? 0 );
	}
	return 0;
}

/** @return array<string,string> slug => earned datetime (site time) */
function earned( int $user_id ): array {
	$meta = get_user_meta( $user_id, META, true );
	return is_array( $meta ) ? array_filter( array_map( 'strval', $meta ) ) : array();
}

/**
 * Every badge with where the member stands on it. Newly earned ones are
 * dated and stored here, so this is also the moment a badge is "given".
 *
 * @return list<array{slug:string, label:string, blurb:string, hint:string, earned:bool, date:string, progress:int, threshold:int}>
 */
function evaluate( int $user_id ): array {
	$stats = stats( $user_id );
	$have  = earned( $user_id );
	$new   = array();
	$out   = array();
	foreach ( BADGES as $slug => $badge ) {
		$progress = progress_of( $badge, $stats );
		$done     = $progress >= $badge['threshold'];
		if ( $done && empty( $have[ $slug ] ) ) {
			$have[ $slug ] = current_time( 'mysql' );
			$new[]         = $slug;
		}
		$out[] = array(
			'slug'      => $slug,
			'label'     => $badge['label'],
			'blurb'     => $badge['blurb'],
			'hint'      => $badge['hint'],
			'earned'    => ! empty( $have[ $slug ] ),
			'date'      => (string) ( $have[ $slug ] ?? '' ),
			'progress'  => min( $progress, $badge['threshold'] ),
			'threshold' => (int) $badge['threshold'],
		);
	}
	if ( $new ) {
		update_user_meta( $user_id, META, $have );
	}
	$GLOBALS['oria_passport_new'][ $user_id ] = $new;
	return $out;
}

/**
 * Re-check after a write and say which badges were earned just now.
 *
 * @return list<string>
 */
function refresh( int $user_id ): array {
	evaluate( $user_id );
	return (array) ( $GLOBALS['oria_passport_new'][ $user_id ] ?? array() );
}

/** The unearned badge the member is closest to, for "next up". */
function next_up( int $user_id ): ?array {
	$best = null;
	foreach ( evaluate( $user_id ) as $b ) {
		if ( $b['earned'] ) {
			continue;
		}
		$ratio = $b['threshold'] > 0 ? $b['progress'] / $b['threshold'] : 0;
		if ( null === $best || $ratio > $best['ratio'] ) {
			$best = $b + array( 'ratio' => $ratio );
		}
	}
	return $best;
}

/** "Tried September 2026" -- the month, from the activity row. */
function tried_label( int $user_id, int $listing_id ): string {
	$at = Activity\when( $user_id, $listing_id, Activity\TRIED );
	if ( '' === $at ) {
		return '';
	}
	/* translators: %s: month and year */
	return sprintf( __( 'Tried %s', 'oria' ), wp_date( 'F Y', (int) strtotime( $at . ' UTC' ) ) );
}
