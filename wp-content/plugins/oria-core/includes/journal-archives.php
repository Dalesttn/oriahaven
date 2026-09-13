<?php
/**
 * The journal's leftover WordPress archives: gone.
 *
 * WordPress gives every post category, tag, author and month a page of its
 * own. Here none of them does a job. The journal is fourteen articles with
 * one index at /journal/, nothing on the site links to /category/beginners/
 * or /tag/pilates/, and the category is shown on an article as plain text.
 * The only way anybody reached those pages was Yoast's category sitemap,
 * which handed Google five near-empty lists to crawl and mostly decline:
 * "Discovered - currently not indexed".
 *
 * A 301 rather than a noindex or a 404. Two of them (/category/guides/ and
 * /category/wellness-journey/) are already indexed, and a redirect hands
 * whatever they have earned to the journal instead of throwing it away. A
 * noindex page would still exist and still be crawled, which is the cost
 * this removes.
 *
 * Categories and tags themselves stay: Discover is a category, and the
 * journal code reads categories and tags to pick related practices and
 * areas. Only the archive pages go.
 *
 * Runs after the redirect map (priority 1), so a mapped URL still wins.
 */

declare(strict_types=1);

namespace Oria\Core\JournalArchives;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'template_redirect', __NAMESPACE__ . '\redirect', 2 );

	// Yoast's sitemaps: no category, tag or author lists.
	add_filter( 'wpseo_sitemap_exclude_taxonomy', __NAMESPACE__ . '\exclude_taxonomy', 10, 2 );
	add_filter( 'wpseo_sitemap_exclude_author', '__return_empty_array' );

	// Core's sitemaps, in case Yoast is ever off.
	add_filter( 'wp_sitemaps_taxonomies', __NAMESPACE__ . '\core_taxonomies' );
	add_filter( 'wp_sitemaps_add_provider', __NAMESPACE__ . '\core_provider', 10, 2 );
}

/** Where a retired archive sends its visitor, or '' to leave the request alone. */
function destination(): string {
	if ( is_feed() || is_preview() || is_customize_preview() ) {
		return '';
	}
	// Global functions: PracticesIndex has its own is_category().
	if ( \is_author() ) {
		return home_url( '/about/' );
	}
	if ( \is_category() || \is_tag() || \is_date() ) {
		return home_url( '/journal/' );
	}
	return '';
}

function redirect(): void {
	$to = destination();
	if ( '' !== $to ) {
		wp_safe_redirect( $to, 301, 'Oria Haven' );
		exit;
	}
}

/** @param bool $excluded */
function exclude_taxonomy( $excluded, string $taxonomy ): bool {
	return in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ? true : (bool) $excluded;
}

/** @param array<string, \WP_Taxonomy> $taxonomies */
function core_taxonomies( array $taxonomies ): array {
	unset( $taxonomies['category'], $taxonomies['post_tag'] );
	return $taxonomies;
}

/** @param mixed $provider */
function core_provider( $provider, string $name ) {
	return 'users' === $name ? false : $provider;
}
