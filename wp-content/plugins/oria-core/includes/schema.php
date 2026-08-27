<?php
/**
 * Structured data: LocalBusiness JSON-LD on listing pages and Event
 * JSON-LD on event pages — the markup that earns rich results (event
 * cards with dates, business knowledge panels) in search.
 *
 * Deliberate omissions: geo (no stored coordinates) and
 * openingHoursSpecification (hours are free text).
 *
 * Ratings are marked up from ONE source only — reviews collected here, by
 * verified members, held for moderation. A listing's Google Places rating
 * is never emitted as structured data: it is licensed data shown under
 * their terms, not ours to republish. See reviews_schema() below.
 */

declare(strict_types=1);

namespace Oria\Core\Schema;

use Oria\Core\Team;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The business's own identity, in one place so the footer and the schema
 * cannot drift apart — they have to byte-match to be worth anything.
 */
const NAP = array(
	'name'     => 'Oria Haven',
	'email'    => 'hello@oriahaven.com.au',
	// Two forms of the one number. The spaced version is what a human
	// reads and what every citation site must be given, byte for byte;
	// the E.164 version is what schema and tel: links want.
	'phone'    => '0431 630 244',
	'phone_e164' => '+61431630244',
	// A service-area business: the Perth metro, no street address published.
	'locality' => 'Perth',
	'region'   => 'WA',
	'area'     => 'Perth, Western Australia',
	'founded'  => '2026',
	// Spaced the way the ATO writes it, because this string also has to
	// match the invoices and the ABN Lookup entry. Checksum verified.
	'abn'      => '46 243 774 311',
);

/** Where the business exists elsewhere online. Feeds sameAs. */
function profiles(): array {
	return array_values(
		array_filter(
			(array) apply_filters(
				'oria_social_profiles',
				array(
					'https://www.instagram.com/oriahavenwellness/',
					'https://www.linkedin.com/company/oria-haven',
				)
			)
		)
	);
}

function bootstrap(): void {
	add_action( 'wp_head', __NAMESPACE__ . '\output', 5 );
	add_action( 'wp_head', __NAMESPACE__ . '\organization', 6 );
}

/**
 * Who this site belongs to, on every page.
 *
 * There was no machine-readable identity for the business anywhere, which
 * blocks the knowledge panel and every local signal downstream of it.
 *
 * Organization rather than LocalBusiness on purpose: a directory serving a
 * whole metro is not a shopfront, and claiming LocalBusiness without a real
 * street address invites exactly the mismatch that gets rich results
 * dropped. telephone and address are omitted rather than guessed — an empty
 * string in structured data is not neutral, it is a wrong answer.
 */
function organization(): void {
	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'@id'         => home_url( '/#organization' ),
		'name'        => NAP['name'],
		'url'         => home_url( '/' ),
		'email'       => NAP['email'],
		'description' => __( "Perth's independent, hand-checked wellness directory.", 'oria' ),
		'areaServed'  => array(
			'@type' => 'AdministrativeArea',
			'name'  => NAP['area'],
		),
		/*
		 * Locality level only. A service-area business has no street to
		 * publish, but leaving address off entirely means Google has no
		 * geography for the organisation at all — city, state and country
		 * are true, verifiable, and enough to place us.
		 */
		'address'     => array(
			'@type'           => 'PostalAddress',
			'addressLocality' => NAP['locality'],
			'addressRegion'   => NAP['region'],
			'addressCountry'  => 'AU',
		),
	);

	$logo = get_theme_file_uri( 'assets/img/email-mark.png' );
	if ( $logo ) {
		$schema['logo'] = array( '@type' => 'ImageObject', 'url' => $logo );
	}
	if ( '' !== NAP['phone_e164'] ) {
		$schema['telephone'] = NAP['phone_e164'];
	}
	if ( '' !== NAP['founded'] ) {
		$schema['foundingDate'] = NAP['founded'];
	}
	if ( '' !== NAP['abn'] ) {
		// schema.org has no Australian business number, so this goes in the
		// generic identifier slot with the scheme named. Digits only here:
		// an identifier is for machines, and the spaced form is for people.
		$schema['identifier'] = array(
			'@type'      => 'PropertyValue',
			'propertyID' => 'ABN',
			'value'      => preg_replace( '/\D/', '', NAP['abn'] ),
		);
	}
	$profiles = profiles();
	if ( $profiles ) {
		$schema['sameAs'] = $profiles;
	}

	echo '<script type="application/ld+json">'
		. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES )
		. '</script>' . "\n";
}

