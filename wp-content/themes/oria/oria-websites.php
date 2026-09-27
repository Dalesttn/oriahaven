<?php
/**
 * /websites/ -- website help for wellness businesses, by Oria Digital.
 *
 * Kept at its original address (it is already linked from practitioner
 * emails and the footer) and rebuilt for the 2026-09 brief: two scoped
 * services, a simple process, a plain FAQ and one enquiry form. The form
 * posts to Oria\Core\WebHelp, which stores the request before emailing it
 * and never fetches the website somebody types in.
 *
 * Nothing here is invented: no clients, testimonials, results or prices.
 * The work-examples section appears only when there are real, approved
 * examples to show -- today there are none, so it is not drawn.
 *
 * @package Oria
 */

declare(strict_types=1);

use Oria\Core\WebHelp;

$oria_dir = get_template_directory();
wp_enqueue_style( 'oria-websites', get_template_directory_uri() . '/assets/css/websites.css', array(), (string) filemtime( $oria_dir . '/assets/css/websites.css' ) );

$oria_service = function_exists( '\Oria\Core\Websites\service_name' ) ? \Oria\Core\Websites\service_name() : 'Oria Digital';
$oria_open    = function_exists( '\Oria\Core\WebHelp\open' ) && WebHelp\open();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_state = sanitize_key( (string) ( $_GET['wh'] ?? '' ) );
$oria_src   = sanitize_key( (string) ( $_GET['src'] ?? 'page' ) );
$oria_pick  = sanitize_key( (string) ( $_GET['service'] ?? '' ) );
// phpcs:enable
$oria_kept = function_exists( '\Oria\Core\WebHelp\recall' ) ? WebHelp\recall() : array();
$oria_val  = static fn( string $k ): string => (string) ( $oria_kept[ $k ] ?? '' );
$oria_svc  = $oria_val( 'service' ) ?: ( isset( WebHelp\SERVICES[ $oria_pick ] ) ? $oria_pick : 'unsure' );
if ( ! in_array( $oria_src, WebHelp\SOURCES, true ) ) {
	$oria_src = 'page';
}

$oria_privacy = (string) get_privacy_policy_url() ?: home_url( '/privacy/' );

$oria_errors = array(
	'invalid-name'     => array( 'wh-name', __( 'Add your name so Dale knows who to reply to.', 'oria' ) ),
	'invalid-business' => array( 'wh-business', __( 'Add the name of your business.', 'oria' ) ),
	'invalid-email'    => array( 'wh-email', __( 'That email address does not look right. Check it and try again.', 'oria' ) ),
	'invalid-website'  => array( 'wh-website', __( 'Add your website address, for example yourpractice.com.au.', 'oria' ) ),
	'invalid-message'  => array( 'wh-message', __( 'Tell us a little about what you would like to improve (a sentence is enough).', 'oria' ) ),
	'expired'          => array( '', __( 'That form sat open for a while. Please send it again.', 'oria' ) ),
	'slow'             => array( '', __( 'That was sent very quickly, so it looked automated. Please check your details and send it again.', 'oria' ) ),
	'busy'             => array( '', __( 'Several requests have come from this connection in the last hour. Please try again a little later.', 'oria' ) ),
	'server'           => array( '', __( 'Something went wrong on our side and the request was not saved. Please try again in a minute.', 'oria' ) ),
	'closed'           => array( '', __( 'Website help requests are not open just yet. Please use the contact form in the meantime.', 'oria' ) ),
);
$oria_err       = $oria_errors[ $oria_state ] ?? null;
$oria_bad_field = $oria_err ? $oria_err[0] : '';

$oria_invalid = static fn( string $id ): string => $id === $oria_bad_field ? ' aria-invalid="true" aria-describedby="wh-error"' : '';

get_header();
?>

<div class="oria-webhelp">

