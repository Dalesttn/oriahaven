<?php
/**
 * The offers email, inline in the /offers/ results (after the sixth card;
 * offers.js keeps it there as filters change). The form itself is the
 * shared template-parts/offers-signup.php, so consent, storage and the
 * success state are the ones every other signup uses.
 *
 * $args: interest (activity slug, for segmenting the list later).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;
?>
<li class="ofx-signup" data-ofx-signup>
	<div class="ofx-signup__in">
		<p class="ofx-signup__eyebrow"><?php esc_html_e( 'Offers by email', 'oria' ); ?></p>
		<h2 class="ofx-signup__title"><?php esc_html_e( 'A little more wellness for your budget', 'oria' ); ?></h2>
		<p class="ofx-signup__lede"><?php esc_html_e( 'Get our latest Perth offers and intro passes by email. No spam, and you can unsubscribe from any email.', 'oria' ); ?></p>
		<?php get_template_part( 'template-parts/offers-signup', null, array( 'source' => 'category', 'interest' => (string) ( $args['interest'] ?? '' ) ) ); ?>
	</div>
</li>
