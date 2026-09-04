<?php
/**
 * Oria Digital: finding the practices whose websites are letting them down,
 * and giving them somewhere to ask about it.
 *
 * The directory owner builds websites. The listings hold 130 businesses who
 * all have one. That is a good lead source and a genuinely dangerous one,
 * because Oria Haven's whole pitch is that it is independent — so this
 * module is built around four rules that are enforced here rather than
 * merely intended.
 *
 *   1. Nothing here is ever shown to a practitioner. The flags, the lead
 *      status and the notes are admin-only, stored in protected meta, and
 *      excluded from REST.
 *   2. Nothing here is readable by any query that orders or filters the
 *      directory. Buying a website cannot change placement, because no
 *      placement code can see these fields.
 *   3. A health check only ever happens because somebody asked for one.
 *      There is no path in this file that emails a practice a critique of
 *      their website.
 *   4. A practice mid-way through a claim invitation is never approached
 *      about a website. The two channels share a suppression check.
 *
 * The checks are deliberately shallow: reachable, HTTPS, redirects, a
 * mobile viewport, page weight, title and description, and whether the
 * "website" is really a Facebook page. All of that is objective and can be
 * said to somebody's face. There is no 0–100 score on purpose — a
 * composite invents precision it hasn't earned, and it is the thing that
 * makes an awkward email possible.
 */

declare(strict_types=1);

namespace Oria\Core\Websites;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PATH = 'websites';

/** Options. */
const OPT_NAME    = 'oria_web_service_name';
const OPT_ENABLED = 'oria_web_cta_enabled';

/** Protected post meta — the leading underscore keeps it out of REST. */
const FLAGS   = '_oria_web_flags';    // array of flag slugs
const CHECKED = '_oria_web_checked';  // mysql datetime
const DETAIL  = '_oria_web_detail';   // array of raw findings
const STATUS  = '_oria_web_status';   // lead status slug
const NOTES   = '_oria_web_notes';    // admin free text

/** Lead statuses, in pipeline order. */
const STATUSES = array(
	''             => 'Not contacted',
	'contacted'    => 'Contacted',
	'interested'   => 'Interested',
	'consultation' => 'Consultation booked',
	'proposal'     => 'Proposal sent',
	'won'          => 'Won',
	'lost'         => 'Lost',
	'not_now'      => 'Follow up later',
	'no'           => 'Not interested',
);

/**
 * The flags. Each is a plain fact about the site, phrased so it could be
 * read aloud to the owner without insulting them.
 */
const FLAG_LABELS = array(
	'unreachable' => 'Site did not respond',
	'error'       => 'Site returned an error',
	'no_https'    => 'No HTTPS',
	'social_only' => 'Social or booking page only',
	'no_viewport' => 'No mobile viewport',
	'heavy'       => 'Very heavy page',
	'no_title'    => 'No page title',
	'no_desc'     => 'No meta description',
);

/** Hosts that mean a practice has no site of its own. */
const SOCIAL_HOSTS = array( 'facebook.com', 'instagram.com', 'linktr.ee', 'wixsite.com', 'godaddysites.com', 'square.site', 'fresha.com', 'linktree.com', 'business.site' );

/** Anything over this is worth a conversation. Bytes. */
const HEAVY_BYTES = 3145728; // 3 MB

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );

	add_filter( 'wpseo_title', __NAMESPACE__ . '\title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description' );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical' );
	/*
	 * og:url answers from the same source as the canonical.
	 *
	 * Seven modules here override wpseo_canonical to point a custom route
	 * at its real address. None of them overrode og:url, so Open Graph
	 * kept answering from the main query -- on a facet page that meant
	 * advertising the old /practice/{category}/ URL, which is now a 301
	 * and was never that page. Same question, same answer.
	 */
	add_filter( 'wpseo_opengraph_url', __NAMESPACE__ . '\canonical' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );

	add_action( 'admin_init', __NAMESPACE__ . '\settings' );
	add_filter( 'manage_listing_posts_columns', __NAMESPACE__ . '\column' );
	add_action( 'manage_listing_posts_custom_column', __NAMESPACE__ . '\column_content', 30, 2 );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\metabox' );
	add_action( 'save_post_' . PostTypes\LISTING, __NAMESPACE__ . '\save_metabox' );
	add_action( 'admin_post_oria_web_check', __NAMESPACE__ . '\handle_check' );
	add_action( 'admin_notices', __NAMESPACE__ . '\notice' );

	// The public request form — the only route by which a health check
	// ever reaches a practice, and it starts with them asking.
	add_action( 'admin_post_nopriv_oria_web_request', __NAMESPACE__ . '\handle_request' );
	add_action( 'admin_post_oria_web_request', __NAMESPACE__ . '\handle_request' );
}

