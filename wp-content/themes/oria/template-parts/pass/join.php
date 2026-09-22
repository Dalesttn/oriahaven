<?php
/**
 * The waitlist form — the only thing on this page that does something.
 *
 * Short on purpose. Name and email are all that is needed to tell somebody
 * the Pass has opened; suburb and interests are optional because they are
 * for Oria's benefit, not the visitor's, and a required field asked for our
 * convenience is how a good conversion rate becomes a bad one.
 *
 * The consent checkbox is required and its exact wording is stored on the
 * row, so what somebody agreed to can be shown later rather than assumed.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Pass\Waitlist;

$oria_live  = ! empty( $args['live'] );
$oria_state = (string) ( $args['state'] ?? '' );
$oria_kind  = (string) ( $args['kind'] ?? 'member' );
$oria_cta   = (string) ( $args['cta'] ?? __( 'Join the waitlist', 'oria' ) );

$oria_errors = array(
	'expired' => __( 'That form sat open too long — please send it again.', 'oria' ),
	'spam'    => __( 'That looked automated. If you are human, try once more a little slower.', 'oria' ),
	'name'    => __( 'We need a name to put on the list.', 'oria' ),
	'email'   => __( 'That email address does not look right.', 'oria' ),
	'consent' => __( 'We need your permission before we can email you.', 'oria' ),
	'server'  => __( 'Something went wrong on our side. Please try again in a minute.', 'oria' ),
);
?>

<section class="wrap pass-join" id="pass-join" aria-labelledby="pass-join-h">
	<div class="pjoin">

		<?php if ( 'done' === $oria_state ) : ?>

			<h2 class="pass-h" id="pass-join-h"><?php esc_html_e( 'You are on the list.', 'oria' ); ?></h2>
			<p class="pjoin__lede">
				<?php if ( 'partner' === $oria_kind ) : ?>
					<?php esc_html_e( 'Thanks — we will be in touch about what opening up a few spaces would look like for your business. No obligation, and no cost to be listed.', 'oria' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Thanks. We will email you when Oria Pass opens in Perth — and nothing else in the meantime.', 'oria' ); ?>
				<?php endif; ?>
			</p>
			<p class="pjoin__after">
				<a class="btn btn--sm btn--ghost" href="<?php echo esc_url( home_url( '/explore/perth/' ) ); ?>">
					<?php esc_html_e( 'Have a look around while you wait', 'oria' ); ?>
				</a>
			</p>

		<?php else : ?>

			<h2 class="pass-h" id="pass-join-h">
				<?php if ( $oria_live ) : ?>
					<?php esc_html_e( 'Start your Oria Pass', 'oria' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Want it when it opens?', 'oria' ); ?>
				<?php endif; ?>
			</h2>

			<p class="pjoin__lede">
				<?php if ( $oria_live ) : ?>
					<?php esc_html_e( 'Set up your membership and your credits land straight away.', 'oria' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Oria Pass is not open yet. Leave your details and you will be among the first to hear — telling us what you would use it for genuinely shapes which studios we approach.', 'oria' ); ?>
				<?php endif; ?>
			</p>

			<?php if ( '' !== $oria_state && isset( $oria_errors[ $oria_state ] ) ) : ?>
				<p class="pjoin__err" role="alert"><?php echo esc_html( $oria_errors[ $oria_state ] ); ?></p>
			<?php endif; ?>

			<form class="pjoin__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				data-oria-event="oria_pass_waitlist_submit">
				<input type="hidden" name="action" value="oria_pass_waitlist">
				<input type="hidden" name="kind" value="member">
				<input type="hidden" name="oria_ts" value="<?php echo esc_attr( (string) time() ); ?>">
				<?php wp_nonce_field( 'oria_pass_waitlist', 'oria_pass_nonce' ); ?>
				<input type="text" name="oria_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="pjoin__hp">

				<div class="pjoin__row">
					<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span>
						<input class="input" type="text" name="name" required autocomplete="given-name"></label>
					<label class="field"><span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
						<input class="input" type="email" name="email" required autocomplete="email"></label>
				</div>

				<label class="field"><span class="field__label"><?php esc_html_e( 'Your suburb (optional)', 'oria' ); ?></span>
					<input class="input" type="text" name="suburb" autocomplete="address-level2"
						placeholder="<?php esc_attr_e( 'Fremantle', 'oria' ); ?>"></label>

				<fieldset class="pjoin__picks">
					<legend class="field__label"><?php esc_html_e( 'What would you use it for? (optional)', 'oria' ); ?></legend>
					<div class="pjoin__opts">
						<?php foreach ( Waitlist\INTERESTS as $oria_slug => $oria_label ) : ?>
							<label class="pjoin__opt">
								<input type="checkbox" name="interests[]" value="<?php echo esc_attr( $oria_slug ); ?>">
								<span><?php echo esc_html( $oria_label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<label class="check pjoin__consent">
					<input type="checkbox" name="consent" value="1" required>
					<span><?php echo esc_html( Waitlist\consent_text( 'member' ) ); ?></span>
				</label>

				<button class="btn btn--dark btn--block" type="submit"><?php echo esc_html( $oria_cta ); ?></button>
				<p class="pjoin__fine"><?php esc_html_e( 'No card, no charge, and we will not pass your address to anybody.', 'oria' ); ?></p>
			</form>

		<?php endif; ?>

	</div>
</section>
