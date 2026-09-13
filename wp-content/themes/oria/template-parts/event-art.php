<?php
/**
 * The stand-in hero for events with no photo of their own (aggregated finds,
 * member events awaiting an upload): one of the site's own scenes, chosen by
 * event type in event_scene(), with the type named over it. Always ours,
 * never someone else's copyrighted banner.
 *
 * Args: 'event_id' (int).
 */

declare(strict_types=1);

$oria_art_id = (int) ( $args['event_id'] ?? 0 );
if ( ! $oria_art_id ) {
	return;
}

$oria_art_terms = wp_get_post_terms( $oria_art_id, 'event_type' );
$oria_art_term  = ! is_wp_error( $oria_art_terms ) && $oria_art_terms ? $oria_art_terms[0] : null;
if ( ! $oria_art_term ) {
	$oria_art_pr   = wp_get_post_terms( $oria_art_id, 'practice' );
	$oria_art_term = ! is_wp_error( $oria_art_pr ) && $oria_art_pr ? $oria_art_pr[0] : null;
}
$oria_art_label = $oria_art_term ? \Oria\Theme\tname( $oria_art_term ) : __( 'Wellness event', 'oria' );
?>
<div class="evart evart--scene" aria-hidden="true">
	<img class="evart__scene" src="<?php echo esc_url( \Oria\Theme\event_scene( $oria_art_id ) ); ?>" alt="" decoding="async">
	<span class="evart__label micro"><?php echo esc_html( $oria_art_label ); ?></span>
</div>
