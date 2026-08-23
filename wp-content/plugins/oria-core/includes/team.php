<?php
/**
 * Practitioner profiles: the people behind a listing.
 *
 * Up to four per listing, of which the plan decides how many publish — one
 * on the free tier, four once claimed (see Tiers\TEAM_LIMITS). The rows a
 * plan cannot publish are kept, not deleted: a lapsed subscription should
 * quieten a listing, never destroy what somebody typed.
 *
 * Two rules shape what a profile may say, and both are here rather than in
 * the template so they cannot be forgotten by the next thing that renders a
 * profile:
 *
 *   Consent. A practice publishing a named employee's photo and history is
 *   publishing another person's information. A profile without the consent
 *   box ticked never appears, whatever the plan.
 *
 *   Checkable facts, not claims. The fields ask for qualifications held and
 *   registrations that can be looked up — the same standard Audience\assign()
 *   applies to audience terms, where a claim without a source is refused. A
 *   registration number is worth more than any adjective, and this is a
 *   directory of health-adjacent practitioners where the difference matters.
 */

declare(strict_types=1);

namespace Oria\Core\Team;

use Oria\Core\PostTypes;
use Oria\Core\Tiers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FIELD = 'team';

function bootstrap(): void {
	// "Specialises in" offers this listing's own services and specialties,
	// so a profile can never drift from what the directory says the place
	// actually does.
	add_filter( 'acf/prepare_field/key=field_oria_team_specialties', __NAMESPACE__ . '\specialty_choices' );

	// ACF's max is a UI limit; the cap is enforced again on the way in.
	add_action( 'acf/save_post', __NAMESPACE__ . '\cap_on_save', 15 );
}

/* ------------------------------------------------------------------ read */

/**
 * Every saved profile, in order, whether or not it can be published.
 *
 * @return array<int, array<string,mixed>>
 */
function all( int $listing_id ): array {
	$rows = get_field( FIELD, $listing_id );
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$out = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$name = trim( (string) ( $row['name'] ?? '' ) );
		if ( '' === $name ) {
			continue; // an empty row somebody added and never filled in
		}
		$out[] = normalise( $row );
	}

	return $out;
}

/**
 * The profiles this listing may actually show: consented, and within the
 * number its plan publishes.
 *
 * @return array<int, array<string,mixed>>
 */
function visible( int $listing_id ): array {
	$consented = array_values(
		array_filter( all( $listing_id ), static fn( array $row ): bool => (bool) $row['consent'] )
	);

	return array_slice( $consented, 0, Tiers\team_limit( $listing_id ) );
}

/** How many saved profiles the current plan is holding back. */
function withheld( int $listing_id ): int {
	$consented = array_filter( all( $listing_id ), static fn( array $row ): bool => (bool) $row['consent'] );
	return max( 0, count( $consented ) - Tiers\team_limit( $listing_id ) );
}

/**
 * One row, tidied into a predictable shape.
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function normalise( array $row ): array {
	$quals = array_values(
		array_filter(
			array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $row['quals'] ?? '' ) ) ?: array() ),
			static fn( string $line ): bool => '' !== $line
		)
	);

	$specialties = array();
	foreach ( (array) ( $row['specialties'] ?? array() ) as $term_id ) {
		$term = get_term( (int) $term_id );
		if ( $term instanceof \WP_Term ) {
			$specialties[] = $term;
		}
	}

	$languages = array_values(
		array_filter(
			array_map( 'trim', explode( ',', (string) ( $row['languages'] ?? '' ) ) ),
			static fn( string $l ): bool => '' !== $l
		)
	);

	return array(
		'name'        => trim( (string) ( $row['name'] ?? '' ) ),
		'role'        => trim( (string) ( $row['role'] ?? '' ) ),
		'photo'       => (int) ( $row['photo'] ?? 0 ),
		'quals'       => $quals,
		'reg_body'    => trim( (string) ( $row['reg_body'] ?? '' ) ),
		'reg_id'      => trim( (string) ( $row['reg_id'] ?? '' ) ),
		'reg_url'     => esc_url_raw( (string) ( $row['reg_url'] ?? '' ) ),
		'years'       => (int) ( $row['years'] ?? 0 ),
		'specialties' => $specialties,
		'languages'   => $languages,
		'bio'         => trim( (string) ( $row['bio'] ?? '' ) ),
		'consent'     => (bool) ( $row['consent'] ?? false ),
	);
}

/** Is there a registration somebody could go and check? */
function has_registration( array $row ): bool {
	return '' !== $row['reg_body'] && ( '' !== $row['reg_id'] || '' !== $row['reg_url'] );
}

