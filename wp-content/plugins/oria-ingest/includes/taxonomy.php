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

function cli(): void {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	\WP_CLI::add_command(
		'oria events fix-types',
		/**
		 * Merge event types this site does not recognise into the ones it does.
		 *
		 * Reports and changes nothing unless --execute is given.
		 *
		 * ## OPTIONS
		 *
		 * [--execute]
		 * : Actually move the events and delete the stray terms.
		 *
		 * ## EXAMPLES
		 *
		 *     wp oria events fix-types
		 *     wp oria events fix-types --execute
		 */
		static function ( array $args, array $assoc ): void {
			$execute = ! empty( $assoc['execute'] );
			$strays  = strays();

			if ( ! $strays ) {
				\WP_CLI::success( 'Every event type is one this site knows. Nothing to do.' );
				return;
			}

			foreach ( $strays as $stray ) {
				\WP_CLI::line(
					sprintf(
						'%-28s %d event(s) -> %s',
						$stray['term']->name . ' (' . $stray['term']->slug . ')',
						$stray['events'],
						'' !== $stray['into'] ? $stray['into'] : 'NO MATCH — left alone'
					)
				);
			}

			$out = merge_strays( $execute );
			foreach ( $out['skipped'] as $slug ) {
				\WP_CLI::warning( sprintf( '%s matches no known type. Retag its events by hand, then delete it.', $slug ) );
			}

			if ( $execute ) {
				\WP_CLI::success( sprintf( 'Moved %d event(s) and deleted %d term(s).', $out['moved'], $out['deleted'] ) );
				return;
			}
			\WP_CLI::success(
				sprintf(
					'Dry run: %d event(s) to move, %d term(s) to delete. Nothing written — add --execute to do it.',
					$out['moved'],
					$out['deleted']
				)
			);
		}
	);
}

function bootstrap(): void {
	cli();
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

/**
 * Event types this site does not recognise, with where each one's events
 * would go. Dry-run fodder: nothing here changes anything.
 *
 * A stray term is matched to a known one by its slug -- "meditation-classes"
 * begins with "meditation" -- and anything that cannot be matched is
 * reported rather than guessed at.
 *
 * @return array<int, array{term: \WP_Term, into: string, events: int}>
 */
function strays(): array {
	$terms = get_terms( array( 'taxonomy' => 'event_type', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$known = slugs();
	$out   = array();
	foreach ( $terms as $term ) {
		if ( in_array( $term->slug, $known, true ) ) {
			continue;
		}
		$into = '';
		foreach ( $known as $slug ) {
			if ( 0 === strpos( $term->slug, $slug ) || 0 === strpos( $slug, $term->slug ) ) {
				$into = $slug;
				break;
			}
		}
		$out[] = array( 'term' => $term, 'into' => $into, 'events' => (int) $term->count );
	}
	return $out;
}

/**
 * Move every event off a stray type and delete it.
 *
 * Ordered deliberately: the events are re-tagged first and the term is only
 * removed once nothing points at it, so an interrupted run leaves events
 * with both terms rather than with none.
 *
 * @return array{moved:int, deleted:int, skipped:string[]}
 */
function merge_strays( bool $execute = false ): array {
	$moved   = 0;
	$deleted = 0;
	$skipped = array();

	foreach ( strays() as $stray ) {
		$term = $stray['term'];
		if ( '' === $stray['into'] ) {
			$skipped[] = $term->slug;
			continue;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'event',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array( array( 'taxonomy' => 'event_type', 'field' => 'term_id', 'terms' => $term->term_id ) ),
			)
		);

		foreach ( $ids as $id ) {
			if ( $execute ) {
				wp_set_object_terms( (int) $id, $stray['into'], 'event_type' );
			}
			$moved++;
		}

		if ( $execute ) {
			wp_delete_term( $term->term_id, 'event_type' );
		}
		$deleted++;
	}

	return array( 'moved' => $moved, 'deleted' => $deleted, 'skipped' => $skipped );
}

/** Valid type slugs, for prompt construction and validation. */
function slugs(): array {
	return array_keys( TYPES );
}
