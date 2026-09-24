<?php
/**
 * "Your first visit" — what actually happens, per kind of session.
 *
 * The thing that stops somebody booking is rarely the price. It is not
 * knowing what to wear, how early to turn up, whether they will be the only
 * beginner in the room, or how much of their clothing is coming off. Google
 * reviews almost never answer any of that, and the fields on a listing that
 * could answer it sit at nought to four per cent filled, because 8 of 441
 * listings have an owner at all.
 *
 * So the unit here is the KIND of session, not the business. What happens at
 * a first reformer class barely varies between studios; what varies is the
 * price and the parking. The page says as much in plain words rather than
 * implying it has rung round every studio.
 *
 * TWO SOURCES, JOINED HERE. The facts that already exist live in the Compare
 * registry, which has characterised 43 kinds of session for the comparison
 * tables: how long, how many people, whether there is touch, whether a
 * beginner needs anything first. The human half — what to wear, when to
 * arrive, what happens in order, and the worry nobody admits to — is written
 * in data/first-visit.json. Neither is duplicated: if the registry already
 * knows a session runs 45 to 60 minutes, that number is read, not retyped
 * into a second file where it could drift.
 *
 * WHAT MAY BE SAID. The room and the procedure, never the outcome — the same
 * rule the Compare registry states and for the same reason. "You lie face
 * down under a towel" is an observable fact about the hour. "It relieves
 * back pain" is a therapeutic claim, and the site makes none.
 *
 * NO FAQPage MARKUP HERE, deliberately. The specialty page already emits one
 * FAQPage block via Faq\for_term(), and a second on the same URL is
 * competing markup rather than twice the chance of being picked up. The
 * guide is plain prose with real headings, which is what answer engines
 * quote from anyway.
 *
 * @see data/compare.json for the per-session attributes this reads.
 * @see data/first-visit.json for the written half and the rules it follows.
 */

declare(strict_types=1);

namespace Oria\Core\FirstVisit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DATA_FILE = 'data/first-visit.json';

/** The written guides, keyed by specialty slug. */
function all(): array {
	static $data = null;

	if ( null === $data ) {
		$raw  = json_decode( (string) @file_get_contents( ORIA_CORE_DIR . DATA_FILE ), true );
		$data = ( is_array( $raw ) && isset( $raw['guides'] ) && is_array( $raw['guides'] ) ) ? $raw['guides'] : array();
	}

	return $data;
}

/**
 * The Compare registry, keyed by its own id.
 *
 * Read directly rather than through Compare's own loader so a change to how
 * that module routes pages cannot quietly empty this one.
 */
function registry(): array {
	static $by_id = null;

	if ( null === $by_id ) {
		$by_id = array();
		$raw   = json_decode( (string) @file_get_contents( ORIA_CORE_DIR . 'data/compare.json' ), true );

		foreach ( (array) ( $raw['experiences'] ?? array() ) as $entry ) {
			if ( isset( $entry['id'] ) ) {
				$by_id[ (string) $entry['id'] ] = $entry;
			}
		}
	}

	return $by_id;
}

/** Whether a slug has a written guide at all. */
function has( string $slug ): bool {
	return isset( all()[ $slug ] );
}

/**
 * The assembled guide for one slug, or null.
 *
 * @return array{
 *   title:string, lede:string, arrive:string, wear:string, bring:string,
 *   steps:list<string>, worry:array{q:string,a:string}, facts:list<array{label:string,value:string}>
 * }|null
 */
function for_slug( string $slug, string $label = '' ): ?array {
	$guide = all()[ $slug ] ?? null;
	if ( ! is_array( $guide ) ) {
		return null; // An unwritten kind of session says nothing rather than guessing.
	}

	/*
	 * The heading reads "your first {title} session", and a term name is
	 * not always a phrase that fits there -- the cold plunge specialty is
	 * called "Ice bath & cold plunge", which reads as two sessions. An
	 * entry may give its own `name` for that sentence; the term name is
	 * used when it does not.
	 */
	$title = (string) ( $guide['name'] ?? '' );
	if ( '' === $title ) {
		$title = '' !== $label ? $label : $slug;
	}

	return array(
		'title'  => $title,
		'lede'   => (string) ( $guide['lede'] ?? '' ),
		'arrive' => (string) ( $guide['arrive'] ?? '' ),
		'wear'   => (string) ( $guide['wear'] ?? '' ),
		'bring'  => (string) ( $guide['bring'] ?? '' ),
		'steps'  => array_values( array_filter( array_map( 'strval', (array) ( $guide['steps'] ?? array() ) ) ) ),
		'worry'  => array(
			'q' => (string) ( $guide['worry']['q'] ?? '' ),
			'a' => (string) ( $guide['worry']['a'] ?? '' ),
		),
		'facts'  => facts( (string) ( $guide['registry'] ?? $slug ) ),
	);
}

