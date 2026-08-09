<?php
/**
 * Plugin Name: Oria Shop
 * Description: Wellness product recommendations with Amazon affiliate links. Products live in a local catalogue (curated by hand today, refreshed by the Amazon Product Advertising API once unlocked), mapped to practices and journal topics, and rendered as branded cards with a configurable disclosure.
 * Version: 0.1.0
 * Author: Oria Haven
 *
 * Provider architecture: the recommendation engine only ever reads the
 * local catalogue. Providers write INTO the catalogue — today that's the
 * site owner (manual curation), later the PA-API refresher, and any other
 * affiliate network could be added the same way.
 */

declare(strict_types=1);

namespace Oria\Shop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ORIA_SHOP_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORIA_SHOP_URL', plugin_dir_url( __FILE__ ) );

require ORIA_SHOP_DIR . 'includes/data.php';
require ORIA_SHOP_DIR . 'includes/fields.php';
require ORIA_SHOP_DIR . 'includes/engine.php';
require ORIA_SHOP_DIR . 'includes/render.php';
require ORIA_SHOP_DIR . 'includes/track.php';
require ORIA_SHOP_DIR . 'includes/admin.php';
require ORIA_SHOP_DIR . 'includes/import.php';
require ORIA_SHOP_DIR . 'includes/providers/amazon.php';

Data\bootstrap();
Fields\bootstrap();
Render\bootstrap();
Track\bootstrap();
Admin\bootstrap();
Import\bootstrap();
Providers\Amazon\bootstrap();

register_deactivation_hook(
	__FILE__,
	static function (): void {
		wp_clear_scheduled_hook( 'oria_shop_refresh' );
	}
);
