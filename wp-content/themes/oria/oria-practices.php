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
			/*
			 * The rolled-up count, so a parent reports the pages beneath it
			 * rather than its own near-empty direct total.
			 */
			$oria_n = function_exists( '\Oria\Core\Intents\listings_in' )
				? count( \Oria\Core\Intents\listings_in( $oria_t ) )
				: (int) $oria_t->count;

			/*
			 * One row of links for every tile, from one rule: the category's own
			 * sub-categories first, topped up with its most-used styles.
			 *
			 * The styles alone were not safe to show. They are gathered from the
			 * terms the listings carry, so a breathwork studio that also runs a
			 * retreat put "Wellness retreats" on the Breathwork tile, and a day
			 * spa with a sauna put "Infrared sauna" on Beauty. Sub-categories are
			 * declared rather than inferred, so they go first and they are right.
			 */
			$oria_kids  = get_terms( array( 'taxonomy' => 'practice', 'parent' => $oria_t->term_id, 'hide_empty' => true ) );
			$oria_kids  = is_wp_error( $oria_kids ) ? array() : $oria_kids;
			$oria_row = array();
			foreach ( $oria_kids as $oria_k ) {
				$oria_row[] = array(
					'url'   => (string) get_term_link( $oria_k ),
					'label' => \Oria\Theme\tname( $oria_k ),
				);
			}
			foreach ( $oria_links as $oria_l ) {
				if ( count( $oria_row ) >= 5 ) {
					break;
				}
				$oria_row[] = array( 'url' => $oria_l['url'], 'label' => $oria_l['label'] );
			}
			?>
			<article class="card ptile">
				<?php if ( $oria_img ) : ?>
					<a class="ptile__media" href="<?php echo esc_url( \Oria\Core\PracticesIndex\tile_url( $oria_t ) ); ?>" tabindex="-1" aria-hidden="true">
						<?php echo wp_get_attachment_image( $oria_img, 'oria-card', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
					</a>
				<?php endif; ?>
				<div class="card__body">
					<?php
					/*
					 * Every tile has the same four parts in the same order:
					 * heading, blurb, one count, one row of links.
					 *
					 * It varied three ways before. A parent's name appeared above
					 * the seven child categories and above nothing else. The count
					 * switched units between "18 listings" and "2 sub-categories".
					 * And a card could carry a chip row AND an intent list, or one,
					 * or neither. Twenty-three tiles, no two built alike.
					 */
					?>
					<h2 class="h3"><a href="<?php echo esc_url( \Oria\Core\PracticesIndex\tile_url( $oria_t ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_t ) ); ?></a></h2>
					<?php if ( '' !== $oria_blurb ) : ?>
						<p class="muted" style="margin-top:.4rem"><?php echo esc_html( wp_trim_words( $oria_blurb, 28 ) ); ?></p>
					<?php endif; ?>
					<p class="hint" style="margin-top:.5rem">
						<?php
						/*
						 * Always listings, never sub-categories. A parent term holds
						 * few listings of its own — Spa & Recovery holds none — so its
						 * own count read as an empty category while the pages beneath
						 * it are among the fullest on the site. The rolled-up figure is
						 * the one a reader is actually asking for.
						 */
						/* translators: %s: number of listings */
						printf( esc_html( _n( '%s listing', '%s listings', $oria_n, 'oria' ) ), esc_html( number_format_i18n( $oria_n ) ) );
						?>
					</p>
					<?php if ( $oria_row ) : ?>
						<div class="chips" style="margin-top:.6rem">
							<?php foreach ( $oria_row as $oria_l ) : ?>
								<a class="pill" href="<?php echo esc_url( $oria_l['url'] ); ?>"><?php echo esc_html( $oria_l['label'] ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<?php
get_footer();
