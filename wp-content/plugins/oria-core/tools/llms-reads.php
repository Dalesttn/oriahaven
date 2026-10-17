<?php
/**
 * Who has been reading /llms.txt -- read-only report of the counter kept by
 * includes/llms.php (count_read()).
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/llms-reads.php        # last 30 days, by reader
 *   wp eval-file wp-content/plugins/oria-core/tools/llms-reads.php 90     # last 90 days
 *   wp eval-file wp-content/plugins/oria-core/tools/llms-reads.php daily  # day by day, last 14 days
 *
 * Counting starts the day this was deployed; there is no history before it.
 *
 * @package Oria\Core
 */

defined( 'ABSPATH' ) || exit;

$arg   = (string) ( $args[0] ?? '' );
$reads = get_option( \Oria\Core\Llms\READS_OPTION, array() );
$reads = is_array( $reads ) ? $reads : array();
if ( ! $reads ) {
	WP_CLI::warning( 'No reads recorded yet. The counter starts when this code is deployed; check back in a few days.' );
	return;
}
ksort( $reads );

if ( 'daily' === $arg ) {
	$rows = array();
	foreach ( array_slice( $reads, -14, null, true ) as $day => $by ) {
		arsort( $by );
		$rows[] = array( 'day' => $day, 'total' => array_sum( $by ), 'readers' => implode( ', ', array_map( static fn( $k, $v ) => "$k $v", array_keys( $by ), $by ) ) );
	}
	WP_CLI\Utils\format_items( 'table', $rows, array( 'day', 'total', 'readers' ) );
	return;
}

$days   = max( 1, (int) ( $arg ?: 30 ) );
$cutoff = wp_date( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
$sum    = array();
$last   = array();
foreach ( $reads as $day => $by ) {
	if ( $day < $cutoff ) {
		continue;
	}
	foreach ( $by as $family => $n ) {
		$sum[ $family ]  = ( $sum[ $family ] ?? 0 ) + (int) $n;
		$last[ $family ] = $day;
	}
}
arsort( $sum );
$rows = array();
foreach ( $sum as $family => $n ) {
	$rows[] = array( 'reader' => $family, 'reads' => $n, 'last_seen' => $last[ $family ] );
}
WP_CLI::log( sprintf( 'llms.txt reads, last %d days (since %s):', $days, max( $cutoff, (string) array_key_first( $reads ) ) ) );
WP_CLI\Utils\format_items( 'table', $rows, array( 'reader', 'reads', 'last_seen' ) );
