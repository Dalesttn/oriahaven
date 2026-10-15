<?php
/**
 * /offers/ and /offers/{activity}/ -- every live, checked wellness offer in
 * Perth, and one evergreen page per activity (Offers brief, 15 Oct 2026).
 *
 * Read from the same records as the category pages (Theme\active_offer() via
 * Offers\among()), so an offer reads the same everywhere. Copy lives in
 * data/offer-pages.json; which offers appear is offers.json alone.
 *
 *   rows()          every live offer, normalised for filtering: activities,
 *                   area (the Perth region), upfront amount, format, who can
 *                   buy it, pass days. Stated values only (Offers\extra_from);
 *                   a row without activities falls back to its listing's
 *                   categories, a row without an amount is "price varies".
 *   filters()       ?area= &budget= &who= &format= &sort= -- plain GET, so
 *                   the page filters without JavaScript; offers.js does the
 *                   same thing in place. Any of them makes the page noindex,
 *                   canonical to the clean address, and out of the sitemap.
 *   page()          the activity page config, or the hub's.
 *   indexable()     an activity page is indexable (and in the sitemap) only
 *                   when offer-pages.json says index and it has min_index
 *                   live offers; below that it stays up, noindexed, with an
 *                   honest empty state rather than vanishing.
 *
 * Order is editorial and never bought, except that a promoted offer
 * (offer-promotions.php) leads "Recommended" and is labelled Promoted on its
 * card. The old ?type=<practice> filter 301s to the matching activity page.
 * With no live offers at all the hub 404s; editors can still preview it.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\OffersHub;

use Oria\Core\Offers;
use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PATH        = 'offers';
const QV          = 'oria_offers_hub';
const QV_ACT      = 'oria_offers_act';
const SITEMAP     = 'offers';
const REWRITE_VER = '2';

/** Old ?type=<practice> values, to the activity page that now answers them. */
const LEGACY_TYPES = array(
	'yoga'     => 'yoga',
	'fitness'  => 'fitness',
	'spa'      => 'spa',
	'recovery' => 'sauna',
	'sound'    => 'sauna',
	'bodywork' => 'massage',
	'beauty'   => 'beauty',
);

/** A row that states no activities borrows its listing's categories. */
const PRACTICE_ACTIVITY = array(
	'yoga'     => 'yoga',
	'recovery' => 'sauna',
	'spa'      => 'spa',
	'bodywork' => 'massage',
	'beauty'   => 'beauty',
	'fitness'  => 'fitness',
);

const BUDGETS = array(
	'u50'    => 'Under $50',
	'50-100' => '$50 to $100',
	'o100'   => 'Over $100',
	'varies' => 'Price varies',
);
const WHO     = array(
	'new'      => 'New customers',
	'anyone'   => 'Anyone',
	'unstated' => 'Not specified',
);
const FORMAT_LABELS = array(
	'pass'     => 'Day pass',
	'pack'     => 'Session pack',
	'single'   => 'Single visit',
	'package'  => 'Treatment package',
	'discount' => 'Discount',
);
/** Only offered when at least two offers state weekend use; never inferred. */
const WHEN = array(
	'weekend' => 'Weekends (stated)',
);
const SORTS = array(
	'recommended' => 'Recommended',
	'price'       => 'Lowest upfront price',
	'checked'     => 'Recently checked',
	'ending'      => 'Ending soon',
);

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', static fn( array $v ): array => array_merge( $v, array( QV, QV_ACT ) ) );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_action( 'template_redirect', __NAMESPACE__ . '\legacy_redirect', 5 );
	add_action( 'template_redirect', __NAMESPACE__ . '\gate' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\seo_title' );
	add_filter( 'wpseo_opengraph_title', __NAMESPACE__ . '\seo_title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\seo_description' );
	add_filter( 'wpseo_opengraph_desc', __NAMESPACE__ . '\seo_description' );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\seo_canonical' );
	add_filter( 'wpseo_opengraph_url', __NAMESPACE__ . '\seo_canonical' );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\seo_robots' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );
	add_action( 'wp_head', __NAMESPACE__ . '\schema', 30 );
	add_action( 'init', __NAMESPACE__ . '\register_sitemap', 20 );
	add_filter( 'wpseo_sitemap_index', __NAMESPACE__ . '\sitemap_index' );
}
bootstrap();

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QV . '=1', 'top' );
	add_rewrite_rule( '^' . PATH . '/(' . implode( '|', Offers\ACTIVITIES ) . ')/?$', 'index.php?' . QV . '=1&' . QV_ACT . '=$matches[1]', 'top' );
	if ( get_option( 'oria_offers_hub_rewrite' ) !== REWRITE_VER ) {
		flush_rewrite_rules( false );
		update_option( 'oria_offers_hub_rewrite', REWRITE_VER );
	}
}

