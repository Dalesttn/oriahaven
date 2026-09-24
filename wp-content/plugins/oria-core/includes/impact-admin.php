<?php
/**
 * Listings → Impact reports: what the weekly job is about to do, and what
 * it has already done.
 *
 * The job runs on a Monday morning whether anybody is watching or not, and
 * it sends real email to real practices. So this screen exists to answer
 * three questions before that happens: who would get one this week, what
 * would it actually say, and who has been written to already.
 *
 * Every number here is worked out by Impact\survey(), the same call the
 * cron and the CLI make. A screen that computed eligibility for itself
 * would drift from the job that sends, and the drift would only surface as
 * an email nobody expected.
 *
 * @see Oria\Core\Impact for the rules and the send.
 */

declare(strict_types=1);

namespace Oria\Core\ImpactAdmin;

use Oria\Core\Impact;
use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SLUG = 'oria-impact-reports';

/** Log rows per page. */
const PER_PAGE = 50;

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_impact_preview', __NAMESPACE__ . '\preview' );
	add_action( 'admin_post_oria_impact_send', __NAMESPACE__ . '\send_now' );
	add_action( 'admin_post_oria_impact_toggle', __NAMESPACE__ . '\toggle' );
}

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . PostTypes\LISTING,
		__( 'Impact reports', 'oria' ),
		__( 'Impact reports', 'oria' ),
		'manage_options',
		SLUG,
		__NAMESPACE__ . '\render'
	);
}

function base_url(): string {
	return admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=' . SLUG );
}

function current_tab(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing a view, changing nothing.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'due';

	return in_array( $tab, array( 'due', 'log' ), true ) ? $tab : 'due';
}

/* --------------------------------------------------------------- actions */

/**
 * Show the email exactly as it would arrive.
 *
 * Rendered from the live numbers rather than from a fixture, because the
 * thing worth checking before a send is this week's wording on this week's
 * figures -- whether the nudge is the right one, whether a count reads
 * oddly at this size.
 */
function preview(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot preview these.', 'oria' ) );
	}
	check_admin_referer( 'oria_impact_preview' );

	$listing = isset( $_GET['listing'] ) ? (int) $_GET['listing'] : 0;
	$data    = $listing ? Impact\pending( $listing ) : null;

	if ( ! $data ) {
		wp_die( esc_html__( 'There is nothing to report for that listing, so there is no email to show.', 'oria' ) );
	}

	// Never cached, and never indexed: this is somebody's private mail.
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );

	echo Impact\body_html( $listing, $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- an email body, escaped where it is built.
	exit;
}

/**
 * Run the job by hand, now.
 *
 * This sends real email, so it is a POST with a nonce and it says how many
 * it is about to send before it does. `--force` is deliberately not offered
 * here: forcing past the threshold is a debugging move for a terminal, not
 * a button sitting next to a list of real practices.
 */
function send_now(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot send these.', 'oria' ) );
	}
	check_admin_referer( 'oria_impact_send' );

	$listing = isset( $_POST['listing'] ) ? (int) $_POST['listing'] : 0;
	$out     = Impact\run( false, $listing );

	wp_safe_redirect(
		add_query_arg(
			array( 'tab' => 'log', 'sent' => (int) $out['sent'], 'failed' => (int) $out['failed'] ),
			base_url()
		)
	);
	exit;
}

/** The site-wide switch. Stops every send without touching anybody's own preference. */
function toggle(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot change this.', 'oria' ) );
	}
	check_admin_referer( 'oria_impact_toggle' );

	// '1'/'0' rather than a boolean -- see Impact\enabled().
	update_option( 'oria_impact_enabled', isset( $_POST['on'] ) ? '1' : '0' );

	wp_safe_redirect( base_url() );
	exit;
}

/* ------------------------------------------------------------------ view */

function render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tab = current_tab();

	echo '<div class="wrap"><h1>' . esc_html__( 'Impact reports', 'oria' ) . '</h1>';

	echo '<p class="description" style="max-width:46em">'
		. esc_html__( 'Once a week a claimed practice is told how many people Oria sent to its website, but only when there have been enough of them to be worth an email. The counts come from the same first-party buckets as the click report, and only complete days are ever counted, so the same clicks are never reported twice.', 'oria' )
		. '</p>';

	status_panel();

	echo '<h2 class="nav-tab-wrapper" style="margin:1.5em 0 0">';
	foreach ( array( 'due' => __( 'Due this week', 'oria' ), 'log' => __( 'Already sent', 'oria' ) ) as $slug => $label ) {
		printf(
			'<a href="%s" class="nav-tab%s">%s</a>',
			esc_url( add_query_arg( 'tab', $slug, base_url() ) ),
			$slug === $tab ? ' nav-tab-active' : '',
			esc_html( $label )
		);
	}
	echo '</h2>';

	if ( 'log' === $tab ) {
		log_table();
	} else {
		due_table();
	}

	echo '</div>';
}

