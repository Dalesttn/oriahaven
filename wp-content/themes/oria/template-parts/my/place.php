<?php
/**
 * One place in a member's list: saved, or tried.
 *
 * Reads the listing at render (name, photo, suburb, category, any Best Of
 * badge) so nothing here goes stale; the only member-specific facts are
 * the date and the two buttons. The buttons are handled by initMe() in
 * app.js, and the card is removed from the page when the member removes it.
 *
 * $args: id (listing), mode ('saved' | 'tried')
 */

declare(strict_types=1);

use Oria\Core\Activity;
use Oria\Core\BestOf;
use Oria\Core\Passport;

$oria_id   = (int) ( $args['id'] ?? 0 );
$oria_mode = 'tried' === ( $args['mode'] ?? '' ) ? 'tried' : 'saved';
if ( ! $oria_id || 'publish' !== get_post_status( $oria_id ) ) {
	return;
}
$oria_uid   = get_current_user_id();
$oria_slug  = (string) get_post_field( 'post_name', $oria_id );
$oria_url   = (string) get_permalink( $oria_id );
$oria_cats  = function_exists( '\Oria\Core\Categories\top_for' ) ? \Oria\Core\Categories\top_for( $oria_id, 1 ) : array();
$oria_cat   = $oria_cats ? \Oria\Theme\tname( $oria_cats[0]['term'] ) : '';
$oria_sub   = BestOf\suburb( $oria_id );
$oria_best  = BestOf\card_badge( $oria_id );
$oria_tried = Activity\has( $oria_uid, $oria_id, Activity\TRIED );
?>
<article class="myplace" data-my-place="<?php echo esc_attr( $oria_slug ); ?>">
	<a class="myplace__media" href="<?php echo esc_url( $oria_url ); ?>" tabindex="-1" aria-hidden="true">
		<img src="<?php echo esc_url( \Oria\Theme\listing_image( $oria_id ) ); ?>" alt="" loading="lazy"
			onerror="this.onerror=null;this.src='<?php echo esc_js( \Oria\Theme\listing_scene( $oria_id ) ); ?>'">
	</a>
	<div class="myplace__body">
		<span class="myplace__meta"><?php echo esc_html( implode( ' · ', array_filter( array( $oria_cat, $oria_sub ) ) ) ); ?></span>
		<h3 class="myplace__name"><a href="<?php echo esc_url( $oria_url ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_id ) ) ); ?></a></h3>
		<div class="myplace__tags">
			<?php if ( $oria_best ) : ?>
				<?php echo BestOf\badge_html( $oria_best['label'], $oria_best['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php endif; ?>
			<?php if ( 'tried' === $oria_mode ) : ?>
				<span class="myplace__when"><?php echo esc_html( Passport\tried_label( $oria_uid, $oria_id ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="myplace__acts">
			<a class="btn btn--sm btn--dark" href="<?php echo esc_url( $oria_url ); ?>"><?php esc_html_e( 'View practice', 'oria' ); ?></a>
			<?php if ( 'saved' === $oria_mode ) : ?>
				<button class="btn btn--sm btn--ghost triedbtn" type="button" data-my-tried="<?php echo esc_attr( $oria_slug ); ?>" aria-pressed="<?php echo $oria_tried ? 'true' : 'false'; ?>">
					<span class="triedbtn__mark" aria-hidden="true">&#10003;</span>
					<span class="triedbtn__label"><?php echo $oria_tried ? esc_html__( 'In your passport', 'oria' ) : esc_html__( "I've tried this", 'oria' ); ?></span>
				</button>
			<?php endif; ?>
			<button class="btn btn--sm btn--plain myplace__remove" type="button" data-my-remove="<?php echo esc_attr( $oria_mode ); ?>" data-slug="<?php echo esc_attr( $oria_slug ); ?>">
				<?php echo 'tried' === $oria_mode ? esc_html__( 'Remove from passport', 'oria' ) : esc_html__( 'Remove', 'oria' ); ?>
			</button>
		</div>
	</div>
</article>
