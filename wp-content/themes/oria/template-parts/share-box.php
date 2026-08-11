<?php
/**
 * The way into a listing's share kit, from the listing itself.
 *
 * Two audiences read the same box differently. An owner wants to be handed
 * something they can post; a visitor wants to pass a practice on to a
 * friend. Same destination, different sentence.
 *
 * @package Oria
 *
 * Expects $args: id (int), owner (bool).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oria_share_id = (int) ( $args['id'] ?? 0 );

// The share kit lives in the plugin; without it there is nowhere to go.
if ( ! $oria_share_id || ! function_exists( 'Oria\Core\Share\url' ) ) {
	return;
}

$oria_share_owner = ! empty( $args['owner'] );
$oria_share_url   = \Oria\Core\Share\url( $oria_share_id );
?>
<div class="sharebox<?php echo $oria_share_owner ? ' sharebox--owner' : ''; ?>">
	<span class="sharebox__icon" aria-hidden="true">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="2.6"/><circle cx="6" cy="12" r="2.6"/><circle cx="18" cy="19" r="2.6"/><path d="M8.3 10.8 15.7 6.7M8.3 13.2l7.4 4.1"/></svg>
	</span>
	<div class="sharebox__body">
		<b class="sharebox__title">
			<?php
			echo esc_html(
				$oria_share_owner
					? __( 'Share your profile', 'oria' )
					: __( 'Know someone who\'d like this?', 'oria' )
			);
			?>
		</b>
		<p class="sharebox__text">
			<?php
			echo esc_html(
				$oria_share_owner
					? __( 'We\'ve made you a card with your name on it and written the post. One tap sends it to Facebook, LinkedIn or WhatsApp.', 'oria' )
					: __( 'The link comes with a card and a few words already written — nothing to type.', 'oria' )
			);
			?>
		</p>
		<a
			class="btn <?php echo $oria_share_owner ? 'btn--dark btn--block' : 'btn--ghost btn--block'; ?>"
			href="<?php echo esc_url( $oria_share_url ); ?>"
			data-oria-event="share_kit_open"
		>
			<?php
			echo esc_html(
				$oria_share_owner
					? __( 'Share my Oria Haven profile', 'oria' )
					: __( 'Share this practice', 'oria' )
			);
			?>
			<?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
	</div>
</div>
