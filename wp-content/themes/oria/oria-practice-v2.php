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
$oria_here  = $oria_term ? \Oria\Core\PracticesIndex\category_url( $oria_term ) : home_url( '/practices/' );
$oria_facet = \Oria\Core\PracticesIndex\facet();
$oria_frame = (array) ( $oria_facet['page']['frame'] ?? array() );
$oria_intro = $oria_term ? get_field( 'landing_intro', 'practice_' . $oria_term->term_id ) : '';

// The sets: everything in the category, and the locked subset when a facet is on.
$oria_all = $oria_term && function_exists( '\Oria\Core\Intents\listings_in' ) ? \Oria\Core\Intents\listings_in( $oria_term ) : array();
$oria_ids = ( $oria_facet && $oria_term ) ? \Oria\Core\PracticesIndex\facet_ids( $oria_term, $oria_facet ) : $oria_all;

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

// Facts for the strip, over whichever set the page is about.
$oria_suburbs = array();
$oria_claimed = 0;
$oria_bands   = array();
$oria_prices  = array();
foreach ( $oria_ids as $oria_id ) {
	foreach ( wp_get_post_terms( (int) $oria_id, 'area' ) as $oria_a ) {
		if ( $oria_a->parent ) { $oria_suburbs[ $oria_a->slug ] = true; }
	}
	if ( 'unclaimed' !== \Oria\Theme\claim_status( (int) $oria_id ) ) { $oria_claimed++; }
	$oria_b = (string) get_field( 'price_band', (int) $oria_id );
	if ( '' !== $oria_b ) { $oria_bands[ $oria_b ] = ( $oria_bands[ $oria_b ] ?? 0 ) + 1; }
	$oria_pf = get_field( 'price_from', (int) $oria_id );
	if ( is_numeric( $oria_pf ) && (float) $oria_pf > 0 ) { $oria_prices[] = (float) $oria_pf; }
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
$oria_rows   = $oria_term && function_exists( '\Oria\Core\Intents\for_practice' ) ? \Oria\Core\Intents\for_practice( $oria_term ) : array();
$oria_guides = $oria_term && function_exists( '\Oria\Core\Guides\for_term' ) ? \Oria\Core\Guides\for_term( $oria_term ) : array();
$oria_latest = $oria_guides ? array() : get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'date', 'order' => 'DESC' ) );

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

