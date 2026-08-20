<?php
/**
 * Oria theme bootstrap.
 *
 * The design system was built dependency-free (no build step), so this file
 * only has three jobs: theme supports, enqueueing the existing CSS/JS in the
 * right order, and a few small helpers the templates share.
 */

declare(strict_types=1);

namespace Oria\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION = '0.1.0';

// Global-namespace shims (an ACF fallback) live in their own file — defining
// them here would put them in Oria\Theme and shadow the real ACF functions.
require_once __DIR__ . '/includes/compat.php';

/* -------------------------------------------------------------------------
 * Supports
 * ---------------------------------------------------------------------- */
add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'responsive-embeds' );

		register_nav_menus(
			array(
				'primary' => __( 'Primary navigation', 'oria' ),
			)
		);

		// Card ratios the prototype uses.
		add_image_size( 'oria-card', 720, 540, true );      // 4:3 listing/journal cards
		add_image_size( 'oria-portrait', 660, 880, true );  // 3:4 featured cards
		add_image_size( 'oria-wide', 1920, 1080, true );    // hero / CTA slabs
	}
);

/* -------------------------------------------------------------------------
 * Assets — same four stylesheets, same order as the prototype
 * ---------------------------------------------------------------------- */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$dir = get_template_directory();
		$uri = get_template_directory_uri();

		/*
		 * Fonts are self-hosted (assets/fonts, declared in fonts.css).
		 * They used to come from fonts.googleapis.com, which meant a
		 * render-blocking stylesheet on one third-party origin and the
		 * font files on a second — two lots of DNS and TLS before any
		 * text could paint, which is expensive on a phone. No preconnect
		 * hints are needed now: the fonts share our own connection.
		 */
		wp_enqueue_style( 'oria-fonts', "{$uri}/assets/css/fonts.css", array(), (string) filemtime( "{$dir}/assets/css/fonts.css" ) );

		foreach ( array( 'tokens', 'base', 'components', 'pages', 'forms' ) as $sheet ) {
			wp_enqueue_style(
				"oria-{$sheet}",
				"{$uri}/assets/css/{$sheet}.css",
				'tokens' === $sheet ? array( 'oria-fonts' ) : array( "oria-tokens" ),
				(string) filemtime( "{$dir}/assets/css/{$sheet}.css" )
			);
		}

		/*
		 * One tint rule per category, generated from the same file the
		 * categories themselves come from. Inline rather than a checked-in
		 * stylesheet so adding a category cannot leave its chip unstyled.
		 */
		if ( function_exists( '\Oria\Core\Categories\tint_css' ) ) {
			wp_add_inline_style( 'oria-components', \Oria\Core\Categories\tint_css() );
		}

		wp_enqueue_script(
			'oria-app',
			"{$uri}/assets/js/app.js",
			array(),
			(string) filemtime( "{$dir}/assets/js/app.js" ),
			array( 'in_footer' => true )
		);

		wp_add_inline_script(
			'oria-app',
			'window.ORIA_TRACK = ' . wp_json_encode( array( 'url' => rest_url( 'oria/v1/track' ) ) ) . ';',
			'before'
		);

		// Listing profiles describe themselves to the analytics layer, so a
		// lead event in GA4 arrives already labelled with the category,
		// suburb and plan it came from. Without this a conversion is just a
		// count; with it we can tell a practitioner what their tier earns.
		if ( is_singular( 'listing' ) ) {
			wp_add_inline_script( 'oria-app', 'window.ORIA_PROFILE = ' . wp_json_encode( profile_context() ) . ';', 'before' );
		}

		// Only the directory engine reads window.ORIA_DATA — it needs every
		// field to filter, sort and render cards client-side. Pages used to
		// be included here for the map and the search hero, but both are
		// satisfied by the slim index, and shipping the full set to the home
		// page meant 255KB of inline JSON for the main thread to parse
		// before the page settled.
		if ( is_post_type_archive( 'listing' ) || is_tax( array( 'practice', 'area', 'specialty' ) ) ) {
			wp_add_inline_script( 'oria-app', 'window.ORIA_DATA = ' . wp_json_encode( listing_data() ) . ';', 'before' );
		} else {
			// Everywhere else — a listing, an event, a journal article — the
			// header search still needs something to search. The full set is
			// ~148KB; this cut of it is ~35KB and holds only what suggestions
			// are drawn from.
			wp_add_inline_script( 'oria-app', 'window.ORIA_SEARCH_DATA = ' . wp_json_encode( search_index() ) . ';', 'before' );
		}
	}
);

/**
 * Preload the two latin font files.
 *
 * A browser only discovers a font after it has parsed the CSS and
 * matched a rule to text on the page, which on a slow connection is
 * late — and both of these are needed above the fold (Manrope for
 * everything, Newsreader for the wordmark). Priority 2 puts the hints
 * after wp_enqueue_scripts has run but well before wp_print_styles.
 */
