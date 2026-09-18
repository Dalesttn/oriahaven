<?php
/**
 * The Oria Reel Recommendation.
 *
 * A Reel someone may have seen in their feed, framed by Oria's own words:
 * a headline, who made it, why we picked it, and what to take from it.
 * Server-rendered and complete without the video -- the Reel is an extra
 * the visitor chooses, never the content.
 *
 * Nothing from Instagram loads with the page. The frame is a placeholder
 * with a real button; pressing it builds Instagram's own embed from the
 * stored, validated address and loads Instagram's script once for the
 * whole page (app.js initReels). The frame's space is reserved up front so
 * nothing below moves until the visitor asks for the video. If the embed
 * does not arrive -- removed, private, blocked -- the fallback text takes
 * its place, with a plain link out while the address is still good.
 *
 * Variants:
 *   full     the trend page: frame beside the text, takeaways, the CTA
 *   compact  guides and category pages: smaller, links to the trend page
 *   listing  a business's own Reel on its listing
 *
 * Args: id (int, the trend), variant (string), location (string, analytics).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\Trends;

$oria_id      = (int) ( $args['id'] ?? 0 );
$oria_variant = in_array( $args['variant'] ?? 'full', array( 'full', 'compact', 'listing' ), true ) ? (string) ( $args['variant'] ?? 'full' ) : 'full';
$oria_where   = (string) ( $args['location'] ?? $oria_variant );
$oria_r       = $oria_id ? Trends\reel( $oria_id ) : null;
if ( ! $oria_r ) {
	return;
}
$oria_slug  = (string) get_post_field( 'post_name', $oria_id );
$oria_head  = '' !== $oria_r['headline'] ? $oria_r['headline'] : get_the_title( $oria_id );
$oria_uid   = 'reel-' . $oria_id . '-' . $oria_variant;
$oria_cover = get_post_thumbnail_id( $oria_id ) ? (string) wp_get_attachment_image_url( get_post_thumbnail_id( $oria_id ), 'oria-portrait' ) : '';
$oria_who   = '' !== $oria_r['creator'] ? $oria_r['creator'] : ( '' !== $oria_r['handle'] ? '@' . $oria_r['handle'] : '' );
$oria_cta_l = trim( (string) get_field( 'primary_cta_label', $oria_id ) );
$oria_cta_u = (string) get_field( 'primary_cta_url', $oria_id );
?>
<section class="reelrec reelrec--<?php echo esc_attr( $oria_variant ); ?>" aria-labelledby="<?php echo esc_attr( $oria_uid ); ?>-h">
	<div class="reelrec__frame" data-reel data-reel-id="<?php echo (int) $oria_id; ?>" data-reel-url="<?php echo esc_url( $oria_r['url'] ); ?>" data-reel-trend="<?php echo esc_attr( $oria_slug ); ?>" data-reel-where="<?php echo esc_attr( $oria_where ); ?>">
		<?php if ( $oria_r['show'] ) : ?>
			<div class="reelrec__placeholder"<?php echo '' !== $oria_cover ? ' style="--reel-cover:url(\'' . esc_url( $oria_cover ) . '\')"' : ''; ?>>
				<button type="button" class="reelrec__play" data-reel-load aria-describedby="<?php echo esc_attr( $oria_uid ); ?>-note">
					<span class="reelrec__playicon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M8 5.5v13l10.5-6.5z" fill="currentColor"/></svg></span>
					<?php
					/* translators: %s: creator name or handle */
					echo esc_html( '' !== $oria_who ? sprintf( __( 'Watch Reel by %s', 'oria' ), $oria_who ) : __( 'Watch Reel', 'oria' ) );
					?>
				</button>
				<p class="reelrec__note" id="<?php echo esc_attr( $oria_uid ); ?>-note"><?php esc_html_e( 'Loads Instagram’s player, which may set its own cookies. No sound until you play it.', 'oria' ); ?></p>
			</div>
		<?php endif; ?>
		<div class="reelrec__fallback"<?php echo $oria_r['show'] ? ' hidden' : ''; ?> data-reel-fallback>
			<p class="reelrec__fallbacklabel micro"><?php echo esc_html( $oria_r['gone'] ? __( 'This Reel is no longer available', 'oria' ) : __( 'About the Reel', 'oria' ) ); ?></p>
			<?php if ( '' !== $oria_r['fallback'] ) : ?>
				<p><?php echo esc_html( $oria_r['fallback'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! $oria_r['gone'] ) : ?>
				<a class="reelrec__out" href="<?php echo esc_url( $oria_r['url'] ); ?>" target="_blank" rel="noopener nofollow" data-reel-out><?php esc_html_e( 'Open the original on Instagram', 'oria' ); ?> <span aria-hidden="true">&#8599;</span><span class="sr-only"> <?php esc_html_e( '(opens in a new tab)', 'oria' ); ?></span></a>
			<?php endif; ?>
		</div>
	</div>

	<div class="reelrec__text">
		<p class="micro reelrec__eyebrow"><?php echo esc_html( 'listing' === $oria_variant ? __( 'On Instagram', 'oria' ) : __( 'Trend to Try', 'oria' ) ); ?></p>
		<h2 class="reelrec__title" id="<?php echo esc_attr( $oria_uid ); ?>-h">
			<?php if ( 'compact' === $oria_variant ) : ?>
				<a href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>" data-trend-cta="related" data-trend-where="<?php echo esc_attr( $oria_where ); ?>"><?php echo esc_html( $oria_head ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $oria_head ); ?>
			<?php endif; ?>
		</h2>

		<?php if ( '' !== $oria_who ) : ?>
			<p class="reelrec__credit">
				<?php esc_html_e( 'Reel by', 'oria' ); ?>
				<?php if ( '' !== $oria_r['profile'] ) : ?>
					<a href="<?php echo esc_url( $oria_r['profile'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $oria_who ); ?></a>
				<?php else : ?>
					<b><?php echo esc_html( $oria_who ); ?></b>
				<?php endif; ?>
				<?php if ( '' !== $oria_r['handle'] && '' !== $oria_r['creator'] ) : ?>
					<span class="reelrec__handle">@<?php echo esc_html( $oria_r['handle'] ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $oria_r['where'] ) : ?>
					<span class="reelrec__handle">· <?php echo esc_html( $oria_r['where'] ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<?php if ( '' !== $oria_r['credit'] ) : ?>
			<p class="reelrec__attr"><?php echo esc_html( $oria_r['credit'] ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $oria_r['reason'] ) : ?>
			<p class="reelrec__reason"><?php echo esc_html( $oria_r['reason'] ); ?></p>
		<?php endif; ?>

		<?php if ( 'full' === $oria_variant && $oria_r['takeaways'] ) : ?>
			<ul class="reelrec__takeaways">
				<?php foreach ( $oria_r['takeaways'] as $oria_t ) : ?>
					<li><?php echo esc_html( $oria_t ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( 'compact' === $oria_variant ) : ?>
			<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>" data-trend-cta="related" data-trend-where="<?php echo esc_attr( $oria_where ); ?>"><?php esc_html_e( 'Understand this trend', 'oria' ); ?><span aria-hidden="true"><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></span></a>
		<?php elseif ( 'full' === $oria_variant && '' !== $oria_cta_l && '' !== $oria_cta_u ) : ?>
			<a class="btn btn--dark btn--sm" href="<?php echo esc_url( $oria_cta_u ); ?>" data-trend-cta="cta" data-trend-where="reel"><?php echo esc_html( $oria_cta_l ); ?><span aria-hidden="true"><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></span></a>
		<?php endif; ?>

		<?php if ( ! $oria_r['owner'] && '' !== $oria_who ) : ?>
			<p class="reelrec__disclaim">
				<?php
				/* translators: %s: creator */
				echo esc_html( sprintf( __( 'Shown through Instagram’s own embed. %s is not affiliated with Oria Haven and has not reviewed this page.', 'oria' ), $oria_who ) );
				?>
			</p>
		<?php endif; ?>
	</div>
</section>
