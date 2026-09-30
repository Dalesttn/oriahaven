<?php
/**
 * "Report this" (brief section 57). A disclosure, so it costs nothing until
 * somebody needs it. Honeypot + time trap + nonce, as every public form.
 *
 * $args: id, label
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_id = (int) ( $args['id'] ?? 0 );
?>
<details class="wkreport">
	<summary><?php echo esc_html( (string) ( $args['label'] ?? __( 'Report this job', 'oria' ) ) ); ?></summary>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wkreport__form">
		<?php echo Work\form_fields( 'oria_work_report' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<input type="hidden" name="id" value="<?php echo (int) $oria_id; ?>">
		<input type="hidden" name="wk_ts" value="<?php echo (int) time(); ?>">
		<div class="wk-trap" aria-hidden="true"><label>Website <input type="text" name="wk_website" tabindex="-1" autocomplete="off"></label></div>
		<fieldset>
			<legend><?php esc_html_e( 'What is wrong?', 'oria' ); ?></legend>
			<?php foreach ( Work\REPORT_REASONS as $oria_k => $oria_v ) : ?>
				<label class="wkradio"><input type="radio" name="reason" value="<?php echo esc_attr( $oria_k ); ?>" required> <?php echo esc_html( $oria_v ); ?></label>
			<?php endforeach; ?>
		</fieldset>
		<label for="wkr-note-<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Anything we should know? (optional)', 'oria' ); ?></label>
		<textarea class="input" id="wkr-note-<?php echo (int) $oria_id; ?>" name="note" rows="3" maxlength="500"></textarea>
		<button class="btn btn--ghost btn--sm" type="submit"><?php esc_html_e( 'Send report', 'oria' ); ?></button>
	</form>
</details>
