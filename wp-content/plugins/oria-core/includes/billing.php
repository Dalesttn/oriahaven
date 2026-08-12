<?php
/**
 * Stripe billing for claimed and featured listings — Payment Links in,
 * webhooks out, no SDK and no card data anywhere near this site.
 *
 * The flow:
 *   1. Two recurring Payment Links live in Stripe (Claimed, Featured).
 *   2. The claim-approval email appends ?client_reference_id=L{id}-{tier}
 *      to each link, so Stripe tells us which listing was paid for.
 *   3. Stripe calls /wp-json/oria/v1/stripe. checkout.session.completed
 *      activates the listing (claim_status = tier, verified_at = today);
 *      a cancelled or unpaid subscription lapses it back to unclaimed —
 *      which switches off every paid surface through the ownership rules.
 *
 * Webhook authenticity is proven with Stripe's signing scheme (HMAC-SHA256
 * over "timestamp.payload"), verified by hand — twenty lines beats a
 * vendored SDK. Secret preferred in wp-config:
 *
 *     define( 'ORIA_STRIPE_WEBHOOK_SECRET', 'whsec_…' );
 */

declare(strict_types=1);

namespace Oria\Core\Billing;

use Oria\Core\PostTypes;
use Oria\Core\Ownership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TIERS = array( 'claimed', 'featured' );

function bootstrap(): void {
	add_action( 'rest_api_init', __NAMESPACE__ . '\routes' );
	add_action( 'admin_notices', __NAMESPACE__ . '\activation_notice' );
	add_action( 'admin_post_oria_billing_portal', __NAMESPACE__ . '\portal_redirect' );
}

/* ---------------------------------------------------------------- config */

function opt( string $name ): string {
	return function_exists( 'get_field' ) ? (string) ( get_field( $name, 'option' ) ?: '' ) : '';
}

function webhook_secret(): string {
	if ( defined( 'ORIA_STRIPE_WEBHOOK_SECRET' ) && is_string( ORIA_STRIPE_WEBHOOK_SECRET ) ) {
		return ORIA_STRIPE_WEBHOOK_SECRET;
	}
	return opt( 'stripe_webhook_secret' );
}

/**
 * Optional API key — only used to cancel a superseded subscription when an
 * owner upgrades Claimed → Featured. Without it, upgrades still work but
 * the old subscription must be cancelled by hand in the Stripe dashboard
 * (the admin is emailed when that's needed).
 *
 *     define( 'ORIA_STRIPE_SECRET_KEY', 'sk_…' );
 */
function secret_key(): string {
	return defined( 'ORIA_STRIPE_SECRET_KEY' ) && is_string( ORIA_STRIPE_SECRET_KEY )
		? ORIA_STRIPE_SECRET_KEY
		: '';
}

function link_for( string $tier ): string {
	return opt( 'featured' === $tier ? 'stripe_link_featured' : 'stripe_link_claimed' );
}

/** Billing is live once both links and the webhook secret exist. */
function configured(): bool {
	return '' !== webhook_secret()
		&& '' !== link_for( 'claimed' )
		&& '' !== link_for( 'featured' );
}

/** The Payment Link for one tier, tagged with the listing it pays for. */
function pay_url( string $tier, int $listing_id, string $email = '' ): string {
	$base = link_for( $tier );
	if ( '' === $base ) {
		return '';
	}
	$args = array( 'client_reference_id' => 'L' . $listing_id . '-' . $tier );
	if ( '' !== $email && is_email( $email ) ) {
		$args['prefilled_email'] = $email;
	}
	return add_query_arg( $args, $base );
}

/* --------------------------------------------------------------- webhook */

function routes(): void {
	register_rest_route(
		'oria/v1',
		'/stripe',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true', // Authenticated by signature, below.
			'callback'            => __NAMESPACE__ . '\webhook',
		)
	);
}

/**
 * Stripe-Signature: t={unix},v1={hmac}. The HMAC is SHA-256 over
 * "{t}.{raw payload}" with the endpoint's signing secret.
 */
