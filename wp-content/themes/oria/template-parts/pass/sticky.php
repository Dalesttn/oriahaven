<?php
/**
 * The sticky bar, phones only, and the only one on the page.
 *
 * It appears once the hero's button has scrolled away and hides again
 * while the form is on screen, so it never sits on top of the thing it
 * points to. pass-landing.js reserves room for it at the foot of the page.
 * Hidden entirely after a successful signup: there is nothing left to ask.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( 'done' === ( $args['state'] ?? '' ) ) {
	return;
}

$oria_live = ! empty( $args['live'] );
?>
<div class="oria-pl__sticky" data-pl-sticky hidden>
	<span class="oria-pl__sticky-what">
		<b><?php esc_html_e( 'Oria Pass', 'oria' ); ?></b>
		<span>
			<?php
			if ( $oria_live ) {
				echo esc_html( sprintf( '%s AUD / %s', (string) ( $args['price'] ?? '' ), (string) ( $args['period'] ?? 'month' ) ) );
			} else {
				/* translators: %s: city */
				echo esc_html( sprintf( __( 'Coming to %s', 'oria' ), (string) ( $args['city'] ?? 'Perth' ) ) );
			}
			?>
		</span>
	</span>
	<a class="oria-pl__sticky-go" href="#pass-join" data-oria-event="oria_pass_join_click">
		<?php echo esc_html( (string) ( $args['cta'] ?? __( 'Join the waitlist', 'oria' ) ) ); ?>
	</a>
</div>
