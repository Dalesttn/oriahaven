<?php
/**
 * Offers: the one special offer a listing can carry, gathered up for a
 * category page, and swept away once it has ended.
 *
 * Two kinds live in the same fields (see Theme\active_offer()): an owner's
 * offer on a claimed listing, and an ADVERTISED offer Oria Haven read on the
 * business's own site, which carries `offer_source` (the page) and
 * `offer_checked` (the date). Advertised offers arrive through
 * data/offers.json and tools/apply-offers.php, never by hand on prod.
 *
 *   among( $ids, $n )   live offers across a set of listings (a category
 *                       page's own set), newest-checked first
 *   sweep()             daily: an offer whose end date has passed is
 *                       removed from the listing, not merely hidden, so the
 *                       owner's dashboard and the data file agree with the
 *                       page
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Offers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CRON   = 'oria_offers_sweep';
const OPTION = 'oria_offers_last_sweep';
const FIELDS = array( 'offer_title', 'offer_text', 'offer_until', 'offer_source', 'offer_checked' );

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\schedule' );
	add_action( CRON, __NAMESPACE__ . '\sweep' );
}

function schedule(): void {
	if ( ! wp_next_scheduled( CRON ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', CRON );
	}
}

/** The live offer on one listing, or null. Theme\active_offer() is the one rule. */
function live( int $listing ): ?array {
	return function_exists( '\Oria\Theme\active_offer' ) ? \Oria\Theme\active_offer( $listing ) : null;
}

/**
 * Live offers among a set of listings -- a category page passes the set it
 * is already showing, so a float page shows float offers. Newest-checked
 * first, then soonest to end, so what is fresh and what is closing lead.
 *
 * @param  list<int> $ids
 * @return list<array{id:int, offer:array}>
 */
function among( array $ids, int $limit = 5 ): array {
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	if ( ! $ids ) {
		return array();
	}
	global $wpdb;
	// Only listings that carry a title at all are worth running the rule on.
	$ph   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$with = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'offer_title' AND meta_value <> '' AND post_id IN ($ph)", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$out  = array();
	foreach ( array_map( 'intval', $with ) as $id ) {
		if ( 'publish' !== get_post_status( $id ) ) {
			continue;
		}
		$o = live( $id );
		if ( $o ) {
			$out[] = array( 'id' => $id, 'offer' => $o );
		}
	}
	usort(
		$out,
		static function ( array $a, array $b ): int {
			$ca = (string) ( $a['offer']['checked'] ?: '0000' );
			$cb = (string) ( $b['offer']['checked'] ?: '0000' );
			if ( $ca !== $cb ) {
				return strcmp( $cb, $ca );
			}
			$ua = (string) ( $a['offer']['until'] ?: '9999' );
			$ub = (string) ( $b['offer']['until'] ?: '9999' );
			return strcmp( $ua, $ub );
		}
	);
	return array_slice( $out, 0, $limit );
}

/**
 * Remove every offer whose end date has passed. Runs daily; safe to run by
 * hand (`wp eval 'Oria\Core\Offers\sweep();'`). Returns the listing ids
 * cleared.
 *
 * @return list<int>
 */
function sweep(): array {
	global $wpdb;
	$today = current_time( 'Y-m-d' );
	$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'offer_until' AND meta_value <> '' AND REPLACE(meta_value, '-', '') < %s", str_replace( '-', '', $today ) ) );
	$done  = array();
	foreach ( array_map( 'intval', $rows ) as $id ) {
		if ( 'listing' !== get_post_type( $id ) ) {
			continue;
		}
		foreach ( FIELDS as $f ) {
			update_field( $f, '', $id );
		}
		$done[] = $id;
	}
	update_option( OPTION, array( 'at' => $today, 'cleared' => $done ), false );
	return $done;
}
