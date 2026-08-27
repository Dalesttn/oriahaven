<?php
/**
 * /practices/ — the Practices index behind the nav item of the same name.
 *
 * Every practice category as a tile, with its intent pages beneath where
 * those are allowed to be seen (IntentPages\visible_for). The /perth/ hub
 * lists everything the directory has in one flat page for crawl depth;
 * this one is for a person choosing a practice, so it carries the tile
 * images and blurbs the terms already hold and stops at categories.
 *
 * A route rather than a WP page, like the hub: it ships in git and exists
 * on production the moment the code lands.
 */

declare(strict_types=1);

namespace Oria\Core\PracticesIndex;

use Oria\Core\IntentPages;
use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_practices';
const V2_VAR    = 'oria_practice_v2';
const FACET_VAR = 'oria_facet';
const PATH      = 'practices';
const FACET_MIN = 3; // listings a facet page needs before it may be indexed
const SITEMAP   = 'facet'; // /facet-sitemap.xml — the indexable facet pages
const SITEMAP_CACHE = 'oria_facet_sitemap'; // built list; walking it live is a timeout
const TILE_LINKS = 5; // links a Practices-index tile lists beneath its blurb

/* Share of a category's listings a style must appear on to be offered
   as a drill-down on its tile. Keeps cross-category specialties off. */
const STYLE_SHARE = 0.25;

/**
 * The redesigned category directory lives at /practices/{practice}/ — the
 * same practice taxonomy archive (so the engine, the answer block, the
 * intents and the FAQ all work unchanged), with a different template.
 *
 * Mode, from the option `oria_directory_v2`:
 *   ''         review — routes exist, noindex, canonical to /practice/{slug}/,
 *              nothing links to them. Safe on production.
 *   'preview'  as review, but the Practices index links to the new pages, so
 *              a person can walk the new experience end to end. Local.
 *   'live'     indexable, self-canonical, linked. The decision to flip this
 *              is the decision to make the new design the category page.
 */
function mode(): string {
	$m = (string) get_option( 'oria_directory_v2', '' );
	return in_array( $m, array( 'preview', 'live' ), true ) ? $m : '';
}

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 10 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_action( 'template_redirect', __NAMESPACE__ . '\facet_404', 4 );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );

	// Every link to a practice category — footer, header search, listing
	// cards, the hub, the 404 page — goes to the new page once v2 is on.
	add_filter( 'term_link', __NAMESPACE__ . '\term_link', 10, 3 );

	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 25 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description', 25 );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical', 25 );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\robots', 25 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 25 );

	// 101 facet pages were live, indexable and in no sitemap: nothing but a
	// category tile linked them, so Search Console reported every one as
	// "URL is unknown to Google". They are the pages carrying the directory's
	// biggest terms, so they get the same treatment the intent pages already
	// had — their own sitemap, gated by exactly the test robots() applies.
	add_action( 'init', __NAMESPACE__ . '\register_sitemap', 20 );
	add_filter( 'wpseo_sitemap_index', __NAMESPACE__ . '\sitemap_index' );
	add_action( 'save_post', __NAMESPACE__ . '\flush_sitemap_cache' );
	add_action( 'deleted_post', __NAMESPACE__ . '\flush_sitemap_cache' );
	add_action( 'set_object_terms', __NAMESPACE__ . '\flush_sitemap_cache_terms', 10, 4 );
}

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QUERY_VAR . '=1', 'top' );
	// /practices/{practice}/ → the practice archive, flagged for the v2 template.
	add_rewrite_rule( '^' . PATH . '/([^/]+)/?$', 'index.php?practice=$matches[1]&' . V2_VAR . '=1', 'top' );
	// /practices/{practice}/{facet}/ → the same page with one facet locked:
	// a style (service term), a specialty, an audience, online, or free.
	add_rewrite_rule( '^' . PATH . '/([^/]+)/([^/]+)/?$', 'index.php?practice=$matches[1]&' . V2_VAR . '=1&' . FACET_VAR . '=$matches[2]', 'top' );
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	$vars[] = V2_VAR;
	$vars[] = FACET_VAR;
	return $vars;
}

/* ------------------------------------------------------------------ facets */

/**
 * The facet a /practices/{practice}/{slug}/ URL locks, or null.
 *
 * A slug resolves, in order: an intent-registry page for this practice
 * (beginners, yin, reformer — the hand-framed ones); a service term, by its
 * slug or by its short form with the practice's own word dropped
 * ("vinyasa" for "vinyasa-yoga" on the yoga page); a specialty term; and the
 * two fixed facets "online" and "free". Anything else is a 404 rather than
 * a silent fall-through to the category, so the URL space stays finite.
 *
 * @return array{slug: string, key: string, value: string, label: string, page: ?array}|null
 */
