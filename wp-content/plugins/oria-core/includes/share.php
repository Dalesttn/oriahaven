<?php
/**
 * Share kit: giving a practice something worth posting.
 *
 * Two halves.
 *
 * 1. A generated social card for every listing. Until now og:image was
 *    only set when a listing had a gallery photo, which almost none of
 *    the scraped ones do — so a practitioner who shared their profile
 *    got a bare grey link box in Facebook and never shared again. Every
 *    listing now has a branded card with its own name on it, whether it
 *    has a photo or not.
 *
 * 2. A public share page at /listing/{slug}/share/ carrying the card,
 *    pre-written copy, one-tap share links and downloadable badges for
 *    Instagram. Public on purpose: a badge for a listing is not secret,
 *    and a login wall between an email and a share button is where the
 *    whole idea dies.
 *
 * Cards are generated once and cached in uploads, keyed by a hash of
 * the text they contain, so renaming a practice quietly produces a new
 * card and the old one falls out of use.
 */

declare(strict_types=1);

namespace Oria\Core\Share;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_share';
const DIR       = 'oria-cards';

/** width, height, and how the layout should read at that shape. */
const FORMATS = array(
	'card'   => array( 1200, 630 ),   // link previews / og:image
	'square' => array( 1080, 1080 ),  // Instagram and Facebook posts
	'story'  => array( 1080, 1920 ),  // Instagram and Facebook stories
);

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\maybe_render' );

	// Give every listing a preview image, photo or not. Yoast's og:image
	// presenter returns early on an empty image list, so wpseo_opengraph_image
	// never fires for a listing — the image has to go in a step earlier, on
	// the presentation object itself. Twitter's presenter does filter its
	// empty string, so that one can stay a plain filter.
	add_filter( 'wpseo_frontend_presentation', __NAMESPACE__ . '\presentation_image', 20 );
	add_filter( 'wpseo_twitter_image', __NAMESPACE__ . '\og_image', 20 );
	add_action( 'wp_head', __NAMESPACE__ . '\head_image', 5 );

	// A renamed listing should not keep serving a card with the old name.
	add_action( 'save_post_' . PostTypes\LISTING, __NAMESPACE__ . '\clear_cards' );
}

/* ------------------------------------------------------------------ route */

function route(): void {
	add_rewrite_rule(
		'^listing/([^/]+)/share/?$',
		'index.php?' . PostTypes\LISTING . '=$matches[1]&' . QUERY_VAR . '=1',
		'top'
	);
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

function is_share_page(): bool {
	return (bool) get_query_var( QUERY_VAR ) && is_singular( PostTypes\LISTING );
}

/** The share page renders from the theme, and never enters the index. */
function maybe_render(): void {
	if ( ! is_share_page() ) {
		return;
	}
	add_filter( 'wpseo_robots', static fn(): string => 'noindex, follow' );
	add_filter( 'wp_robots', static function ( array $r ): array {
		$r['noindex'] = true;
		return $r;
	} );

	$template = locate_template( array( 'oria-share.php' ) );
	if ( $template ) {
		include $template;
		exit;
	}
}

/** The share page for a listing. */
function url( int $listing_id ): string {
	return trailingslashit( (string) get_permalink( $listing_id ) ) . 'share/';
}

/* ------------------------------------------------------------------ cards */

/**
 * The text a card carries. Kept in one place so the cache key and the
 * drawing can never disagree about what's on the image.
 *
 * @return array{name: string, meta: string}
 */
function card_text( int $listing_id ): array {
	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );

	$suburb = '';
	foreach ( wp_get_post_terms( $listing_id, 'area' ) as $t ) {
		if ( $t->parent ) {
			$suburb = wp_specialchars_decode( $t->name, ENT_QUOTES );
			break;
		}
	}
	$practice = '';
	$terms    = wp_get_post_terms( $listing_id, 'practice' );
	if ( ! is_wp_error( $terms ) && $terms ) {
		$practice = wp_specialchars_decode( $terms[0]->name, ENT_QUOTES );
	}

	$meta = trim( implode( '  ·  ', array_filter( array( $suburb, $practice ) ) ) );
	return array( 'name' => $name, 'meta' => $meta ?: 'Perth, Western Australia' );
}

function cache_dir(): array {
	$up = wp_upload_dir();
	return array(
		'path' => trailingslashit( $up['basedir'] ) . DIR,
		'url'  => trailingslashit( $up['baseurl'] ) . DIR,
	);
}

/** Cache key changes with the text, so a rename produces a new file. */
function card_slug( int $listing_id, string $format ): string {
	$t = card_text( $listing_id );
	return sprintf( '%d-%s-%s', $listing_id, $format, substr( md5( $t['name'] . '|' . $t['meta'] . '|v2' ), 0, 8 ) );
}

