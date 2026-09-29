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
 *
 * Layout (2026-09 "serenity" redesign, scoped under .oria-whats-on in
 * assets/css/whats-on.css): a split hero, six activity tiles, one filter
 * surface (When / Where / Price / More filters, with shortcuts and
 * removable chips), then image-top cards in period groups. Cancelled
 * events are left out, as everywhere else the site offers events.
 */

declare(strict_types=1);

wp_enqueue_style( 'oria-whats-on', get_template_directory_uri() . '/assets/css/whats-on.css', array(), (string) filemtime( get_template_directory() . '/assets/css/whats-on.css' ) );

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
	// A cancelled event keeps its own page for anyone who booked, but it is
	// not something to offer. Postponed and sold out stay, labelled.
	$oria_status = function_exists( '\Oria\Core\Events\status' ) ? \Oria\Core\Events\status( (int) $oria_ev->ID ) : '';
	if ( 'cancelled' === $oria_status ) {
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
		/*
		 * Where it is, when we know. Geo refuses a point it cannot place
		 * inside the area this site covers, so an event either has real
		 * coordinates or none -- never a guess at the middle of the state.
		 */
		'pos'    => function_exists( '\Oria\Core\Geo\position' ) ? \Oria\Core\Geo\position( $oria_ev->ID ) : null,
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
		'status' => $oria_status,
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

/*
 * Small line icons for the cards and filters; decoration only.
 */
$oria_ico = static function ( string $name ): string {
	$p = array(
		'cal'    => '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
		'pin'    => '<path d="M12 21s-6.5-6.1-6.5-11A6.5 6.5 0 0 1 18.5 10c0 4.9-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
		'tag'    => '<path d="M3.5 12.2V4.5a1 1 0 0 1 1-1h7.7l8.3 8.3a1.4 1.4 0 0 1 0 2l-6.7 6.7a1.4 1.4 0 0 1-2 0z"/><circle cx="8" cy="8" r="1.5"/>',
		'sliders'=> '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
		'grid'   => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
		'map'    => '<path d="M9 4 3.5 6v14L9 18l6 2 5.5-2V4L15 6z"/><path d="M9 4v14M15 6v14"/>',
	);
	return '<svg class="wo-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $p[ $name ] ?? '' ) . '</svg>';
};

/** One event card. The card is a <div>; its link covers it via ::after, so
 * the save button can be a real button beside it rather than inside it. */
$oria_row = static function ( array $r ) use ( $oria_ico ): void {
	$oria_ev    = $r['post'];
	$oria_title = \Oria\Theme\ptitle( $oria_ev );
	$oria_tbc   = '00:00' === gmdate( 'H:i', $r['ts'] );
	$oria_t     = $oria_tbc ? '' : gmdate( 'g.ia', $r['ts'] );

	// Where: the venue's own name, then the suburb it is in. A suburb on its
	// own is never dressed up as the address.
	$oria_venue = trim( (string) get_field( 'venue', $oria_ev->ID ) );
	$oria_place = '' !== $oria_venue ? trim( explode( ',', $oria_venue )[0] ) : '';
	$oria_where = implode( ', ', array_unique( array_filter( array( $oria_place, (string) $r['suburb'] ) ) ) );

	// Price as the organiser gave it. Missing is not free.
	$oria_price = trim( (string) $r['price'] );
	if ( '' === $oria_price ) {
		$oria_price_label = __( 'Check price', 'oria' );
	} elseif ( 'free' === $r['band'] && preg_match( '/^\$?0(\.00)?$/', $oria_price ) ) {
		$oria_price_label = __( 'Free', 'oria' );
	} else {
		$oria_price_label = $oria_price;
	}

	// A poster (portrait or square artwork) is shown whole, not cropped.
	$oria_poster = false;
	$oria_thumb  = (int) get_post_thumbnail_id( $oria_ev );
	if ( $oria_thumb ) {
		$oria_meta = wp_get_attachment_metadata( $oria_thumb );
		if ( ! empty( $oria_meta['width'] ) && ! empty( $oria_meta['height'] ) ) {
			$oria_poster = ( (int) $oria_meta['height'] / (int) $oria_meta['width'] ) >= 0.9;
		}
	}
	$oria_status = (string) ( $r['status'] ?? '' );
	$oria_status_labels = array(
		'postponed' => __( 'Postponed', 'oria' ),
		'sold-out'  => __( 'Sold out', 'oria' ),
	);
	?>
	<div class="wkrow wocard<?php echo $r['member'] ? ' wkrow--member' : ''; ?><?php echo $r['fresh'] ? ' wkrow--fresh' : ''; ?><?php echo $oria_poster ? ' wocard--poster' : ''; ?>"
		data-when="<?php echo esc_attr( $r['when'] ); ?>"
		data-day="<?php echo esc_attr( gmdate( 'Y-m-d', $r['ts'] ) ); ?>"
		data-suburb="<?php echo esc_attr( sanitize_title( $r['suburb'] ) ); ?>"
		data-type="<?php echo esc_attr( $r['type'] ? $r['type']->slug : '' ); ?>"
		data-band="<?php echo esc_attr( $r['band'] ); ?>"
		data-feel="<?php echo esc_attr( (string) ( $r['feel'] ?? '' ) ); ?>"
		<?php if ( ! empty( $r['pos'] ) ) : ?>
			data-lat="<?php echo esc_attr( (string) $r['pos']['lat'] ); ?>"
			data-lng="<?php echo esc_attr( (string) $r['pos']['lng'] ); ?>"
			data-precision="<?php echo esc_attr( (string) $r['pos']['precision'] ); ?>"
		<?php endif; ?>>
		<div class="wocard__media">
			<?php if ( $oria_thumb ) : ?>
				<?php echo get_the_post_thumbnail( $oria_ev, 'medium_large', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '', 'sizes' => '(max-width: 40rem) 100vw, (max-width: 64rem) 50vw, 400px' ) ); ?>
			<?php else : ?>
				<img class="wocard__logo" src="<?php echo esc_url( \Oria\Theme\event_logo_placeholder( 600 ) ); ?>" srcset="<?php echo esc_url( \Oria\Theme\event_logo_placeholder( 600 ) ); ?> 600w, <?php echo esc_url( \Oria\Theme\event_logo_placeholder( 1200 ) ); ?> 1200w" sizes="(max-width: 40rem) 100vw, (max-width: 64rem) 50vw, 400px" width="600" height="400" alt="" loading="lazy" decoding="async">
			<?php endif; ?>
			<span class="wocard__date" aria-hidden="true">
				<span class="wocard__dow"><?php echo esc_html( gmdate( 'D', $r['ts'] ) ); ?></span>
				<span class="wocard__dm"><?php echo esc_html( gmdate( 'j M', $r['ts'] ) ); ?></span>
			</span>
			<?php if ( $r['member'] ) : ?>
				<span class="wocard__sponsor"><?php esc_html_e( 'Featured practice', 'oria' ); ?></span>
			<?php endif; ?>
		</div>
		<button class="wksave wocard__save" type="button" aria-pressed="false"
			data-save-event="<?php echo (int) $oria_ev->ID; ?>"
			data-title="<?php echo esc_attr( $oria_title ); ?>"
			data-url="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>"
			data-when="<?php echo esc_attr( gmdate( 'D j M', $r['ts'] ) . ( $oria_tbc ? '' : ', ' . $oria_t ) ); ?>"
			data-where="<?php echo esc_attr( (string) $r['suburb'] ); ?>"
			aria-label="<?php echo esc_attr( sprintf( /* translators: %s: event title */ __( 'Save %s', 'oria' ), $oria_title ) ); ?>">
			<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M5 2.75h10a1 1 0 0 1 1 1v13.6a.4.4 0 0 1-.62.33L10 14.3l-5.38 3.38a.4.4 0 0 1-.62-.33V3.75a1 1 0 0 1 1-1Z"/></svg>
			<span class="sr-only savebtn__label"><?php esc_html_e( 'Save', 'oria' ); ?></span>
		</button>
		<div class="wocard__body">
			<p class="wocard__kicker">
				<?php if ( $r['type'] ) : ?><span><?php echo esc_html( \Oria\Theme\tname( $r['type'] ) ); ?></span><?php endif; ?>
				<?php if ( $r['fresh'] ) : ?><span class="wocard__new"><?php esc_html_e( 'New this week', 'oria' ); ?></span><?php endif; ?>
				<?php if ( isset( $oria_status_labels[ $oria_status ] ) ) : ?><span class="wocard__status"><?php echo esc_html( $oria_status_labels[ $oria_status ] ); ?></span><?php endif; ?>
			</p>
			<h3 class="wocard__title"><a class="wkrow__link" href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>"><?php echo esc_html( $oria_title ); ?></a></h3>
			<ul class="wocard__facts" data-wo-meta>
				<li><?php echo $oria_ico( 'cal' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><span><?php echo esc_html( gmdate( 'D j M', $r['ts'] ) ); ?> · <?php echo esc_html( $oria_tbc ? __( 'Time to be confirmed', 'oria' ) : $oria_t ); ?></span></li>
				<?php if ( '' !== $oria_where ) : ?>
					<li><?php echo $oria_ico( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><span><?php echo esc_html( $oria_where ); ?></span></li>
				<?php endif; ?>
			</ul>
			<p class="wocard__foot">
				<span class="wocard__price"><?php echo esc_html( $oria_price_label ); ?></span>
				<span class="wocard__go" aria-hidden="true"><?php esc_html_e( 'View event', 'oria' ); ?> <span class="wocard__arrow">&rarr;</span></span>
			</p>
			<?php if ( ! $r['member'] && $r['src'] ) : ?>
				<p class="wocard__src"><?php echo esc_html( sprintf( /* translators: %s: source website */ __( 'Listed via %s', 'oria' ), $r['src'] ) ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
};

/*
 * Activity tiles: the event types that actually have something on, biggest
 * first, six at most, each with its own picture. Selecting one applies the
 * same Activity filter as the dropdown; the href is the plain ?type= URL,
 * so it works with no script at all.
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
	if ( $oria_term_obj instanceof WP_Term ) {
		$oria_tiles[] = array( 'slug' => $oria_slug, 'name' => \Oria\Theme\tname( $oria_term_obj ), 'count' => $oria_n );
	}
}

/* "How do you want to feel?": only feelings something upcoming answers. */
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
		if ( $oria_fc > 0 ) {
			$oria_feels[] = array( 'key' => $oria_fk, 'label' => (string) ( $oria_fq['for']['options'][ $oria_fk ] ?? $oria_fk ), 'count' => $oria_fc );
		}
	}
	usort( $oria_feels, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] );
}

