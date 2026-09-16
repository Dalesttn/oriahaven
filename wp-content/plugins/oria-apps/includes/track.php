<?php
/**
 * Counting the clicks that leave for an app.
 *
 * Two layers, because they answer different questions. The data layer
 * event feeds the site's existing analytics, which is where "did app
 * visitors go deeper into Oria" gets answered. The counter in post meta is
 * first-party and survives an ad blocker, which is what makes the admin
 * column trustworthy.
 *
 * No cookies, no third party, and nothing about the visitor is recorded --
 * only that an app was opened, and on what day.
 */

declare(strict_types=1);

namespace Oria\Apps\Track;

use Oria\Apps\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META = '_oria_app_clicks';

function bootstrap(): void {
	add_action( 'rest_api_init', __NAMESPACE__ . '\routes' );
	add_action( 'wp_footer', __NAMESPACE__ . '\script', 20 );
}

function routes(): void {
	register_rest_route(
		'oria/v1',
		'/app-click',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => __NAMESPACE__ . '\record',
			'args'                => array(
				'app'  => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'kind' => array( 'required' => false, 'sanitize_callback' => 'sanitize_key' ),
			),
		)
	);
}

function record( \WP_REST_Request $request ) {
	$id   = (int) $request->get_param( 'app' );
	$kind = (string) ( $request->get_param( 'kind' ) ?: 'visit' );

	if ( Data\CPT !== get_post_type( $id ) ) {
		return new \WP_REST_Response( array( 'ok' => false ), 400 );
	}

	$day    = current_time( 'Y-m-d' );
	$counts = (array) get_post_meta( $id, META, true );
	$counts[ $day ][ $kind ] = (int) ( $counts[ $day ][ $kind ] ?? 0 ) + 1;

	// Ninety days is enough to see a trend and small enough to stay cheap.
	$cutoff = gmdate( 'Y-m-d', time() - 90 * DAY_IN_SECONDS );
	foreach ( array_keys( $counts ) as $date ) {
		if ( (string) $date < $cutoff ) {
			unset( $counts[ $date ] );
		}
	}
	update_post_meta( $id, META, $counts );

	return new \WP_REST_Response( array( 'ok' => true ), 200 );
}

/** Total clicks in the window, for the admin column. */
function total( int $id ): int {
	$sum = 0;
	foreach ( (array) get_post_meta( $id, META, true ) as $day ) {
		foreach ( (array) $day as $n ) {
			$sum += (int) $n;
		}
	}
	return $sum;
}

/**
 * One listener for every outbound app link on the page.
 *
 * sendBeacon rather than fetch: the browser is already navigating away,
 * and a beacon is the one request that reliably survives that.
 */
function script(): void {
	if ( is_admin() ) {
		return;
	}
	$url = esc_url_raw( rest_url( 'oria/v1/app-click' ) );
	?>
	<script>
	document.addEventListener("click", function (e) {
		var a = e.target.closest("[data-oapp-click]");
		if (!a) return;
		var id = a.getAttribute("data-oapp-click");
		var kind = a.getAttribute("data-oapp-kind") || "visit";
		try {
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({
				event: "app_" + kind + "_click",
				app_name: a.getAttribute("data-oapp-name") || "",
				app_category: a.getAttribute("data-oapp-cat") || "",
				link_type: kind,
				affiliate: a.getAttribute("data-oapp-aff") === "1",
				page_location: window.location.pathname
			});
		} catch (err) { /* never block the click */ }
		if (navigator.sendBeacon) {
			navigator.sendBeacon(
				<?php echo wp_json_encode( $url ); ?>,
				new Blob([JSON.stringify({ app: id, kind: kind })], { type: "application/json" })
			);
		}
	});
	</script>
	<?php
}
