<?php
/**
 * Editorial app collections — "10 best wellness apps in Australia", and
 * every shortlist like it.
 *
 * A collection is a post whose picks live in one repeater: app, the line
 * that says who it suits, and a paragraph saying why it earned the place.
 * That repeater is the whole ranking. Nothing is scored, nothing is sorted
 * by a number, and nothing is ordered by whether the app pays us — an
 * editor drags the rows and that is the order readers see. The same rule
 * Best Of runs on, for the same reason.
 *
 * What a pick does NOT store is anything the app already knows. Features,
 * pros, considerations, pricing and platforms are read from the app on
 * every render, so correcting Headspace's pricing once corrects it on the
 * app page, on its cards, and in every collection that holds it. A
 * shortlist that quietly goes stale is worse than no shortlist.
 *
 * URL: /guides/{slug}/. The path is free — the journal lives elsewhere —
 * and it reads as what it is to somebody scanning a search result.
 */

declare(strict_types=1);

namespace Oria\Apps\Guides;

use Oria\Apps\Data;
use Oria\Apps\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CPT       = 'app_guide';
const PATH      = 'guides';
const REWRITE_V = '1';

/** Collections the archive needs before it is worth indexing. */
const ARCHIVE_MIN = 2;

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register', 6 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );
	add_action( 'acf/init', __NAMESPACE__ . '\fields' );
	add_filter( 'manage_' . CPT . '_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_' . CPT . '_posts_custom_column', __NAMESPACE__ . '\column', 10, 2 );
}

/* ------------------------------------------------------------- the type */

function register(): void {
	register_post_type(
		CPT,
		array(
			'labels'        => array(
				'name'               => __( 'App guides', 'oria' ),
				'singular_name'      => __( 'App guide', 'oria' ),
				'menu_name'          => __( 'App guides', 'oria' ),
				'add_new'            => __( 'Add guide', 'oria' ),
				'add_new_item'       => __( 'Add app guide', 'oria' ),
				'edit_item'          => __( 'Edit app guide', 'oria' ),
				'new_item'           => __( 'New app guide', 'oria' ),
				'view_item'          => __( 'View guide', 'oria' ),
				'search_items'       => __( 'Search app guides', 'oria' ),
				'not_found'          => __( 'No app guides yet', 'oria' ),
				'not_found_in_trash' => __( 'No app guides in the bin', 'oria' ),
				'archives'           => __( 'App guides', 'oria' ),
			),
			'public'        => true,
			'show_in_menu'  => 'edit.php?post_type=' . Data\CPT,
			// Every part of the page is a field, as on an app: a free prose
			// box beside them would only drift out of step.
			'supports'      => array( 'title', 'excerpt', 'thumbnail', 'revisions' ),
			'has_archive'   => PATH,
			'rewrite'       => array(
				'slug'       => PATH,
				'with_front' => false,
			),
			'show_in_rest'  => true,
			'rest_base'     => 'app-guides',
		)
	);
}

function activate(): void {
	register();
	flush_rewrite_rules();
	update_option( 'oria_app_guides_rewrite_v', REWRITE_V );
}

/** A git deploy never re-runs activation; this covers production. */
function maybe_flush(): void {
	if ( get_option( 'oria_app_guides_rewrite_v' ) === REWRITE_V ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'oria_app_guides_rewrite_v', REWRITE_V );
}

function archive_url(): string {
	return (string) get_post_type_archive_link( CPT );
}

/* ---------------------------------------------------------- the reading */

/**
 * One collection, flattened for a template.
 *
 * @return array<string, mixed>
 */
function guide( int $id ): array {
	$post = get_post( $id );

	return array(
		'id'       => $id,
		'title'    => $post instanceof \WP_Post ? wp_specialchars_decode( $post->post_title ) : '',
		'url'      => (string) get_permalink( $id ),
		'subtitle' => (string) get_post_meta( $id, 'guide_subtitle', true ),
		'intro'    => (string) get_post_meta( $id, 'guide_intro', true ),
		'checked'  => checked_on( $id ),
		'picks'    => picks( $id ),
		'faq'      => Engine\faq( $id ),
	);
}

