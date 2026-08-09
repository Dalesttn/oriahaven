<?php
/**
 * The recommendation engine. Context in (a practice term, a journal post,
 * or explicit category slugs) → product-category terms → products from the
 * local catalogue, deduped by ASIN, capped, with affiliate URLs built from
 * the configured tag. Reads only the catalogue — providers fill it.
 */

declare(strict_types=1);

namespace Oria\Shop\Engine;

use Oria\Shop\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Product-category term ids for a practice term (editor mapping first, defaults second). */
function categories_for_practice( \WP_Term $practice ): array {
	$ids = function_exists( 'get_field' ) ? array_filter( array_map( 'intval', (array) ( get_field( 'product_categories', 'practice_' . $practice->term_id ) ?: array() ) ) ) : array();
	if ( $ids ) {
		return array_values( $ids );
	}
	return ids_from_slugs( Data\DEFAULT_MAP[ $practice->slug ] ?? array() );
}

/** Product-category term ids for a journal post (override first, tag map second). */
function categories_for_post( int $post_id ): array {
	$ids = function_exists( 'get_field' ) ? array_filter( array_map( 'intval', (array) ( get_field( 'product_categories', $post_id ) ?: array() ) ) ) : array();
	if ( $ids ) {
		return array_values( $ids );
	}

	$slugs = array();
	$words = array();
	// get_the_tags() returns false (not []) for an untagged post, and
	// (array) false is [false] — walk and type-check instead of casting.
	$tags  = get_the_tags( $post_id );
	foreach ( array_merge( is_array( $tags ) ? $tags : array(), get_the_category( $post_id ) ) as $term ) {
		if ( $term instanceof \WP_Term ) {
			$words[] = strtolower( $term->name );
		}
	}
	// The post title often names the topic when tags are sparse.
	$words[] = strtolower( (string) get_post_field( 'post_title', $post_id, 'raw' ) );

	foreach ( Data\TAG_MAP as $needle => $cats ) {
		foreach ( $words as $word ) {
			if ( str_contains( $word, $needle ) ) {
				$slugs = array_merge( $slugs, $cats );
				break;
			}
		}
	}
	return ids_from_slugs( array_unique( $slugs ) );
}

/** @param array<string> $slugs */
function ids_from_slugs( array $slugs ): array {
	$ids = array();
	foreach ( $slugs as $slug ) {
		$term = get_term_by( 'slug', $slug, Data\TAX );
		if ( $term instanceof \WP_Term ) {
			$ids[] = (int) $term->term_id;
		}
	}
	return $ids;
}

/**
 * Products for a set of product-category term ids: published catalogue
 * entries, deduped by ASIN, newest curation first, capped.
 *
 * @return array<int, array<string, string>> display-ready product rows.
 */
function products( array $cat_ids, int $limit = 0 ): array {
	if ( ! $cat_ids ) {
		return array();
	}
	$limit = $limit > 0 ? $limit : Data\per_band();

	$posts = get_posts(
		array(
			'post_type'      => Data\CPT,
			'post_status'    => 'publish',
			'posts_per_page' => $limit * 3, // room to dedupe and vary.
			'tax_query'      => array(
				array(
					'taxonomy' => Data\TAX,
					'field'    => 'term_id',
					'terms'    => $cat_ids,
				),
			),
		)
	);

	$seen = array();
	return build_rows( $posts, $limit, $seen );
}

/**
 * Products for a practice page: those pinned to the practice on their
 * edit screens lead, then the practice's category mapping fills the
 * remaining slots.
 */
function products_for_practice( \WP_Term $practice, int $limit = 0 ): array {
	$limit = $limit > 0 ? $limit : Data\per_band();

	$pinned = get_posts(
		array(
			'post_type'      => Data\CPT,
			'post_status'    => 'publish',
			'posts_per_page' => $limit * 2,
			// ACF multi_select stores a serialized id array — match the
			// quoted id so 16 never matches 160.
			'meta_query'     => array(
				array(
					'key'     => 'practices',
					'value'   => '"' . $practice->term_id . '"',
					'compare' => 'LIKE',
				),
			),
		)
	);

	$seen = array();
	$rows = build_rows( $pinned, $limit, $seen );
	if ( count( $rows ) < $limit ) {
		foreach ( products( categories_for_practice( $practice ), $limit ) as $row ) {
			if ( count( $rows ) >= $limit ) {
				break;
			}
			if ( ! isset( $seen[ $row['asin'] ] ) ) {
				$seen[ $row['asin'] ] = true;
				$rows[]               = $row;
			}
		}
	}
	return $rows;
}

/**
 * Posts → display rows, deduped by ASIN across calls via $seen.
 *
 * @param array<int, \WP_Post> $posts
 * @param array<string, bool>  $seen
 * @return array<int, array<string, string>>
 */
function build_rows( array $posts, int $limit, array &$seen ): array {
	$rows = array();
	foreach ( $posts as $p ) {
		$asin = strtoupper( trim( (string) get_post_meta( $p->ID, 'asin', true ) ) );
		if ( '' === $asin || isset( $seen[ $asin ] ) ) {
			continue;
		}
		$seen[ $asin ] = true;

		// The owner's uploaded image wins; the API image fills in otherwise.
		$upload = (int) get_post_meta( $p->ID, 'image', true );
		$image  = $upload ? (string) wp_get_attachment_image_url( $upload, 'medium_large' ) : '';

		$terms  = wp_get_post_terms( $p->ID, Data\TAX );
		$rows[] = array(
			'id'       => (string) $p->ID,
			'image'    => $image ?: (string) get_post_meta( $p->ID, '_oshop_image', true ),
			'title'    => \Oria\Theme\ptitle( $p ),
			'asin'     => $asin,
			'price'    => (string) get_post_meta( $p->ID, 'price', true ),
			'brand'    => (string) get_post_meta( $p->ID, 'brand', true ),
			'blurb'    => (string) get_post_meta( $p->ID, 'blurb', true ),
			'category' => ! is_wp_error( $terms ) && $terms ? $terms[0]->name : '',
			'url'      => affiliate_url( $asin ),
		);
		if ( count( $rows ) >= $limit ) {
			break;
		}
	}
	return $rows;
}

/** The outbound URL: marketplace product page carrying the Associate tag. */
function affiliate_url( string $asin ): string {
	$url = 'https://' . Data\marketplace() . '/dp/' . rawurlencode( $asin );
	$tag = Data\tag();
	return '' === $tag ? $url : $url . '?tag=' . rawurlencode( $tag );
}
