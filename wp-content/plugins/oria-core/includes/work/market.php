<?php
/**
 * Work in Wellness, phase 3: the wider marketplace.
 *
 *   /corporate-wellness/   practitioners for workplaces (brief section 49)
 *   /retreat-staff/        practitioners and crew for retreats (section 50)
 *   Both list profiles whose owners said they are available for that work,
 *   and take an enquiry that comes to Oria staff -- never auto-sent to
 *   practitioners -- so a lead fee can be charged by hand later.
 *
 *   /jobs/training/        curated courses (section 53). Staff add them; there
 *                          is no public submission, so nothing listed is invented.
 *   /jobs/salary-guide/    advertised pay from real Oria job listings (section
 *                          43). A row appears only with 5+ listings quoting pay
 *                          in the same unit -- below that, nothing is shown.
 *
 * Plus benchmark(): a job's views against similar jobs (section 65).
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work\Market;

use Oria\Core\Work;
use Oria\Core\Work\Forms;
use Oria\Core\Work\Notify;
use const Oria\Core\Work\JOB;
use const Oria\Core\Work\PRO;
use const Oria\Core\Work\COURSE;
use const Oria\Core\Work\REQUEST;
use const Oria\Core\Work\PROFESSION;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Minimum listings quoting pay before the salary guide shows a row. */
const SALARY_MIN = 5;

/** Minimum comparable jobs before a benchmark is stated. */
const BENCH_MIN = 3;

/** view => [path, available_for key, professions that suit it]. */
const MARKETS = array(
	'corporate' => array( 'corporate-wellness', 'corporate', array( 'yoga-teacher', 'meditation-teacher', 'massage-therapist', 'breathwork-facilitator', 'personal-trainer', 'group-fitness-instructor', 'mindfulness-coach', 'wellness-speaker', 'corporate-wellness-facilitator' ) ),
	'retreat'   => array( 'retreat-staff', 'retreats', array( 'yoga-teacher', 'meditation-teacher', 'massage-therapist', 'retreat-facilitator', 'retreat-host', 'retreat-cook', 'retreat-manager', 'photographer', 'casual-retreat-staff', 'breathwork-facilitator', 'sound-healing-practitioner' ) ),
);

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 11 );
	add_action( 'admin_post_oria_work_talent_request', __NAMESPACE__ . '\handle_request' );
	add_action( 'admin_post_nopriv_oria_work_talent_request', __NAMESPACE__ . '\handle_request' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\boxes' );
	add_action( 'save_post_' . COURSE, __NAMESPACE__ . '\save_course', 10, 2 );
	// A changed job can change a salary row.
	add_action( 'save_post_' . JOB, static fn() => delete_transient( 'oria_wk_salary' ) );
}

function route(): void {
	add_rewrite_rule( '^corporate-wellness/?$', 'index.php?' . Work\QV . '=corporate', 'top' );
	add_rewrite_rule( '^retreat-staff/?$', 'index.php?' . Work\QV . '=retreat', 'top' );
	add_rewrite_rule( '^' . Work\BASE_JOBS . '/training/?$', 'index.php?' . Work\QV . '=training', 'top' );
	add_rewrite_rule( '^' . Work\BASE_JOBS . '/salary-guide/?$', 'index.php?' . Work\QV . '=salary', 'top' );
}

function url( string $view ): string {
	return array(
		'corporate' => home_url( '/corporate-wellness/' ),
		'retreat'   => home_url( '/retreat-staff/' ),
		'training'  => home_url( '/' . Work\BASE_JOBS . '/training/' ),
		'salary'    => home_url( '/' . Work\BASE_JOBS . '/salary-guide/' ),
	)[ $view ] ?? home_url( '/' );
}

/* ----------------------------------------------- corporate / retreat talent */

/** Public, available-for-$market profiles, best first. */
function talent_for( string $market, int $n = 24 ): array {
	$cfg = MARKETS[ $market ] ?? null;
	if ( ! $cfg ) {
		return array();
	}
	$q   = Work\query( PRO, array(), 400 );
	$ids = array();
	foreach ( $q->posts as $p ) {
		if ( in_array( $cfg[1], (array) Work\meta( (int) $p->ID, 'available_for', array() ), true ) ) {
			$ids[ (int) $p->ID ] = Work\completeness( (int) $p->ID )['score'] + 5 * count( Work\badges( (int) $p->ID ) );
		}
	}
	arsort( $ids );
	return array_slice( array_keys( $ids ), 0, $n );
}

