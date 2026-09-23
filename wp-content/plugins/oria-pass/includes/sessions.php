<?php
/**
 * Sessions: the places a provider has opened to the Pass.
 *
 * A session is one sitting, not a timetable. That is the whole product
 * argument made concrete -- a studio gives two places on a quiet Tuesday
 * rather than handing over their calendar, and keeps everything else.
 *
 * Nothing here creates a public URL. A session is short-lived and thin,
 * and a few hundred of them would be a few hundred pages Google would be
 * right to ignore; they appear inside listing, category and discovery
 * pages instead. The brief is explicit about that and so is this file.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Sessions;

use Oria\Pass\Db;
use Oria\Pass\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where a session can be in its life. */
const STATUSES = array( 'draft', 'active', 'sold_out', 'completed', 'cancelled', 'paused' );

function get( int $id ): ?object {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db\sessions() . ' WHERE id = %d', $id ) );

	return $row ?: null;
}

/** Places left for Pass members. Never negative, whatever the counters say. */
function places_left( object $session ): int {
	return max( 0, (int) $session->pass_capacity - (int) $session->booked_count );
}

/** Can somebody still take a place on this? */
function is_bookable( object $session ): bool {
	if ( 'active' !== (string) $session->status ) {
		return false;
	}
	if ( places_left( $session ) < 1 ) {
		return false;
	}

	return ! has_started( $session );
}

/**
 * Has it begun?
 *
 * start_at is a naive local string. strtotime reads it as UTC on this
 * install, so pairing it with the wrong formatter shifts it eight hours --
 * the trap the events code had to be fixed for. date_create_immutable with
 * wp_timezone() is the form that does not lie.
 */
function has_started( object $session ): bool {
	$start = date_create_immutable( (string) $session->start_at, wp_timezone() );

	return ! $start || $start->getTimestamp() <= time();
}

/** The moment after which cancelling no longer returns the credits. */
function cutoff( object $session ): ?\DateTimeImmutable {
	$start = date_create_immutable( (string) $session->start_at, wp_timezone() );
	if ( ! $start ) {
		return null;
	}

	$hours = (int) ( $session->cancel_cutoff_hours ?: Settings\get( 'cancel_cutoff_hrs' ) );

	return $start->modify( '-' . $hours . ' hours' );
}

/** Is a refund still due if they cancel right now? */
function refundable( object $session ): bool {
	$cutoff = cutoff( $session );

	return $cutoff && time() < $cutoff->getTimestamp();
}

/**
 * Sessions a member could book, soonest first.
 *
 * @param array $args listing_id, category, limit, include_full
 * @return array<int, object>
 */
function upcoming( array $args = array() ): array {
	global $wpdb;

	$where  = array( "status = 'active'", 'start_at > %s' );
	$params = array( current_time( 'mysql' ) );

	if ( ! empty( $args['listing_id'] ) ) {
		$where[]  = 'listing_id = %d';
		$params[] = (int) $args['listing_id'];
	}
	if ( ! empty( $args['category'] ) ) {
		$where[]  = 'category = %s';
		$params[] = (string) $args['category'];
	}
	if ( empty( $args['include_full'] ) ) {
		$where[] = 'booked_count < pass_capacity';
	}

	$params[] = (int) ( $args['limit'] ?? 20 );

	$sql = 'SELECT * FROM ' . Db\sessions() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY start_at ASC LIMIT %d';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Where a Pass can actually be spent.
 *
 * One row per studio rather than per session, because a member deciding
 * where to go is choosing a place first and a time second. The soonest
 * session and the cheapest are carried along, so the row can answer "is
 * there anything this week, and can I afford it" without a second query.
 *
 * Only places with a bookable session are returned. A studio that has run
 * out of places this month is not somewhere you can use your Pass today,
 * and listing it would be an invitation to a closed door.
 *
 * @return array<int, object>
 */
function partners( int $limit = 12 ): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT listing_id,
				COUNT(*) AS sessions,
				MIN(start_at) AS soonest,
				MIN(credits_required) AS from_credits
			FROM ' . Db\sessions() . "
			WHERE status = 'active' AND start_at > %s AND booked_count < pass_capacity
			GROUP BY listing_id
			ORDER BY soonest ASC
			LIMIT %d",
			current_time( 'mysql' ),
			$limit
		)
	);
}

