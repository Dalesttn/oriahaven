<?php
/**
 * One ink drawing per feeling, for the "What would feel good right now?"
 * doorways.
 *
 * Drawn rather than imported: five glyphs do not justify an icon library,
 * and a library's house style would fight the site's own.
 *
 * Meant to read as drawn. Nothing is mirrored on purpose -- the crescent
 * sits off centre, the group is three circles of different sizes, the
 * sparkle's arms are concave -- because a perfectly symmetrical mark
 * reads as a library and this site does not. Each has two tones in one
 * colour, the subject at full weight and its setting at half, the way an
 * ink sketch does.
 *
 * These live inside the doorway, which means they are drawn twice: dark
 * on pale light when the door is shut, and white on the deep arch when
 * it is open. Both were checked; a mark that only works one way round is
 * no good here.
 *
 * They are decorative. Every one sits beside its own name, so the SVG is
 * hidden from assistive technology rather than given a label that would
 * be read out twice.
 *
 * Args: slug (string).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_slug = (string) ( $args['slug'] ?? '' );

/*
 * Each entry is [ the mark, the quieter second tone ].
 */
$oria_paths = array(

	// A crescent, and two sounds going quiet. The arcs sat tight against
	// the moon and read as a smudge on it at 24px; they are out in the
	// clear now.
	'switch-off'     => array(
		'<path d="M16.6 5.3a7.1 7.1 0 1 0 3.4 9.4 5.8 5.8 0 0 1-3.4-9.4Z"/>',
		'<path d="M7.1 9.9c-.7.9-.7 2.2 0 3.1"/><path d="M4.1 8c-1.4 1.9-1.4 4.6 0 6.5"/>',
	),

	// Stacked stones. It was a basin with warmth rising off it, which at
	// any size read as a bowl of soup -- charming, and nothing at all to
	// do with heat, cold and hands-on treatments.
	'recover'        => array(
		'<ellipse cx="12" cy="17.3" rx="6.4" ry="2.2"/><ellipse cx="11.3" cy="12.4" rx="4.9" ry="1.9"/>',
		'<ellipse cx="12.6" cy="8.1" rx="3.4" ry="1.6"/>',
	),

	// One movement, and its echo.
	'move'           => array(
		'<path d="M3.3 14.2c2.9 0 3.4-7.4 6.7-7.4s3.1 7.4 6.4 7.4 2.4-3.6 4.3-3.6"/>',
		'<path d="M4.6 18.4c2.4 0 2.8-3.1 5.6-3.1s2.6 3.1 5.4 3.1 2-1.5 3.6-1.5"/>',
	),

	// A circle of people, which is what the words under it say. Overlapped
	// they merged into a clover at 24px; joined by lines they became the
	// share icon every app already uses. Held apart, sat round an open
	// ring, they are a group.
	'connect'        => array(
		'<circle cx="8" cy="8.2" r="2.2"/><circle cx="16.4" cy="10.4" r="1.9"/><circle cx="10.2" cy="16.8" r="2"/>',
		'<path d="M14.9 16.2a6.6 6.6 0 0 0 2.9-4.6"/><path d="M4.9 11.6a6.6 6.6 0 0 0 2.2 4.9"/><path d="M11.1 5.3a6.6 6.6 0 0 1 3.9 1.6"/>',
	),

	// A small brightness, with curved arms so it is drawn, not plotted.
	'treat-yourself' => array(
		'<path d="M10.6 3.6c0 4.4 1.4 5.8 5.8 5.8-4.4 0-5.8 1.4-5.8 5.8 0-4.4-1.4-5.8-5.8-5.8 4.4 0 5.8-1.4 5.8-5.8Z"/>',
		'<path d="M17.6 14.4c0 2 .7 2.7 2.7 2.7-2 0-2.7.7-2.7 2.7 0-2-.7-2.7-2.7-2.7 2 0 2.7-.7 2.7-2.7Z"/>',
	),
);

// An unknown feeling still gets a doorway, just an unmarked one.
if ( ! isset( $oria_paths[ $oria_slug ] ) ) {
	$oria_main  = '<circle cx="12" cy="12" r="6.4"/>';
	$oria_quiet = '';
} else {
	list( $oria_main, $oria_quiet ) = $oria_paths[ $oria_slug ];
}
?>
<svg class="xc-mood__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
	<?php echo $oria_main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup above. ?>
	<?php if ( '' !== $oria_quiet ) : ?>
		<g opacity=".5"><?php echo $oria_quiet; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup above. ?></g>
	<?php endif; ?>
</svg>
