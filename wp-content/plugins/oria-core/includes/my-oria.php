<?php
/**
 * My Oria -- the member area at /my-oria/.
 *
 * Routes, the sign-in wall, the forms, and the little bundle of state the
 * browser needs. The pages themselves are theme templates under
 * template-parts/my/; the data they show comes from Activity, Passport and
 * Recommend. This file knows URLs and forms and nothing about badges.
 *
 * A route rather than WordPress pages, like /saved/ and /journeys/: it ships
 * in git and exists on production the moment the code lands. Every view is
 * noindex through both the core and the Yoast filter; the two private views
 * (everything but login, register and reset) send a visitor to sign in and
 * bring them back afterwards.
 *
 * Accounts are the existing Members: same role, same table, same
 * practitioner wall. What is new is a password -- members used to arrive
 * only through a review magic link or Google -- and a first name. A member
 * who registers here is left "pending" until they confirm their email by
 * one of the old routes; that status gates reviewing, which is public, and
 * never My Oria, which is theirs.
 */

declare(strict_types=1);

namespace Oria\Core\MyOria;

use Oria\Core\Activity;
use Oria\Core\Mail;
use Oria\Core\Members;
use Oria\Core\Passport;
use Oria\Core\Recommend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PATH      = 'my-oria';
const QUERY_VAR = 'oria_my';
const VIEW_VAR  = 'oria_my_view';
const REWRITE_V = '2';

/** view slug => needs sign-in */
const VIEWS = array(
	''         => true,
	'saved'    => true,
	'passport' => true,
	'profile'  => true,
	// The owner's listing manager. Private like the rest, and gated again
	// inside the view on whether this account actually manages a listing.
	'listing'      => true,
	'listing-edit' => true,
	'login'    => false,
	'register' => false,
	'reset'    => false,
);

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 10 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_action( 'template_redirect', __NAMESPACE__ . '\gate', 3 );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );

	add_filter( 'wp_robots', __NAMESPACE__ . '\wp_robots' );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\yoast_robots', 20 );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 20 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 20 );

	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\browser_state', 20 );
	add_filter( 'login_redirect', __NAMESPACE__ . '\login_landing', 11, 3 );

	foreach ( array( 'register', 'login', 'reset_request', 'reset_set' ) as $a ) {
		add_action( 'admin_post_oria_my_' . $a, __NAMESPACE__ . '\handle_' . $a );
		add_action( 'admin_post_nopriv_oria_my_' . $a, __NAMESPACE__ . '\handle_' . $a );
	}
	add_action( 'admin_post_oria_my_profile', __NAMESPACE__ . '\handle_profile' );
}

/* ---------------------------------------------------------------- routes */

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QUERY_VAR . '=1', 'top' );
	add_rewrite_rule( '^' . PATH . '/([a-z-]+)/?$', 'index.php?' . QUERY_VAR . '=1&' . VIEW_VAR . '=$matches[1]', 'top' );
}

function maybe_flush(): void {
	if ( get_option( 'oria_my_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_my_rewrite_v', REWRITE_V );
	}
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	$vars[] = VIEW_VAR;
	return $vars;
}

function is_page(): bool {
	return (bool) get_query_var( QUERY_VAR );
}

function view(): string {
	$v = sanitize_key( (string) get_query_var( VIEW_VAR ) );
	return array_key_exists( $v, VIEWS ) ? $v : '404';
}

function url( string $view = '' ): string {
	return home_url( '/' . PATH . '/' . ( '' !== $view ? $view . '/' : '' ) );
}

/** Same trick as /saved/: a parameterless rule must not read as the home page. */
function fix_query( \WP_Query $q ): void {
	if ( ! $q->is_main_query() || ! $q->get( QUERY_VAR ) ) {
		return;
	}
	$q->is_home       = false;
	$q->is_front_page = false;
	$q->is_archive    = false;
	$q->is_singular   = false;
	$q->is_404        = false;
	$q->set( 'posts_per_page', 1 );
}

