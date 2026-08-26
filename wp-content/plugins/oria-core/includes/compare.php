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
const REWRITE_V = '3';   // bumped for the /compare/{a}-vs-{b}/ pair pages

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
	// The session builder is a page of its own, not a state of /compare/:
	// it answers a different question and gets its own indexable address.
	add_rewrite_rule( '^' . PATH . '/build/?$', 'index.php?' . QUERY_VAR . '=1&' . BUILD_VAR . '=1', 'top' );

	/*
	 * One rule per curated pair, never a wildcard. A pattern like
	 * ([a-z0-9-]+)-vs-([a-z0-9-]+) would answer 200 for every one of the
	 * 600-odd combinations of 36 experiences, and most of them are pages
	 * nobody would write and nobody searches for. The registry decides
	 * which pairs exist; anything else stays a 404, which is the honest
	 * answer.
	 */
	foreach ( pairs() as $slug => $pair ) {
		add_rewrite_rule(
			'^' . PATH . '/' . preg_quote( $slug, '#' ) . '/?$',
			'index.php?' . QUERY_VAR . '=1&' . PAIR_VAR . '=' . rawurlencode( $slug ),
			'top'
		);
	}
}

function maybe_flush(): void {
	if ( get_option( 'oria_compare_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_compare_rewrite_v', REWRITE_V );
	}
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	$vars[] = BUILD_VAR;
	$vars[] = PAIR_VAR;
	return $vars;
}

function is_compare(): bool {
	return (bool) get_query_var( QUERY_VAR );
}

function is_build(): bool {
	return is_compare() && (bool) get_query_var( BUILD_VAR );
}

/* ------------------------------------------------------------------ pairs */

/**
 * The curated pair pages, keyed by slug.
 *
 * A pair page is the same comparison the hub renders, at an address a
 * search engine can rank for a two-sided question: "yoga vs pilates" is
 * asked 480 times a month in Australia and /compare/?with=yoga,pilates
 * canonicals away to the hub, so nothing was ever competing for it.
 *
 * @return array<string, array>
 */
