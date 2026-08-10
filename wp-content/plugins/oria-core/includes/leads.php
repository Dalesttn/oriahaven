<?php
/**
 * Leads: enquiries captured on-site, stored, then delivered.
 *
 * Until now "Send an enquiry" was a mailto: link — the conversation left
 * the site with no record it ever happened, which meant nothing to show
 * a paying practitioner and nothing to ever bill against. Both enquiry
 * paths now land here first:
 *
 *   profile — the form on a listing page, delivered to that practice
 *   match   — "get matched": one request fanned out to up to MATCH_CAP
 *             practices that fit the service and area
 *
 * Every lead is a private post tied to its listing, counted in the
 * listing's own analytics ('enq' — a real submission, not a click), and
 * delivered by branded email with Reply-To set to the visitor, so the
 *
 * practice answers the person directly and we never sit in the middle
 * of the conversation.
 *
 * Privacy, by design rather than by policy text: the forms ask what
 * service and when — never what's wrong. No field invites health
 * information, the notes placeholder says so out loud, and matched
 * requests tell the visitor exactly which practices received their
 * details. Nothing here is billed yet; the record exists so that the
 * numbers are real when that day comes.
 */

declare(strict_types=1);

namespace Oria\Core\Leads;

use Oria\Core\Analytics;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CPT       = 'oria_lead';
const MATCH_CAP = 3;

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register_cpt' );
	add_action( 'admin_post_oria_enquiry', __NAMESPACE__ . '\handle_enquiry' );
	add_action( 'admin_post_nopriv_oria_enquiry', __NAMESPACE__ . '\handle_enquiry' );
	add_action( 'admin_post_oria_match', __NAMESPACE__ . '\handle_match' );
	add_action( 'admin_post_nopriv_oria_match', __NAMESPACE__ . '\handle_match' );

	add_filter( 'manage_' . CPT . '_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_' . CPT . '_posts_custom_column', __NAMESPACE__ . '\column_content', 10, 2 );
}

