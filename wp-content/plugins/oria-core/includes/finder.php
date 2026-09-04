<?php
/**
 * The Wellness Finder: four questions, then real matches from the directory.
 *
 * It invents nothing. Every result is a listing, event or article we already
 * hold, scored against the answers and shown with the reason it scored. The
 * scoring lives in weights() and the vocabulary in needs() / experiences(),
 * so tuning the tool later is editing two arrays rather than unpicking a
 * function.
 *
 * Two deliberate choices worth knowing about.
 *
 * First, the opening question asks what someone wants to *do*, not what is
 * wrong with them. "Hands-on bodywork" rather than "pain", "Time to myself"
 * rather than "burnout". A directory that maps symptoms onto modalities is
 * making a therapeutic claim on behalf of every practice in it, and that is
 * not ours to make. Two need-shaped options survive — stress & relaxation,
 * connection & community — because both describe an experience someone is
 * seeking rather than a condition they have.
 *
 * Second, experience type (one-to-one, class, workshop, retreat) is inferred
 * from the practice category, because no field records it. See experiences()
 * for the mapping and the schema note. It is scored low on purpose: a guess
 * should be able to order results, never to decide them.
 *
 * Built as a route rather than a WP page, for the same reason as the hub:
 * it ships in git and exists on production the moment the code lands.
 */

declare(strict_types=1);

namespace Oria\Core\Finder;

use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QUERY_VAR  = 'oria_finder';
const PATH       = 'wellness-finder';
const MATRIX_KEY = 'oria_finder_matrix_v2'; // 2: every practice term, not just the first.

/** How many of each kind of result to show. */
const LIMITS = array(
	'practices' => 3,
	'listings'  => 6,
	'events'    => 3,
	'articles'  => 3,
);

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\route', 10 );
	add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );

	add_filter( 'wpseo_title', __NAMESPACE__ . '\title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description' );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical' );
	/*
	 * og:url answers from the same source as the canonical.
	 *
	 * Seven modules here override wpseo_canonical to point a custom route
	 * at its real address. None of them overrode og:url, so Open Graph
	 * kept answering from the main query -- on a facet page that meant
	 * advertising the old /practice/{category}/ URL, which is now a 301
	 * and was never that page. Same question, same answer.
	 */
	add_filter( 'wpseo_opengraph_url', __NAMESPACE__ . '\canonical' );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\robots' );
	add_filter( 'wp_robots', __NAMESPACE__ . '\wp_robots_answered' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );

	foreach ( array( 'save_post_' . PostTypes\LISTING, 'deleted_post', 'edited_term', 'created_term' ) as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\flush_matrix' );
	}
}

/* ------------------------------------------------------------------ route */

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QUERY_VAR . '=1', 'top' );
}

function query_vars( array $vars ): array {
	$vars[] = QUERY_VAR;
	return $vars;
}

function is_finder(): bool {
	return (bool) get_query_var( QUERY_VAR );
}

/** A rule with no post parameters lands WordPress on the home query. */
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
	if ( ! is_finder() ) {
		return $template;
	}
	$found = locate_template( array( 'oria-wellness-finder.php' ) );
	return $found ?: $template;
}

/* -------------------------------------------------------------------- seo */

function title( $title ) {
	return is_finder() ? sprintf( 'Wellness Finder — what suits you, in Perth | %s', get_bloginfo( 'name' ) ) : $title;
}

function core_title( array $parts ): array {
	if ( is_finder() ) {
		$parts['title'] = __( 'Wellness Finder — what suits you, in Perth', 'oria' );
	}
	return $parts;
}

function description( $desc ) {
	if ( ! is_finder() ) {
		return $desc;
	}
	return __( 'Answer four questions and see which wellness practices, practitioners and events in Perth are worth a look — drawn from a directory checked by hand, with no booking fees.', 'oria' );
}