/* --------------------------------------------------------------- settings */

function service_name(): string {
	return (string) get_option( OPT_NAME, 'Oria Digital' );
}

function cta_enabled(): bool {
	return (bool) get_option( OPT_ENABLED, true );
}

function url(): string {
	return home_url( '/' . PATH . '/' );
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

	register_setting( 'general', OPT_NAME, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Oria Digital' ) );
	register_setting( 'general', OPT_ENABLED, array( 'type' => 'boolean', 'sanitize_callback' => static fn( $v ): bool => (bool) $v, 'default' => true ) );

	add_settings_field(
		OPT_NAME,
		__( 'Website services', 'oria' ),
		static function (): void {
			// The hidden 0 matters: an unticked checkbox posts nothing, so
			// without it the box could never be switched back off.
			printf(
				'<input type="text" class="regular-text" name="%1$s" id="%1$s" value="%2$s">
				<p class="description">%3$s</p>
				<input type="hidden" name="%4$s" value="0">
				<label style="display:block;margin-top:10px;"><input type="checkbox" name="%4$s" value="1"%5$s> %6$s</label>
				<p class="description">%7$s</p>',
				esc_attr( OPT_NAME ),
				esc_attr( service_name() ),
				esc_html__( 'The name the web-design service trades under, shown on its own page and in any footer.', 'oria' ),
				esc_attr( OPT_ENABLED ),
				checked( true, cta_enabled(), false ),
				esc_html__( 'Add a small website-services line to practitioner emails', 'oria' ),
				esc_html__( 'Small grey print at the foot of emails to a practice about their own listing. It never appears in enquiry receipts, match confirmations, or anything else sent to a member of the public — and never in a first claim invitation, where they have not asked us for anything yet.', 'oria' )
			);
		},
		'general',
		'oria_settings',
		array( 'label_for' => OPT_NAME )
	);
}

/* ------------------------------------------------------------------ route */

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?oria_websites=1', 'top' );
}

function query_vars( array $vars ): array {
	$vars[] = 'oria_websites';
	return $vars;
}

function is_page(): bool {
	return (bool) get_query_var( 'oria_websites' );
}

function fix_query( \WP_Query $q ): void {
	if ( ! $q->is_main_query() || ! $q->get( 'oria_websites' ) ) {
		return;
	}
	$q->is_home = $q->is_front_page = $q->is_archive = $q->is_singular = $q->is_404 = false;
	$q->set( 'posts_per_page', 1 );
}

function template( string $template ): string {
	if ( ! is_page() ) {
		return $template;
	}
	return locate_template( array( 'oria-websites.php' ) ) ?: $template;
}

function title( $t ) {
	return is_page() ? sprintf( '%s — websites for wellness practices in Perth', service_name() ) : $t;
}

function core_title( array $parts ): array {
	if ( is_page() ) {
		$parts['title'] = sprintf( '%s — websites for wellness practices', service_name() );
	}
	return $parts;
}

function description( $d ) {
	return is_page()
		? __( 'Websites built for Perth wellness practices — faster, easier to book from, and easier to find. Ask for a free website review.', 'oria' )
		: $d;
}

function canonical( $u ) {
	return is_page() ? url() : $u;
}

/* ----------------------------------------------------------- the checking */

/**
 * Look at one website and record what is objectively true about it.
 *
 * Shallow on purpose. One polite request, a short timeout, an honest user
 * agent, and no repeat traffic — these are other people's servers and we
 * are not entitled to hammer them.
 *
 * @return array{flags: list<string>, detail: array<string, mixed>}
 */
