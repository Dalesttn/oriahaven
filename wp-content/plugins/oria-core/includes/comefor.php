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
 * Stored as a taxonomy so the edit screen is WordPress's own tag box:
 * type a reason, press enter, done — with autocomplete across every
 * listing, which is what stops "foot alignment" and "Foot Alignment"
 * becoming two different things. Deliberately not public: these are
 * useful ON a profile, but a browsable archive of complaints is a
 * different kind of page and a different kind of claim.
 *
 * @see data/comefor.json for the seed vocabulary used when pre-filling.
 */

declare(strict_types=1);

namespace Oria\Core\ComeFor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The taxonomy the reasons live in. */
const TAX = 'come_for';

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
 * The reasons on this listing, as rows ready to render.
 *
 * @return array<int, array{slug: string, label: string}>
 */
function for_listing( int $listing_id ): array {
	$terms = get_the_terms( $listing_id, TAX );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}
	$out = array();
	foreach ( $terms as $term ) {
		$out[] = array( 'slug' => $term->slug, 'label' => $term->name );
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
 * The reasons this listing's own words support — the suggestion, not a
 * save. Labels rather than ids: they become tags, and a tag is its words.
 *
 * @return array<int, string>
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
	foreach ( $options as $row ) {
		foreach ( $row['aliases'] as $alias ) {
			$needle = trim( (string) preg_replace( '/[^a-z0-9]+/', ' ', strtolower( $alias ) ) );
			if ( '' !== $needle && false !== strpos( $hay, $needle ) ) {
				$hit[] = $row['label'];
				break;
			}
		}
	}
	return $hit;
}

/* ------------------------------------------------------------------ admin */

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\\register' );
	add_action( 'admin_menu', __NAMESPACE__ . '\\menu' );
	add_action( 'admin_post_oria_comefor_fill', __NAMESPACE__ . '\\handle_fill' );
}

/**
 * The tag box on a listing: type a reason, press enter.
 *
 * public=false on purpose. The reasons belong on a profile, where the
 * practice's own answer sits beside its name; an archive of every clinic
 * filed under a complaint is a different page making a different claim,
 * and nothing links to one.
 */
function register(): void {
	register_taxonomy(
		TAX,
		array( 'listing' ),
		array(
			'labels'            => array(
				'name'          => __( 'People come here for', 'oria' ),
				'singular_name' => __( 'Reason', 'oria' ),
				'add_new_item'  => __( 'Add a reason', 'oria' ),
				'search_items'  => __( 'Search reasons', 'oria' ),
				'popular_items' => __( 'Most used', 'oria' ),
			),
			'public'            => false,
			'publicly_queryable' => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => false,
			'hierarchical'      => false,
			'rewrite'           => false,
		)
	);
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
 * Fill empty listings from each practice's own published words.
 *
 * Never touches a listing that already has reasons: those are somebody's
 * answer and a bulk job may not overrule them. Nothing is invented — a tag
 * appears only because the practice's own text names it.
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
		$have = get_the_terms( (int) $id, TAX );
		if ( $have && ! is_wp_error( $have ) ) {
			++$skip;
			continue;
		}
		$hits = suggest( (int) $id );
		if ( ! $hits ) {
			continue;
		}
		wp_set_object_terms( (int) $id, $hits, TAX, false );
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
		$have = get_the_terms( (int) $id, TAX );
		if ( $have && ! is_wp_error( $have ) ) {
			++$already;
			continue;
		}
		$hits = suggest( (int) $id );
		if ( $hits ) {
			++$covered;
			if ( count( $preview ) < 25 ) {
				$preview[] = array( 'id' => (int) $id, 'labels' => $hits );
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
