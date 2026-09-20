<?php
/**
 * "Submit an event": a free, public route for anyone running a wellness
 * event in Perth to get it onto the site.
 *
 * Free on purpose, and permanently. The directory's problem is not too many
 * events, and an organiser who has to pay to tell us about a Tuesday sound
 * bath simply doesn't tell us. Claiming a listing is a separate thing and
 * stays that way.
 *
 * Nothing published automatically. A submission becomes a DRAFT event for
 * review, exactly like a crawler find, and carries who sent it. The spam
 * walls are the ones signup.php already uses -- nonce, honeypot, fill-time
 * floor, per-IP throttle -- because they are known to work here.
 *
 * Duplicates are flagged rather than refused: an organiser submitting an
 * event the crawler already found is not doing anything wrong, and a
 * reviewer wants to see both and keep the organiser's version.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\EventSubmit;

use Oria\Core\Signup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PATH      = 'submit-an-event';
const QUERY_VAR = 'oria_submit_event';
const MAX_BYTES = 5 * 1024 * 1024;
const MIMES     = array( 'image/jpeg', 'image/png', 'image/webp' );
const THROTTLE  = 5; // submissions per IP per hour

function bootstrap(): void {
	add_action( 'admin_post_nopriv_oria_event_submit', __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_oria_event_submit', __NAMESPACE__ . '\handle' );
	add_filter( 'display_post_states', __NAMESPACE__ . '\post_state', 10, 2 );

	/*
	 * The page itself is a route, not a WordPress page. A page template
	 * attaches by slug, so the form only existed where somebody had
	 * created that page by hand -- which meant it 404'd on production
	 * after a deploy that carried every other part of the feature.
	 */
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\seo_title', 20 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\seo_description', 20 );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\seo_canonical', 20 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 20 );
}

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QUERY_VAR . '=1', 'top' );
}

/** @param string[] $vars @return string[] */
function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

function is_page(): bool {
	return (bool) get_query_var( QUERY_VAR );
}

/** A parameterless rule otherwise reads as the home page. */
function fix_query( \WP_Query $q ): void {
	if ( ! $q->is_main_query() || ! $q->get( QUERY_VAR ) ) {
		return;
	}
	$q->is_home       = false;
	$q->is_front_page = false;
	$q->is_archive    = false;
	$q->is_singular   = false;
	$q->is_404        = false;
	$q->set( 'posts_per_page', 1 );
}

function template( string $template ): string {
	if ( ! is_page() ) {
		return $template;
	}
	$found = locate_template( array( 'oria-submit-event.php' ) );
	return $found ?: $template;
}

function seo_title( $title ) {
	return is_page() ? __( 'Submit an event | Oria Haven', 'oria' ) : $title;
}

function core_title( array $parts ): array {
	if ( is_page() ) {
		$parts['title'] = __( 'Submit an event', 'oria' );
	}
	return $parts;
}

function seo_description( $desc ) {
	return is_page()
		? __( 'Running a wellness event in Perth? Tell us about it and we will put it on the What\'s On page. Free, no account needed.', 'oria' )
		: $desc;
}

function seo_canonical( $canonical ) {
	return is_page() ? page_url() : $canonical;
}

function page_url(): string {
	return home_url( '/' . PATH . '/' );
}

/** Marks submitted events in the admin list, so review order is obvious. */
function post_state( array $states, \WP_Post $post ): array {
	if ( 'event' === $post->post_type && '' !== (string) get_post_meta( $post->ID, '_oria_submitted', true ) ) {
		$states['oria_submitted'] = '' !== (string) get_post_meta( $post->ID, '_oria_possible_dupe', true )
			? __( 'Submitted · possible duplicate', 'oria' )
			: __( 'Submitted by organiser', 'oria' );
	}
	return $states;
}

/* ---------------------------------------------------------------- submit */

