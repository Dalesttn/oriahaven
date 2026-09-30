<?php
/**
 * Work in Wellness: the rules.
 *
 * Everything a page needs to decide -- is this job open, what does its pay
 * say, is this profile complete enough to index, which practitioners suit
 * this shift -- is decided here and only here, so a card, a single page, an
 * email and the schema never disagree about the same record.
 *
 * Matching is deterministic (brief section 23): points for a shared
 * profession, skills, place and availability. No model, nothing a person
 * could not recompute by hand.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ----------------------------------------------------------- open / closed */

/** Why a job or shift is closed ('' when open): expired, filled or ended. */
function closed_reason( int $post_id ): string {
	$type = get_post_type( $post_id );
	$flag = (string) meta( $post_id, 'closed' );
	if ( '' !== $flag ) {
		return $flag;
	}
	if ( JOB === $type ) {
		$exp = (int) meta( $post_id, 'expires', 0 );
		return ( $exp && $exp < time() ) ? 'expired' : '';
	}
	if ( SHIFT === $type ) {
		$end = shift_end_ts( $post_id );
		return ( $end && $end < time() ) ? 'ended' : '';
	}
	return '';
}

function is_open( int $post_id ): bool {
	return 'publish' === get_post_status( $post_id ) && '' === closed_reason( $post_id );
}

/** A shift's start and end as timestamps in the site's timezone. */
function shift_start_ts( int $id ): int {
	$d = (string) meta( $id, 'date' );
	$t = (string) meta( $id, 'start', '00:00' );
	if ( '' === $d ) {
		return 0;
	}
	$dt = date_create_immutable( $d . ' ' . $t, wp_timezone() );
	return $dt ? $dt->getTimestamp() : 0;
}

function shift_end_ts( int $id ): int {
	$d = (string) meta( $id, 'date' );
	$t = (string) meta( $id, 'end', '23:59' );
	if ( '' === $d ) {
		return 0;
	}
	$dt = date_create_immutable( $d . ' ' . $t, wp_timezone() );
	$ts = $dt ? $dt->getTimestamp() : 0;
	// An end before the start is an overnight shift.
	return ( $ts && $ts < shift_start_ts( $id ) ) ? $ts + DAY_IN_SECONDS : $ts;
}

/**
 * How urgent a shift reads right now. The employer's choice is a floor; the
 * clock can only raise it (a "normal" shift starting in five hours is urgent
 * whatever the form said).
 */
function urgency( int $id ): string {
	$set   = (string) meta( $id, 'urgency', 'normal' );
	$start = shift_start_ts( $id );
	if ( ! $start ) {
		return $set;
	}
	$left  = $start - time();
	$today = wp_date( 'Y-m-d' ) === wp_date( 'Y-m-d', $start );
	$clock = $today ? 'today' : ( $left <= 2 * DAY_IN_SECONDS ? '48h' : ( $left <= 7 * DAY_IN_SECONDS ? 'week' : 'normal' ) );
	$rank  = array_flip( array_keys( URGENCY ) );
	return $rank[ $clock ] > ( $rank[ $set ] ?? 0 ) ? $clock : $set;
}

function is_featured( int $id ): bool {
	return (int) meta( $id, 'featured_until', 0 ) > time();
}

/* -------------------------------------------------------------- labelling */

