<?php
/**
 * Flutter Bomb's own studio photos as its gallery and featured image.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/flutter-bomb-photos.php
 *
 * Kym gave Dale permission (11 Nov 2026) to use the photos on her site.
 * These seven are her studio -- nail and pedicure area, treatment rooms,
 * retail shelf, reception. The close-up model shots on her service pages
 * look like stock photography, not her clients or room, so they are left
 * out. Downloaded once from flutterbomb.com.au into the media library and
 * credited. Rerun-safe: a listing that already has its gallery is skipped.
 *
 * @package Oria\Core
 */

use Oria\Core\ListingEditor;

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$post = get_page_by_path( 'flutter-bomb', OBJECT, 'listing' );
if ( ! $post instanceof WP_Post ) {
	WP_CLI::error( 'No flutter-bomb listing.' );
}
$id = (int) $post->ID;
if ( get_post_meta( $id, '_oria_fb_photos', true ) ) {
	WP_CLI::success( 'Photos already added: ' . get_post_meta( $id, '_oria_fb_photos', true ) );
	return;
}

$base   = 'https://flutterbomb.com.au/wp-content/uploads/';
$photos = array(
	'flutterbomb-midland-pedicure-spa-nail-desk.jpg'         => 'The pedicure chairs and nail desk at Flutter Bomb, Midland',
	'flutterbomb-midland-treatment-room.jpg'                 => 'A treatment room at Flutter Bomb',
	'flutterbomb-midland-treatment-room-lashes.jpg'          => 'The lash treatment room at Flutter Bomb',
	'flutterbomb-midland-treatment-nail-area.jpg'            => 'The basin and pedicure area at Flutter Bomb',
	'flutterbomb-midland-seven-islands-skin-care-retail.jpg' => 'Seven Islands skincare on the shelves at Flutter Bomb',
	'flutterbomb-midland-reception.jpg'                      => 'The chandelier and butterfly wall in Flutter Bomb\'s reception',
	'flutterbomb-midland-reception-wall.jpg'                 => 'Butterflies and the Flutter Bomb bomb on the reception wall',
);

$ids = array();
foreach ( $photos as $file => $alt ) {
	$tmp = download_url( $base . $file, 30 );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( $file . ': ' . $tmp->get_error_message() );
		continue;
	}
	$att = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), $id, 'Flutter Bomb, Midland' );
	if ( is_wp_error( $att ) ) {
		wp_delete_file( $tmp );
		WP_CLI::warning( $file . ': ' . $att->get_error_message() );
		continue;
	}
	update_post_meta( (int) $att, '_wp_attachment_image_alt', $alt );
	update_post_meta( (int) $att, '_oria_image_source', $base . $file . ' (owner\'s own photo; permission given to Dale 11 Nov 2026)' );
	$ids[] = (int) $att;
	WP_CLI::log( "  + {$file} -> #{$att}" );
}

if ( ! $ids ) {
	WP_CLI::error( 'No photos downloaded.' );
}
set_post_thumbnail( $id, $ids[0] );
ListingEditor\write( $id, 'gallery', $ids );
update_post_meta( $id, '_oria_fb_photos', implode( ',', $ids ) );

clean_post_cache( $id );
do_action( 'litespeed_purge_post', $id );
WP_CLI::success( sprintf( '%d photos on #%d; featured #%d.', count( $ids ), $id, $ids[0] ) );
