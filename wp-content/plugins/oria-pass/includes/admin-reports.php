<?php
/**
 * The two screens that read rather than collect: how the Pass is going,
 * and what it owes.
 *
 * Same namespace as admin.php, kept in its own file because one is a
 * glance and the other is money, and mixing the waitlist in with a payout
 * run makes both harder to read.
 *
 * Nothing here writes. Marking a payout as paid is a real feature with
 * real consequences and it does not exist yet, so this screen does not
 * pretend otherwise: it tells you what is owed and hands you the
 * spreadsheet, and the paying happens where Oria already pays people.
 *
 * @package OriaPass
 */

declare(strict_types=1);

namespace Oria\Pass\Admin;

use Oria\Pass\Reports;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** One figure, said once. */
function stat( string $label, string $value, string $note = '' ): void {
	printf(
		'<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:14px 16px;">
			<div style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#646970;">%s</div>
			<div style="font-size:26px;font-weight:600;line-height:1.2;margin:4px 0 0;">%s</div>%s
		</div>',
		esc_html( $label ),
		esc_html( $value ),
		'' !== $note ? '<div style="font-size:12px;color:#646970;margin-top:4px;">' . esc_html( $note ) . '</div>' : ''
	);
}

function money( float $amount ): string {
	return '$' . number_format_i18n( $amount, 2 );
}

/**
 * Why this place is owed for.
 *
 * Every row on a payout screen is a row somebody is being paid for, so
 * "Cancelled" beside $12.00 reads as a bug rather than as policy. These
 * rows are here precisely because the credits did not go back, and the
 * label has to say so.
 */
function outcome( string $status ): string {
	switch ( $status ) {
		case 'cancelled_by_member':
			return __( 'Cancelled too late', 'oria' );
		case 'confirmed':
			return __( 'Booked, not marked', 'oria' );
	}

	return \Oria\Pass\Booking\label( $status );
}

/** Is the Pass working? */
function screen_overview(): void {
	if ( ! current_user_can( CAP ) ) {
		return;
	}

	$o     = Reports\overview();
	$month = (string) mysql2date( 'F Y', $o['month'] . '-01' );

	echo '<div class="wrap">';
	printf( '<h1>%s</h1>', esc_html__( 'Oria Pass', 'oria' ) );
	printf(
		'<p class="description">%s</p>',
		esc_html(
			sprintf(
				/* translators: %s: month name */
				__( 'Members and money as they stand now; everything else is %s.', 'oria' ),
				$month
			)
		)
	);

	if ( $o['duplicate'] > 0 ) {
		/*
		 * Money is being taken that nobody meant to take, and no code can
		 * put it back. Said at the top, in the imperative, with the number.
		 */
		printf(
			'<div class="notice notice-warning" style="margin:14px 0;"><p><b>%s</b> %s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: count */
					_n( '%d duplicate subscription.', '%d duplicate subscriptions.', (int) $o['duplicate'], 'oria' ),
					(int) $o['duplicate']
				)
			),
			esc_html__( 'Somebody subscribed twice. The spare membership has been retired here, but Stripe is still charging for it — cancel and refund it in the Stripe dashboard.', 'oria' )
		);
	}

	echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:18px 0;">';

	stat(
		__( 'Paying members', 'oria' ),
		(string) number_format_i18n( $o['paying'] ),
		$o['past_due'] > 0
			/* translators: %d: count */
			? sprintf( _n( '%d payment has failed', '%d payments have failed', $o['past_due'], 'oria' ), $o['past_due'] )
			: __( 'No failed payments', 'oria' )
	);

	stat(
		__( 'Monthly revenue', 'oria' ),
		money( (float) $o['revenue'] ),
		__( 'Members at the listed price, before Stripe', 'oria' )
	);

	/*
	 * Issued against used is the pair worth watching. Credits handed out
	 * and never spent look like profit and are not -- they are somebody
	 * deciding, a month at a time, that the Pass was not worth it.
	 */
	$share = $o['allocated'] > 0 ? round( $o['used'] / $o['allocated'] * 100 ) : 0;
	stat(
		__( 'Credits used', 'oria' ),
		$o['allocated'] > 0
			/* translators: 1: credits used, 2: credits issued */
			? sprintf( __( '%1$s of %2$s', 'oria' ), number_format_i18n( $o['used'] ), number_format_i18n( $o['allocated'] ) )
			: '—',
		$o['allocated'] > 0
			/* translators: %d: percentage */
			? sprintf( __( '%d%% of what was issued this month', 'oria' ), $share )
			: __( 'Nothing issued yet this month', 'oria' )
	);

	stat(
		__( 'Sessions this month', 'oria' ),
		(string) number_format_i18n( $o['sessions'] ),
		/* translators: %s: count */
		sprintf( __( '%s open for booking from here on', 'oria' ), number_format_i18n( $o['upcoming'] ) )
	);

	stat(
		__( 'Studios offering places', 'oria' ),
		(string) number_format_i18n( $o['partners'] ),
		__( 'Listings with a session that is not a draft', 'oria' )
	);

	stat(
		__( 'Owed to studios', 'oria' ),
		money( (float) $o['owing'] ),
		/* translators: %s: count of places */
		sprintf( _n( '%s place this month', '%s places this month', (int) $o['places'], 'oria' ), number_format_i18n( $o['places'] ) )
	);

	$marked = $o['attended'] + $o['no_show'];
	stat(
		__( 'Marked at the door', 'oria' ),
		$marked > 0 ? (string) number_format_i18n( $marked ) : '—',
		$marked > 0
			/* translators: 1: attended, 2: no-shows */
			? sprintf( __( '%1$s went, %2$s did not', 'oria' ), number_format_i18n( $o['attended'] ), number_format_i18n( $o['no_show'] ) )
			: __( 'Studios have not marked anybody off yet', 'oria' )
	);

	echo '</div>';

	if ( 0 === $o['paying'] && 0 === $o['sessions'] ) {
		printf(
			'<p>%s</p>',
			esc_html__( 'Nothing has happened yet. That is what an empty month looks like, not a broken screen.', 'oria' )
		);
	}

	printf(
		'<p><a class="button button-primary" href="%s">%s</a> <a class="button" href="%s">%s</a></p>',
		esc_url( admin_url( 'admin.php?page=oria-pass-payouts' ) ),
		esc_html__( 'Payouts', 'oria' ),
		esc_url( admin_url( 'admin.php?page=oria-pass-waitlist' ) ),
		esc_html__( 'Waitlist', 'oria' )
	);

	echo '</div>';
}

