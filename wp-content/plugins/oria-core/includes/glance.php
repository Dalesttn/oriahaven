<?php
/**
 * "At a glance": the presentation model behind an activity listing's
 * joining details.
 *
 * The template draws what this returns and decides nothing itself. Three
 * rules shape it.
 *
 * A SCHEDULE IS ONLY TURNED INTO A TIMETABLE WHEN IT SAYS SO EXPLICITLY.
 * The text an editor stores is prose. It becomes rows only when every part
 * of it matches one strict pattern -- "Summer (Sept-April): weekdays 6:15am,
 * weekends 7am." -- and the moment any part does not, the whole schedule is
 * shown as the words that were stored. Splitting prose on commas would
 * invent structure; a timetable that is wrong is worse than a sentence.
 *
 * A SEASON IS ONLY CHOSEN WHEN THE DATES ALLOW IT. Explicit month ranges,
 * resolved in Perth time, that do not overlap. In the last week before a
 * season changes both are shown open, because a cached page can outlive
 * the month it was drawn in. Anything uncertain shows every season.
 *
 * NOTHING IS SAID THAT THE DATA DOES NOT SAY. Unknown supervision is not
 * "no supervision"; a missing price is not free; a detail absent is a row
 * absent. The amber notice appears only for a stated absence of
 * supervision, attributed to the organiser.
 *
 * @package OriaCore
 */

declare(strict_types=1);

namespace Oria\Core\Glance;

use Oria\Core\Sources;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MONTHS = array(
	'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
	'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
);

const DAY_LABELS = '(?:weekdays|weekends|weekdays and weekends|daily|every day|mondays?|tuesdays?|wednesdays?|thursdays?|fridays?|saturdays?|sundays?|public holidays)';

const TIME_RE = '\d{1,2}(?::\d{2})?\s?(?:am|pm)';

/**
 * The glance model for a listing, or null when there is nothing to show.
 *
 * @return array<string, mixed>|null
 */
