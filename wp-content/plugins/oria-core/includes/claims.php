<?php
/**
 * The claim workflow's admin surface: status at a glance, filtering, and
 * bulk moderation.
 *
 * The public-facing claim form posts into this later; for now the moderation
 * side exists so seeded listings can be managed from day one.
 */

declare(strict_types=1);

namespace Oria\Core\Claims;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const STATUSES = array(
	'unclaimed' => 'Unclaimed',
	'claimed'   => 'Claimed',
	'featured'  => 'Featured',
);

function bootstrap(): void {
	add_filter( 'manage_listing_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_listing_posts_custom_column', __NAMESPACE__ . '\column_content', 10, 2 );
	add_filter( 'manage_edit-listing_sortable_columns', __NAMESPACE__ . '\sortable' );
	add_action( 'restrict_manage_posts', __NAMESPACE__ . '\status_filter' );
	add_action( 'pre_get_posts', __NAMESPACE__ . '\apply_status_filter' );
	add_filter( 'bulk_actions-edit-listing', __NAMESPACE__ . '\bulk_actions' );
	add_filter( 'handle_bulk_actions-edit-listing', __NAMESPACE__ . '\handle_bulk', 10, 3 );
	add_action( 'admin_notices', __NAMESPACE__ . '\bulk_notice' );
}

/** Claim status and verified date in the listings table. */
function columns( array $columns ): array {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out['oria_status']   = __( 'Status', 'oria' );
			$out['oria_verified'] = __( 'Verified', 'oria' );
		}
	}
	return $out;
}

function column_content( string $column, int $post_id ): void {
	if ( 'oria_status' === $column ) {
		$status = get_post_meta( $post_id, 'claim_status', true );
		$status = isset( STATUSES[ $status ] ) ? $status : 'unclaimed';
		printf(
			'<span class="oria-badge oria-badge--%s">%s</span>',
			esc_attr( $status ),
			esc_html( STATUSES[ $status ] )
		);
		return;
	}

	if ( 'oria_verified' === $column ) {
		$date = get_post_meta( $post_id, 'verified_at', true );
		if ( $date ) {
			echo esc_html( mysql2date( 'j M Y', (string) $date ) );
		} else {
			echo '<span style="color:#b32d2e">' . esc_html__( 'never', 'oria' ) . '</span>';
		}
	}
}

function sortable( array $columns ): array {
	$columns['oria_status'] = 'oria_status';
	return $columns;
}

/** A status dropdown above the listings table. */
function status_filter( string $post_type ): void {
	if ( PostTypes\LISTING !== $post_type ) {
		return;
	}

	$current = isset( $_GET['oria_status'] ) ? sanitize_key( (string) $_GET['oria_status'] ) : '';
	echo '<select name="oria_status">';
	echo '<option value="">' . esc_html__( 'All statuses', 'oria' ) . '</option>';
	foreach ( STATUSES as $value => $label ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $value ),
			selected( $current, $value, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
}

function apply_status_filter( \WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( PostTypes\LISTING !== $query->get( 'post_type' ) ) {
		return;
	}

	$status = isset( $_GET['oria_status'] ) ? sanitize_key( (string) $_GET['oria_status'] ) : '';
	if ( '' === $status || ! isset( STATUSES[ $status ] ) ) {
		return;
	}

	if ( 'unclaimed' === $status ) {
		// Unclaimed includes listings where the meta was never written.
		$query->set(
			'meta_query',
			array(
				'relation' => 'OR',
				array(
					'key'   => 'claim_status',
					'value' => 'unclaimed',
				),
				array(
					'key'     => 'claim_status',
					'compare' => 'NOT EXISTS',
				),
			)
		);
		return;
	}

	$query->set(
		'meta_query',
		array(
			array(
				'key'   => 'claim_status',
				'value' => $status,
			),
		)
	);
}

function bulk_actions( array $actions ): array {
	$actions['oria_mark_claimed']  = __( 'Mark claimed', 'oria' );
	$actions['oria_mark_featured'] = __( 'Mark featured', 'oria' );
	$actions['oria_mark_verified'] = __( 'Mark verified today', 'oria' );
	return $actions;
}

function handle_bulk( string $redirect, string $action, array $post_ids ): string {
	$map = array(
		'oria_mark_claimed'  => 'claimed',
		'oria_mark_featured' => 'featured',
	);

	if ( isset( $map[ $action ] ) ) {
		foreach ( $post_ids as $id ) {
			update_post_meta( $id, 'claim_status', $map[ $action ] );
		}
		return add_query_arg( 'oria_bulk', count( $post_ids ), $redirect );
	}

	if ( 'oria_mark_verified' === $action ) {
		$today = current_time( 'Y-m-d' );
		foreach ( $post_ids as $id ) {
			update_post_meta( $id, 'verified_at', $today );
		}
		return add_query_arg( 'oria_bulk', count( $post_ids ), $redirect );
	}

	return $redirect;
}

function bulk_notice(): void {
	if ( empty( $_GET['oria_bulk'] ) ) {
		return;
	}
	$count = (int) $_GET['oria_bulk'];
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( sprintf( _n( '%d listing updated.', '%d listings updated.', $count, 'oria' ), $count ) )
	);
}

/** Badge styling for the admin column. */
add_action(
	'admin_head',
	static function (): void {
		echo '<style>
		.oria-badge{display:inline-block;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:600}
		.oria-badge--unclaimed{background:#f0f0f1;color:#50575e}
		.oria-badge--claimed{background:#edf7f0;color:#1a7a3f}
		.oria-badge--featured{background:#0E3B38;color:#fff}
		</style>';
	}
);
