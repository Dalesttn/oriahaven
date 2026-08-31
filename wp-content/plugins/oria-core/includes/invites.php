<?php
/**
 * Inviting a practice to take over its own listing.
 *
 * Almost every listing here was built from public information and has never
 * been seen by the business it describes. That is the directory's biggest
 * weakness — a wrong price or a closed practice is worse than no listing —
 * and the fix is to hand each one to its owner. This is the email that does
 * the handing over, sent one at a time from the listings screen.
 *
 * Three things it is careful about.
 *
 * The claim it offers is genuinely free. An approved owner on the free plan
 * can already correct their address, contact details, prices and format —
 * see FIELD_TIERS in tiers.php — and their listing stops reading Unclaimed.
 * Photos, hours, offers and analytics stay paid. The email says exactly
 * that, because a free claim that turns out to cost $29 at the last step
 * would be worth less than sending nothing.
 *
 * It is one click. The address we write to is the one the business
 * published, so arriving back with a signed single-use token is reasonable
 * evidence of control — enough to hand over a free listing, which is worth
 * nothing to steal. Anything paid still goes through billing.
 *
 * It stops when asked. Every send is logged, an opt-out is honoured
 * permanently and immediately, and nothing sends twice by accident.
 *
 * Compliance note. This is unsolicited commercial email to businesses and
 * the Spam Act 2003 (Cth) applies: it is sent only to an address the
 * business itself published, it concerns their own listing, it identifies
 * us, and it carries a working opt-out. The address each listing holds was
 * collected during import — worth confirming that provenance before a
 * large run.
 */

declare(strict_types=1);

namespace Oria\Core\Invites;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PATH = 'claim';

/** Post meta. */
const SENT    = '_oria_invite_sent';    // Y-m-d H:i:s of the last send.
const COUNT   = '_oria_invite_count';   // How many have gone out.
const TOKEN   = '_oria_invite_token';   // Hash of the live token.
const EXPIRES = '_oria_invite_expires'; // Unix time the token dies.
const OPTOUT  = '_oria_invite_optout';  // Y-m-d H:i:s they asked us to stop.
const CLAIMED = '_oria_invite_claimed'; // Y-m-d H:i:s they accepted.

/** How long an emailed link stays good. */
const TTL_DAYS = 30;

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\handle_link' );

	add_filter( 'manage_listing_posts_columns', __NAMESPACE__ . '\column' );
	add_action( 'manage_listing_posts_custom_column', __NAMESPACE__ . '\column_content', 20, 2 );
	add_action( 'admin_post_oria_invite', __NAMESPACE__ . '\handle_send' );
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'phpmailer_init', __NAMESPACE__ . '\attach_alt_text' );
	add_action( 'admin_notices', __NAMESPACE__ . '\notice' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\metabox' );

	add_action( 'init', __NAMESPACE__ . '\schedule' );
	add_action( CRON_HOOK, __NAMESPACE__ . '\cron_run' );
}

/* ------------------------------------------------------------ daily run */

/**
 * The morning run.
 *
 * Outreach was a person clicking Send, five a day, reading each listing
 * first. This keeps the pacing and drops the clicking, but it is off until
 * somebody turns it on -- an automated cold email to a real business cannot
 * be unsent, and the difference between a queue that waits and a queue that
 * fires is worth one deliberate decision.
 *
 *   wp option update oria_invite_auto 1     # arm it
 *   wp option delete oria_invite_auto       # disarm it
 *   wp oria invites --dry-run               # see today's list, send nothing
 */
const CRON_HOOK   = 'oria_invite_daily';
const AUTO_OPTION = 'oria_invite_auto';
const LOG_OPTION  = 'oria_invite_last_run';

/** Views, or any single click that shows somebody wanted to reach them. */
const ENGAGED_VIEWS = 5;

function armed(): bool {
	return (bool) apply_filters( 'oria_invite_auto', (bool) get_option( AUTO_OPTION ) );
}

/**
 * 8am in Perth, daily.
 *
 * Scheduled against the site's own timezone rather than UTC so the run
 * stays at 8am for the person reading the replies, and so a server moved
 * between regions does not quietly start writing to practitioners at
 * midnight.
 */
function schedule(): void {
	if ( wp_next_scheduled( CRON_HOOK ) ) {
		return;
	}
	try {
		$next = new \DateTimeImmutable( 'tomorrow 8:00', wp_timezone() );
	} catch ( \Exception $e ) {
		return;
	}
	wp_schedule_event( $next->getTimestamp(), 'daily', CRON_HOOK );
}

/**
 * Listings somebody has actually looked at, most engaged first.
 *
 * The old queue worked through the directory by ID, which meant writing to
 * practices nobody had visited yet -- a claim email whose whole argument is
 * "people are finding this" reads badly when they are not. Ordering by
 * engagement also means the email that quotes a view count quotes a good one.
 */
function engaged_candidates( int $limit ): array {
	if ( ! function_exists( '\Oria\Core\Analytics\total' ) ) {
		return array();
	}
	$ids = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => COUNT,
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	$scored = array();
	foreach ( $ids as $id ) {
		$id   = (int) $id;
		$view = (int) \Oria\Core\Analytics\total( $id, 'view', 90 );
		$web  = (int) \Oria\Core\Analytics\total( $id, 'web', 90 );
		$book = (int) \Oria\Core\Analytics\total( $id, 'book', 90 );

		// Any one of the three qualifies. A single click on "website" or
		// "book" is a stronger signal than several views, so those count
		// even when the view total is low.
		if ( $view < ENGAGED_VIEWS && $web < 1 && $book < 1 ) {
			continue;
		}
		if ( blocked( $id ) ) {
			continue;
		}
		$scored[ $id ] = ( $book * 10 ) + ( $web * 5 ) + $view;
	}

	arsort( $scored );
	return array_slice( array_keys( $scored ), 0, max( 0, $limit ), true );
}

