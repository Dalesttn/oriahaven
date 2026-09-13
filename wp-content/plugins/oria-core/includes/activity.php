<?php
/**
 * What a member has done with a listing: saved it, or tried it.
 *
 * One table, one row per (member, listing, verb). The verbs are the two
 * My Oria needs -- "I want to go" and "I went" -- and nothing else is
 * recorded: no ratings, no notes, no visit counts. A "tried" is a tick a
 * member puts against a listing, not a review, and it is theirs to remove.
 *
 * Reads are memoised per request, because the dashboard asks the same
 * question from four places; every write drops the memo. The write is a
 * single INSERT ... ON DUPLICATE KEY, so a double-click or a retried
 * request is harmless.
 *
 * The REST route is the only door from the browser. It takes the listing
 * as a slug, because the slug is what the device-side shortlist already
 * stores (see savedIds() in app.js), and answers with the member's whole
 * state so the page never has to guess what changed.
 */

declare(strict_types=1);

namespace Oria\Core\Activity;

use Oria\Core\Db;
use Oria\Core\Members;
use Oria\Core\Passport;
use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SAVED  = 'saved';
const TRIED  = 'tried';
const TYPES  = array( SAVED, TRIED );
const OBJECT = 'listing';
const ROUTE  = 'oria/v1';

function bootstrap(): void {
	add_action( 'rest_api_init', __NAMESPACE__ . '\routes' );
	add_action( 'deleted_user', __NAMESPACE__ . '\purge_user' );
	add_filter( 'wp_privacy_personal_data_exporters', __NAMESPACE__ . '\register_exporter' );
	add_filter( 'wp_privacy_personal_data_erasers', __NAMESPACE__ . '\register_eraser' );
}

function table(): string {
	return Db\user_activity();
}

/**
 * May this account keep a shortlist and a passport?
 *
 * Any signed-in account, unless it is a member the moderators have banned.
 * A pending member (email not yet confirmed) can: confirmation gates
 * reviewing, which is public; this is private to them.
 */
function may_act( int $user_id ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}
	$member = Members\by_user( $user_id );
	return null === $member || Members\STATUS_BANNED !== $member['status'];
}

/* ----------------------------------------------------------------- write */

/** @return bool True when the row now exists (new or already there). */
function add( int $user_id, int $listing_id, string $type ): bool {
	global $wpdb;
	if ( ! in_array( $type, TYPES, true ) || ! is_listing( $listing_id ) ) {
		return false;
	}
	$now = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	$ok = $wpdb->query(
		$wpdb->prepare(
			'INSERT INTO ' . table() . ' (user_id, object_id, object_type, activity_type, created_at) VALUES (%d, %d, %s, %s, %s)'
			. ' ON DUPLICATE KEY UPDATE updated_at = %s',
			$user_id,
			$listing_id,
			OBJECT,
			$type,
			$now,
			$now
		)
	);
	forget( $user_id );
	return false !== $ok;
}

function remove( int $user_id, int $listing_id, string $type ): bool {
	global $wpdb;
	if ( ! in_array( $type, TYPES, true ) ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ok = $wpdb->delete(
		table(),
		array( 'user_id' => $user_id, 'object_id' => $listing_id, 'object_type' => OBJECT, 'activity_type' => $type ),
		array( '%d', '%d', '%s', '%s' )
	);
	forget( $user_id );
	return false !== $ok;
}

/**
 * Bring the device-side shortlist into the account.
 *
 * Called once after sign-in with whatever localStorage held. Additive
 * only: nothing in the account is removed because a browser forgot it.
 *
 * @param list<string> $slugs
 * @return int rows added
 */
function sync_device( int $user_id, array $slugs ): int {
	$n = 0;
	foreach ( array_unique( array_map( 'sanitize_title', $slugs ) ) as $slug ) {
		$id = id_from_slug( $slug );
		if ( $id && ! has( $user_id, $id, SAVED ) && add( $user_id, $id, SAVED ) ) {
			$n++;
		}
	}
	return $n;
}

function purge_user( int $user_id ): void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete( table(), array( 'user_id' => $user_id ), array( '%d' ) );
	delete_user_meta( $user_id, Passport\META );
	forget( $user_id );
}

