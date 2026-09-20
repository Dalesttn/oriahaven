<?php
/**
 * An area page -- a suburb (/area/perth/freo/fremantle/), a region or a city
 * -- as "The Local Rhythm" (v4). Overrides the parent's taxonomy-area.php.
 *
 * A category page helps somebody choose an experience; this one helps them
 * experience a place. Order (brief 11):
 *
 *   1. the place's own photograph, full width, text on the left
 *   2. the Local Finder -- the category pages' Discovery Dock: feeling,
 *      practice, in person / online, Show N
 *   3. at a glance -- four figures, the method behind a disclosure
 *   4. the places -- one Featured card, six listings, The Local Rhythm,
 *      the rest, the map behind List | Map
 *   5. plan a local reset -- three example days built from listings here
 *   6. what's happening around here -- events in this area only
 *   7. browse by practice -- grouped by intention, final /explore/ URLs
 *   8. getting around -- only where the guide has been written
 *   9. nearby areas -- geographically close, each with its count
 *  10. FAQs
 *
 * Same engine as before: #dirResults keeps app.js's directory mode with the
 * area locked (data-region / data-suburb / data-city), so counts, chips,
 * load more and the map are app.js's; the dock's inputs are its own
 * [data-filter] inputs. Shares v4-category.css / v4-category.js with the
 * category pages and the hub; v4-area.css / v4-area.js add this page's own
 * sections. The figures and sentences come from inc/area-guide.php; the
 * hand-written layer from assets/data/area-guides.json.
 *
 * Indexing is unchanged: Oria\Core\AreaDepth still noindexes a thin area and
 * keeps it out of the sitemap. This template reads that, never overrides it.
 */

declare(strict_types=1);

use Oria\V4\Area;

$oria_term   = get_queried_object();
$oria_term   = $oria_term instanceof WP_Term ? $oria_term : null;
$oria_region = $oria_term ? \Oria\Core\Taxonomies\region_for( $oria_term ) : null;
$oria_is_sub = $oria_term && \Oria\Core\Taxonomies\is_suburb( $oria_term );
$oria_is_cty = $oria_term && \Oria\Core\Taxonomies\is_city( $oria_term );
$oria_city   = ( $oria_term && function_exists( '\Oria\Core\Cities\for_area' ) ) ? \Oria\Core\Cities\for_area( $oria_term ) : null;
$oria_cslug  = (string) ( $oria_city['slug'] ?? '' );
$oria_cname  = $oria_city && function_exists( '\Oria\Core\Cities\name' ) ? \Oria\Core\Cities\name( $oria_city ) : __( 'Perth', 'oria' );
$oria_place  = $oria_term ? \Oria\Theme\tname( $oria_term ) : '';
$oria_guide  = $oria_term ? Area\guide( $oria_term ) : array();
$oria_rows   = $oria_term ? Area\rows( $oria_term ) : array();
$oria_n      = count( $oria_rows );

$oria_hz = $oria_term ? Area\hero( $oria_term, $oria_region && $oria_region->term_id !== $oria_term->term_id ? $oria_region : null, $oria_city ) : null;
if ( $oria_hz ) {
	// Only the hero, preloaded: it is the page's largest paint.
	add_action(
		'wp_head',
		static function () use ( $oria_hz ): void {
			if ( '' !== $oria_hz['mid'] ) {
				printf( '<link rel="preload" as="image" href="%1$s" imagesrcset="%2$s 960w, %1$s 1920w" imagesizes="100vw" fetchpriority="high">' . "\n", esc_url( $oria_hz['wide'] ), esc_url( $oria_hz['mid'] ) );
			} else {
				printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( $oria_hz['wide'] ) );
			}
		},
		2
	);
}

get_header();

/* ------------------------------------------------------------- the data */

$oria_ids = array_values( array_filter( array_map( '\Oria\V4\Area\post_id', $oria_rows ) ) );
if ( $oria_ids ) {
	\Oria\Theme\prime_listing_terms( $oria_ids );
}

// Practices here, counted as the category pages count them; the top-level
// ones are the dock's Practice list and the "ways to feel better" figure.
$oria_pcounts = Area\practice_counts( $oria_rows );
$oria_top     = array();
foreach ( $oria_pcounts as $oria_ps => $oria_pn ) {
	$oria_pt = get_term_by( 'slug', (string) $oria_ps, 'practice' );
	if ( $oria_pt instanceof WP_Term && 0 === (int) $oria_pt->parent ) {
		$oria_top[ (string) $oria_ps ] = array( 'term' => $oria_pt, 'count' => (int) $oria_pn );
	}
}

