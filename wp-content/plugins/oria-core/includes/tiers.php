<?php
/**
 * The plan ladder, in one place. Everything that gates a paid feature asks
 * this file, so "what does $29 buy" is never encoded twice.
 *
 *   free (unclaimed) — the listing exists, built from public information.
 *   claimed  $29/mo  — own it: edit everything, 4 photos, offers, hours,
 *                      socials, analytics, Verified badge.
 *   featured $79/mo  — grow it: everything above plus events, unlimited
 *                      photos, the gold badge, and priority placement on
 *                      the home page, category pages, events page and
 *                      directory sorting.
 */

declare(strict_types=1);

namespace Oria\Core\Tiers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CLAIMED  = 'claimed';
const FEATURED = 'featured';

const PRICES = array(
	CLAIMED  => 29,
	FEATURED => 79,
);

/** How many gallery photos each tier may hold. 0 = unlimited. */
const GALLERY_LIMITS = array(
	CLAIMED  => 4,
	FEATURED => 0,
);

/**
 * How many practitioner profiles a listing may publish.
 *
 * One on the free plan, because a great many listings here are a single
 * person — a breathwork facilitator, a naturopath, a yoga teacher — and for
 * them the practitioner IS the practice. Putting their own name and face on
 * a free listing is the most useful thing that tier does; the reason to pay
 * is the other three.
 *
 * A listing that drops back to free keeps every profile it saved and simply
 * stops publishing the extras. Deleting somebody's details because a card
 * expired would be the wrong way round.
 */
const TEAM_LIMITS = array(
	'unclaimed' => 1,
	CLAIMED     => 4,
	FEATURED    => 4,
);

/** The most profiles any plan can publish — the cap enforced on save. */
const TEAM_MAX = 4;

function team_limit( int $listing_id ): int {
	return TEAM_LIMITS[ tier( $listing_id ) ] ?? 1;
}

/**
 * feature => the minimum tier that unlocks it.
 * Anything not listed here is not a paid surface.
 */
const FEATURES = array(
	'manage'    => CLAIMED,  // edit the listing, gallery, hours, socials
	'offers'    => CLAIMED,
	'analytics' => CLAIMED,
	'events'    => FEATURED,
	'priority'  => FEATURED, // badge + featured placements
);

/**
 * Field-level gating for the listing edit screen. An approved owner on the
 * free plan may keep their location and contact details current; every
 * paid field is visible but locked until they subscribe.
 *
 * field name => minimum tier ('free' means any approved owner).
 */
const FIELD_TIERS = array(
	'address'       => 'free',
	'phone'         => 'free',
	'email'         => 'free',
	'website'       => 'free',
	'booking_url'   => CLAIMED,
	'services'      => CLAIMED,
	'price_from'    => 'free',
	'price_band'    => 'free',
	'format'        => 'free',
	// The two blocks a practice fills in for itself. Nobody can
	// research somebody else's class list or package prices, so
	// these exist only where a paying owner has typed them.
	'classes'       => CLAIMED,
	'packages'      => CLAIMED,
	'instagram_url' => CLAIMED,
	'facebook_url'  => CLAIMED,
	'offer_title'   => CLAIMED,
	'offer_text'    => CLAIMED,
	'offer_until'   => CLAIMED,
	'gallery'       => CLAIMED,
	'next_session'  => CLAIMED,
	'good_for'      => CLAIMED,
	'opening_hours' => CLAIMED,
	'transit'       => CLAIMED,
	'parking'       => CLAIMED,
	// Nobody researches amenities — the only source is the business ticking
	// a box about its own premises, which is exactly what claiming makes
	// possible. Until then the field is present, empty, and shows nothing.
	'amenities'     => CLAIMED,
	// Editable on any plan; how many of them publish is what the tier
	// decides — see TEAM_LIMITS.
	'team'          => 'free',
);

/** Whether this listing's plan lets its owner edit a given field. */
function field_editable( int $listing_id, string $field_name ): bool {
	$needs = FIELD_TIERS[ $field_name ] ?? null;
	if ( null === $needs ) {
		return true; // Not a gated field; other rules may still apply.
	}
	if ( 'free' === $needs ) {
		return true;
	}
	return in_array( tier( $listing_id ), array( CLAIMED, FEATURED ), true );
}

/** The listing's current tier: 'unclaimed', 'claimed' or 'featured'. */
function tier( int $listing_id ): string {
	$status = (string) get_post_meta( $listing_id, 'claim_status', true );
	return in_array( $status, array( CLAIMED, FEATURED ), true ) ? $status : 'unclaimed';
}

/**
 * Whether a visitor may see this listing's email address on the page.
 *
 * A paid listing publishes its address; everything else takes enquiries
 * through the form instead, which does three things a mailto: cannot. The
 * practice learns where the enquiry came from, because the email arrives
 * branded rather than as an anonymous message from a stranger. The
 * enquiry is counted, so a practice can be shown what the listing earned
 * them rather than asked to take it on faith. And an address we collected
 * from a public source stops being republished by us — for the several
 * hundred listings nobody has claimed, that is the more defensible
 * position regardless of what it does for subscriptions.
 *
 * Deliberately reads tier() and not display_status(): the latter reports
 * 'claimed' for free-plan owners so their badge looks right, which is the
 * opposite of the distinction being drawn here.
 */
function shows_email( int $listing_id ): bool {
	return in_array( tier( $listing_id ), array( CLAIMED, FEATURED ), true );
}

/** Whether this listing's plan includes a feature. */
function allows( int $listing_id, string $feature ): bool {
	$needs = FEATURES[ $feature ] ?? null;
	if ( null === $needs ) {
		return false;
	}
	$tier = tier( $listing_id );
	if ( FEATURED === $needs ) {
		return FEATURED === $tier;
	}
	return in_array( $tier, array( CLAIMED, FEATURED ), true );
}

/** Gallery photo cap for this listing; 0 means unlimited. */
function gallery_limit( int $listing_id ): int {
	return GALLERY_LIMITS[ tier( $listing_id ) ] ?? 0;
}

/** Human summaries for emails, notices and the pricing page. */
function summary( string $tier ): array {
	if ( FEATURED === $tier ) {
		return array(
			'label'    => __( 'Featured', 'oria' ),
			'price'    => '$' . PRICES[ FEATURED ],
			'features' => array(
				__( 'Everything in Claimed', 'oria' ),
				__( 'Run workshops & events — photos, booking links, home-page slots', 'oria' ),
				__( 'Unlimited gallery photos', 'oria' ),
				__( 'Gold Featured badge', 'oria' ),
				__( 'Priority placement in the directory and category pages', 'oria' ),
				__( 'Featured spots on the home and workshops pages', 'oria' ),
			),
		);
	}
	return array(
		'label'    => __( 'Claimed', 'oria' ),
		'price'    => '$' . PRICES[ CLAIMED ],
		'features' => array(
			__( 'Edit every detail of your listing', 'oria' ),
			// Free listings take enquiries through the form instead, so this
			// is a real difference rather than a line on a chart. Selling it
			// only works if it is written down somewhere they read.
			__( 'Your email address published on your profile', 'oria' ),
			__( 'Verified badge and date', 'oria' ),
			__( 'Up to 4 gallery photos', 'oria' ),
			__( 'Special offers on your profile and cards', 'oria' ),
			__( 'Opening hours and social links', 'oria' ),
			__( 'Performance analytics', 'oria' ),
		),
	);
}