function handle(): void {
	if ( ! wp_verify_nonce( (string) ( $_POST['oria_event_nonce'] ?? '' ), 'oria_event_submit' ) ) {
		bounce( array( 'expired' ) );
	}
	if ( '' !== (string) ( $_POST['oform_website'] ?? '' ) ) {
		bounce( array( 'spam' ) );
	}
	if ( time() - (int) ( $_POST['oria_ts'] ?? 0 ) < 4 ) {
		bounce( array( 'spam' ) );
	}
	$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'oria_event_submit_' . md5( $ip );
	if ( (int) get_transient( $key ) >= THROTTLE ) {
		bounce( array( 'throttled' ) );
	}

	$in = array(
		'title'       => sanitize_text_field( wp_unslash( (string) ( $_POST['title'] ?? '' ) ) ),
		'organiser'   => sanitize_text_field( wp_unslash( (string) ( $_POST['organiser'] ?? '' ) ) ),
		'email'       => sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ),
		'start_date'  => sanitize_text_field( (string) ( $_POST['start_date'] ?? '' ) ),
		'start_time'  => sanitize_text_field( (string) ( $_POST['start_time'] ?? '' ) ),
		'end_date'    => sanitize_text_field( (string) ( $_POST['end_date'] ?? '' ) ),
		'end_time'    => sanitize_text_field( (string) ( $_POST['end_time'] ?? '' ) ),
		'type'        => sanitize_title( (string) ( $_POST['type'] ?? '' ) ),
		'venue'       => sanitize_text_field( wp_unslash( (string) ( $_POST['venue'] ?? '' ) ) ),
		'suburb'      => sanitize_title( (string) ( $_POST['suburb'] ?? '' ) ),
		'price'       => sanitize_text_field( wp_unslash( (string) ( $_POST['price'] ?? '' ) ) ),
		'free'        => ! empty( $_POST['free'] ),
		'description' => sanitize_textarea_field( wp_unslash( (string) ( $_POST['description'] ?? '' ) ) ),
		'booking_url' => esc_url_raw( wp_unslash( (string) ( $_POST['booking_url'] ?? '' ) ) ),
		'practice'    => sanitize_text_field( wp_unslash( (string) ( $_POST['practice'] ?? '' ) ) ),
		'authorised'  => ! empty( $_POST['authorised'] ),
	);

	$errors = validate( $in );
	$file   = image();
	if ( is_string( $file ) ) {
		$errors[] = $file;
		$file     = null;
	}
	if ( $errors ) {
		bounce( $errors, $in );
	}

	$start = trim( $in['start_date'] . ' ' . ( '' !== $in['start_time'] ? $in['start_time'] . ':00' : '00:00:00' ) );
	$end   = '' !== $in['end_date'] || '' !== $in['end_time']
		? trim( ( '' !== $in['end_date'] ? $in['end_date'] : $in['start_date'] ) . ' ' . ( '' !== $in['end_time'] ? $in['end_time'] . ':00' : '00:00:00' ) )
		: '';

	$id = wp_insert_post(
		array(
			'post_type'   => 'event',
			'post_status' => 'draft',
			'post_title'  => $in['title'],
			'post_name'   => wp_unique_post_slug( sanitize_title( $in['title'] ), 0, 'publish', 'event', 0 ),
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		bounce( array( 'failed' ), $in );
	}
	$id = (int) $id;

	save( $id, $in, $start, $end );
	attach_image( $id, $file );
	flag_duplicate( $id, $in, $start );

	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	notify( $id, $in );

	wp_safe_redirect( add_query_arg( 'sent', '1', page_url() ) . '#submit' );
	exit;
}

