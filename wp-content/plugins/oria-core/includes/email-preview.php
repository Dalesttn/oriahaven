<?php
/**
 * Listings → Email preview: see what every email actually looks like.
 *
 * The obvious way to build this is a page that rebuilds each email's body
 * so it can be displayed. That version is wrong within a month — the real
 * sender gets edited, the preview doesn't, and you end up confidently
 * looking at something nobody receives.
 *
 * So nothing here rebuilds anything. Each preview runs the genuine send
 * function with pre_wp_mail hooked to capture the message and stop it
 * leaving. What you see is byte-for-byte what wp_mail() was handed, which
 * means a preview cannot drift from the email: if the sender changes, so
 * does this, and if a sender breaks, this breaks with it.
 *
 * The consequence is that only senders without side effects can appear.
 * Minting an invitation token or creating a practitioner account to look
 * at an email would be a bad trade, so those entries call the same body
 * builders the sender calls, one layer down, and say so.
 *
 * Sending a real test uses the identical callable with the capture off.
 */

declare(strict_types=1);

namespace Oria\Core\EmailPreview;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SLUG = 'oria-email-preview';

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_email_test', __NAMESPACE__ . '\handle_test' );
}

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . PostTypes\LISTING,
		__( 'Email preview', 'oria' ),
		__( 'Email preview', 'oria' ),
		'manage_options',
		SLUG,
		__NAMESPACE__ . '\render'
	);
}

/* ------------------------------------------------------------- capturing */

/**
 * Run something that sends email, and catch the email instead.
 *
 * @return array{sent: bool, to: string, subject: string, body: string, headers: array<int, string>}
 */
function capture( callable $sender ): array {
	$caught = array( 'sent' => false, 'to' => '', 'subject' => '', 'body' => '', 'headers' => array() );

	$catch = static function ( $short_circuit, array $atts ) use ( &$caught ) {
		unset( $short_circuit );
		$caught['sent']    = true;
		$caught['to']      = is_array( $atts['to'] ?? '' ) ? implode( ', ', $atts['to'] ) : (string) ( $atts['to'] ?? '' );
		$caught['subject'] = (string) ( $atts['subject'] ?? '' );
		$caught['body']    = (string) ( $atts['message'] ?? '' );
		$caught['headers'] = (array) ( $atts['headers'] ?? array() );
		return true; // Tell wp_mail it succeeded, without sending anything.
	};

	add_filter( 'pre_wp_mail', $catch, 10, 2 );
	try {
		$sender();
	} finally {
		remove_filter( 'pre_wp_mail', $catch, 10 );
	}
	return $caught;
}

/* -------------------------------------------------------------- registry */

/** A listing to build the samples from — a claimed one where possible. */
function sample_listing(): int {
	$owned = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'meta_key'       => 'claimed_by', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_compare'   => 'EXISTS',
		)
	);
	// claimed_by can point at a user who has since been deleted, so an
	// owner is only an owner if the account is still there to email.
	foreach ( $owned as $owned_id ) {
		$user = get_userdata( (int) get_post_meta( (int) $owned_id, 'claimed_by', true ) );
		if ( $user && is_email( $user->user_email ) ) {
			return (int) $owned_id;
		}
	}
	$any = get_posts( array( 'post_type' => PostTypes\LISTING, 'posts_per_page' => 1, 'fields' => 'ids' ) );
	return (int) ( $any[0] ?? 0 );
}

/** The owner of the sample listing, or whoever is looking at the screen. */
function sample_user( int $listing_id ): \WP_User {
	$owner = get_userdata( (int) get_post_meta( $listing_id, 'claimed_by', true ) );
	return $owner instanceof \WP_User ? $owner : wp_get_current_user();
}

/**
 * Every email we can show, and how to produce it.
 *
 * In the order a practitioner meets them, because that ordering is the
 * thing worth checking: read straight down and you are reading everything
 * one practice receives, in sequence, which is how you notice that two
 * emails open the same way or that nothing ever asks for the review.
 *
 * Adding one is an entry here — no template, no copy repeated anywhere.
 *
 * @return array<string, array{label: string, when: string, who: string, run: callable, note?: string}>
 */
