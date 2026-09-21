<?php
/**
 * "Tell us what is wrong" -- the small door beside claiming.
 *
 * Most people who write in about a listing do not want to run it. They
 * want one line changed: the hours are gone, the phone number moved, the
 * studio closed in March. Until now an unclaimed listing offered them
 * only a claim form, so they emailed Dale instead and the correction
 * lived in an inbox rather than against the listing.
 *
 * So: a form that asks for nothing but the correction, no account, and a
 * record an administrator can act on. Corrections are never held hostage
 * to claiming -- we fix what is wrong either way, and the offer of owner
 * access comes afterwards, once the thing they actually came for is done.
 *
 * Stored as a post type rather than the custom table the brief suggests,
 * because Leads already works that way and it buys the admin list table,
 * search, filtering and capability checks for nothing.
 *
 * @package Oria_Core
 */

declare(strict_types=1);

namespace Oria\Core\Corrections;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CPT = 'oria_correction';

/** Where a correction is up to. */
const NEW_      = 'new';
const APPLIED   = 'applied';
const REJECTED  = 'rejected';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register_cpt' );
	add_action( 'admin_post_oria_correction', __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_nopriv_oria_correction', __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_oria_correction_act', __NAMESPACE__ . '\handle_action' );

	add_filter( 'manage_' . CPT . '_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_' . CPT . '_posts_custom_column', __NAMESPACE__ . '\column_content', 10, 2 );
	add_action( 'add_meta_boxes_' . CPT, __NAMESPACE__ . '\metabox' );
	add_action( 'admin_notices', __NAMESPACE__ . '\notice' );
}

