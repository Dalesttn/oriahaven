<?php
/**
 * The Day Designer widget (Oria\Core\DayDesigner).
 *
 * Server-rendered and compact: heading, three preset chips, the budget,
 * people, starting suburb, time and a "Design my outing" button. The plan
 * appears in the same block, under the controls; the listings below stay
 * where they are. Without JavaScript the form is inert and the venues below
 * are the way in -- so the note says so.
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
// Default start: the page's own suburb, else Perth CBD as a visible, editable approximation.
$oria_default = '' !== $oria_near ? $oria_near : 'perth-cbd';
?>
<section class="wrap dd" id="day-designer" aria-labelledby="<?php echo esc_attr( $oria_uid ); ?>-title"
	data-dd data-dd-ctx="<?php echo esc_attr( (string) $oria_ctx['key'] ); ?>" data-dd-variant="<?php echo esc_attr( $oria_variant ); ?>"
	data-dd-endpoint="<?php echo esc_url( rest_url( 'oria/v1/day-plan' ) ); ?>">
	<div class="dd__card">
		<div class="dd__head">
			<p class="micro dd__eyebrow"><?php esc_html_e( 'Oria Day Designer', 'oria' ); ?></p>
			<h2 class="dd__title" id="<?php echo esc_attr( $oria_uid ); ?>-title"><?php echo esc_html( (string) $oria_ctx['heading'] ); ?></h2>
			<p class="dd__intro"><?php echo esc_html( (string) $oria_ctx['intro'] ); ?></p>
		</div>

		<form class="dd__form" data-dd-form novalidate>
			<div class="dd__chips" role="group" aria-label="<?php esc_attr_e( 'What kind of outing', 'oria' ); ?>">
				<?php foreach ( $oria_presets as $oria_k => $oria_label ) : ?>
					<button type="button" class="dd__chip" data-dd-preset="<?php echo esc_attr( (string) $oria_k ); ?>" aria-pressed="<?php echo $oria_k === $oria_first ? 'true' : 'false'; ?>"><?php echo esc_html( (string) $oria_label ); ?></button>
				<?php endforeach; ?>
			</div>

			<div class="dd__budget">
				<label for="<?php echo esc_attr( $oria_uid ); ?>-budget"><?php esc_html_e( 'Budget for everyone', 'oria' ); ?></label>
				<div class="dd__budget-row">
					<input type="range" id="<?php echo esc_attr( $oria_uid ); ?>-budget-range" min="0" max="<?php echo (int) $oria_max; ?>" step="10" value="<?php echo (int) $oria_budget; ?>" data-dd-range aria-label="<?php esc_attr_e( 'Budget slider', 'oria' ); ?>">
					<span class="dd__money"><span aria-hidden="true">$</span><input type="number" id="<?php echo esc_attr( $oria_uid ); ?>-budget" name="budget" min="0" max="3000" step="5" inputmode="numeric" value="<?php echo (int) $oria_budget; ?>" data-dd-budget> <abbr title="Australian dollars">AUD</abbr></span>
				</div>
				<p class="dd__hint"><?php esc_html_e( 'Activities and optional extras. Travel and parking not included.', 'oria' ); ?></p>
			</div>

			<div class="dd__row">
				<div class="dd__field">
					<label for="<?php echo esc_attr( $oria_uid ); ?>-people"><?php esc_html_e( 'People', 'oria' ); ?></label>
					<select id="<?php echo esc_attr( $oria_uid ); ?>-people" name="people" data-dd-people>
						<option value="1" <?php selected( 1, $oria_people ); ?>><?php esc_html_e( 'Just me', 'oria' ); ?></option>
						<option value="2" <?php selected( 2, $oria_people ); ?>><?php esc_html_e( 'Two of us', 'oria' ); ?></option>
					</select>
				</div>
				<div class="dd__field">
					<label for="<?php echo esc_attr( $oria_uid ); ?>-near"><?php esc_html_e( 'Starting near', 'oria' ); ?></label>
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
				<fieldset class="dd__field dd__time">
					<legend><?php esc_html_e( 'Time available', 'oria' ); ?></legend>
					<?php foreach ( array( 60 => __( '1 hour', 'oria' ), 120 => __( '2 hours', 'oria' ), 240 => __( 'Half a day', 'oria' ) ) as $oria_m => $oria_tl ) : ?>
						<label class="dd__seg"><input type="radio" name="minutes" value="<?php echo (int) $oria_m; ?>" <?php checked( $oria_m, $oria_minutes ); ?>> <span><?php echo esc_html( $oria_tl ); ?></span></label>
					<?php endforeach; ?>
				</fieldset>
			</div>

			<details class="dd__more">
				<summary><?php esc_html_e( 'More preferences', 'oria' ); ?></summary>
				<div class="dd__row">
					<div class="dd__field">
						<label for="<?php echo esc_attr( $oria_uid ); ?>-km"><?php esc_html_e( 'How far you will go', 'oria' ); ?></label>
						<select id="<?php echo esc_attr( $oria_uid ); ?>-km" name="km">
							<option value="0"><?php esc_html_e( 'Any distance', 'oria' ); ?></option>
							<option value="5"><?php esc_html_e( 'Within 5 km', 'oria' ); ?></option>
							<option value="10"><?php esc_html_e( 'Within 10 km', 'oria' ); ?></option>
							<option value="20"><?php esc_html_e( 'Within 20 km', 'oria' ); ?></option>
							<option value="40"><?php esc_html_e( 'Within 40 km', 'oria' ); ?></option>
						</select>
						<p class="dd__hint"><?php esc_html_e( 'Straight-line distance from the suburb you chose.', 'oria' ); ?></p>
					</div>
					<div class="dd__field">
						<label class="dd__check"><input type="checkbox" name="indoor" value="1"> <?php esc_html_e( 'Indoors only', 'oria' ); ?></label>
						<p class="dd__hint"><?php esc_html_e( 'Only places confirmed as indoors. Not a weather forecast.', 'oria' ); ?></p>
					</div>
					<div class="dd__field">
						<label class="dd__check"><input type="checkbox" name="cafe" value="1" data-dd-cafe> <?php esc_html_e( 'Leave time for a café break', 'oria' ); ?></label>
						<label class="dd__inline" for="<?php echo esc_attr( $oria_uid ); ?>-cafe"><?php esc_html_e( 'Allow', 'oria' ); ?> $<input type="number" id="<?php echo esc_attr( $oria_uid ); ?>-cafe" name="cafe_each" min="0" max="60" step="1" value="20" inputmode="numeric"> <?php esc_html_e( 'a person, 30 minutes', 'oria' ); ?></label>
					</div>
				</div>
			</details>

			<div class="dd__go">
				<button type="submit" class="btn btn--dark dd__btn" data-dd-go><?php esc_html_e( 'Design my outing', 'oria' ); ?></button>
				<noscript><p class="dd__hint"><?php esc_html_e( 'The planner needs JavaScript. Every venue is listed below.', 'oria' ); ?></p></noscript>
			</div>
		</form>

		<div class="dd__result" data-dd-result hidden id="<?php echo esc_attr( $oria_uid ); ?>-result" tabindex="-1"></div>
		<div class="dd__saved" data-dd-saved hidden></div>
		<p class="dd-vh" aria-live="polite" data-dd-live></p>
	</div>
</section>
