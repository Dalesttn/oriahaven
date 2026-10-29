<?php
/**
 * Add Alchemy Saunas East Fremantle (34 Duke St) as a published listing.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/add-alchemy-east-fremantle.php
 *
 * Then give it its steam-room and sauna rows:
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/facility-access-import.php steam-room apply
 *   wp eval-file wp-content/plugins/oria-core/tools/facility-access-import.php sauna apply
 *
 * Facts from the venue's own page, alchemysaunas.com.au/our-space/east-fremantle,
 * checked 29 Oct 2026: 34 Duke St; 3 traditional saunas; ice pool, magnesium
 * pool and steam room; ice showers; $35 a 60-minute session; 5am to 9pm.
 * 18+ and swimwear-or-activewear are from the site's FAQ, which covers every
 * venue. The listed Alchemy (alchemy-saunas-perth) is the East Perth venue on
 * Claisebrook Rd, which has no steam room -- this is a separate listing, not
 * a second address on that one. No phone: the number on the East Perth
 * listing is not published for this venue. Photos come from Google Places on
 * first view, as for any listing. Safe to rerun: updates, never duplicates,
 * never unpublishes.
 *
 * @package Oria\Core
 */

use Oria\Core\ListingEditor;

defined( 'ABSPATH' ) || exit;

$slug = 'alchemy-saunas-east-fremantle';
$post = get_page_by_path( $slug, OBJECT, 'listing' );
$id   = $post instanceof WP_Post ? (int) $post->ID : 0;

$desc = 'A social bathhouse under the old Royal George Hotel in East Fremantle: three traditional saunas, a steam room, a magnesium pool, an ice pool and ice showers. A 60-minute session is $35 and runs from 5am to 9pm, with weekly memberships too. Over-18s only; swimwear or activewear.';

$body = '<p>Alchemy Saunas&rsquo; East Fremantle venue sits beneath the heritage Royal George Hotel on Duke Street. It is the largest of the Alchemy spaces: three traditional saunas seating about twenty, a steam room, a magnesium pool, an ice pool and ice showers, with private showers to finish.</p>'
	. '<h3>Good to know</h3><ul>'
	. '<li><strong>Sessions:</strong> 60 minutes, starting on the hour, from 5am to 9pm.</li>'
	. '<li><strong>Price:</strong> $35 a session. Weekly memberships are $40 unlimited, $30 off-peak, or $60 for the Passport across every Alchemy venue (checked October 2026).</li>'
	. '<li><strong>Who can go:</strong> 18 and over, even with an adult. Swimwear or activewear.</li>'
	. '<li><strong>Bring:</strong> two towels (one to sit on, one to dry off) and a water bottle.</li>'
	. '<li><strong>Booking:</strong> through the Alchemy Saunas app; each guest needs their own account.</li>'
	. '</ul>';

$args = array(
	'post_type'    => 'listing',
	'post_status'  => 'publish',
	'post_title'   => 'Alchemy Saunas East Fremantle',
	'post_name'    => $slug,
	'post_excerpt' => $desc,
	'post_content' => $body,
);
if ( $id ) {
	$args['ID'] = $id;
	unset( $args['post_status'] );
	wp_update_post( $args );
} else {
	$id = (int) wp_insert_post( $args, true );
	if ( ! $id ) {
		WP_CLI::error( 'Could not create the listing.' );
	}
}

// Fields, by key through the listing editor (never get/update_field by name).
ListingEditor\write( $id, 'website', 'https://alchemysaunas.com.au/our-space/east-fremantle' );
ListingEditor\write( $id, 'booking_url', 'https://alchemysaunas.com.au/booking' );
ListingEditor\write( $id, 'address', '34 Duke Street, East Fremantle WA 6158' );
ListingEditor\write( $id, 'kind', 'practice' );
ListingEditor\write( $id, 'format', 'in-person' );
ListingEditor\write( $id, 'price_from', 35 );
ListingEditor\write( $id, 'price_band', '$$' );
ListingEditor\write( $id, 'what_to_bring', 'Two towels (one to sit on, one to dry off) and a water bottle.' );
ListingEditor\write(
	$id,
	'services',
	array(
		array( 'name' => 'Traditional sauna' ),
		array( 'name' => 'Steam room' ),
		array( 'name' => 'Ice bath' ),
		array( 'name' => 'Contrast therapy' ),
	)
);

// Filed like the East Perth venue: Recovery first, Spa too.
wp_set_object_terms( $id, array( 'recovery', 'spa' ), 'practice' );
update_post_meta( $id, 'primary_practice', 'recovery' );
ListingEditor\write( $id, 'suburb', 'east-fremantle' ); // adds the region with it
wp_set_object_terms( $id, array( 'bathhouse', 'traditional-sauna', 'ice-bath', 'contrast-therapy' ), 'service', true );
wp_set_object_terms( $id, array( 'cold-plunge' ), 'specialty', true );
update_post_meta( $id, 'claim_status', 'unclaimed' );
update_post_meta( $id, 'placeholder_scene', 'scene-room' );
update_post_meta( $id, '_oria_verified', '2026-10-29' );

if ( ! get_post_meta( $id, 'geo_lat', true ) && function_exists( '\Oria\Core\Geo\geocode' ) ) {
	$geo = \Oria\Core\Geo\geocode( $id );
	WP_CLI::log( $geo ? sprintf( 'Placed at %s, %s (%s).', $geo['lat'], $geo['lng'], $geo['precision'] ) : 'Geocode missed: run wp oria geocode later.' );
}

clean_post_cache( $id );
do_action( 'litespeed_purge_post', $id );
WP_CLI::success( sprintf( 'Alchemy Saunas East Fremantle is #%d (%s) %s', $id, get_post_status( $id ), get_permalink( $id ) ) );
