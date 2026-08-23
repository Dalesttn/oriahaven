<?php
/**
 * Members: the people who read the directory and review what they tried.
 *
 * Deliberately a separate population from practitioners. A practitioner
 * pays to be listed; a member says what a listing was like. Letting one be
 * the other would make every review suspect, so the wall is absolute and
 * enforced server-side in can_review() — not by hiding a button.
 *
 * Identity is split down the middle, for good reasons on both halves:
 *
 *   wp_users      email, the login session, the password that members never
 *                 set. WordPress's authentication is battle-tested and free;
 *                 hand-rolling session handling would be the least safe code
 *                 on the site.
 *   oria_members  everything else — handle, display name, standing, counters,
 *                 preferences. A real table, because this is what community
 *                 features will read and join against, and wp_usermeta is the
 *                 wrong shape for that at any size worth planning for.
 *
 * A member never sees wp-admin. Their entire journey is the front end.
 */

declare(strict_types=1);

namespace Oria\Core\Members;

use Oria\Core\Db;
use Oria\Core\Ownership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ROLE = 'member';

const STATUS_PENDING = 'pending'; // created, email not confirmed yet
const STATUS_ACTIVE  = 'active';
const STATUS_MUTED   = 'muted';   // e.g. became a practitioner — see can_review()
const STATUS_BANNED  = 'banned';

/** Magic links are short-lived on purpose: long enough to walk to the inbox. */
const TOKEN_TTL = 30 * MINUTE_IN_SECONDS;

function bootstrap(): void {
	// plugins_loaded, matching Ownership: the role must exist before
	// WordPress assembles the current user's capabilities.
	add_action( 'plugins_loaded', __NAMESPACE__ . '\ensure_role', 5 );

	add_action( 'admin_init', __NAMESPACE__ . '\block_admin' );
	add_filter( 'show_admin_bar', __NAMESPACE__ . '\hide_admin_bar' );

	/*
	 * The moment anybody is granted the practitioner role — by claiming, by
	 * signing up, by accepting an invite, or by an admin doing it in the
	 * Users screen — their member profile stops being able to post.
	 *
	 * Hooked on the role change rather than patched into each of the three
	 * call sites that grant it today, so a fourth added later cannot quietly
	 * open a hole in the wall.
	 */
	add_action( 'add_user_role', __NAMESPACE__ . '\on_role_granted', 10, 2 );
	add_action( 'set_user_role', __NAMESPACE__ . '\on_role_granted', 10, 2 );

	add_action( 'oria_purge_member_tokens', __NAMESPACE__ . '\purge_tokens' );
	add_action( 'init', __NAMESPACE__ . '\schedule_purge' );
}

/**
 * @param int|string $user_id
 * @param string     $role
 */
function on_role_granted( $user_id, $role ): void {
	if ( Ownership\ROLE === $role ) {
		mute_for_practitioner( (int) $user_id );
	}
}

/* ------------------------------------------------------------------ role */

/**
 * The member role, deliberately weaker than core's subscriber.
 *
 * `read` only. No upload_files: avatars are initials or the picture Google
 * already hosts, so there is no member-writable media library to police.
 */
function ensure_role(): void {
	$role = get_role( ROLE );

	if ( null === $role ) {
		add_role( ROLE, __( 'Member', 'oria' ), array( 'read' => true ) );
		return;
	}

	// Belt and braces against a role edited by hand or by another plugin:
	// a member must never hold anything that opens an admin surface.
	foreach ( array( 'upload_files', 'edit_posts', 'edit_oria_listings', 'manage_options' ) as $cap ) {
		if ( $role->has_cap( $cap ) ) {
			$role->remove_cap( $cap );
		}
	}
}

/** Roles that mean "this person is on the business side of the directory". */
function is_practitioner( int $user_id ): bool {
	$user = get_userdata( $user_id );
	return $user instanceof \WP_User && in_array( Ownership\ROLE, (array) $user->roles, true );
}

/** Staff — anyone who can edit other people's content. */
function is_staff( int $user_id ): bool {
	return user_can( $user_id, 'edit_others_posts' );
}

/** A member and nothing else, which is who gets kept out of wp-admin. */
function is_member_only( int $user_id ): bool {
	$user = get_userdata( $user_id );
	return $user instanceof \WP_User && array( ROLE ) === array_values( (array) $user->roles );
}

/* ------------------------------------------------------- the review rule */

/**
 * May this user post a review?
 *
 * The one place the practitioner wall is enforced. The form, the submit
 * handler and any future REST route all ask this and nothing else, so there
 * is a single answer to audit rather than three that can drift apart.
 *
 * @return true|\WP_Error Error codes are stable and safe to show a visitor.
 */
