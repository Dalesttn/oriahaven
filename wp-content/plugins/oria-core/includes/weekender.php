<?php
/**
 * "Your Perth weekend": the Thursday email, picked and rendered ready to
 * paste into Klaviyo. Never sends anything -- a person reads the draft and
 * sends it from Klaviyo to the weekend list.
 *
 *     wp oria weekender                      # the picks, as a table
 *     wp oria weekender --html               # the email body
 *     wp oria weekender --text               # the plain-text version
 *     wp oria weekender --save               # both, to uploads/oria-weekend/
 *     wp oria weekender --pick=12,34,56 --free=78 --first=90 --offer=123
 *
 * Also previewed under Listings > Weekend email, same picks, same HTML.
 *
 * The weekend is this coming Friday to Sunday in Perth time -- the same
 * window /this-weekend/ shows, so the email and the page agree. The six
 * slots come from the brief, each filled by rule:
 *
 *   This weekend x3  events in the window, best-documented first (a stated
 *                    time, a stated price, an image), then soonest, no two
 *                    from the same organiser
 *   Free or low cost free, by donation, or a published price of $25 or less
 *   For a first-timer the host is tagged beginners-welcome, or the event
 *                    says so itself (beginners, no experience, all levels)
 *   A checked offer  a live offer with a checked date; expired ones are
 *                    already gone (Offers\sweep), and one ending before the
 *                    Friday is skipped
 *
 * Editorial selection always wins: --pick/--free/--first/--offer replace a
 * slot, provided the event is in the window or the offer is live. A slot
 * that cannot be filled honestly is left out and said so. Payment never
 * decides a pick. Nothing unknown is filled in: no time means "times on
 * the booking page", no price means "price on the booking page".
 *
 * Every link goes to an Oria Haven page with the same campaign tags
 * (utm_source=oria-weekend, utm_medium=email, utm_campaign=weekend-{friday},
 * utm_content={slot}); organisers' own booking links are never rewritten.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Weekender;

use Oria\Core\Events;
use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SLUG     = 'oria-weekend-email';
const LOW_COST = 25; // dollars, inclusive: "$25 or less"

function bootstrap(): void {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::add_command( 'oria weekender', __NAMESPACE__ . '\command' );
	}
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
}

/* ---------------------------------------------------------------- window */

/**
 * This coming weekend, Friday 00:00 to Sunday 23:59 Perth time, as naive
 * local timestamps (the way event_start is stored). From Friday on it is
 * the current weekend, from what is left of it.
 *
 * @return array{from:int, to:int, friday:string}
 */
function window( ?int $now = null ): array {
	$now    = $now ?? (int) current_time( 'timestamp' );
	$dow    = (int) gmdate( 'N', $now );
	$friday = $dow >= 5 ? $now - ( $dow - 5 ) * DAY_IN_SECONDS : $now + ( 5 - $dow ) * DAY_IN_SECONDS;
	return array(
		'from'   => max( $now, (int) strtotime( gmdate( 'Y-m-d 00:00:00', $friday ) ) ),
		'to'     => (int) strtotime( gmdate( 'Y-m-d 23:59:59', $friday + 2 * DAY_IN_SECONDS ) ),
		'friday' => gmdate( 'Y-m-d', $friday ),
	);
}

/** @return list<int> published, not cancelled, starting inside the window */
function weekend_events( array $w ): array {
	return array_map(
		'intval',
		Events\upcoming(
			array(
				'limit' => 80,
				'from'  => gmdate( 'Y-m-d H:i:s', $w['from'] ),
				'to'    => gmdate( 'Y-m-d H:i:s', $w['to'] ),
			)
		)
	);
}

/* ----------------------------------------------------------------- picks */

/** The lowest dollar figure in a price string, or null when there is none. */
function lowest_price( string $price ): ?float {
	if ( ! preg_match_all( '/\$\s*(\d+(?:\.\d{1,2})?)/', $price, $m ) ) {
		return null;
	}
	return (float) min( array_map( 'floatval', $m[1] ) );
}

function is_free_or_low( int $id ): bool {
	$price = trim( (string) get_post_meta( $id, 'price', true ) );
	if ( '' === $price ) {
		return false; // Unknown is not cheap.
	}
	if ( preg_match( '/\bfree\b|donation|koha/i', $price ) ) {
		return true;
	}
	$low = lowest_price( $price );
	return null !== $low && $low <= LOW_COST;
}

