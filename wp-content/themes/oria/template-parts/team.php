<?php
/**
 * The people behind a listing.
 *
 * Facts only, in a fixed order: who they are, what they do here, what they
 * hold, and — where it exists — a registration a reader can go and check.
 * The registration is given its own line rather than being folded into the
 * qualifications, because a number somebody can verify is a different kind
 * of statement from a course somebody says they did.
 *
 * @var array $args {
 *     @type int $listing_id
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oria_listing_id = (int) ( $args['listing_id'] ?? get_the_ID() );

if ( ! function_exists( '\Oria\Core\Team\visible' ) ) {
	return;
}

$oria_team = \Oria\Core\Team\visible( $oria_listing_id );

if ( ! $oria_team ) {
	return;
}
?>

<section class="section teamsec" id="team">
	<h2 class="h3 teamsec__title">
		<?php echo esc_html( _n( 'Who you will see', 'Who you will see', count( $oria_team ), 'oria' ) ); ?>
	</h2>

	<div class="teamgrid">
		<?php foreach ( $oria_team as $oria_person ) : ?>
			<article class="teamcard">
				<?php if ( $oria_person['photo'] > 0 ) : ?>
					<div class="teamcard__photo">
						<?php echo wp_get_attachment_image( $oria_person['photo'], 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => esc_attr( $oria_person['name'] ) ) ); ?>
					</div>
				<?php else : ?>
					<div class="teamcard__photo teamcard__photo--initials" aria-hidden="true">
						<?php echo esc_html( mb_substr( $oria_person['name'], 0, 1 ) ); ?>
					</div>
				<?php endif; ?>

				<div class="teamcard__body">
					<h3 class="teamcard__name"><?php echo esc_html( $oria_person['name'] ); ?></h3>

					<p class="teamcard__role">
						<?php echo esc_html( $oria_person['role'] ); ?>
						<?php if ( $oria_person['years'] > 0 ) : ?>
							<span class="teamcard__years">
								<?php
								printf(
									/* translators: %d: number of years */
									esc_html( _n( '%d year practising', '%d years practising', $oria_person['years'], 'oria' ) ),
									(int) $oria_person['years']
								);
								?>
							</span>
						<?php endif; ?>
					</p>

					<?php if ( '' !== $oria_person['bio'] ) : ?>
						<p class="teamcard__bio"><?php echo esc_html( $oria_person['bio'] ); ?></p>
					<?php endif; ?>

					<?php if ( $oria_person['quals'] ) : ?>
						<ul class="teamcard__quals">
							<?php foreach ( $oria_person['quals'] as $oria_q ) : ?>
								<li><?php echo esc_html( $oria_q ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( \Oria\Core\Team\has_registration( $oria_person ) ) : ?>
						<p class="teamcard__reg">
							<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M8 1.8l5.2 2v4c0 3.1-2.1 5.4-5.2 6.4-3.1-1-5.2-3.3-5.2-6.4v-4l5.2-2z"/><path d="M5.8 8.1l1.6 1.6 3-3.2"/></svg>
							<?php
							$oria_reg = $oria_person['reg_body'];
							if ( '' !== $oria_person['reg_id'] ) {
								$oria_reg .= ' · ' . $oria_person['reg_id'];
							}
							if ( '' !== $oria_person['reg_url'] ) {
								printf(
									'<a href="%s" rel="nofollow noopener" target="_blank">%s</a>',
									esc_url( $oria_person['reg_url'] ),
									esc_html( $oria_reg )
								);
							} else {
								echo esc_html( $oria_reg );
							}
							?>
						</p>
					<?php endif; ?>

					<?php if ( $oria_person['specialties'] ) : ?>
						<div class="teamcard__tags">
							<?php foreach ( $oria_person['specialties'] as $oria_term ) : ?>
								<span class="pill"><?php echo esc_html( \Oria\Theme\tname( $oria_term ) ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $oria_person['languages'] ) : ?>
						<p class="teamcard__langs">
							<?php
							printf(
								/* translators: %s: list of languages */
								esc_html__( 'Also speaks %s', 'oria' ),
								esc_html( implode( ', ', $oria_person['languages'] ) )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>
