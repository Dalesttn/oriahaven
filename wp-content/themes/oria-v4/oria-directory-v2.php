<?php
/**
 * The Explore hub -- /explore/ and /explore/{city}/ -- in the category
 * page's "Wellness Horizon" layout (v4). Overrides the parent's
 * oria/oria-directory-v2.php, which the router finds by name
 * (Oria\Core\PracticesIndex\template()).
 *
 * Order: full-bleed city photograph (the owner's own: Perth's skyline,
 * Margaret River's Canal Rocks) -> Discovery Dock (feeling / category /
 * location / show N) -> results heading, filters -> one Featured card ->
 * six listings -> the Oria note -> more listings -> Local intelligence ->
 * load more -> About -> Trends line -> what is listed here -> by practice /
 * area / specialty -> journal -> FAQ.
 *
 * Same engine as before: #dirResults keeps the directory ("hub") mode of
 * app.js -- load more, "Members first", data-city lock. The dock's choices
 * are the engine's own [data-filter] inputs: cat= for categories and moods,
 * region= / suburb= for places, so counts, chips, "Clear all" and the URL
 * are app.js's. The map moved behind the List | Map switch (#catMapView),
 * which app.js starts on request here too. Same H1, crumbs, FAQ, ItemList.
 * Shares v4-category.css / v4-category.js with the category pages; the hub
 * moods live in assets/data/category-horizon.json ("hub").
 */

declare(strict_types=1);

