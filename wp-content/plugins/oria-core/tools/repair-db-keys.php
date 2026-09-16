<?php
/**
 * Check — and optionally repair — the primary keys on the plugin's own tables.
 *
 * A table created by an older schema can have a primary key with no
 * AUTO_INCREMENT, and dbDelta will never add one: it alters columns but not
 * their auto-increment flag. Every insert then writes 0, the first row takes
 * it, and every later row is rejected as a duplicate key. $wpdb->insert()
 * returns false and nothing reports it, so the table simply stops growing.
 *
 * That is what happened to the review moderation log here — one row, and
 * every moderation decision since refused.
 *
 * SAFETY
 *   Dry run is the default and writes nothing. --apply repairs.
 *   Repair renumbers existing rows from 1 in key order, then adds the
 *   AUTO_INCREMENT. No row is deleted and no column is dropped.
 *   Take a database backup first; this alters tables in place.
 *
 * USAGE (from the WordPress root, on the server)
 *     php wp-content/plugins/oria-core/tools/repair-db-keys.php
 *     php wp-content/plugins/oria-core/tools/repair-db-keys.php --apply
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

// Before WordPress loads: the installer runs on plugins_loaded and would
// otherwise repair the very tables this run is supposed to report on.
define( 'ORIA_SKIP_DB_REPAIR', true );

require dirname( __DIR__, 4 ) . '/wp-load.php';

if ( ! function_exists( '\Oria\Core\Db\keyed_tables' ) ) {
	fwrite( STDERR, "This build of oria-core has no keyed_tables(); pull the latest first.\n" );
	exit( 1 );
}

global $wpdb;

$apply = in_array( '--apply', $argv ?? array(), true );
echo $apply ? "APPLY\n\n" : "DRY RUN — nothing will be written. Add --apply to repair.\n\n";

$broken = 0;
$fixed  = 0;

foreach ( \Oria\Core\Db\keyed_tables() as $table => $column ) {
	$short = str_replace( $wpdb->prefix, '', (string) $table );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		printf( "  %-22s table does not exist here\n", $short );
		continue;
	}

	$col  = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) );
	$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
	$zero = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = 0" );
	// phpcs:enable

	$ok = $col && str_contains( strtolower( (string) $col->Extra ), 'auto_increment' );

	if ( $ok ) {
		printf( "  %-22s OK — %s numbers itself, %d row(s)\n", $short, (string) $column, $rows );
		continue;
	}

	$broken++;
	printf(
		"  %-22s BROKEN — %s has no AUTO_INCREMENT, %d row(s), %d of them numbered 0\n",
		$short,
		(string) $column,
		$rows,
		$zero
	);
	echo "                         inserts are being rejected as duplicate keys\n";

	if ( ! $apply ) {
		continue;
	}

	if ( \Oria\Core\Db\ensure_auto_increment( (string) $table, (string) $column ) ) {
		$fixed++;
		// Prove it: write a row, read its key, remove it again.
		$probe = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			(string) $table,
			array( (string) $column => null ) + probe_row( (string) $table ),
			null
		);
		$new_id = (int) $wpdb->insert_id;
		if ( $probe && $new_id > 0 ) {
			$wpdb->delete( (string) $table, array( (string) $column => $new_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			printf( "                         repaired — a test row took key %d and was removed again\n", $new_id );
		} else {
			printf( "                         repaired, but the test insert failed: %s\n", $wpdb->last_error ?: 'no reason given' );
		}
	} else {
		echo "                         repair did not run — check the error log\n";
	}
}

/**
 * The minimum a row needs to be insertable, per table, for the probe.
 *
 * @return array<string, mixed>
 */
function probe_row( string $table ): array {
	global $wpdb;
	$now = current_time( 'mysql', true );

	if ( $table === \Oria\Core\Db\review_log() ) {
		return array( 'comment_id' => 0, 'action' => 'probe', 'created_at' => $now );
	}
	if ( $table === \Oria\Core\Db\members() ) {
		return array( 'user_id' => 0, 'created_at' => $now );
	}
	if ( $table === \Oria\Core\Db\member_tokens() ) {
		return array( 'user_id' => 0, 'token' => 'probe-' . wp_generate_password( 12, false ), 'created_at' => $now );
	}
	return array();
}

echo "\n";
if ( ! $broken ) {
	echo "Nothing to repair — every primary key numbers itself.\n";
} elseif ( $apply ) {
	printf( "Repaired %d of %d table(s).\n", $fixed, $broken );
} else {
	printf( "%d table(s) need repair. Re-run with --apply.\n", $broken );
}
