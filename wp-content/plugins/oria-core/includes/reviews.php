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
 *    reviews therefore supersede the seeded aggregates automatically, and
 *    falling below HEADLINE_MIN clears ours so Google carries the listing
 *    again rather than a stale star hanging about.
 */

declare(strict_types=1);

namespace Oria\Core\Reviews;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_RATING = 'oria_rating';

/*
 * The structured half of a review, stored as comment meta beside the star.
 *
 * This is the part worth more than the star: what somebody actually tried,
 * how experienced they were, and who they think it suits. It answers
 * questions a five-point scale cannot ("is this alright for a beginner?"),
 * and it is the raw material for audience evidence and review-backed facets
 * later on.
 */
const META_MEMBER    = 'oria_member_id';
const META_SERVICE   = 'oria_service_term';  // term_id: what they tried
const META_EXPERIENCE = 'oria_experience';   // beginner | some | experienced
const META_BEST_FOR  = 'oria_best_for';      // audience term_ids
const META_RECOMMEND = 'oria_recommend';     // 1 | 0
const META_RETURN    = 'oria_would_return';  // 1 | 0
const META_VISIT     = 'oria_visit_month';   // YYYY-MM, never an exact date
const META_FLAGS     = 'oria_flags';         // auto-moderation hits
const META_IP        = 'oria_ip_hash';       // salted hash, abuse only

/*
 * Ratings run 1 to 5 in half-star steps, and are stored as the number a
 * person would say out loud — 3.5, not 7 half-stars. Reading the raw meta
 * or a database export gives the rating itself, and schema.org wants the
 * real decimal anyway.
 *
 * The only care needed is casting: a (int) on 3.5 silently becomes 3, so
 * everything that touches a rating goes through the two helpers below.
 */
const RATING_MIN  = 1.0;
const RATING_MAX  = 5.0;
const RATING_STEP = 0.5;

/** A rating we would accept: within range and landing on a half. */
function is_valid_rating( $value ): bool {
	if ( ! is_numeric( $value ) ) {
		return false;
	}
	$rating = (float) $value;
	if ( $rating < RATING_MIN || $rating > RATING_MAX ) {
		return false;
	}
	// Compare in halves as integers; floats do not compare cleanly.
	return abs( ( $rating * 2 ) - round( $rating * 2 ) ) < 0.0001;
}

/** The rating as a number, or 0.0 if it is not one we would accept. */
function normalise_rating( $value ): float {
	return is_valid_rating( $value ) ? round( ( (float) $value ) * 2 ) / 2 : 0.0;
}

/** Every value the picker offers, lowest first: 1, 1.5, 2 … 5. */
function rating_steps(): array {
	$steps = array();
	for ( $r = RATING_MIN; $r <= RATING_MAX; $r += RATING_STEP ) {
		$steps[] = round( $r, 1 );
	}
	return $steps;
}

/** For display: "3.5", and "4.0" rather than a bare "4". */
function rating_label( float $rating ): string {
	return number_format_i18n( $rating, 1 );
}

/** Experience levels, in the order they read on the form. */
function experience_levels(): array {
	return array(
		'beginner'    => __( 'New to it', 'oria' ),
		'some'        => __( 'Some experience', 'oria' ),
		'experienced' => __( 'Experienced', 'oria' ),
	);
}

/**
 * Everything a review carries, read back in one call.
 *
 * @return array<string,mixed>
 */
function details( int $comment_id ): array {
	$best_for = get_comment_meta( $comment_id, META_BEST_FOR, true );

	return array(
		'rating'       => normalise_rating( get_comment_meta( $comment_id, META_RATING, true ) ),
		'member_id'    => (int) get_comment_meta( $comment_id, META_MEMBER, true ),
		'service'      => (int) get_comment_meta( $comment_id, META_SERVICE, true ),
		'experience'   => (string) get_comment_meta( $comment_id, META_EXPERIENCE, true ),
		'best_for'     => is_array( $best_for ) ? array_map( 'intval', $best_for ) : array(),
		'recommend'    => '' === (string) get_comment_meta( $comment_id, META_RECOMMEND, true )
			? null
			: (bool) get_comment_meta( $comment_id, META_RECOMMEND, true ),
		'would_return' => '' === (string) get_comment_meta( $comment_id, META_RETURN, true )
			? null
			: (bool) get_comment_meta( $comment_id, META_RETURN, true ),
		'visit_month'  => (string) get_comment_meta( $comment_id, META_VISIT, true ),
	);
}

/**
 * How many approved Oria reviews a listing needs before its own rating
 * becomes the headline.
 *
 * One, deliberately. A listing with none of our reviews is carried by its
 * Google rating, so no page ever reads as dead; the first Oria review takes
 * over, labelled as an Oria Haven rating so the two sources are never
 * confused. Raise it if early reviews prove too swingy against a large
 * Google score — this is the only place the rule is written.
 */
