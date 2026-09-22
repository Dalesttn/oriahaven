<?php
/**
 * Oria Pass on the existing Stripe billing.
 *
 * No second payment stack. oria-core already runs recurring billing
 * through Stripe Payment Links and one signed webhook at
 * /wp-json/oria/v1/stripe, and that endpoint now fires `oria_stripe_event`
 * for every event it receives. This subscribes, looks for its own
 * reference, and ignores everything else -- so the listing tiers and the
 * Pass share an endpoint without sharing any code.
 *
 * The reference is how Stripe tells us who paid. The listing tiers use
 * L{id}-{tier}; the Pass uses P{user_id}, which their regex will never
 * match and ours will never mistake for a listing.
 *
 *     https://buy.stripe.com/…?client_reference_id=P42
 *
 * Idempotency is the ledger's job, not this file's. Every credit movement
 * carries the Stripe object id that caused it, so a replayed webhook --
 * which Stripe does routinely -- writes nothing the second time.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Stripe;

use Oria\Pass\Credits;
use Oria\Pass\Membership;
use Oria\Pass\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'oria_stripe_event', __NAMESPACE__ . '\handle', 10, 3 );
}

/** P42 → 42. Anything else → 0, including the listing tiers' own refs. */
function user_from_ref( string $ref ): int {
	return preg_match( '/^P(\d+)$/', $ref, $m ) ? (int) $m[1] : 0;
}

/** The buy link with the member's id attached, or '' if not configured. */
function pay_url( int $user_id ): string {
	$link = (string) Settings\get( 'stripe_link' );
	if ( '' === $link || $user_id < 1 ) {
		return '';
	}

	return add_query_arg( 'client_reference_id', 'P' . $user_id, $link );
}

function configured(): bool {
	return '' !== (string) Settings\get( 'stripe_link' );
}

/**
 * @param string $type   Stripe event type.
 * @param array  $object The event's data.object.
 * @param array  $event  The whole event, for its id.
 */
function handle( string $type, array $object, array $event ): void {
	switch ( $type ) {
		case 'checkout.session.completed':
			started( $object );
			break;

		/*
		 * The renewal signal. invoice.paid covers both the first invoice
		 * and every one after it, so the first month's credits and the
		 * twelfth month's arrive by the same path -- one rule to get right
		 * rather than two that can disagree.
		 */
		case 'invoice.paid':
		case 'invoice.payment_succeeded':
			paid( $object );
			break;

		case 'invoice.payment_failed':
			failed( $object );
			break;

		case 'customer.subscription.deleted':
			ended( (string) ( $object['id'] ?? '' ), 'cancelled' );
			break;

		case 'customer.subscription.updated':
			$status = (string) ( $object['status'] ?? '' );
			if ( in_array( $status, array( 'canceled', 'unpaid', 'incomplete_expired' ), true ) ) {
				ended( (string) ( $object['id'] ?? '' ), 'cancelled' );
			} elseif ( 'paused' === $status ) {
				ended( (string) ( $object['id'] ?? '' ), 'paused' );
			}
			break;
	}
}

/**
 * Somebody finished checkout.
 *
 * This only records the membership. It does not hand out credits: the
 * invoice does that, and it arrives for this same checkout moments later.
 * Allocating here as well would give a new member two months on day one.
 */
