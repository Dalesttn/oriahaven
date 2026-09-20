<?php
/**
 * One place that answers "what events are coming up".
 *
 * The archive, a practice profile, a category page and a suburb page all
 * want the same thing with a different filter on it. Before this, each one
 * would have written its own WP_Query and they would have drifted apart --
 * one excluding cancelled events, another not; one counting past events by
 * accident on the day they finished.
 *
 * Everything asks here instead. The rules live once:
 *  - published, still to start, soonest first;
 *  - cancelled events never appear in an upcoming list;
 *  - an empty result means the caller renders nothing at all.
 *
 * Results are cached per query shape and the whole cache is dropped
 * whenever any event is saved, trashed or deleted, so an edit shows up
 * immediately rather than in fifteen minutes.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION_OPTION = 'oria_events_ver';
const CACHE_PREFIX   = 'oria_events_';
const CACHE_LIFE     = 15 * MINUTE_IN_SECONDS;

/*
 * Bumped whenever an event route changes shape: the submit page, the .ics
 * file, the share kit, the landing pages. Rewrite rules live in the
 * database, so a deploy that adds a route leaves it 404ing until somebody
 * saves the permalinks screen -- which is a step nobody should have to
 * remember, and the reason /submit-an-event/ was a 404 on production while
 * working perfectly in development.
 */
const ROUTES_VERSION = '2026-09-20-events-1';

function bootstrap(): void {
	foreach ( array( 'save_post_event', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\bump_version' );
	}
	/*
	 * Meta as well as the post. An event's start time, host and status all
	 * live in meta, and a CLI import or an ACF-only save changes those
	 * without ever firing save_post -- which left a cancelled event sitting
	 * in a cached "upcoming" list until the cache expired.
	 */
	foreach ( array( 'updated_post_meta', 'added_post_meta', 'deleted_post_meta' ) as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\bump_on_meta', 10, 3 );
	}

	// Late on init, so every event route has registered itself first.
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );

	// A finished event is not a search result. It keeps its page and its
	// links; it just stops asking to be indexed or crawled as current.
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\past_robots' );
	add_filter( 'wp_robots', __NAMESPACE__ . '\past_wp_robots' );
	add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', __NAMESPACE__ . '\sitemap_exclusions' );
}

/** @param int|string $meta_id @param int $post_id */
function bump_on_meta( $meta_id, $post_id, $meta_key ): void {
	if ( ! in_array( (string) $meta_key, array( 'event_start', 'event_end', 'event_status', 'listing' ), true ) ) {
		return;
	}
	bump_version( (int) $post_id );
}

/**
 * Register the event routes with WordPress once per deploy.
 *
 * One flush, guarded by a version string, rather than a flush on every
 * request: flush_rewrite_rules() rebuilds and writes the whole rule set,
 * which is expensive enough that doing it unconditionally is a known way
 * to make a site slow.
 */
function maybe_flush(): void {
	if ( get_option( 'oria_event_routes_v' ) !== ROUTES_VERSION ) {
		flush_rewrite_rules();
		update_option( 'oria_event_routes_v', ROUTES_VERSION, false );
	}
}

/**
 * Invalidate every cached list.
 *
 * Cheap: one option write. The alternative -- tracking which cached list
 * each event belongs to -- costs more than the queries it would save.
 */
function bump_version( $post_id = 0 ): void {
	if ( $post_id && 'event' !== get_post_type( (int) $post_id ) ) {
		return;
	}
	update_option( VERSION_OPTION, (string) time(), false );
}

/**
 * Upcoming events, filtered.
 *
 * @param array{limit?:int, listing?:int, practice?:string, event_type?:string, area?:string, exclude?:int[]} $args
 * @return int[] Event post IDs, soonest first.
 */
