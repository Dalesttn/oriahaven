<?php
/**
 * The owner's door: compact on the page, full explanation only once asked.
 *
 * What this replaces was a tall dark panel that opened with a price ("Free,
 * and no card needed") before anybody had said they were interested, and
 * folded the claim form behind a plus beside a second fold for corrections.
 * Two accordions and a sales line, on a page a visitor came to read about a
 * yoga studio. It made an unclaimed listing look like it was for sale.
 *
 * So: a quiet utility strip. A line asking whether you manage the place, a
 * line saying what claiming does, one button, and a tertiary way out for
 * people who are not the owner. Everything else -- what verification means,
 * what you get, the form itself -- waits behind the button, which is where
 * somebody has shown they want it.
 *
 * NOTHING HERE SAYS "UNCLAIMED". The old copy leaned on the listing being
 * built from public information, which reads to a visitor as "we are not
 * sure this is right". The panel is addressed to the owner and says nothing
 * to anybody else about how much to trust the page.
 *
 * ONE SET OF DIALOGS, TWO TRIGGERS. The page may show a small link up by
 * the contact details as well as this panel; both open the same dialog, and
 * the dialogs are printed once, by the panel. Nothing is duplicated but the
 * button.
 *
 * NO JAVASCRIPT, NO DEAD END. A <dialog> that is never opened is hidden, so
 * without scripting the button would do nothing. The <noscript> block below
 * unhides both dialogs and lays them out inline, which is the old behaviour:
 * longer page, working form.
 *
 * Args:
 *   id        int    listing post id (required)
 *   words     array  the kind vocabulary from Oria\Theme\words()
 *   mode      string 'panel' (default) or 'link' for the contextual trigger
 *   placement string analytics label: 'bottom_panel' or 'contact_details'
 *
 * @package Oria
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$oria_cl_id    = (int) ( $args['id'] ?? get_the_ID() );
$oria_cl_words = (array) ( $args['words'] ?? array() );
$oria_cl_mode  = 'link' === ( $args['mode'] ?? '' ) ? 'link' : 'panel';
$oria_cl_place = (string) ( $args['placement'] ?? ( 'link' === $oria_cl_mode ? 'contact_details' : 'bottom_panel' ) );

if ( ! $oria_cl_id ) {
	return;
}

/*
 * A spot -- a beach, a lookout -- has no claim_head because nobody owns it.
 * That single empty string is the whole gate for the "nothing to claim"
 * kind, and it is read here rather than re-derived.
 */
