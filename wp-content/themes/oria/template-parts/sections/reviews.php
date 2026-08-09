<?php
/**
 * Section: review cards
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
	<div class="wrap">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
				<h2 class="h2"><?php echo esc_html( $t('heading') ); ?></h2>
			</div>
		</div>
		<div class="grid grid-3">
			<?php foreach ( srows( $s, 'items' ) as $oria_r ) : ?>
				<div class="quote reveal">
					<div class="quote__mark" aria-hidden="true">&ldquo;</div>
					<div class="quote__title"><?php echo esc_html( (string) ( $oria_r['title'] ?? '' ) ); ?></div>
					<p class="quote__text"><?php echo esc_html( (string) ( $oria_r['quote'] ?? '' ) ); ?></p>
					<div class="quote__by"><b><?php echo esc_html( (string) ( $oria_r['name'] ?? '' ) ); ?></b><span><?php echo esc_html( (string) ( $oria_r['where'] ?? '' ) ); ?></span></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
