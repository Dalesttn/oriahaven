<?php
/**
 * Keyword fallback for when no AI key is configured: relevance gate and
 * event-type guess from title + description. Deliberately conservative —
 * anything ambiguous is dropped rather than guessed, and no description is
 * carried over (copying source copy verbatim is not allowed; the AI path
 * writes an original summary, this path leaves it for the reviewer).
 */

declare(strict_types=1);

namespace Oria\Ingest\Heuristic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** event_type slug => keyword needles (lowercase). First match wins, so
 *  specific practices come before broad ones — "9D Breathwork at Some Yoga
 *  Studio" is breathwork, not yoga. */
const MAP = array(
	'breathwork'           => array( 'breathwork', 'breath work', 'wim hof', 'holotropic', 'pranayama' ),
	'sound-healing'        => array( 'sound bath', 'sound healing', 'gong', 'singing bowl', 'sound journey' ),
	'meditation'           => array( 'meditation', 'meditate', 'sitting group', 'vipassana', 'zazen' ),
	'yoga'                 => array( 'yoga', 'yin', 'vinyasa', 'hatha', 'asana' ),
	'mindfulness'          => array( 'mindfulness', 'mindful' ),
	'womens-circle'        => array( "women's circle", 'womens circle', 'women circle', 'sister circle', 'red tent' ),
	'mens-group'           => array( "men's group", 'mens group', "men's circle", 'mens circle' ),
	'retreat'              => array( 'retreat' ),
	'sauna'                => array( 'sauna' ),
	'cold-plunge'          => array( 'cold plunge', 'ice bath', 'cold exposure', 'cold water immersion' ),
	'nutrition'            => array( 'nutrition', 'gut health', 'wholefood', 'cooking for health' ),
	'fitness'              => array( 'pilates', 'tai chi', 'qigong', 'qi gong', 'movement class' ),
	'personal-development' => array( 'personal development', 'goal setting', 'journaling workshop', 'self discovery' ),
	'spiritual'            => array( 'reiki', 'energy healing', 'chakra', 'crystal', 'shamanic', 'cacao ceremony', 'kirtan' ),
	'relaxation'           => array( 'relaxation', 'stress relief', 'restorative', 'nervous system' ),
	'wellness-workshop'    => array( 'wellness', 'wellbeing', 'well-being', 'self care', 'self-care', 'holistic' ),
);

/** Things that rule an event out even if a keyword matched. */
const EXCLUDE = array( 'webinar', 'online only', 'zoom', 'network marketing', 'mlm' );

/** @return string event_type slug, or '' when not confidently wellness. */
function classify( string $title, string $description ): string {
	$hay = strtolower( $title . ' ' . $description );
	foreach ( EXCLUDE as $bad ) {
		if ( str_contains( $hay, $bad ) ) {
			return '';
		}
	}
	foreach ( MAP as $slug => $needles ) {
		foreach ( $needles as $needle ) {
			if ( str_contains( $hay, $needle ) ) {
				return $slug;
			}
		}
	}
	return '';
}