function registry(): array {
	$id      = sample_listing();
	$post    = get_post( $id );
	$owner   = sample_user( $id );
	$sample  = array(
		'practice_name'  => wp_specialchars_decode( (string) get_the_title( $id ) ),
		'practice_cat'   => 'Yoga',
		'suburb'         => 'Fremantle',
		'account_name'   => $owner->display_name ?: 'Sam Whitfield',
		'account_email'  => $owner->user_email,
	);

	$emails = array(
		'invite'     => array(
			'label' => __( 'Invitation to claim a listing', 'oria' ),
			'when'  => __( 'You press "Send invite" on a listing.', 'oria' ),
			'who'   => __( 'A practice that has never heard from us', 'oria' ),
			'note'  => __( 'Built from the same functions the sender uses. The sender itself is not run here because it would mint a token and log a send against the listing.', 'oria' ),
			'run'   => static function () use ( $id ): void {
				wp_mail(
					'preview@example.test',
					\Oria\Core\Invites\subject( $id, false ),
					\Oria\Forms\Emails\shell(
						'Preview',
						\Oria\Core\Invites\body_html( $id, 'SAMPLE-TOKEN-NOT-REAL' ),
						'masthead'
					),
					\Oria\Forms\Emails\html_headers()
				);
			},
		),
		'follow_up'  => array(
			'label' => __( 'Invitation follow-up', 'oria' ),
			'when'  => __( 'You press "Send follow-up". There is never a third.', 'oria' ),
			'who'   => __( 'A practice that did not reply to the first', 'oria' ),
			'run'   => static function () use ( $id ): void {
				wp_mail(
					'preview@example.test',
					\Oria\Core\Invites\subject( $id, true ),
					\Oria\Forms\Emails\shell(
						'Preview',
						\Oria\Core\Invites\follow_up_html( $id, 'SAMPLE-TOKEN-NOT-REAL' ),
						'masthead'
					),
					\Oria\Forms\Emails\html_headers()
				);
			},
		),
		'received'   => array(
			'label' => __( 'We have your listing', 'oria' ),
			'when'  => __( 'A practice signs itself up through /list-your-practice.', 'oria' ),
			'who'   => __( 'The practitioner who just submitted', 'oria' ),
			'note'  => __( 'Shown in its "you already had an account" form. The other version mints a set-password link, and generating one here would quietly invalidate a real one somebody is holding.', 'oria' ),
			'run'   => static function () use ( $id, $owner, $sample ): void {
				\Oria\Core\Signup\received_email( (int) $owner->ID, $id, $sample, false );
			},
		),
		'signup_admin' => array(
			'label' => __( 'New signup, to you', 'oria' ),
			'when'  => __( 'Same moment as the one above — this is your copy.', 'oria' ),
			'who'   => __( 'You', 'oria' ),
			'run'   => static function () use ( $id, $owner, $sample ): void {
				\Oria\Core\Signup\admin_email( (int) $owner->ID, $id, $sample );
			},
		),
		'approved'   => array(
			'label' => __( 'Claim approved', 'oria' ),
			'when'  => __( 'You approve a claim request in Listings → Claim requests.', 'oria' ),
			'who'   => __( 'The practitioner who claimed it', 'oria' ),
			'run'   => static function () use ( $id, $owner ): void {
				\Oria\Core\ClaimRequests\send_approved(
					$owner->user_email,
					$id,
					$owner->display_name ?: (string) $owner->user_login
				);
			},
		),
		'owner'      => array(
			'label' => __( 'Notice to a listing owner', 'oria' ),
			'when'  => __( 'Plan changed, payment failed, anything about their own listing.', 'oria' ),
			'who'   => __( 'A practitioner who owns a listing', 'oria' ),
			'note'  => __( 'The real owner_mail() wrapper every practitioner notice passes through — including the Oria Digital line, if that is switched on in Settings → General. Only the middle paragraph is a stand-in.', 'oria' ),
			'run'   => static function () use ( $id ): void {
				\Oria\Core\Billing\owner_mail(
					$id,
					__( 'A note about your listing — Oria Haven', 'oria' ),
					__( "Hi there,\n\nThis is the frame every email about your own listing arrives in. The message changes; everything around it does not.", 'oria' )
				);
			},
		),
	);

	if ( $post instanceof \WP_Post && function_exists( '\Oria\Core\Signup\live_body' ) ) {
		$emails['live'] = array(
			'label' => __( "You're live", 'oria' ),
			'when'  => __( 'You publish a listing that came in through signup.', 'oria' ),
			'who'   => __( 'The practitioner whose listing just went live', 'oria' ),
			'note'  => __( 'The genuine body, including the share block and the upgrade options. Sent one layer down from live_email(), which clears the once-only flag as it goes and would spend the real email to show you this one.', 'oria' ),
			'run'   => static function () use ( $post, $owner ): void {
				\Oria\Core\Signup\send(
					$owner->user_email,
					__( 'Your listing is live on Oria Haven', 'oria' ),
					__( "You're live", 'oria' ),
					\Oria\Core\Signup\live_body( $post, $owner )
				);
			},
		);
	}

	if ( function_exists( '\Oria\Core\Leads\deliver' ) ) {
		$emails['lead'] = array(
			'label' => __( 'An enquiry', 'oria' ),
			'when'  => __( 'Somebody uses an enquiry form, or asks to be matched.', 'oria' ),
			'who'   => __( 'The practice being enquired with', 'oria' ),
			'note'  => __( 'The real delivery function, with made-up answers in place of a visitor\'s.', 'oria' ),
			'run'   => static function () use ( $id ): void {
				\Oria\Core\Leads\deliver(
					$id,
					'preview@example.test',
					array(
						'name'  => 'Sam Whitfield',
						'email' => 'sam@example.com',
						'phone' => '0400 000 000',
						'notes' => 'Complete beginner — is an evening class alright to start with?',
					),
					true,
					'Weekday evenings'
				);
			},
		);
	}

	return apply_filters( 'oria_email_previews', $emails );
}

