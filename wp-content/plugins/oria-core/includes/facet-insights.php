<?php
/**
 * Facet insights: the "Oria insights" panel -- figures we worked out
 * ourselves from prices and details checked on each venue's own website.
 *
 * Why it exists: a facet page that only lists places says nothing a search
 * engine can't find elsewhere. A typical price, the cheapest option north
 * and south of the river, how many places say their cabins are private --
 * computed from checked sources and dated -- is original information, and
 * it is the kind of short, factual line an answer engine can lift and cite.
 *
 * Rules the data file must keep (data/facet-insights.json):
 *   - every figure comes from a dated check of venues' own sites, never from
 *     a listing's generic price_from (that is what made the hero say a
 *     traditional sauna "typically" costs $20);
 *   - each panel states its basis -- how many venues, checked when;
 *   - nothing about what a session does to a body.
 *
 * A panel older than MAX_AGE_DAYS is not shown: an out-of-date price
 * presented as an insight is worse than no panel.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\FacetInsights;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAX_AGE_DAYS = 183;

/** The data file's facet entries. */
function all(): array {
	static $data = null;
	if ( null === $data ) {
		$data = array();
		$path = ORIA_CORE_DIR . 'data/facet-insights.json';
		if ( is_readable( $path ) ) {
			$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$data = is_array( $json['facets'] ?? null ) ? $json['facets'] : array();
		}
	}
	return $data;
}

/**
 * The entry for a facet in a city, or an empty array when there is none,
 * it belongs to another city, or it is too old to show.
 *
 * @param array<string, mixed>|null $facet PracticesIndex\facet()
 * @param array<string, mixed>|null $city  Cities\get()
 */
function entry( ?array $facet, ?array $city = null, ?\WP_Term $category = null ): array {
	/*
	 * A whole category page (/explore/perth/yoga/) has no facet; its panel
	 * is keyed "cat:{slug}" so a category and a facet of the same name
	 * can never collide.
	 */
	$keys = $facet
		? array( (string) ( $facet['slug'] ?? '' ), (string) ( $facet['value'] ?? '' ) )
		: ( $category ? array( 'cat:' . $category->slug ) : array() );
	if ( ! $keys ) {
		return array();
	}
	$all = all();
	$e   = array();
	foreach ( $keys as $k ) {
		if ( '' !== $k && isset( $all[ $k ] ) && is_array( $all[ $k ] ) ) {
			$e = $all[ $k ];
			break;
		}
	}
	if ( ! $e || empty( $e['items'] ) ) {
		return array();
	}
	$want = (string) ( $e['city'] ?? 'perth' );
	$here = (string) ( $city['slug'] ?? 'perth' );
	if ( $want !== $here ) {
		return array();
	}
	$checked = strtotime( (string) ( $e['checked'] ?? '' ) );
	if ( ! $checked || ( time() - $checked ) > MAX_AGE_DAYS * DAY_IN_SECONDS ) {
		return array();
	}
	return $e;
}

/**
 * The checked typical price for the hero's snapshot line, replacing the
 * median of listings' generic starting prices on pages that have one.
 */
function snapshot_price( ?array $facet, ?array $city = null, ?\WP_Term $category = null ): string {
	$e = entry( $facet, $city, $category );
	return (string) ( $e['snapshot'] ?? '' );
}

/**
 * A note with listing names turned into links. The note is escaped first;
 * each name in `links` is linked at its first mention, and only when the
 * listing is published.
 *
 * @param array<string, string> $links name => listing slug
 */
function linked_note( string $note, array $links ): string {
	$html = esc_html( $note );
	foreach ( $links as $name => $slug ) {
		$post = get_page_by_path( (string) $slug, OBJECT, 'listing' );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			continue;
		}
		$needle = esc_html( (string) $name );
		$pos    = strpos( $html, $needle );
		if ( false === $pos ) {
			continue;
		}
		$a    = '<a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . $needle . '</a>';
		$html = substr_replace( $html, $a, $pos, strlen( $needle ) );
	}
	return $html;
}