function template( string $template ): string {
	if ( ! is_page() ) {
		return $template;
	}
	if ( '404' === view() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return get_404_template() ?: $template;
	}
	$found = locate_template( array( 'oria-my-oria.php' ) );
	return $found ? $found : $template;
}

/**
 * The wall. Private views need a signed-in account; the sign-in views send
 * a signed-in visitor on to their dashboard. /saved/ becomes the account's
 * list once there is an account.
 */
function gate(): void {
	nocache_headers_if_private();

	if ( function_exists( '\Oria\Core\Saved\is_page' ) && \Oria\Core\Saved\is_page() && is_user_logged_in() ) {
		wp_safe_redirect( url( 'saved' ), 302 );
		exit;
	}
	if ( ! is_page() || '404' === view() ) {
		return;
	}
	$v       = view();
	$private = VIEWS[ $v ];
	if ( $private && ! is_user_logged_in() ) {
		wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( url( $v ) ), url( 'login' ) ), 302 );
		exit;
	}
	/*
	 * The listing manager belongs to whoever manages a listing, and the
	 * check is here rather than only in the template: a member who has
	 * never claimed anything should not be able to reach the shell of an
	 * editor by typing the URL.
	 */
	if ( in_array( $v, array( 'listing', 'listing-edit' ), true )
		&& is_user_logged_in()
		&& ! \Oria\Core\ListingEditor\listing_for( get_current_user_id() ) ) {
		wp_safe_redirect( url(), 302 );
		exit;
	}
	if ( ! $private && 'reset' !== $v && is_user_logged_in() ) {
		wp_safe_redirect( url(), 302 );
		exit;
	}
}

function nocache_headers_if_private(): void {
	if ( ! is_page() ) {
		return;
	}
	nocache_headers();
	/*
	 * Say it to LiteSpeed in its own words as well. Its private cache for
	 * signed-in visitors is switched on, and a cached copy of the listing
	 * editor is a form that shows you the row you just deleted.
	 */
	do_action( 'litespeed_control_set_nocache', 'My Oria is per-person and never cached' );
}

/* ------------------------------------------------------------------- seo */

function wp_robots( array $r ): array {
	if ( is_page() ) {
		$r['noindex'] = true;
		unset( $r['nofollow'] );
	}
	return $r;
}

function yoast_robots( $robots ) {
	return is_page() ? 'noindex, follow' : $robots;
}

function heading(): string {
	switch ( view() ) {
		case 'saved':
			return __( 'Saved places', 'oria' );
		case 'passport':
			return __( 'My Wellness Passport', 'oria' );
		case 'profile':
			return __( 'Profile', 'oria' );
		case 'login':
			return __( 'Log in to My Oria', 'oria' );
		case 'register':
			return __( 'Create My Oria', 'oria' );
		case 'reset':
			return __( 'Reset your password', 'oria' );
	}
	return __( 'My Oria', 'oria' );
}

function title( string $title ): string {
	return is_page() ? heading() . ' | ' . get_bloginfo( 'name' ) : $title;
}

function core_title( array $parts ): array {
	if ( is_page() ) {
		$parts['title'] = heading();
	}
	return $parts;
}

/* ---------------------------------------------------------------- people */

function user_id(): int {
	return get_current_user_id();
}

/** The name the dashboard greets with: first name, else the display name. */
function first_name( int $user_id = 0 ): string {
	$user_id = $user_id ?: user_id();
	$first   = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
	if ( '' !== $first ) {
		return $first;
	}
	$user = get_userdata( $user_id );
	return $user instanceof \WP_User ? (string) strtok( $user->display_name, ' ' ) : '';
}

function is_member_role( int $user_id ): bool {
	$user = get_userdata( $user_id );
	return $user instanceof \WP_User && in_array( Members\ROLE, (array) $user->roles, true );
}

