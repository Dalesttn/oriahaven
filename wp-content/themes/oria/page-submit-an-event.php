<?php
/**
 * /submit-an-event/ — the free, public route for an organiser to put a
 * Perth wellness event on the site.
 *
 * Free, and no account needed: the point is to hear about the Tuesday sound
 * bath, not to sell anything at the door. Submissions arrive as drafts and a
 * person checks them. Handled by \Oria\Core\EventSubmit.
 */

declare(strict_types=1);

get_header();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_sent   = isset( $_GET['sent'] );
$oria_errors = \Oria\Core\EventSubmit\messages( sanitize_text_field( wp_unslash( (string) ( $_GET['e'] ?? '' ) ) ) );
// phpcs:enable
$oria_old = \Oria\Core\EventSubmit\stashed();
$oria_v   = static fn( string $k ): string => esc_attr( (string) ( $oria_old[ $k ] ?? '' ) );

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
		<p style="margin-top:.9rem;max-width:58ch;color:var(--text-soft)"><?php esc_html_e( 'A person reads every submission, usually within a couple of days. We may tidy the wording, and we will email you if anything needs checking. If your practice is already listed with us, the event links to your profile.', 'oria' ); ?></p>
	</div>
</section>

<section class="wrap" style="padding-bottom:4rem" id="submit">
	<?php if ( $oria_sent ) : ?>
		<div class="card" style="max-width:44rem"><div class="card__body">
			<h2 class="h3"><?php esc_html_e( 'Got it — thank you', 'oria' ); ?></h2>
			<p style="margin-top:.6rem;color:var(--text-soft)"><?php esc_html_e( 'Your event is with us and a person will check it within a couple of days. We have emailed you a confirmation. If the details change before it goes up, reply to that email and tell us.', 'oria' ); ?></p>
			<p style="margin-top:1rem">
				<a class="btn btn--dark" href="<?php echo esc_url( home_url( '/whats-on-perth/' ) ); ?>"><?php esc_html_e( "See what's on", 'oria' ); ?></a>
				<a class="btn" href="<?php echo esc_url( home_url( '/submit-an-event/' ) ); ?>"><?php esc_html_e( 'Submit another', 'oria' ); ?></a>
			</p>
		</div></div>
	<?php else : ?>

		<?php if ( $oria_errors ) : ?>
			<div class="card" style="max-width:44rem;border-color:#c98787;margin-bottom:1.5rem"><div class="card__body">
				<b style="color:#9b2c2c"><?php esc_html_e( "That didn't go through:", 'oria' ); ?></b>
				<ul style="margin:.5rem 0 0 1.1rem;color:#9b2c2c;font-size:.9rem">
					<?php foreach ( $oria_errors as $oria_msg ) : ?>
						<li><?php echo esc_html( $oria_msg ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div></div>
		<?php endif; ?>

		<form class="stack" style="gap:1rem;max-width:44rem" method="post" enctype="multipart/form-data"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_event_submit">
			<input type="hidden" name="oria_ts" value="<?php echo esc_attr( (string) time() ); ?>">
			<?php wp_nonce_field( 'oria_event_submit', 'oria_event_nonce' ); ?>
			<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

			<h2 class="h3"><?php esc_html_e( 'The event', 'oria' ); ?></h2>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Event name', 'oria' ); ?></span>
				<input class="input" type="text" name="title" required value="<?php echo $oria_v( 'title' ); ?>"></label>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Kind of event', 'oria' ); ?></span>
				<select class="select" name="type" required>
					<option value=""><?php esc_html_e( 'Choose…', 'oria' ); ?></option>
					<?php foreach ( $oria_types as $oria_t ) : ?>
						<option value="<?php echo esc_attr( $oria_t->slug ); ?>" <?php selected( $oria_v( 'type' ), $oria_t->slug ); ?>><?php echo esc_html( $oria_t->name ); ?></option>
					<?php endforeach; ?>
				</select></label>

			<div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Starts', 'oria' ); ?></span>
					<input class="input" type="date" name="start_date" required value="<?php echo $oria_v( 'start_date' ); ?>"></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Start time', 'oria' ); ?></span>
					<input class="input" type="time" name="start_time" value="<?php echo $oria_v( 'start_time' ); ?>"></label>
			</div>

			<div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Ends', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
					<input class="input" type="date" name="end_date" value="<?php echo $oria_v( 'end_date' ); ?>"></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Finish time', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
					<input class="input" type="time" name="end_time" value="<?php echo $oria_v( 'end_time' ); ?>"></label>
			</div>

			<div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Venue', 'oria' ); ?></span>
					<input class="input" type="text" name="venue" value="<?php echo $oria_v( 'venue' ); ?>" placeholder="<?php esc_attr_e( 'The hall, studio or park', 'oria' ); ?>"></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Suburb', 'oria' ); ?></span>
					<select class="select" name="suburb">
						<option value=""><?php esc_html_e( 'Choose…', 'oria' ); ?></option>
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

			<div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Price', 'oria' ); ?></span>
					<input class="input" type="text" name="price" value="<?php echo $oria_v( 'price' ); ?>" placeholder="<?php esc_attr_e( '$35, or $25–$40', 'oria' ); ?>"></label>
				<label class="field" style="justify-content:flex-end">
					<label class="check" style="align-items:flex-start"><input type="checkbox" name="free" value="1" <?php checked( ! empty( $oria_old['free'] ) ); ?>><span style="font-size:.875rem"><?php esc_html_e( 'Free or by donation', 'oria' ); ?></span></label>
				</label>
			</div>

			<label class="field"><span class="field__label"><?php esc_html_e( 'What happens in the session', 'oria' ); ?></span>
				<textarea class="textarea" name="description" style="min-height:130px" placeholder="<?php esc_attr_e( 'A few sentences: what people will do, who it suits, what to bring.', 'oria' ); ?>"><?php echo esc_textarea( (string) ( $oria_old['description'] ?? '' ) ); ?></textarea>
				<span class="oform-hint"><?php esc_html_e( 'Plain description, no health claims — we cannot publish anything that says an event treats or cures a condition.', 'oria' ); ?></span></label>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Booking or details link', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
				<input class="input" type="url" name="booking_url" value="<?php echo $oria_v( 'booking_url' ); ?>" placeholder="https://"></label>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Event image', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
				<input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp" style="padding:.6rem">
				<span class="oform-hint"><?php esc_html_e( 'JPEG, PNG or WebP under 5MB, and an image you own the rights to. Without one we use a plain category tile.', 'oria' ); ?></span></label>

			<h2 class="h3" style="margin-top:1rem"><?php esc_html_e( 'You', 'oria' ); ?></h2>

			<div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Who is running it', 'oria' ); ?></span>
					<input class="input" type="text" name="organiser" value="<?php echo $oria_v( 'organiser' ); ?>" placeholder="<?php esc_attr_e( 'Studio, teacher or group name', 'oria' ); ?>"></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Your email', 'oria' ); ?></span>
					<input class="input" type="email" name="email" required value="<?php echo $oria_v( 'email' ); ?>">
					<span class="oform-hint"><?php esc_html_e( 'For checking details with you. Never shown on the site.', 'oria' ); ?></span></label>
			</div>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Already listed on Oria Haven?', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
				<input class="input" type="text" name="practice" value="<?php echo $oria_v( 'practice' ); ?>" placeholder="<?php esc_attr_e( 'Your practice name, as it appears on Oria', 'oria' ); ?>">
				<span class="oform-hint"><?php esc_html_e( 'We will connect the event to your profile. Leave it blank if you are not listed — the event still goes up.', 'oria' ); ?></span></label>

			<label class="check" style="align-items:flex-start"><input type="checkbox" name="authorised" value="1" required><span style="font-size:.875rem"><?php esc_html_e( 'I am authorised to submit this event, and I own or have permission to use the image.', 'oria' ); ?></span></label>

			<button class="btn btn--dark btn--block" type="submit"><?php esc_html_e( 'Submit event', 'oria' ); ?></button>
			<p style="font-size:.8125rem;color:var(--text-faint)"><?php esc_html_e( 'Nothing goes live automatically. A person checks every submission.', 'oria' ); ?></p>
		</form>
	<?php endif; ?>
</section>

<?php
get_footer();
