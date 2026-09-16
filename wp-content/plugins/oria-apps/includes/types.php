<?php
/**
 * The wellness_app post type and its category taxonomy.
 *
 * URLs: /apps/{app}/ for an app, /apps/ for the hub, and
 * /apps/category/{category}/ for a category. The category sits under
 * /category/ rather than directly under /apps/ because an app slug and a
 * category slug would otherwise share a namespace -- "yoga" is a plausible
 * app name as well as a category, and the first one registered would win.
 * One collision is enough to lose a page silently.
 */

declare(strict_types=1);

namespace Oria\Apps\Types;

use Oria\Apps\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ARCHIVE   = 'apps';
const REWRITE_V = '2';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register', 5 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );
}

/**
 * Taxonomy first, and that order is load-bearing.
 *
 * A post type with a rewrite slug generates attachment rules for its
 * children -- apps/[^/]+/([^/]+)/?$ resolving to an attachment -- and
 * whichever set is registered first is matched first. With the post type
 * registered first, /apps/category/meditation/ matched that attachment
 * rule, found no attachment, and 404ed. Registering the taxonomy first
 * puts apps/category/([^/]+) ahead of it.
 *
 * The belt to that brace is the explicit top-priority rule below, because
 * a future change to either registration would otherwise reintroduce the
 * same silent collision.
 */
function register(): void {
	register_taxonomy_app_category();
	register_type();
	add_rewrite_rule(
		'^' . ARCHIVE . '/category/([^/]+)/?$',
		'index.php?' . Data\TAX . '=$matches[1]',
		'top'
	);
}

function register_type(): void {
	register_post_type(
		Data\CPT,
		array(
			'labels'        => array(
				'name'               => __( 'Wellness apps', 'oria' ),
				'singular_name'      => __( 'Wellness app', 'oria' ),
				'menu_name'          => __( 'Apps', 'oria' ),
				'add_new'            => __( 'Add app', 'oria' ),
				'add_new_item'       => __( 'Add wellness app', 'oria' ),
				'edit_item'          => __( 'Edit app', 'oria' ),
				'new_item'           => __( 'New app', 'oria' ),
				'view_item'          => __( 'View app', 'oria' ),
				'search_items'       => __( 'Search apps', 'oria' ),
				'not_found'          => __( 'No apps yet', 'oria' ),
				'not_found_in_trash' => __( 'No apps in the bin', 'oria' ),
				'featured_image'     => __( 'Cover image', 'oria' ),
				'set_featured_image' => __( 'Set cover image', 'oria' ),
				'archives'           => __( 'Apps', 'oria' ),
				'item_published'     => __( 'App published.', 'oria' ),
				'item_updated'       => __( 'App updated.', 'oria' ),
			),
			'public'        => true,
			'menu_position' => 23,
			'menu_icon'     => 'dashicons-smartphone',
			/*
			 * No editor, like Best Of guides: every part of an app page is a
			 * field, so a free-prose box would only compete with the fields
			 * and drift out of step with them.
			 */
			'supports'      => array( 'title', 'excerpt', 'thumbnail', 'revisions' ),
			'has_archive'   => ARCHIVE,
			'rewrite'       => array(
				'slug'       => ARCHIVE,
				'with_front' => false,
			),
			'show_in_rest'  => true,
			'rest_base'     => 'wellness-apps',
		)
	);
}

function register_taxonomy_app_category(): void {
	register_taxonomy(
		Data\TAX,
		array( Data\CPT ),
		array(
			'labels'            => array(
				'name'          => __( 'App categories', 'oria' ),
				'singular_name' => __( 'App category', 'oria' ),
				'menu_name'     => __( 'Categories', 'oria' ),
				'all_items'     => __( 'All categories', 'oria' ),
				'edit_item'     => __( 'Edit category', 'oria' ),
				'add_new_item'  => __( 'Add category', 'oria' ),
				'search_items'  => __( 'Search categories', 'oria' ),
				'not_found'     => __( 'No categories found', 'oria' ),
			),
			'public'            => true,
			// Hierarchical for the checkbox admin UI, the same reason the
			// practice taxonomy is: it keeps near-duplicate terms out.
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => ARCHIVE . '/category',
				'with_front' => false,
			),
		)
	);
}

/** Create the starting categories. Only ever adds; never renames or deletes. */
function seed_terms(): void {
	foreach ( Data\CATEGORIES as $slug => $name ) {
		if ( ! term_exists( $slug, Data\TAX ) ) {
			wp_insert_term( $name, Data\TAX, array( 'slug' => $slug ) );
		}
	}
}

function activate(): void {
	register();
	seed_terms();
	flush_rewrite_rules();
	update_option( 'oria_apps_rewrite_v', REWRITE_V );
}

/**
 * A git deploy does not re-run activation, so the rules and the terms would
 * never appear on production. One option check covers it.
 */
function maybe_flush(): void {
	if ( get_option( 'oria_apps_rewrite_v' ) === REWRITE_V ) {
		return;
	}
	seed_terms();
	flush_rewrite_rules();
	update_option( 'oria_apps_rewrite_v', REWRITE_V );
}
