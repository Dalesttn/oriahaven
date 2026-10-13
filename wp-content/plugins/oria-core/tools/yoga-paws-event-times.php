<?php
/**
 * Apply oria-ingest/events/yoga-paws-2026-10.json to Yoga Paws events that already exist.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/yoga-paws-event-times.php
 *
 * The importer skips events that are already published, so a corrected
 * file does not reach them. This matches each row to its event by booking
 * URL and writes the times, end, price and description from the file.
 * Yoga Paws confirmed the Perth start times (8am to 4pm, hourly sessions)
 * by email on 13 Oct 2026. Rows with no event yet are reported, not
 * created: import the file for those. Safe to rerun.
 *
 * @package Oria\Core
 */

defined( 'ABSPATH' ) || exit;

$file = WP_PLUGIN_DIR . '/oria-ingest/events/yoga-paws-2026-10.json';
$data = json_decode( (string) file_get_contents( $file ), true );
if ( empty( $data['events'] ) ) {
	WP_CLI::error( 'Could not read ' . $file );
}

$done = 0;
foreach ( $data['events'] as $row ) {
	$found = get_posts(
		array(
			'post_type'      => 'event',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => 'booking_url', 'value' => (string) $row['booking_url'] ) ),
		)
	);
	if ( ! $found ) {
		WP_CLI::warning( $row['title'] . ' (' . substr( $row['start'], 0, 10 ) . '): no event yet -- import the file first.' );
		continue;
	}
	$id = (int) $found[0];
	update_field( 'field_oria_event_start', (string) $row['start'], $id );
	update_field( 'field_oria_event_end', (string) ( $row['end'] ?? '' ), $id );
	update_field( 'field_oria_event_price', (string) $row['price'], $id );
	update_field( 'field_oria_event_description', '<p>' . esc_html( (string) $row['description'] ) . '</p>', $id );
	update_post_meta( $id, '_oria_verified', (string) ( $row['checked'] ?? wp_date( 'Y-m-d' ) ) );
	clean_post_cache( $id );
	do_action( 'litespeed_purge_post', $id );
	WP_CLI::log( sprintf( '  ~ #%d %s: %s to %s', $id, $row['title'], $row['start'], $row['end'] ?? '' ) );
	++$done;
}

WP_CLI::success( sprintf( '%d of %d Yoga Paws events updated.', $done, count( $data['events'] ) ) );
