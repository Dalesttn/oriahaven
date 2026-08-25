<?php
/**
 * /compare/ — put two to four wellness experiences side by side.
 *
 * The engine is a registry, not a taxonomy query: data/compare.json holds
 * a curated list of comparable experiences with scored attributes, because
 * the comparable units cut across the site's own structure — "yoga vs
 * pilates" is one practice term, "float vs sauna" is two specialties.
 * A registry can name whatever a person actually weighs up.
 *
 * Every attribute in that file describes the room, never the outcome:
 * intensity, guidance, group size, price — things a visitor could verify
 * by standing there. "Stress relief" and "mental wellbeing" scores are
 * deliberately absent; a scored table is a therapeutic claim in its most
 * quotable form, and this site does not make therapeutic claims.
 *
 * Selection travels as ?with=yoga,pilates so a comparison is shareable,
 * and the page canonicals to /compare/ so the variants never compete
 * with it. Built as a route, like the hub: it ships in git and exists on
 * production the moment the code lands.
 */

declare(strict_types=1);

namespace Oria\Core\Compare;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_compare';
const PATH      = 'compare';
const DATA_FILE = 'data/compare.json';
const MIN_PICK  = 2;
const MAX_PICK  = 4;
const REWRITE_V = '1';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 10 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 20 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );
	add_action( 'template_redirect', __NAMESPACE__ . '\bridge', 5 );

	add_filter( 'wpseo_title', __NAMESPACE__ . '\title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description' );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );
}

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QUERY_VAR . '=1', 'top' );
}

