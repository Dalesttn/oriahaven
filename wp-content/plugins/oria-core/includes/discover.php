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
 * They are not hidden from any category or tag archive -- filtering a term
 * out of an archive somebody explicitly asked for leaves an empty page with
 * a heading on it, and a term count that disagrees with what is shown. Only
 * the undirected feed hides them: the journal index, and the date and author
 * slices of it.
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
 * Out of the journal FEED, and nowhere else.
 *
 * NOT out of a category or tag archive. That was the original rule and it
 * was wrong: a reader on /category/wellness-journey/ has asked for that
 * category, and a post filed in it belongs there whatever else it is also
 * filed under. Hiding it made WordPress's own term count disagree with the
 * page -- the admin listed one post, the archive said "Nothing here yet."
 * and Google indexed the empty result.
 *
 * The note at the top of this file already had the principle right --
 * "filtering a term out of the archive FOR that term would leave an empty
 * page with a heading on it" -- and it was applied to the Discover archive
 * alone, when it holds for every term a visitor explicitly asks for.
 *
 * What is left is the undirected feed: the journal index, and the date and
 * author archives that are the same feed sliced differently. Search stays
 * off the list too; somebody looking for the bowl guide should find it.
 */
function exclude_from_journal( \WP_Query $q ): void {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( ! ( $q->is_home() || $q->is_date() || $q->is_author() ) ) {
		return;
	}
	$id = term_id();
	if ( ! $id ) {
		return;
	}
	$existing = (array) $q->get( 'category__not_in' );
	$q->set( 'category__not_in', array_values( array_unique( array_merge( $existing, array( $id ) ) ) ) );
}
