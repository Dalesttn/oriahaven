<?php
/**
 * An area page -- a suburb (/area/perth/freo/fremantle/), a region or a city
 * -- as a local guide: somewhere to discover the place, choose an
 * experience and plan an outing. Overrides the parent's taxonomy-area.php.
 *
 *   1. the place: text on ivory, its own photograph beside it (only a
 *      photograph of this place -- no stand-in scenery)
 *   2. in-page links and one short paragraph about the place
 *   3. the places: the Local Finder, the filters, the listings, the map
 *   4. make a day of it -- reviewed outing records only, every stop checked
 *   5. leave room for a little exploring -- a handful of real places
 *   6. what's on -- the page's one events block, venues in this area
 *   7. plan your visit -- getting here, and what to check before going
 *   8. explore nearby, 9. a small invitation to local businesses, 10. FAQs
 *
 * Sections 4 and 5 and the hero photograph come from the hand-written guide
 * (assets/data/area-guides.json); an area without them gets a shorter
 * page, never filler. Every count comes from Area\rows() -- the cards, the
 * filters, the map, the figures and the FAQ all read the same rows.
 *
 * Same engine as before: #dirResults keeps app.js's directory mode with the
 * area locked (data-region / data-suburb / data-city), so counts, chips,
 * load more and the map are app.js's; the dock's inputs are its own
 * [data-filter] inputs. v4-category.js runs the dock and ribbon (the hero
 * says so with data-xc-root); v4-area.css / v4-area.js add this page's own
 * sections. Indexing is unchanged: Oria\Core\AreaDepth still noindexes a
 * thin area and keeps it out of the sitemap.
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
// Only the place's own photograph: a region's or the city's would be a
// picture of somewhere else under this place's name.
$oria_photo = $oria_hz && ! empty( $oria_hz['own'] );
if ( $oria_photo ) {
	// Only the hero, preloaded: it is the page's largest paint.
	add_action(
		'wp_head',
		static function () use ( $oria_hz ): void {
			if ( '' !== $oria_hz['mid'] ) {
				printf( '<link rel="preload" as="image" href="%1$s" imagesrcset="%2$s 960w, %1$s 1920w" imagesizes="(max-width: 50rem) 100vw, 45vw" fetchpriority="high">' . "\n", esc_url( $oria_hz['wide'] ), esc_url( $oria_hz['mid'] ) );
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
$oria_snap    = $oria_n >= 2 ? Area\snapshot( $oria_rows, count( $oria_top ), $oria_strong, $oria_cname, $oria_term ) : array();
$oria_rhythm  = Area\rhythm_points( $oria_rows, $oria_strong, $oria_place, $oria_cname, $oria_guide );
$oria_outings = Area\outings( $oria_rows, $oria_guide );
$oria_explore = Area\explore( $oria_rows, $oria_guide );
$oria_else    = Area\elsewhere( $oria_rows, $oria_guide );
$oria_groups  = $oria_term ? Area\practice_groups( $oria_term, $oria_rows, $oria_city ) : array();
$oria_nearby  = $oria_term ? Area\nearby( $oria_term, $oria_cslug, $oria_guide ) : array();
$oria_faqs    = $oria_term ? Area\faqs( $oria_term, $oria_rows, $oria_top ) : array();
$oria_online  = count( array_filter( $oria_rows, static fn( array $r ): bool => in_array( $r['format'] ?? '', array( 'online', 'both' ), true ) ) );
$oria_elsewhere = $oria_else;

/*
 * What's on: the one events block. The shared query already drops what has
 * finished (Perth time) and what is cancelled. On a suburb page an event
 * whose venue names somewhere else is left out too: an organiser based here
 * does not make every event of theirs a local one.
 */
