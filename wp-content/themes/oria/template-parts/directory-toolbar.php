<?php
/**
 * The filter toolbar — concept 1a's replacement for the rail.
 *
 * Same inputs, same [data-filter] contract, same URL round-trip: the
 * directory engine in app.js cannot tell the difference. What changes is
 * where they live. Each facet is a popover (a native <details>, so it
 * opens without JavaScript), and the 78-term specialty facet becomes a
 * typeahead — which is what it always was — with the handful of most-used
 * terms for this category shown first.
 *
 * @var array $args {
 *     @type WP_Term|null $term  The practice term, for "most used" specialties.
 *     @type list<int>|null $ids The listings this page is about; the Area
 *                               facet lists only the regions and suburbs
 *                               that hold one of them, with counts.
 * }
 */

declare(strict_types=1);

$oria_term = $args['term'] ?? null;
$oria_ids  = isset( $args['ids'] ) && is_array( $args['ids'] )
	? array_map( 'intval', $args['ids'] )
	: get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );

/*
 * The Area facet, counted from the set on this page rather than listed from
 * the taxonomy. Every region and suburb offered leads to at least one
 * listing here; picking one never lands on "0 listings". Suburbs nest under
 * their region and filter as well — the engine reads a suburb by its name,
 * which is what the payload carries.
 */
$oria_area_counts = array();
foreach ( $oria_ids as $oria_lid ) {
	$oria_terms = wp_get_post_terms( (int) $oria_lid, 'area' );
	if ( is_wp_error( $oria_terms ) ) {
		continue;
	}
	$oria_region = null;
	$oria_suburb = null;
	foreach ( $oria_terms as $oria_at ) {
		// The tree is city → region → suburb (or region → suburb on an
		// unmigrated site); ask the taxonomy which level a term is rather
		// than guessing from parent ids.
		if ( \Oria\Core\Taxonomies\is_city( $oria_at ) ) {
			continue;
		}
		if ( \Oria\Core\Taxonomies\is_suburb( $oria_at ) ) {
			$oria_suburb = $oria_at;
			$oria_region = \Oria\Core\Taxonomies\region_for( $oria_at );
		} elseif ( ! $oria_region && \Oria\Core\Taxonomies\is_region( $oria_at ) ) {
			$oria_region = $oria_at;
		}
	}
	if ( ! $oria_region instanceof WP_Term ) {
		continue;
	}
	$oria_r = $oria_region->slug;
	if ( ! isset( $oria_area_counts[ $oria_r ] ) ) {
		$oria_area_counts[ $oria_r ] = array( 'term' => $oria_region, 'count' => 0, 'suburbs' => array() );
	}
	$oria_area_counts[ $oria_r ]['count']++;
	if ( $oria_suburb instanceof WP_Term ) {
		$oria_sn = \Oria\Theme\tname( $oria_suburb );
		if ( ! isset( $oria_area_counts[ $oria_r ]['suburbs'][ $oria_sn ] ) ) {
			$oria_area_counts[ $oria_r ]['suburbs'][ $oria_sn ] = array( 'term' => $oria_suburb, 'count' => 0 );
		}
		$oria_area_counts[ $oria_r ]['suburbs'][ $oria_sn ]['count']++;
	}
}
uasort( $oria_area_counts, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] );
foreach ( $oria_area_counts as &$oria_rc ) {
	ksort( $oria_rc['suburbs'] );
}
unset( $oria_rc );

/*
 * Styles and specialties, counted from the set on this page — the category,
 * or the narrower view a facet page has locked — so the popover only ever
 * offers terms that lead somewhere in this view. Styles (service terms:
 * yin, reformer, vinyasa) are what people type, so they come first; the
 * eight most used in this set are pulled out as quick picks.
 */
