<?php
/**
 * Section: starting-soon strip.
 *
 * Fed by real upcoming events, soonest first, each linking to its event
 * page. The section's manual ACF rows remain as a fallback only for when
 * nothing is scheduled, so the strip never shows stale hand-typed entries
 * while real events exist.
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\tname;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();

/**
 * "Today 5.45pm", "Tomorrow 6.15pm", "Wed 7.00pm", then "12 Sep 7.00am".
 *
 * event_start is a naive local datetime (no timezone), so both sides are
 * treated the same way: strtotime() parses it as-if-UTC, current_time()
 * gives local "now" as-if-UTC, and gmdate() formats without re-applying
 * the site offset. wp_date() here would shift the clock twice.
 */
$oria_when = static function ( int $ts ): string {
	$now   = (int) current_time( 'timestamp' );
	$day   = gmdate( 'Y-m-d', $ts );
	$today = gmdate( 'Y-m-d', $now );
	$tmrw  = gmdate( 'Y-m-d', $now + DAY_IN_SECONDS );
	$time  = gmdate( 'g.ia', $ts );

	if ( $day === $today ) {
		return sprintf( __( 'Today %s', 'oria' ), $time );
	}
	if ( $day === $tmrw ) {
		return sprintf( __( 'Tomorrow %s', 'oria' ), $time );
	}
	if ( $ts - $now < 6 * DAY_IN_SECONDS ) {
		return gmdate( 'D', $ts ) . ' ' . $time;
	}
	return gmdate( 'j M', $ts ) . ' ' . $time;
};

$oria_events = get_posts(
	array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 6,
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array( 'key' => 'event_start', 'value' => current_time( 'Y-m-d H:i:s' ), 'compare' => '>=', 'type' => 'DATETIME' ),
		),
	)
);

$oria_items = array();
foreach ( $oria_events as $oria_ev ) {
	$oria_start = (string) get_field( 'event_start', $oria_ev->ID );
	$oria_ts    = $oria_start ? strtotime( $oria_start ) : false;
	if ( ! $oria_ts ) {
		continue;
	}

	// Where: the suburb term if the event has one, else its venue text.
	$oria_where = '';
	foreach ( wp_get_post_terms( $oria_ev->ID, 'area' ) as $oria_at ) {
		if ( $oria_at->parent ) {
			$oria_where = tname( $oria_at );
			break;
		}
	}
	if ( '' === $oria_where ) {
		$oria_where = (string) get_field( 'venue', $oria_ev->ID );
	}

	$oria_items[] = array(
		'time_label' => $oria_when( $oria_ts ),
		'name'       => \Oria\Theme\ptitle( $oria_ev ),
		'suburb'     => $oria_where,
		'url'        => get_permalink( $oria_ev ),
	);
}

// Nothing scheduled: fall back to the section's hand-entered rows.
if ( ! $oria_items ) {
	$oria_items = srows( $s, 'sessions' );
}
if ( ! $oria_items ) {
	return;
}

$oria_all = get_post_type_archive_link( 'event' ) ?: home_url( '/events/' );
?>
<section class="livestrip" aria-label="<?php esc_attr_e( 'Events starting soon', 'oria' ); ?>">
	<div class="livestrip__inner">
		<p class="livestrip__label"><span class="livedot" aria-hidden="true"></span><span class="micro"><?php esc_html_e( 'Upcoming events', 'oria' ); ?></span></p>
		<div class="livestrip__track" data-marquee>
			<?php foreach ( $oria_items as $oria_s ) : ?>
				<a class="livesession" href="<?php echo esc_url( (string) ( $oria_s['url'] ?? $oria_all ) ); ?>"><time><?php echo esc_html( (string) ( $oria_s['time_label'] ?? '' ) ); ?></time><b><?php echo esc_html( (string) ( $oria_s['name'] ?? '' ) ); ?></b><span><?php echo esc_html( (string) ( $oria_s['suburb'] ?? '' ) ); ?></span></a>
			<?php endforeach; ?>
			<a class="livesession livesession--more" href="<?php echo esc_url( $oria_all ); ?>"><?php esc_html_e( 'All events', 'oria' ); ?><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7h10M8 3l4 4-4 4"/></svg></a>
		</div>
	</div>
</section>
