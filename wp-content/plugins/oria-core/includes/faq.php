<?php
/**
 * FAQ blocks for the landing pages.
 *
 * Category, specialty and area pages are otherwise the same directory
 * engine with different facets locked, which leaves them thin on unique
 * text. These FAQs fix that by answering the questions from live data —
 * how many practices, which suburbs, what they charge — so every page
 * says something true and specific to itself, and says something
 * different tomorrow when the counts move.
 *
 * A note on expectations: Google withdrew FAQ rich results for
 * general sites in 2023, so the FAQPage markup here will not put an
 * accordion in the search listing. It stays because it is still read by
 * answer engines, and because the questions are worth answering for the
 * person on the page regardless of what the SERP does with them.
 *
 * Hard rule, same as listing copy: nothing here may imply a modality
 * treats a condition. Questions cover availability, geography, price and
 * how the directory works. Never outcomes.
 */

declare(strict_types=1);

namespace Oria\Core\Faq;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_OVERRIDE = '_oria_faq';
const MIN_SAMPLE    = 3;

function bootstrap(): void {
	foreach ( array( Taxonomies\PRACTICE, Taxonomies\SPECIALTY, Taxonomies\AREA ) as $tax ) {
		add_action( $tax . '_edit_form_fields', __NAMESPACE__ . '\field', 10, 1 );
		add_action( 'edited_' . $tax, __NAMESPACE__ . '\save' );
	}
}

/* ------------------------------------------------------------- generation */

/**
 * The FAQ pairs for a term: the editor's own list if they've written one,
 * otherwise generated from the listings currently in that term.
 *
 * @return list<array{q: string, a: string}>
 */
function for_term( \WP_Term $term ): array {
	$manual = parse_override( (string) get_term_meta( $term->term_id, META_OVERRIDE, true ) );
	if ( $manual ) {
		return $manual;
	}
	$rows = matching( $term );
	if ( count( $rows ) < MIN_SAMPLE ) {
		// Too few listings to say anything specific, and a page that thin
		// should not be padded with filler.
		return array();
	}
	return Taxonomies\AREA === $term->taxonomy
		? area_faqs( $term, $rows )
		: practice_faqs( $term, $rows );
}

/**
 * Listings in this term, as rows from the theme's directory dataset.
 *
 * @return list<array<string, mixed>>
 */