function register_cpt(): void {
	register_post_type(
		CPT,
		array(
			'labels'       => array(
				'name'          => __( 'Leads', 'oria' ),
				'singular_name' => __( 'Lead', 'oria' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=listing',
			'supports'     => array( 'title' ),
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		)
	);
}

/* -------------------------------------------------------------- spam walls */

/**
 * The same three walls every Oria form uses: honeypot, minimum fill time,
 * nonce — then a per-IP throttle. Bounces on failure and never returns.
 */
function guard( string $nonce_action, string $back_url ): void {
	if ( '' !== (string) ( $_POST['oform_website'] ?? '' ) ) {
		bounce( $back_url, 'sent' ); // Bots get a quiet success.
	}
	$ts = (int) ( $_POST['oform_ts'] ?? 0 );
	if ( $ts <= 0 || time() - $ts < 3 || time() - $ts > 12 * HOUR_IN_SECONDS ) {
		bounce( $back_url, 'error' );
	}
	if ( ! wp_verify_nonce( (string) ( $_POST['oform_nonce'] ?? '' ), $nonce_action ) ) {
		bounce( $back_url, 'error' );
	}
	$key = 'oria_lead_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		bounce( $back_url, 'error' );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
}

/** Redirect back with a state flag. Never returns. */
function bounce( string $url, string $state, string $extra = '' ): void {
	$url = remove_query_arg( array( 'olead', 'omatched' ), $url );
	$url = add_query_arg( 'olead', $state, $url );
	if ( '' !== $extra ) {
		$url = add_query_arg( 'omatched', $extra, $url );
	}
	wp_safe_redirect( $url . '#enquire' );
	exit;
}

/** The visitor fields both forms share, validated. Bounces on failure. */
function visitor_fields( string $back ): array {
	$name  = sanitize_text_field( wp_unslash( (string) ( $_POST['lead_name'] ?? '' ) ) );
	$email = sanitize_email( wp_unslash( (string) ( $_POST['lead_email'] ?? '' ) ) );
	$phone = sanitize_text_field( wp_unslash( (string) ( $_POST['lead_phone'] ?? '' ) ) );
	$notes = sanitize_textarea_field( wp_unslash( (string) ( $_POST['lead_notes'] ?? '' ) ) );

	if ( '' === $name || ! is_email( $email ) ) {
		bounce( $back, 'error' );
	}
	// Enough for "Saturday mornings, beginner, near the train line" — not
	// enough to encourage a medical history.
	return array(
		'name'  => mb_substr( $name, 0, 120 ),
		'email' => $email,
		'phone' => mb_substr( $phone, 0, 40 ),
		'notes' => mb_substr( $notes, 0, 600 ),
	);
}

/* --------------------------------------------------------- profile enquiry */

function handle_enquiry(): void {
	$listing = (int) ( $_POST['listing_id'] ?? 0 );
	$back    = get_permalink( $listing ) ?: home_url( '/' );

	guard( 'oria_enquiry_' . $listing, $back );

	if ( 'listing' !== get_post_type( $listing ) || 'publish' !== get_post_status( $listing ) ) {
		bounce( home_url( '/' ), 'error' );
	}
	$to = practice_email( $listing );
	if ( '' === $to ) {
		// The form only renders when an email exists, but a stale cached
		// page could still post here. Send it to us instead of the void.
		$to = (string) get_option( 'admin_email' );
	}

	$v = visitor_fields( $back );

	$lead_id = save_lead( $listing, 'profile', $v );
	deliver( $listing, $to, $v, false );
	visitor_receipt( $v, array( $listing ) );
	Analytics\record( $listing, 'enq' );

	do_action( 'oria_lead_created', $lead_id, $listing, 'profile' );
	bounce( $back, 'sent' );
}

/* -------------------------------------------------------------- get matched */

function handle_match(): void {
	$back = (string) ( wp_get_referer() ?: home_url( '/' ) );
	guard( 'oria_match', $back );

	$service = sanitize_key( (string) ( $_POST['match_service'] ?? '' ) );
	$area    = sanitize_key( (string) ( $_POST['match_area'] ?? '' ) );
	$timing  = sanitize_text_field( wp_unslash( (string) ( $_POST['match_timing'] ?? '' ) ) );
	if ( ! in_array( $timing, array( 'Weekday daytime', 'Weekday evenings', 'Weekends', 'Flexible' ), true ) ) {
		$timing = 'Flexible';
	}
	$v = visitor_fields( $back );

	$matches = find_matches( $service, $area );

	if ( ! $matches ) {
		// No practice fits — that's a gap worth knowing about, not an
		// error. The request lands with us and we reply by hand.
		save_lead( 0, 'match', $v, compact( 'service', 'area', 'timing' ) );
		unmatched_admin_email( $service, $area, $timing, $v );
		visitor_receipt( $v, array() );
		bounce( $back, 'sent', '0' );
	}

	foreach ( $matches as $listing ) {
		$lead_id = save_lead( $listing, 'match', $v, compact( 'service', 'area', 'timing' ) );
		deliver( $listing, practice_email( $listing ), $v, true, $timing );
		Analytics\record( $listing, 'enq' );
		do_action( 'oria_lead_created', $lead_id, $listing, 'match' );
	}
	visitor_receipt( $v, $matches );

	bounce( $back, 'sent', (string) count( $matches ) );
}

/**
 * The practices a request fans out to: matching the chosen practice
 * category or specialty, contactable, nearest first — the chosen suburb,
 * then its region, then anywhere in the metro. Paid tiers lead within
 * each ring; that's part of what the tier buys.
 *
 * @return list<int> listing IDs, at most MATCH_CAP.
 */
function find_matches( string $service, string $area ): array {
	$tax = taxonomy_for( $service );
	if ( '' === $tax ) {
		return array();
	}

	$rings   = area_rings( $area );
	$found   = array();
	foreach ( $rings as $ring ) {
		$tax_query = array( array( 'taxonomy' => $tax, 'field' => 'slug', 'terms' => $service ) );
		if ( null !== $ring ) {
			$tax_query[] = array( 'taxonomy' => Taxonomies\AREA, 'field' => 'term_id', 'terms' => $ring, 'include_children' => true );
		}
		$q = get_posts(
			array(
				'post_type'      => 'listing',
				'post_status'    => 'publish',
				'posts_per_page' => 24,
				'fields'         => 'ids',
				'tax_query'      => $tax_query,
			)
		);
		foreach ( rank( $q ) as $id ) {
			if ( ! in_array( $id, $found, true ) && '' !== practice_email( $id ) ) {
				$found[] = $id;
				if ( count( $found ) >= MATCH_CAP ) {
					return $found;
				}
			}
		}
	}
	return $found;
}

/** Which taxonomy the submitted service slug belongs to, or ''. */
function taxonomy_for( string $slug ): string {
	if ( '' === $slug ) {
		return '';
	}
	if ( get_term_by( 'slug', $slug, Taxonomies\PRACTICE ) ) {
		return Taxonomies\PRACTICE;
	}
	if ( get_term_by( 'slug', $slug, Taxonomies\SPECIALTY ) ) {
		return Taxonomies\SPECIALTY;
	}
	return '';
}

/**
 * Search rings, nearest first: the suburb itself, its region, then the
 * whole metro (null = no area constraint).
 *
 * @return list<int|null>
 */
function area_rings( string $area ): array {
	$term = '' !== $area ? get_term_by( 'slug', $area, Taxonomies\AREA ) : false;
	if ( ! $term instanceof \WP_Term ) {
		return array( null );
	}
	$rings = array( (int) $term->term_id );
	if ( $term->parent ) {
		$rings[] = (int) $term->parent;
	}
	$rings[] = null;
	return $rings;
}

/**
 * Paid placement inside a ring: featured, then claimed, then the rest —
 * ties broken by rating. Same ordering philosophy as the directory.
 *
 * @param list<int> $ids
 * @return list<int>
 */
function rank( array $ids ): array {
	$weight = static function ( int $id ): array {
		$status = function_exists( '\Oria\Theme\display_status' ) ? \Oria\Theme\display_status( $id ) : 'unclaimed';
		$tier   = 'featured' === $status ? 0 : ( 'claimed' === $status ? 1 : 2 );
		$rating = (float) get_post_meta( $id, 'google_rating', true );
		return array( $tier, -$rating );
	};
	usort( $ids, static fn( int $a, int $b ): int => $weight( $a ) <=> $weight( $b ) );
	return $ids;
}

/* ---------------------------------------------------------------- storage */

function practice_email( int $listing ): string {
	$email = sanitize_email( (string) get_field( 'email', $listing ) );
	return is_email( $email ) ? $email : '';
}

/** @param array{name:string,email:string,phone:string,notes:string} $v */
function save_lead( int $listing, string $source, array $v, array $request = array() ): int {
	$title = sprintf(
		'%s → %s',
		$v['name'],
		$listing ? wp_specialchars_decode( (string) get_post_field( 'post_title', $listing, 'raw' ) ) : __( 'no match found', 'oria' )
	);
	$id = wp_insert_post(
		array(
			'post_type'   => CPT,
			'post_status' => 'private',
			'post_title'  => $title,
		)
	);
	if ( is_wp_error( $id ) || 0 === $id ) {
		return 0;
	}
	update_post_meta( $id, '_listing_id', $listing );
	update_post_meta( $id, '_source', $source );
	update_post_meta( $id, '_name', $v['name'] );
	update_post_meta( $id, '_email', $v['email'] );
	update_post_meta( $id, '_phone', $v['phone'] );
	update_post_meta( $id, '_notes', $v['notes'] );
	foreach ( $request as $k => $val ) {
		update_post_meta( $id, '_' . $k, (string) $val );
	}
	return (int) $id;
}

/* --------------------------------------------------------------- delivery */

/** The email shell when Oria Forms is active, plain text otherwise. */
function send( string $to, string $subject, string $heading, string $html_body, string $reply_to = '' ): void {
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( '' !== $reply_to && is_email( $reply_to ) ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}
	$html = function_exists( '\Oria\Forms\Emails\shell' )
		? \Oria\Forms\Emails\shell( $heading, $html_body )
		: $html_body;
	wp_mail( $to, $subject, $html, $headers );
}

/** The lead, delivered to the practice. Reply-To is the visitor. */
function deliver( int $listing, string $to, array $v, bool $matched, string $timing = '' ): void {
	if ( '' === $to ) {
		return;
	}
	$practice = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing, 'raw' ) );
	$first    = trim( (string) strtok( $v['name'], ' ' ) );

	$rows = array(
		__( 'Name', 'oria' )  => $v['name'],
		__( 'Email', 'oria' ) => $v['email'],
		__( 'Phone', 'oria' ) => $v['phone'],
	);
	if ( '' !== $timing ) {
		$rows[ __( 'Preferred time', 'oria' ) ] = $timing;
	}
	if ( '' !== $v['notes'] ) {
		$rows[ __( 'Their message', 'oria' ) ] = $v['notes'];
	}

	$table = '';
	foreach ( $rows as $label => $value ) {
		if ( '' === trim( (string) $value ) ) {
			continue;
		}
		$table .= '<tr><td style="padding:9px 14px 9px 0;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#566762;white-space:nowrap;vertical-align:top;">' . esc_html( $label ) . '</td>'
			. '<td style="padding:9px 0;color:#082220;">' . nl2br( esc_html( (string) $value ) ) . '</td></tr>';
	}

	$body = '<h1 style="margin:0 0 6px;font-size:20px;letter-spacing:-0.3px;">' . esc_html( sprintf( __( 'New enquiry for %s', 'oria' ), $practice ) ) . '</h1>'
		. '<p style="margin:0 0 16px;color:#566762;font-size:13px;">' . esc_html( wp_date( 'j F Y, g.ia' ) ) . '</p>'
		. '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-top:1px solid #EFEDE6;">' . $table . '</table>'
		. '<p style="margin:20px 0 0;"><strong>' . esc_html( sprintf( __( 'Hit reply to answer %s directly.', 'oria' ), $first ?: __( 'them', 'oria' ) ) ) . '</strong> '
		. esc_html__( 'Quick replies win these — most people book with whoever answers first.', 'oria' ) . '</p>';

	if ( $matched ) {
		$body .= '<p style="margin:14px 0 0;color:#566762;font-size:13px;">' . esc_html( sprintf( __( 'This person asked Oria Haven to match them with a practice. Up to %d matching practices received this enquiry.', 'oria' ), MATCH_CAP ) ) . '</p>';
	}
	$body .= '<p style="margin:14px 0 0;color:#566762;font-size:13px;">' . esc_html__( "You're receiving this because your practice is listed on Oria Haven. Enquiries are free — we never take a cut of bookings.", 'oria' ) . '</p>';

	send(
		$to,
		sprintf( __( 'New enquiry via Oria Haven — %s', 'oria' ), $v['name'] ),
		__( 'You have a new enquiry', 'oria' ),
		$body,
		$v['email']
	);
}

