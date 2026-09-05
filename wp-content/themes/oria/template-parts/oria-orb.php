<?php
/**
 * Oria. A presence, not an adviser.
 *
 * One source, used on /ask/ and on the front page, so the two can never
 * drift apart. Inline SVG driven entirely by [data-state] on the wrapper:
 * no library, no sprite sheet, and every expression is a path already in
 * the document that CSS shows or hides.
 *
 * She reacts to what the PAGE is doing -- reading, finding, coming up empty
 * -- and never says anything about a listing. The state worth reading the
 * CSS for is "quiet": on a health disclosure the float stops, the sparkles
 * vanish, the glow flattens and the smile becomes a level line. A cheerful
 * mascot bobbing beside "that sounds like a health question, ask a GP"
 * would make a joke of the one moment on that page that must not be one.
 *
 * aria-hidden throughout: where she appears with results, the announced
 * information is the status line beside her. Two live regions talking over
 * each other reads worse than a character a screen reader never mentions.
 *
 * The gradient ids are suffixed per instance. Two orbs on one page sharing
 * an id would both paint from whichever one the browser resolved first.
 *
 * @param string $args['class'] extra class on the wrapper, e.g. oria--hero.
 * @param string $args['uid']   unique suffix when more than one is on a page.
 */

declare(strict_types=1);

$oria_orb_class = isset( $args['class'] ) ? ' ' . (string) $args['class'] : '';
$oria_orb_uid   = isset( $args['uid'] ) ? (string) $args['uid'] : 'a';
$oria_glow_id   = 'oria-glow-' . $oria_orb_uid;
$oria_body_id   = 'oria-body-' . $oria_orb_uid;
?>
<div class="oria<?php echo esc_attr( $oria_orb_class ); ?>" data-oria data-state="idle" aria-hidden="true">
	<svg class="oria__svg" viewBox="0 0 80 80" focusable="false">
		<defs>
			<radialGradient id="<?php echo esc_attr( $oria_glow_id ); ?>" cx="50%" cy="50%" r="50%">
				<stop offset="0%" stop-color="var(--oria-glow)" stop-opacity=".5" />
				<stop offset="55%" stop-color="var(--oria-glow)" stop-opacity=".15" />
				<stop offset="100%" stop-color="var(--oria-glow)" stop-opacity="0" />
			</radialGradient>
			<radialGradient id="<?php echo esc_attr( $oria_body_id ); ?>" cx="34%" cy="26%" r="80%">
				<stop offset="0%" stop-color="var(--oria-hi)" />
				<stop offset="100%" stop-color="var(--oria-body)" />
			</radialGradient>
		</defs>

		<circle class="oria__glow" cx="40" cy="40" r="39" fill="url(#<?php echo esc_attr( $oria_glow_id ); ?>)" />

		<g class="oria__float">
			<g class="oria__sparks">
				<circle cx="40" cy="13" r="1.7" />
				<circle cx="65" cy="47" r="1.2" />
				<circle cx="15" cy="49" r="1.4" />
			</g>

			<circle class="oria__body" cx="40" cy="40" r="21" fill="url(#<?php echo esc_attr( $oria_body_id ); ?>)" />
			<ellipse class="oria__sheen" cx="32" cy="30" rx="7.5" ry="5" />

			<g class="oria__face">
				<g class="oria__eyes oria__eyes--open">
					<ellipse cx="33" cy="39" rx="2.6" ry="3.4" />
					<ellipse cx="47" cy="39" rx="2.6" ry="3.4" />
				</g>
				<g class="oria__eyes oria__eyes--happy">
					<path d="M30.2 40.4q2.8-3.6 5.6 0" />
					<path d="M44.2 40.4q2.8-3.6 5.6 0" />
				</g>
				<g class="oria__eyes oria__eyes--soft">
					<path d="M30.2 39.2q2.8 2.8 5.6 0" />
					<path d="M44.2 39.2q2.8 2.8 5.6 0" />
				</g>
				<g class="oria__eyes oria__eyes--up">
					<ellipse cx="33" cy="37.4" rx="2.5" ry="3.2" />
					<ellipse cx="47" cy="37.4" rx="2.5" ry="3.2" />
				</g>

				<path class="oria__mouth oria__mouth--smile" d="M35.5 46q4.5 4 9 0" />
				<path class="oria__mouth oria__mouth--wide" d="M34 45.4q6 6 12 0" />
				<path class="oria__mouth oria__mouth--flat" d="M36 47.4h8" />
				<path class="oria__mouth oria__mouth--small" d="M37.6 46.8q2.4 2 4.8 0" />
			</g>
		</g>
	</svg>
</div>
