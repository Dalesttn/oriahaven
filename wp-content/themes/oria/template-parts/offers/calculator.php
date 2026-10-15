<?php
/**
 * "Illustrative cost per visit" for day passes on an activity page: the
 * pass price divided by the number of visits the reader expects to make.
 * Only passes with a fixed upfront price and a stated length take part --
 * a variable or unpublished price is never divided. Nothing here implies a
 * visit is guaranteed: the copy says to check the timetable, and a number
 * of visits beyond the pass's days is flagged rather than computed quietly.
 *
 * Server-rendered at DEFAULT visits so it reads without script; offers.js
 * recalculates as the slider moves.
 *
 * $args: rows (OffersHub rows for the page).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\OffersHub as Hub;

$oria_passes = array_values(
	array_filter(
		(array) ( $args['rows'] ?? array() ),
		static fn( array $r ): bool => 'pass' === $r['format'] && null !== $r['amount'] && $r['amount'] > 0 && $r['days'] > 0
	)
);
if ( count( $oria_passes ) < 2 ) {
	return;
}
usort( $oria_passes, static fn( array $a, array $b ): int => $a['amount'] <=> $b['amount'] );
$oria_default = 8;
?>
<section class="ofx-calc" aria-labelledby="ofx-calc-title" data-ofx-calc>
	<div class="ofx-calc__head">
		<h2 class="ofx-section__title" id="ofx-calc-title"><?php esc_html_e( 'What a pass works out at per visit', 'oria' ); ?></h2>
		<p class="ofx-calc__lede"><?php esc_html_e( 'A day pass costs less per visit the more you go. Choose how many times you expect to go during the pass to see an illustrative cost per visit. It assumes you can book that many sessions, so check the timetable before you buy.', 'oria' ); ?></p>
	</div>
	<div class="ofx-calc__control">
		<label for="ofx-calc-visits"><?php esc_html_e( 'Visits during the pass', 'oria' ); ?></label>
		<input type="range" id="ofx-calc-visits" min="1" max="30" step="1" value="<?php echo (int) $oria_default; ?>" data-ofx-calc-input aria-describedby="ofx-calc-out">
		<output id="ofx-calc-out" for="ofx-calc-visits" data-ofx-calc-out><?php echo esc_html( sprintf( /* translators: %d: visits */ _n( '%d visit', '%d visits', $oria_default, 'oria' ), $oria_default ) ); ?></output>
	</div>
	<div class="ofx-table__frame" tabindex="0" role="region" aria-labelledby="ofx-calc-title">
		<table class="ofx-calc__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Pass', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Price', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Length', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Illustrative cost per visit', 'oria' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $oria_passes as $oria_r ) : ?>
					<?php
					$oria_over = $oria_default > $oria_r['days'];
					$oria_each = (float) $oria_r['amount'] / $oria_default;
					?>
					<tr data-ofx-calc-row data-amount="<?php echo esc_attr( (string) $oria_r['amount'] ); ?>" data-days="<?php echo (int) $oria_r['days']; ?>">
						<th scope="row"><a href="#offer-<?php echo (int) $oria_r['id']; ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_r['id'] ) ) ); ?></a><span><?php echo esc_html( (string) $oria_r['suburb'] ); ?></span></th>
						<td><?php echo esc_html( Hub\money( (float) $oria_r['amount'] ) ); ?></td>
						<td><?php echo esc_html( sprintf( /* translators: %d: days */ _n( '%d day', '%d days', $oria_r['days'], 'oria' ), $oria_r['days'] ) ); ?></td>
						<td class="ofx-calc__each" data-ofx-calc-each><?php echo esc_html( $oria_over ? __( 'More visits than days in the pass', 'oria' ) : '$' . number_format( $oria_each, 2 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="ofx-calc__note"><?php esc_html_e( 'Illustrative only: the pass price divided by your visits. Offers without a fixed price or a stated length are left out.', 'oria' ); ?></p>
</section>