<!-- Hero -->
<section class="wh-hero" aria-labelledby="whTitle">
	<div class="wh-wrap">
		<nav class="crumbs wh-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'For practitioners', 'oria' ); ?></a>
			<span aria-hidden="true">/</span><span><?php esc_html_e( 'Website help', 'oria' ); ?></span>
		</nav>
		<p class="wh-eyebrow"><?php esc_html_e( 'For wellness businesses', 'oria' ); ?></p>
		<h1 class="wh-hero__title" id="whTitle"><?php esc_html_e( 'Make your website easier to use and book through.', 'oria' ); ?></h1>
		<p class="wh-hero__lede"><?php esc_html_e( 'Practical improvements for wellness businesses, from clearer service pages and mobile booking buttons to a focused page for your next workshop.', 'oria' ); ?></p>
		<div class="wh-hero__acts">
			<a class="wh-btn wh-btn--primary" href="#request" data-oria-event="website_help_cta_click" data-oria-placement="hero_request"><?php esc_html_e( 'Request a website review', 'oria' ); ?></a>
			<a class="wh-btn wh-btn--secondary" href="#services" data-oria-event="website_help_cta_click" data-oria-placement="hero_services"><?php esc_html_e( 'Explore the services', 'oria' ); ?></a>
		</div>
		<p class="wh-hero__by">
			<?php
			printf(
				/* translators: %s: service brand */
				esc_html__( 'Website services by %s, run by Dale Sutton, the founder of Oria Haven.', 'oria' ),
				esc_html( $oria_service )
			);
			?>
		</p>
	</div>
</section>

<!-- Familiar problems -->
<section class="wh-section" aria-labelledby="whProbTitle">
	<div class="wh-wrap wh-split">
		<div>
			<h2 class="wh-h2" id="whProbTitle"><?php esc_html_e( 'Sound familiar?', 'oria' ); ?></h2>
			<p class="wh-copy"><?php esc_html_e( 'A few reasons wellness businesses get in touch. Yours may be one of these, or something else entirely.', 'oria' ); ?></p>
		</div>
		<ul class="wh-probs">
			<li><?php esc_html_e( 'Customers struggle to find the booking link on their phone.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Services, prices or introductory offers are hard to understand.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Workshop promotion sends people to a general page instead of the event details.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Important pages feel dated or are awkward to use.', 'oria' ); ?></li>
		</ul>
	</div>
</section>

