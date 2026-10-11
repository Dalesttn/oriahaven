<?php
/**
 * Add Yoga Paws Perth (puppy yoga) as a DRAFT listing.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/add-yoga-paws.php
 *
 * Facts from yoga-paws.com, checked 11 Oct 2026: the Perth page
 * (/perth-puppy-yoga) and the session booking pages (venues, addresses,
 * $65, 60 minutes, "suitable for all experience levels"). The 12+ age
 * limit and start times come from Patrick's email (not on the readable
 * site), so they are attributed to Yoga Paws in the copy. Google Places is
 * hidden: the sessions run in other businesses' studios, so a name search
 * would match the host, not Yoga Paws. No health claims. Safe to rerun.
 *
 * @package Oria\Core
 */

use Oria\Core\ListingEditor;

defined( 'ABSPATH' ) || exit;

$slug = 'yoga-paws-perth';
$post = get_page_by_path( $slug, OBJECT, 'listing' );
$id   = $post instanceof WP_Post ? (int) $post->ID : 0;

$desc = 'Beginner-friendly puppy yoga in Perth: a 60-minute session of gentle floor yoga and relaxation while puppies from WA breeders and shelters wander the room. Weekend sessions run at studios in Inglewood, Clarkson and Northbridge, $65 a ticket, with everything provided.';

$body = '<p>Yoga Paws runs weekly puppy yoga across Australia, and in Perth most weekends at three hired studios. Each session is an hour of relaxed, beginner-friendly floor yoga with a litter of puppies free to wander, settle beside you or climb into your lap &mdash; you follow along at your own pace and stop for a visitor whenever one arrives. No yoga experience is needed and the mats and puppies are provided, so you just bring yourself.</p>'
	. '<h3>Where Perth sessions run</h3><ul>'
	. '<li><strong>Inglewood</strong> &mdash; Inspiration Space, 1&amp;2/885 Beaufort St</li>'
	. '<li><strong>Clarkson</strong> &mdash; Sol Tank Studio, 37 Boranup Ave</li>'
	. '<li><strong>Northbridge</strong> &mdash; Studio Northbridge, 2/1 Fitzgerald St (plan your parking)</li>'
	. '</ul>'
	. '<p>Dates are released on the Yoga Paws Perth page and each session is booked separately; Yoga Paws says sessions are open to ages 12 and up and new dates are released each Monday, three weeks ahead. Gift cards, private parties and corporate sessions are also available.</p>'
	. '<h3>How the puppies are looked after</h3>'
	. '<p>Yoga Paws works with WA-based breeders and shelters and says every puppy has a mandatory 60 minutes of downtime before each session, and that studios are cleaned with veterinary-grade F10 sanitiser. Interaction is voluntary and puppies are only handled while you\'re seated.</p>';

$args = array(
	'post_type'    => 'listing',
	'post_status'  => 'draft',
	'post_title'   => 'Yoga Paws Perth',
	'post_name'    => $slug,
	'post_excerpt' => $desc,
	'post_content' => $body,
);
if ( $id ) {
	$args['ID'] = $id;
	unset( $args['post_status'] ); // A rerun never unpublishes it.
	wp_update_post( $args );
} else {
	$id = (int) wp_insert_post( $args, true );
	if ( ! $id ) {
		WP_CLI::error( 'Could not create the listing.' );
	}
}

ListingEditor\write( $id, 'website', 'https://www.yoga-paws.com/perth-puppy-yoga' );
ListingEditor\write( $id, 'booking_url', 'https://www.yoga-paws.com/perth-puppy-yoga' );
ListingEditor\write( $id, 'address', '1&2/885 Beaufort St, Inglewood WA 6052 (also Clarkson and Northbridge)' );
ListingEditor\write( $id, 'kind', 'practice' );
ListingEditor\write( $id, 'format', 'in-person' );
ListingEditor\write( $id, 'price_from', 65 );
ListingEditor\write( $id, 'duration_min', 60 );
ListingEditor\write( $id, 'places_hide', 1 );
update_post_meta( $id, 'places_hide', '1' );

wp_set_object_terms( $id, array( 'yoga' ), 'practice' );
update_post_meta( $id, 'primary_practice', 'yoga' );
wp_set_object_terms( $id, array( 'yoga' ), 'specialty' );
wp_set_object_terms( $id, array( 'gentle-yoga' ), 'service' );
// Inglewood and Northbridge (Central); Clarkson is not in the area tree, so its region.
wp_set_object_terms( $id, array( 'inglewood', 'northbridge', 'central', 'north' ), 'area' );
update_post_meta( $id, 'claim_status', 'unclaimed' );
update_post_meta( $id, '_oria_source_note', 'yoga-paws.com checked 11 Oct 2026; age 12+ and start times from Patrick (Yoga Paws) by email' );

clean_post_cache( $id );
WP_CLI::success( sprintf( 'Yoga Paws Perth is #%d (%s). Review it, then publish.', $id, get_post_status( $id ) ) );
