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

/**
 * The tiers as money sees them, which is not what claim_status records.
 *
 * A free-plan listing has an owner and pays nothing: claimed_by is set
 * while claim_status stays unclaimed. The old filter counted those as
 * unclaimed, which hid the most commercially useful segment in the
 * directory — people who have already said yes once and are not yet
 * paying — inside the pile of businesses that have never replied.
 *
 * @return array<string, string>
 */
function tiers(): array {
	return array(
		'unclaimed' => __( 'Unclaimed — no owner', 'oria' ),
		'free'      => __( 'Free plan — owned, not paying', 'oria' ),
		'claimed'   => __( 'Claimed — $29', 'oria' ),
		'featured'  => __( 'Featured — $79', 'oria' ),
		'showcase'  => __( 'Admin featured (showcase)', 'oria' ),
	);
}

/** Category and tier dropdowns above the listings table. */
function status_filter( string $post_type ): void {
	if ( PostTypes\LISTING !== $post_type ) {
		return;
	}

	// The practice taxonomy is public and has a query var, so WordPress
	// filters the list table on this without any help from us.
	$tax = \Oria\Core\Taxonomies\PRACTICE;
	wp_dropdown_categories(
		array(
			'taxonomy'        => $tax,
			'name'            => $tax,
			'value_field'     => 'slug',
			'show_option_all' => __( 'All categories', 'oria' ),
			'orderby'         => 'name',
			'hide_empty'      => false,
			'show_count'      => true,
			'hierarchical'    => false,
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'selected'        => isset( $_GET[ $tax ] ) ? sanitize_title( wp_unslash( (string) $_GET[ $tax ] ) ) : '',
		)
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current = isset( $_GET['oria_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['oria_status'] ) ) : '';
	echo '<select name="oria_status">';
	echo '<option value="">' . esc_html__( 'All tiers', 'oria' ) . '</option>';
	foreach ( tiers() as $value => $label ) {
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

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$status = isset( $_GET['oria_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['oria_status'] ) ) : '';
	if ( '' === $status || ! isset( tiers()[ $status ] ) ) {
		return;
	}

	/*
	 * Ownership is claimed_by; paying is claim_status. Neither alone tells
	 * you which tier a listing is on, so every branch below reads both, and
	 * each also allows for meta that was never written — most of these
	 * listings arrived by import and carry only the fields the importer set.
	 */
	$has_owner = array(
		'key'     => 'claimed_by',
		'value'   => array( '', '0' ),
		'compare' => 'NOT IN',
	);
	$no_owner = array(
		'relation' => 'OR',
		array( 'key' => 'claimed_by', 'compare' => 'NOT EXISTS' ),
		array( 'key' => 'claimed_by', 'value' => array( '', '0' ), 'compare' => 'IN' ),
	);
	$not_paying = array(
		'relation' => 'OR',
		array( 'key' => 'claim_status', 'compare' => 'NOT EXISTS' ),
		array( 'key' => 'claim_status', 'value' => array( 'claimed', 'featured' ), 'compare' => 'NOT IN' ),
	);

	switch ( $status ) {
		case 'unclaimed':
			$meta = $no_owner;
			break;

		case 'free':
			// The segment worth having: somebody owns it, nobody is paying.
			$meta = array( 'relation' => 'AND', $has_owner, $not_paying );
			break;

		case 'showcase':
			$meta = array( array( 'key' => 'admin_featured', 'value' => '1' ) );
			break;

		default: // claimed, featured
			$meta = array( array( 'key' => 'claim_status', 'value' => $status ) );
	}

	$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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