/** "$55–$70 per class", "$85,000–$95,000 per year", "Negotiable", or ''. Never invents a figure. */
function pay_label( int $id ): string {
	$unit = (string) meta( $id, 'pay_unit' );
	if ( SHIFT === get_post_type( $id ) ) {
		$min = (float) meta( $id, 'pay_amount', 0 );
		$max = 0.0;
	} else {
		$min = (float) meta( $id, 'pay_min', 0 );
		$max = (float) meta( $id, 'pay_max', 0 );
	}
	if ( 'negotiable' === $unit && ! $min ) {
		return __( 'Negotiable', 'oria' );
	}
	if ( 'commission' === $unit && ! $min ) {
		return __( 'Commission', 'oria' );
	}
	if ( ! $min && ! $max ) {
		return '';
	}
	$fmt  = static fn( float $n ): string => '$' . ( $n >= 1000 ? number_format( $n ) : rtrim( rtrim( number_format( $n, 2 ), '0' ), '.' ) );
	$amt  = ( $max > $min ) ? $fmt( $min ) . '–' . $fmt( $max ) : $fmt( max( $min, $max ) );
	$tail = PAY_UNITS[ $unit ] ?? '';
	return trim( $amt . ' ' . ( in_array( $unit, array( 'negotiable', 'commission' ), true ) ? '+ ' . $tail : $tail ) );
}

/** "Saturday 8:00–10:00am" style label for a shift. */
function shift_when( int $id ): string {
	$s = shift_start_ts( $id );
	$e = shift_end_ts( $id );
	if ( ! $s ) {
		return '';
	}
	$day   = wp_date( 'Y-m-d' ) === wp_date( 'Y-m-d', $s ) ? __( 'Today', 'oria' ) : ( wp_date( 'Y-m-d', strtotime( '+1 day' ) ) === wp_date( 'Y-m-d', $s ) ? __( 'Tomorrow', 'oria' ) : wp_date( 'l j M', $s ) );
	$clock = static fn( int $t ): string => str_replace( ':00', '', wp_date( 'g:ia', $t ) );
	return $e ? sprintf( '%s %s–%s', $day, $clock( $s ), $clock( $e ) ) : sprintf( '%s %s', $day, $clock( $s ) );
}

/** Shift length in hours, one decimal, or 0. */
function shift_hours( int $id ): float {
	$s = shift_start_ts( $id );
	$e = shift_end_ts( $id );
	return ( $s && $e > $s ) ? round( ( $e - $s ) / HOUR_IN_SECONDS, 1 ) : 0.0;
}

function posted_ago( int $id ): string {
	$t = (int) get_post_time( 'U', true, $id );
	if ( ! $t ) {
		return '';
	}
	$d = (int) floor( ( time() - $t ) / DAY_IN_SECONDS );
	if ( $d < 1 ) {
		return __( 'Posted today', 'oria' );
	}
	/* translators: %d: days */
	return sprintf( _n( 'Posted %d day ago', 'Posted %d days ago', $d, 'oria' ), $d );
}

/** Employer display name: the linked Oria listing's, else the name typed. */
function employer_name( int $id ): string {
	$listing = (int) meta( $id, 'listing', 0 );
	if ( $listing && 'publish' === get_post_status( $listing ) ) {
		return html_entity_decode( get_the_title( $listing ), ENT_QUOTES, 'UTF-8' );
	}
	return (string) meta( $id, 'employer', '' );
}

/** The employer's Oria listing URL, when the job is linked to a published one. */
function employer_url( int $id ): string {
	$listing = (int) meta( $id, 'listing', 0 );
	return ( $listing && 'publish' === get_post_status( $listing ) ) ? (string) get_permalink( $listing ) : '';
}

function place_label( int $id ): string {
	$s = suburb( $id );
	return $s ? html_entity_decode( $s->name, ENT_QUOTES, 'UTF-8' ) : '';
}

function profession_name( int $id ): string {
	$t = term( $id, PROFESSION );
	return $t ? $t->name : '';
}

/* ------------------------------------------------------------- work profile */

/** A user's work profile id, or 0. One per account. */
function profile_of( int $user_id ): int {
	if ( ! $user_id ) {
		return 0;
	}
	$ids = get_posts( array( 'post_type' => PRO, 'author' => $user_id, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'numberposts' => 1, 'fields' => 'ids' ) );
	return $ids ? (int) $ids[0] : 0;
}

/**
 * How complete a profile is, 0-100, and what is missing. The index rule
 * (brief section 14) reads this: thin profiles stay out of search.
 *
 * @return array{score:int, missing:list<string>}
 */
