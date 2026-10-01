<?php
/**
 * Work in Wellness: every form that writes.
 *
 * All posts go through admin-post.php. Each handler: checks the nonce, checks
 * the person is signed in (except the public report form, which gets the
 * honeypot/time/throttle walls instead), checks they own what they are
 * changing, sanitises every field against a known list, and bounces back
 * with a short code the page turns into a sentence.
 *
 * MODERATION (brief section 56). An account's first job or shift goes to
 * Pending and the admin is emailed. Approving it marks the account trusted,
 * and a trusted employer's later posts go live straight away. Untick
 * "trusted" on the user to put them back in the queue.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work\Forms;

use Oria\Core\Work;
use Oria\Core\Work\Store;
use Oria\Core\Work\Files;
use Oria\Core\Work\Notify;
use const Oria\Core\Work\JOB;
use const Oria\Core\Work\SHIFT;
use const Oria\Core\Work\PRO;
use const Oria\Core\Work\PROFESSION;
use const Oria\Core\Work\SKILL;
use const Oria\Core\Work\EMPLOYMENT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TRUSTED = '_oria_work_trusted';
const SAVED   = '_oria_work_saved';

function bootstrap(): void {
	foreach ( array( 'profile', 'post', 'apply', 'available', 'status', 'accept', 'withdraw', 'alert', 'save', 'close', 'claim', 'avail', 'talent', 'invite', 'feature' ) as $a ) {
		add_action( 'admin_post_oria_work_' . $a, __NAMESPACE__ . '\handle_' . $a );
		add_action( 'admin_post_nopriv_oria_work_' . $a, __NAMESPACE__ . '\must_sign_in' );
	}
	add_action( 'admin_post_oria_work_report', __NAMESPACE__ . '\handle_report' );
	add_action( 'admin_post_nopriv_oria_work_report', __NAMESPACE__ . '\handle_report' );
}

/* ---------------------------------------------------------------- helpers */

function must_sign_in(): void {
	$back = wp_get_referer() ?: home_url( '/jobs/' );
	wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( $back ), \Oria\Core\MyOria\url( 'login' ) ) );
	exit;
}

/** Back to $url with ?wk=$code. Never returns. */
function back( string $url, string $code, string $anchor = '' ): void {
	$url = remove_query_arg( array( 'wk' ), $url );
	wp_safe_redirect( add_query_arg( 'wk', $code, $url ) . $anchor );
	exit;
}

function referer(): string {
	return (string) ( wp_get_referer() ?: home_url( '/jobs/' ) );
}

function check( string $action ): void {
	if ( ! wp_verify_nonce( (string) ( $_POST['_wk'] ?? '' ), $action ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		back( referer(), 'expired' );
	}
}

/** @return string Trimmed, sanitised single-line text. */
function txt( string $k, int $max = 200 ): string {
	return mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST[ $k ] ?? '' ) ) ), 0, $max ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

