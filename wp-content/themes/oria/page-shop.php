<?php
/**
 * /shop/ — Shop Wellness Products. The whole catalogue, browsable by
 * product category with client-side chips (same pattern as What's On).
 * One catalogue, one engine — this page is just the widest view of it.
 */

declare(strict_types=1);

get_header();

$oria_has_shop = function_exists( '\Oria\Shop\Engine\products' );
$oria_products = array();
$oria_cats     = array();

if ( $oria_has_shop ) {
	$oria_terms = get_terms( array( 'taxonomy' => \Oria\Shop\Data\TAX, 'hide_empty' => true ) );
	$oria_terms = is_wp_error( $oria_terms ) ? array() : $oria_terms;
	$oria_products = \Oria\Shop\Engine\products( wp_list_pluck( $oria_terms, 'term_id' ), 60 );
	foreach ( $oria_terms as $oria_t ) {
		$oria_cats[ $oria_t->name ] = $oria_t->name;
	}
	ksort( $oria_cats );
}
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Shop', 'oria' ); ?></span>
	</nav>
	<div class="row-between" style="align-items:flex-end;margin-top:1rem">
		<div>
			<span class="micro"><?php esc_html_e( 'Hand-picked', 'oria' ); ?></span>
			<h1 class="h1 pagehead__title"><?php esc_html_e( 'Shop wellness products', 'oria' ); ?></h1>
		</div>
		<p class="lede" style="max-width:36ch"><?php esc_html_e( 'Gear and books we\'d actually recommend for the practices in the directory. Links go to Amazon.', 'oria' ); ?></p>
	</div>
</section>

<section class="wrap section section--top-flush" data-shopfilter>
	<?php if ( $oria_products ) : ?>
		<?php if ( count( $oria_cats ) > 1 ) : ?>
			<div class="wofilters__row" role="group" aria-label="<?php esc_attr_e( 'Filter by category', 'oria' ); ?>" style="margin-bottom:1.5rem">
				<button class="fchip is-on" type="button" data-cat=""><?php esc_html_e( 'Everything', 'oria' ); ?></button>
				<?php foreach ( $oria_cats as $oria_c ) : ?>
					<button class="fchip" type="button" data-cat="<?php echo esc_attr( $oria_c ); ?>"><?php echo esc_html( $oria_c ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="prodgrid prodgrid--page">
			<?php
			foreach ( $oria_products as $oria_p ) {
				echo \Oria\Shop\Render\card( $oria_p ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</div>
		<p class="dir__empty" data-shop-empty hidden style="margin-top:2rem"><?php esc_html_e( 'Nothing in that category yet.', 'oria' ); ?></p>
		<p class="shopband__disclosure"><?php echo esc_html( \Oria\Shop\Data\disclosure() ); ?></p>
		<?php \Oria\Shop\Track\impressions( $oria_products ); ?>
	<?php else : ?>
		<div class="dir__empty">
			<h2 class="h3"><?php esc_html_e( 'The shelves are being stocked', 'oria' ); ?></h2>
			<p class="muted" style="margin-top:.5rem"><?php esc_html_e( 'Hand-picked wellness products are on their way — check back soon.', 'oria' ); ?></p>
		</div>
	<?php endif; ?>
</section>

<?php
get_footer();
