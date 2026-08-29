<?php
/**
 * A specialty landing page (/perth/acupuncture/ — "Acupuncture in Perth"),
 * in the v2 directory design.
 *
 * Same three floors as the practice pages — Decide, Browse, Read — with a
 * sticky spine, the answer-first top, the facts strip and the toolbar
 * instead of the old filter rail. The engine underneath is unchanged: the
 * directory with this specialty locked, every listing in the HTML, one H1,
 * breadcrumbs intact.
 *
 * What it deliberately does NOT borrow from the practice template is the
 * intent grid. Those rows come from Intents\for_practice() and resolve to
 * /practices/{practice}/{facet}/ addresses; a specialty has no such family,
 * and inventing one would send people to pages that do not exist.
 *
 * The reason this page exists rather than pointing at a facet URL: a facet
 * is the INTERSECTION of a practice and a specialty, so /practices/fitness/
 * tai-chi/ means "tai chi filed under Fitness" and shows one listing where
 * this page shows four. The specialty page is the complete set, and it is
 * what "Tai chi in Perth" ought to mean.
 */

declare(strict_types=1);

get_header();

$oria_term  = get_queried_object();
$oria_term  = $oria_term instanceof WP_Term ? $oria_term : null;
$oria_sname = $oria_term ? \Oria\Theme\tname( $oria_term ) : '';
$oria_h1    = sprintf( __( '%s in Perth', 'oria' ), $oria_sname );

/*
 * The whole set for this specialty, resolved server-side so the facts and
 * the spine count agree with the results underneath them.
 */
$oria_ids = array();
if ( $oria_term ) {
	$oria_ids = get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'specialty',
					'field'    => 'term_id',
					'terms'    => $oria_term->term_id,
				),
			),
		)
	);
	$oria_ids = array_map( 'intval', $oria_ids );
}

// Facts for the strip — the same four the practice pages report.
$oria_suburbs = array();
$oria_claimed = 0;
$oria_bands   = array();
$oria_prices  = array();
foreach ( $oria_ids as $oria_id ) {
	foreach ( wp_get_post_terms( (int) $oria_id, 'area' ) as $oria_a ) {
		if ( $oria_a->parent ) {
			$oria_suburbs[ $oria_a->slug ] = true;
		}
	}
	if ( 'unclaimed' !== \Oria\Theme\claim_status( (int) $oria_id ) ) {
		$oria_claimed++;
	}
	$oria_b = (string) get_field( 'price_band', (int) $oria_id );
	if ( '' !== $oria_b ) {
		$oria_bands[ $oria_b ] = ( $oria_bands[ $oria_b ] ?? 0 ) + 1;
	}
	$oria_pf = get_field( 'price_from', (int) $oria_id );
	if ( is_numeric( $oria_pf ) && (float) $oria_pf > 0 ) {
		$oria_prices[] = (float) $oria_pf;
	}
}
arsort( $oria_bands );
$oria_typical = $oria_bands ? (string) array_key_first( $oria_bands ) : '';

// Median starting price, so one expensive outlier cannot drag it about.
// Three priced listings is the floor for showing a figure at all.
$oria_price_n = count( $oria_prices );
$oria_price   = 0.0;
if ( $oria_price_n >= 3 ) {
	sort( $oria_prices );
	$oria_mid   = intdiv( $oria_price_n, 2 );
	$oria_price = 0 === $oria_price_n % 2 ? ( $oria_prices[ $oria_mid - 1 ] + $oria_prices[ $oria_mid ] ) / 2 : $oria_prices[ $oria_mid ];
}

$oria_answer = ( $oria_term && function_exists( '\Oria\Core\Answer\for_term' ) )
	? \Oria\Core\Answer\for_term( $oria_term )
	: array( 'sentences' => array(), 'updated' => '' );

$oria_intro = ( $oria_term && function_exists( 'Oria\Core\Seo\specialty_intro' ) )
	? \Oria\Core\Seo\specialty_intro( $oria_term )
	: array();

$oria_faqs = ( $oria_term && function_exists( '\Oria\Core\Faq\for_term' ) )
	? (array) \Oria\Core\Faq\for_term( $oria_term )
	: array();

$oria_guides = ( $oria_term && function_exists( '\Oria\Core\Guides\for_term' ) )
	? \Oria\Core\Guides\for_term( $oria_term )
	: array();
?>

