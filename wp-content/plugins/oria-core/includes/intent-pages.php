<?php
/**
 * Intent pages: /practice/{practice}/{intent}/
 *
 * A practice category with one more facet locked — an audience, a service,
 * a specialty, a format or a price band — framed by hand. The intent rows
 * on a category page already count these views and link to them as filter
 * URLs (?aud=…#dirResults); this gives the ones worth a page a canonical
 * address, a title, a frame and a place in the sitemap.
 *
 * What a page is made of: the live filtered set, rendered by the same
 * directory engine as every other landing page, with the intent locked
 * through `data-intent-key` / `data-intent-value` on #dirResults. Around
 * it, a hand-written frame from data/intents.json. It counts and links.
 * It never ranks, never numbers, never uses a rating to order anything —
 * see the header of intents.php for why.
 *
 * When it is allowed to be seen: a page is indexable, in the sitemap and
 * linked from the intent rows only when it is marked live in the registry,
 * its live count clears the floor, and — for audience intents — the
 * category has actually been checked (Audience\coverage). Short of that it
 * still renders, so it can be reviewed, but it answers noindex,follow and
 * nothing links to it. Built dark, lit by data.
 *
 * Routing: the existing combo rule already captures /practice/{p}/{x}/ and
 * hands {x} to the area query var. This module looks at {x} before the
 * combo code does; if it names an intent page for that practice it claims
 * the request and clears the area var, so Seo\validate_combo() sees nothing
 * to validate. An intent slug may therefore never equal an area slug, and
 * the registry loader drops any that does.
 */

declare(strict_types=1);

namespace Oria\Core\IntentPages;

use Oria\Core\Audience;
use Oria\Core\Intents;
use Oria\Core\PostTypes;
use Oria\Core\Seo;
use Oria\Core\Services;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR = 'oria_intent';
const DATA_FILE = 'data/intents.json';
const SITEMAP   = 'intent';

function bootstrap(): void {
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	// Priority 5: ahead of Seo\validate_combo at 10, which would otherwise
	// redirect an intent slug it cannot resolve as an area.
	add_action( 'template_redirect', __NAMESPACE__ . '\detect', 5 );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );

	// After Seo's own filters (10), which see a plain practice archive here.
	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 20 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description', 20 );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical', 20 );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\robots', 20 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 20 );

	// Intent rows link to the canonical page where one is live.
	add_filter( 'oria_intent_rows', __NAMESPACE__ . '\canonical_rows', 10, 2 );

	// Yoast sitemap entry, only ever listing pages that are allowed to be seen.
	add_action( 'template_redirect', __NAMESPACE__ . '\legacy_redirect', 6 );
	add_action( 'init', __NAMESPACE__ . '\register_sitemap', 20 );
	add_filter( 'wpseo_sitemap_index', __NAMESPACE__ . '\sitemap_index' );
}

/* ---------------------------------------------------------------- registry */

/**
 * The registry, validated. Entries whose intent slug collides with an area
 * slug are dropped and logged — a collision would hijack a suburb page.
 *
 * @return array{min: int, intents: array<string, array>, pages: list<array>}
 */
function registry(): array {
	static $reg = null;
	if ( null !== $reg ) {
		return $reg;
	}
	$reg  = array( 'min' => 3, 'intents' => array(), 'pages' => array() );
	$path = ORIA_CORE_DIR . DATA_FILE;
	if ( ! is_readable( $path ) ) {
		return $reg;
	}
	$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_array( $json ) ) {
		return $reg;
	}
	$reg['min'] = max( 1, (int) ( $json['min'] ?? 3 ) );

	foreach ( (array) ( $json['intents'] ?? array() ) as $slug => $def ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug || ! is_array( $def ) || empty( $def['filter'] ) ) {
			continue;
		}
		if ( get_term_by( 'slug', $slug, Taxonomies\AREA ) instanceof \WP_Term ) {
			error_log( sprintf( 'oria intents: "%s" collides with an area slug and was dropped', $slug ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			continue;
		}
		$reg['intents'][ $slug ] = array(
			'slug'   => $slug,
			'label'  => (string) ( $def['label'] ?? $slug ),
			'filter' => array_map( 'strval', (array) $def['filter'] ),
			'kind'   => (string) ( $def['kind'] ?? '' ),
			/*
			 * One line saying what happens in the room, shown on the category
			 * grid so the cards read as choices rather than as a list of tag
			 * names. Optional — a row without one renders as it always did.
			 */
			'note'   => (string) ( $def['note'] ?? '' ),
		);
	}

	foreach ( (array) ( $json['pages'] ?? array() ) as $page ) {
		if ( ! is_array( $page ) ) {
			continue;
		}
		$practice = sanitize_title( (string) ( $page['practice'] ?? '' ) );
		$intent   = sanitize_title( (string) ( $page['intent'] ?? '' ) );
		if ( '' === $practice || ! isset( $reg['intents'][ $intent ] ) ) {
			continue;
		}
		$reg['pages'][] = array(
			'practice' => $practice,
			'intent'   => $intent,
			'live'     => ! empty( $page['live'] ),
			'frame'    => (array) ( $page['frame'] ?? array() ),
		) + $reg['intents'][ $intent ];
	}
	return $reg;
}

