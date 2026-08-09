<?php
/**
 * ACF fields: the product record itself, the per-practice product-category
 * mapping (on the practice term edit screen), and the per-article override
 * (on journal posts). All editable in WordPress, nothing hard-coded.
 */

declare(strict_types=1);

namespace Oria\Shop\Fields;

use Oria\Shop\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'acf/init', __NAMESPACE__ . '\register' );
}

function register(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_product',
			'title'    => 'Product details',
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => Data\CPT ) ) ),
			'position' => 'normal',
			'fields'   => array(
				array(
					'key'          => 'field_oria_prod_asin',
					'name'         => 'asin',
					'label'        => 'Amazon ASIN',
					'type'         => 'text',
					'required'     => true,
					'instructions' => 'From the product page URL: amazon.com.au/dp/B0XXXXXXXX — the B0… code. The affiliate link is built from this and your Associate tag.',
					'wrapper'      => array( 'width' => '34' ),
				),
				array(
					'key'     => 'field_oria_prod_price',
					'name'    => 'price',
					'label'   => 'Price (approx.)',
					'type'    => 'text',
					'placeholder' => '$49',
					'instructions' => 'Shown as “around $49” — approximate wording is deliberate while prices are curated by hand.',
					'wrapper' => array( 'width' => '33' ),
				),
				array(
					'key'     => 'field_oria_prod_brand',
					'name'    => 'brand',
					'label'   => 'Brand',
					'type'    => 'text',
					'wrapper' => array( 'width' => '33' ),
				),
				array(
					'key'          => 'field_oria_prod_blurb',
					'name'         => 'blurb',
					'label'        => 'One-line blurb',
					'type'         => 'text',
					'instructions' => 'Your own words, one sentence — why it belongs in a practice. No health claims.',
				),
				array(
					'key'           => 'field_oria_prod_practices',
					'name'          => 'practices',
					'label'         => 'Show on practices',
					'type'          => 'taxonomy',
					'taxonomy'      => 'practice',
					'field_type'    => 'multi_select',
					'return_format' => 'id',
					'add_term'      => false,
					'save_terms'    => false,
					'load_terms'    => false,
					'instructions'  => 'Pin this product to practice categories — it leads the product band on those practices\' pages, ahead of the automatic category picks. Leave empty to rely on product categories alone.',
				),
				array(
					'key'           => 'field_oria_prod_image',
					'name'          => 'image',
					'label'         => 'Image',
					'type'          => 'image',
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'instructions'  => 'Only images you have the rights to: your own photos, or free-licence stock of the product category. NEVER images saved from Amazon — that breaches the Associates agreement and risks the account. Official Amazon images arrive automatically once PA-API access is unlocked.',
				),
			),
		)
	);

	// Which product categories belong to each practice category.
	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_practice_products',
			'title'    => 'Related products',
			'location' => array( array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'practice' ) ) ),
			'fields'   => array(
				array(
					'key'           => 'field_oria_practice_prodcats',
					'name'          => 'product_categories',
					'label'         => 'Product categories',
					'type'          => 'taxonomy',
					'taxonomy'      => Data\TAX,
					'field_type'    => 'multi_select',
					'return_format' => 'id',
					'add_term'      => false,
					'save_terms'    => false,
					'load_terms'    => false,
					'instructions'  => 'Shown as “Products to support your practice” on this category\'s listings. Leave empty to use the built-in defaults.',
				),
			),
		)
	);

	// Per-article override for the journal.
	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_journal_products',
			'title'    => 'Related products',
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
			'position' => 'side',
			'fields'   => array(
				array(
					'key'           => 'field_oria_post_prodcats',
					'name'          => 'product_categories',
					'label'         => 'Product categories',
					'type'          => 'taxonomy',
					'taxonomy'      => Data\TAX,
					'field_type'    => 'multi_select',
					'return_format' => 'id',
					'add_term'      => false,
					'save_terms'    => false,
					'load_terms'    => false,
					'instructions'  => 'Overrides the automatic pick from this article\'s tags and category.',
				),
			),
		)
	);
}
