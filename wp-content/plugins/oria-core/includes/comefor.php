<?php
/**
 * "People come here for" — the presenting reasons a practice says people
 * arrive with, per service or specialty.
 *
 * The line this stays on the right side of: these describe SCOPE, not
 * OUTCOME. "People come here for heel pain" is the reason for the
 * appointment; "good for heel pain" is a claim about its result, and only
 * the second creates the expectation of beneficial treatment that the
 * National Law's advertising rules forbid for registered practitioners.
 *
 * Two contracts, both enforced here rather than left to a template:
 *
 * 1. It is the practice's own answer. Values are ticked by the owner, or
 *    pre-filled by matching the practice's OWN published words — never our
 *    inference about a business nobody has asked. The front end says whose
 *    answer it is.
 * 2. An empty set means nobody has been asked, never "this practice does
 *    not do these things". No template may read it as a no.
 *
 * @see data/comefor.json for the vocabulary and the writing rules.
 */

declare(strict_types=1);

namespace Oria\Core\ComeFor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ACF field name on the listing. */
const FIELD = 'come_for';

/**
 * The whole vocabulary, keyed by the service/specialty slug it belongs to.
 *
 * @return array<string, array<int, array{slug: string, label: string, emoji: string, aliases: array<int, string>}>>
 */
function vocab(): array {
	static $vocab = null;
	if ( null !== $vocab ) {
		return $vocab;
	}
	$vocab = array();
	$path  = ORIA_CORE_DIR . 'data/comefor.json';
	if ( is_readable( $path ) ) {
		$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( is_array( $json ) && is_array( $json['vocab'] ?? null ) ) {
			foreach ( $json['vocab'] as $key => $rows ) {
				foreach ( (array) $rows as $row ) {
					if ( empty( $row['slug'] ) || empty( $row['label'] ) ) {
						continue;
					}
					$vocab[ (string) $key ][] = array(
						'slug'    => (string) $row['slug'],
						'label'   => (string) $row['label'],
						'emoji'   => (string) ( $row['emoji'] ?? '' ),
						'aliases' => array_values( array_map( 'strval', (array) ( $row['aliases'] ?? array() ) ) ),
					);
				}
			}
		}
	}
	return $vocab;
}

/**
 * The options this listing may be asked about: the union of the
 * vocabularies for every service and specialty it carries, de-duplicated
 * by slug so a clinic tagged both podiatry and physiotherapy is offered
 * one "sports injuries", not two.
 *
 * @return array<string, array{slug: string, label: string, emoji: string, aliases: array<int, string>}>
 */
function options_for( int $listing_id ): array {
	$all  = vocab();
	$out  = array();
	foreach ( array( 'service', 'specialty' ) as $tax ) {
		$terms = get_the_terms( $listing_id, $tax );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			foreach ( (array) ( $all[ $term->slug ] ?? array() ) as $row ) {
				$out[ $row['slug'] ] = $row;
			}
		}
	}
	return $out;
}

/**
 * What the owner has actually ticked, as rows ready to render.
 *
 * Anything stored that is no longer offered to this listing (its terms
 * changed) is dropped rather than shown from a vocabulary it has left.
 *
 * @return array<int, array{slug: string, label: string, emoji: string}>
 */
function for_listing( int $listing_id ): array {
	$saved = get_post_meta( $listing_id, FIELD, true );
	$saved = is_array( $saved ) ? array_map( 'strval', $saved ) : array();
	if ( ! $saved ) {
		return array();
	}
	$options = options_for( $listing_id );
	$out     = array();
	foreach ( $saved as $slug ) {
		if ( isset( $options[ $slug ] ) ) {
			$out[] = array(
				'slug'  => $options[ $slug ]['slug'],
				'label' => $options[ $slug ]['label'],
				'emoji' => $options[ $slug ]['emoji'],
			);
		}
	}
	return $out;
}

/**
 * The listing's own published words, folded for matching.
 *
 * Blurb, excerpt and the free-text services line — everything here was
 * written by or about the practice from its own website, which is the only
 * evidence pre-population is allowed to use.
 */
function haystack( int $listing_id ): string {
	$post = get_post( $listing_id );
	if ( ! $post ) {
		return '';
	}
	$parts = array(
		(string) $post->post_content,
		(string) $post->post_excerpt,
		(string) get_post_meta( $listing_id, 'services', true ),
	);
	foreach ( array( 'service', 'specialty' ) as $tax ) {
		$terms = get_the_terms( $listing_id, $tax );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$parts[] = implode( ' ', wp_list_pluck( $terms, 'name' ) );
		}
	}
	$hay = strtolower( wp_strip_all_tags( implode( ' ', $parts ) ) );
	return (string) preg_replace( '/[^a-z0-9]+/', ' ', $hay );
}

/**
 * The rows this listing's own words support — the suggestion, not a save.
 *
 * @return array<int, string> Slugs.
 */