/**
 * Emails that exist but cannot be shown, and why.
 *
 * Naming them matters more than it looks. A preview screen that silently
 * covers most of the emails invites the belief that it covers all of them,
 * and the next person to change one of these will check here, see nothing,
 * and assume there was nothing to check.
 *
 * @return array<string, string>
 */
function unpreviewable(): array {
	return array(
		__( 'Claim request received (your copy)', 'oria' )   => __( 'Composed inside the form handler, which writes the request before it sends. Goes to you, so it is the least costly one to test by simply making a claim request.', 'oria' ),
		__( 'Website review request and confirmation', 'oria' ) => __( 'The handler fetches and inspects the live website before it writes a word, so there is no body to render without making that request.', 'oria' ),
		__( 'Set your password', 'oria' )                    => __( "WordPress sends this one, not us. Generating a preview would invalidate any reset link already in somebody's inbox.", 'oria' ),
		__( 'Subscription ended', 'oria' )                   => __( 'Sent by the retirement routine as it cancels, and it is not worth cancelling a subscription to read an email.', 'oria' ),
	);
}

/* ------------------------------------------------------------ test sends */

function handle_test(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot send test emails.', 'oria' ) );
	}
	$key = isset( $_GET['email'] ) ? sanitize_key( wp_unslash( (string) $_GET['email'] ) ) : '';
	check_admin_referer( 'oria_email_test_' . $key );

	$all = registry();
	$to  = wp_get_current_user()->user_email;
	$ok  = false;

	if ( isset( $all[ $key ] ) ) {
		/*
		 * Same callable as the preview, with the capture off and the
		 * recipient rewritten to whoever pressed the button — a test that
		 * reached a practice would be worse than no test at all.
		 */
		$redirect = static function ( array $atts ) use ( $to ): array {
			$atts['to'] = $to;
			return $atts;
		};
		add_filter( 'wp_mail', $redirect );
		try {
			( $all[ $key ]['run'] )();
			$ok = true;
		} finally {
			remove_filter( 'wp_mail', $redirect );
		}
	}

	wp_safe_redirect(
		add_query_arg(
			array( 'oria_test' => $ok ? 'sent' : 'failed', 'email' => $key ),
			admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=' . SLUG )
		)
	);
	exit;
}

/* ----------------------------------------------------------------- screen */

