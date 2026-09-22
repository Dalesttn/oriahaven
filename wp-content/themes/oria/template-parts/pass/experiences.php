<?php
/**
 * What you could try.
 *
 * Categories rather than sessions, because in this phase there are no
 * sessions -- and a grid of plausible-looking times nobody can book is the
 * fastest way to lose a visitor's trust on the second click.
 *
 * The credit ranges are illustrative and said to be. Each card links to the
 * real directory page for that kind of wellness, so the section is useful
 * today rather than a placeholder waiting on Phase 3.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

/* slug on /explore/perth/, label, one line, from-credits */
$oria_kinds = array(
	array( 'yoga', __( 'Yoga', 'oria' ), __( 'Slow, strong, or somewhere in between.', 'oria' ), 8 ),
	array( 'fitness', __( 'Pilates', 'oria' ), __( 'Reformer and mat, beginners welcome.', 'oria' ), 10 ),
	array( 'spa/infrared-sauna', __( 'Infrared sauna', 'oria' ), __( 'Slow down, switch off and warm up.', 'oria' ), 12 ),
	array( 'spa/cold-plunge', __( 'Cold plunge', 'oria' ), __( 'Two minutes you will feel all day.', 'oria' ), 12 ),
	array( 'sound/float-therapy', __( 'Float', 'oria' ), __( 'An hour of nothing at all.', 'oria' ), 20 ),
	array( 'breathwork', __( 'Breathwork', 'oria' ), __( 'Guided sessions that ask nothing of you but breathing.', 'oria' ), 14 ),
	array( 'meditation', __( 'Meditation', 'oria' ), __( 'Sit down. Somebody else does the thinking.', 'oria' ), 8 ),
	array( 'recovery', __( 'Recovery', 'oria' ), __( 'Sauna, compression, contrast and the rest.', 'oria' ), 12 ),
);
?>
<section class="wrap pass-kinds" id="pass-experiences" aria-labelledby="pass-kinds-h">
	<h2 class="pass-h" id="pass-kinds-h"><?php esc_html_e( 'What could you try this week?', 'oria' ); ?></h2>
	<p class="pass-sub">
		<?php esc_html_e( 'Your usual routine is fine. Sometimes the thing you need is the one you have not tried yet.', 'oria' ); ?>
	</p>

	<div class="pass-kinds__grid">
		<?php foreach ( $oria_kinds as $oria_k ) : ?>
			<a class="kcard" href="<?php echo esc_url( home_url( '/explore/perth/' . $oria_k[0] . '/' ) ); ?>"
				data-oria-event="oria_pass_experience_view" data-pass-kind="<?php echo esc_attr( $oria_k[0] ); ?>">
				<span class="kcard__name"><?php echo esc_html( $oria_k[1] ); ?></span>
				<span class="kcard__line"><?php echo esc_html( $oria_k[2] ); ?></span>
				<span class="kcard__from">
					<?php
					printf(
						/* translators: %d: number of credits */
						esc_html__( 'from %d credits', 'oria' ),
						(int) $oria_k[3]
					);
					?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>

	<p class="pass-note">
		<?php esc_html_e( 'Credit prices are set by each studio for each session, so these are a guide rather than a rate card.', 'oria' ); ?>
	</p>
</section>
