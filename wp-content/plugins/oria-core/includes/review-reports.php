<?php
/**
 * Reporting a review.
 *
 * Anybody can report one — the practice it names, another reader, the
 * person who wrote it. A report queues the review for a second look and
 * emails the moderator. It never hides anything on its own.
 *
 * That last point is the whole design. Auto-hiding on report hands every
 * business a button that deletes criticism of it, which is precisely the
 * selective suppression the ACCC warns directories against. A report is a
 * request for a human to look again, and the reasons are the ones that
 * would actually justify removal under the published policy — not "I
 * disagree with this".
 */

declare(strict_types=1);

namespace Oria\Core\Reports;

use Oria\Core\PostTypes;
use Oria\Core\Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META       = 'oria_reports';
const META_COUNT = 'oria_report_count';
const PER_DAY    = 5;

function bootstrap(): void {
	add_action( 'admin_post_oria_report_review', __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_nopriv_oria_report_review', __NAMESPACE__ . '\handle' );
}

/**
 * The grounds a review can be reported on.
 *
 * Each maps to something in the reviews policy that could justify taking a
 * review down. "I do not agree with it" is deliberately absent.
 *
 * @return array<string,string>
 */
function reasons(): array {
	return array(
		'not-a-customer' => __( 'This person was never a customer', 'oria' ),
		'health-claim'   => __( 'It describes a medical condition or health outcome', 'oria' ),
		'wrong-business' => __( 'It is about a different business', 'oria' ),
		'abusive'        => __( 'It is abusive, or names an individual', 'oria' ),
		'private'        => __( 'It gives away private information', 'oria' ),
		'spam'           => __( 'It is spam or an advertisement', 'oria' ),
	);
}

/* ---------------------------------------------------------------- write */

function handle(): void {
	$comment_id = isset( $_POST['review'] ) ? (int) $_POST['review'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$comment    = get_comment( $comment_id );
	$listing_id = $comment instanceof \WP_Comment ? (int) $comment->comment_post_ID : 0;

	if ( ! $comment instanceof \WP_Comment || PostTypes\LISTING !== get_post_type( $listing_id ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	if ( ! wp_verify_nonce( (string) ( $_POST['oria_report_nonce'] ?? '' ), 'oria_report_' . $comment_id ) ) {
		back( $listing_id, 'error', 'expired' );
	}

	if ( over_limit() ) {
		back( $listing_id, 'error', 'throttled' );
	}

	$reason = sanitize_key( (string) ( $_POST['reason'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! array_key_exists( $reason, reasons() ) ) {
		back( $listing_id, 'error', 'reason' );
	}

	$detail = sanitize_textarea_field( (string) ( $_POST['detail'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$detail = mb_substr( $detail, 0, 500 );

	record( $comment_id, $reason, $detail );
	notify_admin( $comment_id, $listing_id, $reason, $detail );

	back( $listing_id, 'reported' );
}

/** Append one report to the review's record. Nothing is hidden. */
function record( int $comment_id, string $reason, string $detail ): void {
	$reports = get_comment_meta( $comment_id, META, true );
	$reports = is_array( $reports ) ? $reports : array();

	$reports[] = array(
		'reason'  => $reason,
		'detail'  => $detail,
		'at'      => current_time( 'mysql', true ),
		'by'      => get_current_user_id(),
		'ip_hash' => ip_hash(),
	);

	update_comment_meta( $comment_id, META, $reports );
	update_comment_meta( $comment_id, META_COUNT, count( $reports ) );

	// The report is part of the review's history, so it belongs in the same
	// record as the moderation decisions taken on it.
	if ( function_exists( '\Oria\Core\Reviews\log_moderation' ) ) {
		Reviews\log_moderation( $comment_id, '', '', 'reported: ' . $reason );
	}
}

/** @return array<int, array<string,mixed>> */
function for_review( int $comment_id ): array {
	$reports = get_comment_meta( $comment_id, META, true );
	return is_array( $reports ) ? $reports : array();
}

function count_for( int $comment_id ): int {
	return (int) get_comment_meta( $comment_id, META_COUNT, true );
}

/* ------------------------------------------------------------ throttles */

function ip_hash(): string {
	$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	return '' === $ip ? '' : hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
}

function over_limit(): bool {
	$key = 'oria_rep_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$n   = (int) get_transient( $key );
	if ( $n >= PER_DAY ) {
		return true;
	}
	set_transient( $key, $n + 1, DAY_IN_SECONDS );
	return false;
}

/* --------------------------------------------------------------- notify */

function notify_admin( int $comment_id, int $listing_id, string $reason, string $detail ): void {
	$to = (string) get_option( 'admin_email' );
	if ( ! is_email( $to ) ) {
		return;
	}

	$name  = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );
	$label = reasons()[ $reason ] ?? $reason;
	$total = count_for( $comment_id );

	$body = '<p>' . esc_html__( 'A published review has been reported.', 'oria' ) . '</p>'
		. '<p><strong>' . esc_html( $name ) . '</strong></p>'
		. '<p>' . esc_html__( 'Reason:', 'oria' ) . ' ' . esc_html( $label ) . '</p>'
		. ( '' !== $detail ? '<p>' . esc_html( $detail ) . '</p>' : '' )
		/* translators: %d: how many times this review has been reported */
		. '<p>' . esc_html( sprintf( _n( 'Reported %d time in total.', 'Reported %d times in total.', $total, 'oria' ), $total ) ) . '</p>'
		. '<p>' . esc_html__( 'It is still published. Nothing is hidden by a report.', 'oria' ) . '</p>'
		. '<p><a href="' . esc_url( admin_url( 'comment.php?action=editcomment&c=' . $comment_id ) ) . '">' . esc_html__( 'Read it', 'oria' ) . '</a></p>';

	$shell = function_exists( '\Oria\Forms\Emails\shell' )
		? \Oria\Forms\Emails\shell( __( 'A review has been reported.', 'oria' ), $body, 'masthead' )
		: $body;

	$headers = function_exists( '\Oria\Forms\Emails\html_headers' )
		? (array) \Oria\Forms\Emails\html_headers()
		: array( 'Content-Type: text/html; charset=UTF-8' );

	/* translators: %s: practice name */
	wp_mail( $to, sprintf( __( 'Reported review: %s', 'oria' ), $name ), $shell, $headers );
}

function back( int $listing_id, string $state, string $detail = '' ): void {
	$url = $listing_id > 0 ? (string) get_permalink( $listing_id ) : home_url( '/' );
	$url = add_query_arg( array_filter( array( 'review' => $state, 'why' => $detail ) ), $url );
	wp_safe_redirect( $url . '#reviews' );
	exit;
}
