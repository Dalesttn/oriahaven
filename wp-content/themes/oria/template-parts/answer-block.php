<?php
/**
 * The answer block: the page's own facts, set before anything else.
 *
 * Rendered server-side and placed above the filter rail in document order,
 * because the point of it is to be the first thing read — by a person
 * skimming and by anything retrieving the page and reading from the top.
 *
 * @var array $args {
 *     @type WP_Term $term Term the page is about.
 *     @type WP_Term $area Optional second facet, for combo pages.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oria_term = $args['term'] ?? null;
$oria_area = $args['area'] ?? null;

if ( ! $oria_term instanceof WP_Term || ! function_exists( '\Oria\Core\Answer\for_term' ) ) {
	return;
}

$oria_answer = \Oria\Core\Answer\for_term(
	$oria_term,
	$oria_area instanceof WP_Term ? $oria_area : null
);

if ( ! $oria_answer['sentences'] ) {
	return;
}

/*
 * Guides sit in the empty half of this row. Only on the term's own page —
 * a combo has narrowed to a suburb and an article about the whole category
 * is no longer the obvious next thing to read.
 */
$oria_guides = ( ! $oria_area instanceof WP_Term && function_exists( '\Oria\Core\Guides\for_term' ) )
	? \Oria\Core\Guides\for_term( $oria_term )
	: array();
?>

<section class="wrap section section--top-flush">
	<div class="answerrow<?php echo $oria_guides ? ' answerrow--split' : ''; ?>">
		<?php
		/*
		 * The featured blocks are a sibling of .answer, not a child of it.
		 * Inside, they sat within the answer's left rule, which reads as
		 * though paid placement were part of the directory's own statement
		 * of fact. It is not, and the markup should not suggest it is.
		 */
		?>
		<div class="answercol">
		<div class="answer">
			<p class="answer__body"><?php echo esc_html( implode( ' ', $oria_answer['sentences'] ) ); ?></p>
			<p class="answer__meta">
				<?php
				printf(
					/* translators: %s: date the directory data last changed. */
					esc_html__( 'Figures are taken from the Oria Haven directory, last updated %s.', 'oria' ),
					esc_html( $oria_answer['updated'] )
				);

				// Only where a price was actually quoted. On a page with no price
				// data the caveat is boilerplate attached to nothing.
				if ( ! empty( $oria_answer['has_prices'] ) ) {
					echo ' ' . esc_html__( 'Prices are set by each practice and change without notice.', 'oria' );
				}
				?>
			</p>

			<?php
			/*
			 * A way past the preamble.
			 *
			 * Measured on the live category pages, the first listing sat 2,084px
			 * down on a desktop and 3,248px down on a phone — three and a half
			 * screens of answer, featured, guides, intent rows and introduction
			 * before the thing the page is named after. Somebody arriving from a
			 * search for "meditation Perth" wants practices.
			 *
			 * The count is repeated here on purpose. It is the fact worth
			 * knowing, and a link that says how much is behind it is a better
			 * invitation than an arrow on its own.
			 */
			if ( $oria_answer['count'] > 0 ) :
				?>
				<a class="answer__jump" href="#dirResults">
					<?php
					printf(
						/* translators: %s: number of listings on this page. */
						esc_html( _n( 'See the %s practice', 'See all %s practices', (int) $oria_answer['count'], 'oria' ) ),
						esc_html( number_format_i18n( (int) $oria_answer['count'] ) )
					);
					?>
					<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3v8M3.5 7.5 7 11l3.5-3.5"/></svg>
				</a>
			<?php endif; ?>
		</div>

		<?php
		if ( ! empty( $args['featured'] ) ) {
			get_template_part(
				'template-parts/featured',
				'mini',
				array(
					'posts'   => $args['featured'],
					'heading' => (string) ( $args['featured_heading'] ?? __( 'Featured practices', 'oria' ) ),
				)
			);
		}
		?>
		</div>

		<?php if ( $oria_guides ) : ?>
			<aside class="guides" aria-labelledby="guidesTitle">
				<h2 class="guides__title" id="guidesTitle"><?php esc_html_e( 'Guides on this', 'oria' ); ?></h2>
				<ul class="guides__list">
					<?php
					foreach ( $oria_guides as $oria_post ) :
						$oria_excerpt = trim( wp_strip_all_tags( (string) get_the_excerpt( $oria_post ) ) );
						?>
						<li class="guides__item">
							<?php
							/*
							 * The whole row is one link, so hovering anywhere in the
							 * block highlights it and the thumbnail is not a second
							 * tab stop to the same place.
							 */
							?>
							<a class="guides__link" href="<?php echo esc_url( (string) get_permalink( $oria_post ) ); ?>">
								<?php
								/*
								 * aria-hidden on the wrapper, not alt="" on the image.
								 * WordPress fills an empty alt from the attachment's own
								 * alt text, so a screen reader heard the article title
								 * twice — once from the thumbnail, once from the link.
								 */
								?>
								<span class="guides__thumb" aria-hidden="true">
									<?php
									if ( has_post_thumbnail( $oria_post ) ) {
										echo get_the_post_thumbnail(
											$oria_post,
											'thumbnail',
											array( 'loading' => 'lazy', 'alt' => '', 'decoding' => 'async' )
										);
									} else {
										// Not every article has art. The placeholder scene is
										// the same guaranteed-to-exist fallback the listing
										// cards use, so the column never goes ragged.
										printf(
											'<img src="%s" alt="" loading="lazy" decoding="async" width="150" height="150">',
											esc_url( get_template_directory_uri() . '/assets/img/scene-hall.webp' )
										);
									}
									?>
								</span>
								<span class="guides__text">
									<span class="guides__name"><?php echo esc_html( wp_specialchars_decode( (string) get_the_title( $oria_post ), ENT_QUOTES ) ); ?></span>
									<?php if ( '' !== $oria_excerpt ) : ?>
										<span class="guides__blurb"><?php echo esc_html( wp_trim_words( $oria_excerpt, 14 ) ); ?></span>
									<?php endif; ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<a class="guides__all" href="<?php echo esc_url( home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'All guides', 'oria' ); ?></a>
			</aside>
		<?php endif; ?>
	</div>
</section>