/**
 * One day's sending. Returns what it did, for the report and for --dry-run.
 *
 * @return array{armed:bool,room:int,picked:int,sent:int,failed:int,rows:array<int,array<string,mixed>>,left:int}
 */
function cron_run( bool $dry = false ): array {
	$room = max( 0, DAY_PACE - sent_today() );
	$out  = array(
		'armed'  => armed(),
		'room'   => $room,
		'picked' => 0,
		'sent'   => 0,
		'failed' => 0,
		'rows'   => array(),
		'left'   => 0,
	);

	if ( ! $out['armed'] || ! $room ) {
		return $out;
	}

	$ids           = engaged_candidates( $room );
	$out['picked'] = count( $ids );

	foreach ( $ids as $id ) {
		$id  = (int) $id;
		$row = array(
			'id'    => $id,
			'name'  => wp_specialchars_decode( (string) get_post_field( 'post_title', $id, 'raw' ), ENT_QUOTES ),
			'email' => address( $id ),
			'url'   => (string) get_permalink( $id ),
			'view'  => function_exists( '\Oria\Core\Analytics\total' ) ? (int) \Oria\Core\Analytics\total( $id, 'view', 90 ) : 0,
			'web'   => function_exists( '\Oria\Core\Analytics\total' ) ? (int) \Oria\Core\Analytics\total( $id, 'web', 90 ) : 0,
			'book'  => function_exists( '\Oria\Core\Analytics\total' ) ? (int) \Oria\Core\Analytics\total( $id, 'book', 90 ) : 0,
			'ok'    => null,
		);
		if ( ! $dry ) {
			$row['ok'] = send( $id );
			if ( $row['ok'] ) {
				++$out['sent'];
			} else {
				++$out['failed'];
			}
		}
		$out['rows'][] = $row;
	}

	// Counted after sending, so the report says how many are genuinely left
	// rather than how many there were before this morning.
	$out['left'] = count( engaged_candidates( 9999 ) );

	if ( ! $dry ) {
		update_option(
			LOG_OPTION,
			array(
				'at'     => current_time( 'mysql' ),
				'sent'   => $out['sent'],
				'failed' => $out['failed'],
				'left'   => $out['left'],
			),
			false
		);
		report( $out );
	}
	return $out;
}

/* ---------------------------------------------------------------- report */

/** Where the morning report goes. */
function report_to(): string {
	return (string) apply_filters( 'oria_invite_report_to', 'hello@oriahaven.com.au' );
}

/**
 * Tell someone what went out.
 *
 * Only when the run actually did something. A "nothing sent" email every
 * morning is noise that trains you to ignore the one that matters, and the
 * queue running dry is visible in the next report's "left" count anyway.
 * Failures always report, because a silent failure is the case where the
 * outreach quietly stops and nobody notices for a fortnight.
 */
