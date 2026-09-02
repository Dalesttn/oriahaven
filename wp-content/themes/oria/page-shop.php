<?php
/**
 * /shop/ — the wellness shop.
 *
 * One catalogue, one engine; this page is the widest view of it. Everything
 * below the hero reads the same product rows the in-content bands use, so a
 * product cannot say one thing here and another on a practice page.
 *
 * Filtering, search and sort run client-side over the rendered cards. That
 * is a deliberate ceiling: right for a catalogue of tens, wrong for one of
 * hundreds, at which point this becomes a paged query instead. The cards
 * already carry the data either approach needs.
 */

declare(strict_types=1);

get_header();

$oria_has_shop = function_exists( '\Oria\Shop\Engine\products' );
$oria_products = array();
$oria_cats     = array();

if ( $oria_has_shop ) {
	$oria_terms = get_terms( array( 'taxonomy' => \Oria\Shop\Data\TAX, 'hide_empty' => true ) );
	$oria_terms = is_wp_error( $oria_terms ) ? array() : $oria_terms;

	$oria_products = \Oria\Shop\Engine\products( wp_list_pluck( $oria_terms, 'term_id' ), 200 );

	/*
	 * Counts come from the rows actually rendered, not from term->count. A
	 * term counts every product filed under it; the grid shows what the
	 * engine returned after deduplicating by ASIN. A chip promising eleven
	 * that filters to nine is worse than a chip with no number on it.
	 */
	foreach ( $oria_products as $oria_row ) {
		$oria_slugs = (array) ( $oria_row['cat_slugs'] ?? array() );
		$oria_names = (array) ( $oria_row['cat_names'] ?? array() );
		foreach ( $oria_slugs as $oria_i => $oria_slug ) {
			$oria_slug = (string) $oria_slug;
			if ( '' === $oria_slug ) {
				continue;
			}
			if ( ! isset( $oria_cats[ $oria_slug ] ) ) {
				$oria_cats[ $oria_slug ] = array(
					'name' => (string) ( $oria_names[ $oria_i ] ?? $oria_slug ),
					'n'    => 0,
				);
			}
			++$oria_cats[ $oria_slug ]['n'];
		}
	}
	uasort(
		$oria_cats,
		static function ( array $a, array $b ): int {
			return ( $b['n'] <=> $a['n'] ) ?: strcmp( $a['name'], $b['name'] );
		}
	);

	/*
	 * The copy that heads each category, where an editor has written it.
	 *
	 * Only categories that actually have products are asked for — the loop
	 * above built the list from rendered rows — so this never fetches an
	 * intro for a shelf nobody can reach.
	 */
	foreach ( $oria_cats as $oria_slug => $oria_c ) {
		$oria_term = get_term_by( 'slug', (string) $oria_slug, \Oria\Shop\Data\TAX );
		if ( ! $oria_term instanceof WP_Term ) {
			continue;
		}
		$oria_cats[ $oria_slug ]['heading'] = trim( (string) get_term_meta( $oria_term->term_id, 'heading', true ) );
		$oria_cats[ $oria_slug ]['intro']   = trim( (string) get_term_meta( $oria_term->term_id, 'intro', true ) );

		/*
		 * A shelf with a written guide behind it links to it. Matched on the
		 * category slug -- /singing-bowls/ for singing-bowls -- so a hub page
		 * appears here the moment it is published and nothing has to be
		 * wired up per category. Categories without one show nothing.
		 */
		$oria_guide = get_page_by_path( (string) $oria_slug, OBJECT, 'page' );
		if ( $oria_guide instanceof WP_Post && 'publish' === $oria_guide->post_status ) {
			$oria_cats[ $oria_slug ]['guide'] = (string) get_permalink( $oria_guide );
		}
	}
}

$oria_total = count( $oria_products );
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Shop', 'oria' ); ?></span>
	</nav>
</section>

