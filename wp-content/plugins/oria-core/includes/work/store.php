<?php
/**
 * Work in Wellness: the three tables.
 *
 * Applications, alerts and reports are rows, not posts: they are many, they
 * are private, and none of them should ever have a URL. One applications
 * table carries both kinds -- a job application and a shift "I'm
 * available" -- because the employer works through them in one list and
 * the statuses overlap.
 *
 * No raw IP address is stored anywhere (a report keeps a salted hash, for
 * throttling only).
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work\Store;

use const Oria\Core\Work\STATUSES;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION = '2';
const OPTION  = 'oria_work_db_v';

/** Saved practitioners (brief sections 67-68): an employer's private lists, notes and status. */
function talent(): string {
	global $wpdb;
	return $wpdb->prefix . 'oria_work_talent';
}

/** An employer inviting a practitioner to one job or shift; one row each, ever. */
function invites(): string {
	global $wpdb;
	return $wpdb->prefix . 'oria_work_invites';
}

/** Statuses an employer can give a saved practitioner. */
const TALENT_STATUS = array(
	'saved'     => 'Saved',
	'contacted' => 'Contacted',
	'shortlist' => 'Shortlisted',
	'hired'     => 'Hired',
	'not_now'   => 'Not now',
);

function apps(): string {
	global $wpdb;
	return $wpdb->prefix . 'oria_work_applications';
}

function alerts(): string {
	global $wpdb;
	return $wpdb->prefix . 'oria_work_alerts';
}

function reports(): string {
	global $wpdb;
	return $wpdb->prefix . 'oria_work_reports';
}

function maybe_install(): void {
	if ( get_option( OPTION ) !== VERSION ) {
		install();
	}
}

/** dbDelta wants two spaces after PRIMARY KEY and one field per line. */
function install(): void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	dbDelta(
		'CREATE TABLE ' . apps() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kind varchar(8) NOT NULL DEFAULT 'job',
  post_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
  message text NULL,
  rate varchar(80) NOT NULL DEFAULT '',
  availability varchar(160) NOT NULL DEFAULT '',
  cv_id bigint(20) unsigned NOT NULL DEFAULT 0,
  source varchar(20) NOT NULL DEFAULT 'oria',
  status varchar(16) NOT NULL DEFAULT 'new',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY one_each (kind,post_id,user_id),
  KEY by_post (post_id,status),
  KEY by_user (user_id)
) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . alerts() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  kind varchar(8) NOT NULL DEFAULT 'job',
  profession bigint(20) unsigned NOT NULL DEFAULT 0,
  area bigint(20) unsigned NOT NULL DEFAULT 0,
  employment varchar(40) NOT NULL DEFAULT '',
  keyword varchar(80) NOT NULL DEFAULT '',
  frequency varchar(10) NOT NULL DEFAULT 'daily',
  token varchar(40) NOT NULL DEFAULT '',
  active tinyint(1) NOT NULL DEFAULT 1,
  last_sent datetime NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY by_user (user_id),
  KEY by_freq (active,frequency),
  KEY by_token (token)
) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . reports() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  reason varchar(20) NOT NULL DEFAULT '',
  note varchar(500) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  ip_hash char(64) NOT NULL DEFAULT '',
  status varchar(12) NOT NULL DEFAULT 'new',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY by_post (post_id),
  KEY by_status (status)
) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . talent() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  employer bigint(20) unsigned NOT NULL,
  profile_id bigint(20) unsigned NOT NULL,
  list_name varchar(60) NOT NULL DEFAULT 'Saved',
  note varchar(1000) NOT NULL DEFAULT '',
  status varchar(12) NOT NULL DEFAULT 'saved',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY one_each (employer,profile_id,list_name),
  KEY by_employer (employer)
) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . invites() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  employer bigint(20) unsigned NOT NULL,
  profile_id bigint(20) unsigned NOT NULL,
  post_id bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY one_each (profile_id,post_id),
  KEY by_employer (employer,created_at),
  KEY by_profile (profile_id)
) $c;"
	);

	update_option( OPTION, VERSION, false );
}

/* ------------------------------------------------------------ saved talent */

