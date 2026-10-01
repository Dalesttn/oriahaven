<?php
/**
 * Import external jobs (brief section 80) from data/jobs/<file>.json.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/jobs-import.php <file>                 (dry run)
 *   wp eval-file wp-content/plugins/oria-core/tools/jobs-import.php <file> apply           (create as drafts)
 *   wp eval-file wp-content/plugins/oria-core/tools/jobs-import.php <file> apply publish   (create live)
 *
 * Each job is a short summary in Oria's own words that links to the
 * original ad -- never the ad's full text. It is marked External: no
 * JobPosting markup, a "Claim this job" box, and "Apply" goes to the
 * original. Pay only where the ad stated it. Idempotent: a job whose
 * application URL is already on Oria is skipped.
 *
 * Where the employer already has an Oria listing (same name, or same
 * website domain) the job is linked to it, so it shows under "Work here".
 *
 * @package Oria\Core
 */

use Oria\Core\Work;

$name    = sanitize_key( (string) ( $args[0] ?? '' ) );
$apply   = in_array( 'apply', $args, true );
$publish = in_array( 'publish', $args, true );
$file    = ORIA_CORE_DIR . 'data/jobs/' . $name . '.json';
if ( '' === $name || ! is_readable( $file ) ) {
	WP_CLI::error( 'Usage: jobs-import.php <file> [apply] [publish]; no file at ' . $file );
}
$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
if ( ! is_array( $data['jobs'] ?? null ) ) {
	WP_CLI::error( 'No "jobs" array in ' . $file );
}
WP_CLI::log( ( $apply ? ( $publish ? 'APPLY (live)' : 'APPLY (drafts)' ) : 'DRY RUN' ) . " -- {$name}" );

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$admin = (int) ( $admin[0] ?? 1 );
$host  = static fn( string $u ): string => preg_replace( '/^www\./', '', (string) wp_parse_url( $u, PHP_URL_HOST ) );

/** An Oria listing for this employer: exact name first, then website domain. */
$listing_for = static function ( string $employer, string $site ) use ( $host ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'listing' AND post_status = 'publish' AND LOWER(post_title) = LOWER(%s) LIMIT 1", $employer ) );
	if ( $id ) {
		return $id;
	}
	$h = $host( $site );
	if ( '' === $h ) {
		return 0;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'website' WHERE p.post_type = 'listing' AND p.post_status = 'publish' AND m.meta_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like( $h ) . '%' ) );
};

/** The suburb term for a name, inside a public city. */
$suburb_for = static function ( string $suburb ): string {
	$t = get_term_by( 'slug', sanitize_title( $suburb ), 'area' ) ?: get_term_by( 'name', $suburb, 'area' );
	return $t instanceof WP_Term ? $t->slug : '';
};

/**
 * What the source ad itself says, and nothing more (job listing redesign, section 11.4).
 * Working arrangement and experience are written only when the data file states
 * them -- the importer used to stamp every job "On-site" and "No experience
 * needed", which contradicted ads that said hybrid or asked for experienced
 * instructors. "locations" keeps the named work sites ("Jolimont & Kinross")
 * apart from the region a job is filed under. "closes_stated" marks a closing
 * date taken from the ad, as opposed to the 30-day run Oria assumes.
 *
 * @return array<string, string> meta key => value ('' clears it)
 */
$source_facts = static function ( array $j ): array {
	return array(
		'arrangement'   => isset( Work\ARRANGEMENTS[ (string) ( $j['arrangement'] ?? '' ) ] ) ? (string) $j['arrangement'] : '',
		'experience'    => isset( Work\EXPERIENCE[ (string) ( $j['experience'] ?? '' ) ] ) ? (string) $j['experience'] : '',
		'locations'     => sanitize_text_field( (string) ( $j['locations'] ?? '' ) ),
		'closes_stated' => ! empty( $j['closes'] ) && strtotime( (string) $j['closes'] ) ? '1' : '',
	);
};

