<?php
/**
 * Work in Wellness, phase 3: trust.
 *
 *   Verification (brief section 13). A practitioner uploads evidence for one
 *   badge at a time; it is stored privately and reviewed by a person in
 *   Work in Wellness -> Verification. Approving ticks the badge; either way
 *   the file is then deleted -- Oria keeps the decision, not the document.
 *
 *   Feedback (section 71). After a confirmed shift has ended, each side
 *   answers three yes/no questions. Nothing is shown publicly, ever.
 *
 *   Cancellation (section 73). A practitioner can cancel a confirmed shift.
 *   The shift reopens, the business is told, and practitioners who suit it
 *   and want urgent alerts are emailed if it starts within 48 hours.
 *
 *   Reliability (section 72). An internal 0-100 figure from feedback,
 *   no-shows and late cancellations. Used only to order matches and search;
 *   never displayed to anyone, never a filter that hides a person.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work\Trust;

use Oria\Core\Work;
use Oria\Core\Work\Store;
use Oria\Core\Work\Files;
use Oria\Core\Work\Notify;
use Oria\Core\Work\Forms;
use const Oria\Core\Work\PRO;
use const Oria\Core\Work\SHIFT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The three questions each side answers. */
const ABOUT_PRO      = array( 'Reliable', 'On time', 'Would hire again' );
const ABOUT_EMPLOYER = array( 'Paid as agreed', 'Clear brief on arrival', 'Would work there again' );

function bootstrap(): void {
	foreach ( array( 'verify_request', 'feedback', 'cancel' ) as $a ) {
		add_action( 'admin_post_oria_work_' . $a, __NAMESPACE__ . '\handle_' . $a );
		add_action( 'admin_post_nopriv_oria_work_' . $a, '\Oria\Core\Work\Forms\must_sign_in' );
	}
	add_action( 'admin_post_oria_work_verify_decide', __NAMESPACE__ . '\decide' );
	add_action( 'admin_post_oria_work_verify_file', __NAMESPACE__ . '\admin_file' );
	add_action( 'admin_menu', __NAMESPACE__ . '\menu', 15 );
}

/* ------------------------------------------------------------ verification */

function handle_verify_request(): void {
	Forms\check( 'oria_work_verify_request' );
	$uid  = get_current_user_id();
	$pro  = Work\profile_of( $uid );
	$back = \Oria\Core\MyOria\url( 'work' ) . '#verify';
	if ( ! $pro ) {
		Forms\back( \Oria\Core\MyOria\url( 'work-edit' ), 'profile_first' );
	}
	$type = Forms\pick( 'type', Work\VERIFICATIONS );
	if ( '' === $type ) {
		Forms\back( $back, 'error' );
	}
	foreach ( Store\verify_for( $pro ) as $r ) {
		if ( $r['type'] === $type && 'pending' === $r['status'] ) {
			Forms\back( $back, 'verify_pending' );
		}
	}
	$file = Files\store_evidence( 'evidence', $uid );
	if ( is_wp_error( $file ) || ! $file ) {
		Forms\back( $back, 'evidence' );
	}
	Store\add_verify( $pro, $type, (int) $file, Forms\txt( 'note', 300 ) );
	Notify\mail(
		(string) get_option( 'admin_email' ),
		/* translators: 1: badge, 2: name */
		sprintf( __( 'Verification to review: %1$s — %2$s', 'oria' ), Work\VERIFICATIONS[ $type ], get_the_title( $pro ) ),
		__( 'Evidence waiting for review', 'oria' ),
		array( __( 'Open the queue to view the file and approve or reject. The file is deleted once you decide.', 'oria' ) ),
		array( admin_url( 'admin.php?page=oria-work-verify' ), __( 'Open the verification queue', 'oria' ) )
	);
	Forms\back( $back, 'verify_sent' );
}

function menu(): void {
	$n = count( Store\verify_pending() );
	add_submenu_page( 'oria-work', __( 'Verification', 'oria' ), __( 'Verification', 'oria' ) . ( $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '' ), 'manage_options', 'oria-work-verify', __NAMESPACE__ . '\queue_page' );
}

