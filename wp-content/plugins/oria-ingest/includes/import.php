<?php
/**
 * Hand-curated events, imported from a JSON file.
 *
 * The crawler in pipeline.php watches a fixed list of pages every night. This
 * is the other half: a weekly file of events found and checked by hand, which
 * covers everything the watchlist cannot reach -- a studio announcing a
 * workshop on its own site, a one-off in a park, a market with a sound bath in
 * the corner.
 *
 * It deliberately reuses the crawler's machinery rather than repeating it:
 * Pipeline\fingerprint() and Pipeline\find_twin() decide what is already here,
 * so a curated file and the nightly crawl can never file the same event twice.
 *
 * Rules the file itself has to honour, because code cannot check them:
 *  - descriptions are written by us, never pasted from the source;
 *  - no images (an organiser's photo is theirs; submitted events carry their
 *    own, and everything else falls back to the category tile);
 *  - every event carries the page it was read from, and the date it was read.
 *
 * Usage:
 *     wp oria events import events/week-2026-09-22.json --dry-run
 *     wp oria events import events/week-2026-09-22.json
 *
 * @package Oria\Ingest
 */

declare(strict_types=1);

namespace Oria\Ingest\Import;

use Oria\Ingest\Pipeline;
use Oria\Ingest\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Marks an event this importer created, so a later file may correct it. */
const META_IMPORT = '_oria_import';

/** How far ahead a curated event may sit. Mirrors the crawler's horizon. */
const MAX_DAYS_OUT = 120;

/** Fields every row must carry. */
const REQUIRED = array( 'title', 'start', 'type', 'source_url' );

/**
 * Read and check a file without touching the database.
 *
 * @return array{events: array<int, array<string, mixed>>, errors: string[]}
 */
function read_file( string $path ): array {
	if ( ! is_readable( $path ) ) {
		return array( 'events' => array(), 'errors' => array( sprintf( 'Cannot read %s', $path ) ) );
	}

	$raw = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local CLI file, not a remote fetch.
	if ( ! is_array( $raw ) ) {
		return array( 'events' => array(), 'errors' => array( 'Not valid JSON.' ) );
	}

	// Either a bare array of events, or { "generated": ..., "events": [...] }.
	$events = isset( $raw['events'] ) && is_array( $raw['events'] ) ? $raw['events'] : $raw;
	$errors = array();
	$out    = array();

	foreach ( array_values( (array) $events ) as $i => $row ) {
		if ( ! is_array( $row ) ) {
			$errors[] = sprintf( 'Row %d is not an object.', $i + 1 );
			continue;
		}
		$row   = normalise( $row );
		$fault = fault( $row );
		if ( '' !== $fault ) {
			$errors[] = sprintf( 'Row %d (%s): %s', $i + 1, $row['title'] ?: 'untitled', $fault );
			continue;
		}
		$out[] = $row;
	}

	return array( 'events' => $out, 'errors' => $errors );
}

/** @param array<string, mixed> $row @return array<string, mixed> */
function normalise( array $row ): array {
	$get = static fn( string $k ): string => isset( $row[ $k ] ) && is_scalar( $row[ $k ] ) ? trim( (string) $row[ $k ] ) : '';

	$free  = ! empty( $row['free'] );
	$price = $get( 'price' );

	return array(
		'title'        => $get( 'title' ),
		'start'        => Pipeline\local_datetime( $get( 'start' ) ),
		'end'          => Pipeline\local_datetime( $get( 'end' ) ),
		'type'         => sanitize_title( $get( 'type' ) ),
		'venue'        => $get( 'venue' ),
		'suburb'       => $get( 'suburb' ),
		'price'        => $free ? __( 'Free', 'oria' ) : $price,
		'description'  => $get( 'description' ),
		'booking_url'  => esc_url_raw( $get( 'booking_url' ) ),
		'source_url'   => esc_url_raw( $get( 'source_url' ) ),
		'organiser'    => $get( 'organiser' ),
		'listing_slug' => sanitize_title( $get( 'listing_slug' ) ),
		'checked'      => $get( 'checked' ),
	);
}

