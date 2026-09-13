<?php
/**
 * One passport badge: earned or still to come.
 *
 * The icon is decoration; the name and the line under it carry the meaning,
 * so a screen reader and a monochrome print both get the same badge.
 * Earned shows the date; not yet shows how far along, as "2 of 3", never as
 * a percentage.
 *
 * $args: badge (from Passport\evaluate()), compact (bool)
 */

declare(strict_types=1);

$oria_b = isset( $args['badge'] ) && is_array( $args['badge'] ) ? $args['badge'] : null;
if ( ! $oria_b ) {
	return;
}
$oria_compact = ! empty( $args['compact'] );

$oria_icons = array(
	'first_step'        => '<path d="M9 20c-1.5 0-2.5-1.2-2.5-2.8 0-1.9 1.5-3.7 3.2-3.7 1.5 0 2.3 1.3 2.3 3 0 1.9-1.4 3.5-3 3.5z"/><path d="M15.5 12c-1.5 0-2.5-1.2-2.5-2.8 0-1.9 1.5-3.7 3.2-3.7 1.5 0 2.3 1.3 2.3 3 0 1.9-1.4 3.5-3 3.5z"/>',
	'wellness_explorer' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
	'calm_seeker'       => '<path d="M5 19c0-8 5-13 14-14-1 9-6 14-14 14z"/><path d="M5 19c3-4 6-7 10-10"/>',
	'move_more'         => '<circle cx="14" cy="5" r="1.6"/><path d="m5 20 3.5-6 2.5 1.5L13 12l-2.5-2 3-3 2.5 2.5L19 10"/><path d="m11 12 2 3 2.5 5"/>',
	'recovery_explorer' => '<path d="M12 3c3.5 4 6 7.2 6 10.5A6 6 0 0 1 6 13.5C6 10.2 8.5 7 12 3z"/><path d="M9 14a3 3 0 0 0 3 3"/>',
	'perth_explorer'    => '<path d="M12 21s6-6.2 6-11a6 6 0 1 0-12 0c0 4.8 6 11 6 11z"/><circle cx="12" cy="10" r="2.2"/>',
);
$oria_path = $oria_icons[ $oria_b['slug'] ] ?? $oria_icons['first_step'];
?>
<div class="pbadge<?php echo $oria_b['earned'] ? ' is-earned' : ' is-locked'; ?><?php echo $oria_compact ? ' pbadge--compact' : ''; ?>">
	<span class="pbadge__icon" aria-hidden="true">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo $oria_path; // phpcs:ignore WordPress.Security.EscapeOutput ?></svg>
	</span>
	<span class="pbadge__text">
		<span class="pbadge__name"><?php echo esc_html( $oria_b['label'] ); ?></span>
		<?php if ( ! $oria_compact ) : ?>
			<span class="pbadge__desc"><?php echo esc_html( $oria_b['earned'] ? $oria_b['blurb'] : $oria_b['hint'] ); ?></span>
		<?php endif; ?>
		<span class="pbadge__state">
			<?php
			if ( $oria_b['earned'] ) {
				/* translators: %s: date */
				printf( esc_html__( 'Earned %s', 'oria' ), esc_html( wp_date( 'j M Y', (int) strtotime( $oria_b['date'] ) ) ) );
			} else {
				/* translators: 1: progress, 2: needed */
				printf( esc_html__( '%1$d of %2$d', 'oria' ), (int) $oria_b['progress'], (int) $oria_b['threshold'] );
			}
			?>
		</span>
	</span>
</div>
