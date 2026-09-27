<?php
/**
 * One retreat offer card. Everything essential is text, not artwork.
 *
 * The call to action is an ordinary anchor to the exact stored affiliate
 * link, rel="sponsored", same tab: it works with scripts off, and the
 * click counter (data-oria-aff, app.js) only ever sends the offer id and
 * the placement, never the link.
 *
 * @var array $args { id: int, placement: string }
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\Retreats as R;

$oria_id    = (int) ( $args['id'] ?? 0 );
$oria_place = sanitize_key( (string) ( $args['placement'] ?? 'hub' ) );
if ( ! $oria_id || ! R\eligible( $oria_id ) ) {
	return;
}
$oria_title = get_the_title( $oria_id );
$oria_inc   = array_slice( R\lines( $oria_id, 'inclusions' ), 0, 3 );
$oria_exc   = R\lines( $oria_id, 'exclusions' );
$oria_book  = R\get( $oria_id, 'booking' ) ?: 'BookRetreats';
?>
<article class="ro-card" data-ro-dest="<?php echo esc_attr( R\get( $oria_id, 'destination' ) ); ?>" data-ro-len="<?php echo esc_attr( R\get( $oria_id, 'length' ) ); ?>">
	<div class="ro-card__media">
		<?php
		echo get_the_post_thumbnail(
			$oria_id,
			'medium_large',
			array(
				'loading'  => 'lazy',
				'decoding' => 'async',
				'alt'      => '',
				'sizes'    => '(max-width: 40rem) 100vw, (max-width: 64rem) 50vw, 400px',
			)
		);
		?>
	</div>
	<div class="ro-card__body">
		<p class="ro-card__meta">
			<span><?php echo esc_html( R\place_label( $oria_id ) ); ?></span>
			<span aria-hidden="true">·</span>
			<span><?php echo esc_html( R\duration_label( $oria_id ) ); ?></span>
		</p>
		<h3 class="ro-card__title"><?php echo esc_html( $oria_title ); ?></h3>
		<?php if ( '' !== R\get( $oria_id, 'provider' ) ) : ?>
			<p class="ro-card__by"><?php echo esc_html( sprintf( /* translators: %s: organiser */ __( 'With %s', 'oria' ), R\get( $oria_id, 'provider' ) ) ); ?></p>
		<?php endif; ?>
		<p class="ro-card__summary"><?php echo esc_html( R\get( $oria_id, 'summary' ) ); ?></p>
		<?php if ( $oria_inc ) : ?>
			<ul class="ro-card__inc" aria-label="<?php esc_attr_e( 'Includes', 'oria' ); ?>">
				<?php foreach ( $oria_inc as $oria_line ) : ?>
					<li><?php echo esc_html( $oria_line ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $oria_exc ) : ?>
			<p class="ro-card__exc"><?php echo esc_html( sprintf( /* translators: %s: list */ __( 'Not included: %s', 'oria' ), implode( ', ', $oria_exc ) ) ); ?></p>
		<?php endif; ?>
		<div class="ro-card__foot">
			<p class="ro-card__price"><?php echo esc_html( R\price_label( $oria_id ) ); ?></p>
			<a class="ro-card__cta" href="<?php echo esc_url( R\get( $oria_id, 'aff_url' ) ); ?>" rel="sponsored"
				data-oria-aff="<?php echo (int) $oria_id; ?>" data-oria-aff-place="<?php echo esc_attr( $oria_place ); ?>">
				<?php
				printf(
					/* translators: %s: booking provider */
					esc_html__( 'Check dates on %s', 'oria' ),
					esc_html( $oria_book )
				);
				?>
				<span class="sr-only"><?php echo esc_html( sprintf( /* translators: %s: retreat */ __( 'for %s', 'oria' ), $oria_title ) ); ?></span>
			</a>
			<p class="ro-card__aff"><?php esc_html_e( 'Affiliate link', 'oria' ); ?></p>
		</div>
	</div>
</article>