/** wp-login.php sends a member here, not to a profile screen they cannot open. */
function login_landing( $redirect, $requested, $user ) {
	if ( $user instanceof \WP_User && is_member_role( (int) $user->ID ) ) {
		return url();
	}
	return $redirect;
}

/**
 * Where to go after a sign-in form: the page that asked, if it is ours.
 * wp_validate_redirect() refuses anything off-site.
 */
function after_login_url( int $user_id ): string {
	$to = isset( $_POST['redirect_to'] ) ? (string) wp_unslash( $_POST['redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$to = wp_validate_redirect( $to, '' );
	if ( '' !== $to ) {
		return $to;
	}
	if ( is_member_role( $user_id ) ) {
		return url();
	}
	/*
	 * A practitioner who signs in here came for their listing. Send them to
	 * it; wp-admin is where they used to be sent, and it is no longer where
	 * an owner does anything.
	 */
	if ( function_exists( '\Oria\Core\ListingEditor\listing_for' ) && \Oria\Core\ListingEditor\listing_for( $user_id ) ) {
		return url( 'listing' );
	}
	return admin_url();
}

/* ------------------------------------------------------------ notices */

/**
 * What a form said back, carried in the URL. Codes, not text, so nothing
 * typed by anybody is reflected into the page.
 *
 * @return array{type:string, text:string}|null
 */
function notice(): ?array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$ok  = isset( $_GET['ok'] ) ? sanitize_key( (string) $_GET['ok'] ) : '';
	$err = isset( $_GET['err'] ) ? sanitize_key( (string) $_GET['err'] ) : '';
	// phpcs:enable
	$oks = array(
		'welcome'  => __( 'Welcome to My Oria. Save a place you want to try, and tell us what you are looking for below.', 'oria' ),
		'saved'    => __( 'Profile saved.', 'oria' ),
		'password' => __( 'Password changed. You are signed in.', 'oria' ),
		'sent'     => __( 'If that email has a My Oria account, a reset link is on its way. Check your spam folder if it does not arrive in a few minutes.', 'oria' ),
		'signedin' => __( 'You are signed in.', 'oria' ),
	);
	$errs = array(
		'name'      => __( 'Please tell us your first name.', 'oria' ),
		'email'     => __( 'That email address does not look right.', 'oria' ),
		'password'  => __( 'Passwords need at least 8 characters.', 'oria' ),
		'mismatch'  => __( 'The two passwords do not match.', 'oria' ),
		'agree'     => __( 'Please agree to the privacy policy and terms to continue.', 'oria' ),
		'exists'    => __( 'There is already a My Oria account with that email. Log in, or reset your password if you have never set one.', 'oria' ),
		'blocked'   => __( 'That email is registered as a practice or staff account. My Oria is for visitors; practices manage their listing from the claim page.', 'oria' ),
		'login'     => __( 'That email and password did not match. If you signed up with Google or through a review, use the reset link to set a password.', 'oria' ),
		'expired'   => __( 'That reset link has expired or was already used. Request a new one.', 'oria' ),
		'nonce'     => __( 'That form had expired. Please try again.', 'oria' ),
		'create'    => __( 'We could not create the account. Please try again or email hello@oriahaven.com.au.', 'oria' ),
	);
	if ( '' !== $ok && isset( $oks[ $ok ] ) ) {
		return array( 'type' => 'ok', 'text' => $oks[ $ok ] );
	}
	if ( '' !== $err && isset( $errs[ $err ] ) ) {
		return array( 'type' => 'err', 'text' => $errs[ $err ] );
	}
	return null;
}

/** Back to the form with a code, keeping the page it wanted to return to. */
function back( string $view, string $key, string $code ): void {
	$args = array( $key => $code );
	$to   = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( (string) wp_unslash( $_POST['redirect_to'] ), '' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' !== $to ) {
		$args['redirect_to'] = rawurlencode( $to );
	}
	wp_safe_redirect( add_query_arg( $args, url( $view ) ), 303 );
	exit;
}

/* ----------------------------------------------------------------- forms */

function handle_register(): void {
	if ( ! wp_verify_nonce( (string) ( $_POST['oria_my_nonce'] ?? '' ), 'oria_my_register' ) ) {
		back( 'register', 'err', 'nonce' );
	}
	if ( '' !== (string) ( $_POST['oria_website'] ?? '' ) ) {
		back( 'register', 'ok', 'welcome' ); // honeypot: pretend, record nothing
	}
	$first = sanitize_text_field( (string) wp_unslash( $_POST['first_name'] ?? '' ) );
	$email = sanitize_email( (string) wp_unslash( $_POST['email'] ?? '' ) );
	$pass  = (string) wp_unslash( $_POST['password'] ?? '' );

	if ( '' === $first ) {
		back( 'register', 'err', 'name' );
	}
	if ( ! is_email( $email ) ) {
		back( 'register', 'err', 'email' );
	}
	if ( strlen( $pass ) < 8 ) {
		back( 'register', 'err', 'password' );
	}
	if ( empty( $_POST['agree'] ) ) {
		back( 'register', 'err', 'agree' );
	}

	$may = Members\email_may_join( $email );
	if ( is_wp_error( $may ) ) {
		back( 'register', 'err', 'blocked' );
	}
	if ( null !== Members\by_email( $email ) ) {
		back( 'register', 'err', 'exists' );
	}

	$member = Members\create( $email, $first, 'email' );
	if ( is_wp_error( $member ) ) {
		back( 'register', 'err', 'create' );
	}
	$user_id = (int) $member['user_id'];

	wp_set_password( $pass, $user_id );
	wp_update_user( array( 'ID' => $user_id, 'first_name' => $first, 'display_name' => $first ) );

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );
	Members\touch( (int) $member['member_id'] );
	welcome_email( $user_id );

	$to = after_login_url( $user_id );
	wp_safe_redirect( add_query_arg( 'ok', 'welcome', url() === $to ? url() : $to ), 303 );
	exit;
}

