<?php
/**
 * Global-namespace compatibility shims.
 *
 * Deliberately NOT namespaced: get_field() must be defined globally so it
 * only ever acts as a fallback when ACF is inactive. The templates degrade
 * to plain post meta instead of a fatal error. Repeater fields degrade to
 * empty arrays — post meta stores a row count there, which is useless to
 * render.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'get_field' ) ) {
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
		if ( in_array( $key, array( 'services', 'timetable' ), true ) ) {
			return is_array( $value ) ? $value : array();
		}

		return $value;
	}
}
