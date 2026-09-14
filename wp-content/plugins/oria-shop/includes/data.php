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

	/*
	 * Filled in after an audit found 203 of 356 listings showing no products
	 * at all — twelve practice terms had no entry, including spa and fitness,
	 * two of the biggest. Every slug below is one of the 25 already in
	 * CATEGORIES; where a practice has no honest match in that vocabulary
	 * (beauty, allied, family, seniors, community) it is deliberately left
	 * out rather than padded with something loosely related.
	 */
	'spa'        => array( 'recovery-products', 'sleep-masks', 'diffusers', 'water-bottles' ),
	'fitness'    => array( 'foam-rollers', 'massage-balls', 'water-bottles', 'yoga-mats' ),
	'mind'       => array( 'mindfulness-books', 'journals', 'meditation-cushions', 'meditation-timers' ),
	'nature'     => array( 'journals', 'water-bottles', 'wellness-books' ),
	'experiences'=> array( 'journals', 'wellness-books', 'water-bottles' ),
	'natural'    => array( 'wellness-books', 'diffusers', 'journals' ),
	'creative'   => array( 'journals', 'wellness-books' ),
	'longevity'  => array( 'recovery-products', 'sleep-books', 'water-bottles', 'wellness-books' ),
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

/**
 * Shop by intention: what a reader wants, mapped to the shelves that answer
 * it. slug => label, a line, the categories. This map is the floor -- a
 * product can add itself to an intention through its own `intents` field.
 * The slugs include both the seeded categories and the ones an editor
 * created since; a slug with no products simply matches nothing.
 */
const INTENTS = array(
	'relax'    => array( 'label' => 'Relax', 'line' => 'Massage tools, masks, sound and scent', 'cats' => array( 'massage-tools', 'massage-balls', 'massage-and-relaxation', 'sleep-masks', 'diffusers', 'singing-bowls', 'chimes', 'sound-healing', 'beauty' ) ),
	'sleep'    => array( 'label' => 'Sleep better', 'line' => 'Masks, sound and a slower evening', 'cats' => array( 'sleep-masks', 'white-noise', 'sleep-books', 'diffusers', 'meditation-cards', 'beauty' ) ),
	'meditate' => array( 'label' => 'Meditate', 'line' => 'Cushions, timers, cards and bowls', 'cats' => array( 'meditation-cushions', 'meditation-benches', 'meditation-timers', 'meditation-books', 'meditation-cards', 'mindfulness-books', 'singing-bowls', 'chimes', 'headphones' ) ),
	'sound'    => array( 'label' => 'Explore sound healing', 'line' => 'Singing bowls, chimes and handpans', 'cats' => array( 'singing-bowls', 'chimes', 'sound-healing', 'sound-healing-books' ) ),
	'yoga'     => array( 'label' => 'Practise yoga', 'line' => 'Mats, blocks, straps and bolsters', 'cats' => array( 'yoga-mats', 'yoga-blocks', 'yoga-straps', 'yoga-bolsters' ) ),
	'recover'  => array( 'label' => 'Recover', 'line' => 'Massage guns, balls and rollers', 'cats' => array( 'massage-tools', 'massage-balls', 'foam-rollers', 'recovery-products', 'massage-and-relaxation' ) ),
	'move'     => array( 'label' => 'Move', 'line' => 'Mats, rollers and the bottle you forget', 'cats' => array( 'yoga-mats', 'foam-rollers', 'massage-balls', 'water-bottles' ) ),
	'home'     => array( 'label' => 'Create a calmer home', 'line' => 'Scent, sound, a cushion, a journal', 'cats' => array( 'diffusers', 'chimes', 'singing-bowls', 'journals', 'meditation-cushions', 'meditation-cards' ) ),
);

/**
 * Curated shelves on the shop page. A product an editor ticked into the
 * collection leads; the categories fill the rest of the shelf, so a shelf
 * has something on it the day the shop opens and gets better as editors
 * choose. `intent` is the intention the shelf's CTA opens.
 */
