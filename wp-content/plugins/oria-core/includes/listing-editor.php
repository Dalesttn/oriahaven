<?php
/**
 * The listing manager an owner actually uses.
 *
 * A practitioner who claims their listing has, until now, been handed a
 * trimmed WordPress edit screen: a Publish box, a permalink, a left rail
 * of ACF tabs and a column of fields whose names are ours rather than
 * theirs. It works, and it is the wrong thing to give somebody who runs a
 * massage room and opens this twice a year.
 *
 * This file is the one description of what an owner may edit. Rendering,
 * the permission check, validation, the completion score, what needs
 * checking before it goes live and what the audit log calls it all read
 * from the same array, because the alternative -- a form that lists the
 * fields and a save handler that lists them again -- is how a field ends
 * up editable in one place and not the other.
 *
 * What it deliberately does NOT do is invent a second copy of the data.
 * Every value here is the same post meta the public templates already
 * read, written through ACF so repeaters keep their shape. An owner
 * editing their hours in My Oria and an administrator editing them in
 * wp-admin are editing one row.
 *
 * @package Oria_Core
 */

declare(strict_types=1);

namespace Oria\Core\ListingEditor;

use Oria\Core\Ownership;
use Oria\Core\Tiers;
use Oria\Core\Analytics;
use Oria\Core\Audit;

/** Where a proposed value waits while somebody checks it. */
const PENDING_META = '_oria_pending';

/** How many improvements the dashboard offers at once. Three is a to-do list; nine is a telling-off. */
const MAX_NUDGES = 3;

function bootstrap(): void {
	add_action( 'admin_post_oria_listing_save', __NAMESPACE__ . '\handle_save' );
	add_action( 'admin_post_oria_listing_withdraw', __NAMESPACE__ . '\handle_withdraw' );
}

/* ------------------------------------------------------------- the registry */

/**
 * Every section an owner can open, and every field inside it.
 *
 * Field keys:
 *   name      the ACF/meta name -- the same one the public template reads
 *   type      how to render it; the renderer and the sanitiser both switch on this
 *   label     what the owner is asked, in their words rather than ours
 *   help      one line under the field, only where it prevents a mistake
 *   review    true where a change should be checked before it goes live
 *   sub       for repeaters: the columns of one row
 *
 * Nothing administrative appears here at all. claim_status, admin_featured,
 * claimed_by, google_place_id, hide_experience and category_priority are
 * absent by design -- not hidden in the template, absent from the registry,
 * so no request can write one however it is crafted.
 *
 * @return array<string, array<string, mixed>>
 */
