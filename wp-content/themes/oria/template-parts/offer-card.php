<?php
/**
 * One offer card: the category offers collection and the /offers/ hub both
 * draw it, so an offer reads the same wherever it appears. Everything is
 * stored data -- Theme\active_offer() for the words, Offers\extra() for the
 * structured part; nothing is parsed from prose.
 *
 * A promoted offer (the 30-day pilot, Offers\promotion()) says "Promoted"
 * on the card itself, every time, and shows the venue's code when it has
 * one. data-offer-view lets app.js count the card once when it is seen.
 *
 * $args: id (listing id), offer (Theme\active_offer() row), place
 *        ('category' | 'hub' | 'hub_promoted' ...), promo (array|empty).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;
?>
		<?php
		$oria_o     = (array) ( $args['offer'] ?? array() );
		$oria_lid   = (int) ( $args['id'] ?? 0 );
		$oria_place = sanitize_key( (string) ( $args['place'] ?? 'category' ) );
		$oria_promo = (array) ( $args['promo'] ?? array() );
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
		<li class="oh-offer-card<?php echo $oria_promo ? ' oh-offer-card--promoted' : ''; ?>" aria-labelledby="oh-oc-<?php echo (int) $oria_lid; ?>" data-offer-view="<?php echo (int) $oria_lid; ?>" data-offer-place="<?php echo esc_attr( $oria_place ); ?>">
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
				<?php if ( $oria_promo ) : ?>
					<p class="oh-offer-card__promo"><?php esc_html_e( 'Promoted', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(paid placement)', 'oria' ); ?></span></p>
				<?php endif; ?>
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
					<a class="oh-offer-card__go" href="<?php echo esc_url( \Oria\Theme\outbound( $oria_o['source'], 'offer' ) ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="offer" data-oria-place="<?php echo esc_attr( $oria_place ); ?>" data-oria-id="<?php echo (int) $oria_lid; ?>"><?php esc_html_e( 'View offer', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(opens their site in a new tab)', 'oria' ); ?></span><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
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
				<?php if ( ! empty( $oria_promo['code'] ) ) : ?>
					<p class="oh-offer-card__code"><?php printf( esc_html__( 'Mention code %s when you book', 'oria' ), '<strong>' . esc_html( (string) $oria_promo['code'] ) . '</strong>' ); ?></p>
				<?php endif; ?>
				<p class="oh-offer-card__src"><?php echo esc_html( $oria_src ); ?></p>
			</div>
		</li>