const COLLECTIONS = array(
	'sound-practice'    => array( 'label' => 'Start your sound healing practice', 'line' => 'A bowl, something to strike it with, and a book to make sense of it.', 'cats' => array( 'singing-bowls', 'chimes', 'sound-healing', 'sound-healing-books' ), 'intent' => 'sound' ),
	'calmer-evening'    => array( 'label' => 'Create a calmer evening', 'line' => 'The small things that mark the end of a day.', 'cats' => array( 'sleep-masks', 'beauty', 'meditation-cards', 'massage-tools', 'massage-and-relaxation', 'diffusers', 'meditation-books' ), 'intent' => 'sleep' ),
	'meditation-space'  => array( 'label' => 'Build your meditation space', 'line' => 'A corner of a room, made for sitting still.', 'cats' => array( 'meditation-cushions', 'meditation-benches', 'singing-bowls', 'meditation-cards', 'meditation-books', 'chimes', 'meditation-timers' ), 'intent' => 'meditate' ),
	'movement-recovery' => array( 'label' => 'Movement and recovery', 'line' => 'For the days you train, and the days after.', 'cats' => array( 'yoga-mats', 'massage-balls', 'massage-tools', 'foam-rollers', 'recovery-products' ), 'intent' => 'recover' ),
);

/**
 * Product category => the directory practices it goes with. "Goes well
 * with" on a card links here, which is the shop's way back into the
 * directory: a bowl to a sound bath, a mat to a class.
 */
const CAT_PRACTICES = array(
	'singing-bowls'          => array( 'sound', 'meditation' ),
	'chimes'                 => array( 'sound', 'meditation' ),
	'sound-healing'          => array( 'sound' ),
	'sound-healing-books'    => array( 'sound' ),
	'meditation-cushions'    => array( 'meditation' ),
	'meditation-benches'     => array( 'meditation' ),
	'meditation-timers'      => array( 'meditation' ),
	'meditation-books'       => array( 'meditation', 'mindfulness' ),
	'meditation-cards'       => array( 'meditation', 'mindfulness' ),
	'mindfulness-books'      => array( 'mindfulness' ),
	'headphones'             => array( 'meditation' ),
	'breathwork-books'       => array( 'breathwork' ),
	'yoga-mats'              => array( 'yoga' ),
	'yoga-blocks'            => array( 'yoga' ),
	'yoga-straps'            => array( 'yoga' ),
	'yoga-bolsters'          => array( 'yoga' ),
	'massage-tools'          => array( 'bodywork', 'recovery' ),
	'massage-balls'          => array( 'bodywork', 'recovery', 'fitness' ),
	'massage-and-relaxation' => array( 'bodywork' ),
	'foam-rollers'           => array( 'recovery', 'fitness' ),
	'recovery-products'      => array( 'recovery' ),
	'sleep-masks'            => array( 'recovery' ),
	'white-noise'            => array( 'recovery' ),
	'diffusers'              => array( 'natural' ),
	'beauty'                 => array( 'beauty' ),
	'journals'               => array( 'mindfulness' ),
	'water-bottles'          => array( 'fitness' ),
);

/**
 * Illustrative artwork per category, shown on a card until the Amazon API
 * supplies the product's own photograph. Drawn in the site's style and
 * labelled as an illustration on the card: it says what kind of thing this
 * is, never what this particular one looks like. Files live in the theme at
 * assets/img/shop/{key}.webp.
 */
const CATEGORY_ART = array(
	'singing-bowls'          => 'bowl',
	'chimes'                 => 'chime',
	'sound-healing'          => 'handpan',
	'meditation-cushions'    => 'cushion',
	'meditation-benches'     => 'cushion',
	'yoga-bolsters'          => 'bolster',
	'yoga-blocks'            => 'yoga-props',
	'yoga-straps'            => 'yoga-props',
	'yoga-mats'              => 'mat',
	'foam-rollers'           => 'roller',
	'recovery-products'      => 'roller',
	'massage-tools'          => 'massage',
	'massage-balls'          => 'massage',
	'massage-and-relaxation' => 'massage',
	'sleep-masks'            => 'sleep',
	'beauty'                 => 'sleep',
	'white-noise'            => 'sound-machine',
	'meditation-timers'      => 'sound-machine',
	'headphones'             => 'sound-machine',
	'meditation-books'       => 'book',
	'mindfulness-books'      => 'book',
	'breathwork-books'       => 'book',
	'sound-healing-books'    => 'book',
	'sleep-books'            => 'book',
	'wellness-books'         => 'book',
	'journals'               => 'book',
	'meditation-cards'       => 'book',
);

/** The illustration for a product's categories: the first category that has one, or none. */
function art_url( array $cat_slugs ): string {
	foreach ( $cat_slugs as $slug ) {
		if ( isset( CATEGORY_ART[ (string) $slug ] ) ) {
			return get_theme_file_uri( 'assets/img/shop/' . CATEGORY_ART[ (string) $slug ] . '.webp' );
		}
	}
	return '';
}

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