/** One page definition, or null. */
function page( string $practice, string $intent ): ?array {
	foreach ( registry()['pages'] as $p ) {
		if ( $p['practice'] === $practice && $p['intent'] === $intent ) {
			return $p;
		}
	}
	return null;
}

/** @return list<array> pages defined for a practice, live or not */
function pages_for( string $practice ): array {
	return array_values( array_filter( registry()['pages'], static fn( array $p ): bool => $p['practice'] === $practice ) );
}

/**
 * One address per intent page, and it is the plural one.
 *
 * These pages have always answered at two URLs: /practice/{p}/{i}/ from the
 * route below, and /practices/{p}/{i}/ from the facet route, which resolves
 * an intent slug to this same registry page and renders identical HTML. Both
 * declared themselves canonical. Every internal link the site draws — the
 * category tiles, the style grid, the hub — uses the plural form, while this
 * function put the singular one in the sitemap, so the two strongest signals
 * disagreed on every intent page the directory has.
 *
 * The plural wins because it is what the site links to and what the live
 * directory uses everywhere else. legacy_redirect() keeps the old address
 * working with a 301.
 */
function url( string $practice, string $intent ): string {
	return home_url( '/' . \Oria\Core\PracticesIndex\PATH . '/' . $practice . '/' . $intent . '/' );
}

/* ------------------------------------------------------------------ routing */

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

/**
 * Claim /practice/{p}/{x}/ when {x} is an intent page for {p}: set our var,
 * clear the area var so the combo code stands down.
 */
function detect(): void {
	if ( ! is_tax( Taxonomies\PRACTICE ) ) {
		return;
	}
	$slug = sanitize_title( (string) get_query_var( Seo\QUERY_VAR ) );
	if ( '' === $slug ) {
		return;
	}
	$practice = get_queried_object();
	if ( ! $practice instanceof \WP_Term || null === page( $practice->slug, $slug ) ) {
		return;
	}
	set_query_var( QUERY_VAR, $slug );
	set_query_var( Seo\QUERY_VAR, '' );
}

/** The page being viewed, with its practice term attached, or null. */
function current(): ?array {
	static $cur = false;
	if ( false !== $cur ) {
		return $cur;
	}
	$cur = null;
	if ( ! is_tax( Taxonomies\PRACTICE ) ) {
		return $cur;
	}
	$slug = (string) get_query_var( QUERY_VAR );
	if ( '' === $slug ) {
		return $cur;
	}
	$practice = get_queried_object();
	if ( ! $practice instanceof \WP_Term ) {
		return $cur;
	}
	$page = page( $practice->slug, $slug );
	if ( null === $page ) {
		return $cur;
	}
	$page['term'] = $practice;
	return $cur = $page;
}

function template( string $template ): string {
	if ( null === current() ) {
		return $template;
	}
	$found = locate_template( array( 'oria-intent.php' ) );
	return $found ? $found : $template;
}

/* ------------------------------------------------------------------- counts */

/**
 * Listings in the practice that match the intent's filter. Each key is the
 * directory engine's own, so the server-side set and the client-side view
 * are the same listings.
 *
 * @return list<int>
 */
function matching_ids( \WP_Term $practice, array $filter ): array {
	$ids = function_exists( '\Oria\Core\Intents\listings_in' ) ? Intents\listings_in( $practice ) : array();
	foreach ( $filter as $key => $value ) {
		$value = (string) $value;
		$ids   = array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $key, $value ): bool {
					$id = (int) $id;
					switch ( $key ) {
						case 'aud':
							return has_term( $value, Audience\TAXONOMY, $id );
						case 'svc':
							return has_term( $value, Services\TAXONOMY, $id );
						case 'spec':
							return has_term( $value, Taxonomies\SPECIALTY, $id );
						case 'format':
							$f = (string) get_field( 'format', $id );
							return $f === $value || 'both' === $f;
						case 'price':
							return 0 === strcasecmp( trim( (string) get_field( 'price_band', $id ) ), $value );
						case 'area':
							// A region matches through its suburbs: listings
							// carry the child term, and a page for "north"
							// that missed everything tagged to a suburb in
							// the north would be an empty lie.
							$t = get_term_by( 'slug', $value, Taxonomies\AREA );
							if ( ! $t instanceof \WP_Term ) {
								return false;
							}
							$slugs = array( $t->slug );
							foreach ( (array) get_term_children( $t->term_id, Taxonomies\AREA ) as $cid ) {
								$c = get_term( (int) $cid, Taxonomies\AREA );
								if ( $c instanceof \WP_Term ) {
									$slugs[] = $c->slug;
								}
							}
							return has_term( $slugs, Taxonomies\AREA, $id );
					}
					return false;
				}
			)
		);
	}
	return $ids;
}

