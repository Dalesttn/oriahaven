<?php
/**
 * Log in. Email and password, or Google. redirect_to carries the page that
 * sent them here, so a Save on a listing ends back on that listing.
 */

declare(strict_types=1);

use Oria\Core\MyOria;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_to = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( (string) wp_unslash( $_GET['redirect_to'] ), '' ) : '';
$oria_g  = function_exists( '\Oria\Core\GoogleAuth\available' ) && \Oria\Core\GoogleAuth\available();
?>
<div class="authcard">
	<div class="authcard__head">
		<span class="micro"><?php esc_html_e( 'My Oria', 'oria' ); ?></span>
		<h1 class="h1"><?php esc_html_e( 'Welcome back', 'oria' ); ?></h1>
		<p class="lede"><?php esc_html_e( 'Your saved places and Wellness Passport are where you left them.', 'oria' ); ?></p>
	</div>
	<form class="myform myform--auth" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="oria_my_login">
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $oria_to ); ?>">
		<?php wp_nonce_field( 'oria_my_login', 'oria_my_nonce' ); ?>
		<label class="field">
			<span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
			<input class="input" type="email" name="email" required autocomplete="email" inputmode="email">
		</label>
		<label class="field">
			<span class="field__label"><?php esc_html_e( 'Password', 'oria' ); ?></span>
			<input class="input" type="password" name="password" required autocomplete="current-password">
		</label>
		<button class="btn btn--dark btn--block" type="submit"><?php esc_html_e( 'Log in', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		<?php if ( $oria_g ) : ?>
			<div class="authcard__or"><span><?php esc_html_e( 'or', 'oria' ); ?></span></div>
			<a class="btn btn--ghost btn--block gbtn" href="<?php echo esc_url( \Oria\Core\GoogleAuth\start_url( $oria_to ?: MyOria\url() ) ); ?>" rel="nofollow">
				<svg class="gbtn__mark" viewBox="0 0 18 18" aria-hidden="true" focusable="false"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.83.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.9 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/></svg>
				<?php esc_html_e( 'Continue with Google', 'oria' ); ?>
			</a>
		<?php endif; ?>
		<p class="authcard__links">
			<a href="<?php echo esc_url( MyOria\url( 'reset' ) ); ?>"><?php esc_html_e( 'Forgotten your password?', 'oria' ); ?></a>
			<span aria-hidden="true">·</span>
			<a href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( $oria_to ), MyOria\url( 'register' ) ) ); ?>"><?php esc_html_e( 'Create My Oria', 'oria' ); ?></a>
		</p>
	</form>
</div>
