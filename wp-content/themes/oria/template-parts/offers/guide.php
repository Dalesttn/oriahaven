<?php
/**
 * Below the results on /offers/ pages: the short guide to choosing, the
 * questions (template-parts/faq, which prints its own FAQPage markup from
 * the same array), how offers are checked, related Oria pages, and a quiet
 * line for businesses. Copy from data/offer-pages.json, placeholders filled
 * from the page's own offers (OffersHub\fill); anything that cannot be
 * filled truthfully is dropped.
 *
 * $args: page (config), rows, activity.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\OffersHub as Hub;

$oria_p    = (array) ( $args['page'] ?? array() );
$oria_rows = (array) ( $args['rows'] ?? array() );
$oria_act  = (string) ( $args['activity'] ?? '' );
$oria_hub  = Hub\page();

$oria_guide = (array) ( $oria_p['guide'] ?? array() );
$oria_title = (string) ( $oria_p['guide_title'] ?? '' );
if ( ! $oria_guide ) {
	$oria_guide = (array) ( $oria_hub['guide'] ?? array() );
	$oria_title = (string) ( $oria_hub['guide_title'] ?? '' );
}
$oria_faq = array();
foreach ( (array) ( $oria_p['faq'] ?? array() ) as $oria_q ) {
	$oria_a = Hub\fill( (string) ( $oria_q['a'] ?? '' ), $oria_rows );
	if ( '' !== $oria_a ) {
		$oria_faq[] = array( 'q' => (string) $oria_q['q'], 'a' => $oria_a );
	}
}
// Activity pages also answer the hub's questions about sources and promotion.
if ( '' !== $oria_act ) {
	foreach ( array_slice( (array) ( $oria_hub['faq'] ?? array() ), 0, 2 ) as $oria_q ) {
		$oria_faq[] = array( 'q' => (string) $oria_q['q'], 'a' => (string) $oria_q['a'] );
	}
}

$oria_links = array();
foreach ( (array) ( $oria_p['links'] ?? array() ) as $oria_l ) {
	$oria_t = get_term_by( 'slug', (string) ( $oria_l['practice'] ?? '' ), 'practice' );
	if ( ! $oria_t instanceof WP_Term || ! function_exists( '\Oria\Core\PracticesIndex\category_url' ) ) {
		continue;
	}
	$oria_u = \Oria\Core\PracticesIndex\category_url( $oria_t );
	if ( ! empty( $oria_l['facet'] ) && function_exists( '\Oria\Core\PracticesIndex\resolve_facet' ) ) {
		$oria_fc = \Oria\Core\PracticesIndex\resolve_facet( $oria_t, (string) $oria_l['facet'] );
		if ( ! $oria_fc ) {
			continue;
		}
		$oria_u = \Oria\Core\PracticesIndex\facet_canonical_url( $oria_t, $oria_fc );
	}
	$oria_links[] = array( 'label' => (string) $oria_l['label'], 'url' => $oria_u );
}
// The other activity pages that earn their place.
foreach ( Hub\activities() as $oria_c ) {
	if ( $oria_c['slug'] !== $oria_act && Hub\indexable( $oria_c['slug'] ) ) {
		$oria_links[] = array( 'label' => (string) ( Hub\page( $oria_c['slug'] )['h1'] ?? $oria_c['name'] ), 'url' => Hub\url( $oria_c['slug'] ) );
	}
}
?>
<div class="ofx-after">
	<?php if ( $oria_guide ) : ?>
		<section class="ofx-guide" aria-labelledby="ofx-guide-title">
			<h2 class="ofx-section__title" id="ofx-guide-title"><?php echo esc_html( $oria_title ); ?></h2>
			<div class="ofx-guide__grid">
				<?php foreach ( $oria_guide as $oria_g ) : ?>
					<?php $oria_txt = Hub\fill( (string) ( $oria_g['p'] ?? '' ), $oria_rows ); ?>
					<?php if ( '' === $oria_txt ) { continue; } ?>
					<div class="ofx-guide__item">
						<h3><?php echo esc_html( (string) $oria_g['h'] ); ?></h3>
						<p><?php echo esc_html( $oria_txt ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $oria_faq ) : ?>
		<div class="ofx-faq">
			<?php get_template_part( 'template-parts/faq', null, array( 'faqs' => $oria_faq, 'heading' => __( 'Common questions', 'oria' ), 'id' => 'ofx-faq' ) ); ?>
		</div>
	<?php endif; ?>

	<section class="ofx-method" id="ofx-method" aria-labelledby="ofx-method-title">
		<h2 class="ofx-method__title" id="ofx-method-title"><?php esc_html_e( 'How we check offers', 'oria' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Most offers are read on the business’s own website, or the booking page it links to. Never deal sites, review sites or social media.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Offers marked “Added by the business” come from the business itself, through its listing. We check a business’s first offer before it appears, and the card shows when it was last updated.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Prices, inclusions and conditions are as the business states them. We don’t work out savings or prices it hasn’t published.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Each card shows the date we last checked it. Offers are re-checked every month, and dated ones come off the day after they end.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Promoted offers are paid placements and are always labelled. Nobody pays to be included or for where they appear otherwise.', 'oria' ); ?></li>
		</ul>
		<p><?php printf( wp_kses( __( 'Something out of date? Use “Report a problem” on the offer, or email <a href="%s">hello@oriahaven.com.au</a>.', 'oria' ), array( 'a' => array( 'href' => array() ) ) ), esc_url( 'mailto:hello@oriahaven.com.au?subject=' . rawurlencode( 'Offers page' ) ) ); ?></p>
	</section>

	<?php if ( $oria_links ) : ?>
		<nav class="ofx-related" aria-labelledby="ofx-related-title">
			<h2 class="ofx-related__title" id="ofx-related-title"><?php esc_html_e( 'Keep exploring', 'oria' ); ?></h2>
			<ul>
				<?php foreach ( $oria_links as $oria_l ) : ?>
					<li><a href="<?php echo esc_url( $oria_l['url'] ); ?>"><?php echo esc_html( $oria_l['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<p class="ofx-biz"><?php printf( wp_kses( __( 'Run a Perth wellness business with an offer on? <a href="%s" data-oria-event="offers_add_click">Add your offer</a>. It’s free.', 'oria' ), array( 'a' => array( 'href' => array(), 'data-oria-event' => array() ) ) ), esc_url( home_url( '/offers/add/' ) ) ); ?></p>
</div>
