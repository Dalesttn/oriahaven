<?php
/**
 * The Micro Reset fields.
 *
 * A Micro Reset is a journey somebody can do in an hour without booking
 * anything: a handful of sensory prompts, a route, and the practical detail
 * that decides whether they actually go.
 *
 * Two rules run through this group.
 *
 * Nothing practical is guessed. Distance, surface, toilets, parking and the
 * rest stay empty until somebody has walked the route with a phone in their
 * hand, and the template hides every row that is still empty rather than
 * printing "TBC". A reader can tell the difference between a page that does
 * not know and a page that is making it up, and only one of those is worth
 * trusting on a hot day a long way from the car park.
 *
 * Nothing here treats anything. The prompts say notice, pause, see how you
 * feel. They never say this will calm you, and the safety copy says plainly
 * that it is a walk rather than care.
 */

declare(strict_types=1);

namespace Oria\Core\FieldsJourney;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const GROUP = 'group_oria_reset';

function register(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$k    = static fn( string $n ): string => 'field_oria_reset_' . $n;
	$tab  = static fn( string $n, string $label ): array => array( 'key' => $k( 'tab_' . $n ), 'label' => $label, 'type' => 'tab', 'placement' => 'top' );
	$text = static fn( string $n, string $label, string $ins = '', array $x = array() ): array => array_merge( array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'text', 'instructions' => $ins ), $x );
	$area = static fn( string $n, string $label, string $ins = '', int $rows = 3 ): array => array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'textarea', 'instructions' => $ins, 'rows' => $rows, 'new_lines' => '' );
	$num  = static fn( string $n, string $label, string $ins = '' ): array => array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'number', 'instructions' => $ins );
	$sel  = static fn( string $n, string $label, array $c, string $ins = '' ): array => array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'select', 'choices' => $c, 'allow_null' => 1, 'instructions' => $ins );
	$bool = static fn( string $n, string $label, string $msg = '' ): array => array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'true_false', 'ui' => 1, 'message' => $msg );
	$url  = static fn( string $n, string $label, string $ins = '' ): array => array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'url', 'instructions' => $ins );
	$date = static fn( string $n, string $label, string $ins = '' ): array => array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'date_picker', 'display_format' => 'j M Y', 'return_format' => 'Y-m-d', 'instructions' => $ins );

	acf_add_local_field_group(
		array(
			'key'      => GROUP,
			'title'    => 'Micro Reset',
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => PostTypes\JOURNEY ) ) ),
			'position' => 'normal',
			'fields'   => array(

				// -------------------------------------------------- overview
				$tab( 'overview', 'Overview' ),
				$text( 'hook', 'Hook', 'One line under the title.' ),
				$area( 'intro', 'Short intro', 'Two short paragraphs at most. Say what it is, and what it does not ask of anybody.', 4 ),
				$num( 'duration_minutes', 'Duration (minutes)' ),
				$text( 'cost_label', 'Cost', 'Free, or what it costs.' ),
				$sel( 'intensity', 'Intensity', array( 'gentle' => 'Gentle walking', 'moderate' => 'Moderate', 'active' => 'Active' ) ),
				$sel( 'season', 'Best season', array( 'spring' => 'Spring', 'summer' => 'Summer', 'autumn' => 'Autumn', 'winter' => 'Winter', 'any' => 'Any time' ) ),
				$sel( 'format', 'Format', array( 'self-guided' => 'Self-guided', 'guided' => 'Guided' ) ),
				$text( 'best_for', 'Best for', 'Comma separated, e.g. Reset, Calm, Nature' ),
				$text( 'suitable_for', 'Suitable for', 'e.g. Solo, couples or friends' ),
				$text( 'location_label', 'Location', 'e.g. Kings Park, Perth' ),

				// ----------------------------------------------------- route
				$tab( 'route', 'Route' ),
				array(
					'key'     => $k( 'route_help' ),
					'type'    => 'message',
					'label'   => '',
					'message' => 'Leave anything you have not walked and measured yourself empty. Every row here is hidden on the page until it is filled, and a blank row is better than a guess somebody relies on.',
				),
				$text( 'start_label', 'Starting point', 'The landmark somebody can actually find.' ),
				$url( 'start_map_url', 'Starting point map link' ),
				$text( 'end_label', 'Finish point' ),
				$bool( 'is_loop', 'Finishes where it starts' ),
				$num( 'distance_km', 'Distance (km)', 'Only once it has been measured.' ),
				$date( 'last_verified', 'Route last walked', 'Shown to readers as "Last checked".' ),

				// ----------------------------------------- practical details
				$tab( 'practical', 'Practical details' ),
				$area( 'surface', 'Surface', 'Sealed, compacted, uneven or mixed.' ),
				$area( 'gradient', 'Gradients' ),
				$area( 'steps_or_barriers', 'Steps and barriers' ),
				$area( 'accessibility_summary', 'Accessibility', 'Name the barriers. Never "fully accessible" unless the whole route has been checked.' ),
				$area( 'accessible_alternative', 'Step-free alternative' ),
				$area( 'parking', 'Parking' ),
				$area( 'public_transport', 'Public transport' ),
				$area( 'toilets', 'Toilets' ),
				$area( 'water', 'Drinking water' ),
				$area( 'seating', 'Seating' ),
				$sel( 'shade', 'Shade', array( 'none' => 'None', 'partial' => 'Partial', 'frequent' => 'Frequent' ) ),
				$area( 'best_time', 'Best time to go' ),
				$area( 'pet_rules', 'Dogs', 'Current official park rules only.' ),
				$area( 'weather_note', 'Weather note' ),
				$url( 'conditions_url', 'Current conditions link' ),

				// ---------------------------------------------------- stages
				$tab( 'stages', 'Stages' ),
				array(
					'key'          => $k( 'stages' ),
					'name'         => 'stages',
					'label'        => 'Stages',
					'type'         => 'repeater',
					'button_label' => 'Add a stage',
					'layout'       => 'row',
					'instructions' => 'One row per prompt, in order. A stage with a timer shows a silent countdown the reader can pause or end early.',
					'sub_fields'   => array(
						array( 'key' => $k( 'stage_title' ), 'name' => 'title', 'label' => 'Prompt label', 'type' => 'text', 'wrapper' => array( 'width' => '40' ) ),
						array( 'key' => $k( 'stage_from' ), 'name' => 'from', 'label' => 'From (min)', 'type' => 'number', 'wrapper' => array( 'width' => '15' ) ),
						array( 'key' => $k( 'stage_to' ), 'name' => 'to', 'label' => 'To (min)', 'type' => 'number', 'wrapper' => array( 'width' => '15' ) ),
						array( 'key' => $k( 'stage_timer' ), 'name' => 'timer', 'label' => 'Timer (seconds)', 'type' => 'number', 'instructions' => 'Leave empty for no timer.', 'wrapper' => array( 'width' => '30' ) ),
						array( 'key' => $k( 'stage_body' ), 'name' => 'body', 'label' => 'Prompt', 'type' => 'textarea', 'rows' => 3, 'new_lines' => '' ),
						array( 'key' => $k( 'stage_safety' ), 'name' => 'safety', 'label' => 'Safety note', 'type' => 'textarea', 'rows' => 2, 'new_lines' => '' ),
						array( 'key' => $k( 'stage_action' ), 'name' => 'action', 'label' => 'Optional action line', 'type' => 'text' ),
						array( 'key' => $k( 'stage_responses' ), 'name' => 'responses', 'label' => 'Response options', 'type' => 'text', 'instructions' => 'Comma separated. A private choice for the reader; never required, and never sent anywhere.' ),
						array( 'key' => $k( 'stage_image' ), 'name' => 'image', 'label' => 'Image', 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium' ),
						array( 'key' => $k( 'stage_audio' ), 'name' => 'audio', 'label' => 'Audio (later)', 'type' => 'file', 'return_format' => 'id', 'instructions' => 'Phase 2. Nothing ever plays on its own.' ),
						array( 'key' => $k( 'stage_transcript' ), 'name' => 'transcript', 'label' => 'Audio transcript', 'type' => 'textarea', 'rows' => 2, 'new_lines' => '' ),
					),
				),
				$text( 'complete_message', 'Completion message', 'Shown when the last stage is done.' ),

				// -------------------------------------------------- seasonal
				$tab( 'seasonal', 'Seasonal panel' ),
				array(
					'key'     => $k( 'seasonal_help' ),
					'type'    => 'message',
					'label'   => '',
					'message' => 'A panel that is true only for a few weeks. It swaps itself for the evergreen one the moment the end date passes in Perth time, so the page stays correct without anybody remembering it.',
				),
				$bool( 'seasonal_on', 'Show the seasonal panel' ),
				$date( 'seasonal_start', 'From' ),
				$date( 'seasonal_end', 'Until (inclusive)' ),
				$text( 'seasonal_title', 'Seasonal title' ),
				$area( 'seasonal_body', 'Seasonal body' ),
				$text( 'seasonal_cta_label', 'Seasonal button' ),
				$url( 'seasonal_cta_url', 'Seasonal button link' ),
				$text( 'evergreen_title', 'Evergreen title' ),
				$area( 'evergreen_body', 'Evergreen body' ),
				$text( 'evergreen_cta_label', 'Evergreen button' ),
				$url( 'evergreen_cta_url', 'Evergreen button link' ),

				// ---------------------------------------------------- nearby
				$tab( 'nearby', 'Keep exploring' ),
				array(
					'key'           => $k( 'related_listings' ),
					'name'          => 'related_listings',
					'label'         => 'Related listings',
					'type'          => 'relationship',
					'post_type'     => array( 'listing' ),
					'filters'       => array( 'search' ),
					'return_format' => 'id',
					'max'           => 4,
					'instructions'  => 'Four at most, and only where somebody would genuinely want them next. Do not fill this because the taxonomy happens to match.',
				),
				$text( 'related_reason', 'Why these', 'One line above the cards, e.g. "Prefer walking with other people?"' ),
			),
		)
	);
}