function sections(): array {
	$reasons   = function_exists( '\Oria\Core\Reasons\vocabulary' ) ? \Oria\Core\Reasons\vocabulary() : array();
	$amenities = function_exists( '\Oria\Core\Amenities\vocabulary' ) ? \Oria\Core\Amenities\vocabulary() : array();

	return array(

		'basics' => array(
			'label' => __( 'Business basics', 'oria' ),
			'blurb' => __( 'What you do, and who it is for.', 'oria' ),
			'icon'  => 'basics',
			'fields' => array(
				/*
				 * Two texts, because the page uses them differently and an
				 * owner asked where "What it's like" came from. The short one
				 * is the excerpt: its first sentence leads the page under the
				 * name. The long one is the post body: it IS the "What it's
				 * like" section. When there is no long one, the rest of the
				 * short one stands in.
				 */
				array(
					'name'  => 'listing_description',
					'type'  => 'textarea',
					'rows'  => 4,
					'label' => __( 'Your introduction', 'oria' ),
					'help'  => __( 'Two or three sentences. The first one leads your page, right under your name, so make it the reason to come.', 'oria' ),
					'hint'  => __( 'Around 40 to 80 words.', 'oria' ),
				),
				array(
					'name'  => 'listing_body',
					'type'  => 'prose',
					'rows'  => 10,
					'label' => __( 'What it\'s like', 'oria' ),
					'help'  => __( 'The full description, shown under the "What it\'s like" heading on your page. The room, the format, the people, what a first visit is like. Blank lines make paragraphs.', 'oria' ),
					'hint'  => __( 'Describe what happens there, never what it treats or fixes.', 'oria' ),
				),
				array(
					'name'  => 'good_for',
					'type'  => 'textarea',
					'rows'  => 3,
					'max'   => 300,
					'label' => __( 'What you are especially good at', 'oria' ),
					'help'  => __( 'The room, the format, the teaching. Describe what happens there -- not what it treats or fixes.', 'oria' ),
				),
				array(
					'name'    => 'kind',
					'type'    => 'radios',
					'label'   => __( 'How do people come to you?', 'oria' ),
					'choices' => array(
						'practice' => __( 'They book a session with someone', 'oria' ),
						'place'    => __( 'They turn up -- no appointment', 'oria' ),
						'spot'     => __( 'It is free and public, with nobody to contact', 'oria' ),
					),
				),
				array(
					'name'    => 'format',
					'type'    => 'radios',
					'label'   => __( 'Where do sessions happen?', 'oria' ),
					'choices' => array(
						'in-person' => __( 'In person', 'oria' ),
						'online'    => __( 'Online', 'oria' ),
						'both'      => __( 'Both', 'oria' ),
					),
				),
			),
		),

		'services' => array(
			'label' => __( 'What you offer', 'oria' ),
			'blurb' => __( 'The things someone can actually book or come to.', 'oria' ),
			'icon'  => 'services',
			'fields' => array(
				array(
					'name'   => 'services',
					'type'   => 'repeater',
					'label'  => __( 'Your services', 'oria' ),
					'help'   => __( 'One per line item -- the names you use on your own price list. These appear on your profile and help people find you.', 'oria' ),
					'add'    => __( 'Add a service', 'oria' ),
					'single' => __( 'service', 'oria' ),
					'sub'    => array(
						array( 'name' => 'name', 'type' => 'text', 'label' => __( 'Service name', 'oria' ), 'placeholder' => __( 'Remedial massage', 'oria' ) ),
					),
				),
				array(
					'name'        => 'what_to_bring',
					'type'        => 'text',
					'max'         => 140,
					'label'       => __( 'Before you go', 'oria' ),
					'placeholder' => __( 'Bring a towel and water. Mats provided.', 'oria' ),
					'help'        => __( 'One line on what to bring or expect. Never a claim about what the session does.', 'oria' ),
				),
			),
		),

		'prices' => array(
			'label' => __( 'Prices', 'oria' ),
			'blurb' => __( 'A visible price is the question you stop being asked.', 'oria' ),
			'icon'  => 'prices',
			'fields' => array(
				array(
					'name'   => 'price_from',
					'type'   => 'money',
					'label'  => __( 'Your starting price', 'oria' ),
					'help'   => __( 'The lowest a standard session costs. Enter 0 if you are free or by donation.', 'oria' ),
					'hint'   => __( 'Shown publicly as "From $95".', 'oria' ),
				),
				array(
					'name'    => 'price_band',
					'type'    => 'select',
					'label'   => __( 'Roughly where do you sit?', 'oria' ),
					'blank'   => __( 'Prefer not to say', 'oria' ),
					'choices' => array(
						'Free' => __( 'Free or by donation', 'oria' ),
						'$'    => __( 'Under $25', 'oria' ),
						'$$'   => __( '$25 to $60', 'oria' ),
						'$$$'  => __( '$60 to $200', 'oria' ),
						'$$$$' => __( '$200 and above', 'oria' ),
					),
				),
				array(
					'name'  => 'duration_min',
					'type'  => 'number',
					'label' => __( 'How long is a standard session?', 'oria' ),
					'unit'  => __( 'minutes', 'oria' ),
					'help'  => __( 'The one most people book -- not the longest on your menu.', 'oria' ),
				),
				array(
					'name'    => 'group_size',
					'type'    => 'select',
					'label'   => __( 'Who else is in the room?', 'oria' ),
					'blank'   => __( 'Varies', 'oria' ),
					'help'    => __( 'The thing a nervous first-timer most wants to know.', 'oria' ),
					'choices' => array(
						'one-to-one' => __( 'Just you and the practitioner', 'oria' ),
						'small'      => __( 'A small group, under 12', 'oria' ),
						'class'      => __( 'A full class, 12 or more', 'oria' ),
						'solo'       => __( 'On your own -- a room or a machine', 'oria' ),
					),
				),
				array(
					'name'   => 'packages',
					'type'   => 'repeater',
					'label'  => __( 'Packages and passes', 'oria' ),
					'help'   => __( 'Multi-session passes, intro offers, memberships -- anything that is not a single session.', 'oria' ),
					'add'    => __( 'Add a package', 'oria' ),
					'single' => __( 'package', 'oria' ),
					/*
					 * Every column ACF has for a package, not only the ones an
					 * owner is likely to fill. A repeater row is written whole:
					 * a column the form does not carry is saved as empty, so a
					 * package image or booking link set on the admin screen
					 * would have been wiped by the first save from here.
					 */
					'layout' => 'card',
					'sub'    => array(
						array( 'name' => 'title', 'type' => 'text', 'label' => __( 'Name', 'oria' ), 'placeholder' => __( 'Five-class pass', 'oria' ), 'span' => 'half' ),
						array( 'name' => 'price', 'type' => 'text', 'label' => __( 'Price', 'oria' ), 'placeholder' => __( '$140', 'oria' ), 'span' => 'half' ),
						array( 'name' => 'description', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'What it includes', 'oria' ), 'span' => 'full' ),
						array( 'name' => 'booking_url', 'type' => 'url', 'label' => __( 'Booking link for this package', 'oria' ), 'placeholder' => 'https://', 'span' => 'two-thirds', 'help' => __( 'Optional. Only if it books somewhere different from your main link.', 'oria' ) ),
						array( 'name' => 'image', 'type' => 'image', 'label' => __( 'Image', 'oria' ), 'span' => 'third' ),
					),
				),
			),
		),

		'hours' => array(
			'label' => __( 'Hours and booking', 'oria' ),
			'blurb' => __( 'Stale hours are the fastest way to waste somebody\'s trip.', 'oria' ),
			'icon'  => 'hours',
			'fields' => array(
				array(
					'name'   => 'opening_hours',
					'type'   => 'repeater',
					'label'  => __( 'When you are open', 'oria' ),
					'help'   => __( 'Group the days however you actually work -- "Mon to Fri", "Saturday", "Sunday closed".', 'oria' ),
					'add'    => __( 'Add hours', 'oria' ),
					'single' => __( 'row', 'oria' ),
					'sub'    => array(
						array( 'name' => 'days', 'type' => 'text', 'label' => __( 'Days', 'oria' ), 'placeholder' => __( 'Mon to Fri', 'oria' ) ),
						array( 'name' => 'hours', 'type' => 'text', 'label' => __( 'Hours', 'oria' ), 'placeholder' => __( '6.00am to 8.00pm', 'oria' ) ),
					),
				),
				array(
					'name'        => 'booking_url',
					'type'        => 'url',
					'label'       => __( 'Booking link', 'oria' ),
					'placeholder' => 'https://',
					'help'        => __( 'Where the Book button sends people. Paste the page where they choose a time.', 'oria' ),
				),
				array(
					'name'        => 'next_session',
					'type'        => 'text',
					'label'       => __( 'What is on next', 'oria' ),
					'placeholder' => __( 'Tomorrow 6.30am', 'oria' ),
					'help'        => __( 'Optional. Useful if somebody ready today could come today.', 'oria' ),
				),
			),
		),

		'location' => array(
			'label' => __( 'Location and contact', 'oria' ),
			'blurb' => __( 'How people reach you, and how they find the door.', 'oria' ),
			'icon'  => 'location',
			'fields' => array(
				array(
					'name'   => 'address',
					'type'   => 'text',
					'label'  => __( 'Street address', 'oria' ),
					'review' => true,
					'help'   => __( 'Moving premises? We check address changes before they go live, so your listing keeps pointing people to the right door in the meantime.', 'oria' ),
				),
				array(
					'name'        => 'phone',
					'type'        => 'tel',
					'label'       => __( 'Phone', 'oria' ),
					'placeholder' => '(08) 9000 0000',
					'public'      => true,
				),
				array(
					'name'   => 'email',
					'type'   => 'email',
					'label'  => __( 'Email', 'oria' ),
					'public' => true,
					'help'   => __( 'Shown on your profile so people can write to you directly.', 'oria' ),
				),
				array(
					'name'        => 'website',
					'type'        => 'url',
					'label'       => __( 'Website', 'oria' ),
					'placeholder' => 'https://',
					'public'      => true,
				),
				array(
					'name'        => 'transit',
					'type'        => 'text',
					'label'       => __( 'Getting there by public transport', 'oria' ),
					'placeholder' => __( 'Perth Underground, 6 min walk', 'oria' ),
				),
				array(
					'name'        => 'parking',
					'type'        => 'text',
					'label'       => __( 'Parking', 'oria' ),
					'placeholder' => __( 'Free street parking on Bannister Street', 'oria' ),
				),
			),
		),

		'photos' => array(
			'label' => __( 'Photos', 'oria' ),
			'blurb' => __( 'The room is the first thing people look at.', 'oria' ),
			'icon'  => 'photos',
			'fields' => array(
				array(
					'name'  => 'gallery',
					'type'  => 'gallery',
					'label' => __( 'Your photos', 'oria' ),
					'help'  => __( 'The space, the experience, the people. Avoid flyers, price lists and pictures that are mostly text.', 'oria' ),
					'hint'  => __( 'The first photo leads: it is the one shown on your profile header and on every card you appear in.', 'oria' ),
				),
			),
		),

		'suits' => array(
			'label' => __( 'Who it suits', 'oria' ),
			'blurb' => __( 'Only tick what is genuinely true of your sessions today.', 'oria' ),
			'icon'  => 'suits',
			'fields' => array(
				array(
					'name'    => 'reasons',
					'type'    => 'checks',
					'label'   => __( 'Why do people come to you?', 'oria' ),
					'help'    => __( 'Anything left unticked simply is not shown. It is never displayed as a "no".', 'oria' ),
					'choices' => $reasons,
				),
				array(
					'name'    => 'amenities',
					'type'    => 'checks',
					'label'   => __( 'What is in the building?', 'oria' ),
					'help'    => __( 'Tick only what you actually have. Step-free access and an accessible toilet matter to people planning a first visit.', 'oria' ),
					'choices' => $amenities,
				),
			),
		),

		'answers' => array(
			'label' => __( 'Quick answers', 'oria' ),
			'blurb' => __( 'The questions you answer on the phone, answered on the page instead.', 'oria' ),
			'icon'  => 'answers',
			'fields' => array(
				array(
					'name'   => 'faq',
					'type'   => 'repeater',
					'label'  => __( 'Your questions and answers', 'oria' ),
					'help'   => __( 'Do I need to book? What should I bring? Is parking easy? Write the answer you would give on the phone.', 'oria' ),
					'add'    => __( 'Add a question', 'oria' ),
					'single' => __( 'question', 'oria' ),
					'sub'    => array(
						array( 'name' => 'question', 'type' => 'text', 'label' => __( 'Question', 'oria' ), 'placeholder' => __( 'Do I need to book?', 'oria' ) ),
						array( 'name' => 'answer', 'type' => 'textarea', 'rows' => 3, 'label' => __( 'Answer', 'oria' ) ),
					),
				),
			),
		),

		'team' => array(
			'label' => __( 'Your team', 'oria' ),
			'blurb' => __( 'Named, qualified people are the most convincing thing on the page.', 'oria' ),
			'icon'  => 'team',
			'fields' => array(
				array(
					'name'     => 'team',
					'type'     => 'repeater',
					'layout'   => 'card',
					'max_rows' => Tiers\TEAM_MAX,
					'label'    => __( 'Practitioners', 'oria' ),
					'help'     => __( 'Facts only: what somebody does, what they hold, where they are registered. Never what a treatment can achieve.', 'oria' ),
					'add'      => __( 'Add a practitioner', 'oria' ),
					'plan_note' => 'team',
					'single'   => __( 'practitioner', 'oria' ),
					'sub'      => array(
						array( 'name' => 'name', 'type' => 'text', 'label' => __( 'Name', 'oria' ), 'placeholder' => __( 'Clare Keating', 'oria' ), 'span' => 'half' ),
						array( 'name' => 'role', 'type' => 'text', 'label' => __( 'Role here', 'oria' ), 'placeholder' => __( 'Remedial massage therapist', 'oria' ), 'span' => 'half' ),
						array( 'name' => 'photo', 'type' => 'image', 'label' => __( 'Photo', 'oria' ), 'help' => __( 'A headshot. It does not count towards your photo gallery.', 'oria' ) ),
						array( 'name' => 'years', 'type' => 'number', 'label' => __( 'Years practising', 'oria' ), 'span' => 'third' ),
						array( 'name' => 'languages', 'type' => 'text', 'label' => __( 'Languages besides English', 'oria' ), 'placeholder' => __( 'Italian, Auslan', 'oria' ), 'span' => 'two-thirds' ),
						array( 'name' => 'quals', 'type' => 'textarea', 'rows' => 3, 'label' => __( 'Qualifications', 'oria' ), 'help' => __( 'One per line, e.g. "Dip. Remedial Massage (2016)". What they hold, not what they treat.', 'oria' ), 'span' => 'full' ),
						array( 'name' => 'reg_body', 'type' => 'text', 'label' => __( 'Registered with', 'oria' ), 'placeholder' => 'AHPRA, ATMS, Yoga Australia', 'span' => 'third' ),
						array( 'name' => 'reg_id', 'type' => 'text', 'label' => __( 'Registration number', 'oria' ), 'span' => 'third' ),
						array( 'name' => 'reg_url', 'type' => 'url', 'label' => __( 'Link to the register', 'oria' ), 'placeholder' => 'https://', 'span' => 'third' ),
						array( 'name' => 'specialties', 'type' => 'multi', 'label' => __( 'Specialises in', 'oria' ), 'choices' => 'specialties', 'span' => 'full',
							'help' => __( 'Picked from what this listing already offers. Add a service first if something is missing.', 'oria' ) ),
						array( 'name' => 'bio', 'type' => 'textarea', 'rows' => 3, 'max' => 300, 'label' => __( 'Short bio', 'oria' ), 'span' => 'full',
							'help' => __( 'A couple of sentences on how they work. We cannot publish claims about treating conditions.', 'oria' ) ),
						array( 'name' => 'consent', 'type' => 'toggle', 'span' => 'full',
							'label' => __( 'This person has agreed to appear on Oria Haven', 'oria' ),
							'help'  => __( 'Required. Publishing somebody\'s name, photo and history is publishing their personal information, and it needs their say-so. Anyone left unticked stays off the page.', 'oria' ) ),
					),
				),
			),
		),

		'offers' => array(
			'label' => __( 'Offers and social', 'oria' ),
			'blurb' => __( 'An offer shows on your profile and on every card you appear in.', 'oria' ),
			'icon'  => 'offers',
			'fields' => array(
				array( 'name' => 'offer_title', 'type' => 'text', 'label' => __( 'Offer', 'oria' ), 'placeholder' => __( 'First class free', 'oria' ) ),
				array( 'name' => 'offer_text', 'type' => 'textarea', 'rows' => 3, 'label' => __( 'What it includes', 'oria' ), 'help' => __( 'Any conditions worth knowing before they arrive.', 'oria' ) ),
				array( 'name' => 'offer_until', 'type' => 'date', 'label' => __( 'Runs until', 'oria' ), 'help' => __( 'We stop showing the offer after this date, so nobody turns up expecting something that has ended.', 'oria' ) ),
				array( 'name' => 'instagram_url', 'type' => 'url', 'label' => __( 'Instagram', 'oria' ), 'placeholder' => 'https://instagram.com/' ),
				array( 'name' => 'facebook_url', 'type' => 'url', 'label' => __( 'Facebook', 'oria' ), 'placeholder' => 'https://facebook.com/' ),
			),
		),
	);
}

