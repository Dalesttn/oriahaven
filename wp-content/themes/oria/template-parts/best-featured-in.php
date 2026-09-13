<?php
/**
 * "Featured by Oria Haven" -- on a listing profile, the guides that picked
 * it and why. Renders nothing for the 95% of listings in no guide.
 *
 * Says plainly that this is editorial. A visitor who has just seen a paid
 * "Featured" badge two screens up should not mistake one for the other.
 *
 * $args: listing_id
 */

declare(strict_types=1);

use Oria\Core\BestOf;

$oria_lid = (int) ( $args['listing_id'] ?? 0 );
$oria_in  = $oria_lid ? BestOf\guides_for_listing( $oria_lid ) : array();
if ( ! $oria_in ) {
	return;
}
?>
<div class="bofeatin reveal">
	<span class="micro"><?php esc_html_e( 'Featured by Oria Haven', 'oria' ); ?></span>
	<h2 class="h3 bofeatin__title"><?php echo esc_html( _n( 'This practice appears in our Best Of guide', 'This practice appears in our Best Of guides', count( $oria_in ), 'oria' ) ); ?></h2>
	<ul class="bofeatin__list">
		<?php foreach ( $oria_in as $oria_row ) : ?>
			<li class="bofeatin__item">
				<div class="bofeatin__head">
					<?php echo BestOf\badge_html( $oria_row['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<a class="bofeatin__guide" href="<?php echo esc_url( (string) get_permalink( $oria_row['guide'] ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_row['guide'] ) ) ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				</div>
				<?php if ( $oria_row['reason'] ) : ?>
					<p class="bofeatin__why"><span><?php esc_html_e( 'Why we selected it:', 'oria' ); ?></span> <?php echo esc_html( $oria_row['reason'] ); ?></p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="bofeatin__fine"><?php esc_html_e( 'Best Of guides are an editorial selection. A practice cannot pay to be in one.', 'oria' ); ?></p>
</div>
