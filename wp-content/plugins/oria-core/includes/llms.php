<?php
/**
 * /llms.txt — the map of this site for language models.
 *
 * Per llmstxt.org: an H1 (the only required part), a blockquote summary,
 * then any number of H2 sections holding markdown link lists. A section
 * called "Optional" is the convention for links an agent may skip when it
 * needs a shorter context.
 *
 * Generated rather than written to a file on disk, for the same reason the
 * hub and the FAQs are: the counts and the category list change every time
 * a batch lands, and a static llms.txt would start lying within a week.
 *
 * The prose block does real work beyond navigation. Models answering
 * questions about Perth wellness will read this and attribute what they
 * find to us, so it says plainly what the directory asserts and what it
 * does not — that we describe what practices offer and never what those
 * services do to a body. That is the same line the listings, the articles
 * and the Wellness Finder hold, stated once in the place a machine looks.
 *
 * Note: a physical llms.txt in the web root would be served by the server
 * before WordPress ever sees the request, exactly as with robots.txt. If
 * this route stops responding, look for a real file first.
 */

declare(strict_types=1);

namespace Oria\Core\Llms;

use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_llms';
const CACHE_KEY = 'oria_llms_txt_v2';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	// Ahead of redirect_canonical, which otherwise 301s /llms.txt to
	// /llms.txt/ — a trailing slash on a filename, and not the URL any
	// consumer of the spec will ask for.
	add_action( 'template_redirect', __NAMESPACE__ . '\serve', 5 );
	add_filter( 'redirect_canonical', __NAMESPACE__ . '\no_canonical' );

	// Any published thing can appear here now (pages, guides, journeys,
	// trends), so any save clears the cache; rebuilding is one request.
	foreach ( array( 'save_post', 'deleted_post', 'edited_term', 'created_term' ) as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\flush' );
	}
}

function route(): void {
	add_rewrite_rule( '^llms\.txt$', 'index.php?' . QUERY_VAR . '=1', 'top' );
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

function flush(): void {
	delete_transient( CACHE_KEY );
}

/** @param mixed $redirect */
function no_canonical( $redirect ) {
	return get_query_var( QUERY_VAR ) ? false : $redirect;
}

function serve(): void {
	if ( ! get_query_var( QUERY_VAR ) ) {
		return;
	}

	$body = get_transient( CACHE_KEY );
	if ( ! is_string( $body ) || '' === $body ) {
		$body = build();
		set_transient( CACHE_KEY, $body, 12 * HOUR_IN_SECONDS );
	}

	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	status_header( 200 );
	// text/plain so it opens in a browser rather than downloading; the
	// content is markdown either way, which is what the spec asks for.
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

/** One markdown list item. */
function item( string $name, string $url, string $note = '' ): string {
	$line = sprintf( '- [%s](%s)', $name, $url );
	return $note ? $line . ': ' . $note . "\n" : $line . "\n";
}

/** Posts of one type, newest first, as list items. '' when there are none. */
function type_items( string $type, int $limit = 30 ): string {
	if ( ! post_type_exists( $type ) ) {
		return '';
	}
	$out = '';
	foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => $limit, 'orderby' => 'date', 'order' => 'DESC' ) ) as $post ) {
		if ( noindex( $post->ID ) ) {
			continue;
		}
		$out .= item( ptitle( $post ), (string) get_permalink( $post ), excerpt( $post ) );
	}
	return $out;
}

