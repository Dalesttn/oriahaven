<?php
/**
 * Current offers on a category page: up to five live offers among the
 * places the page shows, each a small card -- the business, the offer in
 * one line, when it ends, and where to claim it. An advertised offer links
 * to the page it was read on and says when it was checked; an owner's
 * offer links to the listing.
 *
 * $args: ids (list<int> the page's listing set), label (the category or
 *        facet name, for the heading).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Core\Offers\among' ) ) {
	return;
}
$oria_rows  = \Oria\Core\Offers\among( (array) ( $args['ids'] ?? array() ), 5 );
$oria_label = (string) ( $args['label'] ?? '' );
if ( ! $oria_rows ) {
	return;
}
?>
<section class="wrap xc-offers" id="offers" aria-labelledby="xc-offers-title">
	<div class="xc-offers__head">
		<span class="micro"><?php esc_html_e( 'Current offers', 'oria' ); ?></span>
		<h2 class="h3" id="xc-offers-title"><?php echo esc_html( '' !== $oria_label ? sprintf( /* translators: %s: category */ __( '%s offers right now', 'oria' ), $oria_label ) : __( 'Offers right now', 'oria' ) ); ?></h2>
		<p class="xc-offers__note"><?php esc_html_e( 'Advertised by each business on its own site, or added by the owner. Each one is removed on the day it ends.', 'oria' ); ?></p>
	</div>
	<ul class="xc-offers__list">
		<?php foreach ( $oria_rows as $oria_r ) : ?>
			<?php
			$oria_o   = $oria_r['offer'];
			$oria_lid = (int) $oria_r['id'];
			$oria_adv = ! empty( $oria_o['advertised'] );
			$oria_sub = function_exists( '\Oria\Core\BestOf\suburb' ) ? (string) \Oria\Core\BestOf\suburb( $oria_lid ) : '';
			?>
			<li class="xc-offer">
				<p class="xc-offer__who"><a href="<?php echo esc_url( (string) get_permalink( $oria_lid ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_lid ) ) ); ?></a><?php echo '' !== $oria_sub ? esc_html( ' · ' . $oria_sub ) : ''; ?></p>
				<p class="xc-offer__title"><?php echo esc_html( $oria_o['title'] ); ?></p>
				<?php if ( '' !== (string) $oria_o['text'] ) : ?>
					<p class="xc-offer__text"><?php echo esc_html( $oria_o['text'] ); ?></p>
				<?php endif; ?>
				<p class="xc-offer__meta">
					<?php if ( '' !== (string) $oria_o['until'] ) : ?>
						<span><?php printf( esc_html__( 'Until %s', 'oria' ), esc_html( mysql2date( 'j M Y', $oria_o['until'] ) ) ); ?></span>
					<?php endif; ?>
					<?php if ( $oria_adv ) : ?>
						<span><?php echo esc_html( '' !== (string) $oria_o['checked'] ? sprintf( /* translators: %s: date */ __( 'On their site · checked %s', 'oria' ), mysql2date( 'j M', $oria_o['checked'] ) ) : __( 'On their site', 'oria' ) ); ?></span>
					<?php else : ?>
						<span><?php esc_html_e( 'From the owner', 'oria' ); ?></span>
					<?php endif; ?>
				</p>
				<?php if ( $oria_adv ) : ?>
					<a class="xc-offer__go" href="<?php echo esc_url( \Oria\Theme\outbound( $oria_o['source'], 'offer' ) ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="offer_source" data-oria-id="<?php echo (int) $oria_lid; ?>"><?php esc_html_e( 'See the offer', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(opens their site)', 'oria' ); ?></span><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<?php else : ?>
					<a class="xc-offer__go" href="<?php echo esc_url( (string) get_permalink( $oria_lid ) ); ?>"><?php esc_html_e( 'View practice', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
