<?php
/**
 * The Workshops/Events archive at /whats-on-perth/ — the single events
 * page: every upcoming event, filterable by date, suburb, type and price.
 *
 * Member events outrank aggregated ones everywhere: a featured band up
 * top shows practitioners' own events, and within each day group member
 * events sort first with a gold badge. Aggregated events link out and
 * carry a quiet "via {source}" note — the source stays the source of truth.
 *
 * Filtering is client-side: the server stamps each row with precomputed
 * tokens (when/suburb/type/price band) and the JS just shows/hides.
 */

declare(strict_types=1);

use function Oria\Theme\arrow;

get_header();

$oria_now = (int) current_time( 'timestamp' );

$oria_events = get_posts(
	array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array( 'key' => 'event_start', 'value' => gmdate( 'Y-m-d H:i:s', $oria_now ), 'compare' => '>=', 'type' => 'DATETIME' ),
		),
	)
);

// This weekend / next weekend windows (Fri–Sun), same logic as /this-weekend/.
$oria_dow    = (int) gmdate( 'N', $oria_now );
$oria_friday = $oria_dow >= 5 ? $oria_now - ( $oria_dow - 5 ) * DAY_IN_SECONDS : $oria_now + ( 5 - $oria_dow ) * DAY_IN_SECONDS;
$oria_wk_a   = gmdate( 'Y-m-d', $oria_friday );
$oria_wk_b   = gmdate( 'Y-m-d', $oria_friday + 2 * DAY_IN_SECONDS );
$oria_nwk_a  = gmdate( 'Y-m-d', $oria_friday + 7 * DAY_IN_SECONDS );
$oria_nwk_b  = gmdate( 'Y-m-d', $oria_friday + 9 * DAY_IN_SECONDS );

/** Space-separated date tokens the JS filter matches against. */
$oria_when_tokens = static function ( int $ts ) use ( $oria_now, $oria_wk_a, $oria_wk_b, $oria_nwk_a, $oria_nwk_b ): string {
	$d      = gmdate( 'Y-m-d', $ts );
	$tokens = array( 'all' );
	if ( gmdate( 'Y-m-d', $oria_now ) === $d ) {
		$tokens[] = 'today';
	}
	if ( gmdate( 'Y-m-d', $oria_now + DAY_IN_SECONDS ) === $d ) {
		$tokens[] = 'tomorrow';
	}
	if ( $d >= $oria_wk_a && $d <= $oria_wk_b ) {
		$tokens[] = 'weekend';
	}
	if ( $d >= $oria_nwk_a && $d <= $oria_nwk_b ) {
		$tokens[] = 'nextweekend';
	}
	if ( gmdate( 'Y-m', $oria_now ) === gmdate( 'Y-m', $ts ) ) {
		$tokens[] = 'month';
	}
	return implode( ' ', $tokens );
};

$oria_price_band = static function ( string $price ): string {
	if ( '' === trim( $price ) ) {
		return 'unknown';
	}
	if ( preg_match( '/free|donation/i', $price ) ) {
		return 'free';
	}
	if ( ! preg_match( '/(\d+(?:\.\d+)?)/', $price, $m ) ) {
		return 'unknown';
	}
	$n = (float) $m[1];
	if ( $n <= 0 ) {
		return 'free';
	}
	if ( $n < 30 ) {
		return 'under30';
	}
	return $n <= 50 ? '30to50' : '50plus';
};

