<?php
/**
 * /this-weekend/ — what's on in Perth this weekend.
 *
 * The weekend window is Friday through Sunday. Early in the week the page
 * looks ahead to the coming weekend; once the weekend has started it shows
 * what's left of the current one, so a Saturday-morning visitor sees
 * tonight and tomorrow, not next week.
 *
 * Events are grouped by day, ordered by start time, with a "coming up"
 * rail beyond the weekend so the page is never a dead end. Event times are
 * naive local datetimes: compared against current_time() and formatted
 * with gmdate(), never wp_date() — see the starting-soon strip.
 */

declare(strict_types=1);

use function Oria\Theme\arrow;

$oria_now = (int) current_time( 'timestamp' );

// The Friday anchoring this weekend: today's own Friday once the weekend
// has begun (Fri/Sat/Sun), otherwise the next one.
$oria_dow    = (int) gmdate( 'N', $oria_now ); // 1 Mon … 7 Sun
$oria_friday = $oria_dow >= 5
	? $oria_now - ( $oria_dow - 5 ) * DAY_IN_SECONDS
	: $oria_now + ( 5 - $oria_dow ) * DAY_IN_SECONDS;

$oria_from = max( $oria_now, (int) strtotime( gmdate( 'Y-m-d 00:00:00', $oria_friday ) ) );
$oria_to   = (int) strtotime( gmdate( 'Y-m-d 23:59:59', $oria_friday + 2 * DAY_IN_SECONDS ) );

/*
 * Asked of \Oria\Core\Events rather than queried here, so this page cannot
 * disagree with the archive about what is on -- a cancelled event used to
 * be excluded everywhere else and still appear in the weekend list.
 */
$oria_events = array_map(
	'get_post',
	\Oria\Core\Events\upcoming(
		array(
			'limit' => 40,
			'from'  => gmdate( 'Y-m-d H:i:s', $oria_from ),
			'to'    => gmdate( 'Y-m-d H:i:s', $oria_to ),
		)
	)
);

// Grouped by calendar day. Member events lead each day — practitioners
// pay to be here; aggregated finds follow in time order.
$oria_days = array();
foreach ( $oria_events as $oria_ev ) {
	$oria_ts = strtotime( (string) get_field( 'event_start', $oria_ev->ID ) );
	if ( $oria_ts ) {
		$oria_days[ gmdate( 'Y-m-d', $oria_ts ) ][] = array( $oria_ev, $oria_ts );
	}
}
foreach ( $oria_days as &$oria_list ) {
	usort(
		$oria_list,
		static function ( array $a, array $b ): int {
			$member = static fn( \WP_Post $p ): bool => '' === (string) get_post_meta( $p->ID, '_oria_src', true );
			return array( ! $member( $a[0] ), $a[1] ) <=> array( ! $member( $b[0] ), $b[1] );
		}
	);
}
unset( $oria_list );

// Beyond the weekend, so there is always a next thing to look at.
$oria_later = array_map(
	'get_post',
	\Oria\Core\Events\upcoming(
		array(
			'limit' => 6,
			'from'  => gmdate( 'Y-m-d H:i:s', $oria_to + 1 ),
		)
	)
);

/*
 * The page in numbers, for the search title, the description, the summary
 * line, the FAQ and the ItemList -- all from the same events the page lists,
 * so none of them can promise something the page doesn't show.
 *
 * Why it matters: "what's on in Perth this weekend" and "perth this
 * weekend" are 5,400 searches a month each (DataWise, Oct 2026) and the
 * results that win carry this weekend's dates and a count. A fixed title
 * reads the same in week 40 as in week 4.
 */
$oria_sun   = $oria_friday + 2 * DAY_IN_SECONDS;
$oria_range = gmdate( 'n', $oria_friday ) === gmdate( 'n', $oria_sun )
	? gmdate( 'j', $oria_friday ) . '–' . gmdate( 'j F', $oria_sun )
	: gmdate( 'j M', $oria_friday ) . ' – ' . gmdate( 'j M', $oria_sun );

