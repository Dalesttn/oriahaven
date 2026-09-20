<?php
/**
 * The redesigned category directory — /practices/{practice}/ and, with one
 * facet locked, /practices/{practice}/{facet}/ (vinyasa, yin, reformer,
 * beginners, online, free …). See Oria\Core\PracticesIndex.
 *
 * Concepts 1b and 1a together: three named floors with a sticky spine
 * (Decide → Browse → Read) and, inside the first two, the answer-first
 * top, the intent grid as the primary move, and a toolbar with a specialty
 * typeahead instead of the rail. Same engine, same data, same invariants —
 * one H1, breadcrumbs, everything in the HTML, filters untouched.
 *
 * A facet page is the same page with the facet locked: its own H1 and
 * title, the matching set rendered server-side, the grid marking where you
 * are, and — where the intent registry has a frame for it — the frame's
 * copy and questions. Every style the grid offers is a clean address.
 *
 * v4 (design test): the parent's template with the design canvas's
 * category layout laid over it. Same data, same engine, same invariants;
 * what changes is the order and the dress. The page now reads as a short
 * guide before it reads as a list -- hero with an "Is this for me?" answer,
 * ways to begin, a first-timer note, the editors' shortlist and what's on,
 * and only then every listing, as editorial rows. Everything shown is
 * computed from the directory; nothing is written for the page.
 *
 * v4 "Wellness Horizon" (DESIGN/New Designs/oria-haven-award-winning-
 * category-page-concept.md): one full-bleed photograph, one promise, one
 * Discovery Dock, then the places. Order: hero (#decide) -> Discovery Dock
 * (feeling / experience / location / show N, popular shortcuts, snapshot)
 * -> results heading, count, filters -> one Featured card -> six organic ->
 * Oria Note -> more organic -> Local intelligence -> pager -> "About ..."
 * -> Best Of shelf -> one event -> guide -> FAQs -> by area / experience
 * -> products and apps. A sticky Discovery Ribbon (v4-category.js) takes
 * over from the dock once it scrolls away; the old .spine menu is gone.
 *
 * Everything filters through the parent's engine (app.js): the dock's
 * choices are real [data-filter] inputs, so counts, chips, "Clear all" and
 * the URL (svc=, region=, suburb=) all come from app.js as before. Hooks
 * app.js reads (#dirResults and its data-*, #dirFilters, #dirCount,
 * #featBand, [data-view], [data-open-map], [data-best-toggle], #results)
 * are unchanged. Configuration (focal points, moods, the Oria Note) lives
 * in assets/data/category-horizon.json until category fields exist.
 */

declare(strict_types=1);

/*
 * The hero photograph, chosen before get_header() so its one source can be
 * preloaded from <head>. Order: a single art-directed photograph in this
 * theme (assets/img/cat/{slug}-1600.webp, with {slug}-900.webp for small
 * screens), the parent category's when a sub-category has none, then the
 * parent theme's three-photo header (Theme\category_hero_url). Decorative:
 * the heading beside it says what the page is.
 */
