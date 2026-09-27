<?php
/**
 * Website help: the enquiry behind /websites/ (Oria Digital).
 *
 * A practice -- listed or not -- asks for help with its website. This file
 * takes the request, keeps it, and tells Dale. Four rules:
 *
 *   1. The lead is stored BEFORE anybody is emailed. A mail failure marks
 *      the record and raises an admin notice; it never loses the request.
 *   2. The submitted website is a reference for a person to open, not a
 *      target for this server. Nothing here fetches it.
 *   3. Nothing is shared with a listed practice, added to a list, or sent
 *      to analytics beyond the fact that a request was accepted.
 *   4. The notification goes to a configured address only. With none set,
 *      the public form is closed and the admin is told why.
 *
 * Records are a private post type: not public, not queryable, not in REST,
 * search or sitemaps, and only readable by administrators.
 *
 * @package OriaCore
 */

declare(strict_types=1);

namespace Oria\Core\WebHelp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CPT           = 'oria_web_lead';
const OPT_RECIPIENT = 'oria_web_help_recipient';
const OPT_REVIEW    = 'oria_web_help_review_line';
const ACTION        = 'oria_web_help';
const NONCE         = 'oria_web_help_nonce';

/** Pipeline statuses, in order. */
const STATUSES = array(
	'new'       => 'New',
	'contacted' => 'Contacted',
	'quoted'    => 'Quoted',
	'won'       => 'Won',
	'closed'    => 'Closed',
);

/** What somebody can ask about. Keys travel in ?service= and the form. */
const SERVICES = array(
	'tuneup'  => 'Website tune-up',
	'landing' => 'Workshop or offer page',
	'unsure'  => 'Not sure yet',
);
const BUDGETS = array(
	''          => 'Prefer not to say',
	'under1k'   => 'Under $1,000',
	'1to3k'     => '$1,000 to $3,000',
	'over3k'    => 'Over $3,000',
	'unsure'    => 'Not sure',
);
const PLATFORMS = array(
	''            => 'Not sure',
	'wordpress'   => 'WordPress',
	'squarespace' => 'Squarespace',
	'wix'         => 'Wix',
	'shopify'     => 'Shopify',
	'other'       => 'Something else',
);

/** Where a request came from: an allowlist, never free text from the URL. */
const SOURCES = array( 'page', 'dashboard', 'claim', 'footer', 'email' );

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register_cpt' );
	add_action( 'admin_init', __NAMESPACE__ . '\settings' );
	add_action( 'admin_post_' . ACTION, __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_nopriv_' . ACTION, __NAMESPACE__ . '\handle' );
	add_action( 'admin_notices', __NAMESPACE__ . '\admin_notices' );
	add_filter( 'manage_' . CPT . '_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_' . CPT . '_posts_custom_column', __NAMESPACE__ . '\column_content', 10, 2 );
	add_action( 'add_meta_boxes_' . CPT, __NAMESPACE__ . '\metaboxes' );
	add_action( 'save_post_' . CPT, __NAMESPACE__ . '\save_status' );
}