/** The hub, or an activity page; never with filters (those are added by the form). */
function url( string $activity = '' ): string {
	return home_url( '/' . PATH . '/' . ( '' !== $activity ? $activity . '/' : '' ) );
}

function is_hub(): bool {
	return (bool) get_query_var( QV );
}

/** The activity this request is for, '' on the hub. */
function current_activity(): string {
	$a = (string) get_query_var( QV_ACT );
	return in_array( $a, Offers\ACTIVITIES, true ) ? $a : '';
}

/** Kept for callers of the old hub: the activity, which is what ?type= became. */
function current_type(): string {
	return current_activity();
}

function fix_query( \WP_Query $q ): void {
	if ( $q->is_main_query() && $q->get( QV ) ) {
		$q->is_home = $q->is_front_page = $q->is_archive = $q->is_singular = $q->is_404 = false;
		$q->set( 'posts_per_page', 1 );
	}
}

/* ------------------------------------------------------------------- copy */

/** data/offer-pages.json, read once. */
function config(): array {
	static $c = null;
	if ( null === $c ) {
		$raw = is_readable( ORIA_CORE_DIR . 'data/offer-pages.json' ) ? (string) file_get_contents( ORIA_CORE_DIR . 'data/offer-pages.json' ) : '';
		$c   = '' !== $raw ? (array) json_decode( $raw, true ) : array();
	}
	return $c;
}

/** The copy for an activity page, or the hub's when $activity is ''. */
function page( string $activity = '' ): array {
	$c = config();
	if ( '' === $activity ) {
		return (array) ( $c['hub'] ?? array() );
	}
	return (array) ( $c['pages'][ $activity ] ?? array() );
}

function label( string $activity ): string {
	return (string) ( page( $activity )['label'] ?? ucfirst( $activity ) );
}

/**
 * Fill {count} {min} {max} {dmin} {dmax} from a set of rows. A string whose
 * placeholder has no value comes back '' so the caller drops it whole.
 */
function fill( string $text, array $rows ): string {
	// Paid prices only: a free trial belongs in its own sentence, not at the bottom of a price range.
	$amounts = array_values( array_filter( array_map( static fn( array $r ) => $r['amount'], $rows ), static fn( $a ): bool => is_float( $a ) && $a > 0 ) );
	$days    = array_values( array_filter( array_map( static fn( array $r ) => $r['days'], $rows ) ) );
	$vals    = array(
		'{count}' => $rows ? (string) count( $rows ) : null,
		// How many of them publish a paid price: the honest denominator for {min}/{max}.
		'{priced}' => count( $amounts ) > 1 ? (string) count( $amounts ) : null,
		'{min}'   => $amounts ? money( min( $amounts ) ) : null,
		'{max}'   => count( $amounts ) > 1 ? money( max( $amounts ) ) : null,
		'{dmin}'  => count( $days ) > 1 ? (string) min( $days ) : null,
		'{dmax}'  => count( $days ) > 1 ? (string) max( $days ) : null,
	);
	foreach ( $vals as $k => $v ) {
		if ( str_contains( $text, $k ) ) {
			if ( null === $v ) {
				return '';
			}
			$text = str_replace( $k, $v, $text );
		}
	}
	return $text;
}

/** "$39", "$29.50", "Free". */
function money( float $a ): string {
	if ( 0.0 === $a ) {
		return __( 'Free', 'oria' );
	}
	return '$' . ( floor( $a ) === $a ? number_format( $a, 0 ) : number_format( $a, 2 ) );
}

/* ------------------------------------------------------------------- data */

/**
 * Every live offer on a published listing, in editorial order (newest
 * checked, then soonest to end; a live hub promotion first), normalised for
 * the filters. Cached for the request.
 *
 * @return list<array{id:int, offer:array, cat:string, acts:list<string>, area:string, area_name:string, suburb:string, amount:?float, format:string, audience:string, days:int, promo:array, rank:int}>
 */
