<?php
/**
 * Email: one branded HTML shell, two senders. The shell is table-based
 * with fully inline styles — the only markup that renders predictably
 * across Gmail, Outlook and Apple Mail. Palette mirrors the theme:
 * deep #0E3B38, gold #C9A24B, paper #F5F4F0, ink #082220.
 */

declare(strict_types=1);

namespace Oria\Forms\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shared shell around any email body.
 *
 * $header chooses the masthead. 'band' is the compact green strip the
 * notifications use. 'masthead' is the centred lockup from the site —
 * mark, wordmark, then the line saying what we are — for the few emails
 * that reach someone who has never heard of us and needs telling.
 */
function shell( string $preheader, string $body_html, string $header = 'band' ): string {
	$site = esc_html( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
	$home = esc_url( home_url( '/' ) );

	// Header band: the ensō mark (a white PNG served from the theme — SVG
	// dies in Gmail) beside the wordmark, which doubles as the alt text
	// when a mail client blocks images.
	$top = '<tr><td style="background:#0E3B38;border-radius:14px 14px 0 0;padding:18px 32px;">'
		. '<a href="' . $home . '" style="text-decoration:none;">'
		. '<img src="' . esc_url( get_theme_file_uri( 'assets/img/email-logo.png' ) ) . '" width="40" height="40" alt="' . esc_attr( $site ) . '" style="display:inline-block;vertical-align:middle;border:0;width:40px;height:40px;">'
		. '<span style="display:inline-block;vertical-align:middle;margin-left:12px;font-family:Arial,Helvetica,sans-serif;font-size:19px;font-weight:bold;color:#FFFFFF;letter-spacing:-0.2px;">' . $site . '</span>'
		. '</a></td></tr>';

	$card_top = 'none';
	$rounding = '0 0 14px 14px';

	if ( 'masthead' === $header ) {
		/*
		 * The mark carries no alt text on purpose: the wordmark beneath it
		 * is live text, so a client with images switched off still shows
		 * the name rather than showing it twice.
		 */
		/*
		 * The WordPress site title is "OriaHaven", one word, because that is
		 * what reads well in a browser tab beside a page name. Set as a
		 * masthead in 30px it wants the space the brand actually has.
		 */
		$wordmark = esc_html( (string) apply_filters( 'oria_email_wordmark', 'Oria Haven' ) );

		$card_top = '1px solid #DCD9D0';
		$rounding = '14px';
		$top      = '<tr><td align="center" style="padding:4px 16px 30px;">'
			. '<a href="' . $home . '" style="text-decoration:none;">'
			. '<img src="' . esc_url( get_theme_file_uri( 'assets/img/email-mark.png' ) ) . '" width="72" height="72" alt="" style="display:block;margin:0 auto 20px;border:0;width:72px;height:72px;">'
			. '<div style="font-family:Arial,Helvetica,sans-serif;font-size:30px;font-weight:bold;color:#082220;letter-spacing:-0.5px;">' . $wordmark . '</div>'
			. '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;color:#566762;letter-spacing:2.5px;text-transform:uppercase;margin-top:10px;">'
			. esc_html__( 'Perth meditation &amp; wellness directory', 'oria' )
			. '</div></a></td></tr>';
	}

	return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
	. '<body style="margin:0;padding:0;background:#F5F4F0;">'
	. '<div style="display:none;max-height:0;overflow:hidden;">' . esc_html( $preheader ) . '</div>'
	. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F4F0;">'
	. '<tr><td align="center" style="padding:32px 16px;">'
	. '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">'

	. $top

	// Body card.
	. '<tr><td style="background:#FFFFFF;border:1px solid #DCD9D0;border-top:' . $card_top . ';border-radius:' . $rounding . ';padding:28px 32px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#082220;">'
	. $body_html
	. '</td></tr>'

	// Footer. Signed by a person, with a number that reaches them — a
	// directory email that answers "who is this and how do I reply to a
	// human" in its last three lines is worth more than one that doesn't.
	. '<tr><td style="padding:18px 8px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.7;color:#8A948F;" align="center">'
	. signature_html()
	. '</td></tr>'

	. '</table></td></tr></table></body></html>';
}

/**
 * Who sent this, in both flavours.
 *
 * One definition so the plain-text and HTML mail cannot end up signed
 * differently, and so the day the number changes it changes once. The
 * business details come from Schema\NAP, which is also what the footer of
 * the website and the Organization schema read — three places that have to
 * agree, reading one source.
 *
 * @return array{name: string, phone: string, tel: string, email: string, abn: string, place: string}
 */
function signer(): array {
	$nap = class_exists( '\Oria\Core\Schema\Bootstrap' ) || defined( 'ORIA_CORE_DIR' )
		? \Oria\Core\Schema\NAP
		: array();

	return array(
		'name'  => (string) get_option( 'oria_invite_from_name', 'Dale' ),
		'phone' => (string) ( $nap['phone'] ?? '' ),
		'tel'   => (string) ( $nap['phone_e164'] ?? '' ),
		'email' => (string) ( $nap['email'] ?? get_option( 'admin_email' ) ),
		'abn'   => (string) ( $nap['abn'] ?? '' ),
		'place' => trim( (string) ( $nap['locality'] ?? '' ) . ', ' . (string) ( $nap['region'] ?? '' ), ', ' ),
	);
}

/** The sign-off for an HTML email's footer. */
function signature_html(): string {
	$s    = signer();
	// The spaced brand, as the masthead uses — the site title is one word.
	$site = esc_html( (string) apply_filters( 'oria_email_wordmark', 'Oria Haven' ) );
	$out  = '<strong style="color:#566762;">' . esc_html( $s['name'] ) . '</strong><br>'
		. $site . ' &middot; ' . esc_html__( 'Perth meditation &amp; wellness directory', 'oria' ) . '<br>';

	$bits = array();
	if ( $s['phone'] ) {
		$bits[] = '<a href="tel:' . esc_attr( $s['tel'] ) . '" style="color:#3F6E60;">' . esc_html( $s['phone'] ) . '</a>';
	}
	if ( $s['email'] ) {
		$bits[] = '<a href="mailto:' . esc_attr( $s['email'] ) . '" style="color:#3F6E60;">' . esc_html( $s['email'] ) . '</a>';
	}
	$bits[] = '<a href="' . esc_url( home_url( '/' ) ) . '" style="color:#3F6E60;">' . esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</a>';
	$out   .= implode( ' &middot; ', $bits );

	$tail = array_filter( array( $s['place'], $s['abn'] ? 'ABN ' . $s['abn'] : '' ) );
	if ( $tail ) {
		$out .= '<br>' . esc_html( implode( ' · ', $tail ) );
	}
	return $out;
}

/** The same sign-off for a plain-text email. */
function signature_text(): string {
	$s    = signer();
	$site = (string) apply_filters( 'oria_email_wordmark', 'Oria Haven' );

	$lines = array( '', '—', $s['name'], $site . ' — Perth meditation & wellness directory' );
	$contact = array_filter( array( $s['phone'], $s['email'] ) );
	if ( $contact ) {
		$lines[] = implode( ' · ', $contact );
	}
	$lines[] = untrailingslashit( home_url( '/' ) );
	$tail = array_filter( array( $s['place'], $s['abn'] ? 'ABN ' . $s['abn'] : '' ) );
	if ( $tail ) {
		$lines[] = implode( ' · ', $tail );
	}
	return "\n" . implode( "\n", $lines ) . "\n";
}

/** The submitted values as a tidy two-column table. */
function values_table( array $form, array $values ): string {
	$rows = '';
	foreach ( $values as $name => $value ) {
		if ( '' === trim( (string) $value ) ) {
			continue;
		}
		$label = (string) ( $form['fields'][ $name ]['label'] ?? $name );
		$rows .= '<tr>'
			. '<td style="padding:9px 14px 9px 0;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#566762;white-space:nowrap;vertical-align:top;">' . esc_html( $label ) . '</td>'
			. '<td style="padding:9px 0;color:#082220;">' . nl2br( esc_html( (string) $value ) ) . '</td>'
			. '</tr>';
	}
	return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-top:1px solid #EFEDE6;margin-top:6px;">' . $rows . '</table>';
}

/** Subject templates may reference any field as %name%. */
function subject( string $template, array $values ): string {
	foreach ( $values as $name => $value ) {
		$template = str_replace( '%' . $name . '%', (string) $value, $template );
	}
	return trim( (string) preg_replace( '/%[a-z_]+%/', '', $template ) );
}

/** @return string[] */
function html_headers( string $reply_to = '' ): array {
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( '' !== $reply_to && is_email( $reply_to ) ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}
	return $headers;
}

/** The notification to the site owner. */
function notify( string $form_id, array $form, array $values ): void {
	$label = (string) $form['label'];
	$body  = '<h1 style="margin:0 0 4px;font-size:20px;letter-spacing:-0.3px;">' . esc_html( $label ) . '</h1>'
		. '<p style="margin:0 0 18px;color:#566762;font-size:13px;">'
		. esc_html( sprintf( __( 'New submission · %s', 'oria' ), wp_date( 'j F Y, g.ia' ) ) )
		. '</p>'
		. values_table( $form, $values )
		. '<p style="margin:22px 0 0;"><a href="' . esc_url( admin_url( 'edit.php?post_type=oria_form_entry' ) ) . '" style="display:inline-block;background:#0E3B38;color:#FFFFFF;text-decoration:none;padding:10px 22px;border-radius:999px;font-size:14px;">' . esc_html__( 'View all entries', 'oria' ) . '</a></p>';

	wp_mail(
		(string) get_option( 'admin_email' ),
		subject( (string) ( $form['notify_subject'] ?? '[%s] ' . $label ), $values ),
		shell( sprintf( __( 'New %s submission', 'oria' ), strtolower( $label ) ), $body ),
		html_headers( (string) ( $values['email'] ?? '' ) )
	);
}

/** The branded receipt back to the person who submitted. */
function auto_reply( string $form_id, array $form, array $values ): void {
	$to = (string) ( $values['email'] ?? '' );
	if ( '' === $to || ! is_email( $to ) || empty( $form['reply_subject'] ) ) {
		return;
	}

	$first = trim( (string) strtok( (string) ( $values['name'] ?? '' ), ' ' ) );
	$body  = '<h1 style="margin:0 0 14px;font-size:20px;letter-spacing:-0.3px;">'
		. esc_html( $first ? sprintf( __( 'Thanks, %s.', 'oria' ), $first ) : __( 'Thanks.', 'oria' ) )
		. '</h1>'
		. '<p style="margin:0 0 18px;">' . esc_html( (string) ( $form['reply_intro'] ?? '' ) ) . '</p>'
		. values_table( $form, $values )
		. '<p style="margin:22px 0 0;color:#566762;font-size:13px;">' . esc_html__( 'If anything above is wrong, just reply to this email.', 'oria' ) . '</p>';

	wp_mail(
		$to,
		(string) $form['reply_subject'],
		shell( (string) ( $form['reply_intro'] ?? '' ), $body ),
		html_headers( (string) get_option( 'admin_email' ) )
	);
}
