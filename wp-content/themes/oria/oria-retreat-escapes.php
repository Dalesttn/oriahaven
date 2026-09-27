<?php
/**
 * /retreat-escapes/ -- comparing retreat escapes, from a day near Perth to
 * a longer stay, with disclosed affiliate links to BookRetreats.
 *
 * Not the Perth retreat archive and not the Best Of guide: those keep
 * their own addresses and their own job (local practices, editorial
 * picks). This page links to both and never lists local practices itself.
 *
 * It is only served once at least one offer is eligible
 * (Retreats\published()); before that an editor sees a preview and
 * everybody else a 404. Filters work in the browser over server-rendered
 * cards, so the page never creates an indexable combination.
 *
 * @package Oria
 */

declare(strict_types=1);

use Oria\Core\Retreats as R;

$oria_css = get_template_directory() . '/assets/css/retreats.css';
wp_enqueue_style( 'oria-retreats', get_template_directory_uri() . '/assets/css/retreats.css', array(), (string) filemtime( $oria_css ) );

$oria_all  = R\active_offers();
$oria_live = R\published();

// Which destinations and lengths actually have something.
$oria_dests = array();
$oria_lens  = array();
foreach ( $oria_all as $oria_id ) {
	$oria_dests[ R\get( $oria_id, 'destination' ) ] = ( $oria_dests[ R\get( $oria_id, 'destination' ) ] ?? 0 ) + 1;
	$oria_lens[ R\get( $oria_id, 'length' ) ]       = ( $oria_lens[ R\get( $oria_id, 'length' ) ] ?? 0 ) + 1;
}

// A destination without offers may point at an existing local guide -- labelled a guide -- or not appear at all.
$oria_guides = array(
	'near-perth' => array( home_url( '/explore/perth/retreats/' ), __( 'Retreats around Perth', 'oria' ) ),
	'wa'         => array( home_url( '/area/margaret-river/' ), __( 'Wellness in Margaret River', 'oria' ) ),
);
$oria_blurbs = array(
	'near-perth' => __( 'A day or a weekend within easy reach, from the hills to the coast.', 'oria' ),
	'wa'         => __( 'Further afield in Western Australia, with more time to settle in.', 'oria' ),
	'bali'       => __( 'A longer escape, where flights and transfers are part of the plan.', 'oria' ),
);

get_header();
?>

<div class="oria-retreats">

<?php if ( ! $oria_live ) : ?>
	<p class="ro-preview" role="status"><?php esc_html_e( 'Preview: this page is not public yet. It appears once at least one retreat offer is Active and complete, and Recommendations are switched on under Retreat offers → Settings.', 'oria' ); ?></p>
<?php endif; ?>

<!-- Hero -->
<section class="ro-hero" aria-labelledby="roTitle">
	<div class="ro-wrap">
		<nav class="crumbs ro-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span><span><?php esc_html_e( 'Retreat escapes', 'oria' ); ?></span>
		</nav>
		<p class="ro-eyebrow"><?php esc_html_e( 'Retreat escapes', 'oria' ); ?></p>
		<h1 class="ro-hero__title" id="roTitle"><?php esc_html_e( 'Find a retreat that fits the time you have.', 'oria' ); ?></h1>
		<p class="ro-hero__lede"><?php esc_html_e( 'From a day near Perth to a longer escape, compare the setting, pace and practical details before you choose.', 'oria' ); ?></p>
		<div class="ro-hero__acts">
			<a class="ro-btn ro-btn--primary" href="#offers"><?php esc_html_e( 'Explore retreats', 'oria' ); ?></a>
			<a class="ro-btn ro-btn--secondary" href="<?php echo esc_url( home_url( '/explore/perth/retreats/' ) ); ?>"><?php esc_html_e( 'Day retreats near Perth', 'oria' ); ?></a>
		</div>
	</div>
</section>

<!-- Where -->
<section class="ro-section ro-section--tight" aria-labelledby="roWhereTitle">
	<div class="ro-wrap">
		<h2 class="ro-h2" id="roWhereTitle"><?php esc_html_e( 'Where would you like to go?', 'oria' ); ?></h2>
		<ul class="ro-dests">
			<?php foreach ( R\DESTINATIONS as $oria_k => $oria_label ) : ?>
				<?php
				$oria_n     = (int) ( $oria_dests[ $oria_k ] ?? 0 );
				$oria_guide = $oria_guides[ $oria_k ] ?? null;
				if ( ! $oria_n && ! $oria_guide ) {
					continue;
				}
				?>
				<li class="ro-dest">
					<p class="ro-dest__name"><?php echo esc_html( $oria_label ); ?></p>
					<p class="ro-dest__line"><?php echo esc_html( $oria_blurbs[ $oria_k ] ?? '' ); ?></p>
					<?php if ( $oria_n ) : ?>
						<a class="ro-dest__go" href="<?php echo esc_url( add_query_arg( 'dest', $oria_k ) ); ?>#offers" data-ro-set="dest:<?php echo esc_attr( $oria_k ); ?>">
							<?php echo esc_html( sprintf( _n( 'See %d retreat', 'See %d retreats', $oria_n, 'oria' ), $oria_n ) ); ?> <span aria-hidden="true">&rarr;</span>
						</a>
					<?php else : ?>
						<a class="ro-dest__go" href="<?php echo esc_url( $oria_guide[0] ); ?>">
							<?php echo esc_html( sprintf( /* translators: %s: guide name */ __( 'Guide: %s', 'oria' ), $oria_guide[1] ) ); ?> <span aria-hidden="true">&rarr;</span>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<!-- The offers -->
