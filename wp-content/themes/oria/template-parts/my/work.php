<?php
/**
 * My Oria -> Work: the job-seeker's side.
 *
 * Profile at a glance, applications and shift offers (with Accept), jobs
 * that match the profile, saved jobs and shifts, and job alerts. An account
 * that is also an employer gets a way across to Recruitment.
 */

declare(strict_types=1);

use Oria\Core\Work;
use Oria\Core\Work\Store;

$oria_uid    = get_current_user_id();
$oria_pro    = Work\profile_of( $oria_uid );
$oria_comp   = $oria_pro ? Work\completeness( $oria_pro ) : null;
$oria_apps   = Store\apps_by_user( $oria_uid );
$oria_saved  = array_values( array_filter( Work\Forms\saved_ids( $oria_uid ), static fn( $id ) => 'publish' === get_post_status( $id ) ) );
$oria_alerts = Store\alerts_by_user( $oria_uid );
$oria_match  = $oria_pro ? Work\jobs_for_pro( $oria_pro, 4 ) : array();
$oria_notice = Work\notice();
$oria_offers = array_filter( $oria_apps, static fn( $a ) => 'offered' === $a['status'] );
?>
<section class="wrap my my--last wkmy">
	<?php if ( $oria_notice ) : ?>
		<div class="notice notice--<?php echo 'ok' === $oria_notice['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
	<?php endif; ?>

	<div class="my__hello">
		<span class="micro"><?php esc_html_e( 'Work in Wellness', 'oria' ); ?></span>
		<h1 class="h1"><?php esc_html_e( 'Work', 'oria' ); ?></h1>
		<p class="lede"><?php esc_html_e( 'Your work profile, the roles you have applied for, shift offers and your job alerts.', 'oria' ); ?></p>
		<p class="wkmy__acts">
			<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( Work\list_url( 'jobs' ) ); ?>"><?php esc_html_e( 'Find jobs', 'oria' ); ?></a>
			<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( Work\list_url( 'shifts' ) ); ?>"><?php esc_html_e( 'Find shifts', 'oria' ); ?></a>
			<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'recruit' ) ); ?>"><?php esc_html_e( 'Hiring? Recruitment', 'oria' ); ?></a>
		</p>
	</div>

	<?php foreach ( $oria_offers as $oria_a ) : ?>
		<div class="wkoffer" role="region" aria-label="<?php esc_attr_e( 'Offer', 'oria' ); ?>">
			<div>
				<p class="micro"><?php echo esc_html( 'shift' === $oria_a['kind'] ? __( 'Shift offer', 'oria' ) : __( 'Job offer', 'oria' ) ); ?></p>
				<p class="wkoffer__title"><a href="<?php echo esc_url( get_permalink( (int) $oria_a['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( (int) $oria_a['post_id'] ) ); ?></a></p>
				<p class="hint"><?php echo esc_html( implode( ' · ', array_filter( array( Work\employer_name( (int) $oria_a['post_id'] ), 'shift' === $oria_a['kind'] ? Work\shift_when( (int) $oria_a['post_id'] ) : '', Work\place_label( (int) $oria_a['post_id'] ) ) ) ) ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wkoffer__acts">
				<?php echo Work\form_fields( 'oria_work_accept' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<input type="hidden" name="app" value="<?php echo (int) $oria_a['id']; ?>">
				<button class="btn btn--dark btn--sm" name="answer" value="yes" type="submit"><?php echo esc_html( 'shift' === $oria_a['kind'] ? __( 'Accept shift', 'oria' ) : __( 'Accept offer', 'oria' ) ); ?></button>
				<button class="btn btn--ghost btn--sm" name="answer" value="no" type="submit"><?php esc_html_e( 'Decline', 'oria' ); ?></button>
			</form>
		</div>
	<?php endforeach; ?>

	<div class="wkmy__grid">
		<section class="wkpanel">
			<h2 class="h3"><?php esc_html_e( 'Your work profile', 'oria' ); ?></h2>
			<?php if ( ! $oria_pro ) : ?>
				<p><?php esc_html_e( 'One profile does three jobs: it lets you apply in one tap, it is what a studio sees when you say you are available, and it lets businesses find you.', 'oria' ); ?></p>
				<a class="btn btn--dark" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work-edit' ) ); ?>" data-wk-event="profile_start"><?php esc_html_e( 'Create practitioner profile', 'oria' ); ?></a>
			<?php else : ?>
				<div class="wkmeter" role="img" aria-label="<?php echo esc_attr( sprintf( __( 'Profile %d%% complete', 'oria' ), $oria_comp['score'] ) ); ?>"><span style="width:<?php echo (int) $oria_comp['score']; ?>%"></span></div>
				<p><strong><?php echo esc_html( sprintf( __( '%d%% complete', 'oria' ), $oria_comp['score'] ) ); ?></strong>
					<?php if ( $oria_comp['missing'] ) : ?> — <?php echo esc_html( sprintf( __( 'add %s.', 'oria' ), implode( ', ', array_slice( $oria_comp['missing'], 0, 3 ) ) ) ); ?><?php endif; ?></p>
				<p class="hint">
					<?php
					$oria_vis = Work\visibility( $oria_pro );
					echo esc_html( 'public' === $oria_vis ? __( 'Public profile.', 'oria' ) : ( 'employers' === $oria_vis ? __( 'Visible to Oria employers only.', 'oria' ) : __( 'Hidden — only you can see it.', 'oria' ) ) );
					echo ' ';
					echo esc_html( Work\meta( $oria_pro, 'urgent_alerts' ) ? __( 'Urgent shift alerts are on.', 'oria' ) : __( 'Urgent shift alerts are off.', 'oria' ) );
					?>
				</p>
				<p class="wkmy__acts">
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work-edit' ) ); ?>"><?php esc_html_e( 'Edit profile', 'oria' ); ?></a>
					<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( get_permalink( $oria_pro ) ); ?>"><?php esc_html_e( 'View it', 'oria' ); ?></a>
				</p>
			<?php endif; ?>
		</section>

		<section class="wkpanel">
			<h2 class="h3"><?php esc_html_e( 'Applications', 'oria' ); ?></h2>
			<?php if ( ! $oria_apps ) : ?>
				<p class="hint"><?php esc_html_e( 'Nothing yet. When you apply for a job or say you are available for a shift, it shows here with its status.', 'oria' ); ?></p>
			<?php else : ?>
				<ul class="wklist">
					<?php foreach ( array_slice( $oria_apps, 0, 20 ) as $oria_a ) : ?>
						<?php $oria_p = (int) $oria_a['post_id']; ?>
						<li>
							<span>
								<a href="<?php echo esc_url( get_permalink( $oria_p ) ); ?>"><?php echo esc_html( get_the_title( $oria_p ) ); ?></a>
								<small><?php echo esc_html( implode( ' · ', array_filter( array( Work\employer_name( $oria_p ), mysql2date( 'j M', (string) $oria_a['created_at'] ) ) ) ) ); ?></small>
							</span>
							<span class="wkstatus wkstatus--<?php echo esc_attr( $oria_a['status'] ); ?>"><?php echo esc_html( Work\STATUSES[ $oria_a['status'] ] ?? $oria_a['status'] ); ?></span>
							<?php if ( ! in_array( $oria_a['status'], array( 'hired', 'confirmed', 'withdrawn', 'rejected' ), true ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php echo Work\form_fields( 'oria_work_withdraw' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<input type="hidden" name="app" value="<?php echo (int) $oria_a['id']; ?>">
									<button class="wklink" type="submit"><?php esc_html_e( 'Withdraw', 'oria' ); ?></button>
								</form>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>

	<?php if ( $oria_pro ) : ?>
		<?php
		$oria_ap    = Work\avail_post( $oria_pro );
		$oria_stats = Work\pro_stats( $oria_pro, $oria_uid );
		?>
		<div class="wkmy__grid">
			<section class="wkpanel" id="availability">
				<h2 class="h3"><?php esc_html_e( 'Availability for cover', 'oria' ); ?></h2>
				<?php if ( $oria_ap ) : ?>
					<p class="wkavail wkavail--post"><span class="wkavail__dot" aria-hidden="true"></span><?php echo esc_html( sprintf( __( 'On the cover board: %s', 'oria' ), Work\avail_label( $oria_pro ) ) ); ?></p>
				<?php else : ?>
					<p class="hint"><?php esc_html_e( 'Free for some extra classes or sessions? Say when, and you go on the cover board for those dates — businesses looking for someone see you first.', 'oria' ); ?></p>
				<?php endif; ?>
				<form class="wkform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php echo Work\form_fields( 'oria_work_avail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div class="wkform__row">
						<div><label for="wa-from"><?php esc_html_e( 'From', 'oria' ); ?></label><input class="input" id="wa-from" type="date" name="from" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $oria_ap['from'] ?? wp_date( 'Y-m-d' ) ); ?>"></div>
						<div><label for="wa-to"><?php esc_html_e( 'Until', 'oria' ); ?></label><input class="input" id="wa-to" type="date" name="to" required min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( wp_date( 'Y-m-d', strtotime( '+90 days' ) ) ); ?>" value="<?php echo esc_attr( $oria_ap['to'] ?? '' ); ?>"></div>
					</div>
					<p class="wklabel"><?php esc_html_e( 'Which days (leave blank for any)', 'oria' ); ?></p>
					<div class="wkchecks">
						<?php foreach ( Work\WEEKDAYS as $oria_d => $oria_l ) : ?>
							<label class="wkcheck"><input type="checkbox" name="days[]" value="<?php echo (int) $oria_d; ?>" <?php checked( in_array( $oria_d, $oria_ap['days'] ?? array(), true ) ); ?>> <?php echo esc_html( $oria_l ); ?></label>
						<?php endforeach; ?>
					</div>
					<label for="wa-note"><?php esc_html_e( 'A short line (optional)', 'oria' ); ?></label>
					<input class="input" id="wa-note" name="note" maxlength="140" value="<?php echo esc_attr( $oria_ap['note'] ?? '' ); ?>">
					<p class="hint"><?php esc_html_e( 'For example "Mornings only, happy to travel to the northern suburbs".', 'oria' ); ?></p>
					<p class="wkmy__acts">
						<button class="btn btn--dark btn--sm" type="submit"><?php echo esc_html( $oria_ap ? __( 'Update availability', 'oria' ) : __( 'Go on the cover board', 'oria' ) ); ?></button>
						<?php if ( $oria_ap ) : ?><button class="btn btn--ghost btn--sm" type="submit" name="do" value="clear" formnovalidate><?php esc_html_e( 'Clear', 'oria' ); ?></button><?php endif; ?>
					</p>
				</form>
			</section>

			<section class="wkpanel">
				<h2 class="h3"><?php esc_html_e( 'Your numbers', 'oria' ); ?></h2>
				<dl class="wkstats">
					<div><dt><?php esc_html_e( 'Profile views', 'oria' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $oria_stats['views'] ) ); ?></dd><small><?php esc_html_e( 'last 30 days', 'oria' ); ?></small></div>
					<div><dt><?php esc_html_e( 'Views by employers', 'oria' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $oria_stats['employers'] ) ); ?></dd><small><?php esc_html_e( 'last 30 days', 'oria' ); ?></small></div>
					<div><dt><?php esc_html_e( 'Invitations', 'oria' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $oria_stats['invites'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Applications', 'oria' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $oria_stats['apps'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Shifts confirmed', 'oria' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $oria_stats['shifts'] ) ); ?></dd></div>
				</dl>
			</section>
		</div>
	<?php endif; ?>

	<?php if ( $oria_match ) : ?>
		<section class="wksec">
			<h2 class="h3"><?php esc_html_e( 'Jobs that match your profile', 'oria' ); ?></h2>
			<div class="wkjobs">
				<?php foreach ( $oria_match as $oria_j ) : ?>
					<?php get_template_part( 'template-parts/work/card-job', null, array( 'id' => (int) $oria_j ) ); ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $oria_saved ) : ?>
		<section class="wksec">
			<h2 class="h3"><?php esc_html_e( 'Saved opportunities', 'oria' ); ?></h2>
			<div class="wkjobs">
				<?php foreach ( $oria_saved as $oria_s ) : ?>
					<?php get_template_part( 'template-parts/work/card-' . ( Work\SHIFT === get_post_type( $oria_s ) ? 'shift' : 'job' ), null, array( 'id' => (int) $oria_s ) ); ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="wksec wkpanel" id="alerts">
		<h2 class="h3"><?php esc_html_e( 'Job alerts', 'oria' ); ?></h2>
		<?php if ( $oria_alerts ) : ?>
			<ul class="wklist">
				<?php foreach ( $oria_alerts as $oria_al ) : ?>
					<?php
					$oria_bits = array_filter(
						array(
							'shift' === $oria_al['kind'] ? __( 'Shifts', 'oria' ) : __( 'Jobs', 'oria' ),
							(int) $oria_al['profession'] && ( $oria_t = get_term( (int) $oria_al['profession'] ) ) instanceof WP_Term ? $oria_t->name : __( 'any profession', 'oria' ),
							(int) $oria_al['area'] && ( $oria_ar = get_term( (int) $oria_al['area'] ) ) instanceof WP_Term ? $oria_ar->name : __( 'anywhere', 'oria' ),
							Work\employment_types()[ $oria_al['employment'] ] ?? '',
							'' !== $oria_al['keyword'] ? '"' . $oria_al['keyword'] . '"' : '',
						)
					);
					?>
					<li>
						<span><?php echo esc_html( implode( ' · ', $oria_bits ) ); ?> <small><?php echo esc_html( array( 'immediate' => __( 'straight away', 'oria' ), 'daily' => __( 'daily', 'oria' ), 'weekly' => __( 'weekly', 'oria' ) )[ $oria_al['frequency'] ] ?? '' ); ?></small></span>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php echo Work\form_fields( 'oria_work_alert' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<input type="hidden" name="do" value="delete">
							<input type="hidden" name="alert" value="<?php echo (int) $oria_al['id']; ?>">
							<button class="wklink" type="submit"><?php esc_html_e( 'Switch off', 'oria' ); ?></button>
						</form>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<form class="wkform wkform--inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo Work\form_fields( 'oria_work_alert' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div>
				<label for="wkal-kind"><?php esc_html_e( 'Alert me about', 'oria' ); ?></label>
				<select class="input" id="wkal-kind" name="kind"><option value="job"><?php esc_html_e( 'Jobs', 'oria' ); ?></option><option value="shift"><?php esc_html_e( 'Casual shifts', 'oria' ); ?></option></select>
			</div>
			<div>
				<label for="wkal-prof"><?php esc_html_e( 'Profession', 'oria' ); ?></label>
				<select class="input" id="wkal-prof" name="profession">
					<option value="0"><?php esc_html_e( 'Any', 'oria' ); ?></option>
					<?php foreach ( get_terms( array( 'taxonomy' => Work\PROFESSION, 'parent' => 0, 'hide_empty' => false ) ) as $oria_g ) : ?>
						<option value="<?php echo (int) $oria_g->term_id; ?>"><?php echo esc_html( sprintf( __( 'All %s', 'oria' ), $oria_g->name ) ); ?></option>
						<?php foreach ( get_terms( array( 'taxonomy' => Work\PROFESSION, 'parent' => $oria_g->term_id, 'hide_empty' => false ) ) as $oria_t ) : ?>
							<option value="<?php echo (int) $oria_t->term_id; ?>">&nbsp;&nbsp;<?php echo esc_html( $oria_t->name ); ?></option>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="wkal-area"><?php esc_html_e( 'Where', 'oria' ); ?></label>
				<select class="input" id="wkal-area" name="area">
					<option value=""><?php esc_html_e( 'Anywhere', 'oria' ); ?></option>
					<?php foreach ( Work\suburb_choices() as $oria_city => $oria_subs ) : ?>
						<optgroup label="<?php echo esc_attr( $oria_city ); ?>">
							<?php foreach ( $oria_subs as $oria_slug => $oria_name ) : ?><option value="<?php echo esc_attr( $oria_slug ); ?>"><?php echo esc_html( $oria_name ); ?></option><?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="wkal-freq"><?php esc_html_e( 'How often', 'oria' ); ?></label>
				<select class="input" id="wkal-freq" name="frequency">
					<option value="immediate"><?php esc_html_e( 'Straight away', 'oria' ); ?></option>
					<option value="daily" selected><?php esc_html_e( 'Daily', 'oria' ); ?></option>
					<option value="weekly"><?php esc_html_e( 'Weekly', 'oria' ); ?></option>
				</select>
			</div>
			<button class="btn btn--dark btn--sm" type="submit"><?php esc_html_e( 'Create alert', 'oria' ); ?></button>
		</form>
	</section>
</section>
