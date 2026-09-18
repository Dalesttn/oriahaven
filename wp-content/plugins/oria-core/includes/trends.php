<?php
/**
 * Trends to Try -- wellness trends people meet on Instagram, explained.
 *
 * A trend page answers "I saw this online: what is it, is it for me, what
 * should I know, and where can I try it in Perth?" The Reel is the way in,
 * never the content: every section is Oria Haven's own writing, and the page
 * has to stand up with the Reel gone.
 *
 * THE RULES THIS FILE ENFORCES (from DESIGN/oria-haven-trend-to-try-claude-code-brief.md)
 *
 * - Nothing publishes itself. Candidates arrive as drafts; the publishing
 *   checklist below sends an incomplete trend straight back to draft,
 *   however it was published (edit screen, quick edit, REST).
 * - A Reel is only ever a validated instagram.com /reel/ or /p/ address. No
 *   embed HTML is accepted, nothing is downloaded, nothing is re-hosted. The
 *   theme builds Instagram's own embed from the address, on a click.
 * - Evidence position is editorial metadata, not a certification, and says
 *   so where it is shown.
 * - Sponsorship is disclosed and changes nothing else: the evidence and
 *   safety sections are the same fields either way.
 *
 * WHAT IT DOES NOT DO (deliberately, phase one)
 *
 * - Fetch anything from Instagram. The candidate tool checks the address's
 *   shape and whether it is already in use; whether the Reel is public and
 *   embeddable is the editor's check, recorded in reel_status with the date.
 *   An automated check would mean scraping, which Instagram's terms forbid.
 * - Save to My Oria: that lives on feature/my-oria, not on the live site.
 *
 * @package Oria
 */

declare(strict_types=1);

namespace Oria\Core\Trends;

use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const POST_TYPE     = PostTypes\TREND;
const REWRITE_V     = '1';
const CANDIDATE_URL = 'oria-trend-candidate';
const HUB_TITLE     = 'Wellness Trends Worth Understanding';

/** Evidence position: editorial, in words (never colour alone), with what it means. */
const EVIDENCE = array(
	'well_supported' => array( 'Well-supported', 'Several good-quality studies point the same way for the effects described here.' ),
	'promising'      => array( 'Promising', 'Early or small studies suggest something, but the evidence is not settled.' ),
	'experiential'   => array( 'Mainly experiential', 'People value how it feels; research on specific effects is limited.' ),
	'caution'        => array( 'Use caution', 'Claims run ahead of the evidence, or there are real risks to know about first.' ),
);

const BEGINNER = array(
	'yes'      => 'Beginner friendly',
	'guidance' => 'Beginner friendly, with guidance',
	'later'    => 'Better once you know the basics',
);

const TREND_STATUS = array(
	'emerging'    => 'Emerging',
	'popular'     => 'Popular',
	'established' => 'Established',
);

/** Editorial stages -- alongside WordPress's own draft/pending/publish, not instead of it. */
const STAGES = array(
	'candidate'       => 'Candidate',
	'researching'     => 'Researching',
	'awaiting_reel'   => 'Awaiting Reel approval',
	'ready_review'    => 'Ready for editorial review',
	'published'       => 'Published',
	'needs_freshness' => 'Needs freshness review',
	'archived'        => 'Archived',
);

const PERMISSION = array(
	'public_embed' => 'Official public embed',
	'requested'    => 'Permission requested',
	'granted'      => 'Permission granted',
	'owner'        => 'Owner content (the business’s own Reel)',
	'unavailable'  => 'Unavailable',
);

const REEL_STATUS = array(
	'unchecked'    => 'Not checked yet',
	'working'      => 'Working',
	'needs_review' => 'Needs review',
	'unavailable'  => 'Unavailable',
	'private'      => 'Private',
	'removed'      => 'Removed',
);

const CREATOR_TYPE = array(
	'practitioner' => 'Practitioner',
	'business'     => 'Business',
	'educator'     => 'Educator',
	'creator'      => 'Creator',
	'other'        => 'Other',
);

/** How recently a price must have been checked to be shown at all. */
const PRICE_FRESH_DAYS = 365;

