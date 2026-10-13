<?php
/**
 * Subscribers: people who asked for the weekly Perth wellness offers email.
 *
 * The offers themselves stay free to see -- an offer is never gated behind
 * an email. What is on offer here is the NEXT one: a weekly email of new
 * offers, checked by Oria Haven, which a person signs up for beside an offer
 * (the category strip, the listing card) or just after clicking through to
 * one. That is express consent for exactly that email, which is what the
 * Spam Act asks for, and it is recorded with the wording they saw.
 *
 * The table in this database is the record. Klaviyo does the sending: every
 * row is pushed to a Klaviyo list as a subscribed profile with its interests
 * (practice category slugs), suburb and source as profile properties, so the
 * weekly email can be segmented -- "spa offers, western suburbs" -- without
 * this site composing anything. A push that fails is retried hourly; nothing
 * is lost if Klaviyo is down or not yet configured.
 *
 *   ORIA_KLAVIYO_KEY   private API key, in wp-config.php (never the database)
 *   oria_klaviyo_list  the list ID, Settings > General > Oria Haven
 *
 * Unsubscribe is honoured on both sides: the token link in any email this
 * site sends, and Klaviyo's own footer link in its emails (Klaviyo keeps its
 * suppression; the admin page shows what we know).
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Subscribers;

use Oria\Core\Db;
use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION_LIST         = 'oria_klaviyo_list';
const OPTION_WEEKEND_LIST = 'oria_klaviyo_weekend_list';
const CRON                = 'oria_subscribers_sync';
const SLUG                = 'oria-subscribers';
const SOURCES             = array( 'category', 'listing', 'offer_click', 'weekend', 'home', 'other' );
const MAX_TRIES           = 6;
const API                 = 'https://a.klaviyo.com/api/';
const REVISION            = '2024-10-15';

/*
 * The emails a person can ask for. Each is its own consent: signing up for
 * one never puts anybody on the other, and a row from before lists existed
 * is on 'offers' only, the one email there was.
 */
const LISTS = array( 'offers', 'weekend' );

/** The wording beside the field; stored with every signup as the consent record. */
function consent_text( string $list = 'offers' ): string {
	if ( 'weekend' === $list ) {
		return __( 'Your Perth weekend: a few good things to do for yourself, every Thursday, from Oria Haven. Unsubscribe any time.', 'oria' );
	}
	return __( 'A weekly email of new Perth wellness offers, checked by Oria Haven. Unsubscribe any time.', 'oria' );
}

function clean_list( string $list ): string {
	return in_array( $list, LISTS, true ) ? $list : 'offers';
}

function bootstrap(): void {
	add_action( 'rest_api_init', __NAMESPACE__ . '\routes' );
	add_action( 'admin_post_oria_subscribe', __NAMESPACE__ . '\handle_form' );
	add_action( 'admin_post_nopriv_oria_subscribe', __NAMESPACE__ . '\handle_form' );
	add_action( 'admin_post_oria_subscribers_csv', __NAMESPACE__ . '\export_csv' );
	add_action( 'admin_post_oria_subscribers_sync', __NAMESPACE__ . '\sync_now' );
	add_action( 'template_redirect', __NAMESPACE__ . '\maybe_unsubscribe' );
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_init', __NAMESPACE__ . '\settings' );
	add_action( 'init', __NAMESPACE__ . '\schedule' );
	add_action( CRON, __NAMESPACE__ . '\sync_pending' );
}

function schedule(): void {
	if ( ! wp_next_scheduled( CRON ) ) {
		wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', CRON );
	}
}

/* --------------------------------------------------------------- config */

function api_key(): string {
	return defined( 'ORIA_KLAVIYO_KEY' ) && is_string( ORIA_KLAVIYO_KEY ) ? trim( ORIA_KLAVIYO_KEY ) : '';
}

/** The Klaviyo list ID for one of LISTS; '' when it has not been set. */
function list_id( string $list = 'offers' ): string {
	return sanitize_text_field( (string) get_option( 'weekend' === $list ? OPTION_WEEKEND_LIST : OPTION_LIST, '' ) );
}

