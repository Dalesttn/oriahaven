<?php
/**
 * Report the true state of a claim. Reads only; writes nothing, ever.
 *
 * Usage (from the WordPress root, on the server):
 *   php wp-content/plugins/oria-core/tools/claim-check.php <listing-slug>
 *   php wp-content/plugins/oria-core/tools/claim-check.php --all
 *
 * A claim is two separate facts and the badge on the page only shows one
 * of them, which is why "it says Claimed" is not an answer to "did the
 * claim work".
 *
 *   claimed_by   WHO owns it. Set on approval. This is the claim.
 *   claim_status WHAT THEY PAY. Set by the Stripe webhook -- or, when
 *                billing is not configured, by the approval itself.
 *
 * So a listing can show the Claimed badge for two completely different
 * reasons: a payment landed, or billing was never switched on and
 * approval handed out the paid tier. This script says which, because the
 * difference is $29 a month and neither the page nor the admin column
 * distinguishes them.
 *
 * It also checks the things that are silently wrong rather than visibly
 * wrong: an owner whose user account lost the practitioner role can log
 * in and see nothing; an approved claim whose email no longer matches the
 * owner's account means two people are involved; a paid status with no
 * Stripe subscription id means the tier was set by hand.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

use Oria\Core\Billing;
use Oria\Core\ClaimRequests;
use Oria\Core\Ownership;
use Oria\Core\PostTypes;
use Oria\Core\Tiers;

$args = array_slice( $argv ?? array(), 1 );
$all  = in_array( '--all', $args, true );
$slug = '';
foreach ( $args as $a ) {
	if ( '' !== $a && '-' !== $a[0] ) {
		$slug = $a;
	}
}

if ( ! $all && '' === $slug ) {
	fwrite( STDERR, "Which listing? Pass a slug, or --all for every claimed one.\n" );
	exit( 1 );
}

$billing = Billing\configured();

echo "\n";
printf( "Billing: %s\n", $billing ? 'configured -- a Claimed badge means a payment landed' : 'NOT configured -- approval itself grants the Claimed tier, free' );
echo str_repeat( '-', 72 ) . "\n";

if ( $all ) {
	$ids = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => 'claimed_by', 'compare' => 'EXISTS' ),
				array( 'key' => 'claimed_by', 'value' => array( '', '0' ), 'compare' => 'NOT IN' ),
			),
		)
	);
	if ( ! $ids ) {
		echo "No listing has an owner yet.\n\n";
		exit( 0 );
	}
	printf( "%d listing%s with an owner.\n\n", count( $ids ), 1 === count( $ids ) ? '' : 's' );
	foreach ( $ids as $id ) {
		report( (int) $id, $billing );
	}
	exit( 0 );
}

$post = get_page_by_path( $slug, OBJECT, PostTypes\LISTING );
if ( ! $post instanceof WP_Post ) {
	fwrite( STDERR, "No listing with the slug \"{$slug}\".\n" );
	exit( 1 );
}
report( (int) $post->ID, $billing );

/**
 * Everything known about one listing's ownership, and what is wrong with it.
 */