function completeness( int $pro ): array {
	$checks = array(
		'photo'      => array( 15, has_post_thumbnail( $pro ), __( 'a photo', 'oria' ) ),
		'profession' => array( 20, (bool) term( $pro, PROFESSION ), __( 'your profession', 'oria' ) ),
		'short'      => array( 15, strlen( trim( (string) get_post_field( 'post_excerpt', $pro ) ) ) >= 40, __( 'a short bio', 'oria' ) ),
		'bio'        => array( 15, str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $pro ) ) ) >= 60, __( 'a fuller bio (60+ words)', 'oria' ) ),
		'place'      => array( 10, (bool) suburb( $pro ), __( 'your suburb', 'oria' ) ),
		'skills'     => array( 10, (bool) wp_get_post_terms( $pro, SKILL, array( 'fields' => 'ids' ) ), __( 'your skills', 'oria' ) ),
		'quals'      => array( 10, '' !== trim( (string) meta( $pro, 'quals' ) ), __( 'your qualifications', 'oria' ) ),
		'avail'      => array( 5, (bool) meta( $pro, 'available_for', array() ), __( 'what you are available for', 'oria' ) ),
	);
	$score   = 0;
	$missing = array();
	foreach ( $checks as $c ) {
		if ( $c[1] ) {
			$score += $c[0];
		} else {
			$missing[] = $c[2];
		}
	}
	return array( 'score' => $score, 'missing' => $missing );
}

/** Index only public, published, 80%+ complete profiles. */
function profile_indexable( int $pro ): bool {
	return 'publish' === get_post_status( $pro ) && 'public' === visibility( $pro ) && completeness( $pro )['score'] >= 80;
}

/** public | employers | hidden */
function visibility( int $pro ): string {
	$v = (string) meta( $pro, 'visibility', 'public' );
	return in_array( $v, array( 'public', 'employers', 'hidden' ), true ) ? $v : 'public';
}

/** Whether the current visitor may see this profile at all. */
function profile_visible_to( int $pro, int $viewer ): bool {
	if ( 'publish' !== get_post_status( $pro ) ) {
		return $viewer && ( (int) get_post_field( 'post_author', $pro ) === $viewer || user_can( $viewer, 'manage_options' ) );
	}
	$v = visibility( $pro );
	if ( 'public' === $v ) {
		return true;
	}
	if ( $viewer && ( (int) get_post_field( 'post_author', $pro ) === $viewer || user_can( $viewer, 'manage_options' ) ) ) {
		return true;
	}
	return 'employers' === $v && $viewer && is_employer( $viewer );
}