/** Save (or move/update) a practitioner on one of an employer's lists. */
function save_talent( int $employer, int $profile, string $list, string $note = '', string $status = 'saved' ): bool {
	global $wpdb;
	$list   = mb_substr( trim( $list ) ?: 'Saved', 0, 60 );
	$status = isset( TALENT_STATUS[ $status ] ) ? $status : 'saved';
	$now    = current_time( 'mysql' );
	$id     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . talent() . ' WHERE employer = %d AND profile_id = %d AND list_name = %s', $employer, $profile, $list ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	if ( $id ) {
		return false !== $wpdb->update( talent(), array( 'note' => mb_substr( $note, 0, 1000 ), 'status' => $status, 'updated_at' => $now ), array( 'id' => $id ) );
	}
	return (bool) $wpdb->insert( talent(), array( 'employer' => $employer, 'profile_id' => $profile, 'list_name' => $list, 'note' => mb_substr( $note, 0, 1000 ), 'status' => $status, 'created_at' => $now, 'updated_at' => $now ) );
}

function remove_talent( int $id, int $employer ): void {
	global $wpdb;
	$wpdb->delete( talent(), array( 'id' => $id, 'employer' => $employer ) );
}

/** An employer's saved practitioners, grouped: list name => rows. */
function talent_lists( int $employer ): array {
	global $wpdb;
	$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . talent() . ' WHERE employer = %d ORDER BY list_name, updated_at DESC', $employer ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$out  = array();
	foreach ( $rows as $r ) {
		$out[ $r['list_name'] ][] = $r;
	}
	return $out;
}

