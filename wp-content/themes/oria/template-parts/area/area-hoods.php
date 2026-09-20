<?php
/**
 * Explore by neighbourhood: a mosaic, a field, and everything else behind
 * a drawer.
 *
 * A wall of forty pills is a database; five photographs are a city. The
 * five are chosen by hand in the theme's area-guides.json, because the
 * five longest lists would be four of Perth Central and tell a visitor
 * nothing. Everything not in the mosaic is still one click away and
 * still in the HTML -- the drawer is a <details>, so it opens with no
 * script at all, and every guide stays crawlable.
 *
 * @var array $args {
 *     @type array  $areas    Rows from \Oria\Core\AreaContext\catalogue().
 *     @type array  $featured Rows for the mosaic, in order. First is the lead.
 *     @type string $heading  The section heading.
 *     @type string $lede     One sentence under it.
 *     @type string $id       Heading id, for aria-labelledby.
 *     @type string $source   Where this block is, for analytics.
 * }
 */

declare(strict_types=1);

$oria_areas    = (array) ( $args['areas'] ?? array() );
$oria_featured = array_values( (array) ( $args['featured'] ?? array() ) );
if ( count( $oria_featured ) < 2 ) {
	return;
}

$oria_id  = (string) ( $args['id'] ?? 'hoods-title' );
$oria_src = (string) ( $args['source'] ?? 'hub-hoods' );

// Grouped for the drawer, in the order the catalogue arrives -- richest
// first, so each region opens with the suburb most worth reading.
$oria_groups = array();
foreach ( $oria_areas as $oria_a ) {
	$oria_key                   = (string) ( $oria_a['region'] ?: __( 'Elsewhere', 'oria' ) );
	$oria_groups[ $oria_key ][] = $oria_a;
}
ksort( $oria_groups );

/**
 * One card. The whole thing is the target; the arrow is decoration.
 *
 * @param array  $area   A catalogue row.
 * @param string $source Analytics position.
 * @param bool   $lead   The large one.
 */
