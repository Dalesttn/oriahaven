<?php
/**
 * The redesigned directory — /directory/ in the same layout as the new
 * category pages: the three-floor spine, an answer-first top with the facts
 * strip, and — where a category page offers its styles — this page offers
 * its categories, as the same grid of cards. Then the toolbar and the
 * listings, then the reading floor.
 *
 * Same URL, same H1, same FAQ, same schema as the page it replaces; only
 * the layout changes. Served only when Oria\Core\PracticesIndex\mode() is
 * 'preview' or 'live' — see that file — so production is untouched until
 * the switch is flipped.
 */

declare(strict_types=1);

get_header();

$oria_mode = \Oria\Core\PracticesIndex\mode();

/*
 * Which city this page is, if any. /explore/ names none and shows the whole
 * corpus; /explore/perth/ names one and everything below follows it -- the
 * count, the categories, the map label and the client-side lock.
 */
$oria_dircity  = null;
$oria_cname = '';
if ( function_exists( '\Oria\Core\Cities\current' ) ) {
	$oria_cslug = (string) get_query_var( \Oria\Core\Cities\QUERY_VAR );
	if ( '' !== $oria_cslug && \Oria\Core\Cities\exists( $oria_cslug ) ) {
		$oria_dircity  = (array) \Oria\Core\Cities\get( $oria_cslug );
		$oria_cname = \Oria\Core\Cities\name( $oria_dircity );
	}

// Every category with listings, counted the way the category pages count
// (descendants included), most populated first.
$oria_cats = array();
foreach ( \Oria\Core\PracticesIndex\practices() as $oria_t ) {
	$oria_cids = function_exists( '\Oria\Core\Intents\listings_in' )
		? \Oria\Core\Intents\listings_in( $oria_t )
		: array();
	// Counted inside the city, or a Margaret River page offers Perth's numbers.
	if ( $oria_dircity && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
		$oria_cids = \Oria\Core\Cities\filter_ids( $oria_cids, $oria_dircity );
	}
	$oria_n = $oria_cids ? count( $oria_cids ) : 0;
	if ( $oria_n > 0 ) {
		$oria_cats[] = array( 'term' => $oria_t, 'count' => $oria_n, 'url' => '' !== $oria_mode ? \Oria\Core\PracticesIndex\category_url( $oria_t ) : (string) get_term_link( $oria_t ) );
	}
}
usort( $oria_cats, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] ?: strcasecmp( \Oria\Theme\tname( $a['term'] ), \Oria\Theme\tname( $b['term'] ) ) );

}

// Facts for the strip, over whatever this page is about.
$oria_all = get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
if ( $oria_dircity && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
	$oria_all = \Oria\Core\Cities\filter_ids( $oria_all, $oria_dircity );
}
$oria_suburbs = array();
$oria_claimed = 0;
foreach ( $oria_all as $oria_id ) {
	foreach ( \Oria\Theme\oria_terms_of( (int) $oria_id, 'area' ) as $oria_a ) {
		if ( $oria_a->parent ) { $oria_suburbs[ $oria_a->slug ] = true; }
	}
	if ( 'unclaimed' !== \Oria\Theme\claim_status( (int) $oria_id ) ) { $oria_claimed++; }
}
// The site FAQ, so the spine knows whether to offer the stop.
$oria_site_faq = function_exists( '\Oria\Core\Faq\site_faq' ) ? (array) \Oria\Core\Faq\site_faq() : array();

// Floor 4 — the latest guides from the journal.
$oria_guides = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'date', 'order' => 'DESC' ) );

$oria_regions = \Oria\Core\Taxonomies\regions();
$oria_regions = is_wp_error( $oria_regions ) ? array() : $oria_regions;

/*
 * regions() spans every city, so the Perth directory was offering
 * "Dunsborough & the Capes" and "Margaret River & South" among its own.
 */
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
?>

