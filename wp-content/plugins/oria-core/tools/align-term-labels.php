<?php
/**
 * Give mismatched specialty/service twins one shared label (Phase 4,
 * approved by Dale 13 Oct 2026).
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/align-term-labels.php            # dry run
 *   wp eval-file wp-content/plugins/oria-core/tools/align-term-labels.php apply      # write
 *   wp eval-file wp-content/plugins/oria-core/tools/align-term-labels.php rollback   # restore the old names
 *
 * Changes the display NAME only. Slugs, URLs, listing tags and indexed pages
 * are untouched, so nothing needs a redirect. Once a pair shares a name the
 * treatment filter shows one checkbox for it and matches either term
 * (app.js TWIN_SPEC / TWIN_SVC). Every old name is saved to
 * uploads/oria-rollback/ before writing; a rerun changes nothing.
 *
 * Kept separate on purpose (genuinely different): Hiking & bushwalking vs
 * Bushwalking, Dance & movement vs Dance, Midwifery vs Private midwifery,
 * Women's health vs Women's health physiotherapy.
 *
 * @package Oria\Core
 */

defined( 'ABSPATH' ) || exit;

// taxonomy => slug => the shared label.
const ORIA_TERM_LABELS = array(
	'specialty' => array(
		'hyperbaric-oxygen' => 'Hyperbaric oxygen therapy',
		'chinese-medicine'  => 'Traditional Chinese medicine',
		'doula'             => 'Doula support',
		'lactation'         => 'Lactation consulting',
		'support-groups'    => 'Support groups',
		'steam-room'        => 'Steam room',
	),
	'service'   => array(
		'ice-bath'          => 'Ice bath & cold plunge',
		'tai-chi'           => 'Tai chi & qigong',
		'mobility-training' => 'Mobility coaching',
	),
);

$mode = (string) ( $args[0] ?? '' );
$dir  = trailingslashit( wp_upload_dir()['basedir'] ) . 'oria-rollback/';

if ( 'rollback' === $mode ) {
	$files = glob( $dir . 'term-labels-*.json' ) ?: array();
	rsort( $files );
	if ( ! $files ) {
		WP_CLI::error( 'No term-labels rollback file in ' . $dir );
	}
	$old = json_decode( (string) file_get_contents( $files[0] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( (array) $old as $row ) {
		wp_update_term( (int) $row['term_id'], (string) $row['taxonomy'], array( 'name' => (string) $row['name'] ) );
		WP_CLI::log( sprintf( '  restored %s:%s -> %s', $row['taxonomy'], $row['slug'], $row['name'] ) );
	}
	WP_CLI::success( 'Rolled back from ' . basename( $files[0] ) );
	return;
}

$apply   = 'apply' === $mode;
$changes = array();
foreach ( ORIA_TERM_LABELS as $tax => $map ) {
	foreach ( $map as $slug => $label ) {
		$t = get_term_by( 'slug', $slug, $tax );
		if ( ! $t instanceof WP_Term ) {
			WP_CLI::warning( "{$tax}:{$slug} not found -- skipped" );
			continue;
		}
		$now = html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
		if ( $now === $label ) {
			WP_CLI::log( "  = {$tax}:{$slug} already \"{$label}\"" );
			continue;
		}
		WP_CLI::log( sprintf( '  %s %s:%s  "%s" -> "%s"', $apply ? '~' : 'would', $tax, $slug, $now, $label ) );
		$changes[] = array( 'term_id' => (int) $t->term_id, 'taxonomy' => $tax, 'slug' => $slug, 'name' => $now, 'to' => $label );
	}
}

if ( ! $changes ) {
	WP_CLI::success( 'Nothing to change.' );
	return;
}
if ( ! $apply ) {
	WP_CLI::success( count( $changes ) . ' label(s) would change. Run with "apply" to write.' );
	return;
}

wp_mkdir_p( $dir );
$file = $dir . 'term-labels-' . gmdate( 'Ymd-His' ) . '.json';
file_put_contents( $file, wp_json_encode( $changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
foreach ( $changes as $c ) {
	$r = wp_update_term( $c['term_id'], $c['taxonomy'], array( 'name' => $c['to'] ) ); // slug untouched: only 'name' is passed
	if ( is_wp_error( $r ) ) {
		WP_CLI::warning( "{$c['taxonomy']}:{$c['slug']}: " . $r->get_error_message() );
	}
}
WP_CLI::log( 'Rollback record: ' . $file );
WP_CLI::success( count( $changes ) . ' label(s) aligned. Purge the cache: wp litespeed-purge all' );
