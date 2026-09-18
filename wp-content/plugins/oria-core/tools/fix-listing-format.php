<?php
/**
 * Put right listings whose "format" is not one the site understands.
 *
 * Usage (from the WordPress root):
 *   php wp-content/plugins/oria-core/tools/fix-listing-format.php            # report only
 *   php wp-content/plugins/oria-core/tools/fix-listing-format.php --apply    # fix
 *
 * The format field is in-person / online / both. Two seed files prepared in
 * September 2026 (listings-ellenbrook.json and the Pemberton files) used
 * descriptive words instead -- group, one-to-one, private, self-guided --
 * and anything that is not "in-person" reads as online on the cards, so a
 * floating sauna in a forest was being labelled "Online available". Every
 * one of those listings is a physical place, so an unknown value becomes
 * "in-person" -- except "hybrid" (some older listings), which means in
 * person and online and becomes "both". Valid values are never touched.
 * Read the dry run before applying: anything else unexpected is listed.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

$apply = in_array( '--apply', $argv ?? array(), true );
$valid = array( 'in-person', 'online', 'both', '' );

global $wpdb;
$rows = $wpdb->get_results(
	"SELECT p.ID, p.post_title, m.meta_value FROM {$wpdb->posts} p
	 JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'format'
	 WHERE p.post_type = 'listing' AND p.post_status <> 'trash'"
);

echo "\n" . ( $apply ? "APPLY\n" : "DRY RUN -- add --apply to fix.\n" );
$n = 0;
foreach ( $rows as $r ) {
	if ( in_array( (string) $r->meta_value, $valid, true ) ) {
		continue;
	}
	++$n;
	// "hybrid" (older listings) means in person AND online: that is "both".
	$to = in_array( strtolower( (string) $r->meta_value ), array( 'hybrid', 'in-person-and-online', 'online-and-in-person' ), true ) ? 'both' : 'in-person';
	printf( "  #%-7d %-45s %s -> %s\n", $r->ID, mb_substr( html_entity_decode( $r->post_title ), 0, 45 ), $r->meta_value, $to );
	if ( $apply ) {
		update_post_meta( (int) $r->ID, 'format', $to );
	}
}
printf( "\n%d listing(s) %s.%s\n\n", $n, $apply ? 'fixed' : 'would be fixed', $apply && $n ? ' Purge the page cache: wp litespeed-purge all' : '' );
