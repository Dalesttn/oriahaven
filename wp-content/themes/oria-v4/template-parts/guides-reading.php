<?php
/**
 * The category page's reading list (October 2026 redesign,
 * DESIGN/October 2026/oria-haven-guides-tab-redesign-claude.md).
 *
 * Two real columns of image-on-top guide cards, each one link: topic and
 * reading time, the full title, a short excerpt cut at a sentence, and a
 * "Read guide" line at the foot. One guide gets one wider split card
 * rather than a lonely half-width one. No guides, no section: the
 * category page no longer pads this with the journal's latest posts.
 *
 * The journal index and the directory keep guides-floor.php.
 *
 * Args: guides (list<WP_Post>), topic (e.g. "spa & recovery").
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$gd_posts = array_slice( array_values( (array) ( $args['guides'] ?? array() ) ), 0, 4 );
if ( ! $gd_posts ) {
	return;
}
$gd_topic = (string) ( $args['topic'] ?? '' );

/**
 * An excerpt of whole sentences, up to about 40 words; failing that, a
 * plain word trim.
 */
$gd_excerpt = static function ( WP_Post $p ): string {
	if ( ! has_excerpt( $p ) ) {
		return '';
	}
	$text = trim( wp_strip_all_tags( (string) get_the_excerpt( $p ) ) );
	if ( str_word_count( $text ) <= 40 ) {
		return $text;
	}
	$out = '';
	foreach ( preg_split( '/(?<=[.!?])\s+/', $text ) ?: array() as $s ) {
		if ( str_word_count( $out . ' ' . $s ) > 40 ) {
			break;
		}
		$out = trim( $out . ' ' . $s );
	}
	return '' !== $out ? $out : wp_trim_words( $text, 32 );
};
?>
<section class="gd wrap" id="guides" aria-labelledby="guides-title">
	<header class="gd__head">
		<div>
			<p class="gd__eyebrow"><?php esc_html_e( 'The Oria reading list', 'oria' ); ?></p>
			<h2 class="gd__title" id="guides-title"><?php esc_html_e( 'A little insight before you go', 'oria' ); ?></h2>
			<?php if ( '' !== $gd_topic ) : ?>
				<p class="gd__lede">
					<?php
					/* translators: %s: category or treatment, lower case */
					printf( esc_html__( 'Practical guides to help you choose your next %s experience.', 'oria' ), esc_html( $gd_topic ) );
					?>
				</p>
			<?php endif; ?>
		</div>
		<a class="gd__all" href="<?php echo esc_url( home_url( '/journal/' ) ); ?>" data-oria-event="category_guides_all"><?php esc_html_e( 'Explore all guides', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
	</header>

	<ul class="gd__grid<?php echo 1 === count( $gd_posts ) ? ' is-single' : ''; ?>">
		<?php foreach ( $gd_posts as $gd_p ) : ?>
			<?php $gd_ex = $gd_excerpt( $gd_p ); ?>
			<li>
				<a class="gd__card" href="<?php echo esc_url( (string) get_permalink( $gd_p ) ); ?>" data-oria-event="category_guide_click">
					<span class="gd__pic">
						<?php
						if ( has_post_thumbnail( $gd_p ) ) {
							// Decorative: the card is named by its title.
							echo get_the_post_thumbnail( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								$gd_p,
								'large',
								array(
									'loading' => 'lazy',
									'alt'     => '',
									'sizes'   => '(min-width: 64rem) 620px, (min-width: 44rem) 50vw, 100vw',
								)
							);
						}
						?>
					</span>
					<span class="gd__body">
						<span class="gd__meta"><?php echo \Oria\Theme\article_meta( (int) $gd_p->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<h3 class="gd__name"><?php echo esc_html( \Oria\Theme\ptitle( $gd_p ) ); ?></h3>
						<?php if ( '' !== $gd_ex ) : ?>
							<span class="gd__ex"><?php echo esc_html( $gd_ex ); ?></span>
						<?php endif; ?>
						<span class="gd__read"><?php esc_html_e( 'Read guide', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></span>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
