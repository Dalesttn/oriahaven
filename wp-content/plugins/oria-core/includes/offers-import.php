<?php
/**
 * Offers import: data/offers.json onto the listings, from the backend.
 *
 * The monthly offers task (run by Claude on Dale's machine) commits a new
 * offers.json to GitHub. This file closes the loop on the server:
 *
 *   import_run( $apply )   the one import rule, shared with
 *                          tools/apply-offers.php -- claimed listings and
 *                          owner-typed offers are never touched, removed
 *                          rows clear the offer, ended rows are skipped
 *   import_status()        what is waiting: a newer offers.json on GitHub
 *                          (needs `git pull`), and/or changes in this
 *                          site's copy that are not on the listings yet
 *   Listings > Offers import   the status, a preview of every change, and
 *                          an Import button (writes, then purges the cache)
 *   import_check()         twice a day: emails the admin address once per
 *                          new file, saying it is ready
 *
 * The file is never fetched into place from GitHub: code and data reach the
 * server the one way, `git pull`, so the working tree stays clean.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Offers;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const IMPORT_SLUG     = 'oria-offers-import';
const IMPORT_CRON     = 'oria_offers_import_check';
/** Last import: { at, hash, counts }. */
const IMPORT_DONE     = 'oria_offers_imported';
/** The state key last emailed about, so each new file is mailed once. */
const IMPORT_NOTIFIED = 'oria_offers_import_notified';
/** Cached status for the admin notice: { pull, pending, at }. */
const IMPORT_STATE    = 'oria_offers_import_state';
/** The same file on GitHub (the repo is public). Filter `oria_offers_github_raw` to change. */
const GITHUB_RAW      = 'https://raw.githubusercontent.com/Dalesttn/public/main/wp-content/plugins/oria-core/data/offers.json';

function import_bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\import_schedule' );
	add_action( IMPORT_CRON, __NAMESPACE__ . '\import_check' );
	add_action( 'admin_menu', __NAMESPACE__ . '\import_menu' );
	add_action( 'admin_post_oria_offers_import', __NAMESPACE__ . '\import_post' );
	add_action( 'admin_notices', __NAMESPACE__ . '\import_notice' );
}
import_bootstrap();

function import_file(): string {
	return ORIA_CORE_DIR . 'data/offers.json';
}

/** Hash of the file's content with line endings folded, so a CRLF checkout matches GitHub. */
function import_hash( string $raw ): string {
	return '' === $raw ? '' : sha1( str_replace( "\r\n", "\n", $raw ) );
}

/**
 * Run the import over this site's offers.json. Dry run unless $apply.
 *
 * @return array{ok:bool, error?:string, rows:list<array{do:string,id:int,name:string,note:string}>, n:array<string,int>}
 */
