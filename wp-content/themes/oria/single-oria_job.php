<?php
/**
 * One job.
 *
 * Reads top to bottom the way somebody decides: what and where, what it
 * pays, what the work is, then how to apply. A closed job keeps its page
 * (brief section 58) -- "This role has closed" and similar open roles --
 * because the URL has earned links and a visitor deserves a next step.
 */

declare(strict_types=1);

use Oria\Core\Work;
use Oria\Core\Work\Store;

get_header();

$oria_id      = (int) get_queried_object_id();
$oria_uid     = get_current_user_id();
$oria_closed  = Work\closed_reason( $oria_id );
$oria_open    = 'publish' === get_post_status( $oria_id ) && '' === $oria_closed;
$oria_emp     = Work\employer_name( $oria_id );
$oria_emp_url = Work\employer_url( $oria_id );
$oria_listing = (int) Work\meta( $oria_id, 'listing', 0 );
$oria_type    = Work\term( $oria_id, Work\EMPLOYMENT );
$oria_arr     = (string) Work\meta( $oria_id, 'arrangement' );
$oria_pay     = Work\pay_label( $oria_id );
$oria_exp     = (int) Work\meta( $oria_id, 'expires', 0 );
$oria_method  = (string) Work\meta( $oria_id, 'apply_method', 'oria' );
$oria_ext     = (bool) Work\meta( $oria_id, 'external' );
$oria_app     = $oria_uid ? Store\app_for( 'job', $oria_id, $oria_uid ) : null;
$oria_pro     = $oria_uid ? Work\profile_of( $oria_uid ) : 0;
$oria_ready   = $oria_pro && Work\completeness( $oria_pro )['score'] >= 60;
$oria_saved   = $oria_uid && in_array( $oria_id, Work\Forms\saved_ids( $oria_uid ), true );
$oria_mine    = $oria_uid && Work\owns( $oria_id, $oria_uid );
$oria_notice  = Work\notice();
$oria_benef   = array_intersect_key( Work\BENEFITS, array_flip( (array) Work\meta( $oria_id, 'benefits', array() ) ) );
$oria_facts   = array_filter(
	array(
		__( 'Type', 'oria' )       => $oria_type ? $oria_type->name : '',
		__( 'Where', 'oria' )      => implode( ', ', array_filter( array( Work\place_label( $oria_id ), Work\ARRANGEMENTS[ $oria_arr ] ?? '' ) ) ),
		__( 'Pay', 'oria' )        => $oria_pay ?: __( 'Not stated', 'oria' ),
		__( 'Experience', 'oria' ) => Work\EXPERIENCE[ (string) Work\meta( $oria_id, 'experience', 'any' ) ] ?? '',
		__( 'Closes', 'oria' )     => $oria_exp ? wp_date( 'j F Y', $oria_exp ) : '',
	)
);
$oria_pattern = array_filter( array( Work\meta( $oria_id, 'weekend' ) ? __( 'Weekend work', 'oria' ) : '', Work\meta( $oria_id, 'evening' ) ? __( 'Evening work', 'oria' ) : '' ) );
?>
<main id="main" class="wk wk--single" data-wk-view="job_view" data-wk-id="<?php echo (int) $oria_id; ?>">
	<div class="wrap">
		<?php
		// Came from a jobs search? Offer the way back to exactly that search (filters and page kept).
		$oria_from = (string) wp_get_referer();
		$oria_back = ( '' !== $oria_from && 0 === strpos( $oria_from, Work\list_url( 'jobs' ) ) && ! preg_match( '#/jobs/[^/?]+/?$#', (string) wp_parse_url( $oria_from, PHP_URL_PATH ) ) ) || ( '' !== $oria_from && untrailingslashit( strtok( $oria_from, '?' ) ) === untrailingslashit( Work\list_url( 'jobs' ) ) ) ? $oria_from : '';
		?>
		<nav class="wkcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<?php if ( '' !== $oria_back ) : ?>
				<a href="<?php echo esc_url( $oria_back ); ?>">&larr; <?php esc_html_e( 'Back to results', 'oria' ); ?></a> <span aria-hidden="true">·</span>
			<?php endif; ?>
			<a href="<?php echo esc_url( Work\list_url( 'jobs' ) ); ?>"><?php esc_html_e( 'Jobs', 'oria' ); ?></a>
			<?php if ( $oria_p = Work\term( $oria_id, Work\PROFESSION ) ) : ?>
				<span aria-hidden="true">/</span> <a href="<?php echo esc_url( Work\list_url( 'jobs', '', $oria_p->slug ) ); ?>"><?php echo esc_html( $oria_p->name ); ?></a>
			<?php endif; ?>
		</nav>

		<?php if ( $oria_notice ) : ?>
			<div class="notice notice--<?php echo 'ok' === $oria_notice['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
		<?php endif; ?>

		<?php if ( ! $oria_open ) : ?>
			<div class="wkclosed" role="status">
				<strong><?php esc_html_e( 'This role has closed', 'oria' ); ?></strong>
				<span><?php echo esc_html( 'filled' === $oria_closed ? __( 'The employer has filled it.', 'oria' ) : __( 'It is no longer taking applications.', 'oria' ) ); ?> <a href="#similar"><?php esc_html_e( 'See similar open roles', 'oria' ); ?></a></span>
			</div>
		<?php endif; ?>

		<div class="wksingle">
			<article class="wksingle__main">
				<header class="wkjobhead">
					<div class="wkjobhead__logo" aria-hidden="true">
						<?php if ( $oria_listing && has_post_thumbnail( $oria_listing ) ) : ?>
							<?php echo get_the_post_thumbnail( $oria_listing, 'thumbnail', array( 'alt' => '' ) ); ?>
						<?php else : ?>
							<?php echo esc_html( mb_strtoupper( mb_substr( $oria_emp ?: get_the_title(), 0, 1 ) ) ); ?>
						<?php endif; ?>
					</div>
					<div>
						<?php if ( Work\is_featured( $oria_id ) ) : ?><span class="wkbadge wkbadge--gold"><?php esc_html_e( 'Featured', 'oria' ); ?></span><?php endif; ?>
						<?php if ( $oria_ext ) : ?><span class="wkbadge"><?php esc_html_e( 'External job', 'oria' ); ?></span><?php endif; ?>
						<h1 class="wkjobhead__title"><?php the_title(); ?></h1>
						<p class="wkjobhead__org">
							<?php if ( $oria_emp_url ) : ?>
								<a href="<?php echo esc_url( $oria_emp_url ); ?>"><?php echo esc_html( $oria_emp ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $oria_emp ); ?>
							<?php endif; ?>
							<span class="wkjobhead__posted"><?php echo esc_html( Work\posted_ago( $oria_id ) ); ?></span>
						</p>
					</div>
				</header>

				<dl class="wkfacts">
					<?php foreach ( $oria_facts as $oria_k => $oria_v ) : ?>
						<div><dt><?php echo esc_html( $oria_k ); ?></dt><dd><?php echo esc_html( $oria_v ); ?></dd></div>
					<?php endforeach; ?>
				</dl>

				<div class="wkprose">
					<?php if ( $oria_ext ) : ?>
						<p class="hint"><?php esc_html_e( 'A summary of a role advertised elsewhere. The full description and application are on the employer\'s site.', 'oria' ); ?></p>
					<?php endif; ?>
					<?php echo wp_kses_post( wpautop( (string) get_post_field( 'post_content', $oria_id ) ) ); ?>
				</div>

				<?php if ( $oria_q = trim( (string) Work\meta( $oria_id, 'quals' ) ) ) : ?>
					<h2 class="h3 wkh"><?php esc_html_e( 'What you will need', 'oria' ); ?></h2>
					<div class="wkprose"><?php echo wp_kses_post( wpautop( esc_html( $oria_q ) ) ); ?></div>
				<?php endif; ?>

				<?php if ( $oria_pattern ) : ?>
					<h2 class="h3 wkh"><?php esc_html_e( 'When you would work', 'oria' ); ?></h2>
					<p class="wkchips"><?php foreach ( $oria_pattern as $oria_b ) : ?><span class="wkchip"><?php echo esc_html( $oria_b ); ?></span><?php endforeach; ?></p>
				<?php endif; ?>

				<?php if ( $oria_benef ) : ?>
					<h2 class="h3 wkh"><?php esc_html_e( 'Staff benefits', 'oria' ); ?></h2>
					<p class="wkchips"><?php foreach ( $oria_benef as $oria_b ) : ?><span class="wkchip"><?php echo esc_html( $oria_b ); ?></span><?php endforeach; ?></p>
				<?php endif; ?>

				<?php if ( $oria_emp_url ) : ?>
					<div class="wkabout">
						<h2 class="h3"><?php echo esc_html( sprintf( __( 'About %s', 'oria' ), $oria_emp ) ); ?></h2>
						<p><?php echo esc_html( wp_trim_words( (string) get_post_field( 'post_excerpt', $oria_listing ), 45 ) ); ?></p>
						<a href="<?php echo esc_url( $oria_emp_url ); ?>"><?php esc_html_e( 'See their Oria Haven profile', 'oria' ); ?> &rarr;</a>
					</div>
				<?php endif; ?>

				<?php if ( $oria_ext ) : ?>
					<div class="wkclaim">
						<p><strong><?php esc_html_e( 'Are you the employer?', 'oria' ); ?></strong> <?php esc_html_e( 'Claim this job to receive applications here, edit it and see how it performs.', 'oria' ); ?></p>
						<?php if ( $oria_uid ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php echo Work\form_fields( 'oria_work_claim' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<input type="hidden" name="id" value="<?php echo (int) $oria_id; ?>">
								<button class="btn btn--ghost btn--sm" type="submit"><?php esc_html_e( 'Claim this job', 'oria' ); ?></button>
							</form>
						<?php else : ?>
							<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( get_permalink( $oria_id ) ), \Oria\Core\MyOria\url( 'login' ) ) ); ?>"><?php esc_html_e( 'Sign in to claim this job', 'oria' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php get_template_part( 'template-parts/work/report', null, array( 'id' => $oria_id ) ); ?>
			</article>

			<aside class="wksingle__side" id="apply" aria-label="<?php esc_attr_e( 'Apply', 'oria' ); ?>">
				<div class="wkapply">
					<?php if ( $oria_pay ) : ?><p class="wkapply__pay"><?php echo esc_html( $oria_pay ); ?></p><?php endif; ?>

					<?php if ( ! $oria_open ) : ?>
						<p><?php esc_html_e( 'Applications are closed.', 'oria' ); ?></p>
						<a class="btn btn--dark wkapply__btn" href="#similar"><?php esc_html_e( 'See similar roles', 'oria' ); ?></a>
					<?php elseif ( $oria_mine ) : ?>
						<p><?php esc_html_e( 'This is your listing.', 'oria' ); ?></p>
						<a class="btn btn--dark wkapply__btn" href="<?php echo esc_url( add_query_arg( 'id', $oria_id, \Oria\Core\MyOria\url( 'recruit-applicants' ) ) ); ?>"><?php esc_html_e( 'See applicants', 'oria' ); ?></a>
					<?php elseif ( 'oria' !== $oria_method ) : ?>
						<a class="btn btn--dark wkapply__btn" href="<?php echo esc_url( home_url( '/jobs/go/' . $oria_id . '/' ) ); ?>" rel="nofollow" data-wk-event="job_apply_click"><?php echo esc_html( 'email' === $oria_method ? __( 'Apply by email', 'oria' ) : __( 'Apply on employer\'s site', 'oria' ) ); ?></a>
						<p class="hint"><?php echo esc_html( 'email' === $oria_method ? __( 'Opens your email app with the role in the subject line.', 'oria' ) : __( 'Opens the employer\'s own application page.', 'oria' ) ); ?></p>
					<?php elseif ( $oria_app ) : ?>
						<p class="wkapply__status"><?php echo esc_html( sprintf( __( 'You applied on %1$s. Status: %2$s', 'oria' ), mysql2date( 'j M', (string) $oria_app['created_at'] ), Work\STATUSES[ $oria_app['status'] ] ?? $oria_app['status'] ) ); ?></p>
						<a class="btn btn--ghost wkapply__btn" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work' ) ); ?>"><?php esc_html_e( 'Follow it in My Oria', 'oria' ); ?></a>
					<?php elseif ( ! $oria_uid ) : ?>
						<a class="btn btn--dark wkapply__btn" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( get_permalink( $oria_id ) . '#apply' ), \Oria\Core\MyOria\url( 'register' ) ) ); ?>" data-wk-event="job_apply_click"><?php esc_html_e( 'Apply for this role', 'oria' ); ?></a>
						<p class="hint"><?php esc_html_e( 'Create a free account to apply. Already have one?', 'oria' ); ?> <a href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( get_permalink( $oria_id ) . '#apply' ), \Oria\Core\MyOria\url( 'login' ) ) ); ?>"><?php esc_html_e( 'Sign in', 'oria' ); ?></a></p>
					<?php else : ?>
						<form class="wkform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" data-wk-submit="job_application">
							<?php echo Work\form_fields( 'oria_work_apply' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<input type="hidden" name="job" value="<?php echo (int) $oria_id; ?>">
							<?php if ( $oria_ready ) : ?>
								<input type="hidden" name="one_click" value="1">
								<p class="wkapply__lead"><?php esc_html_e( 'Your Oria profile goes with it: name, profession, qualifications, experience and availability.', 'oria' ); ?> <a href="<?php echo esc_url( get_permalink( $oria_pro ) ); ?>"><?php esc_html_e( 'Check it', 'oria' ); ?></a></p>
							<?php endif; ?>
							<label for="wka-msg"><?php echo esc_html( $oria_ready ? __( 'A short note (optional)', 'oria' ) : __( 'Why you would be great for this role', 'oria' ) ); ?></label>
							<textarea class="input" id="wka-msg" name="message" rows="4" maxlength="2000" <?php echo $oria_ready ? '' : 'required'; ?>></textarea>
							<label for="wka-rate"><?php esc_html_e( 'Expected rate (optional)', 'oria' ); ?></label>
							<input class="input" id="wka-rate" name="rate" maxlength="80" value="<?php echo esc_attr( $oria_pro ? (string) Work\meta( $oria_pro, 'rate' ) : '' ); ?>">
							<label for="wka-av"><?php esc_html_e( 'Your availability (optional)', 'oria' ); ?></label>
							<input class="input" id="wka-av" name="availability" maxlength="160">
							<?php if ( $oria_pro && Work\meta( $oria_pro, 'cv', 0 ) ) : ?>
								<label class="wktoggle"><input type="checkbox" name="use_profile_cv" value="1" checked> <?php esc_html_e( 'Attach the CV on my profile', 'oria' ); ?></label>
							<?php endif; ?>
							<label for="wka-cv"><?php esc_html_e( 'Upload a CV (PDF or Word, optional)', 'oria' ); ?></label>
							<input id="wka-cv" type="file" name="cv" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
							<p class="hint"><?php esc_html_e( 'Only this employer can open your CV. Your email and phone are not shown to them until you say so.', 'oria' ); ?></p>
							<button class="btn btn--dark wkapply__btn" type="submit" data-wk-event="job_apply_click"><?php echo esc_html( $oria_ready ? __( 'Apply with Oria Profile', 'oria' ) : __( 'Send application', 'oria' ) ); ?></button>
						</form>
						<?php if ( ! $oria_ready ) : ?>
							<p class="hint"><a href="<?php echo esc_url( $oria_pro ? \Oria\Core\MyOria\url( 'work-edit' ) : add_query_arg( 'for', $oria_id, \Oria\Core\MyOria\url( 'work-edit' ) ) ); ?>"><?php esc_html_e( 'Finish your work profile', 'oria' ); ?></a> <?php esc_html_e( 'to apply in one tap next time.', 'oria' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ( $oria_uid && ! $oria_mine ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wkapply__save">
							<?php echo Work\form_fields( 'oria_work_save' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<input type="hidden" name="id" value="<?php echo (int) $oria_id; ?>">
							<button class="wklink" type="submit" data-wk-event="job_save" aria-pressed="<?php echo $oria_saved ? 'true' : 'false'; ?>"><?php echo esc_html( $oria_saved ? __( '♥ Saved', 'oria' ) : __( '♡ Save for later', 'oria' ) ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</aside>
		</div>

		<?php
		$oria_prof_t = Work\term( $oria_id, Work\PROFESSION );
		$oria_sim    = $oria_prof_t ? Work\query( Work\JOB, array( 'profession' => $oria_prof_t->slug ), 5 ) : null;
		$oria_sim    = $oria_sim ? array_values( array_filter( $oria_sim->posts, static fn( $p ) => (int) $p->ID !== $oria_id ) ) : array();
		?>
		<section class="wksec" id="similar">
			<h2 class="h3"><?php echo esc_html( $oria_prof_t ? sprintf( __( 'More %s jobs', 'oria' ), $oria_prof_t->name ) : __( 'More wellness jobs', 'oria' ) ); ?></h2>
			<?php if ( $oria_sim ) : ?>
				<div class="wkjobs">
					<?php foreach ( array_slice( $oria_sim, 0, 4 ) as $oria_p ) : ?>
						<?php get_template_part( 'template-parts/work/card-job', null, array( 'id' => (int) $oria_p->ID ) ); ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="hint"><?php esc_html_e( 'Nothing similar open right now.', 'oria' ); ?> <a href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work' ) . '#alerts' ); ?>"><?php esc_html_e( 'Set an alert', 'oria' ); ?></a> <?php esc_html_e( 'and we will email you when there is.', 'oria' ); ?></p>
			<?php endif; ?>
		</section>
	</div>

	<?php if ( $oria_open && ! $oria_app && ! $oria_mine ) : ?>
		<div class="wkstick" aria-hidden="true">
			<span><?php echo esc_html( $oria_pay ?: get_the_title() ); ?></span>
			<a class="btn btn--dark btn--sm" href="#apply" tabindex="-1"><?php esc_html_e( 'Apply', 'oria' ); ?></a>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
