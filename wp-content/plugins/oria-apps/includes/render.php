<?php
/**
 * The app card, and the band that carries a few of them.
 *
 * Markup lives here rather than in the theme because three surfaces draw
 * the same card -- the hub, a category page, and a band on a practice page
 * -- and a card that differs between them is how a design system rots.
 * Classes reuse the theme's existing tokens; nothing here ships its own
 * colours or radii.
 */

declare(strict_types=1);

namespace Oria\Apps\Render;

use Oria\Apps\Data;
use Oria\Apps\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_shortcode( 'wellness_apps', __NAMESPACE__ . '\shortcode' );
}

/** An arrow, from the theme when it is there. */
function arrow(): string {
	return function_exists( '\Oria\Theme\arrow' ) ? \Oria\Theme\arrow() : '';
}

/**
 * One app as a card.
 *
 * @param array<string, mixed> $row from Engine\row()
 */
function card( array $row ): string {
	$cat = (string) ( $row['cat_names'][0] ?? '' );

	$out  = '<article class="appcard" data-oapp="' . esc_attr( (string) $row['id'] ) . '">';
	$out .= '<a class="appcard__top" href="' . esc_url( (string) $row['url'] ) . '">';

	if ( '' !== (string) $row['logo'] ) {
		// Empty alt: the app's name is the next thing read out, so the icon
		// would only repeat it.
		$out .= '<img class="appcard__logo" src="' . esc_url( (string) $row['logo'] ) . '" alt="" width="56" height="56" loading="lazy" decoding="async">';
	} else {
		$out .= '<span class="appcard__logo appcard__logo--none" aria-hidden="true"></span>';
	}

	$out .= '<span class="appcard__head">';
	if ( '' !== $cat ) {
		$out .= '<span class="micro appcard__cat">' . esc_html( $cat ) . '</span>';
	}
	$out .= '<b class="appcard__name">' . esc_html( (string) $row['title'] ) . '</b>';
	$out .= '</span></a>';

	if ( $row['best_for'] ) {
		$out .= '<p class="appcard__for"><span>' . esc_html__( 'Best for', 'oria' ) . '</span> '
			. esc_html( Data\label( 'best_for', (string) $row['best_for'][0] ) ) . '</p>';
	}

	$blurb = '' !== (string) $row['tagline'] ? (string) $row['tagline'] : (string) $row['excerpt'];
	if ( '' !== $blurb ) {
		$out .= '<p class="appcard__blurb">' . esc_html( $blurb ) . '</p>';
	}

	$out .= '<div class="appcard__foot">';
	$out .= '<span class="appcard__price">' . esc_html( Engine\price_line( $row ) ) . '</span>';
	$out .= '<a class="btn btn--sm btn--dark" href="' . esc_url( (string) $row['url'] ) . '">'
		. esc_html__( 'View app', 'oria' ) . arrow() . '</a>';
	$out .= '</div></article>';

	return $out;
}

/**
 * A row of cards with a heading: "continue your practice at home".
 *
 * @param list<array<string, mixed>> $rows
 */
function band( array $rows, string $heading = '', string $intro = '' ): string {
	if ( ! $rows ) {
		return '';
	}
	$heading = '' !== $heading ? $heading : __( 'Continue at home', 'oria' );

	$out  = '<section class="wrap section section--top-flush appband">';
	$out .= '<div class="sec-head reveal"><div class="sec-head__text">';
	$out .= '<span class="micro">' . esc_html__( 'Wellness apps', 'oria' ) . '</span>';
	$out .= '<h2 class="h2">' . esc_html( $heading ) . '</h2>';
	if ( '' !== $intro ) {
		$out .= '<p class="appband__intro">' . esc_html( $intro ) . '</p>';
	}
	$out .= '</div>';
	$out .= '<a class="btn btn--ghost" href="' . esc_url( (string) get_post_type_archive_link( Data\CPT ) ) . '">'
		. esc_html__( 'All apps', 'oria' ) . arrow() . '</a>';
	$out .= '</div>';

	$out .= '<div class="appgrid">';
	foreach ( $rows as $row ) {
		$out .= card( $row );
	}
	$out .= '</div>';

	if ( affiliate_in( $rows ) ) {
		$out .= '<p class="appband__disclosure">' . esc_html( Data\disclosure() ) . '</p>';
	}
	$out .= '</section>';

	return $out;
}

/** True when any of these apps links out commercially. */
function affiliate_in( array $rows ): bool {
	foreach ( $rows as $row ) {
		if ( ! empty( $row['affiliate'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The band a practice page shows, or '' when nothing genuinely fits.
 *
 * Deliberately quiet: three apps at most, below the listings, and never on
 * a practice the map has no apps for. The directory's job is still to send
 * people to practices.
 */
function for_practice( \WP_Term $practice, int $limit = 3 ): string {
	$rows = Engine\for_practice( $practice, $limit );
	if ( count( $rows ) < 2 ) {
		return '';
	}
	$name = function_exists( '\Oria\Theme\tname' ) ? \Oria\Theme\tname( $practice ) : wp_specialchars_decode( $practice->name );

	return band(
		$rows,
		__( 'Continue between classes', 'oria' ),
		sprintf(
			/* translators: %s: practice name, lowercased */
			__( 'Apps that may help you keep a %s practice going at home. They are a companion to the places above, not a replacement.', 'oria' ),
			strtolower( $name )
		)
	);
}

/**
 * [wellness_apps] — for putting a band inside a journal post.
 *
 * Attributes: category, best_for, ids, limit, heading, intro.
 */
function shortcode( $atts ): string {
	$a = shortcode_atts(
		array( 'category' => '', 'best_for' => '', 'ids' => '', 'limit' => 3, 'heading' => '', 'intro' => '' ),
		is_array( $atts ) ? $atts : array(),
		'wellness_apps'
	);

	$limit = max( 1, (int) $a['limit'] );
	$rows  = array();

	if ( '' !== $a['ids'] ) {
		foreach ( array_filter( array_map( 'intval', explode( ',', (string) $a['ids'] ) ) ) as $id ) {
			if ( 'publish' === get_post_status( $id ) ) {
				$rows[] = Engine\row( $id );
			}
		}
	} elseif ( '' !== $a['best_for'] ) {
		$rows = Engine\by_best_for( (string) $a['best_for'], $limit );
	} else {
		$cats = array_filter( array_map( 'trim', explode( ',', (string) $a['category'] ) ) );
		$rows = Engine\apps( $cats, $limit );
	}

	return band( array_slice( $rows, 0, $limit ), (string) $a['heading'], (string) $a['intro'] );
}
