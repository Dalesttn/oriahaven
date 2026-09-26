<?php
/**
 * Repairing listing ACF reference meta that points at the wrong field.
 *
 * ACF stores two rows per field: the value under the field's name
 * ("kind") and a reference under "_name" holding the field KEY
 * ("field_oria_kind"). The importer used to call update_field() by name,
 * and by name ACF resolves to the first field of that name it finds:
 *
 *     _kind    -> field_oria_cls_kind       (Classes repeater sub-field)
 *     _format  -> field_oria_reset_format   (reset tool)
 *     _email   -> field_sec_contact_email   (a page section)
 *
 * The values were right; the references were not, so get_field() and the
 * admin editor could read the value through the wrong field's settings.
 * import.php now writes by key. This rewrites the references that are
 * already wrong and never touches a value row.
 *
 * Idempotent: a reference that already holds the right key is skipped.
 */

declare(strict_types=1);

namespace Oria\Core\AcfRefs;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Listing field name => the key it must reference (group_oria_listing). */
const FIELDS = array(
	'kind'   => 'field_oria_kind',
	'format' => 'field_oria_format',
	'email'  => 'field_oria_email',
);

/**
 * Every listing reference row for FIELDS that holds the wrong key.
 *
 * @return array<int, array{post_id:int, name:string, was:string}>
 */
function wrong_refs(): array {
	global $wpdb;

	$keys  = array_map( static fn( string $n ): string => '_' . $n, array_keys( FIELDS ) );
	$in    = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is placeholders only.
			"SELECT pm.post_id, pm.meta_key, pm.meta_value
			   FROM {$wpdb->postmeta} pm
			   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			  WHERE p.post_type = %s AND pm.meta_key IN ($in)
			  ORDER BY pm.post_id, pm.meta_key",
			array_merge( array( PostTypes\LISTING ), $keys )
		)
	);

	$out = array();
	foreach ( (array) $rows as $r ) {
		$name = substr( (string) $r->meta_key, 1 );
		if ( FIELDS[ $name ] !== (string) $r->meta_value ) {
			$out[] = array(
				'post_id' => (int) $r->post_id,
				'name'    => $name,
				'was'     => (string) $r->meta_value,
			);
		}
	}
	return $out;
}

/* ------------------------------------------------------------------- CLI */

if ( defined( 'WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command(
		'oria repair-acf-refs',
		/**
		 * Point listing _kind/_format/_email reference meta at the listing fields.
		 */
		new class() {
			/**
			 * Dry run by default. Rewrites reference meta only; values are
			 * never read or written.
			 *
			 * ## OPTIONS
			 *
			 * [--apply]
			 * : Write the fixes. Without it, only report.
			 *
			 * [--verbose]
			 * : List every post that would change.
			 *
			 * ## EXAMPLES
			 *
			 *     wp oria repair-acf-refs
			 *     wp oria repair-acf-refs --apply
			 */
			public function __invoke( array $args, array $assoc ): void {
				$apply = isset( $assoc['apply'] );
				$wrong = wrong_refs();

				$by = array();
				foreach ( $wrong as $w ) {
					$tag        = sprintf( '_%s: %s -> %s', $w['name'], '' === $w['was'] ? '(empty)' : $w['was'], FIELDS[ $w['name'] ] );
					$by[ $tag ] = ( $by[ $tag ] ?? 0 ) + 1;
					if ( isset( $assoc['verbose'] ) ) {
						\WP_CLI::log( sprintf( '    #%-6d %s', $w['post_id'], $tag ) );
					}
				}

				if ( ! $wrong ) {
					\WP_CLI::success( 'Every listing _kind/_format/_email reference already points at the listing field. Nothing to do.' );
					return;
				}

				foreach ( $by as $tag => $n ) {
					\WP_CLI::log( sprintf( '%5d  %s', $n, $tag ) );
				}
				\WP_CLI::log( sprintf( '%d reference row(s) on %d listing(s).', count( $wrong ), count( array_unique( array_column( $wrong, 'post_id' ) ) ) ) );

				if ( ! $apply ) {
					\WP_CLI::log( '- DRY RUN: nothing written. Re-run with --apply. -' );
					return;
				}

				$done = 0;
				foreach ( $wrong as $w ) {
					// The reference row only; the value row under $w['name'] is left alone.
					if ( update_post_meta( $w['post_id'], '_' . $w['name'], FIELDS[ $w['name'] ] ) ) {
						++$done;
					}
				}
				\WP_CLI::success( sprintf( '%d reference row(s) rewritten.', $done ) );
			}
		}
	);
}
