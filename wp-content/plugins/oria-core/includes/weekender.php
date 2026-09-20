<?php
/**
 * The Oria Weekender: the week's five, picked and rendered ready to send.
 *
 * Deliberately stops short of sending anything. No email provider has been
 * chosen, and building against the wrong one costs more than waiting. What
 * this does is the part that is the same whatever gets chosen: decide which
 * five events, and produce HTML to paste into it.
 *
 *     wp oria weekender            # the five, as a table
 *     wp oria weekender --html     # the email body
 *     wp oria weekender --html > weekender.html
 *
 * The five slots come from the brief, and each one is filled by rule rather
 * than by taste:
 *
 *   Oria's Pick      the soonest event from a practice that pays to be here
 *                    -- said plainly, because presenting paid placement as
 *                    an independent recommendation is the one thing the
 *                    brief is unambiguous about
 *   Something free   the soonest event priced free or by donation
 *   For a beginner   the soonest event whose host says beginners are welcome
 *   Something else   the soonest event of a type none of the others used
 *   From a member    the soonest event run by any claimed practice
 *
 * A slot that cannot be filled honestly is left out. Four real ones beat
 * five with a stretch in the middle.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Weekender;

use Oria\Core\Events;
use Oria\Core\Ownership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How far ahead the Weekender looks. */
const HORIZON_DAYS = 21;

function bootstrap(): void {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::add_command( 'oria weekender', __NAMESPACE__ . '\command' );
	}
}

/**
 * The five (or fewer), each as array{id,slot,why}.
 *
 * @return array<int, array<string, mixed>>
 */
function picks(): array {
	$horizon = (int) current_time( 'timestamp' ) + HORIZON_DAYS * DAY_IN_SECONDS;
	$ids     = array_values(
		array_filter(
			Events\upcoming( array( 'limit' => 60 ) ),
			static function ( int $id ) use ( $horizon ): bool {
				$start = strtotime( (string) get_post_meta( $id, 'event_start', true ) );
				return $start && $start <= $horizon;
			}
		)
	);
	if ( ! $ids ) {
		return array();
	}

	$used  = array();
	$picks = array();

	$take = static function ( callable $test, string $slot, string $why ) use ( $ids, &$used, &$picks ): void {
		foreach ( $ids as $id ) {
			if ( in_array( $id, $used, true ) || ! $test( $id ) ) {
				continue;
			}
			$used[]  = $id;
			$picks[] = array( 'id' => $id, 'slot' => $slot, 'why' => $why );
			return;
		}
	};

	$take(
		static function ( int $id ): bool {
			$host = (int) get_post_meta( $id, 'listing', true );
			return $host > 0 && function_exists( '\Oria\Core\Ownership\is_paid' ) && Ownership\is_paid( $host );
		},
		__( "Oria's Pick", 'oria' ),
		__( 'From a practice that pays to be listed with us — said plainly, because it is placement, not a review.', 'oria' )
	);

	$take(
		static function ( int $id ): bool {
			$price = (string) get_post_meta( $id, 'price', true );
			return '' !== trim( $price ) && (bool) preg_match( '/free|donation/i', $price );
		},
		__( 'Something free', 'oria' ),
		__( 'Free or by donation, as published by the organiser.', 'oria' )
	);

	$take(
		static function ( int $id ): bool {
			$host = (int) get_post_meta( $id, 'listing', true );
			return $host > 0 && taxonomy_exists( 'audience' ) && has_term( 'beginners', 'audience', $host );
		},
		__( 'For a first-timer', 'oria' ),
		__( 'The host says beginners are welcome, with a source behind it.', 'oria' )
	);

	$types = array();
	foreach ( $picks as $p ) {
		foreach ( wp_get_post_terms( (int) $p['id'], 'event_type', array( 'fields' => 'slugs' ) ) as $slug ) {
			$types[] = $slug;
		}
	}
	$take(
		static function ( int $id ) use ( $types ): bool {
			$mine = wp_get_post_terms( $id, 'event_type', array( 'fields' => 'slugs' ) );
			return ! is_wp_error( $mine ) && $mine && ! array_intersect( $mine, $types );
		},
		__( 'Something different', 'oria' ),
		__( 'A kind of event none of the others covers this week.', 'oria' )
	);

	$take(
		static function ( int $id ): bool {
			return (int) get_post_meta( $id, 'listing', true ) > 0;
		},
		__( 'From a listed practice', 'oria' ),
		__( 'Run by a practice with a profile on the site.', 'oria' )
	);

	return $picks;
}

