<?php
/**
 * The Day Designer: a small planner on spa pages that turns the page a
 * visitor is already reading into one priced, nearby outing.
 *
 * Deterministic, and deliberately narrow. Every suggestion is a row in
 * data/day-designer.json -- a named session at a real listing, with the
 * price the venue publishes for one person and/or two, the session length,
 * an indoor flag, the source and the date it was read. Nothing is generated:
 * no AI, no Places lookups, no invented cafés. A café break is an optional,
 * clearly labelled allowance with no venue attached.
 *
 * HARD CONSTRAINTS (never traded for relevance): the page's context tag, a
 * published price for the chosen party size, a fresh price, the budget,
 * the time available, the distance limit and indoor-only. Paid status plays
 * no part. When nothing fits, the answer says which constraint ruled the
 * options out and what the nearest miss costs, rather than quietly widening.
 *
 * WHAT IT DOES NOT DO: travel time (no routing provider -- distances are
 * straight-line and labelled so), timetables or live availability, booking.
 *
 *   render()        the widget, wherever a template asks for it
 *   plan()          the planner, pure apart from listing lookups
 *   GET /wp-json/oria/v1/day-plan
 *
 * Settings > Day Designer: on/off, which contexts, price freshness, the
 * homepage teaser, and a list of rows that cannot be used and why.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\DayDesigner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION   = 'oria_day_designer';
const BUFFER   = 15;  // Minutes to arrive, change and settle before a session.
const CAFE_MIN = 30;  // Minutes a café break adds.
const MAX_PLANS = 6;  // The plan plus its swaps.

function bootstrap(): void {
	add_action( 'rest_api_init', __NAMESPACE__ . '\routes' );
	add_action( 'admin_menu', __NAMESPACE__ . '\admin_menu' );
	add_action( 'admin_post_oria_day_designer', __NAMESPACE__ . '\admin_save' );
}

/* --------------------------------------------------------------- config */

/** @return array{enabled:bool, contexts:list<string>, fresh_days:int, teaser:bool} */
function config(): array {
	$o = (array) get_option( OPTION, array() );
	return array(
		'enabled'    => ! isset( $o['enabled'] ) || ! empty( $o['enabled'] ),
		'contexts'   => isset( $o['contexts'] ) ? array_values( array_map( 'strval', (array) $o['contexts'] ) ) : array_keys( data()['contexts'] ),
		'fresh_days' => max( 7, (int) ( $o['fresh_days'] ?? 90 ) ),
		'teaser'     => ! isset( $o['teaser'] ) || ! empty( $o['teaser'] ),
	);
}

/** The data file, read once per request. */
function data(): array {
	static $d = null;
	if ( null === $d ) {
		$raw = file_get_contents( ORIA_CORE_DIR . 'data/day-designer.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$d   = json_decode( (string) $raw, true );
		$d   = is_array( $d ) ? $d : array();
		$d  += array( 'contexts' => array(), 'facets' => array(), 'guides' => array(), 'experiences' => array() );
	}
	return $d;
}

function context( string $key ): ?array {
	$cfg = config();
	if ( ! $cfg['enabled'] || ! in_array( $key, $cfg['contexts'], true ) ) {
		return null;
	}
	$c = data()['contexts'][ $key ] ?? null;
	return is_array( $c ) ? $c + array( 'key' => $key, 'people' => 1 ) : null;
}

/* -------------------------------------------------------------- context */

/**
 * The context for the current request, or null where the widget does not
 * belong. Spa category pages (and their facet pages) and the Best Of guides
 * named in the data file; first page of results only.
 *
 * @return array{ctx:array, near:string}|null
 */
function current(): ?array {
	static $memo = false;
	if ( false !== $memo ) {
		return $memo;
	}
	$memo = null;
	if ( is_paged() ) {
		return null;
	}
	$near = '';
	$key  = '';
	if ( function_exists( '\Oria\Core\PracticesIndex\is_category' ) && \Oria\Core\PracticesIndex\is_category() ) {
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term || 'spa' !== $term->slug ) {
			return null;
		}
		$key   = 'spa';
		$facet = \Oria\Core\PracticesIndex\facet();
		if ( $facet ) {
			if ( 'area' === ( $facet['key'] ?? '' ) && ( $facet['area'] ?? null ) instanceof \WP_Term ) {
				$near = $facet['area']->slug;
			} else {
				$map = data()['facets'];
				$val = (string) ( $facet['value'] ?? '' );
				$key = (string) ( $map[ $val ] ?? $map[ (string) ( $facet['slug'] ?? '' ) ] ?? '' );
				if ( '' === $key ) {
					return null; // A spa facet the planner has no data for (e.g. hydrotherapy).
				}
			}
		}
	} elseif ( is_singular( 'best_of' ) ) {
		$key = (string) ( data()['guides'][ (string) get_post_field( 'post_name', get_queried_object_id() ) ] ?? '' );
	}
	$ctx = '' !== $key ? context( $key ) : null;
	if ( ! $ctx ) {
		return null;
	}
	return $memo = array( 'ctx' => $ctx, 'near' => $near );
}

