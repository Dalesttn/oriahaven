<?php
/**
 * /oria-pass/ and /oria-pass/partners/.
 *
 * Routes, not WordPress pages. A page template attaches by slug, so a
 * feature whose page exists only in one database 404s everywhere else --
 * which is exactly how /submit-an-event/ once shipped broken to
 * production. A rewrite rule ships with the code.
 *
 * Rewrite rules do live in the database, though, so maybe_flush() rebuilds
 * them whenever ROUTES_V moves. Bump it when a rule here changes.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Route;

use Oria\Pass\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_pass';
const PATH      = 'oria-pass';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\rules', 10 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 20 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 20 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description', 20 );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical', 20 );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\assets' );
}

/**
 * Only on the Pass pages. The stylesheet is small enough to load
 * normally; the script goes in the footer because nothing on the page
 * waits for it.
 */
function assets(): void {
	$css = ORIA_PASS_DIR . 'assets/css/pass.css';
	$js  = ORIA_PASS_DIR . 'assets/js/pass.js';

	/*
	 * Registered everywhere, enqueued where we know it is needed.
	 *
	 * Guessing which pages need it has now failed twice: the availability
	 * ticket lives on a custom post type rather than a page, and the
	 * conditional tags are not dependable here anyway -- is_page() reads
	 * false on real pages at this point, presumably because something has
	 * left the global query pointing elsewhere. So the parts that draw
	 * Pass markup ask for the stylesheet themselves, which cannot be wrong
	 * by construction. This list only decides who gets it early enough to
	 * avoid a flash.
	 */
	wp_register_style( 'oria-pass', ORIA_PASS_URL . 'assets/css/pass.css', array(), (string) filemtime( $css ) );
	wp_register_script( 'oria-pass', ORIA_PASS_URL . 'assets/js/pass.js', array(), (string) filemtime( $js ), array( 'in_footer' => true ) );

	$likely = is_page()
		|| is_singular( 'listing' )
		|| is_tax()
		|| '' !== (string) get_query_var( 'oria_my' )
		|| '' !== (string) get_query_var( QUERY_VAR );

	if ( ! $likely ) {
		return;
	}

	wp_enqueue_style( 'oria-pass' );
	wp_enqueue_script( 'oria-pass' );
}

function rules(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QUERY_VAR . '=landing', 'top' );
	add_rewrite_rule( '^' . PATH . '/partners/?$', 'index.php?' . QUERY_VAR . '=partners', 'top' );
}

function maybe_flush(): void {
	if ( get_option( 'oria_pass_routes_v' ) === \Oria\Pass\ROUTES_V ) {
		return;
	}
	rules();
	flush_rewrite_rules();
	update_option( 'oria_pass_routes_v', \Oria\Pass\ROUTES_V );
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;

	return $vars;
}

/** '' when this is not one of ours, otherwise 'landing' or 'partners'. */
function which(): string {
	$v = (string) get_query_var( QUERY_VAR );

	return in_array( $v, array( 'landing', 'partners' ), true ) ? $v : '';
}

function is_page(): bool {
	return '' !== which();
}

/**
 * Same trick the other routes use: a rule with no post behind it reads as
 * the home page unless the query is told otherwise, which would give the
 * landing page the home page's title and schema.
 */
function fix_query( \WP_Query $q ): void {
	if ( ! $q->is_main_query() || ! $q->get( QUERY_VAR ) ) {
		return;
	}
	$q->is_home     = false;
	$q->is_front_page = false;
	$q->is_404      = false;
	$q->is_page     = false;
	$q->is_singular = false;
}

function template( string $template ): string {
	$which = which();
	if ( '' === $which ) {
		return $template;
	}

	$found = locate_template( array( 'landing' === $which ? 'oria-pass.php' : 'oria-pass-partners.php' ) );

	return $found ?: $template;
}

function title( $title ) {
	switch ( which() ) {
		case 'landing':
			return __( 'Oria Pass | One wellness membership for Perth', 'oria' );
		case 'partners':
			return __( 'Become an Oria Pass partner | Oria Haven', 'oria' );
	}

	return $title;
}

function core_title( array $parts ): array {
	$t = title( '' );
	if ( '' !== $t ) {
		$parts['title'] = $t;
		unset( $parts['tagline'] );
	}

	return $parts;
}

function description( $desc ) {
	switch ( which() ) {
		case 'landing':
			return Settings\is_live()
				? __( 'Oria Pass is a wellness membership for Perth: monthly credits to try yoga, Pilates, sauna, float, breathwork and more with participating businesses.', 'oria' )
				: __( 'Oria Pass is an upcoming wellness membership for Perth: credits to try yoga, Pilates, sauna, float, breathwork and more with participating businesses. Join the waitlist.', 'oria' );
		case 'partners':
			return __( 'Put spare capacity to work. Oria Pass introduces your studio to Perth locals looking for something new, on the sessions you choose to release.', 'oria' );
	}

	return $desc;
}

function canonical( $url ) {
	switch ( which() ) {
		case 'landing':
			return home_url( '/' . PATH . '/' );
		case 'partners':
			return home_url( '/' . PATH . '/partners/' );
	}

	return $url;
}

/** The address of the page, for links elsewhere on the site. */
function url( string $which = 'landing' ): string {
	return home_url( 'partners' === $which ? '/' . PATH . '/partners/' : '/' . PATH . '/' );
}
