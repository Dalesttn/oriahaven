<?php
/**
 * /jobs/salary-guide/ (brief section 43).
 *
 * Advertised pay from real Oria job listings only. A row needs five or more
 * listings that quote pay in the same unit; until then the page says so
 * rather than filling the gap with anyone's guess.
 */

declare(strict_types=1);

use Oria\Core\Work;
use Oria\Core\Work\Market;

$oria_rows = Market\salary_rows();
$oria_fmt  = static fn( float $n ): string => '$' . ( $n >= 1000 || floor( $n ) === $n ? number_format( $n ) : number_format( $n, 2 ) );
?>
<header class="wkhero">
	<div class="wrap">
		<p class="micro wkhero__kicker"><?php esc_html_e( 'From real job listings', 'oria' ); ?></p>
		<h1 class="wkhero__title"><?php echo esc_html( Work\heading() ); ?></h1>
		<p class="lede wkhero__lede"><?php esc_html_e( 'What wellness employers advertise, from the job listings on Oria Haven over the last twelve months. Each figure is the middle of what employers advertised — not a survey, not an estimate.', 'oria' ); ?></p>
	</div>
</header>

<section class="wrap wksec">
	<?php if ( ! $oria_rows ) : ?>
		<div class="wkempty"><p><strong><?php esc_html_e( 'Not enough listings yet.', 'oria' ); ?></strong> <?php echo esc_html( sprintf( __( 'We publish a figure only once at least %d listings for the same role quote their pay. Until then, we would rather show nothing than guess.', 'oria' ), Market\SALARY_MIN ) ); ?></p>
			<a class="btn btn--dark btn--sm" href="<?php echo esc_url( Work\list_url( 'jobs' ) ); ?>"><?php esc_html_e( 'See current jobs', 'oria' ); ?></a></div>
	<?php else : ?>
		<div class="wktable">
			<table>
				<thead><tr><th scope="col"><?php esc_html_e( 'Role', 'oria' ); ?></th><th scope="col"><?php esc_html_e( 'Where', 'oria' ); ?></th><th scope="col"><?php esc_html_e( 'Median advertised', 'oria' ); ?></th><th scope="col"><?php esc_html_e( 'Range', 'oria' ); ?></th><th scope="col"><?php esc_html_e( 'Listings', 'oria' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $oria_rows as $oria_r ) : ?>
						<?php $oria_unit = Work\PAY_UNITS[ $oria_r['unit'] ] ?? ''; ?>
						<tr>
							<th scope="row"><a href="<?php echo esc_url( Work\list_url( 'jobs', '', $oria_r['slug'] ) ); ?>"><?php echo esc_html( $oria_r['profession'] ); ?></a></th>
							<td><?php echo esc_html( $oria_r['city'] ?: __( 'Australia', 'oria' ) ); ?></td>
							<td><strong><?php echo esc_html( $oria_fmt( (float) $oria_r['median'] ) . ' ' . $oria_unit ); ?></strong></td>
							<td><?php echo esc_html( $oria_fmt( (float) $oria_r['low'] ) . '–' . $oria_fmt( (float) $oria_r['high'] ) ); ?></td>
							<td><?php echo (int) $oria_r['n']; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="hint"><?php esc_html_e( 'Advertised pay only, before tax and super; per-class and hourly rates are never mixed. For award rates and conditions, check the Fair Work Ombudsman (fairwork.gov.au).', 'oria' ); ?></p>
	<?php endif; ?>
</section>
