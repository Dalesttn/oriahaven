<?php
/**
 * "What's on" on a page that is not the events archive.
 *
 * One component for the practice profile, the category page and the suburb
 * page, so an event looks the same wherever it turns up and a change to the
 * card is a change everywhere. Renders nothing at all when there is nothing
 * to show: an empty module is worse than no module.
 *
 * @var array $args {
 *     @type int[]  $ids    Event IDs, from \Oria\Core\Events.
 *     @type string $title  Section heading.
 *     @type string $all    Optional "see everything" URL.
 *     @type string $all_label Optional label for that link.
 * }
 */

declare(strict_types=1);

$oria_ids = array_values( array_filter( array_map( 'intval', (array) ( $args['ids'] ?? array() ) ) ) );
if ( ! $oria_ids ) {
	return;
}

$oria_title     = (string) ( $args['title'] ?? __( "What's on", 'oria' ) );
$oria_all       = (string) ( $args['all'] ?? '' );
$oria_all_label = (string) ( $args['all_label'] ?? __( 'See all events', 'oria' ) );
?>
<section class="wrap section section--top-flush evmod">
	<div class="evmod__head">
		<h2 class="h3"><?php echo esc_html( $oria_title ); ?></h2>
		<?php if ( '' !== $oria_all ) : ?>
			<a class="evmod__all" href="<?php echo esc_url( $oria_all ); ?>"><?php echo esc_html( $oria_all_label ); ?></a>
		<?php endif; ?>
	</div>
	<div class="evgrid">
		<?php
		foreach ( $oria_ids as $oria_eid ) :
			$oria_start = strtotime( (string) get_post_meta( $oria_eid, 'event_start', true ) );
			$oria_venue = (string) get_post_meta( $oria_eid, 'venue', true );
			$oria_price = (string) get_post_meta( $oria_eid, 'price', true );
			$oria_terms = wp_get_post_terms( $oria_eid, 'event_type' );
			$oria_type  = ! is_wp_error( $oria_terms ) && $oria_terms ? \Oria\Theme\tname( $oria_terms[0] ) : '';
			?>
			<a class="evcard" href="<?php echo esc_url( (string) get_permalink( $oria_eid ) ); ?>">
				<?php if ( $oria_start ) : ?>
					<span class="micro"><?php echo esc_html( wp_date( 'D j M', $oria_start ) . ( '00:00' !== wp_date( 'H:i', $oria_start ) ? ' · ' . wp_date( 'g.ia', $oria_start ) : '' ) ); ?></span>
				<?php endif; ?>
				<b class="evcard__name"><?php echo esc_html( \Oria\Theme\ptitle( $oria_eid ) ); ?></b>
				<span class="evcard__meta">
					<?php echo esc_html( implode( ' · ', array_filter( array( $oria_type, $oria_venue, $oria_price ) ) ) ); ?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