/**
 * What a practitioner may be marked as specialising in.
 *
 * The listing's own service and specialty terms, and nothing else, so a
 * team profile cannot quietly claim something the practice does not offer.
 * Team\specialty_choices() does the same job on the admin screen, but it
 * reads the listing id out of the admin request, so it cannot be reused
 * here.
 *
 * @return array<int, string>
 */
function specialty_choices( int $listing ): array {
	$out = array();
	foreach ( array( 'service', 'specialty' ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		$terms = wp_get_post_terms( $listing, $taxonomy );
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			$out[ (int) $term->term_id ] = wp_specialchars_decode( $term->name, ENT_QUOTES );
		}
	}
	asort( $out );
	return $out;
}

/**
 * A field's choices, resolving the markers that depend on the listing.
 *
 * The registry is written without a listing in hand, so a field whose
 * options come from the listing's own data names its source as a string
 * and has it resolved here -- by the renderer and by the sanitiser alike,
 * so a posted option is validated against exactly the list that was drawn.
 *
 * @param array<string, mixed> $field
 * @return array<int|string, string>
 */
function choices_for( array $field ): array {
	$choices = $field['choices'] ?? array();
	if ( is_array( $choices ) ) {
		return $choices;
	}
	if ( 'specialties' === $choices ) {
		$listing = listing_for( get_current_user_id() );
		return $listing ? specialty_choices( $listing ) : array();
	}
	return array();
}

