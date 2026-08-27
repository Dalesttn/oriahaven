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
	add_filter( 'oria_intent_rows', __NAMESPACE__ . '\annotate_rows', 5, 2 );
	add_action( 'admin_post_oria_services_sync', __NAMESPACE__ . '\handle_sync' );
	add_action( 'admin_post_oria_service_promote', __NAMESPACE__ . '\handle_promote' );

	// Aliases and categories on the term editor, so a service added by hand
	// is as capable as one that came from the file.
	add_action( TAXONOMY . '_add_form_fields', __NAMESPACE__ . '\add_fields' );
	add_action( TAXONOMY . '_edit_form_fields', __NAMESPACE__ . '\edit_fields' );
	add_action( 'created_' . TAXONOMY, __NAMESPACE__ . '\save_fields' );
	add_action( 'edited_' . TAXONOMY, __NAMESPACE__ . '\save_fields' );
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
			'note'       => (string) ( $row['note'] ?? '' ),
			'traits'     => array_map( 'strval', (array) ( $row['traits'] ?? array() ) ),
			'intensity'  => (int) ( $row['intensity'] ?? 0 ),
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
 * One line saying what happens in a session of this service.
 *
 * Written for the cards under "Start with what you want to do", where a
 * reader is choosing between modalities they may never have tried. It
 * describes the room, never the result: a directory does not get to say what
 * a practitioner's work achieves.
 */
function note( string $slug ): string {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( vocabulary() as $entry ) {
			if ( '' !== (string) ( $entry['note'] ?? '' ) ) {
				$map[ (string) $entry['slug'] ] = (string) $entry['note'];
			}
		}
	}
	return (string) ( $map[ $slug ] ?? '' );
}

/**
 * A service's description, wherever it is kept.
 *
 * Two registries hold these. services.json carries a note for most services;
 * intents.json carries one for the handful that also have a hand-framed
 * intent page — yin, vinyasa, reformer and the rest — and those were written
 * first, so they were never duplicated into services.json.
 *
 * The result: a card for "Restorative yin" linked correctly to
 * /practices/yoga/yin/ and showed no description at all, because the words
 * were sitting in the other file. This looks in both.
 */
/**
 * The card facts for a service: three short traits and an effort score.
 *
 * Traits describe the session or who it suits, never an outcome -- the same
 * line note() holds. Intensity is physical effort 1-5 and only exists where
 * effort is a real property of the service; 0 means "not a thing here" and
 * the card shows nothing, which is the amenities contract again.
 *
 * @return array{traits: list<string>, intensity: int}
 */
function card( string $slug ): array {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( vocabulary() as $entry ) {
			$traits = array_values( array_filter( array_map( 'strval', (array) ( $entry['traits'] ?? array() ) ) ) );
			if ( ! $traits ) {
				continue;
			}
			$map[ (string) $entry['slug'] ] = array(
				'traits'    => $traits,
				'intensity' => max( 0, min( 5, (int) ( $entry['intensity'] ?? 0 ) ) ),
			);
		}
	}
	return $map[ $slug ] ?? array( 'traits' => array(), 'intensity' => 0 );
}

function note_any( string $slug ): string {
	$own = note( $slug );
	if ( '' !== $own ) {
		return $own;
	}
	if ( ! function_exists( '\Oria\Core\IntentPages\registry' ) ) {
		return '';
	}
	static $by_svc = null;
	if ( null === $by_svc ) {
		$by_svc = array();
		$reg    = \Oria\Core\IntentPages\registry();
		foreach ( (array) ( $reg['intents'] ?? array() ) as $intent ) {
			$svc = (string) ( $intent['filter']['svc'] ?? '' );
			$txt = (string) ( $intent['note'] ?? '' );
			if ( '' !== $svc && '' !== $txt ) {
				$by_svc[ $svc ] = $txt;
			}
		}
	}
	return (string) ( $by_svc[ $slug ] ?? '' );
}

