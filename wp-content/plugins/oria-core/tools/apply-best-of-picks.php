<?php
/**
 * Write the picks in data/best-of-picks.json into their Best Of guides.
 *
 * The guides themselves come from seed-best-of.php; this fills their Picks
 * tab. Listings are named by slug, so the file reads as a reviewable list
 * and survives a listing's ID changing between local and production.
 *
 * SAFETY
 *   Dry run is the default and writes nothing. --apply writes.
 *   A guide that already has picks is left alone unless --replace is given.
 *   --publish also publishes each guide it writes to.
 *
 * USAGE (from the WordPress root, on the server)
 *     php wp-content/plugins/oria-core/tools/apply-best-of-picks.php
 *     php wp-content/plugins/oria-core/tools/apply-best-of-picks.php --apply --publish
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

if ( ! function_exists( 'update_field' ) ) {
	fwrite( STDERR, "ACF is not available.\n" );
	exit( 1 );
}

$argv    = $argv ?? array();
$apply   = in_array( '--apply', $argv, true );
$replace = in_array( '--replace', $argv, true );
$publish = in_array( '--publish', $argv, true );
$file    = ORIA_CORE_DIR . 'data/best-of-picks.json';
$data    = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;

if ( ! is_array( $data['guides'] ?? null ) ) {
	fwrite( STDERR, "Cannot read {$file}\n" );
	exit( 1 );
}

$written = 0;

foreach ( $data['guides'] as $g ) {
	$slug  = (string) ( $g['slug'] ?? '' );
	$guide = get_page_by_path( $slug, OBJECT, 'best_of' );
	if ( ! $guide instanceof WP_Post ) {
		printf( "NOT FOUND  guide /best/%s/ — run seed-best-of.php --apply first\n\n", $slug );
		continue;
	}
	printf( "%s  (post %d, %s)\n", $guide->post_title, $guide->ID, $guide->post_status );

	$rows    = array();
	$missing = 0;
	foreach ( (array) ( $g['picks'] ?? array() ) as $i => $p ) {
		$lslug   = (string) ( $p['listing'] ?? '' );
		$listing = get_page_by_path( $lslug, OBJECT, 'listing' );
		if ( ! $listing instanceof WP_Post || 'publish' !== $listing->post_status ) {
			printf( "  %d. MISSING listing \"%s\" — skipped\n", $i + 1, $lslug );
			$missing++;
			continue;
		}
		$award = (string) ( $p['award'] ?? 'oria_pick' );
		if ( ! isset( \Oria\Core\BestOf\AWARDS[ $award ] ) ) {
			printf( "  %d. unknown award \"%s\" for %s — using Oria Haven pick\n", $i + 1, $award, $lslug );
			$award = 'oria_pick';
		}
		$rows[] = array(
			'listing'     => $listing->ID,
			'award'       => $award,
			'award_label' => (string) ( $p['award_label'] ?? '' ),
			'reason'      => (string) ( $p['reason'] ?? '' ),
			'best_for'    => (string) ( $p['best_for'] ?? '' ),
			'highlights'  => (string) ( $p['highlights'] ?? '' ),
			'lead_badge'  => empty( $p['lead_badge'] ) ? 0 : 1,
			'price_note'  => (string) ( $p['price_note'] ?? '' ),
			'fact_label'  => (string) ( $p['fact'] ?? '' ),
			'spotlight'   => (string) ( $p['spotlight'] ?? '' ),
			'sessions'    => (string) ( $p['sessions'] ?? '' ),
			'rebate'      => (string) ( $p['rebate'] ?? '' ),
		);
		printf(
			"  %d. %-40s %s%s\n",
			count( $rows ),
			substr( $listing->post_title, 0, 40 ),
			\Oria\Core\BestOf\label( $award, (string) ( $p['award_label'] ?? '' ) ),
			empty( $p['lead_badge'] ) ? '' : '  (lead badge)'
		);
	}

	$have = count( (array) get_field( 'best_of_entries', $guide->ID ) );
	if ( $have && ! $replace ) {
		printf( "  has %d pick(s) already — left alone (pass --replace to overwrite)\n\n", $have );
		continue;
	}
	if ( ! $rows ) {
		echo "  nothing to write\n\n";
		continue;
	}
	if ( $missing ) {
		printf( "  %d listing(s) not found; the guide would be written without them\n", $missing );
	}

	// Guide-level fields that name listings (the decision block) or that the
	// picks file is the tidiest place for (category, quick answer).
	$choose = array();
	foreach ( (array) ( $g['choose'] ?? array() ) as $c ) {
		$l = get_page_by_path( (string) ( $c['listing'] ?? '' ), OBJECT, 'listing' );
		if ( $l instanceof WP_Post && '' !== trim( (string) ( $c['when'] ?? '' ) ) ) {
			$choose[] = array( 'listing' => $l->ID, 'when' => (string) $c['when'] );
		}
	}
	if ( $choose ) {
		printf( "  choose: %d line(s)
", count( $choose ) );
	}

	if ( $apply ) {
		update_field( 'best_of_entries', $rows, $guide->ID );
		if ( $choose ) {
			update_field( 'guide_choose', $choose, $guide->ID );
		}
		if ( ! empty( $g['category'] ) && isset( \Oria\Core\BestOf\CATEGORIES[ $g['category'] ] ) ) {
			update_field( 'guide_category', $g['category'], $guide->ID );
		}
		if ( ! empty( $g['quick_answer'] ) ) {
			update_field( 'quick_answer', (string) $g['quick_answer'], $guide->ID );
		}
		delete_transient( \Oria\Core\BestOf\INDEX_KEY );
		if ( $publish && 'publish' !== $guide->post_status ) {
			wp_update_post( array( 'ID' => $guide->ID, 'post_status' => 'publish' ) );
			echo "  published\n";
		}
		clean_post_cache( $guide->ID );
		$written++;
		printf( "  wrote %d pick(s)\n", count( $rows ) );
	}
	echo "\n";
}

if ( $apply ) {
	printf( "%d guide(s) written. Purge the page cache: wp litespeed-purge all\n", $written );
} else {
	echo "Dry run: nothing written. Re-run with --apply to write (add --publish to publish the guides).\n";
}
