<?php
/**
 * One trend, as a card: the hub's grid and "More trends to understand".
 *
 * Never a live embed -- the brief's rule for archive and related cards.
 * The picture is the trend's own Oria-owned cover image, or a quiet sand
 * panel with the trend's name when there is none yet.
 *
 * Args: post (WP_Post), feature (bool, the hub's large card), location
 * (string, analytics: where the card sits).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\Trends;

$oria_t = $args['post'] ?? null;
if ( ! $oria_t instanceof WP_Post ) {
	return;
}
$oria_id      = (int) $oria_t->ID;
$oria_feature = ! empty( $args['feature'] );
$oria_where   = (string) ( $args['location'] ?? 'hub' );
$oria_url     = (string) get_permalink( $oria_t );
$oria_goals   = Trends\goals( $oria_id );
$oria_ev      = Trends\evidence( $oria_id );
$oria_beg     = Trends\beginner( $oria_id );
$oria_price   = Trends\price( $oria_id );
$oria_line    = has_excerpt( $oria_t ) ? get_the_excerpt( $oria_t ) : wp_html_excerpt( wp_strip_all_tags( (string) get_field( 'short_answer', $oria_id ) ), $oria_feature ? 240 : 130, '…' );
$oria_img     = get_post_thumbnail_id( $oria_t );
$oria_search  = strtolower( get_the_title( $oria_t ) . ' ' . $oria_line . ' ' . implode( ' ', wp_list_pluck( $oria_goals, 'label' ) ) );
?>
<article class="trendcard<?php echo $oria_feature ? ' trendcard--feature' : ''; ?>"
	data-trend-goals=" <?php echo esc_attr( implode( ' ', wp_list_pluck( $oria_goals, 'slug' ) ) ); ?> "
	data-trend-search="<?php echo esc_attr( $oria_search ); ?>">
	<a class="trendcard__media" href="<?php echo esc_url( $oria_url ); ?>" tabindex="-1" aria-hidden="true" data-trend-cta="related" data-trend-where="<?php echo esc_attr( $oria_where ); ?>">
		<?php if ( $oria_img ) : ?>
			<?php echo wp_get_attachment_image( $oria_img, $oria_feature ? 'oria-wide' : 'oria-card', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		<?php else : ?>
			<span class="trendcard__blank"><span aria-hidden="true">&#10022;</span></span>
		<?php endif; ?>
	</a>
	<div class="trendcard__body">
		<?php if ( $oria_goals ) : ?>
			<ul class="trendcard__goals" aria-label="<?php esc_attr_e( 'May suit', 'oria' ); ?>">
				<?php foreach ( array_slice( $oria_goals, 0, 3 ) as $oria_g ) : ?>
					<li style="--gf:<?php echo esc_attr( $oria_g['color'] ); ?>"><?php echo esc_html( $oria_g['label'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<h3 class="trendcard__title"><a href="<?php echo esc_url( $oria_url ); ?>" data-trend-cta="related" data-trend-where="<?php echo esc_attr( $oria_where ); ?>"><?php echo esc_html( get_the_title( $oria_t ) ); ?></a></h3>
		<?php if ( '' !== $oria_line ) : ?>
			<p class="trendcard__line"><?php echo esc_html( $oria_line ); ?></p>
		<?php endif; ?>
		<p class="trendcard__meta">
			<?php if ( $oria_ev ) : ?>
				<span class="evpill evpill--<?php echo esc_attr( $oria_ev['key'] ); ?>" title="<?php echo esc_attr( $oria_ev['explain'] ); ?>"><?php echo esc_html( $oria_ev['label'] ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $oria_beg ) : ?>
				<span><?php echo esc_html( $oria_beg ); ?></span>
			<?php endif; ?>
			<?php if ( $oria_price ) : ?>
				<span><?php echo esc_html( $oria_price['text'] ); ?></span>
			<?php endif; ?>
		</p>
		<span class="trendcard__cta" aria-hidden="true"><?php esc_html_e( 'Understand this trend', 'oria' ); ?> &rarr;</span>
	</div>
</article>
