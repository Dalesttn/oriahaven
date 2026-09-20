<?php
/**
 * Fill in an event from what its own booking page publishes.
 *
 * The ingest reads a listing page once and files what it found. Months
 * later the event page itself is still carrying far more than we kept:
 * the exact street address, whether it is still going ahead, whether
 * there are tickets left, what each tier costs, and who is running it.
 *
 * All of it comes from the schema.org JSON-LD those platforms publish --
 * a machine-readable block whose entire purpose is to be read by
 * directories like this one. Nothing here scrapes prose, guesses, or
 * infers: a fact is stored only when the organiser stated it, and a
 * field with nothing behind it stays empty rather than being filled with
 * something plausible.
 *
 * Every fetch goes through Fetch\get(), which honours robots.txt and
 * identifies itself, and every stored fact carries the URL it came from
 * and the date it was read, so anything here can be checked or removed.
 *
 * @package Oria\Ingest
 */

declare(strict_types=1);

namespace Oria\Ingest\Enrich;

use Oria\Ingest\Fetch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where the facts live. */
const META_STREET  = '_oria_ev_street';
const META_STATUS  = '_oria_ev_status';
const META_AVAIL   = '_oria_ev_availability';
const META_TIERS   = '_oria_ev_tiers';
const META_ORG     = '_oria_ev_organiser';
const META_ORG_URL = '_oria_ev_organiser_url';
const META_FROM    = '_oria_ev_enriched_from';
const META_AT      = '_oria_ev_enriched_at';

/** On an image: where it came from, so it can be credited and removed. */
const META_IMG_SRC    = '_oria_img_source_url';
const META_IMG_ORIGIN = '_oria_img_origin';

/**
 * Read one event's booking page and store what it says.
 *
 * @return array{found: bool, fields: list<string>, note: string}
 */
function one( int $event_id, bool $dry = false, bool $with_image = false ): array {
	$url = (string) get_field( 'booking_url', $event_id );
	if ( '' === $url ) {
		return array( 'found' => false, 'fields' => array(), 'note' => 'no booking url' );
	}
	if ( ! Fetch\allowed( $url ) ) {
		return array( 'found' => false, 'fields' => array(), 'note' => 'robots.txt says no' );
	}

	$html = Fetch\get( $url );
	if ( null === $html ) {
		return array( 'found' => false, 'fields' => array(), 'note' => 'could not fetch' );
	}

	$rows = Fetch\events_from_html( $html, $url );
	if ( ! $rows ) {
		return array( 'found' => false, 'fields' => array(), 'note' => 'no structured data on the page' );
	}

	/*
	 * A page can carry several events -- a series, or a venue's whole
	 * programme. The one whose title is closest to ours is the one this
	 * post is about; anything else would quietly attach another event's
	 * price to this page.
	 */
	$row  = $rows[0];
	$mine = strtolower( wp_strip_all_tags( get_the_title( $event_id ) ) );
	$best = -1.0;
	foreach ( $rows as $candidate ) {
		similar_text( $mine, strtolower( (string) $candidate['title'] ), $pct );
		if ( $pct > $best ) {
			$best = (float) $pct;
			$row  = $candidate;
		}
	}
	if ( $best < 55.0 && count( $rows ) > 1 ) {
		return array( 'found' => false, 'fields' => array(), 'note' => 'no event on that page matches this title' );
	}

	$wrote = array();
	$set   = static function ( string $key, string $value ) use ( $event_id, $dry, &$wrote ): void {
		if ( '' === $value ) {
			return;
		}
		$wrote[] = $key;
		if ( ! $dry ) {
			update_post_meta( $event_id, $key, $value );
		}
	};

	$set( META_STREET, (string) ( $row['street'] ?? '' ) );
	$set( META_STATUS, (string) ( $row['status'] ?? '' ) );
	$set( META_AVAIL, (string) ( $row['availability'] ?? '' ) );
	$set( META_ORG, (string) ( $row['organiser'] ?? '' ) );
	$set( META_ORG_URL, (string) ( $row['organiser_url'] ?? '' ) );

	$tiers = (array) ( $row['tiers'] ?? array() );
	if ( $tiers ) {
		$wrote[] = META_TIERS;
		if ( ! $dry ) {
			update_post_meta( $event_id, META_TIERS, wp_json_encode( $tiers ) );
		}
	}

	if ( $wrote && ! $dry ) {
		update_post_meta( $event_id, META_FROM, esc_url_raw( $url ) );
		update_post_meta( $event_id, META_AT, current_time( 'mysql' ) );
	}

	/*
	 * The picture, only when asked for and only when the event has none.
	 * It is the organiser's own image for this event -- the one they
	 * published in the page's structured data -- and it is stored with
	 * the address it came from so it can be credited and, if anyone
	 * objects, found and removed in one query.
	 */
	if ( $with_image && ! has_post_thumbnail( $event_id ) ) {
		$img = (string) ( $row['image'] ?? '' );
		if ( '' !== $img ) {
			$wrote[] = 'image';
			if ( ! $dry ) {
				$att = sideload( $img, $event_id, $url );
				if ( is_wp_error( $att ) ) {
					$wrote[] = 'image-failed';
				}
			}
		}
	}

	return array( 'found' => (bool) $wrote, 'fields' => $wrote, 'note' => $wrote ? '' : 'nothing new' );
}

