<?php
/**
 * How it works, in four steps.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_credits = (int) ( $args['credits'] ?? 50 );

$oria_steps = array(
	array(
		__( 'Join', 'oria' ),
		sprintf(
			/* translators: %d: credits per month */
			__( 'Pick up an Oria Pass and get %d credits each month.', 'oria' ),
			$oria_credits
		),
	),
	array( __( 'Discover', 'oria' ), __( 'Browse what participating studios around Perth have opened up.', 'oria' ) ),
	array( __( 'Book', 'oria' ), __( 'Spend credits on a class, a session or a room, whenever it suits.', 'oria' ) ),
	array( __( 'Try something new', 'oria' ), __( 'Build a routine out of several places instead of committing to one.', 'oria' ) ),
);
?>
<section class="wrap pass-how" aria-labelledby="pass-how-h">
	<h2 class="pass-h" id="pass-how-h"><?php esc_html_e( 'One pass. Your choice.', 'oria' ); ?></h2>
	<ol class="pass-how__list">
		<?php foreach ( $oria_steps as $oria_i => $oria_s ) : ?>
			<li class="hstep">
				<span class="hstep__n"><?php echo esc_html( sprintf( '%02d', $oria_i + 1 ) ); ?></span>
				<h3 class="hstep__h"><?php echo esc_html( $oria_s[0] ); ?></h3>
				<p><?php echo esc_html( $oria_s[1] ); ?></p>
			</li>
		<?php endforeach; ?>
	</ol>
</section>
