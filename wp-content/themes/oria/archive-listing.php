<?php
/**
 * The directory: /directory/
 *
 * Server renders the full result list (SEO, no-JS), then app.js takes over
 * live filtering against window.ORIA_DATA — the same code path the
 * prototype used, now fed from real posts.
 */

declare(strict_types=1);

get_header();
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Explore', 'oria' ); ?></span>
	</nav>
	<div class="row-between" style="align-items:flex-end;margin-top:1rem">
		<div>
			<span class="micro"><?php esc_html_e( 'Explore', 'oria' ); ?></span>
			<h1 class="h1 pagehead__title"><?php esc_html_e( "Every practice we've found in Perth.", 'oria' ); ?></h1>
		</div>
		<p class="lede dir__lede" style="max-width:34ch"><?php esc_html_e( "Filter by practice, area, price and format. Most listings were built from public information and are waiting for their owner to take them over — each one says so on its own page.", 'oria' ); ?></p>
	</div>

	<?php
	/*
	 * A hundred-odd listings behind five filters assumes you already know
	 * what you're looking for. Whoever doesn't is the person most likely to
	 * leave, so the way out sits above the filters rather than below them.
	 */
	if ( function_exists( 'Oria\Core\Finder\url' ) ) :
		?>
		<a class="dirnudge" href="<?php echo esc_url( \Oria\Core\Finder\url() ); ?>" data-oria-event="finder_from_directory">
			<span class="dirnudge__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m16.2 16.2 4 4"/><path d="M11 8.2v.1M11 10.4v3.4"/></svg>
			</span>
			<span class="dirnudge__text">
				<b><?php esc_html_e( 'Not sure where to start?', 'oria' ); ?></b>
				<span><?php esc_html_e( 'Answer four questions and we\'ll narrow it down for you.', 'oria' ); ?></span>
			</span>
			<span class="dirnudge__go">
				<?php esc_html_e( 'Try the Wellness Finder', 'oria' ); ?>
				<?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
		</a>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/directory', 'goodfor' ); ?>
</section>

<section class="wrap section section--top-flush">
	<div class="dir">
		<?php get_template_part( 'template-parts/directory', 'filters' ); ?>
		<?php // Both only come into play once the filters become a sheet on phones. ?>
		<div class="dirsheet__scrim" id="dirScrim"></div>
		<button class="btn btn--dark btn--block dirsheet__done" id="dirSheetDone" type="button"><?php esc_html_e( 'Show results', 'oria' ); ?></button>

		<div>
			<div class="dir__bar">
				<div style="flex:1;min-width:240px">
					<label class="sr-only" for="dirQ"><?php esc_html_e( 'Search listings', 'oria' ); ?></label>
					<input class="input" id="dirQ" type="search" placeholder="<?php esc_attr_e( 'Search a name, suburb or practice', 'oria' ); ?>">
				</div>
				<div class="dir__tools">
					<button class="btn btn--ghost btn--sm btn--plain" id="filterToggle" aria-expanded="true" aria-controls="dirFilters"><?php esc_html_e( 'Filters', 'oria' ); ?><span class="filtercount" id="filterCount" aria-hidden="true"></span></button>
					<label class="sr-only" for="dirSort"><?php esc_html_e( 'Sort by', 'oria' ); ?></label>
					<select class="select" id="dirSort" style="width:auto">
						<option value="relevance"><?php esc_html_e( 'Featured first', 'oria' ); ?></option>
						<option value="rating"><?php esc_html_e( 'Highest rated', 'oria' ); ?></option>
						<option value="reviews"><?php esc_html_e( 'Most reviewed', 'oria' ); ?></option>
						<option value="price"><?php esc_html_e( 'Lowest price', 'oria' ); ?></option>
						<option value="name"><?php esc_html_e( 'A–Z', 'oria' ); ?></option>
					</select>
				</div>
			</div>

			<?php // The results are the page's main section; without a heading
			// here the listing names (h3) jump straight from the h1. ?>
			<h2 class="sr-only"><?php esc_html_e( 'Practices in Perth', 'oria' ); ?></h2>
			<p class="dir__count" id="dirCount"></p>
			<div class="chips" id="dirChips" style="margin-top:1rem"></div>

			<div class="dir__results" id="dirResults">
				<?php
				/*
				 * Server-rendered fallback. app.js replaces this with the
				 * filtered view on load; crawlers and no-JS visitors see the
				 * full list.
				 */
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/listing', 'card' );
				endwhile;
				?>
			</div>
		</div>
	</div>
</section>

<?php
/*
 * Crawlable entry points for the specialty landing pages
 * (/perth/acupuncture/ and friends).
 */
$oria_spec_terms = get_terms(
	array(
		'taxonomy'   => 'specialty',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
if ( ! is_wp_error( $oria_spec_terms ) && $oria_spec_terms ) :
	?>
<section class="wrap section section--top-flush">
	<h2 class="micro" style="margin-bottom:1rem"><?php esc_html_e( 'Browse by specialty', 'oria' ); ?></h2>
	<div class="chips">
		<?php foreach ( $oria_spec_terms as $oria_s ) : ?>
			<a class="pill" href="<?php echo esc_url( (string) get_term_link( $oria_s ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_s ) . ' (' . (int) $oria_s->count . ')' ); ?></a>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php
// Below the results: a filter interface is not text, and this page had
// none of its own.
if ( function_exists( '\Oria\Core\Faq\site_faq' ) ) {
	get_template_part(
		'template-parts/faq',
		null,
		array(
			'faqs'    => \Oria\Core\Faq\site_faq(),
			'heading' => __( 'Common questions', 'oria' ),
		)
	);
}

get_footer();