function handle_login(): void {
	if ( ! wp_verify_nonce( (string) ( $_POST['oria_my_nonce'] ?? '' ), 'oria_my_login' ) ) {
		back( 'login', 'err', 'nonce' );
	}
	$email = sanitize_email( (string) wp_unslash( $_POST['email'] ?? '' ) );
	$pass  = (string) wp_unslash( $_POST['password'] ?? '' );
	if ( ! is_email( $email ) || '' === $pass ) {
		back( 'login', 'err', 'login' );
	}
	$user = wp_signon(
		array( 'user_login' => $email, 'user_password' => $pass, 'remember' => true ),
		is_ssl()
	);
	if ( is_wp_error( $user ) ) {
		back( 'login', 'err', 'login' );
	}
	$member = Members\by_user( (int) $user->ID );
	if ( null !== $member ) {
		Members\touch( (int) $member['member_id'] );
	}
	wp_safe_redirect( after_login_url( (int) $user->ID ), 303 );
	exit;
}

/**
 * Reset, step one. Always answers "sent", so the form cannot be used to
 * find out which addresses have accounts. The link lands on our own page,
 * not wp-login.php, which a member has never seen.
 */
function handle_reset_request(): void {
	if ( ! wp_verify_nonce( (string) ( $_POST['oria_my_nonce'] ?? '' ), 'oria_my_reset' ) ) {
		back( 'reset', 'err', 'nonce' );
	}
	$email = sanitize_email( (string) wp_unslash( $_POST['email'] ?? '' ) );
	$user  = is_email( $email ) ? get_user_by( 'email', $email ) : false;
	if ( $user instanceof \WP_User ) {
		$key = get_password_reset_key( $user );
		if ( ! is_wp_error( $key ) ) {
			$link = add_query_arg( array( 'key' => $key, 'login' => rawurlencode( $user->user_login ) ), url( 'reset' ) );
			$body = sprintf(
				/* translators: 1: first name, 2: reset link */
				__( "Hi %1\$s,\n\nSomebody asked to reset the password for your My Oria account. If that was you, open this link within the next day:\n\n%2\$s\n\nIf it wasn't you, ignore this email and nothing changes.", 'oria' ),
				first_name( (int) $user->ID ) ?: __( 'there', 'oria' ),
				$link
			);
			wp_mail( $user->user_email, __( 'Reset your My Oria password', 'oria' ), $body . signoff() );
		}
	}
	back( 'reset', 'ok', 'sent' );
}

