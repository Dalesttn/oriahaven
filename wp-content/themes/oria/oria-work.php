<?php
/**
 * Work in Wellness -- every list page.
 *
 *   jobs         /jobs/, /jobs/{city}/, /jobs/category/{p}/, ...  -> template-parts/work/jobs-page.php
 *   shifts       /shifts/...
 *   pros         /practitioners/...
 *   available    /shifts/available-practitioners/  (practitioners open to cover)
 *   recruitment, corporate, retreat, training, salary  -> their own parts
 *
 * Jobs have their own page (the 2026-10 redesign: sage hero, one column of
 * job rows, a sidebar for employers and alerts). Shifts and practitioners
 * share the list below: search form, card grid, pager.
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_view  = Work\view();
$oria_f     = Work\filters();
$oria_city  = (string) get_query_var( Work\QV_CITY );
$oria_profq = (string) get_query_var( Work\QV_PROF );
$oria_base  = Work\list_url( $oria_view, $oria_city, $oria_profq );
$oria_type  = array( 'shifts' => Work\SHIFT, 'pros' => Work\PRO, 'available' => Work\PRO )[ $oria_view ] ?? '';
if ( 'available' === $oria_view ) {
	$oria_f['cover'] = true;
}
// Once the paywall is on, the precise filters are Oria Recruit's; until then everyone has them.
if ( Work\Plans\paywall_on() && ! Work\Plans\allows( get_current_user_id(), 'search' ) ) {
	$oria_f['radius']   = 0;
	$oria_f['years']    = 0;
	$oria_f['verified'] = false;
}
$oria_people = in_array( $oria_view, array( 'pros', 'available' ), true );
$oria_found  = $oria_people ? Work\search_pros( $oria_f, 24 ) : null;
$oria_q      = 'shifts' === $oria_view ? Work\query( Work\SHIFT, $oria_f, 24 ) : null;
$oria_notice = Work\notice();
$oria_join   = \Oria\Core\MyOria\url( 'work-edit' );

get_header();
?>
<main id="main" class="wk wk--<?php echo esc_attr( $oria_view ); ?>">

	<?php if ( $oria_notice && 'jobs' !== $oria_view ) : ?>
		<div class="wrap"><div class="notice notice--<?php echo 'ok' === $oria_notice['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div></div>
	<?php endif; ?>

	<?php if ( 'jobs' === $oria_view ) : ?>
		<?php get_template_part( 'template-parts/work/jobs-page', null, array( 'f' => $oria_f ) ); ?>
	<?php elseif ( 'recruitment' === $oria_view ) : ?>
		<?php get_template_part( 'template-parts/work/recruitment' ); ?>
	<?php elseif ( in_array( $oria_view, array( 'corporate', 'retreat' ), true ) ) : ?>
		<?php get_template_part( 'template-parts/work/market', null, array( 'market' => $oria_view ) ); ?>
	<?php elseif ( in_array( $oria_view, array( 'training', 'salary' ), true ) ) : ?>
		<?php get_template_part( 'template-parts/work/' . $oria_view ); ?>
	<?php else : ?>

	<header class="wkhero">
		<div class="wrap">
			<?php get_template_part( 'template-parts/work/worknav', null, array( 'view' => $oria_view, 'dark' => true ) ); ?>
			<p class="micro wkhero__kicker"><?php esc_html_e( 'Work in Wellness', 'oria' ); ?></p>
			<h1 class="wkhero__title"><?php echo esc_html( Work\heading() ); ?></h1>
			<p class="lede wkhero__lede">
				<?php
				if ( 'shifts' === $oria_view ) {
					esc_html_e( 'Last-minute classes, weekend cover and short-term gaps at wellness businesses. Tap "I\'m available" and the studio hears from you straight away.', 'oria' );
				} elseif ( 'available' === $oria_view ) {
					esc_html_e( 'Need an instructor tomorrow? These practitioners have said they are open to casual cover.', 'oria' );
				} else {
					esc_html_e( 'Instructors, therapists and wellness staff open to work — with their skills, availability and verified credentials.', 'oria' );
				}
				?>
			</p>
			<?php get_template_part( 'template-parts/work/filters', null, array( 'view' => $oria_view, 'f' => $oria_f, 'action' => $oria_base ) ); ?>
		</div>
	</header>

	<section class="wrap wksec" aria-labelledby="wk-results">
		<?php
		// One shape for both sources: the shift query and practitioner search.
		$oria_ids   = $oria_found ? $oria_found['ids'] : ( $oria_q ? array_map( 'intval', wp_list_pluck( $oria_q->posts, 'ID' ) ) : array() );
		$oria_n     = $oria_found ? (int) $oria_found['total'] : ( $oria_q ? (int) $oria_q->found_posts : 0 );
		$oria_pages = $oria_found ? (int) ceil( $oria_n / 24 ) : ( $oria_q ? (int) $oria_q->max_num_pages : 0 );
		$oria_kms   = $oria_found ? $oria_found['km'] : array();
		?>
		<div class="wksec__head">
			<h2 class="h3" id="wk-results">
				<?php
				echo esc_html(
					'shifts' === $oria_view
						? sprintf( _n( '%d open shift', '%d open shifts', $oria_n, 'oria' ), $oria_n )
						: sprintf( _n( '%d practitioner', '%d practitioners', $oria_n, 'oria' ), $oria_n )
				);
				?>
			</h2>
			<?php if ( 'shifts' === $oria_view ) : ?>
				<a class="wksec__side" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work' ) . '#alerts' ); ?>"><?php esc_html_e( 'Get alerts for new ones', 'oria' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( $oria_ids ) : ?>
			<div class="<?php echo 'shifts' === $oria_view ? 'wkshifts' : 'wkpros'; ?>">
				<?php foreach ( $oria_ids as $oria_pid ) : ?>
					<?php get_template_part( 'template-parts/work/card-' . ( 'shifts' === $oria_view ? 'shift' : 'pro' ), null, array( 'id' => (int) $oria_pid, 'km' => $oria_kms[ $oria_pid ] ?? null ) ); ?>
				<?php endforeach; ?>
			</div>
			<?php get_template_part( 'template-parts/work/pager', null, array( 'pages' => $oria_pages, 'current' => max( 1, (int) ( $oria_f['page'] ?? 1 ) ) ) ); ?>
		<?php else : ?>
			<div class="wkempty">
				<?php if ( 'shifts' === $oria_view ) : ?>
					<p><strong><?php esc_html_e( 'No open shifts right now.', 'oria' ); ?></strong> <?php esc_html_e( 'Turn on urgent shift alerts in your work profile and we will tell you when a studio needs cover near you.', 'oria' ); ?></p>
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( $oria_join ); ?>"><?php esc_html_e( 'Join the cover network', 'oria' ); ?></a>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'No practitioners match yet.', 'oria' ); ?></strong> <?php esc_html_e( 'Are you one? Profiles take a few minutes and put you in front of the businesses hiring.', 'oria' ); ?></p>
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( $oria_join ); ?>"><?php esc_html_e( 'Create practitioner profile', 'oria' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</section>

	<?php endif; ?>
</main>
<?php
get_footer();
