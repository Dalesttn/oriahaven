<?php
/**
 * Seed hand-curated products from data/products.json.
 *
 * Usage (from the WordPress root, on the server):
 *   php wp-content/plugins/oria-shop/tools/seed-products.php            # dry run: says what it would do
 *   php wp-content/plugins/oria-shop/tools/seed-products.php --apply    # creates them as drafts
 *   php wp-content/plugins/oria-shop/tools/seed-products.php --apply --publish
 *
 * Dry run by default; nothing is written without --apply. A product whose
 * ASIN is already in the catalogue is skipped, never overwritten, so the
 * script can be run again after the file grows. Categories are matched by
 * slug and must already exist (the plugin seeds them on activation).
 *
 * Copy in the file is ours: blurb and editorial note. Prices are left
 * empty on purpose -- the card says "Check current price on Amazon" until
 * the API fills one in -- and no image is set, since product images may
 * only come from the Amazon API.
 */

declare(strict_types=1);

$apply   = in_array( '--apply', $argv, true );
$publish = in_array( '--publish', $argv, true );

$root = dirname( __DIR__, 4 );
if ( ! file_exists( $root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Run from the WordPress root.\n" );
	exit( 1 );
}
require $root . '/wp-load.php';

$file = dirname( __DIR__ ) . '/data/products.json';
$rows = json_decode( (string) file_get_contents( $file ), true );
if ( ! is_array( $rows ) ) {
	fwrite( STDERR, "Could not read $file\n" );
	exit( 1 );
}

$cpt  = \Oria\Shop\Data\CPT;
$tax  = \Oria\Shop\Data\TAX;
$keys = array(
	'asin'           => 'field_oria_prod_asin',
	'brand'          => 'field_oria_prod_brand',
	'blurb'          => 'field_oria_prod_blurb',
	'editorial_note' => 'field_oria_prod_note',
	'best_for'       => 'field_oria_prod_bestfor',
	'intents'        => 'field_oria_prod_intents',
	'collections'    => 'field_oria_prod_collections',
	'featured'       => 'field_oria_prod_featured',
);

echo $apply ? "APPLY\n" : "DRY RUN (add --apply to write)\n";
$made = 0;
$skip = 0;

foreach ( $rows as $r ) {
	$asin = strtoupper( trim( (string) ( $r['asin'] ?? '' ) ) );
	if ( ! preg_match( '/^[A-Z0-9]{10}$/', $asin ) ) {
		echo "  !! bad ASIN in row: " . wp_json_encode( $r ) . "\n";
		continue;
	}
	$exists = get_posts( array( 'post_type' => $cpt, 'post_status' => 'any', 'meta_key' => 'asin', 'meta_value' => $asin, 'fields' => 'ids', 'posts_per_page' => 1 ) );
	if ( $exists ) {
		echo "  = $asin already in the catalogue (post {$exists[0]}), skipped\n";
		++$skip;
		continue;
	}
	$terms = array();
	foreach ( (array) ( $r['categories'] ?? array() ) as $slug ) {
		$t = get_term_by( 'slug', (string) $slug, $tax );
		if ( $t instanceof WP_Term ) {
			$terms[] = (int) $t->term_id;
		} else {
			echo "  !! $asin: no category '$slug' -- create it first or fix the slug\n";
		}
	}
	echo "  + $asin  {$r['title']}  [" . implode( ',', (array) ( $r['categories'] ?? array() ) ) . "]\n";
	if ( ! $apply ) {
		continue;
	}
	$id = wp_insert_post(
		array(
			'post_type'   => $cpt,
			'post_status' => $publish ? 'publish' : 'draft',
			'post_title'  => (string) $r['title'],
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		echo "  !! $asin failed: " . $id->get_error_message() . "\n";
		continue;
	}
	$meta = array(
		'asin'           => $asin,
		'brand'          => (string) ( $r['brand'] ?? '' ),
		'blurb'          => (string) ( $r['blurb'] ?? '' ),
		'editorial_note' => (string) ( $r['note'] ?? '' ),
		'best_for'       => (string) ( $r['best_for'] ?? '' ),
		'intents'        => array_values( (array) ( $r['intents'] ?? array() ) ),
		'collections'    => array_values( (array) ( $r['collections'] ?? array() ) ),
		'featured'       => ! empty( $r['featured'] ) ? '1' : '0',
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
		update_post_meta( $id, '_' . $k, $keys[ $k ] );
	}
	if ( $terms ) {
		wp_set_object_terms( $id, $terms, $tax );
	}
	++$made;
}

echo "\n" . ( $apply ? "Created $made, skipped $skip.\n" : "Would create " . ( count( $rows ) - $skip ) . ", skip $skip.\n" );
