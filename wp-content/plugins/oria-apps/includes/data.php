<?php
/**
 * The vocabularies the app section runs on.
 *
 * Categories describe what an app IS. "Best for" describes who it suits,
 * and is a field rather than a taxonomy on purpose: it exists to assemble
 * collections editorially ("best free wellness apps"), not to mint a public
 * page per value. A taxonomy would have produced sixteen thin archives on
 * day one.
 *
 * PRACTICE_MAP is the bridge back to the directory. An app tagged for
 * meditation belongs beside meditation classes, and that link should not
 * depend on anyone remembering to tick a box — the map gives every app a
 * sensible default, and the per-app field overrides it.
 */

declare(strict_types=1);

namespace Oria\Apps\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CPT = 'wellness_app';
const TAX = 'app_category';

/** The category tree, seeded once on activation. Editors may add more. */
const CATEGORIES = array(
	'meditation'         => 'Meditation',
	'mindfulness'        => 'Mindfulness',
	'sleep'              => 'Sleep',
	'stress-relaxation'  => 'Stress & Relaxation',
	'fitness'            => 'Fitness',
	'walking'            => 'Walking',
	'running'            => 'Running',
	'yoga'               => 'Yoga',
	'breathwork'         => 'Breathwork',
	'nutrition'          => 'Nutrition',
	'mental-wellness'    => 'Mental Wellness',
	'habit-building'     => 'Habit Building',
	'outdoor-wellness'   => 'Outdoor Wellness',
	'social-wellness'    => 'Social Wellness',
	'recovery'           => 'Recovery',
);

/** Who an app suits. A field, not a taxonomy -- see the note above. */
const BEST_FOR = array(
	'beginners'        => 'Beginners',
	'better-sleep'     => 'Better sleep',
	'relaxation'       => 'Relaxation',
	'stress-support'   => 'Stress support',
	'meditation'       => 'Meditation',
	'getting-active'   => 'Getting active',
	'building-habits'  => 'Building habits',
	'walking-outdoors' => 'Walking outdoors',
	'running'          => 'Running',
	'home-workouts'    => 'Home workouts',
	'mindfulness'      => 'Mindfulness',
	'focus'            => 'Focus',
	'recovery'         => 'Recovery',
	'social'           => 'Social motivation',
	'over-50s'         => 'Over 50s',
	'free'             => 'Free options',
);

/** How an app charges. "Unknown" is honest and stays available. */
const PRICING = array(
	'free'         => 'Free',
	'freemium'     => 'Freemium',
	'paid'         => 'Paid',
	'subscription' => 'Subscription',
	'one-time'     => 'One-time purchase',
	'unknown'      => 'Unknown',
);

/** The platforms worth stating. Each is a true/false field on the app. */
const PLATFORMS = array(
	'available_ios'         => 'iPhone',
	'available_android'     => 'Android',
	'available_web'         => 'Web',
	'available_apple_watch' => 'Apple Watch',
	'available_wear_os'     => 'Wear OS',
);

/**
 * App category => the directory practices it belongs beside.
 *
 * Only practices that exist in the taxonomy and actually hold listings are
 * ever linked; Engine\practices_for() checks. That keeps "take your
 * wellness offline" from pointing at an empty page.
 */
const PRACTICE_MAP = array(
	'meditation'        => array( 'meditation', 'mind' ),
	'mindfulness'       => array( 'mindfulness', 'mind' ),
	'sleep'             => array( 'recovery', 'spa' ),
	'stress-relaxation' => array( 'spa', 'mind', 'bodywork' ),
	'fitness'           => array( 'fitness' ),
	'walking'           => array( 'experiences', 'nature' ),
	'running'           => array( 'fitness', 'community' ),
	'yoga'              => array( 'yoga' ),
	'breathwork'        => array( 'breathwork', 'mind' ),
	'nutrition'         => array( 'nutrition' ),
	'mental-wellness'   => array( 'mind' ),
	'habit-building'    => array( 'mind', 'fitness' ),
	'outdoor-wellness'  => array( 'experiences', 'nature' ),
	'social-wellness'   => array( 'community' ),
	'recovery'          => array( 'recovery', 'spa' ),
);

/** How long an app's facts stay trustworthy before the admin says so. */
const STALE_DAYS = 180;

/**
 * The disclosure, in one place so every surface says the same thing.
 *
 * Shown wherever an affiliate link actually appears, and nowhere else: a
 * blanket disclosure on pages with no commercial link trains people to
 * ignore it.
 */
function disclosure(): string {
	return __( 'Some links on this page are affiliate links. If you sign up through one, Oria Haven may earn a commission at no extra cost to you. It never affects which apps we recommend.', 'oria' );
}

/** How the apps are chosen, said plainly. Shown on app pages and the hub. */
function method(): string {
	return __( 'We review wellness apps on what they actually offer: features, accessibility, pricing and the kind of support they provide. Details are checked against the app’s own website and store listings, and apps change, so each page carries the date we last checked it.', 'oria' );
}

function label( string $group, string $key ): string {
	$map = array( 'best_for' => BEST_FOR, 'pricing' => PRICING, 'platform' => PLATFORMS );
	return (string) ( $map[ $group ][ $key ] ?? $key );
}