function can_review( int $user_id = 0 ) {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( $user_id <= 0 ) {
		return new \WP_Error( 'oria_not_signed_in', __( 'Verify your email to post a review.', 'oria' ) );
	}

	if ( ! get_userdata( $user_id ) instanceof \WP_User ) {
		return new \WP_Error( 'oria_no_user', __( 'That account no longer exists.', 'oria' ) );
	}

	/*
	 * Practitioners and staff cannot review anything — not their own
	 * listing, not a competitor's. A directory that sells placement and
	 * lets sellers review each other has reviews worth nothing.
	 */
	if ( is_practitioner( $user_id ) ) {
		return new \WP_Error(
			'oria_practitioner',
			__( 'Practices listed on Oria Haven cannot post reviews. If a review of your practice is wrong, you can reply to it or report it.', 'oria' )
		);
	}

	if ( is_staff( $user_id ) ) {
		return new \WP_Error( 'oria_staff', __( 'Staff accounts cannot post reviews.', 'oria' ) );
	}

	$member = by_user( $user_id );

	if ( null === $member ) {
		return new \WP_Error( 'oria_not_member', __( 'Verify your email to post a review.', 'oria' ) );
	}

	if ( STATUS_ACTIVE !== $member['status'] ) {
		$messages = array(
			STATUS_PENDING => __( 'Confirm your email address to post a review — check your inbox for the link.', 'oria' ),
			STATUS_MUTED   => __( 'This account is now listed as a practice, so it can no longer post reviews.', 'oria' ),
			STATUS_BANNED  => __( 'This account cannot post reviews.', 'oria' ),
		);
		return new \WP_Error( 'oria_member_' . $member['status'], $messages[ $member['status'] ] ?? __( 'This account cannot post reviews.', 'oria' ) );
	}

	return true;
}

/**
 * Can this email address become a member at all?
 *
 * One email is one identity. An address already used by a practice or by
 * staff is refused here rather than quietly creating a second account that
 * would let the wall above be walked around.
 *
 * @return true|\WP_Error
 */
function email_may_join( string $email ) {
	$email = sanitize_email( $email );

	if ( '' === $email || ! is_email( $email ) ) {
		return new \WP_Error( 'oria_bad_email', __( 'That email address does not look right.', 'oria' ) );
	}

	$user = get_user_by( 'email', $email );

	if ( ! $user instanceof \WP_User ) {
		return true; // brand new person
	}

	if ( is_practitioner( (int) $user->ID ) ) {
		return new \WP_Error(
			'oria_practitioner_email',
			__( 'That email is already registered as a practice on Oria Haven. Practices cannot post reviews.', 'oria' )
		);
	}

	if ( is_staff( (int) $user->ID ) ) {
		return new \WP_Error( 'oria_staff_email', __( 'That email belongs to a staff account.', 'oria' ) );
	}

	return true;
}

/**
 * A member who has since claimed or listed a practice.
 *
 * Their reviews stay published — silently deleting them the moment somebody
 * starts paying is exactly the selective removal the ACCC warns about — but
 * they post nothing further. Called from the claim and signup paths.
 */
function mute_for_practitioner( int $user_id ): void {
	$member = by_user( $user_id );
	if ( null !== $member && STATUS_BANNED !== $member['status'] ) {
		update( (int) $member['member_id'], array( 'status' => STATUS_MUTED ) );
	}
}

/* ------------------------------------------------------------------ read */

/** @return array<string,mixed>|null */
function get( int $member_id ): ?array {
	return fetch( 'member_id', $member_id );
}

/** @return array<string,mixed>|null */
function by_user( int $user_id ): ?array {
	return fetch( 'user_id', $user_id );
}

/** @return array<string,mixed>|null */
function by_handle( string $handle ): ?array {
	return fetch( 'handle', sanitize_title( $handle ) );
}

/** @return array<string,mixed>|null */
function by_email( string $email ): ?array {
	$user = get_user_by( 'email', sanitize_email( $email ) );
	return $user instanceof \WP_User ? by_user( (int) $user->ID ) : null;
}

/** The signed-in member, or null for everyone else. @return array<string,mixed>|null */
function current(): ?array {
	$user_id = get_current_user_id();
	return $user_id > 0 ? by_user( $user_id ) : null;
}

/**
 * @param string     $column One of the indexed lookup columns.
 * @param string|int $value
 * @return array<string,mixed>|null
 */
function fetch( string $column, $value ): ?array {
	global $wpdb;

	if ( ! in_array( $column, array( 'member_id', 'user_id', 'handle' ), true ) ) {
		return null;
	}
	if ( '' === $value || null === $value ) {
		return null;
	}

	$table  = Db\members();
	$format = 'handle' === $column ? '%s' : '%d';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE {$column} = {$format} LIMIT 1", $value ),
		ARRAY_A
	);
	// phpcs:enable

	return is_array( $row ) ? $row : null;
}

