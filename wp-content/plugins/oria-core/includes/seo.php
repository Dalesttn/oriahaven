<?php
/**
 * SEO landing-page plumbing.
 *
 * Two families of indexable pages on top of the directory:
 *
 *   /perth/{specialty}/            "Acupuncture in Perth"
 *   /practice/{practice}/{area}/   "Breathwork in Fremantle & South",
 *                                  "Bodywork & Massage in Joondalup"
 *
 * The combo route reuses the practice taxonomy archive with an extra
 * `oria_area` query var; the template locks both facets in the directory
 * engine. Titles and meta descriptions are written here (via Yoast's
 * filters, falling back to core document titles), and combos with no
 * matching listings are marked noindex so thin pages never enter the index.
 */

declare(strict_types=1);

namespace Oria\Core\Seo;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR  = 'oria_area';
const REWRITE_V  = '8'; // 8: /llms.txt route added.

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\add_routes', 10 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 20 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\validate_combo' );
	add_action( 'template_redirect', __NAMESPACE__ . '\legacy_events_url' );

	// Yoast when present, core titles as the fallback.
	add_filter( 'wpseo_title', __NAMESPACE__ . '\seo_title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\seo_description' );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\seo_canonical' );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\seo_robots' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );
	// Listing preview images live in Share, which has to reach Yoast a step
	// earlier than a filter to set one at all.
	// Last, so it sees Yoast's block: where Yoast already advertises its
	// sitemap this does nothing, and a second line never gets added.
	add_filter( 'robots_txt', __NAMESPACE__ . '\robots_txt', 9999, 2 );
}

/**
 * Point crawlers at the sitemap that actually lists the directory.
 *
 * Core advertises /wp-sitemap.xml in robots.txt. Yoast builds a richer
 * index at /sitemap_index.xml — the one carrying the practice, area and
 * specialty archives — but core's line was the one being served, so
 * robots.txt was naming the weaker of two live sitemaps.
 *
 * Only rewrites when Yoast's sitemaps are switched on; if they are not,
 * core's line is the best available and is left alone. Note this filter
 * cannot run at all if a physical robots.txt file exists in the web root
 * — WordPress serves that file directly and never builds the dynamic one.
 */
function robots_txt( $output, $public ) {
	if ( ! $public ) {
		return $output; // A site set to discourage indexing stays that way.
	}
	$sitemap = yoast_sitemap_url();
	if ( '' === $sitemap ) {
		return $output;
	}

	$lines = preg_split( '/\R/', (string) $output ) ?: array();
	$kept  = array();
	foreach ( $lines as $line ) {
		if ( preg_match( '#^\s*Sitemap:\s*\S*wp-sitemap\.xml\s*$#i', $line ) ) {
			continue;
		}
		$kept[] = $line;
	}
	$output = implode( "\n", $kept );

	if ( false === stripos( $output, $sitemap ) ) {
		$output = rtrim( $output ) . "\n\nSitemap: " . $sitemap . "\n";
	}
	return $output;
}

/** Yoast's sitemap index, or '' when Yoast isn't building sitemaps. */
function yoast_sitemap_url(): string {
	if ( ! class_exists( 'WPSEO_Options' ) || ! method_exists( 'WPSEO_Options', 'get' ) ) {
		return '';
	}
	return \WPSEO_Options::get( 'enable_xml_sitemap', false )
		? home_url( '/sitemap_index.xml' )
		: '';
}

function add_routes(): void {
	// /practice/{practice}/{area}/ — area may be a region or a suburb term.
	add_rewrite_rule(
		'^practice/([^/]+)/([^/]+)/?$',
		'index.php?practice=$matches[1]&' . QUERY_VAR . '=$matches[2]',
		'top'
	);
}

/** Bare /events/ was the archive before it moved to /whats-on-perth/. */
function legacy_events_url(): void {
	if ( is_404() && '/events/' === trailingslashit( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ) ) {
		wp_safe_redirect( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ), 301 );
		exit;
	}
}

