<?php
/**
 * "Live music this weekend" -- three or four relaxed gigs from GigWave,
 * Oria's sister site for Perth live music. Renders nothing at all when the
 * feed is down, empty or out of date (see oria-core/includes/gigwave-feed.php).
 *
 * Args: placement -- 'this_weekend' | 'whats_on', sent with every click as
 * cta_placement so the two pages can be told apart in GA4.
 *
 * @package Oria
 */

declare(strict_types=1);

if ( ! function_exists( '\Oria\Core\GigWave\weekend' ) ) {
	return;
}
$oria_lm = \Oria\Core\GigWave\weekend( 4 );
if ( ! $oria_lm ) {
	return;
}
$oria_lm_place = sanitize_key( (string) ( $args['placement'] ?? 'this_weekend' ) );
$oria_lm_id    = 'lm-' . $oria_lm_place;
?>
<aside class="livemusic" aria-labelledby="<?php echo esc_attr( $oria_lm_id ); ?>">
	<div class="livemusic__head">
		<p class="livemusic__kicker"><?php esc_html_e( 'After the mat', 'oria' ); ?></p>
		<h2 class="livemusic__title" id="<?php echo esc_attr( $oria_lm_id ); ?>"><?php esc_html_e( 'Live music this weekend', 'oria' ); ?></h2>
		<p class="livemusic__lede"><?php esc_html_e( 'Not every way to unwind involves a mat. A few relaxed gigs around Perth, from GigWave, our sister site for live music.', 'oria' ); ?></p>
	</div>
	<ul class="livemusic__list">
		<?php foreach ( $oria_lm['gigs'] as $oria_g ) : ?>
			<li>
				<a class="livemusic__gig" href="<?php echo esc_url( (string) $oria_g['url'] ); ?>" data-oria-event="gigwave_click" data-oria-placement="<?php echo esc_attr( $oria_lm_place ); ?>">
					<span class="livemusic__when">
						<span class="livemusic__day"><?php echo esc_html( (string) $oria_g['day'] ); ?></span>
						<span class="livemusic__time"><?php echo esc_html( (string) $oria_g['time'] ); ?></span>
					</span>
					<span class="livemusic__what">
						<b class="livemusic__act"><?php echo esc_html( (string) $oria_g['act'] ); ?></b>
						<span class="livemusic__venue">
							<?php echo esc_html( (string) $oria_g['venue'] . ( '' !== (string) $oria_g['suburb'] ? ' · ' . (string) $oria_g['suburb'] : '' ) ); ?>
							<?php if ( ! empty( $oria_g['free'] ) ) : ?><span class="livemusic__free"><?php esc_html_e( 'Free', 'oria' ); ?></span><?php endif; ?>
						</span>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<a class="livemusic__all" href="<?php echo esc_url( (string) $oria_lm['url'] ); ?>" data-oria-event="gigwave_click" data-oria-placement="<?php echo esc_attr( $oria_lm_place . '_all' ); ?>"><?php esc_html_e( 'See all Perth gigs this weekend on GigWave', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
</aside>
