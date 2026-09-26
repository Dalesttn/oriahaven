<?php
/**
 * The waitlist: who wants this, where they are, and what they want to try.
 *
 * Its own table rather than a custom post type. These are not content --
 * nobody edits them, nothing renders them, and there will be one row per
 * interested person rather than one per thing worth reading. A table also
 * makes the only question anybody will ask of this data ("how many people
 * in Fremantle want sauna?") a single query instead of a meta join.
 *
 * Consent is stored, not assumed: the checkbox is required, and what it
 * said at the time is written to the row. An address collected for a
 * waitlist is not an address you may later use for anything else, and the
 * row has to be able to prove what was agreed to.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Waitlist;

use Oria\Pass\Route;
use Oria\Pass\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** What somebody can say they are interested in. Slug => label. */
const INTERESTS = array(
	'yoga'          => 'Yoga',
	'pilates'       => 'Pilates',
	'sauna'         => 'Sauna',
	'float'         => 'Float',
	'breathwork'    => 'Breathwork',
	'meditation'    => 'Meditation',
	'sound-healing' => 'Sound healing',
	'recovery'      => 'Recovery',
	'workshops'     => 'Workshops',
	'other'         => 'Something else',
);

function table(): string {
	global $wpdb;

	return $wpdb->prefix . 'oria_pass_waitlist';
}

function create_table(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$table   = table();

	/*
	 * kind separates the two audiences that arrive through the same page:
	 * 'member' is somebody who wants to buy one, 'partner' is a business
	 * offering capacity. They want different follow-up, and keeping them in
	 * one table with a column beats two near-identical tables.
	 */
	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		kind varchar(20) NOT NULL DEFAULT 'member',
		name varchar(120) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		suburb varchar(120) NOT NULL DEFAULT '',
		business varchar(190) NOT NULL DEFAULT '',
		interests text NULL,
		note text NULL,
		consent_text text NULL,
		source varchar(190) NOT NULL DEFAULT '',
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY kind_created (kind, created_at),
		UNIQUE KEY kind_email (kind, email)
	) {$charset};";

	dbDelta( $sql );
}

function bootstrap(): void {
	add_action( 'admin_post_nopriv_oria_pass_waitlist', __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_oria_pass_waitlist', __NAMESPACE__ . '\handle' );
}

/** How many people have put their hand up, by kind. */
function count_of( string $kind = 'member' ): int {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . table() . ' WHERE kind = %s', $kind ) );
}

/** @return array<int, object> */
function recent( string $kind = '', int $limit = 200 ): array {
	global $wpdb;

	$sql = 'SELECT * FROM ' . table();
	if ( '' !== $kind ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		return (array) $wpdb->get_results( $wpdb->prepare( $sql . ' WHERE kind = %s ORDER BY created_at DESC LIMIT %d', $kind, $limit ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return (array) $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY created_at DESC LIMIT %d', $limit ) );
}

/**
 * Back to the form that was sent, with its state.
 *
 * On an error the person's answers go with them, so a refused submission
 * does not also cost them their typing: a short-lived transient, keyed by
 * a random token in the address, read once by the template. Nothing is
 * kept after ten minutes, and nothing at all on success.
 */
function back( array $args, string $kind = 'member', bool $keep = false ): void {
	if ( $keep ) {
		$token = strtolower( wp_generate_password( 20, false ) );
		set_transient( 'oria_pw_' . $token, kept_values(), 10 * MINUTE_IN_SECONDS );
		$args['pv'] = $token;
	}

	$base = 'partner' === $kind ? Route\url( 'partners' ) : Route\url();
	wp_safe_redirect( add_query_arg( $args, $base ) . '#pass-join' );
	exit;
}

/** What was typed, cleaned, for re-filling the form after an error. */
function kept_values(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- handed back to the same person only.
	$interests = array();
	foreach ( (array) ( $_POST['interests'] ?? array() ) as $one ) {
		$one = sanitize_key( (string) $one );
		if ( isset( INTERESTS[ $one ] ) ) {
			$interests[] = $one;
		}
	}

	$out = array(
		'name'      => sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? '' ) ) ),
		'email'     => sanitize_text_field( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ),
		'suburb'    => sanitize_text_field( wp_unslash( (string) ( $_POST['suburb'] ?? '' ) ) ),
		'business'  => sanitize_text_field( wp_unslash( (string) ( $_POST['business'] ?? '' ) ) ),
		'note'      => sanitize_textarea_field( wp_unslash( (string) ( $_POST['note'] ?? '' ) ) ),
		'interests' => $interests,
	);
	// phpcs:enable

	return $out;
}

/**
 * The answers kept by back(), once. Empty when there are none.
 *
 * @return array<string, mixed>
 */