function bootstrap(): void {
	add_action( 'acf/init', __NAMESPACE__ . '\fields' );
	add_action( 'init', __NAMESPACE__ . '\maybe_flush', 99 );
	add_action( 'pre_get_posts', __NAMESPACE__ . '\archive_query' );

	add_filter( 'acf/validate_value/name=reel_url', __NAMESPACE__ . '\validate_reel_url', 10, 4 );
	add_filter( 'acf/update_value/name=reel_url', __NAMESPACE__ . '\store_reel_url', 10, 3 );
	add_filter( 'acf/validate_value/name=primary_cta_url', __NAMESPACE__ . '\validate_internal_url', 10, 4 );
	add_filter( 'acf/validate_value/name=compare_url', __NAMESPACE__ . '\validate_internal_url', 10, 4 );
	add_filter( 'acf/load_field/name=trend_goals', __NAMESPACE__ . '\goal_choices' );

	// After the fields are saved, whichever way the post was published.
	add_action( 'wp_after_insert_post', __NAMESPACE__ . '\guard', 20, 4 );
	add_action( 'admin_notices', __NAMESPACE__ . '\blocked_notice' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\checklist_box' );

	add_filter( 'manage_' . POST_TYPE . '_posts_columns', __NAMESPACE__ . '\admin_columns' );
	add_action( 'manage_' . POST_TYPE . '_posts_custom_column', __NAMESPACE__ . '\admin_column', 10, 2 );

	add_action( 'admin_menu', __NAMESPACE__ . '\candidate_menu' );
	add_action( 'admin_menu', __NAMESPACE__ . '\report_menu' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\stats_box' );
	add_action( 'admin_post_oria_trend_candidate', __NAMESPACE__ . '\candidate_save' );

	add_filter( 'wpseo_title', __NAMESPACE__ . '\seo_title', 20 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\seo_description', 20 );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\hub_robots', 20 );
	add_filter( 'wp_robots', __NAMESPACE__ . '\hub_wp_robots', 20 );
}

/**
 * The hub stays out of the index until something is published on it: an
 * empty "being researched" page is exactly the placeholder the brief says
 * must not be indexed. Drafts themselves are never public at all.
 */
function hub_is_empty(): bool {
	return is_post_type_archive( POST_TYPE ) && 0 === (int) ( wp_count_posts( POST_TYPE )->publish ?? 0 );
}

function hub_robots( $robots ) {
	return hub_is_empty() ? 'noindex, follow' : $robots;
}

function hub_wp_robots( array $r ): array {
	if ( hub_is_empty() ) {
		$r['noindex'] = true;
		$r['follow']  = true;
	}
	return $r;
}

function maybe_flush(): void {
	if ( get_option( 'oria_trends_rewrite_v' ) !== REWRITE_V ) {
		flush_rewrite_rules();
		update_option( 'oria_trends_rewrite_v', REWRITE_V );
	}
}

function hub_url(): string {
	return (string) get_post_type_archive_link( POST_TYPE );
}

/** The hub lists every published trend, newest first. */
function archive_query( \WP_Query $q ): void {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_post_type_archive( POST_TYPE ) ) {
		return;
	}
	$q->set( 'posts_per_page', 60 );
	$q->set( 'orderby', 'date' );
	$q->set( 'order', 'DESC' );
}

/* ------------------------------------------------------------- the Reel */

/**
 * An Instagram address, reduced to the one form the site stores and embeds,
 * or null when it is not one.
 *
 * Only instagram.com, only a /reel/ or /p/ path (with /reels/ and /tv/ read
 * as their current equivalents), and only the shortcode -- query strings,
 * tracking parameters and the account prefix some shares carry are dropped.
 *
 * @return array{url: string, code: string}|null
 */
function normalise_reel_url( string $raw ): ?array {
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return null;
	}
	if ( ! preg_match( '#^https?://#i', $raw ) ) {
		$raw = 'https://' . $raw;
	}
	$parts = wp_parse_url( $raw );
	$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
	if ( ! in_array( $host, array( 'instagram.com', 'www.instagram.com', 'm.instagram.com' ), true ) ) {
		return null;
	}
	$path = (string) ( $parts['path'] ?? '' );
	// Optional "/{account}/" before the kind, as some share links carry.
	if ( ! preg_match( '#^/(?:[A-Za-z0-9._]{1,30}/)?(reel|reels|p|tv)/([A-Za-z0-9_-]{5,40})/?$#', $path, $m ) ) {
		return null;
	}
	$kind = in_array( $m[1], array( 'reel', 'reels' ), true ) ? 'reel' : 'p';
	return array(
		'url'  => 'https://www.instagram.com/' . $kind . '/' . $m[2] . '/',
		'code' => $m[2],
	);
}

/** Another trend already holding this Reel, or 0. */
function holder_of( string $code, int $except = 0 ): int {
	$found = get_posts(
		array(
			'post_type'      => POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post__not_in'   => $except ? array( $except ) : array(),
			'meta_query'     => array(
				array(
					'key'     => 'reel_url',
					'value'   => '/' . $code . '/',
					'compare' => 'LIKE',
				),
			),
		)
	);
	return (int) ( $found[0] ?? 0 );
}