/** What Oria owes, and to whom. */
function screen_payouts(): void {
	if ( ! current_user_can( CAP ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading a report.
	$month   = Reports\month( sanitize_text_field( (string) ( $_GET['month'] ?? '' ) ) );
	$listing = (int) ( $_GET['listing'] ?? 0 );
	// phpcs:enable

	echo '<div class="wrap">';
	printf( '<h1>%s</h1>', esc_html__( 'Oria Pass payouts', 'oria' ) );

	printf(
		'<p class="description">%s</p>',
		esc_html__( 'A studio is paid for the places a member paid for. Credits that went back are not owed; a place somebody cancelled too late, or booked and did not turn up to, is — the seat was held either way. Places count in the month the session ran.', 'oria' )
	);

	// The month picker, built from months that have something in them.
	echo '<form method="get" style="margin:12px 0;"><input type="hidden" name="page" value="oria-pass-payouts">';
	echo '<select name="month">';
	foreach ( Reports\months() as $option ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $option ),
			selected( $month, $option, false ),
			esc_html( (string) mysql2date( 'F Y', $option . '-01' ) )
		);
	}
	printf( '</select> <button class="button">%s</button>', esc_html__( 'Show', 'oria' ) );
	echo '</form>';

	if ( $listing > 0 ) {
		detail( $month, $listing );
		echo '</div>';
		return;
	}

	$rows  = Reports\payouts( $month );
	$total = 0.0;
	foreach ( $rows as $row ) {
		$total += (float) $row['amount'];
	}

	if ( ! $rows ) {
		printf( '<p>%s</p></div>', esc_html__( 'Nothing payable in that month.', 'oria' ) );
		return;
	}

	printf(
		'<p><a class="button" href="%s">%s</a></p>',
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oria_pass_payouts_csv&month=' . rawurlencode( $month ) ), 'oria_pass_payouts_csv' ) ),
		esc_html__( 'Export CSV', 'oria' )
	);

	echo '<table class="widefat striped"><thead><tr>';
	foreach ( array( __( 'Studio', 'oria' ), __( 'Sessions', 'oria' ), __( 'Places', 'oria' ), __( 'Credits', 'oria' ), __( 'Owed', 'oria' ) ) as $i => $head ) {
		printf( '<th%s>%s</th>', $i > 0 ? ' style="text-align:right;"' : '', esc_html( $head ) );
	}
	echo '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		printf(
			'<tr><td><a href="%s">%s</a></td><td style="text-align:right;">%s</td><td style="text-align:right;">%s</td><td style="text-align:right;">%s</td><td style="text-align:right;font-variant-numeric:tabular-nums;">%s</td></tr>',
			esc_url( admin_url( 'admin.php?page=oria-pass-payouts&month=' . rawurlencode( $month ) . '&listing=' . (int) $row['listing_id'] ) ),
			esc_html( $row['name'] ),
			esc_html( number_format_i18n( (int) $row['sessions'] ) ),
			esc_html( number_format_i18n( (int) $row['places'] ) ),
			esc_html( number_format_i18n( (int) $row['credits'] ) ),
			esc_html( money( (float) $row['amount'] ) )
		);
	}

	printf(
		'</tbody><tfoot><tr><th>%s</th><th></th><th></th><th></th><th style="text-align:right;font-variant-numeric:tabular-nums;">%s</th></tr></tfoot></table>',
		esc_html__( 'Total', 'oria' ),
		esc_html( money( $total ) )
	);

	printf(
		'<p class="description">%s</p>',
		esc_html__( 'Nothing on this screen marks anything as paid. Pay studios where Oria already pays people, and keep the CSV as the record of what for.', 'oria' )
	);

	echo '</div>';
}

