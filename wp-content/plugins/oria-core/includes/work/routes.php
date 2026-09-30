<?php
/**
 * Work in Wellness: public addresses.
 *
 *   /jobs/                              landing + search
 *   /jobs/{city}/                       jobs in a city
 *   /jobs/category/{profession}/        jobs for a profession
 *   /jobs/{city}/{profession}/          both -- indexed only at 3+ open jobs
 *   /jobs/{job-slug}/                   a job (the post type's own rule)
 *   /jobs/go/{id}/                      counted hop to an external application
 *   /jobs/alerts/off/{token}/           one-click alert unsubscribe
 *   /shifts/  /shifts/{city}/  /shifts/available-practitioners/  /shifts/{slug}/
 *   /practitioners/  /practitioners/{city}/  /practitioners/category/{p}/  /practitioners/{slug}/
 *   /for-business/recruitment/          the employer pitch and prices
 *   /work-file/{application-id}/        a CV, to its applicant and that job's employer only
 *
 * The city segment is matched against the city slugs, never a generic
 * ([^/]+), so /jobs/perth/ can never be mistaken for a job and a job slug
 * can never shadow a city (model.php reserves them).
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QV        = 'oria_work';
const QV_CITY   = 'oria_work_city';
const QV_PROF   = 'oria_work_prof';
const QV_ARG    = 'oria_work_arg';
const REWRITE_V = '1';

/** Every list view, for the template and the robots rules. */
const VIEWS = array( 'jobs', 'shifts', 'pros', 'available', 'recruitment' );

function route(): void {
	$city = function_exists( '\Oria\Core\Cities\slug_pattern' ) ? \Oria\Core\Cities\slug_pattern() : 'perth';
	foreach ( array( BASE_JOBS => 'jobs', BASE_SHIFTS => 'shifts', BASE_PROS => 'pros' ) as $base => $view ) {
		add_rewrite_rule( '^' . $base . '/?$', 'index.php?' . QV . '=' . $view, 'top' );
		add_rewrite_rule( '^' . $base . '/page/([0-9]+)/?$', 'index.php?' . QV . '=' . $view . '&paged=$matches[1]', 'top' );
		add_rewrite_rule( '^' . $base . '/(' . $city . ')/?$', 'index.php?' . QV . '=' . $view . '&' . QV_CITY . '=$matches[1]', 'top' );
		add_rewrite_rule( '^' . $base . '/category/([a-z0-9-]+)/?$', 'index.php?' . QV . '=' . $view . '&' . QV_PROF . '=$matches[1]', 'top' );
		add_rewrite_rule( '^' . $base . '/(' . $city . ')/([a-z0-9-]+)/?$', 'index.php?' . QV . '=' . $view . '&' . QV_CITY . '=$matches[1]&' . QV_PROF . '=$matches[2]', 'top' );
	}
	add_rewrite_rule( '^' . BASE_SHIFTS . '/available-practitioners/?$', 'index.php?' . QV . '=available', 'top' );
	add_rewrite_rule( '^for-business/recruitment/?$', 'index.php?' . QV . '=recruitment', 'top' );
	add_rewrite_rule( '^' . BASE_JOBS . '/go/([0-9]+)/?$', 'index.php?' . QV . '=go&' . QV_ARG . '=$matches[1]', 'top' );
	add_rewrite_rule( '^' . BASE_JOBS . '/alerts/off/([A-Za-z0-9]+)/?$', 'index.php?' . QV . '=alertoff&' . QV_ARG . '=$matches[1]', 'top' );
	add_rewrite_rule( '^work-file/([0-9]+)/?$', 'index.php?' . QV . '=file&' . QV_ARG . '=$matches[1]', 'top' );
}

function maybe_flush(): void {
	$v = REWRITE_V . '|' . ( function_exists( '\Oria\Core\Cities\slug_pattern' ) ? \Oria\Core\Cities\slug_pattern() : '' );
	if ( get_option( 'oria_work_rewrite_v' ) !== $v ) {
		flush_rewrite_rules( false );
		update_option( 'oria_work_rewrite_v', $v, false );
	}
}

