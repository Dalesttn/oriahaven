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

get_header();

$oria_now = (int) current_time( 'timestamp' );

// The Friday anchoring this weekend: today's own Friday once the weekend
// has begun (Fri/Sat/Sun), otherwise the next one.
$oria_dow    = (int) gmdate( 'N', $oria_now ); // 1 Mon … 7 Sun
$oria_friday = $oria_dow >= 5
	? $oria_now - ( $oria_dow - 5 ) * DAY_IN_SECONDS
	: $oria_now + ( 5 - $oria_dow ) * DAY_IN_SECONDS;

$oria_from = max( $oria_now, (int) strtotime( gmdate( 'Y-m-d 00:00:00', $oria_friday ) ) );
$oria_to   = (int) strtotime( gmdate( 'Y-m-d 23:59:59', $oria_friday + 2 * DAY_IN_SECONDS ) );

$oria_events = get_posts(
	array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 40,
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array( 'key' => 'event_start', 'value' => gmdate( 'Y-m-d H:i:s', $oria_from ), 'compare' => '>=', 'type' => 'DATETIME' ),
			array( 'key' => 'event_start', 'value' => gmdate( 'Y-m-d H:i:s', $oria_to ), 'compare' => '<=', 'type' => 'DATETIME' ),
		),
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
$oria_later = get_posts(
	array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 6,
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array( 'key' => 'event_start', 'value' => gmdate( 'Y-m-d H:i:s', $oria_to ), 'compare' => '>', 'type' => 'DATETIME' ),
		),
	)
);

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
				<?php echo get_the_post_thumbnail( $oria_ev, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
			<?php else : ?>
				<i><?php echo esc_html( $oria_mark ); ?></i>
			<?php endif; ?>
		</span>
		<time class="wkrow__time"><?php echo esc_html( $oria_when ); ?></time>
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

$oria_range = gmdate( 'j', $oria_friday ) . '–' . gmdate( 'j M', $oria_friday + 2 * DAY_IN_SECONDS );
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ); ?>"><?php esc_html_e( 'Workshops/Events', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'This weekend', 'oria' ); ?></span>
	</nav>
	<div class="row-between" style="align-items:flex-end;margin-top:1rem">
		<div>
			<span class="micro"><?php echo esc_html( sprintf( __( 'Fri–Sun · %s', 'oria' ), $oria_range ) ); ?></span>
			<h1 class="h1 pagehead__title"><?php esc_html_e( 'This weekend in Perth', 'oria' ); ?></h1>
		</div>
		<p class="lede" style="max-width:36ch"><?php esc_html_e( 'Every workshop, sitting and session running this weekend, in one place. New every week.', 'oria' ); ?></p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<?php if ( $oria_days ) : ?>
		<div class="stack-lg">
			<?php foreach ( $oria_days as $oria_day => $oria_list ) : ?>
				<div>
					<h2 class="h3 wkday">
						<?php echo esc_html( gmdate( 'l', (int) strtotime( $oria_day ) ) ); ?>
						<span class="wkday__date"><?php echo esc_html( gmdate( 'j F', (int) strtotime( $oria_day ) ) ); ?></span>
					</h2>
					<div class="wkrows">
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

<?php if ( $oria_later ) : ?>
<section class="wrap section section--top-flush">
	<div class="row-between" style="margin-bottom:1rem">
		<h2 class="h3" style="margin:0"><?php esc_html_e( 'Coming up after the weekend', 'oria' ); ?></h2>
		<a class="btn btn--ghost btn--sm btn--plain" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ); ?>"><?php esc_html_e( 'All workshops/events', 'oria' ); ?></a>
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
