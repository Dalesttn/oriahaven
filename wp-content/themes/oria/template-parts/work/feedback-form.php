<?php
/**
 * The three-question private feedback for one confirmed shift (brief
 * section 71). Used on both dashboards.
 *
 * $args: app (application row), about ('pro' = employer rating the
 *        practitioner, 'employer' = practitioner rating the business)
 */

declare(strict_types=1);

use Oria\Core\Work;
use Oria\Core\Work\Trust;

$oria_a     = (array) ( $args['app'] ?? array() );
$oria_about = 'employer' === ( $args['about'] ?? '' ) ? 'employer' : 'pro';
$oria_qs    = 'pro' === $oria_about ? Trust\ABOUT_PRO : Trust\ABOUT_EMPLOYER;
$oria_id    = (int) ( $oria_a['id'] ?? 0 );
$oria_shift = (int) ( $oria_a['post_id'] ?? 0 );
$oria_who   = 'pro' === $oria_about
	? ( (int) $oria_a['profile_id'] ? get_the_title( (int) $oria_a['profile_id'] ) : __( 'Your practitioner', 'oria' ) )
	: Work\employer_name( $oria_shift );
?>
<form class="wkfeedback" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php echo Work\form_fields( 'oria_work_feedback' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<input type="hidden" name="app" value="<?php echo (int) $oria_id; ?>">
	<p class="wkfeedback__title"><strong><?php echo esc_html( $oria_who ); ?></strong> — <?php echo esc_html( get_the_title( $oria_shift ) . ', ' . Work\shift_when( $oria_shift ) ); ?></p>
	<?php foreach ( $oria_qs as $oria_i => $oria_q ) : ?>
		<fieldset class="wkfeedback__q">
			<legend><?php echo esc_html( $oria_q ); ?>?</legend>
			<label class="wkradio"><input type="radio" name="q<?php echo (int) $oria_i + 1; ?>" value="yes" required> <?php esc_html_e( 'Yes', 'oria' ); ?></label>
			<label class="wkradio"><input type="radio" name="q<?php echo (int) $oria_i + 1; ?>" value="no"> <?php esc_html_e( 'No', 'oria' ); ?></label>
		</fieldset>
	<?php endforeach; ?>
	<?php if ( 'pro' === $oria_about ) : ?>
		<label class="wkcheck"><input type="checkbox" name="no_show" value="1"> <?php esc_html_e( 'They did not turn up', 'oria' ); ?></label>
	<?php endif; ?>
	<label for="fb-note-<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Anything else? (optional, private)', 'oria' ); ?></label>
	<input class="input" id="fb-note-<?php echo (int) $oria_id; ?>" name="note" maxlength="500">
	<button class="btn btn--dark btn--sm" type="submit"><?php esc_html_e( 'Send', 'oria' ); ?></button>
</form>
