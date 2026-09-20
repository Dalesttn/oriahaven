<?php
/**
 * Claimed-listing ownership: the practitioner role, the user↔listing link,
 * and the walls that keep a practitioner inside their own listing.
 *
 * The business rule everything hangs off: a practitioner may edit a listing
 * only when BOTH are true —
 *   1. the listing's claimed_by field points at their user account, and
 *   2. the listing's claim_status is a paid one (claimed / featured).
 * Lapse the payment (status back to unclaimed) and every paid surface —
 * editing, offers, socials, analytics — switches off at once.
 */

declare(strict_types=1);

namespace Oria\Core\Ownership;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ROLE = 'practitioner';

const LISTING_CAPS = array(
	'edit_oria_listing',
	'read_oria_listing',
	'delete_oria_listing',
	'edit_oria_listings',
	'edit_others_oria_listings',
	'publish_oria_listings',
	'read_private_oria_listings',
	'delete_oria_listings',
	'delete_private_oria_listings',
	'delete_published_oria_listings',
	'delete_others_oria_listings',
	'edit_private_oria_listings',
	'edit_published_oria_listings',
);

const EVENT_CAPS = array(
	'edit_oria_event',
	'read_oria_event',
	'delete_oria_event',
	'edit_oria_events',
	'edit_others_oria_events',
	'publish_oria_events',
	'read_private_oria_events',
	'delete_oria_events',
	'delete_private_oria_events',
	'delete_published_oria_events',
	'delete_others_oria_events',
	'edit_private_oria_events',
	'edit_published_oria_events',
);

/**
 * The slice of event capabilities a practitioner holds: create, publish and
 * edit their own events — and, unlike listings, delete their own too, since
 * an expired event is theirs to clean up.
 */
const PRACTITIONER_EVENT_CAPS = array(
	'edit_oria_events',
	'edit_published_oria_events',
	'publish_oria_events',
	'delete_oria_events',
	'delete_published_oria_events',
);

function bootstrap(): void {
	// plugins_loaded, not init: role caps must exist before WordPress builds
	// the current user's capability set, or an admin's own session can miss
	// the freshly granted listing caps and the menu disappears.
	add_action( 'plugins_loaded', __NAMESPACE__ . '\ensure_roles', 5 );

	// Belt and braces: administrators can never be locked out of listings,
	// whatever state the persisted role happens to be in.
	add_filter( 'user_has_cap', __NAMESPACE__ . '\admins_always_manage_listings', 10, 4 );

	add_filter( 'map_meta_cap', __NAMESPACE__ . '\scope_to_own_listing', 10, 4 );
	add_filter( 'login_redirect', __NAMESPACE__ . '\login_landing', 10, 3 );
	add_action( 'admin_menu', __NAMESPACE__ . '\practitioner_submenu', 998 );
	add_action( 'admin_menu', __NAMESPACE__ . '\trim_admin_menu', 999 );
	add_action( 'pre_get_posts', __NAMESPACE__ . '\limit_list_table' );
	add_filter( 'ajax_query_attachments_args', __NAMESPACE__ . '\own_media_only' );
	add_filter( 'acf/prepare_field', __NAMESPACE__ . '\admin_only_fields' );
	foreach ( array( 'claim_status', 'claimed_by', 'admin_featured', 'verified_at', 'google_place_id', 'category_priority', 'primary_practice' ) as $locked ) {
		add_filter( "acf/update_value/name={$locked}", __NAMESPACE__ . '\lock_operator_values', 10, 3 );
	}
	add_filter( 'acf/validate_value/name=gallery', __NAMESPACE__ . '\enforce_gallery_limit', 10, 4 );
	add_filter( 'acf/pre_update_value', __NAMESPACE__ . '\guard_field_writes', 10, 4 );
	add_filter( 'views_edit-' . PostTypes\LISTING, __NAMESPACE__ . '\hide_list_views' );
	add_filter( 'views_edit-' . PostTypes\EVENT, __NAMESPACE__ . '\hide_list_views' );

	// A practitioner's profile screen is for name, email and password —
	// not WordPress internals.
	add_action( 'admin_head-profile.php', __NAMESPACE__ . '\trim_profile_screen' );
	add_filter( 'user_contactmethods', __NAMESPACE__ . '\no_contact_methods', PHP_INT_MAX, 2 );
	add_filter( 'wp_is_application_passwords_available_for_user', __NAMESPACE__ . '\no_application_passwords', 10, 2 );
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\trim_admin_bar', 999 );
	add_filter( 'screen_options_show_screen', __NAMESPACE__ . '\hide_screen_options' );
	add_action( 'acf/save_post', __NAMESPACE__ . '\stamp_event_listing', 20 );
}