/* --------------------------------------------------------------- places */

/**
 * Suburbs that can be a starting point: area terms with listings, grouped
 * by region, inside the current city.
 *
 * @return array<string, array<string,string>> region name => [slug => name]
 */
function suburbs(): array {
	$key = 'oria_dd_suburbs_v1';
	$hit = get_transient( $key );
	if ( is_array( $hit ) ) {
		return $hit;
	}
	$out  = array();
	$city = get_term_by( 'slug', 'perth', 'area' );
	if ( $city instanceof \WP_Term ) {
		foreach ( get_terms( array( 'taxonomy' => 'area', 'parent' => $city->term_id, 'hide_empty' => false ) ) as $region ) {
			$subs = get_terms( array( 'taxonomy' => 'area', 'parent' => $region->term_id, 'hide_empty' => true, 'orderby' => 'name' ) );
			if ( is_array( $subs ) && $subs ) {
				foreach ( $subs as $s ) {
					$out[ html_entity_decode( $region->name, ENT_QUOTES, 'UTF-8' ) ][ $s->slug ] = html_entity_decode( $s->name, ENT_QUOTES, 'UTF-8' );
				}
			}
		}
	}
	set_transient( $key, $out, DAY_IN_SECONDS );
	return $out;
}

/** A suburb's rough centre: the mean of its listings' coordinates, else its region's. */
function point_of( string $slug ): ?array {
	$t = '' !== $slug ? get_term_by( 'slug', $slug, 'area' ) : false;
	if ( ! $t instanceof \WP_Term ) {
		return null;
	}
	if ( function_exists( '\Oria\Core\Work\centre_of' ) ) {
		return \Oria\Core\Work\centre_of( $t );
	}
	return null;
}

/** Straight-line distance in km. */
function km( array $a, array $b ): float {
	$r  = 6371.0;
	$dl = deg2rad( $b[0] - $a[0] );
	$dg = deg2rad( $b[1] - $a[1] );
	$h  = sin( $dl / 2 ) ** 2 + cos( deg2rad( $a[0] ) ) * cos( deg2rad( $b[0] ) ) * sin( $dg / 2 ) ** 2;
	return 2 * $r * asin( min( 1.0, sqrt( $h ) ) );
}

/** Published listing for a slug, with what the planner shows, or null. */
function listing( string $slug ): ?array {
	static $memo = array();
	if ( array_key_exists( $slug, $memo ) ) {
		return $memo[ $slug ];
	}
	$p = get_page_by_path( $slug, OBJECT, 'listing' );
	if ( ! $p instanceof \WP_Post || 'publish' !== $p->post_status ) {
		return $memo[ $slug ] = null;
	}
	$lat = (float) get_post_meta( $p->ID, 'geo_lat', true );
	$lng = (float) get_post_meta( $p->ID, 'geo_lng', true );
	return $memo[ $slug ] = array(
		'id'     => $p->ID,
		'name'   => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
		'url'    => (string) get_permalink( $p ),
		'suburb' => function_exists( '\Oria\Core\BestOf\suburb' ) ? (string) \Oria\Core\BestOf\suburb( $p->ID ) : '',
		'point'  => ( $lat && $lng ) ? array( $lat, $lng ) : null,
		'image'  => (string) get_the_post_thumbnail_url( $p, 'medium' ),
	);
}

