<?php
/**
 * /wellness-map/ — the mood-first map. A working prototype, not a mock.
 *
 * WHAT THIS IS TESTING. Whether "how do you want to feel" is a better front
 * door to the directory than "what category are you looking for". Everything
 * here runs on live data: no seeded examples, no placeholder counts. If a
 * mood shows eleven places, there are eleven places.
 *
 * WHY THERE IS NO MOOD LAYER. The first build of this page grouped the
 * directory's thirteen wellness goals into six invented moods -- Slow down,
 * Move, Recover. That was a second vocabulary for an idea the site already
 * had a vocabulary for, and the goals are better than the grouping: they
 * carry their own colour, icon and one-line explanation, and a visitor has
 * already met them on the directory's "What are you after?" row. So this
 * page offers the thirteen directly. Nothing is grouped, nothing is
 * renamed, and nothing new needs tagging.
 *
 * THE COMPLIANCE LINE, WHICH SHAPES THE COPY MORE THAN THE DESIGN DOES.
 * compare.json states it plainly: every attribute describes the room, never
 * the outcome. So "I'm feeling stressed -> 38 ways to help you slow down" is
 * not available to us: it names a state and promises relief. The mood is a
 * question about what the visitor wants to DO -- slow down, move, be around
 * people -- and every answer describes what a room is like. No copy here
 * says a session helps, treats, eases or improves anything.
 *
 * WHAT IT DELIBERATELY DOES NOT DO. There is no calm-to-active slider. Only
 * 65% of listings carry DNA scores, so a slider silently disappears a third
 * of the directory, and nobody thinks in "70% calm". The same information is
 * offered as a plain quieter/livelier toggle, which degrades honestly: a
 * listing with no scores is simply not sorted by them.
 */

declare(strict_types=1);

get_header();

/*
 * The goals, straight from goodfor.json -- label, colour, icon slug and the
 * one-line explanation, exactly as the directory's chip row shows them.
 *
 * COLOUR IS A PROPERTY OF THE GOAL, NOT OF THE PLACE. A listing answers to
 * several goals at once -- most yoga studios match three -- so there is no
 * such thing as "the colour of this business". A pin takes the colour of
 * the first SELECTED goal it matches, which makes the chips the legend and
 * makes the answer to "why is that one rust" always "because you asked for
 * Relax". With nothing selected the map stays one neutral grey: colouring
 * by an unasked-for goal would invent a primary the data does not have.
 *
 * Colour never carries meaning alone. Pins name their place on click, the
 * list repeats everything as text, and the chips say their own names.
 */
$oria_goals_all = function_exists( '\Oria\Core\GoodFor\labels' ) ? \Oria\Core\GoodFor\labels() : array();

/* ---------------------------------------------------------------- data -- */

$oria_rows = array();

/*
 * One city at a time.
 *
 * km_from_cbd() measures from the listing's OWN city centre, which is
 * right for a multi-city directory and wrong for a single distance ring:
 * a five-kilometre band would otherwise hold places in Perth and places
 * in Margaret River and call them equally near. The header's region
 * switcher already decides which city a visitor is in; this follows it.
 */
$oria_city  = function_exists( '\Oria\Core\Cities\current' ) ? \Oria\Core\Cities\current() : null;
$oria_cname = function_exists( '\Oria\Core\Cities\name' ) ? \Oria\Core\Cities\name( $oria_city ) : __( 'Perth', 'oria' );

