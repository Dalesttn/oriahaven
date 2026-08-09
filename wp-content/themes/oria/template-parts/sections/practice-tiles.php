<?php
/**
 * Section: practice tiles
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

$oria_practices = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false ) );
$oria_practices = is_wp_error( $oria_practices ) ? array() : $oria_practices;
// A fresh random order every page load; the script shows eight at a time
// and rotates the window, so every category still gets its turn.
shuffle( $oria_practices );
$oria_visible = 8;
$oria_fallbacks = array(
	'meditation' => 'cat-meditation.webp', 'breathwork' => 'cat-breathwork.webp',
	'yoga' => 'cat-yoga.webp', 'mindfulness' => 'cat-mindfulness.webp',
	'sound' => 'cat-sound.webp', 'retreats' => 'cat-retreats.webp',
);
?>
<section class="section">
	<div class="wrap">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
				<h2 class="h2"><?php echo esc_html( $t('heading') ); ?></h2>
			</div>
			<?php if ( $t('aside') ) : ?><p class="sec-head__aside muted"><?php echo esc_html( $t('aside') ); ?></p><?php endif; ?>
		</div>

		<div class="cats" data-cats-rotate="<?php echo (int) $oria_visible; ?>">
			<?php foreach ( array_values( $oria_practices ) as $oria_i => $oria_p ) :
				$oria_img_id = get_field( 'tile_image', 'practice_' . $oria_p->term_id );
				$oria_url    = $oria_img_id ? wp_get_attachment_image_url( (int) $oria_img_id, 'oria-card' ) : '';
				if ( ! $oria_url ) {
					$oria_url = get_template_directory_uri() . '/assets/img/' . ( $oria_fallbacks[ $oria_p->slug ] ?? 'cat-meditation.webp' );
				}
				$oria_blurb = (string) ( get_field( 'tile_blurb', 'practice_' . $oria_p->term_id ) ?: $oria_p->description );
				?>
				<a class="cat reveal" href="<?php echo esc_url( (string) get_term_link( $oria_p ) ); ?>"<?php echo $oria_i >= $oria_visible ? ' hidden' : ''; ?>>
					<img class="cat__img" src="<?php echo esc_url( $oria_url ); ?>" alt="" loading="lazy">
					<div class="cat__text">
						<div class="cat__name"><?php echo esc_html( \Oria\Theme\tname( $oria_p ) ); ?></div>
						<?php if ( $oria_blurb ) : ?><div class="cat__count"><?php echo esc_html( $oria_blurb ); ?></div><?php endif; ?>
					</div>
					<span class="iconbtn cat__go" aria-hidden="true"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 11 3M5 3h6v6"/></svg></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