function render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$all = registry();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current = isset( $_GET['email'] ) ? sanitize_key( wp_unslash( (string) $_GET['email'] ) ) : (string) array_key_first( $all );
	if ( ! isset( $all[ $current ] ) ) {
		$current = (string) array_key_first( $all );
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'Email preview', 'oria' ) . '</h1>';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$state = isset( $_GET['oria_test'] ) ? sanitize_key( wp_unslash( (string) $_GET['oria_test'] ) ) : '';
	if ( $state ) {
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'sent' === $state ? 'success' : 'error',
			esc_html(
				'sent' === $state
					/* translators: %s: email address */
					? sprintf( __( 'Test sent to %s.', 'oria' ), wp_get_current_user()->user_email )
					: __( 'That test could not be sent.', 'oria' )
			)
		);
	}

	$id = sample_listing();
	if ( ! $id ) {
		echo '<p>' . esc_html__( 'There are no listings to build a sample from yet.', 'oria' ) . '</p></div>';
		return;
	}

	printf(
		'<p class="description">%s</p>',
		esc_html(
			sprintf(
				/* translators: %s: listing name */
				__( 'Each preview runs the real sending code and catches the message instead of posting it, so what you see is what would actually be sent. Samples are built from "%s".', 'oria' ),
				get_the_title( $id )
			)
		)
	);

	echo '<h2 class="nav-tab-wrapper" style="margin-bottom:0">';
	foreach ( $all as $key => $email ) {
		printf(
			'<a href="%s" class="nav-tab%s">%s</a>',
			esc_url( add_query_arg( array( 'post_type' => PostTypes\LISTING, 'page' => SLUG, 'email' => $key ), admin_url( 'edit.php' ) ) ),
			$key === $current ? ' nav-tab-active' : '',
			esc_html( $email['label'] )
		);
	}
	echo '</h2>';

	$email  = $all[ $current ];
	$caught = capture( $email['run'] );

	echo '<div style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:16px 20px;">';
	printf( '<p style="margin:0 0 4px"><strong>%s</strong> %s</p>', esc_html__( 'Sent when:', 'oria' ), esc_html( $email['when'] ) );
	printf( '<p style="margin:0 0 4px"><strong>%s</strong> %s</p>', esc_html__( 'Goes to:', 'oria' ), esc_html( $email['who'] ) );
	printf( '<p style="margin:0"><strong>%s</strong> %s</p>', esc_html__( 'Subject:', 'oria' ), esc_html( $caught['subject'] ) );
	if ( ! empty( $email['note'] ) ) {
		printf( '<p class="description" style="margin:8px 0 0">%s</p>', esc_html( $email['note'] ) );
	}

	printf(
		'<p style="margin:14px 0 0"><a class="button button-primary" href="%s">%s</a></p>',
		esc_url(
			wp_nonce_url(
				admin_url( 'admin-post.php?action=oria_email_test&email=' . $current ),
				'oria_email_test_' . $current
			)
		),
		esc_html(
			sprintf(
				/* translators: %s: the current user's email address */
				__( 'Send this one to %s', 'oria' ),
				wp_get_current_user()->user_email
			)
		)
	);
	echo '</div>';

	if ( ! $caught['sent'] ) {
		echo '<div class="notice notice-error" style="margin-top:12px"><p>' . esc_html__( 'That sender produced no email. Either it bailed out early, or something is broken — which is worth knowing.', 'oria' ) . '</p></div></div>';
		return;
	}

	$is_html = false;
	foreach ( $caught['headers'] as $header ) {
		if ( false !== stripos( (string) $header, 'text/html' ) ) {
			$is_html = true;
			break;
		}
	}

	echo '<div style="margin-top:16px;background:#f0f0f1;padding:20px;border:1px solid #c3c4c7">';
	if ( $is_html ) {
		printf(
			'<iframe title="%s" style="width:100%%;max-width:680px;height:900px;border:1px solid #dcdcde;background:#fff;display:block;margin:0 auto" sandbox="" srcdoc="%s"></iframe>',
			esc_attr__( 'Email preview', 'oria' ),
			esc_attr( $caught['body'] )
		);
	} else {
		printf(
			'<pre style="white-space:pre-wrap;background:#fff;padding:20px;border:1px solid #dcdcde;max-width:680px;margin:0 auto;font-family:ui-monospace,Consolas,monospace;font-size:13px;line-height:1.7">%s</pre>',
			esc_html( $caught['body'] )
		);
	}
	echo '</div>';

	not_shown();
	echo '</div>';
}

/** The honest footnote: what this screen does not cover. */
function not_shown(): void {
	echo '<h2 style="margin-top:2em">' . esc_html__( 'Not on this screen', 'oria' ) . '</h2>';
	echo '<p class="description" style="max-width:70ch">' . esc_html__( 'These emails only exist part-way through something that changes the site — creating an account, cancelling a subscription, fetching somebody\'s website. Running them to look at the wording would do the thing as well as show it.', 'oria' ) . '</p>';
	echo '<table class="widefat striped" style="max-width:70ch"><tbody>';
	foreach ( unpreviewable() as $label => $why ) {
		printf(
			'<tr><td style="width:32%%"><strong>%s</strong></td><td>%s</td></tr>',
			esc_html( $label ),
			esc_html( $why )
		);
	}
	echo '</tbody></table>';
}
