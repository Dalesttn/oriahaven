<?php
/**
 * "Available with Oria Pass" — the block that appears on a listing page.
 *
 * Drawn only when this provider has genuinely opened something. No
 * placeholder, no "coming soon", no sample rows: a listing page is where
 * somebody decides whether to go somewhere, and inventing availability
 * there would be the most expensive lie on the site.
 *
 * It also stays small on purpose. The brief asks that it not dominate the
 * page, and it should not: the profile is the studio's, and this is one
 * more way to reach them rather than the point of the page.
 *
 * Args: id (listing id).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Pass\Sessions\upcoming' ) ) {
	return;
}

use Oria\Pass\Booking;
use Oria\Pass\Membership;
use Oria\Pass\Sessions;
use Oria\Pass\Settings;

$oria_listing = (int) ( $args['id'] ?? get_the_ID() );
$oria_rows    = Sessions\upcoming( array( 'listing_id' => $oria_listing, 'limit' => 4 ) );

if ( ! $oria_rows ) {
	return;
}

$oria_uid    = get_current_user_id();
$oria_member = $oria_uid > 0 && Membership\is_active( $oria_uid );

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_ok  = sanitize_key( (string) ( $_GET['pass_ok'] ?? '' ) );
$oria_err = sanitize_key( (string) ( $_GET['pass_err'] ?? '' ) );
$oria_ref = sanitize_text_field( (string) ( $_GET['pass_ref'] ?? '' ) );
// phpcs:enable

$oria_says = array(
	'full'           => __( 'Those places went while you were looking. There may be another session below.', 'oria' ),
	'already_booked' => __( 'You have already booked that one.', 'oria' ),
	'insufficient'   => __( 'Not enough credits left this month for that one.', 'oria' ),
	'no_membership'  => __( 'You need an active Oria Pass to book with credits.', 'oria' ),
	'started'        => __( 'That session has already started.', 'oria' ),
	'expired'        => __( 'That took too long — please try again.', 'oria' ),
);
?>

<section class="xpass" id="xpass" aria-labelledby="xpass-h">
	<div class="xpass__head">
		<h2 class="xpass__h" id="xpass-h"><?php esc_html_e( 'Available with Oria Pass', 'oria' ); ?></h2>
		<span class="xpass__badge"><?php esc_html_e( 'Pass partner', 'oria' ); ?></span>
	</div>

	<?php if ( 'booked' === $oria_ok ) : ?>
		<p class="xpass__ok" role="status">
			<?php
			printf(
				/* translators: %s: booking reference */
				esc_html__( 'Booked. Your reference is %s — it is in My Oria too.', 'oria' ),
				'<b>' . esc_html( $oria_ref ) . '</b>'
			);
			?>
		</p>
	<?php elseif ( '' !== $oria_err ) : ?>
		<p class="xpass__err" role="alert">
			<?php echo esc_html( $oria_says[ $oria_err ] ?? __( 'That did not go through. Nothing has been charged.', 'oria' ) ); ?>
		</p>
	<?php endif; ?>

	<ul class="xpass__list">
		<?php foreach ( $oria_rows as $oria_s ) : ?>
			<?php $oria_left = Sessions\places_left( $oria_s ); ?>
			<li class="xpass__row">
				<div class="xpass__what">
					<span class="xpass__title"><?php echo esc_html( (string) $oria_s->title ); ?></span>
					<span class="xpass__when"><?php echo esc_html( Sessions\when( $oria_s ) ); ?></span>
					<?php if ( $oria_left <= 2 ) : ?>
						<?php
						/* Said only when it is true, and it is read from the row. */
						?>
						<span class="xpass__left">
							<?php
							printf(
								/* translators: %d: places left */
								esc_html( _n( '%d place left', '%d places left', $oria_left, 'oria' ) ),
								(int) $oria_left
							);
							?>
						</span>
					<?php endif; ?>
				</div>

				<?php get_template_part( 'template-parts/pass/credit', null, array( 'credits' => (int) $oria_s->credits_required ) ); ?>

				<?php if ( $oria_member ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="xpass__go">
						<input type="hidden" name="action" value="oria_pass_book">
						<input type="hidden" name="session_id" value="<?php echo (int) $oria_s->id; ?>">
						<?php wp_nonce_field( 'oria_pass_book' ); ?>
						<button class="btn btn--sm btn--dark" type="submit" data-oria-event="oria_pass_booking_started">
							<?php esc_html_e( 'Book', 'oria' ); ?>
						</button>
					</form>
				<?php else : ?>
					<a class="btn btn--sm btn--ghost xpass__go" href="<?php echo esc_url( \Oria\Pass\Route\url() ); ?>">
						<?php echo esc_html( $oria_uid > 0 ? __( 'Get a Pass', 'oria' ) : __( 'About the Pass', 'oria' ) ); ?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="xpass__fine">
		<?php
		printf(
			/* translators: %s: cancellation window in hours */
			esc_html__( 'Booked with credits from an Oria Pass membership. Cancel more than %s hours ahead and the credits come back.', 'oria' ),
			esc_html( (string) Settings\get( 'cancel_cutoff_hrs' ) )
		);
		?>
	</p>
</section>
