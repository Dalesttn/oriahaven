<?php
/**
 * "Find your kind of yoga" — one row per thing somebody might actually be
 * after, with a real count and a link to exactly those listings.
 *
 * The problem this solves: a category page is a list plus filters, and
 * filters only help someone who already knows what to filter for. Somebody
 * who has never done yoga does not know to look for "Yin". The rows put the
 * intents on the page in words.
 *
 * WHY THERE IS NO "BEST".
 *
 * The obvious version of this ranks one practice per intent by Google
 * rating. Run against the real data, the best yoga studio in Perth comes out
 * as a sauna with a 5.0 from three reviews, ahead of a studio holding 4.9
 * from a hundred and ninety-two. A 5.0 from three is a rounding artefact,
 * not a verdict.
 *
 * The deeper reason is that it would be Oria Haven asserting that a named
 * real business is the best at something, to a business that never asked to
 * be ranked, on a number somebody else measured. The directory shows ratings
 * labelled "on Google" and keeps them out of its own structured data for
 * exactly this reason. A "Best for Vinyasa" heading crosses that line.
 *
 * So the rows count and link. They never rank. Counts are facts; a count is
 * also the more useful thing, because somebody choosing a yoga class wants
 * options, not our pick.
 *
 * THRESHOLDS. A row needs three listings, the same floor as everything else
 * here. Audience rows need more than that — they need the category to have
 * been *checked*, because a row saying "6 beginner-friendly" on a set where
 * only half were looked at implies the other half are not, and that is a
 * claim about businesses nobody verified. Audience\coverage() is the gate.
 */

declare(strict_types=1);

namespace Oria\Core\Intents;

use Oria\Core\Audience;
use Oria\Core\PostTypes;
use Oria\Core\Services;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MIN = 3;

/**
 * The fragment every row link ends with.
 *
 * Without it, clicking a row reloads the page at the top and leaves the
 * visitor seventeen hundred pixels above the practices they just filtered
 * for — the filter applied, the result invisible. The fragment makes the
 * browser land on the results with no script involved.
 */
const ANCHOR = '#dirResults';

/** A filtered URL for one row, pointed at the results. */
function row_url( string $base, string $key, string $value ): string {
	return add_query_arg( $key, $value, $base ) . ANCHOR;
}

/** Published listings in a practice term and its children. */
function listings_in( \WP_Term $practice ): array {
	return get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy'         => Taxonomies\PRACTICE,
					'field'            => 'term_id',
					'terms'            => $practice->term_id,
					'include_children' => true,
				),
			),
		)
	);
}

/**
 * The rows for a category page.
 *
 * @return list<array{label: string, count: int, url: string, kind: string}>
 */
/**
 * @param list<int>|null $ids The set to count over. Defaults to the whole
 *                            category; a city page passes its own, or every
 *                            count on the page describes a different set of
 *                            listings from the one on screen.
 */
function for_practice( \WP_Term $practice, ?array $ids = null ): array {
	$ids = null === $ids ? listings_in( $practice ) : array_values( $ids );

	if ( count( $ids ) < MIN ) {
		return array();
	}

	$base = (string) get_term_link( $practice );
	$rows = array();

	/* ---------------------------------------------------------- audience */

	foreach ( Audience\vocabulary() as $slug => $row ) {
		$c = Audience\coverage( $ids, $slug );

		// publishable means: 80% of the category checked, and 3+ of them yes.
		// Not "3 happen to be tagged".
		if ( ! $c['publishable'] ) {
			continue;
		}

		$rows[] = array(
			'label' => $row['name'],
			'count' => (int) $c['yes'],
			'url'   => row_url( $base, 'aud', $slug ),
			'kind'  => 'audience',
		);
	}

	/* ---------------------------------------------------------- services */

	$tally = array();
	foreach ( $ids as $id ) {
		$terms = wp_get_object_terms( (int) $id, Services\TAXONOMY );
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			$tally[ $term->slug ] = array(
				'name'  => wp_specialchars_decode( $term->name, ENT_QUOTES ),
				'count' => ( $tally[ $term->slug ]['count'] ?? 0 ) + 1,
			);
		}
	}

	/*
	 * A service naming the category back at the reader is not an intent.
	 * "Yoga" inside Yoga & Pilates is the page they are already on, and it
	 * would always be the biggest row.
	 */
	$self = array( $practice->slug, sanitize_title( wp_specialchars_decode( $practice->name, ENT_QUOTES ) ) );

	/*
	 * Only services that belong to this category.
	 *
	 * Without this the rows list whatever else the listed practices happen
	 * to do: "Find your kind of massage & bodywork" offered Naturopathy and
	 * Physiotherapy, and the mind page offered Yin yoga. Every one of those
	 * counts was true and none of them was the question the heading asked.
	 *
	 * services.json already carries the mapping, and it has to be read in
	 * both directions. Services are filed against the most specific practice
	 * that fits — Meditation is filed under "meditation", not under its
	 * parent "mind" — so a parent page has to accept its children's
	 * services, or Mind & Mental Wellbeing shows no rows at all despite
	 * holding meditation, breathwork and mindfulness. Ancestors count too,
	 * so a service filed under "mind" still shows on the meditation page
	 * beneath it.
	 */
	$mine = array( $practice->slug );

	foreach ( (array) get_ancestors( $practice->term_id, Taxonomies\PRACTICE, 'taxonomy' ) as $anc_id ) {
		$anc = get_term( (int) $anc_id, Taxonomies\PRACTICE );
		if ( $anc instanceof \WP_Term ) {
			$mine[] = $anc->slug;
		}
	}

	foreach ( (array) get_term_children( $practice->term_id, Taxonomies\PRACTICE ) as $kid_id ) {
		$kid = get_term( (int) $kid_id, Taxonomies\PRACTICE );
		if ( $kid instanceof \WP_Term ) {
			$mine[] = $kid->slug;
		}
	}

	uasort( $tally, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] );

	foreach ( $tally as $slug => $row ) {
		if ( $row['count'] < MIN || in_array( $slug, $self, true ) ) {
			continue;
		}

		$term = get_term_by( 'slug', $slug, Services\TAXONOMY );
		$cats = $term instanceof \WP_Term ? (array) get_term_meta( $term->term_id, 'oria_categories', true ) : array();

		// No overlap with this category — and an uncategorised service is
		// exactly the noisy sort this filter exists to keep out.
		if ( ! array_intersect( $mine, array_map( 'strval', $cats ) ) ) {
			continue;
		}

		$rows[] = array(
			'label' => $row['name'],
			'count' => (int) $row['count'],
			'url'   => row_url( $base, 'svc', $slug ),
			'kind'  => 'service',
		);
	}

	/* ------------------------------------------------- format and price */

	$online = 0;
	$free   = 0;
	foreach ( $ids as $id ) {
		$format = (string) get_field( 'format', (int) $id );
		if ( in_array( $format, array( 'online', 'both' ), true ) ) {
			$online++;
		}
		if ( 0 === strcasecmp( trim( (string) get_field( 'price_band', (int) $id ) ), 'Free' ) ) {
			$free++;
		}
	}

	if ( $online >= MIN ) {
		$rows[] = array(
			'label' => __( 'Online or hybrid', 'oria' ),
			'count' => $online,
			'url'   => row_url( $base, 'format', 'online' ),
			'kind'  => 'format',
		);
	}

	if ( $free >= MIN ) {
		$rows[] = array(
			'label' => __( 'Free or by donation', 'oria' ),
			'count' => $free,
			'url'   => row_url( $base, 'price', 'Free' ),
			'kind'  => 'price',
		);
	}

	/*
	 * A row whose view has a live intent page (IntentPages) points there
	 * instead of at the filter URL, so the page with the frame and the
	 * canonical address is the one that gets the link.
	 */
	return (array) apply_filters( 'oria_intent_rows', $rows, $practice );
}

