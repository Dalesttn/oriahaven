<?php
/**
 * The weekly timetable: seven day columns, each session with its time, its
 * name, who teaches it, and a coloured bar keyed to what kind of thing it
 * is — a class, a visiting practitioner's session, or a one-off event.
 *
 * Today's column is tinted and, on phones, the strip scrolls sideways and
 * starts on today: the question a timetable answers first is "what's on
 * today", so that is the column that greets you.
 *
 * Expects $args['sessions'] in Classes\timetable_for() shape.
 */

declare(strict_types=1);

$oria_flat = isset( $args['sessions'] ) && is_array( $args['sessions'] ) ? $args['sessions'] : array();
if ( ! $oria_flat ) {
	return;
}

$oria_bydom = array();
foreach ( $oria_flat as $oria_ss ) {
	$oria_bydom[ (int) $oria_ss['day'] ][] = $oria_ss;
}

$oria_today = (int) wp_date( 'N' );

/*
 * "6:00" not "06:00": the leading zero is data-entry convenience, not how
 * anyone says a time out loud.
 */
$oria_tfmt = static function ( string $oria_t ): string {
	return ltrim( $oria_t, '0' );
};
?>
<div class="week" data-week data-week-today="<?php echo (int) $oria_today; ?>">
	<?php for ( $oria_d = 1; $oria_d <= 7; $oria_d++ ) : ?>
		<div class="week__day<?php echo $oria_d === $oria_today ? ' week__day--today' : ''; ?>">
			<div class="week__head">
				<span class="week__name"><?php echo esc_html( \Oria\Core\Classes\full_label( $oria_d ) ); ?></span>
				<?php if ( $oria_d === $oria_today ) : ?>
					<span class="week__todaytag"><?php esc_html_e( 'Today', 'oria' ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( empty( $oria_bydom[ $oria_d ] ) ) : ?>
				<p class="week__none"><?php esc_html_e( 'No classes', 'oria' ); ?></p>
			<?php else : ?>
				<?php foreach ( $oria_bydom[ $oria_d ] as $oria_ss ) : ?>
					<div class="wsession wsession--<?php echo esc_attr( (string) $oria_ss['tone'] ); ?>">
						<span class="wsession__time"><?php echo esc_html( $oria_tfmt( (string) $oria_ss['time'] ) ); ?></span>
						<b class="wsession__title"><?php echo esc_html( (string) $oria_ss['title'] ); ?></b>
						<?php if ( '' !== $oria_ss['with'] ) : ?>
							<span class="wsession__with">
								<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="5" r="2.6"/><path d="M2.8 13.4c.9-2.5 2.8-3.8 5.2-3.8s4.3 1.3 5.2 3.8"/></svg>
								<?php echo esc_html( (string) $oria_ss['with'] ); ?>
							</span>
						<?php endif; ?>
						<?php if ( (int) $oria_ss['mins'] > 0 ) : ?>
							<span class="wsession__len">
								<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M8 4.6V8l2.3 1.6"/></svg>
								<?php
								$oria_m = (int) $oria_ss['mins'];
								if ( $oria_m >= 60 && 0 === $oria_m % 60 ) {
									/* translators: %d: hours */
									printf( esc_html( _n( '%d hour', '%d hours', (int) ( $oria_m / 60 ), 'oria' ) ), (int) ( $oria_m / 60 ) );
								} elseif ( $oria_m > 60 ) {
									/* translators: 1: hours 2: minutes */
									printf( esc_html__( '%1$dh %2$dm', 'oria' ), (int) ( $oria_m / 60 ), $oria_m % 60 );
								} else {
									/* translators: %d: minutes */
									printf( esc_html__( '%d minutes', 'oria' ), $oria_m );
								}
								?>
							</span>
						<?php endif; ?>
						<?php if ( ! empty( $oria_ss['free'] ) ) : ?>
							<span class="wsession__free"><?php esc_html_e( 'Free', 'oria' ); ?></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	<?php endfor; ?>
</div>
