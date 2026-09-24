<?php
/**
 * Claim requests: the pipeline from "that's my practice" to a practitioner
 * account that can edit it.
 *
 *   1. A visitor submits the claim form on an unclaimed listing.
 *   2. The request lands in a queue under Listings → Claim requests, and the
 *      admin is emailed.
 *   3. Approve: their account is created (or found), given the practitioner
 *      role, linked as the listing's owner, the listing goes claimed, and
 *      they receive a set-password / log-in email.
 *      Decline: the request is closed; nothing else changes.
 *
 * The admin approves the PERSON — nothing about the listing content needs
 * reviewing at claim time, because editing only opens up after approval.
 */

declare(strict_types=1);

namespace Oria\Core\ClaimRequests;

use Oria\Core\PostTypes;
use Oria\Core\Ownership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CPT = 'oria_claim';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register_cpt', 8 );
	add_action( 'admin_post_nopriv_oria_claim', __NAMESPACE__ . '\handle_submission' );
	add_action( 'admin_post_oria_claim', __NAMESPACE__ . '\handle_submission' );
	add_action( 'admin_post_oria_claim_decide', __NAMESPACE__ . '\handle_decision' );
	add_filter( 'manage_' . CPT . '_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_' . CPT . '_posts_custom_column', __NAMESPACE__ . '\column_content', 10, 2 );
	add_filter( 'post_row_actions', __NAMESPACE__ . '\row_actions', 10, 2 );
	add_action( 'admin_notices', __NAMESPACE__ . '\decision_notice' );
	// Correct a claim's email, and resend the approval to the right person.
	add_action( 'admin_menu', __NAMESPACE__ . '\register_email_page' );
	add_action( 'admin_post_oria_claim_email', __NAMESPACE__ . '\handle_email_change' );
	// The general "Claim a listing" form (/claim/) feeds the same queue.
	add_action( 'oria_forms_saved', __NAMESPACE__ . '\from_form', 10, 3 );
}

function register_cpt(): void {
	register_post_type(
		CPT,
		array(
			'labels'       => array(
				'name'          => __( 'Claim requests', 'oria' ),
				'singular_name' => __( 'Claim request', 'oria' ),
				'menu_name'     => __( 'Claim requests', 'oria' ),
				'all_items'     => __( 'Claim requests', 'oria' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=' . PostTypes\LISTING,
			'supports'     => array( 'title' ),
			'capabilities' => array(
				// Requests arrive from the front end only.
				'create_posts' => 'do_not_allow',
			),
			'map_meta_cap' => true,
		)
	);
}

/* ------------------------------------------------------------ submission */

function handle_submission(): void {
	$back = wp_get_referer() ?: home_url( '/' );

	if ( ! isset( $_POST['oria_claim_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( (string) $_POST['oria_claim_nonce'] ), 'oria_claim' ) ) {
		wp_safe_redirect( add_query_arg( 'oria_claim', 'error', $back ) );
		exit;
	}

	// Honeypot: humans never see this field.
	if ( ! empty( $_POST['oria_website_hp'] ) ) {
		wp_safe_redirect( add_query_arg( 'oria_claim', 'received', $back ) );
		exit;
	}

	$listing_id = isset( $_POST['listing_id'] ) ? (int) $_POST['listing_id'] : 0;
	$name       = sanitize_text_field( (string) ( $_POST['claimant_name'] ?? '' ) );
	$email      = sanitize_email( (string) ( $_POST['claimant_email'] ?? '' ) );
	$phone      = sanitize_text_field( (string) ( $_POST['claimant_phone'] ?? '' ) );
	// Their job at the practice. The strongest signal after the email
	// domain, and previously only ever arrived buried in the free note.
	$role       = sanitize_text_field( (string) ( $_POST['claimant_role'] ?? '' ) );
	$note       = sanitize_textarea_field( (string) ( $_POST['claimant_note'] ?? '' ) );

	$listing = get_post( $listing_id );
	if ( ! $listing || PostTypes\LISTING !== $listing->post_type || '' === $name || ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'oria_claim', 'error', $back ) );
		exit;
	}
	if ( Ownership\is_paid( $listing_id ) ) {
		wp_safe_redirect( add_query_arg( 'oria_claim', 'already', $back ) );
		exit;
	}

	// Per-IP throttle plus a duplicate check on listing+email.
	$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'oria_claimreq_' . md5( $ip );
	if ( (int) get_transient( $key ) > 5 ) {
		wp_safe_redirect( add_query_arg( 'oria_claim', 'received', $back ) );
		exit;
	}
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );

	$existing = get_posts(
		array(
			'post_type'      => CPT,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_listing_id', 'value' => $listing_id ),
				array( 'key' => '_email', 'value' => $email ),
				array( 'key' => '_status', 'value' => 'pending' ),
			),
		)
	);
	if ( $existing ) {
		wp_safe_redirect( add_query_arg( 'oria_claim', 'received', get_permalink( $listing_id ) . '#claim' ) );
		exit;
	}

	create( $listing_id, $name, $email, $phone, $note );

	wp_mail(
		(string) get_option( 'admin_email' ),
		sprintf( __( '[Oria Haven] Claim request: %s', 'oria' ), get_post_field( 'post_title', $listing, 'raw' ) ),
		sprintf(
			/* translators: 1 name, 2 email, 3 phone, 4 listing, 5 note, 6 admin url */
			__( "%1\$s (%2\$s%3\$s) has requested to claim \"%4\$s\".\n\nTheir note:\n%5\$s\n\nReview and approve:\n%6\$s", 'oria' ),
			$name,
			$email,
			$phone ? ', ' . $phone : '',
			get_post_field( 'post_title', $listing, 'raw' ),
			$note ?: '—',
			admin_url( 'edit.php?post_type=' . CPT )
		)
	);

	wp_safe_redirect( add_query_arg( 'oria_claim', 'received', get_permalink( $listing_id ) . '#claim' ) );
	exit;
}