function report( array $run ): void {
	if ( ! $run['sent'] && ! $run['failed'] ) {
		return;
	}

	$to   = report_to();
	$when = wp_date( 'j F Y' );

	$subject = sprintf(
		/* translators: 1: number sent, 2: date */
		__( 'Oria outreach: %1$d invite(s) sent, %2$s', 'oria' ),
		$run['sent'],
		$when
	);
	if ( $run['failed'] ) {
		$subject = sprintf(
			/* translators: 1: number sent, 2: number failed, 3: date */
			__( 'Oria outreach: %1$d sent, %2$d FAILED, %3$s', 'oria' ),
			$run['sent'],
			$run['failed'],
			$when
		);
	}

	$rows = '';
	foreach ( $run['rows'] as $r ) {
		$rows .= sprintf(
			'<tr>
				<td style="padding:8px 10px;border-bottom:1px solid #E1DED4;">%s<br><a href="%s" style="color:#3F6E60;font-size:12px;">%s</a></td>
				<td style="padding:8px 10px;border-bottom:1px solid #E1DED4;font-size:13px;">%s</td>
				<td style="padding:8px 10px;border-bottom:1px solid #E1DED4;text-align:right;font-variant-numeric:tabular-nums;">%d</td>
				<td style="padding:8px 10px;border-bottom:1px solid #E1DED4;text-align:right;font-variant-numeric:tabular-nums;">%d</td>
				<td style="padding:8px 10px;border-bottom:1px solid #E1DED4;text-align:right;font-variant-numeric:tabular-nums;">%d</td>
				<td style="padding:8px 10px;border-bottom:1px solid #E1DED4;font-weight:700;color:%s;">%s</td>
			</tr>',
			esc_html( $r['name'] ),
			esc_url( $r['url'] ),
			esc_html( (string) $r['email'] ),
			esc_html( (string) $r['email'] ),
			(int) $r['view'],
			(int) $r['web'],
			(int) $r['book'],
			$r['ok'] ? '#2F7A5A' : '#9C3A2A',
			$r['ok'] ? esc_html__( 'sent', 'oria' ) : esc_html__( 'FAILED', 'oria' )
		);
	}

	$html = sprintf(
		'<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;color:#10211F;max-width:720px;">
			<h2 style="font-size:18px;margin:0 0 4px;">%s</h2>
			<p style="color:#47605B;font-size:14px;margin:0 0 18px;">%s</p>
			<table style="border-collapse:collapse;width:100%%;font-size:14px;">
				<tr style="background:#EFEDE6;">
					<th style="padding:8px 10px;text-align:left;">%s</th>
					<th style="padding:8px 10px;text-align:left;">%s</th>
					<th style="padding:8px 10px;text-align:right;">%s</th>
					<th style="padding:8px 10px;text-align:right;">%s</th>
					<th style="padding:8px 10px;text-align:right;">%s</th>
					<th style="padding:8px 10px;text-align:left;">%s</th>
				</tr>
				%s
			</table>
			<p style="color:#47605B;font-size:13px;margin:18px 0 0;">%s</p>
			<p style="font-size:13px;margin:6px 0 0;"><a href="%s" style="color:#3F6E60;">%s</a></p>
		</div>',
		esc_html( sprintf( __( 'Claim invites sent %s', 'oria' ), $when ) ),
		esc_html( sprintf(
			/* translators: 1: sent, 2: failed */
			__( '%1$d sent, %2$d failed. These practices were picked because people are visiting their listings.', 'oria' ),
			$run['sent'],
			$run['failed']
		) ),
		esc_html__( 'Practice', 'oria' ),
		esc_html__( 'Sent to', 'oria' ),
		esc_html__( 'Views', 'oria' ),
		esc_html__( 'Web', 'oria' ),
		esc_html__( 'Book', 'oria' ),
		esc_html__( 'Result', 'oria' ),
		$rows,
		esc_html( sprintf(
			/* translators: 1: remaining count, 2: daily pace */
			_n( '%1$d engaged listing still to write to, at %2$d a day.', '%1$d engaged listings still to write to, at %2$d a day.', (int) $run['left'], 'oria' ),
			(int) $run['left'],
			DAY_PACE
		) ),
		esc_url( admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=oria-outreach' ) ),
		esc_html__( 'Open the outreach queue', 'oria' )
	);

	$text = sprintf( "%s\n\n", $subject );
	foreach ( $run['rows'] as $r ) {
		$text .= sprintf(
			"%s  [%s]\n  %s\n  views %d, web %d, book %d\n  %s\n\n",
			$r['ok'] ? 'SENT  ' : 'FAILED',
			$r['email'],
			$r['name'],
			$r['view'],
			$r['web'],
			$r['book'],
			$r['url']
		);
	}
	$text .= sprintf( "%d engaged listings still to write to, at %d a day.\n", (int) $run['left'], DAY_PACE );

	alt_text( $text );
	wp_mail( $to, $subject, $html, \Oria\Forms\Emails\html_headers() );
	alt_text( '' );
}

/* ------------------------------------------------------------------ route */

function route(): void {
	add_rewrite_rule( '^' . PATH . '/([A-Za-z0-9]+)/?$', 'index.php?oria_invite_token=$matches[1]', 'top' );
}

function query_vars( array $vars ): array {
	$vars[] = 'oria_invite_token';
	return $vars;
}

/* ------------------------------------------------------------- eligibility */

/** The address we'd write to, if there is one. */
function address( int $listing_id ): string {
	$email = trim( (string) get_post_meta( $listing_id, 'email', true ) );
	return is_email( $email ) ? $email : '';
}

/**
 * Why this listing can't be invited, or '' if it can.
 *
 * Returned as a reason rather than a boolean so the admin column can say
 * what's stopping it instead of just hiding the button.
 */
function blocked( int $listing_id ): string {
	if ( get_post_meta( $listing_id, OPTOUT, true ) ) {
		return __( 'Asked us to stop', 'oria' );
	}
	if ( (int) get_post_meta( $listing_id, 'claimed_by', true ) ) {
		return __( 'Already has an owner', 'oria' );
	}
	if ( 'publish' !== get_post_status( $listing_id ) ) {
		return __( 'Not published', 'oria' );
	}
	if ( ! address( $listing_id ) ) {
		return __( 'No email address', 'oria' );
	}
	return '';
}

/* ----------------------------------------------------------------- tokens */

/**
 * Mint a fresh link for this listing and remember its hash.
 *
 * Only the hash is stored, so a leaked database row can't be turned back
 * into a working link, and minting a new one silently kills the old.
 */
function mint( int $listing_id ): string {
	$token = wp_generate_password( 32, false, false );
	update_post_meta( $listing_id, TOKEN, wp_hash( $token ) );
	update_post_meta( $listing_id, EXPIRES, time() + ( TTL_DAYS * DAY_IN_SECONDS ) );
	return $token;
}

function link( string $token, bool $decline = false ): string {
	$url = home_url( '/' . PATH . '/' . rawurlencode( $token ) . '/' );
	return $decline ? add_query_arg( 'no', '1', $url ) : $url;
}

/** The listing a token belongs to, or 0 if it's unknown or stale. */
function listing_for( string $token ): int {
	if ( ! $token ) {
		return 0;
	}
	$found = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => TOKEN,
					'value' => wp_hash( $token ),
				),
			),
		)
	);
	$id = (int) ( $found[0] ?? 0 );
	if ( ! $id ) {
		return 0;
	}
	return (int) get_post_meta( $id, EXPIRES, true ) > time() ? $id : 0;
}

function burn( int $listing_id ): void {
	delete_post_meta( $listing_id, TOKEN );
	delete_post_meta( $listing_id, EXPIRES );
}

/* ------------------------------------------------------------------- send */

/**
 * Write to one practice. Returns true if the mail was handed off.
 */
