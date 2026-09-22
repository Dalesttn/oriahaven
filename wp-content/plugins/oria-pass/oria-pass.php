<?php
/**
 * Plugin Name:  Oria Pass
 * Description:  The Oria Pass membership — landing page, waitlist and the settings the later phases read. Booking and credits are deliberately not here yet.
 * Version:      0.1.0
 * Author:       Oria Haven
 * Requires PHP: 8.0
 * Text Domain:  oria
 *
 * Its own plugin rather than another corner of oria-core, because this is
 * the one part of the site that will eventually hold money: memberships,
 * credit ledgers and provider payouts. Keeping it separable means it can be
 * switched off whole, on any environment, without taking the directory with
 * it -- which is exactly what you want while it is being piloted.
 *
 * Phase 1 is a landing page, a waitlist and the settings the later phases
 * will read. There is no booking engine, no credit ledger and no billing in
 * here yet, and the mode switch below is what keeps the page honest about
 * that: in Waitlist mode it collects names and never implies you can buy
 * anything.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION  = '0.1.0';
const DB_VER   = '3';
const ROUTES_V = '1';

define( 'ORIA_PASS_FILE', __FILE__ );
define( 'ORIA_PASS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORIA_PASS_URL', plugin_dir_url( __FILE__ ) );

require_once ORIA_PASS_DIR . 'includes/settings.php';
require_once ORIA_PASS_DIR . 'includes/db.php';
require_once ORIA_PASS_DIR . 'includes/credits.php';
require_once ORIA_PASS_DIR . 'includes/membership.php';
require_once ORIA_PASS_DIR . 'includes/stripe.php';
require_once ORIA_PASS_DIR . 'includes/sessions.php';
require_once ORIA_PASS_DIR . 'includes/booking.php';
require_once ORIA_PASS_DIR . 'includes/actions.php';
require_once ORIA_PASS_DIR . 'includes/waitlist.php';
require_once ORIA_PASS_DIR . 'includes/route.php';
require_once ORIA_PASS_DIR . 'includes/admin.php';

Settings\bootstrap();
Stripe\bootstrap();
Actions\bootstrap();
Waitlist\bootstrap();
Route\bootstrap();
Admin\bootstrap();

/**
 * The table is created on activation and, as a safety net, whenever the
 * stored schema version falls behind. A plugin that is git-pulled onto a
 * server is never "activated" there in the WordPress sense, so activation
 * alone would leave the table missing on exactly the environment that
 * matters.
 */
register_activation_hook( __FILE__, __NAMESPACE__ . '\install' );

function install(): void {
	Waitlist\create_table();
	Db\install();
	update_option( 'oria_pass_db_ver', DB_VER );
	// Routes are rewrite rules, which live in the database.
	delete_option( 'oria_pass_routes_v' );
	flush_rewrite_rules();
}

add_action(
	'init',
	static function (): void {
		if ( get_option( 'oria_pass_db_ver' ) !== DB_VER ) {
			Waitlist\create_table();
			Db\install();
			update_option( 'oria_pass_db_ver', DB_VER );
		}
	},
	5
);