/**
 * Attach those notes to the category-page rows.
 *
 * Priority 5, ahead of IntentPages\canonical_rows at 10: where a practice has
 * a hand-written intent page for the same filter, that page's note is the more
 * specific one and overwrites this.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function annotate_rows( array $rows, \WP_Term $practice ): array {
	unset( $practice );

	/*
	 * The intent registry, keyed by the filter itself rather than by kind:
	 * "Online or hybrid" and "Free or by donation" are rows too, and their
	 * words already exist there. Reading it by filter also covers audience
	 * rows on any category that has them.
	 */
	static $by_filter = null;
	if ( null === $by_filter ) {
		$by_filter = array();
		if ( function_exists( '\Oria\Core\IntentPages\registry' ) ) {
			$reg = \Oria\Core\IntentPages\registry();
			foreach ( (array) ( $reg['intents'] ?? array() ) as $intent ) {
				$n = (string) ( $intent['note'] ?? '' );
				$f = (array) ( $intent['filter'] ?? array() );
				if ( '' === $n || ! $f ) {
					continue;
				}
				$k = (string) array_key_first( $f );
				$by_filter[ $k . '=' . (string) $f[ $k ] ] = $n;
			}
		}
	}

	foreach ( $rows as &$row ) {
		if ( '' !== (string) ( $row['note'] ?? '' ) ) {
			continue;
		}
		parse_str( (string) wp_parse_url( (string) ( $row['url'] ?? '' ), PHP_URL_QUERY ), $q );

		// A service note is the more specific of the two, so it goes first.
		$slug = isset( $q['svc'] ) ? (string) $q['svc'] : '';
		if ( '' !== $slug && '' !== note( $slug ) ) {
			$row['note'] = note( $slug );
			continue;
		}

		foreach ( (array) $q as $k => $v ) {
			$key = (string) $k . '=' . (string) $v;
			if ( isset( $by_filter[ $key ] ) ) {
				$row['note'] = $by_filter[ $key ];
				break;
			}
		}
	}
	unset( $row );
	return $rows;
}

/**
 * Every folded phrase that should resolve to a canonical slug.
 *
 * Reads the file and the terms, because a service added by hand in the
 * admin is a first-class service: its aliases have to match free text and
 * search queries exactly as the file's do. Canonical names are laid down
 * before any alias, so a term is never displaced by somebody else's
 * synonym, and the file wins ties because it is the reviewed source.
 *
 * @return array<string, string> folded phrase => service slug
 */
function lookup(): array {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map     = array();
	$aliases = array();

	foreach ( vocabulary() as $service ) {
		$map[ fold( $service['name'] ) ] = $service['slug'];
		foreach ( $service['aliases'] as $alias ) {
			$aliases[] = array( fold( $alias ), $service['slug'] );
		}
	}

	$terms = get_terms( array( 'taxonomy' => TAXONOMY, 'hide_empty' => false ) );
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$key = fold( $term->name );
		if ( ! isset( $map[ $key ] ) ) {
			$map[ $key ] = $term->slug;
		}
		foreach ( (array) get_term_meta( (int) $term->term_id, META_ALIAS, true ) as $alias ) {
			$aliases[] = array( fold( (string) $alias ), $term->slug );
		}
	}

	foreach ( $aliases as list( $key, $slug ) ) {
		if ( '' !== $key && ! isset( $map[ $key ] ) ) {
			$map[ $key ] = $slug;
		}
	}

	return $map;
}

/** Slugs the JSON manages, so the admin can say which are file-managed. */
function file_slugs(): array {
	return array_column( vocabulary(), 'slug' );
}

/** The canonical slug for a free-text phrase, or '' if we don't know it. */
function resolve( string $phrase ): string {
	$map = lookup();
	$key = fold( $phrase );
	return (string) ( $map[ $key ] ?? '' );
}

