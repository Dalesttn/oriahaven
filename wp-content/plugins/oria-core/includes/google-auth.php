<?php
/**
 * "Continue with Google" — the fast door into a member account.
 *
 * Standard OpenID Connect authorization-code flow, with PKCE and a state
 * bound to the browser that started it. Two routes:
 *
 *   /auth/google/start/     mint state, nonce and a PKCE verifier, then
 *                           hand the visitor to Google.
 *   /auth/google/callback/  check everything came back the way it left,
 *                           swap the code for an identity, sign them in.
 *
 * On the ID token signature: it is not verified against Google's public
 * keys, and deliberately. The token is not read from the browser — it comes
 * back on a direct server-to-server HTTPS request to Google's token
 * endpoint, authenticated with the client secret. OpenID Connect §3.1.3.7
 * allows TLS server validation to stand in for signature checking in
 * exactly this case, and it saves fetching and caching a JWKS. The claims
 * inside are still checked, one by one, below.
 *
 * Nothing from Google is kept beyond the request. Oria needs to know who
 * somebody is once; it has no business holding an access token afterwards.
 */

declare(strict_types=1);

namespace Oria\Core\GoogleAuth;

use Oria\Core\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PATH      = 'auth/google';
const QUERY_VAR = 'oria_google_step';
const REWRITE_V = '1';

const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
const SCOPES         = 'openid email profile';

const STATE_TTL   = 10 * MINUTE_IN_SECONDS;
const BIND_COOKIE = 'oria_g_bind';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 20 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\handle' );
}

function route(): void {
	add_rewrite_rule( '^' . PATH . '/(start|callback)/?$', 'index.php?' . QUERY_VAR . '=$matches[1]', 'top' );
}

