<?php
/**
 * v4 front page (design test) -- the design canvas's homepage, built from
 * live data.
 *
 * Overrides the parent's page.php for the front page only. The parent
 * renders the page's ACF "Sections"; this renders the canvas's order
 * instead, every figure and name read from the directory:
 *
 *   1. Hero: "How do you want to feel today?" with the feeling chips
 *   2. The Oria concierge: a sentence for Ask Oria (the same ?q= door
 *      the category pages use)
 *   3. Four ways in: categories grouped by how they feel, live counts
 *   4. The Perth Reset: the latest published journey and its stops
 *   5. This week: the next upcoming events
 *   6. Oria Field Notes: the latest journal writing
 *   7. The map
 *   8. A few places worth knowing: Best Of picks, with the editors' reasons
 *   9. Closing picture
 *
 * A section with nothing real to show is left out, never filled. Scene
 * pictures are the theme's own (assets/img/scene-*.webp, see CREDITS.md):
 * decoration, so their alt text is empty.
 *
 * @package Oria
 */

declare(strict_types=1);

use function Oria\Theme\tname;

get_header();

$oria_img = static fn( string $file ): string => get_template_directory_uri() . '/assets/img/' . $file;

// The city the directory is scoped to, and how many places it holds.
$oria_city  = function_exists( '\Oria\Core\Cities\current' ) ? \Oria\Core\Cities\current() : null;
$oria_cname = $oria_city && function_exists( '\Oria\Core\Cities\name' ) ? \Oria\Core\Cities\name( $oria_city ) : __( 'Perth', 'oria' );
$oria_all   = get_posts(
	array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);
$oria_total = count( $oria_all );

// A listing's suburb, for the small grey line under a name.
$oria_suburb = static function ( int $id ): string {
	foreach ( \Oria\Theme\oria_terms_of( $id, 'area' ) as $t ) {
		if ( $t->parent ) {
			return tname( $t );
		}
	}
	return '';
};
$oria_from = static function ( int $id ): string {
	$p = get_field( 'price_from', $id );
	/* translators: %s: starting price */
	return is_numeric( $p ) && (float) $p > 0 ? sprintf( __( 'from $%s', 'oria' ), number_format_i18n( (float) $p ) ) : '';
};

/*
 * The feelings. Each chip swaps the hero picture and pre-writes a sentence
 * in the concierge; nothing is filtered until the visitor sends it.
 * Phrased as a place and a time, never a symptom (see page-ask.php).
 */
$oria_feelings = array(
	array( 'calm', __( 'Calm', 'oria' ), 'scene-still-water.webp', __( 'Somewhere quiet to slow down this week', 'oria' ) ),
	array( 'restored', __( 'Restored', 'oria' ), 'scene-room.webp', __( 'A massage or sauna near me this weekend', 'oria' ) ),
	array( 'energised', __( 'Energised', 'oria' ), 'scene-dawn-ridge.webp', __( 'A class that gets me moving before work', 'oria' ) ),
	array( 'connected', __( 'Connected', 'oria' ), 'scene-hall.webp', __( 'A group or workshop to meet people at', 'oria' ) ),
	array( 'away', __( 'Somewhere else', 'oria' ), 'scene-coast.webp', __( 'A day out of the city, somewhere by the water', 'oria' ) ),
);

// Four ways in: two categories each, counted within the city.
$oria_worlds = array(
	array( __( 'Slow down', 'oria' ), 'scene-still-water.webp', array( 'mind', 'energy' ) ),
	array( __( 'Feel restored', 'oria' ), 'scene-room.webp', array( 'bodywork', 'spa' ) ),
	array( __( 'Come alive', 'oria' ), 'scene-studio.webp', array( 'fitness', 'yoga' ) ),
	array( __( 'Find your people', 'oria' ), 'scene-hall.webp', array( 'community', 'creative' ) ),
);
$oria_world_links = static function ( array $slugs ) use ( $oria_city ): array {
	$out = array();
	foreach ( $slugs as $slug ) {
		$t = get_term_by( 'slug', $slug, 'practice' );
		if ( ! $t instanceof WP_Term || ! function_exists( '\Oria\Core\Intents\listings_in' ) ) {
			continue;
		}
		$ids = \Oria\Core\Intents\listings_in( $t );
		if ( $oria_city && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
			$ids = \Oria\Core\Cities\filter_ids( $ids, $oria_city );
		}
		if ( $ids ) {
			$out[] = array( tname( $t ), \Oria\Core\PracticesIndex\category_url( $t ), count( $ids ) );
		}
	}
	return $out;
};