<section class="wrap shophero">
	<div class="shophero__copy">
		<span class="micro shophero__eyebrow"><?php esc_html_e( 'Hand-picked for your wellbeing', 'oria' ); ?></span>
		<h1 class="h1 shophero__title"><?php esc_html_e( 'Shop wellness products worth knowing about', 'oria' ); ?></h1>
		<p class="lede shophero__lede"><?php esc_html_e( 'Thoughtfully selected products for meditation, movement, relaxation, sound healing, sleep and everyday wellbeing — chosen to suit the practices in the directory.', 'oria' ); ?></p>

		<?php // Only claims the site can actually stand behind. ?>
		<p class="shophero__trust">
			<span><?php esc_html_e( 'Curated by Oria Haven', 'oria' ); ?></span>
			<span aria-hidden="true">·</span>
			<span><?php esc_html_e( 'Independently selected', 'oria' ); ?></span>
			<span aria-hidden="true">·</span>
			<span><?php esc_html_e( 'Amazon Australia', 'oria' ); ?></span>
		</p>

		<?php
		/*
		 * The disclosure sits with the promise rather than in the basement.
		 * A reader deciding whether to trust the selection should not have to
		 * scroll past the whole selection to learn how it is paid for.
		 */
		?>
		<p class="shophero__disclosure"><?php esc_html_e( 'Some products here are affiliate recommendations. If you buy through one of our links we may earn a commission, at no extra cost to you.', 'oria' ); ?></p>
	</div>
	<?php
	/*
	 * Purely decorative, and drawn as a CSS background rather than an <img>.
	 *
	 * As a tag it downloaded on phones even behind display:none — sizes="0px"
	 * governs which candidate is chosen, not whether one is fetched — which
	 * is 60KB spent on something no phone ever shows. A background inside a
	 * min-width query is simply never requested there.
	 *
	 * No alt attribute is needed because there is no image element and the
	 * container is aria-hidden: it says nothing the copy beside it does not.
	 *
	 * Photograph: Alesia Kozik / Pexels (royalty-free licence).
	 */
	?>
	<div class="shophero__art" aria-hidden="true"></div>
</section>

