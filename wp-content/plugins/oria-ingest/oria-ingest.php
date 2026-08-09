<?php
/**
 * Plugin Name: Oria Ingest
 * Description: Automated wellness-event discovery for Oria Haven. Watches a list of Perth event pages, extracts structured event data, classifies it with AI, dedupes it, and files new events as drafts for review. Member-created events always outrank aggregated ones.
 * Version: 0.1.0
 * Author: Oria Haven
 *
 * The collection/AI layer lives entirely in this plugin, separate from the
 * oria-core presentation plugin, so it can later be replaced by an external
 * automation (n8n etc.) posting to the same WordPress data model.
 */

declare(strict_types=1);

namespace Oria\Ingest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ORIA_INGEST_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORIA_INGEST_VERSION', '0.1.0' );

require ORIA_INGEST_DIR . 'includes/taxonomy.php';
require ORIA_INGEST_DIR . 'includes/fetch.php';
require ORIA_INGEST_DIR . 'includes/heuristic.php';
require ORIA_INGEST_DIR . 'includes/ai.php';
require ORIA_INGEST_DIR . 'includes/pipeline.php';
require ORIA_INGEST_DIR . 'includes/admin.php';

Taxonomy\bootstrap();
Admin\bootstrap();

// Daily run, plus a manual "Run now" in the admin.
add_action( 'oria_ingest_daily', __NAMESPACE__ . '\Pipeline\run' );

add_action(
	'init',
	static function (): void {
		if ( ! wp_next_scheduled( 'oria_ingest_daily' ) ) {
			// 03:10 local time, when sources are quiet and the day's edits are in.
			$first = strtotime( 'tomorrow 03:10' ) - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
			wp_schedule_event( $first, 'daily', 'oria_ingest_daily' );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		wp_clear_scheduled_hook( 'oria_ingest_daily' );
	}
);
