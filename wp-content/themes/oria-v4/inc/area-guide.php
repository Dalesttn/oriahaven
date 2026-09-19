<?php
/**
 * Area pages, "The Local Rhythm" (oria-v4/taxonomy-area.php).
 *
 * Everything the area template says about a place is worked out here, from
 * the directory's own listings, so the template only lays it out. Two
 * sources and never a third:
 *
 *   1. The live listings (Faq\matching(), the same rows the answer block and
 *      the FAQ read) -- counts, the practices a place leans towards, hours,
 *      prices, where the addresses sit. Nothing is stored; a new listing
 *      changes the page on the next request.
 *   2. assets/data/area-guides.json -- the editorial layer: a place's own
 *      photograph, its one-line character, the human-written introduction,
 *      getting around, walks for the reset plans. Hand-written, reviewed,
 *      dated. A data refresh never touches it.
 *
 * Every observation has a floor (MIN_PATTERN listings) below which it is
 * not drawn at all: a pattern in three listings is an anecdote. No health,
 * demographic or "affordable" claims -- prices are reported as published
 * figures with their count, never characterised.
 *
 * @package Oria
 */

declare(strict_types=1);

namespace Oria\V4\Area;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Listings needed before the page draws a pattern from them (brief 14). */
const MIN_PATTERN = 5;

/** Published prices needed before a median is shown. */
const MIN_PRICES = 5;

/** Street addresses needed before "within a kilometre of the station". */
const MIN_ADDRESSED = 5;

/**
 * The page's title and description, in the redesign's own words.
 *
 * "Wellness in Fremantle — 22 Places to Explore". Only where Yoast has no
 * hand-written title for the term, and the count only on a page that is
 * indexable -- a noindexed suburb with one listing does not need to
 * advertise the number in a result nobody will see.
 */
function bootstrap(): void {
	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 30 );
	add_filter( 'wpseo_opengraph_title', __NAMESPACE__ . '\title', 30 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description', 30 );
	add_filter( 'wpseo_opengraph_desc', __NAMESPACE__ . '\description', 30 );
}

/** The area term being viewed, or null. */
function term(): ?\WP_Term {
	if ( ! is_tax( 'area' ) ) {
		return null;
	}
	$t = get_queried_object();
	return $t instanceof \WP_Term ? $t : null;
}

/** Whether Yoast holds a hand-written value for this term. */
function has_override( \WP_Term $term, string $key ): bool {
	return function_exists( '\Oria\Core\Seo\term_override' ) && '' !== \Oria\Core\Seo\term_override( $term, $key );
}

function indexable( \WP_Term $term ): bool {
	return ! function_exists( '\Oria\Core\AreaDepth\is_thin' ) || ! \Oria\Core\AreaDepth\is_thin( (int) $term->term_id );
}

function title( $title ) {
	$term = term();
	if ( ! $term || has_override( $term, 'wpseo_title' ) ) {
		return $title;
	}
	$n    = count( rows( $term ) );
	$name = \Oria\Theme\tname( $term );
	return indexable( $term ) && $n > 1
		/* translators: 1: area, 2: number of places, 3: site name */
		? sprintf( __( 'Wellness in %1$s — %2$d Places to Explore | %3$s', 'oria' ), $name, $n, get_bloginfo( 'name' ) )
		/* translators: 1: area, 2: site name */
		: sprintf( __( 'Wellness in %1$s | %2$s', 'oria' ), $name, get_bloginfo( 'name' ) );
}

function description( $desc ) {
	$term = term();
	if ( ! $term || has_override( $term, 'wpseo_metadesc' ) ) {
		return $desc;
	}
	$rows = rows( $term );
	$n    = count( $rows );
	if ( $n < 2 ) {
		return $desc;
	}
	// The three practices this place has most of, so no two areas share one
	// template sentence (brief 21). Top-level only: a category beside its own
	// sub-category ("Retreats, Nature & Experiences and Nature & Outdoor
	// Wellness") says the same thing twice.
	$names = array();
	foreach ( array_keys( practice_counts( $rows ) ) as $slug ) {
		$t = get_term_by( 'slug', (string) $slug, 'practice' );
		if ( $t instanceof \WP_Term && 0 === (int) $t->parent ) {
			$names[] = \Oria\Theme\tname( $t );
		}
		if ( count( $names ) >= 3 ) {
			break;
		}
	}
	$list = count( $names ) > 1 ? implode( ', ', array_slice( $names, 0, -1 ) ) . ' ' . __( 'and', 'oria' ) . ' ' . end( $names ) : (string) ( $names[0] ?? '' );
	return sprintf(
		/* translators: 1: count, 2: area, 3: list of practices */
		__( 'Explore %1$d hand-checked wellness places in %2$s, including %3$s. Compare prices, reviews and what each place is like.', 'oria' ),
		$n,
		\Oria\Theme\tname( $term ),
		$list
	);
}

