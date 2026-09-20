<?php
/**
 * My Oria's own head and shell.
 *
 * The public header is a floating glass nav, a utility bar and a region
 * strip -- three things a member signing in to see their own saved places
 * does not need, and which pushed the first useful line 323px down a
 * 5,897px page. This is the app's front door instead: a rail on a desk, a
 * bottom bar on a phone, and nothing that sells the site to somebody who
 * has already joined it.
 *
 * Deliberately not a second theme. The same tokens, the same Manrope, the
 * same green -- laid out for somebody with a task rather than somebody
 * being introduced.
 *
 * @package Oria
 */

declare(strict_types=1);

use Oria\Core\MyOria;

$oria_view    = function_exists( '\Oria\Core\MyOria\view' ) ? MyOria\view() : '';
$oria_private = (bool) ( MyOria\VIEWS[ $oria_view ] ?? true );
$oria_img     = esc_url( get_template_directory_uri() . '/assets/img' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="icon" href="<?php echo $oria_img; ?>/favicon.svg" type="image/svg+xml">
<link rel="icon" href="<?php echo $oria_img; ?>/favicon-96.png" sizes="96x96" type="image/png">
<link rel="apple-touch-icon" href="<?php echo $oria_img; ?>/apple-touch-icon.png" sizes="180x180">
<meta name="theme-color" content="#0E3B38">
<?php wp_head(); ?>
</head>
<body <?php body_class( $oria_private ? 'my-oria-app' : 'my-oria-auth' ); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'oria' ); ?></a>

<?php if ( ! $oria_private ) : ?>
	<?php
	/*
	 * Signing in. One column of chrome and nothing else: no navigation to
	 * wander off into, no footer, and no "list your practice" pitch at
	 * somebody who is three seconds from being a member.
	 */
	?>
	<div class="myauth">
		<a class="myauth__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo \Oria\Theme\mark( 'small', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed SVG. ?>
			<span class="myauth__word"><b>Oria</b><i>&thinsp;Haven</i></span>
		</a>
		<main class="myauth__main" id="main">
<?php else : ?>
	<div class="myapp">
		<?php get_template_part( 'template-parts/my/rail', null, array( 'view' => $oria_view ) ); ?>

		<div class="myapp__canvas">
			<?php get_template_part( 'template-parts/my/topbar', null, array( 'view' => $oria_view ) ); ?>
			<main class="myapp__main" id="main">
<?php endif; ?>