function facet(): ?array {
	static $cache = false;
	if ( false !== $cache ) {
		return $cache;
	}
	$cache = null;
	if ( ! is_category() ) {
		return null;
	}
	$slug = sanitize_title( (string) get_query_var( FACET_VAR ) );
	$term = get_queried_object();
	if ( '' === $slug || ! $term instanceof \WP_Term ) {
		return null;
	}
	return $cache = resolve_facet( $term, $slug );
}

function resolve_facet( \WP_Term $practice, string $slug ): ?array {
	// 1. A registry page (its filter is authoritative, its frame is the copy).
	if ( function_exists( '\Oria\Core\IntentPages\page' ) ) {
		$page = IntentPages\page( $practice->slug, $slug );
		if ( null !== $page ) {
			$key = (string) array_key_first( $page['filter'] );
			return array( 'slug' => $slug, 'key' => $key, 'value' => (string) $page['filter'][ $key ], 'label' => (string) ( $page['frame']['h1'] ?? $page['label'] ), 'page' => $page );
		}
	}
	// 2. A service term — exact slug, or the short form. One address per
	//    facet: if a registry page locks this same service, or the short
	//    form exists, that is the canonical slug and the resolver reports it;
	//    facet_guard() redirects the other spellings to it.
	if ( taxonomy_exists( 'service' ) ) {
		foreach ( array( $slug, $slug . '-' . $practice->slug ) as $try ) {
			$t = get_term_by( 'slug', $try, 'service' );
			if ( ! $t instanceof \WP_Term ) {
				continue;
			}
			if ( function_exists( '\Oria\Core\IntentPages\pages_for' ) ) {
				foreach ( IntentPages\pages_for( $practice->slug ) as $p ) {
					if ( ( $p['filter']['svc'] ?? '' ) === $t->slug ) {
						return array( 'slug' => $p['intent'], 'key' => 'svc', 'value' => $t->slug, 'label' => (string) ( $p['frame']['h1'] ?? $p['label'] ), 'page' => $p );
					}
				}
			}
			$suffix    = '-' . $practice->slug;
			$canonical = str_ends_with( $t->slug, $suffix ) && strlen( $t->slug ) > strlen( $suffix ) ? substr( $t->slug, 0, -strlen( $suffix ) ) : $t->slug;
			return array( 'slug' => $canonical, 'key' => 'svc', 'value' => $t->slug, 'label' => sprintf( '%s in Perth', wp_specialchars_decode( $t->name, ENT_QUOTES ) ), 'page' => null );
		}
	}
	// 3. A specialty term.
	$t = get_term_by( 'slug', $slug, Taxonomies\SPECIALTY );
	if ( $t instanceof \WP_Term ) {
		return array( 'slug' => $slug, 'key' => 'spec', 'value' => $t->slug, 'label' => sprintf( '%s in Perth', wp_specialchars_decode( $t->name, ENT_QUOTES ) ), 'page' => null );
	}
	// 4. An audience term (only reachable when the intent rows offer it).
	if ( taxonomy_exists( 'audience' ) ) {
		$t = get_term_by( 'slug', $slug, 'audience' );
		if ( $t instanceof \WP_Term ) {
			return array( 'slug' => $slug, 'key' => 'aud', 'value' => $t->slug, 'label' => sprintf( '%s — %s in Perth', wp_specialchars_decode( $t->name, ENT_QUOTES ), wp_specialchars_decode( $practice->name, ENT_QUOTES ) ), 'page' => null );
		}
	}
	// 5. The fixed pair.
	$pname = wp_specialchars_decode( $practice->name, ENT_QUOTES );
	if ( 'online' === $slug ) {
		return array( 'slug' => 'online', 'key' => 'format', 'value' => 'online', 'label' => sprintf( 'Online %s in Perth', lcfirst( $pname ) ), 'page' => null );
	}
	if ( 'free' === $slug ) {
		return array( 'slug' => 'free', 'key' => 'price', 'value' => 'Free', 'label' => sprintf( 'Free or by-donation %s in Perth', lcfirst( $pname ) ), 'page' => null );
	}
	return null;
}

/**
 * The clean address for a filter the intent rows describe — the inverse of
 * resolve_facet(), so a row's ?svc=vinyasa-yoga becomes /practices/yoga/vinyasa/.
 * Returns '' when no clean slug exists (the row keeps its filter URL).
 */
