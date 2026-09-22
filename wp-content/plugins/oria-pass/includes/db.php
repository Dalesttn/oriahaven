<?php
/**
 * The Oria Pass schema.
 *
 * Two tables in this phase: what somebody is paying for, and every credit
 * that has ever moved. Custom tables rather than posts and meta because
 * these are transactions -- they are counted, summed and reconciled, never
 * edited or rendered as content, and a credit balance assembled from
 * postmeta is a balance nobody can audit.
 *
 * The ledger is append-only by intent. Nothing in the code updates a row
 * once written; a correction is another row. That is what makes "where did
 * my credits go" answerable a year later, and it is the difference between
 * a balance you can defend and a number in a meta field.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Db;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function memberships(): string {
	global $wpdb;

	return $wpdb->prefix . 'oria_pass_memberships';
}

function ledger(): string {
	global $wpdb;

	return $wpdb->prefix . 'oria_pass_credit_ledger';
}

function sessions(): string {
	global $wpdb;

	return $wpdb->prefix . 'oria_pass_sessions';
}

function bookings(): string {
	global $wpdb;

	return $wpdb->prefix . 'oria_pass_bookings';
}

function install(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$m       = memberships();
	$l       = ledger();
	$s       = sessions();
	$b       = bookings();

	/*
	 * One row per membership, not per cycle. cycle_ends_at is what the
	 * dashboard counts down to and what credit expiry is measured against.
	 *
	 * subscription_ref is Stripe's subscription id, and it is unique: it is
	 * how a renewal or a cancellation finds its way back to a person
	 * without trusting anything the browser sent.
	 */
	dbDelta(
		"CREATE TABLE {$m} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			subscription_ref varchar(190) NOT NULL DEFAULT '',
			customer_ref varchar(190) NOT NULL DEFAULT '',
			plan varchar(60) NOT NULL DEFAULT 'pass',
			status varchar(20) NOT NULL DEFAULT 'active',
			credits_per_cycle int(10) unsigned NOT NULL DEFAULT 0,
			cycle_started_at datetime NULL,
			cycle_ends_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY subscription_ref (subscription_ref),
			KEY user_status (user_id, status)
		) {$charset};"
	);

	/*
	 * balance_after is stored as well as derived. Deriving it on every read
	 * means summing a person's whole history to draw one number; storing it
	 * means the row can be checked against the sum whenever it matters.
	 *
	 * ref is the idempotency key and it is UNIQUE. Stripe retries webhooks
	 * -- that is normal, not a fault -- so "allocate the credits for invoice
	 * in_123" must be safe to run five times. The insert simply fails the
	 * second time and the caller treats that as done.
	 */
	dbDelta(
		"CREATE TABLE {$l} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			membership_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(32) NOT NULL,
			credit_change int(11) NOT NULL,
			balance_after int(11) NOT NULL,
			booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ref varchar(190) NOT NULL DEFAULT '',
			description varchar(255) NOT NULL DEFAULT '',
			expires_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ref (ref),
			KEY user_created (user_id, created_at),
			KEY user_id_id (user_id, id)
		) {$charset};"
	);

	/*
	 * A session is a single sitting a provider has opened to the Pass --
	 * not their whole class. pass_capacity is the number of places they are
	 * willing to give; booked_count is how many have gone. Both are kept on
	 * the row so capacity can be checked and claimed under one lock rather
	 * than counted from the bookings table on every attempt.
	 *
	 * credits_required and provider_payout are deliberately unrelated. What
	 * a member spends and what a studio is paid are two decisions, and
	 * tying them together now would make every future price change a
	 * negotiation.
	 */
	dbDelta(
		"CREATE TABLE {$s} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id bigint(20) unsigned NOT NULL,
			provider_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL DEFAULT '',
			category varchar(80) NOT NULL DEFAULT '',
			start_at datetime NOT NULL,
			end_at datetime NULL,
			total_capacity int(10) unsigned NOT NULL DEFAULT 0,
			pass_capacity int(10) unsigned NOT NULL DEFAULT 0,
			booked_count int(10) unsigned NOT NULL DEFAULT 0,
			credits_required int(10) unsigned NOT NULL DEFAULT 0,
			provider_payout decimal(10,2) NOT NULL DEFAULT 0.00,
			cancel_cutoff_hours int(10) unsigned NOT NULL DEFAULT 12,
			status varchar(20) NOT NULL DEFAULT 'active',
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY listing_start (listing_id, start_at),
			KEY status_start (status, start_at)
		) {$charset};"
	);

	/*
	 * One row per place taken. The reference is what a member reads out at
	 * the door, so it is short and unambiguous rather than the row id.
	 *
	 * session_user is UNIQUE: a person cannot hold the same session twice,
	 * and that is enforced by the database rather than by a check that
	 * could race two taps against each other.
	 */
	dbDelta(
		"CREATE TABLE {$b} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_reference varchar(20) NOT NULL,
			session_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			credits_spent int(10) unsigned NOT NULL DEFAULT 0,
			provider_payout decimal(10,2) NOT NULL DEFAULT 0.00,
			status varchar(24) NOT NULL DEFAULT 'confirmed',
			booked_at datetime NOT NULL,
			cancelled_at datetime NULL,
			attended_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY booking_reference (booking_reference),
			UNIQUE KEY session_user (session_id, user_id),
			KEY user_status (user_id, status),
			KEY session_status (session_id, status)
		) {$charset};"
	);
}

