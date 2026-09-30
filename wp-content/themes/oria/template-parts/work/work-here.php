<?php
/**
 * "Work here" on a business listing (brief section 63): its open jobs and
 * shifts. Renders nothing when there are none -- no "no vacancies" box on
 * every listing page.
 *
 * $args: listing_id, sec (section counter for the heading id)
 */

declare(strict_types=1);

use Oria\Core\Work;

if ( ! function_exists( '\Oria\Core\Work\for_listing' ) ) {
	return;
}
$oria_listing = (int) ( $args['listing_id'] ?? 0 );
$oria_open    = Work\for_listing( $oria_listing );
if ( ! $oria_open ) {
	return;
}
$oria_sec = (int) ( $args['sec'] ?? 0 );
?>
<section class="xp-sec wkhere" id="work-here" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
	<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Work here', 'oria' ); ?> <span class="wkbadge wkbadge--gold"><?php esc_html_e( "We're hiring", 'oria' ); ?></span></h2>
	<div class="wkjobs wkjobs--tight">
		<?php foreach ( array_slice( $oria_open, 0, 4 ) as $oria_id ) : ?>
			<?php get_template_part( 'template-parts/work/card-' . ( Work\SHIFT === get_post_type( $oria_id ) ? 'shift' : 'job' ), null, array( 'id' => (int) $oria_id ) ); ?>
		<?php endforeach; ?>
	</div>
	<p><a href="<?php echo esc_url( Work\list_url( 'jobs' ) ); ?>"><?php esc_html_e( 'View all wellness jobs', 'oria' ); ?> &rarr;</a></p>
</section>