$oria_events       = array();
$oria_events_local = false;
if ( $oria_term && function_exists( '\Oria\Core\Events\for_area' ) ) {
	foreach ( \Oria\Core\Events\for_area( $oria_term->slug, 8 ) as $oria_eid ) {
		$oria_eid   = (int) $oria_eid;
		$oria_venue = trim( (string) get_post_meta( $oria_eid, 'venue', true ) );
		if ( $oria_is_sub && '' !== $oria_venue && false === stripos( $oria_venue, $oria_place ) ) {
			continue;
		}
		if ( preg_match( '/^\s*online\b/i', $oria_venue ) ) {
			continue;
		}
		$oria_start = strtotime( (string) get_post_meta( $oria_eid, 'event_start', true ) );
		if ( ! $oria_start ) {
			continue;
		}
		$oria_events[] = array(
			'id'    => $oria_eid,
			'when'  => gmdate( 'D j M', $oria_start ) . ( '00:00' !== gmdate( 'H:i', $oria_start ) ? ' · ' . gmdate( 'g.ia', $oria_start ) : '' ),
			'where' => '' !== $oria_venue ? $oria_venue : $oria_place,
		);
		if ( count( $oria_events ) >= 4 ) {
			break;
		}
	}
	$oria_events_local = (bool) $oria_events;
}
$oria_events_url = $oria_term ? Area\events_url( $oria_term, $oria_events_local ) : home_url( '/whats-on-perth/' );

// When the listings here last changed. Not a "checked by hand" date: an
// import moves it too, and the label says only what it is.
$oria_latest  = $oria_ids ? get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'post__in' => $oria_ids, 'numberposts' => 1, 'orderby' => 'modified', 'order' => 'DESC', 'fields' => 'ids' ) ) : array();
$oria_updated = $oria_latest ? (string) get_post_modified_time( 'j F', false, (int) $oria_latest[0], true ) : date_i18n( 'j F' );

// The one-line character: the guide's own, else what the listings say.
$oria_tagline = (string) ( $oria_guide['tagline'] ?? '' );
$oria_subtitle = (string) ( $oria_guide['subtitle'] ?? '' );
if ( '' === $oria_tagline && $oria_n >= Area\MIN_PATTERN ) {
	$oria_two = array_slice( array_keys( $oria_top ), 0, 2 );
	if ( 2 === count( $oria_two ) ) {
		/* translators: 1: area, 2 and 3: practice names */
		$oria_tagline = sprintf( __( 'Most of what %1$s offers is %2$s and %3$s — and there is more besides.', 'oria' ), $oria_place, Area\pname( $oria_two[0] ), Area\pname( $oria_two[1] ) );
	}
}

// The hero's introduction and the short local paragraph: the guide's own
// words where they have been written, never a template with a name swapped.
$oria_intro   = (string) ( $oria_guide['intro'] ?? $oria_tagline );
$oria_local_h = (string) ( $oria_guide['local_heading'] ?? '' );
$oria_local   = (string) ( $oria_guide['local_intro'] ?? ( $oria_guide['rhythm_intro'] ?? '' ) );
if ( '' === $oria_local_h && '' !== $oria_local ) {
	/* translators: %s: area */
	$oria_local_h = sprintf( __( 'About %s', 'oria' ), $oria_place );
}

// The figures, in one disclosure: the standout and the patterns, each with
// its own count (Area\rhythm_points()), from the same rows as the cards.
$oria_facts = $oria_rhythm;

// Getting here, from the guide; the links are its checked sources.
$oria_around = array_values( array_filter( array_map( 'strval', (array) ( $oria_guide['getting_around'] ?? array() ) ) ) );
$oria_links  = array_values( array_filter( (array) ( $oria_guide['links'] ?? array() ), static fn( $l ): bool => is_array( $l ) && ! empty( $l['url'] ) && ! empty( $l['label'] ) ) );

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

