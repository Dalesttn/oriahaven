<?php
/**
 * The two supporting sections at the foot of a category page: products,
 * then apps.
 *
 * Built here rather than through the plugins' band() functions, which the
 * shop page, listing pages and the [wellness_apps] shortcode also use. The
 * cards, the data and the disclosures are still the plugins' own -- only
 * the frame is the category page's: one shared header (Theme\support_head),
 * three across on the page's own grid, and spacing set by the section
 * rather than by the gaps two unrelated bands left each other.
 *
 * What each shows is decided where it always was, per category: products
 * pinned to the practice in the product's "Practices" field first, then the
 * shop categories mapped to it; apps from the apps plugin's practice map.
 * Neither pads a shelf with unrelated things -- no products, no section;
 * fewer than two apps, no section.
 *
 * @package Oria
 *
 * Args: term (WP_Term, the category).
 */

defined( 'ABSPATH' ) || exit;

$oria_term = $args['term'] ?? null;
if ( ! $oria_term instanceof WP_Term ) {
	return;
}
// No category name in the copy: some names are already phrases
// ("Meditation classes", "Mind & Mental Wellbeing") that read wrongly
// inside a sentence. The cards say what the category is.

// ---------------------------------------------------------------- products
$oria_products = function_exists( '\Oria\Shop\Engine\products_for_practice' )
	? \Oria\Shop\Engine\products_for_practice( $oria_term, 3 )
	: array();

// ---------------------------------------------------------------- apps
$oria_apps = function_exists( '\Oria\Apps\Engine\for_practice' )
	? \Oria\Apps\Engine\for_practice( $oria_term, 3 )
	: array();
if ( count( $oria_apps ) < 2 ) {
	$oria_apps = array();
}

if ( ! $oria_products && ! $oria_apps ) {
	return;
}
?>
<div class="wrap supbands">
	<?php if ( $oria_products ) : ?>
		<section class="supband supband--shop" aria-labelledby="supband-shop" data-sup-section="products">
			<?php
			echo \Oria\Theme\support_head( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
				array(
					'id'    => 'supband-shop',
					'title' => __( 'Products to support your practice', 'oria' ),
					'desc'  => __( 'Useful things for your practice at home, chosen by hand.', 'oria' ),
					'url'   => home_url( '/shop/' ),
					'label' => __( 'Shop all', 'oria' ),
					'aria'  => __( 'Shop all wellness products', 'oria' ),
					'cta'   => 'shop_all',
				)
			);
			?>
			<div class="prodgrid supgrid">
				<?php
				foreach ( $oria_products as $oria_p ) {
					echo \Oria\Shop\Render\card( $oria_p ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
				}
				?>
			</div>
			<p class="shopband__disclosure supband__disclosure"><?php echo esc_html( \Oria\Shop\Data\disclosure() ); ?></p>
			<?php
			if ( function_exists( '\Oria\Shop\Track\impressions' ) ) {
				\Oria\Shop\Track\impressions( $oria_products );
			}
			?>
		</section>
	<?php endif; ?>

	<?php if ( $oria_apps ) : ?>
		<section class="supband supband--apps" aria-labelledby="supband-apps" data-sup-section="apps">
			<?php
			echo \Oria\Theme\support_head( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
				array(
					'id'    => 'supband-apps',
					'title' => __( 'Continue between classes', 'oria' ),
					'desc'  => __( 'Apps that can help you keep your practice going at home.', 'oria' ),
					'url'   => (string) get_post_type_archive_link( \Oria\Apps\Data\CPT ),
					'label' => __( 'All apps', 'oria' ),
					'aria'  => __( 'All wellness apps', 'oria' ),
					'cta'   => 'all_apps',
				)
			);
			?>
			<div class="appgrid supgrid">
				<?php
				foreach ( $oria_apps as $oria_a ) {
					echo \Oria\Apps\Render\card( $oria_a ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
				}
				?>
			</div>
			<?php if ( \Oria\Apps\Render\affiliate_in( $oria_apps ) ) : ?>
				<p class="appband__disclosure supband__disclosure"><?php echo esc_html( \Oria\Apps\Data\disclosure() ); ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
