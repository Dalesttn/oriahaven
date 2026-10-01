<?php
/**
 * Work in Wellness, phase 2: finding people.
 *
 *   - Availability posts (brief sections 46-47): a practitioner says "free
 *     for cover, Sat & Sun, 4-19 Oct" and appears on the cover board for
 *     those dates.
 *   - Practitioner search for employers (brief section 27): profession,
 *     near a suburb within a radius, experience, skills, times, cover,
 *     verified only, available on a date; sorted by match, distance or
 *     experience. Deterministic, recomputable by hand.
 *   - The numbers a dashboard shows (brief sections 65-66), read from the
 *     same per-post daily counters the listing analytics use.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ISO weekday (1-7) => short label. */
const WEEKDAYS = array( 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun' );

/* ---------------------------------------------------------- availability */

/**
 * The practitioner's current availability post, or null when there is none
 * or it has ended. Shape: from, to (Y-m-d), days (list of 1-7), note.
 */
function avail_post( int $pro ): ?array {
	$to = (string) meta( $pro, 'avail_to' );
	if ( '' === $to || $to < wp_date( 'Y-m-d' ) ) {
		return null;
	}
	return array(
		'from' => (string) meta( $pro, 'avail_from' ) ?: wp_date( 'Y-m-d' ),
		'to'   => $to,
		'days' => array_map( 'intval', (array) meta( $pro, 'avail_days', array() ) ),
		'note' => (string) meta( $pro, 'avail_note' ),
	);
}

/** Free on this date, by their availability post? */
function available_on( int $pro, string $date ): bool {
	$a = avail_post( $pro );
	if ( ! $a || $date < $a['from'] || $date > $a['to'] ) {
		return false;
	}
	return ! $a['days'] || in_array( (int) wp_date( 'N', (int) strtotime( $date . ' 12:00' ) ), $a['days'], true );
}

/** "Sat & Sun · 4–19 Oct", or "Any day · until 19 Oct". */
function avail_label( int $pro ): string {
	$a = avail_post( $pro );
	if ( ! $a ) {
		return '';
	}
	$days = $a['days'] ? implode( ' & ', array_map( static fn( $d ) => WEEKDAYS[ $d ] ?? '', $a['days'] ) ) : __( 'Any day', 'oria' );
	if ( 2 < count( $a['days'] ) ) {
		$days = implode( ', ', array_map( static fn( $d ) => WEEKDAYS[ $d ] ?? '', $a['days'] ) );
	}
	$from = strtotime( $a['from'] );
	$to   = strtotime( $a['to'] );
	$span = $a['from'] <= wp_date( 'Y-m-d' )
		/* translators: %s: date */
		? sprintf( __( 'until %s', 'oria' ), wp_date( 'j M', $to ) )
		: ( wp_date( 'M', $from ) === wp_date( 'M', $to ) ? wp_date( 'j', $from ) . '–' . wp_date( 'j M', $to ) : wp_date( 'j M', $from ) . '–' . wp_date( 'j M', $to ) );
	return $days . ' · ' . $span;
}

/* ---------------------------------------------------------------- search */

/**
 * Practitioner search. Visibility is applied first (query() already keeps
 * hidden profiles, and employers-only ones for non-employers, out); then the
 * richer filters run in PHP over at most 400 candidates.
 *
 * $f adds to query()'s keys: near (suburb slug), radius (km), years (min),
 * skills (list of slugs), times (list of weekends|evenings|mornings),
 * verified (1), date (Y-m-d, available on), sort (match|near|years).
 *
 * @return array{ids: list<int>, total: int, km: array<int, float|null>}
 */
function search_pros( array $f, int $per = 24 ): array {
	$base = $f;
	unset( $base['page'], $base['sort'] );
	$q = query( PRO, $base, 400 );

	$near   = ! empty( $f['near'] ) ? get_term_by( 'slug', (string) $f['near'], 'area' ) : null;
	$centre = $near instanceof \WP_Term ? centre_of( $near ) : null;
	$radius = max( 0, (int) ( $f['radius'] ?? 0 ) );
	$years  = max( 0, (int) ( $f['years'] ?? 0 ) );
	$skills = array_filter( array_map( 'sanitize_title', (array) ( $f['skills'] ?? array() ) ) );
	$times  = array_intersect( (array) ( $f['times'] ?? array() ), array( 'weekends', 'evenings', 'mornings' ) );
	$date   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $f['date'] ?? '' ) ) ? (string) $f['date'] : '';

	$rows = array();
	foreach ( $q->posts as $p ) {
		$id = (int) $p->ID;
		if ( $years && (int) meta( $id, 'years', 0 ) < $years ) {
			continue;
		}
		if ( $skills && array_diff( $skills, wp_get_post_terms( $id, SKILL, array( 'fields' => 'slugs' ) ) ) ) {
			continue;
		}
		$when = (array) meta( $id, 'availability', array() );
		if ( $times && array_diff( $times, $when ) ) {
			continue;
		}
		if ( in_array( 'not', $when, true ) && ( ! empty( $f['cover'] ) || $date ) ) {
			continue;
		}
		if ( ! empty( $f['verified'] ) && ! badges( $id ) ) {
			continue;
		}
		if ( $date && ! available_on( $id, $date ) ) {
			continue;
		}
		$km = null;
		if ( $centre ) {
			$s  = suburb( $id );
			$c  = $s ? centre_of( $s ) : null;
			$km = ( $c && function_exists( '\Oria\Core\Geo\distance_km' ) ) ? round( \Oria\Core\Geo\distance_km( $centre, $c ), 1 ) : null;
			// With a radius: only people we can place, inside it, who would also travel that far.
			if ( $radius && ( null === $km || $km > $radius || $km > max( 1, (int) meta( $id, 'radius', 15 ) ) ) ) {
				continue;
			}
		}
		$score = completeness( $id )['score'] / 10
			+ 3 * count( badges( $id ) )
			+ ( avail_post( $id ) ? 6 : 0 )
			+ ( null !== $km ? max( 0, 10 - $km / 2 ) : 0 )
			// Internal reliability nudges the order; it never hides anyone.
			+ ( function_exists( __NAMESPACE__ . '\Trust\reliability' ) ? Trust\reliability( $id ) / 10 : 0 );
		$rows[ $id ] = array( 'score' => $score, 'km' => $km, 'years' => (int) meta( $id, 'years', 0 ) );
	}

	$sort = (string) ( $f['sort'] ?? '' );
	uasort(
		$rows,
		static function ( $a, $b ) use ( $sort ) {
			if ( 'near' === $sort ) {
				return ( $a['km'] ?? 9999 ) <=> ( $b['km'] ?? 9999 );
			}
			if ( 'years' === $sort ) {
				return $b['years'] <=> $a['years'];
			}
			return $b['score'] <=> $a['score'];
		}
	);

	$page = max( 1, (int) ( $f['page'] ?? 1 ) );
	$ids  = array_slice( array_keys( $rows ), ( $page - 1 ) * $per, $per );
	$km   = array();
	foreach ( $ids as $id ) {
		$km[ $id ] = $rows[ $id ]['km'];
	}
	return array( 'ids' => $ids, 'total' => count( $rows ), 'km' => $km );
}