// The Perth Reset: the newest journey and its first four stops.
$oria_journey = function_exists( '\Oria\Core\Journeys\split' ) ? \Oria\Core\Journeys\split()['feature'] : null;
$oria_stops   = array();
if ( $oria_journey instanceof WP_Post ) {
	foreach ( (array) ( get_field( 'journey', $oria_journey->ID ) ?: array() ) as $oria_row ) {
		$oria_lid = (int) ( is_object( $oria_row['listing'] ?? null ) ? $oria_row['listing']->ID : ( $oria_row['listing'] ?? 0 ) );
		$oria_stops[] = array(
			'time'  => trim( (string) ( $oria_row['time'] ?? '' ) ),
			'label' => trim( (string) ( $oria_row['label'] ?? '' ) ),
			'name'  => $oria_lid && 'publish' === get_post_status( $oria_lid ) ? \Oria\Theme\ptitle( get_post( $oria_lid ) ) : '',
			'url'   => $oria_lid && 'publish' === get_post_status( $oria_lid ) ? (string) get_permalink( $oria_lid ) : '',
			'where' => $oria_lid ? $oria_suburb( $oria_lid ) : '',
		);
	}
	$oria_stops = array_slice( array_values( array_filter( $oria_stops, static fn( array $s ): bool => '' !== $s['label'] || '' !== $s['name'] ) ), 0, 4 );
}

// This week: the next four upcoming events.
$oria_events = get_posts(
	array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 4,
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array( 'key' => 'event_start', 'value' => current_time( 'Y-m-d H:i:s' ), 'compare' => '>=', 'type' => 'DATETIME' ),
		),
	)
);
// event_start is naive local time: parse and print it without an offset
// (the same convention as the parent's starting-soon section).
$oria_when = static function ( WP_Post $ev ): string {
	$ts = strtotime( (string) get_field( 'event_start', $ev->ID ) );
	return $ts ? gmdate( 'l j F, g.ia', $ts ) : '';
};
$oria_ev_where = static function ( WP_Post $ev ): string {
	foreach ( wp_get_post_terms( $ev->ID, 'area' ) as $t ) {
		if ( $t->parent ) {
			return tname( $t );
		}
	}
	return (string) get_field( 'venue', $ev->ID );
};

// Field Notes: the latest four pieces of writing.
$oria_notes = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 4,
		'no_found_rows'  => true,
	)
);

// A few places worth knowing: the first pick with a reason from each of
// four Best Of guides, so the four are different places and all edited.
$oria_cards = array();
if ( function_exists( '\Oria\Core\BestOf\guides' ) ) {
	$oria_seen = array();
	foreach ( \Oria\Core\BestOf\guides() as $oria_g ) {
		foreach ( \Oria\Core\BestOf\entries( (int) $oria_g->ID ) as $oria_e ) {
			if ( '' === $oria_e['reason'] || isset( $oria_seen[ $oria_e['listing'] ] ) ) {
				continue;
			}
			$oria_seen[ $oria_e['listing'] ] = true;
			$oria_cards[] = $oria_e + array( 'guide' => $oria_g );
			break;
		}
		if ( count( $oria_cards ) >= 4 ) {
			break;
		}
	}
}
?>

<div class="xh">

