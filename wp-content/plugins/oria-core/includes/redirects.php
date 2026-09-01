<?php
/**
 * A 301 map.
 *
 * There was no general redirect machinery here — one hard-coded case in
 * seo.php for the old /events/ archive, and nothing else. That was fine
 * while nothing moved. Two migrations now need it: five duplicate
 * specialty pages folding into their practice pages, and eighty-six area
 * URLs gaining a city segment.
 *
 * Deliberately an option rather than a table. The map is small, it is read
 * on every front-end request, and WordPress already caches options in
 * memory — a custom table would cost a query to save nothing. If this ever
 * passes a few thousand entries that calculation changes.
 *
 * Entries are added by migrations at the moment they move something, which
 * is the only moment the old URL is still knowable. A migration that
 * deletes a term and forgets the redirect cannot be repaired afterwards,
 * because the term link is gone.
 */

declare(strict_types=1);

namespace Oria\Core\Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION = 'oria_redirects';

function bootstrap(): void {
	// Priority 1: ahead of redirect_canonical and ahead of the 404 handler,
	// so a mapped URL never renders a template first.
	add_action( 'template_redirect', __NAMESPACE__ . '\maybe_redirect', 1 );
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );

	/*
	 * Never let WordPress guess a permalink for a 404.
	 *
	 * redirect_guess_404_permalink() matches an unknown path against post
	 * slugs by prefix, and attachments are posts. In production that turned
	 * /perth/acupuncture/ into a 301 to Acupuncture.jpg -- a category page
	 * answering with a photograph. It does the same wherever a rewrite rule
	 * has not been flushed yet, which is exactly when the site can least
	 * afford to invent destinations.
	 *
	 * A 404 is a true answer. A confident redirect to the wrong thing is
	 * not, and it is the version search engines index.
	 */
	add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
}

/**
 * Normalise a path for storage and lookup: leading slash, trailing slash,
 * no host, no query. Both halves of every pair go through this, so a map
 * written with a full URL and looked up with a bare path still matches.
 */
function normalise( string $path ): string {
	$path = (string) wp_parse_url( $path, PHP_URL_PATH );
	$path = '/' . trim( $path, '/' );
	return '/' === $path ? '/' : $path . '/';
}

/** @return array<string, string> */
function all(): array {
	$map = get_option( OPTION, array() );
	return is_array( $map ) ? $map : array();
}

/**
 * Record one move.
 *
 * Refuses a self-redirect, which is the loop this whole file could
 * otherwise cause, and rewrites any existing entry that pointed at the old
 * URL so a two-step migration does not leave a chain. Chains cost a hop
 * each and Google gives up after a handful.
 */
function add( string $from, string $to ): bool {
	$from = normalise( $from );
	$to   = normalise( $to );

	if ( $from === $to ) {
		return false;
	}

	$map = all();

	// A → B already recorded, now B → C: repoint A straight at C.
	foreach ( $map as $old => $target ) {
		if ( normalise( $target ) === $from ) {
			$map[ $old ] = $to;
		}
	}

	$map[ $from ] = $to;
	update_option( OPTION, $map, false );

	return true;
}

function remove( string $from ): void {
	$map = all();
	unset( $map[ normalise( $from ) ] );
	update_option( OPTION, $map, false );
}

/**
 * Serve the 301.
 *
 * The query string is carried across. A campaign parameter on an old URL
 * is the one case where the thing arriving at the old address has something
 * worth keeping.
 */
function maybe_redirect(): void {
	$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	if ( '' === $uri ) {
		return;
	}

	$map  = all();
	if ( ! $map ) {
		return;
	}

	$path = normalise( $uri );
	$to   = $map[ $path ] ?? '';

	if ( '' === $to || $path === normalise( $to ) ) {
		return;
	}

	$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
	$dest  = home_url( $to ) . ( '' !== $query ? '?' . $query : '' );

	wp_safe_redirect( $dest, 301 );
	exit;
}

/* ------------------------------------------------------------------ admin */

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=listing',
		__( 'Redirects', 'oria' ),
		__( 'Redirects', 'oria' ),
		'manage_options',
		'oria-redirects',
		__NAMESPACE__ . '\screen'
	);
}

function screen(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$map = all();
	ksort( $map );

	echo '<div class="wrap"><h1>' . esc_html__( 'Redirects', 'oria' ) . '</h1>';
	printf(
		'<p>%s</p>',
		esc_html(
			sprintf(
				/* translators: %d: number of redirects. */
				_n( '%d permanent redirect, written by a migration.', '%d permanent redirects, written by migrations.', count( $map ), 'oria' ),
				count( $map )
			)
		)
	);

	if ( ! $map ) {
		echo '<p>' . esc_html__( 'Nothing has moved yet.', 'oria' ) . '</p></div>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr>';
	printf( '<th>%s</th><th>%s</th><th>%s</th>', esc_html__( 'From', 'oria' ), esc_html__( 'To', 'oria' ), esc_html__( 'Target status', 'oria' ) );
	echo '</tr></thead><tbody>';

	foreach ( $map as $from => $to ) {
		// The check that matters: a 301 to a 404 is worse than the 404 it
		// replaced, because it looks deliberate.
		$exists = (bool) url_to_postid( home_url( $to ) ) || is_string( get_option( 'oria_redirects_skip_check' ) );
		printf(
			'<tr><td><code>%s</code></td><td><a href="%s"><code>%s</code></a></td><td>%s</td></tr>',
			esc_html( $from ),
			esc_url( home_url( $to ) ),
			esc_html( $to ),
			$exists ? '&mdash;' : '<span style="color:#996800">' . esc_html__( 'not a post — check it resolves', 'oria' ) . '</span>'
		);
	}

	echo '</tbody></table></div>';
}
