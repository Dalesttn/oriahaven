<?php
/**
 * Last-resort fallback template. Everything real has its own template;
 * anything that lands here just gets a clean list.
 */

declare(strict_types=1);

get_header();
?>

<section class="wrap pagehead">
	<h1 class="h1 pagehead__title"><?php the_archive_title(); ?></h1>
</section>

<section class="wrap section section--top-flush">
	<?php if ( have_posts() ) : ?>
		<div class="grid grid-3">
			<?php while ( have_posts() ) : the_post(); ?>
				<a class="article" href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="article__img"><?php the_post_thumbnail( 'oria-card' ); ?></div>
					<?php endif; ?>
					<h2 class="article__title"><?php the_title(); ?></h2>
				</a>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p class="muted"><?php esc_html_e( 'Nothing here yet.', 'oria' ); ?></p>
	<?php endif; ?>
</section>

<?php
get_footer();
