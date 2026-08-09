<?php
/**
 * Section: numbered steps beside an intro
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
	<div class="wrap split split--wide-media">
		<div class="reveal">
			<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
			<h2 class="h2" style="margin-block:.75rem 1.25rem"><?php echo esc_html( $t('heading') ); ?></h2>
			<?php if ( $t('intro') ) : ?><p class="prose"><?php echo esc_html( $t('intro') ); ?></p><?php endif; ?>
			<?php if ( $t('primary_label') || $t('secondary_label') ) : ?>
			<div class="row" style="margin-top:1.75rem;gap:.75rem;flex-wrap:wrap">
				<?php if ( $t('primary_label') ) : ?>
					<a class="btn btn--dark" href="<?php echo esc_url( $t('primary_url') ?: '#' ); ?>"><?php echo esc_html( $t('primary_label') ); ?><?php echo arrow(); // phpcs:ignore ?></a>
				<?php endif; ?>
				<?php if ( $t('secondary_label') ) : ?>
					<a class="btn btn--ghost btn--plain" href="<?php echo esc_url( $t('secondary_url') ?: '#' ); ?>"><?php echo esc_html( $t('secondary_label') ); ?></a>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>

		<div class="reveal" style="--d:100ms">
			<div class="steps">
				<?php foreach ( srows( $s, 'steps' ) as $oria_step ) : ?>
					<div class="step"><span class="step__n"></span><div>
						<div class="step__title"><?php echo esc_html( (string) ( $oria_step['title'] ?? '' ) ); ?></div>
						<p class="step__text"><?php echo esc_html( (string) ( $oria_step['text'] ?? '' ) ); ?></p>
					</div></div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
