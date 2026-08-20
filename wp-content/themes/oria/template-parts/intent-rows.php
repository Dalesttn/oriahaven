<?php
/**
 * The intent rows: a short table of what people are actually looking for
 * inside a category, with counts and links to those listings.
 *
 * A table rather than a row of cards, deliberately. Cards look better and
 * extract worse — an answer engine lifting a card grid gets a column of
 * orphaned numbers, where a two-column table keeps each count attached to
 * the thing it counts.
 *
 * @var array $args {
 *     @type WP_Term $term Practice term the page is about.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oria_term = $args['term'] ?? null;

if ( ! $oria_term instanceof WP_Term || ! function_exists( '\Oria\Core\Intents\for_practice' ) ) {
	return;
}

$oria_rows = \Oria\Core\Intents\for_practice( $oria_term );

if ( count( $oria_rows ) < 2 ) {
	return;
}

$oria_name    = \Oria\Theme\tname( $oria_term );
$oria_summary = \Oria\Core\Intents\summary( $oria_term, $oria_rows );
?>

<section class="wrap section section--top-flush">
	<h2 class="h3 intents__title">
		<?php
		/* translators: %s: category name, e.g. "Yoga & Pilates". */
		printf( esc_html__( 'Find your kind of %s', 'oria' ), esc_html( strtolower( $oria_name ) ) );
		?>
	</h2>

	<?php if ( '' !== $oria_summary ) : ?>
		<p class="intents__lede"><?php echo esc_html( $oria_summary ); ?></p>
	<?php endif; ?>

	<div class="intents">
		<table class="intents__table">
			<caption class="sr-only">
				<?php
				/* translators: %s: category name. */
				printf( esc_html__( 'What is on offer within %s, and how many practices offer each', 'oria' ), esc_html( $oria_name ) );
				?>
			</caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Looking for', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Practices', 'oria' ); ?></th>
					<th scope="col"><span class="sr-only"><?php esc_html_e( 'Link', 'oria' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $oria_rows as $oria_row ) : ?>
					<tr>
						<th scope="row"><a href="<?php echo esc_url( $oria_row['url'] ); ?>"><?php echo esc_html( $oria_row['label'] ); ?></a></th>
						<td><?php echo esc_html( number_format_i18n( (int) $oria_row['count'] ) ); ?></td>
						<td class="intents__go">
							<a href="<?php echo esc_url( $oria_row['url'] ); ?>" aria-label="<?php
								/* translators: %s: intent label, e.g. "Beginner friendly". */
								printf( esc_attr__( 'See %s practices', 'oria' ), esc_attr( $oria_row['label'] ) );
							?>">
								<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11 11 3M5 3h6v6"/></svg>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		/*
		 * The counting rule, said plainly. Every other number on the site is
		 * a simple tally; these are gated, and a reader deserves to know a
		 * row is missing because nobody has checked rather than because
		 * nothing qualifies.
		 */
		?>
		<p class="intents__note">
			<?php esc_html_e( 'A row appears once at least three listed practices publish it. Nothing here is ranked — the count is what practices say they offer, not our judgement of who does it best.', 'oria' ); ?>
		</p>
	</div>
</section>
