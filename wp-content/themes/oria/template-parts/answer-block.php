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
?>

<section class="wrap section section--top-flush">
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
	</div>
</section>