// Pre-compute every row once; collect filter option lists as we go.
$oria_rows    = array();
$oria_suburbs = array();
$oria_types   = array();
foreach ( $oria_events as $oria_ev ) {
	$oria_ts = strtotime( (string) get_field( 'event_start', $oria_ev->ID ) );
	if ( ! $oria_ts ) {
		continue;
	}
	$oria_is_member = '' === (string) get_post_meta( $oria_ev->ID, '_oria_src', true );

	$oria_suburb = '';
	foreach ( wp_get_post_terms( $oria_ev->ID, 'area' ) as $oria_at ) {
		$oria_suburb = \Oria\Theme\tname( $oria_at );
		if ( $oria_at->parent ) {
			break;
		}
	}
	if ( '' === $oria_suburb ) {
		$oria_venue_parts = array_map( 'trim', explode( ',', (string) get_field( 'venue', $oria_ev->ID ) ) );
		$oria_suburb      = (string) end( $oria_venue_parts );
	}

	/*
	 * Which feelings this event answers, in the Finder's own words. The
	 * registry that decides what "Stress & relaxation" means on the
	 * Wellness Finder decides it here too -- a second list of feelings
	 * would drift from the first within a month.
	 */
	$oria_feel = array();
	if ( function_exists( '\Oria\Core\Finder\needs' ) ) {
		$oria_ev_slugs = array();
		foreach ( wp_get_post_terms( $oria_ev->ID, array( 'practice', 'event_type' ) ) as $oria_ev_t ) {
			$oria_ev_slugs[] = $oria_ev_t->slug;
		}
		foreach ( \Oria\Core\Finder\needs() as $oria_fk => $oria_fn ) {
			$oria_want = array_merge( (array) ( $oria_fn['practices'] ?? array() ), (array) ( $oria_fn['specialties'] ?? array() ) );
			if ( array_intersect( $oria_ev_slugs, $oria_want ) ) {
				$oria_feel[] = $oria_fk;
			}
		}
	}

	$oria_type_terms = wp_get_post_terms( $oria_ev->ID, 'event_type' );
	$oria_type       = ! is_wp_error( $oria_type_terms ) && $oria_type_terms ? $oria_type_terms[0] : null;
	if ( ! $oria_type ) {
		$oria_pr   = wp_get_post_terms( $oria_ev->ID, 'practice' );
		$oria_type = ! is_wp_error( $oria_pr ) && $oria_pr ? $oria_pr[0] : null;
	}

	$oria_price = (string) get_field( 'price', $oria_ev->ID );
	$oria_src   = (string) get_post_meta( $oria_ev->ID, '_oria_src', true );

	if ( '' !== $oria_suburb ) {
		$oria_suburbs[ sanitize_title( $oria_suburb ) ] = $oria_suburb;
	}
	if ( $oria_type ) {
		$oria_types[ $oria_type->slug ] = \Oria\Theme\tname( $oria_type );
	}

	$oria_rows[] = array(
		// Added to Oria in the last seven days -- a fact about this list,
		// never a claim that the event itself is newly announced.
		'fresh'  => ( $oria_now - (int) get_post_time( 'U', true, $oria_ev ) ) < 7 * DAY_IN_SECONDS,
		'feel'   => implode( ' ', $oria_feel ),
		'post'   => $oria_ev,
		'ts'     => $oria_ts,
		'day'    => gmdate( 'Y-m-d', $oria_ts ),
		'member' => $oria_is_member,
		'suburb' => $oria_suburb,
		'type'   => $oria_type,
		'price'  => $oria_price,
		'band'   => $oria_price_band( $oria_price ),
		'when'   => $oria_when_tokens( $oria_ts ),
		'src'    => $oria_src,
	);
}

/*
 * Period groups, members first within each (they pay to be here).
 *
 * By calendar day this produced nine headings for ten events, each holding
 * one card. "This weekend" and "Next week" are also what somebody actually
 * came to ask; a date is a detail they want once they are interested, and
 * every card still carries its own.
 */
$oria_group_of = static function ( int $ts ) use ( $oria_now ): array {
	$today    = (int) strtotime( 'today', $oria_now );
	$sat      = (int) strtotime( 'saturday this week', $today );
	$sun_end  = (int) strtotime( 'sunday this week 23:59:59', $today );
	$week_end = (int) strtotime( '+7 days 23:59:59', $today );

	if ( $ts <= $sun_end && $ts >= $sat - DAY_IN_SECONDS ) {
		// Friday counts: nobody planning a weekend excludes Friday night.
		return array( '1-weekend', __( 'This weekend', 'oria' ) );
	}
	if ( $ts <= $today + DAY_IN_SECONDS ) {
		return array( '0-now', __( 'Today and tomorrow', 'oria' ) );
	}
	if ( $ts <= $week_end ) {
		return array( '2-week', __( 'In the next week', 'oria' ) );
	}
	// Beyond that, the month is a big enough bucket to stay full.
	return array( '3-' . gmdate( 'Y-m', $ts ), gmdate( 'F Y', $ts ) );
};

$oria_days   = array();
$oria_labels = array();
foreach ( $oria_rows as $oria_r ) {
	list( $oria_key, $oria_label ) = $oria_group_of( (int) $oria_r['ts'] );
	$oria_days[ $oria_key ][]      = $oria_r;
	$oria_labels[ $oria_key ]      = $oria_label;
}
ksort( $oria_days );
foreach ( $oria_days as &$oria_list ) {
	usort( $oria_list, static fn( $a, $b ) => array( ! $a['member'], $a['ts'] ) <=> array( ! $b['member'], $b['ts'] ) );
}
unset( $oria_list );

/*
 * The paid band up top, and the same events taken out of the feed below.
 * Showing a featured event twice within one screen reads as a mistake
 * rather than as promotion, which helps nobody — least of all the
 * practice paying for the placement.
 *
 * The band sits inside the filtered section, so a visitor who asks for
 * free events on Saturday doesn't keep seeing a paid Tuesday one. When
 * the filters empty it, the whole band hides with its heading.
 */
$oria_member_rows = array_slice( array_values( array_filter( $oria_rows, static fn( $r ) => $r['member'] ) ), 0, 3 );
$oria_featured_ids = array_map( static fn( $r ) => $r['post']->ID, $oria_member_rows );
foreach ( $oria_days as $oria_key => $oria_list ) {
	$oria_days[ $oria_key ] = array_values( array_filter( $oria_list, static fn( $r ) => ! in_array( $r['post']->ID, $oria_featured_ids, true ) ) );
	if ( ! $oria_days[ $oria_key ] ) {
		unset( $oria_days[ $oria_key ] );
	}
}

