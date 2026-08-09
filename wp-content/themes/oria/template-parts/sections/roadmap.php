<?php
/**
 * Section: roadmap card
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
	<div class="wrap wrap--narrow">
		<div class="card" style="background:var(--sand-2)">
			<div class="card__body" style="padding:clamp(1.5rem,3vw,2.25rem)">
				<h2 class="h3" style="margin-bottom:1.25rem"><?php echo esc_html( $t('heading') ); ?></h2>
				<div class="steps">
					<?php foreach ( srows( $s, 'phases' ) as $oria_phase ) : ?>
						<div class="step"><span class="step__n"></span><div>
							<div class="step__title"><?php echo esc_html( (string) ( $oria_phase['title'] ?? '' ) ); ?>
								<?php if ( ! empty( $oria_phase['current'] ) ) : ?><span class="badge badge--claimed" style="margin-left:.4rem"><?php esc_html_e( 'Now', 'oria' ); ?></span><?php endif; ?>
							</div>
							<p class="step__text"><?php echo esc_html( (string) ( $oria_phase['text'] ?? '' ) ); ?></p>
						</div></div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</section>
