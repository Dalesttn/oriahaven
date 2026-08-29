<?php
/**
 * Classes — the sessions a practice runs, and the days they run on.
 *
 * This replaces the free-text timetable. That version asked for one "when"
 * field and read the days back out of the prose, which worked but was a
 * guess: "Wednesdays and Saturdays" parsed correctly only because somebody
 * thought to handle plurals, and the next practice would write something
 * nobody had anticipated. Asking for the day directly is one more control on
 * the form and no guessing at all.
 *
 * The day is stored as ISO numbers so it sorts, filters and translates
 * without a lookup table in three places. A class can name more than one --
 * a Monday-and-Wednesday flow is one class, not two -- so the value is
 * always a list.
 *
 * Rows are shown in the order the practice entered them. They know which
 * session leads their week; sorting by day would overrule that for no
 * reason a visitor would notice.
 */

declare(strict_types=1);

namespace Oria\Core\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ISO day numbers, Monday first, matching gmdate( 'N' ). */
const DAYS = array(
	1 => 'Mon',
	2 => 'Tue',
	3 => 'Wed',
	4 => 'Thu',
	5 => 'Fri',
	6 => 'Sat',
	7 => 'Sun',
);

/** Full names, for the field itself where there is room for them. */
const DAY_NAMES = array(
	1 => 'Monday',
	2 => 'Tuesday',
	3 => 'Wednesday',
	4 => 'Thursday',
	5 => 'Friday',
	6 => 'Saturday',
	7 => 'Sunday',
);

/** The choices ACF offers on the day field. */
function day_choices(): array {
	return DAY_NAMES;
}

/**
 * The days one class row runs on.
 *
 * ACF hands back a list of strings from a multiple select, but a row saved
 * before the field was multiple, or written by an import, may be a bare
 * value. Both are read the same way, and anything outside 1-7 is dropped
 * rather than trusted.
 *
 * @param array<string, mixed> $row
 * @return list<int> ISO day numbers, ascending. Empty when none are set.
 */
function days_of( array $row ): array {
	$raw = $row['day'] ?? array();
	if ( ! is_array( $raw ) ) {
		$raw = array( $raw );
	}
	$out = array();
	foreach ( $raw as $d ) {
		$n = (int) $d;
		if ( isset( DAYS[ $n ] ) ) {
			$out[ $n ] = true;
		}
	}
	$days = array_keys( $out );
	sort( $days );
	return $days;
}

/**
 * Every day this set of classes mentions, so the filter offers only buttons
 * that would do something. A studio closed on Sundays never gets a Sunday
 * button that empties the list.
 *
 * Classes are the grouped shape -- each carries a 'sessions' list and the
 * days live on the sessions. A flat row with its own 'day' key still reads,
 * so anything older that reaches here degrades to the same answer.
 *
 * @param list<array<string, mixed>> $classes
 * @return list<int>
 */
function days_used( array $classes ): array {
	$all = array();
	foreach ( $classes as $class ) {
		$sessions = is_array( $class['sessions'] ?? null ) ? $class['sessions'] : array( $class );
		foreach ( $sessions as $session ) {
			foreach ( days_of( (array) $session ) as $d ) {
				$all[ $d ] = true;
			}
		}
	}
	$out = array_keys( $all );
	sort( $out );
	return $out;
}

/** "Mon" for 1. */
function label( int $day ): string {
	return (string) ( DAYS[ $day ] ?? '' );
}

/** "Monday" for 1. */
function full_label( int $day ): string {
	return (string) ( DAY_NAMES[ $day ] ?? '' );
}

/**
 * "Mon, Wed" — the days one class runs, written out.
 *
 * A class naming no day says so plainly rather than showing an empty cell:
 * "By arrangement" is a real answer and a blank is not.
 */
function day_summary( array $row ): string {
	$days = days_of( $row );
	if ( ! $days ) {
		return __( 'Any day', 'oria' );
	}
	return implode( ', ', array_map( __NAMESPACE__ . '\label', $days ) );
}

/* ------------------------------------------------------------- timetable */

/**
 * The listing's week as flat session rows, one per day-and-time:
 *
 *     { day: 1-7, time: 'HH:MM', title, with, mins: int, tone, free: bool }
 *
 * One source only: the Classes repeater on the listing, which is where the
 * practitioner or the admin keeps it current. kind/mins/free live on the
 * class row -- a class is free, or an event, as a whole -- and each of its
 * session rows inherits them.
 *
 * @return array<int, array{day: int, time: string, title: string, with: string, mins: int, tone: string, free: bool}>
 */
function timetable_for( int $post_id ): array {
	$flat = array();

	$classes = function_exists( 'get_field' ) ? get_field( 'classes', $post_id ) : null;
	foreach ( (array) ( $classes ?: array() ) as $row ) {
		if ( ! is_array( $row ) || '' === trim( (string) ( $row['title'] ?? '' ) ) ) {
			continue;
		}
		$tone = in_array( $row['kind'] ?? '', array( 'venue', 'event' ), true ) ? (string) $row['kind'] : 'class';
		foreach ( (array) ( $row['sessions'] ?? array() ) as $sess ) {
			if ( ! is_array( $sess ) ) {
				continue;
			}
			foreach ( days_of( $sess ) as $day ) {
				$flat[] = array(
					'day'   => (int) $day,
					'time'  => trim( (string) ( $sess['time'] ?? '' ) ),
					'title' => trim( (string) $row['title'] ),
					'with'  => trim( (string) ( $sess['with'] ?? '' ) ),
					'mins'  => max( 0, (int) ( $row['mins'] ?? 0 ) ),
					'tone'  => $tone,
					'free'  => ! empty( $row['free'] ),
				);
			}
		}
	}
	return sort_week( $flat );
}

/** Days in order, then times within the day. */
function sort_week( array $flat ): array {
	usort(
		$flat,
		static function ( array $a, array $b ): int {
			return ( $a['day'] <=> $b['day'] ) ?: ( time_minutes( $a['time'] ) <=> time_minutes( $b['time'] ) );
		}
	);
	return $flat;
}

/**
 * "9:30" -> 570, so times order numerically -- as strings, "9:30" sorts
 * after "18:30" and the morning class lands at the bottom of the day.
 * Takes the first h[:.]mm it can find, honours a trailing pm, and sends
 * anything unparseable to the end rather than to 6am.
 */
function time_minutes( string $time ): int {
	if ( ! preg_match( '/(\d{1,2})(?:[.:h](\d{2}))?/', $time, $m ) ) {
		return 24 * 60;
	}
	$h = (int) $m[1];
	$i = (int) ( $m[2] ?? 0 );
	if ( $h < 12 && preg_match( '/pm/i', $time ) ) {
		$h += 12;
	}
	return ( $h * 60 ) + $i;
}

