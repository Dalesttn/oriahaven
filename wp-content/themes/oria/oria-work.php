<?php
/**
 * Work in Wellness -- every list page.
 *
 *   jobs         /jobs/ (the landing when nothing is narrowed), /jobs/{city}/, /jobs/category/{p}/, ...
 *   shifts       /shifts/...
 *   pros         /practitioners/...
 *   available    /shifts/available-practitioners/  (practitioners open to cover)
 *   recruitment  /for-business/recruitment/        (the employer pitch and prices)
 *
 * One template so the four lists share a search form, a card grid and a
 * pager, and differ only in what they list.
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_view  = Work\view();
$oria_f     = Work\filters();
$oria_city  = (string) get_query_var( Work\QV_CITY );
$oria_profq = (string) get_query_var( Work\QV_PROF );
$oria_base  = Work\list_url( $oria_view, $oria_city, $oria_profq );
$oria_land  = 'jobs' === $oria_view && '' === $oria_city && '' === $oria_profq && ! Work\is_filtered();
$oria_type  = array( 'jobs' => Work\JOB, 'shifts' => Work\SHIFT, 'pros' => Work\PRO, 'available' => Work\PRO )[ $oria_view ] ?? '';
if ( 'available' === $oria_view ) {
	$oria_f['cover'] = true;
}
$oria_q      = $oria_type ? Work\query( $oria_type, $oria_f, 'jobs' === $oria_view ? 20 : 24 ) : null;
$oria_notice = Work\notice();
$oria_post   = add_query_arg( 'type', 'job', \Oria\Core\MyOria\url( 'recruit-post' ) );
$oria_shiftp = add_query_arg( 'type', 'shift', \Oria\Core\MyOria\url( 'recruit-post' ) );
$oria_join   = \Oria\Core\MyOria\url( 'work-edit' );
$oria_city_n = function_exists( '\Oria\Core\Cities\default_city' ) ? (string) ( \Oria\Core\Cities\default_city()['name'] ?? 'Perth' ) : 'Perth';

get_header();
?>
<main id="main" class="wk wk--<?php echo esc_attr( $oria_view ); ?>">

	<?php if ( $oria_notice ) : ?>
		<div class="wrap"><div class="notice notice--<?php echo 'ok' === $oria_notice['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div></div>
	<?php endif; ?>

	<?php if ( 'recruitment' === $oria_view ) : ?>
		<?php get_template_part( 'template-parts/work/recruitment' ); ?>
	<?php else : ?>

	<header class="wkhero<?php echo $oria_land ? ' wkhero--land' : ''; ?>">
		<div class="wrap">
			<nav class="wktabs" aria-label="<?php esc_attr_e( 'Work in wellness', 'oria' ); ?>">
				<a href="<?php echo esc_url( Work\list_url( 'jobs' ) ); ?>"<?php echo 'jobs' === $oria_view ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Jobs', 'oria' ); ?></a>
				<a href="<?php echo esc_url( Work\list_url( 'shifts' ) ); ?>"<?php echo 'shifts' === $oria_view ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Shifts & cover', 'oria' ); ?></a>
				<a href="<?php echo esc_url( Work\list_url( 'pros' ) ); ?>"<?php echo in_array( $oria_view, array( 'pros', 'available' ), true ) ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Practitioners', 'oria' ); ?></a>
			</nav>
			<p class="micro wkhero__kicker"><?php esc_html_e( 'Work in Wellness', 'oria' ); ?></p>
			<h1 class="wkhero__title"><?php echo esc_html( Work\heading() ); ?></h1>
			<p class="lede wkhero__lede">
				<?php
				if ( 'jobs' === $oria_view ) {
					/* translators: %s: city */
					echo esc_html( sprintf( __( 'Discover jobs, casual shifts and practitioner opportunities across %s\'s wellness industry.', 'oria' ), $oria_city_n ) );
				} elseif ( 'shifts' === $oria_view ) {
					esc_html_e( 'Last-minute classes, weekend cover and short-term gaps at wellness businesses. Tap "I\'m available" and the studio hears from you straight away.', 'oria' );
				} elseif ( 'available' === $oria_view ) {
					esc_html_e( 'Need an instructor tomorrow? These practitioners have said they are open to casual cover.', 'oria' );
				} else {
					esc_html_e( 'Instructors, therapists and wellness staff open to work — with their skills, availability and verified credentials.', 'oria' );
				}
				?>
			</p>
			<?php get_template_part( 'template-parts/work/filters', null, array( 'view' => $oria_view, 'f' => $oria_f, 'action' => $oria_base ) ); ?>
			<?php if ( $oria_land ) : ?>
				<p class="wkhero__alt">
					<a class="btn btn--light btn--sm" href="<?php echo esc_url( Work\list_url( 'shifts' ) ); ?>"><?php esc_html_e( 'Find shifts', 'oria' ); ?></a>
					<a class="btn btn--ghost-on-deep btn--sm" href="<?php echo esc_url( $oria_join ); ?>" data-wk-event="profile_start"><?php esc_html_e( 'Create practitioner profile', 'oria' ); ?></a>
					<span class="wkhero__biz"><?php esc_html_e( 'Looking for someone?', 'oria' ); ?> <a href="<?php echo esc_url( $oria_post ); ?>" data-wk-event="job_post_started"><?php esc_html_e( 'Post a job', 'oria' ); ?></a></span>
				</p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $oria_land ) : ?>
		<?php
		// Browse by profession (brief section 4): the brief's eighteen, each with its live count.
		$oria_browse = array( 'yoga-teacher', 'pilates-instructor', 'massage-therapist', 'personal-trainer', 'physiotherapist', 'counsellor', 'psychologist', 'naturopath', 'nutritionist', 'meditation-teacher', 'breathwork-facilitator', 'sauna-recovery-attendant', 'spa-therapist', 'receptionist', 'studio-manager', 'sales', 'marketing', 'retreat-host' );
		?>
		<section class="wrap wksec">
			<h2 class="h2 wksec__title"><?php esc_html_e( 'Browse by profession', 'oria' ); ?></h2>
			<ul class="wkprofs">
				<?php foreach ( $oria_browse as $oria_slug ) : ?>
					<?php
					$oria_t = get_term_by( 'slug', $oria_slug, Work\PROFESSION );
					if ( ! $oria_t ) {
						continue;
					}
					$oria_n = Work\open_count( Work\JOB, array( 'profession' => $oria_slug ) );
					?>
					<li><a class="wkprof" href="<?php echo esc_url( Work\list_url( 'jobs', '', $oria_slug ) ); ?>">
						<span class="wkprof__name"><?php echo esc_html( $oria_t->name ); ?></span>
						<span class="wkprof__n"><?php echo esc_html( $oria_n ? sprintf( _n( '%d open role', '%d open roles', $oria_n, 'oria' ), $oria_n ) : __( 'No open roles yet', 'oria' ) ); ?></span>
					</a></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<section class="wrap wksec" aria-labelledby="wk-results">
		<div class="wksec__head">
			<h2 class="h3" id="wk-results">
				<?php
				$oria_n = $oria_q ? (int) $oria_q->found_posts : 0;
				if ( 'jobs' === $oria_view ) {
					echo esc_html( $oria_land ? __( 'Latest wellness jobs', 'oria' ) : sprintf( _n( '%d job', '%d jobs', $oria_n, 'oria' ), $oria_n ) );
				} elseif ( 'shifts' === $oria_view ) {
					echo esc_html( sprintf( _n( '%d open shift', '%d open shifts', $oria_n, 'oria' ), $oria_n ) );
				} else {
					echo esc_html( sprintf( _n( '%d practitioner', '%d practitioners', $oria_n, 'oria' ), $oria_n ) );
				}
				?>
			</h2>
			<?php if ( in_array( $oria_view, array( 'jobs', 'shifts' ), true ) ) : ?>
				<a class="wksec__side" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work' ) . '#alerts' ); ?>"><?php esc_html_e( 'Get alerts for new ones', 'oria' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( $oria_q && $oria_q->have_posts() ) : ?>
			<div class="<?php echo 'shifts' === $oria_view ? 'wkshifts' : ( 'jobs' === $oria_view ? 'wkjobs' : 'wkpros' ); ?>">
				<?php foreach ( $oria_q->posts as $oria_p ) : ?>
					<?php get_template_part( 'template-parts/work/card-' . ( 'jobs' === $oria_view ? 'job' : ( 'shifts' === $oria_view ? 'shift' : 'pro' ) ), null, array( 'id' => (int) $oria_p->ID ) ); ?>
				<?php endforeach; ?>
			</div>
			<?php
			$oria_pages = (int) $oria_q->max_num_pages;
			if ( $oria_pages > 1 ) :
				$oria_cur = max( 1, (int) ( $oria_f['page'] ?? 1 ) );
				?>
				<nav class="wkpager" aria-label="<?php esc_attr_e( 'More results', 'oria' ); ?>">
					<?php if ( $oria_cur > 1 ) : ?>
						<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( add_query_arg( 'pg', $oria_cur - 1 ) ); ?>" rel="prev"><?php esc_html_e( 'Previous', 'oria' ); ?></a>
					<?php endif; ?>
					<span><?php echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'oria' ), $oria_cur, $oria_pages ) ); ?></span>
					<?php if ( $oria_cur < $oria_pages ) : ?>
						<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( add_query_arg( 'pg', $oria_cur + 1 ) ); ?>" rel="next"><?php esc_html_e( 'Next', 'oria' ); ?></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		<?php else : ?>
			<div class="wkempty">
				<?php if ( 'jobs' === $oria_view ) : ?>
					<p><strong><?php esc_html_e( 'No open roles match yet.', 'oria' ); ?></strong> <?php esc_html_e( 'New roles are added every week. Set an alert and we will email you the moment one is posted.', 'oria' ); ?></p>
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work' ) . '#alerts' ); ?>"><?php esc_html_e( 'Set a job alert', 'oria' ); ?></a>
				<?php elseif ( 'shifts' === $oria_view ) : ?>
					<p><strong><?php esc_html_e( 'No open shifts right now.', 'oria' ); ?></strong> <?php esc_html_e( 'Turn on urgent shift alerts in your work profile and we will tell you when a studio needs cover near you.', 'oria' ); ?></p>
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( $oria_join ); ?>"><?php esc_html_e( 'Join the cover network', 'oria' ); ?></a>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'No practitioners match yet.', 'oria' ); ?></strong> <?php esc_html_e( 'Are you one? Profiles take a few minutes and put you in front of the businesses hiring.', 'oria' ); ?></p>
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( $oria_join ); ?>"><?php esc_html_e( 'Create practitioner profile', 'oria' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( $oria_land ) : ?>
		<?php
		$oria_urgent = Work\query( Work\SHIFT, array( 'when' => 'week' ), 4 );
		if ( $oria_urgent->have_posts() ) :
			?>
			<section class="wrap wksec">
				<div class="wksec__head">
					<h2 class="h3"><?php esc_html_e( 'Cover needed this week', 'oria' ); ?></h2>
					<a class="wksec__side" href="<?php echo esc_url( Work\list_url( 'shifts' ) ); ?>"><?php esc_html_e( 'All shifts', 'oria' ); ?> &rarr;</a>
				</div>
				<div class="wkshifts">
					<?php foreach ( $oria_urgent->posts as $oria_p ) : ?>
						<?php get_template_part( 'template-parts/work/card-shift', null, array( 'id' => (int) $oria_p->ID ) ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php $oria_hiring = Work\hiring_now( 8 ); ?>
		<?php if ( $oria_hiring ) : ?>
			<section class="wrap wksec">
				<h2 class="h3"><?php esc_html_e( 'Wellness businesses hiring now', 'oria' ); ?></h2>
				<ul class="wkhiring">
					<?php foreach ( $oria_hiring as $oria_l => $oria_c ) : ?>
						<li><a href="<?php echo esc_url( (string) get_permalink( $oria_l ) . '#work-here' ); ?>">
							<span class="wkhiring__name"><?php echo esc_html( html_entity_decode( get_the_title( $oria_l ), ENT_QUOTES, 'UTF-8' ) ); ?></span>
							<span class="wkhiring__n"><?php echo esc_html( sprintf( _n( '%d opening', '%d openings', $oria_c, 'oria' ), $oria_c ) ); ?></span>
						</a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<section class="wrap wksec">
			<div class="wkduo">
				<div class="wkduo__card wkduo__card--deep">
					<p class="micro"><?php esc_html_e( 'For practitioners', 'oria' ); ?></p>
					<h2 class="h3"><?php esc_html_e( 'Be first when a studio needs cover', 'oria' ); ?></h2>
					<p><?php esc_html_e( 'Create a free profile, say when you are free, and switch on urgent shift alerts. When a studio near you needs someone tonight, you hear about it first.', 'oria' ); ?></p>
					<a class="btn btn--light" href="<?php echo esc_url( $oria_join ); ?>" data-wk-event="profile_start"><?php esc_html_e( 'Create practitioner profile', 'oria' ); ?></a>
				</div>
				<div class="wkduo__card">
					<p class="micro"><?php esc_html_e( 'For businesses', 'oria' ); ?></p>
					<h2 class="h3"><?php esc_html_e( 'Looking for someone?', 'oria' ); ?></h2>
					<p><?php esc_html_e( 'Post a job or a casual shift free during launch. Instructor called in sick? Post the shift and we alert the practitioners nearby who suit it.', 'oria' ); ?></p>
					<p class="wkduo__acts">
						<a class="btn btn--dark" href="<?php echo esc_url( $oria_post ); ?>" data-wk-event="job_post_started"><?php esc_html_e( 'Post a job', 'oria' ); ?></a>
						<a class="btn btn--ghost" href="<?php echo esc_url( $oria_shiftp ); ?>" data-wk-event="shift_post_started"><?php esc_html_e( 'Post a shift', 'oria' ); ?></a>
					</p>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php endif; ?>
</main>
<?php
get_footer();
