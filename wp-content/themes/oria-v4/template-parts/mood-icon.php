<?php
/**
 * One line drawing per feeling, for the "What would feel good right now?"
 * doorways.
 *
 * Drawn rather than imported: five glyphs do not justify an icon library,
 * and a library's house style would fight the site's own. Each is a single
 * open stroke on a 24 square, round caps, no fills -- the same hand as the
 * rest of Oria's marks.
 *
 * They are decorative. Every one sits beside its own name, so the SVG is
 * hidden from assistive technology rather than given a label that would be
 * read out twice.
 *
 * Args: slug (string).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_slug = (string) ( $args['slug'] ?? '' );

$oria_paths = array(
	// A crescent, and two small sounds going quiet.
	'switch-off'    => '<path d="M15.4 4.6a7.2 7.2 0 1 0 4 9.9 5.8 5.8 0 0 1-4-9.9Z"/><path d="M4.6 9.2c-.7.9-.7 2.1 0 3"/><path d="M2.4 7.4c-1.3 1.7-1.3 4.1 0 5.8"/>',
	// Cupped hands, warmth rising.
	'recover'       => '<path d="M4.5 13.5c0 3.6 3.4 6 7.5 6s7.5-2.4 7.5-6"/><path d="M4.5 13.5h15"/><path d="M9 9.5c0-1.2 1-1.6 1-2.8S9 4.5 9 4.5"/><path d="M14.5 9.5c0-1.2 1-1.6 1-2.8s-1-2.2-1-2.2"/>',
	// One unbroken movement.
	'move'          => '<path d="M3 16.5c3.2 0 3.2-9 6.4-9s3.2 9 6.4 9 2.9-4.5 5.2-4.5"/>',
	// Two, overlapping.
	'connect'       => '<circle cx="9.4" cy="12" r="5.4"/><circle cx="14.6" cy="12" r="5.4"/>',
	// A small brightness.
	'treat-yourself' => '<path d="M12 3.5c0 4.2 1.3 5.5 5.5 5.5-4.2 0-5.5 1.3-5.5 5.5 0-4.2-1.3-5.5-5.5-5.5 4.2 0 5.5-1.3 5.5-5.5Z"/><path d="M18.5 15.5c0 1.9.6 2.5 2.5 2.5-1.9 0-2.5.6-2.5 2.5 0-1.9-.6-2.5-2.5-2.5 1.9 0 2.5-.6 2.5-2.5Z"/>',
);

// An unknown feeling still gets a doorway, just an unmarked one.
$oria_d = $oria_paths[ $oria_slug ] ?? '<circle cx="12" cy="12" r="6.5"/>';
?>
<svg class="xc-mood__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?php echo $oria_d; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup above. ?></svg>