function model( int $id, ?\DateTimeImmutable $now = null ): ?array {
	if ( ! function_exists( '\Oria\Core\Sources\joining' ) ) {
		return null;
	}
	$j   = Sources\joining( $id );
	$cat = category_for( $id );
	$cfg = (array) ( $cat['glance'] ?? array() );

	// Unknown is not a cost to print: it is the absence of one.
	if ( 'unknown' === $j['cost'] ) {
		$j['cost'] = '';
	}

	$details = array();
	foreach ( $j['details'] as $d ) {
		$details[ (string) $d['label'] ] = (string) $d['value'];
	}

	// The supervision line is a notice or a preparation item, never a fact.
	$notice      = null;
	$supervision = $details['Supervision'] ?? '';
	unset( $details['Supervision'] );
	if ( '' !== $supervision && preg_match( '/^\s*(none|no)\b/i', $supervision ) ) {
		$body   = trim( (string) preg_replace( '/^\s*(none|no)\s*(--|—|-|:)?\s*/i', '', $supervision ) );
		$body   = trim( (string) preg_replace( '/\s*\(organiser\'s words\)\s*$/i', '', $body ) );
		$notice = array(
			'heading' => __( 'No organised supervision', 'oria' ),
			'body'    => '' !== $body
				/* translators: %s: what the organiser says, e.g. no water safety or lifeguards */
				? sprintf( __( 'The organiser states: %s.', 'oria' ), rtrim( $body, '.' ) )
				: __( 'The organiser states that no supervision is provided.', 'oria' ),
		);
	}

	$schedule = schedule( (string) $j['schedule'], $now );

	/* Summary facts: the category's short details, in its own order, with
	   the recurrence the schedule states outright ("Every day."). */
	$facts = array();
	foreach ( (array) ( $cfg['summary'] ?? array() ) as $want ) {
		if ( 'cost' === $want ) {
			if ( '' !== $j['price_note'] && strlen( $j['price_note'] ) <= 45 ) {
				$facts[]         = fact( $j['price_note'], 'tag' );
				$j['price_note'] = '';
			} elseif ( '' !== $j['cost'] && '' === $j['price_note'] ) {
				$facts[] = fact( Sources\cost_label( $j['cost'] ), 'tag' );
			}
			continue;
		}
		if ( 'recurrence' === $want ) {
			if ( '' !== $schedule['recurrence'] ) {
				$facts[] = fact( $schedule['recurrence'], 'calendar' );
			}
			continue;
		}
		if ( isset( $details[ $want ] ) ) {
			$facts[] = fact( $details[ $want ], 'distance' === strtolower( $want ) ? 'route' : 'dot', $want );
			unset( $details[ $want ] );
		}
	}

	/* Before you join: the remaining details, then what the organiser said
	   about who it suits and what it costs. Equipment reads as what to bring. */
	// Per category: "What to bring" suits swimmers' fins, not a venue's paddles.
	$rename = (array) ( $cfg['rename'] ?? array() );
	$prep   = array();
	foreach ( $details as $label => $value ) {
		$prep[] = array( 'label' => (string) ( $rename[ $label ] ?? $label ), 'text' => $value );
	}
	if ( 'yes' === $j['beginner'] ) {
		$prep[] = array( 'label' => __( 'Experience', 'oria' ), 'text' => __( 'The organiser says beginners are welcome.', 'oria' ) );
	}
	if ( 'yes' === $j['come_alone'] ) {
		$prep[] = array( 'label' => __( 'Coming alone', 'oria' ), 'text' => __( 'The organiser says newcomers on their own are welcome.', 'oria' ) );
	}
	if ( '' !== $j['price_note'] ) {
		$prep[] = array( 'label' => __( 'Price', 'oria' ), 'text' => $j['price_note'] );
	} elseif ( '' !== $j['cost'] && ! in_array( 'cost', (array) ( $cfg['summary'] ?? array() ), true ) ) {
		$prep[] = array( 'label' => __( 'Cost', 'oria' ), 'text' => Sources\cost_label( $j['cost'] ) );
	}
	// Water activities: saying nothing about supervision reads as reassurance.
	if ( ! empty( $cfg['ask_supervision'] ) && '' === $supervision ) {
		$prep[] = array( 'label' => __( 'Supervision', 'oria' ), 'text' => __( 'Supervision details not provided — check with the organiser.', 'oria' ) );
	} elseif ( '' !== $supervision && null === $notice ) {
		$prep[] = array( 'label' => __( 'Supervision', 'oria' ), 'text' => $supervision );
	}

	if ( $j['meeting_varies'] ) {
		$location = __( 'Locations vary — check with the organiser', 'oria' );
	} else {
		$location = '' !== $j['meeting'] ? $j['meeting'] : suburb_name( $id );
	}

	$model = array(
		'eyebrow'    => (string) ( $cfg['eyebrow'] ?? __( 'Your activity at a glance', 'oria' ) ),
		'heading'    => (string) ( $cfg['heading'] ?? __( 'What to know before you go', 'oria' ) ),
		'when_label' => (string) ( $cfg['when'] ?? __( 'When to meet', 'oria' ) ),
		'location'   => $location,
		'facts'      => $facts,
		'schedule'   => $schedule,
		'prep'       => $prep,
		'notice'     => $notice,
		'join'       => array( 'url' => $j['url'], 'method' => $j['method'] ),
		'checked'    => $j['checked'],
		'source_url' => $j['source_url'],
	);

	$empty = ! $facts && ! $schedule['seasons'] && ! $schedule['lines'] && ! $prep && ! $notice && '' === $j['url'] && '' === $location;
	return $empty ? null : $model;
}

/** @return array{text: string, strong: string, rest: string, icon: string} */
function fact( string $value, string $icon, string $label = '' ): array {
	// A leading figure with its unit is the emphasis: "2km" of "2km return".
	$strong = '';
	$rest   = $value;
	if ( preg_match( '/^([\d.,]+(?:\s?[-–]\s?[\d.,]+)?\s?(?:km|m|min|mins|minutes|hours?|hrs?|h)\b)(.*)$/iu', $value, $m ) ) {
		$strong = trim( $m[1] );
		$rest   = $m[2];
	}
	return array( 'text' => $value, 'strong' => $strong, 'rest' => $rest, 'icon' => $icon, 'label' => $label );
}