/* ----------------------------------------------------------------- data */

/**
 * The listings on this area's page, as the directory index rows.
 *
 * @return list<array<string, mixed>>
 */
function rows( \WP_Term $term ): array {
	static $cache = array();
	if ( ! isset( $cache[ $term->term_id ] ) ) {
		/*
		 * A city term (/area/margaret-river/) is neither a suburb nor a
		 * region, and Faq\matching() compares it against region slugs and
		 * finds almost nothing. Its listings are the city's.
		 */
		if ( function_exists( '\Oria\Core\Taxonomies\is_city' ) && \Oria\Core\Taxonomies\is_city( $term ) ) {
			$cache[ $term->term_id ] = city_rows( $term->slug );
		} else {
			$cache[ $term->term_id ] = function_exists( '\Oria\Core\Faq\matching' ) ? array_values( (array) \Oria\Core\Faq\matching( $term ) ) : array();
		}
	}
	return $cache[ $term->term_id ];
}

/**
 * Every listing in the same city, for "x of Perth's y".
 *
 * @return list<array<string, mixed>>
 */
function city_rows( string $city ): array {
	static $cache = array();
	if ( ! isset( $cache[ $city ] ) ) {
		$cache[ $city ] = array_values(
			array_filter(
				(array) ( \Oria\Theme\listing_data()['listings'] ?? array() ),
				static fn( array $l ): bool => '' === $city || ( $l['city'] ?? '' ) === $city
			)
		);
	}
	return $cache[ $city ];
}

/** Whether a row belongs to a practice (its primary, or filed under it too). */
function in_practice( array $row, string $slug ): bool {
	return ( $row['cat'] ?? '' ) === $slug || in_array( $slug, (array) ( $row['also'] ?? array() ), true );
}

/**
 * Listings per practice (top-level and sub-categories), most first.
 *
 * @param list<array<string, mixed>> $rows
 * @return array<string, int>
 */
function practice_counts( array $rows ): array {
	$n = array();
	foreach ( $rows as $r ) {
		foreach ( array_unique( array_merge( array( (string) ( $r['cat'] ?? '' ) ), (array) ( $r['also'] ?? array() ) ) ) as $slug ) {
			if ( '' !== $slug ) {
				$n[ $slug ] = ( $n[ $slug ] ?? 0 ) + 1;
			}
		}
	}
	arsort( $n );
	return $n;
}

/** The editorial layer (assets/data/area-guides.json). */
function config(): array {
	static $cfg = null;
	if ( null === $cfg ) {
		$file = get_stylesheet_directory() . '/assets/data/area-guides.json';
		$cfg  = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file
		$cfg  = is_array( $cfg ) ? $cfg : array();
	}
	return $cfg;
}

/** This area's editorial entry, or an empty array. */
function guide( \WP_Term $term ): array {
	return (array) ( config()['areas'][ $term->slug ] ?? array() );
}

/**
 * The hero photograph: the area's own, else its region's, else its city's.
 * Never a stock picture of somewhere else wearing this place's name.
 *
 * @return array{wide: string, mid: string, phone: string, pos: string, alt: string, credit: string, own: bool}
 */
function hero( \WP_Term $term, ?\WP_Term $region, ?array $city ): array {
	$chain = array( $term->slug );
	if ( $region ) {
		$chain[] = $region->slug;
	}
	foreach ( $chain as $i => $slug ) {
		$img = (array) ( config()['areas'][ $slug ]['image'] ?? array() );
		if ( ! empty( $img['wide'] ) ) {
			return array(
				'wide'   => get_theme_file_uri( 'assets/img/' . $img['wide'] ),
				'mid'    => ! empty( $img['mid'] ) ? get_theme_file_uri( 'assets/img/' . $img['mid'] ) : '',
				'phone'  => ! empty( $img['phone'] ) ? get_theme_file_uri( 'assets/img/' . $img['phone'] ) : '',
				'pos'    => (string) ( $img['pos'] ?? '50% 50%' ),
				'alt'    => 0 === $i ? (string) ( $img['alt'] ?? '' ) : '',
				'credit' => (string) ( $img['credit'] ?? '' ),
				'own'    => 0 === $i,
			);
		}
	}
	$by_city = array( 'margaret-river' => array( 'scene-margaret-river-coast.webp', '50% center' ) );
	$pick    = $by_city[ (string) ( $city['slug'] ?? '' ) ] ?? array( 'scene-perth-skyline.webp', '58% center' );
	return array(
		'wide'   => get_theme_file_uri( 'assets/img/' . $pick[0] ),
		'mid'    => '',
		'phone'  => '',
		'pos'    => $pick[1],
		'alt'    => '',
		'credit' => '',
		'own'    => false,
	);
}

