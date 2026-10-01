<?php
/**
 * My Oria -> Recruitment -> Saved practitioners (brief sections 67-68).
 *
 * The employer's private talent lists: practitioner, note, status. Light ATS:
 * nothing here is visible to the practitioner, and a practitioner who hides
 * their profile drops out of the list for the employer too.
 */

declare(strict_types=1);

use Oria\Core\Work;
use Oria\Core\Work\Store;

$oria_uid    = get_current_user_id();
$oria_notice = Work\notice();
$oria_lists  = Store\talent_lists( $oria_uid );
$oria_allow  = Work\Plans\allows( $oria_uid, 'talent' );
?>
<section class="wrap my my--last wkmy">
	<?php if ( $oria_notice ) : ?>
		<div class="notice notice--<?php echo 'ok' === $oria_notice['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
	<?php endif; ?>
	<div class="my__hello">
		<a class="wkback" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'recruit' ) ); ?>">&larr; <?php esc_html_e( 'Recruitment', 'oria' ); ?></a>
		<h1 class="h1"><?php esc_html_e( 'Saved practitioners', 'oria' ); ?></h1>
		<p class="lede"><?php esc_html_e( 'Your talent lists — weekend cover, future hires, whoever you want to remember. Notes and status are private to you.', 'oria' ); ?></p>
		<p class="wkmy__acts">
			<a class="btn btn--dark btn--sm" href="<?php echo esc_url( Work\list_url( 'pros' ) ); ?>"><?php esc_html_e( 'Find practitioners', 'oria' ); ?></a>
			<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( Work\list_url( 'available' ) ); ?>"><?php esc_html_e( 'Cover board', 'oria' ); ?></a>
		</p>
	</div>

	<?php if ( ! $oria_allow ) : ?>
		<div class="wkpanel"><p><?php esc_html_e( 'Saved talent lists are part of Oria Recruit.', 'oria' ); ?> <a href="<?php echo esc_url( Work\list_url( 'recruitment' ) ); ?>"><?php esc_html_e( 'See plans', 'oria' ); ?></a></p></div>
	<?php elseif ( ! $oria_lists ) : ?>
		<div class="wkpanel"><p><?php esc_html_e( 'Nobody saved yet. Open a practitioner\'s profile and use "Save to a list".', 'oria' ); ?></p></div>
	<?php else : ?>
		<?php foreach ( $oria_lists as $oria_name => $oria_rows ) : ?>
			<section class="wkpanel">
				<h2 class="h3"><?php echo esc_html( $oria_name ); ?> <small class="hint">(<?php echo (int) count( $oria_rows ); ?>)</small></h2>
				<ul class="wkapps">
					<?php foreach ( $oria_rows as $oria_r ) : ?>
						<?php
						$oria_pro = (int) $oria_r['profile_id'];
						if ( Work\PRO !== get_post_type( $oria_pro ) || ! Work\profile_visible_to( $oria_pro, $oria_uid ) ) {
							continue;
						}
						$oria_ap = Work\avail_label( $oria_pro );
						?>
						<li class="wkapp">
							<div class="wkapp__who">
								<span class="wkapp__photo" aria-hidden="true"><?php echo has_post_thumbnail( $oria_pro ) ? get_the_post_thumbnail( $oria_pro, 'thumbnail', array( 'alt' => '' ) ) : esc_html( mb_strtoupper( mb_substr( get_the_title( $oria_pro ), 0, 1 ) ) ); ?></span>
								<div>
									<p class="wkapp__name"><a href="<?php echo esc_url( get_permalink( $oria_pro ) ); ?>"><?php echo esc_html( get_the_title( $oria_pro ) ); ?></a> <span class="wkstatus wkstatus--<?php echo esc_attr( $oria_r['status'] ); ?>"><?php echo esc_html( Store\TALENT_STATUS[ $oria_r['status'] ] ?? $oria_r['status'] ); ?></span></p>
									<p class="hint"><?php echo esc_html( implode( ' · ', array_filter( array( Work\profession_name( $oria_pro ), Work\place_label( $oria_pro ), (string) Work\meta( $oria_pro, 'rate' ), $oria_ap ? sprintf( __( 'Available %s', 'oria' ), $oria_ap ) : '' ) ) ) ); ?></p>
								</div>
							</div>
							<form class="wkform wkform--inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php echo Work\form_fields( 'oria_work_talent' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<input type="hidden" name="profile" value="<?php echo (int) $oria_pro; ?>">
								<input type="hidden" name="list" value="<?php echo esc_attr( $oria_name ); ?>">
								<div>
									<label for="tn-<?php echo (int) $oria_r['id']; ?>"><?php esc_html_e( 'Note', 'oria' ); ?></label>
									<input class="input" id="tn-<?php echo (int) $oria_r['id']; ?>" name="note" maxlength="1000" value="<?php echo esc_attr( (string) $oria_r['note'] ); ?>">
								</div>
								<div>
									<label for="ts-<?php echo (int) $oria_r['id']; ?>"><?php esc_html_e( 'Status', 'oria' ); ?></label>
									<select class="input" id="ts-<?php echo (int) $oria_r['id']; ?>" name="status"><?php foreach ( Store\TALENT_STATUS as $oria_k => $oria_v ) : ?><option value="<?php echo esc_attr( $oria_k ); ?>" <?php selected( $oria_r['status'], $oria_k ); ?>><?php echo esc_html( $oria_v ); ?></option><?php endforeach; ?></select>
								</div>
								<button class="btn btn--ghost btn--sm" type="submit"><?php esc_html_e( 'Save', 'oria' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php echo Work\form_fields( 'oria_work_talent' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<input type="hidden" name="do" value="remove">
								<input type="hidden" name="row" value="<?php echo (int) $oria_r['id']; ?>">
								<button class="wklink" type="submit"><?php esc_html_e( 'Remove from list', 'oria' ); ?></button>
							</form>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
