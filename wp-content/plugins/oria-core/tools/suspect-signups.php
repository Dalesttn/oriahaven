<?php
/**
 * Accounts that look like bot sign-ups -- READ-ONLY. Lists, scores and
 * explains; deletes nothing, changes nothing, emails nobody.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/suspect-signups.php           # suspects only (score >= 3)
 *   wp eval-file wp-content/plugins/oria-core/tools/suspect-signups.php all       # every self-service sign-up
 *   wp eval-file wp-content/plugins/oria-core/tools/suspect-signups.php csv       # suspects as CSV (to a file with > )
 *
 * Looks at practitioner accounts that own a listing created through
 * "List your practice" (meta _oria_signup). Signals, each worth points:
 *
 *   3  email domain cannot receive mail, or is a throwaway-inbox service
 *   2  never set a password (the set-password link was never used)
 *   2  never visited the owner dashboard (PortalWatch, since 8 Oct 2026)
 *   2  listing still pending with no category and no description
 *   1  listing still pending with no suburb
 *   1  no phone given, or a phone that is not an Australian number
 *   1  name, practice or email looks machine-made (random letters, a URL)
 *   1  several sign-ups share the same email pattern or practice name
 *
 * A real practice that signed up and has not got round to its dashboard
 * can score 4-5; treat the list as a shortlist to look at, not a verdict.
 * Accounts that published a listing, claimed one, paid, or own anything
 * else are left out entirely.
 *
 * To remove one you have checked, in WP-CLI (both are permanent):
 *   wp post delete <listing id> --force
 *   wp user delete <user id> --yes
 *
 * @package Oria\Core
 */

defined( 'ABSPATH' ) || exit;

$mode = (string) ( $args[0] ?? '' );