// Moods: the hub's, cut to the categories found here.
$oria_cfg      = array();
$oria_cfg_file = get_stylesheet_directory() . '/assets/data/category-horizon.json';
if ( is_readable( $oria_cfg_file ) ) {
	$oria_cfg = json_decode( (string) file_get_contents( $oria_cfg_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file
	$oria_cfg = is_array( $oria_cfg ) ? $oria_cfg : array();
}
$oria_moods = array();
foreach ( (array) ( $oria_cfg['hub']['moods'] ?? array() ) as $oria_m ) {
	$oria_items = array_values( array_filter( array_map( 'strval', (array) ( $oria_m['cats'] ?? array() ) ), static fn( string $s ): bool => isset( $oria_top[ $s ] ) ) );
	if ( ! $oria_items || empty( $oria_m['slug'] ) ) {
		continue;
	}
	$oria_mn = count(
		array_filter(
			$oria_rows,
			static function ( array $r ) use ( $oria_items ): bool {
				foreach ( $oria_items as $s ) {
					if ( Area\in_practice( $r, $s ) ) {
						return true;
					}
				}
				return false;
			}
		)
	);
	$oria_moods[] = array(
		'slug'  => sanitize_title( (string) $oria_m['slug'] ),
		'name'  => (string) $oria_m['name'],
		'line'  => (string) ( $oria_m['line'] ?? '' ),
		'items' => $oria_items,
		'n'     => $oria_mn,
	);
}

$oria_strong  = Area\strongest( $oria_rows, $oria_cslug );
$oria_glance  = $oria_n >= 2 ? Area\glance( $oria_rows, count( $oria_top ), $oria_strong, $oria_cname ) : array();
$oria_rhythm  = Area\rhythm_points( $oria_rows, $oria_strong, $oria_place, $oria_cname, $oria_guide );
$oria_plans   = Area\plans( $oria_rows, $oria_guide );
$oria_events  = $oria_term ? Area\events( $oria_term ) : array();
$oria_groups  = $oria_term ? Area\practice_groups( $oria_term, $oria_rows, $oria_city ) : array();
$oria_nearby  = $oria_term ? Area\nearby( $oria_term, $oria_cslug, $oria_guide ) : array();
$oria_answer  = ( $oria_term && function_exists( '\Oria\Core\Answer\for_term' ) ) ? \Oria\Core\Answer\for_term( $oria_term ) : array( 'sentences' => array() );
$oria_online  = count( array_filter( $oria_rows, static fn( array $r ): bool => in_array( $r['format'] ?? '', array( 'online', 'both' ), true ) ) );

// When the listings here last changed: the trust line's date.
$oria_latest  = $oria_ids ? get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'post__in' => $oria_ids, 'numberposts' => 1, 'orderby' => 'modified', 'order' => 'DESC', 'fields' => 'ids' ) ) : array();
$oria_updated = $oria_latest ? (string) get_post_modified_time( 'j F', false, (int) $oria_latest[0], true ) : date_i18n( 'j F' );

// The one-line character: the guide's own, else what the listings say.
$oria_tagline = (string) ( $oria_guide['tagline'] ?? '' );
if ( '' === $oria_tagline && $oria_n >= Area\MIN_PATTERN ) {
	$oria_two = array_slice( array_keys( $oria_top ), 0, 2 );
	if ( 2 === count( $oria_two ) ) {
		/* translators: 1: area, 2 and 3: practice names */
		$oria_tagline = sprintf( __( 'Most of what %1$s offers is %2$s and %3$s — and there is more besides.', 'oria' ), $oria_place, Area\pname( $oria_two[0] ), Area\pname( $oria_two[1] ) );
	}
}

// Featured: one paid placement, rotating daily, as on the hub.
$oria_featured = array();
foreach ( $oria_ids as $oria_fid ) {
	if ( 'featured' === \Oria\Theme\display_status( (int) $oria_fid ) ) {
		$oria_featured[] = (int) $oria_fid;
	}
}
$oria_day = current_time( 'Y-m-d' );
usort( $oria_featured, static fn( int $a, int $b ): int => crc32( $oria_day . $a ) <=> crc32( $oria_day . $b ) );
$oria_featured = array_slice( $oria_featured, 0, 1 );

// The map's pins, behind the List | Map switch.
$oria_map = array();
foreach ( $oria_ids as $oria_mid ) {
	$oria_mla = get_post_meta( (int) $oria_mid, 'geo_lat', true );
	$oria_mlo = get_post_meta( (int) $oria_mid, 'geo_lng', true );
	if ( ! is_numeric( $oria_mla ) || ! is_numeric( $oria_mlo ) || 0.0 === (float) $oria_mla ) {
		continue;
	}
	$oria_map[] = array(
		'n'  => wp_specialchars_decode( (string) get_post_field( 'post_title', $oria_mid, 'raw' ), ENT_QUOTES ),
		'u'  => (string) get_permalink( (int) $oria_mid ),
		'la' => (float) $oria_mla,
		'lo' => (float) $oria_mlo,
		's'  => $oria_is_sub ? $oria_place : '',
		'i'  => function_exists( '\Oria\Theme\listing_image' ) ? \Oria\Theme\listing_image( (int) $oria_mid ) : '',
		'r'  => function_exists( '\Oria\Core\Places\rating_for' ) ? round( \Oria\Core\Places\rating_for( (int) $oria_mid, false )['rating'], 1 ) : 0,
		'o'  => function_exists( '\Oria\Core\Places\open_now' ) ? \Oria\Core\Places\open_now( (int) $oria_mid ) : null,
	);
}

$oria_has_dock = $oria_n >= 4;
$oria_region_name = ( $oria_region && $oria_term && $oria_region->term_id !== $oria_term->term_id ) ? \Oria\Theme\tname( $oria_region ) : '';

