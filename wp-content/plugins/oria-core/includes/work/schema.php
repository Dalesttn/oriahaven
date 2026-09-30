<?php
/**
 * Work in Wellness: structured data.
 *
 * JobPosting (brief sections 59-60) on an open job that a real employer
 * posted here. Not on a closed job (Google wants expired postings gone from
 * the markup), and not on an external summary Oria added itself -- that is
 * not an employer's posting, and marking it up as one is exactly the
 * "aggregated fake job" the guidelines exclude. baseSalary only when the
 * employer gave a figure; never a guess.
 *
 * Person (brief section 14) on an indexable work profile, with occupation,
 * locality and sameAs. No email, phone, ABN or registration number.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work\Schema;

use Oria\Core\Work;
use const Oria\Core\Work\JOB;
use const Oria\Core\Work\PRO;
use const Oria\Core\Work\EMPLOYMENT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'wp_head', __NAMESPACE__ . '\output', 7 );
}

function output(): void {
	if ( ! is_singular( array( JOB, PRO ) ) ) {
		return;
	}
	$id    = (int) get_queried_object_id();
	$graph = JOB === get_post_type( $id ) ? job( $id ) : person( $id );
	if ( $graph ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/** schema.org employmentType from our slug. */
function employment( int $id ): string {
	$t   = Work\term( $id, EMPLOYMENT );
	$map = array(
		'full-time'  => 'FULL_TIME',
		'part-time'  => 'PART_TIME',
		'casual'     => 'PART_TIME',
		'contract'   => 'CONTRACTOR',
		'freelance'  => 'CONTRACTOR',
		'fixed-term' => 'TEMPORARY',
		'internship' => 'INTERN',
		'volunteer'  => 'VOLUNTEER',
		'locum'      => 'TEMPORARY',
		'temporary'  => 'TEMPORARY',
		'seasonal'   => 'TEMPORARY',
	);
	return $t ? ( $map[ $t->slug ] ?? 'OTHER' ) : 'OTHER';
}

function job( int $id ): array {
	if ( ! Work\is_open( $id ) || Work\meta( $id, 'external' ) ) {
		return array();
	}
	$city  = Work\city_of( $id );
	$state = $city && function_exists( '\Oria\Core\Cities\state_code' ) ? \Oria\Core\Cities\state_code( $city ) : '';
	$place = Work\place_label( $id );

	$org = array( '@type' => 'Organization', 'name' => Work\employer_name( $id ) ?: get_bloginfo( 'name' ) );
	if ( $url = Work\employer_url( $id ) ) {
		$org['sameAs'] = $url;
		$listing       = (int) Work\meta( $id, 'listing', 0 );
		if ( $listing && has_post_thumbnail( $listing ) ) {
			$org['logo'] = (string) get_the_post_thumbnail_url( $listing, 'thumbnail' );
		}
	}

	$g = array(
		'@context'           => 'https://schema.org',
		'@type'              => 'JobPosting',
		'title'              => get_the_title( $id ),
		'description'        => wpautop( wp_kses_post( (string) get_post_field( 'post_content', $id ) ) ),
		'datePosted'         => get_post_time( 'c', true, $id ),
		'employmentType'     => employment( $id ),
		'hiringOrganization' => $org,
		'directApply'        => 'oria' === Work\meta( $id, 'apply_method', 'oria' ),
		'identifier'         => array( '@type' => 'PropertyValue', 'name' => 'Oria Haven', 'value' => (string) $id ),
	);
	if ( $exp = (int) Work\meta( $id, 'expires', 0 ) ) {
		$g['validThrough'] = wp_date( 'c', $exp );
	}
	if ( 'remote' === Work\meta( $id, 'arrangement' ) ) {
		$g['jobLocationType']                = 'TELECOMMUTE';
		$g['applicantLocationRequirements'] = array( '@type' => 'Country', 'name' => 'AU' );
	}
	if ( $place || $city ) {
		$g['jobLocation'] = array(
			'@type'   => 'Place',
			'address' => array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => (string) Work\meta( $id, 'address' ),
					'addressLocality' => $place ?: (string) ( $city['name'] ?? '' ),
					'addressRegion'   => $state,
					'addressCountry'  => 'AU',
				)
			),
		);
	}
	$min  = (float) Work\meta( $id, 'pay_min', 0 );
	$max  = (float) Work\meta( $id, 'pay_max', 0 );
	$unit = array( 'hour' => 'HOUR', 'year' => 'YEAR', 'class' => 'HOUR', 'shift' => 'DAY' )[ (string) Work\meta( $id, 'pay_unit' ) ] ?? '';
	// Per-class rates are not an hourly wage; only units Google understands, and only real figures.
	if ( ( $min || $max ) && $unit && 'class' !== Work\meta( $id, 'pay_unit' ) ) {
		$value = array( '@type' => 'QuantitativeValue', 'unitText' => $unit );
		if ( $min && $max && $max > $min ) {
			$value['minValue'] = $min;
			$value['maxValue'] = $max;
		} else {
			$value['value'] = max( $min, $max );
		}
		$g['baseSalary'] = array( '@type' => 'MonetaryAmount', 'currency' => 'AUD', 'value' => $value );
	}
	return $g;
}

function person( int $id ): array {
	if ( ! Work\profile_indexable( $id ) ) {
		return array();
	}
	$g = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Person',
		'name'        => get_the_title( $id ),
		'url'         => get_permalink( $id ),
		'description' => (string) get_post_field( 'post_excerpt', $id ),
	);
	if ( $prof = Work\profession_name( $id ) ) {
		$g['jobTitle']   = (string) Work\meta( $id, 'title' ) ?: $prof;
		$g['hasOccupation'] = array( '@type' => 'Occupation', 'name' => $prof );
	}
	if ( $place = Work\place_label( $id ) ) {
		$g['address'] = array( '@type' => 'PostalAddress', 'addressLocality' => $place, 'addressCountry' => 'AU' );
	}
	if ( has_post_thumbnail( $id ) ) {
		$g['image'] = (string) get_the_post_thumbnail_url( $id, 'medium' );
	}
	$same = array_values( array_filter( array( (string) Work\meta( $id, 'website' ), (string) Work\meta( $id, 'instagram' ), (string) Work\meta( $id, 'linkedin' ) ) ) );
	if ( $same ) {
		$g['sameAs'] = $same;
	}
	$works = (int) Work\meta( $id, 'works_at', 0 );
	if ( $works && 'publish' === get_post_status( $works ) ) {
		$g['worksFor'] = array( '@type' => 'Organization', 'name' => html_entity_decode( get_the_title( $works ), ENT_QUOTES, 'UTF-8' ), 'url' => get_permalink( $works ) );
	}
	return $g;
}
