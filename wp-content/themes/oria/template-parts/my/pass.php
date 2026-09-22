<?php
/**
 * The Oria Pass card in My Oria.
 *
 * Two states and a third that says nothing at all. A member sees their
 * balance, when it resets and where it went; somebody without a membership
 * sees the offer — but only once there is something to sell, because a
 * dashboard card inviting you to join a thing that cannot be bought is
 * worse than an empty space.
 *
 * The balance is read from the ledger every time rather than cached on the
 * user. It is one indexed sum over a handful of rows, and a number this
 * page is trusted for should not be able to drift.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Pass\Membership\for_user' ) ) {
	return;
}

use Oria\Pass\Credits;
use Oria\Pass\Membership;
use Oria\Pass\Settings;
use Oria\Pass\Stripe;

$oria_uid  = get_current_user_id();
$oria_mem  = Membership\for_user( $oria_uid );
$oria_live = Settings\is_live();

/* No membership, nothing to sell yet: say nothing. */
if ( ! $oria_mem && ! $oria_live ) {
	return;
}

if ( ! $oria_mem ) :
	$oria_buy = Stripe\pay_url( $oria_uid );
	?>
	<section class="mycard mypass mypass--offer" aria-labelledby="myPassTitle">
		<p class="mycard__eyebrow"><?php esc_html_e( 'Oria Pass', 'oria' ); ?></p>
		<h2 class="mycard__title" id="myPassTitle"><?php esc_html_e( 'One membership, wellness across Perth.', 'oria' ); ?></h2>
		<p class="mycard__line">
			<?php
			printf(
				/* translators: 1: credits, 2: price, 3: period */
				esc_html__( '%1$d credits a month for %2$s a %3$s. Spend them across participating studios.', 'oria' ),
				(int) Settings\get( 'credits_per_cycle' ),
				esc_html( (string) Settings\get( 'price_display' ) ),
				esc_html( (string) Settings\get( 'price_period' ) )
			);
			?>
		</p>
		<?php if ( '' !== $oria_buy ) : ?>
			<a class="btn btn--sm btn--dark" href="<?php echo esc_url( $oria_buy ); ?>" data-oria-event="oria_pass_checkout_started">
				<?php esc_html_e( 'Join Oria Pass', 'oria' ); ?>
			</a>
		<?php else : ?>
			<a class="mycard__more" href="<?php echo esc_url( \Oria\Pass\Route\url() ); ?>"><?php esc_html_e( 'Read about Oria Pass', 'oria' ); ?></a>
		<?php endif; ?>
	</section>
	<?php
	return;
endif;

$oria_balance = Credits\balance( $oria_uid );
$oria_allowed = (int) ( $oria_mem->credits_per_cycle ?: Settings\get( 'credits_per_cycle' ) );
$oria_pct     = $oria_allowed > 0 ? max( 0, min( 100, (int) round( $oria_balance / $oria_allowed * 100 ) ) ) : 0;
$oria_renews  = (string) $oria_mem->cycle_ends_at;
$oria_history = Credits\history( $oria_uid, 6 );
?>

<section class="mycard mypass" aria-labelledby="myPassTitle">
	<p class="mycard__eyebrow"><?php esc_html_e( 'Oria Pass', 'oria' ); ?></p>

	<h2 class="mycard__title" id="myPassTitle">
		<?php
		printf(
			/* translators: %d: credits remaining */
			esc_html( _n( '%d credit left', '%d credits left', $oria_balance, 'oria' ) ),
			(int) $oria_balance
		);
		?>
	</h2>

	<?php
	/*
	 * A meter, not a bar chart: it is one number against one allowance, and
	 * the text under it says the same thing for anybody who cannot see the
	 * fill.
	 */
	?>
	<div class="mypass__meter" role="img"
		aria-label="<?php echo esc_attr( sprintf( /* translators: 1: credits left, 2: monthly allowance */ __( '%1$d of %2$d credits remaining', 'oria' ), $oria_balance, $oria_allowed ) ); ?>">
		<span class="mypass__fill" style="width:<?php echo (int) $oria_pct; ?>%"></span>
	</div>

	<p class="mycard__line">
		<?php
		if ( 'past_due' === (string) $oria_mem->status ) {
			esc_html_e( 'We are having trouble with your card. Your credits still work while the bank retries.', 'oria' );
		} elseif ( in_array( (string) $oria_mem->status, array( 'cancelled', 'expired' ), true ) ) {
			esc_html_e( 'Your membership has ended. The credits you have already been given are still yours to use.', 'oria' );
		} elseif ( '' !== $oria_renews ) {
			printf(
				/* translators: 1: allowance, 2: date */
				esc_html__( '%1$d more on %2$s. Unused credits do not carry over.', 'oria' ),
				(int) $oria_allowed,
				esc_html( (string) mysql2date( 'j F', $oria_renews ) )
			);
		}
		?>
	</p>

	<?php if ( $oria_history ) : ?>
		<ul class="mypass__log">
			<?php foreach ( $oria_history as $oria_row ) : ?>
				<li>
					<span class="mypass__what"><?php echo esc_html( (string) $oria_row->description ); ?></span>
					<span class="mypass__n mypass__n--<?php echo (int) $oria_row->credit_change >= 0 ? 'up' : 'down'; ?>">
						<?php echo esc_html( sprintf( '%+d', (int) $oria_row->credit_change ) ); ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<a class="mycard__more" href="<?php echo esc_url( \Oria\Pass\Route\url() ); ?>">
		<?php esc_html_e( 'Find something to book', 'oria' ); ?>
	</a>
</section>
