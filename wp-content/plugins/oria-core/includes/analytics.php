<?php
/**
 * Listing analytics: profile views and contact-action clicks, kept as daily
 * buckets in post meta and shown on the listing's edit screen.
 *
 * This is deliberately first-party and tiny — no cookies, no IPs stored, no
 * external service. It exists to answer the one question a paying
 * practitioner asks: "is this listing sending me people?"
 */

declare(strict_types=1);

namespace Oria\Core\Analytics;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_STATS = '_oria_stats';
const KEEP_DAYS  = 90;
const TYPES      = array( 'view', 'web', 'tel', 'mail', 'book', 'dir', 'enq' );

function bootstrap(): void {
	add_action( 'rest_api_init', __NAMESPACE__ . '\routes' );
	add_action( 'add_meta_boxes_' . PostTypes\LISTING, __NAMESPACE__ . '\metabox' );
}

/* ---------------------------------------------------------------- record */

/**
 * All of a listing's counters live in one serialised meta row, so a bare
 * read-modify-write loses increments whenever two of them overlap — and
 * they do overlap, because a visitor who taps "directions" and then
 * "call" fires two beacons that race each other to the same row. Under
 * five simultaneous beacons roughly two were being dropped.
 *
 * A named MySQL lock serialises the writers. If the lock can't be had in
 * time we still write: an occasional lost count beats a dropped one, and
 * these numbers are what a paying practitioner judges us by.
 */
function record( int $post_id, string $type ): void {
	if ( ! in_array( $type, TYPES, true ) ) {
		return;
	}

	global $wpdb;
	$lock = substr( 'oria_stats_' . $post_id . '_' . DB_NAME, 0, 64 );
	$held = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock ) );

	// Another request may have written since this one started; read inside
	// the lock, not before it.
	wp_cache_delete( $post_id, 'post_meta' );

	$stats = get_post_meta( $post_id, META_STATS, true );
	$stats = is_array( $stats ) ? $stats : array();
	$today = current_time( 'Y-m-d' );

	$stats[ $today ][ $type ] = (int) ( $stats[ $today ][ $type ] ?? 0 ) + 1;

	// Trim buckets older than the retention window.
	$cutoff = gmdate( 'Y-m-d', time() - KEEP_DAYS * DAY_IN_SECONDS );
	foreach ( array_keys( $stats ) as $day ) {
		if ( $day < $cutoff ) {
			unset( $stats[ $day ] );
		}
	}

	update_post_meta( $post_id, META_STATS, $stats );

	if ( 1 === $held ) {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

/** One metric as a day-by-day series over the last N days, oldest first. */
function series( int $post_id, string $type, int $days ): array {
	$stats = get_post_meta( $post_id, META_STATS, true );
	$stats = is_array( $stats ) ? $stats : array();
	$out   = array();
	for ( $i = $days - 1; $i >= 0; $i-- ) {
		$day   = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
		$out[] = (int) ( $stats[ $day ][ $type ] ?? 0 );
	}
	return $out;
}

/** Sum one metric over the last N days. */
function total( int $post_id, string $type, int $days ): int {
	$stats = get_post_meta( $post_id, META_STATS, true );
	if ( ! is_array( $stats ) ) {
		return 0;
	}
	$cutoff = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
	$sum    = 0;
	foreach ( $stats as $day => $row ) {
		if ( $day >= $cutoff ) {
			$sum += (int) ( $row[ $type ] ?? 0 );
		}
	}
	return $sum;
}

/* ----------------------------------------------------------------- views */

/**
 * Whether this request should count as a profile view.
 *
 * Views used to be counted server-side on the `wp` hook, which quietly
 * stopped working: listing pages are served from LiteSpeed's page cache
 * (verified — a second request returns X-LiteSpeed-Cache: hit), and a
 * cached response never runs PHP. Every visitor after the first within the
 * cache window went uncounted, so the one number a practitioner is shown to
 * justify paying was reading low by an unknown and variable margin.
 *
 * The count now arrives by the same beacon the contact clicks use. A REST
 * request is never cached, so it is counted every time — and the guards
 * that lived in the old hook move here, where they still work: the beacon
 * carries the visitor's cookies, so an owner reading their own listing is
 * still recognised and still not counted.
 */
function countable_view( int $post_id ): bool {
	if ( current_user_can( 'edit_post', $post_id ) ) {
		return false;
	}
	$agent = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	// Most crawlers never run the script that sends this, but the ones that
	// do are the ones worth excluding by name.
	return '' !== $agent && ! preg_match( '/bot|crawl|spider|slurp|preview|facebookexternalhit/i', $agent );
}

/* ---------------------------------------------------------------- clicks */

function routes(): void {
	register_rest_route(
		'oria/v1',
		'/track',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'args'                => array(
				'id'   => array( 'type' => 'integer', 'required' => true ),
				'type' => array( 'type' => 'string', 'required' => true, 'enum' => array( 'view', 'web', 'tel', 'mail', 'book', 'dir', 'enq' ) ),
			),
			'callback'            => __NAMESPACE__ . '\track_endpoint',
		)
	);
}

function track_endpoint( \WP_REST_Request $request ): \WP_REST_Response {
	$post_id = (int) $request['id'];
	$type    = (string) $request['type'];

	if ( PostTypes\LISTING !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		return new \WP_REST_Response( null, 204 );
	}

	// A light per-IP throttle: an anonymous public counter does not need to
	// absorb a curl loop.
	$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'oria_trk_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n > 30 ) {
		return new \WP_REST_Response( null, 204 );
	}
	set_transient( $key, $n + 1, MINUTE_IN_SECONDS );

	if ( 'view' === $type && ! countable_view( $post_id ) ) {
		return new \WP_REST_Response( null, 204 );
	}

	record( $post_id, $type );
	return new \WP_REST_Response( null, 204 );
}

