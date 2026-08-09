<?php
/**
 * Admin: the watchlist + run-now screen under Workshops/Events, a Source
 * column on the events list so aggregated drafts are obvious at review
 * time, and a provenance box on the edit screen. Admin-only — the events
 * menu practitioners see is unaffected.
 */

declare(strict_types=1);

namespace Oria\Ingest\Admin;

use Oria\Ingest\Pipeline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_ingest_save', __NAMESPACE__ . '\save' );
	add_action( 'admin_post_oria_ingest_run', __NAMESPACE__ . '\run_now' );
	add_action( 'admin_post_oria_ingest_image', __NAMESPACE__ . '\import_image' );
	add_filter( 'manage_event_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_event_posts_custom_column', __NAMESPACE__ . '\column', 10, 2 );
	add_action( 'add_meta_boxes_event', __NAMESPACE__ . '\provenance_box' );
}

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=event',
		__( 'Event ingest', 'oria' ),
		__( 'Event ingest', 'oria' ),
		'manage_options',
		'oria-ingest',
		__NAMESPACE__ . '\screen'
	);
}

function screen(): void {
	$report = get_option( Pipeline\OPT_REPORT, array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Event ingest', 'oria' ); ?></h1>
		<p style="max-width:60ch"><?php esc_html_e( 'Pages watched for wellness events — one URL per line. Event pages, organiser/venue pages and .ics calendar feeds all work. New finds arrive as drafts for review; they never publish themselves.', 'oria' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'oria_ingest_save' ); ?>
			<input type="hidden" name="action" value="oria_ingest_save">
			<textarea name="watchlist" rows="10" style="width:100%;max-width:46rem;font-family:monospace" placeholder="https://events.humanitix.com/some-organiser&#10;https://www.eventbrite.com.au/o/some-studio-12345&#10;https://somestudio.com.au/events.ics"><?php echo esc_textarea( (string) get_option( Pipeline\OPT_WATCHLIST, '' ) ); ?></textarea>
			<p><button class="button button-primary"><?php esc_html_e( 'Save watchlist', 'oria' ); ?></button></p>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1rem">
			<?php wp_nonce_field( 'oria_ingest_run' ); ?>
			<input type="hidden" name="action" value="oria_ingest_run">
			<button class="button"><?php esc_html_e( 'Run ingest now', 'oria' ); ?></button>
			<span style="margin-left:.75rem;color:#666"><?php esc_html_e( 'Also runs automatically every day at ~3am.', 'oria' ); ?></span>
		</form>

		<?php if ( $report ) : ?>
			<h2 style="margin-top:2rem"><?php esc_html_e( 'Last run', 'oria' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: counts */
						__( '%1$s — %2$d source(s), %3$d found, %4$d added, %5$d updated, %6$d duplicate(s) skipped. AI: %7$s.', 'oria' ),
						(string) ( $report['time'] ?? '' ),
						(int) ( $report['sources'] ?? 0 ),
						(int) ( $report['found'] ?? 0 ),
						(int) ( $report['created'] ?? 0 ),
						(int) ( $report['updated'] ?? 0 ),
						(int) ( $report['dupes'] ?? 0 ),
						(string) ( $report['ai'] ?? '' )
					)
				);
				?>
			</p>
			<pre style="background:#fff;border:1px solid #dcdcde;padding:12px;max-width:46rem;overflow:auto;max-height:340px"><?php echo esc_html( implode( "\n", (array) ( $report['lines'] ?? array() ) ) ); ?></pre>
		<?php endif; ?>
	</div>
	<?php
}

function save(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nope.' );
	}
	check_admin_referer( 'oria_ingest_save' );
	$lines = sanitize_textarea_field( wp_unslash( (string) ( $_POST['watchlist'] ?? '' ) ) );
	update_option( Pipeline\OPT_WATCHLIST, $lines, false );
	wp_safe_redirect( admin_url( 'edit.php?post_type=event&page=oria-ingest' ) );
	exit;
}

function run_now(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nope.' );
	}
	check_admin_referer( 'oria_ingest_run' );
	Pipeline\run();
	wp_safe_redirect( admin_url( 'edit.php?post_type=event&page=oria-ingest' ) );
	exit;
}