/** One canonical page however it was answered; the answers are not content. */
function canonical( $url ) {
	return is_finder() ? home_url( '/' . PATH . '/' ) : $url;
}

/**
 * The empty tool is the indexable page. An answered one is a view of data
 * that already has its own pages, so it stays out of the index — the thin
 * programmatic pages the brief warns about are exactly what this prevents.
 */
function robots( $robots ) {
	return ( is_finder() && answered() ) ? 'noindex, follow' : $robots;
}

function wp_robots_answered( array $r ): array {
	if ( is_finder() && answered() ) {
		$r['noindex'] = true;
		$r['follow']  = true;
	}
	return $r;
}

/* --------------------------------------------------------------- questions */

/**
 * The four steps, in order. Each option's key is what travels in the URL,
 * so keys are part of the contract and shouldn't be renamed lightly.
 *
 * @return array<string, array{label: string, hint: string, options: array<string, string>}>
 */
function questions(): array {
	$regions = array( 'any' => __( 'Anywhere in Perth', 'oria' ) );
	foreach ( region_terms() as $term ) {
		$regions[ $term->slug ] = tname( $term );
	}
	$regions['online'] = __( 'Online, from home', 'oria' );

	return apply_filters(
		'oria_finder_questions',
		array(
			'for'   => array(
				'label'   => __( 'What are you after?', 'oria' ),
				'hint'    => __( 'However you\'d put it. There\'s no wrong answer here.', 'oria' ),
				'options' => array(
					'calm'     => __( 'Stress & relaxation', 'oria' ),
					'move'     => __( 'Something physical', 'oria' ),
					'hands'    => __( 'Hands-on bodywork', 'oria' ),
					'solo'     => __( 'Time to myself', 'oria' ),
					'people'   => __( 'Connection & community', 'oria' ),
					'outdoors' => __( 'Time outdoors', 'oria' ),
					'creative' => __( 'Something creative', 'oria' ),
					'spirit'   => __( 'Spiritual & energy work', 'oria' ),
					'unsure'   => __( 'I\'m not sure yet', 'oria' ),
				),
			),
			'how'   => array(
				'label'   => __( 'How do you like to do things?', 'oria' ),
				'hint'    => __( 'Some people want a room to themselves. Some want a room full of people.', 'oria' ),
				'options' => array(
					'oneone'   => __( 'One to one', 'oria' ),
					'group'    => __( 'A small group', 'oria' ),
					'class'    => __( 'A regular class', 'oria' ),
					'workshop' => __( 'A workshop or course', 'oria' ),
					'retreat'  => __( 'A retreat or day escape', 'oria' ),
					'online'   => __( 'Online', 'oria' ),
					'any'      => __( 'No preference', 'oria' ),
				),
			),
			'where' => array(
				'label'   => __( 'Where suits you?', 'oria' ),
				'hint'    => __( 'We\'ll widen the search if there isn\'t much nearby.', 'oria' ),
				'options' => $regions,
			),
			'start' => array(
				'label'   => __( 'How would you like to begin?', 'oria' ),
				'hint'    => __( 'This just changes what we put first.', 'oria' ),
				'options' => array(
					'soon'     => __( 'Try something soon', 'oria' ),
					'event'    => __( 'Find an upcoming event', 'oria' ),
					'person'   => __( 'Find a practitioner', 'oria' ),
					'read'     => __( 'Read a bit first', 'oria' ),
					'nostart'  => __( 'I\'m not sure', 'oria' ),
				),
			),
		)
	);
}

/**
 * What each opening answer points at, in the directory's own vocabulary.
 *
 * Both lists are practice and specialty slugs that exist today. A slug that
 * disappears simply stops matching — nothing breaks — but a need with no
 * live slugs left will quietly return nothing, so this is worth a glance
 * whenever the taxonomy is reorganised.
 *
 * @return array<string, array{practices: list<string>, specialties: list<string>}>
 */
