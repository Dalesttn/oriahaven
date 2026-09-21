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
 * The relation field a taxonomy stores its articles in, or '' if none.
 */
function field_for( \WP_Term $term ): string {
	if ( Taxonomies\PRACTICE === $term->taxonomy ) {
		return 'related_practices';
	}

	return Taxonomies\AREA === $term->taxonomy ? 'related_areas' : '';
}

/**
 * The term ids an article is related to.
 *
 * ACF returns the stored array, but a hand-edited value can arrive as a
 * single id or a comma string. Normalising here rather than trusting the
 * field keeps one bad row from emptying a whole column.
 *
 * @return list<int>
 */
function related_ids( int $post_id, string $field ): array {
	$ids = to_ids( get_field( $field, $post_id ) );

	/*
	 * Recovery, not belt and braces. A batch of imported articles stored
	 * this doubly serialised -- the meta holds the literal string
	 * 'a:2:{i:0;i:6;i:1;i:2;}' rather than the array it describes. ACF
	 * cannot read that and hands back array(0), which matches no term, so
	 * the article silently never appeared beside any category. That is how
	 * the singing bowl guide went missing while Sound & float, whose
	 * parent is Spa & Recovery, showed an ice bath one instead.
	 *
	 * The raw meta still has the truth, so read it when ACF comes back
	 * with nothing usable. Repairing the rows is worth doing too; this
	 * makes the reader correct either way.
	 */
	if ( ! $ids ) {
		$ids = to_ids( maybe_unserialize( get_post_meta( $post_id, $field, true ) ) );
	}

	return $ids;
}

/**
 * Term ids out of whatever a relation field hands over: an array of ids,
 * of WP_Term objects, a single id, or a comma string. Zeros are dropped --
 * they are the shape a bad value arrives in, never a real term.
 *
 * @return list<int>
 */
function to_ids( $value ): array {
	if ( is_string( $value ) && is_serialized( $value ) ) {
		$value = maybe_unserialize( $value );
	}

	if ( ! is_array( $value ) ) {
		$value = '' === (string) $value ? array() : preg_split( '/\s*,\s*/', (string) $value );
	}

	$out = array();
	foreach ( (array) $value as $v ) {
		$id = (int) ( is_object( $v ) ? ( $v->term_id ?? 0 ) : $v );
		if ( $id > 0 ) {
			$out[] = $id;
		}
	}

	return array_values( array_unique( $out ) );
}

/**
 * The one guide a page should feature -- the Oria Note's "Read the full
 * guide".
 *
 * for_term() returns the family newest first and the Note took [0]. The
 * family reaches upwards, so Sound & float, whose parent is Spa &
 * Recovery, inherited the spa article: a page of sound baths and singing
 * bowls offering "Sauna, ice bath or float: which recovery session to
 * book first".
 *
 * Two signals, both deliberately narrow. This file's rule is that
 * MEMBERSHIP is curated and never guessed -- keywords would put the
 * acupuncture article on half the directory. Nothing here changes which
 * articles belong to a page; it only ORDERS the ones an editor has
 * already put there:
 *
 *   direct  the article names THIS term, not merely a relative of it
 *   topic   on a facet page, the facet's own words are in the title
 *
 * Ties keep for_term()'s order, which is newest first.
 *
 * @param string $topic The facet this page is locked to, if any.
 * @param list<\WP_Post> $guides Defaults to the page's own guides.
 */
function best_for( \WP_Term $term, string $topic = '', array $guides = array() ): ?\WP_Post {
	$guides = $guides ?: for_term( $term );
	if ( ! $guides ) {
		return null;
	}

	$field = field_for( $term );
	$words = array_values(
		array_filter(
			preg_split( '/[^a-z0-9]+/', strtolower( $topic ) ) ?: array(),
			static fn( string $w ): bool => strlen( $w ) > 2
		)
	);
	$phrase = implode( ' ', $words );

	$family = family( $term );
	$best   = null;
	$score  = -1;

	foreach ( $guides as $post ) {
		$n   = 0;
		$rel = '' === $field ? array() : related_ids( (int) $post->ID, $field );

		if ( in_array( (int) $term->term_id, $rel, true ) ) {
			$n += 4;
		}

		/*
		 * How much of this page's family the editor tied the article to.
		 * Neither guide names Spa & Recovery itself, but "Sauna, ice bath
		 * or float" is tied to two of its children and the singing bowl
		 * one to a single child, which is the difference between an
		 * article about this page and an article about a corner of it.
		 */
		$n += count( array_intersect( $family, $rel ) );

		if ( $words ) {
			$hay = strtolower( $post->post_title . ' ' . $post->post_name );
			$hay = str_replace( '-', ' ', $hay );
			if ( '' !== $phrase && str_contains( $hay, $phrase ) ) {
				$n += 3;
			} else {
				foreach ( $words as $w ) {
					if ( str_contains( $hay, $w ) ) {
						$n += 1;
						break;
					}
				}
			}
		}

		// Strictly greater, so equal scores keep the incoming (newest) order.
		if ( $n > $score ) {
			$score = $n;
			$best  = $post;
		}
	}

	return $best;
}

/**
 * Articles related to a term.
 *
 * @return list<\WP_Post>
 */
function for_term( \WP_Term $term, int $limit = LIMIT ): array {
	$field = field_for( $term );

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
		if ( array_intersect( $family, related_ids( (int) $post->ID, $field ) ) ) {
			$out[] = $post;
		}

		if ( count( $out ) >= $limit ) {
			break;
		}
	}

	return $out;
}