function facet_url_for_query( \WP_Term $practice, string $query ): string {
	parse_str( $query, $q );
	$slug = '';
	if ( ! empty( $q['svc'] ) ) {
		$svc = sanitize_title( (string) $q['svc'] );
		// Prefer a registry slug that locks this exact service.
		if ( function_exists( '\Oria\Core\IntentPages\pages_for' ) ) {
			foreach ( IntentPages\pages_for( $practice->slug ) as $p ) {
				if ( ( $p['filter']['svc'] ?? '' ) === $svc ) {
					$slug = $p['intent'];
					break;
				}
			}
		}
		if ( '' === $slug ) {
			$suffix = '-' . $practice->slug;
			$slug   = str_ends_with( $svc, $suffix ) && strlen( $svc ) > strlen( $suffix ) ? substr( $svc, 0, -strlen( $suffix ) ) : $svc;
		}
	} elseif ( ! empty( $q['spec'] ) ) {
		$slug = sanitize_title( (string) $q['spec'] );
	} elseif ( ! empty( $q['aud'] ) ) {
		$slug = sanitize_title( (string) $q['aud'] );
	} elseif ( ! empty( $q['format'] ) && 'online' === $q['format'] ) {
		$slug = 'online';
	} elseif ( ! empty( $q['price'] ) && 'Free' === $q['price'] ) {
		$slug = 'free';
	}
	return '' === $slug ? '' : category_url( $practice ) . $slug . '/';
}

/** Listings in the practice that match the locked facet. @return list<int> */
function facet_ids( \WP_Term $practice, array $facet ): array {
	return function_exists( '\Oria\Core\IntentPages\matching_ids' )
		? IntentPages\matching_ids( $practice, array( $facet['key'] => $facet['value'] ) )
		: array();
}

/**
 * Keep the facet URL space tidy: an unresolvable slug is a 404, not a
 * silent fall-through to the category, and a resolvable-but-non-canonical
 * spelling (reformer-pilates for reformer, vinyasa-yoga for vinyasa) is a
 * 301 to the one address the grid links to.
 */
function facet_404(): void {
	if ( ! is_tax( Taxonomies\PRACTICE ) ) {
		return;
	}
	$asked = sanitize_title( (string) get_query_var( FACET_VAR ) );
	if ( '' === $asked ) {
		return;
	}
	$f = facet();
	if ( null === $f ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return;
	}
	if ( $f['slug'] !== $asked ) {
		$term = get_queried_object();
		if ( $term instanceof \WP_Term ) {
			wp_safe_redirect( category_url( $term ) . $f['slug'] . '/', 301 );
			exit;
		}
	}
}

function is_index(): bool {
	return (bool) get_query_var( QUERY_VAR );
}

/** The redesigned category page for a practice term? */
function is_category(): bool {
	return (bool) get_query_var( V2_VAR ) && is_tax( Taxonomies\PRACTICE );
}

function url(): string {
	return home_url( '/' . PATH . '/' );
}

function category_url( \WP_Term $term ): string {
	return home_url( '/' . PATH . '/' . $term->slug . '/' );
}

/** Where a tile should send people: the new page in preview/live, else the original. */
function tile_url( \WP_Term $term ): string {
	return '' !== mode() ? category_url( $term ) : (string) get_term_link( $term );
}

/**
 * The original /practice/{slug}/ address of a practice term, with the
 * sitewide rewrite below switched off — for canonicals and for code that
 * needs to recognise the old family of URLs.
 */
function original_url( \WP_Term $term ): string {
	static $bypass = false;
	if ( $bypass ) {
		return (string) get_term_link( $term );
	}
	$bypass = true;
	remove_filter( 'term_link', __NAMESPACE__ . '\term_link', 10 );
	$url = (string) get_term_link( $term );
	add_filter( 'term_link', __NAMESPACE__ . '\term_link', 10, 3 );
	$bypass = false;
	return $url;
}

/**
 * `term_link` filter: in preview and live, every practice-category link
 * across the site points at the new page. Review mode ('' — production
 * today) leaves every link exactly as it was.
 *
 * @param string   $url
 * @param \WP_Term $term
 * @param string   $taxonomy
 */
function term_link( $url, $term, $taxonomy ) {
	if ( Taxonomies\PRACTICE === $taxonomy && $term instanceof \WP_Term && '' !== mode() ) {
		return category_url( $term );
	}
	return $url;
}

/**
 * Links written inside editorial copy — a landing intro's "Related: yoga,
 * pilates, barre" — point at the original family of pages (/practice/{p}/,
 * /perth/{specialty}/, /directory/?cat=). On a v2 page, in preview or
 * live, rewrite them on the way out so the copy stays one set of words
 * wherever it is shown:
 *
 *   /practice/{p}/            → /practices/{p}/
 *   /practice/{p}/{area}/     → /practices/{p}/?region= | ?suburb=
 *   /practice/{p}/{intent}/   → /practices/{p}/{intent}/
 *   /perth/{specialty}/       → /practices/{here}/{specialty}/ when that
 *                               facet has listings under the current
 *                               category, else /directory/?spec=
 *   /directory/?cat={p}       → /practices/{p}/
 *
 * Anything else — areas, journal, listings, external — is left alone.
 * Review mode ('') returns the HTML untouched.
 */