asort( $oria_suburbs );
asort( $oria_types );

/*
 * "Last checked" has to be true or it is worse than saying nothing. It is
 * the most recent verification stamp the ingest and import paths write,
 * never today's date for its own sake.
 */
$oria_checked = 0;
foreach ( $oria_rows as $oria_r ) {
	$oria_stamp   = (string) get_post_meta( $oria_r['post']->ID, '_oria_verified', true );
	$oria_checked = max( $oria_checked, $oria_stamp ? (int) strtotime( $oria_stamp ) : 0 );
}
$oria_total = count( $oria_rows );

/*
 * "New this week" only where it is news. After a bulk import most of the
 * page is seven days old and the flag lands on three cards in four, at
 * which point it has stopped telling anyone anything. Above two fifths
 * it is dropped entirely rather than shown as decoration.
 */
$oria_fresh_n = count( array_filter( $oria_rows, static fn( array $r ): bool => ! empty( $r['fresh'] ) ) );
if ( $oria_total > 0 && $oria_fresh_n / $oria_total > 0.4 ) {
	foreach ( $oria_rows as $oria_i => $oria_r ) {
		$oria_rows[ $oria_i ]['fresh'] = false;
	}
	foreach ( $oria_days as $oria_k => $oria_list ) {
		foreach ( $oria_list as $oria_j => $oria_r ) {
			$oria_days[ $oria_k ][ $oria_j ]['fresh'] = false;
		}
	}
	// The featured band took its copy before this ran; PHP copies arrays
	// by value, so it would otherwise keep flags nothing else has.
	foreach ( $oria_member_rows as $oria_j => $oria_r ) {
		$oria_member_rows[ $oria_j ]['fresh'] = false;
	}
}

/** One row. */
$oria_row = static function ( array $r ): void {
	$oria_ev  = $r['post'];
	$oria_t   = '00:00' === gmdate( 'H:i', $r['ts'] ) ? __( 'TBC', 'oria' ) : gmdate( 'g.ia', $r['ts'] );
	?>
	<div class="wkrow<?php echo $r['member'] ? ' wkrow--member' : ''; ?><?php echo $r['fresh'] ? ' wkrow--fresh' : ''; ?>"
		data-when="<?php echo esc_attr( $r['when'] ); ?>"
		data-day="<?php echo esc_attr( gmdate( 'Y-m-d', $r['ts'] ) ); ?>"
		data-suburb="<?php echo esc_attr( sanitize_title( $r['suburb'] ) ); ?>"
		data-type="<?php echo esc_attr( $r['type'] ? $r['type']->slug : '' ); ?>"
		data-band="<?php echo esc_attr( $r['band'] ); ?>"
		data-feel="<?php echo esc_attr( (string) ( $r['feel'] ?? '' ) ); ?>">
		<span class="wkrow__thumb" aria-hidden="true">
			<?php if ( has_post_thumbnail( $oria_ev ) ) : ?>
				<?php
				/*
				 * medium_large, not thumbnail. The card is up to ~26rem wide
				 * and 150px stretched into it is the reason these pictures
				 * looked like placeholders.
				 */
				echo get_the_post_thumbnail( $oria_ev, 'medium_large', array( 'loading' => 'lazy', 'alt' => '' ) );
				?>
			<?php else : ?>
				<img class="wkrow__scene" src="<?php echo esc_url( \Oria\Theme\event_scene( $oria_ev->ID ) ); ?>" alt="" loading="lazy" decoding="async">
			<?php endif; ?>
			<time class="wkrow__time"><?php echo esc_html( $oria_t ); ?></time>
		</span>
		<span class="wkrow__body">
			<b>
				<a class="wkrow__link" href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev ) ); ?></a>
				<?php if ( $r['fresh'] ) : ?><i class="wkrow__new"><?php esc_html_e( 'New this week', 'oria' ); ?></i><?php endif; ?>
				<?php if ( $r['member'] ) : ?><i class="wkrow__flag"><?php esc_html_e( 'Featured practice', 'oria' ); ?></i><?php endif; ?>
			</b>
			<em>
				<?php
				// The day, then where and what. The group heading gives the
				// rough when; this gives the exact one.
				echo esc_html( implode( ' · ', array_filter( array(
					gmdate( 'D j M', $r['ts'] ),
					$r['suburb'],
					$r['type'] ? \Oria\Theme\tname( $r['type'] ) : '',
				) ) ) );
				?>
				<?php if ( ! $r['member'] && $r['src'] ) : ?><span class="wkrow__src"><?php echo esc_html( sprintf( __( 'via %s', 'oria' ), $r['src'] ) ); ?></span><?php endif; ?>
			</em>
		</span>
		<?php if ( $r['price'] ) : ?><span class="wkrow__price"><?php echo esc_html( $r['price'] ); ?></span><?php endif; ?>
		<?php
		/*
		 * Saving sits above the card's own link rather than inside it --
		 * a button in an anchor is invalid, and a tap on it would follow
		 * the link before it saved anything. Its accessible name carries
		 * the event's title so a screen reader hears which of twenty-two
		 * Save buttons this is.
		 */
		?>
		<button class="wksave" type="button" aria-pressed="false"
			data-save-event="<?php echo (int) $oria_ev->ID; ?>"
			data-title="<?php echo esc_attr( \Oria\Theme\ptitle( $oria_ev ) ); ?>"
			data-url="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>"
			data-when="<?php echo esc_attr( gmdate( 'D j M', $r['ts'] ) . ( '00:00' === gmdate( 'H:i', $r['ts'] ) ? '' : ', ' . $oria_t ) ); ?>"
			data-where="<?php echo esc_attr( (string) $r['suburb'] ); ?>"
			aria-label="<?php echo esc_attr( sprintf( /* translators: %s: event title */ __( 'Save %s', 'oria' ), \Oria\Theme\ptitle( $oria_ev ) ) ); ?>">
			<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M5 2.75h10a1 1 0 0 1 1 1v13.6a.4.4 0 0 1-.62.33L10 14.3l-5.38 3.38a.4.4 0 0 1-.62-.33V3.75a1 1 0 0 1 1-1Z"/></svg>
			<span class="xp-vh savebtn__label"><?php esc_html_e( 'Save', 'oria' ); ?></span>
		</button>
		<span class="wkrow__go" aria-hidden="true"><?php echo arrow(); // phpcs:ignore ?></span>
	</div>
	<?php
};
?>