/**
 * A business asks Oria for corporate/retreat/event talent. Stored privately
 * for staff, who make the introduction -- the brief's lead-fee model -- so
 * no practitioner is emailed by a stranger's form.
 */
function handle_request(): void {
	$market = 'retreat' === ( $_POST['market'] ?? '' ) ? 'retreat' : 'corporate'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back   = url( $market );
	if ( '' !== (string) ( $_POST['wk_website'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		Forms\back( $back, 'request_sent', '#enquire' );
	}
	$ts = (int) ( $_POST['wk_ts'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $ts <= 0 || time() - $ts < 3 ) {
		Forms\back( $back, 'error', '#enquire' );
	}
	Forms\check( 'oria_work_talent_request' );
	$ip  = hash( 'sha256', wp_salt( 'nonce' ) . (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$key = 'oria_wk_req_' . substr( $ip, 0, 24 );
	if ( (int) get_transient( $key ) >= 5 ) {
		Forms\back( $back, 'request_sent', '#enquire' );
	}
	set_transient( $key, (int) get_transient( $key ) + 1, DAY_IN_SECONDS );

	$org   = Forms\txt( 'org', 120 );
	$name  = Forms\txt( 'name', 80 );
	$email = sanitize_email( Forms\txt( 'email', 120 ) );
	$need  = Forms\area( 'need', 2000 );
	if ( '' === $org || '' === $name || ! is_email( $email ) || strlen( $need ) < 20 ) {
		Forms\back( $back, 'request_more', '#enquire' );
	}
	$id = wp_insert_post(
		array(
			'post_type'    => REQUEST,
			'post_status'  => 'private',
			'post_title'   => sprintf( '%s — %s', $org, 'retreat' === $market ? 'retreat staff' : 'corporate wellness' ),
			'post_content' => $need,
		)
	);
	if ( $id ) {
		foreach ( array( 'market' => $market, 'org' => $org, 'name' => $name, 'email' => $email, 'phone' => Forms\txt( 'phone', 40 ), 'when' => Forms\txt( 'when', 120 ), 'where' => Forms\txt( 'where', 120 ), 'people' => Forms\txt( 'people', 40 ), 'budget' => Forms\txt( 'budget', 80 ), 'status' => 'new' ) as $k => $v ) {
			Work\set( (int) $id, $k, $v );
		}
		Notify\mail(
			(string) get_option( 'admin_email' ),
			/* translators: %s: organisation */
			sprintf( __( 'Talent request: %s', 'oria' ), $org ),
			'retreat' === $market ? __( 'A retreat needs staff', 'oria' ) : __( 'A workplace wants wellness practitioners', 'oria' ),
			array_filter( array( "$name, $org ($email)", Forms\txt( 'when', 120 ), Forms\txt( 'where', 120 ), $need ) ),
			array( admin_url( 'post.php?post=' . $id . '&action=edit' ), __( 'Open the request', 'oria' ) )
		);
	}
	Forms\back( $back, 'request_sent', '#enquire' );
}

/* ------------------------------------------------------------------ courses */

const COURSE_FIELDS = array(
	'provider' => 'Provider',
	'mode'     => 'Format (Online / In person / Blended)',
	'place'    => 'Where (suburb or city)',
	'length'   => 'Length (e.g. 200 hours, 3 weekends)',
	'price'    => 'Price as the provider states it (e.g. "From A$3,200")',
	'url'      => 'Link to the course',
	'checked'  => 'Details checked on (YYYY-MM-DD)',
);

function boxes(): void {
	add_meta_box( 'oria_course', __( 'Course details', 'oria' ), __NAMESPACE__ . '\course_box', COURSE, 'normal', 'high' );
	add_meta_box( 'oria_talent_req', __( 'Enquiry', 'oria' ), __NAMESPACE__ . '\request_box', REQUEST, 'normal', 'high' );
}

function course_box( \WP_Post $post ): void {
	wp_nonce_field( 'oria_course', '_wk_course' );
	echo '<p>' . esc_html__( 'Only courses you have checked. Write the description in your own words; keep prices exactly as the provider states them.', 'oria' ) . '</p><table class="form-table">';
	foreach ( COURSE_FIELDS as $k => $label ) {
		printf( '<tr><th><label for="wkc-%1$s">%2$s</label></th><td><input class="widefat" id="wkc-%1$s" name="wkc_%1$s" value="%3$s"></td></tr>', esc_attr( $k ), esc_html( $label ), esc_attr( (string) Work\meta( $post->ID, 'c_' . $k ) ) );
	}
	printf( '<tr><th>%s</th><td><label><input type="checkbox" name="wkc_aff" value="1" %s> %s</label><br><label><input type="checkbox" name="wkc_featured" value="1" %s> %s</label></td></tr>', esc_html__( 'Flags', 'oria' ), checked( (bool) Work\meta( $post->ID, 'c_aff' ), true, false ), esc_html__( 'Affiliate link (shown as "Affiliate link")', 'oria' ), checked( (bool) Work\meta( $post->ID, 'c_featured' ), true, false ), esc_html__( 'Featured', 'oria' ) );
	echo '</table>';
}

function save_course( int $id, \WP_Post $post ): void {
	if ( ! isset( $_POST['_wk_course'] ) || ! wp_verify_nonce( (string) $_POST['_wk_course'], 'oria_course' ) || ! current_user_can( 'edit_post', $id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	foreach ( array_keys( COURSE_FIELDS ) as $k ) {
		$v = sanitize_text_field( wp_unslash( (string) ( $_POST[ 'wkc_' . $k ] ?? '' ) ) );
		Work\set( $id, 'c_' . $k, 'url' === $k ? esc_url_raw( $v ) : $v );
	}
	Work\set( $id, 'c_aff', empty( $_POST['wkc_aff'] ) ? '' : '1' );
	Work\set( $id, 'c_featured', empty( $_POST['wkc_featured'] ) ? '' : '1' );
}

/** Published courses, featured first; optionally for one profession slug. */
function courses( string $profession = '' ): array {
	$args = array( 'post_type' => COURSE, 'post_status' => 'publish', 'numberposts' => 100, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' );
	if ( '' !== $profession ) {
		$args['tax_query'] = array( array( 'taxonomy' => PROFESSION, 'field' => 'slug', 'terms' => $profession ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$ids = array_map( 'intval', get_posts( $args ) );
	usort( $ids, static fn( $a, $b ) => (int) (bool) Work\meta( $b, 'c_featured' ) <=> (int) (bool) Work\meta( $a, 'c_featured' ) );
	return $ids;
}

function request_box( \WP_Post $post ): void {
	echo '<table class="widefat striped">';
	foreach ( array( 'market', 'org', 'name', 'email', 'phone', 'when', 'where', 'people', 'budget' ) as $k ) {
		echo '<tr><th style="width:20%">' . esc_html( $k ) . '</th><td>' . esc_html( (string) Work\meta( $post->ID, $k ) ) . '</td></tr>';
	}
	echo '</table><p>' . esc_html__( 'Reply to the business directly. Introduce practitioners only with their agreement.', 'oria' ) . '</p>';
}

/* ------------------------------------------------------------ salary guide */

/**
 * Advertised pay from Oria job listings in the last 12 months, open or
 * closed. Each listing contributes the midpoint of its range. Grouped by
 * city, profession and unit -- an hourly rate and a per-class rate are
 * never averaged together. Rows below SALARY_MIN listings are dropped.
 *
 * @return list<array{city:string, profession:string, slug:string, unit:string, n:int, median:float, low:float, high:float}>
 */
function salary_rows(): array {
	$hit = get_transient( 'oria_wk_salary' );
	if ( is_array( $hit ) ) {
		return $hit;
	}
	$ids    = get_posts( array( 'post_type' => JOB, 'post_status' => 'publish', 'numberposts' => 3000, 'fields' => 'ids', 'date_query' => array( array( 'after' => '12 months ago' ) ) ) );
	$groups = array();
	foreach ( $ids as $id ) {
		$id   = (int) $id;
		$unit = (string) Work\meta( $id, 'pay_unit' );
		$min  = (float) Work\meta( $id, 'pay_min', 0 );
		$max  = (float) Work\meta( $id, 'pay_max', 0 );
		$prof = Work\term( $id, PROFESSION );
		if ( ! $prof || ! in_array( $unit, array( 'hour', 'class', 'year', 'shift', 'client' ), true ) || ( ! $min && ! $max ) || Work\meta( $id, 'external' ) ) {
			continue;
		}
		$city = Work\city_of( $id );
		$key  = ( $city['slug'] ?? '' ) . '|' . $prof->slug . '|' . $unit;
		$groups[ $key ]['city']       = (string) ( $city['name'] ?? '' );
		$groups[ $key ]['profession'] = $prof->name;
		$groups[ $key ]['slug']       = $prof->slug;
		$groups[ $key ]['unit']       = $unit;
		$groups[ $key ]['mid'][]      = $max > $min ? ( $min + $max ) / 2 : max( $min, $max );
		$groups[ $key ]['low'][]      = $min ?: $max;
		$groups[ $key ]['high'][]     = $max ?: $min;
	}
	$rows = array();
	foreach ( $groups as $g ) {
		$n = count( $g['mid'] );
		if ( $n < SALARY_MIN ) {
			continue;
		}
		sort( $g['mid'] );
		$median = 0 === $n % 2 ? ( $g['mid'][ $n / 2 - 1 ] + $g['mid'][ $n / 2 ] ) / 2 : $g['mid'][ (int) floor( $n / 2 ) ];
		$rows[] = array( 'city' => $g['city'], 'profession' => $g['profession'], 'slug' => $g['slug'], 'unit' => $g['unit'], 'n' => $n, 'median' => round( $median, 2 ), 'low' => min( $g['low'] ), 'high' => max( $g['high'] ) );
	}
	usort( $rows, static fn( $a, $b ) => array( $a['city'], $a['profession'] ) <=> array( $b['city'], $b['profession'] ) );
	set_transient( 'oria_wk_salary', $rows, 6 * HOUR_IN_SECONDS );
	return $rows;
}

/* --------------------------------------------------------------- benchmark */

/**
 * A job's 30-day views against similar jobs: same profession, posted within
 * 90 days of it. null when there are fewer than BENCH_MIN to compare with
 * or the comparison set has no views -- no figure is better than a shaky one.
 *
 * @return array{pct:int, n:int, profession:string}|null
 */
function benchmark( int $job ): ?array {
	$prof = Work\term( $job, PROFESSION );
	if ( ! $prof ) {
		return null;
	}
	$t      = (int) get_post_time( 'U', true, $job );
	$others = get_posts(
		array(
			'post_type'    => JOB,
			'post_status'  => 'publish',
			'numberposts'  => 200,
			'fields'       => 'ids',
			'post__not_in' => array( $job ),
			'tax_query'    => array( array( 'taxonomy' => PROFESSION, 'terms' => (int) $prof->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			'date_query'   => array( array( 'after' => gmdate( 'Y-m-d', $t - 90 * DAY_IN_SECONDS ), 'before' => gmdate( 'Y-m-d', $t + 90 * DAY_IN_SECONDS ), 'inclusive' => true ) ),
		)
	);
	if ( count( $others ) < BENCH_MIN ) {
		return null;
	}
	$views = array_map( static fn( $id ) => Work\stat( (int) $id, 'view' ), $others );
	sort( $views );
	$n      = count( $views );
	$median = 0 === $n % 2 ? ( $views[ $n / 2 - 1 ] + $views[ $n / 2 ] ) / 2 : $views[ (int) floor( $n / 2 ) ];
	if ( $median <= 0 ) {
		return null;
	}
	return array( 'pct' => (int) round( 100 * ( Work\stat( $job, 'view' ) - $median ) / $median ), 'n' => $n, 'profession' => $prof->name );
}