/** The listing a user owns, or 0. */
function owned_listing( int $user_id ): int {
	$posts = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'claimed_by',
					'value' => $user_id,
				),
			),
		)
	);
	return $posts ? (int) $posts[0] : 0;
}

/** Whether a listing is in a paid (managed) state. */
function is_paid( int $post_id ): bool {
	return in_array(
		(string) get_post_meta( $post_id, 'claim_status', true ),
		array( 'claimed', 'featured' ),
		true
	);
}

/** Whether this user manages this listing right now (owner + paid). */
function manages( int $user_id, int $post_id ): bool {
	return $post_id > 0
		&& (int) get_post_meta( $post_id, 'claimed_by', true ) === $user_id
		&& is_paid( $post_id );
}

/* ----------------------------------------------------------------- roles */

function ensure_roles(): void {
	if ( null === get_role( ROLE ) ) {
		add_role(
			ROLE,
			__( 'Practitioner', 'oria' ),
			array(
				'read'                        => true,
				'upload_files'                => true,
				'edit_oria_listings'          => true,
				'edit_published_oria_listings' => true,
				'edit_others_oria_listings'   => true,
			)
		);
	}

	// Event caps arrived after the role first shipped — top up in place.
	$role = get_role( ROLE );
	if ( $role && ! $role->has_cap( 'edit_oria_events' ) ) {
		foreach ( PRACTITIONER_EVENT_CAPS as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/*
	 * edit_others_oria_listings, counter-intuitively, is required for a
	 * practitioner to save their OWN listing. Imported listings are
	 * authored by the admin; ownership is the claimed_by field. Core's
	 * save path (_wp_translate_postdata) refuses any update where the
	 * form's post_author isn't the current user unless the user holds
	 * the type's edit_others cap — and the classic edit form always
	 * posts the original author. Without this cap every practitioner
	 * save died with "Sorry, you are not allowed to edit posts as this
	 * user." Which listings they can actually touch is still walled
	 * per-post by scope_to_own_listing below: everything they don't own
	 * maps to do_not_allow, whatever role caps say.
	 */
	if ( $role && ! $role->has_cap( 'edit_others_oria_listings' ) ) {
		$role->add_cap( 'edit_others_oria_listings' );
	}

	grant_admin_caps();
}

/**
 * Runtime guarantee: any user who can manage the site can manage listings.
 * This works even if the persisted administrator role predates the plugin
 * or was rebuilt by another plugin.
 *
 * @param array<string, bool> $allcaps
 * @return array<string, bool>
 */
function admins_always_manage_listings( array $allcaps, $caps = array(), $args = array(), $user = null ): array {
	if ( ! empty( $allcaps['manage_options'] ) ) {
		foreach ( array_merge( LISTING_CAPS, EVENT_CAPS ) as $cap ) {
			$allcaps[ $cap ] = true;
		}
		return $allcaps;
	}

	// The events wall: events belong to the Featured plan. A practitioner on
	// the Claimed plan — or whose listing has lapsed — has no event caps,
	// same as every other paid surface switching with the subscription.
	if ( $user instanceof \WP_User && in_array( ROLE, (array) $user->roles, true ) ) {
		static $events_cache = array();
		$uid = (int) $user->ID;
		if ( ! array_key_exists( $uid, $events_cache ) ) {
			$listing               = owned_listing( $uid );
			$events_cache[ $uid ] = $listing
				&& manages( $uid, $listing )
				&& \Oria\Core\Tiers\allows( $listing, 'events' );
		}
		if ( ! $events_cache[ $uid ] ) {
			foreach ( EVENT_CAPS as $cap ) {
				unset( $allcaps[ $cap ] );
			}
		}
	}
	return $allcaps;
}

/** Administrators get every listing and event capability (idempotent). */
function grant_admin_caps(): void {
	$admin = get_role( 'administrator' );
	if ( ! $admin ) {
		return;
	}
	if ( ! $admin->has_cap( 'edit_others_oria_listings' ) ) {
		foreach ( LISTING_CAPS as $cap ) {
			$admin->add_cap( $cap );
		}
	}
	if ( ! $admin->has_cap( 'edit_others_oria_events' ) ) {
		foreach ( EVENT_CAPS as $cap ) {
			$admin->add_cap( $cap );
		}
	}
}

/* ------------------------------------------------------------ capability */

/**
 * A practitioner's listing caps only apply to the listing they own, and
 * only while it is paid. Deleting is never theirs.
 *
 * @param string[] $caps
 * @param mixed[]  $args
 * @return string[]
 */
function scope_to_own_listing( array $caps, string $cap, int $user_id, array $args ): array {
	if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) || empty( $args[0] ) ) {
		return $caps;
	}

	$post = get_post( (int) $args[0] );
	if ( ! $post || ! in_array( $post->post_type, array( PostTypes\LISTING, PostTypes\EVENT ), true ) ) {
		return $caps;
	}

	$user = get_userdata( $user_id );
	if ( ! $user || ! in_array( ROLE, (array) $user->roles, true ) ) {
		return $caps; // Admins and everyone else: default mapping.
	}

	// Events: their own (authored) events, while their listing stays paid.
	// The paid check itself lives in the user_has_cap filter, which strips
	// event caps from lapsed practitioners — here ownership is enough.
	if ( PostTypes\EVENT === $post->post_type ) {
		if ( (int) $post->post_author !== $user_id ) {
			return array( 'do_not_allow' );
		}
		return array( 'delete_post' === $cap ? 'delete_oria_events' : 'edit_oria_events' );
	}

	if ( 'delete_post' === $cap ) {
		return array( 'do_not_allow' );
	}

	// Any approved owner may open their listing — the free plan covers
	// location and contact edits. Which fields are writable is enforced
	// per-field by tier, not here.
	$owns = (int) get_post_meta( (int) $post->ID, 'claimed_by', true ) === $user_id;
	return $owns
		? array( 'edit_oria_listings' )
		: array( 'do_not_allow' );
}

