<?php
/**
 * Work in Wellness: email and the clock.
 *
 * What goes where (brief section 77):
 *   employer     new applicant / "I'm available", offer accepted, job expiring, post approved
 *   practitioner status changes that mean something, shift offers, matching jobs, urgent shifts
 *   admin        a post waiting for approval, a report
 *
 * PRIVACY. An email about an applicant carries their first name, profession
 * and a link to the dashboard -- never their email, phone or CV. The
 * employer reads those signed in. An urgent-shift alert goes only to
 * practitioners who switched urgent alerts on and allow contact.
 *
 * The clock (hourly): close jobs past expiry and shifts past their end,
 * warn employers three days before a job expires, send immediate / daily /
 * weekly alert digests.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work\Notify;

use Oria\Core\Work;
use Oria\Core\Work\Store;
use const Oria\Core\Work\JOB;
use const Oria\Core\Work\SHIFT;
use const Oria\Core\Work\PROFESSION;
use const Oria\Core\Work\EMPLOYMENT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CRON = 'oria_work_hourly';

/** Most urgent-shift alerts sent for one shift. */
const URGENT_CAP = 40;

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\schedule' );
	add_action( CRON, __NAMESPACE__ . '\tick' );
	add_action( 'transition_post_status', __NAMESPACE__ . '\went_live', 10, 3 );
}

function schedule(): void {
	if ( ! wp_next_scheduled( CRON ) ) {
		wp_schedule_event( time() + 300, 'hourly', CRON );
	}
}

function unschedule(): void {
	wp_clear_scheduled_hook( CRON );
}

/* ------------------------------------------------------------------- mail */

/**
 * One branded email. $paras are plain sentences (escaped here); $cta is
 * [url, label]. Falls back to plain wp_mail HTML when the forms plugin's
 * shell is not there.
 */
