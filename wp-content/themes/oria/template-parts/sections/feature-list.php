<?php
/**
 * Section: ticked feature list
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

$oria_tick = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5 8 14.5 16 6"/></svg>';
?>
<section class="section<?php echo esc_attr( sband( $s ) ); ?>">
	<div class="wrap split">
		<div>
			<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
			<h2 class="h2" style="margin-block:.75rem 1.25rem"><?php echo esc_html( $t('heading') ); ?></h2>
		</div>
		<div>
			<div class="featurelist">
				<?php foreach ( srows( $s, 'items' ) as $oria_item ) : ?>
					<div class="featurerow">
						<span class="featurerow__icon"><?php echo $oria_tick; // phpcs:ignore ?></span>
						<div>
							<div class="featurerow__title"><?php echo esc_html( (string) ( $oria_item['title'] ?? '' ) ); ?></div>
							<p class="featurerow__text"><?php echo esc_html( (string) ( $oria_item['text'] ?? '' ) ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
