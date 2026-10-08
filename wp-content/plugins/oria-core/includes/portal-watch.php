<?php
/**
 * Portal watch: tell the directory when a practitioner uses their portal.
 *
 * Two kinds of email, both to the site's admin address (the one that
 * already receives "proposed a change"):
 *
 *   - a change, straight away: any section saved from /my-oria/listing-edit/,
 *     or a proposed change withdrawn, naming the fields;
 *   - a visit, once per practitioner per day: the first time they open the
 *     portal that day, with the page they opened. Every click after that
 *     is not news.
 *
 * A "practitioner" here is an account that owns a listing
 * (ListingEditor\listing_for), not the WordPress role -- see the
 * 'practitioner' role trap in the Work in Wellness notes. Administrators
 * never trigger it, so testing as yourself sends nothing. No IP address or
 * device detail is read or stored: the only thing kept is the date of the
 * last visit email, on the user.
 *
 * Off switch: update_option( 'oria_portal_alerts', 'off' ). Recipient:
 * the 'oria_portal_alerts_to' filter, default admin_email.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\PortalWatch;

defined( 'ABSPATH' ) || exit;

const SEEN = 'oria_portal_seen'; // user meta: Y-m-d (site time) of the last visit email.

function bootstrap(): void {
	add_action( 'template_redirect', __NAMESPACE__ . '\on_visit', 20 );
	add_action( 'oria_listing_owner_saved', __NAMESPACE__ . '\on_save', 10, 5 );
	add_action( 'oria_listing_owner_withdrew', __NAMESPACE__ . '\on_withdraw', 10, 3 );
}

function enabled(): bool {
	return 'off' !== (string) get_option( 'oria_portal_alerts', 'on' );
}

function recipient(): string {
	return (string) apply_filters( 'oria_portal_alerts_to', (string) get_option( 'admin_email' ) );
}

/** The listing this user manages, or 0 -- and never for an administrator. */
function practitioner_listing( int $user ): int {
	if ( ! $user || user_can( $user, 'manage_options' ) || ! function_exists( '\Oria\Core\ListingEditor\listing_for' ) ) {
		return 0;
	}
	return (int) \Oria\Core\ListingEditor\listing_for( $user );
}

/** "Jane Smith (jane@studio.com.au)" */
function who( int $user ): string {
	$u = get_userdata( $user );
	if ( ! $u ) {
		return __( 'A practitioner', 'oria' );
	}
	return trim( $u->display_name . ' (' . $u->user_email . ')' );
}

function send( string $subject, array $lines ): void {
	$to = recipient();
	if ( ! enabled() || '' === $to ) {
		return;
	}
	wp_mail( $to, '[Oria] ' . $subject, implode( "\n", $lines ) );
}

/** The links every email ends with. */
function links( int $listing ): array {
	return array(
		'',
		__( 'Listing:', 'oria' ) . ' ' . (string) get_permalink( $listing ),
		__( 'Edit in admin:', 'oria' ) . ' ' . (string) get_edit_post_link( $listing, 'raw' ),
		'',
		__( 'Turn these emails off: update_option( \'oria_portal_alerts\', \'off\' ).', 'oria' ),
	);
}

/* ------------------------------------------------------------------ visits */

function on_visit(): void {
	if ( ! function_exists( '\Oria\Core\MyOria\is_page' ) || ! \Oria\Core\MyOria\is_page() || ! is_user_logged_in() ) {
		return;
	}
	$user    = get_current_user_id();
	$listing = practitioner_listing( $user );
	if ( ! $listing ) {
		return;
	}
	$today = wp_date( 'Y-m-d' );
	if ( get_user_meta( $user, SEEN, true ) === $today ) {
		return;
	}
	update_user_meta( $user, SEEN, $today );

	$view    = \Oria\Core\MyOria\view();
	$section = isset( $_GET['section'] ) ? sanitize_key( (string) wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only label.
	$where   = '' !== $view ? '/my-oria/' . $view . '/' : '/my-oria/';
	if ( '' !== $section && function_exists( '\Oria\Core\ListingEditor\section' ) ) {
		$s      = \Oria\Core\ListingEditor\section( $section );
		$where .= ' (' . ( $s ? (string) $s['label'] : $section ) . ')';
	}

	send(
		/* translators: %s: listing name */
		sprintf( __( '%s opened their portal', 'oria' ), get_the_title( $listing ) ),
		array_merge(
			array(
				/* translators: 1: person, 2: time */
				sprintf( __( '%1$s signed in and opened their listing portal at %2$s.', 'oria' ), who( $user ), wp_date( 'g:ia, j M Y' ) ),
				/* translators: %s: page path */
				sprintf( __( 'First page: %s', 'oria' ), $where ),
				__( 'You will hear about any changes they save; further visits today are not emailed.', 'oria' ),
			),
			links( $listing )
		)
	);
}

/* ----------------------------------------------------------------- changes */

/**
 * @param string[] $written Field labels saved live.
 * @param string[] $held    Field labels waiting for review (also emailed by notify_review).
 */
function on_save( int $listing, string $section, array $written, array $held, int $user ): void {
	if ( ! $written && ! $held ) {
		return;
	}
	if ( ! practitioner_listing( $user ) ) {
		return;
	}
	$lines = array(
		/* translators: 1: person, 2: section, 3: time */
		sprintf( __( '%1$s saved "%2$s" at %3$s.', 'oria' ), who( $user ), $section, wp_date( 'g:ia, j M Y' ) ),
	);
	if ( $written ) {
		$lines[] = __( 'Live now:', 'oria' ) . ' ' . implode( ', ', $written );
	}
	if ( $held ) {
		$lines[] = __( 'Waiting for your review:', 'oria' ) . ' ' . implode( ', ', $held );
	}
	send(
		/* translators: %s: listing name */
		sprintf( __( '%s updated their listing', 'oria' ), get_the_title( $listing ) ),
		array_merge( $lines, links( $listing ) )
	);
}

function on_withdraw( int $listing, string $field, int $user ): void {
	if ( ! practitioner_listing( $user ) ) {
		return;
	}
	// The field's label, as the owner saw it, rather than its key.
	if ( function_exists( '\Oria\Core\ListingEditor\sections' ) ) {
		foreach ( \Oria\Core\ListingEditor\sections() as $sec ) {
			foreach ( (array) ( $sec['fields'] ?? array() ) as $f ) {
				if ( ( $f['name'] ?? '' ) === $field && ! empty( $f['label'] ) ) {
					$field = (string) $f['label'];
					break 2;
				}
			}
		}
	}
	send(
		/* translators: %s: listing name */
		sprintf( __( '%s withdrew a proposed change', 'oria' ), get_the_title( $listing ) ),
		array_merge(
			/* translators: 1: person, 2: field */
			array( sprintf( __( '%1$s withdrew their proposed change to %2$s.', 'oria' ), who( $user ), $field ) ),
			links( $listing )
		)
	);
}
