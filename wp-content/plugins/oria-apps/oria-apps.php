<?php
/**
 * Plugin Name: Oria Apps
 * Description: Wellness apps as a first-class part of the directory — editorially reviewed, mapped to the practices they belong beside, and rendered with the site's own cards. Affiliate-ready without affiliate participation deciding what gets recommended.
 * Version: 0.1.0
 * Author: Oria Haven
 *
 * Why a plugin rather than more of oria-core: apps are a catalogue with an
 * outbound link, which is the shape oria-shop already has, not the shape a
 * listing has. The two sit side by side and share the theme's components.
 *
 * The one rule that governs the whole thing: an app is here because it is
 * useful, and the affiliate fields are a consequence of that decision, never
 * the cause. Engine\rank() sorts by editorial position and nothing else.
 */

declare(strict_types=1);

namespace Oria\Apps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ORIA_APPS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORIA_APPS_URL', plugin_dir_url( __FILE__ ) );

require ORIA_APPS_DIR . 'includes/data.php';
require ORIA_APPS_DIR . 'includes/types.php';
require ORIA_APPS_DIR . 'includes/fields.php';
require ORIA_APPS_DIR . 'includes/engine.php';
require ORIA_APPS_DIR . 'includes/guides.php';
require ORIA_APPS_DIR . 'includes/render.php';
require ORIA_APPS_DIR . 'includes/search.php';
require ORIA_APPS_DIR . 'includes/track.php';
require ORIA_APPS_DIR . 'includes/pages.php';
require ORIA_APPS_DIR . 'includes/admin.php';

Types\bootstrap();
Guides\bootstrap();
Fields\bootstrap();
Render\bootstrap();
Track\bootstrap();
Search\bootstrap();
Pages\bootstrap();
Admin\bootstrap();

/*
 * Terms and rewrite rules are created once, on activation, and the version
 * check in Types\maybe_flush() covers a git deploy where activation never
 * runs again.
 */
register_activation_hook( __FILE__, __NAMESPACE__ . '\Types\activate' );
register_activation_hook( __FILE__, __NAMESPACE__ . '\Guides\activate' );
