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
 * v4 category redesign (the 19 Sept 2026 brief, sections 1-4): the listings
 * come first again. Order: compact hero (four facts, three actions, the
 * detail in "About these results") -> "Choose your path" (named groups of
 * this page's own rows, from $oria_path_map, base view only) -> toolbar,
 * count and the Featured band -> every listing, with the Best Of guides
 * dropped in after the eighth (v4-category.js keeps them there through
 * app.js re-renders) -> guide, What's on, reading, FAQs, areas, products
 * and apps. Hooks app.js reads (#dirResults and its data-*, #dirFilters,
 * #dirCount, #featBand, [data-view], [data-open-map], [data-hero-near],
 * [data-best-toggle], #results) are unchanged.
 */

declare(strict_types=1);

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
 * Worked out before anything is drawn: the section menu, the hero's
 * "Explore by experience" and the Browse floor all need to know what the
 * page actually has.
 */
$oria_base = $oria_term && ! $oria_facet && ! $oria_area;

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
 * Specialists and the places that also offer it, over this page's whole
 * set -- Featured included, so the two add up to the count above the list.
 * v4-category.js recounts the same split from the payload as filters change.
 */
$oria_primary = array();
$oria_spec_n  = 0;
foreach ( $oria_ids as $oria_pid ) {
	$oria_primary[ (int) $oria_pid ] = function_exists( '\Oria\Core\Primary\of' ) ? (string) \Oria\Core\Primary\of( (int) $oria_pid ) : '';
	if ( in_array( $oria_primary[ (int) $oria_pid ], $oria_family, true ) ) {
		++$oria_spec_n;
	}
}
$oria_also_n = count( $oria_ids ) - $oria_spec_n;

/*
 * "Choose your path": a category's rows, grouped under two named ways in.
 * Each group lists row keys (Intents\row_key: "svc:ice-bath", "page:yin")
 * and is drawn only with the links this page really has -- a row with a
 * live count, or a service at least three listings here carry. Nothing is
 * promised that the page cannot show; a group left with fewer than two
 * links is dropped. Categories with no entry get one "Ways to begin" group
 * of their largest rows. Base view only: a facet or suburb page has made
 * its choice already.
 */
$oria_path_map = array(
	'spa'      => array(
		array(
			'name'  => __( 'Relax & indulge', 'oria' ),
			'line'  => __( 'Unhurried treatments, warm water and quiet rooms.', 'oria' ),
			'items' => array( 'svc:massage', 'svc:facials', 'svc:float-therapy', 'svc:relaxation-massage', 'svc:steam-room' ),
		),
		array(
			'name'  => __( 'Recover & recharge', 'oria' ),
			'line'  => __( 'Heat, cold and the studios built around them.', 'oria' ),
			'items' => array( 'svc:infrared-sauna', 'svc:traditional-sauna', 'svc:ice-bath', 'svc:contrast-therapy', 'svc:red-light-therapy', 'svc:compression-therapy', 'svc:cryotherapy' ),
		),
	),
	'yoga'     => array(
		array(
			'name'  => __( 'Slow & restful', 'oria' ),
			'line'  => __( 'Longer holds, a softer pace, some of it lying down.', 'oria' ),
			'items' => array( 'page:yin', 'page:restorative', 'page:nidra', 'page:pregnancy' ),
		),
		array(
			'name'  => __( 'Strong & flowing', 'oria' ),
			'line'  => __( 'Faster sequences and more physical classes.', 'oria' ),
			'items' => array( 'page:vinyasa', 'page:ashtanga', 'page:hot-room' ),
		),
	),
	'bodywork' => array(
		array(
			'name'  => __( 'Relaxing massage', 'oria' ),
			'line'  => __( 'Unhurried hands-on treatments.', 'oria' ),
			'items' => array( 'svc:relaxation-massage', 'svc:massage', 'svc:swedish-massage', 'svc:pregnancy-massage', 'svc:reflexology', 'svc:lymphatic-drainage' ),
		),
		array(
			'name'  => __( 'Remedial & sports', 'oria' ),
			'line'  => __( 'Firmer, more targeted hands-on work.', 'oria' ),
			'items' => array( 'svc:remedial-massage', 'svc:sports-massage', 'svc:deep-tissue-massage', 'svc:dry-needling', 'svc:myofascial-release', 'svc:cupping' ),
		),
	),
);

$oria_paths = array();
if ( $oria_base && $oria_rows && function_exists( '\Oria\Core\Intents\row_key' ) ) {
	$oria_rows_by_key = array();
	foreach ( $oria_rows as $oria_r ) {
		$oria_rows_by_key[ \Oria\Core\Intents\row_key( $oria_r ) ] = $oria_r;
	}
	$oria_groups = (array) ( $oria_path_map[ $oria_term->slug ] ?? array() );

	// Services counted over this page's set, only when a group asks for one
	// the category's own rows leave out (massage is filed under bodywork, so
	// the spa rows never list it, though 31 spa listings offer it).
	$oria_svc_count = array();
	if ( $oria_groups ) {
		foreach ( $oria_ids as $oria_sid ) {
			foreach ( \Oria\Theme\oria_terms_of( (int) $oria_sid, 'service' ) as $oria_st ) {
				if ( ! isset( $oria_svc_count[ $oria_st->slug ] ) ) {
					$oria_svc_count[ $oria_st->slug ] = array( 'name' => \Oria\Theme\tname( $oria_st ), 'n' => 0 );
				}
				++$oria_svc_count[ $oria_st->slug ]['n'];
			}
		}
	}

	$oria_resolve = static function ( string $key ) use ( $oria_rows_by_key, $oria_svc_count, $oria_here, $oria_row_url ): ?array {
		if ( isset( $oria_rows_by_key[ $key ] ) ) {
			$r = $oria_rows_by_key[ $key ];
			return (int) $r['count'] >= 3
				? array( 'key' => $key, 'label' => (string) $r['label'], 'count' => (int) $r['count'], 'url' => $oria_row_url( $r ) )
				: null;
		}
		if ( 0 === strpos( $key, 'svc:' ) ) {
			$slug = substr( $key, 4 );
			$n    = (int) ( $oria_svc_count[ $slug ]['n'] ?? 0 );
			if ( $n < 3 ) {
				return null;
			}
			return array(
				'key'   => $key,
				'label' => (string) $oria_svc_count[ $slug ]['name'],
				'count' => $n,
				// The same builder the rows use: a clean facet address where
				// there is one, this page filtered where there is not.
				'url'   => $oria_row_url( array( 'url' => $oria_here . '?svc=' . rawurlencode( $slug ) . '#dirResults' ) ),
			);
		}
		return null;
	};

	// One atmospheric picture per group: the first of its links the theme
	// ships a facet photograph for (generic scenes, never a named venue).
	$oria_group_pic = static function ( array $links ) use ( $oria_term ): string {
		foreach ( $links as $l ) {
			$v = (string) substr( $l['key'], (int) strpos( $l['key'], ':' ) + 1 );
			foreach ( array( $v, sanitize_title( $l['label'] ), $v . '-' . $oria_term->slug ) as $try ) {
				$img = '' !== $try ? \Oria\Theme\facet_image( $try ) : '';
				if ( '' !== $img ) {
					return $img;
				}
			}
		}
		return '';
	};

	foreach ( $oria_groups as $oria_g ) {
		$oria_links = array();
		foreach ( $oria_g['items'] as $oria_k ) {
			$oria_l = $oria_resolve( (string) $oria_k );
			if ( $oria_l ) {
				$oria_links[] = $oria_l;
			}
			if ( count( $oria_links ) >= 5 ) {
				break;
			}
		}
		if ( count( $oria_links ) >= 2 ) {
			$oria_paths[] = array( 'name' => $oria_g['name'], 'line' => $oria_g['line'], 'links' => $oria_links, 'img' => $oria_group_pic( $oria_links ) );
		}
	}

	if ( ! $oria_paths ) {
		// No named groups: the category's largest rows, as one way in.
		$oria_ways = $oria_rows;
		usort( $oria_ways, static fn( array $a, array $b ): int => (int) $b['count'] <=> (int) $a['count'] );
		$oria_links = array();
		foreach ( $oria_ways as $oria_w ) {
			$oria_l = $oria_resolve( \Oria\Core\Intents\row_key( $oria_w ) );
			if ( $oria_l ) {
				$oria_links[] = $oria_l;
			}
			if ( count( $oria_links ) >= 5 ) {
				break;
			}
		}
		if ( count( $oria_links ) >= 3 ) {
			$oria_paths[] = array(
				'name'  => __( 'Ways to begin', 'oria' ),
				/* translators: %s: category name, lower case */
				'line'  => sprintf( __( 'The kinds of %s listed most here.', 'oria' ), strtolower( $oria_pname ) ),
				'links' => $oria_links,
				'img'   => $oria_group_pic( $oria_links ),
			);
		}
	}
}

/*
 * Quick filters: the category's own choices -- styles, formats, who it
 * suits -- with live counts, each a real filtered view at a clean address.
 * On the base view "Choose your path" already offers them, so only the
 * facet and suburb views keep the chip row ("Or another kind"). The
 * "Oria's picks" toggle stays either way.
 */
$oria_chips = array();
if ( $oria_term && ! $oria_paths ) {
	/*
	 * An admin's chosen quick filters, in their order (the category's
	 * "Quick filters" field), when there are any. Each is matched to a row
	 * the page can offer right now -- so its count is live, and one that has
	 * dropped below the listing floor is simply left out. Otherwise the
	 * automatic set, as before.
	 */
	$oria_qrows  = $oria_rows;
	$oria_chosen = function_exists( 'get_field' ) ? get_field( 'quick_filters', 'practice_' . $oria_term->term_id ) : null;
	if ( is_array( $oria_chosen ) && $oria_chosen && function_exists( '\Oria\Core\Intents\row_key' ) ) {
		$oria_bykey = array();
		foreach ( $oria_rows as $oria_r ) {
			$oria_bykey[ \Oria\Core\Intents\row_key( $oria_r ) ] = $oria_r;
		}
		$oria_pick = array();
		foreach ( $oria_chosen as $oria_q ) {
			$oria_k = (string) ( $oria_q['row'] ?? '' );
			if ( isset( $oria_bykey[ $oria_k ] ) ) {
				$oria_pick[] = $oria_bykey[ $oria_k ];
			}
		}
		if ( $oria_pick ) {
			$oria_qrows = $oria_pick;
		}
	}
	if ( count( $oria_qrows ) >= 2 ) {
		if ( $oria_facet ) {
			/* translators: %s: category name */
			$oria_chips[] = array( $oria_here, sprintf( __( 'All %s', 'oria' ), strtolower( $oria_pname ) ), count( $oria_all ), false );
		}
		$oria_specpage = null;
		if ( $oria_facet && in_array( $oria_facet['key'] ?? '', array( 'svc', 'spec' ), true ) ) {
			$oria_st = get_term_by( 'slug', (string) $oria_facet['slug'], 'specialty' );
			if ( ! $oria_st instanceof WP_Term ) {
				$oria_st = get_term_by( 'slug', (string) $oria_facet['value'], 'specialty' );
			}
			// Only when it genuinely holds more than this page does --
			// otherwise the link promises a wider view and delivers this one.
			if ( $oria_st instanceof WP_Term && (int) $oria_st->count > count( $oria_ids ) ) {
				$oria_specpage = $oria_st;
			}
		}
		if ( $oria_specpage ) {
			/* translators: 1: specialty, 2: city */
			$oria_chips[] = array( \Oria\Core\PracticesIndex\specialty_url( $oria_specpage ), sprintf( __( 'All %1$s in %2$s', 'oria' ), strtolower( \Oria\Theme\tname( $oria_specpage ) ), $oria_cname ), (int) $oria_specpage->count, false );
		}
		foreach ( $oria_qrows as $oria_row ) {
			$oria_href    = $oria_row_url( $oria_row );
			$oria_on      = '' !== $oria_facet_href && untrailingslashit( $oria_href ) === untrailingslashit( $oria_facet_href );
			$oria_chips[] = array( $oria_href, (string) $oria_row['label'], (int) $oria_row['count'], $oria_on );
		}
	}
}
$oria_chip = static function ( array $c ): void {
	?>
	<a class="quickf__chip<?php echo $c[3] ? ' is-current' : ''; ?>" href="<?php echo esc_url( $c[0] ); ?>" data-oria-event="category_quick_filter_select"<?php echo $c[3] ? ' aria-current="page"' : ''; ?>>
		<?php echo esc_html( $c[1] ); ?> <b><?php echo esc_html( number_format_i18n( $c[2] ) ); ?></b>
	</a>
	<?php
};

// Where "Experiences" (menu) and "Explore by experience" (hero) lead.
$oria_exp_anchor = $oria_paths ? '#paths' : ( $oria_chips ? '#quickf' : '' );
?>

<?php if ( $oria_term ) : ?>
<nav class="spine" aria-label="<?php esc_attr_e( 'Page sections', 'oria' ); ?>">
	<div class="wrap spine__row">
		<?php
		/*
		 * Five short stops at most, in the order the page runs, and only the
		 * ones this page has (the brief's Overview · Experiences · Places ·
		 * Guide · FAQs). Every target clears the sticky header and this menu
		 * with scroll-margin-top (v4-category.css).
		 */
		$oria_floors = array( array( '#decide', __( 'Overview', 'oria' ) ) );
		if ( '' !== $oria_exp_anchor ) {
			$oria_floors[] = array( $oria_exp_anchor, __( 'Experiences', 'oria' ) );
		}
		$oria_floors[] = array( '#browse', __( 'Places', 'oria' ) );
		$oria_floors[] = array( '#read', __( 'Guide', 'oria' ) );
		if ( $oria_faqs ) {
			$oria_floors[] = array( '#faq', __( 'FAQs', 'oria' ) );
		}
		foreach ( $oria_floors as $oria_f ) :
			?>
			<a href="<?php echo esc_attr( $oria_f[0] ); ?>"><?php echo esc_html( $oria_f[1] ); ?></a>
		<?php endforeach; ?>
	</div>
</nav>

<?php
/*
 * The category's own picture beside the words (Theme\category_hero_url),
 * decoration only: the empty alt keeps screen readers on the heading.
 */
$oria_hero_img = ( $oria_term && function_exists( '\Oria\Theme\category_hero_url' ) ) ? \Oria\Theme\category_hero_url( $oria_term ) : '';
?>
<!-- Overview: a compact hero -- eyebrow, H1, one line, four facts, three actions -->
<section class="wrap pagehead floor xc-hero<?php echo '' !== $oria_hero_img ? '' : ' xc-hero--bare'; ?>" id="decide">
<div class="xc-hero__text">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<?php // Home / Explore / {City} / {Category} — the address, said back. ?>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/explore/' ) ); ?>"><?php esc_html_e( 'Explore', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( \Oria\Core\Explore\base_url( $oria_city ) ); ?>"><?php echo esc_html( $oria_cname ); ?></a>
		<span aria-hidden="true">/</span>
		<?php if ( $oria_facet ) : ?>
			<a href="<?php echo esc_url( $oria_here ); ?>"><?php echo esc_html( $oria_pname ); ?></a>
			<?php
			// The label carries the city; the crumb already named it, so peel
			// it back off using whatever label_city() wrote rather than a
			// literal that only ever matched Perth.
			$oria_fl = (string) ( $oria_facet['page']['label'] ?? '' );
			/*
			 * An area facet's label is the whole sentence -- "Spa & Recovery
			 * in Perth CBD" -- and the category crumb sitting right beside it
			 * already said the first half. Name the area.
			 */
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

	<?php
	/*
	 * "Near you" suburbs, for the map's drill-down pills. Only suburbs that
	 * clear the facet gate (three or more) get a link, so every pill lands
	 * on a page the sitemap also stands behind. Base view only.
	 */
	$oria_near = array();
	if ( $oria_base ) {
		foreach ( $oria_all as $oria_nid ) {
			foreach ( \Oria\Theme\oria_terms_of( (int) $oria_nid, 'area' ) as $oria_na ) {
				if ( $oria_na->parent ) {
					if ( ! isset( $oria_near[ $oria_na->slug ] ) ) {
						$oria_near[ $oria_na->slug ] = array( 'name' => $oria_na->name, 'n' => 0 );
					}
					++$oria_near[ $oria_na->slug ]['n'];
				}
			}
		}
		$oria_near = array_filter( $oria_near, static fn( array $oria_r ): bool => $oria_r['n'] >= 3 );
		uasort( $oria_near, static fn( array $oria_a, array $oria_b ): int => $oria_b['n'] <=> $oria_a['n'] );
		$oria_near = array_slice( $oria_near, 0, 12, true );
	}

	/*
	 * The map's pins, built here because the hero's "View map" needs to know
	 * whether there is one. The map itself is drawn behind the List | Map
	 * switch and only starts when somebody asks for it.
	 */
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

	/*
	 * One supporting line under the H1, from copy that already exists: the
	 * facet frame's opener, else the category's tagline (its parent's for a
	 * sub-category), else its description. Only when there is none does a
	 * generic line stand in -- nothing is written per category here.
	 */
	$oria_tag = '';
	if ( $oria_base && function_exists( '\Oria\Core\Categories\tagline_for' ) ) {
		$oria_tag = \Oria\Core\Categories\tagline_for( (string) $oria_term->slug );
		if ( '' === $oria_tag && $oria_term->parent ) {
			$oria_parent = get_term( $oria_term->parent, 'practice' );
			if ( $oria_parent instanceof WP_Term ) {
				$oria_tag = \Oria\Core\Categories\tagline_for( (string) $oria_parent->slug );
			}
		}
	}
	if ( $oria_facet && ! empty( $oria_frame['opener'] ) ) {
		$oria_line = $oria_fill( (string) $oria_frame['opener'] );
	} elseif ( '' !== $oria_tag ) {
		$oria_line = $oria_tag;
	} elseif ( $oria_base && '' !== trim( (string) $oria_term->description ) ) {
		$oria_line = (string) $oria_term->description;
	} else {
		/* translators: %s: place name (a city or a suburb) */
		$oria_line = sprintf( __( 'Hand-checked places in %s, with prices and reviews where they are published.', 'oria' ), $oria_area ? \Oria\Theme\tname( $oria_area ) : $oria_cname );
	}

	/*
	 * Four facts at most, each counted live from the listings on this page;
	 * one with nothing to say is left out. They replace the paragraph of
	 * figures, which now sits in "About these results".
	 */
	$oria_n     = count( $oria_ids );
	$oria_facts = array();
	/* translators: %s: number of places */
	$oria_facts[] = sprintf( _n( '%s hand-checked place', '%s hand-checked places', $oria_n, 'oria' ), number_format_i18n( $oria_n ) );
	if ( count( $oria_suburbs ) > 1 ) {
		$oria_facts[] = $oria_area
			/* translators: %s: number of suburbs */
			? sprintf( __( 'Across %s suburbs', 'oria' ), number_format_i18n( count( $oria_suburbs ) ) )
			/* translators: 1: number of suburbs, 2: city */
			: sprintf( __( 'Across %1$s %2$s suburbs', 'oria' ), number_format_i18n( count( $oria_suburbs ) ), $oria_cname );
	}
	$oria_band_words = array(
		'Free' => __( 'free or by donation', 'oria' ),
		'$'    => __( 'under $25', 'oria' ),
		'$$'   => __( '$25–60', 'oria' ),
		'$$$'  => __( '$60–200', 'oria' ),
		'$$$$' => __( '$200 and over', 'oria' ),
	);
	if ( $oria_price > 0 ) {
		/* translators: %s: median published starting price */
		$oria_facts[] = sprintf( __( 'Typical published price: $%s', 'oria' ), number_format_i18n( round( $oria_price ) ) );
	} elseif ( isset( $oria_band_words[ $oria_typical ] ) ) {
		/* translators: %s: the most common price band, in words */
		$oria_facts[] = sprintf( __( 'Mostly %s', 'oria' ), $oria_band_words[ $oria_typical ] );
	}
	$oria_updated    = '' !== (string) ( $oria_answer['updated'] ?? '' ) ? (string) $oria_answer['updated'] : date_i18n( 'j F Y' );
	$oria_updated_ts = strtotime( $oria_updated );
	/* translators: %s: day and month the figures were last updated */
	$oria_facts[] = sprintf( __( 'Updated %s', 'oria' ), $oria_updated_ts ? date_i18n( 'j F', $oria_updated_ts ) : $oria_updated );
	$oria_facts   = array_slice( $oria_facts, 0, 4 );

	/*
	 * Sentence case for the category's own heading ("Spa & recovery in
	 * Perth"): the category names are title-cased labels, and none of them
	 * holds a proper noun. Facet labels and query headings arrive in sentence
	 * case already and are left alone.
	 */
	$oria_sentence = static function ( string $s ): string {
		return '' === $s ? $s : strtoupper( substr( $s, 0, 1 ) ) . strtolower( substr( $s, 1 ) );
	};
	$oria_h1_shown = $oria_h1;
	if ( ! $oria_facet && $oria_term ) {
		$oria_default_h1 = $oria_area
			? sprintf( __( '%1$s in %2$s', 'oria' ), $oria_pname, \Oria\Theme\tname( $oria_area ) )
			: sprintf( __( '%1$s in %2$s', 'oria' ), $oria_pname, $oria_cname );
		if ( $oria_h1 === $oria_default_h1 ) {
			$oria_h1_shown = $oria_sentence( $oria_pname ) . substr( $oria_h1, strlen( $oria_pname ) );
		}
	}

	$oria_aud = ( $oria_facet && function_exists( '\Oria\Core\IntentPages\audience_note' ) && ! empty( $oria_facet['page'] ) )
		? \Oria\Core\IntentPages\audience_note( $oria_facet['page'], array( 'ids' => $oria_ids ) )
		: null;
	?>
	<div class="cathero xc-hero__body">
		<div class="decide__head">
			<p class="micro xc-hero__eyebrow">
				<?php
				if ( $oria_facet ) {
					echo esc_html( $oria_pname ) . ' · ' . esc_html__( 'Filtered view', 'oria' );
				} else {
					/* translators: %s: city name */
					printf( esc_html__( 'Explore %s', 'oria' ), esc_html( $oria_cname ) );
				}
				?>
			</p>
			<h1 class="h1 pagehead__title"><?php echo esc_html( $oria_h1_shown ); ?></h1>
			<p class="pagehead__tag xc-hero__line"><?php echo esc_html( $oria_line ); ?></p>
		</div>

		<ul class="xc-facts" aria-label="<?php esc_attr_e( 'At a glance', 'oria' ); ?>">
			<?php foreach ( $oria_facts as $oria_st ) : ?>
				<li><?php echo esc_html( $oria_st ); ?></li>
			<?php endforeach; ?>
		</ul>

		<div class="catactions xc-hero__acts">
			<?php if ( '' !== $oria_exp_anchor ) : ?>
				<a class="btn btn--sm xc-btn-primary" href="<?php echo esc_attr( $oria_exp_anchor ); ?>" data-oria-event="category_explore_paths"><?php esc_html_e( 'Explore by experience', 'oria' ); ?></a>
			<?php endif; ?>
			<button type="button" class="btn btn--ghost btn--sm" data-hero-near><?php esc_html_e( 'Find near me', 'oria' ); ?></button>
			<?php if ( $oria_map ) : ?>
				<button type="button" class="btn btn--sm xc-btn-quiet" data-open-map><?php esc_html_e( 'View map', 'oria' ); ?></button>
			<?php endif; ?>
		</div>

		<?php
		/*
		 * The detail behind the facts, one tap away rather than in the way.
		 * It stays in the page's HTML either way, so the quotable sentences
		 * answer engines lift are still there for them.
		 */
		?>
		<details class="catabout">
			<summary><?php esc_html_e( 'About these results', 'oria' ); ?></summary>
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
						if ( $oria_area ) {
							printf(
								/* translators: 1: month year, 2: count, 3: total, 4: category, 5: suburb */
								esc_html__( 'As of %1$s, %2$s of the %3$s %4$s listings on Oria Haven are in %5$s — counted live from the directory.', 'oria' ),
								esc_html( date_i18n( 'F Y' ) ),
								esc_html( number_format_i18n( $oria_n ) ),
								esc_html( number_format_i18n( count( $oria_all ) ) ),
								esc_html( $oria_pname ),
								esc_html( \Oria\Theme\tname( $oria_area ) )
							);
						} else {
							printf(
								/* translators: 1: month year, 2: count, 3: total, 4: category */
								esc_html__( 'As of %1$s, %2$s of the %3$s %4$s listings on Oria Haven match this page — counted live from the directory.', 'oria' ),
								esc_html( date_i18n( 'F Y' ) ),
								esc_html( number_format_i18n( $oria_n ) ),
								esc_html( number_format_i18n( count( $oria_all ) ) ),
								esc_html( $oria_pname )
							);
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
				<p class="hint">
					<?php esc_html_e( 'Most relevant means practices that specialise in this first, then the ones with the most reviews to go on. A listing that publishes its price, describes itself and names its services gets a small nudge, never more than a quarter of a star, because you can decide on it without ringing. Paid placements are shown once, in their own band, and never move anyone up the list.', 'oria' ); ?>
				</p>
			</div>
		</details>
	</div>
</div>
<?php if ( '' !== $oria_hero_img ) : ?>
	<?php // Decoration: the empty alt keeps screen readers on the heading. The files are 1600x900. ?>
	<figure class="xc-hero__pic"><img src="<?php echo esc_url( $oria_hero_img ); ?>" alt="" width="1600" height="900" fetchpriority="high" decoding="async"></figure>
<?php endif; ?>
</section>

<?php if ( $oria_paths ) : ?>
	<!-- Experiences: the category's rows, grouped into named ways in -->
	<section class="wrap xc-paths" id="paths" aria-labelledby="xc-paths-title">
		<h2 class="h3 xc-paths__title" id="xc-paths-title"><?php esc_html_e( 'Choose your path', 'oria' ); ?></h2>
		<div class="xc-paths__grid xc-paths__grid--<?php echo (int) count( $oria_paths ); ?>">
			<?php foreach ( $oria_paths as $oria_pi => $oria_p ) : ?>
				<article class="xc-path" aria-labelledby="xc-path-<?php echo (int) $oria_pi; ?>">
					<div class="xc-path__pic<?php echo '' === $oria_p['img'] ? ' xc-path__pic--bare' : ''; ?>">
						<?php if ( '' !== $oria_p['img'] ) : ?>
							<img src="<?php echo esc_url( $oria_p['img'] ); ?>" alt="" loading="lazy" decoding="async" width="480" height="360">
						<?php endif; ?>
					</div>
					<div class="xc-path__body">
						<h3 class="h4 xc-path__name" id="xc-path-<?php echo (int) $oria_pi; ?>"><?php echo esc_html( $oria_p['name'] ); ?></h3>
						<p class="xc-path__line"><?php echo esc_html( $oria_p['line'] ); ?></p>
						<ul class="xc-path__links">
							<?php foreach ( $oria_p['links'] as $oria_l ) : ?>
								<li>
									<a href="<?php echo esc_url( $oria_l['url'] ); ?>" data-oria-event="category_quick_filter_select">
										<span class="xc-path__label"><?php echo esc_html( $oria_l['label'] ); ?></span>
										<span class="xc-path__n">
											<?php
											/* translators: %s: number of places */
											printf( esc_html( _n( '%s place', '%s places', (int) $oria_l['count'], 'oria' ) ), esc_html( number_format_i18n( (int) $oria_l['count'] ) ) );
											?>
										</span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<!-- Places: the toolbar, the count, Featured, then every listing -->
<section class="wrap section section--top-flush floor xc-browse" id="browse">
	<?php if ( $oria_chips || ( $oria_bo['slugs'] && ! $oria_paths ) ) : ?>
		<nav class="quickf" id="quickf" aria-label="<?php esc_attr_e( 'Quick filters', 'oria' ); ?>">
			<p class="quickf__label">
				<span class="micro"><?php echo $oria_facet ? esc_html__( 'Or another kind', 'oria' ) : esc_html__( 'Narrow it down', 'oria' ); ?></span>
				<span class="hint"><?php esc_html_e( 'Each is a filtered view — it counts, it never ranks.', 'oria' ); ?></span>
			</p>
			<div class="quickf__row">
				<?php
				/*
				 * "Oria's picks": the places the Best Of guides shortlisted,
				 * filtered in the list below rather than a page of its own --
				 * a button, because it changes this list and goes nowhere.
				 */
				?>
				<?php if ( $oria_bo['slugs'] ) : ?>
					<button type="button" class="quickf__chip quickf__chip--best" data-best-toggle aria-pressed="false">
						<span class="badge--best__mark" aria-hidden="true">&#10022;</span>
						<?php esc_html_e( 'Oria’s picks', 'oria' ); ?> <b><?php echo esc_html( number_format_i18n( count( $oria_bo['slugs'] ) ) ); ?></b>
					</button>
				<?php endif; ?>
				<?php foreach ( array_slice( $oria_chips, 0, 6 ) as $oria_c ) { $oria_chip( $oria_c ); } ?>
			</div>
			<?php if ( count( $oria_chips ) > 6 ) : ?>
				<details class="quickf__more">
					<summary>
						<?php
						/* translators: %d: how many more quick filters */
						printf( esc_html__( 'See all options (%d more)', 'oria' ), count( $oria_chips ) - 6 );
						?>
					</summary>
					<div class="quickf__row">
						<?php foreach ( array_slice( $oria_chips, 6 ) as $oria_c ) { $oria_chip( $oria_c ); } ?>
					</div>
				</details>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
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
		<p class="dir__count" id="dirCount" role="status" aria-live="polite"></p>
		<?php if ( $oria_paths && $oria_bo['slugs'] ) : ?>
			<?php // The picks toggle, here once "Choose your path" has replaced the chip row. ?>
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
	<?php
	/*
	 * Why the count is the size it is: the specialists, and the places that
	 * also offer this. Server-drawn for the page as it arrives; the script
	 * recounts it for every filter and hides it when either side is empty.
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
	<div class="chips" id="dirChips"></div>
	<?php
	/*
	 * Featured practices: paid placements, shown ONCE, in their own labelled
	 * band above the list -- and taken out of the list while the band shows,
	 * so nobody appears twice or is counted twice (app.js). The moment the
	 * visitor filters or re-sorts, the band steps aside and these listings
	 * sit in the list like anyone else. Only practices whose primary
	 * category is this one (or one of its own sub-categories) qualify: a
	 * yoga studio that pays does not get to headline the massage page.
	 * Up to three, in an order that rotates daily so nobody owns the top.
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
	$oria_featured = array_slice( $oria_featured, 0, 3 );
	?>
	<?php if ( $oria_featured ) : ?>
		<section class="featcat xc-feat xc-feat--<?php echo (int) count( $oria_featured ); ?>" id="featBand" aria-labelledby="featBandHead"
			data-ids="<?php echo esc_attr( implode( ',', array_map( static fn( int $i ): string => (string) get_post_field( 'post_name', $i ), $oria_featured ) ) ); ?>">
			<div class="featcat__head">
				<h2 class="h4" id="featBandHead">
					<?php
					/* translators: %s: category name, lower case */
					printf( esc_html__( 'Featured %s', 'oria' ), esc_html( strtolower( $oria_pname ) ) );
					?>
				</h2>
				<details class="xc-why">
					<summary><?php esc_html_e( 'Why featured?', 'oria' ); ?></summary>
					<div class="xc-why__body">
						<p>
							<?php
							printf(
								/* translators: %s: category name, lower case */
								esc_html__( 'These are Oria Haven members who pay for this spot. Only places whose main focus is %s can appear here, three at most, in an order that changes every day. Being featured never changes anyone’s position in the list below.', 'oria' ),
								esc_html( strtolower( $oria_pname ) )
							);
							?>
						</p>
						<a class="featcat__how" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'How featuring works', 'oria' ); ?></a>
					</div>
				</details>
			</div>
			<p class="featcat__note"><?php esc_html_e( 'Paid placements from Oria Haven members. They appear here once, marked Featured, and never move anyone up the list below.', 'oria' ); ?></p>
			<div class="dir__results dir__results--wide featcat__grid">
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
		<?php // The city this page was scoped to, so the script keeps that scope. ?>
		<?php if ( ! empty( $oria_city['slug'] ) ) : ?>
			data-city="<?php echo esc_attr( (string) $oria_city['slug'] ); ?>"
		<?php endif; ?>
		<?php
		/*
		 * Lock the client at the level the term actually sits at. It used to
		 * emit data-suburb for any locked area, which an exact suburb match
		 * cannot honour above suburb level: /practices/spa/margaret-river/
		 * rendered seven listings and the script cut them to the two whose
		 * suburb is literally "Margaret River". A city is already locked by
		 * data-city above, so it needs nothing more here.
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
			// Specialists first, then A to Z -- the same rule the script
			// sorts by, and never payment (app.js relevance()).
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
		 * the set above is nothing like the main query, and the schema was
		 * advertising ten Perth listings on the Margaret River page.
		 */
		if ( function_exists( '\Oria\Core\Schema\register_list' ) ) {
			\Oria\Core\Schema\register_list( $oria_shown );
		}
		?>
	</div>

	<?php
	/*
	 * The one editorial interruption: up to three of this category's Best Of
	 * guides, as picture cards with the guide's own opening line. It replaces
	 * the long shelf of four picks that used to sit above the listings.
	 *
	 * Drawn here, after the list, so the page is whole without scripting and
	 * crawlers see real links. v4-category.js moves it in after the eighth
	 * listing on the first page, and puts it back after every re-render --
	 * app.js rebuilds #dirResults with innerHTML on each filter, sort or page,
	 * which would otherwise throw it away.
	 */
	$oria_bo_cards = array_slice( (array) $oria_bo['guides'], 0, 3 );
	?>
	<?php if ( $oria_bo_cards && function_exists( '\Oria\Core\BestOf\intro' ) ) : ?>
		<aside class="xc-bo" id="xcBestOf" aria-labelledby="xcBestOfTitle">
			<div class="xc-bo__head">
				<p class="micro xc-bo__eyebrow"><span class="badge--best__mark" aria-hidden="true">&#10022;</span> <?php esc_html_e( 'From our Best Of guides', 'oria' ); ?></p>
				<h3 class="h4 xc-bo__title" id="xcBestOfTitle"><?php esc_html_e( 'Shortlisted by our editors', 'oria' ); ?></h3>
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
		</aside>
	<?php endif; ?>

	<?php if ( $oria_map ) : ?>
		<?php // Phones: the floating way into the full-screen map. ?>
		<button type="button" class="mapfab" data-view="map" aria-pressed="false"><?php esc_html_e( 'Map', 'oria' ); ?></button>
		<div class="dirmap" id="catMapView" hidden>
			<button type="button" class="dirmap__close" data-view="list" aria-pressed="false"><?php esc_html_e( 'Show list', 'oria' ); ?></button>
			<div class="catmap catmap--view" data-catmap role="img" aria-label="<?php printf( esc_attr__( 'Map of %1$s places across %2$s', 'oria' ), esc_attr( $oria_pname ), esc_attr( $oria_cname ) ); ?>">
				<div class="catmap__tip" hidden></div>
			</div>
			<script type="application/json" data-catmap-data><?php echo wp_json_encode( $oria_map ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON in a data script tag ?></script>
			<?php if ( $oria_near ) : ?>
				<div class="nearyou nearyou--map">
					<h2 class="h4"><?php printf( esc_html__( '%s near you', 'oria' ), esc_html( $oria_pname ) ); ?></h2>
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

	<?php
	/*
	 * The way out for somebody the filters have not served: say it in
	 * their own words to Ask Oria, which retrieves real listings and shows
	 * why each matched. The example names this category and a suburb it
	 * really has listings in -- a place and a time, never a symptom.
	 */
	$oria_ask_sub = '';
	foreach ( (array) $oria_near as $oria_nrow ) {
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
</section>

<?php
/*
 * Everything below supports the listings rather than standing between
 * somebody and them: the guide, what's on, reading, questions, areas,
 * then products and apps.
 */

// Choosing between two practices is decision help, so it opens the guide.
$oria_cmp = function_exists( '\Oria\Core\Compare\prompt_for_term' )
	? \Oria\Core\Compare\prompt_for_term( $oria_term )
	: null;
// A category that owns a within-category group gets the sharper question
// too: not how massage compares to a day spa, but which kind to book.
$oria_gcmp = function_exists( '\Oria\Core\Compare\group_prompt_for_term' )
	? \Oria\Core\Compare\group_prompt_for_term( $oria_term )
	: null;

// The category's introduction, whole: the "Before you go" band that used to
// sit above the listings has come back down into the guide.
$oria_intro_html = ( is_string( $oria_intro ) && '' !== trim( $oria_intro ) && $oria_term )
	? (string) \Oria\Core\PracticesIndex\rewrite_content_links( (string) $oria_intro, $oria_term )
	: '';
?>
<!-- Guide -->
<section class="wrap section floor xc-guide" id="read">
	<p class="micro floor__label"><?php esc_html_e( 'Guide', 'oria' ); ?></p>
	<?php if ( $oria_gcmp ) : ?>
		<p class="cmpnudge cmpnudge--group">
			<a href="<?php echo esc_url( $oria_gcmp['url'] ); ?>" data-oria-event="category_compare_group">
				<?php echo esc_html( $oria_gcmp['label'] ); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</p>
	<?php endif; ?>
	<?php if ( $oria_cmp ) : ?>
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
	<?php elseif ( '' !== $oria_intro_html ) : ?>
		<h2 class="h3" style="margin-bottom:1rem">
			<?php
			/* translators: 1: category, lower case, 2: city */
			printf( esc_html__( 'Before you go: %1$s in %2$s', 'oria' ), esc_html( strtolower( $oria_pname ) ), esc_html( $oria_cname ) );
			?>
		</h2>
		<div class="prose prose--intro"><?php echo wp_kses_post( $oria_intro_html ); ?></div>
	<?php endif; ?>

	<?php
	/*
	 * Trend to Try: one published trend an editor tied to this category, at
	 * the end of the guide. Nothing renders until a trend is published. Left
	 * off suburb pages, like What's on.
	 */
	if ( ! $oria_area && $oria_term && function_exists( '\Oria\Core\Trends\for_practice' ) ) {
		get_template_part( 'template-parts/trend-context', null, array( 'trends' => \Oria\Core\Trends\for_practice( $oria_term ), 'location' => 'category_page' ) );
	}
	?>
</section>

<?php
// What's on: only events tagged with this category or its sub-categories
// (category_events), in this city, not over yet. The child theme's part
// carries the eyebrow + H2 heading.
if ( $oria_events ) {
	get_template_part( 'template-parts/category', 'events', array( 'term' => $oria_term, 'city' => $oria_city, 'rows' => $oria_events ) );
}
?>

<?php
// The guides for this practice, as image cards; the latest from the
// journal where none are tagged to it yet.
get_template_part(
	'template-parts/guides',
	'floor',
	array(
		'guides'  => $oria_guides ?: $oria_latest,
		'heading' => $oria_guides ? sprintf( __( 'Guides to %s worth reading first', 'oria' ), strtolower( $oria_pname ) ) : __( 'From the journal', 'oria' ),
		'icon'    => ( $oria_term && function_exists( '\Oria\Core\Categories\icon' ) ) ? \Oria\Core\Categories\icon( $oria_term->slug ) : '',
		// Supporting reading, below the directory: smaller cards.
		'compact' => true,
	)
);
?>

<?php
/*
 * The FAQ part brings its own section and wrap; it has to sit at the top
 * level, not inside another wrap. On a facet page the questions come from
 * the frame where one exists; a frameless facet page shows none rather than
 * the category's.
 */
if ( $oria_facet ) {
	$oria_filled = array_map( static fn( array $qa ): array => array( 'q' => $oria_fill( $qa['q'] ), 'a' => $oria_fill( $qa['a'] ) ), $oria_faqs );
	if ( $oria_filled ) {
		get_template_part( 'template-parts/faq', null, array( 'faqs' => $oria_filled, 'heading' => sprintf( __( '%s — common questions', 'oria' ), $oria_h1 ), 'id' => 'faq' ) );
	}
} elseif ( $oria_faqs ) {
	get_template_part( 'template-parts/faq', null, array( 'faqs' => $oria_faqs, 'heading' => sprintf( __( 'Questions people ask about %1$s in %2$s', 'oria' ), strtolower( $oria_pname ), $oria_cname ), 'id' => 'faq' ) );
}
?>

<section class="wrap section section--top-flush floor">
	<?php
	// The area mesh: every region and suburb with this category, counted.
	$oria_counts  = \Oria\Theme\combo_counts( $oria_term->slug );
	$oria_regions = \Oria\Core\Taxonomies\regions();
	$oria_regions = is_wp_error( $oria_regions ) ? array() : $oria_regions;

	/*
	 * regions() spans every city, so the Perth page was offering
	 * "Margaret River & South (1)" in its area mesh -- and the count
	 * beside it came from the whole corpus, not from this page.
	 */
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

	$oria_links = array();
	foreach ( $oria_regions as $oria_r ) {
		$oria_rn = (int) ( $oria_counts['regions'][ $oria_r->slug ] ?? 0 );
		if ( $oria_rn > 0 ) {
			$oria_links[] = array( \Oria\Core\PracticesIndex\area_url( $oria_term, $oria_r ), sprintf( '%s (%d)', \Oria\Theme\tname( $oria_r ), $oria_rn ) );
		}
	}
	foreach ( $oria_counts['suburbs'] as $oria_sname => $oria_rn ) {
		$oria_s = get_term_by( 'slug', sanitize_title( $oria_sname ), 'area' );
		if ( $oria_s instanceof WP_Term && 0 !== $oria_s->parent ) {
			$oria_links[] = array( \Oria\Core\PracticesIndex\area_url( $oria_term, $oria_s ), sprintf( '%s (%d)', \Oria\Theme\tname( $oria_s ), $oria_rn ) );
		}
	}
	if ( $oria_links ) :
		?>
		<h2 class="h4 xc-areas__title"><?php printf( esc_html__( '%s by area', 'oria' ), esc_html( $oria_sentence( $oria_pname ) ) ); ?></h2>
		<div class="chips">
			<?php foreach ( $oria_links as $oria_l ) : ?>
				<a class="pill" href="<?php echo esc_url( $oria_l[0] ); ?>"><?php echo esc_html( $oria_l[1] ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
<?php endif; ?>

<?php
/*
 * Products, then apps: two short recommendations at the end of the page,
 * sharing one header and one grid (template-parts/support-bands.php).
 *
 * Skipped on a suburb page: somebody who has narrowed to Fremantle wants a
 * room, not a product or an app. Each section is left out entirely when
 * nothing genuinely fits the category.
 */
if ( ! $oria_area && $oria_term instanceof WP_Term ) {
	get_template_part( 'template-parts/support', 'bands', array( 'term' => $oria_term ) );
}
?>

<?php
get_footer();
