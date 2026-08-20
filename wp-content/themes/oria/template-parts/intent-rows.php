<?php
/**
 * The intent rows: a short table of what people are actually looking for
 * inside a category, with counts and links to those listings.
 *
 * A table rather than a row of cards, deliberately. Cards look better and
 * extract worse — an answer engine lifting a card grid gets a column of
 * orphaned numbers, where a table keeps each count attached to the thing it
 * counts.
 *
 * Long categories split into two tables side by side. Massage & Bodywork
 * runs to nine rows, which is a lot of vertical scroll for what is meant to
 * be a glance. Two tables rather than one table in CSS columns, because a
 * multi-column table cannot keep a header over each column and stops making
 * sense to a screen reader.
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

/*
 * Which row the visitor arrived on, so the table shows where they are.
 * Read from the query string rather than from JavaScript: the filter is
 * applied client-side, but the highlight is part of the page and should
 * survive with scripting off.
 */
$oria_active = array(
	'svc'    => isset( $_GET['svc'] ) ? sanitize_title( wp_unslash( (string) $_GET['svc'] ) ) : '',
	'aud'    => isset( $_GET['aud'] ) ? sanitize_title( wp_unslash( (string) $_GET['aud'] ) ) : '',
	'format' => isset( $_GET['format'] ) ? sanitize_key( wp_unslash( (string) $_GET['format'] ) ) : '',
	'price'  => isset( $_GET['price'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['price'] ) ) : '',
);

/** Is this the row the visitor clicked to get here? */
$oria_is_active = static function ( array $row ) use ( $oria_active ): bool {
	$key = wp_parse_url( $row['url'], PHP_URL_QUERY );
	parse_str( (string) $key, $parsed );

	foreach ( $oria_active as $param => $value ) {
		if ( '' !== $value && isset( $parsed[ $param ] ) && $parsed[ $param ] === $value ) {
			return true;
		}
	}

	return false;
};

// Long lists balance across two columns; short ones stay in one.
$oria_split  = count( $oria_rows ) >= 7;
$oria_chunks = $oria_split
	? array_chunk( $oria_rows, (int) ceil( count( $oria_rows ) / 2 ) )
	: array( $oria_rows );
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

	<div class="intents<?php echo $oria_split ? ' intents--split' : ''; ?>">
		<div class="intents__cols">
			<?php foreach ( $oria_chunks as $oria_i => $oria_chunk ) : ?>
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
						<?php
						foreach ( $oria_chunk as $oria_row ) :
							$oria_on = $oria_is_active( $oria_row );
							?>
							<tr class="<?php echo $oria_on ? 'is-active' : ''; ?>"<?php echo $oria_on ? ' aria-current="true"' : ''; ?>>
								<th scope="row">
									<a href="<?php echo esc_url( $oria_row['url'] ); ?>"><?php echo esc_html( $oria_row['label'] ); ?></a>
									<?php if ( $oria_on ) : ?>
										<span class="intents__now"><?php esc_html_e( 'showing', 'oria' ); ?></span>
									<?php endif; ?>
								</th>
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
			<?php endforeach; ?>
		</div>

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