/** Rules changed? Flush once, not on every load. */
function maybe_flush(): void {
	if ( get_option( 'oria_seo_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_seo_rewrite_v', REWRITE_V );
	}
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/* ------------------------------------------------------------ combo state */

/** The area term for the current combo page, or null. */
function combo_area(): ?\WP_Term {
	if ( ! is_tax( Taxonomies\PRACTICE ) ) {
		return null;
	}
	$slug = (string) get_query_var( QUERY_VAR );
	if ( '' === $slug ) {
		return null;
	}
	$term = get_term_by( 'slug', $slug, Taxonomies\AREA );
	return $term instanceof \WP_Term ? $term : null;
}

/** How many published listings match the current combo. */
function combo_count(): int {
	static $count = null;
	if ( null !== $count ) {
		return $count;
	}
	$practice = get_queried_object();
	$area     = combo_area();
	if ( ! $practice instanceof \WP_Term || ! $area instanceof \WP_Term ) {
		return $count = 0;
	}
	$q = new \WP_Query(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'tax_query'      => array(
				array( 'taxonomy' => Taxonomies\PRACTICE, 'field' => 'term_id', 'terms' => $practice->term_id ),
				array( 'taxonomy' => Taxonomies\AREA, 'field' => 'term_id', 'terms' => $area->term_id ),
			),
		)
	);
	return $count = (int) $q->found_posts;
}

/** An unknown area slug on a combo URL 301s to the plain practice page. */
function validate_combo(): void {
	if ( ! is_tax( Taxonomies\PRACTICE ) || '' === (string) get_query_var( QUERY_VAR ) ) {
		return;
	}
	if ( null === combo_area() ) {
		$practice = get_queried_object();
		$link     = $practice instanceof \WP_Term ? get_term_link( $practice ) : home_url( '/' );
		wp_safe_redirect( is_string( $link ) ? $link : home_url( '/' ), 301 );
		exit;
	}
}

/* ------------------------------------------------------------------ names */

function decoded( \WP_Term $term ): string {
	return wp_specialchars_decode( $term->name, ENT_QUOTES );
}

/* ----------------------------------------------------------------- titles */

/**
 * Has an editor written their own Yoast title/description for this term?
 *
 * Yoast keeps per-term SEO settings in one option rather than term meta.
 * Anything hand-written there outranks what we generate — the same rule
 * the listing title below already follows.
 */
function term_override( \WP_Term $term, string $key ): string {
	$meta = get_option( 'wpseo_taxonomy_meta', array() );
	return trim( (string) ( $meta[ $term->taxonomy ][ $term->term_id ][ $key ] ?? '' ) );
}

/** The queried term when this is a plain practice or area archive, else null. */
function plain_term(): ?\WP_Term {
	if ( combo_area() ) {
		return null; // The combo branch owns this page.
	}
	if ( ! is_tax( Taxonomies\PRACTICE ) && ! is_tax( Taxonomies\AREA ) ) {
		return null;
	}
	$term = get_queried_object();
	return $term instanceof \WP_Term ? $term : null;
}

/**
 * Titles and descriptions for individual pages that need better ones than
 * WordPress generates, keyed by slug.
 *
 * These are defaults, not overrides — see page_default(). Anything typed
 * into Yoast for the page wins, so this fills gaps on production without
 * taking the dashboard away from whoever wants to tune the wording later.
 *
 * The title here is the search-result title only. It does not touch the
 * headline on the page, which is why an 88-character article heading can
 * keep its full sense while the SERP gets 56 characters that fit.
 *
 * @return array<string, array{title?: string, desc?: string}>
 */
function page_defaults(): array {
	return apply_filters(
		'oria_page_seo_defaults',
		array(
			'acupuncture-in-perth' => array(
				'title' => 'Acupuncture in Perth: Costs & What to Expect | ' . get_bloginfo( 'name' ),
				'desc'  => 'Thinking about acupuncture in Perth? Registration, typical costs, what a first session involves, and verified Perth clinics — no booking fees.',
			),
			'list-your-practice'   => array(
				'title' => 'List Your Wellness Practice in Perth | ' . get_bloginfo( 'name' ),
				'desc'  => "Run a wellness practice in Perth? List it free on Oria Haven's hand-checked directory — no pay-to-win rankings, no booking fees, real client enquiries.",
			),
			// The two legal pages have no hand-written excerpt, so the generic
			// fallback below would serve WordPress's auto-truncated one.
			'terms'                => array(
				'desc' => 'How Oria Haven works for the people searching it and the practices listed on it: what we check, what we do not, and what to expect. Plain English.',
			),
			'privacy-policy'       => array(
				'desc' => 'What Oria Haven collects, why, and who else sees it — written to be read. Run from Western Australia; email hello@oriahaven.com.au if anything is unclear.',
			),
			// Guides whose standfirst runs past the 158-character ceiling; these
			// say the same thing in the space a snippet actually gets.
			'sauna-ice-bath-or-float' => array(
				'desc' => 'Sauna, ice bath and float are three very different hours sold as one category. What each asks of you, what they cost across Perth, and which to book first.',
			),
			'perth-hills-wellness-destination' => array(
				'desc' => 'Retreats in jarrah forest, reformer studios with cold plunges, and practitioners who left the city. Nineteen Hills and Swan Valley practices, and a day plan.',
			),
		)
	);
}