/**
 * URL for a listing's card, generating it on first request. Returns ''
 * when the image cannot be made (no GD, unwritable uploads) so callers
 * can fall back rather than serve a broken image.
 */
function card_url( int $listing_id, string $format = 'card' ): string {
	if ( ! isset( FORMATS[ $format ] ) ) {
		return '';
	}
	$dirs = cache_dir();
	$file = card_slug( $listing_id, $format ) . '.png';
	$path = $dirs['path'] . '/' . $file;

	if ( file_exists( $path ) ) {
		return $dirs['url'] . '/' . $file;
	}
	return generate( $listing_id, $format, $path ) ? $dirs['url'] . '/' . $file : '';
}

/** Drop a listing's cached cards when it is saved. */
function clear_cards( int $listing_id ): void {
	$dirs = cache_dir();
	foreach ( (array) glob( $dirs['path'] . '/' . $listing_id . '-*.png' ) as $f ) {
		@unlink( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}

/* ------------------------------------------------------------- generation */

function font(): string {
	return ORIA_CORE_DIR . 'assets/fonts/Manrope-Variable.ttf';
}

/**
 * Manrope ships as a variable font and GD renders its default instance,
 * which is the very light end of the range — elegant on a web page,
 * illegible in a Facebook thumbnail. Drawing the same string a few times
 * at sub-pixel offsets thickens the strokes without needing a second
 * font file.
 */
function text( $im, float $size, int $x, int $y, $colour, string $string, float $weight = 0.0 ): array {
	$box = imagettftext( $im, $size, 0, $x, $y, $colour, font(), $string );
	if ( $weight > 0 ) {
		foreach ( array( array( $weight, 0 ), array( 0, $weight ), array( $weight, $weight ) ) as $o ) {
			imagettftext( $im, $size, 0, (int) round( $x + $o[0] ), (int) round( $y + $o[1] ), $colour, font(), $string );
		}
	}
	return is_array( $box ) ? $box : array();
}

function text_width( float $size, string $string ): int {
	$b = imagettfbbox( $size, 0, font(), $string );
	return is_array( $b ) ? (int) abs( $b[2] - $b[0] ) : 0;
}

/** Break a string to fit a pixel width, at most $max_lines lines. */
function wrap( string $string, float $size, int $max_width, int $max_lines ): array {
	$words = preg_split( '/\s+/', $string ) ?: array();
	$lines = array();
	$line  = '';
	foreach ( $words as $w ) {
		$try = '' === $line ? $w : $line . ' ' . $w;
		if ( text_width( $size, $try ) > $max_width && '' !== $line ) {
			$lines[] = $line;
			$line    = $w;
			if ( count( $lines ) === $max_lines ) {
				break;
			}
		} else {
			$line = $try;
		}
	}
	if ( count( $lines ) < $max_lines && '' !== $line ) {
		$lines[] = $line;
	}
	// Anything that didn't fit gets an ellipsis on the last line.
	$used = implode( ' ', $lines );
	if ( mb_strlen( $used ) < mb_strlen( $string ) ) {
		$last = count( $lines ) - 1;
		while ( $last >= 0 && text_width( $size, $lines[ $last ] . '…' ) > $max_width ) {
			$lines[ $last ] = mb_substr( $lines[ $last ], 0, max( 1, mb_strlen( $lines[ $last ] ) - 2 ) );
		}
		$lines[ $last ] .= '…';
	}
	return $lines;
}

/**
 * Composite the ensō faintly into the corner.
 *
 * imagecopymerge, the usual way to blend at a percentage, discards the
 * alpha channel — which drew the mark's transparent square as a visible
 * lighter rectangle. So the opacity is applied to the source's own alpha
 * per pixel and the result copied with normal alpha blending, leaving
 * nothing but the brushstroke itself.
 */
function watermark( $im, int $w, int $h, string $format ): void {
	$mark = ORIA_CORE_DIR . 'assets/img/mark-white.png';
	if ( ! file_exists( $mark ) ) {
		return;
	}
	$src = @imagecreatefrompng( $mark ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $src ) {
		return;
	}

	$size = (int) round( min( $w, $h ) * ( 'story' === $format ? 0.72 : 0.9 ) );
	$tmp  = imagecreatetruecolor( $size, $size );
	imagealphablending( $tmp, false );
	imagesavealpha( $tmp, true );
	imagefilledrectangle( $tmp, 0, 0, $size, $size, imagecolorallocatealpha( $tmp, 0, 0, 0, 127 ) );
	imagecopyresampled( $tmp, $src, 0, 0, 0, 0, $size, $size, imagesx( $src ), imagesy( $src ) );
	imagedestroy( $src );

	// 0 is opaque and 127 fully clear, so a low opacity means a high alpha.
	$opacity = 0.11;
	for ( $y = 0; $y < $size; $y++ ) {
		for ( $x = 0; $x < $size; $x++ ) {
			$rgba = imagecolorat( $tmp, $x, $y );
			$a    = ( $rgba >> 24 ) & 0x7F;
			if ( 127 === $a ) {
				continue;
			}
			$new = (int) min( 127, round( 127 - ( 127 - $a ) * $opacity ) );
			imagesetpixel( $tmp, $x, $y, ( $new << 24 ) | ( $rgba & 0x00FFFFFF ) );
		}
	}

	imagealphablending( $im, true );
	imagecopy( $im, $tmp, (int) round( $w - $size * 0.55 ), (int) round( $h - $size * 0.72 ), 0, 0, $size, $size );
	imagedestroy( $tmp );
}

/** Draw and save one card. */
function generate( int $listing_id, string $format, string $path ): bool {
	if ( ! function_exists( 'imagettftext' ) || ! file_exists( font() ) ) {
		return false;
	}
	$dirs = cache_dir();
	if ( ! wp_mkdir_p( $dirs['path'] ) ) {
		return false;
	}

	list( $w, $h ) = FORMATS[ $format ];
	$t             = card_text( $listing_id );

	$im = imagecreatetruecolor( $w, $h );
	imagealphablending( $im, true );

	// Background: deep green, lifted slightly toward the top-left so the
	// card has some depth rather than reading as a flat rectangle.
	for ( $y = 0; $y < $h; $y++ ) {
		$k = $y / max( 1, $h - 1 );
		$c = imagecolorallocate(
			$im,
			(int) round( 22 - 10 * $k ),
			(int) round( 74 - 26 * $k ),
			(int) round( 70 - 24 * $k )
		);
		imagefilledrectangle( $im, 0, $y, $w, $y, $c );
	}

	$white = imagecolorallocate( $im, 255, 255, 255 );
	$mist  = imagecolorallocate( $im, 169, 194, 183 );
	$gold  = imagecolorallocate( $im, 201, 162, 75 );

	// The mark, oversized and barely there, bled off the right edge.
	watermark( $im, $w, $h, $format );

	$pad     = (int) round( $w * ( 'card' === $format ? 0.065 : 0.09 ) );
	$col     = $w - $pad * 2 - ( 'card' === $format ? (int) round( $w * 0.12 ) : 0 );
	$name_pt = 'story' === $format ? 62.0 : ( 'square' === $format ? 58.0 : 54.0 );
	$lines   = wrap( $t['name'], $name_pt, $col, 3 );

	// Eyebrow.
	$eyebrow = strtoupper( 'Listed on Oria Haven' );
	$eb_pt   = 'card' === $format ? 17.0 : 19.0;
	// Letter-spaced by hand: GD has no tracking control.
	$spaced = implode( ' ', str_split( $eyebrow ) );
	$spaced = str_replace( '    ', '   ', $spaced );

	$baseline = 'story' === $format ? (int) round( $h * 0.52 ) : (int) round( $h * 0.42 );
	$line_h   = (int) round( $name_pt * 1.62 );

	text( $im, $eb_pt, $pad, $baseline - $line_h * count( $lines ) - (int) round( $line_h * 0.55 ), $gold, $spaced, 0.4 );

	$y = $baseline - $line_h * ( count( $lines ) - 1 );
	foreach ( $lines as $line ) {
		text( $im, $name_pt, $pad, $y, $white, $line, 0.9 );
		$y += $line_h;
	}

	// Rule, then the suburb and practice underneath it.
	$rule_y = $y - (int) round( $line_h * 0.35 );
	imagefilledrectangle( $im, $pad, $rule_y, $pad + (int) round( $w * 0.075 ), $rule_y + 4, $gold );

	text( $im, 'card' === $format ? 25.0 : 28.0, $pad, $rule_y + (int) round( $line_h * 0.62 ), $mist, $t['meta'], 0.3 );

	// Domain, bottom left, so the card still reads if it's cropped.
	text( $im, 'card' === $format ? 20.0 : 23.0, $pad, $h - $pad, $mist, 'oriahaven.com.au', 0.3 );

	$ok = imagepng( $im, $path, 6 );
	imagedestroy( $im );
	return (bool) $ok;
}

/* --------------------------------------------------------------- og image */

/**
 * A listing's own photo still wins; the generated card fills the gap
 * where there isn't one, which is most of the directory.
 *
 * @param mixed $image
 * @return mixed
 */
function og_image( $image ) {
	if ( ! is_singular( PostTypes\LISTING ) || $image ) {
		return $image;
	}
	return preview_url( (int) get_queried_object_id() ) ?: $image;
}

/**
 * The image a listing should present when its link is pasted somewhere:
 * its own lead photo if it has one, otherwise the generated card.
 *
 * Listings carry photos in an ACF gallery rather than a featured image,
 * so Yoast finds nothing on its own either way.
 */
function preview_url( int $id ): string {
	$gallery = array_values( array_filter( array_map( 'intval', (array) ( get_field( 'gallery', $id ) ?: array() ) ) ) );
	if ( $gallery ) {
		$photo = wp_get_attachment_image_url( $gallery[0], 'large' );
		if ( $photo ) {
			return (string) $photo;
		}
	}
	return card_url( $id, 'card' );
}

/**
 * Yoast builds its head from a presentation object; an empty image list
 * there means the og:image presenter is skipped entirely, filter and all.
 * Filling the list is the only point at which a listing can be given one.
 *
 * @param mixed $presentation
 * @return mixed
 */
function presentation_image( $presentation ) {
	if ( ! is_object( $presentation ) || ! is_singular( PostTypes\LISTING ) ) {
		return $presentation;
	}
	if ( ! empty( $presentation->open_graph_images ) ) {
		return $presentation;
	}
	$url = preview_url( (int) get_queried_object_id() );
	if ( ! $url ) {
		return $presentation;
	}
	$image = array( 'url' => $url );
	if ( false !== strpos( $url, '-card-' ) ) {
		$image['width']  = FORMATS['card'][0];
		$image['height'] = FORMATS['card'][1];
		$image['type']   = 'image/png';
	}
	$presentation->open_graph_images = array( $url => $image );
	return $presentation;
}

/**
 * Yoast normally owns the head. Without it a listing would share as a bare
 * link, so print the same tags ourselves — and only then.
 */
function head_image(): void {
	if ( defined( 'WPSEO_VERSION' ) || ! is_singular( PostTypes\LISTING ) ) {
		return;
	}
	$url = preview_url( (int) get_queried_object_id() );
	if ( ! $url ) {
		return;
	}
	printf(
		"<meta property=\"og:image\" content=\"%s\" />\n<meta name=\"twitter:image\" content=\"%1\$s\" />\n<meta name=\"twitter:card\" content=\"summary_large_image\" />\n",
		esc_url( $url )
	);
}

/* ------------------------------------------------------------ share copy */

/** The post we write for them, so sharing costs nothing but a tap. */
function suggested_post( int $listing_id ): string {
	$t = card_text( $listing_id );
	return sprintf(
		"We're now listed on Oria Haven, Perth's independent wellness directory. You can find %s — and everything we offer — on our profile here:\n\n%s",
		$t['name'],
		(string) get_permalink( $listing_id )
	);
}

/** Profile link with campaign tags, so shared traffic is attributable. */
function tagged_url( int $listing_id, string $medium ): string {
	return add_query_arg(
		array(
			'utm_source'   => 'practitioner',
			'utm_medium'   => $medium,
			'utm_campaign' => 'share-kit',
		),
		(string) get_permalink( $listing_id )
	);
}

/** @return array<string, string> label => share URL */
function share_links( int $listing_id ): array {
	$t = card_text( $listing_id );
	return array(
		'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( tagged_url( $listing_id, 'facebook' ) ),
		'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( tagged_url( $listing_id, 'linkedin' ) ),
		'WhatsApp' => 'https://wa.me/?text=' . rawurlencode( $t['name'] . ' is now listed on Oria Haven: ' . tagged_url( $listing_id, 'whatsapp' ) ),
		'Email'    => 'mailto:?subject=' . rawurlencode( $t['name'] . ' on Oria Haven' ) . '&body=' . rawurlencode( suggested_post( $listing_id ) ),
	);
}

/* --------------------------------------------------------- email section */

/**
 * The "share it" block appended to the claim-approval and listing-live
 * emails — the two moments a practitioner is most pleased with us.
 */
function email_block( int $listing_id ): string {
	$t = card_text( $listing_id );
	return "\n\n" . sprintf(
		"SHARE IT WITH YOUR CLIENTS\n%s now has a profile people can find, and it looks good shared — we've made you a card with your name on it, written the post, and put share buttons for Facebook, LinkedIn and WhatsApp all in one place:\n%s\n\nThere are Instagram-sized images there too, if you'd rather post one of those.",
		$t['name'],
		url( $listing_id )
	);
}