function maybe_flush(): void {
	if ( get_option( 'oria_google_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_google_rewrite_v', REWRITE_V );
	}
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/* --------------------------------------------------------- configuration */

function client_id(): string {
	return defined( 'ORIA_GOOGLE_CLIENT_ID' ) && is_string( ORIA_GOOGLE_CLIENT_ID ) ? trim( ORIA_GOOGLE_CLIENT_ID ) : '';
}

function client_secret(): string {
	return defined( 'ORIA_GOOGLE_CLIENT_SECRET' ) && is_string( ORIA_GOOGLE_CLIENT_SECRET ) ? trim( ORIA_GOOGLE_CLIENT_SECRET ) : '';
}

/** Sign-in is offered only when both halves of the credential are present. */
function available(): bool {
	return '' !== client_id() && '' !== client_secret();
}

function redirect_uri(): string {
	return home_url( '/' . PATH . '/callback/' );
}

/** Where the "Continue with Google" button points. */
function start_url( string $return_to = '' ): string {
	$args = array();
	if ( '' !== $return_to ) {
		$args['return'] = rawurlencode( $return_to );
	}
	return add_query_arg( $args, home_url( '/' . PATH . '/start/' ) );
}

/* -------------------------------------------------------------- dispatch */

function handle(): void {
	$step = (string) get_query_var( QUERY_VAR );

	if ( 'start' === $step ) {
		start();
	} elseif ( 'callback' === $step ) {
		callback();
	}
}

/* ----------------------------------------------------------------- start */

function start(): void {
	if ( ! available() ) {
		bail( home_url( '/' ), 'unavailable' );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$return_to = isset( $_GET['return'] ) ? rawurldecode( (string) wp_unslash( $_GET['return'] ) ) : '';
	$return_to = wp_validate_redirect( $return_to, home_url( '/' ) );

	$state    = random_token();
	$nonce    = random_token();
	$verifier = random_token( 64 );
	$bind     = random_token();

	set_transient(
		'oria_goog_' . $state,
		array(
			'nonce'    => $nonce,
			'verifier' => $verifier,
			'return'   => $return_to,
			'bind'     => wp_hash( $bind ),
		),
		STATE_TTL
	);

	/*
	 * The state is also tied to this browser. Without it, somebody could
	 * complete a flow themselves and hand the finished callback URL to
	 * another person, silently signing them into the attacker's account.
	 * SameSite must be Lax, not Strict: the request comes back from Google.
	 */
	setcookie(
		BIND_COOKIE,
		$bind,
		array(
			'expires'  => time() + STATE_TTL,
			'path'     => '/',
			'domain'   => '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);

	$url = add_query_arg(
		array(
			'client_id'             => rawurlencode( client_id() ),
			'redirect_uri'          => rawurlencode( redirect_uri() ),
			'response_type'         => 'code',
			'scope'                 => rawurlencode( SCOPES ),
			'state'                 => $state,
			'nonce'                 => $nonce,
			'code_challenge'        => challenge_for( $verifier ),
			'code_challenge_method' => 'S256',
			// Online only: no refresh token, because there is nothing to
			// come back for. Identity is needed once.
			'access_type'           => 'online',
			'prompt'                => 'select_account',
		),
		AUTH_ENDPOINT
	);

	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- off-site by design.
	exit;
}

/* -------------------------------------------------------------- callback */

function callback(): void {
	if ( ! available() ) {
		bail( home_url( '/' ), 'unavailable' );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- state is the CSRF token here.
	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['state'] ) ) : '';
	$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ) : '';
	$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';
	// phpcs:enable

	/*
	 * `error` is a WordPress public query var, and by the time this runs the
	 * key has been taken out of $_GET even though it is still in the raw
	 * query string. Google has no other word for "the person pressed
	 * cancel", so read it from the query string when $_GET has lost it.
	 */
	if ( '' === $error ) {
		parse_str( (string) ( $_SERVER['QUERY_STRING'] ?? '' ), $raw_query );
		$error = isset( $raw_query['error'] ) ? sanitize_text_field( (string) $raw_query['error'] ) : '';
	}

	/*
	 * Read the binding cookie BEFORE clearing it. clear_bind_cookie() also
	 * unsets $_COOKIE so nothing later in the request can be fooled by a
	 * value that is on its way out of the browser — which means taking the
	 * copy first. Reading it afterwards makes every sign-in fail as though
	 * the browser had changed.
	 */
	$bind  = isset( $_COOKIE[ BIND_COOKIE ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ BIND_COOKIE ] ) ) : '';
	$stash = '' !== $state ? get_transient( 'oria_goog_' . $state ) : false;
	$home  = is_array( $stash ) ? (string) $stash['return'] : home_url( '/' );

	// Spent whatever happens next: a state is good for one attempt.
	if ( '' !== $state ) {
		delete_transient( 'oria_goog_' . $state );
	}
	clear_bind_cookie();

	if ( ! is_array( $stash ) ) {
		bail( home_url( '/' ), 'state' );
	}

	// The person changed their mind at Google's screen.
	if ( '' !== $error ) {
		bail( $home, 'cancelled' );
	}

	if ( '' === $bind || ! hash_equals( (string) $stash['bind'], wp_hash( $bind ) ) ) {
		bail( $home, 'browser' );
	}

	if ( '' === $code ) {
		bail( $home, 'code' );
	}

	$claims = exchange( $code, (string) $stash['verifier'] );
	if ( is_wp_error( $claims ) ) {
		bail( $home, $claims->get_error_code() );
	}

	if ( ! hash_equals( (string) $stash['nonce'], (string) ( $claims['nonce'] ?? '' ) ) ) {
		bail( $home, 'nonce' );
	}

	$email = sanitize_email( (string) ( $claims['email'] ?? '' ) );

	// An unverified Google address proves nothing, which is the entire
	// reason for using Google here.
	if ( '' === $email || true !== ( $claims['email_verified'] ?? false ) ) {
		bail( $home, 'unverified' );
	}

	sign_in( $email, (string) ( $claims['name'] ?? '' ), $home );
}

/**
 * Swap the authorization code for an identity.
 *
 * @return array<string,mixed>|\WP_Error The ID token claims.
 */
function exchange( string $code, string $verifier ) {
	$response = wp_remote_post(
		TOKEN_ENDPOINT,
		array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array(
				'code'          => $code,
				'client_id'     => client_id(),
				'client_secret' => client_secret(),
				'redirect_uri'  => redirect_uri(),
				'grant_type'    => 'authorization_code',
				'code_verifier' => $verifier,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new \WP_Error( 'network', $response->get_error_message() );
	}

	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new \WP_Error( 'exchange', 'Token endpoint refused the code.' );
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$jwt  = (string) ( $body['id_token'] ?? '' );

	if ( '' === $jwt ) {
		return new \WP_Error( 'no_id_token', 'No id_token returned.' );
	}

	$claims = decode_claims( $jwt );
	if ( null === $claims ) {
		return new \WP_Error( 'malformed', 'Unreadable id_token.' );
	}

	// Checked here rather than trusted: audience, issuer, expiry.
	if ( ! hash_equals( client_id(), (string) ( $claims['aud'] ?? '' ) ) ) {
		return new \WP_Error( 'aud', 'Token was issued for another application.' );
	}

	$issuer = (string) ( $claims['iss'] ?? '' );
	if ( ! in_array( $issuer, array( 'https://accounts.google.com', 'accounts.google.com' ), true ) ) {
		return new \WP_Error( 'iss', 'Token came from an unexpected issuer.' );
	}

	if ( (int) ( $claims['exp'] ?? 0 ) <= time() ) {
		return new \WP_Error( 'expired', 'Token had already expired.' );
	}

	return $claims;
}

/**
 * The middle segment of a JWT, without verifying its signature — see the
 * note at the top of this file for why that is sound here.
 *
 * @return array<string,mixed>|null
 */
function decode_claims( string $jwt ): ?array {
	$parts = explode( '.', $jwt );
	if ( 3 !== count( $parts ) ) {
		return null;
	}

	$payload = base64_decode( strtr( $parts[1], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $parts[1] ) % 4 ) % 4 ), true );
	if ( false === $payload ) {
		return null;
	}

	$claims = json_decode( (string) $payload, true );
	return is_array( $claims ) ? $claims : null;
}

