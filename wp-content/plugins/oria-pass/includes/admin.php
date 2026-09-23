<?php
/**
 * The Oria Pass admin: who is waiting, and the switches that decide what
 * the page offers them.
 *
 * Deliberately two screens. The brief lists eight submenus, and seven of
 * them describe things that do not exist yet -- an empty Bookings screen
 * teaches an editor that the menu lies.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Admin;

use Oria\Pass\Reports;
use Oria\Pass\Settings;
use Oria\Pass\Waitlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CAP = 'manage_options';

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_pass_export', __NAMESPACE__ . '\export' );
	add_action( 'admin_post_oria_pass_payouts_csv', __NAMESPACE__ . '\payouts_csv' );
}

function menu(): void {
	add_menu_page(
		__( 'Oria Pass', 'oria' ),
		__( 'Oria Pass', 'oria' ),
		CAP,
		'oria-pass',
		__NAMESPACE__ . '\screen_overview',
		'dashicons-tickets-alt',
		26
	);
	add_submenu_page( 'oria-pass', __( 'Overview', 'oria' ), __( 'Overview', 'oria' ), CAP, 'oria-pass', __NAMESPACE__ . '\screen_overview' );
	add_submenu_page( 'oria-pass', __( 'Payouts', 'oria' ), __( 'Payouts', 'oria' ), CAP, 'oria-pass-payouts', __NAMESPACE__ . '\screen_payouts' );
	add_submenu_page( 'oria-pass', __( 'Waitlist', 'oria' ), __( 'Waitlist', 'oria' ), CAP, 'oria-pass-waitlist', __NAMESPACE__ . '\screen_waitlist' );
	add_submenu_page( 'oria-pass', __( 'Settings', 'oria' ), __( 'Settings', 'oria' ), CAP, 'oria-pass-settings', __NAMESPACE__ . '\screen_settings' );
}

function screen_waitlist(): void {
	if ( ! current_user_can( CAP ) ) {
		return;
	}

	$kind = isset( $_GET['kind'] ) && 'partner' === $_GET['kind'] ? 'partner' : 'member'; // phpcs:ignore WordPress.Security.NonceVerification
	$rows = Waitlist\recent( $kind );

	echo '<div class="wrap">';
	printf( '<h1>%s</h1>', esc_html__( 'Oria Pass waitlist', 'oria' ) );

	printf(
		'<p>%s &nbsp;|&nbsp; %s</p>',
		sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( admin_url( 'admin.php?page=oria-pass-waitlist&kind=member' ) ),
			'member' === $kind ? ' class="current"' : '',
			esc_html( sprintf( /* translators: %d: count */ __( 'People (%d)', 'oria' ), Waitlist\count_of( 'member' ) ) )
		),
		sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( admin_url( 'admin.php?page=oria-pass-waitlist&kind=partner' ) ),
			'partner' === $kind ? ' class="current"' : '',
			esc_html( sprintf( /* translators: %d: count */ __( 'Businesses (%d)', 'oria' ), Waitlist\count_of( 'partner' ) ) )
		)
	);

	printf(
		'<p><a class="button" href="%s">%s</a></p>',
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oria_pass_export&kind=' . $kind ), 'oria_pass_export' ) ),
		esc_html__( 'Export CSV', 'oria' )
	);

	if ( ! $rows ) {
		printf( '<p>%s</p></div>', esc_html__( 'Nobody yet.', 'oria' ) );
		return;
	}

	echo '<table class="widefat striped"><thead><tr>';
	foreach ( array( __( 'When', 'oria' ), __( 'Name', 'oria' ), __( 'Email', 'oria' ), 'partner' === $kind ? __( 'Business', 'oria' ) : __( 'Suburb', 'oria' ), __( 'Interested in', 'oria' ), __( 'Note', 'oria' ) ) as $h ) {
		printf( '<th>%s</th>', esc_html( $h ) );
	}
	echo '</tr></thead><tbody>';

	foreach ( $rows as $r ) {
		$labels = array();
		foreach ( array_filter( explode( ',', (string) $r->interests ) ) as $slug ) {
			$labels[] = Waitlist\INTERESTS[ $slug ] ?? $slug;
		}
		printf(
			'<tr><td>%s</td><td>%s</td><td><a href="mailto:%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>',
			esc_html( (string) mysql2date( 'j M Y, g:ia', (string) $r->created_at ) ),
			esc_html( (string) $r->name ),
			esc_attr( (string) $r->email ),
			esc_html( (string) $r->email ),
			esc_html( 'partner' === $kind ? (string) $r->business : (string) $r->suburb ),
			esc_html( implode( ', ', $labels ) ),
			esc_html( (string) $r->note )
		);
	}

	echo '</tbody></table></div>';
}