/* --------------------------------------------------------- admin surface */

/** @param string $redirect @param string $requested @param \WP_User|\WP_Error $user */
function login_landing( $redirect, $requested, $user ) {
	if ( $user instanceof \WP_User && in_array( ROLE, (array) $user->roles, true ) ) {
		$listing = owned_listing( (int) $user->ID );
		/*
		 * Every approved owner can open their listing now -- free plan
		 * included -- so login lands straight on it. On the My Oria
		 * dashboard rather than the wp-admin edit screen: that screen is
		 * now the administrators' tool, and the front door for an owner is
		 * the manager that was built for them.
		 */
		if ( $listing && function_exists( '\Oria\Core\MyOria\url' ) ) {
			return \Oria\Core\MyOria\url( 'listing' );
		}
		if ( $listing ) {
			return admin_url( 'post.php?post=' . $listing . '&action=edit' );
		}
	}
	return $redirect;
}

/**
 * Every submenu item under Listings (Add new, taxonomies, claim requests)
 * is admin-only, so a practitioner's Listings submenu would be empty — and
 * WordPress denies access to any menu page whose submenu is empty, because
 * it can no longer resolve the page's parent. One self-referencing entry
 * they can access keeps the menu resolvable. Same for Events when their
 * plan includes it.
 */
function practitioner_submenu(): void {
	if ( ! is_practitioner() ) {
		return;
	}
	$listings = 'edit.php?post_type=' . PostTypes\LISTING;
	add_submenu_page( $listings, __( 'Your listing', 'oria' ), __( 'Your listing', 'oria' ), 'edit_oria_listings', $listings );

	if ( current_user_can( 'edit_oria_events' ) ) {
		$events = 'edit.php?post_type=' . PostTypes\EVENT;
		add_submenu_page( $events, __( 'Your workshops/events', 'oria' ), __( 'Your workshops/events', 'oria' ), 'edit_oria_events', $events );
		return;
	}

	/*
	 * Everyone else gets the tab anyway, as a door rather than a wall.
	 *
	 * Events are the Featured plan's headline feature and a practitioner
	 * below it saw no trace of them: no menu item, no mention, nothing to
	 * be curious about. A feature nobody knows exists cannot be the reason
	 * anybody upgrades. So the tab is always there — it runs workshops for
	 * a Featured listing, and for everybody else it explains what it would
	 * do and sells the plan that does it.
	 */
	add_submenu_page(
		$listings,
		__( 'Workshops & events', 'oria' ),
		__( 'Workshops & events', 'oria' ),
		'edit_oria_listings',
		'oria-events',
		__NAMESPACE__ . '\events_upsell_screen'
	);
}

