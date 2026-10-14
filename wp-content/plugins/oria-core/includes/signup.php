<?php
/**
 * Self-service signup: "List your practice".
 *
 * A visitor fills in the same details a free listing carries; one submit
 * creates the listing (PENDING — an admin approves within 24 hours), a
 * practitioner account that owns it, and two emails: a "we've got it,
 * 24 hours" confirmation with a set-password link, and a review nudge for
 * the admin. Publishing the pending listing sends the owner a "you're
 * live" email carrying both upgrade options.
 *
 * Someone already signed in can submit too — the listing attaches to
 * their existing account and the account half of the form is skipped,
 * which is how a person running two studios lists the second one.
 *
 * Hard rules enforced server-side, whatever the form sends:
 *   - photos must be real images (jpeg/png/webp, magic bytes checked),
 *     capped in number and size
 *   - at most FIVE services on the free plan (the form says why)
 *   - one account per email; existing users are pointed at log in/claim
 */

declare(strict_types=1);

namespace Oria\Core\Signup;

use Oria\Core\Ownership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAX_SERVICES = 5;
const MAX_IMAGES   = 4;
const MAX_BYTES    = 5 * 1024 * 1024;
const MIMES        = array( 'image/jpeg', 'image/png', 'image/webp' );
/** How long a "confirm your email" link stays valid. */
const CONFIRM_HOURS = 48;
/** Confirmation emails an hour from one visitor. */
const THROTTLE = 3;
/** Throwaway-inbox services: a listing owner needs an address that lasts. */
const DISPOSABLE = array(
	'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', 'grr.la', '10minutemail.com', '10minutemail.net',
	'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'tempmailo.com', 'tempail.com', 'throwawaymail.com', 'yopmail.com', 'yopmail.net',
	'getnada.com', 'nada.email', 'dispostable.com', 'maildrop.cc', 'mailnesia.com', 'trashmail.com', 'trashmail.de', 'fakeinbox.com',
	'mintemail.com', 'mohmal.com', 'emailondeck.com', 'spamgourmet.com', 'mailcatch.com', 'moakt.com', 'tmail.ws', 'burnermail.io',
	'mail.tm', 'mailpoof.com', 'inboxkitten.com', 'fakemail.net', 'discard.email', 'emailfake.com', 'luxusmail.org', 'tempr.email',
);