/** When it next runs, whether it is switched on, and the rules it uses. */
function status_panel(): void {
	$next = wp_next_scheduled( Impact\HOOK );
	$on   = Impact\enabled();
	$last = (array) get_option( 'oria_impact_last_run', array() );

	echo '<div class="notice notice-info inline" style="margin:1em 0;padding:12px 14px">';

	echo '<p style="margin:0 0 .6em"><b>' . esc_html__( 'Next run:', 'oria' ) . '</b> ';
	echo $next
		? esc_html( wp_date( 'l j F Y, g:ia T', $next ) )
		: '<span style="color:#b32d2e">' . esc_html__( 'not scheduled — the job will never run', 'oria' ) . '</span>';
	echo '</p>';

	printf(
		'<p style="margin:0 0 .6em">%s</p>',
		esc_html(
			sprintf(
				/* translators: 1: click threshold, 2: last complete day */
				__( 'A practice is written to at %1$d or more website clicks, and never more than once in seven days. Counting runs to the end of %2$s; today is still in progress and is not included.', 'oria' ),
				Impact\threshold(),
				wp_date( 'j F', (int) strtotime( Impact\last_complete_day() ) )
			)
		)
	);

	if ( $last ) {
		printf(
			'<p style="margin:0 0 .6em">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: date, 2: checked, 3: sent, 4: failed */
					__( 'Last run %1$s — %2$d checked, %3$d sent, %4$d failed.', 'oria' ),
					(string) ( $last['at'] ?? '?' ),
					(int) ( $last['checked'] ?? 0 ),
					(int) ( $last['sent'] ?? 0 ),
					(int) ( $last['failed'] ?? 0 )
				)
			)
		);
	}

	printf(
		'<form method="post" action="%s" style="margin:0">
			<input type="hidden" name="action" value="oria_impact_toggle">
			%s
			<label><input type="checkbox" name="on" value="1" %s> %s</label>
			<button type="submit" class="button button-small" style="margin-left:.6em">%s</button>
		</form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'oria_impact_toggle', '_wpnonce', true, false ),
		checked( $on, true, false ),
		esc_html__( 'Send these reports', 'oria' ),
		esc_html__( 'Save', 'oria' )
	);

	echo '</div>';
}

/**
 * Who would be written to, and why everybody else would not.
 *
 * The near misses are the useful half. A practice sitting on four clicks is
 * not a fault, it is the rule working, and seeing that is what stops
 * somebody "fixing" a threshold that is doing its job.
 */
function due_table(): void {
	$rows = Impact\survey();
	$due  = array_values( array_filter( $rows, static fn( array $r ): bool => $r['ok'] ) );

	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No listing has been claimed yet, so there is nobody to write to.', 'oria' ) . '</p>';

		return;
	}

	/*
	 * Eligibility and the site-wide switch are different questions, and the
	 * table below answers the first one. Saying "will be written to" while
	 * the switch is off would be a straightforward lie, so the switch is
	 * stated here and the send button is not offered at all.
	 */
	$live = Impact\enabled();

	if ( ! $live ) {
		printf(
			'<div class="notice notice-warning inline" style="margin:1.5em 0"><p>%s</p></div>',
			esc_html__( 'These reports are switched off, so nothing below will actually be sent. The list is what would go out if you switched them back on.', 'oria' )
		);
	}

	if ( $due && $live ) {
		printf(
			'<form method="post" action="%s" style="margin:1.5em 0 1em">
				<input type="hidden" name="action" value="oria_impact_send">
				%s
				<button type="submit" class="button button-primary">%s</button>
				<span class="description" style="margin-left:.8em">%s</span>
			</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'oria_impact_send', '_wpnonce', true, false ),
			esc_html(
				sprintf(
					/* translators: %d: number of practices */
					_n( 'Send %d report now', 'Send %d reports now', count( $due ), 'oria' ),
					count( $due )
				)
			),
			esc_html__( 'Real email, sent immediately. The weekly job does this on its own; this is for when you want it sooner.', 'oria' )
		);
	} elseif ( $live ) {
		echo '<p style="margin:1.5em 0 1em">'
			. esc_html__( 'Nothing is due. Every claimed practice is either below the threshold, already written to this week, or has switched these off.', 'oria' )
			. '</p>';
	}

	echo '<table class="wp-list-table widefat striped"><thead><tr>';
	printf( '<th>%s</th>', esc_html__( 'Practice', 'oria' ) );
	printf( '<th style="width:8em">%s</th>', esc_html__( 'Website clicks', 'oria' ) );
	printf( '<th style="width:14em">%s</th>', esc_html__( 'Period', 'oria' ) );
	printf( '<th>%s</th>', esc_html__( 'What happens', 'oria' ) );
	echo '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		$clicks = isset( $row['data']['clicks'] ) ? (int) $row['data']['clicks'] : null;

		echo '<tr>';

		printf(
			'<td><a href="%s">%s</a></td>',
			esc_url( (string) get_edit_post_link( $row['listing'] ) ),
			esc_html( $row['name'] )
		);

		printf(
			'<td style="font-variant-numeric:tabular-nums">%s</td>',
			null === $clicks ? '—' : esc_html( number_format_i18n( $clicks ) )
		);

		printf(
			'<td>%s</td>',
			isset( $row['data']['from'] )
				? esc_html( $row['data']['from'] . ' → ' . $row['data']['to'] )
				: '—'
		);

		echo '<td>';
		if ( $row['ok'] ) {
			echo '<b>' . esc_html( $live ? __( 'Will be written to', 'oria' ) : __( 'Would be written to', 'oria' ) ) . '</b> ';
			printf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							array( 'action' => 'oria_impact_preview', 'listing' => $row['listing'] ),
							admin_url( 'admin-post.php' )
						),
						'oria_impact_preview'
					)
				),
				esc_html__( 'Preview the email', 'oria' )
			);
		} else {
			echo esc_html( $row['why'] );

			// A near miss can still be previewed: it is the wording that is
			// worth checking, and the numbers are real either way.
			if ( null !== $clicks && $clicks > 0 ) {
				printf(
					' <a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url(
						wp_nonce_url(
							add_query_arg(
								array( 'action' => 'oria_impact_preview', 'listing' => $row['listing'] ),
								admin_url( 'admin-post.php' )
							),
							'oria_impact_preview'
						)
					),
					esc_html__( 'Preview', 'oria' )
				);
			}
		}
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody></table>';
}

