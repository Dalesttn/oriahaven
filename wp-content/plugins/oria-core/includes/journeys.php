<?php
/**
 * Wellness Journeys — the index of the days out.
 *
 * A journey is not a category somebody has to remember to tick. It is any
 * published article that actually carries journey steps, which means the
 * index cannot drift from the articles: add the steps and the piece appears
 * here, empty the repeater and it leaves. A taxonomy would have given us two
 * sources of truth and eventually a journey filed as a guide.
 *
 * The route is its own address rather than /category/journeys/ because it is
 * a main-navigation destination, and a nav item should not send somebody to a
 * URL with the word "category" in it.
 */

declare(strict_types=1);

namespace Oria\Core\Journeys;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

const QUERY_VAR = 'oria_journeys';
const PATH      = 'journeys';
const REWRITE_V = '1';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 10 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 20 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 20 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description', 20 );
}

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QUERY_VAR . '=1', 'top' );
}

/** The rule only reaches the server once the rules are rebuilt. */
function maybe_flush(): void {
	if ( get_option( 'oria_journeys_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_journeys_rewrite_v', REWRITE_V );
	}
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

function is_page(): bool {
	return (bool) get_query_var( QUERY_VAR );
}

/** Same trick as the hub: stop a parameterless rule reading as the home page. */
function fix_query( \WP_Query $q ): void {
	if ( ! $q->is_main_query() || ! $q->get( QUERY_VAR ) ) {
		return;
	}
	$q->is_home       = false;
	$q->is_front_page = false;
	$q->is_archive    = false;
	$q->is_singular   = false;
	$q->is_404        = false;
	$q->set( 'posts_per_page', 1 );
}

function template( string $template ): string {
	if ( ! is_page() ) {
		return $template;
	}
	$found = locate_template( array( 'oria-journeys.php' ) );
	return $found ? $found : $template;
}

/**
 * Every published article carrying at least one journey step.
 *
 * ACF writes the row count to the repeater's own meta key, so "has steps" is
 * a numeric test on one row rather than a scan of journey_0_listing and its
 * siblings. Newest first: a journey is a piece of writing, not a ranking.
 *
 * @return \WP_Post[]
 */
function posts(): array {
	$q = new \WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 24,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_query'          => array(
				array(
					'key'     => 'journey',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	return $q->posts;
}

/**
 * The shape of a journey, for the card: how many stops and how long a day.
 *
 * Read from the steps themselves rather than typed twice. A step with no time
 * still counts as a stop -- it is somewhere you go -- it just cannot bound the
 * day, so the span is quietly dropped rather than guessed at.
 *
 * @return array{stops:int,span:string}
 */
function shape( int $post_id ): array {
	if ( ! function_exists( 'get_field' ) ) {
		return array(
			'stops' => 0,
			'span'  => '',
		);
	}
	$rows  = (array) ( get_field( 'journey', $post_id ) ?: array() );
	$times = array();
	foreach ( $rows as $row ) {
		$t = trim( (string) ( $row['time'] ?? '' ) );
		if ( '' !== $t ) {
			$times[] = $t;
		}
	}
	return array(
		'stops' => count( $rows ),
		'span'  => count( $times ) > 1 ? $times[0] . ' – ' . $times[ count( $times ) - 1 ] : '',
		'times' => $times,
	);
}

/**
 * The feature card's hour rail.
 *
 * The one place on this page where a numbered sequence is honest: the stops
 * in a day do have an order, where the journeys themselves do not. Long days
 * are trimmed from the middle rather than the end, because the first and last
 * hours are what tell you whether the day fits yours.
 *
 * @param string[] $times
 * @return array{times:string[],trimmed:bool}
 */
function rail( array $times, int $max = 7 ): array {
	if ( count( $times ) <= $max ) {
		return array(
			'times'   => $times,
			'trimmed' => false,
		);
	}
	$head = (int) ceil( $max / 2 );
	return array(
		'times'   => array_merge( array_slice( $times, 0, $head ), array_slice( $times, -( $max - $head ) ) ),
		'trimmed' => true,
	);
}

/**
 * @return array{feature:?\WP_Post,rest:\WP_Post[]}
 */
function split(): array {
	$all = posts();
	return array(
		'feature' => array_shift( $all ),
		'rest'    => $all,
	);
}

function heading(): string {
	return __( 'Wellness Journeys', 'oria' );
}

function lede(): string {
	return __( 'Days built out of real places, in an order that works. Each stop is a listing with its own hours and prices, so a journey corrects itself when somewhere moves or closes.', 'oria' );
}

function title( $title ) {
	return is_page() ? sprintf( '%s in Perth | %s', heading(), get_bloginfo( 'name' ) ) : $title;
}

function core_title( $parts ) {
	if ( is_page() && is_array( $parts ) ) {
		$parts['title'] = sprintf( '%s in Perth', heading() );
	}
	return $parts;
}

function description( $desc ) {
	return is_page() ? __( 'Ready-made wellness days around Perth: a swim, a class, a meal and a walk, timed so each place is open when you arrive. Every stop is a real listing.', 'oria' ) : $desc;
}
