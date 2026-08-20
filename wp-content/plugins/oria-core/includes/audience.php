<?php
/**
 * Who a practice is for.
 *
 * The directory has always held what a practice does and where it is. It
 * has never held who it suits, which is the missing half of most of the
 * questions people actually ask — "beginner yoga in Perth", "somewhere my
 * mum could go", "can I bring my kids".
 *
 * We tried to derive it. It does not derive. Keyword-matching the whole
 * published corpus for eleven audience signals returned a beginner hit on 6
 * of 142 listings, and four of those six were service names rather than
 * anything about audience. The cause is structural: a listing holds a
 * 45-word blurb and a short list of service names, and nothing in that
 * encodes who a class is for. There is no text to mine.
 *
 * Which leaves the two honest routes, and this file serves both:
 *
 *   - The owner says so. Claimed practitioners edit their own listing in
 *     wp-admin, so the terms and the evidence box appear there.
 *   - Somebody checks and records where they looked. That is what
 *     `wp oria audience apply` is for.
 *
 * Two rules the rest of the system depends on.
 *
 * EVIDENCE OR NOTHING. Every assignment carries a source URL and the words
 * that justify it. An audience term is Oria Haven asserting something about
 * a named real business; an unsourced one is a guess with a checkbox around
 * it. assign() refuses without a source.
 *
 * ABSENCE IS NOT A NEGATIVE. A listing without `beginners` means nobody has
 * checked — not that beginners are unwelcome. This matters more than it
 * sounds: a "beginner-friendly yoga in Perth" page built at 4% coverage
 * lists six studios and implies the other hundred-odd turn beginners away,
 * which is false and is our error rather than theirs. coverage() exists so
 * that a page is only ever built where the checking has actually been done,
 * and nothing here may be phrased as an exclusion.
 *
 * The taxonomy is private, like services. It creates no URLs. Pages come
 * later, behind a coverage threshold, as a separate decision.
 */

declare(strict_types=1);

namespace Oria\Core\Audience;

use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TAXONOMY  = 'audience';
const META      = '_oria_audience_evidence';
const DATA_FILE = 'data/audiences.json';

/**
 * The share of a set that must be checked before that set is fit to build a
 * page from. Not a share that must be *true* — a share that must have been
 * looked at, either way.
 */
const COVERAGE_THRESHOLD = 0.8;

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register', 7 );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\meta_box' );
	add_action( 'save_post_' . PostTypes\LISTING, __NAMESPACE__ . '\save', 10, 2 );
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_audience_sync', __NAMESPACE__ . '\handle_sync' );
}

function register(): void {
	register_taxonomy(
		TAXONOMY,
		array( PostTypes\LISTING ),
		array(
			'labels'            => array(
				'name'          => __( 'Audiences', 'oria' ),
				'singular_name' => __( 'Audience', 'oria' ),
				'all_items'     => __( 'All audiences', 'oria' ),
				'edit_item'     => __( 'Edit audience', 'oria' ),
				'add_new_item'  => __( 'Add audience', 'oria' ),
				'search_items'  => __( 'Search audiences', 'oria' ),
			),
			// Private for the same reason services are: a public taxonomy
			// mints a URL per term the moment it is seeded, and an audience
			// page at 4% coverage is worse than no page at all.
			'public'            => false,
			'publicly_queryable' => false,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => false,
			'rewrite'           => false,
		)
	);
}

/* ---------------------------------------------------------- the vocabulary */

/**
 * The canonical list, from data/audiences.json.
 *
 * @return array<string, array{slug: string, name: string, question: string, test: string}>
 */
function vocabulary(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$cache = array();
	$path  = ORIA_CORE_DIR . DATA_FILE;

	if ( ! is_readable( $path ) ) {
		return $cache;
	}

	$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( (array) ( $json['audiences'] ?? array() ) as $row ) {
		$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		if ( '' !== $slug ) {
			$cache[ $slug ] = array(
				'slug'     => $slug,
				'name'     => (string) ( $row['name'] ?? $slug ),
				'question' => (string) ( $row['question'] ?? '' ),
				'test'     => (string) ( $row['test'] ?? '' ),
			);
		}
	}

	return $cache;
}

/**
 * Create or rename terms so the taxonomy matches the file.
 *
 * Nothing is ever deleted. A term removed from the file keeps its listings
 * and its evidence; dropping it would throw away checking somebody did.
 *
 * @return array{created: int, renamed: int, unchanged: int}
 */
