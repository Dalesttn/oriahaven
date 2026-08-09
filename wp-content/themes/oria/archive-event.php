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

// Day groups, members first within each day (they pay to be here).
$oria_days = array();
foreach ( $oria_rows as $oria_r ) {
	$oria_days[ $oria_r['day'] ][] = $oria_r;
}
foreach ( $oria_days as &$oria_list ) {
	usort( $oria_list, static fn( $a, $b ) => array( ! $a['member'], $a['ts'] ) <=> array( ! $b['member'], $b['ts'] ) );
}
unset( $oria_list );

$oria_member_rows = array_values( array_filter( $oria_rows, static fn( $r ) => $r['member'] ) );
asort( $oria_suburbs );
asort( $oria_types );

/** One row. */
$oria_row = static function ( array $r ): void {
	$oria_ev  = $r['post'];
	$oria_t   = '00:00' === gmdate( 'H:i', $r['ts'] ) ? __( 'TBC', 'oria' ) : gmdate( 'g.ia', $r['ts'] );
	?>
	<a class="wkrow<?php echo $r['member'] ? ' wkrow--member' : ''; ?>"
		href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>"
		data-when="<?php echo esc_attr( $r['when'] ); ?>"
		data-suburb="<?php echo esc_attr( sanitize_title( $r['suburb'] ) ); ?>"
		data-type="<?php echo esc_attr( $r['type'] ? $r['type']->slug : '' ); ?>"
		data-band="<?php echo esc_attr( $r['band'] ); ?>">
		<span class="wkrow__thumb" aria-hidden="true">
			<?php if ( has_post_thumbnail( $oria_ev ) ) : ?>
				<?php echo get_the_post_thumbnail( $oria_ev, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
			<?php else : ?>
				<i><?php echo esc_html( \Oria\Theme\event_mark( $oria_ev->ID ) ); ?></i>
			<?php endif; ?>
		</span>
		<time class="wkrow__time"><?php echo esc_html( $oria_t ); ?></time>
		<span class="wkrow__body">
			<b><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev ) ); ?><?php if ( $r['member'] ) : ?> <i class="wkrow__flag"><?php esc_html_e( 'Featured practice', 'oria' ); ?></i><?php endif; ?></b>
			<em>
				<?php echo esc_html( implode( ' · ', array_filter( array( $r['suburb'], $r['type'] ? \Oria\Theme\tname( $r['type'] ) : '' ) ) ) ); ?>
				<?php if ( ! $r['member'] && $r['src'] ) : ?><span class="wkrow__src"><?php echo esc_html( sprintf( __( 'via %s', 'oria' ), $r['src'] ) ); ?></span><?php endif; ?>
			</em>
		</span>
		<?php if ( $r['price'] ) : ?><span class="wkrow__price"><?php echo esc_html( $r['price'] ); ?></span><?php endif; ?>
		<span class="wkrow__go" aria-hidden="true"><?php echo arrow(); // phpcs:ignore ?></span>
	</a>
	<?php
};
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Workshops/Events', 'oria' ); ?></span>
	</nav>
	<div class="row-between" style="align-items:flex-end;margin-top:1rem">
		<div>
			<span class="micro"><?php esc_html_e( 'Updated daily', 'oria' ); ?></span>
			<h1 class="h1 pagehead__title"><?php esc_html_e( "What's on in Perth", 'oria' ); ?></h1>
		</div>
		<div style="display:flex;flex-direction:column;align-items:flex-end;gap:.9rem">
			<p class="lede" style="max-width:36ch;margin:0"><?php esc_html_e( 'Workshops, sittings and sessions across the metro — from our member practices and around the web.', 'oria' ); ?></p>
			<a class="btn btn--ghost btn--sm btn--plain" href="<?php echo esc_url( home_url( '/this-weekend/' ) ); ?>"><?php esc_html_e( 'Just this weekend', 'oria' ); ?> <?php echo arrow(); // phpcs:ignore ?></a>
		</div>
	</div>
</section>

<?php if ( $oria_member_rows ) : ?>
<section class="wrap section--top-flush" style="padding-bottom:1.5rem">
	<div class="featband">
		<p class="featband__label"><span class="badge-dot" aria-hidden="true"></span><span class="micro"><?php esc_html_e( 'From our featured practices', 'oria' ); ?></span></p>
		<div class="wkrows">
			<?php foreach ( array_slice( $oria_member_rows, 0, 3 ) as $oria_r ) { $oria_row( $oria_r ); } ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="wrap section section--top-flush" data-whatson>
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
				<button class="fchip<?php echo 'all' === $oria_val ? ' is-on' : ''; ?>" type="button" data-f="when" data-v="<?php echo esc_attr( $oria_val ); ?>"><?php echo esc_html( $oria_label ); ?></button>
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
				<button class="fchip<?php echo '' === $oria_val ? ' is-on' : ''; ?>" type="button" data-f="band" data-v="<?php echo esc_attr( $oria_val ); ?>"><?php echo esc_html( $oria_label ); ?></button>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( $oria_days ) : ?>
		<div class="stack-lg" style="margin-top:2rem">
			<?php foreach ( $oria_days as $oria_day => $oria_list ) : ?>
				<div class="wogroup">
					<h2 class="h3 wkday">
						<?php echo esc_html( gmdate( 'l', (int) strtotime( $oria_day ) ) ); ?>
						<span class="wkday__date"><?php echo esc_html( gmdate( 'j F', (int) strtotime( $oria_day ) ) ); ?></span>
					</h2>
					<div class="wkrows">
						<?php foreach ( $oria_list as $oria_r ) { $oria_row( $oria_r ); } ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="dir__empty" data-wo-empty hidden style="margin-top:2rem"><?php esc_html_e( 'Nothing matches those filters — try widening the dates or price.', 'oria' ); ?></p>
	<?php else : ?>
		<div class="dir__empty" style="margin-top:2rem">
			<h2 class="h3"><?php esc_html_e( 'Nothing listed yet', 'oria' ); ?></h2>
			<p class="muted" style="margin-top:.5rem"><?php esc_html_e( 'New events are added daily — check back soon.', 'oria' ); ?></p>
		</div>
	<?php endif; ?>
</section>

<section class="wrap section section--top-flush">
	<div class="claimprompt" style="max-width:44rem">
		<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'Run wellness events in Perth?', 'oria' ); ?></b>
		<p style="font-size:.875rem;color:var(--text-soft)">
			<?php esc_html_e( 'Featured members\' events appear at the top of this page with a photo, a linked practice profile, and a spot in the weekend guide.', 'oria' ); ?>
			<a href="<?php echo esc_url( home_url( '/claim/' ) ); ?>" style="text-decoration:underline;text-underline-offset:3px"><?php esc_html_e( 'Claim your listing', 'oria' ); ?></a>
		</p>
	</div>
</section>

<?php
get_footer();