$oria_cfg      = array();
$oria_cfg_file = get_stylesheet_directory() . '/assets/data/category-horizon.json';
if ( is_readable( $oria_cfg_file ) ) {
	$oria_cfg = json_decode( (string) file_get_contents( $oria_cfg_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file
	$oria_cfg = is_array( $oria_cfg ) ? $oria_cfg : array();
}
$oria_hz      = array( 'src' => '', 'srcset' => '', 'w' => 1600, 'h' => 900, 'pos' => '' );
$oria_hz_term = get_queried_object();
if ( $oria_hz_term instanceof WP_Term ) {
	$oria_hz_slugs = array( $oria_hz_term->slug );
	if ( $oria_hz_term->parent ) {
		$oria_hz_parent = get_term( (int) $oria_hz_term->parent, $oria_hz_term->taxonomy );
		if ( $oria_hz_parent instanceof WP_Term ) {
			$oria_hz_slugs[] = $oria_hz_parent->slug;
		}
	}
	$oria_hz_dir = get_stylesheet_directory() . '/assets/img/cat/';
	$oria_hz_uri = get_stylesheet_directory_uri() . '/assets/img/cat/';
	foreach ( $oria_hz_slugs as $oria_hz_s ) {
		if ( ! is_readable( $oria_hz_dir . $oria_hz_s . '-1600.webp' ) ) {
			continue;
		}
		$oria_hz['src'] = $oria_hz_uri . $oria_hz_s . '-1600.webp';
		$oria_hz_size   = @getimagesize( $oria_hz_dir . $oria_hz_s . '-1600.webp' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a size we cannot read keeps the 16:9 default
		if ( is_array( $oria_hz_size ) && $oria_hz_size[0] > 0 ) {
			$oria_hz['w'] = (int) $oria_hz_size[0];
			$oria_hz['h'] = (int) $oria_hz_size[1];
		}
		if ( is_readable( $oria_hz_dir . $oria_hz_s . '-900.webp' ) ) {
			$oria_hz['srcset'] = $oria_hz_uri . $oria_hz_s . '-900.webp 900w, ' . $oria_hz['src'] . ' ' . $oria_hz['w'] . 'w';
		}
		$oria_hz['pos'] = (string) ( $oria_cfg['focal'][ $oria_hz_s ] ?? '' );
		break;
	}
	if ( '' === $oria_hz['src'] && function_exists( '\Oria\Theme\category_hero_url' ) ) {
		$oria_hz['src'] = (string) \Oria\Theme\category_hero_url( $oria_hz_term );
	}
	if ( '' === $oria_hz['pos'] ) {
		foreach ( $oria_hz_slugs as $oria_hz_s ) {
			if ( ! empty( $oria_cfg['focal'][ $oria_hz_s ] ) ) {
				$oria_hz['pos'] = (string) $oria_cfg['focal'][ $oria_hz_s ];
				break;
			}
		}
	}
	if ( '' !== $oria_hz['src'] ) {
		// Preload only the one source the browser will pick.
		add_action(
			'wp_head',
			static function () use ( $oria_hz ): void {
				printf(
					'<link rel="preload" as="image" href="%1$s"%2$s fetchpriority="high">' . "\n",
					esc_url( $oria_hz['src'] ),
					'' !== $oria_hz['srcset'] ? ' imagesrcset="' . esc_attr( $oria_hz['srcset'] ) . '" imagesizes="100vw"' : ''
				);
			},
			2
		);
	}
}

get_header();

$oria_term  = get_queried_object();
$oria_term  = $oria_term instanceof WP_Term ? $oria_term : null;
$oria_pname = $oria_term ? \Oria\Theme\tname( $oria_term ) : '';
$oria_here  = $oria_term ? \Oria\Core\PracticesIndex\category_url( $oria_term ) : \Oria\Core\PracticesIndex\url();
$oria_facet = \Oria\Core\PracticesIndex\facet();
$oria_frame = (array) ( $oria_facet['page']['frame'] ?? array() );
$oria_intro = $oria_term ? get_field( 'landing_intro', 'practice_' . $oria_term->term_id ) : '';

// The sets: everything in the category, and the locked subset when a facet is on.
$oria_all = $oria_term && function_exists( '\Oria\Core\Intents\listings_in' ) ? \Oria\Core\Intents\listings_in( $oria_term ) : array();
$oria_ids = ( $oria_facet && $oria_term ) ? \Oria\Core\PracticesIndex\facet_ids( $oria_term, $oria_facet ) : $oria_all;

// Every listing's terms in a handful of queries rather than one
// per listing per taxonomy: the loops below walk hundreds of ids.
\Oria\Theme\prime_listing_terms( array_merge( $oria_all, $oria_ids ) );

/*
 * A suburb combo — /practice/recovery/currambine/ — is this same page with
 * the area locked rather than a style. It gets the facet treatment: the
 * subset resolved server-side so the page is whole without scripting, and
 * the toolbar told which suburb is fixed so the client agrees with it.
 */
$oria_area = function_exists( '\Oria\Core\Seo\combo_area' ) ? \Oria\Core\Seo\combo_area() : null;
$oria_area = $oria_area instanceof WP_Term ? $oria_area : null;
if ( ! $oria_area && $oria_facet && 'area' === ( $oria_facet['key'] ?? '' ) && ( $oria_facet['area'] ?? null ) instanceof WP_Term ) {
	/*
	 * The same page at the new address: /practices/{cat}/{suburb}/. The ids
	 * are already the facet subset, so only the label and the toolbar lock
	 * need to know -- no second filter, which for a region page would have
	 * wrongly demanded the parent term on every listing.
	 */
	$oria_area = $oria_facet['area'];
} elseif ( $oria_area ) {
	$oria_ids = array_values(
		array_filter(
			$oria_ids,
			static fn( $oria_lid ): bool => has_term( $oria_area->term_id, 'area', (int) $oria_lid )
		)
	);
}

/*
 * City scope. A locked area is narrower than a city and has already been
 * applied above, so it governs the set on its own -- and it also says which
 * city the page's prose belongs to, which is how the Margaret River facet
 * stopped claiming this category was "taught in Perth".
 *
 * With no area locked the page is one city's view of the category, and the
 * set has to agree with the heading: before this it said Perth and listed
 * Yallingup.
 */
$oria_city  = null;
$oria_cname = __( 'Perth', 'oria' );
if ( function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
	$oria_city = $oria_area ? \Oria\Core\Cities\for_area( $oria_area ) : null;
	$oria_city = $oria_city ?: \Oria\Core\Cities\current();
	if ( ! $oria_area ) {
		$oria_all = \Oria\Core\Cities\filter_ids( $oria_all, $oria_city );
		$oria_ids = \Oria\Core\Cities\filter_ids( $oria_ids, $oria_city );
	}
	$oria_cname = \Oria\Core\Cities\name( $oria_city );
}

/*
 * The Best Of guides behind this page and their picks that are on it:
 * the "Oria's picks" chip, the shelf under the listings and the line in
 * the header all read this one answer (Theme\category_best_of).
 */
$oria_bo = $oria_term && function_exists( '\Oria\Theme\category_best_of' )
	? \Oria\Theme\category_best_of( $oria_term, $oria_ids )
	: array( 'guides' => array(), 'slugs' => array() );
$oria_bo_lead = $oria_bo['guides'][0] ?? null;

// Facts for the strip, over whichever set the page is about.
$oria_suburbs = array();
$oria_claimed = 0;
$oria_bands   = array();
$oria_prices  = array();
$oria_free    = 0;
$oria_online  = 0;
foreach ( $oria_ids as $oria_id ) {
	foreach ( \Oria\Theme\oria_terms_of( (int) $oria_id, 'area' ) as $oria_a ) {
		if ( $oria_a->parent ) { $oria_suburbs[ $oria_a->slug ] = true; }
	}
	if ( 'unclaimed' !== \Oria\Theme\claim_status( (int) $oria_id ) ) { $oria_claimed++; }
	$oria_b = (string) get_field( 'price_band', (int) $oria_id );
	if ( '' !== $oria_b ) { $oria_bands[ $oria_b ] = ( $oria_bands[ $oria_b ] ?? 0 ) + 1; }
	$oria_pf = get_field( 'price_from', (int) $oria_id );
	if ( is_numeric( $oria_pf ) && (float) $oria_pf > 0 ) { $oria_prices[] = (float) $oria_pf; }
	if ( 'Free' === $oria_b ) { ++$oria_free; }
	if ( 'in-person' !== (string) ( get_field( 'format', (int) $oria_id ) ?: 'in-person' ) ) { ++$oria_online; }
}
arsort( $oria_bands );
$oria_typical = $oria_bands ? (string) array_key_first( $oria_bands ) : '';

/*
 * The typical starting price for this set: the median of the listings'
 * "price from" figures, so one $600 retreat cannot drag it about. Three
 * priced listings is the floor for showing a figure; below that the
 * strip falls back to the most common price band.
 */
$oria_price_n = count( $oria_prices );
$oria_price   = 0.0;
if ( $oria_price_n >= 3 ) {
	sort( $oria_prices );
	$oria_mid   = intdiv( $oria_price_n, 2 );
	$oria_price = 0 === $oria_price_n % 2 ? ( $oria_prices[ $oria_mid - 1 ] + $oria_prices[ $oria_mid ] ) / 2 : $oria_prices[ $oria_mid ];
}

$oria_answer = ( $oria_term && ! $oria_facet && function_exists( '\Oria\Core\Answer\for_term' ) ) ? \Oria\Core\Answer\for_term( $oria_term ) : array( 'sentences' => array(), 'updated' => '' );
/*
 * Counted over this page's listings, not the category's. On
 * /explore/margaret-river/spa/ these cards were offering "Infrared sauna 27"
 * above seven listings, and every one of them led to a 404 because the
 * combination holds nothing south.
 */
$oria_rows   = $oria_term && function_exists( '\Oria\Core\Intents\for_practice' )
	/*
	 * The category within this page's geography. With an area locked that
	 * is $oria_ids, which is already category-and-area; otherwise it is
	 * $oria_all, which the city filter has narrowed. Using the facet
	 * subset instead would zero every other row on the grid.
	 */
	? \Oria\Core\Intents\for_practice( $oria_term, $oria_area ? $oria_ids : $oria_all )
	: array();
$oria_guides = $oria_term && function_exists( '\Oria\Core\Guides\for_term' ) ? \Oria\Core\Guides\for_term( $oria_term ) : array();
$oria_latest = $oria_guides ? array() : get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'date', 'order' => 'DESC' ) );

// Events and workshops on now or coming up in this category. Asked for
// here, before the section menu is drawn, so the menu offers "What's on"
// only when something is. The category view only -- on an area page the
// same citywide list would read as though it were local.
$oria_events = ( $oria_term && ! $oria_area && function_exists( '\Oria\Theme\category_events' ) )
	? \Oria\Theme\category_events( $oria_term, $oria_city )
	: array();

// The FAQ for this page — the frame's on a facet page, the category's
// otherwise — worked out here so the spine knows whether to offer a stop.
$oria_faqs = array();
if ( $oria_facet ) {
	foreach ( (array) ( $oria_frame['faq'] ?? array() ) as $oria_qa ) {
		if ( ! empty( $oria_qa['q'] ) && ! empty( $oria_qa['a'] ) ) {
			$oria_faqs[] = array( 'q' => (string) $oria_qa['q'], 'a' => (string) $oria_qa['a'] );
		}
	}
	// No frame questions: the facet's own, from data/facet-guides.json.
	if ( ! $oria_faqs && function_exists( '\Oria\Core\FacetGuides\faqs' ) ) {
		$oria_faqs = \Oria\Core\FacetGuides\faqs( $oria_facet );
	}
} elseif ( $oria_term && function_exists( '\Oria\Core\Faq\for_term' ) ) {
	$oria_faqs = (array) \Oria\Core\Faq\for_term( $oria_term );
}

$oria_h1 = $oria_facet ? (string) $oria_facet['label'] : sprintf( __( '%1$s in %2$s', 'oria' ), $oria_pname, $oria_cname );
if ( $oria_area ) {
	// "Sleep & Recovery in Currambine" — the suburb is the whole point of
	// the page, so it replaces Perth rather than sitting beside it.
	$oria_h1 = sprintf( __( '%1$s in %2$s', 'oria' ), $oria_pname, \Oria\Theme\tname( $oria_area ) );
}
if ( ! $oria_facet && ! $oria_area && $oria_term ) {
	// A filtered view reached by URL names what it shows: "Yoga in Fremantle".
	$oria_qh = \Oria\Core\PracticesIndex\query_heading( $oria_term );
	if ( '' !== $oria_qh ) {
		$oria_h1 = $oria_qh;
	}
}

/* Every intent row gets a clean address under this category — the inverse
   of the facet resolver. Rows with no clean form keep a filtered view of
   this page. */
$oria_row_url = static function ( array $row ) use ( $oria_term, $oria_here ): string {
	$url = (string) $row['url'];
	if ( ! $oria_term ) {
		return $url;
	}
	/*
	 * Every address this category has ever had. A row can carry any of them
	 * depending on which builder wrote it, and matching only one left rows
	 * pointing at /practices/yoga/yin/ after that family was retired --
	 * eleven cards on the yoga page, nine of them a 404.
	 */
	$olds = array( untrailingslashit( \Oria\Core\PracticesIndex\original_url( $oria_term ) ) );
	foreach ( array( 'practice', 'practices' ) as $oria_seg ) {
		$olds[] = untrailingslashit( home_url( '/' . $oria_seg . '/' . $oria_term->slug ) );
	}
	$olds[] = untrailingslashit( $oria_here );
	$olds    = array_values( array_unique( $olds ) );

	if ( false === strpos( $url, '?' ) ) {
		// A row pointing at a canonical intent page under any of those moves
		// to the same slug here — the facet resolver reads the registry, so
		// it renders the same frame.
		foreach ( $olds as $oria_old ) {
			if ( 0 !== strpos( $url, $oria_old . '/' ) ) {
				continue;
			}
			$seg = trim( substr( $url, strlen( $oria_old ) ), '/' );
			if ( '' !== $seg && false === strpos( $seg, '/' ) ) {
				return $oria_here . $seg . '/';
			}
		}

		return $url;
	}
	$clean = \Oria\Core\PracticesIndex\facet_url_for_query( $oria_term, (string) wp_parse_url( $url, PHP_URL_QUERY ) );
	if ( '' !== $clean ) {
		return $clean;
	}
	// No clean form: keep the filtered view, moved under this page's address.
	foreach ( $olds as $oria_old ) {
		if ( 0 === strpos( $url, $oria_old . '/' ) ) {
			return $oria_here . ltrim( substr( $url, strlen( $oria_old ) ), '/' );
		}
	}
	return $url;
};
$oria_facet_href = $oria_facet ? $oria_here . $oria_facet['slug'] . '/' : '';
$oria_fill = static function ( string $s ) use ( $oria_ids, $oria_all, $oria_pname ): string {
	return strtr( $s, array( '{count}' => number_format_i18n( count( $oria_ids ) ), '{total}' => number_format_i18n( count( $oria_all ) ), '{practice}' => strtolower( $oria_pname ) ) );
};

/*
 * Worked out before anything is drawn: the hero, the dock and the results
 * all need to know what this page actually has.
 */
$oria_base = $oria_term && ! $oria_facet && ! $oria_area;
$oria_n    = count( $oria_ids );

// Sentence case for the category's own name ("Spa & recovery"): the names
// are title-cased labels, and none of them holds a proper noun.
$oria_sentence = static function ( string $s ): string {
	return '' === $s ? $s : strtoupper( substr( $s, 0, 1 ) ) . strtolower( substr( $s, 1 ) );
};
$oria_psent = $oria_sentence( $oria_pname );

// The category and its own sub-categories. A listing whose PRIMARY
// category (Primary\of -- never $practices[0]) is in here is a specialist.
$oria_family = array();
if ( $oria_term ) {
	$oria_family[] = $oria_term->slug;
	foreach ( (array) get_term_children( (int) $oria_term->term_id, 'practice' ) as $oria_cid ) {
		$oria_ct = get_term( (int) $oria_cid, 'practice' );
		if ( $oria_ct instanceof WP_Term ) {
			$oria_family[] = $oria_ct->slug;
		}
	}
}

/*
 * One pass over the page's listings for everything below: primary category
 * (specialists vs also offering), services (the dock's experiences, moods,
 * shortcuts), and areas (the dock's location, Local intelligence).
 */
$oria_primary = array();
$oria_spec_n  = 0;
$oria_svc_n   = array(); // slug => ['name' => .., 'n' => ..]
$oria_id_svc  = array(); // listing id => list of service slugs
$oria_reg_n   = array(); // region slug => ['term' => WP_Term, 'n' => ..]
$oria_sub_n   = array(); // suburb slug => ['term' => WP_Term, 'n' => ..]
$oria_city_slug = (string) ( $oria_city['slug'] ?? '' );
foreach ( $oria_ids as $oria_pid ) {
	$oria_pid = (int) $oria_pid;

	$oria_primary[ $oria_pid ] = function_exists( '\Oria\Core\Primary\of' ) ? (string) \Oria\Core\Primary\of( $oria_pid ) : '';
	if ( in_array( $oria_primary[ $oria_pid ], $oria_family, true ) ) {
		++$oria_spec_n;
	}

	$oria_id_svc[ $oria_pid ] = array();
	foreach ( \Oria\Theme\oria_terms_of( $oria_pid, 'service' ) as $oria_st ) {
		$oria_id_svc[ $oria_pid ][] = $oria_st->slug;
		if ( ! isset( $oria_svc_n[ $oria_st->slug ] ) ) {
			$oria_svc_n[ $oria_st->slug ] = array( 'name' => \Oria\Theme\tname( $oria_st ), 'n' => 0 );
		}
		++$oria_svc_n[ $oria_st->slug ]['n'];
	}

	$oria_seen_reg = array();
	foreach ( \Oria\Theme\oria_terms_of( $oria_pid, 'area' ) as $oria_at ) {
		if ( function_exists( '\Oria\Core\Taxonomies\is_city' ) && \Oria\Core\Taxonomies\is_city( $oria_at ) ) {
			continue;
		}
		// An area in another city: true of the listing, wrong for this page.
		if ( '' !== $oria_city_slug && function_exists( '\Oria\Core\Cities\for_area' ) ) {
			$oria_ac = \Oria\Core\Cities\for_area( $oria_at );
			if ( is_array( $oria_ac ) && ( $oria_ac['slug'] ?? '' ) !== $oria_city_slug ) {
				continue;
			}
		}
		$oria_rt = null;
		if ( \Oria\Core\Taxonomies\is_suburb( $oria_at ) ) {
			if ( ! isset( $oria_sub_n[ $oria_at->slug ] ) ) {
				$oria_sub_n[ $oria_at->slug ] = array( 'term' => $oria_at, 'n' => 0 );
			}
			++$oria_sub_n[ $oria_at->slug ]['n'];
			$oria_rt = \Oria\Core\Taxonomies\region_for( $oria_at );
		} elseif ( \Oria\Core\Taxonomies\is_region( $oria_at ) ) {
			$oria_rt = $oria_at;
		}
		if ( $oria_rt instanceof WP_Term && ! isset( $oria_seen_reg[ $oria_rt->slug ] ) ) {
			$oria_seen_reg[ $oria_rt->slug ] = true;
			if ( ! isset( $oria_reg_n[ $oria_rt->slug ] ) ) {
				$oria_reg_n[ $oria_rt->slug ] = array( 'term' => $oria_rt, 'n' => 0 );
			}
			++$oria_reg_n[ $oria_rt->slug ]['n'];
		}
	}
}
$oria_also_n = $oria_n - $oria_spec_n;
uasort( $oria_reg_n, static fn( array $a, array $b ): int => $b['n'] <=> $a['n'] );
uasort( $oria_sub_n, static fn( array $a, array $b ): int => $b['n'] <=> $a['n'] ?: strcasecmp( $a['term']->name, $b['term']->name ) );

/*
 * Experiences: the category's own treatments that the dock can filter by,
 * each a SERVICE term (svc=), counted over this page's listings -- so the
 * number beside it is the number of results choosing it leaves. Taken from
 * the category's rows (a "page:yin" row resolves to its service) and from
 * the moods below. Not offered when the page itself is one service
 * (/yoga/yin/): ticking a second would widen the page past its own lock.
 */
$oria_parent_t    = ( $oria_term && $oria_term->parent ) ? get_term( (int) $oria_term->parent, 'practice' ) : null;
$oria_parent_slug = $oria_parent_t instanceof WP_Term ? $oria_parent_t->slug : '';
$oria_cfg_slugs   = array_values( array_filter( array( $oria_term ? $oria_term->slug : '', $oria_parent_slug ) ) );
$oria_mood_cfg  = array();
foreach ( $oria_cfg_slugs as $oria_cs ) {
	if ( ! empty( $oria_cfg['moods'][ $oria_cs ] ) && is_array( $oria_cfg['moods'][ $oria_cs ] ) ) {
		$oria_mood_cfg = $oria_cfg['moods'][ $oria_cs ];
		break;
	}
}
$oria_lock_svc = $oria_facet && 'svc' === (string) ( $oria_facet['key'] ?? '' );
$oria_exp      = array(); // slug => ['label', 'n', 'url']
if ( $oria_term && ! $oria_lock_svc ) {
	$oria_cands = array();
	foreach ( $oria_rows as $oria_r ) {
		$oria_rk = function_exists( '\Oria\Core\Intents\row_key' ) ? (string) \Oria\Core\Intents\row_key( $oria_r ) : '';
		if ( 0 === strpos( $oria_rk, 'svc:' ) ) {
			$oria_cands[] = substr( $oria_rk, 4 );
		} elseif ( 0 === strpos( $oria_rk, 'page:' ) && function_exists( '\Oria\Core\PracticesIndex\resolve_facet' ) ) {
			$oria_rf = \Oria\Core\PracticesIndex\resolve_facet( $oria_term, substr( $oria_rk, 5 ) );
			if ( is_array( $oria_rf ) && 'svc' === ( $oria_rf['key'] ?? '' ) && '' !== (string) ( $oria_rf['value'] ?? '' ) ) {
				$oria_cands[] = (string) $oria_rf['value'];
			}
		}
	}
	foreach ( $oria_mood_cfg as $oria_m ) {
		foreach ( (array) ( $oria_m['svc'] ?? array() ) as $oria_ms ) {
			$oria_cands[] = (string) $oria_ms;
		}
	}
	foreach ( array_unique( $oria_cands ) as $oria_slug ) {
		if ( empty( $oria_svc_n[ $oria_slug ]['n'] ) ) {
			continue; // nothing here carries it
		}
		$oria_exp[ $oria_slug ] = array(
			'label' => (string) $oria_svc_n[ $oria_slug ]['name'],
			'n'     => (int) $oria_svc_n[ $oria_slug ]['n'],
			// A clean facet address where there is one (crawlable), this page
			// filtered where there is not.
			'url'   => $oria_row_url( array( 'url' => $oria_here . '?svc=' . rawurlencode( $oria_slug ) . '#dirResults' ) ),
		);
	}
	uasort( $oria_exp, static fn( array $a, array $b ): int => $b['n'] <=> $a['n'] ?: strcasecmp( $a['label'], $b['label'] ) );
}

/*
 * Moods ("Ways to begin"): named groups of those experiences, from the
 * configuration. A mood keeps only experiences this page has, and is
 * dropped when it keeps none; its count is the listings carrying any of
 * them. Moods have no URL of their own -- choosing one ticks its services.
 */
$oria_moods = array();
foreach ( $oria_mood_cfg as $oria_m ) {
	$oria_items = array_values( array_filter( array_map( 'strval', (array) ( $oria_m['svc'] ?? array() ) ), static fn( string $s ): bool => isset( $oria_exp[ $s ] ) ) );
	$oria_items = array_values( array_unique( $oria_items ) );
	if ( ! $oria_items || empty( $oria_m['slug'] ) || empty( $oria_m['name'] ) ) {
		continue;
	}
	$oria_mn = 0;
	foreach ( $oria_id_svc as $oria_svcs ) {
		if ( array_intersect( $oria_items, $oria_svcs ) ) {
			++$oria_mn;
		}
	}
	$oria_moods[] = array(
		'slug'  => sanitize_title( (string) $oria_m['slug'] ),
		'name'  => (string) $oria_m['name'],
		'line'  => (string) ( $oria_m['line'] ?? '' ),
		'items' => $oria_items,
		'n'     => $oria_mn,
	);
}

/*
 * Without moods, "Ways to begin" is the category's largest rows, as real
 * links to their clean addresses. On a facet page the same list offers the
 * category's other kinds, with the whole category first.
 */
$oria_ways = array();
if ( ! $oria_moods && $oria_term ) {
	$oria_wrows = $oria_rows;
	usort( $oria_wrows, static fn( array $a, array $b ): int => (int) $b['count'] <=> (int) $a['count'] );
	if ( $oria_facet ) {
		$oria_ways[] = array( 'label' => sprintf( __( 'All %s', 'oria' ), strtolower( $oria_pname ) ), 'n' => count( $oria_all ), 'url' => $oria_here, 'on' => false );
	}
	foreach ( $oria_wrows as $oria_w ) {
		if ( (int) $oria_w['count'] < 3 ) {
			continue;
		}
		$oria_wh     = $oria_row_url( $oria_w );
		$oria_ways[] = array(
			'label' => (string) $oria_w['label'],
			'n'     => (int) $oria_w['count'],
			'url'   => $oria_wh,
			'on'    => '' !== $oria_facet_href && untrailingslashit( $oria_wh ) === untrailingslashit( $oria_facet_href ),
		);
		if ( count( $oria_ways ) >= 8 ) {
			break;
		}
	}
	if ( count( $oria_ways ) < 2 ) {
		$oria_ways = array();
	}
}

// Popular shortcuts: the four largest experiences, as real links.
$oria_popular = array_slice( $oria_exp, 0, 4, true );

// Location: this page's regions, and its suburbs that clear the facet
// floor. A page already locked to an area has nothing to choose.
$oria_loc_regions = $oria_area ? array() : array_slice( $oria_reg_n, 0, 8, true );
$oria_loc_suburbs = $oria_area ? array() : array_slice( array_filter( $oria_sub_n, static fn( array $r ): bool => $r['n'] >= 3 ), 0, 8, true );
$oria_cities_opt  = ( ! $oria_area && function_exists( '\Oria\Core\Explore\city_options' ) ) ? (array) \Oria\Core\Explore\city_options() : array();
$oria_has_loc     = (bool) ( $oria_loc_regions || $oria_loc_suburbs );

// The snapshot line (brief section 6): four figures, each counted here.
$oria_snap   = array();
/* translators: %s: number of places */
$oria_snap[] = sprintf( _n( '%s place', '%s places', $oria_n, 'oria' ), number_format_i18n( $oria_n ) );
if ( count( $oria_suburbs ) > 1 ) {
	/* translators: %s: number of suburbs */
	$oria_snap[] = sprintf( __( '%s suburbs', 'oria' ), number_format_i18n( count( $oria_suburbs ) ) );
}
if ( $oria_price > 0 ) {
	/* translators: %s: median published starting price */
	$oria_snap[] = sprintf( __( 'Typical published price $%s', 'oria' ), number_format_i18n( round( $oria_price ) ) );
}
if ( $oria_online ) {
	/* translators: %s: number offering online or hybrid sessions */
	$oria_snap[] = sprintf( _n( '%s online or hybrid option', '%s online or hybrid options', $oria_online, 'oria' ), number_format_i18n( $oria_online ) );
}

// The H1 in sentence case, its place in the editorial italic.
$oria_h1_shown = $oria_h1;
$oria_h1_html  = esc_html( $oria_h1 );
if ( $oria_term && '' !== $oria_pname && 0 === strpos( $oria_h1, $oria_pname . ' in ' ) ) {
	$oria_h1_shown = $oria_psent . substr( $oria_h1, strlen( $oria_pname ) );
	$oria_h1_html  = esc_html( $oria_psent ) . ' <span class="edit">' . esc_html( substr( $oria_h1, strlen( $oria_pname ) + 1 ) ) . '</span>';
}

// One supporting line: the category's tagline (its parent's for a sub-
// category), a facet frame's opener, the description; else a plain line.
$oria_tag = '';
if ( $oria_term && function_exists( '\Oria\Core\Categories\tagline_for' ) ) {
	$oria_tag = \Oria\Core\Categories\tagline_for( (string) $oria_term->slug );
	if ( '' === $oria_tag && $oria_term->parent ) {
		$oria_tag = '' !== $oria_parent_slug ? \Oria\Core\Categories\tagline_for( $oria_parent_slug ) : '';
	}
}
// A facet frame's opener is a paragraph, not a line: it opens the results
// instead (still in the server HTML, straight under the results heading).
$oria_opener = ( $oria_facet && ! empty( $oria_frame['opener'] ) ) ? $oria_fill( (string) $oria_frame['opener'] ) : '';
if ( '' !== $oria_tag ) {
	$oria_line = $oria_tag;
} elseif ( $oria_term && '' !== trim( (string) $oria_term->description ) ) {
	$oria_line = (string) $oria_term->description;
} else {
	/* translators: %s: place name (a city or a suburb) */
	$oria_line = sprintf( __( 'Hand-checked places in %s, with prices and reviews where they are published.', 'oria' ), $oria_area ? \Oria\Theme\tname( $oria_area ) : $oria_cname );
}

$oria_updated    = '' !== (string) ( $oria_answer['updated'] ?? '' ) ? (string) $oria_answer['updated'] : date_i18n( 'j F Y' );
$oria_updated_ts = strtotime( $oria_updated );
$oria_updated_dm = $oria_updated_ts ? date_i18n( 'j F', $oria_updated_ts ) : $oria_updated;
$oria_place_name = $oria_area ? \Oria\Theme\tname( $oria_area ) : $oria_cname;

// Compare prompts: the Oria Note's actions, and the guide's opening.
$oria_cmp  = function_exists( '\Oria\Core\Compare\prompt_for_term' ) && $oria_term ? \Oria\Core\Compare\prompt_for_term( $oria_term ) : null;
$oria_gcmp = function_exists( '\Oria\Core\Compare\group_prompt_for_term' ) && $oria_term ? \Oria\Core\Compare\group_prompt_for_term( $oria_term ) : null;

// The map's pins: built here so the dock and ribbon know whether to offer it.
$oria_map = array();
foreach ( $oria_ids as $oria_mid ) {
	$oria_mla = get_post_meta( (int) $oria_mid, 'geo_lat', true );
	$oria_mlo = get_post_meta( (int) $oria_mid, 'geo_lng', true );
	if ( ! is_numeric( $oria_mla ) || ! is_numeric( $oria_mlo ) || 0.0 === (float) $oria_mla ) {
		continue;
	}
	$oria_msub = '';
	foreach ( \Oria\Theme\oria_terms_of( (int) $oria_mid, 'area' ) as $oria_mt ) {
		if ( $oria_mt->parent ) { $oria_msub = $oria_mt->name; break; }
	}
	$oria_map[] = array(
		'n'  => wp_specialchars_decode( (string) get_post_field( 'post_title', $oria_mid, 'raw' ), ENT_QUOTES ),
		'u'  => (string) get_permalink( (int) $oria_mid ),
		'la' => (float) $oria_mla,
		'lo' => (float) $oria_mlo,
		's'  => $oria_msub,
		'i'  => function_exists( '\Oria\Theme\listing_image' ) ? \Oria\Theme\listing_image( (int) $oria_mid ) : '',
		'r'  => function_exists( '\Oria\Core\Places\rating_for' ) ? round( \Oria\Core\Places\rating_for( (int) $oria_mid, false )['rating'], 1 ) : 0,
		'o'  => function_exists( '\Oria\Core\Places\open_now' ) ? \Oria\Core\Places\open_now( (int) $oria_mid ) : null,
	);
}

// "Near you" suburbs for the map's drill-down pills (three or more listings).
$oria_near = array();
foreach ( $oria_sub_n as $oria_ss => $oria_sr ) {
	if ( $oria_sr['n'] >= 3 && $oria_base ) {
		$oria_near[ $oria_ss ] = array( 'name' => $oria_sr['term']->name, 'n' => $oria_sr['n'] );
	}
}
$oria_near = array_slice( $oria_near, 0, 12, true );

$oria_aud = ( $oria_facet && function_exists( '\Oria\Core\IntentPages\audience_note' ) && ! empty( $oria_facet['page'] ) )
	? \Oria\Core\IntentPages\audience_note( $oria_facet['page'], array( 'ids' => $oria_ids ) )
	: null;

/*
 * A dock control: a real button with a visible label, the value it holds,
 * and the panel it opens. JavaScript-only, so a visitor without scripting
 * is never offered a button that does nothing (see the <noscript> rule).
 */
$oria_dock_ctl = static function ( string $panel, string $key, string $key_phone, string $val_key, string $val ): void {
	?>
	<button type="button" class="xc-dock__ctl xc-js" data-xc-open="<?php echo esc_attr( $panel ); ?>" aria-controls="<?php echo esc_attr( $panel ); ?>" aria-expanded="false" aria-haspopup="dialog">
		<span class="xc-dock__k"><span class="xc-wide"><?php echo esc_html( $key ); ?></span><span class="xc-narrow"><?php echo esc_html( $key_phone ); ?></span></span>
		<span class="xc-dock__v" data-xc-val="<?php echo esc_attr( $val_key ); ?>"><?php echo esc_html( $val ); ?></span>
	</button>
	<?php
};

// A treatment checkbox: the engine's own svc filter (app.js binds every
// [data-filter] input on the page, and keeps duplicates in step).
$oria_svc_box = static function ( string $slug, array $e ): void {
	?>
	<label class="xc-check">
		<input type="checkbox" data-filter="svc" value="<?php echo esc_attr( $slug ); ?>">
		<span class="xc-check__label"><?php echo esc_html( $e['label'] ); ?></span>
		<span class="xc-check__n"><?php echo esc_html( number_format_i18n( $e['n'] ) ); ?></span>
	</label>
	<?php
};

$oria_all_label = sprintf( __( 'All %s', 'oria' ), $oria_place_name );
?>

<?php if ( $oria_term ) : ?>
<?php // Controls that need scripting stay out of the way without it. ?>
<noscript><style>.xc-js{display:none!important}</style></noscript>

<!-- 1. Hero: one photograph, full width, text on the left -->
<section class="xc-hz<?php echo '' === $oria_hz['src'] ? ' xc-hz--bare' : ''; ?><?php echo strlen( $oria_h1_shown ) > 30 ? ' xc-hz--long' : ''; ?>" id="decide"<?php echo '' !== $oria_hz['pos'] ? ' style="--xc-hz-pos:' . esc_attr( $oria_hz['pos'] ) . '"' : ''; ?>>
	<?php if ( '' !== $oria_hz['src'] ) : ?>
		<div class="xc-hz__media">
			<img class="xc-hz__img" src="<?php echo esc_url( $oria_hz['src'] ); ?>"<?php echo '' !== $oria_hz['srcset'] ? ' srcset="' . esc_attr( $oria_hz['srcset'] ) . '" sizes="100vw"' : ''; ?> width="<?php echo (int) $oria_hz['w']; ?>" height="<?php echo (int) $oria_hz['h']; ?>" alt="" fetchpriority="high" decoding="async">
		</div>
	<?php endif; ?>
	<div class="xc-hz__shade" aria-hidden="true"></div>
	<div class="wrap xc-hz__inner">
		<nav class="crumbs xc-hz__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/explore/' ) ); ?>"><?php esc_html_e( 'Explore', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( \Oria\Core\Explore\base_url( $oria_city ) ); ?>"><?php echo esc_html( $oria_cname ); ?></a>
			<span aria-hidden="true">/</span>
			<?php if ( $oria_facet ) : ?>
				<a href="<?php echo esc_url( $oria_here ); ?>"><?php echo esc_html( $oria_pname ); ?></a>
				<?php
				// The label carries the city; the crumb already named it.
				$oria_fl = (string) ( $oria_facet['page']['label'] ?? '' );
				if ( '' === $oria_fl && ( $oria_facet['area'] ?? null ) instanceof WP_Term ) {
					$oria_fl = \Oria\Theme\tname( $oria_facet['area'] );
				}
				if ( '' === $oria_fl ) {
					$oria_fl = (string) preg_replace(
						'/ in ' . preg_quote( \Oria\Core\PracticesIndex\label_city(), '/' ) . '$/',
						'',
						(string) $oria_facet['label']
					);
				}
				?>
				<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_fl ); ?></span>
			<?php else : ?>
				<span><?php echo esc_html( $oria_pname ); ?></span>
			<?php endif; ?>
		</nav>

		<div class="xc-hz__copy">
			<p class="micro xc-hz__eyebrow">
				<?php
				if ( $oria_facet ) {
					echo esc_html( $oria_pname ) . ' · ' . esc_html__( 'Filtered view', 'oria' );
				} else {
					/* translators: %s: city name */
					printf( esc_html__( 'Explore %s', 'oria' ), esc_html( $oria_cname ) );
				}
				?>
			</p>
			<h1 class="xc-hz__title"><?php echo $oria_h1_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?></h1>
			<p class="xc-hz__line"><?php echo esc_html( $oria_line ); ?></p>
			<p class="xc-hz__trust">
				<?php
				printf(
					/* translators: 1: number of places, 2: city or suburb, 3: day and month */
					esc_html( _n( '%1$s hand-checked place across %2$s · Updated %3$s', '%1$s hand-checked places across %2$s · Updated %3$s', $oria_n, 'oria' ) ),
					esc_html( number_format_i18n( $oria_n ) ),
					esc_html( $oria_place_name ),
					esc_html( $oria_updated_dm )
				);
				?>
			</p>
			<div class="xc-hz__acts">
				<?php if ( $oria_moods || $oria_ways ) : ?>
					<button type="button" class="btn xc-btn-primary xc-js" data-xc-open="xcWays" aria-controls="xcWays" aria-expanded="false" aria-haspopup="dialog" data-oria-event="category_ways_open"><?php echo $oria_moods ? esc_html__( 'Find your kind of reset', 'oria' ) : esc_html__( 'Ways to begin', 'oria' ); ?></button>
				<?php endif; ?>
				<a class="btn xc-btn-light" href="#results" data-xc-show>
					<?php
					/* translators: %s: number of places */
					printf( esc_html( _n( 'View all %s place', 'View all %s places', $oria_n, 'oria' ) ), esc_html( number_format_i18n( $oria_n ) ) );
					?>
				</a>
			</div>
		</div>
	</div>
</section>

<!-- 2. Discovery Dock: feeling, experience, location, then the places -->
<div class="xc-dockwrap" id="xcDockWrap">
	<div class="wrap xc-dockwrap__inner">
		<div class="xc-dock" id="xcDock" role="search" aria-label="<?php esc_attr_e( 'Find a place', 'oria' ); ?>">
			<?php
			/*
			 * A category x suburb page too small to filter ("Fitness & Movement
			 * in Malaga", seven places, no feelings, experiences or places to
			 * choose between) left the dock as one button in an empty bar. There
			 * it offers the same category in the nearest suburbs instead.
			 */
			$oria_dock_bare = ! $oria_moods && ! $oria_ways && ! $oria_exp && ! $oria_has_loc;
			$oria_near_cat  = ( $oria_dock_bare && $oria_term && $oria_area && \Oria\Core\Taxonomies\is_suburb( $oria_area ) && function_exists( '\Oria\V4\Area\category_nearby' ) )
				? \Oria\V4\Area\category_nearby( $oria_term, $oria_area, is_array( $oria_city ) ? $oria_city : null )
				: array();
			?>
			<div class="xc-dock__controls<?php echo $oria_near_cat ? ' xc-dock__controls--near' : ''; ?>">
				<?php if ( $oria_near_cat ) : ?>
					<div class="xc-near">
						<p class="xc-near__label">
							<?php
							/* translators: %s: category name */
							printf( esc_html__( '%s nearby', 'oria' ), esc_html( $oria_pname ) );
							?>
						</p>
						<ul class="xc-near__list">
							<?php foreach ( $oria_near_cat as $oria_nc ) : ?>
								<li>
									<a class="xc-pchip" href="<?php echo esc_url( $oria_nc['url'] ); ?>" data-oria-event="category_nearby_click"<?php echo '' !== $oria_nc['dir'] ? ' title="' . esc_attr( sprintf( /* translators: 1: suburb, 2: compass direction */ __( '%1$s, to the %2$s', 'oria' ), \Oria\Theme\tname( $oria_nc['term'] ), $oria_nc['dir'] ) ) . '"' : ''; ?>>
										<?php echo esc_html( \Oria\Theme\tname( $oria_nc['term'] ) ); ?> <span class="xc-pchip__n"><?php echo esc_html( number_format_i18n( $oria_nc['n'] ) ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php
				if ( $oria_moods ) {
					$oria_dock_ctl( 'xcWays', __( 'Desired feeling', 'oria' ), __( 'How do you want to feel?', 'oria' ), 'mood', __( 'Any feeling', 'oria' ) );
				} elseif ( $oria_ways ) {
					$oria_dock_ctl( 'xcWays', __( 'Ways to begin', 'oria' ), __( 'Ways to begin', 'oria' ), 'ways', $oria_facet ? __( 'Another kind', 'oria' ) : __( 'Browse by kind', 'oria' ) );
				}
				if ( $oria_exp ) {
					$oria_dock_ctl( 'xcExp', __( 'Experience', 'oria' ), __( 'Choose an experience', 'oria' ), 'exp', __( 'All experiences', 'oria' ) );
				}
				if ( $oria_has_loc ) {
					$oria_dock_ctl( 'xcLoc', __( 'Location', 'oria' ), __( 'Location', 'oria' ), 'loc', $oria_all_label );
				}
				?>
				<a class="btn xc-dock__go" href="#results" data-xc-show>
					<?php
					/* translators: %s: number of places (updated live) */
					printf( esc_html__( 'Show %s places', 'oria' ), '<b data-xc-count>' . esc_html( number_format_i18n( $oria_n ) ) . '</b>' );
					?>
				</a>
			</div>
			<?php if ( $oria_popular || $oria_snap ) : ?>
				<div class="xc-dock__foot">
					<?php if ( $oria_popular ) : ?>
						<ul class="xc-dock__pop" aria-label="<?php esc_attr_e( 'Popular here', 'oria' ); ?>">
							<?php foreach ( $oria_popular as $oria_ps => $oria_pe ) : ?>
								<li>
									<a class="xc-pchip" href="<?php echo esc_url( $oria_pe['url'] ); ?>" data-xc-svc="<?php echo esc_attr( $oria_ps ); ?>" data-oria-event="category_quick_filter_select">
										<?php echo esc_html( $oria_pe['label'] ); ?> <span class="xc-pchip__n"><?php echo esc_html( number_format_i18n( $oria_pe['n'] ) ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
							<?php if ( count( $oria_exp ) > count( $oria_popular ) ) : ?>
								<li class="xc-js xc-more"><button type="button" class="xc-pchip xc-pchip--more" data-xc-open="xcExp" aria-controls="xcExp" aria-expanded="false" aria-haspopup="dialog"><?php esc_html_e( 'More', 'oria' ); ?></button></li>
							<?php endif; ?>
						</ul>
					<?php endif; ?>
					<p class="xc-dock__snap"><?php echo esc_html( implode( ' · ', $oria_snap ) ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<?php
		/*
		 * The dock's panels. A non-modal popover anchored to whatever opened
		 * it on a wide screen; a modal bottom sheet on a phone
		 * (v4-category.js). Never opened by the page itself.
		 */
		?>
		<?php if ( $oria_moods || $oria_ways ) : ?>
			<div class="xc-pop xc-pop--wide" id="xcWays" role="dialog" aria-labelledby="xcWaysTitle" hidden>
				<div class="xc-pop__head">
					<h2 class="xc-pop__title" id="xcWaysTitle"><?php echo $oria_moods ? esc_html__( 'What would feel good right now?', 'oria' ) : esc_html__( 'Ways to begin', 'oria' ); ?></h2>
					<button type="button" class="xc-pop__x" data-xc-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
				</div>
				<div class="xc-pop__body">
					<?php if ( $oria_moods ) : ?>
						<div class="xc-moods" role="group" aria-label="<?php esc_attr_e( 'Moods', 'oria' ); ?>">
							<?php foreach ( $oria_moods as $oria_m ) : ?>
								<button type="button" class="xc-mood" aria-pressed="false" data-xc-mood="<?php echo esc_attr( $oria_m['slug'] ); ?>" data-xc-mood-name="<?php echo esc_attr( $oria_m['name'] ); ?>" data-kind="svc" data-items="<?php echo esc_attr( implode( ',', $oria_m['items'] ) ); ?>">
									<span class="xc-mood__name"><?php echo esc_html( $oria_m['name'] ); ?></span>
									<?php if ( '' !== $oria_m['line'] ) : ?>
										<span class="xc-mood__line"><?php echo esc_html( $oria_m['line'] ); ?></span>
									<?php endif; ?>
									<span class="xc-mood__n">
										<?php
										/* translators: %s: number of places */
										printf( esc_html( _n( '%s place', '%s places', $oria_m['n'], 'oria' ) ), esc_html( number_format_i18n( $oria_m['n'] ) ) );
										?>
									</span>
								</button>
							<?php endforeach; ?>
						</div>
						<?php foreach ( $oria_moods as $oria_m ) : ?>
							<div class="xc-mood__detail" data-xc-mood-detail="<?php echo esc_attr( $oria_m['slug'] ); ?>" hidden>
								<p class="xc-mood__hint">
									<?php
									/* translators: %s: mood name */
									printf( esc_html__( 'In “%s” — untick anything you would rather skip:', 'oria' ), esc_html( $oria_m['name'] ) );
									?>
								</p>
								<div class="xc-checks">
									<?php foreach ( $oria_m['items'] as $oria_it ) { $oria_svc_box( $oria_it, $oria_exp[ $oria_it ] ); } ?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<ul class="xc-ways">
							<?php foreach ( $oria_ways as $oria_w ) : ?>
								<li>
									<a href="<?php echo esc_url( $oria_w['url'] ); ?>" data-oria-event="category_quick_filter_select"<?php echo $oria_w['on'] ? ' aria-current="page"' : ''; ?>>
										<span><?php echo esc_html( $oria_w['label'] ); ?></span>
										<span class="xc-check__n"><?php echo esc_html( number_format_i18n( $oria_w['n'] ) ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<?php if ( $oria_moods ) : ?>
					<div class="xc-pop__foot">
						<button type="button" class="xc-pop__clear" data-xc-clear><?php esc_html_e( 'Clear', 'oria' ); ?></button>
						<a class="btn xc-btn-primary" href="#results" data-xc-show>
							<?php
							/* translators: %s: number of places (updated live) */
							printf( esc_html__( 'Show %s matching places', 'oria' ), '<b data-xc-count>' . esc_html( number_format_i18n( $oria_n ) ) . '</b>' );
							?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $oria_exp ) : ?>
			<div class="xc-pop" id="xcExp" role="dialog" aria-labelledby="xcExpTitle" hidden>
				<div class="xc-pop__head">
					<h2 class="xc-pop__title" id="xcExpTitle"><?php esc_html_e( 'Choose an experience', 'oria' ); ?></h2>
					<button type="button" class="xc-pop__x" data-xc-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
				</div>
				<div class="xc-pop__body">
					<p class="xc-mood__hint"><?php esc_html_e( 'Pick one or several — each number is how many places here offer it.', 'oria' ); ?></p>
					<div class="xc-checks" data-xc-exp-list>
						<?php foreach ( $oria_exp as $oria_es => $oria_ee ) { $oria_svc_box( $oria_es, $oria_ee ); } ?>
					</div>
				</div>
				<div class="xc-pop__foot">
					<button type="button" class="xc-pop__clear" data-xc-clear><?php esc_html_e( 'Clear', 'oria' ); ?></button>
					<a class="btn xc-btn-primary" href="#results" data-xc-show>
						<?php
						/* translators: %s: number of places (updated live) */
						printf( esc_html__( 'Show %s places', 'oria' ), '<b data-xc-count>' . esc_html( number_format_i18n( $oria_n ) ) . '</b>' );
						?>
					</a>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $oria_has_loc ) : ?>
			<div class="xc-pop" id="xcLoc" role="dialog" aria-labelledby="xcLocTitle" hidden>
				<div class="xc-pop__head">
					<h2 class="xc-pop__title" id="xcLocTitle"><?php esc_html_e( 'Where?', 'oria' ); ?></h2>
					<button type="button" class="xc-pop__x" data-xc-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
				</div>
				<div class="xc-pop__body">
					<button type="button" class="xc-near xc-js" data-xc-near><?php esc_html_e( 'Sort by distance from me', 'oria' ); ?></button>
					<?php if ( $oria_loc_regions ) : ?>
						<p class="xc-pop__sub"><?php esc_html_e( 'Areas', 'oria' ); ?></p>
						<div class="xc-checks">
							<?php foreach ( $oria_loc_regions as $oria_rs => $oria_rr ) : ?>
								<label class="xc-check">
									<input type="checkbox" data-filter="region" value="<?php echo esc_attr( $oria_rs ); ?>">
									<span class="xc-check__label"><?php echo esc_html( \Oria\Theme\tname( $oria_rr['term'] ) ); ?></span>
									<span class="xc-check__n"><?php echo esc_html( number_format_i18n( $oria_rr['n'] ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $oria_loc_suburbs ) : ?>
						<p class="xc-pop__sub"><?php esc_html_e( 'Suburbs with the most places', 'oria' ); ?></p>
						<div class="xc-checks">
							<?php foreach ( $oria_loc_suburbs as $oria_sr ) : ?>
								<label class="xc-check">
									<input type="checkbox" data-filter="suburb" value="<?php echo esc_attr( \Oria\Theme\tname( $oria_sr['term'] ) ); ?>">
									<span class="xc-check__label"><?php echo esc_html( \Oria\Theme\tname( $oria_sr['term'] ) ); ?></span>
									<span class="xc-check__n"><?php echo esc_html( number_format_i18n( $oria_sr['n'] ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php
					$oria_other_cities = array_filter( $oria_cities_opt, static fn( $c ): bool => is_array( $c ) && empty( $c['current'] ) && ! empty( $c['url'] ) );
					?>
					<?php if ( $oria_other_cities ) : ?>
						<p class="xc-pop__sub"><?php esc_html_e( 'Another region', 'oria' ); ?></p>
						<ul class="xc-ways xc-ways--cities">
							<?php foreach ( $oria_other_cities as $oria_oc ) : ?>
								<li><a href="<?php echo esc_url( (string) $oria_oc['url'] ); ?>"><span><?php echo esc_html( (string) $oria_oc['name'] ); ?></span><span aria-hidden="true">&rarr;</span></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<div class="xc-pop__foot">
					<button type="button" class="xc-pop__clear" data-xc-clear><?php esc_html_e( 'Clear', 'oria' ); ?></button>
					<a class="btn xc-btn-primary" href="#results" data-xc-show>
						<?php
						/* translators: %s: number of places (updated live) */
						printf( esc_html__( 'Show %s places', 'oria' ), '<b data-xc-count>' . esc_html( number_format_i18n( $oria_n ) ) . '</b>' );
						?>
					</a>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
/*
 * The Discovery Ribbon: the dock's essentials, pinned under the site header
 * once the dock has scrolled away (IntersectionObserver in v4-category.js;
 * invisible and out of the tab order until then). It replaces the page's
 * old section menu.
 */
?>
<div class="xc-ribbon xc-js" id="xcRibbon" role="region" aria-label="<?php esc_attr_e( 'Refine these places', 'oria' ); ?>">
	<div class="wrap xc-ribbon__row">
		<span class="xc-ribbon__cat"><?php echo esc_html( $oria_facet ? $oria_h1_shown : $oria_psent ); ?></span>
		<?php if ( $oria_moods || $oria_exp || $oria_ways ) : ?>
			<button type="button" class="xc-ribbon__btn" data-xc-open="<?php echo ( $oria_moods || $oria_ways ) ? 'xcWays' : 'xcExp'; ?>" aria-controls="<?php echo ( $oria_moods || $oria_ways ) ? 'xcWays' : 'xcExp'; ?>" aria-expanded="false" aria-haspopup="dialog">
				<span data-xc-val="ribbon"><?php esc_html_e( 'All experiences', 'oria' ); ?></span> <span aria-hidden="true">&#9662;</span>
			</button>
		<?php endif; ?>
		<?php if ( $oria_has_loc ) : ?>
			<button type="button" class="xc-ribbon__btn xc-ribbon__btn--loc" data-xc-open="xcLoc" aria-controls="xcLoc" aria-expanded="false" aria-haspopup="dialog">
				<span data-xc-val="loc"><?php echo esc_html( $oria_all_label ); ?></span> <span aria-hidden="true">&#9662;</span>
			</button>
		<?php endif; ?>
		<button type="button" class="xc-ribbon__btn" data-xc-filters aria-controls="xcFilterPanel"><?php esc_html_e( 'Filters', 'oria' ); ?><span data-xc-fcount></span></button>
		<span class="xc-ribbon__count" aria-hidden="true"><b data-xc-count><?php echo esc_html( number_format_i18n( $oria_n ) ); ?></b> <?php esc_html_e( 'places', 'oria' ); ?></span>
		<?php if ( $oria_map ) : ?>
			<button type="button" class="xc-ribbon__btn xc-ribbon__btn--map" data-xc-map><?php esc_html_e( 'Map', 'oria' ); ?></button>
		<?php endif; ?>
	</div>
</div>

<!-- 3. The places -->
<section class="wrap section section--top-flush floor xc-browse" id="browse">
	<div class="xc-reshead">
		<h2 class="h3 results__head" id="results">
			<?php
			if ( $oria_facet || $oria_area ) {
				echo esc_html( $oria_h1_shown );
			} else {
				/* translators: %s: category name, lower case */
				printf( esc_html__( 'All %s', 'oria' ), esc_html( strtolower( $oria_pname ) ) );
			}
			?>
		</h2>
		<p class="dir__count" id="dirCount" role="status" aria-live="polite"></p>
	</div>
	<?php
	/*
	 * With a suburb locked, the other half of the intent has an answer of
	 * its own. AreaContext returns null for a suburb too thin to have a
	 * page, so this never offers a guide we noindex.
	 */
	if ( $oria_area && function_exists( '\Oria\Core\AreaContext\for_term' ) ) {
		$oria_combo_ctx = \Oria\Core\AreaContext\for_term( $oria_area );
		if ( $oria_combo_ctx ) {
			get_template_part(
				'template-parts/area/area-strip',
				null,
				array(
					'area'   => $oria_combo_ctx,
					'lead'   => sprintf(
						/* translators: 1: category, lower case, 2: suburb */
						__( 'You are exploring %1$s in %2$s.', 'oria' ),
						strtolower( $oria_pname ),
						(string) $oria_combo_ctx['name']
					),
					'cta'    => sprintf(
						/* translators: %s: suburb */
						__( 'View the complete %s wellness guide', 'oria' ),
						(string) $oria_combo_ctx['name']
					),
					'source' => 'category-suburb',
				)
			);
		}
	}
	?>
	<?php if ( '' !== $oria_opener ) : ?>
		<p class="xc-opener"><?php echo esc_html( $oria_opener ); ?></p>
	<?php endif; ?>
	<?php
	/*
	 * Why the count is the size it is. Server-drawn for the page as it
	 * arrives; the script recounts it for every filter and hides it when
	 * either side is empty.
	 */
	?>
	<p class="xc-split" id="xcSplit"<?php echo ( $oria_spec_n && $oria_also_n ) ? '' : ' hidden'; ?>>
		<span>
			<?php
			/* translators: %s: category name, lower case */
			printf( esc_html__( 'Specialists in %s', 'oria' ), esc_html( strtolower( $oria_pname ) ) );
			?>
			&mdash; <b data-xc-split="spec"><?php echo esc_html( number_format_i18n( $oria_spec_n ) ); ?></b>
		</span>
		<span>
			<?php
			/* translators: %s: category name, lower case */
			printf( esc_html__( 'Also offering %s', 'oria' ), esc_html( strtolower( $oria_pname ) ) );
			?>
			&mdash; <b data-xc-split="also"><?php echo esc_html( number_format_i18n( $oria_also_n ) ); ?></b>
		</span>
	</p>

	<?php
	get_template_part(
		'template-parts/directory',
		'toolbar',
		array(
			'term'        => $oria_term,
			'ids'         => $oria_ids,
			'mode'        => 'category',
			'style_label' => function_exists( '\Oria\Core\Categories\style_label_for' ) ? \Oria\Core\Categories\style_label_for( $oria_term ) : '',
		)
	);
	?>
	<div class="dirbar">
		<?php if ( $oria_bo['slugs'] ) : ?>
			<?php
			/*
			 * "Oria's picks": the places the Best Of guides shortlisted,
			 * filtered in the list below -- a button, because it changes this
			 * list and goes nowhere. Gold-sealed, never the paid band's look.
			 */
			?>
			<button type="button" class="quickf__chip quickf__chip--best xc-picks" data-best-toggle aria-pressed="false">
				<span class="badge--best__mark" aria-hidden="true">&#10022;</span>
				<?php esc_html_e( 'Oria’s picks', 'oria' ); ?> <b><?php echo esc_html( number_format_i18n( count( $oria_bo['slugs'] ) ) ); ?></b>
			</button>
		<?php endif; ?>
		<?php if ( $oria_map ) : ?>
			<div class="viewswitch" role="group" aria-label="<?php esc_attr_e( 'Show results as', 'oria' ); ?>">
				<button type="button" class="viewswitch__btn" data-view="list" aria-pressed="true"><?php esc_html_e( 'List', 'oria' ); ?></button>
				<button type="button" class="viewswitch__btn" data-view="map" aria-pressed="false"><?php esc_html_e( 'Map', 'oria' ); ?></button>
			</div>
		<?php endif; ?>
	</div>
	<div class="chips" id="dirChips"></div>

	<?php
	/*
	 * Featured: ONE paid placement, a full-width card at the top of the
	 * results, unmistakably labelled. Only a practice whose primary category
	 * is this one (or a sub-category) qualifies; the pick rotates daily
	 * (crc32 of the date and the id), the same for every visitor that day,
	 * so it is fair and page-cache safe. app.js takes it out of the list
	 * while it shows and puts it back the moment the visitor filters.
	 */
	$oria_featured = array();
	foreach ( $oria_ids as $oria_fid ) {
		if ( 'featured' !== \Oria\Theme\display_status( (int) $oria_fid ) ) {
			continue;
		}
		if ( ! in_array( $oria_primary[ (int) $oria_fid ] ?? '', $oria_family, true ) ) {
			continue;
		}
		$oria_featured[] = (int) $oria_fid;
	}
	$oria_day = current_time( 'Y-m-d' );
	usort( $oria_featured, static fn( int $a, int $b ): int => crc32( $oria_day . $a ) <=> crc32( $oria_day . $b ) );
	$oria_featured = array_slice( $oria_featured, 0, 1 );
	?>
	<?php if ( $oria_featured ) : ?>
		<section class="featcat xc-feat" id="featBand" aria-labelledby="featBandHead"
			data-ids="<?php echo esc_attr( implode( ',', array_map( static fn( int $i ): string => (string) get_post_field( 'post_name', $i ), $oria_featured ) ) ); ?>">
			<div class="xc-feat__head">
				<h2 class="xc-feat__label" id="featBandHead"><span class="badge badge--featured"><span class="badge-dot"></span><?php esc_html_e( 'Featured', 'oria' ); ?></span></h2>
				<p class="xc-feat__note"><?php esc_html_e( 'A paid placement from an Oria Haven member.', 'oria' ); ?></p>
				<a class="xc-feat__how" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'How featuring works', 'oria' ); ?></a>
			</div>
			<div class="dir__results featcat__grid">
				<?php
				global $post;
				foreach ( $oria_featured as $oria_fid ) {
					$post = get_post( $oria_fid ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					setup_postdata( $post );
					get_template_part( 'template-parts/listing', 'card' );
				}
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php endif; ?>

	<div class="xc-results">
		<div
			class="dir__results dir__results--wide"
			id="dirResults"
			data-mode="category"
			data-family="<?php echo esc_attr( implode( ' ', $oria_family ) ); ?>"
			<?php if ( $oria_bo['slugs'] ) : ?>
				data-best-picks="<?php echo esc_attr( implode( ',', $oria_bo['slugs'] ) ); ?>"
			<?php endif; ?>
			data-label="<?php echo esc_attr( strtolower( $oria_pname ) ); ?>"
			data-cat="<?php echo esc_attr( $oria_term->slug ); ?>"
			<?php if ( ! empty( $oria_city['slug'] ) ) : ?>
				data-city="<?php echo esc_attr( (string) $oria_city['slug'] ); ?>"
			<?php endif; ?>
			<?php
			/*
			 * Lock the client at the level the term actually sits at: a suburb
			 * by name, a region by slug. A city is locked by data-city above.
			 */
			?>
			<?php if ( $oria_area && \Oria\Core\Taxonomies\is_suburb( $oria_area ) ) : ?>
				data-suburb="<?php echo esc_attr( \Oria\Theme\tname( $oria_area ) ); ?>"
			<?php elseif ( $oria_area && \Oria\Core\Taxonomies\is_region( $oria_area ) ) : ?>
				data-region="<?php echo esc_attr( $oria_area->slug ); ?>"
			<?php endif; ?>
			<?php if ( $oria_facet ) : ?>
				data-intent-key="<?php echo esc_attr( (string) $oria_facet['key'] ); ?>"
				data-intent-value="<?php echo esc_attr( (string) $oria_facet['value'] ); ?>"
			<?php endif; ?>
		>
			<?php
			$oria_shown = array();
			if ( $oria_facet || $oria_area ) {
				/*
				 * The matching set, server-rendered, specialists first then
				 * alphabetical — the page with scripting off. The script
				 * re-renders the same set from the payload.
				 */
				$oria_posts = $oria_ids
					? get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'post__in' => array_map( 'intval', $oria_ids ), 'posts_per_page' => 24, 'orderby' => 'title', 'order' => 'ASC' ) )
					: array();
				$oria_spec = static function ( WP_Post $p ) use ( $oria_family, $oria_primary ): int {
					return in_array( $oria_primary[ (int) $p->ID ] ?? '', $oria_family, true ) ? 0 : 1;
				};
				usort(
					$oria_posts,
					static fn( WP_Post $a, WP_Post $b ): int => $oria_spec( $a ) <=> $oria_spec( $b ) ?: strcasecmp( $a->post_title, $b->post_title )
				);
				global $post;
				foreach ( $oria_posts as $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					setup_postdata( $post );
					$oria_shown[] = (int) $post->ID;
					get_template_part( 'template-parts/listing', 'card' );
				}
				wp_reset_postdata();
			} else {
				while ( have_posts() ) :
					the_post();
					$oria_shown[] = (int) get_the_ID();
					get_template_part( 'template-parts/listing', 'card' );
				endwhile;
			}

			/*
			 * Tell the ItemList what is actually here. On a facet or area page
			 * the set above is nothing like the main query.
			 */
			if ( function_exists( '\Oria\Core\Schema\register_list' ) ) {
				\Oria\Core\Schema\register_list( $oria_shown );
			}
			?>
		</div>

		<?php if ( $oria_map ) : ?>
			<?php // Phones: the floating way into the full-screen map (the ribbon's Map button replaces it once scripting runs). ?>
			<button type="button" class="mapfab" data-view="map" aria-pressed="false"><?php esc_html_e( 'Map', 'oria' ); ?></button>
			<div class="dirmap" id="catMapView" hidden>
				<button type="button" class="dirmap__close" data-view="list" aria-pressed="false"><?php esc_html_e( 'Back to list', 'oria' ); ?></button>
				<div class="catmap catmap--view" data-catmap role="img" aria-label="<?php printf( esc_attr__( 'Map of %1$s places across %2$s', 'oria' ), esc_attr( $oria_pname ), esc_attr( $oria_cname ) ); ?>">
					<div class="catmap__tip" hidden></div>
				</div>
				<script type="application/json" data-catmap-data><?php echo wp_json_encode( $oria_map ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON in a data script tag ?></script>
				<?php if ( $oria_near ) : ?>
					<div class="nearyou nearyou--map">
						<h2 class="h4"><?php printf( esc_html__( '%s near you', 'oria' ), esc_html( $oria_psent ) ); ?></h2>
						<div class="nearyou__pills">
							<?php foreach ( $oria_near as $oria_nslug => $oria_nrow ) : ?>
								<a class="pill" data-suburb="<?php echo esc_attr( $oria_nrow['name'] ); ?>" href="<?php echo esc_url( \Oria\Core\PracticesIndex\category_url( $oria_term ) . $oria_nslug . '/' ); ?>">
									<?php echo esc_html( $oria_nrow['name'] ); ?> <span class="nearyou__n"><?php echo esc_html( number_format_i18n( $oria_nrow['n'] ) ); ?></span>
								</a>
							<?php endforeach; ?>
						</div>
						<p class="hint" style="margin-top:.5rem"><?php esc_html_e( 'Click a suburb to zoom the map there — click it again to zoom back out.', 'oria' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php
	/*
	 * The Oria Note (brief 9.1): decision help after the sixth listing.
	 * Its copy comes from the configuration (spa has one); elsewhere the
	 * category's own compare prompt stands in, and with neither it is left
	 * out. Actions are real pages only: the compare page the prompts point
	 * at, and the category's first guide.
	 *
	 * Drawn here, after the list, so the page is whole without scripting;
	 * v4-category.js moves it (and Local intelligence) into the list on the
	 * first page and puts them back after every app.js re-render.
	 */
	$oria_note_cfg = array();
	foreach ( $oria_cfg_slugs as $oria_cs ) {
		if ( ! empty( $oria_cfg['note'][ $oria_cs ]['heading'] ) ) {
			$oria_note_cfg = (array) $oria_cfg['note'][ $oria_cs ];
			break;
		}
	}
	$oria_note_cmp   = $oria_gcmp ?: $oria_cmp;
	$oria_note_guide = $oria_guides ? $oria_guides[0] : null;
	?>
	<?php if ( ( $oria_note_cfg && ( $oria_note_cmp || $oria_note_guide ) ) || $oria_note_cmp ) : ?>
		<aside class="xc-note" id="xcNote" aria-labelledby="xcNoteTitle">
			<p class="micro xc-note__eyebrow"><?php esc_html_e( 'The Oria note', 'oria' ); ?></p>
			<h3 class="xc-note__title" id="xcNoteTitle"><?php echo $oria_note_cfg ? esc_html( (string) $oria_note_cfg['heading'] ) : esc_html__( 'Deciding between two?', 'oria' ); ?></h3>
			<?php if ( ! empty( $oria_note_cfg['copy'] ) ) : ?>
				<p class="xc-note__copy"><?php echo esc_html( (string) $oria_note_cfg['copy'] ); ?></p>
			<?php endif; ?>
			<p class="xc-note__acts">
				<?php if ( $oria_note_cmp ) : ?>
					<a class="btn btn--sm btn--dark" href="<?php echo esc_url( $oria_note_cmp['url'] ); ?>" data-oria-event="<?php echo $oria_gcmp ? 'category_compare_group' : 'category_compare'; ?>">
						<?php echo $oria_note_cfg ? esc_html__( 'Compare the experiences', 'oria' ) : esc_html( $oria_note_cmp['label'] ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $oria_note_cfg && $oria_note_guide ) : ?>
					<a class="xc-note__link" href="<?php echo esc_url( (string) get_permalink( $oria_note_guide ) ); ?>" data-oria-event="category_guide_click"><?php esc_html_e( 'Read the full guide', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
				<?php endif; ?>
			</p>
		</aside>
	<?php endif; ?>

	<?php
	/*
	 * Local intelligence (brief 9.2): only what the listings on this page
	 * say. The suburbs with the most places here (three or more each, so
	 * every link lands on a real suburb page), and which area holds the
	 * most. Thin data -- fewer than two such suburbs -- and it is left out.
	 */
	$oria_local_subs = array_slice( array_filter( $oria_sub_n, static fn( array $r ): bool => $r['n'] >= 3 ), 0, 3, true );
	$oria_local_reg  = $oria_area ? null : ( $oria_reg_n ? reset( $oria_reg_n ) : null );
	?>
	<?php if ( ! $oria_area && count( $oria_local_subs ) >= 2 ) : ?>
		<aside class="xc-local" id="xcLocal" aria-labelledby="xcLocalTitle">
			<h3 class="xc-local__title" id="xcLocalTitle">
				<?php
				/* translators: %s: city name */
				printf( esc_html__( 'Around %s', 'oria' ), esc_html( $oria_cname ) );
				?>
			</h3>
			<p class="xc-local__copy">
				<?php
				if ( $oria_local_reg && $oria_local_reg['n'] > 0 ) {
					printf(
						/* translators: 1: linked area name, 2: count, 3: total */
						esc_html__( '%1$s has the most options — %2$s of these %3$s places.', 'oria' ),
						'<a href="' . esc_url( \Oria\Core\PracticesIndex\area_url( $oria_term, $oria_local_reg['term'] ) ) . '">' . esc_html( \Oria\Theme\tname( $oria_local_reg['term'] ) ) . '</a>',
						esc_html( number_format_i18n( $oria_local_reg['n'] ) ),
						esc_html( number_format_i18n( $oria_n ) )
					);
					echo ' ';
				}
				$oria_local_links = array();
				foreach ( $oria_local_subs as $oria_ls => $oria_lr ) {
					$oria_local_links[] = '<a href="' . esc_url( \Oria\Core\PracticesIndex\area_url( $oria_term, $oria_lr['term'] ) ) . '">' . esc_html( \Oria\Theme\tname( $oria_lr['term'] ) ) . '</a> (' . esc_html( number_format_i18n( $oria_lr['n'] ) ) . ')';
				}
				printf(
					/* translators: %s: list of linked suburbs with counts */
					esc_html__( 'The busiest suburbs: %s.', 'oria' ),
					implode( ', ', $oria_local_links ) // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
				);
				?>
			</p>
		</aside>
	<?php endif; ?>

	<?php
	/*
	 * "About {category} in {place}" (brief section 6): the figures behind the
	 * snapshot, after the first set of results, one tap away. In the HTML
	 * either way, so the quotable sentences stay available to search.
	 */
	?>
	<details class="catabout xc-about">
		<summary>
			<span>
				<?php
				/* translators: %s: category name, lower case */
				printf( esc_html__( 'How Oria chooses these %s places', 'oria' ), esc_html( strtolower( $oria_pname ) ) );
				?>
			</span>
			<span class="catabout__mark" aria-hidden="true"></span>
		</summary>
		<div class="catabout__body">
			<?php if ( ! $oria_facet && $oria_answer['sentences'] ) : ?>
				<p><?php echo esc_html( implode( ' ', $oria_answer['sentences'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( $oria_facet ) : ?>
				<p>
					<?php
					printf(
						/* translators: 1: matching count, 2: category total, 3: category name. */
						esc_html__( '%1$s of the %2$s %3$s listings in the directory match this view. The count is live, and the order puts specialists first — paid placement never moves anyone up it.', 'oria' ),
						'<b>' . esc_html( number_format_i18n( $oria_n ) ) . '</b>',
						esc_html( number_format_i18n( count( $oria_all ) ) ),
						esc_html( strtolower( $oria_pname ) )
					);
					?>
				</p>
				<?php if ( $oria_aud ) : ?>
					<p>
						<?php
						printf(
							/* translators: 1: audience name, 2: how many say so, 3: how many on this page. */
							esc_html__( '%1$s — %2$s of the %3$s here say so on their own website or timetable.', 'oria' ),
							esc_html( (string) $oria_aud['name'] ),
							esc_html( number_format_i18n( (int) $oria_aud['yes'] ) ),
							esc_html( number_format_i18n( (int) $oria_aud['of'] ) )
						);
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( ( $oria_facet || $oria_area ) && $oria_ids && $oria_all ) : ?>
				<p>
					<?php
					printf(
						/* translators: 1: month year, 2: count, 3: total, 4: category, 5: place */
						esc_html__( 'As of %1$s, %2$s of the %3$s %4$s listings on Oria Haven are in this view (%5$s) — counted live from the directory.', 'oria' ),
						esc_html( date_i18n( 'F Y' ) ),
						esc_html( number_format_i18n( $oria_n ) ),
						esc_html( number_format_i18n( count( $oria_all ) ) ),
						esc_html( $oria_pname ),
						esc_html( $oria_place_name )
					);
					?>
				</p>
			<?php endif; ?>
			<?php if ( $oria_prices ) : ?>
				<p>
					<?php
					printf(
						/* translators: 1: listings publishing a price, 2: lowest, 3: highest */
						esc_html( _n( '%1$s place here publishes a starting price, from $%2$s to $%3$s.', '%1$s places here publish a starting price, from $%2$s to $%3$s.', count( $oria_prices ), 'oria' ) ),
						esc_html( number_format_i18n( count( $oria_prices ) ) ),
						esc_html( number_format_i18n( (float) min( $oria_prices ) ) ),
						esc_html( number_format_i18n( (float) max( $oria_prices ) ) )
					);
					if ( $oria_price > 0 ) {
						echo ' ';
						/* translators: %s: median price */
						printf( esc_html__( 'The median is $%s.', 'oria' ), esc_html( number_format_i18n( round( $oria_price ) ) ) );
					}
					?>
				</p>
			<?php endif; ?>
			<?php if ( $oria_spec_n && $oria_also_n ) : ?>
				<p>
					<?php
					printf(
						/* translators: 1: specialists count, 2: category, lower case, 3: others count */
						esc_html__( '%1$s of these specialise in %2$s — it is their main focus. The other %3$s list it among other things they offer.', 'oria' ),
						esc_html( number_format_i18n( $oria_spec_n ) ),
						esc_html( strtolower( $oria_pname ) ),
						esc_html( number_format_i18n( $oria_also_n ) )
					);
					?>
				</p>
			<?php endif; ?>
			<p>
				<?php
				/* translators: %s: date */
				printf( esc_html__( 'Every listing is hand-checked by Oria Haven. Figures updated %s.', 'oria' ), esc_html( $oria_updated ) );
				?>
			</p>
			<p class="hint">
				<?php esc_html_e( 'Most relevant means practices that specialise in this first, then the ones with the most reviews to go on. A listing that publishes its price, describes itself and names its services gets a small nudge, never more than a quarter of a star, because you can decide on it without ringing. Paid placements are shown once, in their own band, and never move anyone up the list.', 'oria' ); ?>
			</p>
		</div>
	</details>

	<?php
	/*
	 * The way out for somebody the filters have not served: say it in their
	 * own words to Ask Oria. The example names this category and a suburb it
	 * really has listings in -- a place and a time, never a symptom.
	 */
	$oria_ask_sub = '';
	foreach ( $oria_near as $oria_nrow ) {
		$oria_ask_sub = (string) ( $oria_nrow['name'] ?? '' );
		break;
	}
	$oria_ask_eg = '' !== $oria_ask_sub
		/* translators: 1: category name, lowercased, 2: suburb */
		? sprintf( __( 'e.g. %1$s near %2$s after work', 'oria' ), strtolower( $oria_pname ), $oria_ask_sub )
		/* translators: %s: category name, lowercased */
		: sprintf( __( 'e.g. %s near the city after work', 'oria' ), strtolower( $oria_pname ) );
	?>
	<aside class="askband" aria-labelledby="askband-title">
		<?php get_template_part( 'template-parts/oria-orb', null, array( 'class' => 'askband__orb', 'uid' => 'band' ) ); ?>
		<div class="askband__text">
			<p class="micro askband__eyebrow"><?php esc_html_e( 'Ask Oria', 'oria' ); ?></p>
			<h2 class="askband__title" id="askband-title"><?php esc_html_e( 'Can’t find what you’re looking for?', 'oria' ); ?></h2>
			<p class="askband__lede">
				<?php
				printf(
					/* translators: %s: city name */
					esc_html__( 'Say it in your own words — where, when, what to spend, how it should feel — and we’ll look through every listing in %s.', 'oria' ),
					esc_html( $oria_cname )
				);
				?>
			</p>
		</div>
		<div class="askband__act">
			<form class="askband__form" action="<?php echo esc_url( home_url( '/ask/' ) ); ?>" method="get" data-oria-event="category_ask_start">
				<label class="sr-only" for="askband-q"><?php esc_html_e( 'Describe what you are looking for', 'oria' ); ?></label>
				<input class="askband__input" type="text" id="askband-q" name="q" maxlength="400" autocomplete="off" placeholder="<?php echo esc_attr( $oria_ask_eg ); ?>">
				<button class="btn btn--dark askband__go" type="submit"><?php esc_html_e( 'Ask Oria', 'oria' ); ?><span aria-hidden="true"><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></span></button>
			</form>
			<p class="askband__alt">
				<?php esc_html_e( 'Not sure which kind of practice suits you?', 'oria' ); ?>
				<a href="<?php echo esc_url( home_url( '/compare/build/' ) ); ?>" data-oria-event="category_build_session_click"><?php esc_html_e( 'Build your session', 'oria' ); ?></a>
			</p>
		</div>
	</aside>
	<div class="xc-browse-end" aria-hidden="true"></div>
</section>

<?php
/*
 * 4. Best Of shelf (brief 9.3): up to three of this category's guides, as
 * picture cards with the guide's own opening line and how many of these
 * places it shortlisted. Below the results, never before them.
 */
$oria_bo_cards = array_slice( (array) $oria_bo['guides'], 0, 3 );
?>
<?php if ( $oria_bo_cards && function_exists( '\Oria\Core\BestOf\intro' ) ) : ?>
	<section class="wrap xc-bo" id="xcBestOf" aria-labelledby="xcBestOfTitle">
		<div class="xc-bo__head">
			<p class="micro xc-bo__eyebrow"><span class="badge--best__mark" aria-hidden="true">&#10022;</span> <?php esc_html_e( 'From our Best Of guides', 'oria' ); ?></p>
			<h2 class="h3 xc-bo__title" id="xcBestOfTitle"><?php esc_html_e( 'Shortlisted by our editors', 'oria' ); ?></h2>
			<p class="xc-bo__note"><?php esc_html_e( 'Editorial choices, never paid for.', 'oria' ); ?></p>
		</div>
		<ul class="xc-bo__grid">
			<?php foreach ( $oria_bo_cards as $oria_g ) : ?>
				<?php
				$oria_gid  = (int) $oria_g['id'];
				$oria_gimg = has_post_thumbnail( $oria_gid ) ? (string) get_the_post_thumbnail_url( $oria_gid, 'medium_large' ) : '';
				if ( '' === $oria_gimg && ! empty( $oria_g['picks'][0]['listing'] ) ) {
					// No cover yet: the first pick's photo, as the guide cards elsewhere do.
					$oria_gimg = \Oria\Theme\listing_image( (int) $oria_g['picks'][0]['listing'] );
				}
				$oria_gwhy = wp_trim_words( wp_strip_all_tags( \Oria\Core\BestOf\intro( $oria_gid ) ), 18 );
				?>
				<li>
					<a class="xc-bocard" href="<?php echo esc_url( $oria_g['url'] ); ?>" data-oria-event="category_best_of_guide_click">
						<span class="xc-bocard__pic">
							<?php if ( '' !== $oria_gimg ) : ?>
								<img src="<?php echo esc_url( $oria_gimg ); ?>" alt="" loading="lazy" decoding="async" width="480" height="360">
							<?php endif; ?>
						</span>
						<span class="xc-bocard__body">
							<strong class="xc-bocard__title"><?php echo esc_html( $oria_g['title'] ); ?></strong>
							<?php if ( '' !== $oria_gwhy ) : ?>
								<span class="xc-bocard__why"><?php echo esc_html( $oria_gwhy ); ?></span>
							<?php endif; ?>
							<span class="xc-bocard__n">
								<?php
								/* translators: %s: number of places from this page in the guide */
								printf( esc_html( _n( '%s of these places shortlisted', '%s of these places shortlisted', count( $oria_g['picks'] ), 'oria' ) ), esc_html( number_format_i18n( count( $oria_g['picks'] ) ) ) );
								?>
							</span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php
/*
 * 5. One upcoming experience (brief 9.4), ticket style: the first event
 * category_events() returns -- tagged with this category or one of its
 * sub-categories, in this city, not over. None, no section.
 */
$oria_ev_row = $oria_events ? $oria_events[0] : null;
$oria_ev     = $oria_ev_row ? get_post( (int) $oria_ev_row['id'] ) : null;
?>
<?php if ( $oria_ev instanceof WP_Post ) : ?>
	<?php
	$oria_ev_time = $oria_ev_row['now']
		? __( 'On now', 'oria' )
		: ( '00:00' === gmdate( 'H:i', $oria_ev_row['ts'] ) ? gmdate( 'D j M', $oria_ev_row['ts'] ) : gmdate( 'D j M, g.ia', $oria_ev_row['ts'] ) );
	$oria_ev_img  = has_post_thumbnail( $oria_ev ) ? (string) get_the_post_thumbnail_url( $oria_ev, 'medium_large' ) : ( function_exists( '\Oria\Theme\event_scene' ) ? (string) \Oria\Theme\event_scene( $oria_ev->ID ) : '' );
	?>
	<section class="wrap xc-event" id="events" aria-labelledby="xcEventTitle">
		<p class="micro xc-event__eyebrow"><?php esc_html_e( "What's on", 'oria' ); ?></p>
		<h2 class="h3 xc-event__heading" id="xcEventTitle">
			<?php
			/* translators: %s: category name, lower case */
			printf( esc_html__( 'Upcoming %s experience', 'oria' ), esc_html( strtolower( $oria_pname ) ) );
			?>
		</h2>
		<a class="xc-ticket" href="<?php echo esc_url( (string) get_permalink( $oria_ev ) ); ?>" data-oria-event="category_event">
			<span class="xc-ticket__pic">
				<?php if ( '' !== $oria_ev_img ) : ?>
					<img src="<?php echo esc_url( $oria_ev_img ); ?>" alt="" loading="lazy" decoding="async" width="480" height="360">
				<?php endif; ?>
			</span>
			<span class="xc-ticket__body">
				<time class="xc-ticket__time" datetime="<?php echo esc_attr( gmdate( 'Y-m-d\TH:i', $oria_ev_row['ts'] ) ); ?>"><?php echo esc_html( $oria_ev_time ); ?></time>
				<strong class="xc-ticket__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev ) ); ?></strong>
				<span class="xc-ticket__meta">
					<?php echo esc_html( (string) $oria_ev_row['suburb'] ); ?>
					<?php if ( ! $oria_ev_row['member'] && ! empty( $oria_ev_row['src'] ) ) : ?>
						· <?php echo esc_html( sprintf( __( 'via %s', 'oria' ), $oria_ev_row['src'] ) ); ?>
					<?php endif; ?>
				</span>
			</span>
			<span class="xc-ticket__stub">
				<?php if ( ! empty( $oria_ev_row['price'] ) ) : ?>
					<span class="xc-ticket__price"><?php echo esc_html( (string) $oria_ev_row['price'] ); ?></span>
				<?php endif; ?>
				<span class="xc-ticket__go"><?php esc_html_e( 'Details', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></span>
			</span>
		</a>
		<?php if ( count( $oria_events ) > 1 && 'perth' === $oria_city_slug ) : ?>
			<p class="xc-event__more">
				<a href="<?php echo esc_url( home_url( '/whats-on-perth/' ) ); ?>">
					<?php
					/* translators: %d: more events in this category */
					printf( esc_html( _n( '%d more on What’s On', '%d more on What’s On', count( $oria_events ) - 1, 'oria' ) ), count( $oria_events ) - 1 );
					?>
					<span aria-hidden="true">&rarr;</span>
				</a>
			</p>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php
// The category's introduction, whole, as the guide.
$oria_intro_html = ( is_string( $oria_intro ) && '' !== trim( $oria_intro ) && $oria_term )
	? (string) \Oria\Core\PracticesIndex\rewrite_content_links( (string) $oria_intro, $oria_term )
	: '';
// A service or specialty facet's own guide and neighbours, where written.
$oria_fg_on    = $oria_facet && ! $oria_area && function_exists( '\Oria\Core\FacetGuides\intro' );
$oria_fg_intro = $oria_fg_on ? \Oria\Core\FacetGuides\intro( $oria_facet ) : array();
$oria_fg_also  = $oria_fg_on ? \Oria\Core\FacetGuides\see_also( $oria_facet, is_array( $oria_city ) ? $oria_city : null ) : array();
$oria_fg_name  = $oria_fg_on
	? ( '' !== \Oria\Core\FacetGuides\phrase( $oria_facet ) ? \Oria\Core\FacetGuides\phrase( $oria_facet ) . ' ' . sprintf( /* translators: %s: city */ __( 'in %s', 'oria' ), $oria_cname ) : (string) $oria_facet['label'] )
	: '';
?>
<!-- 6. Guide -->
<section class="wrap section floor xc-guide" id="read">
	<p class="micro floor__label"><?php esc_html_e( 'Guide', 'oria' ); ?></p>
	<?php if ( $oria_gcmp && ! $oria_note_cfg ) : ?>
		<p class="cmpnudge cmpnudge--group">
			<a href="<?php echo esc_url( $oria_gcmp['url'] ); ?>" data-oria-event="category_compare_group">
				<?php echo esc_html( $oria_gcmp['label'] ); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</p>
	<?php endif; ?>
	<?php if ( $oria_cmp && $oria_gcmp ) : ?>
		<p class="cmpnudge">
			<a href="<?php echo esc_url( $oria_cmp['url'] ); ?>" data-oria-event="category_compare">
				<?php echo esc_html( $oria_cmp['label'] ); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</p>
	<?php endif; ?>

	<?php if ( $oria_facet && ! empty( $oria_frame['worth_knowing'] ) ) : ?>
		<h2 class="h3" style="margin-bottom:1rem"><?php esc_html_e( 'Worth knowing', 'oria' ); ?></h2>
		<div class="prose prose--intro">
			<?php foreach ( (array) $oria_frame['worth_knowing'] as $oria_para ) : ?>
				<p><?php echo esc_html( $oria_fill( (string) $oria_para ) ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php elseif ( $oria_fg_intro ) : ?>
		<?php
		/*
		 * The facet's own guide (data/facet-guides.json, else the specialty
		 * intro for the same slug), in place of the category's: without it
		 * every Spa facet page carried the same 369 words.
		 */
		?>
		<h2 class="h3" style="margin-bottom:1rem">
			<?php
			/* translators: %s: facet name, e.g. Ice baths & cold plunges in Perth */
			printf( esc_html__( 'Before you go: %s', 'oria' ), esc_html( $oria_fg_name ) );
			?>
		</h2>
		<div class="prose prose--intro">
			<?php foreach ( $oria_fg_intro as $oria_para ) : ?>
				<p><?php echo esc_html( $oria_fill( (string) $oria_para ) ); // {count} stays live ?></p>
			<?php endforeach; ?>
		</div>
	<?php elseif ( '' !== $oria_intro_html ) : ?>
		<h2 class="h3" style="margin-bottom:1rem">
			<?php
			/* translators: 1: category, lower case, 2: city */
			printf( esc_html__( 'Before you go: %1$s in %2$s', 'oria' ), esc_html( strtolower( $oria_pname ) ), esc_html( $oria_cname ) );
			?>
		</h2>
		<div class="prose prose--intro"><?php echo wp_kses_post( $oria_intro_html ); ?></div>
	<?php endif; ?>

	<?php if ( $oria_fg_also ) : ?>
		<?php // Hand-picked neighbours: "Want both? Ice bath & sauna together". ?>
		<nav class="xc-also" aria-label="<?php esc_attr_e( 'Related pages', 'oria' ); ?>">
			<p class="micro xc-also__label"><?php esc_html_e( 'You might also look at', 'oria' ); ?></p>
			<ul class="xc-also__list">
				<?php foreach ( $oria_fg_also as $oria_sa ) : ?>
					<li>
						<a class="xc-also__link" href="<?php echo esc_url( $oria_sa['url'] ); ?>" data-oria-event="category_see_also_click">
							<span class="xc-also__name"><?php echo esc_html( $oria_sa['label'] ); ?> <span aria-hidden="true">&rarr;</span></span>
							<?php if ( '' !== $oria_sa['line'] ) : ?>
								<span class="xc-also__line"><?php echo esc_html( $oria_sa['line'] ); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<?php
	// Trend to Try: one published trend an editor tied to this category.
	if ( ! $oria_area && $oria_term && function_exists( '\Oria\Core\Trends\for_practice' ) ) {
		get_template_part( 'template-parts/trend-context', null, array( 'trends' => \Oria\Core\Trends\for_practice( $oria_term ), 'location' => 'category_page' ) );
	}
	?>
</section>

<?php
/*
 * What's on in this category. Only when there is something: an "Upcoming
 * yoga events" heading over an empty row tells a visitor the category is
 * dead, which is the opposite of what it is for.
 */
if ( $oria_term && function_exists( '\Oria\Core\Events\for_practice' ) ) {
	get_template_part(
		'template-parts/events-module',
		null,
		array(
			'ids'       => \Oria\Core\Events\for_practice( $oria_term->slug, 3 ),
			/* translators: %s: category name, lower case */
			'title'     => sprintf( __( 'Upcoming %s events in Perth', 'oria' ), strtolower( $oria_pname ) ),
			'all'       => get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ),
			'all_label' => __( "See what's on", 'oria' ),
		)
	);
}
?>

<?php
// The guides for this practice, as image cards; the journal's latest where
// none are tagged to it yet.
get_template_part(
	'template-parts/guides',
	'floor',
	array(
		'guides'  => $oria_guides ?: $oria_latest,
		'heading' => $oria_guides ? sprintf( __( 'Guides to %s worth reading first', 'oria' ), strtolower( $oria_pname ) ) : __( 'From the journal', 'oria' ),
		'icon'    => ( $oria_term && function_exists( '\Oria\Core\Categories\icon' ) ) ? \Oria\Core\Categories\icon( $oria_term->slug ) : '',
		'compact' => true,
	)
);
?>

<?php
// 7. FAQs: the frame's on a facet page (none if it has no frame), the
// category's otherwise.
if ( $oria_facet ) {
	$oria_filled = array_map( static fn( array $qa ): array => array( 'q' => $oria_fill( $qa['q'] ), 'a' => $oria_fill( $qa['a'] ) ), $oria_faqs );
	if ( $oria_filled ) {
		get_template_part( 'template-parts/faq', null, array( 'faqs' => $oria_filled, 'heading' => sprintf( __( '%s — common questions', 'oria' ), $oria_h1_shown ), 'id' => 'faq' ) );
	}
} elseif ( $oria_faqs ) {
	get_template_part( 'template-parts/faq', null, array( 'faqs' => $oria_faqs, 'heading' => sprintf( __( 'Questions people ask about %1$s in %2$s', 'oria' ), strtolower( $oria_pname ), $oria_cname ), 'id' => 'faq' ) );
}
?>

<!-- 8. Browse by area and by experience: the crawlable mesh -->
<section class="wrap section section--top-flush floor xc-mesh">
	<?php
	$oria_counts  = \Oria\Theme\combo_counts( $oria_term->slug );
	$oria_regions = \Oria\Core\Taxonomies\regions();
	$oria_regions = is_wp_error( $oria_regions ) ? array() : $oria_regions;
	// regions() spans every city: keep this page's.
	if ( $oria_city && function_exists( '\Oria\Core\Cities\for_area' ) ) {
		$oria_regions = array_values(
			array_filter(
				$oria_regions,
				static function ( $oria_rt ) use ( $oria_city ): bool {
					$oria_rc = \Oria\Core\Cities\for_area( $oria_rt );
					return ! is_array( $oria_rc ) || ( $oria_rc['slug'] ?? '' ) === ( $oria_city['slug'] ?? '' );
				}
			)
		);
	}
	/*
	 * Regions and suburbs kept apart. They used to share one row, which
	 * put "Northern Suburbs" beside "Two Rocks" and asked a visitor to
	 * tell a fifth of the city from a single street.
	 */
	$oria_region_links = array();
	foreach ( $oria_regions as $oria_r ) {
		$oria_rn = (int) ( $oria_counts['regions'][ $oria_r->slug ] ?? 0 );
		if ( $oria_rn > 0 ) {
			$oria_region_links[] = array( \Oria\Core\PracticesIndex\area_url( $oria_term, $oria_r ), \Oria\Theme\tname( $oria_r ), $oria_rn );
		}
	}
	usort( $oria_region_links, static fn( array $a, array $b ): int => $b[2] <=> $a[2] );

	$oria_sub_links = array();
	foreach ( $oria_counts['suburbs'] as $oria_sname => $oria_rn ) {
		$oria_s = get_term_by( 'slug', sanitize_title( $oria_sname ), 'area' );
		if ( $oria_s instanceof WP_Term && 0 !== $oria_s->parent ) {
			$oria_sub_links[] = array( \Oria\Core\PracticesIndex\area_url( $oria_term, $oria_s ), \Oria\Theme\tname( $oria_s ), (int) $oria_rn );
		}
	}
	usort( $oria_sub_links, static fn( array $a, array $b ): int => $b[2] <=> $a[2] ?: strcasecmp( $a[1], $b[1] ) );

	/*
	 * The treatments, richest first, each with the line the intent
	 * registry already carries -- "Firm, focused work, often with a
	 * health-fund receipt" is somebody's sentence, not a generated one.
	 *
	 * A row that merely repeats the category is dropped: somebody on the
	 * Massage & Bodywork page does not need "Massage" offered back.
	 */
	$oria_self = array_map(
		static fn( string $part ): string => trim( strtolower( $part ) ),
		preg_split( '/[&\/,]/', $oria_pname ) ?: array()
	);
	$oria_style_links = array();
	foreach ( $oria_rows as $oria_r ) {
		if ( (int) $oria_r['count'] < 3 ) {
			continue;
		}
		if ( in_array( trim( strtolower( (string) $oria_r['label'] ) ), $oria_self, true ) ) {
			continue;
		}
		$oria_style_links[] = array(
			$oria_row_url( $oria_r ),
			(string) $oria_r['label'],
			(int) $oria_r['count'],
			(string) ( $oria_r['note'] ?? '' ),
		);
	}
	usort( $oria_style_links, static fn( array $a, array $b ): int => $b[2] <=> $a[2] );

	get_template_part(
		'template-parts/explore-tabs',
		null,
		array(
			'styles'  => $oria_style_links,
			'regions' => $oria_region_links,
			'suburbs' => $oria_sub_links,
			/* translators: %s: category name, lower case */
			'heading' => sprintf( __( 'Explore %s', 'oria' ), strtolower( $oria_psent ) ),
			'what'    => strtolower( $oria_psent ),
			'id'      => 'xcExplore',
			'event'   => 'category_quick_filter_select',
		)
	);
	?>
</section>
<?php endif; ?>

<?php
/*
 * 9. Products, then apps (template-parts/support-bands.php): each left out
 * when nothing genuinely fits the category. Skipped on a suburb page.
 */
if ( ! $oria_area && $oria_term instanceof WP_Term ) {
	get_template_part( 'template-parts/support', 'bands', array( 'term' => $oria_term ) );
}
?>

<?php
get_footer();