/**
 * One pending request in the queue. Shared by the claim button on a listing
 * and the general "Claim a listing" form (/claim/), so both land in the
 * same place and are approved the same way.
 *
 * @param string $source 'listing' (the listing page) or 'form' (oria-forms).
 */
function create( int $listing_id, string $name, string $email, string $phone, string $note, string $source = 'listing', int $entry_id = 0 ): int {
	$request_id = (int) wp_insert_post(
		array(
			'post_type'   => CPT,
			'post_status' => 'publish',
			'post_title'  => sprintf( '%s — %s', $name, get_post_field( 'post_title', $listing_id, 'raw' ) ),
		)
	);
	if ( ! $request_id ) {
		return 0;
	}
	update_post_meta( $request_id, '_listing_id', $listing_id );
	update_post_meta( $request_id, '_name', $name );
	update_post_meta( $request_id, '_email', $email );
	update_post_meta( $request_id, '_phone', $phone );
	update_post_meta( $request_id, '_role', $role );
	update_post_meta( $request_id, '_note', $note );
	update_post_meta( $request_id, '_status', 'pending' );
	update_post_meta( $request_id, '_source', $source );
	if ( $entry_id ) {
		update_post_meta( $request_id, '_entry_id', $entry_id );
	}
	return $request_id;
}

/** A request for this listing from this email, in any state. */
function exists_for( int $listing_id, string $email ): bool {
	return (bool) get_posts(
		array(
			'post_type'      => CPT,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_listing_id', 'value' => $listing_id ),
				array( 'key' => '_email', 'value' => $email ),
			),
		)
	);
}

/**
 * The listing a "Claim a listing" form entry is about, or 0.
 *
 * The form's lookup writes "Name — https://…/listing/slug/" into
 * listing_ref when a listing is picked, so the URL is the reliable part.
 * Without it (someone typed a name and never picked), fall back to a
 * published listing whose title matches the typed name exactly -- and only
 * when exactly one does, so a guess never becomes someone else's listing.
 *
 * @param array<string, string> $values the entry's fields.
 */
function listing_from_form( array $values ): int {
	$ref = (string) ( $values['listing_ref'] ?? '' );
	if ( preg_match( '~https?://\S+~', $ref, $m ) ) {
		$id = url_to_postid( $m[0] );
		if ( ! $id ) {
			// The URL may carry another host (a production entry read on a
			// local copy); the slug is what identifies the listing.
			$slug = basename( untrailingslashit( (string) wp_parse_url( $m[0], PHP_URL_PATH ) ) );
			$post = '' !== $slug ? get_page_by_path( $slug, OBJECT, PostTypes\LISTING ) : null;
			$id   = $post ? (int) $post->ID : 0;
		}
		if ( $id && PostTypes\LISTING === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
			return (int) $id;
		}
	}

	$name = trim( (string) ( $values['practice'] ?? '' ) );
	if ( '' === $name ) {
		return 0;
	}
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_title = %s LIMIT 2",
			PostTypes\LISTING,
			$name
		)
	);
	return 1 === count( $ids ) ? (int) $ids[0] : 0;
}