/**
 * A folded phrase with trailing words that name no modality removed.
 *
 * "meditation courses" is the meditation service; "yin classes" is yin. The
 * words come off one at a time and the first hit wins, so "sound bath
 * sessions" reaches "sound bath" without also trying "sound".
 */
function resolve_trimmed( string $folded ): string {
	static $tail = array( 'classes', 'class', 'sessions', 'session', 'courses', 'course' );

	$map   = lookup();
	$words = '' === $folded ? array() : explode( ' ', $folded );

	while ( $words && in_array( end( $words ), $tail, true ) ) {
		array_pop( $words );
		$key = implode( ' ', $words );
		if ( '' !== $key && isset( $map[ $key ] ) ) {
			return (string) $map[ $key ];
		}
	}
	return '';
}

/**
 * A folded phrase with leading words that only qualify the modality removed.
 *
 * "Fertility acupuncture" is acupuncture, "Chinese herbal medicine" is
 * herbal medicine, "Hot stone massage" is massage. The listing said which
 * kind, and the free-text field still says so; the canonical layer only has
 * to know which modality it was. 131 of the 702 strings that resolve to
 * nothing today are this shape.
 *
 * Words come off the front one at a time and the first hit wins, so
 * "Sports dietitian consultation" reaches "dietitian consultation" rather
 * than falling through to nothing.
 *
 * Some heads do not survive the trip, and they are the ones that name an
 * activity rather than a modality — there, the modifier was the meaning.
 * A swimming coach is not a life coach, a head spa is not a day spa,
 * NuCalm relaxation is not a relaxation massage. Those stay unresolved,
 * which is exactly where they are now; if any of them genuinely is the
 * registered service, it earns an explicit alias like every other term.
 */
function resolve_head( string $folded ): string {
	static $vague = array( 'coaching', 'counselling', 'spa', 'relaxation', 'training', 'therapy', 'healing' );

	$map   = lookup();
	$words = '' === $folded ? array() : explode( ' ', $folded );
	$count = count( $words );

	for ( $i = 1; $i < $count; $i++ ) {
		$key = implode( ' ', array_slice( $words, $i ) );
		if ( in_array( $key, $vague, true ) ) {
			return '';
		}
		if ( isset( $map[ $key ] ) ) {
			return (string) $map[ $key ];
		}
	}
	return '';
}

/**
 * Every service a phrase names, not just the one it matches whole.
 *
 * resolve() matches the entire folded phrase, which is right for "Pre &
 * post-natal yoga" — one service whose own name contains a conjunction —
 * and wrong for "Yin & vinyasa classes", which is two. Eleven studios
 * described vinyasa this way and the vinyasa page still read two listings,
 * because a phrase naming two things matched neither.
 *
 * The whole phrase is tried first and wins outright, so a registered name
 * containing "and", "&" or a slash is never taken apart. Only when nothing
 * matches whole is the phrase split, and each part must itself resolve —
 * a fragment that means nothing contributes nothing.
 *
 * Splitting happens on the decoded phrase rather than the folded one:
 * fold() turns "/" and "," into spaces, so "Yoga nidra / deep restore"
 * would arrive here as a single unsplittable run of words.
 *
 * @return string[] Unique slugs, in the order found. Empty if none.
 */
