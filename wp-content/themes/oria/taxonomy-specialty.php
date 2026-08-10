<?php
/**
 * A specialty landing page (/perth/acupuncture/ — "Acupuncture in Perth").
 * The directory engine with this specialty locked; the term description
 * doubles as the intro and the meta description.
 */

declare(strict_types=1);

get_header();

$oria_term = get_queried_object();
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Directory', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php echo esc_html( \Oria\Theme\tname( $oria_term ) ); ?></span>
	</nav>
	<div class="row-between" style="align-items:flex-end;margin-top:1rem">
		<div>
			<span class="micro"><?php esc_html_e( 'Specialty', 'oria' ); ?></span>
			<h1 class="h1 pagehead__title"><?php printf( esc_html__( '%s in Perth', 'oria' ), esc_html( \Oria\Theme\tname( $oria_term ) ) ); ?></h1>
		</div>
		<?php if ( $oria_term instanceof WP_Term && $oria_term->description ) : ?>
			<p class="lede" style="max-width:36ch"><?php echo esc_html( wp_specialchars_decode( $oria_term->description, ENT_QUOTES ) ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="wrap section section--top-flush">
	<div class="dir">
		<?php get_template_part( 'template-parts/directory', 'filters' ); ?>
		<div>
			<div class="dir__bar">
				<div style="flex:1;min-width:240px">
					<label class="sr-only" for="dirQ"><?php esc_html_e( 'Search', 'oria' ); ?></label>
					<input class="input" id="dirQ" type="search" placeholder="<?php printf( esc_attr__( 'Search within %s', 'oria' ), esc_attr( strtolower( \Oria\Theme\tname( $oria_term ) ) ) ); ?>">
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
			<div class="dir__results" id="dirResults" data-spec="<?php echo esc_attr( $oria_term instanceof WP_Term ? $oria_term->slug : '' ); ?>">
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
if ( $oria_term instanceof WP_Term ) {
	get_template_part(
		'template-parts/faq',
		null,
		array(
			'term'    => $oria_term,
			'heading' => sprintf( __( '%s in Perth — common questions', 'oria' ), \Oria\Theme\tname( $oria_term ) ),
		)
	);
}

get_footer();