function send( int $listing_id ): bool {
	if ( blocked( $listing_id ) ) {
		return false;
	}

	$to    = address( $listing_id );
	$again = (int) get_post_meta( $listing_id, COUNT, true ) > 0;
	$token = mint( $listing_id );
	$name  = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );

	// Both parts, every time. A plain-text alternative is worth real money
	// on cold mail: some filters weigh HTML-only messages against you, and
	// some people simply read in plain text.
	alt_text( $again ? follow_up_text( $listing_id, $token ) : body_text( $listing_id, $token ) );

	$sent = wp_mail(
		$to,
		subject( $listing_id, $again ),
		\Oria\Forms\Emails\shell(
			/* translators: %s: practice name */
			sprintf( __( '%s is listed on Oria Haven — it\'s yours to take over, free.', 'oria' ), $name ),
			$again ? follow_up_html( $listing_id, $token ) : body_html( $listing_id, $token ),
			'masthead'
		),
		\Oria\Forms\Emails\html_headers()
	);

	alt_text( '' );

	if ( $sent ) {
		update_post_meta( $listing_id, SENT, current_time( 'mysql' ) );
		update_post_meta( $listing_id, COUNT, (int) get_post_meta( $listing_id, COUNT, true ) + 1 );
	}
	return (bool) $sent;
}

function subject( int $listing_id, bool $again ): string {
	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );
	/* translators: %s: practice name */
	$first = sprintf( __( 'We\'ve listed %s on Oria Haven', 'oria' ), $name );
	return $again ? 'Re: ' . $first : $first;
}

/**
 * Views this listing has actually had, if there are enough to be worth
 * mentioning, else zero.
 *
 * The temptation on a claim email is a general line about the directory
 * getting traffic. That is a claim about their page, made to a business, in
 * writing, and for most listings it would not be true: the median listing has
 * had three views. Telling somebody their page is busy when it is not is
 * misleading, and it is the kind of thing a practitioner checks against their
 * own booking system and remembers.
 *
 * So the line only appears where the number carries it. Below the floor the
 * email says nothing about traffic at all and keeps the accuracy argument,
 * which stands on its own.
 */
function views( int $listing_id ): int {
	if ( ! function_exists( '\Oria\Core\Analytics\total' ) ) {
		return 0;
	}
	$n     = (int) \Oria\Core\Analytics\total( $listing_id, 'view', 90 );
	$floor = (int) apply_filters( 'oria_invite_views_floor', 5 );
	return $n >= $floor ? $n : 0;
}

/**
 * What we've tagged them as, in words — the detail that shows a person
 * looked at their listing, and the quickest way to earn a correction.
 */
function described( int $listing_id ): string {
	$parts = array();

	$practice = wp_get_post_terms( $listing_id, 'practice' );
	if ( ! is_wp_error( $practice ) && $practice ) {
		$parts[] = tname( $practice[0] );
	}

	$suburb = '';
	$areas  = wp_get_post_terms( $listing_id, 'area' );
	foreach ( is_wp_error( $areas ) ? array() : $areas as $area ) {
		if ( $area->parent ) {
			$suburb = tname( $area );
			break;
		}
	}

	$line = $parts ? sprintf(
		/* translators: 1: practice category, 2: suburb */
		__( 'you\'re under %1$s%2$s', 'oria' ),
		$parts[0],
		$suburb ? sprintf( __( ' in %s', 'oria' ), $suburb ) : ''
	) : '';

	$specs = wp_get_post_terms( $listing_id, 'specialty' );
	$specs = is_wp_error( $specs ) ? array() : array_slice( $specs, 0, 3 );
	if ( $specs && $line ) {
		$line .= sprintf(
			/* translators: %s: list of services */
			__( ', with %s', 'oria' ),
			strtolower( wp_sprintf_l( '%l', array_map( __NAMESPACE__ . '\tname', $specs ) ) )
		);
	}

	return $line;
}

function tname( \WP_Term $term ): string {
	return function_exists( 'Oria\Theme\tname' ) ? \Oria\Theme\tname( $term ) : $term->name;
}

/**
 * Defined once, in oria-forms, and shared with every other email we send —
 * so a phone number can never be current in one message and stale in
 * another. This wrapper stays because the invite bodies read better with
 * the sign-off inline than appended.
 */
function signature(): string {
	if ( function_exists( '\Oria\Forms\Emails\signature_text' ) ) {
		return trim( \Oria\Forms\Emails\signature_text(), "\n" );
	}
	return (string) get_option( 'oria_invite_from_name', 'Dale' );
}

/**
 * Holds the plain-text twin of the message being sent, so phpmailer_init
 * can attach it as the alternative part. Cleared straight after the send
 * rather than left lying around for the next unrelated email.
 */
function alt_text( ?string $set = null ): string {
	static $text = '';
	if ( null !== $set ) {
		$text = $set;
	}
	return $text;
}

function attach_alt_text( \PHPMailer\PHPMailer\PHPMailer $mailer ): void {
	$text = alt_text();
	if ( '' !== $text ) {
		$mailer->AltBody = $text;
	}
}

/* --------------------------------------------------------------- the html */

function para( string $html ): string {
	return '<p style="margin:0 0 16px;">' . $html . '</p>';
}

function heading( string $text ): string {
	return '<p style="margin:24px 0 6px;font-size:13px;font-weight:bold;letter-spacing:1.2px;text-transform:uppercase;color:#0E3B38;">' . esc_html( $text ) . '</p>';
}

function button( string $url, string $label ): string {
	return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;"><tr>'
		. '<td style="background:#0E3B38;border-radius:999px;">'
		. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:14px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#FFFFFF;text-decoration:none;">'
		. esc_html( $label ) . '</a></td></tr></table>';
}

function small( string $html ): string {
	return '<p style="margin:0 0 14px;font-size:13px;color:#566762;line-height:1.6;">' . $html . '</p>';
}

