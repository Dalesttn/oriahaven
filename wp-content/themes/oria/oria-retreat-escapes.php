<?php
/**
 * /retreat-escapes/ -- Retreat Escapes by Oria Haven: a retreat discovery
 * page with its own header, a photographic hero, a two-field finder,
 * destination tiles, the collection, planning guidance and a closing
 * invitation. Disclosed affiliate links to BookRetreats throughout.
 *
 * Everything shown comes from the retreat records: destinations and their
 * counts, kinds of escape, prices and the dates they were checked. Nothing
 * is manufactured to make the collection look larger.
 *
 * Served only once at least one offer is eligible (Retreats\published());
 * before that an editor sees a preview and everybody else a 404. The
 * finder is a GET form: with the query in the URL the cards that do not
 * match are rendered hidden, so it works with scripts off; with scripts it
 * filters in place and rewrites the URL (canonical stays the hub).
 *
 * @package Oria
 */

declare(strict_types=1);

use Oria\Core\Retreats as R;

$oria_css = get_template_directory() . '/assets/css/retreats.css';
wp_enqueue_style( 'oria-retreats', get_template_directory_uri() . '/assets/css/retreats.css', array(), (string) filemtime( $oria_css ) );

$oria_all  = R\active_offers();
$oria_live = R\published();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a read-only filter in the address bar.
$oria_sel_dest  = isset( R\DESTINATIONS[ (string) ( $_GET['dest'] ?? '' ) ] ) ? (string) $_GET['dest'] : '';
$oria_sel_style = isset( R\STYLES[ (string) ( $_GET['style'] ?? '' ) ] ) ? (string) $_GET['style'] : '';
// phpcs:enable

// What the records actually contain, for the finder and the tiles.
$oria_dests  = array();
$oria_styles = array();
foreach ( $oria_all as $oria_id ) {
	$oria_d = R\get( $oria_id, 'destination' );
	$oria_dests[ $oria_d ] = ( $oria_dests[ $oria_d ] ?? 0 ) + 1;
	foreach ( R\styles_of( $oria_id ) as $oria_s ) {
		$oria_styles[ $oria_s ] = ( $oria_styles[ $oria_s ] ?? 0 ) + 1;
	}
}
$oria_match = static fn( int $id ): bool =>
	( '' === $oria_sel_dest || R\get( $id, 'destination' ) === $oria_sel_dest )
	&& ( '' === $oria_sel_style || in_array( $oria_sel_style, R\styles_of( $id ), true ) );
$oria_shown = array_values( array_filter( $oria_all, $oria_match ) );

// Destination tiles: a line each, and a photograph from one of its own retreats.
$oria_tile_copy = array(
	'bali'       => __( 'Yoga, warm mornings and new connections.', 'oria' ),
	'wa'         => __( 'Country calm and space to slow down.', 'oria' ),
	'aus-east'   => __( 'Rainforest, coast and hinterland, a flight away.', 'oria' ),
	'near-perth' => __( 'A day or a weekend within easy reach of the city.', 'oria' ),
);
$oria_tile_img = array();
foreach ( $oria_all as $oria_id ) {
	$oria_d = R\get( $oria_id, 'destination' );
	if ( ! isset( $oria_tile_img[ $oria_d ] ) && has_post_thumbnail( $oria_id ) ) {
		$oria_tile_img[ $oria_d ] = $oria_id;
	}
}

// The hero photograph: a Bali retreat's own image when there is one, else the first with a picture.
$oria_hero_id = $oria_tile_img['bali'] ?? ( $oria_tile_img ? (int) reset( $oria_tile_img ) : 0 );
$oria_hero_src = $oria_hero_id ? (string) get_the_post_thumbnail_url( $oria_hero_id, 'full' ) : '';
$oria_hero_set = $oria_hero_id ? (string) wp_get_attachment_image_srcset( (int) get_post_thumbnail_id( $oria_hero_id ), 'full' ) : '';
$oria_hero_cap = $oria_hero_id ? R\place_label( $oria_hero_id ) : '';

