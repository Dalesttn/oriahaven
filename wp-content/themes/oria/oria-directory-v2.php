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

// Every category with listings, counted the way the category pages count
// (descendants included), most populated first.
$oria_cats = array();
foreach ( \Oria\Core\PracticesIndex\practices() as $oria_t ) {
	$oria_n = function_exists( '\Oria\Core\Intents\listings_in' ) ? count( \Oria\Core\Intents\listings_in( $oria_t ) ) : (int) $oria_t->count;
	if ( $oria_n > 0 ) {
		$oria_cats[] = array( 'term' => $oria_t, 'count' => $oria_n, 'url' => '' !== $oria_mode ? \Oria\Core\PracticesIndex\category_url( $oria_t ) : (string) get_term_link( $oria_t ) );
	}
}
usort( $oria_cats, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] ?: strcasecmp( \Oria\Theme\tname( $a['term'] ), \Oria\Theme\tname( $b['term'] ) ) );

// Facts for the strip, over the whole directory.
$oria_all = get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
$oria_suburbs = array();
$oria_claimed = 0;
foreach ( $oria_all as $oria_id ) {
	foreach ( wp_get_post_terms( (int) $oria_id, 'area' ) as $oria_a ) {
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
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Directory', 'oria' ); ?></span>
	</nav>
	<div style="margin-top:1rem">
		<?php $oria_qh = \Oria\Core\PracticesIndex\query_heading(); ?>
		<span class="micro"><?php esc_html_e( 'The directory', 'oria' ); ?></span>
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

	<div class="decide">
		<div class="decide__answer">
			<span class="micro"><?php esc_html_e( 'The short answer', 'oria' ); ?></span>
			<p class="lede" style="margin-top:.5rem;max-width:62ch">
				<?php
				printf(
					/* translators: 1: listing count, 2: category count, 3: suburb count. */
					esc_html__( 'Oria Haven lists %1$s wellness practices across Perth — %2$s categories, %3$s suburbs — every one checked by hand. Pick a practice below; suburb, price and style come after.', 'oria' ),
					'<b>' . esc_html( number_format_i18n( count( $oria_all ) ) ) . '</b>',
					esc_html( number_format_i18n( count( $oria_cats ) ) ),
					esc_html( number_format_i18n( count( $oria_suburbs ) ) )
				);
				?>
			</p>
			<p class="hint" style="margin-top:.6rem"><?php esc_html_e( "Most listings here were built from public information and are waiting for their owner to take them over — each one says so on its own page. We never take a cut of a booking.", 'oria' ); ?></p>
		</div>
		<?php
		/*
		 * The four stat cards that used to sit here repeated the exact
		 * numbers the sentence above already states — the quotable, dated
		 * sentence is the one machines cite, so it stays and the cards go.
		 */
		?>
	</div>

	<?php get_template_part( 'template-parts/directory', 'goodfor' ); ?>

	<?php if ( $oria_cats ) : ?>
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
	<?php endif; ?>
</section>

<!-- Floor 2 — Browse -->
<section class="wrap section section--top-flush floor" id="browse">
	<h2 class="micro floor__label"><?php esc_html_e( 'Browse', 'oria' ); ?></h2>
	<?php get_template_part( 'template-parts/directory', 'toolbar', array( 'term' => null, 'ids' => $oria_all ) ); ?>
	<p class="dir__count" id="dirCount" style="margin-top:1rem"></p>
	<div class="chips" id="dirChips" style="margin-top:.5rem"></div>
	<h2 class="sr-only"><?php esc_html_e( 'All listings', 'oria' ); ?></h2>
	<div class="dir__results dir__results--wide" id="dirResults">
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

	<?php if ( $oria_regions ) : ?>
		<h2 class="micro" style="margin-bottom:1rem"><?php esc_html_e( 'Browse by area', 'oria' ); ?></h2>
		<div class="chips">
			<?php foreach ( $oria_regions as $oria_r ) : ?>
				<a class="pill" href="<?php echo esc_url( \Oria\Core\PracticesIndex\region_url( $oria_r ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_r ) ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php
	$oria_spec_terms = get_terms( array( 'taxonomy' => 'specialty', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
	if ( ! is_wp_error( $oria_spec_terms ) && $oria_spec_terms ) :
		?>
		<h2 class="micro" style="margin:2rem 0 1rem"><?php esc_html_e( 'Browse by specialty', 'oria' ); ?></h2>
		<div class="chips">
			<?php foreach ( $oria_spec_terms as $oria_s ) : ?>
				<a class="pill" href="<?php echo esc_url( \Oria\Core\PracticesIndex\specialty_url( $oria_s ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_s ) . ' (' . (int) $oria_s->count . ')' ); ?></a>
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
