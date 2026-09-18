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
	return 0 === strpos( $url, $old ) ? $oria_here . ltrim( substr( $url, strlen( $old ) ), '/' ) : $url;
};
$oria_facet_href = $oria_facet ? $oria_here . $oria_facet['slug'] . '/' : '';
$oria_fill = static function ( string $s ) use ( $oria_ids, $oria_all, $oria_pname ): string {
	return strtr( $s, array( '{count}' => number_format_i18n( count( $oria_ids ) ), '{total}' => number_format_i18n( count( $oria_all ) ), '{practice}' => strtolower( $oria_pname ) ) );
};
?>

<?php if ( $oria_term ) : ?>
<nav class="spine" aria-label="<?php esc_attr_e( 'Page sections', 'oria' ); ?>">
	<div class="wrap spine__row">
		<?php
		/*
		 * Named, not numbered: the UX audit found "1 Decide 2 Browse" read
		 * as pagination. In the order the page actually runs, and only the
		 * sections this page has.
		 */
		$oria_floors = array(
			array( '#decide', __( 'Overview', 'oria' ) ),
			/* translators: %s: number of listings */
			array( '#browse', sprintf( __( 'Listings (%s)', 'oria' ), number_format_i18n( count( $oria_ids ) ) ) ),
			array( '#read', __( 'Guide', 'oria' ) ),
		);
		if ( $oria_events ) {
			$oria_floors[] = array( '#events', __( "What's on", 'oria' ) );
		}
		if ( $oria_guides || $oria_latest ) {
			$oria_floors[] = array( '#guides', __( 'Reading', 'oria' ) );
		}
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
 * The header picture, in the same .heroband block the guides and Best Of
 * pages use: three photographs dissolving in from the right, decoration
 * only (a background, so screen readers skip it and a phone never
 * downloads it). Category pages keep it to desktop -- dropping it under
 * the header on a phone, as the guides do, would push the listings back
 * down the page the UX redesign pulled them up.
 */
$oria_hero_img = ( $oria_term && function_exists( '\Oria\Theme\category_hero_url' ) ) ? \Oria\Theme\category_hero_url( $oria_term ) : '';
?>
<div class="heroband heroband--stack heroband--cat<?php echo '' !== $oria_hero_img ? '' : ' heroband--bare'; ?>"
	<?php if ( '' !== $oria_hero_img ) : ?>style="--heroband-img:url('<?php echo esc_url( $oria_hero_img ); ?>')"<?php endif; ?>>