foreach ( get_posts(
	array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	)
) as $oria_p ) {

	$oria_id = (int) $oria_p->ID;

	// Coordinates: every listing has them, at address or suburb precision.
	// Outside the city we are showing? Not this map's business.
	if ( $oria_city && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
		if ( ! \Oria\Core\Cities\filter_ids( array( $oria_id ), $oria_city ) ) {
			continue;
		}
	}

	$oria_geo = function_exists( '\Oria\Core\Geo\position' ) ? \Oria\Core\Geo\position( $oria_id ) : null;
	if ( ! $oria_geo || empty( $oria_geo['lat'] ) ) {
		continue;
	}

	// The goals this listing satisfies, derived exactly as the cards derive them.
	$oria_goals = array();
	if ( function_exists( '\Oria\Core\GoodFor\for_listing' ) ) {
		foreach ( \Oria\Core\GoodFor\for_listing( $oria_id ) as $oria_w ) {
			$oria_goals[] = is_array( $oria_w ) ? (string) ( $oria_w['label'] ?? '' ) : (string) $oria_w;
		}
	}

	/*
	 * Two DNA dimensions, where the registry can name this kind of session.
	 * Used only for the quieter/livelier ordering, never to include or
	 * exclude — a listing the registry cannot place still appears.
	 */
	$oria_quiet = null;
	$oria_soc   = null;
	if ( function_exists( '\Oria\Core\Dna\bars' ) ) {
		foreach ( \Oria\Core\Dna\bars( $oria_id ) as $oria_b ) {
			$oria_k = strtolower( (string) ( $oria_b['label'] ?? '' ) );
			if ( str_contains( $oria_k, 'quiet' ) )  { $oria_quiet = (int) $oria_b['score']; }
			if ( str_contains( $oria_k, 'social' ) ) { $oria_soc   = (int) $oria_b['score']; }
		}
	}

	$oria_areas  = wp_get_post_terms( $oria_id, 'area' );
	$oria_areas  = is_wp_error( $oria_areas ) ? array() : $oria_areas;
	$oria_suburb = '';
	foreach ( $oria_areas as $oria_a ) {
		if ( $oria_a->parent ) {
			$oria_suburb = \Oria\Theme\tname( $oria_a );
			break;
		}
	}

	$oria_cat  = '';
	$oria_cats = wp_get_post_terms( $oria_id, 'practice' );
	if ( ! is_wp_error( $oria_cats ) && $oria_cats ) {
		$oria_cat = \Oria\Theme\tname( $oria_cats[0] );
	}

	/*
	 * The picture, and who it belongs to.
	 *
	 * 348 of these images are Google Places photos, and places.php states the
	 * condition plainly: photos must be shown with their author attributions.
	 * The listing pages honour that; a hover card that quietly dropped it
	 * would not. So the attribution rides along with the URL and the card
	 * prints it. Where there is no cached photo the generic scene is used --
	 * nobody's work to credit, and no third-party image hotlinked.
	 *
	 * Cache only. photos_for() may fetch live, and 377 live fetches to build
	 * one page is not a trade anyone would make.
	 */
	$oria_img = '';
	$oria_att = '';
	if ( function_exists( '\Oria\Core\Places\data_for' ) && null !== \Oria\Core\Places\data_for( $oria_id, false ) ) {
		$oria_ph = function_exists( '\Oria\Core\Places\photos_for' ) ? \Oria\Core\Places\photos_for( $oria_id, 400 ) : array();
		$oria_img = (string) ( $oria_ph['urls'][0] ?? '' );
		$oria_att = (string) ( $oria_ph['attributions'][0]['name'] ?? '' );
	}
	if ( '' === $oria_img && function_exists( '\Oria\Theme\listing_scene' ) ) {
		$oria_img = \Oria\Theme\listing_scene( $oria_id );
		$oria_att = '';
	}

	$oria_rows[] = array(
		// Decoded: JSON has no entities, and the map draws these with
		// textContent, so a curly apostrophe would show as &#8217;.
		't'  => html_entity_decode( get_the_title( $oria_id ), ENT_QUOTES, 'UTF-8' ),
		'u'  => get_permalink( $oria_id ),
		'la' => round( (float) $oria_geo['lat'], 5 ),
		'lo' => round( (float) $oria_geo['lng'], 5 ),
		// Rounded here, not in the browser: a suburb centroid does not know
		// it is 0.2528983960960961 km from anywhere.
		'km' => function_exists( '\Oria\Core\Geo\km_from_cbd' )
			? ( null === \Oria\Core\Geo\km_from_cbd( $oria_id )
				? null
				: round( (float) \Oria\Core\Geo\km_from_cbd( $oria_id ), 1 ) )
			: null,
		'g'  => array_values( array_filter( $oria_goals ) ),
		'q'  => $oria_quiet,
		's'  => $oria_soc,
		'sb' => $oria_suburb,
		'c'  => $oria_cat,
		'img' => $oria_img,
		'att' => $oria_att,
		'b'  => has_term( 'beginners', 'audience', $oria_id ) ? 1 : 0,
	);
}

$oria_goals_js = array();
foreach ( $oria_goals_all as $oria_g ) {
	$oria_goals_js[ (string) $oria_g['slug'] ] = array(
		'label'  => (string) $oria_g['label'],
		'colour' => (string) $oria_g['color'],
	);
}