/** @return string[] error codes */
function validate( array $in ): array {
	$errors = array();

	if ( '' === $in['title'] ) {
		$errors[] = 'title';
	}
	if ( '' === $in['email'] || ! is_email( $in['email'] ) ) {
		$errors[] = 'email';
	}
	if ( ! $in['authorised'] ) {
		$errors[] = 'authorised';
	}
	if ( '' === $in['type'] || ! term_exists( $in['type'], 'event_type' ) ) {
		$errors[] = 'type';
	}

	$start = strtotime( $in['start_date'] . ' ' . ( $in['start_time'] ?: '00:00' ) );
	if ( ! $in['start_date'] || ! $start ) {
		$errors[] = 'start';
	} elseif ( $start < (int) current_time( 'timestamp' ) ) {
		$errors[] = 'past';
	}
	if ( '' !== $in['end_date'] && $start ) {
		$end = strtotime( $in['end_date'] . ' ' . ( $in['end_time'] ?: '23:59' ) );
		if ( $end && $end < $start ) {
			$errors[] = 'end';
		}
	}
	if ( '' !== $in['suburb'] && ! term_exists( $in['suburb'], 'area' ) ) {
		$errors[] = 'suburb';
	}
	/*
	 * A cap the form also states. Generous -- three hundred words is far
	 * more than any event needs -- and its job is to stop a paste of an
	 * entire website, not to police a wordy organiser.
	 */
	if ( str_word_count( $in['description'] ) > 300 ) {
		$errors[] = 'description';
	}

	return $errors;
}

/**
 * The uploaded image, validated on its bytes rather than its name.
 * Mirrors Signup\photos(), for one optional file.
 *
 * @return array<string, mixed>|string|null
 */
function image() {
	if ( empty( $_FILES['image'] ) || '' === (string) ( $_FILES['image']['name'] ?? '' ) ) {
		return null;
	}
	$raw = $_FILES['image']; // phpcs:ignore WordPress.Security -- validated below.

	if ( UPLOAD_ERR_OK !== (int) $raw['error'] ) {
		return 'image_upload';
	}
	if ( (int) $raw['size'] > MAX_BYTES ) {
		return 'image_size';
	}
	$tmp  = (string) $raw['tmp_name'];
	$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( false === $info || ! in_array( (string) ( $info['mime'] ?? '' ), MIMES, true ) ) {
		return 'image_type';
	}
	$check = wp_check_filetype_and_ext( $tmp, (string) $raw['name'] );
	if ( empty( $check['ext'] ) || ! in_array( (string) $check['type'], MIMES, true ) ) {
		return 'image_type';
	}

	return array(
		'name'     => sanitize_file_name( (string) $raw['name'] ),
		'type'     => (string) $info['mime'],
		'tmp_name' => $tmp,
		'error'    => 0,
		'size'     => (int) $raw['size'],
	);
}