/** One section, or an empty array where the slug is not one of ours. */
function section( string $slug ): array {
	return sections()[ $slug ] ?? array();
}

/** The first section, used when no slug is given. */
function first_section(): string {
	return (string) array_key_first( sections() );
}

/**
 * Every field name an owner may write, flattened.
 *
 * The save handler tests against this rather than against whatever the
 * form posted, so a field removed from the registry stops being writable
 * everywhere at once.
 *
 * @return array<string, array<string, mixed>>
 */
function writable_fields(): array {
	$out = array();
	foreach ( sections() as $slug => $section ) {
		foreach ( $section['fields'] as $field ) {
			$field['section']    = $slug;
			$out[ $field['name'] ] = $field;
		}
	}
	return $out;
}

/* ------------------------------------------------------------ permissions */

/**
 * The listing this user owns, or 0.
 *
 * Every entry point calls this -- the templates to decide whether to draw
 * anything, the save handler before it writes. A control hidden in the
 * interface is not a permission check.
 *
 * Owning and paying are two different facts here, and this asks only about
 * the first. claimed_by records who the listing belongs to; claim_status
 * records what they pay. With billing switched on, approving a claim sets
 * claimed_by and leaves claim_status at 'unclaimed' until money arrives
 * (ClaimRequests\approve), and a cancelled subscription puts it back there
 * (Billing) -- so the free plan is where an approved owner normally
 * starts and where a lapsed one returns.
 *
 * This used to call Ownership\manages(), which also requires a paid state.
 * That locked every free-plan owner out of their own listing, which is the
 * exact opposite of what the tiers are for: free makes it right, paid
 * makes it work. What the plan decides is which FIELDS open, and
 * Tiers\field_editable() decides that per field, below.
 *
 * Ownership\manages() keeps its stricter meaning for the things that
 * really are paid-only, such as replying to reviews.
 */
function listing_for( int $user_id ): int {
	if ( $user_id < 1 ) {
		return 0;
	}
	$listing = Ownership\owned_listing( $user_id );
	if ( ! $listing ) {
		return 0;
	}
	return (int) get_post_meta( $listing, 'claimed_by', true ) === $user_id ? $listing : 0;
}

/** Whether this owner's plan lets them edit this field right now. */
function editable( int $listing, string $field ): bool {
	return Tiers\field_editable( $listing, $field );
}

/* ----------------------------------------------------------- reading values */

/** The ACF group whose fields belong to a listing. */
const GROUP = 'group_oria_listing';

/**
 * Field name to ACF field key, for the listing group only.
 *
 * ACF resolves a field by name reliably on an edit screen that has the
 * group attached, and not at all out here -- get_field( 'listing_description' )
 * on the front end returns nothing, silently, which is the worst possible
 * failure for a form that then reports "Saved".
 *
 * Scoped to one group on purpose. Names repeat across the groups -- the
 * event group also has booking_url, price and title -- so a map built from
 * every local field hands back whichever was registered last, and an owner
 * saving a booking link would write it to the wrong field entirely.
 *
 * @return array<string, string>
 */
function keys(): array {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map = array();
	if ( ! function_exists( 'acf_get_local_fields' ) ) {
		return $map;
	}
	foreach ( acf_get_local_fields( GROUP ) as $field ) {
		if ( ! empty( $field['name'] ) && ! empty( $field['key'] ) ) {
			$map[ (string) $field['name'] ] = (string) $field['key'];
		}
	}
	return $map;
}

/** One field's ACF key, or '' where the registry names a field ACF does not have. */
function key_for( string $name ): string {
	return keys()[ $name ] ?? '';
}

/**
 * The stored value, read the way ACF stores it.
 *
 * Always by key. Some of these are not post meta at all -- the description
 * is proxied onto post_excerpt by a filter hung on its key -- so reading
 * the meta table directly would miss it, and reading by name would miss it
 * twice over.
 */
function value( int $listing, string $name ) {
	// The long description is the post body, which has no ACF field at all.
	if ( 'listing_body' === $name ) {
		return (string) get_post_field( 'post_content', $listing, 'raw' );
	}
	$key = key_for( $name );
	if ( $key && function_exists( 'get_field' ) ) {
		return get_field( $key, $listing );
	}
	return get_post_meta( $listing, $name, true );
}

/** Write one field the same way, so the proxies fire. */
function write( int $listing, string $name, $value ): void {
	if ( 'listing_body' === $name ) {
		wp_update_post( array( 'ID' => $listing, 'post_content' => (string) $value ) );
		return;
	}
	$key = key_for( $name );
	if ( $key && function_exists( 'update_field' ) ) {
		update_field( $key, $value, $listing );
		return;
	}
	update_post_meta( $listing, $name, $value );
}