function signature_valid( string $payload, string $header, string $secret, int $tolerance = 300 ): bool {
	$t  = '';
	$v1 = array();
	foreach ( explode( ',', $header ) as $part ) {
		$kv = explode( '=', trim( $part ), 2 );
		if ( 2 !== count( $kv ) ) {
			continue;
		}
		if ( 't' === $kv[0] ) {
			$t = $kv[1];
		} elseif ( 'v1' === $kv[0] ) {
			$v1[] = $kv[1];
		}
	}
	if ( '' === $t || ! $v1 || abs( time() - (int) $t ) > $tolerance ) {
		return false;
	}
	$expected = hash_hmac( 'sha256', $t . '.' . $payload, $secret );
	foreach ( $v1 as $candidate ) {
		if ( hash_equals( $expected, $candidate ) ) {
			return true;
		}
	}
	return false;
}

function webhook( \WP_REST_Request $request ): \WP_REST_Response {
	$secret = webhook_secret();
	if ( '' === $secret ) {
		return new \WP_REST_Response( array( 'error' => 'billing not configured' ), 503 );
	}

	$payload = (string) $request->get_body();
	$header  = (string) $request->get_header( 'stripe-signature' );
	if ( ! signature_valid( $payload, $header, $secret ) ) {
		return new \WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
	}

	$event = json_decode( $payload, true );
	if ( ! is_array( $event ) || empty( $event['type'] ) ) {
		return new \WP_REST_Response( array( 'error' => 'malformed event' ), 400 );
	}

	update_option(
		'oria_stripe_last_event',
		array( 'id' => (string) ( $event['id'] ?? '' ), 'type' => (string) $event['type'], 'at' => time() ),
		false
	);

	$object = (array) ( $event['data']['object'] ?? array() );

	switch ( (string) $event['type'] ) {
		case 'checkout.session.completed':
			activate( $object );
			break;

		case 'customer.subscription.deleted':
			lapse( (string) ( $object['id'] ?? '' ) );
			break;

		case 'customer.subscription.updated':
			$status = (string) ( $object['status'] ?? '' );
			// past_due is left alone — Stripe retries the card for days and
			// most recover. Terminal states lapse immediately.
			if ( in_array( $status, array( 'canceled', 'unpaid', 'incomplete_expired' ), true ) ) {
				lapse( (string) ( $object['id'] ?? '' ) );
			}
			break;
	}

	return new \WP_REST_Response( array( 'received' => true ), 200 );
}

/* ------------------------------------------------------------ transitions */

/** checkout.session.completed → the listing goes live on its tier. */
function activate( array $session ): void {
	$ref = (string) ( $session['client_reference_id'] ?? '' );
	if ( ! preg_match( '/^L(\d+)-(claimed|featured)$/', $ref, $m ) ) {
		return;
	}
	$listing_id = (int) $m[1];
	$tier       = $m[2];

	$post = get_post( $listing_id );
	if ( ! $post || PostTypes\LISTING !== $post->post_type ) {
		return;
	}

	// An upgrade replaces the subscription: retire the old one so the owner
	// is never billed twice. With an API key we cancel it directly; without
	// one the admin gets an email to do it in the dashboard.
	$new_sub = sanitize_text_field( (string) ( $session['subscription'] ?? '' ) );
	$old_sub = (string) get_post_meta( $listing_id, '_stripe_subscription_id', true );
	if ( '' !== $old_sub && $old_sub !== $new_sub ) {
		retire_subscription( $old_sub, $listing_id );
	}

	update_post_meta( $listing_id, '_stripe_subscription_id', $new_sub );
	update_post_meta( $listing_id, '_stripe_customer_id', sanitize_text_field( (string) ( $session['customer'] ?? '' ) ) );

	if ( function_exists( 'update_field' ) ) {
		update_field( 'claim_status', $tier, $listing_id );
		update_field( 'verified_at', current_time( 'Y-m-d' ), $listing_id );
	}

	owner_mail(
		$listing_id,
		__( 'Your listing is live — Oria Haven', 'oria' ),
		sprintf(
			/* translators: 1: listing title, 2: tier, 3: edit url */
			__( "Payment received — \"%1\$s\" is now live on the %2\$s plan.\n\nYou can edit every detail of your listing, add photos, offers, opening hours and events, and see how it performs:\n%3\$s", 'oria' ),
			get_post_field( 'post_title', $listing_id, 'raw' ),
			$tier,
			admin_url( 'post.php?post=' . $listing_id . '&action=edit' )
		)
	);
}

