<?php
/**
 * Create a city's area tree (city > region > suburb) from
 * data/areas/<city>.json.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/area-tree-import.php melbourne          (dry run)
 *   wp eval-file wp-content/plugins/oria-core/tools/area-tree-import.php melbourne apply    (write)
 *
 * Idempotent: a term that already exists under the right parent is left
 * alone. A slug that exists somewhere else in the tree is NEVER moved or
 * renamed -- it is reported and skipped, because moving a term would move
 * every listing filed under it into another city.
 *
 * Creating the terms publishes nothing: the city's status in cities.json
 * decides whether any of its pages answer (draft = 404).
 *
 * @package Oria\Core
 */

$city  = sanitize_key( (string) ( $args[0] ?? '' ) );
$apply = 'apply' === (string) ( $args[1] ?? '' );
$file  = ORIA_CORE_DIR . 'data/areas/' . $city . '.json';
if ( '' === $city || ! is_readable( $file ) ) {
	WP_CLI::error( 'Usage: area-tree-import.php <city> [apply]; no data file at ' . $file );
}
$tree = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
if ( ! is_array( $tree ) || empty( $tree['root']['slug'] ) ) {
	WP_CLI::error( 'Data file has no root.' );
}
WP_CLI::log( ( $apply ? 'APPLY' : 'DRY RUN' ) . ' -- area tree for ' . $tree['root']['slug'] );

$n = array( 'created' => 0, 'unchanged' => 0, 'conflict' => 0 );

/**
 * Ensure one term under a parent. Returns its id, or 0 when it cannot be
 * placed (conflict, or a dry run that would create it under a new parent).
 */
$ensure = static function ( string $slug, string $name, int $parent, string $label ) use ( $apply, &$n ): int {
	$t = get_term_by( 'slug', $slug, 'area' );
	if ( $t instanceof WP_Term ) {
		if ( (int) $t->parent === $parent ) {
			++$n['unchanged'];
			return (int) $t->term_id;
		}
		$where = $t->parent ? get_term( (int) $t->parent, 'area' )->slug : '(top level)';
		WP_CLI::warning( "{$label} '{$slug}' already exists under {$where} -- not moved (it may be another city's). Rename the slug in the data file." );
		++$n['conflict'];
		return 0;
	}
	if ( ! $apply ) {
		WP_CLI::log( "  + would create {$label} '{$name}' ({$slug})" );
		++$n['created'];
		return -1; // a placeholder parent for the dry run's children
	}
	$r = wp_insert_term( $name, 'area', array( 'slug' => $slug, 'parent' => max( 0, $parent ) ) );
	if ( is_wp_error( $r ) ) {
		WP_CLI::warning( "{$label} '{$slug}': " . $r->get_error_message() );
		++$n['conflict'];
		return 0;
	}
	WP_CLI::log( "  + created {$label} '{$name}' ({$slug}) #" . (int) $r['term_id'] );
	++$n['created'];
	return (int) $r['term_id'];
};

$root = $ensure( (string) $tree['root']['slug'], (string) $tree['root']['name'], 0, 'city' );
foreach ( (array) ( $tree['regions'] ?? array() ) as $region ) {
	if ( 0 === $root ) {
		break;
	}
	$rid = $ensure( (string) $region['slug'], (string) $region['name'], $root, 'region' );
	if ( 0 === $rid ) {
		continue;
	}
	foreach ( (array) ( $region['suburbs'] ?? array() ) as $s ) {
		$ensure( (string) $s['slug'], (string) $s['name'], $rid, 'suburb' );
	}
}

if ( $apply && $n['created'] ) {
	delete_option( 'area_children' ); // WordPress's cached hierarchy
	clean_term_cache( array(), 'area' );
}

WP_CLI::success( sprintf( '%s: %d to create, %d already in place, %d conflicts.', $apply ? 'Applied' : 'Dry run', $n['created'], $n['unchanged'], $n['conflict'] ) );