$oria_hub = R\hub_url();
$oria_n   = count( $oria_shown );

get_header( 'retreats' );
?>

<div class="oria-retreats">

<?php if ( ! $oria_live ) : ?>
	<p class="ro-preview" role="status"><?php esc_html_e( 'Preview: this page is not public yet. It appears once at least one retreat offer is Active and complete, and Recommendations are switched on under Retreat offers → Settings.', 'oria' ); ?></p>
<?php endif; ?>

<nav class="ro-crumbs ro-wrap" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
	<span aria-hidden="true">/</span><span aria-current="page"><?php esc_html_e( 'Retreat Escapes', 'oria' ); ?></span>
</nav>

<!-- 2. Hero -->
<section class="ro-hero" aria-labelledby="roTitle">
	<?php if ( '' !== $oria_hero_src ) : ?>
		<img class="ro-hero__img" src="<?php echo esc_url( $oria_hero_src ); ?>"<?php echo '' !== $oria_hero_set ? ' srcset="' . esc_attr( $oria_hero_set ) . '" sizes="100vw"' : ''; ?> alt="" width="1600" height="900" fetchpriority="high" decoding="async">
	<?php endif; ?>
	<div class="ro-hero__shade" aria-hidden="true"></div>
	<div class="ro-wrap ro-hero__inner">
		<div class="ro-hero__text">
			<p class="ro-eyebrow ro-eyebrow--light"><?php esc_html_e( 'Somewhere new. Something for you.', 'oria' ); ?></p>
			<h1 class="ro-hero__title" id="roTitle"><?php esc_html_e( 'Wellness retreats. A world away from the everyday.', 'oria' ); ?></h1>
			<p class="ro-hero__lede"><?php esc_html_e( 'Quiet mornings. New connections. Room to breathe. Discover an escape that feels like you.', 'oria' ); ?></p>
		</div>
		<?php if ( '' !== $oria_hero_cap ) : ?>
			<p class="ro-hero__cap"><?php echo esc_html( $oria_hero_cap ); ?></p>
		<?php endif; ?>
	</div>

	<!-- 3. Finder -->
	<form class="ro-wrap ro-finder" method="get" action="<?php echo esc_url( $oria_hub ); ?>#collection" data-ro-finder aria-label="<?php esc_attr_e( 'Find a retreat', 'oria' ); ?>">
		<div class="ro-finder__field">
			<label for="ro-dest"><?php esc_html_e( 'Destination', 'oria' ); ?></label>
			<select id="ro-dest" name="dest">
				<option value=""><?php esc_html_e( 'Anywhere', 'oria' ); ?></option>
				<?php foreach ( R\DESTINATIONS as $oria_k => $oria_l ) : ?>
					<?php if ( ! empty( $oria_dests[ $oria_k ] ) ) : ?>
						<option value="<?php echo esc_attr( $oria_k ); ?>"<?php selected( $oria_sel_dest, $oria_k ); ?>><?php echo esc_html( $oria_l ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="ro-finder__field">
			<label for="ro-style"><?php esc_html_e( 'Kind of escape', 'oria' ); ?></label>
			<select id="ro-style" name="style">
				<option value=""><?php esc_html_e( 'Any', 'oria' ); ?></option>
				<?php foreach ( R\STYLES as $oria_k => $oria_l ) : ?>
					<?php if ( ! empty( $oria_styles[ $oria_k ] ) ) : ?>
						<option value="<?php echo esc_attr( $oria_k ); ?>"<?php selected( $oria_sel_style, $oria_k ); ?>><?php echo esc_html( $oria_l ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</div>
		<button class="ro-btn ro-btn--primary ro-finder__go" type="submit"><?php esc_html_e( 'Find my escape', 'oria' ); ?></button>
	</form>
</section>

<!-- 4. Trust line -->
<ul class="ro-wrap ro-trust" aria-label="<?php esc_attr_e( 'How this collection works', 'oria' ); ?>">
	<li><?php esc_html_e( 'Thoughtfully selected stays', 'oria' ); ?> <a href="#how"><?php esc_html_e( 'how we choose', 'oria' ); ?></a></li>
	<li><?php esc_html_e( 'Clear inclusions and practical details', 'oria' ); ?></li>
	<li><?php esc_html_e( 'Bookings through BookRetreats', 'oria' ); ?></li>
</ul>

<!-- 5. Destinations -->
<section class="ro-section" id="destinations" aria-labelledby="roWhereTitle">
	<div class="ro-wrap">
		<h2 class="ro-h2" id="roWhereTitle"><?php esc_html_e( 'Where will you find your pause?', 'oria' ); ?></h2>
		<ul class="ro-tiles">
			<?php foreach ( R\DESTINATIONS as $oria_k => $oria_l ) : ?>
				<?php
				$oria_c = (int) ( $oria_dests[ $oria_k ] ?? 0 );
				if ( ! $oria_c ) {
					continue; // Only destinations with a retreat to show.
				}
				$oria_tid = (int) ( $oria_tile_img[ $oria_k ] ?? 0 );
				?>
				<li class="ro-tile">
					<a class="ro-tile__link" href="<?php echo esc_url( add_query_arg( 'dest', $oria_k, $oria_hub ) . '#collection' ); ?>" data-ro-set="dest:<?php echo esc_attr( $oria_k ); ?>">
						<?php if ( $oria_tid ) : ?>
							<?php echo get_the_post_thumbnail( $oria_tid, 'large', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => sprintf( /* translators: 1: retreat, 2: destination */ __( '%1$s in %2$s', 'oria' ), get_the_title( $oria_tid ), $oria_l ), 'sizes' => '(max-width: 48rem) 100vw, 50vw' ) ); ?>
						<?php endif; ?>
						<span class="ro-tile__text">
							<span class="ro-tile__name"><?php echo esc_html( $oria_l ); ?></span>
							<span class="ro-tile__line"><?php echo esc_html( $oria_tile_copy[ $oria_k ] ?? '' ); ?></span>
							<span class="ro-tile__count"><?php echo esc_html( sprintf( _n( '%d retreat', '%d retreats', $oria_c, 'oria' ), $oria_c ) ); ?> <span aria-hidden="true">&rarr;</span></span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<!-- 6. The collection -->
<section class="ro-section ro-section--collection" id="collection" aria-labelledby="roOffersTitle" data-ro-offers>
	<div class="ro-wrap">
		<div class="ro-offers-head">
			<div>
				<h2 class="ro-h2" id="roOffersTitle"><?php esc_html_e( 'Less searching. More possibility.', 'oria' ); ?></h2>
				<p class="ro-count" data-ro-count role="status" aria-live="polite"><?php echo esc_html( sprintf( _n( 'Showing %1$d of %2$d retreats', 'Showing %1$d of %2$d retreats', $oria_n, 'oria' ), $oria_n, count( $oria_all ) ) ); ?></p>
			</div>
			<?php echo R\disclosure_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
		</div>

		<?php if ( $oria_all ) : ?>
			<div class="ro-grid" data-ro-grid>
				<?php foreach ( $oria_all as $oria_i => $oria_id ) : ?>
					<?php get_template_part( 'template-parts/retreat-card', null, array( 'id' => (int) $oria_id, 'placement' => 'hub', 'hidden' => ! $oria_match( (int) $oria_id ), 'eager' => $oria_i < 2 ) ); ?>
				<?php endforeach; ?>
			</div>
			<div class="ro-empty" data-ro-empty<?php echo $oria_n ? ' hidden' : ''; ?>>
				<p><strong><?php esc_html_e( 'No exact match in this collection yet.', 'oria' ); ?></strong> <?php esc_html_e( 'Try another destination or kind of escape, or see everything.', 'oria' ); ?></p>
				<a class="ro-btn ro-btn--secondary" href="<?php echo esc_url( $oria_hub . '#collection' ); ?>" data-ro-reset><?php esc_html_e( 'Show all retreats', 'oria' ); ?></a>
			</div>
		<?php else : ?>
			<p class="ro-empty"><?php esc_html_e( 'No retreat offers are ready to show yet.', 'oria' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<!-- 7. Planning -->
<section class="ro-section ro-section--sage" id="plan" aria-labelledby="roPlanTitle">
	<div class="ro-wrap ro-split">
		<div>
			<h2 class="ro-h2" id="roPlanTitle"><?php esc_html_e( 'Your escape. Your own pace.', 'oria' ); ?></h2>
			<p class="ro-copy"><?php esc_html_e( 'A few things worth knowing before you choose, whichever retreat you are looking at.', 'oria' ); ?></p>
			<p class="ro-copy ro-copy--small" id="how"><?php esc_html_e( 'How we choose: each retreat is read in full on the provider page, written up in our own words, and kept only while its details, dates and price can be checked. Earning a commission is not a quality award and never decides the order. We have not stayed at these retreats.', 'oria' ); ?></p>
			<p class="ro-copy ro-copy--small"><?php echo esc_html( R\settings()['disclosure'] ); ?></p>
		</div>
		<div class="ro-plan">
			<details class="ro-plan__item" open>
				<summary><?php esc_html_e( 'First retreat?', 'oria' ); ?></summary>
				<p><?php esc_html_e( 'Compare how much silence there is, how much social time, how structured the days are and how much is left free. A full programme suits some people; others want long stretches with nothing planned. Read the daily schedule, not just the headline.', 'oria' ); ?></p>
			</details>
			<details class="ro-plan__item">
				<summary><?php esc_html_e( 'What is included?', 'oria' ); ?></summary>
				<p><?php esc_html_e( 'Check the room basis (shared or private), which meals, and whether transfers and activities are in the price. Flights are almost never included, so add them, and any visa and insurance, to get the real cost of the trip.', 'oria' ); ?></p>
			</details>
			<details class="ro-plan__item">
				<summary><?php esc_html_e( 'How does booking work?', 'oria' ); ?></summary>
				<p><?php esc_html_e( 'Oria Haven helps you discover and compare. "Check dates" takes you to BookRetreats, where you see current availability and prices and complete the booking with them. Oria Haven does not process bookings or payments, and you do not need an Oria account.', 'oria' ); ?></p>
			</details>
			<details class="ro-plan__item">
				<summary><?php esc_html_e( 'Is it suitable for me?', 'oria' ); ?></summary>
				<p><?php esc_html_e( 'Ask the organiser about physical demands, accessibility, dietary needs and the programme itself, and read the cancellation terms before you pay. A retreat is a change of pace, not a treatment for a medical condition.', 'oria' ); ?></p>
			</details>
		</div>
	</div>
</section>

<!-- 8. Closing -->
<section class="ro-close" aria-labelledby="roCloseTitle">
	<div class="ro-wrap">
		<h2 class="ro-h2 ro-h2--light" id="roCloseTitle"><?php esc_html_e( 'Your next chapter can start here.', 'oria' ); ?></h2>
		<p class="ro-close__line"><?php echo esc_html( sprintf( _n( '%d retreat in the collection, read in full and kept up to date.', '%d retreats in the collection, read in full and kept up to date.', count( $oria_all ), 'oria' ), count( $oria_all ) ) ); ?></p>
		<a class="ro-btn ro-btn--cream" href="#collection" data-ro-reset><?php esc_html_e( 'Browse the collection', 'oria' ); ?></a>
		<p class="ro-close__local"><?php esc_html_e( 'Closer to home?', 'oria' ); ?> <a href="<?php echo esc_url( home_url( '/explore/perth/retreats/' ) ); ?>"><?php esc_html_e( 'Retreats and day escapes around Perth', 'oria' ); ?></a> <?php esc_html_e( 'are in the directory, booked directly with each practice.', 'oria' ); ?></p>
	</div>
</section>

</div>

<?php
// Describes what is on the page: the breadcrumb and the visible collection. No reviews, no offers markup.
$oria_items = array();
foreach ( $oria_all as $oria_i => $oria_id ) {
	$oria_items[] = array(
		'@type'    => 'ListItem',
		'position' => $oria_i + 1,
		'name'     => get_the_title( $oria_id ),
		'url'      => $oria_hub . '#ro-offer-' . (int) $oria_id,
	);
}
$oria_schema = array(
	'@context' => 'https://schema.org',
	'@graph'   => array(
		array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
				array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Retreat Escapes', 'item' => $oria_hub ),
			),
		),
		array(
			'@type'       => 'CollectionPage',
			'@id'         => $oria_hub . '#page',
			'url'         => $oria_hub,
			'name'        => 'Retreat Escapes by Oria Haven',
			'description' => 'Wellness retreats in Bali and Western Australia, compared with clear inclusions and booking details.',
			'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
			'mainEntity'  => array( '@type' => 'ItemList', 'numberOfItems' => count( $oria_items ), 'itemListElement' => $oria_items ),
		),
	),
);
?>
<script type="application/ld+json"><?php echo wp_json_encode( $oria_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>

<script>
(function () {
	"use strict";
	var box = document.querySelector("[data-ro-offers]");
	var grid = box && box.querySelector("[data-ro-grid]");
	var form = document.querySelector("[data-ro-finder]");
	if (!grid || !form) return;
	var cards = Array.prototype.slice.call(grid.querySelectorAll(".ro-card"));
	var total = cards.length;
	var count = box.querySelector("[data-ro-count]");
	var empty = box.querySelector("[data-ro-empty]");
	var dest = form.querySelector("#ro-dest");
	var style = form.querySelector("#ro-style");
	var reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
	function push(name, params) {
		window.dataLayer = window.dataLayer || [];
		var p = { event: name };
		for (var k in params) p[k] = params[k];
		window.dataLayer.push(p);
	}
	function paint(announce) {
		var d = dest.value, s = style.value, n = 0;
		cards.forEach(function (c) {
			var ok = (!d || c.getAttribute("data-ro-dest") === d) && (!s || (" " + c.getAttribute("data-ro-styles") + " ").indexOf(" " + s + " ") > -1);
			c.hidden = !ok;
			if (ok) n++;
		});
		if (count) count.textContent = "Showing " + n + " of " + total + " retreats";
		if (empty) empty.hidden = n > 0;
		var q = new URLSearchParams();
		if (d) q.set("dest", d);
		if (s) q.set("style", s);
		var qs = q.toString();
		try { history.replaceState(null, "", location.pathname + (qs ? "?" + qs : "") + "#collection"); } catch (e) {}
		if (announce) {
			push("retreat_finder_submit", { retreat_destination: d || "any", retreat_style: s || "any", results_count: n });
			if (!n) push("retreat_zero_results", { retreat_destination: d || "any", retreat_style: s || "any" });
		}
	}
	form.addEventListener("submit", function (e) {
		e.preventDefault();
		paint(true);
		box.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
	});
	document.querySelectorAll("[data-ro-set]").forEach(function (a) {
		a.addEventListener("click", function (e) {
			var kv = a.getAttribute("data-ro-set").split(":");
			e.preventDefault();
			if (kv[0] === "dest") dest.value = kv[1];
			push("retreat_destination_select", { retreat_destination: kv[1] });
			paint(true);
			box.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
		});
	});
	document.querySelectorAll("[data-ro-reset]").forEach(function (a) {
		a.addEventListener("click", function (e) {
			e.preventDefault();
			dest.value = ""; style.value = "";
			paint(false);
			box.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
		});
	});
	grid.addEventListener("toggle", function (e) {
		var d = e.target;
		if (d && d.hasAttribute && d.hasAttribute("data-ro-look") && d.open) push("retreat_detail_open", { offer_id: Number(d.getAttribute("data-ro-look")) });
	}, true);
})();
</script>

<?php
get_footer();
