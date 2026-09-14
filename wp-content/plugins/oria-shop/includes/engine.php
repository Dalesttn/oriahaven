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

		$terms = wp_get_post_terms( $p->ID, Data\TAX );
		$terms = is_wp_error( $terms ) ? array() : $terms;
		$slugs = array_map( 'strval', wp_list_pluck( $terms, 'slug' ) );

		/*
		 * The first term names the card; ALL of them make it findable.
		 *
		 * A bowl filed under both singing-bowls and sound-healing is one
		 * product with two homes, and a category chip that only knows the
		 * first one quietly hides it from the other — with a count beside
		 * the chip saying so out loud.
		 */
		$rows[] = array(
			'id'        => (string) $p->ID,
			'image'     => $image ?: (string) get_post_meta( $p->ID, '_oshop_image', true ),
			'title'     => \Oria\Theme\ptitle( $p ),
			'asin'      => $asin,
			'price'     => price_label( (string) get_post_meta( $p->ID, 'price', true ) ),
			'brand'     => (string) get_post_meta( $p->ID, 'brand', true ),
			'blurb'     => (string) get_post_meta( $p->ID, 'blurb', true ),
			'category'  => $terms ? $terms[0]->name : '',
			'cat_slug'  => $terms ? $terms[0]->slug : '',
			'cat_slugs' => $slugs,
			'cat_names' => wp_list_pluck( $terms, 'name' ),
			'url'       => affiliate_url( $asin ),
			// Curation, not catalogue: written by an editor or absent.
			'note'     => (string) get_post_meta( $p->ID, 'editorial_note', true ),
			'best_for' => (string) get_post_meta( $p->ID, 'best_for', true ),
			'featured' => (bool) get_post_meta( $p->ID, 'featured', true ),
			'collections' => array_values( array_filter( (array) get_post_meta( $p->ID, 'collections', true ) ) ),
			// Derived, never asked for: one number cannot disagree with itself.
			'amount'   => price_amount( (string) get_post_meta( $p->ID, 'price', true ) ),
			// Intentions and the practices it goes with: from the category
			// maps, plus whatever the editor ticked on the product.
			'intents'   => intents_for( $slugs, (array) get_post_meta( $p->ID, 'intents', true ) ),
			'practices' => practices_for( $slugs ),
			'art'       => Data\art_url( $slugs ),
		);
		if ( count( $rows ) >= $limit ) {
			break;
		}
	}
	return $rows;
}

/**
 * The number inside a curated price string, or 0 when there is not one.
 *
 * Prices are typed by hand as display text ($49, around $49, From $115), so
 * sorting and budget bands need a number teased back out rather than a
 * second field an editor could contradict. 0 means unknown, and unknown
 * sorts last rather than pretending to be free.
 */
function price_amount( string $price ): float {
	if ( ! preg_match( '~[0-9][0-9,]*(?:[.][0-9]{1,2})?~', $price, $m ) ) {
		return 0.0;
	}

	return (float) str_replace( ',', '', $m[0] );
}

/**
 * Which budget band a price falls in, for the finder and the filters.
 *
 * Bands come from the brief. A product with no price has no band: it is
 * excluded from a budget filter rather than guessed into one.
 */
function budget_band( float $amount ): string {
	if ( $amount <= 0 ) {
		return '';
	}
	if ( $amount < 50 ) {
		return 'under-50';
	}
	if ( $amount < 100 ) {
		return '50-100';
	}
	if ( $amount < 250 ) {
		return '100-250';
	}

	return '250-plus';
}

/**
 * A price as the card says it. Editors type "49.95", "$49.95" or "around
 * $50"; a bare number gets its dollar sign, anything else is left as typed.
 */
function price_label( string $price ): string {
	$price = trim( $price );
	return preg_match( '/^\d+(?:\.\d{1,2})?$/', $price ) ? '$' . $price : $price;
}

/** The outbound URL: marketplace product page carrying the Associate tag. */
function affiliate_url( string $asin ): string {
	$url = 'https://' . Data\marketplace() . '/dp/' . rawurlencode( $asin );
	$tag = Data\tag();
	return '' === $tag ? $url : $url . '?tag=' . rawurlencode( $tag );
}

