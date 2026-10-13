<?php
/**
 * Offer promotions: the 30-day pilot package (Phase 5 of the Oct 2026 brief).
 *
 * A promotion is paid placement for a listing's EXISTING, checked offer --
 * it never creates an offer, never changes one, and never touches Best Of
 * (editorial picks stay independent of payment). One record per listing,
 * in post meta _oria_promo, edited in the listing's "Offer promotion" box:
 *
 *   start, end      Perth dates, inclusive. Live from 00:00 on start to
 *                   23:59 on end, Perth time, and only while the offer
 *                   itself is live (Theme\active_offer()).
 *   places          where it is promoted: 'hub' (the Promoted band on
 *                   /offers/), 'category' (labelled on its category's offer
 *                   collection), 'newsletter' (the offer slot in "Your
 *                   Perth weekend" -- only tick this when a send is actually
 *                   planned and authorised).
 *   code            the venue's own booking/redemption code, shown on the
 *                   card; booking URLs are never rewritten.
 *   fee, note       internal: what was agreed. Never shown.
 *   redemptions     venue-confirmed redemptions, typed in by Dale from what
 *                   the venue reports. The only number here that is a sale.
 *
 * Every placement says "Promoted" on the card itself, every time. The
 * report (Listings > Promotions) keeps observed actions -- offer views,
 * offer clicks, booking clicks, enquiries -- apart from venue-confirmed
 * redemptions, for exactly the promotion's own dates.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Offers;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PROMO_META   = '_oria_promo';
const PROMO_PLACES = array(
	'hub'        => 'The Promoted band on /offers/',
	'category'   => 'Labelled on its category page’s offers',
	'newsletter' => 'The offer slot in “Your Perth weekend” (only when a send is planned and authorised)',
);
const PROMO_SLUG   = 'oria-promotions';

function promo_bootstrap(): void {
	add_action( 'add_meta_boxes_' . PostTypes\LISTING, __NAMESPACE__ . '\promo_box' );
	add_action( 'save_post_' . PostTypes\LISTING, __NAMESPACE__ . '\promo_save', 20 );
	add_action( 'admin_menu', __NAMESPACE__ . '\promo_menu' );
}
promo_bootstrap();

/* ------------------------------------------------------------------ read */

/** The stored record, normalised; empty array when there is none. */
function promo_record( int $listing ): array {
	$r = get_post_meta( $listing, PROMO_META, true );
	if ( ! is_array( $r ) || empty( $r['start'] ) || empty( $r['end'] ) ) {
		return array();
	}
	return array(
		'start'            => (string) $r['start'],
		'end'              => (string) $r['end'],
		'places'           => array_values( array_intersect( array_keys( PROMO_PLACES ), (array) ( $r['places'] ?? array() ) ) ),
		'code'             => (string) ( $r['code'] ?? '' ),
		'fee'              => (string) ( $r['fee'] ?? '' ),
		'note'             => (string) ( $r['note'] ?? '' ),
		'redemptions'      => null === ( $r['redemptions'] ?? null ) || '' === $r['redemptions'] ? null : (int) $r['redemptions'],
		'redemptions_note' => (string) ( $r['redemptions_note'] ?? '' ),
	);
}

/** 'scheduled', 'live' or 'ended' for a record, by Perth date. */
function promo_state( array $r ): string {
	$today = current_time( 'Y-m-d' );
	if ( $today < $r['start'] ) {
		return 'scheduled';
	}
	return $today > $r['end'] ? 'ended' : 'live';
}

/**
 * The live promotion for a listing at one place, or [] -- live means inside
 * its Perth dates, ticked for that place, AND the offer itself still live,
 * so a promotion can never advertise an expired offer.
 */
function promotion( int $listing, string $place ): array {
	$r = promo_record( $listing );
	if ( ! $r || 'live' !== promo_state( $r ) || ! in_array( $place, $r['places'], true ) ) {
		return array();
	}
	if ( 'publish' !== get_post_status( $listing ) || ! live( $listing ) ) {
		return array();
	}
	return $r;
}

/** @return list<int> listings with a live promotion at this place. */
function promoted( string $place ): array {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", PROMO_META ) ) );
	return array_values( array_filter( $ids, static fn( int $id ): bool => (bool) promotion( $id, $place ) ) );
}

/* ----------------------------------------------------------------- admin */

function promo_box(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_meta_box( 'oria-promo', __( 'Offer promotion (paid pilot)', 'oria' ), __NAMESPACE__ . '\promo_box_render', PostTypes\LISTING, 'side', 'low' );
}

