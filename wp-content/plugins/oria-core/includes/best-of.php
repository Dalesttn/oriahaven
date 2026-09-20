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
	'best_for_beginners'   => 'Best for beginners',
	'beginner_friendly'    => 'Beginner friendly',
	'best_sauna'           => 'Best sauna',
	'best_ice_bath'        => 'Best ice bath',
	'best_sauna_ice_bath'  => 'Best sauna + ice bath',
	'best_sound_bath'      => 'Best sound bath',
	'best_breathwork'      => 'Best breathwork',
	'best_for_recovery'    => 'Best for recovery',
	'best_for_relaxation'  => 'Best for relaxation',
	'best_quiet_escape'    => 'Best quiet escape',
	'best_sunday_reset'    => 'Best Sunday reset',
	'best_for_couples'     => 'Best for couples',
	'best_small_group'     => 'Best small group',
	'best_private_session' => 'Best private session',
	'best_value'           => 'Best value',
	'best_budget_pick'     => 'Best budget pick',
	'best_remedial_massage' => 'Best remedial massage',
	'best_sports_active'   => 'Best for sports & active',
	'best_evening'         => 'Best evening appointments',
	'best_weekend'         => 'Best weekend option',
	'best_massage'         => 'Best massage',
	'best_relaxation_massage' => 'Best relaxation massage',
	'best_pregnancy_massage' => 'Best pregnancy massage',
	'best_day_spa'         => 'Best day spa',
	'best_couples_spa'     => 'Best couples spa',
	'best_luxury'          => 'Best luxury experience',
	'best_reformer'        => 'Best reformer Pilates',
	'best_intro_offer'     => 'Best intro offer',
	'best_retreat'         => 'Best retreat',
	'best_day_retreat'     => 'Best day retreat',
	'best_acupuncture'     => 'Best acupuncture',
	'oria_pick'            => 'Oria Haven pick',
);

/**
 * Private-health rebate status a pick can carry. Only what the listing
 * itself says: "confirmed" means the practice states it, never that we
 * checked with a fund. The footnote under the table says the rest.
 */
const REBATES = array(
	'confirmed' => 'Private health rebates available*',
	'provider'  => 'Check with provider',
	'not_listed' => 'Not listed',
	'none'      => 'Not available',
);

