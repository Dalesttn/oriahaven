<?php
/**
 * The weak-page audit: which pages to merge, improve, or take out of the
 * index.
 *
 * Usage (from the WordPress root, on the server):
 *   php wp-content/plugins/oria-core/tools/weak-pages.php            # report only
 *   php wp-content/plugins/oria-core/tools/weak-pages.php --apply    # and act on it
 *   php wp-content/plugins/oria-core/tools/weak-pages.php --release=<listing-slug>
 *
 * Reads data/weak-pages-input.json, which tools/weak-pages-collect.py
 * builds on a desktop from Search Console. Run the collector, commit its
 * output, git pull here, then run this.
 *
 * WHAT --apply DOES, AND ALL IT DOES
 *
 * It takes listings out of Google's index -- never off the site -- and
 * puts back any it took out earlier that have since earned their place.
 * Nothing else. Merging two pages and rewriting a thin one both need a
 * person, so those are reported and left alone.
 *
 * A listing is withdrawn only when ALL of these hold:
 *
 *   - it has been published for MIN_AGE_DAYS, so Google has had a fair
 *     look at it through a whole 90-day window;
 *   - it earned no impressions in that window;
 *   - nobody engaged with it on the site: no more than a handful of views,
 *     no call, no website click, no directions, no enquiry;
 *   - it says very little: a description under THIN_WORDS words, and
 *     nothing added by its owner -- no photos, hours, classes or FAQ;
 *   - it is unclaimed and not featured.
 *
 * Any one of those failing keeps it in. The conditions are AND on purpose:
 * on a young site, zero impressions alone is mostly a statement about the
 * site's authority, not about the page -- 64% of the sitemap had none in
 * the first run. Pruning on impressions alone would have noindexed most of
 * the directory.
 *
 * THREE SAFETY BRAKES
 *
 *   The input must be recent. A stale or missing file makes every page
 *   look like it had no impressions, which is exactly the signal for
 *   noindexing it. --apply refuses an input older than MAX_INPUT_AGE days.
 *
 *   No more than MAX_PER_RUN withdrawals in one run, thinnest first. If a
 *   bad input slips through anyway, the damage is capped and visible.
 *
 *   Dry run by default.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

use Oria\Core\Analytics;
use Oria\Core\PostTypes;
use Oria\Core\WeakPages;

const MIN_AGE_DAYS  = 120;
const THIN_WORDS    = 30;
const MAX_VIEWS     = 3;   // this many views in 90 days is somebody passing, not engagement
const MAX_PER_RUN   = 25;
const MAX_INPUT_AGE = 45;
const SNIPPET_IMP   = 100; // seen this often, on page one or two, and never clicked
const SNIPPET_POS   = 20;

$argv    = $argv ?? array();
$apply   = in_array( '--apply', $argv, true );
$release = '';
$input   = ORIA_CORE_DIR . 'data/weak-pages-input.json';
foreach ( $argv as $a ) {
	if ( 0 === strpos( $a, '--release=' ) ) {
		$release = substr( $a, 10 );
	}
	if ( 0 === strpos( $a, '--input=' ) ) {
		$input = substr( $a, 8 );
	}
}

/* ---- a single release, by hand ------------------------------------------ */

if ( '' !== $release ) {
	$post = get_page_by_path( $release, OBJECT, PostTypes\LISTING );
	if ( ! $post instanceof WP_Post ) {
		fwrite( STDERR, "No listing \"{$release}\".\n" );
		exit( 1 );
	}
	if ( ! isset( WeakPages\held()[ $post->ID ] ) ) {
		echo "{$post->post_title} is not held. Nothing to do.\n";
		exit( 0 );
	}
	WeakPages\release( (int) $post->ID, 'released by hand.' );
	echo "{$post->post_title} is back in the index and the sitemap.\n";
	exit( 0 );
}

/* ---- the evidence ------------------------------------------------------- */