/** Reset, step two: the link was followed and a new password typed. */
function handle_reset_set(): void {
	if ( ! wp_verify_nonce( (string) ( $_POST['oria_my_nonce'] ?? '' ), 'oria_my_reset_set' ) ) {
		back( 'reset', 'err', 'nonce' );
	}
	$key   = (string) wp_unslash( $_POST['key'] ?? '' );
	$login = (string) wp_unslash( $_POST['login'] ?? '' );
	$pass  = (string) wp_unslash( $_POST['password'] ?? '' );
	$again = (string) wp_unslash( $_POST['password2'] ?? '' );

	$user = check_password_reset_key( $key, $login );
	if ( is_wp_error( $user ) ) {
		back( 'reset', 'err', 'expired' );
	}
	if ( strlen( $pass ) < 8 ) {
		wp_safe_redirect( add_query_arg( array( 'key' => $key, 'login' => rawurlencode( $login ), 'err' => 'password' ), url( 'reset' ) ), 303 );
		exit;
	}
	if ( $pass !== $again ) {
		wp_safe_redirect( add_query_arg( array( 'key' => $key, 'login' => rawurlencode( $login ), 'err' => 'mismatch' ), url( 'reset' ) ), 303 );
		exit;
	}
	reset_password( $user, $pass );
	wp_set_current_user( (int) $user->ID );
	wp_set_auth_cookie( (int) $user->ID, true );
	wp_safe_redirect( add_query_arg( 'ok', 'password', is_member_role( (int) $user->ID ) ? url() : admin_url() ), 303 );
	exit;
}

function handle_profile(): void {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( url( 'login' ), 303 );
		exit;
	}
	if ( ! wp_verify_nonce( (string) ( $_POST['oria_my_nonce'] ?? '' ), 'oria_my_profile' ) ) {
		back( 'profile', 'err', 'nonce' );
	}
	$user_id = user_id();
	$first   = sanitize_text_field( (string) wp_unslash( $_POST['first_name'] ?? '' ) );
	if ( '' === $first ) {
		back( 'profile', 'err', 'name' );
	}
	wp_update_user( array( 'ID' => $user_id, 'first_name' => $first, 'display_name' => $first ) );

	Recommend\save_prefs(
		$user_id,
		array(
			'intents'   => (array) ( $_POST['intents'] ?? array() ),
			'interests' => (array) ( $_POST['interests'] ?? array() ),
			'area'      => (string) ( $_POST['area'] ?? '' ),
			'email'     => ! empty( $_POST['email_updates'] ),
		)
	);

	$pass = (string) wp_unslash( $_POST['password'] ?? '' );
	if ( '' !== $pass ) {
		if ( strlen( $pass ) < 8 ) {
			back( 'profile', 'err', 'password' );
		}
		if ( $pass !== (string) wp_unslash( $_POST['password2'] ?? '' ) ) {
			back( 'profile', 'err', 'mismatch' );
		}
		wp_set_password( $pass, $user_id );
		// wp_set_password() ends every session, this one included.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		back( 'profile', 'ok', 'password' );
	}
	// The dashboard's interests prompt returns to the dashboard.
	back( 'dashboard' === (string) ( $_POST['return'] ?? '' ) ? '' : 'profile', 'ok', 'saved' );
}

/* ----------------------------------------------------------------- email */