/**
 * The log: every report, whether it went or not.
 *
 * Append-only and never rewritten, so this is the answer to "did we
 * actually email them, and what did we say the numbers were" months after
 * the buckets it was built from have been pruned.
 */
function log_table(): void {
	global $wpdb;

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- paging, changing nothing.
	$page  = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
	$table = Impact\table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
			PER_PAGE,
			( $page - 1 ) * PER_PAGE
		)
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading back our own redirect.
	$sent = isset( $_GET['sent'] ) ? (int) $_GET['sent'] : -1;
	if ( $sent >= 0 ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$failed = isset( $_GET['failed'] ) ? (int) $_GET['failed'] : 0;
		printf(
			'<div class="notice notice-%s inline" style="margin:1em 0"><p>%s</p></div>',
			$failed ? 'warning' : 'success',
			esc_html(
				$failed
					? sprintf(
						/* translators: 1: sent, 2: failed */
						__( '%1$d sent, %2$d failed. The failed ones keep their clicks and will be tried again.', 'oria' ),
						$sent,
						$failed
					)
					: sprintf( /* translators: %d: number sent */ _n( '%d report sent.', '%d reports sent.', $sent, 'oria' ), $sent )
			)
		);
	}

	if ( ! $rows ) {
		echo '<p style="margin-top:1.5em">' . esc_html__( 'Nothing has been sent yet.', 'oria' ) . '</p>';

		return;
	}

	echo '<table class="wp-list-table widefat striped" style="margin-top:1.5em"><thead><tr>';
	printf( '<th>%s</th>', esc_html__( 'Practice', 'oria' ) );
	printf( '<th>%s</th>', esc_html__( 'Sent to', 'oria' ) );
	printf( '<th style="width:14em">%s</th>', esc_html__( 'Period', 'oria' ) );
	printf( '<th style="width:7em">%s</th>', esc_html__( 'Clicks', 'oria' ) );
	printf( '<th style="width:12em">%s</th>', esc_html__( 'Result', 'oria' ) );
	printf( '<th style="width:13em">%s</th>', esc_html__( 'When', 'oria' ) );
	echo '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		echo '<tr>';

		printf(
			'<td><a href="%s">%s</a></td>',
			esc_url( (string) get_edit_post_link( (int) $row->listing_id ) ),
			esc_html( Impact\short_name( (int) $row->listing_id ) )
		);

		printf( '<td>%s</td>', esc_html( (string) $row->recipient ) );
		printf( '<td>%s</td>', esc_html( $row->period_start . ' → ' . $row->period_end ) );
		printf( '<td style="font-variant-numeric:tabular-nums">%s</td>', esc_html( number_format_i18n( (int) $row->website_clicks ) ) );

		$status = (string) $row->status;
		$colour = array( 'sent' => '#1a7f5a', 'failed' => '#b32d2e', 'pending' => '#8a6d00' )[ $status ] ?? 'inherit';
		printf(
			'<td style="color:%s">%s%s</td>',
			esc_attr( $colour ),
			esc_html( $status ),
			'' !== (string) $row->failure_reason ? '<br><small>' . esc_html( (string) $row->failure_reason ) . '</small>' : ''
		);

		$when = (string) ( $row->sent_at ?: $row->created_at );
		printf( '<td>%s</td>', esc_html( get_date_from_gmt( $when, 'j M Y, g:ia' ) ) );

		echo '</tr>';
	}

	echo '</tbody></table>';

	$pages = (int) ceil( $total / PER_PAGE );
	if ( $pages > 1 ) {
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg( array( 'tab' => 'log', 'paged' => '%#%' ), base_url() ),
					'format'    => '',
					'current'   => $page,
					'total'     => $pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				)
			)
		);
		echo '</div></div>';
	}
}
