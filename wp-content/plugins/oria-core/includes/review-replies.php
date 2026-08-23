<?php
/**
 * Right of reply: a practice answering a review of itself.
 *
 * A directory that publishes criticism of a named business and gives it no
 * way to answer is not being even-handed, and is the version of this that
 * gets lawyers involved. So: one public reply per review, from the account
 * that owns the listing, moderated the same way the review was.
 *
 * The boundaries are deliberate.
 *
 *   One reply, not a thread. The point is a right of response, not an
 *   argument conducted under somebody's review.
 *   Only the owner, and only while paying. Ownership\manages() is the same
 *   test that gates editing the listing, so a lapsed subscription closes
 *   this with everything else.
 *   Only on approved reviews. Replying to something not yet published would
 *   leak the queue.
 *   Never a way back to reviewing. A practitioner replying is answering
 *   their own listing; it does not soften the wall in Members\can_review().
 */

declare(strict_types=1);

namespace Oria\Core\Replies;

use Oria\Core\Ownership;
use Oria\Core\PostTypes;
use Oria\Core\Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TYPE     = 'oria_reply';
const MAX_BODY = 1200;

function bootstrap(): void {
	add_action( 'admin_post_oria_review_reply', __NAMESPACE__ . '\handle' );

	// Replies are comments too, and must never be counted as reviews or
	// hidden from the moderation queue.
	add_filter( 'comment_row_actions', __NAMESPACE__ . '\row_label', 10, 2 );
}

/* ------------------------------------------------------------ permission */

/**
 * May this user reply to this review?
 *
 * @return true|\WP_Error
 */
function can_reply( int $user_id, int $comment_id ) {
	$comment = get_comment( $comment_id );

	if ( ! $comment instanceof \WP_Comment ) {
		return new \WP_Error( 'no_review', __( 'That review no longer exists.', 'oria' ) );
	}

	if ( (int) $comment->comment_parent > 0 ) {
		return new \WP_Error( 'not_a_review', __( 'You can only reply to a review.', 'oria' ) );
	}

	$listing_id = (int) $comment->comment_post_ID;

	if ( PostTypes\LISTING !== get_post_type( $listing_id ) ) {
		return new \WP_Error( 'not_a_listing', __( 'That is not a listing.', 'oria' ) );
	}

	if ( '1' !== (string) $comment->comment_approved ) {
		return new \WP_Error( 'not_published', __( 'That review has not been published.', 'oria' ) );
	}

	if ( $user_id <= 0 || ! Ownership\manages( $user_id, $listing_id ) ) {
		return new \WP_Error( 'not_owner', __( 'Only the practice that owns this listing can reply, while its listing is claimed.', 'oria' ) );
	}

	if ( null !== for_review( $comment_id ) ) {
		return new \WP_Error( 'already', __( 'You have already replied to this review.', 'oria' ) );
	}

	return true;
}

/** Does the current visitor own this listing and could they reply at all? */
function viewer_may_reply( int $listing_id ): bool {
	$user_id = get_current_user_id();
	return $user_id > 0 && Ownership\manages( $user_id, $listing_id );
}

/* ----------------------------------------------------------------- read */

/** The reply to one review, published or not. */
function for_review( int $comment_id ): ?\WP_Comment {
	$found = get_comments(
		array(
			'parent' => $comment_id,
			'type'   => TYPE,
			'status' => 'all',
			'number' => 1,
		)
	);
	return $found ? $found[0] : null;
}

/** The published reply to one review, for the front end. */
function published_for( int $comment_id ): ?\WP_Comment {
	$reply = for_review( $comment_id );
	return ( null !== $reply && '1' === (string) $reply->comment_approved ) ? $reply : null;
}

/* --------------------------------------------------------------- write */

