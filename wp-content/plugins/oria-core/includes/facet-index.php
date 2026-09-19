<?php
/**
 * Facet pages: an editor's say over which are indexed.
 *
 * A facet page -- /explore/perth/spa/steam-room/ -- is indexable by rule:
 * it needs FACET_MIN listings and to be the facet's canonical home. A count
 * alone can't tell a page people search for from a page nobody wants, so
 * this adds a per-URL override, set under Listings -> Facet pages:
 *
 *   Automatic    the rule decides (the default)
 *   Always index index it even under the floor (still needs 1+ listing,
 *                and still only on the canonical home -- never a copy)
 *   Never index  noindex, follow, and out of the sitemap, whatever the count
 *
 * The robots tag (PracticesIndex\robots) and the facet sitemap
 * (PracticesIndex\sitemap_entries) both read state(), so the page and the
 * sitemap can never disagree. Saving flushes the cached sitemap.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\FacetIndex;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION    = 'oria_facet_index';
const INVENTORY = 'oria_facet_inventory'; // every facet home, floor or not; written by the sitemap walk

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_facet_index', __NAMESPACE__ . '\save' );
}

/** A URL's path, the key overrides are stored under ("/explore/perth/spa/steam-room"). */
function key( string $url ): string {
	return untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
}

/** 'index', 'noindex' or '' (automatic) for a URL. */
function state( string $url ): string {
	$all = get_option( OPTION, array() );
	$v   = is_array( $all ) ? (string) ( $all[ key( $url ) ] ?? '' ) : '';
	return in_array( $v, array( 'index', 'noindex' ), true ) ? $v : '';
}

/* ------------------------------------------------------------------ admin */

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . PostTypes\LISTING,
		__( 'Facet pages', 'oria' ),
		__( 'Facet pages', 'oria' ),
		'manage_options',
		'oria-facet-index',
		__NAMESPACE__ . '\render'
	);
}

