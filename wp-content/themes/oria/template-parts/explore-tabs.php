<?php
/**
 * Explore a category: by treatment, or by location.
 *
 * This was two walls of solid green pills, twenty-four options before a
 * visitor had chosen anything, with regions and suburbs mixed into one
 * row so "Northern Suburbs" sat beside "Two Rocks". It is now two tabs,
 * six cards each, and a field.
 *
 * Every link the old rows carried is still served: what does not fit in
 * the six sits in a <details> under it, which opens with no script at
 * all. This is the internal-linking layer, and hiding half of it from a
 * crawler to tidy a page would be a poor trade.
 *
 * @var array $args {
 *     @type array  $styles    Rows of [url, label, count, note] -- the treatments.
 *     @type array  $regions   Rows of [url, label, count] -- whole regions.
 *     @type array  $suburbs   Rows of [url, label, count] -- individual places.
 *     @type string $heading   The section heading.
 *     @type string $what      The category, lower case, for the copy.
 *     @type string $id        Base id for the tabs.
 *     @type string $event     Analytics name for a treatment click.
 * }
 */

declare(strict_types=1);

$oria_styles  = array_values( (array) ( $args['styles'] ?? array() ) );
$oria_regions = array_values( (array) ( $args['regions'] ?? array() ) );
$oria_suburbs = array_values( (array) ( $args['suburbs'] ?? array() ) );
if ( ! $oria_styles && ! $oria_regions ) {
	return;
}

$oria_id    = (string) ( $args['id'] ?? 'xtabs' );
$oria_what  = (string) ( $args['what'] ?? '' );
$oria_event = (string) ( $args['event'] ?? '' );

// Six on the face of each tab; the rest are one click away.
$oria_lead_styles  = array_slice( $oria_styles, 0, 6 );
$oria_rest_styles  = array_slice( $oria_styles, 6 );
$oria_lead_regions = array_slice( $oria_regions, 0, 5 );
$oria_rest_regions = array_slice( $oria_regions, 5 );

/**
 * One card. Warm white, a green name, the count spelled out underneath --
 * not a bare digit at the far edge, which reads as a badge.
 *
 * @param array  $row   [url, label, count, note?].
 * @param string $event Analytics name, or ''.
 */
/**
 * What a treatment feels like, looked up once for the whole section.
 *
 * Keyed by the slug at the end of the card's own URL, so the rows did
 * not have to learn a new field and every caller keeps working.
 */
$oria_senses = (array) require get_template_directory() . '/data/sensations.php';

$oria_sense_of = static function ( string $url ) use ( $oria_senses ): array {
	$slug = basename( untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );

	return $oria_senses[ $slug ] ?? array( '', '' );
};

/**
 * One card. Warm white, a green name, the count spelled out underneath --
 * not a bare digit at the far edge, which reads as a badge.
 *
 * A style card also carries how it feels: a family that tints its edge
 * and draws its mark, and a one-word cue. A place card does not, because
 * a suburb has no temperature, and the same function draws both.
 *
 * @param array  $row   [url, label, count, note?].
 * @param string $event Analytics name, or ''.
 * @param bool   $sense Whether this row is a treatment rather than a place.
 */
