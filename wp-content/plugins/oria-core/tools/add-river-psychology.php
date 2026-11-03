<?php
/**
 * Add River Psychology and Counselling (Jasmine Fisher, Como) as a published
 * listing.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/add-river-psychology.php
 *
 * Facts from the practice's own site, riverpsychologyandcounselling.com
 * (home, about, services, fees-rebates, contact), checked 3 Nov 2026:
 * Suite 2/10 Mary Street, Aldar House, Como WA 6152; in person and
 * telehealth Australia-wide; adolescents 14+ and adults; $220 for 50
 * minutes, Medicare rebate $101.55 with a Mental Health Treatment Plan
 * (from 1 July 2026); NDIS self- and plan-managed; booking on Halaxy.
 *
 * A registered health practitioner, so AHPRA's advertising rules apply: no
 * testimonials, no outcome promises -- only what she offers and how, in
 * plain terms. Safe to rerun: updates, never duplicates, never unpublishes.
 *
 * @package Oria\Core
 */

use Oria\Core\ListingEditor;

defined( 'ABSPATH' ) || exit;

$slug = 'river-psychology-and-counselling';
$post = get_page_by_path( $slug, OBJECT, 'listing' );
$id   = $post instanceof WP_Post ? (int) $post->ID : 0;

$desc = 'A small psychology practice in Como run by Jasmine Fisher, Registered Psychologist, seeing adolescents (14+) and adults in person or by telehealth anywhere in Australia. Sessions are $220 for 50 minutes, with Medicare rebates on a Mental Health Treatment Plan, and NDIS (self- and plan-managed) accepted. The practice describes itself as neurodivergent-affirming, with a quiet, low-sensory consulting room.';

$body = '<p>River Psychology and Counselling is Jasmine Fisher&rsquo;s own practice: she is its founder and only psychologist, so every client works with her directly. She holds a Master of Psychology (Professional) and a Bachelor of Psychology from Curtin University, and has worked across private practice, justice and disability settings.</p>'
	. '<h3>What she works with</h3><ul>'
	. '<li>Anxiety, stress and burnout</li>'
	. '<li>Low mood and depression</li>'
	. '<li>Trauma and difficult experiences</li>'
	. '<li>Relationship, parenting and life challenges</li>'
	. '<li>Emotional regulation</li>'
	. '<li>Neurodivergence, identity and self-understanding, including for Autistic, ADHD and AuDHD clients</li>'
	. '</ul>'
	. '<p>Therapy draws on CBT, Acceptance and Commitment Therapy, Schema Therapy (a particular interest), EMDR and Solution-Focused Therapy; she notes she doesn&rsquo;t use CBT as the framework for neurodivergent-affirming support. She is also a trained Circle of Security&reg; parenting facilitator. The consulting room is in a quiet private clinic with soft lighting, and she welcomes conversations about sensory needs.</p>'
	. '<h3>Fees and funding</h3><ul>'
	. '<li><strong>Session:</strong> $220 for 50 minutes. No referral needed to book privately.</li>'
	. '<li><strong>Medicare:</strong> with an eligible Mental Health Treatment Plan and referral, a rebate of $101.55 a session (from 1 July 2026), claimed on the day, leaving $118.45.</li>'
	. '<li><strong>Also accepted:</strong> private health insurance (receipt provided), NDIS self- and plan-managed participants, ICWA motor vehicle and workers&rsquo; compensation claims, and Bupa Open Arms referrals.</li>'
	. '</ul>'
	. '<p>Fees as published on the practice&rsquo;s website, checked November 2026.</p>';

$args = array(
	'post_type'    => 'listing',
	'post_status'  => 'publish',
	'post_title'   => 'River Psychology and Counselling',
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
ListingEditor\write( $id, 'website', 'https://www.riverpsychologyandcounselling.com/' );
ListingEditor\write( $id, 'booking_url', 'https://www.halaxy.com/book/river-psychology-and-counselling/location/962961' );
ListingEditor\write( $id, 'phone', '0435 093 554' );
ListingEditor\write( $id, 'email', 'admin@riverpsychologyandcounselling.com' );
ListingEditor\write( $id, 'address', 'Suite 2/10 Mary Street, Como WA 6152' );
ListingEditor\write( $id, 'kind', 'practice' );
ListingEditor\write( $id, 'format', 'both' ); // Como in person, telehealth Australia-wide
ListingEditor\write( $id, 'price_from', 220 );
ListingEditor\write( $id, 'price_band', '$$$' );
ListingEditor\write(
	$id,
	'services',
	array(
		array( 'name' => 'Individual psychological therapy (50 min)' ),
		array( 'name' => 'Telehealth sessions' ),
		array( 'name' => 'Schema Therapy' ),
		array( 'name' => 'EMDR' ),
		array( 'name' => 'Neurodivergent-affirming therapy (NDIS)' ),
	)
);

// Filed like the site's other psychologists.
wp_set_object_terms( $id, array( 'mind' ), 'practice' );
update_post_meta( $id, 'primary_practice', 'mind' );
ListingEditor\write( $id, 'suburb', 'como' ); // adds the region with it
wp_set_object_terms( $id, array( 'counselling', 'psychotherapy' ), 'service', true );
update_post_meta( $id, 'claim_status', 'unclaimed' );
update_post_meta( $id, '_oria_verified', '2026-11-03' );

if ( ! get_post_meta( $id, 'geo_lat', true ) && function_exists( '\Oria\Core\Geo\geocode' ) ) {
	$geo = \Oria\Core\Geo\geocode( $id );
	WP_CLI::log( $geo ? sprintf( 'Placed at %s, %s (%s).', $geo['lat'], $geo['lng'], $geo['precision'] ) : 'Geocode missed: run wp oria geocode later.' );
}

clean_post_cache( $id );
do_action( 'litespeed_purge_post', $id );
WP_CLI::success( sprintf( 'River Psychology and Counselling is #%d (%s) %s', $id, get_post_status( $id ), get_permalink( $id ) ) );