function rewrite_content_links( string $html, ?\WP_Term $here = null ): string {
	if ( '' === mode() || '' === $html || false === stripos( $html, 'href' ) ) {
		return $html;
	}
	return (string) preg_replace_callback(
		'~href=(["\'])([^"\']+)\1~i',
		static function ( array $m ) use ( $here ): string {
			$new = rewrite_url( html_entity_decode( $m[2], ENT_QUOTES ), $here );
			return '' === $new ? $m[0] : 'href=' . $m[1] . esc_url( $new ) . $m[1];
		},
		$html
	);
}

/**
 * One URL from the original family mapped to its v2 address — the rule set
 * rewrite_content_links() applies to a block of HTML, for a single stored
 * link (a hero quick pill, a button). Returns '' when the URL is not one
 * that moves: external, an area page, a listing, a journal post, or any
 * URL at all in review mode.
 */
function rewrite_url( string $url, ?\WP_Term $here = null ): string {
	if ( '' === mode() || '' === $url ) {
		return '';
	}
	$home = untrailingslashit( home_url( '/' ) );
	$rel  = 0 === strpos( $url, $home ) ? substr( $url, strlen( $home ) ) : $url;
	$path = (string) wp_parse_url( $rel, PHP_URL_PATH );
	$qs   = (string) wp_parse_url( $rel, PHP_URL_QUERY );
	if ( '' === $path || '/' !== $path[0] ) {
		return ''; // external, mailto, anchor — not ours to touch
	}
	if ( preg_match( '~^/practice/([^/]+)/(?:([^/]+)/)?$~', $path, $pm ) ) {
		$practice = get_term_by( 'slug', $pm[1], Taxonomies\PRACTICE );
		if ( ! $practice instanceof \WP_Term ) {
			return '';
		}
		if ( empty( $pm[2] ) ) {
			return category_url( $practice );
		}
		$area = get_term_by( 'slug', $pm[2], Taxonomies\AREA );
		return $area instanceof \WP_Term ? area_url( $practice, $area ) : category_url( $practice ) . $pm[2] . '/';
	}
	if ( preg_match( '~^/perth/([^/]+)/$~', $path, $pm ) ) {
		$spec = get_term_by( 'slug', $pm[1], Taxonomies\SPECIALTY );
		if ( ! $spec instanceof \WP_Term ) {
			// A specialty since merged into a practice (/perth/yoga/
			// redirects to the category): send people straight there.
			$practice = get_term_by( 'slug', $pm[1], Taxonomies\PRACTICE );
			return $practice instanceof \WP_Term ? category_url( $practice ) : '';
		}
		if ( $here instanceof \WP_Term ) {
			$facet = resolve_facet( $here, $spec->slug );
			if ( null !== $facet && in_array( $facet['key'], array( 'spec', 'svc' ), true ) && facet_ids( $here, $facet ) ) {
				return category_url( $here ) . $facet['slug'] . '/';
			}
		}
		return specialty_url( $spec );
	}
	if ( '/directory/' === $path && '' !== $qs ) {
		parse_str( $qs, $q );
		if ( ! empty( $q['cat'] ) && is_string( $q['cat'] ) && 1 === count( $q ) ) {
			$practice = get_term_by( 'slug', $q['cat'], Taxonomies\PRACTICE );
			return $practice instanceof \WP_Term ? category_url( $practice ) : '';
		}
	}
	return '';
}

/**
 * A heading for a filtered view reached by URL — /directory/?spec=forest-
 * bathing, /practices/yoga/?suburb=Fremantle — so the page names what it
 * is showing: "Forest bathing in Perth", "Yoga in Fremantle". Reads the
 * same query keys the engine reads (spec, svc, aud, suburb, region); one
 * value each, the first if several. Empty when the URL carries none.
 */