$oria_spec_counts = array(); // slug => ['term' => WP_Term, 'count' => n]
$oria_svc_counts  = array();
foreach ( $oria_ids as $oria_lid ) {
	$oria_sp = wp_get_post_terms( (int) $oria_lid, 'specialty' );
	if ( ! is_wp_error( $oria_sp ) ) {
		foreach ( $oria_sp as $oria_t ) {
			$oria_spec_counts[ $oria_t->slug ] = array( 'term' => $oria_t, 'count' => ( $oria_spec_counts[ $oria_t->slug ]['count'] ?? 0 ) + 1 );
		}
	}
	if ( taxonomy_exists( 'service' ) ) {
		$oria_sv = wp_get_post_terms( (int) $oria_lid, 'service' );
		if ( ! is_wp_error( $oria_sv ) ) {
			foreach ( $oria_sv as $oria_t ) {
				$oria_svc_counts[ $oria_t->slug ] = array( 'term' => $oria_t, 'count' => ( $oria_svc_counts[ $oria_t->slug ]['count'] ?? 0 ) + 1 );
			}
		}
	}
}
$oria_by_name = static fn( array $a, array $b ): int => strcasecmp( \Oria\Theme\tname( $a['term'] ), \Oria\Theme\tname( $b['term'] ) );
uasort( $oria_spec_counts, $oria_by_name );
uasort( $oria_svc_counts, $oria_by_name );

// One searchable list: services first, then specialties, each with its count in this set.
$oria_facets = array();
foreach ( $oria_svc_counts as $oria_row ) {
	$oria_facets[] = array( 'kind' => 'svc', 'term' => $oria_row['term'], 'count' => $oria_row['count'] );
}
foreach ( $oria_spec_counts as $oria_row ) {
	$oria_facets[] = array( 'kind' => 'spec', 'term' => $oria_row['term'], 'count' => $oria_row['count'] );
}

// A style and a specialty can share a name ("Remedial massage" is both);
// one label, one checkbox — keep whichever kind reaches more listings here.
$oria_seen = array();
foreach ( $oria_facets as $oria_i => $oria_f ) {
	$oria_key = strtolower( \Oria\Theme\tname( $oria_f['term'] ) );
	if ( isset( $oria_seen[ $oria_key ] ) ) {
		$oria_j = $oria_seen[ $oria_key ];
		if ( $oria_f['count'] > $oria_facets[ $oria_j ]['count'] ) {
			unset( $oria_facets[ $oria_j ] );
			$oria_seen[ $oria_key ] = $oria_i;
		} else {
			unset( $oria_facets[ $oria_i ] );
		}
		continue;
	}
	$oria_seen[ $oria_key ] = $oria_i;
}
$oria_facets = array_values( $oria_facets );
usort( $oria_facets, static fn( array $a, array $b ): int => strcasecmp( \Oria\Theme\tname( $a['term'] ), \Oria\Theme\tname( $b['term'] ) ) );

// Quick picks: the eight most used in this set, whichever kind they are.
$oria_top = $oria_facets;
usort( $oria_top, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] ?: strcasecmp( \Oria\Theme\tname( $a['term'] ), \Oria\Theme\tname( $b['term'] ) ) );
$oria_top      = array_slice( $oria_top, 0, 8 );
$oria_top_keys = array_map( static fn( array $f ): string => $f['kind'] . ':' . $f['term']->slug, $oria_top );

