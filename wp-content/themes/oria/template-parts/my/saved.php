<?php
/**
 * Saved places: everything the member wants to try, newest first.
 */

declare(strict_types=1);

use Oria\Core\Activity;

$oria_uid = get_current_user_id();
$oria_ids = Activity\ids( $oria_uid, Activity\SAVED );
if ( function_exists( '\Oria\Theme\prime_listings' ) ) {
	\Oria\Theme\prime_listings( $oria_ids );
}
?>
<section class="wrap my my--last">
	<div class="my__hello">
		<span class="micro" data-my-count-label="saved"><?php echo $oria_ids ? esc_html( sprintf( _n( '%s saved place', '%s saved places', count( $oria_ids ), 'oria' ), number_format_i18n( count( $oria_ids ) ) ) ) : ''; ?></span>
		<h1 class="h1"><?php esc_html_e( 'Saved places', 'oria' ); ?></h1>
		<p class="lede"><?php esc_html_e( 'The places you want to try. When you have been, mark one as tried and it moves into your passport.', 'oria' ); ?></p>
	</div>

	<div class="myempty" data-my-empty<?php echo $oria_ids ? ' hidden' : ''; ?>>
		<h2 class="h3"><?php esc_html_e( 'Nothing saved yet.', 'oria' ); ?></h2>
		<p><?php esc_html_e( 'Explore wellness experiences around Perth and tap the heart to save anything you’d like to try.', 'oria' ); ?></p>
		<a class="btn btn--dark" href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Explore practices', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</div>

	<?php if ( $oria_ids ) : ?>
		<div class="mygrid" data-my-list>
			<?php foreach ( $oria_ids as $oria_id ) : ?>
				<?php get_template_part( 'template-parts/my/place', null, array( 'id' => $oria_id, 'mode' => 'saved' ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
