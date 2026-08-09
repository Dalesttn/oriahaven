<?php
/**
 * Section: questions accordion
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

$oria_items = srows( $s, 'items' );
$oria_img   = ! empty( $s['image'] ) ? simg( $s, 'image', '', 'oria-card' ) : '';
?>
<section class="section<?php echo esc_attr( sband( $s ) ); ?>">
	<div class="wrap<?php echo $oria_img ? ' split' : ''; ?>">
		<?php if ( $oria_img ) : ?>
		<div class="split__media reveal"><img src="<?php echo esc_url( $oria_img ); ?>" alt="" loading="lazy"></div>
		<?php endif; ?>

		<div class="reveal" <?php echo $oria_img ? 'style="--d:100ms"' : 'style="max-width:56rem;margin-inline:auto"'; ?>>
			<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
			<h2 class="h2" style="margin-block:.75rem 2rem"><?php echo esc_html( $t('heading') ); ?></h2>
			<div class="acc">
				<?php foreach ( $oria_items as $oria_i => $oria_item ) : ?>
					<div class="acc__item<?php echo 0 === $oria_i ? ' is-open' : ''; ?>">
						<button class="acc__btn" type="button"><?php echo esc_html( (string) ( $oria_item['question'] ?? '' ) ); ?><span class="acc__sign" aria-hidden="true"></span></button>
						<div class="acc__panel"><div class="acc__inner"><p><?php echo esc_html( (string) ( $oria_item['answer'] ?? '' ) ); ?></p></div></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