function register_cpt(): void {
	register_post_type(
		CPT,
		array(
			'labels'       => array(
				'name'          => __( 'Corrections', 'oria' ),
				'singular_name' => __( 'Correction', 'oria' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=' . PostTypes\LISTING,
			'supports'     => array( 'title' ),
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		)
	);
}

/**
 * A listing's name as a person reads it.
 *
 * get_the_title() hands back HTML entities, which are invisible in a web
 * page and very visible in a plain-text email: the first correction email
 * went out addressed to "4 Life Sports &#038; Remedial Massage".
 */
function listing_name( int $listing ): string {
	return wp_specialchars_decode( get_the_title( $listing ), ENT_QUOTES );
}

/* ------------------------------------------------------------ vocabulary */

/**
 * What can be wrong, in the words somebody would use.
 *
 * @return array<string, string>
 */
function kinds(): array {
	return array(
		'contact'  => __( 'Phone, email or website', 'oria' ),
		'hours'    => __( 'Opening hours or availability', 'oria' ),
		'address'  => __( 'Address', 'oria' ),
		'services' => __( 'Services or categories', 'oria' ),
		'photos'   => __( 'Photos', 'oria' ),
		'closed'   => __( 'It has closed or moved', 'oria' ),
		'other'    => __( 'Something else', 'oria' ),
	);
}

/**
 * How the sender is connected to the place.
 *
 * Optional on purpose: a regular who noticed the hours are wrong is doing
 * us a favour and should not have to explain themselves. It is asked
 * because the answer decides whether owner access is worth offering.
 *
 * @return array<string, string>
 */
function relationships(): array {
	return array(
		'owner'        => __( 'I own or run it', 'oria' ),
		'team'         => __( 'I work there', 'oria' ),
		'practitioner' => __( 'I practise there', 'oria' ),
		'visitor'      => __( 'I have visited', 'oria' ),
		'other'        => __( 'Something else', 'oria' ),
	);
}

/** Whether this relationship makes owner access worth offering. */
function insider( string $relationship ): bool {
	return in_array( $relationship, array( 'owner', 'team', 'practitioner' ), true );
}

/**
 * What the listing says today about the thing being corrected.
 *
 * Snapshotted at submit time so an administrator reading the record in a
 * week can see what was on the page when somebody objected to it, rather
 * than what it says by then.
 */
function current_value( int $listing, string $kind ): string {
	$get = static function ( string $field ) use ( $listing ): string {
		$v = function_exists( 'get_field' ) ? get_field( $field, $listing ) : get_post_meta( $listing, $field, true );
		if ( is_array( $v ) ) {
			return wp_json_encode( $v ) ?: '';
		}
		return trim( (string) $v );
	};

	switch ( $kind ) {
		case 'contact':
			return trim( sprintf( "phone: %s\nemail: %s\nwebsite: %s", $get( 'phone' ), $get( 'email' ), $get( 'website' ) ) );
		case 'hours':
			$hidden = $get( 'hours_hide' ) ? 'yes' : 'no';
			return trim( sprintf( "hours rows: %s\nno set hours: %s", $get( 'opening_hours' ), $hidden ) );
		case 'address':
			return $get( 'address' );
		case 'services':
			return $get( 'services' );
		case 'photos':
			$gallery = function_exists( 'get_field' ) ? (array) get_field( 'gallery', $listing ) : array();
			return sprintf( '%d photos', count( array_filter( $gallery ) ) );
		case 'closed':
			return (string) get_post_status( $listing );
		default:
			return '';
	}
}

/* ------------------------------------------------------------- the form */

/** Redirect back with a state flag. Never returns. */
function bounce( string $url, string $state ): void {
	$url = remove_query_arg( array( 'ocorr' ), $url );
	wp_safe_redirect( add_query_arg( 'ocorr', $state, $url ) . '#correct' );
	exit;
}

function handle(): void {
	$listing = (int) ( $_POST['listing_id'] ?? 0 );
	$back    = get_permalink( $listing ) ?: home_url( '/' );

	// Bots get a quiet success; a form filled in under three seconds was
	// not read. Same shape as the enquiry guard, for the same reasons.
	if ( '' !== (string) ( $_POST['ocorr_website'] ?? '' ) ) {
		bounce( $back, 'received' );
	}
	$ts = (int) ( $_POST['ocorr_ts'] ?? 0 );
	if ( $ts <= 0 || time() - $ts < 3 || time() - $ts > 12 * HOUR_IN_SECONDS ) {
		bounce( $back, 'error' );
	}
	if ( ! wp_verify_nonce( (string) ( $_POST['ocorr_nonce'] ?? '' ), 'oria_correction_' . $listing ) ) {
		bounce( $back, 'error' );
	}
	$key = 'oria_corr_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		bounce( $back, 'error' );
	}
	set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

	if ( PostTypes\LISTING !== get_post_type( $listing ) || 'publish' !== get_post_status( $listing ) ) {
		bounce( home_url( '/' ), 'error' );
	}

	$kind = sanitize_key( (string) ( $_POST['ocorr_kind'] ?? '' ) );
	$kind = isset( kinds()[ $kind ] ) ? $kind : 'other';

	$rel  = sanitize_key( (string) ( $_POST['ocorr_rel'] ?? '' ) );
	$rel  = isset( relationships()[ $rel ] ) ? $rel : '';

	$what  = sanitize_textarea_field( wp_unslash( (string) ( $_POST['ocorr_what'] ?? '' ) ) );
	$name  = sanitize_text_field( wp_unslash( (string) ( $_POST['ocorr_name'] ?? '' ) ) );
	$email = sanitize_email( wp_unslash( (string) ( $_POST['ocorr_email'] ?? '' ) ) );

	if ( '' === trim( $what ) || '' === trim( $name ) || ! is_email( $email ) ) {
		bounce( $back, 'error' );
	}

	$id = wp_insert_post(
		array(
			'post_type'   => CPT,
			'post_status' => 'publish',
			'post_title'  => sprintf(
				/* translators: 1: listing name, 2: what needs changing */
				__( '%1$s — %2$s', 'oria' ),
				listing_name( $listing ),
				kinds()[ $kind ]
			),
		),
		true
	);

	if ( is_wp_error( $id ) ) {
		bounce( $back, 'error' );
	}

	$meta = array(
		'listing_id'   => $listing,
		'kind'         => $kind,
		'proposed'     => mb_substr( $what, 0, 2000 ),
		'original'     => mb_substr( current_value( $listing, $kind ), 0, 2000 ),
		'sender_name'  => mb_substr( $name, 0, 120 ),
		'sender_email' => $email,
		'relationship' => $rel,
		'status'       => NEW_,
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( (int) $id, '_oria_' . $k, $v );
	}

	notify_staff( (int) $id );

	bounce( $back, insider( $rel ) ? 'received_owner' : 'received' );
}

/** Tell the directory somebody is waiting on us. */
function notify_staff( int $id ): void {
	$to = get_option( 'admin_email' );
	if ( ! $to ) {
		return;
	}
	$listing = (int) get_post_meta( $id, '_oria_listing_id', true );

	wp_mail(
		$to,
		sprintf(
			/* translators: %s: listing name */
			__( '[Oria] Correction suggested for %s', 'oria' ),
			listing_name( $listing )
		),
		sprintf(
			"%s (%s) says:\n\n%s\n\nWhat the listing says now:\n%s\n\nReview: %s\nListing: %s\n",
			get_post_meta( $id, '_oria_sender_name', true ),
			get_post_meta( $id, '_oria_sender_email', true ),
			get_post_meta( $id, '_oria_proposed', true ),
			get_post_meta( $id, '_oria_original', true ) ?: '(nothing recorded)',
			get_edit_post_link( $id, 'raw' ),
			get_permalink( $listing )
		)
	);
}

/* ------------------------------------------------------------ the queue */

/**
 * @param array<string, string> $cols
 * @return array<string, string>
 */
function columns( array $cols ): array {
	$date = $cols['date'] ?? '';
	unset( $cols['date'] );

	$cols['oria_listing'] = __( 'Listing', 'oria' );
	$cols['oria_kind']    = __( 'About', 'oria' );
	$cols['oria_from']    = __( 'From', 'oria' );
	$cols['oria_status']  = __( 'Status', 'oria' );
	if ( '' !== $date ) {
		$cols['date'] = $date;
	}
	return $cols;
}

function column_content( string $col, int $id ): void {
	switch ( $col ) {
		case 'oria_listing':
			$listing = (int) get_post_meta( $id, '_oria_listing_id', true );
			printf(
				'<a href="%s">%s</a>',
				esc_url( (string) get_edit_post_link( $listing ) ),
				esc_html( get_the_title( $listing ) )
			);
			break;

		case 'oria_kind':
			echo esc_html( kinds()[ (string) get_post_meta( $id, '_oria_kind', true ) ] ?? '' );
			break;

		case 'oria_from':
			$rel = (string) get_post_meta( $id, '_oria_relationship', true );
			printf(
				'%s<br><span style="color:#646970">%s</span>',
				esc_html( (string) get_post_meta( $id, '_oria_sender_name', true ) ),
				esc_html( relationships()[ $rel ] ?? '' )
			);
			break;

		case 'oria_status':
			$status = (string) get_post_meta( $id, '_oria_status', true );
			$words  = array(
				NEW_     => __( 'Waiting', 'oria' ),
				APPLIED  => __( 'Applied', 'oria' ),
				REJECTED => __( 'Rejected', 'oria' ),
			);
			$colour = array( NEW_ => '#B47B18', APPLIED => '#2F7D62', REJECTED => '#646970' );
			printf(
				'<b style="color:%s">%s</b>',
				esc_attr( $colour[ $status ] ?? '#646970' ),
				esc_html( $words[ $status ] ?? $status )
			);
			break;
	}
}

function metabox(): void {
	add_meta_box(
		'oria_correction',
		__( 'The correction', 'oria' ),
		__NAMESPACE__ . '\render_metabox',
		CPT,
		'normal',
		'high'
	);
}

function render_metabox( \WP_Post $post ): void {
	$id      = $post->ID;
	$listing = (int) get_post_meta( $id, '_oria_listing_id', true );
	$rel     = (string) get_post_meta( $id, '_oria_relationship', true );
	$status  = (string) get_post_meta( $id, '_oria_status', true );
	$email   = (string) get_post_meta( $id, '_oria_sender_email', true );

	echo '<style>.oriacorr dt{font-weight:600;margin-top:1em}.oriacorr dd{margin:.25em 0 0}.oriacorr pre{white-space:pre-wrap;background:#f6f7f7;padding:.75em;margin:.25em 0 0;border-radius:4px}</style>';
	echo '<dl class="oriacorr">';

	printf(
		'<dt>%s</dt><dd><a href="%s">%s</a> &middot; <a href="%s" target="_blank" rel="noopener">%s</a></dd>',
		esc_html__( 'Listing', 'oria' ),
		esc_url( (string) get_edit_post_link( $listing ) ),
		esc_html( get_the_title( $listing ) ),
		esc_url( (string) get_permalink( $listing ) ),
		esc_html__( 'view public page', 'oria' )
	);

	printf(
		'<dt>%s</dt><dd>%s</dd>',
		esc_html__( 'About', 'oria' ),
		esc_html( kinds()[ (string) get_post_meta( $id, '_oria_kind', true ) ] ?? '' )
	);

	printf(
		'<dt>%s</dt><dd>%s &lt;%s&gt;%s</dd>',
		esc_html__( 'From', 'oria' ),
		esc_html( (string) get_post_meta( $id, '_oria_sender_name', true ) ),
		esc_html( $email ),
		'' !== $rel ? ' &middot; ' . esc_html( relationships()[ $rel ] ?? '' ) : ''
	);

	printf(
		'<dt>%s</dt><dd><pre>%s</pre></dd>',
		esc_html__( 'They say', 'oria' ),
		esc_html( (string) get_post_meta( $id, '_oria_proposed', true ) )
	);

	$original = (string) get_post_meta( $id, '_oria_original', true );
	printf(
		'<dt>%s</dt><dd><pre>%s</pre></dd>',
		esc_html__( 'What the listing said when they wrote', 'oria' ),
		esc_html( '' !== $original ? $original : __( '(nothing recorded for this kind)', 'oria' ) )
	);

	echo '</dl><hr>';

	if ( NEW_ !== $status ) {
		$by   = (int) get_post_meta( $id, '_oria_reviewed_by', true );
		$who  = $by ? get_userdata( $by ) : false;
		$when = (string) get_post_meta( $id, '_oria_reviewed_at', true );
		printf(
			'<p><b>%s</b> %s</p>',
			esc_html( APPLIED === $status ? __( 'Applied', 'oria' ) : __( 'Rejected', 'oria' ) ),
			esc_html( trim( sprintf( '%s %s', $who ? $who->display_name : '', $when ) ) )
		);
	}

	/*
	 * The actions. Applying the correction itself happens on the listing --
	 * these record what was decided and, where it helps, tell the person
	 * who wrote in. Nothing here is conditional on them claiming anything.
	 */
	$base = admin_url( 'admin-post.php?action=oria_correction_act&correction=' . $id );
	$mk   = static fn( string $do ): string => wp_nonce_url( $base . '&do=' . $do, 'oria_correction_act_' . $id );

	echo '<p>';
	printf(
		'<a class="button button-primary" href="%s">%s</a> ',
		esc_url( $mk( 'applied' ) ),
		esc_html__( 'Mark applied and thank them', 'oria' )
	);
	printf(
		'<a class="button" href="%s">%s</a> ',
		esc_url( $mk( 'applied_quiet' ) ),
		esc_html__( 'Mark applied, no email', 'oria' )
	);
	printf(
		'<a class="button" href="%s">%s</a>',
		esc_url( $mk( 'rejected' ) ),
		esc_html__( 'Reject', 'oria' )
	);
	echo '</p>';

	if ( $listing && ! get_post_meta( $listing, 'claimed_by', true ) ) {
		echo '<p>';
		printf(
			'<a class="button" href="%s">%s</a> <span style="color:#646970">%s</span>',
			esc_url( $mk( 'invite' ) ),
			esc_html__( 'Invite them to manage the listing', 'oria' ),
			esc_html__( 'Sends the claim link. Offer this only when they said they run the place.', 'oria' )
		);
		echo '</p>';
	}
}

function handle_action(): void {
	$id = (int) ( $_GET['correction'] ?? 0 );
	$do = sanitize_key( (string) ( $_GET['do'] ?? '' ) );

	if ( ! $id || ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ) );
	}
	check_admin_referer( 'oria_correction_act_' . $id );

	$listing = (int) get_post_meta( $id, '_oria_listing_id', true );
	$back    = (string) get_edit_post_link( $id, 'raw' );
	$flag    = '';

	switch ( $do ) {
		case 'applied':
		case 'applied_quiet':
			update_post_meta( $id, '_oria_status', APPLIED );
			update_post_meta( $id, '_oria_reviewed_by', get_current_user_id() );
			update_post_meta( $id, '_oria_reviewed_at', current_time( 'mysql' ) );
			if ( 'applied' === $do ) {
				thank_them( $id );
				$flag = 'applied_sent';
			} else {
				$flag = 'applied';
			}
			break;

		case 'rejected':
			update_post_meta( $id, '_oria_status', REJECTED );
			update_post_meta( $id, '_oria_reviewed_by', get_current_user_id() );
			update_post_meta( $id, '_oria_reviewed_at', current_time( 'mysql' ) );
			$flag = 'rejected';
			break;

		case 'invite':
			invite( $id );
			update_post_meta( $id, '_oria_invited_at', current_time( 'mysql' ) );
			$flag = 'invited';
			break;
	}

	wp_safe_redirect( add_query_arg( 'oria_corr', $flag, $back ) );
	exit;
}

