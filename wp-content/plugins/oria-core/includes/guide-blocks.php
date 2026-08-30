<?php
/**
 * The blocks a guide uses to hand its reader to the directory.
 *
 * Shortcodes rather than hand-pasted HTML, because the listings move: a
 * studio closes, a price changes, a new one opens in Subiaco -- and a
 * guide quoting last month's directory is exactly the stale SEO page the
 * journal exists to not be. Each render reads the live directory.
 *
 * [oria_listings svc="reformer-pilates" n="4"]  cards for practices
 *     offering the service, best-rated first. Pass slugs="a,b,c" to
 *     name the picks instead, in that order.
 * [oria_journey]  the article's own "day at a glance" timeline, from the
 *     journey repeater on the post. Each step reads its listing live.
 * [oria_events type="sound-healing" n="4"]  the next sessions of a
 *     kind, from the events aggregator. Renders nothing when the
 *     diary is empty, which is the honest answer that week.
 * [oria_suburbs svc="reformer-pilates" cat="fitness"]  links to the
 *     category x suburb pages that actually contain these practices.
 */

declare(strict_types=1);

namespace Oria\Core\GuideBlocks;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_shortcode( 'oria_listings', __NAMESPACE__ . '\listings' );
	add_shortcode( 'oria_suburbs', __NAMESPACE__ . '\suburbs' );
	add_shortcode( 'oria_events', __NAMESPACE__ . '\events' );
	add_shortcode( 'oria_journey', __NAMESPACE__ . '\journey' );
}

/**
 * Listings for a guide block, best-rated first. Filter by service slugs,
 * an area (suburb or region -- children included), or both.
 */
function ids_for( array $svc_slugs, int $limit, string $area = '' ): array {
	$tax = array();
	if ( $svc_slugs ) {
		$tax[] = array( 'taxonomy' => 'service', 'field' => 'slug', 'terms' => $svc_slugs );
	}
	if ( '' !== $area ) {
		$tax[] = array( 'taxonomy' => Taxonomies\AREA, 'field' => 'slug', 'terms' => array( $area ), 'include_children' => true );
	}
	if ( ! $tax ) {
		return array();
	}
	$q = new \WP_Query(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => $tax,
		)
	);
	$ids = array_map( 'intval', $q->posts );

	// Rating carries the sort; unrated listings still appear, after.
	usort(
		$ids,
		static function ( int $a, int $b ): int {
			$ra = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( $a ) : array( 'rating' => 0, 'count' => 0 );
			$rb = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( $b ) : array( 'rating' => 0, 'count' => 0 );
			return ( $rb['rating'] <=> $ra['rating'] ) ?: ( $rb['count'] <=> $ra['count'] );
		}
	);
	return array_slice( $ids, 0, $limit );
}

function listings( $atts ): string {
	$a    = shortcode_atts( array( 'svc' => '', 'area' => '', 'n' => 4, 'slugs' => '' ), $atts );
	$svcs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $a['svc'] ) ) );
	$area = sanitize_title( (string) $a['area'] );
	$n    = max( 1, min( 8, (int) $a['n'] ) );

	/*
	 * `slugs` is the editorial override: name the practices and they render
	 * in that order. Rating order is the right default for "who is best at
	 * this", but a guide that recommends three places in its prose and then
	 * lists four different ones underneath contradicts itself. A slug that
	 * no longer resolves is skipped rather than rendered as a gap.
	 */
	$named = array_filter( array_map( 'sanitize_title', explode( ',', (string) $a['slugs'] ) ) );
	if ( $named ) {
		$ids = array();
		foreach ( $named as $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'listing' );
			if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
				$ids[] = (int) $post->ID;
			}
		}
		$ids = array_slice( $ids, 0, $n );
		if ( ! $ids ) {
			return '';
		}
		return cards( $ids );
	}

	if ( ! $svcs && '' === $area ) {
		return '';
	}
	$ids = ids_for( $svcs, $n, $area );
	if ( ! $ids ) {
		return '';
	}
	return cards( $ids );
}

