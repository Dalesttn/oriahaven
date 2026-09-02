<?php
/**
 * Experience DNA: a six-bar profile of what a session is like, and the
 * "feels like this" neighbours that follow from it.
 *
 * WHERE THE NUMBERS COME FROM. Not from anyone's opinion of the business.
 * Every bar is read from the Compare registry's score for the KIND of
 * session -- yoga, float, sound healing -- which is the modality the
 * directory has actually characterised, and then narrowed by the three
 * facts the listing itself carries: its price band, its group size and
 * whether it is tagged for beginners. A reiki practitioner inherits reiki's
 * profile; if they list one-to-one, Social drops; if they are $$$$,
 * Affordability drops. Every bar traces to a stored fact.
 *
 * The registry's own rule carries over unchanged: every attribute describes
 * THE ROOM, never the outcome. That is why the bar is "Quiet" and not
 * "Calm", and why there is no "Spiritual" -- neither is observable about a
 * room, and scoring them for a business nobody has visited would be the
 * fiction the rest of the site refuses. Six honest bars beat seven.
 *
 * A listing whose kind of session is not in the registry -- a naturopath,
 * a facial, a dietitian -- gets no profile rather than a guessed one.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Dna;

use Oria\Core\Compare;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The registry dimensions a "feels like" distance is measured over. */
const VECTOR = array( 'intensity', 'movement', 'quiet', 'guidance', 'social' );

/**
 * The fully scored experiences: the top-level set.
 *
 * The registry also holds sub-variants (seven kinds of massage, six of
 * yoga) scored only on their own group's schema, with no 'social'. They are
 * right for comparing massage with massage and wrong here, where the point
 * is comparing across kinds.
 *
 * @return array<int, array<string, mixed>>
 */
function top_level(): array {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out = array();
	foreach ( Compare\experiences() as $e ) {
		$a = (array) ( $e['attributes'] ?? array() );
		if ( isset( $a['social'] ) && null !== $a['social'] ) {
			$out[] = $e;
		}
	}
	return $out;
}

/**
 * The term slug an experience's registry url stands for.
 *
 * The url is the registry's KEY, not a link -- listings_for() parses it the
 * same way. /practices/{cat}/{facet}/ names a facet, /practices/{cat}/ a
 * category, /perth/{specialty}/ a specialty.
 *
 * @return array{0: string, 1: string} [kind, slug]
 */
function key_of( array $e ): array {
	$u = (string) ( $e['url'] ?? '' );
	if ( preg_match( '~^/practices?/([^/]+)/([^/]+)/$~', $u, $m ) ) {
		return array( 'facet', $m[2] );
	}
	if ( preg_match( '~^/practices?/([^/]+)/$~', $u, $m ) ) {
		return array( 'category', $m[1] );
	}
	if ( preg_match( '~^/perth/([^/]+)/$~', $u, $m ) ) {
		return array( 'facet', $m[1] );
	}
	return array( '', '' );
}

/**
 * The practice category a facet-keyed experience lives under.
 *
 * /practices/{cat}/{facet}/ says so itself; /perth/{specialty}/ has to ask
 * the specialty's declared home. Empty when neither answers.
 */
function home_of( array $e ): string {
	$u = (string) ( $e['url'] ?? '' );
	if ( preg_match( '~^/practices?/([^/]+)/[^/]+/$~', $u, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '~^/perth/([^/]+)/$~', $u, $m ) && function_exists( '\Oria\Core\PracticesIndex\specialty_home' ) ) {
		return \Oria\Core\PracticesIndex\specialty_home( $m[1] );
	}
	return '';
}

/**
 * Which kind of session this listing is.
 *
 * The listing's practice category is its identity; its specialty and
 * service tags are what it offers. The first version let any facet tag
 * win, and a yoga studio that also runs mat pilates became "pilates", a
 * massage clinic with an infrared sauna became "infrared sauna". The rule
 * now:
 *
 *   - A facet wins only when it is a SPECIALISATION of the listing's own
 *     category -- a float studio filed under spa is a float, not a day spa,
 *     because float's home is spa.
 *   - A facet from some other category is a side-offering and loses to the
 *     category's own experience.
 *   - With no category-level experience at all (fitness, recovery, natural)
 *     the facet stands, preferring one that lives in a category the listing
 *     is filed under.
 *
 * Memoised per request because the single template asks more than once.
 */