$oria_is_free = static function ( \WP_Post $p ): bool {
	$price = trim( (string) get_field( 'price', $p->ID ) );
	return (bool) preg_match( '/^(free\b|\$0(\.00)?$)/i', $price );
};
// The deepest area term is the suburb; a region has a parent (the city) too.
$oria_place = static function ( \WP_Post $p ): string {
	$where = '';
	$depth = -1;
	foreach ( wp_get_post_terms( $p->ID, 'area' ) as $t ) {
		$d = count( get_ancestors( $t->term_id, 'area', 'taxonomy' ) );
		if ( $d > $depth ) {
			$depth = $d;
			$where = \Oria\Theme\tname( $t );
		}
	}
	return $where;
};
$oria_count = count( $oria_events );
$oria_free  = array_values( array_filter( $oria_events, $oria_is_free ) );
$oria_by    = array();
foreach ( $oria_days as $oria_d => $oria_l ) {
	$oria_by[ gmdate( 'l', (int) strtotime( $oria_d ) ) ] = array_column( $oria_l, 0 );
}

/** "Puppy Yoga (Clarkson), 5Rhythms Dance (Fremantle) and 3 more" */
$oria_names = static function ( array $posts, int $max = 3 ) use ( $oria_place ): string {
	$bits = array();
	foreach ( array_slice( $posts, 0, $max ) as $p ) {
		$name   = \Oria\Theme\ptitle( $p );
		$where  = $oria_place( $p );
		$bits[] = $name . ( '' !== $where && false === stripos( $name, $where ) ? ' (' . $where . ')' : '' );
	}
	$more = count( $posts ) - count( $bits );
	if ( $more > 0 ) {
		/* translators: %d: number of further events */
		$bits[] = sprintf( _n( '%d more', '%d more', $more, 'oria' ), $more );
	}
	if ( count( $bits ) < 2 ) {
		return implode( '', $bits );
	}
	$last = array_pop( $bits );
	return implode( ', ', $bits ) . ' ' . __( 'and', 'oria' ) . ' ' . $last;
};

// Search title and description, ahead of get_header() so wp_head sees them.
$oria_seo_title = sprintf( /* translators: %s: date range */ __( 'What’s On in Perth This Weekend, %s', 'oria' ), $oria_range );
if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $oria_seo_title . ' | Oria Haven' ) : strlen( $oria_seo_title ) + 14 ) <= 61 ) {
	$oria_seo_title .= ' | Oria Haven';
}
$oria_seo_desc = $oria_count
	? sprintf(
		/* translators: 1: count, 2: date range, 3: free sentence */
		__( '%1$d wellness events on in Perth this weekend (%2$s): yoga, sound baths, breathwork and workshops by day, with times, suburbs and prices.%3$s', 'oria' ),
		$oria_count,
		$oria_range,
		$oria_free ? ' ' . sprintf( /* translators: %d: free events */ _n( '%d is free.', '%d are free.', count( $oria_free ), 'oria' ), count( $oria_free ) ) : ''
	)
	: sprintf( /* translators: %s: date range */ __( 'What’s on in Perth this weekend (%s): wellness events, workshops and classes by day, plus what’s coming up next.', 'oria' ), $oria_range );
$oria_seo_title_cb = static fn(): string => $oria_seo_title;
$oria_seo_desc_cb  = static fn(): string => $oria_seo_desc;
add_filter( 'wpseo_title', $oria_seo_title_cb, 99 );
add_filter( 'wpseo_opengraph_title', $oria_seo_title_cb, 99 );
add_filter( 'wpseo_metadesc', $oria_seo_desc_cb, 99 );
add_filter( 'wpseo_opengraph_desc', $oria_seo_desc_cb, 99 );