add_action(
	'wp_head',
	static function (): void {
		$dir = get_template_directory();
		$uri = get_template_directory_uri();
		foreach ( array( 'manrope-normal-latin', 'newsreader-italic-latin' ) as $font ) {
			$path = "{$dir}/assets/fonts/{$font}.woff2";
			if ( file_exists( $path ) ) {
				printf(
					'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
					esc_url( "{$uri}/assets/fonts/{$font}.woff2?v=" . filemtime( $path ) )
				);
			}
		}
	},
	2
);

/** The "js" class the reveal animations key off. */
add_filter(
	'language_attributes',
	static fn( string $output ): string => $output . ' class="js"'
);

/* -------------------------------------------------------------------------
 * Data bridge: live posts -> the same shape data/listings.js had
 * ---------------------------------------------------------------------- */

/**
 * The dimensions every lead event from a listing profile should carry.
 * Deliberately not the business name — GA4 reports read better grouped by
 * category and plan, and the page path already identifies the listing.
 *
 * @return array<string, mixed>
 */
function profile_context(): array {
	$id        = get_queried_object_id();
	$practices = wp_get_post_terms( $id, 'practice' );
	$areas     = wp_get_post_terms( $id, 'area' );

	$suburb = '';
	foreach ( is_wp_error( $areas ) ? array() : $areas as $term ) {
		if ( $term->parent ) {
			$suburb = tname( $term );
			break;
		}
	}

	return array(
		'id'       => $id,
		'category' => ! is_wp_error( $practices ) && $practices ? $practices[0]->slug : '',
		'suburb'   => $suburb,
		'plan'     => display_status( $id ),
	);
}

/**
 * Every published listing in the exact structure the prototype's app.js
 * already filters and renders. One query, cached per request.
 */
function listing_data(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'listing',
			'posts_per_page' => 500,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$listings = array();
	foreach ( $posts as $post ) {
		$practices = wp_get_post_terms( $post->ID, 'practice' );
		$areas     = wp_get_post_terms( $post->ID, 'area' );

		$suburb = null;
		$region = null;
		foreach ( $areas as $term ) {
			if ( $term->parent ) {
				$suburb = $term;
				$region = \Oria\Core\Taxonomies\region_for( $term );
			} elseif ( ! $region ) {
				$region = $term;
			}
		}

		$primary = $practices[0] ?? null;
		$also    = array_slice( wp_list_pluck( $practices, 'slug' ), 1 );

		/*
		 * Ancestors count as membership. Categories gained parents, so a
		 * meditation studio is tagged "meditation" while the sidebar offers
		 * "Mind & Mental Wellbeing" — without this, clicking the new top
		 * level would match nothing and look broken. The server gets this
		 * free from tax_query's include_children; the client index has to
		 * be told.
		 */
		foreach ( $practices as $oria_pterm ) {
			foreach ( (array) get_ancestors( (int) $oria_pterm->term_id, 'practice', 'taxonomy' ) as $oria_anc ) {
				$oria_anc_term = get_term( (int) $oria_anc, 'practice' );
				// Leading backslash: this file is namespaced Oria\Theme, so an
				// unqualified WP_Term resolves to Oria\Theme\WP_Term, which
				// does not exist — instanceof then answers false in silence
				// and every ancestor is dropped without an error anywhere.
				if ( $oria_anc_term instanceof \WP_Term ) {
					$also[] = $oria_anc_term->slug;
				}
			}
		}
		$also = array_values( array_unique( array_diff( $also, array( $primary ? $primary->slug : '' ) ) ) );

		// What the card shows: top-level categories, matching the sidebar.
		$cat_top = function_exists( '\Oria\Core\Categories\top_for' )
			? array_map(
				static fn( array $c ): string => $c['term']->slug,
				\Oria\Core\Categories\top_for( (int) $post->ID, 2 )
			)
			: array();

		$specs = wp_get_post_terms( $post->ID, 'specialty' );
		$specs = is_wp_error( $specs ) ? array() : wp_list_pluck( $specs, 'slug' );

		$rated = effective_rating( $post->ID );

		$listings[] = array(
			'id'         => $post->post_name,
			'name'       => ptitle( $post ),
			'url'        => get_permalink( $post ),
			'cat'        => $primary ? $primary->slug : '',
			'catTop'     => $cat_top,
			'also'       => $also,
			'spec'       => $specs,
			'suburb'     => tname( $suburb ?: $region ),
			'region'     => $region ? $region->slug : '',
			'blurb'      => get_the_excerpt( $post ),
			'services'   => array_column( (array) get_field( 'services', $post->ID ), 'name' ),
			'priceFrom'  => (float) get_field( 'price_from', $post->ID ),
			'priceBand'  => (string) get_field( 'price_band', $post->ID ),
			'format'     => (string) ( get_field( 'format', $post->ID ) ?: 'in-person' ),
			'rating'     => $rated['rating'],
			'reviews'    => $rated['count'],
			'rating_src' => $rated['source'],
			'status'     => display_status( $post->ID ),
			'image'      => listing_image( $post->ID ),
			'image_fb'   => listing_scene( $post->ID ),
			'next'       => (string) get_field( 'next_session', $post->ID ),
			'offer'      => null !== active_offer( $post->ID ),
		);
	}

	$cache = array(
		'categories' => array_map(
			static fn( \WP_Term $t ): array => array(
				'id'   => $t->slug,
				'name' => tname( $t ),
				'url'  => get_term_link( $t ),
			),
			get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false ) ) ?: array()
		),
		'regions'    => array_map(
			static function ( \WP_Term $t ): array {
				$children = get_terms( array( 'taxonomy' => 'area', 'parent' => $t->term_id, 'hide_empty' => false ) );
				return array(
					'id'      => $t->slug,
					'name'    => tname( $t ),
					'url'     => get_term_link( $t ),
					'suburbs' => array_map( __NAMESPACE__ . '\tname', is_wp_error( $children ) ? array() : $children ),
				);
			},
			\Oria\Core\Taxonomies\regions()
		),
		// array_values, or one term filtered out upstream leaves a keyed
		// array that wp_json_encode turns into an OBJECT — which is exactly
		// what happened on production: {"0":…} instead of […], every
		// .forEach in app.js threw, and the directory filters died.
		'specialties' => array_values(
			array_map(
				static fn( \WP_Term $t ): array => array(
					'id'   => $t->slug,
					'name' => tname( $t ),
					'url'  => get_term_link( $t ),
				),
				get_terms( array( 'taxonomy' => 'specialty', 'hide_empty' => true ) ) ?: array()
			)
		),
		'listings'   => $listings,
	);

	return $cache;
}

