<?php
/**
 * Writing a review: the form's other half.
 *
 * The shape of the flow, and why:
 *
 *   1. Anyone can fill the form in. Asking somebody to make an account
 *      before they have written a word is how review forms die.
 *   2. On submit, the whole draft is put in a signed, single-use token and
 *      emailed to them. Nothing is stored against the listing yet.
 *   3. Clicking the link proves the address, activates the member, and only
 *      then creates the review — held for moderation, as every review is.
 *
 * A member who is already signed in and verified skips steps 2 and 3.
 *
 * The review is written with wp_insert_comment() rather than
 * wp_new_comment(): by this point the submission has been through the
 * honeypot, the time trap, the nonce, the throttle, per-field validation,
 * the practitioner wall and an email round-trip, and inserting directly
 * means the status and every field are set explicitly rather than inferred
 * from $_POST inside core's filter chain.
 */

declare(strict_types=1);

namespace Oria\Core\ReviewSubmit;

use Oria\Core\Audience;
use Oria\Core\Members;
use Oria\Core\PostTypes;
use Oria\Core\Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PATH       = 'review';
const QUERY_VAR  = 'oria_review_token';
const REWRITE_V  = '1';
const MAX_BODY   = 1000;
const PER_DAY    = 3;   // reviews one person may file in 24 hours
const IP_PER_DAY = 5;

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 20 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\handle_link' );

	add_action( 'admin_post_oria_review', __NAMESPACE__ . '\handle' );
	add_action( 'admin_post_nopriv_oria_review', __NAMESPACE__ . '\handle' );

	// Say what happened even when the visitor lands somewhere without a
	// review form to say it for us.
	add_action( 'wp_body_open', __NAMESPACE__ . '\global_notice' );
}

function route(): void {
	add_rewrite_rule( '^' . PATH . '/([A-Za-z0-9]+)/?$', 'index.php?' . QUERY_VAR . '=$matches[1]', 'top' );
}

