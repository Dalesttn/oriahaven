<?php
/**
 * Post types: listing, event and Best Of guide.
 */

declare(strict_types=1);

namespace Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LISTING = 'listing';
const EVENT   = 'event';
const BEST_OF = 'best_of';
const TREND   = 'oria_trend';

function register(): void {
	register_listing();
	register_event();
	register_best_of();
	register_trend();
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
				'archives'              => __( 'Explore', 'oria' ),
				'item_published'        => __( 'Listing published.', 'oria' ),
				'item_updated'          => __( 'Listing updated.', 'oria' ),
			),
			'public'          => true,
			'menu_position'   => 20,
			'menu_icon'       => 'dashicons-location-alt',
			// No 'editor'. The WYSIWYG was empty on every listing but three, and
			// nothing asks a practitioner to write one: the blurb (excerpt) is
			// what the cards, the meta description and the profile all read.
			'supports'        => array( 'title', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'author', 'comments' ),
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

/**
 * A Best Of guide: an editor's curated shortlist of listings for one need --
 * "Best yoga for beginners in Perth", "Best saunas in Perth".
 *
 * Its own type rather than a journal post because it is a list, not an
 * article: the picks live in structured fields that also drive the badges
 * on the chosen listings, and the hub at /best/ is built from them. Standard
 * post capabilities, which keeps it to editors -- the Member role carries
 * only `read`, so a practitioner cannot award themselves a badge.
 */
function register_best_of(): void {
	register_post_type(
		BEST_OF,
		array(
			'labels'        => array(
				'name'               => __( 'Best Of guides', 'oria' ),
				'singular_name'      => __( 'Best Of guide', 'oria' ),
				'menu_name'          => __( 'Best Of', 'oria' ),
				'add_new'            => __( 'Add guide', 'oria' ),
				'add_new_item'       => __( 'Add Best Of guide', 'oria' ),
				'edit_item'          => __( 'Edit Best Of guide', 'oria' ),
				'view_item'          => __( 'View guide', 'oria' ),
				'search_items'       => __( 'Search guides', 'oria' ),
				'not_found'          => __( 'No guides yet', 'oria' ),
				'not_found_in_trash' => __( 'No guides in the bin', 'oria' ),
				'featured_image'     => __( 'Cover image', 'oria' ),
				'archives'           => __( 'Best Of', 'oria' ),
			),
			'public'        => true,
			'menu_position' => 22,
			'menu_icon'     => 'dashicons-awards',
			// No editor: the intro, picks and methodology are ACF fields, and
			// the excerpt is the card text. Free prose would only compete.
			'supports'      => array( 'title', 'excerpt', 'thumbnail', 'revisions' ),
			// The hub is the archive; single guides sit directly beneath it.
			'has_archive'   => 'best',
			'rewrite'       => array(
				'slug'       => 'best',
				'with_front' => false,
			),
			'show_in_rest'  => true,
			'rest_base'     => 'best-of',
		)
	);
}

/**
 * A Trend to Try: a wellness trend people meet on Instagram, explained --
 * what it is, what to expect, what the evidence does and does not say, and
 * where to try it around Perth. See includes/trends.php.
 *
 * Its own type rather than a journal post for the same reason as Best Of:
 * the page is built from structured fields (the Reel and its permission,
 * evidence position, safety, prices with the date they were checked) that
 * a publishing checklist reads, and the hub at /trends/ is built from them.
 * Standard post capabilities: editors only.
 */
function register_trend(): void {
	register_post_type(
		TREND,
		array(
			'labels'        => array(
				'name'               => __( 'Trends', 'oria' ),
				'singular_name'      => __( 'Trend', 'oria' ),
				'menu_name'          => __( 'Trends to Try', 'oria' ),
				'add_new'            => __( 'Add trend', 'oria' ),
				'add_new_item'       => __( 'Add trend', 'oria' ),
				'edit_item'          => __( 'Edit trend', 'oria' ),
				'view_item'          => __( 'View trend', 'oria' ),
				'search_items'       => __( 'Search trends', 'oria' ),
				'not_found'          => __( 'No trends yet', 'oria' ),
				'not_found_in_trash' => __( 'No trends in the bin', 'oria' ),
				'featured_image'     => __( 'Cover image (Oria-owned only)', 'oria' ),
				'archives'           => __( 'Trends to Try', 'oria' ),
			),
			'public'        => true,
			'menu_position' => 23,
			'menu_icon'     => 'dashicons-video-alt3',
			// No editor: every section is a field the checklist can read.
			'supports'      => array( 'title', 'excerpt', 'thumbnail', 'revisions', 'author' ),
			'has_archive'   => 'trends',
			'rewrite'       => array(
				'slug'       => 'trends',
				'with_front' => false,
			),
			'show_in_rest'  => true,
			'rest_base'     => 'trends',
		)
	);
}
