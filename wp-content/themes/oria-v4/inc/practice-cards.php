<?php
/**
 * The practice cards on the Explore hub.
 *
 * Six categories with a photograph and a line somebody wrote, rather than
 * twenty-six pills. Which six is an editorial decision -- the six longest
 * lists would open with Beauty and Nutrition, which is not what most
 * people come here for -- so the list and the lines live in
 * assets/data/practice-cards.json where they can be changed without
 * touching a template.
 *
 * Nothing here invents copy. A category with no line written for it shows
 * its name and its count and nothing else.
 *
 * @package Oria\V4
 */

declare(strict_types=1);

namespace Oria\V4\PracticeCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The file, read once. */
function config(): array {
	static $cfg = null;
	if ( null === $cfg ) {
		$file = get_stylesheet_directory() . '/assets/data/practice-cards.json';
		$cfg  = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file
		$cfg  = is_array( $cfg ) ? $cfg : array();
	}
	return $cfg;
}

/**
 * The chosen practice slugs, in order.
 *
 * @return list<string>
 */
function featured_slugs(): array {
	$slugs = (array) ( config()['featured'] ?? array() );
	return array_values( array_filter( array_map( 'sanitize_title', $slugs ) ) );
}

/** The short human line for a category, or an empty string. */
function line( string $slug ): string {
	return trim( (string) ( config()['lines'][ $slug ] ?? '' ) );
}
