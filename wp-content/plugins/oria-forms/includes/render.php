<?php
/**
 * Rendering: the [oria_form form="contact"] shortcode, in the theme's own
 * form language (.field / .input / .btn) so the forms are indistinguishable
 * from the rest of the site.
 */

declare(strict_types=1);

namespace Oria\Forms\Render;

use Oria\Forms\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_shortcode( 'oria_form', __NAMESPACE__ . '\shortcode' );
}

/** The lookup widget's script and styles, plus the endpoint it calls. */
function enqueue_lookup_assets(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	// Styles live in the theme's forms.css, which is already in the head:
	// a stylesheet enqueued this late (mid-body, from a shortcode) is not
	// reliably printed, whereas a footer script is.
	wp_enqueue_script( 'oria-forms', ORIA_FORMS_URL . 'assets/forms.js', array(), '1.0', true );
	wp_add_inline_script(
		'oria-forms',
		'window.ORIA_FORMS = ' . wp_json_encode(
			array(
				'search'  => rest_url( 'oria/v1/listing-search' ),
				// Logged-in visitors carry an auth cookie, and WordPress
				// rejects cookie-authenticated REST calls that arrive without
				// a nonce — even on a public route. Logged-out visitors send
				// no cookie, so a stale nonce from a cached page is harmless.
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'claimed' => __( 'This listing already has an owner. If that\'s not right, send the form anyway and tell us below.', 'oria' ),
				'matched' => __( 'Matched to your listing — nice, that speeds things up.', 'oria' ),
			)
		) . ';',
		'before'
	);
}

/** @param array<string, string>|string $atts */
function shortcode( $atts ): string {
	$atts = shortcode_atts( array( 'form' => '' ), (array) $atts );
	$id   = sanitize_key( $atts['form'] );
	$form = Registry\form( $id );
	if ( null === $form ) {
		return '';
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only.
	$state = ( isset( $_GET['oform'] ) && $id === ( $_GET['oform_id'] ?? '' ) ) ? (string) $_GET['oform'] : '';

	if ( 'sent' === $state ) {
		return '<div class="notice" id="oform-' . esc_attr( $id ) . '">'
			. '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" style="width:20px;height:20px;flex:none"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>'
			. '<span>' . esc_html( (string) $form['success'] ) . '</span></div>';
	}

	// Only pages that actually render a lookup field pay for the script.
	foreach ( (array) $form['fields'] as $field ) {
		if ( ! empty( $field['lookup'] ) ) {
			enqueue_lookup_assets();
			break;
		}
	}

	$out = '<form class="stack oform" style="gap:.9rem" id="oform-' . esc_attr( $id ) . '" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
	$out .= '<input type="hidden" name="action" value="oria_form">';
	$out .= '<input type="hidden" name="oform_id" value="' . esc_attr( $id ) . '">';
	$out .= '<input type="hidden" name="oform_ts" value="' . esc_attr( (string) time() ) . '">';
	$out .= wp_nonce_field( 'oria_form_' . $id, 'oform_nonce', true, false );
	// The honeypot: humans never see it, bots fill it.
	$out .= '<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">';

	if ( 'error' === $state ) {
		$out .= '<p style="font-size:.8125rem;color:#9b2c2c">' . esc_html__( 'That didn\'t send — check the highlighted details and try again.', 'oria' ) . '</p>';
	}

	foreach ( (array) $form['fields'] as $name => $field ) {
		$out .= render_field( (string) $name, (array) $field );
	}

	$out .= '<button class="btn btn--dark btn--block" type="submit">' . esc_html( (string) ( $form['submit'] ?? __( 'Send', 'oria' ) ) ) . '</button>';
	$out .= '</form>';
	return $out;
}

function render_field( string $name, array $field ): string {
	$type        = (string) ( $field['type'] ?? 'text' );
	$label       = (string) ( $field['label'] ?? $name );
	$required    = ! empty( $field['required'] );
	$placeholder = (string) ( $field['placeholder'] ?? '' );
	$req_attr    = $required ? ' required' : '';
	$req_mark    = $required ? '' : ' <span style="color:var(--text-faint);font-weight:400">· ' . esc_html__( 'optional', 'oria' ) . '</span>';

	// Carries a value chosen by script (the listing lookup); never shown.
	if ( 'hidden' === $type ) {
		return '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
	}

	if ( 'checkbox' === $type ) {
		return '<label class="check" style="align-items:flex-start"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . $req_attr . '><span style="font-size:.875rem">' . esc_html( $label ) . '</span></label>';
	}

	$out = '<label class="field"><span class="field__label">' . esc_html( $label ) . $req_mark . '</span>';

	if ( 'textarea' === $type ) {
		$out .= '<textarea class="textarea" name="' . esc_attr( $name ) . '" style="min-height:110px"' . $req_attr
			. ( $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' ) . '></textarea>';
	} elseif ( 'select' === $type ) {
		$out .= '<select class="select" name="' . esc_attr( $name ) . '"' . $req_attr . '>';
		foreach ( (array) ( $field['options'] ?? array() ) as $value => $opt_label ) {
			$out .= '<option value="' . esc_attr( (string) $value ) . '">' . esc_html( (string) $opt_label ) . '</option>';
		}
		$out .= '</select>';
	} else {
		$html_type = in_array( $type, array( 'email', 'tel' ), true ) ? $type : 'text';
		$lookup    = (string) ( $field['lookup'] ?? '' );
		$out      .= '<input class="input" type="' . esc_attr( $html_type ) . '" name="' . esc_attr( $name ) . '"' . $req_attr
			. ( $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' )
			. ( $lookup
				? ' data-oform-lookup="' . esc_attr( $lookup ) . '" autocomplete="off" role="combobox"'
					. ' aria-autocomplete="list" aria-expanded="false"'
				: '' )
			. '>';
		if ( $lookup ) {
			// The listbox and the "we matched you" note the script fills in.
			$out .= '<span class="oform-lookup" data-oform-lookup-panel hidden></span>'
				. '<span class="oform-lookup__note" data-oform-lookup-note hidden></span>';
		}
	}

	if ( ! empty( $field['hint'] ) ) {
		$out .= '<span class="oform-hint">' . esc_html( (string) $field['hint'] ) . '</span>';
	}

	return $out . '</label>';
}
