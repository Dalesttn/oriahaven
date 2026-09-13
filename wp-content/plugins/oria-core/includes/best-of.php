<?php
/**
 * Best Of -- curated shortlists, and the badges they award.
 *
 * A guide is a `best_of` post whose picks live in one repeater: listing,
 * award, why. That repeater is the single source of truth. The badge on a
 * listing's card and profile is READ from the guides that hold it, never
 * stored on the listing, so removing a pick from a guide removes its badge
 * everywhere at once and nobody has to remember a second place to edit.
 *
 * Badges are editorial and say so. "Featured" is a paid placement and is a
 * different word, a different colour and a different code path (Tiers). The
 * two must never look alike -- see badge_html() and .badge--best.
 *
 * Copy rule for everything here: an editor SELECTED, CONSIDERED or
 * SHORTLISTED a practice. Nothing claims a venue was tested or personally
 * reviewed unless the editor typed that themselves.
 */

declare(strict_types=1);

namespace Oria\Core\BestOf;

use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const POST_TYPE = PostTypes\BEST_OF;
const PATH      = 'best';
const REWRITE_V = '1';
const INDEX_KEY = 'oria_best_of_index';

/** Award slug => label. Add here, not in the field, so labels stay one list. */
const AWARDS = array(
	'best_for_beginners' => 'Best for beginners',
	'beginner_friendly'  => 'Beginner friendly',
	'best_sauna'         => 'Best sauna',
	'best_for_recovery'  => 'Best for recovery',
	'best_value'         => 'Best value',
	'oria_pick'          => 'Oria Haven pick',
);

/** Guide category slug => label; the hub groups by these. */
const CATEGORIES = array(
	'beginners'   => 'Beginner friendly',
	'relax'       => 'Relax & recover',
	'move'        => 'Move',
	'social'      => 'Social',
	'experiences' => 'Special experiences',
	'places'      => 'Location guides',
);

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );
	add_action( 'pre_get_posts', __NAMESPACE__ . '\archive_query' );

	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 20 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 20 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description', 20 );
	add_action( 'wp_head', __NAMESPACE__ . '\schema', 5 );

	// The index of which listing sits in which guide is rebuilt when a
	// guide changes. Cheap to rebuild; wrong to serve stale.
	add_action( 'save_post_' . POST_TYPE, __NAMESPACE__ . '\forget_index' );
	add_action( 'deleted_post', __NAMESPACE__ . '\forget_index' );
	add_action( 'trashed_post', __NAMESPACE__ . '\forget_index' );
	add_action( 'untrashed_post', __NAMESPACE__ . '\forget_index' );

	add_filter( 'manage_' . POST_TYPE . '_posts_columns', __NAMESPACE__ . '\admin_columns' );
	add_action( 'manage_' . POST_TYPE . '_posts_custom_column', __NAMESPACE__ . '\admin_column', 10, 2 );
}

/** A new post type's rewrite rules only reach the server once rebuilt. */
function maybe_flush(): void {
	if ( get_option( 'oria_best_of_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_best_of_rewrite_v', REWRITE_V );
	}
}

/** The hub shows every guide, featured first, then by title. */
function archive_query( \WP_Query $q ): void {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_post_type_archive( POST_TYPE ) ) {
		return;
	}
	$q->set( 'posts_per_page', 60 );
	$q->set( 'orderby', 'title' );
	$q->set( 'order', 'ASC' );
}

/* ------------------------------------------------------------------ urls */

function hub_url(): string {
	return (string) ( get_post_type_archive_link( POST_TYPE ) ?: home_url( '/' . PATH . '/' ) );
}

function hub_heading(): string {
	return __( 'Best wellness in Perth', 'oria' );
}

function hub_lede(): string {
	return __( 'Standout places across Perth, shortlisted by our editors. From beginner-friendly yoga and Pilates to saunas, massage and places to unwind, each guide narrows the directory to a handful worth your first visit.', 'oria' );
}

/* ----------------------------------------------------------------- guides */

