<?php
/**
 * Work in Wellness: the admin side (brief section 83).
 *
 * One "Work in Wellness" menu holding Jobs, Shifts, Work profiles, the three
 * vocabularies and the report queue. Moderation is WordPress's own: a
 * pending post is approved by publishing it (Notify\went_live() then emails
 * the employer and trusts the account).
 *
 * The "Oria controls" box carries what only staff may set: the linked
 * business listing, featured until, external-job source, whether the
 * account is trusted -- and on a profile, the verification badges and the
 * private details the practitioner gave (visible here, never on the site).
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work\Admin;

use Oria\Core\Work;
use Oria\Core\Work\Store;
use const Oria\Core\Work\JOB;
use const Oria\Core\Work\SHIFT;
use const Oria\Core\Work\PRO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu', 9 );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\boxes' );
	add_action( 'save_post', __NAMESPACE__ . '\save', 20, 2 );
	foreach ( array( JOB, SHIFT, PRO ) as $t ) {
		add_filter( "manage_{$t}_posts_columns", __NAMESPACE__ . '\columns' );
		add_action( "manage_{$t}_posts_custom_column", __NAMESPACE__ . '\column', 10, 2 );
	}
	add_action( 'admin_post_oria_work_report_decide', __NAMESPACE__ . '\decide_report' );
}

function menu(): void {
	$pending = (int) wp_count_posts( JOB )->pending + (int) wp_count_posts( SHIFT )->pending;
	add_menu_page(
		__( 'Work in Wellness', 'oria' ),
		__( 'Work in Wellness', 'oria' ) . ( $pending ? ' <span class="awaiting-mod">' . $pending . '</span>' : '' ),
		'edit_posts',
		'oria-work',
		'__return_null',
		'dashicons-businessperson',
		21
	);
	add_submenu_page( 'oria-work', __( 'Professions', 'oria' ), __( 'Professions', 'oria' ), 'manage_categories', 'edit-tags.php?taxonomy=' . Work\PROFESSION );
	add_submenu_page( 'oria-work', __( 'Skills', 'oria' ), __( 'Skills', 'oria' ), 'manage_categories', 'edit-tags.php?taxonomy=' . Work\SKILL );
	add_submenu_page( 'oria-work', __( 'Employment types', 'oria' ), __( 'Employment types', 'oria' ), 'manage_categories', 'edit-tags.php?taxonomy=' . Work\EMPLOYMENT );
	$open = count( Store\reports_list( 'new' ) );
	add_submenu_page( 'oria-work', __( 'Reports', 'oria' ), __( 'Reports', 'oria' ) . ( $open ? ' <span class="awaiting-mod">' . $open . '</span>' : '' ), 'manage_options', 'oria-work-reports', __NAMESPACE__ . '\reports_page' );
	remove_submenu_page( 'oria-work', 'oria-work' );
}

/* ---------------------------------------------------------------- columns */

function columns( array $c ): array {
	$out = array();
	foreach ( $c as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'title' === $k ) {
			$out['wk_state'] = __( 'State', 'oria' );
			$out['wk_where'] = __( 'Employer / place', 'oria' );
			$out['wk_apps']  = __( 'Responses', 'oria' );
		}
	}
	unset( $out['date'] );
	$out['date'] = $c['date'] ?? __( 'Date', 'oria' );
	return $out;
}

