<?php
/**
 * A journal article — magazine treatment.
 *
 * Reading progress bar, byline with author avatar, an animated pull quote
 * under the cover image, drop-cap opening, an optional photo-essay strip,
 * an author card, then related articles. The pull quote and photo fields
 * are optional; the page degrades to a clean plain article without them.
 */

declare(strict_types=1);

get_header();

while ( have_posts() ) :
	the_post();

	$oria_author  = (int) get_the_author_meta( 'ID' );
	$oria_role    = function_exists( 'get_field' ) ? (string) get_field( 'author_role', 'user_' . $oria_author ) : '';
	$oria_bio     = (string) get_the_author_meta( 'description' );
	$oria_quote   = function_exists( 'get_field' ) ? trim( (string) get_field( 'pull_quote' ) ) : '';
	$oria_quoteby = function_exists( 'get_field' ) ? trim( (string) get_field( 'pull_quote_by' ) ) : '';
	$oria_photos  = function_exists( 'get_field' ) ? array_filter( array_map( 'intval', (array) ( get_field( 'journal_gallery' ) ?: array() ) ) ) : array();
	?>

	<div class="readbar" aria-hidden="true"><span class="readbar__fill" data-readbar></span></div>

	<section class="wrap pagehead">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'Journal', 'oria' ); ?></a>
			<span aria-hidden="true">/</span><span><?php the_title(); ?></span>
		</nav>
		<div style="margin-top:1rem;max-width:56rem">
			<span class="micro"><?php
				$oria_cat = get_the_category()[0] ?? null;
				echo esc_html( implode( ' · ', array_filter( array(
					$oria_cat && 'Uncategorized' !== $oria_cat->name ? \Oria\Theme\tname( $oria_cat ) : '',
					sprintf( __( '%d min read', 'oria' ), \Oria\Theme\reading_time( get_the_ID() ) ),
				) ) ) );
			?></span>
			<h1 class="h1 pagehead__title"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="lede pagehead__lede"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>

			<div class="byline">
				<?php echo \Oria\Theme\author_avatar( $oria_author, 44 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="byline__who">
					<b><?php the_author(); ?></b>
					<em><?php echo esc_html( implode( ' · ', array_filter( array( $oria_role, get_the_date() ) ) ) ); ?></em>
				</span>
				<button class="btn btn--ghost btn--sm btn--plain byline__share" type="button" data-share
					data-copied="<?php esc_attr_e( 'Link copied', 'oria' ); ?>"><?php esc_html_e( 'Share', 'oria' ); ?></button>
			</div>
		</div>
	</section>

	<?php if ( has_post_thumbnail() ) : ?>
	<section class="wrap">
		<?php
		/*
		 * A figure, not a div: the caption is part of the picture, and the
		 * class carries the settle-on-load treatment the design asks for.
		 */
		$oria_thumb = get_post( get_post_thumbnail_id() );
		$oria_cap   = $oria_thumb ? trim( (string) $oria_thumb->post_excerpt ) : '';
		?>
		<figure class="herofig">
			<?php the_post_thumbnail( 'oria-wide' ); ?>
			<?php if ( '' !== $oria_cap ) : ?>
				<figcaption><?php echo esc_html( $oria_cap ); ?></figcaption>
			<?php endif; ?>
		</figure>
	</section>
	<?php endif; ?>

	<?php if ( $oria_quote ) : ?>
	<section class="wrap section section--tight">
		<blockquote class="pullquote reveal">
			<p class="pullquote__text" data-pullquote><?php echo esc_html( $oria_quote ); ?></p>
			<?php if ( $oria_quoteby ) : ?>
				<footer class="pullquote__by"><?php echo esc_html( $oria_quoteby ); ?></footer>
			<?php endif; ?>
		</blockquote>
	</section>
	<?php endif; ?>

	<?php
	/*
	 * The sidebar: places to actually do what the article talks about.
	 * Featured practices always lead; the rest are drawn at random each
	 * view so every listing gets a turn.
	 */
	$oria_practices = \Oria\Theme\journal_practices( get_the_ID() );
	$oria_areas     = \Oria\Theme\journal_areas( get_the_ID() );
	$oria_side      = array();

	if ( $oria_practices ) {
		$oria_slugs = wp_list_pluck( array_slice( $oria_practices, 0, 2 ), 'slug' );
		$oria_where = wp_list_pluck( $oria_areas, 'slug' );

		/**
		 * The sidebar for one set of area slugs; empty means anywhere.
		 *
		 * @param list<string> $areas
		 * @return array<int, array{0: \WP_Post, 1: bool}>
		 */
		$oria_pick = static function ( array $areas ) use ( $oria_slugs ): array {
			$out = array();
			foreach ( $oria_slugs as $oria_slug ) {
				foreach ( \Oria\Theme\featured_listings( 2, $oria_slug, $areas ) as $oria_f ) {
					$out[ $oria_f->ID ] = array( $oria_f, true );
				}
			}
			$out = array_slice( $out, 0, 2, true );

			$tax = array(
				array(
					'taxonomy' => 'practice',
					'field'    => 'slug',
					'terms'    => $oria_slugs,
				),
			);
			if ( $areas ) {
				$tax[] = array(
					'taxonomy'         => 'area',
					'field'            => 'slug',
					'terms'            => $areas,
					'include_children' => true,
				);
			}

			$fill = get_posts(
				array(
					'post_type'      => 'listing',
					'posts_per_page' => 3,
					'orderby'        => 'rand',
					'post__not_in'   => array_keys( $out ),
					'tax_query'      => $tax, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				)
			);
			foreach ( $fill as $oria_f ) {
				if ( count( $out ) >= 3 ) {
					break;
				}
				$out[ $oria_f->ID ] = array( $oria_f, false );
			}
			return $out;
		};

		$oria_side = $oria_pick( $oria_where );

		/*
		 * Nothing of that kind in that area is ordinary while the directory
		 * fills out — a category with three listings and a region with none
		 * of them. Widening beats an empty box, but the heading has to stop
		 * claiming the area at the same moment, which is what clearing
		 * $oria_areas here does.
		 */
		if ( ! $oria_side && $oria_where ) {
			$oria_side  = $oria_pick( array() );
			$oria_areas = array();
		}
	}
	?>

	<section class="wrap section section--tight">
		<div class="<?php echo $oria_side ? 'jlayout' : ''; ?>">
			<div class="prose <?php echo $oria_side ? '' : 'prose--wide'; ?> prose--article">
				<?php the_content(); ?>
			</div>
			<?php if ( $oria_side ) : ?>
			<aside class="jside">
				<?php
				/*
				 * In this guide -- built from the article's own H2s, anchors
				 * minted by the same function that stamps the ids. Only
				 * offered when there is enough article to need a map.
				 */
				$oria_toc = \Oria\Theme\article_toc( (string) get_post_field( 'post_content', get_the_ID(), 'raw' ) );
				?>
				<?php if ( count( $oria_toc ) >= 3 ) : ?>
					<nav class="jtoc" aria-label="<?php esc_attr_e( 'In this guide', 'oria' ); ?>">
						<span class="micro"><?php esc_html_e( 'In this guide', 'oria' ); ?></span>
						<ol class="jtoc__list">
							<?php foreach ( $oria_toc as $oria_t ) : ?>
								<li><a href="#<?php echo esc_attr( $oria_t['id'] ); ?>"><?php echo esc_html( $oria_t['title'] ); ?></a></li>
							<?php endforeach; ?>
						</ol>
					</nav>
				<?php endif; ?>
				<div class="card">
					<div class="card__body">
						<span class="micro" style="display:block;margin-bottom:.35rem"><?php esc_html_e( 'Try it in person', 'oria' ); ?></span>
						<?php
						// "Retreats & day escapes in the Perth Hills" beats
						// "…in Perth" when we know the article is local.
						$oria_place = count( $oria_areas ) === 1
							? \Oria\Theme\tname( $oria_areas[0] )
							: __( 'Perth', 'oria' );
						?>
						<h2 class="h3" style="font-size:1.05rem;margin-bottom:1rem">
							<?php
							printf(
								/* translators: 1: practice name, 2: place name */
								esc_html__( '%1$s in %2$s', 'oria' ),
								esc_html( \Oria\Theme\tname( $oria_practices[0] ) ),
								esc_html( $oria_place )
							);
							?>
						</h2>
						<div class="stack" style="font-size:.9375rem">
							<?php $oria_k = 0; foreach ( $oria_side as $oria_pair ) : list( $oria_l, $oria_is_feat ) = $oria_pair; ?>
								<?php if ( $oria_k++ > 0 ) : ?><hr class="hr"><?php endif; ?>
								<a class="row-between" href="<?php echo esc_url( get_permalink( $oria_l ) ); ?>">
									<span>
										<b><?php echo esc_html( \Oria\Theme\ptitle( $oria_l ) ); ?></b>
										<?php if ( $oria_is_feat ) : ?> <i class="wkrow__flag"><?php esc_html_e( 'Featured', 'oria' ); ?></i><?php endif; ?><br>
										<span class="muted" style="font-size:.8125rem"><?php echo esc_html( \Oria\Theme\tname( wp_get_post_terms( $oria_l->ID, 'area' )[0] ?? null ) ); ?></span>
									</span>
								</a>
							<?php endforeach; ?>
						</div>
						<?php
						/*
						 * With an area in play, /practice/{practice}/{area}/
						 * is a real page and a far better destination than the
						 * whole-metro category — it is the page this article
						 * is effectively an essay about.
						 */
						$oria_seeall = (string) get_term_link( $oria_practices[0] );
						if ( count( $oria_areas ) === 1 ) {
							$oria_seeall = home_url( '/practice/' . $oria_practices[0]->slug . '/' . $oria_areas[0]->slug . '/' );
						}
						?>
						<a class="btn btn--ghost btn--sm btn--plain" style="margin-top:1.1rem" href="<?php echo esc_url( $oria_seeall ); ?>"><?php esc_html_e( 'See all', 'oria' ); ?> <?php echo \Oria\Theme\arrow(); // phpcs:ignore ?></a>
					</div>
				</div>
			</aside>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $oria_photos ) : ?>
	<section class="wrap section section--top-flush">
		<span class="micro" style="display:block;margin-bottom:1rem"><?php esc_html_e( 'In pictures', 'oria' ); ?></span>
		<div class="jgallery">
			<?php foreach ( $oria_photos as $oria_img ) : ?>
				<figure class="jgallery__item reveal">
					<?php echo wp_get_attachment_image( $oria_img, 'large', false, array( 'loading' => 'lazy' ) ); ?>
					<?php $oria_cap = wp_get_attachment_caption( $oria_img ); ?>
					<?php if ( $oria_cap ) : ?>
						<figcaption><?php echo esc_html( $oria_cap ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php $oria_shop = function_exists( '\Oria\Shop\Render\auto_band' ) ? \Oria\Shop\Render\auto_band( __( 'Products you might find helpful', 'oria' ) ) : ''; ?>
	<?php if ( $oria_shop ) : ?>
	<section class="wrap section section--top-flush"><?php echo $oria_shop; // phpcs:ignore WordPress.Security.EscapeOutput ?></section>
	<?php endif; ?>

	<section class="wrap section section--top-flush">
		<div class="authorcard reveal">
			<?php echo \Oria\Theme\author_avatar( $oria_author, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div>
				<span class="micro"><?php esc_html_e( 'Written by', 'oria' ); ?></span>
				<b class="authorcard__name"><?php the_author(); ?></b>
				<?php if ( $oria_role ) : ?><em class="authorcard__role"><?php echo esc_html( $oria_role ); ?></em><?php endif; ?>
				<?php if ( $oria_bio ) : ?><p class="authorcard__bio"><?php echo esc_html( $oria_bio ); ?></p><?php endif; ?>
			</div>
		</div>
	</section>

	<?php
	/*
	 * More from the journal: posts sharing a category first, topped up with
	 * the most recent so the rail is never half-empty.
	 */
	$oria_cats    = wp_get_post_categories( get_the_ID() );
	$oria_args    = array(
		'posts_per_page'      => 3,
		'post__not_in'        => array( get_the_ID() ),
		'ignore_sticky_posts' => true,
	);
	$oria_related = $oria_cats
		? get_posts( $oria_args + array( 'category__in' => $oria_cats ) )
		: array();

	if ( count( $oria_related ) < 3 ) {
		$oria_related = array_merge(
			$oria_related,
			get_posts(
				array(
					'posts_per_page'      => 3 - count( $oria_related ),
					'post__not_in'        => array_merge( array( get_the_ID() ), wp_list_pluck( $oria_related, 'ID' ) ),
					'ignore_sticky_posts' => true,
				)
			)
		);
	}

	if ( $oria_related ) :
		?>
	<section class="section band-sand">
		<div class="wrap">
			<div class="sec-head reveal">
				<div class="sec-head__text">
					<span class="micro"><?php esc_html_e( 'Keep reading', 'oria' ); ?></span>
					<h2 class="h2"><?php esc_html_e( 'More from the journal', 'oria' ); ?></h2>
				</div>
				<div class="sec-head__aside">
					<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'All guides', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				</div>
			</div>

			<div class="grid grid-3">
				<?php foreach ( $oria_related as $oria_post ) : ?>
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
	<?php endif; ?>
	<?php
endwhile;

get_footer();
