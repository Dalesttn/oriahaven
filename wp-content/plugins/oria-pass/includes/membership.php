<?php
/**
 * Who is a member, and what state their membership is in.
 *
 * The lifecycle mirrors Stripe's rather than inventing one: Stripe is the
 * system of record for whether somebody is paying, and this table records
 * what that means for the Pass. When the two disagree, Stripe wins.
 *
 * past_due is deliberately not a lapse. Stripe retries a failed card for
 * days and most recover; cutting somebody's credits off on the first
 * decline would punish a bank's fraud check. The listing tiers already
 * take this view and the Pass follows it.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Membership;

use Oria\Pass\Credits;
use Oria\Pass\Db;
use Oria\Pass\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const STATUSES = array( 'active', 'past_due', 'paused', 'cancelled', 'expired' );

/** The membership row for a person, whatever state it is in. */
function for_user( int $user_id ): ?object {
	global $wpdb;

	if ( $user_id < 1 ) {
		return null;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$row = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . Db\memberships() . ' WHERE user_id = %d ORDER BY id DESC LIMIT 1', $user_id )
	);

	return $row ?: null;
}

function by_subscription( string $ref ): ?object {
	global $wpdb;

	if ( '' === $ref ) {
		return null;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$row = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . Db\memberships() . ' WHERE subscription_ref = %s LIMIT 1', $ref )
	);

	return $row ?: null;
}

/** Can this person book? The only question the rest of the code asks. */
function is_active( int $user_id ): bool {
	$row = for_user( $user_id );

	return $row && in_array( (string) $row->status, array( 'active', 'past_due' ), true );
}

/**
 * Start or restart a membership.
 *
 * Idempotent on the subscription reference: Stripe can send
 * checkout.session.completed more than once, and a second call updates the
 * existing row rather than creating a rival one.
 */
function activate( int $user_id, string $subscription_ref, string $customer_ref = '' ): ?object {
	global $wpdb;

	if ( $user_id < 1 ) {
		return null;
	}

	$now     = current_time( 'mysql' );
	$credits = (int) Settings\get( 'credits_per_cycle' );
	$ends    = cycle_end_from( $now );

	$existing = '' !== $subscription_ref ? by_subscription( $subscription_ref ) : for_user( $user_id );

	if ( $existing ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			Db\memberships(),
			array(
				'user_id'           => $user_id,
				'status'            => 'active',
				'customer_ref'      => $customer_ref ?: (string) $existing->customer_ref,
				'credits_per_cycle' => $credits,
				'cycle_started_at'  => $now,
				'cycle_ends_at'     => $ends,
				'updated_at'        => $now,
			),
			array( 'id' => (int) $existing->id )
		);

		return by_id( (int) $existing->id );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		Db\memberships(),
		array(
			'user_id'           => $user_id,
			'subscription_ref'  => $subscription_ref,
			'customer_ref'      => $customer_ref,
			'plan'              => 'pass',
			'status'            => 'active',
			'credits_per_cycle' => $credits,
			'cycle_started_at'  => $now,
			'cycle_ends_at'     => $ends,
			'created_at'        => $now,
			'updated_at'        => $now,
		)
	);

	return by_id( (int) $wpdb->insert_id );
}

function by_id( int $id ): ?object {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db\memberships() . ' WHERE id = %d', $id ) );

	return $row ?: null;
}

/** Move the cycle on, and hand over the next month's credits. */
function renew( object $membership, string $invoice_ref, ?string $period_end = null ): void {
	global $wpdb;

	/*
	 * One invoice, one renewal. Stripe replays webhooks as a matter of
	 * course, and without this the expiry below runs again on credits the
	 * previous run had just granted -- which took a paying member from a
	 * full month to nothing. The allocation's own ref is the marker,
	 * because it is the thing that must happen exactly once.
	 */
	if ( Credits\has_ref( 'alloc:' . $invoice_ref ) ) {
		return;
	}

	$now  = current_time( 'mysql' );
	$ends = $period_end ?: cycle_end_from( $now );

	/*
	 * The old month closes before the new one opens, so a member never
	 * holds two months at once. Both movements carry the invoice id, which
	 * is what makes a retried webhook harmless.
	 */
	if ( 'cycle' === (string) Settings\get( 'credit_expiry' ) ) {
		Credits\expire_remaining( (int) $membership->user_id, 'expire:' . $invoice_ref, (int) $membership->id );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update(
		Db\memberships(),
		array(
			'status'           => 'active',
			'cycle_started_at' => $now,
			'cycle_ends_at'    => $ends,
			'updated_at'       => $now,
		),
		array( 'id' => (int) $membership->id )
	);

	Credits\allocate(
		(int) $membership->user_id,
		(int) ( $membership->credits_per_cycle ?: Settings\get( 'credits_per_cycle' ) ),
		'alloc:' . $invoice_ref,
		(int) $membership->id,
		$ends
	);
}

/** Set a status without touching credits. */
function set_status( object $membership, string $status ): void {
	global $wpdb;

	if ( ! in_array( $status, STATUSES, true ) ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update(
		Db\memberships(),
		array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => (int) $membership->id )
	);
}

/**
 * A month from a date, in the site's own timezone.
 *
 * strtotime on a naive local string is read as UTC on this install, so the
 * pairing with wp_date would shift it eight hours. date_create_immutable
 * with wp_timezone() is the form the rest of the codebase settled on.
 */
function cycle_end_from( string $mysql_datetime ): string {
	$start = date_create_immutable( $mysql_datetime, wp_timezone() );
	if ( ! $start ) {
		return $mysql_datetime;
	}

	return $start->modify( '+1 month' )->format( 'Y-m-d H:i:s' );
}

/** What the member should be told their state is. */
function label( string $status ): string {
	switch ( $status ) {
		case 'active':
			return __( 'Active', 'oria' );
		case 'past_due':
			return __( 'Payment retrying', 'oria' );
		case 'paused':
			return __( 'Paused', 'oria' );
		case 'cancelled':
			return __( 'Cancelled', 'oria' );
		case 'expired':
			return __( 'Ended', 'oria' );
	}

	return $status;
}
