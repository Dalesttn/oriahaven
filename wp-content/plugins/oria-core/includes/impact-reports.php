<?php
/**
 * The practice impact report: telling a studio what Oria actually sent them.
 *
 * One email, when it is worth sending. A practice that has had five or more
 * website clicks since we last wrote gets told so; a practice that has had
 * four gets nothing, and those four wait. That is the whole feature, and
 * everything below exists to make sure the same five clicks are never
 * reported twice and that a failed send does not quietly lose them.
 *
 * WHAT THIS REUSES, AND WHY THERE IS NO NEW EVENT TABLE
 *
 * The brief sketches a per-event table with an auto-increment id used as a
 * reporting watermark. Oria does not record events that way: Analytics
 * keeps DAILY BUCKETS in post meta (`_oria_stats`), one array per listing
 * per day, which is what both the practitioner metabox and the admin click
 * report already read. Building a second store beside it would mean two
 * numbers that can disagree, and the brief itself says not to.
 *
 * So the watermark is a DATE rather than an event id, and the rule that
 * makes it exact is this: a report only ever counts COMPLETE days. It runs
 * on days strictly before today in Perth, so a click that lands while the
 * job is running belongs to a day that has not been reported yet and will
 * be picked up next time. Nothing is counted twice, and nothing is lost in
 * the gap between reading and sending.
 *
 * The one cost of reusing the buckets is retention: Analytics keeps ninety
 * days. A practice that takes longer than that to reach five clicks will
 * have its earliest clicks pruned before they can be reported. That is a
 * genuinely quiet listing, it is written down here rather than hidden, and
 * the alternative was a second store.
 *
 * @package OriaCore
 */

declare(strict_types=1);

namespace Oria\Core\Impact;

use Oria\Core\Analytics;
use Oria\Core\Audit;
use Oria\Core\ListingEditor;
use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The weekly eligibility job. */
const HOOK = 'oria_practice_impact_weekly';

/** Per-listing reporting state, one meta row. */
const META = '_oria_impact';

/** Schema version for the log table. */
const DB_VER = '1';

const LOCK = 'oria_impact_running';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\schedule', 20 );
	add_action( HOOK, __NAMESPACE__ . '\run_scheduled' );
	add_action( 'admin_post_oria_impact_pref', __NAMESPACE__ . '\handle_pref' );

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::add_command( 'oria impact', __NAMESPACE__ . '\cli' );
	}
}

/* ------------------------------------------------------------------ store */

function table(): string {
	global $wpdb;

	return $wpdb->prefix . 'oria_impact_log';
}

/**
 * The log is the audit trail and the idempotency guard in one.
 *
 * UNIQUE on (listing, period) is what stops a repeated cron run, a timeout
 * followed by a retry, or two overlapping workers sending the same report
 * twice: the second insert simply fails.
 */
function install(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = table();
	$charset = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id bigint(20) unsigned NOT NULL,
			recipient varchar(190) NOT NULL DEFAULT '',
			report_type varchar(32) NOT NULL DEFAULT 'weekly_impact',
			period_start date NOT NULL,
			period_end date NOT NULL,
			website_clicks int(10) unsigned NOT NULL DEFAULT 0,
			profile_views int(10) unsigned NOT NULL DEFAULT 0,
			booking_clicks int(10) unsigned NOT NULL DEFAULT 0,
			phone_clicks int(10) unsigned NOT NULL DEFAULT 0,
			directions_clicks int(10) unsigned NOT NULL DEFAULT 0,
			email_clicks int(10) unsigned NOT NULL DEFAULT 0,
			enquiries int(10) unsigned NOT NULL DEFAULT 0,
			status varchar(16) NOT NULL DEFAULT 'pending',
			failure_reason varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY one_per_period (listing_id, report_type, period_start, period_end),
			KEY listing_created (listing_id, created_at),
			KEY status_created (status, created_at)
		) {$charset};"
	);

	update_option( 'oria_impact_db_ver', DB_VER );
}

/** Created on activation, and again whenever the version lags: a pull is not an activation. */
function maybe_install(): void {
	if ( get_option( 'oria_impact_db_ver' ) !== DB_VER ) {
		install();
	}
}

/* --------------------------------------------------------------- schedule */

