<?php
/**
 * Inviting a practice to take over its own listing.
 *
 * Almost every listing here was built from public information and has never
 * been seen by the business it describes. That is the directory's biggest
 * weakness — a wrong price or a closed practice is worse than no listing —
 * and the fix is to hand each one to its owner. This is the email that does
 * the handing over, sent one at a time from the listings screen.
 *
 * Three things it is careful about.
 *
 * The claim it offers is genuinely free. An approved owner on the free plan
 * can already correct their address, contact details, prices and format —
 * see FIELD_TIERS in tiers.php — and their listing stops reading Unclaimed.
 * Photos, hours, offers and analytics stay paid. The email says exactly
 * that, because a free claim that turns out to cost $29 at the last step
 * would be worth less than sending nothing.
 *
 * It is one click. The address we write to is the one the business
 * published, so arriving back with a signed single-use token is reasonable
 * evidence of control — enough to hand over a free listing, which is worth
 * nothing to steal. Anything paid still goes through billing.
 *
 * It stops when asked. Every send is logged, an opt-out is honoured
 * permanently and immediately, and nothing sends twice by accident.
 *
 * Compliance note. This is unsolicited commercial email to businesses and
 * the Spam Act 2003 (Cth) applies: it is sent only to an address the
 * business itself published, it concerns their own listing, it identifies
 * us, and it carries a working opt-out. The address each listing holds was
 * collected during import — worth confirming that provenance before a
 * large run.
 */

declare(strict_types=1);

namespace Oria\Core\Invites;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PATH = 'claim';

/** Post meta. */
const SENT    = '_oria_invite_sent';    // Y-m-d H:i:s of the last send.
const COUNT   = '_oria_invite_count';   // How many have gone out.
const TOKEN   = '_oria_invite_token';   // Hash of the live token.
const EXPIRES = '_oria_invite_expires'; // Unix time the token dies.
const OPTOUT  = '_oria_invite_optout';  // Y-m-d H:i:s they asked us to stop.
const CLAIMED = '_oria_invite_claimed'; // Y-m-d H:i:s they accepted.

/** How long an emailed link stays good. */
const TTL_DAYS = 30;

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\handle_link' );

	add_filter( 'manage_listing_posts_columns', __NAMESPACE__ . '\column' );
	add_action( 'manage_listing_posts_custom_column', __NAMESPACE__ . '\column_content', 20, 2 );
	add_action( 'admin_post_oria_invite', __NAMESPACE__ . '\handle_send' );
	add_action( 'phpmailer_init', __NAMESPACE__ . '\attach_alt_text' );
	add_action( 'admin_notices', __NAMESPACE__ . '\notice' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\metabox' );
}

/* ------------------------------------------------------------------ route */

function route(): void {
	add_rewrite_rule( '^' . PATH . '/([A-Za-z0-9]+)/?$', 'index.php?oria_invite_token=$matches[1]', 'top' );
}

function query_vars( array $vars ): array {
	$vars[] = 'oria_invite_token';
	return $vars;
}

/* ------------------------------------------------------------- eligibility */

/** The address we'd write to, if there is one. */
function address( int $listing_id ): string {
	$email = trim( (string) get_post_meta( $listing_id, 'email', true ) );
	return is_email( $email ) ? $email : '';
}

/**
 * Why this listing can't be invited, or '' if it can.
 *
 * Returned as a reason rather than a boolean so the admin column can say
 * what's stopping it instead of just hiding the button.
 */
function blocked( int $listing_id ): string {
	if ( get_post_meta( $listing_id, OPTOUT, true ) ) {
		return __( 'Asked us to stop', 'oria' );
	}
	if ( (int) get_post_meta( $listing_id, 'claimed_by', true ) ) {
		return __( 'Already has an owner', 'oria' );
	}
	if ( 'publish' !== get_post_status( $listing_id ) ) {
		return __( 'Not published', 'oria' );
	}
	if ( ! address( $listing_id ) ) {
		return __( 'No email address', 'oria' );
	}
	return '';
}

/* ----------------------------------------------------------------- tokens */

/**
 * Mint a fresh link for this listing and remember its hash.
 *
 * Only the hash is stored, so a leaked database row can't be turned back
 * into a working link, and minting a new one silently kills the old.
 */
