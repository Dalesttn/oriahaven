<?php
/**
 * Amazon Product Advertising API 5.0 provider — built and dormant.
 *
 * Activates only when both wp-config constants exist (issued by Amazon
 * after 3 qualifying sales):
 *
 *   define( 'ORIA_AMAZON_PAAPI_KEY', '…' );
 *   define( 'ORIA_AMAZON_PAAPI_SECRET', '…' );
 *
 * On its schedule it refreshes each published product's title/price from
 * GetItems (keyed by the curated ASIN), stamping `_oshop_refreshed`. Per
 * Amazon's rules, API data may be cached at most 24 hours — hence the
 * refresh cadence — and if the API is down, existing catalogue data keeps
 * displaying (it is our own curation, not stale API data).
 *
 * PA-API 5.0 requests are signed with AWS Signature v4: for amazon.com.au
 * the host is webservices.amazon.com.au and the signing region us-west-2.
 */

declare(strict_types=1);

namespace Oria\Shop\Providers\Amazon;

use Oria\Shop\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HOST   = 'webservices.amazon.com.au';
const REGION = 'us-west-2';

function bootstrap(): void {
	add_action( 'oria_shop_refresh', __NAMESPACE__ . '\refresh_all' );
	add_action(
		'init',
		static function (): void {
			if ( configured() && ! wp_next_scheduled( 'oria_shop_refresh' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'oria_shop_refresh' );
			}
			if ( ! configured() ) {
				wp_clear_scheduled_hook( 'oria_shop_refresh' );
			}
		}
	);
}

function configured(): bool {
	return defined( 'ORIA_AMAZON_PAAPI_KEY' ) && defined( 'ORIA_AMAZON_PAAPI_SECRET' )
		&& '' !== (string) ORIA_AMAZON_PAAPI_KEY && '' !== (string) ORIA_AMAZON_PAAPI_SECRET;
}

/** Refresh every published product from the API, respecting the cadence. */
function refresh_all(): void {
	if ( ! configured() ) {
		return;
	}
	$max_age = max( 1, (int) get_option( 'oria_shop_refresh_h', 24 ) ) * HOUR_IN_SECONDS;
	$now     = time();

	$products = get_posts(
		array(
			'post_type'      => Data\CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
		)
	);

	// GetItems takes up to 10 ASINs per call.
	$due = array();
	foreach ( $products as $id ) {
		$asin = strtoupper( trim( (string) get_post_meta( $id, 'asin', true ) ) );
		if ( '' !== $asin && $now - (int) get_post_meta( $id, '_oshop_refreshed', true ) > $max_age ) {
			$due[ $asin ] = (int) $id;
		}
	}
	foreach ( array_chunk( $due, 10, true ) as $chunk ) {
		$items = get_items( array_keys( $chunk ) );
		foreach ( $chunk as $asin => $id ) {
			if ( isset( $items[ $asin ] ) ) {
				apply_item( $id, $items[ $asin ] );
			}
			update_post_meta( $id, '_oshop_refreshed', $now );
		}
	}
}

/** @param array<string, mixed> $item one PA-API item payload */
function apply_item( int $id, array $item ): void {
	$title = (string) ( $item['ItemInfo']['Title']['DisplayValue'] ?? '' );
	if ( '' !== $title ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
	}
	$price = (string) ( $item['Offers']['Listings'][0]['Price']['DisplayAmount'] ?? '' );
	if ( '' !== $price ) {
		update_post_meta( $id, 'price', $price );
	}
	$brand = (string) ( $item['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'] ?? '' );
	if ( '' !== $brand ) {
		update_post_meta( $id, 'brand', $brand );
	}
	$image = (string) ( $item['Images']['Primary']['Large']['URL'] ?? '' );
	if ( '' !== $image ) {
		// API-provided image URL — permitted to display while data is fresh.
		update_post_meta( $id, '_oshop_image', $image );
	}
}

/**
 * GetItems call. @param array<string> $asins @return array<string, array<string, mixed>> keyed by ASIN.
 */
function get_items( array $asins ): array {
	$payload = wp_json_encode(
		array(
			'ItemIds'     => array_values( $asins ),
			'PartnerTag'  => Data\tag(),
			'PartnerType' => 'Associates',
			'Marketplace' => Data\marketplace(),
			'Resources'   => array(
				'ItemInfo.Title',
				'ItemInfo.ByLineInfo',
				'Offers.Listings.Price',
				'Images.Primary.Large',
			),
		)
	);

	$res = request( 'GetItems', (string) $payload );
	if ( null === $res ) {
		return array();
	}
	$out = array();
	foreach ( (array) ( $res['ItemsResult']['Items'] ?? array() ) as $item ) {
		if ( isset( $item['ASIN'] ) ) {
			$out[ strtoupper( (string) $item['ASIN'] ) ] = (array) $item;
		}
	}
	return $out;
}

/** One signed PA-API request, null on any failure. @return array<string, mixed>|null */
function request( string $operation, string $payload ): ?array {
	$amz_date  = gmdate( 'Ymd\THis\Z' );
	$datestamp = gmdate( 'Ymd' );
	$target    = 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.' . $operation;
	$path      = '/paapi5/' . strtolower( $operation );

	$headers = array(
		'content-encoding' => 'amz-1.0',
		'content-type'     => 'application/json; charset=utf-8',
		'host'             => HOST,
		'x-amz-date'       => $amz_date,
		'x-amz-target'     => $target,
	);

	// --- AWS Signature v4 ------------------------------------------------
	ksort( $headers );
	$canonical_headers = '';
	$signed_headers    = array();
	foreach ( $headers as $k => $v ) {
		$canonical_headers .= $k . ':' . $v . "\n";
		$signed_headers[]   = $k;
	}
	$signed_list = implode( ';', $signed_headers );

	$canonical_request = implode(
		"\n",
		array( 'POST', $path, '', $canonical_headers, $signed_list, hash( 'sha256', $payload ) )
	);

	$scope          = "{$datestamp}/" . REGION . '/ProductAdvertisingAPI/aws4_request';
	$string_to_sign = implode( "\n", array( 'AWS4-HMAC-SHA256', $amz_date, $scope, hash( 'sha256', $canonical_request ) ) );

	$k_date    = hash_hmac( 'sha256', $datestamp, 'AWS4' . ORIA_AMAZON_PAAPI_SECRET, true );
	$k_region  = hash_hmac( 'sha256', REGION, $k_date, true );
	$k_service = hash_hmac( 'sha256', 'ProductAdvertisingAPI', $k_region, true );
	$k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
	$signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

	$headers['authorization'] = sprintf(
		'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
		ORIA_AMAZON_PAAPI_KEY,
		$scope,
		$signed_list,
		$signature
	);
	unset( $headers['host'] ); // wp_remote_post sets it from the URL.

	$res = wp_remote_post(
		'https://' . HOST . $path,
		array(
			'timeout' => 30,
			'headers' => $headers,
			'body'    => $payload,
		)
	);
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	return is_array( $data ) ? $data : null;
}