/** Whether a field counts as answered. 0 is an answer; '' and [] are not. */
function filled( int $listing, string $name ): bool {
	$v = value( $listing, $name );
	if ( is_array( $v ) ) {
		return array() !== $v;
	}
	if ( is_numeric( $v ) ) {
		return true;
	}
	return '' !== trim( (string) $v );
}

/* ------------------------------------------------------- the strength score */

/**
 * What a complete listing is worth, by area rather than by field.
 *
 * Weighted the way a visitor reads the page: the things that decide
 * whether somebody can choose you carry more than the things that decorate
 * the profile. The score exists to order the next three jobs -- it is
 * never a ranking promise, and the dashboard says so.
 *
 * @return array<string, array{weight:int, label:string, section:string, fields:string[], any:bool}>
 */
function weights(): array {
	return array(
		'description' => array( 'weight' => 10, 'section' => 'basics',   'fields' => array( 'listing_description', 'listing_body' ), 'any' => true,  'label' => __( 'Describe your practice', 'oria' ) ),
		'services'    => array( 'weight' => 15, 'section' => 'services', 'fields' => array( 'services' ),            'any' => true,  'label' => __( 'List what you offer', 'oria' ) ),
		'price'       => array( 'weight' => 15, 'section' => 'prices',   'fields' => array( 'price_from', 'price_band' ), 'any' => true, 'label' => __( 'Add a starting price', 'oria' ) ),
		'hours'       => array( 'weight' => 10, 'section' => 'hours',    'fields' => array( 'opening_hours' ),       'any' => true,  'label' => __( 'Add your opening hours', 'oria' ) ),
		'booking'     => array( 'weight' => 10, 'section' => 'hours',    'fields' => array( 'booking_url', 'phone', 'email' ), 'any' => true, 'label' => __( 'Add a way to book or get in touch', 'oria' ) ),
		'location'    => array( 'weight' => 10, 'section' => 'location', 'fields' => array( 'address' ),             'any' => true,  'label' => __( 'Confirm your address', 'oria' ) ),
		'photos'      => array( 'weight' => 15, 'section' => 'photos',   'fields' => array( 'gallery' ),             'any' => true,  'label' => __( 'Add photos of your space', 'oria' ) ),
		'suits'       => array( 'weight' => 5,  'section' => 'suits',    'fields' => array( 'reasons', 'amenities' ), 'any' => true, 'label' => __( 'Say who your sessions suit', 'oria' ) ),
		'answers'     => array( 'weight' => 5,  'section' => 'answers',  'fields' => array( 'faq' ),                 'any' => true,  'label' => __( 'Answer a few common questions', 'oria' ) ),
		'team'        => array( 'weight' => 5,  'section' => 'team',     'fields' => array( 'team' ),                'any' => true,  'label' => __( 'Introduce your practitioners', 'oria' ) ),
	);
}

/**
 * The completion score, and what is missing.
 *
 * An area an owner's plan does not include is dropped from the total
 * rather than scored as a failure -- charging somebody for a field and
 * then marking their profile incomplete because of it would be a
 * remarkable thing to do.
 *
 * @return array{pct:int, done:int, total:int, missing:array<int, array<string,mixed>>}
 */
function score( int $listing ): array {
	$done = 0;
	$total = 0;
	$missing = array();

	foreach ( weights() as $key => $area ) {
		// Areas whose every field is locked by the plan do not count either way.
		$reachable = array_filter(
			$area['fields'],
			static fn( string $f ): bool => editable( $listing, $f )
		);
		if ( ! $reachable ) {
			continue;
		}

		$total += $area['weight'];
		$has = false;
		foreach ( $reachable as $f ) {
			if ( filled( $listing, $f ) ) {
				$has = true;
				break;
			}
		}
		if ( $has ) {
			$done += $area['weight'];
			continue;
		}
		$missing[] = array(
			'key'     => $key,
			'label'   => $area['label'],
			'weight'  => $area['weight'],
			'section' => $area['section'],
		);
	}

	// Heaviest first: the three jobs offered should be the three worth most.
	usort( $missing, static fn( array $a, array $b ): int => $b['weight'] <=> $a['weight'] );

	return array(
		'pct'     => $total > 0 ? (int) round( $done / $total * 100 ) : 100,
		'done'    => $done,
		'total'   => $total,
		'missing' => $missing,
	);
}

/** Up to three things worth doing next. */
function nudges( int $listing ): array {
	return array_slice( score( $listing )['missing'], 0, MAX_NUDGES );
}

/** How complete one section is: 'done', 'part' or 'empty'. */
function section_state( int $listing, string $slug ): string {
	$fields = section( $slug )['fields'] ?? array();
	$open   = 0;
	$filled = 0;
	foreach ( $fields as $field ) {
		if ( ! editable( $listing, $field['name'] ) ) {
			continue;
		}
		++$open;
		if ( filled( $listing, $field['name'] ) ) {
			++$filled;
		}
	}
	if ( 0 === $open || 0 === $filled ) {
		return 0 === $open ? 'locked' : 'empty';
	}
	return $filled === $open ? 'done' : 'part';
}

/* -------------------------------------------------------------- the plan */

/**
 * What this listing's plan does, in the owner's terms.
 *
 * The tier decides three separate things and they are easy to confuse, so
 * they are named apart here: which FIELDS open at all, how many photos
 * PUBLISH, and how many practitioners PUBLISH. Nothing saved is ever lost
 * by dropping a tier -- the public templates simply show fewer.
 *
 * @return array{tier:string, label:string, photos:int, team:int, locked:string[], upgrade:string, next:string}
 */
function plan( int $listing ): array {
	$tier = Tiers\tier( $listing );

	$labels = array(
		'unclaimed' => __( 'Free', 'oria' ),
		Tiers\CLAIMED  => __( 'Claimed', 'oria' ),
		Tiers\FEATURED => __( 'Featured', 'oria' ),
	);

	// Which of the owner's own fields this plan will not open.
	$locked = array();
	foreach ( writable_fields() as $name => $field ) {
		if ( ! Tiers\field_editable( $listing, $name ) ) {
			$locked[] = (string) $field['label'];
		}
	}

	$upgrade = '';
	if ( Tiers\CLAIMED !== $tier && Tiers\FEATURED !== $tier
		&& function_exists( '\Oria\Core\Billing\configured' ) && \Oria\Core\Billing\configured() ) {
		$upgrade = \Oria\Core\Billing\pay_url( 'claimed', $listing, (string) wp_get_current_user()->user_email );
	}

	return array(
		'tier'    => $tier,
		'label'   => $labels[ $tier ] ?? ucfirst( $tier ),
		'photos'  => Tiers\gallery_limit( $listing ),
		'team'    => Tiers\team_limit( $listing ),
		'locked'  => $locked,
		'upgrade' => $upgrade,
		'next'    => Tiers\CLAIMED === $tier ? (string) Tiers\FEATURED : ( Tiers\FEATURED === $tier ? '' : (string) Tiers\CLAIMED ),
	);
}