$oria_cfg      = array();
$oria_cfg_file = get_stylesheet_directory() . '/assets/data/category-horizon.json';
if ( is_readable( $oria_cfg_file ) ) {
	$oria_cfg = json_decode( (string) file_get_contents( $oria_cfg_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file
	$oria_cfg = is_array( $oria_cfg ) ? $oria_cfg : array();
}

/*
 * Which city this page is, if any. /explore/ names none and shows the whole
 * corpus; /explore/perth/ names one and everything below follows it.
 */
$oria_dircity = null;
$oria_cname   = '';
$oria_cslug   = '';
if ( function_exists( '\Oria\Core\Cities\current' ) ) {
	$oria_cslug = (string) get_query_var( \Oria\Core\Cities\QUERY_VAR );
	if ( '' !== $oria_cslug && \Oria\Core\Cities\exists( $oria_cslug ) ) {
		$oria_dircity = (array) \Oria\Core\Cities\get( $oria_cslug );
		$oria_cname   = \Oria\Core\Cities\name( $oria_dircity );
	}
}
$oria_place = '' !== $oria_cname ? $oria_cname : __( 'Perth', 'oria' );

/*
 * The hero photograph: each city's own, chosen by the owner, with where to
 * hold the crop. A city with no picture of its own takes Perth's skyline.
 * One <img>, preloaded from <head>: the full-bleed background on a wide
 * screen, a rounded picture above the words on a phone (v4-category.css).
 */
$oria_hero_by_city = array(
	'margaret-river' => array( 'scene-margaret-river-coast.webp', '50% center' ), // the channel and footbridge
);
list( $oria_hero_file, $oria_hero_pos ) = $oria_hero_by_city[ $oria_cslug ] ?? array( 'scene-perth-skyline.webp', '58% center' ); // the towers
$oria_hz = array( 'src' => get_theme_file_uri( 'assets/img/' . $oria_hero_file ), 'w' => 1280, 'h' => 720, 'pos' => $oria_hero_pos );
$oria_hz_size = @getimagesize( get_theme_file_path( 'assets/img/' . $oria_hero_file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a size we cannot read keeps the 16:9 default
if ( is_array( $oria_hz_size ) && $oria_hz_size[0] > 0 ) {
	$oria_hz['w'] = (int) $oria_hz_size[0];
	$oria_hz['h'] = (int) $oria_hz_size[1];
}
add_action(
	'wp_head',
	static function () use ( $oria_hz ): void {
		printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( $oria_hz['src'] ) );
	},
	2
);

get_header();

$oria_mode = \Oria\Core\PracticesIndex\mode();

// Every listing this page is about.
$oria_all = get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
if ( $oria_dircity && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
	$oria_all = \Oria\Core\Cities\filter_ids( $oria_all, $oria_dircity );
}
$oria_n = count( $oria_all );
\Oria\Theme\prime_listing_terms( $oria_all );

/*
 * Every category with listings here, counted the way the category pages
 * count (descendants included), most populated first. The id sets are kept
 * so a mood can count the places its categories reach between them.
 */
$oria_cats     = array();
$oria_cat_ids  = array();
foreach ( \Oria\Core\PracticesIndex\practices() as $oria_t ) {
	$oria_cids = function_exists( '\Oria\Core\Intents\listings_in' ) ? \Oria\Core\Intents\listings_in( $oria_t ) : array();
	if ( $oria_dircity && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
		$oria_cids = \Oria\Core\Cities\filter_ids( $oria_cids, $oria_dircity );
	}
	if ( ! $oria_cids ) {
		continue;
	}
	$oria_cat_ids[ $oria_t->slug ] = array_map( 'intval', $oria_cids );
	$oria_cats[ $oria_t->slug ]    = array(
		'term'  => $oria_t,
		'count' => count( $oria_cids ),
		'url'   => '' !== $oria_mode ? \Oria\Core\PracticesIndex\category_url( $oria_t ) : (string) get_term_link( $oria_t ),
	);
}
uasort( $oria_cats, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] ?: strcasecmp( \Oria\Theme\tname( $a['term'] ), \Oria\Theme\tname( $b['term'] ) ) );
// The dock's "Category": the top-level ones.
$oria_top_cats = array_filter( $oria_cats, static fn( array $c ): bool => 0 === (int) $c['term']->parent );

// Moods: named groups of categories that have listings here.
$oria_moods = array();
foreach ( (array) ( $oria_cfg['hub']['moods'] ?? array() ) as $oria_m ) {
	$oria_items = array_values( array_unique( array_filter( array_map( 'strval', (array) ( $oria_m['cats'] ?? array() ) ), static fn( string $s ): bool => isset( $oria_cats[ $s ] ) ) ) );
	if ( ! $oria_items || empty( $oria_m['slug'] ) || empty( $oria_m['name'] ) ) {
		continue;
	}
	$oria_union = array();
	foreach ( $oria_items as $oria_it ) {
		$oria_union += array_flip( $oria_cat_ids[ $oria_it ] );
	}
	$oria_moods[] = array(
		'slug'  => sanitize_title( (string) $oria_m['slug'] ),
		'name'  => (string) $oria_m['name'],
		'line'  => (string) ( $oria_m['line'] ?? '' ),
		'items' => $oria_items,
		'n'     => count( $oria_union ),
	);
}

/*
 * One pass over the listings for the places: regions and suburbs with how
 * many listings each holds (the dock's Location, Local intelligence).
 */
$oria_reg_n  = array();
$oria_sub_n  = array();
$oria_suburbs = array();
$oria_claimed = 0;
foreach ( $oria_all as $oria_id ) {
	$oria_id       = (int) $oria_id;
	$oria_seen_reg = array();
	foreach ( \Oria\Theme\oria_terms_of( $oria_id, 'area' ) as $oria_at ) {
		if ( $oria_at->parent ) {
			$oria_suburbs[ $oria_at->slug ] = true;
		}
		if ( function_exists( '\Oria\Core\Taxonomies\is_city' ) && \Oria\Core\Taxonomies\is_city( $oria_at ) ) {
			continue;
		}
		if ( $oria_dircity && function_exists( '\Oria\Core\Cities\for_area' ) ) {
			$oria_ac = \Oria\Core\Cities\for_area( $oria_at );
			if ( is_array( $oria_ac ) && ( $oria_ac['slug'] ?? '' ) !== ( $oria_dircity['slug'] ?? '' ) ) {
				continue;
			}
		}
		$oria_rt = null;
		if ( \Oria\Core\Taxonomies\is_suburb( $oria_at ) ) {
			if ( ! isset( $oria_sub_n[ $oria_at->slug ] ) ) {
				$oria_sub_n[ $oria_at->slug ] = array( 'term' => $oria_at, 'n' => 0 );
			}
			++$oria_sub_n[ $oria_at->slug ]['n'];
			$oria_rt = \Oria\Core\Taxonomies\region_for( $oria_at );
		} elseif ( \Oria\Core\Taxonomies\is_region( $oria_at ) ) {
			$oria_rt = $oria_at;
		}
		if ( $oria_rt instanceof WP_Term && ! isset( $oria_seen_reg[ $oria_rt->slug ] ) ) {
			$oria_seen_reg[ $oria_rt->slug ] = true;
			if ( ! isset( $oria_reg_n[ $oria_rt->slug ] ) ) {
				$oria_reg_n[ $oria_rt->slug ] = array( 'term' => $oria_rt, 'n' => 0 );
			}
			++$oria_reg_n[ $oria_rt->slug ]['n'];
		}
	}
	if ( 'unclaimed' !== \Oria\Theme\claim_status( $oria_id ) ) {
		++$oria_claimed;
	}
}
uasort( $oria_reg_n, static fn( array $a, array $b ): int => $b['n'] <=> $a['n'] );
uasort( $oria_sub_n, static fn( array $a, array $b ): int => $b['n'] <=> $a['n'] ?: strcasecmp( $a['term']->name, $b['term']->name ) );
$oria_loc_regions = array_slice( $oria_reg_n, 0, 8, true );
$oria_loc_suburbs = array_slice( array_filter( $oria_sub_n, static fn( array $r ): bool => $r['n'] >= 3 ), 0, 8, true );
$oria_cities_opt  = function_exists( '\Oria\Core\Explore\city_options' ) ? (array) \Oria\Core\Explore\city_options() : array();
$oria_other_cities = array_filter( $oria_cities_opt, static fn( $c ): bool => is_array( $c ) && empty( $c['current'] ) && ! empty( $c['url'] ) );
$oria_has_loc     = (bool) ( $oria_loc_regions || $oria_loc_suburbs || $oria_other_cities );

// When the listings were last touched: the trust line's date.
$oria_latest_ids = $oria_all ? get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'post__in' => $oria_all, 'numberposts' => 1, 'orderby' => 'modified', 'order' => 'DESC', 'fields' => 'ids' ) ) : array();
$oria_updated    = $oria_latest_ids ? (string) get_post_modified_time( 'j F', false, (int) $oria_latest_ids[0], true ) : date_i18n( 'j F' );

// The site FAQ, the journal's latest, the city's read-up.
$oria_site_faq = function_exists( '\Oria\Core\Faq\site_faq' ) ? (array) \Oria\Core\Faq\site_faq() : array();
$oria_guides   = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'date', 'order' => 'DESC' ) );
$oria_readup   = ( $oria_dircity && function_exists( '\Oria\Core\Cities\read_up' ) ) ? \Oria\Core\Cities\read_up( $oria_dircity ) : array();