/**
 * A submission of the general "Claim a listing" form (oria-forms, form id
 * "claim") becomes a request in the queue, provided it names a listing we
 * can identify. The form has already emailed the admin and the claimant,
 * so nothing is sent from here. An entry that matches no listing stays in
 * Form entries only -- there is nothing to approve it against yet.
 *
 * @param array<string, string> $values
 */
function from_form( string $form_id, array $values, int $entry_id = 0 ): int {
	if ( 'claim' !== $form_id ) {
		return 0;
	}
	$email      = sanitize_email( (string) ( $values['email'] ?? '' ) );
	$name       = sanitize_text_field( (string) ( $values['name'] ?? '' ) );
	$listing_id = listing_from_form( $values );
	if ( ! $listing_id || '' === $name || ! is_email( $email ) ) {
		return 0;
	}
	if ( Ownership\is_paid( $listing_id ) || exists_for( $listing_id, $email ) ) {
		return 0;
	}
	return create(
		$listing_id,
		$name,
		$email,
		sanitize_text_field( (string) ( $values['phone'] ?? '' ) ),
		sanitize_textarea_field( (string) ( $values['message'] ?? '' ) ),
		'form',
		$entry_id
	);
}

/* -------------------------------------------------------------- decision */

function handle_decision(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ) );
	}
	$request_id = isset( $_GET['request'] ) ? (int) $_GET['request'] : 0;
	$decision   = isset( $_GET['decision'] ) ? sanitize_key( (string) $_GET['decision'] ) : '';
	check_admin_referer( 'oria_claim_decide_' . $request_id );

	$back = admin_url( 'edit.php?post_type=' . CPT );
	$request = get_post( $request_id );
	if ( ! $request || CPT !== $request->post_type || ! in_array( $decision, array( 'approve', 'decline' ), true ) ) {
		wp_safe_redirect( add_query_arg( 'oria_decided', 'error', $back ) );
		exit;
	}
	if ( 'pending' !== (string) get_post_meta( $request_id, '_status', true ) ) {
		wp_safe_redirect( add_query_arg( 'oria_decided', 'stale', $back ) );
		exit;
	}

	if ( 'decline' === $decision ) {
		update_post_meta( $request_id, '_status', 'declined' );
		wp_safe_redirect( add_query_arg( 'oria_decided', 'declined', $back ) );
		exit;
	}

	// ---- approve ----
	$listing_id = (int) get_post_meta( $request_id, '_listing_id', true );
	$email      = (string) get_post_meta( $request_id, '_email', true );
	$name       = (string) get_post_meta( $request_id, '_name', true );

	if ( ! get_post( $listing_id ) ) {
		wp_safe_redirect( add_query_arg( 'oria_decided', 'error', $back ) );
		exit;
	}

	$current_owner = (int) get_post_meta( $listing_id, 'claimed_by', true );
	if ( $current_owner && Ownership\is_paid( $listing_id ) ) {
		wp_safe_redirect( add_query_arg( 'oria_decided', 'taken', $back ) );
		exit;
	}

	$user        = get_user_by( 'email', $email );
	$new_account = false;
	if ( $user instanceof \WP_User ) {
		$user->add_role( Ownership\ROLE );
		$user_id = (int) $user->ID;
	} else {
		$user_id = new_owner_account( $email, $name );
		if ( ! $user_id ) {
			wp_safe_redirect( add_query_arg( 'oria_decided', 'error', $back ) );
			exit;
		}
		$new_account = true;
	}

	/*
	 * Approval links the owner to the listing, and costs nothing.
	 *
	 * With billing configured the owner lands on the free plan: claimed_by
	 * is theirs and tiers.php decides what that lets them edit -- address,
	 * contact details, prices, format. A paid tier switches on only when a
	 * payment lands (the webhook stamps status and the verified date).
	 * Without billing -- dev and free mode -- approval itself activates the
	 * Claimed tier, and approval is verification.
	 */
	$today = current_time( 'Y-m-d' );
	$free  = ! \Oria\Core\Billing\configured();
	if ( function_exists( 'update_field' ) ) {
		update_field( 'claimed_by', (int) $user_id, $listing_id );
		if ( $free ) {
			update_field( 'claim_status', 'claimed', $listing_id );
			update_field( 'verified_at', $today, $listing_id );
		}
	} else {
		update_post_meta( $listing_id, 'claimed_by', (int) $user_id );
		if ( $free ) {
			update_post_meta( $listing_id, 'claim_status', 'claimed' );
			update_post_meta( $listing_id, 'verified_at', $today );
		}
	}

	/*
	 * One email, and it says the claim is free.
	 *
	 * With billing on, this used to be headed "Choose your plan" and open
	 * with "one step left", which read as a paywall to an owner who had
	 * been told claiming costs nothing. An owner with an existing account
	 * also got a second, separate approval email promising photos and hours
	 * that the free plan does not include. The paid plans are still
	 * offered, after everything else and marked optional.
	 */
	\Oria\Core\Audit\note(
		$listing_id,
		$free
			/* translators: %s: the new owner's name */
			? sprintf( __( 'Claim approved. %s owns this listing and holds the Claimed tier, because billing is not configured.', 'oria' ), $name )
			/* translators: %s: the new owner's name */
			: sprintf( __( 'Claim approved. %s owns this listing, on the free plan until a payment lands.', 'oria' ), $name ),
		get_current_user_id()
	);

	send_approved( $email, $listing_id, $name, $new_account );

	update_post_meta( $request_id, '_status', 'approved' );
	update_post_meta( $request_id, '_approved_user', (int) $user_id );
	// Whether this approval made the account -- what decides, if the email
	// later turns out to be wrong, that the account can simply be moved.
	update_post_meta( $request_id, '_new_account', $new_account ? '1' : '0' );

	wp_safe_redirect( add_query_arg( 'oria_decided', 'approved', $back ) );
	exit;
}