<!-- Floor 1 — Decide -->
<section class="wrap pagehead floor" id="decide">
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
	 * "Near you" suburbs, computed before the two-column block so the map
	 * column can render the pills as its drill-down. Only suburbs that
	 * clear the facet gate (three or more) get a link, so every pill lands
	 * on a page the sitemap also stands behind. Base view only.
	 */
	$oria_near = array();
	if ( ! $oria_facet && ! $oria_area && $oria_term ) {
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
	?>
	<?php
	/*
	 * The map, built here because the hero's "Open map" needs to know
	 * whether there is one. It is drawn in the Browse floor now, behind the
	 * List | Map switch -- the UX audit found it filling half the opening
	 * screen before anybody had asked for it.
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
	 * The emotional register, one line under the H1. Base view only:
	 * facet and suburb views are already narrowed to a decision.
	 */
	$oria_tag = '';
	if ( ! $oria_facet && ! $oria_area && function_exists( '\Oria\Core\Categories\tagline_for' ) ) {
		$oria_tag = \Oria\Core\Categories\tagline_for( (string) $oria_term->slug );
		if ( '' === $oria_tag && $oria_term->parent ) {
			$oria_parent = get_term( $oria_term->parent, 'practice' );
			if ( $oria_parent instanceof WP_Term ) {
				$oria_tag = \Oria\Core\Categories\tagline_for( (string) $oria_parent->slug );
			}
		}
	}

	/*
	 * The numbers, as five things you can read at a glance instead of a
	 * paragraph you have to parse. Every one is counted live from the
	 * listings on this page; any that has nothing to say is left out.
	 */
	$oria_stats = array();
	$oria_n     = count( $oria_ids );
	/* translators: %s: number of practices */
	$oria_stats[] = sprintf( _n( '%s practice', '%s practices', $oria_n, 'oria' ), number_format_i18n( $oria_n ) );
	if ( count( $oria_suburbs ) > 1 ) {
		/* translators: %s: number of suburbs */
		$oria_stats[] = sprintf( __( '%s suburbs', 'oria' ), number_format_i18n( count( $oria_suburbs ) ) );
	}
	if ( $oria_prices ) {
		/* translators: %s: lowest published starting price */
		$oria_stats[] = sprintf( __( 'From $%s', 'oria' ), number_format_i18n( (float) min( $oria_prices ) ) );
	}
	if ( $oria_price > 0 ) {
		/* translators: %s: median published starting price */
		$oria_stats[] = sprintf( __( 'Typically $%s', 'oria' ), number_format_i18n( round( $oria_price ) ) );
	} elseif ( '' !== $oria_typical ) {
		/* translators: %s: the most common price band, e.g. $$ */
		$oria_stats[] = sprintf( __( 'Mostly %s', 'oria' ), $oria_typical );
	}
	if ( $oria_free ) {
		/* translators: %s: number of free or by-donation practices */
		$oria_stats[] = sprintf( __( '%s free or by donation', 'oria' ), number_format_i18n( $oria_free ) );
	} elseif ( $oria_online ) {
		/* translators: %s: number of practices offering online sessions */
		$oria_stats[] = sprintf( __( '%s online', 'oria' ), number_format_i18n( $oria_online ) );
	}
	$oria_stats   = array_slice( $oria_stats, 0, 5 );
	$oria_updated = '' !== (string) ( $oria_answer['updated'] ?? '' ) ? (string) $oria_answer['updated'] : date_i18n( 'j F Y' );
	?>
	<div class="cathero">
		<div class="decide__head">
			<span class="micro"><?php echo $oria_facet ? esc_html( $oria_pname ) . ' · ' . esc_html__( 'Filtered view', 'oria' ) : esc_html__( 'Explore', 'oria' ); ?></span>
			<h1 class="h1 pagehead__title"><?php echo esc_html( $oria_h1 ); ?></h1>
			<?php if ( '' !== $oria_tag ) : ?>
				<p class="pagehead__tag"><?php echo esc_html( $oria_tag ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $oria_facet && ! empty( $oria_frame['opener'] ) ) : ?>
			<p class="cathero__lede"><?php echo esc_html( $oria_fill( (string) $oria_frame['opener'] ) ); ?></p>
		<?php elseif ( ! $oria_facet && ! $oria_answer['sentences'] && $oria_term->description ) : ?>
			<p class="cathero__lede"><?php echo esc_html( $oria_term->description ); ?></p>
		<?php endif; ?>

		<ul class="catstats" aria-label="<?php esc_attr_e( 'At a glance', 'oria' ); ?>">
			<?php foreach ( $oria_stats as $oria_st ) : ?>
				<li><?php echo esc_html( $oria_st ); ?></li>
			<?php endforeach; ?>
		</ul>

		<div class="catactions">
			<button type="button" class="btn btn--dark btn--sm" data-hero-near><?php esc_html_e( 'Find places near me', 'oria' ); ?></button>
			<a class="btn btn--ghost btn--sm" href="#results">
				<?php
				/* translators: %s: number of listings */
				printf( esc_html__( 'Browse all %s', 'oria' ), esc_html( number_format_i18n( $oria_n ) ) );
				?>
			</a>
			<?php if ( $oria_map ) : ?>
				<button type="button" class="btn btn--ghost btn--sm" data-open-map><?php esc_html_e( 'Open map', 'oria' ); ?></button>
			<?php endif; ?>
		</div>

		<p class="cathero__trust">
			<?php
			/* translators: %s: date the figures were last updated */
			printf( esc_html__( 'Every listing hand-checked · Updated %s', 'oria' ), esc_html( $oria_updated ) );
			?>
		</p>

		<?php
		/*
		 * The detail behind the numbers, one tap away rather than in the
		 * way. It stays in the page's HTML either way, so the quotable
		 * sentences answer engines lift are still there for them.
		 */
		$oria_aud = ( $oria_facet && function_exists( '\Oria\Core\IntentPages\audience_note' ) && ! empty( $oria_facet['page'] ) )
			? \Oria\Core\IntentPages\audience_note( $oria_facet['page'], array( 'ids' => $oria_ids ) )
			: null;
		?>
		<details class="catabout">
			<summary><?php esc_html_e( 'About these results', 'oria' ); ?></summary>
			<div class="catabout__body">
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
					<?php if ( $oria_ids && $oria_all ) : ?>
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
				<?php elseif ( $oria_answer['sentences'] ) : ?>
					<p><?php echo esc_html( implode( ' ', $oria_answer['sentences'] ) ); ?></p>
				<?php endif; ?>
				<p class="hint">
					<?php esc_html_e( 'Most relevant means practices that specialise in this first, then the ones with the most reviews to go on. A listing that publishes its price, describes itself and names its services gets a small nudge, never more than a quarter of a star, because you can decide on it without ringing. Paid placements are shown once, in their own band, and never move anyone up the list.', 'oria' ); ?>
				</p>
			</div>
		</details>
	</div>

	<?php
	/*
	 * The last thing on the Decide floor, because choosing between two
	 * practices is the question this page cannot answer on its own.
	 * Pre-filled against the counterpart the registry names; categories with
	 * no registry entry still get the bare link, which is the internal link
	 * that makes /compare/ crawlable in the first place.
	 */
	$oria_cmp = function_exists( '\Oria\Core\Compare\prompt_for_term' )
		? \Oria\Core\Compare\prompt_for_term( $oria_term )
		: null;
	?>
	<?php
	/*
	 * A category that owns a within-category group gets the second, sharper
	 * question too: not how massage compares to a day spa, but which kind of
	 * massage to book.
	 */
	$oria_gcmp = function_exists( '\Oria\Core\Compare\group_prompt_for_term' )
		? \Oria\Core\Compare\group_prompt_for_term( $oria_term )
		: null;
	?>
	<?php
	/*
	 * The third scale — comparing actual businesses — used to be a link
	 * here, scoped to this category. Removed: every listing card now
	 * carries its own Compare button, so a shortlist is built from the
	 * cards themselves rather than from a prompt sitting above them.
	 * The scoped view still answers at /compare/?in={slug} for anything
	 * linking to it directly; nothing here points at it any more.
	 */
	?>
	<?php // The compare prompts are drawn at the top of the guide now -- see below. ?>
</section>
</div>

<!-- Floor 2 — Listings -->
<section class="wrap section section--top-flush floor" id="browse">
	<?php
	/*
	 * Quick filters: the category's own choices -- styles, formats, who it
	 * suits -- with live counts, straight under the hero. Each is a real
	 * filtered view at a clean address. Six up front, the rest one tap
	 * away; the UX audit found the full grid pushing the listings down.
	 */
	$oria_chips = array();
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
	$oria_chip = static function ( array $c ): void {
		?>
		<a class="quickf__chip<?php echo $c[3] ? ' is-current' : ''; ?>" href="<?php echo esc_url( $c[0] ); ?>" data-oria-event="category_quick_filter_select"<?php echo $c[3] ? ' aria-current="page"' : ''; ?>>
			<?php echo esc_html( $c[1] ); ?> <b><?php echo esc_html( number_format_i18n( $c[2] ) ); ?></b>
		</a>
		<?php
	};
	?>
	<?php if ( $oria_chips ) : ?>
		<nav class="quickf" aria-label="<?php esc_attr_e( 'Quick filters', 'oria' ); ?>">
			<p class="quickf__label">
				<span class="micro"><?php echo $oria_facet ? esc_html__( 'Or another kind', 'oria' ) : esc_html__( 'Narrow it down', 'oria' ); ?></span>
				<span class="hint"><?php esc_html_e( 'Each is a filtered view — it counts, it never ranks.', 'oria' ); ?></span>
			</p>
			<div class="quickf__row">
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
		<?php if ( $oria_map ) : ?>
			<div class="viewswitch" role="group" aria-label="<?php esc_attr_e( 'Show results as', 'oria' ); ?>">
				<button type="button" class="viewswitch__btn" data-view="list" aria-pressed="true"><?php esc_html_e( 'List', 'oria' ); ?></button>
				<button type="button" class="viewswitch__btn" data-view="map" aria-pressed="false"><?php esc_html_e( 'Map', 'oria' ); ?></button>
			</div>
		<?php endif; ?>
	</div>
	<div class="chips" id="dirChips" style="margin-top:.5rem"></div>
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
	$oria_family = array( $oria_term->slug );
	foreach ( (array) get_term_children( (int) $oria_term->term_id, 'practice' ) as $oria_cid ) {
		$oria_ct = get_term( (int) $oria_cid, 'practice' );
		if ( $oria_ct instanceof WP_Term ) {
			$oria_family[] = $oria_ct->slug;
		}
	}
	$oria_featured = array();
	foreach ( $oria_ids as $oria_fid ) {
		if ( 'featured' !== \Oria\Theme\display_status( (int) $oria_fid ) ) {
			continue;
		}
		$oria_fp = function_exists( '\Oria\Core\Primary\of' ) ? \Oria\Core\Primary\of( (int) $oria_fid ) : '';
		if ( ! in_array( $oria_fp, $oria_family, true ) ) {
			continue;
		}
		$oria_featured[] = (int) $oria_fid;
	}
	$oria_day = current_time( 'Y-m-d' );
	usort( $oria_featured, static fn( int $a, int $b ): int => crc32( $oria_day . $a ) <=> crc32( $oria_day . $b ) );
	$oria_featured = array_slice( $oria_featured, 0, 3 );
	?>
	<?php if ( $oria_featured ) : ?>
		<section class="featcat" id="featBand" aria-labelledby="featBandHead"
			data-ids="<?php echo esc_attr( implode( ',', array_map( static fn( int $i ): string => (string) get_post_field( 'post_name', $i ), $oria_featured ) ) ); ?>">
			<div class="featcat__head">
				<h2 class="h4" id="featBandHead">
					<?php
					/* translators: %s: category name, lower case */
					printf( esc_html__( 'Featured %s', 'oria' ), esc_html( strtolower( $oria_pname ) ) );
					?>
				</h2>
				<a class="featcat__how" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'How featuring works', 'oria' ); ?></a>
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
			echo esc_html( $oria_h1 );
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
			 * The matching set, server-rendered, members first then
			 * alphabetical — the page with scripting off. The script
			 * re-renders the same set from the payload.
			 */
			$oria_posts = $oria_ids
				? get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'post__in' => array_map( 'intval', $oria_ids ), 'posts_per_page' => 24, 'orderby' => 'title', 'order' => 'ASC' ) )
				: array();
			// Specialists first, then A to Z -- the same rule the script
			// sorts by, and never payment (app.js relevance()).
			$oria_spec = static function ( WP_Post $p ) use ( $oria_family ): int {
				$t = function_exists( '\Oria\Core\Primary\of' ) ? \Oria\Core\Primary\of( (int) $p->ID ) : '';
				return in_array( $t, $oria_family, true ) ? 0 : 1;
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
	 * why each matched. The form hands the sentence over as ?q=, the same
	 * door the front page's card uses, and /ask/ runs it on arrival. The
	 * example names this category and a suburb it really has listings in,
	 * so the first thing it teaches is a place and a time -- never a
	 * symptom (see page-ask.php on why).
	 *
	 * "Build your session" answers the other doubt: not where, but whether
	 * this is the right kind of practice at all.
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

<!-- Floor 3 — the guide, straight after the first page of listings -->
<section class="wrap section floor" id="read">
	<h2 class="micro floor__label"><?php esc_html_e( 'Guide', 'oria' ); ?></h2>
	<?php
	/*
	 * Choosing between two practices is decision help, so it opens the
	 * guide rather than sitting in the hero above the listings, where the
	 * UX audit found it pushing the results down.
	 */
	?>
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
	<?php elseif ( is_string( $oria_intro ) && '' !== trim( $oria_intro ) ) : ?>
		<h2 class="h3" style="margin-bottom:1rem"><?php printf( esc_html__( 'How %1$s is taught in %2$s', 'oria' ), esc_html( strtolower( $oria_pname ) ), esc_html( $oria_cname ) ); ?></h2>
		<div class="prose prose--intro"><?php echo wp_kses_post( \Oria\Core\PracticesIndex\rewrite_content_links( (string) $oria_intro, $oria_term ) ); ?></div>
	<?php endif; ?>

</section>

<?php
// What's on: after the guide, so the guide is one page of listings away.
if ( $oria_events ) {
	get_template_part( 'template-parts/category', 'events', array( 'term' => $oria_term, 'city' => $oria_city, 'rows' => $oria_events ) );
}
?>

<?php
// Floor 4 — the guides for this practice, as image cards; the latest from
// the journal where none are tagged to it yet, so the floor is always there.
get_template_part(
	'template-parts/guides',
	'floor',
	array(
		'guides'  => $oria_guides ?: $oria_latest,
		'heading' => $oria_guides ? sprintf( __( 'Guides to %s worth reading first', 'oria' ), strtolower( $oria_pname ) ) : __( 'From the journal', 'oria' ),
		'icon'    => ( $oria_term && function_exists( '\Oria\Core\Categories\icon' ) ) ? \Oria\Core\Categories\icon( $oria_term->slug ) : '',
		// Supporting reading, below the directory: smaller cards than the
		// journal's own pages, so they sit under the listings rather than
		// competing with them.
		'compact' => true,
	)
);
?>

<?php
/*
 * The FAQ part brings its own section and wrap; it has to sit at the top
 * level, not inside another wrap, or it inherits a second gutter and loses
 * its spacing. On a facet page the questions come from the frame where one
 * exists; a frameless facet page shows none rather than the category's.
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
	// The area mesh, same as the category page.
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

	$oria_links   = array();
	foreach ( $oria_regions as $oria_r ) {
		$oria_n = (int) ( $oria_counts['regions'][ $oria_r->slug ] ?? 0 );
		if ( $oria_n > 0 ) {
			$oria_links[] = array( \Oria\Core\PracticesIndex\area_url( $oria_term, $oria_r ), sprintf( '%s (%d)', \Oria\Theme\tname( $oria_r ), $oria_n ) );
		}
	}
	foreach ( $oria_counts['suburbs'] as $oria_sname => $oria_n ) {
		$oria_s = get_term_by( 'slug', sanitize_title( $oria_sname ), 'area' );
		if ( $oria_s instanceof WP_Term && 0 !== $oria_s->parent ) {
			$oria_links[] = array( \Oria\Core\PracticesIndex\area_url( $oria_term, $oria_s ), sprintf( '%s (%d)', \Oria\Theme\tname( $oria_s ), $oria_n ) );
		}
	}
	if ( $oria_links ) :
		?>
		<h2 class="micro" style="margin:0 0 1rem"><?php printf( esc_html__( '%s by area', 'oria' ), esc_html( $oria_pname ) ); ?></h2>
		<div class="chips">
			<?php foreach ( $oria_links as $oria_l ) : ?>
				<a class="pill" href="<?php echo esc_url( $oria_l[0] ); ?>"><?php echo esc_html( $oria_l[1] ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php
	/*
	 * The second paid band that sat here is gone: featured practices now
	 * appear once, in the labelled band above the listings. Showing the
	 * same paid cards twice was one of the UX audit's findings.
	 */
	?>
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