/**
 * The picks, in the editor's order, each joined to its app.
 *
 * An unpublished or deleted app drops out silently rather than rendering
 * a row that links nowhere. The numbering the reader sees comes from what
 * survives this, so a dropped pick leaves no gap.
 *
 * @return list<array{app: array<string, mixed>, line: string, blurb: string}>
 */
function picks( int $id ): array {
	$count = (int) get_post_meta( $id, 'picks', true );
	$out   = array();

	for ( $i = 0; $i < $count; $i++ ) {
		$app_id = (int) get_post_meta( $id, 'picks_' . $i . '_app', true );
		if ( ! $app_id || 'publish' !== get_post_status( $app_id ) ) {
			continue;
		}
		$out[] = array(
			'app'   => Engine\row( $app_id ),
			'line'  => (string) get_post_meta( $id, 'picks_' . $i . '_best_for', true ),
			'blurb' => (string) get_post_meta( $id, 'picks_' . $i . '_blurb', true ),
		);
	}

	return $out;
}

/**
 * The line under a pick's name: the editor's own words where they wrote
 * them, otherwise the app's first "best for" value.
 */
function pick_line( array $pick ): string {
	if ( '' !== trim( (string) $pick['line'] ) ) {
		return (string) $pick['line'];
	}
	$best = (array) ( $pick['app']['best_for'] ?? array() );
	return $best ? Data\label( 'best_for', (string) $best[0] ) : '';
}

/**
 * When the collection was last gone over.
 *
 * The editor's date where they set one, because "checked" means a person
 * read it, not that WordPress touched the row. Falling back to the
 * modified date is still better than printing nothing.
 */
function checked_on( int $id ): string {
	$set = (string) get_post_meta( $id, 'guide_checked', true );
	if ( '' !== $set ) {
		return $set;
	}
	return (string) get_post_field( 'post_modified', $id );
}

/** Published collections, newest first. @return list<int> */
function all( int $limit = 0 ): array {
	return array_map(
		'intval',
		(array) get_posts(
			array(
				'post_type'      => CPT,
				'post_status'    => 'publish',
				'posts_per_page' => $limit > 0 ? $limit : -1,
				'fields'         => 'ids',
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
				'no_found_rows'  => true,
			)
		)
	);
}

/** Collections that hold a given app, for a "featured in" line. @return list<int> */
function holding( int $app_id ): array {
	$out = array();
	foreach ( all() as $id ) {
		foreach ( picks( $id ) as $pick ) {
			if ( (int) $pick['app']['id'] === $app_id ) {
				$out[] = $id;
				break;
			}
		}
	}
	return $out;
}