// The weekend's events as an ItemList, each pointing at its own page, where
// the full Event markup lives.
add_action(
	'wp_head',
	static function () use ( $oria_days, $oria_seo_title ): void {
		$items = array();
		$n     = 0;
		foreach ( $oria_days as $oria_l ) {
			foreach ( $oria_l as $pair ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => ++$n,
					'url'      => get_permalink( $pair[0] ),
					'name'     => \Oria\Theme\ptitle( $pair[0] ),
				);
			}
		}
		if ( ! $items ) {
			return;
		}
		$ld = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => $oria_seo_title,
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n"; // phpcs:ignore
	},
	30
);

get_header();

// A light touch of place per practice, as on the pitch: 🧘 Yoga — Fremantle.
$oria_emoji = array(
	'meditation'  => '🪷',
	'breathwork'  => '🌬️',
	'yoga'        => '🧘',
	'mindfulness' => '🌤️',
	'sound'       => '🔔',
	'retreats'    => '🌿',
	'nutrition'   => '🥗',
	'bodywork'    => '💆',
	'natural'     => '🌱',
	'energy'      => '✨',
	'recovery'    => '🧊',
	'allied'      => '🩺',
);

/** One event row: emoji, time, name, suburb, price, link. */
$oria_row = static function ( \WP_Post $oria_ev, int $oria_ts, bool $oria_with_date = false ) use ( $oria_emoji ): void {
	$oria_terms = wp_get_post_terms( $oria_ev->ID, 'practice' );
	$oria_cat   = ! is_wp_error( $oria_terms ) && $oria_terms ? $oria_terms[0] : null;
	$oria_mark  = $oria_cat && isset( $oria_emoji[ $oria_cat->slug ] ) ? $oria_emoji[ $oria_cat->slug ] : '·';

	$oria_where = '';
	foreach ( wp_get_post_terms( $oria_ev->ID, 'area' ) as $oria_at ) {
		$oria_where = \Oria\Theme\tname( $oria_at );
		if ( $oria_at->parent ) {
			break;
		}
	}
	if ( '' === $oria_where ) {
		$oria_where = (string) get_field( 'venue', $oria_ev->ID );
	}
	$oria_price = (string) get_field( 'price', $oria_ev->ID );

	// A midnight start almost always means a date-only entry, not a
	// session that truly begins at 12.00am.
	$oria_time = '00:00' === gmdate( 'H:i', $oria_ts ) ? __( 'TBC', 'oria' ) : gmdate( 'g.ia', $oria_ts );
	$oria_when = $oria_with_date ? gmdate( 'D j M', $oria_ts ) . ' · ' . $oria_time : $oria_time;

	$oria_member = '' === (string) get_post_meta( $oria_ev->ID, '_oria_src', true );
	?>
	<a class="wkrow<?php echo $oria_member ? ' wkrow--member' : ''; ?>" href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>">
		<span class="wkrow__thumb" aria-hidden="true">
			<?php if ( has_post_thumbnail( $oria_ev ) ) : ?>
				<?php
				/*
				 * medium_large, as the archive uses. A 150px thumbnail
				 * stretched across a card is what made these read as
				 * broken images rather than photographs.
				 */
				echo get_the_post_thumbnail( $oria_ev, 'medium_large', array( 'loading' => 'lazy', 'alt' => '' ) );
				?>
			<?php else : ?>
				<img class="wkrow__scene" src="<?php echo esc_url( \Oria\Theme\event_scene( $oria_ev->ID ) ); ?>" alt="" loading="lazy" decoding="async">
			<?php endif; ?>
			<?php
			/*
			 * Inside the thumb, not beside it. .wkrow__time is absolutely
			 * positioned and .wkrow__thumb is the relative box it belongs
			 * to; as a sibling every badge escaped to the nearest
			 * positioned ancestor and piled up at the edge of the page.
			 */
			?>
			<time class="wkrow__time"><?php echo esc_html( $oria_when ); ?></time>
		</span>
		<span class="wkrow__body">
			<b><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev ) ); ?></b>
			<em>
				<?php echo esc_html( $oria_where ); ?>
				<?php if ( $oria_cat ) : ?> · <?php echo esc_html( \Oria\Theme\tname( $oria_cat ) ); ?><?php endif; ?>
			</em>
		</span>
		<?php if ( $oria_price ) : ?><span class="wkrow__price"><?php echo esc_html( $oria_price ); ?></span><?php endif; ?>
		<span class="wkrow__go" aria-hidden="true"><?php echo arrow(); // phpcs:ignore ?></span>
	</a>
	<?php
};

