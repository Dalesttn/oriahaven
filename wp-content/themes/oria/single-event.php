<?php
/**
 * A single event.
 */

declare(strict_types=1);

use function Oria\Theme\arrow;

get_header();

while ( have_posts() ) :
	the_post();
	$oria_start   = (string) get_field( 'event_start' );
	$oria_end     = (string) get_field( 'event_end' );
	$oria_ts      = $oria_start ? strtotime( $oria_start ) : false;
	$oria_te      = $oria_end ? strtotime( $oria_end ) : false;
	$oria_price   = (string) get_field( 'price' );
	$oria_venue   = (string) get_field( 'venue' );
	$oria_booking = (string) get_field( 'booking_url' );
	$oria_listing = get_field( 'listing' );
	$oria_desc    = (string) get_field( 'event_description' );
	$oria_gallery = array_values( array_filter( array_map( 'intval', (array) get_field( 'event_gallery' ) ) ) );

	// The hero: the main photo, or failing that the gallery's lead image.
	$oria_hero_id = has_post_thumbnail() ? (int) get_post_thumbnail_id() : ( $oria_gallery[0] ?? 0 );
	$oria_grid    = array_values( array_diff( $oria_gallery, array( $oria_hero_id ) ) );
	?>

	<section class="wrap pagehead">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ); ?>"><?php esc_html_e( 'Workshops/Events', 'oria' ); ?></a>
			<span aria-hidden="true">/</span><span><?php the_title(); ?></span>
		</nav>
		<div class="row-between" style="align-items:flex-end;margin-top:1rem">
			<div>
				<?php if ( $oria_ts ) : ?>
					<span class="micro"><?php echo esc_html( gmdate( 'l j F Y', $oria_ts ) ); ?></span>
				<?php endif; ?>
				<h1 class="h1 pagehead__title"><?php the_title(); ?></h1>
			</div>
		</div>
	</section>

	<section class="wrap" style="padding-bottom:var(--s-6)">
		<?php if ( $oria_hero_id ) : ?>
			<div class="evhero"><?php echo wp_get_attachment_image( $oria_hero_id, 'oria-wide' ); ?></div>
		<?php else : ?>
			<?php get_template_part( 'template-parts/event', 'art', array( 'event_id' => get_the_ID() ) ); ?>
		<?php endif; ?>
	</section>

	<section class="wrap section section--top-flush">
		<div class="profile">
			<div class="stack-lg">
				<div class="prose">
					<?php the_content(); ?>
					<?php if ( '' !== trim( $oria_desc ) ) : ?>
						<?php echo wp_kses_post( $oria_desc ); ?>
					<?php endif; ?>
				</div>
				<?php if ( $oria_grid ) : ?>
					<div class="evgallery">
						<?php foreach ( $oria_grid as $oria_gid ) : ?>
							<?php echo wp_get_attachment_image( $oria_gid, 'oria-card', false, array( 'loading' => 'lazy' ) ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<aside class="aside">
				<div class="keyfacts" style="grid-template-columns:1fr 1fr">
					<?php if ( $oria_ts ) : ?>
						<div><div class="keyfact__k"><?php esc_html_e( 'When', 'oria' ); ?></div><div class="keyfact__v"><?php echo esc_html( gmdate( 'D j M, g.ia', $oria_ts ) . ( $oria_te ? '–' . gmdate( 'g.ia', $oria_te ) : '' ) ); ?></div></div>
					<?php endif; ?>
					<?php if ( $oria_price ) : ?>
						<div><div class="keyfact__k"><?php esc_html_e( 'Price', 'oria' ); ?></div><div class="keyfact__v"><?php echo esc_html( $oria_price ); ?></div></div>
					<?php endif; ?>
					<?php if ( $oria_venue ) : ?>
						<div><div class="keyfact__k"><?php esc_html_e( 'Where', 'oria' ); ?></div><div class="keyfact__v"><?php echo esc_html( $oria_venue ); ?></div></div>
					<?php endif; ?>
				</div>

				<?php if ( $oria_booking ) : ?>
					<a class="btn btn--dark btn--block" href="<?php echo esc_url( $oria_booking ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'Book / details', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></a>
				<?php endif; ?>

				<?php
				/*
				 * Who runs this: the linked listing as a small card back into
				 * the directory.
				 */
				if ( $oria_listing ) :
					$oria_host_id  = (int) $oria_listing;
					$oria_host_img = \Oria\Theme\listing_image( $oria_host_id );
					$oria_host_bits = array();
					foreach ( wp_get_post_terms( $oria_host_id, 'area' ) as $oria_ht ) {
						if ( $oria_ht->parent ) {
							$oria_host_bits[] = \Oria\Theme\tname( $oria_ht );
							break;
						}
					}
					$oria_host_p = wp_get_post_terms( $oria_host_id, 'practice' );
					if ( ! is_wp_error( $oria_host_p ) && $oria_host_p ) {
						$oria_host_bits[] = \Oria\Theme\tname( $oria_host_p[0] );
					}
					?>
					<a class="hostcard" href="<?php echo esc_url( get_permalink( $oria_host_id ) ); ?>">
						<?php if ( $oria_host_img ) : ?>
							<img class="hostcard__img" src="<?php echo esc_url( $oria_host_img ); ?>" alt="<?php echo esc_attr( \Oria\Theme\ptitle( get_post( $oria_host_id ) ) ); ?>" loading="lazy" onerror="this.style.display='none'">
						<?php endif; ?>
						<span class="hostcard__body">
							<span class="micro"><?php esc_html_e( 'Run by', 'oria' ); ?></span>
							<b class="hostcard__name"><?php echo esc_html( \Oria\Theme\ptitle( $oria_host_id ) ); ?></b>
							<?php if ( $oria_host_bits ) : ?>
								<span class="hostcard__meta"><?php echo esc_html( implode( ' · ', $oria_host_bits ) ); ?></span>
							<?php endif; ?>
						</span>
						<span class="hostcard__go" aria-hidden="true"><?php echo arrow(); // phpcs:ignore ?></span>
					</a>
				<?php endif; ?>
			</aside>
		</div>
	</section>
	<?php
endwhile;

get_footer();