function query_heading( ?\WP_Term $practice = null ): string {
	$get = static function ( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return '';
		}
		$v = sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return trim( (string) strtok( $v, ',' ) );
	};
	$name = static fn( \WP_Term $t ): string => wp_specialchars_decode( $t->name, ENT_QUOTES );

	// What — a specialty, a style, or the practice itself.
	$thing = '';
	$spec  = $get( 'spec' );
	$svc   = $get( 'svc' );
	if ( '' !== $spec ) {
		$t = get_term_by( 'slug', $spec, Taxonomies\SPECIALTY );
		if ( $t instanceof \WP_Term ) { $thing = $name( $t ); }
	} elseif ( '' !== $svc && taxonomy_exists( 'service' ) ) {
		$t = get_term_by( 'slug', $svc, 'service' );
		if ( $t instanceof \WP_Term ) { $thing = $name( $t ); }
	}

	// Who — an audience, phrased as "for beginners".
	$for = '';
	$aud = $get( 'aud' );
	if ( '' !== $aud && taxonomy_exists( 'audience' ) ) {
		$t = get_term_by( 'slug', $aud, 'audience' );
		if ( $t instanceof \WP_Term ) { $for = lcfirst( $name( $t ) ); }
	}

	// Where — a suburb (by name, as the engine carries it) or a region.
	$where  = '';
	$suburb = $get( 'suburb' );
	$region = $get( 'region' );
	if ( '' !== $suburb ) {
		$t = get_term_by( 'name', $suburb, Taxonomies\AREA ) ?: get_term_by( 'slug', sanitize_title( $suburb ), Taxonomies\AREA );
		if ( $t instanceof \WP_Term ) { $where = $name( $t ); }
	} elseif ( '' !== $region ) {
		$t = get_term_by( 'slug', $region, Taxonomies\AREA );
		if ( $t instanceof \WP_Term ) { $where = $name( $t ); }
	}

	if ( '' === $thing && '' === $for && '' === $where ) {
		return '';
	}
	// A registry page that locks this same filter already has the right
	// words ("Beginner yoga in Perth"); use them, swapping the area in.
	if ( $practice instanceof \WP_Term && function_exists( '\Oria\Core\IntentPages\pages_for' ) ) {
		$want = '' !== $spec ? array( 'spec', $spec ) : ( '' !== $svc ? array( 'svc', $svc ) : ( '' !== $aud ? array( 'aud', $aud ) : null ) );
		if ( null !== $want ) {
			foreach ( IntentPages\pages_for( $practice->slug ) as $pg ) {
				if ( ( $pg['filter'][ $want[0] ] ?? null ) === $want[1] && ! empty( $pg['frame']['h1'] ) ) {
					$h1 = (string) $pg['frame']['h1'];
					return '' !== $where ? (string) preg_replace( '/\s+in Perth$/', ' in ' . $where, $h1 ) : $h1;
				}
			}
		}
	}
	if ( '' !== $for ) {
		// "Beginner friendly" → "for beginners"; other names read as they are.
		$for = preg_replace( '/\s+friendly$/i', '', $for );
		if ( 'beginner' === strtolower( $for ) ) { $for = __( 'beginners', 'oria' ); }
	}
	if ( '' === $thing ) {
		$thing = $practice instanceof \WP_Term ? $name( $practice ) : __( 'Wellness', 'oria' );
	}
	if ( '' !== $for ) {
		/* translators: 1: practice or style, 2: audience ("beginners") */
		$thing = sprintf( __( '%1$s for %2$s', 'oria' ), $thing, $for );
	}
	/* translators: 1: what, 2: where */
	return sprintf( __( '%1$s in %2$s', 'oria' ), $thing, '' !== $where ? $where : __( 'Perth', 'oria' ) );
}

/**
 * Where a specialty link should send people: the specialty's own page,
 * always.
 *
 * This used to return /directory/?spec= whenever the v2 mode was on,
 * because /perth/{slug}/ was still the old layout and the filtered
 * directory was the better room to arrive in. That stopped being true when
 * the specialty template was rebuilt in the v2 design: the term page now
 * carries the same listings PLUS the answer block, the facts strip, the
 * written intro, the FAQ and its own schema, and it is the indexable
 * address the long-tail work depends on.
 *
 * It is also what un-orphans them. Every specialty link on every listing,
 * hub and finder result pointed at a query string, which is a large part
 * of why the audit found 77 specialty pages with one inbound link each.
 */
function specialty_url( \WP_Term $specialty ): string {
	return (string) get_term_link( $specialty );
}

/**
 * Where a directory "browse by area" pill should send people: the new
 * directory with that region applied in preview/live, else the region's
 * own page.
 */
function region_url( \WP_Term $region ): string {
	if ( '' === mode() ) {
		return (string) get_term_link( $region );
	}
	return get_post_type_archive_link( PostTypes\LISTING ) . '?region=' . rawurlencode( $region->slug );
}

/**
 * Where the "{practice} by area" pills should send people: the new page
 * with that area already applied in preview/live (the engine reads
 * ?region= and ?suburb= on load), else the original /practice/{p}/{area}/
 * page.
 */
