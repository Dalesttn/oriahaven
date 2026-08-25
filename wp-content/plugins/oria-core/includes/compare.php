<?php
/**
 * /compare/ — put two to four wellness experiences side by side.
 *
 * The engine is a registry, not a taxonomy query: data/compare.json holds
 * a curated list of comparable experiences with scored attributes, because
 * the comparable units cut across the site's own structure — "yoga vs
 * pilates" is one practice term, "float vs sauna" is two specialties.
 * A registry can name whatever a person actually weighs up.
 *
 * Every attribute in that file describes the room, never the outcome:
 * intensity, guidance, group size, price — things a visitor could verify
 * by standing there. "Stress relief" and "mental wellbeing" scores are
 * deliberately absent; a scored table is a therapeutic claim in its most
 * quotable form, and this site does not make therapeutic claims.
 *
 * Selection travels as ?with=yoga,pilates so a comparison is shareable,
 * and the page canonicals to /compare/ so the variants never compete
 * with it. Built as a route, like the hub: it ships in git and exists on
 * production the moment the code lands.
 */

declare(strict_types=1);

namespace Oria\Core\Compare;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_compare';
const PATH      = 'compare';
const DATA_FILE = 'data/compare.json';
const SITEMAP   = 'compare';
const MIN_PICK  = 2;
const MAX_PICK  = 4;
const REWRITE_V = '1';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 10 );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 20 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );
	add_action( 'template_redirect', __NAMESPACE__ . '\bridge', 5 );

	add_filter( 'wpseo_title', __NAMESPACE__ . '\title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description' );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );

	// A route is not a post, so Yoast never sees it: without this the page
	// is in no sitemap at all, the same blind spot /practices/ has.
	add_action( 'init', __NAMESPACE__ . '\register_sitemap', 20 );
	add_filter( 'wpseo_sitemap_index', __NAMESPACE__ . '\sitemap_index' );
}

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QUERY_VAR . '=1', 'top' );
}