function check( string $website ): array {
	$flags  = array();
	$detail = array();

	$website = trim( $website );
	if ( '' === $website ) {
		return array( 'flags' => array( 'unreachable' ), 'detail' => array( 'note' => 'no url' ) );
	}
	if ( ! preg_match( '~^https?://~i', $website ) ) {
		$website = 'https://' . $website;
	}

	$host = strtolower( (string) wp_parse_url( $website, PHP_URL_HOST ) );
	$detail['host'] = $host;
	foreach ( SOCIAL_HOSTS as $needle ) {
		if ( false !== strpos( $host, $needle ) ) {
			$flags[] = 'social_only';
			break;
		}
	}

	$response = wp_remote_get(
		$website,
		array(
			'timeout'     => 12,
			'redirection' => 5,
			'user-agent'  => 'OriaHaven/1.0 (+' . home_url( '/' ) . '; website check)',
			'headers'     => array( 'Accept' => 'text/html' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		$flags[]         = 'unreachable';
		$detail['error'] = $response->get_error_message();
		return array( 'flags' => array_values( array_unique( $flags ) ), 'detail' => $detail );
	}

	$code           = (int) wp_remote_retrieve_response_code( $response );
	$body           = (string) wp_remote_retrieve_body( $response );
	$detail['code'] = $code;
	$detail['bytes'] = strlen( $body );

	if ( $code >= 400 ) {
		$flags[] = 'error';
	}

	// The URL we ended on is the one that matters for HTTPS — a site that
	// redirects http to https is fine, and one that doesn't isn't.
	$final = (string) ( wp_remote_retrieve_header( $response, 'location' ) ?: $website );
	if ( 0 !== strpos( strtolower( $final ), 'https://' ) && 0 !== strpos( strtolower( $website ), 'https://' ) ) {
		$flags[] = 'no_https';
	}
	$detail['final'] = $final;

	if ( strlen( $body ) > HEAVY_BYTES ) {
		$flags[] = 'heavy';
	}
	if ( $body && ! preg_match( '~<meta[^>]+name=["\']viewport["\']~i', $body ) ) {
		$flags[] = 'no_viewport';
	}
	if ( $body && ! preg_match( '~<title[^>]*>\s*\S~i', $body ) ) {
		$flags[] = 'no_title';
	}
	if ( $body && ! preg_match( '~<meta[^>]+name=["\']description["\']~i', $body ) ) {
		$flags[] = 'no_desc';
	}

	return array( 'flags' => array_values( array_unique( $flags ) ), 'detail' => $detail );
}

/** Run the check for a listing and store the result. */
function check_listing( int $listing_id ): array {
	$result = check( (string) get_post_meta( $listing_id, 'website', true ) );
	update_post_meta( $listing_id, FLAGS, $result['flags'] );
	update_post_meta( $listing_id, DETAIL, $result['detail'] );
	update_post_meta( $listing_id, CHECKED, current_time( 'mysql' ) );
	return $result;
}

/**
 * Whether this practice is in the middle of a claim conversation.
 *
 * Two approaches landing in the same fortnight is how a directory starts
 * feeling like a funnel, so the website channel defers to the claim one.
 */
function busy_with_claim( int $listing_id ): bool {
	if ( ! defined( 'ORIA_CORE_DIR' ) ) {
		return false;
	}
	$sent = (string) get_post_meta( $listing_id, '_oria_invite_sent', true );
	if ( ! $sent ) {
		return false;
	}
	return ( time() - (int) strtotime( $sent ) ) < 14 * DAY_IN_SECONDS;
}

/* ------------------------------------------------------------ admin column */

function column( array $columns ): array {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'oria_invite' === $key ) {
			$out['oria_web'] = __( 'Website', 'oria' );
		}
	}
	if ( ! isset( $out['oria_web'] ) ) {
		$out['oria_web'] = __( 'Website', 'oria' );
	}
	return $out;
}

function column_content( string $column, int $post_id ): void {
	if ( 'oria_web' !== $column ) {
		return;
	}
	echo wp_kses_post( cell( $post_id ) );
}

