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

// Asks for its own stylesheet: a part that draws markup owns its looks.
wp_enqueue_style( 'oria-pass' );

$oria_listing = (int) ( $args['id'] ?? get_the_ID() );
$oria_rows    = Sessions\upcoming( array( 'listing_id' => $oria_listing, 'limit' => 60 ) );

if ( ! $oria_rows ) {
	return;
}

/*
 * A studio running the same class every week has a month of dates, and a
 * flat list of twelve is a worse answer than a calendar. A studio with
 * one session has no month to look at, so it keeps the list -- a grid
 * with a single dot in it is decoration.
 */
$oria_dates = array();
foreach ( $oria_rows as $oria_r ) {
	$oria_dates[ substr( (string) $oria_r->start_at, 0, 10 ) ] = true;
}
$oria_calendar = count( $oria_dates ) > 1;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_month = sanitize_text_field( (string) ( $_GET['pass_month'] ?? '' ) );
$oria_day   = sanitize_text_field( (string) ( $_GET['pass_date'] ?? '' ) );
// phpcs:enable

if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $oria_day ) ) {
	$oria_day = '';
}
if ( ! preg_match( '/^\d{4}-\d{2}$/', $oria_month ) ) {
	// The month being looked at, else the month the next session falls in.
	$oria_month = '' !== $oria_day ? substr( $oria_day, 0, 7 ) : substr( (string) $oria_rows[0]->start_at, 0, 7 );
}

$oria_days = $oria_calendar ? Sessions\month_days( $oria_listing, $oria_month ) : array();

/*
 * What the list below shows: the chosen day, or the next few. Never both
 * -- a member who picked the 14th is asking about the 14th.
 */
if ( '' !== $oria_day ) {
	$oria_shown = Sessions\on_day( $oria_listing, $oria_day );
} else {
	/*
	 * No day picked, so the next few -- but the next few IN THE MONTH ON
	 * SCREEN. Paging to October and being shown a September class is the
	 * calendar and the list disagreeing about what the visitor asked, and
	 * the list wins by being the thing with a Book button on it.
	 */
	$oria_month_rows = array_values(
		array_filter(
			$oria_rows,
			static fn( $row ): bool => substr( (string) $row->start_at, 0, 7 ) === $oria_month
		)
	);

	$oria_shown = array_slice( $oria_month_rows ?: $oria_rows, 0, $oria_calendar ? 3 : 4 );
}

