<?php
/**
 * Reading apps out of the catalogue, and deciding which ones belong where.
 *
 * Every template asks this file rather than the database, so an app says
 * the same thing on its own page, on a card, and in a band on a practice
 * page. Ordering is editorial throughout: menu_order first, then title.
 * Nothing here knows or cares whether an app carries an affiliate link.
 */

declare(strict_types=1);

namespace Oria\Apps\Engine;

use Oria\Apps\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One app, flattened to what a template needs.
 *
 * @return array<string, mixed>
 */
function row( int $id ): array {
	static $memo = array();
	if ( isset( $memo[ $id ] ) ) {
		return $memo[ $id ];
	}

	$post  = get_post( $id );
	$terms = wp_get_post_terms( $id, Data\TAX );
	$terms = is_wp_error( $terms ) ? array() : $terms;
	$terms = primary_first( $terms, (string) get_post_meta( $id, 'primary_category', true ) );
	$meta  = static fn( string $k ) => get_post_meta( $id, $k, true );

	$affiliate = (bool) $meta( 'affiliate_available' ) && '' !== (string) $meta( 'affiliate_url' );
	$official  = (string) $meta( 'official_website' );

	return $memo[ $id ] = array(
		'id'          => $id,
		'title'       => $post instanceof \WP_Post ? wp_specialchars_decode( $post->post_title ) : '',
		'url'         => (string) get_permalink( $id ),
		'tagline'     => (string) $meta( 'app_tagline' ),
		'excerpt'     => $post instanceof \WP_Post ? $post->post_excerpt : '',
		'developer'   => (string) $meta( 'developer_name' ),
		'logo'        => logo_url( $id ),
		'cats'        => $terms,
		'cat_slugs'   => array_map( 'strval', wp_list_pluck( $terms, 'slug' ) ),
		'cat_names'   => array_map( static fn( $t ) => wp_specialchars_decode( $t->name ), $terms ),
		'best_for'    => array_values( array_filter( (array) $meta( 'best_for' ) ) ),
		'take'        => (string) $meta( 'oria_take' ),
		'features'    => rows_of( $id, 'key_features', 'text' ),
		'pros'        => rows_of( $id, 'pros', 'text' ),
		'cons'        => rows_of( $id, 'considerations', 'text' ),
		'faq'         => faq( $id ),
		'pricing'     => (string) ( $meta( 'pricing_model' ) ?: 'unknown' ),
		'price'       => (string) $meta( 'starting_price' ),
		'free'        => (bool) $meta( 'free_version_available' ),
		'trial'       => (string) $meta( 'free_trial' ),
		'price_notes' => (string) $meta( 'pricing_notes' ),
		'platforms'   => array_values( array_filter( (array) $meta( 'platforms' ) ) ),
		'official'    => $official,
		'ios'         => (string) $meta( 'ios_url' ),
		'android'     => (string) $meta( 'android_url' ),
		// The outbound link: the affiliate one when there is one, and the
		// official site otherwise. Both are labelled the same to a reader;
		// the disclosure, not the link text, is what does the telling.
		'affiliate'   => $affiliate,
		'outbound'    => $affiliate ? (string) $meta( 'affiliate_url' ) : $official,
		'verified'    => (string) $meta( 'last_verified' ),
		'sources'     => array_values( array_filter( array( (string) $meta( 'primary_source_url' ), (string) $meta( 'secondary_source_url' ), (string) $meta( 'pricing_source_url' ) ) ) ),
		'pick'        => (bool) $meta( 'oria_pick' ),
	);
}

/**
 * The app's main category, first.
 *
 * Terms come back from WordPress in alphabetical order, which is not what
 * an app is mainly for: Insight Timer is a meditation app that also does
 * breathwork, and "Breathwork" is simply what sorts first. Wherever one
 * category has to stand for the app -- the card, the comparison table's
 * focus column, the SEO title -- that had it describing the wrong thing.
 *
 * The editor's choice wins; without one, alphabetical is what is left.
 *
 * @param list<\WP_Term> $terms
 * @return list<\WP_Term>
 */
