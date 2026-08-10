<?php
/**
 * Plugin Name:       Oria Core
 * Plugin URI:        https://oriahaven.com.au
 * Description:       The directory data model — listings, events, practices and areas, the claim workflow, and the JSON seed importer. Deliberately separate from the theme so a redesign never risks the data.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Dale
 * Text Domain:       oria
 */

declare(strict_types=1);

namespace Oria\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION = '0.1.0';

define( 'ORIA_CORE_FILE', __FILE__ );
define( 'ORIA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORIA_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once ORIA_CORE_DIR . 'includes/taxonomies.php';
require_once ORIA_CORE_DIR . 'includes/post-types.php';
require_once ORIA_CORE_DIR . 'includes/fields.php';
require_once ORIA_CORE_DIR . 'includes/fields-pages.php';
require_once ORIA_CORE_DIR . 'includes/claims.php';
require_once ORIA_CORE_DIR . 'includes/reviews.php';
require_once ORIA_CORE_DIR . 'includes/places.php';
require_once ORIA_CORE_DIR . 'includes/ownership.php';
require_once ORIA_CORE_DIR . 'includes/analytics.php';
require_once ORIA_CORE_DIR . 'includes/claim-requests.php';
require_once ORIA_CORE_DIR . 'includes/admin-ui.php';
require_once ORIA_CORE_DIR . 'includes/specialties.php';
require_once ORIA_CORE_DIR . 'includes/seo.php';
require_once ORIA_CORE_DIR . 'includes/schema.php';
require_once ORIA_CORE_DIR . 'includes/tiers.php';
require_once ORIA_CORE_DIR . 'includes/listing-search.php';
require_once ORIA_CORE_DIR . 'includes/signup.php';
require_once ORIA_CORE_DIR . 'includes/ga.php';
require_once ORIA_CORE_DIR . 'includes/mail.php';
require_once ORIA_CORE_DIR . 'includes/search.php';
require_once ORIA_CORE_DIR . 'includes/admin-import.php';
require_once ORIA_CORE_DIR . 'includes/billing.php';
require_once ORIA_CORE_DIR . 'includes/import.php';
require_once ORIA_CORE_DIR . 'includes/hub.php';
require_once ORIA_CORE_DIR . 'includes/faq.php';

/*
 * Taxonomies register before post types so the post types can attach to them
 * in the same hook pass.
 */
add_action( 'init', __NAMESPACE__ . '\Taxonomies\register', 5 );
add_action( 'init', __NAMESPACE__ . '\PostTypes\register', 6 );

Fields\bootstrap();
FieldsPages\bootstrap();
Claims\bootstrap();
Reviews\bootstrap();
Ownership\bootstrap();
Analytics\bootstrap();
ClaimRequests\bootstrap();
AdminUI\bootstrap();
Seo\bootstrap();
Schema\bootstrap();
ListingSearch\bootstrap();
Signup\bootstrap();
Ga\bootstrap();
Mail\bootstrap();
Search\bootstrap();
AdminImport\bootstrap();
Billing\bootstrap();
Hub\bootstrap();
Faq\bootstrap();

/**
 * Rewrite rules are only rebuilt on activation and deactivation. Flushing on
 * every load is a well-known way to make a site crawl.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		Taxonomies\register();
		PostTypes\register();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'oria', __NAMESPACE__ . '\Import\Command' );
}