function maybe_flush(): void {
	if ( get_option( 'oria_compare_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_compare_rewrite_v', REWRITE_V );
	}
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

function is_compare(): bool {
	return (bool) get_query_var( QUERY_VAR );
}

function fix_query( \WP_Query $q ): void {
	if ( ! $q->is_main_query() || ! $q->get( QUERY_VAR ) ) {
		return;
	}
	$q->is_home       = false;
	$q->is_front_page = false;
	$q->is_archive    = false;
	$q->is_singular   = false;
	$q->is_404        = false;
	$q->set( 'posts_per_page', 1 );
}

/**
 * The no-JS picker submits pick[] checkboxes; the engine reads ?with=.
 * Bridge one to the other with a redirect, here rather than in the
 * template because by template time the headers have gone out.
 */
function bridge(): void {
	if ( ! is_compare() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	if ( isset( $_GET['pick'] ) && is_array( $_GET['pick'] ) && ! isset( $_GET['with'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each id is sanitize_title()d.
		$ids = implode( ',', array_filter( array_map( 'sanitize_title', array_slice( (array) wp_unslash( $_GET['pick'] ), 0, MAX_PICK ) ) ) );
		wp_safe_redirect( home_url( '/' . PATH . '/' . ( '' !== $ids ? '?with=' . rawurlencode( $ids ) . '#result' : '' ) ) );
		exit;
	}

	// The same trick for the places picker, which posts slugs not ids.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	if ( isset( $_GET['place'] ) && is_array( $_GET['place'] ) && ! isset( $_GET[ PLACES_VAR ] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each slug is sanitize_title()d.
		$slugs = implode( ',', array_filter( array_map( 'sanitize_title', array_slice( (array) wp_unslash( $_GET['place'] ), 0, MAX_PICK ) ) ) );
		wp_safe_redirect( home_url( '/' . PATH . '/' . ( '' !== $slugs ? '?' . PLACES_VAR . '=' . rawurlencode( $slugs ) . '#result' : '' ) ) );
		exit;
	}
}

function template( string $template ): string {
	if ( ! is_compare() ) {
		return $template;
	}
	$found = locate_template( array( 'oria-compare.php' ) );
	return $found ?: $template;
}

/* ------------------------------------------------------------------- data */

/**
 * The registry, parsed once per request.
 *
 * @return array{attributes: array, experiences: array}
 */
function registry(): array {
	static $data = null;
	if ( null !== $data ) {
		return $data;
	}
	$path = trailingslashit( ORIA_CORE_DIR ) . DATA_FILE;
	$raw  = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	$json = json_decode( $raw, true );
	$data = array(
		'attributes'  => is_array( $json['attributes'] ?? null ) ? $json['attributes'] : array(),
		'groups'      => is_array( $json['groups'] ?? null ) ? $json['groups'] : array(),
		'experiences' => is_array( $json['experiences'] ?? null ) ? $json['experiences'] : array(),
	);
	return $data;
}

/** @return array<int, array> every experience, in registry order */
function experiences(): array {
	return registry()['experiences'];
}

/**
 * Groups are comparisons WITHIN a category — types of massage, styles of
 * yoga — as opposed to the top-level set, which compares one category
 * against another.
 *
 * A group carries its own attribute schema because the discriminating
 * questions change with the scale. "Movement" and "Can be practised at
 * home" separate yoga from float therapy; they are identical for all
 * seven kinds of massage, and a table of rows that all agree is a table
 * that tells you nothing. Pressure, what you wear and whether your fund
 * pays are what actually differ down there.
 *
 * @return array<string, array>
 */
function groups(): array {
	return registry()['groups'];
}

function group( string $id ): ?array {
	$g = groups();
	return isset( $g[ $id ] ) && is_array( $g[ $id ] ) ? $g[ $id ] : null;
}

/**
 * The experiences in one group, or the top-level set when $group is ''.
 *
 * @return array<int, array>
 */
function experiences_in( string $group = '' ): array {
	$out = array();
	foreach ( experiences() as $e ) {
		if ( (string) ( $e['group'] ?? '' ) === $group ) {
			$out[] = $e;
		}
	}
	return $out;
}

/**
 * The attribute sections for a group, in display order. The top-level
 * schema is the fallback, so an unknown group degrades to a real table
 * rather than an empty one.
 */
function sections( string $group = '' ): array {
	$g = '' !== $group ? group( $group ) : null;
	if ( $g && is_array( $g['attributes'] ?? null ) ) {
		return $g['attributes'];
	}
	return registry()['attributes'];
}

/**
 * The group a set of picks belongs to. Picks never span groups — see
 * picked(), which enforces it — so the first one decides.
 *
 * @param array<int, array> $picked
 */
function group_of( array $picked ): string {
	return $picked ? (string) ( $picked[0]['group'] ?? '' ) : '';
}

/**
 * The experiences a request has picked, validated against the registry.
 *
 * Unknown ids are dropped rather than erroring — a stale shared link
 * degrades to whatever part of it still resolves. Order follows the
 * query string, so a shared comparison keeps its author's ordering.
 *
 * @return array<int, array>
 */
function picked(): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	$raw = isset( $_GET['with'] ) && is_string( $_GET['with'] ) ? sanitize_text_field( wp_unslash( $_GET['with'] ) ) : '';
	if ( '' === $raw ) {
		return array();
	}
	$by_id = array();
	foreach ( experiences() as $e ) {
		$by_id[ (string) $e['id'] ] = $e;
	}
	$out = array();
	foreach ( array_unique( array_filter( array_map( 'sanitize_title', explode( ',', $raw ) ) ) ) as $id ) {
		if ( ! isset( $by_id[ $id ] ) ) {
			continue;
		}
		/*
		 * Picks may not span groups. Each group scores its own attributes,
		 * so a mixed set would put remedial massage in a column asking how
		 * much of the session is spent stretching — a blank cell that reads
		 * as a fact about the modality rather than a gap in the data.
		 * The first valid pick fixes the group; later strays are dropped,
		 * the same forgiveness unknown ids already get.
		 */
		if ( $out && (string) ( $by_id[ $id ]['group'] ?? '' ) !== group_of( $out ) ) {
			continue;
		}
		$out[] = $by_id[ $id ];
		if ( count( $out ) === MAX_PICK ) {
			break;
		}
	}
	return count( $out ) >= MIN_PICK ? $out : array();
}

/**
 * The group the page is showing: whichever the picks belong to, else an
 * explicit ?group= for the empty picker, else the top-level set.
 */
function current_group(): string {
	$p = picked();
	if ( $p ) {
		return group_of( $p );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	$raw = isset( $_GET['group'] ) && is_string( $_GET['group'] ) ? sanitize_title( wp_unslash( $_GET['group'] ) ) : '';
	return ( '' !== $raw && group( $raw ) ) ? $raw : '';
}

/**
 * The comparison, summarised in the site's voice: which of the picked
 * experiences sits at each descriptive extreme. Derived entirely from the
 * registry — no outcomes, no recommendations, just where each one stands.
 *
 * @param array<int, array> $picked
 * @return array<int, string>
 */
function summary( array $picked ): array {
	if ( count( $picked ) < MIN_PICK ) {
		return array();
	}
	$lines = array();

	$by = static function ( string $key ) use ( $picked ): array {
		$sorted = $picked;
		usort( $sorted, static fn( array $a, array $b ): int => (int) ( $b['attributes'][ $key ] ?? 0 ) <=> (int) ( $a['attributes'][ $key ] ?? 0 ) );
		return $sorted;
	};

	/*
	 * Groups score different attributes, so every rule below has to ask
	 * whether its attribute is even on this table. Without the guard a
	 * massage comparison reads a missing "intensity" as zero and reports
	 * a difference that does not exist.
	 */
	$has = static function ( string $key ) use ( $picked ): bool {
		foreach ( $picked as $e ) {
			if ( ! isset( $e['attributes'][ $key ] ) ) {
				return false;
			}
		}
		return true;
	};

	$hard = $by( 'intensity' );
	if ( $has( 'intensity' ) && (int) $hard[0]['attributes']['intensity'] !== (int) end( $hard )['attributes']['intensity'] ) {
		$lines[] = sprintf(
			/* translators: 1: hardest experience, 2: gentlest experience */
			__( '%1$s asks the most of the body here; %2$s the least.', 'oria' ),
			$hard[0]['label'],
			end( $hard )['label']
		);
	}

	// The same shape one scale down: within a category, pressure is what
	// separates one table from the next.
	$firm = $by( 'pressure' );
	if ( $has( 'pressure' ) && (int) $firm[0]['attributes']['pressure'] !== (int) end( $firm )['attributes']['pressure'] ) {
		$lines[] = sprintf(
			/* translators: 1: firmest experience, 2: lightest experience */
			__( '%1$s works the firmest of these; %2$s is the lightest touch.', 'oria' ),
			$firm[0]['label'],
			end( $firm )['label']
		);
	}

	// Recovery's own axis. Not how hard the body works — you mostly sit
	// still — but how hard it is to stay in the room, which is the thing
	// anyone choosing between a sauna and an ice bath actually wants told.
	$stay = $by( 'demand' );
	if ( $has( 'demand' ) && (int) $stay[0]['attributes']['demand'] !== (int) end( $stay )['attributes']['demand'] ) {
		$lines[] = sprintf(
			/* translators: 1: hardest to sit through, 2: easiest to sit through */
			__( '%1$s takes the most getting through; %2$s you could sit in all day.', 'oria' ),
			$stay[0]['label'],
			end( $stay )['label']
		);
	}

	$quiet = $by( 'quiet' );
	if ( $has( 'quiet' ) && (int) $quiet[0]['attributes']['quiet'] !== (int) end( $quiet )['attributes']['quiet'] ) {
		$lines[] = sprintf(
			/* translators: %s: quietest experience */
			__( '%s is the quietest room of the set.', 'oria' ),
			$quiet[0]['label']
		);
	}

	$social = $by( 'social' );
	// "Alone" is only claimed at a score of 1 — a sound bath is quiet, but
	// a room of forty people lying down is not solitude.
	if ( $has( 'social' ) && (int) $social[0]['attributes']['social'] >= 3 && 1 === (int) end( $social )['attributes']['social'] ) {
		$lines[] = sprintf(
			/* translators: 1: most social experience, 2: most solitary experience */
			__( '%1$s is the most social; %2$s you do essentially alone.', 'oria' ),
			$social[0]['label'],
			end( $social )['label']
		);
	}

	// Only name a cheapest when there IS one: with three of four sharing a
	// band, crowning whichever sorted first would be an invented distinction.
	// band_rank(), not strlen() — see the note there; "Free" is four
	// characters and used to sort level with "$$$$".
	$band_of = static fn( array $e ): int => (int) ( band_rank( (string) ( $e['attributes']['price'] ?? '' ) ) ?? -1 );
	$bands   = array_map( $band_of, $picked );
	if ( ! in_array( -1, $bands, true ) && count( array_unique( $bands ) ) > 1 ) {
		$min_n = count( array_keys( $bands, min( $bands ), true ) );
		$max_n = count( array_keys( $bands, max( $bands ), true ) );
		$cheap = $picked;
		usort( $cheap, static fn( array $a, array $b ): int => $band_of( $a ) <=> $band_of( $b ) );
		if ( 1 === $min_n && 1 === $max_n ) {
			$lines[] = sprintf(
				/* translators: 1: cheapest experience, 2: dearest experience */
				__( '%1$s is usually the cheapest way in; %2$s sits at the top of the range.', 'oria' ),
				$cheap[0]['label'],
				end( $cheap )['label']
			);
		} elseif ( 1 === $max_n ) {
			$lines[] = sprintf(
				/* translators: %s: dearest experience */
				__( '%s sits at the top of the price range here; the others are closer together.', 'oria' ),
				end( $cheap )['label']
			);
		}
	}

	return $lines;
}

/**
 * The listings behind one experience, resolved from its registry URL.
 *
 * The URL is already the experience's identity — a category, a facet or a
 * specialty page — so it doubles as the query, and the registry needs no
 * second field that could drift out of step with the first.
 *
 * @param array $e a registry experience
 * @return array<int, int> listing post ids
 */
function listings_for( array $e ): array {
	$url = (string) ( $e['url'] ?? '' );

	// /practices/{practice}/{facet}/ — the facet's own subset.
	if ( preg_match( '~^/practices/([^/]+)/([^/]+)/$~', $url, $m ) ) {
		$t = get_term_by( 'slug', $m[1], 'practice' );
		if ( $t instanceof \WP_Term && function_exists( '\Oria\Core\PracticesIndex\resolve_facet' ) ) {
			$f = \Oria\Core\PracticesIndex\resolve_facet( $t, $m[2] );
			if ( null !== $f ) {
				return array_map( 'intval', \Oria\Core\PracticesIndex\facet_ids( $t, $f ) );
			}
		}
	}

	// /practices/{practice}/ — the whole category, rolled up.
	if ( preg_match( '~^/practices/([^/]+)/$~', $url, $m ) ) {
		$t = get_term_by( 'slug', $m[1], 'practice' );
		if ( $t instanceof \WP_Term && function_exists( '\Oria\Core\Intents\listings_in' ) ) {
			return array_map( 'intval', \Oria\Core\Intents\listings_in( $t ) );
		}
	}

	// /perth/{specialty}/ — everything carrying the specialty term.
	if ( preg_match( '~^/perth/([^/]+)/$~', $url, $m ) ) {
		$ids = get_posts(
			array(
				'post_type'      => 'listing',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'specialty',
						'field'    => 'slug',
						'terms'    => $m[1],
					),
				),
			)
		);
		return array_map( 'intval', $ids );
	}

	return array();
}

/**
 * A few listings to try, drawn round-robin across the compared experiences
 * so a three-card row covers three different categories rather than three
 * yoga studios. Shuffled, so repeat visits meet different practices —
 * though a page cache will hold one draw for as long as it holds the page,
 * which is fine: the sample never claims to be a ranking.
 *
 * @param array<int, array> $picked
 * @return array<int, int> listing post ids
 */
function try_listings( array $picked, int $n = 3 ): array {
	$pools = array();
	foreach ( $picked as $e ) {
		$ids = listings_for( $e );
		if ( $ids ) {
			shuffle( $ids );
			$pools[] = $ids;
		}
	}
	if ( ! $pools ) {
		return array();
	}
	$out = array();
	for ( $round = 0; $round < 10 && count( $out ) < $n; $round++ ) {
		foreach ( $pools as $pool ) {
			if ( isset( $pool[ $round ] ) && ! in_array( $pool[ $round ], $out, true ) ) {
				$out[] = $pool[ $round ];
				if ( count( $out ) === $n ) {
					break;
				}
			}
		}
	}
	return $out;
}

/* --------------------------------------------------- places (real providers) */

/*
 * The third scale: not which practice, nor which kind of it, but which of
 * these actual businesses.
 *
 * This one compares named third parties, so it plays by stricter rules than
 * the registry tables above.
 *
 * 1. ONLY FACTS ON FILE. Every row below reads a field we actually hold.
 *    Nothing is inferred, scored or averaged into a judgement.
 * 2. NEVER A CROSS. A missing value renders as "Not listed", never as a
 *    "no". 317 of 331 listings are unclaimed — their owners have never told
 *    us anything — so an absence is a fact about our data entry, and
 *    printing it as a fact about their business would be a lie that costs
 *    a real local trader work. This is the audience system's existing rule:
 *    absence is not a negative.
 * 3. NO WINNER. The site tells people "it counts, it never ranks" on every
 *    category page, and promises in the Finder that nobody can pay their
 *    way up. A "best match" trophy over a named business breaks both. The
 *    summary states where they differ and stops there; choosing is the
 *    Wellness Finder's job, and it discloses how it breaks ties.
 */

const PLACES_VAR = 'places';
const SCOPE_VAR  = 'in';

/** The sentinel for every empty cell. Never "No". */
function unknown(): string {
	return __( 'Not listed', 'oria' );
}

/**
 * A price band as a number, or null when it cannot be read.
 *
 * Counting characters is NOT good enough, and got this wrong: the
 * directory's bands include "Free", whose four characters sorted level
 * with "$$$$" and had the page reporting a free service as the dearest of
 * the set. Ten live listings carry it. Free is zero; everything else is
 * how many dollar signs it has; anything unrecognised is unknown and takes
 * its row out of the comparison rather than guessing.
 */
function band_rank( string $band ): ?int {
	$band = trim( $band );
	if ( '' === $band ) {
		return null;
	}
	if ( 0 === strcasecmp( $band, 'free' ) ) {
		return 0;
	}
	$n = substr_count( $band, '$' );
	return ( $n > 0 && strlen( $band ) === $n ) ? $n : null;
}

/**
 * The listings a request has picked, by slug, in the order given.
 *
 * @return array<int, \WP_Post>
 */
function places(): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	$raw = isset( $_GET[ PLACES_VAR ] ) && is_string( $_GET[ PLACES_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ PLACES_VAR ] ) ) : '';
	if ( '' === $raw ) {
		return array();
	}
	$slugs = array_slice( array_unique( array_filter( array_map( 'sanitize_title', explode( ',', $raw ) ) ) ), 0, MAX_PICK );
	if ( count( $slugs ) < MIN_PICK ) {
		return array();
	}
	$posts = get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'post_name__in'  => $slugs,
			'posts_per_page' => MAX_PICK,
		)
	);
	// Restore the caller's ordering, which post_name__in does not preserve.
	usort(
		$posts,
		static fn( \WP_Post $a, \WP_Post $b ): int =>
			array_search( $a->post_name, $slugs, true ) <=> array_search( $b->post_name, $slugs, true )
	);
	return count( $posts ) >= MIN_PICK ? $posts : array();
}

