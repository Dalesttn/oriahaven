<?php
/**
 * Which days a timetable row runs on.
 *
 * The "when" field is free text and stays that way — a practice writes
 * "Mon–Fri 6.30am" or "Sat & Sun, 8am" the way it appears on their own
 * timetable, and asking them to pick days from a dropdown as well would be
 * the same information typed twice, with two chances to disagree.
 *
 * So the day is read out of what they wrote. Ranges, lists, and the words
 * people use instead of days all resolve to the same set of numbers, and the
 * filter on the page reads those rather than the prose.
 *
 * A row that names no day — "By appointment", "Daily, times vary" without a
 * day word — returns an empty set, and the template shows it under every
 * filter rather than hiding it. Somebody filtering to Tuesday still needs to
 * know the place takes appointments.
 */

declare(strict_types=1);

namespace Oria\Core\Timetable;

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

/**
 * Every spelling of a day that turns up in a hand-written timetable.
 *
 * Longest first where one is a prefix of another, so "thursday" is not
 * matched as "thu" with "rsday" left over — harmless here, but it keeps the
 * offsets honest for the range logic below.
 *
 * @return array<string, int>
 */
function tokens(): array {
	return array(
		'monday'    => 1, 'mondays'    => 1, 'mon' => 1,
		'tuesday'   => 2, 'tuesdays'   => 2, 'tues' => 2, 'tue' => 2,
		'wednesday' => 3, 'wednesdays' => 3, 'weds' => 3, 'wed' => 3,
		'thursday'  => 4, 'thursdays'  => 4, 'thurs' => 4, 'thur' => 4, 'thu' => 4,
		'friday'    => 5, 'fridays'    => 5, 'fri' => 5,
		'saturday'  => 6, 'saturdays'  => 6, 'sat' => 6,
		'sunday'    => 7, 'sundays'    => 7, 'sun' => 7,
	);
}

/**
 * The days a "when" string covers.
 *
 * @return list<int> ISO day numbers, ascending. Empty when none are named.
 */
function days_in( string $when ): array {
	$s = strtolower( wp_specialchars_decode( $when, ENT_QUOTES ) );

	// Every kind of dash people type for a range, plus "to", become one token.
	$s = str_replace( array( '–', '—', '−' ), '-', $s );
	$s = (string) preg_replace( '/\s*(?:-|\bto\b|\bthru\b|\bthrough\b)\s*/', '-', $s );

	$found = array();

	// The words used instead of days.
	if ( preg_match( '/\b(every ?day|daily|all week)\b/', $s ) ) {
		$found = array( 1, 2, 3, 4, 5, 6, 7 );
	}
	if ( preg_match( '/\bweek ?days?\b/', $s ) && ! preg_match( '/\bweek ?ends?\b/', $s ) ) {
		$found = array_merge( $found, array( 1, 2, 3, 4, 5 ) );
	}
	if ( preg_match( '/\bweek ?ends?\b/', $s ) ) {
		$found = array_merge( $found, array( 6, 7 ) );
	}

	$map   = tokens();
	$names = implode( '|', array_keys( $map ) );

	// Ranges first, so "mon-fri" is not read as two separate days.
	if ( preg_match_all( '/\b(' . $names . ')-(' . $names . ')\b/', $s, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			$a = $map[ $hit[1] ];
			$b = $map[ $hit[2] ];
			// A range that wraps the weekend — "sat-mon" — is still a range.
			for ( $i = 0; $i < 7; $i++ ) {
				$d = ( ( $a - 1 + $i ) % 7 ) + 1;
				$found[] = $d;
				if ( $d === $b ) {
					break;
				}
			}
		}
		$s = (string) preg_replace( '/\b(' . $names . ')-(' . $names . ')\b/', ' ', $s );
	}

	// Then anything left standing on its own.
	if ( preg_match_all( '/\b(' . $names . ')\b/', $s, $m ) ) {
		foreach ( $m[1] as $name ) {
			$found[] = $map[ $name ];
		}
	}

	$found = array_values( array_unique( array_map( 'intval', $found ) ) );
	sort( $found );
	return $found;
}

/**
 * The days any row of this timetable mentions, so the filter offers only
 * buttons that would do something.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<int>
 */
function days_used( array $rows ): array {
	$all = array();
	foreach ( $rows as $row ) {
		foreach ( days_in( (string) ( $row['when'] ?? '' ) ) as $d ) {
			$all[ $d ] = true;
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
