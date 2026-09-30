<?php
/**
 * A shift card (brief section 35). Made to look unlike a job card on
 * purpose: a date block leads, urgency is a coloured band, and the call to
 * action is "I'm available", because a shift is answered, not applied for.
 *
 * $args: id
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_id    = (int) ( $args['id'] ?? 0 );
$oria_start = Work\shift_start_ts( $oria_id );
$oria_urg   = Work\urgency( $oria_id );
$oria_hours = Work\shift_hours( $oria_id );
$oria_pay   = Work\pay_label( $oria_id );
$oria_label = array(
	'today' => __( 'Today', 'oria' ),
	'48h'   => __( 'Within 48 hrs', 'oria' ),
	'week'  => __( 'This week', 'oria' ),
);
?>
<a class="wkshift wkshift--<?php echo esc_attr( $oria_urg ); ?>" href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>" data-wk-event="shift_card_click">
	<span class="wkshift__date" aria-hidden="true">
		<span class="wkshift__dow"><?php echo esc_html( $oria_start ? wp_date( 'D', $oria_start ) : '' ); ?></span>
		<span class="wkshift__day"><?php echo esc_html( $oria_start ? wp_date( 'j', $oria_start ) : '' ); ?></span>
		<span class="wkshift__mon"><?php echo esc_html( $oria_start ? wp_date( 'M', $oria_start ) : '' ); ?></span>
	</span>
	<span class="wkshift__body">
		<span class="wkshift__top">
			<span class="wkshift__kicker"><?php esc_html_e( 'Cover needed', 'oria' ); ?></span>
			<?php if ( isset( $oria_label[ $oria_urg ] ) ) : ?>
				<span class="wkbadge wkbadge--<?php echo 'today' === $oria_urg ? 'urgent' : 'soon'; ?>"><?php echo esc_html( $oria_label[ $oria_urg ] ); ?></span>
			<?php endif; ?>
		</span>
		<span class="wkcard__title"><?php echo esc_html( Work\profession_name( $oria_id ) ?: get_the_title( $oria_id ) ); ?></span>
		<span class="wkshift__when"><?php echo esc_html( Work\shift_when( $oria_id ) ); ?></span>
		<span class="wkcard__facts">
			<?php if ( $p = Work\place_label( $oria_id ) ) : ?><span class="wkchip"><?php echo esc_html( $p ); ?></span><?php endif; ?>
			<?php if ( $oria_hours ) : ?><span class="wkchip"><?php echo esc_html( sprintf( _n( '%s hour', '%s hours', (int) ceil( $oria_hours ), 'oria' ), number_format_i18n( $oria_hours, $oria_hours == (int) $oria_hours ? 0 : 1 ) ) ); ?></span><?php endif; // phpcs:ignore Universal.Operators.StrictComparisons ?>
			<?php if ( '' !== $oria_pay ) : ?><span class="wkchip wkchip--pay"><?php echo esc_html( $oria_pay ); ?></span><?php endif; ?>
		</span>
		<span class="wkcard__foot">
			<span><?php echo esc_html( Work\employer_name( $oria_id ) ); ?></span>
			<span class="wkcard__cta"><?php esc_html_e( "I'm available", 'oria' ); ?> <span aria-hidden="true">&rarr;</span></span>
		</span>
	</span>
</a>