$data = is_readable( $input ) ? json_decode( (string) file_get_contents( $input ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
if ( ! is_array( $data ) || empty( $data['urls'] ) ) {
	fwrite( STDERR, "Cannot read {$input}. Run tools/weak-pages-collect.py on a desktop, commit the result, and git pull.\n" );
	exit( 1 );
}

$generated = strtotime( (string) ( $data['generated'] ?? '' ) ) ?: 0;
$age_input = $generated ? (int) floor( ( time() - $generated ) / DAY_IN_SECONDS ) : 9999;
$stale     = $age_input > MAX_INPUT_AGE;

$by_path = array();
foreach ( $data['urls'] as $row ) {
	$by_path[ (string) wp_parse_url( (string) $row['url'], PHP_URL_PATH ) ] = $row;
}

echo "\n";
printf(
	"Weak-page audit. Search Console %s to %s, collected %d day%s ago.\n",
	$data['window']['start'] ?? '?',
	$data['window']['end'] ?? '?',
	$age_input,
	1 === $age_input ? '' : 's'
);
echo $apply ? ( $stale ? "APPLY REFUSED -- the input is older than " . MAX_INPUT_AGE . " days. Reporting only.\n" : "APPLY -- withdrawals and releases below are carried out.\n" ) : "DRY RUN -- nothing is changed. Add --apply to act.\n";
if ( $apply && $stale ) {
	$apply = false;
}
echo str_repeat( '=', 76 ) . "\n";

/* ---- listings ----------------------------------------------------------- */

$ids = get_posts(
	array(
		'post_type'      => PostTypes\LISTING,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

$held     = WeakPages\held();
$now      = time();
$withdraw = array();
$improve  = array();
$snippet  = array();
$releases = array();
$new      = 0;
$next_due = PHP_INT_MAX;
$phones   = array();
$domains  = array();

foreach ( $ids as $id ) {
	$id   = (int) $id;
	$path = (string) wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH );
	$row  = $by_path[ $path ] ?? array();
	$imp  = (int) ( $row['impressions'] ?? 0 );
	$clk  = (int) ( $row['clicks'] ?? 0 );
	$pos  = $row['position'] ?? null;

	$age     = (int) floor( ( $now - (int) get_post_time( 'U', true, $id ) ) / DAY_IN_SECONDS );
	$words   = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $id, 'raw' ) ) );
	$own     = owner_content( $id );
	$claimed = (int) get_post_meta( $id, 'claimed_by', true ) > 0;
	$feat    = '1' === (string) get_post_meta( $id, 'admin_featured', true );
	$views   = Analytics\total( $id, 'view', 90 );
	$acts    = 0;
	foreach ( array( 'web', 'tel', 'mail', 'book', 'dir', 'enq' ) as $t ) {
		$acts += Analytics\total( $id, $t, 90 );
	}
	$engaged = $views > MAX_VIEWS || $acts > 0;
	$thin    = $words < THIN_WORDS && 0 === $own;

	$sig = compact( 'imp', 'clk', 'age', 'words', 'own', 'views', 'acts' );
	$line = array( 'id' => $id, 'name' => get_the_title( $id ), 'path' => $path ) + $sig;

	// Duplicate businesses: the same phone, or the same website in the same
	// suburb. The same website in different suburbs is a business with
	// branches, which is fine -- Bodhi runs Wembley and Yallingup.
	$tel = substr( (string) preg_replace( '/\D/', '', (string) get_field( 'phone', $id, false ) ), -8 );
	if ( strlen( $tel ) === 8 ) {
		$phones[ $tel ][] = $line;
	}
	$dom = domain( (string) get_field( 'website', $id, false ) );
	if ( '' !== $dom ) {
		$domains[ $dom . '|' . suburb( $id ) ][] = $line;
	}

	// Already out: does it still deserve to be?
	if ( isset( $held[ $id ] ) ) {
		$why = '';
		if ( $claimed ) {
			$why = 'claimed by its owner.';
		} elseif ( $feat ) {
			$why = 'featured.';
		} elseif ( $words >= THIN_WORDS ) {
			$why = sprintf( 'the description is now %d words.', $words );
		} elseif ( $own > 0 ) {
			$why = 'its owner has added their own content.';
		} elseif ( $engaged ) {
			$why = sprintf( 'people are using it on the site (%d views, %d contact clicks in 90 days).', $views, $acts );
		}
		if ( '' !== $why ) {
			$releases[] = $line + array( 'why' => $why );
		}
		continue;
	}

	if ( $age < MIN_AGE_DAYS ) {
		$new++;
		$next_due = min( $next_due, $now + ( MIN_AGE_DAYS - $age ) * DAY_IN_SECONDS );
		continue;
	}

	if ( $thin && 0 === $imp && ! $engaged && ! $claimed && ! $feat ) {
		$withdraw[] = $line;
	} elseif ( $thin ) {
		$improve[] = $line;
	} elseif ( $imp >= SNIPPET_IMP && 0 === $clk && null !== $pos && $pos <= SNIPPET_POS ) {
		$snippet[] = $line + array( 'pos' => $pos );
	}
}