/** One event as the facts the email needs. */
function facts( int $id ): array {
	$start = strtotime( (string) get_post_meta( $id, 'event_start', true ) );
	$host  = (int) get_post_meta( $id, 'listing', true );

	return array(
		'title' => wp_specialchars_decode( get_the_title( $id ), ENT_QUOTES ),
		'url'   => (string) get_permalink( $id ),
		'when'  => $start ? gmdate( 'D j M', $start ) . ( '00:00' !== gmdate( 'H:i', $start ) ? ', ' . gmdate( 'g.ia', $start ) : '' ) : '',
		'where' => (string) get_post_meta( $id, 'venue', true ),
		'price' => (string) get_post_meta( $id, 'price', true ),
		'host'  => $host ? wp_specialchars_decode( get_the_title( $host ), ENT_QUOTES ) : (string) get_post_meta( $id, '_oria_organiser', true ),
	);
}

/** The email body: plain, inline-styled, paste-ready. */
function html(): string {
	$picks = picks();
	if ( ! $picks ) {
		return '';
	}

	$out  = '<div style="max-width:560px;margin:0 auto;font-family:Georgia,serif;color:#1d2a26">';
	$out .= '<p style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#7a8a83;margin:0 0 4px">' . esc_html__( 'The Oria Weekender', 'oria' ) . '</p>';
	$out .= '<h1 style="font-size:24px;margin:0 0 18px">' . esc_html__( 'Five things worth leaving the house for', 'oria' ) . '</h1>';

	foreach ( $picks as $pick ) {
		$f    = facts( (int) $pick['id'] );
		$meta = implode( ' · ', array_filter( array( $f['when'], $f['where'], $f['price'] ) ) );

		$out .= '<div style="border-top:1px solid #e3e8e5;padding:16px 0">';
		$out .= '<p style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#9aa8a2;margin:0 0 6px">' . esc_html( (string) $pick['slot'] ) . '</p>';
		$out .= '<p style="margin:0 0 4px;font-size:18px"><a href="' . esc_url( $f['url'] ) . '" style="color:#1d2a26;text-decoration:none">' . esc_html( $f['title'] ) . '</a></p>';
		if ( '' !== $meta ) {
			$out .= '<p style="margin:0;font-size:14px;color:#5c6b65">' . esc_html( $meta ) . '</p>';
		}
		if ( '' !== $f['host'] ) {
			$out .= '<p style="margin:2px 0 0;font-size:14px;color:#5c6b65">' . esc_html( sprintf( /* translators: %s: organiser */ __( 'Run by %s', 'oria' ), $f['host'] ) ) . '</p>';
		}
		$out .= '</div>';
	}

	$out .= '<p style="border-top:1px solid #e3e8e5;padding-top:16px;font-size:13px;color:#7a8a83">';
	$out .= esc_html__( 'Times and prices are the organisers\' to change — the event page has the latest we have checked.', 'oria' );
	$out .= ' <a href="' . esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ) ) . '" style="color:#7a8a83">' . esc_html__( "See everything that's on", 'oria' ) . '</a>.';
	$out .= '</p></div>';

	return $out;
}

/* ------------------------------------------------------------------- cli */

/**
 * The week's Weekender picks.
 *
 * ## OPTIONS
 *
 * [--html]
 * : Print the email body instead of the summary table.
 *
 * ## EXAMPLES
 *
 *     wp oria weekender
 *     wp oria weekender --html > weekender.html
 */
function command( array $args, array $assoc ): void {
	$picks = picks();
	if ( ! $picks ) {
		\WP_CLI::warning( sprintf( 'No events in the next %d days to build a Weekender from.', HORIZON_DAYS ) );
		return;
	}

	if ( ! empty( $assoc['html'] ) ) {
		\WP_CLI::line( html() );
		return;
	}

	foreach ( $picks as $pick ) {
		$f = facts( (int) $pick['id'] );
		\WP_CLI::line( sprintf( '%-22s %s', (string) $pick['slot'], $f['title'] ) );
		\WP_CLI::line( sprintf( '%-22s %s', '', implode( ' · ', array_filter( array( $f['when'], $f['where'], $f['price'] ) ) ) ) );
		\WP_CLI::line( sprintf( '%-22s %s', '', (string) $pick['why'] ) );
		\WP_CLI::line( '' );
	}

	\WP_CLI::success(
		sprintf(
			'%d of 5 slots filled. Run again with --html for the email body.',
			count( $picks )
		)
	);
}
