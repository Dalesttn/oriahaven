<?php
/**
 * Section: latest journal posts
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

$oria_posts = get_posts( array( 'posts_per_page' => 3 ) );
if ( ! $oria_posts ) {
	return;
}
?>
<section class="section<?php echo esc_attr( sband( $s ) ); ?>">
	<div class="wrap">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
				<h2 class="h2"><?php echo esc_html( $t('heading') ); ?></h2>
			</div>
			<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'All guides', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></a>
		</div>

		<div class="grid grid-3">
			<?php foreach ( $oria_posts as $oria_post ) : ?>
				<a class="article reveal" href="<?php echo esc_url( get_permalink( $oria_post ) ); ?>">
					<?php if ( has_post_thumbnail( $oria_post ) ) : ?>
						<div class="article__img"><?php echo get_the_post_thumbnail( $oria_post, 'oria-card', array( 'loading' => 'lazy' ) ); ?></div>
					<?php endif; ?>
					<div class="article__meta"><?php echo \Oria\Theme\article_meta( $oria_post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<h3 class="article__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_post ) ); ?></h3>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