/* -------------------------------------------------------------------------
 * Template helpers
 * ---------------------------------------------------------------------- */

/** The ensō, inline. Small cut for chrome, brush cut for feature spots. */
function mark( string $cut = 'small', int $size = 24 ): string {
	$file = 'small' === $cut ? 'logo-mark-simple.svg' : 'logo-mark.svg';
	$svg  = file_get_contents( get_template_directory() . '/assets/img/' . $file );
	if ( ! $svg ) {
		return '';
	}
	// Strip the XML size so CSS controls it; keep viewBox.
	$svg = (string) preg_replace( '/\s(width|height)="\d+"/', '', $svg, 2 );
	return str_replace(
		'<svg ',
		sprintf( '<svg class="brand__mark" width="%d" height="%d" aria-hidden="true" ', $size, $size ),
		$svg
	);
}

/** Claim status for a listing, defaulting the way the importer does. */
function claim_status( int $post_id ): string {
	$status = (string) get_post_meta( $post_id, 'claim_status', true );
	return in_array( $status, array( 'claimed', 'featured' ), true ) ? $status : 'unclaimed';
}

/**
 * What the public sees. An approved owner on the free plan is "claimed" —
 * their claim went through, so the listing shouldn't read as ownerless —
 * while paid surfaces (offers, socials, the verified seal) keep asking
 * claim_status(), which only paid tiers satisfy.
 */
function display_status( int $post_id ): string {
	$status = claim_status( $post_id );
	// The admin showcase flag reads as Featured everywhere the public looks,
	// while the real claim status keeps driving the paid machinery.
	if ( 'featured' !== $status && '1' === (string) get_post_meta( $post_id, 'admin_featured', true ) ) {
		return 'featured';
	}
	if ( 'unclaimed' === $status && (int) get_post_meta( $post_id, 'claimed_by', true ) ) {
		return 'claimed';
	}
	return $status;
}

/**
 * The listing's live special offer, or null. Offers are a paid feature:
 * they only exist while the listing is claimed, and they expire themselves.
 *
 * @return array{title: string, text: string, until: string}|null
 */
function active_offer( int $post_id ): ?array {
	if ( 'unclaimed' === claim_status( $post_id ) ) {
		return null;
	}
	$title = trim( (string) get_field( 'offer_title', $post_id ) );
	if ( '' === $title ) {
		return null;
	}
	$until = (string) get_field( 'offer_until', $post_id );
	if ( '' !== $until && $until < current_time( 'Y-m-d' ) ) {
		return null;
	}
	return array(
		'title' => $title,
		'text'  => (string) get_field( 'offer_text', $post_id ),
		'until' => $until,
	);
}

