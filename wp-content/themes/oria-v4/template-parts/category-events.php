<?php
/**
 * What's on in this category: events and workshops happening now or coming
 * up, on the category page beside the places that run them.
 *
 * Child-theme copy of oria/template-parts/category-events.php -- only the
 * heading differs (see below). Keep the rest in step with the parent.
 *
 * Same cards as /whats-on-perth/ (the .wkrow family). Featured events --
 * the ones the directory's own practices post -- come first, then the rest
 * by date. Aggregated events keep their "via {source}" note, because the
 * source stays the source of truth.
 *
 * Which events count as this category's:
 *
 *   - tagged with the category or any of its sub-categories -- a breathwork
 *     workshop belongs on the Mind page, a sound bath on Spa & Recovery;
 *
 *   - in this page's city. An event tagged with an area shows only in that
 *     area's city. One with no area shows only in the default city, because
 *     every events source the aggregator reads is a Perth one -- a Perth
 *     workshop with a missing suburb must not turn up on Margaret River;
 *
 *   - not over yet. An event that started this morning and runs until five
 *     is happening, not past, so anything whose end is still ahead stays in
 *     and says "On now" instead of a start time.
 *
 * Nothing on, no section: an empty "What's on" says the category is dead,
 * which is rarely true and never useful.
 *
 * @var array $args {
 *     @type WP_Term    $term  The practice term the page is about.
 *     @type array|null $city  Cities\current() for the page.
 *     @type int        $limit How many to show. Default 4.
 *     @type array      $rows  Optional: category_events() already run by the page.
 * }
 */

declare(strict_types=1);

use function Oria\Theme\arrow;

$oria_term  = $args['term'] ?? null;
$oria_city  = is_array( $args['city'] ?? null ) ? $args['city'] : null;
$oria_limit = max( 1, (int) ( $args['limit'] ?? 4 ) );

if ( ! $oria_term instanceof WP_Term ) {
	return;
}

$oria_rows = isset( $args['rows'] ) && is_array( $args['rows'] )
	? $args['rows']
	: \Oria\Theme\category_events( $oria_term, $oria_city );

if ( ! $oria_rows ) {
	return;
}

$oria_here  = (string) ( $oria_city['slug'] ?? ( function_exists( '\Oria\Core\Cities\default_city' ) ? ( \Oria\Core\Cities\default_city()['slug'] ?? 'perth' ) : 'perth' ) );
$oria_total = count( $oria_rows );
$oria_rows  = array_slice( $oria_rows, 0, $oria_limit );

$oria_pname = \Oria\Theme\tname( $oria_term );
$oria_all   = 'perth' === $oria_here ? home_url( '/whats-on-perth/' ) : '';
?>
<?php
/*
 * v4: one heading, not two. The parent stacked an H2 "What's on" on an H2
 * "Upcoming ... events and workshops"; the brief asks for an eyebrow and a
 * single H2.
 */
?>
<section class="wrap section section--top-flush floor catevents" id="events" aria-labelledby="catevents-title">
	<p class="micro floor__label"><?php esc_html_e( "What's on", 'oria' ); ?></p>
	<div class="guides__head">
		<h2 class="h3" id="catevents-title">
			<?php
			/* translators: %s: category name, lower case */
			printf( esc_html__( 'Upcoming %s experiences', 'oria' ), esc_html( strtolower( $oria_pname ) ) );
			?>
		</h2>
		<?php if ( $oria_all ) : ?>
			<a class="guides__all" href="<?php echo esc_url( $oria_all ); ?>">
				<?php
				// What's On lists every category, so the link says what is
				// actually there rather than promising a filtered list of these.
				$oria_more = $oria_total - count( $oria_rows );
				echo esc_html(
					$oria_more > 0
						/* translators: %d: events in this category not shown here */
						? sprintf( _n( "%d more on What's On", "%d more on What's On", $oria_more, 'oria' ), $oria_more )
						: __( 'Everything on in Perth', 'oria' )
				);
				?>
				<span aria-hidden="true">→</span>
			</a>
		<?php endif; ?>
	</div>

	<div class="wkrows">
		<?php foreach ( $oria_rows as $oria_r ) : ?>
			<?php
			$oria_ev   = get_post( $oria_r['id'] );
			$oria_time = $oria_r['now']
				? __( 'On now', 'oria' )
				: ( '00:00' === gmdate( 'H:i', $oria_r['ts'] ) ? gmdate( 'D j M', $oria_r['ts'] ) : gmdate( 'D j M, g.ia', $oria_r['ts'] ) );
			?>
			<a class="wkrow<?php echo $oria_r['member'] ? ' wkrow--member' : ''; ?>" href="<?php echo esc_url( (string) get_permalink( $oria_ev ) ); ?>" data-oria-event="category_event">
				<span class="wkrow__thumb" aria-hidden="true">
					<?php if ( has_post_thumbnail( $oria_ev ) ) : ?>
						<?php echo get_the_post_thumbnail( $oria_ev, 'medium_large', array( 'loading' => 'lazy', 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php else : ?>
						<img class="wkrow__scene" src="<?php echo esc_url( \Oria\Theme\event_scene( $oria_ev->ID ) ); ?>" alt="" loading="lazy" decoding="async">
					<?php endif; ?>
					<time class="wkrow__time" datetime="<?php echo esc_attr( gmdate( 'Y-m-d\TH:i', $oria_r['ts'] ) ); ?>"><?php echo esc_html( $oria_time ); ?></time>
				</span>
				<span class="wkrow__body">
					<b><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev ) ); ?><?php if ( $oria_r['member'] ) : ?> <i class="wkrow__flag"><?php esc_html_e( 'Featured practice', 'oria' ); ?></i><?php endif; ?></b>
					<em>
						<?php echo esc_html( $oria_r['suburb'] ); ?>
						<?php if ( ! $oria_r['member'] && $oria_r['src'] ) : ?><span class="wkrow__src"><?php echo esc_html( sprintf( __( 'via %s', 'oria' ), $oria_r['src'] ) ); ?></span><?php endif; ?>
					</em>
				</span>
				<?php if ( $oria_r['price'] ) : ?><span class="wkrow__price"><?php echo esc_html( $oria_r['price'] ); ?></span><?php endif; ?>
				<span class="wkrow__go" aria-hidden="true"><?php echo arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