// Thinnest first, so a capped run withdraws the weakest.
usort( $withdraw, static fn( $a, $b ) => $a['words'] <=> $b['words'] ?: strcmp( $a['name'], $b['name'] ) );
$over     = array_slice( $withdraw, MAX_PER_RUN );
$withdraw = array_slice( $withdraw, 0, MAX_PER_RUN );

/* ---- report: listings --------------------------------------------------- */

section( sprintf( 'WITHDRAW FROM THE INDEX  (%d)', count( $withdraw ) ), 'Thin, unclaimed, 4+ months old, no impressions and no engagement. Stays on the site.' );
foreach ( $withdraw as $l ) {
	printf( "  %-44s %2d words  %s\n", cut( $l['name'], 44 ), $l['words'], $l['path'] );
}
if ( $over ) {
	printf( "\n  + %d more qualify. Capped at %d a run -- they come up next time.\n", count( $over ), MAX_PER_RUN );
}

section( sprintf( 'PUT BACK  (%d)', count( $releases ) ), 'Withdrawn earlier, and now earning a place.' );
foreach ( $releases as $l ) {
	printf( "  %-44s %s\n", cut( $l['name'], 44 ), $l['why'] );
}

$dupes = array();
foreach ( array( $phones, $domains ) as $groups ) {
	foreach ( $groups as $group ) {
		if ( count( $group ) < 2 ) {
			continue;
		}
		$key = implode( ',', array_map( static fn( $l ) => $l['id'], $group ) );
		$dupes[ $key ] = $group;
	}
}
section( sprintf( 'MERGE -- LIKELY THE SAME BUSINESS TWICE  (%d)', count( $dupes ) ), 'Same phone, or same website in the same suburb. Check, then 301 the weaker into the stronger.' );
foreach ( $dupes as $group ) {
	foreach ( $group as $i => $l ) {
		printf( "  %s %-42s %4d imp  %2d words  %s\n", 0 === $i ? '*' : ' ', cut( $l['name'], 42 ), $l['imp'], $l['words'], $l['path'] );
	}
	echo "\n";
}

section( sprintf( 'IMPROVE -- THIN BUT SOMEBODY IS LOOKING  (%d)', count( $improve ) ), 'Under ' . THIN_WORDS . ' words with nothing of its own, yet it has impressions or engagement. Rewrite the description -- it is being seen and has nothing to say.' );
usort( $improve, static fn( $a, $b ) => ( $b['imp'] + 10 * $b['views'] ) <=> ( $a['imp'] + 10 * $a['views'] ) );
foreach ( array_slice( $improve, 0, 40 ) as $l ) {
	printf( "  %-44s %4d imp  %3d views  %2d words\n", cut( $l['name'], 44 ), $l['imp'], $l['views'], $l['words'] );
}
if ( count( $improve ) > 40 ) {
	printf( "  ... and %d more\n", count( $improve ) - 40 );
}

