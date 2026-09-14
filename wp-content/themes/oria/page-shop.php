<?php
/**
 * /shop/ — the wellness shop, and /shop/{category}/ — one shelf of it.
 *
 * One catalogue, one engine; this page is the widest view of it. Everything
 * below the hero reads the same product rows the in-content bands use, so a
 * product cannot say one thing here and another on a practice page.
 *
 * The page is arranged the way somebody shops rather than the way the
 * database is arranged: what do you want (intentions), one thing we would
 * hand you first (the featured pick), then the full shelf with its tools,
 * then shelves assembled around a purpose, a finder for anyone still
 * undecided, and the way back into the directory -- because a product is
 * only ever a companion to a practice here.
 *
 * Filtering, search and sort run client-side over the rendered cards. That
 * is a deliberate ceiling: right for a catalogue of tens, wrong for one of
 * hundreds, at which point this becomes a paged query instead. The cards
 * already carry the data either approach needs.
 */

declare(strict_types=1);

use Oria\Shop\Data;
use Oria\Shop\Engine;
use Oria\Shop\Pages;
use Oria\Shop\Render;

get_header();

$oria_has_shop = function_exists( '\Oria\Shop\Engine\products' );
$oria_products = array();
$oria_cats     = array();
$oria_intents  = array();
$oria_landing  = null; // The term a /shop/{category}/ address names.

if ( $oria_has_shop ) {
	$oria_landing = function_exists( '\Oria\Shop\Pages\category' ) ? Pages\category() : null;

	$oria_terms = get_terms( array( 'taxonomy' => Data\TAX, 'hide_empty' => true ) );
	$oria_terms = is_wp_error( $oria_terms ) ? array() : $oria_terms;

	$oria_products = Engine\products( wp_list_pluck( $oria_terms, 'term_id' ), 200 );

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
		// An intention tile only appears when something answers to it.
		foreach ( (array) ( $oria_row['intents'] ?? array() ) as $oria_in ) {
			$oria_intents[ $oria_in ] = ( $oria_intents[ $oria_in ] ?? 0 ) + 1;
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
		$oria_term = get_term_by( 'slug', (string) $oria_slug, Data\TAX );
		if ( ! $oria_term instanceof WP_Term ) {
			continue;
		}
		$oria_cats[ $oria_slug ]['heading'] = trim( (string) get_term_meta( $oria_term->term_id, 'heading', true ) );
		$oria_cats[ $oria_slug ]['intro']   = trim( (string) get_term_meta( $oria_term->term_id, 'intro', true ) );
		$oria_cats[ $oria_slug ]['url']     = function_exists( '\Oria\Shop\Pages\category_url' ) ? Pages\category_url( $oria_term ) : '';

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

	if ( function_exists( '\Oria\Shop\Pages\register_list' ) ) {
		Pages\register_list( $oria_products );
	}
}

$oria_total    = count( $oria_products );
$oria_featured = $oria_products ? Engine\featured_row( $oria_products ) : null;
$oria_shop_url = function_exists( '\Oria\Shop\Pages\shop_url' ) ? Pages\shop_url() : home_url( '/shop/' );

// Hero copy: the category's own on a category address, the shop's otherwise.
$oria_h1   = $oria_landing ? Pages\heading( $oria_landing ) : __( 'Shop wellness products worth knowing about', 'oria' );
$oria_lede = $oria_landing
	? trim( (string) get_term_meta( $oria_landing->term_id, 'intro', true ) )
	: __( 'Thoughtfully selected products for meditation, movement, relaxation, sound healing, sleep and everyday wellbeing — chosen to suit the practices in the directory.', 'oria' );
if ( '' === $oria_lede && $oria_landing ) {
	/* translators: %s: category name */
	$oria_lede = sprintf( __( 'Our %s, chosen to suit the practices in the directory.', 'oria' ), strtolower( $oria_landing->name ) );
}
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<?php if ( $oria_landing ) : ?>
			<a href="<?php echo esc_url( $oria_shop_url ); ?>"><?php esc_html_e( 'Shop', 'oria' ); ?></a>
			<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_landing->name ); ?></span>
		<?php else : ?>
			<span><?php esc_html_e( 'Shop', 'oria' ); ?></span>
		<?php endif; ?>
	</nav>
</section>

<section class="wrap shophero">
	<div class="shophero__copy">
		<span class="micro shophero__eyebrow"><?php echo $oria_landing ? esc_html__( 'Oria Haven Shop', 'oria' ) : esc_html__( 'Hand-picked for your wellbeing', 'oria' ); ?></span>
		<h1 class="h1 shophero__title"><?php echo esc_html( $oria_h1 ); ?></h1>
		<p class="lede shophero__lede"><?php echo esc_html( $oria_lede ); ?></p>

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