function area_url( \WP_Term $practice, \WP_Term $area ): string {
	if ( '' === mode() ) {
		return home_url( '/practice/' . $practice->slug . '/' . $area->slug . '/' );
	}
	// Regions sit under the city term, so parent alone does not tell a
	// region from a suburb — the taxonomy helpers do.
	$q = Taxonomies\is_suburb( $area )
		? 'suburb=' . rawurlencode( wp_specialchars_decode( $area->name, ENT_QUOTES ) )
		: 'region=' . rawurlencode( $area->slug );
	return category_url( $practice ) . '?' . $q;
}

/**
 * Review and preview answer noindex; only live is indexable — and a facet
 * page additionally needs FACET_MIN listings, the same floor as a combo.
 */
function robots( $robots ) {
	if ( ! is_category() ) {
		return $robots;
	}
	if ( 'live' !== mode() ) {
		return 'noindex, follow';
	}
	$f = facet();
	if ( null !== $f ) {
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term || count( facet_ids( $term, $f ) ) < FACET_MIN ) {
			return 'noindex, follow';
		}
	}
	return $robots;
}

/** Same trick as the hub: stop a parameterless rule reading as the home page. */
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

function template( string $template ): string {
	// The directory itself, in the new layout, once the switch is on.
	if ( '' !== mode() && is_post_type_archive( PostTypes\LISTING ) && ! is_search() ) {
		$found = locate_template( array( 'oria-directory-v2.php' ) );
		if ( $found ) {
			return $found;
		}
	}
	/*
	 * The practice x suburb combos — /practice/recovery/currambine/ — take
	 * the same layout. They kept the v1 taxonomy template only because they
	 * live under the old URL prefix, which was never a reason: a combo is a
	 * category page with one more filter locked, exactly like a facet page.
	 * The URL is untouched; only what renders at it changes.
	 */
	if ( is_category() || ( '' !== mode() && \Oria\Core\Seo\combo_area() ) ) {
		$found = locate_template( array( 'oria-practice-v2.php' ) );
		return $found ? $found : $template;
	}
	if ( ! is_index() ) {
		return $template;
	}
	$found = locate_template( array( 'oria-practices.php' ) );
	return $found ? $found : $template;
}

/* ---------------------------------------------------------------- sitemap */

/**
 * Every facet page that is actually indexable, as absolute URLs.
 *
 * The gate is not re-derived here — it is the same pair of conditions
 * robots() applies (live mode, and FACET_MIN listings behind the facet),
 * so a page can never be advertised in the sitemap while carrying noindex.
 *
 * Candidates come from the terms the category's own listings carry, which
 * is the only honest source: a service that no listing in this category
 * offers has no page here to find. Each candidate goes through
 * resolve_facet() so the URL emitted is the canonical spelling — the one
 * facet_404() redirects the others to — and never a second address for a
 * page already listed.
 *
 * Intent-backed facets are left out on purpose. Those have a registry
 * frame and are published by IntentPages' own sitemap; listing them twice
 * would put one page at one URL in two sitemaps.
 *
 * @return list<array{loc: string}>
 */
function sitemap_entries(): array {
	if ( 'live' !== mode() ) {
		return array();
	}

	$cached = get_transient( SITEMAP_CACHE );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$out = array();

	foreach ( practices() as $practice ) {
		$ids = function_exists( '\Oria\Core\Intents\listings_in' )
			? \Oria\Core\Intents\listings_in( $practice )
			: array();
		if ( ! $ids ) {
			continue;
		}

		// The slugs a person could actually reach on this category.
		$candidates = array();
		foreach ( $ids as $id ) {
			foreach ( array( 'service', Taxonomies\SPECIALTY ) as $tax ) {
				if ( ! taxonomy_exists( $tax ) ) {
					continue;
				}
				$terms = wp_get_post_terms( (int) $id, $tax );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $t ) {
					$candidates[ $t->slug ] = true;
				}
			}
		}

		$seen = array();
		foreach ( array_keys( $candidates ) as $slug ) {
			$f = resolve_facet( $practice, $slug );
			if ( null === $f || null !== $f['page'] ) {
				continue; // unresolvable, or the intent sitemap's to publish
			}
			if ( isset( $seen[ $f['slug'] ] ) ) {
				continue; // a non-canonical spelling of one already emitted
			}
			$seen[ $f['slug'] ] = true;

			if ( count( facet_ids( $practice, $f ) ) < FACET_MIN ) {
				continue; // robots() would noindex it
			}
			$out[] = array( 'loc' => category_url( $practice ) . $f['slug'] . '/' );
		}
	}

	set_transient( SITEMAP_CACHE, $out, DAY_IN_SECONDS );
	return $out;
}

/**
 * Drop the cached list when the data behind it moves.
 *
 * Both hooks fire on far more than listings, so each checks first: rebuilding
 * this on an unrelated save would hand the two-minute walk to whoever pressed
 * Update.
 */
