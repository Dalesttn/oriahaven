<?php
/**
 * /oria-pass/ — the Oria Pass landing page.
 *
 * Rendered by a route in the oria-pass plugin, not by a WordPress page, so
 * it exists wherever the code does.
 *
 * The page answers five questions in order: what the Pass is, how it would
 * work, what a month of credits could look like, what somebody might try,
 * and what they can do now. Businesses get their own banner after all of
 * that, so the consumer page never reads as a sales pitch to studios.
 *
 * Every number comes from the Pass settings -- price, credits, the example
 * costs -- so the hero, the example and the FAQ cannot disagree. Nothing
 * here looks like availability: no times, no places left, no "Book now".
 * While the mode is Waitlist or Invite there is nothing to buy, and the
 * copy is written in the future tense to match.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Pass\Settings;

$oria_mode    = Settings\mode();
$oria_live    = Settings\is_live();
$oria_price   = (string) Settings\get( 'price_display' );
$oria_period  = (string) Settings\get( 'price_period' );
$oria_credits = (int) Settings\get( 'credits_per_cycle' );
$oria_city    = (string) Settings\get( 'city' );
$oria_cta     = Settings\cta_label();
$oria_samples = Settings\sample_costs();

// Live without a payment link cannot sell: every button asks to wait instead.
if ( $oria_live && ! ( function_exists( '\Oria\Pass\Stripe\configured' ) && \Oria\Pass\Stripe\configured() ) ) {
	$oria_cta = __( 'Join the waitlist', 'oria' );
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_state = sanitize_key( (string) ( $_GET['pass'] ?? '' ) );
$oria_kind  = 'partner' === ( $_GET['kind'] ?? '' ) ? 'partner' : 'member';
// phpcs:enable

$oria_args = array(
	'mode'    => $oria_mode,
	'live'    => $oria_live,
	'price'   => $oria_price,
	'period'  => $oria_period,
	'credits' => $oria_credits,
	'city'    => $oria_city,
	'cta'     => $oria_cta,
	'state'   => $oria_state,
	'kind'    => $oria_kind,
);

$oria_dir = get_template_directory();
$oria_uri = get_template_directory_uri();
wp_enqueue_style( 'oria-pass-landing', $oria_uri . '/assets/css/pass-landing.css', array(), (string) filemtime( $oria_dir . '/assets/css/pass-landing.css' ) );
wp_enqueue_script( 'oria-pass-landing', $oria_uri . '/assets/js/pass-landing.js', array(), (string) filemtime( $oria_dir . '/assets/js/pass-landing.js' ), array( 'in_footer' => true ) );

switch ( $oria_mode ) {
	case 'live':
		/* translators: %s: city */
		$oria_status = sprintf( __( 'Now open in %s · Participating businesses', 'oria' ), $oria_city );
		break;
	case 'invite':
		/* translators: %s: city */
		$oria_status = sprintf( __( 'Coming to %s · Early access by request', 'oria' ), $oria_city );
		break;
	default:
		/* translators: %s: city */
		$oria_status = sprintf( __( 'Coming to %s · Waitlist open', 'oria' ), $oria_city );
}

/* Experience types: possibilities, each linking to the directory as it is. */
$oria_kinds = array(
	array( 'yoga', __( 'Yoga', 'oria' ), __( 'Explore different styles of movement and practice.', 'oria' ), 'yoga/', 'yoga' ),
	array( 'fitness', __( 'Pilates', 'oria' ), __( 'Get to know mat and reformer formats.', 'oria' ), 'fitness/pilates/', 'Pilates' ),
	array( 'spa', __( 'Sauna', 'oria' ), __( 'Learn what a sauna session involves.', 'oria' ), 'spa/infrared-sauna/', 'sauna' ),
	array( 'recovery', __( 'Float', 'oria' ), __( 'Find out what to expect from a float session.', 'oria' ), 'sound/float-therapy/', 'float therapy' ),
	array( 'breathwork', __( 'Breathwork', 'oria' ), __( 'Explore guided breathing formats.', 'oria' ), 'breathwork/', 'breathwork' ),
	array( 'sound', __( 'Sound sessions', 'oria' ), __( 'Discover sessions centred on sound and listening.', 'oria' ), 'sound/sound-healing/', 'sound sessions' ),
);