// A dock control, as on the category pages.
$oria_dock_ctl = static function ( string $panel, string $key, string $key_phone, string $val_key, string $val ): void {
	?>
	<button type="button" class="xc-dock__ctl xc-js" data-xc-open="<?php echo esc_attr( $panel ); ?>" aria-controls="<?php echo esc_attr( $panel ); ?>" aria-expanded="false" aria-haspopup="dialog">
		<span class="xc-dock__k"><span class="xc-wide"><?php echo esc_html( $key ); ?></span><span class="xc-narrow"><?php echo esc_html( $key_phone ); ?></span></span>
		<span class="xc-dock__v" data-xc-val="<?php echo esc_attr( $val_key ); ?>"><?php echo esc_html( $val ); ?></span>
	</button>
	<?php
};
$oria_cat_box = static function ( string $slug, array $c ): void {
	?>
	<label class="xc-check">
		<input type="checkbox" data-filter="cat" value="<?php echo esc_attr( $slug ); ?>">
		<span class="xc-check__label"><?php echo esc_html( \Oria\Theme\tname( $c['term'] ) ); ?></span>
		<span class="xc-check__n"><?php echo esc_html( number_format_i18n( $c['count'] ) ); ?></span>
	</label>
	<?php
};
$oria_show = static function ( string $label ) use ( $oria_n ): void {
	?>
	<a class="btn xc-btn-primary" href="#results" data-xc-show><?php printf( esc_html( $label ), '<b data-xc-count>' . esc_html( number_format_i18n( $oria_n ) ) . '</b>' ); ?></a>
	<?php
};
/* translators: %s: area */
$oria_all_label = sprintf( __( 'All of %s', 'oria' ), $oria_place );
?>
<noscript><style>.xc-js{display:none!important}</style></noscript>

