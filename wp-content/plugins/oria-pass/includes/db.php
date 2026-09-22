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

function install(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$m       = memberships();
	$l       = ledger();

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
}
