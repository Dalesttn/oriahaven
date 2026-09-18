<?php
/**
 * A listing's primary category: the one it IS, as opposed to the ones it
 * also offers.
 *
 * WHY THIS EXISTS. The importer was always told a listing's primary category
 * (`cat` in every import file) and saved it together with the secondaries in
 * one wp_set_object_terms() call. WordPress keeps no order for a taxonomy
 * that is not registered with 'sort' => true -- term_order is 0 on every
 * relationship on this site -- so the primary was lost on the way in, and
 * everything that read "the first category" got the alphabetically first
 * one. Essential Yoga was a Fitness listing because F comes before Y.
 *
 * Now it is stored, as post meta `primary_practice` (a practice slug):
 *
 *   - the importer writes it from `cat`;
 *   - an admin can set it in the "Primary category" box on the listing;
 *   - tools/backfill-primary.php fills it for existing listings;
 *   - and where it is still empty, of() works it out with infer(), so the
 *     site is right before the backfill has even run.
 *
 * The category page's "Most relevant" order and its "Specialising in" /
 * "Also offering" groups read this, as does the featured band's rule that
 * only a practice's own category can headline it.
 */

declare(strict_types=1);

namespace Oria\Core\Primary;

use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META = 'primary_practice';

/*
 * Words in a practice's own name that say what it is, per category. Stems
 * match anywhere ("yogahub", "meditation"); the short, ambiguous ones are
 * whole words only, so "The Yoga Space" is not a spa and "Heart" is not art.
 */
const WORDS = array(
	'yoga'        => array( 'yoga', 'ashtanga', 'vinyasa', 'kundalini' ),
	'fitness'     => array( 'pilates', 'fitness', 'barre', 'crossfit', 'strength', 'reformer', 'boxing', '\bgym\b', 'personal training', 'bootcamp', 'swim', 'run club', 'dance', 'tai chi', 'qigong', 'kung fu', 'martial', 'leisureplex', 'aquatic' ),
	'bodywork'    => array( 'massage', 'bodywork', 'myotherap', 'remedial', 'reflexolog', 'shiatsu', 'thai massage' ),
	'meditation'  => array( 'meditat', 'dhamma', 'buddhis', '\bzen\b', 'vipassana', 'kadampa' ),
	'mindfulness' => array( 'mindful' ),
	'breathwork'  => array( 'breath' ),
	'sound'       => array( 'sound', '\bgong', 'singing bowl' ),
	'mind'        => array( 'counsell', 'psycholog', 'therapy centre', 'coaching', '\bcoach\b', 'hypno' ),
	'spa'         => array( '\bspa\b', 'day spa', 'bath house', 'bathhouse', '\bbaths?\b', 'float' ),
	'recovery'    => array( 'sauna', 'recovery', 'cryo', 'ice bath', 'plunge', 'hyperbaric', 'infrared', 'float' ),
	'natural'     => array( 'naturopath', 'herbal', 'acupunct', 'chinese medicine', 'ayurved', 'kinesiolog', 'homeopath', 'apothecar', 'remedy' ),
	'nutrition'   => array( 'nutrition', 'dietitian', 'dietician', 'wholefood', 'kitchen', 'organic' ),
	'smoothies-juice' => array( 'juic', 'smoothie', '\bacai\b', 'açaí', '\bbowls\b', 'pressed', 'cold press', 'kombucha', 'cultures' ),
	'allied'      => array( 'physio', 'chiropract', 'podiatr', 'osteopath', 'occupational therap', 'speech', 'exercise physiolog', 'clinic', 'health group' ),
	'family'      => array( 'doula', 'birth', 'midwi', '\bmums?\b', 'mother', 'baby', 'pregnan', 'fertility', '\bmen.s\b', 'women.s' ),
	'energy'      => array( 'reiki', 'spiritual', 'crystal', 'energy healing', 'healing centre', 'pranic', 'shaman', 'temple' ),
	'creative'    => array( '\bart\b', 'art therap', 'music', 'drama', 'creative' ),
	'community'   => array( 'support group', 'recovery group', 'anonymous', 'men.s shed', 'community', 'collective', 'café', 'cafe' ),
	'beauty'      => array( 'beauty', '\bskin\b', 'salon', 'nails?', '\bbrows?\b', 'lash', 'facial', 'medispa' ),
	'retreats'    => array( 'retreat' ),
	'walks-lookouts' => array( '\bloop\b', '\bpath\b', 'walk', 'trail', 'lookout', 'track' ),
	'nature'      => array( 'beach', '\bpark\b', 'forest', 'reserve', '\bpool\b', '\bdam\b', 'falls', 'gardens?', 'sands' ),
	'seniors'     => array( 'seniors', 'over 50', 'over-50', 'ageing', 'aging' ),
	'longevity'   => array( 'longevity', 'biohack', 'red light', 'iv therap' ),
);

function bootstrap(): void {
	add_action( 'acf/init', __NAMESPACE__ . '\register_field' );
	add_filter( 'acf/load_field/name=' . META, __NAMESPACE__ . '\choices' );
}

/**
 * The listing's primary category slug, or '' if it has no category.
 *
 * Stored value first, but only while it is still one of the listing's own
 * categories -- a category taken off the listing must not linger as its
 * primary. Otherwise worked out, the same way the backfill does it.
 */
function of( int $listing_id ): string {
	static $memo = array();
	if ( isset( $memo[ $listing_id ] ) ) {
		return $memo[ $listing_id ];
	}
	$slugs  = slugs( $listing_id );
	$stored = (string) get_post_meta( $listing_id, META, true );
	if ( '' !== $stored && in_array( $stored, $slugs, true ) ) {
		return $memo[ $listing_id ] = $stored;
	}
	return $memo[ $listing_id ] = infer( $listing_id, $slugs )['slug'];
}

