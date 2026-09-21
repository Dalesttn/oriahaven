<?php
/**
 * "Tell us what is wrong" -- the small door.
 *
 * Folded, because this is not what most visitors came for; but one click
 * from the summary to a filled-in form, and it asks only what is needed
 * to act on: what is wrong, what it should say, and how to reply. No
 * account, and nothing about claiming until the correction is done.
 *
 * $args: id (listing post id)
 */

declare(strict_types=1);

use Oria\Core\Corrections;

$oria_cid = (int) ( $args['id'] ?? get_the_ID() );
if ( ! $oria_cid || ! function_exists( '\Oria\Core\Corrections\kinds' ) ) {
	return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading a display state.
$oria_cstate = isset( $_GET['ocorr'] ) ? sanitize_key( (string) wp_unslash( $_GET['ocorr'] ) ) : '';
$oria_cdone  = in_array( $oria_cstate, array( 'received', 'received_owner' ), true );
$oria_cowner = 'received_owner' === $oria_cstate;
$oria_cclaim = ! get_post_meta( $oria_cid, 'claimed_by', true );
?>

<div class="ocorr" id="correct">
	<?php if ( $oria_cdone ) : ?>
		<div class="ocorr__done notice" role="status" data-oria-event="correction_submitted">
			<p class="ocorr__thanks"><b><?php esc_html_e( 'Thank you.', 'oria' ); ?></b>
				<?php esc_html_e( 'We check every correction by hand and will fix it — you do not need an account, and we will email you when it is done.', 'oria' ); ?></p>

			<?php
			/*
			 * The offer, and only now. Somebody who said they run the place
			 * is shown it plainly; everybody else gets nothing, because the
			 * correction was the whole transaction as far as they are
			 * concerned.
			 */
			?>
			<?php if ( $oria_cowner && $oria_cclaim ) : ?>
				<p class="ocorr__offer">
					<?php esc_html_e( 'Since you are connected to this place, you can also look after the page yourself — free, no card, and it is already built.', 'oria' ); ?>
					<a class="ocorr__offerlink" href="#claim"><?php esc_html_e( 'Manage this profile free', 'oria' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<details class="ocorr__fold"<?php echo 'error' === $oria_cstate ? ' open' : ''; ?>>
			<summary class="ocorr__open"><?php esc_html_e( 'Tell us what is wrong', 'oria' ); ?></summary>

			<div class="ocorr__body">
				<?php if ( 'error' === $oria_cstate ) : ?>
					<p class="ocorr__error" role="alert"><?php esc_html_e( 'That did not send. Check your name, email and the description, then try again.', 'oria' ); ?></p>
				<?php endif; ?>

				<p class="ocorr__note"><?php esc_html_e( 'We will fix it whether or not you have anything to do with the place. No account needed.', 'oria' ); ?></p>

				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="ocorr__form" data-oria-event="correction_started">
					<input type="hidden" name="action" value="oria_correction">
					<input type="hidden" name="listing_id" value="<?php echo (int) $oria_cid; ?>">
					<input type="hidden" name="ocorr_ts" value="<?php echo (int) time(); ?>">
					<?php wp_nonce_field( 'oria_correction_' . $oria_cid, 'ocorr_nonce' ); ?>
					<input type="text" name="ocorr_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="ocorr__hp">

					<label class="field">
						<span class="field__label"><?php esc_html_e( 'What needs changing?', 'oria' ); ?></span>
						<select class="input" name="ocorr_kind" required>
							<?php foreach ( Corrections\kinds() as $oria_ck => $oria_cv ) : ?>
								<option value="<?php echo esc_attr( $oria_ck ); ?>"><?php echo esc_html( $oria_cv ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<label class="field">
						<span class="field__label"><?php esc_html_e( 'What should it say?', 'oria' ); ?></span>
						<textarea class="input ocorr__what" name="ocorr_what" rows="4" required
							placeholder="<?php esc_attr_e( 'e.g. we have no set opening hours — please ask people to email us instead', 'oria' ); ?>"></textarea>
					</label>

					<div class="ocorr__row">
						<label class="field">
							<span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span>
							<input class="input" type="text" name="ocorr_name" autocomplete="name" required>
						</label>
						<label class="field">
							<span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
							<input class="input" type="email" name="ocorr_email" autocomplete="email" required>
						</label>
					</div>

					<label class="field">
						<span class="field__label"><?php esc_html_e( 'How do you know this place? (optional)', 'oria' ); ?></span>
						<select class="input" name="ocorr_rel">
							<option value=""><?php esc_html_e( 'Rather not say', 'oria' ); ?></option>
							<?php foreach ( Corrections\relationships() as $oria_rk => $oria_rv ) : ?>
								<option value="<?php echo esc_attr( $oria_rk ); ?>"><?php echo esc_html( $oria_rv ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<button class="btn btn--dark ocorr__send" type="submit"><?php esc_html_e( 'Send the correction', 'oria' ); ?></button>
				</form>
			</div>
		</details>
	<?php endif; ?>
</div>