/**
 * "Your claim is approved" — the words only, no sending.
 *
 * Split out from the approval handler for two reasons. It was the last
 * email in the system still hand-signed "— Oria Haven" in plain text,
 * skipping the branded shell and the contact details every other email
 * now carries; sending it through Signup\send() fixes that in one place.
 * And a body builder with nothing else attached can be rendered on the
 * email preview screen without approving anybody's claim to look at it.
 */
function approved_body( int $listing_id, string $name, bool $new_account = false, ?bool $billing = null ): string {
	$billing = null === $billing ? \Oria\Core\Billing\configured() : $billing;
	$title   = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ) );

	$body = sprintf(
		/* translators: 1 name, 2 listing name */
		__( "Hi %1\$s,\n\nYour claim on \"%2\$s\" is approved, and it's free. There's no monthly fee and no card needed. The listing is yours to look after for as long as you like, and it no longer shows as Unclaimed.", 'oria' ),
		$name,
		$title
	);

	$body .= $new_account
		/* translators: %s: login url */
		? sprintf( __( "\n\nWe've sent you a separate email with a link to set your password. Once that's done, sign in here:\n%s", 'oria' ), wp_login_url() )
		/* translators: %s: login url */
		: sprintf( __( "\n\nSign in with your existing account to manage it:\n%s", 'oria' ), wp_login_url() );

	$body .= $billing
		? __( "\n\nOn the free plan you can keep your address, phone, email, website, prices and session format up to date yourself. If anything else on the listing looks wrong, reply to this email and we'll fix it for you.", 'oria' )
		: __( "\n\nYou can edit your description, services, photos, hours and contact details whenever you like. If anything looks wrong, reply to this email and we'll sort it out.", 'oria' );

	/*
	 * The Pass, said once, to somebody who has just proved they own the
	 * place. This is the first moment we can tell them about something
	 * that puts people through their door rather than in front of their
	 * name, and it is a better second sentence than a plan upgrade.
	 * Left out entirely when the Pass is not installed.
	 */
	if ( function_exists( '\Oria\Pass\Route\url' ) ) {
		$body .= sprintf(
			/* translators: 1: dashboard url, 2: partners page url */
			__( "\n\nSomething new, while you are here. We are starting Oria Pass: a monthly membership where people buy credits and spend them across Perth studios. You choose which sessions to open, how many places you can spare, and what each place is worth to you — and you keep every booking you already have. No fee to join, nothing to install.\n\nIt takes about a minute to open your first one:\n%1\$s\n\nHow it works for studios:\n%2\$s", 'oria' ),
			function_exists( '\Oria\Core\MyOria\url' ) ? add_query_arg( 'tab', 'add', \Oria\Core\MyOria\url( 'pass' ) ) : \Oria\Pass\Route\url(),
			\Oria\Pass\Route\url( 'partners' )
		);
	}

	if ( function_exists( '\Oria\Core\Share\email_block' ) ) {
		$body .= \Oria\Core\Share\email_block( $listing_id );
	}

	// The paid plans, only where they exist, and only after the free
	// claim has been stated in full. The block itself says "optional".
	if ( $billing && function_exists( '\Oria\Core\Signup\upgrade_block' ) ) {
		$body .= \Oria\Core\Signup\upgrade_block( $listing_id, '' );
	}

	return $body;
}

