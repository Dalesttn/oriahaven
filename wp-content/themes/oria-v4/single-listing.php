<?php
/**
 * v4 listing profile -- the "experience page" as a decision page (child theme).
 *
 * Overrides the parent's single-listing.php. Every data read, guard, form,
 * analytics attribute and template part comes from the parent file; what
 * changes is the order, the wrapping and what is shown first:
 *
 *   1. Decision hero  -- real photos (no photo = a pine panel, never a
 *                        stand-in), category · suburb · Best Of, the H1, the
 *                        first sentence of the listing's own excerpt; then,
 *                        directly under the picture, rating, price and
 *                        today's hours, the four actions and a trust note.
 *   2. Story + rail   -- one DOM order that is also the phone order:
 *                        What it's like -> What you'll find here -> Before
 *                        your first visit -> Location and hours -> Why our
 *                        editors picked it -> Reviews. From 60rem a sticky
 *                        rail of practical facts and actions sits beside it;
 *                        below 60rem the rail is not shown (the hero and the
 *                        Location section already carry everything in it).
 *   3. Similar        -- Similar\listings_for() as picture cards, each with a
 *                        reason built only from services the two share.
 *   4. Claim          -- the parent's claim form, unclaimed listings only.
 *   5. Products       -- one compact row, only when at least two match.
 *   6. Phone bar      -- shown once the hero's own actions scroll away,
 *                        hidden again at the footer and while a form is in use.
 *
 * Nothing on this page is written for the listing: every sentence is a
 * stored field or a label. A section with no data behind it does not render.
 *
 * Styles: assets/css/v4-listing.css. Behaviour: assets/js/v4-listing.js
 * (hours today, show-all services, review panel, phone bar); the page works
 * without it. New classes are .xp- prefixed so nothing collides with the
 * parent's own .xp / .xp__* panel.
 */

declare(strict_types=1);

use function Oria\Theme\arrow;

/* -------------------------------------------------------------------------
 * Photos, resolved before get_header() so the first can be preloaded from
 * wp_head. The listing's own, else its featured image, else its Google
 * Places photos (credited). No placeholder scene at the end of the chain:
 * no photo means the pine panel, never a picture of somewhere else.
 * ----------------------------------------------------------------------- */

$oria_qid         = (int) get_queried_object_id();
$oria_places_attr = array();
$oria_gallery     = array_values( array_filter( array_map(
	static fn( $gid ) => wp_get_attachment_image_url( (int) $gid, 'oria-wide' ),
	\Oria\Theme\rows( 'gallery', array(), $oria_qid )
) ) );
// The plan caps what is published, not what is stored.
$oria_gcap = function_exists( '\Oria\Core\Tiers\gallery_limit' ) ? \Oria\Core\Tiers\gallery_limit( $oria_qid ) : 0;
if ( $oria_gcap > 0 && count( $oria_gallery ) > $oria_gcap ) {
	$oria_gallery = array_slice( $oria_gallery, 0, $oria_gcap );
}
if ( ! $oria_gallery && has_post_thumbnail( $oria_qid ) ) {
	$oria_gallery = array_filter( array( (string) get_the_post_thumbnail_url( $oria_qid, 'oria-wide' ) ) );
}
if ( ! $oria_gallery && function_exists( '\Oria\Core\Places\photos_for' ) ) {
	$oria_places = \Oria\Core\Places\photos_for( $oria_qid );
	if ( ! empty( $oria_places['urls'] ) ) {
		$oria_gallery     = array_values( $oria_places['urls'] );
		$oria_places_attr = (array) $oria_places['attributions'];
	}
}
$oria_gallery = array_values( $oria_gallery );
$oria_photos  = count( $oria_gallery );

// Google photo URLs take a size suffix; ask for the size the slot needs.
$oria_gsz = static function ( string $oria_gu, int $oria_gw ): string {
	return false !== strpos( $oria_gu, 'googleusercontent.com' )
		? (string) preg_replace( '/=[a-z0-9-]+$/i', '=w' . $oria_gw, $oria_gu )
		: $oria_gu;
};

// One srcset/sizes pair, shared by the <img> and its preload so the browser reuses the fetch.
$oria_hero_sizes = 1 === $oria_photos
	? '(max-width: 82.5rem) 100vw, 1320px'
	: '(max-width: 40rem) 100vw, (max-width: 82.5rem) 66vw, 880px';
$oria_srcset     = static fn( string $oria_u ): string => $oria_gsz( $oria_u, 800 ) . ' 800w, ' . $oria_gsz( $oria_u, 1600 ) . ' 1600w';

if ( $oria_photos ) {
	$oria_pre = (string) $oria_gallery[0];
	add_action(
		'wp_head',
		static function () use ( $oria_pre, $oria_gsz, $oria_srcset, $oria_hero_sizes ): void {
			printf(
				'<link rel="preload" as="image" href="%1$s" imagesrcset="%2$s" imagesizes="%3$s" fetchpriority="high">' . "\n",
				esc_url( $oria_gsz( $oria_pre, 1200 ) ),
				esc_attr( $oria_srcset( $oria_pre ) ),
				esc_attr( $oria_hero_sizes )
			);
		},
		2
	);
}

get_header();

$oria_star = '<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>';

// Small line icons for the action buttons. Decorative: every button also has words.
$oria_ico = array(
	'web'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>',
	'tel'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>',
	'dir'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 21s-7-5.3-7-11a7 7 0 0 1 14 0c0 5.7-7 11-7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg>',
	'save' => '<span class="savebtn__on" aria-hidden="true">&#9829;</span><span class="savebtn__off" aria-hidden="true">&#9825;</span>',
);