/* --------------------------------------------------------------- metabox */

function metabox( \WP_Post $post ): void {
	// Analytics is a paid surface: owners of managed listings and admins.
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return;
	}
	add_meta_box(
		'oria-analytics',
		__( 'Performance', 'oria' ),
		__NAMESPACE__ . '\render_metabox',
		PostTypes\LISTING,
		'side',
		'high'
	);
}

function render_metabox( \WP_Post $post ): void {
	// Performance stats are a Claimed-plan surface: free-plan owners see
	// what they're missing, not the numbers.
	if ( \Oria\Core\Ownership\is_practitioner() && ! \Oria\Core\Tiers\allows( $post->ID, 'analytics' ) ) {
		$pay = function_exists( '\Oria\Core\Billing\pay_url' )
			? \Oria\Core\Billing\pay_url( 'claimed', $post->ID, (string) wp_get_current_user()->user_email )
			: '';
		echo '<div class="oria-perf__hero" style="text-align:center;padding:22px 16px;">';
		echo '<div style="font-size:26px;line-height:1;">&#128274;</div>';
		echo '<div class="oria-perf__hero-label" style="margin-top:10px;">' . esc_html__( 'Performance stats', 'oria' ) . '</div>';
		echo '<p style="margin:6px 0 0;font-size:12px;color:#C8D9CF;line-height:1.5;">' . esc_html__( 'Profile views, website clicks, calls and booking clicks — see exactly what your listing sends you. Included in the Claimed plan.', 'oria' ) . '</p>';
		if ( '' !== $pay ) {
			echo '<p style="margin:14px 0 2px;"><a href="' . esc_url( $pay ) . '" style="display:inline-block;background:#C9A24B;color:#082220;text-decoration:none;font-weight:600;font-size:13px;padding:9px 22px;border-radius:999px;">' . esc_html__( 'Upgrade to view', 'oria' ) . '</a></p>';
		}
		echo '</div>';
		return;
	}

	$views_30 = total( $post->ID, 'view', 30 );
	$views_7  = total( $post->ID, 'view', 7 );

	// --- hero: profile views + 30-day sparkline -------------------------
	echo '<div class="oria-perf__hero">';
	echo '<div class="oria-perf__hero-label">' . esc_html__( 'Profile views · 30 days', 'oria' ) . '</div>';
	echo '<div class="oria-perf__hero-num">' . esc_html( number_format_i18n( $views_30 ) ) . '</div>';
	echo '<div class="oria-perf__hero-sub">' . esc_html( sprintf( /* translators: %s: view count */ __( '%s in the last 7 days', 'oria' ), number_format_i18n( $views_7 ) ) ) . '</div>';
	echo sparkline( series( $post->ID, 'view', 30 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- built SVG, all numeric.
	echo '</div>';

	// --- contact actions ------------------------------------------------
	$cells = array(
		'web'  => __( 'Website clicks', 'oria' ),
		'tel'  => __( 'Phone taps', 'oria' ),
		'mail' => __( 'Email clicks', 'oria' ),
		'book' => __( 'Booking clicks', 'oria' ),
		'enq'  => __( 'Enquiries received', 'oria' ),
		'dir'  => __( 'Directions', 'oria' ),
	);
	echo '<div class="oria-perf__grid">';
	foreach ( $cells as $type => $label ) {
		printf(
			'<div class="oria-perf__cell"><div class="oria-perf__cell-label">%s</div><div class="oria-perf__cell-num">%s</div><div class="oria-perf__cell-sub">%s</div></div>',
			esc_html( $label ),
			esc_html( number_format_i18n( total( $post->ID, $type, 30 ) ) ),
			esc_html( sprintf( /* translators: %s: click count */ __( '%s · 7 days', 'oria' ), number_format_i18n( total( $post->ID, $type, 7 ) ) ) )
		);
	}
	echo '</div>';

	echo '<p class="oria-perf__note">' . esc_html__( 'Counted on this site only — no cookies, no third-party trackers. Your own visits are excluded. Figures cover the last 30 days unless marked.', 'oria' ) . '</p>';
}

/**
 * A tiny inline-SVG area chart of a daily series. No libraries, no JS —
 * just a polyline scaled into a fixed viewBox.
 */
function sparkline( array $series ): string {
	$n = count( $series );
	if ( $n < 2 ) {
		return '';
	}
	$w    = 240;
	$h    = 44;
	$pad  = 3;
	$max  = max( 1, max( $series ) );
	$pts  = array();
	foreach ( $series as $i => $v ) {
		$x     = $pad + ( $i / ( $n - 1 ) ) * ( $w - 2 * $pad );
		$y     = $h - $pad - ( $v / $max ) * ( $h - 2 * $pad );
		$pts[] = round( $x, 1 ) . ',' . round( $y, 1 );
	}
	$line = implode( ' ', $pts );
	$area = $pad . ',' . ( $h - $pad ) . ' ' . $line . ' ' . ( $w - $pad ) . ',' . ( $h - $pad );

	return '<svg class="oria-perf__spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
		. '<polygon points="' . $area . '" fill="rgba(201,162,75,0.18)"/>'
		. '<polyline points="' . $line . '" fill="none" stroke="#C9A24B" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/>'
		. '</svg>';
}
