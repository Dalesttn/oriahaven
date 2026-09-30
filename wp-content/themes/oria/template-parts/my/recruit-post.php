<?php
/**
 * My Oria -> Recruitment -> Post a job / Post a shift (brief section 16).
 *
 * One form of fieldsets. With JavaScript (work.js) each fieldset is a step
 * with Back / Next and a progress line, and the last step is a preview;
 * without it, the same form is one page and still submits. "Save draft" is
 * on every step and skips validation.
 *
 * ?type=job|shift  ?id= to edit  ?cover=1 pre-sets an urgent shift for today.
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_uid   = get_current_user_id();
$oria_kind  = 'shift' === ( $_GET['type'] ?? '' ) ? 'shift' : 'job'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_id    = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_cover = ! empty( $_GET['cover'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_type  = 'shift' === $oria_kind ? Work\SHIFT : Work\JOB;
if ( $oria_id && ( get_post_type( $oria_id ) !== $oria_type || ! Work\owns( $oria_id, $oria_uid ) ) ) {
	$oria_id = 0;
}
$oria_m       = static fn( string $k, $d = '' ) => $oria_id ? Work\meta( $oria_id, $k, $d ) : $d;
$oria_prof    = $oria_id ? Work\term( $oria_id, Work\PROFESSION ) : null;
$oria_emp     = $oria_id ? Work\term( $oria_id, Work\EMPLOYMENT ) : null;
$oria_sub     = $oria_id ? Work\suburb( $oria_id ) : null;
$oria_skills  = $oria_id ? wp_get_post_terms( $oria_id, Work\SKILL, array( 'fields' => 'ids' ) ) : array();
$oria_listing = function_exists( '\Oria\Core\ListingEditor\listing_for' ) ? (int) \Oria\Core\ListingEditor\listing_for( $oria_uid ) : 0;
$oria_notice  = Work\notice();
$oria_trusted = Work\Forms\trusted( $oria_uid );
$oria_body    = $oria_id ? wp_strip_all_tags( (string) get_post_field( 'post_content', $oria_id ) ) : '';

$oria_prof_select = static function () use ( $oria_prof ): void {
	echo '<option value="">' . esc_html__( 'Choose a profession', 'oria' ) . '</option>';
	foreach ( Work\profession_tree() as $g => $ts ) {
		echo '<optgroup label="' . esc_attr( $g ) . '">';
		foreach ( $ts as $t ) {
			printf( '<option value="%d" %s>%s</option>', (int) $t->term_id, selected( $oria_prof ? (int) $oria_prof->term_id : 0, (int) $t->term_id, false ), esc_html( $t->name ) );
		}
		echo '</optgroup>';
	}
};
$oria_suburb_select = static function () use ( $oria_sub ): void {
	echo '<option value="">' . esc_html__( 'Choose a suburb', 'oria' ) . '</option>';
	foreach ( Work\suburb_choices() as $city => $subs ) {
		echo '<optgroup label="' . esc_attr( $city ) . '">';
		foreach ( $subs as $slug => $name ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $slug ), selected( $oria_sub ? $oria_sub->slug : '', $slug, false ), esc_html( $name ) );
		}
		echo '</optgroup>';
	}
};
?>
<section class="wrap my my--last wkmy">
	<?php if ( $oria_notice ) : ?>
		<div class="notice notice--<?php echo 'ok' === $oria_notice['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
	<?php endif; ?>
	<div class="my__hello">
		<a class="wkback" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'recruit' ) ); ?>">&larr; <?php esc_html_e( 'Recruitment', 'oria' ); ?></a>
		<h1 class="h1">
			<?php
			if ( $oria_id ) {
				esc_html_e( 'Edit', 'oria' );
			} elseif ( $oria_cover ) {
				esc_html_e( 'Find emergency cover', 'oria' );
			} else {
				echo esc_html( 'shift' === $oria_kind ? __( 'Post a shift', 'oria' ) : __( 'Post a job', 'oria' ) );
			}
			?>
		</h1>
		<p class="lede"><?php echo esc_html( 'shift' === $oria_kind ? __( 'Tell us the role, when, where and the rate. Practitioners who suit it are alerted when it is urgent.', 'oria' ) : __( 'Free during launch. Jobs that show the pay get noticeably more applicants.', 'oria' ) ); ?></p>
		<?php if ( ! $oria_trusted && ! $oria_id ) : ?>
			<p class="hint"><?php esc_html_e( 'Your first listing is checked by a person at Oria Haven before it goes live, usually within a business day. After that, your listings go live straight away.', 'oria' ); ?></p>
		<?php endif; ?>
	</div>

	<form class="wkform wkwizard" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-wk-wizard data-wk-submit="<?php echo 'shift' === $oria_kind ? 'shift_post_completed' : 'job_post_completed'; ?>">
		<?php echo Work\form_fields( 'oria_work_post' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<input type="hidden" name="kind" value="<?php echo esc_attr( $oria_kind ); ?>">
		<input type="hidden" name="id" value="<?php echo (int) $oria_id; ?>">
		<ol class="wkwizard__progress" aria-hidden="true"></ol>

		<fieldset class="wkstep wkpanel" data-step="<?php esc_attr_e( 'Role', 'oria' ); ?>">
			<legend class="h3"><?php esc_html_e( 'The role', 'oria' ); ?></legend>
			<label for="rp-title"><?php echo esc_html( 'shift' === $oria_kind ? __( 'Shift title', 'oria' ) : __( 'Job title', 'oria' ) ); ?></label>
			<input class="input" id="rp-title" name="title" required maxlength="120" value="<?php echo esc_attr( $oria_id ? get_the_title( $oria_id ) : '' ); ?>">
			<p class="hint"><?php echo esc_html( 'shift' === $oria_kind ? __( 'For example "Reformer Pilates cover — Saturday 8am".', 'oria' ) : __( 'For example "Casual Reformer Pilates Instructor".', 'oria' ) ); ?></p>
			<label for="rp-prof"><?php esc_html_e( 'Profession', 'oria' ); ?></label>
			<select class="input" id="rp-prof" name="profession" required><?php $oria_prof_select(); ?></select>
			<?php if ( ! $oria_listing ) : ?>
				<label for="rp-emp"><?php esc_html_e( 'Business name', 'oria' ); ?></label>
				<input class="input" id="rp-emp" name="employer" required maxlength="120" value="<?php echo esc_attr( (string) $oria_m( 'employer' ) ); ?>" autocomplete="organization">
				<p class="hint"><?php esc_html_e( 'Is your business listed on Oria Haven? Claim it and your jobs link to your profile.', 'oria' ); ?></p>
			<?php else : ?>
				<p class="hint"><?php echo esc_html( sprintf( __( 'Posting as %s.', 'oria' ), html_entity_decode( get_the_title( $oria_listing ), ENT_QUOTES, 'UTF-8' ) ) ); ?></p>
			<?php endif; ?>
		</fieldset>

		<?php if ( 'shift' === $oria_kind ) : ?>
			<fieldset class="wkstep wkpanel" data-step="<?php esc_attr_e( 'When', 'oria' ); ?>">
				<legend class="h3"><?php esc_html_e( 'When', 'oria' ); ?></legend>
				<div class="wkform__row">
					<div><label for="rp-date"><?php esc_html_e( 'Date', 'oria' ); ?></label><input class="input" id="rp-date" type="date" name="date" required min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( (string) $oria_m( 'date', $oria_cover ? wp_date( 'Y-m-d' ) : '' ) ); ?>"></div>
					<div><label for="rp-start"><?php esc_html_e( 'Start', 'oria' ); ?></label><input class="input" id="rp-start" type="time" name="start" required value="<?php echo esc_attr( (string) $oria_m( 'start' ) ); ?>"></div>
					<div><label for="rp-end"><?php esc_html_e( 'Finish', 'oria' ); ?></label><input class="input" id="rp-end" type="time" name="end" value="<?php echo esc_attr( (string) $oria_m( 'end' ) ); ?>"></div>
				</div>
				<p class="wklabel"><?php esc_html_e( 'How urgent', 'oria' ); ?></p>
				<?php foreach ( Work\URGENCY as $oria_k => $oria_v ) : ?>
					<label class="wkradio"><input type="radio" name="urgency" value="<?php echo esc_attr( $oria_k ); ?>" <?php checked( (string) $oria_m( 'urgency', $oria_cover ? 'today' : 'normal' ), $oria_k ); ?>> <?php echo esc_html( $oria_v ); ?></label>
				<?php endforeach; ?>
				<p class="hint"><?php esc_html_e( 'Within 48 hours or today: we email the practitioners nearby who suit it and have urgent alerts switched on.', 'oria' ); ?></p>
			</fieldset>
		<?php endif; ?>

		<fieldset class="wkstep wkpanel" data-step="<?php esc_attr_e( 'Location', 'oria' ); ?>">
			<legend class="h3"><?php esc_html_e( 'Location', 'oria' ); ?></legend>
			<label for="rp-sub"><?php esc_html_e( 'Suburb', 'oria' ); ?></label>
			<select class="input" id="rp-sub" name="suburb" required><?php $oria_suburb_select(); ?></select>
			<label for="rp-addr"><?php esc_html_e( 'Street address (optional)', 'oria' ); ?></label>
			<input class="input" id="rp-addr" name="address" maxlength="160" value="<?php echo esc_attr( (string) $oria_m( 'address' ) ); ?>" autocomplete="street-address">
			<?php if ( 'job' === $oria_kind ) : ?>
				<p class="wklabel"><?php esc_html_e( 'Work arrangement', 'oria' ); ?></p>
				<?php foreach ( Work\ARRANGEMENTS as $oria_k => $oria_v ) : ?>
					<label class="wkradio"><input type="radio" name="arrangement" value="<?php echo esc_attr( $oria_k ); ?>" <?php checked( (string) $oria_m( 'arrangement', 'onsite' ), $oria_k ); ?>> <?php echo esc_html( $oria_v ); ?></label>
				<?php endforeach; ?>
			<?php endif; ?>
		</fieldset>

		<?php if ( 'job' === $oria_kind ) : ?>
			<fieldset class="wkstep wkpanel" data-step="<?php esc_attr_e( 'Employment', 'oria' ); ?>">
				<legend class="h3"><?php esc_html_e( 'Employment details', 'oria' ); ?></legend>
				<label for="rp-type"><?php esc_html_e( 'Employment type', 'oria' ); ?></label>
				<select class="input" id="rp-type" name="employment" required>
					<option value=""><?php esc_html_e( 'Choose one', 'oria' ); ?></option>
					<?php foreach ( Work\employment_types() as $oria_k => $oria_v ) : ?>
						<option value="<?php echo esc_attr( $oria_k ); ?>" <?php selected( $oria_emp ? $oria_emp->slug : '', $oria_k ); ?>><?php echo esc_html( $oria_v ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="rp-exp"><?php esc_html_e( 'Experience', 'oria' ); ?></label>
				<select class="input" id="rp-exp" name="experience">
					<?php foreach ( Work\EXPERIENCE as $oria_k => $oria_v ) : ?>
						<option value="<?php echo esc_attr( $oria_k ); ?>" <?php selected( (string) $oria_m( 'experience', 'any' ), $oria_k ); ?>><?php echo esc_html( $oria_v ); ?></option>
					<?php endforeach; ?>
				</select>
				<div class="wkchecks">
					<label class="wkcheck"><input type="checkbox" name="weekend" value="1" <?php checked( (bool) $oria_m( 'weekend' ) ); ?>> <?php esc_html_e( 'Includes weekend work', 'oria' ); ?></label>
					<label class="wkcheck"><input type="checkbox" name="evening" value="1" <?php checked( (bool) $oria_m( 'evening' ) ); ?>> <?php esc_html_e( 'Includes evening work', 'oria' ); ?></label>
				</div>
			</fieldset>
		<?php endif; ?>

		<fieldset class="wkstep wkpanel" data-step="<?php esc_attr_e( 'Description', 'oria' ); ?>">
			<legend class="h3"><?php esc_html_e( 'Description', 'oria' ); ?></legend>
			<?php if ( 'job' === $oria_kind ) : ?>
				<label for="rp-sum"><?php esc_html_e( 'One-line summary', 'oria' ); ?></label>
				<input class="input" id="rp-sum" name="summary" maxlength="300" value="<?php echo esc_attr( $oria_id ? (string) get_post_field( 'post_excerpt', $oria_id ) : '' ); ?>">
			<?php endif; ?>
			<label for="rp-desc"><?php echo esc_html( 'shift' === $oria_kind ? __( 'About the shift', 'oria' ) : __( 'About the role', 'oria' ) ); ?></label>
			<textarea class="input" id="rp-desc" name="description" rows="8" required minlength="80" maxlength="8000"><?php echo esc_textarea( $oria_body ); ?></textarea>
			<p class="hint"><?php echo esc_html( 'shift' === $oria_kind ? __( 'The class or clients, the room, who to ask for on arrival.', 'oria' ) : __( 'What the work is, the team, the hours, what a good first month looks like. Blank lines make paragraphs.', 'oria' ) ); ?></p>
			<label for="rp-quals"><?php esc_html_e( 'Qualifications required (optional)', 'oria' ); ?></label>
			<textarea class="input" id="rp-quals" name="quals" rows="3" maxlength="1000"><?php echo esc_textarea( (string) $oria_m( 'quals' ) ); ?></textarea>
			<?php if ( 'shift' === $oria_kind ) : ?>
				<label for="rp-bring"><?php esc_html_e( 'What to bring (optional)', 'oria' ); ?></label>
				<textarea class="input" id="rp-bring" name="bring" rows="2" maxlength="500"><?php echo esc_textarea( (string) $oria_m( 'bring' ) ); ?></textarea>
				<div class="wkform__row">
					<div><label for="rp-exp2"><?php esc_html_e( 'Experience', 'oria' ); ?></label>
						<select class="input" id="rp-exp2" name="experience"><?php foreach ( Work\EXPERIENCE as $oria_k => $oria_v ) : ?><option value="<?php echo esc_attr( $oria_k ); ?>" <?php selected( (string) $oria_m( 'experience', 'any' ), $oria_k ); ?>><?php echo esc_html( $oria_v ); ?></option><?php endforeach; ?></select></div>
					<div><label for="rp-workers"><?php esc_html_e( 'People needed', 'oria' ); ?></label><input class="input" id="rp-workers" type="number" name="workers" min="1" max="20" value="<?php echo esc_attr( (string) $oria_m( 'workers', 1 ) ); ?>"></div>
				</div>
			<?php else : ?>
				<p class="wklabel"><?php esc_html_e( 'Staff benefits', 'oria' ); ?></p>
				<div class="wkchecks">
					<?php foreach ( Work\BENEFITS as $oria_k => $oria_v ) : ?>
						<label class="wkcheck"><input type="checkbox" name="benefits[]" value="<?php echo esc_attr( $oria_k ); ?>" <?php checked( in_array( $oria_k, (array) $oria_m( 'benefits', array() ), true ) ); ?>> <?php echo esc_html( $oria_v ); ?></label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</fieldset>

		<fieldset class="wkstep wkpanel" data-step="<?php esc_attr_e( 'Pay', 'oria' ); ?>">
			<legend class="h3"><?php esc_html_e( 'Pay', 'oria' ); ?></legend>
			<?php if ( 'job' === $oria_kind ) : ?>
				<div class="wkform__row">
					<div><label for="rp-min"><?php esc_html_e( 'From ($)', 'oria' ); ?></label><input class="input" id="rp-min" name="pay_min" inputmode="decimal" value="<?php echo esc_attr( (string) $oria_m( 'pay_min' ) ); ?>"></div>
					<div><label for="rp-max"><?php esc_html_e( 'To ($, optional)', 'oria' ); ?></label><input class="input" id="rp-max" name="pay_max" inputmode="decimal" value="<?php echo esc_attr( (string) $oria_m( 'pay_max' ) ); ?>"></div>
					<div><label for="rp-unit"><?php esc_html_e( 'Per', 'oria' ); ?></label>
						<select class="input" id="rp-unit" name="pay_unit"><option value=""><?php esc_html_e( 'Choose', 'oria' ); ?></option><?php foreach ( Work\PAY_UNITS as $oria_k => $oria_v ) : ?><option value="<?php echo esc_attr( $oria_k ); ?>" <?php selected( (string) $oria_m( 'pay_unit' ), $oria_k ); ?>><?php echo esc_html( ucfirst( $oria_v ) ); ?></option><?php endforeach; ?></select></div>
				</div>
				<p class="hint"><?php esc_html_e( 'Jobs with pay shown appear under the "Pay shown" filter. We never guess a figure you have not given.', 'oria' ); ?></p>
			<?php else : ?>
				<div class="wkform__row">
					<div><label for="rp-amt"><?php esc_html_e( 'Amount ($)', 'oria' ); ?></label><input class="input" id="rp-amt" name="pay_amount" inputmode="decimal" value="<?php echo esc_attr( (string) $oria_m( 'pay_amount' ) ); ?>"></div>
					<div><label for="rp-unit2"><?php esc_html_e( 'Pay type', 'oria' ); ?></label>
						<select class="input" id="rp-unit2" name="pay_unit">
							<?php foreach ( array( 'hour' => __( 'Hourly rate', 'oria' ), 'shift' => __( 'Flat shift rate', 'oria' ), 'class' => __( 'Per class', 'oria' ), 'client' => __( 'Per client', 'oria' ), 'commission' => __( 'Commission', 'oria' ), 'negotiable' => __( 'Negotiable', 'oria' ) ) as $oria_k => $oria_v ) : ?>
								<option value="<?php echo esc_attr( $oria_k ); ?>" <?php selected( (string) $oria_m( 'pay_unit', 'class' ), $oria_k ); ?>><?php echo esc_html( $oria_v ); ?></option>
							<?php endforeach; ?>
						</select></div>
				</div>
			<?php endif; ?>
		</fieldset>

		<?php if ( 'job' === $oria_kind ) : ?>
			<fieldset class="wkstep wkpanel" data-step="<?php esc_attr_e( 'Applications', 'oria' ); ?>">
				<legend class="h3"><?php esc_html_e( 'How people apply', 'oria' ); ?></legend>
				<?php $oria_am = (string) $oria_m( 'apply_method', 'oria' ); ?>
				<label class="wkradio"><input type="radio" name="apply_method" value="oria" <?php checked( $oria_am, 'oria' ); ?>> <?php esc_html_e( 'Through Oria — applications and CVs arrive in your dashboard (recommended)', 'oria' ); ?></label>
				<label class="wkradio"><input type="radio" name="apply_method" value="url" <?php checked( $oria_am, 'url' ); ?>> <?php esc_html_e( 'On our website', 'oria' ); ?></label>
				<label for="rp-url" class="wksub"><?php esc_html_e( 'Application page address', 'oria' ); ?></label>
				<input class="input wksub" id="rp-url" name="apply_url" type="url" value="<?php echo esc_attr( (string) $oria_m( 'apply_url' ) ); ?>">
				<label class="wkradio"><input type="radio" name="apply_method" value="email" <?php checked( $oria_am, 'email' ); ?>> <?php esc_html_e( 'By email', 'oria' ); ?></label>
				<label for="rp-email" class="wksub"><?php esc_html_e( 'Email for applications', 'oria' ); ?></label>
				<input class="input wksub" id="rp-email" name="apply_email" type="email" value="<?php echo esc_attr( (string) $oria_m( 'apply_email' ) ); ?>">
				<label for="rp-days"><?php esc_html_e( 'Keep it open for', 'oria' ); ?></label>
				<select class="input" id="rp-days" name="days"><?php foreach ( array( 14, 30, 45, 60 ) as $oria_d ) : ?><option value="<?php echo (int) $oria_d; ?>" <?php selected( 30, $oria_d ); ?>><?php echo esc_html( sprintf( __( '%d days', 'oria' ), $oria_d ) ); ?></option><?php endforeach; ?></select>
			</fieldset>
		<?php endif; ?>

		<fieldset class="wkstep wkpanel" data-step="<?php esc_attr_e( 'Preview', 'oria' ); ?>" data-preview>
			<legend class="h3"><?php esc_html_e( 'Preview and publish', 'oria' ); ?></legend>
			<div class="wkpreview" data-wk-preview aria-live="polite"></div>
			<p class="hint"><?php esc_html_e( 'By publishing you confirm this is a real, paid (or clearly stated volunteer) role with your business, and not an MLM, adult or commission-only role presented as something else.', 'oria' ); ?></p>
		</fieldset>

		<div class="wkwizard__nav">
			<button class="btn btn--ghost" type="button" data-wk-back hidden><?php esc_html_e( 'Back', 'oria' ); ?></button>
			<button class="btn btn--ghost" type="submit" name="do" value="draft" formnovalidate><?php esc_html_e( 'Save draft', 'oria' ); ?></button>
			<button class="btn btn--dark" type="button" data-wk-next hidden><?php esc_html_e( 'Next', 'oria' ); ?></button>
			<button class="btn btn--dark" type="submit" name="do" value="publish" data-wk-publish><?php echo esc_html( $oria_trusted || ( $oria_id && 'publish' === get_post_status( $oria_id ) ) ? __( 'Publish', 'oria' ) : __( 'Submit for approval', 'oria' ) ); ?></button>
		</div>
	</form>
</section>