<!-- The two services -->
<section class="wh-section wh-section--sage" id="services" aria-labelledby="whSvcTitle">
	<div class="wh-wrap">
		<h2 class="wh-h2" id="whSvcTitle"><?php esc_html_e( 'Two focused ways to help', 'oria' ); ?></h2>
		<p class="wh-copy"><?php esc_html_e( 'Both are quoted after a short review, once the scope is agreed. Nothing is changed until you say yes.', 'oria' ); ?></p>
		<div class="wh-cards">
			<article class="wh-card" aria-labelledby="whSvcA">
				<p class="wh-card__kicker"><?php esc_html_e( 'Improve what you have', 'oria' ); ?></p>
				<h3 class="wh-card__title" id="whSvcA"><?php esc_html_e( 'Website tune-up', 'oria' ); ?></h3>
				<p class="wh-card__text"><?php esc_html_e( 'Improve a defined part of your existing website, so people can find what they need and book it.', 'oria' ); ?></p>
				<ul class="wh-card__list">
					<li><?php esc_html_e( 'Booking buttons that are easy to find and tap', 'oria' ); ?></li>
					<li><?php esc_html_e( 'Mobile layout fixes', 'oria' ); ?></li>
					<li><?php esc_html_e( 'Clearer service and price information', 'oria' ); ?></li>
					<li><?php esc_html_e( 'Easier contact, and agreed performance fixes', 'oria' ); ?></li>
				</ul>
				<p class="wh-card__note"><?php esc_html_e( 'Scope and price are confirmed after the review. This is focused work on agreed pages, not a complete redesign.', 'oria' ); ?></p>
				<a class="wh-btn wh-btn--secondary wh-card__cta" href="<?php echo esc_url( add_query_arg( 'service', 'tuneup' ) ); ?>#request" data-wh-service="tuneup" data-oria-event="website_help_cta_click" data-oria-placement="service_tuneup"><?php esc_html_e( 'Ask about this service', 'oria' ); ?></a>
			</article>
			<article class="wh-card" aria-labelledby="whSvcB">
				<p class="wh-card__kicker"><?php esc_html_e( 'Promote one thing well', 'oria' ); ?></p>
				<h3 class="wh-card__title" id="whSvcB"><?php esc_html_e( 'Workshop or offer page', 'oria' ); ?></h3>
				<p class="wh-card__text"><?php esc_html_e( 'A focused page for one workshop, course or introductory offer, so people who click through land on the details they came for.', 'oria' ); ?></p>
				<ul class="wh-card__list">
					<li><?php esc_html_e( 'Clear structure for what it is and who it suits', 'oria' ); ?></li>
					<li><?php esc_html_e( 'Your photos, inclusions, dates and location', 'oria' ); ?></li>
					<li><?php esc_html_e( 'Answers to the questions people ask before booking', 'oria' ); ?></li>
					<li><?php esc_html_e( 'Linked to the booking system you already use', 'oria' ); ?></li>
				</ul>
				<p class="wh-card__note"><?php esc_html_e( 'Quoted after the review. It works with your existing booking link rather than adding another checkout.', 'oria' ); ?></p>
				<a class="wh-btn wh-btn--secondary wh-card__cta" href="<?php echo esc_url( add_query_arg( 'service', 'landing' ) ); ?>#request" data-wh-service="landing" data-oria-event="website_help_cta_click" data-oria-placement="service_landing"><?php esc_html_e( 'Ask about this service', 'oria' ); ?></a>
			</article>
		</div>
	</div>
</section>

<!-- Process -->
<section class="wh-section" aria-labelledby="whHowTitle">
	<div class="wh-wrap">
		<h2 class="wh-h2" id="whHowTitle"><?php esc_html_e( 'How it works', 'oria' ); ?></h2>
		<p class="wh-copy"><?php echo esc_html( function_exists( '\Oria\Core\WebHelp\review_line' ) ? WebHelp\review_line() : '' ); ?></p>
		<ol class="wh-steps">
			<li class="wh-step">
				<span class="wh-step__n" aria-hidden="true">01</span>
				<h3><?php esc_html_e( 'Share your website', 'oria' ); ?></h3>
				<p><?php esc_html_e( 'Tell Dale what you would like to improve.', 'oria' ); ?></p>
			</li>
			<li class="wh-step">
				<span class="wh-step__n" aria-hidden="true">02</span>
				<h3><?php esc_html_e( 'Receive initial suggestions', 'oria' ); ?></h3>
				<p><?php esc_html_e( 'A short review, with no obligation to buy anything.', 'oria' ); ?></p>
			</li>
			<li class="wh-step">
				<span class="wh-step__n" aria-hidden="true">03</span>
				<h3><?php esc_html_e( 'Agree the work', 'oria' ); ?></h3>
				<p><?php esc_html_e( 'Scope, timing and a quote are confirmed before anything changes.', 'oria' ); ?></p>
			</li>
			<li class="wh-step">
				<span class="wh-step__n" aria-hidden="true">04</span>
				<h3><?php esc_html_e( 'Improve and check', 'oria' ); ?></h3>
				<p><?php esc_html_e( 'The agreed work is done and checked on the devices that matter.', 'oria' ); ?></p>
			</li>
		</ol>
	</div>
</section>