function needs(): array {
	return apply_filters(
		'oria_finder_needs',
		array(
			'calm'     => array(
				'practices'   => array( 'meditation', 'mindfulness', 'breathwork', 'sound', 'recovery' ),
				'specialties' => array( 'meditation', 'mindfulness', 'sound-healing', 'float-therapy', 'breathwork' ),
			),
			'move'     => array(
				'practices'   => array( 'yoga', 'fitness', 'allied' ),
				'specialties' => array( 'yoga', 'pilates', 'personal-training', 'barre', 'functional-fitness', 'mobility', 'tai-chi', 'dance-movement', 'aqua-fitness', 'exercise-physiology' ),
			),
			'hands'    => array(
				'practices'   => array( 'bodywork', 'recovery', 'allied' ),
				'specialties' => array( 'remedial-massage', 'deep-tissue', 'sports-massage', 'reflexology', 'lymphatic-drainage', 'bowen-therapy', 'pregnancy-massage', 'stretch-therapy', 'osteopathy', 'chiropractic' ),
			),
			'solo'     => array(
				'practices'   => array( 'sound', 'recovery', 'meditation', 'nature' ),
				'specialties' => array( 'float-therapy', 'infrared-sauna', 'cold-plunge', 'red-light-therapy', 'cryotherapy', 'meditation', 'compression' ),
			),
			'people'   => array(
				'practices'   => array( 'community', 'family', 'nature' ),
				'specialties' => array( 'mens-groups', 'womens-circles', 'wellness-meetups', 'support-groups', 'recovery-meetings', 'parenting-support' ),
			),
			'outdoors' => array(
				'practices'   => array( 'nature', 'fitness', 'retreats' ),
				'specialties' => array( 'eco-therapy', 'hiking', 'forest-bathing', 'outdoor-fitness', 'surf-therapy', 'equine-therapy' ),
			),
			'creative' => array(
				'practices'   => array( 'creative', 'sound' ),
				'specialties' => array( 'art-therapy', 'music-therapy', 'expressive-therapies', 'dance-movement' ),
			),
			'spirit'   => array(
				'practices'   => array( 'energy', 'sound', 'natural' ),
				'specialties' => array( 'reiki', 'energy-healing', 'chakra-balancing', 'crystal-healing', 'aura-clearing', 'spiritual-healing', 'shamanic-healing', 'pranic-healing', 'sound-healing' ),
			),
		)
	);
}

/**
 * Which practices tend to be delivered in which shape.
 *
 * SCHEMA NOTE. This is inference, not data: nothing on a listing records
 * whether it runs classes or sees people one to one. It holds well at the
 * category level — Retreats & day escapes really are retreats, Yoga &
 * movement really is classes — and badly for the mixed ones, where a
 * bodywork clinic might also run a monthly workshop. Scored at a third of
 * the weight of a practice match for that reason. If it ever needs to be
 * exact, the field to add is a multi-select 'delivery' on the listing, and
 * 130 listings would need backfilling by hand.
 *
 * @return array<string, list<string>>
 */
function experiences(): array {
	return apply_filters(
		'oria_finder_experiences',
		array(
			'oneone'   => array( 'bodywork', 'allied', 'nutrition', 'natural', 'energy', 'mindfulness', 'family', 'recovery' ),
			'group'    => array( 'community', 'family', 'nature', 'sound', 'meditation' ),
			'class'    => array( 'yoga', 'fitness', 'meditation', 'breathwork' ),
			'workshop' => array( 'breathwork', 'creative', 'sound', 'community' ),
			'retreat'  => array( 'retreats', 'nature' ),
		)
	);
}

/**
 * What each match is worth. Relevance decides the order; the tie-break is
 * a fraction of a point so a paid listing can only ever come first among
 * equals, never ahead of a better match.
 *
 * @return array<string, float>
 */
