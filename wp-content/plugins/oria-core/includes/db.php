<?php
/**
 * Custom tables, in one place.
 *
 * Almost everything in this directory lives happily in WordPress's own
 * tables — listings are posts, reviews are comments, practices are terms.
 * Three things do not, and they are all about members:
 *
 *   oria_members       profile and standing for a community member. A
 *                      purpose-built table rather than user meta, because
 *                      wp_usermeta is a key-value store: fine for a dozen
 *                      rows per user, poor once every member carries twenty
 *                      attributes and the site wants to query across them.
 *   oria_member_tokens single-use magic links. Rows are short-lived and
 *                      deleted on use, which is the wrong shape for meta.
 *   oria_review_log    who moderated a review, when, and why. Core records
 *                      that a comment's status changed but not by whose hand
 *                      — and that record is the evidence a negative review
 *                      was not quietly removed.
 *
 * Credentials and sessions stay in wp_users. Members authenticate through
 * WordPress; only what WordPress has no place for lives here.
 *
 * Schema changes: bump VERSION and add the column to the CREATE TABLE
 * below. dbDelta compares and alters in place — it never drops a column, so
 * removals have to be done by hand and deliberately.
 */

declare(strict_types=1);

namespace Oria\Core\Db;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION = 1;
const OPTION  = 'oria_db_version';

function bootstrap(): void {
	// Cheap integer comparison on each load; dbDelta only runs when the
	// stored version is behind, which is once per deploy at most.
	add_action( 'plugins_loaded', __NAMESPACE__ . '\maybe_install', 1 );
}

/* ----------------------------------------------------------------- names */

function members(): string {
	global $wpdb;
	return $wpdb->prefix . 'oria_members';
}

function member_tokens(): string {
	global $wpdb;
	return $wpdb->prefix . 'oria_member_tokens';
}

function review_log(): string {
	global $wpdb;
	return $wpdb->prefix . 'oria_review_log';
}

/* --------------------------------------------------------------- install */

function maybe_install(): void {
	if ( (int) get_option( OPTION, 0 ) >= VERSION ) {
		return;
	}
	install();
}

/**
 * Create or upgrade every custom table.
 *
 * Safe to call repeatedly: dbDelta diffs the live schema against the
 * statements below and issues only the ALTERs needed.
 */
function install(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$members = members();
	$tokens  = member_tokens();
	$log     = review_log();

	/*
	 * dbDelta is fussy in ways that are not obvious: two spaces after
	 * PRIMARY KEY, KEY rather than INDEX, one field per line, and the
	 * CREATE TABLE opening on a single line. Reformatting this block
	 * casually will produce a table that silently never upgrades.
	 *
	 * notify_prefs is longtext holding JSON rather than a JSON column:
	 * WordPress core does the same, and it keeps the schema portable
	 * across the MySQL and MariaDB versions shared hosts actually run.
	 */
	$sql = array();

	$sql[] = "CREATE TABLE {$members} (
		member_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		handle varchar(32) NOT NULL,
		display_name varchar(64) NOT NULL,
		avatar_id bigint(20) unsigned NULL DEFAULT NULL,
		suburb varchar(64) NULL DEFAULT NULL,
		bio varchar(280) NULL DEFAULT NULL,
		status varchar(16) NOT NULL DEFAULT 'pending',
		verified_via varchar(16) NOT NULL DEFAULT 'email',
		verified_at datetime NULL DEFAULT NULL,
		reviews_count int(10) unsigned NOT NULL DEFAULT 0,
		helpful_count int(10) unsigned NOT NULL DEFAULT 0,
		reputation int(11) NOT NULL DEFAULT 0,
		notify_prefs longtext NULL DEFAULT NULL,
		created_at datetime NOT NULL,
		last_seen_at datetime NULL DEFAULT NULL,
		PRIMARY KEY  (member_id),
		UNIQUE KEY user_id (user_id),
		UNIQUE KEY handle (handle),
		KEY status (status),
		KEY created_at (created_at)
	) {$charset};";

	$sql[] = "CREATE TABLE {$tokens} (
		token_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		token_hash varchar(64) NOT NULL,
		email varchar(190) NOT NULL,
		purpose varchar(16) NOT NULL,
		payload longtext NULL DEFAULT NULL,
		expires_at datetime NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (token_id),
		UNIQUE KEY token_hash (token_hash),
		KEY email (email),
		KEY expires_at (expires_at)
	) {$charset};";

	$sql[] = "CREATE TABLE {$log} (
		log_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		comment_id bigint(20) unsigned NOT NULL,
		action varchar(32) NOT NULL,
		from_status varchar(20) NULL DEFAULT NULL,
		to_status varchar(20) NULL DEFAULT NULL,
		actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		reason text NULL DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (log_id),
		KEY comment_id (comment_id),
		KEY created_at (created_at)
	) {$charset};";

	foreach ( $sql as $statement ) {
		dbDelta( $statement );
	}

	update_option( OPTION, VERSION, false );
}

/** Has the schema actually landed? Used by the admin screen to warn early. */
function installed(): bool {
	global $wpdb;
	$table = members();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
}