function upcoming( array $args = array() ): array {
	$args = wp_parse_args(
		$args,
		array(
			'limit'      => 3,
			'listing'    => 0,
			'practice'   => '',
			'event_type' => '',
			'area'       => '',
			'exclude'    => array(),
			// A window, for the pages that want one -- "this weekend" wants
			// both ends, "coming up after it" only the near one. Naive Perth
			// datetimes, the same form event_start is stored in.
			'from'       => '',
			'to'         => '',
		)
	);

	$key   = CACHE_PREFIX . md5( (string) get_option( VERSION_OPTION, '0' ) . wp_json_encode( $args ) );
	$found = get_transient( $key );
	if ( is_array( $found ) ) {
		return $found;
	}

	$query = array(
		'post_type'        => 'event',
		'post_status'      => 'publish',
		'posts_per_page'   => max( 1, (int) $args['limit'] ),
		'fields'           => 'ids',
		'post__not_in'     => array_map( 'intval', (array) $args['exclude'] ),
		'meta_key'         => 'event_start',
		'orderby'          => 'meta_value',
		'order'            => 'ASC',
		'suppress_filters' => false,
		'no_found_rows'    => true,
		'meta_query'       => array(
			'relation' => 'AND',
			array(
				'key'     => 'event_start',
				'value'   => '' !== (string) $args['from'] ? (string) $args['from'] : current_time( 'Y-m-d H:i:s' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
			// A cancelled event still has a page -- somebody who booked it
			// needs to find that out -- but it is not something to offer.
			array(
				'relation' => 'OR',
				array( 'key' => 'event_status', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'event_status', 'value' => 'cancelled', 'compare' => '!=' ),
			),
		),
	);

	if ( '' !== (string) $args['to'] ) {
		$query['meta_query'][] = array(
			'key'     => 'event_start',
			'value'   => (string) $args['to'],
			'compare' => '<=',
			'type'    => 'DATETIME',
		);
	}

	if ( (int) $args['listing'] > 0 ) {
		$query['meta_query'][] = array(
			'key'   => 'listing',
			'value' => (int) $args['listing'],
		);
	}

	$tax = array();
	foreach ( array( 'practice' => 'practice', 'event_type' => 'event_type', 'area' => 'area' ) as $arg => $taxonomy ) {
		if ( '' !== (string) $args[ $arg ] ) {
			$tax[] = array(
				'taxonomy'         => $taxonomy,
				'field'            => 'slug',
				'terms'            => (string) $args[ $arg ],
				// An area's children count as the area: an event in Hilton
				// belongs on Fremantle & South as much as in its own suburb.
				'include_children' => 'area' === $taxonomy,
			);
		}
	}
	if ( $tax ) {
		$query['tax_query'] = $tax;
	}

	$ids = array_map( 'intval', (array) get_posts( $query ) );
	set_transient( $key, $ids, CACHE_LIFE );

	return $ids;
}

/** Upcoming events run by one listing. */
function for_listing( int $listing_id, int $limit = 3 ): array {
	return $listing_id > 0 ? upcoming( array( 'listing' => $listing_id, 'limit' => $limit ) ) : array();
}

/** Upcoming events in one practice category. */
function for_practice( string $slug, int $limit = 3 ): array {
	return '' !== $slug ? upcoming( array( 'practice' => $slug, 'limit' => $limit ) ) : array();
}

/** Upcoming events in one area, its child suburbs included. */
function for_area( string $slug, int $limit = 3 ): array {
	return '' !== $slug ? upcoming( array( 'area' => $slug, 'limit' => $limit ) ) : array();
}

/** How many upcoming events there are in total, for a "view all" line. */
function total(): int {
	return count( upcoming( array( 'limit' => 200 ) ) );
}

/* ------------------------------------------------------------------ facts */

/** 'cancelled', 'postponed', 'sold-out' or '' (going ahead). */
function status( int $event_id ): string {
	$status = (string) get_post_meta( $event_id, 'event_status', true );
	return in_array( $status, array( 'cancelled', 'postponed', 'sold-out' ), true ) ? $status : '';
}

/**
 * How long it runs, in words, or '' when the end is not recorded.
 *
 * Minutes below an hour, hours where they divide cleanly, and days for a
 * retreat -- "2 days" reads better than "51 hours" on a weekend away.
 */
function duration( int $event_id ): string {
	$start = strtotime( (string) get_post_meta( $event_id, 'event_start', true ) );
	$end   = strtotime( (string) get_post_meta( $event_id, 'event_end', true ) );
	if ( ! $start || ! $end || $end <= $start ) {
		return '';
	}

	$minutes = (int) round( ( $end - $start ) / 60 );
	if ( $minutes < 60 ) {
		/* translators: %d: minutes */
		return sprintf( _n( '%d minute', '%d minutes', $minutes, 'oria' ), $minutes );
	}
	if ( $minutes < 24 * 60 ) {
		// floor() returns a float and the division can return an int, so the
		// comparison has to be made on one type: strict === reported a clean
		// two-hour workshop as "2.0 hours".
		$hours = $minutes / 60;
		$hours = (float) floor( (float) $hours ) === (float) $hours ? (string) (int) $hours : number_format_i18n( $hours, 1 );
		/* translators: %s: hours */
		return sprintf( _n( '%s hour', '%s hours', (int) ceil( (float) $hours ), 'oria' ), $hours );
	}
	$days = (int) ceil( $minutes / ( 24 * 60 ) );
	/* translators: %d: days */
	return sprintf( _n( '%d day', '%d days', $days, 'oria' ), $days );
}

/**
 * Has this event finished?
 *
 * The end time decides where there is one -- a weekend retreat is still on
 * during its Sunday -- and the start time otherwise, with the rest of the
 * starting day allowed so a morning class is not "finished" by lunchtime.
 */
function is_past( int $event_id ): bool {
	$end = (string) get_post_meta( $event_id, 'event_end', true );
	if ( '' !== $end ) {
		return strtotime( $end ) < (int) current_time( 'timestamp' );
	}
	$start = (string) get_post_meta( $event_id, 'event_start', true );
	if ( '' === $start ) {
		return false;
	}
	return (int) strtotime( $start . ' +1 day' ) < (int) current_time( 'timestamp' );
}

/* ------------------------------------------------- after the event is over */

/** Every finished event still on the site. Cached with everything else. */
function past_ids(): array {
	$key   = CACHE_PREFIX . 'past_' . md5( (string) get_option( VERSION_OPTION, '0' ) . gmdate( 'Y-m-d' ) );
	$found = get_transient( $key );
	if ( is_array( $found ) ) {
		return $found;
	}

	$ids = array_map(
		'intval',
		(array) get_posts(
			array(
				'post_type'      => 'event',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => 'event_start',
						'value'   => current_time( 'Y-m-d H:i:s' ),
						'compare' => '<',
						'type'    => 'DATETIME',
					),
				),
			)
		)
	);
	$ids = array_values( array_filter( $ids, __NAMESPACE__ . '\is_past' ) );

	set_transient( $key, $ids, HOUR_IN_SECONDS );
	return $ids;
}

