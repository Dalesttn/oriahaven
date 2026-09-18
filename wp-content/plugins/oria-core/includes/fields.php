<?php
/**
 * ACF integration: local JSON sync plus the listing field groups defined in
 * PHP where a JSON file would be overkill.
 *
 * Field groups edited in the ACF admin save to acf-json/ inside this plugin,
 * so the schema travels in git rather than in the database.
 */

declare(strict_types=1);

namespace Oria\Core\Fields;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_filter( 'acf/settings/save_json', __NAMESPACE__ . '\json_path' );
	add_filter( 'acf/settings/load_json', __NAMESPACE__ . '\json_paths' );
	add_action( 'acf/init', __NAMESPACE__ . '\register_listing_fields' );
	add_action( 'acf/init', __NAMESPACE__ . '\register_event_fields' );
	add_action( 'acf/init', __NAMESPACE__ . '\register_journal_fields' );
	add_action( 'acf/init', __NAMESPACE__ . '\register_author_fields' );
	add_action( 'acf/init', __NAMESPACE__ . '\register_best_of_fields' );

	// The description field is a view onto the post excerpt, not its own
	// meta row. Load reads the excerpt; update writes it and stores nothing.
	add_filter( 'acf/load_value/key=field_oria_description', __NAMESPACE__ . '\load_description', 10, 2 );
	add_filter( 'acf/update_value/key=field_oria_description', __NAMESPACE__ . '\save_description', 10, 2 );

	// One description, one input. See the note on the About tab.
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\drop_excerpt_box', 20 );
}

/**
 * @param mixed      $value
 * @param int|string $post_id
 * @return mixed
 */
function load_description( $value, $post_id ) {
	$id = is_numeric( $post_id ) ? (int) $post_id : 0;
	return $id ? (string) get_post_field( 'post_excerpt', $id, 'raw' ) : $value;
}

/**
 * Write the excerpt and store nothing in postmeta.
 *
 * Returning null is what keeps the value out of the meta table: ACF treats
 * it as "no value" and deletes any row. Without that there would be two
 * copies of the description and no rule about which one wins.
 *
 * wp_update_post() fires save_post, which is what called us, so the static
 * guard stops the recursion.
 *
 * @param mixed      $value
 * @param int|string $post_id
 * @return null
 */
function save_description( $value, $post_id ) {
	static $saving = false;

	$id = is_numeric( $post_id ) ? (int) $post_id : 0;
	if ( $saving || ! $id ) {
		return null;
	}

	$new = (string) $value;
	if ( $new === (string) get_post_field( 'post_excerpt', $id, 'raw' ) ) {
		return null;
	}

	$saving = true;
	wp_update_post( array( 'ID' => $id, 'post_excerpt' => $new ) );
	$saving = false;

	return null;
}

/**
 * Remove WordPress's own Excerpt box from the listing screen.
 *
 * The About tab edits the same value. Leaving both would mean whichever
 * saved last won, and ACF saves after the post — so an edit typed into the
 * core box would be quietly overwritten by the stale text ACF still held.
 */
function drop_excerpt_box(): void {
	remove_meta_box( 'postexcerpt', PostTypes\LISTING, 'normal' );
}

function json_path( string $path ): string {
	return ORIA_CORE_DIR . 'acf-json';
}

function json_paths( array $paths ): array {
	$paths[] = ORIA_CORE_DIR . 'acf-json';
	return $paths;
}

/**
 * The listing schema, one-to-one with data/listings.json from the prototype.
 * Declared in PHP so the importer and the fields can never disagree about a
 * key name.
 */