/** @param mixed $valid */
function validate_reel_url( $valid, $value, $field, $input ) {
	if ( true !== $valid || '' === trim( (string) $value ) ) {
		return $valid;
	}
	$r = normalise_reel_url( (string) $value );
	if ( null === $r ) {
		return __( 'Only an Instagram Reel or post address is accepted, like https://www.instagram.com/reel/ABC123xyz/. Paste the address, never embed code.', 'oria' );
	}
	$post_id = (int) ( $_POST['post_ID'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- ACF has verified the form
	$other   = holder_of( $r['code'], $post_id );
	if ( $other ) {
		/* translators: %s: title of the trend already using the Reel */
		return sprintf( __( 'This Reel is already on “%s”.', 'oria' ), get_the_title( $other ) );
	}
	return $valid;
}

/** Stored in its one canonical form, so the duplicate check always matches. */
function store_reel_url( $value, $post_id, $field ) {
	$r = normalise_reel_url( (string) $value );
	return null === $r ? '' : $r['url'];
}

/** Calls to action and compare links stay on this site. */
function validate_internal_url( $valid, $value, $field, $input ) {
	if ( true !== $valid || '' === trim( (string) $value ) ) {
		return $valid;
	}
	$host = strtolower( (string) wp_parse_url( (string) $value, PHP_URL_HOST ) );
	$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	return $host === $home ? $valid : __( 'Use an Oria Haven address here, such as a category or compare page.', 'oria' );
}

/**
 * Everything the Reel component needs, or null when the trend has no Reel.
 *
 * `show` is whether the embed may be offered at all: an address, the
 * editor's confirmation that it may be embedded, and neither the Reel nor
 * the permission marked unavailable. When it may not, the component shows
 * the fallback text -- the page never depends on it.
 *
 * @return array<string, mixed>|null
 */
function reel( int $id ): ?array {
	$url = (string) get_field( 'reel_url', $id );
	$r   = '' !== $url ? normalise_reel_url( $url ) : null;
	if ( null === $r ) {
		return null;
	}
	$status = (string) get_field( 'reel_status', $id ) ?: 'unchecked';
	$perm   = (string) get_field( 'permission_status', $id );
	$gone   = in_array( $status, array( 'unavailable', 'private', 'removed' ), true );
	return array(
		'url'        => $r['url'],
		'code'       => $r['code'],
		'show'       => (bool) get_field( 'embed_allowed', $id ) && ! $gone && 'unavailable' !== $perm,
		'gone'       => $gone,
		'status'     => $status,
		'creator'    => trim( (string) get_field( 'creator_name', $id ) ),
		'handle'     => ltrim( trim( (string) get_field( 'creator_handle', $id ) ), '@' ),
		'profile'    => (string) get_field( 'creator_profile_url', $id ),
		'type'       => (string) get_field( 'creator_type', $id ),
		'where'      => trim( (string) get_field( 'creator_location', $id ) ),
		'credit'     => trim( (string) get_field( 'attribution_text', $id ) ),
		'headline'   => trim( (string) get_field( 'reel_headline', $id ) ),
		'reason'     => trim( (string) get_field( 'reel_reason', $id ) ),
		'takeaways'  => array_values( array_filter( array_map( static fn( $t ) => trim( (string) ( $t['text'] ?? '' ) ), (array) get_field( 'reel_takeaways', $id ) ) ) ),
		'fallback'   => trim( (string) get_field( 'reel_fallback_text', $id ) ),
		'owner'      => 'owner' === $perm,
	);
}

/* --------------------------------------------------------- the content */

function goal_choices( array $field ): array {
	$field['choices'] = array();
	if ( function_exists( '\Oria\Core\GoodFor\labels' ) ) {
		foreach ( \Oria\Core\GoodFor\labels() as $g ) {
			$field['choices'][ $g['slug'] ] = $g['label'];
		}
	}
	return $field;
}

/** @return list<array{slug:string,label:string,color:string}> the trend's goals, in the site's goal order. */
function goals( int $id ): array {
	$have = array_flip( array_map( 'strval', (array) get_field( 'trend_goals', $id ) ) );
	$out  = array();
	if ( function_exists( '\Oria\Core\GoodFor\labels' ) ) {
		foreach ( \Oria\Core\GoodFor\labels() as $g ) {
			if ( isset( $have[ $g['slug'] ] ) ) {
				$out[] = array( 'slug' => $g['slug'], 'label' => $g['label'], 'color' => $g['color'] );
			}
		}
	}
	return $out;
}

/** @return array{key:string,label:string,explain:string}|null */
function evidence( int $id ): ?array {
	$k = (string) get_field( 'evidence_position', $id );
	return isset( EVIDENCE[ $k ] ) ? array( 'key' => $k, 'label' => EVIDENCE[ $k ][0], 'explain' => EVIDENCE[ $k ][1] ) : null;
}

function beginner( int $id ): string {
	return BEGINNER[ (string) get_field( 'beginner_suitability', $id ) ] ?? '';
}

/**
 * The indicative Perth price, only while it is fresh: a price nobody has
 * checked in a year is not shown at all, rather than shown wrong.
 *
 * @return array{text:string, checked:string, range:bool}|null
 */
function price( int $id ): ?array {
	$from    = (float) get_field( 'typical_perth_price_from', $id );
	$to      = (float) get_field( 'typical_perth_price_to', $id );
	$checked = (string) get_field( 'price_checked', $id );
	$ts      = $checked ? strtotime( $checked ) : false;
	if ( $from <= 0 || ! $ts || $ts < time() - PRICE_FRESH_DAYS * DAY_IN_SECONDS ) {
		return null;
	}
	$text = $to > $from
		? sprintf( '$%s–$%s', number_format_i18n( $from ), number_format_i18n( $to ) )
		/* translators: %s: price */
		: sprintf( __( 'From $%s', 'oria' ), number_format_i18n( $from ) );
	return array( 'text' => $text, 'checked' => date_i18n( 'F Y', $ts ), 'range' => $to > $from );
}

/** @return list<array{title:string,publisher:string,url:string,accessed:string}> */
function sources( int $id ): array {
	$out = array();
	foreach ( (array) get_field( 'sources', $id ) as $row ) {
		$url = esc_url_raw( (string) ( $row['url'] ?? '' ) );
		$t   = trim( (string) ( $row['title'] ?? '' ) );
		if ( '' === $url || '' === $t ) {
			continue;
		}
		$out[] = array(
			'title'     => $t,
			'publisher' => trim( (string) ( $row['publisher'] ?? '' ) ),
			'url'       => $url,
			'accessed'  => (string) ( $row['accessed'] ?? '' ),
		);
	}
	return $out;
}

/** Published posts from a relationship field, in the editor's order. @return list<int> */
function related( int $id, string $field ): array {
	$ids = array_map( 'intval', (array) get_field( $field, $id, false ) );
	return array_values( array_filter( $ids, static fn( int $p ): bool => $p > 0 && 'publish' === get_post_status( $p ) ) );
}

/** @return list<\WP_Term> */
function practices( int $id ): array {
	$out = array();
	foreach ( (array) get_field( 'related_practices', $id, false ) as $tid ) {
		$t = get_term( (int) $tid, Taxonomies\PRACTICE );
		if ( $t instanceof \WP_Term ) {
			$out[] = $t;
		}
	}
	return $out;
}

/** Plain-text word count of a field, for the checklist. */
function words( int $id, string $field ): int {
	return str_word_count( wp_strip_all_tags( (string) get_field( $field, $id, false ) ) );
}

/** The date the trend was last reviewed: the evidence review, else the edit. */
function reviewed( int $id ): string {
	$d = (string) get_field( 'evidence_reviewed_date', $id );
	return $d ? date_i18n( 'j F Y', (int) strtotime( $d ) ) : get_the_modified_date( 'j F Y', $id );
}

/* ------------------------------------------------ the publishing checklist */

/**
 * What a trend needs before it may be public -- the brief's thin-content
 * rule, as a list the edit screen shows and the guard enforces.
 *
 * @return list<array{label:string, ok:bool}>
 */
function checklist( int $id ): array {
	$tips    = array_filter( (array) get_field( 'beginner_tips', $id ), static fn( $r ) => '' !== trim( (string) ( $r['tip'] ?? '' ) ) );
	$reel    = reel( $id );
	$no_reel = (bool) get_field( 'publish_without_reel', $id );
	$reel_ok = $reel
		&& (bool) get_field( 'embed_allowed', $id )
		&& '' !== $reel['creator']
		&& '' !== $reel['handle']
		&& in_array( $reel['status'], array( 'working' ), true )
		&& '' !== (string) get_field( 'permission_status', $id )
		&& '' !== $reel['fallback'];
	$items   = array(
		array( 'label' => __( 'Short answer, 25+ words', 'oria' ), 'ok' => words( $id, 'short_answer' ) >= 25 ),
		array( 'label' => __( 'What it is, 60+ words', 'oria' ), 'ok' => words( $id, 'what_it_is' ) >= 60 ),
		array( 'label' => __( 'What to expect', 'oria' ), 'ok' => words( $id, 'what_to_expect' ) >= 30 ),
		array( 'label' => __( 'Evidence position and limitations', 'oria' ), 'ok' => null !== evidence( $id ) && words( $id, 'limitations_uncertainties' ) >= 20 ),
		array( 'label' => __( 'Two or more beginner tips', 'oria' ), 'ok' => count( $tips ) >= 2 ),
		array( 'label' => __( 'Safety considerations', 'oria' ), 'ok' => words( $id, 'safety_considerations' ) >= 20 ),
		array( 'label' => __( 'A next step (call to action, category or listing)', 'oria' ), 'ok' => '' !== (string) get_field( 'primary_cta_url', $id ) || practices( $id ) || related( $id, 'related_listings' ) ),
		array( 'label' => __( 'At least one linked source', 'oria' ), 'ok' => count( sources( $id ) ) >= 1 ),
		array( 'label' => __( 'Evidence reviewed date', 'oria' ), 'ok' => '' !== (string) get_field( 'evidence_reviewed_date', $id ) ),
		array( 'label' => __( 'A checked Reel with creator, permission and fallback — or “publish without a Reel” ticked', 'oria' ), 'ok' => $reel_ok || ( $no_reel && ! $reel ) ),
	);
	if ( get_field( 'is_sponsored', $id ) ) {
		$items[] = array( 'label' => __( 'Sponsor name and disclosure', 'oria' ), 'ok' => '' !== trim( (string) get_field( 'sponsor_name', $id ) ) && '' !== trim( (string) get_field( 'sponsorship_disclosure', $id ) ) );
	}
	return $items;
}

/** @return list<string> labels of what is missing */
function missing( int $id ): array {
	return array_values( array_map( static fn( $i ) => $i['label'], array_filter( checklist( $id ), static fn( $i ) => ! $i['ok'] ) ) );
}

/**
 * Nothing incomplete goes public, whichever way it was published.
 *
 * Runs after the fields are saved (wp_after_insert_post), reads the
 * checklist, and puts a failing trend back to draft with a notice saying
 * what is missing. The same net catches quick edit and the REST API, which
 * never pass through the edit screen.
 */
function guard( int $post_id, \WP_Post $post, bool $update, $before ): void {
	if ( POST_TYPE !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	$gap = missing( $post_id );
	if ( ! $gap ) {
		return;
	}
	remove_action( 'wp_after_insert_post', __NAMESPACE__ . '\guard', 20 );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
	add_action( 'wp_after_insert_post', __NAMESPACE__ . '\guard', 20, 4 );
	set_transient( 'oria_trend_blocked_' . get_current_user_id(), array( 'id' => $post_id, 'gap' => $gap ), 120 );
}

function blocked_notice(): void {
	$key  = 'oria_trend_blocked_' . get_current_user_id();
	$data = get_transient( $key );
	if ( ! is_array( $data ) ) {
		return;
	}
	delete_transient( $key );
	echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Not published — this trend is back in draft.', 'oria' ) . '</strong> '
		. esc_html__( 'A trend page goes public only when it is complete. Still needed:', 'oria' ) . '</p><ul style="list-style:disc;margin-left:1.5em">';
	foreach ( (array) $data['gap'] as $g ) {
		echo '<li>' . esc_html( (string) $g ) . '</li>';
	}
	echo '</ul></div>';
}

function checklist_box(): void {
	add_meta_box(
		'oria_trend_checklist',
		__( 'Publishing checklist', 'oria' ),
		static function ( \WP_Post $post ): void {
			$items = checklist( (int) $post->ID );
			$done  = count( array_filter( $items, static fn( $i ) => $i['ok'] ) );
			echo '<p style="margin-top:0">' . esc_html( sprintf( /* translators: 1: done, 2: total */ __( '%1$d of %2$d done. Publishing is blocked until all are.', 'oria' ), $done, count( $items ) ) ) . '</p><ul style="margin:0">';
			foreach ( $items as $i ) {
				echo '<li style="display:flex;gap:6px;margin:0 0 6px"><span aria-hidden="true" style="color:' . ( $i['ok'] ? '#2e7d32' : '#b3261e' ) . '">' . ( $i['ok'] ? '&#10003;' : '&#10007;' ) . '</span><span>'
					. '<span class="screen-reader-text">' . esc_html( $i['ok'] ? __( 'Done:', 'oria' ) : __( 'Missing:', 'oria' ) ) . ' </span>' . esc_html( $i['label'] ) . '</span></li>';
			}
			echo '</ul>';
		},
		POST_TYPE,
		'side',
		'high'
	);
}

/* ------------------------------------------------------------- admin list */

function admin_columns( array $cols ): array {
	$out = array();
	foreach ( $cols as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'title' === $k ) {
			$out['oria_stage']     = __( 'Stage', 'oria' );
			$out['oria_reel']      = __( 'Reel', 'oria' );
			$out['oria_checklist'] = __( 'Checklist', 'oria' );
			$out['oria_due']       = __( 'Review due', 'oria' );
		}
	}
	return $out;
}

function admin_column( string $col, int $post_id ): void {
	switch ( $col ) {
		case 'oria_stage':
			echo esc_html( STAGES[ (string) get_field( 'editorial_stage', $post_id ) ] ?? '—' );
			break;
		case 'oria_reel':
			$r = reel( $post_id );
			if ( ! $r ) {
				echo esc_html( get_field( 'publish_without_reel', $post_id ) ? __( 'None (by choice)', 'oria' ) : __( 'No Reel yet', 'oria' ) );
				break;
			}
			$bad = $r['gone'] || in_array( $r['status'], array( 'needs_review', 'unchecked' ), true );
			$chk = (string) get_field( 'reel_last_checked', $post_id );
			echo '<span style="color:' . ( $bad ? '#b3261e' : '#2e7d32' ) . ';font-weight:600">' . esc_html( REEL_STATUS[ $r['status'] ] ?? $r['status'] ) . '</span>';
			echo $chk ? '<br><span style="color:#646970">' . esc_html( sprintf( /* translators: %s: date */ __( 'checked %s', 'oria' ), date_i18n( 'j M Y', (int) strtotime( $chk ) ) ) ) . '</span>' : '';
			break;
		case 'oria_checklist':
			$items = checklist( $post_id );
			$done  = count( array_filter( $items, static fn( $i ) => $i['ok'] ) );
			echo esc_html( $done . ' / ' . count( $items ) );
			break;
		case 'oria_due':
			$due = (string) get_field( 'editorial_review_due', $post_id );
			if ( $due ) {
				$ts = (int) strtotime( $due );
				echo '<span style="' . ( $ts < time() ? 'color:#b3261e;font-weight:600' : '' ) . '">' . esc_html( date_i18n( 'j M Y', $ts ) ) . '</span>';
			} else {
				echo '—';
			}
			break;
	}
}

/* ------------------------------------------------------ candidate tool */

function candidate_menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . POST_TYPE,
		__( 'Add a Reel candidate', 'oria' ),
		__( 'Add Reel candidate', 'oria' ),
		'edit_posts',
		CANDIDATE_URL,
		__NAMESPACE__ . '\candidate_page'
	);
}

