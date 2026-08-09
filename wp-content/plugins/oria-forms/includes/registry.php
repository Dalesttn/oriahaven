<?php
/**
 * The form definitions. A form is an id, its fields, and its two emails —
 * everything else (rendering, validation, spam guards, entries) is shared
 * machinery. Add a form by adding an array here, or from other code via
 * the `oria_forms` filter.
 *
 * Field shape:
 *   'type'        text | email | tel | textarea | select | checkbox
 *   'label'       shown above the input
 *   'required'    bool
 *   'options'     select only: value => label
 *   'placeholder' optional
 */

declare(strict_types=1);

namespace Oria\Forms\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<string, array<string, mixed>> */
function forms(): array {
	$forms = array(

		'contact' => array(
			'label'          => __( 'Contact', 'oria' ),
			'submit'         => __( 'Send message', 'oria' ),
			'success'        => __( 'Thanks — message received. We read everything and reply within two working days.', 'oria' ),
			'notify_subject' => '[Oria Haven] %topic%: %name%',
			'reply_subject'  => __( 'We got your message — Oria Haven', 'oria' ),
			'reply_intro'    => __( "Thanks for getting in touch. A real person reads every message — you'll hear back within two working days. Here's a copy of what you sent us.", 'oria' ),
			'fields'         => array(
				'name'    => array( 'type' => 'text', 'label' => __( 'Your name', 'oria' ), 'required' => true ),
				'email'   => array( 'type' => 'email', 'label' => __( 'Email', 'oria' ), 'required' => true ),
				'topic'   => array(
					'type'    => 'select',
					'label'   => __( 'What is it about?', 'oria' ),
					'options' => array(
						'General'            => __( 'General question', 'oria' ),
						'Suggest a practice' => __( 'Suggest a practice we\'ve missed', 'oria' ),
						'Report a listing'   => __( 'Report a listing', 'oria' ),
						'Press'              => __( 'Press & partnerships', 'oria' ),
					),
				),
				'message' => array( 'type' => 'textarea', 'label' => __( 'Message', 'oria' ), 'required' => true, 'placeholder' => __( 'Tell us what you\'re after', 'oria' ) ),
			),
		),

		'claim' => array(
			'label'          => __( 'Claim a listing', 'oria' ),
			'submit'         => __( 'Request to claim', 'oria' ),
			'success'        => __( 'Request received. We check every claim by hand — you\'ll get an email with your log-in once it\'s approved.', 'oria' ),
			'notify_subject' => '[Oria Haven] Claim request: %practice%',
			'reply_subject'  => __( 'Your claim request — Oria Haven', 'oria' ),
			'reply_intro'    => __( "Thanks — we've received your request to claim a listing. We confirm every claim by hand, usually within two working days, and you'll get an email with your log-in details once it's approved.", 'oria' ),
			'fields'         => array(
				'practice'   => array(
					'type'        => 'text',
					'label'       => __( 'Business or practice name', 'oria' ),
					'required'    => true,
					'placeholder' => __( 'Start typing — we\'ll find your listing', 'oria' ),
					// Turns the field into a lookup against published listings.
					'lookup'      => 'listing',
					'hint'        => __( 'Already listed? Pick yours from the list. Not there yet? Just type the name.', 'oria' ),
				),
				// Filled in when a listing is picked from the lookup, so the
				// claim arrives already matched to a listing.
				'listing_ref' => array( 'type' => 'hidden', 'label' => __( 'Matched listing', 'oria' ) ),
				'name'       => array( 'type' => 'text', 'label' => __( 'Your name', 'oria' ), 'required' => true ),
				'email'      => array( 'type' => 'email', 'label' => __( 'Email', 'oria' ), 'required' => true, 'placeholder' => __( 'Ideally the one on your website', 'oria' ) ),
				'phone'      => array( 'type' => 'tel', 'label' => __( 'Phone', 'oria' ) ),
				'message'    => array( 'type' => 'textarea', 'label' => __( 'Anything we should fix straight away?', 'oria' ), 'placeholder' => __( 'e.g. the Tuesday class moved to 7pm', 'oria' ) ),
				'authorised' => array( 'type' => 'checkbox', 'label' => __( 'I\'m authorised to manage this practice\'s information.', 'oria' ), 'required' => true ),
			),
		),
	);

	/** @var array<string, array<string, mixed>> */
	return apply_filters( 'oria_forms', $forms );
}

/** One form's definition, or null. */
function form( string $id ): ?array {
	return forms()[ $id ] ?? null;
}