/**
 * Monday morning in Perth, and only ever one of them.
 */
function schedule(): void {
	maybe_install();

	if ( wp_next_scheduled( HOOK ) ) {
		return;
	}

	$next = date_create_immutable( 'now', wp_timezone() );
	if ( ! $next ) {
		return;
	}

	$next = $next->modify( 'next monday' )->setTime( 8, 30 );

	wp_schedule_event( $next->getTimestamp(), 'weekly', HOOK );
}

function unschedule(): void {
	$next = wp_next_scheduled( HOOK );
	if ( $next ) {
		wp_unschedule_event( $next, HOOK );
	}
}

/* ------------------------------------------------------------ eligibility */

/** Five, unless somebody says otherwise. */
function threshold(): int {
	return max( 1, (int) apply_filters( 'oria_practice_impact_email_threshold', 5 ) );
}

/** Reports can be turned off site-wide without unscheduling anything. */
function enabled(): bool {
	return (bool) apply_filters( 'oria_practice_impact_enabled', (bool) get_option( 'oria_impact_enabled', true ) );
}

/**
 * What a listing has been told, and what it wants.
 *
 * @return array{enabled:bool, recipient:string, upto:string, last_sent:string}
 */
function state( int $listing_id ): array {
	$raw = get_post_meta( $listing_id, META, true );
	$raw = is_array( $raw ) ? $raw : array();

	return array(
		'enabled'   => ! isset( $raw['enabled'] ) || (bool) $raw['enabled'],
		'recipient' => (string) ( $raw['recipient'] ?? '' ),
		'upto'      => (string) ( $raw['upto'] ?? '' ),
		'last_sent' => (string) ( $raw['last_sent'] ?? '' ),
	);
}

function set_state( int $listing_id, array $changes ): void {
	$raw = get_post_meta( $listing_id, META, true );
	$raw = is_array( $raw ) ? $raw : array();

	update_post_meta( $listing_id, META, array_merge( $raw, $changes ) );
}

/** Yesterday in Perth: the last day whose clicks are all in. */
function last_complete_day(): string {
	$now = date_create_immutable( 'now', wp_timezone() );

	return $now ? $now->modify( '-1 day' )->format( 'Y-m-d' ) : gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
}

/**
 * Who gets the report.
 *
 * Only a claimed owner's own account address. Never the public contact
 * address scraped from a practice's website: that is a person who has not
 * asked us for anything, and a report is not a reason to start.
 */
function recipient( int $listing_id ): string {
	$state = state( $listing_id );
	if ( '' !== $state['recipient'] && is_email( $state['recipient'] ) ) {
		return $state['recipient'];
	}

	$owner = (int) get_post_meta( $listing_id, 'claimed_by', true );
	if ( $owner < 1 ) {
		return '';
	}

	$user = get_userdata( $owner );

	return ( $user && is_email( $user->user_email ) ) ? (string) $user->user_email : '';
}

/**
 * What this listing has done since it was last written to.
 *
 * Reads the same daily buckets the metabox reads, from the day after the
 * watermark up to the last complete day.
 *
 * @return array{clicks:int, from:string, to:string, totals:array<string,int>}|null
 */
function pending( int $listing_id ): ?array {
	$stats = get_post_meta( $listing_id, Analytics\META_STATS, true );
	if ( ! is_array( $stats ) || ! $stats ) {
		return null;
	}

	$state = state( $listing_id );
	$to    = last_complete_day();
	$after = $state['upto'];

	$totals = array_fill_keys( Analytics\TYPES, 0 );
	$from   = '';

	foreach ( $stats as $day => $row ) {
		$day = (string) $day;
		if ( $day > $to || ( '' !== $after && $day <= $after ) || ! is_array( $row ) ) {
			continue;
		}
		if ( '' === $from || $day < $from ) {
			$from = $day;
		}
		foreach ( $row as $type => $n ) {
			if ( isset( $totals[ $type ] ) ) {
				$totals[ $type ] += (int) $n;
			}
		}
	}

	if ( '' === $from ) {
		return null;
	}

	return array( 'clicks' => (int) $totals['web'], 'from' => $from, 'to' => $to, 'totals' => $totals );
}

/**
 * Should this listing be written to, and why not.
 *
 * @return array{ok:bool, why:string, data:?array}
 */