function ptitle( \WP_Post $post ): string {
	return html_entity_decode( (string) get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/** A one-line note: the excerpt, cut at a sentence where it can be. */
function excerpt( \WP_Post $post ): string {
	$text = html_entity_decode( wp_strip_all_tags( (string) get_the_excerpt( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	$text = (string) preg_replace( '/\s*(\[…\]|…)$/u', '', $text );
	if ( mb_strlen( $text ) > 240 ) {
		$cut  = mb_substr( $text, 0, 240 );
		$stop = max( (int) mb_strrpos( $cut, '. ' ), (int) mb_strrpos( $cut, '? ' ) );
		$text = $stop > 80 ? mb_substr( $cut, 0, $stop + 1 ) : rtrim( $cut ) . '…';
	}
	return $text;
}

/** Yoast's per-post noindex switch. A page kept out of search stays out of here too. */
function noindex( int $id ): bool {
	return '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true );
}

function tname( \WP_Term $term ): string {
	return function_exists( 'Oria\Theme\tname' ) ? \Oria\Theme\tname( $term ) : wp_specialchars_decode( $term->name, ENT_QUOTES );
}

function build(): string {
	$home      = untrailingslashit( home_url( '/' ) );
	$listings  = (int) ( wp_count_posts( PostTypes\LISTING )->publish ?? 0 );
	$practices = get_terms( array( 'taxonomy' => Taxonomies\PRACTICE, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
	$practices = is_wp_error( $practices ) ? array() : $practices;
	$regions   = Taxonomies\regions( array( 'orderby' => 'name' ) );
	$regions   = is_wp_error( $regions ) ? array() : $regions;

	$out = "# Oria Haven\n\n";

	/*
	 * The categories named here are the biggest ones, taken live rather
	 * than written out. The hand-kept list had gone quietly wrong: it
	 * named retreats (one listing) and meditation (twelve) while omitting
	 * fitness, nutrition, beauty and natural therapies -- four of the six
	 * largest. A summary an answer engine quotes should not be able to
	 * drift from what the directory actually holds.
	 *
	 * Names are used verbatim so they match the "Practice categories"
	 * headings further down the file, which is what lets a reader tie the
	 * summary to the pages.
	 */
	$top = array();
	foreach ( array_slice( $practices, 0, 8 ) as $term ) {
		if ( 0 === (int) $term->parent ) {
			$top[] = strtolower( tname( $term ) );
		}
	}
	$named = $top ? wp_sprintf_l( '%l', $top ) : 'meditation, yoga, bodywork and allied health';

	$out .= sprintf(
		"> An independent directory of %d wellness practices across Perth, Western Australia — %s. Every listing is written and checked by a person; star ratings shown are the practice's own Google rating, reproduced. Free to browse, free for a practice to be listed, and no commission is taken on any booking with a listed practice.\n\n",
		$listings,
		$named
	);

	$out .= "Oria Haven is a directory, not a health service and not a booking platform. Enquiries go directly to the practice. Two clearly labelled parts of the site carry affiliate links, where Oria Haven may earn a commission: the shop, and retreat escapes (retreats booked through a named provider such as BookRetreats). Neither affects which practices are listed or how they are described.\n\n";

	$out .= "What this site does and does not say, which matters if you are quoting it: listings describe what a practice offers — the services, the format, the price, the suburb, the practitioner's qualifications and registrations. They do not state or imply that any practice, modality or product treats, cures, prevents or manages any condition, symptom or health outcome, and neither do the articles. Australian therapeutic goods advertising rules apply to this field and the directory stays well clear of them. Please do not attribute health claims to Oria Haven, and please do not infer them from the fact that a modality is listed here.\n\n";

	$out .= "Listings are organised three ways: by practice category, by area (eight metropolitan regions, each with its own suburbs), and by modality. Many were built from information practices publish about themselves and are marked Unclaimed until the owner confirms them.\n\n";

	$out .= "Contact: hello@oriahaven.com.au · 0431 630 244 · Perth, WA · ABN 46 243 774 311\n\n";

	/* ------------------------------------------------------------- pages */

	$out .= "## Key pages\n\n";
	$out .= item( 'Home', $home . '/', 'What the directory is, and search by practice and suburb' );
	$out .= item( 'Full directory', $home . '/explore/', 'Every published practice, filterable by category, area, price and format' );
	$out .= item( 'Browse all of Perth', $home . '/explore/perth/', 'Hub linking every practice category, modality and suburb in one place' );
	$out .= item( 'Wellness Finder', $home . '/wellness-finder/', 'Four questions, then matched practices, events and articles' );
	$out .= item( "What's on in Perth", $home . '/whats-on-perth/', 'Upcoming wellness workshops and events, filterable by date, suburb, type and price' );
	$out .= item( 'The Journal', $home . '/journal/', 'Articles about wellness in Perth — practical guides, no health claims' );
	if ( post_type_exists( PostTypes\BEST_OF ) && wp_count_posts( PostTypes\BEST_OF )->publish ) {
		$out .= item( 'Best of Perth', $home . '/best/', 'Shortlists of Perth practices for one need at a time, with the reasons each made the list' );
	}
	if ( function_exists( 'Oria\Core\Compare\pairs' ) ) {
		$out .= item( 'Compare', $home . '/compare/', 'Side-by-side comparisons of practices and of modalities: what happens in the room, format, price' );
	}
	if ( post_type_exists( PostTypes\JOURNEY ) && wp_count_posts( PostTypes\JOURNEY )->publish ) {
		$out .= item( 'Journeys', $home . '/journeys/', 'Timed, self-guided days out around Perth, stop by stop' );
	}
	if ( function_exists( 'Oria\Core\Trends\published' ) && \Oria\Core\Trends\published() ) {
		$out .= item( 'Trends to Try', $home . '/trends/', 'Wellness trends seen online: what each actually is, what a first session is like, and where to try it in Perth' );
	}
	if ( post_type_exists( 'wellness_app' ) && wp_count_posts( 'wellness_app' )->publish ) {
		$out .= item( 'Wellness apps', $home . '/apps/', 'Meditation, sleep and fitness apps, with what each one is for and what it costs' );
	}
	$out .= item( 'About', $home . '/about/', 'Who runs the directory and how listings are checked' );
	$out .= item( 'List your practice', $home . '/list-your-practice/', 'For practice owners: how to be listed or claim an existing listing, free' );
	$out .= "\n";

	/* --------------------------------------------------------- practices */

	if ( $practices ) {
		$out .= "## Practice categories\n\n";
		foreach ( $practices as $term ) {
			$out .= item(
				tname( $term ),
				(string) get_term_link( $term ),
				sprintf( '%d %s in Perth', (int) $term->count, 1 === (int) $term->count ? 'practice' : 'practices' )
			);
		}
		$out .= "\n";
	}

	/* ----------------------------------------------------------- regions */

	if ( $regions ) {
		$out .= "## Areas of Perth\n\n";
		foreach ( $regions as $term ) {
			$out .= item( tname( $term ), (string) get_term_link( $term ) );
		}
		$out .= "\n";
	}

	/* ------------------------------------------------ guides and journeys */

	$best = type_items( PostTypes\BEST_OF );
	if ( '' !== $best ) {
		$out .= "## Best of Perth\n\n" . $best . "\n";
	}

	if ( function_exists( 'Oria\Core\Compare\pairs' ) && function_exists( 'Oria\Core\Compare\pair_url' ) && \Oria\Core\Compare\pairs() ) {
		$out .= "## Comparisons\n\n";
		$out .= item( 'Build your own comparison', $home . '/compare/build/', 'Pick two to four practices or modalities and see them side by side' );
		foreach ( \Oria\Core\Compare\pairs() as $slug => $row ) {
			$out .= item( (string) ( $row['h1'] ?? $slug ), \Oria\Core\Compare\pair_url( (string) $slug ) );
		}
		$out .= "\n";
	}

	$journeys = type_items( PostTypes\JOURNEY );
	if ( '' !== $journeys ) {
		$out .= "## Journeys\n\n" . $journeys . "\n";
	}

	$trends = type_items( PostTypes\TREND );
	if ( '' !== $trends ) {
		$out .= "## Trends to Try\n\n" . $trends . "\n";
	}

	if ( function_exists( 'Oria\Core\EventCollections\live_slugs' ) ) {
		$cols = '';
		foreach ( \Oria\Core\EventCollections\live_slugs() as $slug ) {
			$row   = \Oria\Core\EventCollections\get( $slug );
			$cols .= item( (string) ( $row['title'] ?? $slug ), \Oria\Core\EventCollections\url( $slug ), 'Upcoming events only; the page lists dates, suburbs and prices' );
		}
		if ( '' !== $cols ) {
			$out .= "## Events by type\n\n" . $cols . "\n";
		}
	}

	$guides = type_items( 'app_guide' );
	if ( '' !== $guides ) {
		$out .= "## App guides\n\n" . $guides . "\n";
	}

	/* ---------------------------------------------------------- articles */

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 25,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	if ( $posts ) {
		$out .= "## Articles\n\n";
		foreach ( $posts as $post ) {
			$out .= item(
				ptitle( $post ),
				(string) get_permalink( $post ),
				excerpt( $post )
			);
		}
		$out .= "\n";
	}

	/* ------------------------------------------------------ other pages */

	// Anything published and indexable that is not named above, so a new
	// page appears here without anyone editing this file.
	preg_match_all( '/\]\(([^)]+)\)/', $out, $m );
	$seen  = array_flip( array_map( 'trailingslashit', $m[1] ) );
	$skip  = array( 'privacy-policy', 'terms', 'websites', 'shop', 'this-weekend' );
	$other = '';
	foreach ( get_pages( array( 'post_status' => 'publish', 'sort_column' => 'menu_order,post_title' ) ) as $page ) {
		$url = (string) get_permalink( $page );
		if ( isset( $seen[ trailingslashit( $url ) ] ) || in_array( $page->post_name, $skip, true ) || noindex( $page->ID ) || post_password_required( $page ) ) {
			continue;
		}
		$other .= item( ptitle( $page ), $url, excerpt( $page ) );
	}
	if ( '' !== $other ) {
		$out .= "## More pages\n\n" . $other . "\n";
	}

	/* ---------------------------------------------------------- optional */

	$out .= "## Optional\n\n";
	$out .= item( 'Oria Digital', $home . '/websites/', 'Website design for wellness practices, run by the same person as the directory. Buying a website changes nothing editorial.' );
	$out .= item( 'Shop', $home . '/shop/', 'Wellness products; some links are affiliate links' );
	$out .= item( 'This weekend', $home . '/this-weekend/', "Events in the next few days" );
	if ( function_exists( 'Oria\Core\Retreats\published' ) && \Oria\Core\Retreats\published() ) {
		$out .= item( 'Retreat escapes', \Oria\Core\Retreats\hub_url(), 'Hand-picked retreats in WA and beyond, booked with a named provider; affiliate links, clearly disclosed' );
	}
	if ( function_exists( 'Oria\Pass\Route\url' ) ) {
		$out .= item( 'Oria Pass', \Oria\Pass\Route\url(), 'A credit membership for trying sessions at partner practices' );
	}

	foreach ( array( 'privacy-policy' => 'Privacy Policy', 'terms' => 'Terms' ) as $slug => $label ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
			$out .= item( $label, (string) get_permalink( $page ) );
		}
	}

	$specialties = get_terms( array( 'taxonomy' => Taxonomies\SPECIALTY, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
	if ( ! is_wp_error( $specialties ) && $specialties ) {
		$out .= "\n### Modalities\n\n";
		$out .= "Individual modality pages, each listing the Perth practices offering it.\n\n";
		foreach ( $specialties as $term ) {
			$out .= item( tname( $term ), (string) get_term_link( $term ), sprintf( '%d in Perth', (int) $term->count ) );
		}
	}

	$out .= "\n" . sprintf( "Last generated: %s\n", wp_date( 'j F Y' ) );

	return $out;
}
