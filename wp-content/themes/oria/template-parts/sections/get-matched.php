<?php
/**
 * "Get matched" on the front page, two presentations of one form
 * (template-parts/match-form.php):
 *
 *   mobile  — the full band, form inline on the page
 *   desktop — a <dialog>, opened from the hero card or any
 *             [data-match-open] trigger; the band hides via CSS
 *
 * The dialog lives here rather than in the footer so everything about
 * the feature stays in one template. After a submission the server
 * redirects back with ?olead=sent — app.js reopens the dialog on
 * desktop so the confirmation isn't hidden inside a closed modal.
 */

declare(strict_types=1);

if ( ! function_exists( '\Oria\Core\Leads\bootstrap' ) ) {
	return;
}
?>

<section class="wrap section matchband-section" id="enquire">
	<div class="matchband on-deep">
		<div class="matchband__copy">
			<span class="micro"><?php esc_html_e( 'New — free to use', 'oria' ); ?></span>
			<h2 class="h2" style="color:#fff;margin-top:.75rem"><?php esc_html_e( "Tell us what you're after. We'll introduce you.", 'oria' ); ?></h2>
			<p style="color:var(--mist-2);max-width:44ch;margin-top:1rem">
				<?php esc_html_e( "One request, and we hand it to up to three practices that fit — you'll know exactly which ones, and they reply to you directly. No account, no booking fees, no obligation.", 'oria' ); ?>
			</p>
			<ol class="matchband__steps">
				<li><?php esc_html_e( 'Say what you want to try and where', 'oria' ); ?></li>
				<li><?php esc_html_e( 'We match up to three verified practices', 'oria' ); ?></li>
				<li><?php esc_html_e( 'They come to you — pick whoever fits', 'oria' ); ?></li>
			</ol>
		</div>

		<div class="matchband__form">
			<?php get_template_part( 'template-parts/match-form' ); ?>
		</div>
	</div>
</section>

<dialog class="matchdialog" id="matchDialog" aria-labelledby="matchDialogTitle">
	<div class="matchdialog__inner on-deep">
		<div class="matchdialog__head">
			<div>
				<span class="micro"><?php esc_html_e( 'Free matching service', 'oria' ); ?></span>
				<h2 class="h3" id="matchDialogTitle" style="color:#fff;margin-top:.5rem"><?php esc_html_e( "Tell us what you're after. We'll introduce you.", 'oria' ); ?></h2>
			</div>
			<button class="matchdialog__close" type="button" data-match-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&#10005;</button>
		</div>
		<?php get_template_part( 'template-parts/match-form' ); ?>
	</div>
</dialog>
