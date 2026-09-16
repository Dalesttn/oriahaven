<?php
/**
 * The apps list in the admin.
 *
 * Built around the two questions an editor actually has: what is in here,
 * and what has gone stale. App facts rot — prices change, free tiers get
 * cut — so "last checked" is a first-class column and anything older than
 * six months says so in red rather than sitting there looking current.
 */

declare(strict_types=1);

namespace Oria\Apps\Admin;

use Oria\Apps\Data;
use Oria\Apps\Engine;
use Oria\Apps\Track;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_filter( 'manage_' . Data\CPT . '_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_' . Data\CPT . '_posts_custom_column', __NAMESPACE__ . '\column', 10, 2 );
	add_filter( 'manage_edit-' . Data\CPT . '_sortable_columns', __NAMESPACE__ . '\sortable' );
	add_action( 'restrict_manage_posts', __NAMESPACE__ . '\filters' );
	add_action( 'pre_get_posts', __NAMESPACE__ . '\apply_filters_query' );
	add_action( 'admin_head', __NAMESPACE__ . '\styles' );
}

function columns( array $columns ): array {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out['oria_best_for'] = __( 'Best for', 'oria' );
			$out['oria_pricing']  = __( 'Pricing', 'oria' );
			$out['oria_aff']      = __( 'Affiliate', 'oria' );
			$out['oria_clicks']   = __( 'Clicks', 'oria' );
			$out['oria_checked']  = __( 'Last checked', 'oria' );
		}
	}
	return $out;
}

function column( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'oria_best_for':
			$labels = array();
			foreach ( (array) get_post_meta( $post_id, 'best_for', true ) as $key ) {
				$labels[] = Data\label( 'best_for', (string) $key );
			}
			echo $labels ? esc_html( implode( ', ', array_slice( $labels, 0, 3 ) ) ) : '<span class="oria-dim">—</span>';
			break;

		case 'oria_pricing':
			$model = (string) get_post_meta( $post_id, 'pricing_model', true );
			$free  = get_post_meta( $post_id, 'free_version_available', true ) ? ' + free' : '';
			echo esc_html( Data\label( 'pricing', $model ?: 'unknown' ) . $free );
			break;

		case 'oria_aff':
			$on = (bool) get_post_meta( $post_id, 'affiliate_available', true );
			echo $on ? '<span class="oria-yes">' . esc_html__( 'Yes', 'oria' ) . '</span>' : '<span class="oria-dim">—</span>';
			break;

		case 'oria_clicks':
			$n = Track\total( $post_id );
			echo $n ? esc_html( (string) $n ) : '<span class="oria-dim">0</span>';
			break;

		case 'oria_checked':
			$date = (string) get_post_meta( $post_id, 'last_verified', true );
			if ( '' === $date ) {
				echo '<span class="oria-stale">' . esc_html__( 'never checked', 'oria' ) . '</span>';
				break;
			}
			$shown = date_i18n( 'j M Y', (int) strtotime( $date ) );
			if ( Engine\stale( $date ) ) {
				printf(
					'<span class="oria-stale" title="%s">%s</span>',
					esc_attr__( 'Not checked for over six months — prices and free tiers may have moved.', 'oria' ),
					esc_html( $shown )
				);
			} else {
				echo esc_html( $shown );
			}
			break;
	}
}

function sortable( array $columns ): array {
	$columns['oria_checked'] = 'oria_checked';
	return $columns;
}

/** Category, pricing model, affiliate and staleness, as dropdowns. */
function filters(): void {
	if ( Data\CPT !== ( $_GET['post_type'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$pricing = (string) ( $_GET['oria_pricing'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	echo '<select name="oria_pricing"><option value="">' . esc_html__( 'Any pricing', 'oria' ) . '</option>';
	foreach ( Data\PRICING as $key => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $pricing, $key, false ), esc_html( $label ) );
	}
	echo '</select>';

	$aff = (string) ( $_GET['oria_aff'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	echo '<select name="oria_aff">';
	printf( '<option value="">%s</option>', esc_html__( 'Affiliate: any', 'oria' ) );
	printf( '<option value="yes"%s>%s</option>', selected( $aff, 'yes', false ), esc_html__( 'Affiliate link in use', 'oria' ) );
	printf( '<option value="no"%s>%s</option>', selected( $aff, 'no', false ), esc_html__( 'No affiliate link', 'oria' ) );
	echo '</select>';

	$stale = (string) ( $_GET['oria_stale'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	echo '<select name="oria_stale">';
	printf( '<option value="">%s</option>', esc_html__( 'Any check date', 'oria' ) );
	printf( '<option value="1"%s>%s</option>', selected( $stale, '1', false ), esc_html__( 'Due a re-check', 'oria' ) );
	echo '</select>';
}

function apply_filters_query( \WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || Data\CPT !== $query->get( 'post_type' ) ) {
		return;
	}

	$meta = array();

	$pricing = sanitize_key( (string) ( $_GET['oria_pricing'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' !== $pricing ) {
		$meta[] = array( 'key' => 'pricing_model', 'value' => $pricing );
	}

	$aff = sanitize_key( (string) ( $_GET['oria_aff'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'yes' === $aff ) {
		$meta[] = array( 'key' => 'affiliate_available', 'value' => '1' );
	} elseif ( 'no' === $aff ) {
		$meta[] = array( 'relation' => 'OR', array( 'key' => 'affiliate_available', 'value' => '1', 'compare' => '!=' ), array( 'key' => 'affiliate_available', 'compare' => 'NOT EXISTS' ) );
	}

	$stale = sanitize_key( (string) ( $_GET['oria_stale'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '1' === $stale ) {
		$cutoff = gmdate( 'Y-m-d', time() - Data\STALE_DAYS * DAY_IN_SECONDS );
		$meta[] = array(
			'relation' => 'OR',
			array( 'key' => 'last_verified', 'value' => $cutoff, 'compare' => '<' ),
			array( 'key' => 'last_verified', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'last_verified', 'value' => '' ),
		);
	}

	if ( $meta ) {
		$query->set( 'meta_query', count( $meta ) > 1 ? array_merge( array( 'relation' => 'AND' ), $meta ) : $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	if ( 'oria_checked' === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', 'last_verified' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query->set( 'orderby', 'meta_value' );
	}
}

function styles(): void {
	$screen = get_current_screen();
	if ( ! $screen || Data\CPT !== $screen->post_type ) {
		return;
	}
	echo '<style>
		.oria-dim { color: #8c8f94; }
		.oria-yes { color: #1d6b4f; font-weight: 600; }
		.oria-stale { color: #b32d2e; font-weight: 600; }
	</style>';
}