section( sprintf( 'IMPROVE -- SEEN, NEVER CLICKED  (%d)', count( $snippet ) ), 'Shown ' . SNIPPET_IMP . '+ times on page one or two with no click. The page is fine; the title or meta description is not selling it.' );
usort( $snippet, static fn( $a, $b ) => $b['imp'] <=> $a['imp'] );
foreach ( $snippet as $l ) {
	printf( "  %-44s %4d imp  pos %5.1f\n", cut( $l['name'], 44 ), $l['imp'], (float) $l['pos'] );
}

/* ---- report: editorial -------------------------------------------------- */

// App categories are archives of apps, not writing, and are judged with
// the other archives -- which is to say, not yet.
$editorial = array( 'journal', 'best-of', 'compare', 'app', 'app-guide' );
$quiet     = array();
$young     = 0;
$unknown   = 0;
foreach ( $data['urls'] as $row ) {
	if ( ! in_array( $row['kind'], $editorial, true ) || (int) $row['impressions'] > 0 ) {
		continue;
	}
	$pid = post_for( (string) $row['url'] );
	if ( ! $pid ) {
		// No age, no verdict. Listing a page as unfound when it might be a
		// week old is how this section cried wolf about every app.
		$unknown++;
		continue;
	}
	if ( ( $now - (int) get_post_time( 'U', true, $pid ) ) < MIN_AGE_DAYS * DAY_IN_SECONDS ) {
		$young++;
		continue;
	}
	$quiet[] = $row;
}
section( sprintf( 'IMPROVE -- WRITING NOBODY HAS FOUND  (%d)', count( $quiet ) ), 'Articles, guides and comparisons with no impressions after four months. Never withdrawn automatically: retarget the title at a real query, link to it from the pages that rank, or fold it into a stronger piece.' . ( $young ? " ({$young} more are too new to judge.)" : '' ) . ( $unknown ? " ({$unknown} could not be matched to a post and were skipped.)" : '' ) );
foreach ( $quiet as $row ) {
	printf( "  %-12s %s\n", $row['kind'], wp_parse_url( (string) $row['url'], PHP_URL_PATH ) );
}

/* ---- report: archive pages ---------------------------------------------- */

$overlaps = (array) ( $data['overlaps'] ?? array() );
$vocab    = array();
$both     = array();
$nested   = array();
foreach ( $overlaps as $o ) {
	$o['kind'] = overlap_kind( (string) $o['keep'], (string) $o['drop'] );
	if ( (int) $o['keep_imp'] >= 50 && (int) $o['drop_imp'] >= 50 ) {
		$both[] = $o;
	} elseif ( 'vocabulary' === $o['kind'] ) {
		$vocab[] = $o;
	} else {
		$nested[] = $o;
	}
}

section(
	sprintf( 'MERGE -- TWO NAMES FOR ONE THING  (%d)', count( $vocab ) ),
	'Two terms listing the same places. Fold the weaker term into the stronger -- wp oria merge-terms does specialty into practice, and registers the redirect before deleting.'
);
foreach ( $vocab as $o ) {
	pair( $o, 'fold' );
}

section(
	sprintf( 'DIFFERENTIATE -- SAME PLACES, BOTH EARNING  (%d)', count( $both ) ),
	'The overlap is high but both pages earn impressions, so each answers a query the other does not. Merging would throw one away. Make them say different things instead: what separates the two, which places do one and not the other.'
);
foreach ( $both as $o ) {
	pair( $o, 'and ' );
}

section(
	sprintf( 'IDENTICAL BY STRUCTURE  (%d)', count( $nested ) ),
	'Not a content problem. A region with one populated suburb, or a category whose local listings all sit in one sub-category, renders a page identical to its child. These come and go as listings are added, so they want one rule in the listing-count gates -- noindex a parent page whose listings are exactly one child\'s -- rather than a fix per pair.'
);
foreach ( $nested as $o ) {
	pair( $o, 'same' );
}