/** The first thing wrong with a row, or '' when it is importable. */
function fault( array $row ): string {
	foreach ( REQUIRED as $key ) {
		if ( '' === (string) $row[ $key ] ) {
			return sprintf( 'missing %s', $key );
		}
	}

	$ts = strtotime( (string) $row['start'] );
	if ( ! $ts ) {
		return 'start is not a date we can read';
	}
	$now = (int) current_time( 'timestamp' );
	if ( $ts < $now ) {
		return 'starts in the past';
	}
	if ( $ts > $now + MAX_DAYS_OUT * DAY_IN_SECONDS ) {
		return sprintf( 'starts more than %d days out', MAX_DAYS_OUT );
	}
	if ( '' !== (string) $row['end'] && strtotime( (string) $row['end'] ) < $ts ) {
		return 'ends before it starts';
	}
	if ( ! term_exists( (string) $row['type'], 'event_type' ) ) {
		return sprintf( 'unknown event type "%s"', $row['type'] );
	}
	if ( '' !== (string) $row['suburb'] && ! term_exists( (string) $row['suburb'], 'area' ) ) {
		// Not fatal: the event still files, it just won't join an area page.
		return '';
	}

	return '';
}

/**
 * Import one file.
 *
 * @return array{created: int, updated: int, skipped: int, lines: string[], errors: string[]}
 */
function run( string $path, bool $dry_run = true, bool $publish = false ): array {
	$read    = read_file( $path );
	$result  = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'lines' => array(), 'errors' => $read['errors'] );
	$stamp   = gmdate( 'Y-m-d', (int) ( filemtime( $path ) ?: time() ) );
	$sources = array();

	foreach ( $read['events'] as $row ) {
		$fingerprint = Pipeline\fingerprint( (string) $row['title'], (string) $row['start'], (string) $row['suburb'] );
		$twin        = Pipeline\find_twin(
			$fingerprint,
			(string) $row['title'],
			(string) $row['start'],
			(string) $row['booking_url'],
			(string) $row['title']
		);

		// A file may correct an event this importer filed before. Anything
		// else -- a member's own event, a crawler find, a published page a
		// human has edited -- is left exactly as it is.
		$ours = $twin && '' !== (string) get_post_meta( $twin, META_IMPORT, true ) && 'draft' === get_post_status( $twin );

		if ( $twin && ! $ours ) {
			++$result['skipped'];
			$result['lines'][] = sprintf( 'skip    %s — already here as #%d', $row['title'], $twin );
			continue;
		}

		// Two rows in one file describing the same event.
		if ( isset( $sources[ $fingerprint ] ) ) {
			++$result['skipped'];
			$result['lines'][] = sprintf( 'skip    %s — duplicated inside this file', $row['title'] );
			continue;
		}
		$sources[ $fingerprint ] = true;

		if ( $dry_run ) {
			$result[ $ours ? 'updated' : 'created' ]++;
			$result['lines'][] = sprintf(
				'%s  %s — %s%s',
				$ours ? 'update' : 'create',
				$row['title'],
				(string) $row['start'],
				'' !== (string) $row['suburb'] ? ', ' . $row['suburb'] : ''
			);
			continue;
		}

		$id = $ours ? $twin : wp_insert_post(
			array(
				'post_type'   => 'event',
				'post_status' => $publish ? 'publish' : 'draft',
				'post_title'  => (string) $row['title'],
				'post_name'   => wp_unique_post_slug( sanitize_title( (string) $row['title'] ), 0, 'publish', 'event', 0 ),
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			++$result['skipped'];
			$result['errors'][] = sprintf( '%s — could not be created', $row['title'] );
			continue;
		}

		save( (int) $id, $row, $fingerprint, $stamp );
		$result[ $ours ? 'updated' : 'created' ]++;
		$result['lines'][] = sprintf( '%s  #%d %s', $ours ? 'update' : 'create', $id, $row['title'] );
	}

	return $result;
}