/** The card markup, shared by the rated and the named paths. */
function cards( array $ids ): string {
	$out = '<div class="guidecards">';
	foreach ( $ids as $id ) {
		$name   = function_exists( '\Oria\Theme\ptitle' ) ? \Oria\Theme\ptitle( $id ) : get_the_title( $id );
		$img    = function_exists( '\Oria\Theme\listing_image' ) ? \Oria\Theme\listing_image( $id ) : '';
		$rated  = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( $id ) : array( 'rating' => 0, 'count' => 0 );
		$suburb = '';
		foreach ( wp_get_post_terms( $id, 'area' ) as $t ) {
			if ( $t->parent ) {
				$suburb = html_entity_decode( $t->name, ENT_QUOTES );
				break;
			}
		}
		$from = (float) get_field( 'price_from', $id );

		$out .= '<a class="guidecard" href="' . esc_url( get_permalink( $id ) ) . '">';
		if ( $img ) {
			$out .= '<span class="guidecard__media"><img class="guidecard__img" src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async"></span>';
		}
		$out .= '<span class="guidecard__body">';
		$out .= '<b class="guidecard__name">' . esc_html( $name ) . '</b>';
		if ( $suburb ) {
			$out .= '<span class="guidecard__meta">' . esc_html( $suburb ) . '</span>';
		}
		if ( $rated['rating'] > 0 ) {
			/* The practice's own Google rating, reproduced -- same source every card on the site uses. */
			$out .= '<span class="guidecard__meta">&#9733; ' . esc_html( number_format_i18n( $rated['rating'], 1 ) ) . ' (' . esc_html( number_format_i18n( $rated['count'] ) ) . ')</span>';
		}
		if ( $from > 0 ) {
			$out .= '<span class="guidecard__meta">' . sprintf( esc_html__( 'From $%s', 'oria' ), esc_html( number_format_i18n( $from ) ) ) . '</span>';
		}
		$out .= '<span class="guidecard__go">' . esc_html__( 'View practice', 'oria' ) . ' &rarr;</span>';
		$out .= '</span></a>';
	}
	$out .= '</div>';
	return $out;
}

function suburbs( $atts ): string {
	$a    = shortcode_atts( array( 'svc' => '', 'cat' => '' ), $atts );
	$svcs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $a['svc'] ) ) );
	$cat  = sanitize_title( (string) $a['cat'] );
	if ( ! $svcs || '' === $cat ) {
		return '';
	}
	$term = get_term_by( 'slug', $cat, Taxonomies\PRACTICE );
	if ( ! $term instanceof \WP_Term ) {
		return '';
	}

	// Suburbs come from the listings themselves, so a link never points at
	// an empty page.
	$seen = array();
	foreach ( ids_for( $svcs, 50 ) as $id ) {
		foreach ( wp_get_post_terms( $id, 'area' ) as $t ) {
			if ( $t->parent && ! isset( $seen[ $t->slug ] ) ) {
				$seen[ $t->slug ] = html_entity_decode( $t->name, ENT_QUOTES );
			}
		}
	}
	if ( ! $seen ) {
		return '';
	}

	/*
	 * Grouped by region, because "Fremantle & South" is how a person asks
	 * the question. Region names and membership come from the area
	 * taxonomy itself, never a hand-kept list.
	 */
	$groups = array();
	foreach ( $seen as $slug => $name ) {
		$sub    = get_term_by( 'slug', $slug, Taxonomies\AREA );
		$region = $sub && function_exists( '\Oria\Core\Taxonomies\region_for' )
			? Taxonomies\region_for( $sub )
			: null;
		$label  = $region instanceof \WP_Term ? html_entity_decode( $region->name, ENT_QUOTES ) : __( 'Elsewhere', 'oria' );
		$groups[ $label ][ $slug ] = $name;
	}
	ksort( $groups );

	$out = '<div class="guideburbs guideburbs--grouped">';
	foreach ( $groups as $label => $burbs ) {
		asort( $burbs );
		$out .= '<div class="guideburbs__group"><span class="guideburbs__region">' . esc_html( $label ) . '</span>';
		foreach ( $burbs as $slug => $name ) {
			$url  = home_url( '/practices/' . $term->slug . '/' . $slug . '/' );
			$out .= '<a class="pill pill--sand" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
		}
		$out .= '</div>';
	}
	$out .= '</div>';
	return $out;
}

bootstrap();

/**
 * Upcoming events of one kind, soonest first.
 *
 * A guide that names three sessions goes stale the week after they run.
 * This reads the diary at render time instead, so the article is right in
 * October without anybody editing it -- and shows nothing at all when
 * there is nothing on, rather than an empty box or a stale list.
 */
function events( $atts ): string {
	$a    = shortcode_atts( array( 'type' => '', 'n' => 4 ), $atts );
	$type = sanitize_title( (string) $a['type'] );
	if ( '' === $type ) {
		return '';
	}
	$ids = get_posts(
		array(
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 8, (int) $a['n'] ) ),
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => 'event_start',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => 'event_start',
					'value'   => current_time( 'mysql' ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
			'tax_query'      => array(
				array( 'taxonomy' => 'event_type', 'field' => 'slug', 'terms' => array( $type ) ),
			),
		)
	);
	$ids = array_filter( array_map( 'intval', $ids ) );
	if ( ! $ids ) {
		return '';
	}

	$out = '<div class="guideevents">';
	foreach ( $ids as $id ) {
		$start = (string) get_post_meta( $id, 'event_start', true );
		$venue = trim( (string) get_post_meta( $id, 'venue', true ) );
		$price = trim( (string) get_post_meta( $id, 'price', true ) );
		$img   = (string) get_the_post_thumbnail_url( $id, 'medium' );

		$out .= '<a class="guideevent" href="' . esc_url( (string) get_permalink( $id ) ) . '">';
		if ( $img ) {
			$out .= '<img class="guideevent__img" src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async">';
		}
		$out .= '<span class="guideevent__body">';
		if ( '' !== $start ) {
			/*
			 * Date only. A good share of the imported diary carries a
			 * midnight time it never meant, and "12.00am" on a sound bath
			 * reads as broken data -- because it is.
			 */
			$out .= '<span class="guideevent__when">' . esc_html( mysql2date( 'D j M', $start ) ) . '</span>';
		}
		$out .= '<b class="guideevent__name">' . esc_html( (string) get_the_title( $id ) ) . '</b>';
		$meta = array_filter( array( $venue, $price ) );
		if ( $meta ) {
			$out .= '<span class="guideevent__meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
		}
		$out .= '</span></a>';
	}
	$out .= '</div>';
	return $out;
}