<?php
// The count and the freshness line, built before the header so the band can
// carry them as its eyebrow.
ob_start();
?>
			<span class="micro">
				<?php
				// Count and freshness, both measured rather than asserted.
				echo esc_html( sprintf( _n( '%d upcoming event', '%d upcoming events', $oria_total, 'oria' ), $oria_total ) );
				if ( $oria_checked ) {
					$oria_today = (int) strtotime( 'today', $oria_now );
					echo ' · ' . esc_html(
						$oria_checked >= $oria_today
							? __( 'checked today', 'oria' )
							: sprintf( /* translators: %s: date */ __( 'last checked %s', 'oria' ), gmdate( 'j F', $oria_checked ) )
					);
				}
				?>
			</span>
<?php
$oria_eyebrow = trim( wp_strip_all_tags( (string) ob_get_clean() ) );

ob_start();
?>
	<a class="btn btn--sm btn--dark" href="<?php echo esc_url( home_url( '/this-weekend/' ) ); ?>"><?php esc_html_e( 'Just this weekend', 'oria' ); ?></a>
	<?php /* Organisers need this at the top, not buried at the foot of the page. */ ?>
	<a class="btn btn--sm" href="<?php echo esc_url( home_url( '/submit-an-event/' ) ); ?>"><?php esc_html_e( 'Submit an event', 'oria' ); ?></a>
<?php
$oria_actions = (string) ob_get_clean();

get_template_part(
	'template-parts/event-hero',
	null,
	array(
		'img'     => 'hero',
		'crumbs'  => array( __( 'Home', 'oria' ) => home_url( '/' ), __( "What's On", 'oria' ) => '' ),
		'eyebrow' => $oria_eyebrow,
		'title'   => __( 'Wellness events and workshops in Perth', 'oria' ),
		'lede'    => __( 'Sound baths, yoga workshops, breathwork, day retreats and free community sessions — hand-checked, from our member practices and from around Perth.', 'oria' ),
		'actions' => $oria_actions,
	)
);
?>


<?php
/*
 * Browse by type. Built from the types that actually have something on,
 * biggest first, capped at six: a row of eight tiles where three lead to
 * one event each is a worse page than a row of four that all deliver.
 *
 * A tile only appears when it has both events and a picture. The pictures
 * live in the theme (assets/img/event/), like the category heroes, so they
 * exist on production the moment the code lands.
 */
$oria_tile_counts = array();
foreach ( $oria_rows as $oria_r ) {
	if ( $oria_r['type'] ) {
		$oria_tile_counts[ $oria_r['type']->slug ] = ( $oria_tile_counts[ $oria_r['type']->slug ] ?? 0 ) + 1;
	}
}
arsort( $oria_tile_counts );