/* --------------------------------------------------------------- sign in */

function sign_in( string $email, string $name, string $home ): void {
	// The practitioner wall applies here exactly as it does everywhere: a
	// business address cannot become a reviewing member by another door.
	$may = Members\email_may_join( $email );
	if ( is_wp_error( $may ) ) {
		bail( $home, $may->get_error_code() );
	}

	$member = Members\by_email( $email );

	if ( null === $member ) {
		$member = Members\create( $email, $name, 'google' );
		if ( is_wp_error( $member ) ) {
			bail( $home, $member->get_error_code() );
		}
	}

	// Google has already proved the address, so there is no second
	// confirmation to sit through.
	if ( Members\STATUS_PENDING === $member['status'] ) {
		Members\activate( (int) $member['member_id'], 'google' );
	}

	$fresh = Members\get( (int) $member['member_id'] );
	if ( null === $fresh || Members\STATUS_ACTIVE !== $fresh['status'] ) {
		bail( $home, 'oria_member_' . ( $fresh['status'] ?? 'unknown' ) );
	}

	$user_id = (int) $member['user_id'];
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, false );
	Members\touch( (int) $member['member_id'] );

	wp_safe_redirect( add_query_arg( 'signed_in', '1', $home ) );
	exit;
}

/* --------------------------------------------------------------- helpers */

function random_token( int $length = 32 ): string {
	return wp_generate_password( $length, false, false );
}

/** PKCE S256: base64url of the verifier's SHA-256, unpadded. */
function challenge_for( string $verifier ): string {
	return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
}

function clear_bind_cookie(): void {
	if ( isset( $_COOKIE[ BIND_COOKIE ] ) ) {
		setcookie( BIND_COOKIE, '', array( 'expires' => time() - 3600, 'path' => '/' ) );
		unset( $_COOKIE[ BIND_COOKIE ] );
	}
}

/** Give up, quietly, with a reason the page can explain. */
function bail( string $where, string $why ): void {
	wp_safe_redirect( add_query_arg( array( 'review' => 'blocked', 'why' => sanitize_key( $why ) ), $where ) );
	exit;
}
