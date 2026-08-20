<?php
/**
 * The answer block — the first thing a person or a language model reads on
 * a landing page.
 *
 * Why this exists. Category, specialty and area pages are the directory
 * engine with a facet locked, and until now the first two hundred words of
 * extractable text on every one of them ran:
 *
 *     Sound healing in Perth
 *     Sound baths and gong sessions around Perth.
 *     Filters  Categories  Mind & Mental Wellbeing 51  Massage & Bodywork 46
 *     ... [sixteen categories, eight regions, four price bands, two formats]
 *
 * Nine words of answer, then a wall of filter furniture. Answer engines
 * retrieve a page and read it from the top; what they were finding at the
 * top of ours was a menu. This puts the answer there instead.
 *
 * Everything below is computed from the listings actually in the term —
 * counts, suburbs, starting prices, formats. Nothing is written by hand and
 * nothing is fixed, so the block says something different the day a batch
 * lands, and it can never claim a practice the directory does not hold.
 *
 * Same hard rule as the listings, the FAQs and the articles: availability,
 * geography, price and format. Never what a modality does to a body.
 *
 * The maths lives in Faq and is reused rather than reimplemented, so the
 * block and the questions beneath it can never quote different numbers for
 * the same page.
 */

declare(strict_types=1);

namespace Oria\Core\Answer;

use Oria\Core\Faq;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Below this, a page has nothing specific enough to be worth saying, and
 * padding it would be exactly the thin-page behaviour the area-depth rules
 * already noindex it for. Matches Faq\MIN_SAMPLE and AreaDepth\MIN_LISTINGS.
 */
const MIN_SAMPLE = 3;

const UPDATED_CACHE = 'oria_answer_updated_v1';

function bootstrap(): void {
	foreach ( array( 'save_post_listing', 'deleted_post' ) as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\flush' );
	}
}

function flush(): void {
	delete_transient( UPDATED_CACHE );
}

/* ------------------------------------------------------------------- rows */

/**
 * The listings behind this page.
 *
 * Faq\matching() handles a single term. A combo page (/practice/yoga/freo/)
 * locks two, so the area is applied as a second pass here rather than being
 * pushed down into Faq, which has no notion of combos.
 *
 * @return list<array<string, mixed>>
 */
function rows_for( \WP_Term $term, ?\WP_Term $area = null ): array {
	$rows = Faq\matching( $term );

	if ( ! $area instanceof \WP_Term ) {
		return $rows;
	}

	return array_values(
		array_filter(
			$rows,
			static fn( array $r ): bool => Taxonomies\is_suburb( $area )
				// The client index stores the display name, not the slug.
				? sanitize_title( (string) ( $r['suburb'] ?? '' ) ) === $area->slug
				: ( $r['region'] ?? '' ) === $area->slug
		)
	);
}

/* --------------------------------------------------------------- sentences */

/**
 * The answer block for a page, as a list of finished sentences.
 *
 * Sentences rather than one paragraph so the caller decides how to set
 * them, and so a sentence with nothing behind it simply never appears —
 * there is no "0 practices offer online sessions" anywhere in here.
 *
 * @return array{sentences: list<string>, updated: string, count: int, has_prices: bool}
 */
function for_term( \WP_Term $term, ?\WP_Term $area = null ): array {
	$empty = array(
		'sentences'  => array(),
		'updated'    => '',
		'count'      => 0,
		'has_prices' => false,
	);

	$rows  = rows_for( $term, $area );
	$total = count( $rows );

	if ( $total < MIN_SAMPLE ) {
		return $empty;
	}

	$sentences = Taxonomies\AREA === $term->taxonomy && ! $area instanceof \WP_Term
		? area_sentences( $term, $rows )
		: subject_sentences( $term, $area, $rows );

	$shared = shared_sentences( $rows );

	return array(
		'sentences' => array_merge( $sentences, $shared ),
		'updated'   => updated(),
		'count'     => $total,
		// Lets the caller drop the "prices change without notice" caveat on
		// a page that quoted no prices, where it reads as boilerplate
		// attached to nothing.
		'has_prices' => (bool) preg_grep( '/\$/', $shared ),
	);
}

/**
 * The opening for a category, a specialty, or either of those locked to an
 * area.
 *
 * Note the two prepositions. A specialty is a thing a practice *offers* —
 * acupuncture, remedial massage — so "practices offering acupuncture" is
 * both true and the phrasing somebody searching would use. A category is a
 * bucket the directory sorts into, and "practices offering Mind & Mental
 * Wellbeing" is not English. Categories get "under" for that reason; it is
 * the honest description of what the relationship actually is.
 *
 * Term names keep the case they are stored in, which puts a capital in the
 * middle of a sentence — "practices offering Sound healing in Perth". That
 * is deliberate, and the alternatives are worse. Faq lowercases the whole
 * name, which demotes "Yoga & Pilates" to a surname-free "yoga & pilates".
 * Lowercasing only the first word looks right until you run it over the
 * actual vocabulary: all 87 specialty names are stored sentence-case with
 * no internal capitals, and among them sit "Bowen therapy", "Chinese
 * medicine" and "Ayurveda". A rule that only wanted to fix "Sound healing"
 * would quietly rewrite a surname, a nationality and a proper noun.
 *
 * So the names are set as labels, because that is what they are. A capital
 * mid-sentence reads slightly formal. The alternative is wrong.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<string>
 */
