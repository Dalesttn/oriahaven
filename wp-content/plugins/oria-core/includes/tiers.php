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

/**
 * How many gallery photos each tier may PUBLISH. 0 = unlimited.
 *
 * Four on the free plan. A listing with no photograph of the room is the
 * weakest thing in the directory, and the practice is the only party who
 * can fix that -- so charging for the first photo costs us more than it
 * earns. Four is enough to show the space, the practitioner and a detail.
 *
 * 'unclaimed' must be listed explicitly. gallery_limit() falls back to 0
 * for an unknown tier, and 0 means unlimited, so leaving the free plan out
 * of this list would hand it more photos than Claimed rather than fewer.
 *
 * Published, not stored: a listing that drops back to free keeps every
 * photo it uploaded and simply stops showing the extras, the same rule
 * TEAM_LIMITS follows. Deleting somebody's photographs because a card
 * expired would be the wrong way round.
 */
const GALLERY_LIMITS = array(
	'unclaimed' => 4,
	CLAIMED     => 10,
	FEATURED    => 0,
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
 * Field-level gating for the listing edit screen.
 *
 * The line is: FREE MAKES IT RIGHT, PAID MAKES IT WORK.
 *
 * Anything that decides whether the listing is CORRECT is free — what you
 * offer, when you are open, where to park, what the room has in it. Wrong
 * information is Oria Haven's problem before it is the practice's: a
 * directory nobody can trust is worth nothing, and we do not get to charge
 * a business $29 a month for the privilege of fixing what we got wrong
 * about them. Baolin Acupuncture emailed to say we had them down for Reiki
 * and infrared sauna, neither of which they offer. Under the old gating,
 * claiming their listing for free would not have let them correct it.
 *
 * Anything that gives the practice an ADVANTAGE is paid — a booking link,
 * photographs, offers, their email published, the class timetable, the
 * badge, the analytics, the placement. That is a straight trade and it is
 * easy to explain on a pricing page.
 *
 * The commercial argument runs the same way. An owner who can finish their
 * listing comes back, sees what it is doing, and has a reason to want more
 * of it. An owner who meets a wall of padlocks on their first visit closes
 * the tab, and nobody has ever upgraded from a closed tab.
 *
 * field name => minimum tier ('free' means any approved owner).
 */
const FIELD_TIERS = array(
	// The description is the profile's opening paragraph and the text
	// Google shows under the name. Nothing is more clearly theirs to get
	// right, and it was already ungated -- listed here so it stays that way
	// on purpose rather than by omission.
	'listing_description' => 'free',
	'address'       => 'free',
	'phone'         => 'free',
	'email'         => 'free',
	'website'       => 'free',
	// The conversion path, and the one thing on this list a practice would
	// miss most. Kept paid on purpose: it is the clearest thing $29 buys.
	'booking_url'   => CLAIMED,
	// What you actually do. Free, because a service list we researched and
	// got wrong is worse for us than for them -- see the note above.
	'services'      => 'free',
	'price_from'    => 'free',
	'price_band'    => 'free',
	'format'        => 'free',
	// The two blocks a practice fills in for itself. Nobody can
	// research somebody else's class list or package prices, so
	// these exist only where a paying owner has typed them.
	'classes'       => CLAIMED,
	'packages'      => CLAIMED,
	// Free-text questions and answers: prose, so the same gate as the
	// other prose fields.
	'faq'           => CLAIMED,
	'instagram_url' => CLAIMED,
	'facebook_url'  => CLAIMED,
	'offer_title'   => CLAIMED,
	'offer_text'    => CLAIMED,
	'offer_until'   => CLAIMED,
	// Free, capped at four by GALLERY_LIMITS. The cap is the upgrade, not
	// the permission: a practice can always show its room.
	'gallery'       => 'free',
	'next_session'  => CLAIMED,
	// Who a place suits, when it opens, how to get there and what is in the
	// building. All four are plain facts about the practice, all four are
	// things a reader is annoyed to find wrong, and none of them are worth
	// holding hostage. Opening hours especially: stale hours are the single
	// most common way a directory wastes somebody's trip.
	'good_for'      => 'free',
	'opening_hours' => 'free',
	'transit'       => 'free',
	'parking'       => 'free',
	// Nobody researches amenities — the only source is the business ticking
	// a box about its own premises. That is an argument for asking them, not
	// for charging them; an empty field still shows nothing.
	'amenities'     => 'free',
	// Editable on any plan; how many of them publish is what the tier
	// decides — see TEAM_LIMITS.
	'team'          => 'free',
);

/**
 * What a locked field would actually do for the practice.
 *
 * A padlock and the word "upgrade" tells somebody they are being charged
 * without telling them what for. These lines go on the field itself, so
 * the sell happens where the want is -- at the moment they reached for the
 * thing -- rather than in a banner they scrolled past.
 *
 * Written as the benefit, never as the feature: "so people can book you
 * without ringing", not "booking URL field".
 */
const FIELD_SELLS = array(
	'booking_url'   => 'Let people book you straight from your profile, without ringing first.',
	'offer_title'   => 'Run an offer on your profile and on every card your listing appears in.',
	'offer_text'    => 'Run an offer on your profile and on every card your listing appears in.',
	'offer_until'   => 'Run an offer on your profile and on every card your listing appears in.',
	'instagram_url' => 'Send the people who find you here to your Instagram.',
	'facebook_url'  => 'Send the people who find you here to your Facebook page.',
	'classes'       => 'Publish your timetable so people know what runs and when.',
	'packages'      => 'Publish your packages and passes, so the price question is answered before they ring.',
	'faq'           => 'Answer the questions you get asked on the phone, on the page instead.',
	'next_session'  => 'Show what is on next, so somebody ready today can act today.',
);

/** The benefit line for a locked field, or '' where there is nothing to say. */
function field_sell( string $field_name ): string {
	return (string) ( FIELD_SELLS[ $field_name ] ?? '' );
}

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
	return GALLERY_LIMITS[ tier( $listing_id ) ] ?? GALLERY_LIMITS['unclaimed'];
}

/**
 * The photo allowance, said out loud, for whoever is about to hit it.
 *
 * Names what the next step up actually gives rather than only what the
 * current plan withholds -- the difference between a limit and an offer.
 */
function gallery_note( int $listing_id ): string {
	$limit = gallery_limit( $listing_id );
	if ( 0 === $limit ) {
		return '';
	}

	return CLAIMED === tier( $listing_id )
		? sprintf(
			/* translators: %d: photo limit on the Claimed plan */
			__( 'Claimed publishes %d photos. Featured has no limit.', 'oria' ),
			$limit
		)
		: sprintf(
			/* translators: 1: free photo limit, 2: Claimed photo limit */
			__( 'The free plan publishes %1$d photos. Claimed publishes %2$d, and Featured has no limit.', 'oria' ),
			$limit,
			GALLERY_LIMITS[ CLAIMED ]
		);
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
			// No longer "edit every detail" -- a free owner already can edit
			// the details. What Claimed sells is what the listing then does.
			__( 'A booking link, so people can book you from your profile', 'oria' ),
			// Free listings take enquiries through the form instead, so this
			// is a real difference rather than a line on a chart. Selling it
			// only works if it is written down somewhere they read.
			__( 'Your email address published on your profile', 'oria' ),
			__( 'Verified badge and date', 'oria' ),
			__( 'Up to 10 gallery photos, instead of four', 'oria' ),
			__( 'Special offers on your profile and cards', 'oria' ),
			__( 'Opening hours and social links', 'oria' ),
			__( 'Performance analytics', 'oria' ),
		),
	);
}
