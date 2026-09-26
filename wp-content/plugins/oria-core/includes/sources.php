<?php
/**
 * Activity categories collected from public sources, and the rules that
 * keep a half-reviewed batch from showing.
 *
 * Five categories arrived at once (data/source-categories.json): running
 * groups, ocean swimming, pickleball, pottery and creative workshops, and
 * an expansion of the walking-groups service that already existed. Their
 * listings are researched from organisers' public pages, not written by an
 * owner, so three things here are different from an ordinary listing.
 *
 * A NEW CATEGORY IS PENDING UNTIL SOMEBODY LAUNCHES IT. The practice-page
 * robots filter guards facets with FACET_MIN but a plain category page not
 * at all, so a new sub-category with one published listing would have been
 * indexable the moment it existed. Pending means noindex, out of the Yoast
 * sitemap and out of the navigation. `wp oria sources launch <slug>` lifts
 * it; nothing lifts it automatically, because "ready" is an editor's call
 * and the brief was explicit that five listings is a target, not a rule.
 * Existing terms are never marked, so nothing already indexed changes.
 *
 * A GROUP IS NOT A PRACTICE. A running club has no practitioner and no
 * session to book, so kind gains 'group' with its own words ("How to join",
 * "Do you run this group?") and its own schema type. Schema would otherwise
 * have filed every running group under fitness as an ExerciseGym.
 *
 * PUBLISHING NEEDS A REVIEW. A listing that came from a batch cannot go
 * live until an editor has marked it reviewed and it has a way to join.
 * The publish guard holds it at Pending and says which is missing.
 *
 * Provenance is kept in underscored meta and never printed: which pages a
 * fact came from, when they were fetched, and the words that support it.
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

const DATA_FILE = 'data/source-categories.json';

/** Term meta: '1' while a category is waiting for editorial launch. */
const PENDING = 'oria_launch_pending';

/** Listing provenance. Underscored so it is never an ACF field or public. */
const BATCH    = '_oria_src_batch';
const KEY      = '_oria_src_key';
const CHECKED  = '_oria_src_checked';  // ISO 8601, the successful fetch the facts rest on.
const EVIDENCE = '_oria_src_evidence'; // JSON: [{field, url, at, excerpt}].
const URLS     = '_oria_src_urls';     // JSON: the pages consulted.
const REVIEW   = '_oria_review';       // scraped | reviewed | owner-confirmed.
const REVIEWER = '_oria_reviewer';
const PROPOSAL = '_oria_src_proposal'; // JSON, on an EXISTING listing: changes nobody applied.

const REVIEW_STATES = array( 'scraped', 'reviewed', 'owner-confirmed' );

const COST_STATES = array( 'free', 'paid', 'donation', 'mixed', 'unknown' );
const TRISTATE    = array( 'yes', 'no', 'unknown' );
const JOIN_METHODS = array( 'website', 'booking', 'register', 'turn-up', 'email', 'message', 'unknown' );

function bootstrap(): void {
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\robots', 30 );
	add_filter( 'wp_robots', __NAMESPACE__ . '\wp_robots', 30 );
	add_filter( 'wpseo_exclude_from_sitemap_by_term_ids', __NAMESPACE__ . '\sitemap_exclude' );
	add_filter( 'oria_publish_missing', __NAMESPACE__ . '\publish_missing', 10, 2 );
	add_action( 'acf/init', __NAMESPACE__ . '\fields' );
}

/* ---------------------------------------------------------------- the plan */

/** @return array<int, array<string, mixed>> */
function plan(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$path = ORIA_CORE_DIR . DATA_FILE;
	$json = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $cache = is_array( $json['categories'] ?? null ) ? array_values( $json['categories'] ) : array();
}

/** One category by its short key (running, ocean...) or its slug. */
function category( string $key ): ?array {
	foreach ( plan() as $row ) {
		if ( $key === ( $row['key'] ?? '' ) || $key === ( $row['slug'] ?? '' ) ) {
			return $row;
		}
	}
	return null;
}

/** The live term behind a plan row, whichever taxonomy it lives in. */
function term_for( array $row ): ?\WP_Term {
	$t = get_term_by( 'slug', (string) $row['slug'], (string) $row['taxonomy'] );
	return $t instanceof \WP_Term ? $t : null;
}

/* ------------------------------------------------------------- the launch gate */

function is_pending( $term ): bool {
	$id = $term instanceof \WP_Term ? (int) $term->term_id : (int) $term;
	return $id > 0 && '1' === (string) get_term_meta( $id, PENDING, true );
}