/**
 * The email that closes the loop.
 *
 * Says the thing was fixed, links the page so they can see it, and only
 * then mentions owner access -- and only to somebody who said they are
 * connected to the place. Nothing is conditional on them taking it up.
 */
function thank_them( int $id ): void {
	$listing = (int) get_post_meta( $id, '_oria_listing_id', true );
	$email   = (string) get_post_meta( $id, '_oria_sender_email', true );
	$name    = (string) get_post_meta( $id, '_oria_sender_name', true );
	$rel     = (string) get_post_meta( $id, '_oria_relationship', true );

	if ( ! is_email( $email ) ) {
		return;
	}

	$first = trim( (string) strtok( $name, ' ' ) );
	$body  = sprintf(
		/* translators: 1: first name, 2: listing name, 3: listing URL */
		__( "Hi %1\$s,\n\nThank you for telling us — the %2\$s page has been updated.\n\n%3\$s\n", 'oria' ),
		'' !== $first ? $first : __( 'there', 'oria' ),
		listing_name( $listing ),
		get_permalink( $listing )
	);

	if ( insider( $rel ) && ! get_post_meta( $listing, 'claimed_by', true ) ) {
		$body .= sprintf(
			/* translators: %s: the listing page with the claim form */
			__( "\nIf it would help, you can also look after the page yourself — keeping the details, hours and photos right. It is free, there is no card, and the profile is already built, so there is nothing to set up.\n\n%s\n", 'oria' ),
			get_permalink( $listing ) . '#claim'
		);
	}

	$body .= "\n" . ( function_exists( '\Oria\Core\Mail\signoff' ) ? \Oria\Core\Mail\signoff() : __( 'Oria Haven', 'oria' ) );

	wp_mail(
		$email,
		sprintf(
			/* translators: %s: listing name */
			__( '%s has been updated on Oria Haven', 'oria' ),
			listing_name( $listing )
		),
		$body
	);
}

