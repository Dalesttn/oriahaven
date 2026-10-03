<?php
/**
 * Offers on a listing page: this business's offer as the main card (text
 * on the left, price panel on the right), then up to three quieter cards
 * for other live offers in the same category, and the link to the
 * category's offers strip.
 *
 * Everything shown is stored data -- Theme\active_offer() for the words,
 * Offers\extra() for the structured part (kind, price, basis, inclusions,
 * eligibility, terms). An offer without a structured price shows its title
 * and "See offer details"; nothing is parsed from prose, no saving or
 * expiry is invented. An owner's offer (claimed listing, no source) is
 * shown without an outbound link.
 *
 * $args: id (int listing).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_id = (int) ( $args['id'] ?? get_the_ID() );
if ( ! $oria_id || ! function_exists( '\Oria\Theme\active_offer' ) || ! function_exists( '\Oria\Core\Offers\among' ) ) {
	return;
}

$oria_offer = \Oria\Theme\active_offer( $oria_id );
$oria_oc    = function_exists( '\Oria\Core\Categories\primary_for' ) ? \Oria\Core\Categories\primary_for( $oria_id ) : null;
$oria_more  = array();
if ( $oria_oc instanceof WP_Term && function_exists( '\Oria\Core\Intents\listings_in' ) ) {
	$oria_more = \Oria\Core\Offers\among( array_diff( array_map( 'intval', \Oria\Core\Intents\listings_in( $oria_oc ) ), array( $oria_id ) ), 3 );
}
if ( ! $oria_offer && ! $oria_more ) {
	return;
}

$oria_cat   = $oria_oc instanceof WP_Term ? $oria_oc->slug : '';
$oria_cname = $oria_oc instanceof WP_Term ? \Oria\Theme\tname( $oria_oc ) : '';
$oria_cl    = '' !== $oria_cname ? mb_strtolower( $oria_cname ) : __( 'nearby', 'oria' );

/*
 * Intro copy by category. The heading is editorial; the supporting line
 * says exactly what follows -- this business first, then others in the
 * same category -- and never names a category the listing is not in.
 */
$oria_copy = array(
	'yoga'     => array( __( 'Find your next good habit.', 'oria' ), __( 'Start with this studio, or explore a few more yoga offers.', 'oria' ) ),
	'spa'      => array( __( 'Make a little time for yourself.', 'oria' ), __( 'Start here, or see what other spa and recovery places are offering.', 'oria' ) ),
	'bodywork' => array( __( 'Ease what has been tight.', 'oria' ), __( 'Start with this clinic, or compare a few more massage offers.', 'oria' ) ),
	'fitness'  => array( __( 'Find your next good habit.', 'oria' ), __( 'Start with this studio, or explore a few more intro passes.', 'oria' ) ),
	'beauty'   => array( __( 'A little something for yourself.', 'oria' ), __( 'Start here, or see a few more beauty offers.', 'oria' ) ),
);
$oria_head = $oria_copy[ $oria_cat ][0] ?? __( 'Explore offers from this business.', 'oria' );
$oria_sub  = $oria_offer
	? ( $oria_copy[ $oria_cat ][1] ?? ( $oria_more ? sprintf( /* translators: %s: category */ __( 'Start here, or explore other %s offers.', 'oria' ), $oria_cl ) : '' ) )
	: ( '' !== $oria_cname ? sprintf( /* translators: %s: category */ __( 'This business has no current offer. Here is what other %s places are offering.', 'oria' ), $oria_cl ) : '' );