?>

<?php
ob_start();
?>
	<a class="btn btn--sm btn--dark" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ) ); ?>"><?php esc_html_e( "Everything that's on", 'oria' ); ?></a>
	<a class="btn btn--sm" href="<?php echo esc_url( home_url( '/submit-an-event/' ) ); ?>"><?php esc_html_e( 'Submit an event', 'oria' ); ?></a>
<?php
$oria_actions = (string) ob_get_clean();

get_template_part(
	'template-parts/event-hero',
	null,
	array(
		'img'     => 'weekend-hero',
		'crumbs'  => array(
			__( 'Home', 'oria' )       => home_url( '/' ),
			__( "What's On", 'oria' )  => get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ),
			__( 'This weekend', 'oria' ) => '',
		),
		'eyebrow' => sprintf( /* translators: %s: date range */ __( 'Fri–Sun · %s', 'oria' ), $oria_range ),
		'title'   => __( 'This weekend in Perth', 'oria' ),
		'lede'    => __( 'Every workshop, sitting and session running this weekend, in one place. New every week.', 'oria' ),
		'actions' => $oria_actions,
	)
);
?>

<?php if ( $oria_count ) : ?>
<section class="wrap section section--top-flush">
	<p class="wksum">
		<?php
		$oria_split = array();
		foreach ( $oria_by as $oria_dn => $oria_dl ) {
			/* translators: 1: count, 2: day name */
			$oria_split[] = sprintf( __( '%1$d on %2$s', 'oria' ), count( $oria_dl ), $oria_dn );
		}
		printf(
			/* translators: 1: count, 2: date range, 3: per-day split, 4: free sentence */
			esc_html__( '%1$d wellness events are on in Perth this weekend (%2$s): %3$s.%4$s', 'oria' ),
			(int) $oria_count,
			esc_html( $oria_range ),
			esc_html( implode( ', ', $oria_split ) ),
			$oria_free ? ' ' . esc_html( sprintf( /* translators: %d: free events */ _n( '%d of them is free.', '%d of them are free.', count( $oria_free ), 'oria' ), count( $oria_free ) ) ) : '' // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
		);
		?>
	</p>
</section>
<?php endif; ?>

<section class="wrap section section--top-flush">
	<?php if ( $oria_days ) : ?>
		<div class="stack-lg">
			<?php foreach ( $oria_days as $oria_day => $oria_list ) : ?>
				<div>
					<h2 class="h3 wkday">
						<?php echo esc_html( gmdate( 'l', (int) strtotime( $oria_day ) ) ); ?>
						<span class="wkday__date"><?php echo esc_html( gmdate( 'j F', (int) strtotime( $oria_day ) ) ); ?></span>
					</h2>
					<div class="wkrows<?php echo 1 === count( $oria_list ) ? ' wkrows--one' : ''; ?>">
						<?php foreach ( $oria_list as $oria_pair ) { $oria_row( $oria_pair[0], $oria_pair[1] ); } ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="dir__empty">
			<h2 class="h3"><?php esc_html_e( 'A quiet one this weekend', 'oria' ); ?></h2>
			<p class="muted" style="margin-top:.5rem"><?php esc_html_e( "Nothing's listed for this weekend yet — but here's what's coming up.", 'oria' ); ?></p>
		</div>
	<?php endif; ?>
</section>

<section class="wrap section section--top-flush">
	<?php get_template_part( 'template-parts/weekend-signup', null, array( 'source' => 'weekend' ) ); ?>
</section>

