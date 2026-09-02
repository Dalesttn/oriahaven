<?php
/**
 * Discover — the guides that sit outside the journal.
 *
 * A Discover piece is a reference article attached to a hub: how to choose a
 * singing bowl belongs beside /singing-bowls/, not in a reverse-chronological
 * feed of Perth reporting. Both are writing, but the journal is a publication
 * with an order, and a buying guide has no date on it worth respecting.
 *
 * So these are ordinary posts in an ordinary category, kept out of the
 * journal's listings only. They keep their /journal/{slug}/ address, stay
 * indexable, stay in the sitemap, and stay findable in search -- somebody
 * looking for the bowl guide should find it wherever they ask, which is the
 * same line Journeys draws for the same reason.
 *
 * The one place they are not hidden is their own category archive. Filtering
 * a term out of the archive FOR that term would leave an empty page with a
 * heading on it.
 */

declare(strict_types=1);

namespace Oria\Core\Discover;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

const SLUG = 'discover';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\ensure_term', 20 );
	add_action( 'pre_get_posts', __NAMESPACE__ . '\exclude_from_journal' );
}

/**
 * The category has to exist before a post can be filed in it, and the
 * category is code's business rather than an editor's -- the exclusion below
 * is meaningless without it. Cheap: one option read on a normal request.
 */
function ensure_term(): void {
	if ( get_option( 'oria_discover_term' ) && term_exists( SLUG, 'category' ) ) {
		return;
	}
	$term = get_term_by( 'slug', SLUG, 'category' );
	if ( ! $term instanceof \WP_Term ) {
		$made = wp_insert_term(
			__( 'Discover', 'oria' ),
			'category',
			array(
				'slug'        => SLUG,
				'description' => __( 'Reference guides that sit beside a topic hub rather than in the journal feed.', 'oria' ),
			)
		);
		if ( is_wp_error( $made ) ) {
			return;
		}
	}
	update_option( 'oria_discover_term', 1, false );
}

function term_id(): int {
	$t = get_term_by( 'slug', SLUG, 'category' );
	return $t instanceof \WP_Term ? (int) $t->term_id : 0;
}

/**
 * Out of the journal index and its archives, and nowhere else.
 *
 * Search is deliberately not on this list. Neither is the feed of a single
 * tag when that tag is how somebody arrived at the subject.
 */
function exclude_from_journal( \WP_Query $q ): void {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( ! ( $q->is_home() || $q->is_category() || $q->is_tag() || $q->is_date() || $q->is_author() ) ) {
		return;
	}
	// Its own archive is where a Discover piece is supposed to be.
	if ( $q->is_category( SLUG ) ) {
		return;
	}
	$id = term_id();
	if ( ! $id ) {
		return;
	}
	$existing = (array) $q->get( 'category__not_in' );
	$q->set( 'category__not_in', array_values( array_unique( array_merge( $existing, array( $id ) ) ) ) );
}
