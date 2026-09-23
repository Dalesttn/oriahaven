<?php
/**
 * The two things a member can do, and the two a provider can.
 *
 * Plain admin-post endpoints rather than REST: these are form posts from
 * pages the visitor is already on, they redirect back, and they need a
 * nonce and a logged-in user — which is exactly what admin-post is for.
 * The rest of the site does it this way and there is no reason for the
 * Pass to be different.
 *
 * Every one of these re-checks permission at the point of the write. A
 * control hidden in the markup is a courtesy; the check here is the
 * security.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Actions;

use Oria\Pass\Booking;
use Oria\Pass\Sessions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'admin_post_oria_pass_book', __NAMESPACE__ . '\book' );
	add_action( 'admin_post_oria_pass_cancel', __NAMESPACE__ . '\cancel' );
	add_action( 'admin_post_oria_pass_session_save', __NAMESPACE__ . '\session_save' );
	add_action( 'admin_post_oria_pass_session_status', __NAMESPACE__ . '\session_status' );
	add_action( 'admin_post_oria_pass_mark', __NAMESPACE__ . '\mark' );
}

/**
 * Back where they came from, with something to show for it.
 *
 * The fragment matters more than it looks. On a listing page the Pass
 * block sits well down the page, so a member who books is returned to
 * the top and sees nothing: no reference, no confirmation, and no reason
 * to believe it worked. Aiming the redirect at the block puts the answer
 * where they are looking. A page without that anchor simply ignores it.
 */
function back( array $args, string $fallback = '', string $fragment = '' ): void {
	$to = wp_get_referer() ?: ( $fallback ?: home_url( '/' ) );
	$to = add_query_arg( $args, remove_query_arg( array( 'pass_ok', 'pass_err', 'pass_ref', 'pass_made' ), $to ) );

	if ( '' !== $fragment ) {
		$to .= '#' . rawurlencode( $fragment );
	}

	wp_safe_redirect( $to );
	exit;
}

/** A member takes a place. */
function book(): void {
	if ( ! is_user_logged_in() || ! wp_verify_nonce( (string) ( $_POST['_wpnonce'] ?? '' ), 'oria_pass_book' ) ) {
		back( array( 'pass_err' => 'expired' ), '', 'xpass' );
	}

	$result = Booking\book( get_current_user_id(), (int) ( $_POST['session_id'] ?? 0 ) );

	if ( is_wp_error( $result ) ) {
		back( array( 'pass_err' => $result->get_error_code() ), '', 'xpass' );
	}

	back( array( 'pass_ok' => 'booked', 'pass_ref' => $result->booking_reference ), '', 'xpass' );
}

/** A member gives one back. */
function cancel(): void {
	if ( ! is_user_logged_in() || ! wp_verify_nonce( (string) ( $_POST['_wpnonce'] ?? '' ), 'oria_pass_cancel' ) ) {
		back( array( 'pass_err' => 'expired' ) );
	}

	$result = Booking\cancel( (int) ( $_POST['booking_id'] ?? 0 ), get_current_user_id() );

	if ( is_wp_error( $result ) ) {
		back( array( 'pass_err' => $result->get_error_code() ) );
	}

	back( array( 'pass_ok' => $result['refunded'] ? 'cancelled_refunded' : 'cancelled_kept' ) );
}

/**
 * Does this person run this listing?
 *
 * The one question that separates a provider from anybody else with a
 * session id, and it is asked of the listing rather than of a role: owning
 * a listing is what makes somebody a provider here.
 */
function owns( int $listing_id ): bool {
	if ( ! function_exists( '\Oria\Core\ListingEditor\listing_for' ) ) {
		return false;
	}

	return $listing_id > 0 && \Oria\Core\ListingEditor\listing_for( get_current_user_id() ) === $listing_id;
}