/* -------------------------------------------------------------- planner */

/** Money as people read it: $85, $37.50. */
function money( int $cents ): string {
	return '$' . ( 0 === $cents % 100 ? number_format( intdiv( $cents, 100 ) ) : number_format( $cents / 100, 2 ) );
}

/** Is a row's price recent enough to use? */
function fresh( array $e, int $days ): bool {
	$t = strtotime( (string) ( $e['checked'] ?? '' ) . ' 00:00:00' );
	return $t && $t >= strtotime( wp_date( 'Y-m-d' ) . ' 00:00:00' ) - $days * DAY_IN_SECONDS;
}

/**
 * Normalise and clamp request preferences. Anything unknown falls back to
 * the context's defaults; nothing here trusts the browser for a price.
 */
function prefs( array $in, array $ctx ): array {
	$people  = in_array( (int) ( $in['people'] ?? 0 ), array( 1, 2 ), true ) ? (int) $in['people'] : (int) ( $ctx['people'] ?? 1 );
	$minutes = (int) ( $in['minutes'] ?? $ctx['minutes'] ?? 120 );
	$km      = (int) ( $in['km'] ?? 0 );
	return array(
		'people'    => $people,
		'budget'    => 100 * max( 0, min( 3000, (int) ( $in['budget'] ?? $ctx['budget'] ?? 80 ) ) ),
		'minutes'   => max( 30, min( 480, $minutes ) ),
		'near'      => sanitize_title( (string) ( $in['near'] ?? '' ) ),
		'km'        => in_array( $km, array( 0, 5, 10, 20, 40 ), true ) ? $km : 0,
		'indoor'    => ! empty( $in['indoor'] ) && '0' !== (string) $in['indoor'],
		'cafe'      => ! empty( $in['cafe'] ) && '0' !== (string) $in['cafe'],
		'cafe_each' => 100 * max( 0, min( 60, (int) ( $in['cafe_each'] ?? 20 ) ) ),
		'preset'    => isset( $ctx['presets'][ (string) ( $in['preset'] ?? '' ) ] ) ? (string) $in['preset'] : (string) array_key_first( (array) $ctx['presets'] ),
	);
}

/**
 * Build the plan. Returns the best outing first and up to five swaps, each
 * fully costed for the party, or an empty list with the reason.
 */
