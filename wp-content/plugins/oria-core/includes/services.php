<?php
/**
 * Services as a controlled vocabulary.
 *
 * Listings already carry services: 446 distinct free-text strings across
 * 130 published listings, 83% of them used exactly once. That field is a
 * practice describing its own work, and it stays exactly as it is — this
 * adds a canonical layer beside it rather than replacing it.
 *
 * Two things follow from the shape of that data.
 *
 * Most of those strings are not services. They are session logistics
 * ("50-minute sessions", "Packages"), programme names ("Mummy MOT",
 * "Theta Chamber"), funding categories ("NDIS self-managed") and — this
 * is the one that matters — conditions: "PCOS & endometriosis", "IVF
 * cycle support", "Eating disorder recovery". A practice may describe its
 * own work however it wishes. A term in the canonical vocabulary is Oria
 * Haven speaking, and a directory that publishes "IVF Support in Perth"
 * has made a health claim it cannot stand behind. So data/services.json
 * holds only terms we would publish a page for, and everything else stays
 * free text.
 *
 * And the vocabulary is small on purpose. 248 listings will not support
 * hundreds of terms; the area pages taught us what that produces. It
 * grows from the unmatched report, when the listings justify it.
 *
 * The taxonomy is registered private for now: it powers search, filters
 * and admin, and creates no public URLs at all. Giving services pages is
 * a later decision, and one that should go through the same threshold
 * that governs the area pages.
 */

declare(strict_types=1);

namespace Oria\Core\Services;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TAXONOMY  = 'service';
const META_ALIAS = 'oria_aliases';
const DATA_FILE = 'data/services.json';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register', 7 );
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_services_sync', __NAMESPACE__ . '\handle_sync' );
}

function register(): void {
	register_taxonomy(
		TAXONOMY,
		array( PostTypes\LISTING ),
		array(
			'labels'            => array(
				'name'          => __( 'Services', 'oria' ),
				'singular_name' => __( 'Service', 'oria' ),
				'search_items'  => __( 'Search services', 'oria' ),
				'all_items'     => __( 'All services', 'oria' ),
				'edit_item'     => __( 'Edit service', 'oria' ),
				'add_new_item'  => __( 'Add service', 'oria' ),
			),
			/*
			 * Private on purpose. A public taxonomy would mint a URL for
			 * every term the moment it is seeded, and a service page with
			 * one listing is the area-page problem again. The vocabulary
			 * earns pages later, behind a threshold, as a separate call.
			 */
			'public'            => false,
			'publicly_queryable' => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'rewrite'           => false,
		)
	);
}

/* --------------------------------------------------------- the vocabulary */

/**
 * The canonical vocabulary as written down, not as installed.
 *
 * @return array<int, array{slug: string, name: string, categories: array<int, string>, aliases: array<int, string>}>
 */
function vocabulary(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$path = ORIA_CORE_DIR . DATA_FILE;
	if ( ! is_readable( $path ) ) {
		return $cache = array();
	}
	$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_array( $json ) || empty( $json['services'] ) ) {
		return $cache = array();
	}

	$out = array();
	foreach ( (array) $json['services'] as $row ) {
		if ( empty( $row['slug'] ) || empty( $row['name'] ) ) {
			continue;
		}
		$out[] = array(
			'slug'       => (string) $row['slug'],
			'name'       => (string) $row['name'],
			'categories' => array_map( 'strval', (array) ( $row['categories'] ?? array() ) ),
			'aliases'    => array_map( 'strval', (array) ( $row['aliases'] ?? array() ) ),
		);
	}
	return $cache = $out;
}

/**
 * Fold a string to its comparable form.
 *
 * Case, punctuation and the several ways people write "and" all collapse,
 * so "Strength & Conditioning", "strength and conditioning" and
 * "Strength-and-Conditioning" are one key.
 */
function fold( string $s ): string {
	$s = strtolower( wp_specialchars_decode( $s, ENT_QUOTES ) );
	$s = str_replace( '&', ' and ', $s );
	$s = (string) preg_replace( '/[^a-z0-9]+/', ' ', $s );
	return trim( (string) preg_replace( '/\s+/', ' ', $s ) );
}