function cell( int $post_id ): string {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$site = trim( (string) get_post_meta( $post_id, 'website', true ) );
	if ( ! $site ) {
		return '<span style="color:#646970">' . esc_html__( 'No website listed', 'oria' ) . '</span>';
	}

	$status = (string) get_post_meta( $post_id, STATUS, true );
	$out    = '';
	if ( $status && isset( STATUSES[ $status ] ) ) {
		$out .= '<div><b>' . esc_html( STATUSES[ $status ] ) . '</b></div>';
	}

	$checked = (string) get_post_meta( $post_id, CHECKED, true );
	$flags   = (array) ( get_post_meta( $post_id, FLAGS, true ) ?: array() );

	if ( $checked ) {
		if ( $flags ) {
			$labels = array();
			foreach ( $flags as $f ) {
				$labels[] = FLAG_LABELS[ $f ] ?? $f;
			}
			$out .= '<div style="color:#8a6d00">' . esc_html( implode( ' · ', $labels ) ) . '</div>';
		} else {
			$out .= '<div style="color:#1a7f5a">' . esc_html__( 'Nothing flagged', 'oria' ) . '</div>';
		}
		$out .= '<div style="color:#646970;font-size:12px">' . esc_html(
			sprintf( /* translators: %s: date */ __( 'Checked %s', 'oria' ), date_i18n( 'j M', (int) strtotime( $checked ) ) )
		) . '</div>';
	}

	if ( busy_with_claim( $post_id ) ) {
		$out .= '<div style="color:#646970;font-size:12px">' . esc_html__( 'Claim invite in flight — leave it', 'oria' ) . '</div>';
	}

	$out .= sprintf(
		'<a class="button button-small" href="%s">%s</a>',
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oria_web_check&listing=' . $post_id ), 'oria_web_check_' . $post_id ) ),
		esc_html( $checked ? __( 'Re-check', 'oria' ) : __( 'Check site', 'oria' ) )
	);
	return $out;
}

/* ------------------------------------------------------------- admin check */

function handle_check(): void {
	$id = isset( $_GET['listing'] ) ? (int) $_GET['listing'] : 0;
	if ( ! $id || ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot check this listing.', 'oria' ) );
	}
	check_admin_referer( 'oria_web_check_' . $id );

	$result = check_listing( $id );
	$back   = wp_get_referer() ?: admin_url( 'edit.php?post_type=' . PostTypes\LISTING );

	wp_safe_redirect( add_query_arg( 'oria_web_checked', count( $result['flags'] ), remove_query_arg( 'oria_web_checked', $back ) ) );
	exit;
}