/* -------------------------------------------------------------------------
 * Editable-content helpers.
 *
 * The rule across every template: an empty ACF field falls back to the
 * designed copy, so an untouched page renders exactly as designed and an
 * editor can never blank a section into a broken state.
 * ---------------------------------------------------------------------- */

/** Field value or the designed fallback. */
function f( string $name, string $fallback = '', $post_id = false ): string {
	$value = get_field( $name, $post_id );
	return ( is_string( $value ) && '' !== trim( $value ) ) ? $value : $fallback;
}

/** Repeater rows, or the designed fallback rows when the field is empty. */
function rows( string $name, array $fallback = array(), $post_id = false ): array {
	$value = get_field( $name, $post_id );
	return ( is_array( $value ) && $value ) ? $value : $fallback;
}

/**
 * Image field -> URL, falling back to a theme asset. Fields return
 * attachment IDs; the fallback names a file in assets/img.
 */
function fimg( string $name, string $fallback_asset, string $size = 'oria-wide', $post_id = false ): string {
	$id = get_field( $name, $post_id );
	if ( $id ) {
		$url = wp_get_attachment_image_url( (int) $id, $size );
		if ( $url ) {
			return $url;
		}
	}
	return get_template_directory_uri() . '/assets/img/' . $fallback_asset;
}

/** Options-page field with fallback. */
function opt( string $name, string $fallback = '' ): string {
	return f( $name, $fallback, 'option' );
}

/** Featured listings for the home page. */
/**
 * @param list<string> $areas Area slugs to stay within; regions include
 *                            their suburbs. Empty means the whole metro.
 */
function featured_listings( int $count = 3, string $practice = '', array $areas = array() ): array {
	$args = array(
		'post_type'      => 'listing',
		'posts_per_page' => $count,
		'post_status'    => 'publish',
		// Paying Featured subscribers plus the admin's own showcase picks —
		// the second exists to keep these surfaces alive before anyone pays.
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'   => 'claim_status',
				'value' => 'featured',
			),
			array(
				'key'   => 'admin_featured',
				'value' => '1',
			),
		),
	);
	$tax = array();
	if ( '' !== $practice ) {
		$tax[] = array(
			'taxonomy' => 'practice',
			'field'    => 'slug',
			'terms'    => $practice,
		);
	}
	// A region includes its suburbs, so an article about the Hills reaches
	// Kalamunda and Mundaring without having to name them.
	if ( $areas ) {
		$tax[] = array(
			'taxonomy'         => 'area',
			'field'            => 'slug',
			'terms'            => $areas,
			'include_children' => true,
		);
	}
	if ( $tax ) {
		$tax['relation']   = 'AND';
		$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}
	return get_posts( $args );
}

/**
 * How many listings a practice has per region slug and per suburb name —
 * feeds the "browse by area" link mesh on practice landing pages.
 *
 * @return array{regions: array<string,int>, suburbs: array<string,int>}
 */
function combo_counts( string $cat ): array {
	$regions = array();
	$suburbs = array();
	foreach ( listing_data()['listings'] as $l ) {
		if ( $l['cat'] !== $cat && ! in_array( $cat, (array) $l['also'], true ) ) {
			continue;
		}
		if ( $l['region'] ) {
			$regions[ $l['region'] ] = ( $regions[ $l['region'] ] ?? 0 ) + 1;
		}
		if ( $l['suburb'] ) {
			$suburbs[ $l['suburb'] ] = ( $suburbs[ $l['suburb'] ] ?? 0 ) + 1;
		}
	}
	return array( 'regions' => $regions, 'suburbs' => $suburbs );
}

/**
 * A post title safe to escape for output. The the_title filter chain turns
 * "&" into the numeric entity &#038;, so escaping the filtered title double
 * encodes it into visible text. Decode first, escape once at output.
 *
 * @param int|\WP_Post|null $post
 */
function ptitle( $post = null ): string {
	return wp_specialchars_decode( get_the_title( $post ), ENT_QUOTES );
}

/**
 * A term name safe to escape for output. WordPress stores term names with
 * entities already encoded ("Rockingham &amp; Peel"), so escaping the raw
 * name double-encodes and the visitor sees the literal "&amp;". Decode
 * first, escape once at output.
 *
 * @param \WP_Term|string|null $term Term object or already-extracted name.
 */
function tname( $term ): string {
	$name = $term instanceof \WP_Term ? $term->name : (string) ( $term ?? '' );
	return wp_specialchars_decode( $name, ENT_QUOTES );
}

/**
 * The rating a listing should display: reviews collected here first, else
 * its cached Google rating. Cache-only by default so archives of cards never
 * trigger API calls — the cache warms via card_photo's budget and profile
 * views.
 *
 * @return array{rating: float, count: int, source: string} source: none|native|google
 */
