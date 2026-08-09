<?php
/**
 * Section: form in a card
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
		<div class="card" id="claimform">
			<div class="card__body" style="padding:clamp(1.5rem,3vw,2.25rem)">
				<?php if ( $t('heading') ) : ?><h2 class="h3" style="margin-bottom:.5rem"><?php echo esc_html( $t('heading') ); ?></h2><?php endif; ?>
				<?php if ( $t('sub') ) : ?><p class="muted" style="font-size:.9375rem;margin-bottom:1.5rem"><?php echo esc_html( $t('sub') ); ?></p><?php endif; ?>
				<?php
				/*
				 * No wp_kses_post here: the WYSIWYG field has already expanded
				 * the [wpforms] shortcode into full form markup, and kses
				 * strips form/style/script tags while leaving their text —
				 * which renders CSS and JS as visible content. The field is
				 * admin-authored, same trust level as the_content.
				 */
				echo do_shortcode( (string) ( $s['form'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</div>
		</div>
	</div>
</section>