/** The source category a listing belongs to, from its primary category first. */
function category_for( int $id ): ?array {
	if ( ! function_exists( '\Oria\Core\Sources\plan' ) ) {
		return null;
	}
	$primary = function_exists( '\Oria\Core\Primary\of' ) ? (string) \Oria\Core\Primary\of( $id ) : '';
	$plan    = Sources\plan();
	foreach ( $plan as $row ) {
		if ( 'practice' === $row['taxonomy'] && $primary === $row['slug'] ) {
			return $row;
		}
	}
	// Then a service category (walking groups), then any practice match.
	foreach ( array( 'service', 'practice' ) as $tax ) {
		foreach ( $plan as $row ) {
			if ( $tax === $row['taxonomy'] && has_term( (string) $row['slug'], $tax, $id ) ) {
				return $row;
			}
		}
	}
	return null;
}

function suburb_name( int $id ): string {
	$terms = get_the_terms( $id, Taxonomies\AREA );
	if ( ! is_array( $terms ) ) {
		return '';
	}
	foreach ( $terms as $t ) {
		// A suburb sits under a region under a city: two ancestors.
		if ( 2 === count( get_ancestors( $t->term_id, Taxonomies\AREA, 'taxonomy' ) ) ) {
			return wp_specialchars_decode( $t->name, ENT_QUOTES );
		}
	}
	return '';
}

/* ------------------------------------------------------------ schedules */

/**
 * Read a stored schedule.
 *
 * @return array{recurrence: string, seasons: array<int, array<string, mixed>>, open_all: bool, lines: array<int, string>, notes: array<int, string>}
 */
function schedule( string $text, ?\DateTimeImmutable $now = null ): array {
	$out  = array( 'recurrence' => '', 'seasons' => array(), 'open_all' => false, 'lines' => array(), 'notes' => array() );
	$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	if ( '' === $text ) {
		return $out;
	}

	$parsed = parse_seasons( $text );
	if ( null === $parsed ) {
		// Prose, kept as written. A semicolon list gets a line per entry --
		// the same words, nothing read into them.
		$parts        = array_values( array_filter( array_map( 'trim', explode( '; ', $text ) ) ) );
		$out['lines'] = count( $parts ) > 1 ? $parts : array( $text );
		return $out;
	}

	$out['recurrence'] = $parsed['lead'];
	$out['notes']      = $parsed['notes'];
	$pick              = select_season( $parsed['seasons'], $now ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'Australia/Perth' ) ) );
	$out['open_all']   = null === $pick;
	foreach ( $parsed['seasons'] as $i => $s ) {
		$s['current']     = $i === $pick;
		$out['seasons'][] = $s;
	}
	// The current season first; the rest follow in the stored order.
	usort( $out['seasons'], static fn( array $a, array $b ): int => (int) $b['current'] <=> (int) $a['current'] );
	return $out;
}

/**
 * Seasons, but only when every part of the text matches the pattern.
 *
 * @return array{lead: string, seasons: array<int, array<string, mixed>>, notes: array<int, string>}|null
 */
