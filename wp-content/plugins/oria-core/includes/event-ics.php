<?php
/**
 * "Add to calendar" — one .ics file per event.
 *
 * A standards-based file rather than the usual row of Google/Apple/Outlook
 * buttons: every calendar application on every device already knows what to
 * do with it, nothing is handed to a third party on the way, and it works
 * for the person whose calendar is none of those three.
 *
 * Served at /events/{slug}/calendar.ics. The route is its own so the file
 * can be linked, shared and fetched without the page around it.
 *
 * What it carries is only what we hold: the times, the venue as written,
 * the price as published, and a link back to the event page -- which stays
 * the source of truth for anything that changes after the file is saved.
 * A cancelled event still produces a file, with STATUS:CANCELLED, so a
 * calendar that already holds it can be updated rather than left wrong.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\EventIcs;

use Oria\Core\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_event_ics';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	/*
	 * Before the canonical redirect, not after. Permalinks here end in a
	 * slash, so WordPress "corrects" calendar.ics to calendar.ics/ and the
	 * file never renders -- it just bounces.
	 */
	add_filter( 'redirect_canonical', __NAMESPACE__ . '\no_canonical_redirect' );
	add_action( 'template_redirect', __NAMESPACE__ . '\serve', 1 );
}

function route(): void {
	// The trailing slash is tolerated as well as suppressed: a link that
	// picks one up in an email client should still return the file.
	add_rewrite_rule( '^events/([^/]+)/calendar\.ics/?$', 'index.php?post_type=event&name=$matches[1]&' . QUERY_VAR . '=1', 'top' );
}

/** @param string|false $redirect @return string|false */
function no_canonical_redirect( $redirect ) {
	return get_query_var( QUERY_VAR ) ? false : $redirect;
}

/** @param string[] $vars @return string[] */
function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/** The download URL for an event. */
function url( int $event_id ): string {
	return trailingslashit( (string) get_permalink( $event_id ) ) . 'calendar.ics';
}

/**
 * Fold a line to 75 octets, as the spec requires.
 *
 * Long descriptions are normal and an unfolded line is the single most
 * common reason an .ics is rejected without explanation.
 */
function fold( string $line ): string {
	$out = '';
	while ( strlen( $line ) > 73 ) {
		$out  .= substr( $line, 0, 73 ) . "\r\n ";
		$line  = substr( $line, 73 );
	}
	return $out . $line;
}

/** Escape the characters iCalendar treats as syntax. */
function esc_ics( string $text ): string {
	$text = wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
	$text = str_replace( array( "\\", ';', ',' ), array( "\\\\", '\;', '\,' ), $text );
	return str_replace( array( "\r\n", "\n", "\r" ), '\n', $text );
}

/**
 * A naive Perth datetime as UTC, the form calendars agree on.
 *
 * Stored times are Perth-local with no zone, so they are read in the site's
 * timezone and converted -- not relabelled, which would move a 6pm class.
 */
function utc( string $local ): string {
	$dt = date_create_immutable( $local, wp_timezone() );
	return $dt ? $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' ) : '';
}

function build( int $id ): string {
	$start = (string) get_post_meta( $id, 'event_start', true );
	if ( '' === $start ) {
		return '';
	}
	$end = (string) get_post_meta( $id, 'event_end', true );

	/*
	 * No end time recorded: give it 90 minutes rather than nothing. A
	 * zero-length entry collapses to a point in most calendars and tells
	 * the person nothing about their evening. The page states the truth.
	 */
	if ( '' === $end ) {
		$end = gmdate( 'Y-m-d H:i:s', (int) strtotime( $start . ' +90 minutes' ) );
	}

	$price  = (string) get_post_meta( $id, 'price', true );
	$venue  = (string) get_post_meta( $id, 'venue', true );
	$status = Events\status( $id );
	$link   = (string) get_permalink( $id );

	$body = wp_strip_all_tags( (string) get_post_meta( $id, 'event_description', true ) );
	$body = wp_trim_words( $body, 60, '…' );
	$desc = trim( $body . ( '' !== $price ? "\n\n" . sprintf( /* translators: %s: price */ __( 'Price: %s', 'oria' ), $price ) : '' ) . "\n\n" . $link );

	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Oria Haven//What\'s On//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'BEGIN:VEVENT',
		'UID:oria-event-' . $id . '@' . (string) wp_parse_url( home_url(), PHP_URL_HOST ),
		'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
		'DTSTART:' . utc( $start ),
		'DTEND:' . utc( $end ),
		fold( 'SUMMARY:' . esc_ics( get_the_title( $id ) ) ),
		fold( 'DESCRIPTION:' . esc_ics( $desc ) ),
		fold( 'URL:' . $link ),
	);

	if ( '' !== $venue ) {
		$lines[] = fold( 'LOCATION:' . esc_ics( $venue ) );
	}
	if ( 'cancelled' === $status ) {
		$lines[] = 'STATUS:CANCELLED';
	} elseif ( 'postponed' === $status ) {
		// No POSTPONED in the spec; TENTATIVE is the honest neighbour.
		$lines[] = 'STATUS:TENTATIVE';
	} else {
		$lines[] = 'STATUS:CONFIRMED';
	}

	$lines[] = 'END:VEVENT';
	$lines[] = 'END:VCALENDAR';

	return implode( "\r\n", $lines ) . "\r\n";
}

function serve(): void {
	if ( ! get_query_var( QUERY_VAR ) || ! is_singular( 'event' ) ) {
		return;
	}

	$id  = (int) get_queried_object_id();
	$ics = $id ? build( $id ) : '';
	if ( '' === $ics ) {
		return; // No start time: let the page render instead of a broken file.
	}

	/*
	 * Throw away anything already buffered. A stray byte-order mark from a
	 * BOM-saved PHP file lands in front of BEGIN:VCALENDAR and strict
	 * parsers reject the whole file for it -- wp-config.php on this host
	 * carries one, so this is not hypothetical.
	 */
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}

	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( get_post_field( 'post_name', $id ) ) . '.ics"' );
	echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput -- iCalendar body, escaped per field by esc_ics().
	exit;
}