/**
 * Every folded phrase that should resolve to a canonical slug.
 *
 * @return array<string, string> folded phrase => service slug
 */
function lookup(): array {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map = array();
	foreach ( vocabulary() as $service ) {
		$map[ fold( $service['name'] ) ] = $service['slug'];
		foreach ( $service['aliases'] as $alias ) {
			$key = fold( $alias );
			// First writer wins, so a canonical name is never displaced by
			// somebody else's alias.
			if ( '' !== $key && ! isset( $map[ $key ] ) ) {
				$map[ $key ] = $service['slug'];
			}
		}
	}
	return $map;
}

/** The canonical slug for a free-text phrase, or '' if we don't know it. */
function resolve( string $phrase ): string {
	$map = lookup();
	$key = fold( $phrase );
	return (string) ( $map[ $key ] ?? '' );
}

/* -------------------------------------------------------------- installing */

/**
 * Create or update the terms, without ever removing one.
 *
 * Re-runnable by design: it matches on slug, so a renamed term keeps its
 * ID and its listing relationships. Deletion is left to a human, because
 * a term dropped from the JSON may still be attached to listings.
 *
 * @return array{created: int, updated: int, unchanged: int}
 */
function sync_terms(): array {
	$out = array( 'created' => 0, 'updated' => 0, 'unchanged' => 0 );

	foreach ( vocabulary() as $service ) {
		$term = get_term_by( 'slug', $service['slug'], TAXONOMY );

		if ( ! $term instanceof \WP_Term ) {
			$made = wp_insert_term( $service['name'], TAXONOMY, array( 'slug' => $service['slug'] ) );
			if ( is_wp_error( $made ) ) {
				continue;
			}
			$term_id = (int) $made['term_id'];
			$out['created']++;
		} else {
			$term_id = (int) $term->term_id;
			if ( $term->name !== $service['name'] ) {
				wp_update_term( $term_id, TAXONOMY, array( 'name' => $service['name'] ) );
				$out['updated']++;
			} else {
				$out['unchanged']++;
			}
		}

		update_term_meta( $term_id, META_ALIAS, $service['aliases'] );
		update_term_meta( $term_id, 'oria_categories', $service['categories'] );
	}

	return $out;
}

/**
 * Read each listing's free-text services and attach the ones we recognise.
 *
 * Additive and non-destructive in both directions: the ACF field is never
 * written to, and terms already attached by hand are kept. What a practice
 * wrote about itself stays exactly as it wrote it.
 *
 * @return array{listings: int, attached: int, unmatched: array<string, int>}
 */
function map_listings(): array {
	$ids       = get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$attached  = 0;
	$unmatched = array();

	foreach ( $ids as $id ) {
		$slugs = array();
		foreach ( (array) get_field( 'services', (int) $id ) as $row ) {
			$name = trim( (string) ( is_array( $row ) ? ( $row['name'] ?? '' ) : $row ) );
			if ( '' === $name ) {
				continue;
			}
			$slug = resolve( $name );
			if ( '' !== $slug ) {
				$slugs[ $slug ] = true;
			} else {
				$unmatched[ $name ] = ( $unmatched[ $name ] ?? 0 ) + 1;
			}
		}

		if ( $slugs ) {
			// append: a term added by hand in the admin is not ours to remove.
			wp_set_object_terms( (int) $id, array_keys( $slugs ), TAXONOMY, true );
			$attached += count( $slugs );
		}
	}

	arsort( $unmatched );
	return array( 'listings' => count( $ids ), 'attached' => $attached, 'unmatched' => $unmatched );
}

/* ------------------------------------------------------------------ admin */

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . PostTypes\LISTING,
		__( 'Service vocabulary', 'oria' ),
		__( 'Service vocabulary', 'oria' ),
		'manage_options',
		'oria-services',
		__NAMESPACE__ . '\render'
	);
}

