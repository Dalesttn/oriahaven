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
	add_action( 'oria_pass_membership_past_due', __NAMESPACE__ . '\past_due', 10, 2 );
	add_action( 'oria_pass_membership_ended', __NAMESPACE__ . '\ended', 10, 3 );
	add_action( 'oria_pass_session_moved', __NAMESPACE__ . '\moved', 10, 2 );
	add_action( 'oria_pass_session_called_off', __NAMESPACE__ . '\called_off', 10, 2 );
	add_action( 'oria_pass_marked', __NAMESPACE__ . '\marked', 10, 2 );
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

/**
 * A card was declined.
 *
 * Said without alarm and without threat. Stripe retries for days and most
 * of these recover on their own, so the useful thing is to say what
 * happened, what it means for their credits, and that there is nothing to
 * do unless it keeps failing.
 */
function past_due( int $user_id, $membership ): void {
	$member = get_userdata( $user_id );
	if ( ! $member ) {
		return;
	}

	send(
		(string) $member->user_email,
		__( 'A payment for your Oria Pass did not go through', 'oria' ),
		__( 'That payment did not go through', 'oria' ),
		array(
			__( 'Your bank turned down this month&#8217;s Oria Pass payment. It is usually nothing — an expired card, or a fraud check on a payment it has not seen before.', 'oria' ),
			'',
			__( 'Your credits are untouched and anything you have booked stands. We will try again over the next few days, so there is nothing to do unless it keeps failing.', 'oria' ),
			'',
			__( 'If you would rather sort it now, update your card through the payment receipt Stripe emailed you.', 'oria' ),
		)
	);
}

/** The Pass has stopped. */
function ended( int $user_id, $membership, string $status ): void {
	$member = get_userdata( $user_id );
	if ( ! $member || 'paused' === $status ) {
		return;
	}

	$balance = Credits\balance( $user_id );

	send(
		(string) $member->user_email,
		__( 'Your Oria Pass has ended', 'oria' ),
		__( 'Your Pass has ended', 'oria' ),
		array(
			__( 'Your Oria Pass is now closed and you will not be charged again.', 'oria' ),
			'',
			$balance > 0
				? sprintf(
					/* translators: %d: credits */
					__( 'Anything you have already booked still stands. The %d credits left on the account cannot be spent now the Pass has ended.', 'oria' ),
					$balance
				)
				: __( 'Anything you have already booked still stands.', 'oria' ),
			'',
			__( 'If you come back, the places are still here:', 'oria' ),
			\Oria\Pass\Route\url(),
		)
	);
}

/**
 * The session moved.
 *
 * Everyone holding a place is told, with the old time as well as the new
 * one: somebody skimming needs to recognise which booking this is, and
 * "we have moved it to Thursday" is useless to a reader who had it down
 * for Thursday already. The cancellation window is restated because
 * whether the new time suits them is exactly the decision it governs.
 */
function moved( $session, $before ): void {
	if ( ! $session || ! $before ) {
		return;
	}

	$was = date_create_immutable( (string) $before->start_at, wp_timezone() );

	foreach ( Booking\for_session( (int) $session->id ) as $booking ) {
		if ( 'confirmed' !== (string) $booking->status ) {
			continue;
		}

		$member = get_userdata( (int) $booking->user_id );
		if ( ! $member ) {
			continue;
		}

		send(
			(string) $member->user_email,
			__( 'A session you booked has moved', 'oria' ),
			__( 'That one has moved', 'oria' ),
			array(
				sprintf(
					/* translators: %s: session title */
					__( 'The studio has changed the time of %s.', 'oria' ),
					(string) $session->title
				),
				'',
				$was
					? sprintf(
						/* translators: %s: the old date and time */
						__( 'It was %s.', 'oria' ),
						wp_date( 'D j M · g:ia', $was->getTimestamp() )
					)
					: '',
				sprintf(
					/* translators: %s: the new date and time */
					__( 'It is now %s.', 'oria' ),
					Sessions\when( $session )
				),
				where( $session ),
				'',
				sprintf(
					/* translators: %s: booking reference */
					__( 'Your place is still held and your reference has not changed: %s.', 'oria' ),
					(string) $booking->booking_reference
				),
				'',
				__( 'If the new time does not suit, you can give the place back and take the credits with you.', 'oria' ),
				cutoff_line( $session ),
			)
		);
	}
}

/**
 * The studio called a session off.
 *
 * Members have already been told one by one through the cancellation
 * hook; this is the studio's own receipt, so they know how many people
 * that was without counting rows themselves.
 */
function called_off( $session, int $told ): void {
	$owner = $session ? provider_email( $session ) : '';
	if ( '' === $owner ) {
		return;
	}

	send(
		$owner,
		__( 'You called off an Oria Pass session', 'oria' ),
		__( 'That session is off', 'oria' ),
		array(
			sprintf(
				/* translators: %s: session title */
				__( '%s has been called off and is no longer on offer.', 'oria' ),
				(string) $session->title
			),
			Sessions\when( $session ),
			'',
			$told > 0
				? sprintf(
					/* translators: %d: number of members */
					_n( '%d member had booked. They have been told and their credits are back.', '%d members had booked. They have been told and their credits are back.', $told, 'oria' ),
					$told
				)
				: __( 'Nobody had booked, so there was nobody to tell.', 'oria' ),
		)
	);
}

/**
 * Marked at the door.
 *
 * Only a no-show is worth an email. "You attended" tells somebody what
 * they already know; "you were marked as not turning up, and the credits
 * stayed spent" is a charge they might disagree with, and they should
 * hear it from us rather than notice it in their balance.
 */
function marked( $booking, string $status ): void {
	if ( ! $booking || 'no_show' !== $status ) {
		return;
	}

	$member = get_userdata( (int) $booking->user_id );
	if ( ! $member ) {
		return;
	}

	$session = Sessions\get( (int) $booking->session_id );

	send(
		(string) $member->user_email,
		__( 'Your Oria Pass place was marked as missed', 'oria' ),
		__( 'Marked as missed', 'oria' ),
		array(
			$session
				? sprintf(
					/* translators: 1: session title, 2: when it ran */
					__( 'The studio has marked your place on %1$s (%2$s) as not taken up.', 'oria' ),
					(string) $session->title,
					Sessions\when( $session )
				)
				: __( 'The studio has marked one of your places as not taken up.', 'oria' ),
			'',
			sprintf(
				/* translators: %d: credits */
				__( 'The %d credits stay spent — the place was held all session and nobody else could take it.', 'oria' ),
				(int) $booking->credits_spent
			),
			'',
			__( 'If you were there and this is wrong, reply to this email and we will put it right with the studio.', 'oria' ),
		)
	);
}