function register_listing_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_listing',
			'title'    => 'Listing details',
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'listing',
					),
				),
			),
			'position' => 'normal',
			'style'    => 'default',
			'fields'   => array(

				// --- Status -------------------------------------------------
				array(
					'key'       => 'field_oria_tab_status',
					'label'     => 'Status & ownership',
					'type'      => 'tab',
					'placement' => 'left',
				),
				array(
					'key'           => 'field_oria_claim_status',
					'name'          => 'claim_status',
					'label'         => 'Claim status',
					'type'          => 'button_group',
					'choices'       => array(
						'unclaimed' => 'Unclaimed',
						'claimed'   => 'Claimed',
						'featured'  => 'Featured',
					),
					'default_value' => 'unclaimed',
					'instructions'  => 'Featured implies claimed. The importer never overwrites a claimed or featured listing.',
				),
				array(
					'key'           => 'field_oria_admin_featured',
					'name'          => 'admin_featured',
					'label'         => 'Showcase as featured (admin)',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'instructions'  => 'Puts this listing in every Featured placement — home page, hero cards, category bands, priority sorting, gold badge — without touching its plan. The listing stays claimable, and paid Featured still comes only from the claim status above.',
				),
				array(
					'key'           => 'field_oria_hide_experience',
					'name'          => 'hide_experience',
					'label'         => 'Hide the experience profile',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'instructions'  => 'Removes "The experience", "Feels like" and the Experience DNA bars from this listing, and keeps it out of the profile-based matching. Use it when a practice says the profile misdescribes them. Clinics are already excluded automatically.',
				),
				array(
					'key'          => 'field_oria_verified_at',
					'name'         => 'verified_at',
					'label'        => 'Details last verified',
					'type'         => 'date_picker',
					'display_format' => 'j F Y',
					'return_format'  => 'Y-m-d',
					'instructions' => 'The date shown on the profile as "timetable confirmed". Update it whenever you re-check the details.',
				),
				array(
					'key'           => 'field_oria_claimed_by',
					'name'          => 'claimed_by',
					'label'         => 'Claimed by (owner account)',
					'type'          => 'user',
					'role'          => array( 'practitioner' ),
					'return_format' => 'id',
					'allow_null'    => 1,
					'instructions'  => 'The practitioner account that manages this listing. Assign after payment; they can then edit this listing (and only this listing) while the status is Claimed or Featured.',
				),

				/*
				 * --- About --------------------------------------------------
				 *
				 * The description IS the post excerpt. It was only ever
				 * editable in WordPress's own "Excerpt" box, which sits under
				 * the editor, is often collapsed, and is called a word no
				 * practitioner would connect with "the description of my
				 * practice". So the most important paragraph on the profile
				 * was the hardest thing on the screen to find.
				 *
				 * This field reads and writes that same excerpt — see
				 * Fields\load_description() — so there is one description, not
				 * a second one that drifts. The core Excerpt box is removed
				 * from this screen for the same reason: two inputs for one
				 * value is how an edit gets silently overwritten.
				 */
				array(
					'key'       => 'field_oria_tab_about',
					'label'     => 'About',
					'type'      => 'tab',
					'placement' => 'left',
				),
				array(
					'key'          => 'field_oria_description',
					'name'         => 'listing_description',
					'label'        => 'Description',
					'type'         => 'textarea',
					'rows'         => 5,
					'maxlength'    => 600,
					'instructions' => 'The paragraph that opens your profile, and the text Google usually shows under your name in search results — so it is worth the five minutes. Say what you do, who it suits and what a first visit is like, in plain language. Two or three sentences is plenty. Avoid claims about treating or curing anything.',
				),

				// --- Location & contact -------------------------------------
				array(
					'key'       => 'field_oria_tab_contact',
					'label'     => 'Location & contact',
					'type'      => 'tab',
					'placement' => 'left',
				),
				array(
					'key'   => 'field_oria_address',
					'name'  => 'address',
					'label' => 'Street address',
					'type'  => 'text',
				),

				// --- Contact ------------------------------------------------
				array(
					'key'   => 'field_oria_phone',
					'name'  => 'phone',
					'label' => 'Phone',
					'type'  => 'text',
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'   => 'field_oria_email',
					'name'  => 'email',
					'label' => 'Email',
					'type'  => 'email',
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'   => 'field_oria_website',
					'name'  => 'website',
					'label' => 'Website',
					'type'  => 'url',
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_oria_booking_url',
					'name'         => 'booking_url',
					'label'        => 'Booking link',
					'type'         => 'url',
					'wrapper'      => array( 'width' => '50' ),
					'instructions' => 'We link out. We never take the booking ourselves.',
				),

				// --- Services & pricing -------------------------------------
				array(
					'key'       => 'field_oria_tab_services',
					'label'     => 'Services & pricing',
					'type'      => 'tab',
					'placement' => 'left',
				),
				array(
					'key'          => 'field_oria_services',
					'name'         => 'services',
					'label'        => 'Services offered',
					'type'         => 'repeater',
					'button_label' => 'Add service',
					'sub_fields'   => array(
						array(
							'key'   => 'field_oria_service_name',
							'name'  => 'name',
							'label' => 'Service',
							'type'  => 'text',
						),
					),
				),
				array(
					'key'           => 'field_oria_price_from',
					'name'          => 'price_from',
					'label'         => 'Price from (AUD)',
					'type'          => 'number',
					'min'           => 0,
					'instructions'  => '0 means free. Leave empty only if genuinely unknown.',
					'wrapper'       => array( 'width' => '50' ),
				),
				array(
					'key'           => 'field_oria_price_band',
					'name'          => 'price_band',
					'label'         => 'Price band',
					'type'          => 'select',
					'choices'       => array(
						'Free' => 'Free / by donation',
						'$'    => '$ — under $25',
						'$$'   => '$$ — $25–60',
						'$$$'  => '$$$ — $60–200',
						'$$$$' => '$$$$ — $200+',
					),
					'wrapper'       => array( 'width' => '50' ),
				),
				array(
					/*
					 * How long one of these takes, in minutes.
					 *
					 * A number rather than free text so it can be compared and
					 * filtered later. Where a place runs 45, 60 and 90 minute
					 * versions this is the one people book -- usually the shortest
					 * standard session, never the longest on the menu.
					 */
					'key'           => 'field_oria_duration_min',
					'name'          => 'duration_min',
					'label'         => 'Typical session (minutes)',
					'type'          => 'number',
					'min'           => 0,
					'instructions'  => 'The standard session, not the longest on the menu. Leave empty if the site does not say.',
					'wrapper'       => array( 'width' => '33' ),
				),
				array(
					/*
					 * Who else is in the room. The single most useful thing a
					 * nervous first-timer wants to know, and almost never in a
					 * directory. A closed set, because free text here would be
					 * unfilterable and would drift into describing the vibe.
					 */
					'key'           => 'field_oria_group_size',
					'name'          => 'group_size',
					'label'         => 'Group size',
					'type'          => 'select',
					'allow_null'    => 1,
					'choices'       => array(
						'one-to-one' => 'One to one',
						'small'      => 'Small groups (under 12)',
						'class'      => 'Class sized (12+)',
						'solo'       => 'On your own (a room or a machine)',
					),
					'wrapper'       => array( 'width' => '33' ),
				),
				array(
					/*
					 * What to bring, in the practice's own terms.
					 *
					 * One short line, not a paragraph: "Bring a towel and water,
					 * mats provided". Anything longer belongs on their own site,
					 * which is one click away.
					 */
					'key'           => 'field_oria_what_to_bring',
					'name'          => 'what_to_bring',
					'label'         => 'Before you go',
					'type'          => 'text',
					'maxlength'     => 140,
					'placeholder'   => 'Bring a towel and water. Mats provided.',
					'instructions'  => 'One line, in their words. Never a claim about what the session does.',
					'wrapper'       => array( 'width' => '34' ),
				),
				array(
					/*
					 * What kind of thing this is. Nearly every listing is a
					 * practice: you book an hour of somebody's time. A few are
					 * places you simply turn up to -- a juice bar, a bathhouse,
					 * a health-food shop -- where "Book a first session" and
					 * "Is this your practice?" are both wrong. This switches
					 * the page's vocabulary; it changes nothing else.
					 */
					'key'           => 'field_oria_kind',
					'name'          => 'kind',
					'label'         => 'Kind',
					'type'          => 'button_group',
					'choices'       => array(
						'practice' => 'Practice — you book a session',
						'place'    => 'Place — you turn up',
						'spot'     => 'Spot — free and public, nobody to contact',
					),
					'default_value' => 'practice',
					'wrapper'       => array( 'width' => '50' ),
				),
				array(
					'key'           => 'field_oria_format',
					'name'          => 'format',
					'label'         => 'Format',
					'type'          => 'button_group',
					'choices'       => array(
						'in-person' => 'In person',
						'online'    => 'Online',
						'both'      => 'Both',
					),
					'default_value' => 'in-person',
				),

				// --- Classes & packages -------------------------------------
				/*
				 * The two things a practice can only tell us itself. Both are
				 * paid surfaces (see Tiers\FIELD_TIERS) not to ration them,
				 * but because there is no public source to build them from:
				 * class lists move weekly and package prices are nowhere but
				 * the practice's own till.
				 *
				 * Every free-text box here is written by a practitioner and
				 * published as-is, which makes them the likeliest place for a
				 * therapeutic claim to appear. The instructions say so at the
				 * point of writing rather than in a policy nobody reads.
				 */
				array(
					'key'       => 'field_oria_tab_classes',
					'label'     => 'Classes & packages',
					'type'      => 'tab',
					'placement' => 'left',
				),
				/*
				 * A class is entered once -- title, description, price --
				 * and its week lives in the sessions repeater inside it.
				 * "All Levels Hatha" at five day-and-time slots is one entry
				 * with five sessions, not five near-identical rows.
				 */
				array(
					'key'          => 'field_oria_classes',
					'name'         => 'classes',
					'label'        => 'Classes',
					'type'         => 'repeater',
					'button_label' => 'Add class',
					'layout'       => 'row',
					'instructions' => 'Enter each class once, then add its weekly times underneath. Describe what happens in the room — not what it will do for someone.',
					'sub_fields'   => array(
						array(
							'key'         => 'field_oria_cls_title',
							'name'        => 'title',
							'label'       => 'Class',
							'type'        => 'text',
							'placeholder' => 'All Levels Hatha',
							'wrapper'     => array( 'width' => '40' ),
						),
						array(
							'key'         => 'field_oria_cls_desc',
							'name'        => 'description',
							'label'       => 'Short description',
							'type'        => 'textarea',
							'rows'        => 2,
							'maxlength'   => 200,
							'placeholder' => 'Postures held, suits all levels, props provided.',
							'instructions'=> 'No health or outcome claims.',
							'wrapper'     => array( 'width' => '40' ),
						),
						array(
							'key'         => 'field_oria_cls_price',
							'name'        => 'price',
							'label'       => 'Price',
							'type'        => 'text',
							'placeholder' => '$25 or Free',
							'instructions'=> 'Optional.',
							'wrapper'     => array( 'width' => '20' ),
						),
						array(
							'key'          => 'field_oria_cls_kind',
							'name'         => 'kind',
							'label'        => 'Kind',
							'type'         => 'select',
							'choices'      => array(
								'class' => 'Regular class',
								'venue' => 'Visiting practitioner',
								'event' => 'One-off / special event',
							),
							'default_value' => 'class',
							'instructions' => 'Colours the timetable bar.',
							'wrapper'      => array( 'width' => '35' ),
						),
						array(
							'key'          => 'field_oria_cls_mins',
							'name'         => 'mins',
							'label'        => 'Length (minutes)',
							'type'         => 'number',
							'min'          => 0,
							'step'         => 5,
							'instructions' => 'Optional. Shown with a clock on the timetable.',
							'wrapper'      => array( 'width' => '35' ),
						),
						array(
							'key'          => 'field_oria_cls_free',
							'name'         => 'free',
							'label'        => 'Free to attend',
							'type'         => 'true_false',
							'ui'           => 1,
							'wrapper'      => array( 'width' => '30' ),
						),
						array(
							'key'          => 'field_oria_cls_sessions',
							'name'         => 'sessions',
							'label'        => 'Weekly times',
							'type'         => 'repeater',
							'button_label' => 'Add time',
							'layout'       => 'table',
							'instructions' => 'One row per time slot. Pick several days when the same time runs more than once a week. Leave empty for classes by arrangement.',
							'sub_fields'   => array(
								/*
								 * A list, not a single day: "weekday mornings
								 * at 9.30" is one session row with five days
								 * ticked. ISO numbers, so the page filter
								 * never parses prose.
								 */
								array(
									'key'        => 'field_oria_cls_day',
									'name'       => 'day',
									'label'      => 'Day(s)',
									'type'       => 'select',
									'choices'    => function_exists( '\Oria\Core\Classes\day_choices' )
										? \Oria\Core\Classes\day_choices()
										: array(),
									'multiple'   => 1,
									'ui'         => 1,
									'allow_null' => 1,
									'wrapper'    => array( 'width' => '45' ),
								),
								array(
									'key'         => 'field_oria_cls_time',
									'name'        => 'time',
									'label'       => 'Time',
									'type'        => 'text',
									'placeholder' => '9.30 - 10.45am',
									'wrapper'     => array( 'width' => '30' ),
								),
								array(
									'key'         => 'field_oria_cls_with',
									'name'        => 'with',
									'label'       => 'Teacher',
									'type'        => 'text',
									'placeholder' => 'Optional',
									'wrapper'     => array( 'width' => '25' ),
								),
							),
						),
					),
				),
				array(
					'key'          => 'field_oria_packages',
					'name'         => 'packages',
					'label'        => 'Packages',
					'type'         => 'repeater',
					'button_label' => 'Add package',
					'layout'       => 'row',
					'instructions' => 'Passes, courses and bundles. One card each.',
					'sub_fields'   => array(
						array(
							'key'         => 'field_oria_pkg_title',
							'name'        => 'title',
							'label'       => 'Package',
							'type'        => 'text',
							'placeholder' => 'Ten-class pass',
							'wrapper'     => array( 'width' => '50' ),
						),
						array(
							'key'         => 'field_oria_pkg_price',
							'name'        => 'price',
							'label'       => 'Price',
							'type'        => 'text',
							'placeholder' => '$180',
							'wrapper'     => array( 'width' => '50' ),
						),
						/*
						 * The practice's own photograph. Listing photos are
						 * never copied from anywhere else, so this stays
						 * empty until an owner uploads one, and the card is
						 * built to look deliberate without it.
						 */
						array(
							'key'           => 'field_oria_pkg_image',
							'name'          => 'image',
							'label'         => 'Image',
							'type'          => 'image',
							'return_format' => 'id',
							'preview_size'  => 'medium',
							'instructions'  => 'Your own photograph. Optional.',
							'wrapper'       => array( 'width' => '30' ),
						),
						array(
							'key'         => 'field_oria_pkg_desc',
							'name'        => 'description',
							'label'       => 'Short description',
							'type'        => 'textarea',
							'rows'        => 2,
							'maxlength'   => 200,
							'placeholder' => 'Ten classes, valid six months, shareable.',
							'instructions'=> 'What is included and how long it lasts. No health or outcome claims.',
							'wrapper'     => array( 'width' => '40' ),
						),
						array(
							'key'         => 'field_oria_pkg_url',
							'name'        => 'booking_url',
							'label'       => 'Booking link',
							'type'        => 'url',
							'placeholder' => 'https://',
							'instructions'=> 'The page where this package is bought. Optional — without it the card shows no button.',
							'wrapper'     => array( 'width' => '30' ),
						),
					),
				),

				// --- Social & special offer ---------------------------------
				array(
					'key'       => 'field_oria_tab_offer',
					'label'     => 'Social & special offer',
					'type'      => 'tab',
					'placement' => 'left',
				),
				array(
					'key'         => 'field_oria_instagram',
					'name'        => 'instagram_url',
					'label'       => 'Instagram',
					'type'        => 'url',
					'wrapper'     => array( 'width' => '50' ),
					'placeholder' => 'https://instagram.com/…',
				),
				array(
					'key'         => 'field_oria_facebook',
					'name'        => 'facebook_url',
					'label'       => 'Facebook',
					'type'        => 'url',
					'wrapper'     => array( 'width' => '50' ),
					'placeholder' => 'https://facebook.com/…',
				),
				array(
					'key'          => 'field_oria_offer_title',
					'name'         => 'offer_title',
					'label'        => 'Special offer — title',
					'type'         => 'text',
					'placeholder'  => 'First class free in September',
					'instructions' => 'Offers show on the profile (and an "Offer" tag on cards) only while the listing is claimed. No medical or outcome claims.',
				),
				array(
					'key'     => 'field_oria_offer_text',
					'name'    => 'offer_text',
					'label'   => 'Special offer — details',
					'type'    => 'textarea',
					'rows'    => 2,
				),
				array(
					'key'            => 'field_oria_offer_until',
					'name'           => 'offer_until',
					'label'          => 'Special offer — valid until',
					'type'           => 'date_picker',
					'display_format' => 'j F Y',
					'return_format'  => 'Y-m-d',
					'instructions'   => 'The offer hides itself after this date. Leave empty for no end date.',
				),

				// --- Photos & practical info --------------------------------
				array(
					'key'       => 'field_oria_tab_profile',
					'label'     => 'Photos & practical',
					'type'      => 'tab',
					'placement' => 'left',
				),
				array(
					'key'           => 'field_oria_gallery',
					'name'          => 'gallery',
					'label'         => 'Photo gallery',
					'type'          => 'gallery',
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'instructions'  => 'First image leads. With three or more, the profile shows the full gallery layout.',
				),
				array(
					'key'          => 'field_oria_next_session',
					'name'         => 'next_session',
					'label'        => 'Next session',
					'type'         => 'text',
					'placeholder'  => 'Tomorrow 6.30am',
					'wrapper'      => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_oria_good_for',
					'name'         => 'good_for',
					'label'        => 'What they\'re good at',
					'type'         => 'textarea',
					'rows'         => 3,
					'maxlength'    => 300,
					'placeholder'  => 'Small reformer classes with plenty of instructor attention, and a proper beginner intro course.',
					'instructions' => 'A sentence or two on what this practice does especially well — the room, the format, the teaching. Describe what happens there, never what it treats or fixes.',
					'wrapper'      => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_oria_opening_hours',
					'name'         => 'opening_hours',
					'label'        => 'Opening hours',
					'type'         => 'repeater',
					'button_label' => 'Add row',
					'layout'       => 'table',
					'sub_fields'   => array(
						array( 'key' => 'field_oria_oh_days', 'name' => 'days', 'label' => 'Days', 'type' => 'text', 'placeholder' => 'Mon–Fri' ),
						array( 'key' => 'field_oria_oh_hours', 'name' => 'hours', 'label' => 'Hours', 'type' => 'text', 'placeholder' => '6.00am – 8.00pm' ),
					),
				),
				array(
					'key'         => 'field_oria_transit',
					'name'        => 'transit',
					'label'       => 'Getting there — public transport',
					'type'        => 'text',
					'placeholder' => 'Perth Underground, 6 min walk',
					'wrapper'     => array( 'width' => '50' ),
				),
				array(
					'key'         => 'field_oria_parking',
					'name'        => 'parking',
					'label'       => 'Getting there — parking',
					'type'        => 'text',
					'placeholder' => 'Roe St car park, 3 min walk',
					'wrapper'     => array( 'width' => '50' ),
				),
				// --- Why people come here -----------------------------------
				/*
				 * The two ticked vocabularies, together and named after the
				 * question they answer. Both follow the same contract: an
				 * unticked box renders nothing, and no template may read an
				 * empty set as a "no" -- on a seeded listing it only means
				 * nobody has been asked yet.
				 */
				array(
					'key'       => 'field_oria_tab_reasons',
					'label'     => 'Why people come here',
					'type'      => 'tab',
					'placement' => 'left',
				),

				/*
				 * Why people come here. Same contract as amenities above and
				 * a separate vocabulary: that one is what is in the building,
				 * this is how the place runs. Nothing in the list describes an
				 * outcome — see data/reasons.json for where that line sits.
				 */
				array(
					'key'          => 'field_oria_reasons',
					'name'         => 'reasons',
					'label'        => 'Why people come here',
					'type'         => 'checkbox',
					'choices'      => function_exists( '\Oria\Core\Reasons\vocabulary' )
						? \Oria\Core\Reasons\vocabulary()
						: array(),
					'layout'       => 'vertical',
					'instructions' => 'Only what is true of your sessions today. Unticked shows nothing at all.',
				),

				/*
				 * Amenities. Structured, where transit and parking are prose,
				 * because these are meant to become filters once enough
				 * listings carry them — and a checkbox can be counted where a
				 * sentence cannot.
				 *
				 * Choices come from data/amenities.json so the vocabulary
				 * ships in git rather than living in the database. An unticked
				 * box renders nothing at all: on a seeded listing the empty
				 * set means nobody has been asked, and no template may turn
				 * that into a "no".
				 */
				array(
					'key'          => 'field_oria_amenities',
					'name'         => 'amenities',
					'label'        => 'Amenities',
					'type'         => 'checkbox',
					'choices'      => function_exists( '\Oria\Core\Amenities\vocabulary' )
						? \Oria\Core\Amenities\vocabulary()
						: array(),
					'layout'       => 'vertical',
					'instructions' => 'Tick only what you actually have. Anything left unticked is simply not shown — it is never displayed as a "no".',
				),

				// --- Quick answers ------------------------------------------
				/*
				 * Custom question-and-answer pairs, appended after the ones
				 * the page generates from the listing's own data. A question
				 * here that exactly matches a generated one replaces it.
				 */
				array(
					'key'       => 'field_oria_tab_faq',
					'label'     => 'Quick answers',
					'type'      => 'tab',
					'placement' => 'left',
				),
				array(
					'key'          => 'field_oria_faq',
					'name'         => 'faq',
					'label'        => 'Custom questions',
					'type'         => 'repeater',
					'button_label' => 'Add question',
					'layout'       => 'row',
					'instructions' => 'Shown after the automatic answers (location, services, price, booking). A blank line starts a new paragraph; start a line with \'* \' for a bullet point. One question per topic reads better than one long answer. Never write that a practice treats, cures or relieves a condition.',
					'sub_fields'   => array(
						array(
							'key'         => 'field_oria_faq_q',
							'name'        => 'question',
							'label'       => 'Question',
							'type'        => 'text',
							'placeholder' => 'Do I need to bring anything?',
						),
						array(
							'key'         => 'field_oria_faq_a',
							'name'        => 'answer',
							'label'       => 'Answer',
							'type'        => 'textarea',
							'rows'        => 8,
							'maxlength'   => 2500,
							'placeholder' => 'Just yourself — mats, towels and water are provided.',
						),
					),
				),

				// --- Team ---------------------------------------------------
				array(
					'key'       => 'field_oria_tab_team',
					'label'     => 'Team',
					'type'      => 'tab',
					'placement' => 'left',
				),
				array(
					'key'          => 'field_oria_team',
					'name'         => 'team',
					'label'        => 'Practitioners',
					'type'         => 'repeater',
					'button_label' => 'Add a practitioner',
					'max'          => 4,
					'layout'       => 'row',
					'instructions' => 'Up to four people. A free listing publishes the first one; the rest appear once the listing is claimed. Facts only — qualifications, registrations, what somebody actually does. Nothing about what a treatment can achieve.',
					'sub_fields'   => array(
						array(
							'key'      => 'field_oria_team_name',
							'name'     => 'name',
							'label'    => 'Name',
							'type'     => 'text',
							'required' => 1,
							'wrapper'  => array( 'width' => '40' ),
						),
						array(
							'key'          => 'field_oria_team_role',
							'name'         => 'role',
							'label'        => 'Role here',
							'type'         => 'text',
							'required'     => 1,
							'instructions' => 'e.g. "Remedial massage therapist", "Studio owner and yoga teacher".',
							'wrapper'      => array( 'width' => '40' ),
						),
						array(
							'key'     => 'field_oria_team_years',
							'name'    => 'years',
							'label'   => 'Years practising',
							'type'    => 'number',
							'min'     => 0,
							'max'     => 70,
							'wrapper' => array( 'width' => '20' ),
						),
						array(
							'key'           => 'field_oria_team_photo',
							'name'          => 'photo',
							'label'         => 'Photo',
							'type'          => 'image',
							'return_format' => 'id',
							'preview_size'  => 'thumbnail',
							'library'       => 'uploadedTo',
							'instructions'  => 'A headshot. Does not count towards the photo gallery.',
							'wrapper'       => array( 'width' => '30' ),
						),
						array(
							'key'          => 'field_oria_team_quals',
							'name'         => 'quals',
							'label'        => 'Qualifications',
							'type'         => 'textarea',
							'rows'         => 3,
							'instructions' => 'One per line, e.g. "Dip. Remedial Massage (2016)". Qualifications held — not what they treat.',
							'wrapper'      => array( 'width' => '70' ),
						),
						array(
							'key'          => 'field_oria_team_reg_body',
							'name'         => 'reg_body',
							'label'        => 'Registered with',
							'type'         => 'text',
							'instructions' => 'Optional, and worth filling in: a registration anybody can check is the strongest thing on this page. e.g. AHPRA, ATMS, Massage &amp; Myotherapy Australia, Yoga Australia, Australian Breathwork Association.',
							'wrapper'      => array( 'width' => '34' ),
						),
						array(
							'key'          => 'field_oria_team_reg_id',
							'name'         => 'reg_id',
							'label'        => 'Registration number',
							'type'         => 'text',
							'instructions' => 'Shown on the listing so it can be verified.',
							'wrapper'      => array( 'width' => '33' ),
						),
						array(
							'key'          => 'field_oria_team_reg_url',
							'name'         => 'reg_url',
							'label'        => 'Register link',
							'type'         => 'url',
							'instructions' => 'Optional link to the public register entry.',
							'wrapper'      => array( 'width' => '33' ),
						),
						array(
							'key'           => 'field_oria_team_specialties',
							'name'          => 'specialties',
							'label'         => 'Specialises in',
							'type'          => 'select',
							'multiple'      => 1,
							'ui'            => 1,
							'allow_null'    => 1,
							'choices'       => array(),
							'instructions'  => 'Chosen from what this listing already offers, so it stays in step with the directory. Add services on the Services tab first.',
							'wrapper'       => array( 'width' => '50' ),
						),
						array(
							'key'          => 'field_oria_team_languages',
							'name'         => 'languages',
							'label'        => 'Languages',
							'type'         => 'text',
							'instructions' => 'Besides English. Comma separated.',
							'wrapper'      => array( 'width' => '50' ),
						),
						array(
							'key'          => 'field_oria_team_bio',
							'name'         => 'bio',
							'label'        => 'Short bio',
							'type'         => 'textarea',
							'rows'         => 3,
							'maxlength'    => 300,
							'instructions' => 'A couple of sentences about how they work. We cannot publish claims about treating conditions or health outcomes.',
						),
						array(
							'key'          => 'field_oria_team_consent',
							'name'         => 'consent',
							'label'        => 'This person has agreed to appear here',
							'type'         => 'true_false',
							'ui'           => 1,
							'instructions' => 'Required. Publishing somebody\'s name, photo and history is publishing their personal information, and it needs their say-so. Profiles without this stay unpublished.',
						),
					),
				),

				// --- Google Places ------------------------------------------
				array(
					'key'       => 'field_oria_tab_google',
					'label'     => 'Google',
					'type'      => 'tab',
					'placement' => 'left',
				),
				array(
					'key'          => 'field_oria_places_hide',
					'name'         => 'places_hide',
					'label'        => 'Do not show Google reviews or photos here',
					'type'         => 'true_false',
					'ui'           => 1,
					'instructions' => 'Tick this when the rating on the page is not this business\'s own — because it shares an address with a larger neighbour, or because the listing is a group that meets at a venue and picked up the venue\'s reviews. The listing keeps everything else; only the Google rating, reviews and photos come off.',
				),
				array(
					'key'          => 'field_oria_google_place_id',
					'name'         => 'google_place_id',
					'label'        => 'Google place ID',
					'type'         => 'text',
					'instructions' => 'Filled automatically the first time Places photos are fetched. If the wrong venue matched, paste the correct place ID here — the business\'s own Google Maps share link contains it. To show nothing at all, use the switch above rather than clearing this box: an empty box falls back to whatever was fetched last.',
				),
			),
		)
	);
}