?>

<?php
/*
 * The same masked band the journal and the singing bowls hub use: the
 * picture fades out to the left so the headline sits on clean ground, and
 * it is painted by CSS so a phone never downloads it.
 *
 * Decorative, and honest about it. An aerial of a coastal town is a map,
 * which is the idea -- but it is stock, and it is NOT Perth. It carries no
 * landmark that would claim otherwise, and the moment there is a real
 * Perth photograph to hand it should replace this.
 */
?>
<div class="heroband wmhero-band">
<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Wellness map', 'oria' ); ?></span>
	</nav>
	<div class="pagehead__copy">
		<h1 class="h1 pagehead__title"><?php printf( esc_html__( 'What do you want to do in %s?', 'oria' ), esc_html( $oria_cname ) ); ?></h1>
		<p class="lede pagehead__lede"><?php esc_html_e( 'Pick what you are after, not what the industry calls it. Everything below is a real place with a real address — the map only shows what matches.', 'oria' ); ?></p>

		<?php
		/*
		 * Written, not generated. The page needs prose a reader gets
		 * something from -- what the goals mean, what the map is for -- and
		 * a page with one heading and a hundred words of its own was never
		 * going to rank for anything, however good the tool on it is.
		 *
		 * Room language throughout: how quiet, how busy, how far. Nothing
		 * here says a session helps, eases or improves anything.
		 */
		?>
		<div class="pagehead__intro">
			<p>
				<?php
				printf(
					/* translators: 1: number of places, 2: city name, 3: number of goals. */
					esc_html__( 'Every one of the %1$d places on this map is somewhere in %2$s you can actually walk into. Choose from %3$d things you might want an hour to be — quiet, hands-on, physical, social — and the map keeps only the places that answer to it.', 'oria' ),
					count( $oria_rows ),
					esc_html( $oria_cname ),
					count( $oria_goals_all )
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'The wants are the visitor\'s, and the matching is done on what a place offers: its services, its specialties, how large the room usually is and what it costs. A studio is not tagged as calming or restorative anywhere on this site, because a directory that has never been through the door does not get to say so.', 'oria' ); ?>
			</p>
		</div>
	</div>
</section>
</div>

