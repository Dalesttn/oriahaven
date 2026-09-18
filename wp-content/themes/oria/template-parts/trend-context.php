<?php
/**
 * "Seen this online?" -- one Trend to Try, placed in context.
 *
 * On a category page or a guide: the single most relevant published trend
 * (Core\Trends\for_practice / for_guide), never a feed. When the trend has
 * a Reel an editor has cleared, it is the compact Reel card (still click to
 * load); otherwise a slim link to the explainer. No trend, no module.
 *
 * Args: trends (list<WP_Post>), location (string, analytics).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\Trends;

$oria_list  = array_values( (array) ( $args['trends'] ?? array() ) );
$oria_where = (string) ( $args['location'] ?? 'context' );
$oria_t     = $oria_list[0] ?? null;
if ( ! $oria_t instanceof WP_Post ) {
	return;
}
$oria_id = (int) $oria_t->ID;
$oria_r  = Trends\reel( $oria_id );

if ( $oria_r && $oria_r['show'] ) {
	echo '<div class="trendctx trendctx--reel">';
	get_template_part( 'template-parts/reel-card', null, array( 'id' => $oria_id, 'variant' => 'compact', 'location' => $oria_where ) );
	echo '</div>';
	return;
}

$oria_line = has_excerpt( $oria_t ) ? get_the_excerpt( $oria_t ) : wp_html_excerpt( wp_strip_all_tags( (string) get_field( 'short_answer', $oria_id ) ), 150, '…' );
?>
<aside class="trendctx" aria-labelledby="trendctx-<?php echo (int) $oria_id; ?>">
	<p class="micro trendctx__eyebrow"><span aria-hidden="true">&#10022;</span> <?php esc_html_e( 'Seen this online?', 'oria' ); ?></p>
	<div class="trendctx__body">
		<h2 class="trendctx__title" id="trendctx-<?php echo (int) $oria_id; ?>">
			<a href="<?php echo esc_url( (string) get_permalink( $oria_t ) ); ?>" data-trend-cta="related" data-trend-where="<?php echo esc_attr( $oria_where ); ?>"><?php echo esc_html( get_the_title( $oria_t ) ); ?></a>
		</h2>
		<?php if ( '' !== $oria_line ) : ?>
			<p class="trendctx__line"><?php echo esc_html( $oria_line ); ?></p>
		<?php endif; ?>
	</div>
	<a class="btn btn--ghost btn--sm trendctx__cta" href="<?php echo esc_url( (string) get_permalink( $oria_t ) ); ?>" data-trend-cta="related" data-trend-where="<?php echo esc_attr( $oria_where ); ?>" tabindex="-1" aria-hidden="true"><?php esc_html_e( 'Understand this trend', 'oria' ); ?><span aria-hidden="true"><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></span></a>
</aside>