function area( string $k, int $max = 4000 ): string {
	return mb_substr( sanitize_textarea_field( wp_unslash( (string) ( $_POST[ $k ] ?? '' ) ) ), 0, $max ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

function num( string $k ): float {
	$v = preg_replace( '/[^0-9.]/', '', (string) ( $_POST[ $k ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	return '' === $v ? 0.0 : max( 0.0, min( 1000000.0, (float) $v ) );
}

/** One value that must be a key of $allowed. */
function pick( string $k, array $allowed, string $default = '' ): string {
	$v = sanitize_key( (string) ( $_POST[ $k ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	return isset( $allowed[ $v ] ) ? $v : $default;
}

/** Several values, each a key of $allowed. */
function picks( string $k, array $allowed ): array {
	$in = (array) ( $_POST[ $k ] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	return array_values( array_intersect( array_map( 'sanitize_key', $in ), array_keys( $allowed ) ) );
}

/** Term ids from a posted list, restricted to real terms of $tax. */
function term_ids( string $k, string $tax ): array {
	$ids = array_filter( array_map( 'intval', (array) ( $_POST[ $k ] ?? array() ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	return array_values( array_filter( $ids, static fn( int $id ) => (bool) get_term( $id, $tax ) ) );
}

function valid_date( string $d ): string {
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && strtotime( $d ) ? $d : '';
}

function valid_time( string $t ): string {
	return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t ) ? $t : '';
}

function trusted( int $user_id ): bool {
	return user_can( $user_id, 'manage_options' ) || (bool) get_user_meta( $user_id, TRUSTED, true );
}

/* ------------------------------------------------------------ work profile */

function handle_profile(): void {
	check( 'oria_work_profile' );
	$uid  = get_current_user_id();
	$back = \Oria\Core\MyOria\url( 'work-edit' );
	$pro  = Work\profile_of( $uid );

	$name = txt( 'name', 80 );
	if ( '' === $name ) {
		back( $back, 'need_name' );
	}
	$data = array(
		'post_type'    => PRO,
		'post_title'   => $name,
		'post_excerpt' => area( 'short_bio', 300 ),
		'post_content' => wp_kses_post( wpautop( area( 'bio', 5000 ) ) ),
		'post_author'  => $uid,
		'post_status'  => 'publish',
	);
	if ( $pro ) {
		$data['ID'] = $pro;
		// An admin who unpublished a profile keeps it unpublished.
		$data['post_status'] = get_post_status( $pro );
		$pro = (int) wp_update_post( $data, true );
	} else {
		$pro = (int) wp_insert_post( $data, true );
	}
	if ( ! $pro ) {
		back( $back, 'error' );
	}

	$primary = (int) ( $_POST['profession'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$profs   = array_unique( array_filter( array_merge( get_term( $primary, PROFESSION ) ? array( $primary ) : array(), term_ids( 'professions', PROFESSION ) ) ) );
	wp_set_object_terms( $pro, $profs, PROFESSION );
	Work\set( $pro, 'primary', get_term( $primary, PROFESSION ) ? $primary : 0 );
	wp_set_object_terms( $pro, term_ids( 'skills', SKILL ), SKILL );

	$suburb = sanitize_title( (string) ( $_POST['suburb'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' !== $suburb ) {
		Work\set_suburb( $pro, $suburb );
	}

	Work\set( $pro, 'title', txt( 'title', 80 ) );
	Work\set( $pro, 'years', (int) num( 'years' ) );
	Work\set( $pro, 'quals', area( 'quals', 1500 ) );
	Work\set( $pro, 'services', area( 'services', 1000 ) );
	Work\set( $pro, 'radius', max( 1, min( 200, (int) num( 'radius' ) ?: 15 ) ) );
	Work\set( $pro, 'available_for', picks( 'available_for', Work\AVAILABLE_FOR ) );
	Work\set( $pro, 'availability', picks( 'availability', Work\AVAILABILITY ) );
	Work\set( $pro, 'employment_pref', picks( 'employment_pref', Work\employment_types() ) );
	Work\set( $pro, 'rate', txt( 'rate', 60 ) );
	Work\set( $pro, 'visibility', pick( 'visibility', array( 'public' => 1, 'employers' => 1, 'hidden' => 1 ), 'public' ) );
	Work\set( $pro, 'contact_pref', pick( 'contact_pref', array( 'allow' => 1, 'applications' => 1, 'none' => 1 ), 'allow' ) );
	Work\set( $pro, 'urgent_alerts', empty( $_POST['urgent_alerts'] ) ? '' : '1' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	Work\set( $pro, 'website', esc_url_raw( txt( 'website', 200 ) ) );
	Work\set( $pro, 'instagram', esc_url_raw( txt( 'instagram', 200 ) ) );
	Work\set( $pro, 'linkedin', esc_url_raw( txt( 'linkedin', 200 ) ) );
	// Private: never printed on a public page (brief section 90).
	Work\set( $pro, 'abn', preg_replace( '/[^0-9 ]/', '', txt( 'abn', 20 ) ) );
	Work\set( $pro, 'insurance', empty( $_POST['insurance'] ) ? '' : '1' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	Work\set( $pro, 'first_aid', empty( $_POST['first_aid'] ) ? '' : '1' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	Work\set( $pro, 'cpr', empty( $_POST['cpr'] ) ? '' : '1' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	Work\set( $pro, 'wwcc', empty( $_POST['wwcc'] ) ? '' : '1' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	Work\set( $pro, 'registrations', txt( 'registrations', 300 ) );
	$listing = (int) ( $_POST['works_at'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	Work\set( $pro, 'works_at', ( $listing && 'listing' === get_post_type( $listing ) && 'publish' === get_post_status( $listing ) ) ? $listing : 0 );

	$photo = Files\store_photo( 'photo', $pro );
	if ( is_wp_error( $photo ) ) {
		back( $back, 'photo' );
	}
	if ( $photo ) {
		set_post_thumbnail( $pro, $photo );
	}
	$cv = Files\store_cv( 'cv', $uid );
	if ( is_wp_error( $cv ) ) {
		back( $back, 'cv' );
	}
	if ( $cv ) {
		$old = (int) Work\meta( $pro, 'cv', 0 );
		Work\set( $pro, 'cv', $cv );
		// The old CV stays if an application still points at it.
		if ( $old && ! cv_in_use( $old ) ) {
			wp_delete_attachment( $old, true );
		}
	}
	// Back to the job or shift they were answering, if they came from one.
	$for = (int) ( $_POST['for'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $for && in_array( get_post_type( $for ), array( JOB, SHIFT ), true ) ) {
		back( (string) get_permalink( $for ), 'profile_saved', SHIFT === get_post_type( $for ) ? '#respond' : '#apply' );
	}
	back( \Oria\Core\MyOria\url( 'work' ), 'profile_saved' );
}

function cv_in_use( int $cv ): bool {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . Store\apps() . ' WHERE cv_id = %d LIMIT 1', $cv ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/* ------------------------------------------------------- post a job/shift */

function handle_post(): void {
	check( 'oria_work_post' );
	$uid  = get_current_user_id();
	$kind = 'shift' === ( $_POST['kind'] ?? '' ) ? SHIFT : JOB; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$id   = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$form = add_query_arg( array( 'type' => SHIFT === $kind ? 'shift' : 'job' ), \Oria\Core\MyOria\url( 'recruit-post' ) );

	if ( $id && ( get_post_type( $id ) !== $kind || ! Work\owns( $id, $uid ) ) ) {
		back( \Oria\Core\MyOria\url( 'recruit' ), 'error' );
	}
	$draft = 'draft' === ( $_POST['do'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$title = txt( 'title', 120 );
	$desc  = area( 'description', 8000 );
	$prof  = (int) ( $_POST['profession'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$sub   = sanitize_title( (string) ( $_POST['suburb'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( '' === $title ) {
		back( $id ? add_query_arg( 'id', $id, $form ) : $form, 'need_title' );
	}
	if ( ! $draft ) {
		$missing = ( ! get_term( $prof, PROFESSION ) ) || '' === $sub || strlen( $desc ) < 80;
		if ( SHIFT === $kind ) {
			$missing = $missing || '' === valid_date( txt( 'date', 10 ) ) || '' === valid_time( txt( 'start', 5 ) );
		}
		if ( $missing ) {
			back( $id ? add_query_arg( 'id', $id, $form ) : $form, 'need_more' );
		}
	}

	// Screening for the listings the brief prohibits (section 56): held for a person to look at, never auto-rejected.
	$flag = (bool) preg_match( '/\b(mlm|network marketing|unlimited earning|be your own boss|recruit(ing)? others|escort|adult services|sensual|unpaid trial)\b/i', $title . ' ' . $desc );

	$was    = $id ? get_post_status( $id ) : '';
	$status = $draft ? 'draft' : ( ( 'publish' === $was || ( trusted( $uid ) && ! $flag ) ) ? 'publish' : 'pending' );
	$data   = array(
		'post_type'    => $kind,
		'post_title'   => $title,
		'post_content' => wp_kses_post( wpautop( $desc ) ),
		'post_excerpt' => JOB === $kind ? area( 'summary', 300 ) : '',
		'post_status'  => $status,
		'post_author'  => $id ? (int) get_post_field( 'post_author', $id ) : $uid,
	);
	if ( $id ) {
		$data['ID'] = $id;
		$id         = (int) wp_update_post( $data );
	} else {
		$id = (int) wp_insert_post( $data );
	}
	if ( ! $id ) {
		back( $form, 'error' );
	}

	if ( get_term( $prof, PROFESSION ) ) {
		wp_set_object_terms( $id, array( $prof ), PROFESSION );
	}
	wp_set_object_terms( $id, term_ids( 'skills', SKILL ), SKILL );
	if ( '' !== $sub ) {
		Work\set_suburb( $id, $sub );
	}

	// The employer: the account's own listing when it has one, else the name typed.
	$listing = function_exists( '\Oria\Core\ListingEditor\listing_for' ) ? (int) \Oria\Core\ListingEditor\listing_for( $uid ) : 0;
	if ( ! $listing && ! Work\meta( $id, 'listing' ) ) {
		Work\set( $id, 'employer', txt( 'employer', 120 ) );
	} elseif ( $listing ) {
		Work\set( $id, 'listing', $listing );
	}
	Work\set( $id, 'address', txt( 'address', 160 ) );
	Work\set( $id, 'experience', pick( 'experience', Work\EXPERIENCE, 'any' ) );
	Work\set( $id, 'quals', area( 'quals', 1000 ) );
	Work\set( $id, 'flagged', $flag ? '1' : '' );

	if ( JOB === $kind ) {
		$type = sanitize_title( (string) ( $_POST['employment'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( Work\employment_types()[ $type ] ) ) {
			wp_set_object_terms( $id, array( $type ), EMPLOYMENT );
		}
		Work\set( $id, 'arrangement', pick( 'arrangement', Work\ARRANGEMENTS, 'onsite' ) );
		Work\set( $id, 'pay_min', num( 'pay_min' ) ?: '' );
		Work\set( $id, 'pay_max', num( 'pay_max' ) ?: '' );
		Work\set( $id, 'pay_unit', pick( 'pay_unit', Work\PAY_UNITS, '' ) );
		Work\set( $id, 'pay_rank', Work\pay_rank( $id ) ?: '' );
		Work\set( $id, 'weekend', empty( $_POST['weekend'] ) ? '' : '1' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		Work\set( $id, 'evening', empty( $_POST['evening'] ) ? '' : '1' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		Work\set( $id, 'benefits', picks( 'benefits', Work\BENEFITS ) );
		$method = pick( 'apply_method', array( 'oria' => 1, 'url' => 1, 'email' => 1 ), 'oria' );
		$url    = esc_url_raw( txt( 'apply_url', 300 ), array( 'https', 'http' ) );
		$email  = sanitize_email( txt( 'apply_email', 120 ) );
		if ( 'url' === $method && ! $url ) {
			$method = 'oria';
		}
		if ( 'email' === $method && ! is_email( $email ) ) {
			$method = 'oria';
		}
		Work\set( $id, 'apply_method', $method );
		Work\set( $id, 'apply_url', 'url' === $method ? $url : '' );
		Work\set( $id, 'apply_email', 'email' === $method ? $email : '' );
		$days = (int) num( 'days' );
		if ( ! Work\meta( $id, 'expires' ) || 'publish' !== $was ) {
			Work\set( $id, 'expires', time() + DAY_IN_SECONDS * ( in_array( $days, array( 14, 30, 45, 60 ), true ) ? $days : Work\JOB_DAYS ) );
		}
	} else {
		Work\set( $id, 'date', valid_date( txt( 'date', 10 ) ) );
		Work\set( $id, 'start', valid_time( txt( 'start', 5 ) ) );
		Work\set( $id, 'end', valid_time( txt( 'end', 5 ) ) );
		Work\set( $id, 'pay_amount', num( 'pay_amount' ) ?: '' );
		Work\set( $id, 'pay_unit', pick( 'pay_unit', array( 'hour' => 1, 'shift' => 1, 'class' => 1, 'client' => 1, 'commission' => 1, 'negotiable' => 1 ), 'hour' ) );
		Work\set( $id, 'urgency', pick( 'urgency', Work\URGENCY, 'normal' ) );
		Work\set( $id, 'bring', area( 'bring', 500 ) );
		Work\set( $id, 'contact', pick( 'contact', array( 'oria' => 1, 'phone' => 1, 'email' => 1 ), 'oria' ) );
		Work\set( $id, 'workers', max( 1, min( 20, (int) num( 'workers' ) ?: 1 ) ) );
	}

	if ( 'pending' === $status && 'pending' !== $was ) {
		Notify\to_admin_pending( $id );
	}
	if ( $draft ) {
		back( add_query_arg( 'id', $id, $form ), 'draft_saved' );
	}
	back( \Oria\Core\MyOria\url( 'recruit' ), 'publish' === $status ? 'posted_live' : 'posted_pending' );
}

/* --------------------------------------------------------------- applying */

function handle_apply(): void {
	check( 'oria_work_apply' );
	$uid  = get_current_user_id();
	$job  = (int) ( $_POST['job'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back = get_permalink( $job ) ?: home_url( '/jobs/' );
	if ( JOB !== get_post_type( $job ) || ! Work\is_open( $job ) || 'oria' !== Work\meta( $job, 'apply_method', 'oria' ) ) {
		back( $back, 'closed' );
	}
	if ( Work\owns( $job, $uid ) && ! current_user_can( 'manage_options' ) ) {
		back( $back, 'own' );
	}
	if ( Store\app_for( 'job', $job, $uid ) ) {
		back( $back, 'already', '#apply' );
	}
	$pro = Work\profile_of( $uid );
	$cv  = Files\store_cv( 'cv', $uid );
	if ( is_wp_error( $cv ) ) {
		back( $back, 'cv', '#apply' );
	}
	if ( ! $cv && $pro && ! empty( $_POST['use_profile_cv'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$cv = (int) Work\meta( $pro, 'cv', 0 );
	}
	$app = Store\add_app(
		array(
			'kind'         => 'job',
			'post_id'      => $job,
			'user_id'      => $uid,
			'profile_id'   => $pro,
			'message'      => area( 'message', 2000 ),
			'rate'         => txt( 'rate', 80 ),
			'availability' => txt( 'availability', 160 ),
			'cv_id'        => (int) $cv,
			'source'       => $pro && ! empty( $_POST['one_click'] ) ? 'profile' : 'form', // phpcs:ignore WordPress.Security.NonceVerification.Missing
		)
	);
	if ( ! $app ) {
		back( $back, 'already', '#apply' );
	}
	Notify\new_application( $app );
	back( $back, 'applied', '#apply' );
}

function handle_available(): void {
	check( 'oria_work_available' );
	$uid   = get_current_user_id();
	$shift = (int) ( $_POST['shift'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back  = get_permalink( $shift ) ?: home_url( '/shifts/' );
	if ( SHIFT !== get_post_type( $shift ) || ! Work\is_open( $shift ) ) {
		back( $back, 'closed' );
	}
	if ( Work\owns( $shift, $uid ) && ! current_user_can( 'manage_options' ) ) {
		back( $back, 'own' );
	}
	$pro = Work\profile_of( $uid );
	if ( ! $pro ) {
		// A shift response IS the profile: the studio decides on it.
		back( add_query_arg( 'for', $shift, \Oria\Core\MyOria\url( 'work-edit' ) ), 'profile_first' );
	}
	$app = Store\add_app(
		array(
			'kind'       => 'shift',
			'post_id'    => $shift,
			'user_id'    => $uid,
			'profile_id' => $pro,
			'message'    => area( 'message', 800 ),
			'rate'       => txt( 'rate', 80 ) ?: (string) Work\meta( $pro, 'rate' ),
			'source'     => 'available',
		)
	);
	if ( ! $app ) {
		back( $back, 'already', '#respond' );
	}
	Notify\new_application( $app );
	back( $back, 'available_sent', '#respond' );
}

/** Employer moves an application along (including "Offer shift"). */
function handle_status(): void {
	check( 'oria_work_status' );
	$uid    = get_current_user_id();
	$app_id = (int) ( $_POST['app'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$app    = Store\app( $app_id );
	$back   = referer();
	if ( ! $app || ! Work\owns( (int) $app['post_id'], $uid ) ) {
		back( $back, 'error' );
	}
	$to      = pick( 'to', Work\STATUSES );
	$allowed = 'shift' === $app['kind'] ? array( 'viewed', 'offered', 'rejected' ) : array( 'viewed', 'shortlisted', 'interview', 'offered', 'hired', 'rejected' );
	if ( ! in_array( $to, $allowed, true ) || $to === $app['status'] || in_array( $app['status'], array( 'confirmed', 'withdrawn' ), true ) ) {
		back( $back, 'error' );
	}
	Store\set_status( $app_id, $to );
	Notify\status_changed( $app_id, $to );
	back( $back, 'status_saved' );
}

/** Practitioner answers a shift offer. */
function handle_accept(): void {
	check( 'oria_work_accept' );
	$uid    = get_current_user_id();
	$app_id = (int) ( $_POST['app'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$app    = Store\app( $app_id );
	$back   = \Oria\Core\MyOria\url( 'work' );
	if ( ! $app || (int) $app['user_id'] !== $uid || 'offered' !== $app['status'] ) {
		back( $back, 'error' );
	}
	$yes = 'yes' === ( $_POST['answer'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $yes && 'shift' === $app['kind'] && ! Work\is_open( (int) $app['post_id'] ) ) {
		back( $back, 'closed' );
	}
	Store\set_status( $app_id, $yes ? ( 'shift' === $app['kind'] ? 'confirmed' : 'hired' ) : 'withdrawn' );
	Notify\answered( $app_id, $yes );

	// A shift with every place filled closes itself.
	if ( $yes && 'shift' === $app['kind'] ) {
		$shift  = (int) $app['post_id'];
		$filled = Store\counts( array( $shift ) )[ $shift ]['filled'] ?? 0;
		if ( $filled >= max( 1, (int) Work\meta( $shift, 'workers', 1 ) ) ) {
			Work\set( $shift, 'closed', 'filled' );
			Work\set( $shift, 'closed_at', time() );
		}
	}
	back( $back, $yes ? 'accepted' : 'declined' );
}

function handle_withdraw(): void {
	check( 'oria_work_withdraw' );
	$app = Store\app( (int) ( $_POST['app'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $app && (int) $app['user_id'] === get_current_user_id() && ! in_array( $app['status'], array( 'hired', 'confirmed' ), true ) ) {
		Store\set_status( (int) $app['id'], 'withdrawn' );
	}
	back( \Oria\Core\MyOria\url( 'work' ), 'withdrawn' );
}

/** Employer closes a job or shift (filled / no longer needed), or reopens a job. */
function handle_close(): void {
	check( 'oria_work_close' );
	$id   = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back = \Oria\Core\MyOria\url( 'recruit' );
	if ( ! in_array( get_post_type( $id ), array( JOB, SHIFT ), true ) || ! Work\owns( $id, get_current_user_id() ) ) {
		back( $back, 'error' );
	}
	if ( 'reopen' === ( $_POST['do'] ?? '' ) && JOB === get_post_type( $id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		Work\set( $id, 'closed', '' );
		Work\set( $id, 'expires', time() + Work\JOB_DAYS * DAY_IN_SECONDS );
		back( $back, 'reopened' );
	}
	Work\set( $id, 'closed', pick( 'why', array( 'filled' => 1, 'withdrawn' => 1 ), 'filled' ) );
	Work\set( $id, 'closed_at', time() );
	back( $back, 'closed_ok' );
}

/* ------------------------------------------------------ alerts, saved, report */

function handle_alert(): void {
	check( 'oria_work_alert' );
	$uid  = get_current_user_id();
	$back = \Oria\Core\MyOria\url( 'work' ) . '#alerts';
	if ( 'delete' === ( $_POST['do'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		Store\delete_alert( (int) ( $_POST['alert'] ?? 0 ), $uid ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		back( $back, 'alert_off' );
	}
	if ( count( Store\alerts_by_user( $uid ) ) >= 10 ) {
		back( $back, 'alert_limit' );
	}
	$prof = (int) ( $_POST['profession'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$sub  = get_term_by( 'slug', sanitize_title( (string) ( $_POST['area'] ?? '' ) ), 'area' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$type = sanitize_title( (string) ( $_POST['employment'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	Store\add_alert(
		array(
			'user_id'    => $uid,
			'kind'       => 'shift' === ( $_POST['kind'] ?? '' ) ? 'shift' : 'job', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'profession' => get_term( $prof, PROFESSION ) ? $prof : 0,
			'area'       => $sub ? (int) $sub->term_id : 0,
			'employment' => isset( Work\employment_types()[ $type ] ) ? $type : '',
			'keyword'    => txt( 'keyword', 60 ),
			'frequency'  => pick( 'frequency', array( 'immediate' => 1, 'daily' => 1, 'weekly' => 1 ), 'daily' ),
		)
	);
	back( $back, 'alert_on' );
}

/** Save or unsave a job/shift for later (brief section 26). */
function handle_save(): void {
	check( 'oria_work_save' );
	$uid   = get_current_user_id();
	$id    = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$saved = array_map( 'intval', (array) get_user_meta( $uid, SAVED, true ) );
	if ( in_array( get_post_type( $id ), array( JOB, SHIFT ), true ) ) {
		$was   = in_array( $id, $saved, true );
		$saved = $was ? array_values( array_diff( $saved, array( $id ) ) ) : array_slice( array_merge( array( $id ), $saved ), 0, 100 );
		update_user_meta( $uid, SAVED, $saved );
		if ( ! $was && JOB === get_post_type( $id ) ) {
			Work\count_event( $id, 'save' );
		}
	}
	back( referer(), in_array( $id, $saved, true ) ? 'saved' : 'unsaved' );
}

/**
 * "Claim this job" on an external job (brief section 81). A person checks
 * the claim: the admin is emailed who asked, and moves the job to their
 * account by changing its author -- which is also what makes it theirs to
 * edit and receive applications for.
 */
function handle_claim(): void {
	check( 'oria_work_claim' );
	$id   = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back = get_permalink( $id ) ?: home_url( '/jobs/' );
	if ( JOB !== get_post_type( $id ) || ! Work\meta( $id, 'external' ) ) {
		back( $back, 'error' );
	}
	$u = wp_get_current_user();
	Notify\mail(
		(string) get_option( 'admin_email' ),
		/* translators: %s: job title */
		sprintf( __( 'Job claim: %s', 'oria' ), get_the_title( $id ) ),
		__( 'Someone says they are the employer', 'oria' ),
		array(
			/* translators: 1: name, 2: email */
			sprintf( __( '%1$s (%2$s) has claimed this external job.', 'oria' ), $u->display_name, $u->user_email ),
			__( 'If they check out, set them as the job\'s author, untick "External job", and the job is theirs.', 'oria' ),
		),
		array( admin_url( 'post.php?post=' . $id . '&action=edit' ), __( 'Open the job', 'oria' ) )
	);
	back( $back, 'claim_sent' );
}

/* ------------------------------------------------------------ phase 2 */

/**
 * A practitioner's availability post (brief section 46): dates, days and a
 * line. "Clear" removes it. Needs a work profile -- the post IS the profile
 * shown on the cover board.
 */
function handle_avail(): void {
	check( 'oria_work_avail' );
	$uid  = get_current_user_id();
	$pro  = Work\profile_of( $uid );
	$back = \Oria\Core\MyOria\url( 'work' ) . '#availability';
	if ( ! $pro ) {
		back( \Oria\Core\MyOria\url( 'work-edit' ), 'profile_first' );
	}
	if ( 'clear' === ( $_POST['do'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ( array( 'avail_from', 'avail_to', 'avail_days', 'avail_note' ) as $k ) {
			Work\set( $pro, $k, '' );
		}
		back( $back, 'avail_cleared' );
	}
	$from  = valid_date( txt( 'from', 10 ) ) ?: wp_date( 'Y-m-d' );
	$to    = valid_date( txt( 'to', 10 ) );
	$today = wp_date( 'Y-m-d' );
	$far   = wp_date( 'Y-m-d', strtotime( '+90 days' ) );
	if ( '' === $to || $to < $from || $to < $today ) {
		back( $back, 'avail_dates' );
	}
	$days = array_values( array_intersect( array_map( 'intval', (array) ( $_POST['days'] ?? array() ) ), array_keys( Work\WEEKDAYS ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	sort( $days );
	Work\set( $pro, 'avail_from', max( $from, $today ) );
	Work\set( $pro, 'avail_to', min( $to, $far ) );
	Work\set( $pro, 'avail_days', $days );
	Work\set( $pro, 'avail_note', txt( 'note', 140 ) );
	// Posting availability is saying "I'll take cover" -- make the profile say so too.
	$for = (array) Work\meta( $pro, 'available_for', array() );
	if ( ! in_array( 'cover', $for, true ) ) {
		$for[] = 'cover';
		Work\set( $pro, 'available_for', $for );
	}
	back( $back, 'avail_saved' );
}

/** Save a practitioner to a list, update the note/status, or remove (brief sections 67-68). */
function handle_talent(): void {
	check( 'oria_work_talent' );
	$uid  = get_current_user_id();
	$back = referer();
	if ( ! \Oria\Core\Work\Plans\allows( $uid, 'talent' ) ) {
		back( $back, 'plan' );
	}
	if ( 'remove' === ( $_POST['do'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		Store\remove_talent( (int) ( $_POST['row'] ?? 0 ), $uid ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		back( $back, 'talent_removed' );
	}
	$pro = (int) ( $_POST['profile'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( PRO !== get_post_type( $pro ) || ! Work\profile_visible_to( $pro, $uid ) ) {
		back( $back, 'error' );
	}
	$list = txt( 'new_list', 60 ) ?: txt( 'list', 60 ) ?: 'Saved';
	Store\save_talent( $uid, $pro, $list, area( 'note', 1000 ), pick( 'status', Store\TALENT_STATUS, 'saved' ) );
	back( $back, 'talent_saved' );
}

/**
 * Invite a practitioner to one of your open jobs or shifts. Only when they
 * allow employer contact; never twice for the same post; 20 a day at most.
 * The practitioner gets an email -- the employer never sees their address.
 */
function handle_invite(): void {
	check( 'oria_work_invite' );
	$uid  = get_current_user_id();
	$back = referer();
	$pro  = (int) ( $_POST['profile'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$post = (int) ( $_POST['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! \Oria\Core\Work\Plans\allows( $uid, 'invite' ) ) {
		back( $back, 'plan' );
	}
	if ( PRO !== get_post_type( $pro ) || ! Work\profile_visible_to( $pro, $uid ) || ! Work\owns( $post, $uid ) || ! Work\is_open( $post ) ) {
		back( $back, 'error' );
	}
	if ( 'allow' !== (string) Work\meta( $pro, 'contact_pref', 'allow' ) ) {
		back( $back, 'no_contact' );
	}
	if ( Store\invites_today( $uid ) >= 20 ) {
		back( $back, 'invite_limit' );
	}
	if ( ! Store\add_invite( $uid, $pro, $post ) ) {
		back( $back, 'invite_dupe' );
	}
	Notify\invited( $pro, $post, area( 'message', 500 ) );
	back( $back, 'invite_sent' );
}

/**
 * "Feature this job" (brief section 40). No checkout yet: the request
 * reaches the admin, who features it from the job's Oria controls.
 */
function handle_feature(): void {
	check( 'oria_work_feature' );
	$id   = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back = \Oria\Core\MyOria\url( 'recruit' );
	if ( JOB !== get_post_type( $id ) || ! Work\owns( $id, get_current_user_id() ) || ! Work\is_open( $id ) ) {
		back( $back, 'error' );
	}
	if ( Work\meta( $id, 'feature_requested' ) ) {
		back( $back, 'feature_sent' );
	}
	Work\set( $id, 'feature_requested', time() );
	$days = pick( 'days', array( '7' => 1, '14' => 1, '30' => 1 ), '7' );
	Notify\to_admin_feature( $id, (int) $days );
	back( $back, 'feature_sent' );
}

function saved_ids( int $uid ): array {
	return array_values( array_filter( array_map( 'intval', (array) get_user_meta( $uid, SAVED, true ) ) ) );
}

/**
 * Report a job, shift or profile (brief section 57). Open to everyone, so
 * behind the same walls as every public Oria form. Nothing is hidden
 * automatically: a person reads every report.
 */
function handle_report(): void {
	$id   = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back = ( get_permalink( $id ) ?: home_url( '/jobs/' ) );
	if ( '' !== (string) ( $_POST['wk_website'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		back( $back, 'reported' );
	}
	$ts = (int) ( $_POST['wk_ts'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $ts <= 0 || time() - $ts < 3 ) {
		back( $back, 'error' );
	}
	check( 'oria_work_report' );
	if ( ! in_array( get_post_type( $id ), array( JOB, SHIFT, PRO ), true ) ) {
		back( $back, 'error' );
	}
	$ip   = hash( 'sha256', wp_salt( 'nonce' ) . (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$key  = 'oria_wk_rep_' . substr( $ip, 0, 24 );
	$seen = (int) get_transient( $key );
	if ( $seen >= 5 ) {
		back( $back, 'reported' );
	}
	set_transient( $key, $seen + 1, DAY_IN_SECONDS );
	$reason = pick( 'reason', Work\REPORT_REASONS, 'misleading' );
	Store\add_report( $id, $reason, area( 'note', 500 ), get_current_user_id(), $ip );
	Notify\to_admin_report( $id, $reason );
	back( $back, 'reported' );
}
