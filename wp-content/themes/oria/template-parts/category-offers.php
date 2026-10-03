<?php
/**
 * Current offers on a category page: a compact collection of offer cards
 * -- price band (price left, pass duration right), suburb, business, the
 * offer in one line, eligibility, then "View offer", a terms disclosure
 * and a quiet source line -- with the weekly-offers signup as the last
 * tile when the count makes a balanced grid (five offers → 3 × 2), or as
 * a compact strip below when a tile would strand a row.
 *
 * Everything is stored data: Theme\active_offer() for the words and
 * Offers\extra() for the structured part. An offer without a structured
 * price shows its title in the band; nothing is parsed from prose.
 *
 * $args: ids (list<int> the page's listing set), label (category or
 *        facet name), interest (practice slug for the signup).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Core\Offers\among' ) ) {
	return;
}
$oria_rows  = \Oria\Core\Offers\among( (array) ( $args['ids'] ?? array() ), 5 );
$oria_label = (string) ( $args['label'] ?? '' );
$oria_int   = sanitize_key( (string) ( $args['interest'] ?? '' ) );
if ( ! $oria_rows ) {
	return;
}
$oria_n   = count( $oria_rows );
$oria_cl  = '' !== $oria_label ? mb_strtolower( $oria_label ) : __( 'wellness', 'oria' );
$oria_cat = $oria_int;

/* Heading copy by category; "Explore offers" where a new-routine line would not fit. */
$oria_copy = array(
	'yoga'     => array( __( 'Your next yoga chapter.', 'oria' ), __( 'Explore introductory yoga offers around Perth. Find a studio, try the classes, see how you feel.', 'oria' ) ),
	'fitness'  => array( __( 'Your next good habit.', 'oria' ), __( 'Intro passes and trials around Perth. Try a studio for a couple of weeks before you commit.', 'oria' ) ),
	'spa'      => array( __( 'A reason to go this week.', 'oria' ), __( 'Current spa and recovery offers around Perth, read from each place’s own site.', 'oria' ) ),
	'bodywork' => array( __( 'Good hands, better value.', 'oria' ), __( 'Current massage and bodywork offers around Perth, read from each clinic’s own site.', 'oria' ) ),
	'beauty'   => array( __( 'A little something for yourself.', 'oria' ), __( 'Current beauty and skin offers around Perth, read from each place’s own site.', 'oria' ) ),
);
$oria_head = $oria_copy[ $oria_cat ][0] ?? __( 'Explore offers.', 'oria' );
$oria_desc = $oria_copy[ $oria_cat ][1] ?? sprintf( /* translators: %s: category */ __( 'Current %s offers around Perth, read from each business’s own site.', 'oria' ), $oria_cl );

