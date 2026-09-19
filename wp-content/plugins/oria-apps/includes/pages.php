<?php
/**
 * What search engines are told about app pages.
 *
 * Titles and descriptions where Yoast would otherwise invent one, and
 * schema only where the data is genuinely there. SoftwareApplication is
 * emitted for an app page because the page really is about a piece of
 * software, with an offer only when a price is published -- and never an
 * AggregateRating, because we do not collect ratings and inventing them is
 * the line between structured data and lying to a crawler.
 *
 * A category page is indexable once it has enough apps to be worth
 * visiting. Below that it carries noindex and points its canonical at the
 * hub, the same floor the directory's facet pages use.
 */

declare(strict_types=1);

namespace Oria\Apps\Pages;

use Oria\Apps\Data;
use Oria\Apps\Engine;
use Oria\Apps\Guides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Apps a category needs before it is a page rather than a filter. */
const CATEGORY_MIN = 3;

function bootstrap(): void {
	add_filter( 'wpseo_title', __NAMESPACE__ . '\title', 20 );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title', 20 );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\description', 20 );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\canonical', 20 );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\yoast_robots', 20 );
	add_filter( 'wp_robots', __NAMESPACE__ . '\wp_robots' );
	/*
	 * The sitemap tells the same story as the page: while the guides
	 * archive is thin enough to carry noindex, it is not advertised either.
	 * thin_guide_archive() asks is_post_type_archive(), which is never true
	 * inside the sitemap request, so the count is asked directly.
	 */
	add_filter(
		'wpseo_sitemap_post_type_archive_link',
		static function ( $link, $post_type ) {
			return ( Guides\CPT === $post_type && count( Guides\all() ) < Guides\ARCHIVE_MIN ) ? false : $link;
		},
		10,
		2
	);
	add_action( 'wp_footer', __NAMESPACE__ . '\schema', 20 );
}

function hub_url(): string {
	return (string) get_post_type_archive_link( Data\CPT );
}

/** How many published apps sit in a category. */
function category_count( \WP_Term $term ): int {
	return count( Engine\apps( array( $term->slug ) ) );
}

/**
 * The guides archive with only one guide on it.
 *
 * An index page whose whole job is to list collections has nothing to say
 * while there is a single collection to list, and the collection itself is
 * the page that should rank. It becomes a real page at two.
 */
/**
 * The hub narrowed to one goal, e.g. /apps/?for=better-sleep.
 *
 * A view of the hub rather than a page: same apps, fewer of them, no
 * writing of its own. So it points its canonical at the hub and asks not
 * to be indexed -- sixteen of these in an index would be sixteen thin
 * duplicates of a page that is already there, which is the exact outcome
 * "best for" was made a field rather than a taxonomy to avoid.
 */