/** The category a picker is scoped to, from ?in=. */
function place_scope(): ?\WP_Term {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	$raw = isset( $_GET[ SCOPE_VAR ] ) && is_string( $_GET[ SCOPE_VAR ] ) ? sanitize_title( wp_unslash( $_GET[ SCOPE_VAR ] ) ) : '';
	if ( '' === $raw ) {
		return null;
	}
	$t = get_term_by( 'slug', $raw, 'practice' );
	return $t instanceof \WP_Term ? $t : null;
}

/** The listings a scoped picker offers, rolled up like the category pages do. */
function scope_listings( \WP_Term $term ): array {
	$ids = function_exists( '\Oria\Core\Intents\listings_in' )
		? array_map( 'intval', \Oria\Core\Intents\listings_in( $term ) )
		: array();
	if ( ! $ids ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'post__in'       => $ids,
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
}

/** The /compare/ address for a set of listing slugs. */
function places_url( array $slugs ): string {
	$slugs = array_slice( array_values( array_unique( array_filter( $slugs ) ) ), 0, MAX_PICK );
	if ( count( $slugs ) < MIN_PICK ) {
		return home_url( '/' . PATH . '/' );
	}
	return home_url( '/' . PATH . '/?' . PLACES_VAR . '=' . implode( ',', array_map( 'rawurlencode', $slugs ) ) );
}

/**
 * The comparison table for real providers. Each row reads one field we
 * hold; anything empty becomes unknown(), never a "no".
 *
 * @param array<int, \WP_Post> $posts
 * @return array<int, array{label: string, hint: string, values: array<int, string>}>
 */
function place_rows( array $posts ): array {
	$terms = static function ( int $id, string $tax ): string {
		$names = wp_get_post_terms( $id, $tax, array( 'fields' => 'names' ) );
		return ( ! is_wp_error( $names ) && $names ) ? implode( ', ', $names ) : unknown();
	};

	$rows = array(
		array( 'key' => 'where', 'label' => __( 'Where', 'oria' ), 'hint' => '' ),
		array( 'key' => 'price', 'label' => __( 'Price band', 'oria' ), 'hint' => __( 'The directory\'s own bands', 'oria' ) ),
		array( 'key' => 'rating', 'label' => __( 'Rating', 'oria' ), 'hint' => __( 'From Google, not from us', 'oria' ) ),
		array( 'key' => 'format', 'label' => __( 'In person or online', 'oria' ), 'hint' => '' ),
		array( 'key' => 'cats', 'label' => __( 'Listed under', 'oria' ), 'hint' => '' ),
		array( 'key' => 'spec', 'label' => __( 'Specialties listed', 'oria' ), 'hint' => '' ),
		array( 'key' => 'svc', 'label' => __( 'Services listed', 'oria' ), 'hint' => '' ),
		array( 'key' => 'confirmed', 'label' => __( 'Details confirmed by the business', 'oria' ), 'hint' => __( 'Everything above comes from us until they do', 'oria' ) ),
	);

	$out = array();
	foreach ( $rows as $row ) {
		$values = array();
		foreach ( $posts as $p ) {
			$id = (int) $p->ID;
			switch ( $row['key'] ) {
				case 'where':
					$areas    = wp_get_post_terms( $id, 'area', array( 'fields' => 'names' ) );
					$values[] = ( ! is_wp_error( $areas ) && $areas ) ? implode( ', ', array_slice( $areas, 0, 2 ) ) : unknown();
					break;

				case 'price':
					$band     = trim( (string) get_field( 'price_band', $id ) );
					$from     = (float) get_field( 'price_from', $id );
					$values[] = '' === $band
						? unknown()
						: ( $from > 0
							/* translators: 1: price band, 2: lowest price */
							? sprintf( __( '%1$s — from $%2$s', 'oria' ), $band, number_format_i18n( $from ) )
							: $band );
					break;

				case 'rating':
					$r = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( $id ) : array( 'rating' => 0, 'count' => 0 );
					$values[] = ( (float) $r['rating'] > 0 )
						/* translators: 1: rating out of five, 2: number of reviews */
						? sprintf( __( '%1$s from %2$s reviews', 'oria' ), number_format_i18n( (float) $r['rating'], 1 ), number_format_i18n( (int) $r['count'] ) )
						: __( 'No rating on file', 'oria' );
					break;

				case 'format':
					$f   = (string) ( get_field( 'format', $id ) ?: 'in-person' );
					$map = array(
						'in-person' => __( 'In person', 'oria' ),
						'online'    => __( 'Online', 'oria' ),
						'both'      => __( 'Both', 'oria' ),
					);
					$values[] = $map[ $f ] ?? $f;
					break;

				case 'cats':
					$values[] = $terms( $id, 'practice' );
					break;
				case 'spec':
					$values[] = $terms( $id, 'specialty' );
					break;
				case 'svc':
					$values[] = $terms( $id, 'service' );
					break;

				case 'confirmed':
					$st = function_exists( '\Oria\Theme\display_status' ) ? \Oria\Theme\display_status( $id ) : '';
					// "Not yet", never "No" — they may simply not have been asked.
					$values[] = in_array( $st, array( 'claimed', 'featured' ), true )
						? __( 'Yes', 'oria' )
						: __( 'Not yet', 'oria' );
					break;
			}
		}
		$out[] = array( 'label' => $row['label'], 'hint' => $row['hint'], 'values' => $values );
	}
	return $out;
}

/**
 * What differs, stated and not ranked — plus, first, how much of the table
 * is actually known. That caveat is the most important line on the page:
 * without it a reader takes eight rows of "Not listed" as eight findings.
 *
 * @param array<int, \WP_Post> $posts
 * @return array<int, string>
 */
function place_summary( array $posts ): array {
	$lines = array();

	$unconfirmed = 0;
	foreach ( $posts as $p ) {
		$st = function_exists( '\Oria\Theme\display_status' ) ? \Oria\Theme\display_status( (int) $p->ID ) : '';
		if ( ! in_array( $st, array( 'claimed', 'featured' ), true ) ) {
			$unconfirmed++;
		}
	}
	if ( $unconfirmed > 0 ) {
		$lines[] = sprintf(
			/* translators: %d: how many of the compared businesses have not confirmed their details */
			_n(
				'%d of these has not confirmed their own details with us, so a blank means we have not been told — not that the answer is no.',
				'%d of these have not confirmed their own details with us, so a blank means we have not been told — not that the answer is no.',
				$unconfirmed,
				'oria'
			),
			$unconfirmed
		);
	}

	// Price, on the same rule the registry tables use: name an extreme only
	// where one genuinely exists.
	$bands = array();
	foreach ( $posts as $p ) {
		$r = band_rank( (string) get_field( 'price_band', (int) $p->ID ) );
		if ( null !== $r ) {
			$bands[ $p->post_title ] = $r;
		}
	}
	if ( count( $bands ) === count( $posts ) && count( array_unique( $bands ) ) > 1 ) {
		$min = min( $bands );
		$max = max( $bands );
		if ( 1 === count( array_keys( $bands, $min, true ) ) && 1 === count( array_keys( $bands, $max, true ) ) ) {
			$lines[] = sprintf(
				/* translators: 1: cheapest band business, 2: dearest band business */
				__( '%1$s sits in the lowest price band here and %2$s the highest, on the figures each has published.', 'oria' ),
				(string) array_search( $min, $bands, true ),
				(string) array_search( $max, $bands, true )
			);
		}
	}

	// Geography, which is the difference people most often act on.
	$regions = array();
	foreach ( $posts as $p ) {
		$r = wp_get_post_terms( (int) $p->ID, 'area', array( 'fields' => 'names' ) );
		$regions[] = ( ! is_wp_error( $r ) && $r ) ? end( $r ) : '';
	}
	$regions = array_filter( $regions );
	if ( count( $regions ) === count( $posts ) && count( array_unique( $regions ) ) === 1 ) {
		$lines[] = sprintf(
			/* translators: %s: the area they share */
			__( 'All of these are in %s, so travel is unlikely to separate them.', 'oria' ),
			(string) $regions[0]
		);
	}

	return $lines;
}

/* ----------------------------------------------------------- entry points */

/**
 * One experience by id, or null.
 */
function by_id( string $id ): ?array {
	foreach ( experiences() as $e ) {
		if ( (string) $e['id'] === $id ) {
			return $e;
		}
	}
	return null;
}

/**
 * The /compare/ address for a set of ids, in the order given. Fewer than
 * MIN_PICK gets the bare page rather than a state that would not survive
 * picked(), so a caller can never build a link that lands on nothing.
 *
 * @param array<int, string> $ids
 */
function url_for( array $ids ): string {
	$ids = array_slice( array_values( array_unique( array_filter( $ids ) ) ), 0, MAX_PICK );
	if ( count( $ids ) < MIN_PICK ) {
		return home_url( '/' . PATH . '/' );
	}
	return home_url( '/' . PATH . '/?with=' . implode( ',', array_map( 'rawurlencode', $ids ) ) );
}

/**
 * The experience a taxonomy term stands for, matched on the registry URL
 * so an experience is still tied to the site by exactly one field.
 *
 * Facet-backed experiences (pilates lives at /practices/yoga/pilates/)
 * have no term of their own and simply return null — the caller falls
 * back to the bare hub.
 */
function experience_for_term( \WP_Term $term ): ?array {
	if ( 'practice' === $term->taxonomy ) {
		$want = '/practices/' . $term->slug . '/';
	} elseif ( 'specialty' === $term->taxonomy ) {
		$want = '/perth/' . $term->slug . '/';
	} else {
		return null;
	}
	foreach ( experiences() as $e ) {
		if ( (string) $e['url'] === $want ) {
			return $e;
		}
	}

	/*
	 * Second pass, practice terms only: an experience whose URL is a
	 * specialty or a facet still stands for a category — a sound bath is
	 * what the Sound category is — and says so with an optional
	 * "category" key. Only consulted when nothing matched on URL, so a
	 * category that owns an experience outright always wins.
	 */
	if ( 'practice' === $term->taxonomy ) {
		foreach ( experiences() as $e ) {
			if ( isset( $e['category'] ) && (string) $e['category'] === $term->slug ) {
				return $e;
			}
		}
	}

	return null;
}

/**
 * The compare prompt for one category page: what this term stands for, set
 * against the counterpart the registry names for it.
 *
 * Every category gets a prompt, including the fifteen with no registry
 * entry — they fall back to the bare hub. A generic link is still worth
 * having: it is the internal link that makes the page crawlable at all,
 * and the visitor picks their own pair when we cannot pick it for them.
 *
 * @return array{url: string, label: string, filled: bool}
 */
function prompt_for_term( \WP_Term $term ): array {
	$a = experience_for_term( $term );
	$b = $a ? by_id( (string) ( $a['pair'] ?? '' ) ) : null;

	if ( $a && $b ) {
		return array(
			'url'    => url_for( array( (string) $a['id'], (string) $b['id'] ) ),
			/* translators: 1: this category, 2: the counterpart it is compared with */
			'label'  => sprintf( __( 'Compare %1$s with %2$s', 'oria' ), $a['label'], $b['label'] ),
			'filled' => true,
		);
	}

	return array(
		'url'    => home_url( '/' . PATH . '/' ),
		'label'  => __( 'Compare wellness experiences side by side', 'oria' ),
		'filled' => false,
	);
}

/**
 * The within-category group a practice term owns, if any — Bodywork owns
 * "Types of massage". Keyed off the group's own "parent", so the registry
 * declares the relationship and the template never has to know it.
 */
function group_for_term( \WP_Term $term ): ?array {
	if ( 'practice' !== $term->taxonomy ) {
		return null;
	}
	foreach ( groups() as $id => $g ) {
		if ( is_array( $g ) && (string) ( $g['parent'] ?? '' ) === $term->slug ) {
			$g['id'] = (string) $id;
			return $g;
		}
	}
	return null;
}

/**
 * The second prompt a category page can carry: not "how does massage
 * compare to a day spa" but "which kind of massage do I book". It opens
 * pre-filled with the first four the registry lists, which is the useful
 * table rather than an empty picker.
 *
 * @return array{url: string, label: string}|null
 */
function group_prompt_for_term( \WP_Term $term ): ?array {
	$g = group_for_term( $term );
	if ( ! $g ) {
		return null;
	}
	$members = experiences_in( (string) $g['id'] );
	if ( count( $members ) < MIN_PICK ) {
		return null;
	}
	$ids = array();
	foreach ( array_slice( $members, 0, MAX_PICK ) as $e ) {
		$ids[] = (string) $e['id'];
	}
	return array(
		'url'   => url_for( $ids ),
		/* translators: %s: group name, e.g. "types of massage" */
		'label' => sprintf( __( 'Compare %s side by side', 'oria' ), lcfirst( (string) $g['label'] ) ),
	);
}

/**
 * Join labels as a list. Deliberately not wp_sprintf_l(), which serialises
 * with an Oxford comma; the house style is Australian English, which does
 * not use one.
 *
 * @param array<int, string> $labels
 */
function join_labels( array $labels ): string {
	$labels = array_values( array_filter( $labels ) );
	if ( count( $labels ) < 2 ) {
		return (string) ( $labels[0] ?? '' );
	}
	$last = array_pop( $labels );
	/* translators: 1: comma-separated list, 2: final item */
	return sprintf( __( '%1$s and %2$s', 'oria' ), implode( ', ', $labels ), $last );
}

/**
 * A prompt that compares the terms just shown to someone — the Finder's
 * own results. Null when fewer than two of them are in the registry,
 * because a comparison of one is not a comparison.
 *
 * @param array<int, \WP_Term> $terms
 * @return array{url: string, labels: array<int, string>}|null
 */
function prompt_for_terms( array $terms ): ?array {
	$ids = array();
	foreach ( $terms as $t ) {
		if ( ! $t instanceof \WP_Term ) {
			continue;
		}
		$e = experience_for_term( $t );
		if ( $e ) {
			$ids[ (string) $e['id'] ] = (string) $e['label'];
		}
	}
	$ids = array_slice( $ids, 0, MAX_PICK, true );
	if ( count( $ids ) < MIN_PICK ) {
		return null;
	}
	return array(
		'url'    => url_for( array_keys( $ids ) ),
		'labels' => array_values( $ids ),
	);
}

/* ---------------------------------------------------------------- sitemap */

/**
 * @return list<array{loc: string}>
 */
function sitemap_entries(): array {
	// One address today. The pair pages join this list, not the query
	// strings — those canonical to the hub and must never be listed.
	return array( array( 'loc' => home_url( '/' . PATH . '/' ) ) );
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
	// get_sitemap() takes the links as an array and renders each itself.
	$links = array();
	foreach ( sitemap_entries() as $e ) {
		$links[] = array(
			'loc' => $e['loc'],
			'mod' => gmdate( 'c' ),
		);
	}
	$sm->set_sitemap( $sm->renderer->get_sitemap( $links, SITEMAP, 1 ) );
}

function sitemap_index( $xml ) {
	if ( ! sitemap_entries() ) {
		return $xml;
	}
	return $xml . sprintf(
		"<sitemap><loc>%s</loc><lastmod>%s</lastmod></sitemap>\n",
		esc_url( home_url( '/' . SITEMAP . '-sitemap.xml' ) ),
		esc_html( gmdate( 'c' ) )
	);
}

/* -------------------------------------------------------------------- seo */

/**
 * The page's heading, which the H1 and the title tag both take. A group
 * view is a different page to a reader, so it says so — even though every
 * variant still canonicals to the bare /compare/.
 */
function heading(): string {
	$places = places();
	if ( $places ) {
		return sprintf(
			/* translators: %d: how many businesses are being compared */
			__( 'Comparing %d places in Perth', 'oria' ),
			count( $places )
		);
	}
	$scope = place_scope();
	if ( $scope ) {
		return sprintf(
			/* translators: %s: practice category, e.g. "Yoga & Pilates" */
			__( 'Compare %s places in Perth', 'oria' ),
			function_exists( '\Oria\Theme\tname' ) ? \Oria\Theme\tname( $scope ) : $scope->name
		);
	}
	$g = group( current_group() );
	if ( $g ) {
		/* translators: %s: group heading, e.g. "Types of massage compared" */
		return sprintf( __( '%s in Perth', 'oria' ), (string) ( $g['h1'] ?? $g['label'] ) );
	}
	return __( 'Compare wellness experiences in Perth', 'oria' );
}

function title( $title ) {
	return is_compare() ? sprintf( '%s | %s', heading(), get_bloginfo( 'name' ) ) : $title;
}

function core_title( array $parts ): array {
	if ( is_compare() ) {
		$parts['title'] = heading();
	}
	return $parts;
}

function description( $desc ) {
	if ( ! is_compare() ) {
		return $desc;
	}
	$g = group( current_group() );
	if ( $g && '' !== (string) ( $g['blurb'] ?? '' ) ) {
		return (string) $g['blurb'];
	}
	return sprintf(
		/* translators: %d: number of experiences in the top-level set */
		__( 'Put %d wellness experiences side by side — intensity, guidance, group size, price and time — then find who runs each one in Perth.', 'oria' ),
		count( experiences_in( '' ) )
	);
}

/**
 * Every ?with= variant canonicals to the bare page, the same rule the
 * directory applies to its filtered views: one indexable address, many
 * shareable states.
 */
function canonical( $url ) {
	return is_compare() ? home_url( '/' . PATH . '/' ) : $url;
}