function queue_page(): void {
	$rows = Store\verify_pending();
	echo '<div class="wrap"><h1>' . esc_html__( 'Practitioner verification', 'oria' ) . '</h1>';
	echo '<p>' . esc_html__( 'Open the evidence, check it is current and in the practitioner\'s name, then approve or reject. Either way the file is deleted — only the decision is kept.', 'oria' ) . '</p>';
	if ( ! $rows ) {
		echo '<p><em>' . esc_html__( 'Nothing waiting.', 'oria' ) . '</em></p></div>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Practitioner', 'oria' ) . '</th><th>' . esc_html__( 'Badge', 'oria' ) . '</th><th>' . esc_html__( 'Their note', 'oria' ) . '</th><th>' . esc_html__( 'Sent', 'oria' ) . '</th><th>' . esc_html__( 'Evidence', 'oria' ) . '</th><th>' . esc_html__( 'Decision', 'oria' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$id   = (int) $r['id'];
		$file = wp_nonce_url( admin_url( 'admin-post.php?action=oria_work_verify_file&row=' . $id ), 'oria_work_verify_file_' . $id );
		echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( (int) $r['profile_id'] ) ) . '">' . esc_html( get_the_title( (int) $r['profile_id'] ) ) . '</a></td>';
		echo '<td>' . esc_html( Work\VERIFICATIONS[ $r['type'] ] ?? $r['type'] ) . '</td><td>' . esc_html( (string) $r['note'] ) . '</td><td>' . esc_html( mysql2date( 'j M', (string) $r['created_at'] ) ) . '</td>';
		echo '<td><a class="button" href="' . esc_url( $file ) . '">' . esc_html__( 'Open file', 'oria' ) . '</a></td><td>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:6px;flex-wrap:wrap">';
		echo '<input type="hidden" name="action" value="oria_work_verify_decide"><input type="hidden" name="row" value="' . (int) $id . '">';
		wp_nonce_field( 'oria_work_verify_decide_' . $id );
		echo '<button class="button button-primary" name="do" value="approve">' . esc_html__( 'Approve', 'oria' ) . '</button>';
		echo '<input type="text" name="reason" placeholder="' . esc_attr__( 'Reason if rejecting', 'oria' ) . '" maxlength="300">';
		echo '<button class="button" name="do" value="reject">' . esc_html__( 'Reject', 'oria' ) . '</button></form></td></tr>';
	}
	echo '</tbody></table></div>';
}

