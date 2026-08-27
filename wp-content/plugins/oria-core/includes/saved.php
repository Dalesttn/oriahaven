<?php
/**
 * /saved/ — the practices this browser has kept.
 *
 * A route rather than a WordPress page, for the same reason the hub and the
 * practices index are: it ships in git and exists on production the moment
 * the code lands, with no page to create by hand and nothing to go missing
 * in a database export.
 *
 * The page itself holds no data. Saves live in localStorage on the visitor's
 * own device — no account, nothing sent here, nothing for this site to store
 * or lose — so the server renders an empty shell and app.js fills it from the
 * directory payload that every page already carries.
 *
 * noindex, because there is nothing here for anybody but the person holding
 * the device. Every visitor's copy is different and all of them are empty to
 * a crawler.
 */

declare(strict_types=1);

namespace Oria\Core\Saved;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_saved';
const PATH      = 'saved';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 10 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 20 );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\robots', 20 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 20 );
}

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QUERY_VAR . '=1', 'top' );
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

function is_page(): bool {
	return (bool) get_query_var( QUERY_VAR );
}

/** Same trick as the hub: stop a parameterless rule reading as the home page. */
function fix_query( \WP_Query $q ): void {
	if ( ! $q->is_main_query() || ! $q->get( QUERY_VAR ) ) {
		return;
	}
	$q->is_home       = false;
	$q->is_front_page = false;
	$q->is_archive    = false;
	$q->is_singular   = false;
	$q->is_404        = false;
	$q->set( 'posts_per_page', 1 );
}

function template( string $template ): string {
	if ( ! is_page() ) {
		return $template;
	}
	$found = locate_template( array( 'oria-saved.php' ) );
	return $found ? $found : $template;
}

function title( $title ) {
	return is_page() ? __( 'Your saved practices | Oria Haven', 'oria' ) : $title;
}

function core_title( array $parts ): array {
	if ( is_page() ) {
		$parts['title'] = __( 'Your saved practices', 'oria' );
	}
	return $parts;
}

/**
 * Never indexed.
 *
 * Not a privacy measure — nothing here reaches the server to be private
 * about. It is that the page is empty to anyone who is not the person who
 * saved things, so there is nothing for a crawler to keep.
 */
function robots( $robots ) {
	return is_page() ? 'noindex, follow' : $robots;
}

bootstrap();