/**
 * Question-and-answer pairs for a listing, from its own data.
 *
 * Assembled, not written: the address answers "where", the services answer
 * "what", the price fields answer "how much", the contact details answer
 * "how do I book". A field that is empty simply keeps its question off the
 * list, and fewer than two answers means no FAQ at all -- the template and
 * the schema both read this one function, so what is marked up is exactly
 * what is on the page.
 *
 * Nothing here is a claim. Every answer is a fact the listing already
 * publishes two scrolls up.
 *
 * @return list<array{q: string, a: string}>
 */
function listing_faq( int $id ): array {
	$name = html_entity_decode( get_the_title( $id ), ENT_QUOTES );
	$out  = array();

	$address = trim( (string) get_field( 'address', $id ) );
	$suburb  = '';
	foreach ( wp_get_post_terms( $id, 'area' ) as $t ) {
		if ( $t->parent ) {
			$suburb = html_entity_decode( $t->name, ENT_QUOTES );
			break;
		}
	}
	if ( '' !== $address ) {
		$out[] = array(
			'q' => sprintf( __( 'Where is %s?', 'oria' ), $name ),
			'a' => $suburb && false === stripos( $address, $suburb )
				? sprintf( __( '%1$s, in %2$s.', 'oria' ), $address, $suburb )
				: $address . '.',
		);
	} elseif ( '' !== $suburb ) {
		$out[] = array(
			'q' => sprintf( __( 'Where is %s?', 'oria' ), $name ),
			'a' => sprintf( __( 'In %s, Perth. Contact the practice for the exact address.', 'oria' ), $suburb ),
		);
	}

	$services = array_filter( array_map( 'trim', array_column( (array) get_field( 'services', $id ), 'name' ) ) );
	if ( count( $services ) >= 2 ) {
		$out[] = array(
			'q' => sprintf( __( 'What does %s offer?', 'oria' ), $name ),
			'a' => implode( ', ', array_slice( $services, 0, 6 ) ) . '.',
		);
	}

	$from = (float) get_field( 'price_from', $id );
	$band = trim( (string) get_field( 'price_band', $id ) );
	if ( $from > 0 ) {
		$out[] = array(
			'q' => sprintf( __( 'How much does %s cost?', 'oria' ), $name ),
			'a' => sprintf( __( 'Listed prices start from $%s. Confirm current prices with the practice.', 'oria' ), number_format_i18n( $from ) ),
		);
	} elseif ( '' !== $band && 'Free' === $band ) {
		$out[] = array(
			'q' => sprintf( __( 'How much does %s cost?', 'oria' ), $name ),
			'a' => __( 'Free or by donation, as listed by the practice.', 'oria' ),
		);
	}

	$phone = trim( (string) get_field( 'phone', $id ) );
	$site  = trim( (string) get_field( 'website', $id ) );
	if ( '' !== $phone || '' !== $site ) {
		$bits = array();
		if ( '' !== $site ) {
			$bits[] = __( 'through their website', 'oria' );
		}
		if ( '' !== $phone ) {
			$bits[] = sprintf( __( 'by phone on %s', 'oria' ), $phone );
		}
		$out[] = array(
			'q' => sprintf( __( 'How do I book with %s?', 'oria' ), $name ),
			'a' => sprintf( __( 'Directly with the practice, %s. Oria Haven takes no bookings and no commission.', 'oria' ), implode( __( ' or ', 'oria' ), $bits ) ),
		);
	}

	return count( $out ) >= 2 ? $out : array();
}

/** The FAQPage node for a listing, or null when the page shows no FAQ. */
function listing_faq_schema( int $id ): ?array {
	$faq = listing_faq( $id );
	if ( ! $faq ) {
		return null;
	}
	return array(
		'@type'      => 'FAQPage',
		'@id'        => get_permalink( $id ) . '#faq',
		'mainEntity' => array_map(
			static fn( array $qa ): array => array(
				'@type'          => 'Question',
				'name'           => $qa['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $qa['a'],
				),
			),
			$faq
		),
	);
}

function output(): void {
	$graph = null;
	if ( is_singular( 'listing' ) ) {
		$graph = listing_schema( get_the_ID() );
		$faq   = listing_faq_schema( (int) get_the_ID() );
		if ( $graph && $faq ) {
			// Same @graph, so the page stays one JSON-LD block.
			$graph['@graph'][] = $faq;
		}
	} elseif ( is_singular( 'event' ) ) {
		$graph = event_schema( get_the_ID() );
	} elseif ( is_tax( array( 'practice', 'specialty', 'area' ) ) || is_post_type_archive( 'listing' ) ) {
		$graph = item_list_schema();
	}
	if ( $graph ) {
		echo '<script type="application/ld+json">'
			. wp_json_encode( $graph, JSON_UNESCAPED_SLASHES )
			. '</script>' . "\n";
	}
}

