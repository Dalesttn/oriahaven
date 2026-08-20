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

	\WP_CLI::add_command(
		'oria rename-area',
		/**
		 * Rename an area term and record the 301 in one step.
		 *
		 * Written because the CBD rename was done by hand with a throwaway
		 * script, and the city migration needs the same operation eighty-six
		 * more times. A rename without its redirect is a silent 404, and the
		 * old URL is unrecoverable the moment the term changes.
		 */
		new class() {
			/**
			 * ## OPTIONS
			 *
			 * <slug>
			 * : Current slug of the area term.
			 *
			 * [--to-slug=<slug>]
			 * : New slug. Omit to keep the current one.
			 *
			 * [--to-name=<name>]
			 * : New display name. Omit to keep the current one.
			 *
			 * [--dry-run]
			 * : Report what would change without writing anything.
			 *
			 * ## EXAMPLES
			 *
			 *     wp oria rename-area perth --to-slug=perth-cbd --to-name="Perth CBD" --dry-run
			 *     wp oria rename-area perth --to-slug=perth-cbd --to-name="Perth CBD"
			 */
			public function __invoke( array $args, array $assoc ): void {
				list( $slug ) = $args;
				$dry = isset( $assoc['dry-run'] );

				$term = get_term_by( 'slug', $slug, Taxonomies\AREA );
				if ( ! $term instanceof \WP_Term ) {
					\WP_CLI::error( sprintf( 'No area term with slug "%s".', $slug ) );
				}

				$new_slug = (string) ( $assoc['to-slug'] ?? $term->slug );
				$new_name = (string) ( $assoc['to-name'] ?? wp_specialchars_decode( $term->name, ENT_QUOTES ) );

				if ( $new_slug === $term->slug && $new_name === wp_specialchars_decode( $term->name, ENT_QUOTES ) ) {
					\WP_CLI::success( 'Nothing to change.' );
					return;
				}

				// A slug already in use would be silently suffixed to -2 by
				// WordPress, which is how a suburb ends up split in half.
				if ( $new_slug !== $term->slug ) {
					$clash = get_term_by( 'slug', $new_slug, Taxonomies\AREA );
					if ( $clash instanceof \WP_Term ) {
						\WP_CLI::error( sprintf( 'An area term already uses slug "%s" (#%d). Rename or merge that one first.', $new_slug, $clash->term_id ) );
					}
				}

				$old_url   = (string) get_term_link( $term );
				$listings  = get_posts(
					array(
						'post_type'      => PostTypes\LISTING,
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'tax_query'      => array( array( 'taxonomy' => Taxonomies\AREA, 'field' => 'term_id', 'terms' => $term->term_id ) ),
					)
				);

				\WP_CLI::log( sprintf( 'name : %s  ->  %s', wp_specialchars_decode( $term->name, ENT_QUOTES ), $new_name ) );
				\WP_CLI::log( sprintf( 'slug : %s  ->  %s', $term->slug, $new_slug ) );
				\WP_CLI::log( sprintf( 'url  : %s', $old_url ) );
				\WP_CLI::log( sprintf( '%d listings sit in this area.', count( $listings ) ) );

				if ( $dry ) {
					\WP_CLI::log( '- DRY RUN: nothing written -' );
					return;
				}

				$done = wp_update_term( (int) $term->term_id, Taxonomies\AREA, array( 'name' => $new_name, 'slug' => $new_slug ) );
				if ( is_wp_error( $done ) ) {
					\WP_CLI::error( $done->get_error_message() );
				}

				clean_term_cache( (int) $term->term_id, Taxonomies\AREA );
				$fresh   = get_term( (int) $term->term_id, Taxonomies\AREA );
				$new_url = $fresh instanceof \WP_Term ? (string) get_term_link( $fresh ) : '';

				if ( '' !== $new_url ) {
					Redirects\add( $old_url, $new_url );
				}

				\WP_CLI::success( sprintf( 'Renamed. 301 recorded: %s -> %s', $old_url, $new_url ) );
			}
		}
	);

	\WP_CLI::add_command(
		'oria merge-area',
		/**
		 * Move every listing out of one area term into another, then delete
		 * the emptied one and record the 301.
		 *
		 * Written after an alias bug split the Perth CBD across two terms on
		 * production: a populated "perth" and a "perth-cbd" the importer had
		 * created because the alias pointed at a term that did not exist yet.
		 * Renaming cannot fix that — the target slug is taken — so the two
		 * have to be merged.
		 *
		 * Listings are appended to the target before the source is deleted,
		 * so nothing is ever briefly area-less.
		 */
		new class() {
			/**
			 * ## OPTIONS
			 *
			 * <from>
			 * : Slug of the area term to empty and delete.
			 *
			 * --into=<slug>
			 * : Slug of the area term that survives.
			 *
			 * [--to-name=<name>]
			 * : Rename the surviving term while we are here.
			 *
			 * [--dry-run]
			 * : Report what would change without writing anything.
			 *
			 * ## EXAMPLES
			 *
			 *     wp oria merge-area perth --into=perth-cbd --to-name="Perth CBD" --dry-run
			 *     wp oria merge-area perth --into=perth-cbd --to-name="Perth CBD"
			 */
			public function __invoke( array $args, array $assoc ): void {
				list( $from_slug ) = $args;
				$into_slug = (string) ( $assoc['into'] ?? '' );
				$dry       = isset( $assoc['dry-run'] );

				if ( '' === $into_slug ) {
					\WP_CLI::error( '--into=<slug> is required.' );
				}

				$from = get_term_by( 'slug', $from_slug, Taxonomies\AREA );
				$into = get_term_by( 'slug', $into_slug, Taxonomies\AREA );

				if ( ! $from instanceof \WP_Term ) {
					\WP_CLI::error( sprintf( 'No area term with slug "%s".', $from_slug ) );
				}
				if ( ! $into instanceof \WP_Term ) {
					\WP_CLI::error( sprintf( 'No area term with slug "%s".', $into_slug ) );
				}
				if ( (int) $from->term_id === (int) $into->term_id ) {
					\WP_CLI::error( 'Those are the same term.' );
				}
				if ( (int) $from->parent !== (int) $into->parent ) {
					\WP_CLI::warning( sprintf(
						'Different parents: "%s" sits under #%d, "%s" under #%d. Check that is intended.',
						$from->slug, $from->parent, $into->slug, $into->parent
					) );
				}

				$ids = get_posts(
					array(
						'post_type'      => PostTypes\LISTING,
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'tax_query'      => array( array( 'taxonomy' => Taxonomies\AREA, 'field' => 'term_id', 'terms' => $from->term_id ) ),
					)
				);

				$old_url  = (string) get_term_link( $from );
				$new_name = (string) ( $assoc['to-name'] ?? wp_specialchars_decode( $into->name, ENT_QUOTES ) );

				\WP_CLI::log( sprintf( 'from : %s  (%s)  #%d  %d listings', $from->slug, wp_specialchars_decode( $from->name, ENT_QUOTES ), $from->term_id, count( $ids ) ) );
				\WP_CLI::log( sprintf( 'into : %s  (%s)  #%d', $into->slug, wp_specialchars_decode( $into->name, ENT_QUOTES ), $into->term_id ) );
				if ( $new_name !== wp_specialchars_decode( $into->name, ENT_QUOTES ) ) {
					\WP_CLI::log( sprintf( 'rename survivor to: %s', $new_name ) );
				}
				\WP_CLI::log( sprintf( 'redirect: %s  ->  %s', $old_url, (string) get_term_link( $into ) ) );

				foreach ( $ids as $id ) {
					\WP_CLI::log( sprintf( '    %s', wp_specialchars_decode( (string) get_the_title( $id ), ENT_QUOTES ) ) );
				}

				if ( $dry ) {
					\WP_CLI::log( '- DRY RUN: nothing written -' );
					return;
				}

				foreach ( $ids as $id ) {
					wp_set_object_terms( (int) $id, array( (int) $into->term_id ), Taxonomies\AREA, true );
				}

				if ( $new_name !== wp_specialchars_decode( $into->name, ENT_QUOTES ) ) {
					wp_update_term( (int) $into->term_id, Taxonomies\AREA, array( 'name' => $new_name ) );
				}

				Redirects\add( $old_url, (string) get_term_link( $into ) );
				wp_delete_term( (int) $from->term_id, Taxonomies\AREA );

				\WP_CLI::success( sprintf( '%d listings moved. "%s" deleted, 301 recorded.', count( $ids ), $from_slug ) );
			}
		}
	);
}
