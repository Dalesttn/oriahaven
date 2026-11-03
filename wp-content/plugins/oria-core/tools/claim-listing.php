<?php
/**
 * Claim a listing for its owner by hand, through the same approval as the
 * claim queue: a claim request is recorded, then approved -- owner account
 * made if needed (WordPress emails a set-password link), listing linked,
 * marked claimed and verified, audit note, and the "Your claim is approved,
 * and it's free" email.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/claim-listing.php <listing-slug> <email> "<Owner name>" [note]
 *
 * EMAILS THE OWNER. Run it only when Dale asks for a listing to be claimed
 * for someone -- never for a scraped address on our own initiative.
 *
 * @package Oria\Core
 */

use Oria\Core\ClaimRequests;

defined( 'ABSPATH' ) || exit;

$slug  = sanitize_title( (string) ( $args[0] ?? '' ) );
$email = sanitize_email( (string) ( $args[1] ?? '' ) );
$name  = sanitize_text_field( (string) ( $args[2] ?? '' ) );
$note  = sanitize_text_field( (string) ( $args[3] ?? 'Claimed by hand at the owner\'s request.' ) );
if ( '' === $slug || ! is_email( $email ) || '' === $name ) {
	WP_CLI::error( 'Usage: claim-listing.php <listing-slug> <email> "<Owner name>" [note]' );
}

$listing = get_page_by_path( $slug, OBJECT, 'listing' );
if ( ! $listing instanceof WP_Post ) {
	WP_CLI::error( "No listing '{$slug}'." );
}
$id    = (int) $listing->ID;
$owner = (int) get_post_meta( $id, 'claimed_by', true );
if ( $owner ) {
	$u = get_userdata( $owner );
	WP_CLI::error( sprintf( '#%d is already claimed by %s. Nothing changed.', $id, $u ? $u->user_email : "user #{$owner}" ) );
}

$request = ClaimRequests\create( $id, $name, $email, '', $note, 'admin' );
if ( ! $request ) {
	WP_CLI::error( 'Could not record the claim request.' );
}
$status = ClaimRequests\approve( $request );
if ( 'approved' !== $status ) {
	WP_CLI::error( "Approval returned '{$status}' (request #{$request} left as it is)." );
}

$user = get_user_by( 'email', $email );
clean_post_cache( $id );
do_action( 'litespeed_purge_post', $id );
WP_CLI::success(
	sprintf(
		'#%d %s is claimed by %s (user #%d, %s account); claim_status %s. Set-password and approval emails sent.',
		$id,
		get_the_title( $id ),
		$email,
		$user ? $user->ID : 0,
		'1' === (string) get_post_meta( $request, '_new_account', true ) ? 'new' : 'existing',
		(string) get_post_meta( $id, 'claim_status', true )
	)
);
