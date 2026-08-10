<?php
/**
 * Who the site's email comes from.
 *
 * WordPress defaults to wordpress@yourdomain — an address that usually
 * doesn't exist, so replies bounce and spam filters treat it as a hint
 * that nobody is home. Every message the directory sends (signup
 * welcomes, claim approvals, billing notices, form replies) goes through
 * wp_mail(), so setting the sender once here covers all of them rather
 * than repeating a From header at a dozen call sites.
 *
 * A dedicated SMTP plugin, if one is configured, applies its own sender
 * later in the request and wins — which is correct: the address the mail
 * is authenticated as should be the address it claims to be from.
 */

declare(strict_types=1);

namespace Oria\Core\Mail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION_FROM = 'oria_mail_from';
const OPTION_NAME = 'oria_mail_from_name';

function bootstrap(): void {
	add_action( 'admin_init', __NAMESPACE__ . '\settings' );
	add_filter( 'wp_mail_from', __NAMESPACE__ . '\from_address' );
	add_filter( 'wp_mail_from_name', __NAMESPACE__ . '\from_name' );
}

/**
 * The site's own domain, without www — where sending should originate.
 *
 * A bare hostname with no dot ("localhost") makes an address PHPMailer
 * rejects outright, and because the rejection happens on the From header
 * it kills the whole send: every signup and approval email on a local
 * install silently returned false. RFC 2606 reserves .test for exactly
 * this, so a dotless host gets one rather than poisoning the sender.
 */
function domain(): string {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./', '', $host ) ?: '';
	if ( '' === $host ) {
		return 'example.com';
	}
	return str_contains( $host, '.' ) ? $host : $host . '.test';
}

function from_address( string $default = '' ): string {
	$set = sanitize_email( (string) get_option( OPTION_FROM, '' ) );
	if ( '' !== $set && is_email( $set ) ) {
		return $set;
	}
	// Better than wordpress@ even before anything is configured.
	$fallback = 'hello@' . domain();

	// Never hand back something PHPMailer will refuse; a bad sender fails
	// the message rather than just mislabelling it.
	return is_email( $fallback ) ? $fallback : $default;
}

function from_name( string $default = '' ): string {
	$set = trim( (string) get_option( OPTION_NAME, '' ) );
	return '' !== $set
		? $set
		: wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
}

function settings(): void {
	// Shares the section registered alongside the analytics setting;
	// registering it again with the same id is harmless and keeps the two
	// modules independent of each other's load order.
	add_settings_section(
		'oria_settings',
		__( 'Oria Haven', 'oria' ),
		static function (): void {
			echo '<p>' . esc_html__( 'Settings for the directory itself.', 'oria' ) . '</p>';
		},
		'general'
	);

	register_setting( 'general', OPTION_FROM, array( 'type' => 'string', 'sanitize_callback' => __NAMESPACE__ . '\sanitize_from', 'default' => '' ) );
	register_setting( 'general', OPTION_NAME, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );

	add_settings_field(
		OPTION_FROM,
		__( 'Send email from', 'oria' ),
		static function (): void {
			printf(
				'<input name="%1$s" id="%1$s" type="email" class="regular-text" value="%2$s" placeholder="%3$s">
				<p class="description">%4$s</p>',
				esc_attr( OPTION_FROM ),
				esc_attr( (string) get_option( OPTION_FROM, '' ) ),
				esc_attr( 'hello@' . domain() ),
				esc_html(
					sprintf(
						/* translators: %s: the site's domain */
						__( 'A real mailbox you monitor — practitioners reply to these. Use an address on %s, not a Gmail or Outlook one: mail claiming to be from a domain it was not sent from goes to spam. Empty uses hello@%1$s.', 'oria' ),
						domain()
					)
				)
			);
		},
		'general',
		'oria_settings',
		array( 'label_for' => OPTION_FROM )
	);

	add_settings_field(
		OPTION_NAME,
		__( 'Sender name', 'oria' ),
		static function (): void {
			printf(
				'<input name="%1$s" id="%1$s" type="text" class="regular-text" value="%2$s" placeholder="%3$s">
				<p class="description">%4$s</p>',
				esc_attr( OPTION_NAME ),
				esc_attr( (string) get_option( OPTION_NAME, '' ) ),
				esc_attr( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) ),
				esc_html__( 'The name recipients see. Empty uses the site title.', 'oria' )
			);
		},
		'general',
		'oria_settings',
		array( 'label_for' => OPTION_NAME )
	);
}

/** Keep a valid address, or fall back rather than storing nonsense. */
function sanitize_from( $value ): string {
	$value = sanitize_email( trim( (string) $value ) );
	if ( '' === $value ) {
		return '';
	}
	if ( ! is_email( $value ) ) {
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error( OPTION_FROM, 'oria_mail_invalid', __( "That isn't a valid email address — sender left unchanged.", 'oria' ) );
		}
		return (string) get_option( OPTION_FROM, '' );
	}
	// Warn, but allow: some sites legitimately send via a subdomain.
	$host = strtolower( (string) substr( strrchr( $value, '@' ) ?: '', 1 ) );
	if ( function_exists( 'add_settings_error' ) && ! str_ends_with( $host, domain() ) ) {
		add_settings_error(
			OPTION_FROM,
			'oria_mail_offdomain',
			sprintf(
				/* translators: 1: chosen domain, 2: site domain */
				__( 'Saved, but %1$s is not %2$s. Mail sent from another provider\'s domain usually fails SPF and lands in spam — use an address on your own domain unless you know the sending setup handles it.', 'oria' ),
				$host,
				domain()
			),
			'warning'
		);
	}
	return $value;
}
