<?php
/**
 * The daily run: watchlist → candidates → AI/heuristic refinement →
 * dedup → draft events. Members' own events are the source of truth —
 * an aggregated event that looks like an existing member event is dropped,
 * and an ingest run never touches a post it didn't create or that has
 * been published/edited by a human.
 */

declare(strict_types=1);

namespace Oria\Ingest\Pipeline;

use Oria\Ingest\AI;
use Oria\Ingest\Fetch;
use Oria\Ingest\Heuristic;
use Oria\Ingest\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPT_WATCHLIST = 'oria_ingest_watchlist';
const OPT_REPORT    = 'oria_ingest_report';
const MAX_CANDIDATES = 30;   // per run, keeps the daily AI spend tiny.
const MAX_DAYS_OUT   = 120;  // ignore events further out than this.

/** @return array<string> */
function watchlist(): array {
	$raw = (string) get_option( OPT_WATCHLIST, '' );
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', $raw ) ?: array() ), static fn( $u ) => str_starts_with( $u, 'http' ) ) );
}

function run(): array {
	$report = array(
		'time'     => current_time( 'mysql' ),
		'sources'  => 0,
		'found'    => 0,
		'relevant' => 0,
		'dupes'    => 0,
		'created'  => 0,
		'updated'  => 0,
		'ai'       => AI\configured() ? 'on' : 'off (heuristics)',
		'lines'    => array(),
	);

	$report['expired'] = expire_pass();

	$seen = 0;
	foreach ( watchlist() as $url ) {
		$report['sources']++;
		$candidates       = Fetch\collect( $url );
		$report['found'] += count( $candidates );
		$report['lines'][] = sprintf( '%s — %d candidate(s)', $url, count( $candidates ) );

		foreach ( $candidates as $c ) {
			if ( ++$seen > MAX_CANDIDATES ) {
				$report['lines'][] = 'Candidate cap reached — remainder left for the next run.';
				break 2;
			}
			$result = process( $c );
			$report['lines'][] = sprintf( '  · %s → %s', $c['title'] ?: '(untitled)', $result );
			if ( isset( $report[ $result ] ) ) {
				$report[ $result ]++;
			}
			if ( in_array( $result, array( 'created', 'updated' ), true ) ) {
				$report['relevant']++;
			}
		}
	}

	update_option( OPT_REPORT, $report, false );
	return $report;
}

/** @param array<string, string> $c
 *  @return string created|updated|dupes|irrelevant|invalid */
