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
					/*
					 * effective_rating(), not get_field('rating').
					 *
					 * The raw field holds only ratings collected here, and no
					 * featured listing has one -- so every card was hiding a
					 * rating it actually had. Nine of the ten carry a Google
					 * score, and this helper returns that as the fallback.
					 */
					$oria_rated = \Oria\Theme\effective_rating( $oria_post->ID );

					/*
					 * Bands, not price_from. That field runs from $8 to $1666
					 * across the directory and means a single session on one
					 * listing and a whole retreat on the next, so "from $59"
					 * on a card is a number the reader cannot use. The band is
					 * set on all ten of these; price_from on one.
					 */
					$oria_band  = (string) get_field( 'price_band', $oria_post->ID );

					// What the place is good for, from its own services and
					// specialties -- the same derivation the directory uses.
					$oria_wants = function_exists( '\Oria\Core\GoodFor\for_listing' )
						? \Oria\Core\GoodFor\for_listing( $oria_post->ID, 2 )
						: array();
					$oria_img    = \Oria\Theme\listing_image( $oria_post->ID, 'oria-portrait' );
					?>
					<a class="mediacard reveal" href="<?php echo esc_url( get_permalink( $oria_post ) ); ?>" style="--ar:3/4">
						<img class="mediacard__img" src="<?php echo esc_url( $oria_img ); ?>" alt="<?php echo esc_attr( \Oria\Theme\listing_alt( $oria_post->ID ) ); ?>" loading="lazy"
							onerror="this.onerror=null;this.src='<?php echo esc_js( \Oria\Theme\listing_scene( $oria_post->ID ) ); ?>'">
						<div class="mediacard__top">
							<span class="badge badge--featured"><span class="badge-dot"></span><?php esc_html_e( 'Featured', 'oria' ); ?></span>
						</div>
						<div class="mediacard__over">
							<div class="mediacard__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_post ) ); ?></div>

							<div class="mediacard__meta">
								<?php
								$oria_bits = array_filter( array( $oria_sub, $oria_band ) );
								echo esc_html( implode( '  ·  ', $oria_bits ) );
								?>
							</div>

							<?php if ( $oria_rated['rating'] > 0 ) : ?>
								<div class="mediacard__rating">
									<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>
									<b><?php echo esc_html( number_format_i18n( $oria_rated['rating'], 1 ) ); ?></b>
									<?php if ( $oria_rated['count'] > 0 ) : ?>
										<?php
										/*
										 * Never an unattributed star -- the same rule the
										 * directory cards hold. A rating is either ours or
										 * Google's and it always says which.
										 */
										$oria_src = 'google' === $oria_rated['source']
											? __( 'Google', 'oria' )
											: __( 'Oria Haven', 'oria' );
										?>
										<span>(<?php echo esc_html( (string) $oria_rated['count'] ); ?> · <?php echo esc_html( $oria_src ); ?>)</span>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<?php if ( $oria_wants ) : ?>
								<div class="mediacard__tags">
									<?php foreach ( $oria_wants as $oria_w ) : ?>
										<span class="mediacard__tag" style="--gf:<?php echo esc_attr( $oria_w['color'] ); ?>"><?php echo esc_html( $oria_w['label'] ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