/** A small decorative line icon for a checklist row. */
function icon( string $name ): string {
	$paths = array(
		'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/>',
		'shower'   => '<path d="M5 20V8a4 4 0 0 1 8 0"/><path d="M10 8h6"/><path d="M12 12v1M15 12v1M18 12v1M12 16v1M15 16v1M18 16v1"/>',
		'towel'    => '<rect x="5" y="4" width="14" height="16" rx="2"/><path d="M5 9h14M9 4v16"/>',
		'dot'      => '<circle cx="12" cy="12" r="3"/>',
	);
	$d = $paths[ $name ] ?? $paths['dot'];
	return '<svg class="xc-ins__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

/** The first published listing an item links to, for a row's "View venue". */
function first_venue( array $it ): string {
	foreach ( (array) ( $it['links'] ?? array() ) as $slug ) {
		$post = get_page_by_path( (string) $slug, OBJECT, 'listing' );
		if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
			return (string) get_permalink( $post );
		}
	}
	return '';
}

/**
 * The insights section (October 2026 redesign,
 * DESIGN/October 2026/oria-haven-insights-section-redesign-claude.md):
 * an editorial heading, up to three price cards, "find a session that
 * fits" rows, "a few details worth knowing", the method and sources, and a
 * way back to the places. Every group is optional -- a page with fewer
 * facts simply has fewer groups. All server-rendered; the disclosures are
 * native <details>.
 *
 * @param string $name e.g. "Infrared Saunas in Perth"
 */
