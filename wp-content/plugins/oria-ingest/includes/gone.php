<?php
/**
 * What happens to an event's URL after the event is over.
 *
 * The expiry sweep hard-deletes aggregated events once their date has
 * passed, which is right — a directory full of finished events is worse
 * than useless. But the URL has usually been crawled by then, and a
 * deleted post leaves a bare 404. Google treats a 404 as "maybe this
 * comes back" and keeps requesting it for weeks.
 *
 * A 410 says the opposite: gone deliberately, stop asking. It drops out
 * of the index faster and stops eating crawl budget that should be
 * spent on listings.
 *
 * A blanket redirect to the events archive was the other option and is
 * the wrong one. Redirecting many unrelated URLs onto a single page is
 * the textbook soft-404 pattern, and it lies to somebody who followed a
 * link to a specific Tuesday evening in August.
 *
 * So: 410 status, and a page that says what happened and where to go
 * next. Correct for a crawler, useful for a person.
 */

declare(strict_types=1);

namespace Oria\Ingest\Gone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION = 'oria_ingest_gone_slugs';

/**
 * How many retired slugs to remember. Once a slug has been out of the
 * index for months the 410 has done its work, and the option should not
 * grow without limit — it is autoloaded on every request.
 */
const KEEP = 400;

function bootstrap(): void {
	add_action( 'template_redirect', __NAMESPACE__ . '\maybe_gone' );
}

/** Remember an event slug at the moment the sweep deletes it. */
function remember( string $slug ): void {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return;
	}
	$slugs = (array) get_option( OPTION, array() );
	// Newest first, so the trim below drops the oldest.
	array_unshift( $slugs, $slug );
	$slugs = array_slice( array_values( array_unique( $slugs ) ), 0, KEEP );
	update_option( OPTION, $slugs, false );
}

/**
 * A request for a retired event: send 410 and render an explanation.
 *
 * Only fires on a genuine 404 — an event that still exists is served
 * normally, and a slug we have never seen keeps the ordinary 404.
 */
function maybe_gone(): void {
	if ( ! is_404() ) {
		return;
	}
	$slug = requested_event_slug();
	if ( '' === $slug || ! in_array( $slug, (array) get_option( OPTION, array() ), true ) ) {
		return;
	}

	status_header( 410 );
	nocache_headers();
	add_filter( 'wp_robots', __NAMESPACE__ . '\noindex' );

	$template = locate_template( array( 'oria-event-gone.php' ) );
	if ( $template ) {
		include $template;
		exit;
	}
}

/**
 * The slug of the event being asked for, or ''.
 *
 * Read from the path rather than the query vars: on a 404 WordPress has
 * already given up on resolving the request, so `event` is not set.
 */
function requested_event_slug(): string {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! preg_match( '~/events/([^/]+)/?$~', $path, $m ) ) {
		return '';
	}
	return sanitize_title( rawurldecode( $m[1] ) );
}

/**
 * @param array<string, bool> $robots
 * @return array<string, bool>
 */
function noindex( array $robots ): array {
	$robots['noindex'] = true;
	return $robots;
}