function mint( int $listing_id ): string {
	$token = wp_generate_password( 32, false, false );
	update_post_meta( $listing_id, TOKEN, wp_hash( $token ) );
	update_post_meta( $listing_id, EXPIRES, time() + ( TTL_DAYS * DAY_IN_SECONDS ) );
	return $token;
}

function link( string $token, bool $decline = false ): string {
	$url = home_url( '/' . PATH . '/' . rawurlencode( $token ) . '/' );
	return $decline ? add_query_arg( 'no', '1', $url ) : $url;
}

/** The listing a token belongs to, or 0 if it's unknown or stale. */
function listing_for( string $token ): int {
	if ( ! $token ) {
		return 0;
	}
	$found = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => TOKEN,
					'value' => wp_hash( $token ),
				),
			),
		)
	);
	$id = (int) ( $found[0] ?? 0 );
	if ( ! $id ) {
		return 0;
	}
	return (int) get_post_meta( $id, EXPIRES, true ) > time() ? $id : 0;
}

function burn( int $listing_id ): void {
	delete_post_meta( $listing_id, TOKEN );
	delete_post_meta( $listing_id, EXPIRES );
}

/* ------------------------------------------------------------------- send */

/**
 * Write to one practice. Returns true if the mail was handed off.
 */
function send( int $listing_id ): bool {
	if ( blocked( $listing_id ) ) {
		return false;
	}

	$to    = address( $listing_id );
	$again = (int) get_post_meta( $listing_id, COUNT, true ) > 0;
	$token = mint( $listing_id );
	$name  = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );

	// Both parts, every time. A plain-text alternative is worth real money
	// on cold mail: some filters weigh HTML-only messages against you, and
	// some people simply read in plain text.
	alt_text( $again ? follow_up_text( $listing_id, $token ) : body_text( $listing_id, $token ) );

	$sent = wp_mail(
		$to,
		subject( $listing_id, $again ),
		\Oria\Forms\Emails\shell(
			/* translators: %s: practice name */
			sprintf( __( '%s is listed on Oria Haven — it\'s yours to take over, free.', 'oria' ), $name ),
			$again ? follow_up_html( $listing_id, $token ) : body_html( $listing_id, $token ),
			'masthead'
		),
		\Oria\Forms\Emails\html_headers()
	);

	alt_text( '' );

	if ( $sent ) {
		update_post_meta( $listing_id, SENT, current_time( 'mysql' ) );
		update_post_meta( $listing_id, COUNT, (int) get_post_meta( $listing_id, COUNT, true ) + 1 );
	}
	return (bool) $sent;
}

function subject( int $listing_id, bool $again ): string {
	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );
	/* translators: %s: practice name */
	$first = sprintf( __( 'We\'ve listed %s on Oria Haven', 'oria' ), $name );
	return $again ? 'Re: ' . $first : $first;
}

/**
 * What we've tagged them as, in words — the detail that shows a person
 * looked at their listing, and the quickest way to earn a correction.
 */
function described( int $listing_id ): string {
	$parts = array();

	$practice = wp_get_post_terms( $listing_id, 'practice' );
	if ( ! is_wp_error( $practice ) && $practice ) {
		$parts[] = tname( $practice[0] );
	}

	$suburb = '';
	$areas  = wp_get_post_terms( $listing_id, 'area' );
	foreach ( is_wp_error( $areas ) ? array() : $areas as $area ) {
		if ( $area->parent ) {
			$suburb = tname( $area );
			break;
		}
	}

	$line = $parts ? sprintf(
		/* translators: 1: practice category, 2: suburb */
		__( 'you\'re under %1$s%2$s', 'oria' ),
		$parts[0],
		$suburb ? sprintf( __( ' in %s', 'oria' ), $suburb ) : ''
	) : '';

	$specs = wp_get_post_terms( $listing_id, 'specialty' );
	$specs = is_wp_error( $specs ) ? array() : array_slice( $specs, 0, 3 );
	if ( $specs && $line ) {
		$line .= sprintf(
			/* translators: %s: list of services */
			__( ', with %s', 'oria' ),
			strtolower( wp_sprintf_l( '%l', array_map( __NAMESPACE__ . '\tname', $specs ) ) )
		);
	}

	return $line;
}

function tname( \WP_Term $term ): string {
	return function_exists( 'Oria\Theme\tname' ) ? \Oria\Theme\tname( $term ) : $term->name;
}