function link_to( string $url, string $label = '' ): string {
	return '<a href="' . esc_url( $url ) . '" style="color:#0E3B38;">' . esc_html( $label ?: $url ) . '</a>';
}

function body_html( int $listing_id, string $token ): string {
	$name     = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );
	$describe = described( $listing_id );
	$profile  = (string) get_permalink( $listing_id );

	$html  = para( esc_html__( 'Hi there,', 'oria' ) );
	$html .= para(
		sprintf(
			/* translators: 1: practice name, 2: link to their listing */
			esc_html__( 'We run Oria Haven, a directory of wellness practices in Perth. %1$s is on it: %2$s', 'oria' ),
			'<b>' . esc_html( $name ) . '</b>',
			link_to( $profile )
		)
	);
	$html .= para(
		sprintf(
			/* translators: %s: how we've categorised them */
			esc_html__( 'We put the listing together from what\'s public on your website%s. Nobody from your team has checked it, which is why I\'m writing.', 'oria' ),
			$describe ? ' — ' . esc_html( $describe ) : ''
		)
	);

	$seen = views( $listing_id );
	if ( $seen ) {
		$html .= para(
			sprintf(
				/* translators: %s: number of profile views in the last 90 days */
				esc_html__( 'People are finding it. Your listing has been viewed %s times in the last three months, and that number is growing as the directory settles into search results.', 'oria' ),
				'<b>' . esc_html( number_format_i18n( $seen ) ) . '</b>'
			)
		);
	}

	$html .= heading( __( 'If anything\'s wrong, just reply and I\'ll fix it', 'oria' ) );
	$html .= para(
		$seen
			? esc_html__( 'No account, no charge. Those visitors are reading whatever we got from your website, so an out-of-date price or a wrong opening time is doing real damage right now — worse than not being listed at all.', 'oria' )
			: esc_html__( 'No account, no charge. An out-of-date price or a wrong opening time is worse for you than not being listed at all.', 'oria' )
	);

	$html .= heading( __( 'If you\'d like to look after it yourself, you can — free', 'oria' ) );
	$html .= para( esc_html__( 'Claiming confirms you\'re the owner. You can then keep your address, phone, email, website, prices and session format current yourself, and the listing stops being marked Unclaimed. There are paid plans that add photos, opening hours, offers and visitor stats, but you never have to take one.', 'oria' ) );

	$html .= button( link( $token ), __( 'Claim your listing', 'oria' ) );

	$html .= small(
		sprintf(
			/* translators: %d: number of days */
			esc_html__( 'That link is just for your listing and works for %d days.', 'oria' ),
			TTL_DAYS
		)
	);

	$html .= heading( __( 'One more thing worth a look', 'oria' ) );
	$html .= para(
		sprintf(
			/* translators: %s: link to the listing's share page */
			esc_html__( 'Your listing has a share page — a ready-made social card with your name on it, and a small "Listed on Oria Haven" badge you can paste into your own website\'s footer. The badge links back to your profile, so anyone already on your site can see your hours, reviews and the rest in one click: %s', 'oria' ),
			link_to( \Oria\Core\Share\url( $listing_id ) )
		)
	);

	$html .= '<div style="border-top:1px solid #EFEDE6;margin:22px 0 18px;"></div>';
	$html .= small(
		sprintf(
			/* translators: %d: number of listings */
			esc_html__( 'About us: we list %d practices across Perth, from Fremantle to the Hills, all checked by hand. Enquiries go straight to you. We don\'t take a cut of bookings and we never will.', 'oria' ),
			(int) wp_count_posts( PostTypes\LISTING )->publish
		)
	);
	$html .= signature_html();
	$html .= small(
		sprintf(
			/* translators: %s: opt-out link */
			esc_html__( 'Would you rather not be listed? %s and we\'ll take it down.', 'oria' ),
			link_to( link( $token, true ), __( 'Tell us here', 'oria' ) )
		)
	);

	return $html;
}

function follow_up_html( int $listing_id, string $token ): string {
	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );

	$html  = para( esc_html__( 'Hi again — just once more in case that got buried.', 'oria' ) );
	$html .= para(
		sprintf(
			/* translators: 1: practice name, 2: link */
			esc_html__( '%1$s\'s listing: %2$s', 'oria' ),
			'<b>' . esc_html( $name ) . '</b>',
			link_to( (string) get_permalink( $listing_id ) )
		)
	);
	$html .= button( link( $token ), __( 'Take it over — free', 'oria' ) );
	$html .= small(
		sprintf(
			/* translators: %s: opt-out link */
			esc_html__( 'Rather not be listed? %s.', 'oria' ),
			link_to( link( $token, true ), __( 'Tell us here', 'oria' ) )
		)
	);
	$html .= para( '<b>' . esc_html__( 'Either way, I won\'t email again.', 'oria' ) . '</b>' );
	$html .= signature_html();

	return $html;
}

/**
 * The sign-off inside an invite's body. Same details as the shell footer
 * below it, because an invitation is a letter from a person and should
 * read like one — the footer is the letterhead, this is the signature.
 */
function signature_html(): string {
	if ( ! function_exists( '\Oria\Forms\Emails\signature_html' ) ) {
		return '';
	}
	return '<p style="margin:22px 0 18px;font-size:13px;line-height:1.7;color:#566762;">'
		. \Oria\Forms\Emails\signature_html()
		. '</p>';
}

/* --------------------------------------------------------------- the text */