function process( array $c ): string {
	if ( '' === $c['title'] || '' === $c['start'] ) {
		return 'invalid';
	}

	// Normalise the start/end to naive Perth-local datetimes, the format
	// every existing event query and template expects.
	$start = local_datetime( $c['start'] );
	$end   = local_datetime( $c['end'] );
	if ( '' === $start ) {
		return 'invalid';
	}
	$now = (int) current_time( 'timestamp' );
	$ts  = (int) strtotime( $start );
	if ( $ts < $now || $ts > $now + MAX_DAYS_OUT * DAY_IN_SECONDS ) {
		return 'invalid';
	}

	// Geo gate before any AI spend: platforms geo-personalise listing pages,
	// so a "Perth" page can serve interstate events. A stated region that
	// isn't WA is an immediate no.
	$region = strtolower( trim( $c['region'] ) );
	if ( '' !== $region && ! in_array( $region, array( 'wa', 'western australia' ), true ) ) {
		return 'irrelevant';
	}

	// Refine: AI when configured, conservative keywords otherwise.
	if ( AI\configured() ) {
		$r = AI\refine( $c );
		if ( null !== $r && empty( $r['relevant'] ) ) {
			return 'irrelevant';
		}
	} else {
		$r = null;
	}
	if ( null === $r ) {
		$type = Heuristic\classify( $c['title'], $c['description'] );
		if ( '' === $type ) {
			return 'irrelevant';
		}
		$r = array(
			'type'        => $type,
			'title'       => $c['title'],
			'description' => '', // never copy source copy verbatim.
			'suburb'      => $c['suburb'],
			'venue'       => $c['venue'],
			'price'       => price_label( $c['price'], $c['currency'] ),
			'start'       => $start,
			'end'         => $end,
			'organiser'   => $c['organiser'],
			'confidence'  => 0.4,
		);
	}
	if ( '' !== (string) $r['start'] ) {
		$start = local_datetime( (string) $r['start'] ) ?: $start;
	}

	$fingerprint = fingerprint( (string) $r['title'], $start, (string) $r['suburb'] );

	// Same event already here (either source)? The AI may have retitled the
	// candidate, so the raw scraped title is checked as well as the refined one.
	$twin = find_twin( $fingerprint, (string) $r['title'], $start, (string) $c['url'], $c['title'] );
	if ( $twin ) {
		if ( get_post_meta( $twin, '_oria_fingerprint', true ) !== $fingerprint || 'draft' !== get_post_status( $twin ) ) {
			// A member event, or an ingest draft a human already published:
			// theirs is the source of truth. Just note we saw it again —
			// though an ingest event still missing its banner gets one.
			update_post_meta( $twin, '_oria_verified', current_time( 'mysql' ) );
			if ( '' !== (string) get_post_meta( $twin, '_oria_src', true )
				&& ! has_post_thumbnail( $twin ) && '' !== (string) $c['image'] ) {
				$att = sideload_image( (string) $c['image'], $twin );
				if ( $att ) {
					set_post_thumbnail( $twin, $att );
				}
			}
			return 'dupes';
		}
		save_event( $twin, $r, $c, $fingerprint );
		return 'updated';
	}

	// Site-owner decision (2026-08-09): confident AI-screened finds publish
	// themselves; anything the AI was unsure about, and everything on the
	// keyword fallback, still waits in drafts for review.
	$status = AI\configured() && (float) ( $r['confidence'] ?? 0 ) >= 0.6 ? 'publish' : 'draft';

	$id = wp_insert_post(
		array(
			'post_type'   => 'event',
			'post_status' => $status,
			'post_title'  => (string) $r['title'],
			// Drafts normally have no slug until published; setting one now
			// keeps the permalink stable however the draft gets published.
			'post_name'   => wp_unique_post_slug( sanitize_title( (string) $r['title'] ), 0, 'publish', 'event', 0 ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return 'invalid';
	}
	add_post_meta( $id, '_oria_discovered', current_time( 'mysql' ), true );
	save_event( $id, $r, $c, $fingerprint );
	return 'created';
}

/** Any ISO/loose datetime → naive Perth-local "Y-m-d H:i:s", or ''. */
function local_datetime( string $raw ): string {
	if ( '' === trim( $raw ) ) {
		return '';
	}
	try {
		$dt = new \DateTimeImmutable( $raw );
	} catch ( \Exception $e ) {
		return '';
	}
	// Only shift when the source stated an offset; bare local times pass through.
	if ( preg_match( '/(Z|[+-]\d{2}:?\d{2})$/i', trim( $raw ) ) ) {
		$dt = $dt->setTimezone( wp_timezone() );
	}
	return $dt->format( 'Y-m-d H:i:s' );
}

function price_label( string $price, string $currency ): string {
	if ( '' === $price ) {
		return '';
	}
	if ( ! is_numeric( $price ) ) {
		return $price;
	}
	$n = (float) $price;
	return $n > 0 ? '$' . rtrim( rtrim( number_format( $n, 2, '.', '' ), '0' ), '.' ) : 'Free';
}

/**
 * Remove aggregated events whose date has passed (the day after, so a
 * Saturday event survives through Saturday night). Imported banners go
 * with them. Member events are never touched — they simply stop being
 * queried once they're in the past.
 */
function expire_pass(): int {
	$today = current_time( 'Y-m-d' ) . ' 00:00:00';
	$old   = get_posts(
		array(
			'post_type'      => 'event',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => '_oria_src', 'compare' => 'EXISTS' ),
				array( 'key' => 'event_start', 'value' => $today, 'compare' => '<', 'type' => 'DATETIME' ),
			),
		)
	);

	$gone = 0;
	foreach ( $old as $id ) {
		// Still running? An end date later than the start keeps it alive.
		$end = (string) get_post_meta( $id, 'event_end', true );
		if ( '' !== $end && $end >= $today ) {
			continue;
		}
		$thumb = (int) get_post_thumbnail_id( $id );
		if ( $thumb && '' !== (string) get_post_meta( $thumb, '_oria_image_source', true ) ) {
			wp_delete_attachment( $thumb, true );
		}
		wp_delete_post( $id, true );
		$gone++;
	}
	return $gone;
}

/**
 * Copy a remote image into the media library, tolerant of the CDN URLs
 * event platforms use (no file extension, query strings). Returns the
 * attachment id, or 0 — never throws, the pipeline must not stop for a
 * missing banner.
 */
/**
 * The platforms hand out thumbnail-sized URLs (~512px); rewrite to the
 * full-size rendition where the CDN pattern is known.
 */
function hi_res_url( string $url ): string {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( 'img.evbuc.com' === $host ) {
		return (string) preg_replace( '/([?&]w=)\d+/', '${1}1600', $url );
	}
	if ( 'images.humanitix.com' === $host ) {
		return (string) preg_replace( '/@seo-\d+\.jpg$/', '', $url );
	}
	return $url;
}

