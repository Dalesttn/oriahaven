<?php
/**
 * Give each Best Of guide its header picture.
 *
 * The files ship in the plugin — assets/heroes/{slug}.webp — so the same
 * picture lands on every environment without anyone uploading anything.
 * This puts each one in the media library and sets it as that guide's
 * featured image, which is where single-best_of.php reads it from.
 *
 * Deliberately NOT part of seed-best-of.php. The guides are already
 * published and an editor may have changed their wording since; re-running
 * the whole seeder to attach a picture would put all of that back. This
 * touches one field, and only where it is empty.
 *
 * A guide that already has a featured image is left alone. So is a slug
 * with no file. Running it twice changes nothing the second time.
 *
 * USAGE (from the WordPress root)
 *     php wp-content/plugins/oria-core/tools/apply-best-of-heroes.php
 *     php wp-content/plugins/oria-core/tools/apply-best-of-heroes.php --apply
 *     php wp-content/plugins/oria-core/tools/apply-best-of-heroes.php --apply --replace
 *
 * Dry run is the default and writes nothing.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

use Oria\Core\BestOf;

$apply   = in_array( '--apply', $argv ?? array(), true );
$replace = in_array( '--replace', $argv ?? array(), true );

$dir = ORIA_CORE_DIR . 'assets/heroes/';

echo $apply
	? ( $replace ? "APPLY — existing header pictures will be replaced\n\n" : "APPLY\n\n" )
	: "DRY RUN — nothing is written. Add --apply to attach.\n\n";

$guides = get_posts(
	array(
		'post_type'      => BestOf\POST_TYPE,
		'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);

if ( ! $guides ) {
	exit( "No Best Of guides found.\n" );
}

$set     = 0;
$skipped = 0;
$nofile  = 0;

foreach ( $guides as $g ) {
	$slug = (string) $g->post_name;
	$file = $dir . $slug . '.webp';

	if ( ! is_readable( $file ) ) {
		printf( "  ? %-32s no picture shipped for this slug\n", $slug );
		$nofile++;
		continue;
	}

	if ( has_post_thumbnail( $g->ID ) && ! $replace ) {
		printf( "  = %-32s already has one — pass --replace to overwrite\n", $slug );
		$skipped++;
		continue;
	}

	printf( "  %s %-32s %s\n", has_post_thumbnail( $g->ID ) ? '~' : '+', $slug, size_format( (int) filesize( $file ) ) );

	if ( ! $apply ) {
		continue;
	}

	$id = hero_attachment( (int) $g->ID, $slug, $file );
	if ( $id > 0 ) {
		set_post_thumbnail( (int) $g->ID, $id );
		printf( "      attachment #%d\n", $id );
		$set++;
	}
}

echo "\n";
if ( $apply ) {
	printf( "%d set, %d left alone, %d with no picture shipped.\n", $set, $skipped, $nofile );
	echo "They show behind each guide's headline, and become its sharing image.\n";
} else {
	printf( "Would set %d, leave %d alone, %d have no picture shipped.\n", count( $guides ) - $skipped - $nofile, $skipped, $nofile );
}

/**
 * The picture in the media library, imported once and reused after that.
 *
 * Reuse matters more here than it looks: this is the kind of script that
 * gets run again six months later, and without the marker meta every run
 * would leave another copy of a 200KB file in uploads.
 */
function hero_attachment( int $post_id, string $slug, string $file ): int {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_oria_bestof_hero', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$uploaded = wp_upload_bits( $slug . '-hero.webp', null, (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! empty( $uploaded['error'] ) ) {
		printf( "      ! %s\n", $uploaded['error'] );
		return 0;
	}

	$attachment = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => get_the_title( $post_id ) . ' header',
			'post_status'    => 'inherit',
		),
		$uploaded['file'],
		$post_id
	);
	if ( is_wp_error( $attachment ) || ! $attachment ) {
		echo "      ! could not attach\n";
		return 0;
	}

	wp_update_attachment_metadata( (int) $attachment, wp_generate_attachment_metadata( (int) $attachment, $uploaded['file'] ) );
	update_post_meta( (int) $attachment, '_oria_bestof_hero', $slug );
	// Decoration behind a headline that already says what the page is, so
	// the alt text stays empty rather than describing furniture.
	update_post_meta( (int) $attachment, '_wp_attachment_image_alt', '' );

	return (int) $attachment;
}