$oria_card = static function ( array $area, string $source, bool $lead = false ) {
	if ( empty( $area['url'] ) || empty( $area['name'] ) ) {
		return;
	}
	$term = $area['term'] ?? null;
	$img  = $term instanceof WP_Term && function_exists( '\Oria\Core\AreaContext\image' )
		? \Oria\Core\AreaContext\image( $term )
		: array( 'src' => '', 'alt' => '' );

	// The photograph the area page uses. Where a suburb has none of its
	// own, that chain gives its region's -- the right part of town, never
	// a stock picture of somewhere else wearing this place's name.
	$line = $term instanceof WP_Term && function_exists( '\Oria\Core\AreaContext\tagline' )
		? \Oria\Core\AreaContext\tagline( $term )
		: '';
	?>
	<a class="hood<?php echo $lead ? ' hood--lead' : ''; ?><?php echo '' === $img['src'] ? ' hood--bare' : ''; ?>"
		href="<?php echo esc_url( (string) $area['url'] ); ?>"
		data-area-promo="<?php echo esc_attr( $source ); ?>"
		data-area-slug="<?php echo esc_attr( (string) ( $area['slug'] ?? '' ) ); ?>">
		<?php if ( '' !== $img['src'] ) : ?>
			<img class="hood__img" src="<?php echo esc_url( $img['src'] ); ?>" alt=""
				width="<?php echo $lead ? 960 : 640; ?>" height="<?php echo $lead ? 720 : 480; ?>"
				loading="lazy" decoding="async">
		<?php endif; ?>
		<span class="hood__veil" aria-hidden="true"></span>
		<span class="hood__body">
			<b class="hood__name"><?php echo esc_html( (string) $area['name'] ); ?></b>
			<?php if ( $lead && '' !== $line ) : ?>
				<span class="hood__line"><?php echo esc_html( $line ); ?></span>
			<?php endif; ?>
			<span class="hood__n">
				<?php
				printf(
					/* translators: %s: number of places */
					esc_html( _n( '%s place', '%s places', (int) $area['places'], 'oria' ) ),
					esc_html( number_format_i18n( (int) $area['places'] ) )
				);
				?>
				<span class="hood__arrow" aria-hidden="true">&rarr;</span>
			</span>
		</span>
	</a>
	<?php
};
?>
<section class="hoods" aria-labelledby="<?php echo esc_attr( $oria_id ); ?>" data-hoods>
	<header class="hoods__head">
		<div class="hoods__intro">
			<h2 class="h3 hoods__title" id="<?php echo esc_attr( $oria_id ); ?>"><?php echo esc_html( (string) ( $args['heading'] ?? __( 'Explore by neighbourhood', 'oria' ) ) ); ?></h2>
			<p class="hoods__lede"><?php echo esc_html( (string) ( $args['lede'] ?? __( 'Find wellness places, local events and ideas for making a day of it.', 'oria' ) ) ); ?></p>
		</div>

		<div class="hoods__find">
			<label class="xp-vh" for="<?php echo esc_attr( $oria_id ); ?>-q"><?php esc_html_e( 'Find a neighbourhood', 'oria' ); ?></label>
			<span class="hoods__icon" aria-hidden="true">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="9" r="5.5"/><path d="M13.2 13.2 17 17" stroke-linecap="round"/></svg>
			</span>
			<input class="input hoods__q" id="<?php echo esc_attr( $oria_id ); ?>-q" type="search"
				placeholder="<?php esc_attr_e( 'Find a neighbourhood…', 'oria' ); ?>"
				autocomplete="off" role="combobox" aria-expanded="false"
				aria-controls="<?php echo esc_attr( $oria_id ); ?>-ac" aria-autocomplete="list"
				data-hoods-q>
			<div class="hoods__ac" id="<?php echo esc_attr( $oria_id ); ?>-ac" role="listbox" data-hoods-ac hidden></div>
		</div>
	</header>

	<div class="hoods__mosaic">
		<?php
		$oria_card( $oria_featured[0], $oria_src . '-lead', true );
		foreach ( array_slice( $oria_featured, 1, 4 ) as $oria_f ) {
			$oria_card( $oria_f, $oria_src );
		}
		?>
	</div>

	<?php if ( $oria_groups ) : ?>
		<details class="hoodall" data-hoods-all>
			<summary class="hoodall__toggle">
				<span><?php esc_html_e( 'Browse all neighbourhoods', 'oria' ); ?></span>
				<span class="hoodall__mark" aria-hidden="true">&rarr;</span>
			</summary>
			<div class="hoodall__body">
				<?php foreach ( $oria_groups as $oria_region => $oria_rows ) : ?>
					<details class="hoodreg">
						<summary class="hoodreg__toggle">
							<span class="hoodreg__name"><?php echo esc_html( $oria_region ); ?></span>
							<span class="hoodreg__n">
								<?php
								printf(
									/* translators: %s: number of neighbourhoods */
									esc_html( _n( '%s neighbourhood', '%s neighbourhoods', count( $oria_rows ), 'oria' ) ),
									esc_html( number_format_i18n( count( $oria_rows ) ) )
								);
								?>
							</span>
							<span class="hoodreg__mark" aria-hidden="true">&rarr;</span>
						</summary>
						<div class="chips hoodreg__chips">
							<?php foreach ( $oria_rows as $oria_a ) : ?>
								<a class="pill" href="<?php echo esc_url( (string) $oria_a['url'] ); ?>"
									data-hood-name="<?php echo esc_attr( (string) $oria_a['name'] ); ?>"
									data-hood-region="<?php echo esc_attr( (string) $oria_region ); ?>"
									data-hood-n="<?php echo esc_attr( (string) (int) $oria_a['places'] ); ?>"
									data-area-promo="<?php echo esc_attr( $oria_src . '-all' ); ?>"
									data-area-slug="<?php echo esc_attr( (string) $oria_a['slug'] ); ?>">
									<?php echo esc_html( (string) $oria_a['name'] ); ?>
									<span class="hoodreg__count"><?php echo esc_html( (string) (int) $oria_a['places'] ); ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
		</details>
	<?php endif; ?>
</section>
