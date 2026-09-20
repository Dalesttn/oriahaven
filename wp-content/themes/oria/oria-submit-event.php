<?php
/**
 * /submit-an-event/ — the free, public route for an organiser to put a
 * Perth wellness event on the site.
 *
 * Rendered by a route registered in \Oria\Core\EventSubmit, not by a
 * WordPress page: a page template attaches by slug, and a page somebody
 * has to remember to create is a page production does not have.
 *
 * Free, and no account needed: the point is to hear about the Tuesday sound
 * bath, not to sell anything at the door. Submissions arrive as drafts and a
 * person checks them. Handled by \Oria\Core\EventSubmit.
 *
 * The form is plain HTML posting to admin-post.php. submit-event.js adds a
 * searchable suburb box, an image preview and a word counter on top of it;
 * with that file blocked every one of those falls back to the control it
 * was built from, and the form still submits.
 */

declare(strict_types=1);

use Oria\Core\EventSubmit;

get_header();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_sent  = isset( $_GET['sent'] );
$oria_codes = array_filter( explode( ',', sanitize_text_field( wp_unslash( (string) ( $_GET['e'] ?? '' ) ) ) ) );
// phpcs:enable
$oria_errors = EventSubmit\messages( implode( ',', $oria_codes ) );
$oria_fields = EventSubmit\error_fields();
$oria_old    = EventSubmit\stashed();
$oria_v      = static fn( string $k ): string => esc_attr( (string) ( $oria_old[ $k ] ?? '' ) );

/** Does this field have an error against it? */
$oria_bad = static function ( string $field ) use ( $oria_codes, $oria_fields ): bool {
	foreach ( $oria_codes as $oria_code ) {
		if ( ( $oria_fields[ $oria_code ] ?? '' ) === $field ) {
			return true;
		}
	}
	return false;
};

$oria_types = get_terms( array( 'taxonomy' => 'event_type', 'hide_empty' => false ) );
$oria_types = is_wp_error( $oria_types ) ? array() : $oria_types;

/*
 * Suburbs grouped under their region. Flat, the list runs to about 150
 * entries and shows "Margaret River" twice -- once as the region, once as
 * the suburb inside it -- which is a menu nobody can use. Regions become
 * the optgroup labels, and only leaf suburbs are selectable. A region with
 * no children (a standalone area term) stays selectable in its own right.
 */
$oria_areas = get_terms( array( 'taxonomy' => 'area', 'hide_empty' => false ) );
$oria_areas = is_wp_error( $oria_areas ) ? array() : $oria_areas;

$oria_groups = array();
$oria_loose  = array();
foreach ( $oria_areas as $oria_a ) {
	if ( $oria_a->parent ) {
		$oria_groups[ $oria_a->parent ][] = $oria_a;
	}
}
foreach ( $oria_areas as $oria_a ) {
	if ( ! $oria_a->parent && ! isset( $oria_groups[ $oria_a->term_id ] ) ) {
		$oria_loose[] = $oria_a;
	}
}