function parse_seasons( string $text ): ?array {
	$block = '/([A-Z][a-z]+)\s*\(\s*([A-Za-z]{3,9})\.?\s*[-–]\s*([A-Za-z]{3,9})\.?\s*\)\s*:\s*([^.]+)\./u';
	if ( ! preg_match_all( $block, $text, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	$first   = (int) $mm[0][0][1];
	$last    = end( $mm );
	$after   = trim( substr( $text, (int) $last[0][1] + strlen( (string) $last[0][0] ) ) );
	$lead    = trim( rtrim( trim( substr( $text, 0, $first ) ), '.' ) );
	$between = '';
	for ( $i = 1, $n = count( $mm ); $i < $n; $i++ ) {
		$prev_end = (int) $mm[ $i - 1 ][0][1] + strlen( (string) $mm[ $i - 1 ][0][0] );
		$between .= trim( substr( $text, $prev_end, (int) $mm[ $i ][0][1] - $prev_end ) );
	}
	// Words wedged between seasons would be dropped by any layout: refuse.
	if ( '' !== $between || strlen( $lead ) > 30 ) {
		return null;
	}

	$seasons = array();
	$quals   = array();
	foreach ( $mm as $m ) {
		$from = month( (string) $m[2][0] );
		$to   = month( (string) $m[3][0] );
		if ( ! $from || ! $to ) {
			return null;
		}
		$rows = array();
		foreach ( array_map( 'trim', explode( ',', (string) $m[4][0] ) ) as $k => $part ) {
			if ( ! preg_match( '/^(?:([a-z][a-z ]*?)\s+)?(' . DAY_LABELS . ')\s+(' . TIME_RE . ')$/i', $part, $r ) ) {
				return null;
			}
			// A qualifier ("main group") only as the first word of a season.
			if ( '' !== ( $r[1] ?? '' ) ) {
				if ( 0 !== $k ) {
					return null;
				}
				$quals[ strtolower( trim( $r[1] ) ) ] = true;
			}
			$rows[] = array( 'label' => ucfirst( strtolower( $r[2] ) ), 'time' => time_label( $r[3] ) );
		}
		$seasons[] = array(
			'name'   => (string) $m[1][0],
			'from'   => $from,
			'to'     => $to,
			'months' => month_name( $from ) . '–' . month_name( $to ),
			'rows'   => $rows,
		);
	}

	$notes = array();
	if ( 1 === count( $quals ) ) {
		/* translators: %s: which group the times are for, e.g. main group */
		$notes[] = sprintf( __( 'Times shown are for the %s.', 'oria' ), array_key_first( $quals ) );
	} elseif ( count( $quals ) > 1 ) {
		return null;
	}
	if ( '' !== $after ) {
		$notes[] = $after;
	}
	return array( 'lead' => $lead, 'seasons' => $seasons, 'notes' => $notes );
}

/**
 * Which season applies now, or null to show them all.
 *
 * Null when ranges overlap, when none covers the month, or when a
 * different season starts within a week -- a cached page drawn now may
 * still be served then.
 *
 * @param array<int, array<string, mixed>> $seasons
 */
function select_season( array $seasons, \DateTimeImmutable $now ): ?int {
	$now   = $now->setTimezone( new \DateTimeZone( 'Australia/Perth' ) );
	$month = (int) $now->format( 'n' );
	$owner = array_fill( 1, 12, array() );
	foreach ( $seasons as $i => $s ) {
		foreach ( months_in( (int) $s['from'], (int) $s['to'] ) as $m ) {
			$owner[ $m ][] = $i;
		}
	}
	foreach ( $owner as $who ) {
		if ( count( $who ) > 1 ) {
			return null; // Conflicting ranges: no season can be trusted.
		}
	}
	if ( 1 !== count( $owner[ $month ] ) ) {
		return null;
	}
	$pick      = $owner[ $month ][0];
	$left      = (int) $now->format( 't' ) - (int) $now->format( 'j' );
	$next      = 12 === $month ? 1 : $month + 1;
	$next_pick = $owner[ $next ][0] ?? null;
	if ( $left < 7 && $next_pick !== $pick ) {
		return null;
	}
	return $pick;
}

/** @return array<int, int> */
function months_in( int $from, int $to ): array {
	$out = array();
	for ( $m = $from, $guard = 0; $guard < 12; $guard++ ) {
		$out[] = $m;
		if ( $m === $to ) {
			break;
		}
		$m = 12 === $m ? 1 : $m + 1;
	}
	return $out;
}

function month( string $word ): int {
	return MONTHS[ strtolower( substr( $word, 0, 3 ) ) ] ?? 0;
}

function month_name( int $m ): string {
	return wp_date( 'F', (int) mktime( 12, 0, 0, $m, 1, 2026 ) ) ?: '';
}

/** "6:15am" -> "6:15 am"; "7am" -> "7:00 am". */
function time_label( string $t ): string {
	if ( ! preg_match( '/^(\d{1,2})(?::(\d{2}))?\s?(am|pm)$/i', trim( $t ), $m ) ) {
		return $t;
	}
	return sprintf( '%d:%s %s', (int) $m[1], '' !== $m[2] ? $m[2] : '00', strtolower( $m[3] ) );
}
