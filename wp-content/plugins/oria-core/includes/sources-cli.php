<?php
/**
 * `wp oria sources` -- collect activity listings from public organiser pages,
 * check every fact against the page it came from, and import them as drafts.
 *
 *   wp oria sources setup     [--dry-run]
 *   wp oria sources fetch     --batch=<id> [--category=<key>] [--limit=<n>] [--dry-run]
 *   wp oria sources verify    --batch=<id> [--category=<key>]
 *   wp oria sources import    --batch=<id> [--category=<key>] [--limit=<n>] [--dry-run]
 *   wp oria sources report    --batch=<id>
 *   wp oria sources rollback  --batch=<id> [--dry-run]
 *   wp oria sources launch    <slug> [--dry-run] [--force]
 *   wp oria sources selftest
 *
 * A batch lives in oria-core/sources/<batch>/:
 *   manifest.json    every domain considered, its type and the access decision
 *   candidates.json  one record per organisation, with field-level evidence
 *   fetch-log.json   written by fetch: what was requested, when, and what came back
 *   report.md        written by report
 * The page cache and nothing else goes to uploads/oria-sources/cache/.
 * The journal that makes reruns and rollback safe lives in the database,
 * option oria_src_journal_<batch>, because it describes this site's rows.
 *
 * HOW A FACT GETS IN. Fetch saves each permitted page as text. A candidate
 * record names, for every material field, the page and the words on it that
 * support the value. Verify checks each excerpt really is on that cached
 * page -- so a value typed from memory, or from a page that has since
 * changed, fails. Import refuses anything that has not verified. Missing
 * facts stay missing: unknown is a value here, never a gap to fill.
 *
 * FETCHING IS DELIBERATELY SMALL. Only domains the manifest allowlists;
 * robots.txt obeyed per host; two seconds between requests to one host (or
 * the site's Crawl-delay if longer); ten pages per domain; 20s timeout;
 * 1.5MB cap; redirects followed by hand, three at most, each hop re-checked
 * against the allowlist and against private, loopback and metadata
 * addresses. Retry-After is honoured up to a minute; a second refusal stops
 * that domain for the run and lands in the exceptions list. Nothing behind
 * a login, a CAPTCHA or a paywall is attempted. Page text is data: it is
 * never executed and nothing written on a page is treated as an instruction.
 *
 * @package OriaCore
 */

declare(strict_types=1);

namespace Oria\Core\Sources;

use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UA            = 'OriaHavenBot/1.0 (+https://oriahaven.com.au/about/; listings research)';
const DELAY         = 2;       // seconds between requests to one host
const MAX_PER_HOST  = 10;
const MAX_BYTES     = 1572864; // 1.5 MB
const TIMEOUT       = 20;
const MAX_REDIRECTS = 3;
const JOURNAL       = 'oria_src_journal_';

/**
 * Hosts shared by many organisers. A match on one of these says nothing
 * about identity unless the path matches too.
 */
const SHARED_HOSTS = array(
	'meetup.com', 'facebook.com', 'instagram.com', 'linktr.ee', 'eventbrite.com.au', 'eventbrite.com',
	'humanitix.com', 'trybooking.com', 'parkrun.com.au', 'google.com', 'wix.com', 'squarespace.com',
	'bookwhen.com', 'mindbodyonline.com', 'playbypoint.com', 'courtreserve.com', 'revolutionise.com.au',
	'teamapp.com', 'sportstg.com', 'heartfoundationwalking.org.au',
);

class Command {

	/* ================================================================ setup */

	/**
	 * Create the four new sub-categories and their tags. Safe to run twice.
	 *
	 * New terms are created pending (noindex, no sitemap, no navigation).
	 * Existing terms are never renamed, reparented or marked -- the walking
	 * groups service in particular is only checked, never touched.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Say what would be created and create nothing.
	 */
	public function setup( array $args, array $assoc ): void {
		$dry = isset( $assoc['dry-run'] );
		$n   = array( 'created' => 0, 'existing' => 0, 'tags' => 0 );

		foreach ( plan() as $row ) {
			$tax  = (string) $row['taxonomy'];
			$term = term_for( $row );

			if ( 'service' === $tax ) {
				if ( ! $term ) {
					\WP_CLI::warning( sprintf( '%s: expected the existing %s term "%s" and it is not here. Not creating one.', $row['name'], $tax, $row['slug'] ) );
				} else {
					\WP_CLI::log( sprintf( '  reuse  %-20s %s #%d (%d published) -- untouched', $row['slug'], $tax, $term->term_id, (int) $term->count ) );
					$n['existing']++;
				}
				continue;
			}

			if ( $term ) {
				\WP_CLI::log( sprintf( '  exists %-20s %s #%d%s', $row['slug'], $tax, $term->term_id, is_pending( $term ) ? ' (pending launch)' : '' ) );
				$n['existing']++;
				/*
				 * The page's meta description. seo.php prefers the term's own
				 * description over its generic "verified practices" line, which
				 * says the wrong thing about a running club. Only ever filled
				 * when empty, so an editor's wording is never overwritten.
				 */
				if ( '' === trim( (string) $term->description ) && '' !== (string) ( $row['description'] ?? '' ) ) {
					if ( ! $dry ) {
						wp_update_term( (int) $term->term_id, $tax, array( 'description' => (string) $row['description'] ) );
					}
					\WP_CLI::log( sprintf( '         %s description', $dry ? 'would set' : 'set' ) );
				}
			} else {
				$parent = get_term_by( 'slug', (string) $row['parent'], $tax );
				if ( ! $parent instanceof \WP_Term ) {
					\WP_CLI::warning( sprintf( '%s: no parent "%s" -- skipped rather than created at the root.', $row['slug'], $row['parent'] ) );
					continue;
				}
				if ( $dry ) {
					\WP_CLI::log( sprintf( '  would create %-14s under %s, pending launch', $row['slug'], $parent->slug ) );
				} else {
					$made = wp_insert_term( (string) $row['name'], $tax, array( 'slug' => (string) $row['slug'], 'parent' => (int) $parent->term_id, 'description' => (string) ( $row['description'] ?? '' ) ) );
					if ( is_wp_error( $made ) ) {
						\WP_CLI::warning( $row['slug'] . ': ' . $made->get_error_message() );
						continue;
					}
					$id = (int) $made['term_id'];
					update_term_meta( $id, PENDING, '1' );
					update_term_meta( $id, 'landing_intro', (string) $row['intro'] );
					\WP_CLI::log( sprintf( '  created %-19s #%d under %s, pending launch', $row['slug'], $id, $parent->slug ) );
				}
				$n['created']++;
			}

			foreach ( (array) ( $row['tags'] ?? array() ) as $tag ) {
				if ( get_term_by( 'slug', (string) $tag['slug'], Taxonomies\SPECIALTY ) ) {
					continue;
				}
				if ( ! $dry ) {
					$made = wp_insert_term( (string) $tag['name'], Taxonomies\SPECIALTY, array( 'slug' => (string) $tag['slug'] ) );
					if ( is_wp_error( $made ) ) {
						\WP_CLI::warning( $tag['slug'] . ': ' . $made->get_error_message() );
						continue;
					}
				}
				\WP_CLI::log( sprintf( '  %s tag %s', $dry ? 'would create' : 'created', $tag['slug'] ) );
				$n['tags']++;
			}
		}

		if ( ! $dry && function_exists( '\Oria\Core\Categories\flush' ) ) {
			\Oria\Core\Categories\flush();
		}
		\WP_CLI::success( sprintf( '%s%d categories created, %d already present, %d tags created.', $dry ? '[dry-run] ' : '', $n['created'], $n['existing'], $n['tags'] ) );
	}

	/**
	 * Lift the pending flag once a category's listings have been reviewed.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The practice term.
	 *
	 * [--dry-run]
	 * : Report only.
	 *
	 * [--force]
	 * : Launch even with no published listings.
	 */
	public function launch( array $args, array $assoc ): void {
		$term = get_term_by( 'slug', (string) ( $args[0] ?? '' ), Taxonomies\PRACTICE );
		if ( ! $term instanceof \WP_Term ) {
			\WP_CLI::error( 'No such category.' );
		}
		if ( ! is_pending( $term ) ) {
			\WP_CLI::success( $term->slug . ' is not pending -- nothing to do.' );
			return;
		}
		$live = (int) ( new \WP_Query( array( 'post_type' => PostTypes\LISTING, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'tax_query' => array( array( 'taxonomy' => Taxonomies\PRACTICE, 'field' => 'term_id', 'terms' => $term->term_id ) ) ) ) )->found_posts; // phpcs:ignore WordPress.DB.SlowDBQuery
		\WP_CLI::log( sprintf( '%s has %d published listing(s).', $term->slug, $live ) );
		if ( 0 === $live && ! isset( $assoc['force'] ) ) {
			\WP_CLI::error( 'Nothing published yet; launching would index an empty page. Pass --force if that is really intended.' );
		}
		if ( isset( $assoc['dry-run'] ) ) {
			\WP_CLI::success( '[dry-run] would launch ' . $term->slug );
			return;
		}
		delete_term_meta( $term->term_id, PENDING );
		if ( function_exists( '\Oria\Core\Categories\flush' ) ) {
			\Oria\Core\Categories\flush();
		}
		\WP_CLI::success( $term->slug . ' launched: indexable, in the sitemap and in the navigation once it clears the category minimum.' );
	}