function check( int $listing_id, bool $force = false ): array {
	$no = static fn( string $why ): array => array( 'ok' => false, 'why' => $why, 'data' => null );

	if ( PostTypes\LISTING !== get_post_type( $listing_id ) || 'publish' !== get_post_status( $listing_id ) ) {
		return $no( 'not a published listing' );
	}

	$state = state( $listing_id );
	if ( ! $state['enabled'] ) {
		return $no( 'reports switched off for this practice' );
	}

	$to = recipient( $listing_id );
	if ( '' === $to ) {
		return $no( 'no verified owner address' );
	}

	// One a week at most, however many clicks arrive.
	if ( '' !== $state['last_sent'] && strtotime( $state['last_sent'] ) > time() - 7 * DAY_IN_SECONDS && ! $force ) {
		return $no( 'already written to in the last seven days' );
	}

	$data = pending( $listing_id );
	if ( ! $data ) {
		return $no( 'nothing new since the last report' );
	}

	if ( $data['clicks'] < threshold() && ! $force ) {
		return array(
			'ok'   => false,
			'why'  => sprintf( '%d of %d website clicks, carrying forward', $data['clicks'], threshold() ),
			'data' => $data,
		);
	}

	$data['recipient'] = $to;

	return array( 'ok' => true, 'why' => '', 'data' => $data );
}

/* ------------------------------------------------------------------- send */

/**
 * Write to one practice.
 *
 * The log row is written BEFORE the send and its unique key is what makes
 * a repeat impossible; the watermark moves only after the mail transport
 * has accepted it, so a failure leaves the clicks exactly where they were
 * and the next run tries again.
 *
 * The two states that are not a plain first send are both real and both
 * handled. A row already SENT for this period means the report went out
 * and only our note of it was lost, so the watermark is healed from the
 * log and nothing is sent again. A row that FAILED is retried in place
 * rather than inserted again, because the unique key would refuse the
 * insert and the practice would never hear from us at all.
 */
function send( int $listing_id, array $data, bool $dry = false ): array {
	global $wpdb;

	$now   = current_time( 'mysql', true );
	$table = table();

	if ( $dry ) {
		return array( 'status' => 'dry-run', 'reason' => '' );
	}

	$columns = array(
		'listing_id'        => $listing_id,
		'recipient'         => (string) $data['recipient'],
		'report_type'       => 'weekly_impact',
		'period_start'      => $data['from'],
		'period_end'        => $data['to'],
		'website_clicks'    => (int) $data['totals']['web'],
		'profile_views'     => (int) $data['totals']['view'],
		'booking_clicks'    => (int) $data['totals']['book'],
		'phone_clicks'      => (int) $data['totals']['tel'],
		'directions_clicks' => (int) $data['totals']['dir'],
		'email_clicks'      => (int) $data['totals']['mail'],
		'enquiries'         => (int) $data['totals']['enq'],
		'status'            => 'pending',
		'created_at'        => $now,
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$prior = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, status FROM {$table}
			WHERE listing_id = %d AND report_type = 'weekly_impact' AND period_start = %s AND period_end = %s",
			$listing_id,
			$data['from'],
			$data['to']
		)
	);

	if ( $prior && 'sent' === (string) $prior->status ) {
		heal( $listing_id, (string) $data['to'] );

		return array( 'status' => 'skipped', 'reason' => 'already sent for this period' );
	}

	if ( $prior ) {
		$log_id = (int) $prior->id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, array( 'status' => 'pending', 'failure_reason' => '' ), array( 'id' => $log_id ) );
	} else {
		/*
		 * Two workers can still reach this line together, and then one of
		 * them loses on the unique key. That is the guard doing its job,
		 * not a fault, so its error is kept out of the log rather than
		 * printed as a database failure every time it works.
		 */
		$loud = $wpdb->hide_errors();
		$ok   = $wpdb->insert( $table, $columns ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $loud ) {
			$wpdb->show_errors();
		}

		if ( false === $ok ) {
			return array( 'status' => 'skipped', 'reason' => 'another run is sending this report' );
		}

		$log_id = (int) $wpdb->insert_id;
	}

	$sent = wp_mail(
		(string) $data['recipient'],
		subject( $listing_id, (int) $data['clicks'] ),
		body_html( $listing_id, $data ),
		\Oria\Forms\Emails\html_headers()
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update(
		$table,
		$sent
			? array( 'status' => 'sent', 'sent_at' => $now, 'failure_reason' => '' )
			: array( 'status' => 'failed', 'failure_reason' => 'wp_mail returned false' ),
		array( 'id' => $log_id )
	);

	if ( ! $sent ) {
		return array( 'status' => 'failed', 'reason' => 'the mail transport refused it' );
	}

	// Only now, and only this far: the day the report actually covered.
	set_state(
		$listing_id,
		array( 'upto' => $data['to'], 'last_sent' => current_time( 'mysql' ) )
	);

	return array( 'status' => 'sent', 'reason' => '' );
}