function suits_first_timers( int $id ): bool {
	$host = (int) get_post_meta( $id, 'listing', true );
	if ( $host > 0 && taxonomy_exists( 'audience' ) && has_term( 'beginners', 'audience', $host ) ) {
		return true;
	}
	$text = get_the_title( $id ) . ' ' . wp_strip_all_tags( (string) get_post_meta( $id, 'event_description', true ) );
	return (bool) preg_match( '/\bbeginners?\b|first[- ]timers?|no (?:prior )?experience|all levels|new to\b|never (?:tried|done)/i', $text );
}

/** How well an event is documented: a time, a price, an image. Higher first. */
function documented( int $id ): int {
	$start = (string) get_post_meta( $id, 'event_start', true );
	return ( '' !== $start && '00:00' !== substr( $start, 11, 5 ) ? 1 : 0 )
		+ ( '' !== trim( (string) get_post_meta( $id, 'price', true ) ) ? 1 : 0 )
		+ ( has_post_thumbnail( $id ) ? 1 : 0 );
}

/** Who runs it, for "no two from the same organiser". */
function organiser_key( int $id ): string {
	$host = (int) get_post_meta( $id, 'listing', true );
	if ( $host > 0 ) {
		return 'L' . $host;
	}
	$org = strtolower( trim( (string) get_post_meta( $id, '_oria_organiser', true ) ) );
	return '' !== $org ? 'O' . $org : 'E' . $id;
}

/**
 * Every live offer with a checked date, soonest-ending first; one ending
 * before the Friday would be gone before anybody could use it.
 *
 * @return list<array{id:int, offer:array}>
 */
