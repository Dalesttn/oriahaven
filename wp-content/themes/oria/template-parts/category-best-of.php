<?php
/**
 * "From our Best Of guide": a slim shelf under the first page of listings.
 *
 * Placed after the list rather than above it: by here somebody has scanned
 * ten places and is deciding, which is when an editor's shortlist helps --
 * and above it, it would push the listings back down the page the redesign
 * pulled them up. Four picks at most, from the guide with the most of them
 * on this page, each with the award's seal, its label and the editor's own
 * reason. Everything is the guide's: nothing here is written for the page.
 *
 * Editorial, never paid, and it must look it: sand and a gold seal, the
 * Best Of family -- not the deep-green Featured band. Copy says
 * "shortlisted", never "tested" or "ranked" (see Core\BestOf's header).
 *
 * Args: bo (array from Theme\category_best_of(), at least one guide).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\BestOf;

$oria_bo    = (array) ( $args['bo'] ?? array() );
$oria_lead  = $oria_bo['guides'][0] ?? null;
if ( ! $oria_lead || ! function_exists( '\Oria\Core\BestOf\seal_url' ) ) {
	return;
}
$oria_more  = array_slice( $oria_bo['guides'], 1 );
$oria_picks = array_slice( $oria_lead['picks'], 0, 4 );
$oria_n     = count( $oria_lead['picks'] );
?>
<section class="bestshelf" aria-labelledby="bestshelf-title">
	<header class="bestshelf__head">
		<div>
			<p class="micro bestshelf__eyebrow"><span class="badge--best__mark" aria-hidden="true">&#10022;</span> <?php esc_html_e( 'From our Best Of guide', 'oria' ); ?></p>
			<h2 class="bestshelf__title" id="bestshelf-title"><?php echo esc_html( $oria_lead['title'] ); ?></h2>
			<p class="bestshelf__lede">
				<?php
				printf(
					/* translators: %s: number of places */
					esc_html( _n( '%s place our editors shortlisted, and why.', '%s places our editors shortlisted, and why.', $oria_n, 'oria' ) ),
					esc_html( number_format_i18n( $oria_n ) )
				);
				?>
			</p>
		</div>
		<a class="btn btn--ghost btn--sm bestshelf__cta" href="<?php echo esc_url( $oria_lead['url'] ); ?>" data-oria-event="category_best_of_guide_click">
			<?php esc_html_e( 'Read the full guide', 'oria' ); ?><span aria-hidden="true"><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></span>
		</a>
	</header>

	<ol class="bestshelf__list">
		<?php foreach ( $oria_picks as $oria_e ) : ?>
			<?php
			$oria_lid  = (int) $oria_e['listing'];
			$oria_seal = BestOf\seal_url( (string) $oria_e['award'] );
			$oria_why  = '' !== $oria_e['reason'] ? $oria_e['reason'] : $oria_e['best_for'];
			?>
			<li class="bestpick">
				<?php if ( '' !== $oria_seal ) : ?>
					<img class="bestpick__seal" src="<?php echo esc_url( $oria_seal ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
				<?php endif; ?>
				<div class="bestpick__body">
					<span class="bestpick__award"><?php echo esc_html( $oria_e['label'] ); ?></span>
					<a class="bestpick__name" href="<?php echo esc_url( (string) get_permalink( $oria_lid ) ); ?>" data-oria-event="category_best_of_pick_click"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_lid ) ) ); ?></a>
					<?php $oria_sub = BestOf\suburb( $oria_lid ); ?>
					<?php if ( '' !== $oria_sub ) : ?>
						<span class="bestpick__where"><?php echo esc_html( $oria_sub ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $oria_why ) : ?>
						<p class="bestpick__why"><?php echo esc_html( $oria_why ); ?></p>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>

	<?php if ( $oria_more ) : ?>
		<p class="bestshelf__more">
			<?php esc_html_e( 'Also shortlisted:', 'oria' ); ?>
			<?php foreach ( $oria_more as $oria_i => $oria_g ) : ?>
				<?php echo $oria_i ? ' · ' : ''; ?><a href="<?php echo esc_url( $oria_g['url'] ); ?>" data-oria-event="category_best_of_guide_click"><?php echo esc_html( $oria_g['title'] ); ?></a>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>
</section>