function register_event_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_event',
			'title'    => 'Event details',
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'event',
					),
				),
			),
			'position' => 'normal',
			'fields'   => array(
				array(
					'key'            => 'field_oria_event_start',
					'name'           => 'event_start',
					'label'          => 'Starts',
					'type'           => 'date_time_picker',
					'display_format' => 'j M Y g.ia',
					'return_format'  => 'Y-m-d H:i:s',
					'required'       => true,
					'wrapper'        => array( 'width' => '50' ),
				),
				array(
					'key'            => 'field_oria_event_end',
					'name'           => 'event_end',
					'label'          => 'Ends',
					'type'           => 'date_time_picker',
					'display_format' => 'j M Y g.ia',
					'return_format'  => 'Y-m-d H:i:s',
					'wrapper'        => array( 'width' => '50' ),
				),
				array(
					'key'   => 'field_oria_event_price',
					'name'  => 'price',
					'label' => 'Price',
					'type'  => 'text',
					'placeholder' => '$45, Free, By donation…',
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'   => 'field_oria_event_venue',
					'name'  => 'venue',
					'label' => 'Venue',
					'type'  => 'text',
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_oria_event_description',
					'name'         => 'event_description',
					'label'        => 'Description',
					'type'         => 'wysiwyg',
					'tabs'         => 'visual',
					'toolbar'      => 'basic',
					'media_upload' => 0,
					'instructions' => 'What happens, who it suits, what to bring. Plain description only — no medical or outcome claims.',
				),
				array(
					'key'           => 'field_oria_event_gallery',
					'name'          => 'event_gallery',
					'label'         => 'Photo gallery',
					'type'          => 'gallery',
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'instructions'  => 'The first image leads the page when no main photo is set; the rest show as a grid.',
				),
				array(
					'key'           => 'field_oria_event_listing',
					'name'          => 'listing',
					'label'         => 'Run by',
					'type'          => 'post_object',
					'post_type'     => array( 'listing' ),
					'return_format' => 'id',
					'allow_null'    => true,
					'instructions'  => 'The listing that runs this event, if it has one.',
				),
				/*
				 * A placement lever, so admin-only: hidden from practitioners
				 * and write-locked for them in ownership.php. It orders; it does
				 * not place. The event still shows only in the categories and
				 * city it belongs to -- pinning a sound bath cannot put it on
				 * the Fitness page -- and drops off by itself when it is over.
				 */
				array(
					'key'          => 'field_oria_event_priority',
					'name'         => 'category_priority',
					'label'        => 'Priority on category pages',
					'type'         => 'true_false',
					'ui'           => 1,
					'message'      => 'Show this event first on its category pages',
					'instructions' => "Puts it ahead of every other event in the What's on section of each category it is tagged with, Featured practice events included, until it finishes. It still appears only in its own categories and city.",
				),
				array(
					'key'   => 'field_oria_event_booking',
					'name'  => 'booking_url',
					'label' => 'Booking / details link',
					'type'  => 'url',
				),
			),
		)
	);
}

