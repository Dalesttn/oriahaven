<?php
/**
 * The region bar: which city you are browsing, and how to change it.
 *
 * Real links rather than a scripted <select>, so it works with scripting
 * off, middle-clicks, and tells a crawler that these are separate places.
 * The count beside each one is that city's own, which is the honest way to
 * say that Margaret River is seven listings and Perth is 377 — and stops
 * the switch feeling broken when a small city shows a short list.
 *
 * Where each option points is worked out by Explore\switch_url(): it keeps
 * as much of the current address as the target city can honour and gives up
 * one segment at a time, so switching from a Perth sauna page lands on the
 * southern sauna page when there is one, its spa category when there is
 * not, and the city's overview when it holds nothing at all.
 *
 * @package Oria
 */

declare(strict_types=1);

if ( ! function_exists( '\Oria\Core\Explore\city_options' ) ) {
	return;
}

$oria_cities = \Oria\Core\Explore\city_options();

// One city is not a choice.
if ( count( $oria_cities ) < 2 ) {
	return;
}
?>
<div class="regionbar">
	<div class="wrap regionbar__inner">
		<span class="regionbar__label" id="regionbar-label"><?php esc_html_e( 'Region', 'oria' ); ?></span>
		<nav class="regionbar__opts" aria-labelledby="regionbar-label">
			<?php foreach ( $oria_cities as $oria_c ) : ?>
				<a
					class="regionbar__opt<?php echo $oria_c['current'] ? ' is-on' : ''; ?>"
					href="<?php echo esc_url( $oria_c['url'] ); ?>"
					<?php echo $oria_c['current'] ? ' aria-current="true"' : ''; ?>>
					<?php echo esc_html( $oria_c['name'] ); ?>
					<span class="regionbar__n"><?php echo esc_html( number_format_i18n( $oria_c['count'] ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>
</div>