function checked_offers( array $w ): array {
	if ( ! function_exists( '\Oria\Core\Offers\among' ) ) {
		return array();
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'offer_title' AND meta_value <> ''" ) );
	$out = array();
	foreach ( \Oria\Core\Offers\among( $ids, 50 ) as $row ) {
		$o = $row['offer'];
		if ( '' === (string) ( $o['checked'] ?? '' ) ) {
			continue;
		}
		$until = (string) ( $o['until'] ?? '' );
		if ( '' !== $until && strtotime( $until . ' 23:59:59' ) < $w['from'] ) {
			continue;
		}
		$out[] = $row;
	}
	usort( $out, static fn( $a, $b ): int => strcmp( (string) ( $a['offer']['until'] ?: '9999' ), (string) ( $b['offer']['until'] ?: '9999' ) ) );
	return $out;
}

/**
 * The picks, in email order. Each is array{kind:'event'|'offer', id:int,
 * slot:string, key:string, chosen:bool}; 'missing' lists slots left empty.
 *
 * @param array{pick?:list<int>, free?:int, first?:int, offer?:int} $choose
 * @return array{window:array, picks:list<array>, missing:list<string>, rejected:list<string>}
 */
function picks( array $choose = array(), ?array $w = null ): array {
	$w        = $w ?? window();
	$events   = weekend_events( $w );
	$in       = array_flip( $events );
	$used     = array();
	$orgs     = array();
	$picks    = array();
	$missing  = array();
	$rejected = array();

	$ranked = $events;
	usort(
		$ranked,
		static function ( int $a, int $b ): int {
			return array( -documented( $a ), (string) get_post_meta( $a, 'event_start', true ) )
				<=> array( -documented( $b ), (string) get_post_meta( $b, 'event_start', true ) );
		}
	);

	$valid = static function ( int $id, string $what ) use ( $in, &$used, &$rejected ): bool {
		if ( $id <= 0 ) {
			return false;
		}
		if ( ! isset( $in[ $id ] ) ) {
			$rejected[] = sprintf( '%s #%d is not a published event this weekend', $what, $id );
			return false;
		}
		return ! in_array( $id, $used, true );
	};
	$first_fit = static function ( callable $test ) use ( $ranked, &$used, &$orgs ): int {
		foreach ( $ranked as $id ) {
			if ( ! in_array( $id, $used, true ) && ! in_array( organiser_key( $id ), $orgs, true ) && $test( $id ) ) {
				return $id;
			}
		}
		return 0;
	};

	// Editor's choices first, so the automatic slots fill around them.
	$chosen_three = array_slice( array_map( 'intval', (array) ( $choose['pick'] ?? array() ) ), 0, 3 );
	$three        = array();
	foreach ( $chosen_three as $id ) {
		if ( $valid( $id, 'Pick' ) ) {
			$three[] = $id;
			$used[]  = $id;
			$orgs[]  = organiser_key( $id );
		}
	}
	$free_id  = (int) ( $choose['free'] ?? 0 );
	$free_id  = $valid( $free_id, 'Free pick' ) ? $free_id : 0;
	$first_id = (int) ( $choose['first'] ?? 0 );
	$first_id = $valid( $first_id, 'First-timer pick' ) && $first_id !== $free_id ? $first_id : 0;
	foreach ( array( $free_id, $first_id ) as $id ) {
		if ( $id ) {
			$used[] = $id;
			$orgs[] = organiser_key( $id );
		}
	}

	// The specific slots before the general ones, so a free or beginner
	// event is not spent on "this weekend" and leave its own slot empty.
	$free_id  = $free_id ?: $first_fit( __NAMESPACE__ . '\is_free_or_low' );
	if ( $free_id && ! in_array( $free_id, $used, true ) ) {
		$used[] = $free_id;
		$orgs[] = organiser_key( $free_id );
	}
	$first_id = $first_id ?: $first_fit( __NAMESPACE__ . '\suits_first_timers' );
	if ( $first_id && ! in_array( $first_id, $used, true ) ) {
		$used[] = $first_id;
		$orgs[] = organiser_key( $first_id );
	}
	while ( count( $three ) < 3 ) {
		$id = $first_fit( static fn(): bool => true );
		if ( ! $id ) {
			break;
		}
		$three[] = $id;
		$used[]  = $id;
		$orgs[]  = organiser_key( $id );
	}

	foreach ( $three as $i => $id ) {
		$picks[] = array( 'kind' => 'event', 'id' => $id, 'slot' => __( 'This weekend', 'oria' ), 'key' => 'pick-' . ( $i + 1 ), 'chosen' => in_array( $id, $chosen_three, true ) );
	}
	if ( count( $three ) < 3 ) {
		$missing[] = sprintf( 'This weekend: only %d of 3 (not enough separate organisers in the window)', count( $three ) );
	}
	if ( $free_id ) {
		$picks[] = array( 'kind' => 'event', 'id' => $free_id, 'slot' => __( 'Free or low cost', 'oria' ), 'key' => 'free', 'chosen' => $free_id === (int) ( $choose['free'] ?? 0 ) );
	} else {
		$missing[] = 'Free or low cost: nothing this weekend is free, by donation or published at $' . LOW_COST . ' or less';
	}
	if ( $first_id ) {
		$picks[] = array( 'kind' => 'event', 'id' => $first_id, 'slot' => __( 'For a first-timer', 'oria' ), 'key' => 'first', 'chosen' => $first_id === (int) ( $choose['first'] ?? 0 ) );
	} else {
		$missing[] = 'For a first-timer: no event this weekend says beginners are welcome';
	}

	$offers   = checked_offers( $w );
	$offer_id = (int) ( $choose['offer'] ?? 0 );
	$offer    = null;
	if ( $offer_id ) {
		foreach ( $offers as $row ) {
			if ( (int) $row['id'] === $offer_id ) {
				$offer = $row;
			}
		}
		if ( ! $offer ) {
			$rejected[] = sprintf( 'Offer on listing #%d is not live with a checked date', $offer_id );
		}
	}
	/*
	 * A paid promotion ticked for the newsletter takes the offer slot -- the
	 * editor's own --offer choice still wins -- and is labelled "Promoted
	 * offer", with the footer saying so. Only tick 'newsletter' on a
	 * promotion when this send is actually planned and authorised.
	 */
	$promoted = false;
	if ( ! $offer && function_exists( '\Oria\Core\Offers\promotion' ) ) {
		foreach ( $offers as $row ) {
			if ( \Oria\Core\Offers\promotion( (int) $row['id'], 'newsletter' ) ) {
				$offer    = $row;
				$promoted = true;
				break;
			}
		}
	}
	$offer = $offer ?? ( $offers[0] ?? null );
	if ( $offer ) {
		$picks[] = array( 'kind' => 'offer', 'id' => (int) $offer['id'], 'slot' => $promoted ? __( 'Promoted offer', 'oria' ) : __( 'A checked offer', 'oria' ), 'key' => $promoted ? 'offer-promoted' : 'offer', 'chosen' => (int) $offer['id'] === $offer_id, 'offer' => $offer['offer'], 'promoted' => $promoted );
	} else {
		$missing[] = 'A checked offer: no live offer with a checked date';
	}

	return array( 'window' => $w, 'picks' => $picks, 'missing' => $missing, 'rejected' => $rejected );
}

/* ----------------------------------------------------------------- facts */

/** A link to one of our pages, tagged for the campaign. */
function tag( string $url, string $friday, string $content ): string {
	return add_query_arg(
		array(
			'utm_source'   => 'oria-weekend',
			'utm_medium'   => 'email',
			'utm_campaign' => 'weekend-' . $friday,
			'utm_content'  => $content,
		),
		$url
	);
}

/** One pick as the facts the email needs, unknowns said as unknowns. */
function facts( array $pick ): array {
	$id = (int) $pick['id'];
	if ( 'offer' === $pick['kind'] ) {
		$o     = (array) $pick['offer'];
		$until = (string) ( $o['until'] ?? '' );
		return array(
			'title' => (string) ( $o['title'] ?? '' ),
			'url'   => (string) get_permalink( $id ),
			'meta'  => implode(
				' · ',
				array_filter(
					array(
						html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
						function_exists( '\Oria\Core\BestOf\suburb' ) ? \Oria\Core\BestOf\suburb( $id ) : '',
						'' !== $until ? sprintf( /* translators: %s: date */ __( 'until %s', 'oria' ), wp_date( 'j M', (int) strtotime( $until ) ) ) : '',
					)
				)
			),
			'note'  => sprintf( /* translators: %s: date */ __( 'Checked on the business’s own site, %s.', 'oria' ), wp_date( 'j M Y', (int) strtotime( (string) $o['checked'] ) ) ),
		);
	}
	$start = (string) get_post_meta( $id, 'event_start', true );
	$ts    = strtotime( $start );
	$price = trim( (string) get_post_meta( $id, 'price', true ) );
	$host  = (int) get_post_meta( $id, 'listing', true );
	$timed = $ts && '00:00' !== gmdate( 'H:i', $ts );
	return array(
		'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
		'url'   => (string) get_permalink( $id ),
		'meta'  => implode(
			' · ',
			array_filter(
				array(
					$ts ? gmdate( 'D j M', $ts ) . ( $timed ? ', ' . gmdate( 'g.ia', $ts ) : '' ) : '',
					(string) get_post_meta( $id, 'venue', true ),
					'' !== $price ? $price : '',
				)
			)
		),
		'note'  => implode(
			' ',
			array_filter(
				array(
					$host ? sprintf( /* translators: %s: organiser */ __( 'Run by %s.', 'oria' ), html_entity_decode( get_the_title( $host ), ENT_QUOTES, 'UTF-8' ) ) : '',
					! $timed ? __( 'Times on the booking page.', 'oria' ) : '',
					'' === $price ? __( 'Price on the booking page.', 'oria' ) : '',
				)
			)
		),
	);
}

/* ---------------------------------------------------------------- render */

/** Whether any pick in this draft is a paid promotion. */
function has_promoted( array $built ): bool {
	foreach ( (array) $built['picks'] as $p ) {
		if ( ! empty( $p['promoted'] ) ) {
			return true;
		}
	}
	return false;
}

/** The email body: inline-styled, single column, readable at 320px. */
function html( array $built ): string {
	$picks = $built['picks'];
	if ( ! $picks ) {
		return '';
	}
	$f      = $built['window']['friday'];
	$dates  = wp_date( 'j', (int) strtotime( $f ) ) . '–' . wp_date( 'j F', (int) strtotime( $f . ' +2 days' ) );
	$ink    = '#16302B';
	$soft   = '#5C6B65';
	$faint  = '#8A9792';
	$line   = '#E3E1D9';

	$out  = '<div style="max-width:560px;margin:0 auto;padding:0 16px;font-family:Helvetica,Arial,sans-serif;color:' . $ink . ';line-height:1.5">';
	$out .= '<p style="font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:' . $faint . ';margin:0 0 6px">' . esc_html__( 'Your Perth weekend', 'oria' ) . ' · ' . esc_html( $dates ) . '</p>';
	$out .= '<h1 style="font-family:Georgia,serif;font-weight:normal;font-size:26px;line-height:1.2;margin:0 0 20px">' . esc_html__( 'A few good things to do for yourself this weekend', 'oria' ) . '</h1>';

	$last = '';
	foreach ( $picks as $pick ) {
		$x    = facts( $pick );
		$href = tag( $x['url'], $f, (string) $pick['key'] );
		$out .= '<div style="border-top:1px solid ' . $line . ';padding:16px 0">';
		if ( $pick['slot'] !== $last ) {
			$out .= '<p style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:' . $faint . ';margin:0 0 6px">' . esc_html( (string) $pick['slot'] ) . '</p>';
			$last = (string) $pick['slot'];
		}
		$out .= '<p style="margin:0 0 4px;font-size:18px;line-height:1.3"><a href="' . esc_url( $href ) . '" style="color:' . $ink . ';text-decoration:underline">' . esc_html( $x['title'] ) . '</a></p>';
		if ( '' !== $x['meta'] ) {
			$out .= '<p style="margin:0;font-size:14px;color:' . $soft . '">' . esc_html( $x['meta'] ) . '</p>';
		}
		if ( '' !== $x['note'] ) {
			$out .= '<p style="margin:4px 0 0;font-size:13px;color:' . $faint . '">' . esc_html( $x['note'] ) . '</p>';
		}
		$out .= '</div>';
	}

	$out .= '<p style="border-top:1px solid ' . $line . ';padding-top:16px;margin:0 0 8px;font-size:15px"><a href="' . esc_url( tag( home_url( '/this-weekend/' ), $f, 'all' ) ) . '" style="color:' . $ink . '">' . esc_html__( 'Everything on in Perth this weekend', 'oria' ) . ' &rarr;</a></p>';
	$out .= '<p style="margin:0 0 16px;font-size:12px;color:' . $faint . '">' . esc_html( has_promoted( $built ) ? __( 'Picked by Oria Haven, except the promoted offer, which the business paid to place here and which we checked like the rest. Times and prices are the organisers’ to change, so check the booking page before you go.', 'oria' ) : __( 'Picked by Oria Haven; nobody pays to be in this email. Times and prices are the organisers’ to change, so check the booking page before you go.', 'oria' ) ) . '</p>';
	// Klaviyo fills this tag with the recipient's own unsubscribe link.
	$out .= '<p style="margin:0 0 24px;font-size:12px;color:' . $faint . '">' . esc_html__( 'You asked for this at oriahaven.com.au.', 'oria' ) . ' {% unsubscribe %}</p>';
	return $out . '</div>';
}

/** The plain-text part, for clients that will not show HTML. */
function text( array $built ): string {
	$f   = $built['window']['friday'];
	$out = __( 'YOUR PERTH WEEKEND', 'oria' ) . "\n" . __( 'A few good things to do for yourself this weekend', 'oria' ) . "\n";
	$last = '';
	foreach ( $built['picks'] as $pick ) {
		$x = facts( $pick );
		if ( $pick['slot'] !== $last ) {
			$out .= "\n" . strtoupper( (string) $pick['slot'] ) . "\n";
			$last = (string) $pick['slot'];
		}
		$out .= '- ' . $x['title'] . "\n";
		$out .= '' !== $x['meta'] ? '  ' . $x['meta'] . "\n" : '';
		$out .= '' !== $x['note'] ? '  ' . $x['note'] . "\n" : '';
		$out .= '  ' . tag( $x['url'], $f, (string) $pick['key'] ) . "\n";
	}
	$out .= "\n" . __( 'Everything on this weekend:', 'oria' ) . ' ' . tag( home_url( '/this-weekend/' ), $f, 'all' ) . "\n";
	$out .= ( has_promoted( $built ) ? __( 'Picked by Oria Haven, except the promoted offer, which the business paid to place here. Check the booking page before you go.', 'oria' ) : __( 'Picked by Oria Haven; nobody pays to be in this email. Check the booking page before you go.', 'oria' ) ) . "\n";
	return $out . '{% unsubscribe %}' . "\n";
}

/** Writes both versions to uploads/oria-weekend/; returns the paths. */
function save( array $built ): array {
	$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'oria-weekend/';
	wp_mkdir_p( $dir );
	if ( ! file_exists( $dir . 'index.html' ) ) {
		file_put_contents( $dir . 'index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
	$base = $dir . 'weekend-' . $built['window']['friday'];
	file_put_contents( $base . '.html', html( $built ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( $base . '.txt', text( $built ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	return array( $base . '.html', $base . '.txt' );
}

/* ------------------------------------------------------------------- cli */

/**
 * Draft "Your Perth weekend" for this coming weekend. Sends nothing.
 *
 * ## OPTIONS
 *
 * [--html]
 * : Print the email body.
 *
 * [--text]
 * : Print the plain-text version.
 *
 * [--save]
 * : Write both to wp-content/uploads/oria-weekend/.
 *
 * [--pick=<ids>]
 * : Up to three event IDs for "This weekend", comma-separated.
 *
 * [--free=<id>]
 * : Event ID for "Free or low cost".
 *
 * [--first=<id>]
 * : Event ID for "For a first-timer".
 *
 * [--offer=<listing-id>]
 * : The listing whose checked offer to feature.
 *
 * ## EXAMPLES
 *
 *     wp oria weekender
 *     wp oria weekender --pick=901261,901170 --save
 */
function command( array $args, array $assoc ): void {
	$built = picks(
		array(
			'pick'  => array_filter( array_map( 'intval', explode( ',', (string) ( $assoc['pick'] ?? '' ) ) ) ),
			'free'  => (int) ( $assoc['free'] ?? 0 ),
			'first' => (int) ( $assoc['first'] ?? 0 ),
			'offer' => (int) ( $assoc['offer'] ?? 0 ),
		)
	);
	foreach ( $built['rejected'] as $r ) {
		\WP_CLI::warning( $r . ' -- filled automatically instead.' );
	}
	if ( ! $built['picks'] ) {
		\WP_CLI::warning( 'Nothing to send: no events this weekend and no checked offer.' );
		return;
	}
	if ( ! empty( $assoc['html'] ) ) {
		\WP_CLI::line( html( $built ) );
		return;
	}
	if ( ! empty( $assoc['text'] ) ) {
		\WP_CLI::line( text( $built ) );
		return;
	}
	\WP_CLI::line( sprintf( 'Weekend of %s (Perth)', $built['window']['friday'] ) );
	foreach ( $built['picks'] as $pick ) {
		$x = facts( $pick );
		\WP_CLI::line( sprintf( '%-18s #%-7d %s%s', (string) $pick['slot'], (int) $pick['id'], $x['title'], $pick['chosen'] ? '  (chosen)' : '' ) );
		\WP_CLI::line( sprintf( '%-27s %s', '', $x['meta'] ) );
	}
	foreach ( $built['missing'] as $m ) {
		\WP_CLI::line( 'EMPTY  ' . $m );
	}
	if ( ! empty( $assoc['save'] ) ) {
		foreach ( save( $built ) as $path ) {
			\WP_CLI::line( 'saved  ' . $path );
		}
	}
	\WP_CLI::success( 'Draft only -- nothing was sent. Paste the HTML into a Klaviyo campaign for the weekend list.' );
}

/* ----------------------------------------------------------------- admin */

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . PostTypes\LISTING,
		__( 'Weekend email', 'oria' ),
		__( 'Weekend email', 'oria' ),
		'manage_options',
		SLUG,
		__NAMESPACE__ . '\render'
	);
}

/** The draft for this coming weekend, previewed, with the HTML to copy. Sends nothing. */
function render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$built = picks();
	$html  = html( $built );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Your Perth weekend: this week’s draft', 'oria' ); ?></h1>
		<p><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Weekend starting Friday %s. Nothing is sent from here: copy the HTML into a Klaviyo campaign for the weekend list, check it, then send it from Klaviyo. To swap a pick, use wp oria weekender with --pick, --free, --first or --offer.', 'oria' ), wp_date( 'j F', (int) strtotime( $built['window']['friday'] ) ) ) ); ?></p>
		<?php if ( $built['missing'] ) : ?>
			<div class="notice notice-warning inline"><p><?php echo esc_html( implode( ' · ', $built['missing'] ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( '' === $html ) : ?>
			<p><?php esc_html_e( 'Nothing to send this week: no events in the weekend window and no checked offer.', 'oria' ); ?></p>
		<?php else : ?>
			<div style="display:grid;grid-template-columns:minmax(0,600px) minmax(0,1fr);gap:24px;align-items:start;margin-top:16px">
				<div style="background:#fff;border:1px solid #dcdcde;padding:16px 0"><?php echo wp_kses_post( $html ); ?></div>
				<div>
					<p><label for="oria-wk-html"><strong><?php esc_html_e( 'HTML', 'oria' ); ?></strong></label></p>
					<textarea id="oria-wk-html" readonly rows="16" style="width:100%;font-family:monospace;font-size:12px" onfocus="this.select()"><?php echo esc_textarea( $html ); ?></textarea>
					<p><label for="oria-wk-text"><strong><?php esc_html_e( 'Plain text', 'oria' ); ?></strong></label></p>
					<textarea id="oria-wk-text" readonly rows="12" style="width:100%;font-family:monospace;font-size:12px" onfocus="this.select()"><?php echo esc_textarea( text( $built ) ); ?></textarea>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