/* The example mix starts with the first three chosen, as long as they fit. */
$oria_pressed = array();
$oria_used    = 0;
foreach ( array_slice( array_keys( $oria_samples ), 0, 3 ) as $oria_slug ) {
	if ( $oria_used + $oria_samples[ $oria_slug ]['cost'] <= $oria_credits ) {
		$oria_pressed[ $oria_slug ] = true;
		$oria_used                 += $oria_samples[ $oria_slug ]['cost'];
	}
}

$oria_arrow = function_exists( '\Oria\Theme\arrow' ) ? \Oria\Theme\arrow() : ' &rarr;';

get_header();
?>

<div class="oria-pl" data-oria-event="oria_pass_view" data-pass-mode="<?php echo esc_attr( $oria_mode ); ?>">

	<?php /* A. What it is, with the plan on the first screen. */ ?>
	<section class="oria-pl__section oria-pl__section--hero" aria-labelledby="pl-hero-h">
		<div class="oria-pl__container oria-pl__hero">
			<div class="oria-pl__hero-text">
				<p class="oria-pl__eyebrow"><?php echo esc_html( $oria_status ); ?></p>
				<h1 id="pl-hero-h"><?php esc_html_e( 'Try something new. Find what fits.', 'oria' ); ?></h1>
				<p class="oria-pl__lede">
					<?php
					if ( $oria_live ) {
						/* translators: %s: city */
						printf( esc_html__( 'A membership to explore different wellness experiences with participating %s businesses. A chance to discover what you enjoy and what you want to return to.', 'oria' ), esc_html( $oria_city ) );
					} else {
						/* translators: %s: city */
						printf( esc_html__( 'An upcoming membership to explore different wellness experiences with participating %s businesses. A chance to discover what you enjoy and what you want to return to.', 'oria' ), esc_html( $oria_city ) );
					}
					?>
				</p>
				<div class="oria-pl__actions" data-pl-hero-cta>
					<a class="oria-pl__button oria-pl__button--primary" href="#pass-join" data-oria-event="oria_pass_join_click"><?php echo esc_html( $oria_cta ); ?><?php echo $oria_arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
					<a class="oria-pl__textlink" href="#pass-how"><?php echo esc_html( $oria_live ? __( 'See how it works', 'oria' ) : __( 'See how it will work', 'oria' ) ); ?></a>
				</div>
				<p class="oria-pl__reassure">
					<?php
					if ( $oria_live ) {
						esc_html_e( 'Cancel your renewal whenever you like.', 'oria' );
					} elseif ( 'invite' === $oria_mode ) {
						esc_html_e( 'No payment to request early access.', 'oria' );
					} else {
						esc_html_e( 'No payment to join the waitlist.', 'oria' );
					}
					?>
				</p>
			</div>

			<aside class="oria-pl__card" aria-label="<?php echo esc_attr( $oria_live ? __( 'Oria Pass membership', 'oria' ) : __( 'Planned Oria Pass membership', 'oria' ) ); ?>">
				<div class="oria-pl__card-top">
					<span class="oria-pl__card-brand"><?php esc_html_e( 'Oria', 'oria' ); ?> <em><?php esc_html_e( 'Pass', 'oria' ); ?></em></span>
					<span class="oria-pl__card-badge"><?php echo esc_html( $oria_live ? __( 'Membership', 'oria' ) : __( 'Planned membership', 'oria' ) ); ?></span>
				</div>
				<p class="oria-pl__card-price">
					<?php echo esc_html( $oria_price ); ?>
					<small>
						<?php
						/* translators: %s: billing period, e.g. month */
						printf( esc_html__( 'AUD / %s', 'oria' ), esc_html( $oria_period ) );
						?>
					</small>
				</p>
				<p class="oria-pl__card-credits">
					<?php
					/* translators: 1: number of credits, 2: billing period */
					printf( esc_html__( '%1$d credits each %2$s', 'oria' ), (int) $oria_credits, esc_html( $oria_period ) );
					?>
				</p>
				<div class="oria-pl__card-foot">
					<p><?php esc_html_e( 'For eligible experiences at participating businesses.', 'oria' ); ?></p>
					<?php if ( ! $oria_live ) : ?>
						<p><?php esc_html_e( 'Not available to purchase yet.', 'oria' ); ?></p>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</section>

	<?php /* B. How it will work. */ ?>
	<section class="oria-pl__section oria-pl__section--rule" id="pass-how" aria-labelledby="pl-how-h">
		<div class="oria-pl__container">
			<p class="oria-pl__eyebrow"><?php echo esc_html( $oria_live ? __( 'How it works', 'oria' ) : __( 'The planned experience', 'oria' ) ); ?></p>
			<h2 id="pl-how-h"><?php esc_html_e( 'A simple way to explore a little more.', 'oria' ); ?></h2>
			<ol class="oria-pl__steps">
				<li class="oria-pl__step">
					<span class="oria-pl__num" aria-hidden="true">01</span>
					<h3><?php esc_html_e( 'Choose an experience', 'oria' ); ?></h3>
					<p><?php echo esc_html( $oria_live ? __( 'Browse the options offered by participating businesses.', 'oria' ) : __( 'Browse the options offered by participating businesses when the Pass opens.', 'oria' ) ); ?></p>
				</li>
				<li class="oria-pl__step">
					<span class="oria-pl__num" aria-hidden="true">02</span>
					<h3><?php esc_html_e( 'Use your credits', 'oria' ); ?></h3>
					<p><?php esc_html_e( 'Check the session details and credit cost before making a booking.', 'oria' ); ?></p>
				</li>
				<li class="oria-pl__step">
					<span class="oria-pl__num" aria-hidden="true">03</span>
					<h3><?php esc_html_e( 'Find what you enjoy', 'oria' ); ?></h3>
					<p><?php esc_html_e( 'Try something unfamiliar, revisit a favourite or explore another experience.', 'oria' ); ?></p>
				</li>
			</ol>
		</div>
	</section>

	<?php /* C. Credits, made concrete with an example that is labelled as one. */ ?>
	<?php if ( $oria_samples && $oria_credits > 0 ) : ?>
		<section class="oria-pl__section oria-pl__section--tight" aria-labelledby="pl-mix-h">
			<div class="oria-pl__container">
				<div class="oria-pl__mix" data-pl-mix data-budget="<?php echo (int) $oria_credits; ?>">
					<div class="oria-pl__mix-text">
						<h2 id="pl-mix-h"><?php esc_html_e( 'What could a mix look like?', 'oria' ); ?></h2>
						<p class="oria-pl__copy">
							<?php
							/* translators: %d: credits per month */
							printf( esc_html__( 'Explore an example of how %d credits could be spread across different activities.', 'oria' ), (int) $oria_credits );
							?>
						</p>
						<p class="oria-pl__mix-note">
							<strong><?php esc_html_e( 'Illustrative credit costs only.', 'oria' ); ?></strong>
							<?php esc_html_e( 'These are not live offers or guaranteed launch rates. Each business sets the credit cost of its own sessions, so a named session may cost more or fewer.', 'oria' ); ?>
						</p>
					</div>
					<div class="oria-pl__mix-play">
						<p class="oria-pl__mix-hint" data-pl-mix-hint hidden><?php esc_html_e( 'Select activities to add them to the example, or select again to remove them.', 'oria' ); ?></p>
						<ul class="oria-pl__choices" role="list">
							<?php foreach ( $oria_samples as $oria_slug => $oria_s ) : ?>
								<li>
									<button class="oria-pl__choice" type="button" data-cost="<?php echo (int) $oria_s['cost']; ?>"
										aria-pressed="<?php echo isset( $oria_pressed[ $oria_slug ] ) ? 'true' : 'false'; ?>" disabled>
										<strong><?php echo esc_html( $oria_s['label'] ); ?></strong>
										<span>
											<?php
											/* translators: %d: number of credits */
											printf( esc_html__( '%d example credits', 'oria' ), (int) $oria_s['cost'] );
											?>
										</span>
									</button>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="oria-pl__total" aria-live="polite">
							<strong data-pl-total>
								<?php
								/* translators: 1: example credits used, 2: credits per month */
								printf( esc_html__( '%1$d of %2$d example credits', 'oria' ), (int) $oria_used, (int) $oria_credits );
								?>
							</strong>
							<span data-pl-left>
								<?php
								/* translators: %d: example credits left */
								printf( esc_html__( ' · %d remaining', 'oria' ), (int) max( 0, $oria_credits - $oria_used ) );
								?>
							</span>
							<span class="oria-pl__limit" data-pl-limit></span>
						</p>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php /* D. What somebody might explore. Possibilities, not a programme. */ ?>
	<section class="oria-pl__section" aria-labelledby="pl-kinds-h">
		<div class="oria-pl__container">
			<h2 id="pl-kinds-h"><?php esc_html_e( 'What would you like to explore?', 'oria' ); ?></h2>
			<p class="oria-pl__copy"><?php esc_html_e( 'From movement to quieter moments, tell us what you would be interested in trying.', 'oria' ); ?></p>
			<ul class="oria-pl__kinds" role="list">
				<?php foreach ( $oria_kinds as $oria_k ) : ?>
					<li class="oria-pl__kind">
						<img class="oria-pl__kind-icon" src="<?php echo esc_url( $oria_uri . '/assets/img/cat-icons/' . $oria_k[0] . '.svg' ); ?>" alt="" width="56" height="56" loading="lazy" decoding="async">
						<div class="oria-pl__kind-text">
							<h3><?php echo esc_html( $oria_k[1] ); ?></h3>
							<p><?php echo esc_html( $oria_k[2] ); ?></p>
							<a class="oria-pl__kind-link" href="<?php echo esc_url( home_url( '/explore/perth/' . $oria_k[3] ) ); ?>"
								data-oria-event="oria_pass_experience_view" data-pass-kind="<?php echo esc_attr( sanitize_title( $oria_k[1] ) ); ?>">
								<?php
								/* translators: 1: experience type, 2: city */
								printf( esc_html__( 'Explore %1$s in %2$s', 'oria' ), esc_html( $oria_k[4] ), esc_html( $oria_city ) );
								?>
							</a>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="oria-pl__note">
				<?php esc_html_e( 'Experience types shown are possibilities, not a confirmed launch programme. Links open the Oria Haven directory, and businesses listed there are not automatically Oria Pass partners.', 'oria' ); ?>
			</p>
		</div>
	</section>

	<?php /* E. The one form. Every other waitlist button points here. */ ?>
	<?php get_template_part( 'template-parts/pass/join', null, $oria_args ); ?>

	<?php /* F. The details, easy to find, never in the way. */ ?>
	<section class="oria-pl__section" id="pass-faq" aria-labelledby="pl-faq-h">
		<div class="oria-pl__container oria-pl__faq">
			<div class="oria-pl__faq-intro">
				<h2 id="pl-faq-h"><?php esc_html_e( 'A few things to know.', 'oria' ); ?></h2>
				<p class="oria-pl__copy">
					<?php esc_html_e( 'Oria Pass is being designed for people who want to explore a mix of experiences. If you already have a favourite studio, it can sit alongside that routine.', 'oria' ); ?>
				</p>
			</div>
			<?php get_template_part( 'template-parts/pass/faq', null, $oria_args ); ?>
		</div>
	</section>

	<?php /* G. Businesses: a separate door, after the consumer page is done. */ ?>
	<section class="oria-pl__section oria-pl__section--tight oria-pl__section--last" aria-labelledby="pl-biz-h">
		<div class="oria-pl__container">
			<div class="oria-pl__biz">
				<div class="oria-pl__biz-text">
					<p class="oria-pl__eyebrow oria-pl__eyebrow--light"><?php esc_html_e( 'For wellness businesses', 'oria' ); ?></p>
					<h2 id="pl-biz-h"><?php esc_html_e( 'Introduce someone new to what you do.', 'oria' ); ?></h2>
					<p>
						<?php
						/* translators: %s: city */
						printf( esc_html__( 'We are inviting %s providers to help shape Oria Pass. If you are interested in offering selected sessions, tell us about your business and we can discuss participation.', 'oria' ), esc_html( $oria_city ) );
						?>
					</p>
					<p class="oria-pl__biz-note"><?php esc_html_e( 'Joining the directory and participating in Oria Pass are separate.', 'oria' ); ?></p>
				</div>
				<div class="oria-pl__biz-actions">
					<a class="oria-pl__button oria-pl__button--ivory" href="<?php echo esc_url( \Oria\Pass\Route\url( 'partners' ) ); ?>" data-oria-event="oria_pass_provider_cta"><?php esc_html_e( 'Become a founding partner', 'oria' ); ?></a>
					<a class="oria-pl__biz-link" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'Already on Oria Haven? Claim your profile', 'oria' ); ?></a>
				</div>
			</div>
		</div>
	</section>

	<?php get_template_part( 'template-parts/pass/sticky', null, $oria_args ); ?>

</div>

<?php
get_footer();
