<?php
/**
 * The catalogue: an oria_product CPT (admin-only, no public single pages —
 * the shop page and in-content bands are the only surfaces, so no thin SEO
 * pages) and a product_category taxonomy, seeded with the wellness set.
 *
 * DEFAULT_MAP links existing practice-category slugs to product categories;
 * editors refine per-practice via the term-edit screen (Fields\bootstrap)
 * and per-article via the post sidebar. TAG_MAP catches journal tags.
 */

declare(strict_types=1);

namespace Oria\Shop\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CPT = 'oria_product';
const TAX = 'product_category';

/** slug => label. The initial taxonomy from the brief. */
const CATEGORIES = array(
	'yoga-mats'           => 'Yoga mats',
	'yoga-blocks'         => 'Yoga blocks',
	'yoga-straps'         => 'Yoga straps',
	'yoga-bolsters'       => 'Yoga bolsters',
	'meditation-cushions' => 'Meditation cushions',
	'meditation-benches'  => 'Meditation benches',
	'meditation-timers'   => 'Meditation timers',
	'meditation-books'    => 'Meditation books',
	'headphones'          => 'Headphones',
	'diffusers'           => 'Essential oil diffusers',
	'breathwork-books'    => 'Breathwork books',
	'singing-bowls'       => 'Singing bowls',
	'chimes'              => 'Chimes',
	'sound-healing-books' => 'Sound healing books',
	'sleep-masks'         => 'Sleep masks',
	'white-noise'         => 'White noise machines',
	'sleep-books'         => 'Sleep books',
	'mindfulness-books'   => 'Mindfulness books',
	'journals'            => 'Wellness journals',
	'massage-tools'       => 'Massage tools',
	'foam-rollers'        => 'Foam rollers',
	'massage-balls'       => 'Massage balls',
	'recovery-products'   => 'Recovery products',
	'water-bottles'       => 'Water bottles',
	'wellness-books'      => 'Wellness books',
);

/** practice-category slug => product_category slugs (editable per term). */
const DEFAULT_MAP = array(
	'yoga'       => array( 'yoga-mats', 'yoga-blocks', 'yoga-straps', 'yoga-bolsters' ),
	'meditation' => array( 'meditation-cushions', 'meditation-timers', 'meditation-books', 'headphones', 'diffusers' ),
	'breathwork' => array( 'meditation-cushions', 'breathwork-books', 'meditation-timers', 'yoga-mats' ),
	'sound'      => array( 'singing-bowls', 'chimes', 'meditation-cushions', 'sound-healing-books' ),
	'mindfulness'=> array( 'mindfulness-books', 'journals', 'meditation-cushions', 'meditation-timers' ),
	'bodywork'   => array( 'massage-tools', 'foam-rollers', 'massage-balls', 'recovery-products' ),
	'recovery'   => array( 'recovery-products', 'foam-rollers', 'sleep-masks', 'white-noise' ),
	'nutrition'  => array( 'water-bottles', 'wellness-books', 'journals' ),
	'retreats'   => array( 'journals', 'wellness-books', 'yoga-mats' ),
	'energy'     => array( 'singing-bowls', 'chimes', 'journals', 'wellness-books' ),
);

/** journal tag/keyword => product_category slugs. */
const TAG_MAP = array(
	'sleep'      => array( 'sleep-masks', 'white-noise', 'diffusers', 'sleep-books' ),
	'relaxation' => array( 'diffusers', 'meditation-cushions', 'sleep-books' ),
	'meditation' => array( 'meditation-cushions', 'meditation-timers', 'headphones', 'meditation-books' ),
	'yoga'       => array( 'yoga-mats', 'yoga-blocks', 'yoga-straps' ),
	'breathwork' => array( 'breathwork-books', 'meditation-cushions' ),
	'mindfulness'=> array( 'mindfulness-books', 'journals' ),
	'recovery'   => array( 'recovery-products', 'foam-rollers', 'massage-balls' ),
	'sound'      => array( 'singing-bowls', 'chimes' ),
	'beginners'  => array( 'meditation-books', 'yoga-mats', 'journals' ),
);

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register', 6 );
}

function register(): void {
	register_post_type(
		CPT,
		array(
			'label'           => __( 'Products', 'oria' ),
			'labels'          => array(
				'name'          => __( 'Products', 'oria' ),
				'singular_name' => __( 'Product', 'oria' ),
				'add_new_item'  => __( 'Add product', 'oria' ),
				'edit_item'     => __( 'Edit product', 'oria' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 22,
			'menu_icon'       => 'dashicons-cart',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'manage_options' ),
			'map_meta_cap'    => true,
			'taxonomies'      => array( TAX ),
		)
	);

	register_taxonomy(
		TAX,
		array( CPT ),
		array(
			'label'             => __( 'Product categories', 'oria' ),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'show_in_rest'      => true,
		)
	);

	// Seed once; editors add/rename freely afterwards.
	if ( ! get_option( 'oria_shop_seeded' ) ) {
		foreach ( CATEGORIES as $slug => $label ) {
			if ( ! term_exists( $slug, TAX ) ) {
				wp_insert_term( $label, TAX, array( 'slug' => $slug ) );
			}
		}
		update_option( 'oria_shop_seeded', 1, false );
	}
}

/** The affiliate tag from settings ('' = not configured yet). */
function tag(): string {
	return trim( (string) get_option( 'oria_shop_tag', '' ) );
}

/** Marketplace domain, amazon.com.au by default. */
function marketplace(): string {
	return trim( (string) get_option( 'oria_shop_marketplace', 'www.amazon.com.au' ) ) ?: 'www.amazon.com.au';
}

/** Products per band. */
function per_band(): int {
	return max( 1, min( 8, (int) get_option( 'oria_shop_per_band', 4 ) ) );
}

/** Configurable disclosure line, shown wherever products render. */
function disclosure(): string {
	$default = __( 'Affiliate disclosure: some links on Oria Haven are affiliate links. If you buy through them, we may earn a commission at no extra cost to you.', 'oria' );
	return trim( (string) get_option( 'oria_shop_disclosure', '' ) ) ?: $default;
}
