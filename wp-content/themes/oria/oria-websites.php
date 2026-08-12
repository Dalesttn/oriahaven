<?php
/**
 * Oria Digital — the website-services page.
 *
 * Deliberately the plainest page on the site. It has one job: be somewhere
 * to send a practice who has asked, and let them ask for a review. No
 * scarcity, no testimonials we don't have, no packages until there's a
 * client who has bought one.
 *
 * The independence line near the bottom is not decoration. The directory
 * lists the businesses this page sells to, and saying plainly that buying
 * a website changes nothing editorial is the price of running both.
 *
 * @package Oria
 */

declare(strict_types=1);

use function Oria\Theme\arrow;

$oria_service = \Oria\Core\Websites\service_name();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_state = isset( $_GET['owr'] ) ? sanitize_key( wp_unslash( (string) $_GET['owr'] ) ) : '';

get_header();
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_service ); ?></span>
	</nav>
	<span class="micro"><?php echo esc_html( $oria_service ); ?></span>
	<h1 class="h1 pagehead__title"><?php esc_html_e( 'Websites for wellness practices in Perth.', 'oria' ); ?></h1>
	<p class="lede pagehead__lede">
		<?php esc_html_e( 'Most practices don\'t need a bigger website. They need a faster one that works on a phone, says what they do, and makes booking obvious. That\'s the whole job.', 'oria' ); ?>
	</p>
	<p style="margin-top:1.5rem">
		<a class="btn btn--dark" href="#review"><?php esc_html_e( 'Get a free website review', 'oria' ); ?><?php echo arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	</p>
</section>

<section class="wrap section section--top-flush">
	<div class="prose prose--article" style="max-width:62ch">
		<h2><?php esc_html_e( 'What usually needs fixing', 'oria' ); ?></h2>
		<p><?php esc_html_e( 'Having built this directory by hand, we have now looked closely at more than a hundred Perth wellness websites. The same handful of problems come up again and again, and none of them are about how the site looks.', 'oria' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'It takes too long to load on a phone on mobile data, which is how most people arrive.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'The price isn\'t on it, so people email to ask instead of booking — or don\'t.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'The timetable lives on a third-party booking site and nowhere else.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Google has nothing to work with: no suburb on the page, no description, no structured details.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Nobody can update it without ringing whoever built it four years ago.', 'oria' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'What we do about it', 'oria' ); ?></h2>
		<p><?php esc_html_e( 'WordPress sites, built to be fast and edited by the person who runs the practice. Mobile first, because that\'s where your enquiries come from. Local SEO set up properly so you turn up when somebody searches your modality and your suburb. Booking and contact paths that are obvious on a small screen. Analytics, so you can see whether any of it worked.', 'oria' ); ?></p>
		<p><?php esc_html_e( 'If you already have a site that mostly works, we\'d rather fix it than sell you a new one. That is usually cheaper and almost always faster.', 'oria' ); ?></p>

		<h2><?php esc_html_e( 'What it costs', 'oria' ); ?></h2>
		<p><?php esc_html_e( 'It depends on how many pages you need and whether you want us to keep looking after it afterwards, so there is no price list here yet. Ask for the review below and you\'ll get a straight number with it — no meeting required to find out.', 'oria' ); ?></p>
	</div>
</section>

<!-- --------------------------------------------------------------- review -->
<section class="wrap section section--top-flush" id="review">
	<div class="finder__handoff">
		<h2 class="h3"><?php esc_html_e( 'Get a free website review', 'oria' ); ?></h2>

		<?php if ( 'sent' === $oria_state ) : ?>
			<div class="notice" style="background:rgba(255,255,255,.95)">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" style="width:20px;height:20px;flex:none"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>
				<span>
					<b><?php esc_html_e( 'Got it.', 'oria' ); ?></b>
					<?php esc_html_e( 'We\'ll go through the site by hand and come back within a couple of days.', 'oria' ); ?>
				</span>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'Tell us where your site is and we\'ll go through it — what\'s slowing it down, what\'s stopping enquiries, and what we\'d change first. You get the review whether or not you ever hire us.', 'oria' ); ?></p>

			<?php if ( 'error' === $oria_state ) : ?>
				<p style="font-size:.8125rem;color:#ffb4a2"><?php esc_html_e( 'That didn\'t send — check your name, email and website address.', 'oria' ); ?></p>
			<?php endif; ?>

			<form class="stack" style="gap:.8rem" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-oria-event="web_review_requested">
				<input type="hidden" name="action" value="oria_web_request">
				<?php wp_nonce_field( 'oria_web_request', 'oria_web_nonce' ); ?>
				<input type="text" name="oria_web_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

				<label class="field"><span class="field__label"><?php esc_html_e( 'Your website', 'oria' ); ?></span>
					<input class="input" type="url" name="req_site" required placeholder="yourpractice.com.au"></label>

				<div class="grid matchband__pair">
					<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span>
						<input class="input" type="text" name="req_name" required></label>
					<label class="field"><span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
						<input class="input" type="email" name="req_email" required></label>
				</div>

				<label class="field"><span class="field__label"><?php esc_html_e( 'Anything bothering you about it?', 'oria' ); ?> <span class="matchform__opt">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
					<textarea class="textarea" name="req_note" style="min-height:64px" maxlength="600" placeholder="<?php esc_attr_e( 'e.g. it\'s slow on phones, nobody uses the booking button', 'oria' ); ?>"></textarea></label>

				<button class="btn btn--light btn--block" type="submit"><?php esc_html_e( 'Send it over', 'oria' ); ?></button>
				<p class="hint matchform__hint"><?php esc_html_e( 'No charge, no obligation, and we don\'t add you to anything.', 'oria' ); ?></p>
			</form>
		<?php endif; ?>
	</div>
</section>

<section class="wrap section section--top-flush">
	<div class="prose" style="max-width:62ch">
		<h2 class="h3"><?php esc_html_e( 'About the directory', 'oria' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: service name, 2: directory link */
				esc_html__( '%1$s is run by the same person who built %2$s. That is worth being plain about, so: buying a website changes nothing on the directory. It does not affect where a practice appears, how it ranks, whether it is featured, or anything else editorial — those are decided by the same rules for everybody, and no part of the directory can even see who has hired us.', 'oria' ),
				esc_html( $oria_service ),
				'<a href="' . esc_url( home_url( '/' ) ) . '">Oria Haven</a>'
			);
			?>
		</p>
		<p><?php esc_html_e( 'It works the other way round too. You never have to be listed on Oria Haven to have a website built, and being listed doesn\'t get you a discount.', 'oria' ); ?></p>
	</div>
</section>

<?php
get_footer();