/* ------------------------------------------------- intentions, shelves */

/**
 * The intentions a product answers to: those its categories map to, plus
 * any the editor ticked on the product itself.
 *
 * @param list<string> $cat_slugs
 * @param list<string> $ticked
 * @return list<string>
 */
function intents_for( array $cat_slugs, array $ticked = array() ): array {
	$out = array();
	foreach ( $ticked as $t ) {
		if ( isset( Data\INTENTS[ (string) $t ] ) ) {
			$out[] = (string) $t;
		}
	}
	foreach ( Data\INTENTS as $slug => $intent ) {
		if ( array_intersect( $cat_slugs, $intent['cats'] ) ) {
			$out[] = $slug;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * The directory practices a product goes with, as links. Only practices
 * that exist and hold listings: a link to an empty category is not a link.
 *
 * @param list<string> $cat_slugs
 * @return list<array{name:string, url:string}>
 */
function practices_for( array $cat_slugs, int $limit = 3 ): array {
	static $memo = array();
	$key = implode( ',', $cat_slugs );
	if ( isset( $memo[ $key ] ) ) {
		return $memo[ $key ];
	}
	$wanted = array();
	foreach ( $cat_slugs as $c ) {
		foreach ( Data\CAT_PRACTICES[ $c ] ?? array() as $practice ) {
			$wanted[ $practice ] = true;
		}
	}
	$out = array();
	foreach ( array_keys( $wanted ) as $slug ) {
		$term = get_term_by( 'slug', $slug, 'practice' );
		if ( ! $term instanceof \WP_Term || (int) $term->count < 1 ) {
			continue;
		}
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			continue;
		}
		$out[] = array(
			'name' => function_exists( '\Oria\Theme\tname' ) ? \Oria\Theme\tname( $term ) : $term->name,
			'url'  => (string) $url,
		);
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $memo[ $key ] = $out;
}

/**
 * What sits on a curated shelf: products an editor ticked into it first,
 * then products from the shelf's categories, up to the limit.
 *
 * @param list<array<string,mixed>> $rows every product on the page
 * @return list<array<string,mixed>>
 */
function collection_rows( string $slug, array $rows, int $limit = 5 ): array {
	$def = Data\COLLECTIONS[ $slug ] ?? null;
	if ( ! $def ) {
		return array();
	}
	$picked = array();
	$seen   = array();
	foreach ( $rows as $r ) {
		if ( in_array( $slug, (array) ( $r['collections'] ?? array() ), true ) ) {
			$picked[]         = $r;
			$seen[ $r['id'] ] = true;
		}
	}
	foreach ( $rows as $r ) {
		if ( count( $picked ) >= $limit ) {
			break;
		}
		if ( isset( $seen[ $r['id'] ] ) || ! array_intersect( (array) $r['cat_slugs'], $def['cats'] ) ) {
			continue;
		}
		$picked[]         = $r;
		$seen[ $r['id'] ] = true;
	}
	return array_slice( $picked, 0, $limit );
}

/** The one featured product -- the most recently updated with the tick -- or null. */
function featured_row( array $rows ): ?array {
	$best = null;
	$when = 0;
	foreach ( $rows as $r ) {
		if ( empty( $r['featured'] ) ) {
			continue;
		}
		$m = (int) get_post_modified_time( 'U', true, (int) $r['id'] );
		if ( $m >= $when ) {
			$when = $m;
			$best = $r;
		}
	}
	return $best;
}

/**
 * Journal posts that have something to say about what the shop sells --
 * the ones the engine can already map products to.
 *
 * @return list<\WP_Post>
 */
function learn_more( int $n = 4 ): array {
	$out = array();
	foreach ( get_posts( array( 'posts_per_page' => 40, 'post_status' => 'publish' ) ) as $p ) {
		if ( categories_for_post( (int) $p->ID ) ) {
			$out[] = $p;
			if ( count( $out ) >= $n ) {
				break;
			}
		}
	}
	return $out;
}
