<?php
/**
 * The "Best music for aged care in Perth" Best Of guide, with Hertz2Hearts
 * as its lead pick -- the source of the "Best music for aged care" badge.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/add-best-music-aged-care.php            # guide as a draft
 *   wp eval-file wp-content/plugins/oria-core/tools/add-best-music-aged-care.php publish    # publish guide AND the Hertz2Hearts listing
 *
 * Run add-hertz2hearts.php first. Badges only come from published guides
 * with published picks, so "publish" does both at once: a published guide
 * whose only pick is a draft would be an empty page.
 *
 * Wording follows Hertz2Hearts' own site: music facilitation as standard,
 * Registered Music Therapy on request -- never "music therapy" as the award.
 * Safe to rerun: updates the guide it made.
 *
 * @package Oria\Core
 */

defined( 'ABSPATH' ) || exit;

$publish = in_array( 'publish', (array) ( $args ?? array() ), true );

$listing = get_page_by_path( 'hertz2hearts', OBJECT, 'listing' );
if ( ! $listing instanceof WP_Post ) {
	WP_CLI::error( 'Run add-hertz2hearts.php first: the Hertz2Hearts listing does not exist.' );
}

$slug  = 'music-aged-care-perth';
$guide = get_page_by_path( $slug, OBJECT, 'best_of' );
$data  = array(
	'post_type'    => 'best_of',
	'post_name'    => $slug,
	'post_title'   => 'Best music for aged care in Perth',
	'post_excerpt' => 'Live music programs that aged care communities in Perth can bring in for their residents: group singalongs, dementia-friendly sessions and one-to-one music.',
	'post_status'  => $publish ? 'publish' : ( $guide ? $guide->post_status : 'draft' ),
);
if ( $guide ) {
	$data['ID'] = $guide->ID;
	$gid        = (int) wp_update_post( $data );
} else {
	$gid = (int) wp_insert_post( $data );
}
if ( ! $gid ) {
	WP_CLI::error( 'Could not save the guide.' );
}

update_field( 'field_oria_bo_intro', '<p>For aged care providers looking to bring regular live music to their residents. This shortlist is for services that come to the community &mdash; group sessions, dementia-friendly sessions and one-to-one music &mdash; and says plainly whether a program is music facilitation or Registered Music Therapy, because they are not the same thing.</p>', $gid );
update_field( 'field_oria_bo_quick', 'Hertz2Hearts runs group, dementia-friendly and one-to-one music sessions in aged care communities around Perth. It was founded by a Registered Nurse and musician; its standard service is music facilitation, with programs designed by a Registered Music Therapist available on request.', $gid );
update_field( 'field_oria_bo_reviewed', wp_date( 'Ymd' ), $gid );
$creative = get_term_by( 'slug', 'creative', 'practice' );
if ( $creative instanceof WP_Term ) {
	update_field( 'field_oria_bo_practice', (int) $creative->term_id, $gid ); // shows in Creative Therapies' Best Of shelf
}
update_field(
	'field_oria_bo_entries',
	array(
		array(
			'field_oria_bo_entry_listing'    => (int) $listing->ID,
			'field_oria_bo_entry_award'      => 'best_music_aged_care',
			'field_oria_bo_entry_label'      => '',
			'field_oria_bo_entry_best_for'   => 'Aged care communities',
			'field_oria_bo_entry_reason'     => 'Built around the residents in the room: sessions are shaped to their generation, culture, abilities and favourite songs, run alongside the lifestyle and care team, and booked weekly, fortnightly or for occasional events. Founded by a Registered Nurse and musician, and clear about the difference between music facilitation and clinical music therapy.',
			'field_oria_bo_entry_highlights' => "Group music sessions and singalongs\nDementia-friendly sessions, paced and adapted\nOne-to-one music for residents in their room\nRegistered Music Therapist programs on request",
			'field_oria_bo_entry_price'      => 'Price on enquiry: providers start with a free discovery call',
			'field_oria_bo_entry_spot'       => 'Our pick',
			'field_oria_bo_entry_lead'       => 1,
		),
	),
	$gid
);

if ( $publish ) {
	wp_update_post( array( 'ID' => (int) $listing->ID, 'post_status' => 'publish' ) );
}
delete_transient( \Oria\Core\BestOf\INDEX_KEY );
do_action( 'litespeed_purge_post', $gid );
do_action( 'litespeed_purge_post', (int) $listing->ID );

WP_CLI::success( sprintf( 'Guide #%d (%s) at %s; Hertz2Hearts is %s.', $gid, get_post_status( $gid ), get_permalink( $gid ), get_post_status( $listing->ID ) ) );