function handle(): void {
	$comment_id = isset( $_POST['review'] ) ? (int) $_POST['review'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$comment    = get_comment( $comment_id );
	$listing_id = $comment instanceof \WP_Comment ? (int) $comment->comment_post_ID : 0;

	if ( ! wp_verify_nonce( (string) ( $_POST['oria_reply_nonce'] ?? '' ), 'oria_reply_' . $comment_id ) ) {
		back( $listing_id, 'error', 'expired' );
	}

	$can = can_reply( get_current_user_id(), $comment_id );
	if ( is_wp_error( $can ) ) {
		back( $listing_id, 'error', $can->get_error_code() );
	}

	$body = sanitize_textarea_field( (string) ( $_POST['reply'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' === trim( $body ) ) {
		back( $listing_id, 'error', 'empty' );
	}
	if ( mb_strlen( $body ) > MAX_BODY ) {
		$body = mb_substr( $body, 0, MAX_BODY );
	}

	$user = wp_get_current_user();

	$reply_id = wp_insert_comment(
		array(
			'comment_post_ID'      => $listing_id,
			'comment_parent'       => $comment_id,
			'comment_type'         => TYPE,
			'comment_author'       => wp_specialchars_decode( (string) get_the_title( $listing_id ), ENT_QUOTES ),
			'comment_author_email' => (string) $user->user_email,
			'comment_content'      => $body,
			'user_id'              => (int) $user->ID,
			// Moderated exactly like a review. The owner of a listing is
			// not a neutral party on their own page.
			'comment_approved'     => 0,
		)
	);

	if ( ! $reply_id ) {
		back( $listing_id, 'error', 'insert' );
	}

	if ( function_exists( '\Oria\Core\Reviews\log_moderation' ) ) {
		Reviews\log_moderation( (int) $reply_id, '', 'hold', 'practitioner reply submitted' );
	}

	notify_admin( (int) $reply_id, $listing_id );

	back( $listing_id, 'reply-queued' );
}

function back( int $listing_id, string $state, string $detail = '' ): void {
	$url = $listing_id > 0 ? (string) get_permalink( $listing_id ) : home_url( '/' );
	$url = add_query_arg( array_filter( array( 'review' => $state, 'why' => $detail ) ), $url );
	wp_safe_redirect( $url . '#reviews' );
	exit;
}

function notify_admin( int $reply_id, int $listing_id ): void {
	$to = (string) get_option( 'admin_email' );
	if ( ! is_email( $to ) ) {
		return;
	}

	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );
	$body = '<p>' . esc_html__( 'A practice has replied to a review of itself.', 'oria' ) . '</p>'
		. '<p><strong>' . esc_html( $name ) . '</strong></p>'
		. '<p><a href="' . esc_url( admin_url( 'comment.php?action=editcomment&c=' . $reply_id ) ) . '">' . esc_html__( 'Read it', 'oria' ) . '</a></p>';

	$shell = function_exists( '\Oria\Forms\Emails\shell' )
		? \Oria\Forms\Emails\shell( __( 'A reply is waiting for moderation.', 'oria' ), $body, 'masthead' )
		: $body;

	$headers = function_exists( '\Oria\Forms\Emails\html_headers' )
		? (array) \Oria\Forms\Emails\html_headers()
		: array( 'Content-Type: text/html; charset=UTF-8' );

	/* translators: %s: practice name */
	wp_mail( $to, sprintf( __( 'Reply from %s', 'oria' ), $name ), $shell, $headers );
}

/* ---------------------------------------------------------------- admin */

/**
 * @param array<string,string> $actions
 * @param \WP_Comment          $comment
 * @return array<string,string>
 */
function row_label( $actions, $comment ): array {
	if ( $comment instanceof \WP_Comment && TYPE === $comment->comment_type ) {
		$actions = array( 'oria-reply-note' => '<span class="oria-note"><strong>' . esc_html__( 'Practice reply', 'oria' ) . '</strong></span>' ) + (array) $actions;
	}
	return (array) $actions;
}
