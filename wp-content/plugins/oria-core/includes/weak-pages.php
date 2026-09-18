<?php
/**
 * Listings the weak-page audit has taken out of the index, and the rule
 * that keeps them out.
 *
 * tools/weak-pages.php decides; this file enforces. The decision needs
 * Search Console data and a person reading the report, so it is a CLI run.
 * Enforcement has to happen on every page view, so it lives here.
 *
 * A held listing stays on the site. It is still on its category pages, in
 * search, in comparisons, and reachable at its address -- it is only
 * withdrawn from Google's index and from the sitemap, because a page with
 * nothing to say that nobody has searched for in four months costs the
 * site more in quality signals than it earns in traffic. Noindex is the
 * lightest action that achieves that, and it is undone by deleting one row.
 *
 * Two guarantees are enforced here rather than trusted to the audit:
 *
 *   A claimed listing is never held. Somebody owns it and expects to be
 *   found, and it may have been claimed a minute after the audit ran.
 *   is_held() reads claimed_by on every request, so the claim releases it
 *   before the next audit has even been thought of.
 *
 *   The robots rule goes through both Yoast's filter and core's wp_robots,
 *   and the sitemap agrees -- the same three-part pattern seo.php uses, so
 *   the page never says noindex while the sitemap still advertises it.
 */

declare(strict_types=1);

namespace Oria\Core\WeakPages;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** post_id => array{reason: string, at: string, signals: array} */
const OPTION = 'oria_weak_noindex';

function bootstrap(): void {
	// Late, so a held listing stays held whatever an earlier filter decided.
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\robots', 30 );
	add_filter( 'wp_robots', __NAMESPACE__ . '\wp_robots', 30 );
	add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', __NAMESPACE__ . '\sitemap' );
	add_action( 'admin_notices', __NAMESPACE__ . '\notice' );
}

/** @return array<int, array<string, mixed>> */
function held(): array {
	$h = get_option( OPTION, array() );
	return is_array( $h ) ? $h : array();
}

function is_held( int $post_id ): bool {
	if ( $post_id <= 0 || ! isset( held()[ $post_id ] ) ) {
		return false;
	}
	// Owned or showcased: never out of the index, whatever the list says.
	if ( (int) get_post_meta( $post_id, 'claimed_by', true ) ) {
		return false;
	}
	if ( '1' === (string) get_post_meta( $post_id, 'admin_featured', true ) ) {
		return false;
	}
	return PostTypes\LISTING === get_post_type( $post_id );
}

/** @param array<string, mixed> $signals */
function hold( int $post_id, string $reason, array $signals = array() ): void {
	$h             = held();
	$h[ $post_id ] = array(
		'reason'  => $reason,
		'at'      => current_time( 'Y-m-d' ),
		'signals' => $signals,
	);
	update_option( OPTION, $h, false );

	if ( function_exists( '\Oria\Core\Audit\note' ) ) {
		\Oria\Core\Audit\note( $post_id, 'Taken out of Google\'s index by the weak-page audit: ' . $reason . ' Still on the site and in every category it belongs to.' );
	}
}

function release( int $post_id, string $why ): void {
	$h = held();
	if ( ! isset( $h[ $post_id ] ) ) {
		return;
	}
	unset( $h[ $post_id ] );
	update_option( OPTION, $h, false );

	if ( function_exists( '\Oria\Core\Audit\note' ) ) {
		\Oria\Core\Audit\note( $post_id, 'Back in Google\'s index: ' . $why );
	}
}

/* ------------------------------------------------------------- enforcement */

function current_held(): bool {
	return is_singular( PostTypes\LISTING ) && is_held( (int) get_queried_object_id() );
}

/** @param string $robots */
function robots( $robots ) {
	return current_held() ? 'noindex, follow' : $robots;
}

/** @param array<string, bool|string> $r */
function wp_robots( array $r ): array {
	if ( current_held() ) {
		$r['noindex'] = true;
		unset( $r['nofollow'] );
	}
	return $r;
}

/**
 * @param int[] $ids
 * @return int[]
 */
function sitemap( $ids ): array {
	$ids = is_array( $ids ) ? $ids : array();
	foreach ( array_keys( held() ) as $id ) {
		if ( is_held( (int) $id ) ) {
			$ids[] = (int) $id;
		}
	}
	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/**
 * Tell an admin editing a held listing why it is out of the index, and what
 * brings it back -- otherwise the first they hear of it is a practice
 * asking why they cannot find themselves on Google.
 */
function notice(): void {
	if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base || PostTypes\LISTING !== $screen->post_type ) {
		return;
	}
	$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! is_held( $id ) ) {
		return;
	}
	$row = held()[ $id ];
	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p>%s</p></div>',
		esc_html__( 'Not in Google\'s index.', 'oria' ),
		esc_html(
			sprintf(
				/* translators: 1: date, 2: reason */
				__( 'The weak-page audit withdrew it on %1$s: %2$s', 'oria' ),
				mysql2date( 'j M Y', (string) $row['at'] ),
				(string) $row['reason']
			)
		),
		esc_html__( 'It is still on the site and in every category it belongs to. It comes back automatically the moment it is claimed, and on the next audit once its description runs past thirty words or it gains photos, hours or anything else of its own.', 'oria' )
	);
}
