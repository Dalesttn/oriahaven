<?php
/**
 * Section: Best Of guides on the home page.
 *
 * Code-injected on the front page after the latest journal posts (see
 * page.php), so it ships with git pull and needs no page editing. Renders
 * nothing until a guide is published.
 */

declare(strict_types=1);

use Oria\Core\BestOf;
use function Oria\Theme\arrow;

$oria_guides = BestOf\guides();
if ( ! $oria_guides ) {
	return;
}
// Featured guides first, then whatever else exists, three in all.
$oria_pick = array_values( array_filter( $oria_guides, static fn( $g ) => BestOf\is_featured( $g->ID ) ) );
foreach ( $oria_guides as $oria_g ) {
	if ( count( $oria_pick ) >= 3 ) {
		break;
	}
	if ( ! in_array( $oria_g, $oria_pick, true ) ) {
		$oria_pick[] = $oria_g;
	}
}
$oria_pick = array_slice( $oria_pick, 0, 3 );
?>
<section class="section band-sand">
	<div class="wrap">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'Best of Perth', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'Not sure where to start?', 'oria' ); ?></h2>
			</div>
			<p class="sec-head__aside"><?php esc_html_e( 'Our editors shortlist a handful of standout places for one need at a time: a first yoga class, a proper sauna, Pilates without the jargon.', 'oria' ); ?></p>
		</div>
		<div class="bogrid bogrid--3">
			<?php foreach ( $oria_pick as $oria_g ) : ?>
				<?php get_template_part( 'template-parts/best-guide-card', null, array( 'post' => $oria_g ) ); ?>
			<?php endforeach; ?>
		</div>
		<p class="bohome__more"><a class="btn btn--ghost" href="<?php echo esc_url( BestOf\hub_url() ); ?>"><?php esc_html_e( 'Explore Best Of', 'oria' ); ?><?php echo arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></p>
	</div>
</section>