/** The journal extras: the animated pull quote and the photo essay strip. */
function register_journal_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_journal',
			'title'    => 'Article extras',
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
			'position' => 'normal',
			'fields'   => array(
				/*
				 * A journey: an ordered day, each step pointing at a real listing.
				 * The step names the time and what it is; the name, suburb, price
				 * and photo are read from the listing when the page renders, so a
				 * journey cannot quietly go stale the way a hand-typed itinerary
				 * does -- a studio that closes disappears from every journey at once.
				 */
				array(
					'key'          => 'field_oria_journey',
					'name'         => 'journey',
					'label'        => 'Journey (day at a glance)',
					'type'         => 'repeater',
					'button_label' => 'Add a step',
					'layout'       => 'table',
					'instructions' => 'One row per step, in order. Place it in the article with the [oria_journey] shortcode. A step with no listing still shows, which is useful for something the directory does not hold.',
					'sub_fields'   => array(
						array(
							'key'         => 'field_oria_journey_time',
							'name'        => 'time',
							'label'       => 'Time',
							'type'        => 'text',
							'placeholder' => '7:00am',
							'wrapper'     => array( 'width' => '14' ),
						),
						array(
							'key'          => 'field_oria_journey_icon',
							'name'         => 'icon',
							'label'        => 'Icon',
							'type'         => 'text',
							'maxlength'    => 4,
							'placeholder'  => '🌊',
							'instructions' => 'Optional emoji.',
							'wrapper'      => array( 'width' => '10' ),
						),
						array(
							'key'         => 'field_oria_journey_label',
							'name'        => 'label',
							'label'       => 'Step',
							'type'        => 'text',
							'placeholder' => 'Ocean dip',
							'wrapper'     => array( 'width' => '22' ),
						),
						array(
							'key'           => 'field_oria_journey_listing',
							'name'          => 'listing',
							'label'         => 'Where',
							'type'          => 'post_object',
							'post_type'     => array( 'listing' ),
							'return_format' => 'id',
							'ui'            => 1,
							'allow_null'    => 1,
							'wrapper'       => array( 'width' => '32' ),
						),
						array(
							'key'         => 'field_oria_journey_note',
							'name'        => 'note',
							'label'       => 'Note',
							'type'        => 'text',
							'maxlength'   => 90,
							'placeholder' => 'Park on Marine Parade',
							'wrapper'     => array( 'width' => '22' ),
						),
					),
				),
				array(
					'key'          => 'field_oria_pull_quote',
					'name'         => 'pull_quote',
					'label'        => 'Pull quote',
					'type'         => 'textarea',
					'rows'         => 2,
					'new_lines'    => '',
					'instructions' => 'One line worth remembering. Shown large under the cover image with a slow word-by-word reveal.',
				),
				array(
					'key'         => 'field_oria_pull_quote_by',
					'name'        => 'pull_quote_by',
					'label'       => 'Quote attribution',
					'type'        => 'text',
					'placeholder' => 'e.g. Sarah, breathwork facilitator in Fremantle',
					'wrapper'     => array( 'width' => '50' ),
				),
				array(
					'key'           => 'field_oria_journal_gallery',
					'name'          => 'journal_gallery',
					'label'         => 'Photo essay',
					'type'          => 'gallery',
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'instructions'  => 'Extra images shown as a magazine strip after the article. Captions come from each image\'s Caption field in the media library.',
				),
				array(
					'key'           => 'field_oria_journal_practices',
					'name'          => 'related_practices',
					'label'         => 'Related practices',
					'type'          => 'taxonomy',
					'taxonomy'      => 'practice',
					'field_type'    => 'multi_select',
					'return_format' => 'id',
					'add_term'      => false,
					'save_terms'    => false,
					'load_terms'    => false,
					'instructions'  => 'Directory categories shown in the article\'s sidebar ("Try it in person"). Leave empty to auto-match from the article\'s topic.',
				),
				array(
					'key'           => 'field_oria_journal_areas',
					'name'          => 'related_areas',
					'label'         => 'Related areas',
					'type'          => 'taxonomy',
					'taxonomy'      => 'area',
					'field_type'    => 'multi_select',
					'return_format' => 'id',
					'add_term'      => false,
					'save_terms'    => false,
					'load_terms'    => false,
					'instructions'  => 'Keeps the sidebar local: an article about retreats in the Perth Hills should not offer one in Fremantle. Choosing a region covers its suburbs too. Leave empty to auto-match from the title, or to draw on the whole metro.',
				),
			),
		)
	);
}