/** Median of a list of numbers. */
function median( array $v ): float {
	sort( $v );
	$c = count( $v );
	if ( ! $c ) {
		return 0.0;
	}
	$m = intdiv( $c, 2 );
	return $c % 2 ? (float) $v[ $m ] : ( $v[ $m - 1 ] + $v[ $m ] ) / 2;
}

/** Straight-line distance in kilometres. */
function km( float $la1, float $lo1, float $la2, float $lo2 ): float {
	$r  = 6371.0;
	$p1 = deg2rad( $la1 );
	$p2 = deg2rad( $la2 );
	$dp = deg2rad( $la2 - $la1 );
	$dl = deg2rad( $lo2 - $lo1 );
	$a  = sin( $dp / 2 ) ** 2 + cos( $p1 ) * cos( $p2 ) * sin( $dl / 2 ) ** 2;
	return 2 * $r * asin( min( 1.0, sqrt( $a ) ) );
}

/** "north", "south-east" -- where B lies from A. */
function direction( float $la1, float $lo1, float $la2, float $lo2 ): string {
	$y   = sin( deg2rad( $lo2 - $lo1 ) ) * cos( deg2rad( $la2 ) );
	$x   = cos( deg2rad( $la1 ) ) * sin( deg2rad( $la2 ) ) - sin( deg2rad( $la1 ) ) * cos( deg2rad( $la2 ) ) * cos( deg2rad( $lo2 - $lo1 ) );
	$deg = fmod( rad2deg( atan2( $y, $x ) ) + 360.0, 360.0 );
	$names = array( __( 'north', 'oria' ), __( 'north-east', 'oria' ), __( 'east', 'oria' ), __( 'south-east', 'oria' ), __( 'south', 'oria' ), __( 'south-west', 'oria' ), __( 'west', 'oria' ), __( 'north-west', 'oria' ) );
	return $names[ (int) round( $deg / 45 ) % 8 ];
}

/**
 * Opening-hours facts from the cached Google periods: which listings publish
 * hours, which stay open past 7pm on some day, which open at the weekend.
 *
 * @param list<array<string, mixed>> $rows
 * @return array{known: int, evening: list<int>, weekend: list<int>}
 */
function hours( array $rows ): array {
	$out = array( 'known' => 0, 'evening' => array(), 'weekend' => array() );
	if ( ! function_exists( '\Oria\Core\Places\data_for' ) ) {
		return $out;
	}
	foreach ( $rows as $r ) {
		$id = post_id( $r );
		if ( ! $id ) {
			continue;
		}
		$d = \Oria\Core\Places\data_for( $id, false ); // cache only: never an API call from a page view
		if ( empty( $d['periods'] ) || ! is_array( $d['periods'] ) ) {
			continue;
		}
		++$out['known'];
		$eve = false;
		$wkd = false;
		foreach ( $d['periods'] as $p ) {
			if ( empty( $p['close'] ) ) {
				$eve = true; // open around the clock
				$wkd = true;
				break;
			}
			$oday  = (int) ( $p['open']['day'] ?? -1 );
			$cday  = (int) ( $p['close']['day'] ?? -1 );
			$chour = (int) ( $p['close']['hour'] ?? 0 );
			if ( $chour >= 19 || $cday !== $oday ) {
				$eve = true;
			}
			if ( in_array( $oday, array( 0, 6 ), true ) ) {
				$wkd = true;
			}
		}
		if ( $eve ) {
			$out['evening'][] = $id;
		}
		if ( $wkd ) {
			$out['weekend'][] = $id;
		}
	}
	return $out;
}

/** A row's post id (rows carry the slug as id). */
function post_id( array $row ): int {
	static $ids = array();
	$slug = (string) ( $row['id'] ?? '' );
	if ( '' === $slug ) {
		return 0;
	}
	if ( ! isset( $ids[ $slug ] ) ) {
		$p            = get_page_by_path( $slug, OBJECT, 'listing' );
		$ids[ $slug ] = $p instanceof \WP_Post ? (int) $p->ID : 0;
	}
	return $ids[ $slug ];
}

