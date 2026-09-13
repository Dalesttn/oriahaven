<?php
/**
 * Apply corrections a business has asked for, from data/listing-edits.json.
 *
 * Owners write in -- "we no longer offer Pilates", "the price is wrong" --
 * and most of them have not claimed their listing, so they cannot make the
 * change themselves. This makes the edit reviewable before it happens: the
 * request lives in a file under version control, with who asked and when,
 * and the script shows each field's value before and after.
 *
 * Supported per listing: excerpt (the description), ACF fields, the services
 * list, and taxonomy terms to add or remove. Listings are found by slug.
 *
 * SAFETY
 *   Dry run is the default and writes nothing. --apply writes.
 *
 * USAGE (from the WordPress root, on the server)
 *     php wp-content/plugins/oria-core/tools/apply-listing-edits.php
 *     php wp-content/plugins/oria-core/tools/apply-listing-edits.php --apply
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

$apply = in_array( '--apply', $argv ?? array(), true );
$file  = ORIA_CORE_DIR . 'data/listing-edits.json';
$edits = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;

if ( ! is_array( $edits ) ) {
	fwrite( STDERR, "Cannot read {$file}\n" );
	exit( 1 );
}

$show = static function ( $v ): string {
	if ( is_array( $v ) ) {
		return '[' . implode( ', ', array_map( 'strval', $v ) ) . ']';
	}
	$v = (string) $v;
	return '' === $v ? '(empty)' : ( strlen( $v ) > 90 ? substr( $v, 0, 90 ) . '…' : $v );
};

foreach ( $edits as $e ) {
	$slug = (string) ( $e['slug'] ?? '' );
	$post = '' !== $slug ? get_page_by_path( $slug, OBJECT, 'listing' ) : null;
	if ( ! $post instanceof WP_Post ) {
		printf( "NOT FOUND  %s\n\n", $slug );
		continue;
	}
	printf( "%s  (%s)\n", $post->post_title, $slug );
	if ( ! empty( $e['requested_by'] ) ) {
		printf( "  requested by: %s\n", $e['requested_by'] );
	}

	// --- description ------------------------------------------------------
	if ( isset( $e['excerpt'] ) && (string) $e['excerpt'] !== $post->post_excerpt ) {
		printf( "  description\n    before: %s\n    after:  %s\n", $show( $post->post_excerpt ), $show( $e['excerpt'] ) );
		if ( $apply ) {
			wp_update_post( array( 'ID' => $post->ID, 'post_excerpt' => (string) $e['excerpt'] ) );
		}
	}

	// --- ACF fields -------------------------------------------------------
	foreach ( (array) ( $e['fields'] ?? array() ) as $name => $value ) {
		$before = get_field( $name, $post->ID );
		if ( (string) $before === (string) $value ) {
			continue;
		}
		printf( "  %-12s %s -> %s\n", $name, $show( $before ), $show( $value ) );
		if ( $apply ) {
			update_field( $name, $value, $post->ID );
		}
	}

	// --- services list ----------------------------------------------------
	if ( isset( $e['services'] ) ) {
		$before = array();
		foreach ( (array) get_field( 'services', $post->ID ) as $row ) {
			$before[] = (string) ( $row['name'] ?? '' );
		}
		$after = array_values( array_map( 'strval', (array) $e['services'] ) );
		if ( $before !== $after ) {
			printf( "  services     %s -> %s\n", $show( $before ), $show( $after ) );
			if ( $apply ) {
				update_field( 'services', array_map( static fn( $n ) => array( 'name' => $n ), $after ), $post->ID );
			}
		}
	}

	// --- taxonomy terms ---------------------------------------------------
	foreach ( (array) ( $e['terms'] ?? array() ) as $tax => $ops ) {
		$have = wp_get_post_terms( $post->ID, $tax, array( 'fields' => 'slugs' ) );
		$have = is_wp_error( $have ) ? array() : $have;

		$remove = array_values( array_intersect( (array) ( $ops['remove'] ?? array() ), $have ) );
		$add    = array_values( array_diff( (array) ( $ops['add'] ?? array() ), $have ) );

		if ( $remove ) {
			printf( "  %-12s remove %s\n", $tax, $show( $remove ) );
			if ( $apply ) {
				wp_remove_object_terms( $post->ID, $remove, $tax );
			}
		}
		if ( $add ) {
			printf( "  %-12s add    %s\n", $tax, $show( $add ) );
			if ( $apply ) {
				wp_set_object_terms( $post->ID, $add, $tax, true );
			}
		}
	}

	if ( $apply ) {
		clean_post_cache( $post->ID );
	}
	echo "\n";
}

echo $apply
	? "Done. Purge the page cache: wp litespeed-purge all\n"
	: "Dry run: nothing written. Re-run with --apply to write.\n";
