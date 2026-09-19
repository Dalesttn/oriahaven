<?php
/**
 * Oria Haven v4 -- design test (child of the Oria theme).
 *
 * The redesigned layouts from the design canvas -- front page, category
 * page, listing page, one header, a shorter footer -- in the parent's own
 * colours and fonts (the trial "Sunset" palette was reverted on 19 Sep
 * 2026). The category engine, Trends to Try, Ask Oria, schema and SEO all
 * come from the parent untouched.
 *
 * Lives on branch design/haven-v2 only. Activate it locally with
 * Appearance > Themes; switch back to "Oria" before checking out main,
 * where this folder does not exist.
 *
 * @package Oria
 */

declare(strict_types=1);

// The area pages' figures and editorial layer (taxonomy-area.php).
require_once __DIR__ . '/inc/area-guide.php';
\Oria\V4\Area\bootstrap();

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$dir = get_stylesheet_directory();
		$uri = get_stylesheet_directory_uri();
		// Nunito Sans is self-hosted now (@font-face in v4.css).
		wp_enqueue_style(
			'oria-v4',
			"{$uri}/assets/css/v4.css",
			array( 'oria-pages' ),
			(string) filemtime( "{$dir}/assets/css/v4.css" )
		);

		/*
		 * The three redesigned layouts each bring their own sheet, only on
		 * their own page: the front page, a category (practice) page and a
		 * listing. Their templates here override the parent's by name.
		 */
		/*
		 * The Explore hub (/explore/, /explore/{city}/) wears the category
		 * page's layout (oria-v4/oria-directory-v2.php) and shares its sheet
		 * and script. Same test the router uses to hand it that template
		 * (Oria\Core\PracticesIndex\template()).
		 */
		$oria_v4_hub = is_post_type_archive( 'listing' ) && ! is_search()
			&& function_exists( '\Oria\Core\PracticesIndex\mode' ) && '' !== \Oria\Core\PracticesIndex\mode();

		// Area pages (taxonomy-area.php) wear the category layout too, plus
		// their own sections in v4-area.css.
		$oria_v4_area = is_tax( 'area' );

		$layouts = array(
			'oria-v4-home'     => array( 'v4-home.css', is_front_page() ),
			'oria-v4-category' => array( 'v4-category.css', is_tax( 'practice' ) || $oria_v4_hub || $oria_v4_area ),
			'oria-v4-listing'  => array( 'v4-listing.css', is_singular( 'listing' ) ),
			'oria-v4-area'     => array( 'v4-area.css', $oria_v4_area ),
		);
		/*
		 * The area page's List | Map switch: the map library on request, as
		 * the category pages have it (the parent's functions.php offers it
		 * to those only). Without this app.js hides the switch.
		 */
		if ( $oria_v4_area && wp_script_is( 'oria-app', 'enqueued' ) ) {
			$oria_puri = get_template_directory_uri();
			wp_add_inline_script(
				'oria-app',
				'window.ORIA_LEAFLET = window.ORIA_LEAFLET || ' . wp_json_encode(
					array(
						'css' => "{$oria_puri}/assets/vendor/leaflet/leaflet.css?ver=1.9.4",
						'js'  => "{$oria_puri}/assets/vendor/leaflet/leaflet.js?ver=1.9.4",
					)
				) . ';',
				'before'
			);
		}

		foreach ( $layouts as $handle => $layout ) {
			$file = "{$dir}/assets/css/{$layout[0]}";
			if ( $layout[1] && is_readable( $file ) ) {
				wp_enqueue_style( $handle, "{$uri}/assets/css/{$layout[0]}", array( 'oria-v4' ), (string) filemtime( $file ) );
			}
		}

		// The footer's folding columns, every page.
		wp_enqueue_script( 'oria-v4-nav', "{$uri}/assets/js/v4-nav.js", array(), (string) filemtime( "{$dir}/assets/js/v4-nav.js" ), array( 'strategy' => 'defer', 'in_footer' => true ) );

		// Category and listing page behaviour, when those pages ship a script.
		foreach ( array( 'oria-v4-category-js' => array( 'v4-category.js', is_tax( 'practice' ) || $oria_v4_hub || $oria_v4_area ), 'oria-v4-listing-js' => array( 'v4-listing.js', is_singular( 'listing' ) ), 'oria-v4-area-js' => array( 'v4-area.js', $oria_v4_area ) ) as $handle => $js ) {
			if ( $js[1] && is_readable( "{$dir}/assets/js/{$js[0]}" ) ) {
				wp_enqueue_script( $handle, "{$uri}/assets/js/{$js[0]}", array(), (string) filemtime( "{$dir}/assets/js/{$js[0]}" ), array( 'strategy' => 'defer', 'in_footer' => true ) );
			}
		}

		// The front page's feeling chips. The page works without it.
		if ( is_front_page() && is_readable( "{$dir}/assets/js/v4-home.js" ) ) {
			wp_enqueue_script( 'oria-v4-home', "{$uri}/assets/js/v4-home.js", array(), (string) filemtime( "{$dir}/assets/js/v4-home.js" ), array( 'strategy' => 'defer', 'in_footer' => true ) );
		}
	},
	20
);