/** Live facts for a page: matched ids, category total, and whether it may be seen. */
function facts( array $page ): array {
	$term  = $page['term'] ?? get_term_by( 'slug', $page['practice'], Taxonomies\PRACTICE );
	if ( ! $term instanceof \WP_Term ) {
		return array( 'ids' => array(), 'count' => 0, 'total' => 0, 'publishable' => false );
	}
	$all   = function_exists( '\Oria\Core\Intents\listings_in' ) ? Intents\listings_in( $term ) : array();
	$ids   = matching_ids( $term, $page['filter'] );
	$count = count( $ids );
	$ok      = ( $page['live'] || previewing( $page ) ) && $count >= registry()['min'];
	$checked = '';
	if ( 'audience' === $page['kind'] && function_exists( '\Oria\Core\Audience\coverage' ) ) {
		$cov     = Audience\coverage( $all, (string) ( $page['filter']['aud'] ?? '' ) );
		$checked = (string) $cov['checked'];
		if ( $ok ) {
			$ok = ! empty( $cov['publishable'] );
		}
	}
	return array(
		'ids'              => $ids,
		'count'            => $count,
		'total'            => count( $all ),
		'publishable'      => $ok,
		// How many of the category were actually read, for {checked}.
		'audience_checked' => $checked,
	);
}

/**
 * "Who it suits", answered by counting rather than by asserting.
 *
 * The question every style page invites — who is this for? — is one the
 * directory cannot answer about a reader, and should not try to. What it
 * can say is how many of the businesses on this page have published
 * something themselves: a beginners' class, a seniors' programme, a
 * prenatal course. That is a count with evidence behind it rather than a
 * claim about anybody's body.
 *
 * Returns the best-covered audience for this set, or null when nobody has
 * checked. The page's own audience is skipped — telling a reader that all
 * 25 listings on the beginners page suit beginners says nothing.
 *
 * @param array $page  The page definition.
 * @param array $facts The facts() result for it.
 * @return array{slug: string, name: string, yes: int, of: int}|null
 */
function audience_note( array $page, array $facts ): ?array {
	if ( ! function_exists( '\Oria\Core\Audience\vocabulary' ) ) {
		return null;
	}
	$ids = (array) ( $facts['ids'] ?? array() );
	if ( count( $ids ) < 2 ) {
		return null;
	}

	$own  = (string) ( $page['filter']['aud'] ?? '' );
	$best = null;

	foreach ( Audience\vocabulary() as $slug => $row ) {
		if ( $slug === $own ) {
			continue;
		}
		$yes = 0;
		foreach ( $ids as $id ) {
			if ( has_term( $slug, Audience\TAXONOMY, (int) $id ) ) {
				$yes++;
			}
		}
		// Two is a pair, not a pattern; and a bare majority of a small set
		// is not worth a sentence either.
		if ( $yes >= 3 && ( null === $best || $yes > $best['yes'] ) ) {
			$best = array(
				'slug' => $slug,
				'name' => (string) $row['name'],
				'yes'  => $yes,
				'of'   => count( $ids ),
			);
		}
	}

	return $best;
}

/**
 * A local preview switch: the option `oria_intent_preview` set to 'all', or
 * to a list of "practice/intent" keys, treats those pages as live on this
 * installation only. It is a database option, never a file, so it cannot
 * travel to production in a deploy — and it should never be set there,
 * because a previewed page is indexable. Data gates still apply.
 */
function previewing( array $page ): bool {
	$opt = get_option( 'oria_intent_preview', '' );
	if ( 'all' === $opt ) {
		return true;
	}
	return is_array( $opt ) && in_array( $page['practice'] . '/' . $page['intent'], array_map( 'strval', $opt ), true );
}