$oria_regions = \Oria\Core\Taxonomies\regions();
$oria_regions = is_wp_error( $oria_regions ) ? array() : $oria_regions;
// regions() spans every city: keep this page's.
if ( $oria_dircity && function_exists( '\Oria\Core\Cities\for_area' ) ) {
	$oria_regions = array_values(
		array_filter(
			$oria_regions,
			static function ( $oria_rt ) use ( $oria_dircity ): bool {
				$oria_rc = \Oria\Core\Cities\for_area( $oria_rt );
				return ! is_array( $oria_rc ) || ( $oria_rc['slug'] ?? '' ) === ( $oria_dircity['slug'] ?? '' );
			}
		)
	);
}

// The map's pins, behind the List | Map switch.
$oria_map = array();
foreach ( $oria_all as $oria_mid ) {
	$oria_mla = get_post_meta( (int) $oria_mid, 'geo_lat', true );
	$oria_mlo = get_post_meta( (int) $oria_mid, 'geo_lng', true );
	if ( ! is_numeric( $oria_mla ) || ! is_numeric( $oria_mlo ) || 0.0 === (float) $oria_mla ) {
		continue;
	}
	$oria_msub = '';
	foreach ( \Oria\Theme\oria_terms_of( (int) $oria_mid, 'area' ) as $oria_mt ) {
		if ( $oria_mt->parent ) { $oria_msub = $oria_mt->name; break; }
	}
	$oria_map[] = array(
		'n'  => wp_specialchars_decode( (string) get_post_field( 'post_title', $oria_mid, 'raw' ), ENT_QUOTES ),
		'u'  => (string) get_permalink( (int) $oria_mid ),
		'la' => (float) $oria_mla,
		'lo' => (float) $oria_mlo,
		's'  => $oria_msub,
		'i'  => function_exists( '\Oria\Theme\listing_image' ) ? \Oria\Theme\listing_image( (int) $oria_mid ) : '',
		'r'  => function_exists( '\Oria\Core\Places\rating_for' ) ? round( \Oria\Core\Places\rating_for( (int) $oria_mid, false )['rating'], 1 ) : 0,
		'o'  => function_exists( '\Oria\Core\Places\open_now' ) ? \Oria\Core\Places\open_now( (int) $oria_mid ) : null,
	);
}

/*
 * Featured: ONE paid placement at the top of the results, unmistakably
 * labelled, from this city's listings. The pick rotates daily (crc32 of the
 * date and the id) -- the same for everyone that day, so fair and page-cache
 * safe. The hub's engine does not take it out of the list itself;
 * v4-category.js hides its twin in the list while the card shows, and hides
 * the card the moment the visitor filters or re-sorts.
 */
$oria_featured = array();
foreach ( $oria_all as $oria_fid ) {
	if ( 'featured' === \Oria\Theme\display_status( (int) $oria_fid ) ) {
		$oria_featured[] = (int) $oria_fid;
	}
}
$oria_day = current_time( 'Y-m-d' );
usort( $oria_featured, static fn( int $a, int $b ): int => crc32( $oria_day . $a ) <=> crc32( $oria_day . $b ) );
$oria_featured = array_slice( $oria_featured, 0, 1 );

// Popular shortcuts: the four biggest categories, as links to their pages.
$oria_popular = array_slice( $oria_top_cats, 0, 4, true );

// The snapshot line: figures counted here.
$oria_snap   = array();
/* translators: %s: number of places */
$oria_snap[] = sprintf( _n( '%s place', '%s places', $oria_n, 'oria' ), number_format_i18n( $oria_n ) );
/* translators: %s: number of categories */
$oria_snap[] = sprintf( _n( '%s category', '%s categories', count( $oria_top_cats ), 'oria' ), number_format_i18n( count( $oria_top_cats ) ) );
if ( count( $oria_sub_n ) > 1 ) {
	/* translators: %s: number of suburbs */
	$oria_snap[] = sprintf( __( '%s suburbs', 'oria' ), number_format_i18n( count( $oria_sub_n ) ) );
}

$oria_qh = \Oria\Core\PracticesIndex\query_heading();

// A dock control, as on the category pages.
$oria_dock_ctl = static function ( string $panel, string $key, string $key_phone, string $val_key, string $val ): void {
	?>
	<button type="button" class="xc-dock__ctl xc-js" data-xc-open="<?php echo esc_attr( $panel ); ?>" aria-controls="<?php echo esc_attr( $panel ); ?>" aria-expanded="false" aria-haspopup="dialog">
		<span class="xc-dock__k"><span class="xc-wide"><?php echo esc_html( $key ); ?></span><span class="xc-narrow"><?php echo esc_html( $key_phone ); ?></span></span>
		<span class="xc-dock__v" data-xc-val="<?php echo esc_attr( $val_key ); ?>"><?php echo esc_html( $val ); ?></span>
	</button>
	<?php
};
// A category checkbox: the engine's own cat= filter.
$oria_cat_box = static function ( string $slug, array $c ): void {
	?>
	<label class="xc-check">
		<input type="checkbox" data-filter="cat" value="<?php echo esc_attr( $slug ); ?>">
		<span class="xc-check__label"><?php echo esc_html( \Oria\Theme\tname( $c['term'] ) ); ?></span>
		<span class="xc-check__n"><?php echo esc_html( number_format_i18n( $c['count'] ) ); ?></span>
	</label>
	<?php
};
// A "Show N places" button: closes a panel and goes to the results.
$oria_show = static function ( string $label ) use ( $oria_n ): void {
	?>
	<a class="btn xc-btn-primary" href="#results" data-xc-show><?php printf( esc_html( $label ), '<b data-xc-count>' . esc_html( number_format_i18n( $oria_n ) ) . '</b>' ); ?></a>
	<?php
};
/* translators: %s: city */
$oria_all_label = sprintf( __( 'All %s', 'oria' ), $oria_place );
?>
<noscript><style>.xc-js{display:none!important}</style></noscript>