while ( have_posts() ) :
	the_post();

	/* ---------------------------------------------------------------------
	 * Data. Read exactly as the parent reads it.
	 * ------------------------------------------------------------------- */

	$oria_id     = get_the_ID();
	$oria_status = \Oria\Theme\claim_status( $oria_id );

	$oria_areas  = wp_get_post_terms( $oria_id, 'area' );
	$oria_suburb = null;
	$oria_region = null;
	foreach ( ( is_wp_error( $oria_areas ) ? array() : $oria_areas ) as $oria_t ) {
		if ( $oria_t->parent ) {
			$oria_suburb = $oria_t;
			$oria_region = \Oria\Core\Taxonomies\region_for( $oria_t );
		} elseif ( ! $oria_region ) {
			$oria_region = $oria_t;
		}
	}
	// Ranked by the category plan -- never $practices[0]. See Categories\primary_for().
	$oria_practice = function_exists( '\Oria\Core\Categories\primary_for' )
		? \Oria\Core\Categories\primary_for( $oria_id )
		: ( wp_get_post_terms( $oria_id, 'practice' )[0] ?? null );

	$oria_address  = trim( (string) get_field( 'address', $oria_id ) );
	$oria_phone    = (string) get_field( 'phone', $oria_id );
	$oria_email    = (string) get_field( 'email', $oria_id );
	$oria_slugname = (string) get_post_field( 'post_name', $oria_id );
	$oria_website  = \Oria\Theme\outbound( (string) get_field( 'website', $oria_id ), $oria_slugname );
	$oria_booking  = \Oria\Theme\outbound( (string) get_field( 'booking_url', $oria_id ), $oria_slugname );
	$oria_services = \Oria\Theme\rows( 'services', array(), $oria_id );
	$oria_paid     = function_exists( '\Oria\Core\Ownership\is_paid' ) ? \Oria\Core\Ownership\is_paid( $oria_id ) : false;
	$oria_packages = $oria_paid ? \Oria\Theme\rows( 'packages', array(), $oria_id ) : array();
	$oria_verified = (string) get_field( 'verified_at', $oria_id );
	$oria_words    = \Oria\Theme\words( $oria_id );
	// A beach has no phone, no inbox and no owner: contact and claim blocks go.
	$oria_contactless = '' !== (string) ( $oria_words['contactless'] ?? '' );
	$oria_price_from  = get_field( 'price_from', $oria_id );
	$oria_format      = (string) ( get_field( 'format', $oria_id ) ?: 'in-person' );
	$oria_next        = trim( (string) get_field( 'next_session', $oria_id ) );
	$oria_good_for    = (string) get_field( 'good_for', $oria_id );
	$oria_hours       = \Oria\Theme\hours( $oria_id );
	$oria_transit     = (string) get_field( 'transit', $oria_id );
	$oria_parking     = (string) get_field( 'parking', $oria_id );
	$oria_kind        = (string) get_field( 'kind', $oria_id );
	$oria_reviews     = \Oria\Core\Places\reviews_for( $oria_id );
	$oria_display     = \Oria\Theme\display_status( $oria_id );
	$oria_claimed_by  = (int) get_post_meta( $oria_id, 'claimed_by', true );
	$oria_title       = \Oria\Theme\ptitle( $oria_id );

	$oria_format_label = 'both' === $oria_format
		? __( 'In person & online', 'oria' )
		: ( 'online' === $oria_format ? __( 'Online', 'oria' ) : __( 'In person', 'oria' ) );

	$oria_lcity = function_exists( '\Oria\Core\Cities\current' ) ? \Oria\Core\Cities\current() : null;
	$oria_lname = $oria_lcity ? \Oria\Core\Cities\name( $oria_lcity ) : '';
	$oria_cityw = '' !== $oria_lname ? $oria_lname : __( 'Perth', 'oria' );

	$oria_area_name = $oria_suburb instanceof WP_Term
		? \Oria\Theme\tname( $oria_suburb )
		: ( $oria_region instanceof WP_Term ? \Oria\Theme\tname( $oria_region ) : '' );

	/*
	 * Address. A stored address that is only "Suburb WA 6014" is not a
	 * street address, and saying so is more useful than presenting the
	 * suburb as if it were the door.
	 */
	$oria_street = '' !== $oria_address
		&& ! preg_match( "/^[\\p{L}\\s'.-]+(\\s+(WA|NSW|VIC|QLD|SA|TAS|NT|ACT)(\\s*\\d{4})?)?$/iu", $oria_address );
	// Directions to a suburb alone would drop a pin in its middle; the name lets the map find the door.
	$oria_dir_dest = $oria_street ? $oria_address : trim( $oria_title . ', ' . ( '' !== $oria_address ? $oria_address : $oria_area_name ) , ', ' );
	$oria_dir_url  = ( '' !== $oria_address || '' !== $oria_area_name ) && 'online' !== $oria_format
		? \Oria\Theme\map_directions_url( $oria_dir_dest )
		: '';

	/*
	 * The one-sentence reason to go: the first sentence of the listing's own
	 * excerpt, cut and never reworded. The remainder opens "What it's like"
	 * when the listing has no long description, so nothing is said twice.
	 */
	$oria_excerpt = trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $oria_id ) ) );
	$oria_lede    = $oria_excerpt;
	$oria_ex_rest = '';
	if ( '' !== $oria_excerpt && preg_match( '/^(.{40,}?[.!?])\s+(?=[\p{Lu}"\x{201C}\x{2018}(])/su', $oria_excerpt, $oria_xm ) ) {
		$oria_lede    = trim( $oria_xm[1] );
		$oria_ex_rest = trim( substr( $oria_excerpt, strlen( $oria_xm[0] ) ) );
	}
	$oria_body = trim( (string) apply_filters( 'the_content', get_the_content() ) );

	// Best Of: every guide that picked this listing, and the one badge a card may carry.
	$oria_best_in    = function_exists( '\Oria\Core\BestOf\guides_for_listing' ) ? \Oria\Core\BestOf\guides_for_listing( $oria_id ) : array();
	$oria_best_badge = function_exists( '\Oria\Core\BestOf\card_badge' ) ? \Oria\Core\BestOf\card_badge( $oria_id ) : null;

	// The listing's own terms, as slug lookups: used to keep derived links honest.
	$oria_tslugs = static function ( string $oria_tax ) use ( $oria_id ): array {
		$oria_tt = get_the_terms( $oria_id, $oria_tax );
		return is_array( $oria_tt ) ? array_fill_keys( wp_list_pluck( $oria_tt, 'slug' ), true ) : array();
	};
	$oria_own_cats   = $oria_tslugs( 'practice' );
	$oria_own_facets = $oria_tslugs( 'specialty' ) + $oria_tslugs( 'service' );
	$oria_beginners  = isset( $oria_tslugs( 'audience' )['beginners'] );

	$oria_group  = (string) get_field( 'group_size', $oria_id );
	$oria_groups = array(
		'one-to-one' => __( 'One to one', 'oria' ),
		'small'      => __( 'Small groups (under 12)', 'oria' ),
		'class'      => __( 'Class sized (12+)', 'oria' ),
		'solo'       => __( 'On your own (a room or a machine)', 'oria' ),
	);
	$oria_band_raw = trim( (string) get_field( 'price_band', $oria_id ) );

	// The experience profile, as the parent assembles it.
	$oria_wants  = function_exists( '\Oria\Core\GoodFor\for_listing' ) ? \Oria\Core\GoodFor\for_listing( $oria_id ) : array();
	$oria_expect = function_exists( '\Oria\Theme\expect_chips' ) ? \Oria\Theme\expect_chips( $oria_id ) : array();
	$oria_echips = array();
	foreach ( $oria_expect as $oria_e ) {
		// Price and distance live in the hero and Location; how you book lives in "Before your first visit".
		if ( ! in_array( $oria_e['kind'], array( 'price', 'where', 'book' ), true ) ) {
			$oria_echips[] = $oria_e;
		}
	}
	$oria_likely  = function_exists( '\Oria\Theme\likely_line' ) ? \Oria\Theme\likely_line( $oria_id ) : '';
	$oria_dna_on  = ! function_exists( '\Oria\Core\Dna\profile_enabled' ) || \Oria\Core\Dna\profile_enabled();
	$oria_like_on = ! function_exists( '\Oria\Core\Dna\feels_like_enabled' ) || \Oria\Core\Dna\feels_like_enabled();
	$oria_dna     = $oria_dna_on && function_exists( '\Oria\Core\Dna\bars' ) ? \Oria\Core\Dna\bars( $oria_id ) : array();
	$oria_dnax    = $oria_dna && function_exists( '\Oria\Core\Dna\experience_for' ) ? \Oria\Core\Dna\experience_for( $oria_id ) : null;

	/*
	 * Experience DNA, kept to what can be backed.
	 *
	 * Every bar starts from the Compare registry's score for the KIND of
	 * session (Dna\experience_for) and only three are narrowed by a fact on
	 * this listing (Dna\bars): Social by group_size, Affordability by
	 * price_band, Beginner friendly by the "beginners" audience tag. Where
	 * the listing carries no such fact the registry value is a guess about
	 * this particular room -- that is how a communal sauna house came to be
	 * "small and private" -- so the bar is not shown. The others describe the
	 * kind of session itself and stay, provided the registry scored them.
	 */
	$oria_dattr = $oria_dnax ? (array) ( $oria_dnax['attributes'] ?? array() ) : array();
	$oria_dknow = array(
		'physical' => isset( $oria_dattr['intensity'] ) && '' !== (string) $oria_dattr['intensity'],
		'quiet'    => isset( $oria_dattr['quiet'] ) && '' !== (string) $oria_dattr['quiet'],
		'social'   => isset( $oria_groups[ $oria_group ] ),
		'handson'  => '' !== trim( (string) ( $oria_dattr['touch'] ?? '' ) ),
		'afford'   => '' !== $oria_band_raw,
		'beginner' => $oria_beginners || '' !== trim( (string) ( $oria_dattr['experience'] ?? '' ) ),
	);
	$oria_dna_all = $oria_dna;
	$oria_dna     = array_values( array_filter( $oria_dna, static fn( $oria_b ) => ! empty( $oria_dknow[ $oria_b['key'] ] ) ) );
	// Plain-language names and the two ends of each scale (1 -> 5).
	$oria_dmeta = array(
		'physical' => array( __( 'Physical effort', 'oria' ), __( 'Gentle', 'oria' ), __( 'Demanding', 'oria' ) ),
		'quiet'    => array( __( 'Quietness', 'oria' ), __( 'Music or talk', 'oria' ), __( 'Very quiet', 'oria' ) ),
		'social'   => array( __( 'Social atmosphere', 'oria' ), __( 'Just you', 'oria' ), __( 'Shared, social', 'oria' ) ),
		'handson'  => array( __( 'Hands-on support', 'oria' ), __( 'Hands-off', 'oria' ), __( 'Hands-on', 'oria' ) ),
		'afford'   => array( __( 'Price accessibility', 'oria' ), __( 'Premium', 'oria' ), __( 'Budget friendly', 'oria' ) ),
		'beginner' => array( __( 'Beginner friendliness', 'oria' ), __( 'Some experience helps', 'oria' ), __( 'Easy first visit', 'oria' ) ),
	);
	// The one-line summary reads the Social bar, so it only speaks when that bar is backed.
	$oria_feel = $oria_dnax && $oria_dknow['social'] ? \Oria\Core\Dna\summary( $oria_dna_all ) : '';

	/*
	 * "Feels like": the registry's nearest other kinds of session, kept only
	 * when this listing itself is filed under that kind (its category, or a
	 * specialty/service tag). A neighbour in the scoring space that the
	 * listing does not offer -- breathwork beside a sauna house -- is exactly
	 * the loose relation the brief asks us not to publish.
	 */
	$oria_like = array();
	if ( $oria_dnax && $oria_like_on && function_exists( '\Oria\Core\Dna\top_level' ) && function_exists( '\Oria\Core\Compare\experience_url' ) ) {
		$oria_keys = array();
		foreach ( \Oria\Core\Dna\top_level() as $oria_te ) {
			$oria_keys[ \Oria\Core\Compare\experience_url( $oria_te ) ] = \Oria\Core\Dna\key_of( $oria_te );
		}
		foreach ( \Oria\Core\Dna\feels_like( $oria_dnax, 12 ) as $oria_l ) {
			list( $oria_lk, $oria_ls ) = $oria_keys[ $oria_l['url'] ] ?? array( '', '' );
			$oria_mine = 'category' === $oria_lk ? isset( $oria_own_cats[ $oria_ls ] ) : ( 'facet' === $oria_lk && isset( $oria_own_facets[ $oria_ls ] ) );
			if ( '' !== $oria_ls && $oria_mine ) {
				$oria_like[] = $oria_l;
			}
		}
		$oria_like = array_slice( $oria_like, 0, 3 );
	}

	// Rating: our own reviews first, else Google's, labelled and linked as Google's.
	$oria_rate = \Oria\Theme\effective_rating( $oria_id );
	$oria_grat = \Oria\Core\Places\rating_for( $oria_id );

	// Price: an exact "from" figure, else the band in words.
	$oria_price_num  = (int) $oria_price_from;
	$oria_band_label = '';
	if ( $oria_price_num <= 0 && '' !== $oria_band_raw ) {
		$oria_band_label = 'Free' === $oria_band_raw
			? __( 'Free', 'oria' )
			: ( function_exists( '\Oria\Core\Answer\band_label' ) ? \Oria\Core\Answer\band_label( $oria_band_raw ) : '' );
	}
	$oria_price_txt = $oria_price_num > 0
		/* translators: %d: lowest published price */
		? sprintf( __( 'From $%d a session', 'oria' ), $oria_price_num )
		: ( '' === $oria_band_label ? '' : ( 'Free' === $oria_band_raw ? $oria_band_label : sprintf( /* translators: %s: price band such as $25–60 */ __( 'Typically %s', 'oria' ), $oria_band_label ) ) );

	// Special offer (paid; hides itself when expired or unclaimed).
	$oria_offer = \Oria\Theme\active_offer( $oria_id );

	/*
	 * Hours: the owner's own rows win; else Google's week. Today's line is
	 * rendered from the server's clock and then corrected by v4-listing.js
	 * from the same data (the page can be served from a cache the next day),
	 * which also works out "open now" from Google's structured periods.
	 */
	$oria_hbits = array();
	foreach ( $oria_hours as $oria_hr ) {
		$oria_hline = trim( trim( (string) ( $oria_hr['days'] ?? '' ) ) . ' ' . trim( (string) ( $oria_hr['hours'] ?? '' ) ) );
		if ( '' !== $oria_hline ) {
			$oria_hbits[] = $oria_hline;
		}
	}
	$oria_wk     = ! $oria_hbits && ! \Oria\Theme\hours_hidden( $oria_id ) && function_exists( '\Oria\Core\Places\hours_for' ) ? \Oria\Core\Places\hours_for( $oria_id ) : array();
	$oria_prec   = function_exists( '\Oria\Core\Places\data_for' ) ? \Oria\Core\Places\data_for( $oria_id, false ) : null;
	$oria_gts    = $oria_prec ? (int) ( $oria_prec['ts'] ?? 0 ) : 0;
	$oria_todayw = (string) wp_date( 'l' );
	$oria_today  = '';
	foreach ( $oria_wk as $oria_ghl ) {
		if ( 0 === stripos( $oria_ghl, $oria_todayw ) ) {
			$oria_today = trim( (string) preg_replace( '/^[^:]+:\s*/u', '', $oria_ghl ) );
			break;
		}
	}
	$oria_hours_json = $oria_wk
		? (string) wp_json_encode( array(
			'tz'      => wp_timezone_string(),
			'week'    => array_values( $oria_wk ),
			'periods' => $oria_prec ? array_values( (array) ( $oria_prec['periods'] ?? array() ) ) : array(),
		) )
		: '';

	$oria_show_email = $oria_email && ( ! function_exists( '\Oria\Core\Tiers\shows_email' ) || \Oria\Core\Tiers\shows_email( $oria_id ) );
	$oria_can_enq    = ! $oria_contactless && $oria_email && function_exists( '\Oria\Core\Leads\eligible' ) && \Oria\Core\Leads\eligible( $oria_id );
	$oria_owns_this  = is_user_logged_in() && $oria_claimed_by === get_current_user_id();
	$oria_tel        = $oria_phone && ! $oria_contactless ? (string) preg_replace( '/[^0-9+]/', '', $oria_phone ) : '';
	$oria_primary    = $oria_booking
		? array( 'url' => $oria_booking, 'track' => 'book', 'label' => __( 'Book a session', 'oria' ), 'short' => __( 'Book', 'oria' ) )
		: ( $oria_website ? array( 'url' => $oria_website, 'track' => 'web', 'label' => __( 'Check sessions and availability', 'oria' ), 'short' => __( 'Website', 'oria' ) ) : null );

	/* First-visit facts: only fields a listing actually carries. */
	$oria_first = array();
	$oria_bring = trim( (string) get_field( 'what_to_bring', $oria_id ) );
	$oria_mins  = (int) get_field( 'duration_min', $oria_id );
	$oria_kinds = array(
		'practice' => __( 'Book a practitioner', 'oria' ),
		'place'    => __( 'Book a room or a slot', 'oria' ),
		'spot'     => __( 'Turn up, nothing to book', 'oria' ),
	);
	if ( '' !== $oria_bring ) {
		$oria_first[] = array( __( 'What to bring', 'oria' ), $oria_bring );
	}
	if ( $oria_mins > 0 ) {
		/* translators: %d: minutes */
		$oria_first[] = array( __( 'Typical session', 'oria' ), sprintf( _n( '%d minute', '%d minutes', $oria_mins, 'oria' ), $oria_mins ) );
	}
	if ( isset( $oria_groups[ $oria_group ] ) ) {
		$oria_first[] = array( __( 'Group size', 'oria' ), $oria_groups[ $oria_group ] );
	}
	if ( isset( $oria_kinds[ $oria_kind ] ) ) {
		$oria_first[] = array( __( 'Booking', 'oria' ), $oria_kinds[ $oria_kind ] );
	}
	if ( '' !== $oria_next ) {
		$oria_first[] = array( __( 'Next session', 'oria' ), $oria_next );
	}
	$oria_amenities = function_exists( '\Oria\Core\Amenities\for_listing' ) ? \Oria\Core\Amenities\for_listing( $oria_id ) : array();

	// Services heading: "Classes" where the listing runs a class timetable or a class-led category.
	$oria_week       = function_exists( '\Oria\Core\Classes\timetable_for' ) ? \Oria\Core\Classes\timetable_for( $oria_id ) : array();
	$oria_class_cats = array( 'yoga' ); // Fitness mixes classes with hikes and PTs; "What you'll find here" suits those.
	$oria_is_classes = $oria_week || ( $oria_practice instanceof WP_Term && in_array( $oria_practice->slug, $oria_class_cats, true ) );

	$oria_sec = 0; // Heading ids, so every section can be aria-labelledby.

	/* Small builders for the action buttons, so hero, rail and bar stay in step. */
	$oria_rating_html = '';
	if ( $oria_rate['rating'] > 0 ) {
		$oria_rnum = number_format_i18n( (float) $oria_rate['rating'], 1 );
		if ( 'google' === $oria_rate['source'] ) {
			$oria_rcnt = $oria_rate['count'] > 0
				/* translators: %s: number of Google reviews */
				? sprintf( _n( '%s Google review', '%s Google reviews', (int) $oria_rate['count'], 'oria' ), number_format_i18n( (int) $oria_rate['count'] ) )
				: __( 'Rating on Google', 'oria' );
			$oria_rating_html = '<a class="xp-fact__link" href="' . esc_url( ! empty( $oria_grat['uri'] ) ? $oria_grat['uri'] : '#reviews' ) . '"' . ( ! empty( $oria_grat['uri'] ) ? ' rel="nofollow noopener" target="_blank"' : '' ) . '>'
				. $oria_star . ' <b>' . esc_html( $oria_rnum ) . '</b> <span class="xp-fact__sub">' . esc_html( $oria_rcnt ) . '</span>'
				. ( ! empty( $oria_grat['uri'] ) ? '<span class="xp-vh"> ' . esc_html__( '(opens Google)', 'oria' ) . '</span>' : '' ) . '</a>';
		} else {
			$oria_rcnt = $oria_rate['count'] > 0
				/* translators: %s: number of reviews */
				? sprintf( _n( '%s Oria Haven review', '%s Oria Haven reviews', (int) $oria_rate['count'], 'oria' ), number_format_i18n( (int) $oria_rate['count'] ) )
				: __( 'Oria Haven reviews', 'oria' );
			$oria_rating_html = '<a class="xp-fact__link" href="#reviews">' . $oria_star . ' <b>' . esc_html( $oria_rnum ) . '</b> <span class="xp-fact__sub">' . esc_html( $oria_rcnt ) . '</span></a>';
		}
	}
	?>