/**
 * The receipt to the visitor: which practices actually got their details.
 * Transparency is the consent story — nobody should wonder who has
 * their email.
 *
 * @param list<int> $listings
 */
function visitor_receipt( array $v, array $listings ): void {
	$first = trim( (string) strtok( $v['name'], ' ' ) );

	if ( $listings ) {
		$names = array_map(
			static fn( int $id ): string => '<li style="margin:0 0 6px;"><a href="' . esc_url( (string) get_permalink( $id ) ) . '" style="color:#0E3B38;">'
				. esc_html( wp_specialchars_decode( (string) get_post_field( 'post_title', $id, 'raw' ) ) ) . '</a></li>',
			$listings
		);
		$body = '<h1 style="margin:0 0 14px;font-size:20px;letter-spacing:-0.3px;">' . esc_html( $first ? sprintf( __( 'On its way, %s.', 'oria' ), $first ) : __( 'On its way.', 'oria' ) ) . '</h1>'
			. '<p style="margin:0 0 12px;">' . esc_html( _n( 'Your enquiry went to this practice, and they have your details to reply directly:', 'Your enquiry went to these practices, and they have your details to reply directly:', count( $listings ), 'oria' ) ) . '</p>'
			. '<ul style="margin:0 0 16px;padding-left:18px;">' . implode( '', $names ) . '</ul>'
			. '<p style="margin:0;color:#566762;font-size:13px;">' . esc_html__( "Most practices reply within a day. If you haven't heard back in two, reply to this email and we'll chase it for you.", 'oria' ) . '</p>';
	} else {
		$body = '<h1 style="margin:0 0 14px;font-size:20px;letter-spacing:-0.3px;">' . esc_html( $first ? sprintf( __( 'We\'re on it, %s.', 'oria' ), $first ) : __( "We're on it.", 'oria' ) ) . '</h1>'
			. '<p style="margin:0 0 12px;">' . esc_html__( "Nothing in the directory fits that request exactly yet, so a real person is looking at it. We'll reply within a day with the closest options we can find.", 'oria' ) . '</p>';
	}

	send(
		$v['email'],
		__( 'Your enquiry — Oria Haven', 'oria' ),
		__( 'Your enquiry is on its way', 'oria' ),
		$body,
		(string) get_option( 'admin_email' )
	);
}