function pairs(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$cache = array();
	foreach ( (array) ( registry()['pairs'] ?? array() ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		$a    = by_id( (string) ( $row['a'] ?? '' ) );
		$b    = by_id( (string) ( $row['b'] ?? '' ) );

		/*
		 * A pair naming an experience that has since been renamed or removed
		 * is dropped rather than routed. The alternative is a live URL whose
		 * table has one column, which reads as a fact about the modality.
		 * Same reason picked() drops picks that span groups — and a pair that
		 * spans groups is dropped here for exactly that reason too.
		 */
		if ( '' === $slug || ! $a || ! $b ) {
			continue;
		}
		if ( (string) ( $a['group'] ?? '' ) !== (string) ( $b['group'] ?? '' ) ) {
			continue;
		}

		$row['slug']  = $slug;
		$cache[ $slug ] = $row;
	}

	return $cache;
}

/** The pair being viewed, if this request is a pair page. */
function current_pair(): ?array {
	if ( ! is_compare() ) {
		return null;
	}
	$slug = sanitize_title( (string) get_query_var( PAIR_VAR ) );
	return '' === $slug ? null : ( pairs()[ $slug ] ?? null );
}

function is_pair(): bool {
	return null !== current_pair();
}

/** The canonical address of one pair page. */
function pair_url( string $slug ): string {
	return home_url( '/' . PATH . '/' . $slug . '/' );
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
		'pairs'       => is_array( $json['pairs'] ?? null ) ? $json['pairs'] : array(),
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
	/*
	 * A pair page IS its pick. The two ids come from the registry, not the
	 * query string, so ?with= cannot turn /compare/yoga-vs-pilates/ into a
	 * comparison of something else while the H1, the title and the intro
	 * all still say yoga and Pilates.
	 */
	$pair = current_pair();
	if ( $pair ) {
		$a = by_id( (string) $pair['a'] );
		$b = by_id( (string) $pair['b'] );
		return ( $a && $b ) ? array( $a, $b ) : array();
	}

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

/* ------------------------------------------- experience presentation */

/*
 * Everything below reshapes the registry for reading. Not one function
 * adds a fact: they re-cut the same 1-5 scores and text values into hero
 * cards, a glance table and preference lists. Every one works off whatever
 * schema is in play — the top-level set, massage types, recovery — because
 * the page has to serve any pair, not the yoga/sound-bath example it was
 * designed against.
 */

/**
 * The picture that stands for one experience.
 *
 * Every image is a CATEGORY tile — `tile_image` on the practice term, the
 * same asset the homepage and /practices/ already use. Never a listing's
 * photograph: those belong to the business that supplied them, and one
 * studio's treatment room is not what "massage" looks like.
 *
 * The practice comes from the experience's own URL where it has one, and
 * from the registry's `image_from` where the URL is a specialty page.
 * Falls back to the theme's small set of shipped category pictures, then
 * to an empty string — a missing image is better than a wrong one, and
 * every layout that calls this is built to survive it.
 */
function image_for( array $e, string $size = 'oria-card' ): string {
	static $cache = array();
	$key = (string) ( $e['id'] ?? '' ) . '|' . $size;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$slug = '';
	if ( preg_match( '~^/practices/([^/]+)/~', (string) ( $e['url'] ?? '' ), $m ) ) {
		$slug = $m[1];
	} elseif ( ! empty( $e['image_from'] ) ) {
		$slug = (string) $e['image_from'];
	} elseif ( ! empty( $e['category'] ) ) {
		$slug = (string) $e['category'];
	}

	$url = '';
	if ( '' !== $slug ) {
		$term = get_term_by( 'slug', $slug, 'practice' );
		if ( $term instanceof \WP_Term ) {
			$id = (int) get_field( 'tile_image', 'practice_' . $term->term_id );
			if ( $id ) {
				$url = (string) wp_get_attachment_image_url( $id, $size );
			}
		}
		if ( '' === $url ) {
			// The theme ships pictures for six categories; the rest rely on
			// the term's own tile image and go without until it is set.
			$shipped = array(
				'meditation'  => 'cat-meditation.webp',
				'breathwork'  => 'cat-breathwork.webp',
				'yoga'        => 'cat-yoga.webp',
				'mindfulness' => 'cat-mindfulness.webp',
				'sound'       => 'cat-sound.webp',
				'retreats'    => 'cat-retreats.webp',
			);
			if ( isset( $shipped[ $slug ] ) ) {
				$url = get_template_directory_uri() . '/assets/img/' . $shipped[ $slug ];
			}
		}
	}

	$cache[ $key ] = $url;
	return $url;
}

/** A 1-5 score in words, so a scale never depends on the dots alone. */
function scale_word( int $n ): string {
	$words = array(
		1 => __( 'Very low', 'oria' ),
		2 => __( 'Low', 'oria' ),
		3 => __( 'Moderate', 'oria' ),
		4 => __( 'High', 'oria' ),
		5 => __( 'Very high', 'oria' ),
	);
	return $words[ max( 1, min( 5, $n ) ) ];
}

/**
 * Every attribute definition in a schema, flattened to key => definition.
 *
 * @return array<string, array>
 */
function attribute_defs( string $group = '' ): array {
	$out = array();
	foreach ( sections( $group ) as $sec ) {
		foreach ( (array) ( $sec['items'] ?? array() ) as $k => $def ) {
			$out[ $k ] = $def;
		}
	}
	return $out;
}

/**
 * The two or three words that describe one experience, taken from
 * whichever of its scales sit furthest from the middle.
 *
 * Both ends count. A sound bath's defining facts are all LOW — no
 * movement, no effort, not quiet — so reading only the high end would
 * describe it by its second-least-relevant attribute. Each scale carries
 * a word for either end in the registry; the middle has nothing to say
 * and is skipped.
 *
 * @return array<int, string>
 */
function traits_of( array $e, string $group = '', int $n = 3 ): array {
	$defs = attribute_defs( $group );
	$cand = array();
	foreach ( $defs as $k => $def ) {
		if ( 'scale' !== ( $def['type'] ?? '' ) || ! isset( $e['attributes'][ $k ] ) ) {
			continue;
		}
		$v = (int) $e['attributes'][ $k ];
		if ( $v >= 4 ) {
			$word = (string) ( $def['high'] ?? '' );
		} elseif ( $v <= 2 ) {
			$word = (string) ( $def['low'] ?? '' );
		} else {
			continue;
		}
		if ( '' === $word ) {
			continue;
		}
		// Furthest from the middle first — the most characteristic wins.
		$cand[] = array( 'word' => $word, 'weight' => abs( $v - 3 ) );
	}
	usort( $cand, static fn( array $a, array $b ): int => $b['weight'] <=> $a['weight'] );

	$out = array();
	foreach ( $cand as $c ) {
		if ( ! in_array( $c['word'], $out, true ) ) {
			$out[] = $c['word'];
		}
		if ( count( $out ) === $n ) {
			break;
		}
	}
	return $out;
}

/**
 * The "at a glance" rows: the attributes on which the picked experiences
 * differ MOST, so the fastest possible read shows real differences rather
 * than the first few rows of the schema.
 *
 * Scales are ranked by spread. Text attributes are included only when the
 * values genuinely differ, and never more than a couple, because a glance
 * table of long sentences is not a glance.
 *
 * @param array<int, array> $picked
 * @return array<int, array{key: string, label: string, type: string, values: array}>
 */
function glance_rows( array $picked, string $group = '', int $n = 6 ): array {
	$defs   = attribute_defs( $group );
	$scales = array();
	$texts  = array();

	foreach ( $defs as $k => $def ) {
		$vals = array();
		foreach ( $picked as $e ) {
			$vals[] = $e['attributes'][ $k ] ?? null;
		}
		if ( in_array( null, $vals, true ) ) {
			continue;
		}
		if ( 'scale' === ( $def['type'] ?? '' ) ) {
			$ints   = array_map( 'intval', $vals );
			$spread = max( $ints ) - min( $ints );
			if ( $spread > 0 ) {
				$scales[] = array( 'key' => $k, 'label' => (string) $def['label'], 'type' => 'scale', 'values' => $ints, 'spread' => $spread );
			}
		} else {
			$strs = array_map( 'strval', $vals );
			if ( count( array_unique( $strs ) ) > 1 ) {
				$texts[] = array( 'key' => $k, 'label' => (string) $def['label'], 'type' => 'text', 'values' => $strs );
			}
		}
	}

	usort( $scales, static fn( array $a, array $b ): int => $b['spread'] <=> $a['spread'] );

	// Scales first — they are the ones a reader can compare at a glance.
	$out = array_slice( $scales, 0, max( 0, $n - 2 ) );
	foreach ( array_slice( $texts, 0, $n - count( $out ) ) as $t ) {
		$out[] = $t;
	}
	return $out;
}

/**
 * "Which one sounds more like you?" — for each experience, the scales on
 * which it is the sole extreme of the set, phrased as a want.
 *
 * Direction matters and both ends are used, or the gentlest option in any
 * pair would have nothing listed against it at all.
 *
 * @param array<int, array> $picked
 * @return array<int, array{label: string, wants: array<int, string>}>
 */
function preference_bullets( array $picked, string $group = '' ): array {
	$defs = attribute_defs( $group );
	$out  = array();
	foreach ( $picked as $e ) {
		$out[] = array( 'label' => (string) $e['label'], 'url' => (string) $e['url'], 'wants' => array() );
	}

	foreach ( $defs as $k => $def ) {
		if ( 'scale' !== ( $def['type'] ?? '' ) ) {
			continue;
		}
		$vals = array();
		foreach ( $picked as $i => $e ) {
			if ( ! isset( $e['attributes'][ $k ] ) ) {
				continue 2;
			}
			$vals[ $i ] = (int) $e['attributes'][ $k ];
		}
		if ( max( $vals ) === min( $vals ) ) {
			continue;
		}
		$hi = array_keys( $vals, max( $vals ), true );
		$lo = array_keys( $vals, min( $vals ), true );

		/*
		 * The registry's directional words where it has them — "Stillness"
		 * rather than "Less movement". Without them the two lists come out
		 * as mirror images of each other, every line "More x" against
		 * "Less x", which reads like arithmetic instead of a choice.
		 */
		if ( 1 === count( $hi ) ) {
			$word = trim( (string) ( $def['high'] ?? '' ) );
			/* translators: %s: an attribute, e.g. "movement" */
			$out[ $hi[0] ]['wants'][] = '' !== $word ? $word : sprintf( __( 'More %s', 'oria' ), strtolower( (string) $def['label'] ) );
		}
		if ( 1 === count( $lo ) ) {
			$word = trim( (string) ( $def['low'] ?? '' ) );
			/* translators: %s: an attribute, e.g. "movement" */
			$out[ $lo[0] ]['wants'][] = '' !== $word ? $word : sprintf( __( 'Less %s', 'oria' ), strtolower( (string) $def['label'] ) );
		}
	}

	foreach ( $out as $i => $row ) {
		$out[ $i ]['wants'] = array_slice( $row['wants'], 0, 5 );
	}
	return $out;
}

/**
 * A section's heading in the page's editorial voice. Only the top-level
 * schema is remapped — a group names its own sections, and "In the room"
 * is already the right words for a massage table.
 */
function section_heading( string $key, string $fallback, string $group = '' ): string {
	if ( '' !== $group ) {
		return $fallback;
	}
	$map = array(
		'physical'  => __( 'What is it like?', 'oria' ),
		'session'   => __( 'When you are actually there', 'oria' ),
		'people'    => __( 'Who is it like being with?', 'oria' ),
		'practical' => __( 'Before you go', 'oria' ),
	);
	return $map[ $key ] ?? $fallback;
}

/* ------------------------------------------------------ build your session */

/*
 * Sliders over what a session is LIKE, then the registry sorted by how
 * close each experience sits to the answers.
 *
 * Deliberately not "stress relief", "mental wellbeing" or "fitness". Those
 * are outcomes — what the hour is supposed to do to you — and this file's
 * header names two of them as the exact claims the registry refuses. Every
 * axis below is something a visitor could verify by standing in the room.
 *
 * And deliberately no percentage. A weighted distance over editorial 1-5
 * judgements is computable, but printing it as "92%" dresses a judgement
 * as a measurement, and a number is the most quotable thing on any page.
 * The results are ordered, and each says why it placed where it did.
 */

/** Sliders are 0 = no preference, 1-5 = the level asked for. */
const BUILD_ANY = 0;

/**
 * The axes offered, in slider order: every scale in the schema, with the
 * registry's own directional words as the two ends of the track.
 *
 * @return array<string, array{label: string, low: string, high: string}>
 */
function build_axes( string $group = '' ): array {
	$out = array();
	foreach ( attribute_defs( $group ) as $k => $def ) {
		if ( 'scale' !== ( $def['type'] ?? '' ) ) {
			continue;
		}
		$out[ $k ] = array(
			'label' => (string) $def['label'],
			'hint'  => (string) ( $def['hint'] ?? '' ),
			'low'   => (string) ( $def['low'] ?? '' ),
			'high'  => (string) ( $def['high'] ?? '' ),
		);
	}
	return $out;
}

/**
 * What the visitor asked for, read off the query string.
 *
 * @return array{axes: array<string, int>, budget: int}
 */
function build_prefs( string $group = '' ): array {
	$axes = array();
	foreach ( build_axes( $group ) as $k => $_ ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
		$raw = isset( $_GET[ $k ] ) ? (int) $_GET[ $k ] : BUILD_ANY;
		if ( $raw >= 1 && $raw <= 5 ) {
			$axes[ $k ] = $raw;
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
	$budget = isset( $_GET['budget'] ) ? (int) $_GET['budget'] : 0;
	return array(
		'axes'   => $axes,
		'budget' => ( $budget >= 1 && $budget <= 4 ) ? $budget : 0,
	);
}

/**
 * The registry ordered by distance from what was asked.
 *
 * Budget is a ceiling, not a target — nobody means "I want to spend
 * exactly three dollar signs" — so it filters rather than scores. Free
 * always passes.
 *
 * @return array<int, array{experience: array, distance: int, reasons: array<int, string>}>
 */
function build_matches( array $prefs, string $group = '' ): array {
	if ( ! $prefs['axes'] ) {
		return array();
	}
	$axes = build_axes( $group );
	$pool = experiences_in( $group );
	$out  = array();

	foreach ( $pool as $e ) {
		if ( $prefs['budget'] > 0 ) {
			$band = band_rank( (string) ( $e['attributes']['price'] ?? '' ) );
			if ( null !== $band && $band > $prefs['budget'] ) {
				continue;
			}
		}

		$distance = 0;
		$exact    = array();
		$close    = 0;
		foreach ( $prefs['axes'] as $k => $want ) {
			if ( ! isset( $e['attributes'][ $k ] ) ) {
				continue;
			}
			$has  = (int) $e['attributes'][ $k ];
			$gap  = abs( $want - $has );
			$distance += $gap;
			if ( 0 === $gap ) {
				$exact[] = (string) $axes[ $k ]['label'];
			}
			if ( $gap <= 1 ) {
				$close++;
			}
		}

		$out[] = array(
			'experience' => $e,
			'distance'   => $distance,
			'exact'      => $exact,
			'close'      => $close,
		);
	}

	// Closest first; ties broken by registry order, which is stable and is
	// not a ranking of quality.
	usort(
		$out,
		static fn( array $a, array $b ): int => $a['distance'] <=> $b['distance']
	);

	$asked = count( $prefs['axes'] );
	foreach ( $out as $i => $row ) {
		$reasons = array();
		if ( $row['exact'] ) {
			$reasons[] = sprintf(
				/* translators: %s: list of attributes matched exactly */
				__( 'Matches what you asked on %s.', 'oria' ),
				strtolower( join_labels( array_slice( $row['exact'], 0, 3 ) ) )
			);
		}
		if ( ! $row['exact'] && $row['close'] > 0 ) {
			$reasons[] = sprintf(
				/* translators: 1: how many answers are close, 2: how many were given */
				__( 'Close on %1$d of your %2$d answers, exact on none.', 'oria' ),
				$row['close'],
				$asked
			);
		}
		if ( ! $reasons ) {
			$reasons[] = __( 'Nothing here lines up with what you asked for.', 'oria' );
		}
		$out[ $i ]['reasons'] = $reasons;
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
const BUILD_VAR  = 'oria_compare_build';
const PAIR_VAR   = 'oria_compare_pair';

/**
 * The sentinel for every empty cell. Never "No".
 *
 * An em dash rather than words: "Not listed" repeated down a column made
 * the business look deficient, when the deficiency is ours. The dash reads
 * as "nothing here" and gets out of the way. Screen readers are given the
 * words instead — see the template's sr-only span, because a glyph alone
 * carries no accessible name.
 */
function unknown(): string {
	return '—';
}

/** The same absence, spoken. */
function unknown_spoken(): string {
	return __( 'Not specified', 'oria' );
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

	// Editorial labels, not field names, each with a quieter line under it
	// saying where the figure comes from. "Price band" and "Listed under"
	// were our vocabulary, not a reader's.
	$rows = array(
		array( 'key' => 'where', 'type' => 'text', 'label' => __( 'Location', 'oria' ), 'hint' => __( 'Where you\'ll find them', 'oria' ) ),
		array( 'key' => 'price', 'type' => 'price', 'label' => __( 'Price', 'oria' ), 'hint' => __( 'Oria Haven price guide — relative, not official', 'oria' ) ),
		array( 'key' => 'rating', 'type' => 'rating', 'label' => __( 'Rating', 'oria' ), 'hint' => __( 'Based on Google reviews', 'oria' ) ),
		array( 'key' => 'format', 'type' => 'format', 'label' => __( 'Format', 'oria' ), 'hint' => '' ),
		array( 'key' => 'cats', 'type' => 'list', 'label' => __( 'Practices', 'oria' ), 'hint' => '' ),
		array( 'key' => 'spec', 'type' => 'list', 'label' => __( 'Specialties', 'oria' ), 'hint' => __( 'What sets each one apart', 'oria' ) ),
		array( 'key' => 'svc', 'type' => 'list', 'label' => __( 'Classes & services', 'oria' ), 'hint' => '' ),
		array( 'key' => 'confirmed', 'type' => 'confirm', 'label' => __( 'Business confirmation', 'oria' ), 'hint' => __( 'Whether the business has confirmed its own details', 'oria' ) ),
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
							? sprintf( __( '%1$s · from $%2$s', 'oria' ), $band, number_format_i18n( $from ) )
							: $band );
					break;

				case 'rating':
					$r = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( $id ) : array( 'rating' => 0, 'count' => 0 );
					$values[] = ( (float) $r['rating'] > 0 )
						/* translators: 1: rating out of five, 2: number of reviews */
						? sprintf( __( '%1$s|%2$s Google reviews', 'oria' ), number_format_i18n( (float) $r['rating'], 1 ), number_format_i18n( (int) $r['count'] ) )
						: unknown();
					break;

				case 'format':
					$f   = (string) ( get_field( 'format', $id ) ?: 'in-person' );
					$map = array(
						'in-person' => __( 'In person', 'oria' ),
						'online'    => __( 'Online', 'oria' ),
						'both'      => __( 'Online + in person', 'oria' ),
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
					/*
					 * "Pending", never "No". And never a word implying WE
					 * checked anything: the business confirms its own
					 * details, which is a different and much weaker claim
					 * than independent verification.
					 */
					$values[] = in_array( $st, array( 'claimed', 'featured' ), true )
						? __( 'Confirmed by the business', 'oria' )
						: __( 'Confirmation pending', 'oria' );
					break;
			}
		}
		$out[] = array(
			'label'  => $row['label'],
			'hint'   => $row['hint'],
			'type'   => $row['type'],
			'values' => $values,
		);
	}
	return $out;
}

/**
 * The profile that heads each column: photograph, name, suburb, rating and
 * up to two category badges. Everything read, nothing invented.
 *
 * @param array<int, \WP_Post> $posts
 * @return array<int, array>
 */
function place_header( array $posts ): array {
	$out = array();
	foreach ( $posts as $p ) {
		$id    = (int) $p->ID;
		$areas = wp_get_post_terms( $id, 'area', array( 'fields' => 'names' ) );
		$cats  = wp_get_post_terms( $id, 'practice', array( 'fields' => 'names' ) );
		$r     = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( $id ) : array( 'rating' => 0, 'count' => 0, 'source' => '' );
		$st    = function_exists( '\Oria\Theme\display_status' ) ? \Oria\Theme\display_status( $id ) : '';

		$out[] = array(
			'id'        => $id,
			'name'      => get_the_title( $p ),
			'url'       => (string) get_permalink( $p ),
			'image'     => function_exists( '\Oria\Theme\listing_image' ) ? (string) \Oria\Theme\listing_image( $id ) : '',
			'area'      => ( ! is_wp_error( $areas ) && $areas ) ? (string) $areas[0] : '',
			'badges'    => ( ! is_wp_error( $cats ) && $cats ) ? array_slice( $cats, 0, 2 ) : array(),
			'rating'    => (float) $r['rating'],
			'reviews'   => (int) $r['count'],
			'source'    => (string) ( $r['source'] ?? '' ),
			'confirmed' => in_array( $st, array( 'claimed', 'featured' ), true ),
		);
	}
	return $out;
}

/**
 * Oria Haven Insights: the differences worth noticing, stated as facts.
 *
 * The brief asked for these as verdicts — "Best for yoga variety", "Best
 * for sound healing". They are the same findings phrased as
 * recommendations, and this site tells people on every category page that
 * "it counts, it never ranks". So each keeps its data and loses its
 * verdict: "Lists the most classes", not "best for variety".
 *
 * That is not only a promise-keeping move, it is the more truthful one.
 * A business with seven service terms is not better than one with two; it
 * is better DOCUMENTED. 120 of 331 listings carry no service terms at all.
 * Every label below therefore says "lists", and means it.
 *
 * Nothing is emitted unless one business is a genuine, unique extreme.
 *
 * @param array<int, \WP_Post> $posts
 * @return array<int, array{label: string, name: string, detail: string}>
 */
function place_insights( array $posts ): array {
	if ( count( $posts ) < MIN_PICK ) {
		return array();
	}
	$out = array();

	/** Sole holder of the maximum, or null. */
	$sole_max = static function ( array $vals ): ?int {
		if ( ! $vals ) {
			return null;
		}
		$max = max( $vals );
		if ( $max <= 0 ) {
			return null;
		}
		$at = array_keys( $vals, $max, true );
		return 1 === count( $at ) ? (int) $at[0] : null;
	};

	$names   = array();
	$reviews = array();
	$ratings = array();
	$svc_n   = array();
	$online  = array();
	foreach ( $posts as $i => $p ) {
		$id            = (int) $p->ID;
		$names[ $i ]   = get_the_title( $p );
		$r             = function_exists( '\Oria\Theme\effective_rating' ) ? \Oria\Theme\effective_rating( $id ) : array( 'rating' => 0, 'count' => 0 );
		$reviews[ $i ] = (int) $r['count'];
		$ratings[ $i ] = (float) $r['rating'];
		$svc           = wp_get_post_terms( $id, 'service', array( 'fields' => 'names' ) );
		$svc_n[ $i ]   = is_wp_error( $svc ) ? 0 : count( $svc );
		$online[ $i ]  = in_array( (string) get_field( 'format', $id ), array( 'online', 'both' ), true ) ? 1 : 0;
	}

	$i = $sole_max( $reviews );
	if ( null !== $i ) {
		$out[] = array(
			'label'  => __( 'Most reviewed', 'oria' ),
			'name'   => $names[ $i ],
			/* translators: %s: number of reviews */
			'detail' => sprintf( __( '%s Google reviews — the most of these.', 'oria' ), number_format_i18n( $reviews[ $i ] ) ),
		);
	}

	// Ratings are compared to one decimal, which is how they are shown.
	$rounded = array_map( static fn( float $v ): int => (int) round( $v * 10 ), $ratings );
	$i       = $sole_max( $rounded );
	if ( null !== $i ) {
		$out[] = array(
			'label'  => __( 'Highest rated', 'oria' ),
			'name'   => $names[ $i ],
			/* translators: %s: rating out of five */
			'detail' => sprintf( __( '%s on Google. We neither set nor checked this figure.', 'oria' ), number_format_i18n( $ratings[ $i ], 1 ) ),
		);
	}

	$i = $sole_max( $svc_n );
	if ( null !== $i && $svc_n[ $i ] > 1 ) {
		$out[] = array(
			'label'  => __( 'Lists the most classes', 'oria' ),
			'name'   => $names[ $i ],
			/* translators: %d: number of listed services */
			'detail' => sprintf(
				_n( '%d class or service on file. Others may run more without having told us.', '%d classes and services on file. Others may run more without having told us.', $svc_n[ $i ], 'oria' ),
				$svc_n[ $i ]
			),
		);
	}

	if ( 1 === array_sum( $online ) ) {
		$i     = (int) array_search( 1, $online, true );
		$out[] = array(
			'label'  => __( 'The only one online', 'oria' ),
			'name'   => $names[ $i ],
			'detail' => __( 'Runs sessions online as well as in person.', 'oria' ),
		);
	}

	/*
	 * A specialty exactly one of them lists. This is the brief's "best for
	 * sound healing" without the verdict — it says who lists it, which is
	 * all we actually know, and it is the most useful line on the page
	 * when it fires.
	 */
	$by_spec = array();
	foreach ( $posts as $i => $p ) {
		$sp = wp_get_post_terms( (int) $p->ID, 'specialty', array( 'fields' => 'names' ) );
		foreach ( ( is_wp_error( $sp ) ? array() : $sp ) as $name ) {
			$by_spec[ $name ][] = $i;
		}
	}
	$uniques = array();
	foreach ( $by_spec as $name => $holders ) {
		if ( 1 === count( $holders ) ) {
			$uniques[ $holders[0] ][] = $name;
		}
	}
	foreach ( $uniques as $idx => $list ) {
		if ( count( $out ) >= 5 ) {
			break;
		}
		$out[] = array(
			/* translators: %s: a specialty only one of the compared places lists */
			'label'  => sprintf( __( 'Only one lists %s', 'oria' ), strtolower( (string) $list[0] ) ),
			'name'   => $names[ $idx ],
			'detail' => 1 === count( $list )
				? __( 'None of the others has this on file.', 'oria' )
				/* translators: %s: comma-separated specialties */
				: sprintf( __( 'Also the only one listing %s.', 'oria' ), strtolower( implode( ', ', array_slice( $list, 1, 2 ) ) ) ),
		);
	}

	return $out;
}

/**
 * One conditional sentence per business, built from whatever genuinely
 * distinguishes it. Conditional on purpose — "worth a look IF this is what
 * you want" is a fact about the listing; "the best choice" would be a
 * judgement we have no basis for.
 *
 * @param array<int, \WP_Post> $posts
 * @return array<int, array{name: string, url: string, line: string}>
 */
function place_reasons( array $posts ): array {
	$spec_of = array();
	foreach ( $posts as $i => $p ) {
		$sp             = wp_get_post_terms( (int) $p->ID, 'specialty', array( 'fields' => 'names' ) );
		$spec_of[ $i ]  = is_wp_error( $sp ) ? array() : $sp;
	}

	$out = array();
	foreach ( $posts as $i => $p ) {
		$id     = (int) $p->ID;
		$others = array();
		foreach ( $spec_of as $j => $list ) {
			if ( $j !== $i ) {
				$others = array_merge( $others, $list );
			}
		}
		$only = array_values( array_diff( $spec_of[ $i ], $others ) );

		$svc   = wp_get_post_terms( $id, 'service', array( 'fields' => 'names' ) );
		$svc   = is_wp_error( $svc ) ? array() : $svc;
		$fmt   = (string) get_field( 'format', $id );
		$areas = wp_get_post_terms( $id, 'area', array( 'fields' => 'names' ) );

		if ( $only ) {
			/* translators: %s: specialties only this business lists */
			$line = sprintf( __( 'The only one here listing %s.', 'oria' ), strtolower( implode( ' and ', array_slice( $only, 0, 2 ) ) ) );
		} elseif ( count( $svc ) > 2 ) {
			/* translators: %s: a few of the listed services */
			$line = sprintf( __( 'Lists the widest range here, including %s.', 'oria' ), strtolower( implode( ', ', array_slice( $svc, 0, 3 ) ) ) );
		} elseif ( in_array( $fmt, array( 'online', 'both' ), true ) ) {
			$line = __( 'Worth a look if getting there is the hard part — it runs online too.', 'oria' );
		} elseif ( ! is_wp_error( $areas ) && $areas ) {
			/* translators: %s: suburb */
			$line = sprintf( __( 'Worth a look if %s is convenient for you.', 'oria' ), (string) $areas[0] );
		} else {
			$line = __( 'The listing carries the detail we hold.', 'oria' );
		}

		$out[] = array(
			'name' => get_the_title( $p ),
			'url'  => (string) get_permalink( $p ),
			'line' => $line,
		);
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

	/*
	 * Where a curated pair page exists for exactly this two, link to it
	 * rather than to the query string. Every "Compare yoga with Pilates"
	 * link the site already generates — category pages, the finder nudge,
	 * the picker's own shortcut — then points at the indexable address
	 * instead of one that canonicals away, which is both the better link
	 * and the only thing stopping the pair pages being orphans.
	 */
	$slug = pair_for_ids( $ids );
	if ( null !== $slug ) {
		return pair_url( $slug );
	}

	return home_url( '/' . PATH . '/?with=' . implode( ',', array_map( 'rawurlencode', $ids ) ) );
}

/**
 * The curated pair page matching exactly this set of ids, order-blind.
 *
 * Exactly: three ids are a different page from any pair, and returning a
 * two-way page for a three-way pick would quietly drop a column.
 */
function pair_for_ids( array $ids ): ?string {
	if ( 2 !== count( $ids ) ) {
		return null;
	}
	$want = array_map( 'strval', $ids );
	sort( $want );

	foreach ( pairs() as $slug => $pair ) {
		$have = array( (string) $pair['a'], (string) $pair['b'] );
		sort( $have );
		if ( $have === $want ) {
			return $slug;
		}
	}
	return null;
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
	// The hub, the builder and the curated pair pages — every address the
	// engine actually answers on its own canonical. The query strings are
	// never listed: they canonical to the hub.
	$out = array(
		array( 'loc' => home_url( '/' . PATH . '/' ) ),
		array( 'loc' => home_url( '/' . PATH . '/build/' ) ),
	);
	foreach ( pairs() as $slug => $pair ) {
		$out[] = array( 'loc' => pair_url( $slug ) );
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
	if ( is_build() ) {
		return __( 'Build your session', 'oria' );
	}
	$pair = current_pair();
	if ( $pair ) {
		/* translators: %s: the pair heading, e.g. "Yoga vs Pilates" */
		return sprintf( __( '%s in Perth', 'oria' ), (string) $pair['h1'] );
	}
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
	if ( is_build() ) {
		return __( 'Say what you want a session to be like — how hard, how quiet, how guided, how social — and see which Perth wellness practices sit closest. Descriptions of the room, never promises about you.', 'oria' );
	}
	$pair = current_pair();
	if ( $pair && '' !== (string) ( $pair['blurb'] ?? '' ) ) {
		return (string) $pair['blurb'];
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
	if ( ! is_compare() ) {
		return $url;
	}
	// The builder and each pair page are their own addresses; only the
	// ?with= and ?places= states of /compare/ fold back into the hub.
	if ( is_build() ) {
		return home_url( '/' . PATH . '/build/' );
	}
	$pair = current_pair();
	return $pair ? pair_url( (string) $pair['slug'] ) : home_url( '/' . PATH . '/' );
}