/* The signup: a tile that completes the grid at 1, 2 or 5; a strip below at 3 or 4. */
$oria_tile = in_array( $oria_n, array( 1, 2, 5 ), true );
?>
<section class="wrap oh-category-offers" id="offers" aria-labelledby="oh-cat-offers-title">
	<div class="oh-category-offers__head">
		<p class="micro"><?php echo esc_html( '' !== $oria_label ? sprintf( /* translators: %s: category */ __( '%s offers in Perth', 'oria' ), $oria_label ) : __( 'Offers in Perth', 'oria' ) ); ?></p>
		<h2 class="oh-category-offers__title" id="oh-cat-offers-title"><?php echo esc_html( $oria_head ); ?></h2>
		<p class="oh-category-offers__desc"><?php echo esc_html( $oria_desc ); ?></p>
		<p class="oh-category-offers__count"><?php echo esc_html( sprintf( /* translators: 1: count, 2: category */ _n( '%1$s %2$s offer', '%1$s %2$s offers', $oria_n, 'oria' ), number_format_i18n( $oria_n ), $oria_cl ) ); ?></p>
	</div>

	<?php
	/*
	 * The collection is collapsed behind "Show me the offers": a native
	 * <details>, so it opens without JavaScript, is keyboard-operable and
	 * the cards stay in the page for crawlers. #offers in the URL opens it.
	 */
	?>
	<details class="oh-category-offers__fold" id="offers-fold">
		<summary class="oh-category-offers__toggle">
			<span class="oh-category-offers__plus" aria-hidden="true"><span></span><span></span></span>
			<span class="oh-category-offers__show"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( 'Show me the offer', 'Show me the %d offers', $oria_n, 'oria' ), $oria_n ) ); ?></span>
			<span class="oh-category-offers__hide"><?php esc_html_e( 'Hide the offers', 'oria' ); ?></span>
		</summary>

	<ul class="oh-category-offers__grid oh-category-offers__grid--<?php echo (int) $oria_n; ?>">
		<?php foreach ( $oria_rows as $oria_i => $oria_r ) : ?>
			<?php
			$oria_o     = $oria_r['offer'];
			$oria_lid   = (int) $oria_r['id'];
			$oria_adv   = ! empty( $oria_o['advertised'] );
			$oria_x     = (array) ( $oria_o['extra'] ?? array() );
			$oria_price = (string) ( $oria_x['price'] ?? '' );
			$oria_basis = (string) ( $oria_x['basis'] ?? '' );
			$oria_kind  = (string) ( $oria_x['kind'] ?? '' );
			$oria_terms = (array) ( $oria_x['terms'] ?? array() );
			$oria_sub   = function_exists( '\Oria\Core\BestOf\suburb' ) ? (string) \Oria\Core\BestOf\suburb( $oria_lid ) : '';
			$oria_name  = \Oria\Theme\ptitle( get_post( $oria_lid ) );
			$oria_src   = $oria_adv
				? ( '' !== (string) $oria_o['checked'] ? sprintf( /* translators: %s: date */ __( 'Their website · checked %s', 'oria' ), mysql2date( 'j M', $oria_o['checked'] ) ) : __( 'Their website', 'oria' ) )
				: __( 'From the owner', 'oria' );
			?>
			<li class="oh-offer-card" aria-labelledby="oh-oc-<?php echo (int) $oria_lid; ?>">
				<div class="oh-offer-card__band">
					<?php if ( '' !== $oria_price ) : ?>
						<p class="oh-offer-card__price<?php echo mb_strlen( $oria_price ) > 6 ? ' oh-offer-card__price--long' : ''; ?>">
							<span class="sr-only"><?php echo esc_html( \Oria\Theme\offer_price_words( $oria_price, $oria_basis ) ); ?></span>
							<span aria-hidden="true"><?php echo esc_html( $oria_price ); ?></span>
						</p>
						<p class="oh-offer-card__basis" aria-hidden="true">
							<?php if ( '' !== $oria_basis ) : ?><span class="oh-offer-card__dur"><?php echo esc_html( $oria_basis ); ?></span><?php endif; ?>
							<span class="oh-offer-card__kind"><?php echo esc_html( '' !== $oria_kind ? $oria_kind . ' · AUD' : 'AUD' ); ?></span>
						</p>
					<?php else : ?>
						<p class="oh-offer-card__price oh-offer-card__price--text"><?php echo esc_html( $oria_o['title'] ); ?></p>
						<?php if ( '' !== $oria_kind ) : ?><p class="oh-offer-card__basis"><span class="oh-offer-card__kind"><?php echo esc_html( $oria_kind ); ?></span></p><?php endif; ?>
					<?php endif; ?>
				</div>
				<div class="oh-offer-card__body">
					<?php if ( '' !== $oria_sub ) : ?><p class="oh-offer-card__where"><?php echo esc_html( $oria_sub ); ?></p><?php endif; ?>
					<h3 class="oh-offer-card__name" id="oh-oc-<?php echo (int) $oria_lid; ?>"><a href="<?php echo esc_url( (string) get_permalink( $oria_lid ) ); ?>"><?php echo esc_html( $oria_name ); ?></a></h3>
					<?php if ( '' !== $oria_price ) : ?>
						<p class="oh-offer-card__benefit"><?php echo esc_html( $oria_o['title'] ); ?></p>
					<?php elseif ( '' !== (string) $oria_o['text'] ) : ?>
						<p class="oh-offer-card__benefit"><?php echo esc_html( wp_trim_words( (string) $oria_o['text'], 22, '…' ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $oria_x['eligibility'] ) || '' !== (string) $oria_o['until'] ) : ?>
						<p class="oh-offer-card__labels">
							<?php if ( ! empty( $oria_x['eligibility'] ) ) : ?><span><?php echo esc_html( (string) $oria_x['eligibility'] ); ?></span><?php endif; ?>
							<?php if ( '' !== (string) $oria_o['until'] ) : ?><span><?php printf( esc_html__( 'Until %s', 'oria' ), esc_html( mysql2date( 'j M', $oria_o['until'] ) ) ); ?></span><?php endif; ?>
						</p>
					<?php endif; ?>
				</div>
				<div class="oh-offer-card__foot">
					<?php if ( $oria_adv ) : ?>
						<a class="oh-offer-card__go" href="<?php echo esc_url( \Oria\Theme\outbound( $oria_o['source'], 'offer' ) ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="offer_source" data-oria-id="<?php echo (int) $oria_lid; ?>"><?php esc_html_e( 'View offer', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(opens their site in a new tab)', 'oria' ); ?></span><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
					<?php else : ?>
						<a class="oh-offer-card__go" href="<?php echo esc_url( (string) get_permalink( $oria_lid ) ); ?>"><?php esc_html_e( 'View practice', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
					<?php endif; ?>
					<?php if ( $oria_terms || ( '' !== $oria_price && '' !== (string) $oria_o['text'] ) ) : ?>
						<details class="oh-offer-card__terms">
							<summary><?php esc_html_e( 'Terms & inclusions', 'oria' ); ?></summary>
							<?php if ( '' !== $oria_price && '' !== (string) $oria_o['text'] ) : ?><p><?php echo esc_html( $oria_o['text'] ); ?></p><?php endif; ?>
							<?php if ( $oria_terms ) : ?>
								<ul><?php foreach ( $oria_terms as $oria_t ) : ?><li><?php echo esc_html( (string) $oria_t ); ?></li><?php endforeach; ?></ul>
							<?php endif; ?>
						</details>
					<?php endif; ?>
					<p class="oh-offer-card__src"><?php echo esc_html( $oria_src ); ?></p>
				</div>
			</li>
		<?php endforeach; ?>

		<?php if ( $oria_tile ) : ?>
			<li class="oh-offer-card oh-offer-card--signup">
				<p class="micro"><?php esc_html_e( 'The Oria edit', 'oria' ); ?></p>
				<h3 class="oh-offer-card__name"><?php esc_html_e( 'A little wellness in your inbox.', 'oria' ); ?></h3>
				<p class="oh-offer-card__benefit"><?php esc_html_e( 'New Perth wellness offers, gathered into one weekly email.', 'oria' ); ?></p>
				<details class="oh-offer-card__signup">
					<summary><?php esc_html_e( 'Keep me in the loop', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></summary>
					<?php get_template_part( 'template-parts/offers-signup', null, array( 'source' => 'category', 'interest' => $oria_int ) ); ?>
				</details>
				<p class="oh-offer-card__src"><?php esc_html_e( 'Weekly. Unsubscribe any time.', 'oria' ); ?></p>
			</li>
		<?php endif; ?>
	</ul>

	<?php if ( ! $oria_tile ) : ?>
		<div class="oh-category-offers__strip">
			<?php get_template_part( 'template-parts/offers-signup', null, array( 'source' => 'category', 'interest' => $oria_int ) ); ?>
		</div>
	<?php endif; ?>
	</details>
	<script>(function(d){function o(){if(location.hash==='#offers'){d.open=true;}}o();addEventListener('hashchange',o);})(document.getElementById('offers-fold'));</script>
</section>
