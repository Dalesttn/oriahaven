<?php
/**
 * Listings → Click report: every listing ranked by what visitors did on it.
 *
 * The per-listing metabox answers "is this listing sending me people?" for one
 * practitioner. This answers the other half of the same question for the person
 * running the directory: which listings are working, which categories pull, and
 * whether the traffic ends in a phone call or goes nowhere.
 *
 * Reads the same `_oria_stats` buckets Analytics writes — one row per listing,
 * one array per day. No second store, so the report can never disagree with the
 * metabox a practitioner is looking at.
 *
 * @see Oria\Core\Analytics for how the numbers are recorded.
 */

declare(strict_types=1);

namespace Oria\Core\AnalyticsReport;

use Oria\Core\Analytics;
use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SLUG = 'oria-click-report';

/** Windows offered, in days. 90 is the retention ceiling Analytics prunes to. */
const WINDOWS = array( 7, 30, 90 );

/** Click types, in the order a visitor would meet them, with plain labels. */
function labels(): array {
	return array(
		'view' => __( 'Views', 'oria' ),
		'web'  => __( 'Website', 'oria' ),
		'tel'  => __( 'Phone', 'oria' ),
		'mail' => __( 'Email', 'oria' ),
		'book' => __( 'Booking', 'oria' ),
		'dir'  => __( 'Directions', 'oria' ),
		'enq'  => __( 'Enquiries', 'oria' ),
	);
}

/** Everything except the view counter — a view is arriving, not acting. */
function click_types(): array {
	return array_values( array_diff( Analytics\TYPES, array( 'view' ) ) );
}

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_click_report_csv', __NAMESPACE__ . '\export_csv' );
}

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . PostTypes\LISTING,
		__( 'Click report', 'oria' ),
		__( 'Click report', 'oria' ),
		'manage_options',
		SLUG,
		__NAMESPACE__ . '\render'
	);
}

/* ------------------------------------------------------------------ data */

/**
 * Every listing's totals for the window, newest-first by whichever column was
 * asked for.
 *
 * One query for all the stats rows rather than get_post_meta() per listing:
 * at 350 listings the per-post version is 350 round trips to answer a page
 * nobody wants to wait for. The unserialising happens here in PHP, which is
 * cheap, and the titles and terms come from one more query each.
 *
 * @param int    $days  Window length.
 * @param string $order A click type, 'view', or 'clicks' for the total.
 * @return list<array>
 */
function rows( int $days, string $order = 'clicks' ): array {
	global $wpdb;

	$cutoff = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
	$types  = click_types();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$raw = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.post_id, pm.meta_value
			   FROM {$wpdb->postmeta} pm
			   INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			  WHERE pm.meta_key = %s
			    AND p.post_type = %s
			    AND p.post_status = 'publish'",
			Analytics\META_STATS,
			PostTypes\LISTING
		)
	);

	$out = array();
	foreach ( (array) $raw as $row ) {
		$stats = maybe_unserialize( $row->meta_value );
		if ( ! is_array( $stats ) ) {
			continue;
		}

		$totals = array_fill_keys( Analytics\TYPES, 0 );
		foreach ( $stats as $day => $day_row ) {
			// String compare is safe and index-friendly on Y-m-d.
			if ( ! is_array( $day_row ) || (string) $day < $cutoff ) {
				continue;
			}
			foreach ( Analytics\TYPES as $t ) {
				$totals[ $t ] += (int) ( $day_row[ $t ] ?? 0 );
			}
		}

		$clicks = 0;
		foreach ( $types as $t ) {
			$clicks += $totals[ $t ];
		}

		// A listing with nothing in the window is not a row worth printing.
		if ( ! $clicks && ! $totals['view'] ) {
			continue;
		}

		$id    = (int) $row->post_id;
		$terms = get_the_terms( $id, Taxonomies\PRACTICE );
		$cats  = array();
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$cats[] = wp_specialchars_decode( $t->name, ENT_QUOTES );
		}
		sort( $cats );

		$out[] = array(
			'id'       => $id,
			'title'    => wp_specialchars_decode( (string) get_the_title( $id ), ENT_QUOTES ),
			'status'   => (string) get_post_meta( $id, 'claim_status', true ) ?: 'unclaimed',
			/*
			 * Both forms are kept. The joined string is for display; the array
			 * is what by_category() counts, because a category name can itself
			 * contain a comma — "Women's, Men's & Family Wellness" does — and
			 * splitting the display string back apart turned that one category
			 * into two phantom rows in the roll-up.
			 */
			'cats'     => $cats,
			'category' => $cats ? implode( ', ', $cats ) : '—',
			'totals'   => $totals,
			'clicks'   => $clicks,
			// The number that actually says whether a page is doing its job:
			// arriving is not the same as acting.
			'rate'     => $totals['view'] > 0 ? round( 100 * $clicks / $totals['view'], 1 ) : null,
		);
	}

	$key = ( 'clicks' === $order ) ? null : $order;
	usort(
		$out,
		static function ( array $a, array $b ) use ( $key ): int {
			$av = null === $key ? $a['clicks'] : (int) ( $a['totals'][ $key ] ?? 0 );
			$bv = null === $key ? $b['clicks'] : (int) ( $b['totals'][ $key ] ?? 0 );
			return $bv <=> $av ?: strcasecmp( $a['title'], $b['title'] );
		}
	);

	return $out;
}