function send_approved( string $email, int $listing_id, string $name, bool $new_account = false ): void {
	\Oria\Core\Signup\send(
		$email,
		__( "Your claim is approved, and it's free", 'oria' ),
		__( 'Your claim is approved', 'oria' ),
		approved_body( $listing_id, $name, $new_account )
	);
}

/**
 * A practitioner account for a claim, with core's set-password email.
 *
 * @return int The new user id, or 0 on failure.
 */
function new_owner_account( string $email, string $name ): int {
	$username = sanitize_user( (string) strstr( $email, '@', true ), true );
	if ( '' === $username || username_exists( $username ) ) {
		$username = sanitize_user( $username . wp_rand( 100, 999 ), true );
	}
	$user_id = wp_insert_user(
		array(
			'user_login'   => $username,
			'user_email'   => $email,
			'display_name' => $name,
			'user_pass'    => wp_generate_password( 24 ),
			'role'         => Ownership\ROLE,
		)
	);
	if ( is_wp_error( $user_id ) ) {
		return 0;
	}
	// Core's notification carries the set-password link.
	wp_new_user_notification( (int) $user_id, null, 'user' );
	return (int) $user_id;
}

/** Point the listing at its owner. */
function link_owner( int $listing_id, int $user_id ): void {
	if ( function_exists( 'update_field' ) ) {
		update_field( 'claimed_by', $user_id, $listing_id );
	} else {
		update_post_meta( $listing_id, 'claimed_by', $user_id );
	}
}