function weights(): array {
	return apply_filters(
		'oria_finder_weights',
		array(
			'practice'   => 5.0,
			'specialty'  => 4.0,
			'specialty2' => 1.0,  // a second matching specialty, and no more
			'region'     => 5.0,
			'experience' => 3.0,
			'online'     => 2.0,
			'featured'   => 0.2,  // tie-break only
			'claimed'    => 0.1,  // tie-break only
		)
	);
}

/* ----------------------------------------------------------------- answers */

/**
 * The answers, taken from the query string and checked against the
 * questions — anything unrecognised is dropped rather than trusted.
 *
 * @return array<string, string>
 */
function answers(): array {
	$out = array();
	foreach ( questions() as $key => $q ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( (string) $_GET[ $key ] ) ) : '';
		if ( '' !== $raw && isset( $q['options'][ $raw ] ) ) {
			$out[ $key ] = $raw;
		}
	}
	return $out;
}

/** Whether enough has been answered to show results at all. */
function answered(): bool {
	return isset( answers()['for'] );
}

/* ------------------------------------------------------------------ matrix */

/**
 * Every published listing reduced to the handful of facts the scoring
 * needs. Built in four queries rather than three per listing, and cached,
 * because this page is cheap to hit and will be hit by robots.
 *
 * A listing can sit in more than one practice category, so all of them are
 * kept for matching and the first is kept separately for grouping. Matching
 * on the first alone hid a yoga studio that also called itself fitness.
 *
 * @return array<int, array{practice: string, practices: list<string>, specialties: list<string>, region: string, suburb: string, format: string, status: string}>
 */
function matrix(): array {
	$cached = get_transient( MATRIX_KEY );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$ids = get_posts(
		array(
			'post_type'        => PostTypes\LISTING,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		)
	);
	$ids = array_map( 'intval', (array) $ids );
	if ( ! $ids ) {
		return array();
	}

	update_meta_cache( 'post', $ids );

	$rows = array();
	foreach ( $ids as $id ) {
		$rows[ $id ] = array(
			'practice'    => '',
			'practices'   => array(),
			'specialties' => array(),
			'region'      => '',
			'suburb'      => '',
			'format'      => (string) get_post_meta( $id, 'format', true ),
			'status'      => status_of( $id ),
		);
	}

	foreach ( array( Taxonomies\PRACTICE, Taxonomies\SPECIALTY, Taxonomies\AREA ) as $tax ) {
		$terms = wp_get_object_terms( $ids, $tax, array( 'fields' => 'all_with_object_id' ) );
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			$id = (int) $term->object_id;
			if ( ! isset( $rows[ $id ] ) ) {
				continue;
			}
			if ( Taxonomies\PRACTICE === $tax ) {
				$rows[ $id ]['practices'][] = $term->slug;
				if ( '' === $rows[ $id ]['practice'] ) {
					$rows[ $id ]['practice'] = $term->slug;
				}
			} elseif ( Taxonomies\SPECIALTY === $tax ) {
				$rows[ $id ]['specialties'][] = $term->slug;
			} elseif ( $term->parent ) {
				$rows[ $id ]['suburb'] = $term->slug;
				$parent                = get_term( $term->parent, Taxonomies\AREA );
				if ( $parent instanceof \WP_Term ) {
					$rows[ $id ]['region'] = $parent->slug;
				}
			} elseif ( '' === $rows[ $id ]['region'] ) {
				$rows[ $id ]['region'] = $term->slug;
			}
		}
	}

	set_transient( MATRIX_KEY, $rows, 6 * HOUR_IN_SECONDS );
	return $rows;
}

function flush_matrix(): void {
	delete_transient( MATRIX_KEY );
}

/** featured / claimed / unclaimed, for the tie-break only. */
function status_of( int $id ): string {
	if ( (string) get_post_meta( $id, 'admin_featured', true ) || 'featured' === (string) get_post_meta( $id, 'claim_status', true ) ) {
		return 'featured';
	}
	return 'claimed' === (string) get_post_meta( $id, 'claim_status', true ) ? 'claimed' : 'unclaimed';
}

