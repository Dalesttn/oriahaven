<?php
/**
 * Every offer on an activity page side by side, server-rendered so it is
 * there for crawlers and without script. On narrow screens the table
 * scrolls inside its own frame (with a visible cue), never the page.
 *
 * Unknowns say so -- "Not specified — check with the business" -- and are
 * never left blank, which reads as "none". The renewal column appears only
 * when at least one offer states what happens afterwards.
 *
 * $args: rows (OffersHub rows, in page order), noun, place.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\OffersHub as Hub;

$oria_rows    = (array) ( $args['rows'] ?? array() );
$oria_place   = sanitize_key( (string) ( $args['place'] ?? 'offers' ) );
$oria_unknown = __( 'Not specified — check with the business', 'oria' );
$oria_renew   = (bool) array_filter( $oria_rows, static fn( array $r ): bool => ! empty( $r['offer']['extra']['renewal'] ) );
?>
<section class="ofx-table" aria-labelledby="ofx-table-title">
	<h2 class="ofx-section__title" id="ofx-table-title"><?php echo esc_html( sprintf( /* translators: %s: e.g. "yoga intro offers" */ __( 'Compare %s side by side', 'oria' ), (string) ( $args['noun'] ?? 'offers' ) ) ); ?></h2>
	<p class="ofx-table__cue" aria-hidden="true"><?php esc_html_e( 'Scroll sideways to see every column', 'oria' ); ?></p>
	<div class="ofx-table__frame" tabindex="0" role="region" aria-labelledby="ofx-table-title">
		<table>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Business', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Upfront price', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'What’s included', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Validity', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Who can buy', 'oria' ); ?></th>
					<?php if ( $oria_renew ) : ?><th scope="col"><?php esc_html_e( 'Afterwards', 'oria' ); ?></th><?php endif; ?>
					<th scope="col"><span class="sr-only"><?php esc_html_e( 'Links', 'oria' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $oria_rows as $oria_r ) : ?>
					<?php
					$oria_o = (array) $oria_r['offer'];
					$oria_x = (array) ( $oria_o['extra'] ?? array() );
					$oria_p = null !== $oria_r['amount'] ? Hub\money( (float) $oria_r['amount'] ) : __( 'Not published', 'oria' );
					if ( null === $oria_r['amount'] && ! empty( $oria_x['price'] ) ) {
						$oria_p = (string) $oria_x['price'] . ' · ' . __( 'price varies', 'oria' );
					}
					$oria_valid = array_filter( array( (string) ( $oria_x['basis'] ?? '' ), '' !== (string) $oria_o['until'] ? sprintf( /* translators: %s: date */ __( 'ends %s', 'oria' ), mysql2date( 'j M Y', (string) $oria_o['until'] ) ) : '' ) );
					?>
					<tr>
						<th scope="row"><a href="#offer-<?php echo (int) $oria_r['id']; ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_r['id'] ) ) ); ?></a><span><?php echo esc_html( (string) $oria_r['suburb'] ); ?></span></th>
						<td class="ofx-table__price"><?php echo esc_html( $oria_p ); ?></td>
						<td><?php echo esc_html( ! empty( $oria_x['includes'] ) ? implode( ', ', (array) $oria_x['includes'] ) : $oria_unknown ); ?></td>
						<td><?php echo esc_html( $oria_valid ? implode( '; ', $oria_valid ) : $oria_unknown ); ?></td>
						<td><?php echo esc_html( ! empty( $oria_x['eligibility'] ) ? (string) $oria_x['eligibility'] : $oria_unknown ); ?></td>
						<?php if ( $oria_renew ) : ?><td><?php echo esc_html( ! empty( $oria_x['renewal'] ) ? (string) $oria_x['renewal'] : $oria_unknown ); ?></td><?php endif; ?>
						<td>
							<?php if ( ! empty( $oria_o['advertised'] ) ) : ?>
								<a href="<?php echo esc_url( \Oria\Theme\outbound( (string) $oria_o['source'], 'offer' ) ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="offer" data-oria-place="<?php echo esc_attr( $oria_place . '_table' ); ?>" data-oria-id="<?php echo (int) $oria_r['id']; ?>"><?php esc_html_e( 'View offer', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(opens their site in a new tab)', 'oria' ); ?></span></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