function filtered_hub(): bool {
	if ( ! is_post_type_archive( Data\CPT ) ) {
		return false;
	}
	$for = isset( $_GET['for'] ) ? sanitize_key( wp_unslash( $_GET['for'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return '' !== $for && isset( Data\BEST_FOR[ $for ] );
}

function thin_guide_archive(): bool {
	return is_post_type_archive( Guides\CPT ) && count( Guides\all() ) < Guides\ARCHIVE_MIN;
}

function thin_category(): bool {
	if ( ! is_tax( Data\TAX ) ) {
		return false;
	}
	$term = get_queried_object();
	return $term instanceof \WP_Term && category_count( $term ) < CATEGORY_MIN;
}

/* ------------------------------------------------------------------ meta */

function title( $title ) {
	if ( is_singular( Data\CPT ) && '' === (string) get_post_meta( (int) get_the_ID(), '_yoast_wpseo_title', true ) ) {
		$row  = Engine\row( (int) get_the_ID() );
		$cats = array_slice( (array) $row['cat_names'], 0, 2 );
		$what = $cats ? implode( ' & ', $cats ) : __( 'Wellness app', 'oria' );
		/* translators: 1: app name, 2: what it is for, 3: site name */
		return sprintf( __( '%1$s review: %2$s | %3$s', 'oria' ), $row['title'], $what, get_bloginfo( 'name' ) );
	}
	if ( is_post_type_archive( Data\CPT ) ) {
		if ( filtered_hub() ) {
			$for = sanitize_key( wp_unslash( $_GET['for'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			/* translators: 1: a goal, e.g. better sleep, 2: site name */
			return sprintf( __( 'Apps for %1$s | %2$s', 'oria' ), strtolower( Data\label( 'best_for', $for ) ), get_bloginfo( 'name' ) );
		}
		return __( 'Wellness Apps & Digital Tools | Oria Haven', 'oria' );
	}
	if ( is_tax( Data\TAX ) ) {
		$term = get_queried_object();
		if ( $term instanceof \WP_Term ) {
			/* translators: 1: category, 2: site name */
			return sprintf( __( 'Best %1$s Apps | %2$s', 'oria' ), wp_specialchars_decode( $term->name ), get_bloginfo( 'name' ) );
		}
	}
	// A collection's own title already says what it is -- "10 best wellness
	// apps in Australia" -- so it only needs the site name after it.
	if ( is_singular( Guides\CPT ) && '' === (string) get_post_meta( (int) get_the_ID(), '_yoast_wpseo_title', true ) ) {
		/* translators: 1: guide title, 2: site name */
		return sprintf( __( '%1$s | %2$s', 'oria' ), wp_specialchars_decode( (string) get_the_title() ), get_bloginfo( 'name' ) );
	}
	if ( is_post_type_archive( Guides\CPT ) ) {
		return __( 'Wellness App Guides | Oria Haven', 'oria' );
	}
	return $title;
}

function core_title( array $parts ): array {
	if ( is_singular( Data\CPT ) || is_post_type_archive( Data\CPT ) || is_tax( Data\TAX )
		|| is_singular( Guides\CPT ) || is_post_type_archive( Guides\CPT ) ) {
		$made = title( '' );
		if ( '' !== $made ) {
			$parts['title'] = $made;
			unset( $parts['site'], $parts['tagline'] );
		}
	}
	return $parts;
}

function description( $desc ) {
	if ( '' !== (string) $desc ) {
		return $desc;
	}
	if ( is_singular( Data\CPT ) ) {
		$row = Engine\row( (int) get_the_ID() );
		if ( '' !== (string) $row['take'] ) {
			return wp_trim_words( (string) $row['take'], 28, '…' );
		}
		if ( '' !== (string) $row['tagline'] ) {
			/* translators: 1: app name, 2: tagline */
			return sprintf( __( '%1$s: %2$s Features, pricing, who it suits and what to consider.', 'oria' ), $row['title'], rtrim( (string) $row['tagline'], '.' ) . '.' );
		}
	}
	if ( is_post_type_archive( Data\CPT ) ) {
		return __( 'Wellness apps for meditation, sleep, movement and everyday wellbeing — reviewed by Oria Haven, with what each one costs and who it suits.', 'oria' );
	}
	if ( is_tax( Data\TAX ) ) {
		$term = get_queried_object();
		if ( $term instanceof \WP_Term ) {
			$intro = trim( (string) get_term_meta( $term->term_id, 'intro', true ) );
			if ( '' !== $intro ) {
				return wp_trim_words( $intro, 28, '…' );
			}
			/* translators: %s: category name, lowercased */
			return sprintf( __( 'Wellness apps for %s, reviewed by Oria Haven with pricing, platforms and who each one suits.', 'oria' ), strtolower( wp_specialchars_decode( $term->name ) ) );
		}
	}
	if ( is_singular( Guides\CPT ) ) {
		$guide = Guides\guide( (int) get_the_ID() );
		if ( '' !== (string) $guide['subtitle'] ) {
			return (string) $guide['subtitle'];
		}
		if ( '' !== (string) $guide['intro'] ) {
			return wp_trim_words( (string) $guide['intro'], 28, '…' );
		}
	}
	if ( is_post_type_archive( Guides\CPT ) ) {
		return __( 'Shortlists of wellness apps worth your time — for sleep, meditation, movement and mindfulness, chosen and checked by Oria Haven.', 'oria' );
	}
	return $desc;
}

function canonical( $url ) {
	if ( thin_category() || filtered_hub() ) {
		return hub_url();
	}
	// One guide: the archive is a duplicate of it, so it points there.
	if ( thin_guide_archive() ) {
		$only = (int) ( Guides\all( 1 )[0] ?? 0 );
		return $only ? (string) get_permalink( $only ) : $url;
	}
	return $url;
}

function yoast_robots( $robots ) {
	return thin_category() || thin_guide_archive() || filtered_hub() ? 'noindex, follow' : $robots;
}

function wp_robots( array $r ): array {
	if ( thin_category() || thin_guide_archive() || filtered_hub() ) {
		$r['noindex'] = true;
		unset( $r['nofollow'] );
	}
	return $r;
}

/* ---------------------------------------------------------------- schema */

function schema(): void {
	if ( is_singular( Data\CPT ) ) {
		app_schema();
		return;
	}
	if ( is_singular( Guides\CPT ) ) {
		guide_schema();
		return;
	}
	if ( filtered_hub() ) {
		return;
	}
	if ( is_post_type_archive( Data\CPT ) || ( is_tax( Data\TAX ) && ! thin_category() ) ) {
		list_schema();
	}
}

/**
 * A collection page: the article, the ranked list, and the questions.
 *
 * ItemList carries a position because the order genuinely is the ranking --
 * an editor put each app where it sits. No AggregateRating and no
 * reviewRating anywhere: we publish no scores, so there is no number to
 * hand a crawler, and inventing one is the line this file does not cross.
 */
function guide_schema(): void {
	$guide = Guides\guide( (int) get_the_ID() );
	if ( count( $guide['picks'] ) < 2 ) {
		return;
	}

	$items = array();
	foreach ( $guide['picks'] as $i => $pick ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $pick['app']['title'],
			'url'      => $pick['app']['url'],
		);
	}

	$article = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'Article',
		'@id'           => $guide['url'] . '#article',
		'headline'      => $guide['title'],
		'url'           => $guide['url'],
		'datePublished' => get_the_date( 'c' ),
		'dateModified'  => '' !== $guide['checked'] ? gmdate( 'c', (int) strtotime( $guide['checked'] ) ) : get_the_modified_date( 'c' ),
		'publisher'     => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) ),
	);
	if ( '' !== (string) $guide['subtitle'] ) {
		$article['description'] = $guide['subtitle'];
	}

	$graph = array(
		$article,
		array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'@id'             => $guide['url'] . '#apps',
			'name'            => $guide['title'],
			'numberOfItems'   => count( $items ),
			'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
			'itemListElement' => $items,
		),
	);

	if ( $guide['faq'] ) {
		$questions = array();
		foreach ( $guide['faq'] as $item ) {
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $item['answer'] ),
			);
		}
		$graph[] = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'@id'        => $guide['url'] . '#faq',
			'mainEntity' => $questions,
		);
	}

	foreach ( $graph as $node ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $node, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}

