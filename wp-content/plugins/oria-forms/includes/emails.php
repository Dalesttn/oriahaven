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

/** The shared shell around any email body. */
function shell( string $preheader, string $body_html ): string {
	$site = esc_html( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
	$home = esc_url( home_url( '/' ) );

	return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
	. '<body style="margin:0;padding:0;background:#F5F4F0;">'
	. '<div style="display:none;max-height:0;overflow:hidden;">' . esc_html( $preheader ) . '</div>'
	. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F4F0;">'
	. '<tr><td align="center" style="padding:32px 16px;">'
	. '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">'

	// Header band: the ensō mark (a white PNG served from the theme — SVG
	// dies in Gmail) beside the wordmark, which doubles as the alt text
	// when a mail client blocks images.
	. '<tr><td style="background:#0E3B38;border-radius:14px 14px 0 0;padding:18px 32px;">'
	. '<a href="' . $home . '" style="text-decoration:none;">'
	. '<img src="' . esc_url( get_theme_file_uri( 'assets/img/email-logo.png' ) ) . '" width="40" height="40" alt="' . esc_attr( $site ) . '" style="display:inline-block;vertical-align:middle;border:0;width:40px;height:40px;">'
	. '<span style="display:inline-block;vertical-align:middle;margin-left:12px;font-family:Arial,Helvetica,sans-serif;font-size:19px;font-weight:bold;color:#FFFFFF;letter-spacing:-0.2px;">' . $site . '</span>'
	. '</a></td></tr>'

	// Body card.
	. '<tr><td style="background:#FFFFFF;border:1px solid #DCD9D0;border-top:none;border-radius:0 0 14px 14px;padding:28px 32px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#082220;">'
	. $body_html
	. '</td></tr>'

	// Footer.
	. '<tr><td style="padding:18px 8px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8A948F;" align="center">'
	. $site . ' &middot; ' . esc_html__( 'Perth meditation & wellness directory', 'oria' )
	. '<br><a href="' . $home . '" style="color:#3F6E60;">' . esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</a>'
	. '</td></tr>'

	. '</table></td></tr></table></body></html>';
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
