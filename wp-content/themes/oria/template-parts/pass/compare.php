<?php
/**
 * Why a Pass rather than a studio membership.
 *
 * Written without taking a swing at studios -- they are the businesses this
 * product depends on, and a comparison table that calls their memberships
 * bad would be read by exactly the people being recruited as partners.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_rows = array(
	array( __( 'Several studios on one membership', 'oria' ), true, false ),
	array( __( 'Several kinds of wellness', 'oria' ), true, false ),
	array( __( 'Built for trying somewhere new', 'oria' ), true, false ),
	array( __( 'Best value if you go to one place often', 'oria' ), false, true ),
	array( __( 'A relationship with one studio', 'oria' ), false, true ),
);

$oria_tick = '<span class="cmp__y" aria-hidden="true">&#10003;</span><span class="sr-only">' . esc_html__( 'Yes', 'oria' ) . '</span>';
$oria_dash = '<span class="cmp__n" aria-hidden="true">&ndash;</span><span class="sr-only">' . esc_html__( 'No', 'oria' ) . '</span>';
?>
<section class="wrap pass-cmp" aria-labelledby="pass-cmp-h">
	<h2 class="pass-h" id="pass-cmp-h"><?php esc_html_e( 'Why Oria Pass?', 'oria' ); ?></h2>

	<div class="cmp__scroll">
		<table class="cmp">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'What you want', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Oria Pass', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'A studio membership', 'oria' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $oria_rows as $oria_r ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $oria_r[0] ); ?></th>
						<td data-label="<?php esc_attr_e( 'Oria Pass', 'oria' ); ?>"><?php echo wp_kses_post( $oria_r[1] ? $oria_tick : $oria_dash ); ?></td>
						<td data-label="<?php esc_attr_e( 'Studio membership', 'oria' ); ?>"><?php echo wp_kses_post( $oria_r[2] ? $oria_tick : $oria_dash ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<p class="pass-note">
		<?php esc_html_e( 'Love a particular studio? Keep going. Oria Pass is for the weeks you also want to try something else.', 'oria' ); ?>
	</p>
</section>