/**
 * Defined once, in oria-forms, and shared with every other email we send —
 * so a phone number can never be current in one message and stale in
 * another. This wrapper stays because the invite bodies read better with
 * the sign-off inline than appended.
 */
function signature(): string {
	if ( function_exists( '\Oria\Forms\Emails\signature_text' ) ) {
		return trim( \Oria\Forms\Emails\signature_text(), "\n" );
	}
	return (string) get_option( 'oria_invite_from_name', 'Dale' );
}

/**
 * Holds the plain-text twin of the message being sent, so phpmailer_init
 * can attach it as the alternative part. Cleared straight after the send
 * rather than left lying around for the next unrelated email.
 */
function alt_text( ?string $set = null ): string {
	static $text = '';
	if ( null !== $set ) {
		$text = $set;
	}
	return $text;
}

function attach_alt_text( \PHPMailer\PHPMailer\PHPMailer $mailer ): void {
	$text = alt_text();
	if ( '' !== $text ) {
		$mailer->AltBody = $text;
	}
}

/* --------------------------------------------------------------- the html */

function para( string $html ): string {
	return '<p style="margin:0 0 16px;">' . $html . '</p>';
}

function heading( string $text ): string {
	return '<p style="margin:24px 0 6px;font-size:13px;font-weight:bold;letter-spacing:1.2px;text-transform:uppercase;color:#0E3B38;">' . esc_html( $text ) . '</p>';
}

function button( string $url, string $label ): string {
	return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;"><tr>'
		. '<td style="background:#0E3B38;border-radius:999px;">'
		. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:14px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#FFFFFF;text-decoration:none;">'
		. esc_html( $label ) . '</a></td></tr></table>';
}

function small( string $html ): string {
	return '<p style="margin:0 0 14px;font-size:13px;color:#566762;line-height:1.6;">' . $html . '</p>';
}

function link_to( string $url, string $label = '' ): string {
	return '<a href="' . esc_url( $url ) . '" style="color:#0E3B38;">' . esc_html( $label ?: $url ) . '</a>';
}

function body_html( int $listing_id, string $token ): string {
	$name     = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );
	$describe = described( $listing_id );
	$profile  = (string) get_permalink( $listing_id );

	$html  = para( esc_html__( 'Hi there,', 'oria' ) );
	$html .= para(
		sprintf(
			/* translators: 1: practice name, 2: link to their listing */
			esc_html__( 'We run Oria Haven, a directory of wellness practices in Perth. %1$s is on it: %2$s', 'oria' ),
			'<b>' . esc_html( $name ) . '</b>',
			link_to( $profile )
		)
	);
	$html .= para(
		sprintf(
			/* translators: %s: how we've categorised them */
			esc_html__( 'We put the listing together from what\'s public on your website%s. Nobody from your team has checked it, which is why I\'m writing.', 'oria' ),
			$describe ? ' — ' . esc_html( $describe ) : ''
		)
	);

	$html .= heading( __( 'If anything\'s wrong, just reply and I\'ll fix it', 'oria' ) );
	$html .= para( esc_html__( 'No account, no charge. An out-of-date price or a wrong opening time is worse for you than not being listed at all.', 'oria' ) );

	$html .= heading( __( 'If you\'d like to look after it yourself, you can — free', 'oria' ) );
	$html .= para( esc_html__( 'Claiming confirms you\'re the owner. You can then keep your address, phone, email, website, prices and session format current yourself, and the listing stops being marked Unclaimed. There are paid plans that add photos, opening hours, offers and visitor stats, but you never have to take one.', 'oria' ) );

	$html .= button( link( $token ), __( 'Claim your listing', 'oria' ) );

	$html .= small(
		sprintf(
			/* translators: %d: number of days */
			esc_html__( 'That link is just for your listing and works for %d days.', 'oria' ),
			TTL_DAYS
		)
	);

	$html .= '<div style="border-top:1px solid #EFEDE6;margin:22px 0 18px;"></div>';
	$html .= small(
		sprintf(
			/* translators: %d: number of listings */
			esc_html__( 'About us: we list %d practices across Perth, from Fremantle to the Hills, all checked by hand. Enquiries go straight to you. We don\'t take a cut of bookings and we never will.', 'oria' ),
			(int) wp_count_posts( PostTypes\LISTING )->publish
		)
	);
	$html .= signature_html();
	$html .= small(
		sprintf(
			/* translators: %s: opt-out link */
			esc_html__( 'Would you rather not be listed? %s and we\'ll take it down.', 'oria' ),
			link_to( link( $token, true ), __( 'Tell us here', 'oria' ) )
		)
	);

	return $html;
}

