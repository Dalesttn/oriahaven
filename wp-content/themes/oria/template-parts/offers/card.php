<?php
/**
 * One offer on /offers/ and /offers/{activity}/ (OffersHub). The compact
 * category-page card is template-parts/offer-card.php; this is the full one,
 * built for comparing: the upfront price, what is included, who can buy it
 * and when it ends are all visible without opening anything. Only the
 * business's longer terms sit behind "Details & terms".
 *
 * Three different things a visitor can do, kept as three controls, so the
 * card itself is not one big link: the business's Oria listing (the name),
 * the offer on the business's own site (View offer, new tab, said so), and
 * Compare (a checkbox offers.js reveals; it does nothing without script).
 *
 * The data-* attributes are what offers.js filters and sorts by -- the same
 * values OffersHub\matches() and sorted() use on the server.
 *
 * $args: row (OffersHub\rows() item), place (analytics placement),
 *        eager (bool, the first few images), hidden (bool, filtered out).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\OffersHub as Hub;

$oria_r     = (array) ( $args['row'] ?? array() );
$oria_lid   = (int) ( $oria_r['id'] ?? 0 );
$oria_o     = (array) ( $oria_r['offer'] ?? array() );
$oria_x     = (array) ( $oria_o['extra'] ?? array() );
$oria_place = sanitize_key( (string) ( $args['place'] ?? 'hub' ) );
$oria_promo = (array) ( $oria_r['promo'] ?? array() );
$oria_adv   = ! empty( $oria_o['advertised'] );
$oria_price = (string) ( $oria_x['price'] ?? '' );
$oria_basis = (string) ( $oria_x['basis'] ?? '' );
$oria_kind  = (string) ( $oria_x['kind'] ?? '' );
$oria_inc   = array_slice( (array) ( $oria_x['includes'] ?? array() ), 0, 3 );
$oria_terms = (array) ( $oria_x['terms'] ?? array() );
$oria_elig  = (string) ( $oria_x['eligibility'] ?? '' );
$oria_name  = \Oria\Theme\ptitle( get_post( $oria_lid ) );
$oria_acts  = array_map( '\Oria\Core\OffersHub\label', (array) ( $oria_r['acts'] ?? array() ) );
$oria_until = (string) ( $oria_o['until'] ?? '' );
$oria_chk   = (string) ( $oria_o['checked'] ?? '' );
$oria_amt   = $oria_r['amount'] ?? null;
// A price string the record states as a plain dollar figure is the upfront price; anything else ("10% off", "2 for 1") is not a total.
$oria_fixed = null !== $oria_amt;
$oria_big   = '' !== $oria_price ? $oria_price : ( $oria_fixed ? Hub\money( (float) $oria_amt ) : '' );
$oria_img   = '';
if ( has_post_thumbnail( $oria_lid ) ) {
	$oria_img = (string) get_the_post_thumbnail_url( $oria_lid, 'oria-card' );
} elseif ( function_exists( '\Oria\Core\Places\card_photo' ) ) {
	$oria_img = (string) \Oria\Core\Places\card_photo( $oria_lid );
}
$oria_hid   = 'ofx-o-' . $oria_lid;
$oria_eager = ! empty( $args['eager'] );
?>
<li class="ofx-card<?php echo $oria_promo ? ' ofx-card--promoted' : ''; ?>" id="offer-<?php echo (int) $oria_lid; ?>"
	data-ofx-card data-id="<?php echo (int) $oria_lid; ?>" data-rank="<?php echo (int) ( $oria_r['rank'] ?? 0 ); ?>"
	data-area="<?php echo esc_attr( (string) $oria_r['area'] ); ?>" data-budget="<?php echo esc_attr( Hub\budget_band( null === $oria_amt ? null : (float) $oria_amt ) ); ?>"
	data-amount="<?php echo null === $oria_amt ? '' : esc_attr( (string) $oria_amt ); ?>" data-who="<?php echo esc_attr( Hub\who_key( (string) $oria_r['audience'] ) ); ?>"
	data-format="<?php echo esc_attr( (string) $oria_r['format'] ); ?>" data-weekend="<?php echo true === ( $oria_r['weekend'] ?? null ) ? '1' : ''; ?>" data-checked="<?php echo esc_attr( $oria_chk ); ?>" data-until="<?php echo esc_attr( $oria_until ); ?>"
	data-offer-view="<?php echo (int) $oria_lid; ?>" data-offer-place="<?php echo esc_attr( $oria_place ); ?>"<?php echo ! empty( $args['hidden'] ) ? ' hidden' : ''; ?>>
	<article class="ofx-card__in" aria-labelledby="<?php echo esc_attr( $oria_hid ); ?>">
		<div class="ofx-card__media">
			<?php if ( '' !== $oria_img ) : ?>
				<img src="<?php echo esc_url( $oria_img ); ?>" alt="" width="600" height="400" <?php echo $oria_eager ? 'fetchpriority="high"' : 'loading="lazy"'; ?> decoding="async">
			<?php else : ?>
				<span class="ofx-card__fallback" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $oria_name, 0, 1 ) ) ); ?></span>
			<?php endif; ?>
			<?php if ( $oria_promo ) : ?>
				<span class="ofx-card__promo"><?php esc_html_e( 'Promoted', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(paid placement)', 'oria' ); ?></span></span>
			<?php elseif ( '' !== $oria_kind ) : ?>
				<span class="ofx-card__badge"><?php echo esc_html( $oria_kind ); ?></span>
			<?php endif; ?>
		</div>

		<div class="ofx-card__body">
			<h3 class="ofx-card__name" id="<?php echo esc_attr( $oria_hid ); ?>"><a href="<?php echo esc_url( (string) get_permalink( $oria_lid ) ); ?>"><?php echo esc_html( $oria_name ); ?></a></h3>
			<p class="ofx-card__meta">
				<?php echo esc_html( implode( ' · ', array_filter( array( (string) $oria_r['suburb'], implode( ', ', $oria_acts ) ) ) ) ); ?>
			</p>
			<p class="ofx-card__head"><?php echo esc_html( (string) $oria_o['title'] ); ?></p>

			<?php if ( '' !== $oria_big ) : ?>
				<div class="ofx-card__price">
					<p class="ofx-card__amt<?php echo mb_strlen( $oria_big ) > 7 ? ' ofx-card__amt--long' : ''; ?>">
						<span class="sr-only"><?php echo esc_html( \Oria\Theme\offer_price_words( $oria_big, $oria_basis ) ); ?></span>
						<span aria-hidden="true"><?php echo esc_html( $oria_big ); ?></span>
					</p>
					<p class="ofx-card__unit" aria-hidden="true">
						<?php
						$oria_unit = array_filter( array( $oria_basis, $oria_fixed && (float) $oria_amt > 0 ? 'AUD' : '' ) );
						echo esc_html( implode( ' · ', $oria_unit ) );
						?>
					</p>
					<?php if ( ! $oria_fixed ) : ?>
						<p class="ofx-card__varies"><?php esc_html_e( 'Price varies with what you book', 'oria' ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="ofx-card__price">
					<p class="ofx-card__noprice"><?php esc_html_e( 'Price not published — see their site', 'oria' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $oria_inc ) : ?>
				<ul class="ofx-card__inc" aria-label="<?php esc_attr_e( 'Included', 'oria' ); ?>">
					<?php foreach ( $oria_inc as $oria_i ) : ?>
						<li><?php echo esc_html( (string) $oria_i ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<dl class="ofx-card__facts">
				<div>
					<dt><?php esc_html_e( 'Who', 'oria' ); ?></dt>
					<dd><?php echo esc_html( '' !== $oria_elig ? $oria_elig : __( 'Not specified — check with the business', 'oria' ) ); ?></dd>
				</div>
				<?php if ( ! empty( $oria_x['renewal'] ) ) : ?>
					<div>
						<dt><?php esc_html_e( 'After', 'oria' ); ?></dt>
						<dd><?php echo esc_html( (string) $oria_x['renewal'] ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $oria_promo['code'] ) ) : ?>
					<div>
						<dt><?php esc_html_e( 'Code', 'oria' ); ?></dt>
						<dd><?php printf( esc_html__( 'Mention %s when you book', 'oria' ), '<strong>' . esc_html( (string) $oria_promo['code'] ) . '</strong>' ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
		</div>

		<p class="ofx-card__verify">
			<span class="ofx-card__tick" aria-hidden="true"></span>
			<?php
			$oria_bits = array();
			if ( '' !== $oria_chk ) {
				/* translators: %s: date */
				$oria_bits[] = sprintf( __( 'Checked %s', 'oria' ), mysql2date( 'j M Y', $oria_chk ) );
			}
			/* translators: %s: date */
			$oria_bits[] = '' !== $oria_until ? sprintf( __( 'Ends %s', 'oria' ), mysql2date( 'j M Y', $oria_until ) ) : __( 'No end date stated', 'oria' );
			$oria_bits[] = $oria_adv ? __( 'Source: their website', 'oria' ) : __( 'From the owner', 'oria' );
			echo esc_html( implode( ' · ', $oria_bits ) );
			?>
		</p>

		<div class="ofx-card__actions">
			<?php if ( $oria_adv ) : ?>
				<a class="ofx-card__go" href="<?php echo esc_url( \Oria\Theme\outbound( (string) $oria_o['source'], 'offer' ) ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="offer" data-oria-place="<?php echo esc_attr( $oria_place ); ?>" data-oria-id="<?php echo (int) $oria_lid; ?>"><?php esc_html_e( 'View offer', 'oria' ); ?><span class="ofx-card__ext"> <?php esc_html_e( 'on their site', 'oria' ); ?></span><span class="sr-only"> <?php esc_html_e( '(opens in a new tab)', 'oria' ); ?></span><svg aria-hidden="true" viewBox="0 0 16 16" width="14" height="14"><path d="M6 3h7v7M13 3 4 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
			<?php else : ?>
				<a class="ofx-card__go" href="<?php echo esc_url( (string) get_permalink( $oria_lid ) ); ?>"><?php esc_html_e( 'View offer', 'oria' ); ?></a>
			<?php endif; ?>
			<label class="ofx-card__cmp" hidden data-ofx-cmp-label>
				<input type="checkbox" value="<?php echo (int) $oria_lid; ?>" data-ofx-compare>
				<span><?php esc_html_e( 'Compare', 'oria' ); ?><span class="sr-only"> <?php echo esc_html( $oria_name ); ?></span></span>
			</label>
		</div>

		<details class="ofx-card__terms">
			<summary><?php esc_html_e( 'Details & terms', 'oria' ); ?></summary>
			<div class="ofx-card__terms-in">
				<?php if ( '' !== (string) $oria_o['text'] ) : ?><p><?php echo esc_html( (string) $oria_o['text'] ); ?></p><?php endif; ?>
				<?php if ( $oria_terms ) : ?>
					<ul><?php foreach ( $oria_terms as $oria_t ) : ?><li><?php echo esc_html( (string) $oria_t ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<p class="ofx-card__report"><a href="<?php echo esc_url( 'mailto:hello@oriahaven.com.au?subject=' . rawurlencode( 'Offer problem: ' . $oria_name ) . '&body=' . rawurlencode( "Offer: " . $oria_o['title'] . "\nPage: " . get_permalink( $oria_lid ) . "\n\nWhat's wrong:\n" ) ); ?>"><?php esc_html_e( 'Report a problem with this offer', 'oria' ); ?></a></p>
			</div>
		</details>
	</article>
</li>
