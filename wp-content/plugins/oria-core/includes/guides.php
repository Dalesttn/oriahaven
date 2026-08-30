<?php
/**
 * The journal articles that belong beside a category, area or modality page.
 *
 * The answer block is a column of prose against a lot of empty page. The
 * guides go in that space, which is the right place for them: somebody
 * reading "21 practices under Retreats, Nature & Experiences" is exactly the
 * person who might want the piece about day retreats within two hours of
 * Perth.
 *
 * The relationship is curated, not inferred. Articles carry ACF fields —
 * related_practices and related_areas — and this reads them. Guessing from
 * keywords would put the acupuncture article on half the directory, and the
 * whole reason internal links are worth anything is that somebody decided
 * they belonged together.
 *
 * The tree is read in both directions, for the same reason the intent rows
 * read services both ways: an article is tagged with the most specific
 * practice that fits — "Retreats & day escapes", not its parent "Retreats,
 * Nature & Experiences" — so a parent page has to accept its children's
 * articles or it shows none at all.
 */

declare(strict_types=1);

namespace Oria\Core\Guides;

use Oria\Core\Taxonomies;
use Oria\Core\Journeys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LIMIT = 4;

/**
 * Term ids that count as "this page", including ancestors and descendants.
 *
 * @return list<int>
 */
function family( \WP_Term $term ): array {
	$ids = array( (int) $term->term_id );

	foreach ( (array) get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) as $id ) {
		$ids[] = (int) $id;
	}
	foreach ( (array) get_term_children( $term->term_id, $term->taxonomy ) as $id ) {
		$ids[] = (int) $id;
	}

	return array_values( array_unique( $ids ) );
}

/**
 * Articles related to a term.
 *
 * @return list<\WP_Post>
 */
function for_term( \WP_Term $term, int $limit = LIMIT ): array {
	$field = Taxonomies\PRACTICE === $term->taxonomy
		? 'related_practices'
		: ( Taxonomies\AREA === $term->taxonomy ? 'related_areas' : '' );

	// Specialties carry no relation field. Rather than inventing one, the
	// column simply does not appear on those pages.
	if ( '' === $field ) {
		return array();
	}

	$family = family( $term );

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			// A journey lives at /journeys/; it is not one of the journal's guides.
			'meta_query'     => Journeys\not_journey_meta(),
			'post_status'    => 'publish',
			'posts_per_page' => 30,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	$out = array();

	foreach ( $posts as $post ) {
		$related = get_field( $field, $post->ID );

		/*
		 * ACF returns the stored array, but a hand-edited value can arrive as
		 * a single id or a comma string. Normalising here rather than trusting
		 * the field keeps one bad row from emptying the whole column.
		 */
		if ( ! is_array( $related ) ) {
			$related = '' === (string) $related ? array() : preg_split( '/\s*,\s*/', (string) $related );
		}

		$related = array_map( 'intval', (array) $related );

		if ( array_intersect( $family, $related ) ) {
			$out[] = $post;
		}

		if ( count( $out ) >= $limit ) {
			break;
		}
	}

	return $out;
}