function attach_image( int $id, ?array $file ): void {
	if ( null === $file ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$_FILES['oria_event_one'] = $file;
	$att                      = media_handle_upload( 'oria_event_one', $id );
	unset( $_FILES['oria_event_one'] );

	if ( ! is_wp_error( $att ) ) {
		set_post_thumbnail( $id, (int) $att );
		// Provenance: the organiser gave us this, which is why it may be shown.
		update_post_meta( (int) $att, '_oria_image_source', 'organiser-submitted' );
	}
}

function save( int $id, array $in, string $start, string $end ): void {
	$suburb = '';
	if ( '' !== $in['suburb'] ) {
		$term   = get_term_by( 'slug', $in['suburb'], 'area' );
		$suburb = $term instanceof \WP_Term ? $term->name : '';
	}

	$fields = array(
		'event_start'       => array( $start, 'field_oria_event_start' ),
		'event_end'         => array( $end, 'field_oria_event_end' ),
		'price'             => array( $in['free'] ? __( 'Free', 'oria' ) : $in['price'], 'field_oria_event_price' ),
		'venue'             => array( trim( implode( ', ', array_filter( array( $in['venue'], $suburb ) ) ) ), 'field_oria_event_venue' ),
		'event_description' => array( '' !== $in['description'] ? wpautop( esc_html( $in['description'] ) ) : '', 'field_oria_event_description' ),
		'booking_url'       => array( $in['booking_url'], 'field_oria_event_booking' ),
	);
	foreach ( $fields as $name => $pair ) {
		if ( '' !== $pair[0] ) {
			update_post_meta( $id, $name, $pair[0] );
			update_post_meta( $id, "_{$name}", $pair[1] );
		}
	}

	// The organiser's email is for verifying the submission, never display.
	update_post_meta( $id, '_oria_submitted', current_time( 'mysql' ) );
	update_post_meta( $id, '_oria_submitter_email', $in['email'] );
	update_post_meta( $id, '_oria_organiser', $in['organiser'] );
	update_post_meta( $id, '_oria_src', 'organiser' );
	update_post_meta( $id, '_oria_verified', current_time( 'mysql' ) );

	wp_set_object_terms( $id, $in['type'], 'event_type' );
	if ( function_exists( '\Oria\Ingest\Taxonomy\practice_for' ) ) {
		$practice = \Oria\Ingest\Taxonomy\practice_for( $in['type'] );
		if ( '' !== $practice ) {
			wp_set_object_terms( $id, $practice, 'practice' );
		}
	}
	if ( '' !== $in['suburb'] ) {
		$area = term_exists( $in['suburb'], 'area' );
		if ( $area ) {
			wp_set_object_terms( $id, (int) $area['term_id'], 'area' );
		}
	}

	// "My practice is already on Oria": matched by name, never assumed.
	if ( '' !== $in['practice'] ) {
		$hit = get_posts(
			array(
				'post_type'      => 'listing',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				's'              => $in['practice'],
			)
		);
		if ( $hit ) {
			update_post_meta( $id, 'listing', (int) $hit[0] );
			update_post_meta( $id, '_listing', 'field_oria_event_listing' );
		} else {
			update_post_meta( $id, '_oria_practice_claim', $in['practice'] );
		}
	}
}

/**
 * Note when this looks like an event already here.
 *
 * Not a rejection. An organiser submitting the event our crawler found is
 * behaving perfectly reasonably, and their version -- real image, real
 * details, a person to ask -- is the one worth keeping. The reviewer just
 * needs to see the pair.
 */
function flag_duplicate( int $id, array $in, string $start ): void {
	if ( ! function_exists( '\Oria\Ingest\Pipeline\find_twin' ) ) {
		return;
	}
	$fingerprint = \Oria\Ingest\Pipeline\fingerprint( $in['title'], $start, $in['suburb'] );
	update_post_meta( $id, '_oria_fingerprint', $fingerprint );

	$twin = \Oria\Ingest\Pipeline\find_twin( $fingerprint, $in['title'], $start, $in['booking_url'], $in['title'] );
	if ( $twin && $twin !== $id ) {
		update_post_meta( $id, '_oria_possible_dupe', $twin );
	}
}

function notify( int $id, array $in ): void {
	if ( ! function_exists( '\Oria\Core\Signup\send' ) ) {
		return;
	}
	$edit = admin_url( 'post.php?post=' . $id . '&action=edit' );
	$dupe = (int) get_post_meta( $id, '_oria_possible_dupe', true );

	Signup\send(
		(string) get_option( 'admin_email' ),
		sprintf( '[Oria Haven] Event submitted: %s', $in['title'] ),
		__( 'An event is waiting for review', 'oria' ),
		sprintf(
			'<p>%s</p><p>%s<br>%s<br>%s</p>%s<p><a href="%s">%s</a></p>',
			esc_html( $in['title'] ),
			esc_html( sprintf( /* translators: %s: organiser */ __( 'From: %s', 'oria' ), $in['organiser'] ?: $in['email'] ) ),
			esc_html( sprintf( /* translators: %s: date */ __( 'Starts: %s', 'oria' ), $in['start_date'] . ' ' . $in['start_time'] ) ),
			esc_html( sprintf( /* translators: %s: venue */ __( 'Where: %s', 'oria' ), trim( $in['venue'] . ' ' . $in['suburb'] ) ) ),
			$dupe ? '<p><strong>' . esc_html__( 'Possibly already on the site — check before publishing.', 'oria' ) . '</strong></p>' : '',
			esc_url( $edit ),
			esc_html__( 'Review it', 'oria' )
		)
	);

	Signup\send(
		$in['email'],
		__( 'Thanks — your event is with us', 'oria' ),
		__( 'Event received', 'oria' ),
		sprintf(
			'<p>%s</p><p>%s</p>',
			esc_html( sprintf( /* translators: %s: event title */ __( 'We have "%s" and a person will check it within a couple of days. You will not hear from us again unless something needs clarifying.', 'oria' ), $in['title'] ) ),
			esc_html__( 'If the details change before then, reply to this email and tell us.', 'oria' )
		)
	);
}

/** Stash what was typed, bounce back with error codes. Never returns. */
function bounce( array $errors, array $in = array() ): void {
	$args = array( 'e' => implode( ',', array_map( 'sanitize_key', $errors ) ) );
	if ( $in ) {
		set_transient( 'oria_event_in_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), $in, 10 * MINUTE_IN_SECONDS );
	}
	wp_safe_redirect( add_query_arg( $args, page_url() ) . '#submit' );
	exit;
}

/** What the visitor last typed, so a bounce doesn't empty the form. */
function stashed(): array {
	$in = get_transient( 'oria_event_in_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
	return is_array( $in ) ? $in : array();
}

/**
 * Which field each error belongs to.
 *
 * The summary above the form links every message to the control that
 * caused it, which is the difference between "something is wrong" and
 * "this box is wrong". Codes with no field -- the spam walls, a failed
 * insert -- are about the submission rather than an input.
 *
 * @return array<string, string>
 */
function error_fields(): array {
	return array(
		'title'        => 'title',
		'email'        => 'email',
		'authorised'   => 'authorised',
		'type'         => 'type',
		'start'        => 'start_date',
		'past'         => 'start_date',
		'end'          => 'end_date',
		'suburb'       => 'suburb',
		'image_upload' => 'image',
		'image_size'   => 'image',
		'image_type'   => 'image',
		'description'  => 'description',
	);
}

/** Human wording for each error code. @return string[] */
function messages( string $codes ): array {
	$all = array(
		'expired'      => __( 'That form sat open too long. Please send it again.', 'oria' ),
		'spam'         => __( 'That looked automated. Please try again.', 'oria' ),
		'throttled'    => __( 'That is several submissions in an hour — give it a little while.', 'oria' ),
		'title'        => __( 'The event needs a name.', 'oria' ),
		'email'        => __( 'We need a working email to check the details with you.', 'oria' ),
		'authorised'   => __( 'Please confirm you are allowed to submit this event and its image.', 'oria' ),
		'type'         => __( 'Choose the kind of event it is.', 'oria' ),
		'start'        => __( 'The start date is missing or unreadable.', 'oria' ),
		'past'         => __( 'That start date has already passed.', 'oria' ),
		'end'          => __( 'The event cannot finish before it starts.', 'oria' ),
		'suburb'       => __( 'Pick a suburb from the list.', 'oria' ),
		'image_upload' => __( 'The image did not upload. Please try again.', 'oria' ),
		'image_size'   => __( 'Images need to be under 5MB.', 'oria' ),
		'image_type'   => __( 'Images must be JPEG, PNG or WebP.', 'oria' ),
		'description'  => __( 'That description is very long — please keep it under about 300 words.', 'oria' ),
		'failed'       => __( 'Something went wrong at our end. Please try again.', 'oria' ),
	);

	$out = array();
	foreach ( explode( ',', $codes ) as $code ) {
		if ( isset( $all[ $code ] ) ) {
			$out[] = $all[ $code ];
		}
	}
	return $out;
}
