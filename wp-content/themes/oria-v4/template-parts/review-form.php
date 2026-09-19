<?php
/**
 * The review form, in two steps (v4 child override of the parent part).
 *
 * Same form, same handler (Oria\Core\ReviewSubmit\handle, action
 * "oria_review"), same field names, nonce, timestamp and honeypot as the
 * parent's template-parts/review-form.php. What changes is the order:
 *
 *   Step 1 -- the three answers the handler requires (stars, what you tried,
 *             would you recommend) and the short review.
 *   Step 2 -- everything optional (experience, who it suits, when, would you
 *             return) and, for a visitor who is not signed in, the name and
 *             email the handler needs to send its confirmation link.
 *
 * Without JavaScript both steps are simply shown one after the other and the
 * "Continue" / "Back" buttons stay hidden: nothing depends on the script.
 * v4-listing.js turns the two blocks into steps and checks step 1's required
 * answers before moving on, so a browser never tries to focus a required
 * field inside a hidden step.
 *
 * The prompt still asks about the experience rather than the effect: a
 * wellness directory that invites "did it help your condition?" collects
 * health claims it cannot publish.
 *
 * @var array $args {
 *     @type int $listing_id
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oria_listing_id = (int) ( $args['listing_id'] ?? get_the_ID() );

if ( ! function_exists( '\Oria\Core\ReviewSubmit\tried_options' ) ) {
	return;
}

$oria_tried  = \Oria\Core\ReviewSubmit\tried_options( $oria_listing_id );
$oria_member = function_exists( '\Oria\Core\Members\current' ) ? \Oria\Core\Members\current() : null;
$oria_ready  = null !== $oria_member && \Oria\Core\Members\STATUS_ACTIVE === $oria_member['status'];

// A practitioner or staff account is told plainly rather than shown a form
// that will refuse them after they have typed three paragraphs.
$oria_blocked = '';
if ( is_user_logged_in() && function_exists( '\Oria\Core\Members\can_review' ) ) {
	$oria_can = \Oria\Core\Members\can_review();
	if ( is_wp_error( $oria_can ) && in_array( $oria_can->get_error_code(), array( 'oria_practitioner', 'oria_staff', 'oria_member_muted', 'oria_member_banned' ), true ) ) {
		$oria_blocked = (string) $oria_can->get_error_message();
	}
}

$oria_audiences = function_exists( '\Oria\Core\Audience\vocabulary' ) ? \Oria\Core\Audience\vocabulary() : array();
$oria_levels    = \Oria\Core\Reviews\experience_levels();

/*
 * What just happened, if anything. The submit handler sends the visitor back
 * here with a state; without rendering it, a review would vanish into an
 * apparently unchanged page and be written again.
 */