/* ----------------------------------------------------------------- scoring */

/**
 * Score every listing against the answers.
 *
 * A listing has to match the topic — the practice or a specialty — to
 * appear at all. Somewhere convenient that does the wrong thing is not a
 * result, it is noise, and one bad row costs more trust than five good
 * rows earn.
 *
 * @param array<string, string> $answers
 * @return list<array{id: int, score: float, why: list<string>}>
 */
function score( array $answers ): array {
	$w      = weights();
	$need   = needs()[ $answers['for'] ?? '' ] ?? null;
	$exp    = experiences()[ $answers['how'] ?? '' ] ?? array();
	$where  = (string) ( $answers['where'] ?? '' );
	$online = 'online' === $where || 'online' === ( $answers['how'] ?? '' );

	$out = array();
	foreach ( matrix() as $id => $row ) {
		$score = 0.0;
		$why   = array();

		$hit_practice = $need && array_intersect( $row['practices'], $need['practices'] );
		$hit_specs    = $need ? array_values( array_intersect( $row['specialties'], $need['specialties'] ) ) : array();

		if ( $need && ! $hit_practice && ! $hit_specs ) {
			continue;
		}

		if ( $hit_practice ) {
			$score += $w['practice'];
		}
		if ( $hit_specs ) {
			$score += $w['specialty'] + ( count( $hit_specs ) > 1 ? $w['specialty2'] : 0 );
			$why[]  = specialty_name( $hit_specs[0] );
		}

		if ( $online ) {
			if ( in_array( $row['format'], array( 'online', 'both' ), true ) ) {
				$score += $w['online'];
				$why[]  = __( 'available online', 'oria' );
			} elseif ( 'online' === $where ) {
				// They asked for online and this isn't; it is not a match.
				continue;
			}
		}

		$near = ! $where || 'any' === $where || 'online' === $where || $row['region'] === $where;
		if ( $where && 'any' !== $where && 'online' !== $where && $row['region'] === $where ) {
			$score += $w['region'];
			$why[]  = region_name( $row['region'] );
		}

		if ( $exp && array_intersect( $row['practices'], $exp ) ) {
			$score += $w['experience'];
		}

		$score += $w[ $row['status'] ] ?? 0;

		$out[] = array(
			'id'    => $id,
			'score' => $score,
			'why'   => $why,
			'near'  => $near,
		);
	}

	usort(
		$out,
		static function ( array $a, array $b ): int {
			return $b['score'] <=> $a['score'] ?: strcmp( get_the_title( $a['id'] ), get_the_title( $b['id'] ) );
		}
	);
	return $out;
}

/* ----------------------------------------------------------------- results */

/**
 * Everything the results page shows, assembled once.
 *
 * @param array<string, string> $answers
 * @return array{unsure: bool, widened: bool, practices: list<array{term: \WP_Term, count: int}>, listings: list<array{id: int, near: bool, why: list<string>}>, events: list<int>, articles: list<int>}
 */