/**
 * The line under a field whose plan caps how much of it is published.
 *
 * Said as a publishing limit rather than a storage one, because that is
 * what it is: everything typed here is kept, and the profile shows the
 * first few.
 */
function plan_note( array $field, int $listing ): string {
	$plan = plan( $listing );

	if ( 'team' === ( $field['plan_note'] ?? '' ) ) {
		$rows = count( (array) ( value( $listing, 'team' ) ?: array() ) );
		if ( $plan['team'] >= Tiers\TEAM_MAX || $rows <= $plan['team'] ) {
			return sprintf(
				/* translators: %d: how many practitioner profiles this plan publishes */
				_n( 'Your plan publishes %d practitioner.', 'Your plan publishes the first %d.', $plan['team'], 'oria' ),
				$plan['team']
			);
		}
		return sprintf(
			/* translators: 1: profiles published, 2: profiles saved */
			__( 'Your plan publishes the first %1$d of these %2$d. The rest stay saved and appear again on a paid plan.', 'oria' ),
			$plan['team'],
			$rows
		);
	}

	return '';
}

/* ------------------------------------------------------------------ status */

/**
 * What the owner is told their listing is doing, in their words.
 *
 * Never a raw post status. "Publish", "draft" and "pending" describe our
 * database; "Live" and "Changes being checked" describe their business.
 *
 * @return array{key:string, label:string, note:string}
 */
function status( int $listing ): array {
	if ( pending( $listing ) ) {
		return array(
			'key'   => 'review',
			'label' => __( 'Changes being checked', 'oria' ),
			'note'  => __( 'Your listing is live as it was. We will publish the change once we have looked at it.', 'oria' ),
		);
	}

	$post = get_post( $listing );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return array(
			'key'   => 'paused',
			'label' => __( 'Not showing', 'oria' ),
			'note'  => __( 'Your listing is hidden at the moment. Get in touch and we will put it back.', 'oria' ),
		);
	}

	/*
	 * "Live and complete" beside a list of three outstanding jobs reads as
	 * a page arguing with itself, so the badge follows the jobs rather than
	 * a round number: anything still worth doing means there is still
	 * something to do.
	 */
	$s = score( $listing );
	if ( $s['missing'] ) {
		return array(
			'key'   => 'thin',
			'label' => __( 'Live, needs a little more', 'oria' ),
			'note'  => __( 'People can find you. A few more details would help them choose you.', 'oria' ),
		);
	}

	return array(
		'key'   => 'live',
		'label' => __( 'Live', 'oria' ),
		'note'  => __( 'Your profile is on Oria Haven, and everything we ask for is filled in.', 'oria' ),
	);
}

/* -------------------------------------------------------- pending changes */

/**
 * Values an owner has proposed for a field that we check first.
 *
 * The approved value stays live the whole time. Nothing here is read by a
 * public template -- this is a waiting room, not a second copy of the
 * listing.
 *
 * @return array<string, array{value:mixed, at:int, by:int}>
 */
function pending( int $listing ): array {
	$raw = get_post_meta( $listing, PENDING_META, true );
	return is_array( $raw ) ? $raw : array();
}

function set_pending( int $listing, string $field, $value, int $user ): void {
	$all = pending( $listing );
	$all[ $field ] = array( 'value' => $value, 'at' => time(), 'by' => $user );
	update_post_meta( $listing, PENDING_META, $all );
}

function clear_pending( int $listing, string $field ): void {
	$all = pending( $listing );
	unset( $all[ $field ] );
	if ( $all ) {
		update_post_meta( $listing, PENDING_META, $all );
		return;
	}
	delete_post_meta( $listing, PENDING_META );
}

/* -------------------------------------------------------------- sanitising */

/**
 * Clean one posted value into what the field is allowed to hold.
 *
 * Switching on the registry's own type, so a field cannot be saved by a
 * looser rule than the one it is drawn with. Choice fields are filtered
 * against their own choices: a posted option nobody was offered is
 * dropped rather than stored.
 *
 * @param array<string, mixed> $field
 * @param mixed                $raw
 * @return mixed
 */