/* ------------------------------------------------------------------ read */

/**
 * Every row for a member, newest first, with unpublished listings dropped.
 *
 * @return list<array{listing:int, type:string, at:string}>
 */
function rows( int $user_id ): array {
	global $wpdb;
	if ( isset( $GLOBALS['oria_activity_memo'][ $user_id ] ) ) {
		return $GLOBALS['oria_activity_memo'][ $user_id ];
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	$found = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT a.object_id, a.activity_type, a.created_at FROM ' . table() . ' a'
			. " INNER JOIN {$wpdb->posts} p ON p.ID = a.object_id AND p.post_type = %s AND p.post_status = 'publish'"
			. ' WHERE a.user_id = %d AND a.object_type = %s ORDER BY a.created_at DESC, a.id DESC',
			PostTypes\LISTING,
			$user_id,
			OBJECT
		),
		ARRAY_A
	);
	$out = array();
	foreach ( (array) $found as $r ) {
		$out[] = array( 'listing' => (int) $r['object_id'], 'type' => (string) $r['activity_type'], 'at' => (string) $r['created_at'] );
	}
	$GLOBALS['oria_activity_memo'][ $user_id ] = $out;
	return $out;
}

function forget( int $user_id ): void {
	unset( $GLOBALS['oria_activity_memo'][ $user_id ] );
}

/** @return list<int> listing ids, newest first */
function ids( int $user_id, string $type ): array {
	$out = array();
	foreach ( rows( $user_id ) as $r ) {
		if ( $r['type'] === $type ) {
			$out[] = $r['listing'];
		}
	}
	return $out;
}

function has( int $user_id, int $listing_id, string $type ): bool {
	return in_array( $listing_id, ids( $user_id, $type ), true );
}

/** When a member did this, as a MySQL UTC datetime, or ''. */
function when( int $user_id, int $listing_id, string $type ): string {
	foreach ( rows( $user_id ) as $r ) {
		if ( $r['listing'] === $listing_id && $r['type'] === $type ) {
			return $r['at'];
		}
	}
	return '';
}

/** @return array{saved:int, tried:int} */
function counts( int $user_id ): array {
	return array(
		SAVED => count( ids( $user_id, SAVED ) ),
		TRIED => count( ids( $user_id, TRIED ) ),
	);
}

/** @return list<string> post slugs, for the browser */
function slugs( int $user_id, string $type ): array {
	$out = array();
	foreach ( ids( $user_id, $type ) as $id ) {
		$slug = (string) get_post_field( 'post_name', $id );
		if ( '' !== $slug ) {
			$out[] = $slug;
		}
	}
	return $out;
}

function is_listing( int $id ): bool {
	return $id > 0 && PostTypes\LISTING === get_post_type( $id ) && 'publish' === get_post_status( $id );
}

function id_from_slug( string $slug ): int {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return 0;
	}
	$post = get_page_by_path( $slug, OBJECT, PostTypes\LISTING );
	return $post instanceof \WP_Post && 'publish' === $post->post_status ? (int) $post->ID : 0;
}

/* ------------------------------------------------------------------ REST */

function routes(): void {
	register_rest_route(
		ROUTE,
		'/me/activity',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\rest_activity',
			'permission_callback' => __NAMESPACE__ . '\rest_permission',
			'args'                => array(
				'slug' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_title' ),
				'type' => array( 'type' => 'string', 'required' => true, 'enum' => TYPES ),
				'on'   => array( 'type' => 'boolean', 'required' => true ),
			),
		)
	);
	register_rest_route(
		ROUTE,
		'/me/sync',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\rest_sync',
			'permission_callback' => __NAMESPACE__ . '\rest_permission',
			'args'                => array(
				'saved' => array( 'type' => 'array', 'required' => true, 'items' => array( 'type' => 'string' ) ),
			),
		)
	);
}

