<?php
/**
 * A band of featured listings — the paid placement surface used on the
 * category landing pages and the events archive. Renders nothing when
 * nothing is featured, so it never shows an empty promise.
 *
 * Args: 'posts' (WP_Post[]), 'heading' (string).
 */

declare(strict_types=1);

$oria_band_posts = isset( $args['posts'] ) && is_array( $args['posts'] ) ? $args['posts'] : array();
if ( ! $oria_band_posts ) {
	return;
}
$oria_band_heading = (string) ( $args['heading'] ?? __( 'Featured practices', 'oria' ) );
?>
<div class="featband">
	<p class="featband__label"><span class="badge-dot" aria-hidden="true"></span><span class="micro"><?php echo esc_html( $oria_band_heading ); ?></span></p>
	<div class="featband__grid">
		<?php
		global $post;
		foreach ( $oria_band_posts as $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $post );
			get_template_part( 'template-parts/listing', 'card' );
		endforeach;
		wp_reset_postdata();
		?>
	</div>
</div>