/** Stream a piece of evidence to an admin (never a public URL). */
function admin_file(): void {
	$id = (int) ( $_GET['row'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	check_admin_referer( 'oria_work_verify_file_' . $id );
	$row = Store\verify_row( $id );
	if ( ! current_user_can( 'manage_options' ) || ! $row ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	$path = Files\path( (int) $row['file_id'] );
	if ( '' === $path || ! is_readable( $path ) ) {
		wp_die( esc_html__( 'That file is no longer available.', 'oria' ), '', array( 'response' => 404 ) );
	}
	nocache_headers();
	header( 'Content-Type: ' . ( wp_check_filetype( $path )['type'] ?: 'application/octet-stream' ) );
	header( 'Content-Disposition: inline; filename="' . sanitize_file_name( basename( $path ) ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

function decide(): void {
	$id = (int) ( $_POST['row'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	check_admin_referer( 'oria_work_verify_decide_' . $id );
	$row = Store\verify_row( $id );
	if ( ! current_user_can( 'manage_options' ) || ! $row || 'pending' !== $row['status'] ) {
		wp_safe_redirect( admin_url( 'admin.php?page=oria-work-verify' ) );
		exit;
	}
	$pro     = (int) $row['profile_id'];
	$approve = 'approve' === ( $_POST['do'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$reason  = sanitize_text_field( wp_unslash( (string) ( $_POST['reason'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $approve ) {
		$set = array_values( array_unique( array_merge( (array) Work\meta( $pro, 'verified', array() ), array( $row['type'] ) ) ) );
		Work\set( $pro, 'verified', $set );
	}
	// The decision is kept; the document is not.
	if ( (int) $row['file_id'] ) {
		wp_delete_attachment( (int) $row['file_id'], true );
	}
	Store\verify_decide( $id, $approve ? 'approved' : 'rejected', $reason );
	$badge = Work\VERIFICATIONS[ $row['type'] ] ?? $row['type'];
	Notify\mail(
		Notify\user_email( (int) get_post_field( 'post_author', $pro ) ),
		$approve
			/* translators: %s: badge */
			? sprintf( __( 'Verified: %s', 'oria' ), $badge )
			/* translators: %s: badge */
			: sprintf( __( 'We could not verify: %s', 'oria' ), $badge ),
		$approve ? __( 'Your badge is on', 'oria' ) : __( 'Verification update', 'oria' ),
		array_filter(
			array(
				$approve ? __( 'The badge now shows on your work profile. We have deleted the file you sent.', 'oria' ) : __( 'We could not verify this from the file you sent, and we have deleted it.', 'oria' ),
				! $approve && '' !== $reason ? sprintf( __( 'Reason: %s', 'oria' ), $reason ) : '',
				! $approve ? __( 'You are welcome to send a clearer or current copy from My Oria → Work.', 'oria' ) : '',
			)
		),
		array( \Oria\Core\MyOria\url( 'work' ) . '#verify', __( 'Open My Oria', 'oria' ) )
	);
	wp_safe_redirect( admin_url( 'admin.php?page=oria-work-verify' ) );
	exit;
}

/* ---------------------------------------------------------------- feedback */

/**
 * Confirmed shifts that have ended and still need this person's feedback.
 * $as: 'employer' (rows about practitioners) or 'pro' (rows about businesses).
 *
 * @return list<array> application rows
 */
function feedback_due( int $user_id, string $as ): array {
	global $wpdb;
	if ( 'employer' === $as ) {
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT a.* FROM ' . Store\apps() . " a JOIN {$wpdb->posts} p ON p.ID = a.post_id WHERE a.kind = 'shift' AND a.status = 'confirmed' AND p.post_author = %d", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$need = 'pro';
	} else {
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Store\apps() . " WHERE kind = 'shift' AND status = 'confirmed' AND user_id = %d", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$need = 'employer';
	}
	$out = array();
	foreach ( $rows as $r ) {
		$end = Work\shift_end_ts( (int) $r['post_id'] );
		// Ended, within the last 30 days, and not answered yet.
		if ( $end && $end < time() && $end > time() - 30 * DAY_IN_SECONDS && ! Store\feedback_for( (int) $r['id'], $need ) ) {
			$out[] = $r;
		}
	}
	return $out;
}

function handle_feedback(): void {
	Forms\check( 'oria_work_feedback' );
	$uid  = get_current_user_id();
	$app  = Store\app( (int) ( $_POST['app'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back = Forms\referer();
	if ( ! $app || 'shift' !== $app['kind'] || 'confirmed' !== $app['status'] || Work\shift_end_ts( (int) $app['post_id'] ) > time() ) {
		Forms\back( $back, 'error' );
	}
	$employer = (int) get_post_field( 'post_author', (int) $app['post_id'] );
	if ( $uid === $employer ) {
		$about = 'pro';
	} elseif ( $uid === (int) $app['user_id'] ) {
		$about = 'employer';
	} else {
		Forms\back( $back, 'error' );
	}
	$q = array_map( static fn( $k ) => 'yes' === ( $_POST[ $k ] ?? '' ), array( 'q1', 'q2', 'q3' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$no_show = 'pro' === $about && ! empty( $_POST['no_show'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $no_show ) {
		$q = array( false, false, (bool) $q[2] );
	}
	Store\add_feedback( (int) $app['id'], $uid, $about, $q, $no_show, Forms\area( 'note', 500 ) );
	if ( (int) $app['profile_id'] ) {
		forget( (int) $app['profile_id'] );
	}
	Forms\back( $back, 'feedback_saved' );
}

/* ------------------------------------------------------------ cancellation */

/**
 * The practitioner cancels a confirmed shift. The shift reopens if it had
 * closed as filled, the business hears straight away, and -- if it starts
 * within 48 hours -- suitable practitioners with urgent alerts on are told.
 * A cancellation inside 24 hours of the start counts as late.
 */
function handle_cancel(): void {
	Forms\check( 'oria_work_cancel' );
	$uid  = get_current_user_id();
	$app  = Store\app( (int) ( $_POST['app'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back = \Oria\Core\MyOria\url( 'work' );
	if ( ! $app || (int) $app['user_id'] !== $uid || 'shift' !== $app['kind'] || 'confirmed' !== $app['status'] ) {
		Forms\back( $back, 'error' );
	}
	$shift = (int) $app['post_id'];
	$start = Work\shift_start_ts( $shift );
	if ( $start && $start < time() ) {
		Forms\back( $back, 'too_late' );
	}
	Store\set_status( (int) $app['id'], 'cancelled' );
	$pro = (int) $app['profile_id'];
	if ( $pro ) {
		Work\set( $pro, 'cancellations', (int) Work\meta( $pro, 'cancellations', 0 ) + 1 );
		if ( $start && $start - time() < DAY_IN_SECONDS ) {
			Work\set( $pro, 'late_cancellations', (int) Work\meta( $pro, 'late_cancellations', 0 ) + 1 );
		}
	}
	if ( 'filled' === Work\meta( $shift, 'closed' ) ) {
		Work\set( $shift, 'closed', '' );
		Work\set( $shift, 'closed_at', '' );
	}
	$reason = Forms\area( 'reason', 300 );
	Notify\mail(
		Notify\user_email( (int) get_post_field( 'post_author', $shift ) ),
		/* translators: %s: shift */
		sprintf( __( 'Cancelled: %s', 'oria' ), get_the_title( $shift ) ),
		__( 'Your confirmed practitioner has cancelled', 'oria' ),
		array_filter(
			array(
				/* translators: 1: name, 2: when */
				sprintf( __( '%1$s can no longer cover %2$s. The shift is open again.', 'oria' ), Notify\first_name( $uid ) ?: __( 'Your practitioner', 'oria' ), Work\shift_when( $shift ) ),
				'' !== $reason ? sprintf( __( 'Their reason: %s', 'oria' ), $reason ) : '',
				$start && $start - time() < 2 * DAY_IN_SECONDS ? __( 'Because it starts soon, we are alerting other practitioners who suit it now.', 'oria' ) : '',
			)
		),
		array( add_query_arg( 'id', $shift, \Oria\Core\MyOria\url( 'recruit-applicants' ) ), __( 'See who is available', 'oria' ) )
	);
	// Replacement alerts: run again even if the first round already went.
	if ( $start && $start - time() < 2 * DAY_IN_SECONDS ) {
		Work\set( $shift, 'urgent_sent', '' );
		Work\set( $shift, 'urgency', 'today' === Work\urgency( $shift ) ? 'today' : '48h' );
		add_filter( 'oria_work_skip_pros', static fn( array $skip ) => array_merge( $skip, array( $pro ) ) );
		Notify\urgent_alerts( $shift );
	}
	Forms\back( $back, 'cancelled' );
}

/* ------------------------------------------------------------- reliability */

/**
 * Internal reliability, 0-100 (brief section 72). Starts at 70 for anyone
 * new -- unknown is not unreliable -- and moves with evidence: feedback
 * answers, no-shows (-15 each), late cancellations (-8), confirmed shifts
 * completed (+2, up to +20). Cached a day per profile.
 */
function reliability( int $pro ): int {
	$key = 'oria_wk_rel_' . $pro;
	$hit = get_transient( $key );
	if ( false !== $hit ) {
		return (int) $hit;
	}
	$uid   = (int) get_post_field( 'post_author', $pro );
	$rows  = $uid ? Store\feedback_about_pro( $uid ) : array();
	$score = 70.0;
	foreach ( $rows as $r ) {
		$score += ( (int) $r['q1'] + (int) $r['q2'] + (int) $r['q3'] ) * 2 - 3;
		$score -= (int) $r['no_show'] * 15;
	}
	$score -= 8 * (int) Work\meta( $pro, 'late_cancellations', 0 );
	$done   = 0;
	foreach ( $uid ? Store\apps_by_user( $uid ) : array() as $a ) {
		if ( 'shift' === $a['kind'] && 'confirmed' === $a['status'] && Work\shift_end_ts( (int) $a['post_id'] ) < time() ) {
			++$done;
		}
	}
	$score += min( 20, 2 * $done );
	$score  = (int) max( 0, min( 100, round( $score ) ) );
	set_transient( $key, $score, DAY_IN_SECONDS );
	return $score;
}

function forget( int $pro ): void {
	delete_transient( 'oria_wk_rel_' . $pro );
}
