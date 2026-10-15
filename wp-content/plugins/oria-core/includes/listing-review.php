<?php
/**
 * The other side of the listing editor's waiting room.
 *
 * A field the registry marks 'review' => true (the street address, for now)
 * is not written when an owner saves it: ListingEditor\set_pending() parks
 * the proposed value in _oria_pending and the live value stays put. Until
 * this file there was nothing that ever took it out again, so an owner who
 * moved premises waited forever.
 *
 *   waiting()            listings with a change waiting, oldest first
 *   approve() / decline()  one listing's waiting changes, all at once, with
 *                        an email to the owner either way
 *   page()               Listings > Listing changes, a card per listing
 *                        showing what is live beside what was proposed
 *
 * Approving writes through ListingEditor\write(), which resolves the ACF key
 * from the listing group -- never update_field by name out here. An approved
 * address also moves the map pin: the old coordinates point at the old door,
 * so the listing is geocoded again, and if that fails the stale pin is
 * dropped rather than left pointing somewhere the business no longer is.
 *
 * Emails here are transactional (the owner asked for the change), so the
 * outreach pause does not apply.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\ListingReview;

use Oria\Core\PostTypes;
use Oria\Core\ListingEditor as Ed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PAGE = 'oria-listing-changes';

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_listing_decide', __NAMESPACE__ . '\handle_decide' );
}
bootstrap();

/** The admin screen, for links in emails. */
function page_url(): string {
	return admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=' . PAGE );
}

/**
 * The waiting values that can still be acted on: a field since dropped from
 * the registry, or no longer marked for review, is not something to publish.
 *
 * @return array<string, array{value:mixed, at:int, by:int}>
 */
function changes( int $listing ): array {
	$fields = Ed\writable_fields();
	$out    = array();
	foreach ( Ed\pending( $listing ) as $name => $row ) {
		if ( is_array( $row ) && array_key_exists( 'value', $row ) && ! empty( $fields[ $name ]['review'] ) ) {
			$out[ (string) $name ] = $row;
		}
	}
	return $out;
}

/** Listing ids with a change waiting, oldest first. */
function waiting(): array {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", Ed\PENDING_META ) ) );
	$ids = array_values( array_filter( array_unique( $ids ), static fn( int $id ): bool => (bool) changes( $id ) ) );
	$at  = static fn( int $id ): int => (int) min( array_map( static fn( array $r ): int => (int) ( $r['at'] ?? 0 ), changes( $id ) ) );
	usort( $ids, static fn( int $a, int $b ): int => $at( $a ) <=> $at( $b ) );
	return $ids;
}

/** The label the owner saw. */
function label( string $name ): string {
	return (string) ( Ed\writable_fields()[ $name ]['label'] ?? $name );
}

/** Publish every waiting change on this listing, and tell the owner. */
function approve( int $listing ): bool {
	$changes = changes( $listing );
	if ( ! $changes ) {
		return false;
	}
	$labels = array();
	foreach ( $changes as $name => $row ) {
		Ed\write( $listing, $name, $row['value'] );
		Ed\clear_pending( $listing, $name );
		$labels[] = label( $name );
	}
	if ( isset( $changes['address'] ) ) {
		repin( $listing );
	}
	do_action( 'litespeed_purge_post', $listing );
	audit( $listing, sprintf( /* translators: %s: field labels */ __( 'Approved owner change to %s', 'oria' ), implode( ', ', $labels ) ) );
	tell_owner(
		$listing,
		__( 'Your listing change is live on Oria Haven', 'oria' ),
		sprintf(
			"Thanks for keeping your listing up to date. We've checked and published your change to: %s.\n\nYou can see your profile here:\n%s\n",
			implode( ', ', $labels ),
			get_permalink( $listing )
		)
	);
	return true;
}

/** Turn every waiting change down, with a reason the owner can act on. */
function decline( int $listing, string $reason ): bool {
	$changes = changes( $listing );
	if ( ! $changes ) {
		return false;
	}
	$labels = array();
	foreach ( array_keys( $changes ) as $name ) {
		Ed\clear_pending( $listing, $name );
		$labels[] = label( $name );
	}
	audit( $listing, sprintf( /* translators: %s: field labels */ __( 'Declined owner change to %s', 'oria' ), implode( ', ', $labels ) ) . ( '' !== $reason ? ': ' . $reason : '' ) );
	tell_owner(
		$listing,
		__( 'About the change to your Oria Haven listing', 'oria' ),
		sprintf(
			"Thanks for updating your listing. We couldn't publish your change to %s as it stands%s\n\nYour listing still shows what it did before. You can change it and save it again in My Oria, and we'll take another look:\n%s\n",
			implode( ', ', $labels ),
			'' !== $reason ? ":\n\n" . $reason : '.',
			add_query_arg( 'section', (string) ( Ed\writable_fields()[ array_key_first( $changes ) ]['section'] ?? '' ), \Oria\Core\MyOria\url( 'listing-edit' ) )
		)
	);
	return true;
}

/**
 * Move the map pin to the new address. A failed lookup drops the old pin:
 * no position is better than one at the premises they left.
 */
function repin( int $listing ): void {
	if ( ! function_exists( '\Oria\Core\Geo\geocode' ) ) {
		return;
	}
	if ( null === \Oria\Core\Geo\geocode( $listing ) ) {
		foreach ( array( \Oria\Core\Geo\META_LAT, \Oria\Core\Geo\META_LNG, \Oria\Core\Geo\META_PRECISION, \Oria\Core\Geo\META_STAMP ) as $k ) {
			delete_post_meta( $listing, $k );
		}
	}
}

