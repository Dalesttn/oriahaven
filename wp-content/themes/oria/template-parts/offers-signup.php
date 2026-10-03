<?php
/**
 * Sign up for the weekly offers email. One field, posted to admin-post
 * without JavaScript or to the REST route with it (assets/js/offers-signup.js
 * swaps the form for a thank-you in place). The interest (a practice
 * category slug) and listing travel as hidden fields so the list can be
 * segmented later; the consent sentence is the one stored with the row.
 *
 * $args: source ('category' | 'listing' | 'offer_click'), interest (practice
 *        slug), listing (int), suburb (string), hidden (bool -- rendered
 *        collapsed, shown by the script after an offer click).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Core\Subscribers\consent_text' ) ) {
	return;
}
$oria_src    = in_array( (string) ( $args['source'] ?? '' ), array( 'category', 'listing', 'offer_click' ), true ) ? (string) $args['source'] : 'other';
$oria_int    = sanitize_key( (string) ( $args['interest'] ?? '' ) );
$oria_lst    = (int) ( $args['listing'] ?? 0 );
$oria_sbb    = (string) ( $args['suburb'] ?? '' );
$oria_hidden = ! empty( $args['hidden'] );
$oria_state  = sanitize_key( (string) ( $_GET['osub'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_uid    = 'osub-' . wp_unique_id();

wp_enqueue_script( 'oria-offers-signup', get_template_directory_uri() . '/assets/js/offers-signup.js', array(), (string) filemtime( get_theme_file_path( 'assets/js/offers-signup.js' ) ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
wp_localize_script( 'oria-offers-signup', 'ORIA_SUB', array( 'url' => rest_url( 'oria/v1/subscribe' ) ) );
?>
<div class="osub<?php echo $oria_hidden ? ' osub--after' : ''; ?>" id="offers-signup" data-osub<?php echo $oria_hidden ? ' hidden' : ''; ?>>
	<?php if ( 'ok' === $oria_state && ! $oria_hidden ) : ?>
		<p class="osub__done"><strong><?php esc_html_e( 'You’re on the list.', 'oria' ); ?></strong> <?php esc_html_e( 'The next offers email comes out this week.', 'oria' ); ?></p>
	<?php else : ?>
		<form class="osub__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-osub-form>
			<input type="hidden" name="action" value="oria_subscribe">
			<input type="hidden" name="sub_source" value="<?php echo esc_attr( $oria_src ); ?>">
			<input type="hidden" name="sub_interest" value="<?php echo esc_attr( $oria_int ); ?>">
			<input type="hidden" name="sub_listing" value="<?php echo (int) $oria_lst; ?>">
			<input type="hidden" name="sub_suburb" value="<?php echo esc_attr( $oria_sbb ); ?>">
			<input type="hidden" name="oform_ts" value="<?php echo esc_attr( (string) time() ); ?>">
			<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="osub__hp">
			<label class="osub__label" for="<?php echo esc_attr( $oria_uid ); ?>">
				<?php echo esc_html( $oria_hidden ? __( 'Want the next one? New Perth wellness offers, weekly.', 'oria' ) : __( 'New Perth wellness offers, weekly', 'oria' ) ); ?>
			</label>
			<div class="osub__row">
				<input class="osub__input" type="email" id="<?php echo esc_attr( $oria_uid ); ?>" name="sub_email" required autocomplete="email" inputmode="email" placeholder="<?php esc_attr_e( 'you@example.com', 'oria' ); ?>">
				<button class="btn btn--dark osub__btn" type="submit"><?php esc_html_e( 'Send me offers', 'oria' ); ?></button>
			</div>
			<p class="osub__consent"><?php echo esc_html( \Oria\Core\Subscribers\consent_text() ); ?></p>
			<?php if ( 'invalid' === $oria_state || 'error' === $oria_state || 'throttled' === $oria_state ) : ?>
				<p class="osub__err" role="alert"><?php esc_html_e( 'That didn’t go through — check the address and try again.', 'oria' ); ?></p>
			<?php endif; ?>
		</form>
	<?php endif; ?>
</div>
