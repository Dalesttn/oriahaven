<?php
/**
 * The page-builder field groups.
 *
 * One Flexible Content field ("Sections") on every page. Each layout is a
 * section from the designed system — the editor assembles pages from the
 * library and can reorder freely, but every option inside a section is one
 * that was designed, so no arrangement produces an off-brand page.
 *
 * Also: practice-term display fields and the global options page.
 */

declare(strict_types=1);

namespace Oria\Core\FieldsPages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'acf/init', __NAMESPACE__ . '\register_sections' );
	add_action( 'acf/init', __NAMESPACE__ . '\register_practice_term' );
	add_action( 'acf/init', __NAMESPACE__ . '\register_options' );
}

/* ---------------------------------------------------------------- helpers */
function txt( string $layout, string $name, string $label, array $extra = array() ): array {
	return array_merge(
		array( 'key' => "field_sec_{$layout}_{$name}", 'name' => $name, 'label' => $label, 'type' => 'text' ),
		$extra
	);
}
function ta( string $layout, string $name, string $label, int $rows = 3 ): array {
	return array( 'key' => "field_sec_{$layout}_{$name}", 'name' => $name, 'label' => $label, 'type' => 'textarea', 'rows' => $rows );
}
function img( string $layout, string $name, string $label ): array {
	return array( 'key' => "field_sec_{$layout}_{$name}", 'name' => $name, 'label' => $label, 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium' );
}
function wys( string $layout, string $name, string $label ): array {
	return array( 'key' => "field_sec_{$layout}_{$name}", 'name' => $name, 'label' => $label, 'type' => 'wysiwyg', 'tabs' => 'visual', 'media_upload' => 0, 'toolbar' => 'basic' );
}
function rep( string $layout, string $name, string $label, array $sub, string $button ): array {
	return array( 'key' => "field_sec_{$layout}_{$name}", 'name' => $name, 'label' => $label, 'type' => 'repeater', 'button_label' => $button, 'layout' => 'block', 'sub_fields' => $sub );
}
function sf( string $layout, string $rep, string $name, string $label, string $type = 'text', array $extra = array() ): array {
	return array_merge(
		array( 'key' => "field_sec_{$layout}_{$rep}_{$name}", 'name' => $name, 'label' => $label, 'type' => $type ),
		$extra
	);
}
function bg( string $layout ): array {
	return array(
		'key'           => "field_sec_{$layout}_background",
		'name'          => 'background',
		'label'         => 'Background',
		'type'          => 'button_group',
		'choices'       => array( 'paper' => 'Light', 'sand' => 'Sand' ),
		'default_value' => 'paper',
	);
}
function layout( string $name, string $label, array $fields ): array {
	return array( 'key' => "layout_{$name}", 'name' => $name, 'label' => $label, 'sub_fields' => $fields );
}

/* --------------------------------------------------------------- sections */
function register_sections(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_sections',
			'title'    => 'Page sections',
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ) ),
			'position' => 'normal',
			'style'    => 'seamless',
			'hide_on_screen' => array( 'the_content' ),
			'fields'   => array(
				array(
					'key'          => 'field_oria_sections',
					'name'         => 'sections',
					'label'        => 'Sections',
					'type'         => 'flexible_content',
					'button_label' => 'Add section',
					'layouts'      => array(

						layout( 'hero', 'Hero (full screen)', array(
							txt( 'hero', 'eyebrow', 'Eyebrow' ),
							txt( 'hero', 'heading', 'Headline' ),
							ta( 'hero', 'sub', 'Sub-headline' ),
							img( 'hero', 'image', 'Background image' ),
							array( 'key' => 'field_sec_hero_show_search', 'name' => 'show_search', 'label' => 'Show the search bar', 'type' => 'true_false', 'default_value' => 1, 'ui' => 1 ),
							array( 'key' => 'field_sec_hero_show_trust', 'name' => 'show_trust', 'label' => 'Show the ratings card', 'type' => 'true_false', 'default_value' => 1, 'ui' => 1, 'instructions' => 'The glass card with the live average rating across all listings.' ),
							ta( 'hero', 'trust_text', 'Ratings card text', 2 ),
							rep( 'hero', 'tags', 'Popular tag pills', array(
								sf( 'hero', 'tags', 'label', 'Label' ),
								sf( 'hero', 'tags', 'url', 'Link', 'url' ),
							), 'Add tag' ),
						) ),

						layout( 'page_head', 'Page header', array(
							txt( 'page_head', 'eyebrow', 'Eyebrow' ),
							txt( 'page_head', 'heading', 'Headline (empty = page title)' ),
							ta( 'page_head', 'lede', 'Lede' ),
						) ),

						layout( 'starting_soon', 'Upcoming events strip', array(
							rep( 'starting_soon', 'sessions', 'Fallback sessions (shown only when no events are scheduled)', array(
								sf( 'starting_soon', 'sessions', 'time_label', 'Time', 'text', array( 'placeholder' => 'Today 5.45pm' ) ),
								sf( 'starting_soon', 'sessions', 'name', 'Name' ),
								sf( 'starting_soon', 'sessions', 'suburb', 'Suburb' ),
								sf( 'starting_soon', 'sessions', 'url', 'Link', 'url' ),
							), 'Add session' ),
						) ),

						layout( 'practice_tiles', 'Practice tiles', array(
							txt( 'practice_tiles', 'eyebrow', 'Eyebrow' ),
							txt( 'practice_tiles', 'heading', 'Heading' ),
							ta( 'practice_tiles', 'aside', 'Aside text' ),
						) ),

						layout( 'stillness_map', 'Stillness Map', array(
							txt( 'stillness_map', 'eyebrow', 'Eyebrow' ),
							txt( 'stillness_map', 'heading', 'Heading' ),
							ta( 'stillness_map', 'aside', 'Aside text' ),
						) ),

						layout( 'featured_listings', 'Featured listings', array(
							txt( 'featured_listings', 'eyebrow', 'Eyebrow' ),
							txt( 'featured_listings', 'heading', 'Heading' ),
							ta( 'featured_listings', 'aside', 'Aside text' ),
						) ),

						layout( 'feature_split', 'Feature rows + image', array(
							bg( 'feature_split' ),
							txt( 'feature_split', 'eyebrow', 'Eyebrow' ),
							txt( 'feature_split', 'heading', 'Heading' ),
							ta( 'feature_split', 'intro', 'Intro paragraph' ),
							rep( 'feature_split', 'rows', 'Feature rows', array(
								sf( 'feature_split', 'rows', 'title', 'Title' ),
								sf( 'feature_split', 'rows', 'text', 'Text', 'textarea', array( 'rows' => 2 ) ),
							), 'Add row' ),
							img( 'feature_split', 'image', 'Image' ),
						) ),

						layout( 'steps_split', 'Numbered steps + intro', array(
							bg( 'steps_split' ),
							txt( 'steps_split', 'eyebrow', 'Eyebrow' ),
							txt( 'steps_split', 'heading', 'Heading' ),
							ta( 'steps_split', 'intro', 'Intro paragraph' ),
							rep( 'steps_split', 'steps', 'Steps', array(
								sf( 'steps_split', 'steps', 'title', 'Title' ),
								sf( 'steps_split', 'steps', 'text', 'Text', 'textarea', array( 'rows' => 2 ) ),
							), 'Add step' ),
							txt( 'steps_split', 'primary_label', 'Primary button label' ),
							txt( 'steps_split', 'primary_url', 'Primary button link' ),
							txt( 'steps_split', 'secondary_label', 'Secondary button label' ),
							txt( 'steps_split', 'secondary_url', 'Secondary button link' ),
						) ),

						layout( 'journal_latest', 'Latest journal posts', array(
							bg( 'journal_latest' ),
							txt( 'journal_latest', 'eyebrow', 'Eyebrow' ),
							txt( 'journal_latest', 'heading', 'Heading' ),
						) ),

						layout( 'faq', 'Questions accordion', array(
							bg( 'faq' ),
							txt( 'faq', 'eyebrow', 'Eyebrow' ),
							txt( 'faq', 'heading', 'Heading' ),
							img( 'faq', 'image', 'Side image (optional)' ),
							rep( 'faq', 'items', 'Questions', array(
								sf( 'faq', 'items', 'question', 'Question' ),
								sf( 'faq', 'items', 'answer', 'Answer', 'textarea', array( 'rows' => 3 ) ),
							), 'Add question' ),
						) ),

						layout( 'reviews', 'Review cards', array(
							bg( 'reviews' ),
							txt( 'reviews', 'eyebrow', 'Eyebrow' ),
							txt( 'reviews', 'heading', 'Heading' ),
							rep( 'reviews', 'items', 'Reviews', array(
								sf( 'reviews', 'items', 'title', 'Short title' ),
								sf( 'reviews', 'items', 'quote', 'Quote', 'textarea', array( 'rows' => 3 ) ),
								sf( 'reviews', 'items', 'name', 'Name' ),
								sf( 'reviews', 'items', 'where', 'Suburb / role' ),
							), 'Add review' ),
						) ),

						layout( 'pricing', 'Pricing tiers', array(
							bg( 'pricing' ),
							txt( 'pricing', 'eyebrow', 'Eyebrow' ),
							txt( 'pricing', 'heading', 'Heading' ),
							ta( 'pricing', 'sub', 'Sub-heading' ),
							rep( 'pricing', 'tiers', 'Tiers', array(
								sf( 'pricing', 'tiers', 'tier_label', 'Tier label' ),
								sf( 'pricing', 'tiers', 'amount', 'Amount' ),
								sf( 'pricing', 'tiers', 'suffix', 'Suffix' ),
								sf( 'pricing', 'tiers', 'blurb', 'One-line blurb' ),
								sf( 'pricing', 'tiers', 'features', 'Features (one per line)', 'textarea', array( 'rows' => 5 ) ),
								sf( 'pricing', 'tiers', 'cta_label', 'Button label' ),
								sf( 'pricing', 'tiers', 'cta_url', 'Button link' ),
								sf( 'pricing', 'tiers', 'style', 'Style', 'select', array( 'choices' => array( 'now' => 'Highlighted', 'default' => 'Standard', 'soon' => 'Muted' ), 'default_value' => 'default' ) ),
							), 'Add tier' ),
						) ),

						layout( 'feature_list', 'Ticked feature list', array(
							bg( 'feature_list' ),
							txt( 'feature_list', 'eyebrow', 'Eyebrow' ),
							txt( 'feature_list', 'heading', 'Heading' ),
							rep( 'feature_list', 'items', 'Items', array(
								sf( 'feature_list', 'items', 'title', 'Title' ),
								sf( 'feature_list', 'items', 'text', 'Text', 'textarea', array( 'rows' => 2 ) ),
							), 'Add item' ),
						) ),

						layout( 'card_grid', 'Card grid', array(
							bg( 'card_grid' ),
							txt( 'card_grid', 'eyebrow', 'Eyebrow' ),
							txt( 'card_grid', 'heading', 'Heading' ),
							rep( 'card_grid', 'cards', 'Cards', array(
								sf( 'card_grid', 'cards', 'title', 'Title' ),
								sf( 'card_grid', 'cards', 'text', 'Text', 'textarea', array( 'rows' => 2 ) ),
							), 'Add card' ),
						) ),

						layout( 'roadmap', 'Roadmap card', array(
							bg( 'roadmap' ),
							txt( 'roadmap', 'heading', 'Heading' ),
							rep( 'roadmap', 'phases', 'Phases', array(
								sf( 'roadmap', 'phases', 'title', 'Title' ),
								sf( 'roadmap', 'phases', 'text', 'Text', 'textarea', array( 'rows' => 2 ) ),
								sf( 'roadmap', 'phases', 'current', 'Current phase', 'true_false' ),
							), 'Add phase' ),
						) ),

						layout( 'prose', 'Text block', array(
							bg( 'prose' ),
							wys( 'prose', 'content', 'Content' ),
						) ),

						layout( 'form_card', 'Form card', array(
							bg( 'form_card' ),
							txt( 'form_card', 'eyebrow', 'Eyebrow' ),
							txt( 'form_card', 'heading', 'Heading' ),
							ta( 'form_card', 'sub', 'Sub-heading' ),
							wys( 'form_card', 'form', 'Form (paste a form shortcode here)' ),
						) ),

						layout( 'contact', 'Contact split', array(
							txt( 'contact', 'eyebrow', 'Eyebrow' ),
							txt( 'contact', 'heading', 'Heading' ),
							ta( 'contact', 'intro', 'Intro' ),
							txt( 'contact', 'email', 'Email address' ),
							wys( 'contact', 'form', 'Form (paste a form shortcode here)' ),
						) ),

						layout( 'cta', 'Call-to-action slab', array(
							txt( 'cta', 'eyebrow', 'Eyebrow' ),
							txt( 'cta', 'heading', 'Heading' ),
							img( 'cta', 'image', 'Background image' ),
							txt( 'cta', 'primary_label', 'Primary button label' ),
							txt( 'cta', 'primary_url', 'Primary button link' ),
							txt( 'cta', 'secondary_label', 'Secondary button label' ),
							txt( 'cta', 'secondary_url', 'Secondary button link' ),
						) ),
					),
				),
			),
		)
	);
}