/** How often a session comes round. */
const REPEATS = array(
	'once'   => 'Just once',
	'daily'  => 'Every day',
	'weekly' => 'Every week',
);

/** Nobody needs three years of Tuesdays, and a typo should not make them. */
const SERIES_MAX = 60;

/**
 * A repeating session, written out one occurrence at a time.
 *
 * Deliberately real rows rather than a rule evaluated later. Everything
 * the Pass already does -- places, bookings, the cancellation window,
 * reminders, payouts, calling one off -- happens to a session, and a rule
 * would mean each of those learning to pretend. Writing them out means a
 * studio can cancel one wet Tuesday without touching the other eleven.
 *
 * They share a series_id so the set can still be spoken about as one
 * thing. The first failure stops the run: a half-written series is easier
 * to understand than one with a gap in the middle nobody can explain.
 *
 * @return array{ids:array<int,int>, error:?\WP_Error}
 */
function save_series( array $in, string $repeat, string $until_date ): array {
	$repeat = isset( REPEATS[ $repeat ] ) ? $repeat : 'once';
	$start  = date_create_immutable( (string) ( $in['start_at'] ?? '' ), wp_timezone() );

	if ( 'once' === $repeat || ! $start ) {
		$id = save( $in );

		return is_wp_error( $id )
			? array( 'ids' => array(), 'error' => $id )
			: array( 'ids' => array( (int) $id ), 'error' => null );
	}

	$until = date_create_immutable( $until_date . ' 23:59:59', wp_timezone() );
	if ( ! $until || $until->getTimestamp() < $start->getTimestamp() ) {
		// No end date given, or one before the start: a fortnight is plenty.
		$until = $start->modify( '+14 days' );
	}

	$step   = 'daily' === $repeat ? '+1 day' : '+1 week';
	$series = substr( md5( uniqid( (string) $start->getTimestamp(), true ) ), 0, 32 );
	// Each occurrence keeps the first one's length, if it was given one.
	$finish = date_create_immutable( (string) ( $in['end_at'] ?? '' ), wp_timezone() );
	$length = $finish ? $finish->getTimestamp() - $start->getTimestamp() : 0;

	$ids  = array();
	$when = $start;

	while ( $when->getTimestamp() <= $until->getTimestamp() && count( $ids ) < SERIES_MAX ) {
		$one              = $in;
		$one['start_at']  = $when->format( 'Y-m-d H:i:s' );
		$one['end_at']    = $length > 0 ? $when->modify( '+' . $length . ' seconds' )->format( 'Y-m-d H:i:s' ) : '';
		$one['series_id'] = $series;

		$id = save( $one );

		if ( is_wp_error( $id ) ) {
			return array( 'ids' => $ids, 'error' => $id );
		}

		$ids[] = (int) $id;
		$when  = $when->modify( $step );
	}

	return array( 'ids' => $ids, 'error' => null );
}

/**
 * The rest of a series, from a given session onwards.
 *
 * Only what is still to come: a studio ending a weekly class is saying
 * something about next week, not about the ones that already ran.
 *
 * @return array<int, object>
 */
function series_after( object $session, bool $include_self = true ): array {
	global $wpdb;

	if ( '' === (string) $session->series_id ) {
		return $include_self ? array( $session ) : array();
	}

	$from = $include_self ? (string) $session->start_at : (string) $session->start_at . '.999999';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . Db\sessions() . " WHERE series_id = %s AND start_at >= %s AND status <> 'cancelled' ORDER BY start_at ASC",
			(string) $session->series_id,
			$from
		)
	);
}