function results( array $answers ): array {
	$unsure = ! isset( $answers['for'] ) || 'unsure' === $answers['for'];

	if ( $unsure ) {
		return array(
			'unsure'    => true,
			'widened'   => false,
			'practices' => spread(),
			'listings'  => array(),
			'events'    => upcoming( array(), (string) ( $answers['where'] ?? '' ) ),
			'articles'  => articles( array() ),
		);
	}

	$scored  = score( $answers );
	$widened = false;

	// Nothing nearby is a reason to look further out, not to show an empty
	// page — the practice they want existing at all is the useful news.
	//
	// Only ever widen the area, and only when an area was actually chosen.
	// "Online" is a requirement rather than a place: quietly relaxing it
	// would offer someone a Fremantle studio under a notice about looking
	// further afield, which is two wrong answers in one sentence.
	$where = (string) ( $answers['where'] ?? '' );
	if ( ! $scored && $where && ! in_array( $where, array( 'any', 'online' ), true ) ) {
		$wider   = $answers;
		$wider['where'] = 'any';
		$scored  = score( $wider );
		$widened = (bool) $scored;
	}

	// Carry 'near' through so the page can say plainly which results are in
	// the area they asked for and which are further out. Quietly mixing the
	// two is how a directory loses someone's trust in a single scroll.
	$ids = array_map(
		static fn( array $r ): array => array( 'id' => $r['id'], 'near' => $r['near'], 'why' => $r['why'] ),
		array_slice( $scored, 0, LIMITS['listings'] )
	);

	// The practices worth naming are the ones the matches actually landed
	// in, biggest group first — not the whole map for that need.
	//
	// A listing in several categories is filed under the one the visitor
	// asked about. Filing a yoga studio that also lists fitness under
	// "Fitness & Movement" when they asked for something calm would name a
	// heading that has nothing to do with the question.
	$wanted = needs()[ $answers['for'] ?? '' ]['practices'] ?? array();
	$counts = array();
	foreach ( $scored as $row ) {
		$all  = matrix()[ $row['id'] ]['practices'] ?? array();
		$slug = '';
		foreach ( $all as $candidate ) {
			if ( in_array( $candidate, $wanted, true ) ) {
				$slug = $candidate;
				break;
			}
		}
		$slug = $slug ?: ( matrix()[ $row['id'] ]['practice'] ?? '' );
		if ( $slug ) {
			$counts[ $slug ] = ( $counts[ $slug ] ?? 0 ) + 1;
		}
	}
	arsort( $counts );

	$practices = array();
	foreach ( array_slice( array_keys( $counts ), 0, LIMITS['practices'] ) as $slug ) {
		$term = get_term_by( 'slug', $slug, Taxonomies\PRACTICE );
		if ( $term instanceof \WP_Term ) {
			$practices[] = array(
				'term'     => $term,
				'count'    => (int) $counts[ $slug ],
				'includes' => top_specialties( $slug, array_column( $scored, 'id' ) ),
			);
		}
	}

	return array(
		'unsure'    => false,
		'widened'   => $widened,
		'practices' => $practices,
		'listings'  => $ids,
		'events'    => upcoming( array_keys( $counts ), $where ),
		'articles'  => articles( array_column( $practices, 'term' ) ),
	);
}

/**
 * The modalities most common inside a practice category, named.
 *
 * This is what a category card says instead of a description, because no
 * practice term has one — and naming the three things actually on offer
 * is more use to a reader than a sentence we'd have written anyway.
 * Writing real term descriptions would be better still; until then this
 * is drawn from the listings themselves and cannot go stale.
 *
 * @param list<int> $within Listing IDs to look at; all of them when empty.
 * @return list<string>
 */
function top_specialties( string $practice, array $within = array() ): array {
	$counts = array();
	$only   = $within ? array_flip( array_map( 'intval', $within ) ) : null;

	foreach ( matrix() as $id => $row ) {
		if ( ! in_array( $practice, $row['practices'], true ) ) {
			continue;
		}
		if ( null !== $only && ! isset( $only[ $id ] ) ) {
			continue;
		}
		foreach ( $row['specialties'] as $slug ) {
			$counts[ $slug ] = ( $counts[ $slug ] ?? 0 ) + 1;
		}
	}

	arsort( $counts );
	$names = array();
	foreach ( array_slice( array_keys( $counts ), 0, 3 ) as $slug ) {
		$name = specialty_name( $slug );
		if ( $name ) {
			$names[] = $name;
		}
	}
	return $names;
}

/**
 * A deliberately mixed handful for someone who doesn't know yet — one
 * listing-bearing practice from each of five different corners of the
 * directory, so the answer to "I'm not sure" is breadth, not noise.
 *
 * @return list<array{term: \WP_Term, count: int}>
 */
