<?php
/**
 * What is actually true of the places on this page.
 *
 * A category-and-suburb page -- beauty in Claremont, massage in Joondalup --
 * carried the CATEGORY's introduction, the same few hundred words on every
 * suburb of that category. Measured across the 51 indexable combos: 89% of
 * five-word sequences appear on more than one page, siblings run 76% alike
 * against 27% for unrelated pages, and the worst pages are 3% unique. The
 * prose was about the modality, which does not vary by suburb, so neither
 * did the page.
 *
 * These sentences do. They are counted from the listings the page is
 * actually showing, so Claremont and Cottesloe differ because their
 * practices differ, and they change on their own as listings are added.
 *
 * WHAT IT WILL NOT SAY. Every sentence is guarded by whether the data
 * behind it exists in THIS set, measured across the 441 published listings
 * first:
 *
 *   specialty  90%  |  service 87%  |  price_band 76%  |  format 100%
 *   group_size 44%  |  price_from 30%
 *   opening_hours 0.2%  |  booking_url 0%  |  audience 6%
 *
 * So there is nothing here about opening hours, booking links or who a
 * place suits, however useful those would be -- the fields are empty, and a
 * sentence counted from three filled rows out of forty is a guess wearing a
 * number. A fact appears when it can be counted and stays away when it
 * cannot; a page with nothing countable gets no block rather than filler.
 *
 * AND IT SAYS "LIST", NOT "OFFER". An empty taxonomy on this site means
 * nobody has been asked, never "no" -- the rule data/amenities.json states
 * and every other surface keeps. "3 of the 4 list facials" is a count of
 * what practices have published. "3 of the 4 offer facials" would be a
 * claim about the fourth, which nothing here knows.
 *
 * @package OriaCore
 */

declare(strict_types=1);

namespace Oria\Core\LocalFacts;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A field has to be filled on at least this much of the set before it is
 * counted from. Below it, "2 of 7" says more about the empty five than
 * about the two.
 */
const COVERAGE = 0.5;

/** Below this there is no "some of them" to observe. */
const MIN_SET = 3;

/**
 * The listings a category-and-suburb page is showing.
 *
 * @return array<int, int>
 */
function listings( \WP_Term $practice, \WP_Term $area ): array {
	$q = new \WP_Query(
		array(
			'post_type'              => 'listing',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => 100,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'tax_query'              => array(
				array( 'taxonomy' => Taxonomies\PRACTICE, 'field' => 'term_id', 'terms' => $practice->term_id ),
				array( 'taxonomy' => Taxonomies\AREA, 'field' => 'term_id', 'terms' => $area->term_id ),
			),
		)
	);

	return array_map( 'intval', (array) $q->posts );
}

/**
 * Countable observations about one category in one suburb.
 *
 * @return array<int, string> Sentences, or an empty array when nothing can
 *                            be counted honestly.
 */
function for_combo( ?\WP_Term $practice, ?\WP_Term $area ): array {
	if ( ! $practice instanceof \WP_Term || ! $area instanceof \WP_Term ) {
		return array();
	}

	$ids   = listings( $practice, $area );
	$total = count( $ids );
	if ( $total < MIN_SET ) {
		return array();
	}

	$out  = array();
	$said = array(); // Term names already used, so the two taxonomies cannot say it twice.

	foreach ( array( Taxonomies\SPECIALTY, 'service' ) as $tax ) {
		$line = shared_term( $ids, $total, $tax, $practice->name, $said );
		if ( '' !== $line ) {
			$out[] = $line;
		}
		if ( count( $out ) >= 2 ) {
			break; // Two is a texture; five is a spreadsheet.
		}
	}

	foreach ( array( 'price', 'online', 'group' ) as $kind ) {
		$line = ( 'price' === $kind ) ? price( $ids, $total ) : ( ( 'online' === $kind ) ? online( $ids, $total ) : group( $ids, $total ) );
		if ( '' !== $line ) {
			$out[] = $line;
		}
	}

	return $out;
}

/**
 * The thing most of them have in common, named.
 *
 * Two terms are refused however popular they are. One that merely echoes
 * the category -- "massage" on the Massage & Bodywork page -- is the page's
 * own title read back, and one already said under the other taxonomy would
 * be the same sentence twice, since specialty and service share names.
 *
 * "1 of the 8" is refused too: that is a fact about one business rather
 * than about the suburb. "All 4" is kept, because which thing all of them
 * share is exactly what differs between suburbs.
 *
 * @param array<int, string> $said Term names already used, by reference.
 */
