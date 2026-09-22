<?php
/**
 * What the month came to.
 *
 * Two questions, and they are not the same question. An overview asks
 * whether the Pass is working: are people joining, are they using what
 * they paid for, are studios getting anybody through the door. A payout
 * run asks what Oria owes, which is money somebody is waiting on and has
 * to be right.
 *
 * Every number here is read from the ledger and the bookings, never from
 * a running total kept somewhere. A stored figure drifts; a summed one
 * cannot.
 *
 * WHAT EARNS A PAYOUT
 *
 * A studio is paid for the places a member paid for. That is the whole
 * rule, and it falls out of the ledger rather than out of a list of
 * statuses: if the credits came back, the place cost the member nothing
 * and the studio is not owed for it; if they did not, the seat was held
 * and it is owed. So it covers the awkward ones without special cases --
 * somebody who cancelled too late still pays, and the studio is still
 * paid; somebody whose session the studio called off is refunded, and it
 * is not. A no-show pays, which is right: the seat was held all evening.
 *
 * A place is counted in the month the SESSION RAN, not the month it was
 * booked in, because that is the month the studio will be thinking of.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Reports;

use Oria\Pass\Db;
use Oria\Pass\Membership;
use Oria\Pass\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A month is always 'YYYY-MM', and anything else becomes this one. */
function month( string $raw = '' ): string {
	return preg_match( '/^\d{4}-\d{2}$/', $raw ) ? $raw : wp_date( 'Y-m' );
}

/** First and last second of a month, in Perth time, as the DB stores it. */
function bounds( string $month ): array {
	$start = $month . '-01 00:00:00';
	$end   = date_create_immutable( $start, wp_timezone() );
	$end   = $end ? $end->modify( 'first day of next month' )->format( 'Y-m-d H:i:s' ) : $start;

	return array( $start, $end );
}

/**
 * Months that actually have something in them, newest first.
 *
 * Built from the sessions rather than from a date range, so the picker
 * never offers an empty month and never runs out.
 *
 * @return array<int, string>
 */
function months(): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$found = (array) $wpdb->get_col(
		'SELECT DISTINCT DATE_FORMAT(start_at, "%Y-%m") FROM ' . Db\sessions() . ' ORDER BY 1 DESC'
	);

	$now = wp_date( 'Y-m' );
	if ( ! in_array( $now, $found, true ) ) {
		array_unshift( $found, $now );
	}

	return $found;
}

/**
 * The payable bookings of a month, one row each.
 *
 * The HAVING is the rule in the file header: sum the ledger for this
 * booking and keep it only if the member is out of pocket. A booking that
 * was cancelled and re-booked has several ledger rows against one id, and
 * summing them is exactly what gets the final answer right.
 *
 * @return array<int, object>
 */
function payable( string $month, int $listing_id = 0 ): array {
	global $wpdb;

	list( $from, $to ) = bounds( $month );

	$b   = Db\bookings();
	$s   = Db\sessions();
	$l   = Db\ledger();
	$and = $listing_id > 0 ? ' AND s.listing_id = %d' : '';

	$params = array( $from, $to );
	if ( $listing_id > 0 ) {
		$params[] = $listing_id;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT b.id, b.booking_reference, b.user_id, b.credits_spent, b.provider_payout, b.status,
				s.id AS session_id, s.listing_id, s.title, s.start_at,
				COALESCE( SUM( l.credit_change ), 0 ) AS net
			FROM {$b} b
			INNER JOIN {$s} s ON s.id = b.session_id
			LEFT JOIN {$l} l ON l.booking_id = b.id
			WHERE s.start_at >= %s AND s.start_at < %s{$and}
			GROUP BY b.id
			HAVING net < 0
			ORDER BY s.listing_id, s.start_at, b.id",
			$params
		)
	);
}

/**
 * The same month totalled per studio, which is how it gets paid.
 *
 * @return array<int, array>
 */
