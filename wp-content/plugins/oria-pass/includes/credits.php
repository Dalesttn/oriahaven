<?php
/**
 * Credits.
 *
 * Every movement is a row. Nothing here updates a balance in place, and
 * nothing deletes; a mistake is corrected by writing its opposite, so the
 * history stays true even when the numbers were wrong.
 *
 * Three rules this file exists to enforce, in the order they matter:
 *
 *   1. No double spend. Two taps on Book, or two Stripe retries, must not
 *      move credits twice. Every write carries a `ref` that is unique in
 *      the table, so the second attempt is rejected by the database rather
 *      than by a check that might race.
 *
 *   2. No negative balance. Spending is wrapped in a transaction that locks
 *      the person's latest row first, so the balance cannot change between
 *      reading it and writing against it.
 *
 *   3. The balance is derived, not trusted. balance() sums the ledger. The
 *      stored balance_after is a convenience and a check, never the source.
 *
 * Bookings arrive in phase 3. spend() and refund() are written now because
 * they are the reason the ledger is shaped this way, and because writing
 * the money parts under deadline later is how money code goes wrong.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Credits;

use Oria\Pass\Db;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Why credits moved. Stored, reported on, and shown to the member. */
const TYPES = array(
	'monthly_allocation'  => 'Monthly credits',
	'booking'             => 'Booking',
	'cancellation_refund' => 'Booking cancelled',
	'manual_adjustment'   => 'Adjustment',
	'promotional_credit'  => 'Bonus credits',
	'expired_credit'      => 'Credits expired',
);

/**
 * What somebody has, right now.
 *
 * Summed from the ledger rather than read from a column. It is one indexed
 * SUM over a handful of rows per person per month, and it cannot drift.
 */
function balance( int $user_id ): int {
	global $wpdb;

	if ( $user_id < 1 ) {
		return 0;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (int) $wpdb->get_var(
		$wpdb->prepare( 'SELECT COALESCE(SUM(credit_change), 0) FROM ' . Db\ledger() . ' WHERE user_id = %d', $user_id )
	);
}

/**
 * The member's own history, newest first.
 *
 * @return array<int, object>
 */
function history( int $user_id, int $limit = 50 ): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . Db\ledger() . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d',
			$user_id,
			$limit
		)
	);
}

/**
 * Has this exact movement already been written?
 *
 * The ledger's unique ref makes each individual write idempotent, but a
 * SEQUENCE of writes is not idempotent just because its parts are. Renewal
 * expires the old month and then allocates the new one, and a replayed
 * Stripe invoice -- which Stripe sends routinely -- ran the expiry a
 * second time against credits it had itself just granted, taking a paying
 * member from 50 to 0. The guard belongs at the top of the sequence, and
 * this is what it asks.
 */
function has_ref( string $ref ): bool {
	global $wpdb;

	if ( '' === trim( $ref ) ) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (bool) $wpdb->get_var(
		$wpdb->prepare( 'SELECT id FROM ' . Db\ledger() . ' WHERE ref = %s LIMIT 1', $ref )
	);
}

/**
 * Write one movement.
 *
 * Positive to give, negative to take. Returns the new balance, or a
 * WP_Error — including when the same ref has already been written, which
 * is a success from the caller's point of view and is reported as
 * 'already_done' so it can be told apart from a real failure.
 *
 * @param int    $change  Credits to add (positive) or remove (negative).
 * @param string $ref     Idempotency key. Must be unique in the table.
 * @return int|\WP_Error  The balance after this movement.
 */
