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

	$request_id = (int) wp_insert_post(
		array(
			'post_type'   => CPT,
			'post_status' => 'publish',
			'post_title'  => sprintf( '%s — %s', $name, get_post_field( 'post_title', $listing, 'raw' ) ),
		)
	);
	update_post_meta( $request_id, '_listing_id', $listing_id );
	update_post_meta( $request_id, '_name', $name );
	update_post_meta( $request_id, '_email', $email );
	update_post_meta( $request_id, '_phone', $phone );
	update_post_meta( $request_id, '_note', $note );
	update_post_meta( $request_id, '_status', 'pending' );

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

	$user = get_user_by( 'email', $email );
	if ( $user instanceof \WP_User ) {
		$user->add_role( Ownership\ROLE );
		$user_id = (int) $user->ID;
		wp_mail(
			$email,
			__( 'Your listing on Oria Haven is ready to manage', 'oria' ),
			sprintf(
				/* translators: 1 name, 2 listing, 3 login url */
				__( "Hi %1\$s,\n\nYour claim on \"%2\$s\" has been approved. Log in with your existing account to edit your listing:\n%3\$s\n\n— Oria Haven", 'oria' ),
				$name,
				get_post_field( 'post_title', $listing_id, 'raw' ),
				wp_login_url()
			)
		);
	} else {
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
			wp_safe_redirect( add_query_arg( 'oria_decided', 'error', $back ) );
			exit;
		}
		// Core's notification carries the set-password link.
		wp_new_user_notification( (int) $user_id, null, 'user' );
	}

	// Approval links the owner to the listing. With Stripe configured the
	// listing only goes live when payment lands (the webhook stamps status
	// and the verified date); without billing — dev and free mode — approval
	// itself activates, and approval is verification.
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

	if ( ! $free ) {
		$claimed  = \Oria\Core\Tiers\summary( 'claimed' );
		$featured = \Oria\Core\Tiers\summary( 'featured' );
		$bullets  = static fn( array $t ): string => '• ' . implode( "\n• ", $t['features'] );

		\Oria\Core\Billing\owner_mail(
			$listing_id,
			__( 'Choose your plan — Oria Haven', 'oria' ),
			sprintf(
				/* translators: 1 name, 2 listing, 3-5 claimed plan, 6-8 featured plan */
				__( "Hi %1\$s,\n\nYour claim on \"%2\$s\" is approved — one step left. Choose a plan and the listing unlocks the moment payment goes through.\n\nCLAIMED — %3\$s/month\n%4\$s\nActivate: %5\$s\n\nFEATURED — %6\$s/month\n%7\$s\nActivate: %8\$s\n\nUntil then the listing stays live in its free form. Cancel any time — the listing simply returns to its free state, and everything you've added is kept.", 'oria' ),
				$name,
				get_post_field( 'post_title', $listing_id, 'raw' ),
				$claimed['price'],
				$bullets( $claimed ),
				\Oria\Core\Billing\pay_url( 'claimed', $listing_id, $email ),
				$featured['price'],
				$bullets( $featured ),
				\Oria\Core\Billing\pay_url( 'featured', $listing_id, $email )
			) . \Oria\Core\Share\email_block( $listing_id )
		);
	} else {
		// Without billing configured the claim is simply approved, so this
		// is the only email they get — the share kit still belongs in it.
		\Oria\Core\Billing\owner_mail(
			$listing_id,
			__( 'Your claim is approved — Oria Haven', 'oria' ),
			sprintf(
				/* translators: 1 name, 2 listing name */
				__( "Hi %1\$s,\n\nYour claim on \"%2\$s\" is approved. The listing is yours to edit now — sign in and you can keep every detail current.", 'oria' ),
				$name,
				get_post_field( 'post_title', $listing_id, 'raw' )
			) . \Oria\Core\Share\email_block( $listing_id )
		);
	}

	update_post_meta( $request_id, '_status', 'approved' );
	update_post_meta( $request_id, '_approved_user', (int) $user_id );

	wp_safe_redirect( add_query_arg( 'oria_decided', 'approved', $back ) );
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
			echo esc_html( wp_trim_words( (string) get_post_meta( $post_id, '_note', true ), 18 ) );
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
