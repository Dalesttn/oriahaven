<?php
/**
 * Bring "Claim a listing" form entries into the Claim requests queue.
 *
 * Usage (from the WordPress root):
 *   php wp-content/plugins/oria-core/tools/claims-from-forms.php            # report only
 *   php wp-content/plugins/oria-core/tools/claims-from-forms.php --apply    # create the requests
 *
 * Until September 2026 the general claim form at /claim/ (oria-forms, form
 * id "claim") only saved a Form entry and sent its emails; the request queue
 * under Listings > Claim requests, where claims are approved, was fed only
 * by the claim button on a listing page. New submissions now reach the
 * queue by themselves (ClaimRequests\from_form). This carries the ones that
 * arrived before that across, once.
 *
 * An entry becomes a pending request when it names a listing we can
 * identify (the picked listing's URL, or an exact, unique title match) and
 * there is no request yet for that listing from that email. Nothing is
 * emailed: the claimant already had their confirmation, and approving the
 * request is what sends their log-in. Entries that match no listing are
 * listed so they can be matched by hand.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

use Oria\Core\ClaimRequests;
use Oria\Core\Ownership;

$apply = in_array( '--apply', $argv ?? array(), true );

$entries = get_posts(
	array(
		'post_type'      => 'oria_form_entry',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'ASC',
		'meta_key'       => '_oform_id',
		'meta_value'     => 'claim',
	)
);

echo "\n" . ( $apply ? "APPLY\n" : "DRY RUN -- add --apply to create the requests.\n" );
printf( "%d claim form entr%s found.\n\n", count( $entries ), 1 === count( $entries ) ? 'y' : 'ies' );

$made = 0;
foreach ( $entries as $e ) {
	$v     = (array) get_post_meta( $e->ID, '_oform_values', true );
	$email = sanitize_email( (string) ( $v['email'] ?? '' ) );
	$who   = sprintf( '#%d %s <%s> "%s"', $e->ID, (string) ( $v['name'] ?? '?' ), $email, (string) ( $v['practice'] ?? '' ) );
	$lid   = ClaimRequests\listing_from_form( $v );

	if ( ! $lid ) {
		echo "  NO MATCH  {$who}\n            match it by hand: open the listing and ask them to use its Claim button, or add the request once it is listed.\n";
		continue;
	}
	$title = html_entity_decode( get_the_title( $lid ) );
	if ( ClaimRequests\exists_for( $lid, $email ) ) {
		echo "  SKIP      {$who} -> already in the queue for #{$lid} {$title}\n";
		continue;
	}
	if ( Ownership\is_paid( $lid ) ) {
		echo "  SKIP      {$who} -> #{$lid} {$title} is already owned by a paying member\n";
		continue;
	}

	echo "  ADD       {$who} -> #{$lid} {$title}\n";
	if ( $apply ) {
		$rid = ClaimRequests\create(
			$lid,
			sanitize_text_field( (string) ( $v['name'] ?? '' ) ),
			$email,
			sanitize_text_field( (string) ( $v['phone'] ?? '' ) ),
			sanitize_textarea_field( (string) ( $v['message'] ?? '' ) ),
			'form',
			(int) $e->ID
		);
		if ( $rid ) {
			// Dated as the entry, so the queue shows when they really asked.
			wp_update_post( array( 'ID' => $rid, 'post_date' => $e->post_date, 'post_date_gmt' => $e->post_date_gmt ) );
			++$made;
		}
	} else {
		++$made;
	}
}

printf(
	"\n%d request(s) %s.%s\n\n",
	$made,
	$apply ? 'created' : 'would be created',
	$made ? ' Approve them under Listings > Claim requests.' : ''
);
