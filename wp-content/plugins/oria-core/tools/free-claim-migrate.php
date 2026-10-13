<?php
/**
 * Put every approved owner on the free Claimed plan, and list any old paid
 * Claimed subscriptions for Dale to deal with in Stripe.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/free-claim-migrate.php          # dry run
 *   wp eval-file wp-content/plugins/oria-core/tools/free-claim-migrate.php apply    # write
 *
 * Claiming became free on 22 Sep 2026, but with billing switched on a claim
 * approval still left the owner "on the free plan until a payment lands":
 * claimed_by set, claim_status still 'unclaimed'. Those owners were kept out
 * of the editing, photos and analytics they had been told were free. This
 * moves them to 'claimed' and stamps verified_at (approval was the
 * verification). Featured listings are never touched.
 *
 * It does NOT touch Stripe. Listings still carrying a subscription while on
 * the Claimed plan are reported: that subscription is the old $29 Claimed
 * plan, and cancelling or refunding it is Dale's call in the Stripe
 * dashboard. Safe to rerun.
 *
 * @package Oria\Core
 */

defined( 'ABSPATH' ) || exit;

$apply = in_array( 'apply', (array) ( $args ?? array() ), true );

$ids = get_posts(
	array(
		'post_type'      => 'listing',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array( 'key' => 'claimed_by', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
		),
	)
);

$moved = 0;
$paid  = array();
foreach ( $ids as $id ) {
	$id     = (int) $id;
	$status = (string) get_post_meta( $id, 'claim_status', true );
	$owner  = (int) get_post_meta( $id, 'claimed_by', true );
	$sub    = (string) get_post_meta( $id, '_stripe_subscription_id', true );

	if ( ! get_userdata( $owner ) ) {
		WP_CLI::log( sprintf( '  ? #%d %s: owner #%d no longer exists -- left alone', $id, get_the_title( $id ), $owner ) );
		continue;
	}
	if ( 'claimed' === $status && '' !== $sub ) {
		$paid[] = sprintf( '#%d %s (subscription %s)', $id, get_the_title( $id ), $sub );
	}
	if ( in_array( $status, array( 'claimed', 'featured' ), true ) ) {
		continue;
	}
	++$moved;
	WP_CLI::log( sprintf( '  %s #%d %s: %s -> claimed', $apply ? '+' : 'would move', $id, get_the_title( $id ), '' === $status ? '(none)' : $status ) );
	if ( $apply ) {
		update_field( 'claim_status', 'claimed', $id );
		if ( '' === (string) get_post_meta( $id, 'verified_at', true ) ) {
			update_field( 'verified_at', current_time( 'Y-m-d' ), $id );
		}
		clean_post_cache( $id );
		do_action( 'litespeed_purge_post', $id );
	}
}

if ( $paid ) {
	WP_CLI::warning( "These listings are on the Claimed plan with a Stripe subscription -- the old \$29 Claimed plan. Claiming is free now, so cancel (and decide on any refund) in Stripe:\n  " . implode( "\n  ", $paid ) );
}

WP_CLI::success( sprintf( '%s: %d owner(s) %s the free Claimed plan; %d owned listing(s) checked.', $apply ? 'Applied' : 'Dry run', $moved, $apply ? 'moved to' : 'would move to', count( $ids ) ) );