/* ----------------------------------------------------------------- admin */

/**
 * Fill "Specialises in" with the terms this listing already carries.
 *
 * @param array<string,mixed> $field
 * @return array<string,mixed>
 */
function specialty_choices( $field ) {
	$listing_id = current_listing_id();

	if ( $listing_id <= 0 ) {
		return $field;
	}

	$choices = array();
	foreach ( array( 'service', 'specialty' ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		$terms = wp_get_post_terms( $listing_id, $taxonomy );
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			$choices[ (int) $term->term_id ] = wp_specialchars_decode( $term->name, ENT_QUOTES );
		}
	}

	asort( $choices );
	$field['choices'] = $choices;

	if ( ! $choices ) {
		$field['instructions'] = __( 'Add services or specialties to this listing first, then they can be picked here.', 'oria' );
	}

	return $field;
}

/** The listing being edited, on either the classic screen or a save. */
function current_listing_id(): int {
	$id = 0;

	if ( function_exists( 'acf_maybe_get_POST' ) ) {
		$id = (int) acf_maybe_get_POST( 'post_ID' );
	}
	if ( $id <= 0 && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( $id <= 0 ) {
		$id = (int) get_the_ID();
	}

	return PostTypes\LISTING === get_post_type( $id ) ? $id : 0;
}

/**
 * Trim anything past the hard cap on save.
 *
 * ACF's `max` only stops the browser adding another row. Anything that
 * writes the field another way — an import, a REST call, a filter — would
 * otherwise be unbounded.
 *
 * @param int|string $post_id
 */
function cap_on_save( $post_id ): void {
	$listing_id = (int) $post_id;

	if ( PostTypes\LISTING !== get_post_type( $listing_id ) ) {
		return;
	}

	$rows = get_field( FIELD, $listing_id );
	if ( is_array( $rows ) && count( $rows ) > Tiers\TEAM_MAX ) {
		update_field( FIELD, array_slice( $rows, 0, Tiers\TEAM_MAX ), $listing_id );
	}
}

/* ---------------------------------------------------------------- schema */

/**
 * The published practitioners as schema.org Person records.
 *
 * Named, credentialled people are the experience-and-expertise signal a
 * wellness listing usually cannot offer, and "who teaches the beginner
 * class" is exactly the question an answer engine tries to resolve. Only
 * what is visible on the page is marked up.
 *
 * @return array<int, array<string,mixed>>
 */
function schema_for( int $listing_id ): array {
	$out = array();

	foreach ( visible( $listing_id ) as $row ) {
		$person = array(
			'@type' => 'Person',
			'name'  => $row['name'],
		);

		if ( '' !== $row['role'] ) {
			$person['jobTitle'] = $row['role'];
		}

		if ( $row['specialties'] ) {
			$person['knowsAbout'] = array_map(
				static fn( \WP_Term $t ): string => wp_specialchars_decode( $t->name, ENT_QUOTES ),
				$row['specialties']
			);
		}

		if ( $row['quals'] ) {
			$person['hasCredential'] = array_map(
				static fn( string $q ): array => array(
					'@type'       => 'EducationalOccupationalCredential',
					'name'        => $q,
				),
				$row['quals']
			);
		}

		if ( has_registration( $row ) ) {
			$credential = array(
				'@type'                 => 'EducationalOccupationalCredential',
				'credentialCategory'    => 'Professional registration',
				'recognizedBy'          => array( '@type' => 'Organization', 'name' => $row['reg_body'] ),
			);
			if ( '' !== $row['reg_id'] ) {
				$credential['identifier'] = $row['reg_id'];
			}
			if ( '' !== $row['reg_url'] ) {
				$credential['url'] = $row['reg_url'];
			}
			$person['hasCredential'] = array_merge( (array) ( $person['hasCredential'] ?? array() ), array( $credential ) );
		}

		if ( $row['photo'] > 0 ) {
			$img = wp_get_attachment_image_url( $row['photo'], 'medium' );
			if ( $img ) {
				$person['image'] = $img;
			}
		}

		$out[] = $person;
	}

	return $out;
}