function column( string $col, int $id ): void {
	$type = get_post_type( $id );
	switch ( $col ) {
		case 'wk_state':
			$bits = array();
			if ( PRO === $type ) {
				$c      = Work\completeness( $id );
				$bits[] = $c['score'] . '% complete';
				$bits[] = Work\visibility( $id );
				if ( $b = Work\badges( $id ) ) {
					$bits[] = count( $b ) . ' verified';
				}
			} else {
				$why    = Work\closed_reason( $id );
				$bits[] = '' === $why ? 'open' : 'closed (' . $why . ')';
				if ( Work\is_featured( $id ) ) {
					$bits[] = '★ featured';
				} elseif ( Work\meta( $id, 'feature_requested' ) ) {
					$bits[] = '<b style="color:#996800">★ feature requested</b>';
				}
				if ( SHIFT === $type ) {
					$bits[] = Work\URGENCY[ Work\urgency( $id ) ] ?? '';
				}
				if ( Work\meta( $id, 'external' ) ) {
					$bits[] = 'external';
				}
				if ( '1' === (string) Work\meta( $id, 'flagged' ) ) {
					$bits[] = '<b style="color:#b32d2e">flagged words</b>';
				}
			}
			if ( $r = Store\open_reports_for( $id ) ) {
				$bits[] = '<b style="color:#b32d2e">' . (int) $r . ' report(s)</b>';
			}
			echo wp_kses_post( implode( '<br>', array_filter( $bits ) ) );
			break;
		case 'wk_where':
			echo esc_html( implode( ' · ', array_filter( array( PRO === $type ? Work\profession_name( $id ) : Work\employer_name( $id ), Work\place_label( $id ), SHIFT === $type ? Work\shift_when( $id ) : '' ) ) ) );
			break;
		case 'wk_apps':
			if ( PRO === $type ) {
				echo '—';
				break;
			}
			$c = Store\counts( array( $id ) )[ $id ] ?? array( 'n' => 0, 'fresh' => 0 );
			echo esc_html( sprintf( '%d (%d new)', $c['n'], $c['fresh'] ) );
			break;
	}
}

/* ---------------------------------------------------------- oria controls */

function boxes(): void {
	add_meta_box( 'oria_work_admin', __( 'Oria controls', 'oria' ), __NAMESPACE__ . '\box', array( JOB, SHIFT ), 'side', 'high' );
	add_meta_box( 'oria_work_pro', __( 'Verification & private details', 'oria' ), __NAMESPACE__ . '\pro_box', PRO, 'side', 'high' );
	add_meta_box( 'oria_work_details', __( 'Listing details (as submitted)', 'oria' ), __NAMESPACE__ . '\details_box', array( JOB, SHIFT, PRO ), 'normal' );
}

function box( \WP_Post $post ): void {
	wp_nonce_field( 'oria_work_admin', '_wk_admin' );
	$id      = (int) $post->ID;
	$until   = (int) Work\meta( $id, 'featured_until', 0 );
	$author  = (int) $post->post_author;
	?>
	<p><label><?php esc_html_e( 'Business listing ID', 'oria' ); ?><br>
		<input type="number" name="wk_listing" value="<?php echo esc_attr( (string) Work\meta( $id, 'listing' ) ); ?>" class="widefat"></label>
		<small><?php echo esc_html( Work\employer_name( $id ) ?: __( 'Not linked — the typed employer name is used.', 'oria' ) ); ?></small></p>
	<p><label><?php esc_html_e( 'Featured', 'oria' ); ?><br>
		<select name="wk_feature" class="widefat">
			<option value=""><?php echo esc_html( $until > time() ? sprintf( __( 'Featured until %s', 'oria' ), wp_date( 'j M Y', $until ) ) : __( 'Not featured', 'oria' ) ); ?></option>
			<option value="7"><?php esc_html_e( 'Feature for 7 days', 'oria' ); ?></option>
			<option value="14"><?php esc_html_e( 'Feature for 14 days', 'oria' ); ?></option>
			<option value="30"><?php esc_html_e( 'Feature for 30 days', 'oria' ); ?></option>
			<option value="off"><?php esc_html_e( 'Stop featuring', 'oria' ); ?></option>
		</select></label></p>
	<?php if ( JOB === $post->post_type ) : ?>
		<p><label><input type="checkbox" name="wk_external" value="1" <?php checked( (bool) Work\meta( $id, 'external' ) ); ?>> <?php esc_html_e( 'External job (added by Oria)', 'oria' ); ?></label><br>
			<small><?php esc_html_e( 'A short summary in our own words that links to the original. No JobPosting markup, and a "Claim this job" note is shown.', 'oria' ); ?></small></p>
		<p><label><?php esc_html_e( 'Closes', 'oria' ); ?><br>
			<input type="date" name="wk_expires" value="<?php echo esc_attr( ( $e = (int) Work\meta( $id, 'expires', 0 ) ) ? wp_date( 'Y-m-d', $e ) : '' ); ?>" class="widefat"></label></p>
	<?php endif; ?>
	<?php if ( $author && ! user_can( $author, 'manage_options' ) ) : ?>
		<p><label><input type="checkbox" name="wk_trusted" value="1" <?php checked( (bool) get_user_meta( $author, \Oria\Core\Work\Forms\TRUSTED, true ) ); ?>> <?php esc_html_e( 'Trusted employer (posts go live without review)', 'oria' ); ?></label></p>
	<?php endif; ?>
	<?php
}