/** The list names an employer has used, for the "save to" picker. */
function list_names( int $employer ): array {
	global $wpdb;
	return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT list_name FROM ' . talent() . ' WHERE employer = %d ORDER BY list_name', $employer ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/** Lists this practitioner is on for this employer. */
function lists_with( int $employer, int $profile ): array {
	global $wpdb;
	return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT list_name FROM ' . talent() . ' WHERE employer = %d AND profile_id = %d', $employer, $profile ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/* ----------------------------------------------------------------- invites */

/** Record an invite. False if this practitioner was already invited to this post. */
function add_invite( int $employer, int $profile, int $post ): bool {
	global $wpdb;
	if ( $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . invites() . ' WHERE profile_id = %d AND post_id = %d', $profile, $post ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
		return false;
	}
	return (bool) $wpdb->insert( invites(), array( 'employer' => $employer, 'profile_id' => $profile, 'post_id' => $post, 'created_at' => current_time( 'mysql' ) ) );
}

/** Invites an employer has sent in the last 24 hours (the spam limit reads this). */
function invites_today( int $employer ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . invites() . ' WHERE employer = %d AND created_at > %s', $employer, wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function invites_for_profile( int $profile ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . invites() . ' WHERE profile_id = %d', $profile ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/** Post ids this practitioner has already been invited to by this employer. */
function invited_to( int $employer, int $profile ): array {
	global $wpdb;
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM ' . invites() . ' WHERE employer = %d AND profile_id = %d', $employer, $profile ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/* ------------------------------------------------------------ applications */

/** One application row, or null. */
function app( int $id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . apps() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	return $row ?: null;
}

/** This user's application to this post, or null. */
function app_for( string $kind, int $post_id, int $user_id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . apps() . ' WHERE kind = %s AND post_id = %d AND user_id = %d', $kind, $post_id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	return $row ?: null;
}

/** Create an application. Returns the new id, or 0 if one already exists. */
function add_app( array $a ): int {
	global $wpdb;
	// The unique key is the real guard; asking first keeps a double-click from logging a DB error.
	if ( app_for( (string) $a['kind'], (int) $a['post_id'], (int) $a['user_id'] ) ) {
		return 0;
	}
	$now = current_time( 'mysql' );
	$ok  = $wpdb->insert(
		apps(),
		array(
			'kind'         => $a['kind'],
			'post_id'      => (int) $a['post_id'],
			'user_id'      => (int) $a['user_id'],
			'profile_id'   => (int) ( $a['profile_id'] ?? 0 ),
			'message'      => (string) ( $a['message'] ?? '' ),
			'rate'         => (string) ( $a['rate'] ?? '' ),
			'availability' => (string) ( $a['availability'] ?? '' ),
			'cv_id'        => (int) ( $a['cv_id'] ?? 0 ),
			'source'       => (string) ( $a['source'] ?? 'oria' ),
			'status'       => 'new',
			'created_at'   => $now,
			'updated_at'   => $now,
		)
	);
	return $ok ? (int) $wpdb->insert_id : 0;
}

function set_status( int $id, string $status ): bool {
	global $wpdb;
	if ( ! isset( STATUSES[ $status ] ) ) {
		return false;
	}
	return false !== $wpdb->update( apps(), array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
}

/** Applications to one post, newest first. */
function apps_for_post( int $post_id ): array {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . apps() . " WHERE post_id = %d AND status <> 'withdrawn' ORDER BY created_at DESC", $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/** A user's own applications, newest first. */
function apps_by_user( int $user_id ): array {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . apps() . ' WHERE user_id = %d ORDER BY created_at DESC', $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/** Count per post, for dashboards: post_id => [total, new]. */
function counts( array $post_ids ): array {
	global $wpdb;
	$post_ids = array_filter( array_map( 'intval', $post_ids ) );
	if ( ! $post_ids ) {
		return array();
	}
	$in   = implode( ',', $post_ids );
	$rows = (array) $wpdb->get_results( 'SELECT post_id, COUNT(*) AS n, SUM(status = \'new\') AS fresh, SUM(status IN (\'confirmed\',\'hired\')) AS filled FROM ' . apps() . " WHERE post_id IN ($in) AND status <> 'withdrawn' GROUP BY post_id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$out  = array();
	foreach ( $rows as $r ) {
		$out[ (int) $r['post_id'] ] = array( 'n' => (int) $r['n'], 'fresh' => (int) $r['fresh'], 'filled' => (int) $r['filled'] );
	}
	return $out;
}

/* ----------------------------------------------------------------- alerts */

function add_alert( array $a ): int {
	global $wpdb;
	$ok = $wpdb->insert(
		alerts(),
		array(
			'user_id'    => (int) $a['user_id'],
			'kind'       => in_array( $a['kind'] ?? '', array( 'job', 'shift' ), true ) ? $a['kind'] : 'job',
			'profession' => (int) ( $a['profession'] ?? 0 ),
			'area'       => (int) ( $a['area'] ?? 0 ),
			'employment' => (string) ( $a['employment'] ?? '' ),
			'keyword'    => (string) ( $a['keyword'] ?? '' ),
			'frequency'  => in_array( $a['frequency'] ?? '', array( 'immediate', 'daily', 'weekly' ), true ) ? $a['frequency'] : 'daily',
			'token'      => wp_generate_password( 32, false, false ),
			'active'     => 1,
			'created_at' => current_time( 'mysql' ),
		)
	);
	return $ok ? (int) $wpdb->insert_id : 0;
}

function alerts_by_user( int $user_id ): array {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . alerts() . ' WHERE user_id = %d AND active = 1 ORDER BY created_at DESC', $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function alerts_due( string $frequency ): array {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . alerts() . ' WHERE active = 1 AND frequency = %s', $frequency ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function delete_alert( int $id, int $user_id ): void {
	global $wpdb;
	$wpdb->update( alerts(), array( 'active' => 0 ), array( 'id' => $id, 'user_id' => $user_id ) );
}

/** One-click unsubscribe from an email link. Returns true if a live alert was switched off. */
function alert_off_by_token( string $token ): bool {
	global $wpdb;
	if ( strlen( $token ) < 20 ) {
		return false;
	}
	return (bool) $wpdb->update( alerts(), array( 'active' => 0 ), array( 'token' => $token, 'active' => 1 ) );
}

function alert_sent( int $id ): void {
	global $wpdb;
	$wpdb->update( alerts(), array( 'last_sent' => current_time( 'mysql' ) ), array( 'id' => $id ) );
}

/* ---------------------------------------------------------------- reports */

function add_report( int $post_id, string $reason, string $note, int $user_id, string $ip_hash ): int {
	global $wpdb;
	$ok = $wpdb->insert(
		reports(),
		array(
			'post_id'    => $post_id,
			'reason'     => $reason,
			'note'       => mb_substr( $note, 0, 500 ),
			'user_id'    => $user_id,
			'ip_hash'    => $ip_hash,
			'status'     => 'new',
			'created_at' => current_time( 'mysql' ),
		)
	);
	return $ok ? (int) $wpdb->insert_id : 0;
}

function reports_list( string $status = 'new' ): array {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . reports() . ' WHERE status = %s ORDER BY created_at DESC LIMIT 200', $status ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function report_status( int $id, string $status ): void {
	global $wpdb;
	$wpdb->update( reports(), array( 'status' => $status ), array( 'id' => $id ) );
}

function open_reports_for( int $post_id ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . reports() . " WHERE post_id = %d AND status = 'new'", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}