/**
 * A month of a studio's bookable days, for the calendar on its listing.
 *
 * Keyed by date so the grid can ask "is there anything on the 14th" in one
 * lookup rather than filtering a list per cell. Carries the cheapest way
 * in, because a member scanning a month is deciding what they can afford
 * as much as when they are free.
 *
 * Today counts only from now on: a class at 6am is not bookable at noon,
 * and a calendar saying otherwise sends somebody to a closed door.
 *
 * @return array<string, array{count:int, from:int}>
 */
function month_days( int $listing_id, string $month ): array {
	global $wpdb;

	if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
		$month = wp_date( 'Y-m' );
	}

	$first = date_create_immutable( $month . '-01 00:00:00', wp_timezone() );
	if ( ! $first ) {
		return array();
	}

	$from = max( $first->format( 'Y-m-d H:i:s' ), current_time( 'mysql' ) );
	$to   = $first->modify( 'first day of next month' )->format( 'Y-m-d H:i:s' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT DATE(start_at) AS day, COUNT(*) AS count, MIN(credits_required) AS cheapest
			FROM ' . Db\sessions() . "
			WHERE listing_id = %d AND status = 'active'
				AND booked_count < pass_capacity
				AND start_at >= %s AND start_at < %s
			GROUP BY DATE(start_at)",
			$listing_id,
			$from,
			$to
		)
	);

	$days = array();
	foreach ( $rows as $row ) {
		$days[ (string) $row->day ] = array( 'count' => (int) $row->count, 'from' => (int) $row->cheapest );
	}

	return $days;
}

/**
 * One day's bookable sessions at a studio.
 *
 * @return array<int, object>
 */
function on_day( int $listing_id, string $day ): array {
	global $wpdb;

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
		return array();
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . Db\sessions() . " WHERE listing_id = %d AND status = 'active'
				AND DATE(start_at) = %s AND start_at > %s
			ORDER BY start_at ASC",
			$listing_id,
			$day,
			current_time( 'mysql' )
		)
	);
}

/** Everything a provider has on, including what is not yet published. */
function for_listing( int $listing_id, int $limit = 100 ): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . Db\sessions() . ' WHERE listing_id = %d ORDER BY start_at DESC LIMIT %d',
			$listing_id,
			$limit
		)
	);
}

/**
 * Create or update one.
 *
 * Returns the id, or a WP_Error naming what was wrong. The rules are the
 * provider's protection as much as ours: a session that gives away more
 * places than the room holds is a studio's bad afternoon.
 *
 * @return int|\WP_Error
 */
