<?php
/**
 * An area landing page — a region (/area/fremantle-south/) or a suburb
 * beneath it. The directory engine with the region locked.
 */

declare(strict_types=1);

get_header();

$oria_term   = get_queried_object();
$oria_region = $oria_term instanceof WP_Term ? \Oria\Core\Taxonomies\region_for( $oria_term ) : null;
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Directory', 'oria' ); ?></a>
		<?php if ( $oria_region && $oria_term instanceof WP_Term && $oria_region->term_id !== $oria_term->term_id ) : ?>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( (string) get_term_link( $oria_region ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_region ) ); ?></a>
		<?php endif; ?>
		<span aria-hidden="true">/</span><span><?php echo esc_html( \Oria\Theme\tname( get_queried_object() ) ); ?></span>
	</nav>
	<div class="row-between" style="align-items:flex-end;margin-top:1rem">
		<div>
			<span class="micro"><?php esc_html_e( 'Area guide', 'oria' ); ?></span>
			<h1 class="h1 pagehead__title"><?php printf( esc_html__( 'Meditation & wellness in %s', 'oria' ), esc_html( \Oria\Theme\tname( get_queried_object() ) ) ); ?></h1>
		</div>
		<?php if ( $oria_term instanceof WP_Term && $oria_term->description ) : ?>
			<p class="lede" style="max-width:36ch"><?php echo esc_html( $oria_term->description ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="wrap section section--top-flush">
	<div class="dir">
		<?php get_template_part( 'template-parts/directory', 'filters' ); ?>
		<div>
			<div class="dir__bar">
				<div style="flex:1;min-width:240px">
					<label class="sr-only" for="dirQ"><?php esc_html_e( 'Search this area', 'oria' ); ?></label>
					<input class="input" id="dirQ" type="search" placeholder="<?php printf( esc_attr__( 'Search within %s', 'oria' ), esc_attr( \Oria\Theme\tname( get_queried_object() ) ) ); ?>">
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
			<p class="dir__count" id="dirCount"></p>
			<div class="chips" id="dirChips" style="margin-top:1rem"></div>
			<div class="dir__results" id="dirResults" data-region="<?php echo esc_attr( $oria_region ? $oria_region->slug : '' ); ?>">
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
 * The other half of the crawl mesh: each area page links to the practice ×
 * area combo pages that actually have listings here.
 */
if ( $oria_term instanceof WP_Term && $oria_region ) :
	$oria_practices = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false ) );
	$oria_practices = is_wp_error( $oria_practices ) ? array() : $oria_practices;
	$oria_links     = array();

	foreach ( $oria_practices as $oria_p ) {
		$oria_counts = \Oria\Theme\combo_counts( $oria_p->slug );
		$oria_n      = ( 0 === $oria_term->parent )
			? (int) ( $oria_counts['regions'][ $oria_term->slug ] ?? 0 )
			: (int) ( $oria_counts['suburbs'][ \Oria\Theme\tname( $oria_term ) ] ?? 0 );
		if ( $oria_n > 0 ) {
			$oria_links[] = array(
				home_url( '/practice/' . $oria_p->slug . '/' . $oria_term->slug . '/' ),
				sprintf( '%s (%d)', \Oria\Theme\tname( $oria_p ), $oria_n ),
			);
		}
	}

	if ( $oria_links ) :
		?>
<section class="wrap section section--top-flush">
	<h2 class="micro" style="margin-bottom:1rem"><?php printf( esc_html__( 'Browse %s by practice', 'oria' ), esc_html( \Oria\Theme\tname( $oria_term ) ) ); ?></h2>
	<div class="chips">
		<?php foreach ( $oria_links as $oria_l ) : ?>
			<a class="pill" href="<?php echo esc_url( $oria_l[0] ); ?>"><?php echo esc_html( $oria_l[1] ); ?></a>
		<?php endforeach; ?>
	</div>
</section>
	<?php endif; ?>
<?php endif; ?>

<?php
if ( $oria_term instanceof WP_Term ) {
	get_template_part(
		'template-parts/faq',
		null,
		array(
			'term'    => $oria_term,
			'heading' => sprintf( __( 'Wellness in %s — common questions', 'oria' ), \Oria\Theme\tname( $oria_term ) ),
		)
	);
}

get_footer();