/** Deliberate, per-event copy of the organiser's banner. */
function import_image(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nope.' );
	}
	$event = (int) ( $_POST['event'] ?? 0 );
	check_admin_referer( 'oria_ingest_image_' . $event );

	$url = (string) get_post_meta( $event, '_oria_image_url', true );
	if ( '' !== $url && 'event' === get_post_type( $event ) && ! has_post_thumbnail( $event ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = media_sideload_image( $url, $event, get_the_title( $event ), 'id' );
		if ( ! is_wp_error( $att ) ) {
			set_post_thumbnail( $event, (int) $att );
			update_post_meta( (int) $att, '_oria_image_source', $url );
		}
	}
	wp_safe_redirect( get_edit_post_link( $event, 'redirect' ) ?: admin_url( 'edit.php?post_type=event' ) );
	exit;
}

/** @param array<string, string> $cols */
function columns( array $cols ): array {
	$cols['oria_src'] = __( 'Source', 'oria' );
	return $cols;
}

function column( string $col, int $post_id ): void {
	if ( 'oria_src' !== $col ) {
		return;
	}
	$src = (string) get_post_meta( $post_id, '_oria_src', true );
	if ( '' === $src ) {
		echo '<span style="color:#20604C;font-weight:600">' . esc_html__( 'Member', 'oria' ) . '</span>';
		return;
	}
	echo esc_html( $src );
	$conf = (string) get_post_meta( $post_id, '_oria_confidence', true );
	if ( '' !== $conf ) {
		echo '<br><small style="color:#666">' . esc_html( sprintf( __( 'confidence %s', 'oria' ), $conf ) ) . '</small>';
	}
}

function provenance_box( \WP_Post $post ): void {
	if ( '' === (string) get_post_meta( $post->ID, '_oria_src', true ) ) {
		return;
	}
	add_meta_box(
		'oria-ingest-src',
		__( 'Aggregated event', 'oria' ),
		static function ( \WP_Post $p ): void {
			$url = (string) get_post_meta( $p->ID, '_oria_src_url', true );
			echo '<p style="margin-top:0">' . esc_html__( 'Found automatically. The source page stays the source of truth — check details there before publishing.', 'oria' ) . '</p>';
			if ( $url ) {
				echo '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open source page ↗', 'oria' ) . '</a></p>';
			}
			foreach ( array(
				__( 'Organiser', 'oria' )     => (string) get_post_meta( $p->ID, '_oria_organiser', true ),
				__( 'Discovered', 'oria' )    => (string) get_post_meta( $p->ID, '_oria_discovered', true ),
				__( 'Last verified', 'oria' ) => (string) get_post_meta( $p->ID, '_oria_verified', true ),
			) as $label => $value ) {
				if ( '' !== $value ) {
					echo '<p style="margin:.25em 0"><b>' . esc_html( $label ) . ':</b> ' . esc_html( $value ) . '</p>';
				}
			}

			// The organiser's banner, imported only on a deliberate click.
			$image = (string) get_post_meta( $p->ID, '_oria_image_url', true );
			if ( '' === $image ) {
				return;
			}
			if ( has_post_thumbnail( $p ) ) {
				echo '<p style="color:#666">' . esc_html__( 'Featured image set — the branded tile is replaced.', 'oria' ) . '</p>';
				return;
			}
			echo '<hr><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'oria_ingest_image_' . $p->ID );
			echo '<input type="hidden" name="action" value="oria_ingest_image">';
			echo '<input type="hidden" name="event" value="' . (int) $p->ID . '">';
			echo '<button class="button">' . esc_html__( 'Import image from source', 'oria' ) . '</button>';
			echo '<p style="color:#666;margin-bottom:0">' . esc_html__( 'Copies the organiser\'s banner to your media library and sets it as the featured image. Only do this where you\'re comfortable using their artwork — otherwise the branded Oria tile shows.', 'oria' ) . '</p>';
			echo '</form>';
		},
		'event',
		'side'
	);
}