/** A provider opens a session, or edits one. */
function session_save(): void {
	if ( ! is_user_logged_in() || ! wp_verify_nonce( (string) ( $_POST['_wpnonce'] ?? '' ), 'oria_pass_session' ) ) {
		back( array( 'pass_err' => 'expired' ) );
	}

	$listing_id = (int) ( $_POST['listing_id'] ?? 0 );
	if ( ! owns( $listing_id ) ) {
		back( array( 'pass_err' => 'not_yours' ) );
	}

	$id = (int) ( $_POST['session_id'] ?? 0 );
	if ( $id > 0 ) {
		$existing = Sessions\get( $id );
		// A session id from a form is not proof of anything on its own.
		if ( ! $existing || (int) $existing->listing_id !== $listing_id ) {
			back( array( 'pass_err' => 'not_yours' ) );
		}
	}

	$start = trim( (string) ( $_POST['start_date'] ?? '' ) . ' ' . (string) ( $_POST['start_time'] ?? '' ) );
	$end   = trim( (string) ( $_POST['end_time'] ?? '' ) );

	$fields = array(
		'listing_id'          => $listing_id,
		'provider_user_id'    => get_current_user_id(),
		'title'               => sanitize_text_field( wp_unslash( (string) ( $_POST['title'] ?? '' ) ) ),
		'category'            => sanitize_key( (string) ( $_POST['category'] ?? '' ) ),
		'start_at'            => $start,
		'end_at'              => '' !== $end ? (string) ( $_POST['start_date'] ?? '' ) . ' ' . $end : '',
		'total_capacity'      => (int) ( $_POST['total_capacity'] ?? 0 ),
		'pass_capacity'       => (int) ( $_POST['pass_capacity'] ?? 0 ),
		'credits_required'    => (int) ( $_POST['credits_required'] ?? 0 ),
		'provider_payout'     => (float) ( $_POST['provider_payout'] ?? 0 ),
		'cancel_cutoff_hours' => (int) ( $_POST['cancel_cutoff_hours'] ?? 12 ),
		'notes'               => wp_unslash( (string) ( $_POST['notes'] ?? '' ) ),
		'status'              => 'publish' === ( $_POST['save_as'] ?? '' ) ? 'active' : 'draft',
	);

	/*
	 * Repeating only applies to a session being opened. Editing one
	 * Tuesday must never quietly write out eleven more, so an edit takes
	 * the single-session path whatever the form says.
	 */
	$repeat = $id > 0 ? 'once' : sanitize_key( (string) ( $_POST['repeat'] ?? 'once' ) );
	$until  = sanitize_text_field( (string) ( $_POST['repeat_until'] ?? '' ) );

	if ( $id > 0 ) {
		$result = Sessions\save( $fields, $id );
		$made   = is_wp_error( $result ) ? 0 : 1;

		if ( is_wp_error( $result ) ) {
			back( array( 'pass_err' => $result->get_error_code() ) );
		}
	} else {
		$series = Sessions\save_series( $fields, $repeat, $until );
		$made   = count( $series['ids'] );

		// A series that failed before writing anything is just a failure.
		if ( $series['error'] && 0 === $made ) {
			back( array( 'pass_err' => $series['error']->get_error_code() ) );
		}
	}

	/*
	 * The message has to match what actually happened. Saying "it is a
	 * draft until you publish it" to somebody who just pressed Publish is
	 * the sort of small lie that makes people distrust the rest.
	 */
	$published = 'publish' === ( $_POST['save_as'] ?? '' );

	/*
	 * Back to the sessions, not to the form. What somebody wants to see
	 * after saving is the thing they saved.
	 */
	back(
		array(
			'pass_ok'   => $published ? 'session_live' : ( $id > 0 ? 'session_saved' : 'session_draft' ),
			'pass_made' => $made > 1 ? $made : false,
			'tab'       => 'upcoming',
			'edit'      => false,
		)
	);
}

/** Pause, publish or call a session off. */
function session_status(): void {
	if ( ! is_user_logged_in() || ! wp_verify_nonce( (string) ( $_POST['_wpnonce'] ?? '' ), 'oria_pass_session' ) ) {
		back( array( 'pass_err' => 'expired' ) );
	}

	$session = Sessions\get( (int) ( $_POST['session_id'] ?? 0 ) );
	if ( ! $session || ! owns( (int) $session->listing_id ) ) {
		back( array( 'pass_err' => 'not_yours' ) );
	}

	$status = sanitize_key( (string) ( $_POST['status'] ?? '' ) );
	if ( ! in_array( $status, array( 'active', 'paused', 'cancelled', 'draft' ), true ) ) {
		back( array( 'pass_err' => 'bad_status' ) );
	}

	/*
	 * Calling a session off is not just a status change: everybody who
	 * booked it gets their credits back, whenever it happens, because none
	 * of it was their doing.
	 */
	$told = 0;
	if ( 'cancelled' === $status ) {
		foreach ( Booking\for_session( (int) $session->id ) as $booking ) {
			if ( 'confirmed' === (string) $booking->status ) {
				Booking\cancel( (int) $booking->id, 0, true );
				++$told;
			}
		}
	}

	Sessions\set_status( (int) $session->id, $status );

	/*
	 * A receipt for the studio. They have just refunded everybody who had
	 * booked, and they should be told how many that was by something other
	 * than counting the rows themselves.
	 */
	if ( 'cancelled' === $status ) {
		do_action( 'oria_pass_session_called_off', $session, $told );
	}

	back( array( 'pass_ok' => 'cancelled' === $status ? 'session_cancelled' : 'session_saved' ) );
}

/** Ticking somebody off at the door. */
function mark(): void {
	if ( ! is_user_logged_in() || ! wp_verify_nonce( (string) ( $_POST['_wpnonce'] ?? '' ), 'oria_pass_session' ) ) {
		back( array( 'pass_err' => 'expired' ) );
	}

	$booking = Booking\get( (int) ( $_POST['booking_id'] ?? 0 ) );
	if ( ! $booking ) {
		back( array( 'pass_err' => 'missing' ) );
	}

	$session = Sessions\get( (int) $booking->session_id );
	if ( ! $session || ! owns( (int) $session->listing_id ) ) {
		back( array( 'pass_err' => 'not_yours' ) );
	}

	Booking\mark( (int) $booking->id, sanitize_key( (string) ( $_POST['mark'] ?? '' ) ) );

	back( array( 'pass_ok' => 'marked' ) );
}