/** Totals per practice category, for the summary table. */
function by_category( array $rows ): array {
	$out = array();
	foreach ( $rows as $r ) {
		// A listing in two categories counts in both — the alternative is
		// picking one arbitrarily and under-reporting the other. Read from
		// the array, never from the joined display string.
		$cats = $r['cats'] ? $r['cats'] : array( '—' );
		foreach ( $cats as $cat ) {
			if ( ! isset( $out[ $cat ] ) ) {
				$out[ $cat ] = array( 'listings' => 0, 'views' => 0, 'clicks' => 0 );
			}
			$out[ $cat ]['listings']++;
			$out[ $cat ]['views']  += $r['totals']['view'];
			$out[ $cat ]['clicks'] += $r['clicks'];
		}
	}
	uasort( $out, static fn( array $a, array $b ): int => $b['clicks'] <=> $a['clicks'] );
	return $out;
}

/* ---------------------------------------------------------------- screen */

function current_days(): int {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	$d = isset( $_GET['days'] ) ? (int) $_GET['days'] : 30;
	return in_array( $d, WINDOWS, true ) ? $d : 30;
}

function current_order(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	$o = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( (string) $_GET['order'] ) ) : 'clicks';
	return ( 'clicks' === $o || in_array( $o, Analytics\TYPES, true ) ) ? $o : 'clicks';
}

