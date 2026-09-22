<?php
/**
 * /my-oria/pass/ — one address, two audiences.
 *
 * A provider manages the places they have opened. A member sees their
 * credits and what they have booked. Which one somebody gets is decided by
 * what they actually are, not by a role they were handed: owning a listing
 * is what makes somebody a provider here, and the handlers check the same
 * thing again before they write anything.
 *
 * The provider side is deliberately a table and a form rather than a
 * dashboard. A studio opening two places on a Tuesday wants to type four
 * numbers and leave; anything more elaborate is us enjoying ourselves at
 * their expense.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Pass\Sessions\upcoming' ) ) {
	return;
}

use Oria\Core\ListingEditor;
use Oria\Pass\Booking;
use Oria\Pass\Credits;
use Oria\Pass\Sessions;
use Oria\Pass\Settings;

$oria_uid     = get_current_user_id();
$oria_listing = function_exists( '\Oria\Core\ListingEditor\listing_for' ) ? ListingEditor\listing_for( $oria_uid ) : 0;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_ok   = sanitize_key( (string) ( $_GET['pass_ok'] ?? '' ) );
$oria_err  = sanitize_key( (string) ( $_GET['pass_err'] ?? '' ) );
$oria_edit = (int) ( $_GET['edit'] ?? 0 );
// phpcs:enable

$oria_said = array(
	'session_live'      => __( 'Open. Pass members can book it now.', 'oria' ),
	'session_draft'     => __( 'Saved as a draft. Nobody can see it until you publish it.', 'oria' ),
	'session_saved'     => __( 'Saved.', 'oria' ),
	'session_cancelled' => __( 'Session called off. Everyone who had booked has had their credits returned.', 'oria' ),
	'marked'            => __( 'Noted.', 'oria' ),
	'cancelled_refunded'=> __( 'Cancelled, and the credits are back.', 'oria' ),
	'cancelled_kept'    => __( 'Cancelled. That was inside the cutoff, so the credits stay spent.', 'oria' ),
);
$oria_wrong = array(
	'not_yours'    => __( 'That session belongs to a different listing.', 'oria' ),
	'no_places'    => __( 'Open at least one place to Pass members.', 'oria' ),
	'too_many'     => __( 'You cannot offer more Pass places than the session holds.', 'oria' ),
	'no_credits'   => __( 'Set what the session costs in credits.', 'oria' ),
	'no_title'     => __( 'Give the session a name people will recognise.', 'oria' ),
	'past'         => __( 'That start time has already been and gone.', 'oria' ),
	'below_booked' => __( 'You cannot offer fewer places than are already booked.', 'oria' ),
	'expired'      => __( 'That form sat open too long. Please try again.', 'oria' ),
);
?>

<div class="mypass-view">

	<?php if ( '' !== $oria_ok ) : ?>
		<p class="mynote mynote--ok" role="status"><?php echo esc_html( $oria_said[ $oria_ok ] ?? __( 'Done.', 'oria' ) ); ?></p>
	<?php elseif ( '' !== $oria_err ) : ?>
		<p class="mynote mynote--bad" role="alert"><?php echo esc_html( $oria_wrong[ $oria_err ] ?? __( 'That did not save.', 'oria' ) ); ?></p>
	<?php endif; ?>

	<?php if ( $oria_listing < 1 ) : ?>

		<?php
		/*
		 * Not a provider. Show them their own Pass instead of a page about
		 * managing one -- the card already knows how to say "you have no
		 * membership" gracefully, including saying nothing at all.
		 */
		?>
		<h1 class="myhead"><?php esc_html_e( 'Your Oria Pass', 'oria' ); ?></h1>
		<?php get_template_part( 'template-parts/my/pass-card' ); ?>

		<?php if ( ! function_exists( '\Oria\Pass\Membership\for_user' ) || ! \Oria\Pass\Membership\for_user( $oria_uid ) ) : ?>
			<p class="myempty">
				<?php esc_html_e( 'You do not have a Pass yet.', 'oria' ); ?>
				<a href="<?php echo esc_url( \Oria\Pass\Route\url() ); ?>"><?php esc_html_e( 'See what it is', 'oria' ); ?></a>
			</p>
		<?php endif; ?>

	<?php else : ?>

		<?php
		$oria_rows    = Sessions\for_listing( $oria_listing, 60 );
		$oria_editing = $oria_edit > 0 ? Sessions\get( $oria_edit ) : null;
		if ( $oria_editing && (int) $oria_editing->listing_id !== $oria_listing ) {
			$oria_editing = null; // Not theirs. The handler refuses it too.
		}

		/*
		 * Earnings, counted from what actually happened. A booking is worth
		 * its payout once somebody has turned up; a no-show and a cancelled
		 * booking are not the same thing, and only attendance is counted
		 * here so the number never overstates what is owed.
		 */
		$oria_earned = 0.0;
		$oria_pending = 0;
		foreach ( $oria_rows as $oria_r ) {
			foreach ( Booking\for_session( (int) $oria_r->id ) as $oria_bk ) {
				if ( 'attended' === (string) $oria_bk->status ) {
					$oria_earned += (float) $oria_bk->provider_payout;
				} elseif ( 'confirmed' === (string) $oria_bk->status ) {
					$oria_pending++;
				}
			}
		}
		?>

		<h1 class="myhead"><?php esc_html_e( 'Oria Pass places', 'oria' ); ?></h1>
		<p class="mysub">
			<?php esc_html_e( 'Open the sessions you want filled. Your own bookings are untouched, and you decide how many places go to Pass members.', 'oria' ); ?>
		</p>

		<div class="passtiles">
			<div class="passtile">
				<b><?php echo esc_html( number_format_i18n( count( $oria_rows ) ) ); ?></b>
				<span><?php esc_html_e( 'sessions', 'oria' ); ?></span>
			</div>
			<div class="passtile">
				<b><?php echo esc_html( number_format_i18n( $oria_pending ) ); ?></b>
				<span><?php esc_html_e( 'booked, still to come', 'oria' ); ?></span>
			</div>
			<div class="passtile">
				<b><?php echo esc_html( '$' . number_format_i18n( $oria_earned, 2 ) ); ?></b>
				<span><?php esc_html_e( 'earned from attended', 'oria' ); ?></span>
			</div>
		</div>

		<?php /* ------------------------------------------- the sessions */ ?>
		<?php if ( $oria_rows ) : ?>
			<section class="mysec">
				<h2 class="mysec__title"><?php esc_html_e( 'Your sessions', 'oria' ); ?></h2>

				<?php foreach ( $oria_rows as $oria_s ) : ?>
					<?php $oria_bookings = Booking\for_session( (int) $oria_s->id ); ?>
					<article class="mycard passrow">
						<div class="passrow__body">
						<div class="passrow__top">
							<div>
								<h3 class="passrow__title"><?php echo esc_html( (string) $oria_s->title ); ?></h3>
								<p class="passrow__when"><?php echo esc_html( Sessions\when( $oria_s ) ); ?></p>
							</div>
							<span class="passrow__status passrow__status--<?php echo esc_attr( (string) $oria_s->status ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) $oria_s->status ) ) ); ?>
							</span>
						</div>

						<p class="passrow__facts">
							<span><?php
								printf(
									/* translators: 1: booked, 2: places offered */
									esc_html__( '%1$d of %2$d places taken', 'oria' ),
									(int) $oria_s->booked_count,
									(int) $oria_s->pass_capacity
								);
							?></span>
						</p>

						<?php if ( $oria_bookings ) : ?>
							<ul class="passrow__who">
								<?php foreach ( $oria_bookings as $oria_bk ) : ?>
									<?php
									/*
									 * First name and a reference, and nothing else.
									 * A studio needs to know who is at the door; it
									 * does not need the rest of somebody's account.
									 */
									$oria_person = get_userdata( (int) $oria_bk->user_id );
									$oria_first  = $oria_person ? ( $oria_person->first_name ?: $oria_person->display_name ) : __( 'A member', 'oria' );
									?>
									<li class="passrow__person">
										<span><?php echo esc_html( $oria_first ); ?> · <code><?php echo esc_html( (string) $oria_bk->booking_reference ); ?></code></span>
										<?php if ( 'confirmed' === (string) $oria_bk->status ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="passrow__mark">
												<input type="hidden" name="action" value="oria_pass_mark">
												<input type="hidden" name="booking_id" value="<?php echo (int) $oria_bk->id; ?>">
												<?php wp_nonce_field( 'oria_pass_session' ); ?>
												<button type="submit" name="mark" value="attended"><?php esc_html_e( 'Came', 'oria' ); ?></button>
												<button type="submit" name="mark" value="no_show"><?php esc_html_e( 'No show', 'oria' ); ?></button>
											</form>
										<?php else : ?>
											<span class="passrow__done"><?php echo esc_html( Booking\label( (string) $oria_bk->status ) ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<p class="passrow__acts">
							<a href="<?php echo esc_url( add_query_arg( 'edit', (int) $oria_s->id, \Oria\Core\MyOria\url( 'pass' ) ) . '#add' ); ?>"><?php esc_html_e( 'Edit', 'oria' ); ?></a>

							<?php foreach ( array( 'active' => __( 'Publish', 'oria' ), 'paused' => __( 'Pause', 'oria' ), 'cancelled' => __( 'Call it off', 'oria' ) ) as $oria_to => $oria_label ) : ?>
								<?php if ( (string) $oria_s->status === $oria_to ) { continue; } ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="passrow__inline">
									<input type="hidden" name="action" value="oria_pass_session_status">
									<input type="hidden" name="session_id" value="<?php echo (int) $oria_s->id; ?>">
									<input type="hidden" name="status" value="<?php echo esc_attr( $oria_to ); ?>">
									<?php wp_nonce_field( 'oria_pass_session' ); ?>
									<button type="submit"<?php echo 'cancelled' === $oria_to ? ' class="passrow__off"' : ''; ?>>
										<?php echo esc_html( $oria_label ); ?>
									</button>
								</form>
							<?php endforeach; ?>
						</p>

						<?php if ( (int) $oria_s->booked_count > 0 ) : ?>
							<p class="passrow__warn">
								<?php esc_html_e( 'Calling this off returns everybody their credits and tells them it is off.', 'oria' ); ?>
							</p>
						<?php endif; ?>
						</div>

						<div class="passrow__stub">
							<b class="passrow__n"><?php echo esc_html( number_format_i18n( (int) $oria_s->credits_required ) ); ?></b>
							<span class="passrow__nlabel"><?php echo esc_html( _n( 'credit', 'credits', (int) $oria_s->credits_required, 'oria' ) ); ?></span>
							<?php if ( (float) $oria_s->provider_payout > 0 ) : ?>
								<span class="passrow__pay"><?php
									printf(
										/* translators: %s: payout */
										esc_html__( 'you get %s each', 'oria' ),
										esc_html( '$' . number_format_i18n( (float) $oria_s->provider_payout, 2 ) )
									);
								?></span>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</section>
		<?php else : ?>
			<p class="myempty"><?php esc_html_e( 'Nothing opened yet. The form below is the whole job — a name, a time, and how many places you can spare.', 'oria' ); ?></p>
		<?php endif; ?>


		<?php /* ---------------------------------------------- the form */ ?>
		<section class="mycard passform" id="add">
			<h2 class="mycard__title">
				<?php echo esc_html( $oria_editing ? __( 'Edit this session', 'oria' ) : __( 'Open a session', 'oria' ) ); ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="passform__form">
				<input type="hidden" name="action" value="oria_pass_session_save">
				<input type="hidden" name="listing_id" value="<?php echo (int) $oria_listing; ?>">
				<input type="hidden" name="session_id" value="<?php echo (int) ( $oria_editing->id ?? 0 ); ?>">
				<?php wp_nonce_field( 'oria_pass_session' ); ?>

				<label class="field"><span class="field__label"><?php esc_html_e( 'What is it?', 'oria' ); ?></span>
					<input class="input" type="text" name="title" required maxlength="120"
						placeholder="<?php esc_attr_e( 'Tuesday Yin', 'oria' ); ?>"
						value="<?php echo esc_attr( (string) ( $oria_editing->title ?? '' ) ); ?>"></label>

				<div class="passform__row3">
					<label class="field"><span class="field__label"><?php esc_html_e( 'Date', 'oria' ); ?></span>
						<input class="input" type="date" name="start_date" required
							value="<?php echo esc_attr( $oria_editing ? (string) mysql2date( 'Y-m-d', (string) $oria_editing->start_at ) : '' ); ?>"></label>
					<label class="field"><span class="field__label"><?php esc_html_e( 'Starts', 'oria' ); ?></span>
						<input class="input" type="time" name="start_time" required
							value="<?php echo esc_attr( $oria_editing ? (string) mysql2date( 'H:i', (string) $oria_editing->start_at ) : '' ); ?>"></label>
					<label class="field"><span class="field__label"><?php esc_html_e( 'Ends (optional)', 'oria' ); ?></span>
						<input class="input" type="time" name="end_time"
							value="<?php echo esc_attr( $oria_editing && $oria_editing->end_at ? (string) mysql2date( 'H:i', (string) $oria_editing->end_at ) : '' ); ?>"></label>
				</div>

				<div class="passform__row3">
					<label class="field"><span class="field__label"><?php esc_html_e( 'Room holds', 'oria' ); ?></span>
						<input class="input" type="number" min="0" name="total_capacity"
							value="<?php echo esc_attr( (string) ( $oria_editing->total_capacity ?? '' ) ); ?>"></label>
					<label class="field"><span class="field__label"><?php esc_html_e( 'Places for the Pass', 'oria' ); ?></span>
						<input class="input" type="number" min="1" name="pass_capacity" required
							value="<?php echo esc_attr( (string) ( $oria_editing->pass_capacity ?? 2 ) ); ?>">
						<span class="field__help"><?php esc_html_e( 'Usually a couple. The rest stay yours to sell.', 'oria' ); ?></span></label>
					<label class="field"><span class="field__label"><?php esc_html_e( 'Credits', 'oria' ); ?></span>
						<input class="input" type="number" min="1" name="credits_required" required
							value="<?php echo esc_attr( (string) ( $oria_editing->credits_required ?? 10 ) ); ?>">
						<span class="field__help"><?php esc_html_e( 'What a member spends. Yoga is around 8, a float around 20.', 'oria' ); ?></span></label>
				</div>

				<div class="passform__row3">
					<label class="field"><span class="field__label"><?php esc_html_e( 'You are paid', 'oria' ); ?></span>
						<input class="input" type="number" min="0" step="0.01" name="provider_payout"
							value="<?php echo esc_attr( (string) ( $oria_editing->provider_payout ?? '' ) ); ?>">
						<span class="field__help"><?php esc_html_e( 'Per person who turns up. Members never see this.', 'oria' ); ?></span></label>
					<label class="field"><span class="field__label"><?php esc_html_e( 'Cancellation cutoff (hours)', 'oria' ); ?></span>
						<input class="input" type="number" min="0" name="cancel_cutoff_hours"
							value="<?php echo esc_attr( (string) ( $oria_editing->cancel_cutoff_hours ?? Settings\get( 'cancel_cutoff_hrs' ) ) ); ?>"></label>
				</div>

				<label class="field"><span class="field__label"><?php esc_html_e( 'Anything they should know (optional)', 'oria' ); ?></span>
					<textarea class="textarea" name="notes" rows="2"
						placeholder="<?php esc_attr_e( 'Bring a towel. Door code 1234.', 'oria' ); ?>"><?php echo esc_textarea( (string) ( $oria_editing->notes ?? '' ) ); ?></textarea></label>

				<p class="passform__acts">
					<button class="btn btn--dark" type="submit" name="save_as" value="publish">
						<?php echo esc_html( $oria_editing ? __( 'Save and publish', 'oria' ) : __( 'Open it to members', 'oria' ) ); ?>
					</button>
					<button class="btn btn--ghost" type="submit" name="save_as" value="draft"><?php esc_html_e( 'Save as draft', 'oria' ); ?></button>
					<?php if ( $oria_editing ) : ?>
						<a class="passform__alt" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'pass' ) ); ?>"><?php esc_html_e( 'Cancel editing', 'oria' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
		</section>
	<?php endif; ?>

</div>
