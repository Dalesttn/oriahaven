<?php
/**
 * Download every listing's Google photos once, so the site stops paying
 * Google to re-resolve them every week (Dale's call, 2 Nov 2026).
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/places-download.php            (dry run: counts only, no calls)
 *   wp eval-file wp-content/plugins/oria-core/tools/places-download.php apply      (download)
 *   wp eval-file wp-content/plugins/oria-core/tools/places-download.php apply 50   (at most 50 listings this run)
 *   wp eval-file wp-content/plugins/oria-core/tools/places-download.php apply 0 nolookup
 *
 * Listings with their own featured image or gallery are skipped: the Google
 * photo was never shown on those. Each saved photo is one paid media call
 * at most (free when the old cached link still works); a listing with no
 * cached photo names costs one more lookup call, unless "nolookup".
 * Files go to uploads/oria-gphotos/; rerun safely -- done listings are
 * skipped, so a run cut short just carries on.
 *
 * @package Oria\Core
 */

use Oria\Core\Places;

defined( 'ABSPATH' ) || exit;

$apply  = 'apply' === (string) ( $args[0] ?? '' );
$limit  = (int) ( $args[1] ?? 0 );
$lookup = 'nolookup' !== (string) ( $args[2] ?? '' );

$ids = get_posts(
	array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	)
);

$tally = array();
$done  = 0;
$saved = 0;
foreach ( $ids as $id ) {
	$id = (int) $id;
	if ( has_post_thumbnail( $id ) || get_post_meta( $id, 'gallery', true ) ) {
		$tally['has own photos'] = ( $tally['has own photos'] ?? 0 ) + 1;
		continue;
	}
	if ( $apply && $limit > 0 && $done >= $limit ) {
		$tally['left for next run'] = ( $tally['left for next run'] ?? 0 ) + 1;
		continue;
	}
	$r = Places\download_photos( $id, $lookup, ! $apply );
	$k = preg_replace( '/^(would download|name mismatch).*/', '$1', $r['status'] );
	$tally[ $k ] = ( $tally[ $k ] ?? 0 ) + 1;
	if ( $apply && 'done already' !== $r['status'] && 'hidden' !== $r['status'] ) {
		++$done;
		$saved += $r['saved'];
		WP_CLI::log( sprintf( '%-7s #%d %s: %s', $r['saved'] ? 'SAVED' : '-', $id, get_post_field( 'post_name', $id ), $r['status'] ) );
		if ( 'saved' !== $r['status'] ) {
			usleep( 200000 );
		}
	}
}

ksort( $tally );
foreach ( $tally as $k => $n ) {
	WP_CLI::log( sprintf( '  %-28s %d', $k, $n ) );
}
WP_CLI::success( $apply ? sprintf( '%d photos saved across %d listings.', $saved, $done ) : 'Dry run: nothing called or written. Add "apply" to download.' );
