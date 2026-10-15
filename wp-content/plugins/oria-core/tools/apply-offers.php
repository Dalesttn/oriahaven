<?php
/**
 * Write the advertised offers in data/offers.json onto their listings.
 *
 * Each row names a listing by slug and the offer as read on the business's
 * own site: title, details, the page it is on, the date it was checked and,
 * where the site states one, the date it ends. The file is the record; this
 * copies it onto the listing's offer fields (Theme\active_offer shows it,
 * labelled as advertised, and Offers\sweep removes it the day after it ends).
 *
 * RULES
 *   - A claimed listing is never touched: its owner runs their own offer.
 *   - A listing whose offer was typed by an owner (title set, no source) is
 *     never overwritten.
 *   - Rows marked "removed": true clear the offer from the listing.
 *   - Idempotent: a second run reports "unchanged" for every row already on.
 *
 * SAFETY
 *   Dry run is the default and writes nothing. --apply writes.
 *
 * USAGE (from the WordPress root, on the server)
 *     php wp-content/plugins/oria-core/tools/apply-offers.php
 *     php wp-content/plugins/oria-core/tools/apply-offers.php --apply
 *
 * Or from the backend: Listings > Offers import (same rule, plus a preview).
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

// The rule lives in Offers\import_run(), shared with Listings > Offers import.
$apply = in_array( '--apply', $argv ?? array(), true );
$r     = \Oria\Core\Offers\import_run( $apply );
if ( ! $r['ok'] ) {
	fwrite( STDERR, $r['error'] . "\n" );
	exit( 1 );
}

echo ( $apply ? 'APPLY' : 'DRY RUN' ) . "\n\n";
foreach ( $r['rows'] as $row ) {
	if ( 'WRITE' === $row['do'] ) {
		printf( "WRITE      %s\n             %s\n", $row['name'], $row['note'] );
	} else {
		printf( "%-10s %s%s\n", $row['do'], $row['name'], '' !== $row['note'] ? ' -- ' . $row['note'] : '' );
	}
}
$n = $r['n'];
printf( "\n%s: %d written, %d unchanged, %d cleared, %d skipped.\n", $apply ? 'Done' : 'Dry run', $n['written'], $n['unchanged'], $n['cleared'], $n['skipped'] );
if ( $apply ) {
	echo "Page cache purged.\n";
}
