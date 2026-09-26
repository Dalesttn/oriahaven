<?php
/**
 * How to join: the practical block for a group, club or class venue.
 *
 * Only what is known is printed. An unknown price prints nothing rather
 * than "free", an unknown schedule prints nothing rather than business
 * hours, and "beginners welcome" appears only where the organiser said so.
 * A group that meets in different places says so and shows no address.
 *
 * The closing line says where the details were checked and when, and is
 * worded so it cannot be read as the organiser having confirmed them --
 * they have not, unless the listing is claimed.
 *
 * Clicks reuse the listing tracker: a booking or registration counts as
 * "book", anything else as "web". Nothing about the visitor is sent.
 *
 * Args: id (int), sec (int, section number), slug (string, for UTM content).
 *
 * @package Oria
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$oria_jn_id = (int) ( $args['id'] ?? 0 );
if ( ! $oria_jn_id || ! function_exists( '\Oria\Core\Sources\joining' ) ) {
	return;
}
$oria_jn     = \Oria\Core\Sources\joining( $oria_jn_id );
$oria_jn_sec = (int) ( $args['sec'] ?? 0 );

$oria_jn_chips = array();
if ( '' !== $oria_jn['cost'] ) {
	$oria_jn_chips[] = \Oria\Core\Sources\cost_label( $oria_jn['cost'] );
}
if ( 'yes' === $oria_jn['beginner'] ) {
	$oria_jn_chips[] = __( 'Beginners welcome', 'oria' );
}
if ( 'yes' === $oria_jn['come_alone'] ) {
	$oria_jn_chips[] = __( 'Fine to come alone', 'oria' );
}

$oria_jn_rows = array();
if ( '' !== $oria_jn['schedule'] ) {
	$oria_jn_rows[] = array( __( 'When', 'oria' ), $oria_jn['schedule'] );
}
if ( $oria_jn['meeting_varies'] ) {
	$oria_jn_rows[] = array( __( 'Where', 'oria' ), __( 'Locations vary — check with the organiser', 'oria' ) );
} elseif ( '' !== $oria_jn['meeting'] ) {
	$oria_jn_rows[] = array( __( 'Where', 'oria' ), $oria_jn['meeting'] );
}
if ( '' !== $oria_jn['price_note'] ) {
	$oria_jn_rows[] = array( __( 'Price', 'oria' ), $oria_jn['price_note'] );
}
foreach ( $oria_jn['details'] as $oria_jn_d ) {
	$oria_jn_rows[] = array( $oria_jn_d['label'], $oria_jn_d['value'] );
}

$oria_jn_url   = '' !== $oria_jn['url'] && function_exists( '\Oria\Theme\outbound' ) ? \Oria\Theme\outbound( $oria_jn['url'], (string) ( $args['slug'] ?? '' ) ) : $oria_jn['url'];
$oria_jn_track = in_array( $oria_jn['method'], array( 'booking', 'register' ), true ) ? 'book' : 'web';

$oria_jn_host = '' !== $oria_jn['source_url'] && function_exists( '\Oria\Theme\link_label' ) ? \Oria\Theme\link_label( $oria_jn['source_url'] ) : '';
$oria_jn_when = '' !== $oria_jn['checked'] ? strtotime( $oria_jn['checked'] ) : false;
?>
<section class="xp-sec xp-join" id="how-to-join" aria-labelledby="xp-s<?php echo (int) $oria_jn_sec; ?>">
	<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_jn_sec; ?>"><?php esc_html_e( 'How to join', 'oria' ); ?></h2>

	<?php if ( $oria_jn_chips ) : ?>
		<ul class="xp-join__chips">
			<?php foreach ( $oria_jn_chips as $oria_jn_c ) : ?>
				<li><?php echo esc_html( $oria_jn_c ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $oria_jn_rows ) : ?>
		<dl class="xp-first">
			<?php foreach ( $oria_jn_rows as $oria_jn_r ) : ?>
				<div class="xp-first__item">
					<dt><?php echo esc_html( $oria_jn_r[0] ); ?></dt>
					<dd><?php echo esc_html( $oria_jn_r[1] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

	<?php if ( '' !== $oria_jn_url ) : ?>
		<p class="xp-join__act">
			<a class="btn btn--dark" href="<?php echo esc_url( $oria_jn_url ); ?>" rel="nofollow noopener" target="_blank"
				data-oria-track="<?php echo esc_attr( $oria_jn_track ); ?>" data-oria-id="<?php echo (int) $oria_jn_id; ?>">
				<?php echo esc_html( \Oria\Core\Sources\join_label( $oria_jn['method'] ) ); ?> <span aria-hidden="true">&rarr;</span>
			</a>
		</p>
	<?php endif; ?>

	<?php if ( $oria_jn_host && $oria_jn_when ) : ?>
		<p class="xp-sec__hint xp-join__src">
			<?php
			printf(
				/* translators: 1: website, 2: date */
				esc_html__( 'Details checked against %1$s on %2$s. Organisers change things — confirm with them before you go.', 'oria' ),
				esc_html( $oria_jn_host ),
				esc_html( wp_date( 'j F Y', (int) $oria_jn_when ) )
			);
			?>
		</p>
	<?php endif; ?>
</section>
