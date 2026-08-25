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
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	if ( ! is_compare() || ! isset( $_GET['pick'] ) || ! is_array( $_GET['pick'] ) || isset( $_GET['with'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each id is sanitize_title()d.
	$ids = implode( ',', array_filter( array_map( 'sanitize_title', array_slice( (array) wp_unslash( $_GET['pick'] ), 0, MAX_PICK ) ) ) );
	wp_safe_redirect( home_url( '/' . PATH . '/' . ( '' !== $ids ? '?with=' . rawurlencode( $ids ) . '#result' : '' ) ) );
	exit;
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
		'experiences' => is_array( $json['experiences'] ?? null ) ? $json['experiences'] : array(),
	);
	return $data;
}

/** @return array<int, array> every experience, in registry order */
function experiences(): array {
	return registry()['experiences'];
}

/** The attribute sections, in display order. */
function sections(): array {
	return registry()['attributes'];
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
		if ( isset( $by_id[ $id ] ) ) {
			$out[] = $by_id[ $id ];
		}
		if ( count( $out ) === MAX_PICK ) {
			break;
		}
	}
	return count( $out ) >= MIN_PICK ? $out : array();
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

	$hard = $by( 'intensity' );
	if ( (int) $hard[0]['attributes']['intensity'] !== (int) end( $hard )['attributes']['intensity'] ) {
		$lines[] = sprintf(
			/* translators: 1: hardest experience, 2: gentlest experience */
			__( '%1$s asks the most of the body here; %2$s the least.', 'oria' ),
			$hard[0]['label'],
			end( $hard )['label']
		);
	}

	$quiet = $by( 'quiet' );
	if ( (int) $quiet[0]['attributes']['quiet'] !== (int) end( $quiet )['attributes']['quiet'] ) {
		$lines[] = sprintf(
			/* translators: %s: quietest experience */
			__( '%s is the quietest room of the set.', 'oria' ),
			$quiet[0]['label']
		);
	}

	$social = $by( 'social' );
	// "Alone" is only claimed at a score of 1 — a sound bath is quiet, but
	// a room of forty people lying down is not solitude.
	if ( (int) $social[0]['attributes']['social'] >= 3 && 1 === (int) end( $social )['attributes']['social'] ) {
		$lines[] = sprintf(
			/* translators: 1: most social experience, 2: most solitary experience */
			__( '%1$s is the most social; %2$s you do essentially alone.', 'oria' ),
			$social[0]['label'],
			end( $social )['label']
		);
	}

	// Only name a cheapest when there IS one: with three of four sharing a
	// band, crowning whichever sorted first would be an invented distinction.
	$band_of = static fn( array $e ): int => strlen( (string) ( $e['attributes']['price'] ?? '' ) );
	$bands   = array_map( $band_of, $picked );
	if ( count( array_unique( $bands ) ) > 1 ) {
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

function title( $title ) {
	return is_compare() ? sprintf( 'Compare wellness experiences in Perth | %s', get_bloginfo( 'name' ) ) : $title;
}

function core_title( array $parts ): array {
	if ( is_compare() ) {
		$parts['title'] = __( 'Compare wellness experiences in Perth', 'oria' );
	}
	return $parts;
}

function description( $desc ) {
	if ( ! is_compare() ) {
		return $desc;
	}
	return sprintf(
		/* translators: %d: number of experiences in the registry */
		__( 'Put %d wellness experiences side by side — intensity, guidance, group size, price and time — then find who runs each one in Perth.', 'oria' ),
		count( experiences() )
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