function primary_first( array $terms, string $primary ): array {
	if ( '' === $primary ) {
		return $terms;
	}
	$first = array();
	$rest  = array();
	foreach ( $terms as $term ) {
		if ( $term->slug === $primary ) {
			$first[] = $term;
		} else {
			$rest[] = $term;
		}
	}
	return array_merge( $first, $rest );
}

/**
 * The rows of a repeater.
 *
 * ACF stores the row COUNT in the parent key and each row under
 * {field}_{i}_{sub}. Casting that count to an array, as the first version
 * did, produces a single-element array and reads exactly one row -- so an
 * app with four features showed one, silently and on every surface.
 *
 * @return list<string>
 */
function rows_of( int $id, string $field, string $sub ): array {
	$count = (int) get_post_meta( $id, $field, true );
	$out   = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$text = (string) get_post_meta( $id, $field . '_' . $i . '_' . $sub, true );
		if ( '' !== trim( $text ) ) {
			$out[] = $text;
		}
	}
	return $out;
}

/** @return list<array{question:string, answer:string}> */
function faq( int $id ): array {
	$out   = array();
	$count = (int) get_post_meta( $id, 'faq', true );
	for ( $i = 0; $i < $count; $i++ ) {
		$q = (string) get_post_meta( $id, 'faq_' . $i . '_question', true );
		$a = (string) get_post_meta( $id, 'faq_' . $i . '_answer', true );
		if ( '' !== trim( $q ) && '' !== trim( $a ) ) {
			$out[] = array( 'question' => $q, 'answer' => $a );
		}
	}
	return $out;
}

/** The app's own icon, else its cover image, else ''. */
function logo_url( int $id ): string {
	$logo = (int) get_post_meta( $id, 'app_logo', true );
	if ( $logo ) {
		$src = wp_get_attachment_image_url( $logo, 'thumbnail' );
		if ( $src ) {
			return (string) $src;
		}
	}
	$thumb = get_the_post_thumbnail_url( $id, 'thumbnail' );
	return $thumb ? (string) $thumb : '';
}

/**
 * Published apps, newest editorial order first.
 *
 * @param array<int, int|string> $cat  category ids or slugs, or empty for all
 * @return list<array<string, mixed>>
 */
function apps( array $cats = array(), int $limit = 0 ): array {
	$args = array(
		'post_type'      => Data\CPT,
		'post_status'    => 'publish',
		'posts_per_page' => $limit > 0 ? $limit : -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'no_found_rows'  => true,
	);
	if ( $cats ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => Data\TAX,
				'field'    => is_numeric( reset( $cats ) ) ? 'term_id' : 'slug',
				'terms'    => $cats,
			),
		);
	}
	return array_map( __NAMESPACE__ . '\row', get_posts( $args ) ? wp_list_pluck( get_posts( $args ), 'ID' ) : array() );
}

/** Apps whose categories map to this practice, for a band on its page. */
function for_practice( \WP_Term $practice, int $limit = 3 ): array {
	$slugs = array();
	foreach ( Data\PRACTICE_MAP as $cat => $practices ) {
		if ( in_array( $practice->slug, $practices, true ) ) {
			$slugs[] = $cat;
		}
	}

	$rows = array();
	// An app that names this practice itself wins over the category map.
	foreach ( apps() as $row ) {
		$named = array_map( 'intval', (array) get_post_meta( (int) $row['id'], 'practices', true ) );
		if ( in_array( (int) $practice->term_id, $named, true ) ) {
			$rows[ $row['id'] ] = $row;
		}
	}
	if ( $slugs ) {
		foreach ( apps( $slugs ) as $row ) {
			$rows[ $row['id'] ] = $rows[ $row['id'] ] ?? $row;
		}
	}
	return array_slice( array_values( $rows ), 0, $limit );
}