function payouts( string $month ): array {
	$by = array();

	foreach ( payable( $month ) as $row ) {
		$id = (int) $row->listing_id;

		if ( ! isset( $by[ $id ] ) ) {
			$by[ $id ] = array(
				'listing_id' => $id,
				'name'       => wp_specialchars_decode( (string) get_the_title( $id ), ENT_QUOTES ),
				'places'     => 0,
				'credits'    => 0,
				'amount'     => 0.0,
				'sessions'   => array(),
			);
		}

		++$by[ $id ]['places'];
		$by[ $id ]['credits'] += abs( (int) $row->net );
		$by[ $id ]['amount']  += (float) $row->provider_payout;
		$by[ $id ]['sessions'][ (int) $row->session_id ] = true;
	}

	foreach ( $by as &$row ) {
		$row['sessions'] = count( $row['sessions'] );
	}
	unset( $row );

	usort(
		$by,
		static fn( array $a, array $b ): int => $b['amount'] <=> $a['amount']
	);

	return $by;
}

/**
 * Is the Pass working?
 *
 * Deliberately a handful of numbers rather than a wall of them. Each one
 * here is something you would change a decision over.
 */
function overview(): array {
	global $wpdb;

	$month             = month();
	list( $from, $to ) = bounds( $month );

	$m = Db\memberships();
	$l = Db\ledger();
	$s = Db\sessions();
	$b = Db\bookings();

	$count = static function ( string $sql, array $params = array() ) use ( $wpdb ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) );
	};

	$paying = $count( "SELECT COUNT(*) FROM {$m} WHERE status = 'active'" );

	/*
	 * Subscriptions still billing somebody who subscribed again. The row
	 * is retired here the moment it happens; the charge is not, and only a
	 * person in the Stripe dashboard can stop that.
	 */
	$spare = $count( "SELECT COUNT(*) FROM {$m} WHERE status = 'superseded'" );
	$owed   = $count( "SELECT COUNT(*) FROM {$m} WHERE status = 'past_due'" );

	/*
	 * Allocated and spent are the pair that matters. Credits issued and
	 * never used look like profit and are not: they are somebody deciding,
	 * a month at a time, that the Pass was not worth it.
	 */
	$allocated = $count( "SELECT COALESCE( SUM( credit_change ), 0 ) FROM {$l} WHERE type = 'monthly_allocation' AND created_at >= %s AND created_at < %s", array( $from, $to ) );
	$spent     = abs( $count( "SELECT COALESCE( SUM( credit_change ), 0 ) FROM {$l} WHERE type = 'booking' AND created_at >= %s AND created_at < %s", array( $from, $to ) ) );
	$refunded  = $count( "SELECT COALESCE( SUM( credit_change ), 0 ) FROM {$l} WHERE type = 'cancellation_refund' AND created_at >= %s AND created_at < %s", array( $from, $to ) );
	$used      = max( 0, $spent - $refunded );

	$ran      = $count( "SELECT COUNT(*) FROM {$s} WHERE start_at >= %s AND start_at < %s AND status <> 'cancelled'", array( $from, $to ) );
	$attended = $count( "SELECT COUNT(*) FROM {$b} b INNER JOIN {$s} s ON s.id = b.session_id WHERE s.start_at >= %s AND s.start_at < %s AND b.status = 'attended'", array( $from, $to ) );
	$noshow   = $count( "SELECT COUNT(*) FROM {$b} b INNER JOIN {$s} s ON s.id = b.session_id WHERE s.start_at >= %s AND s.start_at < %s AND b.status = 'no_show'", array( $from, $to ) );

	$places = 0;
	$owing  = 0.0;
	foreach ( payouts( $month ) as $row ) {
		$places += (int) $row['places'];
		$owing  += (float) $row['amount'];
	}

	$price = (float) preg_replace( '/[^0-9.]/', '', (string) Settings\get( 'price_display' ) );

	return array(
		'month'     => $month,
		'paying'    => $paying,
		'past_due'  => $owed,
		'duplicate' => $spare,
		'revenue'   => $paying * $price,
		'allocated' => $allocated,
		'used'      => $used,
		'sessions'  => $ran,
		'upcoming'  => $count( "SELECT COUNT(*) FROM {$s} WHERE status = 'active' AND start_at > %s", array( current_time( 'mysql' ) ) ),
		'partners'  => $count( "SELECT COUNT( DISTINCT listing_id ) FROM {$s} WHERE status <> 'draft'" ),
		'places'    => $places,
		'owing'     => $owing,
		'attended'  => $attended,
		'no_show'   => $noshow,
	);
}