/** Fill {count} {total} {practice} in frame text. */
function fill( string $text, array $facts, \WP_Term $practice ): string {
	/*
	 * {checked} is the number that makes the other two mean anything.
	 *
	 * "25 of the 42 yoga listings publish a beginners' class" reads, to a
	 * sceptical person, as though we found 25 and never looked at the rest.
	 * The truth is that all 42 were read and 17 do not publish it — a survey
	 * rather than a sample. Only an audience page can say this, because only
	 * an audience answer records that somebody checked and found nothing;
	 * elsewhere it resolves to the category total, which is the honest
	 * fallback rather than a claim.
	 */
	$checked = (int) $facts['total'];
	$aud     = (string) ( $facts['audience_checked'] ?? '' );
	if ( '' !== $aud ) {
		$checked = (int) $aud;
	}

	return strtr(
		$text,
		array(
			'{count}'    => number_format_i18n( (int) $facts['count'] ),
			'{total}'    => number_format_i18n( (int) $facts['total'] ),
			'{checked}'  => number_format_i18n( $checked ),
			'{practice}' => strtolower( wp_specialchars_decode( $practice->name, ENT_QUOTES ) ),
		)
	);
}

/** Live, publishable pages for a practice — the ones allowed into the mesh. */
function visible_for( string $practice ): array {
	return array_values( array_filter( pages_for( $practice ), static fn( array $p ): bool => facts( $p )['publishable'] ) );
}

/* ------------------------------------------------------------------ seo */

function title( $title ) {
	$p = current();
	if ( null === $p ) {
		return $title;
	}
	$own = (string) ( $p['frame']['title'] ?? '' );
	return '' !== $own ? $own : sprintf( '%s | %s', (string) ( $p['frame']['h1'] ?? $p['label'] ), get_bloginfo( 'name' ) );
}

function description( $desc ) {
	$p = current();
	if ( null === $p ) {
		return $desc;
	}
	$own = (string) ( $p['frame']['description'] ?? '' );
	return '' !== $own ? $own : $desc;
}

function canonical( $canonical ) {
	$p = current();
	return null === $p ? $canonical : url( $p['practice'], $p['intent'] );
}

function robots( $robots ) {
	$p = current();
	if ( null === $p ) {
		return $robots;
	}
	return facts( $p )['publishable'] ? $robots : 'noindex, follow';
}

function core_title( array $parts ): array {
	$p = current();
	if ( null !== $p ) {
		$parts['title'] = (string) ( $p['frame']['h1'] ?? $p['label'] );
	}
	return $parts;
}

/* ------------------------------------------------------- intent rows */

/**
 * Where a row's filter has a live page, send the row there instead of the
 * ?key=value filter view. Dark pages keep the filter URL — nothing links
 * to a page that is not yet allowed to be seen.
 *
 * @param list<array{label: string, count: int, url: string, kind: string}> $rows
 */
function canonical_rows( array $rows, \WP_Term $practice ): array {
	$pages = visible_for( $practice->slug );
	if ( ! $pages ) {
		return $rows;
	}
	foreach ( $rows as &$row ) {
		parse_str( (string) wp_parse_url( (string) $row['url'], PHP_URL_QUERY ), $q );
		foreach ( $pages as $page ) {
			$match = true;
			foreach ( $page['filter'] as $k => $v ) {
				if ( ! isset( $q[ $k ] ) || (string) $q[ $k ] !== (string) $v ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				$row['url'] = url( $page['practice'], $page['intent'] );
				if ( '' !== (string) ( $page['note'] ?? '' ) ) {
					$row['note'] = (string) $page['note'];
				}
				break;
			}
		}
	}
	unset( $row );
	return $rows;
}

/**
 * Send the retired singular address to the one the sitemap now carries.
 *
 * Only fires where the slug is genuinely an intent page. /practice/{p}/{area}/
 * is the practice-by-suburb combo — a different page, indexed, taking clicks,
 * and staying exactly where it is. current() is null there, so those never
 * reach this.
 */
function legacy_redirect(): void {
	// After detect() at priority 5, never before: detect() is what sets the
	// query var current() reads, and current() caches its answer statically.
	// Asking too early does not just miss the redirect, it poisons that cache
	// with null and takes the intent page down with it.
	$page = current();
	if ( null === $page || is_admin() ) {
		return;
	}
	$target = url( (string) $page['practice'], (string) $page['intent'] );
	$here   = home_url( add_query_arg( array() ) );
	// Never redirect a URL to itself: the facet route serves the target, and
	// if it ever also set our query var this would be an infinite loop.
	if ( untrailingslashit( (string) strtok( $here, '?' ) ) === untrailingslashit( (string) strtok( $target, '?' ) ) ) {
		return;
	}
	wp_safe_redirect( $target, 301 );
	exit;
}

/* ---------------------------------------------------------------- sitemap */

/** @return list<array{loc: string}> */
function sitemap_entries(): array {
	$out = array();
	// publishable already folds in live-or-previewing, the count floor and
	// the audience gate — the same decision robots() makes, so the sitemap
	// and the meta tag can never disagree.
	foreach ( registry()['pages'] as $p ) {
		if ( facts( $p )['publishable'] ) {
			$out[] = array( 'loc' => url( $p['practice'], $p['intent'] ) );
		}
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
