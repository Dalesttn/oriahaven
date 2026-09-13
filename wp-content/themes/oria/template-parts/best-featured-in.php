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
			<?php $oria_seal = BestOf\seal_url( $oria_row['award'] ); ?>
			<li class="bofeatin__item">
				<?php if ( $oria_seal ) : ?>
					<img class="bofeatin__seal" src="<?php echo esc_url( $oria_seal ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: award label */ __( 'Oria Haven Best of Perth seal: %s', 'oria' ), $oria_row['label'] ) ); ?>" width="320" height="320" loading="lazy">
				<?php endif; ?>
				<div>
					<div class="bofeatin__head">
						<?php echo BestOf\badge_html( $oria_row['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<a class="bofeatin__guide" href="<?php echo esc_url( (string) get_permalink( $oria_row['guide'] ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_row['guide'] ) ) ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
					</div>
					<?php if ( $oria_row['reason'] ) : ?>
						<p class="bofeatin__why"><span><?php esc_html_e( 'Why we selected it:', 'oria' ); ?></span> <?php echo esc_html( $oria_row['reason'] ); ?></p>
					<?php endif; ?>
					<?php
					/*
					 * The seal is the practice's to use. A practice that puts
					 * it on its own site tends to link the guide it came from,
					 * which is the one kind of link this directory cannot buy.
					 */
					?>
					<?php if ( BestOf\seal_url( $oria_row['award'], 'png' ) ) : ?>
						<a class="bofeatin__get" href="<?php echo esc_url( BestOf\seal_url( $oria_row['award'], 'png' ) ); ?>" download><?php esc_html_e( 'Run this practice? Download the seal for your website', 'oria' ); ?></a>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="bofeatin__fine"><?php esc_html_e( 'Best Of guides are an editorial selection. A practice cannot pay to be in one.', 'oria' ); ?></p>
</div>
