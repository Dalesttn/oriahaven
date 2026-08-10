<?php
/**
 * Every page, including the front page, renders its "Sections" flexible
 * content in order. A page with no sections falls back to the classic
 * title-plus-content layout, so nothing is ever blank.
 */

declare(strict_types=1);

get_header();

while ( have_posts() ) :
	the_post();

	$oria_sections = function_exists( 'get_field' ) ? get_field( 'sections' ) : null;

	if ( is_array( $oria_sections ) && $oria_sections ) {
		foreach ( $oria_sections as $oria_index => $oria_section ) {
			$oria_layout = (string) ( $oria_section['acf_fc_layout'] ?? '' );
			if ( '' === $oria_layout ) {
				continue;
			}
			get_template_part(
				'template-parts/sections/' . str_replace( '_', '-', $oria_layout ),
				null,
				array(
					's' => $oria_section,
					'i' => $oria_index,
				)
			);

			/*
			 * The "get matched" band rides the front page automatically,
			 * straight after the practice tiles — the visitor has just seen
			 * the breadth of the directory, and this is the shortcut through
			 * it. Code-injected rather than an ACF section so it ships to
			 * production with git pull, no page editing required.
			 */
			if ( is_front_page() && 'practice_tiles' === $oria_layout ) {
				get_template_part( 'template-parts/sections/get-matched' );
			}
		}
	} else {
		?>
		<section class="wrap pagehead">
			<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
				<span aria-hidden="true">/</span><span><?php the_title(); ?></span>
			</nav>
			<h1 class="h1 pagehead__title" style="margin-top:1rem"><?php the_title(); ?></h1>
		</section>
		<section class="wrap section section--top-flush">
			<div class="prose" style="max-width:44rem"><?php the_content(); ?></div>
		</section>
		<?php
	}
endwhile;

get_footer();
