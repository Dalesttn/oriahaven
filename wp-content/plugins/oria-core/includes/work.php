<?php
/**
 * Work in Wellness -- jobs, casual shifts, practitioner work profiles and
 * Oria Cover. The loader: every piece lives under includes/work/.
 *
 *   model.php    post types, vocabularies, field access
 *   store.php    applications / alerts / reports tables
 *   logic.php    open/closed, labels, completeness, matching, search
 *   routes.php   public URLs, robots, titles, sitemap, file + outbound hops
 *   files.php    private CVs, public profile photos
 *   forms.php    every form that writes
 *   notify.php   email and the hourly clock
 *   schema.php   JobPosting / Person
 *   admin.php    menu, moderation columns, controls, report queue
 *
 * Nothing here is Perth-specific: cities come from Cities, places from the
 * area tree.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/work/model.php';
require_once __DIR__ . '/work/store.php';
require_once __DIR__ . '/work/logic.php';
require_once __DIR__ . '/work/routes.php';
require_once __DIR__ . '/work/files.php';
require_once __DIR__ . '/work/forms.php';
require_once __DIR__ . '/work/notify.php';
require_once __DIR__ . '/work/schema.php';
require_once __DIR__ . '/work/admin.php';
require_once __DIR__ . '/work/plans.php';
require_once __DIR__ . '/work/talent.php';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register', 7 );
	add_action( 'init', __NAMESPACE__ . '\seed', 20 );
	add_action( 'plugins_loaded', __NAMESPACE__ . '\Store\maybe_install', 2 );
	add_filter( 'wp_unique_post_slug', __NAMESPACE__ . '\reserve_slugs', 10, 4 );

	add_action( 'init', __NAMESPACE__ . '\route', 10 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_action( 'template_redirect', __NAMESPACE__ . '\side_doors', 1 );
	add_action( 'template_redirect', __NAMESPACE__ . '\guard_singles', 2 );
	add_filter( 'template_include', __NAMESPACE__ . '\template', 20 );

	add_filter( 'wp_robots', __NAMESPACE__ . '\wp_robots', 30 );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\yoast_robots', 30 );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 30 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 30 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description', 30 );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical', 30 );
	add_action( 'init', __NAMESPACE__ . '\register_sitemap', 20 );
	add_filter( 'wpseo_sitemap_index', __NAMESPACE__ . '\sitemap_index' );
	add_filter( 'wpseo_sitemap_exclude_post_type', __NAMESPACE__ . '\exclude_types', 10, 2 );

	if ( is_admin() ) {
		add_action( 'pre_get_posts', __NAMESPACE__ . '\Files\hide_private' );
	}
	add_filter( 'ajax_query_attachments_args', __NAMESPACE__ . '\Files\hide_private_ajax' );

	Forms\bootstrap();
	Notify\bootstrap();
	Schema\bootstrap();
	Admin\bootstrap();
	Plans\bootstrap();
}

/**
 * A single profile that is hidden, or visible to employers only, is a 404 to
 * everybody it is not for -- the same answer as a profile that does not
 * exist, so its existence is not disclosed either (brief section 28).
 */
function guard_singles(): void {
	if ( is_singular( PRO ) && ! profile_visible_to( (int) get_queried_object_id(), get_current_user_id() ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
	// Signed-in views of work pages carry apply/available state: never cache them for someone else.
	if ( is_user_logged_in() && is_work_page() ) {
		do_action( 'litespeed_control_set_nocache', 'Work pages show per-person application state' );
		// "Employer views" (brief section 66): counted here, where the account is known.
		$id  = (int) get_queried_object_id();
		$uid = get_current_user_id();
		if ( is_singular( PRO ) && ! is_404() && (int) get_post_field( 'post_author', $id ) !== $uid && is_employer( $uid ) ) {
			count_event( $id, 'empview' );
		}
	}
}
