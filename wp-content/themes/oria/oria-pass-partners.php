<?php
/**
 * /oria-pass/partners/ — the studio side.
 *
 * Same waitlist table, kind = partner. A separate page rather than a second
 * form on the landing page because the audiences want different words: a
 * member is being sold discovery, a studio is being sold spare capacity and
 * needs to hear "you stay in control" before anything else.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Pass\Settings;
use Oria\Pass\Waitlist;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_state = sanitize_key( (string) ( $_GET['pass'] ?? '' ) );
// phpcs:enable

get_header();
?>

<div class="pass pass--partners">

	<header class="pass-hero pass-hero--slim">
		<div class="wrap pass-hero__in pass-hero__in--one">
			<div class="pass-hero__say">
				<p class="micro pass-hero__eyebrow"><?php esc_html_e( 'Oria Pass · For studios', 'oria' ); ?></p>
				<h1 class="pass-hero__title"><?php esc_html_e( 'Put your quiet hours to work.', 'oria' ); ?></h1>
				<p class="pass-hero__lede">
					<?php esc_html_e( 'Oria Pass members are Perth locals looking for something new to try. You decide which sessions they can book, how many places, and what each one is worth. Everything else about how you run your business stays exactly as it is.', 'oria' ); ?>
				</p>
			</div>
		</div>
	</header>

	<section class="wrap pass-how" aria-labelledby="prov-how-h">
		<h2 class="pass-h" id="prov-how-h"><?php esc_html_e( 'How it would work for you', 'oria' ); ?></h2>
		<ol class="pass-how__list">
			<?php
			$oria_steps = array(
				array( __( 'You pick the sessions', 'oria' ), __( 'Usually the quiet ones. A Tuesday 2pm, not a Saturday 9am.', 'oria' ) ),
				array( __( 'You set the places', 'oria' ), __( 'Two spots on a class of twelve, if that is all you want to give.', 'oria' ) ),
				array( __( 'Members book with credits', 'oria' ), __( 'They turn up like any other client. You mark them off.', 'oria' ) ),
				array( __( 'You get paid', 'oria' ), __( 'An agreed amount per attended booking, paid on a regular cycle.', 'oria' ) ),
			);
			foreach ( $oria_steps as $oria_i => $oria_s ) :
				?>
				<li class="hstep">
					<span class="hstep__n"><?php echo esc_html( sprintf( '%02d', $oria_i + 1 ) ); ?></span>
					<h3 class="hstep__h"><?php echo esc_html( $oria_s[0] ); ?></h3>
					<p><?php echo esc_html( $oria_s[1] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
		<p class="pass-note">
			<?php esc_html_e( 'We are not promising you bookings. We are building the thing that could bring them, and looking for studios who want a say in how it works.', 'oria' ); ?>
		</p>
	</section>

	<section class="wrap pass-join" id="pass-join" aria-labelledby="prov-join-h">
		<div class="pjoin">
			<?php if ( 'done' === $oria_state ) : ?>
				<h2 class="pass-h" id="prov-join-h"><?php esc_html_e( 'Thanks — we will be in touch.', 'oria' ); ?></h2>
				<p class="pjoin__lede"><?php esc_html_e( 'We will come back to you about what opening a few spaces would look like. There is no cost to be listed on Oria Haven either way.', 'oria' ); ?></p>
			<?php else : ?>
				<h2 class="pass-h" id="prov-join-h"><?php esc_html_e( 'Become a founding partner', 'oria' ); ?></h2>
				<p class="pjoin__lede"><?php esc_html_e( 'Tell us a little about your studio and we will start the conversation. Founding partners help set how this works.', 'oria' ); ?></p>

				<form class="pjoin__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="oria_pass_waitlist">
					<input type="hidden" name="kind" value="partner">
					<input type="hidden" name="oria_ts" value="<?php echo esc_attr( (string) time() ); ?>">
					<?php wp_nonce_field( 'oria_pass_waitlist', 'oria_pass_nonce' ); ?>
					<input type="text" name="oria_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="pjoin__hp">

					<div class="pjoin__row">
						<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span>
							<input class="input" type="text" name="name" required autocomplete="name"></label>
						<label class="field"><span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
							<input class="input" type="email" name="email" required autocomplete="email"></label>
					</div>
					<div class="pjoin__row">
						<label class="field"><span class="field__label"><?php esc_html_e( 'Business name', 'oria' ); ?></span>
							<input class="input" type="text" name="business" autocomplete="organization"></label>
						<label class="field"><span class="field__label"><?php esc_html_e( 'Suburb', 'oria' ); ?></span>
							<input class="input" type="text" name="suburb" autocomplete="address-level2"></label>
					</div>
					<label class="field"><span class="field__label"><?php esc_html_e( 'What would you be able to open up? (optional)', 'oria' ); ?></span>
						<textarea class="textarea" name="note" rows="3"
							placeholder="<?php esc_attr_e( 'e.g. two spots on weekday morning classes', 'oria' ); ?>"></textarea></label>

					<label class="check pjoin__consent">
						<input type="checkbox" name="consent" value="1" required>
						<span><?php echo esc_html( Waitlist\consent_text( 'partner' ) ); ?></span>
					</label>

					<button class="btn btn--dark btn--block" type="submit" data-oria-event="oria_pass_partner_signup">
						<?php esc_html_e( 'Start the conversation', 'oria' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
	</section>

</div>

<?php
get_footer();
