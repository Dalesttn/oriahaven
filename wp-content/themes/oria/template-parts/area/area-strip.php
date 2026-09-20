<?php
/**
 * The inline neighbourhood strip.
 *
 * One line and one link, for places where a card would interrupt: under the
 * filters on What's On, above the results on a category-and-suburb page,
 * and in an empty state where it turns a dead end into somewhere to go.
 *
 * @var array $args {
 *     @type array  $area   From \Oria\Core\AreaContext.
 *     @type string $lead   The sentence. Falls back to a counted one.
 *     @type string $eyebrow Small label. Only default when no lead is given.
 *     @type string $cta    Link text.
 *     @type string $source Where this strip is, for analytics.
 * }
 */

declare(strict_types=1);

$oria_area = (array) ( $args['area'] ?? array() );
if ( empty( $oria_area['url'] ) || empty( $oria_area['name'] ) ) {
	return;
}

$oria_name = (string) $oria_area['name'];
$oria_lead = trim( (string) ( $args['lead'] ?? '' ) );

/*
 * The eyebrow names the area when the sentence does not. A caller that
 * writes its own lead has already said where we are, and "Exploring
 * Fremantle / You are exploring beauty in Fremantle" says it twice.
 */
$oria_eyebrow = array_key_exists( 'eyebrow', $args )
	? trim( (string) $args['eyebrow'] )
	: ( '' === $oria_lead ? sprintf( /* translators: %s: suburb */ __( 'Exploring %s', 'oria' ), $oria_name ) : '' );

if ( '' === $oria_lead ) {
	$oria_lead = sprintf(
		/* translators: 1: suburb, 2: number of places */
		_n(
			'%2$d wellness place in %1$s, with local day plans and how to get around.',
			'%2$d wellness places in %1$s, with local day plans and how to get around.',
			(int) $oria_area['places'],
			'oria'
		),
		$oria_name,
		(int) $oria_area['places']
	);
}
?>
<div class="areastrip">
	<p class="areastrip__text">
		<?php if ( '' !== $oria_eyebrow ) : ?>
			<span class="areastrip__eyebrow"><?php echo esc_html( $oria_eyebrow ); ?></span>
		<?php endif; ?>
		<span><?php echo esc_html( $oria_lead ); ?></span>
	</p>
	<a class="areastrip__cta btn btn--sm"
		href="<?php echo esc_url( (string) $oria_area['url'] ); ?>"
		data-area-promo="<?php echo esc_attr( (string) ( $args['source'] ?? 'strip' ) ); ?>"
		data-area-slug="<?php echo esc_attr( (string) ( $oria_area['slug'] ?? '' ) ); ?>">
		<?php echo esc_html( (string) ( $args['cta'] ?? sprintf( /* translators: %s: suburb */ __( 'Explore %s', 'oria' ), $oria_name ) ) ); ?>
	</a>
</div>
