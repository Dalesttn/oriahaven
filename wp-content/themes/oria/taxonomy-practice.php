<?php
/**
 * A practice landing page (/practice/meditation/), and — when the oria_area
 * query var is present — the practice × area combo landing page
 * (/practice/bodywork/freo/ → "Bodywork & Massage in Fremantle & South").
 * Both are the directory engine with facets locked; the combo also locks
 * the area, at region or suburb level.
 */

declare(strict_types=1);

get_header();

$oria_term  = get_queried_object();
$oria_intro = $oria_term instanceof WP_Term ? get_field( 'landing_intro', 'practice_' . $oria_term->term_id ) : '';

$oria_area_slug = (string) get_query_var( \Oria\Core\Seo\QUERY_VAR );
$oria_area      = '' !== $oria_area_slug ? get_term_by( 'slug', $oria_area_slug, 'area' ) : false;
$oria_area      = $oria_area instanceof WP_Term ? $oria_area : null;
$oria_is_combo  = null !== $oria_area;

$oria_pname = \Oria\Theme\tname( $oria_term );
$oria_aname = $oria_area ? \Oria\Theme\tname( $oria_area ) : '';
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Explore', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<?php if ( $oria_is_combo ) : ?>
			<a href="<?php echo esc_url( get_term_link( $oria_term ) ); ?>"><?php echo esc_html( $oria_pname ); ?></a>
			<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_aname ); ?></span>
		<?php else : ?>
			<span><?php echo esc_html( $oria_pname ); ?></span>
		<?php endif; ?>
	</nav>
	<div style="margin-top:1rem">
		<span class="micro"><?php esc_html_e( 'Category', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title">
			<?php
			if ( $oria_is_combo ) {
				printf( esc_html__( '%1$s in %2$s', 'oria' ), esc_html( $oria_pname ), esc_html( $oria_aname ) );
			} else {
				printf( esc_html__( '%s in Perth', 'oria' ), esc_html( $oria_pname ) );
			}
			?>
		</h1>
	</div>
	<?php
	/*
	 * The term description is not printed here any more. It still earns its
	 * keep as the page's meta description (see Oria\Core\Seo), but on the
	 * page it only restated the first line of the introduction below while
	 * forcing the header into two columns — which left the introduction
	 * stranded in a narrow left-hand column with the right half empty.
	 */
	?>
</section>

<?php
/*
 * The page's own facts, ahead of both the editorial intro and the filter
 * rail. The intro gives the category its context; this answers the
 * question somebody actually typed, which is the sentence worth first.
 *
 * Combos get one too, unlike the FAQ block at the foot of this file. The
 * FAQ is skipped there because its questions are answered across the whole
 * metro and would read as a lie once the page has narrowed to a suburb.
 * This block takes the area as a second facet and counts within it, so on
 * /practice/yoga/freo/ the numbers describe Fremantle and nowhere else.
 */
// Paid placement, resolved here so the answer block can set it beside the
// facts rather than in a band of its own further down the page.
$oria_feat = ( $oria_term instanceof WP_Term && ! $oria_is_combo )
	? \Oria\Theme\featured_listings( 3, $oria_term->slug )
	: array();

get_template_part(
	'template-parts/answer',
	'block',
	array(
		'term'             => $oria_term,
		'area'             => $oria_area,
		'featured'         => $oria_feat,
		'featured_heading' => sprintf( __( 'Featured in %s', 'oria' ), $oria_pname ),
	)
);
?>
<?php
/*
 * Intent rows, under the answer block and above the editorial intro.
 *
 * Combos are skipped: the counts are category-wide and would be wrong the
 * moment the page narrows to one suburb, which is the same reason the FAQ
 * block sits out combo pages.
 */
if ( ! $oria_is_combo ) {
	get_template_part( 'template-parts/intent', 'rows', array( 'term' => $oria_term ) );
}
?>
<section class="wrap section section--top-flush">
	<div class="dir">
		<?php get_template_part( 'template-parts/directory', 'filters' ); ?>
		<div>
			<div class="dir__bar">
				<div style="flex:1;min-width:240px">
					<label class="sr-only" for="dirQ"><?php esc_html_e( 'Search', 'oria' ); ?></label>
					<input class="input" id="dirQ" type="search" placeholder="<?php printf( esc_attr__( 'Search within %s', 'oria' ), esc_attr( strtolower( $oria_pname ) ) ); ?>">
				</div>
				<div class="dir__tools">
					<button class="btn btn--ghost btn--sm btn--plain" id="filterToggle" aria-expanded="true" aria-controls="dirFilters"><?php esc_html_e( 'Filters', 'oria' ); ?></button>
					<label class="sr-only" for="dirSort"><?php esc_html_e( 'Sort by', 'oria' ); ?></label>
					<select class="select" id="dirSort" style="width:auto">
						<option value="relevance"><?php esc_html_e( 'Featured first', 'oria' ); ?></option>
						<option value="rating"><?php esc_html_e( 'Highest rated', 'oria' ); ?></option>
						<option value="price"><?php esc_html_e( 'Lowest price', 'oria' ); ?></option>
						<option value="name"><?php esc_html_e( 'A–Z', 'oria' ); ?></option>
					</select>
				</div>
			</div>
			<?php // See archive-listing.php: gives the listing h3s a parent. ?>
			<h2 class="sr-only"><?php printf( /* translators: %s: practice name */ esc_html__( '%s in Perth', 'oria' ), esc_html( $oria_pname ) ); ?></h2>
			<p class="dir__count" id="dirCount"></p>
			<?php
			/*
			 * The second level: what actually narrows this category. Counted
			 * from the listings in it rather than listed from the taxonomy,
			 * so no tag here can empty the page.
			 *
			 * Rendered as checkboxes wearing pills, sharing data-filter="spec"
			 * with the sidebar. That means no new JavaScript, and — because
			 * the script writes every [data-filter] input back from state —
			 * a tag and its sidebar checkbox can never disagree.
			 *
			 * Combos are excluded: /practice/massage/fremantle/ has already
			 * narrowed once, and a second row of narrowing reads as clutter.
			 */
			$oria_specs = ( ! $oria_is_combo && $oria_term instanceof WP_Term && function_exists( '\Oria\Core\Categories\specialties_for' ) )
				? \Oria\Core\Categories\specialties_for( $oria_term )
				: array();
			?>
			<?php if ( count( $oria_specs ) > 1 ) : ?>
				<div class="spectags" role="group" aria-label="<?php esc_attr_e( 'Narrow by specialty', 'oria' ); ?>">
					<?php foreach ( $oria_specs as $oria_spec ) : ?>
						<label class="spectag">
							<input type="checkbox" data-filter="spec" value="<?php echo esc_attr( $oria_spec['term']->slug ); ?>">
							<span><?php echo esc_html( \Oria\Theme\tname( $oria_spec['term'] ) ); ?><b><?php echo esc_html( (string) $oria_spec['count'] ); ?></b></span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="chips" id="dirChips" style="margin-top:1rem"></div>
			<div
				class="dir__results"
				id="dirResults"
				data-cat="<?php echo esc_attr( $oria_term instanceof WP_Term ? $oria_term->slug : '' ); ?>"
				<?php if ( $oria_area && 0 === $oria_area->parent ) : ?>
					data-region="<?php echo esc_attr( $oria_area->slug ); ?>"
				<?php elseif ( $oria_area ) : ?>
					data-suburb="<?php echo esc_attr( $oria_aname ); ?>"
				<?php endif; ?>
			>
				<?php
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
 * The category introduction, below the results rather than above them.
 *
 * It used to sit immediately before the listings, which made a 197-word
 * essay the last thing between somebody who searched for a category and
 * the practices in it. It is good context and a poor gatekeeper. Below
 * the results it is still crawlable, still in the document, and no
 * longer standing in the way.
 */
?>
<?php if ( ! $oria_is_combo && is_string( $oria_intro ) && '' !== trim( $oria_intro ) ) : ?>
<section class="wrap section section--top-flush">
	<div class="prose prose--intro"><?php echo wp_kses_post( $oria_intro ); ?></div>
</section>
<?php endif; ?>


<?php
/*
 * The crawlable area mesh: every practice page links to its per-area
 * variants, which is what gets the combo pages discovered and indexed.
 * Regions always; suburbs from their first listing. The threshold used
 * to be two, which left every single-listing combo indexable but linked
 * from exactly one page — the site audit flagged 34 of them. A combo
 * either deserves the mesh or deserves noindex, and the directory
 * already keeps single-listing suburb pages, so it gets the mesh.
 */
if ( $oria_term instanceof WP_Term ) :
	$oria_counts  = \Oria\Theme\combo_counts( $oria_term->slug );
	$oria_regions = \Oria\Core\Taxonomies\regions();
	$oria_regions = is_wp_error( $oria_regions ) ? array() : $oria_regions;
	$oria_links   = array();

	foreach ( $oria_regions as $oria_r ) {
		$oria_n = (int) ( $oria_counts['regions'][ $oria_r->slug ] ?? 0 );
		if ( $oria_n > 0 && ( ! $oria_area || $oria_r->slug !== $oria_area->slug ) ) {
			$oria_links[] = array(
				home_url( '/practice/' . $oria_term->slug . '/' . $oria_r->slug . '/' ),
				sprintf( '%s (%d)', \Oria\Theme\tname( $oria_r ), $oria_n ),
			);
		}
	}
	foreach ( $oria_counts['suburbs'] as $oria_sname => $oria_n ) {
		$oria_s = get_term_by( 'slug', sanitize_title( $oria_sname ), 'area' );
		if ( $oria_s instanceof WP_Term && 0 !== $oria_s->parent && ( ! $oria_area || $oria_s->slug !== $oria_area->slug ) ) {
			$oria_links[] = array(
				home_url( '/practice/' . $oria_term->slug . '/' . $oria_s->slug . '/' ),
				sprintf( '%s (%d)', \Oria\Theme\tname( $oria_s ), $oria_n ),
			);
		}
	}

	if ( $oria_links ) :
		?>
<section class="wrap section section--top-flush">
	<h2 class="micro" style="margin-bottom:1rem"><?php printf( esc_html__( '%s by area', 'oria' ), esc_html( $oria_pname ) ); ?></h2>
	<div class="chips">
		<?php foreach ( $oria_links as $oria_l ) : ?>
			<a class="pill" href="<?php echo esc_url( $oria_l[0] ); ?>"><?php echo esc_html( $oria_l[1] ); ?></a>
		<?php endforeach; ?>
	</div>
</section>
	<?php endif; ?>
<?php endif; ?>

<?php
/*
 * Combos are deliberately skipped: the generated questions are answered
 * across the whole metro ("how many in Perth"), which would read as a
 * lie on a page that has already narrowed to one suburb.
 */
if ( ! $oria_is_combo && $oria_term instanceof WP_Term ) {
	get_template_part(
		'template-parts/faq',
		null,
		array(
			'term'    => $oria_term,
			'heading' => sprintf( __( '%s in Perth — common questions', 'oria' ), $oria_pname ),
		)
	);
}
?>

<?php $oria_shop = function_exists( '\Oria\Shop\Render\auto_band' ) ? \Oria\Shop\Render\auto_band() : ''; ?>
<?php if ( $oria_shop ) : ?>
<section class="wrap section section--top-flush"><?php echo $oria_shop; // phpcs:ignore WordPress.Security.EscapeOutput ?></section>
<?php endif; ?>

<?php
get_footer();
