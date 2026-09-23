<?php
/**
 * Three reasons, immediately under the hero.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_city = (string) ( $args['city'] ?? 'Perth' );

$oria_rows = array(
	array(
		__( 'Discover', 'oria' ),
		__( 'Try wellness experiences you would probably never have booked on your own.', 'oria' ),
	),
	array(
		__( 'Flexible', 'oria' ),
		__( 'Spend your credits across different participating studios, not just one.', 'oria' ),
	),
	array(
		__( 'Local', 'oria' ),
		/* translators: %s: city */
		sprintf( __( 'Explore wellness around %s, from your own suburb to somewhere new.', 'oria' ), $oria_city ),
	),
);
?>
<section class="wrap pass-value" aria-label="<?php esc_attr_e( 'What Oria Pass is for', 'oria' ); ?>">
	<div class="pass-value__row">
		<?php foreach ( $oria_rows as $oria_r ) : ?>
			<div class="pass-value__one">
				<h2 class="pass-value__h"><?php echo esc_html( $oria_r[0] ); ?></h2>
				<p><?php echo esc_html( $oria_r[1] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>
