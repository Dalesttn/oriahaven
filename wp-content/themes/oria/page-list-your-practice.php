<?php
/**
 * /list-your-practice/ — self-service signup: the details of a free
 * listing plus an account, submitted in one go. The listing arrives
 * pending (approved within 24 hours); the account works immediately.
 */

declare(strict_types=1);

get_header();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_done   = isset( $_GET['signup'] ) && 'done' === $_GET['signup'];
$oria_errors = array_filter( explode( ',', sanitize_text_field( wp_unslash( (string) ( $_GET['e'] ?? '' ) ) ) ) );
$oria_key    = sanitize_key( (string) ( $_GET['k'] ?? '' ) );
// phpcs:enable
$oria_old = $oria_key ? (array) get_transient( 'oria_signup_' . $oria_key ) : array();
$oria_v   = static fn( string $k ): string => esc_attr( (string) ( $oria_old[ $k ] ?? '' ) );

$oria_error_text = array(
	'expired'        => __( 'The form sat open too long — please submit it again.', 'oria' ),
	'spam'           => __( "That submission looked automated. If you're human, try once more a little slower.", 'oria' ),
	'name'           => __( 'Practice name is required.', 'oria' ),
	'category'       => __( 'Pick the practice category that fits best.', 'oria' ),
	'suburb'         => __( 'Pick your suburb.', 'oria' ),
	'description'    => __( 'Tell people a little more about the practice — a couple of sentences at least.', 'oria' ),
	'public_email'   => __( "The practice's public email doesn't look like an email address.", 'oria' ),
	'price_band'     => __( 'Pick a price band from the list.', 'oria' ),
	'format'         => __( 'Pick a format from the list.', 'oria' ),
	'account_name'   => __( 'Your name is required for the account.', 'oria' ),
	'account_email'  => __( "Your account email doesn't look like an email address.", 'oria' ),
	'account_exists' => __( 'There\'s already an account with that email — log in instead, or use the claim form if your practice is already listed.', 'oria' ),
	'authorised'     => __( 'Please confirm you\'re authorised to manage this practice.', 'oria' ),
	'photo_count'    => __( 'Up to four photos on the free plan.', 'oria' ),
	'photo_size'     => __( 'Each photo must be under 5MB.', 'oria' ),
	'photo_type'     => __( 'Photos must be image files — JPEG, PNG or WebP.', 'oria' ),
	'photo_upload'   => __( 'One of the photos failed to upload — please try again.', 'oria' ),
	'server'         => __( 'Something went wrong on our side — please try again in a minute.', 'oria' ),
);

