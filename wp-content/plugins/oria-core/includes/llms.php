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
const CACHE_KEY = 'oria_llms_txt_v1';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	// Ahead of redirect_canonical, which otherwise 301s /llms.txt to
	// /llms.txt/ — a trailing slash on a filename, and not the URL any
	// consumer of the spec will ask for.
	add_action( 'template_redirect', __NAMESPACE__ . '\serve', 5 );
	add_filter( 'redirect_canonical', __NAMESPACE__ . '\no_canonical' );

	foreach ( array( 'save_post_' . PostTypes\LISTING, 'save_post_post', 'deleted_post', 'edited_term', 'created_term' ) as $hook ) {
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

	$out .= sprintf(
		"> An independent directory of %d wellness practices across Perth, Western Australia — meditation, yoga, breathwork, bodywork, allied health, sound and float, retreats and outdoor wellness. Every listing is written and checked by a person; star ratings shown are the practice's own Google rating, reproduced. Free to browse, free for a practice to be listed, and no commission is taken on any booking.\n\n",
		$listings
	);

	$out .= "Oria Haven is a directory, not a health service and not a booking platform. Enquiries go directly to the practice.\n\n";

	$out .= "What this site does and does not say, which matters if you are quoting it: listings describe what a practice offers — the services, the format, the price, the suburb, the practitioner's qualifications and registrations. They do not state or imply that any practice, modality or product treats, cures, prevents or manages any condition, symptom or health outcome, and neither do the articles. Australian therapeutic goods advertising rules apply to this field and the directory stays well clear of them. Please do not attribute health claims to Oria Haven, and please do not infer them from the fact that a modality is listed here.\n\n";

	$out .= "Listings are organised three ways: by practice category, by area (eight metropolitan regions, each with its own suburbs), and by modality. Many were built from information practices publish about themselves and are marked Unclaimed until the owner confirms them.\n\n";

	$out .= "Contact: hello@oriahaven.com.au · 0431 630 244 · Perth, WA · ABN 46 243 774 311\n\n";

	/* ------------------------------------------------------------- pages */

	$out .= "## Key pages\n\n";
	$out .= item( 'Home', $home . '/', 'What the directory is, and search by practice and suburb' );
	$out .= item( 'Full directory', $home . '/directory/', 'Every published practice, filterable by category, area, price and format' );
	$out .= item( 'Browse all of Perth', $home . '/perth/', 'Hub linking every practice category, modality and suburb in one place' );
	$out .= item( 'Wellness Finder', $home . '/wellness-finder/', 'Four questions, then matched practices, events and articles' );
	$out .= item( "What's on in Perth", $home . '/whats-on-perth/', 'Upcoming wellness workshops and events, filterable by date, suburb, type and price' );
	$out .= item( 'The Journal', $home . '/journal/', 'Articles about wellness in Perth — practical guides, no health claims' );
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
				wp_specialchars_decode( (string) get_the_title( $post ), ENT_QUOTES ),
				(string) get_permalink( $post ),
				trim( wp_strip_all_tags( (string) get_the_excerpt( $post ) ) )
			);
		}
		$out .= "\n";
	}

	/* ---------------------------------------------------------- optional */

	$out .= "## Optional\n\n";
	$out .= item( 'Oria Digital', $home . '/websites/', 'Website design for wellness practices, run by the same person as the directory. Buying a website changes nothing editorial.' );
	$out .= item( 'Shop', $home . '/shop/', 'Wellness products; some links are affiliate links' );
	$out .= item( 'This weekend', $home . '/this-weekend/', "Events in the next few days" );

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