/** Write one curated event's fields, provenance and terms. */
function save( int $id, array $row, string $fingerprint, string $stamp ): void {
	/*
	 * A file names the suburb by slug, because that is what has to match the
	 * area term. The venue line is read by a person, so it gets the term's
	 * proper name -- "South Perth", not "south-perth".
	 */
	$suburb = (string) $row['suburb'];
	if ( '' !== $suburb ) {
		$term   = get_term_by( 'slug', $suburb, 'area' );
		$suburb = $term instanceof \WP_Term ? $term->name : ucwords( str_replace( '-', ' ', $suburb ) );
	}
	$venue = trim( implode( ', ', array_filter( array( (string) $row['venue'], $suburb ) ) ) );

	$fields = array(
		'event_start'       => array( (string) $row['start'], 'field_oria_event_start' ),
		'event_end'         => array( (string) $row['end'], 'field_oria_event_end' ),
		'price'             => array( (string) $row['price'], 'field_oria_event_price' ),
		'venue'             => array( $venue, 'field_oria_event_venue' ),
		'event_description' => array( '' !== (string) $row['description'] ? '<p>' . esc_html( (string) $row['description'] ) . '</p>' : '', 'field_oria_event_description' ),
		'booking_url'       => array( (string) $row['booking_url'] ?: (string) $row['source_url'], 'field_oria_event_booking' ),
	);
	foreach ( $fields as $name => $pair ) {
		if ( '' !== $pair[0] ) {
			update_post_meta( $id, $name, $pair[0] );
			update_post_meta( $id, "_{$name}", $pair[1] );
		}
	}

	// "Run by": only when the slug names a listing that actually exists.
	if ( '' !== (string) $row['listing_slug'] ) {
		$host = get_page_by_path( (string) $row['listing_slug'], OBJECT, 'listing' );
		if ( $host instanceof \WP_Post ) {
			update_post_meta( $id, 'listing', $host->ID );
			update_post_meta( $id, '_listing', 'field_oria_event_listing' );
		}
	}

	update_post_meta( $id, '_oria_src', wp_parse_url( (string) $row['source_url'], PHP_URL_HOST ) ?: 'curated' );
	update_post_meta( $id, '_oria_src_url', (string) $row['source_url'] );
	update_post_meta( $id, '_oria_organiser', (string) $row['organiser'] );
	update_post_meta( $id, '_oria_fingerprint', $fingerprint );
	update_post_meta( $id, '_oria_verified', '' !== (string) $row['checked'] ? (string) $row['checked'] : current_time( 'mysql' ) );
	update_post_meta( $id, META_IMPORT, $stamp );

	wp_set_object_terms( $id, (string) $row['type'], 'event_type' );
	$practice = Taxonomy\practice_for( (string) $row['type'] );
	if ( '' !== $practice ) {
		wp_set_object_terms( $id, $practice, 'practice' );
	}
	if ( '' !== (string) $row['suburb'] ) {
		$area = term_exists( (string) $row['suburb'], 'area' );
		if ( $area ) {
			wp_set_object_terms( $id, (int) $area['term_id'], 'area' );
		}
	}
}

/* -------------------------------------------------------------------- cli */

function bootstrap(): void {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	\WP_CLI::add_command(
		'oria events import',
		/**
		 * Import a curated events JSON file.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Path to the JSON file.
		 *
		 * [--dry-run]
		 * : Report what would happen and write nothing.
		 *
		 * [--publish]
		 * : File new events as published rather than drafts.
		 *
		 * ## EXAMPLES
		 *
		 *     wp oria events import events/week-2026-09-22.json --dry-run
		 *     wp oria events import events/week-2026-09-22.json
		 */
		static function ( array $args, array $assoc ): void {
			$dry = ! empty( $assoc['dry-run'] );
			$out = run( (string) $args[0], $dry, ! empty( $assoc['publish'] ) );

			foreach ( $out['lines'] as $line ) {
				\WP_CLI::line( $line );
			}
			foreach ( $out['errors'] as $error ) {
				\WP_CLI::warning( $error );
			}

			$summary = sprintf(
				'%d to create, %d to update, %d skipped, %d rejected.',
				$out['created'],
				$out['updated'],
				$out['skipped'],
				count( $out['errors'] )
			);
			if ( $dry ) {
				\WP_CLI::success( 'Dry run: ' . $summary . ' Nothing written.' );
				return;
			}
			\WP_CLI::success( $summary . ' New events are drafts unless --publish was given.' );
		}
	);
}