function render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$days   = current_days();
	$order  = current_order();
	$rows   = rows( $days, $order );
	$labels = labels();
	$base   = admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=' . SLUG );

	$sum = array_fill_keys( Analytics\TYPES, 0 );
	$all = 0;
	foreach ( $rows as $r ) {
		foreach ( Analytics\TYPES as $t ) {
			$sum[ $t ] += $r['totals'][ $t ];
		}
		$all += $r['clicks'];
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'Click report', 'oria' ) . '</h1>';

	echo '<p class="description" style="max-width:44em">'
		. esc_html__( 'What visitors did on each listing, counted on this site only — no cookies and no third-party trackers. Owners viewing their own listing are excluded. Nothing older than 90 days is kept, so the 90-day window is everything there is.', 'oria' )
		. '</p>';

	// --- window switcher -------------------------------------------------
	echo '<h2 class="nav-tab-wrapper" style="margin:1em 0 0">';
	foreach ( WINDOWS as $w ) {
		printf(
			'<a href="%s" class="nav-tab%s">%s</a>',
			esc_url( add_query_arg( array( 'days' => $w, 'order' => $order ), $base ) ),
			$w === $days ? ' nav-tab-active' : '',
			esc_html( sprintf( /* translators: %d: number of days */ __( 'Last %d days', 'oria' ), $w ) )
		);
	}
	echo '</h2>';

	if ( ! $rows ) {
		echo '<div class="notice notice-info inline" style="margin-top:1em"><p>'
			. esc_html__( 'Nothing recorded in this window yet. Counts start the first time somebody opens a listing — if the site has only just gone live, come back in a few days.', 'oria' )
			. '</p></div></div>';
		return;
	}

	// --- headline --------------------------------------------------------
	echo '<div style="display:flex;flex-wrap:wrap;gap:1em;margin:1.5em 0">';
	$cards = array(
		__( 'Listings with activity', 'oria' ) => number_format_i18n( count( $rows ) ),
		__( 'Profile views', 'oria' )          => number_format_i18n( $sum['view'] ),
		__( 'Contact clicks', 'oria' )         => number_format_i18n( $all ),
		__( 'Clicks per 100 views', 'oria' )   => $sum['view'] > 0 ? number_format_i18n( round( 100 * $all / $sum['view'], 1 ), 1 ) : '—',
	);
	foreach ( $cards as $label => $value ) {
		printf(
			'<div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:12px 18px;min-width:150px">'
			. '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#646970">%s</div>'
			. '<div style="font-size:24px;font-weight:600;line-height:1.3">%s</div></div>',
			esc_html( $label ),
			esc_html( $value )
		);
	}
	echo '</div>';

	// --- the table -------------------------------------------------------
	$sortable = array_merge( array( 'view' ), click_types() );

	echo '<table class="widefat striped"><thead><tr>';
	echo '<th style="width:2.5em">#</th><th>' . esc_html__( 'Listing', 'oria' ) . '</th>';
	echo '<th>' . esc_html__( 'Category', 'oria' ) . '</th>';
	foreach ( $sortable as $t ) {
		$on = ( $order === $t );
		printf(
			'<th style="text-align:right"><a href="%s"%s>%s%s</a></th>',
			esc_url( add_query_arg( array( 'days' => $days, 'order' => $t ), $base ) ),
			$on ? ' style="text-decoration:underline"' : '',
			esc_html( $labels[ $t ] ?? $t ),
			$on ? ' ↓' : ''
		);
	}
	printf(
		'<th style="text-align:right"><a href="%s"%s>%s%s</a></th>',
		esc_url( add_query_arg( array( 'days' => $days, 'order' => 'clicks' ), $base ) ),
		'clicks' === $order ? ' style="text-decoration:underline"' : '',
		esc_html__( 'All clicks', 'oria' ),
		'clicks' === $order ? ' ↓' : ''
	);
	echo '<th style="text-align:right" title="' . esc_attr__( 'Contact clicks per 100 profile views', 'oria' ) . '">' . esc_html__( 'Per 100', 'oria' ) . '</th>';
	echo '</tr></thead><tbody>';

	$i = 0;
	foreach ( $rows as $r ) {
		$i++;
		echo '<tr>';
		echo '<td>' . esc_html( number_format_i18n( $i ) ) . '</td>';
		printf(
			'<td><a href="%s"><strong>%s</strong></a>%s</td>',
			esc_url( (string) get_edit_post_link( $r['id'] ) ),
			esc_html( $r['title'] ),
			'unclaimed' === $r['status'] ? '' : ' <span class="dashicons dashicons-yes-alt" style="color:#3F6E60;font-size:16px;width:16px;height:16px" title="' . esc_attr( ucfirst( $r['status'] ) ) . '"></span>'
		);
		echo '<td>' . esc_html( $r['category'] ) . '</td>';
		foreach ( $sortable as $t ) {
			printf( '<td style="text-align:right">%s</td>', esc_html( number_format_i18n( $r['totals'][ $t ] ) ) );
		}
		printf( '<td style="text-align:right"><strong>%s</strong></td>', esc_html( number_format_i18n( $r['clicks'] ) ) );
		printf( '<td style="text-align:right">%s</td>', esc_html( null === $r['rate'] ? '—' : number_format_i18n( $r['rate'], 1 ) ) );
		echo '</tr>';
	}
	echo '</tbody></table>';

	// --- category roll-up ------------------------------------------------
	echo '<h2 style="margin-top:2em">' . esc_html__( 'By category', 'oria' ) . '</h2>';
	echo '<p class="description">' . esc_html__( 'A listing filed in two categories is counted in both, so these columns add up to more than the totals above.', 'oria' ) . '</p>';
	echo '<table class="widefat striped" style="max-width:44em"><thead><tr><th>' . esc_html__( 'Category', 'oria' ) . '</th>'
		. '<th style="text-align:right">' . esc_html__( 'Listings', 'oria' ) . '</th>'
		. '<th style="text-align:right">' . esc_html__( 'Views', 'oria' ) . '</th>'
		. '<th style="text-align:right">' . esc_html__( 'Clicks', 'oria' ) . '</th>'
		. '<th style="text-align:right">' . esc_html__( 'Per 100', 'oria' ) . '</th></tr></thead><tbody>';
	foreach ( by_category( $rows ) as $cat => $c ) {
		printf(
			'<tr><td>%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td><td style="text-align:right"><strong>%s</strong></td><td style="text-align:right">%s</td></tr>',
			esc_html( $cat ),
			esc_html( number_format_i18n( $c['listings'] ) ),
			esc_html( number_format_i18n( $c['views'] ) ),
			esc_html( number_format_i18n( $c['clicks'] ) ),
			esc_html( $c['views'] > 0 ? number_format_i18n( round( 100 * $c['clicks'] / $c['views'], 1 ), 1 ) : '—' )
		);
	}
	echo '</tbody></table>';

	// --- export ----------------------------------------------------------
	printf(
		'<form method="post" action="%s" style="margin-top:1.5em">%s'
		. '<input type="hidden" name="action" value="oria_click_report_csv">'
		. '<input type="hidden" name="days" value="%d">'
		. '<input type="hidden" name="order" value="%s">'
		. '<button class="button">%s</button></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'oria_click_report_csv', '_wpnonce', true, false ), // phpcs:ignore WordPress.Security.EscapeOutput
		(int) $days,
		esc_attr( $order ),
		esc_html__( 'Download CSV', 'oria' )
	);

	echo '</div>';
}