function plan( array $ctx, array $p, int $limit = MAX_PLANS ): array {
	$cfg    = config();
	$origin = point_of( $p['near'] );
	$where  = '';
	if ( '' !== $p['near'] ) {
		$t     = get_term_by( 'slug', $p['near'], 'area' );
		$where = $t instanceof \WP_Term ? html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ) : '';
	}
	$fails = array( 'party' => 0, 'stale' => 0, 'indoor' => 0, 'time' => 0, 'budget' => 0, 'far' => 0 );
	$near_miss = null; // Cheapest option that failed only on budget.
	$plans  = array();

	foreach ( data()['experiences'] as $e ) {
		if ( ! array_intersect( (array) ( $e['tags'] ?? array() ), (array) $ctx['tags'] ) ) {
			continue;
		}
		$l = listing( (string) ( $e['listing'] ?? '' ) );
		if ( ! $l ) {
			continue;
		}
		$cost = isset( $e['prices'][ (string) $p['people'] ] ) ? (int) $e['prices'][ (string) $p['people'] ] : null;
		if ( null === $cost ) {
			++$fails['party'];
			continue;
		}
		if ( ! fresh( $e, $cfg['fresh_days'] ) ) {
			++$fails['stale'];
			continue;
		}
		if ( $p['indoor'] && 'yes' !== ( $e['indoor'] ?? 'unknown' ) ) {
			++$fails['indoor'];
			continue;
		}
		$session = (int) ( $e['minutes'] ?? 0 );
		if ( $session + BUFFER > $p['minutes'] ) {
			++$fails['time'];
			continue;
		}
		$dist = ( $origin && $l['point'] ) ? km( $origin, $l['point'] ) : null;
		if ( $p['km'] > 0 && ( null === $dist || $dist > $p['km'] ) ) {
			++$fails['far'];
			continue;
		}
		if ( $cost > $p['budget'] ) {
			++$fails['budget'];
			if ( null === $near_miss || $cost < $near_miss ) {
				$near_miss = $cost;
			}
			continue;
		}
		// The café break joins only if it still fits the time and the budget.
		$cafe_cost = $p['people'] * $p['cafe_each'];
		$cafe      = $p['cafe'] && $session + BUFFER + CAFE_MIN <= $p['minutes'] && $cost + $cafe_cost <= $p['budget'];
		$cafe_note = '';
		if ( $p['cafe'] && ! $cafe ) {
			$cafe_note = $cost + $cafe_cost > $p['budget']
				? sprintf( 'Café break left out: it would take the total over your %s budget.', money( $p['budget'] ) )
				: 'Café break left out: there is not enough time for it after this session.';
		}
		$total = $cost + ( $cafe ? $cafe_cost : 0 );
		$plans[] = array(
			'id'        => (string) $e['id'],
			'listing'   => array( 'name' => $l['name'], 'url' => $l['url'], 'suburb' => $l['suburb'], 'image' => $l['image'] ),
			'service'   => (string) $e['service'],
			'tags'      => (array) $e['tags'],
			'basis'     => (string) ( $e['basis'] ?? '' ),
			'note'      => (string) ( $e['note'] ?? '' ),
			'session'   => $session,
			'cost'      => $cost,
			'cafe'      => $cafe ? array( 'cost' => $cafe_cost, 'minutes' => CAFE_MIN, 'each' => $p['cafe_each'] ) : null,
			'cafe_note' => $cafe_note,
			'total'     => $total,
			'remaining' => $p['budget'] - $total,
			'duration'  => $session + BUFFER + ( $cafe ? CAFE_MIN : 0 ),
			'km'        => null === $dist ? null : (float) number_format( $dist, 1, '.', '' ),
			'km_label'  => null === $dist ? '' : rtrim( rtrim( number_format( $dist, 1, '.', '' ), '0' ), '.' ) . ' km',
			'directions' => $l['point'] ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $l['point'][0] . ',' . $l['point'][1] ) : '',
			'source'    => preg_match( '#^https?://#', (string) ( $e['source'] ?? '' ) ) ? (string) $e['source'] : '',
			'checked'   => wp_date( 'j F Y', (int) strtotime( (string) $e['checked'] . ' 12:00:00' ) ),
		);
	}

	rank( $plans, $p );
	$plans = diverse( $plans );

	foreach ( $plans as &$pl ) {
		$pl['why'] = why( $pl, $p );
		$pl['money'] = array(
			'cost'      => money( $pl['cost'] ),
			'total'     => money( $pl['total'] ),
			'remaining' => money( max( 0, $pl['remaining'] ) ),
			'cafe'      => $pl['cafe'] ? money( $pl['cafe']['cost'] ) : '',
		);
	}
	unset( $pl );

	return array(
		'ok'        => (bool) $plans,
		'context'   => $ctx['key'],
		'people'    => $p['people'],
		'budget'    => money( $p['budget'] ),
		'minutes'   => $p['minutes'],
		'from'      => $where,
		'located'   => (bool) $origin,
		'plans'     => array_slice( $plans, 0, $limit ),
		'empty'     => $plans ? '' : empty_reason( $fails, $p, $where, $near_miss ),
		'checks'    => $fails,
	);
}

