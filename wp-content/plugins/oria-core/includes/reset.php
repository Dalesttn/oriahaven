<?php
/**
 * Micro Resets: reading one for the page that draws it.
 *
 * The template asks for shapes, not fields. Everything here returns
 * something already decided -- which panel is true today, which practical
 * rows are known, what the stages are -- so the template never has to hold
 * an opinion about Perth time or about what counts as an answer.
 *
 * The rule the whole file is built on: an empty field is not a row. A page
 * that says "Toilets: TBC" is worse than one that says nothing about
 * toilets, because the first invites somebody to rely on it.
 */

declare(strict_types=1);

namespace Oria\Core\Reset;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TYPE_TERM = 'micro-reset';

/** Is this journey one of the guided hours, rather than a day out? */
function is_reset( int $post_id ): bool {
	return PostTypes\JOURNEY === get_post_type( $post_id )
		&& has_term( TYPE_TERM, PostTypes\JOURNEY_TYPE, $post_id );
}

/**
 * One field, by key.
 *
 * Always by key, never by name: two field groups on this site share field
 * names, and ACF's name lookup resolves against a global map that picks the
 * wrong one outside the admin. That failure is silent -- the page simply
 * renders as though the editor never filled anything in.
 */
function f( int $post_id, string $name ) {
	if ( ! function_exists( 'get_field' ) ) {
		return null;
	}

	return get_field( 'field_oria_reset_' . $name, $post_id );
}

/** A trimmed string, or ''. */
function s( int $post_id, string $name ): string {
	return trim( (string) f( $post_id, $name ) );
}

/** "Calm, Nature, Reset" -> list. */
function csv( string $value ): array {
	return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) );
}

/** Seconds as m:ss, for a timer face. */
function clock( int $seconds ): string {
	return sprintf( '%d:%02d', intdiv( $seconds, 60 ), $seconds % 60 );
}

/**
 * Everything the page needs about a reset.
 *
 * @return array<string, mixed>
 */
function data( int $post_id ): array {
	$mins    = (int) f( $post_id, 'duration_minutes' );
	$season  = (string) f( $post_id, 'season' );
	$verified = s( $post_id, 'last_verified' );

	$labels = array(
		'gentle'      => __( 'Gentle walking', 'oria' ),
		'moderate'    => __( 'Moderate', 'oria' ),
		'active'      => __( 'Active', 'oria' ),
		'spring'      => __( 'Spring', 'oria' ),
		'summer'      => __( 'Summer', 'oria' ),
		'autumn'      => __( 'Autumn', 'oria' ),
		'winter'      => __( 'Winter', 'oria' ),
		'any'         => __( 'Any time', 'oria' ),
		'self-guided' => __( 'Self-guided', 'oria' ),
		'guided'      => __( 'Guided', 'oria' ),
	);
	$label = static fn( string $v ): string => $labels[ $v ] ?? $v;

	// At a glance: only what has been filled in.
	$glance = array();
	$add    = static function ( string $l, string $v ) use ( &$glance ): void {
		if ( '' !== trim( $v ) ) {
			$glance[] = array( 'label' => $l, 'value' => trim( $v ) );
		}
	};
	$add( __( 'Duration', 'oria' ), $mins > 0 ? sprintf( /* translators: %d: minutes */ _n( '%d minute', '%d minutes', $mins, 'oria' ), $mins ) : '' );
	$add( __( 'Location', 'oria' ), s( $post_id, 'location_label' ) );
	$add( __( 'Cost', 'oria' ), s( $post_id, 'cost_label' ) );
	$add( __( 'Intensity', 'oria' ), '' !== (string) f( $post_id, 'intensity' ) ? $label( (string) f( $post_id, 'intensity' ) ) : '' );
	$add( __( 'Best for', 'oria' ), implode( ' · ', csv( s( $post_id, 'best_for' ) ) ) );
	$add( __( 'Best season', 'oria' ), '' !== $season ? $label( $season ) : '' );
	$add( __( 'Format', 'oria' ), '' !== (string) f( $post_id, 'format' ) ? $label( (string) f( $post_id, 'format' ) ) : '' );
	$add( __( 'Suitable for', 'oria' ), s( $post_id, 'suitable_for' ) );

	$eyebrow = trim( implode( ' · ', array_filter( array(
		__( 'Oria Micro Reset', 'oria' ),
		'' !== $season && 'any' !== $season ? sprintf( /* translators: %s: season */ __( 'Perth %s', 'oria' ), strtolower( $label( $season ) ) ) : '',
	) ) ) );

	$related = array();
	foreach ( (array) f( $post_id, 'related_listings' ) as $rid ) {
		$rid = (int) ( is_object( $rid ) ? ( $rid->ID ?? 0 ) : $rid );
		if ( $rid > 0 && 'publish' === get_post_status( $rid ) ) {
			$related[] = $rid;
		}
	}

	return array(
		'eyebrow'        => $eyebrow,
		'hook'           => s( $post_id, 'hook' ),
		'intro'          => s( $post_id, 'intro' ),
		'glance'         => $glance,
		'stages'         => stages( $post_id ),
		'complete'       => s( $post_id, 'complete_message' ),
		'verified'       => '' !== $verified ? (string) wp_date( 'j F Y', (int) strtotime( $verified . ' 12:00:00' ) ) : '',
		'conditions_url' => s( $post_id, 'conditions_url' ),
		'related'        => $related,
		'related_reason' => s( $post_id, 'related_reason' ),
	);
}