/**
 * Real editorial copy for the modality pages worth writing for.
 *
 * A specialty page is otherwise the directory engine with one facet locked
 * — accurate, and thin. The terms where that matters are the ones with
 * volume behind them, so those get written properly. Everything else keeps
 * the generated version, which is honest about being a list.
 *
 * Kept in code rather than in term descriptions so it deploys with a pull
 * instead of being retyped in the admin of every environment. A term
 * description typed in WordPress still wins — see specialty_intro().
 *
 * Same rule as everywhere: what a session involves, what it costs, where.
 * Never what it does to a body.
 *
 * @return array<string, list<string>>
 */
function specialty_intros(): array {
	return apply_filters(
		'oria_specialty_intros',
		array(
			'red-light-therapy' => array(
				'Red light therapy is one of the quicker things on a Perth recovery menu: you sit or lie in front of a panel of red and near-infrared LEDs, usually with the skin you want exposed uncovered, for somewhere between ten and twenty minutes. The panels are bright enough that most studios hand you goggles. There is no heat to speak of and nothing is touching you, which makes it the least demanding session in any recovery room.',
				'In Perth it is almost never sold on its own. It turns up as an add-on at recovery studios alongside sauna, ice baths and compression — a few minutes in front of the panel while you are already there for something else — and that is usually the cheapest way to try it. A handful of clinics run it as a standalone booking.',
				'Expect roughly $50 to $70 for a standalone session, less as an add-on, and most studios sell packs that bring the per-session price down if you decide it is something you want regularly. Sessions are short enough to fit either side of work.',
			),
		)
	);
}

/**
 * The intro for the specialty page being viewed.
 *
 * A description typed against the term in WordPress always wins; this only
 * fills the gap where nobody has written one.
 *
 * @return list<string>
 */
function specialty_intro( \WP_Term $term ): array {
	$own     = trim( wp_specialchars_decode( (string) $term->description, ENT_QUOTES ) );
	$written = specialty_intros()[ $term->slug ] ?? array();

	/*
	 * Most term descriptions here are one-line stubs left over from the
	 * import — "Red light therapy sessions around Perth." is the whole of
	 * the one on our highest-volume page. A stub is a placeholder, not
	 * editorial copy, so written copy beats it. Anything long enough to be
	 * somebody's actual writing wins, as it should.
	 */
	if ( $written && mb_strlen( $own ) < 120 ) {
		return $written;
	}
	return '' !== $own ? array( $own ) : $written;
}

/**
 * The default for the page being viewed, or '' — empty whenever the page
 * has its own Yoast value, so a hand-written one is never overwritten.
 */
function page_default( string $key ): string {
	if ( ! is_singular( array( 'post', 'page' ) ) ) {
		return '';
	}
	$post = get_post();
	if ( ! $post instanceof \WP_Post ) {
		return '';
	}
	$defaults = page_defaults();
	if ( ! isset( $defaults[ $post->post_name ][ $key ] ) ) {
		return '';
	}
	$written = 'title' === $key ? '_yoast_wpseo_title' : '_yoast_wpseo_metadesc';
	if ( '' !== trim( (string) get_post_meta( $post->ID, $written, true ) ) ) {
		return '';
	}
	return (string) $defaults[ $post->post_name ][ $key ];
}

function seo_title( $title ) {
	$own = page_default( 'title' );
	if ( '' !== $own ) {
		return $own;
	}

	$area = combo_area();
	if ( $area ) {
		$practice = get_queried_object();
		return sprintf( '%s in %s | %s', decoded( $practice ), decoded( $area ), get_bloginfo( 'name' ) );
	}
	if ( is_tax( Taxonomies\SPECIALTY ) ) {
		return sprintf( '%s in Perth | %s', decoded( get_queried_object() ), get_bloginfo( 'name' ) );
	}

	/*
	 * Category and area archives. Without this they fall through to Yoast's
	 * default and ship as "Yoga & movement Archives" — the word "Archives"
	 * sitting in the title of the most commercially useful pages on the
	 * site, and no mention of Perth in any of them.
	 */
	$term = plain_term();
	if ( $term && '' === term_override( $term, 'wpseo_title' ) ) {
		return sprintf( '%s | %s', archive_heading( $term ), get_bloginfo( 'name' ) );
	}
	// The event archive title now lives in Yoast's own settings, so it stays
	// editable in the admin rather than being overridden from here.
	/*
	 * Events are named by whoever runs them, and those names are the search
	 * term — "Sound Healing & Guided Meditation with Tibetan & Crystal Singing
	 * Bowls" is 69 characters before we append anything. Cutting the name would
	 * cost us the words people type, so drop our own suffix instead and let the
	 * name have the whole title. Short event names keep the brand.
	 */
	if ( is_singular( 'event' ) && '' === (string) get_post_meta( get_the_ID(), '_yoast_wpseo_title', true ) ) {
		$name = decoded_title( (int) get_the_ID() );
		if ( mb_strlen( (string) $title ) > 70 && '' !== $name && mb_strlen( $name ) < mb_strlen( (string) $title ) ) {
			return $name;
		}
	}
	if ( is_singular( 'listing' ) && '' === (string) get_post_meta( get_the_ID(), '_yoast_wpseo_title', true ) ) {
		$context = listing_context( get_the_ID() );
		if ( '' !== $context ) {
			return sprintf( '%s — %s | %s', decoded_title( get_the_ID() ), $context, get_bloginfo( 'name' ) );
		}
	}
	return $title;
}

