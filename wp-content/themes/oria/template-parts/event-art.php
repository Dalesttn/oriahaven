<?php
/**
 * Branded event artwork — the stand-in hero for events with no photo of
 * their own (aggregated finds, member events awaiting an upload). A deep
 * petrol band with an ensō ring, the event type's mark and label. Always
 * on-brand, never someone else's copyrighted banner.
 *
 * Args: 'event_id' (int).
 */

declare(strict_types=1);

$oria_art_id = (int) ( $args['event_id'] ?? 0 );
if ( ! $oria_art_id ) {
	return;
}

$oria_art_marks = array(
	'yoga'                 => '🧘',
	'meditation'           => '🪷',
	'breathwork'           => '🌬️',
	'sound-healing'        => '🔔',
	'mindfulness'          => '🌤️',
	'womens-circle'        => '🌙',
	'mens-group'           => '🔥',
	'wellness-workshop'    => '🌿',
	'retreat'              => '🏞️',
	'sauna'                => '🔥',
	'cold-plunge'          => '🧊',
	'nutrition'            => '🥗',
	'fitness'              => '🤸',
	'personal-development' => '🌱',
	'spiritual'            => '✨',
	'relaxation'           => '🌾',
	'community'            => '🤝',
);

$oria_art_terms = wp_get_post_terms( $oria_art_id, 'event_type' );
$oria_art_term  = ! is_wp_error( $oria_art_terms ) && $oria_art_terms ? $oria_art_terms[0] : null;
if ( ! $oria_art_term ) {
	$oria_art_pr   = wp_get_post_terms( $oria_art_id, 'practice' );
	$oria_art_term = ! is_wp_error( $oria_art_pr ) && $oria_art_pr ? $oria_art_pr[0] : null;
}
$oria_art_mark  = $oria_art_term && isset( $oria_art_marks[ $oria_art_term->slug ] ) ? $oria_art_marks[ $oria_art_term->slug ] : '◦';
$oria_art_label = $oria_art_term ? \Oria\Theme\tname( $oria_art_term ) : __( 'Wellness event', 'oria' );
?>
<div class="evart" aria-hidden="true">
	<span class="evart__ring"></span>
	<span class="evart__mark"><?php echo esc_html( $oria_art_mark ); ?></span>
	<span class="evart__label micro"><?php echo esc_html( $oria_art_label ); ?></span>
</div>
