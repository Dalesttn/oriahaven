<?php
/**
 * Apps in the site search box.
 *
 * The header search builds its suggestions in the browser from data the
 * page already carries, so an app only appears there if it is in that
 * payload. This adds a second, small one: every published app as name,
 * URL and what it is for.
 *
 * Kept separate from the directory's index rather than merged into it.
 * The directory payload is rebuilt whenever a listing changes and is
 * already the largest thing on the page; apps change on a different
 * schedule, are a couple of kilobytes, and a reader typing "headspace"
 * should find the app whether they are on the home page or a listing.
 *
 * Nothing here decides ranking. The browser groups apps below the
 * specialties and categories a search is mostly for, because somebody
 * typing "meditation" on a Perth directory almost always wants the
 * classes first.
 */

declare(strict_types=1);

namespace Oria\Apps\Search;

use Oria\Apps\Data;
use Oria\Apps\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KEY = 'oria_apps_search_v1';

function bootstrap(): void {
	// After the theme's own payload at 20, so the script is enqueued.
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\payload', 21 );
	add_action( 'save_post_' . Data\CPT, __NAMESPACE__ . '\flush' );
	add_action( 'deleted_post', __NAMESPACE__ . '\flush' );
}

function payload(): void {
	if ( ! wp_script_is( 'oria-app', 'enqueued' ) ) {
		return;
	}
	$rows = index();
	if ( ! $rows ) {
		return;
	}
	wp_add_inline_script(
		'oria-app',
		// A list, always: PHP encodes an array as an object the moment its
		// keys stop being sequential, and the search code has been taken
		// down by exactly that before.
		'window.ORIA_APPS = ' . wp_json_encode( array_values( $rows ) ) . ';',
		'before'
	);
}

/**
 * Name, URL and a one-line "what for" per app.
 *
 * @return list<array{name:string, url:string, sub:string}>
 */
function index(): array {
	$cached = get_transient( KEY );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$rows = array();
	foreach ( Engine\apps() as $app ) {
		// The categories, not the tagline: three words a reader can match
		// against what they typed, where a tagline is a sentence.
		$sub = implode( ' · ', array_slice( (array) $app['cat_names'], 0, 3 ) );
		$rows[] = array(
			'name' => (string) $app['title'],
			'url'  => (string) $app['url'],
			'sub'  => '' !== $sub ? $sub : __( 'Wellness app', 'oria' ),
		);
	}

	set_transient( KEY, $rows, 12 * HOUR_IN_SECONDS );
	return $rows;
}

function flush(): void {
	delete_transient( KEY );
}