function experience_for( int $post_id ): ?array {
	static $memo = array();
	if ( array_key_exists( $post_id, $memo ) ) {
		return $memo[ $post_id ];
	}

	$slugs = static function ( string $tax ) use ( $post_id ): array {
		$t = get_the_terms( $post_id, $tax );
		return is_array( $t ) ? array_map( static fn( $x ) => $x->slug, $t ) : array();
	};
	$facets = array_flip( array_merge( $slugs( 'specialty' ), $slugs( 'service' ) ) );
	$cats   = array_flip( $slugs( 'practice' ) );

	$cat_match    = null;   // the experience keyed by one of the listing's categories
	$cat_slug     = '';
	$facet_matches = array();

	foreach ( top_level() as $e ) {
		list( $kind, $slug ) = key_of( $e );
		if ( 'category' === $kind && null === $cat_match && isset( $cats[ $slug ] ) ) {
			$cat_match = $e;
			$cat_slug  = $slug;
		} elseif ( 'facet' === $kind && isset( $facets[ $slug ] ) ) {
			$facet_matches[] = $e;
		}
	}

	if ( null !== $cat_match ) {
		foreach ( $facet_matches as $f ) {
			if ( home_of( $f ) === $cat_slug ) {
				return $memo[ $post_id ] = $f;   // a specialisation of what it is
			}
		}
		return $memo[ $post_id ] = $cat_match;   // side-offerings lose
	}

	foreach ( $facet_matches as $f ) {
		if ( isset( $cats[ home_of( $f ) ] ) ) {
			return $memo[ $post_id ] = $f;   // a facet in a category it is filed under
		}
	}
	return $memo[ $post_id ] = $facet_matches[0] ?? null;
}

/* --------------------------------------------------- text -> 1..5 scales */

/** The registry writes touch as a sentence; the bar needs a number. */
function touch_scale( string $touch ): int {
	$t = strtolower( $touch );
	if ( '' === $t || 0 === strpos( $t, 'none' ) && false === strpos( $t, 'or ' ) ) {
		return 1;
	}
	if ( false !== strpos( $t, 'whole point' ) || false !== strpos( $t, 'works on you' ) ) {
		return 5;
	}
	if ( false !== strpos( $t, 'most treatments' ) || false !== strpos( $t, 'hands-on' ) ) {
		return 4;
	}
	if ( false !== strpos( $t, 'spotting' ) || false !== strpos( $t, 'placed hands' ) ) {
		return 3;
	}
	// "Optional adjustments", "occasional form corrections", "none, or light…"
	return 2;
}

/**
 * How little you need to know to walk in.
 *
 * The listing's own beginners tag is the strongest signal and wins
 * outright. Otherwise the registry's experience note: they all begin
 * "None", and the ones that go on to ask for an intro class or a
 * screening call sit one step lower.
 */
function beginner_scale( string $note, bool $tagged ): int {
	if ( $tagged ) {
		return 5;
	}
	$n = strtolower( $note );
	if ( '' === $n ) {
		return 3;
	}
	if ( false !== strpos( $n, 'intro class' ) || false !== strpos( $n, 'screen' ) || false !== strpos( $n, 'graded' ) ) {
		return 4;
	}
	return 0 === strpos( $n, 'none' ) ? 5 : 3;
}

/** More dollar signs, less affordable. The band is the site's own. */
function afford_scale( string $band ): int {
	$map = array(
		'free' => 5,
		'$'    => 5,
		'$$'   => 4,
		'$$$'  => 2,
		'$$$$' => 1,
	);
	return $map[ strtolower( trim( $band ) ) ] ?? 3;
}

/* --------------------------------------------------------------- the bars */

/**
 * The six bars for a listing, or an empty array when it has no profile.
 *
 * @return array<int, array{key: string, label: string, score: int, word: string}>
 */
function bars( int $post_id ): array {
	$e = experience_for( $post_id );
	if ( null === $e ) {
		return array();
	}
	$a = (array) $e['attributes'];

	/*
	 * The listing's own facts narrow the modality's numbers. These are the
	 * three fields a crawl or an owner actually fills in; nothing here is
	 * inferred from a blurb.
	 */
	$band   = (string) get_field( 'price_band', $post_id );
	$group  = (string) get_field( 'group_size', $post_id );
	$tagged = false;
	foreach ( (array) get_the_terms( $post_id, 'audience' ) as $t ) {
		if ( $t instanceof \WP_Term && 'beginners' === $t->slug ) {
			$tagged = true;
		}
	}

	$social = (int) $a['social'];
	if ( 'one-to-one' === $group || 'solo' === $group ) {
		$social = 1;
	} elseif ( 'small' === $group ) {
		$social = min( $social, 2 );
	} elseif ( 'class' === $group ) {
		$social = max( $social, 3 );
	}

	$afford = afford_scale( '' !== $band ? $band : (string) ( $a['price'] ?? '' ) );

	$rows = array(
		array( 'physical', __( 'Physical', 'oria' ), (int) $a['intensity'] ),
		array( 'quiet', __( 'Quiet', 'oria' ), (int) $a['quiet'] ),
		array( 'social', __( 'Social', 'oria' ), $social ),
		array( 'handson', __( 'Hands-on', 'oria' ), touch_scale( (string) ( $a['touch'] ?? '' ) ) ),
		array( 'afford', __( 'Affordability', 'oria' ), $afford ),
		array( 'beginner', __( 'Beginner friendly', 'oria' ), beginner_scale( (string) ( $a['experience'] ?? '' ), $tagged ) ),
	);

	$out = array();
	foreach ( $rows as $r ) {
		$s     = max( 1, min( 5, $r[2] ) );
		$out[] = array(
			'key'   => $r[0],
			'label' => $r[1],
			'score' => $s,
			'word'  => Compare\scale_word( $s ),
		);
	}
	return $out;
}