/**
 * Put the watermark back where the log says it should be.
 *
 * Without this a listing whose state meta was lost would find its report
 * already logged, skip, and never move on -- stuck on the same period for
 * good, and silent from then on.
 */
function heal( int $listing_id, string $upto ): void {
	global $wpdb;

	$table = table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$sent_at = (string) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT sent_at FROM {$table} WHERE listing_id = %d AND status = 'sent' ORDER BY period_end DESC LIMIT 1",
			$listing_id
		)
	);

	set_state(
		$listing_id,
		array( 'upto' => $upto, 'last_sent' => '' !== $sent_at ? get_date_from_gmt( $sent_at ) : current_time( 'mysql' ) )
	);
}

/* ------------------------------------------------------------------- run */

/**
 * @return array{checked:int, eligible:int, sent:int, failed:int, skipped:int, lines:array<int,string>}
 */
function run( bool $dry = false, int $only = 0, bool $force = false, int $limit = 400 ): array {
	$out = array( 'checked' => 0, 'eligible' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'lines' => array() );

	if ( ! enabled() && ! $force ) {
		$out['lines'][] = 'Impact reports are switched off site-wide.';

		return $out;
	}

	$ids = $only > 0 ? array( $only ) : claimed_listings( $limit );

	foreach ( $ids as $listing_id ) {
		++$out['checked'];
		$verdict = check( (int) $listing_id, $force );

		if ( ! $verdict['ok'] ) {
			$out['lines'][] = sprintf( '  %-46s — %s', short_name( (int) $listing_id ), $verdict['why'] );
			continue;
		}

		++$out['eligible'];
		$res = send( (int) $listing_id, $verdict['data'], $dry );

		if ( 'sent' === $res['status'] ) {
			++$out['sent'];
		} elseif ( 'failed' === $res['status'] ) {
			++$out['failed'];
		} elseif ( 'skipped' === $res['status'] ) {
			++$out['skipped'];
		}

		$out['lines'][] = sprintf(
			'  %-46s — %d clicks %s..%s → %s%s',
			short_name( (int) $listing_id ),
			(int) $verdict['data']['clicks'],
			$verdict['data']['from'],
			$verdict['data']['to'],
			$res['status'],
			'' !== $res['reason'] ? ' (' . $res['reason'] . ')' : ''
		);
	}

	return $out;
}

/** The scheduled run, with a lock so two of them cannot overlap. */
function run_scheduled(): void {
	if ( get_transient( LOCK ) ) {
		return;
	}
	set_transient( LOCK, 1, 15 * MINUTE_IN_SECONDS );

	$out = run();

	delete_transient( LOCK );

	error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		sprintf(
			'[oria-impact] weekly run: %d checked, %d eligible, %d sent, %d failed, %d skipped',
			$out['checked'],
			$out['eligible'],
			$out['sent'],
			$out['failed'],
			$out['skipped']
		)
	);

	update_option(
		'oria_impact_last_run',
		array( 'at' => current_time( 'mysql' ) ) + array_intersect_key( $out, array_flip( array( 'checked', 'eligible', 'sent', 'failed', 'skipped' ) ) ),
		false
	);
}

/**
 * Listings with an owner, cheaply.
 *
 * Ids and one meta key, not post objects: there are several hundred of
 * these and none of their content is wanted.
 *
 * @return array<int, int>
 */
function claimed_listings( int $limit ): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return array_map(
		'intval',
		(array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'claimed_by'
				WHERE p.post_type = %s AND p.post_status = 'publish'
					AND m.meta_value <> '' AND m.meta_value <> '0'
				ORDER BY p.ID ASC
				LIMIT %d",
				PostTypes\LISTING,
				$limit
			)
		)
	);
}

