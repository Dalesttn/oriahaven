<?php
/**
 * Fill in street addresses for listings that have none.
 *
 * The addresses were found on each business's own website and matched on
 * street, WA postcode and the listing's own suburb before being written into
 * data/address-fixes.json. This script only ever FILLS an empty address: a
 * listing that already has one -- because an owner or an editor typed it in
 * since the data was gathered -- is left exactly as it is.
 *
 * Listings are found by slug, not post ID, so the file is correct on any copy
 * of the site whatever its IDs happen to be.
 *
 * SAFETY
 *   Dry run is the default: it reports what it would change and writes
 *   nothing. --apply writes.
 *
 * USAGE (from the WordPress root, on the server)
 *     php wp-content/plugins/oria-core/tools/apply-addresses.php
 *     php wp-content/plugins/oria-core/tools/apply-addresses.php --apply
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

$apply = in_array( '--apply', $argv ?? array(), true );
$file  = ORIA_CORE_DIR . 'data/address-fixes.json';
$rows  = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;

if ( ! is_array( $rows ) ) {
	fwrite( STDERR, "Cannot read {$file}\n" );
	exit( 1 );
}

$tally = array( 'filled' => 0, 'would fill' => 0, 'already has one' => 0, 'not found' => 0 );

foreach ( $rows as $r ) {
	$slug    = (string) ( $r['slug'] ?? '' );
	$address = trim( (string) ( $r['address'] ?? '' ) );
	$post    = '' !== $slug ? get_page_by_path( $slug, OBJECT, 'listing' ) : null;

	if ( ! $post instanceof WP_Post || '' === $address ) {
		$tally['not found']++;
		printf( "  not found      %s\n", $slug ?: (string) ( $r['business'] ?? '?' ) );
		continue;
	}

	$current = trim( (string) get_post_meta( $post->ID, 'address', true ) );
	if ( '' !== $current ) {
		$tally['already has one']++;
		printf( "  kept           %-38s %s\n", $post->post_title, $current );
		continue;
	}

	if ( $apply ) {
		// update_field rather than update_post_meta, so ACF's field reference
		// is written too and the edit screen shows the value in its box.
		if ( function_exists( 'update_field' ) ) {
			update_field( 'address', $address, $post->ID );
		} else {
			update_post_meta( $post->ID, 'address', $address );
		}
		clean_post_cache( $post->ID );
		$tally['filled']++;
		printf( "  filled         %-38s %s\n", $post->post_title, $address );
	} else {
		$tally['would fill']++;
		printf( "  would fill     %-38s %s\n", $post->post_title, $address );
	}
}

echo "\n";
foreach ( $tally as $k => $v ) {
	if ( $v ) {
		printf( "%-16s %d\n", $k, $v );
	}
}
echo $apply
	? "\nDone. Purge the page cache so listings show their address: wp litespeed-purge all\n"
	: "\nDry run: nothing written. Re-run with --apply to write.\n";
