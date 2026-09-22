<?php
/**
 * The day before.
 *
 * Every other email in the Pass answers something that just happened.
 * This one answers something that has not: a class booked three weeks ago
 * is a class most people have stopped thinking about, and a studio that
 * has held two places all month would rather be reminded than counted on.
 *
 * Three rules, and they are all about sending exactly once.
 *
 * A reminder that goes twice is worse than one that does not go at all,
 * so each side carries its own mark -- reminded_at on the booking for the
 * member, on the session for the studio -- and the mark is written
 * whether or not the mail succeeded. Mail can be retried by a person;
 * a duplicate cannot be unsent.
 *
 * Nothing here decides anything either. It finds what is due, fires the
 * same kind of event the rest of the Pass uses, and lets the notifier
 * write the words. If mail is broken the marks still move on and the
 * bookings are untouched.
 *
 * It runs hourly rather than daily so "tomorrow" means roughly a day and
 * not somewhere between a day and two. WP-Cron only fires on traffic; on
 * a quiet site a reminder is late rather than lost, which is the right
 * way round, and DEPLOY.md already says to put a real cron on the server.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Reminders;

use Oria\Pass\Booking;
use Oria\Pass\Db;
use Oria\Pass\Sessions;
use Oria\Pass\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HOOK = 'oria_pass_remind';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\schedule' );
	add_action( HOOK, __NAMESPACE__ . '\run' );
}

/** Hourly, and only ever one of them. */
function schedule(): void {
	if ( ! wp_next_scheduled( HOOK ) ) {
		wp_schedule_event( time() + 300, 'hourly', HOOK );
	}
}

function unschedule(): void {
	$next = wp_next_scheduled( HOOK );
	if ( $next ) {
		wp_unschedule_event( $next, HOOK );
	}
}

/** How far ahead a reminder looks. */
function window_hours(): int {
	$hours = (int) Settings\get( 'reminder_hours' );

	return $hours > 0 ? $hours : 24;
}

/**
 * Sessions about to run that somebody has booked.
 *
 * Sold out counts. A full session is still a session happening tomorrow,
 * and it is the one most worth reminding people about -- every place in
 * it is somebody who might not turn up.
 *
 * @return array<int, object>
 */
function due(): array {
	global $wpdb;

	$now = date_create_immutable( current_time( 'mysql' ), wp_timezone() );
	if ( ! $now ) {
		return array();
	}

	// Naive Perth strings both sides of the comparison. Never strtotime these.
	$until = $now->modify( '+' . window_hours() . ' hours' )->format( 'Y-m-d H:i:s' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . Db\sessions() . "
			WHERE status IN ( 'active', 'sold_out' )
				AND start_at > %s AND start_at <= %s
				AND booked_count > 0
			ORDER BY start_at ASC
			LIMIT 200",
			$now->format( 'Y-m-d H:i:s' ),
			$until
		)
	);
}

/**
 * Send what is due.
 *
 * @return array{members:int, providers:int} What went out, for the log and for tests.
 */
function run(): array {
	global $wpdb;

	$now      = current_time( 'mysql' );
	$members  = 0;
	$studios  = 0;

	foreach ( due() as $session ) {
		$coming = 0;

		foreach ( Booking\for_session( (int) $session->id ) as $booking ) {
			if ( 'confirmed' !== (string) $booking->status ) {
				continue;
			}

			++$coming;

			if ( null !== $booking->reminded_at && '' !== (string) $booking->reminded_at ) {
				continue;
			}

			/*
			 * Marked first, deliberately. If the send throws or the mail
			 * host is down, the worst case is one person missing one
			 * reminder -- which is where they already were. Marking
			 * afterwards risks sending it again every hour until it
			 * works, which is how a helpful email becomes a nuisance.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( Db\bookings(), array( 'reminded_at' => $now ), array( 'id' => (int) $booking->id ) );

			do_action( 'oria_pass_reminder_member', $booking, $session );
			++$members;
		}

		if ( $coming > 0 && ( null === $session->reminded_at || '' === (string) $session->reminded_at ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( Db\sessions(), array( 'reminded_at' => $now ), array( 'id' => (int) $session->id ) );

			do_action( 'oria_pass_reminder_provider', $session, $coming );
			++$studios;
		}
	}

	return array( 'members' => $members, 'providers' => $studios );
}
