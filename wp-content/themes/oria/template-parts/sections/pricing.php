<?php
/**
 * Section: pricing tiers
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
<section class="section<?php echo esc_attr( sband( $s ) ); ?>" id="pricing">
	<div class="wrap">
		<div class="sec-head sec-head--center reveal">
			<div class="sec-head__text">
				<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
				<h2 class="h2"><?php echo esc_html( $t('heading') ); ?></h2>
			</div>
			<?php if ( $t('sub') ) : ?><p class="sec-head__aside" style="text-align:center;max-width:52ch;color:var(--text-soft)"><?php echo esc_html( $t('sub') ); ?></p><?php endif; ?>
		</div>

		<div class="pricegrid">
			<?php foreach ( srows( $s, 'tiers' ) as $oria_tier ) :
				$oria_style = (string) ( $oria_tier['style'] ?? 'default' );
				$oria_feats = preg_split( '/\r?\n/', (string) ( $oria_tier['features'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
				?>
				<div class="price<?php echo 'now' === $oria_style ? ' price--now' : ( 'soon' === $oria_style ? ' price--soon' : '' ); ?>">
					<span class="price__tier"><?php echo esc_html( (string) ( $oria_tier['tier_label'] ?? '' ) ); ?></span>
					<div class="price__amt"><?php echo esc_html( (string) ( $oria_tier['amount'] ?? '' ) ); ?><?php if ( ! empty( $oria_tier['suffix'] ) ) : ?><small> <?php echo esc_html( (string) $oria_tier['suffix'] ); ?></small><?php endif; ?></div>
					<?php if ( ! empty( $oria_tier['blurb'] ) ) : ?><p style="font-size:.9375rem;<?php echo 'now' === $oria_style ? 'color:var(--mist)' : 'color:var(--text-soft)'; ?>"><?php echo esc_html( (string) $oria_tier['blurb'] ); ?></p><?php endif; ?>
					<ul class="price__list">
						<?php foreach ( $oria_feats as $oria_feat ) : ?>
							<li><?php echo $oria_tick; // phpcs:ignore ?><span><?php echo esc_html( $oria_feat ); ?></span></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( ! empty( $oria_tier['cta_url'] ) ) : ?>
						<a class="btn <?php echo 'now' === $oria_style ? 'btn--light' : 'btn--ghost'; ?> btn--block" href="<?php echo esc_url( (string) $oria_tier['cta_url'] ); ?>"><?php echo esc_html( (string) ( $oria_tier['cta_label'] ?? '' ) ); ?><?php echo arrow(); // phpcs:ignore ?></a>
					<?php elseif ( ! empty( $oria_tier['cta_label'] ) ) : ?>
						<button class="btn btn--ghost btn--block" type="button" <?php echo 'soon' === $oria_style ? 'disabled style="opacity:.5;cursor:default"' : ''; ?>><?php echo esc_html( (string) $oria_tier['cta_label'] ); ?></button>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