function effective_rating( int $post_id, bool $may_fetch = false ): array {
	$native = (float) get_field( 'rating', $post_id );
	if ( $native > 0 ) {
		return array(
			'rating' => $native,
			'count'  => (int) get_field( 'review_count', $post_id ),
			'source' => 'native',
		);
	}
	if ( function_exists( '\Oria\Core\Places\rating_for' ) ) {
		$google = \Oria\Core\Places\rating_for( $post_id, $may_fetch );
		if ( $google['rating'] > 0 ) {
			return array(
				'rating' => $google['rating'],
				'count'  => $google['count'],
				'source' => 'google',
			);
		}
	}
	return array( 'rating' => 0.0, 'count' => 0, 'source' => 'none' );
}

/** The listing's placeholder scene URL — the guaranteed-to-exist fallback. */
function listing_scene( int $post_id ): string {
	$scene = (string) get_post_meta( $post_id, 'placeholder_scene', true );
	if ( ! preg_match( '/^[a-z0-9_-]+$/', $scene ) || ! file_exists( get_template_directory() . "/assets/img/{$scene}.webp" ) ) {
		$scene = 'scene-hall';
	}
	return get_template_directory_uri() . "/assets/img/{$scene}.webp";
}

/**
 * A listing's image URL: featured image, else its first Google Places photo
 * (cache-first, budgeted), else the placeholder scene. One chain, used by
 * cards, tiles and profiles.
 */
function listing_image( int $post_id, string $size = 'oria-card' ): string {
	$url = get_the_post_thumbnail_url( $post_id, $size );
	if ( $url ) {
		return $url;
	}
	if ( function_exists( '\Oria\Core\Places\card_photo' ) ) {
		$url = \Oria\Core\Places\card_photo( $post_id );
		if ( '' !== $url ) {
			return $url;
		}
	}
	return listing_scene( $post_id );
}

/**
 * Google Maps Embed API URL for an address, or '' when no key is set.
 * Coordinates win over the address when both exist — they can't be
 * misgeocoded. The key lives once, in Site settings.
 */
function map_embed_url( string $address, $lat = null, $lng = null ): string {
	// The plugin helper honours the wp-config constant override.
	$key = function_exists( '\Oria\Core\Places\browser_key' )
		? \Oria\Core\Places\browser_key()
		: opt( 'google_maps_api_key' );
	if ( '' === $key ) {
		return '';
	}
	$q = ( $lat && $lng ) ? $lat . ',' . $lng : $address;
	if ( '' === trim( (string) $q ) ) {
		return '';
	}
	return add_query_arg(
		array(
			'key' => rawurlencode( $key ),
			'q'   => rawurlencode( (string) $q ),
		),
		'https://www.google.com/maps/embed/v1/place'
	);
}

/** Directions link — needs no API key. */
function map_directions_url( string $address ): string {
	return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $address );
}

/** Estimated reading time in minutes, floor 1. */
function reading_time( int $post_id ): int {
	$words = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
	return max( 1, (int) ceil( $words / 200 ) );
}

/** "Category · N min read" — the article meta line from the design. */
function article_meta( int $post_id ): string {
	$cat  = get_the_category( $post_id )[0] ?? null;
	$bits = array();
	if ( $cat instanceof \WP_Term && 'Uncategorized' !== $cat->name ) {
		$bits[] = esc_html( tname( $cat ) );
	}
	/* translators: %d: minutes */
	$bits[] = sprintf( esc_html__( '%d min read', 'oria' ), reading_time( $post_id ) );
	return implode( '<span>&middot;</span>', array_map( static fn( $b ) => "<span>{$b}</span>", $bits ) );
}

/**
 * The slim index behind the header search AND the home page's region
 * map: just enough to build a suggestion list and count places per
 * region — no blurbs, images, prices or ratings.
 *
 * The full set is ~255KB of inline JSON. It is not the download that
 * hurts (Brotli takes it to a fraction of that) but the main thread:
 * a quarter-megabyte object literal has to be parsed and evaluated
 * before the page settles, which on a throttled mobile CPU is exactly
 * the blocking time a PageSpeed score punishes. Only the directory
 * engine genuinely needs every field.
 *
 * Kept in a transient because assembling it walks every listing and its
 * terms, which is far too much work to repeat on each request for a
 * search box most visitors never touch. Cleared whenever a listing or a
 * term changes, so it cannot go stale behind the editor's back.
 *
 * The key carries a version. A deploy that adds a field to these rows
 * would otherwise keep reading the old shape from a transient with
 * hours left on it — 'region' arrived that way, and a stale index would
 * have left the home map reporting no places in any region until it
 * expired. Bump SEARCH_INDEX_V whenever the shape changes.
 */