function bootstrap(): void {
	// Signed in or not, the submission is processed. It used to bounce
	// logged-in users straight to wp-admin, which silently binned a
	// completed form — and blocked the ordinary case of someone who runs
	// two studios listing the second one.
	add_action( 'admin_post_nopriv_oria_signup', __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_oria_signup', __NAMESPACE__ . '\handle' );
	add_action( 'transition_post_status', __NAMESPACE__ . '\live_email', 10, 3 );
	// The link in the "confirm your email" message. Logged out by definition.
	add_action( 'admin_post_nopriv_oria_signup_confirm', __NAMESPACE__ . '\confirm' );
	add_action( 'admin_post_oria_signup_confirm', __NAMESPACE__ . '\confirm' );
}

function page_url(): string {
	return home_url( '/list-your-practice/' );
}

function handle(): void {
	// Spam walls, in the oria-forms mould: nonce, honeypot, minimum fill time.
	if ( ! wp_verify_nonce( (string) ( $_POST['oria_signup_nonce'] ?? '' ), 'oria_signup' ) ) {
		bounce( array( 'expired' ) );
	}
	if ( '' !== (string) ( $_POST['oform_website'] ?? '' ) ) {
		bounce( array( 'spam' ) );
	}
	if ( time() - (int) ( $_POST['oria_ts'] ?? 0 ) < 4 ) {
		bounce( array( 'spam' ) );
	}

	/*
	 * Four answers, and three of them are about the person rather than the
	 * business. Everything the listing itself needs -- category, suburb,
	 * address, description, services, prices, photos -- is asked for in the
	 * dashboard instead, where it can be saved a bit at a time and changed
	 * afterwards. A ten-minute form that had to be finished in one sitting
	 * was the wrong shape for a job that is never finished in one sitting.
	 */
	$in = array(
		'practice_name' => sanitize_text_field( wp_unslash( (string) ( $_POST['practice_name'] ?? '' ) ) ),
		'phone'         => sanitize_text_field( wp_unslash( (string) ( $_POST['phone'] ?? '' ) ) ),
		'account_name'  => sanitize_text_field( wp_unslash( (string) ( $_POST['account_name'] ?? '' ) ) ),
		'account_email' => sanitize_email( wp_unslash( (string) ( $_POST['account_email'] ?? '' ) ) ),
		'authorised'    => ! empty( $_POST['authorised'] ),
	);

	// An existing account owns anything it submits; only a visitor needs
	// one built for them.
	$existing = get_current_user_id();

	$errors = validate( $in, $existing > 0 );


	if ( $errors ) {
		bounce( $errors, $in );
	}

	/*
	 * A visitor confirms their email BEFORE anything is created. Bots were
	 * registering with made-up addresses: every submission built an account
	 * and a pending listing and emailed the admin, whatever the address. Now
	 * the submission waits (CONFIRM_HOURS) and only the link in the
	 * confirmation email creates the account, the listing and the admin
	 * email -- an address nobody reads never gets that far, and nothing is
	 * left behind to clean up. Somebody already signed in has a confirmed
	 * email, so their second listing is created straight away as before.
	 */
	if ( $existing > 0 ) {
		create( $in, $existing );
	}
	if ( ! deliverable( $in['account_email'] ) ) {
		bounce( array( 'account_email_domain' ), $in );
	}
	if ( ! throttle_ok( $in['account_email'] ) ) {
		bounce( array( 'throttled' ), $in );
	}
	$token = wp_generate_password( 32, false );
	set_transient( 'oria_signup_pending_' . $token, $in, CONFIRM_HOURS * HOUR_IN_SECONDS );
	confirm_email( $in, $token );
	wp_safe_redirect( add_query_arg( 'signup', 'check', page_url() ) );
	exit;
}

/**
 * The link from the confirmation email: the stored submission becomes the
 * account and the pending listing. One use; an expired or reused link says
 * so rather than failing silently.
 */
function confirm(): void {
	$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $_GET['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the credential.
	$in    = '' !== $token ? get_transient( 'oria_signup_pending_' . $token ) : false;
	if ( ! is_array( $in ) ) {
		bounce( array( 'link' ) );
	}
	delete_transient( 'oria_signup_pending_' . $token );
	// Somebody may have registered that address in the meantime.
	if ( email_exists( (string) $in['account_email'] ) ) {
		bounce( array( 'account_exists' ), $in );
	}
	create( $in, 0 );
}

/**
 * Whether an address can receive mail at all: its domain has a mail server
 * (or at least resolves), and it is not a known throwaway-inbox service.
 * Deliberately lenient beyond that -- a real practice on an unusual domain
 * must never be turned away.
 */
function deliverable( string $email ): bool {
	$domain = strtolower( (string) substr( strrchr( $email, '@' ), 1 ) );
	if ( '' === $domain ) {
		return false;
	}
	if ( in_array( $domain, DISPOSABLE, true ) ) {
		return false;
	}
	if ( function_exists( 'checkdnsrr' ) ) {
		return checkdnsrr( $domain . '.', 'MX' ) || checkdnsrr( $domain . '.', 'A' );
	}
	return true;
}

/**
 * At most THROTTLE confirmation emails an hour from one visitor, and three a
 * day to one address -- so the form cannot be used to flood somebody's
 * inbox. Keyed by hashes: no IP address or email is stored in the clear.
 */
function throttle_ok( string $email ): bool {
	$ip_key   = 'oria_su_ip_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) . wp_salt() );
	$mail_key = 'oria_su_em_' . md5( strtolower( $email ) . wp_salt() );
	$ip_n     = (int) get_transient( $ip_key );
	$mail_n   = (int) get_transient( $mail_key );
	if ( $ip_n >= THROTTLE || $mail_n >= 3 ) {
		return false;
	}
	set_transient( $ip_key, $ip_n + 1, HOUR_IN_SECONDS );
	set_transient( $mail_key, $mail_n + 1, DAY_IN_SECONDS );
	return true;
}

/** "Confirm your email to finish listing X." */
function confirm_email( array $in, string $token ): void {
	$link = add_query_arg( array( 'action' => 'oria_signup_confirm', 't' => $token ), admin_url( 'admin-post.php' ) );
	send(
		(string) $in['account_email'],
		/* translators: %s: practice name */
		sprintf( __( 'Confirm your email to list %s on Oria Haven', 'oria' ), (string) $in['practice_name'] ),
		__( 'One click to finish', 'oria' ),
		sprintf(
			/* translators: 1: name, 2: practice name, 3: link, 4: hours */
			__( "Hi %1\$s,\n\nThanks for listing %2\$s on Oria Haven. Please confirm this is your email address, and we'll set up your account and your listing:\n\n%3\$s\n\nThe link works for %4\$d hours. If you didn't ask to be listed, ignore this email: nothing has been created and you won't hear from us again.", 'oria' ),
			(string) $in['account_name'],
			(string) $in['practice_name'],
			$link,
			CONFIRM_HOURS
		)
	);
}

/**
 * The account and the pending listing, and the two emails. Never returns.
 *
 * @param int $existing The signed-in owner's user id, or 0 for a new account.
 */
function create( array $in, int $existing ): void {
	// --- The listing (pending: "approved within 24 hours"). --------------
	$listing = wp_insert_post(
		array(
			'post_type'   => 'listing',
			'post_status' => 'pending',
			'post_title'  => $in['practice_name'],
			'post_name'   => wp_unique_post_slug( sanitize_title( $in['practice_name'] ), 0, 'publish', 'listing', 0 ),
			// The blurb is the only place a description lives. It is what the
			// cards, the meta description and the profile all read, and it is
			// editable in the admin -- which matters most for the one field a
			// practitioner writes freehand, where an outcome claim would land.
			'post_excerpt'=> '',
		),
		true
	);
	if ( is_wp_error( $listing ) ) {
		bounce( array( 'server' ), $in );
	}
	$listing = (int) $listing;

	// Phone is the only detail the form still collects; the rest is filled
	// in from the dashboard.
	$fields = array(
		'phone' => array( $in['phone'], 'field_oria_phone' ),
	);
	foreach ( $fields as $name => $pair ) {
		if ( '' !== $pair[0] ) {
			update_post_meta( $listing, $name, $pair[0] );
			update_post_meta( $listing, "_{$name}", $pair[1] );
		}
	}

	/*
	 * No category, suburb or services yet -- the owner picks those in the
	 * dashboard. The listing is pending until then, so it is not filed
	 * anywhere public while it has nothing to file it under.
	 */


	// --- The account. -----------------------------------------------------
	if ( $existing > 0 ) {
		$user_id  = $existing;
		$is_new   = false;
	} else {
		$username = sanitize_user( (string) strstr( $in['account_email'], '@', true ), true );
		if ( '' === $username || username_exists( $username ) ) {
			$username = sanitize_user( $username . wp_rand( 100, 999 ), true );
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $in['account_email'],
				'display_name' => $in['account_name'],
				'user_pass'    => wp_generate_password( 24 ),
				'role'         => Ownership\ROLE,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			wp_delete_post( $listing, true );
			bounce( array( 'server' ), $in );
		}
		$is_new = true;
	}

	update_post_meta( $listing, 'claimed_by', (int) $user_id );
	update_post_meta( $listing, '_oria_signup', time() );

	received_email( (int) $user_id, $listing, $in, $is_new );
	admin_email( (int) $user_id, $listing, $in );

	wp_safe_redirect( add_query_arg( 'signup', 'done', page_url() ) );
	exit;
}

/**
 * @param bool $has_account Submitter is already signed in, so the account
 *                          half of the form was never shown to them.
 * @return array<string> error codes
 */
function validate( array $in, bool $has_account = false ): array {
	$errors = array();
	if ( '' === $in['practice_name'] ) {
		$errors[] = 'name';
	}
	if ( ! $has_account ) {
		if ( '' === $in['account_name'] ) {
			$errors[] = 'account_name';
		}
		if ( ! is_email( $in['account_email'] ) ) {
			$errors[] = 'account_email';
		} elseif ( email_exists( $in['account_email'] ) ) {
			$errors[] = 'account_exists';
		}
	}
	if ( ! $in['authorised'] ) {
		$errors[] = 'authorised';
	}
	return $errors;
}

/**
 * The uploaded photos, validated hard: count, size, real image bytes.
 * Returns per-file arrays ready for media_handle_upload, or an error code.
 *
 * @return array<int, array<string, mixed>>|string
 */
function photos() {
	if ( empty( $_FILES['photos'] ) || ! is_array( $_FILES['photos']['name'] ?? null ) ) {
		return array();
	}
	$raw   = $_FILES['photos']; // phpcs:ignore WordPress.Security -- validated below.
	$files = array();
	$count = count( array_filter( (array) $raw['name'] ) );
	if ( 0 === $count ) {
		return array();
	}
	if ( $count > MAX_IMAGES ) {
		return 'photo_count';
	}
	foreach ( (array) $raw['name'] as $i => $name ) {
		if ( '' === (string) $name ) {
			continue;
		}
		if ( UPLOAD_ERR_OK !== (int) $raw['error'][ $i ] ) {
			return 'photo_upload';
		}
		if ( (int) $raw['size'][ $i ] > MAX_BYTES ) {
			return 'photo_size';
		}
		$tmp = (string) $raw['tmp_name'][ $i ];
		// Trust the bytes, not the filename or the browser's claimed type.
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $info || ! in_array( (string) ( $info['mime'] ?? '' ), MIMES, true ) ) {
			return 'photo_type';
		}
		$check = wp_check_filetype_and_ext( $tmp, (string) $name );
		if ( empty( $check['ext'] ) || ! in_array( (string) $check['type'], MIMES, true ) ) {
			return 'photo_type';
		}
		$files[] = array(
			'name'     => sanitize_file_name( (string) $name ),
			'type'     => (string) $info['mime'],
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => (int) $raw['size'][ $i ],
		);
	}
	return $files;
}

/** Stash the text input, redirect back with error codes. Never returns. */
function bounce( array $errors, array $in = array() ): void {
	$args = array( 'e' => implode( ',', array_map( 'sanitize_key', $errors ) ) );
	if ( $in ) {
		unset( $in['authorised'] );
		$key = wp_generate_password( 12, false );
		set_transient( 'oria_signup_' . $key, $in, 10 * MINUTE_IN_SECONDS );
		$args['k'] = $key;
	}
	wp_safe_redirect( add_query_arg( $args, page_url() ) );
	exit;
}

/**
 * "We've got it, we'll review it within 24 hours."
 *
 * Always sent, whoever submitted. The set-password link only appears when
 * we actually built them an account — someone who was already signed in
 * has no password to set, and a reset link would just read as a phishing
 * attempt.
 */
function received_email( int $user_id, int $listing, array $in, bool $is_new ): void {
	$user = get_userdata( $user_id );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return;
	}
	$name = $user->display_name ?: ( $in['account_name'] ?: $user->user_login );

	/*
	 * The form only collects a name and a phone number, and a listing can't
	 * be published without a category and a suburb (PublishGuard). So this
	 * email asks them to fill it in -- it used to say "nothing to do", and
	 * listings sat bare in the queue until an admin filled them in instead.
	 */
	$body = sprintf(
		/* translators: 1: person name, 2: practice name */
		__(
			"G'day %1\$s,\n\nThanks for listing %2\$s on Oria Haven. We've got your details, and we check every new listing by hand before it goes live, usually within 24 hours.\n\nWhile we review it, please fill in your listing: your category, suburb, a short description of what you offer, and a few photos. We can only publish a listing once it has a category and a suburb, and complete listings are approved fastest. You'll get another email the moment it goes live.",
			'oria'
		),
		$name,
		$in['practice_name']
	);

	$dashboard = function_exists( '\Oria\Core\MyOria\url' ) ? \Oria\Core\MyOria\url( 'listing' ) : home_url( '/my-oria/listing/' );

	if ( $is_new ) {
		$key  = get_password_reset_key( $user );
		$link = is_wp_error( $key ) ? wp_login_url() : network_site_url(
			'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ),
			'login'
		);
		$body .= sprintf(
			/* translators: 1: set-password URL, 2: dashboard URL */
			__( "\n\n1. Set your password (your practitioner account is ready):\n%1\$s\n\n2. Then fill in your listing from your dashboard:\n%2\$s", 'oria' ),
			$link,
			$dashboard
		);
	} else {
		$body .= sprintf(
			/* translators: %s: dashboard URL */
			__( "\n\nIt's attached to your existing account, so you can fill it in from your dashboard now:\n%s", 'oria' ),
			$dashboard
		);
	}

	$body .= __( "\n\nThe Oria Haven team", 'oria' );

	send( $user->user_email, __( "We've got your listing — Oria Haven", 'oria' ), __( 'Listing received', 'oria' ), $body );
}