function for_term( ?\WP_Term $term ): ?array {
	if ( ! $term instanceof \WP_Term ) {
		return null;
	}

	$name = function_exists( '\Oria\Theme\tname' ) ? \Oria\Theme\tname( $term ) : $term->name;

	return for_slug( $term->slug, (string) $name );
}

/**
 * The guide for whatever a category page is currently showing.
 *
 * Resolution lives here rather than in a template because there are two
 * copies of that template -- the parent theme's and v4's -- and a rule
 * written twice is a rule that will eventually be written differently.
 *
 * A facet page is keyed by the facet, a bare category page
 * (/explore/perth/yoga/) by the category itself. Facets come in three
 * kinds and only two of them name a kind of session: `spec` (a specialty,
 * /spa/cold-plunge/) and `svc` (a service, /bodywork/massage/ -- which is
 * how the largest term on the site is filed, and reading `spec` alone
 * silently dropped it). An `aud` facet narrows who a session is for
 * without changing what happens in the room, so it gets nothing rather
 * than the same guide again one URL down.
 *
 * Suburb pages get nothing for the same reason: the same guide repeated
 * across forty suburb URLs is the near-duplicate content the facet guides
 * exist to avoid, and the visitor has already read it on the page above.
 *
 * @param array|null $facet As returned by PracticesIndex\facet().
 */
function for_context( ?\WP_Term $term, ?array $facet = null, bool $is_area = false ): ?array {
	if ( $is_area ) {
		return null;
	}

	if ( $facet ) {
		$slug = in_array( (string) ( $facet['key'] ?? '' ), array( 'spec', 'svc' ), true )
			? (string) ( $facet['value'] ?? '' )
			: '';

		if ( '' !== $slug && has( $slug ) ) {
			$term = function_exists( '\Oria\Core\PracticesIndex\facet_term' )
				? \Oria\Core\PracticesIndex\facet_term( $facet )
				: null;

			return $term ? for_term( $term ) : for_slug( $slug );
		}

		/*
		 * A facet that names no written session also rules out the parent
		 * category's guide: somebody on the deep-tissue page is not asking
		 * what a general massage is like.
		 */
		return null;
	}

	return for_term( $term );
}

/**
 * The at-a-glance row, read from the Compare registry.
 *
 * Only the attributes a first-timer is actually weighing up, and only the
 * ones this session has. A registry entry that says nothing about touch
 * produces no touch row, rather than a row saying "unknown" — which is how
 * every other empty value on this site behaves.
 *
 * @return list<array{label:string, value:string}>
 */
function facts( string $id ): array {
	$attrs = registry()[ $id ]['attributes'] ?? array();
	if ( ! is_array( $attrs ) ) {
		return array();
	}

	$wanted = array(
		'duration'   => __( 'How long', 'oria' ),
		'groupsize'  => __( 'How many people', 'oria' ),
		'experience' => __( 'Experience needed', 'oria' ),
		'position'   => __( 'Where you are', 'oria' ),
		'touch'      => __( 'Hands-on', 'oria' ),
		'oneonone'   => __( 'One to one available', 'oria' ),
		'price'      => __( 'Typical price', 'oria' ),
	);

	$out = array();
	foreach ( $wanted as $key => $label ) {
		$value = trim( (string) ( $attrs[ $key ] ?? '' ) );
		if ( '' !== $value ) {
			$out[] = array( 'label' => $label, 'value' => $value );
		}
	}

	return $out;
}

/**
 * Every slug with a guide, for linking between them.
 *
 * @return list<string>
 */
function slugs(): array {
	return array_keys( all() );
}