function body_text( int $listing_id, string $token ): string {
	$name     = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );
	$describe = described( $listing_id );
	$seen     = views( $listing_id );

	return sprintf(
		"Hi there,\n\n" .
		"We run Oria Haven, a directory of wellness practices in Perth. %1\$s is on it:\n\n%2\$s\n\n" .
		"We put the listing together from what's public on your website%3\$s. Nobody from your team has checked it, which is why I'm writing.\n\n" .
		"%10\$s" .
		"IF ANYTHING'S WRONG, JUST REPLY AND I'LL FIX IT.\n%11\$s\n\n" .
		"IF YOU'D LIKE TO LOOK AFTER IT YOURSELF, YOU CAN — FREE.\nClaiming confirms you're the owner. You can then keep your address, phone, email, website, prices and session format current yourself, and the listing stops being marked Unclaimed. There are paid plans that add photos, opening hours, offers and visitor stats, but you never have to take one.\n\n" .
		"Claim it here:\n%4\$s\n\n" .
		"That link is just for your listing and works for %5\$d days.\n\n" .
		"ONE MORE THING WORTH A LOOK\nYour listing has a share page — a ready-made social card with your name on it, and a small \"Listed on Oria Haven\" badge you can paste into your own website's footer. The badge links back to your profile, so anyone already on your site can see your hours, reviews and the rest in one click:\n%6\$s\n\n" .
		"About us: we list %7\$d practices across Perth, from Fremantle to the Hills, all checked by hand. Enquiries go straight to you. We don't take a cut of bookings and we never will.\n\n" .
		"%8\$s\n\n" .
		"---\nWould you rather not be listed? Tell us here and we'll take it down:\n%9\$s\n",
		$name,
		get_permalink( $listing_id ),
		$describe ? ' — ' . $describe : '',
		link( $token ),
		TTL_DAYS,
		\Oria\Core\Share\url( $listing_id ),
		(int) wp_count_posts( PostTypes\LISTING )->publish,
		signature(),
		link( $token, true ),
		$seen
			? sprintf(
				/* translators: %s: number of profile views in the last 90 days */
				__( "People are finding it. Your listing has been viewed %s times in the last three months, and that number is growing as the directory settles into search results.\n\n", 'oria' ),
				number_format_i18n( $seen )
			)
			: '',
		$seen
			? __( 'No account, no charge. Those visitors are reading whatever we got from your website, so an out-of-date price or a wrong opening time is doing real damage right now — worse than not being listed at all.', 'oria' )
			: __( 'No account, no charge. An out-of-date price or a wrong opening time is worse for you than not being listed at all.', 'oria' )
	);
}

/**
 * The second and last email. Saying we won't write again is the part that
 * earns any trust here, so it has to be true — see blocked(), which stops
 * a third.
 */
function follow_up_text( int $listing_id, string $token ): string {
	$name = wp_specialchars_decode( (string) get_post_field( 'post_title', $listing_id, 'raw' ), ENT_QUOTES );

	return sprintf(
		"Hi again — just once more in case that got buried.\n\n" .
		"%1\$s's listing: %2\$s\n" .
		"Take it over (free): %3\$s\n" .
		"Rather not be listed: %4\$s\n\n" .
		"Either way, I won't email again.\n\n%5\$s\n",
		$name,
		get_permalink( $listing_id ),
		link( $token ),
		link( $token, true ),
		signature()
	);
}

/* -------------------------------------------------------------- admin send */

function handle_send(): void {
	$listing_id = isset( $_GET['listing'] ) ? (int) $_GET['listing'] : 0;

	if ( ! $listing_id || ! current_user_can( 'edit_post', $listing_id ) ) {
		wp_die( esc_html__( 'You cannot invite this listing.', 'oria' ) );
	}
	check_admin_referer( 'oria_invite_' . $listing_id );

	$back = wp_get_referer() ?: admin_url( 'edit.php?post_type=' . PostTypes\LISTING );
	$ok   = send( $listing_id );

	wp_safe_redirect( add_query_arg( 'oria_invited', $ok ? '1' : '0', remove_query_arg( 'oria_invited', $back ) ) );
	exit;
}

function notice(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$state = isset( $_GET['oria_invited'] ) ? (string) $_GET['oria_invited'] : '';
	if ( '' === $state ) {
		return;
	}
	printf(
		'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
		'1' === $state ? 'success' : 'error',
		esc_html(
			'1' === $state
				? __( 'Invitation sent.', 'oria' )
				: __( 'That invitation could not be sent — check the listing has an email address and has not opted out.', 'oria' )
		)
	);
}

/* ------------------------------------------------------------ admin column */

function column( array $columns ): array {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'oria_verified' === $key ) {
			$out['oria_invite'] = __( 'Invite', 'oria' );
		}
	}
	// If the claims column ever moves, still show it rather than swallow it.
	if ( ! isset( $out['oria_invite'] ) ) {
		$out['oria_invite'] = __( 'Invite', 'oria' );
	}
	return $out;
}

function column_content( string $column, int $post_id ): void {
	if ( 'oria_invite' !== $column ) {
		return;
	}
	echo wp_kses_post( status_html( $post_id ) );
}

/**
 * One cell, and the whole workflow: what's happened, and the one button
 * that does the next thing.
 */
