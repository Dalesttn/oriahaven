<?php
/**
 * Admin: settings under Products (Associate tag, marketplace, band size,
 * disclosure, refresh cadence), performance columns on the product list,
 * and a click-sources box on the product edit screen.
 */

declare(strict_types=1);

namespace Oria\Shop\Admin;

use Oria\Shop\Data;
use Oria\Shop\Track;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_shop_save', __NAMESPACE__ . '\save' );
	add_filter( 'manage_' . Data\CPT . '_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_' . Data\CPT . '_posts_custom_column', __NAMESPACE__ . '\column', 10, 2 );
	add_action( 'add_meta_boxes_' . Data\CPT, __NAMESPACE__ . '\sources_box' );
	add_action( 'admin_notices', __NAMESPACE__ . '\tag_nag' );
}

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . Data\CPT,
		__( 'Shop settings', 'oria' ),
		__( 'Shop settings', 'oria' ),
		'manage_options',
		'oria-shop',
		__NAMESPACE__ . '\screen'
	);
}

function screen(): void {
	$fields = array(
		'oria_shop_tag'         => array( __( 'Amazon Associate tag', 'oria' ), 'e.g. oriahaven-22 — from your Associates dashboard. Public, appears in every link.' ),
		'oria_shop_marketplace' => array( __( 'Marketplace', 'oria' ), 'www.amazon.com.au' ),
		'oria_shop_per_band'    => array( __( 'Products per band', 'oria' ), '4' ),
		'oria_shop_refresh_h'   => array( __( 'API refresh interval (hours)', 'oria' ), '24 — only used once PA-API access is unlocked.' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Shop settings', 'oria' ); ?></h1>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:44rem">
			<?php wp_nonce_field( 'oria_shop_save' ); ?>
			<input type="hidden" name="action" value="oria_shop_save">
			<table class="form-table">
				<?php foreach ( $fields as $key => $def ) : ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def[0] ); ?></label></th>
						<td>
							<input class="regular-text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) get_option( $key, '' ) ); ?>">
							<p class="description"><?php echo esc_html( $def[1] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="oria_shop_disclosure"><?php esc_html_e( 'Affiliate disclosure', 'oria' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="3" id="oria_shop_disclosure" name="oria_shop_disclosure"><?php echo esc_textarea( (string) get_option( 'oria_shop_disclosure', '' ) ); ?></textarea>
						<p class="description"><?php echo esc_html( sprintf( __( 'Shown under every product band. Empty uses the default: “%s”', 'oria' ), Data\disclosure() ) ); ?></p>
					</td>
				</tr>
			</table>
			<h2><?php esc_html_e( 'Amazon Product Advertising API', 'oria' ); ?></h2>
			<p style="max-width:60ch"><?php esc_html_e( 'Unlocks after 3 qualifying sales. When Amazon issues your keys, add them to wp-config.php — never here, never in the browser:', 'oria' ); ?></p>
			<pre style="background:#fff;border:1px solid #dcdcde;padding:10px">define( 'ORIA_AMAZON_PAAPI_KEY', '…' );
define( 'ORIA_AMAZON_PAAPI_SECRET', '…' );</pre>
			<p><?php echo \Oria\Shop\Providers\Amazon\configured()
				? '<b style="color:#20604C">' . esc_html__( 'API keys detected — scheduled refresh is active.', 'oria' ) . '</b>'
				: esc_html__( 'No API keys detected — running on the hand-curated catalogue (which is all Amazon permits until access is granted).', 'oria' ); ?></p>
			<p><button class="button button-primary"><?php esc_html_e( 'Save settings', 'oria' ); ?></button></p>
		</form>
	</div>
	<?php
}

function save(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nope.' );
	}
	check_admin_referer( 'oria_shop_save' );
	foreach ( array( 'oria_shop_tag', 'oria_shop_marketplace', 'oria_shop_per_band', 'oria_shop_refresh_h', 'oria_shop_disclosure' ) as $key ) {
		update_option( $key, sanitize_text_field( wp_unslash( (string) ( $_POST[ $key ] ?? '' ) ) ), false );
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . Data\CPT . '&page=oria-shop' ) );
	exit;
}

function tag_nag(): void {
	$screen = get_current_screen();
	if ( $screen && str_contains( (string) $screen->id, Data\CPT ) && '' === Data\tag() && current_user_can( 'manage_options' ) ) {
		echo '<div class="notice notice-warning"><p>'
			. esc_html__( 'No Associate tag set — product links currently earn nothing. Add yours under Products → Shop settings.', 'oria' )
			. '</p></div>';
	}
}

/** @param array<string, string> $cols */
function columns( array $cols ): array {
	$cols['oshop_asin']  = __( 'ASIN', 'oria' );
	$cols['oshop_stats'] = __( '30 days', 'oria' );
	return $cols;
}

function column( string $col, int $post_id ): void {
	if ( 'oshop_asin' === $col ) {
		echo '<code>' . esc_html( (string) get_post_meta( $post_id, 'asin', true ) ) . '</code>';
	} elseif ( 'oshop_stats' === $col ) {
		printf(
			/* translators: 1: impressions, 2: clicks */
			esc_html__( '%1$d shown · %2$d clicked', 'oria' ),
			Track\total( $post_id, 'i' ),
			Track\total( $post_id, 'c' )
		);
	}
}

function sources_box( \WP_Post $post ): void {
	$sources = (array) get_post_meta( $post->ID, '_oshop_sources', true );
	if ( ! $sources ) {
		return;
	}
	add_meta_box(
		'oria-shop-sources',
		__( 'Clicks by page', 'oria' ),
		static function () use ( $sources ): void {
			echo '<ul style="margin:0">';
			foreach ( array_slice( $sources, 0, 10, true ) as $path => $count ) {
				echo '<li><code>' . esc_html( (string) $path ) . '</code> — ' . (int) $count . '</li>';
			}
			echo '</ul>';
		},
		Data\CPT,
		'side'
	);
}