$oria_src_label = static function ( array $o ): string {
	if ( empty( $o['advertised'] ) ) {
		return __( 'Offer from the owner', 'oria' );
	}
	return '' !== (string) $o['checked']
		? sprintf( /* translators: %s: date */ __( 'Advertised by the business · Offer checked %s', 'oria' ), mysql2date( 'j F Y', $o['checked'] ) )
		: __( 'Advertised by the business', 'oria' );
};
$oria_n = 0;
?>
<section class="wrap oh-offers" id="offers" aria-labelledby="oh-offers-title">
	<div class="oh-offers__head">
		<p class="micro"><?php esc_html_e( 'Offers & intro passes', 'oria' ); ?></p>
		<h2 class="h2" id="oh-offers-title"><?php echo esc_html( $oria_head ); ?></h2>
		<?php if ( '' !== $oria_sub ) : ?>
			<p class="oh-offers__sub"><?php echo esc_html( $oria_sub ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $oria_offer ) : ?>
		<?php
		$oria_x     = (array) ( $oria_offer['extra'] ?? array() );
		$oria_price = (string) ( $oria_x['price'] ?? '' );
		$oria_basis = (string) ( $oria_x['basis'] ?? '' );
		$oria_terms = (array) ( $oria_x['terms'] ?? array() );
		$oria_sub_name = function_exists( '\Oria\Core\BestOf\suburb' ) ? (string) \Oria\Core\BestOf\suburb( $oria_id ) : '';
		$oria_tid   = 'oh-terms-' . $oria_id;
		?>
		<article class="oh-offer oh-offer--main" aria-labelledby="oh-offer-<?php echo (int) $oria_id; ?>">
			<div class="oh-offer__body">
				<p class="oh-offer__who"><strong><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_id ) ) ); ?></strong><?php echo '' !== $oria_sub_name ? '<span> · ' . esc_html( $oria_sub_name ) . '</span>' : ''; ?></p>
				<p class="oh-offer__tag"><?php echo esc_html( empty( $oria_offer['advertised'] ) ? __( 'From this business', 'oria' ) : __( 'At this business', 'oria' ) ); ?></p>
				<h3 class="oh-offer__title" id="oh-offer-<?php echo (int) $oria_id; ?>"><?php echo esc_html( $oria_offer['title'] ); ?></h3>
				<?php if ( '' !== (string) $oria_offer['text'] ) : ?>
					<p class="oh-offer__text"><?php echo esc_html( $oria_offer['text'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $oria_x['includes'] ) ) : ?>
					<ul class="oh-offer__chips" aria-label="<?php esc_attr_e( 'Included', 'oria' ); ?>">
						<?php foreach ( (array) $oria_x['includes'] as $oria_chip ) : ?>
							<li><?php echo esc_html( (string) $oria_chip ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p class="oh-offer__elig">
					<?php if ( ! empty( $oria_x['eligibility'] ) ) : ?>
						<span><?php echo esc_html( (string) $oria_x['eligibility'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== (string) $oria_offer['until'] ) : ?>
						<span><?php printf( esc_html__( 'Until %s', 'oria' ), esc_html( mysql2date( 'j F Y', $oria_offer['until'] ) ) ); ?></span>
					<?php endif; ?>
				</p>
				<?php if ( $oria_terms ) : ?>
					<details class="oh-offer__terms" id="<?php echo esc_attr( $oria_tid ); ?>">
						<summary><?php esc_html_e( 'Inclusions & terms', 'oria' ); ?></summary>
						<ul>
							<?php foreach ( $oria_terms as $oria_t ) : ?>
								<li><?php echo esc_html( (string) $oria_t ); ?></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
			</div>
			<div class="oh-offer__panel">
				<?php if ( ! empty( $oria_x['kind'] ) ) : ?>
					<p class="oh-offer__kind"><?php echo esc_html( (string) $oria_x['kind'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $oria_price ) : ?>
					<p class="oh-offer__price<?php echo mb_strlen( $oria_price ) > 6 ? ' oh-offer__price--long' : ''; ?>">
						<span class="sr-only"><?php echo esc_html( \Oria\Theme\offer_price_words( $oria_price, $oria_basis ) ); ?></span>
						<span aria-hidden="true"><?php echo esc_html( $oria_price ); ?></span>
					</p>
					<?php if ( '' !== $oria_basis ) : ?>
						<p class="oh-offer__basis" aria-hidden="true"><?php echo esc_html( 'AUD · ' . $oria_basis ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p class="oh-offer__basis"><?php esc_html_e( 'See offer details', 'oria' ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $oria_offer['advertised'] ) ) : ?>
					<a class="oh-offer__go" href="<?php echo esc_url( \Oria\Theme\outbound( $oria_offer['source'], 'offer' ) ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="offer_source" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'View offer', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(opens their site in a new tab)', 'oria' ); ?></span><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<?php endif; ?>
			</div>
		</article>
		<p class="oh-offers__src"><?php echo esc_html( $oria_src_label( $oria_offer ) ); ?></p>
		<?php get_template_part( 'template-parts/offers-signup', null, array( 'source' => 'offer_click', 'listing' => $oria_id, 'hidden' => true ) ); ?>
	<?php endif; ?>

	<?php if ( $oria_more ) : ?>
		<div class="oh-offers__more">
			<div class="oh-offers__more-head">
				<h3 class="h4"><?php echo esc_html( $oria_offer ? __( 'More ways to get started', 'oria' ) : __( 'Current offers', 'oria' ) ); ?></h3>
				<?php if ( '' !== $oria_cname ) : ?>
					<p class="micro"><?php echo esc_html( sprintf( /* translators: %s: category */ __( 'Other %s offers', 'oria' ), $oria_cl ) ); ?></p>
				<?php endif; ?>
			</div>
			<ul class="oh-offers__grid">
				<?php foreach ( $oria_more as $oria_r ) : ?>
					<?php
					++$oria_n;
					$oria_mid = (int) $oria_r['id'];
					$oria_mo  = $oria_r['offer'];
					$oria_mx  = (array) ( $oria_mo['extra'] ?? array() );
					$oria_mp  = (string) ( $oria_mx['price'] ?? '' );
					$oria_mb  = (string) ( $oria_mx['basis'] ?? '' );
					$oria_msub = function_exists( '\Oria\Core\BestOf\suburb' ) ? (string) \Oria\Core\BestOf\suburb( $oria_mid ) : '';
					?>
					<li class="oh-offer oh-offer--compact">
						<p class="oh-offer__kind"><?php echo esc_html( (string) ( $oria_mx['kind'] ?? __( 'Offer', 'oria' ) ) ); ?></p>
						<p class="oh-offer__who"><a href="<?php echo esc_url( (string) get_permalink( $oria_mid ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_mid ) ) ); ?></a><?php echo '' !== $oria_msub ? '<span> · ' . esc_html( $oria_msub ) . '</span>' : ''; ?></p>
						<?php if ( '' !== $oria_mp ) : ?>
							<p class="oh-offer__amount">
								<span class="sr-only"><?php echo esc_html( \Oria\Theme\offer_price_words( $oria_mp, $oria_mb ) ); ?></span>
								<span class="oh-offer__num" aria-hidden="true"><?php echo esc_html( $oria_mp ); ?></span>
								<?php if ( '' !== $oria_mb ) : ?><span class="oh-offer__dur" aria-hidden="true"><?php echo esc_html( $oria_mb ); ?></span><?php endif; ?>
							</p>
						<?php else : ?>
							<p class="oh-offer__amount"><span class="oh-offer__dur"><?php echo esc_html( $oria_mo['title'] ); ?></span></p>
						<?php endif; ?>
						<p class="oh-offer__text"><?php echo esc_html( '' !== $oria_mp ? $oria_mo['title'] : wp_trim_words( (string) $oria_mo['text'], 18, '…' ) ); ?></p>
						<?php if ( ! empty( $oria_mo['advertised'] ) ) : ?>
							<a class="oh-offer__go oh-offer__go--quiet" href="<?php echo esc_url( \Oria\Theme\outbound( $oria_mo['source'], 'offer' ) ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="offer_source" data-oria-id="<?php echo (int) $oria_mid; ?>"><?php esc_html_e( 'Explore offer', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(opens their site in a new tab)', 'oria' ); ?></span><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
						<?php else : ?>
							<a class="oh-offer__go oh-offer__go--quiet" href="<?php echo esc_url( (string) get_permalink( $oria_mid ) ); ?>"><?php esc_html_e( 'View practice', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $oria_oc instanceof WP_Term ) : ?>
				<a class="oh-offers__all" href="<?php echo esc_url( (string) get_term_link( $oria_oc ) . '#offers' ); ?>"><?php echo esc_html( sprintf( /* translators: %s: category */ __( 'All %s offers', 'oria' ), $oria_cl ) ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/offers-signup', null, array( 'source' => 'listing', 'listing' => $oria_id ) ); ?>
</section>
