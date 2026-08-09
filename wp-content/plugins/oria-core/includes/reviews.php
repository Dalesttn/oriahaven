<?php
/**
 * Reviews: built on native comments so moderation, spam filtering and the
 * "awaiting approval" flow come from core instead of being reinvented.
 *
 *  - A review is a comment on a listing with a 1–5 rating in comment meta.
 *  - Listing reviews are ALWAYS held for moderation (brief: launch with
 *    lightweight verification and an admin queue).
 *  - Approving/unapproving a review recomputes the listing's aggregate
 *    rating and review_count, which every card and profile reads. Real
 *    reviews therefore supersede the seeded aggregates automatically.
 */

declare(strict_types=1);

namespace Oria\Core\Reviews;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_RATING = 'oria_rating';

function bootstrap(): void {
	// Listings created before comment support existed have comment_status
	// "closed"; open them uniformly rather than editing every post.
	add_filter( 'comments_open', __NAMESPACE__ . '\force_open', 10, 2 );

	add_filter( 'preprocess_comment', __NAMESPACE__ . '\require_rating' );
	add_filter( 'pre_comment_approved', __NAMESPACE__ . '\hold_for_moderation', 10, 2 );
	add_action( 'comment_post', __NAMESPACE__ . '\save_rating', 10, 3 );
	add_action( 'comment_post', __NAMESPACE__ . '\recompute_from_comment', 20 );
	add_action( 'transition_comment_status', __NAMESPACE__ . '\on_status_change', 10, 3 );
	add_action( 'deleted_comment', __NAMESPACE__ . '\recompute_from_comment' );

	// Reviews are not threaded conversations: no replies, newest first.
	add_filter( 'comment_reply_link', '__return_empty_string' );
}

function is_listing_comment( int $post_id ): bool {
	return PostTypes\LISTING === get_post_type( $post_id );
}

/** @param bool $open @param int|string $post_id */
function force_open( $open, $post_id ): bool {
	return is_listing_comment( (int) $post_id ) ? true : (bool) $open;
}

/** A listing review without a valid rating is rejected before it is stored. */
function require_rating( array $commentdata ): array {
	$post_id = (int) ( $commentdata['comment_post_ID'] ?? 0 );
	if ( ! is_listing_comment( $post_id ) ) {
		return $commentdata;
	}

	$rating = isset( $_POST[ META_RATING ] ) ? (int) $_POST[ META_RATING ] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $rating < 1 || $rating > 5 ) {
		wp_die(
			esc_html__( 'Please choose a star rating before sending your review.', 'oria' ),
			esc_html__( 'Rating missing', 'oria' ),
			array( 'back_link' => true, 'response' => 400 )
		);
	}
	return $commentdata;
}

/**
 * Reviews never auto-approve, whatever the site's comment settings say.
 * Spam verdicts are left alone.
 *
 * @param int|string $approved
 */
function hold_for_moderation( $approved, array $commentdata ) {
	if ( ! is_listing_comment( (int) ( $commentdata['comment_post_ID'] ?? 0 ) ) ) {
		return $approved;
	}
	return ( 'spam' === $approved || 'trash' === $approved ) ? $approved : 0;
}

/** @param int|string $comment_id @param int|string $approved */
function save_rating( $comment_id, $approved, array $commentdata ): void {
	if ( ! is_listing_comment( (int) ( $commentdata['comment_post_ID'] ?? 0 ) ) ) {
		return;
	}
	$rating = isset( $_POST[ META_RATING ] ) ? (int) $_POST[ META_RATING ] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $rating >= 1 && $rating <= 5 ) {
		update_comment_meta( (int) $comment_id, META_RATING, $rating );
	}
}

/** @param int|string $comment_id */
function recompute_from_comment( $comment_id ): void {
	$comment = get_comment( $comment_id );
	if ( $comment && is_listing_comment( (int) $comment->comment_post_ID ) ) {
		recompute( (int) $comment->comment_post_ID );
	}
}

function on_status_change( string $new_status, string $old_status, \WP_Comment $comment ): void {
	if ( is_listing_comment( (int) $comment->comment_post_ID ) ) {
		recompute( (int) $comment->comment_post_ID );
	}
}

/**
 * Aggregate = the average of approved review ratings. While a listing has no
 * approved reviews its imported aggregate is left untouched, so seeded
 * ratings survive until real ones exist.
 */
function recompute( int $post_id ): void {
	$reviews = approved( $post_id );

	$ratings = array();
	foreach ( $reviews as $review ) {
		$rating = (int) get_comment_meta( (int) $review->comment_ID, META_RATING, true );
		if ( $rating >= 1 && $rating <= 5 ) {
			$ratings[] = $rating;
		}
	}

	if ( ! $ratings ) {
		return;
	}

	$avg   = round( array_sum( $ratings ) / count( $ratings ), 1 );
	$count = count( $ratings );

	if ( function_exists( 'update_field' ) ) {
		update_field( 'rating', $avg, $post_id );
		update_field( 'review_count', $count, $post_id );
	} else {
		update_post_meta( $post_id, 'rating', $avg );
		update_post_meta( $post_id, 'review_count', $count );
	}
}

/** Approved reviews for a listing, newest first. @return \WP_Comment[] */
function approved( int $post_id ): array {
	return get_comments(
		array(
			'post_id' => $post_id,
			'status'  => 'approve',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		)
	);
}

/** The star value on a review comment. */
function rating_of( \WP_Comment $comment ): int {
	return (int) get_comment_meta( (int) $comment->comment_ID, META_RATING, true );
}