function notice(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['oria_web_checked'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$n = (int) $_GET['oria_web_checked'];
	printf(
		'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
		esc_html(
			$n
				? sprintf( /* translators: %d: number of flags */ _n( 'Checked — %d thing worth a look.', 'Checked — %d things worth a look.', $n, 'oria' ), $n )
				: __( 'Checked — nothing flagged.', 'oria' )
		)
	);
}

/* ---------------------------------------------------------------- metabox */

function metabox(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_meta_box( 'oria-web', __( 'Website opportunity', 'oria' ), __NAMESPACE__ . '\render_metabox', PostTypes\LISTING, 'side', 'low' );
}

function render_metabox( \WP_Post $post ): void {
	wp_nonce_field( 'oria_web_meta', 'oria_web_meta_nonce' );
	echo '<p style="margin-top:0">' . wp_kses_post( cell( $post->ID ) ) . '</p>';

	echo '<p><label for="oria_web_status"><b>' . esc_html__( 'Lead status', 'oria' ) . '</b></label><br>';
	echo '<select name="oria_web_status" id="oria_web_status" style="width:100%">';
	$current = (string) get_post_meta( $post->ID, STATUS, true );
	foreach ( STATUSES as $value => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
	}
	echo '</select></p>';

	printf(
		'<p><label for="oria_web_notes"><b>%s</b></label><br><textarea name="oria_web_notes" id="oria_web_notes" rows="4" style="width:100%%">%s</textarea></p>
		<p class="description">%s</p>',
		esc_html__( 'Notes', 'oria' ),
		esc_textarea( (string) get_post_meta( $post->ID, NOTES, true ) ),
		esc_html__( 'Admin only. Never shown to the practice, and never read by anything that orders the directory.', 'oria' )
	);
}

function save_metabox( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['oria_web_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['oria_web_meta_nonce'] ) ), 'oria_web_meta' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$status = isset( $_POST['oria_web_status'] ) ? sanitize_key( wp_unslash( $_POST['oria_web_status'] ) ) : '';
	update_post_meta( $post_id, STATUS, isset( STATUSES[ $status ] ) ? $status : '' );
	update_post_meta( $post_id, NOTES, sanitize_textarea_field( wp_unslash( (string) ( $_POST['oria_web_notes'] ?? '' ) ) ) );
}

/* --------------------------------------------------- the public request */

/**
 * Somebody has asked for a review of their own website. This is the only
 * way a health check begins.
 */
function handle_request(): void {
	$back = wp_get_referer() ?: url();

	if ( ! isset( $_POST['oria_web_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['oria_web_nonce'] ) ), 'oria_web_request' ) ) {
		wp_safe_redirect( add_query_arg( 'owr', 'error', $back ) );
		exit;
	}
	// Honeypot, same as the other forms.
	if ( ! empty( $_POST['oria_web_hp'] ) ) {
		wp_safe_redirect( add_query_arg( 'owr', 'sent', $back ) );
		exit;
	}

	$name  = sanitize_text_field( wp_unslash( (string) ( $_POST['req_name'] ?? '' ) ) );
	$email = sanitize_email( wp_unslash( (string) ( $_POST['req_email'] ?? '' ) ) );
	$site  = esc_url_raw( wp_unslash( (string) ( $_POST['req_site'] ?? '' ) ) );
	$note  = sanitize_textarea_field( wp_unslash( (string) ( $_POST['req_note'] ?? '' ) ) );

	if ( ! $name || ! is_email( $email ) || ! $site ) {
		wp_safe_redirect( add_query_arg( 'owr', 'error', $back ) );
		exit;
	}

	$result = check( $site );
	$found  = array();
	foreach ( $result['flags'] as $f ) {
		$found[] = '- ' . ( FLAG_LABELS[ $f ] ?? $f );
	}

	wp_mail(
		get_option( 'admin_email' ),
		sprintf( /* translators: %s: website */ __( 'Website review requested: %s', 'oria' ), $site ),
		sprintf(
			"%s <%s> has asked for a review of %s\n\n%s\n\nAutomatic checks found:\n%s\n\nRaw: %s\n\nThey asked, so a reply is expected — but read the site yourself before you send anything.",
			$name,
			$email,
			$site,
			$note ?: '(no note)',
			$found ? implode( "\n", $found ) : '- nothing flagged',
			wp_json_encode( $result['detail'] )
		),
		array( 'Reply-To: ' . $email )
	);

	wp_mail(
		$email,
		sprintf( /* translators: %s: service name */ __( 'Your website review — %s', 'oria' ), service_name() ),
		sprintf(
			"Hi %s,\n\nThanks — we've got your request to look at %s.\n\nSomebody will go through it by hand and come back to you within a couple of days with what we'd change and roughly what it would take. There's no charge for the review and no obligation at the end of it.\n\n%s\n%s\n",
			$name,
			$site,
			service_name(),
			home_url( '/' )
		) . \Oria\Core\Mail\signoff()
	);

	wp_safe_redirect( add_query_arg( 'owr', 'sent', $back ) );
	exit;
}

/* -------------------------------------------------------- the email line */

/**
 * The footer line for practitioner emails, in both flavours.
 *
 * Two forms because our practitioner mail goes out both ways: the claim
 * and plan emails ride the HTML shell, the account emails are plain text.
 * Appending the plain version to an HTML email would render it at body
 * size with a dead URL in the middle of it, which is the opposite of
 * small print.
 *
 * Never added to anything sent to a member of the public — no enquiry
 * receipts, no match confirmations — and never to a cold claim invitation,
 * where a practice has not asked us for anything yet.
 */
function email_line(): string {
	if ( ! cta_enabled() ) {
		return '';
	}
	return sprintf(
		"\n\n—\nLooking for a new website? We can help. %s builds them for wellness practices in Perth.\n%s\n",
		service_name(),
		url()
	);
}

/** The same thing as quiet grey print under an HTML email's body. */
function email_line_html(): string {
	if ( ! cta_enabled() ) {
		return '';
	}
	return sprintf(
		'<p style="margin:22px 0 0;padding-top:14px;border-top:1px solid #EFEDE6;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#8A948F;">%s <a href="%s" style="color:#3F6E60;">%s</a></p>',
		esc_html(
			sprintf(
				/* translators: %s: the web service's name */
				__( 'Looking for a new website? We can help — %s builds them for wellness practices in Perth.', 'oria' ),
				service_name()
			)
		),
		esc_url( url() ),
		esc_html__( 'Have a look', 'oria' )
	);
}
