<?php
/**
 * One line drawing per family of sensation, for the "Choose a style"
 * cards.
 *
 * Drawn by family rather than by treatment: there are hundreds of
 * service terms and eight ways a room can feel, so eight marks cover
 * the wall without anybody having to draw a picture of lymphatic
 * drainage.
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

$oria_marks = array(
	// Warmth rising off a curve.
	'heat'     => '<path d="M4 17.5h16"/><path d="M8 13.2c0-1.6 1.4-2.1 1.4-3.7S8 6 8 6"/><path d="M12 13.2c0-1.6 1.4-2.1 1.4-3.7S12 6 12 6"/><path d="M16 13.2c0-1.6 1.4-2.1 1.4-3.7S16 6 16 6"/>',
	// Still water, and one crystal.
	'cold'     => '<path d="M3.5 16.5c2 0 2-1.4 4-1.4s2 1.4 4 1.4 2-1.4 4-1.4 2 1.4 4 1.4"/><path d="M12 3.5v7.6M9 5.3l3 1.8 3-1.8M9 9.1l3-1.8 3 1.8"/>',
	// Two rounds, one each way.
	'contrast' => '<path d="M4 9c2.7-3.4 6-3.4 8 0"/><path d="M12 15c2 3.4 5.3 3.4 8 0"/><path d="M4 15h3M17 9h3"/>',
	// A panel, radiating.
	'light'    => '<circle cx="12" cy="12" r="3.6"/><path d="M12 3.6v2.2M12 18.2v2.2M3.6 12h2.2M18.2 12h2.2M6.1 6.1l1.6 1.6M16.3 16.3l1.6 1.6M17.9 6.1l-1.6 1.6M7.7 16.3l-1.6 1.6"/>',
	// Rings going out from a bowl.
	'sound'    => '<path d="M6.5 11.5c0 3 2.5 5 5.5 5s5.5-2 5.5-5Z"/><path d="M4 8.6c0-2 1.4-3.2 2.6-3.2"/><path d="M20 8.6c0-2-1.4-3.2-2.6-3.2"/>',
	// A horizon, held.
	'still'    => '<path d="M3.5 14.5h17"/><path d="M8.2 14.5a3.8 3.8 0 0 1 7.6 0"/><path d="M12 5.2v2.4"/>',
	// A hand, or the warmth of one.
	'touch'    => '<path d="M7 13.5V7.2a1.4 1.4 0 0 1 2.8 0v5"/><path d="M9.8 11.4V5.9a1.4 1.4 0 0 1 2.8 0v5.5"/><path d="M12.6 11.8V7.4a1.4 1.4 0 0 1 2.8 0v6"/><path d="M15.4 12.6c0-1.6 2.2-1.8 2.2.2v2.4c0 3-2.4 5.3-5.4 5.3s-5.2-2-5.2-5.3"/>',
	// One movement, unbroken.
	'move'     => '<path d="M3 15.4c3 0 3-7.4 6-7.4s3 7.4 6 7.4 2.7-3.7 4.9-3.7"/>',
);

$oria_d = $oria_marks[ $oria_family ] ?? '';

if ( '' === $oria_d ) {
	return; // An unmapped style says nothing rather than guessing.
}
?>
<span class="xcard__icon" aria-hidden="true">
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" focusable="false"><?php echo $oria_d; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup above. ?></svg>
</span>
