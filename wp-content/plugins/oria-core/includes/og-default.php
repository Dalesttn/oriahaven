<?php
/**
 * Default social preview images for everything that is not a listing.
 *
 * Listings already present a photo or a generated card (see Share). The
 * rest of the site — home, the directory, practice/specialty/suburb
 * landings, the hub, the journal index, plain pages — had no og:image
 * at all, so a pasted link rendered as bare text in chats and feeds.
 * Every URL now presents a branded card, drawn by the Share brush and
 * titled the way the page titles itself.
 *
 * The Yoast nuance is the one Share documents: an empty image list
 * skips the og:image presenter entirely, so the image has to be planted
 * on the presentation object itself, not offered to a later filter.
 */

declare(strict_types=1);

namespace Oria\Core\OgDefault;

use Oria\Core\PostTypes;
use Oria\Core\Share;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	// After Share's 20, so listings keep their own treatment untouched.
	add_filter( 'wpseo_frontend_presentation', __NAMESPACE__ . '\presentation_image', 30 );
	add_filter( 'wpseo_twitter_image', __NAMESPACE__ . '\twitter_image', 30 );
}

/**
 * Whether this request is one we decorate: anything front-end except
 * listings (Share's job), 404s (nothing worth sharing), and feeds.
 */
function applies(): bool {
	return ! is_admin() && ! is_404() && ! is_feed() && ! is_singular( PostTypes\LISTING );
}

/**
 * What the card should say for the current request. Term pages follow
 * their landing pages' own H1 patterns; everything else speaks for the
 * site. The key only has to be stable and filename-safe — uniqueness
 * comes from the text hash beside it.
 *
 * @return array{key: string, name: string, meta: string}
 */
function card_spec(): array {
	if ( is_front_page() ) {
		return array(
			'key'  => 'home',
			'name' => 'Wellness in Perth',
			'meta' => 'Meditation, yoga, breathwork, bodywork and more',
		);
	}

	$obj = get_queried_object();

	if ( $obj instanceof \WP_Term ) {
		$count = (int) $obj->count;
		$name  = $obj->name;
		if ( in_array( $obj->taxonomy, array( 'practice', 'specialty' ), true ) ) {
			$name = sprintf( '%s in Perth', $obj->name );
		} elseif ( 'area' === $obj->taxonomy ) {
			$name = sprintf( 'Wellness in %s', $obj->name );
		}
		return array(
			'key'  => $obj->taxonomy . '-' . $obj->slug,
			'name' => $name,
			/* translators: %d: number of listed practices */
			'meta' => sprintf( _n( '%d listed practice', '%d listed practices', $count, 'oria' ), $count ),
		);
	}

	if ( is_post_type_archive( PostTypes\EVENT ) ) {
		return array(
			'key'  => 'events',
			'name' => "What's on in Perth",
			'meta' => 'Wellness events across the metro',
		);
	}

	if ( $obj instanceof \WP_Post ) {
		return array(
			'key'  => 'p' . $obj->ID,
			'name' => wp_specialchars_decode( get_the_title( $obj ), ENT_QUOTES ),
			'meta' => "Perth's independent wellness directory",
		);
	}

	return array(
		'key'  => 'site',
		'name' => 'Oria Haven',
		'meta' => "Perth's guide to wellness and conscious living",
	);
}

/** The card for the current request, or '' when drawing is impossible. */
function card_url(): string {
	$spec = card_spec();
	return Share\generic_card_url(
		$spec['key'],
		array(
			'name' => $spec['name'],
			'meta' => $spec['meta'],
		)
	);
}

/**
 * @param mixed $presentation
 * @return mixed
 */
function presentation_image( $presentation ) {
	if ( ! is_object( $presentation ) || ! applies() ) {
		return $presentation;
	}
	if ( ! empty( $presentation->open_graph_images ) ) {
		return $presentation;
	}
	$url = card_url();
	if ( ! $url ) {
		return $presentation;
	}
	$presentation->open_graph_images = array(
		$url => array(
			'url'    => $url,
			'width'  => Share\FORMATS['card'][0],
			'height' => Share\FORMATS['card'][1],
			'type'   => 'image/png',
		),
	);
	return $presentation;
}

/**
 * @param mixed $image
 * @return mixed
 */
function twitter_image( $image ) {
	if ( $image || ! applies() ) {
		return $image;
	}
	return card_url() ?: $image;
}