function render( ?array $facet, ?array $city, string $name, ?\WP_Term $category = null ): string {
	$e = entry( $facet, $city, $category );
	if ( ! $e ) {
		return '';
	}
	$checked = (int) strtotime( (string) $e['checked'] );
	$cname   = (string) ( $city['name'] ?? 'Perth' );
	$topic   = trim( (string) ( $e['topic'] ?? '' ) );
	if ( '' === $topic ) {
		$topic = strtolower( (string) preg_replace( '/\s+in\s+.+$/i', '', $name ) );
	}
	$by = array(
		'lead'   => array(),
		'fit'    => array(),
		'detail' => array(),
	);
	foreach ( (array) $e['items'] as $it ) {
		if ( empty( $it['label'] ) || empty( $it['value'] ) ) {
			continue;
		}
		$g = (string) ( $it['group'] ?? 'detail' );
		$g = isset( $by[ $g ] ) ? $g : 'detail';
		$by[ $g ][] = $it;
	}
	$note = static fn( array $it, string $key ): string => linked_note( (string) ( $it[ $key ] ?? '' ), (array) ( $it['links'] ?? array() ) );

	$o = '<section class="xc-ins" id="xcInsights" aria-labelledby="xcInsightsTitle">';

	// 1. Heading.
	$o .= '<header class="xc-ins__head">';
	$o .= '<p class="xc-ins__eyebrow"><svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="7.5" fill="none" stroke="currentColor" stroke-width="1.4"/><circle cx="10" cy="10" r="2.5" fill="currentColor"/></svg>' . esc_html__( 'The Oria insight', 'oria' ) . '</p>';
	/* translators: 1: city, 2: topic, e.g. infrared saunas */
	$o .= '<h2 class="xc-ins__title" id="xcInsightsTitle">' . esc_html( sprintf( __( 'A clearer picture of %1$s’s %2$s', 'oria' ), $cname, $topic ) ) . '</h2>';
	$o .= '<p class="xc-ins__lede">' . esc_html__( 'Compare published prices and the details worth checking before you book.', 'oria' ) . '</p>';
	$o .= '<p class="xc-ins__meta">';
	if ( ! empty( $e['venues'] ) ) {
		/* translators: %s: number of venues */
		$o .= '<span>' . esc_html( sprintf( _n( 'Based on %s venue', 'Based on %s venues', (int) $e['venues'], 'oria' ), number_format_i18n( (int) $e['venues'] ) ) ) . '</span>';
	}
	/* translators: %s: date */
	$o .= '<span>' . esc_html( sprintf( __( 'Checked %s', 'oria' ), date_i18n( 'j F Y', $checked ) ) ) . '</span>';
	$o .= '<a class="xc-ins__how" href="#xcInsightsMethod" data-xc-details>' . esc_html__( 'How we worked this out', 'oria' ) . '</a>';
	$o .= '</p></header>';

	// 2. Price cards.
	if ( $by['lead'] ) {
		$o .= '<div class="xc-ins__lead" style="--xc-ins-n:' . count( $by['lead'] ) . '">';
		foreach ( $by['lead'] as $it ) {
			$o .= '<dl class="xc-ins__card' . ( ! empty( $it['tint'] ) ? ' is-tint' : '' ) . '">';
			$o .= '<dt class="xc-ins__label">' . esc_html( (string) $it['label'] ) . '</dt>';
			$o .= '<dd class="xc-ins__price">' . esc_html( (string) $it['value'] ) . '</dd>';
			if ( ! empty( $it['unit'] ) ) {
				$o .= '<dd class="xc-ins__unit">' . esc_html( (string) $it['unit'] ) . '</dd>';
			}
			$o .= '<dd class="xc-ins__foot">';
			if ( ! empty( $it['note'] ) ) {
				$o .= '<span class="xc-ins__evidence">' . $note( $it, 'note' ) . '</span>';
			}
			if ( ! empty( $it['detail'] ) ) {
				$o .= '<span class="xc-ins__detail">' . $note( $it, 'detail' ) . '</span>';
			}
			$o .= '</dd></dl>';
		}
		$o .= '</div>';
	}

	// 3. Find a session that fits.
	if ( $by['fit'] ) {
		$o .= '<div class="xc-ins__fit"><h3 class="xc-ins__h3">' . esc_html__( 'Find a session that fits', 'oria' ) . '</h3><ul class="xc-ins__rows">';
		foreach ( $by['fit'] as $it ) {
			$url = first_venue( $it );
			$o  .= '<li class="xc-ins__row">';
			$o  .= '<span class="xc-ins__need">' . esc_html( (string) $it['label'] ) . '</span>';
			$o  .= '<span class="xc-ins__rowprice"><b>' . esc_html( (string) $it['value'] ) . '</b>' . ( ! empty( $it['unit'] ) ? ' <span>' . esc_html( (string) $it['unit'] ) . '</span>' : '' ) . '</span>';
			$o  .= '<span class="xc-ins__venue">' . $note( $it, 'note' ) . '</span>';
			$o  .= '<span class="xc-ins__act">' . ( '' !== $url ? '<a href="' . esc_url( $url ) . '">' . esc_html__( 'View venue', 'oria' ) . ' <span aria-hidden="true">&rarr;</span></a>' : '' ) . '</span>';
			if ( ! empty( $it['other'] ) ) {
				$o .= '<details class="xc-ins__other"><summary>' . esc_html__( 'Other checked options', 'oria' ) . '</summary><p>' . $note( $it, 'other' ) . '</p></details>';
			}
			$o .= '</li>';
		}
		$o .= '</ul></div>';
	}

	// 4. A few details worth knowing.
	if ( $by['detail'] ) {
		$left  = array_values( array_filter( $by['detail'], static fn( array $it ): bool => 'left' === ( $it['side'] ?? '' ) ) );
		$right = array_values( array_filter( $by['detail'], static fn( array $it ): bool => 'left' !== ( $it['side'] ?? '' ) ) );
		$o    .= '<div class="xc-ins__know"><h3 class="xc-ins__h3">' . esc_html__( 'A few details worth knowing', 'oria' ) . '</h3>';
		$o    .= '<div class="xc-ins__knowgrid' . ( $left ? '' : ' is-single' ) . '">';
		if ( $left ) {
			$o .= '<dl class="xc-ins__keyfacts">';
			foreach ( $left as $it ) {
				$o .= '<div class="xc-ins__keyfact"><dt>' . esc_html( (string) $it['label'] ) . '</dt><dd class="xc-ins__keyval">' . esc_html( (string) $it['value'] ) . '</dd>';
				if ( ! empty( $it['strip'] ) && is_array( $it['strip'] ) && (int) $it['strip'][1] > 0 ) {
					$pct = max( 0, min( 100, round( 100 * (int) $it['strip'][0] / (int) $it['strip'][1] ) ) );
					$o  .= '<dd class="xc-ins__strip" aria-hidden="true"><span style="width:' . (int) $pct . '%"></span></dd>';
					$o  .= '<dd class="xc-ins__striplab" aria-hidden="true"><span>' . esc_html( (string) $it['strip'][2] ) . ' · ' . (int) $it['strip'][0] . '</span><span>' . esc_html( (string) $it['strip'][3] ) . ' · ' . ( (int) $it['strip'][1] - (int) $it['strip'][0] ) . '</span></dd>';
				}
				if ( ! empty( $it['note'] ) ) {
					$o .= '<dd class="xc-ins__keynote">' . $note( $it, 'note' ) . '</dd>';
				}
				$o .= '</div>';
			}
			$o .= '</dl>';
		}
		$o .= '<ul class="xc-ins__check">';
		foreach ( $right as $it ) {
			$o .= '<li>' . icon( (string) ( $it['icon'] ?? 'dot' ) ) . '<div><p class="xc-ins__checkhead"><span class="xc-ins__checklabel">' . esc_html( (string) $it['label'] ) . '</span> <b>' . esc_html( (string) $it['value'] ) . '</b></p>';
			if ( ! empty( $it['note'] ) ) {
				$o .= '<p class="xc-ins__checknote">' . $note( $it, 'note' ) . '</p>';
			}
			if ( ! empty( $it['more'] ) ) {
				$o .= '<details class="xc-ins__other"><summary>' . esc_html__( 'See venues and details', 'oria' ) . '</summary><p>' . $note( $it, 'more' ) . '</p></details>';
			}
			$o .= '</div></li>';
		}
		$o .= '</ul></div></div>';
	}

	// 5. Sources and method.
	$o .= '<details class="xc-ins__method" id="xcInsightsMethod"><summary>' . esc_html__( 'Sources & how we calculated these figures', 'oria' ) . '</summary><div class="xc-ins__methodbody"><ul>';
	/* translators: 1: date, 2: what was checked */
	$o .= '<li>' . esc_html( sprintf( __( 'Checked %1$s, from %2$s, or the booking pages they link to.', 'oria' ), date_i18n( 'j F Y', $checked ), (string) ( $e['basis'] ?? '' ) ) ) . '</li>';
	foreach ( (array) ( $e['method'] ?? array() ) as $m ) {
		$o .= '<li>' . esc_html( (string) $m ) . '</li>';
	}
	$o .= '<li>' . esc_html__( 'Each figure says how many venues it comes from; different facts can rest on different numbers of venues. Venue names link to their Oria Haven listings, which link to the venue’s own site.', 'oria' ) . '</li>';
	$o .= '</ul></div></details>';

	// 6. The way back to the places.
	$o .= '<div class="xc-ins__cta"><p>' . esc_html__( 'Ready to find your space?', 'oria' ) . '</p>';
	/* translators: %s: topic, e.g. infrared saunas */
	$o .= '<a class="btn xc-ins__go" href="#results" data-oria-event="insights_explore">' . esc_html( sprintf( __( 'Explore %s', 'oria' ), $topic ) ) . ' <span aria-hidden="true">&rarr;</span></a></div>';

	$o .= '</section>';
	return $o;
}