/**
 * The listings on a directory archive, as an ordered ItemList.
 *
 * URL-only ListItems, which is the form Google documents for summary
 * pages: it says "this page is a list, and here is what it points at"
 * without restating each business inline. The full LocalBusiness record
 * lives on the profile the item links to, which is the page we want
 * ranking for the business name anyway.
 *
 * Skipped on thin or empty archives — a one-item list is noise, and on a
 * noindexed combo it is markup nobody will ever read.
 *
 * @return array<string, mixed>|null
 */
function item_list_schema(): ?array {
	$posts = $GLOBALS['wp_query']->posts ?? array();
	if ( count( $posts ) < 2 ) {
		return null;
	}

	$items = array();
	foreach ( $posts as $i => $post ) {
		$url = get_permalink( $post );
		if ( ! $url ) {
			continue;
		}
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => $url,
			'name'     => wp_specialchars_decode( get_post_field( 'post_title', $post, 'raw' ) ),
		);
	}
	if ( ! $items ) {
		return null;
	}

	$term = get_queried_object();
	$self = $term instanceof \WP_Term ? get_term_link( $term ) : get_post_type_archive_link( 'listing' );

	return array(
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'@id'             => ( is_string( $self ) ? $self : home_url( '/' ) ) . '#listings',
		'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
		'numberOfItems'   => count( $items ),
		'itemListElement' => $items,
	);
}

/** @return array<string, mixed>|null */
function listing_schema( int $id ): ?array {
	$name = get_post_field( 'post_title', $id, 'raw' );
	if ( '' === $name ) {
		return null;
	}

	$suburb = '';
	foreach ( wp_get_post_terms( $id, 'area' ) as $term ) {
		if ( $term->parent ) {
			$suburb = wp_specialchars_decode( $term->name );
			break;
		}
	}

	$out = array(
		'@context' => 'https://schema.org',
		'@type'    => 'LocalBusiness',
		'@id'      => get_permalink( $id ) . '#business',
		'name'     => wp_specialchars_decode( $name ),
		'url'      => get_permalink( $id ),
	);

	$desc = \Oria\Core\Seo\entity_description( $id );
	if ( '' !== $desc ) {
		$out['description'] = $desc;
	}

	$address = (string) get_field( 'address', $id );
	if ( '' !== $address || '' !== $suburb ) {
		$out['address'] = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $address,
				'addressLocality' => $suburb,
				'addressRegion'   => 'WA',
				'addressCountry'  => 'AU',
			)
		);
	}

	$phone = (string) get_field( 'phone', $id );
	if ( '' !== $phone ) {
		$out['telephone'] = $phone;
	}
	$band = (string) get_field( 'price_band', $id );
	if ( '' !== $band ) {
		$out['priceRange'] = $band;
	}
	$site = (string) get_field( 'website', $id );
	if ( '' !== $site ) {
		$out['sameAs'] = array( $site );
	}

	$gallery = array_values( array_filter( array_map( 'intval', (array) ( get_field( 'gallery', $id ) ?: array() ) ) ) );
	if ( $gallery ) {
		$img = wp_get_attachment_image_url( $gallery[0], 'large' );
		if ( $img ) {
			$out['image'] = $img;
		}
	}

	$out = array_merge( $out, reviews_schema( $id ) );

	/*
	 * Named practitioners, where the listing publishes any. This is the
	 * experience-and-expertise signal a wellness listing usually cannot
	 * offer, and only what a visitor can see on the page is marked up.
	 */
	if ( function_exists( 'Oria\Core\Team\schema_for' ) ) {
		$people = Team\schema_for( $id );
		if ( $people ) {
			$out['employee'] = $people;
		}
	}

	return $out;
}

/**
 * Reviews and their aggregate — ours only, never Google's.
 *
 * The file header explains why the Google rating is not marked up: it is
 * licensed data from the Places API and republishing it as our own
 * structured data is not ours to do. First-party reviews are a different
 * thing entirely. A directory publishing what its members said about a
 * third-party business is the case review markup exists for, unlike a
 * business rating itself, and it is the one thing here that can put stars
 * in a search result.
 *
 * Two rules keep it honest:
 *   - only approved reviews collected here, matching what the page shows a
 *     visitor, because markup that disagrees with the page is a penalty
 *     waiting to happen;
 *   - nothing at all below HEADLINE_MIN, which is also the point at which
 *     the page stops showing our rating as the headline.
 *
 * @return array<string, mixed> Merged into the LocalBusiness record.
 */