$oria_tile_dir = get_stylesheet_directory() . '/assets/img/event/';
$oria_tile_uri = get_stylesheet_directory_uri() . '/assets/img/event/';
$oria_tiles    = array();
foreach ( $oria_tile_counts as $oria_slug => $oria_n ) {
	if ( count( $oria_tiles ) >= 6 || ! file_exists( $oria_tile_dir . $oria_slug . '-900.webp' ) ) {
		continue;
	}
	$oria_term_obj = get_term_by( 'slug', $oria_slug, 'event_type' );
	if ( ! $oria_term_obj instanceof WP_Term ) {
		continue;
	}
	$oria_tiles[] = array(
		'slug'  => $oria_slug,
		'name'  => \Oria\Theme\tname( $oria_term_obj ),
		'count' => $oria_n,
	);
}
?>
<?php if ( count( $oria_tiles ) >= 3 ) : ?>
	<section class="wrap section section--top-flush">
		<h2 class="h3" style="margin-bottom:var(--s-4)"><?php esc_html_e( 'What kind of thing are you after?', 'oria' ); ?></h2>
		<div class="evtypes">
			<?php foreach ( $oria_tiles as $oria_t ) : ?>
				<a class="evtype" href="<?php echo esc_url( add_query_arg( 'type', $oria_t['slug'], get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ) ) ); ?>">
					<img class="evtype__img" src="<?php echo esc_url( $oria_tile_uri . $oria_t['slug'] . '-600.webp' ); ?>"
						srcset="<?php echo esc_url( $oria_tile_uri . $oria_t['slug'] . '-600.webp' ); ?> 600w, <?php echo esc_url( $oria_tile_uri . $oria_t['slug'] . '-900.webp' ); ?> 900w"
						sizes="(max-width: 700px) 45vw, 22vw" alt="" loading="lazy" decoding="async" width="600" height="450">
					<span class="evtype__body">
						<b><?php echo esc_html( $oria_t['name'] ); ?></b>
						<span class="evtype__n"><?php echo esc_html( sprintf( _n( '%d event', '%d events', $oria_t['count'], 'oria' ), $oria_t['count'] ) ); ?></span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
/*
 * "How do you want to feel?" -- the Finder's question, asked of this
 * week's events. Only feelings something on this page actually answers:
 * a tile reading "0 experiences" is an empty shelf with a label on it.
 */
$oria_feels = array();
if ( function_exists( '\Oria\Core\Finder\needs' ) && function_exists( '\Oria\Core\Finder\questions' ) ) {
	$oria_fq = \Oria\Core\Finder\questions();
	foreach ( \Oria\Core\Finder\needs() as $oria_fk => $oria_fn ) {
		$oria_fc = 0;
		foreach ( $oria_rows as $oria_r ) {
			if ( in_array( $oria_fk, explode( ' ', (string) ( $oria_r['feel'] ?? '' ) ), true ) ) {
				++$oria_fc;
			}
		}
		if ( $oria_fc < 1 ) {
			continue;
		}
		$oria_feels[] = array(
			'key'   => $oria_fk,
			'label' => (string) ( $oria_fq['for']['options'][ $oria_fk ] ?? $oria_fk ),
			'count' => $oria_fc,
		);
	}
	usort( $oria_feels, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] );
}
?>