function save( array $in, int $id = 0 ) {
	global $wpdb;

	$listing_id = (int) ( $in['listing_id'] ?? 0 );
	if ( $listing_id < 1 || 'listing' !== get_post_type( $listing_id ) ) {
		return new \WP_Error( 'no_listing', __( 'That session is not attached to a listing.', 'oria' ) );
	}

	$title = trim( (string) ( $in['title'] ?? '' ) );
	if ( '' === $title ) {
		return new \WP_Error( 'no_title', __( 'Give the session a name people will recognise.', 'oria' ) );
	}

	$start = date_create_immutable( (string) ( $in['start_at'] ?? '' ), wp_timezone() );
	if ( ! $start ) {
		return new \WP_Error( 'no_start', __( 'That start time could not be read.', 'oria' ) );
	}
	if ( 0 === $id && $start->getTimestamp() <= time() ) {
		return new \WP_Error( 'past', __( 'That is in the past. Pick a time still to come.', 'oria' ) );
	}

	$end = '' !== (string) ( $in['end_at'] ?? '' ) ? date_create_immutable( (string) $in['end_at'], wp_timezone() ) : null;
	if ( $end && $end->getTimestamp() <= $start->getTimestamp() ) {
		return new \WP_Error( 'end_before_start', __( 'The session cannot end before it starts.', 'oria' ) );
	}

	$total = max( 0, (int) ( $in['total_capacity'] ?? 0 ) );
	$pass  = max( 0, (int) ( $in['pass_capacity'] ?? 0 ) );
	if ( $pass < 1 ) {
		return new \WP_Error( 'no_places', __( 'Open at least one place to Pass members.', 'oria' ) );
	}
	if ( $total > 0 && $pass > $total ) {
		return new \WP_Error( 'too_many', __( 'You cannot offer more Pass places than the session holds.', 'oria' ) );
	}

	$credits = max( 0, (int) ( $in['credits_required'] ?? 0 ) );
	if ( $credits < 1 ) {
		return new \WP_Error( 'no_credits', __( 'Set what this costs in credits.', 'oria' ) );
	}

	$status = in_array( (string) ( $in['status'] ?? '' ), STATUSES, true ) ? (string) $in['status'] : 'draft';
	$now    = current_time( 'mysql' );

	$row = array(
		'listing_id'          => $listing_id,
		'provider_user_id'    => (int) ( $in['provider_user_id'] ?? 0 ),
		'title'               => $title,
		'category'            => sanitize_key( (string) ( $in['category'] ?? '' ) ),
		'start_at'            => $start->format( 'Y-m-d H:i:s' ),
		'end_at'              => $end ? $end->format( 'Y-m-d H:i:s' ) : null,
		'total_capacity'      => $total,
		'pass_capacity'       => $pass,
		'credits_required'    => $credits,
		'provider_payout'     => round( (float) ( $in['provider_payout'] ?? 0 ), 2 ),
		'cancel_cutoff_hours' => max( 0, (int) ( $in['cancel_cutoff_hours'] ?? Settings\get( 'cancel_cutoff_hrs' ) ) ),
		'status'              => $status,
		'series_id'           => sanitize_key( (string) ( $in['series_id'] ?? '' ) ),
		'notes'               => sanitize_textarea_field( (string) ( $in['notes'] ?? '' ) ),
		'updated_at'          => $now,
	);

	if ( $id > 0 ) {
		$existing = get( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'missing', __( 'That session no longer exists.', 'oria' ) );
		}
		// Never below what is already booked: those are real people.
		if ( $pass < (int) $existing->booked_count ) {
			return new \WP_Error(
				'below_booked',
				sprintf(
					/* translators: %d: bookings already taken */
					__( '%d members have already booked this. You cannot offer fewer places than that.', 'oria' ),
					(int) $existing->booked_count
				)
			);
		}

		// An edit is to this occurrence; it never changes which series it is in.
		$row['series_id'] = (string) $existing->series_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( Db\sessions(), $row, array( 'id' => $id ) );

		/*
		 * Moving a session that people have booked is the one edit that
		 * cannot stay between the studio and the database. Somebody has
		 * arranged their evening around the old time, and finding out by
		 * turning up is the worst way to learn. Announced only when the
		 * time actually moved and only while somebody holds a place.
		 */
		$moved = (string) $existing->start_at !== $row['start_at'] || (string) ( $existing->end_at ?? '' ) !== (string) ( $row['end_at'] ?? '' );

		if ( $moved && (int) $existing->booked_count > 0 ) {
			do_action( 'oria_pass_session_moved', get( $id ), $existing );
		}

		return $id;
	}

	$row['booked_count'] = 0;
	$row['created_at']   = $now;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert( Db\sessions(), $row );

	return (int) $wpdb->insert_id;
}

/** Change a session's status without touching its bookings. */
function set_status( int $id, string $status ): bool {
	global $wpdb;

	if ( ! in_array( $status, STATUSES, true ) ) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (bool) $wpdb->update(
		Db\sessions(),
		array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => $id )
	);
}

/** The studio behind a session, for the card and the confirmation. */
function provider_name( object $session ): string {
	$title = get_the_title( (int) $session->listing_id );

	return '' !== $title ? wp_specialchars_decode( $title, ENT_QUOTES ) : __( 'The provider', 'oria' );
}

/** "Thursday · 5:30pm", in Perth time. */
function when( object $session ): string {
	$start = date_create_immutable( (string) $session->start_at, wp_timezone() );
	if ( ! $start ) {
		return '';
	}

	return wp_date( 'D j M · g:ia', $start->getTimestamp() );
}
