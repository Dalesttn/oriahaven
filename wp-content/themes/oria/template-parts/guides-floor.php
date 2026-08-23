<?php
/**
 * Floor 4 — Guides. A row of journal cards (featured image, date, title,
 * excerpt) for the guides related to this page. Same card the journal
 * index uses, so the two read as one family.
 *
 * @var array $args {
 *     @type list<WP_Post> $guides  The posts to show.
 *     @type string        $heading Section heading.
 *     @type string        $icon    Optional inline SVG used where a post has no image.
 * }
 */

declare(strict_types=1);

$oria_guides  = isset( $args['guides'] ) && is_array( $args['guides'] ) ? $args['guides'] : array();
$oria_heading = (string) ( $args['heading'] ?? __( 'Guides worth reading first', 'oria' ) );
$oria_icon    = (string) ( $args['icon'] ?? '' );

if ( ! $oria_guides ) {
	return;
}
?>
<section class="wrap section section--top-flush floor" id="guides">
	<h2 class="micro floor__label"><?php esc_html_e( 'Guides', 'oria' ); ?></h2>
	<div class="guides__head">
		<h2 class="h3"><?php echo esc_html( $oria_heading ); ?></h2>
		<a class="guides__all" href="<?php echo esc_url( home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'All guides', 'oria' ); ?> <span aria-hidden="true">→</span></a>
	</div>
	<div class="grid grid-3 guidegrid">
		<?php foreach ( $oria_guides as $oria_i => $oria_g ) : ?>
			<a class="article guidecard" href="<?php echo esc_url( (string) get_permalink( $oria_g ) ); ?>" style="--i:<?php echo (int) $oria_i; ?>">
				<?php if ( has_post_thumbnail( $oria_g ) ) : ?>
					<div class="article__img"><?php echo get_the_post_thumbnail( $oria_g, 'oria-card', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<?php else : ?>
					<div class="article__img article__img--empty" aria-hidden="true"><?php echo $oria_icon; // phpcs:ignore WordPress.Security.EscapeOutput -- inline SVG from the plugin's own assets ?></div>
				<?php endif; ?>
				<div class="article__meta"><?php echo \Oria\Theme\article_meta( (int) $oria_g->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<h3 class="article__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_g ) ); ?></h3>
				<?php if ( has_excerpt( $oria_g ) ) : ?>
					<p class="article__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $oria_g ), 22 ) ); ?></p>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
</section>