/** Both halves present for a list: a key in wp-config and that list's ID in settings. */
function configured( string $list = 'offers' ): bool {
	return '' !== api_key() && '' !== list_id( $list );
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
	register_setting(
		'general',
		OPTION_LIST,
		array(
			'type'              => 'string',
			'sanitize_callback' => static fn( $v ): string => preg_replace( '/[^A-Za-z0-9]/', '', (string) $v ) ?? '',
			'default'           => '',
		)
	);
	add_settings_field(
		OPTION_LIST,
		__( 'Klaviyo offers list', 'oria' ),
		static function (): void {
			printf(
				'<input type="text" class="regular-text code" id="%1$s" name="%1$s" value="%2$s" placeholder="e.g. Xy9AbC"> <p class="description">%3$s</p>',
				esc_attr( OPTION_LIST ),
				esc_attr( list_id() ),
				'' === api_key()
					? esc_html__( 'The Klaviyo list ID (Lists & Segments → the list → Settings). The private API key is read from ORIA_KLAVIYO_KEY in wp-config.php, which is not defined yet: signups are stored here and pushed once it is.', 'oria' )
					: esc_html__( 'The Klaviyo list ID (Lists & Segments → the list → Settings). ORIA_KLAVIYO_KEY is defined in wp-config.php.', 'oria' )
			);
		},
		'general',
		'oria_settings',
		array( 'label_for' => OPTION_LIST )
	);
	register_setting(
		'general',
		OPTION_WEEKEND_LIST,
		array(
			'type'              => 'string',
			'sanitize_callback' => static fn( $v ): string => preg_replace( '/[^A-Za-z0-9]/', '', (string) $v ) ?? '',
			'default'           => '',
		)
	);
	add_settings_field(
		OPTION_WEEKEND_LIST,
		__( 'Klaviyo weekend list', 'oria' ),
		static function (): void {
			printf(
				'<input type="text" class="regular-text code" id="%1$s" name="%1$s" value="%2$s" placeholder="e.g. Ab12Cd"> <p class="description">%3$s</p>',
				esc_attr( OPTION_WEEKEND_LIST ),
				esc_attr( list_id( 'weekend' ) ),
				esc_html__( 'A SEPARATE Klaviyo list for the "Your Perth weekend" Thursday email -- not the offers list, because people sign up for each email on its own. Until it is set, weekend signups are stored here and pushed once it is.', 'oria' )
			);
		},
		'general',
		'oria_settings',
		array( 'label_for' => OPTION_WEEKEND_LIST )
	);
}

/* ---------------------------------------------------------------- intake */

function routes(): void {
	register_rest_route(
		'oria/v1',
		'/subscribe',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'args'                => array(
				'email'    => array( 'type' => 'string', 'required' => true ),
				'interest' => array( 'type' => 'string', 'default' => '' ),
				'suburb'   => array( 'type' => 'string', 'default' => '' ),
				'source'   => array( 'type' => 'string', 'default' => 'other' ),
				'listing'  => array( 'type' => 'integer', 'default' => 0 ),
				'ts'       => array( 'type' => 'integer', 'default' => 0 ),
				'website'  => array( 'type' => 'string', 'default' => '' ),
				'list'     => array( 'type' => 'string', 'default' => 'offers' ),
			),
			'callback'            => __NAMESPACE__ . '\rest_subscribe',
		)
	);
}

function rest_subscribe( \WP_REST_Request $r ): \WP_REST_Response {
	$result = intake(
		(string) $r['email'],
		(string) $r['interest'],
		(string) $r['suburb'],
		(string) $r['source'],
		(int) $r['listing'],
		(int) $r['ts'],
		(string) $r['website'],
		(string) $r['list']
	);
	return new \WP_REST_Response( array( 'ok' => 'ok' === $result, 'state' => $result ), 'ok' === $result ? 200 : ( 'throttled' === $result ? 429 : 400 ) );
}