function handle_sync(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot do that.', 'oria' ) );
	}
	check_admin_referer( 'oria_services_sync' );

	$terms = sync_terms();
	$map   = map_listings();
	set_transient( 'oria_services_report', array( 'terms' => $terms, 'map' => $map ), HOUR_IN_SECONDS );

	wp_safe_redirect( admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=oria-services&synced=1' ) );
	exit;
}

function render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$vocab = vocabulary();
	echo '<div class="wrap"><h1>' . esc_html__( 'Service vocabulary', 'oria' ) . '</h1>';

	printf(
		'<p class="description" style="max-width:74ch">%s</p>',
		esc_html__( 'Canonical services, with the phrasings that resolve to each. Listings keep their own free-text services exactly as written — this reads them and attaches the ones it recognises, and never edits them. Running this again is safe.', 'oria' )
	);

	printf(
		'<form method="post" action="%s">%s<input type="hidden" name="action" value="oria_services_sync">'
		. '<p><button class="button button-primary">%s</button> <span class="description">%s</span></p></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'oria_services_sync', '_wpnonce', true, false ),
		esc_html__( 'Sync vocabulary and re-scan listings', 'oria' ),
		esc_html(
			sprintf(
				/* translators: %d: number of canonical services */
				__( '%d canonical services defined.', 'oria' ),
				count( $vocab )
			)
		)
	);

	$report = get_transient( 'oria_services_report' );
	if ( is_array( $report ) ) {
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: created, 2: updated, 3: unchanged, 4: listings, 5: attachments */
					__( 'Terms: %1$d created, %2$d updated, %3$d unchanged. Scanned %4$d listings and made %5$d attachments.', 'oria' ),
					$report['terms']['created'],
					$report['terms']['updated'],
					$report['terms']['unchanged'],
					$report['map']['listings'],
					$report['map']['attached']
				)
			)
		);

		$un = (array) ( $report['map']['unmatched'] ?? array() );
		if ( $un ) {
			echo '<h2>' . esc_html__( 'Phrases we did not recognise', 'oria' ) . '</h2>';
			echo '<p class="description" style="max-width:74ch">' . esc_html__( 'Not errors. Most are session lengths, programme names or a practice\'s own wording, and belong nowhere but the listing. Anything here that is a real service worth a page is a candidate for the vocabulary — the counts say which are worth adding.', 'oria' ) . '</p>';
			echo '<table class="widefat striped" style="max-width:60ch"><thead><tr><th>' . esc_html__( 'Phrase', 'oria' ) . '</th><th style="width:8em">' . esc_html__( 'Listings', 'oria' ) . '</th></tr></thead><tbody>';
			$shown = 0;
			foreach ( $un as $phrase => $n ) {
				if ( $shown++ >= 60 ) {
					break;
				}
				printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html( $phrase ), (int) $n );
			}
			echo '</tbody></table>';
			printf( '<p class="description">%s</p>', esc_html( sprintf( __( '%d distinct unrecognised phrases in total.', 'oria' ), count( $un ) ) ) );
		}
	}

	echo '<h2>' . esc_html__( 'The vocabulary', 'oria' ) . '</h2>';
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th style="width:18em">' . esc_html__( 'Service', 'oria' ) . '</th>';
	echo '<th style="width:8em">' . esc_html__( 'Listings', 'oria' ) . '</th>';
	echo '<th>' . esc_html__( 'Also matches', 'oria' ) . '</th>';
	echo '</tr></thead><tbody>';
	foreach ( $vocab as $service ) {
		$term = get_term_by( 'slug', $service['slug'], TAXONOMY );
		printf(
			'<tr><td><strong>%s</strong></td><td>%s</td><td class="description">%s</td></tr>',
			esc_html( $service['name'] ),
			$term instanceof \WP_Term ? (int) $term->count : '&mdash;',
			esc_html( $service['aliases'] ? implode( ', ', $service['aliases'] ) : '—' )
		);
	}
	echo '</tbody></table></div>';
}
