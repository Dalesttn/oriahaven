<?php
/**
 * Google Analytics (GA4) via gtag.js.
 *
 * The tag ID lives under Settings → General ("Google tag ID"); the snippet
 * prints at the top of <head> only when an ID is saved, and never for
 * logged-in users — the admin's and practitioners' own browsing would
 * otherwise pollute the numbers.
 */

declare(strict_types=1);

namespace Oria\Core\Ga;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION = 'oria_ga_tag_id';

function bootstrap(): void {
	add_action( 'admin_init', __NAMESPACE__ . '\settings' );
	add_action( 'wp_head', __NAMESPACE__ . '\snippet', 1 );
	// Tag Manager's second half: the no-JavaScript fallback iframe, which
	// Google requires immediately after <body>.
	add_action( 'wp_body_open', __NAMESPACE__ . '\noscript', 1 );
}

/** Tag Manager containers need a different snippet from a GA4 tag. */
function is_gtm( string $id ): bool {
	return str_starts_with( $id, 'GTM-' );
}

function settings(): void {
	register_setting(
		'general',
		OPTION,
		array(
			'type'              => 'string',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize',
			'default'           => '',
		)
	);
	// Its own titled section, rendered by do_settings_sections() below the
	// core table. Sitting in the 'default' section instead makes it the
	// unlabelled last row under "Week Starts On", where it goes unnoticed.
	add_settings_section(
		'oria_settings',
		__( 'Oria Haven', 'oria' ),
		static function (): void {
			echo '<p>' . esc_html__( 'Settings for the directory itself.', 'oria' ) . '</p>';
		},
		'general'
	);

	add_settings_field(
		OPTION,
		__( 'Google tag ID', 'oria' ),
		static function (): void {
			printf(
				'<input name="%1$s" id="%1$s" type="text" class="regular-text code" value="%2$s" placeholder="G-XXXXXXXXXX">
				<p class="description">%3$s</p>',
				esc_attr( OPTION ),
				esc_attr( (string) get_option( OPTION, '' ) ),
				esc_html__( 'Paste either a Google Analytics measurement ID (G-XXXXXXXXXX, from Analytics → Admin → Data streams) or a Tag Manager container ID (GTM-XXXXXXX, shown at the top of your GTM workspace). Whichever you use, the right code is added to every page for you — nothing to paste into the theme. Leave empty to switch tracking off. Logged-in users are never tracked.', 'oria' )
			);
		},
		'general',
		'oria_settings',
		array( 'label_for' => OPTION )
	);
}

/** Accept a plausible Google tag ID or nothing at all. */
function sanitize( $value ): string {
	$value = strtoupper( trim( (string) $value ) );
	if ( '' === $value ) {
		return '';
	}
	// Longest prefix first so GTM- isn't mistaken for G-.
	if ( ! preg_match( '/^(?:GTM|GT|AW|G)-[A-Z0-9]{4,20}$/', $value ) ) {
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error(
				OPTION,
				'oria_ga_invalid',
				__( 'That doesn\'t look like a Google tag ID (expected something like G-ABC12DE3FG) — not saved.', 'oria' )
			);
		}
		return (string) get_option( OPTION, '' );
	}
	return $value;
}

function snippet(): void {
	$id = (string) get_option( OPTION, '' );
	if ( '' === $id || is_user_logged_in() ) {
		return;
	}

	if ( is_gtm( $id ) ) {
		// Google's Tag Manager container snippet, verbatim apart from the ID.
		printf(
			"<!-- Google Tag Manager -->\n" .
			"<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':" .
			"new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0]," .
			"j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=" .
			"'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);" .
			"})(window,document,'script','dataLayer','%s');</script>\n" .
			"<!-- End Google Tag Manager -->\n",
			esc_js( $id )
		);
		return;
	}

	// Google's standard gtag.js install, verbatim apart from the ID.
	printf(
		'<script async src="https://www.googletagmanager.com/gtag/js?id=%1$s"></script>' . "\n" .
		'<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","%1$s");</script>' . "\n",
		esc_attr( $id )
	);
}

/** Tag Manager's no-JavaScript iframe, printed right after <body>. */
function noscript(): void {
	$id = (string) get_option( OPTION, '' );
	if ( '' === $id || is_user_logged_in() || ! is_gtm( $id ) ) {
		return;
	}
	printf(
		"<!-- Google Tag Manager (noscript) -->\n" .
		'<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=%s"' .
		' height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n" .
		"<!-- End Google Tag Manager (noscript) -->\n",
		esc_attr( $id )
	);
}
