<?php
/**
 * One recommended practice on a Best Of guide.
 *
 * Bigger than a directory card because it has more to say: the badge names
 * the award, the reason says why, the highlights say what a first visit
 * gets. Name, suburb, photo and price are read from the listing at render,
 * so a guide cannot quietly go stale.
 *
 * $args: entry (from BestOf\entries()), rank (int, 1-based)
 */

declare(strict_types=1);

use Oria\Core\BestOf;

$oria_e = isset( $args['entry'] ) && is_array( $args['entry'] ) ? $args['entry'] : null;
if ( ! $oria_e || empty( $oria_e['listing'] ) ) {
	return;
}
$oria_id   = (int) $oria_e['listing'];
$oria_rank = (int) ( $args['rank'] ?? 0 );
$oria_url  = (string) get_permalink( $oria_id );
$oria_web  = (string) get_field( 'website', $oria_id );
$oria_book = (string) get_field( 'booking_url', $oria_id );
$oria_out  = $oria_book ?: $oria_web;
$oria_cats = function_exists( '\Oria\Core\Categories\top_for' ) ? \Oria\Core\Categories\top_for( $oria_id ) : array();
$oria_cat  = $oria_cats ? \Oria\Theme\tname( $oria_cats[0]['term'] ) : '';
$oria_sub  = BestOf\suburb( $oria_id );
$oria_from = BestOf\price_from( $oria_id );
$oria_rate = \Oria\Theme\effective_rating( $oria_id );
?>
<li class="bopick reveal" id="pick-<?php echo esc_attr( (string) $oria_rank ); ?>">
	<div class="bopick__media">
		<a href="<?php echo esc_url( $oria_url ); ?>" tabindex="-1" aria-hidden="true">
			<img src="<?php echo esc_url( \Oria\Theme\listing_image( $oria_id, 'oria-card' ) ); ?>" alt="" loading="lazy"
				onerror="this.onerror=null;this.src='<?php echo esc_js( \Oria\Theme\listing_scene( $oria_id ) ); ?>'">
		</a>
		<div class="listing__quick">
			<button class="qact" type="button" data-card-save="<?php echo esc_attr( (string) get_post_field( 'post_name', $oria_id ) ); ?>" aria-pressed="false" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: practice name */ __( 'Save %s', 'oria' ), \Oria\Theme\ptitle( get_post( $oria_id ) ) ) ); ?>" title="<?php esc_attr_e( 'Save', 'oria' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 8.6a4.9 4.9 0 0 0-8.8-3A4.9 4.9 0 0 0 3.2 8.6c0 4.9 8.8 10.2 8.8 10.2s8.8-5.3 8.8-10.2Z"/></svg>
			</button>
		</div>
	</div>
	<div class="bopick__body">
		<?php $oria_seal = BestOf\seal_url( $oria_e['award'] ); ?>
		<?php if ( $oria_seal ) : ?>
			<img class="bopick__seal" src="<?php echo esc_url( $oria_seal ); ?>" alt="" width="320" height="320" loading="lazy">
		<?php endif; ?>
		<div class="bopick__top">
			<?php if ( $oria_rank ) : ?>
				<span class="bopick__rank" aria-hidden="true"><?php echo esc_html( str_pad( (string) $oria_rank, 2, '0', STR_PAD_LEFT ) ); ?></span>
			<?php endif; ?>
			<?php echo BestOf\badge_html( $oria_e['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php if ( '' !== $oria_e['fact'] ) : ?>
				<?php // A fact, not an award: a grey pill, never the badge shape. ?>
				<span class="pill bopick__fact"><?php echo esc_html( $oria_e['fact'] ); ?></span>
			<?php endif; ?>
		</div>
		<h3 class="bopick__name"><a href="<?php echo esc_url( $oria_url ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_id ) ) ); ?></a></h3>
		<p class="bopick__where">
			<?php echo esc_html( implode( ' · ', array_filter( array( $oria_sub, $oria_cat ) ) ) ); ?>
			<?php if ( $oria_rate['rating'] > 0 ) : ?>
				<span class="rating"><svg class="rating__star" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg><?php echo esc_html( number_format_i18n( $oria_rate['rating'], 1 ) ); ?><?php if ( 'google' === $oria_rate['source'] ) : ?><span class="rating__count"><?php esc_html_e( 'Google', 'oria' ); ?></span><?php endif; ?></span>
			<?php endif; ?>
		</p>
		<?php if ( $oria_e['reason'] ) : ?>
			<p class="bopick__why"><?php echo esc_html( $oria_e['reason'] ); ?></p>
		<?php endif; ?>
		<?php if ( $oria_e['highlights'] ) : ?>
			<ul class="bopick__facts">
				<?php foreach ( $oria_e['highlights'] as $oria_h ) : ?>
					<li><?php echo esc_html( $oria_h ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( '' !== $oria_e['sessions'] || '' !== $oria_e['rebate'] ) : ?>
			<dl class="bopick__details">
				<?php if ( '' !== $oria_e['sessions'] ) : ?>
					<div><dt><?php esc_html_e( 'Sessions', 'oria' ); ?></dt><dd><?php echo esc_html( $oria_e['sessions'] ); ?></dd></div>
				<?php endif; ?>
				<?php if ( '' !== $oria_e['rebate'] ) : ?>
					<div><dt><?php esc_html_e( 'Private health', 'oria' ); ?></dt><dd><?php echo esc_html( BestOf\rebate_label( $oria_e ) ); ?></dd></div>
				<?php endif; ?>
			</dl>
		<?php endif; ?>
		<div class="bopick__foot">
			<span class="bopick__price">
				<?php echo esc_html( BestOf\price_line( $oria_e ) ); ?>
				<?php if ( BestOf\duration( $oria_id ) ) : ?>
					<span class="bopick__time">&middot; <?php echo esc_html( BestOf\duration( $oria_id ) ); ?></span>
				<?php endif; ?>
			</span>
			<span class="bopick__acts">
				<a class="btn btn--sm btn--dark" href="<?php echo esc_url( $oria_url ); ?>"><?php esc_html_e( 'View practice', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<?php if ( $oria_out ) : ?>
					<a class="btn btn--sm btn--ghost" href="<?php echo esc_url( \Oria\Theme\outbound( $oria_out, 'best-of' ) ); ?>" rel="nofollow noopener" target="_blank"><?php echo $oria_book ? esc_html__( 'Book', 'oria' ) : esc_html__( 'Visit website', 'oria' ); ?></a>
				<?php endif; ?>
			</span>
		</div>
	</div>
</li>
