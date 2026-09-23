<?php
/**
 * The credit badge.
 *
 * A product token, not a sale badge: no red, no strike-through, no "was".
 * It is the unit the membership is spent in, so it should read like a price
 * tag on something good rather than a discount sticker.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_n = (int) ( $args['credits'] ?? 0 );
if ( $oria_n < 1 ) {
	return;
}
?>
<span class="pcredit">
	<b><?php echo esc_html( number_format_i18n( $oria_n ) ); ?></b>
	<span><?php echo esc_html( _n( 'credit', 'credits', $oria_n, 'oria' ) ); ?></span>
</span>
