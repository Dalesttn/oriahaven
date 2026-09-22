<?php
/**
 * Booking: taking a place, and giving it back.
 *
 * The whole file exists for one sentence: claiming a seat and paying for
 * it must be a single act. If the seat is taken and the credits are not
 * spent, a studio holds a place for somebody who paid nothing; if the
 * credits go and the seat does not, a member has paid for nothing. Both
 * are worse than a failed booking.
 *
 * So book() opens one transaction, locks the session row, checks
 * everything under that lock, writes the booking, spends the credits
 * through the ledger -- which joins the same transaction rather than
 * starting its own, because MySQL does not nest them -- and only then
 * commits. Any failure rolls the lot back and nothing happened.
 *
 * Two races are closed by the database rather than by checking first:
 *
 *   - Two members going for the last place: the row lock means the second
 *     waits, then sees booked_count already full.
 *   - One member double-tapping Book: the UNIQUE key on (session, user)
 *     refuses the second row.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Booking;

use Oria\Pass\Credits;
use Oria\Pass\Db;
use Oria\Pass\Membership;
use Oria\Pass\Sessions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where a booking can be in its life. */
const STATUSES = array( 'confirmed', 'cancelled_by_member', 'cancelled_by_provider', 'attended', 'no_show', 'refunded' );

function get( int $id ): ?object {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db\bookings() . ' WHERE id = %d', $id ) );

	return $row ?: null;
}

/**
 * A member's own bookings, soonest session first.
 *
 * @return array<int, object>
 */
function for_user( int $user_id, bool $upcoming_only = false, int $limit = 50 ): array {
	global $wpdb;

	$b = Db\bookings();
	$s = Db\sessions();

	$sql    = "SELECT b.*, s.title, s.start_at, s.end_at, s.listing_id, s.cancel_cutoff_hours, s.status AS session_status
		FROM {$b} b INNER JOIN {$s} s ON s.id = b.session_id WHERE b.user_id = %d";
	$params = array( $user_id );

	if ( $upcoming_only ) {
		$sql     .= " AND s.start_at > %s AND b.status = 'confirmed'";
		$params[] = current_time( 'mysql' );
	}

	$sql     .= ' ORDER BY s.start_at ASC LIMIT %d';
	$params[] = $limit;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/** Who is coming, for the provider. */
function for_session( int $session_id ): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results(
		$wpdb->prepare( 'SELECT * FROM ' . Db\bookings() . ' WHERE session_id = %d ORDER BY booked_at ASC', $session_id )
	);
}

/** Short, unambiguous, and safe to read down a phone. No I, O, 0 or 1. */
function reference(): string {
	$alphabet = 'ACDEFGHJKLMNPQRSTUVWXYZ23456789';
	$out      = '';
	for ( $i = 0; $i < 6; $i++ ) {
		$out .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
	}

	return 'OP-' . $out;
}

/**
 * Take a place.
 *
 * @return object|\WP_Error The booking, or why not.
 */