$oria_state = isset( $_GET['review'] ) ? sanitize_key( wp_unslash( (string) $_GET['review'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_why   = isset( $_GET['why'] ) ? sanitize_key( wp_unslash( (string) $_GET['why'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$oria_said    = \Oria\Core\ReviewSubmit\message_for( $oria_state, $oria_why );
$oria_message = null !== $oria_said ? $oria_said['text'] : '';
$oria_done    = null !== $oria_said && 'done' === $oria_said['kind'];

$oria_fid = (string) $oria_listing_id; // Suffix for every id, so two forms can never collide.
?>

<div class="reviewform" id="write-review">
	<h3 class="h3 reviewform__title" id="write-review-title" tabindex="-1"><?php esc_html_e( 'Been here? Write a review', 'oria' ); ?></h3>

	<?php if ( '' !== $oria_message ) : ?>
		<p class="notice reviewform__said" role="status"><?php echo esc_html( $oria_message ); ?></p>
	<?php endif; ?>

	<?php if ( $oria_done ) : ?>
		<?php // Nothing more to do here: showing the form again invites a second attempt. ?>
	<?php elseif ( '' !== $oria_blocked ) : ?>
		<p class="notice notice--plain"><?php echo esc_html( $oria_blocked ); ?></p>
	<?php else : ?>

		<p class="hint reviewform__lede">
			<?php esc_html_e( 'A few taps is plenty. Every review is read by a person before it appears, and we publish the critical ones too.', 'oria' ); ?>
		</p>

		<?php
		/*
		 * Signing in with Google first skips the email confirmation
		 * entirely. Offered before the form rather than inside it, because
		 * coming back from Google reloads the page and anything typed would
		 * be lost.
		 */
		if ( ! $oria_ready && function_exists( '\Oria\Core\GoogleAuth\available' ) && \Oria\Core\GoogleAuth\available() ) :
			?>
			<div class="reviewform__google">
				<a class="btn btn--ghost gbtn" href="<?php echo esc_url( \Oria\Core\GoogleAuth\start_url( get_permalink( $oria_listing_id ) . '#write-review' ) ); ?>" rel="nofollow">
					<svg class="gbtn__mark" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
						<path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/>
						<path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.83.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/>
						<path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"/>
						<path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.9 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/>
					</svg>
					<?php esc_html_e( 'Continue with Google', 'oria' ); ?>
				</a>
				<span class="hint reviewform__or"><?php esc_html_e( 'Skips the email confirmation. Or just fill this in:', 'oria' ); ?></span>
			</div>
		<?php endif; ?>

		<form class="reviewform__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-review-steps>
			<input type="hidden" name="action" value="oria_review">
			<input type="hidden" name="listing" value="<?php echo esc_attr( $oria_fid ); ?>">
			<input type="hidden" name="oria_ts" value="<?php echo esc_attr( (string) time() ); ?>">
			<?php wp_nonce_field( 'oria_review_' . $oria_listing_id, 'oria_review_nonce' ); ?>

			<?php // Humans never see this; bots fill it in. Off-screen, out of the tab order and out of the accessibility tree. ?>
			<div class="reviewform__hp" aria-hidden="true">
				<label for="oria_website_<?php echo esc_attr( $oria_fid ); ?>"><?php esc_html_e( 'Leave this empty', 'oria' ); ?></label>
				<input type="text" id="oria_website_<?php echo esc_attr( $oria_fid ); ?>" name="oria_website" tabindex="-1" autocomplete="off" aria-hidden="true">
			</div>

			<!-- Step 1: the answers the handler requires, and the review itself. -->
			<div class="reviewform__step" data-review-step="1">
				<p class="reviewform__stepno" data-review-stepno hidden><?php esc_html_e( 'Step 1 of 2', 'oria' ); ?></p>

				<fieldset class="reviewform__row reviewform__stars">
					<legend class="reviewform__label"><?php esc_html_e( 'Overall rating', 'oria' ); ?> <span class="reviewform__req" aria-hidden="true">*</span></legend>
					<?php
					/*
					 * A plain select is the real control; app.js draws a star
					 * widget beside it and keeps the two in step. The select
					 * stays focusable and is what the form submits.
					 */
					?>
					<div class="starrate" data-starrate>
						<label class="sr-only" for="oria-rating-<?php echo esc_attr( $oria_fid ); ?>"><?php esc_html_e( 'Your rating out of five', 'oria' ); ?></label>
						<select class="select starrate__select" id="oria-rating-<?php echo esc_attr( $oria_fid ); ?>" name="oria_rating" required>
							<option value=""><?php esc_html_e( 'Choose a rating…', 'oria' ); ?></option>
							<?php foreach ( array_reverse( \Oria\Core\Reviews\rating_steps() ) as $oria_val ) : ?>
								<option value="<?php echo esc_attr( (string) $oria_val ); ?>">
									<?php
									printf(
										/* translators: %s: a rating such as 3.5 */
										esc_html__( '%s stars', 'oria' ),
										esc_html( \Oria\Core\Reviews\rating_label( (float) $oria_val ) )
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</fieldset>

				<?php if ( $oria_tried ) : ?>
					<div class="reviewform__row">
						<label class="reviewform__label" for="oria-service-<?php echo esc_attr( $oria_fid ); ?>">
							<?php esc_html_e( 'What did you try?', 'oria' ); ?> <span class="reviewform__req" aria-hidden="true">*</span>
						</label>
						<select class="select" id="oria-service-<?php echo esc_attr( $oria_fid ); ?>" name="service" required>
							<option value=""><?php esc_html_e( 'Choose one…', 'oria' ); ?></option>
							<?php foreach ( $oria_tried as $oria_term ) : ?>
								<option value="<?php echo esc_attr( (string) $oria_term->term_id ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_term ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<fieldset class="reviewform__row">
					<legend class="reviewform__label"><?php esc_html_e( 'Would you recommend it?', 'oria' ); ?> <span class="reviewform__req" aria-hidden="true">*</span></legend>
					<div class="reviewform__choice">
						<label><input type="radio" name="recommend" value="yes" required> <?php esc_html_e( 'Yes', 'oria' ); ?></label>
						<label><input type="radio" name="recommend" value="no"> <?php esc_html_e( 'No', 'oria' ); ?></label>
					</div>
				</fieldset>

				<div class="reviewform__row">
					<label class="reviewform__label" for="oria-body-<?php echo esc_attr( $oria_fid ); ?>"><?php esc_html_e( 'Short review', 'oria' ); ?> <span class="reviewform__opt"><?php esc_html_e( '(optional)', 'oria' ); ?></span></label>
					<textarea class="input" id="oria-body-<?php echo esc_attr( $oria_fid ); ?>" name="body" rows="4" maxlength="1000"
						aria-describedby="oria-body-note-<?php echo esc_attr( $oria_fid ); ?>"
						placeholder="<?php esc_attr_e( 'What was the session like? What should somebody know before they go — parking, what to bring, how busy it gets?', 'oria' ); ?>"></textarea>
					<p class="hint reviewform__note" id="oria-body-note-<?php echo esc_attr( $oria_fid ); ?>">
						<?php esc_html_e( 'Please keep to your experience. We cannot publish reviews describing medical conditions or health outcomes.', 'oria' ); ?>
					</p>
				</div>

				<div class="reviewform__nav" data-review-nav hidden>
					<button class="btn btn--dark reviewform__next" type="button" data-review-next>
						<?php echo esc_html( $oria_ready ? __( 'Add a few details', 'oria' ) : __( 'Continue', 'oria' ) ); ?>
					</button>
					<?php if ( $oria_ready ) : ?>
						<?php // A signed-in member has nothing left that is required: they may send straight away. ?>
						<button class="btn btn--ghost" type="submit"><?php esc_html_e( 'Send my review', 'oria' ); ?></button>
					<?php else : ?>
						<span class="hint reviewform__navnote"><?php esc_html_e( 'Next: a few optional details and your email.', 'oria' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<!-- Step 2: optional details, then the email the confirmation goes to. -->
			<div class="reviewform__step" data-review-step="2">
				<p class="reviewform__stepno" data-review-stepno hidden tabindex="-1"><?php esc_html_e( 'Step 2 of 2 — all optional unless marked', 'oria' ); ?></p>

				<fieldset class="reviewform__row">
					<legend class="reviewform__label"><?php esc_html_e( 'How much had you done before?', 'oria' ); ?></legend>
					<div class="reviewform__choice">
						<?php foreach ( $oria_levels as $oria_key => $oria_label ) : ?>
							<label><input type="radio" name="experience" value="<?php echo esc_attr( $oria_key ); ?>"> <?php echo esc_html( $oria_label ); ?></label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<?php if ( $oria_audiences ) : ?>
					<fieldset class="reviewform__row">
						<legend class="reviewform__label"><?php esc_html_e( 'Who would it suit?', 'oria' ); ?></legend>
						<div class="reviewform__tags">
							<?php foreach ( $oria_audiences as $oria_slug => $oria_aud ) : ?>
								<label class="spectag">
									<input type="checkbox" name="best_for[]" value="<?php echo esc_attr( (string) $oria_slug ); ?>">
									<span><?php echo esc_html( (string) $oria_aud['name'] ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endif; ?>

				<div class="reviewform__row reviewform__split">
					<div>
						<label class="reviewform__label" for="oria-visit-<?php echo esc_attr( $oria_fid ); ?>"><?php esc_html_e( 'When did you go?', 'oria' ); ?></label>
						<input class="input" type="month" id="oria-visit-<?php echo esc_attr( $oria_fid ); ?>" name="visit_month" max="<?php echo esc_attr( gmdate( 'Y-m' ) ); ?>">
					</div>
					<fieldset class="reviewform__row reviewform__row--flat">
						<legend class="reviewform__label"><?php esc_html_e( 'Would you go again?', 'oria' ); ?></legend>
						<div class="reviewform__choice">
							<label><input type="radio" name="would_return" value="yes"> <?php esc_html_e( 'Yes', 'oria' ); ?></label>
							<label><input type="radio" name="would_return" value="no"> <?php esc_html_e( 'No', 'oria' ); ?></label>
						</div>
					</fieldset>
				</div>

				<?php if ( ! $oria_ready ) : ?>
					<div class="reviewform__row reviewform__split">
						<div>
							<label class="reviewform__label" for="oria-name-<?php echo esc_attr( $oria_fid ); ?>"><?php esc_html_e( 'Your name', 'oria' ); ?></label>
							<input class="input" type="text" id="oria-name-<?php echo esc_attr( $oria_fid ); ?>" name="name" autocomplete="name"
								aria-describedby="oria-name-note-<?php echo esc_attr( $oria_fid ); ?>"
								placeholder="<?php esc_attr_e( 'Jessica Miller', 'oria' ); ?>">
							<p class="hint reviewform__note" id="oria-name-note-<?php echo esc_attr( $oria_fid ); ?>"><?php esc_html_e( 'Shown as a first name and initial, like "Jessica M."', 'oria' ); ?></p>
						</div>
						<div>
							<label class="reviewform__label" for="oria-email-<?php echo esc_attr( $oria_fid ); ?>">
								<?php esc_html_e( 'Your email', 'oria' ); ?> <span class="reviewform__req" aria-hidden="true">*</span>
							</label>
							<input class="input" type="email" id="oria-email-<?php echo esc_attr( $oria_fid ); ?>" name="email" required autocomplete="email"
								aria-describedby="oria-email-note-<?php echo esc_attr( $oria_fid ); ?>">
							<p class="hint reviewform__note" id="oria-email-note-<?php echo esc_attr( $oria_fid ); ?>"><?php esc_html_e( 'Only to confirm it is you. Never shown, never sold.', 'oria' ); ?></p>
						</div>
					</div>
				<?php endif; ?>

				<div class="reviewform__foot">
					<button class="btn btn--dark reviewform__send" type="submit"><?php esc_html_e( 'Send my review', 'oria' ); ?></button>
					<button class="btn btn--ghost reviewform__back" type="button" data-review-back hidden><?php esc_html_e( 'Back', 'oria' ); ?></button>
				</div>
				<p class="hint reviewform__note">
					<?php
					echo wp_kses_post(
						\Oria\Core\Reviews\policy_line(
							/* translators: %s: link to the reviews policy, dropped until that page exists */
							esc_html__( 'Reviews are never affected by whether a practice pays for a listing. %s', 'oria' ),
							esc_html__( 'How reviews work here', 'oria' )
						)
					);
					?>
				</p>
			</div>
		</form>
	<?php endif; ?>
</div>