function subject_sentences( \WP_Term $term, ?\WP_Term $area, array $rows ): array {
	$name       = Faq\decoded( $term->name );
	$total      = count( $rows );
	$out        = array();
	$is_spec    = Taxonomies\SPECIALTY === $term->taxonomy;
	$noun       = $is_spec ? 'practice' : 'wellness practice';
	$qualifier  = $is_spec ? sprintf( 'offering %s', $name ) : sprintf( 'under %s', $name );

	$where = $area instanceof \WP_Term
		? Faq\decoded( $area->name )
		: 'Perth';

	$suburbs = Faq\tally( $rows, 'suburb' );

	// On a combo page the area is already the answer to "where", so counting
	// its suburbs back at the reader is noise.
	$out[] = ( $area instanceof \WP_Term || count( $suburbs ) < 2 )
		? sprintf( 'Oria Haven lists %1$s %2$s in %3$s.', Faq\plural( $total, $noun, $noun . 's' ), $qualifier, $where )
		: sprintf(
			'Oria Haven lists %1$s %2$s in %3$s, across %4$s.',
			Faq\plural( $total, $noun, $noun . 's' ),
			$qualifier,
			$where,
			Faq\plural( count( $suburbs ), 'suburb', 'suburbs' )
		);

	/*
	 * "The largest clusters are", not "most are". Three suburbs holding
	 * 5, 3 and 2 of twenty-four practices are the biggest groups on the
	 * page and they are nowhere near most of it — and "most" is exactly
	 * the kind of word something quoting this page would repeat.
	 *
	 * A suburb holding one practice is not a cluster either, or the page
	 * ends up claiming a concentration in Bayswater (1).
	 */
	if ( ! $area instanceof \WP_Term ) {
		$clusters = array_slice( array_filter( $suburbs, static fn( int $n ): bool => $n >= 2 ), 0, 3, true );
		if ( count( $clusters ) >= 2 ) {
			$out[] = sprintf(
				'The largest clusters are in %s.',
				Faq\oxford( array_map( static fn( string $s ): string => sprintf( '%s (%d)', $s, $clusters[ $s ] ), array_keys( $clusters ) ) )
			);
		}
	}

	return $out;
}

/**
 * The opening for an area page. The interesting axis here is what kind of
 * wellness the suburb has, not where it is — the reader already knows where
 * it is, they typed it.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<string>
 */
function area_sentences( \WP_Term $term, array $rows ): array {
	$place = Faq\decoded( $term->name );
	$total = count( $rows );
	$out   = array();

	$cats   = Faq\tally( $rows, 'cat' );
	$labels = array();
	foreach ( array_slice( array_keys( $cats ), 0, 3 ) as $slug ) {
		$t = get_term_by( 'slug', $slug, Taxonomies\PRACTICE );
		if ( $t instanceof \WP_Term ) {
			// Case as stored, for the same reason as above — "yoga & pilates"
			// demotes a surname.
			$labels[] = sprintf( '%s (%d)', Faq\decoded( $t->name ), $cats[ $slug ] );
		}
	}

	$out[] = sprintf(
		'Oria Haven lists %1$s in %2$s, across %3$s.',
		Faq\plural( $total, 'wellness practice', 'wellness practices' ),
		$place,
		Faq\plural( count( $cats ), 'category', 'categories' )
	);

	if ( count( $labels ) >= 2 ) {
		$out[] = sprintf( 'The most common are %s.', Faq\oxford( $labels ) );
	}

	// Regions carry suburbs; a suburb page has none to report.
	if ( ! Taxonomies\is_suburb( $term ) ) {
		$suburbs = Faq\tally( $rows, 'suburb' );
		if ( count( $suburbs ) >= 2 ) {
			$out[] = sprintf( 'They are spread across %s.', Faq\plural( count( $suburbs ), 'suburb', 'suburbs' ) );
		}
	}

	return $out;
}

/**
 * Price and format, which read the same whatever the page is about.
 *
 * The price sentence says "starting prices" rather than "sessions cost",
 * because price_from is a floor and nothing in the directory knows what an
 * hour actually comes to. A range plus a median is more use to somebody
 * deciding than a single "from" figure, and more use to anything quoting
 * the page.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<string>
 */