/**
 * The Workshops & events tab for a practitioner whose plan does not include
 * them: what it does, what it is worth, and one way to get it.
 *
 * Deliberately a real page rather than a disabled menu item. Somebody who
 * clicks it is interested, which is the most useful signal a pricing page
 * ever gets, and meeting that with a dead link wastes it.
 */
function events_upsell_screen(): void {
	$listing = owned_listing( get_current_user_id() );
	$email   = (string) ( wp_get_current_user()->user_email ?? '' );
	$plan    = \Oria\Core\Tiers\summary( \Oria\Core\Tiers\FEATURED );

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Workshops & events', 'oria' ) . '</h1>';

	echo '<div style="max-width:760px;margin-top:20px;background:linear-gradient(135deg,#0E3B38 0%,#16544E 100%);border-radius:16px;padding:28px 32px;color:#FFFFFF;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">';

	echo '<span style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#C9A24B;font-weight:700;">'
		. esc_html( sprintf( __( 'Featured · %s/month', 'oria' ), $plan['price'] ) ) . '</span>';

	echo '<h2 style="margin:8px 0 6px;color:#FFFFFF;font-size:22px;letter-spacing:-0.3px;">'
		. esc_html__( 'Put your workshops in front of Perth', 'oria' ) . '</h2>';

	echo '<p style="margin:0 0 4px;color:#A9C2B7;font-size:13px;line-height:1.6;max-width:56ch;">'
		. esc_html__( 'On the Featured plan this tab becomes your own events diary. Anything you publish here goes onto Oria Haven’s What’s On listings alongside the rest of Perth, with its own page, photos and a booking link — and it is tied to your listing, so people who find the workshop find the practice.', 'oria' )
		. '</p>';

	echo '<p style="margin:14px 0 0;color:#A9C2B7;font-size:13px;">'
		. esc_html__( 'Featured also includes:', 'oria' ) . '</p>';

	echo '<ul style="margin:8px 0 0;padding:0 0 0 18px;color:#C8D9CF;font-size:13px;line-height:1.9;">';
	foreach ( (array) $plan['features'] as $line ) {
		echo '<li>' . esc_html( (string) $line ) . '</li>';
	}
	echo '</ul>';

	if ( $listing && function_exists( '\Oria\Core\Billing\pay_url' ) && \Oria\Core\Billing\configured() ) {
		printf(
			'<p style="margin:22px 0 0;"><a href="%s" style="display:inline-block;background:#C9A24B;color:#082220;text-decoration:none;font-weight:600;font-size:14px;padding:12px 28px;border-radius:999px;">%s &rarr;</a></p>',
			esc_url( \Oria\Core\Billing\pay_url( \Oria\Core\Tiers\FEATURED, $listing, $email ) ),
			esc_html__( 'Go Featured', 'oria' )
		);
	}

	echo '</div>';

	echo '<p style="margin-top:16px;max-width:760px;color:#50575e;font-size:13px;">'
		. esc_html__( 'Nothing about your listing changes if you stay where you are — your details, services, hours and photos are yours to edit either way.', 'oria' )
		. '</p>';

	echo '</div>';
}

/** Practitioners see their listing and their profile — nothing else. */
function trim_admin_menu(): void {
	if ( ! is_practitioner() ) {
		return;
	}
	global $menu;
	$keep = array(
		'edit.php?post_type=' . PostTypes\LISTING,
		'edit.php?post_type=' . PostTypes\EVENT,
		'profile.php',
		'upload.php',
	);
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && ! in_array( $item[2], $keep, true ) ) {
			remove_menu_page( $item[2] );
		}
	}
}

/**
 * The admin bar a practitioner sees: their site, their account, and the
 * listing they are editing. Nothing else.
 *
 * An allow-list rather than a list of things to remove, which is how
 * trim_admin_menu() handles the sidebar for the same reason. The host
 * installs its own menu — Hostinger's sits up there for every user — and
 * so does the occasional plugin, and a deny-list only ever removes the
 * clutter somebody has already noticed. A practitioner logging in to fix
 * their opening hours should not be offered their landlord's control
 * panel.
 *
 * Removing a node takes its children with it, so the keep list only names
 * the top level. my-account has to survive: it holds Log Out.
 */
