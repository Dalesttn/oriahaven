<?php
/**
 * Checks the retreat-offer rules (oria-core/includes/retreats.php) with
 * temporary offers, then removes them. Run:
 *   wp eval-file wp-content/plugins/oria-core/tools/test-retreats.php
 * Every line should read PASS.
 */
use Oria\Core\Retreats as R;

$fail = 0;
$ok   = static function ( string $name, bool $cond ) use ( &$fail ): void {
	$fail += $cond ? 0 : 1;
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . "\n";
};

$saved_opt = get_option( R\OPTION );
update_option( R\OPTION, array( 'enabled' => true, 'review_days' => 30, 'hosts' => array( 'bookretreats.com', 'www.bookretreats.com' ) ) );

$img = (int) ( get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0] ?? 0 );
$AFF = 'https://bookretreats.com/r/test-retreat?aff=AbC123&sub=x%2Fy&utm_source=oria#dates';

$make = static function ( array $meta, string $status = 'publish' ) use ( $img ): int {
	$id = wp_insert_post( array( 'post_type' => R\CPT, 'post_status' => $status, 'post_title' => 'TEST retreat ' . wp_generate_password( 4, false ) ) );
	$base = array(
		'aff_url' => 'https://bookretreats.com/r/test-retreat?aff=AbC123&sub=x%2Fy&utm_source=oria#dates',
		'source_url' => 'https://bookretreats.com/r/test-retreat', 'image_source' => 'BookRetreats listing photo',
		'summary' => 'A quiet weekend in the forest with morning yoga, long walks and simple, shared meals at the lodge.',
		'destination' => 'wa', 'locality' => 'Margaret River', 'length' => 'weekend', 'days' => '3', 'nights' => '2',
		'date_model' => 'check', 'state' => 'active', 'reviewed' => wp_date( 'Y-m-d' ), 'booking' => 'BookRetreats',
		'inclusions' => "2 nights accommodation\nDaily yoga\nAll meals\nAirport transfer", 'exclusions' => 'Flights',
	);
	foreach ( array_merge( $base, $meta ) as $k => $v ) {
		update_post_meta( $id, '_ro_' . $k, wp_slash( $v ) );
	}
	if ( $img ) {
		set_post_thumbnail( $id, $img );
	}
	return (int) $id;
};

$ok( 'an image exists to test with', $img > 0 );

// 1. Link integrity.
$ok( 'exact generated link accepted', R\valid_link( $AFF ) );
$ok( 'http refused', ! R\valid_link( 'http://bookretreats.com/r/x' ) );
$ok( 'other host refused', ! R\valid_link( 'https://evil.example/r/x?go=bookretreats.com' ) );
$ok( 'lookalike host refused', ! R\valid_link( 'https://bookretreats.com.evil.example/x' ) );
$ok( 'javascript: refused', ! R\valid_link( 'javascript:alert(1)' ) );
$ok( 'whitespace refused', ! R\valid_link( 'https://bookretreats.com/r/x y' ) );
$ok( 'credentials refused', ! R\valid_link( 'https://user:pw@bookretreats.com/r/x' ) );
$ok( 'backslash refused', ! R\valid_link( 'https://bookretreats.com/r/x\\y' ) );

$a = $make( array() );
$ok( 'stored link is byte-identical', $AFF === R\get( $a, 'aff_url' ) );
$ok( 'complete active offer is eligible', R\eligible( $a ) );
$html = R\card( $a, 'test' );
preg_match( '/<a class="ro-card__cta" href="([^"]+)"/', $html, $m );
$ok( 'rendered href decodes to the exact link', html_entity_decode( $m[1] ?? '', ENT_QUOTES ) === $AFF );
$ok( 'card link is rel="sponsored noopener"', false !== strpos( $html, 'rel="sponsored noopener"' ) );
$ok( 'card link opens in a new tab', false !== strpos( $html, 'target="_blank"' ) );
$ok( 'card says affiliate link', false !== stripos( $html, 'affiliate link' ) );
$ok( 'card keeps the full inclusions behind "A closer look"', false !== strpos( $html, 'A closer look' ) && false !== strpos( $html, 'class="ro-card__inc"' ) );
$ok( 'card shows exclusions', false !== strpos( $html, 'Not included' ) && false !== strpos( $html, '<li>Flights</li>' ) );

// 2. What is kept off the page.
$b = $make( array( 'aff_url' => '' ) );
$ok( 'missing link: not eligible, no card', ! R\eligible( $b ) && '' === trim( R\card( $b, 'test' ) ) );
$c = $make( array( 'state' => 'paused' ) );
$ok( 'paused offer excluded', ! R\eligible( $c ) );
$d = $make( array(), 'draft' );
$ok( 'draft offer excluded', ! R\eligible( $d ) );
$e = $make( array( 'date_model' => 'fixed', 'start' => '2026-01-01', 'end' => '2026-01-05' ) );
$ok( 'past fixed departure excluded', ! R\eligible( $e ) && R\expired( $e ) );
$f = $make( array( 'date_model' => 'multiple', 'start' => '2026-01-01', 'end' => wp_date( 'Y-m-d', time() + 60 * DAY_IN_SECONDS ) ) );
$ok( 'several departures with a future last date stay', R\eligible( $f ) );
$g = $make( array( 'summary' => 'Too short.' ) );
$ok( 'thin summary excluded', ! R\eligible( $g ) );