/** Order by the chosen preset, then distance, then price. */
function rank( array &$plans, array $p ): void {
	$preset = $p['preset'];
	usort(
		$plans,
		static function ( array $a, array $b ) use ( $preset, $p ): int {
			$da = $a['km'] ?? 999.0;
			$db = $b['km'] ?? 999.0;
			switch ( $preset ) {
				case 'affordable':
					return array( $a['total'], $da ) <=> array( $b['total'], $db );
				case 'nearby':
					return array( $da, $a['total'] ) <=> array( $db, $b['total'] );
				case 'two':
					// Published packages for two first, then the longest time together.
					$ca = in_array( 'couples', $a['tags'], true ) ? 0 : 1;
					$cb = in_array( 'couples', $b['tags'], true ) ? 0 : 1;
					return array( $ca, -$a['session'], $da ) <=> array( $cb, -$b['session'], $db );
				case 'first':
					// A first float: an hour, then the lowest price.
					return array( 60 === $a['session'] ? 0 : 1, $a['total'], $da ) <=> array( 60 === $b['session'] ? 0 : 1, $b['total'], $db );
				default:
					// A quiet reset: somewhere reasonably close first (in 8 km bands, so a
					// longer session two suburbs further still wins), then the most time
					// in the chair, then the price.
					return array( (int) floor( $da / 8 ), -$a['session'], $a['total'] ) <=> array( (int) floor( $db / 8 ), -$b['session'], $b['total'] );
			}
		}
	);
}

/** One plan per venue up front, so a swap means a different place. */
function diverse( array $plans ): array {
	$seen  = array();
	$first = array();
	$rest  = array();
	foreach ( $plans as $pl ) {
		$k = $pl['listing']['url'];
		if ( isset( $seen[ $k ] ) ) {
			$rest[] = $pl;
		} else {
			$seen[ $k ] = true;
			$first[]    = $pl;
		}
	}
	return array_merge( $first, $rest );
}

/** A plain, factual line on why this fits -- only things the planner checked. */
function why( array $pl, array $p ): string {
	$bits = array();
	$bits[] = $pl['remaining'] > 0
		? sprintf( 'Fits your %s budget with %s to spare', money( $p['budget'] ), money( $pl['remaining'] ) )
		: sprintf( 'Uses your %s budget exactly', money( $p['budget'] ) );
	$bits[] = sprintf( 'the %d-minute session plus arrival time fits the %s you have', $pl['session'], hours( $p['minutes'] ) );
	if ( null !== $pl['km'] ) {
		$bits[] = sprintf( 'about %s km from your starting point in a straight line', rtrim( rtrim( number_format( (float) $pl['km'], 1 ), '0' ), '.' ) );
	}
	return ucfirst( implode( ', ', $bits ) ) . '.';
}

function hours( int $m ): string {
	if ( $m % 60 ) {
		return $m . ' minutes';
	}
	return 60 === $m ? 'hour' : ( $m / 60 ) . ' hours';
}

/** Name the constraint that ruled things out, and the nearest miss. */
function empty_reason( array $f, array $p, string $where, ?int $near_miss ): string {
	arsort( $f );
	$top   = (string) array_key_first( $f );
	$party = 2 === $p['people'] ? 'for two people' : 'for one person';
	$at    = '' !== $where ? ' near ' . $where : '';
	if ( 0 === (int) ( $f[ $top ] ?? 0 ) ) {
		return 'There are no priced options for this page yet. Browse the venues below.';
	}
	switch ( $top ) {
		case 'budget':
			return sprintf( 'Nothing priced %s fits a %s budget%s.', $party, money( $p['budget'] ), $at )
				. ( $near_miss ? sprintf( ' The lowest-priced option that otherwise fits is %s.', money( $near_miss ) ) : '' );
		case 'time':
			return sprintf( 'Nothing here fits in %s once arrival time is added. Try allowing more time.', hours( $p['minutes'] ) );
		case 'far':
			return sprintf( 'Nothing priced %s is within %d km%s. Try a wider distance, or browse the venues below.', $party, $p['km'], $at );
		case 'indoor':
			return 'Nothing priced here is confirmed as indoors. Turn off indoor-only to see the rest.';
		case 'party':
			return sprintf( 'None of these venues publishes a price %s. Try %s, or browse the venues below.', $party, 2 === $p['people'] ? 'one person' : 'two people' );
		default:
			return 'The prices we hold for this page need rechecking before we can plan with them. Browse the venues below.';
	}
}