<!-- FAQ -->
<section class="wh-section wh-section--rule" aria-labelledby="whFaqTitle">
	<div class="wh-wrap wh-split">
		<h2 class="wh-h2" id="whFaqTitle"><?php esc_html_e( 'Questions people ask', 'oria' ); ?></h2>
		<div class="wh-faq">
			<?php
			$oria_faqs = array(
				__( 'Do I need a paid Oria Haven listing?', 'oria' )                  => __( 'No. Website help is open to any wellness business, listed on Oria Haven or not, on any plan or none.', 'oria' ),
				__( 'Does buying website help affect my listing or Best Of selection?', 'oria' ) => __( 'No. It has no effect on where a business appears, how it is ranked, whether it is featured or whether it is chosen for a guide. No part of the directory can see who has asked for website help.', 'oria' ),
				__( 'Will you replace my whole website?', 'oria' )                    => __( 'Only if that is separately agreed. The service starts with focused improvements.', 'oria' ),
				__( 'Can you work with my platform?', 'oria' )                        => __( 'Tell us which platform you use. Whether it is a good fit is confirmed during the review.', 'oria' ),
				__( 'What does it cost?', 'oria' )                                    => __( 'You get a quote for the agreed scope before any paid work begins.', 'oria' ),
				__( 'Do I have to move hosting or change booking systems?', 'oria' ) => __( 'No, that is not a requirement. The work is built around what you already use.', 'oria' ),
				/* translators: %s: service brand */
				__( 'Who provides the service?', 'oria' )                             => sprintf( __( '%s, run by Dale Sutton, who also runs Oria Haven.', 'oria' ), $oria_service ),
			);
			foreach ( $oria_faqs as $oria_q => $oria_a ) :
				?>
				<details class="wh-qa">
					<summary><?php echo esc_html( $oria_q ); ?></summary>
					<p><?php echo esc_html( $oria_a ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- The enquiry -->
<section class="wh-section wh-section--form" id="request" aria-labelledby="whFormTitle">
	<div class="wh-wrap">
		<div class="wh-formbox">
			<?php if ( 'sent' === $oria_state ) : ?>
				<?php // A state marker: fires website_help_enquiry_submitted on render, which only happens after the server stored the request. ?>
				<div class="wh-done" role="status" data-oria-event="website_help_enquiry_submitted" data-oria-placement="<?php echo esc_attr( $oria_src ); ?>">
					<h2 class="wh-h2" id="whFormTitle" tabindex="-1"><?php esc_html_e( 'Request received', 'oria' ); ?></h2>
					<p><?php esc_html_e( 'Thanks — your website review request has been received. Dale will review the details and contact you about the next steps.', 'oria' ); ?></p>
					<p><a class="wh-textlink" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to Oria Haven', 'oria' ); ?></a></p>
				</div>
			<?php elseif ( ! $oria_open ) : ?>
				<h2 class="wh-h2" id="whFormTitle"><?php esc_html_e( 'Request a website review', 'oria' ); ?></h2>
				<p class="wh-copy"><?php esc_html_e( 'Website help requests are opening soon. In the meantime you can reach us through the contact form.', 'oria' ); ?></p>
				<p><a class="wh-btn wh-btn--secondary" href="<?php echo esc_url( home_url( '/about/#contact' ) ); ?>"><?php esc_html_e( 'Get in touch', 'oria' ); ?></a></p>
			<?php else : ?>
				<h2 class="wh-h2" id="whFormTitle"><?php esc_html_e( 'Request a website review', 'oria' ); ?></h2>
				<p class="wh-copy">
					<?php
					printf(
						/* translators: %s: service brand */
						esc_html__( 'Tell us about your website and what you would like to improve. %s will reply about next steps.', 'oria' ),
						esc_html( $oria_service )
					);
					?>
				</p>

				<?php if ( $oria_err ) : ?>
					<p class="wh-error" id="wh-error" role="alert"><?php echo esc_html( $oria_err[1] ); ?></p>
				<?php endif; ?>

				<form class="wh-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-wh-form novalidate>
					<input type="hidden" name="action" value="<?php echo esc_attr( WebHelp\ACTION ); ?>">
					<input type="hidden" name="wh_ts" value="<?php echo esc_attr( (string) time() ); ?>">
					<input type="hidden" name="wh_src" value="<?php echo esc_attr( $oria_src ); ?>">
					<?php wp_nonce_field( WebHelp\ACTION, WebHelp\NONCE ); ?>
					<input type="text" name="wh_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="wh-hp">

					<fieldset class="wh-choice">
						<legend class="wh-label"><?php esc_html_e( 'What are you interested in?', 'oria' ); ?></legend>
						<div class="wh-choice__row">
							<?php foreach ( WebHelp\SERVICES as $oria_k => $oria_l ) : ?>
								<label class="wh-chip">
									<input type="radio" name="wh_service" value="<?php echo esc_attr( $oria_k ); ?>"<?php checked( $oria_svc, $oria_k ); ?>>
									<span><?php echo esc_html( $oria_l ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<div class="wh-row">
						<p class="wh-field">
							<label class="wh-label" for="wh-name"><?php esc_html_e( 'Your name', 'oria' ); ?></label>
							<input id="wh-name" type="text" name="wh_name" required maxlength="120" autocomplete="name" value="<?php echo esc_attr( $oria_val( 'name' ) ); ?>"<?php echo $oria_invalid( 'wh-name' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?>>
						</p>
						<p class="wh-field">
							<label class="wh-label" for="wh-business"><?php esc_html_e( 'Business name', 'oria' ); ?></label>
							<input id="wh-business" type="text" name="wh_business" required maxlength="160" autocomplete="organization" value="<?php echo esc_attr( $oria_val( 'business' ) ); ?>"<?php echo $oria_invalid( 'wh-business' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?>>
						</p>
					</div>
					<div class="wh-row">
						<p class="wh-field">
							<label class="wh-label" for="wh-email"><?php esc_html_e( 'Email', 'oria' ); ?></label>
							<input id="wh-email" type="email" name="wh_email" required maxlength="190" autocomplete="email" value="<?php echo esc_attr( $oria_val( 'email' ) ); ?>"<?php echo $oria_invalid( 'wh-email' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?>>
						</p>
						<p class="wh-field">
							<label class="wh-label" for="wh-phone"><?php esc_html_e( 'Phone', 'oria' ); ?> <span class="wh-opt"><?php esc_html_e( '(optional)', 'oria' ); ?></span></label>
							<input id="wh-phone" type="tel" name="wh_phone" maxlength="40" autocomplete="tel" value="<?php echo esc_attr( $oria_val( 'phone' ) ); ?>">
						</p>
					</div>
					<p class="wh-field">
						<label class="wh-label" for="wh-website"><?php esc_html_e( 'Your website', 'oria' ); ?></label>
						<input id="wh-website" type="text" inputmode="url" name="wh_website" required maxlength="300" autocomplete="url" placeholder="yourpractice.com.au" value="<?php echo esc_attr( $oria_val( 'website' ) ); ?>"<?php echo $oria_invalid( 'wh-website' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?>>
					</p>
					<p class="wh-field">
						<label class="wh-label" for="wh-message"><?php esc_html_e( 'What would you like to improve?', 'oria' ); ?></label>
						<textarea id="wh-message" name="wh_message" required minlength="10" maxlength="1500" rows="5" placeholder="<?php esc_attr_e( 'For example: people say they cannot find the booking button on their phone.', 'oria' ); ?>"<?php echo $oria_invalid( 'wh-message' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?>><?php echo esc_textarea( $oria_val( 'message' ) ); ?></textarea>
						<span class="wh-hint"><?php esc_html_e( 'Please do not include passwords or login details.', 'oria' ); ?></span>
					</p>
					<div class="wh-row">
						<p class="wh-field">
							<label class="wh-label" for="wh-budget"><?php esc_html_e( 'Budget', 'oria' ); ?> <span class="wh-opt"><?php esc_html_e( '(optional)', 'oria' ); ?></span></label>
							<select id="wh-budget" name="wh_budget">
								<?php foreach ( WebHelp\BUDGETS as $oria_k => $oria_l ) : ?>
									<option value="<?php echo esc_attr( $oria_k ); ?>"<?php selected( $oria_val( 'budget' ), $oria_k ); ?>><?php echo esc_html( $oria_l ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p class="wh-field">
							<label class="wh-label" for="wh-platform"><?php esc_html_e( 'Website platform', 'oria' ); ?> <span class="wh-opt"><?php esc_html_e( '(optional)', 'oria' ); ?></span></label>
							<select id="wh-platform" name="wh_platform">
								<?php foreach ( WebHelp\PLATFORMS as $oria_k => $oria_l ) : ?>
									<option value="<?php echo esc_attr( $oria_k ); ?>"<?php selected( $oria_val( 'platform' ), $oria_k ); ?>><?php echo esc_html( $oria_l ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
					</div>

					<p class="wh-privacy">
						<?php
						printf(
							/* translators: 1: service brand, 2: privacy link */
							esc_html__( 'Dale and %1$s will use these details only to respond to your enquiry. They are not shared with any listed practice and you are not added to a mailing list. %2$s', 'oria' ),
							esc_html( $oria_service ),
							'<a href="' . esc_url( $oria_privacy ) . '">' . esc_html__( 'Privacy policy', 'oria' ) . '</a>'
						);
						?>
					</p>
					<button class="wh-btn wh-btn--primary wh-submit" type="submit" data-wh-submit><?php esc_html_e( 'Request my website review', 'oria' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</div>
</section>

<!-- Independence -->
<section class="wh-section wh-section--rule">
	<div class="wh-wrap wh-independent">
		<h2 class="wh-h3"><?php esc_html_e( 'About Oria Haven and Oria Digital', 'oria' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: service brand, 2: directory link */
				esc_html__( '%1$s is run by the same person who runs %2$s, so it is worth being plain about how they stay separate. Asking for or buying website help changes nothing on the directory: not where a business appears, how it is ranked, whether it is featured or whether it is chosen for a guide. And you never need to be listed on Oria Haven to ask for help.', 'oria' ),
				esc_html( $oria_service ),
				'<a href="' . esc_url( home_url( '/' ) ) . '">Oria Haven</a>'
			);
			?>
		</p>
	</div>
</section>

</div>

<script>
(function () {
	"use strict";
	var form = document.querySelector("[data-wh-form]");
	/* "Ask about this service" picks that service and goes to the form,
	   without a reload; the visitor can still change the choice. */
	document.querySelectorAll("[data-wh-service]").forEach(function (a) {
		a.addEventListener("click", function (e) {
			if (!form) return;
			var r = form.querySelector('input[name="wh_service"][value="' + a.getAttribute("data-wh-service") + '"]');
			if (!r) return;
			e.preventDefault();
			r.checked = true;
			var box = document.getElementById("request");
			if (box) box.scrollIntoView({ behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block: "start" });
			r.focus({ preventScroll: true });
		});
	});
	if (!form) return;
	/* One submission at a time, with a visible sending state. */
	form.addEventListener("submit", function (e) {
		var btn = form.querySelector("[data-wh-submit]");
		if (form.getAttribute("data-sending")) { e.preventDefault(); return; }
		if (!form.checkValidity()) { e.preventDefault(); form.reportValidity(); return; }
		form.setAttribute("data-sending", "1");
		if (btn) { btn.setAttribute("aria-busy", "true"); btn.textContent = "Sending…"; }
	});
	/* After an error, put focus on the field that needs attention. */
	var bad = form.querySelector('[aria-invalid="true"]');
	if (bad) bad.focus();
})();
</script>

<?php
get_footer();