<?php if ( ! $oria_products ) : ?>
	<section class="wrap section section--top-flush">
		<div class="dir__empty">
			<h2 class="h3"><?php esc_html_e( 'We’re curating this collection', 'oria' ); ?></h2>
			<p class="muted" style="margin-top:.5rem"><?php esc_html_e( 'Hand-picked wellness products are on their way — check back soon.', 'oria' ); ?></p>
		</div>
	</section>
	<?php
	get_footer();
	return;
endif;
?>

<div data-shopfilter data-shop-initial-cat="<?php echo esc_attr( $oria_landing ? $oria_landing->slug : '' ); ?>">

<?php
/*
 * Shop by intention.
 *
 * The first question is not "which category" but "what for". Each tile is
 * a filter over the grid below, so the page never leaves; the count says
 * the tile leads somewhere. Buttons rather than links: with scripting off
 * the whole shelf is already on the page, and a link to a filter that
 * cannot run is a dead link.
 */
$oria_tiles = array();
foreach ( Data\INTENTS as $oria_slug => $oria_in ) {
	if ( ! empty( $oria_intents[ $oria_slug ] ) ) {
		$oria_tiles[ $oria_slug ] = $oria_in + array( 'n' => (int) $oria_intents[ $oria_slug ] );
	}
}
?>
<?php if ( count( $oria_tiles ) > 1 && ! $oria_landing ) : ?>
	<section class="wrap section section--top-flush shopintents">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'Start with what you want', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'Shop by intention', 'oria' ); ?></h2>
			</div>
		</div>
		<div class="shopintents__grid" role="group" aria-label="<?php esc_attr_e( 'Shop by intention', 'oria' ); ?>">
			<?php foreach ( $oria_tiles as $oria_slug => $oria_in ) : ?>
				<button class="intile reveal" type="button" data-intent="<?php echo esc_attr( (string) $oria_slug ); ?>" aria-pressed="false">
					<span class="intile__mark intile__mark--<?php echo esc_attr( (string) $oria_slug ); ?>" aria-hidden="true"></span>
					<span class="intile__label"><?php echo esc_html( $oria_in['label'] ); ?></span>
					<span class="intile__line"><?php echo esc_html( $oria_in['line'] ); ?></span>
					<span class="intile__n">
						<?php
						printf(
							/* translators: %d: number of products */
							esc_html( _n( '%d product', '%d products', $oria_in['n'], 'oria' ) ),
							(int) $oria_in['n']
						);
						?>
					</span>
				</button>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
/*
 * The featured pick: one product, said plainly, with the reason. It reads
 * from the same row as its card, so the shelf and the spotlight agree.
 */
