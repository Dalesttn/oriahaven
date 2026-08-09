<?php
/**
 * Submission handling: nonce, honeypot, time-trap and per-IP throttle in
 * front of validation; then an entry is saved and both emails go out.
 * Errors and success both travel back as query args on the referring page.
 */

declare(strict_types=1);

namespace Oria\Forms\Handler;

use Oria\Forms\Registry;
use Oria\Forms\Entries;
use Oria\Forms\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'admin_post_oria_form', __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_nopriv_oria_form', __NAMESPACE__ . '\handle' );
}

function back( string $state, string $form_id ): void {
	$url = wp_get_referer() ?: home_url( '/' );
	$url = remove_query_arg( array( 'oform', 'oform_id' ), $url );
	$url = add_query_arg( array( 'oform' => $state, 'oform_id' => $form_id ), $url ) . '#oform-' . $form_id;
	wp_safe_redirect( $url );
	exit;
}

function handle(): void {
	$form_id = sanitize_key( (string) ( $_POST['oform_id'] ?? '' ) );
	$form    = Registry\form( $form_id );
	if ( null === $form ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	// --- spam walls, cheapest first -----------------------------------
	if ( '' !== (string) ( $_POST['oform_website'] ?? '' ) ) {
		back( 'sent', $form_id ); // Bots get a quiet "success".
	}
	$ts = (int) ( $_POST['oform_ts'] ?? 0 );
	if ( $ts <= 0 || time() - $ts < 3 || time() - $ts > 12 * HOUR_IN_SECONDS ) {
		back( 'error', $form_id );
	}
	if ( ! wp_verify_nonce( (string) ( $_POST['oform_nonce'] ?? '' ), 'oria_form_' . $form_id ) ) {
		back( 'error', $form_id );
	}
	$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'oria_oform_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		back( 'error', $form_id );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	// --- validate + sanitise ------------------------------------------
	$values = array();
	foreach ( (array) $form['fields'] as $name => $field ) {
		$type = (string) ( $field['type'] ?? 'text' );
		$raw  = (string) ( $_POST[ $name ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised per type below.

		switch ( $type ) {
			case 'email':
				$value = sanitize_email( $raw );
				if ( '' !== $raw && ! is_email( $value ) ) {
					back( 'error', $form_id );
				}
				break;
			case 'textarea':
				$value = sanitize_textarea_field( $raw );
				break;
			case 'select':
				$value = sanitize_text_field( $raw );
				if ( '' !== $value && ! array_key_exists( $value, (array) ( $field['options'] ?? array() ) ) ) {
					back( 'error', $form_id );
				}
				break;
			case 'checkbox':
				$value = '' !== $raw ? __( 'Yes', 'oria' ) : '';
				break;
			default:
				$value = sanitize_text_field( $raw );
		}

		if ( ! empty( $field['required'] ) && '' === trim( (string) $value ) ) {
			back( 'error', $form_id );
		}
		$values[ (string) $name ] = (string) $value;
	}

	// --- record + email ------------------------------------------------
	Entries\save( $form_id, $form, $values );
	Emails\notify( $form_id, $form, $values );
	Emails\auto_reply( $form_id, $form, $values );

	back( 'sent', $form_id );
}
