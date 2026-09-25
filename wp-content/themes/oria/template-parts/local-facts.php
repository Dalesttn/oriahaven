<?php
/**
 * What is true of the places on this page, counted rather than written.
 *
 * Sits above the category introduction on a category-and-suburb page. The
 * introduction is about the modality and reads the same in every suburb;
 * these lines are about the practices actually listed here, so they differ
 * because the practices do.
 *
 * Renders nothing when nothing can be counted, which is the whole point:
 * the alternative was padding, and padding is what made the pages alike.
 *
 * Args: facts (string[] from Oria\Core\LocalFacts\for_combo), place (string).
 *
 * @package Oria
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$oria_lf       = array_values( array_filter( (array) ( $args['facts'] ?? array() ) ) );
$oria_lf_place = trim( (string) ( $args['place'] ?? '' ) );

if ( ! $oria_lf ) {
	return;
}
?>
<section class="lfacts" aria-labelledby="lfacts-h">
	<h2 class="lfacts__h h3" id="lfacts-h">
		<?php
		if ( '' !== $oria_lf_place ) {
			/* translators: %s: suburb */
			printf( esc_html__( 'What these places have in common in %s', 'oria' ), esc_html( $oria_lf_place ) );
		} else {
			esc_html_e( 'What these places have in common', 'oria' );
		}
		?>
	</h2>

	<ul class="lfacts__list">
		<?php foreach ( $oria_lf as $oria_lf_line ) : ?>
			<li><?php echo esc_html( (string) $oria_lf_line ); ?></li>
		<?php endforeach; ?>
	</ul>

	<?php
	/*
	 * Said once, plainly. These are counts of what practices have published
	 * on Oria, not a survey of the suburb -- a practice that does not list
	 * something may still do it, and the sentence above only ever says what
	 * was listed.
	 */
	?>
	<p class="lfacts__note"><?php esc_html_e( 'Counted from what these practices list on Oria Haven, and updated as listings change.', 'oria' ); ?></p>
</section>