/* ----------------------------------------------------------------- REST */

function routes(): void {
	register_rest_route(
		'oria/v1',
		'/day-plan',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => array(
				'ctx' => array( 'type' => 'string', 'required' => true ),
			),
			'callback'            => __NAMESPACE__ . '\endpoint',
		)
	);
}

function endpoint( \WP_REST_Request $r ): \WP_REST_Response {
	$ctx = context( sanitize_key( (string) $r['ctx'] ) );
	if ( ! $ctx ) {
		return new \WP_REST_Response( array( 'ok' => false, 'error' => 'unavailable' ), 404 );
	}
	// A light throttle, keyed by a hash -- the address itself is never stored.
	$ip   = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$tkey = 'oria_dd_rl_' . substr( md5( $ip . wp_salt( 'nonce' ) ), 0, 16 );
	$n    = (int) get_transient( $tkey );
	if ( $n > 90 ) {
		return new \WP_REST_Response( array( 'ok' => false, 'error' => 'slow_down' ), 429 );
	}
	set_transient( $tkey, $n + 1, 5 * MINUTE_IN_SECONDS );

	$p    = prefs( $r->get_params(), $ctx );
	$ckey = 'oria_dd_' . md5( wp_json_encode( array( $ctx['key'], $p, config()['fresh_days'], filemtime( ORIA_CORE_DIR . 'data/day-designer.json' ) ) ) );
	$out  = get_transient( $ckey );
	if ( ! is_array( $out ) ) {
		$out = plan( $ctx, $p );
		set_transient( $ckey, $out, 10 * MINUTE_IN_SECONDS );
	}
	$res = new \WP_REST_Response( $out, 200 );
	$res->header( 'Cache-Control', 'no-store' );
	return $res;
}

/* --------------------------------------------------------------- render */

/**
 * Draw the widget for the current page, once. $args: variant
 * ('category' | 'guide'). Silent where the page has no context.
 */
function render( array $args = array() ): void {
	static $done = false;
	$cur = current();
	if ( $done || ! $cur ) {
		return;
	}
	$done = true;
	get_template_part( 'template-parts/day-designer', null, $cur + array( 'variant' => (string) ( $args['variant'] ?? 'category' ), 'suburbs' => suburbs() ) );
}

/**
 * The card's photo for a context: an explicit media-library URL, else the
 * tile image the site already uses for the named category or specialty
 * (Theme\term_tile, which falls back to the parent category). '' if none.
 * Licensed site photos only -- listing photos are not used here.
 */
function image( array $ctx ): string {
	$img = (array) ( $ctx['image'] ?? array() );
	if ( ! empty( $img['url'] ) && preg_match( '#^https?://#', (string) $img['url'] ) ) {
		return (string) $img['url'];
	}
	$tax  = (string) ( $img['taxonomy'] ?? 'practice' );
	$term = get_term_by( 'slug', (string) ( $img['term'] ?? 'spa' ), $tax );
	return ( $term instanceof \WP_Term && function_exists( '\Oria\Theme\term_tile' ) ) ? (string) \Oria\Theme\term_tile( $term ) : '';
}

/** Should the theme load the widget's CSS/JS on this request? */
function active(): bool {
	return null !== current();
}

/* ---------------------------------------------------------------- admin */

function admin_menu(): void {
	add_options_page( 'Day Designer', 'Day Designer', 'manage_options', 'oria-day-designer', __NAMESPACE__ . '\admin_screen' );
}

