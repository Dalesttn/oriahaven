<?php
/**
 * The review form.
 *
 * Three required answers — stars, what you tried, would you recommend — and
 * everything else optional. Each extra required field costs completions, and
 * the optional ones are still filled in by most people.
 *
 * The prompt deliberately asks about the experience rather than the effect.
 * A wellness directory that invites "did it help your condition?" collects
 * health claims it cannot publish, so the question is what the session was
 * like and what somebody should know before going.
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

$oria_tried = \Oria\Core\ReviewSubmit\tried_options( $oria_listing_id );
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
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$oria_state = isset( $_GET['review'] ) ? sanitize_key( wp_unslash( (string) $_GET['review'] ) ) : '';
$oria_why   = isset( $_GET['why'] ) ? sanitize_key( wp_unslash( (string) $_GET['why'] ) ) : '';
// phpcs:enable

$oria_says = array(
	'sent'   => __( 'Nearly there — check your email and tap the link to confirm your review. It lasts 30 minutes.', 'oria' ),
	'queued' => __( 'Thank you. Your review is with us to read, and appears once it is approved.', 'oria' ),
	'stale'  => __( 'That link had already been used, or it expired. Write your review again and we will send a fresh one.', 'oria' ),
	'reported' => __( 'Thank you — we will look at that review again. It stays published while we do.', 'oria' ),
	'reply-queued' => __( 'Your reply is with us to read. It appears under the review once approved.', 'oria' ),
);

$oria_errors = array(
	'rating'    => __( 'Choose a star rating.', 'oria' ),
	'service'   => __( 'Tell us what you tried.', 'oria' ),
	'recommend' => __( 'Let us know whether you would recommend it.', 'oria' ),
	'already'   => __( 'You have already reviewed this one. One review each keeps them honest.', 'oria' ),
	'throttled' => __( 'That is a lot of reviews for one day. Try again tomorrow.', 'oria' ),
	'expired'   => __( 'That form had been open a while. Have another go.', 'oria' ),
	'browser'   => __( 'That sign-in did not finish safely. Please try again.', 'oria' ),
	'not_owner' => __( 'Only the practice that owns this listing can reply to its reviews.', 'oria' ),
	'reason'    => __( 'Choose a reason for the report.', 'oria' ),
	'empty'     => __( 'Write something before sending the reply.', 'oria' ),
	'state'     => __( 'That sign-in took too long. Please try again.', 'oria' ),
	'cancelled' => __( 'No problem — you can still review using your email instead.', 'oria' ),
	'unverified' => __( 'That Google account has no confirmed email address, so we cannot use it.', 'oria' ),
	'timing'    => __( 'That form had been open a while. Have another go.', 'oria' ),
);

$oria_message = '';
$oria_done    = false;
if ( isset( $oria_says[ $oria_state ] ) ) {
	$oria_message = $oria_says[ $oria_state ];
	$oria_done    = in_array( $oria_state, array( 'sent', 'queued' ), true );
} elseif ( 'error' === $oria_state ) {
	$oria_message = $oria_errors[ $oria_why ] ?? __( 'That did not go through. Have another go.', 'oria' );
} elseif ( 'blocked' === $oria_state ) {
	$oria_blocks  = array(
		'oria_practitioner_email' => __( 'That email is already registered as a practice on Oria Haven. Practices cannot post reviews.', 'oria' ),
		'oria_practitioner'       => __( 'Practices listed on Oria Haven cannot post reviews.', 'oria' ),
		'oria_staff_email'        => __( 'That email belongs to a staff account.', 'oria' ),
		'oria_staff'              => __( 'Staff accounts cannot post reviews.', 'oria' ),
		'oria_member_muted'       => __( 'This account is now listed as a practice, so it can no longer post reviews.', 'oria' ),
		'oria_member_banned'      => __( 'This account cannot post reviews.', 'oria' ),
		'oria_bad_email'          => __( 'That email address does not look right.', 'oria' ),
	);
	// A failed Google round trip is a retry, not a dead end.
	$oria_retryable = array( 'browser', 'state', 'cancelled', 'unverified', 'code', 'nonce', 'aud', 'iss', 'expired', 'exchange', 'network', 'malformed', 'no_id_token', 'unavailable' );
	$oria_message   = $oria_blocks[ $oria_why ] ?? ( $oria_errors[ $oria_why ] ?? __( 'That account cannot post reviews.', 'oria' ) );
	$oria_done      = ! in_array( $oria_why, $oria_retryable, true );
}
?>

<div class="reviewform" id="write-review">
	<h3 class="h3 reviewform__title"><?php esc_html_e( 'Been here? Write a review', 'oria' ); ?></h3>

	<?php if ( '' !== $oria_message ) : ?>
		<p class="notice reviewform__said"><?php echo esc_html( $oria_message ); ?></p>
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
		 * entirely — Google has already proved the address. Offered before
		 * the form rather than inside it, because coming back from Google
		 * reloads the page and anything typed would be lost.
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

		<form class="reviewform__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_review">
			<input type="hidden" name="listing" value="<?php echo esc_attr( (string) $oria_listing_id ); ?>">
			<input type="hidden" name="oria_ts" value="<?php echo esc_attr( (string) time() ); ?>">
			<?php wp_nonce_field( 'oria_review_' . $oria_listing_id, 'oria_review_nonce' ); ?>

			<?php // Humans never see this; bots fill it in. ?>
			<div class="reviewform__hp" aria-hidden="true">
				<label for="oria_website_<?php echo esc_attr( (string) $oria_listing_id ); ?>"><?php esc_html_e( 'Leave this empty', 'oria' ); ?></label>
				<input type="text" id="oria_website_<?php echo esc_attr( (string) $oria_listing_id ); ?>" name="oria_website" tabindex="-1" autocomplete="off">
			</div>

			<fieldset class="reviewform__row reviewform__stars">
				<legend class="reviewform__label"><?php esc_html_e( 'Overall', 'oria' ); ?> <span class="reviewform__req">*</span></legend>
				<?php
				/*
				 * A plain select is the real control: it works with no
				 * JavaScript, no CSS, and on any assistive technology,
				 * which a pile of clipped radios never quite does.
				 *
				 * app.js draws a star widget beside it and keeps the two in
				 * step. The select stays in the DOM and stays focusable —
				 * it is what the form actually submits — so a keyboard user
				 * picks "3.5 stars" from a list while a mouse user drags
				 * across stars. Nothing is lost if the script never runs.
				 */
				?>
				<div class="starrate" data-starrate>
					<label class="sr-only" for="oria-rating-<?php echo esc_attr( (string) $oria_listing_id ); ?>"><?php esc_html_e( 'Your rating out of five', 'oria' ); ?></label>
					<select class="select starrate__select" id="oria-rating-<?php echo esc_attr( (string) $oria_listing_id ); ?>" name="oria_rating" required>
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
					<label class="reviewform__label" for="oria-service-<?php echo esc_attr( (string) $oria_listing_id ); ?>">
						<?php esc_html_e( 'What did you try?', 'oria' ); ?> <span class="reviewform__req">*</span>
					</label>
					<select class="select" id="oria-service-<?php echo esc_attr( (string) $oria_listing_id ); ?>" name="service" required>
						<option value=""><?php esc_html_e( 'Choose one…', 'oria' ); ?></option>
						<?php foreach ( $oria_tried as $oria_term ) : ?>
							<option value="<?php echo esc_attr( (string) $oria_term->term_id ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_term ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="reviewform__row">
				<span class="reviewform__label"><?php esc_html_e( 'Would you recommend it?', 'oria' ); ?> <span class="reviewform__req">*</span></span>
				<div class="reviewform__choice">
					<label><input type="radio" name="recommend" value="yes" required> <?php esc_html_e( 'Yes', 'oria' ); ?></label>
					<label><input type="radio" name="recommend" value="no"> <?php esc_html_e( 'No', 'oria' ); ?></label>
				</div>
			</div>

			<div class="reviewform__row">
				<span class="reviewform__label"><?php esc_html_e( 'How much had you done before?', 'oria' ); ?></span>
				<div class="reviewform__choice">
					<?php foreach ( $oria_levels as $oria_key => $oria_label ) : ?>
						<label><input type="radio" name="experience" value="<?php echo esc_attr( $oria_key ); ?>"> <?php echo esc_html( $oria_label ); ?></label>
					<?php endforeach; ?>
				</div>
			</div>

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
					<label class="reviewform__label" for="oria-visit-<?php echo esc_attr( (string) $oria_listing_id ); ?>"><?php esc_html_e( 'When did you go?', 'oria' ); ?></label>
					<input class="input" type="month" id="oria-visit-<?php echo esc_attr( (string) $oria_listing_id ); ?>" name="visit_month" max="<?php echo esc_attr( gmdate( 'Y-m' ) ); ?>">
				</div>
				<div>
					<span class="reviewform__label"><?php esc_html_e( 'Would you go again?', 'oria' ); ?></span>
					<div class="reviewform__choice">
						<label><input type="radio" name="would_return" value="yes"> <?php esc_html_e( 'Yes', 'oria' ); ?></label>
						<label><input type="radio" name="would_return" value="no"> <?php esc_html_e( 'No', 'oria' ); ?></label>
					</div>
				</div>
			</div>

			<div class="reviewform__row">
				<label class="reviewform__label" for="oria-body-<?php echo esc_attr( (string) $oria_listing_id ); ?>"><?php esc_html_e( 'Anything else?', 'oria' ); ?></label>
				<textarea class="input" id="oria-body-<?php echo esc_attr( (string) $oria_listing_id ); ?>" name="body" rows="4" maxlength="1000"
					placeholder="<?php esc_attr_e( 'What was the session like? What should somebody know before they go — parking, what to bring, how busy it gets?', 'oria' ); ?>"></textarea>
				<p class="hint reviewform__note">
					<?php esc_html_e( 'Please keep to your experience. We cannot publish reviews describing medical conditions or health outcomes.', 'oria' ); ?>
				</p>
			</div>

			<?php if ( ! $oria_ready ) : ?>
				<div class="reviewform__row reviewform__split">
					<div>
						<label class="reviewform__label" for="oria-name-<?php echo esc_attr( (string) $oria_listing_id ); ?>"><?php esc_html_e( 'Your name', 'oria' ); ?></label>
						<input class="input" type="text" id="oria-name-<?php echo esc_attr( (string) $oria_listing_id ); ?>" name="name" autocomplete="name"
							placeholder="<?php esc_attr_e( 'Jessica Miller', 'oria' ); ?>">
						<p class="hint reviewform__note"><?php esc_html_e( 'Shown as a first name and initial, like "Jessica M."', 'oria' ); ?></p>
					</div>
					<div>
						<label class="reviewform__label" for="oria-email-<?php echo esc_attr( (string) $oria_listing_id ); ?>">
							<?php esc_html_e( 'Your email', 'oria' ); ?> <span class="reviewform__req">*</span>
						</label>
						<input class="input" type="email" id="oria-email-<?php echo esc_attr( (string) $oria_listing_id ); ?>" name="email" required autocomplete="email">
						<p class="hint reviewform__note"><?php esc_html_e( 'Only to confirm it is you. Never shown, never sold.', 'oria' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

			<button class="btn btn--dark" type="submit">
				<?php echo $oria_ready ? esc_html__( 'Send my review', 'oria' ) : esc_html__( 'Send my review', 'oria' ); ?>
			</button>
			<p class="hint reviewform__note">
				<?php
				printf(
					/* translators: %s: link to the reviews policy */
					esc_html__( 'Reviews are never affected by whether a practice pays for a listing. %s', 'oria' ),
					'<a href="' . esc_url( home_url( '/reviews-policy/' ) ) . '">' . esc_html__( 'How reviews work here', 'oria' ) . '</a>'
				);
				?>
			</p>
		</form>
	<?php endif; ?>
</div>
