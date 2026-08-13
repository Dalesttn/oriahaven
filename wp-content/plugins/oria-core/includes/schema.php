<?php
/**
 * Structured data: LocalBusiness JSON-LD on listing pages and Event
 * JSON-LD on event pages — the markup that earns rich results (event
 * cards with dates, business knowledge panels) in search.
 *
 * Deliberate omissions: aggregateRating (our ratings come from Google
 * Places and must never be re-published as structured data), geo (no
 * stored coordinates), openingHoursSpecification (hours are free text).
 */

declare(strict_types=1);

namespace Oria\Core\Schema;

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
	$profiles = profiles();
	if ( $profiles ) {
		$schema['sameAs'] = $profiles;
	}

	echo '<script type="application/ld+json">'
		. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES )
		. '</script>' . "\n";
}

function output(): void {
	$graph = null;
	if ( is_singular( 'listing' ) ) {
		$graph = listing_schema( get_the_ID() );
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
	return $out;
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
