<?php
/**
 * "Live music this weekend" -- a small card on /this-weekend/ and What's On,
 * fed by GigWave's public read-only gig feed (gigwave.com.au, same owner).
 *
 * The feed: GET {base}/wp-json/gigwave/v1/gigs?when=weekend&limit=4 returns
 * { source, when, from, to, total, url, updated, gigs: [ { act, act_url,
 * venue, venue_url, suburb, date, time, start, day, time_label, entry,
 * free } ] }.
 *
 * Rules, because this is a link to a sister site and must stay editorial:
 * - Fetched server-side, short timeout, cached an hour. A visitor never
 *   waits on GigWave and GigWave never sees a visitor's IP.
 * - The last good copy is kept. If a fetch fails the card keeps showing it
 *   while its gigs are still ahead; once they have all passed it shows
 *   nothing. No error, no empty box, no "check back later".
 * - Only links whose host is GigWave's own are ever printed, whatever the
 *   feed says.
 *
 * Base URL: the ORIA_GIGWAVE_FEED_BASE constant (wp-config) wins, then the
 * oria_gigwave_feed_base option, then the live site. Locally it points at
 * staging (test.gigwave.com.au) until the feed is on the live site.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\GigWave;

defined( 'ABSPATH' ) || exit;

const DEFAULT_BASE = 'https://gigwave.com.au';
const CACHE_KEY    = 'oria_gigwave_weekend';
const LAST_GOOD    = 'oria_gigwave_weekend_last';
const BACKOFF_KEY  = 'oria_gigwave_weekend_backoff';

/** The feed's base URL, without a trailing slash. */
function base(): string {
	$base = defined( 'ORIA_GIGWAVE_FEED_BASE' ) ? (string) constant( 'ORIA_GIGWAVE_FEED_BASE' ) : (string) get_option( 'oria_gigwave_feed_base', '' );
	$base = untrailingslashit( trim( $base ) );
	return '' !== $base && allowed_url( $base ) ? $base : DEFAULT_BASE;
}

/** True only for an https URL on gigwave.com.au or one of its subdomains. */
function allowed_url( string $url ): bool {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) ) {
		return false;
	}
	$host = strtolower( (string) ( $parts['host'] ?? '' ) );
	return 'gigwave.com.au' === $host || str_ends_with( $host, '.gigwave.com.au' );
}

/**
 * Fetch and clean the feed. Returns the cleaned payload, or null on any
 * failure (network, status, JSON, shape).
 *
 * @return array{url:string,to:string,gigs:list<array<string,string|bool>>}|null
 */
function fetch(): ?array {
	$res = wp_remote_get(
		base() . '/wp-json/gigwave/v1/gigs?when=weekend&limit=4',
		array(
			'timeout'     => 4,
			'redirection' => 2,
			'headers'     => array( 'Accept' => 'application/json' ),
			'user-agent'  => 'OriaHaven/1.0 (+https://oriahaven.com.au)',
		)
	);
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $data ) || ! isset( $data['gigs'] ) || ! is_array( $data['gigs'] ) ) {
		return null;
	}

	$gigs = array();
	foreach ( $data['gigs'] as $g ) {
		if ( ! is_array( $g ) ) {
			continue;
		}
		$act   = sanitize_text_field( (string) ( $g['act'] ?? '' ) );
		$venue = sanitize_text_field( (string) ( $g['venue'] ?? '' ) );
		$start = (string) ( $g['start'] ?? '' );
		$link  = esc_url_raw( (string) ( $g['venue_url'] ?? '' ) );
		if ( '' === $act || '' === $venue || ! strtotime( $start ) || ! allowed_url( $link ) ) {
			continue;
		}
		$gigs[] = array(
			'act'    => $act,
			'venue'  => $venue,
			'suburb' => sanitize_text_field( (string) ( $g['suburb'] ?? '' ) ),
			'start'  => $start,
			'day'    => sanitize_text_field( (string) ( $g['day'] ?? '' ) ),
			'time'   => sanitize_text_field( (string) ( $g['time_label'] ?? '' ) ),
			'url'    => $link,
			'free'   => ! empty( $g['free'] ),
		);
	}

	$all = esc_url_raw( (string) ( $data['url'] ?? '' ) );
	return array(
		'url'  => allowed_url( $all ) ? $all : base() . '/gigs/this-weekend/',
		'to'   => sanitize_text_field( (string) ( $data['to'] ?? '' ) ),
		'gigs' => $gigs,
	);
}

/**
 * The weekend's gigs, still to come, at most $limit. Empty when there is
 * nothing to show -- the caller then renders nothing at all.
 *
 * @return array{url:string,gigs:list<array<string,string|bool>>}|array{}
 */
function weekend( int $limit = 4 ): array {
	$feed = get_transient( CACHE_KEY );
	if ( ! is_array( $feed ) ) {
		$feed = null;
		// After a failure, wait ten minutes before asking again rather than
		// putting a 4-second fetch in front of every page view.
		if ( ! get_transient( BACKOFF_KEY ) ) {
			$feed = fetch();
		}
		if ( is_array( $feed ) ) {
			set_transient( CACHE_KEY, $feed, HOUR_IN_SECONDS );
			update_option( LAST_GOOD, $feed, false );
		} else {
			if ( ! get_transient( BACKOFF_KEY ) ) {
				set_transient( BACKOFF_KEY, 1, 10 * MINUTE_IN_SECONDS );
			}
			$feed = get_option( LAST_GOOD );
		}
	}
	if ( ! is_array( $feed ) || empty( $feed['gigs'] ) ) {
		return array();
	}

	// A kept copy can outlive its weekend: never show a gig that has
	// already started.
	$now  = time();
	$gigs = array_values(
		array_filter(
			(array) $feed['gigs'],
			static fn( $g ): bool => is_array( $g ) && (int) strtotime( (string) $g['start'] ) > $now
		)
	);
	if ( ! $gigs ) {
		return array();
	}
	return array(
		'url'  => (string) $feed['url'],
		'gigs' => array_slice( $gigs, 0, max( 1, $limit ) ),
	);
}

/** Flush the cache (after changing the base URL, say). */
function flush(): void {
	delete_transient( CACHE_KEY );
	delete_transient( BACKOFF_KEY );
}