function register_cpt(): void {
	register_post_type(
		CPT,
		array(
			'labels'              => array(
				'name'          => __( 'Website help requests', 'oria' ),
				'singular_name' => __( 'Website help request', 'oria' ),
				'menu_name'     => __( 'Website help', 'oria' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'show_in_nav_menus'   => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-admin-site-alt3',
			'menu_position'       => 58,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			// Administrators only: no editor or author ever sees a lead.
			'capabilities'        => array(
				'create_posts'       => 'do_not_allow',
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => 'manage_options',
				'edit_post'          => 'manage_options',
				'read_post'          => 'manage_options',
				'delete_post'        => 'manage_options',
				'delete_posts'       => 'manage_options',
				'publish_posts'      => 'manage_options',
				'read_private_posts' => 'manage_options',
			),
			'map_meta_cap'        => false,
		)
	);
}

/* --------------------------------------------------------------- settings */

function recipient(): string {
	$to = sanitize_email( (string) get_option( OPT_RECIPIENT, '' ) );
	return is_email( $to ) ? $to : '';
}

/** The public form only opens when somebody will actually receive it. */
function open(): bool {
	return '' !== recipient();
}

/** The review offer line, editable, never a promise of a deadline. */
function review_line(): string {
	$line = trim( (string) get_option( OPT_REVIEW, '' ) );
	return '' !== $line
		? $line
		: __( 'Start with a short review: up to three practical suggestions, with no obligation to go ahead.', 'oria' );
}

function settings(): void {
	add_settings_section(
		'oria_settings',
		__( 'Oria Haven', 'oria' ),
		static function (): void {
			echo '<p>' . esc_html__( 'Settings for the directory itself.', 'oria' ) . '</p>';
		},
		'general'
	);
	register_setting( 'general', OPT_RECIPIENT, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => '' ) );
	register_setting( 'general', OPT_REVIEW, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
	add_settings_field(
		OPT_RECIPIENT,
		__( 'Website help enquiries', 'oria' ),
		static function (): void {
			printf(
				'<input type="email" class="regular-text" name="%1$s" id="%1$s" value="%2$s" placeholder="you@example.com">
				<p class="description">%3$s</p>
				<p><label for="%4$s">%5$s</label><br><input type="text" class="large-text" name="%4$s" id="%4$s" value="%6$s" placeholder="%7$s"></p>',
				esc_attr( OPT_RECIPIENT ),
				esc_attr( recipient() ),
				esc_html__( 'Where website-help requests from /websites/ are emailed. Every request is also kept under Website help in this admin. While this is empty the public form stays closed.', 'oria' ),
				esc_attr( OPT_REVIEW ),
				esc_html__( 'Review offer (shown on the page)', 'oria' ),
				esc_attr( (string) get_option( OPT_REVIEW, '' ) ),
				esc_attr( review_line() )
			);
		},
		'general',
		'oria_settings',
		array( 'label_for' => OPT_RECIPIENT )
	);
}

/* ------------------------------------------------------------- the handler */

/** Back to the form with a state, and the typed values when it failed. */
function back( string $state, array $keep = array() ): void {
	$args = array( 'wh' => $state );
	if ( $keep ) {
		$token = strtolower( wp_generate_password( 20, false ) );
		set_transient( 'oria_wh_' . $token, $keep, 10 * MINUTE_IN_SECONDS );
		$args['whv'] = $token;
	}
	$base = function_exists( '\Oria\Core\Websites\url' ) ? \Oria\Core\Websites\url() : home_url( '/websites/' );
	wp_safe_redirect( add_query_arg( $args, $base ) . '#request' );
	exit;
}

/** The kept values, once. */
function recall(): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a random token, display only.
	$token = sanitize_key( (string) ( $_GET['whv'] ?? '' ) );
	if ( '' === $token ) {
		return array();
	}
	$kept = get_transient( 'oria_wh_' . $token );
	delete_transient( 'oria_wh_' . $token );
	return is_array( $kept ) ? $kept : array();
}

/**
 * The submitted values, cleaned, plus the first problem found ('' if none).
 *
 * @return array{0: array<string, string>, 1: string}
 */
function read_post(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle().
	$get = static fn( string $k ): string => (string) wp_unslash( (string) ( $_POST[ $k ] ?? '' ) );

	$v = array(
		'name'     => mb_substr( sanitize_text_field( $get( 'wh_name' ) ), 0, 120 ),
		'business' => mb_substr( sanitize_text_field( $get( 'wh_business' ) ), 0, 160 ),
		'email'    => mb_substr( sanitize_email( $get( 'wh_email' ) ), 0, 190 ),
		'website'  => mb_substr( trim( $get( 'wh_website' ) ), 0, 300 ),
		'phone'    => mb_substr( sanitize_text_field( $get( 'wh_phone' ) ), 0, 40 ),
		'service'  => sanitize_key( $get( 'wh_service' ) ),
		'message'  => mb_substr( sanitize_textarea_field( $get( 'wh_message' ) ), 0, 1500 ),
		'budget'   => sanitize_key( $get( 'wh_budget' ) ),
		'platform' => sanitize_key( $get( 'wh_platform' ) ),
		'source'   => sanitize_key( $get( 'wh_src' ) ),
	);
	// phpcs:enable

	if ( ! isset( SERVICES[ $v['service'] ] ) ) {
		$v['service'] = 'unsure';
	}
	if ( ! isset( BUDGETS[ $v['budget'] ] ) ) {
		$v['budget'] = '';
	}
	if ( ! isset( PLATFORMS[ $v['platform'] ] ) ) {
		$v['platform'] = '';
	}
	if ( ! in_array( $v['source'], SOURCES, true ) ) {
		$v['source'] = 'page';
	}

	// A bare domain is what people type; give it a scheme, then insist on http(s).
	if ( '' !== $v['website'] && ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $v['website'] ) ) {
		$v['website'] = 'https://' . $v['website'];
	}
	$url_ok = (bool) preg_match( '#^https?://[^\s/$.?\#][^\s]*\.[^\s]{2,}$#i', $v['website'] )
		&& in_array( strtolower( (string) wp_parse_url( $v['website'], PHP_URL_SCHEME ) ), array( 'http', 'https' ), true );
	$v['website'] = $url_ok ? esc_url_raw( $v['website'], array( 'http', 'https' ) ) : $v['website'];

	$problem = '';
	if ( '' === $v['name'] ) {
		$problem = 'name';
	} elseif ( '' === $v['business'] ) {
		$problem = 'business';
	} elseif ( ! is_email( $v['email'] ) ) {
		$problem = 'email';
	} elseif ( ! $url_ok ) {
		$problem = 'website';
	} elseif ( mb_strlen( trim( $v['message'] ) ) < 10 ) {
		$problem = 'message';
	}
	return array( $v, $problem );
}

