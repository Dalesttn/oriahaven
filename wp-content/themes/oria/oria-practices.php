<?php
/**
 * /practices/ — every practice category as a tile, with the intent pages
 * beneath each where they are allowed to be seen. See
 * Oria\Core\PracticesIndex.
 */

declare(strict_types=1);

get_header();

$oria_terms = \Oria\Core\PracticesIndex\practices();
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Practices', 'oria' ); ?></span>
	</nav>
	<div style="margin-top:1rem;max-width:44rem">
		<span class="micro"><?php esc_html_e( 'Practices', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title"><?php esc_html_e( 'Wellness practices in Perth', 'oria' ); ?></h1>
		<p class="lede pagehead__lede">
			<?php esc_html_e( 'Every category the directory lists, and the pages inside each. Pick the practice first; the suburb, the price and the style come after.', 'oria' ); ?>
		</p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<div class="ptiles">
		<?php
		foreach ( $oria_terms as $oria_t ) :
			$oria_blurb = trim( (string) ( get_field( 'tile_blurb', 'practice_' . $oria_t->term_id ) ?: $oria_t->description ) );
			$oria_img   = (int) get_field( 'tile_image', 'practice_' . $oria_t->term_id );
			$oria_links = \Oria\Core\PracticesIndex\intent_links( $oria_t );
			$oria_n     = (int) $oria_t->count;
			$oria_kids  = get_terms( array( 'taxonomy' => 'practice', 'parent' => $oria_t->term_id, 'hide_empty' => true ) );
			$oria_kids  = is_wp_error( $oria_kids ) ? array() : $oria_kids;
			?>
			<article class="card ptile">
				<?php if ( $oria_img ) : ?>
					<a class="ptile__media" href="<?php echo esc_url( \Oria\Core\PracticesIndex\tile_url( $oria_t ) ); ?>" tabindex="-1" aria-hidden="true">
						<?php echo wp_get_attachment_image( $oria_img, 'oria-card', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
					</a>
				<?php endif; ?>
				<div class="card__body">
					<?php if ( $oria_t->parent ) : ?>
						<?php $oria_parent = get_term( $oria_t->parent, 'practice' ); ?>
						<?php if ( $oria_parent instanceof WP_Term ) : ?>
							<span class="micro"><?php echo esc_html( \Oria\Theme\tname( $oria_parent ) ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
					<h2 class="h3" style="margin-top:.25rem"><a href="<?php echo esc_url( \Oria\Core\PracticesIndex\tile_url( $oria_t ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_t ) ); ?></a></h2>
					<?php if ( '' !== $oria_blurb ) : ?>
						<p class="muted" style="margin-top:.4rem"><?php echo esc_html( wp_trim_words( $oria_blurb, 28 ) ); ?></p>
					<?php endif; ?>
					<p class="hint" style="margin-top:.5rem">
						<?php
						if ( $oria_n > 0 ) {
							/* translators: %s: number of listings */
							printf( esc_html( _n( '%s listing', '%s listings', $oria_n, 'oria' ) ), esc_html( number_format_i18n( $oria_n ) ) );
						} elseif ( $oria_kids ) {
							/* translators: %s: number of sub-categories */
							printf( esc_html( _n( '%s sub-category', '%s sub-categories', count( $oria_kids ), 'oria' ) ), esc_html( number_format_i18n( count( $oria_kids ) ) ) );
						}
						?>
					</p>
					<?php if ( $oria_kids ) : ?>
						<div class="chips" style="margin-top:.6rem">
							<?php foreach ( $oria_kids as $oria_k ) : ?>
								<a class="pill" href="<?php echo esc_url( (string) get_term_link( $oria_k ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_k ) ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $oria_links ) : ?>
						<ul class="ptile__intents">
							<?php foreach ( $oria_links as $oria_l ) : ?>
								<li><a href="<?php echo esc_url( $oria_l['url'] ); ?>"><?php echo esc_html( $oria_l['label'] ); ?></a> <em><?php echo esc_html( number_format_i18n( $oria_l['count'] ) ); ?></em></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<?php
get_footer();
