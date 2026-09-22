<?php
/**
 * A listing cannot go live with nothing to file it under.
 *
 * Sign-up asks for four things now -- practice name, your name, email,
 * phone -- and the owner fills in the rest from their dashboard. That is a
 * better form, and it means a listing can reach the review queue as a name
 * and a phone number with no category and no suburb on it. Approving one of
 * those publishes a page that appears on no category page, in no suburb, and
 * in no facet: findable only by somebody who already knows the URL, which is
 * nobody.
 *
 * So publishing is held rather than refused. The post goes back to Pending,
 * an admin notice says exactly which two things are missing, and the editor
 * fixes them and publishes again. Held, not blocked: the work is already
 * saved, and nothing the editor typed is thrown away.
 *
 * WP-CLI is exempt. The import pipeline sets its terms as it goes and has
 * its own validator, and a guard that bounced a bulk import to Pending
 * halfway through would be a worse problem than the one it solved.
 *
 * Why "an area" rather than "a suburb": of 441 published listings exactly
 * one sits outside the metro with only a region on it -- Floating Sauna
 * Pemberton, filed under Margaret River -- and that is a legitimate way for
 * a regional listing to be filed, not an oversight. Requiring a child term
 * would make that listing unpublishable.
 */

declare(strict_types=1);

namespace Oria\Core\PublishGuard;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const NOTICE = 'oria_publish_hold';

/** taxonomy => the word an editor would use for it. */
const NEEDED = array(
	'practice' => 'a category',
	'area'     => 'a suburb',
);

function bootstrap(): void {
	// Priority 20: tax_input is applied inside wp_insert_post(), before
	// save_post fires, so by here the classic editor's terms are stored.
	add_action( 'save_post_' . PostTypes\LISTING, __NAMESPACE__ . '\guard', 20, 2 );
	/*
	 * The block editor sets terms AFTER the post, in a separate step of the
	 * same request, so save_post would read the old terms and hold a listing
	 * that is about to be filed correctly. This fires once that step is done.
	 */
	add_action( 'rest_after_insert_' . PostTypes\LISTING, __NAMESPACE__ . '\guard_rest', 10, 1 );
	add_action( 'admin_notices', __NAMESPACE__ . '\notice' );
}

/**
 * Whether the guard applies to this request.
 *
 * A seam rather than an inline constant check, so the exemption can be
 * lifted deliberately -- which is the only way to exercise this code from
 * WP-CLI, where it is otherwise always off.
 */
function active(): bool {
	$on = ! ( defined( 'WP_CLI' ) && WP_CLI );

	return (bool) apply_filters( 'oria_publish_guard_active', $on );
}

/** What this listing still needs before it can be seen. */
function missing( int $listing ): array {
	$out = array();

	foreach ( NEEDED as $taxonomy => $label ) {
		$terms = get_the_terms( $listing, $taxonomy );
		if ( ! is_array( $terms ) || ! $terms ) {
			$out[] = $label;
		}
	}

	return $out;
}

function guard( int $listing, \WP_Post $post ): void {
	if ( wp_is_post_autosave( $listing ) || wp_is_post_revision( $listing ) ) {
		return;
	}
	if ( ! active() ) {
		return;
	}
	if ( 'publish' !== $post->post_status ) {
		return;
	}

	$short = missing( $listing );
	if ( ! $short ) {
		return;
	}

	hold( $listing, $short );
}

/** The same test, after the block editor has written its terms. */
function guard_rest( \WP_Post $post ): void {
	guard( (int) $post->ID, $post );
}

/**
 * Put it back to Pending and say why.
 *
 * The static flag matters: wp_update_post() fires save_post again, and
 * without it the guard would call itself until PHP gave up.
 */
function hold( int $listing, array $short ): void {
	static $busy = false;
	if ( $busy ) {
		return;
	}
	$busy = true;

	wp_update_post(
		array(
			'ID'          => $listing,
			'post_status' => 'pending',
		)
	);

	$busy = false;

	set_transient(
		NOTICE . '_' . get_current_user_id(),
		array(
			'listing' => $listing,
			'missing' => $short,
		),
		MINUTE_IN_SECONDS
	);
}

/** Why the listing did not publish, at the top of the screen it happened on. */
function notice(): void {
	$key  = NOTICE . '_' . get_current_user_id();
	$held = get_transient( $key );
	if ( ! is_array( $held ) || empty( $held['missing'] ) ) {
		return;
	}
	delete_transient( $key );

	$title = get_the_title( (int) ( $held['listing'] ?? 0 ) );
	$what  = (array) $held['missing'];
	$list  = count( $what ) > 1
		/* translators: 1: first missing thing, 2: second */
		? sprintf( __( '%1$s and %2$s', 'oria' ), $what[0], $what[1] )
		: (string) $what[0];

	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
		esc_html( sprintf( /* translators: %s: listing name */ __( '%s is still Pending.', 'oria' ), $title ) ),
		esc_html(
			sprintf(
				/* translators: %s: what is missing, e.g. "a category and a suburb" */
				__( 'It needs %s before it can go live — without them the page appears on no category page and in no suburb, so nobody would find it. Set them below and publish again.', 'oria' ),
				$list
			)
		)
	);
}