function query_vars( array $vars ): array {
	return array_merge( $vars, array( QV, QV_CITY, QV_PROF, QV_ARG ) );
}

function view(): string {
	return (string) get_query_var( QV );
}

function is_work_page(): bool {
	return in_array( view(), VIEWS, true ) || is_singular( array( JOB, SHIFT, PRO ) );
}

/** A virtual page is not the blog home. */
function fix_query( \WP_Query $q ): void {
	if ( $q->is_main_query() && '' !== (string) $q->get( QV ) ) {
		$q->is_home       = false;
		$q->is_front_page = false;
		$q->is_404        = false;
	}
}

/** Filters for a list page: the route's own city/profession plus the query string. */
function filters(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$g = static fn( string $k ): string => sanitize_title( wp_unslash( (string) ( $_GET[ $k ] ?? '' ) ) );
	$f = array(
		'q'          => sanitize_text_field( wp_unslash( (string) ( $_GET['q'] ?? '' ) ) ),
		'profession' => (string) get_query_var( QV_PROF ) ?: $g( 'profession' ),
		'city'       => (string) get_query_var( QV_CITY ),
		'area'       => $g( 'area' ),
		'type'       => $g( 'type' ),
		'skill'      => $g( 'skill' ),
		'when'       => $g( 'when' ),
		'sort'       => $g( 'sort' ),
		'paid'       => ! empty( $_GET['paid'] ),
		'remote'     => ! empty( $_GET['remote'] ),
		'weekend'    => ! empty( $_GET['weekend'] ),
		'evening'    => ! empty( $_GET['evening'] ),
		'cover'      => ! empty( $_GET['cover'] ),
		'page'       => max( 1, (int) get_query_var( 'paged' ), (int) ( $_GET['pg'] ?? 1 ) ),
	);
	// phpcs:enable
	return $f;
}