$listings = get_posts(
	array(
		'post_type'      => 'listing',
		'post_status'    => array( 'pending', 'draft', 'publish', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_oria_signup',
	)
);

$rows   = array();
$domain = static fn( string $e ): string => strtolower( (string) substr( strrchr( $e, '@' ), 1 ) );
$local  = static fn( string $e ): string => strtolower( (string) strstr( $e, '@', true ) );
// "looks machine-made": a URL, or a long run with no vowels, or a word that is mostly digits.
$robotic = static function ( string $s ): bool {
	$s = trim( $s );
	if ( '' === $s ) {
		return false;
	}
	if ( preg_match( '#https?://|www\.|\.(com|net|ru|xyz|top)\b#i', $s ) ) {
		return true;
	}
	foreach ( preg_split( '/[\s._\-+]+/', strtolower( $s ) ) as $w ) {
		if ( strlen( $w ) >= 6 && ! preg_match( '/[aeiouy]/', $w ) ) {
			return true;
		}
		if ( strlen( $w ) >= 8 && preg_match_all( '/\d/', $w ) >= 4 ) {
			return true;
		}
	}
	return false;
};

// Count shared patterns first: same practice name, same email local part stem.
$names = array();
$stems = array();
foreach ( $listings as $lid ) {
	$owner = (int) get_post_meta( $lid, 'claimed_by', true );
	$u     = $owner ? get_userdata( $owner ) : false;
	$names[ strtolower( trim( get_the_title( $lid ) ) ) ] = ( $names[ strtolower( trim( get_the_title( $lid ) ) ) ] ?? 0 ) + 1;
	if ( $u ) {
		$stem           = preg_replace( '/\d+$/', '', $local( $u->user_email ) );
		$stems[ $stem ] = ( $stems[ $stem ] ?? 0 ) + 1;
	}
}

foreach ( $listings as $lid ) {
	$owner = (int) get_post_meta( $lid, 'claimed_by', true );
	$u     = $owner ? get_userdata( $owner ) : false;
	if ( ! $u ) {
		continue;
	}
	// Left out: anyone who has clearly become a real owner.
	$owned = get_posts( array( 'post_type' => 'listing', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => 'claimed_by', 'meta_value' => $owner ) );
	if ( 'publish' === get_post_status( $lid ) || in_array( (string) get_post_meta( $lid, 'claim_status', true ), array( 'claimed', 'featured' ), true ) || '' !== (string) get_post_meta( $lid, '_stripe_customer_id', true ) || count( $owned ) > 1 ) {
		if ( 'all' !== $mode ) {
			continue;
		}
	}

	$email  = (string) $u->user_email;
	$score  = 0;
	$why    = array();
	$status = (string) get_post_status( $lid );

	if ( function_exists( '\Oria\Core\Signup\deliverable' ) && ! \Oria\Core\Signup\deliverable( $email ) ) {
		$score += 3;
		$why[]  = 'email domain cannot receive mail / throwaway';
	}
	if ( '' !== (string) $u->user_activation_key ) {
		$score += 2;
		$why[]  = 'never set a password';
	}
	if ( '' === (string) get_user_meta( $owner, 'oria_portal_seen', true ) ) {
		$score += 2;
		$why[]  = 'never opened the dashboard';
	}
	$cats = wp_get_post_terms( $lid, 'practice', array( 'fields' => 'ids' ) );
	if ( 'pending' === $status && ! $cats && '' === trim( (string) get_post_field( 'post_excerpt', $lid ) ) && '' === trim( (string) get_post_field( 'post_content', $lid ) ) ) {
		$score += 2;
		$why[]  = 'pending, no category or description';
	}
	if ( 'pending' === $status && ! wp_get_post_terms( $lid, 'area', array( 'fields' => 'ids' ) ) ) {
		$score += 1;
		$why[]  = 'no suburb';
	}
	$phone = preg_replace( '/[^\d+]/', '', (string) get_post_meta( $lid, 'phone', true ) );
	if ( '' === $phone || ! preg_match( '/^(\+?61|0)[2-478]\d{8}$/', $phone ) ) {
		$score += 1;
		$why[]  = '' === $phone ? 'no phone' : 'phone not Australian';
	}
	if ( $robotic( get_the_title( $lid ) ) || $robotic( (string) $u->display_name ) || $robotic( $local( $email ) ) ) {
		$score += 1;
		$why[]  = 'machine-made name or email';
	}
	$stem = preg_replace( '/\d+$/', '', $local( $email ) );
	if ( ( $names[ strtolower( trim( get_the_title( $lid ) ) ) ] ?? 0 ) > 1 || ( strlen( $stem ) >= 3 && ( $stems[ $stem ] ?? 0 ) > 1 ) ) {
		$score += 1;
		$why[]  = 'shares a name or email pattern with another sign-up';
	}

	if ( 'all' !== $mode && $score < 3 ) {
		continue;
	}
	$rows[] = array(
		'score'      => $score,
		'signed_up'  => wp_date( 'Y-m-d', (int) get_post_meta( $lid, '_oria_signup', true ) ),
		'user'       => $owner,
		'email'      => $email,
		'name'       => (string) $u->display_name,
		'listing'    => $lid,
		'practice'   => html_entity_decode( get_the_title( $lid ), ENT_QUOTES, 'UTF-8' ),
		'status'     => $status,
		'why'        => implode( '; ', $why ),
	);
}

usort( $rows, static fn( $a, $b ): int => $b['score'] <=> $a['score'] ?: strcmp( $b['signed_up'], $a['signed_up'] ) );

if ( ! $rows ) {
	WP_CLI::success( 'all' === $mode ? 'No self-service sign-ups found.' : 'No suspect sign-ups (nothing scored 3 or more).' );
	return;
}
if ( 'csv' === $mode ) {
	WP_CLI\Utils\format_items( 'csv', $rows, array_keys( $rows[0] ) );
	return;
}
WP_CLI\Utils\format_items( 'table', $rows, array( 'score', 'signed_up', 'user', 'email', 'practice', 'listing', 'status', 'why' ) );
WP_CLI::log( '' );
WP_CLI::log( 'Read-only: nothing was changed. Scores of 6+ are very likely bots; 3-5 are worth a look (a real owner who has not started can score 4-5).' );
WP_CLI::log( 'To remove one you have checked (permanent): wp post delete <listing> --force   then   wp user delete <user> --yes' );