/** @return list<string> the listing's practice slugs (reads the primed cache) */
function slugs( int $listing_id ): array {
	$terms = get_the_terms( $listing_id, Taxonomies\PRACTICE );
	return is_array( $terms ) ? array_values( wp_list_pluck( $terms, 'slug' ) ) : array();
}

/**
 * Work the primary out from what the listing says about itself.
 *
 *   1. Its own name. "Essence Pilates" is fitness whatever else it offers;
 *      where the name says two things, the first one said wins -- "Flow Hot
 *      Yoga & Pilates" is a yoga studio.
 *   2. Its services. Each canonical service belongs to categories
 *      (data/services.json); the category most of them point at wins.
 *   3. Its most specific category -- a sub-category over its parent -- and
 *      then the one the most listings share, which is a guess, and is
 *      reported as one.
 *
 * @param list<string> $slugs
 * @return array{slug: string, why: string}
 */
function infer( int $listing_id, array $slugs ): array {
	if ( ! $slugs ) {
		return array( 'slug' => '', 'why' => 'none' );
	}
	if ( 1 === count( $slugs ) ) {
		return array( 'slug' => $slugs[0], 'why' => 'only' );
	}

	// 1. The name.
	$name = strtolower( html_entity_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES ) );
	$best = null;
	foreach ( WORDS as $slug => $words ) {
		if ( ! in_array( $slug, $slugs, true ) ) {
			continue;
		}
		foreach ( $words as $w ) {
			// Every entry is a pattern: they are this file's own constants.
			if ( preg_match( '/' . $w . '/i', $name, $m, PREG_OFFSET_CAPTURE ) ) {
				$at = (int) $m[0][1];
				if ( null === $best || $at < $best[1] ) {
					$best = array( $slug, $at );
				}
			}
		}
	}
	if ( $best ) {
		return array( 'slug' => $best[0], 'why' => 'name' );
	}

	// 2. The services.
	$votes = array();
	$map   = service_categories();
	$svcs  = get_the_terms( $listing_id, 'service' );
	foreach ( is_array( $svcs ) ? $svcs : array() as $t ) {
		foreach ( (array) ( $map[ $t->slug ] ?? array() ) as $cat ) {
			if ( in_array( $cat, $slugs, true ) ) {
				$votes[ $cat ] = ( $votes[ $cat ] ?? 0 ) + 1;
			}
		}
	}
	if ( $votes ) {
		arsort( $votes );
		$top = array_keys( $votes );
		if ( count( $top ) === 1 || $votes[ $top[0] ] > $votes[ $top[1] ] ) {
			return array( 'slug' => $top[0], 'why' => 'services' );
		}
	}

	// 3. A guess: the most specific, then the most shared.
	$terms = array();
	foreach ( $slugs as $s ) {
		$t = get_term_by( 'slug', $s, Taxonomies\PRACTICE );
		if ( $t instanceof \WP_Term ) {
			$terms[] = $t;
		}
	}
	usort(
		$terms,
		static fn( \WP_Term $a, \WP_Term $b ): int => ( $b->parent ? 1 : 0 ) <=> ( $a->parent ? 1 : 0 ) ?: $b->count <=> $a->count
	);
	return array( 'slug' => $terms ? $terms[0]->slug : $slugs[0], 'why' => 'guess' );
}

/** @return array<string, list<string>> service slug => category slugs */
function service_categories(): array {
	static $map = null;
	if ( null === $map ) {
		$map  = array();
		$path = ORIA_CORE_DIR . 'data/services.json';
		$json = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( (array) ( $json['services'] ?? array() ) as $s ) {
			if ( ! empty( $s['slug'] ) ) {
				$map[ (string) $s['slug'] ] = array_map( 'strval', (array) ( $s['categories'] ?? array() ) );
			}
		}
	}
	return $map;
}

/* ------------------------------------------------------------ the admin box */

/**
 * "Primary category", in the listing's sidebar. Admin-only for now (see
 * Ownership\admin_only_fields): it decides whether a practice counts as a
 * specialist on a category page, which is a ranking input. Its choices are
 * only the listing's own categories -- the primary is one of the things it
 * already is, never a new one.
 */
function register_field(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_primary_practice',
			'title'    => __( 'Primary category', 'oria' ),
			'position' => 'side',
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => PostTypes\LISTING ) ) ),
			'fields'   => array(
				array(
					'key'           => 'field_oria_primary_practice',
					'name'          => META,
					'label'         => __( 'What this place is, first', 'oria' ),
					'type'          => 'select',
					'choices'       => array(),
					'allow_null'    => 1,
					'ui'            => 0,
					'return_format' => 'value',
					'instructions'  => __( 'Of the categories it is filed under, the one it is. Category pages list these as specialists and the rest as "also offering". Left empty, it is worked out from the name and services.', 'oria' ),
				),
			),
		)
	);
}

/**
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function choices( $field ) {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : ( isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$field['choices'] = array();
	foreach ( $post_id ? slugs( $post_id ) : array() as $slug ) {
		$t = get_term_by( 'slug', $slug, Taxonomies\PRACTICE );
		$field['choices'][ $slug ] = $t instanceof \WP_Term ? $t->name : $slug;
	}
	if ( $post_id && '' === (string) get_post_meta( $post_id, META, true ) ) {
		$guess                = infer( $post_id, slugs( $post_id ) );
		$field['placeholder'] = $guess['slug'];
		/* translators: 1: category, 2: how it was worked out */
		$field['instructions'] .= ' ' . sprintf( __( 'Worked out now: %1$s (from its %2$s).', 'oria' ), $guess['slug'], 'guess' === $guess['why'] ? __( 'categories — a guess', 'oria' ) : $guess['why'] );
	}
	return $field;
}
