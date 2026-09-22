<?php
/**
 * What each plan actually does, side by side.
 *
 * The plan cards above sell; this answers. A practice deciding between $0
 * and $30 wants to see the line it is buying, not two lists of bullets that
 * have to be held in the head at once.
 *
 * Two columns, not three. There used to be a column for an unclaimed
 * listing, back when claiming cost money and the table had to show what the
 * money bought. Claiming is free now, so "unclaimed" is not a plan anybody
 * chooses -- it is a listing whose owner has not turned up yet -- and
 * putting it in a price comparison invited the reader to weigh an option
 * that does not exist.
 *
 * Every number here is read from Tiers rather than typed: the price, the
 * gallery limits and the practitioner-profile limits. A chart that says four
 * photos while the code allows ten is worse than no chart, and the way that
 * happens is somebody changing a constant and not knowing this file exists.
 *
 * The ticks are hand-mapped, because FEATURES keys ('manage', 'priority')
 * are not sentences a practitioner would recognise. Each row names the gate
 * it reflects so the two can be checked against each other.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Core\Tiers\tier' ) ) {
	return;
}

use Oria\Core\Tiers;

$oria_free_team = Tiers\TEAM_LIMITS[ Tiers\CLAIMED ] ?? 4;
$oria_paid_team = Tiers\TEAM_LIMITS[ Tiers\FEATURED ] ?? 4;
$oria_free_gal  = Tiers\GALLERY_LIMITS[ Tiers\CLAIMED ] ?? 10;
$oria_paid_gal  = Tiers\GALLERY_LIMITS[ Tiers\FEATURED ] ?? 0;
$oria_paid_p    = Tiers\PRICES[ Tiers\FEATURED ] ?? 30;

$oria_yes = '<span class="tiers__yes" aria-hidden="true">&#10003;</span><span class="sr-only">' . esc_html__( 'Included', 'oria' ) . '</span>';
$oria_no  = '<span class="tiers__no" aria-hidden="true">&ndash;</span><span class="sr-only">' . esc_html__( 'Not included', 'oria' ) . '</span>';
$oria_num = static fn( int $n ): string => 0 === $n
	? '<span class="tiers__num">' . esc_html__( 'Unlimited', 'oria' ) . '</span>'
	: '<span class="tiers__num">' . esc_html( number_format_i18n( $n ) ) . '</span>';

/*
 * The two groups are the two halves of the argument. Everything above the
 * second heading is free because it decides whether the listing is CORRECT,
 * and wrong information is our problem before it is the practice's.
 * Everything below it is reach, which is the only thing left worth charging
 * for.
 */
$oria_rows = array(
	array( 'group', __( 'Free, once you claim it', 'oria' ) ),
	array( __( 'Listed in the directory, in search and on category pages', 'oria' ), $oria_yes, $oria_yes ),
	// FEATURES['manage']
	array( __( 'Edit every field yourself, any time', 'oria' ), $oria_yes, $oria_yes ),
	// FIELD_TIERS: address, phone, website, price_from, price_band, format
	array( __( 'Address, phone, website and prices', 'oria' ), $oria_yes, $oria_yes ),
	// FIELD_TIERS: opening_hours, transit, parking, amenities
	array( __( 'Opening hours, parking, transport and amenities', 'oria' ), $oria_yes, $oria_yes ),
	// FIELD_TIERS: services, classes, packages
	array( __( 'Services, timetable and packages', 'oria' ), $oria_yes, $oria_yes ),
	// FIELD_TIERS['booking_url']
	array( __( 'Booking link', 'oria' ), $oria_yes, $oria_yes ),
	// FIELD_TIERS: instagram_url, facebook_url
	array( __( 'Instagram and Facebook links', 'oria' ), $oria_yes, $oria_yes ),
	// FEATURES['offers']
	array( __( 'Special offers on your profile and cards', 'oria' ), $oria_yes, $oria_yes ),
	// shows_email()
	array( __( 'Your email address shown on the profile', 'oria' ), $oria_yes, $oria_yes ),
	// FEATURES['analytics']
	array( __( 'Performance analytics', 'oria' ), $oria_yes, $oria_yes ),
	array( __( 'Verified badge and date', 'oria' ), $oria_yes, $oria_yes ),
	array( __( 'Enquiries through the profile and our matching service', 'oria' ), $oria_yes, $oria_yes ),
	// TEAM_LIMITS
	array( __( 'Practitioner profiles', 'oria' ), $oria_num( (int) $oria_free_team ), $oria_num( (int) $oria_paid_team ) ),
	// GALLERY_LIMITS
	array( __( 'Gallery photos', 'oria' ), $oria_num( (int) $oria_free_gal ), $oria_num( (int) $oria_paid_gal ) ),

	array( 'group', __( 'Added by Featured', 'oria' ) ),
	// FEATURES['events']
	array( __( 'Publish workshops and events', 'oria' ), $oria_no, $oria_yes ),
	// FEATURES['priority']
	array( __( 'Priority placement in the directory', 'oria' ), $oria_no, $oria_yes ),
	array( __( 'Featured on the home and workshops pages', 'oria' ), $oria_no, $oria_yes ),
	array( __( 'Gold Featured badge', 'oria' ), $oria_no, $oria_yes ),
);
?>

<section class="wrap section" id="compare-plans">
	<h2 class="h3" style="margin-bottom:.4rem"><?php esc_html_e( 'Both plans, side by side', 'oria' ); ?></h2>
	<p class="hint" style="max-width:56ch;margin-bottom:1.2rem">
		<?php esc_html_e( 'Claiming your listing is free and always will be, and so is keeping every detail on it right. The paid plan adds reach, not permission.', 'oria' ); ?>
	</p>

	<div class="tiers__scroll">
		<table class="tiers">
			<caption class="sr-only"><?php esc_html_e( 'Features included in the Free and Featured plans', 'oria' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Feature', 'oria' ); ?></th>
					<th scope="col" class="tiers__col--pick">
						<span class="tiers__flag"><?php esc_html_e( 'Where every practice starts', 'oria' ); ?></span>
						<span class="tiers__plan"><?php esc_html_e( 'Free', 'oria' ); ?></span>
						<span class="tiers__price"><?php esc_html_e( '$0', 'oria' ); ?></span>
					</th>
					<th scope="col">
						<span class="tiers__plan"><?php esc_html_e( 'Featured', 'oria' ); ?></span>
						<span class="tiers__price"><?php echo esc_html( '$' . $oria_paid_p . '/mo' ); ?></span>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $oria_rows as $oria_row ) : ?>
					<?php if ( 'group' === $oria_row[0] ) : ?>
						<tr class="tiers__group">
							<th scope="colgroup" colspan="3"><?php echo esc_html( (string) $oria_row[1] ); ?></th>
						</tr>
					<?php else : ?>
						<tr>
							<th scope="row"><?php echo esc_html( (string) $oria_row[0] ); ?></th>
							<td class="tiers__col--pick"><?php echo wp_kses_post( (string) $oria_row[1] ); ?></td>
							<td><?php echo wp_kses_post( (string) $oria_row[2] ); ?></td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<p class="hint" style="max-width:56ch;margin-top:1rem">
		<?php esc_html_e( 'Cancel any time. A listing that drops back to Free keeps everything you added — photos, timetable, practitioner profiles — and simply stops publishing the Featured parts until you start again.', 'oria' ); ?>
	</p>
</section>