	/* ================================================================ fetch */

	/**
	 * Fetch the manifest's permitted pages into the cache.
	 *
	 * ## OPTIONS
	 *
	 * --batch=<id>
	 * : Batch directory under oria-core/sources/.
	 *
	 * [--category=<key>]
	 * : running, ocean, pickleball, creative or walking.
	 *
	 * [--limit=<n>]
	 * : At most this many sources.
	 *
	 * [--refresh]
	 * : Re-fetch pages already in the cache.
	 *
	 * [--dry-run]
	 * : List what would be requested; request nothing.
	 */
	public function fetch( array $args, array $assoc ): void {
		$batch    = self::batch( $assoc );
		$manifest = self::read( $batch, 'manifest.json' );
		$cat      = (string) ( $assoc['category'] ?? '' );
		$limit    = (int) ( $assoc['limit'] ?? 0 );
		$dry      = isset( $assoc['dry-run'] );
		$refresh  = isset( $assoc['refresh'] );

		$allow = self::allowlist( $manifest );
		$log   = self::read( $batch, 'fetch-log.json', true ) ?: array( 'batch' => $batch, 'requests' => array() );
		$done  = 0;
		$hosts = array();

		// A host that refused us on an earlier run stays refused: asking again
		// every run is exactly the persistence a 403 is asking us not to have.
		$refused = array();
		foreach ( (array) $log['requests'] as $r ) {
			if ( in_array( $r['state'] ?? '', array( 'stopped', 'blocked' ), true ) ) {
				$refused[ self::host( (string) $r['url'] ) ] = (string) ( $r['note'] ?? '' );
			}
		}

		foreach ( (array) ( $manifest['sources'] ?? array() ) as $src ) {
			if ( '' !== $cat && $cat !== ( $src['category'] ?? '' ) ) {
				continue;
			}
			if ( 'fetch' !== ( $src['decision'] ?? '' ) ) {
				continue;
			}
			if ( $limit && $done >= $limit ) {
				break;
			}
			$done++;

			foreach ( (array) ( $src['urls'] ?? array() ) as $url ) {
				$url  = (string) $url;
				$host = self::host( $url );
				if ( ! self::allowed( $url, $allow ) ) {
					$log['requests'][] = self::entry( $url, 'refused', 'not on the manifest allowlist' );
					\WP_CLI::warning( "not allowlisted: {$url}" );
					continue;
				}
				if ( isset( $refused[ $host ] ) && ! $refresh ) {
					\WP_CLI::log( "  skip     {$url}  -- refused on an earlier run ({$refused[ $host ]}); --refresh to ask again" );
					continue;
				}
				if ( ( $hosts[ $host ] ?? 0 ) >= MAX_PER_HOST ) {
					$log['requests'][] = self::entry( $url, 'skipped', 'ten-page limit for this domain reached' );
					continue;
				}
				if ( ! $refresh && is_array( self::cached( $url ) ) ) {
					\WP_CLI::log( "  cached  {$url}" );
					continue;
				}
				if ( $dry ) {
					\WP_CLI::log( "  would fetch {$url}" );
					continue;
				}
				$hosts[ $host ] = ( $hosts[ $host ] ?? 0 ) + 1;
				$result         = self::get( $url, $allow );
				$log['requests'][] = self::entry( $url, $result['state'], $result['note'], $result['status'] ?? 0, $result['final'] ?? '' );
				\WP_CLI::log( sprintf( '  %-8s %s%s', $result['state'], $url, '' !== $result['note'] ? '  -- ' . $result['note'] : '' ) );
				if ( 'stopped' === $result['state'] ) {
					$hosts[ $host ] = MAX_PER_HOST; // Persistent refusal: leave this domain alone for the run.
				}
			}
		}

		if ( ! $dry ) {
			self::write( $batch, 'fetch-log.json', $log );
		}
		\WP_CLI::success( sprintf( '%s%d source(s) processed.', $dry ? '[dry-run] ' : '', $done ) );
	}

