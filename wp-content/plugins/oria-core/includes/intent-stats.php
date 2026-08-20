<?php
/**
 * How often each intent is actually chosen.
 *
 * The hero's "Popular right now" pills were hand-typed rows on the home
 * page, which meant popular was whatever somebody last decided it was. This
 * counts the real thing: every time a visitor arrives at a filtered view —
 * `?aud=beginners`, `?svc=yin-yoga`, `?price=Free` — that intent gets a tick.
 *
 * First-party and tiny, like Analytics: no cookies, no IPs, no external
 * service, daily buckets pruned to a month. The difference is where it goes.
 * Listing stats belong to a listing; an intent belongs to nobody, so these
 * live in one option.
 *
 * THE FEEDBACK LOOP, AND WHY src=hero EXISTS.
 *
 * A pill on the home page sends traffic to an intent. That traffic makes the
 * intent look popular. Being popular keeps the pill on the home page. Left
 * alone the list calcifies inside a fortnight and stops being a measurement
 * at all — it becomes a record of what we promoted last month.
 *
 * So the pills carry `src=hero`, and a request carrying it is never counted.
 * The pills can report the ranking; they cannot vote in it.
 *
 * Bots and logged-in staff are excluded for the same reasons as Analytics:
 * a crawler sweeping every filter combination would otherwise decide what is
 * popular, and it would be wrong.
 */

declare(strict_types=1);

namespace Oria\Core\IntentStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION    = 'oria_intent_stats';
const KEEP_DAYS = 30;

/** The query keys that describe an intent, and nothing else. */
const KEYS = array( 'aud', 'svc', 'price', 'format' );

/** Below this in the window, a chip is noise rather than a signal. */
const FLOOR = 10;

function bootstrap(): void {
	add_action( 'template_redirect', __NAMESPACE__ . '\maybe_record', 20 );
}

/* ------------------------------------------------------------- recording */

function maybe_record(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( is_admin() || is_preview() || is_user_logged_in() ) {
		return;
	}

	// The pills' own traffic. See the note at the top of this file.
	if ( isset( $_GET['src'] ) ) {
		return;
	}

	$agent = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	if ( '' === $agent || preg_match( '/bot|crawl|spider|slurp|preview|facebookexternalhit/i', $agent ) ) {
		return;
	}

	$hits = array();
	foreach ( KEYS as $key ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			continue;
		}

		// A filter may hold several values; each is its own intent.
		foreach ( explode( ',', (string) wp_unslash( $_GET[ $key ] ) ) as $value ) {
			$value = trim( $value );
			if ( '' !== $value ) {
				$hits[] = $key . ':' . sanitize_text_field( $value );
			}
		}
	}
	// phpcs:enable

	if ( $hits ) {
		record( array_unique( $hits ) );
	}
}

/**
 * Add one to each intent's bucket for today.
 *
 * One option, one lock, one write — the same read-modify-write race
 * Analytics hit with two beacons landing together, and the same fix.
 *
 * @param list<string> $intents
 */
function record( array $intents ): void {
	global $wpdb;

	$lock = substr( 'oria_intent_stats_' . DB_NAME, 0, 64 );
	$held = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock ) );

	// Read inside the lock: another request may have written since this one
	// began, and the cached copy would silently drop its increment.
	wp_cache_delete( OPTION, 'options' );

	$stats = get_option( OPTION, array() );
	$stats = is_array( $stats ) ? $stats : array();
	$today = current_time( 'Y-m-d' );

	foreach ( $intents as $intent ) {
		$stats[ $today ][ $intent ] = (int) ( $stats[ $today ][ $intent ] ?? 0 ) + 1;
	}

	$cutoff = gmdate( 'Y-m-d', time() - KEEP_DAYS * DAY_IN_SECONDS );
	foreach ( array_keys( $stats ) as $day ) {
		if ( (string) $day < $cutoff ) {
			unset( $stats[ $day ] );
		}
	}

	// autoload false: this is read on the home page and nowhere else.
	update_option( OPTION, $stats, false );

	if ( 1 === $held ) {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

/* --------------------------------------------------------------- reading */

/**
 * Intent keys and their totals over the window, biggest first.
 *
 * @return array<string, int>
 */
function totals( int $days = KEEP_DAYS ): array {
	$stats = get_option( OPTION, array() );
	$stats = is_array( $stats ) ? $stats : array();

	$cutoff = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
	$out    = array();

	foreach ( $stats as $day => $rows ) {
		if ( (string) $day < $cutoff || ! is_array( $rows ) ) {
			continue;
		}
		foreach ( $rows as $intent => $n ) {
			$out[ $intent ] = ( $out[ $intent ] ?? 0 ) + (int) $n;
		}
	}

	arsort( $out );

	return $out;
}