function shared_sentences( array $rows ): array {
	$out    = array();
	$total  = count( $rows );
	$prices = Faq\prices( $rows );

	if ( count( $prices ) >= MIN_SAMPLE ) {
		$min = min( $prices );
		$max = max( $prices );

		$out[] = $min === $max
			? sprintf(
				'%1$s publish a starting price, and all of them start at %2$s.',
				spell( count( $prices ), true ),
				Faq\money( $min )
			)
			: sprintf(
				'%1$s publish a starting price, running from %2$s to %3$s with a median of %4$s.',
				spell( count( $prices ), true ),
				Faq\money( $min ),
				Faq\money( $max ),
				Faq\money( Faq\median( $prices ) )
			);
	}

	/*
	 * The band, not just the floor.
	 *
	 * price_from is filled on 16% of listings and price_band on 68% — four
	 * times the evidence for the same question. The floor is the more
	 * precise figure where it exists, so it keeps its sentence; this adds
	 * the one that can actually speak for most of the page.
	 *
	 * Free is excluded because it has its own sentence below, and a modal
	 * band of "free or by donation" would say it twice.
	 */
	$bands = Faq\tally( $rows, 'priceBand' );
	unset( $bands['Free'] );
	$banded = array_sum( $bands );

	if ( $banded >= MIN_SAMPLE ) {
		$top   = (string) array_key_first( $bands );
		$label = band_label( $top );

		if ( '' !== $label ) {
			// Where every banded listing sits in the same band there is no
			// "most common" to report — "the most common is $60–200 (20 of
			// them)" out of twenty is a comparison against nothing.
			$out[] = $bands[ $top ] === $banded
				? sprintf( '%1$s publish a price band, and all of them are %2$s.', spell( $banded, true ), $label )
				: sprintf(
					'%1$s publish a price band, and the most common is %2$s (%3$d of them).',
					spell( $banded, true ),
					$label,
					$bands[ $top ]
				);
		}
	}

	$free = Faq\free_count( $rows );
	if ( $free >= 2 ) {
		$out[] = sprintf( '%1$s of the %2$d run sessions that are free or by donation.', spell( $free, true ), $total );
	}

	$online = count(
		array_filter(
			$rows,
			static fn( array $r ): bool => in_array( (string) ( $r['format'] ?? '' ), array( 'online', 'both' ), true )
		)
	);
	if ( $online > 0 ) {
		$out[] = sprintf(
			'%1$s %2$s online or hybrid sessions.',
			spell( $online, true ),
			1 === $online ? 'offers' : 'offer'
		);
	}

	return $out;
}

/**
 * A count, spelled out where it opens a sentence.
 *
 * Every sentence in shared_sentences() begins with its number, and "1
 * offers online or hybrid sessions." is the sort of line that reads as
 * machine output and gets treated as machine output. Ten and above stay in
 * figures, which is both the usual convention and the point at which a
 * spelled number is harder to scan than the digits.
 */
/**
 * A price band as a range somebody can read.
 *
 * The stored values are the dollar signs the admin select uses. "$$$" is
 * not a price, and it is certainly not a price to anything reading the page
 * rather than looking at it, so the block spells the range out. Unknown
 * values return empty and the sentence is dropped rather than guessed at.
 */
function band_label( string $band ): string {
	return array(
		'$'    => 'under $25',
		'$$'   => '$25–60',
		'$$$'  => '$60–200',
		'$$$$' => '$200 and above',
	)[ $band ] ?? '';
}

function spell( int $n, bool $sentence_start = false ): string {
	$words = array( 1 => 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine' );

	if ( ! isset( $words[ $n ] ) ) {
		return number_format_i18n( $n );
	}

	return $sentence_start ? ucfirst( $words[ $n ] ) : $words[ $n ];
}

/* ---------------------------------------------------------------- currency */

/**
 * When the directory last changed, as a date somebody can check.
 *
 * A generated block that says "updated today" on every page is worth
 * nothing — it is true of any page generated at request time, including the
 * ones nobody has touched since March. This is the newest modification date
 * on any published listing, which is a fact about the data rather than a
 * fact about when the page was rendered.
 */
function updated(): string {
	$cached = get_transient( UPDATED_CACHE );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	global $wpdb;
	$stamp = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT MAX(post_modified) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
			'listing',
			'publish'
		)
	);

	$out = is_string( $stamp ) && '' !== $stamp
		? (string) wp_date( 'j F Y', (int) strtotime( $stamp ) )
		: (string) wp_date( 'j F Y' );

	set_transient( UPDATED_CACHE, $out, 6 * HOUR_IN_SECONDS );

	return $out;
}

/** The block as one paragraph — for meta descriptions and schema. */
function as_text( \WP_Term $term, ?\WP_Term $area = null ): string {
	return implode( ' ', for_term( $term, $area )['sentences'] );
}