function write( int $user_id, string $type, int $change, string $ref, array $extra = array() ) {
	global $wpdb;

	if ( $user_id < 1 ) {
		return new \WP_Error( 'no_user', __( 'No member to credit.', 'oria' ) );
	}
	if ( ! isset( TYPES[ $type ] ) ) {
		return new \WP_Error( 'bad_type', __( 'Unknown kind of credit movement.', 'oria' ) );
	}
	if ( 0 === $change ) {
		return new \WP_Error( 'no_change', __( 'Nothing to move.', 'oria' ) );
	}
	if ( '' === trim( $ref ) ) {
		return new \WP_Error( 'no_ref', __( 'Every movement needs a reference.', 'oria' ) );
	}

	$table = Db\ledger();

	/*
	 * The lock matters. Without it two requests can both read a balance of
	 * 10, both decide a 8-credit booking is affordable, and both write --
	 * leaving -6. SELECT ... FOR UPDATE holds the row until this
	 * transaction ends, so the second request waits and then sees the
	 * truth. InnoDB only, which is every WordPress install since 2013.
	 */
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$current = (int) $wpdb->get_var(
		$wpdb->prepare( 'SELECT COALESCE(SUM(credit_change), 0) FROM ' . $table . ' WHERE user_id = %d FOR UPDATE', $user_id )
	);

	if ( $change < 0 && $current + $change < 0 ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return new \WP_Error(
			'insufficient',
			__( 'Not enough credits for that.', 'oria' ),
			array( 'balance' => $current, 'needed' => abs( $change ) )
		);
	}

	$after = $current + $change;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ok = $wpdb->insert(
		$table,
		array(
			'user_id'       => $user_id,
			'membership_id' => (int) ( $extra['membership_id'] ?? 0 ),
			'type'          => $type,
			'credit_change' => $change,
			'balance_after' => $after,
			'booking_id'    => (int) ( $extra['booking_id'] ?? 0 ),
			'ref'           => $ref,
			'description'   => (string) ( $extra['description'] ?? TYPES[ $type ] ),
			'expires_at'    => $extra['expires_at'] ?? null,
			'created_at'    => current_time( 'mysql' ),
		)
	);

	if ( false === $ok ) {
		$duplicate = str_contains( (string) $wpdb->last_error, 'Duplicate entry' );
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( $duplicate ) {
			// Already written by an earlier attempt. Not a failure.
			return new \WP_Error( 'already_done', __( 'That has already been recorded.', 'oria' ), array( 'balance' => balance( $user_id ) ) );
		}

		error_log( sprintf( '[oria-pass] ledger write failed for user %d (%s): %s', $user_id, $type, $wpdb->last_error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

		return new \WP_Error( 'db', __( 'Could not record that. Nothing has been charged.', 'oria' ) );
	}

	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	return $after;
}

/**
 * A cycle's credits.
 *
 * The ref ties the allocation to the thing that paid for it -- a Stripe
 * invoice -- so a retried webhook cannot hand out a second month.
 */
function allocate( int $user_id, int $credits, string $ref, int $membership_id = 0, ?string $expires_at = null ) {
	return write(
		$user_id,
		'monthly_allocation',
		abs( $credits ),
		$ref,
		array(
			'membership_id' => $membership_id,
			'expires_at'    => $expires_at,
			'description'   => __( 'Monthly credits', 'oria' ),
		)
	);
}

/** Spend on a booking. Phase 3 calls this; the rules live here. */
function spend( int $user_id, int $credits, string $ref, int $booking_id = 0, string $what = '' ) {
	return write(
		$user_id,
		'booking',
		-abs( $credits ),
		$ref,
		array(
			'booking_id'  => $booking_id,
			'description' => '' !== $what ? $what : __( 'Booking', 'oria' ),
		)
	);
}

/** Give them back. A distinct ref, so a refund cannot be applied twice. */
function refund( int $user_id, int $credits, string $ref, int $booking_id = 0, string $what = '' ) {
	return write(
		$user_id,
		'cancellation_refund',
		abs( $credits ),
		$ref,
		array(
			'booking_id'  => $booking_id,
			'description' => '' !== $what ? $what : __( 'Booking cancelled', 'oria' ),
		)
	);
}

/**
 * Take back whatever is left at the end of a cycle.
 *
 * Credits are for the month they were issued in. This is written as one
 * balancing row rather than by expiring individual allocations, because
 * what a member needs to see is "your month reset", not a reconciliation.
 */
function expire_remaining( int $user_id, string $ref, int $membership_id = 0 ) {
	$left = balance( $user_id );
	if ( $left < 1 ) {
		return 0;
	}

	return write(
		$user_id,
		'expired_credit',
		-$left,
		$ref,
		array(
			'membership_id' => $membership_id,
			'description'   => __( 'Unused credits at the end of the month', 'oria' ),
		)
	);
}

/** A human label for a ledger row. */
function label( string $type ): string {
	return TYPES[ $type ] ?? $type;
}