function sync_terms(): array {
	$out = array( 'created' => 0, 'renamed' => 0, 'unchanged' => 0 );

	foreach ( vocabulary() as $slug => $row ) {
		$term = get_term_by( 'slug', $slug, TAXONOMY );

		if ( ! $term instanceof \WP_Term ) {
			$made = wp_insert_term( $row['name'], TAXONOMY, array( 'slug' => $slug, 'description' => $row['test'] ) );
			if ( ! is_wp_error( $made ) ) {
				$out['created']++;
			}
			continue;
		}

		// Decoded, because WordPress stores "Women's sessions" with an
		// encoded apostrophe and a raw comparison would rename for ever.
		if ( wp_specialchars_decode( $term->name, ENT_QUOTES ) !== $row['name'] || $term->description !== $row['test'] ) {
			wp_update_term( $term->term_id, TAXONOMY, array( 'name' => $row['name'], 'description' => $row['test'] ) );
			$out['renamed']++;
		} else {
			$out['unchanged']++;
		}
	}

	return $out;
}

/* -------------------------------------------------------------- evidence */

/**
 * The evidence behind every audience term on a listing.
 *
 * @return array<string, array{source: string, quote: string, checked: string, by: string}>
 */
function evidence( int $post_id ): array {
	$raw = get_post_meta( $post_id, META, true );
	return is_array( $raw ) ? $raw : array();
}

/**
 * Record one audience term against a listing, with the source that justifies
 * it.
 *
 * Refuses without a source. That is the whole point of the field: the term
 * is a claim about somebody else's business, and a claim we cannot show the
 * working for is one we should not be publishing.
 *
 * @param string $by 'owner' when the practice said so, 'staff' when somebody
 *                   checked and wrote down where they looked.
 * @return true|\WP_Error
 */
function assign( int $post_id, string $slug, string $source, string $quote = '', string $by = 'staff' ) {
	$slug = sanitize_title( $slug );

	if ( ! isset( vocabulary()[ $slug ] ) ) {
		return new \WP_Error( 'oria_audience_unknown', sprintf( 'No audience term "%s" in the vocabulary.', $slug ) );
	}

	$source = esc_url_raw( trim( $source ) );
	if ( '' === $source && 'owner' !== $by ) {
		return new \WP_Error(
			'oria_audience_unsourced',
			sprintf( '"%s" needs a source URL. An audience term is a claim about a real business.', $slug )
		);
	}

	$term = get_term_by( 'slug', $slug, TAXONOMY );
	if ( ! $term instanceof \WP_Term ) {
		return new \WP_Error( 'oria_audience_missing_term', sprintf( 'Term "%s" does not exist yet — run the sync first.', $slug ) );
	}

	wp_set_object_terms( $post_id, array( (int) $term->term_id ), TAXONOMY, true );

	$all = evidence( $post_id );
	$all[ $slug ] = array(
		'source'  => $source,
		'quote'   => sanitize_text_field( $quote ),
		'checked' => gmdate( 'Y-m-d' ),
		'by'      => 'owner' === $by ? 'owner' : 'staff',
	);
	update_post_meta( $post_id, META, $all );

	return true;
}

/**
 * Mark a listing as checked for an audience term and found not to apply.
 *
 * The reason this exists: without it, "no" and "nobody looked" are the same
 * state, and coverage() cannot tell a set that has been worked through from
 * one nobody has touched. A checked negative is real information.
 */
function record_absent( int $post_id, string $slug, string $source ): void {
	$slug = sanitize_title( $slug );
	$all  = evidence( $post_id );

	$all[ $slug ] = array(
		'source'  => esc_url_raw( trim( $source ) ),
		'quote'   => '',
		'checked' => gmdate( 'Y-m-d' ),
		'by'      => 'staff',
		'absent'  => true,
	);
	update_post_meta( $post_id, META, $all );
}

/** Has anyone looked at this listing for this term, either way? */
function checked( int $post_id, string $slug ): bool {
	return isset( evidence( $post_id )[ sanitize_title( $slug ) ] );
}

/* -------------------------------------------------------------- coverage */