$n = array( 'new' => 0, 'skip' => 0, 'problem' => 0 );
foreach ( $data['jobs'] as $j ) {
	$title = trim( (string) ( $j['title'] ?? '' ) );
	$url   = esc_url_raw( (string) ( $j['apply_url'] ?? '' ) );
	$emp   = trim( (string) ( $j['employer'] ?? '' ) );
	if ( '' === $title || '' === $url || '' === $emp ) {
		WP_CLI::warning( "missing title/employer/apply_url: {$title}" );
		++$n['problem'];
		continue;
	}
	// Already here?
	$dupe = get_posts( array( 'post_type' => Work\JOB, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => Work\key( 'source_url' ), 'meta_value' => $url ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $dupe ) {
		// Already here. One thing is still corrected: the posting date must be the ad's,
		// never the day it was imported (jobs redesign brief, section 7).
		$want = ! empty( $j['posted'] ) && strtotime( (string) $j['posted'] ) ? wp_date( 'Y-m-d 09:00:00', strtotime( (string) $j['posted'] . ' 12:00' ) ) : '';
		$have = (string) get_post_field( 'post_date', (int) $dupe[0] );
		if ( '' !== $want && substr( $have, 0, 10 ) !== substr( $want, 0, 10 ) ) {
			WP_CLI::log( "  ~ {$title} @ {$emp}: posting date {$have} -> {$want}" );
			if ( $apply ) {
				wp_update_post( array( 'ID' => (int) $dupe[0], 'post_date' => $want, 'post_date_gmt' => get_gmt_from_date( $want ), 'edit_date' => true ) );
			}
		} else {
			WP_CLI::log( "  = {$title} @ {$emp}: already on Oria (#{$dupe[0]})" );
		}
		// And the source facts: drop the old stamped defaults, keep only what the ad says.
		$did = (int) $dupe[0];
		foreach ( $source_facts( $j ) as $k => $v ) {
			if ( (string) Work\meta( $did, $k ) !== $v ) {
				WP_CLI::log( sprintf( '    %s: "%s" -> "%s"', $k, (string) Work\meta( $did, $k ), $v ) );
				if ( $apply ) {
					Work\set( $did, $k, $v );
				}
			}
		}
		// The page now names the source once, in its own notice: remove the sentence baked into the body.
		$body = (string) get_post_field( 'post_content', $did );
		$bare = (string) preg_replace( '#\s*<p>Originally advertised on [^<]*</p>\s*$#', '', $body );
		if ( $bare !== $body ) {
			WP_CLI::log( '    body: removed the "Originally advertised on" line' );
			if ( $apply ) {
				wp_update_post( array( 'ID' => $did, 'post_content' => $bare ) );
			}
		}
		++$n['skip'];
		continue;
	}
	$prof = get_term_by( 'name', (string) ( $j['profession'] ?? '' ), Work\PROFESSION );
	$sub  = $suburb_for( (string) ( $j['suburb'] ?? '' ) );
	$type = sanitize_title( (string) ( $j['employment_type'] ?? '' ) );
	$lst  = $listing_for( $emp, (string) ( $j['employer_website'] ?? '' ) );
	// Close when the original does: its stated closing date, else 30 days from when it was posted
	// (a typical job-board run), else 30 days from today -- never later than the source ad.
	$posted = ! empty( $j['posted'] ) && strtotime( (string) $j['posted'] ) ? strtotime( (string) $j['posted'] ) : 0;
	$exp    = ! empty( $j['closes'] ) && strtotime( (string) $j['closes'] )
		? strtotime( (string) $j['closes'] . ' 23:59' )
		: ( $posted ? $posted + Work\JOB_DAYS * DAY_IN_SECONDS : time() + Work\JOB_DAYS * DAY_IN_SECONDS );
	$prob = array_filter(
		array(
			$prof ? '' : 'profession "' . ( $j['profession'] ?? '' ) . '" not found',
			'' !== $sub ? '' : 'suburb "' . ( $j['suburb'] ?? '' ) . '" not found',
			isset( Work\employment_types()[ $type ] ) ? '' : 'employment type "' . $type . '"',
			$exp > time() ? '' : 'already closed',
		)
	);
	$pay = '';
	if ( ! empty( $j['pay_unit'] ) && ( ! empty( $j['pay_min'] ) || ! empty( $j['pay_max'] ) ) ) {
		$pay = sprintf( '$%s%s %s', $j['pay_min'] ?: $j['pay_max'], ! empty( $j['pay_max'] ) && ! empty( $j['pay_min'] ) ? '–$' . $j['pay_max'] : '', $j['pay_unit'] );
	}
	WP_CLI::log( sprintf( '  + %s @ %s (%s)%s%s%s', $title, $emp, $j['suburb'] ?? '?', $pay ? " · {$pay}" : '', $lst ? " · linked to listing #{$lst}" : '', $prob ? ' · PROBLEM: ' . implode( '; ', $prob ) : '' ) );
	if ( $prob && ( ! $prof || '' === $sub || $exp <= time() ) ) {
		++$n['problem'];
		continue;
	}
	if ( ! $apply ) {
		++$n['new'];
		continue;
	}

	$source = (string) ( $j['source'] ?? '' );
	// Just the summary: the job page names the source once, in its "About this listing" notice.
	$body   = trim( (string) ( $j['summary'] ?? '' ) );
	$id     = wp_insert_post(
		array(
			'post_type'    => Work\JOB,
			'post_title'   => $title,
			'post_excerpt' => wp_trim_words( (string) ( $j['summary'] ?? '' ), 40 ),
			'post_content' => wp_kses_post( wpautop( $body ) ),
			'post_status'  => $publish ? 'publish' : 'draft',
			'post_author'  => $admin,
		) + ( $posted ? array(
			// The ad's own posting date, not today's (so "Posted 9 days ago" is true).
			'post_date'     => wp_date( 'Y-m-d 09:00:00', $posted ),
			'post_date_gmt' => get_gmt_from_date( wp_date( 'Y-m-d 09:00:00', $posted ) ),
			'edit_date'     => true,
		) : array() )
	);
	if ( ! $id ) {
		WP_CLI::warning( "could not create {$title}" );
		++$n['problem'];
		continue;
	}
	wp_set_object_terms( $id, array( (int) $prof->term_id ), Work\PROFESSION );
	if ( isset( Work\employment_types()[ $type ] ) ) {
		wp_set_object_terms( $id, array( $type ), Work\EMPLOYMENT );
	}
	Work\set_suburb( $id, $sub );
	Work\set( $id, 'external', '1' );
	Work\set( $id, 'source_url', $url );
	Work\set( $id, 'source', $source );
	Work\set( $id, 'checked', (string) ( $data['checked'] ?? wp_date( 'Y-m-d' ) ) );
	Work\set( $id, 'evidence', (string) ( $j['evidence'] ?? '' ) );
	Work\set( $id, 'employer', $emp );
	Work\set( $id, 'listing', $lst );
	Work\set( $id, 'apply_method', 'url' );
	Work\set( $id, 'apply_url', $url );
	foreach ( $source_facts( $j ) as $k => $v ) {
		Work\set( $id, $k, $v );
	}
	Work\set( $id, 'quals', (string) ( $j['qualifications'] ?? '' ) );
	Work\set( $id, 'weekend', ! empty( $j['weekend'] ) ? '1' : '' );
	Work\set( $id, 'evening', ! empty( $j['evening'] ) ? '1' : '' );
	Work\set( $id, 'pay_min', ! empty( $j['pay_min'] ) ? (float) $j['pay_min'] : '' );
	Work\set( $id, 'pay_max', ! empty( $j['pay_max'] ) ? (float) $j['pay_max'] : '' );
	Work\set( $id, 'pay_unit', isset( Work\PAY_UNITS[ (string) ( $j['pay_unit'] ?? '' ) ] ) ? (string) $j['pay_unit'] : '' );
	Work\set( $id, 'pay_rank', Work\pay_rank( $id ) ?: '' );
	Work\set( $id, 'expires', $exp );
	++$n['new'];
}

WP_CLI::success( sprintf( '%s: %d %s, %d already on Oria, %d with problems (not imported).', $apply ? 'Done' : 'Dry run', $n['new'], $apply ? ( $publish ? 'published' : 'created as drafts' ) : 'would be created', $n['skip'], $n['problem'] ) );