function trim_admin_bar( \WP_Admin_Bar $bar ): void {
	if ( ! is_practitioner() ) {
		return;
	}

	$keep = array(
		'site-name',   // Visit site / Dashboard
		'my-account',  // Howdy, and the way back out
		'menu-toggle', // the sidebar toggle on a phone
		'view',        // View this listing
		'edit',        // Edit this listing, from the front end
		'archive',
		'preview',
	);

	foreach ( $bar->get_nodes() ?: array() as $node ) {
		// Only the top level; children go with their parent.
		if ( ! in_array( (string) $node->parent, array( '', 'root-default', 'top-secondary' ), true ) ) {
			continue;
		}
		if ( ! in_array( (string) $node->id, $keep, true ) ) {
			$bar->remove_node( (string) $node->id );
		}
	}
}

/**
 * No Screen Options tab for practitioners.
 *
 * It exists to show and hide metaboxes and to set how many rows a table
 * lists. A practitioner has one listing and a fixed set of fields, so the
 * tab offers nothing to configure and quite a lot to break — the first
 * thing it lets you do is hide the field group you came to edit.
 *
 * @param bool $show
 */
function hide_screen_options( $show ) {
	return is_practitioner() ? false : $show;
}

/** The practitioners' Listings table shows only their own. */
function limit_list_table( \WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || ! is_practitioner() ) {
		return;
	}
	if ( PostTypes\EVENT === $query->get( 'post_type' ) ) {
		$query->set( 'author', get_current_user_id() );
		return;
	}
	if ( PostTypes\LISTING !== $query->get( 'post_type' ) ) {
		return;
	}
	$query->set(
		'meta_query',
		array(
			array(
				'key'   => 'claimed_by',
				'value' => get_current_user_id(),
			),
		)
	);
}

/**
 * The list-table view links (All (40) | Published (40)…) count every post
 * on the site — meaningless and confusing for a practitioner who can only
 * see their own. Hide the row for them entirely.
 *
 * @param array<string, string> $views
 * @return array<string, string>
 */
function hide_list_views( array $views ): array {
	return is_practitioner() ? array() : $views;
}

/** Practitioners browse only their own uploads in the media modal. */
function own_media_only( array $args ): array {
	if ( is_practitioner() ) {
		$args['author'] = get_current_user_id();
	}
	return $args;
}

/**
 * Directory-operator fields for practitioners: status and owner never
 * appear at all; the verification date and the Places plumbing stay
 * visible but greyed out so owners can see them without changing them.
 *
 * @param array|false $field
 * @return array|false
 */
function admin_only_fields( $field ) {
	if ( ! is_array( $field ) || current_user_can( 'manage_options' ) ) {
		return $field;
	}
	// By prepare-time ACF has rewritten 'name' into the input name
	// (acf[field_…]); the field's own name survives in '_name'.
	$name = $field['_name'] ?? ( $field['name'] ?? '' );
	if ( in_array( $name, array( 'claim_status', 'claimed_by', 'admin_featured', 'category_priority', 'primary_practice' ), true ) ) {
		return false;
	}
	// The event's "Run by" picker: practitioners never choose — the field is
	// stamped with their own listing on save (stamp_event_listing).
	if ( 'listing' === $name ) {
		return false;
	}
	if ( 'verified_at' === $name ) {
		$field                 = lock( $field );
		$field['instructions'] = 'Set by Oria Haven when your claim is approved or your details are re-checked.';
	}
	if ( 'google_place_id' === $name ) {
		$field                 = lock( $field );
		$field['instructions'] = 'Managed by Oria Haven — links your listing to its Google Business Profile.';
	}

	/*
	 * Plan gating. A free owner can now put their listing right -- services,
	 * hours, amenities, parking, who it suits -- and what stays locked is
	 * what would give them an advantage.
	 *
	 * The lock says what the field would DO for them and links to the
	 * checkout, because a padlock and the word "upgrade" tells somebody they
	 * are being charged without telling them what for. The sell belongs here,
	 * at the moment they reached for the thing, rather than only in a banner
	 * above the fold that they scrolled past.
	 */
	$listing = owned_listing( get_current_user_id() );
	if ( $listing && ! \Oria\Core\Tiers\field_editable( $listing, $name ) ) {
		$field                     = lock( $field );
		$field['wrapper']['class'] = trim( ( $field['wrapper']['class'] ?? '' ) . ' oria-locked' );
		$field['instructions']     = upgrade_note( $name, $listing );
	}
	return $field;
}

