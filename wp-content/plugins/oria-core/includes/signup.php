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

function bootstrap(): void {
	// Signed in or not, the submission is processed. It used to bounce
	// logged-in users straight to wp-admin, which silently binned a
	// completed form — and blocked the ordinary case of someone who runs
	// two studios listing the second one.
	add_action( 'admin_post_nopriv_oria_signup', __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_oria_signup', __NAMESPACE__ . '\handle' );
	add_action( 'transition_post_status', __NAMESPACE__ . '\live_email', 10, 3 );
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

	$in = array(
		'practice_name' => sanitize_text_field( wp_unslash( (string) ( $_POST['practice_name'] ?? '' ) ) ),
		'practice_cat'  => sanitize_key( (string) ( $_POST['practice_cat'] ?? '' ) ),
		'suburb'        => sanitize_title( (string) ( $_POST['suburb'] ?? '' ) ),
		'address'       => sanitize_text_field( wp_unslash( (string) ( $_POST['address'] ?? '' ) ) ),
		'phone'         => sanitize_text_field( wp_unslash( (string) ( $_POST['phone'] ?? '' ) ) ),
		'public_email'  => sanitize_email( wp_unslash( (string) ( $_POST['public_email'] ?? '' ) ) ),
		'website'       => esc_url_raw( wp_unslash( (string) ( $_POST['website'] ?? '' ) ) ),
		'description'   => sanitize_textarea_field( wp_unslash( (string) ( $_POST['description'] ?? '' ) ) ),
		'price_from'    => sanitize_text_field( (string) ( $_POST['price_from'] ?? '' ) ),
		'price_band'    => sanitize_text_field( wp_unslash( (string) ( $_POST['price_band'] ?? '' ) ) ),
		'format'        => sanitize_key( (string) ( $_POST['format'] ?? 'in-person' ) ),
		'account_name'  => sanitize_text_field( wp_unslash( (string) ( $_POST['account_name'] ?? '' ) ) ),
		'account_email' => sanitize_email( wp_unslash( (string) ( $_POST['account_email'] ?? '' ) ) ),
		'authorised'    => ! empty( $_POST['authorised'] ),
		'services'      => array(),
	);
	foreach ( array_slice( (array) ( $_POST['services'] ?? array() ), 0, MAX_SERVICES ) as $svc ) {
		$svc = sanitize_text_field( wp_unslash( (string) $svc ) );
		if ( '' !== $svc ) {
			$in['services'][] = $svc;
		}
	}

	// An existing account owns anything it submits; only a visitor needs
	// one built for them.
	$existing = get_current_user_id();

	$errors = validate( $in, $existing > 0 );

	$files = photos();
	if ( is_string( $files ) ) {
		$errors[] = $files;
		$files    = array();
	}

	if ( $errors ) {
		bounce( $errors, $in );
	}

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
			'post_excerpt'=> wp_trim_words( $in['description'], 50, '…' ),
		),
		true
	);
	if ( is_wp_error( $listing ) ) {
		bounce( array( 'server' ), $in );
	}
	$listing = (int) $listing;

	$fields = array(
		'address'    => array( $in['address'], 'field_oria_address' ),
		'phone'      => array( $in['phone'], 'field_oria_phone' ),
		'email'      => array( $in['public_email'], 'field_oria_email' ),
		'website'    => array( $in['website'], 'field_oria_website' ),
		'price_from' => array( '' === $in['price_from'] ? '' : (string) max( 0, (int) $in['price_from'] ), 'field_oria_price_from' ),
		'price_band' => array( $in['price_band'], 'field_oria_price_band' ),
		'format'     => array( $in['format'], 'field_oria_format' ),
	);
	foreach ( $fields as $name => $pair ) {
		if ( '' !== $pair[0] ) {
			update_post_meta( $listing, $name, $pair[0] );
			update_post_meta( $listing, "_{$name}", $pair[1] );
		}
	}

	// Services: ACF repeater rows.
	update_post_meta( $listing, 'services', count( $in['services'] ) );
	update_post_meta( $listing, '_services', 'field_oria_services' );
	foreach ( $in['services'] as $i => $svc ) {
		update_post_meta( $listing, "services_{$i}_name", $svc );
		update_post_meta( $listing, "_services_{$i}_name", 'field_oria_service_name' );
	}

	// Terms: practice category, suburb and its region.
	wp_set_object_terms( $listing, $in['practice_cat'], 'practice' );
	$suburb = get_term_by( 'slug', $in['suburb'], 'area' );
	if ( $suburb instanceof \WP_Term ) {
		$terms = array( (int) $suburb->term_id );
		if ( $suburb->parent ) {
			$terms[] = (int) $suburb->parent;
		}
		wp_set_object_terms( $listing, $terms, 'area' );
	}

	// Photos: validated already; attach and fill the gallery.
	$gallery = array();
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	foreach ( $files as $file ) {
		$_FILES['oria_one'] = $file;
		$att                = media_handle_upload( 'oria_one', $listing );
		if ( ! is_wp_error( $att ) ) {
			$gallery[] = (int) $att;
		}
	}
	unset( $_FILES['oria_one'] );
	if ( $gallery ) {
		update_post_meta( $listing, 'gallery', $gallery );
		update_post_meta( $listing, '_gallery', 'field_oria_gallery' );
	}

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
	if ( ! get_term_by( 'slug', $in['practice_cat'], 'practice' ) ) {
		$errors[] = 'category';
	}
	$suburb = get_term_by( 'slug', $in['suburb'], 'area' );
	if ( ! $suburb instanceof \WP_Term || ! $suburb->parent ) {
		$errors[] = 'suburb';
	}
	if ( mb_strlen( $in['description'] ) < 40 ) {
		$errors[] = 'description';
	}
	if ( '' !== $in['public_email'] && ! is_email( $in['public_email'] ) ) {
		$errors[] = 'public_email';
	}
	if ( '' !== $in['price_band'] && ! in_array( $in['price_band'], array( 'Free', '$', '$$', '$$$', '$$$$' ), true ) ) {
		$errors[] = 'price_band';
	}
	if ( ! in_array( $in['format'], array( 'in-person', 'online', 'both' ), true ) ) {
		$errors[] = 'format';
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

	$body = sprintf(
		/* translators: 1: person name, 2: practice name */
		__(
			"G'day %1\$s,\n\nThanks for listing %2\$s on Oria Haven. We've got your details and they're with us now.\n\nWe check every new listing by hand, so yours will be reviewed and approved within 24 hours. You'll get another email the moment it goes live, and we'll only be in touch before then if something needs clarifying.\n\nNothing to do in the meantime.",
			'oria'
		),
		$name,
		$in['practice_name']
	);

	if ( $is_new ) {
		$key  = get_password_reset_key( $user );
		$link = is_wp_error( $key ) ? wp_login_url() : network_site_url(
			'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ),
			'login'
		);
		$body .= sprintf(
			/* translators: %s: set-password URL */
			__( "\n\nOne thing you can do now: your practitioner account is ready, so set your password here and you'll be able to edit your details any time.\n%s", 'oria' ),
			$link
		);
	} else {
		$body .= sprintf(
			/* translators: %s: dashboard URL */
			__( "\n\nIt's attached to your existing account, so it'll appear alongside your other listings here:\n%s", 'oria' ),
			admin_url( 'edit.php?post_type=listing' )
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
			"A new practice registered itself and is waiting for review (24-hour promise!).\n\n%s\nCategory: %s · Suburb: %s\nContact: %s\n\nReview and publish:\n%s\n\nPublishing it sends the owner their approval email, including the two upgrade options.",
			$in['practice_name'],
			$in['practice_cat'],
			$in['suburb'],
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
 * The two paid plans as plain text, with activation links when Stripe is
 * configured. Without billing set up the prices and features still show —
 * a dev environment shouldn't silently drop the pitch — but the email
 * points at a conversation instead of a dead link.
 */
function upgrade_block( int $listing_id, string $email ): string {
	$claimed  = \Oria\Core\Tiers\summary( 'claimed' );
	$featured = \Oria\Core\Tiers\summary( 'featured' );
	$bullets  = static fn( array $t ): string => '• ' . implode( "\n• ", $t['features'] );

	$out = "\n\n" . __( "WANT MORE FROM IT?\nTwo optional plans, cancel any time — the listing simply returns to its free form and everything you've added is kept.", 'oria' );

	$out .= sprintf(
		"\n\n%s — %s/month\n%s",
		strtoupper( $claimed['label'] ),
		$claimed['price'],
		$bullets( $claimed )
	);
	$claimed_url = \Oria\Core\Billing\pay_url( 'claimed', $listing_id, $email );
	if ( '' !== $claimed_url ) {
		$out .= sprintf( "\n%s %s", __( 'Activate:', 'oria' ), $claimed_url );
	}

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

	if ( '' === $claimed_url && '' === $featured_url ) {
		$out .= "\n\n" . __( 'Reply to this email if either sounds useful and we\'ll set it up.', 'oria' );
	}

	return $out;
}