/** An employer: owns an Oria listing or has posted a job or shift. */
function is_employer( int $user_id ): bool {
	if ( ! $user_id ) {
		return false;
	}
	if ( function_exists( '\Oria\Core\ListingEditor\listing_for' ) && \Oria\Core\ListingEditor\listing_for( $user_id ) ) {
		return true;
	}
	return (bool) get_posts( array( 'post_type' => array( JOB, SHIFT ), 'author' => $user_id, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
}

/** Only the author (or an admin) may manage a job, shift or its applicants. */
function owns( int $post_id, int $user_id ): bool {
	return $user_id && ( (int) get_post_field( 'post_author', $post_id ) === $user_id || user_can( $user_id, 'manage_options' ) );
}

/** Verified badges an admin has ticked. */
function badges( int $pro ): array {
	$set = (array) meta( $pro, 'verified', array() );
	return array_values( array_intersect_key( VERIFICATIONS, array_flip( $set ) ) );
}

/* ------------------------------------------------------------------ places */

/**
 * A suburb's rough centre: the mean position of the Oria listings filed in
 * it (suburb terms carry no coordinates of their own). Falls back to the
 * region, then the city centre. Cached a week.
 *
 * @return array{0:float,1:float}|null
 */
function centre_of( \WP_Term $area ): ?array {
	$key = 'oria_wk_c_' . $area->term_id;
	$hit = get_transient( $key );
	if ( is_array( $hit ) ) {
		return $hit ?: null;
	}
	global $wpdb;
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT AVG(CAST(lat.meta_value AS DECIMAL(10,6))) AS lat, AVG(CAST(lng.meta_value AS DECIMAL(10,6))) AS lng, COUNT(*) AS n
			 FROM {$wpdb->term_relationships} tr
			 JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'listing' AND p.post_status = 'publish'
			 JOIN {$wpdb->postmeta} lat ON lat.post_id = p.ID AND lat.meta_key = 'geo_lat'
			 JOIN {$wpdb->postmeta} lng ON lng.post_id = p.ID AND lng.meta_key = 'geo_lng'
			 WHERE tr.term_taxonomy_id = %d",
			(int) $area->term_taxonomy_id
		),
		ARRAY_A
	);
	$out = null;
	if ( $row && (int) $row['n'] > 0 ) {
		$out = array( (float) $row['lat'], (float) $row['lng'] );
	} elseif ( $area->parent ) {
		$parent = get_term( (int) $area->parent, 'area' );
		$out    = $parent instanceof \WP_Term ? centre_of( $parent ) : null;
	} elseif ( function_exists( '\Oria\Core\Cities\get' ) ) {
		$city = \Oria\Core\Cities\get( $area->slug );
		$out  = isset( $city['centre'] ) && is_array( $city['centre'] ) ? array( (float) $city['centre'][0], (float) $city['centre'][1] ) : null;
	}
	set_transient( $key, $out ?: array(), WEEK_IN_SECONDS );
	return $out;
}

/** Kilometres between two posts' suburbs, or null when either is unplaced. */
function km_between( int $a, int $b ): ?float {
	$sa = suburb( $a );
	$sb = suburb( $b );
	if ( ! $sa || ! $sb ) {
		return null;
	}
	if ( $sa->term_id === $sb->term_id ) {
		return 0.0;
	}
	$ca = centre_of( $sa );
	$cb = centre_of( $sb );
	if ( ! $ca || ! $cb || ! function_exists( '\Oria\Core\Geo\distance_km' ) ) {
		return null;
	}
	return round( \Oria\Core\Geo\distance_km( $ca, $cb ), 1 );
}

/* --------------------------------------------------------------- matching */

/**
 * Whether a work profile suits a shift (brief section 22): same profession,
 * open to cover or casual work, free at that time of the week, within their
 * travel radius. Profiles set to hidden, or "no unsolicited contact", never
 * match -- matching is a form of contact.
 */
function pro_suits_shift( int $pro, int $shift ): bool {
	if ( 'publish' !== get_post_status( $pro ) || 'hidden' === visibility( $pro ) || 'none' === (string) meta( $pro, 'contact_pref', 'allow' ) ) {
		return false;
	}
	$need = term( $shift, PROFESSION );
	if ( ! $need || ! has_term( (int) $need->term_id, PROFESSION, $pro ) ) {
		return false;
	}
	$for = (array) meta( $pro, 'available_for', array() );
	if ( ! array_intersect( $for, array( 'cover', 'casual' ) ) ) {
		return false;
	}
	$when = (array) meta( $pro, 'availability', array() );
	if ( in_array( 'not', $when, true ) ) {
		return false;
	}
	$start = shift_start_ts( $shift );
	if ( $start && array_intersect( $when, array( 'weekends', 'evenings', 'mornings' ) ) && ! in_array( 'now', $when, true ) ) {
		$dow  = (int) wp_date( 'N', $start );
		$hour = (int) wp_date( 'G', $start );
		$fits = ( $dow >= 6 && in_array( 'weekends', $when, true ) )
			|| ( $hour >= 17 && in_array( 'evenings', $when, true ) )
			|| ( $hour < 12 && in_array( 'mornings', $when, true ) );
		if ( ! $fits ) {
			return false;
		}
	}
	$km     = km_between( $pro, $shift );
	$radius = (int) meta( $pro, 'radius', 15 );
	return null === $km || $km <= max( 1, $radius );
}

