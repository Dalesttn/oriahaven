<?php
/**
 * "Where you can use it" — the places open to a Pass right now.
 *
 * A member holding credits has one question the rest of the dashboard
 * does not answer: where do these go? The credit card says what is left
 * and "Coming up" says what is already booked, but neither says where to
 * spend the rest, and a membership nobody can find a use for is a
 * membership that gets cancelled.
 *
 * Only places with a session that can still be booked appear. A studio
 * whose places have gone is not somewhere you can use a Pass today, and
 * showing it would be an invitation to a closed door.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Pass\Sessions\partners' ) ) {
	return;
}

use Oria\Pass\Sessions;

$oria_places = Sessions\partners( 12 );

if ( ! $oria_places ) {
	/*
	 * Said plainly rather than hidden. An empty section here is real news
	 * -- the member is paying and there is nothing to spend it on -- and
	 * quietly rendering nothing would leave them wondering whether the
	 * page was broken.
	 */
	?>
	<section class="mycard myplaces" aria-labelledby="myPlacesTitle">
		<h2 class="mycard__title" id="myPlacesTitle"><?php esc_html_e( 'Where you can use it', 'oria' ); ?></h2>
		<p class="myplaces__none">
			<?php esc_html_e( 'Nothing is open for booking this minute. Studios add places as their week firms up, so it is worth another look in a day or two — your credits keep until the end of the month either way.', 'oria' ); ?>
		</p>
	</section>
	<?php
	return;
}
?>

<section class="mycard myplaces" aria-labelledby="myPlacesTitle">
	<h2 class="mycard__title" id="myPlacesTitle"><?php esc_html_e( 'Where you can use it', 'oria' ); ?></h2>
	<p class="myplaces__sub">
		<?php
		printf(
			/* translators: %s: number of places */
			esc_html( _n( '%s studio has places open to your Pass.', '%s studios have places open to your Pass.', count( $oria_places ), 'oria' ) ),
			esc_html( number_format_i18n( count( $oria_places ) ) )
		);
		?>
	</p>

	<ul class="myplaces__list">
		<?php foreach ( $oria_places as $oria_p ) : ?>
			<?php
			$oria_id   = (int) $oria_p->listing_id;
			$oria_name = wp_specialchars_decode( (string) get_the_title( $oria_id ), ENT_QUOTES );

			if ( '' === $oria_name || 'publish' !== get_post_status( $oria_id ) ) {
				continue; // A session on a listing nobody can open is not an option.
			}

			// Naive Perth strings in, Perth time out. Never strtotime() these.
			$oria_when = date_create_immutable( (string) $oria_p->soonest, wp_timezone() );
			?>
			<li class="myplaces__row">
				<a class="myplaces__name" href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>">
					<?php echo esc_html( $oria_name ); ?>
				</a>

				<span class="myplaces__meta">
					<?php if ( $oria_when ) : ?>
						<span class="myplaces__next"><?php echo esc_html( wp_date( 'D j M · g:ia', $oria_when->getTimestamp() ) ); ?></span>
					<?php endif; ?>

					<span class="myplaces__from">
						<?php
						printf(
							/* translators: %d: credits */
							esc_html__( 'from %d credits', 'oria' ),
							(int) $oria_p->from_credits
						);
						?>
					</span>

					<?php if ( (int) $oria_p->sessions > 1 ) : ?>
						<span class="myplaces__count">
							<?php
							printf(
								/* translators: %d: number of sessions */
								esc_html( _n( '%d session', '%d sessions', (int) $oria_p->sessions, 'oria' ) ),
								(int) $oria_p->sessions
							);
							?>
						</span>
					<?php endif; ?>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="myplaces__fine">
		<?php esc_html_e( 'Book on the studio’s own page. Your credits come off when the place is confirmed.', 'oria' ); ?>
	</p>
</section>