/**
 * The phrase a category or area archive should lead with — and the same
 * phrase its <h1> already uses, so the title tag and the page agree.
 *
 * A practice is "{Practice} in Perth". An area is "Wellness practices in
 * {Area}", because the page is every modality in one suburb rather than
 * one modality across the metro.
 */
function archive_heading( \WP_Term $term ): string {
	return Taxonomies\AREA === $term->taxonomy
		? sprintf( 'Wellness practices in %s', decoded( $term ) )
		: sprintf( '%s in Perth', decoded( $term ) );
}

/** "Bodywork & Massage in Fremantle" for a listing, or ''. */
function listing_context( int $id ): string {
	$practice = wp_get_post_terms( $id, 'practice' )[0] ?? null;
	$suburb   = '';
	foreach ( wp_get_post_terms( $id, 'area' ) as $term ) {
		if ( $term->parent ) {
			$suburb = wp_specialchars_decode( $term->name );
			break;
		}
	}
	if ( ! $practice instanceof \WP_Term ) {
		return '' !== $suburb ? $suburb : '';
	}
	$name = wp_specialchars_decode( $practice->name );
	return '' !== $suburb ? sprintf( '%s in %s', $name, $suburb ) : $name;
}

function decoded_title( int $id ): string {
	return wp_specialchars_decode( (string) get_post_field( 'post_title', $id, 'raw' ) );
}

/** A first-160-characters description for entities without a hand-written one. */
function entity_description( int $id ): string {
	if ( 'event' === get_post_type( $id ) ) {
		$text = (string) get_field( 'event_description', $id );
	} else {
		// Listings keep their blurb in the excerpt; content is the long form.
		$text = (string) get_post_field( 'post_content', $id, 'raw' )
			?: (string) get_post_field( 'post_excerpt', $id, 'raw' );
	}
	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) ?? '' );
	if ( '' === $text ) {
		return '';
	}
	return mb_strlen( $text ) > 158 ? mb_substr( $text, 0, 157 ) . '…' : $text;
}

/**
 * A post or page's hand-written standfirst, cut to a snippet on a word
 * boundary. Only the typed excerpt: WordPress fabricates one from the opening
 * of the body when the field is empty, and that reads like a truncated article
 * rather than a description.
 */
function excerpt_description( int $id ): string {
	$post = get_post( $id );
	if ( ! $post instanceof \WP_Post ) {
		return '';
	}
	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $post->post_excerpt ) ) ?? '' );
	if ( '' === $text ) {
		return '';
	}
	if ( mb_strlen( $text ) <= 158 ) {
		return wp_specialchars_decode( $text, ENT_QUOTES );
	}
	$cut   = mb_substr( $text, 0, 157 );
	$space = mb_strrpos( $cut, ' ' );
	if ( false !== $space && $space > 100 ) {
		$cut = mb_substr( $cut, 0, $space );
	}
	return wp_specialchars_decode( rtrim( $cut, " ,;:—-" ) . '…', ENT_QUOTES );
}

