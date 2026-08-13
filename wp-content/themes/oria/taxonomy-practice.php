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
		<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Directory', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<?php if ( $oria_is_combo ) : ?>
			<a href="<?php echo esc_url( get_term_link( $oria_term ) ); ?>"><?php echo esc_html( $oria_pname ); ?></a>
			<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_aname ); ?></span>
		<?php else : ?>
			<span><?php echo esc_html( $oria_pname ); ?></span>
		<?php endif; ?>
	</nav>
	<div class="row-between" style="align-items:flex-end;margin-top:1rem">
		<div>
			<span class="micro"><?php esc_html_e( 'Practice', 'oria' ); ?></span>
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
		<?php if ( ! $oria_is_combo && $oria_term instanceof WP_Term && $oria_term->description ) : ?>
			<p class="lede" style="max-width:36ch"><?php echo esc_html( $oria_term->description ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php if ( ! $oria_is_combo && is_string( $oria_intro ) && '' !== trim( $oria_intro ) ) : ?>
<section class="wrap section section--top-flush">
	<div class="prose"><?php echo wp_kses_post( $oria_intro ); ?></div>
</section>
<?php endif; ?>

<?php
// Paid placement: featured listings in this practice lead the page.
$oria_feat = $oria_term instanceof WP_Term ? \Oria\Theme\featured_listings( 3, $oria_term->slug ) : array();
if ( $oria_feat ) :
	?>
<section class="wrap section section--top-flush">
	<?php
	get_template_part(
		'template-parts/featured',
		'band',
		array(
			'posts'   => $oria_feat,
			'heading' => sprintf( __( 'Featured in %s', 'oria' ), $oria_pname ),
		)
	);
	?>
</section>
<?php endif; ?>

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
 * The crawlable area mesh: every practice page links to its per-area
 * variants, which is what gets the combo pages discovered and indexed.
 * Regions always; suburbs once they hold at least two listings.
 */
if ( $oria_term instanceof WP_Term ) :
	$oria_counts  = \Oria\Theme\combo_counts( $oria_term->slug );
	$oria_regions = get_terms( array( 'taxonomy' => 'area', 'parent' => 0, 'hide_empty' => false ) );
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
		if ( $oria_n < 2 ) {
			continue;
		}
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