function signoff(): string {
	return function_exists( '\Oria\Core\Mail\signoff' ) ? "\n\n" . Mail\signoff() : "\n\n— Oria Haven";
}

function welcome_email( int $user_id ): void {
	$user = get_userdata( $user_id );
	if ( ! $user instanceof \WP_User ) {
		return;
	}
	$body = sprintf(
		/* translators: 1: first name, 2: dashboard URL */
		__( "Hi %1\$s,\n\nYour My Oria account is ready. Save places you want to try, mark the ones you have been to, and your Wellness Passport fills in as you go.\n\n%2\$s\n\nWe only email you about your account unless you tell us otherwise on your profile.", 'oria' ),
		first_name( $user_id ),
		url()
	);
	wp_mail( $user->user_email, __( 'Welcome to My Oria', 'oria' ), $body . signoff() );
}

/* -------------------------------------------------------------- browser */

/**
 * window.ORIA_ME: is anyone signed in, and if so what have they saved and
 * tried. Tiny, and on every page, because the save buttons are on every
 * page. Slugs, not ids, to match what the device shortlist already keeps.
 * Guests get the two URLs the save prompt needs and nothing else.
 */
function browser_state(): void {
	if ( ! wp_script_is( 'oria-app', 'enqueued' ) && ! wp_script_is( 'oria-app', 'registered' ) ) {
		return;
	}
	$me = array(
		'loggedIn'    => false,
		'loginUrl'    => url( 'login' ),
		'registerUrl' => url( 'register' ),
		'homeUrl'     => url(),
	);
	if ( is_user_logged_in() && Activity\may_act( user_id() ) ) {
		$uid = user_id();
		$me  = array_merge(
			$me,
			array(
				'loggedIn' => true,
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'api'      => rest_url( Activity\ROUTE . '/me/' ),
				'saved'    => Activity\slugs( $uid, Activity\SAVED ),
				'tried'    => Activity\slugs( $uid, Activity\TRIED ),
				'savedUrl' => url( 'saved' ),
				'name'     => first_name( $uid ),
			)
		);
	}
	wp_add_inline_script( 'oria-app', 'window.ORIA_ME = ' . wp_json_encode( $me ) . ';', 'before' );
}

/* --------------------------------------------------------- for templates */

/** @return array{saved:int, tried:int, badges:int} */
function summary( int $user_id ): array {
	$c = Activity\counts( $user_id );
	return array(
		'saved'  => $c[ Activity\SAVED ],
		'tried'  => $c[ Activity\TRIED ],
		'badges' => count( Passport\earned( $user_id ) ),
	);
}

/** The account tabs, in order. @return list<array{slug:string, label:string, url:string}> */
function tabs(): array {
	$tabs = array(
		array( 'slug' => '', 'label' => __( 'Dashboard', 'oria' ), 'url' => url() ),
		array( 'slug' => 'saved', 'label' => __( 'Saved', 'oria' ), 'url' => url( 'saved' ) ),
		array( 'slug' => 'passport', 'label' => __( 'Passport', 'oria' ), 'url' => url( 'passport' ) ),
		array( 'slug' => 'profile', 'label' => __( 'Profile', 'oria' ), 'url' => url( 'profile' ) ),
	);

	/*
	 * Owners get one more destination, and only owners: a navigation item
	 * that leads somewhere empty is worse than one that is not there.
	 * Placed second because somebody who runs a practice came here for the
	 * practice, not for their saved places.
	 */
	if ( function_exists( '\Oria\Core\ListingEditor\listing_for' )
		&& \Oria\Core\ListingEditor\listing_for( get_current_user_id() ) ) {
		array_splice(
			$tabs,
			1,
			0,
			array( array( 'slug' => 'listing', 'label' => __( 'My listing', 'oria' ), 'url' => url( 'listing' ) ) )
		);
	}

	return $tabs;
}

function logout_url(): string {
	return wp_logout_url( home_url( '/' ) );
}
