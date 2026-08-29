<?php
/**
 * "What are you after?" — the discovery chips on the directory page.
 *
 * Twelve colour-coded wants, each a preset over the specialty filters the
 * page already has: clicking one ticks its specialties and the existing
 * engine does the rest (app.js initGoodFor). The chips never tag a listing
 * and never promise an outcome — the label is the visitor's want, the
 * mapping is to experience types, and the fine print under the row says
 * exactly what a chip does so nobody mistakes it for a recommendation.
 */

declare(strict_types=1);

$oria_chips = function_exists( '\Oria\Core\GoodFor\labels' ) ? \Oria\Core\GoodFor\labels() : array();

if ( ! $oria_chips ) {
	return;
}

/*
 * Icons are generated illustrations (Higgsfield z_image, processed to
 * white-on-transparent 64px webp) in assets/img/goodfor/{slug}.webp —
 * regenerate via the same pipeline if a label is ever added.
 */
?>
<div class="goodfor" data-goodfor>
	<h2 class="h4 goodfor__head"><?php esc_html_e( 'What are you after?', 'oria' ); ?></h2>
	<div class="goodfor__row" role="group" aria-label="<?php esc_attr_e( 'Browse by what you want from the visit', 'oria' ); ?>">
		<?php foreach ( $oria_chips as $oria_c ) : ?>
			<button
				class="goodfor__chip"
				type="button"
				style="--gf:<?php echo esc_attr( $oria_c['color'] ); ?>"
				data-goodfor-chip
				data-specs="<?php echo esc_attr( (string) wp_json_encode( $oria_c['specs'] ) ); ?>"
				title="<?php echo esc_attr( $oria_c['line'] ); ?>"
				aria-pressed="false"
			>
				<img class="goodfor__icon" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/goodfor/' . $oria_c['slug'] . '.webp' ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
				<span><?php echo esc_html( $oria_c['label'] ); ?></span>
			</button>
		<?php endforeach; ?>
	</div>
	<p class="hint goodfor__hint"><?php esc_html_e( 'Each one picks a set of experience types below — the same filters, chosen for you. Un-tick anything that isn\'t what you meant.', 'oria' ); ?></p>
</div>