function suggest( int $listing_id ): array {
	$options = options_for( $listing_id );
	if ( ! $options ) {
		return array();
	}
	$hay = haystack( $listing_id );
	if ( '' === trim( $hay ) ) {
		return array();
	}
	$hit = array();
	foreach ( $options as $slug => $row ) {
		foreach ( $row['aliases'] as $alias ) {
			$needle = trim( (string) preg_replace( '/[^a-z0-9]+/', ' ', strtolower( $alias ) ) );
			if ( '' !== $needle && false !== strpos( $hay, $needle ) ) {
				$hit[] = $slug;
				break;
			}
		}
	}
	return $hit;
}

/* ------------------------------------------------------------------ admin */

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\\menu' );
	add_action( 'admin_post_oria_comefor_fill', __NAMESPACE__ . '\\handle_fill' );
}

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=listing',
		__( 'People come here for', 'oria' ),
		__( 'People come here for', 'oria' ),
		'manage_options',
		'oria-comefor',
		__NAMESPACE__ . '\render'
	);
}

/** Every published listing the vocabulary can speak to. */
function candidates(): array {
	return get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
}

/**
 * Fill empty fields from each practice's own published words.
 *
 * Never overwrites: a set already ticked is somebody's answer — ours or
 * the owner's — and a bulk job may not overrule it. Nothing is invented;
 * a row appears only because the practice's own text names it.
 *
 * @return array{filled: int, rows: int, skipped: int}
 */
function fill_empty(): array {
	$filled = 0;
	$rows   = 0;
	$skip   = 0;
	foreach ( candidates() as $id ) {
		if ( ! $id ) {
			continue;
		}
		$have = get_post_meta( (int) $id, FIELD, true );
		if ( is_array( $have ) && $have ) {
			++$skip;
			continue;
		}
		$hits = suggest( (int) $id );
		if ( ! $hits ) {
			continue;
		}
		update_post_meta( (int) $id, FIELD, $hits );
		++$filled;
		$rows += count( $hits );
	}
	return array( 'filled' => $filled, 'rows' => $rows, 'skipped' => $skip );
}

function handle_fill(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot do that.', 'oria' ) );
	}
	check_admin_referer( 'oria_comefor_fill' );
	set_transient( 'oria_comefor_report', fill_empty(), HOUR_IN_SECONDS );
	wp_safe_redirect( admin_url( 'edit.php?post_type=listing&page=oria-comefor&filled=1' ) );
	exit;
}

/** The screen: what the vocabulary would say, and the one button. */
function render(): void {
	$preview = array();
	$covered = 0;
	$already = 0;
	foreach ( candidates() as $id ) {
		if ( ! $id ) {
			continue;
		}
		$have = get_post_meta( (int) $id, FIELD, true );
		if ( is_array( $have ) && $have ) {
			++$already;
			continue;
		}
		$hits = suggest( (int) $id );
		if ( $hits ) {
			++$covered;
			if ( count( $preview ) < 25 ) {
				$opts   = options_for( (int) $id );
				$labels = array();
				foreach ( $hits as $slug ) {
					$labels[] = trim( ( $opts[ $slug ]['emoji'] ?? '' ) . ' ' . ( $opts[ $slug ]['label'] ?? $slug ) );
				}
				$preview[] = array( 'id' => (int) $id, 'labels' => $labels );
			}
		}
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'People come here for', 'oria' ) . '</h1>';
	echo '<p style="max-width:70ch">' . esc_html__( 'Matched from each practice\'s own published words — their blurb, their services, their own terms. Nothing is inferred, and an existing answer is never overwritten. Every row names a reason people arrive, never what the practice achieves.', 'oria' ) . '</p>';

	$report = get_transient( 'oria_comefor_report' );
	if ( is_array( $report ) ) {
		delete_transient( 'oria_comefor_report' );
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: listings filled, 2: rows added, 3: listings left alone */
					__( 'Filled %1$d listings with %2$d reasons. %3$d already had an answer and were left alone.', 'oria' ),
					(int) $report['filled'],
					(int) $report['rows'],
					(int) $report['skipped']
				)
			)
		);
	}

	printf(
		'<p><b>%s</b></p>',
		esc_html(
			sprintf(
				/* translators: 1: listings matched, 2: listings already answered */
				__( '%1$d listings can be filled from their own words. %2$d already have an answer.', 'oria' ),
				$covered,
				$already
			)
		)
	);

	if ( $covered ) {
		printf(
			'<form method="post" action="%s">%s<button class="button button-primary" type="submit">%s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'oria_comefor_fill', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="oria_comefor_fill">',
			esc_html__( 'Fill the empty ones', 'oria' )
		);
	}

	if ( $preview ) {
		echo '<h2>' . esc_html__( 'What it would write', 'oria' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( $preview as $row ) {
			printf(
				'<tr><td><a href="%s">%s</a></td><td>%s</td></tr>',
				esc_url( (string) get_edit_post_link( $row['id'] ) ),
				esc_html( get_the_title( $row['id'] ) ),
				esc_html( implode( ' · ', $row['labels'] ) )
			);
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}
