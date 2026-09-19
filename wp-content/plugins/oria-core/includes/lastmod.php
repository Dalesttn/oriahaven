<?php
/**
 * Sitemap lastmod that means something.
 *
 * The four sitemaps built in code -- facet, intent, explore, compare --
 * stamped every URL with gmdate( 'c' ): the moment the sitemap was
 * generated. All 300 facet pages "changed" on every build, which teaches
 * Google to ignore the field (Google's sitemap guidance: lastmod should
 * reflect the last significant change to the page).
 *
 * A URL's date is now the latest of:
 *   - the last edit to any listing it shows (post_modified_gmt);
 *   - the last time the SET of listings it shows changed -- a place added
 *     or removed -- which an edit date alone cannot see. A fingerprint of
 *     the listing ids is kept per URL (option oria_sitemap_sig), and the
 *     date moves only when the fingerprint does;
 *   - the modification time of any data file carrying the page's own
 *     words (facet-guides.json, intents.json, compare.json).
 * Nothing about a request, a view or a recount moves it.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Lastmod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION = 'oria_sitemap_sig';

/** Every listing's last-modified time, in one query. */
function listing_times(): array {
	static $map = null;
	if ( null === $map ) {
		global $wpdb;
		$map  = array();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_modified_gmt FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", 'listing' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $rows as $r ) {
			$map[ (int) $r['ID'] ] = (int) strtotime( $r['post_modified_gmt'] . ' UTC' );
		}
	}
	return $map;
}

/** The stored fingerprints, saved once at the end of the request if changed. */
function &sigs(): array {
	static $sig = null;
	if ( null === $sig ) {
		$sig = get_option( OPTION, array() );
		$sig = is_array( $sig ) ? $sig : array();
		add_action(
			'shutdown',
			static function () use ( &$sig ): void {
				if ( ! empty( $GLOBALS['oria_lastmod_dirty'] ) ) {
					update_option( OPTION, $sig, false );
				}
			}
		);
	}
	return $sig;
}

/**
 * The lastmod for one URL, ISO 8601.
 *
 * @param string       $loc   The URL.
 * @param list<int>    $ids   The listings the page shows.
 * @param list<string> $files Data files holding the page's own words.
 */
function for_url( string $loc, array $ids, array $files = array() ): string {
	$times = listing_times();
	$ids   = array_values( array_unique( array_map( 'intval', $ids ) ) );
	sort( $ids );

	$edited = 0;
	foreach ( $ids as $id ) {
		$edited = max( $edited, (int) ( $times[ $id ] ?? 0 ) );
	}

	$sig  = &sigs();
	$hash = md5( implode( ',', $ids ) );
	if ( ! isset( $sig[ $loc ] ) || ! is_array( $sig[ $loc ] ) ) {
		// First sighting: date it from its listings, not from today.
		$sig[ $loc ]                    = array( $hash, $edited );
		$GLOBALS['oria_lastmod_dirty'] = true;
	} elseif ( $sig[ $loc ][0] !== $hash ) {
		// The set changed -- a place added or removed -- so the page did.
		$sig[ $loc ]                    = array( $hash, time() );
		$GLOBALS['oria_lastmod_dirty'] = true;
	}

	$t = max( $edited, (int) $sig[ $loc ][1] );
	foreach ( $files as $f ) {
		if ( is_readable( $f ) ) {
			$t = max( $t, (int) filemtime( $f ) );
		}
	}
	return gmdate( 'c', $t > 0 ? $t : time() );
}

/**
 * The newest date among a sitemap's entries, for its line in the index.
 *
 * @param list<array{loc: string, mod?: string}> $entries
 */
function newest( array $entries ): string {
	$t = 0;
	foreach ( $entries as $e ) {
		$t = max( $t, (int) strtotime( (string) ( $e['mod'] ?? '' ) ) );
	}
	return gmdate( 'c', $t > 0 ? $t : time() );
}