/** A dead subscription → back to unclaimed; the paid wall does the rest. */
function lapse( string $subscription_id ): void {
	if ( '' === $subscription_id ) {
		return;
	}
	$posts = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => '_stripe_subscription_id', 'value' => $subscription_id ),
			),
		)
	);
	if ( ! $posts ) {
		return;
	}
	$listing_id = (int) $posts[0];

	if ( function_exists( 'update_field' ) ) {
		update_field( 'claim_status', 'unclaimed', $listing_id );
	}
	delete_post_meta( $listing_id, '_stripe_subscription_id' );

	owner_mail(
		$listing_id,
		__( 'Your listing subscription has ended — Oria Haven', 'oria' ),
		sprintf(
			/* translators: 1: listing title, 2: login url */
			__( "The subscription for \"%1\$s\" has ended, so the listing is now on the free plan. It still shows as claimed by you, everything you added is kept, and you can keep your location, contact details, prices and description up to date any time.\n\nLog in to manage it — or restart a plan from there to bring back photos, offers, your timetable and the rest:\n%2\$s", 'oria' ),
			get_post_field( 'post_title', $listing_id, 'raw' ),
			wp_login_url()
		)
	);
}

/** Plain, branded-when-possible mail to the listing's owner. */
function owner_mail( int $listing_id, string $subject, string $body ): void {
	$user_id = (int) get_post_meta( $listing_id, 'claimed_by', true );
	$user    = $user_id ? get_userdata( $user_id ) : false;
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return;
	}

	/*
	 * Every email a practice gets about its own listing goes through here,
	 * which makes this the one place the website-services line belongs —
	 * appended in whichever form matches the email, rather than pasted
	 * into a dozen message bodies that each guess wrong. It is off unless
	 * switched on in Settings → General.
	 */

	// Ride on the Oria Forms email shell when the plugin is active.
	if ( function_exists( '\Oria\Forms\Emails\shell' ) ) {
		$html = '<p style="margin:0 0 14px;">' . nl2br( esc_html( $body ) ) . '</p>'
			. \Oria\Core\Websites\email_line_html();
		wp_mail( $user->user_email, $subject, \Oria\Forms\Emails\shell( $subject, $html ), array( 'Content-Type: text/html; charset=UTF-8' ) );
		return;
	}
	wp_mail( $user->user_email, $subject, $body . \Oria\Core\Websites\email_line() );
}

/**
 * Cancel a superseded subscription at period end, or flag it for a human.
 */
function retire_subscription( string $sub_id, int $listing_id ): void {
	$key = secret_key();
	if ( '' !== $key ) {
		$r = wp_remote_request(
			'https://api.stripe.com/v1/subscriptions/' . rawurlencode( $sub_id ),
			array(
				'method'  => 'DELETE',
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'timeout' => 15,
			)
		);
		if ( ! is_wp_error( $r ) && wp_remote_retrieve_response_code( $r ) < 300 ) {
			return;
		}
	}

	update_post_meta( $listing_id, '_stripe_stale_subscription_id', $sub_id );
	wp_mail(
		(string) get_option( 'admin_email' ),
		__( '[Oria Haven] Cancel superseded Stripe subscription', 'oria' ),
		sprintf(
			/* translators: 1: listing title, 2: subscription id */
			__( "\"%1\$s\" upgraded plans, leaving an old subscription active in Stripe. Cancel %2\$s in the Stripe dashboard so the owner isn't billed twice. (Define ORIA_STRIPE_SECRET_KEY in wp-config to automate this.)", 'oria' ),
			get_post_field( 'post_title', $listing_id, 'raw' ),
			$sub_id
		)
	);
}

/* --------------------------------------------------------- billing portal */

/** The admin-post URL that opens Stripe's customer portal for the owner. */
function portal_url(): string {
	return wp_nonce_url( admin_url( 'admin-post.php?action=oria_billing_portal' ), 'oria_portal' );
}

/**
 * Send the logged-in owner to Stripe's hosted customer portal, where they
 * can cancel, change card or read invoices. With an API key we mint an
 * authenticated session; otherwise the no-code portal login link from Site
 * settings serves as fallback (Stripe emails them a sign-in link there).
 */
