<?php
/**
 * Work in Wellness: uploads.
 *
 * CVs are private (brief section 89): PDF or DOCX, 5 MB at most, written to
 * uploads/oria-private/cv/ behind a deny-all .htaccess and an index.php, and
 * only ever handed out by routes.php serve_file() after an access check. The
 * attachment row exists so the file has an id and an owner; it is flagged
 * private and kept out of every media library query.
 *
 * Profile photos are ordinary public images (JPEG/PNG/WebP, 5 MB), because a
 * photo on a public profile is meant to be seen.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work\Files;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAX_BYTES  = 5 * MB_IN_BYTES;
const CV_MIMES   = array(
	'pdf'  => 'application/pdf',
	'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
);
const IMG_MIMES  = array(
	'jpg|jpeg' => 'image/jpeg',
	'png'      => 'image/png',
	'webp'     => 'image/webp',
);
const PRIVATE_FLAG = '_oria_private_file';

/** Evidence for verification: a scan or photo of a certificate, or a PDF. */
const EVIDENCE_MIMES = array(
	'pdf'      => 'application/pdf',
	'jpg|jpeg' => 'image/jpeg',
	'png'      => 'image/png',
	'webp'     => 'image/webp',
);

function private_dir( string $sub = 'cv' ): string {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'oria-private/' . sanitize_key( $sub );
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$root = dirname( $dir );
	if ( ! file_exists( $root . '/.htaccess' ) ) {
		file_put_contents( $root . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	foreach ( array( $root, $dir ) as $d ) {
		if ( ! file_exists( $d . '/index.php' ) ) {
			file_put_contents( $d . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
	return $dir;
}

/**
 * Store an uploaded CV from $_FILES[$field]. Returns the attachment id, 0
 * when nothing was sent, or a WP_Error explaining what was wrong.
 *
 * @return int|\WP_Error
 */
function store_cv( string $field, int $user_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the form.
	$f = $_FILES[ $field ] ?? null;
	if ( ! is_array( $f ) || UPLOAD_ERR_NO_FILE === (int) ( $f['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
		return 0;
	}
	if ( UPLOAD_ERR_OK !== (int) $f['error'] || ! is_uploaded_file( (string) $f['tmp_name'] ) ) {
		return new \WP_Error( 'upload', __( 'The file did not upload. Please try again.', 'oria' ) );
	}
	if ( (int) $f['size'] > MAX_BYTES ) {
		return new \WP_Error( 'size', __( 'Your CV needs to be under 5 MB.', 'oria' ) );
	}
	$check = wp_check_filetype_and_ext( (string) $f['tmp_name'], (string) $f['name'], CV_MIMES );
	if ( empty( $check['ext'] ) || ! in_array( $check['type'], CV_MIMES, true ) ) {
		return new \WP_Error( 'type', __( 'Your CV needs to be a PDF or Word (.docx) file.', 'oria' ) );
	}
	$dir  = private_dir();
	$name = wp_unique_filename( $dir, 'cv-' . $user_id . '-' . wp_generate_password( 10, false, false ) . '.' . $check['ext'] );
	$dest = $dir . '/' . $name;
	if ( ! move_uploaded_file( (string) $f['tmp_name'], $dest ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions
		return new \WP_Error( 'move', __( 'The file could not be saved. Please try again.', 'oria' ) );
	}
	@chmod( $dest, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	$id = wp_insert_attachment(
		array(
			'post_title'     => sanitize_file_name( (string) $f['name'] ),
			'post_mime_type' => $check['type'],
			'post_status'    => 'private',
			'post_author'    => $user_id,
			'guid'           => '',
		),
		$dest
	);
	if ( ! $id || is_wp_error( $id ) ) {
		wp_delete_file( $dest );
		return new \WP_Error( 'save', __( 'The file could not be saved. Please try again.', 'oria' ) );
	}
	update_post_meta( (int) $id, PRIVATE_FLAG, 1 );
	return (int) $id;
}

/**
 * Store verification evidence from $_FILES[$field] in the private folder.
 * Same walls as a CV; deleted again once an admin has decided.
 *
 * @return int|\WP_Error
 */
function store_evidence( string $field, int $user_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the form.
	$f = $_FILES[ $field ] ?? null;
	if ( ! is_array( $f ) || UPLOAD_ERR_NO_FILE === (int) ( $f['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
		return 0;
	}
	if ( UPLOAD_ERR_OK !== (int) $f['error'] || ! is_uploaded_file( (string) $f['tmp_name'] ) || (int) $f['size'] > MAX_BYTES ) {
		return new \WP_Error( 'size', __( 'The file needs to be a PDF or photo under 5 MB.', 'oria' ) );
	}
	$check = wp_check_filetype_and_ext( (string) $f['tmp_name'], (string) $f['name'], EVIDENCE_MIMES );
	if ( empty( $check['ext'] ) || ! in_array( $check['type'], EVIDENCE_MIMES, true ) ) {
		return new \WP_Error( 'type', __( 'The file needs to be a PDF or photo under 5 MB.', 'oria' ) );
	}
	if ( 0 === strpos( (string) $check['type'], 'image/' ) && ! @getimagesize( (string) $f['tmp_name'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return new \WP_Error( 'type', __( 'The file needs to be a PDF or photo under 5 MB.', 'oria' ) );
	}
	$dir  = private_dir( 'verify' );
	$dest = $dir . '/' . wp_unique_filename( $dir, 'ev-' . $user_id . '-' . wp_generate_password( 12, false, false ) . '.' . $check['ext'] );
	if ( ! move_uploaded_file( (string) $f['tmp_name'], $dest ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions
		return new \WP_Error( 'move', __( 'The file could not be saved. Please try again.', 'oria' ) );
	}
	@chmod( $dest, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	$id = wp_insert_attachment( array( 'post_title' => 'verification evidence', 'post_mime_type' => $check['type'], 'post_status' => 'private', 'post_author' => $user_id, 'guid' => '' ), $dest );
	if ( ! $id || is_wp_error( $id ) ) {
		wp_delete_file( $dest );
		return new \WP_Error( 'save', __( 'The file could not be saved. Please try again.', 'oria' ) );
	}
	update_post_meta( (int) $id, PRIVATE_FLAG, 1 );
	return (int) $id;
}

/** Absolute path of a private file, or '' if the id is not one of ours. */
function path( int $id ): string {
	if ( ! $id || ! get_post_meta( $id, PRIVATE_FLAG, true ) ) {
		return '';
	}
	return (string) get_attached_file( $id );
}

/** Private files never appear in a media library, not even the admin's grid. */
function hide_private( \WP_Query $q ): void {
	if ( 'attachment' !== $q->get( 'post_type' ) ) {
		return;
	}
	$mq   = (array) $q->get( 'meta_query' );
	$mq[] = array( 'key' => PRIVATE_FLAG, 'compare' => 'NOT EXISTS' );
	$q->set( 'meta_query', $mq );
}

function hide_private_ajax( array $args ): array {
	$args['meta_query']   = (array) ( $args['meta_query'] ?? array() ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$args['meta_query'][] = array( 'key' => PRIVATE_FLAG, 'compare' => 'NOT EXISTS' );
	return $args;
}

/** Deleting the attachment deletes the file (core does this) -- and nothing links to it publicly. */

/**
 * Store an uploaded public photo from $_FILES[$field] and attach it to a post.
 *
 * @return int|\WP_Error Attachment id, 0 for nothing sent.
 */
function store_photo( string $field, int $post_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$f = $_FILES[ $field ] ?? null;
	if ( ! is_array( $f ) || UPLOAD_ERR_NO_FILE === (int) ( $f['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
		return 0;
	}
	if ( UPLOAD_ERR_OK !== (int) $f['error'] || (int) $f['size'] > MAX_BYTES ) {
		return new \WP_Error( 'size', __( 'Your photo needs to be a JPEG, PNG or WebP under 5 MB.', 'oria' ) );
	}
	$check = wp_check_filetype_and_ext( (string) $f['tmp_name'], (string) $f['name'], IMG_MIMES );
	if ( empty( $check['type'] ) || ! in_array( $check['type'], IMG_MIMES, true ) || ! @getimagesize( (string) $f['tmp_name'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return new \WP_Error( 'type', __( 'Your photo needs to be a JPEG, PNG or WebP under 5 MB.', 'oria' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_upload( $field, $post_id, array(), array( 'test_form' => false, 'mimes' => IMG_MIMES ) );
	return is_wp_error( $id ) ? new \WP_Error( 'save', __( 'The photo could not be saved. Please try again.', 'oria' ) ) : (int) $id;
}