function pro_box( \WP_Post $post ): void {
	wp_nonce_field( 'oria_work_admin', '_wk_admin' );
	$id  = (int) $post->ID;
	$set = (array) Work\meta( $id, 'verified', array() );
	echo '<p><strong>' . esc_html__( 'Badges shown on the profile', 'oria' ) . '</strong><br><small>' . esc_html__( 'Tick only after you have seen the document. The document itself is never shown.', 'oria' ) . '</small></p>';
	foreach ( Work\VERIFICATIONS as $k => $label ) {
		printf( '<label style="display:block"><input type="checkbox" name="wk_verified[]" value="%s" %s> %s</label>', esc_attr( $k ), checked( in_array( $k, $set, true ), true, false ), esc_html( $label ) );
	}
	$priv = array(
		'ABN'                => (string) Work\meta( $id, 'abn' ),
		'Insurance'          => Work\meta( $id, 'insurance' ) ? 'says yes' : 'not stated',
		'WWCC'               => Work\meta( $id, 'wwcc' ) ? 'says yes' : 'not stated',
		'First Aid'          => Work\meta( $id, 'first_aid' ) ? 'says yes' : 'not stated',
		'CPR'                => Work\meta( $id, 'cpr' ) ? 'says yes' : 'not stated',
		'Registrations'      => (string) Work\meta( $id, 'registrations' ),
		'Email'              => (string) ( get_userdata( (int) $post->post_author )->user_email ?? '' ),
	);
	echo '<hr><p><strong>' . esc_html__( 'Private (never on the site)', 'oria' ) . '</strong></p><table class="widefat striped" style="font-size:12px">';
	foreach ( $priv as $k => $v ) {
		echo '<tr><td>' . esc_html( $k ) . '</td><td>' . esc_html( '' === $v ? '—' : $v ) . '</td></tr>';
	}
	echo '</table>';
}

/** Read-only view of every field, so staff can moderate without opening the front end. */
function details_box( \WP_Post $post ): void {
	$rows = array();
	foreach ( get_post_meta( $post->ID ) as $k => $v ) {
		if ( 0 !== strpos( $k, '_wk_' ) || in_array( $k, array( '_wk_abn', '_wk_pay_rank', '_wk_urgent_sent' ), true ) ) {
			continue;
		}
		$val    = maybe_unserialize( $v[0] );
		$rows[] = array( substr( $k, 4 ), is_array( $val ) ? implode( ', ', array_map( 'strval', $val ) ) : (string) $val );
	}
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'Nothing submitted yet.', 'oria' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped">';
	foreach ( $rows as $r ) {
		if ( in_array( $r[0], array( 'expires', 'featured_until', 'closed_at' ), true ) && ctype_digit( $r[1] ) ) {
			$r[1] = wp_date( 'j M Y g:ia', (int) $r[1] );
		}
		echo '<tr><th style="width:30%">' . esc_html( $r[0] ) . '</th><td>' . esc_html( $r[1] ) . '</td></tr>';
	}
	echo '</table>';
}