/** @return array<int, int> */
function pending_ids(): array {
	$ids = get_terms(
		array(
			'taxonomy'   => Taxonomies\PRACTICE,
			'hide_empty' => false,
			'fields'     => 'ids',
			'meta_key'   => PENDING, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
}

/** The practice archive being viewed is a pending category. */
function viewing_pending(): bool {
	if ( ! is_tax( Taxonomies\PRACTICE ) ) {
		return false;
	}
	$term = get_queried_object();
	return $term instanceof \WP_Term && is_pending( $term );
}

function robots( $robots ) {
	return viewing_pending() ? 'noindex, follow' : $robots;
}

/** Core's own tag too, for any request where Yoast is not the one printing. */
function wp_robots( array $robots ): array {
	if ( viewing_pending() ) {
		unset( $robots['index'] );
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}

/** @param array<int, int> $ids */
function sitemap_exclude( $ids ): array {
	return array_values( array_unique( array_merge( (array) $ids, pending_ids() ) ) );
}

/* ---------------------------------------------------------------- publishing */

/**
 * A batch listing needs an editor's review and a way to join before it may
 * go live. Listings that never came from a batch are untouched.
 *
 * @param array<int, string> $missing
 * @return array<int, string>
 */
function publish_missing( array $missing, int $listing ): array {
	if ( '' === (string) get_post_meta( $listing, BATCH, true ) ) {
		return $missing;
	}
	if ( ! in_array( (string) get_post_meta( $listing, REVIEW, true ), array( 'reviewed', 'owner-confirmed' ), true ) ) {
		$missing[] = __( 'an editorial review', 'oria' );
	}
	$method = (string) get_post_meta( $listing, 'join_method', true );
	$url    = (string) get_post_meta( $listing, 'join_url', true );
	if ( '' === $url && ! in_array( $method, array( 'turn-up', 'email' ), true ) ) {
		$missing[] = __( 'a way to join', 'oria' );
	}
	return $missing;
}

/* ------------------------------------------------------------ reading values */

/**
 * Joining details for a listing, only the ones that are actually known.
 *
 * Unknown stays absent rather than becoming a default: a blank price is not
 * "free", a blank schedule is not "open daily" and a blank beginner answer is
 * not "beginner friendly". The template prints what is here and nothing else.
 *
 * @return array<string, mixed>
 */
function joining( int $id ): array {
	$get = static fn( string $k ): string => trim( (string) get_post_meta( $id, $k, true ) );

	$rows = array();
	$n    = (int) get_post_meta( $id, 'activity_details', true );
	for ( $i = 0; $i < $n; $i++ ) {
		$label = trim( (string) get_post_meta( $id, "activity_details_{$i}_label", true ) );
		$value = trim( (string) get_post_meta( $id, "activity_details_{$i}_value", true ) );
		if ( '' !== $label && '' !== $value && 'unknown' !== strtolower( $value ) ) {
			$rows[] = array( 'label' => $label, 'value' => $value );
		}
	}

	$cost = $get( 'cost_status' );
	return array(
		'method'        => $get( 'join_method' ),
		'url'           => $get( 'join_url' ),
		'cost'          => in_array( $cost, COST_STATES, true ) ? $cost : '',
		'price_note'    => $get( 'price_note' ),
		'schedule'      => $get( 'schedule_text' ),
		'meeting'       => $get( 'meeting_point' ),
		'meeting_varies' => '1' === $get( 'meeting_varies' ),
		'beginner'      => $get( 'beginner' ),
		'come_alone'    => $get( 'come_alone' ),
		'details'       => $rows,
		'checked'       => $get( CHECKED ),
		'source_url'    => source_url( $id ),
	);
}

/** Whether this listing carries joining details worth a block of its own. */
function has_joining( int $id ): bool {
	$j = joining( $id );
	return '' !== $j['url'] || '' !== $j['cost'] || '' !== $j['schedule'] || $j['meeting_varies'] || $j['details'];
}

/** The organiser page the facts were checked against, for the "checked" line. */
function source_url( int $id ): string {
	$urls = json_decode( (string) get_post_meta( $id, URLS, true ), true );
	return is_array( $urls ) && $urls ? (string) reset( $urls ) : '';
}

/** Human words for a cost status. Never called for unknown. */
function cost_label( string $cost ): string {
	$map = array(
		'free'     => __( 'Free', 'oria' ),
		'paid'     => __( 'Paid', 'oria' ),
		'donation' => __( 'By donation', 'oria' ),
		'mixed'    => __( 'Free and paid options', 'oria' ),
	);
	return $map[ $cost ] ?? '';
}

/** The word for the join action, from the method the organiser uses. */
function join_label( string $method ): string {
	$map = array(
		'booking'  => __( 'Book a session', 'oria' ),
		'register' => __( 'Register to join', 'oria' ),
		'turn-up'  => __( 'See where to meet', 'oria' ),
		'email'    => __( 'Email the organiser', 'oria' ),
		'message'  => __( 'Message the organiser', 'oria' ),
	);
	return $map[ $method ] ?? __( 'How to join', 'oria' );
}

/* ------------------------------------------------------------------ fields */

/**
 * The joining details, as fields an editor can see and correct.
 *
 * Added only for what the listing model genuinely lacked: price_from is a
 * number with no basis, next_session is a single date, and nothing recorded
 * whether a meeting point moves. Kind covers group/provider/venue, audience
 * covers beginners, services covers tags -- none of those are duplicated.
 */
function fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$select = static fn( string $key, string $name, string $label, array $choices, string $instructions = '' ): array => array(
		'key'           => $key,
		'name'          => $name,
		'label'         => $label,
		'type'          => 'select',
		'choices'       => $choices,
		'allow_null'    => 1,
		'instructions'  => $instructions,
		'wrapper'       => array( 'width' => '33' ),
	);

	acf_add_local_field_group(
		array(
			'key'      => 'group_oria_joining',
			'title'    => 'Joining details',
			'position' => 'normal',
			'menu_order' => 5,
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => PostTypes\LISTING ) ) ),
			'fields'   => array(
				array(
					'key'          => 'field_oria_join_note',
					'label'        => '',
					'name'         => '',
					'type'         => 'message',
					'message'      => 'Leave anything the organiser does not say as blank or Unknown. A blank price is not free, a blank schedule is not open daily, and a friendly photo is not evidence that beginners are welcome.',
				),
				$select( 'field_oria_join_method', 'join_method', 'How people join', array_combine( JOIN_METHODS, array( 'Website', 'Book online', 'Register', 'Just turn up', 'Email', 'Message', 'Unknown' ) ) ),
				array(
					'key'     => 'field_oria_join_url',
					'name'    => 'join_url',
					'label'   => 'Join / booking link',
					'type'    => 'url',
					'wrapper' => array( 'width' => '67' ),
				),
				$select( 'field_oria_cost_status', 'cost_status', 'Cost', array_combine( COST_STATES, array( 'Free', 'Paid', 'Donation', 'Mixed', 'Unknown' ) ) ),
				array(
					'key'          => 'field_oria_price_note',
					'name'         => 'price_note',
					'label'        => 'Price and basis',
					'type'         => 'text',
					'instructions' => 'In AUD, only if the organiser publishes it, and say what it buys: per session, per course, membership.',
					'wrapper'      => array( 'width' => '67' ),
				),
				array(
					'key'          => 'field_oria_schedule_text',
					'name'         => 'schedule_text',
					'label'        => 'When it runs',
					'type'         => 'textarea',
					'rows'         => 2,
					'instructions' => 'Perth time. Session times, not business hours. "Varies -- see organiser" is an honest answer.',
				),
				array(
					'key'     => 'field_oria_meeting_point',
					'name'    => 'meeting_point',
					'label'   => 'Meeting point',
					'type'    => 'text',
					'wrapper' => array( 'width' => '67' ),
				),
				array(
					'key'          => 'field_oria_meeting_varies',
					'name'         => 'meeting_varies',
					'label'        => 'Meeting point moves',
					'type'         => 'true_false',
					'ui'           => 1,
					'instructions' => 'Shows "Locations vary -- check organiser" and no map pin.',
					'wrapper'      => array( 'width' => '33' ),
				),
				$select( 'field_oria_beginner', 'beginner', 'Suits beginners', array_combine( TRISTATE, array( 'Yes', 'No', 'Unknown' ) ), 'Only if the organiser says so.' ),
				$select( 'field_oria_come_alone', 'come_alone', 'Fine to come alone', array_combine( TRISTATE, array( 'Yes', 'No', 'Unknown' ) ), 'Only if the organiser says so.' ),
				array(
					'key'          => 'field_oria_activity_details',
					'name'         => 'activity_details',
					'label'        => 'Activity details',
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => 'Add a detail',
					'instructions' => 'Distance, pace, format, swimming ability, session level, materials -- one fact per row, in the organiser\'s own terms.',
					'sub_fields'   => array(
						array( 'key' => 'field_oria_ad_label', 'name' => 'label', 'label' => 'Detail', 'type' => 'text' ),
						array( 'key' => 'field_oria_ad_value', 'name' => 'value', 'label' => 'Value', 'type' => 'text' ),
					),
				),
			),
		)
	);
}