/**
 * A short factual line for the block, or '' when there is nothing to say.
 *
 * Written to be liftable on its own — an answer engine quoting one sentence
 * from this page should get something true and specific rather than a
 * heading.
 *
 * @param list<array{label: string, count: int, url: string, kind: string}> $rows
 */
function summary( \WP_Term $practice, array $rows ): string {
	if ( count( $rows ) < 2 ) {
		return '';
	}

	$name  = wp_specialchars_decode( $practice->name, ENT_QUOTES );
	$parts = array();

	foreach ( array_slice( $rows, 0, 3 ) as $row ) {
		$parts[] = sprintf( '%d %s', $row['count'], strtolower( $row['label'] ) );
	}

	return sprintf(
		/* translators: 1: category name, 2: list like "6 beginner friendly, 4 yin yoga". */
		__( 'Within %1$s in Perth: %2$s.', 'oria' ),
		$name,
		\Oria\Core\Faq\oxford( $parts )
	);
}

/* ------------------------------------------------------- popular intents */

/**
 * The most-chosen intents across the whole directory.
 *
 * Feeds the home page's "Popular right now" pills. Ranked by what visitors
 * actually filtered for (IntentStats), not by what somebody typed into a
 * repeater — and pointed at the whole directory rather than a category,
 * because on the home page nobody has picked one yet.
 *
 * Returns fewer than asked for, or nothing at all, when the data does not
 * support more. A pill sitting on three clicks is not a measurement, and the
 * caller falls back to the editorial list rather than dressing noise up as
 * a trend.
 *
 * @return list<array{label: string, count: int, url: string, kind: string}>
 */
function popular( int $limit = 5 ): array {
	if ( ! function_exists( '\Oria\Core\IntentStats\totals' ) ) {
		return array();
	}

	$base = get_post_type_archive_link( PostTypes\LISTING ) ?: home_url( '/directory/' );
	$out  = array();

	foreach ( \Oria\Core\IntentStats\totals( \Oria\Core\IntentStats\KEEP_DAYS ) as $intent => $count ) {
		if ( count( $out ) >= $limit ) {
			break;
		}
		if ( $count < \Oria\Core\IntentStats\FLOOR ) {
			// Sorted descending, so the first one under the floor ends it.
			break;
		}

		list( $key, $value ) = array_pad( explode( ':', (string) $intent, 2 ), 2, '' );

		$label = label_for( $key, $value );
		if ( '' === $label ) {
			// A term deleted since it was counted. Skip it rather than
			// printing a slug at somebody.
			continue;
		}

		$out[] = array(
			'label' => $label,
			'count' => (int) $count,
			// src=hero keeps the pills out of their own ranking.
			'url'   => add_query_arg( array( $key => $value, 'src' => 'hero' ), $base ) . ANCHOR,
			'kind'  => $key,
		);
	}

	return $out;
}

/** A human label for one intent key, or '' when it no longer resolves. */
function label_for( string $key, string $value ): string {
	if ( 'format' === $key ) {
		return 'online' === $value ? __( 'Online or hybrid', 'oria' ) : __( 'In person', 'oria' );
	}

	if ( 'price' === $key ) {
		return 'Free' === $value ? __( 'Free or by donation', 'oria' ) : $value;
	}

	$taxonomy = 'aud' === $key ? Audience\TAXONOMY : Services\TAXONOMY;
	$term     = get_term_by( 'slug', $value, $taxonomy );

	return $term instanceof \WP_Term ? wp_specialchars_decode( $term->name, ENT_QUOTES ) : '';
}