$oria_card = static function ( array $row, string $event = '', bool $sense = false ) use ( $oria_sense_of ) {
	$note = (string) ( $row[3] ?? '' );
	list( $family, $cue ) = $sense ? $oria_sense_of( (string) $row[0] ) : array( '', '' );
	?>
	<a class="xcard<?php echo $sense ? ' xcard--sense' : ''; ?>" href="<?php echo esc_url( (string) $row[0] ); ?>"<?php echo '' !== $family ? ' data-sense="' . esc_attr( $family ) . '"' : ''; ?><?php echo '' !== $event ? ' data-oria-event="' . esc_attr( $event ) . '"' : ''; ?>>
		<?php if ( '' !== $family ) : ?>
			<span class="xcard__wash" aria-hidden="true"></span>
			<?php get_template_part( 'template-parts/sensation-icon', null, array( 'family' => $family ) ); ?>
		<?php endif; ?>
		<?php if ( '' !== $cue ) : ?>
			<span class="xcard__cue"><?php echo esc_html( $cue ); ?></span>
		<?php endif; ?>
		<b class="xcard__name"><?php echo esc_html( (string) $row[1] ); ?></b>
		<?php if ( '' !== $note ) : ?>
			<span class="xcard__note"><?php echo esc_html( $note ); ?></span>
		<?php endif; ?>
		<span class="xcard__foot">
			<span class="xcard__n">
				<?php
				printf(
					/* translators: %s: number of places */
					esc_html( _n( '%s place', '%s places', (int) $row[2], 'oria' ) ),
					esc_html( number_format_i18n( (int) $row[2] ) )
				);
				?>
			</span>
			<span class="xcard__arrow" aria-hidden="true">
				<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M3 8h9"/><path d="m8.5 4.5 3.5 3.5-3.5 3.5"/></svg>
			</span>
		</span>
	</a>
	<?php
};
?>
<section class="xtabs" data-xtabs>
	<h2 class="h3 xtabs__title" id="<?php echo esc_attr( $oria_id ); ?>-title"><?php echo esc_html( (string) ( $args['heading'] ?? __( 'Explore this category', 'oria' ) ) ); ?></h2>

	<?php if ( $oria_styles && $oria_regions ) : ?>
		<div class="xtabs__bar" role="tablist" aria-labelledby="<?php echo esc_attr( $oria_id ); ?>-title" data-xtabs-bar hidden>
			<button class="xtabs__tab" type="button" role="tab" id="<?php echo esc_attr( $oria_id ); ?>-t1"
				aria-controls="<?php echo esc_attr( $oria_id ); ?>-p1" aria-selected="true">
				<?php esc_html_e( 'By treatment', 'oria' ); ?>
			</button>
			<button class="xtabs__tab" type="button" role="tab" id="<?php echo esc_attr( $oria_id ); ?>-t2"
				aria-controls="<?php echo esc_attr( $oria_id ); ?>-p2" aria-selected="false" tabindex="-1">
				<?php esc_html_e( 'By location', 'oria' ); ?>
			</button>
		</div>
	<?php endif; ?>

	<?php if ( $oria_styles ) : ?>
		<div class="xtabs__panel" id="<?php echo esc_attr( $oria_id ); ?>-p1" data-xtabs-panel>
			<h3 class="h4 xtabs__sub"><?php esc_html_e( 'Choose a style', 'oria' ); ?></h3>
			<div class="xtabs__grid xtabs__grid--sense">
				<?php
				foreach ( $oria_lead_styles as $oria_row ) {
					$oria_card( $oria_row, $oria_event, true );
				}
				?>
			</div>

			<?php if ( $oria_rest_styles ) : ?>
				<details class="xtabs__all">
					<summary class="xtabs__allbtn">
						<span><?php esc_html_e( 'View all styles', 'oria' ); ?></span>
						<span class="xtabs__mark" aria-hidden="true">&rarr;</span>
					</summary>
					<div class="chips xtabs__chips">
						<?php foreach ( $oria_rest_styles as $oria_row ) : ?>
							<a class="pill pill--count" href="<?php echo esc_url( (string) $oria_row[0] ); ?>"<?php echo '' !== $oria_event ? ' data-oria-event="' . esc_attr( $oria_event ) . '"' : ''; ?>>
								<?php echo esc_html( (string) $oria_row[1] ); ?>
								<span class="pill__n">
									<?php
									printf(
										/* translators: %s: number of places */
										esc_html( _n( '%s place', '%s places', (int) $oria_row[2], 'oria' ) ),
										esc_html( number_format_i18n( (int) $oria_row[2] ) )
									);
									?>
								</span>
							</a>
						<?php endforeach; ?>
					</div>
				</details>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $oria_regions ) : ?>
		<div class="xtabs__panel" id="<?php echo esc_attr( $oria_id ); ?>-p2" data-xtabs-panel>
			<h3 class="h4 xtabs__sub"><?php esc_html_e( 'Choose a part of town', 'oria' ); ?></h3>
			<div class="xtabs__grid">
				<?php
				foreach ( $oria_lead_regions as $oria_row ) {
					$oria_card( $oria_row );
				}
				?>
			</div>

			<?php if ( $oria_suburbs ) : ?>
				<div class="xtabs__find">
					<label class="xtabs__findlabel" for="<?php echo esc_attr( $oria_id ); ?>-q">
						<?php
						printf(
							/* translators: %s: category name, lower case */
							esc_html__( 'Find %s near you', 'oria' ),
							esc_html( $oria_what )
						);
						?>
					</label>
					<div class="xtabs__findbox">
						<span class="xtabs__findicon" aria-hidden="true">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="9" r="5.5"/><path d="M13.2 13.2 17 17" stroke-linecap="round"/></svg>
						</span>
						<input class="input xtabs__q" id="<?php echo esc_attr( $oria_id ); ?>-q" type="search"
							placeholder="<?php esc_attr_e( 'Search a suburb…', 'oria' ); ?>"
							autocomplete="off" role="combobox" aria-expanded="false"
							aria-controls="<?php echo esc_attr( $oria_id ); ?>-ac" aria-autocomplete="list"
							data-xtabs-q>
						<div class="xtabs__ac" id="<?php echo esc_attr( $oria_id ); ?>-ac" role="listbox" data-xtabs-ac hidden></div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $oria_rest_regions || $oria_suburbs ) : ?>
				<details class="xtabs__all">
					<summary class="xtabs__allbtn">
						<span><?php esc_html_e( 'View every location', 'oria' ); ?></span>
						<span class="xtabs__mark" aria-hidden="true">&rarr;</span>
					</summary>
					<?php if ( $oria_rest_regions ) : ?>
						<p class="xtabs__group"><?php esc_html_e( 'Regions', 'oria' ); ?></p>
						<div class="chips xtabs__chips">
							<?php foreach ( $oria_rest_regions as $oria_row ) : ?>
								<a class="pill pill--count" href="<?php echo esc_url( (string) $oria_row[0] ); ?>">
									<?php echo esc_html( (string) $oria_row[1] ); ?>
									<span class="pill__n">
										<?php
										printf(
											/* translators: %s: number of places */
											esc_html( _n( '%s place', '%s places', (int) $oria_row[2], 'oria' ) ),
											esc_html( number_format_i18n( (int) $oria_row[2] ) )
										);
										?>
									</span>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $oria_suburbs ) : ?>
						<p class="xtabs__group"><?php esc_html_e( 'Suburbs', 'oria' ); ?></p>
						<div class="chips xtabs__chips">
							<?php foreach ( $oria_suburbs as $oria_row ) : ?>
								<a class="pill pill--count" href="<?php echo esc_url( (string) $oria_row[0] ); ?>"
									data-xtabs-place="<?php echo esc_attr( strtolower( (string) $oria_row[1] ) ); ?>">
									<?php echo esc_html( (string) $oria_row[1] ); ?>
									<span class="pill__n">
										<?php
										printf(
											/* translators: %s: number of places */
											esc_html( _n( '%s place', '%s places', (int) $oria_row[2], 'oria' ) ),
											esc_html( number_format_i18n( (int) $oria_row[2] ) )
										);
										?>
									</span>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</details>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
