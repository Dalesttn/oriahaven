<?php
/**
 * Section: feature rows beside an image
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

?>
<section class="section<?php echo esc_attr( sband( $s ) ); ?>">
	<div class="wrap split">
		<div class="reveal">
			<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
			<h2 class="h2" style="margin-block:.75rem 1.25rem"><?php echo esc_html( $t('heading') ); ?></h2>
			<?php if ( $t('intro') ) : ?><p class="prose"><?php echo esc_html( $t('intro') ); ?></p><?php endif; ?>

			<div class="featurelist" style="margin-top:2rem">
				<?php foreach ( srows( $s, 'rows' ) as $oria_row ) : ?>
					<div class="featurerow">
						<span class="featurerow__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6.5v5.2c0 4.8 3.3 8.4 8 9.3 4.7-.9 8-4.5 8-9.3V6.5L12 3Z"/><path d="M8.8 12.2 11 14.4l4.4-4.6"/></svg></span>
						<div>
							<div class="featurerow__title"><?php echo esc_html( (string) ( $oria_row['title'] ?? '' ) ); ?></div>
							<p class="featurerow__text"><?php echo esc_html( (string) ( $oria_row['text'] ?? '' ) ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="split__media reveal" style="--d:100ms">
			<img src="<?php echo esc_url( simg( $s, 'image', 'scene-studio.webp', 'oria-card' ) ); ?>" alt="" loading="lazy">
		</div>
	</div>
</section>