$oria_cl_head = (string) ( $oria_cl_words['claim_head'] ?? __( 'Do you manage this practice?', 'oria' ) );
if ( '' === $oria_cl_head ) {
	return;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_cl_state = isset( $_GET['oria_claim'] ) ? sanitize_key( (string) wp_unslash( $_GET['oria_claim'] ) ) : '';
$oria_cl_corr  = isset( $_GET['ocorr'] ) ? sanitize_key( (string) wp_unslash( $_GET['ocorr'] ) ) : '';
// phpcs:enable

$oria_cl_done  = 'received' === $oria_cl_state;
$oria_cl_error = 'error' === $oria_cl_state;
$oria_cl_name  = wp_specialchars_decode( (string) get_the_title( $oria_cl_id ), ENT_QUOTES );

/*
 * The contextual link is only ever a trigger. It says nothing when the claim
 * has just been sent, because the panel below is already saying it.
 */
if ( 'link' === $oria_cl_mode ) {
	if ( $oria_cl_done ) {
		return;
	}
	?>
	<button type="button" class="oclaim__inline" data-oria-claim-open
		data-oria-event="claim_started" data-oria-placement="<?php echo esc_attr( $oria_cl_place ); ?>">
		<?php esc_html_e( 'Claim this profile', 'oria' ); ?>
	</button>
	<?php
	return;
}
?>

<section class="oclaim" aria-labelledby="oclaim-head" data-oria-event="claim_cta_view" data-oria-placement="<?php echo esc_attr( $oria_cl_place ); ?>">

	<?php if ( $oria_cl_done ) : ?>

		<?php // Calm, and private to the person who sent it: nothing here tells a passing visitor that a claim is pending. ?>
		<div class="oclaim__body">
			<?php
			/*
			 * claim_completed, not a new claim_submitted. This is the
			 * site's established name for "the request is stored", it is
			 * already in the GTM trigger's allowlist regex, and renaming it
			 * would silently zero an existing metric rather than improve
			 * one. The name is a little generous -- the claim is not
			 * complete until somebody approves it -- but that is a rename
			 * to make in GTM, not quietly here.
			 */
			?>
			<p class="oclaim__sent" role="status" data-oria-event="claim_completed">
				<b><?php esc_html_e( 'Request received.', 'oria' ); ?></b>
				<?php esc_html_e( 'We check every claim by hand, and you will get an email with your log-in once it is approved.', 'oria' ); ?>
			</p>
			<?php
			/*
			 * The other door stays open. Somebody who has just asked to
			 * claim the page may still want one line fixed today, and
			 * without this the dialog below would have no way in at all.
			 */
			?>
			<p class="oclaim__alt">
				<?php esc_html_e( 'Something to correct in the meantime?', 'oria' ); ?>
				<button type="button" class="oclaim__altlink" data-oria-corr-open data-oria-event="correction_started">
					<?php esc_html_e( 'Suggest an edit', 'oria' ); ?>
				</button>
			</p>
		</div>

	<?php else : ?>

		<span class="oclaim__icon" aria-hidden="true">
			<?php // A shield, drawn in the same open round-capped stroke as the rest of the site's marks. ?>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" focusable="false">
				<path d="M12 3.2 5.4 5.9v5.4c0 3.9 2.6 7.5 6.6 8.9 4-1.4 6.6-5 6.6-8.9V5.9Z"/>
				<path d="m9.2 11.8 2 2 3.6-3.9" opacity=".55"/>
			</svg>
		</span>

		<div class="oclaim__body">
			<h2 class="oclaim__head" id="oclaim-head"><?php echo esc_html( $oria_cl_head ); ?></h2>
			<p class="oclaim__text"><?php esc_html_e( 'Claim the profile to update its details, photos, services and contact information.', 'oria' ); ?></p>

			<div class="oclaim__acts">
				<button type="button" class="btn btn--dark btn--sm oclaim__go" data-oria-claim-open
					data-oria-event="claim_started" data-oria-placement="<?php echo esc_attr( $oria_cl_place ); ?>">
					<?php esc_html_e( 'Claim this profile', 'oria' ); ?>
				</button>

				<p class="oclaim__alt">
					<?php esc_html_e( 'Not the owner?', 'oria' ); ?>
					<button type="button" class="oclaim__altlink" data-oria-corr-open data-oria-event="correction_started">
						<?php esc_html_e( 'Suggest an edit', 'oria' ); ?>
					</button>
				</p>
			</div>
		</div>

	<?php endif; ?>
</section>

<?php // --- the two doors, printed once ------------------------------------ ?>

<dialog class="odlg" id="oria-claim-dialog" aria-labelledby="oclaim-dlg-head">
	<form method="dialog" class="odlg__x">
		<button type="submit" class="odlg__close" aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
	</form>

	<?php
	/*
	 * autofocus on the panel rather than letting the browser take the
	 * first focusable child, which is the close button -- a screen
	 * reader would open on the word "Close", and on a phone focusing a
	 * text field instead would throw the keyboard up over the dialog
	 * before anybody had read the heading.
	 */
	?>
	<div class="odlg__in" tabindex="-1" autofocus>
		<h2 class="odlg__head" id="oclaim-dlg-head"><?php esc_html_e( 'Claim your Oria Haven profile', 'oria' ); ?></h2>

		<?php // The listing is named, never searched for: they arrived from it. ?>
		<p class="odlg__for"><?php echo esc_html( $oria_cl_name ); ?></p>

		<p class="odlg__intro"><?php esc_html_e( 'Verify your connection to this practice and take control of the information shown here. Claiming is free and no card is required.', 'oria' ); ?></p>

		<?php
		/*
		 * Only what actually works today. The listing editor, the photo
		 * cap and the click stats are all live on the claimed tier
		 * (Tiers\allows: manage, offers, analytics), so all four lines
		 * here are things somebody gets the week they are approved.
		 */
		?>
		<ul class="odlg__gets">
			<li><?php esc_html_e( 'Update practice details, services and contact information', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Add or improve photos and profile content', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Keep the listing accurate for people deciding where to go', 'oria' ); ?></li>
			<li><?php esc_html_e( 'See how many people found your profile and what they did next', 'oria' ); ?></li>
		</ul>

		<?php if ( $oria_cl_error ) : ?>
			<p class="odlg__error" role="alert"><?php esc_html_e( 'That did not send. Check your name and email, then try again.', 'oria' ); ?></p>
		<?php endif; ?>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="odlg__form">
			<input type="hidden" name="action" value="oria_claim">
			<input type="hidden" name="listing_id" value="<?php echo (int) $oria_cl_id; ?>">
			<?php wp_nonce_field( 'oria_claim', 'oria_claim_nonce' ); ?>
			<?php // The existing honeypot, unchanged: no CAPTCHA has been needed. ?>
			<input type="text" name="oria_website_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="odlg__hp">

			<div class="odlg__row">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Your full name', 'oria' ); ?></span>
					<input class="input" type="text" name="claimant_name" autocomplete="name" required></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Your role here', 'oria' ); ?></span>
					<input class="input" type="text" name="claimant_role" autocomplete="organization-title"
						placeholder="<?php esc_attr_e( 'e.g. owner, studio manager', 'oria' ); ?>"></label>
			</div>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Work email', 'oria' ); ?></span>
				<input class="input" type="email" name="claimant_email" autocomplete="email" required
					placeholder="<?php esc_attr_e( 'Ideally one at the practice\'s own domain', 'oria' ); ?>">
				<?php // Said once, plainly, so a Gmail address does not look like a wasted trip. ?>
				<span class="field__hint"><?php esc_html_e( 'A Gmail, Outlook or booking-platform address is fine — we will just check it by hand.', 'oria' ); ?></span>
			</label>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Phone (optional)', 'oria' ); ?></span>
				<input class="input" type="text" name="claimant_phone" autocomplete="tel"></label>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Anything that helps us confirm it is you (optional)', 'oria' ); ?></span>
				<textarea class="textarea" name="claimant_note" rows="3"
					placeholder="<?php esc_attr_e( 'e.g. where on your website we can see your name', 'oria' ); ?>"></textarea></label>

			<button class="btn btn--dark odlg__send" type="submit"><?php esc_html_e( 'Send claim request', 'oria' ); ?></button>

			<p class="odlg__after"><?php esc_html_e( 'A person reads every request. If it checks out we will email you a link to set a password, and the profile is yours to edit from then on.', 'oria' ); ?></p>
		</form>
	</div>
</dialog>

<dialog class="odlg" id="oria-corr-dialog" aria-labelledby="ocorr-dlg-head">
	<form method="dialog" class="odlg__x">
		<button type="submit" class="odlg__close" aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
	</form>

	<?php
	/*
	 * autofocus on the panel rather than letting the browser take the
	 * first focusable child, which is the close button -- a screen
	 * reader would open on the word "Close", and on a phone focusing a
	 * text field instead would throw the keyboard up over the dialog
	 * before anybody had read the heading.
	 */
	?>
	<div class="odlg__in" tabindex="-1" autofocus>
		<h2 class="odlg__head" id="ocorr-dlg-head"><?php esc_html_e( 'Suggest an update', 'oria' ); ?></h2>
		<p class="odlg__for"><?php echo esc_html( $oria_cl_name ); ?></p>
		<p class="odlg__intro"><?php esc_html_e( 'Spotted information that needs updating? Let us know and the Oria Haven team will review it.', 'oria' ); ?></p>

		<?php // The existing correction form, unfolded: the dialog is the fold now. ?>
		<?php get_template_part( 'template-parts/correction-form', null, array( 'id' => $oria_cl_id, 'bare' => true ) ); ?>
	</div>
</dialog>

<noscript>
	<style>
		/* Without scripting nothing can open a dialog, so both stand open. */
		.odlg { display: block; position: static; width: auto; max-width: none; border: 0; padding: 0; margin: var(--s-5) 0 0; background: none; }
		.odlg__x { display: none; }
	</style>
</noscript>

<script>
/*
 * Native <dialog>: the focus trap, Escape and focus restoration are the
 * browser's, not ours, and there is no library. Only the opening is here.
 */
(function () {
	var open = function (sel) {
		var d = document.getElementById(sel);
		if (!d) { return; }
		if (typeof d.showModal === 'function') { d.showModal(); } else { d.setAttribute('open', ''); }
	};

	document.addEventListener('click', function (e) {
		var claim = e.target.closest('[data-oria-claim-open]');
		if (claim) { open('oria-claim-dialog'); return; }
		var corr = e.target.closest('[data-oria-corr-open]');
		if (corr) { open('oria-corr-dialog'); }
	});

	/*
	 * A rejected submission comes back as ?oria_claim=error, and the form
	 * it belongs to is inside the dialog -- so reopen it, or the error
	 * message is written somewhere nobody can see.
	 */
	if (/[?&]oria_claim=error/.test(location.search)) { open('oria-claim-dialog'); }
	if (/[?&]ocorr=error/.test(location.search)) { open('oria-corr-dialog'); }

	// The share page and old emails link to #claim; honour it.
	if (location.hash === '#claim') { open('oria-claim-dialog'); }
}());
</script>