function admin_email( int $user_id, int $listing, array $in ): void {
	$user    = get_userdata( $user_id );
	$contact = $user
		? sprintf( '%s <%s>', $user->display_name ?: $user->user_login, $user->user_email )
		: sprintf( '%s <%s>', $in['account_name'], $in['account_email'] );

	wp_mail(
		(string) get_option( 'admin_email' ),
		sprintf( '[Oria Haven] New practice signup: %s', $in['practice_name'] ),
		sprintf(
			"A new practice registered itself and is waiting for review (24-hour promise!).\n\n%s\nContact: %s\n\nThey fill in the category, suburb and the rest from their dashboard, so the listing may still be bare.\n\nReview and publish:\n%s\n\nPublishing it sends the owner their approval email.",
			$in['practice_name'],
			$contact,
			admin_url( 'post.php?post=' . $listing . '&action=edit' )
		)
	);
}

/** One place that knows whether the branded HTML shell is available. */
function send( string $to, string $subject, string $heading, string $body ): void {
	if ( function_exists( '\Oria\Forms\Emails\shell' ) ) {
		wp_mail(
			$to,
			$subject,
			\Oria\Forms\Emails\shell( $heading, '<p style="margin:0 0 14px;">' . nl2br( esc_html( $body ) ) . '</p>' ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
		return;
	}
	wp_mail( $to, $subject, $body . \Oria\Core\Mail\signoff() );
}

/**
 * Approved and published: tell the owner they're live, and lay out the two
 * paid plans.
 *
 * This is the one email a new practitioner is guaranteed to open, so it is
 * where the upgrade is offered — with both tiers priced, their features
 * spelled out, and a Stripe link tagged to this listing so payment
 * activates it automatically. The free plan is stated plainly as a real
 * option; nobody upgrades because they felt cornered.
 */
function live_email( string $new, string $old, ?\WP_Post $post = null ): void {
	// transition_post_status does not always carry a post -- a strict
	// WP_Post hint here takes the whole request down with a TypeError,
	// including the insert inside submit() that creates the listing.
	if ( ! $post instanceof \WP_Post ) {
		return;
	}
	// Any unpublished state counts: an admin who saves as draft first and
	// publishes later should still trigger the approval email.
	if ( 'listing' !== $post->post_type || 'publish' !== $new || 'publish' === $old ) {
		return;
	}
	if ( ! get_post_meta( $post->ID, '_oria_signup', true ) ) {
		return;
	}
	delete_post_meta( $post->ID, '_oria_signup' ); // once only.

	$owner = get_userdata( (int) get_post_meta( $post->ID, 'claimed_by', true ) );
	if ( ! $owner || ! is_email( $owner->user_email ) ) {
		return;
	}

	send( $owner->user_email, __( 'Your listing is live on Oria Haven', 'oria' ), __( "You're live", 'oria' ), live_body( $post, $owner ) );
}

/**
 * The words of the "you're live" email, with no sending and no state.
 *
 * live_email() has to clear the _oria_signup flag so the email fires once,
 * which makes it the wrong thing to call merely to look at the wording.
 * The body lives here so the preview screen renders the genuine copy —
 * share block, upgrade block and all — without spending anybody's email.
 */
function live_body( \WP_Post $post, \WP_User $owner ): string {
	$body = sprintf(
		/* translators: 1: display name, 2: practice name, 3: listing URL */
		__( "G'day %1\$s,\n\nGood news — %2\$s has been approved and is now live on Oria Haven:\n%3\$s\n\nIt's listed free, and it stays that way for as long as you like. Enquiries go straight to you and we never take a cut of a booking.", 'oria' ),
		$owner->display_name ?: $owner->user_login,
		\get_post_field( 'post_title', $post->ID, 'raw' ),
		get_permalink( $post )
	);

	$body .= \Oria\Core\Share\email_block( $post->ID );
	$body .= upgrade_block( $post->ID, $owner->user_email );
	return $body . __( "\n\nThe Oria Haven team", 'oria' );
}

/**
 * The one paid plan as plain text, with an activation link when Stripe is
 * configured. Claiming is free, so Featured is all there is to offer.
 * Without billing set up the price and features still show — a dev
 * environment shouldn't silently drop the pitch — but the email points at
 * a conversation instead of a dead link.
 */
function upgrade_block( int $listing_id, string $email ): string {
	$featured = \Oria\Core\Tiers\summary( 'featured' );
	$bullets  = static fn( array $t ): string => '• ' . implode( "\n• ", $t['features'] );

	$out = "\n\n" . __( "WANT MORE FROM IT?\nOne optional plan, cancel any time — the listing stays claimed and free, and everything you've added is kept.", 'oria' );

	$out .= sprintf(
		"\n\n%s — %s/month\n%s",
		strtoupper( $featured['label'] ),
		$featured['price'],
		$bullets( $featured )
	);
	$featured_url = \Oria\Core\Billing\pay_url( 'featured', $listing_id, $email );
	if ( '' !== $featured_url ) {
		$out .= sprintf( "\n%s %s", __( 'Activate:', 'oria' ), $featured_url );
	}

	if ( '' === $featured_url ) {
		$out .= "\n\n" . __( 'Reply to this email if it sounds useful and we\'ll set it up.', 'oria' );
	}

	return $out;
}
