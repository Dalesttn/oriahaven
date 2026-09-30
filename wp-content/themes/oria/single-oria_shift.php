<?php
/**
 * One shift. Faster than a job page on purpose (brief section 7): when,
 * where, what it pays, then one button. The response IS the practitioner's
 * profile, so "I'm available" takes a rate and an optional line, nothing more.
 */

declare(strict_types=1);

use Oria\Core\Work;
use Oria\Core\Work\Store;

get_header();

$oria_id     = (int) get_queried_object_id();
$oria_uid    = get_current_user_id();
$oria_open   = Work\is_open( $oria_id );
$oria_urg    = Work\urgency( $oria_id );
$oria_pay    = Work\pay_label( $oria_id );
$oria_hours  = Work\shift_hours( $oria_id );
$oria_app    = $oria_uid ? Store\app_for( 'shift', $oria_id, $oria_uid ) : null;
$oria_pro    = $oria_uid ? Work\profile_of( $oria_uid ) : 0;
$oria_mine   = $oria_uid && Work\owns( $oria_id, $oria_uid );
$oria_notice = Work\notice();
$oria_emp    = Work\employer_name( $oria_id );
$oria_emp_u  = Work\employer_url( $oria_id );
$oria_places = max( 1, (int) Work\meta( $oria_id, 'workers', 1 ) );
$oria_labels = array(
	'today' => __( 'Urgent — today', 'oria' ),
	'48h'   => __( 'Needed within 48 hours', 'oria' ),
	'week'  => __( 'Needed this week', 'oria' ),
);
?>
<main id="main" class="wk wk--single wk--shift" data-wk-view="shift_view" data-wk-id="<?php echo (int) $oria_id; ?>">
	<div class="wrap wrap--narrow">
		<nav class="wkcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( Work\list_url( 'shifts' ) ); ?>"><?php esc_html_e( 'Shifts & cover', 'oria' ); ?></a>
		</nav>

		<?php if ( $oria_notice ) : ?>
			<div class="notice notice--<?php echo 'ok' === $oria_notice['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
		<?php endif; ?>

		<article class="wkshiftpage wkshift--<?php echo esc_attr( $oria_urg ); ?>">
			<header class="wkshiftpage__head">
				<p class="wkshift__kicker">
					<?php esc_html_e( 'Cover needed', 'oria' ); ?>
					<?php if ( isset( $oria_labels[ $oria_urg ] ) && $oria_open ) : ?>
						<span class="wkbadge wkbadge--<?php echo 'today' === $oria_urg ? 'urgent' : 'soon'; ?>"><?php echo esc_html( $oria_labels[ $oria_urg ] ); ?></span>
					<?php endif; ?>
				</p>
				<h1 class="wkjobhead__title"><?php the_title(); ?></h1>
				<p class="wkjobhead__org">
					<?php if ( $oria_emp_u ) : ?><a href="<?php echo esc_url( $oria_emp_u ); ?>"><?php echo esc_html( $oria_emp ); ?></a><?php else : ?><?php echo esc_html( $oria_emp ); ?><?php endif; ?>
				</p>
			</header>

			<dl class="wkfacts wkfacts--big">
				<div><dt><?php esc_html_e( 'When', 'oria' ); ?></dt><dd><?php echo esc_html( Work\shift_when( $oria_id ) ); ?><?php echo $oria_hours ? ' · ' . esc_html( sprintf( __( '%s hrs', 'oria' ), number_format_i18n( $oria_hours, floor( $oria_hours ) === $oria_hours ? 0 : 1 ) ) ) : ''; ?></dd></div>
				<div><dt><?php esc_html_e( 'Where', 'oria' ); ?></dt><dd><?php echo esc_html( implode( ', ', array_filter( array( (string) Work\meta( $oria_id, 'address' ), Work\place_label( $oria_id ) ) ) ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Pay', 'oria' ); ?></dt><dd><?php echo esc_html( $oria_pay ?: __( 'Ask the business', 'oria' ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Role', 'oria' ); ?></dt><dd><?php echo esc_html( Work\profession_name( $oria_id ) ); ?><?php echo $oria_places > 1 ? ' · ' . esc_html( sprintf( __( '%d people needed', 'oria' ), $oria_places ) ) : ''; ?></dd></div>
			</dl>

			<div id="respond" class="wkrespond">
				<?php if ( ! $oria_open ) : ?>
					<p><strong><?php esc_html_e( 'This shift has been filled or has passed.', 'oria' ); ?></strong> <a href="<?php echo esc_url( Work\list_url( 'shifts' ) ); ?>"><?php esc_html_e( 'See open shifts', 'oria' ); ?></a></p>
				<?php elseif ( $oria_mine ) : ?>
					<a class="btn btn--dark wkapply__btn" href="<?php echo esc_url( add_query_arg( 'id', $oria_id, \Oria\Core\MyOria\url( 'recruit-applicants' ) ) ); ?>"><?php esc_html_e( 'See who is available', 'oria' ); ?></a>
				<?php elseif ( $oria_app ) : ?>
					<p class="wkapply__status"><?php echo esc_html( sprintf( __( 'You said you are available. Status: %s', 'oria' ), Work\STATUSES[ $oria_app['status'] ] ?? $oria_app['status'] ) ); ?></p>
					<?php if ( 'offered' === $oria_app['status'] ) : ?>
						<a class="btn btn--dark wkapply__btn" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work' ) ); ?>"><?php esc_html_e( 'Accept the shift', 'oria' ); ?></a>
					<?php endif; ?>
				<?php elseif ( ! $oria_uid ) : ?>
					<a class="btn btn--dark wkapply__btn wkavailbtn" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( get_permalink( $oria_id ) . '#respond' ), \Oria\Core\MyOria\url( 'register' ) ) ); ?>" data-wk-event="shift_available_click"><?php esc_html_e( "I'm available", 'oria' ); ?></a>
					<p class="hint"><?php esc_html_e( 'Create a free account and profile so the business can see who you are.', 'oria' ); ?> <a href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( get_permalink( $oria_id ) . '#respond' ), \Oria\Core\MyOria\url( 'login' ) ) ); ?>"><?php esc_html_e( 'Sign in', 'oria' ); ?></a></p>
				<?php elseif ( ! $oria_pro ) : ?>
					<a class="btn btn--dark wkapply__btn wkavailbtn" href="<?php echo esc_url( add_query_arg( 'for', $oria_id, \Oria\Core\MyOria\url( 'work-edit' ) ) ); ?>" data-wk-event="shift_available_click"><?php esc_html_e( "I'm available", 'oria' ); ?></a>
					<p class="hint"><?php esc_html_e( 'You will set up your work profile first — it takes a couple of minutes, and the business decides on it.', 'oria' ); ?></p>
				<?php else : ?>
					<form class="wkform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-wk-submit="shift_application">
						<?php echo Work\form_fields( 'oria_work_available' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<input type="hidden" name="shift" value="<?php echo (int) $oria_id; ?>">
						<div class="wkform__row">
							<div>
								<label for="wks-rate"><?php esc_html_e( 'Your rate for this shift', 'oria' ); ?></label>
								<input class="input" id="wks-rate" name="rate" maxlength="80" value="<?php echo esc_attr( (string) Work\meta( $oria_pro, 'rate' ) ); ?>">
							</div>
						</div>
						<label for="wks-msg"><?php esc_html_e( 'Anything they should know? (optional)', 'oria' ); ?></label>
						<textarea class="input" id="wks-msg" name="message" rows="2" maxlength="800"></textarea>
						<button class="btn btn--dark wkapply__btn wkavailbtn" type="submit" data-wk-event="shift_available_click"><?php esc_html_e( "I'm available", 'oria' ); ?></button>
						<p class="hint"><?php esc_html_e( 'The business sees your profile and can offer you the shift. Nothing is confirmed until you accept.', 'oria' ); ?></p>
					</form>
				<?php endif; ?>
			</div>

			<div class="wkprose"><?php echo wp_kses_post( wpautop( (string) get_post_field( 'post_content', $oria_id ) ) ); ?></div>

			<?php foreach ( array( 'quals' => __( 'Qualifications required', 'oria' ), 'bring' => __( 'What to bring', 'oria' ) ) as $oria_k => $oria_h ) : ?>
				<?php if ( $oria_v = trim( (string) Work\meta( $oria_id, $oria_k ) ) ) : ?>
					<h2 class="h3 wkh"><?php echo esc_html( $oria_h ); ?></h2>
					<div class="wkprose"><?php echo wp_kses_post( wpautop( esc_html( $oria_v ) ) ); ?></div>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $oria_e = (string) Work\meta( $oria_id, 'experience' ) ) : ?>
				<p class="hint"><?php echo esc_html( sprintf( __( 'Experience: %s', 'oria' ), Work\EXPERIENCE[ $oria_e ] ?? $oria_e ) ); ?></p>
			<?php endif; ?>

			<?php get_template_part( 'template-parts/work/report', null, array( 'id' => $oria_id, 'label' => __( 'Report this shift', 'oria' ) ) ); ?>
		</article>
	</div>

	<?php if ( $oria_open && ! $oria_app && ! $oria_mine ) : ?>
		<div class="wkstick" aria-hidden="true">
			<span><?php echo esc_html( Work\shift_when( $oria_id ) ); ?></span>
			<a class="btn btn--dark btn--sm" href="#respond" tabindex="-1"><?php esc_html_e( "I'm available", 'oria' ); ?></a>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
