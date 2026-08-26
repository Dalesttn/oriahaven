<?php
/**
 * An intent page: /practice/{practice}/{intent}/ — see Oria\Core\IntentPages.
 *
 * The directory engine with the practice AND the intent locked, inside a
 * hand-written frame. Document order is deliberate: facts first, then the
 * view and its listings, then the prose, then the questions — so a person
 * skimming and anything reading from the top both get the answer before
 * the chrome. Everything is in the HTML; the script only re-renders the
 * same set with the same rules.
 */

declare(strict_types=1);

get_header();

$oria_page  = \Oria\Core\IntentPages\current();
$oria_term  = $oria_page['term'] ?? null;
$oria_facts = $oria_page ? \Oria\Core\IntentPages\facts( $oria_page ) : array( 'ids' => array(), 'count' => 0, 'total' => 0, 'publishable' => false );
$oria_frame = (array) ( $oria_page['frame'] ?? array() );
$oria_pname = $oria_term instanceof WP_Term ? \Oria\Theme\tname( $oria_term ) : '';
$oria_h1    = (string) ( $oria_frame['h1'] ?? ( $oria_page['label'] ?? '' ) );
$oria_fill  = static fn( string $s ): string => $oria_term instanceof WP_Term ? \Oria\Core\IntentPages\fill( $s, $oria_facts, $oria_term ) : $s;