// The map's pins, behind the List | Map switch: street addresses only. A
// listing geocoded to the suburb would sit on the suburb's centre point,
// which is a guess drawn as a fact.
$oria_addressed = array();
foreach ( $oria_rows as $oria_r ) {
	if ( 'address' === ( $oria_r['geo'] ?? '' ) ) {
		$oria_addressed[ Area\post_id( $oria_r ) ] = true;
	}
}
$oria_map = array();
foreach ( $oria_ids as $oria_mid ) {
	if ( ! isset( $oria_addressed[ (int) $oria_mid ] ) ) {
		continue;
	}
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

$oria_unmapped = $oria_map ? $oria_n - count( $oria_map ) : 0;
$oria_has_dock = $oria_n >= 4;

// The in-page links: only to sections this area actually has.
$oria_navs = array( 'places' => __( 'Places', 'oria' ) );
if ( $oria_outings ) {
	$oria_navs['day-ideas'] = __( 'Day ideas', 'oria' );
}
$oria_navs['whats-on']        = __( "What's on", 'oria' );
$oria_navs['plan-your-visit'] = __( 'Plan your visit', 'oria' );
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

<div class="oag" data-xc-root>

<!-- 1. The place -->
<section class="oag-hero<?php echo $oria_photo ? '' : ' oag-hero--text'; ?>" id="decide" aria-labelledby="oagTitle">
	<div class="oag-wrap oag-hero__grid">
		<div class="oag-hero__copy">
			<nav class="crumbs oag-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/explore/' ) ); ?>"><?php esc_html_e( 'Explore', 'oria' ); ?></a>
				<?php if ( '' !== $oria_region_name ) : ?>
					<span aria-hidden="true">/</span>
					<a href="<?php echo esc_url( (string) get_term_link( $oria_region ) ); ?>"><?php echo esc_html( $oria_region_name ); ?></a>
				<?php endif; ?>
				<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_place ); ?></span>
			</nav>
			<p class="oag-eyebrow"><?php esc_html_e( 'The Oria local guide', 'oria' ); ?></p>
			<h1 class="oag-hero__title" id="oagTitle">
				<?php
				/* translators: %s: area */
				printf( esc_html__( 'Wellness in %s', 'oria' ), esc_html( $oria_term ? Area\in_name( $oria_term ) : $oria_place ) );
				?>
			</h1>
			<?php if ( '' !== $oria_subtitle ) : ?>
				<p class="oag-hero__sub"><?php echo esc_html( $oria_subtitle ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $oria_intro ) : ?>
				<p class="oag-hero__intro"><?php echo esc_html( $oria_intro ); ?></p>
			<?php endif; ?>
			<?php if ( $oria_n ) : ?>
				<div class="oag-hero__acts">
					<a class="oag-btn oag-btn--primary" href="#places" data-oria-event="area_places_click"><?php esc_html_e( 'Explore places', 'oria' ); ?></a>
					<?php if ( $oria_outings ) : ?>
						<a class="oag-btn oag-btn--secondary" href="#day-ideas" data-oria-event="area_reset_click"><?php esc_html_e( 'Plan a local outing', 'oria' ); ?></a>
					<?php endif; ?>
					<?php if ( $oria_map ) : ?>
						<button type="button" class="oag-textlink xc-js" data-xc-map data-xa-map data-oria-event="area_map_open"><?php esc_html_e( 'View map', 'oria' ); ?></button>
					<?php endif; ?>
				</div>
				<p class="oag-hero__meta">
					<?php
					echo esc_html(
						implode(
							' · ',
							array(
								/* translators: %s: number of places */
								sprintf( _n( '%s place listed', '%s places listed', $oria_n, 'oria' ), number_format_i18n( $oria_n ) ),
								/* translators: %s: day and month */
								sprintf( __( 'Listings last changed %s', 'oria' ), $oria_updated ),
							)
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php if ( $oria_photo ) : ?>
			<figure class="oag-hero__figure">
				<img class="oag-hero__img" src="<?php echo esc_url( $oria_hz['wide'] ); ?>"<?php echo '' !== $oria_hz['mid'] ? ' srcset="' . esc_url( $oria_hz['mid'] ) . ' 960w, ' . esc_url( $oria_hz['wide'] ) . ' 1920w" sizes="(max-width: 50rem) 100vw, 45vw"' : ''; ?> width="1920" height="1280" alt="<?php echo esc_attr( $oria_hz['alt'] ); ?>" style="object-position:<?php echo esc_attr( $oria_hz['pos'] ); ?>" fetchpriority="high" decoding="async">
				<?php if ( '' !== $oria_hz['credit'] ) : ?>
					<figcaption class="oag-hero__credit"><?php echo esc_html( $oria_hz['credit'] ); ?></figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>
	</div>
</section>

<!-- 2. The way round the page, and the place in a paragraph -->
<div class="oag-wrap oag-intro">
	<nav class="oag-nav" aria-label="<?php esc_attr_e( 'On this page', 'oria' ); ?>">
		<?php foreach ( $oria_navs as $oria_nav_id => $oria_nav_label ) : ?>
			<a href="#<?php echo esc_attr( $oria_nav_id ); ?>"><?php echo esc_html( $oria_nav_label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php if ( '' !== $oria_local ) : ?>
		<div class="oag-local">
			<?php if ( '' !== $oria_local_h ) : ?>
				<h2 class="oag-local__title"><?php echo esc_html( $oria_local_h ); ?></h2>
			<?php endif; ?>
			<p class="oag-local__text"><?php echo esc_html( $oria_local ); ?></p>
		</div>
	<?php endif; ?>
</div>

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

<!-- 3. The places -->
<section class="wrap section section--top-flush floor xc-browse oag-places" id="browse">
	<span class="oag-anchor" id="places" aria-hidden="true"></span>
	<div class="xc-reshead">
		<h2 class="oag-h2" id="results">
			<?php
			/* translators: %s: area */
			printf( esc_html__( 'Find your kind of wellness in %s', 'oria' ), esc_html( $oria_place ) );
			?>
		</h2>
		<p class="dir__count" id="dirCount" role="status" aria-live="polite"></p>
	</div>
	<?php
	if ( $oria_n ) {
		get_template_part( 'template-parts/directory', 'toolbar', array( 'term' => null, 'ids' => $oria_ids, 'guide' => true ) );
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
		 *
		 * The cards are the page's own rows -- the set every count, filter
		 * and pin on the page is worked out from -- not the taxonomy query,
		 * which also held a listing tagged here whose suburb says otherwise
		 * and so was drawn in the HTML but in none of the figures.
		 */
		?>
		<div class="dir__results dir__results--wide" id="dirResults" data-region="<?php echo esc_attr( ! $oria_is_cty && $oria_region ? $oria_region->slug : '' ); ?>"<?php echo $oria_is_cty ? ' data-city="' . esc_attr( (string) $oria_term->slug ) . '"' : ''; ?><?php echo $oria_is_sub ? ' data-suburb="' . esc_attr( $oria_place ) . '"' : ''; ?>>
			<?php
			global $post;
			foreach ( $oria_ids as $oria_cid ) {
				$post = get_post( (int) $oria_cid ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				if ( $post instanceof WP_Post ) {
					setup_postdata( $post );
					get_template_part( 'template-parts/listing', 'card' );
				}
			}
			wp_reset_postdata();
			if ( function_exists( '\Oria\Core\Schema\register_list' ) ) {
				\Oria\Core\Schema\register_list( $oria_ids );
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
				<?php if ( $oria_unmapped > 0 ) : ?>
					<p class="oag-mapnote">
						<?php
						/* translators: %d: number of places */
						printf( esc_html( _n( '%d place has no street address on file, so it is not on the map.', '%d places have no street address on file, so they are not on the map.', $oria_unmapped, 'oria' ) ), (int) $oria_unmapped );
						?>
					</p>
				<?php endif; ?>
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

	<?php if ( $oria_elsewhere ) : ?>
		<aside class="oag-elsewhere" aria-labelledby="oagElseTitle">
			<h3 class="oag-elsewhere__title" id="oagElseTitle">
				<?php
				/* translators: %s: area */
				printf( esc_html__( 'Listed in %s, but not only here', 'oria' ), esc_html( $oria_place ) );
				?>
			</h3>
			<ul class="oag-elsewhere__list">
				<?php foreach ( $oria_elsewhere as $oria_el ) : ?>
					<li><a href="<?php echo esc_url( $oria_el['url'] ); ?>"><?php echo esc_html( $oria_el['name'] ); ?></a>: <?php echo esc_html( $oria_el['note'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</aside>
	<?php endif; ?>

	<?php if ( $oria_groups || $oria_facts ) : ?>
		<div class="oag-more">
			<?php if ( $oria_groups ) : ?>
				<details class="oag-disc">
					<summary data-oria-event="area_practices_open">
						<?php
						/* translators: %s: area */
						printf( esc_html__( 'Browse every practice in %s', 'oria' ), esc_html( $oria_place ) );
						?>
					</summary>
					<div class="oag-groups">
						<?php foreach ( $oria_groups as $oria_gr ) : ?>
							<div class="oag-group">
								<h3 class="oag-group__name"><?php echo esc_html( $oria_gr['name'] ); ?></h3>
								<ul class="oag-group__links">
									<?php foreach ( $oria_gr['links'] as $oria_l ) : ?>
										<li class="<?php echo $oria_l['sub'] ? 'is-sub' : ''; ?>">
											<a href="<?php echo esc_url( $oria_l['url'] ); ?>" data-oria-event="area_practice_click"><?php echo esc_html( $oria_l['name'] ); ?> <span class="oag-group__n"><?php echo esc_html( number_format_i18n( $oria_l['n'] ) ); ?></span></a>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endforeach; ?>
					</div>
				</details>
			<?php endif; ?>
			<?php if ( $oria_facts ) : ?>
				<details class="oag-disc" data-area-slug="<?php echo esc_attr( $oria_term ? $oria_term->slug : '' ); ?>">
					<summary data-oria-event="area_snapshot_methodology_open">
						<?php
						/* translators: %s: area */
						printf( esc_html__( '%s in figures', 'oria' ), esc_html( $oria_place ) );
						?>
					</summary>
					<ul class="oag-facts">
						<?php foreach ( $oria_facts as $oria_f ) : ?>
							<li><strong><?php echo esc_html( $oria_f['big'] ); ?></strong> <?php echo esc_html( $oria_f['label'] ); ?><?php if ( '' !== $oria_f['note'] ) : ?> <span class="oag-facts__note"><?php echo esc_html( $oria_f['note'] ); ?></span><?php endif; ?></li>
						<?php endforeach; ?>
					</ul>
					<p class="oag-facts__how">
						<?php
						printf(
							/* translators: %s: date */
							esc_html__( 'Counted from the places Oria Haven lists here, which last changed %s. A place counts once for each kind of practice it offers. Prices are the lowest each place publishes for a session; many publish none, and each sets and changes its own.', 'oria' ),
							esc_html( $oria_updated )
						);
						?>
					</p>
				</details>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<div class="xc-browse-end" aria-hidden="true"></div>
</section>

<?php if ( $oria_outings ) : ?>
<!-- 4. Make a day of it -->
<section class="oag-section" id="day-ideas" aria-labelledby="oagDaysTitle">
	<div class="oag-wrap">
		<p class="oag-eyebrow"><?php esc_html_e( 'Day ideas', 'oria' ); ?></p>
		<h2 class="oag-h2" id="oagDaysTitle"><?php esc_html_e( 'Make a day of it', 'oria' ); ?></h2>
		<p class="oag-lede"><?php esc_html_e( 'Start with an experience, add a little time outdoors or a refreshment stop, and make the outing your own.', 'oria' ); ?></p>
		<div class="oag-plans">
			<?php foreach ( $oria_outings as $oria_o ) : ?>
				<article class="oag-plan" id="plan-<?php echo esc_attr( $oria_o['slug'] ); ?>" aria-labelledby="plan-<?php echo esc_attr( $oria_o['slug'] ); ?>-h">
					<h3 class="oag-plan__name" id="plan-<?php echo esc_attr( $oria_o['slug'] ); ?>-h"><?php echo esc_html( $oria_o['name'] ); ?></h3>
					<p class="oag-plan__line"><?php echo esc_html( $oria_o['line'] ); ?></p>
					<ul class="oag-plan__meta">
						<?php if ( '' !== $oria_o['when'] ) : ?>
							<li><?php echo esc_html( $oria_o['when'] ); ?></li>
						<?php endif; ?>
						<li>
							<?php
							/* translators: %d: number of stops */
							printf( esc_html( _n( '%d stop', '%d stops', count( $oria_o['stops'] ), 'oria' ) ), count( $oria_o['stops'] ) );
							?>
						</li>
					</ul>
					<details class="oag-plan__more" data-oag-plan>
						<summary data-oria-event="area_plan_open"><?php esc_html_e( 'View the plan', 'oria' ); ?></summary>
						<ol class="oag-stops">
							<?php foreach ( $oria_o['stops'] as $oria_st ) : ?>
								<li class="oag-stop oag-stop--<?php echo esc_attr( $oria_st['kind'] ); ?>">
									<span class="oag-stop__role"><?php echo esc_html( $oria_st['label'] ); ?></span>
									<?php if ( '' !== $oria_st['url'] ) : ?>
										<a class="oag-stop__name" href="<?php echo esc_url( $oria_st['url'] ); ?>" data-oria-event="area_reset_stop"><?php echo esc_html( $oria_st['name'] ); ?></a>
									<?php else : ?>
										<strong class="oag-stop__name"><?php echo esc_html( $oria_st['name'] ); ?></strong>
									<?php endif; ?>
									<?php if ( '' !== $oria_st['detail'] ) : ?>
										<span class="oag-stop__detail"><?php echo esc_html( $oria_st['detail'] ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== $oria_st['join'] ) : ?>
										<span class="oag-stop__join"><?php echo esc_html( $oria_st['join'] ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ol>
						<p class="oag-plan__cost"><?php echo esc_html( $oria_o['cost'] ); ?></p>
						<?php if ( '' !== $oria_o['duration'] ) : ?>
							<p class="oag-plan__cost"><?php echo esc_html( $oria_o['duration'] ); ?></p>
						<?php endif; ?>
						<p class="oag-plan__acts">
							<?php if ( '' !== $oria_o['directions'] ) : ?>
								<a class="oag-textlink" href="<?php echo esc_url( $oria_o['directions'] ); ?>" target="_blank" rel="noopener" data-oria-event="area_plan_directions"><?php esc_html_e( 'Directions between the stops', 'oria' ); ?> <span aria-hidden="true">&nearr;</span><span class="sr-only"><?php esc_html_e( '(opens Google Maps in a new tab)', 'oria' ); ?></span></a>
							<?php endif; ?>
							<a class="oag-textlink" href="#plan-<?php echo esc_attr( $oria_o['slug'] ); ?>" data-oag-share><?php esc_html_e( 'Link to this plan', 'oria' ); ?></a>
						</p>
						<?php if ( '' !== $oria_o['checked'] ) : ?>
							<p class="oag-plan__checked">
								<?php
								/* translators: %s: date */
								printf( esc_html__( 'Checked against the listings %s. An idea, not a booking: confirm times and availability with each place.', 'oria' ), esc_html( $oria_o['checked'] ) );
								?>
							</p>
						<?php endif; ?>
					</details>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $oria_explore ) : ?>
<!-- 5. Between experiences -->
<section class="oag-section oag-section--stone" aria-labelledby="oagExploreTitle">
	<div class="oag-wrap">
		<h2 class="oag-h2" id="oagExploreTitle"><?php esc_html_e( 'Leave room for a little exploring', 'oria' ); ?></h2>
		<ul class="oag-tiles">
			<?php foreach ( $oria_explore as $oria_x ) : ?>
				<li class="oag-tile oag-tile--<?php echo esc_attr( $oria_x['kind'] ); ?>">
					<span class="oag-tile__kind"><?php echo esc_html( $oria_x['label'] ); ?></span>
					<h3 class="oag-tile__name">
						<?php if ( '' !== $oria_x['url'] ) : ?>
							<a href="<?php echo esc_url( $oria_x['url'] ); ?>"><?php echo esc_html( $oria_x['name'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $oria_x['name'] ); ?>
						<?php endif; ?>
					</h3>
					<p class="oag-tile__line"><?php echo esc_html( $oria_x['line'] ); ?></p>
					<?php if ( '' !== $oria_x['maps'] ) : ?>
						<a class="oag-textlink oag-tile__map" href="<?php echo esc_url( $oria_x['maps'] ); ?>" target="_blank" rel="noopener" data-oria-event="area_explore_directions"><?php esc_html_e( 'Find it on the map', 'oria' ); ?> <span aria-hidden="true">&nearr;</span><span class="sr-only"><?php esc_html_e( '(opens Google Maps in a new tab)', 'oria' ); ?></span></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
<?php endif; ?>

<!-- 6. What's on: the page's one events block -->
<section class="oag-section" id="whats-on" aria-labelledby="oagEventsTitle">
	<div class="oag-wrap">
		<div class="oag-head">
			<h2 class="oag-h2" id="oagEventsTitle">
				<?php
				/* translators: %s: area */
				printf( esc_html__( "What's on in %s", 'oria' ), esc_html( $oria_place ) );
				?>
			</h2>
			<a class="oag-textlink" href="<?php echo esc_url( $oria_events_url ); ?>" data-oria-event="area_events_all">
				<?php echo esc_html( $oria_events && $oria_events_local ? __( 'All local events', 'oria' ) : __( 'All events in Perth', 'oria' ) ); ?> <span aria-hidden="true">&rarr;</span>
			</a>
		</div>
		<?php if ( $oria_events ) : ?>
			<ul class="oag-events">
				<?php foreach ( $oria_events as $oria_ev ) : ?>
					<li>
						<a class="oag-ev" href="<?php echo esc_url( (string) get_permalink( $oria_ev['id'] ) ); ?>" data-oria-event="area_event">
							<span class="oag-ev__when"><?php echo esc_html( $oria_ev['when'] ); ?></span>
							<strong class="oag-ev__name"><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev['id'] ) ); ?></strong>
							<?php if ( '' !== $oria_ev['where'] ) : ?>
								<span class="oag-ev__where"><?php echo esc_html( $oria_ev['where'] ); ?></span>
							<?php endif; ?>
							<span class="oag-ev__go"><?php esc_html_e( 'Details and booking', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="oag-empty">
				<?php
				/* translators: %s: area */
				printf( esc_html__( 'Nothing is listed in %s in the coming weeks yet. The events page covers the rest of Perth.', 'oria' ), esc_html( $oria_place ) );
				?>
			</p>
		<?php endif; ?>
	</div>
</section>

<!-- 7. Plan your visit -->
<section class="oag-section" id="plan-your-visit" aria-labelledby="oagVisitTitle">
	<div class="oag-wrap">
		<h2 class="oag-h2" id="oagVisitTitle"><?php esc_html_e( 'Plan your visit', 'oria' ); ?></h2>
		<div class="oag-visit">
			<?php if ( $oria_around ) : ?>
				<div class="oag-visit__col">
					<h3 class="oag-visit__h"><?php esc_html_e( 'Getting here', 'oria' ); ?></h3>
					<ul class="oag-visit__list">
						<?php foreach ( $oria_around as $oria_line ) : ?>
							<li><?php echo esc_html( $oria_line ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( $oria_links ) : ?>
						<p class="oag-visit__links">
							<?php foreach ( $oria_links as $oria_lk ) : ?>
								<a class="oag-textlink" href="<?php echo esc_url( (string) $oria_lk['url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( (string) $oria_lk['label'] ); ?> <span aria-hidden="true">&nearr;</span><span class="sr-only"><?php esc_html_e( '(opens in a new tab)', 'oria' ); ?></span></a>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="oag-visit__col">
				<h3 class="oag-visit__h"><?php esc_html_e( 'Before you go', 'oria' ); ?></h3>
				<ul class="oag-visit__list">
					<li><?php esc_html_e( 'Check session times, prices and booking on each listing, and with the place itself. Details change.', 'oria' ); ?></li>
					<li><?php esc_html_e( 'Outdoor sessions can change with the weather. Check with the organiser on the day.', 'oria' ); ?></li>
					<li><?php esc_html_e( 'Oria Haven does not yet hold checked access information for these places. Ask each one directly about step-free access.', 'oria' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
</section>

<?php if ( $oria_nearby ) : ?>
<!-- 8. Explore nearby -->
<section class="oag-section" id="nearby" aria-labelledby="oagNearTitle">
	<div class="oag-wrap">
		<h2 class="oag-h2" id="oagNearTitle"><?php esc_html_e( 'Explore nearby', 'oria' ); ?></h2>
		<ul class="oag-near">
			<?php foreach ( $oria_nearby as $oria_nb ) : ?>
				<li>
					<a class="oag-near__card" href="<?php echo esc_url( (string) get_term_link( $oria_nb['term'] ) ); ?>" data-oria-event="area_nearby_click">
						<strong class="oag-near__name"><?php echo esc_html( \Oria\Theme\tname( $oria_nb['term'] ) ); ?></strong>
						<span class="oag-near__meta">
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
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
<?php endif; ?>

<!-- 9. For local businesses -->
<section class="oag-section oag-section--tight" aria-labelledby="oagBizTitle">
	<div class="oag-wrap">
		<div class="oag-biz">
			<div>
				<h2 class="oag-biz__title" id="oagBizTitle">
					<?php
					/* translators: %s: area */
					printf( esc_html__( 'Run a wellness business or group in %s?', 'oria' ), esc_html( $oria_place ) );
					?>
				</h2>
				<p class="oag-biz__text"><?php esc_html_e( 'Help visitors understand what you offer and how to join.', 'oria' ); ?></p>
			</div>
			<div class="oag-biz__acts">
				<a class="oag-btn oag-btn--ivory" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>" data-oria-event="area_claim_click"><?php esc_html_e( 'Claim your profile', 'oria' ); ?></a>
				<a class="oag-btn oag-btn--outline" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>" data-oria-event="area_list_click"><?php esc_html_e( 'List your business', 'oria' ); ?></a>
			</div>
		</div>
	</div>
</section>

</div>

<?php
// 10. FAQs, counted from the same rows as everything above.
if ( $oria_term && $oria_faqs ) {
	get_template_part(
		'template-parts/faq',
		null,
		array(
			'faqs'    => $oria_faqs,
			/* translators: %s: area */
			'heading' => sprintf( __( 'Wellness in %s: common questions', 'oria' ), $oria_place ),
		)
	);
}

get_footer();