/** A practice term's display name by slug. */
function pname( string $slug ): string {
	$t = get_term_by( 'slug', $slug, 'practice' );
	return $t instanceof \WP_Term ? \Oria\Theme\tname( $t ) : '';
}

/**
 * The practice this place leans towards most, measured against its city:
 * the one that makes up the biggest slice of this place compared with its
 * slice of the city, among practices with three or more listings here.
 * "Breathwork: 4 of the 21 listed across Perth." Null when nothing is at
 * least twice its city-wide share.
 *
 * @param list<array<string, mixed>> $rows
 * @return array{slug: string, n: int, of: int}|null
 */
function strongest( array $rows, string $city ): ?array {
	if ( count( $rows ) < MIN_PATTERN ) {
		return null;
	}
	$here  = practice_counts( $rows );
	$all   = practice_counts( city_rows( $city ) );
	$total = count( city_rows( $city ) );
	$best  = null;
	foreach ( $here as $slug => $n ) {
		$of = (int) ( $all[ $slug ] ?? 0 );
		if ( $n < 3 || $of < 5 || ! $total ) {
			continue;
		}
		/*
		 * Lift: how much bigger a slice of this place the practice is than
		 * of the city as a whole. Twice the city's share at least -- the
		 * biggest category everywhere is not what makes a place distinct.
		 */
		$lift = ( $n / count( $rows ) ) / ( $of / $total );
		if ( $lift < 2.0 ) {
			continue;
		}
		if ( ! $best || $lift > $best['lift'] || ( $lift === $best['lift'] && $n > $best['n'] ) ) {
			$best = array( 'slug' => (string) $slug, 'n' => (int) $n, 'of' => $of, 'lift' => $lift );
		}
	}
	if ( ! $best ) {
		return null;
	}
	unset( $best['lift'] );
	return $best;
}

/**
 * "At a glance": the page's figures as four short cards (brief 12).
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array{n: string, label: string}>
 */
function glance( array $rows, int $top_cats, ?array $strong, string $place_city ): array {
	$n     = count( $rows );
	$cards = array();
	/* translators: %s: number of places */
	$cards[] = array( 'n' => number_format_i18n( $n ), 'label' => _n( 'hand-checked place', 'hand-checked places', $n, 'oria' ) );
	$cards[] = array( 'n' => number_format_i18n( $top_cats ), 'label' => _n( 'kind of practice', 'kinds of practice', $top_cats, 'oria' ) );
	if ( $strong ) {
		$cards[] = array(
			'n'     => pname( $strong['slug'] ),
			/* translators: 1: count here, 2: count across the city, 3: city */
			'label' => sprintf( __( 'stands out here — %1$d of the %2$d listed across %3$s', 'oria' ), $strong['n'], $strong['of'], $place_city ),
			'word'  => true,
		);
	}
	$prices = prices( $rows );
	if ( count( $prices ) >= MIN_PRICES ) {
		$cards[] = array(
			'n'     => '$' . number_format_i18n( (int) round( median( $prices ) ) ),
			/* translators: %d: number of places with a published price */
			'label' => sprintf( __( 'middle published starting price, from %d places', 'oria' ), count( $prices ) ),
		);
	}
	$online = count( array_filter( $rows, static fn( array $r ): bool => in_array( $r['format'] ?? '', array( 'online', 'both' ), true ) ) );
	if ( $online >= 2 && count( $cards ) < 4 ) {
		$cards[] = array( 'n' => number_format_i18n( $online ), 'label' => __( 'also see people online', 'oria' ) );
	}
	return array_slice( $cards, 0, 4 );
}

/**
 * Published starting prices, dollars. 0 is "not published", not free.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<int>
 */