/** An unmatched request is a demand signal — tell the admin what was asked for. */
function unmatched_admin_email( string $service, string $area, string $timing, array $v ): void {
	send(
		(string) get_option( 'admin_email' ),
		'[Oria Haven] Unmatched lead: ' . $service,
		__( 'Unmatched lead', 'oria' ),
		'<h1 style="margin:0 0 10px;font-size:20px;">' . esc_html__( 'A request found no matching practice', 'oria' ) . '</h1>'
		. '<p style="margin:0 0 8px;">' . esc_html( sprintf( 'Service: %s · Area: %s · Time: %s', $service ?: '—', $area ?: 'anywhere', $timing ) ) . '</p>'
		. '<p style="margin:0 0 8px;">' . esc_html( sprintf( '%s · %s · %s', $v['name'], $v['email'], $v['phone'] ?: 'no phone' ) ) . '</p>'
		. ( '' !== $v['notes'] ? '<p style="margin:0 0 8px;color:#566762;">' . nl2br( esc_html( $v['notes'] ) ) . '</p>' : '' )
		. '<p style="margin:14px 0 0;color:#566762;font-size:13px;">' . esc_html__( 'They were told a person would reply within a day. This is also a signal: someone searched for a service the directory does not cover in that area.', 'oria' ) . '</p>',
		$v['email']
	);
}