/** @param string|array $robots */
function past_robots( $robots ) {
	if ( is_singular( 'event' ) && is_past( (int) get_queried_object_id() ) ) {
		return 'noindex, follow';
	}
	return $robots;
}

/**
 * The same through core's filter, so it survives Yoast not running.
 * 'follow' stays: the host and the archive linked from a finished event
 * are exactly where that visitor should go next.
 *
 * @param array $robots
 */
function past_wp_robots( $robots ): array {
	if ( is_singular( 'event' ) && is_past( (int) get_queried_object_id() ) ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}

/**
 * Keep finished events out of the XML sitemap.
 *
 * Aggregated events are deleted by the ingest sweep and answer 410, so
 * this is mostly about the ones we keep on purpose -- a member's own
 * event, and an organiser's submission.
 *
 * @param array $ids
 */
function sitemap_exclusions( $ids ): array {
	return array_values( array_unique( array_merge( (array) $ids, past_ids() ) ) );
}

/** The day this event's details were last verified, or 0. */
function verified( int $event_id ): int {
	$stamp = (string) get_post_meta( $event_id, '_oria_verified', true );
	return $stamp ? (int) strtotime( $stamp ) : 0;
}

/** Where the details came from: array{url,host} or null. */
function source( int $event_id ): ?array {
	$url = (string) get_post_meta( $event_id, '_oria_src_url', true );
	if ( '' === $url ) {
		return null;
	}
	return array(
		'url'  => $url,
		'host' => (string) ( wp_parse_url( $url, PHP_URL_HOST ) ?: __( 'the organiser', 'oria' ) ),
	);
}