const HEADLINE_MIN = 1;

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

	/*
	 * Reviews are not threaded conversations, so core's reply link is
	 * removed — but only for visitors. A practice answering a review of
	 * itself is a right of reply, handled by its own form in
	 * review-replies.php rather than by core's threading.
	 */
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

	$rating = isset( $_POST[ META_RATING ] ) ? wp_unslash( $_POST[ META_RATING ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! is_valid_rating( $rating ) ) {
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
	$rating = isset( $_POST[ META_RATING ] ) ? normalise_rating( wp_unslash( $_POST[ META_RATING ] ) ) : 0.0; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( $rating > 0 ) {
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
		log_moderation( (int) $comment->comment_ID, $old_status, $new_status );
		recompute( (int) $comment->comment_post_ID );
	}
}

/**
 * Write one line of the moderation record.
 *
 * WordPress remembers that a comment's status changed, but not by whose
 * hand. For a directory publishing reviews of other people's businesses
 * that gap matters: the ACCC's position on online reviews is that negative
 * ones must not be quietly removed, and the only way to show they were not
 * is a record of every decision, who made it, and when. Append-only.
 *
 * @param string $reason Optional note; the admin screen supplies one on a
 *                       rejection, and nothing else needs to.
 */
function log_moderation( int $comment_id, string $from, string $to, string $reason = '' ): void {
	global $wpdb;

	if ( ! function_exists( '\Oria\Core\Db\review_log' ) ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->insert(
		\Oria\Core\Db\review_log(),
		array(
			'comment_id'    => $comment_id,
			'action'        => 'status',
			'from_status'   => substr( $from, 0, 20 ),
			'to_status'     => substr( $to, 0, 20 ),
			'actor_user_id' => get_current_user_id(),
			'reason'        => '' !== $reason ? $reason : null,
			'created_at'    => current_time( 'mysql', true ),
		),
		array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
	);
}

/**
 * The decisions taken on one review, oldest first.
 *
 * @return array<int, array<string,mixed>>
 */
function moderation_log( int $comment_id ): array {
	global $wpdb;

	if ( ! function_exists( '\Oria\Core\Db\review_log' ) ) {
		return array();
	}

	$table = \Oria\Core\Db\review_log();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE comment_id = %d ORDER BY log_id ASC", $comment_id ),
		ARRAY_A
	);
}

/**
 * Aggregate = the average of approved review ratings, written to the native
 * `rating` / `review_count` fields that Theme\effective_rating() prefers.
 *
 * Below HEADLINE_MIN the aggregate is cleared rather than left alone, so a
 * listing always shows either its own reviews or Google's — never a number
 * with nothing behind it. Verified before this shipped: no listing on
 * production carries a native rating, so nothing is being overwritten.
 */
function recompute( int $post_id ): void {
	$reviews = approved( $post_id );

	$ratings = array();
	foreach ( $reviews as $review ) {
		$rating = normalise_rating( get_comment_meta( (int) $review->comment_ID, META_RATING, true ) );
		if ( $rating > 0 ) {
			$ratings[] = $rating;
		}
	}

	/*
	 * Below the threshold the listing must fall back to its Google rating,
	 * which means actively clearing ours rather than returning early: a
	 * listing whose last review is unapproved or deleted would otherwise
	 * keep a stale star for ever, and it would be an Oria star with no Oria
	 * reviews behind it.
	 */
	if ( count( $ratings ) < HEADLINE_MIN ) {
		clear( $post_id );
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

/**
 * Drop the native aggregate so Theme\effective_rating() falls through to
 * Google. Both the ACF value and the raw meta go: update_field() writes the
 * key ACF reads, and a leftover raw row would keep answering get_post_meta().
 */
function clear( int $post_id ): void {
	if ( function_exists( 'update_field' ) ) {
		update_field( 'rating', '', $post_id );
		update_field( 'review_count', '', $post_id );
	}
	delete_post_meta( $post_id, 'rating' );
	delete_post_meta( $post_id, 'review_count' );
}

/** How many approved Oria reviews a listing carries. */
function count_for( int $post_id ): int {
	return count( approved( $post_id ) );
}

/**
 * Approved reviews for a listing, newest first.
 *
 * Top level only. A practitioner's right of reply is a child comment on the
 * same listing, and without the parent filter it would be counted as a
 * review, rendered as one, and included in the schema — a business would be
 * able to lift its own listing's review count by answering.
 *
 * @return \WP_Comment[]
 */
function approved( int $post_id ): array {
	return get_comments(
		array(
			'post_id' => $post_id,
			'status'  => 'approve',
			'parent'  => 0,
			'type'    => 'comment',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		)
	);
}

/** The star value on a review comment, in halves. */
function rating_of( \WP_Comment $comment ): float {
	return normalise_rating( get_comment_meta( (int) $comment->comment_ID, META_RATING, true ) );
}
