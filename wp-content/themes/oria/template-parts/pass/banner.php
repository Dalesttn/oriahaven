<?php
/**
 * The Oria Pass banner, wherever it is worth saying.
 *
 * One component rather than a dozen hand-written promos, because the
 * thing it must never do is promise something that is not there yet, and
 * that is a single decision better made once.
 *
 * It reads the real state before it writes a word:
 *
 *   - No studio has opened a place yet? Then it asks for interest and
 *     says the Pass is starting. It does not invite anybody to buy a
 *     membership with nothing to spend it on, whatever the mode is set
 *     to. This is the same rule as never showing fake availability,
 *     applied to the membership rather than to a session.
 *   - Places exist? Then it says how many studios, and invites booking.
 *   - The visitor already holds a Pass, or is a provider already running
 *     sessions? Then there is nothing to sell them and it draws nothing.
 *
 * Args:
 *   audience  'practice' | 'member'  Who it is talking to.
 *   tone      'band' (default) | 'slim' | 'bare'
 *
 * 'bare' drops the card -- background, border and padding -- for when it
 * is dropped inside something that already is one. A card inside a card
 * reads as a mistake however well it is coloured.
 *   where     A short slug for analytics, e.g. 'category', 'dashboard'.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Pass\Route\url' ) ) {
	return;
}

use Oria\Pass\Membership;
use Oria\Pass\Sessions;
use Oria\Pass\Settings;

// Asks for its own stylesheet: a part that draws markup owns its looks.
wp_enqueue_style( 'oria-pass' );

$oria_audience = 'practice' === ( $args['audience'] ?? '' ) ? 'practice' : 'member';
$oria_tone     = in_array( (string) ( $args['tone'] ?? '' ), array( 'slim', 'bare' ), true ) ? (string) $args['tone'] : 'band';
$oria_where    = sanitize_key( (string) ( $args['where'] ?? 'site' ) );

$oria_uid      = get_current_user_id();
$oria_partners = count( Sessions\partners( 50 ) );
$oria_early    = 0 === $oria_partners;

if ( 'member' === $oria_audience ) {
	// Nothing to say to somebody who already has one.
	if ( $oria_uid > 0 && function_exists( '\Oria\Pass\Membership\is_active' ) && Membership\is_active( $oria_uid ) ) {
		return;
	}

	$oria_eyebrow = __( 'Oria Pass', 'oria' );
	$oria_title   = $oria_early
		? __( 'One membership for wellness across Perth. We are building it now.', 'oria' )
		: __( 'One membership, wellness across Perth.', 'oria' );

	$oria_says = $oria_early
		? __( 'Studios are signing up now. Tell us what you would use it for and we will let you know the week it opens — no card, no commitment.', 'oria' )
		: sprintf(
			/* translators: 1: credits a month, 2: price, 3: number of studios */
			_n(
				'%1$s credits a month for %2$s, spent at %3$s Perth studio.',
				'%1$s credits a month for %2$s, spent across %3$s Perth studios.',
				$oria_partners,
				'oria'
			),
			esc_html( (string) Settings\get( 'credits_per_cycle' ) ),
			esc_html( (string) Settings\get( 'price_display' ) ),
			esc_html( number_format_i18n( $oria_partners ) )
		);

	$oria_cta   = $oria_early ? __( 'Register interest', 'oria' ) : __( 'See how it works', 'oria' );
	$oria_href  = \Oria\Pass\Route\url();
	$oria_event = 'oria_pass_banner_member';
} else {
	/*
	 * A provider already offering places does not need selling to, and a
	 * visitor who owns nothing here is not the audience for this one.
	 */
	$oria_listing = function_exists( '\Oria\Core\ListingEditor\listing_for' ) ? \Oria\Core\ListingEditor\listing_for( $oria_uid ) : 0;
	if ( $oria_listing > 0 && Sessions\for_listing( $oria_listing, 1 ) ) {
		return;
	}

	$oria_eyebrow = __( 'For practices', 'oria' );
	$oria_title   = $oria_listing > 0
		? __( 'Fill your quiet classes with Oria Pass.', 'oria' )
		: __( 'Claim your practice, then fill your quiet classes.', 'oria' );

	$oria_says = __( 'Open the places you would rather not leave empty. Members book them with credits, you set what each place is worth to you, and you keep every booking you already have. No fee to join, nothing to install.', 'oria' );

	$oria_cta   = $oria_listing > 0 ? __( 'Open a session', 'oria' ) : __( 'List your practice', 'oria' );
	$oria_href  = $oria_listing > 0
		? ( function_exists( '\Oria\Core\MyOria\url' ) ? add_query_arg( 'tab', 'add', \Oria\Core\MyOria\url( 'pass' ) ) : \Oria\Pass\Route\url() )
		: home_url( '/list-your-practice/' );
	$oria_event = 'oria_pass_banner_practice';
}
?>

<aside class="xbanner xbanner--<?php echo esc_attr( $oria_tone ); ?> xbanner--<?php echo esc_attr( $oria_audience ); ?>"
	data-oria-event="<?php echo esc_attr( $oria_event ); ?>"
	data-oria-where="<?php echo esc_attr( $oria_where ); ?>">

	<div class="xbanner__words">
		<span class="xbanner__eyebrow"><?php echo esc_html( $oria_eyebrow ); ?></span>
		<p class="xbanner__title"><?php echo esc_html( $oria_title ); ?></p>
		<p class="xbanner__says"><?php echo esc_html( $oria_says ); ?></p>
	</div>

	<div class="xbanner__acts">
		<a class="btn btn--dark" href="<?php echo esc_url( $oria_href ); ?>"
			data-oria-event="<?php echo esc_attr( $oria_event . '_click' ); ?>">
			<?php echo esc_html( $oria_cta ); ?>
		</a>

		<?php if ( 'practice' === $oria_audience ) : ?>
			<a class="xbanner__alt" href="<?php echo esc_url( \Oria\Pass\Route\url( 'partners' ) ); ?>">
				<?php esc_html_e( 'How it works for studios', 'oria' ); ?>
			</a>
		<?php elseif ( ! $oria_early ) : ?>
			<span class="xbanner__alt"><?php esc_html_e( 'Cancel any time.', 'oria' ); ?></span>
		<?php endif; ?>
	</div>
</aside>
