<?php
/**
 * /for-business/recruitment/ -- the employer page.
 *
 * Says what exists today and what it costs today: posting is free during
 * launch. The paid tiers are listed as "coming" with the planned price, so
 * nobody is surprised later, and nothing here takes a payment.
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_p     = Work\prices();
$oria_job   = add_query_arg( 'type', 'job', \Oria\Core\MyOria\url( 'recruit-post' ) );
$oria_shift = add_query_arg( 'type', 'shift', \Oria\Core\MyOria\url( 'recruit-post' ) );
$oria_pros  = Work\open_count( Work\PRO, array() );
?>
<header class="wkhero wkhero--land">
	<div class="wrap">
		<p class="micro wkhero__kicker"><?php esc_html_e( 'For wellness businesses', 'oria' ); ?></p>
		<h1 class="wkhero__title"><?php esc_html_e( 'Find staff. Find cover. Find wellness talent.', 'oria' ); ?></h1>
		<p class="lede wkhero__lede"><?php esc_html_e( 'Post permanent roles and casual shifts where wellness practitioners already look. When an instructor calls in sick, post the shift and we alert the practitioners nearby who suit it.', 'oria' ); ?></p>
		<p class="wkhero__alt">
			<a class="btn btn--light" href="<?php echo esc_url( $oria_job ); ?>" data-wk-event="job_post_started"><?php esc_html_e( 'Post a job', 'oria' ); ?></a>
			<a class="btn btn--ghost-on-deep" href="<?php echo esc_url( $oria_shift ); ?>" data-wk-event="shift_post_started"><?php esc_html_e( 'Post a shift', 'oria' ); ?></a>
		</p>
	</div>
</header>

<section class="wrap wksec">
	<h2 class="h2 wksec__title"><?php esc_html_e( 'How Oria Cover works', 'oria' ); ?></h2>
	<ol class="wksteps">
		<li><strong><?php esc_html_e( 'Post the shift', 'oria' ); ?></strong><span><?php esc_html_e( 'Role, suburb, time and rate. It takes about a minute on your phone.', 'oria' ); ?></span></li>
		<li><strong><?php esc_html_e( 'We find the fit', 'oria' ); ?></strong><span><?php esc_html_e( 'Practitioners with that profession, free at that time, within their travel radius and open to cover.', 'oria' ); ?></span></li>
		<li><strong><?php esc_html_e( 'They tap "I\'m available"', 'oria' ); ?></strong><span><?php esc_html_e( 'You see their profile, experience, rate and verified credentials.', 'oria' ); ?></span></li>
		<li><strong><?php esc_html_e( 'You offer, they accept', 'oria' ); ?></strong><span><?php esc_html_e( 'The shift is confirmed and closes itself once every place is filled.', 'oria' ); ?></span></li>
	</ol>
	<?php if ( $oria_pros ) : ?>
		<p class="hint"><?php echo esc_html( sprintf( _n( '%d practitioner has a public profile so far.', '%d practitioners have public profiles so far.', $oria_pros, 'oria' ), $oria_pros ) ); ?></p>
	<?php endif; ?>
</section>

<section class="wrap wksec">
	<h2 class="h2 wksec__title"><?php esc_html_e( 'What it costs', 'oria' ); ?></h2>
	<p class="lede"><?php esc_html_e( 'Free during launch. We will tell every employer well before anything is charged, and nothing is ever billed without you choosing it.', 'oria' ); ?></p>
	<div class="wkprices">
		<div class="wkprice wkprice--now">
			<h3 class="h3"><?php esc_html_e( 'Launch', 'oria' ); ?></h3>
			<p class="wkprice__amt"><?php esc_html_e( 'Free', 'oria' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Job listings, open 30 days', 'oria' ); ?></li>
				<li><?php esc_html_e( 'Casual shifts and cover', 'oria' ); ?></li>
				<li><?php esc_html_e( 'Applicants in your dashboard', 'oria' ); ?></li>
				<li><?php esc_html_e( 'Urgent alerts to matching practitioners', 'oria' ); ?></li>
				<li><?php esc_html_e( '"Work here" on your Oria listing', 'oria' ); ?></li>
			</ul>
		</div>
		<div class="wkprice">
			<h3 class="h3"><?php esc_html_e( 'Featured job', 'oria' ); ?> <span class="wkbadge"><?php esc_html_e( 'Coming', 'oria' ); ?></span></h3>
			<p class="wkprice__amt"><?php echo esc_html( '$' . (int) $oria_p['job_featured'] ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Top of search and category pages', 'oria' ); ?></li>
				<li><?php esc_html_e( 'Highlighted card', 'oria' ); ?></li>
				<li><?php esc_html_e( 'Included in the weekly jobs email', 'oria' ); ?></li>
			</ul>
		</div>
		<div class="wkprice">
			<h3 class="h3"><?php esc_html_e( 'Oria Recruit', 'oria' ); ?> <span class="wkbadge"><?php esc_html_e( 'Coming', 'oria' ); ?></span></h3>
			<p class="wkprice__amt"><?php echo esc_html( sprintf( __( '$%d/month', 'oria' ), (int) $oria_p['recruit_month'] ) ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Unlimited jobs and shifts', 'oria' ); ?></li>
				<li><?php esc_html_e( 'Practitioner search', 'oria' ); ?></li>
				<li><?php esc_html_e( 'Emergency cover network', 'oria' ); ?></li>
				<li><?php esc_html_e( 'Job analytics', 'oria' ); ?></li>
			</ul>
		</div>
	</div>
</section>

<section class="wrap wksec">
	<h2 class="h3"><?php esc_html_e( 'What we do not list', 'oria' ); ?></h2>
	<p class="hint"><?php esc_html_e( 'We check every new employer before their first listing goes live. We do not list MLM opportunities, unpaid work presented as a job, commission-only roles that hide it, adult services, or anything discriminatory or unsafe. For pay rates and conditions, the Fair Work Ombudsman is the place to check: fairwork.gov.au.', 'oria' ); ?></p>
</section>
