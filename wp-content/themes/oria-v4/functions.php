<?php
/**
 * Oria Haven v4 -- design test (child of the Oria theme).
 *
 * The v4 "Sunset" direction from the design canvas, applied to the real
 * site: colours and type only. Every template, the category engine,
 * Trends to Try, Ask Oria, schema and SEO come from the parent untouched,
 * so what you see is the live site in the new clothes.
 *
 * Nunito Sans loads from Google Fonts here for speed of iteration. If v4
 * is chosen, self-host it the way the parent's fonts.css does before it
 * goes anywhere near production.
 *
 * Lives on branch design/haven-v2 only. Activate it locally with
 * Appearance > Themes; switch back to "Oria" before checking out main,
 * where this folder does not exist.
 *
 * @package Oria
 */

declare(strict_types=1);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$dir = get_stylesheet_directory();
		$uri = get_stylesheet_directory_uri();
		wp_enqueue_style(
			'oria-v4-fonts',
			'https://fonts.googleapis.com/css2?family=Nunito+Sans:ital,opsz,wght@0,6..12,400..900;1,6..12,400..800&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'oria-v4',
			"{$uri}/assets/css/v4.css",
			array( 'oria-pages', 'oria-v4-fonts' ),
			(string) filemtime( "{$dir}/assets/css/v4.css" )
		);

		/*
		 * The three redesigned layouts each bring their own sheet, only on
		 * their own page: the front page, a category (practice) page and a
		 * listing. Their templates here override the parent's by name.
		 */
		$layouts = array(
			'oria-v4-home'     => array( 'v4-home.css', is_front_page() ),
			'oria-v4-category' => array( 'v4-category.css', is_tax( 'practice' ) ),
			'oria-v4-listing'  => array( 'v4-listing.css', is_singular( 'listing' ) ),
		);
		foreach ( $layouts as $handle => $layout ) {
			$file = "{$dir}/assets/css/{$layout[0]}";
			if ( $layout[1] && is_readable( $file ) ) {
				wp_enqueue_style( $handle, "{$uri}/assets/css/{$layout[0]}", array( 'oria-v4' ), (string) filemtime( $file ) );
			}
		}

		// The front page's feeling chips. The page works without it.
		if ( is_front_page() && is_readable( "{$dir}/assets/js/v4-home.js" ) ) {
			wp_enqueue_script( 'oria-v4-home', "{$uri}/assets/js/v4-home.js", array(), (string) filemtime( "{$dir}/assets/js/v4-home.js" ), array( 'strategy' => 'defer', 'in_footer' => true ) );
		}
	},
	20
);