function maybe_flush(): void {
	if ( get_option( 'oria_review_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_review_rewrite_v', REWRITE_V );
	}
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/* --------------------------------------------------------------- messages */

/**
 * What to tell somebody, for a given outcome.
 *
 * One map, used by both the form and the site-wide notice. It used to live
 * inline in the template, which meant an outcome that sent a visitor
 * anywhere other than a listing page — a stale sign-in link lands on the
 * home page, because by then there is no stored return address — produced a
 * query string and no message at all.
 *
 * @return array{text: string, kind: string}|null
 */
function message_for( string $state, string $why = '' ): ?array {
	$says = array(
		'sent'         => __( 'Nearly there — check your email and tap the link to confirm your review. It lasts 30 minutes.', 'oria' ),
		'queued'       => __( 'Thank you. Your review is with us to read, and appears once it is approved.', 'oria' ),
		'stale'        => __( 'That link had already been used, or it expired. Write your review again and we will send a fresh one.', 'oria' ),
		'reported'     => __( 'Thank you — we will look at that review again. It stays published while we do.', 'oria' ),
		'reply-queued' => __( 'Your reply is with us to read. It appears under the review once approved.', 'oria' ),
	);

	$errors = array(
		'rating'      => __( 'Choose a star rating.', 'oria' ),
		'service'     => __( 'Tell us what you tried.', 'oria' ),
		'recommend'   => __( 'Let us know whether you would recommend it.', 'oria' ),
		'already'     => __( 'You have already reviewed this one. One review each keeps them honest.', 'oria' ),
		'throttled'   => __( 'That is a lot of reviews for one day. Try again tomorrow.', 'oria' ),
		'expired'     => __( 'That form had been open a while. Have another go.', 'oria' ),
		'timing'      => __( 'That form had been open a while. Have another go.', 'oria' ),
		'browser'     => __( 'That sign-in did not finish safely. Please try again.', 'oria' ),
		'state'       => __( 'That sign-in link had already been used, or it sat too long before coming back. Please try again.', 'oria' ),
		'cancelled'   => __( 'No problem — you can still review using your email instead.', 'oria' ),
		'unverified'  => __( 'That Google account has no confirmed email address, so we cannot use it.', 'oria' ),
		'unavailable' => __( 'Signing in with Google is not available at the moment. Use your email instead.', 'oria' ),
		'not_owner'   => __( 'Only the practice that owns this listing can reply to its reviews.', 'oria' ),
		'reason'      => __( 'Choose a reason for the report.', 'oria' ),
		'empty'       => __( 'Write something before sending the reply.', 'oria' ),
	);

	$blocks = array(
		'oria_practitioner_email' => __( 'That email is already registered as a practice on Oria Haven. Practices cannot post reviews.', 'oria' ),
		'oria_practitioner'       => __( 'Practices listed on Oria Haven cannot post reviews.', 'oria' ),
		'oria_staff_email'        => __( 'That email belongs to a staff account.', 'oria' ),
		'oria_staff'              => __( 'Staff accounts cannot post reviews.', 'oria' ),
		'oria_member_muted'       => __( 'This account is now listed as a practice, so it can no longer post reviews.', 'oria' ),
		'oria_member_banned'      => __( 'This account cannot post reviews.', 'oria' ),
		'oria_bad_email'          => __( 'That email address does not look right.', 'oria' ),
	);

	if ( isset( $says[ $state ] ) ) {
		return array( 'text' => $says[ $state ], 'kind' => in_array( $state, array( 'sent', 'queued' ), true ) ? 'done' : 'info' );
	}

	if ( 'error' === $state ) {
		return array( 'text' => $errors[ $why ] ?? __( 'That did not go through. Have another go.', 'oria' ), 'kind' => 'error' );
	}

	if ( 'blocked' === $state ) {
		// A failed Google round trip is a retry, not a dead end.
		$retryable = array( 'browser', 'state', 'cancelled', 'unverified', 'code', 'nonce', 'aud', 'iss', 'expired', 'exchange', 'network', 'malformed', 'no_id_token', 'unavailable' );
		$text      = $blocks[ $why ] ?? ( $errors[ $why ] ?? __( 'That account cannot post reviews.', 'oria' ) );
		return array( 'text' => $text, 'kind' => in_array( $why, $retryable, true ) ? 'error' : 'done' );
	}

	return null;
}

/**
 * The same message, shown wherever the visitor happened to land.
 *
 * The listing page prints it inside the review form. Everywhere else — the
 * home page, most often, after a sign-in link went stale — this is the only
 * thing that says what happened.
 */
function global_notice(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$state = isset( $_GET['review'] ) ? sanitize_key( wp_unslash( (string) $_GET['review'] ) ) : '';
	$why   = isset( $_GET['why'] ) ? sanitize_key( wp_unslash( (string) $_GET['why'] ) ) : '';
	// phpcs:enable

	if ( '' === $state || is_singular( PostTypes\LISTING ) ) {
		return; // the form on a listing page says it better, and in context
	}

	$message = message_for( $state, $why );
	if ( null === $message ) {
		return;
	}

	printf(
		'<div class="authnotice authnotice--%s"><div class="wrap"><p>%s</p></div></div>',
		esc_attr( $message['kind'] ),
		esc_html( $message['text'] )
	);
}

/* ------------------------------------------------------------ redirection */

/**
 * Back to the listing with a state to render.
 *
 * States: sent (check your inbox), queued (it's with us), error, plus the
 * error codes from Members\can_review() so the page can say what actually
 * stopped it.
 */
function back( int $listing_id, string $state, string $detail = '' ): void {
	$url = $listing_id > 0 ? (string) get_permalink( $listing_id ) : home_url( '/' );
	$url = add_query_arg( array_filter( array( 'review' => $state, 'why' => $detail ) ), $url );
	wp_safe_redirect( $url . '#reviews' );
	exit;
}

/* ------------------------------------------------------------- submission */

function handle(): void {
	$listing_id = isset( $_POST['listing'] ) ? (int) $_POST['listing'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( PostTypes\LISTING !== get_post_type( $listing_id ) || 'publish' !== get_post_status( $listing_id ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	// --- spam walls, cheapest first ------------------------------------
	// A filled honeypot gets a cheerful "sent" and goes nowhere: telling a
	// bot it failed only teaches it to try again differently.
	if ( '' !== (string) ( $_POST['oria_website'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		back( $listing_id, 'sent' );
	}

	$ts = isset( $_POST['oria_ts'] ) ? (int) $_POST['oria_ts'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $ts <= 0 || time() - $ts < 4 || time() - $ts > 12 * HOUR_IN_SECONDS ) {
		back( $listing_id, 'error', 'timing' );
	}

	if ( ! wp_verify_nonce( (string) ( $_POST['oria_review_nonce'] ?? '' ), 'oria_review_' . $listing_id ) ) {
		back( $listing_id, 'error', 'expired' );
	}

	if ( ip_over_limit() ) {
		back( $listing_id, 'error', 'throttled' );
	}

	// --- who is this ----------------------------------------------------
	$member = Members\current();
	$signed_in_and_ready = null !== $member && Members\STATUS_ACTIVE === $member['status'];

	if ( is_user_logged_in() ) {
		// A signed-in practitioner or staff member is refused outright, and
		// told why rather than being shown a dead form.
		$can = Members\can_review();
		if ( is_wp_error( $can ) && ! in_array( $can->get_error_code(), array( 'oria_not_member', 'oria_member_pending' ), true ) ) {
			back( $listing_id, 'blocked', $can->get_error_code() );
		}
	}

	// --- the review itself ----------------------------------------------
	$draft = collect( $listing_id );
	if ( is_wp_error( $draft ) ) {
		back( $listing_id, 'error', $draft->get_error_code() );
	}

	if ( $signed_in_and_ready ) {
		if ( has_reviewed( $listing_id, (int) $member['member_id'] ) ) {
			back( $listing_id, 'error', 'already' );
		}
		$stored = store( $listing_id, (int) $member['member_id'], $draft );
		back( $listing_id, is_wp_error( $stored ) ? 'error' : 'queued', is_wp_error( $stored ) ? $stored->get_error_code() : '' );
	}

	// --- otherwise, verify the address first ------------------------------
	$email = sanitize_email( (string) ( $_POST['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$name  = sanitize_text_field( (string) ( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	$may = Members\email_may_join( $email );
	if ( is_wp_error( $may ) ) {
		back( $listing_id, 'blocked', $may->get_error_code() );
	}

	$existing = Members\by_email( $email );
	if ( null !== $existing && has_reviewed( $listing_id, (int) $existing['member_id'] ) ) {
		back( $listing_id, 'error', 'already' );
	}

	$token = Members\mint_token(
		$email,
		'verify',
		array(
			'listing' => $listing_id,
			'name'    => $name,
			'draft'   => $draft,
		)
	);

	send_verification( $email, $listing_id, $token );

	back( $listing_id, 'sent' );
}

/**
 * Read and validate the form. Everything is checked against the listing or
 * the vocabulary it claims to come from — a term id in the POST proves
 * nothing on its own.
 *
 * @return array<string,mixed>|\WP_Error
 */
function collect( int $listing_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the caller.
	$rating = isset( $_POST['oria_rating'] ) ? Reviews\normalise_rating( wp_unslash( $_POST['oria_rating'] ) ) : 0.0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( $rating <= 0 ) {
		return new \WP_Error( 'rating', __( 'Choose a star rating.', 'oria' ) );
	}

	$service = isset( $_POST['service'] ) ? (int) $_POST['service'] : 0;
	if ( $service > 0 && ! in_array( $service, tried_options( $listing_id, true ), true ) ) {
		return new \WP_Error( 'service', __( 'That is not something this place offers.', 'oria' ) );
	}
	if ( 0 === $service ) {
		return new \WP_Error( 'service', __( 'Tell us what you tried.', 'oria' ) );
	}

	$recommend_raw = (string) ( $_POST['recommend'] ?? '' );
	if ( ! in_array( $recommend_raw, array( 'yes', 'no' ), true ) ) {
		return new \WP_Error( 'recommend', __( 'Let us know whether you would recommend it.', 'oria' ) );
	}

	$return_raw = (string) ( $_POST['would_return'] ?? '' );
	$experience = sanitize_key( (string) ( $_POST['experience'] ?? '' ) );
	$visit      = sanitize_text_field( (string) ( $_POST['visit_month'] ?? '' ) );
	$body       = sanitize_textarea_field( (string) ( $_POST['body'] ?? '' ) );

	$best_for = array();
	foreach ( (array) ( $_POST['best_for'] ?? array() ) as $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( isset( Audience\vocabulary()[ $slug ] ) ) {
			$term = get_term_by( 'slug', $slug, Audience\TAXONOMY );
			if ( $term instanceof \WP_Term ) {
				$best_for[] = (int) $term->term_id;
			}
		}
	}
	// phpcs:enable

	if ( ! array_key_exists( $experience, Reviews\experience_levels() ) ) {
		$experience = '';
	}

	if ( '' !== $visit && ! preg_match( '/^\d{4}-\d{2}$/', $visit ) ) {
		$visit = '';
	}

	if ( mb_strlen( $body ) > MAX_BODY ) {
		$body = mb_substr( $body, 0, MAX_BODY );
	}

	return array(
		'rating'       => $rating,
		'service'      => $service,
		'experience'   => $experience,
		'best_for'     => array_values( array_unique( $best_for ) ),
		'recommend'    => 'yes' === $recommend_raw ? 1 : 0,
		'would_return' => in_array( $return_raw, array( 'yes', 'no' ), true ) ? ( 'yes' === $return_raw ? 1 : 0 ) : '',
		'visit_month'  => $visit,
		'body'         => $body,
	);
}

/* ------------------------------------------------------------ the link back */

/** The emailed link: verify the address, then write the review. */
function handle_link(): void {
	$token = (string) get_query_var( QUERY_VAR );
	if ( '' === $token ) {
		return;
	}

	$row = Members\consume_token( $token, 'verify' );
	if ( null === $row ) {
		wp_safe_redirect( add_query_arg( 'review', 'stale', home_url( '/' ) ) );
		exit;
	}

	$email      = (string) $row['email'];
	$payload    = (array) $row['payload'];
	$listing_id = (int) ( $payload['listing'] ?? 0 );
	$draft      = (array) ( $payload['draft'] ?? array() );

	if ( $listing_id <= 0 || ! $draft ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	$member = Members\by_email( $email );
	if ( null === $member ) {
		$member = Members\create( $email, (string) ( $payload['name'] ?? '' ) );
		if ( is_wp_error( $member ) ) {
			back( $listing_id, 'blocked', $member->get_error_code() );
		}
	}

	// The address is now proven, which is the whole point of the round trip.
	Members\activate( (int) $member['member_id'] );

	// Re-check the wall: the person may have claimed a listing between
	// writing the review and clicking the link.
	$can = Members\can_review( (int) $member['user_id'] );
	if ( is_wp_error( $can ) ) {
		back( $listing_id, 'blocked', $can->get_error_code() );
	}

	if ( has_reviewed( $listing_id, (int) $member['member_id'] ) ) {
		back( $listing_id, 'error', 'already' );
	}

	$stored = store( $listing_id, (int) $member['member_id'], $draft );
	back( $listing_id, is_wp_error( $stored ) ? 'error' : 'queued' );
}

/* ---------------------------------------------------------------- storage */

/** Has this member already reviewed this listing? One each, for ever. */
function has_reviewed( int $listing_id, int $member_id ): bool {
	$found = get_comments(
		array(
			'post_id'    => $listing_id,
			'status'     => 'all',
			'number'     => 1,
			'count'      => true,
			'meta_key'   => Reviews\META_MEMBER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => (string) $member_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	return (int) $found > 0;
}

/**
 * Write the review, unapproved, with everything it carries.
 *
 * @param array<string,mixed> $draft
 * @return int|\WP_Error Comment id.
 */
function store( int $listing_id, int $member_id, array $draft ) {
	$member = Members\get( $member_id );
	if ( null === $member ) {
		return new \WP_Error( 'member', __( 'That account no longer exists.', 'oria' ) );
	}

	if ( member_over_limit( $member_id ) ) {
		return new \WP_Error( 'throttled', __( 'That is a lot of reviews for one day. Try again tomorrow.', 'oria' ) );
	}

	$user = get_userdata( (int) $member['user_id'] );

	$comment_id = wp_insert_comment(
		array(
			'comment_post_ID'      => $listing_id,
			'comment_author'       => (string) $member['display_name'],
			'comment_author_email' => $user instanceof \WP_User ? $user->user_email : '',
			'comment_content'      => (string) ( $draft['body'] ?? '' ),
			'comment_type'         => 'comment',
			'user_id'              => (int) $member['user_id'],
			// Every review is held. Not a setting — a rule.
			'comment_approved'     => 0,
		)
	);

	if ( ! $comment_id ) {
		return new \WP_Error( 'insert', __( 'Could not save that review.', 'oria' ) );
	}

	$comment_id = (int) $comment_id;
	$flags      = flags_for( (string) ( $draft['body'] ?? '' ) );

	update_comment_meta( $comment_id, Reviews\META_RATING, Reviews\normalise_rating( $draft['rating'] ?? 0 ) );
	update_comment_meta( $comment_id, Reviews\META_MEMBER, $member_id );
	update_comment_meta( $comment_id, Reviews\META_SERVICE, (int) ( $draft['service'] ?? 0 ) );
	update_comment_meta( $comment_id, Reviews\META_RECOMMEND, (int) ( $draft['recommend'] ?? 0 ) );
	update_comment_meta( $comment_id, Reviews\META_IP, ip_hash() );

	foreach ( array(
		Reviews\META_EXPERIENCE => (string) ( $draft['experience'] ?? '' ),
		Reviews\META_VISIT      => (string) ( $draft['visit_month'] ?? '' ),
	) as $key => $value ) {
		if ( '' !== $value ) {
			update_comment_meta( $comment_id, $key, $value );
		}
	}

	if ( '' !== (string) ( $draft['would_return'] ?? '' ) ) {
		update_comment_meta( $comment_id, Reviews\META_RETURN, (int) $draft['would_return'] );
	}

	if ( ! empty( $draft['best_for'] ) ) {
		update_comment_meta( $comment_id, Reviews\META_BEST_FOR, array_map( 'intval', (array) $draft['best_for'] ) );
	}

	if ( $flags ) {
		update_comment_meta( $comment_id, Reviews\META_FLAGS, $flags );
	}

	Members\update( $member_id, array( 'reviews_count' => (int) $member['reviews_count'] + 1 ) );

	notify_admin( $comment_id, $listing_id, $flags );

	return $comment_id;
}

/* -------------------------------------------------------------- moderation */

/**
 * Words that mean a human must read this one properly.
 *
 * Not a rejection: a false positive that silently bins somebody's review is
 * worse than a queue. This only routes it to a person — which is where every
 * review goes anyway, so today it is really an early warning for the
 * moderator, and the guard that stops health claims slipping through when
 * approval is eventually partly automated.
 *
 * @return array<int,string>
 */
function flags_for( string $body ): array {
	if ( '' === trim( $body ) ) {
		return array();
	}

	$flags = array();
	$text  = ' ' . lower( $body ) . ' ';

	// Health and outcome claims. Every page on this site is kept clear of
	// this language; a free-text box is the one place it can walk back in.
	$health = array(
		'cured', 'cure', 'healed', 'heals', 'diagnos', 'treated my', 'treatment for my',
		'my anxiety', 'my depression', 'my adhd', 'my cancer', 'chronic pain',
		'fixed my', 'got rid of my', 'medication', 'prescribed',
	);
	foreach ( $health as $needle ) {
		if ( false !== strpos( $text, $needle ) ) {
			$flags[] = 'health-claim';
			break;
		}
	}

	if ( preg_match( '~https?://|www\.~i', $body ) ) {
		$flags[] = 'link';
	}

	if ( preg_match( '/(.)\1{6,}/u', $body ) ) {
		$flags[] = 'repetition';
	}

	return array_values( array_unique( $flags ) );
}

/* --------------------------------------------------------------- throttles */

/**
 * A salted hash of the address, never the address itself.
 *
 * HMAC-SHA256 keyed on the site's own salt rather than wp_hash(), which is
 * HMAC-MD5. IPv4 is a small enough space (four billion) that a plain digest
 * is reversible by anyone with a laptop and a weekend; keying it on a secret
 * this database does not contain is what actually makes it one-way. Kept
 * only for spotting one person filing twenty reviews.
 */
/**
 * Lower-case, without assuming mbstring.
 *
 * WordPress polyfills mb_substr() and mb_strlen() and nothing else, so
 * mb_strtolower() is a fatal error on a host built without the extension.
 * Rare, but a directory full of names like "Beauté Boudoir" should not
 * fall over on one, and the ASCII fallback is only ever reached there.
 */
function lower( string $text ): string {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
}

function ip_hash(): string {
	$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	return '' === $ip ? '' : hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
}

function ip_over_limit(): bool {
	$key = 'oria_rev_ip_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$n   = (int) get_transient( $key );
	if ( $n >= IP_PER_DAY ) {
		return true;
	}
	set_transient( $key, $n + 1, DAY_IN_SECONDS );
	return false;
}

function member_over_limit( int $member_id ): bool {
	$key = 'oria_rev_m_' . $member_id;
	$n   = (int) get_transient( $key );
	if ( $n >= PER_DAY ) {
		return true;
	}
	set_transient( $key, $n + 1, DAY_IN_SECONDS );
	return false;
}

/* ------------------------------------------------------------------ emails */

function send_verification( string $email, int $listing_id, string $token ): void {
	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );
	$link = home_url( '/' . PATH . '/' . rawurlencode( $token ) . '/' );

	$body = '<p>' . esc_html__( 'Thanks for writing about', 'oria' ) . ' <strong>' . esc_html( $name ) . '</strong>.</p>'
		. '<p>' . esc_html__( 'One tap confirms it is really you, and your review goes to us to read before it appears.', 'oria' ) . '</p>'
		. '<p style="margin:26px 0"><a href="' . esc_url( $link ) . '" style="background:#0E3B38;color:#fff;padding:13px 22px;border-radius:8px;text-decoration:none;font-weight:600">'
		. esc_html__( 'Confirm my review', 'oria' ) . '</a></p>'
		. '<p style="color:#6b6b6b;font-size:13px">' . esc_html__( 'The link works once and lasts 30 minutes. If you did not write a review, ignore this and nothing is published.', 'oria' ) . '</p>';

	$sent = wp_mail(
		$email,
		/* translators: %s: practice name */
		sprintf( __( 'Confirm your review of %s', 'oria' ), $name ),
		email_shell(
			__( 'One tap and your review is on its way to us.', 'oria' ),
			$body
		),
		email_headers()
	);

	unset( $sent );
}

function notify_admin( int $comment_id, int $listing_id, array $flags ): void {
	$to = (string) get_option( 'admin_email' );
	if ( ! is_email( $to ) ) {
		return;
	}

	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );
	$body = '<p>' . esc_html__( 'A review is waiting to be read.', 'oria' ) . '</p>'
		. '<p><strong>' . esc_html( $name ) . '</strong></p>'
		. ( $flags ? '<p style="color:#8a5a00"><strong>' . esc_html__( 'Flagged:', 'oria' ) . '</strong> ' . esc_html( implode( ', ', $flags ) ) . '</p>' : '' )
		. '<p><a href="' . esc_url( admin_url( 'comment.php?action=editcomment&c=' . $comment_id ) ) . '">' . esc_html__( 'Read it', 'oria' ) . '</a></p>';

	wp_mail(
		$to,
		$flags
			/* translators: %s: practice name */
			? sprintf( __( 'Flagged review: %s', 'oria' ), $name )
			/* translators: %s: practice name */
			: sprintf( __( 'New review: %s', 'oria' ), $name ),
		email_shell( __( 'A review is waiting for moderation.', 'oria' ), $body ),
		email_headers()
	);
}

/** The shared email chrome, when the forms plugin is there to provide it. */
function email_shell( string $preheader, string $body_html ): string {
	if ( function_exists( '\Oria\Forms\Emails\shell' ) ) {
		return \Oria\Forms\Emails\shell( $preheader, $body_html, 'masthead' );
	}
	return '<div style="font-family:sans-serif;max-width:560px;margin:0 auto">' . $body_html . '</div>';
}

/** @return array<int,string> */
function email_headers(): array {
	if ( function_exists( '\Oria\Forms\Emails\html_headers' ) ) {
		return (array) \Oria\Forms\Emails\html_headers();
	}
	return array( 'Content-Type: text/html; charset=UTF-8' );
}

/* ------------------------------------------------------------------ options */

/**
 * What somebody can say they tried here: the listing's own services and
 * specialties, which is also what stops a term id from another listing
 * being posted in.
 *
 * @param bool $ids_only Return term ids rather than term objects.
 * @return array<int, \WP_Term|int>
 */
function tried_options( int $listing_id, bool $ids_only = false ): array {
	$terms = array();

	foreach ( array( 'service', 'specialty' ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		$found = wp_get_post_terms( $listing_id, $taxonomy );
		if ( ! is_wp_error( $found ) ) {
			$terms = array_merge( $terms, $found );
		}
	}

	// One entry per name: a listing tagged "Remedial massage" in both
	// taxonomies should offer it once.
	$seen = array();
	$out  = array();
	foreach ( $terms as $term ) {
		$key = lower( $term->name );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$out[]        = $term;
	}

	usort( $out, static fn( \WP_Term $a, \WP_Term $b ): int => strcasecmp( $a->name, $b->name ) );

	return $ids_only ? array_map( static fn( \WP_Term $t ): int => (int) $t->term_id, $out ) : $out;
}
