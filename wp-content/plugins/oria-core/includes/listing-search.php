<?php
/**
 * A tiny public search over listing names, for the claim form's
 * business-name lookup. Everything it returns is already public on the
 * directory — names, suburbs and permalinks — so there is nothing here a
 * visitor could not read off the archive page.
 */

declare(strict_types=1);

namespace Oria\Core\ListingSearch;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAX_RESULTS = 8;

function bootstrap(): void {
	add_action( 'rest_api_init', __NAMESPACE__ . '\routes' );
}

function routes(): void {
	register_rest_route(
		'oria/v1',
		'/listing-search',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => array(
				'q' => array( 'type' => 'string', 'required' => true ),
			),
			'callback'            => __NAMESPACE__ . '\search',
		)
	);
}

function search( \WP_REST_Request $request ): \WP_REST_Response {
	$q = trim( (string) $request->get_param( 'q' ) );
	if ( mb_strlen( $q ) < 2 ) {
		return new \WP_REST_Response( array(), 200 );
	}

	$posts = get_posts(
		array(
			'post_type'        => PostTypes\LISTING,
			'post_status'      => 'publish',
			'posts_per_page'   => MAX_RESULTS,
			's'                => $q,
			'search_columns'   => array( 'post_title' ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);

	$out = array();
	foreach ( $posts as $post ) {
		// Where: the suburb term if there is one, else the region.
		$where = '';
		foreach ( wp_get_post_terms( $post->ID, 'area' ) as $term ) {
			$where = wp_specialchars_decode( $term->name, ENT_QUOTES );
			if ( $term->parent ) {
				break;
			}
		}

		$status = (string) get_post_meta( $post->ID, 'claim_status', true );
		$owned  = (int) get_post_meta( $post->ID, 'claimed_by', true ) > 0;

		$out[] = array(
			'id'      => $post->ID,
			'name'    => wp_specialchars_decode( get_post_field( 'post_title', $post->ID, 'raw' ), ENT_QUOTES ),
			'where'   => $where,
			'url'     => get_permalink( $post ),
			// Lets the form warn "this one already has an owner" rather than
			// taking a claim that will only be declined later.
			'claimed' => $owned || in_array( $status, array( 'claimed', 'featured' ), true ),
		);
	}

	return new \WP_REST_Response( $out, 200 );
}
