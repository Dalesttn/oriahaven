<?php
/**
 * My Oria -> Work -> Edit profile.
 *
 * One form in plain sections (brief sections 10-12, 28). Every field is
 * labelled; nothing depends on a placeholder. The private section says,
 * next to the fields, that it is never shown publicly.
 *
 * ?for={job|shift id} returns the person to what they were answering.
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_uid    = get_current_user_id();
$oria_pro    = Work\profile_of( $oria_uid );
$oria_user   = wp_get_current_user();
$oria_for    = (int) ( $_GET['for'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_notice = Work\notice();
$oria_m      = static fn( string $k, $d = '' ) => $oria_pro ? Work\meta( $oria_pro, $k, $d ) : $d;
$oria_terms  = static fn( string $tax ) => $oria_pro ? wp_get_post_terms( $oria_pro, $tax, array( 'fields' => 'ids' ) ) : array();
$oria_profs  = $oria_terms( Work\PROFESSION );
$oria_skills = $oria_terms( Work\SKILL );
$oria_prim   = (int) $oria_m( 'primary', 0 ) ?: (int) ( $oria_profs[0] ?? 0 );
$oria_sub    = $oria_pro ? Work\suburb( $oria_pro ) : null;
$oria_checks = static function ( string $name, array $options, array $on ): void {
	foreach ( $options as $k => $v ) {
		printf( '<label class="wkcheck"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>', esc_attr( $name ), esc_attr( (string) $k ), checked( in_array( (string) $k, array_map( 'strval', $on ), true ), true, false ), esc_html( (string) $v ) );
	}
};
?>
<section class="wrap my my--last wkmy">
	<?php if ( $oria_notice ) : ?>
		<div class="notice notice--<?php echo 'ok' === $oria_notice['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
	<?php endif; ?>
	<div class="my__hello">
		<a class="wkback" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work' ) ); ?>">&larr; <?php esc_html_e( 'Work', 'oria' ); ?></a>
		<h1 class="h1"><?php echo esc_html( $oria_pro ? __( 'Edit your work profile', 'oria' ) : __( 'Create your practitioner profile', 'oria' ) ); ?></h1>
		<p class="lede"><?php esc_html_e( 'Businesses decide on this when you apply or say you are available. The more you fill in, the more you match.', 'oria' ); ?></p>
	</div>

	<form class="wkform wkform--profile" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php echo Work\form_fields( 'oria_work_profile' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<input type="hidden" name="for" value="<?php echo (int) $oria_for; ?>">

		<fieldset class="wkpanel">
			<legend class="h3"><?php esc_html_e( 'About you', 'oria' ); ?></legend>
			<label for="wp-name"><?php esc_html_e( 'Your name', 'oria' ); ?></label>
			<input class="input" id="wp-name" name="name" required maxlength="80" value="<?php echo esc_attr( $oria_pro ? get_the_title( $oria_pro ) : trim( $oria_user->first_name . ' ' . $oria_user->last_name ) ); ?>" autocomplete="name">
			<label for="wp-title"><?php esc_html_e( 'Professional title', 'oria' ); ?></label>
			<input class="input" id="wp-title" name="title" maxlength="80" value="<?php echo esc_attr( (string) $oria_m( 'title' ) ); ?>">
			<p class="hint"><?php esc_html_e( 'For example "Reformer Pilates Instructor" or "Remedial Massage Therapist".', 'oria' ); ?></p>
			<label for="wp-photo"><?php esc_html_e( 'Profile photo', 'oria' ); ?></label>
			<?php if ( $oria_pro && has_post_thumbnail( $oria_pro ) ) : ?>
				<div class="wkphoto"><?php echo get_the_post_thumbnail( $oria_pro, 'thumbnail', array( 'alt' => '' ) ); ?><span class="hint"><?php esc_html_e( 'Choose a new file to replace it.', 'oria' ); ?></span></div>
			<?php endif; ?>
			<input id="wp-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
			<label for="wp-short"><?php esc_html_e( 'Short bio', 'oria' ); ?></label>
			<textarea class="input" id="wp-short" name="short_bio" rows="2" maxlength="300"><?php echo esc_textarea( $oria_pro ? (string) get_post_field( 'post_excerpt', $oria_pro ) : '' ); ?></textarea>
			<p class="hint"><?php esc_html_e( 'One or two sentences. It leads your profile and your cards.', 'oria' ); ?></p>
			<label for="wp-bio"><?php esc_html_e( 'Full bio', 'oria' ); ?></label>
			<textarea class="input" id="wp-bio" name="bio" rows="7" maxlength="5000"><?php echo esc_textarea( $oria_pro ? wp_strip_all_tags( (string) get_post_field( 'post_content', $oria_pro ) ) : '' ); ?></textarea>
			<p class="hint"><?php esc_html_e( 'How you teach or work, who you work with, what a session with you is like. Describe what you do, not what it cures.', 'oria' ); ?></p>
		</fieldset>

		<fieldset class="wkpanel">
			<legend class="h3"><?php esc_html_e( 'What you do', 'oria' ); ?></legend>
			<label for="wp-prof"><?php esc_html_e( 'Main profession', 'oria' ); ?></label>
			<select class="input" id="wp-prof" name="profession" required>
				<option value=""><?php esc_html_e( 'Choose one', 'oria' ); ?></option>
				<?php foreach ( Work\profession_tree() as $oria_g => $oria_ts ) : ?>
					<optgroup label="<?php echo esc_attr( $oria_g ); ?>">
						<?php foreach ( $oria_ts as $oria_t ) : ?>
							<option value="<?php echo (int) $oria_t->term_id; ?>" <?php selected( $oria_prim, (int) $oria_t->term_id ); ?>><?php echo esc_html( $oria_t->name ); ?></option>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>
			<details class="wkmore"<?php echo count( $oria_profs ) > 1 ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Other professions you work in', 'oria' ); ?></summary>
				<?php foreach ( Work\profession_tree() as $oria_g => $oria_ts ) : ?>
					<p class="wkgroup"><?php echo esc_html( $oria_g ); ?></p>
					<div class="wkchecks"><?php $oria_checks( 'professions', wp_list_pluck( $oria_ts, 'name', 'term_id' ), $oria_profs ); ?></div>
				<?php endforeach; ?>
			</details>
			<p class="wklabel"><?php esc_html_e( 'Skills', 'oria' ); ?></p>
			<?php foreach ( Work\skill_tree() as $oria_g => $oria_ts ) : ?>
				<p class="wkgroup"><?php echo esc_html( $oria_g ); ?></p>
				<div class="wkchecks"><?php $oria_checks( 'skills', wp_list_pluck( $oria_ts, 'name', 'term_id' ), $oria_skills ); ?></div>
			<?php endforeach; ?>
			<div class="wkform__row">
				<div>
					<label for="wp-years"><?php esc_html_e( 'Years of experience', 'oria' ); ?></label>
					<input class="input" id="wp-years" name="years" type="number" min="0" max="60" inputmode="numeric" value="<?php echo esc_attr( (string) $oria_m( 'years' ) ); ?>">
				</div>
				<div>
					<label for="wp-rate"><?php esc_html_e( 'Your usual rate', 'oria' ); ?></label>
					<input class="input" id="wp-rate" name="rate" maxlength="60" value="<?php echo esc_attr( (string) $oria_m( 'rate' ) ); ?>">
					<p class="hint"><?php esc_html_e( 'For example "$70 per class" or "$45/hour".', 'oria' ); ?></p>
				</div>
			</div>
			<label for="wp-quals"><?php esc_html_e( 'Qualifications and certifications', 'oria' ); ?></label>
			<textarea class="input" id="wp-quals" name="quals" rows="3" maxlength="1500"><?php echo esc_textarea( (string) $oria_m( 'quals' ) ); ?></textarea>
			<p class="hint"><?php esc_html_e( 'One per line, e.g. "200hr Yoga Alliance". Leave out certificate numbers.', 'oria' ); ?></p>
			<label for="wp-services"><?php esc_html_e( 'Services you offer', 'oria' ); ?></label>
			<textarea class="input" id="wp-services" name="services" rows="2" maxlength="1000"><?php echo esc_textarea( (string) $oria_m( 'services' ) ); ?></textarea>
		</fieldset>

		<fieldset class="wkpanel">
			<legend class="h3"><?php esc_html_e( 'Where and when', 'oria' ); ?></legend>
			<div class="wkform__row">
				<div>
					<label for="wp-suburb"><?php esc_html_e( 'Your suburb', 'oria' ); ?></label>
					<select class="input" id="wp-suburb" name="suburb">
						<option value=""><?php esc_html_e( 'Choose your suburb', 'oria' ); ?></option>
						<?php foreach ( Work\suburb_choices() as $oria_city => $oria_subs ) : ?>
							<optgroup label="<?php echo esc_attr( $oria_city ); ?>">
								<?php foreach ( $oria_subs as $oria_slug => $oria_name ) : ?>
									<option value="<?php echo esc_attr( $oria_slug ); ?>" <?php selected( $oria_sub ? $oria_sub->slug : '', $oria_slug ); ?>><?php echo esc_html( $oria_name ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
					<p class="hint"><?php esc_html_e( 'Only your suburb is shown — never your address.', 'oria' ); ?></p>
				</div>
				<div>
					<label for="wp-radius"><?php esc_html_e( 'How far will you travel? (km)', 'oria' ); ?></label>
					<input class="input" id="wp-radius" name="radius" type="number" min="1" max="200" inputmode="numeric" value="<?php echo esc_attr( (string) $oria_m( 'radius', 15 ) ); ?>">
				</div>
			</div>
			<p class="wklabel"><?php esc_html_e( 'Available for', 'oria' ); ?></p>
			<div class="wkchecks"><?php $oria_checks( 'available_for', Work\AVAILABLE_FOR, (array) $oria_m( 'available_for', array() ) ); ?></div>
			<p class="wklabel"><?php esc_html_e( 'When you are free', 'oria' ); ?></p>
			<div class="wkchecks"><?php $oria_checks( 'availability', Work\AVAILABILITY, (array) $oria_m( 'availability', array() ) ); ?></div>
			<p class="wklabel"><?php esc_html_e( 'Kinds of work you want', 'oria' ); ?></p>
			<div class="wkchecks"><?php $oria_checks( 'employment_pref', Work\employment_types(), (array) $oria_m( 'employment_pref', array() ) ); ?></div>
			<label class="wkcheck wkcheck--big"><input type="checkbox" name="urgent_alerts" value="1" <?php checked( (bool) $oria_m( 'urgent_alerts' ) ); ?>> <span><strong><?php esc_html_e( 'Urgent shift alerts', 'oria' ); ?></strong> <?php esc_html_e( 'Email me when a business near me needs cover within 48 hours and I suit the shift.', 'oria' ); ?></span></label>
		</fieldset>

		<fieldset class="wkpanel">
			<legend class="h3"><?php esc_html_e( 'Your CV and links', 'oria' ); ?></legend>
			<label for="wp-cv"><?php esc_html_e( 'CV (PDF or Word, under 5 MB)', 'oria' ); ?></label>
			<?php if ( $oria_pro && Work\meta( $oria_pro, 'cv', 0 ) ) : ?>
				<p class="hint"><?php esc_html_e( 'You have a CV on file. Choose a new file to replace it.', 'oria' ); ?></p>
			<?php endif; ?>
			<input id="wp-cv" type="file" name="cv" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
			<p class="hint"><?php esc_html_e( 'Your CV is private. Only a business you apply to can open it.', 'oria' ); ?></p>
			<div class="wkform__row">
				<div><label for="wp-web"><?php esc_html_e( 'Website', 'oria' ); ?></label><input class="input" id="wp-web" name="website" type="url" value="<?php echo esc_attr( (string) $oria_m( 'website' ) ); ?>"></div>
				<div><label for="wp-ig"><?php esc_html_e( 'Instagram', 'oria' ); ?></label><input class="input" id="wp-ig" name="instagram" type="url" value="<?php echo esc_attr( (string) $oria_m( 'instagram' ) ); ?>"></div>
				<div><label for="wp-li"><?php esc_html_e( 'LinkedIn', 'oria' ); ?></label><input class="input" id="wp-li" name="linkedin" type="url" value="<?php echo esc_attr( (string) $oria_m( 'linkedin' ) ); ?>"></div>
			</div>
		</fieldset>

		<fieldset class="wkpanel wkpanel--private">
			<legend class="h3"><?php esc_html_e( 'Credentials (private)', 'oria' ); ?></legend>
			<p class="hint"><?php esc_html_e( 'Never shown on your profile. We use these to check and add verified badges — only the badge is ever shown, never a number or document.', 'oria' ); ?></p>
			<div class="wkchecks">
				<label class="wkcheck"><input type="checkbox" name="insurance" value="1" <?php checked( (bool) $oria_m( 'insurance' ) ); ?>> <?php esc_html_e( 'I hold professional insurance', 'oria' ); ?></label>
				<label class="wkcheck"><input type="checkbox" name="wwcc" value="1" <?php checked( (bool) $oria_m( 'wwcc' ) ); ?>> <?php esc_html_e( 'Working With Children Check', 'oria' ); ?></label>
				<label class="wkcheck"><input type="checkbox" name="first_aid" value="1" <?php checked( (bool) $oria_m( 'first_aid' ) ); ?>> <?php esc_html_e( 'First Aid certificate', 'oria' ); ?></label>
				<label class="wkcheck"><input type="checkbox" name="cpr" value="1" <?php checked( (bool) $oria_m( 'cpr' ) ); ?>> <?php esc_html_e( 'CPR certificate', 'oria' ); ?></label>
			</div>
			<div class="wkform__row">
				<div><label for="wp-abn"><?php esc_html_e( 'ABN (optional)', 'oria' ); ?></label><input class="input" id="wp-abn" name="abn" inputmode="numeric" maxlength="20" value="<?php echo esc_attr( (string) $oria_m( 'abn' ) ); ?>"></div>
				<div><label for="wp-reg"><?php esc_html_e( 'Professional registrations', 'oria' ); ?></label><input class="input" id="wp-reg" name="registrations" maxlength="300" value="<?php echo esc_attr( (string) $oria_m( 'registrations' ) ); ?>"></div>
			</div>
		</fieldset>

		<fieldset class="wkpanel">
			<legend class="h3"><?php esc_html_e( 'Privacy', 'oria' ); ?></legend>
			<p class="wklabel"><?php esc_html_e( 'Who can see your profile', 'oria' ); ?></p>
			<?php foreach ( array( 'public' => __( 'Public — anyone, and search engines once it is complete', 'oria' ), 'employers' => __( 'Oria employers only', 'oria' ), 'hidden' => __( 'Hidden — only used when I apply', 'oria' ) ) as $oria_k => $oria_v ) : ?>
				<label class="wkradio"><input type="radio" name="visibility" value="<?php echo esc_attr( $oria_k ); ?>" <?php checked( Work\visibility( (int) $oria_pro ) === $oria_k || ( ! $oria_pro && 'public' === $oria_k ) ); ?>> <?php echo esc_html( $oria_v ); ?></label>
			<?php endforeach; ?>
			<p class="wklabel"><?php esc_html_e( 'Contact', 'oria' ); ?></p>
			<?php $oria_cp = (string) $oria_m( 'contact_pref', 'allow' ); ?>
			<?php foreach ( array( 'allow' => __( 'Businesses may invite me to jobs and shifts', 'oria' ), 'applications' => __( 'Only through my applications', 'oria' ), 'none' => __( 'No unsolicited contact', 'oria' ) ) as $oria_k => $oria_v ) : ?>
				<label class="wkradio"><input type="radio" name="contact_pref" value="<?php echo esc_attr( $oria_k ); ?>" <?php checked( $oria_cp, $oria_k ); ?>> <?php echo esc_html( $oria_v ); ?></label>
			<?php endforeach; ?>
		</fieldset>

		<div class="wkform__submit">
			<button class="btn btn--dark" type="submit"><?php echo esc_html( $oria_pro ? __( 'Save profile', 'oria' ) : __( 'Create profile', 'oria' ) ); ?></button>
		</div>
	</form>
</section>
