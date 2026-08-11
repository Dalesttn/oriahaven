<?php
/**
 * The Journal index — WordPress's posts page. Set a "Journal" page as the
 * posts page under Settings → Reading and this template renders it.
 */

declare(strict_types=1);

get_header();

$oria_first = true;
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Journal', 'oria' ); ?></span>
	</nav>
	<div class="row-between" style="align-items:flex-end;margin-top:1rem">
		<div>
			<span class="micro"><?php esc_html_e( 'The Journal', 'oria' ); ?></span>
			<h1 class="h1 pagehead__title"><?php esc_html_e( 'Guides to Wellness in Perth', 'oria' ); ?></h1>
		</div>
		<p class="lede" style="max-width:36ch"><?php esc_html_e( "Written here, about here. What's on, what things cost, and where to go when you don't know where to start.", 'oria' ); ?></p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<?php if ( have_posts() ) : ?>
		<?php
		// The newest post gets the full-width feature card; the rest a grid.
		while ( have_posts() ) :
			the_post();
			if ( $oria_first ) :
				$oria_first = false;
				?>
				<?php
				/*
				 * Sizing lives in .jlead (pages.css) rather than inline
				 * here: the card has to hold its own height whether or not
				 * the post has a featured image, and it needs a different
				 * shape on a phone than it does across a desktop.
				 */
				?>
				<a class="mediacard jlead reveal" href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'oria-wide', array( 'class' => 'mediacard__img' ) ); ?>
					<?php endif; ?>
					<div class="mediacard__top"><span class="badge badge--free"><?php esc_html_e( 'Latest', 'oria' ); ?></span></div>
					<div class="mediacard__over">
						<div class="mediacard__title"><?php the_title(); ?></div>
						<div class="mediacard__meta"><?php echo esc_html( get_the_excerpt() ); ?></div>
					</div>
				</a>
				<div class="grid grid-3">
				<?php
			else :
				?>
				<a class="article reveal" href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="article__img"><?php the_post_thumbnail( 'oria-card', array( 'loading' => 'lazy' ) ); ?></div>
					<?php endif; ?>
					<div class="article__meta"><?php echo \Oria\Theme\article_meta( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<h2 class="article__title"><?php the_title(); ?></h2>
				</a>
				<?php
			endif;
		endwhile;
		?>
		</div>
		<div style="margin-top:3rem"><?php the_posts_pagination(); ?></div>
	<?php else : ?>
		<p class="muted"><?php esc_html_e( 'No articles yet — the first guides are being written.', 'oria' ); ?></p>
	<?php endif; ?>
</section>

<?php
get_footer();
