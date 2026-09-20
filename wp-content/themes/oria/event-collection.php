<?php
/**
 * An events landing page — /free-wellness-events-perth/ and its siblings.
 *
 * Registered and gated by \Oria\Core\EventCollections: this template only
 * ever runs for a collection that already holds enough events to be worth
 * a page, so it has no empty state to design. What it does carry is a way
 * back into the permanent side of the site, because a page made of things
 * that expire should leave you somewhere that does not.
 */

declare(strict_types=1);

use Oria\Core\EventCollections;
use function Oria\Theme\arrow;

get_header();

$oria_slug = EventCollections\current();
$oria_row  = EventCollections\get( $oria_slug );
$oria_ids  = EventCollections\event_ids( $oria_slug );
$oria_all  = get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' );

// The practice category behind this collection, when there is one: the
// permanent page a visitor should land on once these dates have gone.
$oria_practice = (string) ( $oria_row['practice'] ?? '' );
$oria_term     = '' !== $oria_practice ? get_term_by( 'slug', $oria_practice, 'practice' ) : null;
$oria_term     = $oria_term instanceof WP_Term ? $oria_term : null;

// Newest verification stamp across the set, for an honest freshness line.
$oria_checked = 0;
foreach ( $oria_ids as $oria_id ) {
	$oria_stamp   = function_exists( '\Oria\Core\Events\verified' ) ? \Oria\Core\Events\verified( $oria_id ) : 0;
	$oria_checked = max( $oria_checked, $oria_stamp );
}
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( $oria_all ); ?>"><?php esc_html_e( "What's On", 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php echo esc_html( (string) $oria_row['title'] ); ?></span>
	</nav>
	<div style="margin-top:1rem;max-width:56rem">
		<span class="micro">
			<?php
			echo esc_html( sprintf( _n( '%d coming up', '%d coming up', count( $oria_ids ), 'oria' ), count( $oria_ids ) ) );
			if ( $oria_checked ) {
				echo ' · ' . esc_html(
					$oria_checked >= (int) strtotime( 'today', (int) current_time( 'timestamp' ) )
						? __( 'checked today', 'oria' )
						: sprintf( /* translators: %s: date */ __( 'last checked %s', 'oria' ), gmdate( 'j F', $oria_checked ) )
				);
			}
			?>
		</span>
		<h1 class="h1 pagehead__title"><?php echo esc_html( (string) $oria_row['title'] ); ?></h1>
		<p class="lede pagehead__lede" style="max-width:60ch"><?php echo esc_html( (string) ( $oria_row['intro'] ?? '' ) ); ?></p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<div class="evgrid">
		<?php
		foreach ( $oria_ids as $oria_eid ) :
			$oria_start = strtotime( (string) get_post_meta( $oria_eid, 'event_start', true ) );
			$oria_venue = (string) get_post_meta( $oria_eid, 'venue', true );
			$oria_price = (string) get_post_meta( $oria_eid, 'price', true );
			$oria_types = wp_get_post_terms( $oria_eid, 'event_type' );
			$oria_type  = ! is_wp_error( $oria_types ) && $oria_types ? \Oria\Theme\tname( $oria_types[0] ) : '';
			?>
			<a class="evcard" href="<?php echo esc_url( (string) get_permalink( $oria_eid ) ); ?>">
				<?php if ( $oria_start ) : ?>
					<span class="micro"><?php echo esc_html( gmdate( 'D j M', $oria_start ) . ( '00:00' !== gmdate( 'H:i', $oria_start ) ? ' · ' . gmdate( 'g.ia', $oria_start ) : '' ) ); ?></span>
				<?php endif; ?>
				<b class="evcard__name"><?php echo esc_html( \Oria\Theme\ptitle( $oria_eid ) ); ?></b>
				<span class="evcard__meta"><?php echo esc_html( implode( ' · ', array_filter( array( $oria_type, $oria_venue, $oria_price ) ) ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<section class="wrap section section--top-flush">
	<div class="claimprompt" style="max-width:44rem">
		<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'When these dates have passed', 'oria' ); ?></b>
		<p style="font-size:.875rem;color:var(--text-soft)">
			<?php esc_html_e( 'Events come and go. The practices behind them do not, and they are where the next one will be announced.', 'oria' ); ?>
		</p>
		<p style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.8rem">
			<?php if ( $oria_term ) : ?>
				<a class="btn btn--sm btn--dark" href="<?php echo esc_url( (string) get_term_link( $oria_term ) ); ?>">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: practice category */
							__( 'Browse %s in Perth', 'oria' ),
							strtolower( \Oria\Theme\tname( $oria_term ) )
						)
					);
					?>
				</a>
			<?php endif; ?>
			<a class="btn btn--sm" href="<?php echo esc_url( $oria_all ); ?>"><?php esc_html_e( "Everything that's on", 'oria' ); ?> <?php echo arrow(); // phpcs:ignore ?></a>
			<a class="btn btn--sm" href="<?php echo esc_url( home_url( '/submit-an-event/' ) ); ?>"><?php esc_html_e( 'Submit an event', 'oria' ); ?></a>
		</p>
	</div>
</section>

<?php
get_footer();
