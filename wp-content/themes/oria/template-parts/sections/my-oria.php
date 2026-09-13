<?php
/**
 * Section: Make Oria yours -- the home page's one mention of My Oria.
 *
 * Code-injected on the front page (page.php). A guest gets the pitch and
 * two buttons; a signed-in member gets their own numbers and a way back in.
 * The guest version is what the page cache holds, which is correct: a
 * signed-in member is never served from cache.
 */

declare(strict_types=1);

use Oria\Core\MyOria;
use function Oria\Theme\arrow;

$oria_in = is_user_logged_in() && function_exists( '\Oria\Core\Activity\may_act' ) && \Oria\Core\Activity\may_act( get_current_user_id() );
?>
<section class="section">
	<div class="wrap">
		<div class="myhome reveal">
			<div class="myhome__text">
				<span class="micro"><?php esc_html_e( 'My Oria', 'oria' ); ?></span>
				<?php if ( $oria_in ) : ?>
					<?php $oria_sum = MyOria\summary( get_current_user_id() ); ?>
					<h2 class="h2"><?php echo esc_html( sprintf( /* translators: %s: first name */ __( 'Welcome back, %s', 'oria' ), MyOria\first_name() ) ); ?></h2>
					<p class="myhome__facts">
						<span><span aria-hidden="true">&#9829;</span> <?php echo esc_html( sprintf( _n( '%s saved place', '%s saved places', $oria_sum['saved'], 'oria' ), number_format_i18n( $oria_sum['saved'] ) ) ); ?></span>
						<span><span aria-hidden="true">&#10003;</span> <?php echo esc_html( sprintf( _n( '%s experience', '%s experiences', $oria_sum['tried'], 'oria' ), number_format_i18n( $oria_sum['tried'] ) ) ); ?></span>
						<span><span aria-hidden="true">&#10022;</span> <?php echo esc_html( sprintf( _n( '%s badge', '%s badges', $oria_sum['badges'], 'oria' ), number_format_i18n( $oria_sum['badges'] ) ) ); ?></span>
					</p>
					<div class="myhome__acts">
						<a class="btn btn--dark" href="<?php echo esc_url( MyOria\url() ); ?>"><?php esc_html_e( 'Open My Oria', 'oria' ); ?><?php echo arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
					</div>
				<?php else : ?>
					<h2 class="h2"><?php esc_html_e( 'Make Oria yours', 'oria' ); ?></h2>
					<p class="lede"><?php esc_html_e( 'Save places you want to try, keep track of experiences you have explored and build your personal Wellness Passport.', 'oria' ); ?></p>
					<p class="myhome__facts">
						<span><span aria-hidden="true">&#9829;</span> <?php esc_html_e( 'Saved places', 'oria' ); ?></span>
						<span><span aria-hidden="true">&#10003;</span> <?php esc_html_e( 'Experiences tried', 'oria' ); ?></span>
						<span><span aria-hidden="true">&#10022;</span> <?php esc_html_e( 'Passport badges', 'oria' ); ?></span>
					</p>
					<div class="myhome__acts">
						<a class="btn btn--dark" href="<?php echo esc_url( MyOria\url( 'register' ) ); ?>"><?php esc_html_e( 'Create My Oria', 'oria' ); ?><?php echo arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
						<a class="btn btn--ghost" href="<?php echo esc_url( MyOria\url( 'login' ) ); ?>"><?php esc_html_e( 'Log in', 'oria' ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
