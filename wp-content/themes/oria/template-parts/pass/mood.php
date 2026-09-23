<?php
/**
 * Start with how you want to feel.
 *
 * This is the hinge between the Pass and the rest of Oria Haven: the same
 * feelings-first idea the directory already sells, pointed at the Pass. The
 * buttons go to real directory pages, so it works now and can be repointed
 * at Pass inventory in Phase 3 without the section being rebuilt.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

/* label, one line, where it goes today */
$oria_moods = array(
	array( __( 'Relax', 'oria' ), __( 'Yin, sauna, sound', 'oria' ), '/explore/perth/spa/' ),
	array( __( 'Reset', 'oria' ), __( 'Breathwork, meditation', 'oria' ), '/explore/perth/breathwork/' ),
	array( __( 'Recharge', 'oria' ), __( 'Pilates, cold plunge', 'oria' ), '/explore/perth/fitness/' ),
	array( __( 'Sleep better', 'oria' ), __( 'Slow movement, float', 'oria' ), '/explore/perth/sound/' ),
	array( __( 'Move', 'oria' ), __( 'Yoga, strength, mobility', 'oria' ), '/explore/perth/yoga/' ),
	array( __( 'Recover', 'oria' ), __( 'Sauna, contrast, massage', 'oria' ), '/explore/perth/recovery/' ),
	array( __( 'Connect', 'oria' ), __( 'Group classes, circles', 'oria' ), '/explore/perth/meditation/' ),
);
?>
<section class="wrap pass-mood" aria-labelledby="pass-mood-h">
	<h2 class="pass-h" id="pass-mood-h"><?php esc_html_e( 'Start with how you want to feel.', 'oria' ); ?></h2>
	<p class="pass-sub">
		<?php esc_html_e( 'You do not need to know whether you want breathwork, Yin yoga or a sauna. Start with the feeling and work back.', 'oria' ); ?>
	</p>
	<div class="pass-mood__row">
		<?php foreach ( $oria_moods as $oria_m ) : ?>
			<a class="mchip" href="<?php echo esc_url( home_url( $oria_m[2] ) ); ?>" data-pass-mood="<?php echo esc_attr( sanitize_title( $oria_m[0] ) ); ?>">
				<b><?php echo esc_html( $oria_m[0] ); ?></b>
				<span><?php echo esc_html( $oria_m[1] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