function handle(): void {
	// Bots filling the honeypot are told it worked and nothing is kept.
	if ( '' !== (string) ( $_POST['wh_hp'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		back( 'sent' );
	}

	list( $v, $problem ) = read_post();
	$keep = array_diff_key( $v, array( 'source' => 1 ) );
	// Hand back what they typed, so a mistyped address can be corrected
	// rather than retyped. It is only ever re-displayed, escaped.
	$keep['email'] = mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['wh_email'] ?? '' ) ) ), 0, 190 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( ! open() ) {
		back( 'closed', $keep );
	}
	if ( ! isset( $_POST[ NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ NONCE ] ) ), ACTION ) ) {
		back( 'expired', $keep );
	}
	$ts = (int) ( $_POST['wh_ts'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $ts <= 0 || time() - $ts < 3 || time() - $ts > 12 * HOUR_IN_SECONDS ) {
		back( 'slow', $keep );
	}
	if ( '' !== $problem ) {
		back( 'invalid-' . $problem, $keep );
	}

	// A hashed visitor key -- never the address itself -- for the throttle.
	$who  = 'oria_wh_rl_' . md5( wp_salt( 'nonce' ) . (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$seen = (int) get_transient( $who );
	if ( $seen >= 5 ) {
		back( 'busy', $keep );
	}
	set_transient( $who, $seen + 1, HOUR_IN_SECONDS );

	// The same person pressing twice (or re-sending within ten minutes) is
	// one request, not two. They are told it arrived, because it did.
	$dupe = 'oria_wh_dup_' . md5( strtolower( $v['email'] ) . '|' . strtolower( $v['website'] ) );
	if ( get_transient( $dupe ) ) {
		back( 'sent' );
	}

	$id = save( $v );
	if ( ! $id ) {
		back( 'server', $keep );
	}
	set_transient( $dupe, $id, 10 * MINUTE_IN_SECONDS );

	notify( $id, $v );
	back( 'sent' );
}

/** Store the request. Returns the record id, or 0. */
function save( array $v ): int {
	$id = wp_insert_post(
		array(
			'post_type'   => CPT,
			'post_status' => 'private',
			'post_title'  => sprintf( '%s — %s', $v['business'], $v['name'] ),
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	foreach ( array( 'name', 'business', 'email', 'website', 'phone', 'service', 'message', 'budget', 'platform', 'source' ) as $k ) {
		update_post_meta( (int) $id, '_wh_' . $k, $v[ $k ] );
	}
	update_post_meta( (int) $id, '_wh_status', 'new' );

	// The related listing comes from who is signed in, never from the form.
	if ( is_user_logged_in() && function_exists( '\Oria\Core\ListingEditor\listing_for' ) ) {
		$listing = (int) \Oria\Core\ListingEditor\listing_for( get_current_user_id() );
		if ( $listing ) {
			update_post_meta( (int) $id, '_wh_listing', $listing );
		}
	}
	return (int) $id;
}

/**
 * Tell the recipient. The visitor's address is Reply-To, validated and
 * stripped of line breaks so it cannot inject a header. A failure is
 * recorded on the lead and surfaced in the admin.
 */
function notify( int $id, array $v ): bool {
	$to = recipient();
	if ( '' === $to ) {
		update_post_meta( $id, '_wh_mail', 'failed' );
		return false;
	}
	$reply = str_replace( array( "\r", "\n" ), '', $v['email'] );
	$lines = array(
		sprintf( 'Name: %s', $v['name'] ),
		sprintf( 'Business: %s', $v['business'] ),
		sprintf( 'Email: %s', $v['email'] ),
		sprintf( 'Website: %s', $v['website'] ),
		sprintf( 'Phone: %s', '' !== $v['phone'] ? $v['phone'] : '—' ),
		sprintf( 'Service: %s', SERVICES[ $v['service'] ] ?? $v['service'] ),
		sprintf( 'Budget: %s', BUDGETS[ $v['budget'] ] ?? '—' ),
		sprintf( 'Platform: %s', PLATFORMS[ $v['platform'] ] ?? '—' ),
		sprintf( 'Came from: %s', $v['source'] ),
		'',
		'What they would like to improve:',
		$v['message'],
		'',
		sprintf( 'Open the request: %s', admin_url( 'post.php?post=' . $id . '&action=edit' ) ),
		'',
		'The website has not been fetched or checked by the site. Open it yourself before replying.',
	);
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( is_email( $reply ) ) {
		$headers[] = 'Reply-To: ' . $reply;
	}
	$ok = (bool) wp_mail(
		$to,
		sprintf( /* translators: %s: business name */ __( 'Website help request: %s', 'oria' ), $v['business'] ),
		implode( "\n", $lines ),
		$headers
	);
	update_post_meta( $id, '_wh_mail', $ok ? 'sent' : 'failed' );
	return $ok;
}

/* ------------------------------------------------------------------ admin */

function admin_notices(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! open() ) {
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Website help: no enquiry recipient is set, so the form on /websites/ is closed.', 'oria' ),
			esc_url( admin_url( 'options-general.php#' . OPT_RECIPIENT ) ),
			esc_html__( 'Set one in Settings → General.', 'oria' )
		);
	}
	$failed = get_posts(
		array(
			'post_type'      => CPT,
			'post_status'    => 'private',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'meta_key'       => '_wh_mail', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 'failed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( $failed ) {
		printf(
			'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
			esc_html( sprintf( _n( 'Website help: %d request was saved but its notification email did not send.', 'Website help: %d requests were saved but their notification emails did not send.', count( $failed ), 'oria' ), count( $failed ) ) ),
			esc_url( admin_url( 'edit.php?post_type=' . CPT ) ),
			esc_html__( 'Review them', 'oria' )
		);
	}
}

function columns( array $cols ): array {
	return array(
		'cb'         => $cols['cb'] ?? '',
		'title'      => __( 'Request', 'oria' ),
		'wh_service' => __( 'Service', 'oria' ),
		'wh_website' => __( 'Website', 'oria' ),
		'wh_status'  => __( 'Status', 'oria' ),
		'wh_mail'    => __( 'Email', 'oria' ),
		'date'       => __( 'Received', 'oria' ),
	);
}

function column_content( string $col, int $id ): void {
	switch ( $col ) {
		case 'wh_service':
			echo esc_html( SERVICES[ (string) get_post_meta( $id, '_wh_service', true ) ] ?? '' );
			break;
		case 'wh_website':
			$u = (string) get_post_meta( $id, '_wh_website', true );
			echo $u ? '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) wp_parse_url( $u, PHP_URL_HOST ) ) . '</a>' : '';
			break;
		case 'wh_status':
			echo esc_html( STATUSES[ (string) get_post_meta( $id, '_wh_status', true ) ] ?? 'New' );
			break;
		case 'wh_mail':
			echo 'failed' === get_post_meta( $id, '_wh_mail', true ) ? '<b style="color:#b32d2e">' . esc_html__( 'Not sent', 'oria' ) . '</b>' : esc_html__( 'Sent', 'oria' );
			break;
	}
}

function metaboxes(): void {
	add_meta_box( 'oria-wh-detail', __( 'The request', 'oria' ), __NAMESPACE__ . '\render_detail', CPT, 'normal', 'high' );
	add_meta_box( 'oria-wh-status', __( 'Status', 'oria' ), __NAMESPACE__ . '\render_status', CPT, 'side', 'high' );
}

function render_detail( \WP_Post $post ): void {
	$m = static fn( string $k ): string => (string) get_post_meta( $post->ID, '_wh_' . $k, true );
	$rows = array(
		__( 'Name', 'oria' )     => esc_html( $m( 'name' ) ),
		__( 'Business', 'oria' ) => esc_html( $m( 'business' ) ),
		__( 'Email', 'oria' )    => '<a href="mailto:' . esc_attr( $m( 'email' ) ) . '">' . esc_html( $m( 'email' ) ) . '</a>',
		__( 'Website', 'oria' )  => '<a href="' . esc_url( $m( 'website' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $m( 'website' ) ) . '</a>',
		__( 'Phone', 'oria' )    => esc_html( $m( 'phone' ) ?: '—' ),
		__( 'Service', 'oria' )  => esc_html( SERVICES[ $m( 'service' ) ] ?? '' ),
		__( 'Budget', 'oria' )   => esc_html( BUDGETS[ $m( 'budget' ) ] ?? '—' ),
		__( 'Platform', 'oria' ) => esc_html( PLATFORMS[ $m( 'platform' ) ] ?? '—' ),
		__( 'Came from', 'oria' ) => esc_html( $m( 'source' ) ),
	);
	$listing = (int) $m( 'listing' );
	if ( $listing ) {
		$rows[ __( 'Their listing', 'oria' ) ] = '<a href="' . esc_url( (string) get_edit_post_link( $listing ) ) . '">' . esc_html( get_the_title( $listing ) ) . '</a>';
	}
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $rows as $label => $html ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
	}
	echo '</tbody></table>';
	echo '<h4>' . esc_html__( 'What they would like to improve', 'oria' ) . '</h4>';
	echo '<p style="white-space:pre-wrap">' . esc_html( $m( 'message' ) ) . '</p>';
	if ( 'failed' === $m( 'mail' ) ) {
		echo '<p style="color:#b32d2e"><b>' . esc_html__( 'The notification email for this request did not send.', 'oria' ) . '</b> ' . esc_html__( 'Changing the status marks it as seen.', 'oria' ) . '</p>';
	}
	echo '<p class="description">' . esc_html__( 'Private. Never shared with a listed practice, never added to a mailing list, and never read by anything that orders the directory.', 'oria' ) . '</p>';
}

function render_status( \WP_Post $post ): void {
	wp_nonce_field( 'oria_wh_status', 'oria_wh_status_nonce' );
	$current = (string) get_post_meta( $post->ID, '_wh_status', true ) ?: 'new';
	echo '<select name="oria_wh_status" style="width:100%">';
	foreach ( STATUSES as $k => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $current, $k, false ), esc_html( $label ) );
	}
	echo '</select>';
}

function save_status( int $id ): void {
	if ( ! isset( $_POST['oria_wh_status_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['oria_wh_status_nonce'] ) ), 'oria_wh_status' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = sanitize_key( wp_unslash( (string) ( $_POST['oria_wh_status'] ?? 'new' ) ) );
	update_post_meta( $id, '_wh_status', isset( STATUSES[ $s ] ) ? $s : 'new' );
	// Somebody has looked at it: a failed notification no longer needs shouting about.
	if ( 'failed' === get_post_meta( $id, '_wh_mail', true ) ) {
		update_post_meta( $id, '_wh_mail', 'seen' );
	}
}
