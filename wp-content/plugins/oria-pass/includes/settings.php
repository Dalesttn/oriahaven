<?php
/**
 * Every number and every switch the Pass reads, in one place.
 *
 * The prices and credit counts on the landing page are display copy, not a
 * contract: nothing is charged in this phase and no credit exists yet. They
 * live here anyway so that the day they do mean something, there is one
 * place to change them rather than nine templates to grep.
 *
 * MODE is the important one. It decides whether the page asks somebody to
 * join a waitlist, request early access, or buy -- and in this phase only
 * the first two are real. A landing page that offers a Join button leading
 * nowhere is worse than no landing page.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION = 'oria_pass_settings';

/** The three states the product can be in, in the order it grows through them. */
const MODES = array(
	'waitlist' => 'Waitlist — collect interest, nothing is for sale',
	'invite'   => 'Invite only — early access by request',
	'live'     => 'Live — memberships can be bought',
);

function defaults(): array {
	return array(
		'mode'              => 'waitlist',
		'price_display'     => '$99',
		'price_period'      => 'month',
		'credits_per_cycle' => 50,
		'city'              => 'Perth',
		// Phase 3 reads these; they are here now so the policy is written
		// down before anybody has relied on it.
		'cancel_cutoff_hrs' => 12,
		// How long before a session the reminder goes out.
		'reminder_hours'    => 24,
		'credit_expiry'     => 'cycle',
		'support_email'     => (string) get_option( 'admin_email' ),
		'terms_url'         => '',
		'privacy_url'       => '',
		'partner_email'     => (string) get_option( 'admin_email' ),
		// The recurring Stripe Payment Link for the Pass. The member's id
		// is appended as client_reference_id so the webhook knows who paid.
		'stripe_link'       => '',
		/*
		 * The landing page's "what could a mix look like" example: a guide
		 * to relative cost, never a rate card. These are the values the
		 * page has always published; each studio sets its own per session.
		 * Emptying the list removes the example rather than inventing one.
		 */
		'sample_costs'      => array(
			'yoga'    => 8,
			'pilates' => 10,
			'sauna'   => 12,
			'float'   => 20,
		),
	);
}

/**
 * The example credit costs, labelled, at most four, never more than a
 * month's credits each. Empty when there is nothing honest to show.
 *
 * @return array<string, array{label: string, cost: int}>
 */
function sample_costs(): array {
	$labels = array(
		'yoga'    => __( 'Yoga', 'oria' ),
		'pilates' => __( 'Pilates', 'oria' ),
		'sauna'   => __( 'Sauna', 'oria' ),
		'float'   => __( 'Float', 'oria' ),
	);
	$budget = (int) get( 'credits_per_cycle' );
	$out    = array();

	foreach ( (array) get( 'sample_costs' ) as $slug => $cost ) {
		$cost = (int) $cost;
		if ( isset( $labels[ $slug ] ) && $cost > 0 && $cost <= $budget ) {
			$out[ (string) $slug ] = array( 'label' => $labels[ $slug ], 'cost' => $cost );
		}
	}

	return array_slice( $out, 0, 4, true );
}

function bootstrap(): void {
	add_action( 'admin_init', __NAMESPACE__ . '\register' );
}

/** @return array<string, mixed> */
/**
 * What one credit is worth to a member, in dollars.
 *
 * The membership price divided by the credits it buys, and nothing
 * cleverer: it is the only number a studio can check against their own
 * bank statement. Zero when either half is missing, so a caller can tell
 * "we do not know" from "it is free" and say neither out loud.
 */
function credit_value(): float {
	$price   = (float) preg_replace( '/[^0-9.]/', '', (string) get( 'price_display' ) );
	$credits = (int) get( 'credits_per_cycle' );

	return ( $price > 0 && $credits > 0 ) ? round( $price / $credits, 4 ) : 0.0;
}

function all(): array {
	$saved = get_option( OPTION );

	return wp_parse_args( is_array( $saved ) ? $saved : array(), defaults() );
}

/** One setting, falling back to its default rather than to null. */
function get( string $key ) {
	return all()[ $key ] ?? ( defaults()[ $key ] ?? null );
}

function mode(): string {
	$mode = (string) get( 'mode' );

	return isset( MODES[ $mode ] ) ? $mode : 'waitlist';
}

/** Whether anything can actually be bought yet. */
function is_live(): bool {
	return 'live' === mode();
}

/** What the primary button should say, given the mode. */
function cta_label(): string {
	switch ( mode() ) {
		case 'live':
			return __( 'Join Oria Pass', 'oria' );
		case 'invite':
			return __( 'Request early access', 'oria' );
		default:
			return __( 'Join the waitlist', 'oria' );
	}
}

function register(): void {
	register_setting(
		'oria_pass',
		OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize',
			'default'           => defaults(),
		)
	);
}

/**
 * @param mixed $raw
 * @return array<string, mixed>
 */
function sanitize( $raw ): array {
	$in  = is_array( $raw ) ? $raw : array();
	$out = defaults();

	$mode = sanitize_key( (string) ( $in['mode'] ?? '' ) );
	if ( isset( MODES[ $mode ] ) ) {
		$out['mode'] = $mode;
	}

	foreach ( array( 'price_display', 'price_period', 'city' ) as $key ) {
		if ( isset( $in[ $key ] ) ) {
			$out[ $key ] = sanitize_text_field( (string) $in[ $key ] );
		}
	}

	if ( isset( $in['credits_per_cycle'] ) ) {
		$out['credits_per_cycle'] = max( 0, (int) $in['credits_per_cycle'] );
	}
	if ( isset( $in['cancel_cutoff_hrs'] ) ) {
		$out['cancel_cutoff_hrs'] = max( 0, (int) $in['cancel_cutoff_hrs'] );
	}
	if ( isset( $in['credit_expiry'] ) && in_array( $in['credit_expiry'], array( 'cycle', 'rollover' ), true ) ) {
		$out['credit_expiry'] = (string) $in['credit_expiry'];
	}

	foreach ( array( 'support_email', 'partner_email' ) as $key ) {
		$email = sanitize_email( (string) ( $in[ $key ] ?? '' ) );
		if ( is_email( $email ) ) {
			$out[ $key ] = $email;
		}
	}

	foreach ( array( 'terms_url', 'privacy_url', 'stripe_link' ) as $key ) {
		$out[ $key ] = esc_url_raw( (string) ( $in[ $key ] ?? '' ) );
	}

	return $out;
}