function reviews_schema( int $id ): array {
	if ( ! function_exists( '\Oria\Core\Reviews\approved' ) ) {
		return array();
	}

	$reviews = \Oria\Core\Reviews\approved( $id );
	if ( count( $reviews ) < \Oria\Core\Reviews\HEADLINE_MIN ) {
		return array();
	}

	$items   = array();
	$ratings = array();

	foreach ( $reviews as $review ) {
		$rating = \Oria\Core\Reviews\rating_of( $review );
		if ( $rating < 1 || $rating > 5 ) {
			continue;
		}
		$ratings[] = $rating;

		$item = array(
			'@type'         => 'Review',
			'reviewRating'  => array(
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating'  => 5,
				'worstRating' => 1,
			),
			'author'        => array(
				'@type' => 'Person',
				'name'  => wp_specialchars_decode( (string) $review->comment_author ),
			),
			'datePublished' => mysql2date( 'Y-m-d', (string) $review->comment_date_gmt ),
		);

		$body = trim( (string) $review->comment_content );
		if ( '' !== $body ) {
			$item['reviewBody'] = $body;
		}

		$items[] = $item;
	}

	if ( ! $ratings ) {
		return array();
	}

	return array(
		'aggregateRating' => array(
			'@type'       => 'AggregateRating',
			'ratingValue' => round( array_sum( $ratings ) / count( $ratings ), 1 ),
			'reviewCount' => count( $ratings ),
			'bestRating'  => 5,
			'worstRating' => 1,
		),
		'review'          => $items,
	);
}

/** @return array<string, mixed>|null */
function event_schema( int $id ): ?array {
	$start = (string) get_field( 'event_start', $id );
	$name  = get_post_field( 'post_title', $id, 'raw' );
	if ( '' === $start || '' === $name ) {
		return null;
	}

	// Naive local datetimes are Perth times; say so explicitly.
	$iso = static fn( string $dt ): string => str_replace( ' ', 'T', $dt ) . '+08:00';

	$suburb = '';
	foreach ( wp_get_post_terms( $id, 'area' ) as $term ) {
		$suburb = wp_specialchars_decode( $term->name );
		if ( $term->parent ) {
			break;
		}
	}

	$out = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Event',
		'name'                => wp_specialchars_decode( $name ),
		'url'                 => get_permalink( $id ),
		'startDate'           => $iso( $start ),
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'location'            => array_filter(
			array(
				'@type'   => 'Place',
				'name'    => (string) get_field( 'venue', $id ) ?: $suburb,
				'address' => array_filter(
					array(
						'@type'           => 'PostalAddress',
						'addressLocality' => $suburb,
						'addressRegion'   => 'WA',
						'addressCountry'  => 'AU',
					)
				),
			)
		),
	);

	$end = (string) get_field( 'event_end', $id );
	if ( '' !== $end ) {
		$out['endDate'] = $iso( $end );
	}

	$desc = wp_trim_words( wp_strip_all_tags( (string) get_field( 'event_description', $id ) ), 40, '…' );
	if ( '' !== $desc ) {
		$out['description'] = $desc;
	}

	if ( has_post_thumbnail( $id ) ) {
		$out['image'] = get_the_post_thumbnail_url( $id, 'large' );
	}

	$price = (string) get_field( 'price', $id );
	if ( preg_match( '/(\d+(?:\.\d+)?)/', $price, $m ) ) {
		$out['offers'] = array(
			'@type'         => 'Offer',
			'price'         => $m[1],
			'priceCurrency' => 'AUD',
			'url'           => (string) get_field( 'booking_url', $id ) ?: get_permalink( $id ),
			'availability'  => 'https://schema.org/InStock',
		);
	} elseif ( preg_match( '/free|donation/i', $price ) ) {
		$out['isAccessibleForFree'] = true;
	}

	$listing = (int) get_field( 'listing', $id );
	$org     = $listing ? get_post_field( 'post_title', $listing, 'raw' ) : (string) get_post_meta( $id, '_oria_organiser', true );
	if ( '' !== $org ) {
		$out['organizer'] = array_filter(
			array(
				'@type' => 'Organization',
				'name'  => wp_specialchars_decode( $org ),
				'url'   => $listing ? get_permalink( $listing ) : '',
			)
		);
	}
	return $out;
}