const SEARCH_INDEX_V = 2; // 2: listing rows gained 'region' for the map.

function search_index(): array {
	$key    = 'oria_search_index_v' . SEARCH_INDEX_V;
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$full  = listing_data();
	$index = array(
		'categories'  => $full['categories'],
		'specialties' => $full['specialties'],
		'regions'     => array_map(
			static fn( array $r ): array => array( 'id' => $r['id'], 'name' => $r['name'], 'suburbs' => $r['suburbs'] ),
			$full['regions']
		),
		'listings'    => array_map(
			// 'region' is here for the home page's map, which counts places
			// per region — the one field search itself never reads.
			static fn( array $l ): array => array(
				'name'   => $l['name'],
				'url'    => $l['url'],
				'cat'    => $l['cat'],
				'spec'   => $l['spec'],
				'suburb' => $l['suburb'],
				'region' => $l['region'],
			),
			$full['listings']
		),
	);

	set_transient( $key, $index, 12 * HOUR_IN_SECONDS );
	return $index;
}

/** Any change to a listing or a term rebuilds the index on next read. */
function flush_search_index(): void {
	delete_transient( 'oria_search_index_v' . SEARCH_INDEX_V );
}

foreach ( array( 'save_post_listing', 'deleted_post', 'edited_term', 'created_term' ) as $oria_flush_hook ) {
	add_action( $oria_flush_hook, __NAMESPACE__ . '\flush_search_index' );
}
unset( $oria_flush_hook );

/**
 * The practice categories a journal article is about: the editor's picks
 * (related_practices field) when set, otherwise matched from the article's
 * title, tags and categories against each practice's name words.
 *
 * @return array<int, \WP_Term>
 */
function journal_practices( int $post_id ): array {
	$picked = function_exists( 'get_field' ) ? array_filter( array_map( 'intval', (array) ( get_field( 'related_practices', $post_id ) ?: array() ) ) ) : array();
	if ( $picked ) {
		return array_values( array_filter( array_map( static fn( $id ) => get_term( $id, 'practice' ), $picked ), static fn( $t ) => $t instanceof \WP_Term ) );
	}

	$hay  = strtolower( (string) get_post_field( 'post_title', $post_id, 'raw' ) );
	$tags = get_the_tags( $post_id );
	foreach ( array_merge( is_array( $tags ) ? $tags : array(), get_the_category( $post_id ) ) as $t ) {
		if ( $t instanceof \WP_Term ) {
			$hay .= ' ' . strtolower( $t->name );
		}
	}

	$matches = array();
	$terms   = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => true ) );
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		// "Sound & float" matches on "sound" or "float"; slugs count too.
		$needles = array_filter(
			array_merge( array( $term->slug ), preg_split( '/[\s&,]+/', strtolower( tname( $term ) ) ) ?: array() ),
			static fn( $w ) => strlen( $w ) > 3 && 'and' !== $w
		);
		foreach ( $needles as $needle ) {
			if ( str_contains( $hay, $needle ) ) {
				$matches[ $term->term_id ] = $term;
				break;
			}
		}
	}
	return array_values( $matches );
}

/**
 * A section image's alt text, from the media library.
 *
 * Editor-chosen images are the one case where we cannot write the alt in
 * code: only the person who picked the picture knows what is in it. So we
 * use whatever they typed in the media library, and where they typed
 * nothing we leave it empty rather than inventing a description of a photo
 * nobody has described. An empty alt is a smaller error than a wrong one.
 */
function simg_alt( array $section, string $key ): string {
	$id = (int) ( $section[ $key ] ?? 0 );
	return $id ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
}

/**
 * Featured images stand in for the thing they illustrate.
 *
 * Journal cards render the post thumbnail, and WordPress takes its alt
 * from the media library — which is empty across the board here, so every
 * article card on the site shipped alt="". The article's own title is an
 * accurate description of an image chosen to represent that article, and
 * it is always right, which is more than can be said for anything we could
 * invent. A real alt in the media library still wins.
 */
add_filter(
	'wp_get_attachment_image_attributes',
	static function ( array $attr, $attachment, $size ) {
		unset( $size );
		if ( '' !== trim( (string) ( $attr['alt'] ?? '' ) ) ) {
			return $attr;
		}
		$parent = (int) get_post_field( 'post_parent', $attachment );
		$owner  = 0;
		// The post this image is the thumbnail of, which is not necessarily
		// the post it was uploaded to.
		global $post;
		if ( $post instanceof \WP_Post && (int) get_post_thumbnail_id( $post ) === (int) $attachment->ID ) {
			$owner = $post->ID;
		} elseif ( $parent && (int) get_post_thumbnail_id( $parent ) === (int) $attachment->ID ) {
			$owner = $parent;
		}
		if ( $owner ) {
			$attr['alt'] = ptitle( get_post( $owner ) );
		}
		return $attr;
	},
	10,
	3
);

