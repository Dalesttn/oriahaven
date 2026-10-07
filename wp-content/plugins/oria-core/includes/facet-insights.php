<?php
/**
 * Facet insights: the "Oria insights" panel -- figures we worked out
 * ourselves from prices and details checked on each venue's own website.
 *
 * Why it exists: a facet page that only lists places says nothing a search
 * engine can't find elsewhere. A typical price, the cheapest option north
 * and south of the river, how many places say their cabins are private --
 * computed from checked sources and dated -- is original information, and
 * it is the kind of short, factual line an answer engine can lift and cite.
 *
 * Rules the data file must keep (data/facet-insights.json):
 *   - every figure comes from a dated check of venues' own sites, never from
 *     a listing's generic price_from (that is what made the hero say a
 *     traditional sauna "typically" costs $20);
 *   - each panel states its basis -- how many venues, checked when;
 *   - nothing about what a session does to a body.
 *
 * A panel older than MAX_AGE_DAYS is not shown: an out-of-date price
 * presented as an insight is worse than no panel.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\FacetInsights;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAX_AGE_DAYS = 183;

/** The data file's facet entries. */
function all(): array {
	static $data = null;
	if ( null === $data ) {
		$data = array();
		$path = ORIA_CORE_DIR . 'data/facet-insights.json';
		if ( is_readable( $path ) ) {
			$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$data = is_array( $json['facets'] ?? null ) ? $json['facets'] : array();
		}
	}
	return $data;
}

/**
 * The entry for a facet in a city, or an empty array when there is none,
 * it belongs to another city, or it is too old to show.
 *
 * @param array<string, mixed>|null $facet PracticesIndex\facet()
 * @param array<string, mixed>|null $city  Cities\get()
 */
function entry( ?array $facet, ?array $city = null ): array {
	if ( ! $facet ) {
		return array();
	}
	$all = all();
	$e   = array();
	foreach ( array( (string) ( $facet['slug'] ?? '' ), (string) ( $facet['value'] ?? '' ) ) as $k ) {
		if ( '' !== $k && isset( $all[ $k ] ) && is_array( $all[ $k ] ) ) {
			$e = $all[ $k ];
			break;
		}
	}
	if ( ! $e || empty( $e['items'] ) ) {
		return array();
	}
	$want = (string) ( $e['city'] ?? 'perth' );
	$here = (string) ( $city['slug'] ?? 'perth' );
	if ( $want !== $here ) {
		return array();
	}
	$checked = strtotime( (string) ( $e['checked'] ?? '' ) );
	if ( ! $checked || ( time() - $checked ) > MAX_AGE_DAYS * DAY_IN_SECONDS ) {
		return array();
	}
	return $e;
}

/**
 * The checked typical price for the hero's snapshot line, replacing the
 * median of listings' generic starting prices on pages that have one.
 */
function snapshot_price( ?array $facet, ?array $city = null ): string {
	$e = entry( $facet, $city );
	return (string) ( $e['snapshot'] ?? '' );
}

/**
 * A note with listing names turned into links. The note is escaped first;
 * each name in `links` is linked at its first mention, and only when the
 * listing is published.
 *
 * @param array<string, string> $links name => listing slug
 */
function linked_note( string $note, array $links ): string {
	$html = esc_html( $note );
	foreach ( $links as $name => $slug ) {
		$post = get_page_by_path( (string) $slug, OBJECT, 'listing' );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			continue;
		}
		$needle = esc_html( (string) $name );
		$pos    = strpos( $html, $needle );
		if ( false === $pos ) {
			continue;
		}
		$a    = '<a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . $needle . '</a>';
		$html = substr_replace( $html, $a, $pos, strlen( $needle ) );
	}
	return $html;
}

/**
 * The panel's HTML, or '' when this facet has no current insights.
 *
 * @param string $name e.g. "Infrared Saunas in Perth"
 */
function render( ?array $facet, ?array $city, string $name ): string {
	$e = entry( $facet, $city );
	if ( ! $e ) {
		return '';
	}
	$checked = (int) strtotime( (string) $e['checked'] );
	$id      = 'xcInsights';

	$out  = '<section class="xc-insights" aria-labelledby="' . $id . '">';
	$out .= '<h2 class="h3 xc-insights__title" id="' . $id . '">';
	/* translators: %s: facet name, e.g. Infrared Saunas in Perth */
	$out .= esc_html( sprintf( __( 'Oria insights: %s', 'oria' ), $name ) ) . '</h2>';
	$out .= '<p class="xc-insights__basis">';
	$out .= esc_html(
		sprintf(
			/* translators: 1: date, 2: what was checked */
			__( 'Worked out by Oria Haven from %2$s, checked %1$s.', 'oria' ),
			date_i18n( 'j F Y', $checked ),
			(string) ( $e['basis'] ?? __( "each venue's own website", 'oria' ) )
		)
	);
	$out .= '</p><dl class="xc-insights__grid">';
	foreach ( (array) $e['items'] as $it ) {
		if ( empty( $it['label'] ) || empty( $it['value'] ) ) {
			continue;
		}
		$out .= '<div class="xc-insights__item"><dt>' . esc_html( (string) $it['label'] ) . '</dt>';
		$out .= '<dd><strong class="xc-insights__value">' . esc_html( (string) $it['value'] ) . '</strong>';
		if ( ! empty( $it['note'] ) ) {
			$out .= '<span class="xc-insights__note">' . linked_note( (string) $it['note'], (array) ( $it['links'] ?? array() ) ) . '</span>';
		}
		$out .= '</dd></div>';
	}
	$out .= '</dl></section>';
	return $out;
}