/** Guide category slug => label, in hub order; the hub groups by these. */
const CATEGORIES = array(
	'relax'       => 'Relax & reset',
	'recovery'    => 'Recovery',
	'hands-on'    => 'Hands-on care',
	'beginners'   => 'Beginner friendly',
	'budget'      => 'By budget',
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

/**
 * The month an editor last reviewed the guide, else the month it was last
 * saved. The two are different claims: a review date is typed on purpose,
 * a save date is whatever WordPress did.
 */
function reviewed_month( int $guide ): string {
	$raw = trim( (string) get_field( 'editorially_reviewed_date', $guide ) );
	$ts  = '' !== $raw ? strtotime( $raw ) : false;
	return $ts ? wp_date( 'F Y', $ts ) : (string) get_the_modified_date( 'F Y', $guide );
}

/**
 * The year an award was made, as a string, or '' when it cannot be known.
 *
 * The reviewed date is the editor saying "I checked this on this day", which
 * is the closest thing the data has to an award date. Nothing is invented:
 * a guide with neither a reviewed date nor a readable modified date shows
 * no year at all, and the badge reads "Oria Best of".
 */
function award_year( int $guide ): string {
	$raw = trim( (string) get_field( 'editorially_reviewed_date', $guide ) );
	$ts  = '' !== $raw ? strtotime( $raw ) : false;
	if ( ! $ts ) {
		$ts = (int) get_post_time( 'U', true, $guide );
	}
	return $ts ? gmdate( 'Y', $ts ) : '';
}

/** "Reviewed September 2026", or "Updated …" when nobody has said they reviewed it. */
function updated( int $guide ): string {
	$reviewed = '' !== trim( (string) get_field( 'editorially_reviewed_date', $guide ) );
	/* translators: %s: month and year */
	return sprintf( $reviewed ? __( 'Reviewed %s', 'oria' ) : __( 'Updated %s', 'oria' ), reviewed_month( $guide ) );
}

/** The 40-80 word answer near the top, for a reader (or a machine) in a hurry. */
function quick_answer( int $guide ): string {
	return trim( (string) get_field( 'quick_answer', $guide ) );
}

/**
 * "Choose X if …" -- the decision block. Rows name a listing and a
 * condition; the listing must be published or the row is dropped.
 *
 * @return list<array{listing:int, when:string}>
 */
function choose( int $guide ): array {
	$out = array();
	foreach ( (array) get_field( 'guide_choose', $guide ) as $row ) {
		$id   = (int) ( $row['listing'] ?? 0 );
		$when = trim( (string) ( $row['when'] ?? '' ) );
		if ( $id && '' !== $when && 'publish' === get_post_status( $id ) ) {
			$out[] = array( 'listing' => $id, 'when' => $when );
		}
	}
	return $out;
}

/**
 * The editor's highlighted picks -- "Best overall", "Best value" -- at most
 * three, in list order. Only entries the editor labelled; never forced.
 *
 * @param list<array> $entries from entries()
 * @return list<array>
 */
function spotlights( array $entries ): array {
	$out = array();
	foreach ( $entries as $e ) {
		if ( '' !== $e['spotlight'] ) {
			$out[] = $e;
		}
	}
	return array_slice( $out, 0, 3 );
}

/**
 * What a pick costs, in words. The editor's note wins ("From $35 — price
 * checked September 2026"); else the listing's own price_from; else an
 * honest "Check current pricing". Never a number nobody typed.
 */
function price_line( array $entry ): string {
	if ( '' !== $entry['price_note'] ) {
		return $entry['price_note'];
	}
	$from = price_from( (int) $entry['listing'] );
	/* translators: %s: price */
	return '' !== $from ? sprintf( __( 'From %s', 'oria' ), $from ) : __( 'Check current pricing', 'oria' );
}

/** The rebate label for a pick, or '' when the editor said nothing. */
function rebate_label( array $entry ): string {
	return REBATES[ $entry['rebate'] ] ?? '';
}

/** Does any pick in the list carry a rebate status? Decides the table column. */
function any_rebate( array $entries ): bool {
	foreach ( $entries as $e ) {
		if ( '' !== $e['rebate'] ) {
			return true;
		}
	}
	return false;
}

/** "60 min", from the listing's typical session, or ''. */
function duration( int $listing ): string {
	$m = (int) get_field( 'duration_min', $listing );
	/* translators: %d: minutes */
	return $m > 0 ? sprintf( __( '%d min', 'oria' ), $m ) : '';
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
			'spotlight'  => trim( (string) ( $row['spotlight'] ?? '' ) ),
			'price_note' => trim( (string) ( $row['price_note'] ?? '' ) ),
			'fact'       => trim( (string) ( $row['fact_label'] ?? '' ) ),
			'sessions'   => trim( (string) ( $row['sessions'] ?? '' ) ),
			'rebate'     => isset( REBATES[ (string) ( $row['rebate'] ?? '' ) ] ) ? (string) $row['rebate'] : '',
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
	return array(
		'label' => $pick['label'],
		'url'   => (string) get_permalink( $pick['guide'] ),
		'year'  => award_year( (int) $pick['guide'] ),
	);
}

/**
 * The badge markup. One shape everywhere so a reader learns it once.
 *
 * An award, not a filter. The old badge was a pale pill the size of a
 * category chip, which is what a category chip looks like -- so it read as
 * one. This is deep green with a cream disc, and the disc exists for a
 * reason: the Oria mark is painted in gold (see .badge--best__mark, masked
 * to logo-mark.svg) and gold on green loses its edges.
 *
 * Two lines of text, because "Best ice bath" alone says nothing about who
 * decided. "Oria Best of 2026" says a person did, in a particular year.
 *
 * The ✦ glyph stays inside the mark span: v4 masks it away, and a theme
 * without that rule still gets a mark rather than an empty circle.
 *
 * @param string $label       The award, e.g. "Best ice bath".
 * @param string $url         The guide it came from; omit for a plain badge.
 * @param string $extra_class Extra classes -- 'badge--best--sm' for a compact one.
 * @param string $year        Award year; omitted when the guide cannot date it.
 */
function badge_html( string $label, string $url = '', string $extra_class = '', string $year = '' ): string {
	$class   = trim( 'badge--best ' . $extra_class );
	$eyebrow = '' !== $year
		/* translators: %s: year */
		? sprintf( __( 'Oria Best of %s', 'oria' ), $year )
		: __( 'Oria Best of', 'oria' );

	$inner = '<span class="badge--best__disc" aria-hidden="true"><span class="badge--best__mark">&#10022;</span></span>'
		. '<span class="badge--best__text">'
		. '<span class="badge--best__eyebrow">' . esc_html( $eyebrow ) . '</span>'
		. '<span class="badge--best__title">' . esc_html( $label ) . '</span>'
		. '</span>';

	/* translators: 1: "Oria Best of 2026", 2: the award */
	$name = sprintf( __( '%1$s: %2$s', 'oria' ), $eyebrow, $label );

	if ( '' === $url ) {
		return '<span class="' . esc_attr( $class ) . '" role="img" aria-label="' . esc_attr( $name ) . '">' . $inner . '</span>';
	}

	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '" aria-label="'
		. esc_attr( sprintf( /* translators: %s: award name */ __( '%s — see the Best Of guide it comes from', 'oria' ), $name ) )
		. '">' . $inner . '</a>';
}

/**
 * The award's seal: the emblem in the theme's assets/img/badges, one per
 * award slug. An override label still shows its base award's seal.
 * 'webp' for the page, 'png' (1000px) for a practice to download.
 */
function seal_url( string $award, string $ext = 'webp', bool $fallback = true ): string {
	$award = isset( AWARDS[ $award ] ) ? $award : 'oria_pick';

	$file = static function ( string $name ) use ( $ext ): string {
		$rel = "assets/img/badges/{$name}.{$ext}";
		return file_exists( get_template_directory() . '/' . $rel )
			? get_template_directory_uri() . '/' . $rel
			: '';
	};

	$url = $file( $award );
	if ( '' !== $url ) {
		return $url;
	}

	/*
	 * A known award whose seal has not been drawn yet. The list of awards
	 * grows whenever a guide needs a label, and the artwork does not arrive
	 * in the same commit -- so displaying nothing is the common case, not
	 * the rare one, and the profile block used to collapse around the gap.
	 *
	 * The generic Oria pick seal stands in. It is not a lie: the badge
	 * beside it still reads "Best acupuncture", and the seal is the
	 * directory's mark rather than a claim of its own.
	 *
	 * $fallback is false where the real file is the point -- the download
	 * link on a profile, which should offer a practice the seal it actually
	 * won or offer nothing at all.
	 */
	return $fallback ? $file( 'oria_pick' ) : '';
}

/**
 * Every DISTINCT badge a listing holds, linked to its guide -- for the profile.
 *
 * A practice in three guides that each called it "Best value" holds one
 * badge, not three; the repeat says nothing a reader can use. The first
 * guide to award a label carries the link, and the Featured-by block
 * underneath still names every guide.
 */
function badges_html( int $listing ): string {
	$out  = '';
	$seen = array();
	foreach ( guides_for_listing( $listing ) as $row ) {
		$key = strtolower( trim( $row['label'] ) );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$out         .= badge_html( $row['label'], (string) get_permalink( $row['guide'] ), '', award_year( (int) $row['guide'] ) );
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
