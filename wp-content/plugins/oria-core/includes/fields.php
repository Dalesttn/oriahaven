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

				// --- Timetable ----------------------------------------------
				array(
					'key'       => 'field_oria_tab_timetable',
					'label'     => 'Timetable',
					'type'      => 'tab',
					'placement' => 'left',
				),
				array(
					'key'          => 'field_oria_timetable',
					'name'         => 'timetable',
					'label'        => 'Timetable',
					'type'         => 'repeater',
					'button_label' => 'Add session',
					'layout'       => 'table',
					'sub_fields'   => array(
						array(
							'key'   => 'field_oria_tt_when',
							'name'  => 'when',
							'label' => 'When',
							'type'  => 'text',
							'placeholder' => 'Mon–Fri 6.30am',
						),
						array(
							'key'   => 'field_oria_tt_session',
							'name'  => 'session',
							'label' => 'Session',
							'type'  => 'text',
							'placeholder' => 'Morning sitting · 25 min, silent',
						),
						array(
							'key'   => 'field_oria_tt_price',
							'name'  => 'price',
							'label' => 'Price',
							'type'  => 'text',
							'placeholder' => '$15',
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
					'label'        => 'Good for',
					'type'         => 'text',
					'placeholder'  => 'Complete beginners',
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
					'key'          => 'field_oria_google_place_id',
					'name'         => 'google_place_id',
					'label'        => 'Google place ID',
					'type'         => 'text',
					'instructions' => 'Filled automatically the first time Places photos are fetched. If the wrong venue matched, paste the correct place ID here. Enter "off" to disable Places photos for this listing.',
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
