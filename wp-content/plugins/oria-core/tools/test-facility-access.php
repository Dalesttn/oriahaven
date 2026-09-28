<?php
/**
 * Checks the facility-access rules (includes/facility-access.php) with a
 * temporary listing, then removes it. Run:
 *   wp eval-file wp-content/plugins/oria-core/tools/test-facility-access.php
 */
use Oria\Core\FacilityAccess as FA;

$fail = 0;
$ok   = static function ( string $name, bool $cond ) use ( &$fail ): void {
	$fail += $cond ? 0 : 1;
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . "\n";
};

$id = (int) wp_insert_post( array( 'post_type' => 'listing', 'post_status' => 'draft', 'post_title' => 'TEST facility venue' ) );
$k  = 'field_oria_fa_';
$put = static function ( array $offers, array $extra = array() ) use ( $id, $k ): void {
	$row = array_merge(
		array( $k . 'facility' => 'steam-room', $k . 'status' => 'confirmed', $k . 'setting' => 'shared', $k . 'casual' => 'yes', $k . 'min_age' => '16', $k . 'checked' => '20260928' ),
		$extra
	);
	$row[ $k . 'offers' ] = array_map(
		static fn( array $o ): array => array(
			$k . 'o_product'    => $o[0],
			$k . 'o_kind'       => $o[1],
			$k . 'o_price'      => $o[2],
			$k . 'o_basis'      => $o[3] ?? '',
			$k . 'o_duration'   => $o[4] ?? '',
			$k . 'o_conditions' => $o[5] ?? '',
			$k . 'o_main'       => $o[6] ?? 0,
		),
		$offers
	);
	update_field( 'field_oria_fa_rows', array( $row ), $id );
	wp_cache_flush();
};

$ok( 'no row: no summary', null === FA\summary( $id, 'steam-room' ) );

// A membership is never the entry price; an unknown price is never $0.
$put( array( array( 'Unlimited', 'membership', '49', 'per week' ), array( 'Casual', 'casual_visit', '', 'per adult' ) ) );
$s = FA\summary( $id, 'steam-room' );
$ok( 'membership skipped for the main offer', 'Casual' === ( $s['product'] ?? '' ) );
$ok( 'unknown price stays null, labelled honestly', null === $s['price'] && 'Check current price' === $s['price_text'] );

// Treatment access is shown as such, never as free.
$put( array( array( 'Aqua Retreat', 'treatment_bundle', '', '', '', 'Treatment priced separately', 1 ) ), array( $k . 'casual' => 'no', $k . 'condition' => 'With a 60-minute treatment' ) );
$s = FA\summary( $id, 'steam-room' );
$ok( 'treatment bundle reads "Included with a treatment"', 'Included with a treatment' === $s['price_text'] );
$ok( 'entry condition shown when not casual', 0 === strpos( $s['access'], 'With a 60-minute treatment' ) );
$ok( 'no casual visit claimed', 'no' === $s['casual'] );

// A priced main offer, with conditions that repeat the access line trimmed.
$put( array( array( 'Bathhouse', 'bathhouse_session', '89', 'per person', '90', '18+; swimwear required; weekends only', 1 ), array( 'For two', 'couple_or_group', '178', 'per two people', '90' ) ) );
$s = FA\summary( $id, 'steam-room' );
$ok( 'marked offer is the main one', '$89 per person' === $s['price_text'] && 89.0 === $s['price'] );
$ok( 'access time labelled as access, not steam time', '90 min access' === $s['duration'] );
$ok( 'repeated age/swimwear trimmed, real condition kept', 'weekends only' === $s['conditions'] );
$ok( 'couple price kept as another offer', 1 === count( $s['others'] ) && 'couple_or_group' === $s['others'][0]['kind'] );

// Status gates.
$put( array( array( 'X', 'casual_visit', '10', '', '', '', 1 ) ), array( $k . 'status' => 'conflicting' ) );
$ok( 'conflicting row is not shown', null === FA\summary( $id, 'steam-room' ) );

// FAQ answers only what the data supports.
$put( array( array( 'Entry', 'casual_visit', '18.5', 'per adult', '', '', 1 ) ) );
$sums = array( $id => FA\summary( $id, 'steam-room' ) );
$qs   = array_column( FA\faqs( $sums, 'Perth' ), 'q' );
$ok( 'under-$25 question asked when a price supports it', in_array( 'Is there a steam room for under $25?', $qs, true ) );
$ok( 'no quiet-session question without a published one', ! in_array( 'Are there quiet or silent steam sessions?', $qs, true ) );

wp_delete_post( $id, true );

// Coverage: the steam-room page takes a leisure centre (fitness) too.
$spa = get_term_by( 'slug', 'spa', 'practice' );
$f   = $spa ? \Oria\Core\PracticesIndex\resolve_facet( $spa, 'steam-room' ) : null;
$ids = $f ? \Oria\Core\PracticesIndex\facet_ids( $spa, $f ) : array();
$bp  = get_page_by_path( 'beatty-park-aqua', OBJECT, 'listing' );
$ok( 'steam-room facet includes a non-spa venue with the service', $bp && in_array( (int) $bp->ID, array_map( 'intval', $ids ), true ) );
$ok( 'drafts never counted', ! array_filter( $ids, static fn( $i ): bool => 'publish' !== get_post_status( (int) $i ) ) );

echo $fail ? "FAILURES: $fail\n" : "all facility access checks pass\n";
