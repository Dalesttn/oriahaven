<?php
/**
 * Fill in "Smudge Fan Creative Experience" (Sigma Breathwork, Osborne Park,
 * Sun 8 Nov 2026) and retire its empty duplicate.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/update-smudge-fan.php
 *
 * Facts from the event's own Humanitix page, checked 20 Oct 2026. The page's
 * one ticket is "222 = $72 now (& smaller fees) + $150 on the day", so the
 * event costs $222 -- not the $72 the importer stored. The description is
 * our own words; the four stages are the organiser's. Images are the
 * organiser's own event photos from that page.
 *
 * Two events exist on production: "smudge-fan-creative-experience-2" has
 * the date, venue and price; "smudge-fan-creative-experience" is an empty
 * shell (no date, no details). The full one is updated; the shell is set
 * to draft -- not deleted -- so it stops showing as a blank page. Safe to
 * rerun.
 *
 * @package Oria\Core
 */

defined( 'ABSPATH' ) || exit;

$full  = get_page_by_path( 'smudge-fan-creative-experience-2', OBJECT, 'event' );
$shell = get_page_by_path( 'smudge-fan-creative-experience', OBJECT, 'event' );
if ( ! $full instanceof WP_Post ) {
	// If only one exists, it is the one to fill in.
	$full  = $shell;
	$shell = null;
}
if ( ! $full instanceof WP_Post ) {
	WP_CLI::error( 'No Smudge Fan Creative Experience event found.' );
}
$id = (int) $full->ID;

$desc = '<p>A small half-day workshop in Osborne Park run by Sigma Breathwork, in which each person makes their own smudge fan from feathers, crystals, leather, wire and other natural materials. The host taught cake decorating across WA and New Zealand before turning to breathwork, and describes this as an intimate session bringing the two together.</p>'
	. '<h3>How the afternoon runs</h3><ul>'
	. '<li><strong>Connect:</strong> ceremonial cacao, an opening circle and a guided meditation acknowledging Country and the natural materials you&rsquo;ll be working with.</li>'
	. '<li><strong>Create:</strong> make your fan with guidance, choosing the feathers, crystals and details yourself. Tea and snacks are on hand throughout.</li>'
	. '<li><strong>Infuse:</strong> a guided meditation and blessing for what you&rsquo;ve made.</li>'
	. '<li><strong>Integrate:</strong> a short sound healing session and a closing sharing circle.</li>'
	. '</ul>'
	. '<h3>Good to know</h3><ul>'
	. '<li><strong>When:</strong> Sunday 8 November, 12.30pm to 6pm.</li>'
	. '<li><strong>Price:</strong> $222 in total &mdash; $72 when you book on Humanitix (plus booking fees), then $150 on the day (checked 20 Oct 2026).</li>'
	. '<li><strong>Where:</strong> 131 North Beach Drive, Osborne Park.</li>'
	. '<li><strong>Refunds:</strong> available up to 14 days before the event.</li>'
	. '</ul>';

update_field( 'field_oria_event_start', '2026-11-08 12:30:00', $id );
update_field( 'field_oria_event_end', '2026-11-08 18:00:00', $id );
update_field( 'field_oria_event_price', '$222 ($72 to book + $150 on the day)', $id );
update_field( 'field_oria_event_description', $desc, $id );
update_field( 'field_oria_event_booking', 'https://events.humanitix.com/smudge-fan-creative-experience', $id );
update_post_meta( $id, '_oria_organiser', 'Sigma Breathwork' );
update_post_meta( $id, '_oria_verified', '2026-10-20' );
wp_update_post(
	array(
		'ID'           => $id,
		'post_excerpt' => 'A half-day workshop in Osborne Park where you make your own smudge fan from feathers, crystals and natural materials, with cacao, guided meditation and a closing sound healing. Sun 8 Nov, 12.30-6pm, $222.',
	)
);

// The organiser's own photos: the banner as the cover, two fans in the gallery. Uploaded once.
if ( ! get_post_meta( $id, '_oria_smudge_photos', true ) ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$files = array(
		'smudge-fan-cover.webp' => 'A handmade smudge fan of colourful feathers on a sacred geometry cloth',
		'smudge-fan-1.webp'     => 'A feather smudge fan with a leather-bound handle against macrame',
		'smudge-fan-2.webp'     => 'A peacock-feather smudge fan with a purple crystal, among lavender',
	);
	$ids = array();
	foreach ( $files as $file => $alt ) {
		$src = __DIR__ . '/assets/' . $file;
		if ( ! is_readable( $src ) ) {
			continue;
		}
		$tmp = wp_tempnam( $file );
		copy( $src, $tmp );
		$att = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), $id, 'Smudge Fan Creative Experience' );
		if ( is_wp_error( $att ) ) {
			WP_CLI::warning( $file . ': ' . $att->get_error_message() );
			continue;
		}
		update_post_meta( (int) $att, '_wp_attachment_image_alt', $alt );
		update_post_meta( (int) $att, '_oria_image_source', 'https://events.humanitix.com/smudge-fan-creative-experience (organiser\'s event photos)' );
		$ids[] = (int) $att;
	}
	if ( $ids ) {
		set_post_thumbnail( $id, $ids[0] );
		if ( count( $ids ) > 1 ) {
			update_field( 'field_oria_event_gallery', array_slice( $ids, 1 ), $id );
		}
		update_post_meta( $id, '_oria_smudge_photos', implode( ',', $ids ) );
	}
}

clean_post_cache( $id );
do_action( 'litespeed_purge_post', $id );
WP_CLI::log( sprintf( 'Updated #%d %s', $id, get_permalink( $id ) ) );

// The empty duplicate: only if it really is empty (no start date, no description).
if ( $shell instanceof WP_Post && (int) $shell->ID !== $id && 'publish' === $shell->post_status ) {
	$empty = '' === (string) get_post_meta( $shell->ID, 'event_start', true ) && '' === trim( wp_strip_all_tags( (string) get_post_meta( $shell->ID, 'event_description', true ) ) );
	if ( $empty ) {
		wp_update_post( array( 'ID' => (int) $shell->ID, 'post_status' => 'draft' ) );
		do_action( 'litespeed_purge_post', (int) $shell->ID );
		WP_CLI::log( sprintf( 'Empty duplicate #%d set to draft (not deleted).', $shell->ID ) );
	} else {
		WP_CLI::warning( sprintf( 'Second event #%d has its own details, so it was left alone: check it by hand.', $shell->ID ) );
	}
}
WP_CLI::success( 'Done. Purge the cache: wp litespeed-purge all' );