<section class="wrap section section--top-flush" data-whatson>
	<?php if ( count( $oria_feels ) >= 3 ) : ?>
		<div class="wofeels">
			<h2 class="h3 wofeels__title"><?php esc_html_e( 'How do you want to feel?', 'oria' ); ?></h2>
			<div class="wofeels__row" role="group" aria-label="<?php esc_attr_e( 'Filter by how you want to feel', 'oria' ); ?>">
				<?php foreach ( $oria_feels as $oria_f ) : ?>
					<button class="wofeel wofeel--<?php echo esc_attr( $oria_f['key'] ); ?>" type="button"
						aria-pressed="false" data-f="feel" data-v="<?php echo esc_attr( $oria_f['key'] ); ?>">
						<span class="wofeel__name"><?php echo esc_html( $oria_f['label'] ); ?></span>
						<span class="wofeel__n">
							<?php
							printf(
								/* translators: %d: number of events */
								esc_html( _n( '%d experience', '%d experiences', $oria_f['count'], 'oria' ) ),
								(int) $oria_f['count']
							);
							?>
						</span>
					</button>
				<?php endforeach; ?>
			</div>
			<p class="wofeels__now" data-wo-feelnote hidden></p>
		</div>
	<?php endif; ?>

	<?php
	/*
	 * The next ten days as real dates, each with what is actually on it.
	 * A day with nothing is shown greyed rather than hidden: the gap is
	 * information, and hiding it makes the strip jump around as events
	 * come and go.
	 */
	$oria_strip = array();
	for ( $oria_d = 0; $oria_d < 10; $oria_d++ ) {
		$oria_dts   = strtotime( 'today +' . $oria_d . ' days', $oria_now );
		$oria_key   = gmdate( 'Y-m-d', $oria_dts );
		$oria_count = 0;
		foreach ( $oria_rows as $oria_r ) {
			if ( gmdate( 'Y-m-d', $oria_r['ts'] ) === $oria_key ) {
				++$oria_count;
			}
		}
		$oria_strip[] = array(
			'key'   => $oria_key,
			'top'   => 0 === $oria_d ? __( 'Today', 'oria' ) : ( 1 === $oria_d ? __( 'Tmrw', 'oria' ) : gmdate( 'D', $oria_dts ) ),
			'day'   => gmdate( 'j', $oria_dts ),
			'count' => $oria_count,
		);
	}
	?>
	<div class="wodates" role="group" aria-label="<?php esc_attr_e( 'Jump to a date', 'oria' ); ?>">
		<button class="wodate is-on" type="button" aria-pressed="true" data-f="day" data-v="">
			<span class="wodate__top"><?php esc_html_e( 'All', 'oria' ); ?></span>
			<span class="wodate__day"><?php echo esc_html( (string) count( $oria_rows ) ); ?></span>
		</button>
		<?php foreach ( $oria_strip as $oria_s ) : ?>
			<button class="wodate<?php echo 0 === $oria_s['count'] ? ' is-empty' : ''; ?>" type="button" aria-pressed="false"
				data-f="day" data-v="<?php echo esc_attr( $oria_s['key'] ); ?>"
				<?php echo 0 === $oria_s['count'] ? 'disabled aria-disabled="true"' : ''; ?>>
				<span class="wodate__top"><?php echo esc_html( $oria_s['top'] ); ?></span>
				<span class="wodate__day"><?php echo esc_html( $oria_s['day'] ); ?></span>
				<span class="wodate__n"><?php echo esc_html( $oria_s['count'] ? (string) $oria_s['count'] : '·' ); ?></span>
			</button>
		<?php endforeach; ?>
	</div>

	<div class="wofilters">
		<div class="wofilters__row" role="group" aria-label="<?php esc_attr_e( 'Filter by date', 'oria' ); ?>">
			<?php
			foreach ( array(
				'all'         => __( 'All dates', 'oria' ),
				'today'       => __( 'Today', 'oria' ),
				'tomorrow'    => __( 'Tomorrow', 'oria' ),
				'weekend'     => __( 'This weekend', 'oria' ),
				'nextweekend' => __( 'Next weekend', 'oria' ),
				'month'       => __( 'This month', 'oria' ),
			) as $oria_val => $oria_label ) :
				?>
				<button class="fchip<?php echo 'all' === $oria_val ? ' is-on' : ''; ?>" type="button" aria-pressed="<?php echo 'all' === $oria_val ? 'true' : 'false'; ?>" data-f="when" data-v="<?php echo esc_attr( $oria_val ); ?>"><?php echo esc_html( $oria_label ); ?></button>
			<?php endforeach; ?>
		</div>
		<div class="wofilters__row">
			<select class="select select--sm" data-f="suburb" aria-label="<?php esc_attr_e( 'Suburb', 'oria' ); ?>">
				<option value=""><?php esc_html_e( 'All suburbs', 'oria' ); ?></option>
				<?php foreach ( $oria_suburbs as $oria_slug => $oria_name ) : ?>
					<option value="<?php echo esc_attr( $oria_slug ); ?>"><?php echo esc_html( $oria_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<select class="select select--sm" data-f="type" aria-label="<?php esc_attr_e( 'Type', 'oria' ); ?>">
				<option value=""><?php esc_html_e( 'All types', 'oria' ); ?></option>
				<?php foreach ( $oria_types as $oria_slug => $oria_name ) : ?>
					<option value="<?php echo esc_attr( $oria_slug ); ?>"><?php echo esc_html( $oria_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			foreach ( array(
				''        => __( 'Any price', 'oria' ),
				'free'    => __( 'Free', 'oria' ),
				'under30' => __( 'Under $30', 'oria' ),
				'30to50'  => __( '$30–$50', 'oria' ),
				'50plus'  => __( '$50+', 'oria' ),
			) as $oria_val => $oria_label ) :
				?>
				<button class="fchip<?php echo '' === $oria_val ? ' is-on' : ''; ?>" type="button" aria-pressed="<?php echo '' === $oria_val ? 'true' : 'false'; ?>" data-f="band" data-v="<?php echo esc_attr( $oria_val ); ?>"><?php echo esc_html( $oria_label ); ?></button>
			<?php endforeach; ?>
		</div>
	</div>

	<?php
	/*
	 * The suburbs on this page that have a guide worth visiting. Built from
	 * the events actually listed, so the strip can never offer a
	 * neighbourhood with nothing in it -- AreaContext returns null for a
	 * suburb too thin to have a page.
	 */
	$oria_area_map = array();
	if ( function_exists( '\Oria\Core\AreaContext\for_post' ) ) {
		foreach ( $oria_rows as $oria_r ) {
			$oria_key = sanitize_title( (string) $oria_r['suburb'] );
			if ( '' === $oria_key || isset( $oria_area_map[ $oria_key ] ) ) {
				continue;
			}
			$oria_ctx = \Oria\Core\AreaContext\for_post( (int) $oria_r['post']->ID );
			if ( ! $oria_ctx ) {
				continue;
			}
			$oria_area_map[ $oria_key ] = array(
				'name'   => (string) $oria_ctx['name'],
				'url'    => (string) $oria_ctx['url'],
				'places' => (int) $oria_ctx['places'],
				'slug'   => (string) $oria_ctx['slug'],
			);
		}
	}
	if ( $oria_area_map ) :
		?>
		<script type="application/json" data-wo-areas><?php echo wp_json_encode( $oria_area_map ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON in a data block. ?></script>
	<?php endif; ?>
	<div class="areastrip" data-wo-areastrip hidden>
		<p class="areastrip__text">
			<span class="areastrip__eyebrow" data-wo-area-title></span>
			<span data-wo-area-line></span>
		</p>
		<a class="areastrip__cta btn btn--sm" href="#" data-wo-area-cta data-area-promo="whats-on-filter"></a>
	</div>

	<div class="wotoolbar" data-wo-toolbar>
		<p class="wotoolbar__count" data-wo-count role="status" aria-live="polite"
			data-one="<?php esc_attr_e( '%d event', 'oria' ); ?>"
			data-many="<?php esc_attr_e( '%d events', 'oria' ); ?>"
			data-none="<?php esc_attr_e( 'No events match', 'oria' ); ?>">
			<?php echo esc_html( sprintf( _n( '%d event', '%d events', $oria_total, 'oria' ), $oria_total ) ); ?>
		</p>
		<button class="wotoolbar__clear" type="button" data-wo-clear hidden><?php esc_html_e( 'Clear filters', 'oria' ); ?></button>
	</div>

	<?php if ( $oria_member_rows ) : ?>
		<?php
		/*
		 * One, two or three: the band lays itself out to the number it has
		 * rather than leaving a third of a panel empty. A single featured
		 * event becomes a wide editorial card instead of a lonely tile.
		 */
		?>
		<div class="featband featband--<?php echo count( $oria_member_rows ); ?> wogroup" data-wo-feat>
			<p class="featband__label">
				<span class="badge-dot" aria-hidden="true"></span>
				<span class="micro"><?php esc_html_e( 'Featured practices', 'oria' ); ?></span>
				<span class="featband__note"><?php esc_html_e( 'Paid placement by practices listed with us', 'oria' ); ?></span>
			</p>
			<div class="wkrows">
				<?php foreach ( $oria_member_rows as $oria_r ) { $oria_row( $oria_r ); } ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $oria_days ) : ?>
		<div class="stack-lg" style="margin-top:2rem">
			<?php foreach ( $oria_days as $oria_day => $oria_list ) : ?>
				<div class="wogroup">
					<h2 class="h3 wkday">
						<?php echo esc_html( $oria_labels[ $oria_day ] ?? '' ); ?>
						<span class="wkday__date" data-wo-group-count
								data-one="<?php esc_attr_e( '%d event', 'oria' ); ?>"
								data-many="<?php esc_attr_e( '%d events', 'oria' ); ?>"><?php echo esc_html( sprintf( _n( '%d event', '%d events', count( $oria_list ), 'oria' ), count( $oria_list ) ) ); ?></span>
					</h2>
					<div class="wkrows">
						<?php foreach ( $oria_list as $oria_r ) { $oria_row( $oria_r ); } ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="dir__empty" data-wo-empty hidden style="margin-top:2rem">
			<h2 class="h3"><?php esc_html_e( 'Nothing matches those filters yet', 'oria' ); ?></h2>
			<p class="muted" style="margin-top:.5rem"><?php esc_html_e( 'Try widening the dates or the area — or start again and browse everything coming up.', 'oria' ); ?></p>
			<p class="wo-empty-area" data-wo-empty-area hidden></p>
			<p style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem">
				<button class="btn btn--sm btn--dark" type="button" data-wo-clear><?php esc_html_e( 'Clear filters', 'oria' ); ?></button>
				<a class="btn btn--sm" href="#" data-wo-empty-cta data-area-promo="whats-on-empty" hidden></a>
				<a class="btn btn--sm" href="<?php echo esc_url( home_url( '/submit-an-event/' ) ); ?>"><?php esc_html_e( 'Submit an event', 'oria' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<div class="dir__empty" style="margin-top:2rem">
			<h2 class="h3"><?php esc_html_e( 'Nothing listed yet', 'oria' ); ?></h2>
			<p class="muted" style="margin-top:.5rem"><?php esc_html_e( 'New events go up as we find them and as organisers send them in.', 'oria' ); ?></p>
			<p style="margin-top:1rem"><a class="btn btn--sm btn--dark" href="<?php echo esc_url( home_url( '/submit-an-event/' ) ); ?>"><?php esc_html_e( 'Submit an event', 'oria' ); ?></a></p>
		</div>
	<?php endif; ?>
</section>

<?php
/*
 * Where this week's events actually are. Counted off the page's own rows
 * rather than queried again, so the numbers here and the numbers in the
 * list can never disagree -- and only suburbs whose guide is worth
 * opening, which is AreaContext's decision, not this template's.
 */
$oria_near = array();
if ( function_exists( '\Oria\Core\AreaContext\for_post' ) ) {
	foreach ( $oria_rows as $oria_r ) {
		$oria_ctx = \Oria\Core\AreaContext\for_post( (int) $oria_r['post']->ID );
		if ( ! $oria_ctx ) {
			continue;
		}
		$oria_k = (string) $oria_ctx['slug'];
		if ( ! isset( $oria_near[ $oria_k ] ) ) {
			$oria_near[ $oria_k ] = array(
				'name'  => (string) $oria_ctx['name'],
				'url'   => (string) $oria_ctx['url'],
				'n'     => 0,
				'next'  => $oria_r['ts'],
			);
		}
		++$oria_near[ $oria_k ]['n'];
		$oria_near[ $oria_k ]['next'] = min( $oria_near[ $oria_k ]['next'], $oria_r['ts'] );
	}
	uasort( $oria_near, static fn( array $a, array $b ): int => $b['n'] <=> $a['n'] ?: $a['next'] <=> $b['next'] );
	$oria_near = array_slice( $oria_near, 0, 6, true );
}
?>
<?php if ( count( $oria_near ) >= 3 ) : ?>
	<section class="wrap section section--top-flush" aria-labelledby="woNearTitle">
		<h2 class="h3" id="woNearTitle" style="margin-bottom:var(--s-4)"><?php esc_html_e( 'What’s happening near you?', 'oria' ); ?></h2>
		<div class="wonear">
			<?php foreach ( $oria_near as $oria_slug => $oria_a ) : ?>
				<a class="wonear__card" href="<?php echo esc_url( $oria_a['url'] ); ?>"
					data-area-promo="whats-on-near" data-area-slug="<?php echo esc_attr( (string) $oria_slug ); ?>">
					<b class="wonear__name"><?php echo esc_html( $oria_a['name'] ); ?></b>
					<span class="wonear__n">
						<?php
						printf(
							/* translators: %d: number of events */
							esc_html( _n( '%d event coming up', '%d events coming up', (int) $oria_a['n'], 'oria' ) ),
							(int) $oria_a['n']
						);
						?>
					</span>
					<span class="wonear__next">
						<?php
						printf(
							/* translators: %s: date of the next event */
							esc_html__( 'Next on %s', 'oria' ),
							esc_html( gmdate( 'D j M', (int) $oria_a['next'] ) )
						);
						?>
						<span class="wonear__arrow" aria-hidden="true">&rarr;</span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
/*
 * The collections that are live today. EventCollections keeps its own
 * floor -- a collection with too little in it has no page -- so this
 * shows whatever survived that and nothing else.
 */
$oria_colls = array();
if ( function_exists( '\Oria\Core\EventCollections\all' ) ) {
	foreach ( \Oria\Core\EventCollections\all() as $oria_cslug => $oria_crow ) {
		if ( ! \Oria\Core\EventCollections\live( (string) $oria_cslug ) ) {
			continue;
		}
		$oria_colls[] = array(
			'title' => (string) ( $oria_crow['title'] ?? $oria_cslug ),
			'url'   => \Oria\Core\EventCollections\url( (string) $oria_cslug ),
			'count' => count( \Oria\Core\EventCollections\event_ids( (string) $oria_cslug, 50 ) ),
		);
	}
}
?>
<?php if ( count( $oria_colls ) >= 2 ) : ?>
	<section class="wrap section section--top-flush" aria-labelledby="woCollTitle">
		<h2 class="h3" id="woCollTitle" style="margin-bottom:var(--s-4)"><?php esc_html_e( 'Ways in', 'oria' ); ?></h2>
		<div class="wocolls">
			<?php foreach ( $oria_colls as $oria_c ) : ?>
				<a class="wocoll" href="<?php echo esc_url( (string) $oria_c['url'] ); ?>">
					<b class="wocoll__name"><?php echo esc_html( (string) $oria_c['title'] ); ?></b>
					<?php if ( ! empty( $oria_c['count'] ) ) : ?>
						<span class="wocoll__n">
							<?php
							printf(
								/* translators: %d: number of events */
								esc_html( _n( '%d event', '%d events', (int) $oria_c['count'], 'oria' ) ),
								(int) $oria_c['count']
							);
							?>
						</span>
					<?php endif; ?>
					<span class="wocoll__arrow" aria-hidden="true">&rarr;</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<section class="evband">
	<div class="evband__inner">
		<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'Run wellness events in Perth?', 'oria' ); ?></b>
		<p style="font-size:.875rem;color:var(--text-soft)">
			<?php esc_html_e( 'Tell us about it and we will put it on this page. It is free, there is no account to make, and a person reads every submission.', 'oria' ); ?>
		</p>
		<p style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.8rem">
			<a class="btn btn--dark btn--sm" href="<?php echo esc_url( home_url( '/submit-an-event/' ) ); ?>"><?php esc_html_e( 'Submit an event', 'oria' ); ?></a>
			<a class="btn btn--sm" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'Claim your listing', 'oria' ); ?></a>
		</p>
		<p class="evband__note">
			<?php esc_html_e( 'Claimed practices also get their events at the top of this page, with a photo and a linked profile.', 'oria' ); ?>
		</p>
	</div>
</section>

<?php
get_footer();
