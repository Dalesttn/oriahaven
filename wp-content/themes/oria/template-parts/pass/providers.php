<?php
/**
 * The provider pitch.
 *
 * The commercial half of the page, and the half that has to be true: the
 * whole product depends on studios believing this fills spare capacity
 * rather than eating their full-price bookings. So it leads with control,
 * and it never promises bookings.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_points = array(
	__( 'You decide which sessions to open, and how many places on each.', 'oria' ),
	__( 'Your own bookings stay yours — nothing changes about how you sell normally.', 'oria' ),
	__( 'Reach people actively looking to try somewhere new in your suburb.', 'oria' ),
	__( 'Get paid for eligible bookings. No discounting your whole timetable.', 'oria' ),
);
?>
<section class="wrap pass-prov" aria-labelledby="pass-prov-h">
	<div class="prov">
		<div class="prov__say">
			<p class="micro prov__eyebrow"><?php esc_html_e( 'For studios', 'oria' ); ?></p>
			<h2 class="pass-h" id="pass-prov-h"><?php esc_html_e( 'Have an empty space? Turn it into a new customer.', 'oria' ); ?></h2>
			<p class="prov__lede">
				<?php esc_html_e( 'Oria Pass puts selected spare capacity in front of Perth locals who are actively looking for something to try. You choose what to release, and when.', 'oria' ); ?>
			</p>
			<ul class="prov__list">
				<?php foreach ( $oria_points as $oria_p ) : ?>
					<li><?php echo esc_html( $oria_p ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="prov__acts">
				<a class="btn btn--light" href="<?php echo esc_url( \Oria\Pass\Route\url( 'partners' ) ); ?>" data-oria-event="oria_pass_provider_cta">
					<?php esc_html_e( 'Become a founding partner', 'oria' ); ?>
				</a>
				<a class="prov__alt" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>">
					<?php esc_html_e( 'Already listed? Claim your profile', 'oria' ); ?>
				</a>
			</p>
			<p class="prov__fine">
				<?php esc_html_e( 'We are signing up the first studios now, so the terms are still a conversation rather than a contract.', 'oria' ); ?>
			</p>
		</div>
	</div>
</section>