/**
 * A social profile URL turned into a label and an icon.
 *
 * The footer draws its links from the same list that feeds the sameAs in
 * the Organization schema, so a new profile is added in one place and
 * appears in both. A URL we have no icon for is skipped rather than
 * rendered as a mystery square.
 *
 * @return array{label: string, icon: string}|null
 */
function social_link( string $url ): ?array {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

	// Stroke-drawn to sit with the rest of the theme's iconography.
	$icons = array(
		'instagram.com' => array(
			'label' => 'Instagram',
			'icon'  => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1" fill="currentColor" stroke="none"/>',
		),
		'linkedin.com'  => array(
			'label' => 'LinkedIn',
			'icon'  => '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path d="M8 10.8V17M8 7.6v.01M12 17v-3.4a2.1 2.1 0 0 1 4.2 0V17"/>',
		),
		'facebook.com'  => array(
			'label' => 'Facebook',
			'icon'  => '<path d="M15 8h2.5V5H15c-2 0-3.5 1.5-3.5 3.5V11H9v3h2.5v7h3v-7H17l.5-3h-3V8.7c0-.4.3-.7.5-.7Z"/>',
		),
	);

	foreach ( $icons as $needle => $meta ) {
		if ( false !== strpos( $host, $needle ) ) {
			return $meta;
		}
	}
	return null;
}

/**
 * Alt text for a listing's photo: what the picture is of, in the words
 * somebody would use out loud.
 *
 * "Karrinyup Wellness Centre — Bodywork & Massage in Karrinyup" rather
 * than a keyword list. Screen readers read this aloud, so it has to be a
 * sentence fragment a person would say; the fact that it also happens to
 * contain the practice type and the suburb is a by-product of describing
 * the thing accurately, which is the only kind of alt text worth having.
 */
function listing_alt( int $post_id ): string {
	$name = ptitle( get_post( $post_id ) );

	$practice = wp_get_post_terms( $post_id, 'practice' );
	$practice = ( ! is_wp_error( $practice ) && $practice ) ? tname( $practice[0] ) : '';

	$suburb = '';
	$areas  = wp_get_post_terms( $post_id, 'area' );
	foreach ( is_wp_error( $areas ) ? array() : $areas as $term ) {
		if ( $term->parent ) {
			$suburb = tname( $term );
			break;
		}
	}

	$where = $suburb ?: __( 'Perth', 'oria' );
	if ( ! $practice ) {
		/* translators: 1: practice name, 2: suburb */
		return trim( sprintf( __( '%1$s in %2$s', 'oria' ), $name, $where ) );
	}
	/* translators: 1: practice name, 2: category, 3: suburb */
	return trim( sprintf( __( '%1$s — %2$s in %3$s', 'oria' ), $name, $practice, $where ) );
}

/**
 * The areas an article is about, so its sidebar can stay local.
 *
 * An article about retreats in the Perth Hills that offers a retreat in
 * Fremantle is worse than one that offers nothing: it reads as though
 * nobody is paying attention. The editor's picks win; failing that we
 * match the title, which is usually where an article says where it is.
 *
 * The matching is deliberately timid. Region names are mostly compass
 * points and the word Perth — "Perth Central", "Northern Suburbs",
 * "South East" — and matching on those would tie half the journal to an
 * area it never mentioned. Only the distinctive part of a name counts, so
 * "Perth Hills" matches on "hills" while "Northern Suburbs" never
 * auto-matches at all and waits to be picked by hand.
 *
 * @return array<int, \WP_Term>
 */
function journal_areas( int $post_id ): array {
	$picked = function_exists( 'get_field' ) ? array_filter( array_map( 'intval', (array) ( get_field( 'related_areas', $post_id ) ?: array() ) ) ) : array();
	if ( $picked ) {
		return array_values( array_filter( array_map( static fn( $id ) => get_term( $id, 'area' ), $picked ), static fn( $t ) => $t instanceof \WP_Term ) );
	}

	$hay  = strtolower( (string) get_post_field( 'post_title', $post_id, 'raw' ) );
	$tags = get_the_tags( $post_id );
	foreach ( is_array( $tags ) ? $tags : array() as $t ) {
		if ( $t instanceof \WP_Term ) {
			$hay .= ' ' . strtolower( $t->name );
		}
	}

	// Words that describe where everything in this directory is, and so
	// distinguish nothing.
	$generic = array( 'perth', 'north', 'northern', 'south', 'southern', 'east', 'eastern', 'west', 'western', 'central', 'suburbs', 'suburb', 'metro', 'valley' );

	$matches = array();
	$terms   = get_terms( array( 'taxonomy' => 'area', 'hide_empty' => true ) );
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$words = array_filter(
			preg_split( '/[\s&,]+/', strtolower( tname( $term ) ) ) ?: array(),
			static fn( $w ) => strlen( $w ) > 3 && ! in_array( $w, $generic, true )
		);
		foreach ( $words as $word ) {
			if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/', $hay ) ) {
				$matches[ $term->term_id ] = $term;
				break;
			}
		}
	}

	// A region and one of its own suburbs both matching is one area, not two.
	foreach ( $matches as $id => $term ) {
		if ( $term->parent && isset( $matches[ $term->parent ] ) ) {
			unset( $matches[ $id ] );
		}
	}

	return array_values( $matches );
}

