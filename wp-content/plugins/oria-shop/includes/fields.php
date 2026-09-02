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
				/*
				 * Why we picked it.
				 *
				 * The difference between a product feed and curation, and the one
				 * field that cannot be derived, imported or guessed -- it is the
				 * editor saying who the thing is for and why it earned a place.
				 * Left empty it simply does not render; a card never invents one.
				 */
				array(
					'key'          => 'field_oria_prod_note',
					'name'         => 'editorial_note',
					'label'        => 'Why we picked it',
					'type'         => 'textarea',
					'rows'         => 3,
					'maxlength'    => 320,
					'new_lines'    => '',
					'instructions' => 'Two or three sentences in your own words: who it suits, and what it is good for as an object. Never a claim about health.',
				),
				array(
					'key'           => 'field_oria_prod_bestfor',
					'name'          => 'best_for',
					'label'         => 'Best for',
					'type'          => 'select',
					'allow_null'    => 1,
					'ui'            => 1,
					'choices'       => array(
						'beginners'    => 'Beginners',
						'everyday'     => 'Everyday use',
						'practitioner' => 'Practitioners',
						'gift'         => 'Gifting',
					),
					'wrapper'       => array( 'width' => '50' ),
					'instructions'  => 'A fixed list on purpose: a filter is only useful when every product answers the same question the same way.',
				),
				/*
				 * Collections are editorial groupings, not categories. A product
				 * sits in one category and may appear in several collections, or
				 * none -- an empty collection renders nothing rather than an empty
				 * shelf.
				 */
				array(
					'key'          => 'field_oria_prod_collections',
					'name'         => 'collections',
					'label'        => 'Collections',
					'type'         => 'checkbox',
					'choices'      => array(
						'sound-practice' => 'Start your sound healing practice',
						'calmer-evening' => 'Create a calmer evening',
						'meditation-space' => 'Build your meditation space',
						'movement-recovery' => 'Movement and recovery',
					),
					'wrapper'      => array( 'width' => '50' ),
					'instructions' => 'Optional. Drives the curated shelves on the shop page.',
				),
				array(
					'key'          => 'field_oria_prod_featured',
					'name'         => 'featured',
					'label'        => 'Feature as the Oria Haven pick',
					'type'         => 'true_false',
					'ui'           => 1,
					'instructions' => 'One product at a time. The most recently updated featured product wins, so there is no way to leave two fighting.',
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

	/*
	 * The copy that heads a product category.
	 *
	 * Written per category, never generated: a shelf worth having is a shelf
	 * someone can say something true about. An empty intro renders nothing
	 * at all rather than a paragraph of filler, which is also the signal that
	 * the category is not yet ready to be a page of its own.
	 *
	 * TGA/AHPRA: this is copy about objects, not outcomes. What the thing is,
	 * who tends to buy it, what to look for when choosing — never what it
	 * does to a body. Comparable stores write "1 in 3 Australians have high
	 * blood pressure"; the directory does not get to make that move.
	 */
	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_prodcat',
			'title'    => 'Category page',
			'location' => array( array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => Data\TAX ) ) ),
			'fields'   => array(
				array(
					'key'          => 'field_oria_prodcat_heading',
					'name'         => 'heading',
					'label'        => 'Heading',
					'type'         => 'text',
					'placeholder'  => 'Singing bowls',
					'instructions' => 'Optional. Overrides the category name at the top of the page — "Singing bowls for meditation" reads better as a heading than a filter label does.',
				),
				array(
					'key'          => 'field_oria_prodcat_intro',
					'name'         => 'intro',
					'label'        => 'Introduction',
					'type'         => 'textarea',
					'rows'         => 4,
					'maxlength'    => 600,
					'new_lines'    => '',
					'instructions' => 'Two or three sentences: what this kind of product is, the differences worth knowing when choosing one, and who tends to want it. Describe the object, never a health outcome.',
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
