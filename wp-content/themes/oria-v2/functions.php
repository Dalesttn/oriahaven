<?php
/**
 * Oria Haven v2 -- design test (child of the Oria theme).
 *
 * Why a child theme rather than a copy: the parent carries the category
 * engine, Trends to Try, Ask Oria, schema and every SEO rule. A copy would
 * fork all of that the day it was made and miss every fix after. As a
 * child, this theme inherits everything and overrides only what the new
 * design changes -- a template file of the same name here wins over the
 * parent's, and v2.css loads last so its tokens win the cascade.
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
			'oria-v2',
			"{$uri}/assets/css/v2.css",
			array( 'oria-pages' ),
			(string) filemtime( "{$dir}/assets/css/v2.css" )
		);
	},
	20
);