/** Offer owner access to somebody who said they run the place. */
function invite( int $id ): void {
	$listing = (int) get_post_meta( $id, '_oria_listing_id', true );
	$email   = (string) get_post_meta( $id, '_oria_sender_email', true );
	$name    = (string) get_post_meta( $id, '_oria_sender_name', true );

	if ( ! is_email( $email ) ) {
		return;
	}

	$first = trim( (string) strtok( $name, ' ' ) );

	wp_mail(
		$email,
		sprintf(
			/* translators: %s: listing name */
			__( 'Managing %s on Oria Haven', 'oria' ),
			listing_name( $listing )
		),
		sprintf(
			/* translators: 1: first name, 2: listing name, 3: claim URL */
			__( "Hi %1\$s,\n\nYou mentioned you are connected to %2\$s. If you would like, you can look after its page yourself — the contact details, hours, services and photos.\n\nIt is free, no card is needed, and the profile is already built, so there is nothing to set up from scratch.\n\n%3\$s\n\nIf you would rather we just kept it up to date, that is fine too — send anything that changes and we will fix it.\n", 'oria' ),
			'' !== $first ? $first : __( 'there', 'oria' ),
			listing_name( $listing ),
			get_permalink( $listing ) . '#claim'
		) . "\n" . ( function_exists( '\Oria\Core\Mail\signoff' ) ? \Oria\Core\Mail\signoff() : __( 'Oria Haven', 'oria' ) )
	);
}

function notice(): void {
	$flag = isset( $_GET['oria_corr'] ) ? sanitize_key( (string) wp_unslash( $_GET['oria_corr'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' === $flag ) {
		return;
	}
	$words = array(
		'applied'      => __( 'Marked as applied.', 'oria' ),
		'applied_sent' => __( 'Marked as applied, and they have been thanked.', 'oria' ),
		'rejected'     => __( 'Marked as rejected.', 'oria' ),
		'invited'      => __( 'Invitation sent.', 'oria' ),
	);
	if ( isset( $words[ $flag ] ) ) {
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $words[ $flag ] ) );
	}
}