function flush_sitemap_cache( $post_id = 0 ): void {
	if ( $post_id && PostTypes\LISTING !== get_post_type( (int) $post_id ) ) {
		return;
	}
	delete_transient( SITEMAP_CACHE );
}

/** @param int $object_id */
function flush_sitemap_cache_terms( $object_id, $terms = null, $tt_ids = null, $taxonomy = '' ): void {
	if ( ! in_array( (string) $taxonomy, array( 'service', Taxonomies\SPECIALTY, Taxonomies\PRACTICE ), true ) ) {
		return;
	}
	flush_sitemap_cache( (int) $object_id );
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
	$links = array();
	foreach ( sitemap_entries() as $e ) {
		$links[] = array( 'loc' => $e['loc'], 'mod' => gmdate( 'c' ) );
	}
	$sm->set_sitemap( $sm->renderer->get_sitemap( $links, SITEMAP, 1 ) );
}

/** Only advertise the sitemap when it has something in it. */
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

/**
 * The practice terms worth a tile: anything with listings of its own, or
 * whose children have listings — a parent such as "Mind & Mental
 * Wellbeing" counts nothing directly and is still a real category.
 *
 * @return list<\WP_Term>
 */
function practices(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => Taxonomies\PRACTICE,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$out = array();
	foreach ( $terms as $t ) {
		if ( $t->count > 0 ) {
			$out[] = $t;
			continue;
		}
		$kids = get_terms(
			array(
				'taxonomy'   => Taxonomies\PRACTICE,
				'parent'     => $t->term_id,
				'hide_empty' => true,
				'fields'     => 'ids',
			)
		);
		if ( is_array( $kids ) && $kids ) {
			$out[] = $t;
		}
	}
	return $out;
}

/** @return list<array{url: string, label: string, count: int}> */
function intent_links( \WP_Term $practice ): array {
	$links = array();
	$seen  = array(); // "key:value" filters already linked, so a style is never listed twice
	if ( function_exists( '\Oria\Core\IntentPages\visible_for' ) ) {
		foreach ( IntentPages\visible_for( $practice->slug ) as $p ) {
			$links[] = array(
				// The same page under the new family once v2 is on.
				'url'   => '' !== mode() ? category_url( $practice ) . $p['intent'] . '/' : IntentPages\url( $p['practice'], $p['intent'] ),
				'label' => (string) ( $p['frame']['h1'] ?? $p['label'] ),
				'count' => (int) IntentPages\facts( $p )['count'],
			);
			foreach ( $p['filter'] as $k => $v ) {
				$seen[ $k . ':' . $v ] = true;
			}
		}
	}
	// In preview/live the tile also lists the category's own styles and
	// specialties — the drill-down the Style & specialty facet offers.
	if ( '' !== mode() ) {
		foreach ( style_links( $practice ) as $l ) {
			if ( empty( $seen[ $l['key'] . ':' . $l['value'] ] ) ) {
				$links[] = $l;
			}
		}
	}
	// Five per tile at most: the most used first. The rest are one click
	// away in the category's own Style & specialty facet.
	usort( $links, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] ?: strcasecmp( $a['label'], $b['label'] ) );
	return array_slice( $links, 0, TILE_LINKS );
}

/**
 * The styles (service terms) and specialties with listings inside a
 * category, as clean facet links with counts — most used first. Only
 * those reaching FACET_MIN listings, which is also the floor a facet page
 * needs before it may be indexed, so every link here lands somewhere
 * worth landing. Where a style and a specialty share a name, the one
 * reaching more listings is kept.
 *
 * @return list<array{url:string,label:string,count:int,key:string,value:string}>
 */