function maybe_flush(): void {
	if ( get_option( 'oria_compare_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_compare_rewrite_v', REWRITE_V );
	}
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

function is_compare(): bool {
	return (bool) get_query_var( QUERY_VAR );
}

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

/**
 * The no-JS picker submits pick[] checkboxes; the engine reads ?with=.
 * Bridge one to the other with a redirect, here rather than in the
 * template because by template time the headers have gone out.
 */
function bridge(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	if ( ! is_compare() || ! isset( $_GET['pick'] ) || ! is_array( $_GET['pick'] ) || isset( $_GET['with'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each id is sanitize_title()d.
	$ids = implode( ',', array_filter( array_map( 'sanitize_title', array_slice( (array) wp_unslash( $_GET['pick'] ), 0, MAX_PICK ) ) ) );
	wp_safe_redirect( home_url( '/' . PATH . '/' . ( '' !== $ids ? '?with=' . rawurlencode( $ids ) . '#result' : '' ) ) );
	exit;
}

function template( string $template ): string {
	if ( ! is_compare() ) {
		return $template;
	}
	$found = locate_template( array( 'oria-compare.php' ) );
	return $found ?: $template;
}

/* ------------------------------------------------------------------- data */

/**
 * The registry, parsed once per request.
 *
 * @return array{attributes: array, experiences: array}
 */
function registry(): array {
	static $data = null;
	if ( null !== $data ) {
		return $data;
	}
	$path = trailingslashit( ORIA_CORE_DIR ) . DATA_FILE;
	$raw  = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	$json = json_decode( $raw, true );
	$data = array(
		'attributes'  => is_array( $json['attributes'] ?? null ) ? $json['attributes'] : array(),
		'experiences' => is_array( $json['experiences'] ?? null ) ? $json['experiences'] : array(),
	);
	return $data;
}

/** @return array<int, array> every experience, in registry order */
function experiences(): array {
	return registry()['experiences'];
}

/** The attribute sections, in display order. */
function sections(): array {
	return registry()['attributes'];
}

/**
 * The experiences a request has picked, validated against the registry.
 *
 * Unknown ids are dropped rather than erroring — a stale shared link
 * degrades to whatever part of it still resolves. Order follows the
 * query string, so a shared comparison keeps its author's ordering.
 *
 * @return array<int, array>
 */
function picked(): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	$raw = isset( $_GET['with'] ) && is_string( $_GET['with'] ) ? sanitize_text_field( wp_unslash( $_GET['with'] ) ) : '';
	if ( '' === $raw ) {
		return array();
	}
	$by_id = array();
	foreach ( experiences() as $e ) {
		$by_id[ (string) $e['id'] ] = $e;
	}
	$out = array();
	foreach ( array_unique( array_filter( array_map( 'sanitize_title', explode( ',', $raw ) ) ) ) as $id ) {
		if ( isset( $by_id[ $id ] ) ) {
			$out[] = $by_id[ $id ];
		}
		if ( count( $out ) === MAX_PICK ) {
			break;
		}
	}
	return count( $out ) >= MIN_PICK ? $out : array();
}

/**
 * The comparison, summarised in the site's voice: which of the picked
 * experiences sits at each descriptive extreme. Derived entirely from the
 * registry — no outcomes, no recommendations, just where each one stands.
 *
 * @param array<int, array> $picked
 * @return array<int, string>
 */
function summary( array $picked ): array {
	if ( count( $picked ) < MIN_PICK ) {
		return array();
	}
	$lines = array();

	$by = static function ( string $key ) use ( $picked ): array {
		$sorted = $picked;
		usort( $sorted, static fn( array $a, array $b ): int => (int) ( $b['attributes'][ $key ] ?? 0 ) <=> (int) ( $a['attributes'][ $key ] ?? 0 ) );
		return $sorted;
	};

	$hard = $by( 'intensity' );
	if ( (int) $hard[0]['attributes']['intensity'] !== (int) end( $hard )['attributes']['intensity'] ) {
		$lines[] = sprintf(
			/* translators: 1: hardest experience, 2: gentlest experience */
			__( '%1$s asks the most of the body here; %2$s the least.', 'oria' ),
			$hard[0]['label'],
			end( $hard )['label']
		);
	}

	$quiet = $by( 'quiet' );
	if ( (int) $quiet[0]['attributes']['quiet'] !== (int) end( $quiet )['attributes']['quiet'] ) {
		$lines[] = sprintf(
			/* translators: %s: quietest experience */
			__( '%s is the quietest room of the set.', 'oria' ),
			$quiet[0]['label']
		);
	}

	$social = $by( 'social' );
	// "Alone" is only claimed at a score of 1 — a sound bath is quiet, but
	// a room of forty people lying down is not solitude.
	if ( (int) $social[0]['attributes']['social'] >= 3 && 1 === (int) end( $social )['attributes']['social'] ) {
		$lines[] = sprintf(
			/* translators: 1: most social experience, 2: most solitary experience */
			__( '%1$s is the most social; %2$s you do essentially alone.', 'oria' ),
			$social[0]['label'],
			end( $social )['label']
		);
	}

	// Only name a cheapest when there IS one: with three of four sharing a
	// band, crowning whichever sorted first would be an invented distinction.
	$band_of = static fn( array $e ): int => strlen( (string) ( $e['attributes']['price'] ?? '' ) );
	$bands   = array_map( $band_of, $picked );
	if ( count( array_unique( $bands ) ) > 1 ) {
		$min_n = count( array_keys( $bands, min( $bands ), true ) );
		$max_n = count( array_keys( $bands, max( $bands ), true ) );
		$cheap = $picked;
		usort( $cheap, static fn( array $a, array $b ): int => $band_of( $a ) <=> $band_of( $b ) );
		if ( 1 === $min_n && 1 === $max_n ) {
			$lines[] = sprintf(
				/* translators: 1: cheapest experience, 2: dearest experience */
				__( '%1$s is usually the cheapest way in; %2$s sits at the top of the range.', 'oria' ),
				$cheap[0]['label'],
				end( $cheap )['label']
			);
		} elseif ( 1 === $max_n ) {
			$lines[] = sprintf(
				/* translators: %s: dearest experience */
				__( '%s sits at the top of the price range here; the others are closer together.', 'oria' ),
				end( $cheap )['label']
			);
		}
	}

	return $lines;
}

/* -------------------------------------------------------------------- seo */

function title( $title ) {
	return is_compare() ? sprintf( 'Compare wellness experiences in Perth | %s', get_bloginfo( 'name' ) ) : $title;
}

function core_title( array $parts ): array {
	if ( is_compare() ) {
		$parts['title'] = __( 'Compare wellness experiences in Perth', 'oria' );
	}
	return $parts;
}

function description( $desc ) {
	if ( ! is_compare() ) {
		return $desc;
	}
	return sprintf(
		/* translators: %d: number of experiences in the registry */
		__( 'Put %d wellness experiences side by side — intensity, guidance, group size, price and time — then find who runs each one in Perth.', 'oria' ),
		count( experiences() )
	);
}

/**
 * Every ?with= variant canonicals to the bare page, the same rule the
 * directory applies to its filtered views: one indexable address, many
 * shareable states.
 */
function canonical( $url ) {
	return is_compare() ? home_url( '/' . PATH . '/' ) : $url;
}
