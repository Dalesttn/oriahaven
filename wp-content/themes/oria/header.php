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

<?php
/*
 * The utility bar.
 *
 * Sits above everything, on every page including the hero pages — so the
 * hero begins below it rather than under it. That is a deliberate change to
 * the full-bleed homepage: the practitioner-facing actions are worth a
 * permanent strip, and half a header floating over an image while the other
 * half sits above it reads as a mistake rather than a choice.
 *
 * Sticky, and the nav sticks beneath it, which is why both offsets are
 * driven by --topbar-h rather than by numbers that could drift apart.
 *
 * Everything here moved out of the nav pill: search, For practitioners and
 * List your practice. The pill is now purely what a visitor browses with.
 */
?>
<div class="topbar">
	<div class="topbar__inner">
		<p class="topbar__tag"><?php esc_html_e( 'Explore. Feel. Flourish.', 'oria' ); ?></p>

		<div class="topbar__actions">
			<span class="navsearch navsearch--top">
				<label class="screen-reader-text" for="navSearch"><?php esc_html_e( 'Search practices', 'oria' ); ?></label>
				<svg class="navsearch__icon" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="8" r="5.5"/><path d="M12.2 12.2 16 16"/></svg>
				<input id="navSearch" type="text" autocomplete="off"
					placeholder="<?php esc_attr_e( 'Search…', 'oria' ); ?>"
					data-oria-search role="combobox" aria-autocomplete="list" aria-expanded="false"
					aria-controls="navSearchList">
				<span class="osearch osearch--nav" id="navSearchList" data-oria-search-panel hidden></span>
			</span>

			<a class="topbar__link" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'Claim your business', 'oria' ); ?></a>

			<a class="topbar__cta" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>">
				<?php esc_html_e( 'List your practice', 'oria' ); ?>
				<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11 11 3M5 3h6v6"/></svg>
			</a>
		</div>
	</div>
</div>

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
					printf( '<li><a class="nav__link" href="%s">%s</a></li>', esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ), esc_html__( 'Explore', 'oria' ) );
					/*
					 * Experiences carries a submenu. The markup matches what WordPress's
					 * own walker emits for a nested menu item -- menu-item-has-children
					 * on the li, ul.sub-menu inside -- so the CSS and the JS enhancement
					 * work the same whether this fallback renders or an admin-built menu
					 * does. The parent stays a real link to /practices/; opening the
					 * panel is never the only way past it.
					 */
					echo '<li class="menu-item-has-children">';
					printf( '<a class="nav__link" href="%s">%s</a>', esc_url( function_exists( '\Oria\Core\PracticesIndex\url' ) ? \Oria\Core\PracticesIndex\url() : home_url( '/practices/' ) ), esc_html__( 'Experiences', 'oria' ) );
					echo '<ul class="sub-menu">';
					/*
					 * Ask first: describing what you want is the shortest way in,
					 * and browsing is what the rest of this list is for.
					 *
					 * "Wellness map" replaced "All experiences", which pointed at
					 * PracticesIndex\url() -- the very same URL as its own parent
					 * above it. The dropdown was carrying a duplicate link, and the
					 * index is still one click away through that parent, so this
					 * cost an entry point that was never really there.
					 */
					printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/ask/' ) ), esc_html__( 'Ask Oria', 'oria' ) );
					printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/wellness-map/' ) ), esc_html__( 'Wellness map', 'oria' ) );
					printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/journeys/' ) ), esc_html__( 'Wellness Journeys', 'oria' ) );
					printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/compare/' ) ), esc_html__( 'Compare experiences', 'oria' ) );
					printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/compare/build/' ) ), esc_html__( 'Build your session', 'oria' ) );
					echo '</ul></li>';
					/*
					 * Discover: the written guides, as opposed to Explore, which is
					 * the directory. One child today. The parent points at that child
					 * rather than nowhere -- the rule above applies here too, a top
					 * level item is never a dead end -- and moves to a /discover/
					 * index the moment there is a second hub to index.
					 */
					echo '<li class="menu-item-has-children">';
					printf( '<a class="nav__link" href="%s">%s</a>', esc_url( home_url( '/singing-bowls/' ) ), esc_html__( 'Discover', 'oria' ) );
					echo '<ul class="sub-menu">';
					printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/singing-bowls/' ) ), esc_html__( 'Singing bowls', 'oria' ) );
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
			<?php
			/*
			 * Search, For practitioners and List your practice now live in the
			 * utility bar above. What remains is what a visitor uses while
			 * browsing.
			 *
			 * Saved count. Hidden until the device has something in it: a
			 * counter reading zero is clutter for the many people who have
			 * never pressed Save, and an invitation to nobody.
			 *
			 * Deliberately not nav__hide -- this is the one action that
			 * should survive down to a phone, where the shortlist is most
			 * likely to have been built.
			 */
			?>
			<a class="navsaved" href="<?php echo esc_url( home_url( '/saved/' ) ); ?>" data-saved-nav hidden>
				<span class="navsaved__heart" aria-hidden="true">&#9829;</span>
				<span class="navsaved__count" data-saved-nav-count>0</span>
			</a>
			<button class="nav__toggle" data-drawer-open aria-label="<?php esc_attr_e( 'Open menu', 'oria' ); ?>" aria-controls="drawer">
				<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M2 4.5h12M2 11.5h12"/></svg>
			</button>
		</div>
	</nav>