<!-- 1. Hero -->
<section class="xh-hero" data-xh-hero aria-labelledby="xh-hero-title">
	<div class="xh-hero__pics" aria-hidden="true">
		<?php foreach ( $oria_feelings as $oria_i => $oria_f ) : ?>
			<img class="xh-hero__pic<?php echo 0 === $oria_i ? ' is-on' : ''; ?>" data-feel-pic="<?php echo esc_attr( $oria_f[0] ); ?>"
				src="<?php echo esc_url( $oria_img( $oria_f[2] ) ); ?>" alt="" <?php echo 0 === $oria_i ? 'fetchpriority="high"' : 'loading="lazy"'; ?> decoding="async">
		<?php endforeach; ?>
	</div>
	<div class="wrap xh-hero__copy on-deep">
		<p class="micro xh-hero__eyebrow">
			<?php
			/* translators: %s: city name */
			printf( esc_html__( '%s’s wellness guide', 'oria' ), esc_html( $oria_cname ) );
			?>
		</p>
		<h1 class="display xh-hero__title" id="xh-hero-title"><?php esc_html_e( 'How do you want to feel today?', 'oria' ); ?></h1>
		<p class="lede xh-hero__lede"><?php esc_html_e( 'Find somewhere to move, recover, connect or simply stop for a while.', 'oria' ); ?></p>
	</div>

	<!-- 2. The concierge -->
	<div class="wrap xh-concierge-wrap">
		<form class="xh-concierge" id="concierge" action="<?php echo esc_url( home_url( '/ask/' ) ); ?>" method="get">
			<div class="xh-concierge__head">
				<h2 class="h3"><?php esc_html_e( 'What would make today feel better?', 'oria' ); ?></h2>
				<a class="xh-link" href="<?php echo esc_url( \Oria\Core\Explore\base_url( $oria_city ) ); ?>"><?php esc_html_e( 'Or browse everything', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
			</div>
			<div class="xh-feel" role="group" aria-label="<?php esc_attr_e( 'How you want to feel', 'oria' ); ?>">
				<?php foreach ( $oria_feelings as $oria_i => $oria_f ) : ?>
					<button type="button" class="xh-feel__chip" data-feel="<?php echo esc_attr( $oria_f[0] ); ?>" data-feel-say="<?php echo esc_attr( $oria_f[3] ); ?>" aria-pressed="<?php echo 0 === $oria_i ? 'true' : 'false'; ?>">
						<?php echo esc_html( $oria_f[1] ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="xh-concierge__row">
				<label class="sr-only" for="xh-q"><?php esc_html_e( 'Describe what you are looking for', 'oria' ); ?></label>
				<input class="xh-concierge__input" type="text" id="xh-q" name="q" maxlength="400" autocomplete="off"
					placeholder="<?php echo esc_attr( $oria_feelings[0][3] ); ?>">
				<button class="btn btn--dark xh-concierge__go" type="submit"><?php esc_html_e( 'Ask Oria', 'oria' ); ?></button>
			</div>
		</form>
	</div>
</section>

<!-- 3. Four ways in -->
<section class="wrap xh-sec xh-worlds" aria-labelledby="xh-worlds-title">
	<header class="xh-sec__head">
		<div>
			<p class="micro"><?php esc_html_e( 'Four ways in', 'oria' ); ?></p>
			<h2 class="h1" id="xh-worlds-title"><?php esc_html_e( 'Start with how you feel, not what it’s called.', 'oria' ); ?></h2>
		</div>
		<?php if ( $oria_total ) : ?>
			<p class="xh-sec__aside">
				<?php
				/* translators: %s: number of listings */
				printf( esc_html__( '%s places, sorted by what they do for you.', 'oria' ), esc_html( number_format_i18n( $oria_total ) ) );
				?>
			</p>
		<?php endif; ?>
	</header>
	<div class="xh-worlds__grid">
		<?php
		foreach ( $oria_worlds as $oria_w ) :
			$oria_links = $oria_world_links( $oria_w[2] );
			if ( ! $oria_links ) {
				continue;
			}
			?>
			<article class="xh-world">
				<img class="xh-world__pic" src="<?php echo esc_url( $oria_img( $oria_w[1] ) ); ?>" alt="" loading="lazy" decoding="async">
				<div class="xh-world__body on-deep">
					<h3 class="h2 xh-world__title"><?php echo esc_html( $oria_w[0] ); ?></h3>
					<ul class="xh-world__links">
						<?php foreach ( $oria_links as $oria_l ) : ?>
							<li><a href="<?php echo esc_url( $oria_l[1] ); ?>"><?php echo esc_html( $oria_l[0] ); ?> <span>· <?php echo esc_html( number_format_i18n( $oria_l[2] ) ); ?></span> <span aria-hidden="true">&rarr;</span></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<?php if ( $oria_journey instanceof WP_Post && count( $oria_stops ) >= 2 ) : ?>
<!-- 4. The Perth Reset: a real journey -->
<section class="wrap xh-sec" aria-labelledby="xh-reset-title">
	<div class="xh-reset on-deep">
		<div class="xh-reset__text">
			<p class="micro xh-reset__eyebrow">
				<?php
				/* translators: %s: city name */
				printf( esc_html__( 'The %s Reset', 'oria' ), esc_html( $oria_cname ) );
				?>
			</p>
			<h2 class="h1" id="xh-reset-title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_journey ) ); ?></h2>
			<?php if ( has_excerpt( $oria_journey ) ) : ?>
				<p class="xh-reset__lede"><?php echo esc_html( get_the_excerpt( $oria_journey ) ); ?></p>
			<?php endif; ?>
			<div class="xh-actions">
				<a class="btn btn--light" href="<?php echo esc_url( get_permalink( $oria_journey ) ); ?>"><?php esc_html_e( 'See the whole day', 'oria' ); ?></a>
				<a class="btn btn--ghost-on-deep" href="<?php echo esc_url( home_url( '/journeys/' ) ); ?>"><?php esc_html_e( 'More journeys', 'oria' ); ?></a>
			</div>
			<?php if ( has_post_thumbnail( $oria_journey ) ) : ?>
				<figure class="xh-reset__pic"><?php echo get_the_post_thumbnail( $oria_journey, 'large', array( 'alt' => '', 'loading' => 'lazy' ) ); ?></figure>
			<?php endif; ?>
		</div>
		<ol class="xh-stops">
			<?php foreach ( $oria_stops as $oria_s ) : ?>
				<li class="xh-stop">
					<span class="xh-stop__time"><?php echo esc_html( $oria_s['time'] ); ?></span>
					<div>
						<?php if ( '' !== $oria_s['label'] ) : ?>
							<p class="micro xh-stop__label"><?php echo esc_html( $oria_s['label'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $oria_s['name'] ) : ?>
							<h3 class="h3 xh-stop__name"><a href="<?php echo esc_url( $oria_s['url'] ); ?>"><?php echo esc_html( $oria_s['name'] ); ?></a></h3>
						<?php endif; ?>
						<?php if ( '' !== $oria_s['where'] ) : ?>
							<p class="xh-stop__where"><?php echo esc_html( $oria_s['where'] ); ?></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
<?php endif; ?>

<?php if ( $oria_events ) : ?>
<!-- 5. This week -->
<section class="wrap xh-sec" aria-labelledby="xh-week-title">
	<header class="xh-sec__head">
		<div>
			<p class="micro">
				<?php
				/* translators: %s: city name */
				printf( esc_html__( 'Coming up in %s', 'oria' ), esc_html( $oria_cname ) );
				?>
			</p>
			<h2 class="h1" id="xh-week-title"><?php esc_html_e( 'Chosen by how it feels, not by the clock.', 'oria' ); ?></h2>
		</div>
		<a class="xh-link" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ) ); ?>"><?php esc_html_e( 'Everything coming up', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
	</header>
	<div class="xh-week">
		<?php
		foreach ( $oria_events as $oria_i => $oria_ev ) :
			$oria_lead = 0 === $oria_i;
			$oria_evimg = $oria_lead ? get_the_post_thumbnail_url( $oria_ev, 'large' ) : '';
			?>
			<a class="xh-ev<?php echo $oria_lead ? ' xh-ev--lead on-deep' : ''; ?>" href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>">
				<?php if ( $oria_evimg ) : ?>
					<img class="xh-ev__pic" src="<?php echo esc_url( $oria_evimg ); ?>" alt="" loading="lazy" decoding="async">
				<?php endif; ?>
				<span class="xh-ev__body">
					<span class="micro xh-ev__when"><?php echo esc_html( $oria_when( $oria_ev ) ); ?></span>
					<span class="<?php echo $oria_lead ? 'h2' : 'h3'; ?> xh-ev__name"><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev ) ); ?></span>
					<?php $oria_ew = $oria_ev_where( $oria_ev ); ?>
					<?php if ( '' !== $oria_ew ) : ?>
						<span class="xh-ev__where"><?php echo esc_html( $oria_ew ); ?></span>
					<?php endif; ?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $oria_notes ) : ?>
