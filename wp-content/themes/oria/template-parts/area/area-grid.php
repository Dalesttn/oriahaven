<?php
/**
 * A short row of neighbourhood cards.
 *
 * For the places where somebody has not chosen anything yet -- the home
 * page, the top of a hub -- and a picture of a suburb is a better
 * invitation than a list of its name.
 *
 * Renders nothing when fewer than two areas are worth showing: one lone
 * card in a section headed "one neighbourhood at a time" reads as a bug.
 *
 * @var array $args {
 *     @type array  $areas   Rows from \Oria\Core\AreaContext.
 *     @type string $eyebrow Small label above the heading.
 *     @type string $heading The section heading.
 *     @type string $line    One sentence under it.
 *     @type string $id      Heading id, for aria-labelledby.
 *     @type string $source  Where this grid is, for analytics.
 *     @type string $more    Optional "see them all" link.
 *     @type string $more_label Its text.
 * }
 */

declare(strict_types=1);

$oria_areas = array_values( (array) ( $args['areas'] ?? array() ) );
if ( count( $oria_areas ) < 2 ) {
	return;
}

$oria_id  = (string) ( $args['id'] ?? 'area-grid-title' );
$oria_src = (string) ( $args['source'] ?? 'area-grid' );
?>
<section class="wrap section areagrid" aria-labelledby="<?php echo esc_attr( $oria_id ); ?>">
	<header class="areagrid__head">
		<div>
			<?php if ( ! empty( $args['eyebrow'] ) ) : ?>
				<p class="micro"><?php echo esc_html( (string) $args['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="h1" id="<?php echo esc_attr( $oria_id ); ?>"><?php echo esc_html( (string) ( $args['heading'] ?? __( 'Explore Perth one neighbourhood at a time', 'oria' ) ) ); ?></h2>
			<?php if ( ! empty( $args['line'] ) ) : ?>
				<p class="areagrid__line"><?php echo esc_html( (string) $args['line'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $args['more'] ) ) : ?>
			<a class="btn btn--sm areagrid__more" href="<?php echo esc_url( (string) $args['more'] ); ?>">
				<?php echo esc_html( (string) ( $args['more_label'] ?? __( 'All neighbourhoods', 'oria' ) ) ); ?> <span aria-hidden="true">&rarr;</span>
			</a>
		<?php endif; ?>
	</header>

	<div class="areagrid__cards">
		<?php
		foreach ( $oria_areas as $oria_a ) {
			get_template_part( 'template-parts/area/area-card', null, array( 'area' => $oria_a, 'source' => $oria_src ) );
		}
		?>
	</div>
</section>