function matching( \WP_Term $term ): array {
	if ( ! function_exists( '\Oria\Theme\listing_data' ) ) {
		return array();
	}
	$all  = \Oria\Theme\listing_data()['listings'] ?? array();
	$out  = array();
	$slug = $term->slug;

	/*
	 * The city this page belongs to. An area term carries its own city; a
	 * practice or specialty page takes the one it is being viewed under.
	 * Without this the generated answers counted the whole corpus and the
	 * spa page told Google "89 practices across 58 suburbs" above a list of
	 * 82 -- the same unfiltered-count fault the ItemList had.
	 */
	$city = '';
	if ( function_exists( '\Oria\Core\Cities\for_area' ) ) {
		$info = Taxonomies\AREA === $term->taxonomy
			? \Oria\Core\Cities\for_area( $term )
			: \Oria\Core\Cities\current();
		$city = (string) ( $info['slug'] ?? '' );
	}

	foreach ( $all as $row ) {
		if ( '' !== $city && isset( $row['city'] ) && $row['city'] !== $city ) {
			continue;
		}
		$hit = false;
		switch ( $term->taxonomy ) {
			case Taxonomies\PRACTICE:
				$hit = ( $row['cat'] ?? '' ) === $slug || in_array( $slug, (array) ( $row['also'] ?? array() ), true );
				break;
			case Taxonomies\SPECIALTY:
				$hit = in_array( $slug, (array) ( $row['spec'] ?? array() ), true );
				break;
			case Taxonomies\AREA:
				// is_suburb(), not a parent check: regions gained a parent when the
				// city level was inserted, and a region would otherwise be matched
				// against suburb names and find nothing.
				$hit = Taxonomies\is_suburb( $term )
					? sanitize_title( (string) ( $row['suburb'] ?? '' ) ) === $slug
					: ( $row['region'] ?? '' ) === $slug;
				break;
		}
		if ( $hit ) {
			$out[] = $row;
		}
	}
	return $out;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array{q: string, a: string}>
 */
function practice_faqs( \WP_Term $term, array $rows ): array {
	$name  = strtolower( decoded( $term->name ) );
	$total = count( $rows );
	$faqs  = array();

	$suburbs = tally( $rows, 'suburb' );

	/*
	 * Every question below keeps the category name as the object of a verb
	 * — "offer X", "book X", "find X" — and never bolts a noun onto the
	 * end of it. Category names are inconsistent in number ("Allied
	 * Health", "Meditation classes", "Sound & float"), so anything of the
	 * form "{name} practices" or "{name} sessions" produces garbage for
	 * roughly half the directory.
	 */
	$faqs[] = array(
		'q' => sprintf( 'How many practices offer %s in Perth?', $name ),
		'a' => sprintf(
			'Oria Haven lists %1$s across %2$s in the Perth metro. Every entry is checked by hand before it goes live, and the list is updated as practices open, move or close.',
			plural( $total, 'practice', 'practices' ),
			plural( count( $suburbs ), 'suburb', 'suburbs' )
		),
	);

	// "Most choice" has to mean something. A suburb holding one practice is
	// not a choice, so the question only runs where there is a real cluster.
	$clusters = array_slice( array_filter( $suburbs, static fn( $n ) => $n >= 2 ), 0, 3, true );
	if ( $clusters ) {
		$faqs[] = array(
			'q' => sprintf( 'Where in Perth can I find %s?', $name ),
			'a' => sprintf(
				'%1$s across %2$s. The most choice is in %3$s — use the area filter on this page to narrow it to the side of town you can actually get to.',
				ucfirst( plural( $total, 'practice', 'practices' ) ),
				plural( count( $suburbs ), 'suburb', 'suburbs' ),
				oxford( array_map( static fn( $s ) => sprintf( '%s (%d)', $s, $clusters[ $s ] ), array_keys( $clusters ) ) )
			),
		);
	}

	$prices = prices( $rows );
	if ( count( $prices ) >= MIN_SAMPLE ) {
		$faqs[] = array(
			'q' => sprintf( 'What does it cost to book %s in Perth?', $name ),
			'a' => sprintf(
				'Of the %1$d practices here that publish a starting price, sessions begin from %2$s, with a typical starting price around %3$s. Prices come from the practices themselves and change without notice — confirm before you book.',
				count( $prices ),
				money( min( $prices ) ),
				money( median( $prices ) )
			),
		);
	}

	// "Free yoga in Perth" is a real search with real intent, and the answer
	// is sitting in the price band nobody queries.
	$free = free_count( $rows );
	if ( $free >= 2 ) {
		$faqs[] = array(
			'q' => sprintf( 'Can I find free %s in Perth?', $name ),
			'a' => sprintf(
				'%1$d of the %2$d practices listed run sessions that are free or by donation. Sort by price on this page to bring them to the top.',
				$free,
				$total
			),
		);
	}

	$online = count( array_filter( $rows, static fn( $r ) => in_array( $r['format'] ?? '', array( 'online', 'both' ), true ) ) );
	if ( $online > 0 ) {
		$faqs[] = array(
			'q' => sprintf( 'Can I book %s online in Perth?', $name ),
			'a' => sprintf(
				'Yes — %1$d of the %2$d practices listed offer online or hybrid sessions. Filter by format above to see only those.',
				$online,
				$total
			),
		);
	}

	$faqs[] = editorial_faq();
	return $faqs;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array{q: string, a: string}>
 */
function area_faqs( \WP_Term $term, array $rows ): array {
	$place = decoded( $term->name );
	$total = count( $rows );
	$cats  = tally( $rows, 'cat' );
	$faqs  = array();

	$labels = array();
	foreach ( array_slice( array_keys( $cats ), 0, 4 ) as $slug ) {
		$t = get_term_by( 'slug', $slug, Taxonomies\PRACTICE );
		if ( $t instanceof \WP_Term ) {
			$labels[] = sprintf( '%s (%d)', strtolower( decoded( $t->name ) ), $cats[ $slug ] );
		}
	}

	$faqs[] = array(
		'q' => sprintf( 'What wellness practices are there in %s?', $place ),
		'a' => $labels
			? sprintf( 'We list %1$s in %2$s, most commonly %3$s.', plural( $total, 'practice', 'practices' ), $place, oxford( $labels ) )
			: sprintf( 'We list %s in %s.', plural( $total, 'practice', 'practices' ), $place ),
	);

	$prices = prices( $rows );
	if ( count( $prices ) >= MIN_SAMPLE ) {
		$faqs[] = array(
			'q' => sprintf( 'What do wellness sessions cost in %s?', $place ),
			'a' => sprintf(
				'Among the %1$s practices that publish a starting price, sessions begin from %2$s, with a typical starting price around %3$s. Prices are set by each practice and change without notice.',
				$place,
				money( min( $prices ) ),
				money( median( $prices ) )
			),
		);
	}

	$free = free_count( $rows );
	if ( $free >= 2 ) {
		$faqs[] = array(
			'q' => sprintf( 'Is there anything free in %s?', $place ),
			'a' => sprintf(
				'%1$d of the %2$d practices listed in %3$s run sessions that are free or by donation.',
				$free,
				$total,
				$place
			),
		);
	}

	$faqs[] = editorial_faq();
	return $faqs;
}

/**
 * The FAQ for pages that cover the whole directory rather than one term.
 *
 * for_term() needs a term, which the /perth/ hub and the directory archive
 * do not have — they are the two widest pages on the site and carried no
 * unique prose at all. Same rules as everywhere else: geography,
 * availability, price, and how the directory works. Never outcomes.
 *
 * @return list<array{q: string, a: string}>
 */
function site_faq(): array {
	$listings = (int) ( wp_count_posts( 'listing' )->publish ?? 0 );
	if ( $listings < MIN_SAMPLE ) {
		return array();
	}

	$categories = (int) wp_count_terms( array( 'taxonomy' => Taxonomies\PRACTICE, 'hide_empty' => true ) );

	$regions = get_terms(
		array(
			'taxonomy'   => Taxonomies\AREA,
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	$names = array();
	foreach ( is_wp_error( $regions ) ? array() : $regions as $region ) {
		$names[] = decoded( $region->name );
	}

	$faqs = array(
		array(
			'q' => 'How many wellness practices are listed in Perth?',
			'a' => sprintf(
				'Oria Haven lists %d practices across %d categories, from meditation and yoga to remedial massage, breathwork, sound and float, allied health and outdoor wellness. Every one is checked by hand before it goes up, and the number keeps moving as we work through the city.',
				$listings,
				$categories
			),
		),
	);

	if ( $names ) {
		$faqs[] = array(
			'q' => 'Which parts of Perth does Oria Haven cover?',
			'a' => sprintf(
				'The whole metropolitan area, grouped into %d regions: %s. Each has its own page, and so does every suburb we list a practice in.',
				count( $names ),
				oxford( $names )
			),
		);
	}

	$faqs[] = array(
		'q' => 'Is it free for a practice to be listed?',
		'a' => 'Yes. Listing costs nothing and claiming a listing costs nothing — an owner can take over their profile and keep their address, contact details, prices and format current for free. Paid plans add photos, opening hours, offers and visitor stats, and no plan changes where a practice appears or how it is described.',
	);

	$faqs[] = array(
		'q' => 'How are listings verified?',
		'a' => 'Most were built from what a practice already publishes about itself, then checked by hand. A listing stays marked Unclaimed until somebody from the business confirms it is theirs. We correct anything that is wrong whether or not a listing has been claimed, and we take listings down on request.',
	);

	$faqs[] = array(
		'q' => 'Do you charge a booking fee?',
		'a' => 'No. Enquiries go straight to the practice and they reply to you directly. We never take a commission on a booking, and we never charge you for an introduction.',
	);

	return $faqs;
}

/** The same answer everywhere: how the directory is actually built. */
function editorial_faq(): array {
	return array(
		'q' => 'How does Oria Haven choose which practices to list?',
		'a' => 'We build the directory ourselves rather than taking paid submissions, so a practice appears because it exists and serves Perth — not because it paid. Practices can claim their listing to keep it accurate and add photos, offers and booking links. We never take a cut of bookings, and enquiries go straight to the practice.',
	);
}

/* ------------------------------------------------------------------ maths */

/** @param list<array<string, mixed>> $rows */
function tally( array $rows, string $key ): array {
	$out = array();
	foreach ( $rows as $row ) {
		$v = (string) ( $row[ $key ] ?? '' );
		if ( '' !== $v ) {
			$out[ $v ] = ( $out[ $v ] ?? 0 ) + 1;
		}
	}
	arsort( $out );
	return $out;
}

/** @param list<array<string, mixed>> $rows */
function free_count( array $rows ): int {
	return count(
		array_filter(
			$rows,
			static fn( $r ) => 0 === strcasecmp( trim( (string) ( $r['priceBand'] ?? '' ) ), 'free' )
		)
	);
}

/** @param list<array<string, mixed>> $rows @return list<float> */
function prices( array $rows ): array {
	$out = array();
	foreach ( $rows as $row ) {
		$p = (float) ( $row['priceFrom'] ?? 0 );
		if ( $p > 0 ) {
			$out[] = $p;
		}
	}
	return $out;
}

/** @param list<float> $values */
function median( array $values ): float {
	sort( $values );
	$n = count( $values );
	if ( 0 === $n ) {
		return 0.0;
	}
	$mid = intdiv( $n, 2 );
	return 0 === $n % 2 ? ( $values[ $mid - 1 ] + $values[ $mid ] ) / 2 : $values[ $mid ];
}

function money( float $v ): string {
	return '$' . number_format_i18n( round( $v ) );
}

function plural( int $n, string $one, string $many ): string {
	return number_format_i18n( $n ) . ' ' . ( 1 === $n ? $one : $many );
}

/** @param list<string> $items */
function oxford( array $items ): string {
	$n = count( $items );
	if ( $n < 2 ) {
		return (string) ( $items[0] ?? '' );
	}
	$last = array_pop( $items );
	return implode( ', ', $items ) . ' and ' . $last;
}

function decoded( string $s ): string {
	return wp_specialchars_decode( $s, ENT_QUOTES );
}

/* ------------------------------------------------------------- admin field */

/**
 * Editor override. One question per line as "Question | Answer" — a
 * textarea rather than a repeater so it needs no ACF and no new tables,
 * and so leaving it empty falls back to the generated set.
 *
 * @param \WP_Term $term
 */
function field( $term ): void {
	if ( ! $term instanceof \WP_Term ) {
		return;
	}
	$value = (string) get_term_meta( $term->term_id, META_OVERRIDE, true );
	$auto  = $value ? array() : for_term( $term );
	?>
	<tr class="form-field">
		<th scope="row"><label for="oria-faq"><?php esc_html_e( 'FAQ', 'oria' ); ?></label></th>
		<td>
			<?php wp_nonce_field( 'oria_faq', 'oria_faq_nonce' ); ?>
			<textarea name="oria_faq" id="oria-faq" rows="6" class="large-text" placeholder="Question | Answer"><?php echo esc_textarea( $value ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'One per line, as "Question | Answer". Leave empty to use the questions generated from live listing data.', 'oria' ); ?>
				<?php if ( $auto ) : ?>
					<br><strong><?php esc_html_e( 'Currently generating:', 'oria' ); ?></strong>
					<?php echo esc_html( implode( ' · ', wp_list_pluck( $auto, 'q' ) ) ); ?>
				<?php endif; ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Never write an answer that says a practice treats, cures or relieves a condition.', 'oria' ); ?>
			</p>
		</td>
	</tr>
	<?php
}

function save( int $term_id ): void {
	if ( ! isset( $_POST['oria_faq_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['oria_faq_nonce'] ), 'oria_faq' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$raw = isset( $_POST['oria_faq'] ) ? sanitize_textarea_field( wp_unslash( $_POST['oria_faq'] ) ) : '';
	if ( '' === trim( $raw ) ) {
		delete_term_meta( $term_id, META_OVERRIDE );
		return;
	}
	update_term_meta( $term_id, META_OVERRIDE, $raw );
}

/** @return list<array{q: string, a: string}> */
function parse_override( string $raw ): array {
	$out = array();
	foreach ( preg_split( '/\R/', $raw ) ?: array() as $line ) {
		$parts = explode( '|', $line, 2 );
		if ( count( $parts ) < 2 ) {
			continue;
		}
		$q = trim( $parts[0] );
		$a = trim( $parts[1] );
		if ( '' !== $q && '' !== $a ) {
			$out[] = array( 'q' => $q, 'a' => $a );
		}
	}
	return $out;
}