function save( int $id, \WP_Post $post ): void {
	if ( ! isset( $_POST['_wk_admin'] ) || ! wp_verify_nonce( (string) $_POST['_wk_admin'], 'oria_work_admin' ) || ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	if ( PRO === $post->post_type ) {
		Work\set( $id, 'verified', array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['wk_verified'] ?? array() ) ), array_keys( Work\VERIFICATIONS ) ) ) );
		return;
	}
	$listing = (int) ( $_POST['wk_listing'] ?? 0 );
	Work\set( $id, 'listing', ( $listing && 'listing' === get_post_type( $listing ) ) ? $listing : 0 );
	$f = sanitize_key( (string) ( $_POST['wk_feature'] ?? '' ) );
	if ( 'off' === $f ) {
		Work\set( $id, 'featured_until', '' );
	} elseif ( in_array( $f, array( '7', '14', '30' ), true ) ) {
		Work\set( $id, 'featured_until', time() + (int) $f * DAY_IN_SECONDS );
		Work\set( $id, 'feature_requested', '' );
	}
	if ( JOB === $post->post_type ) {
		Work\set( $id, 'external', empty( $_POST['wk_external'] ) ? '' : '1' );
		$d = sanitize_text_field( (string) ( $_POST['wk_expires'] ?? '' ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
			$ts = (int) ( date_create_immutable( $d . ' 23:59', wp_timezone() ) ?: new \DateTimeImmutable() )->getTimestamp();
			Work\set( $id, 'expires', $ts );
			if ( $ts > time() && 'expired' === Work\meta( $id, 'closed' ) ) {
				Work\set( $id, 'closed', '' );
			}
		}
	}
	$author = (int) $post->post_author;
	if ( $author && ! user_can( $author, 'manage_options' ) ) {
		update_user_meta( $author, \Oria\Core\Work\Forms\TRUSTED, empty( $_POST['wk_trusted'] ) ? 0 : 1 );
	}
}

/* ----------------------------------------------------------------- reports */

function reports_page(): void {
	$rows = Store\reports_list( 'new' );
	echo '<div class="wrap"><h1>' . esc_html__( 'Reported jobs, shifts and profiles', 'oria' ) . '</h1>';
	echo '<p>' . esc_html__( 'Nothing is hidden automatically. Dismiss a report, or unpublish what it points at.', 'oria' ) . '</p>';
	if ( ! $rows ) {
		echo '<p><em>' . esc_html__( 'No open reports.', 'oria' ) . '</em></p></div>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Listing', 'oria' ) . '</th><th>' . esc_html__( 'Reason', 'oria' ) . '</th><th>' . esc_html__( 'Note', 'oria' ) . '</th><th>' . esc_html__( 'When', 'oria' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$pid = (int) $r['post_id'];
		$act = static function ( string $do, string $label ) use ( $r ): string {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=oria_work_report_decide&report=' . (int) $r['id'] . '&do=' . $do ), 'oria_work_report_' . (int) $r['id'] );
			return '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
		};
		echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $pid ) ) . '">' . esc_html( get_the_title( $pid ) ?: '#' . $pid ) . '</a> <small>(' . esc_html( (string) get_post_type( $pid ) ) . ')</small></td>';
		echo '<td>' . esc_html( Work\REPORT_REASONS[ $r['reason'] ] ?? $r['reason'] ) . '</td><td>' . esc_html( (string) $r['note'] ) . '</td><td>' . esc_html( mysql2date( 'j M, g:ia', (string) $r['created_at'] ) ) . '</td>';
		echo '<td>' . $act( 'dismiss', __( 'Dismiss', 'oria' ) ) . $act( 'unpublish', __( 'Unpublish', 'oria' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table></div>';
}

function decide_report(): void {
	$id = (int) ( $_GET['report'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	check_admin_referer( 'oria_work_report_' . $id );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	$do = sanitize_key( (string) ( $_GET['do'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	global $wpdb;
	$post = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . Store\reports() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	if ( 'unpublish' === $do && $post ) {
		wp_update_post( array( 'ID' => $post, 'post_status' => 'draft' ) );
		// Every open report on the same listing is dealt with by the same act.
		$wpdb->update( Store\reports(), array( 'status' => 'actioned' ), array( 'post_id' => $post, 'status' => 'new' ) );
	} else {
		Store\report_status( $id, 'dismissed' );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=oria-work-reports' ) );
	exit;
}
