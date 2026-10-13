<?php
/**
 * Sign up for "Your Perth weekend", the Thursday email. The same form
 * machinery as the offers signup (offers-signup.js, the /subscribe route,
 * the subscribers table) but its own list and its own consent: joining
 * this never puts anybody on the offers email, or the other way round.
 *
 * Email first; the area and the interest are optional and folded away, so
 * the form is one field for anybody who just wants it.
 *
 * $args: source ('weekend' | 'home'), heading (bool -- false when the
 *        surrounding section already has one).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Core\Subscribers\consent_text' ) ) {
	return;
}

$oria_src   = in_array( (string) ( $args['source'] ?? '' ), array( 'weekend', 'home' ), true ) ? (string) $args['source'] : 'weekend';
$oria_state = isset( $_GET['osub_list'] ) && 'weekend' === $_GET['osub_list'] ? sanitize_key( (string) ( $_GET['osub'] ?? '' ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_uid   = 'wsub-' . wp_unique_id();

// The Perth regions and the busiest categories: short lists, so a select
// stays quick to use on a phone.
$oria_perth   = get_term_by( 'slug', 'perth', 'area' );
$oria_regions = $oria_perth instanceof WP_Term ? get_terms( array( 'taxonomy' => 'area', 'parent' => (int) $oria_perth->term_id, 'hide_empty' => false ) ) : array();
$oria_kinds   = get_terms( array( 'taxonomy' => 'practice', 'parent' => 0, 'orderby' => 'count', 'order' => 'DESC', 'number' => 10 ) );

wp_enqueue_script( 'oria-offers-signup', get_template_directory_uri() . '/assets/js/offers-signup.js', array(), (string) filemtime( get_theme_file_path( 'assets/js/offers-signup.js' ) ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
wp_localize_script( 'oria-offers-signup', 'ORIA_SUB', array( 'url' => rest_url( 'oria/v1/subscribe' ), 'weekend' => home_url( '/this-weekend/' ) ) );
?>
<div class="wsub" id="weekend-signup" data-osub data-osub-list="weekend">
	<div class="wsub__intro">
		<p class="micro"><?php esc_html_e( 'Your Perth weekend', 'oria' ); ?></p>
		<?php if ( ! isset( $args['heading'] ) || $args['heading'] ) : ?>
			<h2 class="h3 wsub__h"><?php esc_html_e( 'A few good things to do for yourself, every Thursday.', 'oria' ); ?></h2>
		<?php endif; ?>
		<p class="wsub__lede"><?php esc_html_e( 'Three weekend picks, one free or low-cost option, one for first-timers and one checked offer. Short, and only from places we have checked.', 'oria' ); ?></p>
	</div>

	<?php if ( 'ok' === $oria_state ) : ?>
		<p class="osub__done wsub__done" role="status"><strong><?php esc_html_e( 'You’re in.', 'oria' ); ?></strong> <?php esc_html_e( 'The first one arrives on Thursday.', 'oria' ); ?> <a href="<?php echo esc_url( home_url( '/this-weekend/' ) ); ?>"><?php esc_html_e( 'See what’s on this weekend', 'oria' ); ?> &rarr;</a></p>
	<?php else : ?>
		<form class="osub__form wsub__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-osub-form>
			<input type="hidden" name="action" value="oria_subscribe">
			<input type="hidden" name="sub_list" value="weekend">
			<input type="hidden" name="sub_source" value="<?php echo esc_attr( $oria_src ); ?>">
			<input type="hidden" name="sub_listing" value="0">
			<input type="hidden" name="oform_ts" value="<?php echo esc_attr( (string) time() ); ?>">
			<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="osub__hp">
			<label class="osub__label" for="<?php echo esc_attr( $oria_uid ); ?>"><?php esc_html_e( 'Your email', 'oria' ); ?></label>
			<div class="osub__row">
				<input class="osub__input" type="email" id="<?php echo esc_attr( $oria_uid ); ?>" name="sub_email" required autocomplete="email" inputmode="email" placeholder="<?php esc_attr_e( 'you@example.com', 'oria' ); ?>">
				<button class="btn btn--dark osub__btn" type="submit"><?php esc_html_e( 'Send me the weekend', 'oria' ); ?></button>
			</div>
			<?php if ( $oria_regions || $oria_kinds ) : ?>
				<details class="wsub__more">
					<summary><?php esc_html_e( 'Tailor it (optional)', 'oria' ); ?></summary>
					<div class="wsub__prefs">
						<?php if ( $oria_regions && ! is_wp_error( $oria_regions ) ) : ?>
							<label class="wsub__pref"><span><?php esc_html_e( 'Your side of Perth', 'oria' ); ?></span>
								<select name="sub_suburb">
									<option value=""><?php esc_html_e( 'Anywhere', 'oria' ); ?></option>
									<?php foreach ( $oria_regions as $oria_r ) : ?>
										<option value="<?php echo esc_attr( $oria_r->slug ); ?>"><?php echo esc_html( $oria_r->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endif; ?>
						<?php if ( $oria_kinds && ! is_wp_error( $oria_kinds ) ) : ?>
							<label class="wsub__pref"><span><?php esc_html_e( 'Most interested in', 'oria' ); ?></span>
								<select name="sub_interest">
									<option value=""><?php esc_html_e( 'A bit of everything', 'oria' ); ?></option>
									<?php foreach ( $oria_kinds as $oria_k ) : ?>
										<option value="<?php echo esc_attr( $oria_k->slug ); ?>"><?php echo esc_html( $oria_k->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endif; ?>
					</div>
				</details>
			<?php endif; ?>
			<p class="osub__consent"><?php echo esc_html( \Oria\Core\Subscribers\consent_text( 'weekend' ) ); ?></p>
			<?php if ( in_array( $oria_state, array( 'invalid', 'error', 'throttled' ), true ) ) : ?>
				<p class="osub__err" role="alert"><?php echo esc_html( 'throttled' === $oria_state ? __( 'Too many tries from here just now. Give it an hour and try again.', 'oria' ) : __( 'That didn’t go through. Check the address and try again.', 'oria' ) ); ?></p>
			<?php endif; ?>
		</form>
	<?php endif; ?>
</div>