/* Particular days with something on, for the When control. */
$oria_day_opts = array();
for ( $oria_d = 0; $oria_d < 14; $oria_d++ ) {
	$oria_dts   = strtotime( 'today +' . $oria_d . ' days', $oria_now );
	$oria_key   = gmdate( 'Y-m-d', $oria_dts );
	$oria_count = count( array_filter( $oria_rows, static fn( array $r ): bool => gmdate( 'Y-m-d', $r['ts'] ) === $oria_key ) );
	if ( $oria_count > 0 ) {
		$oria_day_opts[ $oria_key ] = sprintf( '%s (%d)', gmdate( 'D j M', $oria_dts ), $oria_count );
	}
}

$oria_archive = get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' );
$oria_wk_label = gmdate( 'D j', strtotime( $oria_wk_a ) ) . '–' . gmdate( 'D j M', strtotime( $oria_wk_b ) );

?>

<div class="oria-whats-on">

<!-- 1. Hero: copy on ivory, a warm photograph beside it -->
<section class="wo-hero" aria-labelledby="woTitle">
	<div class="wo-wrap wo-hero__grid">
		<div class="wo-hero__copy">
			<nav class="crumbs wo-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
				<span aria-hidden="true">/</span><span><?php esc_html_e( "What's On", 'oria' ); ?></span>
			</nav>
			<p class="wo-eyebrow"><?php esc_html_e( "What's on in Perth", 'oria' ); ?></p>
			<h1 class="wo-hero__title" id="woTitle"><?php esc_html_e( 'Wellness events and workshops in Perth', 'oria' ); ?></h1>
			<p class="wo-hero__tag"><?php esc_html_e( 'Make time for something good.', 'oria' ); ?></p>
			<p class="wo-hero__lede"><?php esc_html_e( 'Discover sound baths, yoga workshops, breathwork, retreats and community moments around Perth.', 'oria' ); ?></p>
			<div class="wo-hero__acts">
				<a class="wo-btn wo-btn--primary" href="#wo-results" data-wo-go=""><?php echo $oria_ico( 'cal' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><?php esc_html_e( 'Browse events', 'oria' ); ?></a>
				<a class="wo-btn wo-btn--secondary" href="<?php echo esc_url( add_query_arg( 'date', 'weekend', $oria_archive ) ); ?>#wo-results" data-wo-go="when:weekend"><?php esc_html_e( 'This weekend', 'oria' ); ?></a>
				<a class="wo-textlink" href="<?php echo esc_url( home_url( '/submit-an-event/' ) ); ?>"><?php esc_html_e( 'Submit an event', 'oria' ); ?></a>
			</div>
			<?php if ( $oria_total ) : ?>
				<p class="wo-hero__meta"><?php echo esc_html( sprintf( _n( '%d upcoming event', '%d upcoming events', $oria_total, 'oria' ), $oria_total ) ); ?></p>
			<?php endif; ?>
		</div>
		<figure class="wo-hero__media">
			<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/event/wo-hero-1400.webp' ); ?>"
				srcset="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/event/wo-hero-800.webp' ); ?> 800w, <?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/event/wo-hero-1400.webp' ); ?> 1400w"
				sizes="(max-width: 50rem) 100vw, 50vw" width="1400" height="875" alt="" fetchpriority="high" decoding="async">
		</figure>
	</div>
</section>

<?php if ( count( $oria_tiles ) >= 3 ) : ?>
<!-- 2. Activity tiles -->
<section class="wo-section wo-section--tight" aria-labelledby="woTilesTitle">
	<div class="wo-wrap">
		<details class="wo-tiles" data-wo-tiles open>
			<summary class="wo-tiles__toggle"><?php esc_html_e( 'Browse by activity', 'oria' ); ?></summary>
			<h2 class="wo-h2" id="woTilesTitle"><?php esc_html_e( 'Find your kind of feel-good', 'oria' ); ?></h2>
			<ul class="wo-tiles__grid">
				<?php foreach ( $oria_tiles as $oria_t ) : ?>
					<li>
						<a class="wotile" href="<?php echo esc_url( add_query_arg( 'type', $oria_t['slug'], $oria_archive ) ); ?>#wo-results" data-wo-tile="<?php echo esc_attr( $oria_t['slug'] ); ?>">
							<img class="wotile__img" src="<?php echo esc_url( $oria_tile_uri . $oria_t['slug'] . '-600.webp' ); ?>"
								srcset="<?php echo esc_url( $oria_tile_uri . $oria_t['slug'] . '-600.webp' ); ?> 600w, <?php echo esc_url( $oria_tile_uri . $oria_t['slug'] . '-900.webp' ); ?> 900w"
								sizes="(max-width: 40rem) 45vw, (max-width: 75rem) 30vw, 200px" alt="" loading="lazy" decoding="async" width="600" height="400">
							<span class="wotile__name"><?php echo esc_html( $oria_t['name'] ); ?></span>
							<span class="wotile__n"><?php echo esc_html( sprintf( _n( '%d event', '%d events', $oria_t['count'], 'oria' ), $oria_t['count'] ) ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>
	</div>
</section>
<?php endif; ?>

<!-- 3 + 4. One filter surface, then the results -->
<section class="wo-section wo-section--results" data-whatson id="wo-results" aria-labelledby="woResultsTitle">
	<div class="wo-wrap">

		<div class="wo-filters" data-wo-filters>
			<div class="wo-filters__row">
				<label class="wo-field wo-field--when">
					<span class="wo-field__label"><?php esc_html_e( 'When', 'oria' ); ?></span>
					<span class="wo-select"><?php echo $oria_ico( 'cal' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?>
						<select data-f="whenday">
							<option value="when:all"><?php esc_html_e( 'All dates', 'oria' ); ?></option>
							<option value="when:today"><?php esc_html_e( 'Today', 'oria' ); ?></option>
							<option value="when:tomorrow"><?php esc_html_e( 'Tomorrow', 'oria' ); ?></option>
							<option value="when:weekend"><?php echo esc_html( sprintf( /* translators: %s: dates */ __( 'This weekend (%s)', 'oria' ), $oria_wk_label ) ); ?></option>
							<option value="when:nextweekend"><?php esc_html_e( 'Next weekend', 'oria' ); ?></option>
							<option value="when:month"><?php esc_html_e( 'This month', 'oria' ); ?></option>
							<?php if ( $oria_day_opts ) : ?>
								<optgroup label="<?php esc_attr_e( 'A particular day', 'oria' ); ?>">
									<?php foreach ( $oria_day_opts as $oria_dk => $oria_dl ) : ?>
										<option value="day:<?php echo esc_attr( $oria_dk ); ?>"><?php echo esc_html( $oria_dl ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endif; ?>
						</select>
					</span>
				</label>
				<button class="wo-filters__toggle" type="button" data-wo-filters-toggle aria-expanded="false" aria-controls="woMoreFilters">
					<?php echo $oria_ico( 'sliders' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><?php esc_html_e( 'Filters', 'oria' ); ?>
				</button>
				<div class="wo-filters__rest" id="woMoreFilters" data-wo-filters-panel>
					<label class="wo-field">
						<span class="wo-field__label"><?php esc_html_e( 'Where', 'oria' ); ?></span>
						<span class="wo-select"><?php echo $oria_ico( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?>
							<select data-f="suburb">
								<option value=""><?php esc_html_e( 'All areas', 'oria' ); ?></option>
								<?php foreach ( $oria_suburbs as $oria_slug => $oria_name ) : ?>
									<option value="<?php echo esc_attr( $oria_slug ); ?>"><?php echo esc_html( $oria_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</span>
					</label>
					<label class="wo-field">
						<span class="wo-field__label"><?php esc_html_e( 'Price', 'oria' ); ?></span>
						<span class="wo-select"><?php echo $oria_ico( 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?>
							<select data-f="band">
								<option value=""><?php esc_html_e( 'Any price', 'oria' ); ?></option>
								<option value="free"><?php esc_html_e( 'Free or by donation', 'oria' ); ?></option>
								<option value="under30"><?php esc_html_e( 'Under $30', 'oria' ); ?></option>
								<option value="30to50"><?php esc_html_e( '$30–$50', 'oria' ); ?></option>
								<option value="50plus"><?php esc_html_e( '$50 and over', 'oria' ); ?></option>
							</select>
						</span>
					</label>
					<details class="wo-more" data-wo-more>
						<summary class="wo-more__summary"><?php echo $oria_ico( 'sliders' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><?php esc_html_e( 'More filters', 'oria' ); ?><span class="wo-more__n" data-wo-more-n></span></summary>
						<div class="wo-more__panel">
							<label class="wo-field">
								<span class="wo-field__label"><?php esc_html_e( 'Activity', 'oria' ); ?></span>
								<span class="wo-select wo-select--plain">
									<select data-f="type">
										<option value=""><?php esc_html_e( 'All activities', 'oria' ); ?></option>
										<?php foreach ( $oria_types as $oria_slug => $oria_name ) : ?>
											<option value="<?php echo esc_attr( $oria_slug ); ?>"><?php echo esc_html( $oria_name ); ?></option>
										<?php endforeach; ?>
									</select>
								</span>
							</label>
							<?php if ( count( $oria_feels ) >= 2 ) : ?>
								<fieldset class="wo-feels">
									<legend class="wo-field__label"><?php esc_html_e( 'How do you want to feel?', 'oria' ); ?></legend>
									<div class="wo-feels__row">
										<?php foreach ( $oria_feels as $oria_f ) : ?>
											<button class="wofeel" type="button" aria-pressed="false" data-f="feel" data-v="<?php echo esc_attr( $oria_f['key'] ); ?>">
												<span class="wofeel__name"><?php echo esc_html( $oria_f['label'] ); ?></span>
												<span class="wofeel__n"><?php echo esc_html( (string) $oria_f['count'] ); ?></span>
											</button>
										<?php endforeach; ?>
									</div>
									<p class="wofeels__now" data-wo-feelnote hidden></p>
								</fieldset>
							<?php endif; ?>
						</div>
					</details>
				</div>
			</div>
			<div class="wo-shortcuts" role="group" aria-label="<?php esc_attr_e( 'Shortcuts', 'oria' ); ?>">
				<button class="fchip" type="button" aria-pressed="true" data-f="when" data-v="all"><?php esc_html_e( 'All upcoming', 'oria' ); ?></button>
				<button class="fchip" type="button" aria-pressed="false" data-f="when" data-v="weekend"><?php esc_html_e( 'This weekend', 'oria' ); ?></button>
				<button class="fchip" type="button" aria-pressed="false" data-f="band" data-v="free"><?php esc_html_e( 'Free events', 'oria' ); ?></button>
			</div>
			<ul class="wo-active" data-wo-active hidden aria-label="<?php esc_attr_e( 'Active filters', 'oria' ); ?>"></ul>
		</div>

		<?php
		/* The suburbs on this page that have a guide worth visiting. */
		$oria_area_map = array();
		if ( function_exists( '\Oria\Core\AreaContext\for_post' ) ) {
			foreach ( $oria_rows as $oria_r ) {
				$oria_key = sanitize_title( (string) $oria_r['suburb'] );
				if ( '' === $oria_key || isset( $oria_area_map[ $oria_key ] ) ) {
					continue;
				}
				$oria_ctx = \Oria\Core\AreaContext\for_post( (int) $oria_r['post']->ID );
				if ( $oria_ctx ) {
					$oria_area_map[ $oria_key ] = array(
						'name'   => (string) $oria_ctx['name'],
						'url'    => (string) $oria_ctx['url'],
						'places' => (int) $oria_ctx['places'],
						'slug'   => (string) $oria_ctx['slug'],
					);
				}
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

		<div class="wo-results-head">
			<div>
				<h2 class="wo-h2" id="woResultsTitle"><?php esc_html_e( 'Coming up in Perth', 'oria' ); ?></h2>
				<p class="wo-results-head__sub"><?php esc_html_e( 'Find your next experience.', 'oria' ); ?></p>
			</div>
			<div class="wotoolbar" data-wo-toolbar>
				<p class="wotoolbar__count" data-wo-count role="status" aria-live="polite"
					data-one="<?php esc_attr_e( '%d event', 'oria' ); ?>"
					data-many="<?php esc_attr_e( '%d events', 'oria' ); ?>"
					data-none="<?php esc_attr_e( 'No events match', 'oria' ); ?>">
					<?php echo esc_html( sprintf( _n( '%d event', '%d events', $oria_total, 'oria' ), $oria_total ) ); ?>
				</p>
				<button class="wotoolbar__clear" type="button" data-wo-clear hidden><?php esc_html_e( 'Clear filters', 'oria' ); ?></button>
				<div class="woview" data-wo-view role="group" aria-label="<?php esc_attr_e( 'How to show these events', 'oria' ); ?>" hidden>
					<button class="woview__btn is-on" type="button" aria-pressed="true" data-wo-mode="list"><?php echo $oria_ico( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><?php esc_html_e( 'Cards', 'oria' ); ?></button>
					<button class="woview__btn" type="button" aria-pressed="false" data-wo-mode="map"><?php echo $oria_ico( 'map' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><?php esc_html_e( 'Map', 'oria' ); ?></button>
				</div>
			</div>
		</div>

		<section class="worecs" data-wo-recs hidden aria-labelledby="woRecsTitle">
			<h2 class="h3 worecs__title" id="woRecsTitle"></h2>
			<div class="worecs__row" data-wo-recs-row></div>
		</section>

		<div class="womap" data-wo-map hidden>
			<div class="womap__canvas" data-wo-map-canvas></div>
			<p class="womap__note">
				<?php esc_html_e( 'Pins are the venue where we could place it, and a suburb centre otherwise. A few events have no location we could confirm and are in the list only.', 'oria' ); ?>
			</p>
		</div>

		<?php if ( $oria_member_rows ) : ?>
			<div class="wogroup wo-group wo-group--featured" data-wo-feat>
				<p class="wo-group__label">
					<span class="wo-group__name"><?php esc_html_e( 'Featured practices', 'oria' ); ?></span>
					<span class="wo-group__note"><?php esc_html_e( 'Paid placement by practices listed with us', 'oria' ); ?></span>
				</p>
				<div class="wkrows wo-grid">
					<?php foreach ( $oria_member_rows as $oria_r ) { $oria_row( $oria_r ); } ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $oria_days ) : ?>
			<?php foreach ( $oria_days as $oria_day => $oria_list ) : ?>
				<div class="wogroup wo-group">
					<h3 class="wo-group__label wkday">
						<span class="wo-group__name"><?php echo esc_html( $oria_labels[ $oria_day ] ?? '' ); ?></span>
						<span class="wkday__date wo-group__note" data-wo-group-count
							data-one="<?php esc_attr_e( '%d event', 'oria' ); ?>"
							data-many="<?php esc_attr_e( '%d events', 'oria' ); ?>"><?php echo esc_html( sprintf( _n( '%d event', '%d events', count( $oria_list ), 'oria' ), count( $oria_list ) ) ); ?></span>
					</h3>
					<div class="wkrows wo-grid">
						<?php foreach ( $oria_list as $oria_r ) { $oria_row( $oria_r ); } ?>
					</div>
				</div>
			<?php endforeach; ?>
			<div class="dir__empty wo-empty" data-wo-empty hidden>
				<h2 class="wo-h2"><?php esc_html_e( 'Nothing matches those filters yet', 'oria' ); ?></h2>
				<p><?php esc_html_e( 'Try widening the dates or the area, or start again and browse everything coming up.', 'oria' ); ?></p>
				<p class="wo-empty-area" data-wo-empty-area hidden></p>
				<p class="wo-empty__acts">
					<button class="wo-btn wo-btn--primary" type="button" data-wo-clear><?php esc_html_e( 'Clear filters', 'oria' ); ?></button>
					<a class="wo-btn wo-btn--secondary" href="#" data-wo-empty-cta data-area-promo="whats-on-empty" hidden></a>
				</p>
			</div>
		<?php elseif ( ! $oria_member_rows ) : ?>
			<div class="dir__empty wo-empty">
				<h2 class="wo-h2"><?php esc_html_e( 'Nothing listed yet', 'oria' ); ?></h2>
				<p><?php esc_html_e( 'New events go up as we find them and as organisers send them in.', 'oria' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php
/* Where the events are, counted off the page's own rows. */
$oria_near = array();
if ( function_exists( '\Oria\Core\AreaContext\for_post' ) ) {
	foreach ( $oria_rows as $oria_r ) {
		$oria_ctx = \Oria\Core\AreaContext\for_post( (int) $oria_r['post']->ID );
		if ( ! $oria_ctx ) {
			continue;
		}
		$oria_k = (string) $oria_ctx['slug'];
		if ( ! isset( $oria_near[ $oria_k ] ) ) {
			$oria_near[ $oria_k ] = array( 'name' => (string) $oria_ctx['name'], 'url' => (string) $oria_ctx['url'], 'n' => 0, 'next' => $oria_r['ts'] );
		}
		++$oria_near[ $oria_k ]['n'];
		$oria_near[ $oria_k ]['next'] = min( $oria_near[ $oria_k ]['next'], $oria_r['ts'] );
	}
	uasort( $oria_near, static fn( array $a, array $b ): int => $b['n'] <=> $a['n'] ?: $a['next'] <=> $b['next'] );
	$oria_near = array_slice( $oria_near, 0, 6, true );
}

/* The event collections that are live today. */
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

<?php if ( count( $oria_near ) >= 3 || count( $oria_colls ) >= 2 ) : ?>
<!-- 5. Quiet ways onward -->
<section class="wo-section wo-section--onward">
	<div class="wo-wrap wo-onward">
		<?php if ( count( $oria_near ) >= 3 ) : ?>
			<div aria-labelledby="woNearTitle">
				<h2 class="wo-h3" id="woNearTitle"><?php esc_html_e( 'What’s happening near you', 'oria' ); ?></h2>
				<ul class="wo-links">
					<?php foreach ( $oria_near as $oria_slug => $oria_a ) : ?>
						<li>
							<a href="<?php echo esc_url( $oria_a['url'] ); ?>" data-area-promo="whats-on-near" data-area-slug="<?php echo esc_attr( (string) $oria_slug ); ?>">
								<span class="wo-links__name"><?php echo esc_html( $oria_a['name'] ); ?></span>
								<span class="wo-links__meta">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: number of events, 2: date */
											_n( '%1$d event · next %2$s', '%1$d events · next %2$s', (int) $oria_a['n'], 'oria' ),
											(int) $oria_a['n'],
											gmdate( 'D j M', (int) $oria_a['next'] )
										)
									);
									?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php if ( count( $oria_colls ) >= 2 ) : ?>
			<div aria-labelledby="woCollTitle">
				<h2 class="wo-h3" id="woCollTitle"><?php esc_html_e( 'Explore more wellness events', 'oria' ); ?></h2>
				<ul class="wo-links">
					<?php foreach ( $oria_colls as $oria_c ) : ?>
						<li>
							<a href="<?php echo esc_url( (string) $oria_c['url'] ); ?>">
								<span class="wo-links__name"><?php echo esc_html( (string) $oria_c['title'] ); ?></span>
								<?php if ( ! empty( $oria_c['count'] ) ) : ?>
									<span class="wo-links__meta"><?php echo esc_html( sprintf( _n( '%d event', '%d events', (int) $oria_c['count'], 'oria' ), (int) $oria_c['count'] ) ); ?></span>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<!-- 6. The host invitation -->
<section class="wo-host" aria-labelledby="woHostTitle">
	<div class="wo-wrap wo-host__in">
		<div>
			<h2 class="wo-h2" id="woHostTitle"><?php esc_html_e( 'Hosting something good?', 'oria' ); ?></h2>
			<p class="wo-host__text"><?php esc_html_e( 'Share your workshop, class or retreat with people looking for wellness experiences around Perth.', 'oria' ); ?></p>
		</div>
		<div class="wo-host__acts">
			<a class="wo-btn wo-btn--primary" href="<?php echo esc_url( home_url( '/submit-an-event/' ) ); ?>"><?php esc_html_e( 'Submit an event', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
			<a class="wo-textlink" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'Claim your listing', 'oria' ); ?></a>
		</div>
	</div>
</section>

</div>

<?php
get_footer();