/** Rows the planner cannot use, and why. */
function issues(): array {
	$out  = array();
	$days = config()['fresh_days'];
	foreach ( data()['experiences'] as $e ) {
		$why = array();
		$l   = listing( (string) ( $e['listing'] ?? '' ) );
		if ( ! $l ) {
			$why[] = 'listing not found or not published';
		} elseif ( ! $l['point'] ) {
			$why[] = 'listing has no coordinates (distance filter skips it)';
		}
		if ( ! fresh( $e, $days ) ) {
			$why[] = sprintf( 'price checked %s, older than %d days', (string) ( $e['checked'] ?? '?' ), $days );
		}
		if ( empty( $e['prices'] ) ) {
			$why[] = 'no price for any party size';
		}
		if ( $why ) {
			$out[] = array( (string) ( $e['id'] ?? '?' ), (string) ( $e['listing'] ?? '' ), implode( '; ', $why ) );
		}
	}
	return $out;
}

function admin_screen(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$cfg = config();
	$all = data()['contexts'];
	?>
	<div class="wrap">
		<h1>Day Designer</h1>
		<p style="max-width:70ch">The planner on spa category pages, the spa Best Of guides and the homepage teaser. Its suggestions come only from <code>oria-core/data/day-designer.json</code>: a named session at a real listing, with the venue's published price for one and/or two people and the date it was checked. Add or correct rows there; nothing here changes prices.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_day_designer">
			<?php wp_nonce_field( 'oria_day_designer' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Switched on</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $cfg['enabled'] ); ?>> Show the Day Designer (untick to remove it everywhere at once)</label></td></tr>
				<tr><th scope="row">Where</th><td>
					<?php foreach ( $all as $k => $c ) : ?>
						<label style="display:block"><input type="checkbox" name="contexts[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, $cfg['contexts'], true ) ); ?>> <?php echo esc_html( (string) $c['heading'] ); ?> <code><?php echo esc_html( $k ); ?></code></label>
					<?php endforeach; ?>
					<p class="description">"spa" is the Spa &amp; Recovery category page; the others are its float, head spa and sauna pages and the matching Best Of guides.</p>
				</td></tr>
				<tr><th scope="row">Price freshness</th><td><input type="number" name="fresh_days" min="7" max="365" value="<?php echo (int) $cfg['fresh_days']; ?>"> days — older prices drop out of plans until rechecked</td></tr>
				<tr><th scope="row">Homepage teaser</th><td><label><input type="checkbox" name="teaser" value="1" <?php checked( $cfg['teaser'] ); ?>> Show the small "Plan a little spa escape" strip on the homepage</label></td></tr>
			</table>
			<?php submit_button( 'Save' ); ?>
		</form>
		<h2>Rows the planner cannot use</h2>
		<?php $iss = issues(); ?>
		<?php if ( ! $iss ) : ?>
			<p>None — every row has a published listing, coordinates and a fresh price.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:900px"><thead><tr><th>Row</th><th>Listing</th><th>Problem</th></tr></thead><tbody>
				<?php foreach ( $iss as $i ) : ?>
					<tr><td><code><?php echo esc_html( $i[0] ); ?></code></td><td><?php echo esc_html( $i[1] ); ?></td><td><?php echo esc_html( $i[2] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
	</div>
	<?php
}

function admin_save(): void {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'oria_day_designer' ) ) {
		wp_die( 'Not allowed.' );
	}
	$keys = array_keys( data()['contexts'] );
	update_option(
		OPTION,
		array(
			'enabled'    => empty( $_POST['enabled'] ) ? 0 : 1,
			'contexts'   => array_values( array_intersect( $keys, array_map( 'sanitize_key', (array) ( $_POST['contexts'] ?? array() ) ) ) ),
			'fresh_days' => max( 7, min( 365, (int) ( $_POST['fresh_days'] ?? 90 ) ) ),
			'teaser'     => empty( $_POST['teaser'] ) ? 0 : 1,
		),
		false
	);
	wp_safe_redirect( admin_url( 'options-general.php?page=oria-day-designer&updated=1' ) );
	exit;
}
