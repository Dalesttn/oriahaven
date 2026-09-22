<?php
/**
 * The sticky bar, phones only.
 *
 * It appears once the hero has scrolled away, so it never covers the
 * button it duplicates. Hidden entirely on the thank-you state: there is
 * nothing left to ask for.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( 'done' === ( $args['state'] ?? '' ) ) {
	return;
}

$oria_live = ! empty( $args['live'] );
?>
<div class="pass-sticky" data-pass-sticky hidden>
	<span class="pass-sticky__what">
		<?php if ( $oria_live ) : ?>
			<b><?php esc_html_e( 'Oria Pass', 'oria' ); ?></b>
			<span><?php echo esc_html( sprintf( '%s / %s', (string) ( $args['price'] ?? '$99' ), (string) ( $args['period'] ?? 'month' ) ) ); ?></span>
		<?php else : ?>
			<b><?php esc_html_e( 'Oria Pass', 'oria' ); ?></b>
			<span><?php esc_html_e( 'Opening in Perth soon', 'oria' ); ?></span>
		<?php endif; ?>
	</span>
	<a class="pass-sticky__go" href="#pass-join" data-oria-event="oria_pass_join_click">
		<?php echo esc_html( (string) ( $args['cta'] ?? __( 'Join the waitlist', 'oria' ) ) ); ?>
	</a>
</div>