function render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$inv = get_transient( INVENTORY );
	if ( ! is_array( $inv ) || isset( $_GET['rebuild'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only rebuild of a cache
		// The inventory is written by the sitemap walk; run it now.
		delete_transient( \Oria\Core\PracticesIndex\SITEMAP_CACHE );
		\Oria\Core\PracticesIndex\sitemap_entries();
		$inv = get_transient( INVENTORY );
		$inv = is_array( $inv ) ? $inv : array();
	}
	$ov     = get_option( OPTION, array() );
	$ov     = is_array( $ov ) ? $ov : array();
	$show   = isset( $_GET['show'] ) ? sanitize_key( (string) $_GET['show'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$min    = \Oria\Core\PracticesIndex\FACET_MIN;

	$rows = array();
	foreach ( $inv as $r ) {
		$k      = key( (string) $r['loc'] );
		$o      = (string) ( $ov[ $k ] ?? '' );
		$auto   = (int) $r['n'] >= $min;
		$live   = 'noindex' === $o ? false : ( 'index' === $o ? (int) $r['n'] >= 1 : $auto );
		$r['k'] = $k;
		$r['o'] = $o;
		$r['auto'] = $auto;
		$r['live'] = $live;
		if ( ( 'indexed' === $show && ! $live ) || ( 'noindexed' === $show && $live ) || ( 'overridden' === $show && '' === $o ) ) {
			continue;
		}
		if ( '' !== $search && false === stripos( $k . ' ' . $r['label'] . ' ' . $r['practice'], $search ) ) {
			continue;
		}
		$rows[] = $r;
	}
	usort( $rows, static fn( $a, $b ) => array( $a['practice'], $b['n'] * -1 ) <=> array( $b['practice'], $a['n'] * -1 ) );

	$base = admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=oria-facet-index' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Facet pages', 'oria' ); ?></h1>
		<p style="max-width:62em">
			<?php
			printf(
				/* translators: %d: minimum listings */
				esc_html__( 'Every filtered page the directory can show on its own address -- a service, specialty or area within a category. By default a page is indexed when it has %d or more places and is that service\'s main page. Override it here: "Always index" keeps a page with real search demand in Google even with fewer places; "Never index" keeps it out of Google and the sitemap but still visible to visitors.', 'oria' ),
				(int) $min
			);
			?>
		</p>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved. The sitemap has been refreshed.', 'oria' ); ?></p></div>
		<?php endif; ?>
		<form method="get" style="margin:1em 0">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( PostTypes\LISTING ); ?>">
			<input type="hidden" name="page" value="oria-facet-index">
			<select name="show">
				<?php foreach ( array( 'all' => __( 'All pages', 'oria' ), 'indexed' => __( 'Indexed', 'oria' ), 'noindexed' => __( 'Not indexed', 'oria' ), 'overridden' => __( 'Overridden only', 'oria' ) ) as $v => $l ) : ?>
					<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $show, $v ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search pages', 'oria' ); ?>">
			<?php submit_button( __( 'Filter', 'oria' ), 'secondary', '', false ); ?>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'rebuild', 1, $base ) ); ?>"><?php esc_html_e( 'Recount', 'oria' ); ?></a>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_facet_index">
			<?php wp_nonce_field( 'oria_facet_index' ); ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Page', 'oria' ); ?></th>
					<th><?php esc_html_e( 'Category', 'oria' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Places', 'oria' ); ?></th>
					<th><?php esc_html_e( 'Automatic', 'oria' ); ?></th>
					<th><?php esc_html_e( 'Your setting', 'oria' ); ?></th>
					<th><?php esc_html_e( 'Result', 'oria' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( (string) $r['loc'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $r['label'] ); ?></a><br><code style="font-size:11px"><?php echo esc_html( $r['k'] ); ?>/</code></td>
						<td><?php echo esc_html( (string) $r['practice'] ); ?></td>
						<td style="text-align:right"><?php echo (int) $r['n']; ?></td>
						<td><?php echo $r['auto'] ? esc_html__( 'Index', 'oria' ) : esc_html( sprintf( /* translators: %d: minimum */ __( 'Noindex (under %d)', 'oria' ), $min ) ); ?></td>
						<td>
							<select name="ov[<?php echo esc_attr( $r['k'] ); ?>]">
								<option value="" <?php selected( $r['o'], '' ); ?>><?php esc_html_e( 'Automatic', 'oria' ); ?></option>
								<option value="index" <?php selected( $r['o'], 'index' ); ?>><?php esc_html_e( 'Always index', 'oria' ); ?></option>
								<option value="noindex" <?php selected( $r['o'], 'noindex' ); ?>><?php esc_html_e( 'Never index', 'oria' ); ?></option>
							</select>
						</td>
						<td><b style="color:<?php echo $r['live'] ? '#1a7a3f' : '#8a6d00'; ?>"><?php echo $r['live'] ? esc_html__( 'Indexed', 'oria' ) : esc_html__( 'Not indexed', 'oria' ); ?></b></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No pages match.', 'oria' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save settings', 'oria' ) ); ?>
		</form>
	</div>
	<?php
}

function save(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ) );
	}
	check_admin_referer( 'oria_facet_index' );
	$all  = get_option( OPTION, array() );
	$all  = is_array( $all ) ? $all : array();
	$post = isset( $_POST['ov'] ) && is_array( $_POST['ov'] ) ? wp_unslash( $_POST['ov'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each key and value sanitised below
	foreach ( $post as $k => $v ) {
		$k = '/' . trim( sanitize_text_field( (string) $k ), '/' );
		$v = sanitize_key( (string) $v );
		if ( in_array( $v, array( 'index', 'noindex' ), true ) ) {
			$all[ $k ] = $v;
		} else {
			unset( $all[ $k ] ); // Automatic
		}
	}
	update_option( OPTION, $all, false );
	// The sitemap reads the setting when it is built: rebuild it.
	delete_transient( \Oria\Core\PracticesIndex\SITEMAP_CACHE );
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=oria-facet-index&saved=1' ) );
	exit;
}