<!-- 1. Hero: the city, full width, text on the left -->
<section class="xc-hz xc-hz--long xc-hub" id="decide" style="--xc-hz-pos:<?php echo esc_attr( $oria_hz['pos'] ); ?>">
	<div class="xc-hz__media">
		<img class="xc-hz__img" src="<?php echo esc_url( $oria_hz['src'] ); ?>" width="<?php echo (int) $oria_hz['w']; ?>" height="<?php echo (int) $oria_hz['h']; ?>" alt="" fetchpriority="high" decoding="async">
	</div>
	<div class="xc-hz__shade" aria-hidden="true"></div>
	<div class="wrap xc-hz__inner">
		<nav class="crumbs xc-hz__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span><span><?php esc_html_e( 'Explore', 'oria' ); ?></span>
		</nav>
		<div class="xc-hz__copy">
			<p class="micro xc-hz__eyebrow"><?php esc_html_e( 'Explore', 'oria' ); ?></p>
			<?php if ( '' !== $oria_qh ) : ?>
				<h1 class="xc-hz__title"><?php echo esc_html( $oria_qh ); ?></h1>
			<?php else : ?>
				<h1 class="xc-hz__title">
					<?php
					printf(
						/* translators: %s: live listing count. */
						esc_html__( 'Discover %s ways to look after yourself.', 'oria' ),
						'<b>' . esc_html( number_format_i18n( $oria_n ) ) . '</b>'
					);
					?>
				</h1>
			<?php endif; ?>
			<p class="xc-hz__line xc-hz__line--long">
				<?php
				printf(
					/* translators: 1: listing count, 2: category count, 3: suburb count, 4: city. */
					esc_html__( 'Oria Haven lists %1$s wellness practices across %4$s — %2$s categories, %3$s suburbs — every one checked by hand. Pick a practice below; suburb, price and style come after.', 'oria' ),
					esc_html( number_format_i18n( $oria_n ) ),
					esc_html( number_format_i18n( count( $oria_cats ) ) ),
					esc_html( number_format_i18n( count( $oria_sub_n ) ) ),
					esc_html( $oria_place )
				);
				?>
			</p>
			<p class="xc-hz__trust">
				<?php
				printf(
					/* translators: 1: number of places, 2: city, 3: day and month */
					esc_html( _n( '%1$s hand-checked place across %2$s · Updated %3$s', '%1$s hand-checked places across %2$s · Updated %3$s', $oria_n, 'oria' ) ),
					esc_html( number_format_i18n( $oria_n ) ),
					esc_html( $oria_place ),
					esc_html( $oria_updated )
				);
				?>
			</p>
			<div class="xc-hz__acts">
				<?php if ( $oria_moods ) : ?>
					<button type="button" class="btn xc-btn-primary xc-js" data-xc-open="xcWays" aria-controls="xcWays" aria-expanded="false" aria-haspopup="dialog" data-oria-event="category_ways_open"><?php esc_html_e( 'Find your kind of reset', 'oria' ); ?></button>
				<?php endif; ?>
				<a class="btn xc-btn-light" href="#results" data-xc-show>
					<?php
					/* translators: %s: number of places */
					printf( esc_html( _n( 'View all %s place', 'View all %s places', $oria_n, 'oria' ) ), esc_html( number_format_i18n( $oria_n ) ) );
					?>
				</a>
			</div>
		</div>
	</div>
</section>

