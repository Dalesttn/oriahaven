<?php
/**
 * Facet guides: the words that make a facet page its own page.
 *
 * /explore/perth/spa/cold-plunge/, /spa/traditional-sauna/, /spa/steam-room/
 * and the rest all rendered the SAME category intro (369 words of Spa &
 * Recovery) and no FAQ of their own -- near-copies to Google, which is a
 * large part of why "cold plunge perth" and "sauna perth" sat on pages 5-8
 * (the 19 Sep 2026 audit). Seven specialty intros already written for the
 * retired /perth/{specialty}/ pages (remedial massage, Pilates, infrared
 * sauna, naturopathy, acupuncture, sound healing, retreats) were showing
 * nowhere.
 *
 * This module answers, for one facet:
 *   intro()    its own guide paragraphs -- data/facet-guides.json first,
 *              else the specialty intro for the same slug;
 *   faqs()     its own questions, with FAQPage markup via template-parts/faq;
 *   phrase()   the words the search result leads with ("Ice Baths & Cold
 *              Plunges"), for PracticesIndex\title();
 *   see_also() hand-picked neighbours ("Want both? Ice bath & sauna").
 *
 * Keyed by the facet's URL slug (the last path segment), then its term
 * slug. Same house rule as every other guide on the site: what a session
 * involves, what it costs, where -- never what it does to a body.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\FacetGuides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The data file's facet entries. */
function all(): array {
	static $data = null;
	if ( null === $data ) {
		$data = array();
		$path = ORIA_CORE_DIR . 'data/facet-guides.json';
		if ( is_readable( $path ) ) {
			$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$data = is_array( $json['facets'] ?? null ) ? $json['facets'] : array();
		}
	}
	return $data;
}

/**
 * The entry for a facet, or an empty array.
 *
 * @param array<string, mixed> $facet PracticesIndex\facet()
 */
function entry( array $facet ): array {
	$all = all();
	foreach ( array( (string) ( $facet['slug'] ?? '' ), (string) ( $facet['value'] ?? '' ) ) as $k ) {
		if ( '' !== $k && isset( $all[ $k ] ) && is_array( $all[ $k ] ) ) {
			return $all[ $k ];
		}
	}
	return array();
}

/** Only service and specialty facets have guides of their own. */
function applies( ?array $facet ): bool {
	return is_array( $facet ) && in_array( (string) ( $facet['key'] ?? '' ), array( 'svc', 'spec' ), true );
}

/**
 * The facet's own guide paragraphs, or none (the page then keeps the
 * category's intro).
 *
 * @return list<string>
 */
function intro( ?array $facet ): array {
	if ( ! applies( $facet ) ) {
		return array();
	}
	$paras = array_values( array_filter( array_map( 'strval', (array) ( entry( $facet )['intro'] ?? array() ) ), 'strlen' ) );
	if ( $paras ) {
		return $paras;
	}
	if ( function_exists( '\Oria\Core\Seo\specialty_intros' ) ) {
		$si = \Oria\Core\Seo\specialty_intros();
		foreach ( array( (string) ( $facet['value'] ?? '' ), (string) ( $facet['slug'] ?? '' ) ) as $k ) {
			if ( '' !== $k && ! empty( $si[ $k ] ) ) {
				return array_values( array_map( 'strval', (array) $si[ $k ] ) );
			}
		}
	}
	return array();
}

/**
 * The facet's own questions.
 *
 * @return list<array{q: string, a: string}>
 */
function faqs( ?array $facet ): array {
	if ( ! applies( $facet ) ) {
		return array();
	}
	$out = array();
	foreach ( (array) ( entry( $facet )['faq'] ?? array() ) as $qa ) {
		if ( is_array( $qa ) && ! empty( $qa['q'] ) && ! empty( $qa['a'] ) ) {
			$out[] = array( 'q' => (string) $qa['q'], 'a' => (string) $qa['a'] );
		}
	}
	return $out;
}

/** What the title leads with ("Ice Baths & Cold Plunges"), or ''. */
/**
 * The facet's own meta description, if one has been written for it.
 *
 * The generated fallback in PracticesIndex\description() is the same
 * sentence on every facet page -- "N practices checked by hand, with
 * timetables, prices and contact details" -- which says nothing a
 * searcher comparing infrared saunas wants to know, and reads oddly on a
 * facet with no timetable at all. A guide can write its own.
 *
 * {count} is filled with what the page actually shows, so the number in
 * the description never drifts from the number in the list.
 */
function description( ?array $facet, int $count = 0 ): string {
	if ( ! applies( $facet ) ) {
		return '';
	}

	$desc = trim( (string) ( entry( $facet )['description'] ?? '' ) );

	return '' === $desc ? '' : strtr( $desc, array( '{count}' => number_format_i18n( $count ) ) );
}

function phrase( ?array $facet ): string {
	return applies( $facet ) ? trim( (string) ( entry( $facet )['phrase'] ?? '' ) ) : '';
}

/**
 * Hand-picked neighbours, each a facet slug in a category, as links.
 *
 * @return list<array{url: string, label: string, line: string}>
 */
function see_also( ?array $facet, ?array $city = null ): array {
	if ( ! applies( $facet ) || ! function_exists( '\Oria\Core\PracticesIndex\category_url' ) ) {
		return array();
	}
	$out = array();
	foreach ( (array) ( entry( $facet )['see_also'] ?? array() ) as $s ) {
		$cat = get_term_by( 'slug', (string) ( $s['category'] ?? '' ), 'practice' );
		if ( ! $cat instanceof \WP_Term || empty( $s['facet'] ) || empty( $s['label'] ) ) {
			continue;
		}
		$out[] = array(
			'url'   => \Oria\Core\PracticesIndex\category_url( $cat, $city ) . sanitize_title( (string) $s['facet'] ) . '/',
			'label' => (string) $s['label'],
			'line'  => (string) ( $s['line'] ?? '' ),
		);
	}
	return $out;
}

/**
 * The facet pages a Best Of guide should point on to (data/best-of-browse.json),
 * as links. The template adds them to the guide's own "Keep exploring" list
 * where not already there: the saunas guide linked a noindexed page and
 * neither sauna page, the massage guides nothing past the category.
 *
 * @return list<array{url: string, label: string}>
 */
function best_of_links( string $guide ): array {
	static $data = null;
	if ( null === $data ) {
		$data = array();
		$path = ORIA_CORE_DIR . 'data/best-of-browse.json';
		if ( is_readable( $path ) ) {
			$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$data = is_array( $json['guides'] ?? null ) ? $json['guides'] : array();
		}
	}
	if ( ! function_exists( '\Oria\Core\PracticesIndex\category_url' ) ) {
		return array();
	}
	$out = array();
	foreach ( (array) ( $data[ $guide ] ?? array() ) as $l ) {
		$cat = get_term_by( 'slug', (string) ( $l['category'] ?? '' ), 'practice' );
		if ( $cat instanceof \WP_Term && ! empty( $l['facet'] ) && ! empty( $l['label'] ) ) {
			$out[] = array(
				'url'   => \Oria\Core\PracticesIndex\category_url( $cat ) . sanitize_title( (string) $l['facet'] ) . '/',
				'label' => (string) $l['label'],
			);
		}
	}
	return $out;
}
