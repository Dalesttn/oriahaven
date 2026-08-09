<?php
/**
 * Plugin Name:       Oria Forms
 * Plugin URI:        https://oriahaven.com.au
 * Description:       The site's own forms: defined in code, rendered in the theme's design language, spam-guarded, saved as entries, and answered with branded email. Replaces WPForms.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Dale
 * Text Domain:       oria
 */

declare(strict_types=1);

namespace Oria\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ORIA_FORMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORIA_FORMS_URL', plugin_dir_url( __FILE__ ) );

require_once ORIA_FORMS_DIR . 'includes/registry.php';
require_once ORIA_FORMS_DIR . 'includes/render.php';
require_once ORIA_FORMS_DIR . 'includes/handler.php';
require_once ORIA_FORMS_DIR . 'includes/entries.php';
require_once ORIA_FORMS_DIR . 'includes/emails.php';

Render\bootstrap();
Handler\bootstrap();
Entries\bootstrap();
