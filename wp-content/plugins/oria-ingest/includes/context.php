<?php
/**
 * What else is on, and what a visitor can be told about an event.
 *
 * Two jobs, one file, because they answer the same question from different
 * sides: someone looking at an event page wants to know whether this one
 * suits them, and if not, what else there is.
 *
 * The signals are derived, never invented. Every one traces to a fact already
 * stored — the price field, the start and end times, the format, or an
 * audience tag on the linked practice that was only applied against a source
 * and a quote. "Good for going solo" and "relaxing" are not in here, however
 * useful they would be on a card: nothing in the data supports them, and a
 * directory that starts guessing on behalf of a practitioner has given up the
 * one thing that makes it worth reading.
 */

declare(strict_types=1);

namespace Oria\Ingest\Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Events still to happen, cheapest query first. */
function upcoming_args(): array {
	return array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => 'event_start',
				'value'   => current_time( 'Y-m-d H:i:s' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
		),
	);
}

/**
 * Other events worth offering next to this one.
 *
 * Ranked rather than filtered, because a hard filter on type would show
 * nothing on a night when the only other event is a different kind — and an
 * empty "you might also like" is worse than a loosely related one. Same type
 * scores highest, then same suburb, then same host, and everything else falls
 * back on being sooner.
 *
 * @return list<int> Post IDs, best first.
 */
function similar( int $event_id, int $limit = 4 ): array {
	$ids = get_posts( upcoming_args() );
	if ( ! $ids ) {
		return array();
	}

	$types = wp_get_post_terms( $event_id, 'event_type', array( 'fields' => 'ids' ) );
	$types = is_wp_error( $types ) ? array() : $types;
	$venue = strtolower( trim( (string) get_field( 'venue', $event_id ) ) );
	$host  = (int) get_field( 'listing', $event_id );

	$scored = array();
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id === $event_id ) {
			continue;
		}
		$score = 0;

		$t = wp_get_post_terms( $id, 'event_type', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $t ) && array_intersect( $types, $t ) ) {
			$score += 4;
		}
		$v = strtolower( trim( (string) get_field( 'venue', $id ) ) );
		if ( '' !== $venue && '' !== $v && $v === $venue ) {
			$score += 2;
		}
		if ( $host && (int) get_field( 'listing', $id ) === $host ) {
			$score += 3;
		}
		$scored[] = array( 'id' => $id, 'score' => $score );
	}

	// Already ordered by start date, so a stable sort on score alone keeps
	// "sooner" as the tiebreak without having to compare dates again.
	usort( $scored, static fn( array $a, array $b ): int => $b['score'] <=> $a['score'] );

	return array_slice( wp_list_pluck( $scored, 'id' ), 0, max( 0, $limit ) );
}

/**
 * The price as a number, or null when it is not stated as one.
 *
 * The field is free text off somebody else's ticketing page: "$35", "From
 * $25", "Free", "By donation", "TBC". Anything that does not clearly carry a
 * dollar figure returns null rather than a guess, so "TBC" never becomes $0
 * and never lands in an Under $30 shelf.
 */
function price_amount( int $event_id ): ?float {
	$raw = trim( (string) get_field( 'price', $event_id ) );
	if ( '' === $raw ) {
		return null;
	}
	if ( preg_match( '/\b(free|no charge|gold coin|by donation|donation)\b/i', $raw ) ) {
		return 0.0;
	}
	if ( preg_match( '/\$\s*([0-9]+(?:\.[0-9]{1,2})?)/', $raw, $m ) ) {
		return (float) $m[1];
	}
	return null;
}

/**
 * Short factual chips for an event card or page.
 *
 * Each entry is array{ label, why } — why being the fact it came from, so a
 * template can show it on hover and anybody auditing this can see there is
 * no invention in it.
 *
 * @return list<array{label: string, why: string}>
 */
function signals( int $event_id ): array {
	$out = array();

	$amount = price_amount( $event_id );
	if ( null !== $amount ) {
		if ( 0.0 === $amount ) {
			$out[] = array(
				'label' => __( 'Free or by donation', 'oria' ),
				'why'   => __( 'The organiser lists no ticket price.', 'oria' ),
			);
		} elseif ( $amount < 30 ) {
			$out[] = array(
				'label' => __( 'Under $30', 'oria' ),
				'why'   => sprintf( __( 'Listed at $%s.', 'oria' ), rtrim( rtrim( number_format( $amount, 2 ), '0' ), '.' ) ),
			);
		}
	}

	$start = (string) get_field( 'event_start', $event_id );
	$end   = (string) get_field( 'event_end', $event_id );
	if ( '' !== $start && '' !== $end ) {
		$mins = (int) round( ( strtotime( $end ) - strtotime( $start ) ) / 60 );
		if ( $mins > 0 && $mins <= 24 * 60 ) {
			$out[] = array(
				'label' => $mins >= 90
					? sprintf( __( '%s hours', 'oria' ), rtrim( rtrim( number_format( $mins / 60, 1 ), '0' ), '.' ) )
					: sprintf( __( '%d minutes', 'oria' ), $mins ),
				'why'   => __( 'From the start and end times on the listing.', 'oria' ),
			);
		}
	}

	/*
	 * Beginner friendly is the linked practice's audience tag, which is only
	 * ever applied with a source URL and a quote behind it — see
	 * Oria\Core\Audience. It is a statement about the practice, so it is
	 * worded as one; the event itself has said nothing.
	 */
	$host = (int) get_field( 'listing', $event_id );
	if ( $host && taxonomy_exists( 'audience' ) && has_term( 'beginners', 'audience', $host ) ) {
		$out[] = array(
			'label' => __( 'Beginner friendly', 'oria' ),
			'why'   => sprintf(
				/* translators: %s: the practice running the event */
				__( '%s says beginners are welcome.', 'oria' ),
				get_the_title( $host )
			),
		);
	}

	return $out;
}
