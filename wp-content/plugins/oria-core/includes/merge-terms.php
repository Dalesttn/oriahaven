<?php
/**
 * Folding a specialty term into the practice term of the same name.
 *
 * Five slugs exist in both taxonomies — breathwork, meditation,
 * mindfulness, nutrition, yoga — and each pair renders two pages about the
 * same subject in the same city:
 *
 *     /practice/meditation/   "Meditation classes in Perth"   12 listings
 *     /perth/meditation/      "Meditation in Perth"           11 listings
 *
 * That is a duplicate-content problem and an entity problem at once. An
 * answer engine working out what "meditation in Perth" refers to on this
 * site finds two candidates and nothing saying which is the subject.
 *
 * The specialty vocabulary is meant to hold precise modalities — yin yoga,
 * remedial massage, craniosacral therapy. A specialty called "Yoga" is a
 * category wearing a specialty's clothes, and the practice taxonomy already
 * has that job.
 *
 * WHAT THIS DOES NOT DO. It does not change a listing's primary category.
 * The practice term is appended, so a Pranic Healing centre tagged with the
 * yoga specialty gains "Yoga & Pilates" as a secondary practice and keeps
 * Spirituality as its primary. wp_set_object_terms() with $append = true is
 * the whole of that guarantee, and the listing index reads $practices[0] as
 * primary, so order is preserved.
 *
 * The redirect is registered before the term is deleted, because
 * get_term_link() stops working the moment it is gone and the old URL is
 * then unrecoverable.
 */

declare(strict_types=1);

namespace Oria\Core\MergeTerms;

use Oria\Core\PostTypes;
use Oria\Core\Redirects;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plan the merge without touching anything.
 *
 * @return array{ok: bool, error: string, from_url: string, to_url: string, listings: list<int>, gaining: list<int>}
 */
function plan( string $specialty_slug, string $practice_slug ): array {
	$out = array( 'ok' => false, 'error' => '', 'from_url' => '', 'to_url' => '', 'listings' => array(), 'gaining' => array() );

	$spec = get_term_by( 'slug', $specialty_slug, Taxonomies\SPECIALTY );
	$prac = get_term_by( 'slug', $practice_slug, Taxonomies\PRACTICE );

	if ( ! $spec instanceof \WP_Term ) {
		$out['error'] = sprintf( 'No specialty term "%s".', $specialty_slug );
		return $out;
	}
	if ( ! $prac instanceof \WP_Term ) {
		$out['error'] = sprintf( 'No practice term "%s".', $practice_slug );
		return $out;
	}

	$listings = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array(
				array( 'taxonomy' => Taxonomies\SPECIALTY, 'field' => 'term_id', 'terms' => $spec->term_id ),
			),
		)
	);

	// Which of them would gain the practice term they do not already hold —
	// the only editorially meaningful change this makes.
	$gaining = array();
	foreach ( $listings as $id ) {
		$have = wp_get_object_terms( (int) $id, Taxonomies\PRACTICE, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $have ) && ! in_array( (int) $prac->term_id, array_map( 'intval', $have ), true ) ) {
			$gaining[] = (int) $id;
		}
	}

	$out['ok']       = true;
	$out['from_url'] = (string) get_term_link( $spec );
	$out['to_url']   = (string) get_term_link( $prac );
	$out['listings'] = array_map( 'intval', $listings );
	$out['gaining']  = $gaining;

	return $out;
}

/**
 * Do it.
 *
 * @return array{ok: bool, error: string, moved: int, from_url: string, to_url: string}
 */
function merge( string $specialty_slug, string $practice_slug ): array {
	$p = plan( $specialty_slug, $practice_slug );

	if ( ! $p['ok'] ) {
		return array( 'ok' => false, 'error' => $p['error'], 'moved' => 0, 'from_url' => '', 'to_url' => '' );
	}

	$prac = get_term_by( 'slug', $practice_slug, Taxonomies\PRACTICE );
	$spec = get_term_by( 'slug', $specialty_slug, Taxonomies\SPECIALTY );

	foreach ( $p['gaining'] as $id ) {
		// Append. Never replace — $practices[0] is the primary category and
		// reordering it would silently recategorise a real business.
		wp_set_object_terms( (int) $id, array( (int) $prac->term_id ), Taxonomies\PRACTICE, true );
	}

	// Before the delete, while the old URL still exists.
	Redirects\add( $p['from_url'], $p['to_url'] );

	wp_delete_term( (int) $spec->term_id, Taxonomies\SPECIALTY );

	return array(
		'ok'       => true,
		'error'    => '',
		'moved'    => count( $p['gaining'] ),
		'from_url' => $p['from_url'],
		'to_url'   => $p['to_url'],
	);
}

/* ------------------------------------------------------------------- CLI */

if ( defined( 'WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command(
		'oria merge-specialty',
		/**
		 * Fold a specialty term into a practice term of the same subject.
		 */
		new class() {
			/**
			 * ## OPTIONS
			 *
			 * <specialty>
			 * : Slug of the specialty term to fold away.
			 *
			 * --into=<practice>
			 * : Slug of the practice term that becomes the single entity.
			 *
			 * [--dry-run]
			 * : Report what would change without writing anything.
			 *
			 * ## EXAMPLES
			 *
			 *     wp oria merge-specialty meditation --into=meditation --dry-run
			 *     wp oria merge-specialty yoga --into=yoga
			 */
			public function __invoke( array $args, array $assoc ): void {
				list( $specialty ) = $args;
				$practice = (string) ( $assoc['into'] ?? '' );
				$dry      = isset( $assoc['dry-run'] );

				if ( '' === $practice ) {
					\WP_CLI::error( '--into=<practice-slug> is required.' );
				}

				$p = plan( $specialty, $practice );

				if ( ! $p['ok'] ) {
					\WP_CLI::error( $p['error'] );
				}

				\WP_CLI::log( sprintf( '%s  ->  %s', $p['from_url'], $p['to_url'] ) );
				\WP_CLI::log( sprintf( '%d listings carry the specialty; %d would gain the practice term:', count( $p['listings'] ), count( $p['gaining'] ) ) );

				foreach ( $p['gaining'] as $id ) {
					$primary = wp_get_object_terms( (int) $id, Taxonomies\PRACTICE );
					$primary = ( ! is_wp_error( $primary ) && $primary ) ? wp_specialchars_decode( $primary[0]->name, ENT_QUOTES ) : '(none)';
					\WP_CLI::log( sprintf( '    %-44s primary stays: %s', wp_specialchars_decode( (string) get_the_title( $id ), ENT_QUOTES ), $primary ) );
				}

				if ( $dry ) {
					\WP_CLI::log( '- DRY RUN: nothing written -' );
					return;
				}

				$r = merge( $specialty, $practice );

				if ( ! $r['ok'] ) {
					\WP_CLI::error( $r['error'] );
				}

				\WP_CLI::success( sprintf( '%d listings gained the practice term. Specialty deleted, 301 recorded.', $r['moved'] ) );
			}
		}
	);
}