<?php if ( $oria_term ) : ?>
<nav class="spine" aria-label="<?php esc_attr_e( 'Page sections', 'oria' ); ?>">
	<div class="wrap spine__row">
		<a href="#decide"><b>1</b> <?php esc_html_e( 'Decide', 'oria' ); ?></a>
		<a href="#browse"><b>2</b> <?php printf( esc_html__( 'Browse all %s', 'oria' ), esc_html( number_format_i18n( count( $oria_ids ) ) ) ); ?></a>
		<?php if ( $oria_intro ) : ?><a href="#read"><b>3</b> <?php esc_html_e( 'Read up', 'oria' ); ?></a><?php endif; ?>
		<?php if ( $oria_guides ) : ?><a href="#guides"><b><?php echo $oria_intro ? 4 : 3; ?></b> <?php esc_html_e( 'Guides', 'oria' ); ?></a><?php endif; ?>
		<?php if ( $oria_faqs ) : ?><a href="#faq"><b><?php echo ( $oria_intro ? 1 : 0 ) + ( $oria_guides ? 1 : 0 ) + 3; ?></b> <?php esc_html_e( 'FAQ', 'oria' ); ?></a><?php endif; ?>
	</div>
</nav>

<!-- Floor 1 — Decide -->
<section class="wrap pagehead floor" id="decide">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Directory', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_sname ); ?></span>
	</nav>
	<div class="decide">
		<div class="decide__answer">
			<div class="decide__head">
				<span class="micro"><?php esc_html_e( 'Specialty', 'oria' ); ?></span>
				<h1 class="h1 pagehead__title"><?php echo esc_html( $oria_h1 ); ?></h1>
			</div>
			<?php if ( $oria_answer['sentences'] ) : ?>
				<span class="micro"><?php esc_html_e( 'The short answer', 'oria' ); ?></span>
				<p class="lede" style="margin-top:.5rem;max-width:62ch"><?php echo esc_html( implode( ' ', $oria_answer['sentences'] ) ); ?></p>
				<p class="hint" style="margin-top:.6rem">
					<?php
					/* translators: %s: the date the figures were last refreshed */
					printf( esc_html__( 'Every listing hand-checked. Figures live from the directory, last updated %s.', 'oria' ), esc_html( (string) $oria_answer['updated'] ) );
					?>
				</p>
			<?php elseif ( $oria_intro ) : ?>
				<?php // No generated answer for this term yet — the written intro opens instead. ?>
				<p class="lede" style="max-width:62ch"><?php echo esc_html( (string) $oria_intro[0] ); ?></p>
			<?php endif; ?>
		</div>

		<?php
		/*
		 * The map, exactly as the category pages carry it: every listing on
		 * this page pinned at its real coordinates, hover to name it, click
		 * for the photo card and the profile. Same payload shape, so the one
		 * initCatMap() in app.js serves both.
		 */
		$oria_map = array();
		foreach ( $oria_ids as $oria_mid ) {
			$oria_mla = get_post_meta( (int) $oria_mid, 'geo_lat', true );
			$oria_mlo = get_post_meta( (int) $oria_mid, 'geo_lng', true );
			if ( ! is_numeric( $oria_mla ) || ! is_numeric( $oria_mlo ) || 0.0 === (float) $oria_mla ) {
				continue;
			}
			$oria_msub = '';
			foreach ( wp_get_post_terms( (int) $oria_mid, 'area' ) as $oria_mt ) {
				if ( $oria_mt->parent ) {
					$oria_msub = $oria_mt->name;
					break;
				}
			}
			$oria_map[] = array(
				'n'  => wp_specialchars_decode( (string) get_post_field( 'post_title', $oria_mid, 'raw' ), ENT_QUOTES ),
				'u'  => (string) get_permalink( (int) $oria_mid ),
				'la' => (float) $oria_mla,
				'lo' => (float) $oria_mlo,
				's'  => $oria_msub,
				'i'  => function_exists( '\Oria\Theme\listing_image' ) ? \Oria\Theme\listing_image( (int) $oria_mid ) : '',
			);
		}

		/*
		 * "Near you" for this specialty. A specialty has no facet family of
		 * its own — /practices/{cat}/{suburb}/ belongs to categories — so the
		 * pills filter this page in place rather than inventing an address
		 * that would 404.
		 */
		$oria_near = array();
		foreach ( $oria_ids as $oria_nid ) {
			foreach ( wp_get_post_terms( (int) $oria_nid, 'area' ) as $oria_na ) {
				if ( ! $oria_na->parent ) {
					continue;
				}
				if ( ! isset( $oria_near[ $oria_na->slug ] ) ) {
					$oria_near[ $oria_na->slug ] = array( 'name' => $oria_na->name, 'n' => 0 );
				}
				++$oria_near[ $oria_na->slug ]['n'];
			}
		}
		$oria_near = array_filter( $oria_near, static fn( array $oria_r ): bool => $oria_r['n'] >= 3 );
		uasort( $oria_near, static fn( array $oria_a, array $oria_b ): int => $oria_b['n'] <=> $oria_a['n'] );
		$oria_near = array_slice( $oria_near, 0, 12, true );
		?>
		<?php if ( $oria_map ) : ?>
		<div class="decide__map">
			<div class="catmap" data-catmap role="img" aria-label="<?php printf( esc_attr__( 'Map of %s places across Perth', 'oria' ), esc_attr( $oria_sname ) ); ?>">
				<div class="catmap__tip" hidden></div>
			</div>
			<script type="application/json" data-catmap-data><?php echo wp_json_encode( $oria_map ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON in a data script tag ?></script>
			<?php if ( $oria_near && $oria_term ) : ?>
				<div class="nearyou nearyou--map">
					<h2 class="h4"><?php printf( esc_html__( '%s near you', 'oria' ), esc_html( $oria_sname ) ); ?></h2>
					<div class="nearyou__pills">
						<?php foreach ( $oria_near as $oria_nrow ) : ?>
							<a class="pill" data-suburb="<?php echo esc_attr( $oria_nrow['name'] ); ?>" href="<?php echo esc_url( add_query_arg( 'suburb', rawurlencode( $oria_nrow['name'] ), (string) get_term_link( $oria_term ) ) ); ?>">
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
	 * The compare prompt, exactly as the practice pages carry it —
	 * experience_for_term() already resolves specialty terms.
	 */
	$oria_cmp = function_exists( '\Oria\Core\Compare\prompt_for_term' )
		? \Oria\Core\Compare\prompt_for_term( $oria_term )
		: null;
	?>
	<?php if ( $oria_cmp && $oria_cmp['filled'] ) : ?>
		<p class="cmpnudge">
			<a href="<?php echo esc_url( $oria_cmp['url'] ); ?>" data-oria-event="specialty_compare">
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
	<?php // data-spec is what app.js reads to lock a specialty, as data-cat locks a practice. ?>
	<div class="dir__results dir__results--wide" id="dirResults" data-spec="<?php echo esc_attr( $oria_term->slug ); ?>">
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/listing', 'card' );
		endwhile;
		?>
	</div>
</section>

<?php if ( $oria_intro ) : ?>
<!-- Floor 3 — Read -->
<section class="wrap section floor" id="read">
	<h2 class="micro floor__label"><?php esc_html_e( 'Read up', 'oria' ); ?></h2>
	<h2 class="h3" style="margin-bottom:1rem">
		<?php
		/* translators: %s: the specialty, lowercased */
		printf( esc_html__( 'What %s involves', 'oria' ), esc_html( strtolower( $oria_sname ) ) );
		?>
	</h2>
	<div class="prose prose--intro">
		<?php foreach ( $oria_intro as $oria_para ) : ?>
			<p><?php echo esc_html( $oria_para ); ?></p>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php
if ( $oria_guides ) {
	get_template_part(
		'template-parts/guides',
		'floor',
		array(
			'guides'  => $oria_guides,
			/* translators: %s: the specialty, lowercased */
			'heading' => sprintf( __( 'Guides to %s worth reading first', 'oria' ), strtolower( $oria_sname ) ),
			'icon'    => '',
		)
	);
}

/*
 * The FAQ part brings its own section and wrap, so it has to sit at the top
 * level or it inherits a second gutter and loses its spacing.
 */
if ( $oria_faqs ) {
	get_template_part(
		'template-parts/faq',
		null,
		array(
			'faqs'    => $oria_faqs,
			/* translators: %s: the specialty, lowercased */
			'heading' => sprintf( __( 'Questions people ask about %s in Perth', 'oria' ), strtolower( $oria_sname ) ),
			'id'      => 'faq',
		)
	);
}
?>
<?php endif; ?>

<?php
get_footer();
