<?php
/**
 * Finish Flutter Bomb's own sign-up (Kym, Midland) and publish it.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/fill-flutter-bomb.php
 *
 * Kym signed up on 11 Nov 2026 with a one-line description; Dale asked for
 * the rest to be filled in. Everything here is from the business's own site,
 * flutterbomb.com.au (home, pricing, contact-us, about-us, faq), checked
 * 11 Nov 2026. Her own phone, email, website and booking link are kept.
 *
 * Left for her: photos of the studio (Google's are downloaded as a stand-in)
 * and the team -- Layla, Krissy and Elvira would each need to agree to
 * appear, which the team section asks the owner to confirm.
 *
 * Publishing fires the normal sign-up approval: claim_status claimed,
 * verified_at, and the "Your listing is live" email to her. Safe to rerun.
 *
 * @package Oria\Core
 */

use Oria\Core\ListingEditor;

defined( 'ABSPATH' ) || exit;

global $wpdb;
$id = (int) $wpdb->get_var(
	"SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
	 WHERE p.post_type = 'listing' AND m.meta_key = 'website' AND m.meta_value LIKE '%flutterbomb.com.au%'
	 ORDER BY p.ID ASC LIMIT 1"
);
if ( ! $id ) {
	WP_CLI::error( 'No listing with the flutterbomb.com.au website.' );
}

$desc = 'A lash, brow and beauty studio in Midland, inside Lou Lou Hair Salon on Victoria Street and two minutes\' walk from Midland Gate. Owner Kym and her team do lash lifts and extensions, brow lamination, BIAB nails, manicures, pedicures, waxing and facials. Lash lift and tint is $90 and brow wax and tint $50; appointments only, no deposit.';

$body = '<p>Flutter Bomb is Kym&rsquo;s studio, tucked inside Lou Lou Hair Salon at 27 Victoria Street, Midland. Kym is a Hills local; the team also includes Layla, Krissy (the studio&rsquo;s brow lamination specialist) and Elvira, a beauty therapist of more than 35 years who once ran her own salon.</p>'
	. '<h3>Treatments</h3><ul>'
	. '<li><strong>Lashes:</strong> lift and tint, keratin treatment, and classic, hybrid or volume extensions with refills at two or three weeks.</li>'
	. '<li><strong>Brows:</strong> lamination, wax and tint, and a 3&ndash;4 week lamination refresh.</li>'
	. '<li><strong>Nails:</strong> BIAB overlays (with French tips, nail art or gel tip extensions), manicures and pedicures.</li>'
	. '<li><strong>Waxing:</strong> face, arms, legs, bikini and Brazilian.</li>'
	. '<li><strong>Facials:</strong> a 30-minute Skin Reset or the one-hour Ultimate Skin Reset, using Seven Islands skincare.</li>'
	. '</ul>'
	. '<p>Appointments only, booked online or by phone. No deposit is taken; you pay on the day, and Square takes all the usual cards. Clients come from around Midland, Guildford, Helena Valley, Mundaring and the Hills. Let the studio know about sensitive skin or allergies before you go.</p>'
	. '<p>Prices as published on flutterbomb.com.au, checked November 2026.</p>';

wp_update_post(
	array(
		'ID'           => $id,
		'post_title'   => 'Flutter Bomb',
		'post_name'    => 'flutter-bomb-midland',
		'post_excerpt' => $desc,
		'post_content' => $body,
	)
);

ListingEditor\write( $id, 'address', '27 Victoria Street, Midland WA 6056' );
ListingEditor\write( $id, 'parking', 'Street parking nearby' );
ListingEditor\write( $id, 'transit', 'Two minutes\' walk from Midland Gate Shopping Centre' );
ListingEditor\write( $id, 'kind', 'practice' );
ListingEditor\write( $id, 'format', 'in-person' );
ListingEditor\write( $id, 'price_from', 10 ); // upper lip wax
ListingEditor\write( $id, 'price_band', '$$' );
ListingEditor\write( $id, 'instagram_url', 'https://instagram.com/flutterbombco' );
ListingEditor\write( $id, 'facebook_url', 'https://facebook.com/flutterbomb' );
ListingEditor\write( $id, 'hours_hide', 0 );

ListingEditor\write(
	$id,
	'services',
	array_map(
		static fn( string $n ): array => array( 'name' => $n ),
		array( 'Lash lift & tint', 'Lash keratin treatment', 'Lash extensions', 'Brow lamination', 'Brow wax & tint', 'BIAB nails', 'Manicure', 'Pedicure', 'Waxing', 'Facials' )
	)
);