function tell_owner( int $listing, string $subject, string $body ): void {
	$owner = get_userdata( (int) get_post_meta( $listing, 'claimed_by', true ) );
	if ( ! $owner || ! is_email( $owner->user_email ) ) {
		return;
	}
	wp_mail( $owner->user_email, $subject, $body . "\n" . __( 'Oria Haven', 'oria' ) . "\n" );
}

function audit( int $listing, string $text ): void {
	if ( function_exists( '\Oria\Core\Audit\note' ) ) {
		\Oria\Core\Audit\note( $listing, $text, get_current_user_id() );
	}
}

/* ------------------------------------------------------------- admin page */

function menu(): void {
	$n     = count( waiting() );
	$label = __( 'Listing changes', 'oria' ) . ( $n ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '' );
	add_submenu_page( 'edit.php?post_type=' . PostTypes\LISTING, __( 'Listing changes', 'oria' ), $label, 'manage_options', PAGE, __NAMESPACE__ . '\page' );
}

/** A value as the table shows it. */
function show( $v ): string {
	if ( is_array( $v ) ) {
		$v = implode( ', ', array_map( 'strval', $v ) );
	}
	$v = trim( (string) $v );
	return '' === $v ? '<em>' . esc_html__( '(empty)', 'oria' ) . '</em>' : nl2br( esc_html( $v ) );
}

function page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$ids = waiting();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only.
	$done = sanitize_key( (string) ( $_GET['done'] ?? '' ) );

	echo '<div class="wrap"><h1>' . esc_html__( 'Listing changes', 'oria' ) . '</h1>';
	echo '<p style="max-width:62rem">' . esc_html__( 'Some changes an owner makes are checked before they go live; the street address is one. The listing keeps showing the current value until you approve. Check the new address is a real place the business operates from (their website or Google listing should agree) and it is in the suburb the listing is filed under.', 'oria' ) . '</p>';
	if ( 'approved' === $done ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Approved. The change is live and the owner has been emailed.', 'oria' ) . '</p></div>';
	} elseif ( 'declined' === $done ) {
		echo '<div class="notice notice-info"><p>' . esc_html__( 'Declined. The owner has been emailed with your reason.', 'oria' ) . '</p></div>';
	}
	if ( ! $ids ) {
		echo '<p><strong>' . esc_html__( 'Nothing waiting.', 'oria' ) . '</strong></p></div>';
		return;
	}
	foreach ( $ids as $id ) {
		$changes = changes( $id );
		$first   = reset( $changes );
		$owner   = get_userdata( (int) ( $first['by'] ?? 0 ) );
		echo '<div class="card" style="max-width:62rem;padding:1rem 1.25rem;margin:1rem 0">';
		printf(
			'<h2 style="margin-top:0">%s</h2><p>%s · <a href="%s">%s</a> · <a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_html( html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) ),
			esc_html( sprintf( /* translators: 1: owner, 2: date */ __( 'From %1$s, %2$s', 'oria' ), $owner ? $owner->display_name . ' (' . $owner->user_email . ')' : __( 'the owner', 'oria' ), wp_date( 'j M Y, g:i a', (int) ( $first['at'] ?? 0 ) ) ) ),
			esc_url( (string) get_edit_post_link( $id ) ),
			esc_html__( 'Edit listing', 'oria' ),
			esc_url( (string) get_permalink( $id ) ),
			esc_html__( 'View profile', 'oria' )
		);
		echo '<table class="widefat striped"><thead><tr><th style="width:14rem"></th><th>' . esc_html__( 'Live now', 'oria' ) . '</th><th>' . esc_html__( 'Proposed', 'oria' ) . '</th></tr></thead><tbody>';
		foreach ( $changes as $name => $row ) {
			echo '<tr><th>' . esc_html( label( $name ) ) . '</th><td>' . show( Ed\value( $id, $name ) ) . '</td><td><strong>' . show( $row['value'] ) . '</strong></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- show() escapes
		}
		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1rem;display:grid;gap:.5rem;max-width:40rem">';
		echo '<input type="hidden" name="action" value="oria_listing_decide"><input type="hidden" name="listing" value="' . (int) $id . '">';
		wp_nonce_field( 'oria_listing_decide_' . $id );
		echo '<label for="oria-reason-' . (int) $id . '">' . esc_html__( 'Reason, if you decline (sent to the owner)', 'oria' ) . '</label>';
		echo '<textarea id="oria-reason-' . (int) $id . '" name="reason" rows="3" class="large-text"></textarea>';
		echo '<p style="margin:0;display:flex;gap:.5rem"><button class="button button-primary" name="decision" value="approve">' . esc_html__( 'Approve and publish', 'oria' ) . '</button><button class="button" name="decision" value="decline">' . esc_html__( 'Decline', 'oria' ) . '</button></p>';
		echo '</form></div>';
	}
	echo '</div>';
}

function handle_decide(): void {
	$id = (int) ( $_POST['listing'] ?? 0 );
	if ( ! current_user_can( 'manage_options' ) || ! $id ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ), 403 );
	}
	check_admin_referer( 'oria_listing_decide_' . $id );
	$decision = sanitize_key( (string) ( $_POST['decision'] ?? '' ) );
	$reason   = sanitize_textarea_field( wp_unslash( (string) ( $_POST['reason'] ?? '' ) ) );
	$done     = '';
	if ( 'approve' === $decision && approve( $id ) ) {
		$done = 'approved';
	} elseif ( 'decline' === $decision && decline( $id, $reason ) ) {
		$done = 'declined';
	}
	wp_safe_redirect( add_query_arg( 'done', $done, page_url() ) );
	exit;
}
