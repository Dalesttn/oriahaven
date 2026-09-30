<?php
/**
 * My Oria -> Recruitment: the employer dashboard (brief section 15).
 *
 * Jobs and shifts this account posted, with their state, response counts
 * and the next thing to do. "Find emergency cover" is a shift pre-set to
 * urgent (brief section 74). Messaging and billing are later phases and are
 * not shown as empty tabs.
 */

declare(strict_types=1);

use Oria\Core\Work;
use Oria\Core\Work\Store;

$oria_uid    = get_current_user_id();
$oria_notice = Work\notice();
$oria_posts  = get_posts( array( 'post_type' => array( Work\JOB, Work\SHIFT ), 'author' => $oria_uid, 'post_status' => array( 'publish', 'pending', 'draft' ), 'numberposts' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
$oria_counts = Store\counts( wp_list_pluck( $oria_posts, 'ID' ) );
$oria_jobs   = array_filter( $oria_posts, static fn( $p ) => Work\JOB === $p->post_type );
$oria_shifts = array_filter( $oria_posts, static fn( $p ) => Work\SHIFT === $p->post_type );
$oria_new    = array_sum( array_column( $oria_counts, 'fresh' ) );
$oria_row    = static function ( WP_Post $p ) use ( $oria_counts ): void {
	$id     = (int) $p->ID;
	$c      = $oria_counts[ $id ] ?? array( 'n' => 0, 'fresh' => 0 );
	$why    = Work\closed_reason( $id );
	$state  = 'publish' !== $p->post_status ? ( 'pending' === $p->post_status ? __( 'Waiting for approval', 'oria' ) : __( 'Draft', 'oria' ) ) : ( '' === $why ? __( 'Live', 'oria' ) : __( 'Closed', 'oria' ) );
	$stateK = 'publish' !== $p->post_status ? $p->post_status : ( '' === $why ? 'live' : 'closed' );
	$is_job = Work\JOB === $p->post_type;
	?>
	<li class="wkrow">
		<div class="wkrow__main">
			<a class="wkrow__title" href="<?php echo esc_url( 'publish' === $p->post_status ? (string) get_permalink( $id ) : add_query_arg( array( 'type' => $is_job ? 'job' : 'shift', 'id' => $id ), \Oria\Core\MyOria\url( 'recruit-post' ) ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a>
			<small><?php echo esc_html( implode( ' · ', array_filter( array( Work\place_label( $id ), $is_job ? ( ( $e = (int) Work\meta( $id, 'expires', 0 ) ) && '' === $why ? sprintf( __( 'closes %s', 'oria' ), wp_date( 'j M', $e ) ) : '' ) : Work\shift_when( $id ), $is_job && ( $clk = (int) Work\meta( $id, 'apply_clicks', 0 ) ) ? sprintf( _n( '%d click to apply', '%d clicks to apply', $clk, 'oria' ), $clk ) : '' ) ) ) ); ?></small>
		</div>
		<span class="wkstatus wkstatus--<?php echo esc_attr( $stateK ); ?>"><?php echo esc_html( $state ); ?></span>
		<a class="wkrow__n" href="<?php echo esc_url( add_query_arg( 'id', $id, \Oria\Core\MyOria\url( 'recruit-applicants' ) ) ); ?>">
			<?php echo esc_html( sprintf( $is_job ? _n( '%d applicant', '%d applicants', $c['n'], 'oria' ) : _n( '%d available', '%d available', $c['n'], 'oria' ), $c['n'] ) ); ?>
			<?php if ( $c['fresh'] ) : ?><b class="wknew"><?php echo esc_html( sprintf( __( '%d new', 'oria' ), $c['fresh'] ) ); ?></b><?php endif; ?>
		</a>
		<span class="wkrow__acts">
			<a class="wklink" href="<?php echo esc_url( add_query_arg( array( 'type' => $is_job ? 'job' : 'shift', 'id' => $id ), \Oria\Core\MyOria\url( 'recruit-post' ) ) ); ?>"><?php esc_html_e( 'Edit', 'oria' ); ?></a>
			<?php if ( 'publish' === $p->post_status ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php echo Work\form_fields( 'oria_work_close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
					<?php if ( '' === $why ) : ?>
						<input type="hidden" name="why" value="filled">
						<button class="wklink" type="submit"><?php echo esc_html( $is_job ? __( 'Mark filled', 'oria' ) : __( 'Close', 'oria' ) ); ?></button>
					<?php elseif ( $is_job ) : ?>
						<input type="hidden" name="do" value="reopen">
						<button class="wklink" type="submit"><?php esc_html_e( 'Reopen 30 days', 'oria' ); ?></button>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		</span>
	</li>
	<?php
};
?>
<section class="wrap my my--last wkmy">
	<?php if ( $oria_notice ) : ?>
		<div class="notice notice--<?php echo 'ok' === $oria_notice['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
	<?php endif; ?>
	<div class="my__hello">
		<span class="micro"><?php esc_html_e( 'Work in Wellness', 'oria' ); ?></span>
		<h1 class="h1"><?php esc_html_e( 'Recruitment', 'oria' ); ?></h1>
		<p class="lede"><?php echo esc_html( $oria_new ? sprintf( _n( 'You have %d new response.', 'You have %d new responses.', $oria_new, 'oria' ), $oria_new ) : __( 'Your jobs and shifts, and everyone who has answered them.', 'oria' ) ); ?></p>
		<p class="wkmy__acts">
			<a class="btn btn--dark btn--sm" href="<?php echo esc_url( add_query_arg( 'type', 'job', \Oria\Core\MyOria\url( 'recruit-post' ) ) ); ?>" data-wk-event="job_post_started"><?php esc_html_e( 'Post a job', 'oria' ); ?></a>
			<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( add_query_arg( 'type', 'shift', \Oria\Core\MyOria\url( 'recruit-post' ) ) ); ?>" data-wk-event="shift_post_started"><?php esc_html_e( 'Post a shift', 'oria' ); ?></a>
			<a class="btn btn--ghost btn--sm wkcoverbtn" href="<?php echo esc_url( add_query_arg( array( 'type' => 'shift', 'cover' => 1 ), \Oria\Core\MyOria\url( 'recruit-post' ) ) ); ?>" data-wk-event="emergency_cover_started"><?php esc_html_e( 'Find emergency cover', 'oria' ); ?></a>
		</p>
	</div>

	<section class="wkpanel">
		<h2 class="h3"><?php esc_html_e( 'Jobs', 'oria' ); ?></h2>
		<?php if ( $oria_jobs ) : ?>
			<ul class="wkrows"><?php foreach ( $oria_jobs as $oria_p ) { $oria_row( $oria_p ); } ?></ul>
		<?php else : ?>
			<p class="hint"><?php esc_html_e( 'No jobs yet. Permanent, part-time and casual roles stay open for 30 days and are free during launch.', 'oria' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="wkpanel">
		<h2 class="h3"><?php esc_html_e( 'Shifts', 'oria' ); ?></h2>
		<?php if ( $oria_shifts ) : ?>
			<ul class="wkrows"><?php foreach ( $oria_shifts as $oria_p ) { $oria_row( $oria_p ); } ?></ul>
		<?php else : ?>
			<p class="hint"><?php esc_html_e( 'No shifts yet. A shift closes itself once it is filled or has passed.', 'oria' ); ?></p>
		<?php endif; ?>
	</section>

	<p class="hint"><?php esc_html_e( 'Your first listing is checked by a person before it goes live; after that, your listings go live straight away.', 'oria' ); ?> <a href="<?php echo esc_url( Work\list_url( 'recruitment' ) ); ?>"><?php esc_html_e( 'How it works and what it costs', 'oria' ); ?></a></p>
</section>