function report( int $id, bool $billing ): void {
	$title  = get_the_title( $id );
	$owner  = (int) get_post_meta( $id, 'claimed_by', true );
	$status = (string) get_post_meta( $id, 'claim_status', true );
	$tier   = Tiers\tier( $id );
	$seen   = (string) get_post_meta( $id, 'verified_at', true );
	$sub    = (string) get_post_meta( $id, '_stripe_subscription_id', true );
	$cust   = (string) get_post_meta( $id, '_stripe_customer_id', true );

	printf( "%s  (#%d, %s)\n", $title, $id, get_post_status( $id ) );
	printf( "  %s\n", get_permalink( $id ) );
	echo "\n";

	// ---- the owner -----------------------------------------------------
	$user   = $owner ? get_userdata( $owner ) : false;
	$faults = array();

	if ( ! $owner ) {
		echo "  Owner            none -- claimed_by is empty, so nobody owns this\n";
		$faults[] = 'No owner. The badge, if there is one, was set by hand.';
	} elseif ( ! $user instanceof WP_User ) {
		printf( "  Owner            #%d -- NO SUCH USER\n", $owner );
		$faults[] = 'claimed_by points at a user account that no longer exists.';
	} else {
		printf( "  Owner            %s <%s>  (user #%d)\n", $user->display_name, $user->user_email, $user->ID );
		printf( "  Roles            %s\n", implode( ', ', $user->roles ) ?: '(none)' );
		if ( ! in_array( Ownership\ROLE, (array) $user->roles, true ) && ! user_can( $user, 'manage_options' ) ) {
			$faults[] = 'The owner does not have the practitioner role, so logging in shows them nothing to edit.';
		}
		// Nothing records a last log-in, so the registration date is the
		// only honest signal that an account was actually made for her.
		printf( "  Account made     %s\n", mysql2date( 'Y-m-d H:i', $user->user_registered ) );
	}

	// ---- the plan ------------------------------------------------------
	echo "\n";
	printf( "  claim_status     %s\n", '' !== $status ? $status : '(empty)' );
	printf( "  Tier in force    %s\n", $tier );
	printf( "  Verified at      %s\n", '' !== $seen ? $seen : '(never stamped)' );
	printf( "  Stripe           %s\n", '' !== $sub ? $sub . ( '' !== $cust ? ' / ' . $cust : '' ) : 'no subscription recorded' );

	if ( 'unclaimed' === $tier && $owner ) {
		echo "\n  READS AS: free plan. She owns it and pays nothing, which is\n";
		echo "  the intended free tier -- but the public badge will say\n";
		echo "  Unclaimed unless display_status() is covering for it.\n";
	} elseif ( 'unclaimed' !== $tier && $billing && '' === $sub ) {
		$faults[] = sprintf( 'Status is "%s" but no Stripe subscription is recorded -- nobody is being billed for it.', $status );
	} elseif ( 'unclaimed' !== $tier && ! $billing ) {
		echo "\n  READS AS: the Claimed tier, granted free because billing is not\n";
		echo "  configured. She has every paid feature and there is no\n";
		echo "  subscription behind it. That is the code working as written,\n";
		echo "  not a mistake -- but it is not a paying customer.\n";
	} elseif ( 'unclaimed' !== $tier && $billing && '' !== $sub ) {
		echo "\n  READS AS: a paying subscriber.\n";
	}

	// ---- what the account can actually do ------------------------------
	/*
	 * Opening the listing needs only claimed_by -- scope_to_own_listing()
	 * lets any approved owner in and tiers.php decides field by field what
	 * they may change. manages() is a stricter test (owner AND paying) used
	 * for review replies and events, so on the free plan it is false BY
	 * DESIGN and is not a fault. It is only wrong when the plan is paid.
	 */
	if ( $user instanceof WP_User ) {
		$manages = Ownership\manages( $user->ID, $id );
		echo "
";
		printf( "  Can edit listing %s
", $owner === $user->ID ? 'yes' : 'NO' );
		printf( "  Review replies   %s
", $manages ? 'yes' : 'no (paid feature)' );
		printf( "  Lands on         #%d at log-in
", Ownership\owned_listing( $user->ID ) );
		if ( ! $manages && 'unclaimed' !== $tier ) {
			$faults[] = 'The plan is paid but manages() is false -- review replies and events will be refused.';
		}
		if ( Ownership\owned_listing( $user->ID ) !== $id ) {
			$faults[] = sprintf( 'She owns more than one listing; log-in sends her to #%d, not this one.', Ownership\owned_listing( $user->ID ) );
		}
	}

	// ---- the request row -----------------------------------------------
	$reqs = get_posts(
		array(
			'post_type'      => ClaimRequests\CPT,
			'post_status'    => 'any',
			'posts_per_page' => 5,
			'meta_key'       => '_listing_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => (string) $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	echo "\n";
	if ( ! $reqs ) {
		echo "  Claim request    none on file\n";
		if ( $owner ) {
			$faults[] = 'There is an owner but no claim request -- this was linked by hand, not through the claim form.';
		}
	}
	foreach ( $reqs as $r ) {
		$rstatus = (string) get_post_meta( $r->ID, '_status', true );
		$remail  = (string) get_post_meta( $r->ID, '_email', true );
		$ruser   = (int) get_post_meta( $r->ID, '_approved_user', true );
		printf(
			"  Claim request    #%d  %s  %s  <%s>%s\n",
			$r->ID,
			get_the_date( 'Y-m-d', $r ),
			'' !== $rstatus ? $rstatus : 'pending',
			$remail,
			$ruser ? sprintf( '  -> user #%d', $ruser ) : ''
		);
		if ( 'approved' === $rstatus && $user instanceof WP_User && strcasecmp( $remail, $user->user_email ) !== 0 ) {
			$faults[] = sprintf( 'The approved request was from %s but the owner account is %s -- two different people.', $remail, $user->user_email );
		}
		if ( 'pending' === $rstatus || '' === $rstatus ) {
			$faults[] = sprintf( 'Request #%d is still pending -- it was never approved or declined.', $r->ID );
		}
	}

	// ---- has she done anything with it ---------------------------------
	$gallery = (array) ( get_field( 'gallery', $id ) ?: array() );
	$hours   = get_field( 'opening_hours', $id );
	$excerpt = (string) get_post_field( 'post_excerpt', $id, 'raw' );
	echo "\n";
	printf( "  Own photos       %d (limit %d)\n", count( $gallery ), Tiers\gallery_limit( $id ) );
	printf( "  Own hours        %s\n", $hours ? 'yes' : 'no -- still showing the Google hours' );
	printf( "  Description      %d characters\n", strlen( $excerpt ) );

	// ---- verdict --------------------------------------------------------
	echo "\n";
	if ( $faults ) {
		echo "  PROBLEMS\n";
		foreach ( $faults as $f ) {
			echo "    - " . $f . "\n";
		}
	} else {
		echo "  Nothing wrong with the claim itself.\n";
	}
	echo "\n" . str_repeat( '-', 72 ) . "\n";
}
