<?php
/**
 * One ink drawing per family of sensation, for the "Choose a style"
 * cards.
 *
 * Drawn by family rather than by treatment: there are hundreds of
 * service terms and eight ways a room can feel, so eight marks cover the
 * wall without anybody having to draw a picture of lymphatic drainage.
 *
 * They are meant to read as drawn rather than generated. Nothing here is
 * symmetrical on purpose -- rays are different lengths, arcs sit slightly
 * off centre, curls rise to different heights -- because a perfectly
 * mirrored mark reads as an icon library and the site does not. Every
 * stroke is open and round-capped, and the second, quieter element of
 * each drawing sits at reduced opacity so the mark has two tones in one
 * colour, the way an ink sketch does.
 *
 * Inline SVG on purpose. They inherit currentColor, so each one tints
 * itself with its own sensation without a second file, a second request
 * or a second copy per colour; and at 24px a hand-set path stays crisp
 * where traced artwork goes soft.
 *
 * Decorative. Each one sits beside the treatment's own name, so it is
 * hidden from assistive technology rather than labelled twice.
 *
 * Args: family (string).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_family = (string) ( $args['family'] ?? '' );

/*
 * Each entry is [ the mark, the quieter second tone ].
 */
$oria_marks = array(

	// Warmth coming off stone: three curls, none the same height.
	'heat'     => array(
		'<path d="M8 14.1c0-1.8 1.6-2.3 1.6-4.1S8 7.6 8 5.8"/><path d="M12.2 14.4c0-1.5 1.4-2 1.4-3.5s-1.4-2-1.4-3.5"/>',
		'<path d="M16.2 14.2c0-1.2 1.2-1.6 1.2-2.9"/><path d="M4.9 18.1c2.7-1.2 11.6-1.2 14.3 0"/>',
	),

	// Water gone still, and one crystal over it.
	'cold'     => array(
		'<path d="M12 3.9v7.2"/><path d="M9.1 5.7 12 7.4l2.9-1.7"/><path d="M9.1 9.3 12 7.6l2.9 1.7"/>',
		'<path d="M3.7 16.2c2.3-1 4.1.9 6.4 0s4.2.9 6.5 0 2.4.6 3.6.2"/>',
	),

	// Two waves running against each other, which is what rounds are.
	// It was one squiggle with a dot at each end and the dots read as
	// specks on the screen rather than as hot and cold.
	'contrast' => array(
		'<path d="M3.6 9.4c3.2-3.5 6.2-3.5 8.3 0s5.1 3.5 8.1 0"/>',
		'<path d="M3.6 15.2c3.2 3.5 6.2 3.5 8.3 0s5.1-3.5 8.1 0"/>',
	),

	// A panel low on the wall, throwing uneven light.
	'light'    => array(
		'<path d="M6.7 15.3a5.3 5.3 0 0 1 10.6 0"/><path d="M4.1 15.3h16"/>',
		'<path d="M12 4.3v2.5"/><path d="m6.4 6.8 1.4 1.7"/><path d="m17.9 6.5-1.6 1.9"/><path d="M3.5 11.1h2"/><path d="M18.8 11.1h2.1"/>',
	),

	// A bowl, still ringing.
	'sound'    => array(
		'<path d="M6.5 12.5h11.1"/><path d="M7 12.5c0 2.9 2.2 4.8 5 4.8s5-1.9 5-4.8"/>',
		'<path d="M5 9.2c.2-2 1.2-3.4 2.5-4.1"/><path d="M19.1 9.4c-.3-2.1-1.4-3.5-2.7-4.2"/>',
	),

	// Somebody lying down. A dome on a line was too close to the sun in
	// "light" to tell apart at 24px; a head at one end is not.
	'still'    => array(
		'<circle cx="6.3" cy="13.4" r="1.7"/><path d="M8.6 14.9c2.4-1.2 6-.6 8.6 1.8"/>',
		'<path d="M3.5 17.6h17"/>',
	),

	// A hand. Abstraction kept failing here: a cupped palm with warmth
	// over it read as a smiley face, and an arch on pillars read as a
	// bank. Three uneven fingers, a palm and a thumb is the one shape
	// nobody mistakes for something else at 24px.
	'touch'    => array(
		'<path d="M8.6 12.1V7.4"/><path d="M11.8 12.1V6.2"/><path d="M15 12.1V7.9"/>'
		. '<path d="M18.2 11.3v3.4c0 2.4-2 4.4-4.4 4.4h-2.6c-1.2 0-2.3-.5-3.1-1.4l-2.9-3.1c-.7-.8-.6-2 .2-2.7.7-.6 1.8-.5 2.5.2l1.3 1.4"/>',
		'',
	),

	// One movement, unbroken.
	'move'     => array(
		'<path d="M3.4 15.5c2.9 0 3.3-7.3 6.6-7.3s3.1 7.3 6.3 7.3 2.4-3.5 4.3-3.5"/>',
		'',
	),
);

if ( ! isset( $oria_marks[ $oria_family ] ) ) {
	return; // An unmapped style says nothing rather than guessing.
}

list( $oria_main, $oria_quiet ) = $oria_marks[ $oria_family ];
?>
<span class="xcard__icon" aria-hidden="true">
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" focusable="false">
		<?php echo $oria_main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup above. ?>
		<?php if ( '' !== $oria_quiet ) : ?>
			<g opacity=".5"><?php echo $oria_quiet; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup above. ?></g>
		<?php endif; ?>
	</svg>
</span>