<!-- 6. Oria Field Notes -->
<section class="wrap xh-sec" aria-labelledby="xh-notes-title">
	<header class="xh-sec__head xh-sec__head--rule">
		<h2 class="h1" id="xh-notes-title"><?php esc_html_e( 'Oria Field Notes', 'oria' ); ?></h2>
		<p class="xh-sec__aside"><?php esc_html_e( 'Local, first-hand and specific. The places worth the drive and the questions a booking page never answers.', 'oria' ); ?></p>
	</header>
	<div class="xh-notes">
		<?php
		$oria_lead_note = array_shift( $oria_notes );
		$oria_ln_img    = get_the_post_thumbnail_url( $oria_lead_note, 'large' );
		?>
		<a class="xh-note xh-note--lead on-deep" href="<?php echo esc_url( get_permalink( $oria_lead_note ) ); ?>">
			<?php if ( $oria_ln_img ) : ?>
				<img class="xh-note__pic" src="<?php echo esc_url( $oria_ln_img ); ?>" alt="" loading="lazy" decoding="async">
			<?php endif; ?>
			<span class="xh-note__body">
				<span class="h2 xh-note__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_lead_note ) ); ?></span>
				<?php if ( has_excerpt( $oria_lead_note ) ) : ?>
					<span class="xh-note__lede"><?php echo esc_html( get_the_excerpt( $oria_lead_note ) ); ?></span>
				<?php endif; ?>
			</span>
		</a>
		<div class="xh-notes__side">
			<?php foreach ( $oria_notes as $oria_n ) : ?>
				<a class="xh-note" href="<?php echo esc_url( get_permalink( $oria_n ) ); ?>">
					<span class="h3 xh-note__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_n ) ); ?></span>
					<span class="xh-note__date"><?php echo esc_html( get_the_date( 'j F Y', $oria_n ) ); ?></span>
				</a>
			<?php endforeach; ?>
			<a class="xh-link" href="<?php echo esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'All Field Notes', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- 7. The map -->