/** The no-JavaScript path: the same form posted to admin-post, bounced back. */
function handle_form(): void {
	$back = (string) ( wp_get_referer() ?: home_url( '/' ) );
	$back = remove_query_arg( 'osub', $back );
	$p    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- pages are cached; the walls are in intake().
	$state = intake(
		(string) ( $p['sub_email'] ?? '' ),
		(string) ( $p['sub_interest'] ?? '' ),
		(string) ( $p['sub_suburb'] ?? '' ),
		(string) ( $p['sub_source'] ?? 'other' ),
		(int) ( $p['sub_listing'] ?? 0 ),
		(int) ( $p['oform_ts'] ?? 0 ),
		(string) ( $p['oform_website'] ?? '' ),
		(string) ( $p['sub_list'] ?? 'offers' )
	);
	$list = clean_list( (string) ( $p['sub_list'] ?? 'offers' ) );
	wp_safe_redirect( add_query_arg( array( 'osub' => $state, 'osub_list' => $list ), remove_query_arg( 'osub_list', $back ) ) . ( 'weekend' === $list ? '#weekend-signup' : '#offers-signup' ) );
	exit;
}

/**
 * Every signup, from either path. Honeypot and minimum fill time rather
 * than a nonce (the pages carrying the form are cached for hours), then a
 * per-address throttle. Returns 'ok', 'invalid', 'throttled' or 'error'.
 */
function intake( string $email, string $interest, string $suburb, string $source, int $listing, int $ts, string $honeypot, string $list = 'offers' ): string {
	$list = clean_list( $list );
	if ( '' !== trim( $honeypot ) ) {
		return 'ok'; // Bots get a quiet success.
	}
	if ( $ts > 0 && time() - $ts < 2 ) {
		return 'invalid';
	}
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return 'invalid';
	}
	$key = 'oria_sub_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$n   = (int) get_transient( $key );
	if ( $n >= 8 ) {
		return 'throttled';
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	$interest = sanitize_key( $interest );
	if ( '' !== $interest && ! term_exists( $interest, 'practice' ) ) {
		$interest = '';
	}
	$suburb = mb_substr( sanitize_text_field( $suburb ), 0, 64 );
	$source = in_array( $source, SOURCES, true ) ? $source : 'other';
	if ( $listing > 0 && PostTypes\LISTING !== get_post_type( $listing ) ) {
		$listing = 0;
	}
	if ( '' === $interest && $listing > 0 && function_exists( '\Oria\Core\Categories\primary_for' ) ) {
		$term     = \Oria\Core\Categories\primary_for( $listing );
		$interest = $term instanceof \WP_Term ? $term->slug : '';
	}
	if ( '' === $suburb && $listing > 0 && function_exists( '\Oria\Core\BestOf\suburb' ) ) {
		$suburb = mb_substr( \Oria\Core\BestOf\suburb( $listing ), 0, 64 );
	}

	$id = add( $email, $interest, $suburb, $source, $listing, $list );
	if ( 0 === $id ) {
		return 'error';
	}
	push( $id );
	return 'ok';
}

/* --------------------------------------------------------------- storage */

/**
 * Insert or refresh one address for one list. A second signup from the
 * same person adds the new interest and the new list, with its own consent
 * record; the lists they already had keep theirs. Somebody who had
 * unsubscribed comes back on the list they just asked for ONLY -- leaving
 * was a no to everything, and asking for the weekend email is not a yes to
 * the offers one. Returns the row id, 0 on a database failure.
 */
