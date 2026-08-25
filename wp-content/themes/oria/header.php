<?php
/**
 * Site header: the floating glass pill nav from the prototype.
 * On the front page it overlays the hero (white type); everywhere else it
 * sticks with dark type — the same .site-head--solid switch the static
 * pages used.
 */

declare(strict_types=1);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
/*
 * Favicons. Browsers take the SVG. Google Search does not — it wants a
 * raster square whose side is a multiple of 48, and it renders that in the
 * results page, which is why the 96 and 48 are here rather than relying on
 * the vector alone. apple-touch-icon covers an iOS home screen, where the
 * tile is drawn full-bleed and iOS applies its own rounding.
 */
$oria_img = esc_url( get_template_directory_uri() . '/assets/img' );
?>
<link rel="icon" href="<?php echo $oria_img; ?>/favicon.svg" type="image/svg+xml">
<link rel="icon" href="<?php echo $oria_img; ?>/favicon-96.png" sizes="96x96" type="image/png">
<link rel="icon" href="<?php echo $oria_img; ?>/favicon-48.png" sizes="48x48" type="image/png">
<link rel="apple-touch-icon" href="<?php echo $oria_img; ?>/apple-touch-icon.png" sizes="180x180">
<meta name="theme-color" content="#0E3B38">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'oria' ); ?></a>

<?php $oria_has_hero = 'hero' === \Oria\Theme\page_first_layout(); ?>
<header class="site-head<?php echo $oria_has_hero ? '' : ' site-head--solid'; ?>">
	<nav class="nav" aria-label="<?php esc_attr_e( 'Main', 'oria' ); ?>">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo \Oria\Theme\mark( 'small', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<b>Oria</b><i>&thinsp;Haven</i>
		</a>

		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'nav__links',
				'fallback_cb'    => static function (): void {
					// Sensible default until a menu is assigned in the admin.
					echo '<ul class="nav__links">';
					printf( '<li><a class="nav__link" href="%s">%s</a></li>', esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ), esc_html__( 'Directory', 'oria' ) );
					/*
					 * Practices carries a submenu. The markup matches what WordPress's
					 * own walker emits for a nested menu item -- menu-item-has-children
					 * on the li, ul.sub-menu inside -- so the CSS and the JS enhancement
					 * work the same whether this fallback renders or an admin-built menu
					 * does. The parent stays a real link to /practices/; opening the
					 * panel is never the only way past it.
					 */
					echo '<li class="menu-item-has-children">';
					printf( '<a class="nav__link" href="%s">%s</a>', esc_url( home_url( '/practices/' ) ), esc_html__( 'Practices', 'oria' ) );
					echo '<ul class="sub-menu">';
					printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/practices/' ) ), esc_html__( 'All practices', 'oria' ) );
					printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/compare/' ) ), esc_html__( 'Compare experiences', 'oria' ) );
					printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/compare/build/' ) ), esc_html__( 'Build your session', 'oria' ) );
					echo '</ul></li>';
					printf( '<li><a class="nav__link" href="%s">%s</a></li>', esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ), esc_html__( 'Workshops/Events', 'oria' ) );
					printf( '<li><a class="nav__link" href="%s">%s</a></li>', esc_url( home_url( '/journal/' ) ), esc_html__( 'Journal', 'oria' ) );
					printf( '<li><a class="nav__link" href="%s">%s</a></li>', esc_url( home_url( '/about/' ) ), esc_html__( 'About', 'oria' ) );
					echo '</ul>';
				},
			)
		);
		?>

		<div class="nav__actions">
			<?php // Search anywhere, not just the home hero. ?>
			<span class="navsearch nav__hide">
				<label class="screen-reader-text" for="navSearch"><?php esc_html_e( 'Search practices', 'oria' ); ?></label>
				<svg class="navsearch__icon" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="8" r="5.5"/><path d="M12.2 12.2 16 16"/></svg>
				<input id="navSearch" type="text" autocomplete="off"
					placeholder="<?php esc_attr_e( 'Search…', 'oria' ); ?>"
					data-oria-search role="combobox" aria-autocomplete="list" aria-expanded="false"
					aria-controls="navSearchList">
				<span class="osearch osearch--nav" id="navSearchList" data-oria-search-panel hidden></span>
			</span>
			<a class="nav__link nav__hide" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'For practitioners', 'oria' ); ?></a>
			<a class="btn btn--dark nav__hide" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'List your practice', 'oria' ); ?><span class="btn__dot"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 11 3M5 3h6v6"/></svg></span></a>
			<button class="nav__toggle" data-drawer-open aria-label="<?php esc_attr_e( 'Open menu', 'oria' ); ?>" aria-controls="drawer">
				<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M2 4.5h12M2 11.5h12"/></svg>
			</button>
		</div>
	</nav>
</header>

<div class="drawer" id="drawer" hidden>
	<div class="drawer__top">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo \Oria\Theme\mark( 'small', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<b>Oria</b><i>&thinsp;Haven</i>
		</a>
		<button class="iconbtn iconbtn--light" data-drawer-close aria-label="<?php esc_attr_e( 'Close menu', 'oria' ); ?>">
			<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 3l8 8M11 3l-8 8"/></svg>
		</button>
	</div>
	<?php
	wp_nav_menu(
		array(
			'theme_location' => 'primary',
			'container'      => 'div',
			'container_class' => 'drawer__links',
			'fallback_cb'    => static function (): void {
				echo '<div class="drawer__links">';
				printf( '<a href="%s">%s</a>', esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ), esc_html__( 'Directory', 'oria' ) );
				printf( '<a href="%s">%s</a>', esc_url( home_url( '/practices/' ) ), esc_html__( 'Practices', 'oria' ) );
				// On a phone the child is simply shown, indented, under its parent:
				// an accordion hiding a single item is a tap that buys nothing.
				printf( '<a class="drawer__sub" href="%s">%s</a>', esc_url( home_url( '/compare/' ) ), esc_html__( 'Compare experiences', 'oria' ) );
				printf( '<a class="drawer__sub" href="%s">%s</a>', esc_url( home_url( '/compare/build/' ) ), esc_html__( 'Build your session', 'oria' ) );
				printf( '<a href="%s">%s</a>', esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ), esc_html__( 'Workshops/Events', 'oria' ) );
				printf( '<a href="%s">%s</a>', esc_url( home_url( '/journal/' ) ), esc_html__( 'Journal', 'oria' ) );
				printf( '<a href="%s">%s</a>', esc_url( home_url( '/about/' ) ), esc_html__( 'About', 'oria' ) );
				echo '</div>';
			},
		)
	);
	?>
	<div class="drawer__foot">
		<a class="btn btn--light btn--block" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'List your practice', 'oria' ); ?><span class="btn__dot"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 11 3M5 3h6v6"/></svg></span></a>
	</div>
</div>

<?php if ( ! $oria_has_hero ) : ?>
<div class="ambient" aria-hidden="true"></div>
<?php endif; ?>

<main id="main">