<!-- 2. Discovery Dock -->
<div class="xc-dockwrap" id="xcDockWrap">
	<div class="wrap xc-dockwrap__inner">
		<div class="xc-dock" id="xcDock" role="search" aria-label="<?php esc_attr_e( 'Find a place', 'oria' ); ?>">
			<div class="xc-dock__controls">
				<?php
				if ( $oria_moods ) {
					$oria_dock_ctl( 'xcWays', __( 'Desired feeling', 'oria' ), __( 'How do you want to feel?', 'oria' ), 'mood', __( 'Any feeling', 'oria' ) );
				}
				if ( $oria_top_cats ) {
					$oria_dock_ctl( 'xcExp', __( 'Category', 'oria' ), __( 'Choose a category', 'oria' ), 'exp', __( 'All categories', 'oria' ) );
				}
				if ( $oria_has_loc ) {
					$oria_dock_ctl( 'xcLoc', __( 'Location', 'oria' ), __( 'Location', 'oria' ), 'loc', $oria_all_label );
				}
				?>
				<a class="btn xc-dock__go" href="#results" data-xc-show>
					<?php
					/* translators: %s: number of places (updated live) */
					printf( esc_html__( 'Show %s places', 'oria' ), '<b data-xc-count>' . esc_html( number_format_i18n( $oria_n ) ) . '</b>' );
					?>
				</a>
			</div>
			<div class="xc-dock__foot">
				<?php if ( $oria_popular ) : ?>
					<ul class="xc-dock__pop" aria-label="<?php esc_attr_e( 'Popular categories', 'oria' ); ?>">
						<?php foreach ( $oria_popular as $oria_pc ) : ?>
							<li>
								<a class="xc-pchip" href="<?php echo esc_url( $oria_pc['url'] ); ?>" data-oria-event="explore_category_click">
									<?php echo esc_html( \Oria\Theme\tname( $oria_pc['term'] ) ); ?> <span class="xc-pchip__n"><?php echo esc_html( number_format_i18n( $oria_pc['count'] ) ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
						<?php if ( count( $oria_top_cats ) > count( $oria_popular ) ) : ?>
							<li class="xc-js xc-more"><button type="button" class="xc-pchip xc-pchip--more" data-xc-open="xcExp" aria-controls="xcExp" aria-expanded="false" aria-haspopup="dialog"><?php esc_html_e( 'More', 'oria' ); ?></button></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
				<p class="xc-dock__snap"><?php echo esc_html( implode( ' · ', $oria_snap ) ); ?></p>
			</div>
		</div>

		<?php if ( $oria_moods ) : ?>
			<div class="xc-pop xc-pop--wide" id="xcWays" role="dialog" aria-labelledby="xcWaysTitle" hidden>
				<div class="xc-pop__head">
					<h2 class="xc-pop__title" id="xcWaysTitle"><?php esc_html_e( 'What would feel good right now?', 'oria' ); ?></h2>
					<button type="button" class="xc-pop__x" data-xc-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
				</div>
				<div class="xc-pop__body">
					<div class="xc-moods" role="group" aria-label="<?php esc_attr_e( 'Moods', 'oria' ); ?>">
						<?php foreach ( $oria_moods as $oria_m ) : ?>
							<button type="button" class="xc-mood" aria-pressed="false" data-xc-mood="<?php echo esc_attr( $oria_m['slug'] ); ?>" data-xc-mood-name="<?php echo esc_attr( $oria_m['name'] ); ?>" data-kind="cat" data-items="<?php echo esc_attr( implode( ',', $oria_m['items'] ) ); ?>">
								<span class="xc-mood__name"><?php echo esc_html( $oria_m['name'] ); ?></span>
								<?php if ( '' !== $oria_m['line'] ) : ?>
									<span class="xc-mood__line"><?php echo esc_html( $oria_m['line'] ); ?></span>
								<?php endif; ?>
								<span class="xc-mood__n">
									<?php
									/* translators: %s: number of places */
									printf( esc_html( _n( '%s place', '%s places', $oria_m['n'], 'oria' ) ), esc_html( number_format_i18n( $oria_m['n'] ) ) );
									?>
								</span>
							</button>
						<?php endforeach; ?>
					</div>
					<?php foreach ( $oria_moods as $oria_m ) : ?>
						<div class="xc-mood__detail" data-xc-mood-detail="<?php echo esc_attr( $oria_m['slug'] ); ?>" hidden>
							<p class="xc-mood__hint">
								<?php
								/* translators: %s: mood name */
								printf( esc_html__( 'In “%s” — untick anything you would rather skip:', 'oria' ), esc_html( $oria_m['name'] ) );
								?>
							</p>
							<div class="xc-checks">
								<?php foreach ( $oria_m['items'] as $oria_it ) { $oria_cat_box( $oria_it, $oria_cats[ $oria_it ] ); } ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="xc-pop__foot">
					<button type="button" class="xc-pop__clear" data-xc-clear><?php esc_html_e( 'Clear', 'oria' ); ?></button>
					<?php /* translators: %s: number of places (updated live) */ $oria_show( __( 'Show %s matching places', 'oria' ) ); ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $oria_top_cats ) : ?>
			<div class="xc-pop" id="xcExp" role="dialog" aria-labelledby="xcExpTitle" hidden>
				<div class="xc-pop__head">
					<h2 class="xc-pop__title" id="xcExpTitle"><?php esc_html_e( 'Choose a category', 'oria' ); ?></h2>
					<button type="button" class="xc-pop__x" data-xc-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
				</div>
				<div class="xc-pop__body">
					<p class="xc-mood__hint"><?php esc_html_e( 'Pick one or several — each number is how many places here offer it.', 'oria' ); ?></p>
					<div class="xc-checks" data-xc-exp-list>
						<?php foreach ( $oria_top_cats as $oria_cs => $oria_cc ) { $oria_cat_box( (string) $oria_cs, $oria_cc ); } ?>
					</div>
				</div>
				<div class="xc-pop__foot">
					<button type="button" class="xc-pop__clear" data-xc-clear><?php esc_html_e( 'Clear', 'oria' ); ?></button>
					<?php /* translators: %s: number of places (updated live) */ $oria_show( __( 'Show %s places', 'oria' ) ); ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $oria_has_loc ) : ?>
			<div class="xc-pop" id="xcLoc" role="dialog" aria-labelledby="xcLocTitle" hidden>
				<div class="xc-pop__head">
					<h2 class="xc-pop__title" id="xcLocTitle"><?php esc_html_e( 'Where?', 'oria' ); ?></h2>
					<button type="button" class="xc-pop__x" data-xc-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
				</div>
				<div class="xc-pop__body">
					<?php if ( $oria_loc_regions ) : ?>
						<p class="xc-pop__sub"><?php esc_html_e( 'Areas', 'oria' ); ?></p>
						<div class="xc-checks">
							<?php foreach ( $oria_loc_regions as $oria_rs => $oria_rr ) : ?>
								<label class="xc-check">
									<input type="checkbox" data-filter="region" value="<?php echo esc_attr( (string) $oria_rs ); ?>">
									<span class="xc-check__label"><?php echo esc_html( \Oria\Theme\tname( $oria_rr['term'] ) ); ?></span>
									<span class="xc-check__n"><?php echo esc_html( number_format_i18n( $oria_rr['n'] ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $oria_loc_suburbs ) : ?>
						<p class="xc-pop__sub"><?php esc_html_e( 'Suburbs with the most places', 'oria' ); ?></p>
						<div class="xc-checks">
							<?php foreach ( $oria_loc_suburbs as $oria_sr ) : ?>
								<label class="xc-check">
									<input type="checkbox" data-filter="suburb" value="<?php echo esc_attr( \Oria\Theme\tname( $oria_sr['term'] ) ); ?>">
									<span class="xc-check__label"><?php echo esc_html( \Oria\Theme\tname( $oria_sr['term'] ) ); ?></span>
									<span class="xc-check__n"><?php echo esc_html( number_format_i18n( $oria_sr['n'] ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $oria_other_cities ) : ?>
						<p class="xc-pop__sub"><?php esc_html_e( 'Another region', 'oria' ); ?></p>
						<ul class="xc-ways xc-ways--cities">
							<?php foreach ( $oria_other_cities as $oria_oc ) : ?>
								<li><a href="<?php echo esc_url( (string) $oria_oc['url'] ); ?>"><span><?php echo esc_html( (string) $oria_oc['name'] ); ?></span><span class="xc-check__n"><?php echo esc_html( number_format_i18n( (int) ( $oria_oc['count'] ?? 0 ) ) ); ?></span></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<div class="xc-pop__foot">
					<button type="button" class="xc-pop__clear" data-xc-clear><?php esc_html_e( 'Clear', 'oria' ); ?></button>
					<?php /* translators: %s: number of places (updated live) */ $oria_show( __( 'Show %s places', 'oria' ) ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="xc-ribbon xc-js" id="xcRibbon" role="region" aria-label="<?php esc_attr_e( 'Refine these places', 'oria' ); ?>">
	<div class="wrap xc-ribbon__row">
		<span class="xc-ribbon__cat">
			<?php
			/* translators: %s: city */
			printf( esc_html__( 'Explore %s', 'oria' ), esc_html( $oria_place ) );
			?>
		</span>
		<?php if ( $oria_moods || $oria_top_cats ) : ?>
			<button type="button" class="xc-ribbon__btn" data-xc-open="<?php echo $oria_moods ? 'xcWays' : 'xcExp'; ?>" aria-controls="<?php echo $oria_moods ? 'xcWays' : 'xcExp'; ?>" aria-expanded="false" aria-haspopup="dialog">
				<span data-xc-val="ribbon"><?php esc_html_e( 'All categories', 'oria' ); ?></span> <span aria-hidden="true">&#9662;</span>
			</button>
		<?php endif; ?>
		<?php if ( $oria_has_loc ) : ?>
			<button type="button" class="xc-ribbon__btn xc-ribbon__btn--loc" data-xc-open="xcLoc" aria-controls="xcLoc" aria-expanded="false" aria-haspopup="dialog">
				<span data-xc-val="loc"><?php echo esc_html( $oria_all_label ); ?></span> <span aria-hidden="true">&#9662;</span>
			</button>
		<?php endif; ?>
		<button type="button" class="xc-ribbon__btn" data-xc-filters aria-controls="xcFilterPanel"><?php esc_html_e( 'Filters', 'oria' ); ?><span data-xc-fcount></span></button>
		<span class="xc-ribbon__count" aria-hidden="true"><b data-xc-count><?php echo esc_html( number_format_i18n( $oria_n ) ); ?></b> <?php esc_html_e( 'places', 'oria' ); ?></span>
		<?php if ( $oria_map ) : ?>
			<button type="button" class="xc-ribbon__btn xc-ribbon__btn--map" data-xc-map><?php esc_html_e( 'Map', 'oria' ); ?></button>
		<?php endif; ?>
	</div>
</div>

<!-- 3. The places -->
<section class="wrap section section--top-flush floor xc-browse" id="browse">
	<div class="xc-reshead">
		<h2 class="h3 results__head" id="results">
			<?php
			/* translators: %s: city */
			printf( esc_html__( 'Every place in %s', 'oria' ), esc_html( $oria_place ) );
			?>
		</h2>
		<p class="dir__count" id="dirCount" role="status" aria-live="polite"></p>
	</div>
	<?php get_template_part( 'template-parts/directory', 'toolbar', array( 'term' => null, 'ids' => $oria_all ) ); ?>
	<?php if ( $oria_map ) : ?>
		<div class="dirbar">
			<div class="viewswitch" role="group" aria-label="<?php esc_attr_e( 'Show results as', 'oria' ); ?>">
				<button type="button" class="viewswitch__btn" data-view="list" aria-pressed="true"><?php esc_html_e( 'List', 'oria' ); ?></button>
				<button type="button" class="viewswitch__btn" data-view="map" aria-pressed="false"><?php esc_html_e( 'Map', 'oria' ); ?></button>
			</div>
		</div>
	<?php endif; ?>
	<div class="chips" id="dirChips"></div>

	<?php if ( $oria_featured ) : ?>
		<section class="featcat xc-feat" id="featBand" aria-labelledby="featBandHead"
			data-ids="<?php echo esc_attr( implode( ',', array_map( static fn( int $i ): string => (string) get_post_field( 'post_name', $i ), $oria_featured ) ) ); ?>">
			<div class="xc-feat__head">
				<h2 class="xc-feat__label" id="featBandHead"><span class="badge badge--featured"><span class="badge-dot"></span><?php esc_html_e( 'Featured', 'oria' ); ?></span></h2>
				<p class="xc-feat__note"><?php esc_html_e( 'A paid placement from an Oria Haven member.', 'oria' ); ?></p>
				<a class="xc-feat__how" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'How featuring works', 'oria' ); ?></a>
			</div>
			<div class="dir__results featcat__grid">
				<?php
				global $post;
				foreach ( $oria_featured as $oria_fid ) {
					$post = get_post( $oria_fid ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					setup_postdata( $post );
					get_template_part( 'template-parts/listing', 'card' );
				}
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php endif; ?>

	<div class="xc-results">
		<?php // data-city locks the client rebuild to this city, as on the category pages. ?>
		<div class="dir__results dir__results--wide" id="dirResults"<?php echo $oria_dircity ? ' data-city="' . esc_attr( (string) $oria_dircity['slug'] ) . '"' : ''; ?>>
			<?php
			$oria_shown = array();
			while ( have_posts() ) :
				the_post();
				$oria_shown[] = (int) get_the_ID();
				get_template_part( 'template-parts/listing', 'card' );
			endwhile;
			if ( function_exists( '\Oria\Core\Schema\register_list' ) ) {
				\Oria\Core\Schema\register_list( $oria_shown );
			}
			?>
		</div>

		<?php if ( $oria_map ) : ?>
			<button type="button" class="mapfab" data-view="map" aria-pressed="false"><?php esc_html_e( 'Map', 'oria' ); ?></button>
			<div class="dirmap" id="catMapView" hidden>
				<button type="button" class="dirmap__close" data-view="list" aria-pressed="false"><?php esc_html_e( 'Back to list', 'oria' ); ?></button>
				<div class="catmap catmap--view" data-catmap role="img" aria-label="<?php printf( esc_attr__( 'Map of every listed practice across %s', 'oria' ), esc_attr( $oria_place ) ); ?>">
					<div class="catmap__tip" hidden></div>
				</div>
				<script type="application/json" data-catmap-data><?php echo wp_json_encode( $oria_map ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON in a data script tag ?></script>
			</div>
		<?php endif; ?>
	</div>

	<?php
	/*
	 * The Oria note: decision help after the sixth listing, pointing only at
	 * pages that exist -- the compare hub, the session builder, Ask Oria.
	 * The heading and labels are the site's own existing wording.
	 */
	?>
	<aside class="xc-note" id="xcNote" aria-labelledby="xcNoteTitle">
		<p class="micro xc-note__eyebrow"><?php esc_html_e( 'The Oria note', 'oria' ); ?></p>
		<h3 class="xc-note__title" id="xcNoteTitle"><?php esc_html_e( 'Not sure which kind of practice suits you?', 'oria' ); ?></h3>
		<p class="xc-note__acts">
			<a class="btn btn--sm btn--dark" href="<?php echo esc_url( home_url( '/compare/build/' ) ); ?>" data-oria-event="category_build_session_click"><?php esc_html_e( 'Build your session', 'oria' ); ?></a>
			<a class="xc-note__link" href="<?php echo esc_url( home_url( '/compare/' ) ); ?>" data-oria-event="explore_compare_click"><?php esc_html_e( 'Compare experiences', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
			<a class="xc-note__link" href="<?php echo esc_url( home_url( '/ask/' ) ); ?>" data-oria-event="category_ask_start"><?php esc_html_e( 'Ask Oria', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
		</p>
	</aside>

	<?php
	/*
	 * Local intelligence: only what the listings here say -- the area with
	 * the most places and the busiest suburbs (three or more each), each
	 * linked to its own page. Left out when the data is thin.
	 */
	$oria_local_subs = array_slice( array_filter( $oria_sub_n, static fn( array $r ): bool => $r['n'] >= 3 ), 0, 3, true );
	$oria_local_reg  = $oria_reg_n ? reset( $oria_reg_n ) : null;
	?>
	<?php if ( count( $oria_local_subs ) >= 2 ) : ?>
		<aside class="xc-local" id="xcLocal" aria-labelledby="xcLocalTitle">
			<h3 class="xc-local__title" id="xcLocalTitle">
				<?php
				/* translators: %s: city name */
				printf( esc_html__( 'Around %s', 'oria' ), esc_html( $oria_place ) );
				?>
			</h3>
			<p class="xc-local__copy">
				<?php
				if ( $oria_local_reg && $oria_local_reg['n'] > 0 ) {
					printf(
						/* translators: 1: linked area name, 2: count, 3: total */
						esc_html__( '%1$s has the most options — %2$s of these %3$s places.', 'oria' ),
						'<a href="' . esc_url( \Oria\Core\PracticesIndex\region_url( $oria_local_reg['term'] ) ) . '">' . esc_html( \Oria\Theme\tname( $oria_local_reg['term'] ) ) . '</a>',
						esc_html( number_format_i18n( $oria_local_reg['n'] ) ),
						esc_html( number_format_i18n( $oria_n ) )
					);
					echo ' ';
				}
				$oria_local_links = array();
				foreach ( $oria_local_subs as $oria_lr ) {
					$oria_local_links[] = '<a href="' . esc_url( (string) get_term_link( $oria_lr['term'] ) ) . '">' . esc_html( \Oria\Theme\tname( $oria_lr['term'] ) ) . '</a> (' . esc_html( number_format_i18n( $oria_lr['n'] ) ) . ')';
				}
				printf(
					/* translators: %s: list of linked suburbs with counts */
					esc_html__( 'The busiest suburbs: %s.', 'oria' ),
					implode( ', ', $oria_local_links ) // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
				);
				?>
			</p>
		</aside>
	<?php endif; ?>

	<details class="catabout xc-about">
		<summary>
			<?php
			/* translators: %s: city */
			printf( esc_html__( 'About these places in %s', 'oria' ), esc_html( $oria_place ) );
			?>
		</summary>
		<div class="catabout__body">
			<p>
				<?php
				printf(
					/* translators: 1: listing count, 2: category count, 3: suburb count, 4: city. */
					esc_html__( 'Oria Haven lists %1$s wellness practices across %4$s — %2$s categories, %3$s suburbs — every one checked by hand. Pick a practice below; suburb, price and style come after.', 'oria' ),
					esc_html( number_format_i18n( $oria_n ) ),
					esc_html( number_format_i18n( count( $oria_cats ) ) ),
					esc_html( number_format_i18n( count( $oria_sub_n ) ) ),
					esc_html( $oria_place )
				);
				?>
			</p>
			<p><?php esc_html_e( 'Most listings here were built from public information and are waiting for their owner to take them over — each one says so on its own page. We never take a cut of a booking.', 'oria' ); ?></p>
			<p class="hint"><?php esc_html_e( 'Members first means paid and claimed listings lead the list; switch the sort to see them by rating, price or name.', 'oria' ); ?></p>
		</div>
	</details>
	<div class="xc-browse-end" aria-hidden="true"></div>
</section>

<?php
/*
 * The way into Trends to Try from Explore: one quiet line, for somebody who
 * arrived with something from their feed rather than a category in mind.
 * Only once a trend is published.
 */
if ( function_exists( '\Oria\Core\Trends\published' ) && \Oria\Core\Trends\published() ) :
	?>
	<div class="wrap xc-trendline">
		<p class="cmpnudge">
			<a href="<?php echo esc_url( \Oria\Core\Trends\hub_url() ); ?>" data-oria-event="explore_trends_click">
				<span aria-hidden="true">&#10022;</span> <?php esc_html_e( 'Seen a wellness trend online? What it is, what to expect and where to try it in Perth', 'oria' ); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</p>
	</div>
<?php endif; ?>

<!-- 4. Read up: what is listed here, and every way in -->
<section class="wrap section floor xc-guide xc-mesh" id="read">
	<p class="micro floor__label"><?php esc_html_e( 'Read up', 'oria' ); ?></p>
	<?php // Only where the page is a city: /explore/ is every city at once. ?>
	<?php if ( $oria_dircity && $oria_readup ) : ?>
		<h2 class="h3" style="margin-bottom:1rem"><?php printf( esc_html__( 'What is listed in %s', 'oria' ), esc_html( $oria_place ) ); ?></h2>
		<div class="prose prose--intro" style="margin-bottom:2rem">
			<?php foreach ( $oria_readup as $oria_para ) : ?>
				<p><?php echo esc_html( $oria_para ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $oria_cats ) : ?>
		<h2 class="h4 xc-mesh__title"><?php esc_html_e( 'Start with a practice', 'oria' ); ?></h2>
		<div class="chips xc-mesh__chips">
			<?php foreach ( $oria_cats as $oria_c ) : ?>
				<a class="pill" href="<?php echo esc_url( $oria_c['url'] ); ?>" data-oria-event="explore_category_click"><?php echo esc_html( \Oria\Theme\tname( $oria_c['term'] ) . ' (' . (int) $oria_c['count'] . ')' ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $oria_regions ) : ?>
		<h2 class="h4 xc-mesh__title"><?php esc_html_e( 'Browse by area', 'oria' ); ?></h2>
		<div class="chips xc-mesh__chips">
			<?php foreach ( $oria_regions as $oria_r ) : ?>
				<a class="pill" href="<?php echo esc_url( \Oria\Core\PracticesIndex\region_url( $oria_r ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_r ) ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php
	/*
	 * Counted over this page's listings, and only the head of the list; the
	 * toolbar's popover still holds every specialty.
	 */
	$oria_spec_tally = array();
	foreach ( $oria_all as $oria_sid ) {
		foreach ( \Oria\Theme\oria_terms_of( (int) $oria_sid, 'specialty' ) as $oria_st ) {
			if ( ! isset( $oria_spec_tally[ $oria_st->term_id ] ) ) {
				$oria_spec_tally[ $oria_st->term_id ] = array( 'term' => $oria_st, 'n' => 0 );
			}
			++$oria_spec_tally[ $oria_st->term_id ]['n'];
		}
	}
	usort( $oria_spec_tally, static fn( array $a, array $b ): int => $b['n'] <=> $a['n'] ?: strcasecmp( \Oria\Theme\tname( $a['term'] ), \Oria\Theme\tname( $b['term'] ) ) );
	$oria_spec_terms = array_slice( $oria_spec_tally, 0, 24 );
	?>
	<?php if ( $oria_spec_terms ) : ?>
		<h2 class="h4 xc-mesh__title"><?php esc_html_e( 'Browse by specialty', 'oria' ); ?></h2>
		<div class="chips xc-mesh__chips">
			<?php foreach ( $oria_spec_terms as $oria_row ) : ?>
				<a class="pill" href="<?php echo esc_url( \Oria\Core\PracticesIndex\specialty_url( $oria_row['term'] ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_row['term'] ) . ' (' . (int) $oria_row['n'] . ')' ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php
get_template_part( 'template-parts/guides', 'floor', array( 'guides' => $oria_guides, 'heading' => __( 'Latest from the journal', 'oria' ) ) );

// The site FAQ, as the directory has always carried it.
if ( $oria_site_faq ) {
	get_template_part( 'template-parts/faq', null, array( 'faqs' => $oria_site_faq, 'heading' => __( 'Common questions', 'oria' ), 'id' => 'faq' ) );
}

get_footer();
