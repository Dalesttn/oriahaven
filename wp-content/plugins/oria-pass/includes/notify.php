<?php
/**
 * Telling people what happened.
 *
 * A booking nobody is told about is worse than no booking: the member is
 * not sure it worked, and the studio finds out when somebody walks in.
 * Everything here hangs off the events the engine already fires, so the
 * booking path does not know or care whether mail is configured.
 *
 * Three rules.
 *
 * Every email answers the question its reader actually has. A member wants
 * to know when and where and what it cost; a studio wants to know who is
 * coming and on what. Neither wants a newsletter.
 *
 * Nothing here decides anything. If sending fails, the booking stands --
 * a mail server having a bad morning must never undo somebody's place or
 * their credits, so these run after the fact and swallow their own
 * problems.
 *
 * A studio is told a first name and a reference. It does not need the
 * member's email address, and giving it one would be handing over somebody
 * else's details for no reason.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Notify;

use Oria\Pass\Booking;
use Oria\Pass\Credits;
use Oria\Pass\Sessions;
use Oria\Pass\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'oria_pass_booked', __NAMESPACE__ . '\booked', 10, 3 );
	add_action( 'oria_pass_cancelled', __NAMESPACE__ . '\cancelled', 10, 3 );
	add_action( 'oria_pass_membership_renewed', __NAMESPACE__ . '\renewed', 10, 2 );
	add_action( 'oria_pass_membership_started', __NAMESPACE__ . '\started', 10, 2 );
}

/**
 * One way out to the mail system.
 *
 * Uses the site's branded shell where oria-forms provides it, so a Pass
 * email looks like every other email Oria sends, and falls back to plain
 * text rather than to nothing.
 */