<nav class="spine" aria-label="<?php esc_attr_e( 'Page sections', 'oria' ); ?>">
	<div class="wrap spine__row">
		<a href="#decide"><b>1</b> <?php esc_html_e( 'Decide', 'oria' ); ?></a>
		<a href="#browse"><b>2</b> <?php printf( esc_html__( 'Browse all %s', 'oria' ), esc_html( number_format_i18n( count( $oria_all ) ) ) ); ?></a>
		<a href="#read"><b>3</b> <?php esc_html_e( 'Read up', 'oria' ); ?></a>
		<?php if ( $oria_guides ) : ?><a href="#guides"><b>4</b> <?php esc_html_e( 'Guides', 'oria' ); ?></a><?php endif; ?>
		<?php if ( $oria_site_faq ) : ?><a href="#faq"><b><?php echo $oria_guides ? 5 : 4; ?></b> <?php esc_html_e( 'FAQ', 'oria' ); ?></a><?php endif; ?>
	</div>
</nav>

<!-- Floor 1 — Decide -->
<section class="wrap pagehead floor" id="decide">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Explore', 'oria' ); ?></span>
	</nav>
	<div class="decide">
		<div class="decide__answer">
			<div class="decide__head">
				<?php $oria_qh = \Oria\Core\PracticesIndex\query_heading(); ?>
				<span class="micro"><?php esc_html_e( 'Explore', 'oria' ); ?></span>
				<?php if ( '' !== $oria_qh ) : ?>
					<h1 class="h1 pagehead__title"><?php echo esc_html( $oria_qh ); ?></h1>
				<?php else : ?>
					<h1 class="h1 pagehead__title"><?php
						printf(
							/* translators: %s: live listing count. The page's one big emotional line — the count keeps it honest and keeps it fresh. */
							esc_html__( 'Discover %s ways to look after yourself.', 'oria' ),
							'<b>' . esc_html( number_format_i18n( count( $oria_all ) ) ) . '</b>'
						);
					?></h1>
				<?php endif; ?>
			</div>
			<span class="micro"><?php esc_html_e( 'The short answer', 'oria' ); ?></span>
			<p class="lede" style="margin-top:.5rem;max-width:62ch">
				<?php
				printf(
					/* translators: 1: listing count, 2: category count, 3: suburb count. */
					esc_html__( 'Oria Haven lists %1$s wellness practices across %4$s — %2$s categories, %3$s suburbs — every one checked by hand. Pick a practice below; suburb, price and style come after.', 'oria' ),
					'<b>' . esc_html( number_format_i18n( count( $oria_all ) ) ) . '</b>',
					esc_html( number_format_i18n( count( $oria_cats ) ) ),
					esc_html( number_format_i18n( count( $oria_suburbs ) ) ),
					esc_html( '' !== $oria_cname ? $oria_cname : __( 'Perth', 'oria' ) )
				);
				?>
			</p>
			<p class="hint" style="margin-top:.6rem"><?php esc_html_e( "Most listings here were built from public information and are waiting for their owner to take them over — each one says so on its own page. We never take a cut of a booking.", 'oria' ); ?></p>
		</div>
	</div>

	<?php if ( $oria_cats ) : ?>
	<div class="dirsw">
		<h2 class="h3 typewrite" style="margin-top:2rem" data-typewrite><?php esc_html_e( 'Start with a practice', 'oria' ); ?></h2>
		<p class="hint typewrite__after" style="margin:.35rem 0 1rem"><?php esc_html_e( 'Each opens that category — its styles, its suburbs and its questions. Counts are live; nothing here is ranked.', 'oria' ); ?></p>
		<div class="intentgrid intentgrid--cats">
			<?php foreach ( $oria_cats as $oria_i => $oria_c ) : ?>
				<a class="intentcard intentcard--icon" href="<?php echo esc_url( $oria_c['url'] ); ?>" style="--i:<?php echo (int) $oria_i; ?>">
					<?php if ( function_exists( '\Oria\Core\Categories\icon' ) ) : ?>
						<span class="intentcard__icon" aria-hidden="true"><?php echo \Oria\Core\Categories\icon( $oria_c['term']->slug ); // phpcs:ignore WordPress.Security.EscapeOutput -- inline SVG from the plugin's own assets ?></span>
					<?php endif; ?>
					<span class="intentcard__label"><?php echo esc_html( \Oria\Theme\tname( $oria_c['term'] ) ); ?></span>
					<span class="intentcard__count"><?php echo esc_html( number_format_i18n( (int) $oria_c['count'] ) ); ?> <span aria-hidden="true">→</span></span>
				</a>
			<?php endforeach; ?>
		</div>
		<?php if ( count( $oria_cats ) > 8 ) : ?>
			<button type="button" class="intentgrid__more" data-intentgrid-more aria-expanded="false"><?php printf( esc_html__( 'Show all %s categories', 'oria' ), esc_html( number_format_i18n( count( $oria_cats ) ) ) ); ?> <span aria-hidden="true">▾</span></button>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php
	/*
	 * Only the wants this page can actually answer. Without the counts the
	 * row printed all twelve on /explore/margaret-river/, where most of
	 * them filter to nothing.
	 */
	get_template_part(
		'template-parts/directory',
		'goodfor',
		function_exists( '\Oria\Core\GoodFor\counts' ) ? array( 'counts' => \Oria\Core\GoodFor\counts( $oria_all ) ) : array()
	);
	?>

		<?php
	/*
	 * The whole directory on one map — every listing with coordinates,
	 * same pins, popups and photo cards as the category pages, full
	 * width where the category grid used to sprawl.
	 */
	$oria_map = array();
	foreach ( $oria_all as $oria_mid ) {
		$oria_mla = get_post_meta( (int) $oria_mid, 'geo_lat', true );
		$oria_mlo = get_post_meta( (int) $oria_mid, 'geo_lng', true );
		if ( ! is_numeric( $oria_mla ) || ! is_numeric( $oria_mlo ) || 0.0 === (float) $oria_mla ) {
			continue;
		}
		$oria_mterms = \Oria\Theme\oria_terms_of( (int) $oria_mid, 'area' );
		$oria_msub  = '';
		foreach ( $oria_mterms as $oria_mt ) {
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
	?>
	<?php if ( $oria_map ) : ?>
		<h2 class="h3" style="margin-top:2rem"><?php esc_html_e( 'Every practice, on the map', 'oria' ); ?></h2>
		<p class="hint" style="margin:.35rem 0 1rem"><?php esc_html_e( 'Hover a pin to see who it is; click for the photo and profile.', 'oria' ); ?></p>
		<div class="catmap catmap--wide" data-catmap role="img" aria-label="<?php printf( esc_attr__( 'Map of every listed practice across %s', 'oria' ), esc_attr( '' !== $oria_cname ? $oria_cname : __( 'Perth', 'oria' ) ) ); ?>">
			<div class="catmap__tip" hidden></div>
		</div>
		<script type="application/json" data-catmap-data><?php echo wp_json_encode( $oria_map ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON in a data script tag ?></script>
	<?php endif; ?>
</section>

<!-- Floor 2 — Browse -->
<section class="wrap section section--top-flush floor" id="browse">
	<h2 class="micro floor__label"><?php esc_html_e( 'Browse', 'oria' ); ?></h2>
	<?php get_template_part( 'template-parts/directory', 'toolbar', array( 'term' => null, 'ids' => $oria_all ) ); ?>
	<p class="dir__count" id="dirCount" style="margin-top:1rem"></p>
	<div class="chips" id="dirChips" style="margin-top:.5rem"></div>
	<h2 class="sr-only"><?php esc_html_e( 'All listings', 'oria' ); ?></h2>
	<?php // data-city locks the client rebuild to this city, as on the category pages. ?>
	<div class="dir__results dir__results--wide" id="dirResults"<?php echo $oria_dircity ? ' data-city="' . esc_attr( (string) $oria_dircity['slug'] ) . '"' : ''; ?>>
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/listing', 'card' );
		endwhile;
		?>
	</div>
</section>

<!-- Floor 3 — Read -->
<section class="wrap section floor" id="read">
	<h2 class="micro floor__label"><?php esc_html_e( 'Read up', 'oria' ); ?></h2>

	<?php
	/*
	 * The city's own read-up, before the browse chips. Facts about what is
	 * listed and how it is spread -- never about what any of it does, which
	 * is not a claim a directory is allowed to make.
	 */
	$oria_readup = function_exists( '\Oria\Core\Cities\read_up' )
		? \Oria\Core\Cities\read_up( $oria_dircity )
		: array();
	?>
	<?php // Only where the page is a city. /explore/ is every city at once,
	// and a read-up naming one of them there would be describing a
	// different set of listings from the one on screen. ?>
	<?php if ( $oria_dircity && $oria_readup ) : ?>
		<h2 class="h3" style="margin-bottom:1rem">
			<?php printf( esc_html__( 'What is listed in %s', 'oria' ), esc_html( '' !== $oria_cname ? $oria_cname : __( 'Perth', 'oria' ) ) ); ?>
		</h2>
		<div class="prose prose--intro" style="margin-bottom:2rem">
			<?php foreach ( $oria_readup as $oria_para ) : ?>
				<p><?php echo esc_html( $oria_para ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $oria_regions ) : ?>
		<h2 class="micro" style="margin-bottom:1rem"><?php esc_html_e( 'Browse by area', 'oria' ); ?></h2>
		<div class="chips">
			<?php foreach ( $oria_regions as $oria_r ) : ?>
				<a class="pill" href="<?php echo esc_url( \Oria\Core\PracticesIndex\region_url( $oria_r ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_r ) ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php
	/*
	 * Counted over this page's listings, not the whole corpus: the Margaret
	 * River directory was offering "Massage (100)" above seven listings.
	 *
	 * And only the head of the list. Ninety pills is not a browse, it is a
	 * wall -- the toolbar's Style & specialty popover still holds every one
	 * of them, filtered to what this page shows.
	 */
	$oria_spec_max = 24;
	\Oria\Theme\prime_listing_terms( $oria_all );

	$oria_spec_tally = array();
	foreach ( $oria_all as $oria_sid ) {
		foreach ( \Oria\Theme\oria_terms_of( (int) $oria_sid, 'specialty' ) as $oria_st ) {
			if ( ! isset( $oria_spec_tally[ $oria_st->term_id ] ) ) {
				$oria_spec_tally[ $oria_st->term_id ] = array( 'term' => $oria_st, 'n' => 0 );
			}
			++$oria_spec_tally[ $oria_st->term_id ]['n'];
		}
	}
	usort(
		$oria_spec_tally,
		static fn( array $a, array $b ): int => $b['n'] <=> $a['n']
			?: strcasecmp( \Oria\Theme\tname( $a['term'] ), \Oria\Theme\tname( $b['term'] ) )
	);
	$oria_spec_terms = array_slice( $oria_spec_tally, 0, $oria_spec_max );

	if ( $oria_spec_terms ) :
		?>
		<h2 class="micro" style="margin:2rem 0 1rem"><?php esc_html_e( 'Browse by specialty', 'oria' ); ?></h2>
		<div class="chips">
			<?php foreach ( $oria_spec_terms as $oria_row ) : ?>
				<a class="pill" href="<?php echo esc_url( \Oria\Core\PracticesIndex\specialty_url( $oria_row['term'] ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_row['term'] ) . ' (' . (int) $oria_row['n'] . ')' ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php
get_template_part( 'template-parts/guides', 'floor', array( 'guides' => $oria_guides, 'heading' => __( 'Latest from the journal', 'oria' ) ) );

// The site FAQ, as the directory has always carried it — at top level so it
// keeps its own section spacing.
if ( $oria_site_faq ) {
	get_template_part( 'template-parts/faq', null, array( 'faqs' => $oria_site_faq, 'heading' => __( 'Common questions', 'oria' ), 'id' => 'faq' ) );
}

$oria_feat = \Oria\Theme\featured_listings( 3 );
if ( $oria_feat ) :
	?>
<section class="wrap section section--top-flush">
	<?php get_template_part( 'template-parts/featured', 'band', array( 'posts' => $oria_feat, 'heading' => __( 'Featured practices — paid placement', 'oria' ) ) ); ?>
</section>
<?php endif; ?>

<?php
get_footer();