<section class="wrap section section--top-flush wmap" id="wmap">

	<?php
	/*
	 * The same chips as the directory's "What are you after?" row, in the same
	 * class so they inherit its styling rather than growing a parallel one.
	 * The count is the only thing added, and it is live.
	 */
	?>
	<div class="goodfor goodfor--wmap" data-wmap-goals>
		<div class="goodfor__row" role="group" aria-label="<?php esc_attr_e( 'What are you after', 'oria' ); ?>">
			<?php foreach ( $oria_goals_all as $oria_g ) : ?>
				<button class="goodfor__chip" type="button"
					style="--gf:<?php echo esc_attr( $oria_g['color'] ); ?>"
					data-goal="<?php echo esc_attr( $oria_g['slug'] ); ?>"
					title="<?php echo esc_attr( $oria_g['line'] ); ?>"
					aria-pressed="false">
					<img class="goodfor__icon" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/goodfor/' . $oria_g['slug'] . '.webp' ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
					<span><?php echo esc_html( $oria_g['label'] ); ?></span>
					<em class="goodfor__n" data-goal-count>—</em>
				</button>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="wmap__controls">
		<div class="wmap__seg" role="group" aria-label="<?php esc_attr_e( 'Distance', 'oria' ); ?>">
			<button class="segbtn" type="button" data-dist="5"><?php esc_html_e( 'Within 5 km', 'oria' ); ?></button>
			<button class="segbtn" type="button" data-dist="15"><?php esc_html_e( 'Within 15 km', 'oria' ); ?></button>
			<button class="segbtn is-on" type="button" data-dist="0"><?php printf( esc_html__( 'All of %s', 'oria' ), esc_html( $oria_cname ) ); ?></button>
		</div>

		<div class="wmap__seg" role="group" aria-label="<?php esc_attr_e( 'Order', 'oria' ); ?>">
			<?php // "Nearest" means nearest to the middle of the map, not to the CBD — see renderList(). ?>
			<button class="segbtn is-on" type="button" data-order=""><?php esc_html_e( 'Closest to view', 'oria' ); ?></button>
			<button class="segbtn" type="button" data-order="quiet"><?php esc_html_e( 'Quietest first', 'oria' ); ?></button>
			<button class="segbtn" type="button" data-order="social"><?php esc_html_e( 'Most social first', 'oria' ); ?></button>
		</div>

		<label class="check wmap__begin">
			<input type="checkbox" data-beginners>
			<span><?php esc_html_e( 'Beginner friendly', 'oria' ); ?></span>
		</label>
	</div>

	<p class="wmap__count" data-wmap-count aria-live="polite"></p>

	<h2 class="wmap__h2">
		<?php
		printf(
			/* translators: 1: number of places, 2: city name. */
			esc_html__( 'All %1$d wellness places in %2$s', 'oria' ),
			count( $oria_rows ),
			esc_html( $oria_cname )
		);
		?>
	</h2>

	<div class="wmap__split">
		<div class="wmap__map" data-wmap-map role="application" aria-label="<?php esc_attr_e( 'Map of matching places', 'oria' ); ?>"></div>
		<ol class="wmap__list" data-wmap-list>
			<?php
			/*
			 * Rendered on the server, not left empty for the script to fill.
			 *
			 * This page had ZERO impressions in 28 days and Search Console
			 * reported the URL as unknown to Google. Part of that was
			 * discovery -- nothing linked here -- but the rest was this: the
			 * whole page was 364 words, most of them navigation, and exactly
			 * one internal link in <main>. Every one of these places lived in
			 * a JSON blob a crawler has no reason to read.
			 *
			 * wellness-map.js clears this list with textContent = "" before
			 * its first paint, so nothing is duplicated and nothing is stale:
			 * a visitor with JavaScript sees the interactive version, and a
			 * crawler -- or anybody without it -- sees the real list.
			 */
			foreach ( $oria_rows as $oria_r ) :
				$oria_bits = array_filter( array( $oria_r['c'], $oria_r['sb'], $oria_r['pb'] ) );
				?>
				<li class="wmap__item">
					<a class="wmap__link" href="<?php echo esc_url( $oria_r['u'] ); ?>"><?php echo esc_html( $oria_r['t'] ); ?></a>
					<span class="wmap__meta"><?php echo esc_html( implode( '  ·  ', $oria_bits ) ); ?></span>
					<?php if ( $oria_r['g'] ) : ?>
						<span class="wmap__goals"><?php echo esc_html( implode( ', ', array_slice( (array) $oria_r['g'], 0, 3 ) ) ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>

	<p class="wmap__note micro">
		<?php esc_html_e( 'A place with no experience scores still appears here '
			. '— it just cannot be ordered by how quiet or social it is.', 'oria' ); ?>
	</p>

	<?php
	/*
	 * ItemList, so the page says what it is holding.
	 *
	 * Positions and names only, each pointing at the listing's own URL --
	 * the detail lives there and is described by that page's own schema.
	 * Repeating a business's address and rating here would be two sources
	 * for one fact, and the one further from the business is the one that
	 * goes stale.
	 *
	 * Not emitted through wpseo_schema_graph: this is a page template, and
	 * the graph filter fires for the whole site. seo.php decodes entities
	 * across every graph it sees, which is why the names are decoded here
	 * before encoding -- JSON has no entities.
	 */
	$oria_ld = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'name'            => sprintf(
			/* translators: %s: city name. */
			__( 'Wellness places in %s', 'oria' ),
			$oria_cname
		),
		'numberOfItems'   => count( $oria_rows ),
		'itemListOrder'   => 'https://schema.org/ItemListUnordered',
		'itemListElement' => array(),
	);
	foreach ( array_values( $oria_rows ) as $oria_i => $oria_r ) {
		$oria_ld['itemListElement'][] = array(
			'@type'    => 'ListItem',
			'position' => $oria_i + 1,
			'name'     => html_entity_decode( (string) $oria_r['t'], ENT_QUOTES, 'UTF-8' ),
			'url'      => (string) $oria_r['u'],
		);
	}
	?>
	<script type="application/ld+json"><?php echo wp_json_encode( $oria_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>

	<script type="application/json" data-wmap-data><?php echo wp_json_encode( $oria_rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
	<script type="application/json" data-wmap-centre><?php echo wp_json_encode( $oria_cname ); ?></script>
	<script type="application/json" data-wmap-goals-map><?php echo wp_json_encode( $oria_goals_js, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
</section>

<?php
get_footer();
