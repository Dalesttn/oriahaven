<?php
/**
 * The "Listed on Oria Haven" website badge.
 *
 * The share kit already hands a practice a written post, an image and four
 * social buttons. Those earn traffic; none of them earns a link, because a
 * social share is not a link a search engine counts. This is the piece that
 * was missing — a small block of HTML a practice pastes into their own site,
 * pointing at their own profile.
 *
 * Every listing carries a website, which makes those sites the most natural
 * source of relevant links this directory will ever have: local, on topic,
 * and editorially honest, because the practice really is listed.
 *
 * Two rules keep it on the right side of Google's link schemes guidance, and
 * both are deliberate:
 *
 *   1. The anchor is the practice's own name, never a keyword. A thousand
 *      sites linking "best massage Perth" is a link scheme; a thousand sites
 *      linking their own name is a directory.
 *   2. The badge is never a condition of being listed, of claiming, or of any
 *      tier. Listing is free and unconditional. This is offered, not traded.
 *
 * Drawn as a PNG rather than written as SVG because it renders on somebody
 * else's website: an SVG asking for Manrope would fall back to whatever font
 * that visitor happens to own, and the badge would stop being ours. Drawn at
 * twice its display size so it stays sharp on a retina screen.
 */

declare(strict_types=1);

namespace Oria\Core\Badge;

use Oria\Core\Share;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Display size in CSS pixels. The file itself is drawn at SCALE times this. */
const W     = 210;
const H     = 62;
const SCALE = 2;

/** @return array<string, string> variant => the background it is made for */
function variants(): array {
	return array(
		'dark'  => __( 'For a light background', 'oria' ),
		'light' => __( 'For a dark background', 'oria' ),
	);
}

/**
 * URL of the badge image, drawing and caching it on first request.
 *
 * Returns '' when GD or the font is missing, so callers can fall back to a
 * plain text link rather than paste a broken image onto a client's website.
 */
function image_url( string $variant = 'dark' ): string {
	if ( ! isset( variants()[ $variant ] ) ) {
		return '';
	}
	$dirs = Share\cache_dir();
	$file = sprintf( 'badge-%s-v1.png', $variant );
	$path = $dirs['path'] . '/' . $file;

	if ( file_exists( $path ) ) {
		return $dirs['url'] . '/' . $file;
	}
	return draw( $variant, $path ) ? $dirs['url'] . '/' . $file : '';
}

