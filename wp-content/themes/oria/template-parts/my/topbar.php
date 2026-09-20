<?php
/**
 * The app's top bar.
 *
 * A title and the way back to the public site, and nothing else. There is
 * no global search here because there is nothing yet to search across an
 * account of four saved places, and a box that finds nothing is worse
 * than no box.
 *
 * $args: view
 */

declare(strict_types=1);

use Oria\Core\MyOria;

$oria_view = (string) ( $args['view'] ?? '' );
$oria_name = MyOria\first_name( get_current_user_id() );

/* Perth time, because that is where the member is. */
$oria_hour = (int) current_time( 'G' );
$oria_when = $oria_hour < 12 ? __( 'Good morning', 'oria' ) : ( $oria_hour < 17 ? __( 'Good afternoon', 'oria' ) : __( 'Good evening', 'oria' ) );
?>
<header class="mytop">
	<div class="mytop__title">
		<?php if ( '' === $oria_view ) : ?>
			<p class="mytop__eyebrow"><?php echo esc_html( $oria_when ); ?></p>
			<p class="mytop__page"><?php echo esc_html( $oria_name ?: __( 'My Oria', 'oria' ) ); ?></p>
		<?php else : ?>
			<p class="mytop__eyebrow"><?php esc_html_e( 'My Oria', 'oria' ); ?></p>
			<p class="mytop__page"><?php echo esc_html( MyOria\heading() ); ?></p>
		<?php endif; ?>
	</div>

	<a class="mytop__out" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php esc_html_e( 'Explore Oria Haven', 'oria' ); ?> <span aria-hidden="true">&#8599;</span>
	</a>
</header>