function short_name( int $listing_id ): string {
	$name = wp_specialchars_decode( (string) get_the_title( $listing_id ), ENT_QUOTES );

	return '' !== $name ? mb_substr( $name, 0, 44 ) : ( '#' . $listing_id );
}

/**
 * The owner's own switch, from their dashboard.
 *
 * The listing is never taken from the form: it is resolved from whoever is
 * signed in, so the worst a forged post can do is change the sender's own
 * preference.
 */
function handle_pref(): void {
	$user    = get_current_user_id();
	$listing = (int) ListingEditor\listing_for( $user );

	if ( $listing < 1 ) {
		wp_safe_redirect( \Oria\Core\MyOria\url() );
		exit;
	}

	check_admin_referer( 'oria_impact_pref' );

	$on = isset( $_POST['impact_on'] );
	set_state( $listing, array( 'enabled' => $on ) );

	Audit\note(
		$listing,
		$on
			? __( 'Owner switched practice impact emails on', 'oria' )
			: __( 'Owner switched practice impact emails off', 'oria' ),
		$user
	);

	wp_safe_redirect( add_query_arg( 'impact', $on ? 'on' : 'off', \Oria\Core\MyOria\url( 'listing' ) ) );
	exit;
}

/* ------------------------------------------------------------------ email */

function subject( int $listing_id, int $clicks ): string {
	return sprintf(
		/* translators: %d: number of website visits */
		_n(
			'Your Oria Haven profile sent %d visit to your website',
			'Your Oria Haven profile sent %d visits to your website',
			$clicks,
			'oria'
		),
		$clicks
	);
}

/** One line of the activity list, drawn only when there is something in it. */
function row( string $label, int $n ): string {
	if ( $n < 1 ) {
		return '';
	}

	return '<tr><td style="padding:6px 0;color:#5f6d69;font-size:15px;">' . esc_html( $label )
		. '</td><td style="padding:6px 0;text-align:right;font-weight:600;color:#14201e;font-size:15px;">'
		. esc_html( number_format_i18n( $n ) ) . '</td></tr>';
}

