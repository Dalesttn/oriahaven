<?php
/**
 * Modality tile images, deployable.
 *
 * The pictures on the specialty cards are two things the normal deploy cannot
 * carry: image files (uploads is gitignored) and term meta (the database only
 * ever travels production -> local, never back). Choosing them is slow, human
 * work -- reviewing stock, rejecting the one with a brand logo on the shirt,
 * noticing that every "kinesiology" photo is actually kinesio taping -- and
 * redoing it on the server would be redoing the judgement, not just the
 * clicks.
 *
 * So the chosen set ships as files under data/tiles/<slug>.webp with a
 * tiles.json holding each one's alt text and credit, and this installs them:
 * sideload, attach, assign. Idempotent, so running it twice is safe, and a
 * tile somebody has since replaced by hand is left alone unless --force says
 * otherwise.
 *
 *   wp oria tiles --dry-run
 *   wp oria tiles
 *   wp oria tiles --force        # overwrite tiles already set
 */

declare(strict_types=1);

namespace Oria\Core\Tiles;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

function dir_path(): string {
	return ORIA_CORE_DIR . 'data/tiles';
}

/** @return array<string, array{name:string,alt:string,caption:string,source:string}> */
function manifest(): array {
	$file = dir_path() . '/tiles.json';
	if ( ! file_exists( $file ) ) {
		return array();
	}
	$data = json_decode( (string) file_get_contents( $file ), true );
	return is_array( $data ) ? $data : array();
}

/**
 * Install the shipped tiles.
 *
 * @param bool $dry   Report only.
 * @param bool $force Replace a tile that is already set.
 * @return array{set:int,skipped:int,missing:int,lines:string[]}
 */
function install( bool $dry = false, bool $force = false ): array {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$out = array(
		'set'     => 0,
		'skipped' => 0,
		'missing' => 0,
		'lines'   => array(),
	);

	/*
	 * The tiles ship as WebP -- 42% smaller than JPEG across this set. Every
	 * mainstream PHP build has supported it for years, but a host without it
	 * would accept the upload and then silently fail to generate the 720x540
	 * sub-size the cards actually request, leaving a broken image rather than
	 * an obvious error. Refuse up front instead.
	 */
	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		$out['lines'][] = 'the image editor on this server cannot process WebP, so the sub-sizes the cards need would not be generated';
		++$out['missing'];
		return $out;
	}

	foreach ( manifest() as $slug => $meta ) {
		$src = dir_path() . '/' . $slug . '.webp';

		$term = get_term_by( 'slug', $slug, 'specialty' );
		if ( ! $term instanceof \WP_Term ) {
			$out['lines'][] = sprintf( 'no such modality: %s', $slug );
			++$out['missing'];
			continue;
		}
		if ( ! file_exists( $src ) ) {
			$out['lines'][] = sprintf( '%s: image file missing from data/tiles', $slug );
			++$out['missing'];
			continue;
		}

		$current = function_exists( 'get_field' ) ? (int) get_field( 'tile_image', 'specialty_' . $term->term_id ) : 0;
		if ( $current && ! $force ) {
			$out['lines'][] = sprintf( '%s: already has a tile, left alone', $slug );
			++$out['skipped'];
			continue;
		}

		if ( $dry ) {
			$out['lines'][] = sprintf( '%s: would set tile (%s)', $slug, size_format( (int) filesize( $src ) ) );
			++$out['set'];
			continue;
		}

		/*
		 * Copy into uploads under a stable name rather than letting WordPress
		 * uniquify it, so re-running does not leave modality-x-1.webp,
		 * modality-x-2.webp behind it.
		 */
		$up   = wp_upload_dir();
		$name = 'modality-' . $slug . '.webp';
		$dest = trailingslashit( $up['path'] ) . $name;

		$existing = attachment_url_to_postid( trailingslashit( $up['url'] ) . $name );
		if ( ! copy( $src, $dest ) ) {
			$out['lines'][] = sprintf( '%s: could not write to uploads', $slug );
			++$out['missing'];
			continue;
		}

		if ( $existing ) {
			$att = $existing;
		} else {
			$att = wp_insert_attachment(
				array(
					'post_mime_type' => 'image/webp',
					'post_title'     => $meta['name'] . ' tile',
					'post_excerpt'   => (string) ( $meta['caption'] ?? '' ),
					'post_content'   => trim( ( $meta['caption'] ?? '' ) . ' ' . ( $meta['source'] ?? '' ) ),
					'post_status'    => 'inherit',
				),
				$dest
			);
			if ( is_wp_error( $att ) ) {
				$out['lines'][] = sprintf( '%s: %s', $slug, $att->get_error_message() );
				++$out['missing'];
				continue;
			}
		}

		wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $dest ) );
		update_post_meta( $att, '_wp_attachment_image_alt', (string) ( $meta['alt'] ?? '' ) );
		update_post_meta( $att, '_oria_source', (string) ( $meta['source'] ?? '' ) );

		update_field( 'tile_image', $att, 'specialty_' . $term->term_id );
		clean_term_cache( $term->term_id, 'specialty' );

		$out['lines'][] = sprintf( '%s: set (attachment %d)', $slug, $att );
		++$out['set'];
	}

	return $out;
}
