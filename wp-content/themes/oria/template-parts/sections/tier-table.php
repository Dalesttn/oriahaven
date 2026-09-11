<?php
/**
 * What each plan actually does, side by side.
 *
 * The three plan cards above sell; this answers. A practice deciding between
 * $0 and $29 wants to see the line it is buying, not three lists of bullets
 * that have to be held in the head at once.
 *
 * Every number here is read from Tiers rather than typed: the prices, the
 * gallery limits and the practitioner-profile limits. A chart that says four
 * photos while the code allows three is worse than no chart, and the way that
 * happens is somebody changing a constant and not knowing this file exists.
 *
 * The ticks are hand-mapped, because FEATURES keys ('manage', 'priority') are
 * not sentences a practitioner would recognise. Each row names the gate it
 * reflects so the two can be checked against each other.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '\Oria\Core\Tiers\tier' ) ) {
	return;
}

use Oria\Core\Tiers;

$oria_free_team = Tiers\TEAM_LIMITS['unclaimed'] ?? 1;
$oria_claim_team = Tiers\TEAM_LIMITS[ Tiers\CLAIMED ] ?? 4;
$oria_feat_team = Tiers\TEAM_LIMITS[ Tiers\FEATURED ] ?? 4;
$oria_claim_gal = Tiers\GALLERY_LIMITS[ Tiers\CLAIMED ] ?? 4;
$oria_feat_gal  = Tiers\GALLERY_LIMITS[ Tiers\FEATURED ] ?? 0;

$oria_yes = '<span class="tiers__yes" aria-hidden="true">&#10003;</span><span class="sr-only">' . esc_html__( 'Included', 'oria' ) . '</span>';
$oria_no  = '<span class="tiers__no" aria-hidden="true">&ndash;</span><span class="sr-only">' . esc_html__( 'Not included', 'oria' ) . '</span>';
$oria_num = static fn( int $n ): string => 0 === $n
	? '<span class="tiers__num">' . esc_html__( 'Unlimited', 'oria' ) . '</span>'
	: '<span class="tiers__num">' . esc_html( number_format_i18n( $n ) ) . '</span>';

/*
 * free / claimed / featured. Ordered so the free column reads first, which is
 * where a practice arriving from a claim email starts.
 */
$oria_rows = array(
	/*
	 * The three groups are the three gates, not a tidy-up: everything above
	 * the first heading is what an unpaid listing already does, and each
	 * heading marks the point where a plan starts paying for itself. A flat
	 * list of nineteen ticks hides exactly that.
	 */
	array( 'group', __( 'In every listing, free', 'oria' ) ),
	array( __( 'Listed in the directory', 'oria' ), $oria_yes, $oria_yes, $oria_yes ),
	array( __( 'Claim it as yours', 'oria' ), $oria_yes, $oria_yes, $oria_yes ),
	array( __( 'Found in search and on category pages', 'oria' ), $oria_yes, $oria_yes, $oria_yes ),
	// FIELD_TIERS: address, phone, website, price_from, price_band, format
	array( __( 'Keep your address, phone, website and prices current', 'oria' ), $oria_yes, $oria_yes, $oria_yes ),
	array( __( 'Enquiries through the profile and our matching service', 'oria' ), $oria_yes, $oria_yes, $oria_yes ),
	// TEAM_LIMITS
	array( __( 'Practitioner profiles', 'oria' ), $oria_num( (int) $oria_free_team ), $oria_num( (int) $oria_claim_team ), $oria_num( (int) $oria_feat_team ) ),

	array( 'group', __( 'Added by Claimed', 'oria' ) ),
	// FEATURES['manage']
	array( __( 'Edit every field yourself', 'oria' ), $oria_no, $oria_yes, $oria_yes ),
	// shows_email()
	array( __( 'Your email address shown on the profile', 'oria' ), $oria_no, $oria_yes, $oria_yes ),
	// GALLERY_LIMITS
	array( __( 'Gallery photos', 'oria' ), $oria_no, $oria_num( (int) $oria_claim_gal ), $oria_num( (int) $oria_feat_gal ) ),
	// FIELD_TIERS['booking_url']
	array( __( 'Booking link', 'oria' ), $oria_no, $oria_yes, $oria_yes ),
	// FIELD_TIERS: classes, packages, services
	array( __( 'Timetable, packages and full service list', 'oria' ), $oria_no, $oria_yes, $oria_yes ),
	// FIELD_TIERS: opening_hours, instagram_url, facebook_url
	array( __( 'Opening hours and social links', 'oria' ), $oria_no, $oria_yes, $oria_yes ),
	// FEATURES['offers']
	array( __( 'Special offers on your profile and cards', 'oria' ), $oria_no, $oria_yes, $oria_yes ),
	// FEATURES['analytics']
	array( __( 'Performance analytics', 'oria' ), $oria_no, $oria_yes, $oria_yes ),
	array( __( 'Verified badge and date', 'oria' ), $oria_no, $oria_yes, $oria_yes ),

	array( 'group', __( 'Added by Featured', 'oria' ) ),
	// FEATURES['events']
	array( __( 'Publish workshops and events', 'oria' ), $oria_no, $oria_no, $oria_yes ),
	// FEATURES['priority']
	array( __( 'Priority placement in the directory', 'oria' ), $oria_no, $oria_no, $oria_yes ),
	array( __( 'Featured on the home and workshops pages', 'oria' ), $oria_no, $oria_no, $oria_yes ),
	array( __( 'Gold Featured badge', 'oria' ), $oria_no, $oria_no, $oria_yes ),
);
?>

