<?php
/**
 * "You might like": a handful of listings for a member, from what they
 * told us they are after and what they have already saved.
 *
 * Plain taxonomy matching, on purpose. A member picks intents (Relax, Move
 * ...) and interests (Yoga, Sauna ...); both are sets of specialty, service
 * and practice slugs the directory already uses, so the query is the same
 * one the "want" chips run. Listings they have saved or tried are left out,
 * a Best Of pick or a featured practice rises, and a listing in their
 * preferred region rises a little. Nothing here is a health recommendation
 * and nothing here reads anything the member did not type.
 *
 * Preferences are user meta, four keys, none of them sensitive.
 */

declare(strict_types=1);

namespace Oria\Core\Recommend;

use Oria\Core\Activity;
use Oria\Core\BestOf;
use Oria\Core\GoodFor;
use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_INTENTS   = 'oria_wellness_intents';
const META_INTERESTS = 'oria_practice_interests';
const META_AREA      = 'oria_preferred_area';
const META_EMAIL     = 'oria_email_preferences';

/** What a member is after. Keys are GoodFor want slugs; labels are theirs. */
const INTENTS = array(
	'relax'     => 'Relax',
	'reset'     => 'Reset',
	'recharge'  => 'Recharge',
	'wind-down' => 'Sleep better',
	'connect'   => 'Connect',
	'move'      => 'Move',
	'hands-on'  => 'Hands-on care',
);

/** What a member wants to explore, mapped onto the directory's terms. */
const INTERESTS = array(
	'yoga'       => array( 'label' => 'Yoga', 'practice' => array( 'yoga' ) ),
	'pilates'    => array( 'label' => 'Pilates', 'specialty' => array( 'pilates', 'reformer-pilates' ), 'service' => array( 'mat-pilates', 'clinical-pilates', 'pilates' ) ),
	'meditation' => array( 'label' => 'Meditation', 'specialty' => array( 'meditation', 'mindfulness' ) ),
	'breathwork' => array( 'label' => 'Breathwork', 'specialty' => array( 'breathwork' ) ),
	'massage'    => array( 'label' => 'Massage', 'specialty' => array( 'massage', 'remedial-massage', 'deep-tissue', 'thai-massage' ) ),
	'sauna'      => array( 'label' => 'Sauna', 'specialty' => array( 'infrared-sauna' ), 'service' => array( 'traditional-sauna', 'infrared-sauna' ) ),
	'ice-bath'   => array( 'label' => 'Ice bath', 'specialty' => array( 'cold-plunge' ), 'service' => array( 'ice-bath' ) ),
	'float'      => array( 'label' => 'Float', 'specialty' => array( 'float-therapy' ) ),
	'sound'      => array( 'label' => 'Sound healing', 'specialty' => array( 'sound-healing' ) ),
	'hiking'     => array( 'label' => 'Hiking', 'specialty' => array( 'hiking', 'forest-bathing' ) ),
	'fitness'    => array( 'label' => 'Fitness', 'practice' => array( 'fitness' ) ),
	'retreats'   => array( 'label' => 'Retreats', 'specialty' => array( 'wellness-retreats' ) ),
	'spa'        => array( 'label' => 'Spa', 'practice' => array( 'spa' ) ),
);

/* ---------------------------------------------------------- preferences */

/** @return array<string,string> region slug => name, plus 'anywhere' */
function areas(): array {
	$out = array();
	foreach ( Taxonomies\regions() as $t ) {
		$out[ $t->slug ] = \Oria\Theme\tname( $t );
	}
	$out['anywhere'] = __( 'Anywhere in Perth', 'oria' );
	return $out;
}

/** @return array{intents:list<string>, interests:list<string>, area:string, email:bool} */
function prefs( int $user_id ): array {
	$intents   = array_values( array_intersect( (array) get_user_meta( $user_id, META_INTENTS, true ), array_keys( INTENTS ) ) );
	$interests = array_values( array_intersect( (array) get_user_meta( $user_id, META_INTERESTS, true ), array_keys( INTERESTS ) ) );
	$area      = (string) get_user_meta( $user_id, META_AREA, true );
	$email     = (array) get_user_meta( $user_id, META_EMAIL, true );
	return array(
		'intents'   => $intents,
		'interests' => $interests,
		'area'      => isset( areas()[ $area ] ) ? $area : '',
		'email'     => ! empty( $email['updates'] ),
	);
}

/** @param array{intents?:array, interests?:array, area?:string, email?:bool} $p */
function save_prefs( int $user_id, array $p ): void {
	update_user_meta( $user_id, META_INTENTS, array_values( array_intersect( array_map( 'sanitize_key', (array) ( $p['intents'] ?? array() ) ), array_keys( INTENTS ) ) ) );
	update_user_meta( $user_id, META_INTERESTS, array_values( array_intersect( array_map( 'sanitize_key', (array) ( $p['interests'] ?? array() ) ), array_keys( INTERESTS ) ) ) );
	$area = sanitize_key( (string) ( $p['area'] ?? '' ) );
	update_user_meta( $user_id, META_AREA, isset( areas()[ $area ] ) ? $area : '' );
	update_user_meta( $user_id, META_EMAIL, array( 'updates' => ! empty( $p['email'] ) ) );
}

