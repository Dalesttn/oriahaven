<?php
/**
 * Admin batch import: Listings → Import batch.
 *
 * Upload a JSON seed file (the real-listings-N.json format) and run the
 * SAME importer the CLI uses — add-only, duplicate-checked by id, name,
 * phone and website domain, with missing practice categories and area
 * terms created on the way. Dry run is the default so every file gets a
 * preview before it writes anything.
 *
 * Reuse trick: the Import\Command class talks to \WP_CLI, which doesn't
 * exist on web requests — so a four-method collector is aliased to that
 * name here (log/warning/success collect lines, error throws). The CLI
 * itself is unaffected: when the real WP_CLI exists, the alias is skipped.
 */

declare(strict_types=1);

namespace Oria\Core\AdminImport;

use Oria\Core\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TRANSIENT = 'oria_admin_import_report';
const MAX_BYTES = 4 * 1024 * 1024;

/** Collects what the importer would print; error() aborts the run. */
class CliShim {
	/** @var array<string> */
	public static array $lines = array();

	public static function log( $msg ): void {
		self::$lines[] = (string) $msg;
	}
	public static function warning( $msg ): void {
		self::$lines[] = 'Warning: ' . (string) $msg;
	}
	public static function success( $msg ): void {
		self::$lines[] = '✔ ' . (string) $msg;
	}
	public static function error( $msg ): void {
		throw new \RuntimeException( (string) $msg );
	}
}

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_import_batch', __NAMESPACE__ . '\handle' );
}

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=listing',
		__( 'Import batch', 'oria' ),
		__( 'Import batch', 'oria' ),
		'manage_options',
		'oria-import-batch',
		__NAMESPACE__ . '\screen'
	);
}

function screen(): void {
	$report = get_transient( TRANSIENT );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Import a listings batch', 'oria' ); ?></h1>
		<p style="max-width:62ch"><?php esc_html_e( 'Upload a JSON seed file in the same format as the existing real-listings files: top-level "categories", "regions" and "listings". The import only ADDS — anything already in the directory (matched by id, name, phone or website domain) is reported and left untouched, claimed listings are never modified, and missing categories or suburbs are created automatically.', 'oria' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'oria_import_batch' ); ?>
			<input type="hidden" name="action" value="oria_import_batch">
			<p><input type="file" name="seed" accept=".json,application/json" required></p>
			<p>
				<label><input type="checkbox" name="dry" value="1" checked> <?php esc_html_e( 'Dry run — report what would happen without writing anything', 'oria' ); ?></label>
			</p>
			<p><button class="button button-primary"><?php esc_html_e( 'Run import', 'oria' ); ?></button></p>
		</form>

		<?php if ( is_array( $report ) && $report ) : ?>
			<h2><?php echo $report['dry'] ? esc_html__( 'Dry-run result (nothing was written)', 'oria' ) : esc_html__( 'Import result', 'oria' ); ?></h2>
			<pre style="background:#fff;border:1px solid #dcdcde;padding:12px;max-width:52rem;overflow:auto;max-height:420px"><?php echo esc_html( implode( "\n", (array) $report['lines'] ) ); ?></pre>
			<?php if ( $report['dry'] ) : ?>
				<p><?php esc_html_e( 'Happy with the preview? Upload the same file again with the dry-run box unticked.', 'oria' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

function handle(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nope.' );
	}
	check_admin_referer( 'oria_import_batch' );

	$back = admin_url( 'edit.php?post_type=listing&page=oria-import-batch' );
	$dry  = ! empty( $_POST['dry'] );

	$fail = static function ( string $why ) use ( $back, $dry ): void {
		set_transient( TRANSIENT, array( 'dry' => $dry, 'lines' => array( 'Import not run: ' . $why ) ), HOUR_IN_SECONDS );
		wp_safe_redirect( $back );
		exit;
	};

	$file = $_FILES['seed'] ?? null; // phpcs:ignore WordPress.Security -- validated below.
	if ( ! $file || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? -1 ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
		$fail( __( 'the file failed to upload.', 'oria' ) );
	}
	if ( (int) $file['size'] > MAX_BYTES ) {
		$fail( __( 'the file is larger than 4MB.', 'oria' ) );
	}

	$data = json_decode( (string) file_get_contents( (string) $file['tmp_name'] ), true );
	if ( ! is_array( $data ) || empty( $data['listings'] ) || ! is_array( $data['listings'] ) ) {
		$fail( __( 'that is not a valid seed file — expected JSON with a top-level "listings" array.', 'oria' ) );
	}

	// The importer speaks WP_CLI; on web requests, that's our collector.
	if ( ! class_exists( '\WP_CLI' ) ) {
		class_alias( __NAMESPACE__ . '\CliShim', 'WP_CLI' );
	}
	CliShim::$lines = array();

	try {
		$assoc = $dry ? array( 'dry-run' => true ) : array();
		( new Import\Command() )->import( array( (string) $file['tmp_name'] ), $assoc );
	} catch ( \RuntimeException $e ) {
		CliShim::$lines[] = 'Error: ' . $e->getMessage();
	}

	set_transient( TRANSIENT, array( 'dry' => $dry, 'lines' => CliShim::$lines ), HOUR_IN_SECONDS );
	wp_safe_redirect( $back );
	exit;
}