function resolve_all( string $phrase ): array {
	$whole = resolve( $phrase );
	if ( '' !== $whole ) {
		return array( $whole );
	}

	$trimmed = resolve_trimmed( fold( $phrase ) );
	if ( '' !== $trimmed ) {
		return array( $trimmed );
	}

	/*
	 * "with" joins two services as often as "and" does — "Yoga nidra with
	 * sound healing", "Finnish sauna with aufguss rituals". It is safe here
	 * only because the whole phrase is tried first: no registered name or
	 * alias contains the word, so nothing that resolves today can be split
	 * by it.
	 */
	$decoded = wp_specialchars_decode( $phrase, ENT_QUOTES );
	$parts   = preg_split( '/\s*(?:&|\/|\+|,|\band\b|\bwith\b)\s*/i', $decoded );

	$out = array();
	foreach ( (array) $parts as $part ) {
		$slug = resolve( (string) $part );
		if ( '' === $slug ) {
			$slug = resolve_trimmed( fold( (string) $part ) );
		}
		if ( '' === $slug ) {
			$slug = resolve_head( fold( (string) $part ) );
		}
		if ( '' !== $slug ) {
			$out[ $slug ] = true;
		}
	}

	if ( $out ) {
		return array_keys( $out );
	}

	$head = resolve_head( fold( $phrase ) );
	return '' === $head ? array() : array( $head );
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
			/*
			 * Compare decoded. WordPress stores "Lashes & brows" as
			 * "Lashes &amp; brows", so a raw comparison never matches and
			 * every sync would rewrite the term and report an update that
			 * changed nothing — a report nobody can trust is worse than no
			 * report.
			 */
			if ( wp_specialchars_decode( $term->name, ENT_QUOTES ) !== $service['name'] ) {
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
			$found = resolve_all( $name );
			if ( $found ) {
				foreach ( $found as $slug ) {
					$slugs[ $slug ] = true;
				}
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

/* ------------------------------------------------- term editor extensions */

/** The practice categories a service can belong to, for the checkboxes. */
function category_choices(): array {
	$terms = get_terms( array( 'taxonomy' => \Oria\Core\Taxonomies\PRACTICE, 'hide_empty' => false ) );
	$out   = array();
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$out[ $term->slug ] = wp_specialchars_decode( $term->name, ENT_QUOTES );
	}
	asort( $out );
	return $out;
}

function fields_markup( array $aliases, array $categories ): void {
	printf(
		'<p class="description" style="margin-bottom:.6em">%s</p><textarea name="oria_aliases" rows="3" class="large-text" placeholder="%s">%s</textarea>',
		esc_html__( 'Other ways people write this service, one per line. These match a practice\'s own wording and, later, what visitors type into search — "yin" finding Yin yoga is an alias doing its job.', 'oria' ),
		esc_attr__( "sound bath\ngong bath\nsinging bowls", 'oria' ),
		esc_textarea( implode( "\n", $aliases ) )
	);

	echo '<fieldset style="margin-top:1em"><legend class="description" style="margin-bottom:.5em">'
		. esc_html__( 'Categories this service belongs to. A service can sit in several — these decide which category shows it as a filter.', 'oria' )
		. '</legend><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(15em,1fr));gap:.3em">';
	foreach ( category_choices() as $slug => $label ) {
		printf(
			'<label><input type="checkbox" name="oria_categories[]" value="%s"%s> %s</label>',
			esc_attr( $slug ),
			checked( in_array( $slug, $categories, true ), true, false ),
			esc_html( $label )
		);
	}
	echo '</div></fieldset>';
}

function add_fields(): void {
	echo '<div class="form-field"><label>' . esc_html__( 'Also matches', 'oria' ) . '</label>';
	wp_nonce_field( 'oria_service_fields', 'oria_service_nonce' );
	fields_markup( array(), array() );
	echo '</div>';
}

function edit_fields( \WP_Term $term ): void {
	$aliases    = (array) get_term_meta( (int) $term->term_id, META_ALIAS, true );
	$categories = (array) get_term_meta( (int) $term->term_id, 'oria_categories', true );

	echo '<tr class="form-field"><th scope="row"><label>' . esc_html__( 'Also matches', 'oria' ) . '</label></th><td>';
	wp_nonce_field( 'oria_service_fields', 'oria_service_nonce' );

	if ( in_array( $term->slug, file_slugs(), true ) ) {
		printf(
			'<div class="notice notice-info inline" style="margin:0 0 1em"><p>%s</p></div>',
			esc_html__( 'This service is defined in data/services.json, so the next sync will overwrite anything changed here. Edit the file to make it stick — or add a new service instead, which syncing never touches.', 'oria' )
		);
	}

	fields_markup( array_map( 'strval', $aliases ), array_map( 'strval', $categories ) );
	echo '</td></tr>';
}

function save_fields( int $term_id ): void {
	if ( ! isset( $_POST['oria_service_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( (string) $_POST['oria_service_nonce'] ) ), 'oria_service_fields' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	$raw     = isset( $_POST['oria_aliases'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['oria_aliases'] ) ) : '';
	$aliases = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ?: array() ) ) );
	update_term_meta( $term_id, META_ALIAS, $aliases );

	$cats = isset( $_POST['oria_categories'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['oria_categories'] ) ) : array();
	update_term_meta( $term_id, 'oria_categories', $cats );
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

/**
 * Turn an unrecognised phrase into a service, in one press.
 *
 * The unmatched list is already ranked by how many practices wrote the
 * phrase, which makes it the most honest shortlist there is: the
 * vocabulary grows from what businesses actually offer rather than from
 * anybody's idea of what a wellness directory ought to contain. The
 * phrase becomes both the name and the first alias, so the listings that
 * prompted it attach on the next scan.
 */
function handle_promote(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot do that.', 'oria' ) );
	}
	check_admin_referer( 'oria_service_promote' );

	$phrase = isset( $_POST['phrase'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['phrase'] ) ) : '';
	$made   = '';

	if ( '' !== $phrase && '' === resolve( $phrase ) ) {
		$term = wp_insert_term( $phrase, TAXONOMY );
		if ( ! is_wp_error( $term ) ) {
			update_term_meta( (int) $term['term_id'], META_ALIAS, array( $phrase ) );
			update_term_meta( (int) $term['term_id'], 'oria_categories', array() );
			$made = $phrase;
			// Re-scan so the listings that prompted it attach immediately;
			// a term nobody can see attached to anything looks broken.
			$report = get_transient( 'oria_services_report' );
			$map    = map_listings();
			set_transient(
				'oria_services_report',
				array( 'terms' => is_array( $report ) ? $report['terms'] : array( 'created' => 0, 'updated' => 0, 'unchanged' => 0 ), 'map' => $map ),
				HOUR_IN_SECONDS
			);
		}
	}

	wp_safe_redirect(
		add_query_arg(
			array( 'promoted' => rawurlencode( $made ) ),
			admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=oria-services' )
		)
	);
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

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$promoted = isset( $_GET['promoted'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['promoted'] ) ) : '';
	if ( '' !== $promoted ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: the phrase that became a service */
					__( '"%s" is now a service, and the listings that used the phrase are attached. Open it under Services to add other spellings or put it in a category.', 'oria' ),
					$promoted
				)
			)
		);
	}

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
			echo '<table class="widefat striped" style="max-width:66ch"><thead><tr><th>' . esc_html__( 'Phrase', 'oria' ) . '</th><th style="width:8em">' . esc_html__( 'Listings', 'oria' ) . '</th><th style="width:11em"></th></tr></thead><tbody>';
			$shown = 0;
			foreach ( $un as $phrase => $n ) {
				if ( $shown++ >= 60 ) {
					break;
				}
				printf(
					'<tr><td>%s</td><td>%d</td><td><form method="post" action="%s" style="margin:0">%s'
					. '<input type="hidden" name="action" value="oria_service_promote">'
					. '<input type="hidden" name="phrase" value="%s">'
					. '<button class="button button-small">%s</button></form></td></tr>',
					esc_html( $phrase ),
					(int) $n,
					esc_url( admin_url( 'admin-post.php' ) ),
					wp_nonce_field( 'oria_service_promote', '_wpnonce', true, false ),
					esc_attr( $phrase ),
					esc_html__( 'Add as service', 'oria' )
				);
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
