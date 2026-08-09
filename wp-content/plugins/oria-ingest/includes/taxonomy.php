<?php
/**
 * The event-type taxonomy: what kind of session an event is, independent of
 * which practice category runs it. Aggregated and member events both use it,
 * and the What's On filters are built from it.
 */

declare(strict_types=1);

namespace Oria\Ingest\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** slug => [label, practice-category slug it maps to ('' = none)] */
const TYPES = array(
	'yoga'                 => array( 'Yoga', 'yoga' ),
	'meditation'           => array( 'Meditation', 'meditation' ),
	'breathwork'           => array( 'Breathwork', 'breathwork' ),
	'sound-healing'        => array( 'Sound Healing', 'sound' ),
	'mindfulness'          => array( 'Mindfulness', 'mindfulness' ),
	'womens-circle'        => array( "Women's Circle", '' ),
	'mens-group'           => array( "Men's Group", '' ),
	'wellness-workshop'    => array( 'Wellness Workshop', '' ),
	'retreat'              => array( 'Retreat', 'retreats' ),
	'sauna'                => array( 'Sauna', 'recovery' ),
	'cold-plunge'          => array( 'Cold Plunge', 'recovery' ),
	'nutrition'            => array( 'Nutrition', 'nutrition' ),
	'fitness'              => array( 'Fitness & Movement', 'fitness' ),
	'personal-development' => array( 'Personal Development', '' ),
	'spiritual'            => array( 'Spiritual & Holistic', 'energy' ),
	'relaxation'           => array( 'Stress & Relaxation', '' ),
	'community'            => array( 'Community Event', '' ),
);

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register', 5 );
}

function register(): void {
	register_taxonomy(
		'event_type',
		array( 'event' ),
		array(
			'label'             => __( 'Event types', 'oria' ),
			'labels'            => array(
				'name'          => __( 'Event types', 'oria' ),
				'singular_name' => __( 'Event type', 'oria' ),
			),
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'event-type', 'with_front' => false ),
		)
	);

	foreach ( TYPES as $slug => $def ) {
		if ( ! term_exists( $slug, 'event_type' ) ) {
			wp_insert_term( $def[0], 'event_type', array( 'slug' => $slug ) );
		}
	}
}

/** The practice-category slug a type maps to, or ''. */
function practice_for( string $type ): string {
	return TYPES[ $type ][1] ?? '';
}

/** Valid type slugs, for prompt construction and validation. */
function slugs(): array {
	return array_keys( TYPES );
}
