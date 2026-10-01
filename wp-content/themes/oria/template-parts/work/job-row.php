<?php
/**
 * One job as a horizontal row (2026-10 jobs redesign, section 7).
 *
 * The title and "View role" are the links; the bookmark is a separate
 * control, so nothing interactive sits inside another link. Pay is the
 * stored figure with its own period -- never converted. Labels say only
 * what is true: "Featured" for a paid/admin placement, the source for an
 * external ad.
 *
 * $args: id, saved (bool)
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_id      = (int) ( $args['id'] ?? 0 );
$oria_saved   = ! empty( $args['saved'] );
$oria_url     = (string) get_permalink( $oria_id );
$oria_title   = get_the_title( $oria_id );
$oria_listing = (int) Work\meta( $oria_id, 'listing', 0 );
$oria_emp     = Work\employer_name( $oria_id );
$oria_type    = Work\term( $oria_id, Work\EMPLOYMENT );
$oria_pay     = Work\pay_label( $oria_id );
$oria_arr     = (string) Work\meta( $oria_id, 'arrangement' );
$oria_src     = Work\source_name( $oria_id );
$oria_uid     = get_current_user_id();
/* translators: 1: job, 2: employer */
$oria_save_label = $oria_emp ? sprintf( __( 'Save %1$s at %2$s', 'oria' ), $oria_title, $oria_emp ) : sprintf( __( 'Save %s', 'oria' ), $oria_title );
?>
<article class="ohw-job<?php echo Work\is_featured( $oria_id ) ? ' is-featured' : ''; ?>" aria-labelledby="ohw-job-<?php echo (int) $oria_id; ?>">
	<div class="ohw-job__logo" aria-hidden="true">
		<?php if ( $oria_listing && has_post_thumbnail( $oria_listing ) ) : ?>
			<?php echo get_the_post_thumbnail( $oria_listing, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		<?php else : ?>
			<?php echo esc_html( mb_strtoupper( mb_substr( $oria_emp ?: $oria_title, 0, 1 ) ) ); ?>
		<?php endif; ?>
	</div>

	<div class="ohw-job__main">
		<?php if ( Work\is_featured( $oria_id ) ) : ?>
			<p class="ohw-job__flag"><?php esc_html_e( 'Featured listing', 'oria' ); ?></p>
		<?php endif; ?>
		<h3 class="ohw-job__title" id="ohw-job-<?php echo (int) $oria_id; ?>"><a href="<?php echo esc_url( $oria_url ); ?>"><?php echo esc_html( $oria_title ); ?></a></h3>
		<p class="ohw-job__org"><?php echo esc_html( implode( ' · ', array_filter( array( $oria_emp, Work\place_label( $oria_id ) ) ) ) ); ?></p>
		<p class="ohw-job__facts">
			<?php if ( '' !== $oria_pay ) : ?>
				<span class="ohw-job__pay"><?php echo esc_html( $oria_pay ); ?></span>
			<?php else : ?>
				<span class="ohw-job__nopay"><?php esc_html_e( 'Pay not specified', 'oria' ); ?></span>
			<?php endif; ?>
			<?php if ( $oria_type ) : ?><span><?php echo esc_html( $oria_type->name ); ?></span><?php endif; ?>
			<?php if ( in_array( $oria_arr, array( 'remote', 'hybrid' ), true ) ) : ?><span><?php echo esc_html( Work\ARRANGEMENTS[ $oria_arr ] ); ?></span><?php endif; ?>
		</p>
		<p class="ohw-job__meta">
			<span><?php echo esc_html( Work\posted_label( $oria_id ) ); ?></span>
			<?php if ( '' !== $oria_src ) : ?>
				<span><?php echo esc_html( sprintf( __( 'Advertised on %s', 'oria' ), $oria_src ) ); ?></span>
			<?php endif; ?>
		</p>
	</div>

	<div class="ohw-job__acts">
		<a class="ohw-job__view" href="<?php echo esc_url( $oria_url ); ?>" data-wk-event="job_card_click"><?php esc_html_e( 'View role', 'oria' ); ?><span class="wk-vh"> — <?php echo esc_html( $oria_title ); ?></span> <span aria-hidden="true">&rarr;</span></a>
		<?php if ( $oria_uid ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ohw-save" data-ohw-save>
				<?php echo Work\form_fields( 'oria_work_save' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<input type="hidden" name="id" value="<?php echo (int) $oria_id; ?>">
				<button type="submit" class="ohw-save__btn" aria-pressed="<?php echo $oria_saved ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $oria_save_label ); ?>" data-wk-event="job_save">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 3.5h11a.5.5 0 0 1 .5.5v16.2a.3.3 0 0 1-.48.24L12 16.3l-5.52 4.14A.3.3 0 0 1 6 20.2V4a.5.5 0 0 1 .5-.5Z"/></svg>
					<span class="ohw-save__text"><?php echo esc_html( $oria_saved ? __( 'Saved', 'oria' ) : __( 'Save', 'oria' ) ); ?></span>
				</button>
			</form>
		<?php else : ?>
			<a class="ohw-save__btn" href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( $oria_url ), \Oria\Core\MyOria\url( 'login' ) ) ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 3.5h11a.5.5 0 0 1 .5.5v16.2a.3.3 0 0 1-.48.24L12 16.3l-5.52 4.14A.3.3 0 0 1 6 20.2V4a.5.5 0 0 1 .5-.5Z"/></svg>
				<span class="ohw-save__text"><?php esc_html_e( 'Save', 'oria' ); ?></span><span class="wk-vh"> — <?php echo esc_html( sprintf( __( 'sign in to save %s', 'oria' ), $oria_title ) ); ?></span>
			</a>
		<?php endif; ?>
	</div>
</article>