/** Profile ids that suit a shift. */
function pros_for_shift( int $shift, int $limit = 200 ): array {
	$need = term( $shift, PROFESSION );
	if ( ! $need ) {
		return array();
	}
	$ids = get_posts(
		array(
			'post_type'   => PRO,
			'post_status' => 'publish',
			'numberposts' => 500,
			'fields'      => 'ids',
			'tax_query'   => array( array( 'taxonomy' => PROFESSION, 'terms' => (int) $need->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$out = array();
	foreach ( $ids as $id ) {
		if ( pro_suits_shift( (int) $id, $shift ) ) {
			$out[] = (int) $id;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
	}
	return $out;
}

/**
 * Points for a profile against a job or shift. 0 means "no shared
 * profession", which is never shown as a match.
 */
function score( int $pro, int $post ): int {
	$need = term( $post, PROFESSION );
	if ( ! $need || ! has_term( (int) $need->term_id, PROFESSION, $pro ) ) {
		return 0;
	}
	$pts    = 10;
	$skills = array_intersect(
		wp_get_post_terms( $pro, SKILL, array( 'fields' => 'ids' ) ),
		wp_get_post_terms( $post, SKILL, array( 'fields' => 'ids' ) )
	);
	$pts += 2 * count( $skills );
	$km   = km_between( $pro, $post );
	if ( null !== $km ) {
		$radius = (int) meta( $pro, 'radius', 15 );
		$pts   += $km <= $radius ? 4 : ( $km <= 2 * $radius ? 1 : -3 );
	}
	if ( JOB === get_post_type( $post ) ) {
		$emp  = term( $post, EMPLOYMENT );
		$pref = (array) meta( $pro, 'employment_pref', array() );
		if ( $emp && in_array( $emp->slug, $pref, true ) ) {
			$pts += 3;
		}
		$for = (array) meta( $pro, 'available_for', array() );
		if ( $emp && in_array( $emp->slug, array( 'full-time', 'part-time' ), true ) && in_array( 'permanent', $for, true ) ) {
			$pts += 2;
		}
		if ( $emp && 'casual' === $emp->slug && in_array( 'casual', $for, true ) ) {
			$pts += 2;
		}
	}
	return $pts;
}

/** Open jobs that match a profile, best first. */
function jobs_for_pro( int $pro, int $n = 6 ): array {
	$prof = wp_get_post_terms( $pro, PROFESSION, array( 'fields' => 'ids' ) );
	if ( ! $prof ) {
		return array();
	}
	$ids    = get_posts( array( 'post_type' => JOB, 'post_status' => 'publish', 'numberposts' => 100, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => PROFESSION, 'terms' => $prof ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$scored = array();
	foreach ( $ids as $id ) {
		if ( is_open( (int) $id ) && ( $s = score( $pro, (int) $id ) ) > 0 ) {
			$scored[ (int) $id ] = $s;
		}
	}
	arsort( $scored );
	return array_slice( array_keys( $scored ), 0, $n );
}

/** Profiles an employer might want for a job, best first (visible to employers only). */
function pros_for_job( int $job, int $n = 6 ): array {
	$need = term( $job, PROFESSION );
	if ( ! $need ) {
		return array();
	}
	$ids    = get_posts( array( 'post_type' => PRO, 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => PROFESSION, 'terms' => (int) $need->term_id ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$scored = array();
	foreach ( $ids as $id ) {
		if ( 'hidden' === visibility( (int) $id ) || 'none' === (string) meta( (int) $id, 'contact_pref', 'allow' ) ) {
			continue;
		}
		$scored[ (int) $id ] = score( (int) $id, $job );
	}
	arsort( $scored );
	return array_slice( array_keys( array_filter( $scored ) ), 0, $n );
}

/* ----------------------------------------------------------------- search */

/**
 * Build the query for a jobs / shifts / profiles list from filters.
 *
 * $f keys: q, profession (slug), area (slug), type (employment slug),
 * paid (1), remote (1), when (today|weekend|week), sort (new|pay|relevant),
 * city (slug), page. Everything is server-side (brief section 93).
 */
function query( string $post_type, array $f, int $per = 20 ): \WP_Query {
	$tax  = array();
	$meta = array();

	if ( ! empty( $f['profession'] ) ) {
		$t = get_term_by( 'slug', (string) $f['profession'], PROFESSION );
		if ( $t ) {
			$tax[] = array( 'taxonomy' => PROFESSION, 'terms' => (int) $t->term_id, 'include_children' => true );
		}
	}
	$area = ! empty( $f['area'] ) ? (string) $f['area'] : (string) ( $f['city'] ?? '' );
	if ( '' !== $area ) {
		$tax[] = array( 'taxonomy' => 'area', 'field' => 'slug', 'terms' => $area, 'include_children' => true );
	}
	if ( JOB === $post_type && ! empty( $f['type'] ) ) {
		$tax[] = array( 'taxonomy' => EMPLOYMENT, 'field' => 'slug', 'terms' => (string) $f['type'] );
	}
	if ( ! empty( $f['skill'] ) ) {
		$tax[] = array( 'taxonomy' => SKILL, 'field' => 'slug', 'terms' => (string) $f['skill'] );
	}

	if ( JOB === $post_type ) {
		$meta[] = array( 'key' => key( 'closed' ), 'compare' => 'NOT EXISTS' );
		$meta[] = array(
			'relation' => 'OR',
			array( 'key' => key( 'expires' ), 'compare' => 'NOT EXISTS' ),
			array( 'key' => key( 'expires' ), 'value' => time(), 'compare' => '>', 'type' => 'NUMERIC' ),
		);
		if ( ! empty( $f['paid'] ) ) {
			$meta[] = array(
				'relation' => 'OR',
				array( 'key' => key( 'pay_min' ), 'value' => 0, 'compare' => '>', 'type' => 'DECIMAL' ),
				array( 'key' => key( 'pay_max' ), 'value' => 0, 'compare' => '>', 'type' => 'DECIMAL' ),
			);
		}
		if ( ! empty( $f['remote'] ) ) {
			$meta[] = array( 'key' => key( 'arrangement' ), 'value' => array( 'remote', 'hybrid' ), 'compare' => 'IN' );
		}
		if ( ! empty( $f['weekend'] ) ) {
			$meta[] = array( 'key' => key( 'weekend' ), 'value' => '1' );
		}
		if ( ! empty( $f['evening'] ) ) {
			$meta[] = array( 'key' => key( 'evening' ), 'value' => '1' );
		}
	}

	if ( SHIFT === $post_type ) {
		$meta[] = array( 'key' => key( 'closed' ), 'compare' => 'NOT EXISTS' );
		$today  = wp_date( 'Y-m-d' );
		$range  = array( $today, wp_date( 'Y-m-d', strtotime( '+120 days' ) ) );
		if ( 'today' === ( $f['when'] ?? '' ) ) {
			$range = array( $today, $today );
		} elseif ( 'weekend' === ( $f['when'] ?? '' ) ) {
			$sat   = (int) wp_date( 'N' ) >= 6 ? $today : wp_date( 'Y-m-d', strtotime( 'next saturday' ) );
			$range = array( max( $today, $sat ), wp_date( 'Y-m-d', strtotime( $sat . ' +1 day' ) ) );
		} elseif ( 'week' === ( $f['when'] ?? '' ) ) {
			$range = array( $today, wp_date( 'Y-m-d', strtotime( '+7 days' ) ) );
		}
		$meta[] = array( 'key' => key( 'date' ), 'value' => $range, 'compare' => 'BETWEEN', 'type' => 'DATE' );
	}

	if ( PRO === $post_type ) {
		$viewer  = get_current_user_id();
		$allowed = ( $viewer && is_employer( $viewer ) ) || current_user_can( 'manage_options' ) ? array( 'public', 'employers' ) : array( 'public' );
		$meta[]  = array(
			'relation' => 'OR',
			array( 'key' => key( 'visibility' ), 'compare' => 'NOT EXISTS' ),
			array( 'key' => key( 'visibility' ), 'value' => $allowed, 'compare' => 'IN' ),
		);
		if ( ! empty( $f['cover'] ) ) {
			$meta[] = array( 'key' => key( 'available_for' ), 'value' => '"cover"', 'compare' => 'LIKE' );
		}
	}

	$args = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => $per,
		'paged'          => max( 1, (int) ( $f['page'] ?? 1 ) ),
		's'              => mb_substr( trim( (string) ( $f['q'] ?? '' ) ), 0, 80 ),
		'tax_query'      => $tax ? array_merge( array( 'relation' => 'AND' ), $tax ) : array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_query'     => $meta ? array_merge( array( 'relation' => 'AND' ), $meta ) : array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		'no_found_rows'  => false,
	);

	$sort = (string) ( $f['sort'] ?? '' );
	if ( SHIFT === $post_type ) {
		$args['meta_key'] = key( 'date' ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['orderby']  = array( 'meta_value' => 'ASC', 'date' => 'DESC' );
	} elseif ( 'pay' === $sort && JOB === $post_type ) {
		$args['meta_key'] = key( 'pay_rank' ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
	} elseif ( '' === $args['s'] || 'new' === $sort ) {
		$args['orderby'] = array( 'date' => 'DESC' );
	}

	$q = new \WP_Query( $args );

	// Featured first on page one of an unsorted list (brief section 40).
	if ( JOB === $post_type && '' === $sort && 1 === (int) $args['paged'] && $q->posts ) {
		usort( $q->posts, static fn( $a, $b ) => (int) is_featured( $b->ID ) <=> (int) is_featured( $a->ID ) );
	}
	return $q;
}

/**
 * A comparable annual figure for "highest paying" sort, from whatever the
 * employer gave. Stored on save as pay_rank; never shown.
 */
function pay_rank( int $id ): float {
	$amt  = max( (float) meta( $id, 'pay_min', 0 ), (float) meta( $id, 'pay_max', 0 ) );
	$unit = (string) meta( $id, 'pay_unit' );
	$mult = array( 'hour' => 1800, 'class' => 800, 'shift' => 300, 'client' => 900, 'year' => 1 );
	return $amt * ( $mult[ $unit ] ?? 0 );
}

/** Businesses with open jobs or shifts: listing id => count. */
function hiring_now( int $limit = 8 ): array {
	$ids = get_posts( array( 'post_type' => array( JOB, SHIFT ), 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids', 'meta_query' => array( array( 'key' => key( 'listing' ), 'compare' => 'EXISTS' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$out = array();
	foreach ( $ids as $id ) {
		if ( ! is_open( (int) $id ) ) {
			continue;
		}
		$l = (int) meta( (int) $id, 'listing', 0 );
		if ( $l && 'publish' === get_post_status( $l ) ) {
			$out[ $l ] = ( $out[ $l ] ?? 0 ) + 1;
		}
	}
	arsort( $out );
	return array_slice( $out, 0, $limit, true );
}

/** Open jobs and shifts for one Oria listing ("Work here"). */
function for_listing( int $listing ): array {
	$ids = get_posts( array( 'post_type' => array( JOB, SHIFT ), 'post_status' => 'publish', 'numberposts' => 20, 'fields' => 'ids', 'meta_query' => array( array( 'key' => key( 'listing' ), 'value' => $listing ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return array_values( array_filter( array_map( 'intval', $ids ), __NAMESPACE__ . '\is_open' ) );
}

/** Open job count for a profession in an area, for the 3-job index rule (brief section 61). */
function open_count( string $post_type, array $f ): int {
	$q = query( $post_type, $f, 1 );
	return (int) $q->found_posts;
}

/* --------------------------------------------------------------- notices */

/**
 * The sentence for a ?wk= code a form bounced back with, and whether it is
 * good news. Unknown codes say nothing.
 *
 * @return array{text:string, type:string}|null
 */
function notice(): ?array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$code = sanitize_key( (string) ( $_GET['wk'] ?? '' ) );
	$ok   = array(
		'profile_saved'  => __( 'Your work profile is saved.', 'oria' ),
		'posted_live'    => __( 'Posted — it is live now.', 'oria' ),
		'posted_pending' => __( 'Thanks — we check every new employer, and your listing will be live within one business day. We will email you.', 'oria' ),
		'draft_saved'    => __( 'Draft saved. Come back any time to finish it.', 'oria' ),
		'applied'        => __( 'Application sent. You can follow it in My Oria → Work.', 'oria' ),
		'available_sent' => __( 'Sent — the business can now see you are available and offer you the shift.', 'oria' ),
		'status_saved'   => __( 'Updated.', 'oria' ),
		'accepted'       => __( 'Confirmed. The business has been told.', 'oria' ),
		'declined'       => __( 'Declined. The business has been told.', 'oria' ),
		'withdrawn'      => __( 'Application withdrawn.', 'oria' ),
		'closed_ok'      => __( 'Closed. It now shows as closed and takes no more applications.', 'oria' ),
		'reopened'       => __( 'Reopened for another 30 days.', 'oria' ),
		'alert_on'       => __( 'Alert set. We will email you when something matches.', 'oria' ),
		'alert_off'      => __( 'Alert switched off.', 'oria' ),
		'saved'          => __( 'Saved to My Oria → Work.', 'oria' ),
		'unsaved'        => __( 'Removed from your saved list.', 'oria' ),
		'reported'       => __( 'Thank you. A person at Oria Haven will look at this.', 'oria' ),
		'claim_sent'     => __( 'Thanks — we will check and hand the job over to your account, usually within a business day.', 'oria' ),
	);
	$bad = array(
		'expired'       => __( 'That form had expired. Please try again.', 'oria' ),
		'error'         => __( 'Something went wrong. Please try again.', 'oria' ),
		'need_name'     => __( 'Please add your name.', 'oria' ),
		'need_title'    => __( 'Please add a title.', 'oria' ),
		'need_more'     => __( 'Please fill in the profession, suburb and a description of at least a few sentences — and for a shift, the date and start time.', 'oria' ),
		'photo'         => __( 'Your photo needs to be a JPEG, PNG or WebP under 5 MB.', 'oria' ),
		'cv'            => __( 'Your CV needs to be a PDF or Word (.docx) file under 5 MB.', 'oria' ),
		'closed'        => __( 'This has closed and is no longer taking applications.', 'oria' ),
		'own'           => __( 'That one is yours — you cannot apply to it.', 'oria' ),
		'already'       => __( 'You have already responded to this one. Follow it in My Oria → Work.', 'oria' ),
		'profile_first' => __( 'Businesses decide on your work profile, so set one up first — it takes a couple of minutes, and you will come straight back.', 'oria' ),
		'alert_limit'   => __( 'You can have up to 10 alerts. Switch one off to add another.', 'oria' ),
	);
	if ( isset( $ok[ $code ] ) ) {
		return array( 'text' => $ok[ $code ], 'type' => 'ok' );
	}
	if ( isset( $bad[ $code ] ) ) {
		return array( 'text' => $bad[ $code ], 'type' => 'error' );
	}
	return null;
}

/** Nonce + hidden fields every work form carries. */
function form_fields( string $action ): string {
	return '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">' . wp_nonce_field( $action, '_wk', true, false );
}