/* ---------------------------------------------------------------- admin ui */

function columns( array $cols ): array {
	return array(
		'cb'           => $cols['cb'] ?? '<input type="checkbox" />',
		'title'        => __( 'Lead', 'oria' ),
		'oria_contact' => __( 'Contact', 'oria' ),
		'oria_want'    => __( 'Asked for', 'oria' ),
		'oria_source'  => __( 'Source', 'oria' ),
		'date'         => __( 'Date', 'oria' ),
	);
}

function column_content( string $col, int $post_id ): void {
	switch ( $col ) {
		case 'oria_contact':
			$email = (string) get_post_meta( $post_id, '_email', true );
			$phone = (string) get_post_meta( $post_id, '_phone', true );
			echo esc_html( $email );
			if ( '' !== $phone ) {
				echo '<br>' . esc_html( $phone );
			}
			break;
		case 'oria_want':
			$service = (string) get_post_meta( $post_id, '_service', true );
			$area    = (string) get_post_meta( $post_id, '_area', true );
			$notes   = (string) get_post_meta( $post_id, '_notes', true );
			if ( '' !== $service || '' !== $area ) {
				echo esc_html( trim( $service . ( $area ? ' · ' . $area : '' ) ) );
			} elseif ( '' !== $notes ) {
				echo esc_html( wp_trim_words( $notes, 12, '…' ) );
			} else {
				echo '—';
			}
			break;
		case 'oria_source':
			echo 'match' === get_post_meta( $post_id, '_source', true )
				? esc_html__( 'Get matched', 'oria' )
				: esc_html__( 'Listing page', 'oria' );
			break;
	}
}
