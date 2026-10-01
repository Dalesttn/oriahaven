<?php
/**
 * The jobs page -- /jobs/, /jobs/{city}/, /jobs/category/{p}/ (2026-10 redesign brief).
 *
 * Order: work navigation, sage hero (headline, two search fields, a few
 * profession shortcuts), results with sort and filters, a narrow sidebar for
 * employers and alerts, then the practitioner callout. On phones the results
 * come first and the sidebar follows them.
 *
 * Everything is server-rendered GET: deep links, reloads and Back restore
 * the search. Counts, dates and pay come from the job records. Nothing here
 * is decorative data.
 *
 * $args: f (filters from Work\filters())
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_f     = (array) ( $args['f'] ?? Work\filters() );
// The profession can come from the route (/jobs/category/{p}/); the page treats both alike.
$oria_q     = Work\query( Work\JOB, $oria_f, 20 );
$oria_ids   = array_map( 'intval', wp_list_pluck( $oria_q->posts, 'ID' ) );
$oria_n     = (int) $oria_q->found_posts;
$oria_place = Work\place_phrase( $oria_f );
$oria_city  = ! empty( $oria_f['city'] ) && function_exists( '\Oria\Core\Cities\get' ) ? \Oria\Core\Cities\get( (string) $oria_f['city'] ) : ( function_exists( '\Oria\Core\Cities\default_city' ) ? \Oria\Core\Cities\default_city() : array( 'name' => 'Perth' ) );
$oria_cname = (string) ( $oria_city['name'] ?? 'Perth' );
$oria_chips = Work\active_filters( $oria_f );
$oria_more  = Work\filter_count( $oria_f );
$oria_open  = $oria_more > 0 || ! empty( $_GET['filters'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_notice = Work\notice();
$oria_uid   = get_current_user_id();
$oria_saved = $oria_uid ? Work\Forms\saved_ids( $oria_uid ) : array();
$oria_sort  = (string) ( $oria_f['sort'] ?? '' );

// Shortcuts: the professions that actually have open roles (at most five), plus "All professions".
$oria_counts = Work\profession_counts( $oria_f );
$oria_short  = array_slice( $oria_counts, 0, 5, true );
$oria_cur_p  = (string) ( $oria_f['profession'] ?? '' );
if ( '' !== $oria_cur_p && ! isset( $oria_short[ $oria_cur_p ] ) ) {
	// Never hide the filter that is on, even when it has nothing open.
	$oria_short = array( $oria_cur_p => (int) ( $oria_counts[ $oria_cur_p ] ?? 0 ) ) + $oria_short;
}

// Where the alert panel sends people: the alert form, pre-filled with this search.
$oria_alert = add_query_arg(
	array_filter(
		array(
			'ap' => $oria_cur_p,
			'aa' => (string) ( $oria_f['area'] ?? '' ),
			'at' => (string) ( $oria_f['type'] ?? '' ),
			'ak' => (string) ( $oria_f['q'] ?? '' ),
		)
	),
	\Oria\Core\MyOria\url( 'work' )
) . '#alerts';
?>
<div class="oh-work">

	<div class="ohw-navbar">
		<div class="ohw-wrap">
			<?php get_template_part( 'template-parts/work/worknav', null, array( 'view' => 'jobs' ) ); ?>
		</div>
	</div>

	<header class="ohw-hero">
		<div class="ohw-wrap ohw-hero__grid">
			<div>
				<p class="ohw-eyebrow">
					<?php
					/* translators: %s: the places this search covers */
					echo esc_html( sprintf( __( 'Work in wellness · %s', 'oria' ), Work\place_phrase( array( 'city' => (string) ( $oria_f['city'] ?? '' ) ) ) ) );
					?>
				</p>
				<h1 class="ohw-title"><?php esc_html_e( 'Find work that', 'oria' ); ?> <em><?php esc_html_e( 'feels like you.', 'oria' ); ?></em></h1>
				<p class="ohw-lede"><?php esc_html_e( 'Discover wellness jobs, flexible shifts and your next opportunity to help people feel their best.', 'oria' ); ?></p>
			</div>
			<p class="ohw-aside" aria-hidden="true">
				<span><?php esc_html_e( 'Good work. Local connections.', 'oria' ); ?></span>
				<?php
				/* translators: %s: city */
				echo esc_html( sprintf( __( 'For the people behind %s’s wellness community.', 'oria' ), $oria_cname ) );
				?>
			</p>
		</div>

		<div class="ohw-wrap">
			<form class="ohw-search" method="get" action="<?php echo esc_url( Work\list_url( 'jobs', (string) ( $oria_f['city'] ?? '' ) ) ); ?>" role="search" data-wk-search="jobs">
				<div class="ohw-search__field">
					<label for="ohw-q"><?php esc_html_e( 'What do you do?', 'oria' ); ?></label>
					<input id="ohw-q" type="search" name="q" value="<?php echo esc_attr( (string) ( $oria_f['q'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Job title, keyword or business', 'oria' ); ?>" autocomplete="off">
				</div>
				<div class="ohw-search__field">
					<label for="ohw-a"><?php esc_html_e( 'Where?', 'oria' ); ?></label>
					<select id="ohw-a" name="area">
						<option value=""><?php echo esc_html( Work\place_phrase( array( 'city' => (string) ( $oria_f['city'] ?? '' ) ) ) ); ?></option>
						<?php foreach ( Work\suburb_choices() as $oria_cn => $oria_subs ) : ?>
							<optgroup label="<?php echo esc_attr( $oria_cn ); ?>">
								<?php foreach ( $oria_subs as $oria_slug => $oria_name ) : ?>
									<option value="<?php echo esc_attr( $oria_slug ); ?>" <?php selected( (string) ( $oria_f['area'] ?? '' ), $oria_slug ); ?>><?php echo esc_html( $oria_name ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
				</div>
				<?php // Keep the rest of the search when the main form is submitted. ?>
				<?php foreach ( array( 'profession', 'type', 'paid', 'remote', 'weekend', 'evening', 'saved', 'sort' ) as $oria_k ) : ?>
					<?php if ( ! empty( $oria_f[ $oria_k ] ) ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $oria_k ); ?>" value="<?php echo esc_attr( true === $oria_f[ $oria_k ] ? '1' : (string) $oria_f[ $oria_k ] ); ?>">
					<?php endif; ?>
				<?php endforeach; ?>
				<button class="ohw-btn ohw-btn--forest" type="submit"><?php esc_html_e( 'Find jobs', 'oria' ); ?></button>
			</form>

			<nav class="ohw-browse" aria-label="<?php esc_attr_e( 'Browse roles', 'oria' ); ?>">
				<span class="ohw-browse__label"><?php esc_html_e( 'Browse roles', 'oria' ); ?></span>
				<a class="ohw-chip" href="<?php echo esc_url( Work\jobs_url( $oria_f, array( 'profession' => '' ) ) ); ?>"<?php echo '' === $oria_cur_p ? ' aria-current="true"' : ''; ?>><?php esc_html_e( 'All roles', 'oria' ); ?></a>
				<?php foreach ( $oria_short as $oria_slug => $oria_c ) : ?>
					<?php $oria_t = get_term_by( 'slug', $oria_slug, Work\PROFESSION ); ?>
					<?php if ( $oria_t ) : ?>
						<a class="ohw-chip" href="<?php echo esc_url( Work\jobs_url( $oria_f, array( 'profession' => $oria_slug ) ) ); ?>"<?php echo $oria_cur_p === $oria_slug ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $oria_t->name ); ?> <span class="ohw-chip__n"><?php echo (int) $oria_c; ?></span></a>
					<?php endif; ?>
				<?php endforeach; ?>
				<a class="ohw-chip ohw-chip--quiet" href="<?php echo esc_url( add_query_arg( 'filters', '1', Work\jobs_url( $oria_f ) ) . '#ohw-filters' ); ?>"><?php esc_html_e( 'All professions', 'oria' ); ?></a>
			</nav>
		</div>
	</header>

	<div class="ohw-wrap ohw-body">
		<section class="ohw-results" aria-labelledby="ohw-results-title">
			<?php if ( $oria_notice ) : ?>
				<div class="notice notice--<?php echo 'ok' === $oria_notice['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
			<?php endif; ?>

			<div class="ohw-results__head">
				<div>
					<h2 class="ohw-h2" id="ohw-results-title"><?php echo esc_html( ! empty( $oria_f['saved'] ) ? __( 'Your saved jobs', 'oria' ) : __( 'Your next opportunity', 'oria' ) ); ?></h2>
					<p class="ohw-count" role="status">
						<?php
						/* translators: 1: number of jobs, 2: place */
						echo esc_html( sprintf( _n( '%1$d job · %2$s', '%1$d jobs · %2$s', $oria_n, 'oria' ), $oria_n, $oria_place ) );
						?>
					</p>
				</div>
				<form class="ohw-sort" method="get" action="<?php echo esc_url( Work\jobs_url( $oria_f, array( 'sort' => '' ) ) ); ?>">
					<?php foreach ( Work\JOB_KEYS as $oria_k ) : ?>
						<?php if ( 'sort' !== $oria_k && ! empty( $oria_f[ $oria_k ] ) ) : ?>
							<input type="hidden" name="<?php echo esc_attr( $oria_k ); ?>" value="<?php echo esc_attr( true === $oria_f[ $oria_k ] ? '1' : (string) $oria_f[ $oria_k ] ); ?>">
						<?php endif; ?>
					<?php endforeach; ?>
					<label for="ohw-sort"><?php esc_html_e( 'Sort', 'oria' ); ?></label>
					<select id="ohw-sort" name="sort" data-ohw-autosubmit>
						<option value=""><?php esc_html_e( 'Newest', 'oria' ); ?></option>
						<option value="closing" <?php selected( $oria_sort, 'closing' ); ?>><?php esc_html_e( 'Closing soon', 'oria' ); ?></option>
					</select>
					<noscript><button class="ohw-btn ohw-btn--small" type="submit"><?php esc_html_e( 'Sort', 'oria' ); ?></button></noscript>
				</form>
			</div>

			<div class="ohw-tools">

				<a class="ohw-tool" href="<?php echo esc_url( Work\jobs_url( $oria_f, array( 'paid' => empty( $oria_f['paid'] ) ) ) ); ?>" aria-pressed="<?php echo empty( $oria_f['paid'] ) ? 'false' : 'true'; ?>"><?php esc_html_e( 'Pay shown', 'oria' ); ?></a>
				<?php if ( $oria_uid ) : ?>
					<a class="ohw-tool" href="<?php echo esc_url( Work\jobs_url( $oria_f, array( 'saved' => empty( $oria_f['saved'] ) ) ) ); ?>" aria-pressed="<?php echo empty( $oria_f['saved'] ) ? 'false' : 'true'; ?>"><?php echo esc_html( sprintf( __( 'Saved jobs (%d)', 'oria' ), count( $oria_saved ) ) ); ?></a>
				<?php endif; ?>
				<details class="ohw-filters" id="ohw-filters"<?php echo $oria_open ? ' open' : ''; ?>>
					<summary class="ohw-tool"><?php echo esc_html( $oria_more ? sprintf( __( 'Filters (%d)', 'oria' ), $oria_more ) : __( 'Filters', 'oria' ) ); ?></summary>
					<form class="ohw-filters__panel" method="get" action="<?php echo esc_url( Work\list_url( 'jobs', (string) ( $oria_f['city'] ?? '' ) ) ); ?>">
						<?php foreach ( array( 'q', 'area', 'saved', 'sort', 'paid' ) as $oria_k ) : ?>
							<?php if ( ! empty( $oria_f[ $oria_k ] ) ) : ?>
								<input type="hidden" name="<?php echo esc_attr( $oria_k ); ?>" value="<?php echo esc_attr( true === $oria_f[ $oria_k ] ? '1' : (string) $oria_f[ $oria_k ] ); ?>">
							<?php endif; ?>
						<?php endforeach; ?>
						<div class="ohw-filters__grid">
							<div>
								<label for="ohw-prof"><?php esc_html_e( 'Profession', 'oria' ); ?></label>
								<select id="ohw-prof" name="profession">
									<option value=""><?php esc_html_e( 'All professions', 'oria' ); ?></option>
									<?php foreach ( Work\profession_tree() as $oria_g => $oria_terms ) : ?>
										<optgroup label="<?php echo esc_attr( $oria_g ); ?>">
											<?php foreach ( $oria_terms as $oria_t ) : ?>
												<option value="<?php echo esc_attr( $oria_t->slug ); ?>" <?php selected( $oria_cur_p, $oria_t->slug ); ?>><?php echo esc_html( $oria_t->name . ( isset( $oria_counts[ $oria_t->slug ] ) ? ' (' . $oria_counts[ $oria_t->slug ] . ')' : '' ) ); ?></option>
											<?php endforeach; ?>
										</optgroup>
									<?php endforeach; ?>
								</select>
							</div>
							<div>
								<label for="ohw-type"><?php esc_html_e( 'Employment type', 'oria' ); ?></label>
								<select id="ohw-type" name="type">
									<option value=""><?php esc_html_e( 'Any type', 'oria' ); ?></option>
									<?php foreach ( Work\employment_types() as $oria_k => $oria_v ) : ?>
										<option value="<?php echo esc_attr( $oria_k ); ?>" <?php selected( (string) ( $oria_f['type'] ?? '' ), $oria_k ); ?>><?php echo esc_html( $oria_v ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<fieldset class="ohw-filters__checks">
							<legend><?php esc_html_e( 'Work pattern', 'oria' ); ?></legend>
							<label><input type="checkbox" name="remote" value="1" <?php checked( ! empty( $oria_f['remote'] ) ); ?>> <?php esc_html_e( 'Remote or hybrid', 'oria' ); ?></label>
							<label><input type="checkbox" name="weekend" value="1" <?php checked( ! empty( $oria_f['weekend'] ) ); ?>> <?php esc_html_e( 'Weekend work', 'oria' ); ?></label>
							<label><input type="checkbox" name="evening" value="1" <?php checked( ! empty( $oria_f['evening'] ) ); ?>> <?php esc_html_e( 'Evening work', 'oria' ); ?></label>
							<p class="ohw-note"><?php esc_html_e( 'These match only jobs whose ad says so — a job that does not mention weekends is not counted as weekend work.', 'oria' ); ?></p>
						</fieldset>
						<div class="ohw-filters__acts">
							<button class="ohw-btn ohw-btn--forest" type="submit"><?php esc_html_e( 'Show jobs', 'oria' ); ?></button>
							<?php if ( $oria_chips ) : ?><a href="<?php echo esc_url( Work\list_url( 'jobs', (string) ( $oria_f['city'] ?? '' ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'oria' ); ?></a><?php endif; ?>
						</div>
					</form>
				</details>
			</div>

			<?php if ( $oria_chips ) : ?>
				<ul class="ohw-applied" aria-label="<?php esc_attr_e( 'Applied filters', 'oria' ); ?>">
					<?php foreach ( $oria_chips as $oria_c ) : ?>
						<li><a href="<?php echo esc_url( $oria_c['url'] ); ?>"><?php echo esc_html( $oria_c['label'] ); ?> <span aria-hidden="true">×</span><span class="wk-vh"> <?php esc_html_e( '(remove)', 'oria' ); ?></span></a></li>
					<?php endforeach; ?>
					<li><a class="ohw-applied__clear" href="<?php echo esc_url( Work\list_url( 'jobs', (string) ( $oria_f['city'] ?? '' ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'oria' ); ?></a></li>
				</ul>
			<?php endif; ?>

			<?php if ( $oria_ids ) : ?>
				<ul class="ohw-jobs">
					<?php foreach ( $oria_ids as $oria_id ) : ?>
						<li><?php get_template_part( 'template-parts/work/job-row', null, array( 'id' => $oria_id, 'saved' => in_array( $oria_id, $oria_saved, true ) ) ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php get_template_part( 'template-parts/work/pager', null, array( 'pages' => (int) $oria_q->max_num_pages, 'current' => max( 1, (int) ( $oria_f['page'] ?? 1 ) ) ) ); ?>
			<?php elseif ( ! empty( $oria_f['saved'] ) ) : ?>
				<div class="ohw-empty">
					<p><strong><?php esc_html_e( 'You have not saved any jobs yet.', 'oria' ); ?></strong> <?php esc_html_e( 'Use the bookmark on any job to keep it here for later.', 'oria' ); ?></p>
					<a href="<?php echo esc_url( Work\jobs_url( $oria_f, array( 'saved' => '' ) ) ); ?>"><?php esc_html_e( 'Back to all jobs', 'oria' ); ?></a>
				</div>
			<?php else : ?>
				<div class="ohw-empty">
					<p><strong><?php esc_html_e( 'No jobs match these filters.', 'oria' ); ?></strong> <?php esc_html_e( 'Try fewer filters or a wider area — or set an alert and hear when a matching role is posted.', 'oria' ); ?></p>
					<p class="ohw-empty__acts">
						<?php if ( $oria_chips ) : ?><a class="ohw-btn ohw-btn--ghost" href="<?php echo esc_url( Work\list_url( 'jobs', (string) ( $oria_f['city'] ?? '' ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'oria' ); ?></a><?php endif; ?>
						<?php if ( ! empty( $oria_f['area'] ) ) : ?><a class="ohw-btn ohw-btn--ghost" href="<?php echo esc_url( Work\jobs_url( $oria_f, array( 'area' => '' ) ) ); ?>"><?php echo esc_html( sprintf( __( 'Search all of %s', 'oria' ), $oria_cname ) ); ?></a><?php endif; ?>
						<a class="ohw-btn ohw-btn--forest" href="<?php echo esc_url( $oria_alert ); ?>"><?php esc_html_e( 'Create a job alert', 'oria' ); ?></a>
					</p>
				</div>
			<?php endif; ?>
		</section>

		<aside class="ohw-side" aria-label="<?php esc_attr_e( 'For businesses and job alerts', 'oria' ); ?>">
			<div class="ohw-panel ohw-panel--forest">
				<p class="ohw-eyebrow"><?php esc_html_e( 'For wellness businesses', 'oria' ); ?></p>
				<h2 class="ohw-panel__title"><?php esc_html_e( 'Your next great team member starts here.', 'oria' ); ?></h2>
				<p><?php esc_html_e( 'Connect with people who care about the work you do.', 'oria' ); ?></p>
				<a class="ohw-btn ohw-btn--cream" href="<?php echo esc_url( add_query_arg( 'type', 'job', \Oria\Core\MyOria\url( 'recruit-post' ) ) ); ?>" data-wk-event="job_post_started"><?php esc_html_e( 'Post a job', 'oria' ); ?></a>
				<a class="ohw-panel__link" href="<?php echo esc_url( add_query_arg( array( 'type' => 'shift', 'cover' => 1 ), \Oria\Core\MyOria\url( 'recruit-post' ) ) ); ?>" data-wk-event="emergency_cover_started"><?php esc_html_e( 'Need someone to cover?', 'oria' ); ?></a>
			</div>
			<div class="ohw-panel ohw-panel--sand">
				<svg class="ohw-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6.5 2 6.5H4S6 14 6 9Z"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>
				<h2 class="ohw-panel__title"><?php esc_html_e( 'The right role. In your inbox.', 'oria' ); ?></h2>
				<p><?php esc_html_e( 'Choose your profession and location. Hear when matching opportunities arrive.', 'oria' ); ?></p>
				<a class="ohw-btn ohw-btn--forest" href="<?php echo esc_url( $oria_alert ); ?>"><?php esc_html_e( 'Create a job alert', 'oria' ); ?></a>
			</div>
		</aside>
	</div>

	<section class="ohw-callout" aria-labelledby="ohw-callout-title">
		<div class="ohw-wrap ohw-callout__grid">
			<div>
				<p class="ohw-eyebrow"><?php esc_html_e( 'For practitioners', 'oria' ); ?></p>
				<h2 class="ohw-callout__title" id="ohw-callout-title"><?php esc_html_e( 'A little flexibility.', 'oria' ); ?> <em><?php esc_html_e( 'A new connection.', 'oria' ); ?></em></h2>
			</div>
			<div>
				<p><?php esc_html_e( 'Let local studios know what you do and when you’re available. Find cover work that fits around your practice.', 'oria' ); ?></p>
				<p class="ohw-callout__acts">
					<a class="ohw-btn ohw-btn--forest" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work-edit' ) ); ?>" data-wk-event="profile_start"><?php esc_html_e( 'Create your profile', 'oria' ); ?></a>
					<a class="ohw-link" href="<?php echo esc_url( Work\list_url( 'shifts' ) ); ?>"><?php esc_html_e( 'Explore shifts & cover', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
				</p>
			</div>
		</div>
	</section>

	<?php
	// Career resources: only pages with something real on them.
	$oria_res = array_filter(
		array(
			Work\Market\salary_rows() ? array( Work\list_url( 'salary' ), __( 'Salary guide', 'oria' ) ) : null,
			Work\Market\courses() ? array( Work\list_url( 'training' ), __( 'Training & certification', 'oria' ) ) : null,
			array( Work\list_url( 'corporate' ), __( 'Corporate wellness', 'oria' ) ),
			array( Work\list_url( 'retreat' ), __( 'Retreat staff', 'oria' ) ),
		)
	);
	?>
	<nav class="ohw-wrap ohw-resources" aria-label="<?php esc_attr_e( 'Career resources', 'oria' ); ?>">
		<span><?php esc_html_e( 'More in Work in Wellness:', 'oria' ); ?></span>
		<?php foreach ( $oria_res as $oria_r ) : ?>
			<a href="<?php echo esc_url( $oria_r[0] ); ?>"><?php echo esc_html( $oria_r[1] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<p class="wk-vh" aria-live="polite" data-ohw-live></p>
</div>
