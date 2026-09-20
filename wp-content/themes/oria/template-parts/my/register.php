<?php
/**
 * Create My Oria. Three fields and a tick; the interests come afterwards,
 * on the dashboard, where they can be skipped.
 */

declare(strict_types=1);

use Oria\Core\MyOria;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_to = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( (string) wp_unslash( $_GET['redirect_to'] ), '' ) : '';
$oria_g  = function_exists( '\Oria\Core\GoogleAuth\available' ) && \Oria\Core\GoogleAuth\available();
?>
<section class="wrap my my--last">
	<div class="myauth">
		<div class="myauth__text">
			<span class="micro"><?php esc_html_e( 'Free, and yours', 'oria' ); ?></span>
			<h1 class="h1"><?php esc_html_e( 'Create My Oria', 'oria' ); ?></h1>
			<p class="lede"><?php esc_html_e( 'Save places you want to try, keep track of the ones you have explored, and build your Wellness Passport as you go.', 'oria' ); ?></p>
			<ul class="myauth__list">
				<li><span aria-hidden="true">&#9829;</span> <?php esc_html_e( 'Save any practice, on any device', 'oria' ); ?></li>
				<li><span aria-hidden="true">&#10003;</span> <?php esc_html_e( 'Mark the ones you have tried', 'oria' ); ?></li>
				<li><span aria-hidden="true">&#10022;</span> <?php esc_html_e( 'Earn passport badges for exploring', 'oria' ); ?></li>
			</ul>
		</div>
		<form class="myform myform--auth" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_my_register">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $oria_to ); ?>">
			<?php wp_nonce_field( 'oria_my_register', 'oria_my_nonce' ); ?>
			<div class="field field--trap" aria-hidden="true"><label>Website<input type="text" name="oria_website" tabindex="-1" autocomplete="off"></label></div>
			<label class="field">
				<span class="field__label"><?php esc_html_e( 'First name', 'oria' ); ?></span>
				<input class="input" type="text" name="first_name" required autocomplete="given-name" maxlength="40">
			</label>
			<label class="field">
				<span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
				<input class="input" type="email" name="email" required autocomplete="email" inputmode="email">
			</label>
			<label class="field">
				<span class="field__label"><?php esc_html_e( 'Password', 'oria' ); ?></span>
				<input class="input" type="password" name="password" required minlength="8" autocomplete="new-password">
				<span class="hint"><?php esc_html_e( 'At least 8 characters.', 'oria' ); ?></span>
			</label>
			<label class="mycheck">
				<input type="checkbox" name="agree" value="1" required>
				<span>
					<?php
					printf(
						/* translators: 1: privacy policy link, 2: terms link */
						esc_html__( 'I agree to the %1$s and %2$s.', 'oria' ),
						'<a href="' . esc_url( home_url( '/privacy-policy/' ) ) . '">' . esc_html__( 'privacy policy', 'oria' ) . '</a>',
						'<a href="' . esc_url( home_url( '/terms/' ) ) . '">' . esc_html__( 'terms', 'oria' ) . '</a>'
					);
					?>
				</span>
			</label>
			<button class="btn btn--dark btn--block" type="submit"><?php esc_html_e( 'Create My Oria', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<?php if ( $oria_g ) : ?>
				<div class="myauth__or"><span><?php esc_html_e( 'or', 'oria' ); ?></span></div>
				<a class="btn btn--ghost btn--block gbtn" href="<?php echo esc_url( \Oria\Core\GoogleAuth\start_url( $oria_to ?: MyOria\url() ) ); ?>" rel="nofollow">
					<svg class="gbtn__mark" viewBox="0 0 18 18" aria-hidden="true" focusable="false"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.83.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.9 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/></svg>
					<?php esc_html_e( 'Continue with Google', 'oria' ); ?>
				</a>
			<?php endif; ?>
			<p class="myauth__links">
				<?php esc_html_e( 'Already have one?', 'oria' ); ?>
				<a href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( $oria_to ), MyOria\url( 'login' ) ) ); ?>"><?php esc_html_e( 'Log in', 'oria' ); ?></a>
			</p>
		</form>
	</div>
</section>
