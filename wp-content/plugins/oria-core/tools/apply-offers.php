<?php
/**
 * Write the advertised offers in data/offers.json onto their listings.
 *
 * Each row names a listing by slug and the offer as read on the business's
 * own site: title, details, the page it is on, the date it was checked and,
 * where the site states one, the date it ends. The file is the record; this
 * copies it onto the listing's offer fields (Theme\active_offer shows it,
 * labelled as advertised, and Offers\sweep removes it the day after it ends).
 *
 * RULES
 *   - A claimed listing is never touched: its owner runs their own offer.
 *   - A listing whose offer was typed by an owner (title set, no source) is
 *     never overwritten.
 *   - Rows marked "removed": true clear the offer from the listing.
 *   - Idempotent: a second run reports "unchanged" for every row already on.
 *
 * SAFETY
 *   Dry run is the default and writes nothing. --apply writes.
 *
 * USAGE (from the WordPress root, on the server)
 *     php wp-content/plugins/oria-core/tools/apply-offers.php
 *     php wp-content/plugins/oria-core/tools/apply-offers.php --apply
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

$apply = in_array( '--apply', $argv ?? array(), true );
$file  = ORIA_CORE_DIR . 'data/offers.json';
$data  = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;

if ( ! is_array( $data['offers'] ?? null ) ) {
	fwrite( STDERR, "Cannot read {$file}\n" );
	exit( 1 );
}

$n = array( 'written' => 0, 'unchanged' => 0, 'cleared' => 0, 'skipped' => 0 );
echo ( $apply ? 'APPLY' : 'DRY RUN' ) . "\n\n";

foreach ( $data['offers'] as $o ) {
	$slug = (string) ( $o['listing'] ?? '' );
	$post = '' !== $slug ? get_page_by_path( $slug, OBJECT, 'listing' ) : null;
	if ( ! $post instanceof WP_Post ) {
		printf( "NOT FOUND  %s\n", $slug );
		++$n['skipped'];
		continue;
	}
	$id      = $post->ID;
	$status  = (string) get_post_meta( $id, 'claim_status', true );
	$claimed = 'unclaimed' !== $status || (int) get_post_meta( $id, 'claimed_by', true );
	$have    = array();
	foreach ( \Oria\Core\Offers\FIELDS as $f ) {
		$have[ $f ] = (string) get_field( $f, $id );
	}
	$have_extra = (string) get_post_meta( $id, \Oria\Core\Offers\META, true );

	if ( $claimed ) {
		printf( "SKIP       %s -- claimed; the owner runs this listing's offer\n", $post->post_title );
		++$n['skipped'];
		continue;
	}
	if ( '' !== $have['offer_title'] && '' === $have['offer_source'] ) {
		printf( "SKIP       %s -- an owner-typed offer is already on it\n", $post->post_title );
		++$n['skipped'];
		continue;
	}

	if ( ! empty( $o['removed'] ) ) {
		if ( '' === $have['offer_title'] ) {
			printf( "=          %s -- already clear\n", $post->post_title );
			++$n['unchanged'];
			continue;
		}
		printf( "CLEAR      %s -- \"%s\"\n", $post->post_title, $have['offer_title'] );
		if ( $apply ) {
			foreach ( \Oria\Core\Offers\FIELDS as $f ) {
				update_field( $f, '', $id );
			}
			delete_post_meta( $id, \Oria\Core\Offers\META );
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
		printf( "SKIP       %s -- row needs a title and a source_url\n", $post->post_title );
		++$n['skipped'];
		continue;
	}
	if ( '' !== $want['offer_until'] && $want['offer_until'] < current_time( 'Y-m-d' ) ) {
		printf( "SKIP       %s -- ended %s; mark the row removed\n", $post->post_title, $want['offer_until'] );
		++$n['skipped'];
		continue;
	}
	/*
	 * The structured part the listing card shows -- kind, price, basis,
	 * inclusions, eligibility, terms -- as one JSON meta beside the ACF
	 * fields. Only what the row states; nothing is derived from the prose.
	 */
	$extra = \Oria\Core\Offers\extra_from( $o );
	$extra_json = $extra ? (string) wp_json_encode( $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '';

	// ACF stores dates as Ymd; compare on the normalised form.
	$same = $extra_json === $have_extra;
	foreach ( $want as $f => $v ) {
		if ( str_replace( '-', '', $have[ $f ] ) !== str_replace( '-', '', $v ) ) {
			$same = false;
		}
	}
	if ( $same ) {
		printf( "=          %s\n", $post->post_title );
		++$n['unchanged'];
		continue;
	}
	printf( "WRITE      %s\n             %s%s\n", $post->post_title, $want['offer_title'], '' !== $want['offer_until'] ? " (until {$want['offer_until']})" : '' );
	if ( $apply ) {
		foreach ( $want as $f => $v ) {
			update_field( $f, $v, $id );
		}
		if ( '' !== $extra_json ) {
			update_post_meta( $id, \Oria\Core\Offers\META, $extra_json );
		} else {
			delete_post_meta( $id, \Oria\Core\Offers\META );
		}
	}
	++$n['written'];
}

printf( "\n%s: %d written, %d unchanged, %d cleared, %d skipped.\n", $apply ? 'Done' : 'Dry run', $n['written'], $n['unchanged'], $n['cleared'], $n['skipped'] );
if ( $apply ) {
	echo "Purge the page cache: wp litespeed-purge all\n";
}
