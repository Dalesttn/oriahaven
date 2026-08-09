<?php
/**
 * Section: call-to-action slab
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

?>
<section class="section section--bottom-flush">
	<div class="slab ctaslab on-deep">
		<div class="ctaslab__bg" aria-hidden="true"><img src="<?php echo esc_url( simg( $s, 'image', 'scene-dusk-ridge.webp' ) ); ?>" alt="" loading="lazy"></div>
		<div class="ctaslab__inner">
			<div>
				<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
				<h2 class="h2 ctaslab__title" style="margin-top:1rem"><?php echo esc_html( $t('heading') ); ?></h2>
			</div>
			<div class="row" style="gap:.75rem;flex-wrap:wrap">
				<?php if ( $t('primary_label') ) : ?>
					<a class="btn btn--light" href="<?php echo esc_url( $t('primary_url') ?: '#' ); ?>"><?php echo esc_html( $t('primary_label') ); ?><span class="btn__dot"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h10M8 3l4 4-4 4"/></svg></span></a>
				<?php endif; ?>
				<?php if ( $t('secondary_label') ) : ?>
					<a class="btn btn--ghost-on-deep btn--plain" href="<?php echo esc_url( $t('secondary_url') ?: '#' ); ?>"><?php echo esc_html( $t('secondary_label') ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
