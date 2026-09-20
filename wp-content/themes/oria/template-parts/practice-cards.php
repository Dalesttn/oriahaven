<?php
/**
 * A short grid of practice cards.
 *
 * Six categories with their own photograph, a line somebody wrote, and a
 * count kept quiet underneath. A visitor should read "Yoga" before they
 * read "51" -- the number is there to reassure, not to choose for them.
 *
 * @var array $args {
 *     @type array  $cards   Rows: term, url, count, line, image.
 *     @type string $heading The section heading.
 *     @type string $lede    One sentence under it.
 *     @type string $id      Heading id, for aria-labelledby.
 *     @type string $more    Optional "everything" link.
 *     @type string $more_label Its text.
 * }
 */

declare(strict_types=1);

$oria_cards = array_values( (array) ( $args['cards'] ?? array() ) );
if ( count( $oria_cards ) < 3 ) {
	return;
}

$oria_id = (string) ( $args['id'] ?? 'pcards-title' );
?>
<section class="pcards" aria-labelledby="<?php echo esc_attr( $oria_id ); ?>">
	<header class="pcards__head">
		<div>
			<h2 class="h3 pcards__title" id="<?php echo esc_attr( $oria_id ); ?>"><?php echo esc_html( (string) ( $args['heading'] ?? __( 'Explore popular practices', 'oria' ) ) ); ?></h2>
			<?php if ( ! empty( $args['lede'] ) ) : ?>
				<p class="pcards__lede"><?php echo esc_html( (string) $args['lede'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $args['more'] ) ) : ?>
			<a class="btn btn--sm pcards__more" href="<?php echo esc_url( (string) $args['more'] ); ?>">
				<?php echo esc_html( (string) ( $args['more_label'] ?? __( 'Explore all practices', 'oria' ) ) ); ?> <span aria-hidden="true">&rarr;</span>
			</a>
		<?php endif; ?>
	</header>

	<div class="pcards__grid">
		<?php foreach ( $oria_cards as $oria_c ) : ?>
			<a class="pcard<?php echo '' === (string) $oria_c['image'] ? ' pcard--bare' : ''; ?>"
				href="<?php echo esc_url( (string) $oria_c['url'] ); ?>"
				data-oria-event="explore_category_click">
				<?php if ( '' !== (string) $oria_c['image'] ) : ?>
					<img class="pcard__img" src="<?php echo esc_url( (string) $oria_c['image'] ); ?>" alt=""
						width="640" height="480" loading="lazy" decoding="async">
				<?php endif; ?>
				<span class="pcard__veil" aria-hidden="true"></span>
				<span class="pcard__body">
					<b class="pcard__name"><?php echo esc_html( (string) $oria_c['name'] ); ?></b>
					<?php if ( '' !== (string) $oria_c['line'] ) : ?>
						<span class="pcard__line"><?php echo esc_html( (string) $oria_c['line'] ); ?></span>
					<?php endif; ?>
					<span class="pcard__n">
						<?php
						printf(
							/* translators: %s: number of places */
							esc_html( _n( '%s place', '%s places', (int) $oria_c['count'], 'oria' ) ),
							esc_html( number_format_i18n( (int) $oria_c['count'] ) )
						);
						?>
						<span class="pcard__arrow" aria-hidden="true">&rarr;</span>
					</span>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