$oria_h1 = $oria_facet ? (string) $oria_facet['label'] : sprintf( __( '%s in Perth', 'oria' ), $oria_pname );
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
	$old = untrailingslashit( \Oria\Core\PracticesIndex\original_url( $oria_term ) );
	if ( false === strpos( $url, '?' ) ) {
		// A row already pointing at a canonical intent page under the old
		// family (/practice/yoga/yin/) moves to the same slug here — the
		// facet resolver reads the registry, so it renders the same frame.
		if ( 0 === strpos( $url, $old . '/' ) ) {
			$seg = trim( substr( $url, strlen( $old ) ), '/' );
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
		<a href="#decide"><b>1</b> <?php esc_html_e( 'Decide', 'oria' ); ?></a>
		<a href="#browse"><b>2</b> <?php printf( esc_html__( 'Browse all %s', 'oria' ), esc_html( number_format_i18n( count( $oria_ids ) ) ) ); ?></a>
		<a href="#read"><b>3</b> <?php esc_html_e( 'Read up', 'oria' ); ?></a>
		<?php if ( $oria_guides || $oria_latest ) : ?><a href="#guides"><b>4</b> <?php esc_html_e( 'Guides', 'oria' ); ?></a><?php endif; ?>
		<?php if ( $oria_faqs ) : ?><a href="#faq"><b><?php echo ( $oria_guides || $oria_latest ) ? 5 : 4; ?></b> <?php esc_html_e( 'FAQ', 'oria' ); ?></a><?php endif; ?>
	</div>
</nav>

<!-- Floor 1 — Decide -->
<section class="wrap pagehead floor" id="decide">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( \Oria\Core\PracticesIndex\url() ); ?>"><?php esc_html_e( 'Practices', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<?php if ( $oria_facet ) : ?>
			<a href="<?php echo esc_url( $oria_here ); ?>"><?php echo esc_html( $oria_pname ); ?></a>
			<span aria-hidden="true">/</span><span><?php echo esc_html( (string) ( $oria_facet['page']['label'] ?? preg_replace( '/ in Perth$/', '', (string) $oria_facet['label'] ) ) ); ?></span>
		<?php else : ?>
			<span><?php echo esc_html( $oria_pname ); ?></span>
		<?php endif; ?>
	</nav>
	<div style="margin-top:1rem">
		<span class="micro"><?php echo $oria_facet ? esc_html( $oria_pname ) . ' · ' . esc_html__( 'Filtered view', 'oria' ) : esc_html__( 'Practice', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title"><?php echo esc_html( $oria_h1 ); ?></h1>
		<?php
		/*
		 * The emotional register, one line, before the numbers. Base view
		 * only: facet and suburb views are already narrowed to a decision,
		 * and their opener copy does this job.
		 */
		$oria_tag = '';
		if ( ! $oria_facet && ! $oria_area && function_exists( '\Oria\Core\Categories\tagline_for' ) ) {
			$oria_tag = \Oria\Core\Categories\tagline_for( (string) $oria_term->slug );
			if ( '' === $oria_tag && $oria_term->parent ) {
				// Child categories inherit the parent's line — "Meditation
				// classes" reads under Mind's tagline as naturally as Mind does.
				$oria_parent = get_term( $oria_term->parent, 'practice' );
				if ( $oria_parent instanceof WP_Term ) {
					$oria_tag = \Oria\Core\Categories\tagline_for( (string) $oria_parent->slug );
				}
			}
		}
		?>
		<?php if ( '' !== $oria_tag ) : ?>
			<p class="pagehead__tag"><?php echo esc_html( $oria_tag ); ?></p>
		<?php endif; ?>
	</div>

	<div class="decide">
		<div class="decide__answer">
			<?php if ( $oria_facet ) : ?>
				<span class="micro"><?php esc_html_e( 'The short answer', 'oria' ); ?></span>
				<p class="lede" style="margin-top:.5rem;max-width:62ch">
					<?php
					printf(
						/* translators: 1: matching count, 2: category total, 3: category name. */
						esc_html__( '%1$s of the %2$s %3$s listings in the directory match this view. The count is live, and the order below is members first, then alphabetical — never by rating.', 'oria' ),
						'<b>' . esc_html( number_format_i18n( count( $oria_ids ) ) ) . '</b>',
						esc_html( number_format_i18n( count( $oria_all ) ) ),
						esc_html( strtolower( $oria_pname ) )
					);
					?>
				</p>
				<?php if ( ! empty( $oria_frame['opener'] ) ) : ?>
					<p class="hint" style="margin-top:.6rem;max-width:62ch"><?php echo esc_html( $oria_fill( (string) $oria_frame['opener'] ) ); ?></p>
				<?php endif; ?>
				<?php
				/*
				 * "Who it suits" — answered by counting what these businesses
				 * publish, never by asserting anything about the reader. Absent
				 * until somebody has actually checked, which is the point of it.
				 */
				$oria_aud = ( $oria_facet && function_exists( '\Oria\Core\IntentPages\audience_note' ) && ! empty( $oria_facet['page'] ) )
					? \Oria\Core\IntentPages\audience_note(
						$oria_facet['page'],
						array( 'ids' => $oria_ids )
					)
					: null;
				?>
				<?php if ( $oria_aud ) : ?>
					<p class="hint" style="margin-top:.6rem;max-width:62ch">
						<?php
						/*
						 * Label first, because the audience names are noun
						 * phrases — "Beginner friendly", "Step-free access",
						 * "Drop-in welcome" — and none of them sits inside a
						 * sentence without bending it.
						 */
						printf(
							/* translators: 1: audience name e.g. "Beginner friendly", 2: how many say so, 3: how many on this page. */
							esc_html__( '%1$s — %2$s of the %3$s here say so on their own website or timetable.', 'oria' ),
							esc_html( (string) $oria_aud['name'] ),
							esc_html( number_format_i18n( (int) $oria_aud['yes'] ) ),
							esc_html( number_format_i18n( (int) $oria_aud['of'] ) )
						);
						?>
					</p>
				<?php endif; ?>
			<?php elseif ( $oria_answer['sentences'] ) : ?>
				<span class="micro"><?php esc_html_e( 'The short answer', 'oria' ); ?></span>
				<p class="lede" style="margin-top:.5rem;max-width:62ch"><?php echo esc_html( implode( ' ', $oria_answer['sentences'] ) ); ?></p>
				<p class="hint" style="margin-top:.6rem"><?php printf( esc_html__( 'Every listing hand-checked. Figures live from the directory, last updated %s.', 'oria' ), esc_html( (string) $oria_answer['updated'] ) ); ?></p>
			<?php elseif ( $oria_term->description ) : ?>
				<p class="lede" style="max-width:62ch"><?php echo esc_html( $oria_term->description ); ?></p>
			<?php endif; ?>
			<?php if ( $oria_facet && $oria_ids && $oria_all ) : ?>
				<?php
				/*
				 * One dated, quotable sentence with real numbers. AI answer
				 * engines lift exactly this shape -- a count, a place, a
				 * date -- and a sentence they can quote is a citation the
				 * site earns. Counts only; nothing about what any of it
				 * does for anybody.
				 */
				?>
				<p class="hint" style="margin-top:.6rem;max-width:62ch">
					<?php
					if ( $oria_area ) {
						printf(
							/* translators: 1: month year, 2: count, 3: total, 4: category, 5: suburb */
							esc_html__( 'As of %1$s, %2$s of the %3$s %4$s listings on Oria Haven are in %5$s — counted live from the directory.', 'oria' ),
							esc_html( date_i18n( 'F Y' ) ),
							esc_html( number_format_i18n( count( $oria_ids ) ) ),
							esc_html( number_format_i18n( count( $oria_all ) ) ),
							esc_html( $oria_pname ),
							esc_html( \Oria\Theme\tname( $oria_area ) )
						);
					} else {
						printf(
							/* translators: 1: month year, 2: count, 3: total, 4: category */
							esc_html__( 'As of %1$s, %2$s of the %3$s %4$s listings on Oria Haven match this page — counted live from the directory.', 'oria' ),
							esc_html( date_i18n( 'F Y' ) ),
							esc_html( number_format_i18n( count( $oria_ids ) ) ),
							esc_html( number_format_i18n( count( $oria_all ) ) ),
							esc_html( $oria_pname )
						);
					}
					?>
				</p>
			<?php endif; ?>
		</div>
		<dl class="facts">
			<div><dt><?php esc_html_e( 'listings', 'oria' ); ?></dt><dd><?php echo esc_html( number_format_i18n( count( $oria_ids ) ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'suburbs', 'oria' ); ?></dt><dd><?php echo esc_html( number_format_i18n( count( $oria_suburbs ) ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'claimed by the business', 'oria' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $oria_claimed ) ); ?></dd></div>
			<?php if ( $oria_price > 0 ) : ?>
				<div>
					<dt><?php esc_html_e( 'typical price', 'oria' ); ?></dt>
					<dd><?php printf( esc_html__( 'from $%s', 'oria' ), esc_html( number_format_i18n( round( $oria_price ) ) ) ); ?></dd>
					<small class="facts__note"><?php printf( esc_html( _n( 'median, %s priced listing', 'median, %s priced listings', $oria_price_n, 'oria' ) ), esc_html( number_format_i18n( $oria_price_n ) ) ); ?></small>
				</div>
			<?php elseif ( '' !== $oria_typical ) : ?>
				<div><dt><?php esc_html_e( 'typical price band', 'oria' ); ?></dt><dd><?php echo esc_html( $oria_typical ); ?></dd></div>
			<?php endif; ?>
		</dl>
	</div>

	<?php
	/*
	 * "Near you", from the same listings the page is already holding. Only
	 * suburbs that clear the facet gate (three or more) get a link, so
	 * every pill lands on a page the sitemap also stands behind; smaller
	 * suburbs stay reachable through the toolbar's area filter. Base view
	 * only — a narrowed page answering "where" twice is noise.
	 */
	$oria_near = array();
	if ( ! $oria_facet && ! $oria_area && $oria_term ) {
		foreach ( $oria_all as $oria_nid ) {
			foreach ( wp_get_post_terms( (int) $oria_nid, 'area' ) as $oria_na ) {
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
	<?php if ( $oria_near ) : ?>
		<div class="nearyou">
			<h2 class="h3"><?php printf( esc_html__( '%s near you', 'oria' ), esc_html( $oria_pname ) ); ?></h2>
			<div class="nearyou__pills">
				<?php foreach ( $oria_near as $oria_nslug => $oria_nrow ) : ?>
					<a class="pill" href="<?php echo esc_url( home_url( '/practices/' . $oria_term->slug . '/' . $oria_nslug . '/' ) ); ?>">
						<?php echo esc_html( $oria_nrow['name'] ); ?> <span class="nearyou__n"><?php echo esc_html( number_format_i18n( $oria_nrow['n'] ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( count( $oria_rows ) >= 2 ) : ?>
		<h2 class="h3 typewrite" style="margin-top:2rem" data-typewrite><?php echo $oria_facet ? esc_html__( 'Or another kind', 'oria' ) : esc_html__( 'Start with what you want to do', 'oria' ); ?></h2>
		<p class="hint typewrite__after" style="margin:.35rem 0 1rem"><?php printf( esc_html__( 'Each is a filtered view of the %s listings — it counts, it never ranks.', 'oria' ), esc_html( number_format_i18n( count( $oria_all ) ) ) ); ?></p>
		<div class="intentgrid">
			<?php if ( $oria_facet ) : ?>
				<a class="intentcard intentcard--all" href="<?php echo esc_url( $oria_here ); ?>" style="--i:0">
					<span class="intentcard__label"><?php printf( esc_html__( 'All %s', 'oria' ), esc_html( strtolower( $oria_pname ) ) ); ?></span>
					<span class="intentcard__count"><?php echo esc_html( number_format_i18n( count( $oria_all ) ) ); ?> <span aria-hidden="true">→</span></span>
				</a>
			<?php endif; ?>
			<?php
			$oria_i = $oria_facet ? 1 : 0; // the "All …" card, when present, takes slot 0
			foreach ( $oria_rows as $oria_row ) :
				$oria_href = $oria_row_url( $oria_row );
				$oria_on   = '' !== $oria_facet_href && untrailingslashit( $oria_href ) === untrailingslashit( $oria_facet_href );
				?>
				<a class="intentcard<?php echo $oria_on ? ' is-current' : ''; ?>" href="<?php echo esc_url( $oria_href ); ?>"<?php echo $oria_on ? ' aria-current="page"' : ''; ?> style="--i:<?php echo (int) $oria_i++; ?>">
					<span class="intentcard__body">
						<span class="intentcard__label"><?php echo esc_html( (string) $oria_row['label'] ); ?></span>
						<?php // Only rows backed by an intent page carry one, so the grid degrades to labels. ?>
						<?php if ( '' !== (string) ( $oria_row['note'] ?? '' ) ) : ?>
							<span class="intentcard__note"><?php echo esc_html( (string) $oria_row['note'] ); ?></span>
						<?php endif; ?>
					</span>
					<span class="intentcard__count"><?php echo esc_html( number_format_i18n( (int) $oria_row['count'] ) ); ?> <span aria-hidden="true"><?php echo $oria_on ? '✓' : '→'; ?></span></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

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
</section>

<!-- Floor 2 — Browse -->
<section class="wrap section section--top-flush floor" id="browse">
	<h2 class="micro floor__label"><?php esc_html_e( 'Browse', 'oria' ); ?></h2>
	<?php get_template_part( 'template-parts/directory', 'toolbar', array( 'term' => $oria_term, 'ids' => $oria_ids ) ); ?>
	<p class="dir__count" id="dirCount" style="margin-top:1rem"></p>
	<div class="chips" id="dirChips" style="margin-top:.5rem"></div>
	<h2 class="sr-only"><?php echo esc_html( $oria_h1 ); ?> — <?php esc_html_e( 'listings', 'oria' ); ?></h2>
	<div
		class="dir__results dir__results--wide"
		id="dirResults"
		data-cat="<?php echo esc_attr( $oria_term->slug ); ?>"
		<?php if ( $oria_area ) : ?>
			<?php // app.js already honours data-suburb as a locked filter. ?>
			data-suburb="<?php echo esc_attr( \Oria\Theme\tname( $oria_area ) ); ?>"
		<?php endif; ?>
		<?php if ( $oria_facet ) : ?>
			data-intent-key="<?php echo esc_attr( (string) $oria_facet['key'] ); ?>"
			data-intent-value="<?php echo esc_attr( (string) $oria_facet['value'] ); ?>"
		<?php endif; ?>
	>
		<?php
		if ( $oria_facet || $oria_area ) {
			/*
			 * The matching set, server-rendered, members first then
			 * alphabetical — the page with scripting off. The script
			 * re-renders the same set from the payload.
			 */
			$oria_posts = $oria_ids
				? get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'post__in' => array_map( 'intval', $oria_ids ), 'posts_per_page' => 24, 'orderby' => 'title', 'order' => 'ASC' ) )
				: array();
			usort(
				$oria_posts,
				static function ( WP_Post $a, WP_Post $b ): int {
					$ma = 'unclaimed' === \Oria\Theme\claim_status( $a->ID ) ? 1 : 0;
					$mb = 'unclaimed' === \Oria\Theme\claim_status( $b->ID ) ? 1 : 0;
					return $ma <=> $mb ?: strcasecmp( $a->post_title, $b->post_title );
				}
			);
			global $post;
			foreach ( $oria_posts as $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $post );
				get_template_part( 'template-parts/listing', 'card' );
			}
			wp_reset_postdata();
		} else {
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/listing', 'card' );
			endwhile;
		}
		?>
	</div>
</section>

<!-- Floor 3 — Read -->
<section class="wrap section floor" id="read">
	<h2 class="micro floor__label"><?php esc_html_e( 'Read up', 'oria' ); ?></h2>

	<?php if ( $oria_facet && ! empty( $oria_frame['worth_knowing'] ) ) : ?>
		<h2 class="h3" style="margin-bottom:1rem"><?php esc_html_e( 'Worth knowing', 'oria' ); ?></h2>
		<div class="prose prose--intro">
			<?php foreach ( (array) $oria_frame['worth_knowing'] as $oria_para ) : ?>
				<p><?php echo esc_html( $oria_fill( (string) $oria_para ) ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php elseif ( is_string( $oria_intro ) && '' !== trim( $oria_intro ) ) : ?>
		<h2 class="h3" style="margin-bottom:1rem"><?php printf( esc_html__( 'How %s is taught in Perth', 'oria' ), esc_html( strtolower( $oria_pname ) ) ); ?></h2>
		<div class="prose prose--intro"><?php echo wp_kses_post( \Oria\Core\PracticesIndex\rewrite_content_links( (string) $oria_intro, $oria_term ) ); ?></div>
	<?php endif; ?>

</section>

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
	get_template_part( 'template-parts/faq', null, array( 'faqs' => $oria_faqs, 'heading' => sprintf( __( 'Questions people ask about %s in Perth', 'oria' ), strtolower( $oria_pname ) ), 'id' => 'faq' ) );
}
?>

<section class="wrap section section--top-flush floor">
	<?php
	// The area mesh, same as the category page.
	$oria_counts  = \Oria\Theme\combo_counts( $oria_term->slug );
	$oria_regions = \Oria\Core\Taxonomies\regions();
	$oria_regions = is_wp_error( $oria_regions ) ? array() : $oria_regions;
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

	<?php $oria_feat = \Oria\Theme\featured_listings( 3, $oria_term->slug ); ?>
	<?php if ( $oria_feat ) : ?>
		<div style="margin-top:2rem">
			<?php get_template_part( 'template-parts/featured', 'band', array( 'posts' => $oria_feat, 'heading' => sprintf( __( 'Featured in %s — paid placement', 'oria' ), $oria_pname ) ) ); ?>
		</div>
	<?php endif; ?>
</section>
<?php endif; ?>

<?php
get_footer();