function seo_description( $desc ) {
	$own = page_default( 'desc' );
	if ( '' !== $own ) {
		return $own;
	}

	$area = combo_area();
	if ( $area ) {
		$practice = get_queried_object();
		return sprintf(
			'Compare %1$s in %2$s: real timetables, prices and contact details for every practice we\'ve verified. Independent Perth wellness directory.',
			strtolower( decoded( $practice ) ),
			decoded( $area )
		);
	}
	if ( is_tax( Taxonomies\SPECIALTY ) ) {
		$term = get_queried_object();
		$own  = trim( wp_specialchars_decode( (string) $term->description, ENT_QUOTES ) );

		/*
		 * The term description used to serve as both the page intro and the
		 * meta description, which capped it at about 160 characters and so
		 * capped how much these pages could ever say. Red light therapy —
		 * the highest-volume, lowest-difficulty term we have — was carrying
		 * a forty-character intro because of it.
		 *
		 * They are separate jobs now. A short description is still a fine
		 * meta description; a long one is page copy, and the meta falls back
		 * to the generated line rather than shipping a truncated paragraph.
		 */
		$len = mb_strlen( $own );
		if ( $len >= 80 && $len <= 160 ) {
			return $own;
		}
		// Under 80 characters is a stub, over 160 is page copy. Either way
		// the generated line is the better meta description.
		return sprintf( 'Find %s across the Perth metro — timetables, prices and verified contact details.', strtolower( decoded( $term ) ) );
	}
	// Category and area archives, same gap as the title above.
	$term = plain_term();
	if ( $term && '' === term_override( $term, 'wpseo_desc' ) ) {
		if ( '' !== trim( (string) $term->description ) ) {
			return wp_specialchars_decode( $term->description, ENT_QUOTES );
		}
		return Taxonomies\AREA === $term->taxonomy
			? sprintf(
				'Wellness practices in %s: verified studios, clinics and practitioners with real prices, timetables and contact details. No booking fees.',
				decoded( $term )
			)
			: sprintf(
				'Compare %s across the Perth metro — verified practices with real prices, timetables and contact details. No booking fees.',
				strtolower( decoded( $term ) )
			);
	}

	if ( ( is_singular( 'listing' ) || is_singular( 'event' ) ) && ! $desc ) {
		$auto = entity_description( (int) get_the_ID() );
		if ( '' !== $auto ) {
			return $auto;
		}
	}
	// Yoast leaves the description empty unless one is typed, so a guide with a
	// standfirst and no Yoast value shipped without a description at all.
	if ( is_singular( array( 'post', 'page' ) ) && ! $desc ) {
		$auto = excerpt_description( (int) get_the_ID() );
		if ( '' !== $auto ) {
			return $auto;
		}
	}
	if ( is_post_type_archive( 'event' ) && ! $desc ) {
		return __( 'Every wellness workshop, sitting and session across the Perth metro, updated daily — filter by date, suburb, type and price.', 'oria' );
	}
	if ( is_front_page() && ! $desc ) {
		return __( "Perth's independent wellness directory: meditation, yoga, breathwork, massage and more — verified practices, honest prices, and what's on this weekend.", 'oria' );
	}
	if ( is_post_type_archive( 'listing' ) && ! $desc ) {
		return __( 'Browse every meditation studio, yoga school, breathwork facilitator and wellness practice in Perth — checked by hand, with real timetables and prices.', 'oria' );
	}
	return $desc;
}


function seo_canonical( $canonical ) {
	$area = combo_area();
	if ( $area ) {
		$practice = get_queried_object();
		return home_url( '/practice/' . $practice->slug . '/' . $area->slug . '/' );
	}
	return $canonical;
}

/** Empty combos never enter the index; everything else follows Yoast. */
function seo_robots( $robots ) {
	if ( combo_area() && 0 === combo_count() ) {
		return 'noindex, follow';
	}
	return $robots;
}

/** Core <title> fallback for the same pages when Yoast is inactive. */
function core_title( array $parts ): array {
	$own = page_default( 'title' );
	if ( '' !== $own ) {
		// Core appends the site name itself, so hand back just the page part.
		$parts['title'] = trim( (string) preg_replace( '/\s*\|\s*' . preg_quote( get_bloginfo( 'name' ), '/' ) . '$/', '', $own ) );
		return $parts;
	}

	$area = combo_area();
	if ( $area ) {
		$parts['title'] = sprintf( '%s in %s', decoded( get_queried_object() ), decoded( $area ) );
	} elseif ( is_tax( Taxonomies\SPECIALTY ) ) {
		$parts['title'] = sprintf( '%s in Perth', decoded( get_queried_object() ) );
	} elseif ( plain_term() ) {
		$parts['title'] = archive_heading( plain_term() );
	} elseif ( is_post_type_archive( 'event' ) ) {
		$parts['title'] = __( "What's on in Perth — wellness workshops & events", 'oria' );
	} elseif ( is_singular( 'listing' ) ) {
		$context = listing_context( (int) get_the_ID() );
		if ( '' !== $context ) {
			$parts['title'] = sprintf( '%s — %s', decoded_title( (int) get_the_ID() ), $context );
		}
	}
	return $parts;
}