function status_html( int $post_id ): string {
	$claimed = (string) get_post_meta( $post_id, CLAIMED, true );
	if ( $claimed ) {
		return '<span style="color:#1a7f5a">' . esc_html(
			sprintf(
				/* translators: %s: date */
				__( 'Claimed %s', 'oria' ),
				date_i18n( 'j M', (int) strtotime( $claimed ) )
			)
		) . '</span>';
	}

	$why = blocked( $post_id );
	$sent  = (string) get_post_meta( $post_id, SENT, true );
	$count = (int) get_post_meta( $post_id, COUNT, true );

	$history = '';
	if ( $sent ) {
		$history = '<div style="color:#646970;font-size:12px">' . esc_html(
			sprintf(
				/* translators: 1: number of emails, 2: date */
				_n( 'Sent %2$s', 'Sent %1$d×, last %2$s', $count, 'oria' ),
				$count,
				date_i18n( 'j M', (int) strtotime( $sent ) )
			)
		) . '</div>';
	}

	if ( $why ) {
		return $history . '<span style="color:#646970">' . esc_html( $why ) . '</span>';
	}

	// Two is the limit: the follow-up promises we won't write a third time.
	if ( $count >= 2 ) {
		return $history . '<span style="color:#646970">' . esc_html__( 'Done — no more', 'oria' ) . '</span>';
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=oria_invite&listing=' . $post_id ),
		'oria_invite_' . $post_id
	);

	return $history . sprintf(
		'<a class="button button-small" href="%s">%s</a>',
		esc_url( $url ),
		esc_html( $count ? __( 'Send follow-up', 'oria' ) : __( 'Send invite', 'oria' ) )
	);
}

/* ------------------------------------------------------------ outreach queue */

/**
 * How many first invites make a sensible day.
 *
 * Five is pacing, not law: small daily runs keep each email personal enough
 * to answer replies properly, and keep the sending domain's reputation
 * warming slowly instead of tripping bulk filters in week one. The page
 * still shows the buttons past five — it just says you're done.
 */
const DAY_PACE = 5;

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . PostTypes\LISTING,
		__( 'Outreach queue', 'oria' ),
		__( 'Outreach queue', 'oria' ),
		'manage_options',
		'oria-outreach',
		__NAMESPACE__ . '\render_queue'
	);
}

/** Invites that left today, by SENT stamp. */
function sent_today(): int {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
			SENT,
			$wpdb->esc_like( current_time( 'Y-m-d' ) ) . '%'
		)
	);
}

/**
 * The next listings worth writing to, oldest ID first.
 *
 * Queried loosely (never-sent, published) and then confirmed with
 * blocked(), so the eligibility rules live in exactly one place.
 */
function fresh_candidates( int $limit ): array {
	$ids = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'post_status'    => 'publish',
			'posts_per_page' => $limit * 6,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => COUNT,
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
	$out = array();
	foreach ( $ids as $id ) {
		if ( ! blocked( (int) $id ) ) {
			$out[] = (int) $id;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
	}
	return $out;
}

/** First invite sent, no reply in two weeks, follow-up not yet sent. */
function followups_due( int $limit ): array {
	$ids = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'post_status'    => 'publish',
			'posts_per_page' => $limit * 6,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => COUNT,
					'value' => '1',
				),
				array(
					'key'     => SENT,
					'value'   => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 14 * DAY_IN_SECONDS ),
					'compare' => '<',
				),
			),
		)
	);
	$out = array();
	foreach ( $ids as $id ) {
		if ( ! blocked( (int) $id ) ) {
			$out[] = (int) $id;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
	}
	return $out;
}

/** One queue row: who they are, where they are, and the send button. */
function queue_row( int $id ): string {
	$areas  = get_the_terms( $id, 'area' );
	$suburb = ( $areas && ! is_wp_error( $areas ) ) ? $areas[0]->name : '';

	return sprintf(
		'<tr><td><a href="%s"><b>%s</b></a>%s</td><td>%s</td><td>%s</td></tr>',
		esc_url( (string) get_permalink( $id ) ),
		esc_html( wp_specialchars_decode( (string) get_post_field( 'post_title', $id, 'raw' ), ENT_QUOTES ) ),
		$suburb ? '<span style="color:#646970"> — ' . esc_html( $suburb ) . '</span>' : '',
		esc_html( address( $id ) ),
		wp_kses_post( status_html( $id ) )
	);
}

/**
 * The queue: today's pace, the next five, follow-ups due, recent sends.
 *
 * Everything sends through the existing per-listing handler, so this page
 * adds no second sending path — it is a view over the same buttons the
 * listings column already has, minus the scrolling through 350 rows.
 */