<section class="wrap section section--top-flush" data-shopfilter>
	<?php if ( $oria_products ) : ?>

		<div class="shoptools">
			<div class="shoptools__search">
				<label class="sr-only" for="oshop-search"><?php esc_html_e( 'Search products', 'oria' ); ?></label>
				<input
					type="search"
					id="oshop-search"
					class="input shoptools__input"
					data-shop-search
					autocomplete="off"
					placeholder="<?php esc_attr_e( 'Search singing bowls, yoga mats, massage tools…', 'oria' ); ?>">
				<button class="shoptools__clear" type="button" data-shop-clear hidden>
					<span class="sr-only"><?php esc_html_e( 'Clear search', 'oria' ); ?></span>
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<div class="shoptools__sort">
				<label class="sr-only" for="oshop-sort"><?php esc_html_e( 'Sort products', 'oria' ); ?></label>
				<?php // No "most popular": there is no popularity data, so there is no such sort. ?>
				<select id="oshop-sort" class="select select--sm" data-shop-sort>
					<option value="recommended"><?php esc_html_e( 'Recommended', 'oria' ); ?></option>
					<option value="newest"><?php esc_html_e( 'Newest', 'oria' ); ?></option>
					<option value="price-asc"><?php esc_html_e( 'Price: low to high', 'oria' ); ?></option>
					<option value="price-desc"><?php esc_html_e( 'Price: high to low', 'oria' ); ?></option>
				</select>
			</div>
		</div>

		<?php if ( count( $oria_cats ) > 1 ) : ?>
			<div class="shopchips" role="group" aria-label="<?php esc_attr_e( 'Filter by category', 'oria' ); ?>">
				<button class="fchip is-on" type="button" data-cat="">
					<?php esc_html_e( 'Everything', 'oria' ); ?>
					<span class="fchip__n"><?php echo esc_html( (string) $oria_total ); ?></span>
				</button>
				<?php foreach ( $oria_cats as $oria_slug => $oria_c ) : ?>
					<button class="fchip" type="button" data-cat="<?php echo esc_attr( (string) $oria_slug ); ?>">
						<?php echo esc_html( $oria_c['name'] ); ?>
						<span class="fchip__n"><?php echo esc_html( (string) $oria_c['n'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		/*
		 * A header for the selected category: the same shape a standalone
		 * /shop/{category}/ page will use when a category has the depth to
		 * deserve one. Rendered once per category and revealed by the chip,
		 * so choosing a shelf feels like arriving somewhere rather than
		 * shortening a list.
		 *
		 * Only categories with copy get one. A heading over a blank space
		 * would be the filler the empty field exists to avoid.
		 */
		?>
		<?php foreach ( $oria_cats as $oria_slug => $oria_c ) : ?>
			<?php if ( '' === (string) ( $oria_c['intro'] ?? '' ) ) : continue; endif; ?>
			<div class="shopcathead" data-cat-head="<?php echo esc_attr( (string) $oria_slug ); ?>" hidden>
				<div class="shopcathead__copy">
					<h2 class="h3 shopcathead__title">
						<?php echo esc_html( '' !== (string) ( $oria_c['heading'] ?? '' ) ? $oria_c['heading'] : $oria_c['name'] ); ?>
					</h2>
					<p class="shopcathead__intro"><?php echo esc_html( (string) $oria_c['intro'] ); ?></p>
					<?php if ( ! empty( $oria_c['guide'] ) ) : ?>
						<p class="shopcathead__guide">
							<a href="<?php echo esc_url( (string) $oria_c['guide'] ); ?>">
								<?php
								printf(
									/* translators: %s: category name, e.g. Singing bowls */
									esc_html__( 'Read the %s guide', 'oria' ),
									esc_html( strtolower( (string) $oria_c['name'] ) )
								);
								?>
							</a>
						</p>
					<?php endif; ?>
				</div>
				<p class="shopcathead__n">
					<?php
					printf(
						/* translators: %d: number of products in this category */
						esc_html( _n( '%d product', '%d products', (int) $oria_c['n'], 'oria' ) ),
						(int) $oria_c['n']
					);
					?>
				</p>
			</div>
		<?php endforeach; ?>

		<p class="shopcount muted" data-shop-count aria-live="polite"></p>

		<div class="prodgrid prodgrid--page" data-shop-grid>
			<?php
			foreach ( $oria_products as $oria_p ) {
				echo \Oria\Shop\Render\card( $oria_p ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</div>

		<?php // An empty shelf is an invitation, not a dead end. ?>
		<div class="dir__empty" data-shop-empty hidden>
			<h2 class="h3"><?php esc_html_e( 'Nothing matches that yet', 'oria' ); ?></h2>
			<p class="muted" style="margin-top:.5rem"><?php esc_html_e( 'We add products as we find ones worth recommending. Try another category, or clear the search to see everything.', 'oria' ); ?></p>
			<button class="btn btn--ghost btn--sm" type="button" data-shop-reset style="margin-top:1rem"><?php esc_html_e( 'Show everything', 'oria' ); ?></button>
		</div>

		<p class="shopband__disclosure"><?php echo esc_html( \Oria\Shop\Data\disclosure() ); ?></p>
		<?php \Oria\Shop\Track\impressions( $oria_products ); ?>

	<?php else : ?>
		<div class="dir__empty">
			<h2 class="h3"><?php esc_html_e( 'The shelves are being stocked', 'oria' ); ?></h2>
			<p class="muted" style="margin-top:.5rem"><?php esc_html_e( 'Hand-picked wellness products are on their way — check back soon.', 'oria' ); ?></p>
		</div>
	<?php endif; ?>
</section>

<?php
get_footer();
