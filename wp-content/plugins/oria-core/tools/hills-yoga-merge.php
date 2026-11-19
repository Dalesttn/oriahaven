<?php
/**
 * Hills Yoga Classes (Helen Grenfell, Helena Valley): fold her sign-up into
 * the listing we already had, move her login to her business address, and
 * tell her. Dale's decisions, 19 Nov 2026.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/hills-yoga-merge.php          (dry run)
 *   wp eval-file wp-content/plugins/oria-core/tools/hills-yoga-merge.php apply    (write + email)
 *
 * - Helen signed up twice today (#901720 empty, #901721 filled in) while
 *   Hills Yoga (#530, hills-yoga-helena-valley, live since August) already
 *   existed. Her details from #901721 are copied onto #530, which keeps
 *   its address and search history; she becomes its owner on the free
 *   Claimed plan; both new submissions go to the bin (restorable).
 * - Her account email changes from Gmail to helen@hillsyogaclasses.com --
 *   the address she gave on the listing itself. WordPress's automatic
 *   "your email changed" notice to the old address is suppressed: she gets
 *   one email, the one Dale approved, at the new address.
 *
 * Refuses to run twice (#530 already owned).
 *
 * @package Oria\Core
 */

use Oria\Core\ListingEditor;
use Oria\Core\Ownership;

defined( 'ABSPATH' ) || exit;

$apply   = 'apply' === (string) ( $args[0] ?? '' );
$target  = 530;
$source  = 901721;
$empty   = 901720;
$user_id = 26;
$new     = 'helen@hillsyogaclasses.com';

$t = get_post( $target );
$s = get_post( $source );
$u = get_userdata( $user_id );
if ( ! $t || 'hills-yoga-helena-valley' !== $t->post_name || ! $s || ! $u ) {
	WP_CLI::error( 'Listings or user not as expected; nothing changed.' );
}
if ( (int) get_post_meta( $target, 'claimed_by', true ) ) {
	WP_CLI::error( "#{$target} already has an owner; nothing changed." );
}
$other = get_user_by( 'email', $new );
if ( $other && (int) $other->ID !== $user_id ) {
	WP_CLI::error( "{$new} already belongs to user #{$other->ID}; nothing changed." );
}

// Everything she filled in on the sign-up, field by field through the
// listing editor (repeaters included). Empty fields on hers leave #530's.
$fields = array(
	'good_for', 'kind', 'services', 'what_to_bring', 'price_from', 'duration_min', 'group_size',
	'opening_hours', 'hours_hide', 'booking_url', 'phone', 'email', 'website', 'transit', 'parking',
	'reasons', 'amenities', 'faq', 'team', 'facebook_url', 'instagram_url',
);
$copy = array();
foreach ( $fields as $f ) {
	$v = ListingEditor\value( $source, $f );
	if ( null === $v || '' === $v || array() === $v || false === $v ) {
		continue;
	}
	$copy[ $f ] = $v;
}
$excerpt = str_replace( 'being.Helen', 'being. Helen', (string) $s->post_excerpt );

WP_CLI::log( ( $apply ? 'APPLY' : 'DRY RUN' ) . " -- #{$source} -> #{$target} ({$t->post_title})" );
WP_CLI::log( '  title    -> ' . $s->post_title );
WP_CLI::log( '  excerpt  -> ' . substr( $excerpt, 0, 90 ) . '...' );
WP_CLI::log( '  fields   -> ' . implode( ', ', array_keys( $copy ) ) );
WP_CLI::log( "  owner    -> user #{$user_id} ({$u->user_email} -> {$new}), claimed, verified today" );
WP_CLI::log( "  bin      -> #{$source}, #{$empty}" );
WP_CLI::log( "  email    -> {$new}" );
if ( ! $apply ) {
	WP_CLI::success( 'Dry run: nothing written or sent. Add "apply".' );
	return;
}

// 1. The listing.
wp_update_post(
	array(
		'ID'           => $target,
		'post_title'   => $s->post_title,
		'post_excerpt' => $excerpt,
		'post_content' => $s->post_content,
	)
);
foreach ( $copy as $f => $v ) {
	ListingEditor\write( $target, $f, $v );
}

// 2. Her account: business email, owner role.
add_filter( 'send_email_change_email', '__return_false' );
$r = wp_update_user( array( 'ID' => $user_id, 'user_email' => $new ) );
if ( is_wp_error( $r ) ) {
	WP_CLI::error( 'Email change failed: ' . $r->get_error_message() . ' (listing fields already copied; rerun is blocked only once she owns #530).' );
}
$u = get_userdata( $user_id );
$u->add_role( Ownership\ROLE );

// 3. Ownership, as a claim approval does it.
update_post_meta( $target, 'claimed_by', $user_id );
update_post_meta( $target, 'claim_status', 'claimed' );
update_post_meta( $target, 'verified_at', current_time( 'Y-m-d' ) );
if ( function_exists( '\Oria\Core\Audit\note' ) ) {
	\Oria\Core\Audit\note( $target, 'Merged in Helen Grenfell\'s own sign-up (#901721) and made her the owner on the free Claimed plan. Her login moved to helen@hillsyogaclasses.com.', 0 );
}

// 4. The duplicates, to the bin (restorable).
foreach ( array( $source, $empty ) as $dup ) {
	delete_post_meta( $dup, '_oria_signup' ); // never fires a "you're live" email
	wp_trash_post( $dup );
}

// 5. The email Dale approved. The branded shell adds his signature.
if ( function_exists( '\Oria\Core\Leads\send' ) ) {
	\Oria\Core\Leads\send(
		$new,
		'Your Oria Haven login is now helen@hillsyogaclasses.com',
		'Your login has changed',
		'<p>Hi Helen,</p>'
		. '<p>Thanks for adding Hills Yoga Classes to Oria Haven.</p>'
		. '<p>We only accept business email addresses for practitioner accounts, so we&rsquo;ve changed your login from your Gmail address to helen@hillsyogaclasses.com, the address on your website. Your password hasn&rsquo;t changed; just sign in with this email from now on.</p>'
		. '<p>We already had a Hills Yoga listing for your Helena Valley studio, so we&rsquo;re bringing your new details into that one rather than publishing a second. We&rsquo;ll let you know when it&rsquo;s live.</p>'
	);
}

clean_post_cache( $target );
do_action( 'litespeed_purge_post', $target );
WP_CLI::success( sprintf( 'Merged into #%d %s; %s owns it; duplicates binned; email sent to %s.', $target, get_permalink( $target ), $new, $new ) );
