<?php
/**
 * /singing-bowls/ — the singing bowl hub.
 *
 * One page that holds the subject together: what a bowl is, what actually
 * differs between them, the two we list, and the places in Perth where you
 * can hear one before buying anything.
 *
 * WHY ONE PAGE AND NOT NINETEEN. The plan this came from had a page each for
 * best bowls, sets, beginners, accessories, frequencies, notes and sizes.
 * There are two bowls in the catalogue. Nine of those pages would have been
 * built around one product or none, which is the thin-content problem the
 * product CPT was deliberately designed to avoid, and it would have spent
 * crawl budget the site does not have -- listings are already sitting at
 * "Discovered, currently not indexed" for want of it. This hub says
 * everything those pages would have said, in one place that can earn its
 * keep. Sections split out into their own pages as they outgrow their slot,
 * and the hub becomes the index it already looks like.
 *
 * Everything dynamic is read, never hard-coded: the products come from the
 * shop engine, the places from the directory, the reading from whatever is
 * actually published. An empty source renders nothing rather than a promise.
 *
 * No claim anywhere about what a bowl or a session does to a person. The
 * frequency question is answered as what the numbers mean, which is the only
 * part of it that is a fact.
 */

declare(strict_types=1);

get_header();

/* ------------------------------------------------------------- the bowls */

$oria_bowls = array();
if ( function_exists( '\Oria\Shop\Engine\products' ) ) {
	$oria_bterm = get_term_by( 'slug', 'singing-bowls', \Oria\Shop\Data\TAX );
	if ( $oria_bterm instanceof WP_Term ) {
		$oria_bowls = \Oria\Shop\Engine\products( array( $oria_bterm->term_id ), 6 );
	}
}

/* ------------------------------------------------- where to hear one live */

$oria_sound   = get_term_by( 'slug', 'sound-healing', 'specialty' );
$oria_soundn  = $oria_sound instanceof WP_Term ? (int) $oria_sound->count : 0;
$oria_soundur = '';
if ( $oria_sound instanceof WP_Term ) {
	$oria_l       = get_term_link( $oria_sound );
	$oria_soundur = is_wp_error( $oria_l ) ? '' : (string) $oria_l;
}

$oria_medit   = get_term_by( 'slug', 'meditation', 'specialty' );
$oria_meditur = '';
if ( $oria_medit instanceof WP_Term ) {
	$oria_l       = get_term_link( $oria_medit );
	$oria_meditur = is_wp_error( $oria_l ) ? '' : (string) $oria_l;
}

/*
 * What a sound session is actually like, read from the Compare registry
 * rather than described here -- the same numbers the listing pages score
 * against, so the hub cannot drift from them.
 */
$oria_sx = null;
if ( function_exists( '\Oria\Core\Compare\experiences' ) ) {
	foreach ( \Oria\Core\Compare\experiences() as $oria_e ) {
		if ( 'sound-healing' === $oria_e['id'] ) {
			$oria_sx = $oria_e['attributes'];
			break;
		}
	}
}

/* --------------------------------------------------------------- reading */

/*
 * Scored against titles, not searched.
 *
 * WP_Query's `s` searches post_content, and on a site whose articles all
 * mention sound or stillness somewhere in the body it returned a naturopath
 * guide and a reformer pilates explainer as the top two results for
 * "sound". A title is a promise about the subject; a body mention is not.
 *
 * Read rather than listed, because "What happens at a sound bath" is
 * published on production and a draft locally -- a hard-coded link would be
 * a 404 on one of the two, and this way the section fills itself as the
 * journal grows.
 */
$oria_kw     = array( 'sound bath' => 6, 'singing bowl' => 6, 'sound' => 5, 'bowl' => 5, 'meditat' => 4, 'breath' => 2, 'stillness' => 2 );
$oria_scored = array();

foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 200 ) ) as $oria_c ) {
	$oria_hay = strtolower( (string) $oria_c->post_title );
	$oria_s   = 0;
	foreach ( $oria_kw as $oria_k => $oria_w ) {
		if ( false !== strpos( $oria_hay, $oria_k ) ) {
			$oria_s += $oria_w;
		}
	}
	if ( $oria_s > 0 ) {
		$oria_scored[] = array( 'score' => $oria_s, 'post' => $oria_c );
	}
}

usort(
	$oria_scored,
	static function ( array $a, array $b ): int {
		return $b['score'] <=> $a['score'] ?: strcmp( $b['post']->post_date, $a['post']->post_date );
	}
);