/**
 * The cookie only counts on a REST route when a wp_rest nonce rides with
 * it; without one WordPress treats the request as anonymous and this
 * returns false. That is the whole authentication story: the user id is
 * always the server's, never the browser's.
 */
function rest_permission(): bool {
	return is_user_logged_in() && may_act( get_current_user_id() );
}

function rest_activity( \WP_REST_Request $req ): \WP_REST_Response {
	$user_id = get_current_user_id();
	$id      = id_from_slug( (string) $req['slug'] );
	if ( ! $id ) {
		return new \WP_REST_Response( array( 'error' => 'unknown_listing' ), 404 );
	}
	$type = (string) $req['type'];
	if ( $req['on'] ) {
		add( $user_id, $id, $type );
	} else {
		remove( $user_id, $id, $type );
	}
	$new = TRIED === $type ? Passport\refresh( $user_id ) : array();
	return new \WP_REST_Response( state( $user_id, $new ) );
}

function rest_sync( \WP_REST_Request $req ): \WP_REST_Response {
	$user_id      = get_current_user_id();
	$added        = sync_device( $user_id, array_map( 'strval', (array) $req['saved'] ) );
	$out          = state( $user_id );
	$out['added'] = $added;
	return new \WP_REST_Response( $out );
}

/**
 * Everything the browser needs to paint every button on the page.
 *
 * @param list<string> $new_badges
 */
function state( int $user_id, array $new_badges = array() ): array {
	return array(
		'saved'      => slugs( $user_id, SAVED ),
		'tried'      => slugs( $user_id, TRIED ),
		'counts'     => counts( $user_id ),
		'badges'     => count( Passport\earned( $user_id ) ),
		'new_badges' => array_values( array_map( static fn( string $s ): array => array( 'slug' => $s, 'label' => Passport\BADGES[ $s ]['label'] ?? $s ), $new_badges ) ),
	);
}

/* --------------------------------------------------------------- privacy */

function register_exporter( array $exporters ): array {
	$exporters['oria-activity'] = array(
		'exporter_friendly_name' => __( 'Oria Haven saved and tried places', 'oria' ),
		'callback'               => __NAMESPACE__ . '\export',
	);
	return $exporters;
}

function register_eraser( array $erasers ): array {
	$erasers['oria-activity'] = array(
		'eraser_friendly_name' => __( 'Oria Haven saved and tried places', 'oria' ),
		'callback'             => __NAMESPACE__ . '\erase',
	);
	return $erasers;
}

function export( string $email ): array {
	$user = get_user_by( 'email', $email );
	$data = array();
	if ( $user instanceof \WP_User ) {
		foreach ( rows( (int) $user->ID ) as $r ) {
			$data[] = array(
				'group_id'    => 'oria-activity',
				'group_label' => __( 'Saved and tried places', 'oria' ),
				'item_id'     => 'activity-' . $r['listing'] . '-' . $r['type'],
				'data'        => array(
					array( 'name' => __( 'Place', 'oria' ), 'value' => get_the_title( $r['listing'] ) ),
					array( 'name' => __( 'Action', 'oria' ), 'value' => $r['type'] ),
					array( 'name' => __( 'When', 'oria' ), 'value' => $r['at'] ),
				),
			);
		}
	}
	return array( 'data' => $data, 'done' => true );
}

function erase( string $email ): array {
	$user = get_user_by( 'email', $email );
	if ( $user instanceof \WP_User ) {
		purge_user( (int) $user->ID );
	}
	return array( 'items_removed' => $user instanceof \WP_User, 'items_retained' => false, 'messages' => array(), 'done' => true );
}