$oria_filter_key   = (string) array_key_first( $oria_page['filter'] ?? array() );
$oria_filter_value = (string) ( $oria_page['filter'][ $oria_filter_key ] ?? '' );
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( function_exists( '\Oria\Core\PracticesIndex\url' ) ? \Oria\Core\PracticesIndex\url() : home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Practices', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<?php if ( $oria_term instanceof WP_Term ) : ?>
			<a href="<?php echo esc_url( (string) get_term_link( $oria_term ) ); ?>"><?php echo esc_html( $oria_pname ); ?></a>
			<span aria-hidden="true">/</span>
		<?php endif; ?>
		<span><?php echo esc_html( (string) ( $oria_page['label'] ?? '' ) ); ?></span>
	</nav>
	<div style="margin-top:1rem;max-width:52rem">
		<span class="micro"><?php echo esc_html( $oria_pname ); ?> · <?php esc_html_e( 'Filtered view', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title"><?php echo esc_html( $oria_h1 ); ?></h1>
		<?php if ( ! empty( $oria_frame['opener'] ) ) : ?>
			<p class="lede pagehead__lede" style="max-width:62ch"><?php echo esc_html( $oria_fill( (string) $oria_frame['opener'] ) ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php if ( $oria_term instanceof WP_Term ) : ?>
<section class="wrap section section--top-flush">
	<?php
	/*
	 * The facts line — the page's own numbers, written to be liftable on
	 * their own. "{count} of {total}" is the honest shape: a filtered view
	 * is a share of a category, and saying so is what keeps it from reading
	 * as a ranking.
	 */
	?>
	<div class="answer">
		<div class="answer__body">
			<p>
				<?php
				printf(
					/* translators: 1: matching count, 2: category total, 3: category name, 4: intent label. */
					esc_html__( '%1$s of the %2$s %3$s listings in the directory match this view: %4$s. The count is live, and the order below is members first, then alphabetical — never by rating.', 'oria' ),
					'<b>' . esc_html( number_format_i18n( (int) $oria_facts['count'] ) ) . '</b>',
					esc_html( number_format_i18n( (int) $oria_facts['total'] ) ),
					esc_html( strtolower( $oria_pname ) ),
					'<b>' . esc_html( strtolower( (string) ( $oria_page['label'] ?? '' ) ) ) . '</b>'
				);
				?>
			</p>
			<?php
			/*
			 * Who it suits, as a count of what these businesses publish
			 * rather than a claim about the reader. Absent until somebody
			 * has actually checked, which is rather the point of it.
			 */
			$oria_aud = function_exists( '\Oria\Core\IntentPages\audience_note' )
				? \Oria\Core\IntentPages\audience_note( $oria_page, $oria_facts )
				: null;
			?>
			<?php if ( $oria_aud ) : ?>
				<p class="answer__also">
					<?php
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
		</div>
	</div>

	<?php // The view, spelled out: what is locked on this page. ?>
	<div class="chips viewbar" aria-label="<?php esc_attr_e( 'This view', 'oria' ); ?>">
		<span class="micro viewbar__label"><?php esc_html_e( 'This view', 'oria' ); ?></span>
		<a class="chip" href="<?php echo esc_url( (string) get_term_link( $oria_term ) ); ?>"><?php echo esc_html( $oria_pname ); ?></a>
		<span class="chip chip--locked"><?php echo esc_html( (string) ( $oria_page['label'] ?? '' ) ); ?></span>
	</div>
</section>

<section class="wrap section section--top-flush">
	<div class="dir">
		<?php get_template_part( 'template-parts/directory', 'filters' ); ?>
		<div>
			<div class="dir__bar">
				<div style="flex:1;min-width:240px">
					<label class="sr-only" for="dirQ"><?php esc_html_e( 'Search', 'oria' ); ?></label>
					<input class="input" id="dirQ" type="search" placeholder="<?php printf( esc_attr__( 'Search within %s', 'oria' ), esc_attr( strtolower( $oria_h1 ) ) ); ?>">
				</div>
				<div class="dir__tools">
					<button class="btn btn--ghost btn--sm btn--plain" id="filterToggle" aria-expanded="true" aria-controls="dirFilters"><?php esc_html_e( 'Filters', 'oria' ); ?></button>
					<label class="sr-only" for="dirSort"><?php esc_html_e( 'Sort by', 'oria' ); ?></label>
					<select class="select" id="dirSort" style="width:auto">
						<option value="relevance"><?php esc_html_e( 'Members first', 'oria' ); ?></option>
						<option value="name"><?php esc_html_e( 'A–Z', 'oria' ); ?></option>
						<option value="price"><?php esc_html_e( 'Lowest price', 'oria' ); ?></option>
					</select>
				</div>
			</div>
			<h2 class="sr-only"><?php echo esc_html( $oria_h1 ); ?></h2>
			<p class="dir__count" id="dirCount"></p>
			<div class="chips" id="dirChips" style="margin-top:1rem"></div>
			<div
				class="dir__results"
				id="dirResults"
				data-cat="<?php echo esc_attr( $oria_term->slug ); ?>"
				data-intent-key="<?php echo esc_attr( $oria_filter_key ); ?>"
				data-intent-value="<?php echo esc_attr( $oria_filter_value ); ?>"
			>
				<?php
				/*
				 * The matching set, server-rendered, members first then
				 * alphabetical. The script re-renders the same set from the
				 * payload; without it this is the page.
				 */
				$oria_posts = $oria_facts['ids']
					? get_posts(
						array(
							'post_type'      => 'listing',
							'post_status'    => 'publish',
							'post__in'       => array_map( 'intval', $oria_facts['ids'] ),
							'posts_per_page' => 24,
							'orderby'        => 'title',
							'order'          => 'ASC',
						)
					)
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
				?>
			</div>
		</div>
	</div>
</section>

<?php if ( ! empty( $oria_frame['worth_knowing'] ) ) : ?>
<section class="wrap section section--top-flush">
	<h2 class="h3" style="margin-bottom:1rem"><?php esc_html_e( 'Worth knowing', 'oria' ); ?></h2>
	<div class="prose prose--intro">
		<?php foreach ( (array) $oria_frame['worth_knowing'] as $oria_para ) : ?>
			<p><?php echo esc_html( $oria_fill( (string) $oria_para ) ); ?></p>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php
/*
 * How this page is built — the transparency the original brief asks for
 * where a page could be mistaken for a ranking. Plain, short, the same
 * every time.
 */
?>
<section class="wrap section section--top-flush">
	<div class="card"><div class="card__body">
		<span class="micro"><?php esc_html_e( 'How this page is put together', 'oria' ); ?></span>
		<ul class="prose" style="margin-top:.75rem;max-width:none">
			<li><?php esc_html_e( 'The listings are the live set of practices in this category that match the view. Nothing is added by hand and nothing is left out by judgement.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Order is members first, then alphabetical. Google ratings are shown for information, labelled, and never used to select or order.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Featured placements are paid, labelled, and sit in their own band.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'A practice appears under an audience or a service only where it has published that itself. Absence means nobody has checked — never that it is unwelcome.', 'oria' ); ?></li>
		</ul>
	</div></div>
</section>

<?php
/* Nearby and next to this: the practice itself, its other visible intents, and
   the same intent elsewhere — a small mesh, never the whole site. */
$oria_near = array( array( (string) get_term_link( $oria_term ), sprintf( __( 'All %s in Perth', 'oria' ), strtolower( $oria_pname ) ) ) );
foreach ( \Oria\Core\IntentPages\visible_for( $oria_term->slug ) as $oria_sib ) {
	if ( $oria_sib['intent'] !== $oria_page['intent'] ) {
		$oria_near[] = array( \Oria\Core\IntentPages\url( $oria_sib['practice'], $oria_sib['intent'] ), (string) ( $oria_sib['frame']['h1'] ?? $oria_sib['label'] ) );
	}
}
foreach ( \Oria\Core\IntentPages\registry()['pages'] as $oria_other ) {
	if ( $oria_other['intent'] === $oria_page['intent'] && $oria_other['practice'] !== $oria_page['practice'] && \Oria\Core\IntentPages\facts( $oria_other )['publishable'] ) {
		$oria_near[] = array( \Oria\Core\IntentPages\url( $oria_other['practice'], $oria_other['intent'] ), (string) ( $oria_other['frame']['h1'] ?? $oria_other['label'] ) );
	}
}
?>
<section class="wrap section section--top-flush">
	<h2 class="micro" style="margin-bottom:1rem"><?php esc_html_e( 'Nearby and next to this', 'oria' ); ?></h2>
	<div class="chips">
		<?php foreach ( $oria_near as $oria_l ) : ?>
			<a class="pill" href="<?php echo esc_url( $oria_l[0] ); ?>"><?php echo esc_html( $oria_l[1] ); ?></a>
		<?php endforeach; ?>
	</div>
</section>

<?php
if ( ! empty( $oria_frame['faq'] ) ) {
	$oria_faqs = array();
	foreach ( (array) $oria_frame['faq'] as $oria_qa ) {
		if ( ! empty( $oria_qa['q'] ) && ! empty( $oria_qa['a'] ) ) {
			$oria_faqs[] = array( 'q' => $oria_fill( (string) $oria_qa['q'] ), 'a' => $oria_fill( (string) $oria_qa['a'] ) );
		}
	}
	if ( $oria_faqs ) {
		get_template_part(
			'template-parts/faq',
			null,
			array(
				'faqs'    => $oria_faqs,
				'heading' => sprintf( __( '%s — common questions', 'oria' ), $oria_h1 ),
			)
		);
	}
}

$oria_feat = \Oria\Theme\featured_listings( 3, $oria_term->slug );
if ( $oria_feat ) {
	?>
	<section class="wrap section section--top-flush">
		<?php get_template_part( 'template-parts/featured', 'band', array( 'posts' => $oria_feat, 'heading' => sprintf( __( 'Featured in %s', 'oria' ), $oria_pname ) ) ); ?>
	</section>
	<?php
}
endif;

get_footer();