function prices( array $rows ): array {
	$out = array();
	foreach ( $rows as $r ) {
		$p = (int) ( $r['priceFrom'] ?? 0 );
		if ( $p > 0 ) {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * The Local Rhythm's data-backed observations (brief 14). Each one carries
 * its own count, and each has a floor below which it is left out.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<string>
 */
function rhythm_points( array $rows, ?array $strong, string $place, string $place_city, array $guide ): array {
	$n = count( $rows );
	if ( $n < MIN_PATTERN ) {
		return array();
	}
	$out = array();

	if ( $strong ) {
		$out[] = sprintf(
			/* translators: 1: practice, 2: count here, 3: count across the city, 4: city, 5: area */
			__( '%1$s is where %5$s stands out: %2$d of the %3$d %1$s practices listed across %4$s are here.', 'oria' ),
			pname( $strong['slug'] ),
			$strong['n'],
			$strong['of'],
			$place_city,
			$place
		);
	}

	// Where the addresses sit, against a named landmark from the guide.
	$anchor = (array) ( $guide['anchor'] ?? array() );
	if ( isset( $anchor['lat'], $anchor['lng'], $anchor['name'] ) ) {
		$addressed = array_filter( $rows, static fn( array $r ): bool => 'address' === ( $r['geo'] ?? '' ) && is_numeric( $r['lat'] ?? null ) && is_numeric( $r['lng'] ?? null ) );
		if ( count( $addressed ) >= MIN_ADDRESSED ) {
			$near = array_filter( $addressed, static fn( array $r ): bool => km( (float) $r['lat'], (float) $r['lng'], (float) $anchor['lat'], (float) $anchor['lng'] ) <= 1.0 );
			if ( count( $near ) * 2 >= count( $addressed ) ) {
				$out[] = sprintf(
					/* translators: 1: count near, 2: count with a street address, 3: landmark */
					__( '%1$d of the %2$d places with a street address on file are within a kilometre of %3$s, as the crow flies.', 'oria' ),
					count( $near ),
					count( $addressed ),
					(string) $anchor['name']
				);
			}
		}
	}

	$h = hours( $rows );
	if ( $h['known'] >= MIN_PATTERN ) {
		if ( count( $h['evening'] ) >= 2 ) {
			$out[] = sprintf(
				/* translators: 1: count, 2: count that publish hours */
				__( '%1$d of the %2$d places that publish their hours stay open past 7pm on at least one day.', 'oria' ),
				count( $h['evening'] ),
				$h['known']
			);
		}
		if ( count( $h['weekend'] ) >= 2 ) {
			$out[] = sprintf(
				/* translators: 1: count, 2: count that publish hours */
				__( '%1$d of those %2$d open on a Saturday or Sunday.', 'oria' ),
				count( $h['weekend'] ),
				$h['known']
			);
		}
	}

	$prices = prices( $rows );
	if ( count( $prices ) >= MIN_PRICES ) {
		$out[] = sprintf(
			/* translators: 1: lowest, 2: highest, 3: count with a price */
			__( 'Published starting prices run from $%1$d to $%2$d across the %3$d places that list one — each practice sets its own, and they change.', 'oria' ),
			min( $prices ),
			max( $prices ),
			count( $prices )
		);
	}

	$online = count( array_filter( $rows, static fn( array $r ): bool => in_array( $r['format'] ?? '', array( 'online', 'both' ), true ) ) );
	if ( $online >= 2 ) {
		$out[] = sprintf(
			/* translators: %d: count */
			_n( '%d place here also sees people online.', '%d places here also see people online.', $online, 'oria' ),
			$online
		);
	}

	return $out;
}

/**
 * Suburb centroids: the geocoder's own suburb point where a listing carries
 * one, else the mean of that suburb's street addresses.
 *
 * @return array<string, array{lat: float, lng: float}> keyed by suburb name
 */
function centroids( string $city ): array {
	$pts  = array();
	$subs = array();
	foreach ( city_rows( $city ) as $r ) {
		$s = (string) ( $r['suburb'] ?? '' );
		if ( '' === $s || ! is_numeric( $r['lat'] ?? null ) || ! is_numeric( $r['lng'] ?? null ) || 0.0 === (float) $r['lat'] ) {
			continue;
		}
		if ( 'suburb' === ( $r['geo'] ?? '' ) ) {
			$subs[ $s ] = array( 'lat' => (float) $r['lat'], 'lng' => (float) $r['lng'] );
		}
		$pts[ $s ][] = array( (float) $r['lat'], (float) $r['lng'] );
	}
	$out = $subs;
	foreach ( $pts as $s => $list ) {
		if ( ! isset( $out[ $s ] ) ) {
			$out[ $s ] = array(
				'lat' => array_sum( array_column( $list, 0 ) ) / count( $list ),
				'lng' => array_sum( array_column( $list, 1 ) ) / count( $list ),
			);
		}
	}
	return $out;
}

/**
 * Nearby areas worth going on to (brief 18): suburbs with enough listings to
 * be indexed, closest first, within 8 km -- or the guide's own hand-picked
 * list when it has one. Each with its direction, its count and the practice
 * it has most of.
 *
 * @return list<array{term: \WP_Term, n: int, dir: string, top: string}>
 */
function nearby( \WP_Term $term, string $city, array $guide, int $limit = 6 ): array {
	if ( ! function_exists( '\Oria\Core\Taxonomies\is_suburb' ) || ! \Oria\Core\Taxonomies\is_suburb( $term ) ) {
		return array();
	}
	$cent = centroids( $city );
	$me   = $cent[ \Oria\Theme\tname( $term ) ] ?? null;
	$min  = function_exists( '\Oria\Core\AreaDepth\minimum' ) ? \Oria\Core\AreaDepth\minimum() : 3;

	$picked = array_map( 'strval', (array) ( $guide['nearby'] ?? array() ) );
	$cands  = array();
	if ( $picked ) {
		foreach ( $picked as $slug ) {
			$t = get_term_by( 'slug', $slug, 'area' );
			if ( $t instanceof \WP_Term ) {
				$cands[] = $t;
			}
		}
	} else {
		foreach ( (array) get_terms( array( 'taxonomy' => 'area', 'hide_empty' => false ) ) as $t ) {
			if ( $t instanceof \WP_Term && $t->term_id !== $term->term_id && \Oria\Core\Taxonomies\is_suburb( $t ) ) {
				$cands[] = $t;
			}
		}
	}

	$out = array();
	foreach ( $cands as $t ) {
		$depth = function_exists( '\Oria\Core\AreaDepth\depth' ) ? \Oria\Core\AreaDepth\depth( (int) $t->term_id ) : 0;
		if ( $depth < 1 || ( ! $picked && $depth < $min ) ) {
			continue;
		}
		$c = $cent[ \Oria\Theme\tname( $t ) ] ?? null;
		$d = ( $me && $c ) ? km( $me['lat'], $me['lng'], $c['lat'], $c['lng'] ) : null;
		if ( ! $picked && ( null === $d || $d > 8.0 ) ) {
			continue;
		}
		$top   = practice_counts( rows( $t ) );
		$first = (string) array_key_first( $top );
		$out[] = array(
			'term' => $t,
			'n'    => $depth,
			'd'    => $d ?? 99.0,
			'dir'  => ( $me && $c && $d > 0.5 ) ? direction( $me['lat'], $me['lng'], $c['lat'], $c['lng'] ) : '',
			'top'  => '' !== $first ? pname( $first ) : '',
		);
	}
	if ( ! $picked ) {
		usort( $out, static fn( array $a, array $b ): int => $a['d'] <=> $b['d'] );
	}
	return array_slice( $out, 0, $limit );
}

/**
 * Browse by practice (brief 19): the practices with listings here, grouped
 * by what somebody wants from them, each linked to its final /explore/
 * address -- no redirect hop, and never to a combination with nothing in it.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array{name: string, links: list<array{url: string, name: string, n: int}>}>
 */
function practice_groups( \WP_Term $term, array $rows, ?array $city ): array {
	$counts = practice_counts( $rows );
	if ( ! $counts || ! function_exists( '\Oria\Core\PracticesIndex\category_url' ) ) {
		return array();
	}
	$groups = (array) ( config()['groups'] ?? array() );
	$is_city = function_exists( '\Oria\Core\Taxonomies\is_city' ) && \Oria\Core\Taxonomies\is_city( $term );

	$out = array();
	$put = array();
	foreach ( $groups as $g ) {
		$links = array();
		foreach ( (array) ( $g['cats'] ?? array() ) as $top ) {
			$top_t = get_term_by( 'slug', (string) $top, 'practice' );
			if ( ! $top_t instanceof \WP_Term ) {
				continue;
			}
			// The top-level category, then its sub-categories with listings here.
			$family = array( $top_t );
			foreach ( (array) get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false, 'parent' => (int) $top_t->term_id ) ) as $sub ) {
				if ( $sub instanceof \WP_Term ) {
					$family[] = $sub;
				}
			}
			foreach ( $family as $p ) {
				$n = (int) ( $counts[ $p->slug ] ?? 0 );
				if ( $n < 1 || isset( $put[ $p->slug ] ) ) {
					continue;
				}
				$put[ $p->slug ] = true;
				$base            = \Oria\Core\PracticesIndex\category_url( $p, $city );
				$links[]         = array(
					'url'  => $is_city ? $base : $base . $term->slug . '/',
					'name' => \Oria\Theme\tname( $p ),
					'n'    => $n,
					'sub'  => $p->parent > 0,
				);
			}
		}
		if ( $links ) {
			$out[] = array( 'name' => (string) ( $g['name'] ?? '' ), 'line' => (string) ( $g['line'] ?? '' ), 'links' => $links );
		}
	}
	return $out;
}

/**
 * "Plan a local reset" (brief 15): three example days, each stop a real
 * listing here (or a public place the guide names), with the other listings
 * that could take the same stop so a visitor can swap one out. Editorial
 * inspiration only -- no availability, no travel times, no outcomes.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array{name: string, line: string, stops: list<array<string, mixed>>}>
 */
function plans( array $rows, array $guide ): array {
	if ( count( $rows ) < MIN_PATTERN ) {
		return array();
	}
	$h       = hours( $rows );
	$evening = array_flip( $h['evening'] );
	$walks   = array_values( array_filter( (array) ( $guide['walks'] ?? array() ), static fn( $w ): bool => is_array( $w ) && ! empty( $w['label'] ) ) );

	// Members first, then the better-reviewed, then the name: the same order
	// every visitor sees on a given day.
	$rank = static function ( array $a, array $b ): int {
		$ma = 'unclaimed' === ( $a['status'] ?? 'unclaimed' ) ? 1 : 0;
		$mb = 'unclaimed' === ( $b['status'] ?? 'unclaimed' ) ? 1 : 0;
		$sa = (float) ( $a['rating'] ?? 0 ) * log( 2 + (int) ( $a['reviews'] ?? 0 ) );
		$sb = (float) ( $b['rating'] ?? 0 ) * log( 2 + (int) ( $b['reviews'] ?? 0 ) );
		return array( $ma, -$sb, (string) $a['name'] ) <=> array( $mb, -$sa, (string) $b['name'] );
	};
	/*
	 * The places that fit a stop. Those whose MAIN practice fits come first --
	 * a men's circle also filed under Experiences is not the obvious "go
	 * inward" -- then the rest, each group in the order above.
	 */
	$pick = static function ( array $cats, ?array $only = null ) use ( $rows, $rank ): array {
		$main = array();
		$also = array();
		foreach ( $rows as $r ) {
			if ( null !== $only && ! isset( $only[ post_id( $r ) ] ) ) {
				continue;
			}
			if ( in_array( (string) ( $r['cat'] ?? '' ), $cats, true ) ) {
				$main[] = $r;
				continue;
			}
			foreach ( $cats as $c ) {
				if ( in_practice( $r, $c ) ) {
					$also[] = $r;
					break;
				}
			}
		}
		usort( $main, $rank );
		usort( $also, $rank );
		return array_slice( array_merge( $main, $also ), 0, 5 );
	};

	$calm  = array( 'breathwork', 'meditation', 'mind', 'energy', 'sound', 'yoga' );
	$hands = array( 'bodywork', 'spa', 'recovery', 'float', 'natural' );
	$move  = array( 'yoga', 'fitness', 'pilates' );
	$food  = array( 'nutrition' );

	$defs = array(
		array(
			'name'  => __( 'A quiet morning', 'oria' ),
			'line'  => __( 'Something slow to start, some air, then hands-on care.', 'oria' ),
			'stops' => array( array( 'kind' => 'listing', 'what' => __( 'Start gently', 'oria' ), 'cats' => $calm ), array( 'kind' => 'walk', 'i' => 0 ), array( 'kind' => 'listing', 'what' => __( 'Then a treatment', 'oria' ), 'cats' => $hands ) ),
		),
		array(
			'name'  => __( 'After-work reset', 'oria' ),
			'line'  => __( 'Places that publish evening hours, for after the day is done.', 'oria' ),
			'stops' => array( array( 'kind' => 'listing', 'what' => __( 'Move or be moved', 'oria' ), 'cats' => array_merge( $move, $hands ), 'evening' => true ), array( 'kind' => 'walk', 'i' => 1 ), array( 'kind' => 'listing', 'what' => __( 'Wind down', 'oria' ), 'cats' => $calm, 'evening' => true ) ),
		),
		array(
			'name'  => __( 'A deeper day', 'oria' ),
			'line'  => __( 'Longer, slower, with a proper break in the middle.', 'oria' ),
			'stops' => array( array( 'kind' => 'listing', 'what' => __( 'Go inward', 'oria' ), 'cats' => array( 'meditation', 'retreats', 'experiences', 'energy', 'breathwork' ) ), array( 'kind' => 'listing', 'what' => __( 'Eat well', 'oria' ), 'cats' => $food ), array( 'kind' => 'listing', 'what' => __( 'Finish with care', 'oria' ), 'cats' => $hands ) ),
		),
	);

	$plans = array();
	$seen  = array(); // places already leading a stop in an earlier plan
	foreach ( $defs as $def ) {
		$stops = array();
		$used  = array();
		foreach ( $def['stops'] as $s ) {
			if ( 'walk' === $s['kind'] ) {
				$w = $walks[ $s['i'] ] ?? ( $walks[0] ?? null );
				if ( $w ) {
					$stops[] = array( 'kind' => 'walk', 'what' => __( 'Get some air', 'oria' ), 'label' => (string) $w['label'], 'line' => (string) ( $w['line'] ?? '' ) );
				}
				continue;
			}
			$cands = array_values( array_filter( $pick( $s['cats'], ! empty( $s['evening'] ) ? $evening : null ), static fn( array $r ): bool => ! isset( $used[ (string) $r['id'] ] ) ) );
			if ( ! $cands ) {
				$stops = array();
				break; // a plan with a hole in it is not a plan
			}
			// Three plans that open on three different places where the area
			// has them; the others stay as swaps.
			usort( $cands, static fn( array $a, array $b ): int => (int) isset( $seen[ (string) $a['id'] ] ) <=> (int) isset( $seen[ (string) $b['id'] ] ) );
			$used[ (string) $cands[0]['id'] ] = true;
			$seen[ (string) $cands[0]['id'] ] = true;
			$stops[] = array(
				'kind'  => 'listing',
				'what'  => $s['what'],
				'cands' => array_map(
					static fn( array $r ): array => array(
						'name' => html_entity_decode( (string) $r['name'], ENT_QUOTES, 'UTF-8' ),
						'url'  => (string) $r['url'],
						'cat'  => pname( (string) $r['cat'] ),
						'blurb' => wp_trim_words( (string) ( $r['blurb'] ?? '' ), 16, '…' ),
					),
					$cands
				),
			);
		}
		$listing_stops = array_filter( $stops, static fn( array $s ): bool => 'listing' === $s['kind'] );
		if ( count( $listing_stops ) >= 2 ) {
			$plans[] = array( 'name' => $def['name'], 'line' => $def['line'], 'stops' => $stops );
		}
	}
	return $plans;
}

/**
 * Events in this area -- tagged with it or a suburb inside it -- not over
 * yet. Never Perth-wide events to fill the space (brief 16).
 *
 * @return list<array{id: int, ts: int, now: bool, suburb: string, src: string, cat: string}>
 */
function events( \WP_Term $term, int $limit = 3 ): array {
	$now     = (int) current_time( 'timestamp' );
	$now_sql = gmdate( 'Y-m-d H:i:s', $now );
	$ids     = get_posts(
		array(
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => $limit * 3,
			'fields'         => 'ids',
			'meta_key'       => 'event_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => 'event_start', 'value' => $now_sql, 'compare' => '>=', 'type' => 'DATETIME' ),
				array( 'key' => 'event_end', 'value' => $now_sql, 'compare' => '>=', 'type' => 'DATETIME' ),
			),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array( 'taxonomy' => 'area', 'field' => 'term_id', 'terms' => array( (int) $term->term_id ), 'include_children' => true ),
			),
		)
	);
	$out = array();
	foreach ( $ids as $id ) {
		$id = (int) $id;
		$ts = strtotime( (string) get_post_meta( $id, 'event_start', true ) );
		if ( ! $ts ) {
			continue;
		}
		$end    = strtotime( (string) get_post_meta( $id, 'event_end', true ) ) ?: 0;
		$suburb = '';
		foreach ( (array) wp_get_post_terms( $id, 'area' ) as $at ) {
			if ( $at instanceof \WP_Term ) {
				$suburb = \Oria\Theme\tname( $at );
				if ( $at->parent ) {
					break;
				}
			}
		}
		$cat = '';
		foreach ( (array) wp_get_post_terms( $id, 'practice' ) as $pt ) {
			if ( $pt instanceof \WP_Term ) {
				$cat = \Oria\Theme\tname( $pt );
				break;
			}
		}
		$out[] = array(
			'id'     => $id,
			'ts'     => $ts,
			'now'    => $ts < $now && $end >= $now,
			'suburb' => $suburb,
			'src'    => (string) get_post_meta( $id, '_oria_src', true ),
			'cat'    => $cat,
		);
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}