<section class="wrap section" id="compare-plans">
	<h2 class="h3" style="margin-bottom:.4rem"><?php esc_html_e( 'Every plan, side by side', 'oria' ); ?></h2>
	<p class="hint" style="max-width:56ch;margin-bottom:1.2rem">
		<?php esc_html_e( 'Claiming your listing is free and always will be. The paid plans add what you can do with it once it is yours.', 'oria' ); ?>
	</p>

	<div class="tiers__scroll">
		<table class="tiers">
			<caption class="sr-only"><?php esc_html_e( 'Features included in the Free, Claimed and Featured plans', 'oria' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Feature', 'oria' ); ?></th>
					<th scope="col">
						<span class="tiers__plan"><?php esc_html_e( 'Free', 'oria' ); ?></span>
						<span class="tiers__price"><?php esc_html_e( '$0', 'oria' ); ?></span>
					</th>
					<th scope="col" class="tiers__col--pick">
						<span class="tiers__flag"><?php esc_html_e( 'Most start here', 'oria' ); ?></span>
						<span class="tiers__plan"><?php esc_html_e( 'Claimed', 'oria' ); ?></span>
						<span class="tiers__price"><?php echo esc_html( '$' . Tiers\PRICES[ Tiers\CLAIMED ] . '/mo' ); ?></span>
					</th>
					<th scope="col">
						<span class="tiers__plan"><?php esc_html_e( 'Featured', 'oria' ); ?></span>
						<span class="tiers__price"><?php echo esc_html( '$' . Tiers\PRICES[ Tiers\FEATURED ] . '/mo' ); ?></span>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $oria_rows as $oria_row ) : ?>
					<?php if ( 'group' === $oria_row[0] ) : ?>
						<tr class="tiers__group">
							<th scope="colgroup" colspan="4"><?php echo esc_html( (string) $oria_row[1] ); ?></th>
						</tr>
					<?php else : ?>
						<tr>
							<th scope="row"><?php echo esc_html( (string) $oria_row[0] ); ?></th>
							<td><?php echo wp_kses_post( (string) $oria_row[1] ); ?></td>
							<td class="tiers__col--pick"><?php echo wp_kses_post( (string) $oria_row[2] ); ?></td>
							<td><?php echo wp_kses_post( (string) $oria_row[3] ); ?></td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<p class="hint" style="max-width:56ch;margin-top:1rem">
		<?php esc_html_e( 'Cancel any time. A listing that drops back to Free keeps everything you added — photos, timetable, practitioner profiles — and simply stops publishing the paid parts until you start again.', 'oria' ); ?>
	</p>
</section>