function candidate_page(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$msg = isset( $_GET['oria_msg'] ) ? sanitize_key( wp_unslash( $_GET['oria_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only
	$ref = isset( $_GET['oria_ref'] ) ? (int) $_GET['oria_ref'] : 0; // phpcs:ignore WordPress.Security.NonceVerification -- display only
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Add a Reel candidate', 'oria' ); ?></h1>
		<p style="max-width:62ch"><?php esc_html_e( 'Paste one public Instagram Reel or post address. It is checked for shape and for whether a trend already uses it, then saved as a draft candidate — never published, and nothing is downloaded. Watch the whole Reel before you approve it.', 'oria' ); ?></p>
		<?php if ( 'invalid' === $msg ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'That is not an Instagram Reel or post address. Expected something like https://www.instagram.com/reel/ABC123xyz/.', 'oria' ); ?></p></div>
		<?php elseif ( 'duplicate' === $msg && $ref ) : ?>
			<div class="notice notice-warning"><p>
				<?php esc_html_e( 'This Reel is already on a trend:', 'oria' ); ?>
				<a href="<?php echo esc_url( (string) get_edit_post_link( $ref ) ); ?>"><?php echo esc_html( get_the_title( $ref ) ); ?></a>
			</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_trend_candidate">
			<?php wp_nonce_field( 'oria_trend_candidate' ); ?>
			<p>
				<label for="oria-reel-url"><strong><?php esc_html_e( 'Reel address', 'oria' ); ?></strong></label><br>
				<input type="url" id="oria-reel-url" name="reel_url" class="regular-text" required placeholder="https://www.instagram.com/reel/…">
			</p>
			<p>
				<label for="oria-reel-trend"><strong><?php esc_html_e( 'Working title (optional)', 'oria' ); ?></strong></label><br>
				<input type="text" id="oria-reel-trend" name="trend_title" class="regular-text" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. Japanese head spas', 'oria' ); ?>">
			</p>
			<?php submit_button( __( 'Save as draft candidate', 'oria' ) ); ?>
		</form>
	</div>
	<?php
}

function candidate_save(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'oria' ), 403 );
	}
	check_admin_referer( 'oria_trend_candidate' );
	$back = admin_url( 'edit.php?post_type=' . POST_TYPE . '&page=' . CANDIDATE_URL );
	$r    = normalise_reel_url( (string) wp_unslash( $_POST['reel_url'] ?? '' ) );
	if ( null === $r ) {
		wp_safe_redirect( add_query_arg( 'oria_msg', 'invalid', $back ) );
		exit;
	}
	$other = holder_of( $r['code'] );
	if ( $other ) {
		wp_safe_redirect( add_query_arg( array( 'oria_msg' => 'duplicate', 'oria_ref' => $other ), $back ) );
		exit;
	}
	$title = sanitize_text_field( (string) wp_unslash( $_POST['trend_title'] ?? '' ) );
	$id    = wp_insert_post(
		array(
			'post_type'   => POST_TYPE,
			'post_status' => 'draft',
			/* translators: %s: Instagram shortcode */
			'post_title'  => '' !== $title ? $title : sprintf( __( 'Reel candidate %s', 'oria' ), $r['code'] ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		wp_die( esc_html( $id->get_error_message() ) );
	}
	update_field( 'reel_url', $r['url'], $id );
	update_field( 'reel_status', 'unchecked', $id );
	update_field( 'editorial_stage', 'candidate', $id );
	wp_safe_redirect( (string) get_edit_post_link( (int) $id, 'url' ) );
	exit;
}

/* ------------------------------------------------ where trends surface */

/**
 * Every published trend, featured first, then newest. Only ever a handful,
 * so matching happens here in PHP rather than in meta queries.
 *
 * @return list<\WP_Post>
 */
function published(): array {
	static $all = null;
	if ( null !== $all ) {
		return $all;
	}
	$all = get_posts(
		array(
			'post_type'      => POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 60,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	usort( $all, static fn( $a, $b ): int => (int) (bool) get_field( 'featured_trend', $b->ID ) <=> (int) (bool) get_field( 'featured_trend', $a->ID ) );
	return $all;
}

/**
 * Published trends an editor tied to this category, or to its parent or a
 * child (a sauna trend filed under Spa belongs on the infrared sauna page).
 *
 * @return list<\WP_Post>
 */
function for_practice( \WP_Term $term ): array {
	$out = array();
	foreach ( published() as $t ) {
		foreach ( practices( (int) $t->ID ) as $p ) {
			if ( $p->term_id === $term->term_id
				|| term_is_ancestor_of( $p, $term, $term->taxonomy )
				|| term_is_ancestor_of( $term, $p, $term->taxonomy ) ) {
				$out[] = $t;
				continue 2;
			}
		}
	}
	return $out;
}

/**
 * Published trends for a guide: first any trend that names this guide as
 * related, then any sharing a category with it (a journal guide's
 * related_practices, a Best Of guide's guide_practice).
 *
 * @return list<\WP_Post>
 */
function for_guide( int $post_id ): array {
	$named = array();
	$by_cat = array();
	$terms = array();
	foreach ( (array) get_field( 'related_practices', $post_id, false ) as $tid ) {
		$t = get_term( (int) $tid, Taxonomies\PRACTICE );
		if ( $t instanceof \WP_Term ) {
			$terms[] = $t;
		}
	}
	if ( function_exists( '\Oria\Core\BestOf\practice' ) && PostTypes\BEST_OF === get_post_type( $post_id ) ) {
		$bp = \Oria\Core\BestOf\practice( $post_id );
		if ( $bp ) {
			$terms[] = $bp;
		}
	}
	foreach ( published() as $t ) {
		if ( in_array( $post_id, array_map( 'intval', (array) get_field( 'related_guides', $t->ID, false ) ), true ) ) {
			$named[] = $t;
			continue;
		}
		foreach ( $terms as $term ) {
			if ( in_array( $t, for_practice( $term ), true ) ) {
				$by_cat[] = $t;
				break;
			}
		}
	}
	return array_merge( $named, $by_cat );
}

/* ------------------------------------------------------------- reporting */

/**
 * The brief's success measures from the site's own counter (Analytics):
 * visits, the share who opened the Reel, and the share who took a next
 * step -- a listing, category, compare page, event or the main call to
 * action. First-party, no cookies, editors and crawlers not counted.
 *
 * @return array{views:int, reel:int, next:int, reel_rate:string, next_rate:string}
 */
function stats( int $id, int $days ): array {
	$f = static fn( string $t ): int => function_exists( '\Oria\Core\Analytics\total' ) ? \Oria\Core\Analytics\total( $id, $t, $days ) : 0;
	$v = $f( 'view' );
	$r = $f( 'reel' );
	$n = $f( 'next' );
	$pct = static fn( int $x ): string => $v > 0 ? round( 100 * $x / $v ) . '%' : '—';
	return array( 'views' => $v, 'reel' => $r, 'next' => $n, 'reel_rate' => $pct( $r ), 'next_rate' => $pct( $n ) );
}

function stats_box(): void {
	add_meta_box(
		'oria_trend_stats',
		__( 'How it is doing', 'oria' ),
		static function ( \WP_Post $post ): void {
			if ( 'publish' !== $post->post_status ) {
				echo '<p>' . esc_html__( 'Counts start once the trend is published.', 'oria' ) . '</p>';
				return;
			}
			foreach ( array( 30, 90 ) as $d ) {
				$s = stats( (int) $post->ID, $d );
				/* translators: %d: days */
				echo '<p style="margin:0 0 4px"><strong>' . esc_html( sprintf( __( 'Last %d days', 'oria' ), $d ) ) . '</strong></p><ul style="margin:0 0 10px">';
				echo '<li>' . esc_html( sprintf( /* translators: %s: number */ __( '%s visits', 'oria' ), number_format_i18n( $s['views'] ) ) ) . '</li>';
				echo '<li>' . esc_html( sprintf( /* translators: 1: number, 2: rate */ __( '%1$s opened the Reel (%2$s)', 'oria' ), number_format_i18n( $s['reel'] ), $s['reel_rate'] ) ) . '</li>';
				echo '<li>' . esc_html( sprintf( /* translators: 1: number, 2: rate */ __( '%1$s took a next step (%2$s)', 'oria' ), number_format_i18n( $s['next'] ), $s['next_rate'] ) ) . '</li></ul>';
			}
			echo '<p class="description">' . esc_html__( 'Counted by the site itself; editors and crawlers excluded. GA4 has the detail.', 'oria' ) . '</p>';
		},
		POST_TYPE,
		'side',
		'default'
	);
}

function report_menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . POST_TYPE,
		__( 'Trends report', 'oria' ),
		__( 'Report', 'oria' ),
		'edit_posts',
		'oria-trend-report',
		__NAMESPACE__ . '\report_page'
	);
}

function report_page(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$days = isset( $_GET['days'] ) && 90 === (int) $_GET['days'] ? 90 : 30; // phpcs:ignore WordPress.Security.NonceVerification -- display only
	$rows = published();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Trends report', 'oria' ); ?></h1>
		<p style="max-width:70ch"><?php esc_html_e( 'Whether each trend page is useful: how many people opened the Reel, and how many went on to a listing, category, compare page, event or the main call to action. Time on page is not counted — it says little about usefulness.', 'oria' ); ?></p>
		<p>
			<?php foreach ( array( 30, 90 ) as $d ) : ?>
				<a class="button<?php echo $d === $days ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'days', $d ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d: days */ __( 'Last %d days', 'oria' ), $d ) ); ?></a>
			<?php endforeach; ?>
		</p>
		<?php if ( ! $rows ) : ?>
			<p><?php esc_html_e( 'No published trends yet.', 'oria' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:960px">
				<thead><tr>
					<th><?php esc_html_e( 'Trend', 'oria' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Visits', 'oria' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Opened Reel', 'oria' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Took a next step', 'oria' ); ?></th>
					<th><?php esc_html_e( 'Reel', 'oria' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $t ) : ?>
					<?php $s = stats( (int) $t->ID, $days ); $r = reel( (int) $t->ID ); ?>
					<tr>
						<td><a href="<?php echo esc_url( (string) get_edit_post_link( $t->ID ) ); ?>"><?php echo esc_html( get_the_title( $t ) ); ?></a></td>
						<td style="text-align:right"><?php echo esc_html( number_format_i18n( $s['views'] ) ); ?></td>
						<td style="text-align:right"><?php echo esc_html( number_format_i18n( $s['reel'] ) . ' (' . $s['reel_rate'] . ')' ); ?></td>
						<td style="text-align:right"><?php echo esc_html( number_format_i18n( $s['next'] ) . ' (' . $s['next_rate'] . ')' ); ?></td>
						<td><?php echo esc_html( $r ? ( REEL_STATUS[ $r['status'] ] ?? $r['status'] ) : __( 'None', 'oria' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Visits count people, not editors or crawlers. A Reel open is counted once per page view; so is a next step. Saves to My Oria and email signups will join this once those exist on the live site.', 'oria' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/* --------------------------------------------------------------------- SEO */

function seo_title( $title ) {
	if ( is_post_type_archive( POST_TYPE ) ) {
		return HUB_TITLE . ' | Oria Haven';
	}
	return $title;
}

function seo_description( $desc ) {
	if ( is_post_type_archive( POST_TYPE ) ) {
		return __( 'Seen a wellness trend in your feed? What it is, what to expect, what the evidence says and where you may be able to try it around Perth.', 'oria' );
	}
	if ( is_singular( POST_TYPE ) && '' === trim( (string) $desc ) ) {
		$a = trim( wp_strip_all_tags( (string) get_field( 'short_answer', (int) get_the_ID() ) ) );
		return '' !== $a ? wp_html_excerpt( $a, 155, '…' ) : $desc;
	}
	return $desc;
}

/* ------------------------------------------------------------------ fields */

function fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$k = static fn( string $n ): string => 'field_oria_trend_' . $n;
	$tab = static fn( string $n, string $label ): array => array( 'key' => $k( 'tab_' . $n ), 'label' => $label, 'type' => 'tab', 'placement' => 'top' );
	$text = static fn( string $n, string $label, string $ins = '', array $x = array() ): array => array_merge( array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'text', 'instructions' => $ins ), $x );
	$area = static fn( string $n, string $label, string $ins = '', int $rows = 4 ): array => array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'textarea', 'instructions' => $ins, 'rows' => $rows, 'new_lines' => '' );
	$rich = static fn( string $n, string $label, string $ins = '' ): array => array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'wysiwyg', 'instructions' => $ins, 'toolbar' => 'basic', 'media_upload' => 0, 'tabs' => 'visual' );
	$sel  = static fn( string $n, string $label, array $choices, string $ins = '' ): array => array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'select', 'choices' => $choices, 'allow_null' => 1, 'instructions' => $ins );
	$rel  = static fn( string $n, string $label, array $types, string $ins = '', int $max = 0 ): array => array( 'key' => $k( $n ), 'name' => $n, 'label' => $label, 'type' => 'relationship', 'post_type' => $types, 'filters' => array( 'search' ), 'return_format' => 'id', 'max' => $max ?: '', 'instructions' => $ins );

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_trend',
			'title'    => 'Trend to Try',
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => POST_TYPE ) ) ),
			'position' => 'acf_after_title',
			'style'    => 'seamless',
			'fields'   => array(
				// ------------------------------------------------ Reel
				$tab( 'reel', 'Reel & creator' ),
				array( 'key' => $k( 'reel_help' ), 'type' => 'message', 'label' => '', 'message' => 'Paste the public address of one Instagram Reel or post — never embed code. Watch the whole Reel before approving it: its claims, its tone and who made it. Oria never downloads or re-hosts it; the page shows Instagram’s own embed only when a visitor presses “Watch Reel”.' ),
				$text( 'reel_url', 'Reel address', 'https://www.instagram.com/reel/…  (checked and stored in a standard form)', array( 'type' => 'url' ) ),
				$text( 'creator_name', 'Creator name' ),
				$text( 'creator_handle', 'Instagram handle', 'Without the @' ),
				$text( 'creator_profile_url', 'Creator profile address', '', array( 'type' => 'url' ) ),
				$sel( 'creator_type', 'Creator type', CREATOR_TYPE ),
				$text( 'creator_location', 'Creator location', 'Where they are based, if known' ),
				$text( 'attribution_text', 'Attribution line', 'Optional. Shown under the Reel, e.g. “Reel by Jane Doe, sauna host in Fremantle.”' ),
				array( 'key' => $k( 'embed_allowed' ), 'name' => 'embed_allowed', 'label' => 'Embedding confirmed', 'type' => 'true_false', 'ui' => 1, 'message' => 'I have checked this Reel is public and offers Instagram’s embed option.' ),
				$sel( 'permission_status', 'Permission', PERMISSION ),
				array_merge( $area( 'permission_notes', 'Permission notes (private)', 'Never shown on the site.', 3 ) ),
				$sel( 'reel_status', 'Reel status', REEL_STATUS, 'Set to Working once you have watched it on its page today.' ),
				array( 'key' => $k( 'reel_last_checked' ), 'name' => 'reel_last_checked', 'label' => 'Last checked', 'type' => 'date_time_picker', 'display_format' => 'j M Y g:i a', 'return_format' => 'Y-m-d H:i:s' ),
				$area( 'reel_fallback_text', 'If the Reel cannot be shown', 'What the Reel shows, in two or three sentences, so the page still makes sense without it.', 3 ),
				$text( 'reel_headline', 'Headline for the Reel', 'Oria’s words, e.g. “A calm first sauna, step by step”' ),
				$area( 'reel_reason', 'Why Oria picked it', 'One sentence.', 2 ),
				array(
					'key' => $k( 'reel_takeaways' ), 'name' => 'reel_takeaways', 'label' => 'Takeaways', 'type' => 'repeater', 'max' => 3, 'layout' => 'table', 'button_label' => 'Add takeaway',
					'instructions' => 'Two or three, in Oria’s own words.',
					'sub_fields' => array( array( 'key' => $k( 'takeaway_text' ), 'name' => 'text', 'label' => 'Takeaway', 'type' => 'text' ) ),
				),
				array( 'key' => $k( 'publish_without_reel' ), 'name' => 'publish_without_reel', 'label' => 'Publish without a Reel', 'type' => 'true_false', 'ui' => 1, 'message' => 'A deliberate editorial decision: this page goes out with no Reel.' ),

				// ------------------------------------------------ Content
				$tab( 'content', 'The page' ),
				$area( 'short_answer', 'Short answer', 'The direct answer, near the top. 40–70 words. Search and answer engines quote this.', 3 ),
				$rich( 'what_it_is', 'What is it?' ),
				$rich( 'why_trending', 'Why is it appearing everywhere?' ),
				$rich( 'what_to_expect', 'What to expect when you try it' ),
				$rich( 'reel_context', 'What the Reel gets right — and what needs context' ),
				$rich( 'possible_benefits', 'What people may find beneficial', 'Cautious wording, each claim linked to its source close by.' ),
				$rich( 'limitations_uncertainties', 'What remains uncertain or overstated' ),
				array(
					'key' => $k( 'beginner_tips' ), 'name' => 'beginner_tips', 'label' => 'Beginner tips', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add tip',
					'sub_fields' => array( array( 'key' => $k( 'tip' ), 'name' => 'tip', 'label' => 'Tip', 'type' => 'text' ) ),
				),
				$rich( 'safety_considerations', 'Safety considerations', 'Sourced. Pregnancy, heart conditions, medication, heat or cold sensitivity — whatever genuinely applies.' ),
				$rich( 'who_may_need_professional_advice', 'Who should talk to a health professional first' ),
				array(
					'key' => $k( 'questions_to_ask_provider' ), 'name' => 'questions_to_ask_provider', 'label' => 'Questions to ask before booking', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add question',
					'sub_fields' => array( array( 'key' => $k( 'question' ), 'name' => 'question', 'label' => 'Question', 'type' => 'text' ) ),
				),
				$area( 'oria_verdict', 'Oria’s verdict', 'Short and balanced.', 3 ),

				// ------------------------------------------------ Practical
				$tab( 'practical', 'Facts & price' ),
				array( 'key' => $k( 'trend_goals' ), 'name' => 'trend_goals', 'label' => 'How it may help you feel', 'type' => 'checkbox', 'layout' => 'horizontal', 'choices' => array(), 'return_format' => 'value', 'instructions' => 'The site’s own goals, the same chips as the directory.' ),
				$sel( 'beginner_suitability', 'Beginner suitability', BEGINNER ),
				$sel( 'trend_status', 'Trend status', TREND_STATUS ),
				$text( 'typical_duration', 'Typical session', 'e.g. 20–45 minutes' ),
				array( 'key' => $k( 'typical_perth_price_from' ), 'name' => 'typical_perth_price_from', 'label' => 'Typical Perth price from ($)', 'type' => 'number', 'min' => 0 ),
				array( 'key' => $k( 'typical_perth_price_to' ), 'name' => 'typical_perth_price_to', 'label' => 'to ($)', 'type' => 'number', 'min' => 0 ),
				array( 'key' => $k( 'price_checked' ), 'name' => 'price_checked', 'label' => 'Price checked on', 'type' => 'date_picker', 'display_format' => 'j M Y', 'return_format' => 'Y-m-d', 'instructions' => 'The price only shows while this is under a year old.' ),
				$area( 'price_notes', 'Price notes', '', 2 ),
				array( 'key' => $k( 'featured_trend' ), 'name' => 'featured_trend', 'label' => 'Feature on the hub', 'type' => 'true_false', 'ui' => 1 ),

				// ------------------------------------------------ Evidence
				$tab( 'evidence', 'Evidence & review' ),
				$sel( 'evidence_position', 'Evidence position', array_map( static fn( $e ) => $e[0], EVIDENCE ), 'Editorial judgement, not a certification — the page says so.' ),
				array( 'key' => $k( 'evidence_reviewed_date' ), 'name' => 'evidence_reviewed_date', 'label' => 'Evidence reviewed on', 'type' => 'date_picker', 'display_format' => 'j M Y', 'return_format' => 'Y-m-d' ),
				array( 'key' => $k( 'editorial_review_due' ), 'name' => 'editorial_review_due', 'label' => 'Review again by', 'type' => 'date_picker', 'display_format' => 'j M Y', 'return_format' => 'Y-m-d' ),
				array(
					'key' => $k( 'sources' ), 'name' => 'sources', 'label' => 'Sources', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add source',
					'instructions' => 'Primary research, Australian health bodies, professional organisations. Every health claim needs one.',
					'sub_fields' => array(
						array( 'key' => $k( 'src_title' ), 'name' => 'title', 'label' => 'Title', 'type' => 'text' ),
						array( 'key' => $k( 'src_pub' ), 'name' => 'publisher', 'label' => 'Publisher / journal', 'type' => 'text' ),
						array( 'key' => $k( 'src_url' ), 'name' => 'url', 'label' => 'Address', 'type' => 'url' ),
						array( 'key' => $k( 'src_date' ), 'name' => 'accessed', 'label' => 'Checked on', 'type' => 'date_picker', 'display_format' => 'j M Y', 'return_format' => 'Y-m-d' ),
					),
				),
				$sel( 'editorial_stage', 'Editorial stage', STAGES ),
				$area( 'editor_todo', 'Editor to-do (private)', 'Never shown on the site.', 4 ),

				// ------------------------------------------------ Relationships
				$tab( 'links', 'Where to try it' ),
				$text( 'primary_cta_label', 'Main call to action', 'e.g. Find saunas in Perth' ),
				$text( 'primary_cta_url', 'Call to action address', 'An Oria Haven page', array( 'type' => 'url' ) ),
				array( 'key' => $k( 'related_practices' ), 'name' => 'related_practices', 'label' => 'Related categories', 'type' => 'taxonomy', 'taxonomy' => Taxonomies\PRACTICE, 'field_type' => 'multi_select', 'add_term' => 0, 'save_terms' => 0, 'load_terms' => 0, 'return_format' => 'id' ),
				$rel( 'related_listings', 'Perth listings that offer it', array( PostTypes\LISTING ), 'Only places you have confirmed actually offer this — never from the name alone.', 8 ),
				$rel( 'related_events', 'Related events', array( PostTypes\EVENT ), '', 4 ),
				$text( 'compare_label', 'Compare link label', 'e.g. Sauna vs ice bath vs float' ),
				$text( 'compare_url', 'Compare link address', '', array( 'type' => 'url' ) ),
				$rel( 'related_guides', 'Related guides', array( 'post', PostTypes\BEST_OF ), '', 4 ),
				$rel( 'related_products', 'Related products', array( 'oria_product' ), 'Only when genuinely useful.', 3 ),
				$rel( 'related_apps', 'Related apps', array( 'wellness_app' ), '', 3 ),
				$rel( 'related_trends', 'Related trends', array( POST_TYPE ), '', 3 ),

				// ------------------------------------------------ Disclosure
				$tab( 'disclosure', 'Disclosure' ),
				array( 'key' => $k( 'is_sponsored' ), 'name' => 'is_sponsored', 'label' => 'Sponsored', 'type' => 'true_false', 'ui' => 1, 'instructions' => 'Sponsorship never changes the evidence or safety sections.' ),
				$text( 'sponsor_name', 'Sponsor' ),
				$area( 'sponsorship_disclosure', 'Sponsorship disclosure', 'Shown at the top of the page.', 2 ),
				array( 'key' => $k( 'contains_affiliate_links' ), 'name' => 'contains_affiliate_links', 'label' => 'Contains affiliate links', 'type' => 'true_false', 'ui' => 1 ),
			),
		)
	);
}
