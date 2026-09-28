<?php
/**
 * A facility page's side-by-side view and "before you go", from the saved
 * Facility access rows. Cheapest verified price first; unknown prices last,
 * and labelled as unknown rather than as free.
 *
 * A real table on wide screens; under 52rem each row stacks into a labelled
 * card (the td data-label attributes), so a phone never scrolls sideways.
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

/** The venue's suburb: its most specific area term. */
$oria_suburb = static function ( int $id ): string {
	$terms = wp_get_post_terms( $id, 'area' );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '';
	}
	usort( $terms, static fn( $a, $b ): int => (int) ( 0 === (int) $a->parent ) <=> (int) ( 0 === (int) $b->parent ) );
	return html_entity_decode( $terms[0]->name, ENT_QUOTES, 'UTF-8' );
};

/** "Casual entry, 16+, shared" -> chips; the first says how you get in. */
$oria_chips = static fn( string $access ): array => array_values( array_filter( array_map( 'trim', explode( ', ', $access ) ) ) );

/** "28 September 2026" -> "28 Sep 2026". */
$oria_short = static function ( string $long ): string {
	$ts = strtotime( $long );
	return $ts ? wp_date( 'j M Y', $ts ) : $long;
};
?>
<section class="fac-compare" aria-labelledby="facCompareH">
	<div class="fac-compare__head">
		<h2 class="h3" id="facCompareH">
			<?php
			/* translators: %s: facility, e.g. steam room */
			printf( esc_html__( 'Every %s here, side by side', 'oria' ), esc_html( $oria_what ) );
			?>
		</h2>
		<p class="fac-compare__lede"><?php esc_html_e( 'The standard adult visit that includes it, as each venue publishes it, cheapest first. Prices are what the venue listed on the day checked; confirm before you go.', 'oria' ); ?></p>
	</div>
	<div class="fac-compare__scroll">
		<table class="fac-table">
			<caption class="sr-only">
				<?php
				/* translators: %s: facility */
				printf( esc_html__( 'Price, access and inclusions for each %s listed', 'oria' ), esc_html( $oria_what ) );
				?>
			</caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Venue', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Price', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Time', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Who can go', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Also included', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Checked', 'oria' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $oria_sums as $oria_id => $oria_s ) : ?>
					<?php
					$oria_chipset = $oria_chips( (string) $oria_s['access'] );
					$oria_sub     = $oria_suburb( (int) $oria_id );
					$oria_mins    = '' !== (string) $oria_s['duration'] ? trim( str_replace( 'access', '', (string) $oria_s['duration'] ) ) : '';
					?>
					<tr>
						<th scope="row" class="fac-table__venue">
							<a href="<?php echo esc_url( get_permalink( (int) $oria_id ) ); ?>"><?php echo esc_html( get_the_title( (int) $oria_id ) ); ?></a>
							<?php if ( '' !== $oria_sub ) : ?>
								<span class="fac-table__sub"><?php echo esc_html( $oria_sub ); ?></span>
							<?php endif; ?>
						</th>
						<td class="fac-table__price" data-label="<?php esc_attr_e( 'Price', 'oria' ); ?>">
							<b class="<?php echo null === $oria_s['price'] ? 'is-unknown' : ''; ?>"><?php echo esc_html( (string) $oria_s['price_text'] ); ?></b>
							<?php if ( '' !== (string) $oria_s['product'] ) : ?>
								<span class="fac-table__product"><?php echo esc_html( (string) $oria_s['product'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== (string) $oria_s['conditions'] ) : ?>
								<span class="fac-table__note"><?php echo esc_html( (string) $oria_s['conditions'] ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Time', 'oria' ); ?>">
							<?php if ( '' !== $oria_mins ) : ?>
								<span class="fac-pill"><?php echo esc_html( $oria_mins ); ?></span>
							<?php else : ?>
								<span class="fac-table__none"><?php esc_html_e( 'Not stated', 'oria' ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Who can go', 'oria' ); ?>">
							<?php if ( $oria_chipset ) : ?>
								<span class="fac-chips">
									<?php foreach ( $oria_chipset as $oria_ci => $oria_c ) : ?>
										<span class="fac-chip<?php echo 0 === $oria_ci ? ' fac-chip--lead' : ''; ?>"><?php echo esc_html( ucfirst( $oria_c ) ); ?></span>
									<?php endforeach; ?>
								</span>
							<?php else : ?>
								<span class="fac-table__none"><?php esc_html_e( 'Not stated', 'oria' ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== (string) $oria_s['notice'] ) : ?>
								<span class="fac__notice fac-table__notice"><?php echo esc_html( (string) $oria_s['notice'] ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Also included', 'oria' ); ?>">
							<?php if ( $oria_s['includes'] ) : ?>
								<span class="fac-chips">
									<?php foreach ( (array) $oria_s['includes'] as $oria_inc ) : ?>
										<span class="fac-chip fac-chip--quiet"><?php echo esc_html( $oria_inc ); ?></span>
									<?php endforeach; ?>
								</span>
							<?php else : ?>
								<span class="fac-table__none">&mdash;</span>
							<?php endif; ?>
						</td>
						<td class="fac-table__checked" data-label="<?php esc_attr_e( 'Checked', 'oria' ); ?>">
							<?php echo esc_html( $oria_short( (string) $oria_s['checked'] ) ); ?>
							<?php if ( '' !== (string) $oria_s['source'] ) : ?>
								<a href="<?php echo esc_url( (string) $oria_s['source'] ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'Source', 'oria' ); ?><span class="sr-only"> <?php echo esc_html( sprintf( /* translators: %s: venue */ __( 'for %s (opens in a new tab)', 'oria' ), get_the_title( (int) $oria_id ) ) ); ?></span></a>
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