/** How many listings this user owns. */
function owned_count( int $user_id ): int {
	return count(
		get_posts(
			array(
				'post_type'      => PostTypes\LISTING,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => 'claimed_by', 'value' => $user_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		)
	);
}

/**
 * Whether this claim's approval made this account -- the account can then
 * be moved to the corrected email rather than a second one made.
 *
 * Approvals record it (_new_account). For ones made before that, it is
 * read from the account: registered no earlier than the request, holding
 * only the practitioner role, and owning nothing but this one listing. An
 * existing account that merely got linked fails at least one of those, and
 * is left alone.
 */
function made_by_claim( int $request_id, \WP_User $user ): bool {
	if ( owned_count( (int) $user->ID ) > 1 ) {
		return false;
	}
	$flag = (string) get_post_meta( $request_id, '_new_account', true );
	if ( '' !== $flag ) {
		return '1' === $flag;
	}
	$registered = strtotime( $user->user_registered . ' UTC' );
	$requested  = (int) get_post_time( 'U', true, $request_id );
	return $registered && $registered >= $requested - 60 && array( Ownership\ROLE ) === array_values( (array) $user->roles );
}

/* ------------------------------------------------------ change the email */

function register_email_page(): void {
	// Hidden: reached from the claim's row action, never from the menu.
	add_submenu_page( 'options.php', __( 'Claim email', 'oria' ), __( 'Claim email', 'oria' ), 'manage_options', 'oria-claim-email', __NAMESPACE__ . '\render_email_page' );
}

function email_page_url( int $request_id ): string {
	return admin_url( 'options.php?page=oria-claim-email&request=' . $request_id );
}

function render_email_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ) );
	}
	$request_id = isset( $_GET['request'] ) ? (int) $_GET['request'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
	$request    = get_post( $request_id );
	if ( ! $request || CPT !== $request->post_type ) {
		wp_die( esc_html__( 'Claim request not found.', 'oria' ) );
	}
	$status     = (string) ( get_post_meta( $request_id, '_status', true ) ?: 'pending' );
	$email      = (string) get_post_meta( $request_id, '_email', true );
	$name       = (string) get_post_meta( $request_id, '_name', true );
	$listing_id = (int) get_post_meta( $request_id, '_listing_id', true );
	$history    = array_filter( (array) get_post_meta( $request_id, '_email_history', true ) );
	$approved   = 'approved' === $status;
	?>
	<div class="wrap">
		<h1><?php echo esc_html( $approved ? __( 'Change the email and resend the approval', 'oria' ) : __( 'Change the claim email', 'oria' ) ); ?></h1>
		<?php if ( isset( $_GET['oria_decided'] ) && 'bad_email' === $_GET['oria_decided'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'That is not a valid email address.', 'oria' ); ?></p></div>
		<?php endif; ?>
		<p>
			<?php
			printf(
				/* translators: 1: person, 2: listing */
				esc_html__( 'The claim by %1$s on %2$s.', 'oria' ),
				'<b>' . esc_html( $name ) . '</b>',
				'<b>' . esc_html( (string) get_post_field( 'post_title', $listing_id, 'raw' ) ) . '</b>'
			);
			?>
		</p>
		<?php if ( $approved ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'Saving sends the approval email again to the address below, with a fresh link to set a password. If the approval made a new account, that account moves to this address: its password is scrambled, anyone signed in is signed out, and the link already sent to the old address stops working. If this address already has an account, the listing moves to that account instead.', 'oria' ); ?>
			</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_claim_email">
			<input type="hidden" name="request" value="<?php echo (int) $request_id; ?>">
			<?php wp_nonce_field( 'oria_claim_email_' . $request_id ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Current email', 'oria' ); ?></th>
					<td><code><?php echo esc_html( $email ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><label for="oriaClaimEmail"><?php esc_html_e( 'Correct email', 'oria' ); ?></label></th>
					<td><input type="email" class="regular-text" id="oriaClaimEmail" name="email" value="<?php echo esc_attr( $email ); ?>" required></td>
				</tr>
			</table>
			<?php submit_button( $approved ? __( 'Save and resend the approval', 'oria' ) : __( 'Save email', 'oria' ) ); ?>
		</form>
		<?php if ( $history ) : ?>
			<h2><?php esc_html_e( 'Earlier addresses', 'oria' ); ?></h2>
			<ul>
				<?php foreach ( $history as $h ) : ?>
					<li><?php echo esc_html( sprintf( '%s → %s (%s)', (string) ( $h['from'] ?? '' ), (string) ( $h['to'] ?? '' ), (string) ( $h['when'] ?? '' ) ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CPT ) ); ?>">&larr; <?php esc_html_e( 'Back to claim requests', 'oria' ); ?></a></p>
	</div>
	<?php
}

function handle_email_change(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ) );
	}
	$request_id = isset( $_POST['request'] ) ? (int) $_POST['request'] : 0;
	check_admin_referer( 'oria_claim_email_' . $request_id );

	$back    = admin_url( 'edit.php?post_type=' . CPT );
	$request = get_post( $request_id );
	$new     = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );
	if ( ! $request || CPT !== $request->post_type ) {
		wp_safe_redirect( add_query_arg( 'oria_decided', 'error', $back ) );
		exit;
	}
	if ( ! is_email( $new ) ) {
		wp_safe_redirect( add_query_arg( 'oria_decided', 'bad_email', email_page_url( $request_id ) ) );
		exit;
	}

	$status     = (string) ( get_post_meta( $request_id, '_status', true ) ?: 'pending' );
	$old        = (string) get_post_meta( $request_id, '_email', true );
	$name       = (string) get_post_meta( $request_id, '_name', true );
	$listing_id = (int) get_post_meta( $request_id, '_listing_id', true );
	$changed    = strtolower( $old ) !== strtolower( $new );

	if ( $changed ) {
		$history   = array_filter( (array) get_post_meta( $request_id, '_email_history', true ) );
		$history[] = array( 'from' => $old, 'to' => $new, 'when' => current_time( 'Y-m-d H:i' ), 'by' => get_current_user_id() );
		update_post_meta( $request_id, '_email_history', array_values( $history ) );
		update_post_meta( $request_id, '_email', $new );
	}

	// Not approved yet: the corrected address is simply what approval uses.
	if ( 'approved' !== $status ) {
		wp_safe_redirect( add_query_arg( 'oria_decided', 'email_saved', $back ) );
		exit;
	}

	$old_user    = get_user_by( 'id', (int) get_post_meta( $request_id, '_approved_user', true ) );
	$existing    = get_user_by( 'email', $new );
	$new_account = false;

	if ( $existing instanceof \WP_User && ( ! $old_user instanceof \WP_User || (int) $existing->ID !== (int) $old_user->ID ) ) {
		// The right person already has an account: the listing moves to it.
		$existing->add_role( Ownership\ROLE );
		$user_id = (int) $existing->ID;
	} elseif ( $old_user instanceof \WP_User && made_by_claim( $request_id, $old_user ) ) {
		/*
		 * The account this claim made: move it to the right address, and
		 * shut out whoever received the first email. A new password ends
		 * any password they set; destroying sessions ends any log-in; and
		 * the fresh set-password link below replaces the reset key, so the
		 * link in the misdirected email no longer works.
		 */
		$user_id = (int) $old_user->ID;
		if ( $changed ) {
			add_filter( 'send_email_change_email', '__return_false' ); // no "your email changed" note to the wrong person
			$ok = wp_update_user( array( 'ID' => $user_id, 'user_email' => $new ) );
			remove_filter( 'send_email_change_email', '__return_false' );
			if ( is_wp_error( $ok ) ) {
				wp_safe_redirect( add_query_arg( 'oria_decided', 'error', $back ) );
				exit;
			}
			wp_set_password( wp_generate_password( 24 ), $user_id );
			\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		}
		wp_new_user_notification( $user_id, null, 'user' );
		$new_account = true;
	} else {
		// Linked to somebody's own account: leave it be, and make one for
		// the right address.
		$user_id = new_owner_account( $new, $name );
		if ( ! $user_id ) {
			wp_safe_redirect( add_query_arg( 'oria_decided', 'error', $back ) );
			exit;
		}
		$new_account = true;
	}

	link_owner( $listing_id, $user_id );
	// A different account took over: the previous one keeps nothing it only
	// had through this listing.
	if ( $old_user instanceof \WP_User && (int) $old_user->ID !== $user_id && 0 === owned_count( (int) $old_user->ID ) ) {
		$old_user->remove_role( Ownership\ROLE );
	}
	update_post_meta( $request_id, '_approved_user', $user_id );
	update_post_meta( $request_id, '_new_account', $new_account ? '1' : '0' );

	send_approved( $new, $listing_id, $name, $new_account );

	\Oria\Core\Audit\note(
		$listing_id,
		$changed
			/* translators: 1: old email, 2: new email */
			? sprintf( __( 'Claim email corrected from %1$s to %2$s, and the approval resent.', 'oria' ), $old, $new )
			/* translators: %s: email */
			: sprintf( __( 'Claim approval resent to %s.', 'oria' ), $new ),
		get_current_user_id()
	);

	wp_safe_redirect( add_query_arg( 'oria_decided', 'resent', $back ) );
	exit;
}