/**
 * The note under a locked field: what it would do, and a way to get it.
 *
 * Falls back to naming the plan where no benefit line is written, so a
 * newly gated field is still explained rather than silently padlocked.
 */
function upgrade_note( string $name, int $listing ): string {
    $sell = \Oria\Core\Tiers\field_sell( $name );
    $note = '' !== $sell
        ? $sell
        : __( 'Part of the Claimed plan.', 'oria' );

    if ( ! function_exists( '\Oria\Core\Billing\pay_url' ) || ! \Oria\Core\Billing\configured() ) {
        return $note;
    }

    $email = (string) ( wp_get_current_user()->user_email ?? '' );

    return sprintf(
        '%s <a href="%s">%s</a>',
        esc_html( $note ),
        esc_url( \Oria\Core\Billing\pay_url( 'claimed', $listing, $email ) ),
        esc_html__( 'Unlock it with Claimed, $29/month', 'oria' )
    );
}

/**
 * Grey a field out, in the form its own field type understands.
 *
 * ACF's 'disabled' is not one thing. On an input it is a boolean. On a
 * field built from choices it is the LIST OF CHOICES to disable, and the
 * checkbox renderer runs array_map() straight over it -- so the boolean
 * that works everywhere else is a fatal TypeError there, and it takes the
 * whole edit screen with it.
 *
 * That is what a practitioner on the free plan hit. Amenities is a
 * checkbox gated to the Claimed plan, so every free-plan owner who opened
 * their own listing got a white page. Administrators never saw it: this
 * whole function returns early for anyone who can manage_options.
 *
 * So a choice-based field is locked by naming all of its choices, which is
 * what ACF means by disabled there and renders every box greyed, and
 * everything else keeps the boolean.
 *
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function lock( array $field ): array {
	$choice_types = array( 'checkbox', 'radio', 'button_group' );

	if ( in_array( $field['type'] ?? '', $choice_types, true ) ) {
		$choices = (array) ( $field['choices'] ?? array() );
		// Named choices, never a bare true: an empty list would leave the
		// field editable, so fall back to the boolean when there are no
		// choices to name.
		$field['disabled'] = $choices ? array_keys( $choices ) : 1;
		return $field;
	}

	$field['disabled'] = 1;
	return $field;
}

/**
 * Server-side twin of the plan gating: a crafted request can't write a
 * field the plan doesn't include. Returning true tells ACF the write is
 * handled, so nothing is stored — repeaters included.
 *
 * @param mixed      $check
 * @param mixed      $value
 * @param int|string $post_id
 * @return mixed
 */
function guard_field_writes( $check, $value, $post_id, array $field ) {
	if ( null !== $check || ! is_practitioner() || ! is_numeric( $post_id ) ) {
		return $check;
	}
	if ( PostTypes\LISTING !== get_post_type( (int) $post_id ) ) {
		return $check;
	}
	if ( ! \Oria\Core\Tiers\field_editable( (int) $post_id, (string) ( $field['name'] ?? '' ) ) ) {
		return true; // Swallow the write, keep the stored value.
	}
	return $check;
}


/**
 * Server-side twin of admin_only_fields: hiding or disabling an input only
 * protects the form, not a crafted POST. Any save by a logged-in user
 * without manage_options keeps the stored value. Unauthenticated contexts
 * (WP-CLI imports, the claim-approval admin action) pass through untouched.
 *
 * @param mixed      $value
 * @param int|string $post_id
 * @param array      $field
 * @return mixed
 */
function lock_operator_values( $value, $post_id, array $field ) {
	if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
		return get_field( $field['name'], $post_id, false );
	}
	return $value;
}

/**
 * After an event saves: a practitioner's event always belongs to their own
 * listing — no picker, no way to publish under someone else's name — and the
 * event inherits the listing's practice and area terms so it lands in the
 * right archives without a taxonomy UI. Admin saves only inherit terms when
 * the event has none, so manual curation sticks.
 *
 * @param int|string $post_id acf/save_post also fires for options pages.
 */