function spread(): array {
	$picks = apply_filters( 'oria_finder_spread', array( 'meditation', 'bodywork', 'yoga', 'breathwork', 'sound' ) );

	$out = array();
	foreach ( $picks as $slug ) {
		$term = get_term_by( 'slug', $slug, Taxonomies\PRACTICE );
		if ( $term instanceof \WP_Term && $term->count > 0 ) {
			$out[] = array(
				'term'     => $term,
				'count'    => (int) $term->count,
				'includes' => top_specialties( $slug ),
			);
		}
	}
	return $out;
}

/**
 * Events still to come, in the practices we matched.
 *
 * @param list<string> $practice_slugs
 * @return list<int>
 */
function upcoming( array $practice_slugs, string $where ): array {
	$args = array(
		'post_type'      => PostTypes\EVENT,
		'post_status'    => 'publish',
		'posts_per_page' => LIMITS['events'],
		'fields'         => 'ids',
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => 'event_start',
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
	);

	$tax = array();
	if ( $practice_slugs ) {
		$tax[] = array(
			'taxonomy' => Taxonomies\PRACTICE,
			'field'    => 'slug',
			'terms'    => array_slice( $practice_slugs, 0, LIMITS['practices'] ),
		);
	}
	if ( $where && 'any' !== $where && 'online' !== $where ) {
		$tax[] = array(
			'taxonomy'         => Taxonomies\AREA,
			'field'            => 'slug',
			'terms'            => array( $where ),
			'include_children' => true,
		);
	}
	if ( $tax ) {
		$tax['relation'] = 'AND';
		$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	$ids = get_posts( $args );

	// An area with nothing on is common and not worth an empty section;
	// fall back to what's on anywhere before giving up.
	if ( ! $ids && count( $tax ) > 1 ) {
		unset( $args['tax_query'] );
		$args['tax_query'] = array( $tax[0] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$ids               = get_posts( $args );
	}

	return array_map( 'intval', (array) $ids );
}

/**
 * Journal articles about the practices we're recommending.
 *
 * @param list<\WP_Term> $practices
 * @return list<int>
 */
function articles( array $practices ): array {
	$wanted = array_map( static fn( \WP_Term $t ): int => $t->term_id, $practices );

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'fields'         => 'ids',
		)
	);

	if ( ! $wanted || ! function_exists( 'Oria\Theme\journal_practices' ) ) {
		return array_slice( array_map( 'intval', (array) $posts ), 0, LIMITS['articles'] );
	}

	$hits  = array();
	$rest  = array();
	foreach ( (array) $posts as $id ) {
		$ids = array_map( static fn( \WP_Term $t ): int => $t->term_id, \Oria\Theme\journal_practices( (int) $id ) );
		if ( array_intersect( $wanted, $ids ) ) {
			$hits[] = (int) $id;
		} else {
			$rest[] = (int) $id;
		}
	}

	return array_slice( array_merge( $hits, $rest ), 0, LIMITS['articles'] );
}

/* ------------------------------------------------------------------- names */

function tname( \WP_Term $term ): string {
	return function_exists( 'Oria\Theme\tname' ) ? \Oria\Theme\tname( $term ) : $term->name;
}

/** @return list<\WP_Term> */
function region_terms(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$terms = get_terms(
		array(
			'taxonomy'   => Taxonomies\AREA,
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	$cache = is_wp_error( $terms ) ? array() : $terms;
	return $cache;
}

function region_name( string $slug ): string {
	$term = get_term_by( 'slug', $slug, Taxonomies\AREA );
	return $term instanceof \WP_Term ? tname( $term ) : '';
}

function specialty_name( string $slug ): string {
	$term = get_term_by( 'slug', $slug, Taxonomies\SPECIALTY );
	return $term instanceof \WP_Term ? tname( $term ) : '';
}

/** The URL for a given set of answers — used by the back links and share. */
function url( array $answers = array() ): string {
	$base = home_url( '/' . PATH . '/' );
	return $answers ? add_query_arg( $answers, $base ) : $base;
}
