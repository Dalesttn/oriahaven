<?php
/**
 * Lightweight affiliate analytics: per-product daily impression and click
 * counters plus the source page for clicks. Counters live in post meta
 * (`_oshop_i_YYYYMMDD`, `_oshop_c_YYYYMMDD`, `_oshop_sources`), summed for
 * the admin columns. No personal data — no IPs, no cookies, no visitors.
 */

declare(strict_types=1);

namespace Oria\Shop\Track;

use Oria\Shop\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'rest_api_init', __NAMESPACE__ . '\routes' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\assets' );
}

function routes(): void {
	register_rest_route(
		'oria/v1',
		'/shop-click',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'args'                => array(
				'product' => array( 'type' => 'integer', 'required' => true ),
				'source'  => array( 'type' => 'string', 'default' => '' ),
			),
			'callback'            => __NAMESPACE__ . '\record_click',
		)
	);
}

function assets(): void {
	// Tiny click beacon, only meaningful on pages that render cards; it
	// no-ops instantly elsewhere, so it ships with the main bundle level.
	wp_add_inline_script(
		'oria-app',
		'document.addEventListener("click",function(e){var a=e.target.closest("[data-oshop-click]");if(!a)return;' .
		'var b=JSON.stringify({product:+a.dataset.oshopClick,source:location.pathname});' .
		'navigator.sendBeacon&&navigator.sendBeacon("' . esc_url_raw( rest_url( 'oria/v1/shop-click' ) ) . '",new Blob([b],{type:"application/json"}));});'
	);
}

function record_click( \WP_REST_Request $req ): \WP_REST_Response {
	$id = (int) $req['product'];
	if ( Data\CPT === get_post_type( $id ) ) {
		bump( $id, 'c' );
		$source = sanitize_text_field( (string) $req['source'] );
		if ( '' !== $source ) {
			$sources = (array) get_post_meta( $id, '_oshop_sources', true );
			$sources[ $source ] = (int) ( $sources[ $source ] ?? 0 ) + 1;
			arsort( $sources );
			update_post_meta( $id, '_oshop_sources', array_slice( $sources, 0, 20, true ) );
		}
	}
	return new \WP_REST_Response( null, 204 );
}

/** Impressions, counted server-side at render. @param array<int, array<string, string>> $products */
function impressions( array $products ): void {
	foreach ( $products as $p ) {
		bump( (int) $p['id'], 'i' );
	}
}

function bump( int $id, string $kind ): void {
	$key = "_oshop_{$kind}_" . current_time( 'Ymd' );
	update_post_meta( $id, $key, (int) get_post_meta( $id, $key, true ) + 1 );
}

/** Total over the last N days. */
function total( int $id, string $kind, int $days = 30 ): int {
	$sum = 0;
	$now = (int) current_time( 'timestamp' );
	for ( $d = 0; $d < $days; $d++ ) {
		$sum += (int) get_post_meta( $id, "_oshop_{$kind}_" . gmdate( 'Ymd', $now - $d * DAY_IN_SECONDS ), true );
	}
	return $sum;
}