/** Author profile extras, shown in the byline and author card on articles. */
function register_author_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_author',
			'title'    => 'Journal author profile',
			'location' => array(
				array(
					array(
						'param'    => 'user_role',
						'operator' => '==',
						'value'    => 'administrator',
					),
				),
				array(
					array(
						'param'    => 'user_role',
						'operator' => '==',
						'value'    => 'editor',
					),
				),
			),
			'fields'   => array(
				array(
					'key'          => 'field_oria_author_role',
					'name'         => 'author_role',
					'label'        => 'Byline role',
					'type'         => 'text',
					'placeholder'  => 'e.g. Founding editor',
					'instructions' => 'Shown under your name on articles you write.',
				),
				array(
					'key'           => 'field_oria_author_photo',
					'name'          => 'author_photo',
					'label'         => 'Profile photo',
					'type'          => 'image',
					'return_format' => 'id',
					'preview_size'  => 'thumbnail',
					'instructions'  => 'A square photo works best. Without one, articles show your initials instead.',
				),
			),
		)
	);
}

/**
 * A Best Of guide: intro, category, the picks, how they were chosen.
 *
 * The picks repeater is the only place a badge is ever recorded. Its rows are
 * dragged into order -- that IS the ranking -- and BestOf\entries() reads
 * them in that order, so there is no rank number to fall out of step.
 */