function stamp_event_listing( $post_id ): void {
	if ( ! is_numeric( $post_id ) || PostTypes\EVENT !== get_post_type( (int) $post_id ) ) {
		return;
	}
	$post_id = (int) $post_id;
	$is_pr   = is_practitioner();

	if ( $is_pr && function_exists( 'update_field' ) ) {
		$own = owned_listing( get_current_user_id() );
		if ( $own ) {
			update_field( 'listing', $own, $post_id );
		}
	}

	$listing = (int) ( function_exists( 'get_field' ) ? get_field( 'listing', $post_id ) : 0 );
	if ( ! $listing ) {
		return;
	}

	$existing = wp_get_post_terms( $post_id, \Oria\Core\Taxonomies\PRACTICE );
	if ( $is_pr || empty( $existing ) || is_wp_error( $existing ) ) {
		foreach ( array( \Oria\Core\Taxonomies\PRACTICE, \Oria\Core\Taxonomies\AREA ) as $tax ) {
			$terms = wp_get_post_terms( $listing, $tax, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				wp_set_object_terms( $post_id, $terms, $tax );
			}
		}
	}
}

/**
 * The Claimed plan includes 4 gallery photos; Featured is unlimited.
 * Enforced at save so the limit can't be bypassed with a crafted request.
 * Admins are never limited.
 *
 * @param bool|string $valid
 * @param mixed       $value
 * @return bool|string
 */
function enforce_gallery_limit( $valid, $value, array $field, $input_name ) {
	if ( true !== $valid || ! is_practitioner() ) {
		return $valid;
	}
	$listing = owned_listing( get_current_user_id() );
	$limit   = $listing ? \Oria\Core\Tiers\gallery_limit( $listing ) : 0;
	if ( $limit > 0 && count( (array) $value ) > $limit ) {
		return \Oria\Core\Tiers\gallery_note( $listing );
	}
	return $valid;
}

/**
 * Hide the Personal Options block (colour schemes, toolbar, language…) on a
 * practitioner's own profile. WordPress hardcodes that table with no remove
 * hooks, so the accepted pattern is to hide it — every row in the first
 * form-table on profile.php is a personal option.
 */
function trim_profile_screen(): void {
	if ( ! is_practitioner() ) {
		return;
	}

	// Third-party profile sections — Yoast's social-profile fields chief
	// among them — hook these actions; unhook anything SEO-flavoured.
	global $wp_filter;
	foreach ( array( 'show_user_profile', 'personal_options', 'profile_personal_options' ) as $hook ) {
		if ( empty( $wp_filter[ $hook ] ) ) {
			continue;
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $id => $cb ) {
				$fn    = $cb['function'];
				$owner = '';
				if ( is_array( $fn ) ) {
					$owner = is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0];
				} elseif ( is_string( $fn ) ) {
					$owner = $fn;
				}
				if ( preg_match( '/yoast|wpseo/i', $owner ) ) {
					unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ] );
				}
			}
		}
	}

	echo '<style>
		#your-profile > h2:first-of-type,
		#your-profile > table.form-table:first-of-type { display: none; }
	</style>';
}

/**
 * No contact-method fields (Yoast's Facebook/Instagram/X/etc included) on
 * practitioner profiles — their practice's social links live on the
 * listing, where they belong.
 *
 * @param array<string, string> $methods
 * @param \WP_User|false        $user
 * @return array<string, string>
 */
function no_contact_methods( array $methods, $user = false ): array {
	$about_practitioner = $user instanceof \WP_User
		? in_array( ROLE, (array) $user->roles, true )
		: is_practitioner(); // No subject given — fall back to the viewer.
	return $about_practitioner ? array() : $methods;
}

/**
 * Application passwords exist for API integrations; practitioners have no
 * API surface here. Removing availability hides the section and disables
 * the feature for them in one move.
 *
 * @param bool $available
 */
function no_application_passwords( $available, \WP_User $user ): bool {
	if ( in_array( ROLE, (array) $user->roles, true ) ) {
		return false;
	}
	return (bool) $available;
}

function is_practitioner(): bool {
	$user = wp_get_current_user();
	return $user->exists() && in_array( ROLE, (array) $user->roles, true );
}