function has_prefs( int $user_id ): bool {
	$p = prefs( $user_id );
	return (bool) ( $p['intents'] || $p['interests'] );
}

/* ------------------------------------------------------------- matching */

/**
 * The term slugs a member's preferences reach, by taxonomy.
 *
 * @return array{practice:list<string>, specialty:list<string>, service:list<string>}
 */
function wanted_terms( int $user_id ): array {
	$p    = prefs( $user_id );
	$sets = array( 'practice' => array(), 'specialty' => array(), 'service' => array() );

	foreach ( $p['interests'] as $key ) {
		foreach ( array( 'practice', 'specialty', 'service' ) as $tax ) {
			$sets[ $tax ] = array_merge( $sets[ $tax ], (array) ( INTERESTS[ $key ][ $tax ] ?? array() ) );
		}
	}

	// An intent is a GoodFor want: its vocabulary is specialty and service
	// slugs, sorted by which taxonomy holds each.
	if ( $p['intents'] && function_exists( '\Oria\Core\GoodFor\labels' ) ) {
		$kinds = GoodFor\slug_kinds();
		foreach ( GoodFor\labels() as $row ) {
			if ( ! in_array( $row['slug'], $p['intents'], true ) ) {
				continue;
			}
			foreach ( $row['specs'] as $slug ) {
				$kind = $kinds[ $slug ] ?? '';
				if ( 'spec' === $kind ) {
					$sets['specialty'][] = $slug;
				} elseif ( 'svc' === $kind ) {
					$sets['service'][] = $slug;
				}
			}
		}
	}

	// Nothing chosen yet: borrow the kinds of place they have already saved.
	if ( ! array_filter( $sets ) ) {
		foreach ( Activity\ids( $user_id, Activity\SAVED ) as $id ) {
			foreach ( function_exists( '\Oria\Core\Categories\top_for' ) ? \Oria\Core\Categories\top_for( $id, 1 ) : array() as $top ) {
				$sets['practice'][] = (string) $top['term']->slug;
			}
		}
	}

	return array_map( static fn( array $s ): array => array_values( array_unique( $s ) ), $sets );
}

/**
 * @return list<int> listing ids, best first
 */
function for_user( int $user_id, int $n = 6 ): array {
	$wanted  = wanted_terms( $user_id );
	$exclude = array_unique( array_merge( Activity\ids( $user_id, Activity\SAVED ), Activity\ids( $user_id, Activity\TRIED ) ) );

	$tax = array( 'relation' => 'OR' );
	foreach ( $wanted as $taxonomy => $slugs ) {
		if ( $slugs ) {
			$tax[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slugs );
		}
	}

	$args = array(
		'post_type'      => PostTypes\LISTING,
		'post_status'    => 'publish',
		'posts_per_page' => 80,
		'fields'         => 'ids',
		'post__not_in'   => $exclude ?: array( 0 ),
		'no_found_rows'  => true,
	);
	if ( count( $tax ) > 1 ) {
		$args['tax_query'] = $tax;
	} else {
		// No preferences and nothing saved: the editors' picks stand in.
		$picks = function_exists( '\Oria\Core\BestOf\index' ) ? array_keys( BestOf\index() ) : array();
		$picks = array_values( array_diff( $picks, $exclude ) );
		if ( ! $picks ) {
			return array();
		}
		$args['post__in'] = $picks;
		unset( $args['post__not_in'] );
	}

	$ids = array_map( 'intval', (array) get_posts( $args ) );
	if ( ! $ids ) {
		return array();
	}

	$region  = prefs( $user_id )['area'];
	$best_of = function_exists( '\Oria\Core\BestOf\index' ) ? BestOf\index() : array();
	$scored  = array();
	foreach ( $ids as $id ) {
		$score = 0.0;
		foreach ( $wanted as $taxonomy => $slugs ) {
			if ( ! $slugs ) {
				continue;
			}
			$terms = get_the_terms( $id, $taxonomy );
			$have  = is_array( $terms ) ? wp_list_pluck( $terms, 'slug' ) : array();
			$score += 2 * count( array_intersect( $have, $slugs ) );
		}
		if ( isset( $best_of[ $id ] ) ) {
			$score += 3;
		}
		if ( 'featured' === \Oria\Theme\display_status( $id ) ) {
			$score += 2;
		}
		if ( '' !== $region && 'anywhere' !== $region && in_region( $id, $region ) ) {
			$score += 2;
		}
		$rated  = \Oria\Theme\effective_rating( $id, false );
		$score += min( 5.0, (float) $rated['rating'] ) * 0.5;
		$scored[ $id ] = $score;
	}
	arsort( $scored, SORT_NUMERIC );
	return array_slice( array_keys( $scored ), 0, $n );
}

function in_region( int $listing_id, string $region_slug ): bool {
	$areas = get_the_terms( $listing_id, Taxonomies\AREA );
	foreach ( is_array( $areas ) ? $areas : array() as $t ) {
		if ( $t instanceof \WP_Term && Taxonomies\region_for( $t )->slug === $region_slug ) {
			return true;
		}
	}
	return false;
}
