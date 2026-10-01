<?php
/**
 * One job (2026-10 detail-page redesign, the companion to the jobs list).
 *
 * Answers four questions in order: what is the role and who is hiring;
 * where, what it pays and what it asks; what the work is; how to apply.
 * Sage title area with the key facts, then the description beside a forest
 * application panel (on a phone the panel comes straight after the facts,
 * before the long text), then claim/report, then related roles or an alert.
 *
 * Honesty rules this template keeps:
 * - Facts appear only when stored. An imported job's working arrangement and
 *   experience are left out unless the ad stated them, and its closing date
 *   only when the ad gave one (Work\closes_at()).
 * - Who is looking changes the panel, not the facts. An administrator sees
 *   the job-seeker view plus a separate admin strip; the job's own author
 *   (not an admin) sees "Your listing". Applicant records are offered only
 *   for jobs that take applications on Oria.
 * - A closed job keeps its page: the URL has earned links and a visitor
 *   deserves a next step.
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
$oria_prof    = Work\term( $oria_id, Work\PROFESSION );
$oria_type    = Work\term( $oria_id, Work\EMPLOYMENT );
$oria_arr     = (string) Work\meta( $oria_id, 'arrangement' );
$oria_xp      = (string) Work\meta( $oria_id, 'experience' );
$oria_pay     = Work\pay_parts( $oria_id );
$oria_closes  = Work\closes_at( $oria_id );
$oria_method  = (string) Work\meta( $oria_id, 'apply_method', 'oria' );
$oria_ext     = (bool) Work\meta( $oria_id, 'external' );
$oria_src     = Work\source_name( $oria_id );
$oria_host    = Work\apply_host( $oria_id );
$oria_places  = Work\work_places( $oria_id );
$oria_region  = Work\place_label( $oria_id );
$oria_admin   = current_user_can( 'manage_options' );
$oria_author  = $oria_uid && (int) get_post_field( 'post_author', $oria_id ) === $oria_uid;
$oria_ownview = $oria_author && ! $oria_admin; // An admin is not "the employer" just because they imported the job.
$oria_app     = $oria_uid && 'oria' === $oria_method ? Store\app_for( 'job', $oria_id, $oria_uid ) : null;
$oria_pro     = $oria_uid ? Work\profile_of( $oria_uid ) : 0;
$oria_ready   = $oria_pro && Work\completeness( $oria_pro )['score'] >= 60;
$oria_saved   = $oria_uid && in_array( $oria_id, Work\Forms\saved_ids( $oria_uid ), true );
$oria_notice  = Work\notice();
$oria_benef   = array_intersect_key( Work\BENEFITS, array_flip( (array) Work\meta( $oria_id, 'benefits', array() ) ) );
$oria_quals   = Work\quals_items( (string) Work\meta( $oria_id, 'quals' ) );
$oria_title   = get_the_title( $oria_id );
$oria_url     = (string) get_permalink( $oria_id );

// The description. Imported jobs once carried "Originally advertised on ..." in the body;
// the source notice below says it once, so that line is dropped here.
$oria_body = (string) get_post_field( 'post_content', $oria_id );
if ( $oria_ext ) {
	$oria_body = (string) preg_replace( '#\s*<p>Originally advertised on [^<]*</p>\s*$#', '', $oria_body );
}
// A short introduction only when the excerpt is its own text, not the body's first sentences again.
$oria_intro = trim( (string) get_post_field( 'post_excerpt', $oria_id ) );
$oria_plain = trim( wp_strip_all_tags( $oria_body ) );
if ( '' !== $oria_intro && 0 === strpos( $oria_plain, rtrim( (string) preg_replace( '/\s*(…|\[&hellip;\]|&hellip;|\.\.\.)$/u', '', $oria_intro ), '. ' ) ) ) {
	$oria_intro = '';
}

$oria_pattern = array_filter( array( Work\meta( $oria_id, 'weekend' ) ? __( 'Weekend work', 'oria' ) : '', Work\meta( $oria_id, 'evening' ) ? __( 'Evening work', 'oria' ) : '' ) );
$oria_named   = '' !== trim( (string) Work\meta( $oria_id, 'locations' ) );
$oria_facts   = array_filter(
	array(
		array( __( 'Employment', 'oria' ), $oria_type ? $oria_type->name : '', '' ),
		// Named work sites stay apart from the region the job is filed under.
		array( $oria_named && ( str_contains( $oria_places, '&' ) || str_contains( $oria_places, ',' ) ) ? __( 'Work locations', 'oria' ) : __( 'Location', 'oria' ), $oria_places, $oria_named && $oria_region !== $oria_places ? $oria_region : '' ),
		array( __( 'Working arrangement', 'oria' ), Work\ARRANGEMENTS[ $oria_arr ] ?? '', '' ),
		array( __( 'Experience', 'oria' ), Work\EXPERIENCE[ $oria_xp ] ?? '', '' ),
		array( __( 'Hours', 'oria' ), $oria_pattern ? implode( ' · ', $oria_pattern ) : '', '' ),
		array( __( 'Closing date', 'oria' ), $oria_closes ? wp_date( 'j F Y', $oria_closes ) : '', '' ),
	),
	static fn( array $f ): bool => '' !== $f[1]
);

// Came from a jobs search? Offer the way back to exactly that search -- only our own jobs list, never an outside address.
$oria_from = (string) wp_get_referer();
$oria_list = Work\list_url( 'jobs' );
$oria_back = '';
if ( '' !== $oria_from && wp_validate_redirect( $oria_from, '' ) && 0 === strpos( $oria_from, untrailingslashit( $oria_list ) ) && ! preg_match( '#/jobs/(?!category/)[^/?]+/?(\?|$)#', $oria_from ) ) {
	$oria_back = $oria_from;
}

/* translators: %s: job title */
$oria_save_label = sprintf( __( 'Save %s', 'oria' ), $oria_title );
$oria_save_icon  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 3.5h11a.5.5 0 0 1 .5.5v16.2a.3.3 0 0 1-.48.24L12 16.3l-5.52 4.14A.3.3 0 0 1 6 20.2V4a.5.5 0 0 1 .5-.5Z"/></svg>';
?>
<main id="main" class="oh-work oh-job-detail" data-wk-view="job_view" data-wk-id="<?php echo (int) $oria_id; ?>">
	<div class="ohw-navbar">
		<div class="ohw-wrap">
			<?php get_template_part( 'template-parts/work/worknav', null, array( 'view' => 'jobs' ) ); ?>
		</div>
	</div>

	<header class="ohj-hero">
		<div class="ohw-wrap">
			<nav class="ohj-crumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
				<?php if ( '' !== $oria_back ) : ?>
					<a href="<?php echo esc_url( $oria_back ); ?>"><span aria-hidden="true">&larr;</span> <?php esc_html_e( 'Back to results', 'oria' ); ?></a>
					<span class="ohj-crumb__sep" aria-hidden="true">·</span>
				<?php endif; ?>
				<a href="<?php echo esc_url( $oria_list ); ?>"><?php esc_html_e( 'Wellness jobs', 'oria' ); ?></a>
				<?php if ( $oria_prof ) : ?>
					<span class="ohj-crumb__sep" aria-hidden="true">/</span>
					<a href="<?php echo esc_url( Work\list_url( 'jobs', '', $oria_prof->slug ) ); ?>"><?php echo esc_html( $oria_prof->name ); ?></a>
				<?php endif; ?>
			</nav>

			<?php if ( $oria_notice ) : ?>
				<div class="ohj-notice ohj-notice--<?php echo 'ok' === $oria_notice['type'] ? 'ok' : 'error'; ?>" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
			<?php endif; ?>

			<?php if ( ! $oria_open ) : ?>
				<div class="ohj-notice ohj-notice--closed" role="status">
					<strong><?php esc_html_e( 'This role has closed.', 'oria' ); ?></strong>
					<?php echo esc_html( 'filled' === $oria_closed ? __( 'The employer has filled it.', 'oria' ) : __( 'It is no longer taking applications.', 'oria' ) ); ?>
					<a href="#similar"><?php esc_html_e( 'See what else is open', 'oria' ); ?></a>
				</div>
			<?php endif; ?>

			<div class="ohj-emp">
				<div class="ohj-emp__logo" aria-hidden="true">
					<?php if ( $oria_listing && has_post_thumbnail( $oria_listing ) && '' !== $oria_emp_url ) : ?>
						<?php echo get_the_post_thumbnail( $oria_listing, 'thumbnail', array( 'alt' => '' ) ); ?>
					<?php else : ?>
						<?php echo esc_html( mb_strtoupper( mb_substr( $oria_emp ?: $oria_title, 0, 1 ) ) ); ?>
					<?php endif; ?>
				</div>
				<div class="ohj-emp__text">
					<p class="ohj-emp__name">
						<?php if ( '' !== $oria_emp_url ) : ?>
							<a href="<?php echo esc_url( $oria_emp_url ); ?>"><?php echo esc_html( $oria_emp ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $oria_emp ); ?>
						<?php endif; ?>
					</p>
					<p class="ohj-emp__meta">
						<?php if ( $oria_prof ) : ?><span><?php echo esc_html( $oria_prof->name ); ?></span><?php endif; ?>
						<?php if ( '' !== $oria_src ) : ?>
							<span class="ohj-badge"><?php echo esc_html( sprintf( __( 'Advertised on %s', 'oria' ), $oria_src ) ); ?></span>
						<?php endif; ?>
						<?php if ( Work\is_featured( $oria_id ) ) : ?><span class="ohj-badge ohj-badge--gold"><?php esc_html_e( 'Featured listing', 'oria' ); ?></span><?php endif; ?>
					</p>
				</div>
			</div>

			<h1 class="ohj-title"><?php echo esc_html( $oria_title ); ?></h1>
			<?php if ( '' !== $oria_intro ) : ?>
				<p class="ohj-intro"><?php echo esc_html( $oria_intro ); ?></p>
			<?php endif; ?>

			<?php if ( $oria_facts ) : ?>
				<dl class="ohj-facts">
					<?php foreach ( $oria_facts as $oria_f ) : ?>
						<div class="ohj-fact">
							<dt><?php echo esc_html( $oria_f[0] ); ?></dt>
							<dd><?php echo esc_html( $oria_f[1] ); ?><?php if ( '' !== $oria_f[2] ) : ?><span class="ohj-fact__sub"><?php echo esc_html( $oria_f[2] ); ?></span><?php endif; ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
			<p class="ohj-posted"><?php echo esc_html( Work\posted_label( $oria_id ) ); ?></p>
		</div>
	</header>

	<div class="ohw-wrap ohj-body">
		<?php /* The panel comes first in the page so a phone reaches it before the long description; on desktop the grid puts it on the right. */ ?>
		<aside class="ohj-side" id="apply" aria-labelledby="ohj-apply-title">
			<div class="ohj-apply">
				<?php if ( '' !== $oria_pay['amount'] ) : ?>
					<p class="ohj-apply__label"><?php echo esc_html( 'year' === $oria_pay['per'] ? ( $oria_pay['range'] ? __( 'Salary range', 'oria' ) : __( 'Salary', 'oria' ) ) : ( $oria_pay['range'] ? __( 'Pay range', 'oria' ) : __( 'Pay', 'oria' ) ) ); ?></p>
					<p class="ohj-apply__pay"><?php echo esc_html( $oria_pay['amount'] ); ?></p>
					<?php if ( '' !== $oria_pay['unit'] ) : ?><p class="ohj-apply__unit"><?php echo esc_html( $oria_pay['unit'] ); ?></p><?php endif; ?>
				<?php else : ?>
					<p class="ohj-apply__label"><?php esc_html_e( 'Pay', 'oria' ); ?></p>
					<p class="ohj-apply__unit"><?php esc_html_e( 'Pay not specified', 'oria' ); ?></p>
				<?php endif; ?>

				<?php if ( ! $oria_open ) : ?>
					<h2 class="ohj-apply__title" id="ohj-apply-title"><?php esc_html_e( 'Applications have closed.', 'oria' ); ?></h2>
					<p><?php esc_html_e( 'This role is no longer taking applications.', 'oria' ); ?></p>
					<a class="ohw-btn ohw-btn--cream" href="#similar"><?php esc_html_e( 'See what else is open', 'oria' ); ?></a>

				<?php elseif ( $oria_ownview ) : ?>
					<h2 class="ohj-apply__title" id="ohj-apply-title"><?php esc_html_e( 'Your listing', 'oria' ); ?></h2>
					<p><?php echo esc_html( 'oria' === $oria_method ? __( 'Applications come to your Oria dashboard.', 'oria' ) : sprintf( /* translators: %s: where applications go */ __( 'Applications go to %s, so they are not stored on Oria Haven.', 'oria' ), '' !== $oria_host ? $oria_host : __( 'your email', 'oria' ) ) ); ?></p>
					<a class="ohw-btn ohw-btn--cream" href="<?php echo esc_url( add_query_arg( array( 'type' => 'job', 'id' => $oria_id ), \Oria\Core\MyOria\url( 'recruit-post' ) ) ); ?>"><?php esc_html_e( 'Manage listing', 'oria' ); ?></a>
					<?php if ( 'oria' === $oria_method ) : ?>
						<a class="ohj-apply__link" href="<?php echo esc_url( add_query_arg( 'id', $oria_id, \Oria\Core\MyOria\url( 'recruit-applicants' ) ) ); ?>"><?php esc_html_e( 'View applicants', 'oria' ); ?></a>
					<?php endif; ?>

				<?php elseif ( 'url' === $oria_method ) : ?>
					<h2 class="ohj-apply__title" id="ohj-apply-title"><?php esc_html_e( 'Make your next move.', 'oria' ); ?></h2>
					<?php if ( '' !== $oria_host ) : ?>
						<p><?php echo esc_html( '' !== $oria_src ? sprintf( __( 'Read the full advert and apply directly through %s.', 'oria' ), $oria_src ) : __( 'Read the full advert and apply on the employer’s own site.', 'oria' ) ); ?></p>
						<?php /* An ordinary link: /jobs/go/ counts the click on the server, then sends people to the exact stored advert. Works without JavaScript. */ ?>
						<a class="ohw-btn ohw-btn--cream ohj-apply__btn" href="<?php echo esc_url( home_url( '/jobs/go/' . $oria_id . '/' ) ); ?>" rel="nofollow" data-wk-event="job_apply_click">
							<?php echo esc_html( sprintf( __( 'Apply on %s', 'oria' ), '' !== $oria_src ? $oria_src : __( 'external site', 'oria' ) ) ); ?> <span aria-hidden="true">&#8599;</span>
						</a>
						<p class="ohj-apply__note"><?php echo esc_html( sprintf( __( 'Takes you to %s', 'oria' ), $oria_host ) ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'The application link for this role is missing, so we can’t send you to it. Please let us know below and we’ll fix it.', 'oria' ); ?></p>
						<a class="ohw-btn ohw-btn--cream" href="#ohj-report"><?php esc_html_e( 'Report this problem', 'oria' ); ?></a>
					<?php endif; ?>

				<?php elseif ( 'email' === $oria_method ) : ?>
					<h2 class="ohj-apply__title" id="ohj-apply-title"><?php esc_html_e( 'Make your next move.', 'oria' ); ?></h2>
					<p><?php esc_html_e( 'This employer takes applications by email. Your email app opens with the role in the subject line.', 'oria' ); ?></p>
					<a class="ohw-btn ohw-btn--cream ohj-apply__btn" href="<?php echo esc_url( home_url( '/jobs/go/' . $oria_id . '/' ) ); ?>" rel="nofollow" data-wk-event="job_apply_click"><?php esc_html_e( 'Apply by email', 'oria' ); ?></a>

				<?php elseif ( $oria_app ) : ?>
					<h2 class="ohj-apply__title" id="ohj-apply-title"><?php esc_html_e( 'You’ve applied.', 'oria' ); ?></h2>
					<p><?php echo esc_html( sprintf( __( 'Sent on %1$s. Status: %2$s.', 'oria' ), mysql2date( 'j M', (string) $oria_app['created_at'] ), Work\STATUSES[ $oria_app['status'] ] ?? $oria_app['status'] ) ); ?></p>
					<a class="ohw-btn ohw-btn--cream" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work' ) ); ?>"><?php esc_html_e( 'Follow it in My Oria', 'oria' ); ?></a>

				<?php elseif ( ! $oria_uid ) : ?>
					<h2 class="ohj-apply__title" id="ohj-apply-title"><?php esc_html_e( 'Make your next move.', 'oria' ); ?></h2>
					<p><?php esc_html_e( 'Applications for this role go straight to the employer through Oria Haven. You’ll need a free account to send one.', 'oria' ); ?></p>
					<a class="ohw-btn ohw-btn--cream ohj-apply__btn" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( $oria_url . '#apply' ), \Oria\Core\MyOria\url( 'register' ) ) ); ?>" data-wk-event="job_apply_click"><?php esc_html_e( 'Apply for this role', 'oria' ); ?></a>
					<p class="ohj-apply__note"><?php esc_html_e( 'Already have an account?', 'oria' ); ?> <a href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( $oria_url . '#apply' ), \Oria\Core\MyOria\url( 'login' ) ) ); ?>"><?php esc_html_e( 'Sign in', 'oria' ); ?></a></p>

				<?php else : ?>
					<h2 class="ohj-apply__title" id="ohj-apply-title"><?php esc_html_e( 'Make your next move.', 'oria' ); ?></h2>
					<form class="ohj-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" data-wk-submit="job_application">
						<?php echo Work\form_fields( 'oria_work_apply' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<input type="hidden" name="job" value="<?php echo (int) $oria_id; ?>">
						<?php if ( $oria_ready ) : ?>
							<input type="hidden" name="one_click" value="1">
							<p><?php esc_html_e( 'Your Oria profile goes with it: name, profession, qualifications, experience and availability.', 'oria' ); ?> <a href="<?php echo esc_url( get_permalink( $oria_pro ) ); ?>"><?php esc_html_e( 'Check it', 'oria' ); ?></a></p>
						<?php endif; ?>
						<label for="wka-msg"><?php echo esc_html( $oria_ready ? __( 'A short note (optional)', 'oria' ) : __( 'Why you would be great for this role', 'oria' ) ); ?></label>
						<textarea class="input" id="wka-msg" name="message" rows="4" maxlength="2000" <?php echo $oria_ready ? '' : 'required'; ?>></textarea>
						<label for="wka-rate"><?php esc_html_e( 'Expected rate (optional)', 'oria' ); ?></label>
						<input class="input" id="wka-rate" name="rate" maxlength="80" value="<?php echo esc_attr( $oria_pro ? (string) Work\meta( $oria_pro, 'rate' ) : '' ); ?>">
						<label for="wka-av"><?php esc_html_e( 'Your availability (optional)', 'oria' ); ?></label>
						<input class="input" id="wka-av" name="availability" maxlength="160">
						<?php if ( $oria_pro && Work\meta( $oria_pro, 'cv', 0 ) ) : ?>
							<label class="ohj-check"><input type="checkbox" name="use_profile_cv" value="1" checked> <?php esc_html_e( 'Attach the CV on my profile', 'oria' ); ?></label>
						<?php endif; ?>
						<label for="wka-cv"><?php esc_html_e( 'Upload a CV (PDF or Word, optional)', 'oria' ); ?></label>
						<input id="wka-cv" type="file" name="cv" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
						<p class="ohj-apply__note"><?php esc_html_e( 'Only this employer can open your CV. Your email and phone are not shown to them until you say so.', 'oria' ); ?></p>
						<button class="ohw-btn ohw-btn--cream ohj-apply__btn" type="submit" data-wk-event="job_apply_click"><?php echo esc_html( $oria_ready ? __( 'Apply with Oria Profile', 'oria' ) : __( 'Send application', 'oria' ) ); ?></button>
					</form>
					<?php if ( ! $oria_ready ) : ?>
						<p class="ohj-apply__note"><a href="<?php echo esc_url( $oria_pro ? \Oria\Core\MyOria\url( 'work-edit' ) : add_query_arg( 'for', $oria_id, \Oria\Core\MyOria\url( 'work-edit' ) ) ); ?>"><?php esc_html_e( 'Finish your work profile', 'oria' ); ?></a> <?php esc_html_e( 'to apply in one tap next time.', 'oria' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( ! $oria_ownview ) : ?>
					<div class="ohj-apply__save">
						<?php if ( $oria_uid ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ohw-save" data-ohw-save>
								<?php echo Work\form_fields( 'oria_work_save' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<input type="hidden" name="id" value="<?php echo (int) $oria_id; ?>">
								<button type="submit" class="ohw-save__btn" aria-pressed="<?php echo $oria_saved ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $oria_save_label ); ?>" data-wk-event="job_save">
									<?php echo $oria_save_icon; // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
									<span class="ohw-save__text"><?php echo esc_html( $oria_saved ? __( 'Saved', 'oria' ) : __( 'Save this job', 'oria' ) ); ?></span>
								</button>
							</form>
						<?php else : ?>
							<a class="ohw-save__btn" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( $oria_url ), \Oria\Core\MyOria\url( 'login' ) ) ); ?>">
								<?php echo $oria_save_icon; // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
								<span class="ohw-save__text"><?php esc_html_e( 'Save this job', 'oria' ); ?></span><span class="wk-vh"> — <?php esc_html_e( 'sign in to save it', 'oria' ); ?></span>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $oria_open && $oria_closes ) : ?>
					<p class="ohj-apply__closes"><?php echo esc_html( sprintf( __( 'Applications close %s', 'oria' ), wp_date( 'j F', $oria_closes ) ) ); ?></p>
				<?php endif; ?>
			</div>
		</aside>

		<article class="ohj-main" aria-labelledby="ohj-about">
			<section class="ohj-sec">
				<h2 class="ohj-h2" id="ohj-about"><?php esc_html_e( 'About the role', 'oria' ); ?></h2>
				<div class="ohj-prose"><?php echo wp_kses_post( wpautop( $oria_body ) ); ?></div>
			</section>

			<?php if ( $oria_quals ) : ?>
				<section class="ohj-sec ohj-needs" aria-labelledby="ohj-needs">
					<h2 class="ohj-h2" id="ohj-needs"><?php esc_html_e( 'What you’ll bring', 'oria' ); ?></h2>
					<?php if ( count( $oria_quals ) > 1 ) : ?>
						<ul class="ohj-ticks">
							<?php foreach ( $oria_quals as $oria_q ) : ?><li><?php echo esc_html( $oria_q ); ?></li><?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p><?php echo esc_html( $oria_quals[0] ); ?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( $oria_benef ) : ?>
				<section class="ohj-sec" aria-labelledby="ohj-benefits">
					<h2 class="ohj-h2" id="ohj-benefits"><?php esc_html_e( 'Staff benefits', 'oria' ); ?></h2>
					<ul class="ohj-ticks ohj-ticks--cols">
						<?php foreach ( $oria_benef as $oria_b ) : ?><li><?php echo esc_html( $oria_b ); ?></li><?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php if ( '' !== $oria_emp_url ) : ?>
				<section class="ohj-sec" aria-labelledby="ohj-emp-about">
					<h2 class="ohj-h2" id="ohj-emp-about"><?php echo esc_html( sprintf( __( 'About %s', 'oria' ), $oria_emp ) ); ?></h2>
					<?php $oria_ex = wp_trim_words( (string) get_post_field( 'post_excerpt', $oria_listing ), 45 ); ?>
					<?php if ( '' !== $oria_ex ) : ?><p class="ohj-prose"><?php echo esc_html( $oria_ex ); ?></p><?php endif; ?>
					<a class="ohw-link" href="<?php echo esc_url( $oria_emp_url ); ?>"><?php esc_html_e( 'See their Oria Haven profile', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
				</section>
			<?php endif; ?>

			<aside class="ohj-source" aria-label="<?php esc_attr_e( 'About this listing', 'oria' ); ?>">
				<p class="ohj-source__title"><?php esc_html_e( 'About this listing', 'oria' ); ?></p>
				<?php if ( $oria_ext ) : ?>
					<?php $oria_srcurl = (string) Work\meta( $oria_id, 'source_url' ); ?>
					<p>
						<?php echo esc_html( sprintf( __( 'This is a summary of a role advertised on %s. The original advert has the full job description and application details — check the working arrangements there.', 'oria' ), '' !== $oria_src ? $oria_src : ( '' !== $oria_host ? $oria_host : __( 'another site', 'oria' ) ) ) ); ?>
						<?php if ( '' !== $oria_srcurl ) : ?>
							<a href="<?php echo esc_url( $oria_srcurl ); ?>" rel="nofollow noopener"><?php echo esc_html( '' !== $oria_src ? sprintf( __( 'Read the original advert on %s', 'oria' ), $oria_src ) : __( 'Read the original advert', 'oria' ) ); ?></a>
						<?php endif; ?>
					</p>
				<?php elseif ( 'oria' === $oria_method ) : ?>
					<p><?php echo esc_html( sprintf( __( 'Posted on Oria Haven by %s. Applications go straight to them.', 'oria' ), $oria_emp ?: __( 'the employer', 'oria' ) ) ); ?></p>
				<?php else : ?>
					<p><?php echo esc_html( sprintf( __( 'Posted on Oria Haven by %1$s. Applications are handled %2$s.', 'oria' ), $oria_emp ?: __( 'the employer', 'oria' ), 'email' === $oria_method ? __( 'by email', 'oria' ) : sprintf( __( 'on %s', 'oria' ), $oria_host ) ) ); ?></p>
				<?php endif; ?>
			</aside>

			<?php if ( $oria_admin ) : ?>
				<?php /* Server-side gate: only administrators get this markup at all. */ ?>
				<div class="ohj-admin">
					<p class="ohj-admin__title"><?php esc_html_e( 'Admin only', 'oria' ); ?></p>
					<p class="ohj-admin__links">
						<a href="<?php echo esc_url( (string) get_edit_post_link( $oria_id ) ); ?>"><?php esc_html_e( 'Edit job', 'oria' ); ?></a>
						<?php if ( 'oria' === $oria_method ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'id', $oria_id, \Oria\Core\MyOria\url( 'recruit-applicants' ) ) ); ?>"><?php esc_html_e( 'View applicants', 'oria' ); ?></a>
						<?php endif; ?>
					</p>
					<?php if ( $oria_ext ) : ?>
						<p><?php echo esc_html( sprintf( __( 'Imported %1$s. Source check: %2$s', 'oria' ), (string) Work\meta( $oria_id, 'checked' ), (string) Work\meta( $oria_id, 'evidence' ) ?: '—' ) ); ?></p>
						<?php
						$oria_unset = array_filter(
							array(
								'' === $oria_arr ? __( 'working arrangement', 'oria' ) : '',
								'' === $oria_xp ? __( 'experience level', 'oria' ) : '',
								! $oria_closes ? __( 'closing date (the listing runs 30 days from the ad)', 'oria' ) : '',
							)
						);
						?>
						<?php if ( $oria_unset ) : ?>
							<p><?php echo esc_html( sprintf( __( 'Not shown because the ad didn’t confirm it: %s.', 'oria' ), implode( '; ', $oria_unset ) ) ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $oria_ext && ! $oria_admin && ! $oria_author ) : ?>
				<div class="ohj-claim">
					<h2 class="ohj-claim__title"><?php esc_html_e( 'Is this your business?', 'oria' ); ?></h2>
					<p><?php esc_html_e( 'Claim this listing to manage its details on Oria Haven.', 'oria' ); ?></p>
					<?php if ( $oria_uid ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php echo Work\form_fields( 'oria_work_claim' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<input type="hidden" name="id" value="<?php echo (int) $oria_id; ?>">
							<button class="ohw-btn ohw-btn--ghost ohw-btn--small" type="submit"><?php esc_html_e( 'Claim listing', 'oria' ); ?></button>
						</form>
					<?php else : ?>
						<a class="ohw-btn ohw-btn--ghost ohw-btn--small" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( $oria_url ), \Oria\Core\MyOria\url( 'login' ) ) ); ?>"><?php esc_html_e( 'Sign in to claim', 'oria' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="ohj-report" id="ohj-report">
				<?php get_template_part( 'template-parts/work/report', null, array( 'id' => $oria_id, 'label' => __( 'Report a problem with this listing', 'oria' ) ) ); ?>
			</div>
		</article>
	</div>

	<?php $oria_rel = Work\related_jobs( $oria_id, 3 ); ?>
	<?php if ( $oria_rel ) : ?>
		<section class="ohw-wrap ohj-related" id="similar" aria-labelledby="ohj-rel-title">
			<h2 class="ohj-h2" id="ohj-rel-title"><?php echo esc_html( sprintf( __( 'More %s jobs', 'oria' ), $oria_prof ? $oria_prof->name : '' ) ); ?></h2>
			<?php $oria_saved_ids = $oria_uid ? Work\Forms\saved_ids( $oria_uid ) : array(); ?>
			<ul class="ohw-jobs">
				<?php foreach ( $oria_rel as $oria_rid ) : ?>
					<li><?php get_template_part( 'template-parts/work/job-row', null, array( 'id' => $oria_rid, 'saved' => in_array( $oria_rid, $oria_saved_ids, true ) ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php else : ?>
		<?php
		// Nothing related is open: offer an alert for exactly this kind of role, or the full list.
		$oria_city  = Work\city_of( $oria_id );
		$oria_sub   = Work\suburb( $oria_id );
		$oria_reg   = $oria_sub ? ( count( get_ancestors( $oria_sub->term_id, 'area', 'taxonomy' ) ) >= 2 ? get_term( (int) $oria_sub->parent, 'area' ) : $oria_sub ) : null;
		$oria_alert = add_query_arg(
			array_filter(
				array(
					'ap' => $oria_prof ? $oria_prof->slug : '',
					'aa' => $oria_reg instanceof WP_Term ? $oria_reg->slug : '',
				)
			),
			\Oria\Core\MyOria\url( 'work' )
		) . '#alerts';
		$oria_role_lc = $oria_prof ? mb_strtolower( $oria_prof->name ) : __( 'wellness', 'oria' );
		?>
		<section class="ohw-wrap ohj-related" id="similar" aria-labelledby="ohj-alert-title">
			<div class="ohj-alertbox">
				<div>
					<p class="ohw-eyebrow"><?php esc_html_e( 'Keep your options open', 'oria' ); ?></p>
					<h2 class="ohw-callout__title" id="ohj-alert-title"><?php esc_html_e( 'More work that', 'oria' ); ?> <em><?php esc_html_e( 'fits you.', 'oria' ); ?></em></h2>
				</div>
				<div>
					<p><?php echo esc_html( $oria_city ? sprintf( /* translators: 1: profession, lower case, 2: city */ __( 'Hear about %1$s roles around %2$s when matching opportunities are added.', 'oria' ), $oria_role_lc, (string) $oria_city['name'] ) : sprintf( __( 'Hear about %s roles when matching opportunities are added.', 'oria' ), $oria_role_lc ) ); ?></p>
					<p class="ohw-callout__acts">
						<a class="ohw-btn ohw-btn--forest" href="<?php echo esc_url( $oria_alert ); ?>"><?php esc_html_e( 'Create a job alert', 'oria' ); ?></a>
						<a class="ohw-link" href="<?php echo esc_url( $oria_list ); ?>"><?php esc_html_e( 'Browse wellness jobs', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
					</p>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<p class="wk-vh" aria-live="polite" data-ohw-live></p>
</main>
<?php
get_footer();