function add( string $email, string $interest, string $suburb, string $source, int $listing, string $list = 'offers' ): int {
	global $wpdb;
	$table   = Db\subscribers();
	$now     = current_time( 'mysql' );
	$email   = strtolower( $email );
	$list    = clean_list( $list );
	$consent = array( 'text' => consent_text( $list ), 'at' => $now, 'source' => $source, 'synced' => false );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE email = %s", $email ), ARRAY_A );
	if ( $row ) {
		$returning = 'unsubscribed' === $row['status'];
		$lists     = $returning ? array() : lists_of( $row );
		$consents  = $returning ? array() : consents_of( $row );
		$interests = interests_of( $row );
		if ( '' !== $interest && ! in_array( $interest, $interests, true ) ) {
			$interests[] = $interest;
		}
		if ( ! in_array( $list, $lists, true ) ) {
			$lists[]           = $list;
			$consents[ $list ] = $consent;
		} else {
			// Already on it: re-push so a changed interest or area reaches Klaviyo.
			$consents[ $list ]['synced'] = false;
		}
		$wpdb->update(
			$table,
			array(
				'status'            => 'subscribed',
				'interests'         => wp_json_encode( array_values( $interests ) ),
				'suburb'            => '' !== $suburb ? $suburb : $row['suburb'],
				'lists'             => wp_json_encode( array_values( $lists ) ),
				'consents'          => wp_json_encode( $consents ),
				'updated_at'        => $now,
				'unsubscribed_at'   => null,
				'klaviyo_synced_at' => null,
				'klaviyo_attempts'  => 0,
				'last_error'        => null,
			),
			array( 'id' => (int) $row['id'] ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', null, null, '%d', null ),
			array( '%d' )
		);
		return (int) $row['id'];
	}
	$ok = $wpdb->insert(
		$table,
		array(
			'email'        => $email,
			'status'       => 'subscribed',
			'interests'    => wp_json_encode( '' !== $interest ? array( $interest ) : array() ),
			'suburb'       => $suburb,
			'source'       => $source,
			'listing_id'   => $listing,
			'consent_text' => consent_text( $list ),
			'lists'        => wp_json_encode( array( $list ) ),
			'consents'     => wp_json_encode( array( $list => $consent ) ),
			'token'        => wp_generate_password( 40, false, false ),
			'created_at'   => $now,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
	);
	// phpcs:enable
	return $ok ? (int) $wpdb->insert_id : 0;
}

/** @return list<string> */
function interests_of( array $row ): array {
	$list = json_decode( (string) ( $row['interests'] ?? '' ), true );
	return is_array( $list ) ? array_values( array_filter( array_map( 'strval', $list ) ) ) : array();
}

/**
 * The emails a row asked for. A row from before v5 has no lists and was
 * an offers signup, the only kind there was.
 *
 * @return list<string>
 */
function lists_of( array $row ): array {
	$raw = $row['lists'] ?? null;
	if ( null === $raw || '' === $raw ) {
		return array( 'offers' );
	}
	$list = json_decode( (string) $raw, true );
	return is_array( $list ) ? array_values( array_intersect( LISTS, array_map( 'strval', $list ) ) ) : array( 'offers' );
}

/**
 * Per list: the consent wording, when, from where, and whether Klaviyo has
 * it. A pre-v5 row's offers consent is rebuilt from its own columns, and
 * counts as synced when the row was.
 *
 * @return array<string, array{text:string, at:string, source:string, synced:bool}>
 */
function consents_of( array $row ): array {
	$out = json_decode( (string) ( $row['consents'] ?? '' ), true );
	$out = is_array( $out ) ? $out : array();
	if ( ! isset( $out['offers'] ) && in_array( 'offers', lists_of( $row ), true ) ) {
		$out['offers'] = array(
			'text'   => (string) ( $row['consent_text'] ?? '' ),
			'at'     => (string) ( $row['created_at'] ?? '' ),
			'source' => (string) ( $row['source'] ?? '' ),
			'synced' => ! empty( $row['klaviyo_synced_at'] ),
		);
	}
	return $out;
}

function row( int $id ): ?array {
	global $wpdb;
	$table = Db\subscribers();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

function unsubscribe_by_token( string $token ): ?array {
	global $wpdb;
	$table = Db\subscribers();
	$token = preg_replace( '/[^A-Za-z0-9]/', '', $token ) ?? '';
	if ( '' === $token ) {
		return null;
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE token = %s", $token ), ARRAY_A );
	if ( ! $row ) {
		return null;
	}
	if ( 'unsubscribed' !== $row['status'] ) {
		$wpdb->update(
			$table,
			array( 'status' => 'unsubscribed', 'unsubscribed_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $row['id'] )
		);
		klaviyo_unsubscribe( (string) $row['email'] );
	}
	// phpcs:enable
	return $row;
}

/** /?oria_unsub=TOKEN -- the link in any email this site sends. */
function maybe_unsubscribe(): void {
	$token = (string) ( $_GET['oria_unsub'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the credential.
	if ( '' === $token ) {
		return;
	}
	$row = unsubscribe_by_token( $token );
	nocache_headers();
	status_header( 200 );
	get_header();
	echo '<main class="wrap" style="padding:4rem 0;max-width:40rem;">';
	if ( $row ) {
		echo '<h1 class="h2">' . esc_html__( 'You are unsubscribed', 'oria' ) . '</h1>';
		echo '<p>' . esc_html( sprintf( /* translators: %s: email */ __( 'No more Oria Haven emails will go to %s. If that was a mistake, sign up again on the site.', 'oria' ), (string) $row['email'] ) ) . '</p>';
	} else {
		echo '<h1 class="h2">' . esc_html__( 'That link has expired', 'oria' ) . '</h1>';
		echo '<p>' . esc_html__( 'Use the unsubscribe link at the foot of the most recent email, or reply to it and we will do it by hand.', 'oria' ) . '</p>';
	}
	echo '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to Oria Haven', 'oria' ) . '</a></p></main>';
	get_footer();
	exit;
}

/* --------------------------------------------------------------- klaviyo */

/**
 * Push one row to the Klaviyo list as a subscribed profile. Records the
 * outcome on the row; never throws, never blocks the visitor's response for
 * long (8s cap). Returns true on 2xx.
 */
function push( int $id ): bool {
	$row = row( $id );
	if ( ! $row || 'subscribed' !== $row['status'] ) {
		return false;
	}
	$consents = consents_of( $row );
	$todo     = array();
	foreach ( lists_of( $row ) as $list ) {
		if ( empty( $consents[ $list ]['synced'] ) && configured( $list ) ) {
			$todo[] = $list;
		}
	}
	if ( ! $todo ) {
		// Not an attempt: whatever is left waits, untouched, for its key and list ID.
		return false;
	}
	/*
	 * Two calls, because the subscription job takes no custom properties:
	 * first upsert the profile with what we know about them (interests,
	 * suburb, source, the consent they gave), then subscribe that address to
	 * the list with express consent.
	 */
	$res = request(
		'profile-import',
		array(
			'data' => array(
				'type'       => 'profile',
				'attributes' => array(
					'email'      => $row['email'],
					'properties' => array(
						'oria_interests' => interests_of( $row ),
						'oria_suburb'    => (string) $row['suburb'],
						'oria_source'    => (string) $row['source'],
						'oria_consent'   => (string) $row['consent_text'],
						'oria_signed_up' => (string) $row['created_at'],
						'oria_lists'     => lists_of( $row ),
					),
				),
			),
		)
	);
	if ( ! ok( $res ) ) {
		note( $id, 'profile: ' . describe( $res ), false );
		return false;
	}
	foreach ( $todo as $list ) {
		$res = request(
			'profile-subscription-bulk-create-jobs',
			array(
				'data' => array(
					'type'          => 'profile-subscription-bulk-create-job',
					'attributes'    => array(
						'custom_source' => 'weekend' === $list ? 'Oria Haven weekend signup' : 'Oria Haven offers signup',
						'profiles'      => array(
							'data' => array(
								array(
									'type'       => 'profile',
									'attributes' => array(
										'email'         => $row['email'],
										'subscriptions' => array(
											'email' => array( 'marketing' => array( 'consent' => 'SUBSCRIBED' ) ),
										),
									),
								),
							),
						),
					),
					'relationships' => array(
						'list' => array( 'data' => array( 'type' => 'list', 'id' => list_id( $list ) ) ),
					),
				),
			)
		);
		if ( ! ok( $res ) ) {
			note( $id, 'subscribe ' . $list . ': ' . describe( $res ), false );
			return false;
		}
		$consents[ $list ]['synced'] = true;
		save_consents( $id, $consents );
	}
	// The row is synced only once every list it asked for is; one still
	// waiting for its list ID keeps the row in the retry queue.
	foreach ( lists_of( $row ) as $list ) {
		if ( empty( $consents[ $list ]['synced'] ) ) {
			return true;
		}
	}
	note( $id, '', true );
	return true;
}

function save_consents( int $id, array $consents ): void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->update( Db\subscribers(), array( 'consents' => wp_json_encode( $consents ) ), array( 'id' => $id ) );
}

/** @param array|\WP_Error $res */
function ok( $res ): bool {
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	return $code >= 200 && $code < 300;
}

/** @param array|\WP_Error $res */
function describe( $res ): string {
	if ( is_wp_error( $res ) ) {
		return $res->get_error_message();
	}
	return 'HTTP ' . wp_remote_retrieve_response_code( $res ) . ' ' . mb_substr( (string) wp_remote_retrieve_body( $res ), 0, 160 );
}

/** Tell Klaviyo the person left every list, so its sends stop too. Best effort. */
function klaviyo_unsubscribe( string $email ): void {
	foreach ( LISTS as $list ) {
		if ( configured( $list ) ) {
			klaviyo_unsubscribe_from( $email, $list );
		}
	}
}

function klaviyo_unsubscribe_from( string $email, string $list ): void {
	request(
		'profile-subscription-bulk-delete-jobs',
		array(
			'data' => array(
				'type'          => 'profile-subscription-bulk-delete-job',
				'attributes'    => array(
					'profiles' => array( 'data' => array( array( 'type' => 'profile', 'attributes' => array( 'email' => $email ) ) ) ),
				),
				'relationships' => array( 'list' => array( 'data' => array( 'type' => 'list', 'id' => list_id( $list ) ) ) ),
			),
		)
	);
}

/** @return array|\WP_Error */
function request( string $path, array $body ) {
	return wp_remote_post(
		API . $path,
		array(
			'timeout' => 8,
			'headers' => array(
				'Authorization' => 'Klaviyo-API-Key ' . api_key(),
				'revision'      => REVISION,
				'Content-Type'  => 'application/vnd.api+json',
				'Accept'        => 'application/vnd.api+json',
			),
			'body'    => wp_json_encode( $body ),
		)
	);
}

/** Record one push attempt on the row. $wpdb->update() writes a PHP null as SQL NULL. */
function note( int $id, string $error, bool $synced ): void {
	global $wpdb;
	$table = Db\subscribers();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->update(
		$table,
		array(
			'klaviyo_synced_at' => $synced ? current_time( 'mysql' ) : null,
			'last_error'        => '' !== $error ? mb_substr( $error, 0, 255 ) : null,
		),
		array( 'id' => $id )
	);
	$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET klaviyo_attempts = klaviyo_attempts + 1 WHERE id = %d", $id ) );
	// phpcs:enable
}

/** Hourly: every subscribed row Klaviyo has not confirmed, up to MAX_TRIES. */
function sync_pending( int $limit = 100 ): array {
	global $wpdb;
	if ( '' === api_key() ) {
		return array( 'pushed' => 0, 'failed' => 0, 'skipped' => 'not configured' );
	}
	$table = Db\subscribers();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE status = 'subscribed' AND klaviyo_synced_at IS NULL AND klaviyo_attempts < %d ORDER BY id ASC LIMIT %d", MAX_TRIES, $limit ) ) );
	$n   = array( 'pushed' => 0, 'failed' => 0 );
	foreach ( $ids as $id ) {
		if ( push( $id ) ) {
			++$n['pushed'];
		} else {
			++$n['failed'];
		}
	}
	return $n;
}

/* ---------------------------------------------------------------- admin */

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . PostTypes\LISTING,
		__( 'Subscribers', 'oria' ),
		__( 'Subscribers', 'oria' ),
		'manage_options',
		SLUG,
		__NAMESPACE__ . '\render'
	);
}

function stats(): array {
	global $wpdb;
	$table = Db\subscribers();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$out = array(
		'subscribed'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'subscribed'" ),
		'unsubscribed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'unsubscribed'" ),
		'unsynced'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'subscribed' AND klaviyo_synced_at IS NULL" ),
		'week'         => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE created_at >= %s", gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS ) ) ),
		'interests'    => array(),
		'sources'      => array(),
		'lists'        => array_fill_keys( LISTS, 0 ),
	);
	foreach ( (array) $wpdb->get_results( "SELECT interests, source, lists FROM `{$table}` WHERE status = 'subscribed'", ARRAY_A ) as $r ) {
		foreach ( lists_of( $r ) as $l ) {
			++$out['lists'][ $l ];
		}
		foreach ( interests_of( $r ) as $i ) {
			$out['interests'][ $i ] = ( $out['interests'][ $i ] ?? 0 ) + 1;
		}
		$out['sources'][ $r['source'] ] = ( $out['sources'][ $r['source'] ] ?? 0 ) + 1;
	}
	// phpcs:enable
	arsort( $out['interests'] );
	arsort( $out['sources'] );
	return $out;
}

function render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$table = Db\subscribers();
	$s     = stats();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows  = (array) $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT 100", ARRAY_A );
	$label = static function ( string $slug ): string {
		$t = get_term_by( 'slug', $slug, 'practice' );
		return $t instanceof \WP_Term ? $t->name : $slug;
	};
	$csv  = wp_nonce_url( admin_url( 'admin-post.php?action=oria_subscribers_csv' ), 'oria_subscribers_csv' );
	$sync = wp_nonce_url( admin_url( 'admin-post.php?action=oria_subscribers_sync' ), 'oria_subscribers_sync' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Subscribers', 'oria' ); ?></h1>
		<?php if ( isset( $_GET['synced'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( 'Sync run: %s pushed, %s failed.', 'oria' ), (string) (int) ( $_GET['synced'] ?? 0 ), (string) (int) ( $_GET['failed'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?></p></div>
		<?php endif; ?>
		<?php if ( ! configured() ) : ?>
			<div class="notice notice-warning"><p>
				<?php
				echo '' === api_key()
					? esc_html__( 'Klaviyo is not connected: define ORIA_KLAVIYO_KEY in wp-config.php (a private key with full profile and list access). Signups are stored here meanwhile and pushed by the hourly sync once the key exists.', 'oria' )
					: esc_html__( 'Klaviyo key found, but no list is set. Add the list ID under Settings → General → Oria Haven.', 'oria' );
				?>
			</p></div>
		<?php endif; ?>
		<?php if ( '' !== api_key() && ! configured( 'weekend' ) && $s['lists']['weekend'] > 0 ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'There are weekend-email signups but no Klaviyo weekend list ID. Add it under Settings → General → Oria Haven; they are stored here and pushed once it is set.', 'oria' ); ?></p></div>
		<?php endif; ?>
		<p>
			<?php esc_html_e( 'Offers email:', 'oria' ); ?> <strong><?php echo esc_html( number_format_i18n( $s['lists']['offers'] ) ); ?></strong> ·
			<?php esc_html_e( 'Your Perth weekend:', 'oria' ); ?> <strong><?php echo esc_html( number_format_i18n( $s['lists']['weekend'] ) ); ?></strong>
		</p>
		<p>
			<strong><?php echo esc_html( number_format_i18n( $s['subscribed'] ) ); ?></strong> <?php esc_html_e( 'subscribed', 'oria' ); ?> ·
			<?php echo esc_html( number_format_i18n( $s['week'] ) ); ?> <?php esc_html_e( 'in the last 7 days', 'oria' ); ?> ·
			<?php echo esc_html( number_format_i18n( $s['unsubscribed'] ) ); ?> <?php esc_html_e( 'unsubscribed', 'oria' ); ?> ·
			<?php echo esc_html( number_format_i18n( $s['unsynced'] ) ); ?> <?php esc_html_e( 'not yet in Klaviyo', 'oria' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $csv ); ?>"><?php esc_html_e( 'Download CSV', 'oria' ); ?></a>
			<a class="button" href="<?php echo esc_url( $sync ); ?>"><?php esc_html_e( 'Push to Klaviyo now', 'oria' ); ?></a>
		</p>
		<h2><?php esc_html_e( 'By interest', 'oria' ); ?></h2>
		<table class="widefat striped" style="max-width:520px">
			<thead><tr><th><?php esc_html_e( 'Category', 'oria' ); ?></th><th style="width:90px"><?php esc_html_e( 'People', 'oria' ); ?></th></tr></thead>
			<tbody>
			<?php if ( ! $s['interests'] ) : ?>
				<tr><td colspan="2"><?php esc_html_e( 'No signups yet.', 'oria' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $s['interests'] as $slug => $n ) : ?>
				<tr><td><?php echo esc_html( $label( (string) $slug ) ); ?></td><td><?php echo esc_html( number_format_i18n( $n ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2><?php esc_html_e( 'Latest 100', 'oria' ); ?></h2>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Email', 'oria' ); ?></th><th><?php esc_html_e( 'Lists', 'oria' ); ?></th><th><?php esc_html_e( 'Interests', 'oria' ); ?></th><th><?php esc_html_e( 'Suburb', 'oria' ); ?></th>
				<th><?php esc_html_e( 'Source', 'oria' ); ?></th><th><?php esc_html_e( 'Status', 'oria' ); ?></th><th><?php esc_html_e( 'Klaviyo', 'oria' ); ?></th><th><?php esc_html_e( 'Signed up', 'oria' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rows as $r ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $r['email'] ); ?></td>
					<td><?php echo esc_html( implode( ', ', lists_of( $r ) ) ); ?></td>
					<td><?php echo esc_html( implode( ', ', array_map( $label, interests_of( $r ) ) ) ?: '—' ); ?></td>
					<td><?php echo esc_html( (string) $r['suburb'] ?: '—' ); ?></td>
					<td><?php echo esc_html( (string) $r['source'] . ( (int) $r['listing_id'] ? ' · ' . wp_specialchars_decode( (string) get_post_field( 'post_title', (int) $r['listing_id'], 'raw' ) ) : '' ) ); ?></td>
					<td><?php echo esc_html( (string) $r['status'] ); ?></td>
					<td><?php echo $r['klaviyo_synced_at'] ? esc_html( mysql2date( 'j M, H:i', (string) $r['klaviyo_synced_at'] ) ) : '<span style="color:#b32d2e">' . esc_html( (string) ( $r['last_error'] ?: __( 'pending', 'oria' ) ) ) . '</span>'; ?></td>
					<td><?php echo esc_html( mysql2date( 'j M Y, H:i', (string) $r['created_at'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function export_csv(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ) );
	}
	check_admin_referer( 'oria_subscribers_csv' );
	global $wpdb;
	$table = Db\subscribers();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = (array) $wpdb->get_results( "SELECT email, status, lists, interests, suburb, source, consent_text, created_at, unsubscribed_at FROM `{$table}` ORDER BY created_at ASC", ARRAY_A );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="oria-subscribers-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'email', 'status', 'lists', 'interests', 'suburb', 'source', 'consent_text', 'created_at', 'unsubscribed_at' ) );
	foreach ( $rows as $r ) {
		$r['lists']     = implode( '|', lists_of( $r ) );
		$r['interests'] = implode( '|', interests_of( $r ) );
		fputcsv( $out, array_values( $r ) );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}

function sync_now(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ) );
	}
	check_admin_referer( 'oria_subscribers_sync' );
	$n = sync_pending( 200 );
	wp_safe_redirect( add_query_arg( array( 'synced' => (int) ( $n['pushed'] ?? 0 ), 'failed' => (int) ( $n['failed'] ?? 0 ) ), admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=' . SLUG ) ) );
	exit;
}
