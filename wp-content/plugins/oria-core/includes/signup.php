<?php
/**
 * Self-service signup: "List your practice".
 *
 * A visitor fills in the same details a free listing carries; one submit
 * creates the listing (PENDING — an admin approves within 24 hours), a
 * practitioner account that owns it, and two emails: a branded welcome
 * with a set-password link for them, a review nudge for the admin. When
 * the admin publishes the pending listing, a "you're live" email follows.
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
	add_action( 'admin_post_nopriv_oria_signup', __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_oria_signup', __NAMESPACE__ . '\already_logged_in' );
	add_action( 'transition_post_status', __NAMESPACE__ . '\live_email', 10, 3 );
}

function page_url(): string {
	return home_url( '/list-your-practice/' );
}

function already_logged_in(): void {
	wp_safe_redirect( admin_url( 'edit.php?post_type=listing' ) );
	exit;
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

	$errors = validate( $in );

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
			'post_excerpt'=> wp_trim_words( $in['description'], 50, '…' ),
			'post_content'=> $in['description'],
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

	update_post_meta( $listing, 'claimed_by', (int) $user_id );
	update_post_meta( $listing, '_oria_signup', time() );

	welcome_email( (int) $user_id, $listing, $in );
	admin_email( $listing, $in );

	wp_safe_redirect( add_query_arg( 'signup', 'done', page_url() ) );
	exit;
}

/** @return array<string> error codes */
function validate( array $in ): array {
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
	if ( '' === $in['account_name'] ) {
		$errors[] = 'account_name';
	}
	if ( ! is_email( $in['account_email'] ) ) {
		$errors[] = 'account_email';
	} elseif ( email_exists( $in['account_email'] ) ) {
		$errors[] = 'account_exists';
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

function welcome_email( int $user_id, int $listing, array $in ): void {
	$user = get_userdata( $user_id );
	$key  = get_password_reset_key( $user );
	$link = is_wp_error( $key ) ? wp_login_url() : network_site_url(
		'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ),
		'login'
	);

	$subject = __( "You're registered with Oria Haven", 'oria' );
	$body    = sprintf(
		/* translators: 1: person name, 2: practice name, 3: set-password URL */
		__(
			"G'day %1\$s,\n\nThanks for listing %2\$s on Oria Haven. We check every new listing by hand — yours will be reviewed and approved within 24 hours, and we'll only be in touch if something needs clarifying.\n\nYour practitioner account is ready now. Set your password here:\n%3\$s\n\nOnce you're in, you can edit your details any time — and if you'd like more room (unlimited services, photo gallery, timetable, special offers, workshops and featured placement), you can upgrade from your dashboard whenever it suits.\n\nThe Oria Haven team",
			'oria'
		),
		$in['account_name'],
		$in['practice_name'],
		$link
	);

	if ( function_exists( '\Oria\Forms\Emails\shell' ) ) {
		$html = \Oria\Forms\Emails\shell(
			__( "You're registered", 'oria' ),
			'<p>' . nl2br( esc_html( $body ) ) . '</p>'
		);
		wp_mail( $user->user_email, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		return;
	}
	wp_mail( $user->user_email, $subject, $body );
}

function admin_email( int $listing, array $in ): void {
	wp_mail(
		(string) get_option( 'admin_email' ),
		sprintf( '[Oria Haven] New practice signup: %s', $in['practice_name'] ),
		sprintf(
			"A new practice registered itself and is waiting for review (24-hour promise!).\n\n%s\nCategory: %s · Suburb: %s\nContact: %s <%s>\n\nReview and publish:\n%s",
			$in['practice_name'],
			$in['practice_cat'],
			$in['suburb'],
			$in['account_name'],
			$in['account_email'],
			admin_url( 'post.php?post=' . $listing . '&action=edit' )
		)
	);
}

/** Pending → publish on a signup listing: tell the owner they're live. */
function live_email( string $new, string $old, \WP_Post $post ): void {
	if ( 'listing' !== $post->post_type || 'publish' !== $new || 'pending' !== $old ) {
		return;
	}
	if ( ! get_post_meta( $post->ID, '_oria_signup', true ) ) {
		return;
	}
	delete_post_meta( $post->ID, '_oria_signup' ); // once only.

	$owner = get_userdata( (int) get_post_meta( $post->ID, 'claimed_by', true ) );
	if ( ! $owner ) {
		return;
	}
	$subject = __( 'Your listing is live on Oria Haven', 'oria' );
	$body    = sprintf(
		/* translators: 1: display name, 2: listing URL */
		__( "G'day %1\$s,\n\nYour listing has been approved and is now live:\n%2\$s\n\nKeep it fresh from your dashboard — and when you're ready for more reach, the upgrade options are right there too.\n\nThe Oria Haven team", 'oria' ),
		$owner->display_name,
		get_permalink( $post )
	);
	if ( function_exists( '\Oria\Forms\Emails\shell' ) ) {
		$html = \Oria\Forms\Emails\shell(
			__( "You're live", 'oria' ),
			'<p>' . nl2br( esc_html( $body ) ) . '</p>'
		);
		wp_mail( $owner->user_email, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		return;
	}
	wp_mail( $owner->user_email, $subject, $body );
}
