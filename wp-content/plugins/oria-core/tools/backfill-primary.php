<?php
/**
 * Fill in each listing's primary category.
 *
 * Usage (from the WordPress root, on the server):
 *   php wp-content/plugins/oria-core/tools/backfill-primary.php            # report only
 *   php wp-content/plugins/oria-core/tools/backfill-primary.php --apply    # and save
 *
 * The site never stored a listing's primary category -- see
 * includes/primary.php -- so every listing filed under two or more
 * categories needs one. This works each out the way Primary\infer() does:
 * from the practice's own name, then from its services.
 *
 * Only confident answers are saved. A guess (neither the name nor the
 * services decide it) is listed and left empty: the site still works one
 * out live, but it stays visibly a guess in the listing's "Primary
 * category" box, where somebody who knows the place can set it. Saving it
 * would freeze a guess as though it had been checked.
 *
 * A value somebody has already set is never touched.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

use Oria\Core\PostTypes;
use Oria\Core\Primary;

$apply = in_array( '--apply', $argv ?? array(), true );

$ids = get_posts(
	array(
		'post_type'      => PostTypes\LISTING,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
if ( function_exists( '\Oria\Theme\prime_listing_terms' ) ) {
	\Oria\Theme\prime_listing_terms( $ids );
}

echo "\n" . ( $apply ? "APPLY -- confident answers are saved.\n" : "DRY RUN -- nothing is saved. Add --apply to save.\n" );
echo str_repeat( '=', 76 ) . "\n";

$by      = array( 'name' => array(), 'services' => array(), 'guess' => array() );
$kept    = 0;
$single  = 0;
$stale   = array();
foreach ( $ids as $id ) {
	$id     = (int) $id;
	$slugs  = Primary\slugs( $id );
	$stored = (string) get_post_meta( $id, Primary\META, true );
	if ( count( $slugs ) < 2 ) {
		++$single;
		continue;
	}
	if ( '' !== $stored ) {
		if ( in_array( $stored, $slugs, true ) ) {
			++$kept;
			continue;
		}
		$stale[] = sprintf( '%s: set to "%s", which it is no longer filed under', get_the_title( $id ), $stored );
	}
	$r               = Primary\infer( $id, $slugs );
	$by[ $r['why'] ][] = array( 'id' => $id, 'slug' => $r['slug'], 'all' => $slugs );
}

printf(
	"%d listings. %d have one category (nothing to decide), %d already have a primary set.\n\n",
	count( $ids ),
	$single,
	$kept
);

foreach ( array( 'name' => 'From the name', 'services' => 'From its services' ) as $why => $label ) {
	printf( "%s  (%d) -- saved\n", strtoupper( $label ), count( $by[ $why ] ) );
	foreach ( array_slice( $by[ $why ], 0, 12 ) as $r ) {
		printf( "  %-40s %-12s of %s\n", mb_substr( html_entity_decode( get_the_title( $r['id'] ), ENT_QUOTES ), 0, 40 ), $r['slug'], implode( ', ', $r['all'] ) );
	}
	if ( count( $by[ $why ] ) > 12 ) {
		printf( "  ... and %d more\n", count( $by[ $why ] ) - 12 );
	}
	echo "\n";
}

printf( "GUESSES  (%d) -- not saved; set these in the listing's \"Primary category\" box\n", count( $by['guess'] ) );
foreach ( $by['guess'] as $r ) {
	printf( "  %-40s guessing %-12s of %s\n", mb_substr( html_entity_decode( get_the_title( $r['id'] ), ENT_QUOTES ), 0, 40 ), $r['slug'], implode( ', ', $r['all'] ) );
}
if ( $stale ) {
	echo "\nPREVIOUSLY SET, NO LONGER VALID -- worked out again above:\n  " . implode( "\n  ", $stale ) . "\n";
}

if ( ! $apply ) {
	echo "\nNothing saved. Add --apply to save the name and services answers.\n\n";
	exit( 0 );
}

$n = 0;
foreach ( array( 'name', 'services' ) as $why ) {
	foreach ( $by[ $why ] as $r ) {
		update_post_meta( $r['id'], Primary\META, $r['slug'] );
		++$n;
	}
}
printf( "\nSaved %d. Purge the page cache so category pages pick it up: wp litespeed-purge all\n\n", $n );
