<?php
/**
 * The membership card.
 *
 * One plan on purpose. The code takes a list so a second can be added, but
 * offering two before anybody has bought one is a decision made without
 * evidence.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_live    = ! empty( $args['live'] );
$oria_credits = (int) ( $args['credits'] ?? 50 );
?>
<section class="wrap pass-plan" aria-labelledby="pass-plan-h">
	<h2 class="pass-h" id="pass-plan-h"><?php esc_html_e( 'One membership. Plenty of possibilities.', 'oria' ); ?></h2>

	<div class="plan">
		<div class="plan__top">
			<h3 class="plan__name"><?php esc_html_e( 'Oria Pass', 'oria' ); ?></h3>
			<p class="plan__price">
				<b><?php echo esc_html( (string) ( $args['price'] ?? '$99' ) ); ?></b>
				<span><?php printf( '/ %s', esc_html( (string) ( $args['period'] ?? 'month' ) ) ); ?></span>
			</p>
			<p class="plan__credits">
				<?php
				printf(
					/* translators: %d: credits */
					esc_html__( '%d credits every month', 'oria' ),
					$oria_credits
				);
				?>
			</p>
		</div>

		<ul class="plan__list">
			<li><?php esc_html_e( 'Mix and match experiences across participating studios', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Spend credits on classes, sessions and rooms', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Everything managed inside My Oria', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Cancel your renewal whenever you like', 'oria' ); ?></li>
		</ul>

		<p class="plan__act">
			<a class="btn btn--dark" href="#pass-join" data-oria-event="oria_pass_join_click">
				<?php echo esc_html( (string) ( $args['cta'] ?? __( 'Join the waitlist', 'oria' ) ) ); ?>
			</a>
		</p>

		<p class="plan__fine">
			<?php if ( $oria_live ) : ?>
				<?php esc_html_e( 'What is available varies by studio, suburb and session. Unused credits expire at the end of each month.', 'oria' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Nothing to pay yet — this is what the membership will be when it opens. Join the waitlist and we will tell you when it does.', 'oria' ); ?>
			<?php endif; ?>
		</p>
	</div>
</section>
