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
 * A line drawing for a goal, following the theme's amenity icons: one
 * 16x16 box, strokes in currentColor, and a plain dot when a key has no
 * drawing -- so adding a value to Data\BEST_FOR can never leave a hole on
 * a live page.
 *
 * Drawn rather than illustrated. These sit eight to a row above the fold
 * and their job is to make a list of words scannable, not to be looked at
 * -- a set of little pictures competing with the labels would slow down
 * exactly the reader they are supposed to help.
 *
 * "Over 50s" gets a sunrise rather than anything to do with bodies or
 * hearts: a walking stick would be patronising, and a heart on a wellness
 * directory reads as a claim about heart health that nobody here is
 * making.
 */
function goal_icon( string $key ): string {
	$p = array(
		// a seedling with two leaves
		'beginners'        => '<path d="M8 14V7.5"/><path d="M8 8.5C6.3 8.5 5 7.4 5 5.4c2 0 3 1.1 3 3.1Z"/><path d="M8 8.5c1.7 0 3-1.1 3-3.1-2 0-3 1.1-3 3.1Z"/>',
		// crescent moon
		'better-sleep'     => '<path d="M12 9.8A4.8 4.8 0 0 1 6.2 4a5 5 0 1 0 5.8 5.8Z"/>',
		// water at rest
		'relaxation'       => '<path d="M2.5 6.2c1.1-1 2.2-1 3.3 0s2.2 1 3.3 0 2.2-1 3.3 0"/><path d="M2.5 9.8c1.1-1 2.2-1 3.3 0s2.2 1 3.3 0 2.2-1 3.3 0"/><path d="M2.5 13c1.1-1 2.2-1 3.3 0s2.2 1 3.3 0 2.2-1 3.3 0"/>',
		// a leaf. Not because stress is botanical -- because the first two
		// drawings here were both a dot inside arcs, and at 20px "stress
		// support" and "mindfulness" were the same picture. A set of icons
		// that cannot be told apart is slower to read than no icons at all.
		'stress-support'   => '<path d="M13 3c0 5.5-3.6 9-9 9-.7 0-1.4-.1-2-.3C2.3 6.7 6.8 3 13 3Z"/><path d="M11 5 3 13"/>',
		// a seated figure
		'meditation'       => '<circle cx="8" cy="3.8" r="1.5"/><path d="M8 6.3c-2 0-3.3 1.4-3.3 3.2 0 .9.4 1.7 1 2.2"/><path d="M8 6.3c2 0 3.3 1.4 3.3 3.2 0 .9-.4 1.7-1 2.2"/><path d="M4.4 13.5h7.2"/>',
		// a figure walking
		'getting-active'   => '<circle cx="9.2" cy="3.2" r="1.4"/><path d="M9.6 6c-1 .2-1.8.9-2 1.9L7.2 9.5l1.8 1.2.4 3"/><path d="M9 10.7 6.4 13.5"/><path d="M7.6 7.9 5.4 7"/>',
		// a loop that comes back round
		'building-habits'  => '<path d="M13 8a5 5 0 1 1-1.6-3.7"/><path d="M13.2 3.2v2.4h-2.4"/><path d="M6 8.2l1.5 1.5L10.3 7"/>',
		// a ridge line
		'walking-outdoors' => '<path d="M2 12.5l3.6-5.4 2.5 3.5 2.3-3.2 3.6 5.1Z"/><circle cx="11.9" cy="3.8" r="1.3"/>',
		// a figure running
		'running'          => '<circle cx="9.6" cy="3.2" r="1.4"/><path d="M10 6c-1.2.1-2.2.8-2.6 1.9L6.9 9.4l2 1.1.2 3"/><path d="M8.9 10.5 5.8 12.8"/><path d="M7.5 8 4.8 7.2"/><path d="M2.4 9.6h1.8M2.8 5.8h1.6"/>',
		// a dumbbell
		'home-workouts'    => '<path d="M3 6.4v3.2M5 5.2v5.6M11 5.2v5.6M13 6.4v3.2"/><path d="M5 8h6"/>',
		// ripples from a still point
		'mindfulness'      => '<circle cx="8" cy="8" r="1.2"/><path d="M10.8 5.2a4 4 0 0 1 0 5.6"/><path d="M5.2 10.8a4 4 0 0 1 0-5.6"/><path d="M13 3a7 7 0 0 1 0 10"/>',
		// a mark being aimed at
		'focus'            => '<circle cx="8" cy="8" r="5"/><circle cx="8" cy="8" r="1.8"/><path d="M8 1.5v1.8M8 12.7v1.8M1.5 8h1.8M12.7 8h1.8"/>',
		// something coming back round
		'recovery'         => '<path d="M3 8a5 5 0 1 1 1.6 3.7"/><path d="M2.8 12.8v-2.4h2.4"/><path d="M8 5.4V8l1.8 1.1"/>',
		// two figures
		'social'           => '<circle cx="6" cy="4.6" r="1.7"/><path d="M2.6 13c0-1.9 1.5-3.4 3.4-3.4S9.4 11.1 9.4 13"/><circle cx="11.4" cy="5.6" r="1.4"/><path d="M10 9.9a3 3 0 0 1 3.5 2.6"/>',
		// a sunrise
		'over-50s'         => '<path d="M8 3v1.6M4.4 4.6l1.1 1.1M11.6 4.6l-1.1 1.1"/><path d="M5 11.5a3 3 0 0 1 6 0"/><path d="M2 11.5h12"/><path d="M4 14h8"/>',
		// a price tag
		'free'             => '<path d="M8.6 2.5H13a.5.5 0 0 1 .5.5v4.4a1 1 0 0 1-.3.7l-5.6 5.6a1 1 0 0 1-1.4 0L2.3 9.8a1 1 0 0 1 0-1.4l5.6-5.6a1 1 0 0 1 .7-.3Z"/><circle cx="10.9" cy="5.1" r=".9"/>',
	);

	if ( ! isset( $p[ $key ] ) ) {
		return '<span class="appgoal__dot" aria-hidden="true"></span>';
	}

	return '<svg class="appgoal__icon" viewBox="0 0 16 16" width="20" height="20" fill="none" '
		. 'stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" '
		. 'aria-hidden="true" focusable="false">' . $p[ $key ] . '</svg>';
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
