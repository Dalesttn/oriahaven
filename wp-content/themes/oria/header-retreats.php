<?php
/**
 * Header for Retreat Escapes (get_header( 'retreats' )): the Oria Haven
 * wordmark with a small descriptor, three links that scroll to the page's
 * own sections, and a plain way back to the main site. No utility bar, no
 * region switcher, no drawer -- a global collection should not be framed
 * as a Perth directory. On a phone the links sit behind a native
 * <details> disclosure, so nothing depends on hover or scripts.
 *
 * Same <head> as header.php so favicons, wp_head() and the skip link stay
 * identical; the main landmark opens here and footer.php closes it.
 *
 * @package Oria
 */

declare(strict_types=1);
$oria_img = esc_url( get_template_directory_uri() . '/assets/img' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="<?php echo $oria_img; ?>/favicon.svg" type="image/svg+xml">
<link rel="icon" href="<?php echo $oria_img; ?>/favicon-96.png" sizes="96x96" type="image/png">
<link rel="icon" href="<?php echo $oria_img; ?>/favicon-48.png" sizes="48x48" type="image/png">
<link rel="apple-touch-icon" href="<?php echo $oria_img; ?>/apple-touch-icon.png" sizes="180x180">
<meta name="theme-color" content="#243F33">
<?php wp_head(); ?>
<meta name="commission-factory-verification" content="53094dbbe7cc4ae5848446e8580ef2fb" />
</head>

<body <?php body_class( 'oria-retreats-page' ); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'oria' ); ?></a>

<header class="rh" id="top">
	<div class="rh__inner">
		<a class="rh__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Oria Haven home', 'oria' ); ?>">
			<?php echo \Oria\Theme\mark( 'small', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="rh__word"><b>Oria</b><i>&thinsp;Haven</i></span>
			<span class="rh__desc"><?php esc_html_e( 'Retreat Escapes', 'oria' ); ?></span>
		</a>
		<nav class="rh__nav" aria-label="<?php esc_attr_e( 'Retreat escapes', 'oria' ); ?>">
			<a href="#destinations"><?php esc_html_e( 'Destinations', 'oria' ); ?></a>
			<a href="#collection"><?php esc_html_e( 'The collection', 'oria' ); ?></a>
			<a href="#plan"><?php esc_html_e( 'Plan your escape', 'oria' ); ?></a>
			<a class="rh__back" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to Oria Haven', 'oria' ); ?></a>
		</nav>
		<details class="rh__menu">
			<summary aria-label="<?php esc_attr_e( 'Menu', 'oria' ); ?>"><span></span><span></span><span></span></summary>
			<nav aria-label="<?php esc_attr_e( 'Retreat escapes', 'oria' ); ?>">
				<a href="#destinations"><?php esc_html_e( 'Destinations', 'oria' ); ?></a>
				<a href="#collection"><?php esc_html_e( 'The collection', 'oria' ); ?></a>
				<a href="#plan"><?php esc_html_e( 'Plan your escape', 'oria' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to Oria Haven', 'oria' ); ?></a>
			</nav>
		</details>
	</div>
</header>

<main id="main">