function sideload_image( string $url, int $post_id ): int {
	if ( ! str_starts_with( $url, 'http' ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Full-size first, the original URL as fallback.
	$tmp = download_url( hi_res_url( $url ), 30 );
	if ( is_wp_error( $tmp ) ) {
		$tmp = download_url( $url, 30 );
	}
	if ( is_wp_error( $tmp ) ) {
		return 0;
	}

	// Name the file from real content, not the URL: CDN URLs often have no
	// usable extension, and media_handle_sideload rejects unknown types.
	$size = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$ext  = $size ? ( array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp' )[ $size['mime'] ?? '' ] ?? '' ) : '';
	if ( '' === $ext ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}

	$att = media_handle_sideload(
		array(
			'name'     => sanitize_title( get_post_field( 'post_title', $post_id, 'raw' ) ?: 'event' ) . '-' . $post_id . '.' . $ext,
			'tmp_name' => $tmp,
		),
		$post_id,
		get_post_field( 'post_title', $post_id, 'raw' )
	);
	if ( is_wp_error( $att ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	update_post_meta( (int) $att, '_oria_image_source', $url );
	return (int) $att;
}

function fingerprint( string $title, string $start, string $suburb ): string {
	$norm = preg_replace( '/[^a-z0-9]/', '', strtolower( $title ) ) ?? '';
	return md5( $norm . '|' . substr( $start, 0, 10 ) . '|' . strtolower( trim( $suburb ) ) );
}

/**
 * An existing event this candidate duplicates. Strongest signal first:
 * same source URL, then exact fingerprint, then same-day fuzzy title match
 * (catches the same event named differently across platforms, retitled by
 * the AI, and member-created twins).
 */
function find_twin( string $fingerprint, string $title, string $start, string $src_url = '', string $raw_title = '' ): int {
	if ( '' !== $src_url ) {
		$by_url = get_posts(
			array(
				'post_type'      => 'event',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_oria_src_url',
				'meta_value'     => $src_url,
			)
		);
		if ( $by_url ) {
			return (int) $by_url[0];
		}
	}

	$exact = get_posts(
		array(
			'post_type'      => 'event',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_oria_fingerprint',
			'meta_value'     => $fingerprint,
		)
	);
	if ( $exact ) {
		return (int) $exact[0];
	}

	$same_day = get_posts(
		array(
			'post_type'      => 'event',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'event_start',
					'value'   => array( substr( $start, 0, 10 ) . ' 00:00:00', substr( $start, 0, 10 ) . ' 23:59:59' ),
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				),
			),
		)
	);
	$norm    = static fn( string $t ): string => preg_replace( '/[^a-z0-9]/', '', strtolower( $t ) ) ?? '';
	$needles = array_filter( array_unique( array( $norm( $title ), $norm( $raw_title ) ) ) );
	foreach ( $same_day as $id ) {
		$b = $norm( get_post_field( 'post_title', $id, 'raw' ) );
		if ( '' === $b ) {
			continue;
		}
		foreach ( $needles as $a ) {
			similar_text( $a, $b, $pct );
			if ( $pct >= 80 ) {
				return (int) $id;
			}
		}
	}
	return 0;
}

/** Write fields, provenance and terms. ACF key references included so the
 *  fields edit normally in the admin. */
function save_event( int $id, array $r, array $c, string $fingerprint ): void {
	$fields = array(
		'event_start'       => array( (string) $r['start'] ? local_datetime( (string) $r['start'] ) : '', 'field_oria_event_start' ),
		'event_end'         => array( (string) $r['end'] ? local_datetime( (string) $r['end'] ) : '', 'field_oria_event_end' ),
		'price'             => array( (string) $r['price'], 'field_oria_event_price' ),
		'venue'             => array( trim( implode( ', ', array_filter( array( (string) $r['venue'], (string) $r['suburb'] ) ) ) ), 'field_oria_event_venue' ),
		'event_description' => array( (string) $r['description'] ? '<p>' . esc_html( (string) $r['description'] ) . '</p>' : '', 'field_oria_event_description' ),
		'booking_url'       => array( (string) $c['url'], 'field_oria_event_booking' ),
	);
	foreach ( $fields as $name => $pair ) {
		if ( '' !== $pair[0] ) {
			update_post_meta( $id, $name, $pair[0] );
			update_post_meta( $id, "_{$name}", $pair[1] );
		}
	}

	update_post_meta( $id, '_oria_src', wp_parse_url( (string) $c['source_url'], PHP_URL_HOST ) ?: 'unknown' );
	update_post_meta( $id, '_oria_src_url', (string) $c['url'] );
	update_post_meta( $id, '_oria_organiser', (string) $r['organiser'] );
	update_post_meta( $id, '_oria_image_url', (string) $c['image'] );

	// Site-owner decision (2026-08-09): pull the organiser's banner
	// automatically. Provenance is stamped on the attachment so any image
	// can be traced and removed on request. Failures just leave the
	// branded tile in place and retry next run.
	if ( '' !== (string) $c['image'] && ! has_post_thumbnail( $id ) ) {
		$att = sideload_image( (string) $c['image'], $id );
		if ( $att ) {
			set_post_thumbnail( $id, $att );
		}
	}
	update_post_meta( $id, '_oria_confidence', (string) ( $r['confidence'] ?? '' ) );
	update_post_meta( $id, '_oria_fingerprint', $fingerprint );
	update_post_meta( $id, '_oria_verified', current_time( 'mysql' ) );

	wp_set_object_terms( $id, (string) $r['type'], 'event_type' );
	$practice = Taxonomy\practice_for( (string) $r['type'] );
	if ( '' !== $practice ) {
		wp_set_object_terms( $id, $practice, 'practice' );
	}
	if ( '' !== (string) $r['suburb'] ) {
		$area = term_exists( (string) $r['suburb'], 'area' );
		if ( $area ) {
			wp_set_object_terms( $id, (int) $area['term_id'], 'area' );
		}
	}
}