function import_run( bool $apply ): array {
	$n    = array( 'written' => 0, 'unchanged' => 0, 'cleared' => 0, 'skipped' => 0 );
	$raw  = is_readable( import_file() ) ? (string) file_get_contents( import_file() ) : '';
	$data = '' !== $raw ? json_decode( $raw, true ) : null;
	if ( ! is_array( $data['offers'] ?? null ) ) {
		return array( 'ok' => false, 'error' => 'Cannot read ' . import_file(), 'rows' => array(), 'n' => $n );
	}
	if ( ! function_exists( 'update_field' ) ) {
		return array( 'ok' => false, 'error' => 'ACF is not available.', 'rows' => array(), 'n' => $n );
	}

	$rows  = array();
	$today = current_time( 'Y-m-d' );
	$add   = static function ( string $do, int $id, string $name, string $note = '' ) use ( &$rows ): void {
		$rows[] = array( 'do' => $do, 'id' => $id, 'name' => $name, 'note' => $note );
	};

	foreach ( $data['offers'] as $o ) {
		$slug = (string) ( $o['listing'] ?? '' );
		$post = '' !== $slug ? get_page_by_path( $slug, OBJECT, PostTypes\LISTING ) : null;
		if ( ! $post instanceof \WP_Post ) {
			$add( 'NOT FOUND', 0, $slug );
			++$n['skipped'];
			continue;
		}
		$id      = $post->ID;
		$name    = html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' );
		$status  = (string) get_post_meta( $id, 'claim_status', true );
		$claimed = 'unclaimed' !== $status || (int) get_post_meta( $id, 'claimed_by', true );
		$have    = array();
		foreach ( FIELDS as $f ) {
			$have[ $f ] = (string) get_field( $f, $id );
		}
		$have_extra = (string) get_post_meta( $id, META, true );

		if ( $claimed ) {
			$add( 'SKIP', $id, $name, 'claimed; the owner runs this listing’s offer' );
			++$n['skipped'];
			continue;
		}
		if ( '' !== $have['offer_title'] && '' === $have['offer_source'] ) {
			$add( 'SKIP', $id, $name, 'an owner-typed offer is already on it' );
			++$n['skipped'];
			continue;
		}

		if ( ! empty( $o['removed'] ) ) {
			if ( '' === $have['offer_title'] ) {
				$add( '=', $id, $name, 'already clear' );
				++$n['unchanged'];
				continue;
			}
			$add( 'CLEAR', $id, $name, '“' . $have['offer_title'] . '”' );
			if ( $apply ) {
				foreach ( FIELDS as $f ) {
					update_field( $f, '', $id );
				}
				delete_post_meta( $id, META );
				do_action( 'litespeed_purge_post', $id );
			}
			++$n['cleared'];
			continue;
		}

		$want = array(
			'offer_title'   => trim( (string) ( $o['title'] ?? '' ) ),
			'offer_text'    => trim( (string) ( $o['details'] ?? '' ) ),
			'offer_until'   => (string) ( $o['valid_until'] ?? '' ),
			'offer_source'  => esc_url_raw( (string) ( $o['source_url'] ?? '' ) ),
			'offer_checked' => (string) ( $o['checked'] ?? '' ),
		);
		if ( '' === $want['offer_title'] || '' === $want['offer_source'] ) {
			$add( 'SKIP', $id, $name, 'row needs a title and a source_url' );
			++$n['skipped'];
			continue;
		}
		if ( '' !== $want['offer_until'] && $want['offer_until'] < $today ) {
			$add( 'SKIP', $id, $name, 'ended ' . $want['offer_until'] . '; mark the row removed' );
			++$n['skipped'];
			continue;
		}
		/*
		 * The structured part the listing card shows -- kind, price, basis,
		 * inclusions, eligibility, terms -- as one JSON meta beside the ACF
		 * fields. Only what the row states; nothing is derived from the prose.
		 */
		$extra      = extra_from( $o );
		$extra_json = $extra ? (string) wp_json_encode( $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '';

		// ACF stores dates as Ymd; compare on the normalised form.
		$same = $extra_json === $have_extra;
		foreach ( $want as $f => $v ) {
			if ( str_replace( '-', '', $have[ $f ] ) !== str_replace( '-', '', $v ) ) {
				$same = false;
			}
		}
		if ( $same ) {
			$add( '=', $id, $name );
			++$n['unchanged'];
			continue;
		}
		$add( 'WRITE', $id, $name, $want['offer_title'] . ( '' !== $want['offer_until'] ? " (until {$want['offer_until']})" : '' ) );
		if ( $apply ) {
			foreach ( $want as $f => $v ) {
				update_field( $f, $v, $id );
			}
			if ( '' !== $extra_json ) {
				update_post_meta( $id, META, $extra_json );
			} else {
				delete_post_meta( $id, META );
			}
			do_action( 'litespeed_purge_post', $id );
		}
		++$n['written'];
	}

	if ( $apply ) {
		update_option( IMPORT_DONE, array( 'at' => current_time( 'mysql' ), 'hash' => import_hash( $raw ), 'n' => $n ), false );
		if ( $n['written'] || $n['cleared'] ) {
			// Category pages and the /offers/ hub carry offer cards too.
			do_action( 'litespeed_purge_all' );
		}
		delete_option( IMPORT_STATE );
	}
	return array( 'ok' => true, 'rows' => $rows, 'n' => $n );
}

/**
 * What is waiting, checked now.
 *
 * pull     true when GitHub's offers.json differs from this site's copy
 *          (null when GitHub could not be reached)
 * pending  rows a real import would write or clear, from this site's copy
 *
 * @return array{pull:?bool, pending:int, written:int, cleared:int, disk:string, github:string}
 */
function import_status(): array {
	$disk = is_readable( import_file() ) ? import_hash( (string) file_get_contents( import_file() ) ) : '';
	$url  = (string) apply_filters( 'oria_offers_github_raw', GITHUB_RAW );
	$res  = wp_remote_get( add_query_arg( 't', (string) time(), $url ), array( 'timeout' => 10 ) );
	$gh   = ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ? import_hash( (string) wp_remote_retrieve_body( $res ) ) : '';
	$dry  = import_run( false );
	$out  = array(
		'pull'    => '' === $gh ? null : $gh !== $disk,
		'pending' => $dry['n']['written'] + $dry['n']['cleared'],
		'written' => $dry['n']['written'],
		'cleared' => $dry['n']['cleared'],
		'disk'    => $disk,
		'github'  => $gh,
	);
	update_option( IMPORT_STATE, array( 'pull' => $out['pull'], 'pending' => $out['pending'], 'at' => time() ), false );
	return $out;
}

/* ---------------------------------------------------------------- email */

function import_schedule(): void {
	if ( ! wp_next_scheduled( IMPORT_CRON ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', IMPORT_CRON );
	}
}

/**
 * Twice a day: if a new offers.json is on GitHub, or this site's copy has
 * changes not yet on the listings, email the admin address -- once per file.
 */
function import_check(): void {
	$s = import_status();
	if ( ! $s['pull'] && ! $s['pending'] ) {
		return;
	}
	// Keyed on the file's content: after `git pull` the site's copy has the
	// hash GitHub had, so the same file is not mailed about twice.
	$key = $s['pull'] ? $s['github'] : $s['disk'];
	if ( get_option( IMPORT_NOTIFIED ) === $key ) {
		return;
	}
	$to = (string) get_option( 'admin_email' );
	if ( '' === $to ) {
		return;
	}
	$page = admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=' . IMPORT_SLUG );
	if ( $s['pull'] ) {
		$subject = __( '[Oria] New offers are ready to import', 'oria' );
		$body    = "A new offers file is on GitHub and ready to import.\n\n"
			. "1. On the server, in domains/oriahaven.com.au/public_html, run:\n\n    git pull\n\n"
			. "2. Open Listings > Offers import, check the preview, and press Import:\n\n    {$page}\n\n"
			. "The import skips claimed listings and owner-typed offers, and clears the page cache when it is done.\n";
	} else {
		$subject = __( '[Oria] Offers are waiting to be imported', 'oria' );
		$body    = sprintf( "This site's offers file has %d offer(s) to add or update and %d to remove that are not on the listings yet.\n\n", $s['written'], $s['cleared'] )
			. "Open Listings > Offers import, check the preview, and press Import:\n\n    {$page}\n";
	}
	if ( wp_mail( $to, $subject, $body ) ) {
		update_option( IMPORT_NOTIFIED, $key, false );
	}
}

/* ---------------------------------------------------------------- admin */

function import_menu(): void {
	add_submenu_page( 'edit.php?post_type=' . PostTypes\LISTING, __( 'Offers import', 'oria' ), __( 'Offers import', 'oria' ), 'manage_options', IMPORT_SLUG, __NAMESPACE__ . '\import_page' );
}

/** A one-line nudge on the dashboard and listing screens, from the last check (no network call here). */
function import_notice(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ( 'dashboard' !== $screen->id && PostTypes\LISTING !== $screen->post_type ) || str_contains( (string) $screen->id, IMPORT_SLUG ) ) {
		return;
	}
	$st = get_option( IMPORT_STATE );
	if ( ! is_array( $st ) || ( empty( $st['pull'] ) && empty( $st['pending'] ) ) ) {
		return;
	}
	$msg = ! empty( $st['pull'] ) ? __( 'A new offers file is on GitHub: run git pull on the server, then import it.', 'oria' ) : __( 'Offers are waiting to be imported.', 'oria' );
	printf(
		'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
		esc_html( $msg ),
		esc_url( admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=' . IMPORT_SLUG ) ),
		esc_html__( 'Offers import', 'oria' )
	);
}

function import_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s    = import_status();
	$dry  = import_run( false );
	$last = get_option( IMPORT_DONE );
	$done = isset( $_GET['imported'] ) ? get_transient( 'oria_offers_import_result_' . get_current_user_id() ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	echo '<div class="wrap"><h1>' . esc_html__( 'Offers import', 'oria' ) . '</h1>';
	echo '<p style="max-width:62rem">' . esc_html__( 'Advertised offers come from data/offers.json, which the monthly offers check updates on GitHub. Pull it onto the server with git, then import it here. Claimed listings and owner-typed offers are never touched; offers past their end date are skipped.', 'oria' ) . '</p>';

	if ( is_array( $done ) ) {
		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( sprintf( __( 'Imported: %1$d written, %2$d cleared, %3$d unchanged, %4$d skipped. The page cache has been cleared.', 'oria' ), $done['written'], $done['cleared'], $done['unchanged'], $done['skipped'] ) ) );
	}

	echo '<h2>' . esc_html__( 'GitHub', 'oria' ) . '</h2><p>';
	if ( null === $s['pull'] ) {
		esc_html_e( 'Could not reach GitHub just now, so this cannot say whether a newer file is waiting.', 'oria' );
	} elseif ( $s['pull'] ) {
		echo '<strong>' . esc_html__( 'A newer offers file is on GitHub.', 'oria' ) . '</strong> ' . esc_html__( 'Run this on the server first, then reload this page:', 'oria' ) . '</p><pre style="background:#fff;padding:.6em 1em;display:inline-block">cd domains/oriahaven.com.au/public_html &amp;&amp; git pull</pre><p>';
	} else {
		esc_html_e( 'This site has the latest offers file.', 'oria' );
	}
	echo '</p>';

	echo '<h2>' . esc_html__( 'On this site', 'oria' ) . '</h2>';
	if ( ! $dry['ok'] ) {
		echo '<p>' . esc_html( (string) $dry['error'] ) . '</p></div>';
		return;
	}
	$n = $dry['n'];
	echo '<p>' . esc_html( sprintf( __( '%1$d to add or update, %2$d to remove, %3$d unchanged, %4$d skipped.', 'oria' ), $n['written'], $n['cleared'], $n['unchanged'], $n['skipped'] ) ) . '</p>';

	$show = array_filter( $dry['rows'], static fn( array $r ): bool => '=' !== $r['do'] );
	if ( $show ) {
		$label = array(
			'WRITE'     => __( 'Add / update', 'oria' ),
			'CLEAR'     => __( 'Remove', 'oria' ),
			'SKIP'      => __( 'Skip', 'oria' ),
			'NOT FOUND' => __( 'Not found', 'oria' ),
		);
		echo '<table class="widefat striped" style="max-width:62rem"><thead><tr><th>' . esc_html__( 'Change', 'oria' ) . '</th><th>' . esc_html__( 'Listing', 'oria' ) . '</th><th>' . esc_html__( 'Offer / reason', 'oria' ) . '</th></tr></thead><tbody>';
		foreach ( $show as $r ) {
			$name = $r['id'] ? '<a href="' . esc_url( (string) get_permalink( $r['id'] ) ) . '">' . esc_html( $r['name'] ) . '</a>' : esc_html( $r['name'] );
			echo '<tr><td>' . esc_html( $label[ $r['do'] ] ?? $r['do'] ) . '</td><td>' . $name . '</td><td>' . esc_html( $r['note'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		}
		echo '</tbody></table>';
	}

	if ( $s['pending'] ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1.2em">';
		echo '<input type="hidden" name="action" value="oria_offers_import">';
		wp_nonce_field( 'oria_offers_import' );
		submit_button( sprintf( __( 'Import %d change(s)', 'oria' ), $s['pending'] ), 'primary', 'submit', false );
		echo '</form>';
	} else {
		echo '<p><strong>' . esc_html__( 'Nothing to import: every offer in this site’s file is already on its listing.', 'oria' ) . '</strong></p>';
	}

	if ( is_array( $last ) && ! empty( $last['at'] ) ) {
		$ln = (array) ( $last['n'] ?? array() );
		echo '<p style="color:#50575e;margin-top:2em">' . esc_html( sprintf( __( 'Last import: %1$s (%2$d written, %3$d cleared).', 'oria' ), wp_date( 'j M Y, g:i a', (int) strtotime( (string) $last['at'] . ' ' . wp_timezone_string() ) ), (int) ( $ln['written'] ?? 0 ), (int) ( $ln['cleared'] ?? 0 ) ) ) . '</p>';
	}
	echo '</div>';
}

function import_post(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ), 403 );
	}
	check_admin_referer( 'oria_offers_import' );
	$r = import_run( true );
	set_transient( 'oria_offers_import_result_' . get_current_user_id(), $r['n'], 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=' . IMPORT_SLUG . '&imported=1' ) );
	exit;
}