<section class="ro-section" id="offers" aria-labelledby="roOffersTitle" data-ro-offers>
	<div class="ro-wrap">
		<div class="ro-offers-head">
			<h2 class="ro-h2" id="roOffersTitle"><?php esc_html_e( 'Retreats to compare', 'oria' ); ?></h2>
			<p class="ro-count" data-ro-count role="status" aria-live="polite"><?php echo esc_html( sprintf( _n( '%d retreat', '%d retreats', count( $oria_all ), 'oria' ), count( $oria_all ) ) ); ?></p>
		</div>
		<?php echo R\disclosure_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>

		<?php if ( count( $oria_dests ) > 1 || count( $oria_lens ) > 1 ) : ?>
			<div class="ro-filters" data-ro-filters>
				<?php if ( count( $oria_dests ) > 1 ) : ?>
					<div class="ro-filter" role="group" aria-label="<?php esc_attr_e( 'Destination', 'oria' ); ?>">
						<button type="button" class="ro-chip" aria-pressed="true" data-ro-f="dest" data-ro-v=""><?php esc_html_e( 'Anywhere', 'oria' ); ?></button>
						<?php foreach ( R\DESTINATIONS as $oria_k => $oria_label ) : ?>
							<?php if ( ! empty( $oria_dests[ $oria_k ] ) ) : ?>
								<button type="button" class="ro-chip" aria-pressed="false" data-ro-f="dest" data-ro-v="<?php echo esc_attr( $oria_k ); ?>"><?php echo esc_html( $oria_label ); ?></button>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( count( $oria_lens ) > 1 ) : ?>
					<div class="ro-filter" role="group" aria-label="<?php esc_attr_e( 'How long', 'oria' ); ?>">
						<button type="button" class="ro-chip" aria-pressed="true" data-ro-f="len" data-ro-v=""><?php esc_html_e( 'Any length', 'oria' ); ?></button>
						<?php foreach ( R\LENGTHS as $oria_k => $oria_label ) : ?>
							<?php if ( ! empty( $oria_lens[ $oria_k ] ) ) : ?>
								<button type="button" class="ro-chip" aria-pressed="false" data-ro-f="len" data-ro-v="<?php echo esc_attr( $oria_k ); ?>"><?php echo esc_html( $oria_label ); ?></button>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $oria_all ) : ?>
			<div class="ro-grid" data-ro-grid>
				<?php foreach ( $oria_all as $oria_id ) : ?>
					<?php echo R\card( (int) $oria_id, 'hub' ); // phpcs:ignore WordPress.Security.EscapeOutput -- template part escapes. ?>
				<?php endforeach; ?>
			</div>
			<p class="ro-empty" data-ro-empty hidden><?php esc_html_e( 'Nothing matches that combination yet. Try another length or destination.', 'oria' ); ?></p>
		<?php else : ?>
			<p class="ro-empty"><?php esc_html_e( 'No retreat offers are ready to show yet.', 'oria' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<!-- How to choose -->
<section class="ro-section ro-section--sage" aria-labelledby="roChooseTitle">
	<div class="ro-wrap ro-split">
		<div>
			<h2 class="ro-h2" id="roChooseTitle"><?php esc_html_e( 'Choosing a retreat', 'oria' ); ?></h2>
			<p class="ro-copy"><?php esc_html_e( 'A few questions worth answering before you book, whichever retreat you are looking at.', 'oria' ); ?></p>
		</div>
		<ul class="ro-choose">
			<li><b><?php esc_html_e( 'How much time do you have?', 'oria' ); ?></b> <?php esc_html_e( 'A day retreat asks for nothing but the day. A longer stay needs travel, and time to settle in before it feels restful.', 'oria' ); ?></li>
			<li><b><?php esc_html_e( 'What pace suits you?', 'oria' ); ?></b> <?php esc_html_e( 'Some programmes are full from morning to night; others leave long stretches free. Read the daily schedule, not just the headline.', 'oria' ); ?></li>
			<li><b><?php esc_html_e( 'What is actually included?', 'oria' ); ?></b> <?php esc_html_e( 'Check meals, sessions, the room basis and transfers. Flights are rarely part of the price.', 'oria' ); ?></li>
			<li><b><?php esc_html_e( 'What are the booking terms?', 'oria' ); ?></b> <?php esc_html_e( 'Deposits, cancellation and changes are set by the organiser and the booking provider. Read them before you pay.', 'oria' ); ?></li>
			<li><b><?php esc_html_e( 'Anything about your health?', 'oria' ); ?></b> <?php esc_html_e( 'Talk it through with the organiser, and your own practitioner if needed, before you book.', 'oria' ); ?></li>
		</ul>
	</div>
</section>

<!-- Closer to home -->
<section class="ro-section" aria-labelledby="roLocalTitle">
	<div class="ro-wrap">
		<h2 class="ro-h2" id="roLocalTitle"><?php esc_html_e( 'Closer to home', 'oria' ); ?></h2>
		<p class="ro-copy"><?php esc_html_e( 'Local retreats and practices, booked directly with them. These links are not affiliate links.', 'oria' ); ?></p>
		<ul class="ro-local">
			<li><a href="<?php echo esc_url( home_url( '/explore/perth/retreats/' ) ); ?>"><b><?php esc_html_e( 'Retreats and day escapes around Perth', 'oria' ); ?></b><span><?php esc_html_e( 'The local directory, with each practice\'s own contact details.', 'oria' ); ?></span></a></li>
			<li><a href="<?php echo esc_url( home_url( '/best/wellness-retreats-perth/' ) ); ?>"><b><?php esc_html_e( 'The best wellness retreats near Perth', 'oria' ); ?></b><span><?php esc_html_e( 'Our editorial guide to local retreats.', 'oria' ); ?></span></a></li>
			<li><a href="<?php echo esc_url( home_url( '/journeys/' ) ); ?>"><b><?php esc_html_e( 'Wellness Journeys', 'oria' ); ?></b><span><?php esc_html_e( 'Ideas for trying something new around Perth.', 'oria' ); ?></span></a></li>
		</ul>
	</div>
</section>

<!-- How booking works -->
<section class="ro-section ro-section--rule" aria-labelledby="roHowTitle">
	<div class="ro-wrap ro-split">
		<h2 class="ro-h2" id="roHowTitle"><?php esc_html_e( 'How booking works', 'oria' ); ?></h2>
		<div class="ro-how">
			<p><?php esc_html_e( 'The "Check dates" links on this page take you to BookRetreats, where you see current availability and prices and book. Oria Haven does not take bookings or payments, and you do not need an Oria account.', 'oria' ); ?></p>
			<p><?php echo esc_html( R\settings()['disclosure'] ); ?></p>
			<p><?php esc_html_e( 'Retreats are chosen for how well they suit the time and pace people are looking for, and for how clearly their details are set out. Earning a commission is not a quality award and does not decide the order.', 'oria' ); ?></p>
			<p><?php esc_html_e( 'Ordinary directory enquiries and direct links to practices remain commission-free.', 'oria' ); ?></p>
		</div>
	</div>
</section>

</div>

<script>
(function () {
	"use strict";
	var box = document.querySelector("[data-ro-offers]");
	var grid = box && box.querySelector("[data-ro-grid]");
	if (!grid) return;
	var cards = Array.prototype.slice.call(grid.querySelectorAll(".ro-card"));
	var count = box.querySelector("[data-ro-count]");
	var empty = box.querySelector("[data-ro-empty]");
	var q = new URLSearchParams(location.search);
	var state = { dest: q.get("dest") || "", len: q.get("len") || "" };
	function paint(push) {
		var n = 0;
		cards.forEach(function (c) {
			var ok = (!state.dest || c.getAttribute("data-ro-dest") === state.dest) && (!state.len || c.getAttribute("data-ro-len") === state.len);
			c.hidden = !ok;
			if (ok) n++;
		});
		box.querySelectorAll("[data-ro-f]").forEach(function (b) {
			b.setAttribute("aria-pressed", state[b.getAttribute("data-ro-f")] === b.getAttribute("data-ro-v") ? "true" : "false");
		});
		if (count) count.textContent = n + (n === 1 ? " retreat" : " retreats");
		if (empty) empty.hidden = n > 0;
		if (push) {
			var p = new URLSearchParams(location.search);
			["dest", "len"].forEach(function (k) { if (state[k]) p.set(k, state[k]); else p.delete(k); });
			var qs = p.toString();
			try { history.replaceState(null, "", location.pathname + (qs ? "?" + qs : "") + location.hash); } catch (e) {}
		}
	}
	box.addEventListener("click", function (e) {
		var b = e.target.closest && e.target.closest("[data-ro-f]");
		if (!b) return;
		state[b.getAttribute("data-ro-f")] = b.getAttribute("data-ro-v");
		paint(true);
	});
	document.querySelectorAll("[data-ro-set]").forEach(function (a) {
		a.addEventListener("click", function (e) {
			var kv = a.getAttribute("data-ro-set").split(":");
			e.preventDefault();
			state[kv[0]] = kv[1];
			paint(true);
			box.scrollIntoView({ behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block: "start" });
		});
	});
	paint(false);
})();
</script>

<?php
get_footer();