function send( string $to, string $subject, string $heading, array $lines ): void {
	if ( ! is_email( $to ) ) {
		return;
	}

	try {
		deliver( $to, $subject, $heading, $lines );
	} catch ( \Throwable $e ) {
		/*
		 * Swallowed on purpose, and this is the whole reason the file says
		 * nothing here decides anything. These run after a booking has
		 * committed; letting a mail problem out of here would turn a place
		 * somebody holds into a 500 page.
		 */
		error_log( sprintf( '[oria-pass] could not send "%s" to %s: %s', $subject, $to, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}

/** The actual send, kept separate so send() has one job: not throwing. */
function deliver( string $to, string $subject, string $heading, array $lines ): void {

	$text = implode( "\n", array_filter( $lines, 'strlen' ) );

	if ( function_exists( '\Oria\Forms\Emails\shell' ) ) {
		$html = '';
		foreach ( $lines as $line ) {
			$html .= '' === trim( $line )
				? ''
				: '<p style="margin:0 0 14px;">' . nl2br( esc_html( $line ) ) . '</p>';
		}

		wp_mail( $to, $subject, \Oria\Forms\Emails\shell( $heading, $html ), array( 'Content-Type: text/html; charset=UTF-8' ) );

		return;
	}

	$signoff = function_exists( '\Oria\Core\Mail\signoff' ) ? \Oria\Core\Mail\signoff() : '';

	wp_mail( $to, $subject, $text . $signoff );
}

/** Where the studio is, in one line, for somebody about to travel there. */
function where( object $session ): string {
	$listing = (int) $session->listing_id;
	$name    = Sessions\provider_name( $session );
	$address = function_exists( 'get_field' ) ? trim( (string) get_field( 'field_oria_address', $listing ) ) : '';

	return '' !== $address ? $name . ', ' . $address : $name;
}

/** When the credits stop coming back, said as a time rather than a rule. */
function cutoff_line( object $session ): string {
	$cutoff = Sessions\cutoff( $session );
	if ( ! $cutoff ) {
		return '';
	}

	/*
	 * Time and date are formatted apart and joined by the sentence. Putting
	 * the word "on" inside a date format asks PHP for the ISO year and the
	 * month number, which is how this line once read "8:26am 20269".
	 */
	return sprintf(
		/* translators: 1: time, e.g. 8:00pm  2: date, e.g. Fri 25 Sep */
		__( 'Change of plan? Cancel before %1$s on %2$s and the credits go back to your balance. After that they stay spent, because the place is held for you.', 'oria' ),
		wp_date( 'g:ia', $cutoff->getTimestamp() ),
		wp_date( 'D j M', $cutoff->getTimestamp() )
	);
}

/** A member has taken a place — tell them, then tell the studio. */
function booked( $booking, $session, int $user_id ): void {
	if ( ! $booking || ! $session ) {
		return;
	}

	$member = get_userdata( $user_id );
	$first  = $member ? ( $member->first_name ?: $member->display_name ) : '';

	if ( $member ) {
		send(
			(string) $member->user_email,
			__( 'Your Oria Pass booking is confirmed', 'oria' ),
			__( "You're booked", 'oria' ),
			array(
				'' !== $first ? sprintf( /* translators: %s: first name */ __( 'Thanks %s — that is booked.', 'oria' ), $first ) : __( 'That is booked.', 'oria' ),
				'',
				(string) $session->title,
				Sessions\when( $session ),
				where( $session ),
				'',
				sprintf(
					/* translators: 1: credits spent, 2: credits left */
					__( '%1$d credits spent. You have %2$d left this month.', 'oria' ),
					(int) $booking->credits_spent,
					Credits\balance( $user_id )
				),
				sprintf(
					/* translators: %s: booking reference */
					__( 'Your reference is %s — give it at the door.', 'oria' ),
					(string) $booking->booking_reference
				),
				'' !== trim( (string) $session->notes ) ? "\n" . (string) $session->notes : '',
				'',
				cutoff_line( $session ),
			)
		);
	}

	/*
	 * And the studio. First name and reference only: they need to know who
	 * is coming, not how to contact somebody who booked through us.
	 */
	$owner = provider_email( $session );
	if ( '' !== $owner ) {
		send(
			$owner,
			__( 'A new Oria Pass booking', 'oria' ),
			__( 'Somebody booked', 'oria' ),
			array(
				sprintf(
					/* translators: 1: first name, 2: session title */
					__( '%1$s has taken a Pass place on %2$s.', 'oria' ),
					'' !== $first ? $first : __( 'A member', 'oria' ),
					(string) $session->title
				),
				Sessions\when( $session ),
				'',
				sprintf(
					/* translators: %s: reference */
					__( 'Reference: %s', 'oria' ),
					(string) $booking->booking_reference
				),
				sprintf(
					/* translators: 1: places taken, 2: places offered */
					__( '%1$d of your %2$d Pass places are now taken.', 'oria' ),
					(int) $session->booked_count + 1,
					(int) $session->pass_capacity
				),
				'',
				__( 'Mark them off after the session in My Oria, under Pass places.', 'oria' ),
			)
		);
	}
}

/** A place was given back. Say plainly whether the credits came with it. */
function cancelled( $booking, $session, bool $refunded ): void {
	if ( ! $booking ) {
		return;
	}

	$member = get_userdata( (int) $booking->user_id );
	$by_us  = 'cancelled_by_provider' === (string) $booking->status;

	if ( $member ) {
		send(
			(string) $member->user_email,
			$by_us
				? __( 'Your Oria Pass booking was cancelled', 'oria' )
				: __( 'Your Oria Pass booking is cancelled', 'oria' ),
			$by_us ? __( 'The studio cancelled', 'oria' ) : __( 'Cancelled', 'oria' ),
			array(
				$by_us
					? sprintf(
						/* translators: %s: session title */
						__( 'The studio has called off %s. Sorry — this one was not your doing.', 'oria' ),
						(string) ( $session->title ?? '' )
					)
					: sprintf(
						/* translators: %s: session title */
						__( 'Your place on %s has been cancelled.', 'oria' ),
						(string) ( $session->title ?? '' )
					),
				$session ? Sessions\when( $session ) : '',
				'',
				$refunded
					? sprintf(
						/* translators: 1: credits returned, 2: balance */
						__( '%1$d credits are back in your balance. You now have %2$d.', 'oria' ),
						(int) $booking->credits_spent,
						Credits\balance( (int) $booking->user_id )
					)
					: sprintf(
						/* translators: %d: balance */
						__( 'That was inside the cancellation window, so the credits stay spent. You have %d left this month.', 'oria' ),
						Credits\balance( (int) $booking->user_id )
					),
				'',
				__( 'There is plenty else on — have a look when you are ready.', 'oria' ),
			)
		);
	}

	// The studio only needs telling when it was not them who cancelled.
	$owner = $session ? provider_email( $session ) : '';
	if ( ! $by_us && '' !== $owner ) {
		$first = $member ? ( $member->first_name ?: $member->display_name ) : __( 'A member', 'oria' );

		send(
			$owner,
			__( 'An Oria Pass booking was cancelled', 'oria' ),
			__( 'A place freed up', 'oria' ),
			array(
				sprintf(
					/* translators: 1: first name, 2: session title */
					__( '%1$s has cancelled their place on %2$s.', 'oria' ),
					$first,
					(string) $session->title
				),
				Sessions\when( $session ),
				'',
				__( 'The place is back on offer to other members.', 'oria' ),
			)
		);
	}
}

/** The first month. */
function started( int $user_id, $membership ): void {
	$member = get_userdata( $user_id );
	if ( ! $member ) {
		return;
	}

	send(
		(string) $member->user_email,
		__( 'Welcome to Oria Pass', 'oria' ),
		__( 'Your Pass is live', 'oria' ),
		array(
			__( 'Your Oria Pass is set up. Your credits land as soon as the first payment clears, which is usually a moment or two.', 'oria' ),
			'',
			__( 'Find something to try, book it with credits, and turn up. That is the whole thing.', 'oria' ),
			\Oria\Pass\Route\url(),
		)
	);
}

/** A new month's credits. */
function renewed( int $user_id, $membership ): void {
	$member = get_userdata( $user_id );
	if ( ! $member ) {
		return;
	}

	send(
		(string) $member->user_email,
		__( 'Your Oria Pass credits have reset', 'oria' ),
		__( 'A fresh month', 'oria' ),
		array(
			sprintf(
				/* translators: %d: credits */
				__( 'Your Oria Pass has renewed and you have %d credits to spend.', 'oria' ),
				Credits\balance( $user_id )
			),
			'',
			sprintf(
				/* translators: %s: date */
				__( 'They are for this month — anything unused goes on %s.', 'oria' ),
				(string) mysql2date( 'j F', (string) ( $membership->cycle_ends_at ?? '' ) )
			),
			'',
			__( 'Have a look at what is open:', 'oria' ),
			\Oria\Pass\Route\url(),
		)
	);
}

/**
 * Where a studio's Pass mail goes.
 *
 * The owner's account address, not the listing's public one: this is
 * operational mail about their bookings, and the public address is
 * sometimes a shared inbox nobody watches.
 */
function provider_email( object $session ): string {
	$owner_id = (int) $session->provider_user_id;

	if ( $owner_id < 1 ) {
		$owner_id = (int) get_post_meta( (int) $session->listing_id, 'claimed_by', true );
	}

	$owner = $owner_id > 0 ? get_userdata( $owner_id ) : null;

	return $owner ? (string) $owner->user_email : '';
}