/**
 * An author's face for bylines: their profile photo if set, otherwise an
 * initials monogram. Never Gravatar — it 404s on local and leaks emails.
 */
function author_avatar( int $user_id, int $size = 44 ): string {
	$photo = function_exists( 'get_field' ) ? (int) get_field( 'author_photo', 'user_' . $user_id ) : 0;
	if ( $photo ) {
		$img = wp_get_attachment_image(
			$photo,
			array( $size * 2, $size * 2 ),
			false,
			array(
				'class' => 'avatar-img',
				'alt'   => '',
				'style' => "width:{$size}px;height:{$size}px",
			)
		);
		if ( $img ) {
			return $img;
		}
	}
	$name     = (string) get_the_author_meta( 'display_name', $user_id );
	$initials = '';
	foreach ( array_slice( preg_split( '/\s+/', trim( $name ) ) ?: array(), 0, 2 ) as $word ) {
		$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
	}
	return '<span class="avatar-mono" aria-hidden="true" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . round( $size * 0.36 ) . 'px">'
		. esc_html( $initials ?: '·' ) . '</span>';
}

/** The pictorial mark for an event's type — used by art tiles and rows. */
function event_mark( int $event_id ): string {
	$marks = array(
		'yoga'                 => '🧘',
		'meditation'           => '🪷',
		'breathwork'           => '🌬️',
		'sound-healing'        => '🔔',
		'mindfulness'          => '🌤️',
		'womens-circle'        => '🌙',
		'mens-group'           => '🔥',
		'wellness-workshop'    => '🌿',
		'retreat'              => '🏞️',
		'sauna'                => '🔥',
		'cold-plunge'          => '🧊',
		'nutrition'            => '🥗',
		'fitness'              => '🤸',
		'personal-development' => '🌱',
		'spiritual'            => '✨',
		'relaxation'           => '🌾',
		'community'            => '🤝',
	);
	$terms = wp_get_post_terms( $event_id, 'event_type' );
	$term  = ! is_wp_error( $terms ) && $terms ? $terms[0] : null;
	if ( ! $term ) {
		$pr   = wp_get_post_terms( $event_id, 'practice' );
		$term = ! is_wp_error( $pr ) && $pr ? $pr[0] : null;
	}
	return $term && isset( $marks[ $term->slug ] ) ? $marks[ $term->slug ] : '◦';
}

/** The arrow-in-a-dot SVG every button uses. */
function arrow(): string {
	return '<span class="btn__dot"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 11 3M5 3h6v6"/></svg></span>';
}

/**
 * The first section layout on the current page, if it is a built page.
 * The header uses this: a page that opens with a hero gets the transparent
 * overlay nav; everything else gets the solid sticky nav.
 */
function page_first_layout(): string {
	if ( ! is_page() ) {
		return '';
	}
	$sections = function_exists( 'get_field' ) ? get_field( 'sections' ) : null;
	if ( ! is_array( $sections ) || ! $sections ) {
		return '';
	}
	return (string) ( $sections[0]['acf_fc_layout'] ?? '' );
}

/** Repeater rows from a section array, tolerating missing keys. */
function srows( array $section, string $key ): array {
	$rows = $section[ $key ] ?? null;
	return is_array( $rows ) ? $rows : array();
}

/** Section image (attachment ID) -> URL with theme-asset fallback. */
function simg( array $section, string $key, string $fallback_asset, string $size = 'oria-wide' ): string {
	$id = $section[ $key ] ?? 0;
	if ( $id ) {
		$url = wp_get_attachment_image_url( (int) $id, $size );
		if ( $url ) {
			return $url;
		}
	}
	return get_template_directory_uri() . '/assets/img/' . $fallback_asset;
}

/** paper|sand -> the section band class. */
function sband( array $section ): string {
	return ( 'sand' === ( $section['background'] ?? 'paper' ) ) ? ' band-sand' : '';
}
