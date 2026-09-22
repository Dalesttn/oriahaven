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
	$user_id = user_from_ref( (string) ( $session['client_reference_id'] ?? '' ) );
	if ( $user_id < 1 || ! get_userdata( $user_id ) ) {
		return;
	}

	$subscription = (string) ( $session['subscription'] ?? '' );
	$customer     = (string) ( $session['customer'] ?? '' );

	$membership = Membership\activate( $user_id, $subscription, $customer );

	if ( $membership ) {
		do_action( 'oria_pass_membership_started', $user_id, $membership );
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
function paid( array $invoice ): void {
	$subscription = (string) ( $invoice['subscription'] ?? '' );
	$invoice_id   = (string) ( $invoice['id'] ?? '' );
	if ( '' === $subscription || '' === $invoice_id ) {
		return;
	}

	$membership = Membership\by_subscription( $subscription );
	if ( ! $membership ) {
		error_log( sprintf( '[oria-pass] invoice %s paid for unknown subscription %s', $invoice_id, $subscription ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		update_option( 'oria_pass_orphan_invoice', array( 'invoice' => $invoice_id, 'subscription' => $subscription, 'at' => time() ), false );

		return;
	}

	$period_end = isset( $invoice['lines']['data'][0]['period']['end'] )
		? (string) wp_date( 'Y-m-d H:i:s', (int) $invoice['lines']['data'][0]['period']['end'] )
		: null;

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
	$membership = Membership\by_subscription( (string) ( $invoice['subscription'] ?? '' ) );
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
