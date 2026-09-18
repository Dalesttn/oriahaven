<?php
/**
 * Section: Trend to Try on the home page.
 *
 * Code-injected on the front page after the Best Of section (page.php), the
 * same way Best Of is, so it ships with git pull and needs no page editing.
 * One featured trend -- the hub's own choice -- never a feed, and no embed:
 * the card uses the trend's Oria-owned picture. Renders nothing until a
 * trend is published.
 *
 * @package Oria
 */

declare(strict_types=1);

use Oria\Core\Trends;
use function Oria\Theme\arrow;

if ( ! function_exists( '\Oria\Core\Trends\published' ) ) {
	return;
}
$oria_all = Trends\published();
if ( ! $oria_all ) {
	return;
}
?>
<section class="section">
	<div class="wrap">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'Trend to Try', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'Seen it in your feed?', 'oria' ); ?></h2>
			</div>
			<p class="sec-head__aside"><?php esc_html_e( 'The wellness trends everyone is posting, explained: what they are, what to expect, what the evidence says, and where to try them around Perth.', 'oria' ); ?></p>
		</div>
		<?php get_template_part( 'template-parts/trend-card', null, array( 'post' => $oria_all[0], 'feature' => true, 'location' => 'home' ) ); ?>
		<?php if ( count( $oria_all ) > 1 ) : ?>
			<p class="bohome__more"><a class="btn btn--ghost" href="<?php echo esc_url( Trends\hub_url() ); ?>" data-trend-cta="related" data-trend-where="home"><?php esc_html_e( 'More trends explained', 'oria' ); ?><?php echo arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></p>
		<?php endif; ?>
	</div>
</section>