/* ------------------------------------------------------------- admin ui */

function columns( array $columns ): array {
	return array(
		'cb'           => $columns['cb'] ?? '<input type="checkbox" />',
		'title'        => __( 'Request', 'oria' ),
		'oria_listing' => __( 'Listing', 'oria' ),
		'oria_contact' => __( 'Contact', 'oria' ),
		'oria_note'    => __( 'Note', 'oria' ),
		'oria_state'   => __( 'Status', 'oria' ),
		'date'         => __( 'Date', 'oria' ),
	);
}

function column_content( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'oria_listing':
			$listing_id = (int) get_post_meta( $post_id, '_listing_id', true );
			if ( $listing_id ) {
				printf(
					'<a href="%s">%s</a>',
					esc_url( (string) get_edit_post_link( $listing_id ) ),
					esc_html( get_post_field( 'post_title', $listing_id, 'raw' ) )
				);
			}
			break;
		case 'oria_contact':
			$email = (string) get_post_meta( $post_id, '_email', true );
			$phone = (string) get_post_meta( $post_id, '_phone', true );
			echo esc_html( $email );
			if ( $phone ) {
				echo '<br><span style="color:#50575e">' . esc_html( $phone ) . '</span>';
			}
			break;
		case 'oria_note':
			$role = (string) get_post_meta( $post_id, '_role', true );
			if ( '' !== $role ) {
				echo '<b>' . esc_html( $role ) . '</b><br>';
			}
			echo esc_html( wp_trim_words( (string) get_post_meta( $post_id, '_note', true ), 18 ) );
			if ( 'form' === (string) get_post_meta( $post_id, '_source', true ) ) {
				echo '<br><span style="color:#50575e">' . esc_html__( 'Via the Claim a listing form', 'oria' ) . '</span>';
			}
			break;
		case 'oria_state':
			$status = (string) ( get_post_meta( $post_id, '_status', true ) ?: 'pending' );
			$labels = array(
				'pending'  => array( __( 'Pending', 'oria' ), '#f0b849' ),
				'approved' => array( __( 'Approved', 'oria' ), '#1a7a3f' ),
				'declined' => array( __( 'Declined', 'oria' ), '#b32d2e' ),
			);
			$row = $labels[ $status ] ?? $labels['pending'];
			printf( '<b style="color:%s">%s</b>', esc_attr( $row[1] ), esc_html( $row[0] ) );
			break;
	}
}

