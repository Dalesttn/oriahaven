<?php
/**
 * Listings → Area coverage: which suburbs carry a page, and which don't.
 *
 * The threshold in AreaDepth is invisible from the outside — a suburb page
 * quietly stops being indexable and nothing says so. This is the screen
 * that says so, and it doubles as the import plan: the suburbs one or two
 * listings short of the line are the cheapest pages on the site to switch
 * back on, and they are worth knowing by name.
 */

declare(strict_types=1);

namespace Oria\Core\AreaCoverage;

use Oria\Core\AreaDepth;
use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SLUG = 'oria-area-coverage';

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
}

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . PostTypes\LISTING,
		__( 'Area coverage', 'oria' ),
		__( 'Area coverage', 'oria' ),
		'manage_options',
		SLUG,
		__NAMESPACE__ . '\render'
	);
}

/**
 * Every suburb with its listing count, deepest first.
 *
 * Regions are left out: they aggregate their children and are never the
 * thing you would import to fix.
 *
 * @return array<int, array{term: \WP_Term, n: int}>
 */
function suburbs(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => Taxonomies\AREA,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$rows = array();
	foreach ( $terms as $term ) {
		if ( 0 === (int) $term->parent ) {
			continue;
		}
		$rows[] = array( 'term' => $term, 'n' => AreaDepth\depth( (int) $term->term_id ) );
	}

	usort( $rows, static fn( array $a, array $b ): int => $b['n'] <=> $a['n'] );
	return $rows;
}

function render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$min  = AreaDepth\minimum();
	$rows = suburbs();

	$live  = array_values( array_filter( $rows, static fn( array $r ): bool => $r['n'] >= $min ) );
	$close = array_values( array_filter( $rows, static fn( array $r ): bool => $r['n'] > 0 && $r['n'] < $min ) );
	$empty = array_values( array_filter( $rows, static fn( array $r ): bool => 0 === $r['n'] ) );

	echo '<div class="wrap"><h1>' . esc_html__( 'Area coverage', 'oria' ) . '</h1>';

	printf(
		'<p class="description" style="max-width:74ch">%s</p>',
		esc_html(
			sprintf(
				/* translators: %d: minimum listings */
				__( 'A suburb page is indexable once it has %d listings, and carries a noindex until then — an empty page in the sitemap costs the whole site crawl priority, not just itself. The count is read live, so a page comes back the moment the listings exist. Nothing here needs pressing.', 'oria' ),
				$min
			)
		)
	);

	printf(
		'<p style="font-size:15px"><strong>%s</strong> &nbsp;·&nbsp; <strong>%s</strong> &nbsp;·&nbsp; <strong>%s</strong></p>',
		esc_html( sprintf( _n( '%d suburb indexed', '%d suburbs indexed', count( $live ), 'oria' ), count( $live ) ) ),
		esc_html( sprintf( _n( '%d needs more', '%d need more', count( $close ), 'oria' ), count( $close ) ) ),
		esc_html( sprintf( _n( '%d empty', '%d empty', count( $empty ), 'oria' ), count( $empty ) ) )
	);

	if ( $close ) {
		echo '<h2>' . esc_html__( 'Closest to earning a page', 'oria' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'These already have practices. Each one is a page you get back for very little importing.', 'oria' ) . '</p>';
		table( $close, $min );
	}

	echo '<h2>' . esc_html__( 'Indexed', 'oria' ) . '</h2>';
	table( $live, $min );

	if ( $empty ) {
		echo '<h2>' . esc_html__( 'Nothing listed yet', 'oria' ) . '</h2>';
		echo '<p class="description" style="max-width:74ch">' . esc_html__( 'Not a fault list — a coverage map. Some of these suburbs may simply not have three wellness businesses, and those pages staying unpublished is the right outcome rather than a problem to solve.', 'oria' ) . '</p>';
		table( $empty, $min );
	}

	echo '</div>';
}

/** @param array<int, array{term: \WP_Term, n: int}> $rows */
function table( array $rows, int $min ): void {
	echo '<table class="widefat striped" style="max-width:64ch"><thead><tr>';
	echo '<th>' . esc_html__( 'Suburb', 'oria' ) . '</th>';
	echo '<th style="width:8em">' . esc_html__( 'Listings', 'oria' ) . '</th>';
	echo '<th style="width:12em">' . esc_html__( 'In the index', 'oria' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		$n    = $row['n'];
		$term = $row['term'];
		$need = $min - $n;

		printf(
			'<tr><td><a href="%s">%s</a></td><td>%d</td><td>%s</td></tr>',
			esc_url( (string) get_term_link( $term ) ),
			esc_html( $term->name ),
			$n,
			$n >= $min
				? '<span style="color:#1a7a3f">' . esc_html__( 'Yes', 'oria' ) . '</span>'
				: '<span style="color:#8a6d00">' . esc_html(
					sprintf(
						/* translators: %d: how many more listings are needed */
						_n( '%d more needed', '%d more needed', $need, 'oria' ),
						$need
					)
				) . '</span>'
		);
	}

	echo '</tbody></table>';
}