</header>

<?php
/*
 * The region bar sits under the nav wherever the nav occupies space.
 *
 * Not on a hero page. There .site-head is position:absolute so it floats
 * over the hero and takes no room in flow, which left the bar sitting at
 * the very top with the header painted on top of it and main pushed down
 * by its height -- a band of empty page above the hero.
 *
 * It prints nothing while there is only one city either.
 */
if ( ! $oria_has_hero ) {
	get_template_part( 'template-parts/city', 'switcher' );
}
?>

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
				printf( '<a href="%s">%s</a>', esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ), esc_html__( 'Explore', 'oria' ) );
				printf( '<a href="%s">%s</a>', esc_url( function_exists( '\Oria\Core\PracticesIndex\url' ) ? \Oria\Core\PracticesIndex\url() : home_url( '/practices/' ) ), esc_html__( 'Experiences', 'oria' ) );
				// On a phone the children are simply shown, indented, under their
				// parent: an accordion hiding a few items is a tap that buys nothing.
				printf( '<a class="drawer__sub" href="%s">%s</a>', esc_url( home_url( '/ask/' ) ), esc_html__( 'Ask Oria', 'oria' ) );
				printf( '<a class="drawer__sub" href="%s">%s</a>', esc_url( home_url( '/wellness-map/' ) ), esc_html__( 'Wellness map', 'oria' ) );
				printf( '<a class="drawer__sub" href="%s">%s</a>', esc_url( home_url( '/journeys/' ) ), esc_html__( 'Wellness Journeys', 'oria' ) );
				printf( '<a class="drawer__sub" href="%s">%s</a>', esc_url( home_url( '/compare/' ) ), esc_html__( 'Compare experiences', 'oria' ) );
				printf( '<a class="drawer__sub" href="%s">%s</a>', esc_url( home_url( '/compare/build/' ) ), esc_html__( 'Build your session', 'oria' ) );
				printf( '<a href="%s">%s</a>', esc_url( home_url( '/singing-bowls/' ) ), esc_html__( 'Discover', 'oria' ) );
				printf( '<a class="drawer__sub" href="%s">%s</a>', esc_url( home_url( '/singing-bowls/' ) ), esc_html__( 'Singing bowls', 'oria' ) );
				printf( '<a href="%s">%s</a>', esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ), esc_html__( 'Workshops/Events', 'oria' ) );
				printf( '<a href="%s">%s</a>', esc_url( home_url( '/journal/' ) ), esc_html__( 'Journal', 'oria' ) );
				printf( '<a href="%s">%s</a>', esc_url( home_url( '/about/' ) ), esc_html__( 'About', 'oria' ) );
				echo '</div>';
			},
		)
	);
	?>
	<div class="drawer__foot">
		<a class="drawer__saved" href="<?php echo esc_url( home_url( '/saved/' ) ); ?>" data-saved-nav hidden>
			<span aria-hidden="true">&#9829;</span>
			<span><?php esc_html_e( 'Saved practices', 'oria' ); ?></span>
			<span class="navsaved__count" data-saved-nav-count>0</span>
		</a>
		<?php
		/*
		 * The utility bar drops this below 860px and it was never in the
		 * drawer, so on a phone it had nowhere to be reached from at all —
		 * true before the bar existed, and worth fixing now that the bar has
		 * made the practitioner path explicit everywhere else.
		 */
		?>
		<a class="drawer__sub" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'Claim your business', 'oria' ); ?></a>
		<a class="btn btn--light btn--block" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'List your practice', 'oria' ); ?><span class="btn__dot"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 11 3M5 3h6v6"/></svg></span></a>
	</div>
</div>

<?php if ( ! $oria_has_hero ) : ?>
<div class="ambient" aria-hidden="true"></div>
<?php endif; ?>

<main id="main">