function recall(): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a random token, display only.
	$token = sanitize_key( (string) ( $_GET['pv'] ?? '' ) );
	if ( '' === $token ) {
		return array();
	}
	$kept = get_transient( 'oria_pw_' . $token );
	delete_transient( 'oria_pw_' . $token );

	return is_array( $kept ) ? $kept : array();
}

/**
 * One submission.
 *
 * The same spam walls the rest of the site uses -- nonce, honeypot, a
 * minimum time on the form -- because this endpoint is public and an email
 * list is exactly what a bot wants to fill.
 */
function handle(): void {
	$kind = 'partner' === ( $_POST['kind'] ?? '' ) ? 'partner' : 'member';

	if ( ! wp_verify_nonce( (string) ( $_POST['oria_pass_nonce'] ?? '' ), 'oria_pass_waitlist' ) ) {
		back( array( 'pass' => 'expired' ), $kind, true );
	}
	if ( '' !== (string) ( $_POST['oria_website'] ?? '' ) ) {
		back( array( 'pass' => 'done', 'kind' => $kind ), $kind ); // A bot is told it worked.
	}
	if ( time() - (int) ( $_POST['oria_ts'] ?? 0 ) < 3 ) {
		back( array( 'pass' => 'spam' ), $kind, true );
	}

	$name  = sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? '' ) ) );
	$email = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );

	if ( '' === $name ) {
		back( array( 'pass' => 'name' ), $kind, true );
	}
	if ( ! is_email( $email ) ) {
		back( array( 'pass' => 'email' ), $kind, true );
	}
	if ( empty( $_POST['consent'] ) ) {
		back( array( 'pass' => 'consent' ), $kind, true );
	}

	$interests = array();
	foreach ( (array) ( $_POST['interests'] ?? array() ) as $one ) {
		$one = sanitize_key( (string) $one );
		if ( isset( INTERESTS[ $one ] ) ) {
			$interests[] = $one;
		}
	}

	global $wpdb;

	$row = array(
		'kind'         => $kind,
		'name'         => $name,
		'email'        => $email,
		'suburb'       => sanitize_text_field( wp_unslash( (string) ( $_POST['suburb'] ?? '' ) ) ),
		'business'     => sanitize_text_field( wp_unslash( (string) ( $_POST['business'] ?? '' ) ) ),
		'interests'    => implode( ',', $interests ),
		'note'         => sanitize_textarea_field( wp_unslash( (string) ( $_POST['note'] ?? '' ) ) ),
		'consent_text' => consent_text( $kind ),
		'source'       => Settings\mode(),
		'user_id'      => get_current_user_id(),
		'created_at'   => current_time( 'mysql' ),
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ok = $wpdb->insert( table(), $row );

	/*
	 * A duplicate is the same person pressing the button twice, or coming
	 * back a week later. It is not an error worth showing them -- they are
	 * on the list either way -- so the unique key does the de-duplication
	 * quietly and the thank-you is the same.
	 */
	if ( false === $ok && ! str_contains( (string) $wpdb->last_error, 'Duplicate entry' ) ) {
		back( array( 'pass' => 'server' ), $kind, true );
	}

	notify( $row );

	back( array( 'pass' => 'done', 'kind' => $kind ), $kind );
}

/** Exactly what the person agreed to, kept with the row. */
function consent_text( string $kind ): string {
	return 'partner' === $kind
		? __( 'I would like Oria Haven to contact me about becoming an Oria Pass partner.', 'oria' )
		: __( 'I would like Oria Haven to email me when Oria Pass opens, and about my place on the waitlist.', 'oria' );
}

/** Tell somebody a person is waiting. No auto-reply yet: there is nothing to say. */
function notify( array $row ): void {
	$to = 'partner' === $row['kind']
		? (string) Settings\get( 'partner_email' )
		: (string) Settings\get( 'support_email' );

	if ( ! is_email( $to ) ) {
		return;
	}

	$lines = array(
		'partner' === $row['kind'] ? 'A business asked about Oria Pass.' : 'Someone joined the Oria Pass waitlist.',
		'',
		'Name: ' . $row['name'],
		'Email: ' . $row['email'],
	);
	if ( '' !== $row['business'] ) {
		$lines[] = 'Business: ' . $row['business'];
	}
	if ( '' !== $row['suburb'] ) {
		$lines[] = 'Suburb: ' . $row['suburb'];
	}
	if ( '' !== $row['interests'] ) {
		$lines[] = 'Interested in: ' . $row['interests'];
	}
	if ( '' !== $row['note'] ) {
		$lines[] = '';
		$lines[] = $row['note'];
	}

	wp_mail(
		$to,
		'partner' === $row['kind'] ? '[Oria Pass] Partner enquiry' : '[Oria Pass] New waitlist signup',
		implode( "\n", $lines )
	);
}
