<?php
/**
 * Where journal articles and journeys live.
 *
 * Posts used to sit at the site root -- /day-retreats-near-perth/ -- which
 * put an article in the same namespace as every page, and gave a reader no
 * clue from the URL what kind of thing they were about to open. They now
 * sit under the index they belong to:
 *
 *   /journal/day-retreats-near-perth/
 *   /journeys/one-day-on-the-cottesloe-coast/
 *
 * Both are the same post type, so the segment cannot come from the permalink
 * setting -- WordPress has one structure for all posts. It comes from the
 * same test the journeys index uses: an article with journey steps is a
 * journey, anything else is a guide. One definition, read in both places.
 *
 * The consequence, which is a feature rather than a hazard: adding steps to
 * an existing article moves it from /journal/ to /journeys/, and the old
 * address 301s to the new one on its own. Nothing to remember, nothing to
 * record in a map.
 *
 * Old root URLs are indexed, so they cannot simply stop working. Rather than
 * storing thirteen redirects that would go stale the moment an article is
 * renamed, canonical() sends any request that reaches a post by a path other
 * than its permalink to the permalink, once, with a 301. That covers the old
 * root URLs, the wrong-segment URLs, and every article written from now on.
 */

declare(strict_types=1);

namespace Oria\Core\PostUrls;

use Oria\Core\Journeys;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

const JOURNAL   = 'journal';
const JOURNEYS  = 'journeys';
const REWRITE_V = '1';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 11 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );
	add_filter( 'post_link', __NAMESPACE__ . '\link', 10, 2 );
	// Priority 2: after the redirects map, before redirect_canonical, so a
	// post never renders at two addresses.
	add_action( 'template_redirect', __NAMESPACE__ . '\canonical', 2 );
}

/**
 * Is this article a journey?
 *
 * The journeys index defines a journey as an article carrying journey steps.
 * This asks the meta directly rather than through get_field(): the URL of
 * every post is built on nearly every request, and the repeater's own key
 * holds the row count, so one meta read answers it.
 */
function is_journey( int $post_id ): bool {
	return (int) get_post_meta( $post_id, 'journey', true ) > 0;
}

function segment( int $post_id ): string {
	return is_journey( $post_id ) ? JOURNEYS : JOURNAL;
}

/**
 * Prefix the permalink.
 *
 * Only plain posts move. Pages, listings and events keep the addresses they
 * have, and a draft still has no meaningful slug, so it is left alone and
 * WordPress's own preview URL survives.
 */
function link( string $permalink, $post ): string {
	$post = get_post( $post );
	if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
		return $permalink;
	}
	if ( in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft', 'future' ), true ) ) {
		return $permalink;
	}
	$path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
	if ( '' === $path || '/' === $path ) {
		return $permalink;
	}
	// Already prefixed (a filter running twice, or a rebuilt link).
	$first = strtok( trim( $path, '/' ), '/' );
	if ( in_array( $first, array( JOURNAL, JOURNEYS ), true ) ) {
		return $permalink;
	}
	return home_url( '/' . segment( $post->ID ) . '/' . trim( $path, '/' ) . '/' );
}

/**
 * One rule each. The trailing ([^/]+) is required, so neither of these can
 * swallow /journal/ (a page) or /journeys/ (the index) -- those have no
 * second segment and keep their own rules.
 */
function route(): void {
	foreach ( array( JOURNAL, JOURNEYS ) as $seg ) {
		add_rewrite_rule( '^' . $seg . '/([^/]+)/?$', 'index.php?name=$matches[1]', 'top' );
		// Feeds and paged comments would otherwise fall through to a 404 at
		// the new address while still working at the old one.
		add_rewrite_rule( '^' . $seg . '/([^/]+)/feed/?$', 'index.php?name=$matches[1]&feed=feed', 'top' );
		add_rewrite_rule( '^' . $seg . '/([^/]+)/comment-page-([0-9]{1,})/?$', 'index.php?name=$matches[1]&cpage=$matches[2]', 'top' );
	}
}

function maybe_flush(): void {
	if ( get_option( 'oria_post_urls_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_post_urls_rewrite_v', REWRITE_V );
	}
}

/**
 * Send a post reached by any other path to its permalink.
 *
 * This is what keeps the old root URLs alive, and what repairs an article
 * that changes kind. Previews, feeds and embeds are left alone: a preview
 * has no canonical address yet, and redirecting a feed breaks the reader
 * that asked for it.
 */
function canonical(): void {
	if ( ! is_singular( 'post' ) || is_feed() || is_embed() || is_preview() ) {
		return;
	}
	if ( is_customize_preview() || ( isset( $_GET['preview'] ) || isset( $_GET['p'] ) || isset( $_GET['page_id'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof \WP_Post ) {
		return;
	}
	$target = (string) get_permalink( $post );
	if ( '' === $target ) {
		return;
	}
	$want = (string) wp_parse_url( $target, PHP_URL_PATH );
	$have = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' === $want || untrailingslashit( $want ) === untrailingslashit( $have ) ) {
		return;
	}
	// Carry the query string, so a UTM-tagged old link keeps its tags.
	$query = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	wp_safe_redirect( $target . ( '' !== $query ? '?' . $query : '' ), 301 );
	exit;
}