/** Approve / Decline links on pending rows. */
function row_actions( array $actions, \WP_Post $post ): array {
	if ( CPT !== $post->post_type ) {
		return $actions;
	}
	unset( $actions['inline hide-if-no-js'], $actions['edit'] );

	if ( 'pending' === (string) ( get_post_meta( $post->ID, '_status', true ) ?: 'pending' ) ) {
		$base = admin_url( 'admin-post.php?action=oria_claim_decide&request=' . $post->ID );
		$actions = array(
			'oria_approve' => sprintf(
				'<a href="%s" style="color:#1a7a3f;font-weight:600">%s</a>',
				esc_url( wp_nonce_url( $base . '&decision=approve', 'oria_claim_decide_' . $post->ID ) ),
				esc_html__( 'Approve', 'oria' )
			),
			'oria_decline' => sprintf(
				'<a href="%s" style="color:#b32d2e">%s</a>',
				esc_url( wp_nonce_url( $base . '&decision=decline', 'oria_claim_decide_' . $post->ID ) ),
				esc_html__( 'Decline', 'oria' )
			),
		) + $actions;
	}
	$state = (string) ( get_post_meta( $post->ID, '_status', true ) ?: 'pending' );
	if ( 'declined' !== $state ) {
		$actions['oria_email'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( email_page_url( (int) $post->ID ) ),
			'approved' === $state ? esc_html__( 'Change email / resend approval', 'oria' ) : esc_html__( 'Change email', 'oria' )
		);
	}
	return $actions;
}

function decision_notice(): void {
	if ( empty( $_GET['oria_decided'] ) ) {
		return;
	}
	$messages = array(
		'approved' => array( 'success', __( 'Claim approved. The practitioner account is linked and their log-in email is on its way.', 'oria' ) ),
		'declined' => array( 'info', __( 'Claim declined.', 'oria' ) ),
		'taken'    => array( 'error', __( 'That listing is already claimed by another account — resolve the existing owner first.', 'oria' ) ),
		'stale'    => array( 'warning', __( 'That request was already decided.', 'oria' ) ),
		'error'    => array( 'error', __( 'Something went wrong deciding that request.', 'oria' ) ),
		'resent'      => array( 'success', __( 'Approval resent. The claim now uses the corrected email, and a fresh set-password link went with it.', 'oria' ) ),
		'email_saved' => array( 'success', __( 'Email updated. Approving the claim will use the new address.', 'oria' ) ),
		'bad_email'   => array( 'error', __( 'That is not a valid email address.', 'oria' ) ),
	);
	$key = sanitize_key( (string) $_GET['oria_decided'] );
	if ( ! isset( $messages[ $key ] ) ) {
		return;
	}
	printf(
		'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
		esc_attr( $messages[ $key ][0] ),
		esc_html( $messages[ $key ][1] )
	);
}
