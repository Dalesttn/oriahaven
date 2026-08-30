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

require_once ORIA_CORE_DIR . 'includes/db.php';
require_once ORIA_CORE_DIR . 'includes/taxonomies.php';
require_once ORIA_CORE_DIR . 'includes/post-types.php';
require_once ORIA_CORE_DIR . 'includes/fields.php';
require_once ORIA_CORE_DIR . 'includes/fields-pages.php';
require_once ORIA_CORE_DIR . 'includes/claims.php';
require_once ORIA_CORE_DIR . 'includes/reviews.php';
require_once ORIA_CORE_DIR . 'includes/places.php';
require_once ORIA_CORE_DIR . 'includes/ownership.php';
require_once ORIA_CORE_DIR . 'includes/analytics.php';
require_once ORIA_CORE_DIR . 'includes/analytics-report.php';
require_once ORIA_CORE_DIR . 'includes/claim-requests.php';
require_once ORIA_CORE_DIR . 'includes/admin-ui.php';
require_once ORIA_CORE_DIR . 'includes/specialties.php';
require_once ORIA_CORE_DIR . 'includes/amenities.php';
require_once ORIA_CORE_DIR . 'includes/geo.php';
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
require_once ORIA_CORE_DIR . 'includes/compare.php';
require_once ORIA_CORE_DIR . 'includes/faq.php';
require_once ORIA_CORE_DIR . 'includes/leads.php';
require_once ORIA_CORE_DIR . 'includes/share.php';
require_once ORIA_CORE_DIR . 'includes/og-default.php';
require_once ORIA_CORE_DIR . 'includes/badge.php';
require_once ORIA_CORE_DIR . 'includes/hardening.php';
require_once ORIA_CORE_DIR . 'includes/intent-pages.php';
require_once ORIA_CORE_DIR . 'includes/practices-index.php';
require_once ORIA_CORE_DIR . 'includes/finder.php';
require_once ORIA_CORE_DIR . 'includes/goodfor.php';
require_once ORIA_CORE_DIR . 'includes/comefor.php';
require_once ORIA_CORE_DIR . 'includes/invites.php';
require_once ORIA_CORE_DIR . 'includes/websites.php';
require_once ORIA_CORE_DIR . 'includes/llms.php';
require_once ORIA_CORE_DIR . 'includes/email-preview.php';
require_once ORIA_CORE_DIR . 'includes/area-depth.php';
require_once ORIA_CORE_DIR . 'includes/area-coverage.php';
require_once ORIA_CORE_DIR . 'includes/services.php';
require_once ORIA_CORE_DIR . 'includes/categories.php';
require_once ORIA_CORE_DIR . 'includes/answer.php';
require_once ORIA_CORE_DIR . 'includes/audience.php';
require_once ORIA_CORE_DIR . 'includes/cities.php';
require_once ORIA_CORE_DIR . 'includes/redirects.php';
require_once ORIA_CORE_DIR . 'includes/merge-terms.php';
require_once ORIA_CORE_DIR . 'includes/migrate-city.php';
require_once ORIA_CORE_DIR . 'includes/intent-stats.php';
require_once ORIA_CORE_DIR . 'includes/intents.php';
require_once ORIA_CORE_DIR . 'includes/guides.php';
require_once ORIA_CORE_DIR . 'includes/members.php';
require_once ORIA_CORE_DIR . 'includes/members-admin.php';
require_once ORIA_CORE_DIR . 'includes/review-submit.php';
require_once ORIA_CORE_DIR . 'includes/google-auth.php';
require_once ORIA_CORE_DIR . 'includes/review-replies.php';
require_once ORIA_CORE_DIR . 'includes/review-reports.php';
require_once ORIA_CORE_DIR . 'includes/team.php';
require_once ORIA_CORE_DIR . 'includes/similar.php';
require_once ORIA_CORE_DIR . 'includes/reasons.php';
require_once ORIA_CORE_DIR . 'includes/saved.php';
require_once ORIA_CORE_DIR . 'includes/classes.php';
require_once ORIA_CORE_DIR . 'includes/guide-blocks.php';
require_once ORIA_CORE_DIR . 'includes/journeys.php';

/*
 * Taxonomies register before post types so the post types can attach to them
 * in the same hook pass.
 */
add_action( 'init', __NAMESPACE__ . '\Taxonomies\register', 5 );
add_action( 'init', __NAMESPACE__ . '\PostTypes\register', 6 );

Db\bootstrap();
Fields\bootstrap();
FieldsPages\bootstrap();
Claims\bootstrap();
Reviews\bootstrap();
Ownership\bootstrap();
Analytics\bootstrap();
AnalyticsReport\bootstrap();
ClaimRequests\bootstrap();
AdminUI\bootstrap();
Seo\bootstrap();
Schema\bootstrap();
Places\bootstrap();
ListingSearch\bootstrap();
Signup\bootstrap();
Ga\bootstrap();
Mail\bootstrap();
Search\bootstrap();
AdminImport\bootstrap();
Billing\bootstrap();
Hub\bootstrap();
Compare\bootstrap();
Faq\bootstrap();
Leads\bootstrap();
Share\bootstrap();
OgDefault\bootstrap();
Hardening\bootstrap();
IntentPages\bootstrap();
PracticesIndex\bootstrap();
Finder\bootstrap();
Invites\bootstrap();
Websites\bootstrap();
Llms\bootstrap();
EmailPreview\bootstrap();
AreaDepth\bootstrap();
AreaCoverage\bootstrap();
Services\bootstrap();
Categories\bootstrap();
Answer\bootstrap();
Audience\bootstrap();
Cities\bootstrap();
Redirects\bootstrap();
IntentStats\bootstrap();
Members\bootstrap();
MembersAdmin\bootstrap();
ReviewSubmit\bootstrap();
GoogleAuth\bootstrap();
Replies\bootstrap();
Reports\bootstrap();
Team\bootstrap();
ComeFor\bootstrap();
Journeys\bootstrap();

/**
 * Rewrite rules are only rebuilt on activation and deactivation. Flushing on
 * every load is a well-known way to make a site crawl.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		Taxonomies\register();
		PostTypes\register();
		Db\install();
		Members\ensure_role();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		wp_clear_scheduled_hook( 'oria_purge_member_tokens' );
		flush_rewrite_rules();
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'oria', __NAMESPACE__ . '\Import\Command' );
}
