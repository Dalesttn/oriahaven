<?php
/**
 * My Oria -> Recruitment -> one job's or shift's responses (brief sections
 * 18 and 21). Only the post's author (or an admin) reaches the rows.
 *
 * Contact details appear here, to the employer, and only for people who
 * applied or said they were available -- they chose to contact this business.
 * CVs download through /work-file/{id}/, never a public URL. For a shift,
 * "Suitable practitioners" counts matching profiles without naming anyone
 * who has not responded.
 */

declare(strict_types=1);

use Oria\Core\Work;
use Oria\Core\Work\Store;

$oria_uid  = get_current_user_id();
$oria_id   = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_type = get_post_type( $oria_id );
$oria_ok   = in_array( $oria_type, array( Work\JOB, Work\SHIFT ), true ) && Work\owns( $oria_id, $oria_uid );
$oria_note = Work\notice();
?>
<section class="wrap my my--last wkmy">
	<a class="wkback" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'recruit' ) ); ?>">&larr; <?php esc_html_e( 'Recruitment', 'oria' ); ?></a>
	<?php if ( ! $oria_ok ) : ?>
		<p><?php esc_html_e( 'That listing is not one of yours.', 'oria' ); ?></p>
	</section>
		<?php
		return;
	endif;
	$oria_shift = Work\SHIFT === $oria_type;
	$oria_apps  = Store\apps_for_post( $oria_id );
	// Opening the list is "viewed" (brief section 77: applicants hear their application was seen).
	foreach ( $oria_apps as $oria_i => $oria_a ) {
		// Only the employer's own look counts -- an admin checking in is not "viewed".
		if ( 'new' === $oria_a['status'] && (int) get_post_field( 'post_author', $oria_id ) === $oria_uid ) {
			Store\set_status( (int) $oria_a['id'], 'viewed' );
			if ( 'job' === $oria_a['kind'] ) {
				Work\Notify\status_changed( (int) $oria_a['id'], 'viewed' );
			}
			$oria_apps[ $oria_i ]['status'] = 'viewed';
		}
	}
	$oria_fit   = $oria_shift ? count( Work\pros_for_shift( $oria_id ) ) : 0;
	$oria_steps = $oria_shift ? array( 'offered' => __( 'Offer shift', 'oria' ), 'rejected' => __( 'Not this time', 'oria' ) ) : array( 'shortlisted' => __( 'Shortlist', 'oria' ), 'interview' => __( 'Interview', 'oria' ), 'offered' => __( 'Offer', 'oria' ), 'hired' => __( 'Hired', 'oria' ), 'rejected' => __( 'Not progressing', 'oria' ) );
	?>
	<?php if ( $oria_note ) : ?>
		<div class="notice notice--<?php echo 'ok' === $oria_note['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_note['text'] ); ?></div>
	<?php endif; ?>
	<div class="my__hello">
		<span class="micro"><?php echo esc_html( $oria_shift ? Work\shift_when( $oria_id ) : Work\posted_ago( $oria_id ) ); ?></span>
		<h1 class="h2"><?php echo esc_html( get_the_title( $oria_id ) ); ?></h1>
		<?php if ( $oria_shift ) : ?>
			<p class="lede"><?php echo esc_html( sprintf( _n( '%d suitable practitioner nearby.', '%d suitable practitioners nearby.', $oria_fit, 'oria' ), $oria_fit ) ); ?>
				<?php if ( $sent = (int) Work\meta( $oria_id, 'urgent_count', 0 ) ) : ?><?php echo esc_html( sprintf( _n( '%d was sent an urgent alert.', '%d were sent an urgent alert.', $sent, 'oria' ), $sent ) ); ?><?php endif; ?>
			</p>
		<?php endif; ?>
	</div>

	<?php
	// Recommended practitioners (brief sections 22-23): suitable people who have not responded yet.
	$oria_seen = array_map( 'intval', array_column( $oria_apps, 'profile_id' ) );
	$oria_rec  = array_values( array_diff( $oria_shift ? Work\pros_for_shift( $oria_id, 30 ) : Work\pros_for_job( $oria_id, 12 ), $oria_seen ) );
	$oria_rec  = array_slice( array_filter( $oria_rec, static fn( $p ) => Work\profile_visible_to( (int) $p, $oria_uid ) ), 0, 6 );
	?>
	<?php if ( $oria_rec && Work\is_open( $oria_id ) ) : ?>
		<section class="wksec">
			<h2 class="h3"><?php esc_html_e( 'Recommended practitioners', 'oria' ); ?></h2>
			<p class="hint"><?php echo esc_html( $oria_shift ? __( 'They suit this shift — profession, place, time — and have not answered yet. Open a profile to invite them.', 'oria' ) : __( 'Matched on profession, skills, place and the kind of work they want. Open a profile to invite them to apply.', 'oria' ) ); ?></p>
			<div class="wkpros">
				<?php foreach ( $oria_rec as $oria_p ) : ?>
					<?php get_template_part( 'template-parts/work/card-pro', null, array( 'id' => (int) $oria_p, 'km' => Work\km_between( (int) $oria_p, $oria_id ) ) ); ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! $oria_apps ) : ?>
		<div class="wkpanel"><p><?php echo esc_html( $oria_shift ? __( 'Nobody has said they are available yet. You will get an email the moment someone does.', 'oria' ) : __( 'No applicants yet. You will get an email the moment someone applies.', 'oria' ) ); ?></p></div>
	<?php else : ?>
		<ul class="wkapps">
			<?php foreach ( $oria_apps as $oria_a ) : ?>
				<?php
				$oria_pro  = (int) $oria_a['profile_id'];
				$oria_user = get_userdata( (int) $oria_a['user_id'] );
				$oria_name = $oria_pro ? get_the_title( $oria_pro ) : ( $oria_user ? $oria_user->display_name : __( 'Applicant', 'oria' ) );
				$oria_yrs  = $oria_pro ? (int) Work\meta( $oria_pro, 'years', 0 ) : 0;
				$oria_bdg  = $oria_pro ? Work\badges( $oria_pro ) : array();
				$oria_km   = $oria_pro ? Work\km_between( $oria_pro, $oria_id ) : null;
				?>
				<li class="wkapp wkapp--<?php echo esc_attr( $oria_a['status'] ); ?>">
					<div class="wkapp__who">
						<span class="wkapp__photo" aria-hidden="true"><?php echo $oria_pro && has_post_thumbnail( $oria_pro ) ? get_the_post_thumbnail( $oria_pro, 'thumbnail', array( 'alt' => '' ) ) : esc_html( mb_strtoupper( mb_substr( $oria_name, 0, 1 ) ) ); ?></span>
						<div>
							<p class="wkapp__name"><?php echo esc_html( $oria_name ); ?> <span class="wkstatus wkstatus--<?php echo esc_attr( $oria_a['status'] ); ?>"><?php echo esc_html( Work\STATUSES[ $oria_a['status'] ] ?? $oria_a['status'] ); ?></span></p>
							<p class="hint"><?php echo esc_html( implode( ' · ', array_filter( array( $oria_pro ? Work\profession_name( $oria_pro ) : '', $oria_pro ? Work\place_label( $oria_pro ) : '', null !== $oria_km ? sprintf( __( '%s km away', 'oria' ), number_format_i18n( $oria_km, 0 ) ) : '', $oria_yrs ? sprintf( _n( '%d year', '%d years', $oria_yrs, 'oria' ), $oria_yrs ) : '', '' !== $oria_a['rate'] ? $oria_a['rate'] : '', mysql2date( 'j M, g:ia', (string) $oria_a['created_at'] ) ) ) ) ); ?></p>
							<?php if ( $oria_bdg ) : ?>
								<p class="wkpro__badges"><?php foreach ( $oria_bdg as $oria_b ) : ?><span class="wkverified"><?php echo esc_html( $oria_b ); ?></span><?php endforeach; ?></p>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( '' !== trim( (string) $oria_a['message'] ) ) : ?>
						<blockquote class="wkapp__msg"><?php echo nl2br( esc_html( (string) $oria_a['message'] ) ); ?></blockquote>
					<?php endif; ?>
					<?php if ( '' !== $oria_a['availability'] ) : ?><p class="hint"><?php echo esc_html( sprintf( __( 'Availability: %s', 'oria' ), $oria_a['availability'] ) ); ?></p><?php endif; ?>
					<p class="wkapp__links">
						<?php if ( $oria_pro ) : ?><a href="<?php echo esc_url( get_permalink( $oria_pro ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Profile', 'oria' ); ?></a><?php endif; ?>
						<?php if ( (int) $oria_a['cv_id'] ) : ?><a href="<?php echo esc_url( home_url( '/work-file/' . (int) $oria_a['id'] . '/' ) ); ?>"><?php esc_html_e( 'Download CV', 'oria' ); ?></a><?php endif; ?>
						<?php if ( $oria_user && 'withdrawn' !== $oria_a['status'] ) : ?><a href="mailto:<?php echo esc_attr( $oria_user->user_email ); ?>?subject=<?php echo rawurlencode( get_the_title( $oria_id ) ); ?>" data-wk-event="employer_contact"><?php esc_html_e( 'Email', 'oria' ); ?></a><?php endif; ?>
					</p>
					<?php if ( ! in_array( $oria_a['status'], array( 'confirmed', 'withdrawn', 'hired' ), true ) ) : ?>
						<form class="wkapp__acts" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php echo Work\form_fields( 'oria_work_status' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<input type="hidden" name="app" value="<?php echo (int) $oria_a['id']; ?>">
							<?php foreach ( $oria_steps as $oria_k => $oria_v ) : ?>
								<?php if ( $oria_k !== $oria_a['status'] ) : ?>
									<button class="btn btn--sm <?php echo in_array( $oria_k, array( 'offered', 'hired' ), true ) ? 'btn--dark' : 'btn--ghost'; ?>" name="to" value="<?php echo esc_attr( $oria_k ); ?>" type="submit"><?php echo esc_html( $oria_v ); ?></button>
								<?php endif; ?>
							<?php endforeach; ?>
						</form>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