$oria_here = static function ( array $args ): string {
	$url = remove_query_arg( array( 'pass_ok', 'pass_err', 'pass_ref', 'pass_made', 'pass_date', 'pass_month' ), (string) get_permalink() );

	return add_query_arg( $args, $url ) . '#xpass';
};

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

	<?php if ( $oria_calendar ) : ?>
		<?php
		$oria_first = date_create_immutable( $oria_month . '-01 00:00:00', wp_timezone() );
		$oria_prev  = $oria_first->modify( '-1 month' )->format( 'Y-m' );
		$oria_next  = $oria_first->modify( '+1 month' )->format( 'Y-m' );

		/*
		 * Arrows only where there is something to find. A month button that
		 * leads to an empty grid is a promise the studio did not make.
		 */
		$oria_has_prev = $oria_prev >= wp_date( 'Y-m' ) && (bool) Sessions\month_days( $oria_listing, $oria_prev );
		$oria_has_next = (bool) Sessions\month_days( $oria_listing, $oria_next );

		// Monday-first, which is how a week reads here.
		$oria_lead  = ( (int) $oria_first->format( 'N' ) ) - 1;
		$oria_total = (int) $oria_first->format( 't' );
		$oria_today = wp_date( 'Y-m-d' );
		?>
		<div class="xcal">
			<div class="xcal__head">
				<?php if ( $oria_has_prev ) : ?>
					<a class="xcal__move" href="<?php echo esc_url( $oria_here( array( 'pass_month' => $oria_prev ) ) ); ?>" rel="nofollow">
						<span aria-hidden="true">&larr;</span>
						<span class="screen-reader-text"><?php esc_html_e( 'Previous month', 'oria' ); ?></span>
					</a>
				<?php else : ?>
					<span class="xcal__move xcal__move--off" aria-hidden="true">&larr;</span>
				<?php endif; ?>

				<h3 class="xcal__month"><?php echo esc_html( (string) mysql2date( 'F Y', $oria_month . '-01' ) ); ?></h3>

				<?php if ( $oria_has_next ) : ?>
					<a class="xcal__move" href="<?php echo esc_url( $oria_here( array( 'pass_month' => $oria_next ) ) ); ?>" rel="nofollow">
						<span aria-hidden="true">&rarr;</span>
						<span class="screen-reader-text"><?php esc_html_e( 'Next month', 'oria' ); ?></span>
					</a>
				<?php else : ?>
					<span class="xcal__move xcal__move--off" aria-hidden="true">&rarr;</span>
				<?php endif; ?>
			</div>

			<div class="xcal__grid" role="grid">
				<?php foreach ( array( 'M', 'T', 'W', 'T', 'F', 'S', 'S' ) as $oria_i => $oria_letter ) : ?>
					<span class="xcal__dow" role="columnheader"><?php echo esc_html( $oria_letter ); ?></span>
				<?php endforeach; ?>

				<?php for ( $oria_pad = 0; $oria_pad < $oria_lead; $oria_pad++ ) : ?>
					<span class="xcal__pad"></span>
				<?php endfor; ?>

				<?php for ( $oria_n = 1; $oria_n <= $oria_total; $oria_n++ ) : ?>
					<?php
					$oria_date = sprintf( '%s-%02d', $oria_month, $oria_n );
					$oria_has  = $oria_days[ $oria_date ] ?? null;
					$oria_on   = $oria_date === $oria_day;
					?>
					<?php if ( $oria_has ) : ?>
						<a class="xcal__day xcal__day--open<?php echo $oria_on ? ' is-on' : ''; ?>"
							href="<?php echo esc_url( $oria_here( array( 'pass_date' => $oria_date ) ) ); ?>"
							rel="nofollow"
							<?php echo $oria_on ? ' aria-current="date"' : ''; ?>
							aria-label="<?php
							printf(
								/* translators: 1: date, 2: number of sessions, 3: cheapest credits */
								esc_attr__( '%1$s — %2$d to book, from %3$d credits', 'oria' ),
								esc_attr( (string) mysql2date( 'j F', $oria_date ) ),
								(int) $oria_has['count'],
								(int) $oria_has['from']
							);
							?>">
							<span class="xcal__n"><?php echo (int) $oria_n; ?></span>
							<span class="xcal__dot" aria-hidden="true"></span>
						</a>
					<?php else : ?>
						<span class="xcal__day<?php echo $oria_date === $oria_today ? ' is-today' : ''; ?>">
							<span class="xcal__n"><?php echo (int) $oria_n; ?></span>
						</span>
					<?php endif; ?>
				<?php endfor; ?>
			</div>

			<p class="xcal__note">
				<?php if ( '' !== $oria_day ) : ?>
					<?php echo esc_html( (string) mysql2date( 'l j F', $oria_day ) ); ?>
					&nbsp;<a href="<?php echo esc_url( $oria_here( array( 'pass_month' => $oria_month ) ) ); ?>" rel="nofollow"><?php esc_html_e( 'show the next few instead', 'oria' ); ?></a>
				<?php elseif ( $oria_days ) : ?>
					<?php esc_html_e( 'Dotted days have places. Pick one, or book from the next few below.', 'oria' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Nothing left this month. Try the arrow for the next one.', 'oria' ); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $oria_shown ) : ?>
		<p class="xpass__none">
			<?php esc_html_e( 'Those places have gone. Pick another day on the calendar.', 'oria' ); ?>
		</p>
	<?php endif; ?>

	<ul class="xpass__list">
		<?php foreach ( $oria_shown as $oria_s ) : ?>
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