function clean( array $field, $raw ) {
	switch ( $field['type'] ) {

		case 'prose':
			/*
			 * Prose keeps its paragraphs. sanitize_textarea_field() would
			 * flatten the blank lines an owner uses to break the text up;
			 * wp_kses_post() keeps them and strips anything that is not
			 * plain writing. Typed text has no <p> tags, so wpautop adds
			 * them the same way the editor would.
			 */
			$v = wp_kses_post( (string) wp_unslash( $raw ) );
			$v = trim( $v );
			return '' === $v ? '' : ( str_contains( $v, '<p' ) ? $v : wpautop( $v ) );

		case 'textarea':
			$v = sanitize_textarea_field( (string) wp_unslash( $raw ) );
			return isset( $field['max'] ) ? mb_substr( $v, 0, (int) $field['max'] ) : $v;

		case 'url':
			$v = esc_url_raw( trim( (string) wp_unslash( $raw ) ) );
			return $v;

		case 'email':
			$v = sanitize_email( (string) wp_unslash( $raw ) );
			return is_email( $v ) ? $v : '';

		case 'tel':
			// Digits and the handful of separators a written phone number uses.
			return trim( preg_replace( '/[^0-9+()\-\s]/', '', (string) wp_unslash( $raw ) ) ?? '' );

		case 'number':
		case 'money':
			$v = trim( (string) wp_unslash( $raw ) );
			if ( '' === $v ) {
				return '';
			}
			// Owners type "$95" and "95.00"; both mean 95.
			$v = preg_replace( '/[^0-9.]/', '', $v ) ?? '';
			return '' === $v ? '' : (string) max( 0, (float) $v );

		case 'date':
			$v = trim( (string) wp_unslash( $raw ) );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? str_replace( '-', '', $v ) : '';

		case 'select':
		case 'radios':
			$v = sanitize_text_field( (string) wp_unslash( $raw ) );
			return isset( $field['choices'][ $v ] ) ? $v : '';

		case 'checks':
			$in  = is_array( $raw ) ? wp_unslash( $raw ) : array();
			$out = array();
			foreach ( $in as $one ) {
				$one = sanitize_text_field( (string) $one );
				if ( isset( $field['choices'][ $one ] ) ) {
					$out[] = $one;
				}
			}
			return $out;

		case 'toggle':
			return $raw ? 1 : 0;

		case 'image':
			return clean_image( $raw );

		case 'gallery':
			$in  = is_array( $raw ) ? wp_unslash( $raw ) : array();
			$out = array();
			foreach ( $in as $one ) {
				$id = clean_image( $one );
				// Same photo twice is somebody double-clicking, not a choice.
				if ( '' !== $id && ! in_array( (int) $id, $out, true ) ) {
					$out[] = (int) $id;
				}
			}

			/*
			 * The plan's allowance, applied here as well as in the form,
			 * because Ownership\enforce_gallery_limit is an
			 * acf/validate_value filter and update_field() runs no
			 * validation at all. A limit of 0 means Featured, which has
			 * none.
			 *
			 * It caps what can be ADDED, never what is already stored. The
			 * public templates publish only the first gallery_limit()
			 * photos anyway, so a listing that drops to the free plan shows
			 * fewer -- it does not lose the rest. Trimming here would
			 * delete somebody's photographs because a card expired, which
			 * is the wrong way round.
			 */
			$listing = listing_for( get_current_user_id() );
			$limit   = $listing ? Tiers\gallery_limit( $listing ) : 0;
			if ( $limit > 0 ) {
				$had = count( (array) ( value( $listing, 'gallery' ) ?: array() ) );
				$out = array_slice( $out, 0, max( $limit, $had ) );
			}
			return $out;

		case 'multi':
			$in  = is_array( $raw ) ? wp_unslash( $raw ) : array();
			$out = array();
			foreach ( $in as $one ) {
				$one     = (int) $one;
				$allowed = choices_for( $field );
				if ( $one > 0 && isset( $allowed[ $one ] ) ) {
					$out[] = $one;
				}
			}
			return $out;

		case 'repeater':
			return clean_rows( $field, $raw );

		case 'text':
		default:
			$v = sanitize_text_field( (string) wp_unslash( $raw ) );
			return isset( $field['max'] ) ? mb_substr( $v, 0, (int) $field['max'] ) : $v;
	}
}

/**
 * An uploaded image the owner is actually entitled to use.
 *
 * The form posts an attachment id, and an id is trivially edited, so the
 * number is checked rather than trusted: it must be a real attachment,
 * it must be an image, and it must be one this person uploaded or one
 * already attached to their own listing. Otherwise any id in the media
 * library could be pulled onto a profile.
 *
 * @param mixed $raw
 */
function clean_image( $raw ): string {
	$id = (int) $raw;
	if ( $id < 1 ) {
		return '';
	}
	$post = get_post( $id );
	if ( ! $post || 'attachment' !== $post->post_type ) {
		return '';
	}
	if ( ! wp_attachment_is_image( $id ) ) {
		return '';
	}
	$user    = get_current_user_id();
	$listing = listing_for( $user );
	$mine    = (int) $post->post_author === $user;
	$theirs  = $listing && (int) $post->post_parent === $listing;

	return ( $mine || $theirs || current_user_can( 'manage_options' ) ) ? (string) $id : '';
}

/**
 * Repeater rows, in the order they were posted, with the blanks dropped.
 *
 * A row whose every column is empty is somebody having pressed Add and
 * changed their mind, not data. Saving it would put an empty line on
 * their public profile.
 *
 * @param array<string, mixed> $field
 * @param mixed                $raw
 * @return array<int, array<string, string>>
 */
function clean_rows( array $field, $raw ): array {
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$rows = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$out = array();
		$any = false;
		foreach ( $field['sub'] as $sub ) {
			$val                 = clean( $sub, $row[ $sub['name'] ] ?? '' );
			$out[ $sub['name'] ] = $val;

			/*
			 * A row counts as real when somebody typed something in it. A
			 * ticked consent box on its own does not: an empty practitioner
			 * whose only answer is "yes they agreed" is a row somebody
			 * added and abandoned, and saving it puts a blank card on the
			 * public profile.
			 */
			if ( 'toggle' === $sub['type'] ) {
				continue;
			}
			if ( is_array( $val ) ? array() !== $val : '' !== trim( (string) $val ) ) {
				$any = true;
			}
		}
		if ( $any ) {
			$rows[] = $out;
		}
	}

	/*
	 * The cap, enforced here as well as by the Add button. Team\cap_on_save
	 * runs on acf/save_post, which update_field() does not fire -- so
	 * without this a crafted post could write a fifth practitioner.
	 */
	if ( ! empty( $field['max_rows'] ) ) {
		$rows = array_slice( $rows, 0, (int) $field['max_rows'] );
	}

	return $rows;
}

/* ----------------------------------------------------------------- saving */

/** Where to send somebody back to, with a word about what happened. */
function back_to( string $section, string $state ): string {
	$url = \Oria\Core\MyOria\url( 'listing-edit' );
	return add_query_arg(
		array(
			'section' => $section,
			'saved'   => $state,
		),
		$url
	);
}

/**
 * Save one section.
 *
 * Deliberately one section at a time. The owner pressed Save on the page
 * in front of them, and a handler that wrote every field in the registry
 * would quietly blank the ones that page never drew.
 */
