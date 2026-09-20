<?php
/**
 * Password reset, both halves: ask for the link, then set the password
 * once the link is followed. The same page, told apart by the key in the
 * URL. Also how a Google or review-link member sets a first password.
 */

declare(strict_types=1);

use Oria\Core\MyOria;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$oria_key   = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
$oria_login = isset( $_GET['login'] ) ? (string) wp_unslash( $_GET['login'] ) : '';
// phpcs:enable
$oria_set = '' !== $oria_key && '' !== $oria_login;
?>
<div class="authcard">
	<div class="authcard__head">
		<span class="micro"><?php esc_html_e( 'My Oria', 'oria' ); ?></span>
		<h1 class="h1"><?php echo $oria_set ? esc_html__( 'Choose a new password', 'oria' ) : esc_html__( 'Reset your password', 'oria' ); ?></h1>
		<p class="lede"><?php echo $oria_set ? esc_html__( 'Pick something you will remember. You will be signed in straight after.', 'oria' ) : esc_html__( 'Tell us the email on your account and we will send a link. If you joined with Google or through a review, this is also how you set a password for the first time.', 'oria' ); ?></p>
	</div>
	<?php if ( $oria_set ) : ?>
		<form class="myform myform--auth" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_my_reset_set">
			<input type="hidden" name="key" value="<?php echo esc_attr( $oria_key ); ?>">
			<input type="hidden" name="login" value="<?php echo esc_attr( $oria_login ); ?>">
			<?php wp_nonce_field( 'oria_my_reset_set', 'oria_my_nonce' ); ?>
			<label class="field">
				<span class="field__label"><?php esc_html_e( 'New password', 'oria' ); ?></span>
				<input class="input" type="password" name="password" required minlength="8" autocomplete="new-password">
			</label>
			<label class="field">
				<span class="field__label"><?php esc_html_e( 'Repeat it', 'oria' ); ?></span>
				<input class="input" type="password" name="password2" required minlength="8" autocomplete="new-password">
			</label>
			<button class="btn btn--dark btn--block" type="submit"><?php esc_html_e( 'Set password and log in', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</form>
	<?php else : ?>
		<form class="myform myform--auth" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_my_reset_request">
			<?php wp_nonce_field( 'oria_my_reset', 'oria_my_nonce' ); ?>
			<label class="field">
				<span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
				<input class="input" type="email" name="email" required autocomplete="email" inputmode="email">
			</label>
			<button class="btn btn--dark btn--block" type="submit"><?php esc_html_e( 'Send me a reset link', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<p class="authcard__links"><a href="<?php echo esc_url( MyOria\url( 'login' ) ); ?>"><?php esc_html_e( 'Back to log in', 'oria' ); ?></a></p>
		</form>
	<?php endif; ?>
</div>