function book( int $user_id, int $session_id ) {
	global $wpdb;

	if ( $user_id < 1 ) {
		return new \WP_Error( 'signed_out', __( 'Sign in to book with your Pass.', 'oria' ) );
	}
	if ( ! Membership\is_active( $user_id ) ) {
		return new \WP_Error( 'no_membership', __( 'You need an active Oria Pass to book this.', 'oria' ) );
	}

	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	/*
	 * FOR UPDATE, and everything below is decided on THIS row rather than
	 * on the copy the page was drawn from. A session read a minute ago on
	 * a listing page may already be full.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$session = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . Db\sessions() . ' WHERE id = %d FOR UPDATE', $session_id )
	);

	if ( ! $session ) {
		return fail( 'missing', __( 'That session is no longer listed.', 'oria' ) );
	}
	/*
	 * Sold out is checked before the general status, because "not open for
	 * booking" is a true but useless thing to tell somebody whose real
	 * answer is that the last place went while they were reading.
	 */
	if ( 'sold_out' === (string) $session->status || Sessions\places_left( $session ) < 1 ) {
		return fail( 'full', __( 'The Pass places for that session have gone.', 'oria' ) );
	}
	if ( 'active' !== (string) $session->status ) {
		return fail( 'not_active', __( 'That session is not open for booking.', 'oria' ) );
	}
	if ( Sessions\has_started( $session ) ) {
		return fail( 'started', __( 'That session has already started.', 'oria' ) );
	}
	$credits = (int) $session->credits_required;
	$now     = current_time( 'mysql' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ok = $wpdb->insert(
		Db\bookings(),
		array(
			'booking_reference' => reference(),
			'session_id'        => (int) $session->id,
			'user_id'           => $user_id,
			'credits_spent'     => $credits,
			'provider_payout'   => (float) $session->provider_payout,
			'status'            => 'confirmed',
			'booked_at'         => $now,
			'created_at'        => $now,
			'updated_at'        => $now,
		)
	);

	if ( false === $ok ) {
		// The unique key on (session, user) is what a second tap hits.
		if ( str_contains( (string) $wpdb->last_error, 'Duplicate entry' ) ) {
			return fail( 'already_booked', __( 'You have already booked this one.', 'oria' ) );
		}

		error_log( sprintf( '[oria-pass] booking insert failed (user %d, session %d): %s', $user_id, $session_id, $wpdb->last_error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

		return fail( 'db', __( 'That did not go through. Nothing has been charged.', 'oria' ) );
	}

	$booking_id = (int) $wpdb->insert_id;

	// Joined, not nested: this spends inside the transaction opened above.
	$spent = Credits\spend( $user_id, $credits, 'book:' . $booking_id, $booking_id, (string) $session->title, true );

	if ( is_wp_error( $spent ) ) {
		return fail( $spent->get_error_code(), $spent->get_error_message() );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$wpdb->query(
		$wpdb->prepare( 'UPDATE ' . Db\sessions() . ' SET booked_count = booked_count + 1, updated_at = %s WHERE id = %d', $now, (int) $session->id )
	);

	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Sold out is a consequence, not a decision, so it is set after the fact.
	$after = Sessions\get( (int) $session->id );
	if ( $after && Sessions\places_left( $after ) < 1 ) {
		Sessions\set_status( (int) $after->id, 'sold_out' );
	}

	$booking = get( $booking_id );

	do_action( 'oria_pass_booked', $booking, $session, $user_id );

	return $booking;
}

/** Roll everything back and say why. */
function fail( string $code, string $message ): \WP_Error {
	global $wpdb;

	$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	return new \WP_Error( $code, $message );
}

/**
 * Give a place back.
 *
 * Inside the cutoff the credits stay spent -- the studio held a place and
 * nobody else could take it -- and that is said plainly rather than
 * discovered afterwards. A provider cancelling always refunds, whenever it
 * happens, because none of it was the member's doing.
 *
 * @return array|\WP_Error  ['refunded' => bool, 'credits' => int]
 */
function cancel( int $booking_id, int $by_user_id, bool $by_provider = false ) {
	global $wpdb;

	$booking = get( $booking_id );
	if ( ! $booking ) {
		return new \WP_Error( 'missing', __( 'That booking no longer exists.', 'oria' ) );
	}
	if ( ! $by_provider && (int) $booking->user_id !== $by_user_id ) {
		return new \WP_Error( 'not_yours', __( 'That is not your booking.', 'oria' ) );
	}
	if ( 'confirmed' !== (string) $booking->status ) {
		return new \WP_Error( 'not_confirmed', __( 'That booking has already been settled.', 'oria' ) );
	}

	$session  = Sessions\get( (int) $booking->session_id );
	$refund   = $by_provider || ( $session && Sessions\refundable( $session ) );
	$now      = current_time( 'mysql' );
	$status   = $by_provider ? 'cancelled_by_provider' : 'cancelled_by_member';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update(
		Db\bookings(),
		array( 'status' => $status, 'cancelled_at' => $now, 'updated_at' => $now ),
		array( 'id' => $booking_id )
	);

	/*
	 * The place goes back on the board either way. A late cancellation
	 * costs the member their credits, but leaving the seat locked would
	 * cost the studio a booking as well, and punishing both is nobody's
	 * policy.
	 */
	if ( $session ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Db\sessions() . ' SET booked_count = GREATEST(0, booked_count - 1), updated_at = %s WHERE id = %d',
				$now,
				(int) $session->id
			)
		);
		if ( 'sold_out' === (string) $session->status ) {
			Sessions\set_status( (int) $session->id, 'active' );
		}
	}

	$credits = 0;
	if ( $refund ) {
		$given = Credits\refund(
			(int) $booking->user_id,
			(int) $booking->credits_spent,
			'refund:' . $booking_id,
			$booking_id,
			$session ? (string) $session->title : ''
		);
		if ( ! is_wp_error( $given ) ) {
			$credits = (int) $booking->credits_spent;
		}
	}

	do_action( 'oria_pass_cancelled', $booking, $session, $refund );

	return array( 'refunded' => $refund, 'credits' => $credits );
}

/** The provider ticking somebody off at the door. */
function mark( int $booking_id, string $status ): bool {
	global $wpdb;

	if ( ! in_array( $status, array( 'attended', 'no_show' ), true ) ) {
		return false;
	}

	$now = current_time( 'mysql' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (bool) $wpdb->update(
		Db\bookings(),
		array(
			'status'      => $status,
			'attended_at' => 'attended' === $status ? $now : null,
			'updated_at'  => $now,
		),
		array( 'id' => $booking_id, 'status' => 'confirmed' )
	);
}

/** What a member should be told their booking is. */
function label( string $status ): string {
	switch ( $status ) {
		case 'confirmed':
			return __( 'Booked', 'oria' );
		case 'cancelled_by_member':
			return __( 'Cancelled', 'oria' );
		case 'cancelled_by_provider':
			return __( 'Cancelled by the studio', 'oria' );
		case 'attended':
			return __( 'Went', 'oria' );
		case 'no_show':
			return __( 'Missed', 'oria' );
		case 'refunded':
			return __( 'Refunded', 'oria' );
	}

	return $status;
}