function shared_term( array $ids, int $total, string $tax, string $category = '', array &$said = array() ): string {
	if ( ! taxonomy_exists( $tax ) ) {
		return '';
	}

	$counts  = array();
	$names   = array();
	$covered = 0;

	foreach ( $ids as $id ) {
		$terms = get_the_terms( $id, $tax );
		if ( ! is_array( $terms ) || ! $terms ) {
			continue;
		}
		++$covered;
		foreach ( $terms as $t ) {
			$counts[ $t->term_id ] = ( $counts[ $t->term_id ] ?? 0 ) + 1;
			$names[ $t->term_id ]  = function_exists( '\Oria\Theme\tname' ) ? \Oria\Theme\tname( $t ) : $t->name;
		}
	}

	if ( ! $counts || $covered / $total < COVERAGE ) {
		return '';
	}

	arsort( $counts );

	$cat = strtolower( trim( $category ) );
	foreach ( $counts as $id => $n ) {
		$name = strtolower( trim( (string) $names[ $id ] ) );

		if ( $n < 2 || isset( $said[ $name ] ) ) {
			continue;
		}
		// The category saying its own name back.
		if ( '' !== $cat && ( false !== strpos( $cat, $name ) || false !== strpos( $name, $cat ) ) ) {
			continue;
		}

		$said[ $name ] = true;

		if ( $n >= $total ) {
			return sprintf(
				/* translators: 1: total on the page, 2: what they all list, e.g. facials */
				__( 'All %1$d list %2$s.', 'oria' ),
				$total,
				$name
			);
		}

		return sprintf(
			/* translators: 1: number of practices, 2: total on the page, 3: what they list, e.g. facials */
			__( '%1$d of the %2$d list %3$s.', 'oria' ),
			$n,
			$total,
			$name
		);
	}

	return '';
}

/**
 * What it costs here, from published prices only.
 *
 * The lowest published price, and how many published one -- never an
 * average across a field two thirds of listings leave empty, and never a
 * figure presented as the suburb's going rate.
 */
function price( array $ids, int $total ): string {
	$prices = array();
	foreach ( $ids as $id ) {
		$p = (int) get_post_meta( $id, 'price_from', true );
		if ( $p > 0 ) {
			$prices[] = $p;
		}
	}

	$n = count( $prices );
	if ( $n < 2 || $n / $total < COVERAGE ) {
		return '';
	}

	sort( $prices );
	$low  = (int) $prices[0];
	$high = (int) $prices[ $n - 1 ];

	if ( $low === $high ) {
		return sprintf(
			/* translators: 1: price, 2: how many published a price, 3: total */
			__( 'The %2$d that publish a price all start at $%1$d.', 'oria' ),
			$low,
			$n,
			$total
		);
	}

	return sprintf(
		/* translators: 1: lowest price, 2: highest, 3: how many published a price */
		__( 'Published prices start between $%1$d and $%2$d, across the %3$d that list one.', 'oria' ),
		$low,
		$high,
		$n
	);
}

/** How many are not only in the room. `format` is filled on every listing. */
function online( array $ids, int $total ): string {
	$n = 0;
	foreach ( $ids as $id ) {
		$f = (string) get_post_meta( $id, 'format', true );
		if ( '' !== $f && 'in-person' !== $f ) {
			++$n;
		}
	}

	if ( $n < 1 || $n >= $total ) {
		return '';
	}

	return sprintf(
		/* translators: 1: number of practices, 2: total on the page */
		_n( '%1$d of the %2$d also works online.', '%1$d of the %2$d also work online.', $n, 'oria' ),
		$n,
		$total
	);
}

/** One-to-one or a room full, where enough of them say. */
function group( array $ids, int $total ): string {
	$solo    = 0;
	$covered = 0;

	foreach ( $ids as $id ) {
		$g = trim( (string) get_post_meta( $id, 'group_size', true ) );
		if ( '' === $g ) {
			continue;
		}
		++$covered;
		if ( preg_match( '/one|1\s*[-to]*\s*1|solo|individual|private/i', $g ) ) {
			++$solo;
		}
	}

	if ( $covered / $total < COVERAGE || $solo < 1 || $solo >= $total ) {
		return '';
	}

	return sprintf(
		/* translators: 1: number of practices, 2: total on the page */
		_n( '%1$d of the %2$d is one-to-one.', '%1$d of the %2$d are one-to-one.', $solo, 'oria' ),
		$solo,
		$total
	);
}

/**
 * The opening paragraph of a category guide, for a suburb page.
 *
 * A category-and-suburb page used to carry the whole guide -- the same two
 * or three hundred words in every suburb of that category, which is most of
 * what made the pages alike. One paragraph still orients somebody who
 * arrived from a search; the rest belongs on the category page, where it is
 * written once and linked to from here.
 */
function first_para( string $html ): string {
	if ( ! preg_match( '/<p\b[^>]*>.*?<\/p>/is', $html, $m ) ) {
		return $html;
	}

	return (string) $m[0];
}