function style_links( \WP_Term $practice, int $min = FACET_MIN ): array {
	$ids = function_exists( '\Oria\Core\Intents\listings_in' ) ? \Oria\Core\Intents\listings_in( $practice ) : array();
	if ( ! $ids ) {
		return array();
	}
	$counts = array(); // "key:slug" => [term, count, key]
	foreach ( $ids as $id ) {
		foreach ( array( 'svc' => 'service', 'spec' => Taxonomies\SPECIALTY ) as $key => $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$terms = wp_get_post_terms( (int) $id, $tax );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $t ) {
				$k = $key . ':' . $t->slug;
				$counts[ $k ] = array( 'term' => $t, 'count' => ( $counts[ $k ]['count'] ?? 0 ) + 1, 'key' => $key );
			}
		}
	}
	// One entry per name.
	$by_name = array();
	foreach ( $counts as $row ) {
		$name = strtolower( wp_specialchars_decode( $row['term']->name, ENT_QUOTES ) );
		if ( ! isset( $by_name[ $name ] ) || $row['count'] > $by_name[ $name ]['count'] ) {
			$by_name[ $name ] = $row;
		}
	}
	$out   = array();
	$pname = strtolower( wp_specialchars_decode( $practice->name, ENT_QUOTES ) );

	/*
	 * A style has to be characteristic of the category, not merely present
	 * in it. The absolute floor alone was not enough: three breathwork
	 * studios that also run a retreat put "Wellness retreats" on the
	 * Breathwork tile, and three day spas with a sauna put "Infrared sauna"
	 * on Beauty. Both are true of the data and neither is a sub-category of
	 * anything — they read as a miscategorisation to anyone scanning the
	 * page.
	 *
	 * So a style must also be carried by a share of the category before it
	 * is offered as a way in. The share scales with the category, which the
	 * flat minimum could not: three of ten listings says something, three
	 * of forty says almost nothing.
	 */
	$floor = max( $min, (int) ceil( STYLE_SHARE * count( $ids ) ) );

	foreach ( $by_name as $row ) {
		if ( $row['count'] < $floor ) {
			continue;
		}
		// A style that is just the category's own name says nothing new.
		if ( $row['term']->slug === $practice->slug || strtolower( wp_specialchars_decode( $row['term']->name, ENT_QUOTES ) ) === $pname ) {
			continue;
		}
		// The facet resolver owns the canonical slug (short forms, registry
		// pages); a term it cannot resolve gets no link rather than a 404.
		$slug  = 'svc' === $row['key'] ? preg_replace( '/-' . preg_quote( $practice->slug, '/' ) . '$/', '', $row['term']->slug ) : $row['term']->slug;
		$facet = resolve_facet( $practice, (string) $slug );
		if ( null === $facet || $facet['value'] !== $row['term']->slug ) {
			$facet = resolve_facet( $practice, $row['term']->slug );
		}
		if ( null === $facet || $facet['value'] !== $row['term']->slug ) {
			continue;
		}
		$out[] = array(
			'url'   => category_url( $practice ) . $facet['slug'] . '/',
			'label' => (string) $facet['label'],
			'count' => (int) $row['count'],
			'key'   => (string) $facet['key'],
			'value' => (string) $facet['value'],
		);
	}
	usort( $out, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] ?: strcasecmp( $a['label'], $b['label'] ) );
	return $out;
}

/* ------------------------------------------------------------------ seo */

function title( $title ) {
	if ( is_index() ) {
		return sprintf( 'Wellness practices in Perth: every category we list | %s', get_bloginfo( 'name' ) );
	}
	$f = facet();
	if ( null !== $f ) {
		$own = (string) ( $f['page']['frame']['title'] ?? '' );
		return '' !== $own ? $own : sprintf( '%s | %s', $f['label'], get_bloginfo( 'name' ) );
	}
	return $title;
}

function description( $desc ) {
	if ( is_index() ) {
		return 'Every wellness practice category Oria Haven lists in Perth — massage, yoga and Pilates, breathwork, meditation, recovery, naturopathy and more — with the pages inside each.';
	}
	$f = facet();
	if ( null !== $f ) {
		$own = (string) ( $f['page']['frame']['description'] ?? '' );
		if ( '' !== $own ) {
			return $own;
		}
		$term = get_queried_object();
		$n    = $term instanceof \WP_Term ? count( facet_ids( $term, $f ) ) : 0;
		return sprintf( '%s — %s checked by hand, with timetables, prices and contact details. Counted live from the Oria Haven directory.', $f['label'], $n ? sprintf( _n( '%d practice', '%d practices', $n, 'oria' ), $n ) : 'practices' );
	}
	return $desc;
}

function canonical( $canonical ) {
	if ( is_category() ) {
		$term = get_queried_object();
		if ( $term instanceof \WP_Term ) {
			$f = facet();
			if ( null !== $f ) {
				// A facet page is its own address whatever the mode; under
				// review it is noindexed anyway.
				return category_url( $term ) . $f['slug'] . '/';
			}
			// Under review the original page stays the canonical one; live,
			// the new page speaks for itself.
			return 'live' === mode() ? category_url( $term ) : original_url( $term );
		}
	}
	if ( is_tax( Taxonomies\PRACTICE ) && ! IntentPages\current() ) {
		// The original /practice/{slug}/ page: its own canonical until the
		// switch is flipped to live, when the new page becomes the one.
		$term = get_queried_object();
		if ( $term instanceof \WP_Term ) {
			return 'live' === mode() ? category_url( $term ) : original_url( $term );
		}
	}
	return is_index() ? url() : $canonical;
}

function core_title( array $parts ): array {
	if ( is_index() ) {
		$parts['title'] = 'Wellness practices in Perth';
	} elseif ( null !== facet() ) {
		$parts['title'] = facet()['label'];
	}
	return $parts;
}