/**
 * How thoroughly a set of listings has been checked for one audience term.
 *
 * The gate on ever building a page from this data. `checked` counts every
 * listing somebody has looked at — yes and no alike — because a page may
 * only claim a comparison across a set that was actually worked through.
 *
 * @param list<int> $post_ids
 * @return array{total: int, checked: int, yes: int, share: float, publishable: bool}
 */
function coverage( array $post_ids, string $slug ): array {
	$slug    = sanitize_title( $slug );
	$total   = count( $post_ids );
	$done    = 0;
	$yes     = 0;

	foreach ( $post_ids as $id ) {
		$row = evidence( (int) $id )[ $slug ] ?? null;
		if ( null === $row ) {
			continue;
		}
		$done++;
		if ( empty( $row['absent'] ) ) {
			$yes++;
		}
	}

	$share = $total > 0 ? $done / $total : 0.0;

	return array(
		'total'       => $total,
		'checked'     => $done,
		'yes'         => $yes,
		'share'       => $share,
		'publishable' => $total > 0 && $share >= COVERAGE_THRESHOLD && $yes >= 3,
	);
}

/** Every published listing in a practice term, for coverage(). */
function listings_in_practice( string $practice_slug ): array {
	return get_posts(
		array(
			'post_type'      => PostTypes\LISTING,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy'         => Taxonomies\PRACTICE,
					'field'            => 'slug',
					'terms'            => $practice_slug,
					'include_children' => true,
				),
			),
		)
	);
}

/* ------------------------------------------------------------- edit screen */

function meta_box(): void {
	add_meta_box(
		'oria-audience-evidence',
		__( 'Who this practice is for', 'oria' ),
		__NAMESPACE__ . '\render_box',
		PostTypes\LISTING,
		'normal',
		'default'
	);
}

/**
 * The evidence box.
 *
 * One line per audience term: `slug | source URL | the words that say so`.
 * A textarea rather than a repeater for the same reason the FAQ override is
 * one — no ACF dependency, no new tables, and an empty box means untouched
 * rather than deliberately blank.
 *
 * The vocabulary is printed underneath with the question each term answers,
 * because the person filling this in is often the practice owner and the
 * whole exercise fails if they guess at what "families" means.
 */
function render_box( \WP_Post $post ): void {
	wp_nonce_field( 'oria_audience_save', 'oria_audience_nonce' );

	$lines = array();
	foreach ( evidence( $post->ID ) as $slug => $row ) {
		$lines[] = sprintf(
			'%s | %s | %s',
			empty( $row['absent'] ) ? $slug : '-' . $slug,
			$row['source'] ?? '',
			$row['quote'] ?? ''
		);
	}

	echo '<p style="margin-top:0">';
	echo esc_html__( 'One line per answer: slug | link to the page that says so | the words themselves.', 'oria' );
	echo '<br>';
	echo esc_html__( 'Prefix a slug with a minus to record that you checked and it does not apply — that is real information, and it is not the same as leaving it out.', 'oria' );
	echo '</p>';

	printf(
		'<textarea name="oria_audience_evidence" rows="%d" style="width:100%%;font-family:monospace" placeholder="%s">%s</textarea>',
		max( 4, count( $lines ) + 2 ),
		esc_attr__( 'beginners | https://example.com/classes | "New to yoga? Start with our four-week beginners course."', 'oria' ),
		esc_textarea( implode( "\n", $lines ) )
	);

	echo '<table class="widefat striped" style="margin-top:1rem"><thead><tr>';
	printf( '<th style="width:11rem">%s</th><th>%s</th><th>%s</th>', esc_html__( 'Slug', 'oria' ), esc_html__( 'The question it answers', 'oria' ), esc_html__( 'What counts as a yes', 'oria' ) );
	echo '</tr></thead><tbody>';
	foreach ( vocabulary() as $slug => $row ) {
		printf(
			'<tr><td><code>%s</code></td><td>%s</td><td style="color:#666">%s</td></tr>',
			esc_html( $slug ),
			esc_html( $row['question'] ),
			esc_html( $row['test'] )
		);
	}
	echo '</tbody></table>';
}

