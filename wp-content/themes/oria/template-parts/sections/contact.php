<?php
/**
 * Section: contact split
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

?>
<section class="wrap section" id="contact">
	<div class="split">
		<div>
			<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
			<h2 class="h2" style="margin-block:.75rem 1.25rem"><?php echo esc_html( $t('heading') ); ?></h2>
			<?php if ( $t('intro') ) : ?><p class="prose"><?php echo esc_html( $t('intro') ); ?></p><?php endif; ?>
			<?php if ( $t('email') ) : ?>
			<div class="contactcard on-deep" style="margin-top:2rem">
				<div class="contactcard__rows" style="margin-top:0">
					<div class="contactcard__row"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="2" y="3.5" width="12" height="9" rx="2"/><path d="m2.6 4.5 5.4 3.6 5.4-3.6"/></svg><a href="mailto:<?php echo esc_attr( $t('email') ); ?>"><?php echo esc_html( $t('email') ); ?></a></div>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<div class="card">
			<div class="card__body" style="padding:clamp(1.5rem,3vw,2.25rem)">
				<?php
				// Raw on purpose — see the note in form-card.php: kses would
				// strip the expanded form's markup and leak its CSS/JS as text.
				echo do_shortcode( (string) ( $s['form'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</div>
		</div>
	</div>
</section>