function started( array $session ): void {
	$ref     = (string) ( $session['client_reference_id'] ?? '' );
	$user_id = user_from_ref( $ref );

	if ( $user_id < 1 || ! get_userdata( $user_id ) ) {
		/*
		 * Somebody has paid and we cannot tell who. That happens when the
		 * Payment Link is opened directly rather than through the button on
		 * the Pass page, which is what attaches the member's id -- and it
		 * used to return in silence, so the money was taken, no credits
		 * arrived, and nothing anywhere said why. It is recorded the same
		 * way an orphan invoice is, because somebody is out of pocket and
		 * the answer has to be findable.
		 */
		error_log( sprintf( '[oria-pass] checkout %s completed with no member behind client_reference_id "%s"', (string) ( $session['id'] ?? '?' ), $ref ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

		update_option(
			'oria_pass_orphan_checkout',
			array(
				'session'      => (string) ( $session['id'] ?? '' ),
				'reference'    => $ref,
				'subscription' => (string) ( $session['subscription'] ?? '' ),
				'customer'     => (string) ( $session['customer'] ?? '' ),
				'email'        => (string) ( $session['customer_details']['email'] ?? '' ),
				'at'           => time(),
			),
			false
		);

		return;
	}

	$subscription = (string) ( $session['subscription'] ?? '' );
	$customer     = (string) ( $session['customer'] ?? '' );

	$membership = Membership\activate( $user_id, $subscription, $customer );

	if ( $membership ) {
		do_action( 'oria_pass_membership_started', $user_id, $membership );

		/*
		 * If the invoice beat us here, its credits are waiting. Settled
		 * after the started hook, so the welcome email goes out before the
		 * one saying the credits have landed.
		 */
		if ( '' !== $subscription ) {
			drain( $subscription, $membership );
		}
	}
}

/**
 * An invoice was paid — the first one or a renewal.
 *
 * The member is found through the subscription, which is the only
 * identifier an invoice carries that we stored. A first invoice can arrive
 * before or after the checkout session depending on Stripe's ordering, so
 * a missing membership is recorded rather than dropped: without it the
 * credits would vanish silently and somebody would have paid for nothing.
 */
/**
 * The subscription an invoice belongs to.
 *
 * Stripe moved this. Older API versions carry it at the top level as
 * `subscription`; newer ones nest it under
 * `parent.subscription_details.subscription`, and a destination pinned to
 * a recent version sends only the new shape. Reading just one of them
 * means a renewal silently grants nothing on half the API versions in
 * existence, so read whichever arrived. It can also come expanded as an
 * object rather than an id.
 */
function subscription_of( array $invoice ): string {
	$candidates = array(
		$invoice['subscription'] ?? null,
		$invoice['parent']['subscription_details']['subscription'] ?? null,
		$invoice['lines']['data'][0]['parent']['subscription_item_details']['subscription'] ?? null,
	);

	foreach ( $candidates as $found ) {
		if ( is_array( $found ) ) {
			$found = $found['id'] ?? '';
		}
		if ( is_string( $found ) && '' !== $found ) {
			return $found;
		}
	}

	return '';
}

/**
 * Invoices that arrived before the member did.
 *
 * Stripe does not promise an order, and in practice invoice.paid often
 * beats checkout.session.completed -- so the credits are granted against
 * a subscription we have never heard of. The old code logged that and
 * gave up, which is how somebody came to hold an active membership with
 * nought credits and no way to tell why.
 *
 * Held here instead, keyed by subscription, and applied the moment the
 * membership shows up. Anything older than a fortnight is dropped: by
 * then it is not a race, it is a mistake, and the log has it.
 */
const PENDING = 'oria_pass_pending_invoices';

function hold( string $subscription, string $invoice_id, ?string $period_end ): void {
	$queue = (array) get_option( PENDING, array() );

	foreach ( $queue as $ref => $row ) {
		if ( ! is_array( $row ) || (int) ( $row['at'] ?? 0 ) < time() - 1209600 ) {
			unset( $queue[ $ref ] );
		}
	}

	$queue[ $subscription ] = array( 'invoice' => $invoice_id, 'period_end' => $period_end, 'at' => time() );

	update_option( PENDING, $queue, false );
}

/** A membership has appeared; settle anything that was waiting for it. */
function drain( string $subscription, object $membership ): void {
	$queue = (array) get_option( PENDING, array() );
	$held  = $queue[ $subscription ] ?? null;

	if ( ! is_array( $held ) || '' === (string) ( $held['invoice'] ?? '' ) ) {
		return;
	}

	unset( $queue[ $subscription ] );
	update_option( PENDING, $queue, false );

	Membership\renew( $membership, (string) $held['invoice'], $held['period_end'] ?? null );

	do_action( 'oria_pass_membership_renewed', (int) $membership->user_id, $membership );
}

function paid( array $invoice ): void {
	$subscription = subscription_of( $invoice );
	$invoice_id   = (string) ( $invoice['id'] ?? '' );
	if ( '' === $subscription || '' === $invoice_id ) {
		/*
		 * An invoice we cannot place. Silence here is expensive: this is
		 * the event that grants the credits, so a member has paid and
		 * received nothing, and Stripe was told 200. Record it with the
		 * keys the invoice did carry, which is usually enough to see why.
		 */
		error_log( sprintf( '[oria-pass] invoice.paid with no subscription to match (invoice "%s", keys: %s)', $invoice_id, implode( ',', array_keys( $invoice ) ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

		update_option(
			'oria_pass_orphan_invoice',
			array( 'invoice' => $invoice_id, 'subscription' => '', 'keys' => array_keys( $invoice ), 'at' => time() ),
			false
		);

		return;
	}

	$period_end = isset( $invoice['lines']['data'][0]['period']['end'] )
		? (string) wp_date( 'Y-m-d H:i:s', (int) $invoice['lines']['data'][0]['period']['end'] )
		: null;

	$membership = Membership\by_subscription( $subscription );
	if ( ! $membership ) {
		// Not lost -- held, and applied when the checkout event lands.
		hold( $subscription, $invoice_id, $period_end );

		error_log( sprintf( '[oria-pass] invoice %s paid before its subscription %s was known; held', $invoice_id, $subscription ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

		return;
	}

	Membership\renew( $membership, $invoice_id, $period_end );

	do_action( 'oria_pass_membership_renewed', (int) $membership->user_id, $membership );
}

/**
 * A card was declined.
 *
 * Marked, not cut off. Stripe retries for days and most recover; taking
 * somebody's credits away on the first decline would punish a bank's fraud
 * check rather than a non-payer. The subscription events above handle the
 * end when Stripe finally gives up.
 */
function failed( array $invoice ): void {
	$membership = Membership\by_subscription( subscription_of( $invoice ) );
	if ( ! $membership ) {
		return;
	}

	Membership\set_status( $membership, 'past_due' );
}

/** The subscription is over. Credits already given are left alone. */
function ended( string $subscription_ref, string $status ): void {
	$membership = Membership\by_subscription( $subscription_ref );
	if ( ! $membership ) {
		return;
	}

	Membership\set_status( $membership, $status );

	do_action( 'oria_pass_membership_ended', (int) $membership->user_id, $membership, $status );
}