/** True when any pick in the collection links out commercially. */
function affiliate_in( array $picks ): bool {
	foreach ( $picks as $pick ) {
		if ( ! empty( $pick['app']['affiliate'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The platforms a pick runs on, shortened for a table cell.
 *
 * iPhone and Android only: a column listing five platforms stops being
 * scannable, which is the single thing a comparison table is for.
 */
function platform_line( array $app ): string {
	$on = (array) $app['platforms'];
	$of = array();
	if ( in_array( 'available_ios', $on, true ) ) {
		$of[] = __( 'iPhone', 'oria' );
	}
	if ( in_array( 'available_android', $on, true ) ) {
		$of[] = __( 'Android', 'oria' );
	}
	if ( ! $of && in_array( 'available_web', $on, true ) ) {
		$of[] = __( 'Web', 'oria' );
	}
	return $of ? implode( ' · ', $of ) : '—';
}

/* ------------------------------------------------------------- the form */

function fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_app_guide',
			'title'    => 'App guide',
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => CPT ) ) ),
			'position' => 'normal',
			'fields'   => array(

				array( 'key' => 'field_oria_guide_tab_intro', 'label' => 'The guide', 'type' => 'tab', 'placement' => 'left' ),
				array(
					'key'          => 'field_oria_guide_subtitle',
					'name'         => 'guide_subtitle',
					'label'        => 'Subtitle',
					'type'         => 'text',
					'maxlength'    => 120,
					'instructions' => 'The line under the heading. "Apps for sleep, meditation, fitness, mindfulness and everyday wellbeing."',
				),
				array(
					'key'          => 'field_oria_guide_intro',
					'name'         => 'guide_intro',
					'label'        => 'Introduction',
					'type'         => 'textarea',
					'rows'         => 5,
					'instructions' => 'A paragraph or two saying who the list is for and how it was put together. Written before the table, read before the table.',
				),
				array(
					'key'            => 'field_oria_guide_checked',
					'name'           => 'guide_checked',
					'label'          => 'Last checked',
					'type'           => 'date_picker',
					'display_format' => 'j F Y',
					'return_format'  => 'Y-m-d',
					'instructions'   => 'The day a person last read this list against the apps in it. Printed on the page.',
					'wrapper'        => array( 'width' => '40' ),
				),

				array( 'key' => 'field_oria_guide_tab_picks', 'label' => 'The picks', 'type' => 'tab', 'placement' => 'left' ),
				array(
					'key'          => 'field_oria_guide_picks',
					'name'         => 'picks',
					'label'        => 'Picks',
					'type'         => 'repeater',
					'layout'       => 'row',
					'button_label' => 'Add a pick',
					'instructions' => 'Drag to order. This order is the ranking readers see — there is no score behind it. Features, pricing and considerations come from each app, so they only ever need correcting once.',
					'sub_fields'   => array(
						array(
							'key'           => 'field_oria_guide_pick_app',
							'name'          => 'app',
							'label'         => 'App',
							'type'          => 'post_object',
							'post_type'     => array( Data\CPT ),
							'return_format' => 'id',
							'ui'            => 1,
							'required'      => 1,
							'wrapper'       => array( 'width' => '40' ),
						),
						array(
							'key'          => 'field_oria_guide_pick_best_for',
							'name'         => 'best_for',
							'label'        => 'Best for',
							'type'         => 'text',
							'maxlength'    => 60,
							'instructions' => 'In this list\'s context — "Meditation beginners". Leave empty to use the app\'s own.',
							'wrapper'      => array( 'width' => '60' ),
						),
						array(
							'key'          => 'field_oria_guide_pick_blurb',
							'name'         => 'blurb',
							'label'        => 'Why it is here',
							'type'         => 'textarea',
							'rows'         => 4,
							'instructions' => 'Two or three sentences on why this app earned its place, in this list. Not a repeat of the app page.',
						),
					),
				),

				array( 'key' => 'field_oria_guide_tab_faq', 'label' => 'Questions', 'type' => 'tab', 'placement' => 'left' ),
				array(
					'key'          => 'field_oria_guide_faq',
					'name'         => 'faq',
					'label'        => 'Questions people ask',
					'type'         => 'repeater',
					'layout'       => 'row',
					'button_label' => 'Add a question',
					'instructions' => 'Only questions a reader genuinely asks. Answer in the first sentence; a block added for search engines reads like one.',
					'sub_fields'   => array(
						array( 'key' => 'field_oria_guide_faq_q', 'name' => 'question', 'label' => 'Question', 'type' => 'text' ),
						array( 'key' => 'field_oria_guide_faq_a', 'name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'rows' => 3 ),
					),
				),
			),
		)
	);
}

/* ------------------------------------------------------------ the admin */

function columns( array $cols ): array {
	$out = array();
	foreach ( $cols as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out['oria_picks']   = __( 'Picks', 'oria' );
			$out['oria_checked'] = __( 'Last checked', 'oria' );
		}
	}
	return $out;
}

function column( string $col, int $id ): void {
	if ( 'oria_picks' === $col ) {
		echo (int) count( picks( $id ) );
		return;
	}
	if ( 'oria_checked' === $col ) {
		$on = checked_on( $id );
		if ( '' === $on ) {
			echo '—';
			return;
		}
		$stale = Engine\stale( $on );
		printf(
			'<span style="%s">%s</span>',
			$stale ? 'color:#b32d2e;font-weight:600' : '',
			esc_html( date_i18n( 'j M Y', (int) strtotime( $on ) ) )
		);
	}
}