// 3. Prices.
$h = $make( array( 'price_mode' => 'from', 'price_amount' => '1450', 'price_currency' => 'USD', 'price_basis' => 'per person, twin share', 'price_checked' => wp_date( 'Y-m-d', time() - 5 * DAY_IN_SECONDS ) ) );
// A fixed rate, so the test does not depend on today's market or the network.
set_transient( 'oria_fx_USD', 1.5, HOUR_IN_SECONDS );
$ok( 'fresh USD price shows about A$ (rounded to $10) with its basis', 'From about A$2,180 per person, twin share' === R\price_label( $h ) );
$ok( 'source note keeps the provider\'s own figure', 0 === strpos( R\source_note( $h, 'BookRetreats' ), 'Listed as US$1,450 on BookRetreats' ) );
set_transient( 'oria_fx_USD', 0, HOUR_IN_SECONDS );
delete_option( 'oria_fx_last' );
$ok( 'no trustworthy rate keeps the source currency', 'From US$1,450 per person, twin share' === R\price_label( $h ) && '' === R\source_note( $h, 'BookRetreats' ) );
delete_transient( 'oria_fx_USD' );
$i = $make( array( 'price_mode' => 'from', 'price_amount' => '900', 'price_currency' => 'AUD', 'price_checked' => wp_date( 'Y-m-d', time() - 45 * DAY_IN_SECONDS ) ) );
$ok( 'stale price shows Check current price', 'Check current price' === R\price_label( $i ) );
$ok( 'no price shows Check current price', 'Check current price' === R\price_label( $a ) );

// 4. Destinations stay apart.
$j = $make( array( 'destination' => 'bali', 'locality' => 'Ubud', 'length' => 'longer', 'days' => '7', 'nights' => '6' ) );
$wa = R\active_offers( array( 'destination' => 'wa' ) );
$ok( 'Bali offer never in WA results', ! in_array( $j, $wa, true ) );
$ok( 'Bali offer listed under Bali', in_array( $j, R\active_offers( array( 'destination' => 'bali' ) ), true ) );
$listing_ids = array_map( static fn( $r ) => (int) ( $r['id'] ?? 0 ), (array) ( \Oria\Theme\listing_data()['listings'] ?? array() ) );
$ok( 'offers never enter the directory data', ! array_intersect( array( $a, $j ), $listing_ids ) );

// 5. The switch.
update_option( R\OPTION, array( 'enabled' => false ) );
$ok( 'switched off: nothing active', array() === R\active_offers() );
update_option( R\OPTION, array( 'enabled' => true, 'review_days' => 30 ) );

// 6. Publishing an incomplete Active offer keeps it a draft.
wp_set_current_user( 1 ); // the nonce belongs to the user who saves
$_POST['oria_ro_nonce'] = wp_create_nonce( 'oria_ro_save' );
$_POST['ro']            = array( 'state' => 'active', 'summary' => 'short' );
$_POST['_thumbnail_id'] = 0;
$k = wp_insert_post( array( 'post_type' => R\CPT, 'post_status' => 'publish', 'post_title' => 'TEST incomplete' ) );
$ok( 'incomplete Active offer saved as draft', 'draft' === get_post_status( $k ) );
$_POST['ro'] = array( 'state' => 'draft' );
$l = wp_insert_post( array( 'post_type' => R\CPT, 'post_status' => 'draft', 'post_title' => 'TEST draft ok' ) );
$ok( 'incomplete draft can still be saved', $l > 0 && 'draft' === get_post_status( $l ) );
unset( $_POST['oria_ro_nonce'], $_POST['ro'], $_POST['_thumbnail_id'] );

// 7. A journey shows only offers an editor chose, and only live ones.
$jr = wp_insert_post( array( 'post_type' => 'journey', 'post_status' => 'draft', 'post_title' => 'TEST journey' ) );
$ok( 'journey with nothing chosen shows nothing', '' === R\related( $jr ) );
update_post_meta( $jr, R\RELATED, $b . ',' . $d );
$ok( 'journey with only non-live offers shows nothing', '' === R\related( $jr ) );
update_post_meta( $jr, R\RELATED, $j . ',' . $b . ',' . $a );
$rel = R\related( $jr );
$ok( 'journey shows the chosen live offers, disclosed', 2 === substr_count( $rel, 'class="ro-card"' ) && false !== strpos( $rel, 'ro-disclosure' ) );
$ok( 'journey keeps the editor order', strpos( $rel, 'ro-offer-' . $j ) < strpos( $rel, 'ro-offer-' . $a ) );
$ok( 'journey clicks are placed as journey', false !== strpos( $rel, 'data-oria-aff-place="journey"' ) );
wp_delete_post( $jr, true );

// Clean up.
foreach ( get_posts( array( 'post_type' => R\CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 's' => 'TEST' ) ) as $x ) {
	wp_delete_post( (int) $x, true );
}
false === $saved_opt ? delete_option( R\OPTION ) : update_option( R\OPTION, $saved_opt );
echo $fail ? "FAILURES: $fail\n" : "all retreat checks pass\n";