/** The asterisk and its screen-reader word, so required is never colour alone. */
$oria_req = '<span class="sev-req" aria-hidden="true">*</span><span class="xp-vh"> ' . esc_html__( '(required)', 'oria' ) . '</span>';
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( home_url( '/whats-on-perth/' ) ); ?>"><?php esc_html_e( "What's On", 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Submit an event', 'oria' ); ?></span>
	</nav>
	<div style="margin-top:1rem;max-width:56rem">
		<span class="micro"><?php esc_html_e( 'Free, always', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title"><?php esc_html_e( 'Submit an event', 'oria' ); ?></h1>
		<p class="lede pagehead__lede"><?php esc_html_e( 'Running a workshop, sound bath, retreat day or class in Perth? Tell us about it and we will put it on the What\'s On page. It costs nothing, and you do not need an account.', 'oria' ); ?></p>
	</div>
</section>

<section class="wrap sev" id="submit">
	<?php if ( $oria_sent ) : ?>
		<?php
		/*
		 * The one moment this page has to feel like a person received
		 * something, rather than a form having been processed.
		 */
		?>
		<div class="sev-done">
			<span class="sev-done__mark" aria-hidden="true"><?php echo \Oria\Theme\mark( 'small', 40 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<h2 class="h2"><?php esc_html_e( 'Your event is on its way', 'oria' ); ?></h2>
			<p><?php esc_html_e( 'Thanks for sharing it with us. A person will read the details, usually within a couple of days, and we will email you if anything needs checking.', 'oria' ); ?></p>
			<p class="sev-done__acts">
				<a class="btn btn--dark" href="<?php echo esc_url( home_url( '/whats-on-perth/' ) ); ?>"><?php esc_html_e( "Browse what's on", 'oria' ); ?></a>
				<a class="btn" href="<?php echo esc_url( EventSubmit\page_url() ); ?>"><?php esc_html_e( 'Submit another event', 'oria' ); ?></a>
			</p>
			<p class="sev-done__note">
				<?php esc_html_e( 'Not listed with us yet?', 'oria' ); ?>
				<a href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'List your practice', 'oria' ); ?></a>
				<?php esc_html_e( '— also free, and it gives the event a profile to sit against.', 'oria' ); ?>
			</p>
		</div>
	<?php else : ?>

		<div class="sev__layout">
			<div class="sev__main">
				<?php if ( $oria_errors ) : ?>
					<div class="sev-errors" data-error-summary role="alert">
						<h2 class="sev-errors__title">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: how many things need fixing */
									_n( 'Please check %d detail', 'Please check %d details', count( $oria_errors ), 'oria' ),
									count( $oria_errors )
								)
							);
							?>
						</h2>
						<ul>
							<?php foreach ( $oria_codes as $oria_code ) : ?>
								<?php
								$oria_msg   = EventSubmit\messages( $oria_code );
								$oria_field = $oria_fields[ $oria_code ] ?? '';
								if ( ! $oria_msg ) {
									continue;
								}
								?>
								<li>
									<?php if ( '' !== $oria_field ) : ?>
										<a href="#sev-<?php echo esc_attr( $oria_field ); ?>"><?php echo esc_html( $oria_msg[0] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $oria_msg[0] ); ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<p class="sev__legend"><?php echo wp_kses_post( sprintf( /* translators: %s: an asterisk */ __( 'Fields marked %s are required. Everything else is optional — send what you have.', 'oria' ), '<span class="sev-req" aria-hidden="true">*</span>' ) ); ?></p>

				<form class="sev-form" method="post" enctype="multipart/form-data" data-submit-event
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="oria_event_submit">
					<input type="hidden" name="oria_ts" value="<?php echo esc_attr( (string) time() ); ?>">
					<?php wp_nonce_field( 'oria_event_submit', 'oria_event_nonce' ); ?>
					<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

					<fieldset class="sev-sec">
						<legend class="sev-sec__head">
							<span class="sev-sec__n" aria-hidden="true">1</span>
							<span class="sev-sec__title"><?php esc_html_e( 'The event', 'oria' ); ?></span>
						</legend>

						<label class="field" for="sev-title"><span class="field__label"><?php esc_html_e( 'Event name', 'oria' ); ?> <?php echo wp_kses_post( $oria_req ); ?></span>
							<input class="input" id="sev-title" type="text" name="title" required value="<?php echo $oria_v( 'title' ); ?>"
								<?php echo $oria_bad( 'title' ) ? 'aria-invalid="true"' : ''; ?>></label>

						<label class="field" for="sev-type"><span class="field__label"><?php esc_html_e( 'Kind of event', 'oria' ); ?> <?php echo wp_kses_post( $oria_req ); ?></span>
							<select class="select" id="sev-type" name="type" required <?php echo $oria_bad( 'type' ) ? 'aria-invalid="true"' : ''; ?>>
								<option value=""><?php esc_html_e( 'Choose…', 'oria' ); ?></option>
								<?php foreach ( $oria_types as $oria_t ) : ?>
									<option value="<?php echo esc_attr( $oria_t->slug ); ?>" <?php selected( $oria_v( 'type' ), $oria_t->slug ); ?>><?php echo esc_html( $oria_t->name ); ?></option>
								<?php endforeach; ?>
							</select></label>

						<div class="sev-grid">
							<label class="field" for="sev-start_date"><span class="field__label"><?php esc_html_e( 'Starts', 'oria' ); ?> <?php echo wp_kses_post( $oria_req ); ?></span>
								<input class="input" id="sev-start_date" type="date" name="start_date" required value="<?php echo $oria_v( 'start_date' ); ?>"
									<?php echo $oria_bad( 'start_date' ) ? 'aria-invalid="true"' : ''; ?>></label>
							<label class="field" for="sev-start_time"><span class="field__label"><?php esc_html_e( 'Start time', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
								<input class="input" id="sev-start_time" type="time" name="start_time" value="<?php echo $oria_v( 'start_time' ); ?>"></label>
						</div>

						<div class="sev-grid">
							<label class="field" for="sev-end_date"><span class="field__label"><?php esc_html_e( 'Ends', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
								<input class="input" id="sev-end_date" type="date" name="end_date" value="<?php echo $oria_v( 'end_date' ); ?>"
									<?php echo $oria_bad( 'end_date' ) ? 'aria-invalid="true"' : ''; ?>>
								<span class="oform-hint"><?php esc_html_e( 'Only for something running over more than one day.', 'oria' ); ?></span></label>
							<label class="field" for="sev-end_time"><span class="field__label"><?php esc_html_e( 'Finish time', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
								<input class="input" id="sev-end_time" type="time" name="end_time" value="<?php echo $oria_v( 'end_time' ); ?>"></label>
						</div>

						<div class="sev-grid">
							<label class="field" for="sev-venue"><span class="field__label"><?php esc_html_e( 'Venue', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
								<input class="input" id="sev-venue" type="text" name="venue" value="<?php echo $oria_v( 'venue' ); ?>" placeholder="<?php esc_attr_e( 'The hall, studio or park', 'oria' ); ?>"></label>

							<label class="field" data-suburb-label for="sev-suburb"><span class="field__label"><?php esc_html_e( 'Suburb', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
								<select class="select" id="sev-suburb" name="suburb" data-placeholder="<?php esc_attr_e( 'Start typing a suburb', 'oria' ); ?>"
									<?php echo $oria_bad( 'suburb' ) ? 'aria-invalid="true"' : ''; ?>>
									<option value=""><?php esc_html_e( 'Choose a suburb', 'oria' ); ?></option>
									<?php foreach ( $oria_groups as $oria_parent_id => $oria_kids ) : ?>
										<optgroup label="<?php echo esc_attr( (string) get_term_field( 'name', $oria_parent_id, 'area' ) ); ?>">
											<?php foreach ( $oria_kids as $oria_a ) : ?>
												<option value="<?php echo esc_attr( $oria_a->slug ); ?>" <?php selected( $oria_v( 'suburb' ), $oria_a->slug ); ?>><?php echo esc_html( $oria_a->name ); ?></option>
											<?php endforeach; ?>
										</optgroup>
									<?php endforeach; ?>
									<?php foreach ( $oria_loose as $oria_a ) : ?>
										<option value="<?php echo esc_attr( $oria_a->slug ); ?>" <?php selected( $oria_v( 'suburb' ), $oria_a->slug ); ?>><?php echo esc_html( $oria_a->name ); ?></option>
									<?php endforeach; ?>
								</select></label>
						</div>

						<div class="sev-grid">
							<label class="field" for="sev-price"><span class="field__label"><?php esc_html_e( 'Price', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
								<input class="input" id="sev-price" type="text" name="price" value="<?php echo $oria_v( 'price' ); ?>" placeholder="<?php esc_attr_e( '$35, or $25–$40', 'oria' ); ?>">
								<span class="oform-hint"><?php esc_html_e( 'However you publish it — a number, a range, or "by donation".', 'oria' ); ?></span></label>
							<div class="field sev-free">
								<label class="check"><input type="checkbox" name="free" value="1" <?php checked( ! empty( $oria_old['free'] ) ); ?>><span><?php esc_html_e( 'Free or by donation', 'oria' ); ?></span></label>
							</div>
						</div>
					</fieldset>

					<fieldset class="sev-sec">
						<legend class="sev-sec__head">
							<span class="sev-sec__n" aria-hidden="true">2</span>
							<span class="sev-sec__title"><?php esc_html_e( 'What to expect', 'oria' ); ?></span>
						</legend>

						<label class="field" for="sev-description"><span class="field__label"><?php esc_html_e( 'What happens in the session', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
							<span class="oform-hint" id="sev-desc-help"><?php esc_html_e( 'What will people do, who does it suit, and what should they bring? Sixty to a hundred and fifty words is plenty.', 'oria' ); ?></span>
							<textarea class="textarea" id="sev-description" name="description" rows="6" maxlength="2000" aria-describedby="sev-desc-help sev-desc-count"><?php echo esc_textarea( (string) ( $oria_old['description'] ?? '' ) ); ?></textarea>
							<span class="sev-count"><span data-desc-count id="sev-desc-count"></span> <?php esc_html_e( '· no health claims, please — we cannot publish anything saying an event treats or cures a condition.', 'oria' ); ?></span></label>

						<label class="field" for="sev-booking_url"><span class="field__label"><?php esc_html_e( 'Booking or details link', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
							<input class="input" id="sev-booking_url" type="url" name="booking_url" value="<?php echo $oria_v( 'booking_url' ); ?>" placeholder="https://"
								<?php echo $oria_bad( 'booking_url' ) ? 'aria-invalid="true"' : ''; ?>></label>

						<div class="field">
							<span class="field__label"><?php esc_html_e( 'Event image', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
							<div class="sev-drop" data-image-zone>
								<img class="sev-drop__preview" data-image-preview alt="" hidden>
								<label class="sev-drop__pick" for="sev-image">
									<span class="sev-drop__title"><?php esc_html_e( 'Choose an image', 'oria' ); ?></span>
									<span class="sev-drop__meta"><?php esc_html_e( 'JPEG, PNG or WebP · up to 5MB · landscape looks best', 'oria' ); ?></span>
									<input class="sev-drop__input" id="sev-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
								</label>
								<span class="sev-drop__name" data-image-name></span>
								<button class="sev-drop__remove" type="button" data-image-remove hidden><?php esc_html_e( 'Remove image', 'oria' ); ?></button>
								<span class="sev-drop__note" data-image-note role="status"></span>
							</div>
							<span class="oform-hint"><?php esc_html_e( 'An image you own or have permission to publish. Without one we use a plain category tile, which is fine.', 'oria' ); ?></span>
						</div>
					</fieldset>

					<fieldset class="sev-sec">
						<legend class="sev-sec__head">
							<span class="sev-sec__n" aria-hidden="true">3</span>
							<span class="sev-sec__title"><?php esc_html_e( 'About you', 'oria' ); ?></span>
						</legend>

						<div class="sev-grid">
							<label class="field" for="sev-organiser"><span class="field__label"><?php esc_html_e( 'Who is running it', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
								<input class="input" id="sev-organiser" type="text" name="organiser" value="<?php echo $oria_v( 'organiser' ); ?>" placeholder="<?php esc_attr_e( 'Studio, teacher or group name', 'oria' ); ?>"></label>

							<label class="field" for="sev-email"><span class="field__label"><?php esc_html_e( 'Your email', 'oria' ); ?> <?php echo wp_kses_post( $oria_req ); ?></span>
								<input class="input" id="sev-email" type="email" name="email" required value="<?php echo $oria_v( 'email' ); ?>"
									<?php echo $oria_bad( 'email' ) ? 'aria-invalid="true"' : ''; ?>>
								<span class="oform-hint"><?php esc_html_e( 'For checking details with you. Never shown on the site.', 'oria' ); ?></span></label>
						</div>

						<label class="field" for="sev-practice"><span class="field__label"><?php esc_html_e( 'Is your practice already on Oria Haven?', 'oria' ); ?> <span class="sev-opt"><?php esc_html_e( 'optional', 'oria' ); ?></span></span>
							<input class="input" id="sev-practice" type="text" name="practice" value="<?php echo $oria_v( 'practice' ); ?>" placeholder="<?php esc_attr_e( 'Your practice name, as it appears on Oria', 'oria' ); ?>">
							<span class="oform-hint"><?php esc_html_e( 'Enter its exact name and we will connect the event to your profile. Not listed? Leave it blank — the event still goes up.', 'oria' ); ?></span></label>

						<label class="check sev-auth" for="sev-authorised">
							<input type="checkbox" id="sev-authorised" name="authorised" value="1" required <?php echo $oria_bad( 'authorised' ) ? 'aria-invalid="true"' : ''; ?>>
							<span><?php esc_html_e( 'I am authorised to submit this event, and I own or have permission to use the image.', 'oria' ); ?> <?php echo wp_kses_post( $oria_req ); ?></span>
						</label>
					</fieldset>

					<div class="sev-actions">
						<button class="btn btn--dark btn--lg" type="submit" data-submit-button>
							<span data-submit-label><?php esc_html_e( 'Submit event', 'oria' ); ?></span>
						</button>
						<p class="sev-actions__note"><?php esc_html_e( 'Nothing goes live automatically. A person checks every submission.', 'oria' ); ?></p>
					</div>
				</form>
			</div>

			<?php
			/*
			 * What happens next, beside the form on a wide screen and above
			 * it on a narrow one. It answers the three questions people ask
			 * before filling anything in, which is why it is not at the foot
			 * of the page where only the persistent would find it.
			 */
			?>
			<aside class="sev__aside" aria-label="<?php esc_attr_e( 'What happens next', 'oria' ); ?>">
				<div class="sev-next">
					<h2 class="sev-next__title"><?php esc_html_e( 'What happens next', 'oria' ); ?></h2>
					<ol class="sev-next__list">
						<li>
							<b><?php esc_html_e( 'You send the details', 'oria' ); ?></b>
							<span><?php esc_html_e( 'No account, no payment, no catch.', 'oria' ); ?></span>
						</li>
						<li>
							<b><?php esc_html_e( 'A person reads it', 'oria' ); ?></b>
							<span><?php esc_html_e( 'Usually within a couple of days. We may tidy the wording for clarity.', 'oria' ); ?></span>
						</li>
						<li>
							<b><?php esc_html_e( 'It appears in What\'s On', 'oria' ); ?></b>
							<span><?php esc_html_e( 'And on the pages for its suburb and kind of event. We email you if anything needs checking.', 'oria' ); ?></span>
						</li>
					</ol>
					<p class="sev-next__note"><?php esc_html_e( 'Already listed with us? Put your practice name in and the event links to your profile.', 'oria' ); ?></p>
				</div>
			</aside>
		</div>
	<?php endif; ?>
</section>

<?php
get_footer();