function rows(): array {
	static $rows = null;
	if ( null !== $rows ) {
		return $rows;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids   = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'offer_title' AND meta_value <> ''" ) );
	$promo = array();
	$rest  = array();
	foreach ( Offers\among( $ids, 500 ) as $r ) {
		$id = (int) $r['id'];
		if ( PostTypes\LISTING !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			continue;
		}
		$x    = (array) ( $r['offer']['extra'] ?? array() );
		$term = function_exists( '\Oria\Core\Categories\primary_for' ) ? \Oria\Core\Categories\primary_for( $id ) : null;
		$acts = (array) ( $x['activities'] ?? array() );
		if ( ! array_key_exists( 'activities', $x ) ) {
			foreach ( (array) wp_get_post_terms( $id, 'practice', array( 'fields' => 'slugs' ) ) as $slug ) {
				if ( isset( PRACTICE_ACTIVITY[ $slug ] ) ) {
					$acts[] = PRACTICE_ACTIVITY[ $slug ];
				}
			}
		}
		list( $area, $area_name ) = region( $id );
		$row = array(
			'id'        => $id,
			'offer'     => $r['offer'],
			'cat'       => $term instanceof \WP_Term ? (string) $term->slug : '',
			'acts'      => array_values( array_unique( $acts ) ),
			'area'      => $area,
			'area_name' => $area_name,
			'suburb'    => function_exists( '\Oria\Core\BestOf\suburb' ) ? (string) \Oria\Core\BestOf\suburb( $id ) : '',
			'amount'    => isset( $x['amount'] ) ? (float) $x['amount'] : null,
			'format'    => (string) ( $x['format'] ?? '' ),
			'audience'  => (string) ( $x['audience'] ?? '' ),
			'days'      => (int) ( $x['days'] ?? 0 ),
			'weekend'   => isset( $x['weekend'] ) ? (bool) $x['weekend'] : null,
			'promo'     => function_exists( '\Oria\Core\Offers\promotion' ) ? (array) Offers\promotion( $id, 'hub' ) : array(),
		);
		if ( $row['promo'] ) {
			$promo[] = $row;
		} else {
			$rest[] = $row;
		}
	}
	$rows = array_merge( $promo, $rest );
	foreach ( $rows as $i => $r ) {
		$rows[ $i ]['rank'] = $i;
	}
	return $rows;
}

/**
 * The Perth region a listing sits in (the area term directly under the
 * city), as [slug, name], or ['', ''].
 */
function region( int $id ): array {
	foreach ( (array) wp_get_post_terms( $id, 'area' ) as $t ) {
		$chain = array_reverse( get_ancestors( $t->term_id, 'area', 'taxonomy' ) );
		$chain[] = $t->term_id;
		if ( count( $chain ) >= 2 ) {
			$r = get_term( (int) $chain[1], 'area' );
			if ( $r instanceof \WP_Term ) {
				return array( (string) $r->slug, html_entity_decode( $r->name, ENT_QUOTES, 'UTF-8' ) );
			}
		}
	}
	return array( '', '' );
}

/**
 * Is there any live offer at all? The header asks on every page, so this
 * stops at the first live one instead of building rows().
 */
function published(): bool {
	static $yes = null;
	if ( null !== $yes ) {
		return $yes;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'offer_title' AND meta_value <> ''" ) );
	foreach ( $ids as $id ) {
		if ( PostTypes\LISTING === get_post_type( $id ) && 'publish' === get_post_status( $id ) && Offers\live( $id ) ) {
			return $yes = true;
		}
	}
	return $yes = false;
}

/** The rows for one activity, or all of them. */
function page_rows( string $activity = '' ): array {
	if ( '' === $activity ) {
		return rows();
	}
	return array_values( array_filter( rows(), static fn( array $r ): bool => in_array( $activity, $r['acts'], true ) ) );
}

/** Activity links with a live offer, in the brief's order, with counts. */
function activities(): array {
	$out = array();
	foreach ( Offers\ACTIVITIES as $a ) {
		$n = count( page_rows( $a ) );
		if ( $n ) {
			$out[] = array( 'slug' => $a, 'name' => label( $a ), 'count' => $n );
		}
	}
	return $out;
}

/** Legacy shape for any caller still asking for "chips". */
function chips(): array {
	return activities();
}

/** An activity page earns indexing: configured to, and enough live offers to compare. */
function indexable( string $activity ): bool {
	if ( '' === $activity ) {
		return published();
	}
	$p = page( $activity );
	return ! empty( $p['index'] ) && count( page_rows( $activity ) ) >= (int) ( config()['min_index'] ?? 4 );
}

/* ---------------------------------------------------------------- filters */

/**
 * The filters in this request, validated against what exists.
 *
 * @return array{area:string, budget:string, who:string, format:string, when:string, sort:string}
 */
function filters(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET filters.
	$get = static fn( string $k ): string => sanitize_key( wp_unslash( (string) ( $_GET[ $k ] ?? '' ) ) );
	$f   = array(
		'area'   => $get( 'area' ),
		'budget' => $get( 'budget' ),
		'who'    => $get( 'who' ),
		'format' => $get( 'format' ),
		'when'   => $get( 'when' ),
		'sort'   => $get( 'sort' ),
	);
	// phpcs:enable
	if ( ! isset( BUDGETS[ $f['budget'] ] ) ) {
		$f['budget'] = '';
	}
	if ( ! isset( WHO[ $f['who'] ] ) ) {
		$f['who'] = '';
	}
	if ( ! isset( FORMAT_LABELS[ $f['format'] ] ) ) {
		$f['format'] = '';
	}
	if ( ! isset( WHEN[ $f['when'] ] ) ) {
		$f['when'] = '';
	}
	if ( ! isset( SORTS[ $f['sort'] ] ) || 'recommended' === $f['sort'] ) {
		$f['sort'] = '';
	}
	if ( '' !== $f['area'] && ! preg_match( '/^[a-z0-9-]+$/', $f['area'] ) ) {
		$f['area'] = '';
	}
	return $f;
}

/** Any filter or sort in the request (they make the page a variant). */
function filtered(): bool {
	return (bool) array_filter( filters() );
}

function budget_band( ?float $amount ): string {
	if ( null === $amount ) {
		return 'varies';
	}
	return $amount < 50 ? 'u50' : ( $amount <= 100 ? '50-100' : 'o100' );
}

function who_key( string $audience ): string {
	return '' === $audience ? 'unstated' : $audience;
}

function matches( array $r, array $f ): bool {
	return ( '' === $f['area'] || $r['area'] === $f['area'] )
		&& ( '' === $f['budget'] || budget_band( $r['amount'] ) === $f['budget'] )
		&& ( '' === $f['who'] || who_key( $r['audience'] ) === $f['who'] )
		&& ( '' === $f['format'] || $r['format'] === $f['format'] )
		&& ( '' === $f['when'] || true === $r['weekend'] );
}

/** Sort a set of rows. Unknown prices and end dates go last, never first. */
function sorted( array $rows, string $sort ): array {
	$key = static function ( array $r ) use ( $sort ): array {
		switch ( $sort ) {
			case 'price':
				return array( null === $r['amount'] ? 1 : 0, (float) $r['amount'], $r['rank'] );
			case 'checked':
				return array( - (int) str_replace( '-', '', (string) $r['offer']['checked'] ), $r['rank'] );
			case 'ending':
				$u = (string) $r['offer']['until'];
				return array( '' === $u ? 1 : 0, (int) str_replace( '-', '', $u ), $r['rank'] );
		}
		return array( $r['rank'] );
	};
	usort( $rows, static fn( array $a, array $b ): int => $key( $a ) <=> $key( $b ) );
	return $rows;
}

/**
 * The filter options this set of rows can actually answer, with counts --
 * an option that would always return nothing is not offered.
 *
 * @return array{area:array<string,string>, budget:array<string,string>, who:array<string,string>, format:array<string,string>, when:array<string,string>}
 */
function options( array $rows ): array {
	$o = array( 'area' => array(), 'budget' => array(), 'who' => array(), 'format' => array(), 'when' => array() );
	$weekend = count( array_filter( $rows, static fn( array $r ): bool => true === $r['weekend'] ) );
	if ( $weekend >= 2 ) {
		$o['when'] = WHEN;
	}
	foreach ( $rows as $r ) {
		if ( '' !== $r['area'] ) {
			$o['area'][ $r['area'] ] = $r['area_name'];
		}
		$o['budget'][ budget_band( $r['amount'] ) ] = true;
		$o['who'][ who_key( $r['audience'] ) ]      = true;
		if ( '' !== $r['format'] ) {
			$o['format'][ $r['format'] ] = true;
		}
	}
	asort( $o['area'] );
	foreach ( array( 'budget' => BUDGETS, 'who' => WHO, 'format' => FORMAT_LABELS ) as $k => $labels ) {
		$o[ $k ] = array_intersect_key( $labels, $o[ $k ] );
	}
	return $o;
}

/**
 * Transparent picks for an unfiltered page: each is a stated, checkable
 * criterion, worked out from that page's live offers. Never a promoted
 * offer. Needs six offers to choose from; a pick whose criterion nothing
 * meets (no dated offer, no pass) is simply not made, and fewer than two
 * picks show nothing.
 *
 * @return list<array{label:string, why:string, row:array}>
 */
function picks( ?array $rows = null ): array {
	$pool = array_values( array_filter( $rows ?? rows(), static fn( array $r ): bool => ! $r['promo'] ) );
	if ( count( $pool ) < 6 ) {
		return array();
	}
	$out  = array();
	$used = array();
	$take = static function ( array $cands, string $label, string $why ) use ( &$out, &$used ): void {
		foreach ( $cands as $r ) {
			if ( ! isset( $used[ $r['id'] ] ) ) {
				$used[ $r['id'] ] = true;
				$out[]            = array( 'label' => $label, 'why' => $why, 'row' => $r );
				return;
			}
		}
	};
	$priced = array_values( array_filter( $pool, static fn( array $r ): bool => null !== $r['amount'] ) );
	usort( $priced, static fn( array $a, array $b ): int => $a['amount'] <=> $b['amount'] );
	$take( $priced, __( 'Lowest upfront price', 'oria' ), __( 'The smallest fixed price to start of any offer here.', 'oria' ) );

	$passes = array_values( array_filter( $pool, static fn( array $r ): bool => $r['days'] > 0 ) );
	usort( $passes, static fn( array $a, array $b ): int => array( $b['days'], $a['amount'] ?? INF ) <=> array( $a['days'], $b['amount'] ?? INF ) );
	$take( $passes, __( 'Longest intro pass', 'oria' ), __( 'The most days of any pass on this page.', 'oria' ) );

	$dated = array_values( array_filter( $pool, static fn( array $r ): bool => '' !== (string) $r['offer']['until'] ) );
	usort( $dated, static fn( array $a, array $b ): int => strcmp( (string) $a['offer']['until'], (string) $b['offer']['until'] ) );
	$take( $dated, __( 'Ends soonest', 'oria' ), __( 'The dated offer closest to its end date.', 'oria' ) );

	return count( $out ) >= 2 ? $out : array();
}

/* ------------------------------------------------------------------ serve */

function serving(): bool {
	if ( ! is_hub() ) {
		return false;
	}
	$a = current_activity();
	if ( '' !== $a ) {
		// An activity page is evergreen: it stays up while empty, if it is one we write for.
		return (bool) page( $a ) && ( published() || current_user_can( 'edit_posts' ) );
	}
	return published() || current_user_can( 'edit_posts' );
}

/** /offers/?type=yoga (the old filter) -> /offers/yoga/, keeping any other filters. */
function legacy_redirect(): void {
	if ( ! is_hub() || '' !== current_activity() || ! isset( $_GET['type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$type = sanitize_key( wp_unslash( (string) $_GET['type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$keep = array_intersect_key( filters(), array_flip( array( 'area', 'budget', 'who', 'format', 'when', 'sort' ) ) );
	$to   = url( LEGACY_TYPES[ $type ] ?? '' );
	wp_safe_redirect( add_query_arg( array_filter( $keep ), $to ), 301 );
	exit;
}

function gate(): void {
	if ( ! is_hub() || serving() ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
}

function template( string $t ): string {
	return serving() ? ( locate_template( array( 'oria-offers.php' ) ) ?: $t ) : $t;
}

/** The clean address of the page being served. */
function self_url(): string {
	return url( current_activity() );
}

function seo_title( $t ) {
	if ( ! serving() ) {
		return $t;
	}
	$a = current_activity();
	$p = page( $a );
	return (string) ( $p['title'] ?? sprintf( /* translators: %s: activity */ __( '%s Offers in Perth | Oria Haven', 'oria' ), label( $a ) ) );
}

function h1(): string {
	$a = current_activity();
	$p = page( $a );
	return (string) ( $p['h1'] ?? sprintf( /* translators: %s: activity, lower case */ __( '%s offers in Perth', 'oria' ), label( $a ) ) );
}

function core_title( array $parts ): array {
	if ( serving() ) {
		$parts['title'] = h1();
	}
	return $parts;
}

function seo_description( $d ) {
	if ( ! serving() ) {
		return $d;
	}
	$a    = current_activity();
	$text = fill( (string) ( page( $a )['description'] ?? '' ), page_rows( $a ) );
	if ( '' === $text ) {
		$text = sprintf( /* translators: %s: noun, e.g. "massage offers" */ __( 'Current %s from Perth wellness businesses, each checked on the business’s own site, with prices, inclusions and conditions upfront.', 'oria' ), (string) ( page( $a )['noun'] ?? 'wellness offers' ) );
	}
	return $text;
}

function seo_canonical( $c ) {
	return serving() ? self_url() : $c;
}

/**
 * noindex,follow for any filtered or sorted variant, and for an activity
 * page that has not earned indexing. Nothing here is robots.txt-blocked, so
 * crawlers can always read this.
 */
function seo_robots( $r ) {
	if ( ! serving() ) {
		return $r;
	}
	return filtered() || ! indexable( current_activity() ) ? 'noindex, follow' : 'index, follow';
}

/**
 * CollectionPage + BreadcrumbList + ItemList of the businesses' listings.
 * Yoast prints only the WebSite node on this route, so nothing here
 * duplicates it. No Offer or Product nodes: one page about many businesses
 * is neither, and nothing is invented for a rich result.
 */
function schema(): void {
	if ( ! serving() ) {
		return;
	}
	$a     = current_activity();
	$rows  = page_rows( $a );
	$self  = self_url();
	$crumb = array(
		array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
		array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Offers', 'item' => url() ),
	);
	if ( '' !== $a ) {
		$crumb[] = array( '@type' => 'ListItem', 'position' => 3, 'name' => label( $a ), 'item' => $self );
	}
	$items = array();
	foreach ( $rows as $i => $r ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => (string) get_permalink( $r['id'] ),
			'name'     => html_entity_decode( get_the_title( $r['id'] ), ENT_QUOTES, 'UTF-8' ) . ': ' . (string) $r['offer']['title'],
		);
	}
	$graph = array(
		array(
			'@type'       => 'CollectionPage',
			'@id'         => $self . '#page',
			'url'         => $self,
			'name'        => h1(),
			'description' => seo_description( '' ),
			'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
			'breadcrumb'  => array( '@id' => $self . '#breadcrumb' ),
			'mainEntity'  => array( '@id' => $self . '#offers' ),
			'inLanguage'  => 'en-AU',
		),
		array( '@type' => 'BreadcrumbList', '@id' => $self . '#breadcrumb', 'itemListElement' => $crumb ),
		array( '@type' => 'ItemList', '@id' => $self . '#offers', 'numberOfItems' => count( $items ), 'itemListElement' => $items ),
	);
	printf( "<script type=\"application/ld+json\">%s</script>\n", wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

/* ---------------------------------------------------------------- sitemap */

/** The hub and every indexable activity page, dated by their newest check. */
function sitemap_entries(): array {
	$out = array();
	foreach ( array_merge( array( '' ), Offers\ACTIVITIES ) as $a ) {
		if ( ! indexable( $a ) ) {
			continue;
		}
		$newest = '';
		foreach ( page_rows( $a ) as $r ) {
			$newest = max( $newest, (string) $r['offer']['checked'] );
		}
		$out[] = array(
			'loc' => url( $a ),
			'mod' => '' !== $newest ? wp_date( 'c', (int) strtotime( $newest . ' 00:00:00 ' . wp_timezone_string() ) ) : gmdate( 'c' ),
		);
	}
	return $out;
}

function register_sitemap(): void {
	if ( ! isset( $GLOBALS['wpseo_sitemaps'] ) || ! method_exists( $GLOBALS['wpseo_sitemaps'], 'register_sitemap' ) ) {
		return;
	}
	$GLOBALS['wpseo_sitemaps']->register_sitemap( SITEMAP, __NAMESPACE__ . '\build_sitemap' );
}

function build_sitemap(): void {
	$sm = $GLOBALS['wpseo_sitemaps'] ?? null;
	if ( ! $sm || ! isset( $sm->renderer ) ) {
		return;
	}
	$sm->set_sitemap( $sm->renderer->get_sitemap( sitemap_entries(), SITEMAP, 1 ) );
}

function sitemap_index( $xml ) {
	$entries = sitemap_entries();
	if ( ! $entries ) {
		return $xml;
	}
	$newest = max( array_column( $entries, 'mod' ) );
	return $xml . sprintf(
		"<sitemap><loc>%s</loc><lastmod>%s</lastmod></sitemap>\n",
		esc_url( home_url( '/' . SITEMAP . '-sitemap.xml' ) ),
		esc_html( $newest )
	);
}
