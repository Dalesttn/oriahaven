<?php
/**
 * Import by URL: paste amazon.com.au links (or bare ASINs), get draft
 * products ready for review. ASINs are stripped from any URL shape and
 * deduped against the catalogue.
 *
 * Detail fill is tiered: with PA-API configured the official GetItems
 * response supplies title/brand/price/image; without it, a best-effort
 * fetch reads the page <title> only — the same single fact a human
 * curator would copy by hand, nothing more — and when Amazon declines
 * even that, the draft carries a placeholder name to fill in.
 */

declare(strict_types=1);

namespace Oria\Shop\Import;

use Oria\Shop\Data;
use Oria\Shop\Providers\Amazon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TRANSIENT = 'oria_shop_import_report';

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_shop_import', __NAMESPACE__ . '\handle' );
}

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . Data\CPT,
		__( 'Import by URL', 'oria' ),
		__( 'Import by URL', 'oria' ),
		'manage_options',
		'oria-shop-import',
		__NAMESPACE__ . '\screen'
	);
}

function screen(): void {
	$terms  = get_terms( array( 'taxonomy' => Data\TAX, 'hide_empty' => false ) );
	$terms  = is_wp_error( $terms ) ? array() : $terms;
	$report = get_transient( TRANSIENT );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Import products by URL', 'oria' ); ?></h1>
		<p style="max-width:60ch"><?php esc_html_e( 'Paste amazon.com.au product links, one per line — long search links are fine, only the ASIN is kept. Products arrive as drafts for review.', 'oria' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'oria_shop_import' ); ?>
			<input type="hidden" name="action" value="oria_shop_import">
			<textarea name="urls" rows="8" style="width:100%;max-width:46rem;font-family:monospace" placeholder="https://www.amazon.com.au/dp/B071Z185V9&#10;https://www.amazon.com.au/gp/aw/d/198606607X/?ref=…"></textarea>
			<p>
				<label><?php esc_html_e( 'Category:', 'oria' ); ?>
					<select name="category">
						<option value="0"><?php esc_html_e( '— pick later —', 'oria' ); ?></option>
						<?php foreach ( $terms as $t ) : ?>
							<option value="<?php echo (int) $t->term_id; ?>"><?php echo esc_html( $t->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button class="button button-primary" style="margin-left:.75rem"><?php esc_html_e( 'Import as drafts', 'oria' ); ?></button>
			</p>
		</form>

		<?php if ( is_array( $report ) && $report ) : ?>
			<h2><?php esc_html_e( 'Last import', 'oria' ); ?></h2>
			<ul>
				<?php foreach ( $report as $line ) : ?>
					<li><?php echo wp_kses( (string) $line, array( 'a' => array( 'href' => true ) ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}

function handle(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nope.' );
	}
	check_admin_referer( 'oria_shop_import' );

	$category = (int) ( $_POST['category'] ?? 0 );
	$lines    = preg_split( '/\r?\n/', sanitize_textarea_field( wp_unslash( (string) ( $_POST['urls'] ?? '' ) ) ) ) ?: array();

	$report = array();
	foreach ( array_filter( array_map( 'trim', $lines ) ) as $line ) {
		$asin = asin_from( $line );
		if ( '' === $asin ) {
			$report[] = sprintf( __( 'No ASIN found in: %s', 'oria' ), esc_html( mb_substr( $line, 0, 60 ) ) );
			continue;
		}
		$report[] = import_one( $asin, $category );
	}

	set_transient( TRANSIENT, $report, HOUR_IN_SECONDS );
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . Data\CPT . '&page=oria-shop-import' ) );
	exit;
}

/** The 10-character ASIN in any Amazon URL shape, or a bare ASIN line. */
function asin_from( string $line ): string {
	if ( preg_match( '#/(?:dp|gp/product|gp/aw/d|product)/([A-Z0-9]{10})(?:[/?]|$)#i', $line, $m ) ) {
		return strtoupper( $m[1] );
	}
	if ( preg_match( '/^[A-Z0-9]{10}$/i', $line ) ) {
		return strtoupper( $line );
	}
	return '';
}

/** Create (or skip) one draft, fill what we can, return a report line. */
function import_one( string $asin, int $category ): string {
	$existing = get_posts( array( 'post_type' => Data\CPT, 'post_status' => 'any', 'meta_key' => 'asin', 'meta_value' => $asin, 'fields' => 'ids', 'posts_per_page' => 1 ) );
	if ( $existing ) {
		return sprintf( __( '%s — already in the catalogue, skipped.', 'oria' ), $asin );
	}

	$id = wp_insert_post(
		array(
			'post_type'   => Data\CPT,
			'post_status' => 'draft',
			/* translators: %s: ASIN */
			'post_title'  => sprintf( __( 'Amazon product %s — name me', 'oria' ), $asin ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return sprintf( __( '%s — failed to create.', 'oria' ), $asin );
	}
	update_post_meta( $id, 'asin', $asin );
	update_post_meta( $id, '_asin', 'field_oria_prod_asin' );
	if ( $category > 0 ) {
		wp_set_object_terms( $id, $category, Data\TAX );
	}

	$how = fill_details( $id, $asin );
	$edit = get_edit_post_link( $id, 'raw' );

	return sprintf(
		'%s — %s <a href="%s">%s</a>',
		esc_html( $asin ),
		esc_html( $how ),
		esc_url( (string) $edit ),
		esc_html__( 'Review draft', 'oria' )
	);
}

/** Fill title/brand (and more via API). Returns a human description of how. */
function fill_details( int $id, string $asin ): string {
	if ( Amazon\configured() ) {
		$items = Amazon\get_items( array( $asin ) );
		if ( isset( $items[ $asin ] ) ) {
			Amazon\apply_item( $id, $items[ $asin ] );
			return __( 'details from the Amazon API.', 'oria' );
		}
		return __( 'API had no data — name it while reviewing.', 'oria' );
	}

	$title = page_title( $asin );
	if ( '' === $title ) {
		return __( 'Amazon declined the lookup — name it while reviewing.', 'oria' );
	}

	// "Name : Author, A: Amazon.com.au: Books" → name + brand.
	$title = (string) preg_replace( '/\s*:?\s*Amazon\.com\.au.*$/i', '', $title );
	$brand = '';
	if ( preg_match( '/^(.{10,})\s+:\s+([^:]{2,60})$/', $title, $m ) ) {
		$title = trim( $m[1] );
		$brand = trim( $m[2] );
	}
	wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $title ) ) );
	if ( '' !== $brand ) {
		update_post_meta( $id, 'brand', sanitize_text_field( $brand ) );
		update_post_meta( $id, '_brand', 'field_oria_prod_brand' );
	}
	return __( 'title read from the product page.', 'oria' );
}

/** Best-effort page <title> — the one fact a curator would copy by hand. */
function page_title( string $asin ): string {
	$res = wp_remote_get(
		'https://' . Data\marketplace() . '/dp/' . rawurlencode( $asin ),
		array(
			'timeout'     => 15,
			'redirection' => 2,
			'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
			'headers'     => array( 'Accept-Language' => 'en-AU,en;q=0.9' ),
		)
	);
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return '';
	}
	if ( ! preg_match( '#<title>\s*([^<]+?)\s*</title>#i', (string) wp_remote_retrieve_body( $res ), $m ) ) {
		return '';
	}
	$title = html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5 );
	// A captcha interstitial has a title too — don't mistake it for a product.
	return preg_match( '/robot|captcha|sorry/i', $title ) ? '' : $title;
}
