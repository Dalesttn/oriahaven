<?php
/**
 * Section: featured listings
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

// Every featured listing gets its turn: a random order each page view,
// shown three at a time, rotating every 30 seconds when there are more.
$oria_featured = \Oria\Theme\featured_listings( 24 );
if ( ! $oria_featured ) {
	return; // Hides itself until something is marked featured.
}
shuffle( $oria_featured );

// Full rows only: a short final group wraps around to the front of the
// shuffled order, so the section never rotates to a lone card.
$oria_total = count( $oria_featured );
if ( $oria_total > 3 ) {
	$oria_groups = array();
	for ( $oria_i = 0; $oria_i < $oria_total; $oria_i += 3 ) {
		$oria_g = array_slice( $oria_featured, $oria_i, 3 );
		$oria_j = 0;
		while ( count( $oria_g ) < 3 ) {
			$oria_g[] = $oria_featured[ $oria_j++ ];
		}
		$oria_groups[] = $oria_g;
	}
} else {
	$oria_groups = array( $oria_featured );
}
$oria_dir    = get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' );
?>
<section class="section">
	<div class="wrap">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
				<h2 class="h2"><?php echo esc_html( $t('heading') ); ?></h2>
			</div>
			<div class="sec-head__aside">
				<?php if ( $t('aside') ) : ?><p class="muted" style="margin-bottom:1rem"><?php echo esc_html( $t('aside') ); ?></p><?php endif; ?>
				<a class="btn btn--ghost" href="<?php echo esc_url( $oria_dir ); ?>"><?php esc_html_e( 'See all listings', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></a>
			</div>
		</div>

		<div class="featrotator" data-rotate="30000">
			<?php foreach ( $oria_groups as $oria_gi => $oria_group ) : ?>
			<div class="grid grid-3 featrotator__group<?php echo 0 === $oria_gi ? ' is-active' : ''; ?>"<?php echo 0 !== $oria_gi ? ' hidden' : ''; ?>>
				<?php foreach ( $oria_group as $oria_post ) :
					$oria_areas = wp_get_post_terms( $oria_post->ID, 'area' );
					$oria_sub   = '';
					foreach ( $oria_areas as $oria_a ) {
						$oria_sub = \Oria\Theme\tname( $oria_a );
						if ( $oria_a->parent ) { break; }
					}
					$oria_rating = (float) get_field( 'rating', $oria_post->ID );
					$oria_price  = get_field( 'price_from', $oria_post->ID );
					$oria_img    = \Oria\Theme\listing_image( $oria_post->ID, 'oria-portrait' );
					?>
					<a class="mediacard reveal" href="<?php echo esc_url( get_permalink( $oria_post ) ); ?>" style="--ar:3/4">
						<img class="mediacard__img" src="<?php echo esc_url( $oria_img ); ?>" alt="" loading="lazy"
							onerror="this.onerror=null;this.src='<?php echo esc_js( \Oria\Theme\listing_scene( $oria_post->ID ) ); ?>'">
						<div class="mediacard__top">
							<span class="badge badge--featured"><span class="badge-dot"></span><?php esc_html_e( 'Featured', 'oria' ); ?></span>
							<?php if ( $oria_rating > 0 ) : ?>
								<span class="pill pill--glass"><svg viewBox="0 0 16 16" fill="currentColor" style="width:12px;height:12px;color:#E8C874"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg><?php echo esc_html( number_format_i18n( $oria_rating, 1 ) ); ?></span>
							<?php endif; ?>
						</div>
						<div class="mediacard__over">
							<div class="mediacard__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_post ) ); ?></div>
							<div class="mediacard__meta"><?php echo esc_html( trim( $oria_sub . ( (int) $oria_price > 0 ? ' - from $' . (int) $oria_price : '' ), ' -' ) ); ?></div>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
