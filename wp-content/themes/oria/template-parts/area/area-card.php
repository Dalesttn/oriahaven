<?php
/**
 * The compact neighbourhood card.
 *
 * A window into a place rather than an advertisement for one: the area's
 * own photograph, what is actually there, and one way in. Used in the
 * listing sidebar and on event pages, below the things people came for.
 *
 * Renders nothing when the area cannot be resolved or is too thin to have
 * a page worth visiting -- that decision belongs to AreaContext, not here.
 *
 * @var array $args {
 *     @type array  $area    From \Oria\Core\AreaContext\for_post().
 *     @type string $eyebrow Small label above the name.
 *     @type string $line    One sentence. Falls back to the counts.
 *     @type string $cta     Link text.
 *     @type string $source  Where this card is, for analytics.
 * }
 */

declare(strict_types=1);

$oria_area = (array) ( $args['area'] ?? array() );
if ( empty( $oria_area['url'] ) || empty( $oria_area['name'] ) ) {
	return;
}

$oria_name = (string) $oria_area['name'];
$oria_term = $oria_area['term'] ?? null;
$oria_img  = $oria_term instanceof WP_Term ? \Oria\Core\AreaContext\image( $oria_term ) : array( 'src' => '', 'alt' => '' );

/*
 * The counts sentence is built from stored data, never asserted: a suburb
 * with no upcoming event simply does not mention events.
 */
$oria_bits = array();
if ( (int) $oria_area['places'] > 0 ) {
	/* translators: %d: number of places */
	$oria_bits[] = sprintf( _n( '%d hand-checked place', '%d hand-checked places', (int) $oria_area['places'], 'oria' ), (int) $oria_area['places'] );
}
if ( (int) $oria_area['practices'] > 1 ) {
	/* translators: %d: number of kinds of practice */
	$oria_bits[] = sprintf( _n( '%d kind of practice', '%d kinds of practice', (int) $oria_area['practices'], 'oria' ), (int) $oria_area['practices'] );
}
if ( (int) $oria_area['events'] > 0 ) {
	/* translators: %d: number of events */
	$oria_bits[] = sprintf( _n( '%d event coming up', '%d events coming up', (int) $oria_area['events'], 'oria' ), (int) $oria_area['events'] );
}

$oria_line = trim( (string) ( $args['line'] ?? '' ) );
if ( '' === $oria_line && $oria_term instanceof WP_Term ) {
	$oria_line = \Oria\Core\AreaContext\tagline( $oria_term );
}

$oria_cta = (string) ( $args['cta'] ?? sprintf( /* translators: %s: suburb */ __( 'Explore %s', 'oria' ), $oria_name ) );
?>
<a class="areacard<?php echo '' === $oria_img['src'] ? ' areacard--plain' : ''; ?>"
	href="<?php echo esc_url( (string) $oria_area['url'] ); ?>"
	data-area-promo="<?php echo esc_attr( (string) ( $args['source'] ?? 'card' ) ); ?>"
	data-area-slug="<?php echo esc_attr( (string) ( $oria_area['slug'] ?? '' ) ); ?>"
	aria-label="<?php echo esc_attr( sprintf( /* translators: %s: suburb */ __( 'Explore wellness in %s', 'oria' ), $oria_name ) ); ?>">

	<?php if ( '' !== $oria_img['src'] ) : ?>
		<span class="areacard__media" aria-hidden="true">
			<img src="<?php echo esc_url( $oria_img['src'] ); ?>" alt="" width="480" height="270" loading="lazy" decoding="async">
		</span>
	<?php endif; ?>

	<span class="areacard__body">
		<span class="areacard__eyebrow"><?php echo esc_html( (string) ( $args['eyebrow'] ?? __( 'Neighbourhood guide', 'oria' ) ) ); ?></span>
		<b class="areacard__name"><?php echo esc_html( sprintf( /* translators: %s: suburb */ __( 'Wellness in %s', 'oria' ), $oria_name ) ); ?></b>
		<?php if ( $oria_bits ) : ?>
			<span class="areacard__counts"><?php echo esc_html( implode( ' · ', $oria_bits ) ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $oria_line ) : ?>
			<span class="areacard__line"><?php echo esc_html( $oria_line ); ?></span>
		<?php endif; ?>
		<span class="areacard__cta"><?php echo esc_html( $oria_cta ); ?> <span aria-hidden="true">&rarr;</span></span>
	</span>
</a>