function handle_save(): void {
	$user    = get_current_user_id();
	$listing = listing_for( $user );
	$slug    = isset( $_POST['section'] ) ? sanitize_key( (string) wp_unslash( $_POST['section'] ) ) : '';
	$section = section( $slug );

	if ( ! $listing || ! $section ) {
		wp_safe_redirect( \Oria\Core\MyOria\url() );
		exit;
	}

	check_admin_referer( 'oria_listing_save_' . $slug );

	$written = array();
	$held    = array();

	foreach ( $section['fields'] as $field ) {
		$name = $field['name'];

		// The plan gate, on the server, whatever the form contained.
		if ( ! editable( $listing, $name ) ) {
			continue;
		}

		// A field the form did not draw is not a field the owner cleared.
		if ( ! array_key_exists( $name, $_POST ) && 'checks' !== $field['type'] && 'repeater' !== $field['type'] ) {
			continue;
		}

		$raw   = $_POST[ $name ] ?? ( in_array( $field['type'], array( 'checks', 'repeater' ), true ) ? array() : '' );
		$value = clean( $field, $raw );

		/*
		 * A row removed with JavaScript switched off. The Remove button is a
		 * submit carrying "field:index", so the row is still in the post --
		 * it is dropped here instead of in the browser.
		 */
		if ( 'repeater' === $field['type'] ) {
			$value = drop_row( $name, $value );
		}
		$was   = value( $listing, $name );

		if ( same( $was, $value ) ) {
			continue;
		}

		// Changes we check first keep the live value and wait.
		if ( ! empty( $field['review'] ) ) {
			set_pending( $listing, $name, $value, $user );
			$held[] = $field['label'];
			continue;
		}

		write( $listing, $name, $value );
		$written[] = $field['label'];
	}

	if ( $written || $held ) {
		Audit\note(
			$listing,
			sprintf(
				/* translators: 1: section name, 2: comma-separated field labels */
				__( 'Owner updated %1$s: %2$s', 'oria' ),
				$section['label'],
				implode( ', ', array_merge( $written, $held ) )
			),
			$user
		);
	}

	if ( $held ) {
		notify_review( $listing, $held, $user );
	}

	$state = $held ? 'review' : ( $written ? 'ok' : 'none' );

	// "Save and continue" lands on the next section rather than back on
	// the one just finished, which is the whole point of pressing it.
	$next = isset( $_POST['next'] ) ? sanitize_key( (string) wp_unslash( $_POST['next'] ) ) : '';
	$land = ( $next && section( $next ) ) ? $next : $slug;

	wp_safe_redirect( back_to( $land, $state ) );
	exit;
}

/** An owner changing their mind about a change that was waiting. */
function handle_withdraw(): void {
	$user    = get_current_user_id();
	$listing = listing_for( $user );
	$field   = isset( $_POST['field'] ) ? sanitize_key( (string) wp_unslash( $_POST['field'] ) ) : '';

	if ( ! $listing || ! $field ) {
		wp_safe_redirect( \Oria\Core\MyOria\url() );
		exit;
	}
	check_admin_referer( 'oria_listing_withdraw' );

	clear_pending( $listing, $field );
	Audit\note( $listing, sprintf( __( 'Owner withdrew a proposed change to %s', 'oria' ), $field ), $user );

	wp_safe_redirect( \Oria\Core\MyOria\url( 'listing' ) );
	exit;
}

/**
 * Remove the row the Remove button named, if it named one in this field.
 *
 * @param array<int, array<string, string>> $rows
 * @return array<int, array<string, string>>
 */
function drop_row( string $field_name, array $rows ): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller checked it.
	$asked = isset( $_POST['oria_drop'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['oria_drop'] ) ) : '';
	if ( '' === $asked || ! str_contains( $asked, ':' ) ) {
		return $rows;
	}

	list( $which, $index ) = explode( ':', $asked, 2 );
	if ( $which !== $field_name || ! is_numeric( $index ) ) {
		return $rows;
	}

	unset( $rows[ (int) $index ] );
	return array_values( $rows );
}

/**
 * Loose comparison that treats '' and null and [] as the same nothing.
 *
 * A date is the one value that changes shape on the way round: stored as
 * Ymd, read back through ACF as Y-m-d. Comparing them as typed made every
 * offers save log "Runs until" as changed when nothing had.
 */
function same( $a, $b ): bool {
	if ( is_array( $a ) || is_array( $b ) ) {
		return wp_json_encode( array_values( (array) $a ) ) === wp_json_encode( array_values( (array) $b ) );
	}
	$a = (string) $a;
	$b = (string) $b;
	if ( preg_match( '/^\d{4}-?\d{2}-?\d{2}$/', $a ) && preg_match( '/^\d{4}-?\d{2}-?\d{2}$/', $b ) ) {
		return str_replace( '-', '', $a ) === str_replace( '-', '', $b );
	}
	return $a === $b;
}

/** Tell the directory somebody is waiting on us. */
function notify_review( int $listing, array $labels, int $user ): void {
	$to = get_option( 'admin_email' );
	if ( ! $to ) {
		return;
	}
	$who = get_userdata( $user );
	wp_mail(
		$to,
		sprintf( __( '[Oria] %s proposed a change', 'oria' ), get_the_title( $listing ) ),
		sprintf(
			"%s (%s) changed: %s\n\n%s",
			$who ? $who->display_name : __( 'An owner', 'oria' ),
			$who ? $who->user_email : '',
			implode( ', ', $labels ),
			get_edit_post_link( $listing, 'raw' )
		)
	);
}

/* ------------------------------------------------------------ performance */

/**
 * The last 30 days, in the only terms that mean anything to an owner.
 *
 * Only the actions that represent somebody doing something: a view, and
 * then the five ways they can act on it.
 *
 * @return array<int, array{key:string, label:string, n:int}>
 */
function stats( int $listing, int $days = 30 ): array {
	$map = array(
		'view' => __( 'Profile views', 'oria' ),
		'web'  => __( 'Website clicks', 'oria' ),
		'tel'  => __( 'Phone taps', 'oria' ),
		'book' => __( 'Booking clicks', 'oria' ),
		'enq'  => __( 'Enquiries', 'oria' ),
		'dir'  => __( 'Directions', 'oria' ),
	);
	$out = array();
	foreach ( $map as $key => $label ) {
		$out[] = array(
			'key'   => $key,
			'label' => $label,
			'n'     => Analytics\total( $listing, $key, $days ),
		);
	}
	return $out;
}

/**
 * One sentence about the numbers, or '' where there is nothing honest to say.
 *
 * Tied to a gap we can actually name, never to a promise about ranking.
 * With no traffic yet there is no observation to make, so it says nothing
 * and the dashboard shows the checklist instead.
 */
function stats_note( int $listing, array $stats ): string {
	$by = array_column( $stats, 'n', 'key' );
	$views = (int) ( $by['view'] ?? 0 );

	if ( $views < 10 ) {
		return '';
	}

	if ( editable( $listing, 'booking_url' ) && ! filled( $listing, 'booking_url' ) ) {
		return sprintf(
			/* translators: %s: number of profile views */
			__( 'People looked at your profile %s times this month, but there is no booking link for them to press.', 'oria' ),
			number_format_i18n( $views )
		);
	}
	if ( ! filled( $listing, 'price_from' ) && ! filled( $listing, 'price_band' ) ) {
		return sprintf(
			__( 'People looked at your profile %s times this month. A starting price is the question they ask next.', 'oria' ),
			number_format_i18n( $views )
		);
	}
	if ( ! filled( $listing, 'gallery' ) ) {
		return sprintf(
			__( 'People looked at your profile %s times this month. Photographs of the room are what they look at first.', 'oria' ),
			number_format_i18n( $views )
		);
	}
	return '';
}
