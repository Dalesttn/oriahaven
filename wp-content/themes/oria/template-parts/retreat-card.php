<?php
/**
 * One retreat card, shared by the hub collection and the small modules on
 * other pages. Reads top to bottom the way someone compares: a photograph,
 * an editorial label (an experience, never a score), where and with whom,
 * the title with its duration, one sentence on pace and appeal, two facts
 * (nights, meals), then the price as the provider lists it with an honest
 * A$ estimate, "Check dates" to BookRetreats, and "A closer look" holding
 * the full inclusions and exclusions.
 *
 * The call to action is an ordinary anchor to the exact stored affiliate
 * link, rel="sponsored noopener", in a new tab. The click counter
 * (data-oria-aff, app.js) only ever sends the offer id and the placement.
 *
 * @var array $args { id: int, placement: string, hidden?: bool, eager?: bool }
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\Retreats as R;

$oria_id    = (int) ( $args['id'] ?? 0 );
$oria_place = sanitize_key( (string) ( $args['placement'] ?? 'hub' ) );
if ( ! $oria_id || ! R\eligible( $oria_id ) ) {
	return;
}
$oria_title  = get_the_title( $oria_id );
$oria_inc    = R\lines( $oria_id, 'inclusions' );
$oria_exc    = R\lines( $oria_id, 'exclusions' );
$oria_book   = R\get( $oria_id, 'booking' ) ?: 'BookRetreats';
$oria_nights = (int) R\get( $oria_id, 'nights' );
$oria_meals  = R\get( $oria_id, 'meals' );
$oria_label  = R\get( $oria_id, 'label' );
$oria_line   = R\get( $oria_id, 'suits' ) ?: R\get( $oria_id, 'pace' );
$oria_shown  = R\source_price( $oria_id );
$oria_cur    = R\get( $oria_id, 'price_currency' ) ?: 'AUD';
$oria_aud    = R\aud_estimate( $oria_id );
$oria_fxday  = R\fx_checked( $oria_cur );
$oria_basis  = R\get( $oria_id, 'price_basis' );
$oria_dur    = R\duration_label( $oria_id );
$oria_styles = R\styles_of( $oria_id );
$oria_did    = 'ro-look-' . $oria_id;
?>
<article class="ro-card" id="ro-offer-<?php echo (int) $oria_id; ?>" data-ro-dest="<?php echo esc_attr( R\get( $oria_id, 'destination' ) ); ?>" data-ro-len="<?php echo esc_attr( R\get( $oria_id, 'length' ) ); ?>" data-ro-styles="<?php echo esc_attr( implode( ' ', $oria_styles ) ); ?>"<?php echo ! empty( $args['hidden'] ) ? ' hidden' : ''; ?> aria-labelledby="ro-t-<?php echo (int) $oria_id; ?>">
	<div class="ro-card__media">
		<?php
		if ( has_post_thumbnail( $oria_id ) ) {
			echo get_the_post_thumbnail(
				$oria_id,
				'large',
				array(
					'loading'  => empty( $args['eager'] ) ? 'lazy' : 'eager',
					'decoding' => 'async',
					'alt'      => sprintf( /* translators: 1: retreat, 2: place */ __( '%1$s, %2$s', 'oria' ), $oria_title, R\place_label( $oria_id ) ),
					'sizes'    => '(max-width: 40rem) 100vw, (max-width: 80rem) 50vw, 580px',
				)
			);
		}
		?>
	</div>
	<div class="ro-card__body">
		<?php if ( '' !== $oria_label ) : ?>
			<p class="ro-card__label"><?php echo esc_html( $oria_label ); ?></p>
		<?php endif; ?>
		<p class="ro-card__meta">
			<span><?php echo esc_html( R\place_label( $oria_id ) ); ?></span>
			<?php if ( '' !== R\get( $oria_id, 'provider' ) ) : ?>
				<span aria-hidden="true">·</span>
				<span><?php echo esc_html( R\get( $oria_id, 'provider' ) ); ?></span>
			<?php endif; ?>
		</p>
		<h3 class="ro-card__title" id="ro-t-<?php echo (int) $oria_id; ?>"><?php echo esc_html( $oria_title ); ?> <span class="ro-card__dur"><?php echo esc_html( $oria_dur ); ?></span></h3>
		<?php if ( '' !== $oria_line ) : ?>
			<p class="ro-card__line"><?php echo esc_html( $oria_line ); ?></p>
		<?php endif; ?>
		<ul class="ro-card__facts" aria-label="<?php esc_attr_e( 'At a glance', 'oria' ); ?>">
			<?php if ( $oria_nights ) : ?>
				<li><?php echo esc_html( sprintf( _n( '%d night', '%d nights', $oria_nights, 'oria' ), $oria_nights ) ); ?></li>
			<?php endif; ?>
			<?php if ( '' !== $oria_meals ) : ?>
				<li><?php echo esc_html( $oria_meals ); ?></li>
			<?php elseif ( '' !== $oria_basis ) : ?>
				<li><?php echo esc_html( ucfirst( trim( (string) preg_replace( '/^per person,?\s*/i', '', $oria_basis ) ) ) ?: $oria_basis ); ?></li>
			<?php endif; ?>
		</ul>

		<div class="ro-card__foot">
			<div class="ro-card__pricing">
				<?php if ( '' !== $oria_shown && $oria_aud ) : ?>
					<?php // Australian dollars lead, because the reader is in Australia; the provider's own figure and the rate's date sit underneath. ?>
					<p class="ro-card__price">
						<span class="ro-card__from"><?php esc_html_e( 'From about', 'oria' ); ?></span>
						<strong><?php echo esc_html( 'A$' . number_format_i18n( $oria_aud ) ); ?></strong>
						<?php if ( '' !== $oria_basis ) : ?><span class="ro-card__basis"><?php echo esc_html( $oria_basis ); ?></span><?php endif; ?>
					</p>
					<p class="ro-card__aud">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: price in the provider's currency, 2: booking provider, 3: date the rate was fetched */
								__( 'Listed as %1$s on %2$s; A$ is an estimate at the reference rate of %3$s. Final price confirmed by %2$s.', 'oria' ),
								$oria_shown,
								$oria_book,
								'' !== $oria_fxday ? mysql2date( 'j M', $oria_fxday ) : __( 'today', 'oria' )
							)
						);
						?>
					</p>
				<?php elseif ( '' !== $oria_shown ) : ?>
					<p class="ro-card__price">
						<span class="ro-card__from"><?php esc_html_e( 'From', 'oria' ); ?></span>
						<strong><?php echo esc_html( $oria_shown ); ?></strong>
						<?php if ( '' !== $oria_basis ) : ?><span class="ro-card__basis"><?php echo esc_html( $oria_basis ); ?></span><?php endif; ?>
					</p>
					<?php if ( 'AUD' !== $oria_cur ) : ?>
						<p class="ro-card__aud"><?php echo esc_html( sprintf( /* translators: 1: currency, 2: provider */ __( 'Charged in %1$s by %2$s; no exchange estimate available right now.', 'oria' ), $oria_cur, $oria_book ) ); ?></p>
					<?php else : ?>
						<p class="ro-card__aud"><?php echo esc_html( sprintf( /* translators: %s: provider */ __( 'Final price confirmed by %s.', 'oria' ), $oria_book ) ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p class="ro-card__price"><strong><?php esc_html_e( 'Check current pricing', 'oria' ); ?></strong></p>
					<p class="ro-card__aud"><?php echo esc_html( sprintf( /* translators: %s: provider */ __( 'Prices and dates are on %s.', 'oria' ), $oria_book ) ); ?></p>
				<?php endif; ?>
			</div>
			<div class="ro-card__act">
				<a class="ro-card__cta" href="<?php echo esc_url( R\get( $oria_id, 'aff_url' ) ); ?>" target="_blank" rel="sponsored noopener"
					data-oria-aff="<?php echo (int) $oria_id; ?>" data-oria-aff-place="<?php echo esc_attr( $oria_place ); ?>">
					<?php esc_html_e( 'Check dates', 'oria' ); ?>
					<span class="sr-only"><?php echo esc_html( sprintf( /* translators: 1: retreat, 2: provider */ __( 'for %1$s on %2$s (opens in a new tab)', 'oria' ), $oria_title, $oria_book ) ); ?></span>
				</a>
				<p class="ro-card__aff"><?php echo esc_html( sprintf( /* translators: %s: provider */ __( 'On %s · affiliate link', 'oria' ), $oria_book ) ); ?></p>
			</div>
		</div>

		<?php if ( $oria_inc || $oria_exc || '' !== R\get( $oria_id, 'summary' ) ) : ?>
			<details class="ro-card__look" id="<?php echo esc_attr( $oria_did ); ?>" data-ro-look="<?php echo (int) $oria_id; ?>">
				<summary><?php esc_html_e( 'A closer look', 'oria' ); ?></summary>
				<?php if ( '' !== R\get( $oria_id, 'summary' ) ) : ?>
					<p><?php echo esc_html( R\get( $oria_id, 'summary' ) ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== R\get( $oria_id, 'pace' ) ) : ?>
					<p><strong><?php esc_html_e( 'Pace:', 'oria' ); ?></strong> <?php echo esc_html( R\get( $oria_id, 'pace' ) ); ?></p>
				<?php endif; ?>
				<?php if ( $oria_inc ) : ?>
					<p class="ro-card__h"><?php esc_html_e( 'Included', 'oria' ); ?></p>
					<ul class="ro-card__inc"><?php foreach ( $oria_inc as $oria_l ) : ?><li><?php echo esc_html( $oria_l ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<?php if ( $oria_exc ) : ?>
					<p class="ro-card__h"><?php esc_html_e( 'Not included', 'oria' ); ?></p>
					<ul class="ro-card__exc"><?php foreach ( $oria_exc as $oria_l ) : ?><li><?php echo esc_html( $oria_l ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<?php if ( '' !== R\get( $oria_id, 'reviewed' ) ) : ?>
					<p class="ro-card__checked"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Details checked against the provider page on %s.', 'oria' ), mysql2date( 'j F Y', R\get( $oria_id, 'reviewed' ) ) ) ); ?></p>
				<?php endif; ?>
			</details>
		<?php endif; ?>
	</div>
</article>
