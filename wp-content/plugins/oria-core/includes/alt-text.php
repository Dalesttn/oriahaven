<?php
/**
 * Alt text, with the markup taken out of it.
 *
 * A journal image on /explore/ was announcing itself to screen readers as
 * "<!-- wp:paragraph -->". The value is in the media library, not in a
 * template: somebody -- or more likely an import -- put block markup in the
 * alt field, and every template that has ever rendered that attachment has
 * been repeating it faithfully ever since.
 *
 * Cleaning the record fixes that one image. This stops the next one, because
 * the same import will do the same thing again and nobody reads the alt
 * field of an attachment they are not currently editing.
 *
 * WHAT COMES OUT RATHER THAN WHAT GOES IN. Where the stored value is markup,
 * the attribute is emptied rather than patched up. An image with no usable
 * description is decorative as far as assistive technology is concerned, and
 * an empty alt says exactly that; inventing a description from the filename
 * or the post title would put words in the picture's mouth, and a screen
 * reader would then read the article's title twice -- once from the image
 * and once from the heading beside it.
 *
 * Runs on wp_get_attachment_image_attributes, which is the single gate every
 * wp_get_attachment_image() and the_post_thumbnail() call passes through, so
 * no template needs to know about it.
 *
 * @package OriaCore
 */

declare(strict_types=1);

namespace Oria\Core\AltText;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_filter( 'wp_get_attachment_image_attributes', __NAMESPACE__ . '\clean', 20 );
}

/**
 * @param array<string, mixed> $attr
 * @return array<string, mixed>
 */
function clean( $attr ) {
	if ( ! is_array( $attr ) || ! isset( $attr['alt'] ) ) {
		return $attr;
	}

	$attr['alt'] = sanitize( (string) $attr['alt'] );

	return $attr;
}

/**
 * The stored value, or '' when what is stored is not a description.
 *
 * Entities are decoded first: the value arrives escaped, so an HTML comment
 * reaches here as `&lt;!-- wp:paragraph --&gt;` and would survive a naive
 * tag strip untouched. Decode, strip, then look at what is left.
 */
function sanitize( string $alt ): string {
	$out = wp_specialchars_decode( $alt, ENT_QUOTES );

	// HTML comments are not tags and wp_strip_all_tags leaves them alone.
	$out = (string) preg_replace( '/<!--.*?-->/s', '', $out );
	$out = wp_strip_all_tags( $out );

	// Shortcodes and block delimiters that survived as plain text.
	$out = (string) preg_replace( '/\[[^\]]*\]/', '', $out );
	$out = (string) preg_replace( '/\{\{[^}]*\}\}/', '', $out );

	$out = trim( (string) preg_replace( '/\s+/u', ' ', $out ) );

	/*
	 * What is left has to read as a description. A lone "wp:paragraph", a
	 * filename or a stray punctuation mark is not one, and an empty alt is
	 * a better answer than a bad one.
	 */
	if ( '' === $out
		|| preg_match( '/^(wp:|core\/|https?:)/i', $out )
		|| preg_match( '/\.(jpe?g|png|gif|webp|avif|svg)$/i', $out )
		|| ! preg_match( '/\p{L}/u', $out )
	) {
		return '';
	}

	return $out;
}
