<?php
/**
 * Every neighbourhood with a guide, grouped by region and filterable.
 *
 * The hub's way in by place, beside its ways in by practice and by
 * feeling. The whole list is in the HTML -- the field only hides rows, so
 * a visitor without JavaScript still gets every link, and a crawler sees
 * every one of them too.
 *
 * @var array $args {
 *     @type array  $areas   Rows from \Oria\Core\AreaContext\catalogue().
 *     @type string $heading The section heading.
 *     @type string $id      Heading id, for aria-labelledby.
 *     @type string $map     Optional link to the map.
 *     @type string $source  Where this block is, for analytics.
 * }
 */

declare(strict_types=1);

$oria_areas = (array) ( $args['areas'] ?? array() );
if ( count( $oria_areas ) < 4 ) {
	return;
}

// Grouped in the order the catalogue arrives, which is richest first, so
// each region's own list opens with the suburb most worth reading.
$oria_groups = array();
foreach ( $oria_areas as $oria_a ) {
	$oria_key                   = (string) ( $oria_a['region'] ?: __( 'Elsewhere', 'oria' ) );
	$oria_groups[ $oria_key ][] = $oria_a;
}
ksort( $oria_groups );

$oria_id  = (string) ( $args['id'] ?? 'area-browse-title' );
$oria_src = (string) ( $args['source'] ?? 'area-browse' );
?>
<section class="areabrowse" aria-labelledby="<?php echo esc_attr( $oria_id ); ?>" data-area-browse>
	<div class="areabrowse__head">
		<h2 class="h4 xc-mesh__title" id="<?php echo esc_attr( $oria_id ); ?>"><?php echo esc_html( (string) ( $args['heading'] ?? __( 'Explore by neighbourhood', 'oria' ) ) ); ?></h2>
		<p class="areabrowse__find">
			<label class="xp-vh" for="<?php echo esc_attr( $oria_id ); ?>-q"><?php esc_html_e( 'Find a neighbourhood', 'oria' ); ?></label>
			<input class="input input--sm" id="<?php echo esc_attr( $oria_id ); ?>-q" type="search"
				placeholder="<?php esc_attr_e( 'Find a neighbourhood…', 'oria' ); ?>"
				autocomplete="off" data-area-browse-q>
		</p>
	</div>

	<?php foreach ( $oria_groups as $oria_region => $oria_rows ) : ?>
		<div class="areabrowse__group" data-area-browse-group>
			<p class="areabrowse__region"><?php echo esc_html( $oria_region ); ?></p>
			<div class="chips xc-mesh__chips">
				<?php foreach ( $oria_rows as $oria_a ) : ?>
					<a class="pill" href="<?php echo esc_url( (string) $oria_a['url'] ); ?>"
						data-area-browse-item="<?php echo esc_attr( strtolower( (string) $oria_a['name'] ) ); ?>"
						data-area-promo="<?php echo esc_attr( $oria_src ); ?>"
						data-area-slug="<?php echo esc_attr( (string) $oria_a['slug'] ); ?>">
						<?php echo esc_html( (string) $oria_a['name'] ); ?>
						<span class="areabrowse__n"><?php echo esc_html( (string) (int) $oria_a['places'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>

	<p class="areabrowse__none" data-area-browse-none hidden><?php esc_html_e( 'No neighbourhood by that name yet.', 'oria' ); ?></p>

	<?php if ( ! empty( $args['map'] ) ) : ?>
		<p class="areabrowse__map">
			<a class="xp-link" href="<?php echo esc_url( (string) $args['map'] ); ?>"><?php esc_html_e( 'Open every area on the wellness map', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
		</p>
	<?php endif; ?>
</section>