function save( int $post_id, \WP_Post $post ): void {
	if ( ! isset( $_POST['oria_audience_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( (string) $_POST['oria_audience_nonce'] ), 'oria_audience_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$raw = (string) wp_unslash( $_POST['oria_audience_evidence'] ?? '' );

	$keep    = array();
	$term_ids = array();

	foreach ( preg_split( '/\R/', $raw ) ?: array() as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 3 ) );
		$slug  = $parts[0] ?? '';

		if ( '' === $slug ) {
			continue;
		}

		$absent = str_starts_with( $slug, '-' );
		$slug   = sanitize_title( ltrim( $slug, '-' ) );

		if ( ! isset( vocabulary()[ $slug ] ) ) {
			continue;
		}

		$row = array(
			'source'  => esc_url_raw( $parts[1] ?? '' ),
			'quote'   => sanitize_text_field( $parts[2] ?? '' ),
			'checked' => gmdate( 'Y-m-d' ),
			// Edited on the listing screen: either the owner or a member of
			// staff, and owned_listing() is the thing that knows which.
			'by'      => \Oria\Core\Ownership\manages( get_current_user_id(), $post_id ) && ! current_user_can( 'manage_options' ) ? 'owner' : 'staff',
		);

		if ( $absent ) {
			$row['absent'] = true;
		} else {
			$term = get_term_by( 'slug', $slug, TAXONOMY );
			if ( $term instanceof \WP_Term ) {
				$term_ids[] = (int) $term->term_id;
			}
		}

		$keep[ $slug ] = $row;
	}

	wp_set_object_terms( $post_id, $term_ids, TAXONOMY, false );

	if ( $keep ) {
		update_post_meta( $post_id, META, $keep );
	} else {
		delete_post_meta( $post_id, META );
	}
}

/* ------------------------------------------------------------ admin screen */

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=' . PostTypes\LISTING,
		__( 'Audience coverage', 'oria' ),
		__( 'Audience coverage', 'oria' ),
		'manage_options',
		'oria-audience',
		__NAMESPACE__ . '\screen'
	);
}

function handle_sync(): void {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'oria_audience_sync' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ) );
	}

	$r = sync_terms();

	wp_safe_redirect(
		add_query_arg(
			array( 'page' => 'oria-audience', 'synced' => rawurlencode( sprintf( '%d created, %d updated, %d unchanged', $r['created'], $r['renamed'], $r['unchanged'] ) ) ),
			admin_url( 'edit.php?post_type=' . PostTypes\LISTING )
		)
	);
	exit;
}

/**
 * Coverage per category, per audience term.
 *
 * Reads as a checking worklist rather than a results table, because that is
 * what it is for. The number that matters is how much of a set has been
 * looked at — a column of zeroes with 100% checked is a finished job and a
 * publishable fact; a column of zeroes with 0% checked is a job nobody has
 * started, and the two must never look alike.
 */
function screen(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$vocab      = vocabulary();
	$categories = get_terms( array( 'taxonomy' => Taxonomies\PRACTICE, 'parent' => 0, 'hide_empty' => false ) );
	$categories = is_wp_error( $categories ) ? array() : $categories;

	echo '<div class="wrap"><h1>' . esc_html__( 'Audience coverage', 'oria' ) . '</h1>';

	if ( isset( $_GET['synced'] ) ) {
		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( sanitize_text_field( wp_unslash( (string) $_GET['synced'] ) ) ) );
	}

	printf(
		'<form method="post" action="%s" style="margin:1rem 0">%s<input type="hidden" name="action" value="oria_audience_sync"><button class="button button-primary">%s</button></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		wp_nonce_field( 'oria_audience_sync', '_wpnonce', true, false ), // phpcs:ignore WordPress.Security.EscapeOutput
		esc_html__( 'Sync audience terms', 'oria' )
	);

	echo '<p>' . esc_html__( 'Each cell is: how many of that category have been checked for that audience, and how many were a yes. A cell only becomes a page when 80% of the category has been checked and at least three are a yes — because a page built on a half-checked set implies the unchecked ones are a no.', 'oria' ) . '</p>';

	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Category', 'oria' ) . '</th><th>' . esc_html__( 'Listings', 'oria' ) . '</th>';
	foreach ( $vocab as $slug => $row ) {
		printf( '<th title="%s" style="writing-mode:vertical-rl;height:9rem">%s</th>', esc_attr( $row['test'] ), esc_html( $row['name'] ) );
	}
	echo '</tr></thead><tbody>';

	foreach ( $categories as $cat ) {
		$ids = listings_in_practice( $cat->slug );
		if ( ! $ids ) {
			continue;
		}

		printf(
			'<tr><td><strong>%s</strong></td><td>%d</td>',
			esc_html( wp_specialchars_decode( $cat->name, ENT_QUOTES ) ),
			count( $ids )
		);

		foreach ( array_keys( $vocab ) as $slug ) {
			$c = coverage( $ids, $slug );

			if ( 0 === $c['checked'] ) {
				echo '<td style="color:#b32d2e">&mdash;</td>';
				continue;
			}

			printf(
				'<td style="%s">%d/%d checked<br><strong>%d yes</strong></td>',
				$c['publishable'] ? 'background:#edfaef' : '',
				(int) $c['checked'],
				(int) $c['total'],
				(int) $c['yes']
			);
		}

		echo '</tr>';
	}

	echo '</tbody></table>';
	echo '<p style="color:#b32d2e">' . esc_html__( 'A dash means nobody has looked. It does not mean no.', 'oria' ) . '</p>';
	echo '</div>';
}

