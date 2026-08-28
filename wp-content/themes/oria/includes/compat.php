<?php
/**
 * Global-namespace compatibility shims.
 *
 * Deliberately NOT namespaced: get_field() must be defined globally so it
 * only ever acts as a fallback when ACF is inactive. The templates degrade
 * to plain post meta instead of a fatal error. Repeater fields degrade to
 * empty arrays — post meta stores a row count there, which is useless to
 * render.
 *
 * A bare function_exists() guard is NOT enough. On a normal request
 * plugins load before themes, so ACF wins and the shim is skipped — but
 * while ACF is being ACTIVATED the order inverts: the theme is already
 * loaded, our shim is already declared, and including ACF's
 * api-template.php then fatals with "Cannot redeclare get_field()".
 * So the shim also refuses to declare itself whenever ACF is present on
 * disk, and a late hook covers the case where ACF is installed but
 * switched off.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Is ACF loaded, or sitting on disk ready to be activated? */
function oria_acf_available(): bool {
	if ( function_exists( 'get_field' ) || class_exists( 'ACF', false ) ) {
		return true;
	}
	foreach ( array( 'advanced-custom-fields-pro/acf.php', 'advanced-custom-fields/acf.php' ) as $plugin ) {
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			return true;
		}
	}
	return false;
}

/** Declare the stand-in, once, and only if nothing else has. */
function oria_define_get_field_fallback(): void {
	if ( function_exists( 'get_field' ) ) {
		return;
	}

	/**
	 * Minimal stand-in for ACF's get_field().
	 *
	 * @param string    $key     Field name.
	 * @param int|false $post_id Post ID, or false for the current post.
	 */
	function get_field( string $key, $post_id = false ) {
		$value = get_post_meta( (int) ( $post_id ?: get_the_ID() ), $key, true );

		// ACF repeaters store a row count in the parent key; without ACF we
		// cannot reassemble the rows, so return none rather than a number.
		if ( in_array( $key, array( 'services', 'classes', 'packages', 'faq' ), true ) ) {
			return is_array( $value ) ? $value : array();
		}

		return $value;
	}
}

// No ACF anywhere: the templates need the fallback from the very start.
if ( ! oria_acf_available() ) {
	oria_define_get_field_fallback();
}

// ACF on disk but switched off: the front end still has to render, so
// declare the fallback as late as possible. template_redirect is the
// right hook precisely because plugin activation never reaches it —
// activation is an admin request that redirects long before this fires.
// (wp_loaded would be too early: it runs during the activation request
// itself, which recreates the very collision this file exists to avoid.)
add_action( 'template_redirect', 'oria_define_get_field_fallback', 0 );