$disagree = (array) ( $data['sitemap_noindex'] ?? array() );
if ( $disagree ) {
	section(
		sprintf( 'SITEMAP AND PAGE DISAGREE  (%d)', count( $disagree ) ),
		'In the sitemap, but the page itself says noindex. Google is asked to index a page that then refuses -- a listing-count floor and its sitemap exclusion have drifted apart.'
	);
	foreach ( $disagree as $u ) {
		printf( "  %s\n", wp_parse_url( (string) $u, PHP_URL_PATH ) );
	}
}

$migrated = strtotime( (string) ( $data['migrated'] ?? '' ) ) ?: 0;
$arch     = array_filter( $data['urls'], static fn( $r ) => in_array( $r['kind'], array( 'explore', 'category', 'suburb', 'region' ), true ) );
$silent   = array_filter( $arch, static fn( $r ) => 0 === (int) $r['impressions'] );
$judge    = $migrated + MIN_AGE_DAYS * DAY_IN_SECONDS;
section(
	sprintf( 'ARCHIVE PAGES WITH NO IMPRESSIONS  (%d of %d)', count( $silent ), count( $arch ) ),
	$now < $judge
		? sprintf( 'Not judged yet. These moved to /explore/ on %s and are too new to read anything into; from %s they are held to the same four-month rule. Thin ones are already noindexed by their listing-count floors.', gmdate( 'j M Y', $migrated ), gmdate( 'j M Y', $judge ) )
		: 'Past the four-month mark. Any that are also in the overlap list above are the first to fold.'
);

/* ---- summary and action ------------------------------------------------- */

echo "\n" . str_repeat( '=', 76 ) . "\n";
printf( "%d listings checked. %d too new to judge", count( $ids ), $new );
if ( $new && PHP_INT_MAX !== $next_due ) {
	printf( ' (the first becomes judgeable %s)', gmdate( 'j M Y', $next_due ) );
}
printf( ". %d currently withdrawn.\n", count( $held ) );

if ( ! $apply ) {
	echo "\nNothing changed. Add --apply to withdraw and put back the listings above.\n\n";
	exit( 0 );
}

foreach ( $withdraw as $l ) {
	WeakPages\hold(
		$l['id'],
		sprintf( '%d-word description and nothing added by an owner; no impressions in 90 days and no engagement on the site after %d days.', $l['words'], $l['age'] ),
		array_intersect_key( $l, array_flip( array( 'imp', 'clk', 'age', 'words', 'own', 'views', 'acts' ) ) )
	);
}
foreach ( $releases as $l ) {
	WeakPages\release( $l['id'], $l['why'] );
}
printf( "\nWithdrew %d, put back %d. Purge the cache so the sitemap and robots tags update: wp litespeed-purge all\n\n", count( $withdraw ), count( $releases ) );

/* ---- helpers ------------------------------------------------------------ */

/** Anything on the listing its owner (or we, for them) added beyond the researched basics. */
function owner_content( int $id ): int {
	$n = 0;
	$g = get_field( 'gallery', $id, false );
	$n += is_array( $g ) ? count( $g ) : 0;
	foreach ( array( 'opening_hours', 'amenities', 'good_for', 'transit', 'parking', 'faq', 'classes', 'packages', 'team' ) as $f ) {
		if ( ! empty( get_field( $f, $id, false ) ) ) {
			$n++;
		}
	}
	return $n;
}

/** The registrable host of a website, or '' for platforms many businesses share. */
function domain( string $url ): string {
	$host = strtolower( (string) wp_parse_url( ( false === strpos( $url, '//' ) ? 'https://' : '' ) . $url, PHP_URL_HOST ) );
	$host = (string) preg_replace( '/^www\./', '', $host );
	$shared = '/(facebook|instagram|linktr\.ee|google|fresha|mindbody|square|wix|bookwell|squarespace|business\.site|healthengine|hotdoc|cliniko|gettimely|halaxy)/';
	return '' === $host || preg_match( $shared, $host ) ? '' : $host;
}