$oria_prices = array(
	'Free' => __( 'Free or by donation', 'oria' ),
	'$'    => __( 'Under $25', 'oria' ),
	'$$'   => __( '$25–$60', 'oria' ),
	'$$$'  => __( '$60–$200', 'oria' ),
	'$$$$' => __( '$200+', 'oria' ),
);
?>
<div class="toolbar" id="dirFilters">
	<div class="toolbar__search">
		<label class="sr-only" for="dirQ"><?php esc_html_e( 'Search', 'oria' ); ?></label>
		<?php
		/*
		 * A combobox, not a plain field: app.js fills the panel below with
		 * what this page actually holds. autocomplete="off" because the
		 * browser's own history dropdown would cover ours.
		 */
		?>
		<input class="input" id="dirQ" type="search" autocomplete="off"
			role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="dirQList"
			placeholder="<?php esc_attr_e( 'Search studios, teachers, styles…', 'oria' ); ?>">
		<span class="osearch osearch--dir" id="dirQList" data-dir-search-panel hidden></span>
	</div>

	<?php
	/*
	 * The style facet is the one people miss, so it gets a nudge: a pulsing
	 * ring and a one-line tooltip, shown once per visitor and dismissed the
	 * first time the popover opens (app.js remembers in localStorage).
	 */
	?>
	<div class="toolbar__filters">
	<?php if ( $oria_facets ) : ?>
	<div class="hinthost" data-hint-key="style">
	<span class="hintbubble" id="styleHint" role="tooltip"><?php esc_html_e( 'Find more options here', 'oria' ); ?></span>
	<details class="popover" data-popover>
		<summary class="btn btn--ghost btn--sm" aria-describedby="styleHint"><?php esc_html_e( 'Style & specialty', 'oria' ); ?> <span aria-hidden="true">▾</span></summary>
		<div class="popover__panel popover__panel--wide">
			<?php $oria_rest = count( $oria_facets ) - count( $oria_top ); ?>
			<?php if ( $oria_rest > 0 ) : ?>
			<label class="sr-only" for="facetSearch"><?php esc_html_e( 'Find a style or specialty', 'oria' ); ?></label>
			<input class="input input--sm" id="facetSearch" type="search" data-facet-search="#facetList" placeholder="<?php printf( esc_attr__( 'Type to search %d terms…', 'oria' ), count( $oria_facets ) ); ?>">
			<?php endif; ?>
			<?php if ( $oria_top ) : ?>
				<span class="micro" style="display:block;margin:<?php echo $oria_rest > 0 ? '.9rem' : '0'; ?> 0 .4rem"><?php echo $oria_rest > 0 ? esc_html__( 'Most used here', 'oria' ) : esc_html__( 'In this view', 'oria' ); ?></span>
				<div class="chips">
					<?php foreach ( $oria_top as $oria_s ) : ?>
						<label class="spectag"><input type="checkbox" data-filter="<?php echo esc_attr( $oria_s['kind'] ); ?>" value="<?php echo esc_attr( $oria_s['term']->slug ); ?>"><span><?php echo esc_html( \Oria\Theme\tname( $oria_s['term'] ) ); ?><b><?php echo esc_html( (string) $oria_s['count'] ); ?></b></span></label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $oria_rest > 0 ) : ?>
			<span class="micro" style="display:block;margin:.9rem 0 .4rem"><?php esc_html_e( 'All terms', 'oria' ); ?></span>
			<div class="facetlist" id="facetList" role="group" aria-label="<?php esc_attr_e( 'Styles and specialties', 'oria' ); ?>">
				<?php foreach ( $oria_facets as $oria_f ) : ?>
					<?php $oria_s = $oria_f['term']; ?>
					<?php if ( in_array( $oria_f['kind'] . ':' . $oria_s->slug, $oria_top_keys, true ) ) { continue; } ?>
					<label class="check" data-facet-label="<?php echo esc_attr( strtolower( \Oria\Theme\tname( $oria_s ) ) ); ?>"><input type="checkbox" data-filter="<?php echo esc_attr( $oria_f['kind'] ); ?>" value="<?php echo esc_attr( $oria_s->slug ); ?>"><span><?php echo esc_html( \Oria\Theme\tname( $oria_s ) ); ?> <em><?php echo esc_html( (string) $oria_f['count'] ); ?></em></span></label>
				<?php endforeach; ?>
				<p class="hint facetlist__empty" hidden><?php esc_html_e( 'Nothing matches — try a shorter word.', 'oria' ); ?></p>
			</div>
			<?php endif; ?>
			<button type="button" class="popover__done" data-popover-close><?php esc_html_e( 'Done', 'oria' ); ?></button>
		</div>
	</details>
	</div>
	<?php endif; ?>

	<?php if ( $oria_area_counts ) : ?>
	<details class="popover" data-popover>
		<summary class="btn btn--ghost btn--sm"><?php esc_html_e( 'Area', 'oria' ); ?> <span aria-hidden="true">▾</span></summary>
		<div class="popover__panel popover__panel--areas" role="group" aria-label="<?php esc_attr_e( 'Area', 'oria' ); ?>">
			<p class="hint" style="margin:0 0 .6rem"><?php esc_html_e( 'Only areas with listings in this view.', 'oria' ); ?></p>
			<?php foreach ( $oria_area_counts as $oria_rslug => $oria_rc ) : ?>
				<div class="areagroup">
					<label class="check areagroup__region"><input type="checkbox" data-filter="region" value="<?php echo esc_attr( $oria_rslug ); ?>"><span><?php echo esc_html( \Oria\Theme\tname( $oria_rc['term'] ) ); ?> <em><?php echo esc_html( (string) $oria_rc['count'] ); ?></em></span></label>
					<?php if ( $oria_rc['suburbs'] ) : ?>
						<div class="areagroup__suburbs">
							<?php foreach ( $oria_rc['suburbs'] as $oria_sn => $oria_sc ) : ?>
								<label class="check"><input type="checkbox" data-filter="suburb" value="<?php echo esc_attr( $oria_sn ); ?>"><span><?php echo esc_html( $oria_sn ); ?> <em><?php echo esc_html( (string) $oria_sc['count'] ); ?></em></span></label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<button type="button" class="popover__done" data-popover-close><?php esc_html_e( 'Done', 'oria' ); ?></button>
		</div>
	</details>
	<?php endif; ?>

	<details class="popover" data-popover>
		<summary class="btn btn--ghost btn--sm"><?php esc_html_e( 'Price', 'oria' ); ?> <span aria-hidden="true">▾</span></summary>
		<div class="popover__panel" role="group" aria-label="<?php esc_attr_e( 'Price per session', 'oria' ); ?>">
			<?php foreach ( $oria_prices as $oria_v => $oria_l ) : ?>
				<label class="check"><input type="checkbox" data-filter="price" value="<?php echo esc_attr( $oria_v ); ?>"><span><?php echo esc_html( $oria_l ); ?></span></label>
			<?php endforeach; ?>
			<button type="button" class="popover__done" data-popover-close><?php esc_html_e( 'Done', 'oria' ); ?></button>
		</div>
	</details>

	<div class="hinthost hinthost--gf" data-hint-key="goodfor" data-hint-delay="2600">
	<span class="hintbubble" id="gfHint" role="tooltip"><?php esc_html_e( 'What are you after?', 'oria' ); ?></span>
	<details class="popover" data-popover>
		<summary class="btn btn--sm gfsummary" aria-describedby="gfHint"><?php esc_html_e( 'Good for', 'oria' ); ?> <span aria-hidden="true">▾</span></summary>
		<div class="popover__panel" role="group" aria-label="<?php esc_attr_e( 'Good for', 'oria' ); ?>">
			<span class="micro" style="display:block;margin:0 0 .5rem"><?php esc_html_e( 'What are you after?', 'oria' ); ?></span>
			<?php
			/*
			 * The twelve wants, as multi-select options over the same specialty
			 * checkboxes the chip row drives — app.js initGoodFor() reads
			 * data-goodfor-opt and keeps the ticks honest against the real
			 * filters. Format and rating live on below them, demoted from the
			 * pill label but not lost.
			 */
			$oria_gf_all = function_exists( '\Oria\Core\GoodFor\labels' ) ? \Oria\Core\GoodFor\labels() : array();
			?>
			<?php foreach ( $oria_gf_all as $oria_g ) : ?>
				<label class="check"><input type="checkbox" data-goodfor-opt data-specs="<?php echo esc_attr( (string) wp_json_encode( $oria_g['specs'] ) ); ?>"><span><span class="gfdot" style="--gf:<?php echo esc_attr( $oria_g['color'] ); ?>"></span><?php echo esc_html( $oria_g['label'] ); ?></span></label>
			<?php endforeach; ?>
			<button type="button" class="popover__done" data-popover-close><?php esc_html_e( 'Done', 'oria' ); ?></button>
		</div>
	</details>
	</div>

	</div>

	<div class="toolbar__sort">
		<label class="sr-only" for="dirSort"><?php esc_html_e( 'Sort by', 'oria' ); ?></label>
		<select class="select" id="dirSort">
			<option value="relevance"><?php esc_html_e( 'Sort: Members first', 'oria' ); ?></option>
			<option value="rating"><?php esc_html_e( 'Sort: Highest rated', 'oria' ); ?></option>
			<option value="price"><?php esc_html_e( 'Sort: Lowest price', 'oria' ); ?></option>
			<option value="name"><?php esc_html_e( 'Sort: A–Z', 'oria' ); ?></option>
		</select>
	</div>
</div>