	/** @return array{state: string, note: string, status?: int, final?: string} */
	private static function get( string $url, array $allow ): array {
		static $last = array();

		$robots = self::robots_for( $url, $allow );
		if ( 'blocked' === $robots['state'] ) {
			return array( 'state' => 'blocked', 'note' => $robots['note'] );
		}
		$delay = max( DELAY, (int) $robots['delay'] );

		$current = $url;
		for ( $hop = 0; $hop <= MAX_REDIRECTS; $hop++ ) {
			$host = self::host( $current );
			if ( ! self::allowed( $current, $allow ) ) {
				return array( 'state' => 'refused', 'note' => 'redirected off the allowlist to ' . $host );
			}
			if ( ! self::public_host( $host ) ) {
				return array( 'state' => 'refused', 'note' => 'resolves to a private or reserved address' );
			}
			if ( $hop > 0 && ! self::robots_allows( $current, $allow ) ) {
				return array( 'state' => 'blocked', 'note' => 'robots.txt disallows the redirect target' );
			}

			$wait = (float) ( $last[ $host ] ?? 0 ) + $delay - microtime( true );
			if ( $wait > 0 ) {
				usleep( (int) ( $wait * 1e6 ) );
			}

			$attempt = 0;
			do {
				$attempt++;
				$res            = wp_safe_remote_get( $current, array(
					'timeout'             => TIMEOUT,
					'redirection'         => 0,
					'limit_response_size' => MAX_BYTES,
					'user-agent'          => UA,
					'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5' ),
				) );
				$last[ $host ] = microtime( true );

				if ( is_wp_error( $res ) ) {
					if ( $attempt < 2 ) {
						sleep( $delay * 2 );
						continue;
					}
					return array( 'state' => 'error', 'note' => $res->get_error_message() );
				}
				$code = (int) wp_remote_retrieve_response_code( $res );
				if ( 429 === $code || 503 === $code ) {
					$after = (int) wp_remote_retrieve_header( $res, 'retry-after' );
					if ( $attempt < 2 && $after > 0 && $after <= 60 ) {
						sleep( $after );
						continue;
					}
					return array( 'state' => 'stopped', 'note' => "HTTP {$code} twice -- stopping this domain", 'status' => $code );
				}
				break;
			} while ( true );

			if ( $code >= 300 && $code < 400 ) {
				$loc = (string) wp_remote_retrieve_header( $res, 'location' );
				if ( '' === $loc ) {
					return array( 'state' => 'error', 'note' => "HTTP {$code} without a location", 'status' => $code );
				}
				$current = \WP_Http::make_absolute_url( $loc, $current );
				continue;
			}
			if ( 401 === $code || 403 === $code ) {
				return array( 'state' => 'stopped', 'note' => "HTTP {$code} -- access refused, not retried", 'status' => $code );
			}
			if ( $code < 200 || $code >= 300 ) {
				return array( 'state' => 'error', 'note' => "HTTP {$code}", 'status' => $code );
			}

			$type = strtolower( (string) wp_remote_retrieve_header( $res, 'content-type' ) );
			if ( '' !== $type && false === strpos( $type, 'html' ) && false === strpos( $type, 'json' ) ) {
				return array( 'state' => 'skipped', 'note' => 'not a page: ' . $type, 'status' => $code );
			}

			$html = (string) wp_remote_retrieve_body( $res );
			$page = self::extract( $html, $current );
			$page['url']        = $url;
			$page['final_url']  = $current;
			$page['status']     = $code;
			$page['fetched_at'] = gmdate( 'c' );
			self::store( $url, $page );
			return array( 'state' => 'fetched', 'note' => strlen( $page['text'] ) . ' chars of text', 'status' => $code, 'final' => $current );
		}
		return array( 'state' => 'error', 'note' => 'too many redirects' );
	}

	/**
	 * Reduce a page to what research needs, as inert data.
	 *
	 * @return array<string, mixed>
	 */
	private static function extract( string $html, string $base ): array {
		$title = preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ? self::clean( $m[1] ) : '';
		$desc  = preg_match( '#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)#i', $html, $m ) ? self::clean( $m[1] ) : '';

		$ld = array();
		if ( preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $mm ) ) {
			foreach ( $mm[1] as $block ) {
				$data = json_decode( trim( $block ), true );
				if ( is_array( $data ) ) {
					$ld[] = $data;
				}
			}
		}

		$links = array();
		if ( preg_match_all( '#<a\s[^>]*href=["\']([^"\'\#]+)["\'][^>]*>(.*?)</a>#is', $html, $mm, PREG_SET_ORDER ) ) {
			foreach ( $mm as $a ) {
				$href = \WP_Http::make_absolute_url( html_entity_decode( $a[1] ), $base );
				$text = self::clean( $a[2] );
				if ( preg_match( '#book|join|register|sign ?up|schedule|timetable|class|session|workshop|price|membership|contact|about|swim|run|walk|pickleball|pottery|ceramic|come and try|faq#i', $text . ' ' . $href ) ) {
					$links[ $href ] = substr( $text, 0, 80 );
				}
			}
		}

		$body = (string) preg_replace( '#<(script|style|noscript|svg|template)\b[^>]*>.*?</\1>#is', ' ', $html );
		$body = (string) preg_replace( '#<(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>#i', "\n", $body );
		$text = self::clean( wp_strip_all_tags( $body, false ) );

		return array(
			'title'  => $title,
			'desc'   => $desc,
			'jsonld' => $ld,
			'links'  => array_slice( $links, 0, 60, true ),
			'text'   => substr( $text, 0, 200000 ),
		);
	}

	private static function clean( string $s ): string {
		$s = html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = str_replace( array( "\xc2\xa0", "\xe2\x80\x8b" ), ' ', $s );
		return trim( (string) preg_replace( '#[ \t\r\f\v]+#', ' ', (string) preg_replace( '#\s*\n\s*#', "\n", $s ) ) );
	}

	/* ---------------------------------------------------------- robots.txt */

	/** @return array{state: string, note: string, delay: int} */
	private static function robots_for( string $url, array $allow ): array {
		return self::robots_allows( $url, $allow, $delay, $note )
			? array( 'state' => 'ok', 'note' => '', 'delay' => (int) $delay )
			: array( 'state' => 'blocked', 'note' => $note ?: 'robots.txt disallows this path', 'delay' => 0 );
	}

	private static function robots_allows( string $url, array $allow, &$delay = 0, &$note = '' ): bool {
		static $rules = array();

		$parts = wp_parse_url( $url );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path  = (string) ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );

		if ( ! isset( $rules[ $host ] ) ) {
			$rules[ $host ] = array( 'rules' => array(), 'delay' => 0, 'unreachable' => false );
			$res            = wp_safe_remote_get( ( $parts['scheme'] ?? 'https' ) . '://' . $host . '/robots.txt', array( 'timeout' => TIMEOUT, 'user-agent' => UA, 'limit_response_size' => 262144 ) );
			$code           = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			if ( 0 === $code || $code >= 500 ) {
				// Unreachable robots.txt: Google treats this as "do not crawl", and so do we.
				$rules[ $host ]['unreachable'] = true;
			} elseif ( $code >= 200 && $code < 300 ) {
				$rules[ $host ] = self::parse_robots( (string) wp_remote_retrieve_body( $res ) ) + array( 'unreachable' => false );
			}
			// 4xx: no robots.txt, everything allowed.
		}

		$r     = $rules[ $host ];
		$delay = (int) $r['delay'];
		if ( $r['unreachable'] ) {
			$note = 'robots.txt could not be read -- treated as disallowed';
			return false;
		}

		$best = array( 'len' => -1, 'allow' => true );
		foreach ( $r['rules'] as $rule ) {
			$pattern = '#^' . str_replace( array( '\*', '\$' ), array( '.*', '$' ), preg_quote( $rule['path'], '#' ) ) . '#';
			if ( '' !== $rule['path'] && preg_match( $pattern, $path ) ) {
				$len = strlen( $rule['path'] );
				if ( $len > $best['len'] || ( $len === $best['len'] && $rule['allow'] ) ) {
					$best = array( 'len' => $len, 'allow' => $rule['allow'] );
				}
			}
		}
		return $best['allow'];
	}

	/** @return array{rules: array<int, array{path: string, allow: bool}>, delay: int} */
	private static function parse_robots( string $body ): array {
		$groups  = array();
		$agents  = array();
		$current = null;
		foreach ( preg_split( '#\r?\n#', $body ) as $line ) {
			$line = trim( (string) preg_replace( '#\#.*$#', '', $line ) );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $k, $v ) = array_map( 'trim', explode( ':', $line, 2 ) );
			$k             = strtolower( $k );
			if ( 'user-agent' === $k ) {
				if ( null !== $current && ! empty( $groups[ $current ]['rules'] ) ) {
					$agents = array();
				}
				$agents[] = strtolower( $v );
				$current  = implode( '|', $agents );
				$groups[ $current ] = $groups[ $current ] ?? array( 'agents' => $agents, 'rules' => array(), 'delay' => 0 );
				$groups[ $current ]['agents'] = $agents;
			} elseif ( null !== $current && ( 'disallow' === $k || 'allow' === $k ) ) {
				$groups[ $current ]['rules'][] = array( 'path' => $v, 'allow' => 'allow' === $k || '' === $v );
			} elseif ( null !== $current && 'crawl-delay' === $k ) {
				$groups[ $current ]['delay'] = (int) ceil( (float) $v );
			}
		}
		$mine = null;
		$star = null;
		foreach ( $groups as $g ) {
			foreach ( $g['agents'] as $a ) {
				if ( '' !== $a && false !== stripos( 'oriahavenbot', $a ) ) {
					$mine = $g;
				} elseif ( '*' === $a ) {
					$star = $g;
				}
			}
		}
		$g = $mine ?? $star ?? array( 'rules' => array(), 'delay' => 0 );
		return array( 'rules' => $g['rules'], 'delay' => min( 30, (int) $g['delay'] ) );
	}

	/* ------------------------------------------------------ address safety */

	private static function public_host( string $host ): bool {
		if ( '' === $host || 'localhost' === $host || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.internal' ) ) {
			return false;
		}
		$ips = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : (array) gethostbynamel( $host );
		if ( ! $ips ) {
			return false;
		}
		foreach ( $ips as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) || str_starts_with( $ip, '169.254.' ) || str_starts_with( $ip, '100.64.' ) ) {
				return false;
			}
		}
		return true;
	}

	/** @return array<int, string> registrable-ish domains the manifest permits */
	private static function allowlist( array $manifest ): array {
		$out = array();
		foreach ( (array) ( $manifest['sources'] ?? array() ) as $src ) {
			if ( 'fetch' === ( $src['decision'] ?? '' ) && ! empty( $src['domain'] ) ) {
				$out[] = strtolower( preg_replace( '#^www\.#', '', (string) $src['domain'] ) );
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function allowed( string $url, array $allow ): bool {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( 'https' !== $scheme && 'http' !== $scheme ) {
			return false;
		}
		$host = self::host( $url );
		foreach ( $allow as $d ) {
			if ( $host === $d || str_ends_with( $host, '.' . $d ) ) {
				return true;
			}
		}
		return false;
	}

	private static function host( string $url ): string {
		return (string) preg_replace( '#^www\.#', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
	}

	/* ---------------------------------------------------------------- cache */

	private static function cache_dir(): string {
		$up  = wp_upload_dir( null, false );
		$dir = trailingslashit( $up['basedir'] ) . 'oria-sources/cache/';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . 'index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( dirname( $dir ) . '/.htaccess', "Require all denied\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dir;
	}

	private static function cache_file( string $url ): string {
		return self::cache_dir() . sha1( $url ) . '.json';
	}

	private static function store( string $url, array $page ): void {
		file_put_contents( self::cache_file( $url ), (string) wp_json_encode( $page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/** @return array<string, mixed>|null */
	public static function cached( string $url ): ?array {
		$f = self::cache_file( $url );
		if ( ! is_readable( $f ) ) {
			return null;
		}
		$d = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return is_array( $d ) ? $d : null;
	}

	/* =============================================================== verify */

	/**
	 * Check every evidence excerpt against the page it cites.
	 *
	 * ## OPTIONS
	 *
	 * --batch=<id>
	 * : Batch directory.
	 *
	 * [--category=<key>]
	 * : Only this category.
	 */
	public function verify( array $args, array $assoc ): void {
		$batch = self::batch( $assoc );
		$data  = self::read( $batch, 'candidates.json' );
		$cat   = (string) ( $assoc['category'] ?? '' );
		$fail  = 0;
		$ok    = 0;

		foreach ( (array) $data['candidates'] as $c ) {
			if ( ( '' !== $cat && $cat !== $c['category'] ) || 'candidate' !== ( $c['status'] ?? '' ) ) {
				continue;
			}
			$problems = self::check( $c );
			if ( $problems ) {
				$fail++;
				\WP_CLI::warning( $c['key'] . ":\n    - " . implode( "\n    - ", $problems ) );
			} else {
				$ok++;
				\WP_CLI::log( '  verified  ' . $c['key'] );
			}
		}
		if ( $fail ) {
			\WP_CLI::error( sprintf( '%d verified, %d failed. Import will refuse the failures.', $ok, $fail ), false );
			return;
		}
		\WP_CLI::success( sprintf( '%d candidate(s) verified against their cached pages.', $ok ) );
	}

	/**
	 * Why a candidate cannot be imported, or nothing.
	 *
	 * @return array<int, string>
	 */
	public static function check( array $c ): array {
		$p = array();

		foreach ( array( 'key', 'category', 'name', 'blurb', 'kind' ) as $req ) {
			if ( '' === trim( (string) ( $c[ $req ] ?? '' ) ) ) {
				$p[] = "missing {$req}";
			}
		}
		if ( ! category( (string) ( $c['category'] ?? '' ) ) ) {
			$p[] = 'unknown category ' . ( $c['category'] ?? '' );
		}
		if ( ! in_array( $c['kind'] ?? '', array( 'group', 'practice', 'place' ), true ) ) {
			$p[] = 'kind must be group, practice or place';
		}
		if ( ! in_array( $c['cost'] ?? 'unknown', COST_STATES, true ) ) {
			$p[] = 'cost must be one of ' . implode( '/', COST_STATES );
		}
		foreach ( array( 'beginner', 'come_alone' ) as $t ) {
			if ( ! in_array( $c[ $t ] ?? 'unknown', TRISTATE, true ) ) {
				$p[] = "{$t} must be yes/no/unknown";
			}
		}
		if ( empty( $c['source_urls'] ) ) {
			$p[] = 'no source_urls';
		}
		if ( '' === trim( (string) ( $c['suburb'] ?? '' ) ) && empty( $c['meeting_varies'] ) && '' === trim( (string) ( $c['region'] ?? '' ) ) ) {
			$p[] = 'no suburb, region or meeting_varies';
		}
		if ( 'ocean' === ( $c['category'] ?? '' ) ) {
			$sub = array_intersect( (array) ( $c['tags'] ?? array() ), array( 'social-dip', 'distance-swim', 'coached-swim' ) );
			if ( ! $sub && 'unknown' !== ( $c['subtype'] ?? '' ) ) {
				$p[] = 'ocean swimming needs a subtype tag, or "subtype": "unknown"';
			}
		}

		// Every material value that is not unknown needs evidence under its name.
		$need = array( 'name', 'activity' );
		if ( 'unknown' !== ( $c['cost'] ?? 'unknown' ) ) {
			$need[] = 'cost';
		}
		if ( '' !== trim( (string) ( $c['price_note'] ?? '' ) ) ) {
			$need[] = 'price';
		}
		if ( '' !== trim( (string) ( $c['schedule'] ?? '' ) ) ) {
			$need[] = 'schedule';
		}
		foreach ( array( 'beginner', 'come_alone' ) as $t ) {
			if ( 'unknown' !== ( $c[ $t ] ?? 'unknown' ) ) {
				$need[] = $t;
			}
		}
		if ( '' !== trim( (string) ( $c['meeting_point'] ?? '' ) ) || '' !== trim( (string) ( $c['suburb'] ?? '' ) ) ) {
			$need[] = 'location';
		}
		if ( '' !== trim( (string) ( $c['join']['url'] ?? '' ) ) ) {
			$need[] = 'join';
		}
		foreach ( (array) ( $c['tags'] ?? array() ) as $tag ) {
			$need[] = 'tag:' . $tag;
		}
		foreach ( (array) ( $c['details'] ?? array() ) as $d ) {
			if ( 'unknown' !== strtolower( (string) ( $d['value'] ?? '' ) ) ) {
				$need[] = 'detail:' . ( $d['label'] ?? '' );
			}
		}

		$have = array();
		foreach ( (array) ( $c['evidence'] ?? array() ) as $e ) {
			$field = (string) ( $e['field'] ?? '' );
			$url   = (string) ( $e['url'] ?? '' );
			$quote = (string) ( $e['excerpt'] ?? '' );
			$page  = self::cached( $url );
			if ( ! $page ) {
				$p[] = "{$field}: page not fetched ({$url})";
				continue;
			}
			if ( ! in_array( $url, (array) $c['source_urls'], true ) ) {
				$p[] = "{$field}: cites a page not listed in source_urls";
			}
			$hay = self::norm( (string) $page['text'] . ' ' . (string) $page['title'] . ' ' . (string) $page['desc'] . ' ' . wp_json_encode( $page['jsonld'] ) . ' ' . implode( ' ', array_keys( (array) ( $page['links'] ?? array() ) ) ) );
			if ( strlen( self::norm( $quote ) ) < 3 || false === strpos( $hay, self::norm( $quote ) ) ) {
				$p[] = "{$field}: excerpt not found on {$url}: \"" . substr( $quote, 0, 70 ) . '"';
				continue;
			}
			// The name excerpt has to actually be the name, not just be on the page.
			if ( 'name' === $field && false === strpos( self::name_key( $quote ), self::name_key( (string) ( $c['name'] ?? '' ) ) ) ) {
				$p[] = 'name: the excerpt does not contain the listing name';
				continue;
			}
			$have[ $field ] = true;
		}
		foreach ( $need as $f ) {
			if ( empty( $have[ $f ] ) ) {
				$p[] = "no verified evidence for {$f}";
			}
		}
		return array_values( array_unique( $p ) );
	}

	private static function norm( string $s ): string {
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = str_replace( array( "\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x93", "\xe2\x80\x94", "\xc2\xa0", '\\/' ), array( "'", "'", '"', '"', '-', '-', ' ', '/' ), $s );
		return strtolower( trim( (string) preg_replace( '#\s+#', ' ', $s ) ) );
	}

	/* =============================================================== import */

	/**
	 * Import verified candidates as drafts; matches become proposals.
	 *
	 * ## OPTIONS
	 *
	 * --batch=<id>
	 * : Batch directory.
	 *
	 * [--category=<key>]
	 * : Only this category.
	 *
	 * [--limit=<n>]
	 * : At most this many candidates.
	 *
	 * [--dry-run]
	 * : Report every decision, write nothing.
	 */
	public function import( array $args, array $assoc ): void {
		$batch = self::batch( $assoc );
		$data  = self::read( $batch, 'candidates.json' );
		$res   = self::run_import( $batch, (array) $data['candidates'], (string) ( $assoc['category'] ?? '' ), (int) ( $assoc['limit'] ?? 0 ), isset( $assoc['dry-run'] ) );

		foreach ( $res['lines'] as $l ) {
			\WP_CLI::log( $l );
		}
		\WP_CLI::success( sprintf(
			'%s%d draft(s) created, %d refreshed, %d proposal(s) for existing listings, %d need review, %d skipped.',
			$res['dry'] ? '[dry-run] ' : '',
			$res['n']['created'], $res['n']['refreshed'], $res['n']['proposal'], $res['n']['review'], $res['n']['skipped']
		) );
	}

	/** The import itself, callable from selftest. */
	public static function run_import( string $batch, array $candidates, string $cat, int $limit, bool $dry ): array {
		$journal = $dry ? self::journal( $batch ) : self::journal( $batch );
		$index   = self::index();
		$lines   = array();
		$n       = array( 'created' => 0, 'refreshed' => 0, 'proposal' => 0, 'review' => 0, 'skipped' => 0 );
		$seen    = 0;

		if ( $dry ) {
			$lines[] = '-- DRY RUN: nothing will be written --';
		}

		foreach ( $candidates as $c ) {
			if ( '' !== $cat && $cat !== ( $c['category'] ?? '' ) ) {
				continue;
			}
			if ( 'candidate' !== ( $c['status'] ?? '' ) ) {
				continue; // rejected and manual records are reported, never imported
			}
			if ( $limit && $seen >= $limit ) {
				break;
			}
			$seen++;
			$key = (string) $c['key'];

			$problems = self::check( $c );
			if ( $problems ) {
				$n['skipped']++;
				$lines[] = "  skip     {$key}: not verified (" . $problems[0] . ( count( $problems ) > 1 ? ', +' . ( count( $problems ) - 1 ) . ' more' : '' ) . ')';
				$journal['skipped'][ $key ] = 'not verified';
				continue;
			}

			// 1. Ours already: same stable key.
			$mine = self::by_key( $key );
			if ( $mine ) {
				$rec = $journal['created'][ $mine->ID ] ?? null;
				if ( 'draft' !== $mine->post_status || ! $rec || $rec['modified'] !== $mine->post_modified_gmt ) {
					$n['skipped']++;
					$lines[] = "  keep     {$key}: #{$mine->ID} was reviewed, published or edited since import -- left alone";
					continue;
				}
				if ( ! $dry ) {
					self::write_listing( $c, $batch, (int) $mine->ID );
					$journal['created'][ $mine->ID ]['modified'] = get_post_field( 'post_modified_gmt', $mine->ID );
				}
				$n['refreshed']++;
				$lines[] = "  refresh  {$key}: #{$mine->ID} (unedited draft from this batch)";
				continue;
			}

			// 2. Somebody else's listing: a proposal, never an overwrite.
			$match = self::match( $c, $index, $batch );
			if ( $match && 'batch' === $match['certainty'] ) {
				// The same organisation twice in one batch is one entity: the
				// fix is one record with "also", not a proposal to ourselves.
				$n['review']++;
				$journal['review'][ $key ] = 'same organisation as ' . $match['title'] . ' in this batch (' . $match['on'] . ') -- merge into one record with "also"';
				$lines[] = "  review   {$key}: " . $journal['review'][ $key ];
				continue;
			}
			if ( $match && 'exact' === $match['certainty'] ) {
				if ( ! $dry ) {
					self::propose( (int) $match['id'], $c, $batch );
					$journal['proposals'][ $match['id'] ][] = $key;
					$journal['proposals'][ $match['id'] ] = array_values( array_unique( $journal['proposals'][ $match['id'] ] ) );
				}
				$n['proposal']++;
				$lines[] = sprintf( '  propose  %s: matches existing #%d "%s" on %s -- proposal stored, listing unchanged', $key, $match['id'], $match['title'], $match['on'] );
				continue;
			}
			if ( $match ) {
				$n['review']++;
				$journal['review'][ $key ] = sprintf( 'possible duplicate of #%d "%s" (%s)', $match['id'], $match['title'], $match['on'] );
				$lines[] = "  review   {$key}: " . $journal['review'][ $key ] . ' -- not imported';
				continue;
			}

			// 3. New.
			if ( $dry ) {
				$lines[] = "  create   {$key}: \"{$c['name']}\" as a draft";
				self::remember( $index, $c, 0, '', $key );
				$n['created']++;
				continue;
			}
			$id = self::write_listing( $c, $batch, 0 );
			if ( ! $id ) {
				$n['skipped']++;
				$lines[] = "  skip     {$key}: insert failed";
				continue;
			}
			$journal['created'][ $id ] = array( 'key' => $key, 'modified' => get_post_field( 'post_modified_gmt', $id ), 'at' => gmdate( 'c' ) );
			self::remember( $index, $c, $id, '', $key );
			$n['created']++;
			$lines[] = "  create   {$key}: #{$id} \"{$c['name']}\" (draft)";
		}

		if ( ! $dry ) {
			$journal['runs'][] = array( 'at' => gmdate( 'c' ), 'category' => $cat, 'limit' => $limit, 'counts' => $n );
			update_option( JOURNAL . $batch, $journal, false );
		}
		return array( 'lines' => $lines, 'n' => $n, 'dry' => $dry );
	}

	/** Create or refresh one draft. Returns the post id, or 0. */
	private static function write_listing( array $c, string $batch, int $id ): int {
		$row  = category( (string) $c['category'] );
		$post = array(
			'post_type'    => PostTypes\LISTING,
			'post_status'  => 'draft',
			'post_title'   => (string) $c['name'],
			'post_excerpt' => (string) $c['blurb'],
		);
		if ( $id ) {
			$post['ID'] = $id;
		} else {
			$post['post_name'] = sanitize_title( (string) ( $c['slug'] ?? $c['name'] . ' ' . ( $c['suburb'] ?? '' ) ) );
		}
		$new = wp_insert_post( $post, true );
		if ( is_wp_error( $new ) ) {
			return 0;
		}
		$id = (int) $new;

		// Categories: this one plus any the same organisation also belongs to.
		$practice = array();
		$services = array();
		foreach ( array_merge( array( (string) $c['category'] ), (array) ( $c['also'] ?? array() ) ) as $k ) {
			$r = category( (string) $k );
			if ( ! $r ) {
				continue;
			}
			if ( 'practice' === $r['taxonomy'] ) {
				$practice[] = (string) $r['slug'];
			} else {
				$services[] = (string) $r['slug'];
				if ( ! empty( $r['home'] ) ) {
					$practice[] = (string) $r['home'];
				}
			}
		}
		$practice = array_values( array_unique( $practice ) );
		wp_set_object_terms( $id, $practice, Taxonomies\PRACTICE );
		if ( $practice ) {
			update_post_meta( $id, \Oria\Core\Primary\META, $practice[0] );
		}
		if ( $services ) {
			wp_set_object_terms( $id, $services, 'service' );
		}
		wp_set_object_terms( $id, array_values( array_filter( (array) ( $c['tags'] ?? array() ), static fn( $t ) => (bool) get_term_by( 'slug', (string) $t, Taxonomies\SPECIALTY ) ) ), Taxonomies\SPECIALTY );
		if ( 'yes' === ( $c['beginner'] ?? '' ) ) {
			wp_set_object_terms( $id, array( 'beginners' ), 'audience', true );
		}

		$area = self::area( (string) ( $c['suburb'] ?? '' ), (string) ( $c['region'] ?? '' ) );
		if ( $area ) {
			wp_set_object_terms( $id, array( $area ), Taxonomies\AREA );
		}

		$acf = static function ( string $name, $value ) use ( $id ): void {
			if ( function_exists( 'update_field' ) ) {
				update_field( $name, $value, $id );
			} else {
				update_post_meta( $id, $name, $value );
			}
		};
		/*
		 * Every field by KEY. By name, ACF resolves to whichever field of that
		 * name it finds first: "kind" landed on the Classes repeater's
		 * sub-field, "format" on the reset tool's, "email" on a page section's.
		 * The value survived, the reference meta did not.
		 */
		$acf( 'field_oria_kind', (string) $c['kind'] );
		$acf( 'field_oria_claim_status', 'unclaimed' );
		$acf( 'field_oria_format', 'in-person' );
		$acf( 'field_oria_website', (string) ( $c['website'] ?? '' ) );
		// Public organisation contact only; never a person's.
		$acf( 'field_oria_phone', (string) ( $c['contact']['phone'] ?? '' ) );
		$acf( 'field_oria_email', (string) ( $c['contact']['email'] ?? '' ) );
		// A fixed address only when the meeting point is fixed and known.
		$acf( 'field_oria_address', empty( $c['meeting_varies'] ) ? (string) ( $c['address'] ?? '' ) : '' );
		// The band cards already read: "Free" covers free and by-donation.
		if ( in_array( $c['cost'] ?? '', array( 'free', 'donation' ), true ) ) {
			$acf( 'field_oria_price_band', 'Free' );
		}
		// price_from prints as "$X / session", so it is only set by a candidate
		// whose price genuinely is a whole-dollar per-session figure.
		// Absent means empty: this only ever runs on the batch's own drafts,
		// so clearing a figure an earlier run wrote is correct, not a loss.
		$acf( 'field_oria_price_from', isset( $c['price_from'] ) && is_numeric( $c['price_from'] ) ? (int) $c['price_from'] : '' );
		// The new fields by KEY: ACF resolves names unreliably off-admin.
		$acf( 'field_oria_join_method', (string) ( $c['join']['method'] ?? 'unknown' ) );
		$acf( 'field_oria_join_url', (string) ( $c['join']['url'] ?? '' ) );
		$acf( 'field_oria_cost_status', (string) ( $c['cost'] ?? 'unknown' ) );
		$acf( 'field_oria_price_note', (string) ( $c['price_note'] ?? '' ) );
		$acf( 'field_oria_schedule_text', (string) ( $c['schedule'] ?? '' ) );
		$acf( 'field_oria_meeting_point', empty( $c['meeting_varies'] ) ? (string) ( $c['meeting_point'] ?? '' ) : '' );
		$acf( 'field_oria_meeting_varies', empty( $c['meeting_varies'] ) ? 0 : 1 );
		$acf( 'field_oria_beginner', (string) ( $c['beginner'] ?? 'unknown' ) );
		$acf( 'field_oria_come_alone', (string) ( $c['come_alone'] ?? 'unknown' ) );
		$acf(
			'field_oria_activity_details',
			array_values( array_map(
				static fn( array $d ): array => array( 'label' => (string) $d['label'], 'value' => (string) $d['value'] ),
				(array) ( $c['details'] ?? array() )
			) )
		);
		if ( ! empty( $c['meeting_varies'] ) ) {
			// No invented map pin for a group that meets in different places.
			foreach ( array( 'geo_lat', 'geo_lng', 'geo_precision' ) as $g ) {
				delete_post_meta( $id, $g );
			}
		}

		$checked = '';
		foreach ( (array) $c['evidence'] as $e ) {
			$page = self::cached( (string) $e['url'] );
			$at   = (string) ( $page['fetched_at'] ?? '' );
			if ( $at > $checked ) {
				$checked = $at;
			}
		}
		$evidence = array_map(
			static function ( array $e ): array {
				$page = self::cached( (string) $e['url'] );
				return array(
					'field'   => (string) $e['field'],
					'url'     => (string) $e['url'],
					'at'      => (string) ( $page['fetched_at'] ?? '' ),
					'excerpt' => substr( (string) $e['excerpt'], 0, 300 ),
				);
			},
			(array) $c['evidence']
		);
		update_post_meta( $id, BATCH, $batch );
		update_post_meta( $id, KEY, (string) $c['key'] );
		update_post_meta( $id, CHECKED, $checked );
		update_post_meta( $id, URLS, wp_json_encode( array_values( (array) $c['source_urls'] ), JSON_UNESCAPED_SLASHES ) );
		update_post_meta( $id, EVIDENCE, wp_slash( (string) wp_json_encode( $evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		update_post_meta( $id, REVIEW, 'scraped' );
		if ( ! empty( $c['flags'] ) ) {
			update_post_meta( $id, '_oria_src_flags', implode( ', ', (array) $c['flags'] ) );
		}

		clean_post_cache( $id );
		return $id;
	}

	private static function area( string $suburb, string $region ): int {
		foreach ( array( $suburb, $region ) as $name ) {
			if ( '' === trim( $name ) ) {
				continue;
			}
			$slug  = sanitize_title( $name );
			$alias = json_decode( (string) @file_get_contents( ORIA_CORE_DIR . 'data/area-aliases.json' ), true ); // phpcs:ignore
			if ( isset( $alias['aliases'][ $slug ] ) && get_term_by( 'slug', $alias['aliases'][ $slug ], Taxonomies\AREA ) ) {
				$slug = $alias['aliases'][ $slug ];
			}
			$t = get_term_by( 'slug', $slug, Taxonomies\AREA );
			if ( $t instanceof \WP_Term && \Oria\Core\Cities\for_area( $t ) && 'perth' === \Oria\Core\Cities\for_area( $t )['slug'] ) {
				return (int) $t->term_id;
			}
		}
		return 0;
	}

	/** Store what the batch would change on a listing somebody else owns. */
	private static function propose( int $id, array $c, string $batch ): void {
		$all           = json_decode( (string) get_post_meta( $id, PROPOSAL, true ), true );
		$all           = is_array( $all ) ? $all : array();
		$all[ $batch ] = array(
			'at'         => gmdate( 'c' ),
			'key'        => (string) $c['key'],
			'categories' => array_values( array_merge( array( (string) $c['category'] ), (array) ( $c['also'] ?? array() ) ) ),
			'tags'       => (array) ( $c['tags'] ?? array() ),
			'join'       => (array) ( $c['join'] ?? array() ),
			'cost'       => (string) ( $c['cost'] ?? 'unknown' ),
			'price_note' => (string) ( $c['price_note'] ?? '' ),
			'schedule'   => (string) ( $c['schedule'] ?? '' ),
			'details'    => (array) ( $c['details'] ?? array() ),
			'sources'    => (array) $c['source_urls'],
		);
		update_post_meta( $id, PROPOSAL, wp_slash( (string) wp_json_encode( $all, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
	}

	/* ------------------------------------------------------------- matching */

	private static function by_key( string $key ): ?\WP_Post {
		$p = get_posts( array( 'post_type' => PostTypes\LISTING, 'post_status' => 'any', 'posts_per_page' => 1, 'meta_key' => KEY, 'meta_value' => $key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		return $p ? $p[0] : null;
	}

	/**
	 * Every existing listing, keyed the ways a duplicate could show itself.
	 *
	 * @return array<string, mixed>
	 */
	private static function index(): array {
		$ix    = array( 'url' => array(), 'name' => array(), 'phone' => array(), 'rows' => array() );
		$posts = get_posts( array( 'post_type' => PostTypes\LISTING, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => -1 ) );
		foreach ( $posts as $p ) {
			$suburbs = wp_get_post_terms( $p->ID, Taxonomies\AREA, array( 'fields' => 'slugs' ) );
			self::remember(
				$ix,
				array(
					'name'    => $p->post_title,
					'website' => (string) get_post_meta( $p->ID, 'website', true ),
					'join'    => array( 'url' => (string) get_post_meta( $p->ID, 'join_url', true ) ),
					'contact' => array( 'phone' => (string) get_post_meta( $p->ID, 'phone', true ) ),
					'suburb'  => is_array( $suburbs ) && $suburbs ? $suburbs[0] : '',
				),
				(int) $p->ID,
				$p->post_title
			);
		}
		return $ix;
	}

	private static function remember( array &$ix, array $c, int $id, string $title = '', string $batch_key = '' ): void {
		$title = '' !== $title ? $title : (string) $c['name'];
		foreach ( array( (string) ( $c['website'] ?? '' ), (string) ( $c['join']['url'] ?? '' ) ) as $u ) {
			$k = self::url_key( $u );
			if ( '' !== $k ) {
				$ix['url'][ $k ] = $ix['url'][ $k ] ?? array( $id, $title );
				if ( '' !== $batch_key ) {
					$ix['mine'][ 'url:' . $k ] = $ix['mine'][ 'url:' . $k ] ?? $batch_key;
				}
			}
		}
		$k = self::name_key( (string) $c['name'] );
		if ( '' !== $k ) {
			$ix['name'][ $k ] = $ix['name'][ $k ] ?? array( $id, $title );
			if ( '' !== $batch_key ) {
				$ix['mine'][ 'name:' . $k ] = $ix['mine'][ 'name:' . $k ] ?? $batch_key;
			}
		}
		$k = self::phone_key( (string) ( $c['contact']['phone'] ?? '' ) );
		if ( '' !== $k ) {
			$ix['phone'][ $k ] = $ix['phone'][ $k ] ?? array( $id, $title );
		}
		$ix['rows'][] = array( $id, $title, self::name_key( (string) $c['name'] ), sanitize_title( (string) ( $c['suburb'] ?? '' ) ) );
	}

	/** @return array{id: int, title: string, on: string, certainty: string}|null */
	private static function match( array $c, array $ix, string $batch = '' ): ?array {
		/*
		 * "branch": true declares a distinct, separately verified location of
		 * an organisation whose other groups share its website -- Befriend's
		 * walks, say. URL and phone then prove nothing, so only the name is
		 * compared. The declaration is visible in the candidate file, never
		 * inferred.
		 */
		if ( ! empty( $c['branch'] ) ) {
			$c['website']          = '';
			$c['join']['url']      = '';
			$c['contact']['phone'] = '';
		}
		// Earlier in this same run?
		$mine = array( 'name:' . self::name_key( (string) $c['name'] ) );
		foreach ( array( (string) ( $c['website'] ?? '' ), (string) ( $c['join']['url'] ?? '' ) ) as $u ) {
			if ( '' !== self::url_key( $u ) ) {
				$mine[] = 'url:' . self::url_key( $u );
			}
		}
		foreach ( $mine as $m ) {
			if ( isset( $ix['mine'][ $m ] ) ) {
				return array( 'id' => 0, 'title' => $ix['mine'][ $m ], 'on' => strtok( $m, ':' ), 'certainty' => 'batch' );
			}
		}
		$hit = self::match_existing( $c, $ix );
		// A draft this batch made on an earlier run is ours, not an owner's.
		if ( $hit && $hit['id'] && '' !== $batch && get_post_meta( $hit['id'], BATCH, true ) === $batch ) {
			return array( 'id' => $hit['id'], 'title' => '#' . $hit['id'] . ' ' . $hit['title'], 'on' => $hit['on'], 'certainty' => 'batch' );
		}
		return $hit;
	}

	/** @return array{id: int, title: string, on: string, certainty: string}|null */
	private static function match_existing( array $c, array $ix ): ?array {
		foreach ( array( (string) ( $c['website'] ?? '' ), (string) ( $c['join']['url'] ?? '' ) ) as $u ) {
			$k = self::url_key( $u );
			if ( '' !== $k && isset( $ix['url'][ $k ] ) ) {
				return array( 'id' => $ix['url'][ $k ][0], 'title' => $ix['url'][ $k ][1], 'on' => 'official URL ' . $k, 'certainty' => 'exact' );
			}
		}
		$k = self::phone_key( (string) ( $c['contact']['phone'] ?? '' ) );
		if ( '' !== $k && isset( $ix['phone'][ $k ] ) ) {
			return array( 'id' => $ix['phone'][ $k ][0], 'title' => $ix['phone'][ $k ][1], 'on' => 'phone', 'certainty' => 'exact' );
		}
		$name = self::name_key( (string) $c['name'] );
		if ( '' !== $name && isset( $ix['name'][ $name ] ) ) {
			return array( 'id' => $ix['name'][ $name ][0], 'title' => $ix['name'][ $name ][1], 'on' => 'name', 'certainty' => 'exact' );
		}
		// Similar names are a question for a person, never a merge.
		$sub = sanitize_title( (string) ( $c['suburb'] ?? '' ) );
		foreach ( $ix['rows'] as $r ) {
			if ( '' === $name || '' === $r[2] ) {
				continue;
			}
			similar_text( $name, $r[2], $pct );
			$contains = strlen( $r[2] ) >= 6 && strlen( $name ) >= 6 && ( false !== strpos( $name, $r[2] ) || false !== strpos( $r[2], $name ) );
			if ( $pct >= 85 || ( $contains && ( '' === $sub || $sub === $r[3] ) ) ) {
				return array( 'id' => (int) $r[0], 'title' => (string) $r[1], 'on' => sprintf( 'similar name, %d%%', (int) $pct ), 'certainty' => 'possible' );
			}
		}
		return null;
	}

	/**
	 * An official URL reduced to what identifies an organisation.
	 *
	 * Tracking parameters go; a shared host (Meetup, Facebook, a council, a
	 * booking platform) keeps its path, because the host alone would make
	 * every Meetup group one organisation.
	 */
	public static function url_key( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( str_contains( $url, '//' ) ? $url : 'https://' . $url );
		$host  = (string) preg_replace( '#^(www|m)\.#', '', strtolower( (string) ( $parts['host'] ?? '' ) ) );
		if ( '' === $host ) {
			return '';
		}
		$shared = false;
		foreach ( SHARED_HOSTS as $h ) {
			if ( $host === $h || str_ends_with( $host, '.' . $h ) ) {
				$shared = true;
			}
		}
		if ( str_ends_with( $host, '.gov.au' ) ) {
			$shared = true;
		}
		if ( ! $shared ) {
			return $host;
		}
		$path = strtolower( rtrim( (string) ( $parts['path'] ?? '' ), '/' ) );
		parse_str( (string) ( $parts['query'] ?? '' ), $q );
		foreach ( array_keys( $q ) as $k ) {
			if ( preg_match( '#^(utm_|fbclid|gclid|mc_|ref$|source$)#i', (string) $k ) ) {
				unset( $q[ $k ] );
			}
		}
		ksort( $q );
		return $host . $path . ( $q ? '?' . http_build_query( $q ) : '' );
	}

	private static function name_key( string $name ): string {
		$k = strtolower( remove_accents( html_entity_decode( $name, ENT_QUOTES ) ) );
		$k = (string) preg_replace( '#^(the|a)\s+#', '', $k );
		return (string) preg_replace( '#[^a-z0-9]#', '', $k );
	}

	private static function phone_key( string $phone ): string {
		$d = (string) preg_replace( '#\D#', '', $phone );
		if ( str_starts_with( $d, '61' ) && strlen( $d ) > 9 ) {
			$d = substr( $d, 2 );
		}
		return strlen( $d ) >= 8 ? substr( $d, -9 ) : '';
	}

	/* ============================================================= rollback */

	/**
	 * Undo a batch: its unedited drafts go to the Trash, its proposals go.
	 *
	 * Anything published, reviewed or edited since the import stays, and so
	 * does every listing that existed before it.
	 *
	 * ## OPTIONS
	 *
	 * --batch=<id>
	 * : Batch to roll back.
	 *
	 * [--dry-run]
	 * : Report only.
	 */
	public function rollback( array $args, array $assoc ): void {
		$res = self::run_rollback( self::batch( $assoc, false ), isset( $assoc['dry-run'] ) );
		foreach ( $res['lines'] as $l ) {
			\WP_CLI::log( $l );
		}
		\WP_CLI::success( sprintf( '%s%d draft(s) trashed, %d kept, %d proposal set(s) removed.', $res['dry'] ? '[dry-run] ' : '', $res['trashed'], $res['kept'], $res['proposals'] ) );
	}

	public static function run_rollback( string $batch, bool $dry ): array {
		$journal = self::journal( $batch );
		$out     = array( 'lines' => array(), 'trashed' => 0, 'kept' => 0, 'proposals' => 0, 'dry' => $dry );

		foreach ( $journal['created'] as $id => $rec ) {
			$post = get_post( (int) $id );
			if ( ! $post instanceof \WP_Post || 'trash' === $post->post_status ) {
				unset( $journal['created'][ $id ] );
				continue;
			}
			$ours   = get_post_meta( $post->ID, BATCH, true ) === $batch;
			$intact = 'draft' === $post->post_status && $post->post_modified_gmt === $rec['modified'] && 'scraped' === get_post_meta( $post->ID, REVIEW, true );
			if ( ! $ours || ! $intact ) {
				$out['kept']++;
				$out['lines'][] = sprintf( '  keep   #%d %s -- %s', $post->ID, $post->post_title, $ours ? 'reviewed, published or edited since import' : 'no longer carries this batch' );
				continue;
			}
			if ( ! $dry ) {
				wp_trash_post( $post->ID );
				unset( $journal['created'][ $id ] );
			}
			$out['trashed']++;
			$out['lines'][] = sprintf( '  trash  #%d %s', $post->ID, $post->post_title );
		}

		foreach ( array_keys( $journal['proposals'] ) as $id ) {
			$all = json_decode( (string) get_post_meta( (int) $id, PROPOSAL, true ), true );
			if ( is_array( $all ) && isset( $all[ $batch ] ) ) {
				if ( ! $dry ) {
					unset( $all[ $batch ] );
					$all ? update_post_meta( (int) $id, PROPOSAL, wp_slash( (string) wp_json_encode( $all, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ) : delete_post_meta( (int) $id, PROPOSAL );
					unset( $journal['proposals'][ $id ] );
				}
				$out['proposals']++;
				$out['lines'][] = sprintf( '  unpropose #%d', $id );
			}
		}

		if ( ! $dry ) {
			$journal['runs'][] = array( 'at' => gmdate( 'c' ), 'rollback' => array( 'trashed' => $out['trashed'], 'kept' => $out['kept'] ) );
			update_option( JOURNAL . $batch, $journal, false );
		}
		return $out;
	}

	/* =============================================================== report */

	/**
	 * Per-category table and the exceptions list, to the screen and report.md.
	 *
	 * ## OPTIONS
	 *
	 * --batch=<id>
	 * : Batch.
	 */
	public function report( array $args, array $assoc ): void {
		$batch   = self::batch( $assoc );
		$data    = self::read( $batch, 'candidates.json' );
		$journal = self::journal( $batch );
		$log     = self::read( $batch, 'fetch-log.json', true ) ?: array( 'requests' => array() );

		$by_key = array();
		foreach ( $journal['created'] as $id => $rec ) {
			$by_key[ $rec['key'] ] = (int) $id;
		}
		$proposed = array();
		foreach ( $journal['proposals'] as $id => $keys ) {
			foreach ( (array) $keys as $k ) {
				$proposed[ $k ] = (int) $id;
			}
		}

		$rows = array();
		$orgs = array();
		$exc  = array();
		foreach ( (array) $data['candidates'] as $c ) {
			$k = (string) $c['category'];
			$rows[ $k ] = $rows[ $k ] ?? array( 'found' => 0, 'draft' => 0, 'match' => 0, 'review' => 0, 'rejected' => 0 );
			$rows[ $k ]['found']++;
			foreach ( array_merge( array( $k ), (array) ( $c['also'] ?? array() ) ) as $ck ) {
				$orgs[ self::name_key( (string) $c['name'] ) ][] = (string) $ck;
			}
			$status = (string) ( $c['status'] ?? '' );
			if ( 'rejected' === $status ) {
				$rows[ $k ]['rejected']++;
				$exc[] = sprintf( '| %s | %s | rejected | %s |', $k, $c['name'], $c['reject_reason'] ?? '' );
			} elseif ( isset( $by_key[ $c['key'] ] ) ) {
				$rows[ $k ]['draft']++;
			} elseif ( isset( $proposed[ $c['key'] ] ) ) {
				$rows[ $k ]['match']++;
			} else {
				$rows[ $k ]['review']++;
				$why = $journal['review'][ $c['key'] ] ?? ( 'manual' === $status ? ( $c['reject_reason'] ?? 'needs manual research' ) : ( $journal['skipped'][ $c['key'] ] ?? 'not imported yet' ) );
				$exc[] = sprintf( '| %s | %s | review | %s |', $k, $c['name'], $why );
			}
			foreach ( (array) ( $c['flags'] ?? array() ) as $f ) {
				$exc[] = sprintf( '| %s | %s | flag | %s |', $k, $c['name'], $f );
			}
		}
		// One row per URL: the latest attempt, not one per run.
		$latest = array();
		foreach ( $log['requests'] as $r ) {
			$latest[ (string) $r['url'] ] = $r;
		}
		foreach ( $latest as $r ) {
			if ( in_array( $r['state'], array( 'blocked', 'stopped', 'refused', 'error' ), true ) ) {
				$exc[] = sprintf( '| fetch | %s | %s | %s |', $r['url'], $r['state'], $r['note'] );
			}
		}
		foreach ( (array) ( self::read( $batch, 'manifest.json' )['sources'] ?? array() ) as $s ) {
			if ( 'fetch' !== ( $s['decision'] ?? '' ) ) {
				$exc[] = sprintf( '| %s | %s | %s | %s |', $s['category'] ?? '', $s['domain'] ?? '', $s['decision'] ?? '', $s['note'] ?? '' );
			}
		}

		$md   = array( "# Source batch {$batch}", '', 'Generated ' . wp_date( 'j F Y, g:ia' ) . ' (Perth).', '', '| Category | Candidates found | New drafts | Existing matches | Review required | Rejected |', '|---|---:|---:|---:|---:|---:|' );
		$tot  = array( 0, 0, 0, 0, 0 );
		foreach ( $rows as $k => $r ) {
			$md[] = sprintf( '| %s | %d | %d | %d | %d | %d |', $k, $r['found'], $r['draft'], $r['match'], $r['review'], $r['rejected'] );
			$tot  = array( $tot[0] + $r['found'], $tot[1] + $r['draft'], $tot[2] + $r['match'], $tot[3] + $r['review'], $tot[4] + $r['rejected'] );
		}
		$md[] = vsprintf( '| **Total** | **%d** | **%d** | **%d** | **%d** | **%d** |', $tot );
		$multi = array_filter( $orgs, static fn( $v ) => count( array_unique( $v ) ) > 1 );
		$md[]  = '';
		$md[]  = sprintf( 'Unique organisations: %d. Appearing in more than one category: %d%s.', count( $orgs ), count( $multi ), $multi ? ' (' . implode( ', ', array_map( static fn( $k, $v ) => $k . ': ' . implode( '+', $v ), array_keys( $multi ), $multi ) ) . ')' : '' );
		$md[]  = '';
		$md[]  = '## Exceptions';
		$md[]  = '';
		$md[]  = '| Where | What | Kind | Detail |';
		$md[]  = '|---|---|---|---|';
		$md    = array_merge( $md, $exc ?: array( '| - | - | - | none |' ) );

		self::write_text( $batch, 'report.md', implode( "\n", $md ) . "\n" );
		\WP_CLI::log( implode( "\n", $md ) );
	}

	/* ============================================================= selftest */

	/**
	 * Prove the promises: dry run writes nothing, reruns do not duplicate,
	 * owners' listings are only proposed to, rollback spares edited work.
	 *
	 * Uses its own fixture records (named "ZZ Selftest ...") and removes
	 * them afterwards. It never touches a real listing except to read one.
	 */
	public function selftest( array $args, array $assoc ): void {
		$batch = 'selftest-' . gmdate( 'YmdHis' );
		$fails = 0;
		$t     = static function ( bool $ok, string $what ) use ( &$fails ): void {
			\WP_CLI::log( ( $ok ? '  PASS  ' : '  FAIL  ' ) . $what );
			$fails += $ok ? 0 : 1;
		};

		// A fixture page, so verify has something real to check against.
		$url = 'https://selftest.invalid/page';
		self::store( $url, array( 'url' => $url, 'title' => 'Selftest', 'desc' => '', 'jsonld' => array(), 'links' => array(), 'text' => 'ZZ Selftest Runners meet in Hyde Park every Saturday at 7am. Free to join. Social running for all paces.', 'fetched_at' => gmdate( 'c' ) ) );

		$claimed = get_posts( array( 'post_type' => PostTypes\LISTING, 'post_status' => 'publish', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => 'claimed_by', 'value' => array( '', '0' ), 'compare' => 'NOT IN' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$owner   = $claimed ? $claimed[0] : null;

		$cands = array(
			self::fixture( 'zz-selftest-runners', 'ZZ Selftest Runners', $url ),
			self::fixture( 'zz-selftest-runners-dupe-name', 'ZZ Selftest Runners', $url ), // same organisation twice in one file
		);
		if ( $owner ) {
			// Its own fixture page carrying the owner's name, so it verifies.
			$ourl = 'https://selftest.invalid/owned';
			self::store( $ourl, array( 'url' => $ourl, 'title' => 'Selftest', 'desc' => '', 'jsonld' => array(), 'links' => array(), 'text' => $owner->post_title . ' -- ZZ Selftest Runners meet in Hyde Park every Saturday at 7am. Free to join. Social running for all paces.', 'fetched_at' => gmdate( 'c' ) ) );
			$c            = self::fixture( 'zz-selftest-owned', (string) $owner->post_title, $ourl );
			$c['evidence'][0]['excerpt'] = (string) $owner->post_title;
			$c['website'] = (string) get_post_meta( $owner->ID, 'website', true );
			$cands[]      = $c;
			$before       = array( get_post_field( 'post_modified_gmt', $owner->ID ), get_post_field( 'post_excerpt', $owner->ID ), get_post_meta( $owner->ID, 'claim_status', true ), get_post_meta( $owner->ID, 'phone', true ) );
		}

		global $wpdb;
		$snap = static fn(): string => md5( (string) $wpdb->get_var( "SELECT CONCAT(COUNT(*),':',COALESCE(MAX(ID),0)) FROM {$wpdb->posts}" ) . (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ) . (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships}" ) ); // phpcs:ignore

		$s0  = $snap();
		$dry = self::run_import( $batch, $cands, '', 0, true );
		$t( $snap() === $s0, 'dry run changes no posts, meta or term relationships' );
		$t( 1 === $dry['n']['created'], 'dry run: the same organisation twice in one file is created once (' . $dry['n']['created'] . ')' );

		$one = self::run_import( $batch, $cands, '', 0, false );
		$ids = array_keys( self::journal( $batch )['created'] );
		$t( 1 === count( $ids ), 'first import creates exactly one draft' );
		$id  = (int) ( $ids[0] ?? 0 );
		$t( $id && 'draft' === get_post_status( $id ), 'it is a draft' );
		$t( $id && 'scraped' === get_post_meta( $id, REVIEW, true ), 'it is marked scraped, not reviewed' );
		$t( $id && 'unknown' === get_post_meta( $id, 'beginner', true ), 'unknown beginner suitability stays unknown' );
		$t( $id && '1' === (string) get_post_meta( $id, 'meeting_varies', true ) && '' === (string) get_post_meta( $id, 'geo_lat', true ), 'a moving meeting point gets no map pin' );

		$two = self::run_import( $batch, $cands, '', 0, false );
		$t( 0 === $two['n']['created'] && 1 === count( self::journal( $batch )['created'] ), 'second import creates nothing new' );

		if ( $owner ) {
			$after = array( get_post_field( 'post_modified_gmt', $owner->ID ), get_post_field( 'post_excerpt', $owner->ID ), get_post_meta( $owner->ID, 'claim_status', true ), get_post_meta( $owner->ID, 'phone', true ) );
			$t( $before === $after, 'the claimed listing "' . $owner->post_title . '" is unchanged' );
			$prop = json_decode( (string) get_post_meta( $owner->ID, PROPOSAL, true ), true );
			$t( is_array( $prop ) && isset( $prop[ $batch ] ), 'and carries a proposal instead' );
		} else {
			\WP_CLI::log( '  SKIP  no claimed listing on this site to test owner protection against' );
		}

		$t( self::url_key( 'https://www.meetup.com/perth-runners/?utm_source=x' ) !== self::url_key( 'https://www.meetup.com/perth-walkers/' ), 'two Meetup groups are two organisations' );
		$t( self::url_key( 'https://www.meetup.com/perth-runners/?utm_source=x' ) === self::url_key( 'https://meetup.com/perth-runners' ), 'tracking parameters do not make a new organisation' );
		$t( 'example.com.au' === self::url_key( 'https://www.example.com.au/book?utm_campaign=y' ), 'an organisation\'s own domain is the identity' );

		// Publishing would be held: asked of the guard directly, so the test
		// never actually publishes a fixture and fires publish hooks.
		$held = \Oria\Core\PublishGuard\missing( $id );
		$t( in_array( 'an editorial review', $held, true ), 'the publish guard holds an unreviewed batch listing (' . implode( ', ', $held ) . ')' );

		// Rollback: an edited draft survives, an untouched one goes.
		$j = self::journal( $batch );
		$j['created'][ $id ]['modified'] = get_post_field( 'post_modified_gmt', $id );
		update_option( JOURNAL . $batch, $j, false );
		$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', time() + 5 ) ), array( 'ID' => $id ) ); // simulate an editor's save
		clean_post_cache( $id );
		$rb = self::run_rollback( $batch, false );
		$t( 'draft' === get_post_status( $id ) && 1 === $rb['kept'], 'rollback keeps a draft edited since import' );

		$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => $j['created'][ $id ]['modified'] ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		$rb = self::run_rollback( $batch, false );
		$t( 'trash' === get_post_status( $id ), 'rollback trashes an untouched draft from its own batch' );
		if ( $owner ) {
			$prop = json_decode( (string) get_post_meta( $owner->ID, PROPOSAL, true ), true );
			$t( ! is_array( $prop ) || ! isset( $prop[ $batch ] ), 'and removes its proposal from the owner\'s listing' );
		}

		// Clean up the fixtures completely: they are the test's own records.
		wp_delete_post( $id, true );
		delete_option( JOURNAL . $batch );
		@unlink( self::cache_file( $url ) ); // phpcs:ignore
		@unlink( self::cache_file( 'https://selftest.invalid/owned' ) ); // phpcs:ignore
		$t( ! get_post( $id ), 'fixtures removed' );

		$fails ? \WP_CLI::error( "{$fails} check(s) failed." ) : \WP_CLI::success( 'All checks passed.' );
	}

	private static function fixture( string $key, string $name, string $url ): array {
		return array(
			'key' => $key, 'category' => 'running', 'name' => $name, 'kind' => 'group',
			'blurb' => 'A selftest record.', 'website' => 'https://selftest.invalid/', 'source_urls' => array( $url ),
			'suburb' => '', 'region' => '', 'meeting_varies' => true, 'cost' => 'free', 'beginner' => 'unknown', 'come_alone' => 'unknown',
			'tags' => array( 'social-running' ), 'details' => array(), 'join' => array( 'method' => 'turn-up', 'url' => '' ),
			'status' => 'candidate',
			'evidence' => array(
				array( 'field' => 'name', 'url' => $url, 'excerpt' => 'ZZ Selftest Runners' ),
				array( 'field' => 'activity', 'url' => $url, 'excerpt' => 'meet in Hyde Park every Saturday' ),
				array( 'field' => 'cost', 'url' => $url, 'excerpt' => 'Free to join' ),
				array( 'field' => 'tag:social-running', 'url' => $url, 'excerpt' => 'Social running for all paces' ),
			),
		);
	}

	/* ============================================================== plumbing */

	private static function batch( array $assoc, bool $must_exist = true ): string {
		$b = sanitize_key( (string) ( $assoc['batch'] ?? '' ) );
		if ( '' === $b ) {
			\WP_CLI::error( '--batch=<id> is required.' );
		}
		if ( $must_exist && ! is_dir( self::dir( $b ) ) ) {
			\WP_CLI::error( 'No batch directory ' . self::dir( $b ) );
		}
		return $b;
	}

	private static function dir( string $batch ): string {
		return ORIA_CORE_DIR . 'sources/' . $batch . '/';
	}

	private static function read( string $batch, string $file, bool $optional = false ): array {
		$path = self::dir( $batch ) . $file;
		if ( ! is_readable( $path ) ) {
			if ( $optional ) {
				return array();
			}
			\WP_CLI::error( "Missing {$path}" );
		}
		$d = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $d ) ) {
			\WP_CLI::error( "Not valid JSON: {$path}" );
		}
		return $d;
	}

	private static function write( string $batch, string $file, array $data ): void {
		self::write_text( $batch, $file, (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	}

	private static function write_text( string $batch, string $file, string $text ): void {
		wp_mkdir_p( self::dir( $batch ) );
		file_put_contents( self::dir( $batch ) . $file, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/** @return array{created: array, proposals: array, review: array, skipped: array, runs: array} */
	private static function journal( string $batch ): array {
		$j = get_option( JOURNAL . $batch, array() );
		$j = is_array( $j ) ? $j : array();
		return $j + array( 'created' => array(), 'proposals' => array(), 'review' => array(), 'skipped' => array(), 'runs' => array() );
	}

	private static function entry( string $url, string $state, string $note, int $status = 0, string $final = '' ): array {
		return array_filter( array( 'url' => $url, 'at' => gmdate( 'c' ), 'state' => $state, 'status' => $status ?: null, 'final' => $final && $final !== $url ? $final : null, 'note' => $note ), static fn( $v ) => null !== $v );
	}
}