function mail( string $to, string $subject, string $heading, array $paras, array $cta = array() ): void {
	if ( ! is_email( $to ) ) {
		return;
	}
	$html = '';
	foreach ( $paras as $p ) {
		$html .= '<p style="margin:0 0 14px">' . nl2br( esc_html( (string) $p ) ) . '</p>';
	}
	if ( $cta ) {
		$html .= '<p style="margin:22px 0"><a href="' . esc_url( (string) $cta[0] ) . '" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#0E3B38;color:#fff;text-decoration:none;font-weight:700">' . esc_html( (string) $cta[1] ) . '</a></p>';
	}
	try {
		if ( function_exists( '\Oria\Core\Leads\send' ) ) {
			\Oria\Core\Leads\send( $to, $subject, $heading, $html );
		} else {
			wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		}
	} catch ( \Throwable $e ) { // A failed email must never break the request that caused it.
		error_log( 'Oria work mail failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}

function user_email( int $user_id ): string {
	$u = get_userdata( $user_id );
	return $u ? (string) $u->user_email : '';
}

function first_name( int $user_id ): string {
	$u = get_userdata( $user_id );
	if ( ! $u ) {
		return '';
	}
	$n = trim( (string) $u->first_name ) ?: trim( (string) strtok( (string) $u->display_name, ' ' ) );
	return $n;
}

function dash( string $view, array $args = array() ): string {
	return add_query_arg( $args, \Oria\Core\MyOria\url( $view ) );
}

/* ----------------------------------------------------------------- admin */

function to_admin_pending( int $id ): void {
	$kind = JOB === get_post_type( $id ) ? __( 'job', 'oria' ) : __( 'shift', 'oria' );
	mail(
		(string) get_option( 'admin_email' ),
		/* translators: 1: job/shift, 2: title */
		sprintf( __( 'New %1$s to approve: %2$s', 'oria' ), $kind, get_the_title( $id ) ),
		__( 'Waiting for approval', 'oria' ),
		array(
			sprintf( '%s — %s, %s', get_the_title( $id ), Work\employer_name( $id ) ?: __( 'employer not named', 'oria' ), Work\place_label( $id ) ?: __( 'no suburb', 'oria' ) ),
			'1' === (string) Work\meta( $id, 'flagged' ) ? __( 'Flagged by the screening words (MLM / adult / unpaid trial). Please read it closely.', 'oria' ) : __( 'First post from this account. Approving it lets their later posts go live without waiting.', 'oria' ),
		),
		array( admin_url( 'post.php?post=' . $id . '&action=edit' ), __( 'Review and publish', 'oria' ) )
	);
}

function to_admin_report( int $id, string $reason ): void {
	mail(
		(string) get_option( 'admin_email' ),
		/* translators: %s: title */
		sprintf( __( 'Reported: %s', 'oria' ), get_the_title( $id ) ),
		__( 'A listing was reported', 'oria' ),
		array(
			/* translators: %s: reason */
			sprintf( __( 'Reason: %s', 'oria' ), Work\REPORT_REASONS[ $reason ] ?? $reason ),
			__( 'Nothing has been hidden automatically.', 'oria' ),
		),
		array( admin_url( 'admin.php?page=oria-work-reports' ), __( 'Open the report queue', 'oria' ) )
	);
}

/* ------------------------------------------------------------- lifecycle */

/**
 * Pending -> live: tell the employer, trust the account, and run the
 * immediate alerts (and, for a shift, the urgent-cover alerts).
 */
function went_live( string $new, string $old, \WP_Post $post ): void {
	if ( 'publish' !== $new || 'publish' === $old || ! in_array( $post->post_type, array( JOB, SHIFT ), true ) ) {
		return;
	}
	$author = (int) $post->post_author;
	if ( 'pending' === $old && ! user_can( $author, 'manage_options' ) ) {
		update_user_meta( $author, \Oria\Core\Work\Forms\TRUSTED, 1 );
		mail(
			user_email( $author ),
			/* translators: %s: title */
			sprintf( __( 'Your listing is live: %s', 'oria' ), $post->post_title ),
			__( 'You are live on Oria Haven', 'oria' ),
			array(
				/* translators: %s: title */
				sprintf( __( '"%s" has been approved and is now live.', 'oria' ), $post->post_title ),
				__( 'Your next posts will go live straight away.', 'oria' ),
			),
			array( get_permalink( $post ), __( 'See it live', 'oria' ) )
		);
	}
	// Hook runs mid-save: terms and meta land after it, so alerts wait for shutdown.
	add_action(
		'shutdown',
		static function () use ( $post ): void {
			clean_post_cache( $post->ID );
			immediate_alerts( $post->ID );
			if ( SHIFT === $post->post_type ) {
				urgent_alerts( $post->ID );
			}
		}
	);
}

/** A new application or "I'm available": the employer hears, without the applicant's contact details. */
function new_application( int $app_id ): void {
	$app = Store\app( $app_id );
	if ( ! $app ) {
		return;
	}
	$post  = (int) $app['post_id'];
	$pro   = (int) $app['profile_id'];
	$who   = first_name( (int) $app['user_id'] ) ?: __( 'Someone', 'oria' );
	$prof  = $pro ? Work\profession_name( $pro ) : '';
	$shift = 'shift' === $app['kind'];
	$to    = user_email( (int) get_post_field( 'post_author', $post ) );
	mail(
		$to,
		$shift
			/* translators: 1: name, 2: shift */
			? sprintf( __( '%1$s is available for "%2$s"', 'oria' ), $who, get_the_title( $post ) )
			/* translators: 1: name, 2: job */
			: sprintf( __( 'New applicant for "%2$s": %1$s', 'oria' ), $who, get_the_title( $post ) ),
		$shift ? __( 'Someone can cover your shift', 'oria' ) : __( 'You have a new applicant', 'oria' ),
		array_filter(
			array(
				trim( $who . ( $prof ? ' — ' . $prof : '' ) ),
				'' !== $app['rate'] ? sprintf( __( 'Rate: %s', 'oria' ), $app['rate'] ) : '',
				$shift ? __( 'Open your dashboard to see their profile and offer them the shift.', 'oria' ) : __( 'Open your dashboard to read their application and CV.', 'oria' ),
			)
		),
		array( dash( 'recruit-applicants', array( 'id' => $post ) ), __( 'View applicant', 'oria' ) )
	);
}

/** Tell the applicant when a status means something to them. */
function status_changed( int $app_id, string $to ): void {
	$app = Store\app( $app_id );
	if ( ! $app ) {
		return;
	}
	$post  = (int) $app['post_id'];
	$title = get_the_title( $post );
	$biz   = Work\employer_name( $post ) ?: __( 'The employer', 'oria' );
	$lines = array(
		/* translators: 1: business, 2: title */
		'viewed'      => sprintf( __( '%1$s has viewed your application for "%2$s".', 'oria' ), $biz, $title ),
		/* translators: 1: business, 2: title */
		'shortlisted' => sprintf( __( 'Good news — %1$s has shortlisted you for "%2$s".', 'oria' ), $biz, $title ),
		/* translators: 1: business, 2: title */
		'interview'   => sprintf( __( '%1$s would like to interview you for "%2$s". They will be in touch with the details.', 'oria' ), $biz, $title ),
		/* translators: 1: business, 2: title */
		'offered'     => 'shift' === $app['kind'] ? sprintf( __( '%1$s has offered you the shift "%2$s". Accept it from your dashboard to confirm.', 'oria' ), $biz, $title ) : sprintf( __( '%1$s has made you an offer for "%2$s".', 'oria' ), $biz, $title ),
		/* translators: 1: business, 2: title */
		'hired'       => sprintf( __( 'Congratulations — %1$s has marked you as hired for "%2$s".', 'oria' ), $biz, $title ),
		/* translators: 1: business, 2: title */
		'rejected'    => sprintf( __( '%1$s has decided not to go ahead with your application for "%2$s". Thank you for applying — there are more roles waiting.', 'oria' ), $biz, $title ),
	);
	if ( ! isset( $lines[ $to ] ) ) {
		return;
	}
	mail(
		user_email( (int) $app['user_id'] ),
		/* translators: %s: title */
		'offered' === $to && 'shift' === $app['kind'] ? sprintf( __( 'Shift offer: %s', 'oria' ), $title ) : sprintf( __( 'Update on "%s"', 'oria' ), $title ),
		'offered' === $to && 'shift' === $app['kind'] ? __( 'You have a shift offer', 'oria' ) : __( 'Application update', 'oria' ),
		array( $lines[ $to ] ),
		array( dash( 'work' ), 'offered' === $to && 'shift' === $app['kind'] ? __( 'Accept the shift', 'oria' ) : __( 'Open My Oria', 'oria' ) )
	);
}

/** The practitioner answered an offer: tell the employer. */
function answered( int $app_id, bool $yes ): void {
	$app = Store\app( $app_id );
	if ( ! $app ) {
		return;
	}
	$post = (int) $app['post_id'];
	$who  = first_name( (int) $app['user_id'] ) ?: __( 'The practitioner', 'oria' );
	mail(
		user_email( (int) get_post_field( 'post_author', $post ) ),
		$yes
			/* translators: 1: name, 2: title */
			? sprintf( __( '%1$s confirmed "%2$s"', 'oria' ), $who, get_the_title( $post ) )
			/* translators: 1: name, 2: title */
			: sprintf( __( '%1$s can no longer take "%2$s"', 'oria' ), $who, get_the_title( $post ) ),
		$yes ? __( 'Confirmed', 'oria' ) : __( 'Offer declined', 'oria' ),
		array( $yes ? __( 'It is confirmed. Their contact details are in your dashboard.', 'oria' ) : __( 'They have declined. Other practitioners who said they were available are still in your dashboard.', 'oria' ) ),
		array( dash( 'recruit-applicants', array( 'id' => $post ) ), __( 'Open dashboard', 'oria' ) )
	);
}

/* ----------------------------------------------------------------- alerts */

/** Whether a job/shift matches one alert row. */
function matches( array $alert, int $post ): bool {
	$type = get_post_type( $post );
	if ( ( 'shift' === $alert['kind'] ) !== ( SHIFT === $type ) ) {
		return false;
	}
	if ( (int) $alert['profession'] && ! has_term( (int) $alert['profession'], PROFESSION, $post ) ) {
		$t = get_term( (int) $alert['profession'], PROFESSION );
		// A group alert ("Movement & Fitness") matches any profession under it.
		$kids = $t && ! $t->parent ? get_term_children( (int) $t->term_id, PROFESSION ) : array();
		if ( ! $kids || ! has_term( $kids, PROFESSION, $post ) ) {
			return false;
		}
	}
	if ( (int) $alert['area'] && ! has_term( (int) $alert['area'], 'area', $post ) ) {
		return false;
	}
	if ( '' !== $alert['employment'] && ! has_term( $alert['employment'], EMPLOYMENT, $post ) ) {
		return false;
	}
	if ( '' !== $alert['keyword'] && false === stripos( get_the_title( $post ) . ' ' . get_post_field( 'post_content', $post ), (string) $alert['keyword'] ) ) {
		return false;
	}
	return Work\is_open( $post );
}

/** One line per job for an email. */
function line( int $post ): string {
	$bits = array_filter( array( Work\employer_name( $post ), Work\place_label( $post ), SHIFT === get_post_type( $post ) ? Work\shift_when( $post ) : '', Work\pay_label( $post ) ) );
	return get_the_title( $post ) . ( $bits ? ' — ' . implode( ' · ', $bits ) : '' ) . "\n" . get_permalink( $post );
}

function immediate_alerts( int $post ): void {
	foreach ( Store\alerts_due( 'immediate' ) as $a ) {
		if ( matches( $a, $post ) ) {
			send_digest( $a, array( $post ) );
		}
	}
}

function send_digest( array $alert, array $posts ): void {
	if ( ! $posts ) {
		return;
	}
	$n = count( $posts );
	mail(
		user_email( (int) $alert['user_id'] ),
		SHIFT === get_post_type( $posts[0] )
			/* translators: %d: count */
			? sprintf( _n( '%d new shift matches your alert', '%d new shifts match your alert', $n, 'oria' ), $n )
			/* translators: %d: count */
			: sprintf( _n( '%d new job matches your alert', '%d new jobs match your alert', $n, 'oria' ), $n ),
		__( 'New for you on Oria Haven', 'oria' ),
		array_merge(
			array_map( __NAMESPACE__ . '\line', array_slice( $posts, 0, 12 ) ),
			array( __( 'Stop this alert:', 'oria' ) . ' ' . home_url( '/jobs/alerts/off/' . $alert['token'] . '/' ) )
		),
		array( get_permalink( $posts[0] ), SHIFT === get_post_type( $posts[0] ) ? __( "I'm available", 'oria' ) : __( 'View job', 'oria' ) )
	);
	Store\alert_sent( (int) $alert['id'] );
}

/** Daily and weekly digests: everything published since the alert last went out. */
function digests( string $frequency ): void {
	$window = 'weekly' === $frequency ? WEEK_IN_SECONDS : DAY_IN_SECONDS;
	$fresh  = get_posts(
		array(
			'post_type'   => array( JOB, SHIFT ),
			'post_status' => 'publish',
			'numberposts' => 300,
			'fields'      => 'ids',
			'date_query'  => array( array( 'after' => gmdate( 'Y-m-d H:i:s', time() - $window ), 'column' => 'post_date_gmt' ) ),
		)
	);
	foreach ( Store\alerts_due( $frequency ) as $a ) {
		// Both sides are site-local 'Y-m-d H:i:s', so a string compare is a time compare.
		$since = (string) ( $a['last_sent'] ?: $a['created_at'] );
		$hit   = array();
		foreach ( $fresh as $p ) {
			if ( (string) get_post_field( 'post_date', (int) $p ) > $since && matches( $a, (int) $p ) ) {
				$hit[] = (int) $p;
			}
		}
		send_digest( $a, $hit );
	}
}

/**
 * Oria Cover (brief section 100): a shift needed within 48 hours goes to
 * the practitioners who suit it and switched urgent alerts on.
 */
function urgent_alerts( int $shift ): void {
	if ( ! in_array( Work\urgency( $shift ), array( 'today', '48h' ), true ) || Work\meta( $shift, 'urgent_sent' ) ) {
		return;
	}
	Work\set( $shift, 'urgent_sent', time() );
	$sent = 0;
	foreach ( Work\pros_for_shift( $shift ) as $pro ) {
		if ( ! Work\meta( $pro, 'urgent_alerts' ) || 'allow' !== (string) Work\meta( $pro, 'contact_pref', 'allow' ) ) {
			continue;
		}
		$uid = (int) get_post_field( 'post_author', $pro );
		mail(
			user_email( $uid ),
			/* translators: %s: shift title */
			sprintf( __( 'URGENT: %s', 'oria' ), get_the_title( $shift ) ),
			__( 'Cover needed', 'oria' ),
			array( line( $shift ), __( 'You are getting this because you switched on urgent shift alerts. Turn them off in My Oria → Work.', 'oria' ) ),
			array( get_permalink( $shift ) . '#respond', __( "I'm available", 'oria' ) )
		);
		if ( ++$sent >= URGENT_CAP ) {
			break;
		}
	}
	Work\set( $shift, 'urgent_count', $sent );
}

/* ------------------------------------------------------------------ clock */

function tick(): void {
	// Close what has run out.
	foreach ( get_posts( array( 'post_type' => array( JOB, SHIFT ), 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'meta_query' => array( array( 'key' => Work\key( 'closed' ), 'compare' => 'NOT EXISTS' ) ) ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$id  = (int) $id;
		$why = Work\closed_reason( $id );
		if ( '' !== $why ) {
			Work\set( $id, 'closed', $why );
			Work\set( $id, 'closed_at', time() );
			continue;
		}
		// Three days' warning before a job expires, once.
		$exp = (int) Work\meta( $id, 'expires', 0 );
		if ( JOB === get_post_type( $id ) && $exp && $exp - time() < 3 * DAY_IN_SECONDS && ! Work\meta( $id, 'warned' ) ) {
			Work\set( $id, 'warned', 1 );
			mail(
				user_email( (int) get_post_field( 'post_author', $id ) ),
				/* translators: %s: title */
				sprintf( __( '"%s" closes in 3 days', 'oria' ), get_the_title( $id ) ),
				__( 'Your job is about to close', 'oria' ),
				array( __( 'If you are still hiring, open your dashboard and keep it open for another 30 days.', 'oria' ) ),
				array( dash( 'recruit' ), __( 'Open dashboard', 'oria' ) )
			);
		}
		// A shift whose clock just crossed into urgent gets its alerts now.
		if ( SHIFT === get_post_type( $id ) ) {
			urgent_alerts( $id );
		}
	}

	$now = (int) wp_date( 'G' );
	if ( 7 === $now && get_option( 'oria_work_daily' ) !== wp_date( 'Y-m-d' ) ) {
		update_option( 'oria_work_daily', wp_date( 'Y-m-d' ), false );
		digests( 'daily' );
		if ( 1 === (int) wp_date( 'N' ) ) {
			digests( 'weekly' );
		}
	}
}
