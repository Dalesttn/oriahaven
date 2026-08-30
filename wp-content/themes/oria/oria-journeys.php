<?php
/**
 * Wellness Journeys index.
 *
 * The cards carry two facts the journal cards do not: how many stops the day
 * has, and the hours it runs between. Both are read from the steps, so they
 * cannot describe a day the article no longer contains -- and both are the
 * things somebody actually decides on, which is whether they have the day
 * free at all.
 */

declare(strict_types=1);

use Oria\Core\Journeys;

get_header();

$oria_journeys = Journeys\posts();
?>

<section class="wrap pagehead">
	<span class="micro"><?php esc_html_e( 'Perth', 'oria' ); ?></span>
	<h1 class="h1 pagehead__title"><?php echo esc_html( Journeys\heading() ); ?></h1>
	<p class="pagehead__lede"><?php echo esc_html( Journeys\lede() ); ?></p>
</section>

<section class="wrap section section--top-flush">
	<?php if ( $oria_journeys ) : ?>
		<div class="grid grid-3">
			<?php
			foreach ( $oria_journeys as $oria_post ) :
				$oria_shape = Journeys\shape( $oria_post->ID );
				$oria_bits  = array_filter(
					array(
						$oria_shape['stops'] ? sprintf(
							/* translators: %s: number of stops in the day. */
							_n( '%s stop', '%s stops', $oria_shape['stops'], 'oria' ),
							number_format_i18n( $oria_shape['stops'] )
						) : '',
						$oria_shape['span'],
					)
				);
				?>
				<a class="article reveal" href="<?php echo esc_url( (string) get_permalink( $oria_post ) ); ?>">
					<?php if ( has_post_thumbnail( $oria_post ) ) : ?>
						<div class="article__img"><?php echo get_the_post_thumbnail( $oria_post, 'oria-card', array( 'loading' => 'lazy' ) ); ?></div>
					<?php endif; ?>
					<?php if ( $oria_bits ) : ?>
						<div class="article__meta"><?php echo esc_html( implode( ' · ', $oria_bits ) ); ?></div>
					<?php endif; ?>
					<h2 class="article__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_post ) ); ?></h2>
					<?php if ( $oria_post->post_excerpt ) : ?>
						<p class="article__excerpt"><?php echo esc_html( wp_trim_words( $oria_post->post_excerpt, 26 ) ); ?></p>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<?php
		/*
		 * An empty index is a real state on a new site, and the honest thing
		 * is to send people somewhere rather than apologise at them.
		 */
		?>
		<p class="muted"><?php esc_html_e( 'The first journeys are being written. In the meantime, the journal has the guides they are built from.', 'oria' ); ?></p>
		<p><a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'Read the journal', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></p>
	<?php endif; ?>
</section>

<?php
get_footer();
