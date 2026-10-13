<?php
/**
 * Facility prices due for a re-check (the freshness policy in
 * includes/facility-access.php: STALE_DAYS).
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/facility-stale.php            # every facility
 *   wp eval-file wp-content/plugins/oria-core/tools/facility-stale.php sauna      # one facility
 *
 * Read-only. Lists every published listing whose confirmed facility row was
 * last checked more than STALE_DAYS ago (or never), oldest first, with the
 * source to re-check. A stale price still shows on the page with its date,
 * but stops counting for budget filters, price sorting and "under $50"
 * until the row's checked date is renewed -- by an editor in the listing's
 * Facility access box, or by re-running the import with a fresh file.
 *
 * @package Oria\Core
 */

use Oria\Core\FacilityAccess as FA;

defined( 'ABSPATH' ) || exit;

$only = sanitize_key( (string) ( $args[0] ?? '' ) );
$ids  = get_posts(
	array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => FA\FIELD, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ),
	)
);

$due = array();
foreach ( $ids as $id ) {
	foreach ( FA\rows( (int) $id ) as $row ) {
		if ( '' !== $only && $only !== $row['facility'] ) {
			continue;
		}
		if ( 'confirmed' !== $row['status'] || ! FA\is_stale( $row ) ) {
			continue;
		}
		$due[] = array(
			'checked'  => '' !== $row['checked'] ? $row['checked'] : '(never)',
			'facility' => $row['facility'],
			'listing'  => sprintf( '#%d %s', $id, html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) ),
			'source'   => $row['sources'][0] ?? (string) get_post_meta( $id, 'website', true ),
		);
	}
}
usort( $due, static fn( $a, $b ): int => strcmp( $a['checked'], $b['checked'] ) );

if ( ! $due ) {
	WP_CLI::success( sprintf( 'Nothing due: every confirmed facility price was checked within %d days.', FA\STALE_DAYS ) );
	return;
}
WP_CLI\Utils\format_items( 'table', $due, array( 'checked', 'facility', 'listing', 'source' ) );
WP_CLI::warning( sprintf( '%d facility price(s) older than %d days: shown with their date, but left out of budget filters until re-checked.', count( $due ), FA\STALE_DAYS ) );