function follow_up_html( int $listing_id, string $token ): string {
	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );

	$html  = para( esc_html__( 'Hi again — just once more in case that got buried.', 'oria' ) );
	$html .= para(
		sprintf(
			/* translators: 1: practice name, 2: link */
			esc_html__( '%1$s\'s listing: %2$s', 'oria' ),
			'<b>' . esc_html( $name ) . '</b>',
			link_to( (string) get_permalink( $listing_id ) )
		)
	);
	$html .= button( link( $token ), __( 'Take it over — free', 'oria' ) );
	$html .= small(
		sprintf(
			/* translators: %s: opt-out link */
			esc_html__( 'Rather not be listed? %s.', 'oria' ),
			link_to( link( $token, true ), __( 'Tell us here', 'oria' ) )
		)
	);
	$html .= para( '<b>' . esc_html__( 'Either way, I won\'t email again.', 'oria' ) . '</b>' );
	$html .= signature_html();

	return $html;
}

/**
 * The sign-off inside an invite's body. Same details as the shell footer
 * below it, because an invitation is a letter from a person and should
 * read like one — the footer is the letterhead, this is the signature.
 */
function signature_html(): string {
	if ( ! function_exists( '\Oria\Forms\Emails\signature_html' ) ) {
		return '';
	}
	return '<p style="margin:22px 0 18px;font-size:13px;line-height:1.7;color:#566762;">'
		. \Oria\Forms\Emails\signature_html()
		. '</p>';
}

/* --------------------------------------------------------------- the text */

function body_text( int $listing_id, string $token ): string {
	$name     = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );
	$describe = described( $listing_id );

	return sprintf(
		"Hi there,\n\n" .
		"We run Oria Haven, a directory of wellness practices in Perth. %1\$s is on it:\n\n%2\$s\n\n" .
		"We put the listing together from what's public on your website%3\$s. Nobody from your team has checked it, which is why I'm writing.\n\n" .
		"IF ANYTHING'S WRONG, JUST REPLY AND I'LL FIX IT.\nNo account, no charge. An out-of-date price or a wrong opening time is worse for you than not being listed at all.\n\n" .
		"IF YOU'D LIKE TO LOOK AFTER IT YOURSELF, YOU CAN — FREE.\nClaiming confirms you're the owner. You can then keep your address, phone, email, website, prices and session format current yourself, and the listing stops being marked Unclaimed. There are paid plans that add photos, opening hours, offers and visitor stats, but you never have to take one.\n\n" .
		"Claim it here:\n%4\$s\n\n" .
		"That link is just for your listing and works for %5\$d days.\n\n" .
		"About us: we list %6\$d practices across Perth, from Fremantle to the Hills, all checked by hand. Enquiries go straight to you. We don't take a cut of bookings and we never will.\n\n" .
		"%7\$s\n\n" .
		"---\nWould you rather not be listed? Tell us here and we'll take it down:\n%8\$s\n",
		$name,
		get_permalink( $listing_id ),
		$describe ? ' — ' . $describe : '',
		link( $token ),
		TTL_DAYS,
		(int) wp_count_posts( PostTypes\LISTING )->publish,
		signature(),
		link( $token, true )
	);
}

/**
 * The second and last email. Saying we won't write again is the part that
 * earns any trust here, so it has to be true — see blocked(), which stops
 * a third.
 */
function follow_up_text( int $listing_id, string $token ): string {
	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );

	return sprintf(
		"Hi again — just once more in case that got buried.\n\n" .
		"%1\$s's listing: %2\$s\n" .
		"Take it over (free): %3\$s\n" .
		"Rather not be listed: %4\$s\n\n" .
		"Either way, I won't email again.\n\n%5\$s\n",
		$name,
		get_permalink( $listing_id ),
		link( $token ),
		link( $token, true ),
		signature()
	);
}

/* -------------------------------------------------------------- admin send */

