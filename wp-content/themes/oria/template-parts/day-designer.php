<?php
/**
 * The Day Designer widget (Oria\Core\DayDesigner), compact card.
 *
 * One softly outlined card: a tinted introduction (icon, label, short
 * heading, one sentence) and a form (preference pills, budget, start, people,
 * time, more preferences, Design my outing). The plan appears under both,
 * inside the same card. On category pages day-designer.js moves the card
 * into the listing grid after the first full row, and back after every
 * re-render; here it is drawn once, next to the grid.
 *
 * Narrow (< 600px of card width): the form folds behind "Start planning".
 * That only happens once the script has marked the card (data-dd-js, set by
 * the one-line inline script below so there is no flash of the full form);
 * without scripting the form simply stays open.
 *
 * $args: ctx (context array), near (suburb slug from the page, or ''),
 *        variant ('category' | 'guide'), suburbs (region => [slug => name]).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_ctx     = (array) ( $args['ctx'] ?? array() );
$oria_near    = (string) ( $args['near'] ?? '' );
$oria_subs    = (array) ( $args['suburbs'] ?? array() );
$oria_variant = (string) ( $args['variant'] ?? 'category' );
if ( ! $oria_ctx ) {
	return;
}
$oria_uid     = 'dd-' . wp_unique_id();
$oria_people  = (int) ( $oria_ctx['people'] ?? 1 );
$oria_budget  = (int) ( $oria_ctx['budget'] ?? 80 );
$oria_minutes = (int) ( $oria_ctx['minutes'] ?? 120 );
$oria_presets = (array) ( $oria_ctx['presets'] ?? array() );
$oria_first   = (string) array_key_first( $oria_presets );
$oria_max     = max( 300, $oria_budget * 2 );
$oria_title   = (string) ( $oria_ctx['title'] ?? $oria_ctx['heading'] ?? '' );
$oria_blurb   = (string) ( $oria_ctx['blurb'] ?? $oria_ctx['intro'] ?? '' );
$oria_note    = (string) ( $oria_ctx['note'] ?? '' );
// Default start: the page's own suburb, else Perth CBD as a visible, editable approximation.
$oria_default = '' !== $oria_near ? $oria_near : 'perth-cbd';
?>
<div class="oh-dd-slot<?php echo 'category' === $oria_variant ? ' oh-dd-slot--grid' : ' wrap'; ?>" data-dd-slot>
<section class="dd" id="day-designer" aria-labelledby="<?php echo esc_attr( $oria_uid ); ?>-title"
	data-dd data-dd-ctx="<?php echo esc_attr( (string) $oria_ctx['key'] ); ?>" data-dd-variant="<?php echo esc_attr( $oria_variant ); ?>"
	data-dd-endpoint="<?php echo esc_url( rest_url( 'oria/v1/day-plan' ) ); ?>">

	<div class="dd__intro">
		<div class="dd__brand">
			<span class="dd__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/><path d="M9.5 15.2c1.2-2.4 3.8-2.6 5-1.2-1.5.1-2.8 1-3.3 2.5"/></svg>
			</span>
			<p class="dd__label"><?php esc_html_e( 'Oria Day Designer', 'oria' ); ?></p>
		</div>
		<h2 class="dd__title" id="<?php echo esc_attr( $oria_uid ); ?>-title"><?php echo esc_html( $oria_title ); ?></h2>
		<p class="dd__blurb"><?php echo esc_html( $oria_blurb ); ?></p>
		<?php if ( '' !== $oria_note ) : ?>
			<p class="dd__note"><?php echo esc_html( $oria_note ); ?></p>
		<?php endif; ?>
		<?php $oria_photo = function_exists( '\Oria\Core\DayDesigner\image' ) ? \Oria\Core\DayDesigner\image( $oria_ctx ) : ''; ?>
		<?php if ( '' !== $oria_photo ) : ?>
			<?php // Decorative: the category's own photo, shown only where the wide layout leaves room. ?>
			<div class="dd__photo" aria-hidden="true"><img src="<?php echo esc_url( $oria_photo ); ?>" alt="" loading="lazy" decoding="async"></div>
		<?php endif; ?>
		<button type="button" class="dd__toggle" data-dd-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $oria_uid ); ?>-panel">
			<span data-dd-toggle-label><?php esc_html_e( 'Start planning', 'oria' ); ?></span>
			<svg class="dd__chev" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
		</button>
	</div>

	<form class="dd__panel" id="<?php echo esc_attr( $oria_uid ); ?>-panel" data-dd-form novalidate>
		<fieldset class="dd__prefs">
			<legend class="dd__flabel"><?php esc_html_e( 'What matters most?', 'oria' ); ?></legend>
			<div class="dd__pills">
				<?php foreach ( $oria_presets as $oria_k => $oria_label ) : ?>
					<label class="dd__pill">
						<input type="radio" name="preset" value="<?php echo esc_attr( (string) $oria_k ); ?>" <?php checked( $oria_k, $oria_first ); ?> data-dd-preset>
						<span><svg class="dd__tick" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg><?php echo esc_html( (string) $oria_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<div class="dd__fields">
			<div class="dd__field dd__field--budget" role="group" aria-labelledby="<?php echo esc_attr( $oria_uid ); ?>-blabel" aria-describedby="<?php echo esc_attr( $oria_uid ); ?>-bdesc">
				<p class="dd__flabel" id="<?php echo esc_attr( $oria_uid ); ?>-blabel"><?php esc_html_e( 'Total budget', 'oria' ); ?> <span class="dd__fdesc" id="<?php echo esc_attr( $oria_uid ); ?>-bdesc"><?php esc_html_e( 'For everyone · AUD · travel and parking extra', 'oria' ); ?></span></p>
				<div class="dd__budget">
					<input type="range" min="0" max="<?php echo (int) $oria_max; ?>" step="10" value="<?php echo (int) $oria_budget; ?>" data-dd-range aria-label="<?php esc_attr_e( 'Total budget, slider', 'oria' ); ?>">
					<span class="dd__amount"><span aria-hidden="true">$</span><input type="number" name="budget" min="0" max="3000" step="5" inputmode="numeric" value="<?php echo (int) $oria_budget; ?>" data-dd-budget aria-label="<?php esc_attr_e( 'Total budget in dollars', 'oria' ); ?>" aria-describedby="<?php echo esc_attr( $oria_uid ); ?>-bdesc"></span>
				</div>
			</div>

			<div class="dd__field">
				<label class="dd__flabel" for="<?php echo esc_attr( $oria_uid ); ?>-near"><?php esc_html_e( 'Starting near', 'oria' ); ?></label>
				<select id="<?php echo esc_attr( $oria_uid ); ?>-near" name="near" data-dd-near>
					<?php foreach ( $oria_subs as $oria_region => $oria_list ) : ?>
						<optgroup label="<?php echo esc_attr( (string) $oria_region ); ?>">
							<?php foreach ( (array) $oria_list as $oria_slug => $oria_name ) : ?>
								<option value="<?php echo esc_attr( (string) $oria_slug ); ?>" <?php selected( $oria_slug, $oria_default ); ?>><?php echo esc_html( (string) $oria_name ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="dd__field">
				<label class="dd__flabel" for="<?php echo esc_attr( $oria_uid ); ?>-people"><?php esc_html_e( 'People', 'oria' ); ?></label>
				<select id="<?php echo esc_attr( $oria_uid ); ?>-people" name="people" data-dd-people>
					<option value="1" <?php selected( 1, $oria_people ); ?>><?php esc_html_e( 'Just me', 'oria' ); ?></option>
					<option value="2" <?php selected( 2, $oria_people ); ?>><?php esc_html_e( 'Two of us', 'oria' ); ?></option>
				</select>
			</div>

			<fieldset class="dd__field">
				<legend class="dd__flabel"><?php esc_html_e( 'Time available', 'oria' ); ?></legend>
				<div class="dd__seg">
					<?php foreach ( array( 60 => __( '1 hour', 'oria' ), 120 => __( '2 hours', 'oria' ), 240 => __( 'Half a day', 'oria' ) ) as $oria_m => $oria_tl ) : ?>
						<label><input type="radio" name="minutes" value="<?php echo (int) $oria_m; ?>" <?php checked( $oria_m, $oria_minutes ); ?>><span><?php echo esc_html( $oria_tl ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		</div>

		<div class="dd__more" data-dd-more hidden id="<?php echo esc_attr( $oria_uid ); ?>-more">
			<div class="dd__fields">
				<div class="dd__field">
					<label class="dd__flabel" for="<?php echo esc_attr( $oria_uid ); ?>-km"><?php esc_html_e( 'How far you will go', 'oria' ); ?></label>
					<select id="<?php echo esc_attr( $oria_uid ); ?>-km" name="km" aria-describedby="<?php echo esc_attr( $oria_uid ); ?>-kmhelp">
						<option value="0"><?php esc_html_e( 'Any distance', 'oria' ); ?></option>
						<option value="5"><?php esc_html_e( 'Within 5 km', 'oria' ); ?></option>
						<option value="10"><?php esc_html_e( 'Within 10 km', 'oria' ); ?></option>
						<option value="20"><?php esc_html_e( 'Within 20 km', 'oria' ); ?></option>
						<option value="40"><?php esc_html_e( 'Within 40 km', 'oria' ); ?></option>
					</select>
					<p class="dd__help" id="<?php echo esc_attr( $oria_uid ); ?>-kmhelp"><?php esc_html_e( 'Straight line from where you start.', 'oria' ); ?></p>
				</div>
				<div class="dd__field">
					<label class="dd__check"><input type="checkbox" name="indoor" value="1" aria-describedby="<?php echo esc_attr( $oria_uid ); ?>-inhelp"> <?php esc_html_e( 'Indoors only', 'oria' ); ?></label>
					<p class="dd__help" id="<?php echo esc_attr( $oria_uid ); ?>-inhelp"><?php esc_html_e( 'Places confirmed as indoors. Not a weather forecast.', 'oria' ); ?></p>
				</div>
				<div class="dd__field">
					<label class="dd__check"><input type="checkbox" name="cafe" value="1"> <?php esc_html_e( 'Leave time for a café break', 'oria' ); ?></label>
					<label class="dd__inline" for="<?php echo esc_attr( $oria_uid ); ?>-cafe"><?php esc_html_e( 'Allow', 'oria' ); ?> <span class="dd__amount dd__amount--sm"><span aria-hidden="true">$</span><input type="number" id="<?php echo esc_attr( $oria_uid ); ?>-cafe" name="cafe_each" min="0" max="60" step="1" value="20" inputmode="numeric"></span> <?php esc_html_e( 'a person, 30 min', 'oria' ); ?></label>
				</div>
			</div>
		</div>

		<div class="dd__actions">
			<button type="button" class="dd__morebtn" data-dd-morebtn aria-expanded="false" aria-controls="<?php echo esc_attr( $oria_uid ); ?>-more">
				<span data-dd-morelabel><?php esc_html_e( 'More preferences', 'oria' ); ?></span>
				<svg class="dd__chev" viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
			</button>
			<button type="submit" class="dd__go" data-dd-go>
				<span data-dd-golabel><?php esc_html_e( 'Design my outing', 'oria' ); ?></span>
				<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
			</button>
		</div>
		<noscript><p class="dd__help"><?php esc_html_e( 'The planner needs JavaScript. Every venue is listed on this page.', 'oria' ); ?></p></noscript>
	</form>

	<div class="dd__result" data-dd-result hidden id="<?php echo esc_attr( $oria_uid ); ?>-result" tabindex="-1"></div>
	<div class="dd__saved" data-dd-saved hidden></div>
	<p class="dd-vh" aria-live="polite" data-dd-live></p>
</section>
<script>
/* Marks the card before first paint (no flash of the full form on a phone) and wires the two
   disclosures here, so neither can be left stuck closed if day-designer.js does not load. */
( function ( s ) {
	var r = s.previousElementSibling, t = r.querySelector( '[data-dd-toggle]' ), m = r.querySelector( '[data-dd-morebtn]' ), box = r.querySelector( '[data-dd-more]' );
	r.setAttribute( 'data-dd-js', '' );
	t.addEventListener( 'click', function () {
		var o = r.classList.toggle( 'is-open' );
		t.setAttribute( 'aria-expanded', o ? 'true' : 'false' );
		t.querySelector( '[data-dd-toggle-label]' ).textContent = o ? <?php echo wp_json_encode( __( 'Hide planner', 'oria' ) ); ?> : <?php echo wp_json_encode( __( 'Start planning', 'oria' ) ); ?>;
	} );
	m.addEventListener( 'click', function () {
		var o = box.hidden;
		box.hidden = ! o;
		m.setAttribute( 'aria-expanded', o ? 'true' : 'false' );
	} );
} )( document.currentScript );
</script>
</div>