/* ----------------------------------------------------------------- write */

/**
 * Create a member — the WordPress account and the profile row together.
 *
 * Idempotent on email: an address that already has a member profile is
 * returned rather than duplicated, which is what makes "sign in with the
 * same email twice" behave.
 *
 * @param string $verified_via 'email' (magic link) or 'google'.
 * @return array<string,mixed>|\WP_Error The member row.
 */
function create( string $email, string $display_name = '', string $verified_via = 'email' ) {
	global $wpdb;

	$email = sanitize_email( $email );
	$may   = email_may_join( $email );
	if ( is_wp_error( $may ) ) {
		return $may;
	}

	$existing = by_email( $email );
	if ( null !== $existing ) {
		return $existing;
	}

	$user = get_user_by( 'email', $email );

	if ( $user instanceof \WP_User ) {
		$user_id = (int) $user->ID;
		// An account with no member profile yet — a subscriber from some
		// earlier experiment. Give it the role rather than a second account.
		if ( ! in_array( ROLE, (array) $user->roles, true ) ) {
			$user->add_role( ROLE );
		}
	} else {
		$user_id = wp_insert_user(
			array(
				'user_login'   => unique_login( $email ),
				'user_email'   => $email,
				'display_name' => format_display_name( $display_name ),
				// Members never sign in with a password and are never sent
				// one; the column cannot be empty, so it gets noise.
				'user_pass'    => wp_generate_password( 32 ),
				'role'         => ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		$user_id = (int) $user_id;
	}

	$name = format_display_name( '' !== $display_name ? $display_name : (string) strstr( $email, '@', true ) );
	$now  = current_time( 'mysql', true );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ok = $wpdb->insert(
		Db\members(),
		array(
			'user_id'      => $user_id,
			'handle'       => unique_handle( $name ),
			'display_name' => $name,
			'status'       => STATUS_PENDING,
			'verified_via' => in_array( $verified_via, array( 'email', 'google' ), true ) ? $verified_via : 'email',
			'created_at'   => $now,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( ! $ok ) {
		return new \WP_Error( 'oria_member_insert', __( 'Could not create that account.', 'oria' ) );
	}

	$member = by_user( $user_id );
	return null !== $member ? $member : new \WP_Error( 'oria_member_missing', __( 'Could not read that account back.', 'oria' ) );
}

/**
 * @param array<string,mixed> $fields
 */
function update( int $member_id, array $fields ): bool {
	global $wpdb;

	$allowed = array(
		'handle'        => '%s',
		'display_name'  => '%s',
		'avatar_id'     => '%d',
		'suburb'        => '%s',
		'bio'           => '%s',
		'status'        => '%s',
		'verified_via'  => '%s',
		'verified_at'   => '%s',
		'reviews_count' => '%d',
		'helpful_count' => '%d',
		'reputation'    => '%d',
		'notify_prefs'  => '%s',
		'last_seen_at'  => '%s',
	);

	$data    = array();
	$formats = array();
	foreach ( $fields as $key => $value ) {
		if ( isset( $allowed[ $key ] ) ) {
			$data[ $key ] = $value;
			$formats[]    = $allowed[ $key ];
		}
	}

	if ( ! $data ) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return false !== $wpdb->update( Db\members(), $data, array( 'member_id' => $member_id ), $formats, array( '%d' ) );
}

/** Confirm the email and let them post. */
function activate( int $member_id, string $verified_via = 'email' ): bool {
	return update(
		$member_id,
		array(
			'status'       => STATUS_ACTIVE,
			'verified_via' => in_array( $verified_via, array( 'email', 'google' ), true ) ? $verified_via : 'email',
			'verified_at'  => current_time( 'mysql', true ),
		)
	);
}

function touch( int $member_id ): void {
	update( $member_id, array( 'last_seen_at' => current_time( 'mysql', true ) ) );
}

/* ----------------------------------------------------------- names, handles */

/**
 * "Jessica Miller" becomes "Jess M." — enough to read as a person, not
 * enough to identify one. Full names on a wellness directory invite both
 * impersonation and second thoughts about reviewing honestly.
 */
function format_display_name( string $raw ): string {
	$clean = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $raw ) ) );
	$clean = (string) preg_replace( '/[^\p{L}\p{N}\'\- ]/u', '', $clean );

	if ( '' === $clean ) {
		return __( 'Member', 'oria' );
	}

	$parts = explode( ' ', $clean );
	$first = mb_substr( $parts[0], 0, 40 );

	if ( count( $parts ) === 1 ) {
		return $first;
	}

	$last = (string) end( $parts );
	$initial = mb_substr( $last, 0, 1 );
	// mb_strtoupper is not among the two mb_ functions WordPress polyfills.
	$initial = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initial, 'UTF-8' ) : strtoupper( $initial );

	return $first . ' ' . $initial . '.';
}