function handle_send(): void {
	$listing_id = isset( $_GET['listing'] ) ? (int) $_GET['listing'] : 0;

	if ( ! $listing_id || ! current_user_can( 'edit_post', $listing_id ) ) {
		wp_die( esc_html__( 'You cannot invite this listing.', 'oria' ) );
	}
	check_admin_referer( 'oria_invite_' . $listing_id );

	$back = wp_get_referer() ?: admin_url( 'edit.php?post_type=' . PostTypes\LISTING );
	$ok   = send( $listing_id );

	wp_safe_redirect( add_query_arg( 'oria_invited', $ok ? '1' : '0', remove_query_arg( 'oria_invited', $back ) ) );
	exit;
}

function notice(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$state = isset( $_GET['oria_invited'] ) ? (string) $_GET['oria_invited'] : '';
	if ( '' === $state ) {
		return;
	}
	printf(
		'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
		'1' === $state ? 'success' : 'error',
		esc_html(
			'1' === $state
				? __( 'Invitation sent.', 'oria' )
				: __( 'That invitation could not be sent — check the listing has an email address and has not opted out.', 'oria' )
		)
	);
}

/* ------------------------------------------------------------ admin column */

function column( array $columns ): array {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'oria_verified' === $key ) {
			$out['oria_invite'] = __( 'Invite', 'oria' );
		}
	}
	// If the claims column ever moves, still show it rather than swallow it.
	if ( ! isset( $out['oria_invite'] ) ) {
		$out['oria_invite'] = __( 'Invite', 'oria' );
	}
	return $out;
}

function column_content( string $column, int $post_id ): void {
	if ( 'oria_invite' !== $column ) {
		return;
	}
	echo wp_kses_post( status_html( $post_id ) );
}

/**
 * One cell, and the whole workflow: what's happened, and the one button
 * that does the next thing.
 */
function status_html( int $post_id ): string {
	$claimed = (string) get_post_meta( $post_id, CLAIMED, true );
	if ( $claimed ) {
		return '<span style="color:#1a7f5a">' . esc_html(
			sprintf(
				/* translators: %s: date */
				__( 'Claimed %s', 'oria' ),
				date_i18n( 'j M', (int) strtotime( $claimed ) )
			)
		) . '</span>';
	}

	$why = blocked( $post_id );
	$sent  = (string) get_post_meta( $post_id, SENT, true );
	$count = (int) get_post_meta( $post_id, COUNT, true );

	$history = '';
	if ( $sent ) {
		$history = '<div style="color:#646970;font-size:12px">' . esc_html(
			sprintf(
				/* translators: 1: number of emails, 2: date */
				_n( 'Sent %2$s', 'Sent %1$d×, last %2$s', $count, 'oria' ),
				$count,
				date_i18n( 'j M', (int) strtotime( $sent ) )
			)
		) . '</div>';
	}

	if ( $why ) {
		return $history . '<span style="color:#646970">' . esc_html( $why ) . '</span>';
	}

	// Two is the limit: the follow-up promises we won't write a third time.
	if ( $count >= 2 ) {
		return $history . '<span style="color:#646970">' . esc_html__( 'Done — no more', 'oria' ) . '</span>';
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=oria_invite&listing=' . $post_id ),
		'oria_invite_' . $post_id
	);

	return $history . sprintf(
		'<a class="button button-small" href="%s">%s</a>',
		esc_url( $url ),
		esc_html( $count ? __( 'Send follow-up', 'oria' ) : __( 'Send invite', 'oria' ) )
	);
}

/* ----------------------------------------------------------- edit-screen box */

function metabox(): void {
	add_meta_box(
		'oria-invite',
		__( 'Invite to claim', 'oria' ),
		__NAMESPACE__ . '\render_metabox',
		PostTypes\LISTING,
		'side',
		'default'
	);
}

function render_metabox( \WP_Post $post ): void {
	$email = address( $post->ID );
	echo '<p style="margin-top:0">';
	echo $email
		? esc_html( sprintf( __( 'Would write to %s', 'oria' ), $email ) )
		: esc_html__( 'No email address on this listing.', 'oria' );
	echo '</p>';
	echo wp_kses_post( status_html( $post->ID ) );
}

/* ------------------------------------------------------ the link they click */