/* ------------------------------------------------------------------ csv */

/**
 * The CSV exists so a practitioner's numbers can be sent to them without
 * screenshotting an admin screen — which is the whole reason the counters
 * were built.
 */
function export_csv(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot do that.', 'oria' ) );
	}
	check_admin_referer( 'oria_click_report_csv' );

	$days  = isset( $_POST['days'] ) ? (int) $_POST['days'] : 30;
	$days  = in_array( $days, WINDOWS, true ) ? $days : 30;
	$order = isset( $_POST['order'] ) ? sanitize_key( wp_unslash( (string) $_POST['order'] ) ) : 'clicks';
	$order = ( 'clicks' === $order || in_array( $order, Analytics\TYPES, true ) ) ? $order : 'clicks';

	$rows   = rows( $days, $order );
	$labels = labels();
	$cols   = array_merge( array( 'view' ), click_types() );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=oria-clicks-' . $days . 'd-' . gmdate( 'Y-m-d' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );

	$head = array( __( 'Listing', 'oria' ), __( 'Category', 'oria' ), __( 'Status', 'oria' ) );
	foreach ( $cols as $t ) {
		$head[] = $labels[ $t ] ?? $t;
	}
	$head[] = __( 'All clicks', 'oria' );
	$head[] = __( 'Clicks per 100 views', 'oria' );
	$head[] = __( 'URL', 'oria' );
	fputcsv( $out, $head );

	foreach ( $rows as $r ) {
		$line = array( $r['title'], $r['category'], $r['status'] );
		foreach ( $cols as $t ) {
			$line[] = $r['totals'][ $t ];
		}
		$line[] = $r['clicks'];
		$line[] = null === $r['rate'] ? '' : $r['rate'];
		$line[] = (string) get_permalink( $r['id'] );
		fputcsv( $out, $line );
	}

	fclose( $out );
	exit;
}
