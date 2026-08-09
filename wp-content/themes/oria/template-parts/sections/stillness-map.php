<?php
/**
 * Section: the Stillness Map slab
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

?>
<section class="slab on-deep" aria-labelledby="mapHeading">
	<div style="padding: clamp(2.5rem,6vw,5rem) clamp(1.25rem,4vw,4rem)">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
				<h2 class="h2" id="mapHeading"><?php echo esc_html( $t('heading') ); ?></h2>
			</div>
			<?php if ( $t('aside') ) : ?><p class="sec-head__aside muted"><?php echo esc_html( $t('aside') ); ?></p><?php endif; ?>
		</div>
		<?php get_template_part( 'template-parts/stillness-map' ); ?>
	</div>
</section>