?>
<?php if ( $oria_featured && ! $oria_landing ) : ?>
	<section class="wrap section section--top-flush">
		<article class="shopfeat reveal">
			<div class="shopfeat__media">
				<?php if ( ! empty( $oria_featured['image'] ) ) : ?>
					<img src="<?php echo esc_url( (string) $oria_featured['image'] ); ?>" alt="<?php echo esc_attr( (string) $oria_featured['title'] ); ?>" loading="lazy" width="600" height="450">
				<?php endif; ?>
			</div>
			<div class="shopfeat__body">
				<span class="micro shopfeat__eyebrow"><?php esc_html_e( 'Oria Haven pick', 'oria' ); ?></span>
				<h2 class="h2 shopfeat__title"><?php echo esc_html( (string) $oria_featured['title'] ); ?></h2>
				<?php if ( ! empty( $oria_featured['brand'] ) ) : ?>
					<p class="shopfeat__brand"><?php echo esc_html( (string) $oria_featured['brand'] ); ?> · <?php echo esc_html( (string) $oria_featured['category'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $oria_featured['note'] ) ) : ?>
					<p class="shopfeat__why"><?php echo esc_html( (string) $oria_featured['note'] ); ?></p>
				<?php elseif ( ! empty( $oria_featured['blurb'] ) ) : ?>
					<p class="shopfeat__why"><?php echo esc_html( (string) $oria_featured['blurb'] ); ?></p>
				<?php endif; ?>
				<div class="shopfeat__foot">
					<span class="prodcard__price<?php echo '' === (string) $oria_featured['price'] ? ' prodcard__price--none' : ''; ?>">
						<?php
						if ( '' !== (string) $oria_featured['price'] ) {
							/* translators: %s: approximate price */
							echo esc_html( sprintf( __( 'Approx. %s', 'oria' ), (string) $oria_featured['price'] ) );
						} else {
							esc_html_e( 'Check current price on Amazon', 'oria' );
						}
						?>
					</span>
					<a class="btn btn--dark" href="<?php echo esc_url( (string) $oria_featured['url'] ); ?>" target="_blank" rel="sponsored nofollow noopener" data-oshop-click="<?php echo esc_attr( (string) $oria_featured['id'] ); ?>" data-oshop-place="featured"><?php esc_html_e( 'View on Amazon', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				</div>
			</div>
		</article>
	</section>
<?php endif; ?>

<section class="wrap section section--top-flush" id="products">
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
	 * A header for the selected category: the same shape the standalone
	 * /shop/{category}/ address uses, since it is the same template.
	 * Rendered once per category and revealed by the chip, so choosing a
	 * shelf feels like arriving somewhere rather than shortening a list.
	 *
	 * Only categories with copy get one. A heading over a blank space
	 * would be the filler the empty field exists to avoid. On the category
	 * address the hero already carries this copy, so the band stays hidden.
	 */
	?>
	<?php foreach ( $oria_cats as $oria_slug => $oria_c ) : ?>
		<?php if ( '' === (string) ( $oria_c['intro'] ?? '' ) || $oria_landing ) : continue; endif; ?>
		<div class="shopcathead" data-cat-head="<?php echo esc_attr( (string) $oria_slug ); ?>" hidden>
			<div class="shopcathead__copy">
				<h2 class="h3 shopcathead__title">
					<?php echo esc_html( '' !== (string) ( $oria_c['heading'] ?? '' ) ? $oria_c['heading'] : $oria_c['name'] ); ?>
				</h2>
				<p class="shopcathead__intro"><?php echo esc_html( (string) $oria_c['intro'] ); ?></p>
				<p class="shopcathead__guide">
					<?php if ( ! empty( $oria_c['guide'] ) ) : ?>
						<a href="<?php echo esc_url( (string) $oria_c['guide'] ); ?>">
							<?php
							printf(
								/* translators: %s: category name, e.g. Singing bowls */
								esc_html__( 'Read the %s guide', 'oria' ),
								esc_html( strtolower( (string) $oria_c['name'] ) )
							);
							?>
						</a>
					<?php endif; ?>
					<?php if ( ! empty( $oria_c['url'] ) ) : ?>
						<a href="<?php echo esc_url( (string) $oria_c['url'] ); ?>"><?php esc_html_e( 'Open this shelf on its own page', 'oria' ); ?></a>
					<?php endif; ?>
				</p>
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
			echo Render\card( $oria_p ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
	</div>

	<?php // An empty shelf is an invitation, not a dead end. ?>
	<div class="dir__empty" data-shop-empty hidden>
		<h2 class="h3"><?php esc_html_e( 'We’re curating this collection', 'oria' ); ?></h2>
		<p class="muted" style="margin-top:.5rem"><?php esc_html_e( 'Nothing matches that combination yet. We add products as we find ones worth recommending — try another intention, or clear the filters to see everything.', 'oria' ); ?></p>
		<button class="btn btn--ghost btn--sm" type="button" data-shop-reset style="margin-top:1rem"><?php esc_html_e( 'Show everything', 'oria' ); ?></button>
	</div>
</section>

<?php
/*
 * Curated shelves. Each is a purpose with a few products beside it. The
 * editor's ticks lead; the categories fill; a shelf with fewer than two
 * things on it is not a shelf and is not drawn.
 */
?>
<?php if ( ! $oria_landing ) : ?>
	<?php foreach ( Data\COLLECTIONS as $oria_cslug => $oria_col ) : ?>
		<?php
		$oria_rows = Engine\collection_rows( (string) $oria_cslug, $oria_products, 4 );
		if ( count( $oria_rows ) < 2 ) {
			continue;
		}
		?>
		<section class="wrap section section--top-flush shopshelf" data-shop-shelf="<?php echo esc_attr( (string) $oria_cslug ); ?>">
			<div class="sec-head reveal">
				<div class="sec-head__text">
					<span class="micro"><?php esc_html_e( 'A collection', 'oria' ); ?></span>
					<h2 class="h2"><?php echo esc_html( $oria_col['label'] ); ?></h2>
					<p class="shopshelf__line"><?php echo esc_html( $oria_col['line'] ); ?></p>
				</div>
				<?php if ( ! empty( $oria_tiles[ $oria_col['intent'] ] ) ) : ?>
					<button class="btn btn--ghost" type="button" data-shop-intent-go="<?php echo esc_attr( (string) $oria_col['intent'] ); ?>" data-shop-collection="<?php echo esc_attr( (string) $oria_cslug ); ?>"><?php esc_html_e( 'See everything for this', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				<?php endif; ?>
			</div>
			<div class="prodgrid prodgrid--shelf">
				<?php
				foreach ( $oria_rows as $oria_p ) {
					echo Render\card( $oria_p ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</div>
		</section>
	<?php endforeach; ?>
<?php endif; ?>

<?php
/*
 * The finder. Three questions, then the grid above filtered to the answer.
 * A plain form: submit runs the filter in script; with scripting off it
 * submits to the same page with the answers in the query string, which the
 * script reads on load. Either way the reader ends up at the products.
 */
?>
<?php if ( count( $oria_tiles ) > 1 ) : ?>
	<section class="wrap section section--top-flush">
		<form class="shopfinder reveal" data-shop-finder action="<?php echo esc_url( $oria_shop_url ); ?>#products" method="get">
			<div class="shopfinder__head">
				<span class="micro"><?php esc_html_e( 'Not sure where to start?', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'Find the right product', 'oria' ); ?></h2>
				<p class="muted"><?php esc_html_e( 'Three quick questions and we will narrow the shelf for you.', 'oria' ); ?></p>
			</div>
			<div class="shopfinder__fields">
				<label class="shopfinder__field">
					<span><?php esc_html_e( 'I want to', 'oria' ); ?></span>
					<select class="select" name="intent">
						<option value=""><?php esc_html_e( 'Anything', 'oria' ); ?></option>
						<?php foreach ( $oria_tiles as $oria_slug => $oria_in ) : ?>
							<option value="<?php echo esc_attr( (string) $oria_slug ); ?>"><?php echo esc_html( strtolower( $oria_in['label'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="shopfinder__field">
					<span><?php esc_html_e( 'I am', 'oria' ); ?></span>
					<select class="select" name="best">
						<option value=""><?php esc_html_e( 'Not fussed', 'oria' ); ?></option>
						<option value="beginners"><?php esc_html_e( 'new to this', 'oria' ); ?></option>
						<option value="everyday"><?php esc_html_e( 'after something for every day', 'oria' ); ?></option>
						<option value="practitioner"><?php esc_html_e( 'a practitioner', 'oria' ); ?></option>
						<option value="gift"><?php esc_html_e( 'buying a gift', 'oria' ); ?></option>
					</select>
				</label>
				<label class="shopfinder__field">
					<span><?php esc_html_e( 'Budget', 'oria' ); ?></span>
					<select class="select" name="band">
						<option value=""><?php esc_html_e( 'Any', 'oria' ); ?></option>
						<option value="under-50"><?php esc_html_e( 'Under $50', 'oria' ); ?></option>
						<option value="50-100"><?php esc_html_e( '$50 to $100', 'oria' ); ?></option>
						<option value="100-250"><?php esc_html_e( '$100 to $250', 'oria' ); ?></option>
						<option value="250-plus"><?php esc_html_e( 'Over $250', 'oria' ); ?></option>
					</select>
				</label>
				<button class="btn btn--dark" type="submit"><?php esc_html_e( 'Show me', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</div>
		</form>
	</section>
<?php endif; ?>

<?php
/*
 * Why shop through Oria Haven. Three things that are true, none of which
 * is a promise about what a product will do for anybody.
 */
?>
<section class="wrap section section--top-flush">
	<div class="shopwhy reveal">
		<div class="shopwhy__head">
			<span class="micro"><?php esc_html_e( 'How this shop works', 'oria' ); ?></span>
			<h2 class="h2"><?php esc_html_e( 'Why shop through Oria Haven', 'oria' ); ?></h2>
		</div>
		<ul class="shopwhy__list">
			<li>
				<strong><?php esc_html_e( 'Chosen by people, not by a feed', 'oria' ); ?></strong>
				<span><?php esc_html_e( 'Every product here was picked by us, and each one says why it is on the shelf.', 'oria' ); ?></span>
			</li>
			<li>
				<strong><?php esc_html_e( 'Matched to real practices', 'oria' ); ?></strong>
				<span><?php esc_html_e( 'The shop grew out of the directory. A bowl sits beside the sound baths, a mat beside the yoga studios.', 'oria' ); ?></span>
			</li>
			<li>
				<strong><?php esc_html_e( 'Costs you nothing extra', 'oria' ); ?></strong>
				<span><?php esc_html_e( 'You buy on Amazon Australia at Amazon’s price. If you buy through our link we may earn a small commission, which helps keep the directory free.', 'oria' ); ?></span>
			</li>
		</ul>
	</div>
</section>

<?php
/*
 * Learn more: journal posts the engine can already map products to.
 * A guide about singing bowls next to the singing bowls -- and nothing at
 * all when there is no such guide yet.
 */
$oria_reads = Engine\learn_more( 3 );
?>
<?php if ( count( $oria_reads ) >= 2 ) : ?>
	<section class="wrap section section--top-flush">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'Read before you buy', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'Learn more', 'oria' ); ?></h2>
			</div>
			<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'All guides', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		</div>
		<div class="grid grid-3">
			<?php foreach ( $oria_reads as $oria_post ) : ?>
				<a class="article reveal" href="<?php echo esc_url( (string) get_permalink( $oria_post ) ); ?>">
					<?php if ( has_post_thumbnail( $oria_post ) ) : ?>
						<div class="article__img"><?php echo get_the_post_thumbnail( $oria_post, 'oria-card', array( 'loading' => 'lazy' ) ); ?></div>
					<?php endif; ?>
					<div class="article__meta"><?php echo \Oria\Theme\article_meta( $oria_post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<h3 class="article__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_post ) ); ?></h3>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
/*
 * Related experiences: the practices the shelf goes with, as links into
 * the directory. Drawn from the same category map the cards use, and only
 * for practices that actually hold listings.
 */
$oria_exps = array();
foreach ( $oria_cats as $oria_slug => $oria_c ) {
	if ( $oria_landing && $oria_landing->slug !== (string) $oria_slug ) {
		continue;
	}
	foreach ( Engine\practices_for( array( (string) $oria_slug ), 3 ) as $oria_pr ) {
		$oria_exps[ $oria_pr['url'] ] = $oria_pr['name'];
	}
}
?>
<?php if ( $oria_exps ) : ?>
	<section class="wrap section section--top-flush">
		<div class="shopexp reveal">
			<div class="shopexp__head">
				<span class="micro"><?php esc_html_e( 'From the shelf to the room', 'oria' ); ?></span>
				<h2 class="h3"><?php esc_html_e( 'Experience it in person', 'oria' ); ?></h2>
				<p class="muted"><?php esc_html_e( 'Everything here goes with a practice you can find in the directory. Try the real thing first, or alongside.', 'oria' ); ?></p>
			</div>
			<div class="shopexp__links">
				<?php foreach ( $oria_exps as $oria_url => $oria_name ) : ?>
					<a class="fchip" href="<?php echo esc_url( (string) $oria_url ); ?>" data-oshop-exp><?php echo esc_html( $oria_name ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="wrap section section--top-flush">
	<p class="shopband__disclosure"><?php echo esc_html( Data\disclosure() ); ?></p>
	<?php \Oria\Shop\Track\impressions( $oria_products ); ?>
</section>

<?php
/*
 * Quick view. One dialog for the whole page, filled from the card that
 * opened it: the card already carries everything the dialog shows, so
 * nothing is fetched and nothing can disagree with the card.
 */
?>
<dialog class="shopqv" data-shop-qv aria-labelledby="shopqv-title">
	<div class="shopqv__inner">
		<button class="shopqv__close" type="button" data-shop-qv-close aria-label="<?php esc_attr_e( 'Close', 'oria' ); ?>">&times;</button>
		<div class="shopqv__media"><img src="" alt="" data-qv-img hidden></div>
		<div class="shopqv__body">
			<span class="micro prodcard__cat" data-qv-cat></span>
			<h2 class="h3 shopqv__title" id="shopqv-title" data-qv-name></h2>
			<p class="prodcard__brand" data-qv-brand hidden></p>
			<span class="prodcard__tag" data-qv-tag hidden></span>
			<div class="shopqv__why" data-qv-why hidden>
				<strong><?php esc_html_e( 'Why we picked it', 'oria' ); ?></strong>
				<p data-qv-note></p>
			</div>
			<p class="prodcard__goes" data-qv-goes hidden><span><?php esc_html_e( 'Goes well with', 'oria' ); ?></span> <span data-qv-goes-links></span></p>
			<div class="shopqv__foot">
				<span class="prodcard__price" data-qv-price></span>
				<a class="btn btn--dark" href="#" target="_blank" rel="sponsored nofollow noopener" data-qv-buy data-oshop-place="quickview"><?php esc_html_e( 'View on Amazon', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			</div>
			<p class="shophero__disclosure"><?php esc_html_e( 'Affiliate link. Same price for you; a small commission for us.', 'oria' ); ?></p>
		</div>
	</div>
</dialog>

</div>

<?php
get_footer();