<div class="xp-page">

	<div class="wrap xp-crumbs">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Explore', 'oria' ); ?></a>
			<?php if ( '' !== $oria_lname && function_exists( '\Oria\Core\Explore\base_url' ) ) : ?>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( \Oria\Core\Explore\base_url( $oria_lcity ) ); ?>"><?php echo esc_html( $oria_lname ); ?></a>
			<?php endif; ?>
			<?php if ( $oria_practice ) : ?>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( (string) get_term_link( $oria_practice ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_practice ) ); ?></a>
			<?php endif; ?>
			<?php if ( $oria_suburb ) : ?>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( (string) get_term_link( $oria_suburb ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_suburb ) ); ?></a>
			<?php endif; ?>
			<span aria-hidden="true">/</span><span aria-current="page"><?php the_title(); ?></span>
		</nav>
	</div>

	<!-- 1. Decision hero -->
	<section class="xp-hero" aria-labelledby="xp-title" data-xp-hero>
		<?php
		$oria_lightbox = $oria_photos >= 2;
		$oria_panel_cl = 'xp-hero__panel xp-hero__panel--n' . min( 3, $oria_photos );
		if ( $oria_lightbox ) {
			// .gallery[data-lightbox] is the hook app.js's lightbox looks for.
			$oria_panel_cl .= ' gallery';
		}
		// Places photo URIs are short-lived; one that expires simply drops out.
		$oria_fb = "this.closest('.xp-hero__shot').hidden=true";
		?>
		<div class="<?php echo esc_attr( $oria_panel_cl ); ?>"<?php echo $oria_lightbox ? ' data-lightbox' : ''; ?>>
			<?php if ( $oria_photos ) : ?>
				<div class="xp-hero__media">
					<?php foreach ( array_slice( $oria_gallery, 0, 3 ) as $oria_pi => $oria_pu ) : ?>
						<?php
						$oria_palt = 0 === $oria_pi
							? $oria_title
							/* translators: 1: listing name, 2: photo number */
							: sprintf( __( '%1$s, photo %2$d', 'oria' ), $oria_title, $oria_pi + 1 );
						/*
						 * First photo: eager, high priority, preloaded from wp_head.
						 * The others: lazy (and never fetched on a phone, where
						 * their slot is display:none). width/height give the
						 * browser the ratio; the grid cell sets the real size.
						 */
						$oria_img = sprintf(
							'<img src="%1$s" srcset="%2$s" sizes="%3$s" alt="%4$s" width="1200" height="800"%5$s onerror="%6$s">',
							esc_url( $oria_gsz( $oria_pu, 0 === $oria_pi ? 1200 : 800 ) ),
							esc_attr( $oria_srcset( $oria_pu ) ),
							esc_attr( 0 === $oria_pi ? $oria_hero_sizes : '(max-width: 40rem) 1px, 33vw' ),
							esc_attr( $oria_palt ),
							0 === $oria_pi ? ' fetchpriority="high" decoding="async"' : ' loading="lazy" decoding="async"',
							esc_attr( $oria_fb )
						);
						?>
						<?php if ( $oria_lightbox ) : ?>
							<button type="button" class="xp-hero__shot xp-hero__shot--<?php echo (int) $oria_pi; ?>" data-lb="<?php echo (int) $oria_pi; ?>"
								aria-label="<?php echo esc_attr( sprintf( /* translators: 1: photo number, 2: photo count, 3: listing name */ __( 'Open photo %1$d of %2$d of %3$s', 'oria' ), $oria_pi + 1, $oria_photos, $oria_title ) ); ?>">
								<?php echo $oria_img; // phpcs:ignore WordPress.Security.EscapeOutput -- every attribute escaped above. ?>
							</button>
						<?php else : ?>
							<div class="xp-hero__shot xp-hero__shot--0"><?php echo $oria_img; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<div class="xp-hero__shade" aria-hidden="true"></div>
			<?php endif; ?>

			<?php if ( $oria_best_badge ) : ?>
				<div class="xp-hero__award">
					<?php
					echo \Oria\Core\BestOf\badge_html(
						$oria_best_badge['label'],
						$oria_best_badge['url'],
						'',
						(string) ( $oria_best_badge['year'] ?? '' )
					); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in badge_html().
					?>
				</div>
			<?php endif; ?>

			<div class="xp-hero__text">
				<?php
				$oria_eyebrow = array();
				if ( $oria_practice instanceof WP_Term ) {
					$oria_eyebrow[] = '<a href="' . esc_url( (string) get_term_link( $oria_practice ) ) . '">' . esc_html( \Oria\Theme\tname( $oria_practice ) ) . '</a>';
				}
				if ( '' !== $oria_area_name ) {
					$oria_eyebrow[] = esc_html( $oria_area_name );
				}
				/*
				 * The award is NOT in this row. It is a two-line badge and
				 * these are one-line facts, so inline it made the row tower
				 * over itself -- and an award between "Yoga" and "West Perth"
				 * reads as a third category. It sits on the photograph
				 * instead, top left, which is the placement the brief wanted
				 * and the corner the photos button leaves free.
				 */
				?>
				<?php if ( $oria_eyebrow ) : ?>
					<p class="xp-hero__eyebrow"><?php echo implode( ' <span class="xp-hero__sep" aria-hidden="true">&middot;</span> ', $oria_eyebrow ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?></p>
				<?php endif; ?>

				<h1 class="h1 xp-hero__title" id="xp-title"><?php the_title(); ?></h1>

				<?php if ( '' !== $oria_lede ) : ?>
					<p class="xp-hero__lede"><?php echo esc_html( $oria_lede ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $oria_lightbox ) : ?>
				<button type="button" class="xp-hero__all" data-lb="0">
					<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2.5" y="4" width="15" height="12" rx="2"/><circle cx="7.5" cy="8.5" r="1.4"/><path d="m3 15 4.5-4.5 3 3 2.5-2.5 4 4"/></svg>
					<?php
					/* translators: %d: number of photos */
					echo esc_html( sprintf( _n( 'View %d photo', 'View all %d photos', $oria_photos, 'oria' ), $oria_photos ) );
					?>
				</button>
				<?php $oria_lb = array_map( static fn( string $oria_gu ): string => $oria_gsz( $oria_gu, 1600 ), $oria_gallery ); ?>
				<script type="application/json" data-lightbox-set><?php echo wp_json_encode( array_values( $oria_lb ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
			<?php endif; ?>
		</div>

		<?php
		/*
		 * The decision strip: rating, price and today's hours, then the
		 * actions, straight under the picture at every width. Every value is
		 * a stored field or Google's own figure, labelled as Google's.
		 */
		$oria_hfacts = array();
		if ( $oria_offer ) {
			$oria_hfacts[] = array( 'offer', '<span class="xp-decide__k">' . esc_html__( 'Offer', 'oria' ) . '</span> ' . esc_html( $oria_offer['title'] ) );
		}
		if ( '' !== $oria_rating_html ) {
			$oria_hfacts[] = array( 'rating', $oria_rating_html );
		}
		if ( '' !== $oria_price_txt ) {
			$oria_hfacts[] = array( 'price', esc_html( $oria_price_txt ) );
		}
		if ( '' !== $oria_today ) {
			$oria_hfacts[] = array( 'hours', '<span data-xp-today>' . esc_html( sprintf( /* translators: %s: today's hours as Google words them */ __( 'Today %s', 'oria' ), $oria_today ) ) . '</span>' );
		} elseif ( $oria_hbits ) {
			$oria_hfacts[] = array( 'hours', '<a class="xp-fact__link" href="#getting-there">' . esc_html__( 'Opening hours', 'oria' ) . '</a>' );
		}
		if ( 'online' === $oria_format || 'both' === $oria_format ) {
			$oria_hfacts[] = array( 'format', esc_html( $oria_format_label ) );
		}
		?>
		<div class="xp-decide" data-xp-decide>
			<?php if ( $oria_hfacts ) : ?>
				<ul class="xp-decide__facts" aria-label="<?php esc_attr_e( 'At a glance', 'oria' ); ?>">
					<?php foreach ( $oria_hfacts as $oria_hf ) : ?>
						<li class="xp-decide__fact xp-decide__fact--<?php echo esc_attr( $oria_hf[0] ); ?>"><?php echo $oria_hf[1]; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped as built. ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div class="xp-decide__acts">
				<?php if ( $oria_primary ) : ?>
					<a class="btn btn--dark xp-tap xp-decide__main" href="<?php echo esc_url( $oria_primary['url'] ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="<?php echo esc_attr( $oria_primary['track'] ); ?>" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo esc_html( $oria_primary['label'] ); ?><span class="xp-vh"> <?php esc_html_e( '(opens their site)', 'oria' ); ?></span><?php echo arrow(); // phpcs:ignore ?></a>
				<?php endif; ?>
				<?php if ( $oria_tel ) : ?>
					<a class="btn btn--ghost xp-tap xp-decide__btn" href="tel:<?php echo esc_attr( $oria_tel ); ?>" data-oria-track="tel" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo $oria_ico['tel']; // phpcs:ignore ?><span><?php esc_html_e( 'Call', 'oria' ); ?></span><span class="xp-vh"> <?php echo esc_html( $oria_phone ); ?></span></a>
				<?php endif; ?>
				<?php if ( $oria_dir_url ) : ?>
					<a class="btn btn--ghost xp-tap xp-decide__btn<?php echo $oria_primary ? '' : ' xp-decide__main'; ?>" href="<?php echo esc_url( $oria_dir_url ); ?>" rel="noopener" target="_blank" data-oria-track="dir" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo $oria_ico['dir']; // phpcs:ignore ?><span><?php esc_html_e( 'Directions', 'oria' ); ?></span><span class="xp-vh"> <?php echo esc_html( sprintf( /* translators: %s: listing name */ __( 'to %s (opens Google Maps)', 'oria' ), $oria_title ) ); ?></span></a>
				<?php endif; ?>
				<?php // Rendered as the unsaved state and corrected by app.js on load: what this browser has saved lives on the device. ?>
				<button class="btn btn--ghost xp-tap xp-decide__btn xp-decide__save savebtn" type="button"
					data-save="<?php echo esc_attr( $oria_slugname ); ?>"
					data-save-name="<?php echo esc_attr( $oria_title ); ?>"
					aria-pressed="false">
					<?php echo $oria_ico['save']; // phpcs:ignore ?><span class="savebtn__label"><?php esc_html_e( 'Save', 'oria' ); ?></span><span class="xp-vh"> <?php echo esc_html( $oria_title ); ?></span>
				</button>
			</div>

			<p class="xp-decide__trust">
				<?php if ( 'featured' === $oria_display ) : ?>
					<span class="xp-decide__tag"><?php esc_html_e( 'Featured listing', 'oria' ); ?></span>
				<?php endif; ?>
				<?php
				if ( 'unclaimed' !== $oria_status ) {
					echo esc_html(
						$oria_verified
							/* translators: %s: date */
							? sprintf( __( 'Managed by the practice. Details confirmed by the owner on %s.', 'oria' ), mysql2date( 'j F Y', $oria_verified ) )
							: __( 'Managed by the practice.', 'oria' )
					);
				} else {
					esc_html_e( 'Information independently sourced and hand-checked by Oria Haven.', 'oria' );
				}
				?>
			</p>
		</div>

		<?php if ( $oria_places_attr ) : // Google's terms require crediting photo contributors. ?>
			<p class="xp-hero__credit">
				<?php esc_html_e( 'Photos via Google', 'oria' ); ?> &mdash;
				<?php
				$oria_links = array();
				foreach ( $oria_places_attr as $oria_a ) {
					$oria_links[] = ! empty( $oria_a['uri'] )
						? '<a href="' . esc_url( $oria_a['uri'] ) . '" rel="nofollow noopener" target="_blank">' . esc_html( (string) $oria_a['name'] ) . '</a>'
						: esc_html( (string) ( $oria_a['name'] ?? '' ) );
				}
				echo implode( ', ', $oria_links ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</p>
		<?php endif; ?>
	</section>

	<?php if ( '' !== $oria_hours_json ) : ?>
		<script type="application/json" id="xp-hours-data"><?php echo $oria_hours_json; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_json_encode. ?></script>
	<?php endif; ?>

	<!-- 2. Story (left) and the action rail (right) -->
	<div class="wrap xp-body">
		<div class="xp-story">

			<?php
			// Straight off the one-click link in an invitation email.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $oria_owns_this && isset( $_GET['oria_claimed'] ) ) :
				?>
				<div class="claimprompt xp-owner">
					<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'This listing is yours now.', 'oria' ); ?></b>
					<p style="font-size:.875rem;color:var(--text-soft)">
						<?php esc_html_e( 'We\'ve emailed you a link to set a password. After that you can keep your address, contact details, prices and format up to date whenever you like.', 'oria' ); ?>
					</p>
					<p style="margin-top:1rem">
						<a class="btn btn--dark btn--sm" href="<?php echo esc_url( get_edit_post_link( $oria_id ) ?: admin_url() ); ?>"><?php esc_html_e( 'Edit my listing', 'oria' ); ?></a>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $oria_owns_this ) : ?>
				<?php get_template_part( 'template-parts/share-box', null, array( 'id' => $oria_id, 'owner' => true, 'share_label' => $oria_words['share'] ) ); ?>
			<?php endif; ?>

			<?php
			/* --- What it's like -------------------------------------------
			 * The listing's own description, then the experience profile:
			 * good for, the feel, Experience DNA, why it might suit you, and
			 * good_for. Every piece is conditional on its own field. */
			$oria_about_html = '';
			if ( '' !== $oria_body ) {
				$oria_about_html = wp_kses_post( $oria_body );
			} elseif ( '' !== $oria_ex_rest ) {
				$oria_about_html = '<p>' . esc_html( $oria_ex_rest ) . '</p>';
			}
			$oria_has_about = '' !== $oria_about_html || $oria_wants || $oria_echips || '' !== $oria_feel || $oria_like || $oria_dna || '' !== $oria_likely || '' !== trim( $oria_good_for );
			?>
			<?php if ( $oria_has_about ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" id="about" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'What it’s like', 'oria' ); ?></h2>

					<?php if ( '' !== $oria_about_html ) : ?>
						<div class="prose xp-prose"><?php echo $oria_about_html; // phpcs:ignore WordPress.Security.EscapeOutput -- kses'd / escaped above. ?></div>
					<?php endif; ?>

					<?php if ( $oria_echips ) : ?>
						<ul class="xp-tags" aria-label="<?php esc_attr_e( 'What to expect', 'oria' ); ?>">
							<?php foreach ( $oria_echips as $oria_e ) : ?>
								<li class="xp-tag xp-tag--<?php echo esc_attr( $oria_e['kind'] ); ?>"><?php echo esc_html( $oria_e['label'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( $oria_wants || '' !== $oria_feel || $oria_like || $oria_dna || '' !== $oria_likely ) : ?>
						<div class="xp xp-profile">
							<?php if ( $oria_wants ) : ?>
								<div class="xp__b">
									<span class="micro rowlabel"><?php esc_html_e( 'Good for', 'oria' ); ?></span>
									<div class="profile__wants">
										<?php foreach ( $oria_wants as $oria_w ) : ?>
											<span class="pill pill--gf" style="--gf:<?php echo esc_attr( $oria_w['color'] ); ?>"><?php echo esc_html( $oria_w['label'] ); ?></span>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

							<?php if ( '' !== $oria_feel || $oria_like ) : ?>
								<div class="xp__b xp__b--rule">
									<span class="micro rowlabel"><?php esc_html_e( 'The experience', 'oria' ); ?></span>
									<?php if ( '' !== $oria_feel ) : ?>
										<p class="xp__statement"><?php echo esc_html( $oria_feel ); ?></p>
									<?php endif; ?>
									<?php if ( $oria_like ) : ?>
										<p class="xp__like">
											<span class="xp__like-k"><?php esc_html_e( 'Feels like', 'oria' ); ?></span>
											<?php foreach ( $oria_like as $oria_n => $oria_l ) : ?>
												<a href="<?php echo esc_url( $oria_l['url'] ); ?>"><?php echo esc_html( $oria_l['label'] ); ?></a><?php echo $oria_n < count( $oria_like ) - 1 ? '<span class="xp__dot" aria-hidden="true">·</span>' : ''; ?>
											<?php endforeach; ?>
										</p>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<?php if ( $oria_dna ) : ?>
								<div class="xp__b xp__b--rule xp-dna-block">
									<span class="xp-dna__mark" aria-hidden="true"></span>
									<h3 class="micro rowlabel xp-dna__head"><?php esc_html_e( 'Experience DNA', 'oria' ); ?></h3>
									<p class="xp__lede">
										<?php
										echo esc_html( sprintf(
											/* translators: %s: the kind of session, e.g. Ice bath & contrast */
											__( 'A quick feel for the room, based on how %s sessions usually run.', 'oria' ),
											(string) ( $oria_dnax['label'] ?? '' )
										) );
										?>
									</p>
									<?php
									/*
									 * The strand: the same scores as the bars below, drawn as one
									 * smooth line -- a point per bar, spaced evenly, higher for a
									 * higher score -- in each bar's own colour. The faint twin
									 * mirrored about the middle and the rungs between them are
									 * decoration that makes it read as a helix; they carry no
									 * data. Hidden from assistive tech: the bars say it all.
									 */
									$oria_hx_col = array(
										'physical' => '#B4561F',
										'quiet'    => '#3E6E8E',
										'social'   => '#C9A24B',
										'handson'  => '#4F7A64',
										'afford'   => '#8A5A83',
										'beginner' => '#2E8B84',
									);
									$oria_hx_short = array(
										'physical' => __( 'Effort', 'oria' ),
										'quiet'    => __( 'Quiet', 'oria' ),
										'social'   => __( 'Social', 'oria' ),
										'handson'  => __( 'Hands-on', 'oria' ),
										'afford'   => __( 'Price', 'oria' ),
										'beginner' => __( 'Beginners', 'oria' ),
									);
									$oria_hx_n = count( $oria_dna );
									if ( $oria_hx_n >= 3 ) :
										$oria_hx_w   = 600;
										$oria_hx_h   = 150;
										$oria_hx_top = 22;
										$oria_hx_bot = $oria_hx_h - 22;
										$oria_hx_pts = array();
										$oria_hx_twin = array();
										foreach ( $oria_dna as $oria_hi => $oria_hb ) {
											$oria_hs = max( 1, min( 5, (int) $oria_hb['score'] ) );
											$oria_hy = $oria_hx_top + ( 5 - $oria_hs ) / 4 * ( $oria_hx_bot - $oria_hx_top );
											$oria_hx = ( $oria_hi + 0.5 ) / $oria_hx_n * $oria_hx_w;
											$oria_hx_pts[]  = array( 'x' => $oria_hx, 'y' => $oria_hy, 'c' => $oria_hx_col[ $oria_hb['key'] ] ?? '#4F7A64', 'key' => (string) $oria_hb['key'] );
											$oria_hx_twin[] = array( 'x' => $oria_hx, 'y' => $oria_hx_h - $oria_hy );
										}
										// A smooth path (Catmull-Rom as cubic Béziers), running in flat
										// from the left edge and out to the right like a strand.
										$oria_hx_path = static function ( array $pts, int $w ): string {
											$all = array_merge( array( array( 'x' => 0, 'y' => $pts[0]['y'] ) ), $pts, array( array( 'x' => $w, 'y' => $pts[ count( $pts ) - 1 ]['y'] ) ) );
											$d   = sprintf( 'M%.1f %.1f', $all[0]['x'], $all[0]['y'] );
											$n   = count( $all );
											for ( $i = 0; $i < $n - 1; $i++ ) {
												$p0 = $all[ max( 0, $i - 1 ) ];
												$p1 = $all[ $i ];
												$p2 = $all[ $i + 1 ];
												$p3 = $all[ min( $n - 1, $i + 2 ) ];
												$d .= sprintf(
													' C%.1f %.1f %.1f %.1f %.1f %.1f',
													$p1['x'] + ( $p2['x'] - $p0['x'] ) / 6,
													$p1['y'] + ( $p2['y'] - $p0['y'] ) / 6,
													$p2['x'] - ( $p3['x'] - $p1['x'] ) / 6,
													$p2['y'] - ( $p3['y'] - $p1['y'] ) / 6,
													$p2['x'],
													$p2['y']
												);
											}
											return $d;
										};
										$oria_hx_gid = 'xpHx' . (int) $oria_id;
										?>
										<figure class="xp-helix" data-xp-helix aria-hidden="true">
											<svg class="xp-helix__svg" viewBox="0 0 <?php echo (int) $oria_hx_w; ?> <?php echo (int) $oria_hx_h; ?>" preserveAspectRatio="none" focusable="false">
												<defs>
													<linearGradient id="<?php echo esc_attr( $oria_hx_gid ); ?>" x1="0" y1="0" x2="<?php echo (int) $oria_hx_w; ?>" y2="0" gradientUnits="userSpaceOnUse">
														<?php foreach ( $oria_hx_pts as $oria_p ) : ?>
															<stop offset="<?php echo esc_attr( (string) round( $oria_p['x'] / $oria_hx_w, 4 ) ); ?>" stop-color="<?php echo esc_attr( $oria_p['c'] ); ?>"/>
														<?php endforeach; ?>
													</linearGradient>
												</defs>
												<line class="xp-helix__mid" x1="0" y1="<?php echo (int) ( $oria_hx_h / 2 ); ?>" x2="<?php echo (int) $oria_hx_w; ?>" y2="<?php echo (int) ( $oria_hx_h / 2 ); ?>"/>
												<?php foreach ( $oria_hx_pts as $oria_hi => $oria_p ) : ?>
													<line class="xp-helix__rung" x1="<?php echo esc_attr( (string) round( $oria_p['x'], 1 ) ); ?>" y1="<?php echo esc_attr( (string) round( $oria_p['y'], 1 ) ); ?>" x2="<?php echo esc_attr( (string) round( $oria_p['x'], 1 ) ); ?>" y2="<?php echo esc_attr( (string) round( $oria_hx_twin[ $oria_hi ]['y'], 1 ) ); ?>" stroke="<?php echo esc_attr( $oria_p['c'] ); ?>"/>
												<?php endforeach; ?>
												<path class="xp-helix__twin" d="<?php echo esc_attr( $oria_hx_path( $oria_hx_twin, $oria_hx_w ) ); ?>" stroke="url(#<?php echo esc_attr( $oria_hx_gid ); ?>)" pathLength="1"/>
												<path class="xp-helix__line" d="<?php echo esc_attr( $oria_hx_path( $oria_hx_pts, $oria_hx_w ) ); ?>" stroke="url(#<?php echo esc_attr( $oria_hx_gid ); ?>)" pathLength="1"/>
											</svg>
											<?php // The points in HTML, so they stay round however wide the strand is drawn. ?>
											<div class="xp-helix__dots" style="--xp-hx-n:<?php echo (int) $oria_hx_n; ?>">
												<?php foreach ( $oria_hx_pts as $oria_p ) : ?>
													<span class="xp-helix__col" style="--xp-hx-c:<?php echo esc_attr( $oria_p['c'] ); ?>">
														<i class="xp-helix__dot" style="top:<?php echo esc_attr( (string) round( $oria_p['y'] / $oria_hx_h * 100, 2 ) ); ?>%"></i>
														<span class="xp-helix__lab"><?php echo esc_html( $oria_hx_short[ $oria_p['key'] ] ?? '' ); ?></span>
													</span>
												<?php endforeach; ?>
											</div>
										</figure>
									<?php endif; ?>
									<ul class="xp-dna">
										<?php foreach ( $oria_dna as $oria_b ) : ?>
											<?php $oria_dm = $oria_dmeta[ $oria_b['key'] ] ?? array( $oria_b['label'], '', '' ); ?>
											<li class="xp-dna__row" style="--xp-hx-c:<?php echo esc_attr( $oria_hx_col[ $oria_b['key'] ] ?? '' ); ?>">
												<span class="xp-dna__label"><?php echo esc_html( $oria_dm[0] ); ?></span>
												<span class="xp-dna__scale" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: dimension, 2: score, 3: score in words, 4: low end, 5: high end */ __( '%1$s: %2$d out of 5, %3$s. 1 is %4$s, 5 is %5$s.', 'oria' ), $oria_dm[0], (int) $oria_b['score'], strtolower( (string) $oria_b['word'] ), strtolower( $oria_dm[1] ), strtolower( $oria_dm[2] ) ) ); ?>">
													<span class="xp-dna__end" aria-hidden="true"><?php echo esc_html( $oria_dm[1] ); ?></span>
													<span class="xp-dna__track" aria-hidden="true"><?php for ( $oria_i = 1; $oria_i <= 5; $oria_i++ ) : ?><i class="xp-dna__seg<?php echo $oria_i <= (int) $oria_b['score'] ? ' is-on' : ''; ?>"></i><?php endfor; ?></span>
													<span class="xp-dna__end" aria-hidden="true"><?php echo esc_html( $oria_dm[2] ); ?></span>
												</span>
											</li>
										<?php endforeach; ?>
									</ul>
									<details class="xp-dna__how">
										<summary><?php esc_html_e( 'How these ratings work', 'oria' ); ?></summary>
										<div class="xp-dna__howbody">
											<p><?php echo esc_html( sprintf( /* translators: %s: the kind of session */ __( 'These bars are a guide, not a score of this business. Each starts from Oria Haven’s profile of %s as a kind of session, then is adjusted by facts stored on this listing: its price band, its group size and whether it welcomes beginners.', 'oria' ), (string) ( $oria_dnax['label'] ?? '' ) ) ); ?></p>
											<p><?php esc_html_e( 'Nobody has rated this particular venue in person, and reviews are not fed in. Where we have no fact about this listing behind a bar — for example how many people share the room — we leave that bar out rather than guess.', 'oria' ); ?></p>
											<p><?php esc_html_e( 'They describe the room — how quiet, how physical, how many people — never what a session is supposed to do for you.', 'oria' ); ?></p>
										</div>
									</details>
								</div>
							<?php endif; ?>

							<?php if ( '' !== $oria_likely ) : ?>
								<div class="xp__b xp__b--rule">
									<span class="micro rowlabel"><?php esc_html_e( 'Why it might suit you', 'oria' ); ?></span>
									<p class="likely"><?php echo esc_html( $oria_likely ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( '' !== trim( $oria_good_for ) ) : ?>
						<div class="xp-sub">
							<h3 class="h3 xp-sub__title"><?php esc_html_e( "What they're good at", 'oria' ); ?></h3>
							<div class="prose xp-prose"><?php echo wp_kses_post( wpautop( esc_html( $oria_good_for ) ) ); ?></div>
							<?php if ( $oria_claimed_by ) : ?>
								<p class="hint"><?php esc_html_e( 'Told to us by the practice.', 'oria' ); ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php
			/* --- Classes / services you'll find here ----------------------
			 * The parent's services logic, unchanged: a service with a live
			 * facet page becomes a card that goes there; anything else keeps
			 * its own words as a pill. Cards are compact; four show, the
			 * rest sit behind "Show all services". */
			$oria_comefor = function_exists( '\Oria\Core\ComeFor\for_listing' ) ? \Oria\Core\ComeFor\for_listing( $oria_id ) : array();
			$oria_reasons = function_exists( '\Oria\Core\Reasons\flat' ) ? \Oria\Core\Reasons\flat( $oria_id ) : array();
			$oria_cards   = array();
			$oria_srest   = array();
			$oria_taken   = array();
			foreach ( $oria_services as $oria_service ) {
				$oria_sname = trim( (string) ( $oria_service['name'] ?? '' ) );
				if ( '' === $oria_sname ) {
					continue;
				}
				$oria_slugs = function_exists( '\Oria\Core\Services\resolve_all' ) ? \Oria\Core\Services\resolve_all( $oria_sname ) : array();
				$oria_url   = '';
				$oria_slug  = '';
				if ( $oria_slugs && function_exists( '\Oria\Core\PracticesIndex\service_url' ) ) {
					$oria_slug = (string) $oria_slugs[0];
					$oria_url  = \Oria\Core\PracticesIndex\service_url( $oria_slug );
				}
				if ( '' === $oria_url || isset( $oria_taken[ $oria_url ] ) ) {
					$oria_srest[] = $oria_sname;
					continue;
				}
				$oria_taken[ $oria_url ] = true;
				$oria_cards[]            = array(
					'label' => $oria_sname,
					'url'   => $oria_url,
					'note'  => function_exists( '\Oria\Core\Services\note_any' ) ? \Oria\Core\Services\note_any( $oria_slug ) : '',
					'slug'  => $oria_slug,
				);
			}
			$oria_svc_show = 4;
			?>
			<?php if ( $oria_cards || $oria_srest || $oria_comefor || $oria_reasons ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" id="services" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php echo esc_html( $oria_is_classes ? __( 'Classes you’ll find here', 'oria' ) : __( 'What you’ll find here', 'oria' ) ); ?></h2>
					<?php if ( $oria_cards ) : ?>
						<p class="xp-sec__hint"><?php printf( esc_html__( 'Each one leads to everywhere else in %s that offers it.', 'oria' ), esc_html( $oria_cityw ) ); ?></p>
						<ul class="xp-svc" id="xp-svc-list" data-xp-svc="<?php echo (int) $oria_svc_show; ?>">
							<?php foreach ( $oria_cards as $oria_ci => $oria_c ) : ?>
								<?php
								$oria_cimg  = \Oria\Theme\facet_image( (string) $oria_c['slug'] );
								$oria_ccard = function_exists( '\Oria\Core\Services\card' )
									? \Oria\Core\Services\card( (string) $oria_c['slug'] )
									: array( 'traits' => array() );
								// Two facts at most, and never one that repeats the card's name or its one-line note.
								$oria_ctraits = array_slice( array_values( array_filter(
									(array) ( $oria_ccard['traits'] ?? array() ),
									static fn( $oria_tr ) => '' !== trim( (string) $oria_tr )
										&& false === stripos( (string) $oria_tr, (string) $oria_c['label'] )
										&& false === stripos( (string) $oria_c['note'], trim( (string) $oria_tr ) )
								) ), 0, 2 );
								?>
								<li class="xp-svc__item"<?php echo $oria_ci >= $oria_svc_show ? ' data-xp-extra' : ''; ?>>
									<a class="xp-svc__card" href="<?php echo esc_url( $oria_c['url'] ); ?>">
										<?php if ( $oria_cimg ) : ?>
											<img class="xp-svc__img" src="<?php echo esc_url( $oria_cimg ); ?>" alt="" loading="lazy" decoding="async" width="160" height="160">
										<?php else : ?>
											<span class="xp-svc__img xp-svc__img--none" aria-hidden="true"></span>
										<?php endif; ?>
										<span class="xp-svc__body">
											<b class="xp-svc__name"><?php echo esc_html( $oria_c['label'] ); ?></b>
											<?php if ( '' !== $oria_c['note'] ) : ?>
												<span class="xp-svc__note"><?php echo esc_html( $oria_c['note'] ); ?></span>
											<?php endif; ?>
											<?php if ( $oria_ctraits ) : ?>
												<span class="xp-svc__facts">
													<?php foreach ( $oria_ctraits as $oria_tr ) : ?>
														<span class="xp-svc__fact"><span aria-hidden="true">&#10003;</span> <?php echo esc_html( (string) $oria_tr ); ?></span>
													<?php endforeach; ?>
												</span>
											<?php endif; ?>
											<span class="xp-svc__go"><?php printf( esc_html__( 'More places in %s', 'oria' ), esc_html( $oria_cityw ) ); ?> <span aria-hidden="true">&rarr;</span></span>
										</span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php if ( count( $oria_cards ) > $oria_svc_show ) : ?>
							<?php // Revealed by v4-listing.js, which also folds the extra cards; without it every card simply shows. ?>
							<button type="button" class="btn btn--ghost xp-tap xp-svc__all" data-xp-svc-all aria-controls="xp-svc-list" aria-expanded="false" hidden
								data-less="<?php esc_attr_e( 'Show fewer services', 'oria' ); ?>">
								<?php printf( esc_html__( 'Show all services (%d)', 'oria' ), count( $oria_cards ) ); ?>
							</button>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( $oria_srest ) : ?>
						<ul class="xp-tags xp-tags--services" aria-label="<?php esc_attr_e( 'Also offered', 'oria' ); ?>">
							<?php foreach ( $oria_srest as $oria_r ) : ?>
								<li class="xp-tag"><?php echo esc_html( $oria_r ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( $oria_comefor ) : ?>
						<div class="xp-sub">
							<h3 class="h3 xp-sub__title"><?php esc_html_e( 'People come here for', 'oria' ); ?></h3>
							<ul class="chips">
								<?php foreach ( $oria_comefor as $oria_cf ) : ?>
									<li><span class="chip"><?php echo esc_html( $oria_cf['label'] ); ?></span></li>
								<?php endforeach; ?>
							</ul>
							<p class="hint"><?php esc_html_e( 'Told to us by the practice. Reasons people book in — not a statement about what treatment achieves.', 'oria' ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $oria_reasons ) : ?>
						<div class="xp-sub">
							<h3 class="h3 xp-sub__title"><?php esc_html_e( 'Why people come here', 'oria' ); ?></h3>
							<ul class="chips">
								<?php foreach ( $oria_reasons as $oria_rn ) : ?>
									<li><span class="chip"><?php echo esc_html( $oria_rn['label'] ); ?></span></li>
								<?php endforeach; ?>
							</ul>
							<p class="hint"><?php esc_html_e( 'Told to us by the practice.', 'oria' ); ?></p>
						</div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php /* --- The week, from the Classes repeater --------------------- */ ?>
			<?php if ( $oria_week ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" id="timetable" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Weekly timetable', 'oria' ); ?></h2>
					<p class="xp-sec__hint"><?php esc_html_e( 'Published by the practice. Public holidays excepted — check before you travel.', 'oria' ); ?></p>
					<?php get_template_part( 'template-parts/listing-week', null, array( 'sessions' => $oria_week ) ); ?>
				</section>
			<?php endif; ?>

			<?php /* --- Packages (paid plans only) ------------------------------ */ ?>
			<?php if ( $oria_packages ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Packages', 'oria' ); ?></h2>
					<div class="pkgs">
						<?php
						foreach ( $oria_packages as $oria_pkg ) :
							$oria_ptitle = trim( (string) ( $oria_pkg['title'] ?? '' ) );
							if ( '' === $oria_ptitle ) {
								continue;
							}
							$oria_pdesc  = trim( (string) ( $oria_pkg['description'] ?? '' ) );
							$oria_pprice = trim( (string) ( $oria_pkg['price'] ?? '' ) );
							$oria_pimg   = (int) ( $oria_pkg['image'] ?? 0 );
							$oria_purl   = \Oria\Theme\outbound( trim( (string) ( $oria_pkg['booking_url'] ?? '' ) ), $oria_slugname );
							?>
							<article class="pkgcard">
								<?php if ( $oria_pimg ) : ?>
									<div class="pkgcard__media">
										<?php echo wp_get_attachment_image( $oria_pimg, 'medium_large', false, array( 'class' => 'pkgcard__img', 'loading' => 'lazy', 'alt' => esc_attr( $oria_ptitle ) ) ); ?>
									</div>
								<?php endif; ?>
								<div class="pkgcard__body">
									<h3 class="pkgcard__title"><?php echo esc_html( $oria_ptitle ); ?></h3>
									<?php if ( $oria_pdesc ) : ?>
										<p class="pkgcard__desc"><?php echo esc_html( $oria_pdesc ); ?></p>
									<?php endif; ?>
									<?php if ( $oria_pprice ) : ?>
										<span class="pkgcard__price"><?php echo esc_html( $oria_pprice ); ?></span>
									<?php endif; ?>
									<?php if ( $oria_purl ) : ?>
										<a class="pkgcard__cta" href="<?php echo esc_url( $oria_purl ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="book" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Book', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php
			/* --- Before your first visit ------------------------------------
			 * Stored first-visit fields and ticked amenities only; empty
			 * fields are not shown. Where the details came from is said
			 * plainly, with a date only when a real one is stored. */
			?>
			<?php if ( $oria_first || $oria_amenities ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" id="first-visit" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Before your first visit', 'oria' ); ?></h2>
					<?php if ( $oria_first ) : ?>
						<dl class="xp-first">
							<?php foreach ( $oria_first as $oria_fv ) : ?>
								<div class="xp-first__item">
									<dt><?php echo esc_html( $oria_fv[0] ); ?></dt>
									<dd><?php echo esc_html( $oria_fv[1] ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
					<?php if ( $oria_amenities ) : ?>
						<div class="amenity xp-amenity" id="amenities">
							<?php foreach ( $oria_amenities as $oria_grp ) : ?>
								<div class="amenity__group">
									<h3 class="micro amenity__label"><?php echo esc_html( $oria_grp['label'] ); ?></h3>
									<ul class="amenity__list">
										<?php foreach ( $oria_grp['items'] as $oria_item ) : ?>
											<li>
												<?php echo \Oria\Theme\amenity_icon( (string) $oria_item['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- built SVG, no user input. ?>
												<span><?php echo esc_html( (string) $oria_item['label'] ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<p class="hint xp-source">
						<?php
						if ( 'unclaimed' === $oria_status ) {
							esc_html_e( 'From public sources such as their website and Google listing, checked by hand. Not yet confirmed by the practice — worth checking with them before you go.', 'oria' );
						} elseif ( $oria_verified ) {
							/* translators: %s: date */
							echo esc_html( sprintf( __( 'Told to us by the practice. Last confirmed %s.', 'oria' ), mysql2date( 'j F Y', $oria_verified ) ) );
						} else {
							esc_html_e( 'Told to us by the practice.', 'oria' );
						}
						?>
					</p>
				</section>
			<?php endif; ?>

			<?php
			/* --- Location and hours ------------------------------------------
			 * Address (or an honest "not provided"), directions, the map on
			 * request only, then today's hours with the week in a disclosure. */
			$oria_map   = '' !== $oria_address ? \Oria\Theme\map_embed_url( $oria_street ? $oria_address : $oria_dir_dest ) : '';
			$oria_geo   = function_exists( '\Oria\Core\Geo\position' ) ? \Oria\Core\Geo\position( $oria_id ) : null;
			$oria_kmlab = $oria_geo ? \Oria\Core\Geo\label( $oria_id ) : '';
			$oria_has_loc = '' !== $oria_address || '' !== $oria_area_name || $oria_transit || $oria_parking || '' !== $oria_kmlab || $oria_wk || $oria_hbits;
			?>
			<?php if ( $oria_has_loc && 'online' !== $oria_format ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" id="getting-there" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<?php
					/*
					 * The neighbourhood, at the head of the section already
					 * about where this place is. AreaContext returns null
					 * for a suburb too thin to have a page, so nothing
					 * renders rather than something pointing at a 404.
					 */
					if ( function_exists( '\Oria\Core\AreaContext\for_post' ) ) {
						$oria_area_ctx = \Oria\Core\AreaContext\for_post( $oria_id );
						if ( $oria_area_ctx ) {
							get_template_part(
								'template-parts/area/area-strip',
								null,
								array( 'area' => $oria_area_ctx, 'source' => 'listing-location' )
							);
						}
					}
					?>
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php echo esc_html( $oria_wk || $oria_hbits ? __( 'Location and hours', 'oria' ) : __( 'Getting there', 'oria' ) ); ?></h2>
					<div class="xp-loc">
						<div class="xp-loc__col">
							<p class="xp-loc__k"><?php esc_html_e( 'Address', 'oria' ); ?></p>
							<?php if ( $oria_street ) : ?>
								<p class="xp-loc__v"><?php echo esc_html( $oria_address ); ?></p>
							<?php else : ?>
								<p class="xp-loc__v"><?php echo esc_html( '' !== $oria_address ? $oria_address : $oria_area_name ); ?></p>
								<p class="xp-loc__note"><?php esc_html_e( 'Exact address not provided.', 'oria' ); ?></p>
							<?php endif; ?>
							<?php if ( $oria_dir_url ) : ?>
								<a class="btn btn--ghost xp-tap xp-loc__dir" href="<?php echo esc_url( $oria_dir_url ); ?>" rel="noopener" target="_blank" data-oria-track="dir" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo $oria_ico['dir']; // phpcs:ignore ?><span><?php esc_html_e( 'Get directions', 'oria' ); ?></span><span class="xp-vh"> <?php esc_html_e( '(opens Google Maps)', 'oria' ); ?></span></a>
							<?php endif; ?>

							<?php if ( $oria_transit || $oria_parking || '' !== $oria_kmlab ) : ?>
								<dl class="xp-loc__more">
									<?php if ( $oria_transit ) : ?>
										<div><dt><?php esc_html_e( 'Public transport', 'oria' ); ?></dt><dd><?php echo esc_html( $oria_transit ); ?></dd></div>
									<?php endif; ?>
									<?php if ( $oria_parking ) : ?>
										<div><dt><?php esc_html_e( 'Parking', 'oria' ); ?></dt><dd><?php echo esc_html( $oria_parking ); ?></dd></div>
									<?php endif; ?>
									<?php if ( '' !== $oria_kmlab ) : ?>
										<div data-oria-distance data-lat="<?php echo esc_attr( (string) $oria_geo['lat'] ); ?>" data-lng="<?php echo esc_attr( (string) $oria_geo['lng'] ); ?>">
											<dt><?php esc_html_e( 'Distance', 'oria' ); ?></dt>
											<dd>
												<span data-oria-distance-value><?php echo esc_html( $oria_kmlab ); ?></span>
												<?php if ( 'suburb' === $oria_geo['precision'] ) : ?>
													<small class="xp-loc__small"><?php esc_html_e( 'measured to the suburb, not the door', 'oria' ); ?></small>
												<?php endif; ?>
												<?php // ODbL requires OpenStreetMap to be credited wherever a derived distance is shown. ?>
												<small class="xp-loc__small xp-loc__small--faint"><?php echo esc_html( \Oria\Core\Geo\attribution() ); ?></small>
											</dd>
										</div>
									<?php endif; ?>
								</dl>
							<?php endif; ?>
						</div>

						<?php if ( $oria_wk || $oria_hbits ) : ?>
							<div class="xp-loc__col">
								<p class="xp-loc__k"><?php esc_html_e( 'Opening hours', 'oria' ); ?></p>
								<?php if ( $oria_hbits ) : ?>
									<ul class="hourslist xp-loc__hours">
										<?php foreach ( $oria_hbits as $oria_hb ) : ?>
											<li><?php echo esc_html( $oria_hb ); ?></li>
										<?php endforeach; ?>
									</ul>
									<p class="hint"><?php esc_html_e( 'Hours from the practice — worth a check before a special trip.', 'oria' ); ?></p>
								<?php else : ?>
									<p class="xp-loc__v xp-loc__today">
										<span data-xp-today-long><?php echo esc_html( '' !== $oria_today ? sprintf( /* translators: %s: today's hours */ __( 'Today: %s', 'oria' ), $oria_today ) : __( 'Hours for today not listed', 'oria' ) ); ?></span>
									</p>
									<details class="xp-loc__week">
										<summary><?php esc_html_e( 'Hours for the full week', 'oria' ); ?></summary>
										<ul class="hourslist">
											<?php foreach ( $oria_wk as $oria_hl ) : ?>
												<li<?php echo 0 === stripos( $oria_hl, $oria_todayw ) ? ' class="is-today"' : ''; ?>><?php echo esc_html( $oria_hl ); ?></li>
											<?php endforeach; ?>
										</ul>
									</details>
									<p class="hint">
										<?php
										echo esc_html(
											$oria_gts > 0
												/* translators: %s: date the hours were fetched from Google */
												? sprintf( __( 'Hours via Google, last fetched %s — worth a check before a special trip.', 'oria' ), wp_date( 'j F Y', $oria_gts ) )
												: __( 'Hours via Google — worth a check before a special trip.', 'oria' )
										);
										?>
									</p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( $oria_map ) : ?>
						<button type="button" class="mapfacade xp-loc__map" data-map-src="<?php echo esc_url( $oria_map ); ?>"
							data-map-title="<?php printf( esc_attr__( 'Map showing %s', 'oria' ), esc_attr( $oria_title ) ); ?>">
							<?php echo $oria_ico['dir']; // phpcs:ignore ?>
							<span><?php esc_html_e( 'Show the map', 'oria' ); ?><span class="xp-vh"> <?php echo esc_html( sprintf( /* translators: %s: listing name */ __( 'of %s (loads Google Maps)', 'oria' ), $oria_title ) ); ?></span></span>
						</button>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php
			/* --- Why our editors picked it --------------------------------
			 * The strongest pick with the editor's reason first; any other
			 * guides as a compact list, so two near-identical excerpts are
			 * never stacked. Same data as template-parts/best-featured-in. */
			?>
			<?php if ( $oria_best_in ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec xp-picks" id="picked" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Why our editors picked it', 'oria' ); ?></h2>
					<?php
					// The lead pick first, as the badge in the hero shows it.
					usort( $oria_best_in, static fn( $a, $b ) => (int) ! empty( $b['lead'] ) <=> (int) ! empty( $a['lead'] ) );
					$oria_lead_pick = $oria_best_in[0];
					$oria_more_pick = array_slice( $oria_best_in, 1 );
					$oria_png       = \Oria\Core\BestOf\seal_url( (string) $oria_lead_pick['award'], 'png', false );
					?>
					<figure class="xp-pick xp-pick--lead">
						<figcaption class="xp-pick__head">
							<span class="xp-pick__award"><span class="xp-pick__mark badge--best__mark" aria-hidden="true">&#10022;</span> <?php echo esc_html( (string) $oria_lead_pick['label'] ); ?></span>
							<span class="xp-pick__sep" aria-hidden="true">&middot;</span>
							<a class="xp-pick__guide" href="<?php echo esc_url( (string) get_permalink( $oria_lead_pick['guide'] ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_lead_pick['guide'] ) ) ); ?></a>
						</figcaption>
						<?php if ( ! empty( $oria_lead_pick['reason'] ) ) : ?>
							<blockquote class="xp-pick__why"><p>&ldquo;<?php echo esc_html( (string) $oria_lead_pick['reason'] ); ?>&rdquo;</p></blockquote>
						<?php endif; ?>
						<?php if ( $oria_png ) : ?>
							<a class="xp-pick__seal" href="<?php echo esc_url( $oria_png ); ?>" download><?php esc_html_e( 'Run this practice? Download the seal for your website', 'oria' ); ?></a>
						<?php endif; ?>
					</figure>
					<?php if ( $oria_more_pick ) : ?>
						<div class="xp-picks__more">
							<h3 class="xp-picks__morek"><?php esc_html_e( 'Also picked in', 'oria' ); ?></h3>
							<ul>
								<?php foreach ( $oria_more_pick as $oria_row ) : ?>
									<?php $oria_mpng = \Oria\Core\BestOf\seal_url( (string) $oria_row['award'], 'png', false ); ?>
									<li>
										<span class="xp-pick__mark badge--best__mark" aria-hidden="true">&#10022;</span>
										<span class="xp-picks__award"><?php echo esc_html( (string) $oria_row['label'] ); ?></span>
										<span aria-hidden="true">&middot;</span>
										<a class="xp-pick__guide" href="<?php echo esc_url( (string) get_permalink( $oria_row['guide'] ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_row['guide'] ) ) ); ?></a>
										<?php if ( $oria_mpng ) : ?>
											<a class="xp-picks__seal" href="<?php echo esc_url( $oria_mpng ); ?>" download><?php esc_html_e( 'Seal', 'oria' ); ?><span class="xp-vh"> <?php echo esc_html( sprintf( /* translators: %s: award label */ __( 'for %s', 'oria' ), (string) $oria_row['label'] ) ); ?></span></a>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
					<p class="hint"><?php esc_html_e( 'Best Of guides are an editorial selection. A practice cannot pay to be in one.', 'oria' ); ?></p>
				</section>
			<?php endif; ?>

			<?php
			/* --- Who you'll see ------------------------------------------
			 * Both of the parent's team renderers: the listing's own team
			 * repeater, and template-parts/team (Team\visible), which opens
			 * its own section. */
			$oria_team = \Oria\Theme\rows( 'team', array(), $oria_id );
			$oria_team = array_values( array_filter( (array) $oria_team, static function ( $oria_tr ) {
				return is_array( $oria_tr ) && '' !== trim( (string) ( $oria_tr['name'] ?? '' ) );
			} ) );
			?>
			<?php if ( $oria_team ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Who you\'ll see', 'oria' ); ?></h2>
					<div class="teamrow">
						<?php foreach ( array_slice( $oria_team, 0, 6 ) as $oria_tm ) : ?>
							<?php
							$oria_tphoto = $oria_tm['photo'] ?? null;
							if ( is_array( $oria_tphoto ) ) {
								$oria_tphoto = $oria_tphoto['sizes']['thumbnail'] ?? ( $oria_tphoto['url'] ?? '' );
							} elseif ( is_numeric( $oria_tphoto ) ) {
								$oria_tphoto = (string) wp_get_attachment_image_url( (int) $oria_tphoto, 'thumbnail' );
							} else {
								$oria_tphoto = '';
							}
							?>
							<div class="teamcard">
								<?php if ( $oria_tphoto ) : ?>
									<img class="teamcard__photo" src="<?php echo esc_url( $oria_tphoto ); ?>" alt="" width="56" height="56" loading="lazy">
								<?php endif; ?>
								<div>
									<b class="teamcard__name"><?php echo esc_html( (string) $oria_tm['name'] ); ?></b>
									<?php if ( ! empty( $oria_tm['role'] ) ) : ?>
										<span class="teamcard__role"><?php echo esc_html( (string) $oria_tm['role'] ); ?></span>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="hint" style="margin-top:.6rem"><?php esc_html_e( 'Told to us by the practice.', 'oria' ); ?></p>
				</section>
			<?php endif; ?>

			<div class="xp-part"><?php get_template_part( 'template-parts/team', null, array( 'listing_id' => $oria_id ) ); ?></div>

			<?php
			/*
			 * Upcoming events run by this listing.
			 *
			 * Asked of \Oria\Core\Events rather than queried here, so this
			 * profile, the category page, the suburb page and the archive
			 * all apply the same rules -- cancelled events excluded, the
			 * same idea of "upcoming" -- and a change is made once.
			 */
			$oria_events = function_exists( '\Oria\Core\Events\for_listing' )
				? array_map( 'get_post', \Oria\Core\Events\for_listing( $oria_id, 6 ) )
				: array();
			?>
			<?php if ( $oria_events ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<div class="xp-sec__headrow">
						<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Upcoming events', 'oria' ); ?></h2>
						<a class="xp-link" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ); ?>"><?php esc_html_e( 'All events', 'oria' ); ?></a>
					</div>
					<div class="stack-md">
						<?php
						foreach ( $oria_events as $oria_ev ) :
							$oria_ev_start = (string) get_field( 'event_start', $oria_ev->ID );
							$oria_ev_ts    = $oria_ev_start ? strtotime( $oria_ev_start ) : false;
							$oria_ev_price = (string) get_field( 'price', $oria_ev->ID );
							$oria_ev_venue = (string) get_field( 'venue', $oria_ev->ID );
							?>
							<article class="eventrow">
								<div class="eventdate">
									<b><?php echo esc_html( $oria_ev_ts ? gmdate( 'd', $oria_ev_ts ) : '—' ); ?></b>
									<span><?php echo esc_html( $oria_ev_ts ? gmdate( 'M', $oria_ev_ts ) : '' ); ?></span>
								</div>
								<div>
									<?php if ( $oria_ev_ts ) : ?>
										<span class="muted" style="font-size:.8125rem"><?php echo esc_html( gmdate( 'D, g.ia', $oria_ev_ts ) ); ?></span>
									<?php endif; ?>
									<h3 class="h3" style="font-size:1.15rem;margin-top:.25rem"><a href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev ) ); ?></a></h3>
									<?php if ( $oria_ev_venue ) : ?>
										<p class="muted" style="font-size:.875rem;margin-top:.25rem"><?php echo esc_html( $oria_ev_venue ); ?></p>
									<?php endif; ?>
								</div>
								<div class="eventrow__cta" style="text-align:right">
									<?php if ( $oria_ev_price ) : ?>
										<div class="listing__price" style="margin-bottom:.6rem"><?php echo esc_html( $oria_ev_price ); ?></div>
									<?php endif; ?>
									<a class="btn btn--sm btn--dark" href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>"><?php esc_html_e( 'Details', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></a>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php
			/* --- Reviews ---------------------------------------------------
			 * A summary with both sources kept apart (Google's count is
			 * Google's), Oria Haven reviews (template-parts/review-list),
			 * three Google reviews attributed and linked, and the review
			 * form folded behind "Write a review". #reviews stays on the
			 * wrapper and #write-review on the form, for the handler's
			 * redirect and the Google sign-in return. */
			$oria_rv_open  = isset( $_GET['review'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state: the handler came back with a message.
			$oria_native_n = function_exists( '\Oria\Core\Reviews\approved' ) ? count( (array) \Oria\Core\Reviews\approved( $oria_id ) ) : 0;
			$oria_sec++;
			$oria_rv_head = 'xp-s' . $oria_sec;
			?>
			<section class="xp-sec" id="reviews" aria-labelledby="<?php echo esc_attr( $oria_rv_head ); ?>">
				<h2 class="h2 xp-sec__title" id="<?php echo esc_attr( $oria_rv_head ); ?>"><?php esc_html_e( 'Reviews', 'oria' ); ?></h2>

				<div class="xp-rvsum">
					<div class="xp-rvsum__scores">
						<?php if ( $oria_grat['rating'] > 0 ) : ?>
							<p class="xp-rvsum__score">
								<span class="xp-rvsum__num"><?php echo esc_html( number_format_i18n( (float) $oria_grat['rating'], 1 ) ); ?></span>
								<span class="xp-rvsum__stars" aria-hidden="true"><?php echo str_repeat( $oria_star, 5 ); // phpcs:ignore ?></span>
								<span class="xp-rvsum__of">
									<?php
									echo esc_html(
										$oria_grat['count'] > 0
											/* translators: %s: number of Google reviews */
											? sprintf( _n( 'out of 5 from %s review on Google', 'out of 5 from %s reviews on Google', (int) $oria_grat['count'], 'oria' ), number_format_i18n( (int) $oria_grat['count'] ) )
											: __( 'out of 5 on Google', 'oria' )
									);
									?>
								</span>
							</p>
						<?php endif; ?>
						<?php if ( 'native' === $oria_rate['source'] && $oria_rate['rating'] > 0 ) : ?>
							<p class="xp-rvsum__score xp-rvsum__score--oria">
								<span class="xp-rvsum__num"><?php echo esc_html( number_format_i18n( (float) $oria_rate['rating'], 1 ) ); ?></span>
								<span class="xp-rvsum__of">
									<?php
									/* translators: %s: number of Oria Haven reviews */
									echo esc_html( sprintf( _n( 'out of 5 from %s Oria Haven review', 'out of 5 from %s Oria Haven reviews', max( 1, (int) $oria_rate['count'] ), 'oria' ), number_format_i18n( max( 1, (int) $oria_rate['count'] ) ) ) );
									?>
								</span>
							</p>
						<?php endif; ?>
						<?php if ( $oria_grat['rating'] <= 0 && 0 === $oria_native_n ) : ?>
							<p class="xp-rvsum__none"><?php esc_html_e( 'No reviews here yet.', 'oria' ); ?></p>
						<?php endif; ?>
					</div>
					<div class="xp-rvsum__acts">
						<button type="button" class="btn btn--dark xp-tap xp-rvopen" data-xp-rvopen aria-controls="xp-rvpanel" aria-expanded="<?php echo $oria_rv_open ? 'true' : 'false'; ?>"><?php esc_html_e( 'Write a review', 'oria' ); ?></button>
						<?php if ( ! empty( $oria_grat['uri'] ) && $oria_grat['count'] > 0 ) : ?>
							<a class="btn btn--ghost xp-tap" href="<?php echo esc_url( $oria_grat['uri'] ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'Read all on Google', 'oria' ); ?><span class="xp-vh"> <?php esc_html_e( '(opens Google)', 'oria' ); ?></span></a>
						<?php endif; ?>
					</div>
				</div>

				<?php get_template_part( 'template-parts/review', 'list', array( 'listing_id' => $oria_id ) ); ?>

				<?php if ( $oria_reviews ) : ?>
					<div class="xp-greviews">
						<h3 class="h3 xp-sub__title"><?php esc_html_e( 'From Google', 'oria' ); ?></h3>
						<?php foreach ( array_slice( $oria_reviews, 0, 3 ) as $oria_rv ) : ?>
							<article class="reviewitem">
								<div class="reviewitem__head">
									<div class="row" style="gap:.75rem">
										<?php if ( ! empty( $oria_rv['avatar'] ) ) : ?>
											<img src="<?php echo esc_url( $oria_rv['avatar'] ); ?>" alt="" aria-hidden="true" width="36" height="36" loading="lazy" decoding="async"
												style="border-radius:50%;flex:none" onerror="this.style.display='none'">
										<?php endif; ?>
										<div>
											<div class="reviewitem__who">
												<?php if ( ! empty( $oria_rv['author_uri'] ) ) : ?>
													<a href="<?php echo esc_url( $oria_rv['author_uri'] ); ?>" rel="nofollow noopener" target="_blank"><?php echo esc_html( $oria_rv['author'] ); ?></a>
												<?php else : ?>
													<?php echo esc_html( $oria_rv['author'] ); ?>
												<?php endif; ?>
											</div>
											<div class="reviewitem__when"><?php echo esc_html( '' !== $oria_rv['when'] ? $oria_rv['when'] . ' · ' : '' ); ?><?php esc_html_e( 'on Google', 'oria' ); ?></div>
										</div>
									</div>
									<?php if ( $oria_rv['rating'] > 0 ) : ?>
										<span class="rating"><?php echo $oria_star; // phpcs:ignore ?> <?php echo esc_html( number_format_i18n( (float) $oria_rv['rating'], 1 ) ); ?><span class="xp-vh"> <?php esc_html_e( 'out of 5', 'oria' ); ?></span></span>
									<?php endif; ?>
								</div>
								<p class="muted xp-greview__text"><?php echo esc_html( $oria_rv['text'] ); ?></p>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php
				/*
				 * The form panel. Hidden until "Write a review" (inline on a
				 * wide screen, a full-screen sheet on a phone -- v4-listing.js),
				 * open on arrival when the handler came back with a message or
				 * the Google sign-in returned to #write-review. Without script
				 * the <noscript> rule shows it inline.
				 */
				?>
				<div class="xp-rvpanel" id="xp-rvpanel" data-xp-rvpanel<?php echo $oria_rv_open ? '' : ' hidden'; ?>>
					<div class="xp-rvpanel__bar">
						<button type="button" class="xp-rvpanel__close xp-tap" data-xp-rvclose>
							<span aria-hidden="true">&times;</span> <?php esc_html_e( 'Close', 'oria' ); ?><span class="xp-vh"> <?php esc_html_e( 'the review form', 'oria' ); ?></span>
						</button>
					</div>
					<?php get_template_part( 'template-parts/review', 'form', array( 'listing_id' => $oria_id ) ); ?>
				</div>
				<noscript><style>.xp-rvpanel[hidden]{display:block!important}.xp-rvopen,.xp-rvpanel__bar{display:none!important}</style></noscript>
			</section>

			<?php /* --- Quick answers: the FAQPage schema reads the same helper -- */ ?>
			<?php $oria_faq = function_exists( '\Oria\Core\Schema\listing_faq' ) ? \Oria\Core\Schema\listing_faq( $oria_id ) : array(); ?>
			<?php if ( $oria_faq ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" id="faq" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Quick answers', 'oria' ); ?></h2>
					<div class="qanda">
						<?php foreach ( $oria_faq as $oria_i => $oria_qa ) : ?>
							<details class="qanda__item"<?php echo 0 === $oria_i ? ' open' : ''; ?>>
								<summary class="qanda__q"><?php echo esc_html( $oria_qa['q'] ); ?></summary>
								<div class="qanda__a"><?php echo wp_kses_post( \Oria\Theme\qanda_html( (string) $oria_qa['a'] ) ); ?></div>
							</details>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php
			/* --- Send an enquiry ---------------------------------------------
			 * The parent's enquiry form, logic unchanged (stored, forwarded
			 * with Reply-To -- Oria\Core\Leads). The rail links here. */
			?>
			<?php if ( $oria_can_enq ) : ?>
				<?php
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
				$oria_lead_state = isset( $_GET['olead'] ) ? (string) $_GET['olead'] : '';
				// phpcs:enable
				$oria_sec++;
				?>
				<section class="xp-sec xp-enquire" id="enquire" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Send an enquiry', 'oria' ); ?></h2>
					<?php if ( 'sent' === $oria_lead_state ) : ?>
						<div class="notice" role="status">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" style="width:20px;height:20px;flex:none" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>
							<span><b><?php esc_html_e( 'Enquiry sent.', 'oria' ); ?></b> <?php esc_html_e( 'It went straight to the practice — check your email for a copy.', 'oria' ); ?></span>
						</div>
					<?php else : ?>
						<form class="xp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-oria-event="enquiry_started">
							<input type="hidden" name="action" value="oria_enquiry">
							<input type="hidden" name="listing_id" value="<?php echo (int) $oria_id; ?>">
							<input type="hidden" name="oform_ts" value="<?php echo esc_attr( (string) time() ); ?>">
							<?php wp_nonce_field( 'oria_enquiry_' . $oria_id, 'oform_nonce' ); ?>
							<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="xp-hp">
							<?php if ( 'error' === $oria_lead_state ) : ?>
								<p class="xp-form__error" role="alert"><?php esc_html_e( 'That didn\'t send — check your name and email and try again.', 'oria' ); ?></p>
							<?php endif; ?>
							<div class="xp-form__row">
								<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span>
									<input class="input" type="text" name="lead_name" autocomplete="name" required></label>
								<label class="field"><span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
									<input class="input" type="email" name="lead_email" autocomplete="email" required></label>
							</div>
							<label class="field"><span class="field__label"><?php esc_html_e( 'Phone', 'oria' ); ?> <span class="xp-form__opt">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
								<input class="input" type="tel" name="lead_phone" autocomplete="tel"></label>
							<label class="field"><span class="field__label"><?php esc_html_e( 'Message', 'oria' ); ?></span>
								<textarea class="textarea" name="lead_notes" style="min-height:96px" maxlength="600" required placeholder="<?php esc_attr_e( 'e.g. availability, prices, what a first visit looks like — please don\'t include medical details', 'oria' ); ?>"></textarea></label>
							<button class="btn btn--dark xp-tap" type="submit"><?php esc_html_e( 'Send to the practice', 'oria' ); ?></button>
						</form>
					<?php endif; ?>
					<?php if ( '' !== $oria_words['enquiry_note'] ) : ?>
						<p class="hint"><?php
							/* translators: %s: "Enquiries go straight to the practice" or "... business" */
							printf( esc_html__( '%s — you\'ll get a copy by email. We never take a cut of bookings.', 'oria' ), esc_html( $oria_words['enquiry_note'] ) );
						?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php
			/* --- Share and follow ------------------------------------------ */
			// Social links are a paid feature: shown only while claimed AND filled in.
			$oria_ig  = 'unclaimed' !== $oria_status ? trim( (string) get_field( 'instagram_url', $oria_id ) ) : '';
			$oria_fbk = 'unclaimed' !== $oria_status ? trim( (string) get_field( 'facebook_url', $oria_id ) ) : '';
			?>
			<div class="xp-extras">
				<?php if ( ! $oria_owns_this ) : ?>
					<?php get_template_part( 'template-parts/share-box', null, array( 'id' => $oria_id, 'owner' => false, 'share_label' => $oria_words['share'] ) ); ?>
				<?php endif; ?>

				<?php if ( $oria_ig || $oria_fbk ) : ?>
					<div class="card">
						<div class="card__body">
							<h2 class="h3" style="font-size:1.05rem;margin-bottom:.75rem"><?php esc_html_e( 'Follow along', 'oria' ); ?></h2>
							<div class="stack" style="font-size:.9375rem">
								<?php if ( $oria_ig ) : ?>
									<a class="row" style="gap:.65rem" href="<?php echo esc_url( $oria_ig ); ?>" rel="nofollow noopener" target="_blank">
										<span class="featurerow__icon" style="width:34px;height:34px;border-radius:10px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="width:16px;height:16px" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1" fill="currentColor" stroke="none"/></svg></span>
										<span><b><?php esc_html_e( 'Instagram', 'oria' ); ?></b><br><span class="muted" style="font-size:.8125rem"><?php echo esc_html( '@' . trim( (string) wp_parse_url( $oria_ig, PHP_URL_PATH ), '/' ) ); ?></span></span>
									</a>
								<?php endif; ?>
								<?php if ( $oria_ig && $oria_fbk ) : ?><hr class="hr"><?php endif; ?>
								<?php if ( $oria_fbk ) : ?>
									<a class="row" style="gap:.65rem" href="<?php echo esc_url( $oria_fbk ); ?>" rel="nofollow noopener" target="_blank">
										<span class="featurerow__icon" style="width:34px;height:34px;border-radius:10px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="width:16px;height:16px" aria-hidden="true"><path d="M15 8h2.5V5H15c-2 0-3.5 1.5-3.5 3.5V11H9v3h2.5v7h3v-7H17l.5-3h-3V8.7c0-.4.3-.7.5-.7Z"/></svg></span>
										<span><b><?php esc_html_e( 'Facebook', 'oria' ); ?></b><br><span class="muted" style="font-size:.8125rem"><?php echo esc_html( trim( (string) wp_parse_url( $oria_fbk, PHP_URL_PATH ), '/' ) ?: __( 'Page', 'oria' ) ); ?></span></span>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! $oria_contactless && ! ( 'unclaimed' === $oria_status && ! $oria_claimed_by ) ) : ?>
					<div class="claimprompt">
						<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'Something out of date?', 'oria' ); ?></b>
						<p style="font-size:.875rem;color:var(--text-soft)">
							<?php esc_html_e( 'This listing is managed by the owner.', 'oria' ); ?>
							<a href="<?php echo esc_url( home_url( '/about/#contact' ) ); ?>" style="text-decoration:underline;text-underline-offset:3px"><?php esc_html_e( 'Let us know', 'oria' ); ?></a>
						</p>
					</div>
				<?php endif; ?>
			</div>

		</div><!-- .xp-story -->

		<!-- The action rail (60rem and up) -->
		<aside class="xp-rail" aria-labelledby="xp-rail-title">
			<div class="xp-rail__card">
				<?php
				/*
				 * Whose box this is, and the card's heading in one. The
				 * "Plan your visit" eyebrow used to carry the rail's
				 * accessible name (the aside points at #xp-rail-title), so
				 * this block takes over the id: a logo, centred, with the
				 * listing's name as its alt -- or the name itself when there
				 * is no logo. Either way a screen reader hears the name.
				 */
				$oria_logo_id = (int) get_post_meta( $oria_qid, 'logo', true );
				$oria_has_logo = $oria_logo_id && wp_attachment_is_image( $oria_logo_id );
				?>
				<h2 class="xp-rail__brand<?php echo $oria_has_logo ? ' xp-rail__brand--logo' : ''; ?>" id="xp-rail-title">
					<?php if ( $oria_has_logo ) : ?>
						<span class="xp-rail__logo">
							<?php
							// 'medium' scales; 'thumbnail' is a 150x150 crop on this site.
							echo wp_get_attachment_image( $oria_logo_id, 'medium', false, array( 'alt' => get_the_title( $oria_qid ), 'loading' => 'lazy', 'decoding' => 'async' ) );
							?>
						</span>
					<?php else : ?>
						<span class="xp-rail__name"><?php echo esc_html( get_the_title( $oria_qid ) ); ?></span>
					<?php endif; ?>
				</h2>

				<?php if ( $oria_offer ) : ?>
					<div class="xp-rail__top">
						<p class="xp-label"><?php esc_html_e( 'Offer', 'oria' ); ?></p>
						<p class="xp-rail__big"><?php echo esc_html( $oria_offer['title'] ); ?></p>
						<?php if ( $oria_offer['text'] ) : ?>
							<p class="xp-rail__sub"><?php echo esc_html( $oria_offer['text'] ); ?></p>
						<?php endif; ?>
						<?php if ( $oria_offer['until'] ) : ?>
							<p class="xp-rail__sub"><?php printf( esc_html__( 'Until %s', 'oria' ), esc_html( mysql2date( 'j F Y', $oria_offer['until'] ) ) ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="xp-rail__ctas">
					<?php if ( $oria_primary ) : ?>
						<a class="btn btn--dark btn--block xp-tap" href="<?php echo esc_url( $oria_primary['url'] ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="<?php echo esc_attr( $oria_primary['track'] ); ?>" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo esc_html( $oria_primary['label'] ); ?><span class="xp-vh"> <?php esc_html_e( '(opens their site)', 'oria' ); ?></span><?php echo arrow(); // phpcs:ignore ?></a>
					<?php endif; ?>
					<?php if ( $oria_booking && $oria_website ) : ?>
						<a class="btn btn--ghost btn--block xp-tap" href="<?php echo esc_url( $oria_website ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="web" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Visit their website', 'oria' ); ?></a>
					<?php endif; ?>
					<div class="xp-rail__row">
						<?php if ( $oria_tel ) : ?>
							<a class="btn btn--ghost xp-tap" href="tel:<?php echo esc_attr( $oria_tel ); ?>" data-oria-track="tel" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo $oria_ico['tel']; // phpcs:ignore ?><span><?php esc_html_e( 'Call', 'oria' ); ?></span><span class="xp-vh"> <?php echo esc_html( $oria_phone ); ?></span></a>
						<?php endif; ?>
						<?php if ( $oria_dir_url ) : ?>
							<a class="btn btn--ghost xp-tap" href="<?php echo esc_url( $oria_dir_url ); ?>" rel="noopener" target="_blank" data-oria-track="dir" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo $oria_ico['dir']; // phpcs:ignore ?><span><?php esc_html_e( 'Directions', 'oria' ); ?></span><span class="xp-vh"> <?php esc_html_e( '(opens Google Maps)', 'oria' ); ?></span></a>
						<?php endif; ?>
						<button class="btn btn--ghost xp-tap savebtn" type="button"
							data-save="<?php echo esc_attr( $oria_slugname ); ?>"
							data-save-name="<?php echo esc_attr( $oria_title ); ?>"
							aria-pressed="false">
							<?php echo $oria_ico['save']; // phpcs:ignore ?><span class="savebtn__label"><?php esc_html_e( 'Save', 'oria' ); ?></span><span class="xp-vh"> <?php echo esc_html( $oria_title ); ?></span>
						</button>
					</div>
					<?php if ( $oria_can_enq ) : ?>
						<a class="xp-link xp-tap" href="#enquire"><?php esc_html_e( 'Send an enquiry', 'oria' ); ?></a>
					<?php endif; ?>
				</div>

				<?php
				// Facts: only rows with something behind them.
				$oria_facts = array();
				if ( 'online' !== $oria_format && ( '' !== $oria_address || '' !== $oria_area_name ) ) {
					$oria_facts[] = array(
						__( 'Address', 'oria' ),
						$oria_street
							? esc_html( $oria_address )
							: esc_html( '' !== $oria_address ? $oria_address : $oria_area_name ) . '<span class="xp-fact__sub xp-fact__block">' . esc_html__( 'Exact address not provided', 'oria' ) . '</span>',
					);
				}
				if ( $oria_offer && $oria_price_num > 0 ) {
					$oria_facts[] = array( __( 'Price', 'oria' ), esc_html( sprintf( __( 'From $%d a session', 'oria' ), $oria_price_num ) ) );
				} elseif ( $oria_price_num > 0 ) {
					$oria_facts[] = array( __( 'Price', 'oria' ), esc_html( sprintf( __( 'From $%d a session', 'oria' ), $oria_price_num ) ) );
				} elseif ( '' !== $oria_band_label ) {
					$oria_facts[] = array( __( 'Typical price', 'oria' ), esc_html( $oria_band_label ) );
				}
				$oria_facts[] = array( __( 'Format', 'oria' ), esc_html( $oria_format_label ) );
				if ( '' !== $oria_rating_html ) {
					$oria_facts[] = array( __( 'Rating', 'oria' ), $oria_rating_html );
				}
				if ( $oria_hbits ) {
					$oria_facts[] = array( __( 'Hours', 'oria' ), implode( '<br>', array_map( 'esc_html', $oria_hbits ) ) );
				} elseif ( '' !== $oria_today ) {
					$oria_facts[] = array( __( 'Hours today', 'oria' ), '<span data-xp-today-short>' . esc_html( $oria_today ) . '</span> <a class="xp-fact__more" href="#getting-there">' . esc_html__( 'All week', 'oria' ) . '</a>' );
				}
				if ( '' !== $oria_next ) {
					$oria_facts[] = array( __( 'Next session', 'oria' ), esc_html( $oria_next ) );
				}
				if ( $oria_show_email && ! $oria_contactless ) {
					$oria_facts[] = array( __( 'Email', 'oria' ), '<a class="xp-fact__link" href="mailto:' . esc_attr( $oria_email ) . '" data-oria-track="mail" data-oria-id="' . (int) $oria_id . '">' . esc_html( $oria_email ) . '</a>' );
				}
				?>
				<dl class="xp-facts">
					<?php foreach ( $oria_facts as $oria_f ) : ?>
						<div class="xp-fact">
							<dt><?php echo esc_html( $oria_f[0] ); ?></dt>
							<dd><?php echo $oria_f[1]; // phpcs:ignore WordPress.Security.EscapeOutput -- every value escaped as it was built. ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</div>
		</aside>
	</div><!-- .xp-body -->

	<?php
	/* --- 3. Similar places --------------------------------------------------
	 * Similar practices, scored on shared category, shared services and
	 * actual kilometres -- see Oria\Core\Similar. Each card says why, but
	 * only from services the two actually share; otherwise it says nothing.
	 * Then the specialty pages this listing is tagged with. */
	$oria_similar = function_exists( '\Oria\Core\Similar\listings_for' ) ? \Oria\Core\Similar\listings_for( $oria_id, 3 ) : array();

	// This listing's services and specialties, by slug, with their names.
	$oria_my_terms = array();
	foreach ( array( 'service', 'specialty' ) as $oria_tax ) {
		$oria_tt = get_the_terms( $oria_id, $oria_tax );
		foreach ( is_array( $oria_tt ) ? $oria_tt : array() as $oria_term ) {
			$oria_my_terms[ $oria_tax ][ $oria_term->slug ] = \Oria\Theme\tname( $oria_term );
		}
	}
	$oria_lc = static function ( string $oria_s ): string {
		// "Ice bath" -> "ice bath", but leave "HIIT" or "Bikram yoga"-style names alone.
		return preg_match( '/^[A-Z][a-z]/', $oria_s ) && ! preg_match( '/^(Bikram|Iyengar|Ashtanga|Kundalini|Reiki|Pilates|Hatha|Yin|Vinyasa|Thai|Swedish|Chinese|Japanese|Himalayan|Finnish)\b/', $oria_s )
			? strtolower( $oria_s[0] ) . substr( $oria_s, 1 )
			: $oria_s;
	};

	$oria_specs     = wp_get_post_terms( $oria_id, 'specialty' );
	$oria_specs     = is_wp_error( $oria_specs ) ? array() : $oria_specs;
	$oria_speccards = array();
	foreach ( $oria_specs as $oria_spec ) {
		$oria_scount = (int) $oria_spec->count;
		// Only where the destination actually holds something in this city.
		if ( function_exists( '\Oria\Core\PracticesIndex\specialty_home' ) && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
			$oria_shome = \Oria\Core\PracticesIndex\specialty_home( $oria_spec->slug );
			$oria_scat  = '' !== $oria_shome ? get_term_by( 'slug', $oria_shome, \Oria\Core\Taxonomies\PRACTICE ) : null;
			if ( ! $oria_scat instanceof WP_Term ) {
				continue;
			}
			$oria_sfacet = \Oria\Core\PracticesIndex\resolve_facet( $oria_scat, $oria_spec->slug );
			if ( ! is_array( $oria_sfacet ) ) {
				continue;
			}
			$oria_srows = \Oria\Core\Cities\filter_ids( \Oria\Core\PracticesIndex\facet_ids( $oria_scat, $oria_sfacet ) );
			if ( ! $oria_srows ) {
				continue;
			}
			$oria_scount = count( $oria_srows );
		}
		$oria_speccards[] = array( 'term' => $oria_spec, 'count' => $oria_scount );
	}
	?>
	<?php if ( $oria_similar || $oria_speccards ) : ?>
		<section class="wrap xp-more" id="similar" aria-labelledby="xp-more-title">
			<?php if ( $oria_similar ) : ?>
				<h2 class="h2 xp-sec__title" id="xp-more-title"><?php echo esc_html( $oria_contactless && '' !== ( $oria_words['similar'] ?? '' ) ? $oria_words['similar'] : __( 'Similar places', 'oria' ) ); ?></h2>
				<ul class="xp-cards">
					<?php
					foreach ( $oria_similar as $oria_nid ) :
						$oria_nid   = (int) $oria_nid;
						$oria_n_sub = null;
						$oria_n_ar  = wp_get_post_terms( $oria_nid, 'area' );
						foreach ( ( is_wp_error( $oria_n_ar ) ? array() : $oria_n_ar ) as $oria_at ) {
							if ( $oria_at->parent ) {
								$oria_n_sub = $oria_at;
								break;
							}
						}
						$oria_n_cat  = function_exists( '\Oria\Core\Categories\primary_for' ) ? \Oria\Core\Categories\primary_for( $oria_nid ) : null;
						$oria_n_km   = function_exists( '\Oria\Core\Similar\km_between' ) ? \Oria\Core\Similar\km_between( $oria_id, $oria_nid ) : null;
						$oria_n_meta = array_filter( array(
							$oria_n_sub ? \Oria\Theme\tname( $oria_n_sub ) : '',
							( null !== $oria_n_km && $oria_n_km >= 0.4 )
								? sprintf( __( '%s km away', 'oria' ), number_format( $oria_n_km, $oria_n_km < 10 ? 1 : 0 ) )
								: '',
						) );
						$oria_n_rate = \Oria\Theme\effective_rating( $oria_nid );

						// A picture: their own, else a cached Google photo. Never a stand-in scene.
						$oria_n_img = (string) get_the_post_thumbnail_url( $oria_nid, 'oria-card' );
						if ( '' === $oria_n_img ) {
							$oria_n_gal = \Oria\Theme\rows( 'gallery', array(), $oria_nid );
							$oria_n_img = $oria_n_gal ? (string) wp_get_attachment_image_url( (int) $oria_n_gal[0], 'oria-card' ) : '';
						}
						if ( '' === $oria_n_img && function_exists( '\Oria\Core\Places\card_photo' ) ) {
							$oria_n_img = $oria_gsz( \Oria\Core\Places\card_photo( $oria_nid ), 640 );
						}

						// Why it is here: services (else specialties) the two actually share.
						$oria_n_why = '';
						foreach ( array( 'service', 'specialty' ) as $oria_tax ) {
							if ( empty( $oria_my_terms[ $oria_tax ] ) ) {
								continue;
							}
							$oria_n_tt = get_the_terms( $oria_nid, $oria_tax );
							$oria_both = array();
							foreach ( is_array( $oria_n_tt ) ? $oria_n_tt : array() as $oria_term ) {
								if ( isset( $oria_my_terms[ $oria_tax ][ $oria_term->slug ] ) ) {
									$oria_both[] = $oria_lc( $oria_my_terms[ $oria_tax ][ $oria_term->slug ] );
								}
							}
							if ( $oria_both ) {
								$oria_both  = array_slice( $oria_both, 0, 3 );
								$oria_last  = array_pop( $oria_both );
								$oria_n_why = sprintf(
									/* translators: %s: list of shared services, e.g. "ice bath and traditional sauna" */
									__( 'Also offers %s.', 'oria' ),
									$oria_both ? implode( ', ', $oria_both ) . ' ' . __( 'and', 'oria' ) . ' ' . $oria_last : $oria_last
								);
								break;
							}
						}
						?>
						<li class="xp-card">
							<?php if ( '' !== $oria_n_img ) : ?>
								<img class="xp-card__img" src="<?php echo esc_url( $oria_n_img ); ?>" alt="" loading="lazy" decoding="async" width="640" height="400" onerror="this.remove()">
							<?php else : ?>
								<span class="xp-card__img xp-card__img--none" aria-hidden="true"></span>
							<?php endif; ?>
							<div class="xp-card__body">
								<?php if ( $oria_n_cat instanceof WP_Term ) : ?>
									<p class="xp-label"><?php echo esc_html( \Oria\Theme\tname( $oria_n_cat ) ); ?></p>
								<?php endif; ?>
								<h3 class="xp-card__title"><a class="xp-card__link" href="<?php echo esc_url( (string) get_permalink( $oria_nid ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( $oria_nid ) ); ?></a></h3>
								<?php if ( $oria_n_meta || ( $oria_n_rate['rating'] ?? 0 ) > 0 ) : ?>
									<p class="xp-card__meta">
										<?php echo esc_html( implode( ' · ', $oria_n_meta ) ); ?>
										<?php if ( ( $oria_n_rate['rating'] ?? 0 ) > 0 ) : ?>
											<span class="rating"><?php echo $oria_star; // phpcs:ignore ?><?php echo esc_html( number_format_i18n( (float) $oria_n_rate['rating'], 1 ) ); ?><span class="xp-card__src"><?php echo esc_html( 'google' === $oria_n_rate['source'] ? __( 'on Google', 'oria' ) : __( 'on Oria Haven', 'oria' ) ); ?></span></span>
										<?php endif; ?>
									</p>
								<?php endif; ?>
								<?php if ( '' !== $oria_n_why ) : ?>
									<p class="xp-card__why"><?php echo esc_html( $oria_n_why ); ?></p>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $oria_speccards ) : ?>
				<div class="xp-more__specs">
					<h2 class="h3 xp-sub__title"<?php echo $oria_similar ? '' : ' id="xp-more-title"'; ?>><?php esc_html_e( 'Find more like this', 'oria' ); ?></h2>
					<div class="speccards">
						<?php foreach ( $oria_speccards as $oria_sc ) : ?>
							<?php
							$oria_spec    = $oria_sc['term'];
							$oria_stile   = \Oria\Theme\term_tile( $oria_spec );
							$oria_sparent = \Oria\Theme\specialty_parent( $oria_spec );
							?>
							<a class="speccard" href="<?php echo esc_url( function_exists( '\Oria\Core\PracticesIndex\specialty_url' ) ? \Oria\Core\PracticesIndex\specialty_url( $oria_spec ) : (string) get_term_link( $oria_spec ) ); ?>">
								<?php if ( '' !== $oria_stile ) : ?>
									<img class="speccard__img" src="<?php echo esc_url( $oria_stile ); ?>" alt="" loading="lazy" width="320" height="200">
								<?php endif; ?>
								<span class="speccard__body">
									<?php if ( $oria_sparent ) : ?>
										<span class="speccard__eyebrow"><?php echo esc_html( \Oria\Theme\tname( $oria_sparent ) ); ?></span>
									<?php endif; ?>
									<span class="speccard__name">
										<?php
										printf(
											/* translators: 1: modality name, 2: city name. */
											esc_html__( '%1$s in %2$s', 'oria' ),
											esc_html( \Oria\Theme\tname( $oria_spec ) ),
											esc_html( $oria_cityw )
										);
										?>
									</span>
									<?php if ( $oria_sc['count'] > 0 ) : ?>
										<span class="speccard__count"><?php printf( esc_html( _n( '%s place', '%s places', (int) $oria_sc['count'], 'oria' ) ), esc_html( number_format_i18n( (int) $oria_sc['count'] ) ) ); ?></span>
									<?php endif; ?>
								</span>
								<span class="speccard__go" aria-hidden="true">&rarr;</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php
	/* --- 4. Claim: unclaimed, ownerless, contactable listings only ----------
	 * The parent's claim form, unchanged. Folded behind a "Claim this page"
	 * button unless the reader arrived at #claim or is mid-claim. */
	?>
	<?php if ( ! $oria_contactless && 'unclaimed' === $oria_status && ! $oria_claimed_by ) : // Free-plan listings have an owner -- don't invite rival claims. ?>
		<?php
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$oria_claim_state = isset( $_GET['oria_claim'] ) ? (string) $_GET['oria_claim'] : '';
		// phpcs:enable
		?>
		<section class="wrap xp-claimwrap" aria-labelledby="xp-claim-title">
			<div class="xp-claim on-deep" id="claim">
				<div class="xp-claim__intro">
					<h2 class="xp-claim__title" id="xp-claim-title"><?php echo esc_html( '' !== $oria_words['claim_head'] ? $oria_words['claim_head'] : __( 'Is this your business?', 'oria' ) ); ?></h2>
					<?php if ( 'received' !== $oria_claim_state ) : ?>
						<p class="xp-claim__text"><?php esc_html_e( 'This listing was built from public information. Request to claim it and, once we\'ve confirmed it\'s you, you can edit every detail yourself.', 'oria' ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( 'received' === $oria_claim_state ) : ?>
					<div class="notice xp-claim__done" role="status" data-oria-event="claim_completed">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>
						<span><b><?php esc_html_e( 'Request received.', 'oria' ); ?></b> <?php esc_html_e( 'We check every claim by hand — you\'ll get an email with your log-in once it\'s approved.', 'oria' ); ?></span>
					</div>
				<?php else : ?>
					<details class="xp-claim__fold"<?php echo 'error' === $oria_claim_state ? ' open' : ''; ?>>
						<summary class="btn btn--light xp-tap xp-claim__open"><?php esc_html_e( 'Claim this page', 'oria' ); ?></summary>
						<div class="xp-claim__form">
							<?php if ( 'error' === $oria_claim_state ) : ?>
								<p class="xp-form__error" role="alert"><?php esc_html_e( 'That didn\'t send — check the name and email and try again.', 'oria' ); ?></p>
							<?php endif; ?>
							<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="xp-form" data-oria-event="claim_started">
								<input type="hidden" name="action" value="oria_claim">
								<input type="hidden" name="listing_id" value="<?php echo (int) $oria_id; ?>">
								<?php wp_nonce_field( 'oria_claim', 'oria_claim_nonce' ); ?>
								<input type="text" name="oria_website_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="xp-hp">
								<div class="xp-form__row">
									<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span><input class="input" type="text" name="claimant_name" autocomplete="name" required></label>
									<label class="field"><span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span><input class="input" type="email" name="claimant_email" autocomplete="email" required placeholder="<?php esc_attr_e( 'Ideally the one on your website', 'oria' ); ?>"></label>
								</div>
								<label class="field"><span class="field__label"><?php esc_html_e( 'Phone (optional)', 'oria' ); ?></span><input class="input" type="text" name="claimant_phone" autocomplete="tel"></label>
								<label class="field"><span class="field__label"><?php esc_html_e( 'Anything that helps us verify you', 'oria' ); ?></span><textarea class="textarea" name="claimant_note" style="min-height:70px" placeholder="<?php esc_attr_e( 'e.g. your role, or where we can confirm your details', 'oria' ); ?>"></textarea></label>
								<button class="btn btn--light xp-tap" type="submit"><?php esc_html_e( 'Request to claim', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></button>
							</form>
						</div>
					</details>
					<script>
					/* Arriving at #claim (the share page links here) opens the form. */
					(function () { if (location.hash === '#claim') { var d = document.querySelector('#claim details'); if (d) { d.open = true; } } })();
					</script>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	/* --- 5. Products: one compact row, after everything a visitor came for.
	 * The shop plugin's own matching (the listing's practice), rendered by its
	 * own band(); hidden when fewer than two products match, which is the
	 * same "no padding a shelf" rule the category pages follow. */
	$oria_prods = function_exists( '\Oria\Shop\Render\auto_products' ) ? \Oria\Shop\Render\auto_products( 3 ) : array();
	$oria_shop  = count( $oria_prods ) >= 2 && function_exists( '\Oria\Shop\Render\band' ) ? \Oria\Shop\Render\band( $oria_prods ) : '';
	?>
	<?php if ( '' !== $oria_shop ) : ?>
		<section class="wrap xp-shop" aria-label="<?php esc_attr_e( 'Products', 'oria' ); ?>"><?php echo $oria_shop; // phpcs:ignore WordPress.Security.EscapeOutput ?></section>
	<?php endif; ?>

	<?php
	/* --- 6. Phone: the bottom action bar ---------------------------------
	 * Words under every icon. Always shown on a phone without script; with
	 * v4-listing.js it appears once the hero's own actions have scrolled
	 * away and steps aside at the footer, while a form field has focus and
	 * while the review sheet is open. Same tracking attributes as every
	 * other button. Not shown from 60rem, where the rail does this job. */
	?>
	<div class="xp-bar" id="xp-bar" role="group" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: listing name */ __( 'Quick actions for %s', 'oria' ), $oria_title ) ); ?>">
		<?php if ( $oria_primary ) : ?>
			<a class="xp-bar__btn xp-bar__btn--main" href="<?php echo esc_url( $oria_primary['url'] ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="<?php echo esc_attr( $oria_primary['track'] ); ?>" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo $oria_ico['web']; // phpcs:ignore ?><span><?php echo esc_html( $oria_primary['short'] ); ?></span></a>
		<?php endif; ?>
		<?php if ( $oria_tel ) : ?>
			<a class="xp-bar__btn" href="tel:<?php echo esc_attr( $oria_tel ); ?>" data-oria-track="tel" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo $oria_ico['tel']; // phpcs:ignore ?><span><?php esc_html_e( 'Call', 'oria' ); ?></span></a>
		<?php endif; ?>
		<?php if ( $oria_dir_url ) : ?>
			<a class="xp-bar__btn<?php echo $oria_primary ? '' : ' xp-bar__btn--main'; ?>" href="<?php echo esc_url( $oria_dir_url ); ?>" rel="noopener" target="_blank" data-oria-track="dir" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo $oria_ico['dir']; // phpcs:ignore ?><span><?php esc_html_e( 'Directions', 'oria' ); ?></span></a>
		<?php endif; ?>
		<button class="xp-bar__btn savebtn" type="button"
			data-save="<?php echo esc_attr( $oria_slugname ); ?>"
			data-save-name="<?php echo esc_attr( $oria_title ); ?>"
			aria-pressed="false">
			<?php echo $oria_ico['save']; // phpcs:ignore ?><span class="savebtn__label"><?php esc_html_e( 'Save', 'oria' ); ?></span>
		</button>
	</div>

</div><!-- .xp-page -->
	<?php
endwhile;

get_footer();