/** One studio's month, place by place, for the conversation that follows a query. */
function detail( string $month, int $listing_id ): void {
	$rows  = Reports\payable( $month, $listing_id );
	$total = 0.0;

	printf(
		'<h2>%s</h2><p><a href="%s">%s</a></p>',
		esc_html( wp_specialchars_decode( (string) get_the_title( $listing_id ), ENT_QUOTES ) ),
		esc_url( admin_url( 'admin.php?page=oria-pass-payouts&month=' . rawurlencode( $month ) ) ),
		esc_html__( '&larr; All studios', 'oria' )
	);

	if ( ! $rows ) {
		printf( '<p>%s</p>', esc_html__( 'Nothing payable for that studio in that month.', 'oria' ) );
		return;
	}

	echo '<table class="widefat striped"><thead><tr>';
	foreach ( array( __( 'Session', 'oria' ), __( 'When', 'oria' ), __( 'Reference', 'oria' ), __( 'Member', 'oria' ), __( 'Outcome', 'oria' ), __( 'Credits', 'oria' ), __( 'Owed', 'oria' ) ) as $head ) {
		printf( '<th>%s</th>', esc_html( $head ) );
	}
	echo '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		$total += (float) $row->provider_payout;
		$member = get_userdata( (int) $row->user_id );

		printf(
			'<tr><td>%s</td><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td><td style="font-variant-numeric:tabular-nums;">%s</td></tr>',
			esc_html( (string) $row->title ),
			esc_html( (string) mysql2date( 'D j M, g:ia', (string) $row->start_at ) ),
			esc_html( (string) $row->booking_reference ),
			esc_html( $member ? $member->display_name : __( 'Deleted account', 'oria' ) ),
			esc_html( outcome( (string) $row->status ) ),
			esc_html( number_format_i18n( abs( (int) $row->net ) ) ),
			esc_html( money( (float) $row->provider_payout ) )
		);
	}

	printf(
		'</tbody><tfoot><tr><th>%s</th><th></th><th></th><th></th><th></th><th></th><th style="font-variant-numeric:tabular-nums;">%s</th></tr></tfoot></table>',
		esc_html__( 'Total', 'oria' ),
		esc_html( money( $total ) )
	);
}

/**
 * The month as a spreadsheet.
 *
 * One row per place rather than one per studio: a studio that queries its
 * total wants to see which places made it up, and a summary cannot answer
 * that. Summing it is the spreadsheet's job.
 */
function payouts_csv(): void {
	if ( ! current_user_can( CAP ) || ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), 'oria_pass_payouts_csv' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ) );
	}

	$month = Reports\month( sanitize_text_field( (string) ( $_GET['month'] ?? '' ) ) );
	$rows  = Reports\payable( $month );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=oria-pass-payouts-' . $month . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'studio', 'listing_id', 'session', 'session_start', 'reference', 'member', 'outcome', 'credits', 'payout_aud' ) );

	foreach ( $rows as $row ) {
		$member = get_userdata( (int) $row->user_id );

		fputcsv(
			$out,
			array(
				wp_specialchars_decode( (string) get_the_title( (int) $row->listing_id ), ENT_QUOTES ),
				(int) $row->listing_id,
				(string) $row->title,
				(string) $row->start_at,
				(string) $row->booking_reference,
				$member ? $member->display_name : '',
				outcome( (string) $row->status ),
				abs( (int) $row->net ),
				number_format( (float) $row->provider_payout, 2, '.', '' ),
			)
		);
	}

	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
