<?php
/**
 * Amenities: what is in the building, declared by whoever claims the listing.
 *
 * Deliberately the thinnest module in the plugin. There is no importer, no
 * seeder and no keyword matcher, because there is no honest way to derive an
 * amenity from a website: "showers" appearing on a page is not the same as
 * showers existing, and a photograph of a towel rail is not a towel service.
 * The only source is the business ticking a box about its own premises.
 *
 * Which also fixes what an empty set means. On an unclaimed listing it means
 * nobody has been asked. It does NOT mean the amenity is absent, and nothing
 * here renders a "no" — an unticked box produces no output at all.
 *
 * @see data/amenities.json for the vocabulary and why step-free is not in it.
 */

declare(strict_types=1);

namespace Oria\Core\Amenities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DATA_FILE = 'data/amenities.json';
const FIELD     = 'amenities';

/**
 * The vocabulary, grouped, parsed once per request.
 *
 * @return list<array{id: string, label: string, amenities: list<array{slug: string, label: string}>}>
 */
function groups(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$cache = array();
	$path  = trailingslashit( ORIA_CORE_DIR ) . DATA_FILE;
	if ( ! is_readable( $path ) ) {
		return $cache;
	}

	$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( (array) ( $json['groups'] ?? array() ) as $group ) {
		$items = array();
		foreach ( (array) ( $group['amenities'] ?? array() ) as $row ) {
			$slug = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}
			$items[] = array( 'slug' => $slug, 'label' => (string) ( $row['label'] ?? $slug ) );
		}
		if ( ! $items ) {
			continue;
		}
		$cache[] = array(
			'id'        => sanitize_key( (string) ( $group['id'] ?? '' ) ),
			'label'     => (string) ( $group['label'] ?? '' ),
			'amenities' => $items,
		);
	}

	return $cache;
}

/**
 * Flat slug => label map, for the ACF choices and for labelling a saved value.
 *
 * @return array<string, string>
 */
function vocabulary(): array {
	static $flat = null;
	if ( null !== $flat ) {
		return $flat;
	}
	$flat = array();
	foreach ( groups() as $group ) {
		foreach ( $group['amenities'] as $row ) {
			$flat[ $row['slug'] ] = $row['label'];
		}
	}
	return $flat;
}

/**
 * What one listing has declared, grouped for display and filtered against the
 * vocabulary — so a slug retired from the JSON stops rendering rather than
 * appearing as a bare key.
 *
 * Items carry their slug as well as their label, because the template keys
 * its icons off the slug — presentation stays in the theme, the vocabulary
 * stays here, and neither has to know the other's business.
 *
 * @return list<array{label: string, items: list<array{slug: string, label: string}>}>
 *         Empty when nothing has been declared, which is the normal state for
 *         a seeded listing.
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
		foreach ( $group['amenities'] as $row ) {
			if ( isset( $saved[ $row['slug'] ] ) ) {
				$items[] = array( 'slug' => $row['slug'], 'label' => $row['label'] );
			}
		}
		if ( $items ) {
			$out[] = array( 'label' => $group['label'], 'items' => $items );
		}
	}

	return $out;
}

/** Has this listing declared anything at all? */
function has_any( int $post_id ): bool {
	return (bool) for_listing( $post_id );
}