function render_queue(): void {
	$today   = sent_today();
	$fresh   = fresh_candidates( DAY_PACE );
	$due     = followups_due( DAY_PACE );
	$head    = '<tr><th style="text-align:left">' . esc_html__( 'Practice', 'oria' ) . '</th><th style="text-align:left">' . esc_html__( 'Email', 'oria' ) . '</th><th style="text-align:left">' . esc_html__( 'Invite', 'oria' ) . '</th></tr>';

	echo '<div class="wrap"><h1>' . esc_html__( 'Outreach queue', 'oria' ) . '</h1>';

	if ( $today >= DAY_PACE ) {
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number sent today */
					__( '%d sent today — that\'s the day\'s pace done. Tomorrow\'s five will be waiting here.', 'oria' ),
					$today
				)
			)
		);
	} else {
		printf(
			'<p style="font-size:14px">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: sent today, 2: daily pace */
					__( 'Sent today: %1$d of %2$d. Each email offers a fix-it reply, a free claim, and the website badge — and a listing is never emailed more than twice.', 'oria' ),
					$today,
					DAY_PACE
				)
			)
		);
	}

	echo '<h2>' . esc_html__( 'Next up', 'oria' ) . '</h2>';
	if ( $fresh ) {
		echo '<table class="widefat striped" style="max-width:900px"><thead>' . $head . '</thead><tbody>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $fresh as $id ) {
			echo queue_row( $id ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';
	} else {
		echo '<p>' . esc_html__( 'Nobody left to invite — every listing with an email address has been written to.', 'oria' ) . '</p>';
	}

	echo '<h2>' . esc_html__( 'Follow-ups due', 'oria' ) . '</h2>';
	echo '<p style="color:#646970">' . esc_html__( 'First invite sent at least two weeks ago, no claim, no opt-out. The follow-up is the last email a listing ever gets.', 'oria' ) . '</p>';
	if ( $due ) {
		echo '<table class="widefat striped" style="max-width:900px"><thead>' . $head . '</thead><tbody>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $due as $id ) {
			echo queue_row( $id ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';
	} else {
		echo '<p>' . esc_html__( 'None due.', 'oria' ) . '</p>';
	}

	echo '</div>';
}

/* ----------------------------------------------------------- edit-screen box */

function metabox(): void {
	add_meta_box(
		'oria-invite',
		__( 'Invite to claim', 'oria' ),
		__NAMESPACE__ . '\render_metabox',
		PostTypes\LISTING,
		'side',
		'default'
	);
}

function render_metabox( \WP_Post $post ): void {
	$email = address( $post->ID );
	echo '<p style="margin-top:0">';
	echo $email
		? esc_html( sprintf( __( 'Would write to %s', 'oria' ), $email ) )
		: esc_html__( 'No email address on this listing.', 'oria' );
	echo '</p>';
	echo wp_kses_post( status_html( $post->ID ) );
}

/* ------------------------------------------------------ the link they click */

/**
 * Someone has clicked a link in one of these emails: either to take the
 * listing over, or to ask us to take it down.
 */
function handle_link(): void {
	$token = (string) get_query_var( 'oria_invite_token' );
	if ( ! $token ) {
		return;
	}

	$listing_id = listing_for( $token );
	if ( ! $listing_id ) {
		wp_die(
			esc_html__( 'That link has expired or has already been used. Reply to the email we sent and we\'ll send a fresh one.', 'oria' ),
			esc_html__( 'Link expired', 'oria' ),
			array( 'response' => 410 )
		);
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['no'] ) ) {
		decline( $listing_id );
		return;
	}

	accept( $listing_id );
}

/**
 * Take the listing down? Not automatically. A single click in an email is
 * enough to say "stop", and it is honoured instantly; it is not enough to
 * delete something on its own, so a person removes it.
 */
function decline( int $listing_id ): void {
	update_post_meta( $listing_id, OPTOUT, current_time( 'mysql' ) );
	burn( $listing_id );

	wp_mail(
		get_option( 'admin_email' ),
		sprintf(
			/* translators: %s: practice name */
			__( 'Removal requested: %s', 'oria' ),
			get_the_title( $listing_id )
		),
		sprintf(
			"%s has asked to be taken off Oria Haven.\n\n%s\n\nEdit: %s\n\nThey will not be emailed again either way.",
			get_the_title( $listing_id ),
			get_permalink( $listing_id ),
			get_edit_post_link( $listing_id, 'raw' )
		)
	);

	wp_die(
		esc_html__( 'Thanks — we\'ve stopped. Your listing will be taken down within a day, and we won\'t email you again.', 'oria' ),
		esc_html__( 'Removal requested', 'oria' ),
		array( 'response' => 200 )
	);
}

/**
 * Hand the listing over.
 *
 * The address we wrote to was published by the business, and this token
 * went only to that address and works once, which is proof enough for a
 * free listing. Everything with a price on it still goes through billing.
 */
function accept( int $listing_id ): void {
	$email = address( $listing_id );
	if ( ! $email ) {
		wp_die( esc_html__( 'This listing no longer has a contact address.', 'oria' ) );
	}

	burn( $listing_id );

	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		// Same shape of username as the signup form makes.
		$username = sanitize_user( (string) strstr( $email, '@', true ), true );
		if ( '' === $username || username_exists( $username ) ) {
			$username = sanitize_user( $username . wp_rand( 100, 999 ), true );
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24 ),
				'display_name' => get_the_title( $listing_id ),
				'role'         => \Oria\Core\Ownership\ROLE,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			wp_die( esc_html__( 'We could not set up your account. Please reply to our email and we\'ll sort it out.', 'oria' ) );
		}
		$user = get_user_by( 'id', $user_id );
	}

	if ( ! $user instanceof \WP_User ) {
		wp_die( esc_html__( 'We could not set up your account. Please reply to our email and we\'ll sort it out.', 'oria' ) );
	}

	// Free plan: an owner, but claim_status stays unclaimed so no paid
	// surface switches itself on. tiers.php decides what that allows.
	if ( function_exists( 'update_field' ) ) {
		update_field( 'claimed_by', $user->ID, $listing_id );
	} else {
		update_post_meta( $listing_id, 'claimed_by', $user->ID );
	}
	update_post_meta( $listing_id, CLAIMED, current_time( 'mysql' ) );

	$key   = get_password_reset_key( $user );
	$reset = is_wp_error( $key ) ? wp_login_url() : network_site_url(
		'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ),
		'login'
	);

	wp_mail(
		$email,
		__( 'Your listing is yours — Oria Haven', 'oria' ),
		sprintf(
			"Done — %1\$s is now yours.\n\nSet a password here and you can edit it whenever you like:\n%2\$s\n\nYour listing:\n%3\$s\n\nOn the free plan you can keep your address, contact details, prices and format up to date. Photos, opening hours, offers and visitor stats are on the paid plans, and there's no hurry.\n\n%4\$s\n",
			get_the_title( $listing_id ),
			$reset,
			get_permalink( $listing_id ),
			signature()
		) . \Oria\Core\Websites\email_line()
	);

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, false );

	wp_safe_redirect( add_query_arg( 'oria_claimed', '1', get_permalink( $listing_id ) ) );
	exit;
}