<!-- 1. The place -->
<section class="xc-hz xc-hz--long xc-area<?php echo $oria_hz && $oria_hz['own'] ? ' xc-area--own' : ''; ?>" id="decide" style="--xc-hz-pos:<?php echo esc_attr( $oria_hz['pos'] ?? '50% 50%' ); ?>">
	<?php if ( $oria_hz ) : ?>
		<div class="xc-hz__media">
			<img class="xc-hz__img" src="<?php echo esc_url( $oria_hz['wide'] ); ?>"<?php echo '' !== $oria_hz['mid'] ? ' srcset="' . esc_url( $oria_hz['mid'] ) . ' 960w, ' . esc_url( $oria_hz['wide'] ) . ' 1920w" sizes="100vw"' : ''; ?> width="1920" height="1280" alt="<?php echo esc_attr( $oria_hz['alt'] ); ?>" fetchpriority="high" decoding="async">
		</div>
	<?php endif; ?>
	<div class="xc-hz__shade" aria-hidden="true"></div>
	<div class="wrap xc-hz__inner">
		<nav class="crumbs xc-hz__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/explore/' ) ); ?>"><?php esc_html_e( 'Explore', 'oria' ); ?></a>
			<?php if ( '' !== $oria_region_name ) : ?>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( (string) get_term_link( $oria_region ) ); ?>"><?php echo esc_html( $oria_region_name ); ?></a>
			<?php endif; ?>
			<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_place ); ?></span>
		</nav>
		<div class="xc-hz__copy">
			<p class="micro xc-hz__eyebrow">
				<?php
				echo esc_html(
					'' !== $oria_region_name
						/* translators: %s: region, e.g. Fremantle & South */
						? sprintf( __( 'Neighbourhood guide · %s', 'oria' ), $oria_region_name )
						: __( 'Neighbourhood guide', 'oria' )
				);
				?>
			</p>
			<h1 class="xc-hz__title">
				<?php
				/* translators: %s: area */
				printf( esc_html__( 'Wellness in %s', 'oria' ), esc_html( $oria_term ? Area\in_name( $oria_term ) : $oria_place ) );
				?>
			</h1>
			<?php if ( '' !== $oria_tagline ) : ?>
				<p class="xc-hz__line"><?php echo esc_html( $oria_tagline ); ?></p>
			<?php endif; ?>
			<?php if ( $oria_n ) : ?>
				<p class="xc-hz__trust">
					<?php
					$oria_trust = array(
						/* translators: %s: number of places */
						sprintf( _n( '%s hand-checked place', '%s hand-checked places', $oria_n, 'oria' ), number_format_i18n( $oria_n ) ),
					);
					if ( count( $oria_top ) > 1 ) {
						/* translators: %s: number of kinds of practice */
						$oria_trust[] = sprintf( __( '%s ways to feel better', 'oria' ), number_format_i18n( count( $oria_top ) ) );
					}
					/* translators: %s: day and month */
					$oria_trust[] = sprintf( __( 'Updated %s', 'oria' ), $oria_updated );
					echo esc_html( implode( ' · ', $oria_trust ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( $oria_n ) : ?>
				<div class="xc-hz__acts">
					<?php if ( $oria_has_dock && $oria_moods ) : ?>
						<button type="button" class="btn xc-btn-primary xc-js" data-xc-open="xcWays" aria-controls="xcWays" aria-expanded="false" aria-haspopup="dialog" data-oria-event="area_finder_open">
							<?php
							/* translators: %s: area */
							printf( esc_html__( 'Find something in %s', 'oria' ), esc_html( $oria_place ) );
							?>
						</button>
					<?php else : ?>
						<a class="btn xc-btn-primary" href="#results" data-xc-show>
							<?php
							/* translators: %s: number of places */
							printf( esc_html( _n( 'See the %s place', 'See all %s places', $oria_n, 'oria' ) ), esc_html( number_format_i18n( $oria_n ) ) );
							?>
						</a>
					<?php endif; ?>
					<?php if ( $oria_map ) : ?>
						<button type="button" class="btn xc-btn-light xc-js" data-xc-map data-xa-map data-oria-event="area_map_open"><?php esc_html_e( 'Explore the map', 'oria' ); ?></button>
					<?php endif; ?>
					<?php if ( $oria_plans ) : ?>
						<a class="xc-area__plan" href="#reset" data-oria-event="area_reset_click">
							<?php
							/* translators: %s: area */
							printf( esc_html__( 'Plan a %s reset', 'oria' ), esc_html( $oria_place ) );
							?>
							<span aria-hidden="true">&rarr;</span>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $oria_hz && '' !== $oria_hz['credit'] ) : ?>
		<p class="xc-area__credit"><?php echo esc_html( $oria_hz['credit'] ); ?></p>
	<?php endif; ?>
</section>

<?php if ( $oria_has_dock ) : ?>
<!-- 2. The Local Finder -->
<div class="xc-dockwrap" id="xcDockWrap">
	<div class="wrap xc-dockwrap__inner">
		<div class="xc-dock" id="xcDock" role="search" aria-label="<?php printf( esc_attr__( 'Find a place in %s', 'oria' ), esc_attr( $oria_place ) ); ?>">
			<div class="xc-dock__controls">
				<?php
				if ( $oria_moods ) {
					$oria_dock_ctl( 'xcWays', __( 'What do you need?', 'oria' ), __( 'What do you need?', 'oria' ), 'mood', __( 'Anything', 'oria' ) );
				}
				if ( count( $oria_top ) > 1 ) {
					$oria_dock_ctl( 'xcExp', __( 'Practice', 'oria' ), __( 'Practice', 'oria' ), 'exp', __( 'All practices', 'oria' ) );
				}
				$oria_dock_ctl( 'xcLoc', __( 'Where?', 'oria' ), __( 'Where?', 'oria' ), 'loc', $oria_all_label );
				?>
				<a class="btn xc-dock__go" href="#results" data-xc-show>
					<?php
					/* translators: %s: number of places (updated live) */
					printf( esc_html__( 'Show %s places', 'oria' ), '<b data-xc-count>' . esc_html( number_format_i18n( $oria_n ) ) . '</b>' );
					?>
				</a>
			</div>
		</div>

		<?php if ( $oria_moods ) : ?>
			<div class="xc-pop xc-pop--wide" id="xcWays" role="dialog" aria-labelledby="xcWaysTitle" hidden>
				<div class="xc-pop__head">
					<h2 class="xc-pop__title" id="xcWaysTitle">
						<?php
						/* translators: %s: area */
						printf( esc_html__( 'What would help, here in %s?', 'oria' ), esc_html( $oria_place ) );
						?>
					</h2>
					<button type="button" class="xc-pop__x" data-xc-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
				</div>
				<div class="xc-pop__body">
					<div class="xc-moods" role="group" aria-label="<?php esc_attr_e( 'What you need', 'oria' ); ?>">
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
								<?php foreach ( $oria_m['items'] as $oria_it ) { $oria_cat_box( $oria_it, $oria_top[ $oria_it ] ); } ?>
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

		<?php if ( count( $oria_top ) > 1 ) : ?>
			<div class="xc-pop" id="xcExp" role="dialog" aria-labelledby="xcExpTitle" hidden>
				<div class="xc-pop__head">
					<h2 class="xc-pop__title" id="xcExpTitle"><?php esc_html_e( 'Choose a practice', 'oria' ); ?></h2>
					<button type="button" class="xc-pop__x" data-xc-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
				</div>
				<div class="xc-pop__body">
					<p class="xc-mood__hint">
						<?php
						/* translators: %s: area */
						printf( esc_html__( 'Pick one or several — each number is how many places in %s offer it.', 'oria' ), esc_html( $oria_place ) );
						?>
					</p>
					<div class="xc-checks" data-xc-exp-list>
						<?php foreach ( $oria_top as $oria_cs => $oria_cc ) { $oria_cat_box( (string) $oria_cs, $oria_cc ); } ?>
					</div>
				</div>
				<div class="xc-pop__foot">
					<button type="button" class="xc-pop__clear" data-xc-clear><?php esc_html_e( 'Clear', 'oria' ); ?></button>
					<?php /* translators: %s: number of places (updated live) */ $oria_show( __( 'Show %s places', 'oria' ) ); ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="xc-pop" id="xcLoc" role="dialog" aria-labelledby="xcLocTitle" hidden>
			<div class="xc-pop__head">
				<h2 class="xc-pop__title" id="xcLocTitle"><?php esc_html_e( 'Where?', 'oria' ); ?></h2>
				<button type="button" class="xc-pop__x" data-xc-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
			</div>
			<div class="xc-pop__body">
				<p class="xc-pop__sub">
					<?php
					/* translators: %s: area */
					printf( esc_html__( 'In %s', 'oria' ), esc_html( $oria_place ) );
					?>
				</p>
				<div class="xc-checks">
					<label class="xc-check">
						<input type="checkbox" data-filter="format" value="in-person">
						<span class="xc-check__label"><?php esc_html_e( 'In person', 'oria' ); ?></span>
					</label>
					<?php if ( $oria_online ) : ?>
						<label class="xc-check">
							<input type="checkbox" data-filter="format" value="online">
							<span class="xc-check__label"><?php esc_html_e( 'Online sessions', 'oria' ); ?></span>
							<span class="xc-check__n"><?php echo esc_html( number_format_i18n( $oria_online ) ); ?></span>
						</label>
					<?php endif; ?>
				</div>
				<?php if ( $oria_map ) : ?>
					<p class="xc-area__popmap"><button type="button" class="xc-pchip" data-xc-map data-xa-map><?php esc_html_e( 'Show them on the map', 'oria' ); ?></button></p>
				<?php endif; ?>
				<?php if ( $oria_nearby ) : ?>
					<p class="xc-pop__sub"><?php esc_html_e( 'Or somewhere nearby', 'oria' ); ?></p>
					<ul class="xc-ways xc-ways--cities">
						<?php foreach ( $oria_nearby as $oria_nb ) : ?>
							<li><a href="<?php echo esc_url( (string) get_term_link( $oria_nb['term'] ) ); ?>"><span><?php echo esc_html( \Oria\Theme\tname( $oria_nb['term'] ) ); ?></span><span class="xc-check__n"><?php echo esc_html( number_format_i18n( $oria_nb['n'] ) ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<div class="xc-pop__foot">
				<button type="button" class="xc-pop__clear" data-xc-clear><?php esc_html_e( 'Clear', 'oria' ); ?></button>
				<?php /* translators: %s: number of places (updated live) */ $oria_show( __( 'Show %s places', 'oria' ) ); ?>
			</div>
		</div>
	</div>
</div>

<div class="xc-ribbon xc-js" id="xcRibbon" role="region" aria-label="<?php esc_attr_e( 'Refine these places', 'oria' ); ?>">
	<div class="wrap xc-ribbon__row">
		<span class="xc-ribbon__cat"><?php echo esc_html( $oria_place ); ?></span>
		<button type="button" class="xc-ribbon__btn" data-xc-open="<?php echo $oria_moods ? 'xcWays' : 'xcExp'; ?>" aria-controls="<?php echo $oria_moods ? 'xcWays' : 'xcExp'; ?>" aria-expanded="false" aria-haspopup="dialog">
			<span data-xc-val="ribbon"><?php esc_html_e( 'All practices', 'oria' ); ?></span> <span aria-hidden="true">&#9662;</span>
		</button>
		<button type="button" class="xc-ribbon__btn" data-xc-filters aria-controls="xcFilterPanel"><?php esc_html_e( 'Filters', 'oria' ); ?><span data-xc-fcount></span></button>
		<span class="xc-ribbon__count" aria-hidden="true"><b data-xc-count><?php echo esc_html( number_format_i18n( $oria_n ) ); ?></b> <?php esc_html_e( 'places', 'oria' ); ?></span>
		<?php if ( $oria_map ) : ?>
			<button type="button" class="xc-ribbon__btn xc-ribbon__btn--map" data-xc-map><?php esc_html_e( 'Map', 'oria' ); ?></button>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>

<?php if ( $oria_glance ) : ?>
<!-- 3. At a glance -->
<section class="wrap xa-glance" aria-labelledby="xaGlanceTitle">
	<h2 class="sr-only" id="xaGlanceTitle">
		<?php
		/* translators: %s: area */
		printf( esc_html__( '%s at a glance', 'oria' ), esc_html( $oria_place ) );
		?>
	</h2>
	<ul class="xa-glance__row">
		<?php foreach ( $oria_glance as $oria_g ) : ?>
			<li class="xa-glance__card<?php echo ! empty( $oria_g['word'] ) ? ' xa-glance__card--word' : ''; ?>">
				<span class="xa-glance__n"><?php echo esc_html( $oria_g['n'] ); ?></span>
				<span class="xa-glance__label"><?php echo esc_html( $oria_g['label'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<details class="xa-glance__about">
		<summary><?php esc_html_e( 'About these figures', 'oria' ); ?></summary>
		<div class="xa-glance__body">
			<?php if ( ! empty( $oria_answer['sentences'] ) ) : ?>
				<p><?php echo esc_html( implode( ' ', $oria_answer['sentences'] ) ); ?></p>
			<?php endif; ?>
			<p>
				<?php
				printf(
					/* translators: %s: date */
					esc_html__( 'Counted from the places Oria Haven lists here, last updated %s. A place counts once for each kind of practice it offers. Prices are the lowest each practice publishes for a session; many publish none, and every practice sets and changes its own.', 'oria' ),
					esc_html( $oria_updated )
				);
				?>
			</p>
		</div>
	</details>
</section>
<?php endif; ?>

<!-- 4. The places -->
<section class="wrap section section--top-flush floor xc-browse" id="browse">
	<div class="xc-reshead">
		<h2 class="h3 results__head" id="results">
			<?php
			/* translators: %s: area */
			printf( esc_html__( 'Places in %s', 'oria' ), esc_html( $oria_place ) );
			?>
		</h2>
		<p class="dir__count" id="dirCount" role="status" aria-live="polite"></p>
	</div>
	<?php
	if ( $oria_n ) {
		get_template_part( 'template-parts/directory', 'toolbar', array( 'term' => null, 'ids' => $oria_ids ) );
	}
	?>
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
		<?php
		/*
		 * Both facets locked, as the parent template does: a suburb page that
		 * handed the script only its region listed every practice in the
		 * region. A city term locks the city instead.
		 */
		?>
		<div class="dir__results dir__results--wide" id="dirResults" data-region="<?php echo esc_attr( ! $oria_is_cty && $oria_region ? $oria_region->slug : '' ); ?>"<?php echo $oria_is_cty ? ' data-city="' . esc_attr( (string) $oria_term->slug ) . '"' : ''; ?><?php echo $oria_is_sub ? ' data-suburb="' . esc_attr( $oria_place ) . '"' : ''; ?>>
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
				<div class="catmap catmap--view" data-catmap role="img" aria-label="<?php printf( esc_attr__( 'Map of the places listed in %s', 'oria' ), esc_attr( $oria_place ) ); ?>">
					<div class="catmap__tip" hidden></div>
				</div>
				<script type="application/json" data-catmap-data><?php echo wp_json_encode( $oria_map ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON in a data script tag ?></script>
			</div>
		<?php endif; ?>
	</div>

	<?php
	/*
	 * An area with nothing in it stays browseable (AreaDepth noindexes it):
	 * say so plainly and point at the suburbs nearby that do have places.
	 */
	if ( ! $oria_n && $oria_term && function_exists( '\Oria\Core\AreaDepth\siblings_with_listings' ) ) :
		$oria_sibs = \Oria\Core\AreaDepth\siblings_with_listings( $oria_term );
		?>
		<div class="notice" style="margin-top:.5rem">
			<p style="margin:0">
				<b><?php printf( esc_html__( 'No practices listed in %s yet.', 'oria' ), esc_html( $oria_place ) ); ?></b>
				<?php esc_html_e( 'The directory is still growing — this suburb will fill in as practices join.', 'oria' ); ?>
			</p>
		</div>
		<?php if ( $oria_sibs ) : ?>
			<h3 class="h4" style="margin-top:var(--s-5)"><?php esc_html_e( 'Nearby suburbs', 'oria' ); ?></h3>
			<ul class="chips" style="margin-top:.75rem">
				<?php foreach ( $oria_sibs as $oria_near ) : ?>
					<li><a class="chip" href="<?php echo esc_url( (string) get_term_link( $oria_near ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_near ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $oria_rhythm ) : ?>
		<?php
		/*
		 * The Local Rhythm: the page's own observation of the place, set
		 * after the sixth listing by v4-category.js (it is the page's
		 * #xcNote). Every line is counted from the listings, with its count.
		 */
		?>
		<aside class="xc-note xa-rhythm" id="xcNote" aria-labelledby="xaRhythmTitle">
			<p class="micro xc-note__eyebrow"><?php esc_html_e( 'The local rhythm', 'oria' ); ?></p>
			<h3 class="xc-note__title" id="xaRhythmTitle">
				<?php
				// "in the Northern Suburbs", "in Fremantle".
				$oria_in = preg_match( '/\bSuburbs$/', $oria_place ) ? 'the ' . $oria_place : $oria_place;
				/* translators: %s: area */
				printf( esc_html__( 'How wellness works in %s', 'oria' ), esc_html( $oria_in ) );
				?>
			</h3>
			<?php if ( ! empty( $oria_guide['rhythm_intro'] ) ) : ?>
				<p class="xa-rhythm__intro"><?php echo esc_html( (string) $oria_guide['rhythm_intro'] ); ?></p>
			<?php endif; ?>
			<?php
			// A small line icon per kind of figure; decoration only.
			$oria_rh_icons = array(
				'star'     => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>',
				'pin'      => '<path d="M12 21s-6.5-6.1-6.5-11A6.5 6.5 0 0 1 18.5 10c0 4.9-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
				'moon'     => '<path d="M19.5 14.5A7.5 7.5 0 0 1 9.5 4.5a7.5 7.5 0 1 0 10 10z"/>',
				'calendar' => '<rect x="4" y="5.5" width="16" height="14.5" rx="2.5"/><path d="M4 10h16M8.5 3.5v4M15.5 3.5v4"/>',
				'tag'      => '<path d="M3.5 12.2V4.5a1 1 0 0 1 1-1h7.7l8.3 8.3a1.4 1.4 0 0 1 0 2l-6.7 6.7a1.4 1.4 0 0 1-2 0z"/><circle cx="8" cy="8" r="1.5"/>',
				'screen'   => '<rect x="3.5" y="4.5" width="17" height="11.5" rx="2"/><path d="M8.5 20h7M12 16v4"/>',
			);
			?>
			<ul class="xa-rhythm__tiles">
				<?php foreach ( $oria_rhythm as $oria_pt ) : ?>
					<li class="xa-rtile xa-rtile--<?php echo esc_attr( $oria_pt['kind'] ); ?>">
						<svg class="xa-rtile__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?php echo $oria_rh_icons[ $oria_pt['kind'] ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup above ?></svg>
						<span class="xa-rtile__big"><?php echo esc_html( $oria_pt['big'] ); ?></span>
						<span class="xa-rtile__label"><?php echo esc_html( $oria_pt['label'] ); ?></span>
						<?php if ( null !== $oria_pt['frac'] ) : ?>
							<span class="xa-rtile__bar" aria-hidden="true"><i style="width:<?php echo esc_attr( (string) max( 4, min( 100, round( $oria_pt['frac'] * 100 ) ) ) ); ?>%"></i></span>
						<?php endif; ?>
						<?php if ( '' !== $oria_pt['note'] ) : ?>
							<span class="xa-rtile__note"><?php echo esc_html( $oria_pt['note'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="xa-rhythm__meta">
				<?php
				/* translators: %s: date */
				printf( esc_html__( 'From the listings as of %s.', 'oria' ), esc_html( $oria_updated ) );
				?>
			</p>
		</aside>
	<?php endif; ?>
	<div class="xc-browse-end" aria-hidden="true"></div>
</section>

<?php if ( $oria_plans ) : ?>
<!-- 5. Plan a local reset -->
<section class="wrap section xa-reset" id="reset" aria-labelledby="xaResetTitle">
	<div class="xa-head">
		<p class="micro xa-head__eyebrow"><?php esc_html_e( 'Plan a local reset', 'oria' ); ?></p>
		<h2 class="h2 xa-head__title" id="xaResetTitle">
			<?php
			/* translators: %s: area */
			printf( esc_html__( 'Three ways to spend a day in %s', 'oria' ), esc_html( $oria_place ) );
			?>
		</h2>
		<p class="xa-head__line"><?php esc_html_e( 'Editorial ideas built from places listed here — not advice, and not a booking. Check times and availability with each place. Swap any stop for another that fits.', 'oria' ); ?></p>
	</div>
	<div class="xa-plans">
		<?php foreach ( $oria_plans as $oria_pi => $oria_plan ) : ?>
			<article class="xa-plan">
				<h3 class="xa-plan__name"><?php echo esc_html( $oria_plan['name'] ); ?></h3>
				<p class="xa-plan__line"><?php echo esc_html( $oria_plan['line'] ); ?></p>
				<ol class="xa-plan__stops">
					<?php foreach ( $oria_plan['stops'] as $oria_si => $oria_stop ) : ?>
						<?php if ( 'walk' === $oria_stop['kind'] ) : ?>
							<li class="xa-stop xa-stop--walk">
								<span class="xa-stop__what"><?php echo esc_html( $oria_stop['what'] ); ?></span>
								<strong class="xa-stop__name"><?php echo esc_html( $oria_stop['label'] ); ?></strong>
								<?php if ( '' !== $oria_stop['line'] ) : ?>
									<span class="xa-stop__blurb"><?php echo esc_html( $oria_stop['line'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php else : ?>
							<?php $oria_c0 = $oria_stop['cands'][0]; ?>
							<li class="xa-stop" data-xa-stop='<?php echo esc_attr( (string) wp_json_encode( $oria_stop['cands'] ) ); ?>' data-xa-i="0">
								<span class="xa-stop__what"><?php echo esc_html( $oria_stop['what'] ); ?></span>
								<a class="xa-stop__name" href="<?php echo esc_url( $oria_c0['url'] ); ?>" data-oria-event="area_reset_stop" data-xa-name><?php echo esc_html( $oria_c0['name'] ); ?></a>
								<span class="xa-stop__cat" data-xa-cat><?php echo esc_html( $oria_c0['cat'] ); ?></span>
								<?php if ( '' !== $oria_c0['blurb'] ) : ?>
									<span class="xa-stop__blurb" data-xa-blurb><?php echo esc_html( $oria_c0['blurb'] ); ?></span>
								<?php endif; ?>
								<?php if ( count( $oria_stop['cands'] ) > 1 ) : ?>
									<button type="button" class="xa-stop__swap xc-js" data-xa-swap aria-label="<?php echo esc_attr( sprintf( /* translators: %s: stop label */ __( 'Swap “%s” for another place', 'oria' ), $oria_stop['what'] ) ); ?>"><?php esc_html_e( 'Swap', 'oria' ); ?></button>
								<?php endif; ?>
							</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ol>
			</article>
		<?php endforeach; ?>
	</div>
	<p class="xa-sr" aria-live="polite" data-xa-live></p>
</section>
<?php endif; ?>

<?php if ( $oria_events ) : ?>
<!-- 6. Happening around here -->
<section class="wrap section xa-events" id="events" aria-labelledby="xaEventsTitle">
	<div class="xa-head">
		<p class="micro xa-head__eyebrow"><?php esc_html_e( "What's on", 'oria' ); ?></p>
		<h2 class="h3 xa-head__title" id="xaEventsTitle">
			<?php
			/* translators: %s: area */
			printf( esc_html__( 'Happening around %s', 'oria' ), esc_html( $oria_place ) );
			?>
		</h2>
	</div>
	<ul class="xa-events__row">
		<?php foreach ( $oria_events as $oria_ev ) : ?>
			<li>
				<a class="xa-ev" href="<?php echo esc_url( (string) get_permalink( $oria_ev['id'] ) ); ?>" data-oria-event="area_event">
					<time class="xa-ev__when" datetime="<?php echo esc_attr( gmdate( 'Y-m-d\TH:i', $oria_ev['ts'] ) ); ?>">
						<?php
						echo esc_html(
							$oria_ev['now']
								? __( 'On now', 'oria' )
								: ( '00:00' === gmdate( 'H:i', $oria_ev['ts'] ) ? gmdate( 'D j M', $oria_ev['ts'] ) : gmdate( 'D j M, g.ia', $oria_ev['ts'] ) )
						);
						?>
					</time>
					<strong class="xa-ev__name"><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev['id'] ) ); ?></strong>
					<span class="xa-ev__meta">
						<?php
						$oria_evm = array_filter( array( $oria_ev['suburb'], $oria_ev['cat'], '' !== $oria_ev['src'] ? sprintf( /* translators: %s: source */ __( 'via %s', 'oria' ), $oria_ev['src'] ) : '' ) );
						echo esc_html( implode( ' · ', $oria_evm ) );
						?>
					</span>
					<span class="xa-ev__go"><?php esc_html_e( 'View event', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
<?php endif; ?>

<?php if ( $oria_groups ) : ?>
<!-- 7. Browse by practice -->
<section class="wrap section xa-practices" id="practices" aria-labelledby="xaPracTitle">
	<div class="xa-paper">
	<span class="xa-paper__tape" aria-hidden="true"></span>
	<div class="xa-head">
		<p class="micro xa-head__eyebrow"><?php esc_html_e( 'Browse by practice', 'oria' ); ?></p>
		<h2 class="h3 xa-head__title" id="xaPracTitle">
			<?php
			/* translators: %s: area */
			printf( esc_html__( 'Everything on offer in %s', 'oria' ), esc_html( $oria_place ) );
			?>
		</h2>
	</div>
	<div class="xa-groups">
		<?php foreach ( $oria_groups as $oria_gr ) : ?>
			<div class="xa-group">
				<h3 class="xa-group__name"><?php echo esc_html( $oria_gr['name'] ); ?></h3>
				<?php if ( '' !== $oria_gr['line'] ) : ?>
					<p class="xa-group__line"><?php echo esc_html( $oria_gr['line'] ); ?></p>
				<?php endif; ?>
				<ul class="xa-group__links">
					<?php foreach ( $oria_gr['links'] as $oria_l ) : ?>
						<li class="<?php echo $oria_l['sub'] ? 'is-sub' : ''; ?>">
							<a href="<?php echo esc_url( $oria_l['url'] ); ?>" data-oria-event="area_practice_click">
								<span><?php echo esc_html( $oria_l['name'] ); ?></span>
								<span class="xa-group__n"><?php echo esc_html( number_format_i18n( $oria_l['n'] ) ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>
	</div>
	</div>
</section>
<?php endif; ?>

<?php
$oria_around = array_values( array_filter( array_map( 'strval', (array) ( $oria_guide['getting_around'] ?? array() ) ) ) );
$oria_links  = array_values( array_filter( (array) ( $oria_guide['links'] ?? array() ), static fn( $l ): bool => is_array( $l ) && ! empty( $l['url'] ) && ! empty( $l['label'] ) ) );
?>
<?php if ( $oria_around || $oria_nearby ) : ?>
<!-- 8 + 9. Getting around, and nearby -->
<section class="wrap section xa-onward" aria-label="<?php esc_attr_e( 'Getting around and nearby areas', 'oria' ); ?>">
	<?php if ( $oria_around ) : ?>
		<div class="xa-around" id="getting-around">
			<p class="micro xa-head__eyebrow"><?php esc_html_e( 'Getting around', 'oria' ); ?></p>
			<h2 class="h3 xa-head__title">
				<?php
				/* translators: %s: area */
				printf( esc_html__( 'Getting to and around %s', 'oria' ), esc_html( $oria_place ) );
				?>
			</h2>
			<ul class="xa-around__list">
				<?php foreach ( $oria_around as $oria_line ) : ?>
					<li><?php echo esc_html( $oria_line ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $oria_links ) : ?>
				<p class="xa-around__links">
					<?php foreach ( $oria_links as $oria_lk ) : ?>
						<a href="<?php echo esc_url( (string) $oria_lk['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( (string) $oria_lk['label'] ); ?> <span aria-hidden="true">&nearr;</span><span class="sr-only"><?php esc_html_e( '(opens in a new tab)', 'oria' ); ?></span></a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $oria_nearby ) : ?>
		<div class="xa-nearby" id="nearby">
			<p class="micro xa-head__eyebrow"><?php esc_html_e( 'Nearby areas', 'oria' ); ?></p>
			<h2 class="h3 xa-head__title">
				<?php
				/* translators: %s: area */
				printf( esc_html__( 'Close to %s', 'oria' ), esc_html( $oria_place ) );
				?>
			</h2>
			<ul class="xa-nearby__row">
				<?php foreach ( $oria_nearby as $oria_nb ) : ?>
					<li>
						<a class="xa-near" href="<?php echo esc_url( (string) get_term_link( $oria_nb['term'] ) ); ?>" data-oria-event="area_nearby_click">
							<strong class="xa-near__name"><?php echo esc_html( \Oria\Theme\tname( $oria_nb['term'] ) ); ?></strong>
							<span class="xa-near__meta">
								<?php
								$oria_nm = array(
									/* translators: %s: number of places */
									sprintf( _n( '%s place', '%s places', $oria_nb['n'], 'oria' ), number_format_i18n( $oria_nb['n'] ) ),
								);
								if ( '' !== $oria_nb['dir'] ) {
									/* translators: %s: compass direction, e.g. north-east */
									array_unshift( $oria_nm, sprintf( __( 'To the %s', 'oria' ), $oria_nb['dir'] ) );
								}
								echo esc_html( implode( ' · ', $oria_nm ) );
								?>
							</span>
							<?php if ( '' !== $oria_nb['top'] ) : ?>
								<span class="xa-near__top">
									<?php
									/* translators: %s: practice */
									printf( esc_html__( 'Most of all: %s', 'oria' ), esc_html( $oria_nb['top'] ) );
									?>
								</span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</section>
<?php endif; ?>

<?php

/*
 * What's on near here. The area's child suburbs count as the area, so a
 * region page is not empty while an event sits in one of its suburbs.
 */
if ( $oria_term && function_exists( '\Oria\Core\Events\for_area' ) ) {
	get_template_part(
		'template-parts/events-module',
		null,
		array(
			'ids'       => \Oria\Core\Events\for_area( $oria_term->slug, 3 ),
			/* translators: %s: suburb or region */
			'title'     => sprintf( __( "What's on near %s", 'oria' ), $oria_place ),
			'all'       => get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ),
			'all_label' => __( 'See every event', 'oria' ),
		)
	);
}

// 10. FAQs, as the area pages have always carried them.
if ( $oria_term ) {
	get_template_part(
		'template-parts/faq',
		null,
		array(
			'term'    => $oria_term,
			/* translators: %s: area */
			'heading' => sprintf( __( 'Wellness in %s — common questions', 'oria' ), $oria_place ),
		)
	);
}

get_footer();
