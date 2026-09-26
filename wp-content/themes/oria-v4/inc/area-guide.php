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

/**
 * The area's name as it reads after "in": "the Northern Suburbs",
 * "Fremantle". Region names that are a plural description take "the".
 */
function in_name( \WP_Term $term ): string {
	$name = \Oria\Theme\tname( $term );
	return preg_match( '/\bSuburbs$/', $name ) ? 'the ' . $name : $name;
}

function title( $title ) {
	$term = term();
	if ( ! $term || has_override( $term, 'wpseo_title' ) ) {
		return $title;
	}
	// A guide's own title, written with its day ideas in mind.
	$own = (string) ( guide( $term )['seo_title'] ?? '' );
	if ( '' !== $own ) {
		return $own;
	}
	$n    = count( rows( $term ) );
	$name = in_name( $term );
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
	$own = (string) ( guide( $term )['seo_description'] ?? '' );
	if ( '' !== $own ) {
		return $own;
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
		__( '%1$d wellness places in %2$s, including %3$s. Compare what they offer, what they cost and how to visit.', 'oria' ),
		$n,
		in_name( $term ),
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
		} elseif ( function_exists( '\Oria\Core\Taxonomies\is_suburb' ) && \Oria\Core\Taxonomies\is_suburb( $term ) ) {
			/*
			 * A suburb by its display name, as the results engine matches it
			 * (data-suburb). Faq\matching() compares sanitize_title() of the
			 * name with the slug, which misses a suburb whose slug had to be
			 * made unique: "Margaret River" is margaret-river-town, and its
			 * page said "No practices listed" above its own ten listings.
			 */
			$name = \Oria\Theme\tname( $term );
			$city = function_exists( '\Oria\Core\Cities\for_area' ) ? (string) ( \Oria\Core\Cities\for_area( $term )['slug'] ?? '' ) : '';
			$cache[ $term->term_id ] = array_values(
				array_filter(
					city_rows( $city ),
					static fn( array $r ): bool => html_entity_decode( (string) ( $r['suburb'] ?? '' ), ENT_QUOTES, 'UTF-8' ) === $name
				)
			);
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

/**
 * The neighbourhoods chosen for the hub's mosaic, in order.
 *
 * Deliberately a list somebody wrote rather than a ranking: the five
 * biggest would be four of Perth Central and the CBD twice over, which
 * tells a visitor nothing about the city. Edited in
 * assets/data/area-guides.json under "featured"; an empty or missing
 * list leaves the caller to fall back to scoring.
 *
 * @return list<string> Area slugs.
 */
function featured_slugs(): array {
	$slugs = (array) ( config()['featured'] ?? array() );
	return array_values( array_filter( array_map( 'sanitize_title', $slugs ) ) );
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
 * "At a glance", as one insight with figures behind it.
 *
 * glance() gave four cards of equal weight, so "Breathwork stands out
 * here" -- the only line that says anything about the place -- sat in a
 * beige box the same size as a count of practice types. This separates
 * them: the standout is the story and the numbers support it.
 *
 * Nothing new is calculated. strongest() still decides what stands out,
 * on the rule it already used: at least three here, at least five across
 * the city, and twice the city's share of them, because the biggest
 * category everywhere is not what makes a place distinct.
 *
 * @param list<array<string, mixed>> $rows
 * @param array|null                 $strong From strongest().
 * @param \WP_Term|null              $area   For the category-and-suburb link.
 * @return array{standout: array|null, stats: list<array>, place: string}
 */
function snapshot( array $rows, int $top_cats, ?array $strong, string $place_city, ?\WP_Term $area = null ): array {
	$n   = count( $rows );
	$out = array( 'standout' => null, 'stats' => array(), 'place' => $place_city );

	if ( $strong ) {
		$term = get_term_by( 'slug', (string) $strong['slug'], 'practice' );
		$url  = '';
		if ( $term instanceof \WP_Term && $area instanceof \WP_Term && function_exists( '\Oria\Core\PracticesIndex\area_url' ) ) {
			/*
			 * The clean combination address where one resolves, because
			 * area_url() hands back the ?suburb= form that 301s to it --
			 * fine for a form submission, a wasted hop on a link we are
			 * writing ourselves. Built with the same two helpers the
			 * redirect uses, so the two can never disagree.
			 */
			if ( function_exists( '\Oria\Core\PracticesIndex\resolve_facet' ) ) {
				$facet = \Oria\Core\PracticesIndex\resolve_facet( $term, $area->slug );
				if ( null !== $facet && 'area' === ( $facet['key'] ?? '' ) ) {
					$city = function_exists( '\Oria\Core\PracticesIndex\facet_city' )
						? \Oria\Core\PracticesIndex\facet_city( $facet )
						: null;
					$url = \Oria\Core\PracticesIndex\category_url( $term, $city ) . $facet['slug'] . '/';
				}
			}
			if ( '' === $url ) {
				$url = (string) \Oria\Core\PracticesIndex\area_url( $term, $area );
			}
		}
		$out['standout'] = array(
			'slug' => (string) $strong['slug'],
			'name' => pname( (string) $strong['slug'] ),
			'here' => (int) $strong['n'],
			'of'   => (int) $strong['of'],
			'url'  => $url,
			'id'   => $term instanceof \WP_Term ? (int) $term->term_id : 0,
		);
	}

	$out['stats'][] = array(
		'value' => number_format_i18n( $n ),
		/* translators: the label under a count of places */
		'label' => _n( 'hand-checked place', 'hand-checked places', $n, 'oria' ),
		'note'  => '',
	);
	$out['stats'][] = array(
		'value' => number_format_i18n( $top_cats ),
		/* translators: the label under a count of practice types */
		'label' => _n( 'practice type', 'practice types', $top_cats, 'oria' ),
		'note'  => __( 'From quiet to active', 'oria' ),
	);

	/*
	 * "Typical" in the label, "median of N" in the note: the word people
	 * read and the arithmetic they can check. Below the floor there is no
	 * card at all rather than a number pretending to more than it knows.
	 */
	$prices = prices( $rows );
	if ( count( $prices ) >= MIN_PRICES ) {
		$out['stats'][] = array(
			'value' => '$' . number_format_i18n( (int) round( median( $prices ) ) ),
			'label' => __( 'typical starting price', 'oria' ),
			/* translators: %d: how many places publish a price */
			'note'  => sprintf( __( 'Median of %d published prices', 'oria' ), count( $prices ) ),
		);
	} else {
		$online = count( array_filter( $rows, static fn( array $r ): bool => in_array( $r['format'] ?? '', array( 'online', 'both' ), true ) ) );
		if ( $online >= 2 ) {
			$out['stats'][] = array(
				'value' => number_format_i18n( $online ),
				'label' => __( 'also see people online', 'oria' ),
				'note'  => '',
			);
		}
	}

	return $out;
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
 * The Local Rhythm's data-backed observations (brief 14), as stat tiles.
 * Each one carries its own count, and each has a floor below which it is
 * left out. The figure leads ("17 of 31"), a plain label says what it
 * counts, and a note carries any caveat -- so the tile reads at a glance
 * and the qualification is still there.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array{kind: string, big: string, label: string, note: string, frac: float|null}>
 */
function rhythm_points( array $rows, ?array $strong, string $place, string $place_city, array $guide ): array {
	$n = count( $rows );
	if ( $n < MIN_PATTERN ) {
		return array();
	}
	$out  = array();
	/* translators: 1: count, 2: total */
	$of   = static fn( int $a, int $b ): string => sprintf( __( '%1$d of %2$d', 'oria' ), $a, $b );
	$tile = static fn( string $kind, string $big, string $label, string $note = '', ?float $frac = null ): array => compact( 'kind', 'big', 'label', 'note', 'frac' );

	if ( $strong ) {
		$out[] = $tile(
			'star',
			pname( $strong['slug'] ),
			/* translators: 1: count here, 2: count across the city, 3: city */
			sprintf( __( 'stands out here: %1$d of the %2$d listed across %3$s', 'oria' ), $strong['n'], $strong['of'], $place_city ),
			'',
			$strong['of'] ? $strong['n'] / $strong['of'] : null
		);
	}

	// Where the addresses sit, against a named landmark from the guide.
	$anchor = (array) ( $guide['anchor'] ?? array() );
	if ( isset( $anchor['lat'], $anchor['lng'], $anchor['name'] ) ) {
		$addressed = array_filter( $rows, static fn( array $r ): bool => 'address' === ( $r['geo'] ?? '' ) && is_numeric( $r['lat'] ?? null ) && is_numeric( $r['lng'] ?? null ) );
		if ( count( $addressed ) >= MIN_ADDRESSED ) {
			$near = array_filter( $addressed, static fn( array $r ): bool => km( (float) $r['lat'], (float) $r['lng'], (float) $anchor['lat'], (float) $anchor['lng'] ) <= 1.0 );
			if ( count( $near ) * 2 >= count( $addressed ) ) {
				$out[] = $tile(
					'pin',
					$of( count( $near ), count( $addressed ) ),
					/* translators: %s: landmark */
					sprintf( __( 'within a kilometre of %s', 'oria' ), (string) $anchor['name'] ),
					__( 'Of the places with a street address on file, as the crow flies.', 'oria' ),
					count( $near ) / count( $addressed )
				);
			}
		}
	}

	$h = hours( $rows );
	if ( $h['known'] >= MIN_PATTERN ) {
		if ( count( $h['evening'] ) >= 2 ) {
			$out[] = $tile(
				'moon',
				$of( count( $h['evening'] ), $h['known'] ),
				__( 'open past 7pm on at least one day', 'oria' ),
				__( 'Of the places that publish their hours.', 'oria' ),
				count( $h['evening'] ) / $h['known']
			);
		}
		if ( count( $h['weekend'] ) >= 2 ) {
			$out[] = $tile(
				'calendar',
				$of( count( $h['weekend'] ), $h['known'] ),
				__( 'open on a Saturday or Sunday', 'oria' ),
				__( 'Of the places that publish their hours.', 'oria' ),
				count( $h['weekend'] ) / $h['known']
			);
		}
	}

	$prices = prices( $rows );
	if ( count( $prices ) >= MIN_PRICES ) {
		$out[] = $tile(
			'tag',
			'$' . min( $prices ) . '–$' . max( $prices ),
			/* translators: %d: count with a price */
			sprintf( __( 'published starting prices, from %d places', 'oria' ), count( $prices ) ),
			__( 'Each practice sets its own, and they change.', 'oria' )
		);
	}

	$online = count( array_filter( $rows, static fn( array $r ): bool => in_array( $r['format'] ?? '', array( 'online', 'both' ), true ) ) );
	if ( $online >= 2 ) {
		$out[] = $tile(
			'screen',
			(string) $online,
			_n( 'place also sees people online', 'places also see people online', $online, 'oria' ),
			/* translators: %d: listings here */
			sprintf( __( 'Out of %d here.', 'oria' ), $n ),
			$online / $n
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
 * The same practice in the suburbs nearest this one -- for a category x
 * suburb page with too little to filter ("Fitness & Movement in Malaga"):
 * where else close by has it. Closest first, within 15 km, only suburbs
 * with at least one such listing, each linked to its own category x suburb
 * page. Distances are between suburb centres, so they are shown only as a
 * direction.
 *
 * @return list<array{term: \WP_Term, n: int, dir: string, url: string}>
 */
function category_nearby( \WP_Term $practice, \WP_Term $suburb, ?array $city, int $limit = 6 ): array {
	if ( ! function_exists( '\Oria\Core\PracticesIndex\category_url' ) ) {
		return array();
	}
	$cslug = (string) ( $city['slug'] ?? '' );
	$cent  = centroids( $cslug );
	$here  = \Oria\Theme\tname( $suburb );
	$me    = $cent[ $here ] ?? null;
	if ( ! $me ) {
		return array();
	}

	// Listings of this practice per suburb name, in one pass.
	$per = array();
	foreach ( city_rows( $cslug ) as $r ) {
		$s = html_entity_decode( (string) ( $r['suburb'] ?? '' ), ENT_QUOTES, 'UTF-8' );
		if ( '' !== $s && $s !== $here && in_practice( $r, $practice->slug ) ) {
			$per[ $s ] = ( $per[ $s ] ?? 0 ) + 1;
		}
	}
	if ( ! $per ) {
		return array();
	}

	$terms = array();
	foreach ( (array) get_terms( array( 'taxonomy' => 'area', 'hide_empty' => false ) ) as $t ) {
		if ( $t instanceof \WP_Term && \Oria\Core\Taxonomies\is_suburb( $t ) ) {
			$terms[ \Oria\Theme\tname( $t ) ] = $t;
		}
	}

	$base = \Oria\Core\PracticesIndex\category_url( $practice, $city );
	$out  = array();
	foreach ( $per as $name => $n ) {
		$c = $cent[ $name ] ?? null;
		if ( ! $c || ! isset( $terms[ $name ] ) ) {
			continue;
		}
		$d = km( $me['lat'], $me['lng'], $c['lat'], $c['lng'] );
		if ( $d > 15.0 ) {
			continue;
		}
		$out[] = array(
			'term' => $terms[ $name ],
			'n'    => $n,
			'd'    => $d,
			'dir'  => $d > 0.5 ? direction( $me['lat'], $me['lng'], $c['lat'], $c['lng'] ) : '',
			'url'  => $base . $terms[ $name ]->slug . '/',
		);
	}
	usort( $out, static fn( array $a, array $b ): int => $a['d'] <=> $b['d'] );
	return array_slice( $out, 0, $limit );
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

/* ------------------------------------------------------- destination guide */

/*
 * The area page as a destination guide: the reviewed outing ideas, the
 * places to leave room for, the FAQ and the figures, all from the same rows
 * as the listings above them. Nothing here is generated to fill a slot --
 * an area without reviewed records simply shows fewer sections.
 */

/**
 * Practices that are clinical care. A dietitian, a physio or a counsellor
 * is a real service but never a casual stop in a day out, and a nutrition
 * category does not make somewhere a place to eat.
 */
const CLINICAL = array( 'allied', 'nutrition', 'mind' );

/**
 * Of those, the families whose every sub-category is clinical too. "mind"
 * is not one: breathwork and meditation sit under it, while a listing filed
 * under "mind" itself is counselling or psychotherapy.
 */
const CLINICAL_FAMILIES = array( 'allied', 'nutrition' );

/**
 * Wording that means a place is not a one-off visit: a course, a series,
 * an assessment before anything else. Read from what the listing itself
 * says, so the rule follows the evidence rather than the category.
 */
const SERIES_RE = '/\b(twelve|ten|six|multi)[- ]?series\b|\bseries rather than\b|\bcourse of\b|\bblock of \d|\binitial (consultation|assessment) (is )?required\b|\bby referral\b/i';

/** A day of the week in a schedule line: "Saturdays 6.30am". */
const WEEKDAY_RE = '/\b(mon|tues|wednes|thurs|fri|satur|sun)days?\b/i';

/**
 * This area's rows, keyed by the listing's slug.
 *
 * @param list<array<string, mixed>> $rows
 * @return array<string, array<string, mixed>>
 */
function by_slug( array $rows ): array {
	$out = array();
	foreach ( $rows as $r ) {
		$id = post_id( $r );
		if ( $id > 0 ) {
			$out[ (string) get_post_field( 'post_name', $id ) ] = $r;
		}
	}
	return $out;
}

/**
 * Whether a row is clinical care, by its MAIN practice or that practice's
 * parent. A sauna also filed under nutrition is still a sauna.
 */
function is_clinical( array $row ): bool {
	$cat = (string) ( $row['cat'] ?? '' );
	if ( in_array( $cat, CLINICAL, true ) ) {
		return true;
	}
	$t = '' !== $cat ? get_term_by( 'slug', $cat, 'practice' ) : null;
	if ( $t instanceof \WP_Term && $t->parent ) {
		$parent = get_term( (int) $t->parent, 'practice' );
		return $parent instanceof \WP_Term && in_array( $parent->slug, CLINICAL_FAMILIES, true );
	}
	return false;
}

/** Whether a row describes itself as a series, a course or referral-first. */
function is_series( array $row ): bool {
	$id   = post_id( $row );
	$text = (string) ( $row['blurb'] ?? '' ) . ' ' . ( $id ? wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ) : '' );
	return (bool) preg_match( SERIES_RE, $text );
}

/** A street-address point for a row, or '' when it has none on file. */
function row_point( array $row ): string {
	if ( 'address' !== ( $row['geo'] ?? '' ) || ! is_numeric( $row['lat'] ?? null ) || ! is_numeric( $row['lng'] ?? null ) ) {
		return '';
	}
	return round( (float) $row['lat'], 6 ) . ',' . round( (float) $row['lng'], 6 );
}

/**
 * Why a stop cannot stand, or '' when it can. Public so the checks can be
 * run from WP-CLI against the real data.
 *
 * @param array<string, mixed>               $stop   The record's stop.
 * @param array<string, array<string, mixed>> $rows   by_slug() rows.
 * @param array<string, array<string, mixed>> $places The guide's places.
 * @param string                              $when   The plan's stated day, if any.
 */
function stop_problem( array $stop, array $rows, array $places, string $when ): string {
	$role = (string) ( $stop['role'] ?? '' );

	if ( in_array( $role, array( 'outdoors', 'heritage' ), true ) ) {
		$p = $places[ (string) ( $stop['place'] ?? '' ) ] ?? null;
		if ( ! $p || empty( $p['name'] ) ) {
			return 'unknown place';
		}
		return (string) ( $p['kind'] ?? '' ) === $role ? '' : 'place is not ' . $role;
	}

	if ( 'refresh' === $role ) {
		if ( ! empty( $stop['place'] ) ) {
			$p = $places[ (string) $stop['place'] ] ?? null;
			return ( $p && 'refresh' === ( $p['kind'] ?? '' ) ) ? '' : 'place is not a food venue';
		}
		$row = $rows[ (string) ( $stop['listing'] ?? '' ) ] ?? null;
		if ( ! $row ) {
			return 'listing not in this area';
		}
		// A venue, not a practice: a dietitian is filed under nutrition too.
		return 'place' === (string) get_post_meta( post_id( $row ), 'kind', true ) ? '' : 'listing is not a venue';
	}

	if ( in_array( $role, array( 'experience', 'group' ), true ) ) {
		$row = $rows[ (string) ( $stop['listing'] ?? '' ) ] ?? null;
		if ( ! $row ) {
			return 'listing not in this area';
		}
		if ( is_clinical( $row ) ) {
			return 'clinical care';
		}
		if ( 'online' === ( $row['format'] ?? '' ) ) {
			return 'online only';
		}
		if ( is_series( $row ) ) {
			return 'series or assessment first';
		}
		// A Saturday-only session belongs in a plan that says Saturday.
		if ( preg_match( WEEKDAY_RE, (string) ( $row['next'] ?? '' ), $m ) && false === stripos( $when, $m[1] ) ) {
			return 'runs on a set day the plan does not name';
		}
		if ( 'group' === $role && empty( $stop['join'] ) ) {
			return 'group without joining details';
		}
		return '';
	}

	return 'unknown role';
}

/**
 * "Make a day of it": the area's reviewed outing records, each stop checked
 * against the live listings. A record with any stop that fails is dropped
 * whole -- a plan with a hole in it is not a plan -- and there is no
 * generated fallback.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function outings( array $rows, array $guide ): array {
	$records = array_values( array_filter( (array) ( $guide['outings'] ?? array() ), 'is_array' ) );
	if ( ! $records ) {
		return array();
	}
	$index  = by_slug( $rows );
	$places = (array) ( $guide['places'] ?? array() );
	$labels = array(
		'experience' => __( 'Booked experience', 'oria' ),
		'group'      => __( 'Group activity', 'oria' ),
		'outdoors'   => __( 'Outdoors', 'oria' ),
		'heritage'   => __( 'Art and heritage', 'oria' ),
		'refresh'    => __( 'Coffee or a meal', 'oria' ),
	);

	$out = array();
	foreach ( $records as $rec ) {
		$when   = (string) ( $rec['when'] ?? '' );
		$stops  = array();
		$points = array();
		$ok     = ! empty( $rec['name'] ) && ! empty( $rec['checked'] );
		foreach ( (array) ( $rec['stops'] ?? array() ) as $s ) {
			if ( ! $ok || ! is_array( $s ) || '' !== stop_problem( $s, $index, $places, $when ) ) {
				$ok = false;
				break;
			}
			$role  = (string) $s['role'];
			$row   = $index[ (string) ( $s['listing'] ?? '' ) ] ?? null;
			$place = $places[ (string) ( $s['place'] ?? '' ) ] ?? null;
			$meet  = $places[ (string) ( $s['meet'] ?? '' ) ] ?? null;

			if ( $row ) {
				$name  = html_entity_decode( (string) $row['name'], ENT_QUOTES, 'UTF-8' );
				$url   = (string) $row['url'];
				$point = row_point( $row );
				if ( '' === $point && $meet && ! empty( $meet['query'] ) ) {
					$point = (string) $meet['query'];
				}
			} else {
				$name  = (string) $place['name'];
				$url   = '';
				$point = (string) ( $place['query'] ?? '' );
			}
			$points[] = $point;
			$stops[]  = array(
				'role'   => $role,
				'label'  => $labels[ $role ] ?? '',
				'name'   => $name,
				'url'    => $url,
				'detail' => (string) ( $s['detail'] ?? ( $place['line'] ?? '' ) ),
				'join'   => (string) ( $s['join'] ?? '' ),
				'kind'   => in_array( $role, array( 'experience', 'group' ), true ) ? 'booked' : ( 'refresh' === $role ? 'food' : 'public' ),
			);
		}
		$booked = array_filter( $stops, static fn( array $s ): bool => 'booked' === $s['kind'] );
		if ( ! $ok || count( $stops ) < 2 || ! $booked ) {
			continue;
		}

		// Directions only when every stop has a place we can stand behind.
		$directions = '';
		if ( ! in_array( '', $points, true ) && count( $points ) >= 2 ) {
			$args = array(
				'api'         => '1',
				'origin'      => $points[0],
				'destination' => $points[ count( $points ) - 1 ],
			);
			if ( count( $points ) > 2 ) {
				$args['waypoints'] = implode( '|', array_slice( $points, 1, -1 ) );
			}
			$directions = 'https://www.google.com/maps/dir/?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
		}

		$ts    = strtotime( (string) $rec['checked'] );
		$out[] = array(
			'slug'       => sanitize_title( (string) ( $rec['slug'] ?? $rec['name'] ) ),
			'name'       => (string) $rec['name'],
			'line'       => (string) ( $rec['line'] ?? '' ),
			'when'       => $when,
			'cost'       => (string) ( $rec['cost'] ?? __( 'Costs vary. Check each place.', 'oria' ) ),
			'duration'   => (string) ( $rec['duration'] ?? '' ),
			'checked'    => $ts ? wp_date( 'j F Y', $ts ) : '',
			'stops'      => $stops,
			'directions' => $directions,
		);
	}
	return $out;
}

/**
 * "Leave room for a little exploring": a handful of real places around the
 * listings, from the guide, each checked the same way as an outing stop.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array{kind: string, name: string, line: string, url: string, maps: string}>
 */
function explore( array $rows, array $guide ): array {
	$index  = by_slug( $rows );
	$places = (array) ( $guide['places'] ?? array() );
	$labels = array(
		'outdoors' => __( 'Walk or sit outdoors', 'oria' ),
		'refresh'  => __( 'Coffee or a meal', 'oria' ),
		'heritage' => __( 'Art and heritage', 'oria' ),
	);
	$out = array();
	foreach ( (array) ( $guide['explore'] ?? array() ) as $e ) {
		if ( ! is_array( $e ) || ! isset( $labels[ (string) ( $e['role'] ?? '' ) ] ) ) {
			continue;
		}
		if ( '' !== stop_problem( $e, $index, $places, '' ) ) {
			continue;
		}
		$row   = $index[ (string) ( $e['listing'] ?? '' ) ] ?? null;
		$place = $places[ (string) ( $e['place'] ?? '' ) ] ?? null;
		$point = $row ? row_point( $row ) : (string) ( $place['query'] ?? '' );
		$out[] = array(
			'kind'  => (string) $e['role'],
			'label' => $labels[ (string) $e['role'] ],
			'name'  => $row ? html_entity_decode( (string) $row['name'], ENT_QUOTES, 'UTF-8' ) : (string) $place['name'],
			'line'  => (string) ( $e['line'] ?? ( $place['line'] ?? '' ) ),
			'url'   => $row ? (string) $row['url'] : '',
			'maps'  => '' !== $point ? 'https://www.google.com/maps/search/?' . http_build_query( array( 'api' => '1', 'query' => $point ), '', '&', PHP_QUERY_RFC3986 ) : '',
		);
		if ( count( $out ) >= 3 ) {
			break;
		}
	}
	return $out;
}

/**
 * Listings filed here that the guide has checked meet somewhere else: a
 * collective based in town whose hikes are across the city. They stay in
 * the results; this is the honest label beside them.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array{name: string, url: string, note: string}>
 */
function elsewhere( array $rows, array $guide ): array {
	$index = by_slug( $rows );
	$out   = array();
	foreach ( (array) ( $guide['elsewhere'] ?? array() ) as $slug => $note ) {
		$row = $index[ (string) $slug ] ?? null;
		if ( $row && '' !== trim( (string) $note ) ) {
			$out[] = array(
				'name' => html_entity_decode( (string) $row['name'], ENT_QUOTES, 'UTF-8' ),
				'url'  => (string) $row['url'],
				'note' => (string) $note,
			);
		}
	}
	return $out;
}

/**
 * The area's FAQ, counted from the same rows as everything else on the
 * page. The shared generator tallies only each listing's main practice,
 * which made the page say four breathwork places in one sentence and five
 * in the next. A hand-written FAQ on the term still wins.
 *
 * @param list<array<string, mixed>>                     $rows
 * @param array<string, array{term: \WP_Term, count: int}> $top Top-level practices here, most first.
 * @return list<array{q: string, a: string}>
 */
function faqs( \WP_Term $term, array $rows, array $top ): array {
	if ( function_exists( '\Oria\Core\Faq\parse_override' ) && defined( '\Oria\Core\Faq\META_OVERRIDE' ) ) {
		$manual = \Oria\Core\Faq\parse_override( (string) get_term_meta( $term->term_id, \Oria\Core\Faq\META_OVERRIDE, true ) );
		if ( $manual ) {
			return $manual;
		}
	}
	$n = count( $rows );
	if ( $n < 3 ) {
		return array();
	}
	$place = in_name( $term );
	$out   = array();

	$most = array();
	foreach ( array_slice( $top, 0, 3, true ) as $t ) {
		$most[] = sprintf( '%s (%d)', \Oria\Theme\tname( $t['term'] ), (int) $t['count'] );
	}
	$list = count( $most ) > 1 ? implode( ', ', array_slice( $most, 0, -1 ) ) . ' ' . __( 'and', 'oria' ) . ' ' . end( $most ) : (string) ( $most[0] ?? '' );
	$out[] = array(
		/* translators: %s: area */
		'q' => sprintf( __( 'What wellness is there in %s?', 'oria' ), $place ),
		'a' => sprintf(
			/* translators: 1: count, 2: area, 3: number of kinds, 4: list */
			__( 'Oria Haven lists %1$d places in %2$s across %3$d kinds of practice. The most common are %4$s. A place counts once for each kind of practice it offers.', 'oria' ),
			$n,
			$place,
			count( $top ),
			$list
		),
	);

	$prices = prices( $rows );
	if ( count( $prices ) >= 2 ) {
		$out[] = array(
			/* translators: %s: area */
			'q' => sprintf( __( 'What do sessions cost in %s?', 'oria' ), $place ),
			'a' => sprintf(
				/* translators: 1: count with a price, 2: count, 3: lowest, 4: highest */
				__( '%1$d of the %2$d places publish a starting price, from $%3$d to $%4$d. What you pay depends on the experience, and each place sets and changes its own, so check the listing before you go.', 'oria' ),
				count( $prices ),
				$n,
				min( $prices ),
				max( $prices )
			),
		);
	}

	$free = count( array_filter( $rows, static fn( array $r ): bool => 'Free' === ( $r['priceBand'] ?? '' ) ) );
	if ( $free >= 1 ) {
		$out[] = array(
			/* translators: %s: area */
			'q' => sprintf( __( 'Is anything free in %s?', 'oria' ), $place ),
			'a' => sprintf(
				/* translators: 1: count, 2: total */
				_n( '%1$d of the %2$d places lists its sessions as free or by donation.', '%1$d of the %2$d places list their sessions as free or by donation.', $free, 'oria' ),
				$free,
				$n
			),
		);
	}

	if ( function_exists( '\Oria\Core\Faq\editorial_faq' ) ) {
		$out[] = \Oria\Core\Faq\editorial_faq();
	}
	return $out;
}

/**
 * Whether a What's On link can open filtered to this area. The events page
 * filters by suburb name, so only a suburb with at least one event can.
 */
function events_url( \WP_Term $term, bool $has_events ): string {
	$base = (string) ( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ) );
	$sub  = function_exists( '\Oria\Core\Taxonomies\is_suburb' ) && \Oria\Core\Taxonomies\is_suburb( $term );
	return ( $sub && $has_events ) ? add_query_arg( 'area', sanitize_title( \Oria\Theme\tname( $term ) ), $base ) : $base;
}
