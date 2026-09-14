<?php
/**
 * One Best Of guide as a card: on the hub, in "You might also like", and on
 * the home page. Two sizes -- the feature is the same parts with room.
 *
 * $args: post (WP_Post|int), feature (bool)
 */

declare(strict_types=1);

use Oria\Core\BestOf;

$oria_g = get_post( $args['post'] ?? null );
if ( ! $oria_g instanceof WP_Post ) {
	return;
}
$oria_feature = ! empty( $args['feature'] );
$oria_n       = count( BestOf\entries( $oria_g->ID ) );
$oria_cat     = BestOf\category_label( BestOf\category( $oria_g->ID ) );
$oria_meta    = array_filter(
	array(
		$oria_cat,
		$oria_n ? sprintf(
			/* translators: %s: number of practices in the guide */
			_n( '%s place', '%s places', $oria_n, 'oria' ),
			number_format_i18n( $oria_n )
		) : '',
		BestOf\reviewed_month( $oria_g->ID ),
	)
);
?>
<a class="bocard<?php echo $oria_feature ? ' bocard--feature' : ''; ?> reveal" href="<?php echo esc_url( (string) get_permalink( $oria_g ) ); ?>">
	<?php if ( has_post_thumbnail( $oria_g ) ) : ?>
		<div class="bocard__img"><?php echo get_the_post_thumbnail( $oria_g, $oria_feature ? 'large' : 'oria-card', array( 'loading' => $oria_feature ? 'eager' : 'lazy' ) ); ?></div>
	<?php else : ?>
		<?php
		/*
		 * No cover yet: the first pick's photo stands in, so a guide never
		 * ships as a blank tile. Editors can set a cover any time.
		 */
		$oria_first = BestOf\entries( $oria_g->ID )[0]['listing'] ?? 0;
		?>
		<div class="bocard__img<?php echo $oria_first ? '' : ' bocard__img--empty'; ?>">
			<?php if ( $oria_first ) : ?>
				<img src="<?php echo esc_url( \Oria\Theme\listing_image( $oria_first, $oria_feature ? 'large' : 'oria-card' ) ); ?>" alt="" loading="lazy">
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<div class="bocard__body">
		<?php if ( $oria_meta ) : ?>
			<span class="bocard__meta"><?php echo esc_html( implode( ' · ', $oria_meta ) ); ?></span>
		<?php endif; ?>
		<h3 class="bocard__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_g ) ); ?></h3>
		<?php if ( $oria_g->post_excerpt ) : ?>
			<p class="bocard__excerpt"><?php echo esc_html( wp_trim_words( $oria_g->post_excerpt, $oria_feature ? 36 : 22 ) ); ?></p>
		<?php endif; ?>
		<span class="bocard__go"><?php esc_html_e( 'View guide', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	</div>
</a>