function portal_redirect(): void {
	if ( ! Ownership\is_practitioner() || ! check_admin_referer( 'oria_portal' ) ) {
		wp_die( esc_html__( 'Sorry, this page is for listing owners.', 'oria' ), 403 );
	}

	$listing  = Ownership\owned_listing( get_current_user_id() );
	$customer = $listing ? (string) get_post_meta( $listing, '_stripe_customer_id', true ) : '';
	$key      = secret_key();

	if ( '' !== $key && '' !== $customer ) {
		$r = wp_remote_post(
			'https://api.stripe.com/v1/billing_portal/sessions',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'body'    => array(
					'customer'   => $customer,
					'return_url' => admin_url( 'edit.php?post_type=' . \Oria\Core\PostTypes\LISTING ),
				),
				'timeout' => 15,
			)
		);
		$data = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( ! is_wp_error( $r ) && ! empty( $data['url'] ) ) {
			wp_redirect( esc_url_raw( (string) $data['url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- Stripe-hosted portal.
			exit;
		}
	}

	$fallback = opt( 'stripe_portal_login_url' );
	if ( '' !== $fallback ) {
		wp_redirect( esc_url_raw( $fallback ) ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	wp_die(
		esc_html__( 'Billing management is not configured yet — please contact us and we\'ll sort your subscription by hand.', 'oria' ),
		200
	);
}

/* ----------------------------------------------------- practitioner nudge */

/** Gold-dotted feature bullets for the admin panels. */
function bullet_list( array $features ): string {
	$out = '<ul style="margin:14px 0 0;padding:0;list-style:none;columns:2;column-gap:36px;max-width:760px;">';
	foreach ( $features as $f ) {
		$out .= '<li style="margin:0 0 8px;padding-left:18px;position:relative;break-inside:avoid;font-size:13px;line-height:1.5;">'
			. '<span style="position:absolute;left:0;top:7px;width:7px;height:7px;border-radius:7px;background:#C9A24B;"></span>'
			. esc_html( $f ) . '</li>';
	}
	return $out . '</ul>';
}

/**
 * The right nudge for where the owner is on the ladder, styled to be
 * unmissable: unpaid owners see both plans side by side; Claimed owners
 * see what Featured would add. Featured owners see nothing.
 */
function activation_notice(): void {
	if ( ! configured() || ! Ownership\is_practitioner() ) {
		return;
	}
	$user_id = get_current_user_id();
	$listing = Ownership\owned_listing( $user_id );
	if ( ! $listing ) {
		return;
	}
	$email       = (string) ( wp_get_current_user()->user_email ?? '' );
	$tier        = \Oria\Core\Tiers\tier( $listing );
	$has_billing = '' !== (string) get_post_meta( $listing, '_stripe_customer_id', true )
		|| '' !== opt( 'stripe_portal_login_url' );

	if ( 'featured' === $tier ) {
		// Nothing to sell — just a clean way to manage or cancel.
		if ( $has_billing ) {
			printf(
				'<div class="notice" style="border:none;background:transparent;box-shadow:none;padding:0;margin:20px 20px 16px 2px;"><p style="margin:0;font-size:13px;color:#50575e;">%s <a href="%s" style="display:inline-block;margin-left:10px;background:#0E3B38;color:#FFFFFF;text-decoration:none;font-weight:600;font-size:13px;padding:9px 22px;border-radius:999px;">%s</a></p></div>',
				esc_html__( 'You\'re on the Featured plan.', 'oria' ),
				esc_url( portal_url() ),
				esc_html__( 'Manage billing or cancel', 'oria' )
			);
		}
		return;
	}

	$wrap_open  = '<div class="notice" style="border:none;background:transparent;box-shadow:none;padding:0;margin:20px 20px 24px 2px;">'
		. '<div style="background:linear-gradient(135deg,#0E3B38 0%,#16544E 100%);border-radius:16px;padding:28px 32px;color:#FFFFFF;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">';
	$wrap_close = '</div></div>';
	$gold_btn   = 'display:inline-block;background:#C9A24B;color:#082220;text-decoration:none;font-weight:600;font-size:14px;padding:12px 28px;border-radius:999px;';
	$ghost_btn  = 'display:inline-block;background:transparent;color:#FFFFFF;border:1px solid #3F6E60;text-decoration:none;font-weight:600;font-size:14px;padding:11px 26px;border-radius:999px;';

	if ( 'claimed' === $tier ) {
		$featured = \Oria\Core\Tiers\summary( 'featured' );
		echo $wrap_open // phpcs:ignore WordPress.Security.EscapeOutput -- built above from static strings.
			. '<span style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#C9A24B;font-weight:700;">'
			. esc_html( sprintf( __( 'Featured · %s/month', 'oria' ), $featured['price'] ) ) . '</span>'
			. '<h2 style="margin:8px 0 4px;color:#FFFFFF;font-size:22px;letter-spacing:-0.3px;">' . esc_html__( 'Ready to grow? Go Featured.', 'oria' ) . '</h2>'
			. '<p style="margin:0;color:#A9C2B7;font-size:13px;">' . esc_html__( 'Your listing is claimed and live. Featured puts it in front of more people — and unlocks events.', 'oria' ) . '</p>'
			. bullet_list( $featured['features'] ) // phpcs:ignore WordPress.Security.EscapeOutput
			. '<p style="margin:20px 0 0;"><a href="' . esc_url( pay_url( 'featured', $listing, $email ) ) . '" style="' . $gold_btn . '">' // phpcs:ignore WordPress.Security.EscapeOutput
			. esc_html__( 'Upgrade to Featured', 'oria' ) . ' &rarr;</a>'
			. ( $has_billing
				? ' <a href="' . esc_url( portal_url() ) . '" style="' . $ghost_btn . 'margin-left:10px;">' . esc_html__( 'Manage billing or cancel', 'oria' ) . '</a>'
				: '' )
			. '</p>'
			. $wrap_close; // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}

	$claimed  = \Oria\Core\Tiers\summary( 'claimed' );
	$featured = \Oria\Core\Tiers\summary( 'featured' );
	$card     = 'flex:1;min-width:280px;background:rgba(255,255,255,0.06);border:1px solid rgba(169,194,183,0.25);border-radius:12px;padding:20px 22px;';

	echo $wrap_open // phpcs:ignore WordPress.Security.EscapeOutput
		. '<h2 style="margin:0 0 4px;color:#FFFFFF;font-size:22px;letter-spacing:-0.3px;">' . esc_html__( 'Your claim is approved — choose a plan to unlock everything.', 'oria' ) . '</h2>'
		. '<p style="margin:0 0 18px;color:#A9C2B7;font-size:13px;">' . esc_html__( 'On the free plan you can keep your location and contact details up to date. A plan unlocks the rest the moment payment goes through — and everything you add is kept, even if you cancel.', 'oria' ) . '</p>'
		. '<div style="display:flex;gap:16px;flex-wrap:wrap;">'

		. '<div style="' . $card . '">'
		. '<b style="font-size:15px;">' . esc_html( $claimed['label'] ) . '</b>'
		. '<span style="float:right;color:#C8D9CF;">' . esc_html( $claimed['price'] ) . esc_html__( '/mo', 'oria' ) . '</span>'
		. bullet_list( $claimed['features'] ) // phpcs:ignore WordPress.Security.EscapeOutput
		. '<p style="margin:16px 0 0;"><a href="' . esc_url( pay_url( 'claimed', $listing, $email ) ) . '" style="' . $ghost_btn . '">' // phpcs:ignore WordPress.Security.EscapeOutput
		. esc_html__( 'Activate Claimed', 'oria' ) . '</a></p></div>'

		. '<div style="' . $card . 'border-color:#C9A24B;">'
		. '<b style="font-size:15px;">' . esc_html( $featured['label'] ) . '</b>'
		. '<span style="margin-left:8px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#C9A24B;font-weight:700;">' . esc_html__( 'Best for growth', 'oria' ) . '</span>'
		. '<span style="float:right;color:#C8D9CF;">' . esc_html( $featured['price'] ) . esc_html__( '/mo', 'oria' ) . '</span>'
		. bullet_list( $featured['features'] ) // phpcs:ignore WordPress.Security.EscapeOutput
		. '<p style="margin:16px 0 0;"><a href="' . esc_url( pay_url( 'featured', $listing, $email ) ) . '" style="' . $gold_btn . '">' // phpcs:ignore WordPress.Security.EscapeOutput
		. esc_html__( 'Go Featured', 'oria' ) . ' &rarr;</a></p></div>'

		. '</div>'
		. $wrap_close; // phpcs:ignore WordPress.Security.EscapeOutput
}
