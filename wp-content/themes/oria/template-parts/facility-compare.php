<?php
/**
 * A facility page's side-by-side view and "before you go", from the saved
 * Facility access rows. Cheapest verified price first; unknown prices last,
 * and labelled as unknown rather than as free.
 *
 * @var array $args { sums: array<int, array>, place: string, facility: string }
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\FacilityAccess as FA;

$oria_sums = (array) ( $args['sums'] ?? array() );
if ( ! $oria_sums ) {
	return;
}
$oria_what = strtolower( FA\FACILITIES[ (string) ( $args['facility'] ?? '' ) ] ?? __( 'facility', 'oria' ) );

uasort(
	$oria_sums,
	static function ( array $a, array $b ): int {
		$pa = null === $a['price'] ? INF : (float) $a['price'];
		$pb = null === $b['price'] ? INF : (float) $b['price'];
		return $pa <=> $pb;
	}
);

$oria_towels = array();
$oria_ages   = array();
foreach ( $oria_sums as $oria_id => $oria_s ) {
	foreach ( (array) $oria_s['essentials'] as $oria_e ) {
		if ( 0 === stripos( $oria_e, 'Towels:' ) ) {
			$oria_towels[] = get_the_title( (int) $oria_id ) . ' — ' . trim( substr( $oria_e, 7 ) );
		}
	}
	if ( preg_match( '/(\d+)\+/', (string) $oria_s['access'], $oria_m ) ) {
		$oria_ages[ (int) $oria_m[1] ] = true;
	}
}
?>
<section class="fac-compare" aria-labelledby="facCompareH">
	<h2 class="h3" id="facCompareH">
		<?php
		/* translators: %s: facility, e.g. steam room */
		printf( esc_html__( 'Every %s here, side by side', 'oria' ), esc_html( $oria_what ) );
		?>
	</h2>
	<p class="fac-compare__lede"><?php esc_html_e( 'The standard adult visit that includes it, as each venue publishes it. Prices are what the venue listed on the day checked; confirm before you go.', 'oria' ); ?></p>
	<div class="fac-compare__scroll" tabindex="0" role="region" aria-labelledby="facCompareH">
		<table class="fac-compare__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Venue', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Price and basis', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Access time', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Who can go', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Also included', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Checked', 'oria' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $oria_sums as $oria_id => $oria_s ) : ?>
					<tr>
						<th scope="row"><a href="<?php echo esc_url( get_permalink( (int) $oria_id ) ); ?>"><?php echo esc_html( get_the_title( (int) $oria_id ) ); ?></a></th>
						<td>
							<b><?php echo esc_html( (string) $oria_s['price_text'] ); ?></b>
							<?php if ( '' !== (string) $oria_s['product'] ) : ?>
								<br><span class="muted"><?php echo esc_html( (string) $oria_s['product'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== (string) $oria_s['conditions'] ) : ?>
								<br><span class="muted"><?php echo esc_html( (string) $oria_s['conditions'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( '' !== (string) $oria_s['duration'] ? str_replace( ' access', '', (string) $oria_s['duration'] ) : __( 'Not stated', 'oria' ) ); ?></td>
						<td><?php echo esc_html( '' !== (string) $oria_s['access'] ? (string) $oria_s['access'] : __( 'Not stated', 'oria' ) ); ?><?php echo '' !== (string) $oria_s['notice'] ? '<br><span class="fac__notice">' . esc_html( (string) $oria_s['notice'] ) . '</span>' : ''; ?></td>
						<td><?php echo esc_html( $oria_s['includes'] ? implode( ', ', (array) $oria_s['includes'] ) : '—' ); ?></td>
						<td>
							<?php echo esc_html( (string) $oria_s['checked'] ); ?>
							<?php if ( '' !== (string) $oria_s['source'] ) : ?>
								<br><a href="<?php echo esc_url( (string) $oria_s['source'] ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'Source', 'oria' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<h2 class="h3 fac-compare__h"><?php esc_html_e( 'Before you go', 'oria' ); ?></h2>
	<ul class="fac-compare__go">
		<li><?php esc_html_e( 'Check how you get in. Some places sell a casual visit; others include the steam room with a pool entry, a bathhouse session, a treatment or a membership. Each card says which.', 'oria' ); ?></li>
		<?php if ( $oria_ages ) : ?>
			<li>
				<?php
				ksort( $oria_ages );
				printf(
					/* translators: %s: list of ages, e.g. "16+ and 18+" */
					esc_html__( 'Age rules differ: %s at the places that publish one.', 'oria' ),
					esc_html( implode( ' / ', array_map( static fn( int $a ): string => $a . '+', array_keys( $oria_ages ) ) ) )
				);
				?>
			</li>
		<?php endif; ?>
		<?php if ( $oria_towels ) : ?>
			<li>
				<?php esc_html_e( 'Towels:', 'oria' ); ?>
				<?php echo esc_html( implode( '; ', $oria_towels ) ); ?>.
				<?php esc_html_e( 'Anywhere not listed, check before you go.', 'oria' ); ?>
			</li>
		<?php endif; ?>
		<li><?php esc_html_e( 'Pack swimwear and a water bottle, and read the venue\'s own rules page before you book: booking, age and swimwear rules are theirs, and they change.', 'oria' ); ?></li>
		<li><?php esc_html_e( 'Oria Haven is a directory, not a health service. If you have a health concern, or you\'re pregnant or unsure, speak with your GP or a registered practitioner before you book.', 'oria' ); ?></li>
	</ul>
</section>