$oria_reads = wp_list_pluck( array_slice( $oria_scored, 0, 4 ), 'post' );
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Singing bowls', 'oria' ); ?></span>
	</nav>
</section>

<?php
/*
 * The hero photograph is decorative, so it is painted by CSS rather than
 * carried in the markup: it has nothing to say that the headline does not,
 * and an <img> here would put an empty alt attribute in the document and
 * download the file on phones, which never show it. The fade is a mask on
 * the image rather than a coloured scrim over it, so the left side resolves
 * to whatever the page ground actually is instead of a hard-coded hex that
 * has to be kept in step with the token.
 */
?>
<div class="heroband sbhero-band">
	<section class="wrap sbhero">
		<span class="micro sbhero__eyebrow"><?php esc_html_e( 'A guide', 'oria' ); ?></span>
		<h1 class="h1 sbhero__title"><?php esc_html_e( 'Singing bowls, and where to hear one in Perth', 'oria' ); ?></h1>
		<p class="lede sbhero__lede"><?php esc_html_e( 'What a singing bowl actually is, what differs between one and the next, and the places around Perth where you can sit with a room full of them before deciding to own one.', 'oria' ); ?></p>
	</section>
</div>

<section class="wrap section section--top-flush sbwrap">

	<div class="sb__b">
		<h2 class="h3"><?php esc_html_e( 'What a singing bowl is', 'oria' ); ?></h2>
		<p><?php esc_html_e( 'A standing bell, played from the outside rather than struck from within. You either strike it once with a padded mallet and let the note fall away, or run a wooden striker around the rim until the bowl holds a continuous tone. The second takes a few minutes to learn and is the reason people describe bowls as being played rather than rung.', 'oria' ); ?></p>
		<p><?php esc_html_e( 'They come from the Himalayan metalworking tradition and are still made there, alongside a modern industry making them in quartz. A bowl is an instrument and an object: it wants a soft surface under it, it marks if you keep it in a drawer with other things, and its note is decided almost entirely by its size.', 'oria' ); ?></p>
	</div>

	<div class="sb__b sb__b--rule">
		<h2 class="h3"><?php esc_html_e( 'Choosing one', 'oria' ); ?></h2>

		<h3 class="sb__h"><?php esc_html_e( 'Metal or crystal', 'oria' ); ?></h3>
		<p><?php esc_html_e( 'Metal bowls — usually sold as Tibetan or Himalayan — have a complex, layered tone with several notes audible at once, and they are the smaller and cheaper of the two. Quartz bowls produce a purer, louder, more singular note and are considerably more expensive and more fragile. A first bowl is almost always metal.', 'oria' ); ?></p>

		<h3 class="sb__h"><?php esc_html_e( 'Hand-hammered or machine-made', 'oria' ); ?></h3>
		<p><?php esc_html_e( 'Hand-hammered bowls carry visible marks and an uneven surface, and their tone is longer and less even for it. Machine-made bowls are cheaper and more consistent, and there is nothing wrong with one. The uneven surface is the thing you are paying for, and whether it is worth it is a question about how you like the sound, not about quality.', 'oria' ); ?></p>

		<h3 class="sb__h"><?php esc_html_e( 'Size decides the note', 'oria' ); ?></h3>
		<p><?php esc_html_e( 'Bigger bowls sound lower and carry further. A bowl meant for a table is a different object from one meant to fill a room, and the difference in the listing is usually diameter rather than anything else. If a bowl is sold by its note, the note is a consequence of the size.', 'oria' ); ?></p>

		<h3 class="sb__h"><?php esc_html_e( 'What the numbers mean', 'oria' ); ?></h3>
		<p><?php esc_html_e( 'Bowls are often advertised at 432Hz or 440Hz. That is a tuning reference — the pitch A is set to, and what the rest of the scale is measured from. 440Hz is the modern orchestral standard; 432Hz is a slightly lower alternative some makers prefer. It tells you what the bowl will sound like beside other instruments, and we make no claim about it beyond that.', 'oria' ); ?></p>

		<h3 class="sb__h"><?php esc_html_e( 'Sets', 'oria' ); ?></h3>
		<p><?php esc_html_e( 'A bowl on its own needs two more things: something to play it with, and something soft to sit it on. Sets include both, which is why they are usually the sensible buy — the mallet and the cushion are what people otherwise order separately a week later.', 'oria' ); ?></p>
	</div>

	<?php if ( $oria_bowls ) : ?>
		<div class="sb__b sb__b--rule">
			<h2 class="h3"><?php esc_html_e( 'The bowls we list', 'oria' ); ?></h2>
			<?php // Counted, not written -- "Two" stops being true the moment a third is added. ?>
			<p class="sb__lede">
				<?php
				printf(
					/* translators: %d: number of bowls listed */
					esc_html( _n( '%d bowl, chosen rather than collected. It says why it is here.', '%d bowls, chosen rather than collected. Each one says why it is here.', count( $oria_bowls ), 'oria' ) ),
					count( $oria_bowls )
				);
				?>
			</p>
			<div class="prodgrid prodgrid--page">
				<?php
				foreach ( $oria_bowls as $oria_p ) {
					echo \Oria\Shop\Render\card( $oria_p ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</div>
			<p class="shopband__disclosure"><?php echo esc_html( \Oria\Shop\Data\disclosure() ); ?></p>
			<?php \Oria\Shop\Track\impressions( $oria_bowls ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $oria_soundur && $oria_soundn > 0 ) : ?>
		<div class="sb__b sb__b--rule">
			<h2 class="h3"><?php esc_html_e( 'Hear one before you buy one', 'oria' ); ?></h2>
			<p><?php
				printf(
					/* translators: %d: number of sound healing practices listed */
					esc_html__( 'A recording tells you almost nothing about a bowl — the sound is physical, and most of it happens in the room rather than in the note. %d practices across Perth run sound sessions, and an hour in one is the cheapest way to find out whether you want to own one.', 'oria' ),
					(int) $oria_soundn
				);
			?></p>

			<?php if ( $oria_sx ) : ?>
				<ul class="sb__facts">
					<?php if ( ! empty( $oria_sx['duration'] ) ) : ?>
						<li><b><?php esc_html_e( 'Typical session', 'oria' ); ?></b> <?php echo esc_html( (string) $oria_sx['duration'] ); ?></li>
					<?php endif; ?>
					<?php if ( ! empty( $oria_sx['groupsize'] ) ) : ?>
						<li><b><?php esc_html_e( 'Group', 'oria' ); ?></b> <?php echo esc_html( (string) $oria_sx['groupsize'] ); ?></li>
					<?php endif; ?>
					<?php if ( ! empty( $oria_sx['position'] ) ) : ?>
						<li><b><?php esc_html_e( 'You are', 'oria' ); ?></b> <?php echo esc_html( (string) $oria_sx['position'] ); ?></li>
					<?php endif; ?>
					<?php if ( ! empty( $oria_sx['touch'] ) ) : ?>
						<li><b><?php esc_html_e( 'Touch', 'oria' ); ?></b> <?php echo esc_html( (string) $oria_sx['touch'] ); ?></li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>

			<p class="sb__cta">
				<a class="btn btn--dark" href="<?php echo esc_url( $oria_soundur ); ?>">
					<?php
					printf(
						/* translators: %d: number of practices */
						esc_html__( 'Sound healing in Perth — %d places', 'oria' ),
						(int) $oria_soundn
					);
					?><?php echo \Oria\Theme\arrow(); // phpcs:ignore ?>
				</a>
				<?php if ( $oria_meditur ) : ?>
					<a class="btn btn--ghost btn--plain" href="<?php echo esc_url( $oria_meditur ); ?>"><?php esc_html_e( 'Meditation in Perth', 'oria' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $oria_reads ) : ?>
		<div class="sb__b sb__b--rule">
			<h2 class="h3"><?php esc_html_e( 'Read next', 'oria' ); ?></h2>
			<ul class="sb__reads">
				<?php foreach ( $oria_reads as $oria_r ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $oria_r ) ); ?>"><?php echo esc_html( get_the_title( $oria_r ) ); ?></a>
						<?php if ( $oria_r->post_excerpt ) : ?>
							<span><?php echo esc_html( wp_trim_words( $oria_r->post_excerpt, 18 ) ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="sb__b sb__b--rule">
		<h2 class="h3"><?php esc_html_e( 'Everything else in the shop', 'oria' ); ?></h2>
		<p><?php esc_html_e( 'Bowls are one shelf of a small, hand-picked shop — chimes, meditation cards, mats and recovery tools sit beside them.', 'oria' ); ?></p>
		<p class="sb__cta">
			<a class="btn btn--ghost btn--plain" href="<?php echo esc_url( add_query_arg( 'category', 'singing-bowls', home_url( '/shop/' ) ) ); ?>"><?php esc_html_e( 'Singing bowls in the shop', 'oria' ); ?></a>
			<a class="btn btn--ghost btn--plain" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'The whole shop', 'oria' ); ?></a>
		</p>
	</div>

</section>

<?php
get_footer();
