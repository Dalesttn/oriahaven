<?php
/**
 * "Why people come here" — the practice's own answer, ticked on claim.
 *
 * Deliberately the same shape as Oria\Core\Amenities, because it is the same
 * kind of thing: a controlled vocabulary that ships in git, a checkbox on the
 * listing editor, and a render that shows only what has been ticked. Two
 * registries rather than one because they answer different questions —
 * amenities are what is in the building, these are how the place runs — and
 * a reader scanning for "do they have showers" is not the reader scanning for
 * "can I just turn up".
 *
 * The vocabulary is verifiable by the business itself and says nothing about
 * outcomes. See the note in data/reasons.json for why that line is drawn
 * where it is.
 *
 * An empty set means nobody has been asked. It never means no.
 */

declare(strict_types=1);

namespace Oria\Core\Reasons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DATA_FILE = 'data/reasons.json';
const FIELD     = 'reasons';

/**
 * The registry, as groups.
 *
 * @return list<array{id: string, label: string, reasons: list<array{slug: string, label: string}>}>
 */
function groups(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$path = ORIA_CORE_DIR . DATA_FILE;
	if ( ! is_readable( $path ) ) {
		return $cache = array();
	}
	$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_array( $json ) || empty( $json['groups'] ) ) {
		return $cache = array();
	}

	$out = array();
	foreach ( (array) $json['groups'] as $group ) {
		$items = array();
		foreach ( (array) ( $group['reasons'] ?? array() ) as $row ) {
			if ( ! empty( $row['slug'] ) && ! empty( $row['label'] ) ) {
				$items[] = array(
					'slug'  => sanitize_key( (string) $row['slug'] ),
					'label' => (string) $row['label'],
				);
			}
		}
		if ( $items ) {
			$out[] = array(
				'id'      => sanitize_key( (string) ( $group['id'] ?? '' ) ),
				'label'   => (string) ( $group['label'] ?? '' ),
				'reasons' => $items,
			);
		}
	}
	return $cache = $out;
}

/**
 * slug => label, for the ACF checkbox.
 *
 * @return array<string, string>
 */
function vocabulary(): array {
	$out = array();
	foreach ( groups() as $group ) {
		foreach ( $group['reasons'] as $row ) {
			$out[ $row['slug'] ] = $row['label'];
		}
	}
	return $out;
}

/**
 * What this listing has declared, grouped for display.
 *
 * @return list<array{label: string, items: list<array{slug: string, label: string}>}>
 */
function for_listing( int $post_id ): array {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}

	$saved = (array) get_field( FIELD, $post_id );
	if ( ! $saved ) {
		return array();
	}
	$saved = array_flip( array_map( 'sanitize_key', array_map( 'strval', $saved ) ) );

	$out = array();
	foreach ( groups() as $group ) {
		$items = array();
		foreach ( $group['reasons'] as $row ) {
			if ( isset( $saved[ $row['slug'] ] ) ) {
				$items[] = $row;
			}
		}
		if ( $items ) {
			$out[] = array( 'label' => $group['label'], 'items' => $items );
		}
	}
	return $out;
}

/** A flat list, for a compact strip rather than grouped headings. */
function flat( int $post_id ): array {
	$out = array();
	foreach ( for_listing( $post_id ) as $group ) {
		foreach ( $group['items'] as $item ) {
			$out[] = $item;
		}
	}
	return $out;
}

/** Has this listing declared anything at all? */
function has_any( int $post_id ): bool {
	return (bool) for_listing( $post_id );
}