function register_best_of_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_best_of',
			'title'    => 'Best Of guide',
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => \Oria\Core\PostTypes\BEST_OF,
					),
				),
			),
			'position' => 'normal',
			'fields'   => array(
				array(
					'key'   => 'field_oria_bo_tab_guide',
					'label' => 'Guide',
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_oria_bo_intro',
					'name'         => 'guide_intro',
					'label'        => 'Introduction',
					'type'         => 'textarea',
					'rows'         => 4,
					'new_lines'    => '',
					'instructions' => 'Two or three sentences under the title: who this guide is for and what it narrows down. The Excerpt (right-hand column) is the shorter text used on cards.',
				),
				array(
					'key'          => 'field_oria_bo_quick',
					'name'         => 'quick_answer',
					'label'        => 'Quick answer',
					'type'         => 'textarea',
					'rows'         => 3,
					'new_lines'    => '',
					'instructions' => '40 to 80 words that answer the question on their own, naming the picks. Shown in a box under the intro.',
				),
				array(
					'key'            => 'field_oria_bo_reviewed',
					'name'           => 'editorially_reviewed_date',
					'label'          => 'Editorially reviewed',
					'type'           => 'date_picker',
					'display_format' => 'j F Y',
					'return_format'  => 'Y-m-d',
					'instructions'   => 'The date you last checked the picks and prices. Shown as "Reviewed September 2026"; left empty, the page says "Updated" with the save date instead.',
					'wrapper'        => array( 'width' => '40' ),
				),
				array(
					'key'           => 'field_oria_bo_category',
					'name'          => 'guide_category',
					'label'         => 'Guide category',
					'type'          => 'select',
					'choices'       => \Oria\Core\BestOf\CATEGORIES,
					'default_value' => 'beginners',
					'return_format' => 'value',
					'instructions'  => 'Groups the guide on the /best/ hub and picks its "You might also like" neighbours.',
					'wrapper'       => array( 'width' => '40' ),
				),
				array(
					'key'           => 'field_oria_bo_practice',
					'name'          => 'guide_practice',
					'label'         => 'Directory category',
					'type'          => 'taxonomy',
					'taxonomy'      => 'practice',
					'field_type'    => 'select',
					'allow_null'    => 1,
					'return_format' => 'id',
					'add_term'      => false,
					'save_terms'    => false,
					'load_terms'    => false,
					'instructions'  => 'The category these picks come from, e.g. Yoga. Adds an "Explore all yoga in Perth" link to the guide.',
					'wrapper'       => array( 'width' => '40' ),
				),
				array(
					'key'          => 'field_oria_bo_featured',
					'name'         => 'featured_guide',
					'label'        => 'Feature on the hub',
					'type'         => 'true_false',
					'ui'           => 1,
					'instructions' => 'Large card at the top of /best/.',
					'wrapper'      => array( 'width' => '20' ),
				),

				array(
					'key'   => 'field_oria_bo_tab_picks',
					'label' => 'Picks',
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_oria_bo_entries',
					'name'         => 'best_of_entries',
					'label'        => 'Recommended practices',
					'type'         => 'repeater',
					'button_label' => 'Add a pick',
					'layout'       => 'block',
					'collapsed'    => 'field_oria_bo_entry_listing',
					'instructions' => 'Drag rows to order them; the first row is your top pick. Each pick gives the practice a badge on its profile and cards, linked back to this guide, for as long as it stays in the list.',
					'sub_fields'   => array(
						array(
							'key'           => 'field_oria_bo_entry_listing',
							'name'          => 'listing',
							'label'         => 'Practice',
							'type'          => 'post_object',
							'post_type'     => array( 'listing' ),
							'post_status'   => array( 'publish' ),
							'return_format' => 'id',
							'ui'            => 1,
							'required'      => 1,
							'wrapper'       => array( 'width' => '40' ),
						),
						array(
							'key'           => 'field_oria_bo_entry_award',
							'name'          => 'award',
							'label'         => 'Award',
							'type'          => 'select',
							'choices'       => \Oria\Core\BestOf\AWARDS,
							'default_value' => 'best_for_beginners',
							'return_format' => 'value',
							'wrapper'       => array( 'width' => '30' ),
						),
						array(
							'key'          => 'field_oria_bo_entry_label',
							'name'         => 'award_label',
							'label'        => 'Badge wording (optional)',
							'type'         => 'text',
							'maxlength'    => 32,
							'placeholder'  => 'Best infrared sauna',
							'instructions' => "Replaces the award's standard label on this badge only.",
							'wrapper'      => array( 'width' => '30' ),
						),
						array(
							'key'          => 'field_oria_bo_entry_reason',
							'name'         => 'reason',
							'label'        => 'Why we chose it',
							'type'         => 'textarea',
							'rows'         => 3,
							'new_lines'    => '',
							'required'     => 1,
							'instructions' => 'One or two sentences a reader can act on. Say what the practice offers a beginner, not how good it is.',
						),
						array(
							'key'          => 'field_oria_bo_entry_best_for',
							'name'         => 'best_for',
							'label'        => 'Best for',
							'type'         => 'text',
							'maxlength'    => 40,
							'placeholder'  => 'First timers',
							'instructions' => 'A few words for the comparison table.',
							'wrapper'      => array( 'width' => '30' ),
						),
						array(
							'key'          => 'field_oria_bo_entry_highlights',
							'name'         => 'highlights',
							'label'        => 'Highlights',
							'type'         => 'text',
							'placeholder'  => 'Beginner classes, Equipment supplied, Intro offer',
							'instructions' => 'Up to four, separated by commas. Shown under the reason and in the table.',
							'wrapper'      => array( 'width' => '50' ),
						),
						array(
							'key'          => 'field_oria_bo_entry_price',
							'name'         => 'price_note',
							'label'        => 'Price note (optional)',
							'type'         => 'text',
							'placeholder'  => 'From $35 — price checked September 2026',
							'instructions' => 'Replaces the automatic "From $X" from the listing. Leave empty to use the listing price, or "Check current pricing" when unknown.',
							'wrapper'      => array( 'width' => '40' ),
						),
						array(
							'key'          => 'field_oria_bo_entry_fact',
							'name'         => 'fact_label',
							'label'        => 'Fact label (optional)',
							'type'         => 'text',
							'maxlength'    => 24,
							'placeholder'  => 'Under $50',
							'instructions' => 'A plain fact, not an award: "Under $50", "Sauna + ice bath". Shown as a grey pill, distinct from the badge.',
							'wrapper'      => array( 'width' => '30' ),
						),
						array(
							'key'          => 'field_oria_bo_entry_sessions',
							'name'         => 'sessions',
							'label'        => 'Session lengths (optional)',
							'type'         => 'text',
							'placeholder'  => '45, 60 and 90 min',
							'instructions' => 'As the practice lists them. Shown on the pick and in the table.',
							'wrapper'      => array( 'width' => '40' ),
						),
						array(
							'key'           => 'field_oria_bo_entry_rebate',
							'name'          => 'rebate',
							'label'         => 'Private health (optional)',
							'type'          => 'select',
							'choices'       => array(
								''           => '— not shown —',
								'confirmed'  => 'Practice states rebates available',
								'provider'   => 'Check with provider',
								'not_listed' => 'Not listed',
								'none'       => 'Not available',
							),
							'default_value' => '',
							'return_format' => 'value',
							'instructions'  => 'Only what the practice itself says. Choosing anything here adds the private-health column and footnote to the table.',
							'wrapper'       => array( 'width' => '30' ),
						),
						array(
							'key'          => 'field_oria_bo_entry_spot',
							'name'         => 'spotlight',
							'label'        => 'Spotlight (optional)',
							'type'         => 'text',
							'maxlength'    => 24,
							'placeholder'  => 'Best overall',
							'instructions' => 'Up to three picks can be highlighted at the top of the guide: "Best overall", "Best for beginners", "Best value".',
							'wrapper'      => array( 'width' => '30' ),
						),
						array(
							'key'          => 'field_oria_bo_entry_lead',
							'name'         => 'lead_badge',
							'label'        => 'Lead badge',
							'type'         => 'true_false',
							'ui'           => 1,
							'instructions' => 'A card shows one badge. If this practice is in several guides, tick here to make this the one.',
							'wrapper'      => array( 'width' => '20' ),
						),
					),
				),

				array(
					'key'   => 'field_oria_bo_tab_method',
					'label' => 'How we chose',
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_oria_bo_method',
					'name'         => 'methodology',
					'label'        => 'How we chose',
					'type'         => 'textarea',
					'rows'         => 4,
					'new_lines'    => 'br',
					'placeholder'  => 'We looked for studios offering dedicated beginner classes, supportive instruction, clear class descriptions and an easy way for a first-time visitor to book.',
					'instructions' => 'Say what was considered: selected, reviewed, shortlisted. Only say a place was visited or tested if it was.',
				),
				array(
					'key'          => 'field_oria_bo_note',
					'name'         => 'editor_note',
					'label'        => "Editor's note (optional)",
					'type'         => 'textarea',
					'rows'         => 2,
					'new_lines'    => '',
					'instructions' => 'A short aside shown beside the picks, e.g. what to bring or how to read the prices.',
				),
				array(
					'key'          => 'field_oria_bo_faq',
					'name'         => 'guide_faq',
					'label'        => 'Questions',
					'type'         => 'repeater',
					'button_label' => 'Add a question',
					'layout'       => 'block',
					'collapsed'    => 'field_oria_bo_faq_q',
					'instructions' => 'Shown as an accordion and, from two questions up, as FAQ structured data.',
					'sub_fields'   => array(
						array(
							'key'      => 'field_oria_bo_faq_q',
							'name'     => 'question',
							'label'    => 'Question',
							'type'     => 'text',
							'required' => 1,
						),
						array(
							'key'       => 'field_oria_bo_faq_a',
							'name'      => 'answer',
							'label'     => 'Answer',
							'type'      => 'textarea',
							'rows'      => 3,
							'new_lines' => '',
							'required'  => 1,
						),
					),
				),
				array(
					'key'          => 'field_oria_bo_choose',
					'name'         => 'guide_choose',
					'label'        => 'Which one should you choose?',
					'type'         => 'repeater',
					'button_label' => 'Add a line',
					'layout'       => 'table',
					'instructions' => 'One line per situation: "Choose [practice] if …". Turns the list into a decision.',
					'sub_fields'   => array(
						array(
							'key'           => 'field_oria_bo_choose_listing',
							'name'          => 'listing',
							'label'         => 'Choose',
							'type'          => 'post_object',
							'post_type'     => array( 'listing' ),
							'post_status'   => array( 'publish' ),
							'return_format' => 'id',
							'ui'            => 1,
							'required'      => 1,
							'wrapper'       => array( 'width' => '40' ),
						),
						array(
							'key'         => 'field_oria_bo_choose_when',
							'name'        => 'when',
							'label'       => 'if …',
							'type'        => 'text',
							'placeholder' => "you're completely new to sound baths",
							'required'    => 1,
							'wrapper'     => array( 'width' => '60' ),
						),
					),
				),
				array(
					'key'          => 'field_oria_bo_links',
					'name'         => 'guide_links',
					'label'        => 'Keep exploring',
					'type'         => 'repeater',
					'button_label' => 'Add a link',
					'layout'       => 'table',
					'instructions' => 'Related pages on this site: a comparison, a journal guide, a journey. The directory category link is added automatically.',
					'sub_fields'   => array(
						array(
							'key'     => 'field_oria_bo_links_label',
							'name'    => 'label',
							'label'   => 'Label',
							'type'    => 'text',
							'wrapper' => array( 'width' => '45' ),
						),
						array(
							'key'     => 'field_oria_bo_links_url',
							'name'    => 'url',
							'label'   => 'Link',
							'type'    => 'url',
							'wrapper' => array( 'width' => '55' ),
						),
					),
				),
			),
		)
	);
}