/**
 * The day-at-a-glance timeline.
 *
 * Steps are ordered by the editor; everything about the place -- name,
 * suburb, price, photo, whether it still exists -- is read from the listing
 * at render time. That is the whole point of building a journey out of
 * listings rather than typing one: when a studio closes or moves, every
 * journey that sends someone there corrects itself.
 *
 * A step whose listing has been deleted keeps its time and label and simply
 * stops being a link, because a broken day is worse than an incomplete one.
 */
function journey( $atts ): string {
	$a    = shortcode_atts( array( 'id' => 0 ), $atts );
	$post = (int) $a['id'] ?: get_the_ID();
	if ( ! $post || ! function_exists( 'get_field' ) ) {
		return '';
	}
	$rows = (array) ( get_field( 'journey', $post ) ?: array() );
	$rows = array_values( array_filter( $rows, static function ( $r ): bool {
		return is_array( $r ) && ( '' !== trim( (string) ( $r['label'] ?? '' ) ) || '' !== trim( (string) ( $r['time'] ?? '' ) ) );
	} ) );
	if ( ! $rows ) {
		return '';
	}

	$out = '<div class="journey"><ol class="journey__list">';
	foreach ( $rows as $row ) {
		$time  = trim( (string) ( $row['time'] ?? '' ) );
		$icon  = trim( (string) ( $row['icon'] ?? '' ) );
		$label = trim( (string) ( $row['label'] ?? '' ) );
		$note  = trim( (string) ( $row['note'] ?? '' ) );
		$id    = (int) ( $row['listing'] ?? 0 );
		$live  = $id && 'publish' === get_post_status( $id );

		$out .= '<li class="journey__step">';
		$out .= '<div class="journey__when">';
		if ( '' !== $time ) {
			$out .= '<span class="journey__time">' . esc_html( $time ) . '</span>';
		}
		$out .= '</div>';

		$out .= '<div class="journey__what">';
		$out .= '<span class="journey__label">';
		if ( '' !== $icon ) {
			$out .= '<span class="journey__icon" aria-hidden="true">' . esc_html( $icon ) . '</span>';
		}
		$out .= esc_html( $label ) . '</span>';

		if ( $live ) {
			$name   = function_exists( '\Oria\Theme\ptitle' ) ? \Oria\Theme\ptitle( $id ) : get_the_title( $id );
			/*
			 * A real photograph only. listing_image() falls back to one of the
			 * theme's scene illustrations, which are pale by design and at 52px
			 * read as an empty box beside the name rather than as a picture.
			 * Better no thumbnail than a smudge: the row closes up without one.
			 */
			$img = (string) get_the_post_thumbnail_url( $id, 'oria-card' );
			if ( '' === $img && function_exists( '\Oria\Core\Places\card_photo' ) ) {
				$img = (string) \Oria\Core\Places\card_photo( $id );
			}
			$suburb = '';
			foreach ( wp_get_post_terms( $id, 'area' ) as $t ) {
				if ( $t->parent ) {
					$suburb = html_entity_decode( $t->name, ENT_QUOTES );
					break;
				}
			}
			$from = (float) get_field( 'price_from', $id );
			$meta = array_filter( array( $suburb, $from > 0 ? sprintf( 'from $%s', number_format_i18n( $from ) ) : '' ) );

			$out .= '<a class="journey__place" href="' . esc_url( (string) get_permalink( $id ) ) . '">';
			if ( $img ) {
				$out .= '<img class="journey__img" src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async">';
			}
			$out .= '<span class="journey__placebody">';
			$out .= '<b class="journey__name">' . esc_html( $name ) . '</b>';
			if ( $meta ) {
				$out .= '<span class="journey__meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
			}
			$out .= '</span></a>';
		}

		if ( '' !== $note ) {
			$out .= '<span class="journey__note">' . esc_html( $note ) . '</span>';
		}
		$out .= '</div></li>';
	}
	$out .= '</ol></div>';
	return $out;
}