// A representative slice of the price list, not all 60 lines.
$packages = array(
	array( 'Lash lift & tint', '$90', '1 hr 15 min. With keratin treatment, $120 (1 hr 30 min).' ),
	array( 'Lash extensions, full set', 'from $120', 'Classic $120, hybrid $135, volume $150 (2 hrs). Refills from $65.' ),
	array( 'Brow lamination', '$80', '1 hr. With wax and tint, $95.' ),
	array( 'Brow wax & tint', '$50', '30 min. Add a lash tint for $70 all up.' ),
	array( 'BIAB overlay', '$70', '1 hr. French tip $85, nail art $100; refill $60.' ),
	array( 'Manicure / pedicure', '$65 / $75', '30 min / 45 min.' ),
	array( 'Ultimate Skin Reset Facial', '$130', '1 hr, Seven Islands skincare. The 30-minute Skin Reset is $80.' ),
	array( 'Waxing', 'from $10', 'Upper lip $10, underarms $20, Brazilian $65, full leg and Brazilian $110.' ),
);
ListingEditor\write(
	$id,
	'packages',
	array_map(
		static fn( array $p ): array => array(
			'title'       => $p[0],
			'price'       => $p[1],
			'description' => $p[2],
			'booking_url' => 'https://flutterbomb.com.au/book/',
		),
		$packages
	)
);

ListingEditor\write(
	$id,
	'opening_hours',
	array(
		array( 'days' => 'Tue and Wed', 'hours' => '9am to 5pm' ),
		array( 'days' => 'Thursday', 'hours' => '9am to 7pm' ),
		array( 'days' => 'Friday', 'hours' => '9am to 5pm' ),
		array( 'days' => 'Saturday', 'hours' => '9am to 2pm' ),
		array( 'days' => 'Sun and Mon', 'hours' => 'Closed' ),
	)
);

ListingEditor\write(
	$id,
	'faq',
	array(
		array( 'question' => 'Do you take walk-ins?', 'answer' => 'Appointments only. You can drop in to check for a free spot, but booking ahead is the way to get the time you want; afternoons and weekends fill first.' ),
		array( 'question' => 'Is there a deposit?', 'answer' => 'No. You book without a deposit and pay on the day, by any card Square accepts.' ),
		array( 'question' => 'Where exactly is the studio?', 'answer' => 'Inside Lou Lou Hair Salon at 27 Victoria Street, Midland, a two-minute walk from Midland Gate. There is street parking nearby.' ),
		array( 'question' => 'I have sensitive skin or allergies. Can I still book?', 'answer' => 'Yes, but tell the studio beforehand so the treatment can be adjusted, or so they can advise whether it suits you.' ),
	)
);

wp_set_object_terms( $id, array( 'lashes-and-brows', 'nails', 'waxing', 'facials' ), 'service', true );
update_post_meta( $id, 'primary_practice', 'beauty' );
update_post_meta( $id, '_oria_verified', '2026-11-11' );

if ( ! get_post_meta( $id, 'geo_lat', true ) && function_exists( '\Oria\Core\Geo\geocode' ) ) {
	$geo = \Oria\Core\Geo\geocode( $id );
	WP_CLI::log( $geo ? sprintf( 'Placed at %s, %s (%s).', $geo['lat'], $geo['lng'], $geo['precision'] ) : 'Geocode missed: run wp oria geocode later.' );
}

// Publishing is the approval: Signup\live_email() puts her on the free
// Claimed plan and sends "Your listing is live".
if ( 'publish' !== get_post_status( $id ) ) {
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
}
// The plan, in case this install predates that hook's fix.
if ( ! in_array( (string) get_post_meta( $id, 'claim_status', true ), array( 'claimed', 'featured' ), true ) ) {
	update_post_meta( $id, 'claim_status', 'claimed' );
}
if ( ! get_post_meta( $id, 'verified_at', true ) ) {
	update_post_meta( $id, 'verified_at', '2026-11-11' );
}

if ( function_exists( '\Oria\Core\Places\download_photos' ) && ! has_post_thumbnail( $id ) ) {
	$ph = \Oria\Core\Places\download_photos( $id );
	WP_CLI::log( 'Google photos: ' . $ph['status'] . ( $ph['saved'] ? " ({$ph['saved']})" : '' ) );
}

clean_post_cache( $id );
do_action( 'litespeed_purge_post', $id );
WP_CLI::success( sprintf( 'Flutter Bomb is #%d (%s, %s) %s', $id, get_post_status( $id ), get_post_meta( $id, 'claim_status', true ), get_permalink( $id ) ) );