$oria_practices = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false ) );
$oria_practices = is_wp_error( $oria_practices ) ? array() : $oria_practices;
$oria_regions   = get_terms( array( 'taxonomy' => 'area', 'hide_empty' => false, 'parent' => 0 ) );
$oria_regions   = is_wp_error( $oria_regions ) ? array() : $oria_regions;
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'List your practice', 'oria' ); ?></span>
	</nav>
	<div style="margin-top:1rem;max-width:56rem">
		<span class="micro"><?php esc_html_e( 'Free listing', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title"><?php esc_html_e( 'List your practice', 'oria' ); ?></h1>
		<p class="lede pagehead__lede"><?php esc_html_e( 'Ten minutes now, approved within 24 hours. Your free listing includes your details, description, up to five services and four photos — and you can upgrade for more reach whenever you like.', 'oria' ); ?></p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<?php if ( $oria_done ) : ?>
		<div class="card" style="max-width:44rem"><div class="card__body">
			<h2 class="h3"><?php esc_html_e( "You're registered 🎉", 'oria' ); ?></h2>
			<p style="margin-top:.6rem;color:var(--text-soft)"><?php esc_html_e( "Check your inbox: your account is ready now (set your password from the link in the email), and your listing will be reviewed and approved within 24 hours. Once you're in, you can edit your details any time — or upgrade for the full toolkit.", 'oria' ); ?></p>
		</div></div>
	<?php else : ?>

		<?php if ( $oria_errors ) : ?>
			<div class="card" style="max-width:44rem;border-color:#c98787;margin-bottom:1.5rem"><div class="card__body">
				<b style="color:#9b2c2c"><?php esc_html_e( "That didn't go through:", 'oria' ); ?></b>
				<ul style="margin:.5rem 0 0 1.1rem;color:#9b2c2c;font-size:.9rem">
					<?php foreach ( $oria_errors as $oria_e ) : ?>
						<?php if ( isset( $oria_error_text[ $oria_e ] ) ) : ?><li><?php echo esc_html( $oria_error_text[ $oria_e ] ); ?></li><?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div></div>
		<?php endif; ?>

		<form class="stack" style="gap:1rem;max-width:44rem" method="post" enctype="multipart/form-data"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_signup">
			<input type="hidden" name="oria_ts" value="<?php echo esc_attr( (string) time() ); ?>">
			<?php wp_nonce_field( 'oria_signup', 'oria_signup_nonce' ); ?>
			<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

			<h2 class="h3"><?php esc_html_e( 'The practice', 'oria' ); ?></h2>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Practice name', 'oria' ); ?></span>
				<input class="input" type="text" name="practice_name" required value="<?php echo $oria_v( 'practice_name' ); ?>"></label>

			<div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Category', 'oria' ); ?></span>
					<select class="select" name="practice_cat" required>
						<option value=""><?php esc_html_e( 'Choose…', 'oria' ); ?></option>
						<?php foreach ( $oria_practices as $oria_p ) : ?>
							<option value="<?php echo esc_attr( $oria_p->slug ); ?>" <?php selected( $oria_v( 'practice_cat' ), $oria_p->slug ); ?>><?php echo esc_html( \Oria\Theme\tname( $oria_p ) ); ?></option>
						<?php endforeach; ?>
					</select></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Suburb', 'oria' ); ?></span>
					<select class="select" name="suburb" required>
						<option value=""><?php esc_html_e( 'Choose…', 'oria' ); ?></option>
						<?php foreach ( $oria_regions as $oria_r ) : ?>
							<optgroup label="<?php echo esc_attr( \Oria\Theme\tname( $oria_r ) ); ?>">
								<?php foreach ( get_terms( array( 'taxonomy' => 'area', 'hide_empty' => false, 'parent' => $oria_r->term_id ) ) as $oria_s ) : ?>
									<option value="<?php echo esc_attr( $oria_s->slug ); ?>" <?php selected( $oria_v( 'suburb' ), $oria_s->slug ); ?>><?php echo esc_html( \Oria\Theme\tname( $oria_s ) ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select></label>
			</div>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Street address', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
				<input class="input" type="text" name="address" value="<?php echo $oria_v( 'address' ); ?>"></label>

			<div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Phone', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
					<input class="input" type="tel" name="phone" value="<?php echo $oria_v( 'phone' ); ?>"></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Public email', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
					<input class="input" type="email" name="public_email" value="<?php echo $oria_v( 'public_email' ); ?>"></label>
			</div>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Website', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
				<input class="input" type="url" name="website" placeholder="https://" value="<?php echo $oria_v( 'website' ); ?>"></label>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Description', 'oria' ); ?></span>
				<textarea class="textarea" name="description" required minlength="40" style="min-height:130px"
					placeholder="<?php esc_attr_e( 'What you offer, who it suits, what a first visit looks like. Plain description — no medical claims.', 'oria' ); ?>"><?php echo esc_textarea( (string) ( $oria_old['description'] ?? '' ) ); ?></textarea></label>

			<div class="field">
				<span class="field__label"><?php esc_html_e( 'Services', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'up to five', 'oria' ); ?></span></span>
				<div class="stack" style="gap:.5rem">
					<?php for ( $oria_i = 0; $oria_i < 5; $oria_i++ ) : ?>
						<input class="input" type="text" name="services[]" placeholder="<?php echo esc_attr( array( 'e.g. Guided meditation', 'e.g. Beginner course', '', '', '' )[ $oria_i ] ); ?>"
							value="<?php echo esc_attr( (string) ( $oria_old['services'][ $oria_i ] ?? '' ) ); ?>">
					<?php endfor; ?>
				</div>
				<span class="oform-hint"><?php esc_html_e( 'The free plan lists up to five services. Need more? The Claimed plan removes the limit — you can upgrade from your dashboard after signing up.', 'oria' ); ?></span>
			</div>

			<div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:1rem">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Price from (AUD)', 'oria' ); ?></span>
					<input class="input" type="number" min="0" name="price_from" value="<?php echo $oria_v( 'price_from' ); ?>"></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Price band', 'oria' ); ?></span>
					<select class="select" name="price_band">
						<option value=""><?php esc_html_e( 'Choose…', 'oria' ); ?></option>
						<?php foreach ( array( 'Free' => 'Free / by donation', '$' => '$ — under $25', '$$' => '$$ — $25–60', '$$$' => '$$$ — $60–200', '$$$$' => '$$$$ — $200+' ) as $oria_bv => $oria_bl ) : ?>
							<option value="<?php echo esc_attr( $oria_bv ); ?>" <?php selected( $oria_v( 'price_band' ), $oria_bv ); ?>><?php echo esc_html( $oria_bl ); ?></option>
						<?php endforeach; ?>
					</select></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Format', 'oria' ); ?></span>
					<select class="select" name="format">
						<?php foreach ( array( 'in-person' => __( 'In person', 'oria' ), 'online' => __( 'Online', 'oria' ), 'both' => __( 'Both', 'oria' ) ) as $oria_fv => $oria_fl ) : ?>
							<option value="<?php echo esc_attr( $oria_fv ); ?>" <?php selected( $oria_v( 'format' ) ?: 'in-person', $oria_fv ); ?>><?php echo esc_html( $oria_fl ); ?></option>
						<?php endforeach; ?>
					</select></label>
			</div>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Photos', 'oria' ); ?> <span style="color:var(--text-faint);font-weight:400">· <?php esc_html_e( 'optional, up to four', 'oria' ); ?></span></span>
				<input class="input" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" style="padding:.6rem">
				<span class="oform-hint"><?php esc_html_e( 'Image files only (JPEG, PNG or WebP), under 5MB each. Photos you own the rights to.', 'oria' ); ?></span></label>

			<h2 class="h3" style="margin-top:1rem"><?php esc_html_e( 'Your account', 'oria' ); ?></h2>
			<p class="muted" style="font-size:.875rem;margin-top:-.4rem"><?php esc_html_e( "This is how you'll log in to manage the listing. We'll email you a link to set your password.", 'oria' ); ?></p>

			<div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span>
					<input class="input" type="text" name="account_name" required value="<?php echo $oria_v( 'account_name' ); ?>"></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Your email', 'oria' ); ?></span>
					<input class="input" type="email" name="account_email" required value="<?php echo $oria_v( 'account_email' ); ?>"></label>
			</div>

			<label class="check" style="align-items:flex-start"><input type="checkbox" name="authorised" value="1" required>
				<span style="font-size:.875rem"><?php esc_html_e( "I'm authorised to manage this practice's information.", 'oria' ); ?></span></label>

			<button class="btn btn--dark btn--block" type="submit"><?php esc_html_e( 'Create my free listing', 'oria' ); ?></button>
			<p class="muted" style="font-size:.8125rem"><?php esc_html_e( 'Free forever for the basics. Reviewed by a human before it goes live — usually well inside 24 hours.', 'oria' ); ?></p>
		</form>
	<?php endif; ?>
</section>

<?php
get_footer();
