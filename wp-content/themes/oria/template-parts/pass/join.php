<?php
/**
 * The waitlist form — the only thing on the landing page that does something.
 *
 * Short on purpose. Name and email are all that is needed to tell somebody
 * the Pass has opened; suburb and interests are optional because they are
 * for Oria's benefit, not the visitor's.
 *
 * The consent checkbox is required, never pre-ticked, and its exact wording
 * is stored on the row, so what somebody agreed to can be shown later
 * rather than assumed. After a refused submission the handler hands back
 * what was typed (Waitlist\recall), so an error never costs the typing.
 *
 * Live mode with a Stripe link buys rather than collects. Live mode
 * without one still shows the waitlist: a "Join" button that leads nowhere
 * is worse than asking somebody to wait.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Pass\Waitlist;

$oria_live  = ! empty( $args['live'] );
$oria_mode  = (string) ( $args['mode'] ?? 'waitlist' );
$oria_state = (string) ( $args['state'] ?? '' );
$oria_kind  = (string) ( $args['kind'] ?? 'member' );
$oria_buy   = $oria_live && function_exists( '\Oria\Pass\Stripe\configured' ) && \Oria\Pass\Stripe\configured();
$oria_cta   = $oria_live && ! $oria_buy ? __( 'Join the waitlist', 'oria' ) : (string) ( $args['cta'] ?? __( 'Join the waitlist', 'oria' ) );
$oria_kept  = function_exists( '\Oria\Pass\Waitlist\recall' ) ? Waitlist\recall() : array();
$oria_picks = array_flip( (array) ( $oria_kept['interests'] ?? array() ) );

$oria_privacy = (string) \Oria\Pass\Settings\get( 'privacy_url' );
if ( '' === $oria_privacy ) {
	$oria_privacy = (string) get_privacy_policy_url();
}

$oria_errors = array(
	'expired' => __( 'That form sat open too long. Please send it again.', 'oria' ),
	'spam'    => __( 'That was sent very quickly, so it looked automated. Please check your details and send it again.', 'oria' ),
	'name'    => __( 'Add your name so we know who to email.', 'oria' ),
	'email'   => __( 'That email address does not look right. Check it and try again.', 'oria' ),
	'consent' => __( 'Tick the box to let us email you about the launch.', 'oria' ),
	'server'  => __( 'Something went wrong on our side. Please try again in a minute.', 'oria' ),
);
?>

<section class="oria-pl__section oria-pl__section--tight" id="pass-join" aria-labelledby="pass-join-h" data-pl-join>
	<div class="oria-pl__container">
		<div class="oria-pl__join">

			<?php if ( $oria_buy ) : ?>
				<?php $oria_uid = get_current_user_id(); ?>
				<h2 id="pass-join-h"><?php esc_html_e( 'Start your Oria Pass', 'oria' ); ?></h2>
				<?php if ( $oria_uid > 0 ) : ?>
					<p class="oria-pl__copy">
						<?php
						printf(
							/* translators: 1: credits, 2: price, 3: period */
							esc_html__( '%1$d credits land in your account as soon as the first payment goes through, then again every %3$s. %2$s a %3$s, cancel whenever you like.', 'oria' ),
							(int) \Oria\Pass\Settings\get( 'credits_per_cycle' ),
							esc_html( (string) \Oria\Pass\Settings\get( 'price_display' ) ),
							esc_html( (string) \Oria\Pass\Settings\get( 'price_period' ) )
						);
						?>
					</p>
					<p class="oria-pl__join-acts">
						<a class="oria-pl__button oria-pl__button--primary" href="<?php echo esc_url( \Oria\Pass\Stripe\pay_url( $oria_uid ) ); ?>"
							data-oria-event="oria_pass_checkout_started"><?php esc_html_e( 'Set up my membership', 'oria' ); ?></a>
					</p>
					<p class="oria-pl__fine"><?php esc_html_e( 'Payment is handled by Stripe. Your card details never touch Oria Haven.', 'oria' ); ?></p>
				<?php else : ?>
					<p class="oria-pl__copy"><?php esc_html_e( 'Your Pass lives in your Oria account, so make one first. It takes a moment, and your credits and bookings will be waiting there.', 'oria' ); ?></p>
					<p class="oria-pl__join-acts">
						<a class="oria-pl__button oria-pl__button--primary" href="<?php echo esc_url( home_url( '/my-oria/' ) ); ?>"><?php esc_html_e( 'Create an account', 'oria' ); ?></a>
						<a class="oria-pl__textlink" href="<?php echo esc_url( home_url( '/my-oria/' ) ); ?>"><?php esc_html_e( 'Already have one? Sign in', 'oria' ); ?></a>
					</p>
				<?php endif; ?>

			<?php elseif ( 'done' === $oria_state && 'partner' !== $oria_kind ) : ?>

				<h2 id="pass-join-h" tabindex="-1" data-pl-done><?php esc_html_e( 'You are on the waitlist.', 'oria' ); ?></h2>
				<p class="oria-pl__copy"><?php esc_html_e( 'We will email you when there is news about the launch.', 'oria' ); ?></p>
				<p class="oria-pl__join-acts">
					<a class="oria-pl__button oria-pl__button--secondary" href="<?php echo esc_url( home_url( '/explore/perth/' ) ); ?>"><?php esc_html_e( 'Explore wellness in Perth', 'oria' ); ?></a>
				</p>

			<?php else : ?>

				<h2 id="pass-join-h"><?php esc_html_e( 'Be there for the beginning.', 'oria' ); ?></h2>
				<p class="oria-pl__copy">
					<?php
					if ( 'invite' === $oria_mode ) {
						esc_html_e( 'Ask for early access and we will be in touch about launch. You can also tell us which experiences and areas you would like us to include.', 'oria' );
					} else {
						esc_html_e( 'Join the waitlist for launch updates. You can also tell us which experiences and areas you would like us to include.', 'oria' );
					}
					?>
				</p>

				<?php if ( '' !== $oria_state && isset( $oria_errors[ $oria_state ] ) ) : ?>
					<p class="oria-pl__error" role="alert"><?php echo esc_html( $oria_errors[ $oria_state ] ); ?></p>
				<?php endif; ?>

				<form class="oria-pl__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					data-oria-event="oria_pass_waitlist_submit">
					<input type="hidden" name="action" value="oria_pass_waitlist">
					<input type="hidden" name="kind" value="member">
					<input type="hidden" name="oria_ts" value="<?php echo esc_attr( (string) time() ); ?>">
					<?php wp_nonce_field( 'oria_pass_waitlist', 'oria_pass_nonce' ); ?>
					<input type="text" name="oria_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="oria-pl__hp">

					<div class="oria-pl__row">
						<p class="oria-pl__field">
							<label for="pl-name"><?php esc_html_e( 'Your name', 'oria' ); ?></label>
							<input id="pl-name" type="text" name="name" required autocomplete="given-name"
								value="<?php echo esc_attr( (string) ( $oria_kept['name'] ?? '' ) ); ?>"<?php echo 'name' === $oria_state ? ' aria-invalid="true"' : ''; ?>>
						</p>
						<p class="oria-pl__field">
							<label for="pl-email"><?php esc_html_e( 'Email', 'oria' ); ?></label>
							<input id="pl-email" type="email" name="email" required autocomplete="email"
								value="<?php echo esc_attr( (string) ( $oria_kept['email'] ?? '' ) ); ?>"<?php echo 'email' === $oria_state ? ' aria-invalid="true"' : ''; ?>>
						</p>
					</div>

					<p class="oria-pl__field oria-pl__field--narrow">
						<label for="pl-suburb"><?php esc_html_e( 'Your suburb', 'oria' ); ?> <span class="oria-pl__opt"><?php esc_html_e( '(optional)', 'oria' ); ?></span></label>
						<input id="pl-suburb" type="text" name="suburb" autocomplete="address-level2"
							value="<?php echo esc_attr( (string) ( $oria_kept['suburb'] ?? '' ) ); ?>">
					</p>

					<fieldset class="oria-pl__picks">
						<legend><?php esc_html_e( 'What would you like to try?', 'oria' ); ?> <span class="oria-pl__opt"><?php esc_html_e( '(optional, choose any)', 'oria' ); ?></span></legend>
						<div class="oria-pl__chips">
							<?php foreach ( Waitlist\INTERESTS as $oria_slug => $oria_label ) : ?>
								<label class="oria-pl__chip">
									<input type="checkbox" name="interests[]" value="<?php echo esc_attr( $oria_slug ); ?>"<?php checked( isset( $oria_picks[ $oria_slug ] ) ); ?>>
									<span><?php echo esc_html( $oria_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<label class="oria-pl__consent">
						<input type="checkbox" name="consent" value="1" required<?php echo 'consent' === $oria_state ? ' aria-invalid="true"' : ''; ?>>
						<span>
							<?php echo esc_html( Waitlist\consent_text( 'member' ) ); ?>
							<?php if ( '' !== $oria_privacy ) : ?>
								<a href="<?php echo esc_url( $oria_privacy ); ?>"><?php esc_html_e( 'Privacy policy', 'oria' ); ?></a>
							<?php endif; ?>
						</span>
					</label>

					<button class="oria-pl__button oria-pl__button--primary oria-pl__submit" type="submit"><?php echo esc_html( $oria_cta ); ?></button>
					<p class="oria-pl__fine"><?php esc_html_e( 'Joining the waitlist is not a paid membership. No card, no charge, and we will not pass your details to anybody.', 'oria' ); ?></p>
				</form>

			<?php endif; ?>

		</div>
	</div>
</section>