/**
 * The directory practices an app belongs beside, as links.
 *
 * Only practices that exist and hold listings: "take your wellness
 * offline" pointing at an empty page is worse than not offering it.
 *
 * @param list<string> $cat_slugs
 * @return list<array{name:string, url:string}>
 */
function practices_for( array $cat_slugs, array $named = array(), int $limit = 4 ): array {
	$terms = array();
	foreach ( $named as $id ) {
		$t = get_term( (int) $id, 'practice' );
		if ( $t instanceof \WP_Term ) {
			$terms[ $t->slug ] = $t;
		}
	}
	foreach ( $cat_slugs as $slug ) {
		foreach ( Data\PRACTICE_MAP[ $slug ] ?? array() as $practice ) {
			if ( isset( $terms[ $practice ] ) ) {
				continue;
			}
			$t = get_term_by( 'slug', $practice, 'practice' );
			if ( $t instanceof \WP_Term ) {
				$terms[ $practice ] = $t;
			}
		}
	}

	$out = array();
	foreach ( $terms as $term ) {
		if ( (int) $term->count < 1 ) {
			continue;
		}
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			continue;
		}
		$out[] = array(
			'name' => function_exists( '\Oria\Theme\tname' ) ? \Oria\Theme\tname( $term ) : wp_specialchars_decode( $term->name ),
			'url'  => (string) $url,
		);
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * Apps like this one: the editor's own choices first, then shared
 * categories, then shared "best for".
 *
 * @return list<array<string, mixed>>
 */
function related( int $id, int $limit = 3 ): array {
	$me   = row( $id );
	$out  = array();
	$seen = array( $id => true );

	foreach ( (array) get_post_meta( $id, 'related_apps', true ) as $rel ) {
		$rel = (int) $rel;
		if ( $rel && ! isset( $seen[ $rel ] ) && 'publish' === get_post_status( $rel ) ) {
			$out[]        = row( $rel );
			$seen[ $rel ] = true;
		}
	}
	foreach ( array( 'cat_slugs', 'best_for' ) as $key ) {
		foreach ( apps() as $row ) {
			if ( count( $out ) >= $limit ) {
				break 2;
			}
			if ( isset( $seen[ $row['id'] ] ) || ! array_intersect( (array) $row[ $key ], (array) $me[ $key ] ) ) {
				continue;
			}
			$out[]                = $row;
			$seen[ $row['id'] ] = true;
		}
	}
	return array_slice( $out, 0, $limit );
}

/** Apps an editor has marked as a pick. */
function picks( int $limit = 4 ): array {
	return array_slice( array_values( array_filter( apps(), static fn( array $r ): bool => (bool) $r['pick'] ) ), 0, $limit );
}

/** Apps suiting a given audience, for the collection pages. */
function by_best_for( string $key, int $limit = 0 ): array {
	$rows = array_values( array_filter( apps(), static fn( array $r ): bool => in_array( $key, (array) $r['best_for'], true ) ) );
	return $limit > 0 ? array_slice( $rows, 0, $limit ) : $rows;
}

/** How the price reads on a card. Never invents a number. */
function price_line( array $row ): string {
	$model = Data\label( 'pricing', (string) $row['pricing'] );
	if ( 'free' === $row['pricing'] ) {
		return __( 'Free', 'oria' );
	}
	if ( $row['free'] ) {
		return sprintf(
			/* translators: %s: pricing model, e.g. Freemium */
			__( '%s · free version', 'oria' ),
			$model
		);
	}
	return 'unknown' === $row['pricing'] ? __( 'See the app for pricing', 'oria' ) : $model;
}

/** Whether an app's facts are older than we are comfortable showing. */
function stale( string $verified ): bool {
	if ( '' === $verified ) {
		return true;
	}
	return ( time() - (int) strtotime( $verified ) ) > Data\STALE_DAYS * DAY_IN_SECONDS;
}
