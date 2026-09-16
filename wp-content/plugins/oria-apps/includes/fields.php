<?php
/**
 * What an editor fills in for an app.
 *
 * Grouped into tabs in the order the work actually happens: what it is,
 * what we think of it, what it costs, where it runs, where the facts came
 * from, and only then the commercial fields. The source tab is not
 * paperwork -- an app page written from the app's own marketing is worth
 * nothing, and the date is what lets a reader judge how current the
 * pricing is.
 */

declare(strict_types=1);

namespace Oria\Apps\Fields;

use Oria\Apps\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'acf/init', __NAMESPACE__ . '\register' );
}

function register(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_app',
			'title'    => 'Wellness app details',
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => Data\CPT ) ) ),
			'position' => 'normal',
			'fields'   => array(

				/* ---------------------------------------------- the app */
				array( 'key' => 'field_oria_app_tab_basics', 'label' => 'The app', 'type' => 'tab', 'placement' => 'left' ),
				array(
					'key'          => 'field_oria_app_tagline',
					'name'         => 'app_tagline',
					'label'        => 'Tagline',
					'type'         => 'text',
					'maxlength'    => 90,
					'instructions' => 'One line in our words, not theirs. "Structured meditation courses for people starting out."',
				),
				array(
					'key'           => 'field_oria_app_logo',
					'name'          => 'app_logo',
					'label'         => 'App icon',
					'type'          => 'image',
					'return_format' => 'id',
					'preview_size'  => 'thumbnail',
					'instructions'  => 'Square icon, stored locally so no page waits on a third-party server. The cover image (featured image) is used for sharing.',
				),
				array(
					'key'   => 'field_oria_app_developer',
					'name'  => 'developer_name',
					'label' => 'Developer',
					'type'  => 'text',
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'     => 'field_oria_app_website',
					'name'    => 'official_website',
					'label'   => 'Official website',
					'type'    => 'url',
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'     => 'field_oria_app_ios',
					'name'    => 'ios_url',
					'label'   => 'App Store URL',
					'type'    => 'url',
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'     => 'field_oria_app_android',
					'name'    => 'android_url',
					'label'   => 'Google Play URL',
					'type'    => 'url',
					'wrapper' => array( 'width' => '50' ),
				),

				/* ------------------------------------------- editorial */
				array( 'key' => 'field_oria_app_tab_editorial', 'label' => 'Editorial', 'type' => 'tab', 'placement' => 'left' ),
				array(
					'key'          => 'field_oria_app_best_for',
					'name'         => 'best_for',
					'label'        => 'Best for',
					'type'         => 'checkbox',
					'choices'      => Data\BEST_FOR,
					'instructions' => 'Who this suits. Drives the "best apps for…" collections. Two or three is usually right; ticking everything says nothing.',
				),
				array(
					'key'           => 'field_oria_app_primary_cat',
					'name'          => 'primary_category',
					'label'         => 'Main category',
					'type'          => 'select',
					'choices'       => Data\CATEGORIES,
					'allow_null'    => 1,
					'ui'            => 1,
					'instructions'  => 'The one category that stands for this app on a card, in the comparison tables and in its page title. Must be one of the categories ticked on the right. Left empty, the first alphabetically is used, which is rarely what you meant.',
					'wrapper'       => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_oria_app_oria_take',
					'name'         => 'oria_take',
					'label'        => 'Oria take',
					'type'         => 'textarea',
					'rows'         => 3,
					'maxlength'    => 320,
					'instructions' => 'The honest one-paragraph verdict, in the site\'s voice. Say who it suits and who it does not. No scores, no stars.',
				),
				array(
					'key'          => 'field_oria_app_features',
					'name'         => 'key_features',
					'label'        => 'Key features',
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => 'Add feature',
					'sub_fields'   => array(
						array( 'key' => 'field_oria_app_feature_text', 'name' => 'text', 'label' => 'Feature', 'type' => 'text' ),
					),
				),
				array(
					'key'          => 'field_oria_app_pros',
					'name'         => 'pros',
					'label'        => 'What we like',
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => 'Add point',
					'sub_fields'   => array(
						array( 'key' => 'field_oria_app_pro_text', 'name' => 'text', 'label' => 'Point', 'type' => 'text' ),
					),
				),
				array(
					'key'          => 'field_oria_app_cons',
					'name'         => 'considerations',
					'label'        => 'Things to consider',
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => 'Add point',
					'instructions' => 'Not a disclaimer. The limitation a reader would be annoyed to discover after signing up.',
					'sub_fields'   => array(
						array( 'key' => 'field_oria_app_con_text', 'name' => 'text', 'label' => 'Point', 'type' => 'text' ),
					),
				),
				array(
					'key'          => 'field_oria_app_faq',
					'name'         => 'faq',
					'label'        => 'Questions people ask',
					'type'         => 'repeater',
					'layout'       => 'row',
					'button_label' => 'Add question',
					'instructions' => 'Only real questions with real answers. An empty FAQ is better than a padded one.',
					'sub_fields'   => array(
						array( 'key' => 'field_oria_app_faq_q', 'name' => 'question', 'label' => 'Question', 'type' => 'text' ),
						array( 'key' => 'field_oria_app_faq_a', 'name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'rows' => 3 ),
					),
				),

				/* --------------------------------------------- pricing */
				array( 'key' => 'field_oria_app_tab_pricing', 'label' => 'Pricing', 'type' => 'tab', 'placement' => 'left' ),
				array(
					'key'           => 'field_oria_app_pricing_model',
					'name'          => 'pricing_model',
					'label'         => 'Pricing model',
					'type'          => 'select',
					'choices'       => Data\PRICING,
					'default_value' => 'unknown',
					'wrapper'       => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_oria_app_price',
					'name'         => 'starting_price',
					'label'        => 'Starting price',
					'type'         => 'text',
					'wrapper'      => array( 'width' => '50' ),
					'instructions' => 'As published, with the period: "$12.99 a month". Leave empty rather than guessing.',
				),
				array(
					'key'     => 'field_oria_app_free_version',
					'name'    => 'free_version_available',
					'label'   => 'Free version',
					'type'    => 'true_false',
					'ui'      => 1,
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'     => 'field_oria_app_free_trial',
					'name'    => 'free_trial',
					'label'   => 'Free trial',
					'type'    => 'text',
					'wrapper' => array( 'width' => '50' ),
					'instructions' => 'e.g. "7 days". Empty if there is none.',
				),
				array(
					'key'   => 'field_oria_app_pricing_notes',
					'name'  => 'pricing_notes',
					'label' => 'Pricing notes',
					'type'  => 'textarea',
					'rows'  => 2,
				),

				/* ------------------------------------------- platforms */
				array( 'key' => 'field_oria_app_tab_platforms', 'label' => 'Platforms', 'type' => 'tab', 'placement' => 'left' ),
				array(
					'key'          => 'field_oria_app_platforms',
					'name'         => 'platforms',
					'label'        => 'Runs on',
					'type'         => 'checkbox',
					'choices'      => Data\PLATFORMS,
					'instructions' => 'Only tick what you have confirmed on the app\'s own store listing.',
				),
				array(
					'key'           => 'field_oria_app_practices',
					'name'          => 'practices',
					'label'         => 'Goes with these practices',
					'type'          => 'taxonomy',
					'taxonomy'      => 'practice',
					'field_type'    => 'multi_select',
					'add_term'      => 0,
					'save_terms'    => 0,
					'return_format' => 'id',
					'instructions'  => 'Optional. Where this app should appear in the directory. Leave empty and the app category decides, which is right for most apps.',
				),

				/* --------------------------------------------- sources */
				array( 'key' => 'field_oria_app_tab_sources', 'label' => 'Sources & checking', 'type' => 'tab', 'placement' => 'left' ),
				array(
					'key'          => 'field_oria_app_source_1',
					'name'         => 'primary_source_url',
					'label'        => 'Primary source',
					'type'         => 'url',
					'instructions' => 'The app\'s own site or store listing. Everything on the page should be traceable to a source here.',
				),
				array( 'key' => 'field_oria_app_source_2', 'name' => 'secondary_source_url', 'label' => 'Secondary source', 'type' => 'url' ),
				array( 'key' => 'field_oria_app_source_price', 'name' => 'pricing_source_url', 'label' => 'Pricing source', 'type' => 'url' ),
				array(
					'key'            => 'field_oria_app_verified',
					'name'           => 'last_verified',
					'label'          => 'Last checked',
					'type'           => 'date_picker',
					'display_format' => 'j F Y',
					'return_format'  => 'Y-m-d',
					'instructions'   => 'Shown on the page. After six months the app list warns that it is due a re-check.',
				),
				array( 'key' => 'field_oria_app_verify_notes', 'name' => 'verification_notes', 'label' => 'Notes for the next check', 'type' => 'textarea', 'rows' => 2 ),

				/* ------------------------------------------- affiliate */
				array( 'key' => 'field_oria_app_tab_affiliate', 'label' => 'Affiliate', 'type' => 'tab', 'placement' => 'left' ),
				array(
					'key'          => 'field_oria_app_aff_available',
					'name'         => 'affiliate_available',
					'label'        => 'Affiliate link in use',
					'type'         => 'true_false',
					'ui'           => 1,
					'instructions' => 'Switching this on swaps the outbound link and shows the disclosure. It changes nothing about whether the app is recommended, and nothing about where it ranks.',
				),
				array( 'key' => 'field_oria_app_aff_url', 'name' => 'affiliate_url', 'label' => 'Affiliate URL', 'type' => 'url', 'conditional_logic' => array( array( array( 'field' => 'field_oria_app_aff_available', 'operator' => '==', 'value' => '1' ) ) ) ),
				array( 'key' => 'field_oria_app_aff_program', 'name' => 'affiliate_program_name', 'label' => 'Program', 'type' => 'text', 'wrapper' => array( 'width' => '50' ) ),
				array( 'key' => 'field_oria_app_aff_network', 'name' => 'affiliate_network', 'label' => 'Network', 'type' => 'text', 'wrapper' => array( 'width' => '50' ) ),
				array( 'key' => 'field_oria_app_aff_checked', 'name' => 'affiliate_last_checked', 'label' => 'Link last checked', 'type' => 'date_picker', 'display_format' => 'j F Y', 'return_format' => 'Y-m-d' ),
				array( 'key' => 'field_oria_app_aff_notes', 'name' => 'affiliate_notes', 'label' => 'Notes', 'type' => 'textarea', 'rows' => 2 ),

				/* -------------------------------------------- editorial placement */
				array( 'key' => 'field_oria_app_tab_placement', 'label' => 'Placement', 'type' => 'tab', 'placement' => 'left' ),
				array(
					'key'          => 'field_oria_app_pick',
					'name'         => 'oria_pick',
					'label'        => 'Oria pick',
					'type'         => 'true_false',
					'ui'           => 1,
					'instructions' => 'Features the app on the hub. Assigned by hand, never by a score.',
				),
				array(
					'key'           => 'field_oria_app_related',
					'name'          => 'related_apps',
					'label'         => 'Related apps',
					'type'          => 'relationship',
					'post_type'     => array( Data\CPT ),
					'max'           => 4,
					'return_format' => 'id',
					'instructions'  => 'Optional. Leave empty and related apps are worked out from shared categories and who they suit.',
				),
			),
		)
	);
}