function suburb( int $id ): string {
	$terms = get_the_terms( $id, \Oria\Core\Taxonomies\AREA );
	if ( ! is_array( $terms ) ) {
		return '';
	}
	// City > region > suburb: the deepest term is the suburb. A region
	// would be too coarse -- two branches a few suburbs apart are branches.
	usort( $terms, static fn( $a, $b ) => count( get_ancestors( $b->term_id, $b->taxonomy ) ) <=> count( get_ancestors( $a->term_id, $a->taxonomy ) ) );
	return $terms[0]->slug ?? '';
}

/**
 * What kind of overlap a pair is, read from the two addresses.
 *
 * /explore/{city}/{category}/{last}/ -- where {last} is either a suburb or
 * region (a combination) or a specialty (a facet). Same {last} under two
 * categories, or two areas under one category, is the directory's own
 * hierarchy producing a page twice; two specialties under one category is
 * the vocabulary saying one thing twice.
 */
function overlap_kind( string $a, string $b ): string {
	$pa = array_values( array_filter( explode( '/', (string) wp_parse_url( $a, PHP_URL_PATH ) ) ) );
	$pb = array_values( array_filter( explode( '/', (string) wp_parse_url( $b, PHP_URL_PATH ) ) ) );
	if ( 'area' === ( $pa[0] ?? '' ) || 'area' === ( $pb[0] ?? '' ) ) {
		return 'structure';
	}
	$la = (string) end( $pa );
	$lb = (string) end( $pb );
	if ( $la === $lb ) {
		// /explore/perth/smoothies-juice/ beside /explore/perth/nutrition/
		// smoothies-juice/ is one specialty published at two addresses --
		// that wants a redirect, not the parent-page rule.
		return count( $pa ) !== count( $pb ) ? 'vocabulary' : 'structure';
	}
	// The city is an area too: margaret-river is both the city and the
	// town, so a region page and its only suburb can share the city's slug.
	$city = (string) ( $pa[1] ?? '' );
	$area = \Oria\Core\Taxonomies\AREA;
	$is_area = static fn( string $s ): bool => $s === $city || (bool) get_term_by( 'slug', $s, $area );
	if ( $is_area( $la ) && $is_area( $lb ) ) {
		return 'structure'; // one area inside another
	}
	return 'vocabulary';
}

/** @param array<string, mixed> $o */
function pair( array $o, string $verb ): void {
	printf(
		"  %3d%%  keep %-46s %3d places  %4d imp\n        %s %-46s %3d places  %4d imp\n",
		(int) round( 100 * (float) $o['overlap'] ),
		cut( (string) wp_parse_url( (string) $o['keep'], PHP_URL_PATH ), 46 ),
		$o['keep_n'],
		$o['keep_imp'],
		$verb,
		cut( (string) wp_parse_url( (string) $o['drop'], PHP_URL_PATH ), 46 ),
		$o['drop_n'],
		$o['drop_imp']
	);
}

/**
 * The post behind an editorial address. url_to_postid() only knows core
 * rewrite rules, and every editorial type here has its own -- /apps/,
 * /best/, /guides/, /compare/ -- so it returns 0 for most of them.
 */
function post_for( string $url ): int {
	$pid = url_to_postid( $url );
	if ( $pid ) {
		return $pid;
	}
	$slug = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$hit  = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => array( 'post', 'best_of', 'compare', 'wellness_app', 'app_guide' ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	return $hit ? (int) $hit[0] : 0;
}

function section( string $title, string $note ): void {
	echo "\n" . $title . "\n";
	echo wordwrap( $note, 74, "\n", true ) . "\n\n";
}

function cut( string $s, int $n ): string {
	return strlen( $s ) > $n ? substr( $s, 0, $n - 1 ) . '~' : $s;
}