/**
 * One line assembled from the bars, in room-language.
 *
 * "Quiet, small and private, hands-off." Every clause is a bar reading; the
 * words are about the room. "Restorative", "calming", "intimate" would be
 * the same information with a claim attached, and a directory that has
 * never been to a business does not get to make it.
 */
function summary( array $bars ): string {
	$by = array();
	foreach ( $bars as $b ) {
		$by[ $b['key'] ] = (int) $b['score'];
	}
	if ( ! $by ) {
		return '';
	}
	/*
	 * Three bands, not two thresholds. The first version only spoke at the
	 * extremes, so a kind that sits at 3 on everything -- which is yoga,
	 * the most common kind on the site -- produced no sentence at all. A
	 * middling reading is still a reading: "some music, moderately active,
	 * in a group" tells a first-timer what to expect as surely as "quiet"
	 * does. Every phrase still describes the room.
	 *
	 * Ordered by how much each bar tends to decide someone's choice, then
	 * cut to three so the line stays a line.
	 */
	$phrase = static function ( string $key, int $v ): string {
		switch ( $key ) {
			case 'quiet':
				return $v >= 4 ? __( 'quiet', 'oria' ) : ( $v <= 2 ? __( 'music-led', 'oria' ) : __( 'some music', 'oria' ) );
			case 'physical':
				return $v >= 4 ? __( 'physically demanding', 'oria' ) : ( $v <= 2 ? __( 'still', 'oria' ) : __( 'moderately active', 'oria' ) );
			case 'social':
				if ( $v <= 1 ) {
					return __( 'on your own', 'oria' );
				}
				return $v <= 2 ? __( 'small and private', 'oria' ) : ( $v >= 4 ? __( 'sociable', 'oria' ) : __( 'in a group', 'oria' ) );
			case 'handson':
				// Only the extremes say anything worth a clause.
				return $v >= 4 ? __( 'hands-on', 'oria' ) : ( $v <= 1 ? __( 'hands-off', 'oria' ) : '' );
		}
		return '';
	};

	$bits = array();
	foreach ( array( 'quiet', 'physical', 'social', 'handson' ) as $k ) {
		$p = $phrase( $k, (int) ( $by[ $k ] ?? 3 ) );
		if ( '' !== $p ) {
			$bits[] = $p;
		}
	}
	$bits = array_slice( $bits, 0, 3 );
	if ( ! $bits ) {
		return '';
	}
	return ucfirst( implode( __( ', ', 'oria' ), $bits ) ) . '.';
}

/* -------------------------------------------------------- feels like this */

/**
 * The nearest other kinds of session, by distance over the scored vector.
 *
 * This is the part that answers "I don't want yoga, I want something that
 * feels like yoga": tai chi and mat pilates sit close to it in the space,
 * which a category browse would never surface. Plain Euclidean over five
 * 1-5 dimensions; the registry is small enough that nothing cleverer earns
 * its keep.
 *
 * @return array<int, array{label: string, url: string, distance: float}>
 */
function feels_like( array $from, int $n = 3 ): array {
	$fa = (array) $from['attributes'];
	$out = array();
	/*
	 * Touch joins the scored dimensions here. Without it massage and
	 * infrared sauna sit at exactly the same point -- both still, quiet,
	 * solitary, unguided -- and one lists the other as its closest match at
	 * distance zero, which is the one answer nobody asking would accept.
	 * Whether a practitioner works on you is the axis that separates them.
	 */
	$ft = touch_scale( (string) ( $fa['touch'] ?? '' ) );
	foreach ( top_level() as $e ) {
		if ( $e['id'] === $from['id'] ) {
			continue;
		}
		$ea = (array) $e['attributes'];
		$d  = ( $ft - touch_scale( (string) ( $ea['touch'] ?? '' ) ) ) ** 2;
		foreach ( VECTOR as $k ) {
			$d += ( (int) $fa[ $k ] - (int) $ea[ $k ] ) ** 2;
		}
		$out[] = array(
			'label'    => (string) $e['label'],
			'url'      => Compare\experience_url( $e ),
			'distance' => sqrt( $d ),
		);
	}
	usort( $out, static fn( array $a, array $b ): int => $a['distance'] <=> $b['distance'] );
	return array_slice( $out, 0, $n );
}