/** Draw one badge and save it. */
function draw( string $variant, string $path ): bool {
	if ( ! function_exists( 'imagettftext' ) ) {
		return false;
	}
	$font = ORIA_CORE_DIR . 'assets/fonts/Manrope-Variable.ttf';
	if ( ! file_exists( $font ) ) {
		return false;
	}
	$dirs = Share\cache_dir();
	if ( ! wp_mkdir_p( $dirs['path'] ) ) {
		return false;
	}

	$w    = W * SCALE;
	$h    = H * SCALE;
	$dark = 'dark' === $variant;

	$im = imagecreatetruecolor( $w, $h );
	imagealphablending( $im, false );
	imagesavealpha( $im, true );
	imagefilledrectangle( $im, 0, 0, $w, $h, imagecolorallocatealpha( $im, 0, 0, 0, 127 ) );
	imagealphablending( $im, true );

	$petrol = imagecolorallocate( $im, 14, 59, 56 );
	$white  = imagecolorallocate( $im, 255, 255, 255 );
	$gold   = imagecolorallocate( $im, 201, 162, 75 );

	$radius = 12 * SCALE;
	if ( $dark ) {
		rounded_rect( $im, $w, $h, $radius, $petrol );
	} else {
		// On a dark site a filled tile reads as a sticker, so the light
		// variant is an outline holding transparent space instead.
		rounded_outline( $im, $w, $h, $radius, imagecolorallocatealpha( $im, 255, 255, 255, 88 ) );
	}

	// The ensō, left, sized to the two lines of type beside it.
	$pad       = 14 * SCALE;
	$mark_size = 34 * SCALE;
	$mark_file = ORIA_CORE_DIR . 'assets/img/mark-white.png';
	$mark      = file_exists( $mark_file ) ? @imagecreatefrompng( $mark_file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors

	if ( $mark ) {
		$tmp = imagecreatetruecolor( $mark_size, $mark_size );
		imagealphablending( $tmp, false );
		imagesavealpha( $tmp, true );
		imagefilledrectangle( $tmp, 0, 0, $mark_size, $mark_size, imagecolorallocatealpha( $tmp, 0, 0, 0, 127 ) );
		imagecopyresampled( $tmp, $mark, 0, 0, 0, 0, $mark_size, $mark_size, imagesx( $mark ), imagesy( $mark ) );
		imagedestroy( $mark );

		imagealphablending( $im, true );
		imagecopy( $im, $tmp, $pad, (int) round( ( $h - $mark_size ) / 2 ), 0, 0, $mark_size, $mark_size );
		imagedestroy( $tmp );
	}

	$text_x   = $pad + $mark_size + (int) round( 11 * SCALE );
	$eyebrow  = $dark ? $gold : imagecolorallocate( $im, 214, 178, 96 );
	$wordmark = $white;

	// "LISTED ON", letter-spaced by hand: GD has no tracking control.
	$spaced = implode( ' ', str_split( 'LISTED ON' ) );
	foreach ( array( 0.0, 0.5 ) as $offset ) {
		imagettftext( $im, 7.5 * SCALE, 0, (int) round( $text_x + $offset ), (int) round( 26 * SCALE ), $eyebrow, $font, $spaced );
	}

	// Manrope is a variable font and GD renders its lightest instance, which
	// disappears at this size. Overdraw at sub-pixel offsets for weight.
	foreach ( array( array( 0, 0 ), array( 0.6, 0 ), array( 0, 0.6 ), array( 0.6, 0.6 ) ) as $o ) {
		imagettftext(
			$im,
			15.5 * SCALE,
			0,
			(int) round( $text_x + $o[0] ),
			(int) round( 47 * SCALE + $o[1] ),
			$wordmark,
			$font,
			'Oria Haven'
		);
	}

	$ok = imagepng( $im, $path, 6 );
	imagedestroy( $im );
	return (bool) $ok;
}

/** GD has no rounded rectangle: one cross, four corner discs. */
function rounded_rect( $im, int $w, int $h, int $r, int $colour ): void {
	imagefilledrectangle( $im, $r, 0, $w - $r - 1, $h - 1, $colour );
	imagefilledrectangle( $im, 0, $r, $w - 1, $h - $r - 1, $colour );
	$d = $r * 2;
	imagefilledellipse( $im, $r, $r, $d, $d, $colour );
	imagefilledellipse( $im, $w - $r - 1, $r, $d, $d, $colour );
	imagefilledellipse( $im, $r, $h - $r - 1, $d, $d, $colour );
	imagefilledellipse( $im, $w - $r - 1, $h - $r - 1, $d, $d, $colour );
}

/** The same shape as a hairline: four sides and four arcs. */
function rounded_outline( $im, int $w, int $h, int $r, int $colour ): void {
	imagesetthickness( $im, SCALE );
	imageline( $im, $r, 1, $w - $r, 1, $colour );
	imageline( $im, $r, $h - 2, $w - $r, $h - 2, $colour );
	imageline( $im, 1, $r, 1, $h - $r, $colour );
	imageline( $im, $w - 2, $r, $w - 2, $h - $r, $colour );
	$d = $r * 2;
	imagearc( $im, $r, $r, $d, $d, 180, 270, $colour );
	imagearc( $im, $w - $r - 1, $r, $d, $d, 270, 360, $colour );
	imagearc( $im, $r, $h - $r - 1, $d, $d, 90, 180, $colour );
	imagearc( $im, $w - $r - 1, $h - $r - 1, $d, $d, 0, 90, $colour );
	imagesetthickness( $im, 1 );
}

/**
 * The HTML a practice pastes into their own site.
 *
 * Deliberately plain: an anchor and an image, no classes, no script, and no
 * inline style beyond the two that stop a theme stretching the picture. It
 * has to survive Wix, Squarespace, a WordPress block and whatever else is
 * out there, and the simplest possible markup is what does that.
 */
function snippet( int $listing_id, string $variant = 'dark' ): string {
	$url  = (string) get_permalink( $listing_id );
	$img  = image_url( $variant );
	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );

	if ( '' === $img ) {
		// No image is no reason to withhold the link, which is the part
		// that actually matters.
		return sprintf( '<a href="%s">%s on Oria Haven</a>', esc_url( $url ), esc_html( $name ) );
	}

	$alt = sprintf(
		/* translators: %s: the practice's name */
		__( '%s is listed on Oria Haven, the Perth wellness directory', 'oria' ),
		$name
	);

	return sprintf(
		"<a href=\"%1\$s\">\n  <img src=\"%2\$s\" alt=\"%3\$s\"\n       width=\"%4\$d\" height=\"%5\$d\" style=\"max-width:100%%;height:auto;border:0\" loading=\"lazy\">\n</a>",
		esc_url( $url ),
		esc_url( $img ),
		esc_attr( $alt ),
		W,
		H
	);
}