/**
 * Someone has clicked a link in one of these emails: either to take the
 * listing over, or to ask us to take it down.
 */
function handle_link(): void {
	$token = (string) get_query_var( 'oria_invite_token' );
	if ( ! $token ) {
		return;
	}

	$listing_id = listing_for( $token );
	if ( ! $listing_id ) {
		wp_die(
			esc_html__( 'That link has expired or has already been used. Reply to the email we sent and we\'ll send a fresh one.', 'oria' ),
			esc_html__( 'Link expired', 'oria' ),
			array( 'response' => 410 )
		);
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['no'] ) ) {
		decline( $listing_id );
		return;
	}

	accept( $listing_id );
}

/**
 * Take the listing down? Not automatically. A single click in an email is
 * enough to say "stop", and it is honoured instantly; it is not enough to
 * delete something on its own, so a person removes it.
 */
function decline( int $listing_id ): void {
	update_post_meta( $listing_id, OPTOUT, current_time( 'mysql' ) );
	burn( $listing_id );

	wp_mail(
		get_option( 'admin_email' ),
		sprintf(
			/* translators: %s: practice name */
			__( 'Removal requested: %s', 'oria' ),
			get_the_title( $listing_id )
		),
		sprintf(
			"%s has asked to be taken off Oria Haven.\n\n%s\n\nEdit: %s\n\nThey will not be emailed again either way.",
			get_the_title( $listing_id ),
			get_permalink( $listing_id ),
			get_edit_post_link( $listing_id, 'raw' )
		)
	);

	wp_die(
		esc_html__( 'Thanks — we\'ve stopped. Your listing will be taken down within a day, and we won\'t email you again.', 'oria' ),
		esc_html__( 'Removal requested', 'oria' ),
		array( 'response' => 200 )
	);
}

/**
 * Hand the listing over.
 *
 * The address we wrote to was published by the business, and this token
 * went only to that address and works once, which is proof enough for a
 * free listing. Everything with a price on it still goes through billing.
 */
function accept( int $listing_id ): void {
	$email = address( $listing_id );
	if ( ! $email ) {
		wp_die( esc_html__( 'This listing no longer has a contact address.', 'oria' ) );
	}

	burn( $listing_id );

	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		// Same shape of username as the signup form makes.
		$username = sanitize_user( (string) strstr( $email, '@', true ), true );
		if ( '' === $username || username_exists( $username ) ) {
			$username = sanitize_user( $username . wp_rand( 100, 999 ), true );
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24 ),
				'display_name' => get_the_title( $listing_id ),
				'role'         => \Oria\Core\Ownership\ROLE,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			wp_die( esc_html__( 'We could not set up your account. Please reply to our email and we\'ll sort it out.', 'oria' ) );
		}
		$user = get_user_by( 'id', $user_id );
	}

	if ( ! $user instanceof \WP_User ) {
		wp_die( esc_html__( 'We could not set up your account. Please reply to our email and we\'ll sort it out.', 'oria' ) );
	}

	// Free plan: an owner, but claim_status stays unclaimed so no paid
	// surface switches itself on. tiers.php decides what that allows.
	if ( function_exists( 'update_field' ) ) {
		update_field( 'claimed_by', $user->ID, $listing_id );
	} else {
		update_post_meta( $listing_id, 'claimed_by', $user->ID );
	}
	update_post_meta( $listing_id, CLAIMED, current_time( 'mysql' ) );

	$key   = get_password_reset_key( $user );
	$reset = is_wp_error( $key ) ? wp_login_url() : network_site_url(
		'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ),
		'login'
	);

	wp_mail(
		$email,
		__( 'Your listing is yours — Oria Haven', 'oria' ),
		sprintf(
			"Done — %1\$s is now yours.\n\nSet a password here and you can edit it whenever you like:\n%2\$s\n\nYour listing:\n%3\$s\n\nOn the free plan you can keep your address, contact details, prices and format up to date. Photos, opening hours, offers and visitor stats are on the paid plans, and there's no hurry.\n\n%4\$s\n",
			get_the_title( $listing_id ),
			$reset,
			get_permalink( $listing_id ),
			signature()
		) . \Oria\Core\Websites\email_line()
	);

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, false );

	wp_safe_redirect( add_query_arg( 'oria_claimed', '1', get_permalink( $listing_id ) ) );
	exit;
}