/** A URL-safe public handle, unique across members. */
function unique_handle( string $display_name ): string {
	$base = sanitize_title( $display_name );
	if ( '' === $base ) {
		$base = 'member';
	}
	$base   = substr( $base, 0, 26 );
	$handle = $base;

	for ( $i = 2; null !== by_handle( $handle ); $i++ ) {
		$handle = $base . '-' . $i;
		if ( $i > 500 ) { // pathological; fall back to something certainly free
			$handle = $base . '-' . wp_generate_password( 6, false, false );
			break;
		}
	}

	return $handle;
}

/** wp_users.user_login is unique and public-ish; keep it opaque and short. */
function unique_login( string $email ): string {
	$base  = sanitize_user( (string) strstr( $email, '@', true ), true );
	$base  = '' !== $base ? substr( $base, 0, 40 ) : 'member';
	$login = $base;

	for ( $i = 2; username_exists( $login ); $i++ ) {
		$login = $base . $i;
		if ( $i > 500 ) {
			$login = $base . wp_generate_password( 8, false, false );
			break;
		}
	}

	return $login;
}

/* ---------------------------------------------------------------- tokens */

/**
 * Mint a single-use magic link token.
 *
 * Only the hash is stored, exactly as invites.php does it: a leaked database
 * row cannot be turned back into a working link.
 *
 * @param array<string,mixed> $payload Carried through verification — the
 *                                     draft review, where to return to.
 * @return string The token to put in the emailed URL.
 */
function mint_token( string $email, string $purpose = 'verify', array $payload = array(), int $ttl = TOKEN_TTL ): string {
	global $wpdb;

	$token = wp_generate_password( 40, false, false );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->insert(
		Db\member_tokens(),
		array(
			'token_hash' => wp_hash( $token ),
			'email'      => sanitize_email( $email ),
			'purpose'    => $purpose,
			'payload'    => $payload ? wp_json_encode( $payload ) : null,
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
			'created_at' => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	return $token;
}

/**
 * Spend a token. Returns its row once and only once.
 *
 * The delete is what makes it single-use, and it is checked: if another
 * request consumed the row first, that DELETE affects nothing and this call
 * returns null rather than both requests believing they won.
 *
 * @return array<string,mixed>|null
 */
function consume_token( string $token, string $purpose = 'verify' ): ?array {
	global $wpdb;

	if ( '' === $token ) {
		return null;
	}

	$table = Db\member_tokens();
	$hash  = wp_hash( $token );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s AND purpose = %s LIMIT 1", $hash, $purpose ),
		ARRAY_A
	);

	if ( ! is_array( $row ) ) {
		return null;
	}

	$deleted = $wpdb->delete( $table, array( 'token_id' => (int) $row['token_id'] ), array( '%d' ) );
	// phpcs:enable

	if ( ! $deleted ) {
		return null; // someone else got there first
	}

	if ( strtotime( (string) $row['expires_at'] . ' UTC' ) < time() ) {
		return null; // expired, and now also gone
	}

	$row['payload'] = $row['payload'] ? (array) json_decode( (string) $row['payload'], true ) : array();

	return $row;
}

function schedule_purge(): void {
	if ( ! wp_next_scheduled( 'oria_purge_member_tokens' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'oria_purge_member_tokens' );
	}
}

/** Expired tokens are useless; not keeping them is one less thing to leak. */
function purge_tokens(): void {
	global $wpdb;
	$table = Db\member_tokens();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %s", gmdate( 'Y-m-d H:i:s' ) ) );
}

/* ------------------------------------------------------------ admin walls */

/**
 * Members have no business in wp-admin, and landing there would be both
 * confusing and a needless surface.
 *
 * The exemptions are not optional extras. `admin_init` fires on
 * admin-ajax.php and admin-post.php too, and both are front-end plumbing
 * rather than admin screens — admin-post.php is where every form on this
 * site submits. Redirecting there swallows the request before the handler
 * runs, which looks exactly like a button that does nothing: a signed-in
 * member could not post a review at all until this exempted it.
 */
function block_admin(): void {
	if ( ! is_user_logged_in() || is_front_end_endpoint() ) {
		return;
	}

	if ( is_member_only( get_current_user_id() ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
}

/** Endpoints under /wp-admin/ that exist to serve the front end. */
function is_front_end_endpoint(): bool {
	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return true;
	}

	$script = basename( (string) ( $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '' ) );

	return in_array( $script, array( 'admin-post.php', 'admin-ajax.php' ), true );
}

/** @param bool $show */
function hide_admin_bar( $show ): bool {
	return is_user_logged_in() && is_member_only( get_current_user_id() ) ? false : (bool) $show;
}