/* --------------------------------------------------- practice term extras */
function register_practice_term(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_practice_term',
			'title'    => 'Practice display',
			'location' => array( array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'practice' ) ) ),
			'fields'   => array(
				array( 'key' => 'field_oria_tile_image', 'name' => 'tile_image', 'label' => 'Tile image', 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium' ),
				array( 'key' => 'field_oria_tile_blurb', 'name' => 'tile_blurb', 'label' => 'Tile blurb', 'type' => 'textarea', 'rows' => 2 ),
				array( 'key' => 'field_oria_practice_intro', 'name' => 'landing_intro', 'label' => 'Landing page introduction', 'type' => 'wysiwyg', 'tabs' => 'visual', 'media_upload' => 0, 'toolbar' => 'basic' ),
			),
		)
	);
}

/* --------------------------------------------------------------- options */
function register_options(): void {
	if ( ! function_exists( 'acf_add_options_page' ) || ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title' => 'Site settings',
			'menu_title' => 'Site settings',
			'menu_slug'  => 'oria-settings',
			'capability' => 'manage_options',
			'redirect'   => false,
			'icon_url'   => 'dashicons-admin-generic',
			'position'   => 59,
		)
	);

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_options',
			'title'    => 'Site settings',
			'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'oria-settings' ) ) ),
			'fields'   => array(
				array(
					'key'          => 'field_oria_gmaps_key',
					'name'         => 'google_maps_api_key',
					'label'        => 'Google Maps API key (browser)',
					'type'         => 'text',
					'instructions' => 'Used only for the profile map iframe (Maps Embed API). Restrict this key by WEBSITE in the Google Cloud console. Prefer defining ORIA_GOOGLE_BROWSER_KEY in wp-config.php — the constant overrides this field and keeps the key out of the database. Leave empty to show a plain "open in Google Maps" link instead.',
				),
				array(
					'key'          => 'field_oria_places_server_key',
					'name'         => 'google_places_server_key',
					'label'        => 'Google Places API key (server)',
					'type'         => 'text',
					'instructions' => 'Used from PHP for place lookups and photo resolution; never appears in page markup. Enable Places API (New) on it and restrict by IP address. Prefer defining ORIA_GOOGLE_SERVER_KEY in wp-config.php — the constant overrides this field and keeps the key out of the database.',
				),
				array(
					'key'          => 'field_oria_stripe_link_claimed',
					'name'         => 'stripe_link_claimed',
					'label'        => 'Stripe Payment Link — Claimed plan',
					'type'         => 'url',
					'instructions' => 'The recurring Payment Link for the Claimed tier, from the Stripe dashboard (buy.stripe.com/…). The claim-approval email appends the listing reference automatically.',
				),
				array(
					'key'          => 'field_oria_stripe_link_featured',
					'name'         => 'stripe_link_featured',
					'label'        => 'Stripe Payment Link — Featured plan',
					'type'         => 'url',
					'instructions' => 'The recurring Payment Link for the Featured tier.',
				),
				array(
					'key'          => 'field_oria_stripe_portal_url',
					'name'         => 'stripe_portal_login_url',
					'label'        => 'Stripe customer portal login link (fallback)',
					'type'         => 'url',
					'instructions' => 'Optional. The no-code portal login link from Stripe → Settings → Billing → Customer portal. Only used when ORIA_STRIPE_SECRET_KEY is not defined — with the key, owners are signed straight into the portal without it.',
				),
				array(
					'key'          => 'field_oria_stripe_webhook_secret',
					'name'         => 'stripe_webhook_secret',
					'label'        => 'Stripe webhook signing secret',
					'type'         => 'text',
					'instructions' => 'From the webhook endpoint in the Stripe dashboard (whsec_…). Prefer defining ORIA_STRIPE_WEBHOOK_SECRET in wp-config.php — the constant overrides this field and keeps the secret out of the database. Billing activates only when both links and this secret are set; until then, approving a claim activates the listing for free as before.',
				),
				array(
					'key'           => 'field_oria_places_enable',
					'name'          => 'places_photos_enable',
					'label'         => 'Use Google Places photos on listing profiles',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'instructions'  => 'When a listing has no photos of its own, show its Google Business photos with attribution. A listing\'s own gallery always wins. Photo references refresh every 30 days per Google\'s caching terms.',
				),
				array( 'key' => 'field_oria_footer_tagline', 'name' => 'footer_tagline', 'label' => 'Footer tagline', 'type' => 'textarea', 'rows' => 2 ),
				array( 'key' => 'field_oria_acknowledgement', 'name' => 'acknowledgement', 'label' => 'Acknowledgement of country', 'type' => 'textarea', 'rows' => 2 ),
				array( 'key' => 'field_oria_instagram', 'name' => 'instagram_url', 'label' => 'Instagram URL', 'type' => 'text' ),
				array( 'key' => 'field_oria_facebook', 'name' => 'facebook_url', 'label' => 'Facebook URL', 'type' => 'text' ),
			),
		)
	);
}