/**
 * The stages, in order.
 *
 * A row with neither a label nor a prompt is skipped: a half-filled
 * repeater row is an editing accident, not a stage of a walk.
 *
 * @return list<array<string, mixed>>
 */
function stages( int $post_id ): array {
	$out = array();

	foreach ( (array) f( $post_id, 'stages' ) as $row ) {
		$title = trim( (string) ( $row['title'] ?? '' ) );
		$body  = trim( (string) ( $row['body'] ?? '' ) );
		if ( '' === $title && '' === $body ) {
			continue;
		}

		$from = (string) ( $row['from'] ?? '' );
		$to   = (string) ( $row['to'] ?? '' );
		$when = '';
		if ( '' !== $from && '' !== $to ) {
			/* translators: 1: start minute, 2: end minute */
			$when = sprintf( __( '%1$s–%2$s min', 'oria' ), $from, $to );
		} elseif ( '' !== $from ) {
			/* translators: %s: minute */
			$when = sprintf( __( 'from %s min', 'oria' ), $from );
		}

		$out[] = array(
			'title'     => '' !== $title ? $title : $body,
			'body'      => $body,
			'when'      => $when,
			'action'    => trim( (string) ( $row['action'] ?? '' ) ),
			'safety'    => trim( (string) ( $row['safety'] ?? '' ) ),
			'timer'     => max( 0, (int) ( $row['timer'] ?? 0 ) ),
			'responses' => csv( trim( (string) ( $row['responses'] ?? '' ) ) ),
		);
	}

	return $out;
}

/**
 * The practical rows that are actually known.
 *
 * @return list<array{label: string, value: string, url: string}>
 */
function practical( int $post_id ): array {
	$rows = array();
	$add  = static function ( string $label, string $value, string $url = '' ) use ( &$rows ): void {
		if ( '' !== trim( $value ) ) {
			$rows[] = array( 'label' => $label, 'value' => trim( $value ), 'url' => $url );
		}
	};

	$start = s( $post_id, 'start_label' );
	$add( __( 'Start', 'oria' ), $start, '' !== $start ? s( $post_id, 'start_map_url' ) : '' );

	$end = s( $post_id, 'end_label' );
	if ( '' === $end && f( $post_id, 'is_loop' ) && '' !== $start ) {
		$end = __( 'Back where you started', 'oria' );
	}
	$add( __( 'Finish', 'oria' ), $end );

	$km = (float) f( $post_id, 'distance_km' );
	$add( __( 'Distance', 'oria' ), $km > 0 ? sprintf( /* translators: %s: kilometres */ __( '%s km', 'oria' ), rtrim( rtrim( number_format_i18n( $km, 1 ), '0' ), '.' ) ) : '' );

	$shade   = (string) f( $post_id, 'shade' );
	$shades  = array( 'none' => __( 'Very little', 'oria' ), 'partial' => __( 'Partial', 'oria' ), 'frequent' => __( 'Frequent', 'oria' ) );

	foreach ( array(
		'surface'                => __( 'Surface', 'oria' ),
		'gradient'               => __( 'Gradients', 'oria' ),
		'steps_or_barriers'      => __( 'Steps and barriers', 'oria' ),
		'accessibility_summary'  => __( 'Accessibility', 'oria' ),
		'accessible_alternative' => __( 'Step-free alternative', 'oria' ),
		'parking'                => __( 'Parking', 'oria' ),
		'public_transport'       => __( 'Public transport', 'oria' ),
		'toilets'                => __( 'Toilets', 'oria' ),
		'water'                  => __( 'Drinking water', 'oria' ),
		'seating'                => __( 'Seating', 'oria' ),
		'best_time'              => __( 'Best time to go', 'oria' ),
		'pet_rules'              => __( 'Dogs', 'oria' ),
		'weather_note'           => __( 'Weather', 'oria' ),
	) as $name => $label ) {
		$add( $label, s( $post_id, $name ) );
		if ( 'seating' === $name ) {
			$add( __( 'Shade', 'oria' ), $shades[ $shade ] ?? '' );
		}
	}

	return $rows;
}

/**
 * Which panel is true today: the seasonal one, the evergreen one, or none.
 *
 * Decided in Perth time and compared as plain Y-m-d strings. The dates are
 * naive local dates, and turning them into timestamps is how this sort of
 * comparison goes wrong on a site whose PHP runs in UTC: a festival closing
 * on the 4th would stop being on partway through the 3rd, Perth time.
 * Comparing the strings sidesteps the arithmetic entirely.
 *
 * @return array<string, string>|null
 */
function panel( int $post_id ): ?array {
	if ( ! f( $post_id, 'seasonal_on' ) ) {
		return null;
	}

	$today = (string) current_time( 'Y-m-d' );
	$from  = s( $post_id, 'seasonal_start' );
	$until = s( $post_id, 'seasonal_end' );

	$running = ( '' === $from || $today >= $from ) && ( '' === $until || $today <= $until );

	$title = $running ? s( $post_id, 'seasonal_title' ) : s( $post_id, 'evergreen_title' );
	if ( '' === $title ) {
		return null;
	}

	return array(
		'kind'      => $running ? 'on' : 'evergreen',
		'title'     => $title,
		'body'      => $running ? s( $post_id, 'seasonal_body' ) : s( $post_id, 'evergreen_body' ),
		'cta_label' => $running ? s( $post_id, 'seasonal_cta_label' ) : s( $post_id, 'evergreen_cta_label' ),
		'cta_url'   => $running ? s( $post_id, 'seasonal_cta_url' ) : s( $post_id, 'evergreen_cta_url' ),
	);
}
