<?php
/**
 * Top of the "Offers and social" section in the listing editor: where the
 * owner's offer stands, and what we check. Drawn inside the editor's form,
 * so the withdraw control is a formaction button with its own nonce name
 * (the form already posts a _wpnonce of its own).
 *
 * $args: listing.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\OwnerOffers as OO;

$oria_listing = (int) ( $args['listing'] ?? 0 );
if ( ! $oria_listing || ! function_exists( '\Oria\Core\OwnerOffers\pending' ) ) {
	return;
}
$oria_p      = OO\pending( $oria_listing );
$oria_live   = function_exists( '\Oria\Theme\active_offer' ) ? \Oria\Theme\active_offer( $oria_listing ) : null;
$oria_review = OO\needs_review( $oria_listing );
?>
<div class="myoffer">
	<?php if ( $oria_p ) : ?>
		<div class="myoffer__state myoffer__state--wait" role="status">
			<p><strong><?php esc_html_e( 'Your offer is waiting to be checked.', 'oria' ); ?></strong> <?php esc_html_e( 'We look at a business’s first offer before it goes live, usually within two working days, and email you either way. The form below shows what you sent; saving again replaces it.', 'oria' ); ?></p>
			<button type="submit" class="btn btn--ghost btn--sm" formnovalidate
				formaction="<?php echo esc_url( add_query_arg( array( 'action' => 'oria_offer_withdraw', 'oria_wn' => wp_create_nonce( 'oria_offer_withdraw' ) ), admin_url( 'admin-post.php' ) ) ); ?>">
				<?php esc_html_e( 'Withdraw it', 'oria' ); ?>
			</button>
		</div>
	<?php elseif ( $oria_live && empty( $oria_live['advertised'] ) ) : ?>
		<p class="myoffer__state myoffer__state--live" role="status">
			<?php
			echo esc_html(
				'' !== (string) $oria_live['until']
					/* translators: %s: date */
					? sprintf( __( 'Your offer is live until %s.', 'oria' ), mysql2date( 'j M Y', (string) $oria_live['until'] ) )
					: __( 'Your offer is live. It has no end date, so remember to remove it when it finishes.', 'oria' )
			);
			?>
			<a href="<?php echo esc_url( home_url( '/offers/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See the offers page', 'oria' ); ?></a>
		</p>
	<?php elseif ( $oria_live && ! empty( $oria_live['advertised'] ) ) : ?>
		<p class="myoffer__state" role="status"><?php esc_html_e( 'Your listing shows an offer we found on your website. Fill in the form to replace it with your own wording, or empty the first line and save to take it down.', 'oria' ); ?></p>
	<?php endif; ?>

	<details class="myoffer__rules"<?php echo $oria_live || $oria_p ? '' : ' open'; ?>>
		<summary><?php esc_html_e( 'Where your offer shows, and what we check', 'oria' ); ?></summary>
		<ul>
			<li><?php esc_html_e( 'It shows on your profile, on the category pages you appear in, and on the Oria Haven offers pages, where people can filter and compare offers.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Adding an offer is free. It’s labelled as coming from your business, with the date you last updated it.', 'oria' ); ?></li>
			<?php if ( $oria_review ) : ?>
				<li><?php esc_html_e( 'We check your first offer before it goes live. After that, your changes show straight away.', 'oria' ); ?></li>
			<?php endif; ?>
			<li><?php esc_html_e( 'It must be a real offer you are running now, with the price and conditions somebody would meet at the desk. No claims about treating health conditions.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Add an end date if it has one: it comes off the day after. Taking it down is instant: empty the first line and save.', 'oria' ); ?></li>
		</ul>
	</details>
</div>
