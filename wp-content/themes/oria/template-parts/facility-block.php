<?php
/**
 * One venue's access to a facility, on its card: what the visit costs and
 * includes, who can go, and when that was checked.
 *
 * Drawn here for the page without scripting and again by app.js
 * facilityBlock() from the same summary (FacilityAccess\summary), so the two
 * cannot drift. Everything shown is a saved, sourced fact; an unknown is
 * left out rather than guessed.
 *
 * @var array $args { s: array (FacilityAccess\summary) }
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_s = (array) ( $args['s'] ?? array() );
if ( ! $oria_s ) {
	return;
}
$oria_what = array_filter( array( (string) $oria_s['product'], (string) $oria_s['duration'] ) );
?>
<div class="fac">
	<?php if ( '' !== (string) $oria_s['notice'] ) : ?>
		<p class="fac__notice" role="note"><?php echo esc_html( (string) $oria_s['notice'] ); ?></p>
	<?php endif; ?>
	<p class="fac__price">
		<b><?php echo esc_html( (string) $oria_s['price_text'] ); ?></b>
		<?php if ( $oria_what ) : ?>
			<span><?php echo esc_html( implode( ' · ', $oria_what ) ); ?></span>
		<?php endif; ?>
	</p>
	<?php if ( '' !== (string) $oria_s['conditions'] ) : ?>
		<p class="fac__cond"><?php echo esc_html( (string) $oria_s['conditions'] ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== (string) $oria_s['access'] ) : ?>
		<p class="fac__access"><?php echo esc_html( (string) $oria_s['access'] ); ?></p>
	<?php endif; ?>
	<?php if ( $oria_s['includes'] ) : ?>
		<p class="fac__incl"><span><?php esc_html_e( 'Includes', 'oria' ); ?></span> <?php echo esc_html( implode( ', ', (array) $oria_s['includes'] ) ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== (string) $oria_s['quiet'] ) : ?>
		<p class="fac__quiet"><?php echo esc_html( (string) $oria_s['quiet'] ); ?></p>
	<?php endif; ?>
	<?php if ( $oria_s['essentials'] ) : ?>
		<p class="fac__ess"><?php echo esc_html( implode( ' · ', array_slice( (array) $oria_s['essentials'], 0, 3 ) ) ); ?></p>
	<?php endif; ?>
	<p class="fac__meta">
		<?php if ( '' !== (string) $oria_s['book_url'] ) : ?>
			<a href="<?php echo esc_url( (string) $oria_s['book_url'] ); ?>" rel="nofollow noopener" target="_blank" data-oria-event="facility_check_sessions_click" data-oria-placement="facility_card"><?php esc_html_e( 'Check sessions and prices', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(opens the venue\'s site in a new tab)', 'oria' ); ?></span></a>
		<?php endif; ?>
		<?php if ( '' !== (string) $oria_s['checked'] ) : ?>
			<span>
				<?php
				printf(
					/* translators: %s: date */
					esc_html__( 'Checked %s', 'oria' ),
					esc_html( (string) $oria_s['checked'] )
				);
				?>
				<?php if ( '' !== (string) $oria_s['source'] ) : ?>
					&middot; <a href="<?php echo esc_url( (string) $oria_s['source'] ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'source', 'oria' ); ?></a>
				<?php endif; ?>
			</span>
		<?php endif; ?>
	</p>
</div>