<?php if ( $oria_later ) : ?>
<section class="wrap section section--top-flush">
	<div class="row-between" style="margin-bottom:1rem">
		<h2 class="h3" style="margin:0"><?php esc_html_e( 'Coming up after the weekend', 'oria' ); ?></h2>
		<a class="btn btn--ghost btn--sm btn--plain" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ); ?>"><?php esc_html_e( "Everything that's on", 'oria' ); ?></a>
	</div>
	<div class="wkrows">
		<?php
		foreach ( $oria_later as $oria_ev ) {
			$oria_ts = strtotime( (string) get_field( 'event_start', $oria_ev->ID ) );
			if ( $oria_ts ) {
				$oria_row( $oria_ev, $oria_ts, true );
			}
		}
		?>
	</div>
</section>
<?php endif; ?>

<?php
/*
 * Questions people ask about a Perth weekend ("what's on this Saturday",
 * "what's free"), answered from this weekend's own list. Rebuilt on every
 * render, so the answers and the list above can never disagree.
 */
$oria_faqs = array();
if ( $oria_count ) {
	$oria_all    = array_merge( ...array_values( $oria_by ) );
	$oria_faqs[] = array(
		'q' => __( 'What’s on in Perth this weekend?', 'oria' ),
		/* translators: 1: count, 2: date range, 3: event names */
		'a' => sprintf( __( '%1$d wellness events are listed for this weekend (%2$s), including %3$s. Each is listed by day on this page with its time, suburb and price.', 'oria' ), $oria_count, $oria_range, $oria_names( $oria_all, 4 ) ),
	);
	$oria_faqs[] = array(
		'q' => __( 'What’s free in Perth this weekend?', 'oria' ),
		'a' => $oria_free
			/* translators: 1: count, 2: event names */
			? sprintf( _n( '%1$d of this weekend’s events is free: %2$s.', '%1$d of this weekend’s events are free: %2$s.', count( $oria_free ), 'oria' ), count( $oria_free ), $oria_names( $oria_free, 5 ) )
			: __( 'None of the events listed for this weekend is free. Every event on this page shows its price, so you can compare before you go.', 'oria' ),
	);
	foreach ( $oria_by as $oria_dn => $oria_dl ) {
		$oria_faqs[] = array(
			/* translators: %s: day name */
			'q' => sprintf( __( 'What’s on in Perth on %s?', 'oria' ), $oria_dn ),
			'a' => $oria_names( $oria_dl, 4 ) . '.',
		);
	}
}
$oria_faqs[] = array(
	'q' => __( 'How is this list put together?', 'oria' ),
	'a' => __( 'It lists the wellness events in and around Perth that Oria Haven knows about for the coming Friday to Sunday: yoga, sound baths, breathwork, workshops and retreats, from organisers’ own pages and from practitioners who list with us. Past events drop off on their own, and new ones appear as they are published.', 'oria' ),
);
get_template_part( 'template-parts/faq', null, array( 'faqs' => $oria_faqs, 'heading' => __( 'This weekend in Perth: common questions', 'oria' ) ) );
?>

<?php $oria_feat = \Oria\Theme\featured_listings( 3 ); ?>
<?php if ( $oria_feat ) : ?>
<section class="wrap section section--top-flush">
	<?php
	get_template_part(
		'template-parts/featured',
		'band',
		array(
			'posts'   => $oria_feat,
			'heading' => __( 'Featured practices', 'oria' ),
		)
	);
	?>
</section>
<?php endif; ?>

<section class="wrap section section--top-flush">
	<div class="claimprompt" style="max-width:44rem">
		<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'Running something this weekend?', 'oria' ); ?></b>
		<p style="font-size:.875rem;color:var(--text-soft)">
			<?php esc_html_e( 'Featured members list their workshops and events here, on the home page and on their own listing.', 'oria' ); ?>
			<a href="<?php echo esc_url( home_url( '/claim/' ) ); ?>" style="text-decoration:underline;text-underline-offset:3px"><?php esc_html_e( 'Claim your listing', 'oria' ); ?></a>
		</p>
	</div>
</section>

<?php
get_footer();
