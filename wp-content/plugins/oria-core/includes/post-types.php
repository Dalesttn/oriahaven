<?php
/**
 * Post types: listing and event.
 */

declare(strict_types=1);

namespace Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LISTING = 'listing';
const EVENT   = 'event';

function register(): void {
	register_listing();
	register_event();
}

/**
 * A practice in the directory.
 *
 * The rewrite is a plain /listing/{slug}/ for now. The keyword-rich
 * /{practice}/{suburb}/{slug}/ structure is a separate pass, because rewrite
 * rules are the one thing you cannot write safely without a running site to
 * flush and test against.
 */
function register_listing(): void {
	register_post_type(
		LISTING,
		array(
			'labels'          => array(
				'name'                  => __( 'Listings', 'oria' ),
				'singular_name'         => __( 'Listing', 'oria' ),
				'menu_name'             => __( 'Listings', 'oria' ),
				'add_new'               => __( 'Add listing', 'oria' ),
				'add_new_item'          => __( 'Add listing', 'oria' ),
				'edit_item'             => __( 'Edit listing', 'oria' ),
				'new_item'              => __( 'New listing', 'oria' ),
				'view_item'             => __( 'View listing', 'oria' ),
				'search_items'          => __( 'Search listings', 'oria' ),
				'not_found'             => __( 'No listings yet', 'oria' ),
				'not_found_in_trash'    => __( 'No listings in the bin', 'oria' ),
				'featured_image'        => __( 'Main photo', 'oria' ),
				'set_featured_image'    => __( 'Set main photo', 'oria' ),
				'archives'              => __( 'Directory', 'oria' ),
				'item_published'        => __( 'Listing published.', 'oria' ),
				'item_updated'          => __( 'Listing updated.', 'oria' ),
			),
			'public'          => true,
			'menu_position'   => 20,
			'menu_icon'       => 'dashicons-location-alt',
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'author', 'comments' ),
			// Listings carry their own capability set, so the practitioner
			// role can edit a listing without gaining any access to posts or
			// pages. Ownership\grant_admin_caps() gives administrators the lot.
			'capability_type' => array( 'oria_listing', 'oria_listings' ),
			'map_meta_cap'    => true,
			// Listings are created by the directory (imports and admins),
			// never by practitioners — their edit cap must not imply an
			// "Add listing" button.
			'capabilities'    => array( 'create_posts' => 'manage_options' ),
			'taxonomies'      => array( \Oria\Core\Taxonomies\PRACTICE, \Oria\Core\Taxonomies\AREA ),
			'has_archive'     => 'directory',
			'rewrite'         => array(
				'slug'       => 'listing',
				'with_front' => false,
			),
			'show_in_rest'    => true,
			'rest_base'       => 'listings',
			'delete_with_user' => false,
		)
	);
}

/**
 * A one-off session, workshop or retreat. Separate from listings because it
 * expires — an event is only useful until its date passes, and a listing is
 * useful indefinitely.
 */
function register_event(): void {
	register_post_type(
		EVENT,
		array(
			'labels'        => array(
				'name'               => __( 'Workshops/Events', 'oria' ),
				'singular_name'      => __( 'Workshop/Event', 'oria' ),
				'menu_name'          => __( 'Workshops/Events', 'oria' ),
				'add_new'            => __( 'Add workshop/event', 'oria' ),
				'add_new_item'       => __( 'Add workshop/event', 'oria' ),
				'edit_item'          => __( 'Edit workshop/event', 'oria' ),
				'view_item'          => __( 'View workshop/event', 'oria' ),
				'search_items'       => __( 'Search workshops/events', 'oria' ),
				'not_found'          => __( 'No workshops or events yet', 'oria' ),
				'not_found_in_trash' => __( 'No workshops or events in the bin', 'oria' ),
				'featured_image'     => __( 'Photo', 'oria' ),
				'archives'           => __( 'Workshops/Events', 'oria' ),
			),
			'public'        => true,
			'menu_position' => 21,
			'menu_icon'     => 'dashicons-calendar-alt',
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			// Events carry their own capability set so practitioners can run
			// their own events without touching posts, pages or listings they
			// don't own. Scoped in Ownership\scope_to_own_listing().
			'capability_type' => array( 'oria_event', 'oria_events' ),
			'map_meta_cap'    => true,
			'taxonomies'    => array( \Oria\Core\Taxonomies\PRACTICE, \Oria\Core\Taxonomies\AREA ),
			// The archive lives at the What's On URL; single events keep /events/.
			'has_archive'   => 'whats-on-perth',
			'rewrite'       => array(
				'slug'       => 'events',
				'with_front' => false,
			),
			'show_in_rest'  => true,
			'rest_base'     => 'events',
		)
	);
}
