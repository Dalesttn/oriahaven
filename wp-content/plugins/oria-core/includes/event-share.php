<?php
/**
 * The share kit for an event, at /events/{slug}/share/.
 *
 * The same argument as the listing kit, applied to the other thing this
 * site holds. Social buttons earn visits; they do not earn links, because
 * a share on Facebook is nofollowed and counts for nothing in search. The
 * piece that earns a link is the badge an organiser pastes on their own
 * site next to the event they are already promoting.
 *
 * Event organisers are a better source of that than practices, for a plain
 * reason: they are actively publicising something right now, and they need
 * somewhere to point people that is not a ticketing platform's checkout.
 *
 * Nothing here is a condition of being listed. An event goes up whether or
 * not anybody ever pastes a badge.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\EventShare;

use Oria\Core\Share;
use Oria\Core\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_event_share';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\maybe_render' );

	// An event with no photo of its own still shares as something better
	// than a bare link. One with a photo keeps its photo.
	add_filter( 'wpseo_frontend_presentation', __NAMESPACE__ . '\presentation_image', 20 );
	add_filter( 'wpseo_twitter_image', __NAMESPACE__ . '\twitter_image', 20 );
}

function route(): void {
	add_rewrite_rule( '^events/([^/]+)/share/?$', 'index.php?post_type=event&name=$matches[1]&' . QUERY_VAR . '=1', 'top' );
}

/** @param string[] $vars @return string[] */
function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

function is_share_page(): bool {
	return (bool) get_query_var( QUERY_VAR ) && is_singular( 'event' );
}

/** The share page for an event. */
function url( int $event_id ): string {
	return trailingslashit( (string) get_permalink( $event_id ) ) . 'share/';
}

function maybe_render(): void {
	if ( ! is_share_page() ) {
		return;
	}

	// A page of assets for one organiser is not a search result.
	add_filter( 'wpseo_robots', static fn(): string => 'noindex, follow' );
	add_filter(
		'wp_robots',
		static function ( array $r ): array {
			$r['noindex'] = true;
			return $r;
		}
	);

	$template = locate_template( array( 'oria-event-share.php' ) );
	if ( $template ) {
		include $template;
		exit;
	}
}

/* ------------------------------------------------------------------ card */

/**
 * The words on the generated card: the event, then when and where.
 *
 * @return array{name: string, meta: string}
 */
function card_text( int $event_id ): array {
	$start = strtotime( (string) get_post_meta( $event_id, 'event_start', true ) );
	$venue = (string) get_post_meta( $event_id, 'venue', true );

	// gmdate against a naive Perth string: see the note in Events. wp_date
	// would shift a 6pm class to 2am tomorrow.
	$when = $start ? gmdate( 'D j M', $start ) . ( '00:00' !== gmdate( 'H:i', $start ) ? ', ' . gmdate( 'g.ia', $start ) : '' ) : '';

	return array(
		'name' => wp_specialchars_decode( (string) get_post_field( 'post_title', $event_id, 'raw' ), ENT_QUOTES ),
		'meta' => trim( implode( '  ·  ', array_filter( array( $when, $venue ) ) ) ) ?: __( 'Perth, Western Australia', 'oria' ),
	);
}

/** The drawn card for an event, or '' when one cannot be made. */
function card_url( int $event_id ): string {
	if ( ! function_exists( '\Oria\Core\Share\generic_card_url' ) ) {
		return '';
	}
	return Share\generic_card_url( 'event-' . $event_id, card_text( $event_id ), __( "What's on in Perth", 'oria' ) );
}

/** The event's own photo if it has one, else the drawn card. */
function preview_url( int $event_id ): string {
	$photo = get_the_post_thumbnail_url( $event_id, 'large' );
	return $photo ? (string) $photo : card_url( $event_id );
}

/** @param mixed $presentation @return mixed */
function presentation_image( $presentation ) {
	if ( ! is_object( $presentation ) || ! is_singular( 'event' ) || ! empty( $presentation->open_graph_images ) ) {
		return $presentation;
	}
	$url = preview_url( (int) get_queried_object_id() );
	if ( '' === $url ) {
		return $presentation;
	}
	$presentation->open_graph_images = array( $url => array( 'url' => $url ) );
	return $presentation;
}

/** @param mixed $image @return mixed */
function twitter_image( $image ) {
	if ( ! is_singular( 'event' ) || $image ) {
		return $image;
	}
	return preview_url( (int) get_queried_object_id() ) ?: $image;
}

/* ------------------------------------------------------------ share copy */

/** Event link with campaign tags, so shared traffic is attributable. */
function tagged_url( int $event_id, string $medium ): string {
	return add_query_arg(
		array(
			'utm_source'   => 'organiser',
			'utm_medium'   => $medium,
			'utm_campaign' => 'event-share',
		),
		(string) get_permalink( $event_id )
	);
}

/** The post we write for them, so sharing costs nothing but a tap. */
function suggested_post( int $event_id ): string {
	$t = card_text( $event_id );
	return sprintf(
		/* translators: 1: event name, 2: when and where, 3: url */
		__( "%1\$s is on — %2\$s. Full details, and the rest of what's on around Perth, are on Oria Haven:\n\n%3\$s", 'oria' ),
		$t['name'],
		$t['meta'],
		(string) get_permalink( $event_id )
	);
}

/** @return array<string, string> label => share URL */
function share_links( int $event_id ): array {
	$t = card_text( $event_id );
	return array(
		'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( tagged_url( $event_id, 'facebook' ) ),
		'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( tagged_url( $event_id, 'linkedin' ) ),
		'WhatsApp' => 'https://wa.me/?text=' . rawurlencode( $t['name'] . ' — ' . $t['meta'] . ': ' . tagged_url( $event_id, 'whatsapp' ) ),
		'Email'    => 'mailto:?subject=' . rawurlencode( $t['name'] ) . '&body=' . rawurlencode( suggested_post( $event_id ) ),
	);
}

/** Is this event still worth promoting? */
function shareable( int $event_id ): bool {
	return ! Events\is_past( $event_id ) && 'cancelled' !== Events\status( $event_id );
}