<section class="wrap xh-sec" aria-labelledby="xh-map-title">
	<div class="xh-map on-deep">
		<div class="xh-map__text">
			<h2 class="h1" id="xh-map-title"><?php esc_html_e( 'There is something nearby you have not tried yet.', 'oria' ); ?></h2>
			<?php if ( $oria_total ) : ?>
				<p>
					<?php
					/* translators: %s: number of listings */
					printf( esc_html__( '%s places on one map, each checked by hand.', 'oria' ), esc_html( number_format_i18n( $oria_total ) ) );
					?>
				</p>
			<?php endif; ?>
			<a class="btn btn--light" href="<?php echo esc_url( home_url( '/wellness-map/' ) ); ?>"><?php esc_html_e( 'Open the map', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
		</div>
		<img class="xh-map__pic" src="<?php echo esc_url( $oria_img( 'wellness-map-hero-1280.webp' ) ); ?>" alt="" loading="lazy" decoding="async">
	</div>
</section>

<?php if ( count( $oria_cards ) >= 3 ) : ?>
<!-- 8. A few places worth knowing -->
<section class="wrap xh-sec" aria-labelledby="xh-cards-title">
	<header class="xh-sec__head">
		<div>
			<p class="micro"><span class="badge--best__mark" aria-hidden="true">&#10022;</span> <?php esc_html_e( 'From our Best Of guides', 'oria' ); ?></p>
			<h2 class="h1" id="xh-cards-title"><?php esc_html_e( 'A few places worth knowing', 'oria' ); ?></h2>
		</div>
		<a class="xh-link" href="<?php echo esc_url( function_exists( '\Oria\Core\BestOf\hub_url' ) ? \Oria\Core\BestOf\hub_url() : home_url( '/best/' ) ); ?>"><?php esc_html_e( 'Every Best Of guide', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
	</header>
	<div class="xh-cards">
		<?php
		foreach ( $oria_cards as $oria_i => $oria_c ) :
			$oria_lid  = (int) $oria_c['listing'];
			$oria_meta = array_filter( array( $oria_suburb( $oria_lid ), $oria_from( $oria_lid ) ) );
			?>
			<article class="xh-card<?php echo 0 === $oria_i ? ' xh-card--lead' : ''; ?>">
				<p class="micro xh-card__award"><span aria-hidden="true">&#10022;</span> <?php echo esc_html( $oria_c['label'] ); ?></p>
				<h3 class="<?php echo 0 === $oria_i ? 'h1' : 'h3'; ?> xh-card__name"><a href="<?php echo esc_url( get_permalink( $oria_lid ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_lid ) ) ); ?></a></h3>
				<?php if ( $oria_meta ) : ?>
					<p class="xh-card__meta"><?php echo esc_html( implode( ' · ', $oria_meta ) ); ?></p>
				<?php endif; ?>
				<p class="xh-card__why"><?php echo esc_html( $oria_c['reason'] ); ?></p>
				<a class="xh-card__guide" href="<?php echo esc_url( get_permalink( $oria_c['guide'] ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( $oria_c['guide'] ) ); ?></a>
			</article>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<!-- 9. Closing -->
<section class="wrap xh-sec">
	<div class="xh-close on-deep">
		<img class="xh-close__pic" src="<?php echo esc_url( $oria_img( 'scene-perth-skyline.webp' ) ); ?>" alt="" loading="lazy" decoding="async">
		<div class="xh-close__text">
			<h2 class="display xh-close__title"><?php esc_html_e( 'Somewhere nearby, a room is already being prepared.', 'oria' ); ?></h2>
			<div class="xh-actions">
				<a class="btn btn--light" href="<?php echo esc_url( \Oria\Core\Explore\base_url( $oria_city ) ); ?>"><?php esc_html_e( 'Find something for today', 'oria' ); ?></a>
				<a class="btn btn--ghost-on-deep" href="<?php echo esc_url( home_url( '/ask/' ) ); ?>"><?php esc_html_e( 'Ask Oria', 'oria' ); ?></a>
			</div>
		</div>
	</div>
</section>

</div>

<?php
get_footer();