function app_schema(): void {
	$row = Engine\row( (int) get_the_ID() );

	$node = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'SoftwareApplication',
		'@id'         => $row['url'] . '#app',
		'name'        => $row['title'],
		'url'         => $row['url'],
		'applicationCategory' => 'HealthApplication',
	);
	if ( '' !== (string) $row['tagline'] ) {
		$node['description'] = $row['tagline'];
	}
	if ( '' !== (string) $row['developer'] ) {
		$node['author'] = array( '@type' => 'Organization', 'name' => $row['developer'] );
	}
	if ( '' !== (string) $row['logo'] ) {
		$node['image'] = $row['logo'];
	}
	$platforms = array_values( array_filter( array_map(
		static fn( string $p ): string => (string) ( array( 'available_ios' => 'iOS', 'available_android' => 'Android', 'available_web' => 'Web', 'available_apple_watch' => 'watchOS', 'available_wear_os' => 'Wear OS' )[ $p ] ?? '' ),
		(array) $row['platforms']
	) ) );
	if ( $platforms ) {
		$node['operatingSystem'] = implode( ', ', $platforms );
	}

	/*
	 * An offer only where there is something true to say. A free app is
	 * price 0; a published price is stated as written; anything else --
	 * "freemium", "unknown" -- gets no offer node at all rather than a
	 * fabricated one.
	 */
	if ( 'free' === $row['pricing'] ) {
		$node['offers'] = array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'AUD' );
	} elseif ( '' !== (string) $row['price'] && preg_match( '/([0-9]+(?:\.[0-9]{1,2})?)/', (string) $row['price'], $m ) ) {
		$node['offers'] = array( '@type' => 'Offer', 'price' => $m[1], 'priceCurrency' => 'AUD' );
	}

	$graph = array( $node );

	if ( $row['faq'] ) {
		$questions = array();
		foreach ( $row['faq'] as $item ) {
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $item['answer'] ),
			);
		}
		$graph[] = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'@id'        => $row['url'] . '#faq',
			'mainEntity' => $questions,
		);
	}

	foreach ( $graph as $node ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $node, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}

function list_schema(): void {
	$term = is_tax( Data\TAX ) ? get_queried_object() : null;
	$rows = $term instanceof \WP_Term ? Engine\apps( array( $term->slug ) ) : Engine\apps();
	if ( count( $rows ) < 2 ) {
		return;
	}

	$items = array();
	foreach ( $rows as $i => $row ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $row['title'],
			'url'      => $row['url'],
		);
	}

	echo '<script type="application/ld+json">' . wp_json_encode(
		array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'@id'             => ( $term instanceof \WP_Term ? (string) get_term_link( $term ) : hub_url() ) . '#apps',
			'name'            => $term instanceof \WP_Term ? wp_specialchars_decode( $term->name ) : __( 'Wellness apps', 'oria' ),
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		),
		JSON_UNESCAPED_SLASHES
	) . '</script>' . "\n";
}