/* ------------------------------------------------------------------- CLI */

if ( defined( 'WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command(
		'oria audience',
		/**
		 * Apply a researched batch of audience answers.
		 *
		 * The file is a record of somebody having looked. Each row names a
		 * listing, an audience slug, the page that was read and the words on
		 * it. Rows may be negative — `"absent": true` — and those are as
		 * valuable as the positives, because a checked no is what separates a
		 * finished category from an untouched one.
		 *
		 * Nothing is inferred here. A row without a source is refused, and a
		 * listing that cannot be matched by slug is reported rather than
		 * guessed at.
		 */
		new class() {
			/**
			 * ## OPTIONS
			 *
			 * <file>
			 * : Path to the JSON file of answers.
			 *
			 * [--dry-run]
			 * : Report what would change without writing anything.
			 *
			 * ## EXAMPLES
			 *
			 *     wp oria audience apply audience-yoga.json --dry-run
			 *     wp oria audience apply audience-yoga.json
			 */
			public function apply( array $args, array $assoc ): void {
				list( $file ) = $args;
				$dry = isset( $assoc['dry-run'] );

				if ( ! file_exists( $file ) ) {
					\WP_CLI::error( "File not found: {$file}" );
				}

				$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( ! is_array( $data ) || empty( $data['answers'] ) ) {
					\WP_CLI::error( 'Not a valid answers file: expected a top-level "answers" list.' );
				}

				if ( $dry ) {
					\WP_CLI::log( '- DRY RUN: nothing will be written -' );
				}

				$sync = sync_terms();
				\WP_CLI::log( sprintf( 'Vocabulary: %d created, %d updated, %d unchanged.', $sync['created'], $sync['renamed'], $sync['unchanged'] ) );

				$yes = 0;
				$no  = 0;
				$bad = 0;

				foreach ( $data['answers'] as $row ) {
					$slug_l = sanitize_title( (string) ( $row['listing'] ?? '' ) );
					$post   = $slug_l ? get_page_by_path( $slug_l, OBJECT, PostTypes\LISTING ) : null;

					if ( ! $post instanceof \WP_Post ) {
						\WP_CLI::warning( sprintf( 'No listing with slug "%s" - skipped.', $slug_l ) );
						$bad++;
						continue;
					}

					$slug_a = sanitize_title( (string) ( $row['audience'] ?? '' ) );
					$source = (string) ( $row['source'] ?? '' );
					$absent = ! empty( $row['absent'] );

					if ( '' === $source ) {
						\WP_CLI::warning( sprintf( '%s / %s has no source - skipped.', $slug_l, $slug_a ) );
						$bad++;
						continue;
					}

					if ( $dry ) {
						\WP_CLI::log( sprintf( '  %s %s <- %s', $absent ? 'no ' : 'YES', str_pad( $slug_a, 12 ), $post->post_title ) );
						$absent ? $no++ : $yes++;
						continue;
					}

					if ( $absent ) {
						record_absent( (int) $post->ID, $slug_a, $source );
						$no++;
						continue;
					}

					$done = assign( (int) $post->ID, $slug_a, $source, (string) ( $row['quote'] ?? '' ), 'staff' );
					if ( is_wp_error( $done ) ) {
						\WP_CLI::warning( sprintf( '%s / %s: %s', $slug_l, $slug_a, $done->get_error_message() ) );
						$bad++;
						continue;
					}
					$yes++;
				}

				\WP_CLI::success( sprintf( '%d yes, %d checked-and-no, %d skipped.', $yes, $no, $bad ) );
			}
		}
	);
}
