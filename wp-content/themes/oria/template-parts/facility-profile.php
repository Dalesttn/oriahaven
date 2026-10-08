<?php
/**
 * A listing's facility access on its own profile: the same saved facts the
 * facility page's card shows (FacilityAccess\summary), plus the venue's
 * other offers that include the facility -- concessions, packs,
 * memberships -- each labelled as what it is, never as casual entry.
 *
 * @var array $args { id: int, sec: int }
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\FacilityAccess as FA;

$oria_id  = (int) ( $args['id'] ?? 0 );
$oria_sec = (int) ( $args['sec'] ?? 0 );
foreach ( FA\rows( $oria_id ) as $oria_row ) :
	$oria_s = FA\summary( $oria_id, (string) $oria_row['facility'] );
	if ( ! $oria_s ) {
		continue;
	}
	$oria_label = FA\FACILITIES[ $oria_row['facility'] ] ?? '';
	?>
	<section class="xp-sec" id="<?php echo esc_attr( 'facility-' . $oria_row['facility'] ); ?>" aria-labelledby="xp-fa-<?php echo esc_attr( (string) $oria_sec ); ?>">
		<h2 class="h2 xp-sec__title" id="xp-fa-<?php echo esc_attr( (string) $oria_sec ); ?>">
			<?php
			/* translators: %s: facility, e.g. Steam room */
			printf( esc_html__( '%s access', 'oria' ), esc_html( $oria_label ) );
			?>
		</h2>
		<p class="xp-sec__hint"><?php esc_html_e( 'As the venue publishes it, on the day shown. Prices change: confirm before you go.', 'oria' ); ?></p>
		<?php get_template_part( 'template-parts/facility-block', null, array( 's' => $oria_s ) ); ?>
		<?php if ( $oria_s['others'] ) : ?>
			<div class="fac-compare__scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Other ways in', 'oria' ); ?>" style="margin-top:.75rem">
				<table class="fac-compare__table fac-compare__table--stack">
					<caption class="sr-only"><?php esc_html_e( 'Other offers that include it', 'oria' ); ?></caption>
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Other ways in', 'oria' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Kind', 'oria' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Price', 'oria' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Conditions', 'oria' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( (array) $oria_s['others'] as $oria_o ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( (string) $oria_o['product'] ); ?></th>
								<td data-label="<?php esc_attr_e( 'Kind', 'oria' ); ?>"><?php echo esc_html( FA\KINDS[ $oria_o['kind'] ] ?? '' ); ?></td>
								<td data-label="<?php esc_attr_e( 'Price', 'oria' ); ?>"><?php echo esc_html( FA\price_label( $oria_o ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Conditions', 'oria' ); ?>"><?php echo esc_html( (string) $oria_o['conditions'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endforeach; ?>