function promo_box_render( \WP_Post $post ): void {
	$r = promo_record( (int) $post->ID );
	wp_nonce_field( 'oria_promo_' . $post->ID, 'oria_promo_nonce' );
	$v = static fn( string $k ): string => esc_attr( (string) ( $r[ $k ] ?? '' ) );
	echo '<p style="margin-top:0;color:#50575e">' . esc_html__( 'Promotes this listing’s existing, checked offer. Every placement is labelled “Promoted”. Leave the dates empty for no promotion.', 'oria' ) . '</p>';
	if ( ! live( (int) $post->ID ) ) {
		echo '<p style="color:#b32d2e">' . esc_html__( 'This listing has no live offer, so a promotion would show nothing.', 'oria' ) . '</p>';
	}
	printf( '<p><label>%s<br><input type="date" name="oria_promo[start]" value="%s"></label></p>', esc_html__( 'Starts (Perth date)', 'oria' ), $v( 'start' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	printf( '<p><label>%s<br><input type="date" name="oria_promo[end]" value="%s"></label></p>', esc_html__( 'Ends, inclusive (Perth date)', 'oria' ), $v( 'end' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p style="margin-bottom:4px"><strong>' . esc_html__( 'Where', 'oria' ) . '</strong></p>';
	foreach ( PROMO_PLACES as $k => $label ) {
		printf( '<label style="display:block;margin:0 0 4px"><input type="checkbox" name="oria_promo[places][]" value="%s"%s> %s</label>', esc_attr( $k ), checked( in_array( $k, $r['places'] ?? array( 'hub', 'category' ), true ), true, false ), esc_html( $label ) );
	}
	printf( '<p><label>%s<br><input type="text" name="oria_promo[code]" value="%s" class="widefat"></label></p>', esc_html__( 'Venue’s code (optional, shown)', 'oria' ), $v( 'code' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	printf( '<p><label>%s<br><input type="text" name="oria_promo[fee]" value="%s" class="widefat"></label></p>', esc_html__( 'Agreed fee (internal)', 'oria' ), $v( 'fee' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	printf( '<p><label>%s<br><textarea name="oria_promo[note]" rows="2" class="widefat">%s</textarea></label></p>', esc_html__( 'Note (internal)', 'oria' ), esc_textarea( (string) ( $r['note'] ?? '' ) ) );
	printf( '<p><label>%s<br><input type="number" min="0" name="oria_promo[redemptions]" value="%s" style="width:6em"></label></p>', esc_html__( 'Venue-confirmed redemptions', 'oria' ), null === ( $r['redemptions'] ?? null ) ? '' : (int) $r['redemptions'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	printf( '<p><label>%s<br><input type="text" name="oria_promo[redemptions_note]" value="%s" class="widefat" placeholder="%s"></label></p>', esc_html__( 'Confirmed how and when', 'oria' ), $v( 'redemptions_note' ), esc_attr__( 'e.g. email from owner, 14 Nov', 'oria' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
}

function promo_save( int $post_id ): void {
	if ( ! isset( $_POST['oria_promo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oria_promo_nonce'] ) ), 'oria_promo_' . $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
		return;
	}
	$in    = (array) wp_unslash( $_POST['oria_promo'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitised below.
	$date  = static fn( $d ): string => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $d ) ? (string) $d : '';
	$start = $date( $in['start'] ?? '' );
	$end   = $date( $in['end'] ?? '' );
	if ( '' === $start || '' === $end ) {
		delete_post_meta( $post_id, PROMO_META );
		return;
	}
	if ( $end < $start ) {
		$end = $start;
	}
	$red = trim( (string) ( $in['redemptions'] ?? '' ) );
	update_post_meta(
		$post_id,
		PROMO_META,
		array(
			'start'            => $start,
			'end'              => $end,
			'places'           => array_values( array_intersect( array_keys( PROMO_PLACES ), array_map( 'sanitize_key', (array) ( $in['places'] ?? array() ) ) ) ),
			'code'             => sanitize_text_field( (string) ( $in['code'] ?? '' ) ),
			'fee'              => sanitize_text_field( (string) ( $in['fee'] ?? '' ) ),
			'note'             => sanitize_textarea_field( (string) ( $in['note'] ?? '' ) ),
			'redemptions'      => '' === $red ? null : max( 0, (int) $red ),
			'redemptions_note' => sanitize_text_field( (string) ( $in['redemptions_note'] ?? '' ) ),
		)
	);
	do_action( 'litespeed_purge_post', $post_id );
	do_action( 'litespeed_purge_url', home_url( '/offers/' ) );
}

/* ---------------------------------------------------------------- report */

function promo_menu(): void {
	add_submenu_page( 'edit.php?post_type=' . PostTypes\LISTING, __( 'Promotions', 'oria' ), __( 'Promotions', 'oria' ), 'manage_options', PROMO_SLUG, __NAMESPACE__ . '\promo_report' );
}

/** Sum one counter over Perth dates [from, to] inclusive, from the listing's daily stats. */
function promo_sum( int $listing, string $type, string $from, string $to ): int {
	$stats = get_post_meta( $listing, '_oria_stats', true );
	$sum   = 0;
	foreach ( is_array( $stats ) ? $stats : array() as $day => $row ) {
		if ( $day >= $from && $day <= $to ) {
			$sum += (int) ( $row[ $type ] ?? 0 );
		}
	}
	return $sum;
}

function promo_report(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids   = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", PROMO_META ) ) );
	$today = current_time( 'Y-m-d' );
	$obs   = array(
		'offerview' => __( 'Offer views', 'oria' ),
		'offer'     => __( 'Offer clicks', 'oria' ),
		'book'      => __( 'Booking clicks', 'oria' ),
		'web'       => __( 'Website clicks', 'oria' ),
		'tel'       => __( 'Phone taps', 'oria' ),
		'enq'       => __( 'Enquiries', 'oria' ),
	);
	echo '<div class="wrap"><h1>' . esc_html__( 'Offer promotions', 'oria' ) . '</h1>';
	echo '<p style="max-width:62rem">' . esc_html__( 'Each row covers exactly the promotion’s own Perth dates (up to today while it is live). Observed actions are what visitors did on Oria Haven: a click is interest, not a purchase. Only the last column is a sale, and only as the venue confirmed it. Counts exclude the owner and crawlers; stats are kept for 90 days, so record redemptions before then.', 'oria' ) . '</p>';
	if ( ! $ids ) {
		echo '<p>' . esc_html__( 'No promotions yet. Add one from a listing’s “Offer promotion” box.', 'oria' ) . '</p></div>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Listing and offer', 'oria' ) . '</th><th>' . esc_html__( 'Dates', 'oria' ) . '</th><th>' . esc_html__( 'Status', 'oria' ) . '</th><th>' . esc_html__( 'Where', 'oria' ) . '</th>';
	foreach ( $obs as $label ) {
		echo '<th style="text-align:right">' . esc_html( $label ) . '</th>';
	}
	echo '<th style="text-align:right">' . esc_html__( 'Venue-confirmed redemptions', 'oria' ) . '</th></tr></thead><tbody>';
	foreach ( $ids as $id ) {
		$r = promo_record( $id );
		if ( ! $r ) {
			continue;
		}
		$to    = min( $r['end'], $today );
		$state = promo_state( $r );
		$o     = live( $id );
		$warn  = 'live' === $state && ! $o ? ' — ' . __( 'offer has ended, so nothing is showing', 'oria' ) : '';
		echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) ) . '</a><br><span style="color:#50575e">' . esc_html( $o ? (string) $o['title'] : __( '(no live offer)', 'oria' ) ) . '</span></td>';
		echo '<td>' . esc_html( wp_date( 'j M', (int) strtotime( $r['start'] ) ) . ' – ' . wp_date( 'j M Y', (int) strtotime( $r['end'] ) ) ) . '</td>';
		echo '<td>' . esc_html( ucfirst( $state ) . $warn ) . '</td>';
		echo '<td>' . esc_html( implode( ', ', $r['places'] ) ?: '—' ) . '</td>';
		foreach ( array_keys( $obs ) as $type ) {
			echo '<td style="text-align:right">' . ( 'scheduled' === $state ? '—' : esc_html( number_format_i18n( promo_sum( $id, $type, $r['start'], $to ) ) ) ) . '</td>';
		}
		echo '<td style="text-align:right">' . ( null === $r['redemptions'] ? '<span style="color:#50575e">' . esc_html__( 'not reported', 'oria' ) . '</span>' : esc_html( number_format_i18n( $r['redemptions'] ) ) . ( '' !== $r['redemptions_note'] ? '<br><span style="color:#50575e">' . esc_html( $r['redemptions_note'] ) . '</span>' : '' ) ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}