/**
 * Bring an image in, recording where it came from.
 *
 * @return int|\WP_Error Attachment id.
 */
function sideload( string $url, int $event_id, string $page_url ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$id = media_sideload_image( $url, $event_id, null, 'id' );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	update_post_meta( (int) $id, META_IMG_SRC, esc_url_raw( $url ) );
	update_post_meta( (int) $id, META_IMG_ORIGIN, esc_url_raw( $page_url ) );
	set_post_thumbnail( $event_id, (int) $id );
	return (int) $id;
}

/* ------------------------------------------------------------------ read */

/** The stored ticket tiers, or an empty list. */
function tiers( int $event_id ): array {
	$raw = (string) get_post_meta( $event_id, META_TIERS, true );
	$out = $raw ? json_decode( $raw, true ) : array();
	return is_array( $out ) ? $out : array();
}

/** What the organiser last said about the event going ahead. */
function status( int $event_id ): string {
	return (string) get_post_meta( $event_id, META_STATUS, true );
}

/** Whether tickets were still available when we last looked. */
function availability( int $event_id ): string {
	return (string) get_post_meta( $event_id, META_AVAIL, true );
}

/** @return array{name: string, url: string} */
function organiser( int $event_id ): array {
	return array(
		'name' => (string) get_post_meta( $event_id, META_ORG, true ),
		'url'  => (string) get_post_meta( $event_id, META_ORG_URL, true ),
	);
}

/** The full street address, when the source published one. */
function street( int $event_id ): string {
	return (string) get_post_meta( $event_id, META_STREET, true );
}

/** When these facts were read, as a display date. */
function checked_on( int $event_id ): string {
	$at = (string) get_post_meta( $event_id, META_AT, true );
	return $at ? (string) mysql2date( 'j F Y', $at ) : '';
}

/* ------------------------------------------------------------------- cli */

if ( defined( 'WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command(
		'oria events enrich',
		/**
		 * Read each event's booking page and store the facts it publishes.
		 *
		 * Reads the schema.org block those platforms provide for exactly
		 * this purpose. Nothing is guessed: a field the organiser did not
		 * publish stays empty.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Report what would be stored without fetching images or writing.
		 *
		 * [--with-images]
		 * : Also bring in the organiser's own event image where the event
		 * has none. The image is stored with the page it came from.
		 *
		 * [--limit=<n>]
		 * : Stop after this many events.
		 *
		 * [--id=<id>]
		 * : Just this one event.
		 *
		 * ## EXAMPLES
		 *
		 *     wp oria events enrich --dry-run
		 *     wp oria events enrich
		 *     wp oria events enrich --with-images --limit=5
		 */
		function ( array $args, array $assoc ): void {
			$dry    = isset( $assoc['dry-run'] );
			$images = isset( $assoc['with-images'] );
			$limit  = isset( $assoc['limit'] ) ? max( 0, (int) $assoc['limit'] ) : 0;

			$ids = isset( $assoc['id'] )
				? array( (int) $assoc['id'] )
				: ( function_exists( '\Oria\Core\Events\upcoming' ) ? \Oria\Core\Events\upcoming( array( 'limit' => 300 ) ) : array() );

			$done = 0;
			$ok   = 0;
			$none = 0;

			foreach ( $ids as $id ) {
				if ( $limit && $done >= $limit ) {
					break;
				}
				++$done;

				$res = one( (int) $id, $dry, $images );
				if ( $res['found'] ) {
					++$ok;
					\WP_CLI::log( sprintf( '  %-44s %s', mb_substr( wp_strip_all_tags( get_the_title( (int) $id ) ), 0, 42 ), implode( ', ', $res['fields'] ) ) );
				} else {
					++$none;
					\WP_CLI::log( sprintf( '  %-44s -- %s', mb_substr( wp_strip_all_tags( get_the_title( (int) $id ) ), 0, 42 ), $res['note'] ) );
				}

				// Someone else's server, one request at a time.
				if ( $done < count( $ids ) ) {
					sleep( 1 );
				}
			}

			\WP_CLI::success(
				sprintf(
					'%d read, %d gained something, %d had nothing new.%s',
					$done,
					$ok,
					$none,
					$dry ? ' (dry run: nothing written)' : ''
				)
			);
		}
	);
}