/** Whether the visitor narrowed the list with the query string (those URLs are never indexed). */
function is_filtered(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	foreach ( array( 'q', 'profession', 'area', 'type', 'skill', 'when', 'sort', 'paid', 'remote', 'weekend', 'evening', 'cover', 'pg' ) as $k ) {
		if ( isset( $_GET[ $k ] ) && '' !== (string) $_GET[ $k ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
	}
	return (int) get_query_var( 'paged' ) > 1;
}

/** Canonical URL of a list view with route segments only. */
function list_url( string $view, string $city = '', string $prof = '' ): string {
	$base = array( 'jobs' => BASE_JOBS, 'shifts' => BASE_SHIFTS, 'pros' => BASE_PROS )[ $view ] ?? BASE_JOBS;
	if ( 'available' === $view ) {
		return home_url( '/' . BASE_SHIFTS . '/available-practitioners/' );
	}
	if ( 'recruitment' === $view ) {
		return home_url( '/for-business/recruitment/' );
	}
	if ( '' !== $city && '' !== $prof ) {
		return home_url( "/$base/$city/$prof/" );
	}
	if ( '' !== $city ) {
		return home_url( "/$base/$city/" );
	}
	if ( '' !== $prof ) {
		return home_url( "/$base/category/$prof/" );
	}
	return home_url( "/$base/" );
}

/* ------------------------------------------------------------- dispatch */

function template( string $template ): string {
	$v = view();
	if ( in_array( $v, VIEWS, true ) ) {
		// An unknown city or profession segment is a 404, not an empty page.
		$city = (string) get_query_var( QV_CITY );
		$prof = (string) get_query_var( QV_PROF );
		if ( ( '' !== $city && function_exists( '\Oria\Core\Cities\is_public' ) && ! \Oria\Core\Cities\is_public( \Oria\Core\Cities\get( $city ) ) )
			|| ( '' !== $prof && ! get_term_by( 'slug', $prof, PROFESSION ) ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return get_404_template() ?: $template;
		}
		status_header( 200 );
		$found = locate_template( array( 'oria-work.php' ) );
		return $found ?: $template;
	}
	return $template;
}

/** The side-door routes that never render a page. */
function side_doors(): void {
	$v = view();
	if ( 'go' === $v ) {
		outbound( (int) get_query_var( QV_ARG ) );
	}
	if ( 'alertoff' === $v ) {
		$ok = Store\alert_off_by_token( (string) get_query_var( QV_ARG ) );
		wp_die(
			esc_html( $ok ? __( 'Done — that job alert is switched off. You can set up new alerts from My Oria any time.', 'oria' ) : __( 'That alert is already off.', 'oria' ) ),
			esc_html__( 'Job alert', 'oria' ),
			array( 'response' => 200, 'link_url' => esc_url( home_url( '/' . BASE_JOBS . '/' ) ), 'link_text' => esc_html__( 'Back to jobs', 'oria' ) )
		);
	}
	if ( 'file' === $v ) {
		serve_file( (int) get_query_var( QV_ARG ) );
	}
}

/**
 * External application hop (brief section 20): count it, then go. Only
 * to the address stored on the job -- this is not an open redirect.
 */
function outbound( int $job ): void {
	if ( JOB !== get_post_type( $job ) || ! is_open( $job ) ) {
		wp_safe_redirect( home_url( '/' . BASE_JOBS . '/' ), 302 );
		exit;
	}
	$method = (string) meta( $job, 'apply_method', 'oria' );
	set( $job, 'apply_clicks', (int) meta( $job, 'apply_clicks', 0 ) + 1 );
	if ( 'url' === $method ) {
		$url = esc_url_raw( (string) meta( $job, 'apply_url' ) );
		if ( $url ) {
			nocache_headers();
			wp_redirect( $url, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect -- the employer's own stored application URL.
			exit;
		}
	}
	if ( 'email' === $method ) {
		$email = sanitize_email( (string) meta( $job, 'apply_email' ) );
		if ( is_email( $email ) ) {
			nocache_headers();
			wp_redirect( 'mailto:' . $email . '?subject=' . rawurlencode( sprintf( 'Application: %s (via Oria Haven)', get_the_title( $job ) ) ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect
			exit;
		}
	}
	wp_safe_redirect( get_permalink( $job ), 302 );
	exit;
}

/**
 * A CV, streamed from the private folder -- never a public URL (brief
 * section 89). Readable by the applicant and by the employer of the job
 * applied to, nobody else.
 */
function serve_file( int $app_id ): void {
	$app = Store\app( $app_id );
	$uid = get_current_user_id();
	if ( ! $app || ! $uid || ( (int) $app['user_id'] !== $uid && ! owns( (int) $app['post_id'], $uid ) ) ) {
		wp_die( esc_html__( 'You do not have access to this file.', 'oria' ), '', array( 'response' => 403 ) );
	}
	$path = Files\path( (int) $app['cv_id'] );
	if ( '' === $path || ! is_readable( $path ) ) {
		wp_die( esc_html__( 'That file is no longer available.', 'oria' ), '', array( 'response' => 404 ) );
	}
	if ( (int) $app['user_id'] !== $uid && 'new' === $app['status'] ) {
		Store\set_status( $app_id, 'viewed' );
		Notify\status_changed( $app_id, 'viewed' );
	}
	nocache_headers();
	header( 'Content-Type: ' . ( wp_check_filetype( $path )['type'] ?: 'application/octet-stream' ) );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $path ) ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Length: ' . (string) filesize( $path ) );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/* -------------------------------------------------------------------- seo */

/**
 * What the robots meta says.
 *
 * Indexed: the three landings, city and profession pages with something on
 * them, city x profession pages with 3+ open jobs (brief section 61), open
 * jobs, and complete public profiles. Not indexed: anything filtered by
 * the query string, closed jobs after 30 days, shifts (they live for hours),
 * the available-practitioners board, and thin or private profiles.
 */
function noindex(): bool {
	$v = view();
	if ( in_array( $v, VIEWS, true ) ) {
		if ( is_filtered() || 'available' === $v ) {
			return true;
		}
		$city = (string) get_query_var( QV_CITY );
		$prof = (string) get_query_var( QV_PROF );
		if ( '' !== $city || '' !== $prof ) {
			$type = array( 'jobs' => JOB, 'shifts' => SHIFT, 'pros' => PRO )[ $v ] ?? JOB;
			$n    = open_count( $type, array( 'city' => $city, 'profession' => $prof ) );
			return ( '' !== $city && '' !== $prof ) ? $n < 3 : $n < 1;
		}
		return false;
	}
	if ( is_singular( SHIFT ) ) {
		return true;
	}
	if ( is_singular( JOB ) ) {
		$id = (int) get_queried_object_id();
		if ( 'publish' !== get_post_status( $id ) ) {
			return true;
		}
		if ( '' !== closed_reason( $id ) ) {
			$closed = (int) meta( $id, 'closed_at', 0 ) ?: (int) meta( $id, 'expires', 0 );
			return $closed && $closed < time() - 30 * DAY_IN_SECONDS;
		}
		return false;
	}
	if ( is_singular( PRO ) ) {
		return ! profile_indexable( (int) get_queried_object_id() );
	}
	return false;
}

function wp_robots( array $r ): array {
	if ( is_work_page() && noindex() ) {
		$r['noindex'] = true;
		$r['follow']  = true;
		unset( $r['index'] );
	}
	return $r;
}

/** @param mixed $robots */
function yoast_robots( $robots ) {
	return ( is_work_page() && noindex() ) ? 'noindex, follow' : $robots;
}

/** The page's H1 and <title>, shared so they never disagree. */
function heading(): string {
	$v    = view();
	$city = (string) get_query_var( QV_CITY );
	$prof = (string) get_query_var( QV_PROF );
	$cn   = '';
	if ( '' !== $city && function_exists( '\Oria\Core\Cities\get' ) ) {
		$cn = (string) ( \Oria\Core\Cities\get( $city )['name'] ?? '' );
	}
	$pt = '';
	if ( '' !== $prof ) {
		$t  = get_term_by( 'slug', $prof, PROFESSION );
		$pt = $t ? $t->name : '';
	}
	switch ( $v ) {
		case 'jobs':
			if ( $pt && $cn ) {
				/* translators: 1: profession, 2: city */
				return sprintf( __( '%1$s jobs in %2$s', 'oria' ), $pt, $cn );
			}
			if ( $pt ) {
				/* translators: %s: profession */
				return sprintf( __( '%s jobs', 'oria' ), $pt );
			}
			/* translators: %s: city */
			return $cn ? sprintf( __( 'Wellness jobs in %s', 'oria' ), $cn ) : __( 'Find work in wellness', 'oria' );
		case 'shifts':
			/* translators: %s: city */
			return $cn ? sprintf( __( 'Casual wellness shifts in %s', 'oria' ), $cn ) : ( $pt ? sprintf( __( '%s shifts and cover', 'oria' ), $pt ) : __( 'Casual shifts and cover', 'oria' ) );
		case 'pros':
			/* translators: %s: city */
			return $pt ? sprintf( __( '%ss', 'oria' ), $pt ) : ( $cn ? sprintf( __( 'Wellness practitioners in %s', 'oria' ), $cn ) : __( 'Wellness practitioners', 'oria' ) );
		case 'available':
			return __( 'Practitioners available for cover', 'oria' );
		case 'recruitment':
			return __( 'Find wellness staff and cover', 'oria' );
	}
	return '';
}

/** @param mixed $title */
function title( $title ) {
	if ( in_array( view(), VIEWS, true ) ) {
		return heading() . ' | Oria Haven';
	}
	return $title;
}

function core_title( array $parts ): array {
	if ( in_array( view(), VIEWS, true ) ) {
		$parts['title'] = heading();
	}
	return $parts;
}

/** @param mixed $desc */
function description( $desc ) {
	switch ( view() ) {
		case 'jobs':
			return __( 'Wellness jobs with real businesses: yoga, Pilates, massage, spa, fitness and studio roles. Pay shown wherever the employer gives it.', 'oria' );
		case 'shifts':
			return __( 'Casual wellness shifts and last-minute cover: yoga, Pilates, massage and reception. Tap "I\'m available" and the studio hears from you.', 'oria' );
		case 'pros':
			return __( 'Wellness practitioners open to work, with their skills, availability and verified credentials.', 'oria' );
		case 'recruitment':
			return __( 'Post wellness jobs and casual shifts, find cover when an instructor calls in sick, and reach practitioners who are ready to work.', 'oria' );
	}
	return $desc;
}

/** Only the page's own clean URL is canonical: filters point back to it. */
function canonical( $canonical ) {
	$v = view();
	if ( in_array( $v, VIEWS, true ) ) {
		return list_url( $v, (string) get_query_var( QV_CITY ), (string) get_query_var( QV_PROF ) );
	}
	return $canonical;
}

/* ---------------------------------------------------------------- sitemap */

const SITEMAP = 'work';

function register_sitemap(): void {
	if ( isset( $GLOBALS['wpseo_sitemaps'] ) && is_object( $GLOBALS['wpseo_sitemaps'] ) ) {
		$GLOBALS['wpseo_sitemaps']->register_sitemap( SITEMAP, __NAMESPACE__ . '\build_sitemap' );
	}
}

function sitemap_index( string $index ): string {
	return $index . '<sitemap><loc>' . esc_url( home_url( '/' . SITEMAP . '-sitemap.xml' ) ) . '</loc><lastmod>' . esc_html( gmdate( 'c' ) ) . '</lastmod></sitemap>' . "\n";
}

function build_sitemap(): void {
	global $wpseo_sitemaps;
	$urls = array();
	$now  = gmdate( 'c' );
	foreach ( array( 'jobs', 'shifts', 'pros' ) as $v ) {
		$urls[] = array( 'loc' => list_url( $v ), 'mod' => $now );
	}
	$urls[] = array( 'loc' => list_url( 'recruitment' ), 'mod' => $now );

	$cities = function_exists( '\Oria\Core\Cities\live' ) ? \Oria\Core\Cities\live() : array();
	foreach ( $cities as $c ) {
		$slug = (string) $c['slug'];
		if ( open_count( JOB, array( 'city' => $slug ) ) > 0 ) {
			$urls[] = array( 'loc' => list_url( 'jobs', $slug ), 'mod' => $now );
		}
		foreach ( get_terms( array( 'taxonomy' => PROFESSION, 'hide_empty' => true, 'childless' => true ) ) as $t ) {
			if ( open_count( JOB, array( 'city' => $slug, 'profession' => $t->slug ) ) >= 3 ) {
				$urls[] = array( 'loc' => list_url( 'jobs', $slug, $t->slug ), 'mod' => $now );
			}
		}
	}
	foreach ( get_posts( array( 'post_type' => JOB, 'post_status' => 'publish', 'numberposts' => 1000, 'fields' => 'ids' ) ) as $id ) {
		if ( is_open( (int) $id ) ) {
			$urls[] = array( 'loc' => get_permalink( $id ), 'mod' => get_post_modified_time( 'c', true, $id ) );
		}
	}
	foreach ( get_posts( array( 'post_type' => PRO, 'post_status' => 'publish', 'numberposts' => 1000, 'fields' => 'ids' ) ) as $id ) {
		if ( profile_indexable( (int) $id ) ) {
			$urls[] = array( 'loc' => get_permalink( $id ), 'mod' => get_post_modified_time( 'c', true, $id ) );
		}
	}
	$wpseo_sitemaps->set_sitemap( $wpseo_sitemaps->renderer->get_sitemap( $urls, SITEMAP, 1 ) );
}

/** Keep the post types' own Yoast sitemaps out: ours decides what is indexable. */
function exclude_types( $excluded, string $type ): bool {
	return in_array( $type, array( JOB, SHIFT, PRO ), true ) ? true : (bool) $excluded;
}