function body_html( int $listing_id, array $data ): string {
	$name    = wp_specialchars_decode( (string) get_the_title( $listing_id ), ENT_QUOTES );
	$clicks  = (int) $data['clicks'];
	$t       = $data['totals'];
	$from    = (string) mysql2date( 'j M', $data['from'] );
	$to      = (string) mysql2date( 'j M Y', $data['to'] );
	$board   = function_exists( '\Oria\Core\MyOria\url' ) ? \Oria\Core\MyOria\url( 'listing' ) : home_url( '/my-oria/' );
	$profile = (string) get_permalink( $listing_id );

	$html  = '<p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#3F6E60;">'
		. esc_html__( 'Your practice impact', 'oria' ) . '</p>';
	$html .= '<p style="margin:0 0 18px;font-size:14px;color:#5f6d69;">'
		. esc_html( sprintf( /* translators: 1: start date, 2: end date */ __( '%1$s to %2$s', 'oria' ), $from, $to ) ) . '</p>';

	$html .= '<p style="margin:0 0 10px;font-size:22px;line-height:1.3;font-weight:650;color:#0e3b38;">'
		. esc_html(
			sprintf(
				/* translators: %d: number of website visits */
				_n( 'Your Oria Haven profile sent %d visit to your website.', 'Your Oria Haven profile sent %d visits to your website.', $clicks, 'oria' ),
				$clicks
			)
		) . '</p>';

	$html .= '<p style="margin:0 0 22px;font-size:15px;line-height:1.5;color:#5f6d69;">'
		. esc_html(
			sprintf(
				/* translators: %s: practice name */
				__( 'These are clicks from people who found %s on Oria Haven and chose to open your website. They are clicks rather than confirmed visitors, and we do not know what happened after that.', 'oria' ),
				$name
			)
		) . '</p>';

	$rows = row( __( 'Profile views', 'oria' ), (int) $t['view'] )
		. row( __( 'Website clicks', 'oria' ), (int) $t['web'] )
		. row( __( 'Booking clicks', 'oria' ), (int) $t['book'] )
		. row( __( 'Phone taps', 'oria' ), (int) $t['tel'] )
		. row( __( 'Direction requests', 'oria' ), (int) $t['dir'] )
		. row( __( 'Email taps', 'oria' ), (int) $t['mail'] )
		. row( __( 'Enquiries', 'oria' ), (int) $t['enq'] );

	if ( '' !== $rows ) {
		$html .= '<table role="presentation" width="100%" style="border-collapse:collapse;border-top:1px solid #e6e3da;border-bottom:1px solid #e6e3da;margin:0 0 22px;">'
			. $rows . '</table>';
	}

	$nudge = nudge( $listing_id );
	if ( '' !== $nudge ) {
		$html .= '<p style="margin:0 0 22px;padding:12px 14px;background:#f4f2ec;border-radius:10px;font-size:15px;line-height:1.5;color:#14201e;">'
			. esc_html( $nudge ) . '</p>';
	}

	$html .= '<p style="margin:0 0 10px;"><a href="' . esc_url( $board )
		. '" style="display:inline-block;background:#0e3b38;color:#fff;text-decoration:none;padding:13px 22px;border-radius:999px;font-weight:600;">'
		. esc_html__( 'View your practice dashboard', 'oria' ) . '</a></p>';

	$html .= '<p style="margin:0 0 20px;font-size:14px;"><a href="' . esc_url( $profile ) . '" style="color:#0e3b38;">'
		. esc_html__( 'View your public profile', 'oria' ) . '</a></p>';

	$html .= '<p style="margin:0;font-size:13px;line-height:1.5;color:#7d8a86;">'
		. esc_html__( 'These numbers are first-party activity measured on Oria Haven itself. Your own visits to your listing are not counted. You can turn these reports off in your dashboard at any time; it will not affect account or billing emails.', 'oria' ) . '</p>';

	return \Oria\Forms\Emails\shell(
		sprintf( /* translators: %s: practice name */ __( 'Here is what people did after finding %s on Oria Haven.', 'oria' ), $name ),
		$html
	);
}

/**
 * One suggestion, and only when it is true of this listing.
 *
 * A prompt that appears in every email is an advert; a prompt that names a
 * field this practice has actually left empty is help. A practice that has
 * told us it keeps no set hours is not missing anything, so it is never
 * asked for them.
 */
function nudge( int $listing_id ): string {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	$filled = static function ( string $key ) use ( $listing_id ): bool {
		$v = get_field( $key, $listing_id );

		if ( is_array( $v ) ) {
			return (bool) array_filter(
				$v,
				static fn( $row ): bool => '' !== trim( is_array( $row ) ? implode( '', array_map( 'strval', $row ) ) : (string) $row )
			);
		}

		return '' !== trim( (string) $v );
	};

	if ( ! $filled( 'field_oria_booking_url' ) ) {
		return __( 'There is no booking link on your profile. Adding one lets somebody act while they are still interested, instead of going away to look for it.', 'oria' );
	}

	if ( ! get_field( 'field_oria_hours_hide', $listing_id ) && ! $filled( 'field_oria_opening_hours' ) ) {
		return __( 'Your opening hours are not published. They are the thing people check last before deciding to come.', 'oria' );
	}

	return '';
}

/* -------------------------------------------------------------------- cli */

/**
 * wp oria impact run [--dry-run] [--listing=<id>] [--force]
 */
function cli( array $args, array $opts ): void {
	$dry     = isset( $opts['dry-run'] );
	$force   = isset( $opts['force'] );
	$listing = (int) ( $opts['listing'] ?? 0 );

	\WP_CLI::log( sprintf( 'Threshold: %d website clicks. Complete days up to %s.', threshold(), last_complete_day() ) );
	\WP_CLI::log( $dry ? 'Dry run — nothing will be sent.' : 'Live run.' );

	$out = run( $dry, $listing, $force );

	foreach ( $out['lines'] as $line ) {
		\WP_CLI::log( $line );
	}

	\WP_CLI::success(
		sprintf(
			'%d checked, %d eligible, %d sent, %d failed, %d skipped.',
			$out['checked'],
			$out['eligible'],
			$out['sent'],
			$out['failed'],
			$out['skipped']
		)
	);
}
