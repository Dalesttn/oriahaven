<?php
/**
 * Add Hertz2Hearts (music wellbeing for aged care, Perth) as a DRAFT listing.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/add-hertz2hearts.php
 *
 * Details from the business's own website (screenshot supplied by Dale,
 * 10 Oct 2026). The site is explicit that its standard service is Music
 * Facilitation and "not clinical music therapy"; Registered Music Therapy
 * is available on request through RMT collaborators. The description keeps
 * that distinction. No health claims. Safe to rerun: updates the draft it
 * made rather than creating a second one.
 *
 * @package Oria\Core
 */

use Oria\Core\ListingEditor;

defined( 'ABSPATH' ) || exit;

$slug = 'hertz2hearts';
$post = get_page_by_path( $slug, OBJECT, 'listing' );
$id   = $post instanceof WP_Post ? (int) $post->ID : 0;

$desc = 'Hertz2Hearts brings live music into aged care communities around Perth: group singalongs, dementia-friendly sessions paced to each person, and one-to-one music for residents who find groups too much or are cared for in their room. It was founded by a Registered Nurse and musician. Sessions are music facilitation as standard; programs designed and overseen by a Registered Music Therapist are available on request. Aged care providers start with a discovery call.';

$body = '<p>Hertz2Hearts works with aged care providers to bring regular live music to their residents &mdash; weekly, fortnightly or for occasional events. Every program starts with a conversation about the community: residents&rsquo; generation, culture, abilities and favourite songs.</p>'
	. '<h3>Services</h3><ul>'
	. '<li><strong>Group music sessions</strong> &mdash; live music, singalongs and familiar favourites.</li>'
	. '<li><strong>Dementia-friendly sessions</strong> &mdash; paced, adapted and led gently, with no pressure to perform.</li>'
	. '<li><strong>Individual engagement</strong> &mdash; one-to-one music for residents who find groups overwhelming or are cared for in their room.</li>'
	. '<li><strong>Music facilitators</strong> &mdash; screened, credentialed and inducted facilitators who work alongside lifestyle and care teams.</li>'
	. '<li><strong>Registered Music Therapist collaboration</strong> &mdash; on request, for a clinically focused program designed and overseen by an RMT.</li>'
	. '</ul>'
	. '<p><strong>Music facilitation and music therapy are not the same thing.</strong> Hertz2Hearts&rsquo; standard service is music facilitation, which is recreational and wellbeing-focused and is not clinical music therapy. Registered Music Therapy (assessment, goal setting, treatment planning and review) is arranged with qualified Registered Music Therapists when a provider needs it.</p>';

$args = array(
	'post_type'    => 'listing',
	'post_status'  => 'draft',
	'post_title'   => 'Hertz2Hearts',
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

// Fields, by key through the listing editor (never get/update_field by name).
ListingEditor\write( $id, 'website', 'http://hertz2hearts.com.au/' ); // https not live yet
ListingEditor\write( $id, 'booking_url', 'http://hertz2hearts.com.au/' );
ListingEditor\write( $id, 'email', 'info@hertz2hearts.com.au' );
ListingEditor\write( $id, 'phone', '0414 500 720' );
ListingEditor\write( $id, 'kind', 'practice' );
ListingEditor\write( $id, 'format', 'in-person' );

// Creative Therapies first (the primary), Senior Wellness for aged care.
wp_set_object_terms( $id, array( 'creative', 'seniors' ), 'practice' );
update_post_meta( $id, 'primary_practice', 'creative' );
// Mobile across Perth: the city, not a suburb it does not have.
wp_set_object_terms( $id, array( 'perth' ), 'area' );
update_post_meta( $id, 'claim_status', 'unclaimed' );

// Logo: the music-note mark from their own site.
$logo_src = __DIR__ . '/assets/hertz2hearts-logo.png';
if ( is_readable( $logo_src ) && ! get_post_meta( $id, '_oria_h2h_logo', true ) ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$tmp = wp_tempnam( 'hertz2hearts-logo.png' );
	copy( $logo_src, $tmp );
	$att = media_handle_sideload( array( 'name' => 'hertz2hearts-logo.png', 'tmp_name' => $tmp ), $id, 'Hertz2Hearts logo' );
	if ( ! is_wp_error( $att ) ) {
		update_post_meta( (int) $att, '_oria_image_source', 'business-website (supplied by Dale)' );
		ListingEditor\write( $id, 'logo', (int) $att );
		update_post_meta( $id, '_oria_h2h_logo', (int) $att );
	}
}

clean_post_cache( $id );
WP_CLI::success( sprintf( 'Hertz2Hearts is #%d (%s). Review it, then publish.', $id, get_post_status( $id ) ) );
