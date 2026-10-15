<?php
/**
 * The offers email on /offers/ pages. Two places: inline in the results
 * (after the sixth card, as an <li>; offers.js keeps it there as filters
 * change) and, on long pages, once more at the end (as a <section>, with
 * its own element id). The form itself is the shared
 * template-parts/offers-signup.php, so consent, storage and the success
 * state are the ones every other signup uses.
 *
 * $args: interest (activity slug, for segmenting the list later),
 *        end (bool -- the end-of-page version).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_end = ! empty( $args['end'] );
$oria_tag = $oria_end ? 'section' : 'li';
$oria_hid = $oria_end ? 'ofx-signup-end-title' : 'ofx-signup-title';
?>
<<?php echo $oria_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- one of two literals ?> class="ofx-signup<?php echo $oria_end ? ' ofx-signup--end' : ''; ?>"<?php echo $oria_end ? '' : ' data-ofx-signup'; ?> aria-labelledby="<?php echo esc_attr( $oria_hid ); ?>">
	<div class="ofx-signup__in">
		<p class="ofx-signup__eyebrow"><?php esc_html_e( 'Offers by email', 'oria' ); ?></p>
		<h2 class="ofx-signup__title" id="<?php echo esc_attr( $oria_hid ); ?>"><?php esc_html_e( 'A little more wellness for your budget', 'oria' ); ?></h2>
		<p class="ofx-signup__lede"><?php esc_html_e( 'Get our latest Perth offers and intro passes by email. No spam, and you can unsubscribe from any email.', 'oria' ); ?></p>
		<?php get_template_part( 'template-parts/offers-signup', null, array( 'source' => 'category', 'interest' => (string) ( $args['interest'] ?? '' ), 'id' => $oria_end ? 'offers-signup-end' : 'offers-signup' ) ); ?>
	</div>
</<?php echo $oria_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
