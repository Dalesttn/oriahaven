<?php
/**
 * A job card (brief section 34): logo, title, business, suburb, type, pay,
 * posted. The whole card is one link; nothing inside it is a second link.
 *
 * $args: id
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_id      = (int) ( $args['id'] ?? 0 );
$oria_listing = (int) Work\meta( $oria_id, 'listing', 0 );
$oria_emp     = Work\employer_name( $oria_id );
$oria_type    = Work\term( $oria_id, Work\EMPLOYMENT );
$oria_pay     = Work\pay_label( $oria_id );
$oria_feat    = Work\is_featured( $oria_id );
$oria_arr     = (string) Work\meta( $oria_id, 'arrangement' );
?>
<a class="wkcard wkcard--job<?php echo $oria_feat ? ' is-featured' : ''; ?>" href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>" data-wk-event="job_card_click">
	<span class="wkcard__logo" aria-hidden="true">
		<?php if ( $oria_listing && has_post_thumbnail( $oria_listing ) ) : ?>
			<?php echo get_the_post_thumbnail( $oria_listing, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		<?php else : ?>
			<?php echo esc_html( mb_strtoupper( mb_substr( $oria_emp ?: get_the_title( $oria_id ), 0, 1 ) ) ); ?>
		<?php endif; ?>
	</span>
	<span class="wkcard__body">
		<?php if ( $oria_feat ) : ?>
			<span class="wkbadge wkbadge--gold"><?php esc_html_e( 'Featured', 'oria' ); ?></span>
		<?php endif; ?>
		<span class="wkcard__title"><?php echo esc_html( get_the_title( $oria_id ) ); ?></span>
		<span class="wkcard__org"><?php echo esc_html( implode( ' · ', array_filter( array( $oria_emp, Work\place_label( $oria_id ) ) ) ) ); ?></span>
		<span class="wkcard__facts">
			<?php if ( $oria_type ) : ?><span class="wkchip"><?php echo esc_html( $oria_type->name ); ?></span><?php endif; ?>
			<?php if ( in_array( $oria_arr, array( 'remote', 'hybrid' ), true ) ) : ?><span class="wkchip"><?php echo esc_html( Work\ARRANGEMENTS[ $oria_arr ] ); ?></span><?php endif; ?>
			<?php if ( '' !== $oria_pay ) : ?><span class="wkchip wkchip--pay"><?php echo esc_html( $oria_pay ); ?></span><?php endif; ?>
		</span>
		<span class="wkcard__foot">
			<span><?php echo esc_html( Work\posted_ago( $oria_id ) ); ?></span>
			<span class="wkcard__cta"><?php esc_html_e( 'View job', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></span>
		</span>
	</span>
</a>