/* ------------------------------------------------------------------ stats */

/** A counter over the last $days, from the shared daily store (Analytics). */
function stat( int $post_id, string $type, int $days = 30 ): int {
	return function_exists( '\Oria\Core\Analytics\total' ) ? (int) \Oria\Core\Analytics\total( $post_id, $type, $days ) : 0;
}

/** Record a counter server-side (a save is a form post, not a beacon). */
function count_event( int $post_id, string $type ): void {
	if ( function_exists( '\Oria\Core\Analytics\record' ) && ! user_can( get_current_user_id(), 'edit_post', $post_id ) ) {
		\Oria\Core\Analytics\record( $post_id, $type );
	}
}

/**
 * A job's or shift's numbers for the employer: views, applications,
 * application rate, apply clicks (external), saves -- last 30 days, except
 * applications, which are all-time for the post.
 *
 * @return array{views:int, apps:int, rate:string, clicks:int, saves:int}
 */
function post_stats( int $id ): array {
	$views = stat( $id, 'view' );
	$apps  = Store\counts( array( $id ) )[ $id ]['n'] ?? 0;
	return array(
		'views'  => $views,
		'apps'   => (int) $apps,
		'rate'   => $views ? round( 100 * $apps / $views ) . '%' : '—',
		'clicks' => (int) meta( $id, 'apply_clicks', 0 ),
		'saves'  => stat( $id, 'save' ),
	);
}

/** A practitioner's numbers (brief section 66). */
function pro_stats( int $pro, int $user_id ): array {
	$apps = Store\apps_by_user( $user_id );
	return array(
		'views'     => stat( $pro, 'view' ),
		'employers' => stat( $pro, 'empview' ),
		'invites'   => Store\invites_for_profile( $pro ),
		'apps'      => count( array_filter( $apps, static fn( $a ) => 'job' === $a['kind'] ) ),
		'shifts'    => count( array_filter( $apps, static fn( $a ) => 'shift' === $a['kind'] && 'confirmed' === $a['status'] ) ),
	);
}

/* ---------------------------------------------------- internal linking */

/**
 * Directory category slug => profession slug, for "Yoga teaching jobs" on
 * a category page (brief section 62). Only pairs that are the same work.
 */
const CATEGORY_PROFESSION = array(
	'yoga'        => 'yoga-teacher',
	'pilates'     => 'pilates-instructor',
	'massage'     => 'massage-therapist',
	'bodywork'    => 'massage-therapist',
	'fitness'     => 'personal-trainer',
	'meditation'  => 'meditation-teacher',
	'breathwork'  => 'breathwork-facilitator',
	'spa'         => 'spa-therapist',
	'naturopathy' => 'naturopath',
	'nutrition'   => 'nutritionist',
	'sound'       => 'sound-healing-practitioner',
	'reiki'       => 'reiki-practitioner',
	'counselling' => 'counsellor',
	'sauna'       => 'sauna-recovery-attendant',
	'recovery'    => 'recovery-coach',
);

/** [label, url, count] for a category page's jobs link, or null when nothing is open. */
function category_jobs_link( string $category_slug ): ?array {
	$prof = CATEGORY_PROFESSION[ $category_slug ] ?? '';
	$term = '' !== $prof ? get_term_by( 'slug', $prof, PROFESSION ) : null;
	if ( ! $term ) {
		return null;
	}
	$n = open_count( JOB, array( 'profession' => $prof ) ) + open_count( SHIFT, array( 'profession' => $prof ) );
	if ( ! $n ) {
		return null;
	}
	/* translators: %s: profession */
	return array( sprintf( __( '%s jobs and shifts', 'oria' ), $term->name ), list_url( 'jobs', '', $prof ), $n );
}

/** Open jobs and shifts filed in an area (and below it), newest first. */
function in_area( \WP_Term $area, int $n = 4 ): array {
	$ids = get_posts(
		array(
			'post_type'   => array( JOB, SHIFT ),
			'post_status' => 'publish',
			'numberposts' => 30,
			'fields'      => 'ids',
			'tax_query'   => array( array( 'taxonomy' => 'area', 'terms' => (int) $area->term_id, 'include_children' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return array_slice( array_values( array_filter( array_map( 'intval', $ids ), __NAMESPACE__ . '\is_open' ) ), 0, $n );
}