/** The list as a spreadsheet, which is what anybody will actually do with it. */
function export(): void {
	if ( ! current_user_can( CAP ) || ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), 'oria_pass_export' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ) );
	}

	$kind = isset( $_GET['kind'] ) && 'partner' === $_GET['kind'] ? 'partner' : 'member';
	$rows = Waitlist\recent( $kind, 10000 );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=oria-pass-' . $kind . '-' . gmdate( 'Y-m-d' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'created', 'name', 'email', 'suburb', 'business', 'interests', 'note', 'consent', 'mode' ) );
	foreach ( $rows as $r ) {
		fputcsv(
			$out,
			array( $r->created_at, $r->name, $r->email, $r->suburb, $r->business, $r->interests, $r->note, $r->consent_text, $r->source )
		);
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

function screen_settings(): void {
	if ( ! current_user_can( CAP ) ) {
		return;
	}

	$s = Settings\all();
	$n = static fn( string $k ): string => Settings\OPTION . '[' . $k . ']';

	echo '<div class="wrap">';
	printf( '<h1>%s</h1>', esc_html__( 'Oria Pass settings', 'oria' ) );
	echo '<form method="post" action="options.php">';
	settings_fields( 'oria_pass' );

	echo '<table class="form-table"><tbody>';

	printf( '<tr><th scope="row">%s</th><td><select name="%s">', esc_html__( 'Mode', 'oria' ), esc_attr( $n( 'mode' ) ) );
	foreach ( Settings\MODES as $value => $label ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $value ),
			selected( $s['mode'], $value, false ),
			esc_html( $label )
		);
	}
	printf(
		'</select><p class="description">%s</p></td></tr>',
		esc_html__( 'Live needs the membership and booking phases built first. Until then the page collects interest rather than money.', 'oria' )
	);

	$text = array(
		'price_display'     => __( 'Price shown', 'oria' ),
		'price_period'      => __( 'Per', 'oria' ),
		'credits_per_cycle' => __( 'Credits per cycle', 'oria' ),
		'city'              => __( 'City', 'oria' ),
		'cancel_cutoff_hrs' => __( 'Cancellation cutoff (hours)', 'oria' ),
		'support_email'     => __( 'Waitlist notifications to', 'oria' ),
		'partner_email'     => __( 'Partner enquiries to', 'oria' ),
		'stripe_link'       => __( 'Stripe Payment Link', 'oria' ),
		'terms_url'         => __( 'Terms URL', 'oria' ),
		'privacy_url'       => __( 'Privacy URL', 'oria' ),
	);
	foreach ( $text as $key => $label ) {
		printf(
			'<tr><th scope="row">%s</th><td><input type="text" class="regular-text" name="%s" value="%s"></td></tr>',
			esc_html( $label ),
			esc_attr( $n( $key ) ),
			esc_attr( (string) $s[ $key ] )
		);
	}

	printf( '<tr><th scope="row">%s</th><td><select name="%s">', esc_html__( 'Unused credits', 'oria' ), esc_attr( $n( 'credit_expiry' ) ) );
	foreach ( array( 'cycle' => __( 'Expire at the end of each cycle', 'oria' ), 'rollover' => __( 'Roll over (not implemented yet)', 'oria' ) ) as $value => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $s['credit_expiry'], $value, false ), esc_html( $label ) );
	}
	echo '</select></td></tr>';

	echo '</tbody></table>';
	submit_button();
	echo '</form></div>';
}