/** @return list<\WP_Post> */
function guides( int $limit = 60 ): array {
	static $cache = null;
	if ( null !== $cache ) {
		return array_slice( $cache, 0, $limit );
	}
	$cache = get_posts(
		array(
			'post_type'        => POST_TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => 60,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);
	return array_slice( $cache, 0, $limit );
}

function category( int $guide ): string {
	$c = (string) get_field( 'guide_category', $guide );
	return isset( CATEGORIES[ $c ] ) ? $c : '';
}

function category_label( string $slug ): string {
	return CATEGORIES[ $slug ] ?? '';
}

function is_featured( int $guide ): bool {
	return (bool) get_field( 'featured_guide', $guide );
}

/** The directory category this guide draws from, if the editor set one. */
function practice( int $guide ): ?\WP_Term {
	$id = (int) get_field( 'guide_practice', $guide );
	$t  = $id ? get_term( $id, Taxonomies\PRACTICE ) : null;
	return $t instanceof \WP_Term ? $t : null;
}

/** Intro under the H1; falls back to the excerpt so a card and a page agree. */
function intro( int $guide ): string {
	$i = trim( (string) get_field( 'guide_intro', $guide ) );
	return '' !== $i ? $i : trim( (string) get_post_field( 'post_excerpt', $guide ) );
}

/** "Updated September 2026" -- the month the guide was last saved. */
function updated( int $guide ): string {
	return sprintf(
		/* translators: %s: month and year */
		__( 'Updated %s', 'oria' ),
		get_the_modified_date( 'F Y', $guide )
	);
}

/**
 * The picks, in the editor's order, with anything unpublished dropped.
 *
 * @return list<array{listing:int, award:string, label:string, best_for:string, reason:string, highlights:list<string>, lead:bool}>
 */
function entries( int $guide ): array {
	static $cache = array();
	if ( isset( $cache[ $guide ] ) ) {
		return $cache[ $guide ];
	}
	$out = array();
	foreach ( (array) get_field( 'best_of_entries', $guide ) as $row ) {
		$listing = (int) ( $row['listing'] ?? 0 );
		if ( ! $listing || 'publish' !== get_post_status( $listing ) || PostTypes\LISTING !== get_post_type( $listing ) ) {
			continue;
		}
		$award = (string) ( $row['award'] ?? '' );
		$out[] = array(
			'listing'    => $listing,
			'award'      => $award,
			'label'      => label( $award, (string) ( $row['award_label'] ?? '' ) ),
			'best_for'   => trim( (string) ( $row['best_for'] ?? '' ) ),
			'reason'     => trim( (string) ( $row['reason'] ?? '' ) ),
			'highlights' => highlights( (string) ( $row['highlights'] ?? '' ) ),
			'lead'       => ! empty( $row['lead_badge'] ),
		);
	}
	return $cache[ $guide ] = $out;
}

function label( string $award, string $override = '' ): string {
	$override = trim( $override );
	if ( '' !== $override ) {
		return $override;
	}
	return AWARDS[ $award ] ?? AWARDS['oria_pick'];
}

/** "Beginner classes, Equipment supplied, Intro offer" -> up to four items. */
function highlights( string $text ): array {
	$parts = preg_split( '/\s*[,;•|]\s*|\s*\n\s*/', $text ) ?: array();
	$parts = array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
	return array_slice( $parts, 0, 4 );
}

/**
 * Three guides to read next: the same category first, then the rest.
 *
 * @return list<\WP_Post>
 */
function related( int $guide, int $n = 3 ): array {
	$cat  = category( $guide );
	$same = array();
	$rest = array();
	foreach ( guides() as $g ) {
		if ( $g->ID === $guide ) {
			continue;
		}
		if ( '' !== $cat && category( $g->ID ) === $cat ) {
			$same[] = $g;
		} else {
			$rest[] = $g;
		}
	}
	return array_slice( array_merge( $same, $rest ), 0, $n );
}

/* ------------------------------------------------------- listing -> guide */

/**
 * listing id => every published guide that picked it.
 *
 * Built once from all guides and kept in a transient; guides are few and
 * the lookup runs on every card, so this is the cheap direction.
 *
 * @return array<int, list<array{guide:int, award:string, label:string, reason:string, lead:bool}>>
 */
function index(): array {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$stored = get_transient( INDEX_KEY );
	if ( is_array( $stored ) ) {
		return $memo = $stored;
	}
	$memo = array();
	foreach ( guides() as $g ) {
		foreach ( entries( $g->ID ) as $e ) {
			$memo[ $e['listing'] ][] = array(
				'guide'  => $g->ID,
				'award'  => $e['award'],
				'label'  => $e['label'],
				'reason' => $e['reason'],
				'lead'   => $e['lead'],
			);
		}
	}
	set_transient( INDEX_KEY, $memo, DAY_IN_SECONDS );
	return $memo;
}

function forget_index( int $post_id ): void {
	if ( POST_TYPE === get_post_type( $post_id ) ) {
		delete_transient( INDEX_KEY );
	}
}

/** @return list<array{guide:int, award:string, label:string, reason:string, lead:bool}> */
function guides_for_listing( int $listing ): array {
	return index()[ $listing ] ?? array();
}

/**
 * The one badge a card may carry. The editor's lead badge wins; otherwise
 * the first guide alphabetically. Null when the listing is in no guide.
 *
 * @return array{label:string, url:string}|null
 */
function card_badge( int $listing ): ?array {
	$in = guides_for_listing( $listing );
	if ( ! $in ) {
		return null;
	}
	$pick = $in[0];
	foreach ( $in as $row ) {
		if ( $row['lead'] ) {
			$pick = $row;
			break;
		}
	}
	return array( 'label' => $pick['label'], 'url' => (string) get_permalink( $pick['guide'] ) );
}

/**
 * The badge markup. One shape everywhere so a reader learns it once.
 *
 * ✦ rather than a dot: the dot is what Featured and Claimed use, and these
 * must not read as the same family.
 */
function badge_html( string $label, string $url = '', string $extra_class = '' ): string {
	$class = trim( 'badge badge--best ' . $extra_class );
	$inner = '<span class="badge--best__mark" aria-hidden="true">&#10022;</span>' . esc_html( $label );
	if ( '' === $url ) {
		return '<span class="' . esc_attr( $class ) . '">' . $inner . '</span>';
	}
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '" title="' . esc_attr__( 'See the Best Of guide this comes from', 'oria' ) . '">' . $inner . '</a>';
}

/**
 * The award's seal: the emblem in the theme's assets/img/badges, one per
 * award slug. An override label still shows its base award's seal.
 * 'webp' for the page, 'png' (1000px) for a practice to download.
 */
function seal_url( string $award, string $ext = 'webp' ): string {
	$award = isset( AWARDS[ $award ] ) ? $award : 'oria_pick';
	$rel   = "assets/img/badges/{$award}.{$ext}";
	return file_exists( get_template_directory() . '/' . $rel ) ? get_template_directory_uri() . '/' . $rel : '';
}

/** Every badge a listing holds, linked to its guide -- for the profile. */
function badges_html( int $listing ): string {
	$out = '';
	foreach ( guides_for_listing( $listing ) as $row ) {
		$out .= badge_html( $row['label'], (string) get_permalink( $row['guide'] ) );
	}
	return $out;
}

/* ------------------------------------------------------------ listing facts */

/** Suburb (or region) name for a listing, the way cards print it. */
function suburb( int $listing ): string {
	$suburb = null;
	$region = null;
	foreach ( wp_get_post_terms( $listing, Taxonomies\AREA ) as $t ) {
		if ( ! $t instanceof \WP_Term ) {
			continue;
		}
		if ( $t->parent ) {
			$suburb = $t;
		} elseif ( ! $region ) {
			$region = $t;
		}
	}
	$t = $suburb ?: $region;
	return $t ? \Oria\Theme\tname( $t ) : '';
}

function price_from( int $listing ): string {
	$p = (float) get_field( 'price_from', $listing );
	return $p > 0 ? '$' . number_format_i18n( $p, 0 === (int) ( $p * 100 ) % 100 ? 0 : 2 ) : '';
}

/* ------------------------------------------------------------------- seo */

function title( string $title ): string {
	if ( is_post_type_archive( POST_TYPE ) ) {
		return hub_heading() . ' | ' . get_bloginfo( 'name' );
	}
	return $title;
}

function core_title( array $parts ): array {
	if ( is_post_type_archive( POST_TYPE ) ) {
		$parts['title'] = hub_heading();
	}
	return $parts;
}

function description( string $desc ): string {
	if ( is_post_type_archive( POST_TYPE ) ) {
		return '' !== $desc ? $desc : hub_lede();
	}
	if ( '' === $desc && is_singular( POST_TYPE ) ) {
		return wp_trim_words( intro( (int) get_the_ID() ), 28, '…' );
	}
	return $desc;
}

/**
 * ItemList of the picks, plus FAQPage when the editor wrote questions.
 * The listing URLs are the real profile pages, so each item is a node
 * search already knows.
 */
function schema(): void {
	if ( ! is_singular( POST_TYPE ) ) {
		return;
	}
	$guide = (int) get_the_ID();
	$url   = (string) get_permalink( $guide );
	$items = array();
	foreach ( entries( $guide ) as $i => $e ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => (string) get_permalink( $e['listing'] ),
			'name'     => wp_specialchars_decode( (string) get_post_field( 'post_title', $e['listing'], 'raw' ) ),
		);
	}
	if ( count( $items ) < 2 ) {
		return;
	}
	$graph = array(
		array(
			'@type'           => 'ItemList',
			'@id'             => $url . '#picks',
			'name'            => wp_specialchars_decode( get_the_title( $guide ) ),
			'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		),
	);
	$faq = array();
	foreach ( (array) get_field( 'guide_faq', $guide ) as $row ) {
		$q = trim( (string) ( $row['question'] ?? '' ) );
		$a = trim( (string) ( $row['answer'] ?? '' ) );
		if ( '' !== $q && '' !== $a ) {
			$faq[] = array(
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a ),
			);
		}
	}
	if ( count( $faq ) >= 2 ) {
		$graph[] = array( '@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => $faq );
	}
	$out = array( '@context' => 'https://schema.org', '@graph' => $graph );
	if ( function_exists( '\Oria\Core\Schema\decoded' ) ) {
		$out = \Oria\Core\Schema\decoded( $out );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $out, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}

/* ----------------------------------------------------------------- admin */

function admin_columns( array $cols ): array {
	$out = array();
	foreach ( $cols as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'title' === $k ) {
			$out['bo_category'] = __( 'Category', 'oria' );
			$out['bo_picks']    = __( 'Picks', 'oria' );
			$out['bo_featured'] = __( 'Featured on hub', 'oria' );
		}
	}
	return $out;
}

function admin_column( string $col, int $post_id ): void {
	switch ( $col ) {
		case 'bo_category':
			echo esc_html( category_label( category( $post_id ) ) ?: '—' );
			break;
		case 'bo_picks':
			echo esc_html( (string) count( entries( $post_id ) ) );
			break;
		case 'bo_featured':
			echo is_featured( $post_id ) ? '&#10003;' : '—';
			break;
	}
}
