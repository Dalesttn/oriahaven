<?php
/**
 * /offers/ -- the offers hub (oria-core/includes/offers-hub.php).
 *
 * Promoted offers first, in their own labelled band, then every other live
 * offer in editorial order. Category chips narrow the list (?type=). Cards
 * are template-parts/offer-card.php, the same as the category pages.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\OffersHub as Hub;

get_header();

$oria_type  = Hub\current_type();
$oria_rows  = array_values( array_filter( Hub\rows(), static fn( array $r ): bool => '' === $oria_type || $r['cat'] === $oria_type ) );
$oria_promo = array();
$oria_rest  = array();
foreach ( $oria_rows as $oria_r ) {
	$oria_p = function_exists( '\Oria\Core\Offers\promotion' ) ? \Oria\Core\Offers\promotion( (int) $oria_r['id'], 'hub' ) : array();
	if ( $oria_p ) {
		$oria_r['promo'] = $oria_p;
		$oria_promo[]    = $oria_r;
	} else {
		$oria_rest[] = $oria_r;
	}
}
$oria_chips = Hub\chips();
$oria_total = count( Hub\rows() );
?>

<section class="wrap section oh-offers-hub">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<span aria-current="page"><?php esc_html_e( 'Offers', 'oria' ); ?></span>
	</nav>

	<header class="oh-offers-hub__head">
		<p class="micro"><?php echo esc_html( sprintf( /* translators: %d: number of offers */ _n( '%d offer in Perth', '%d offers in Perth', $oria_total, 'oria' ), $oria_total ) ); ?></p>
		<h1 class="oh-offers-hub__title"><?php esc_html_e( 'Wellness offers in Perth', 'oria' ); ?></h1>
		<p class="oh-offers-hub__lede"><?php esc_html_e( 'Intro passes, first-visit deals and specials, each read from the business’s own site, with the date we checked it. An offer disappears the day after it ends. Nothing here is paid for unless it says Promoted.', 'oria' ); ?></p>
	</header>

	<?php if ( count( $oria_chips ) > 1 ) : ?>
		<nav class="oh-offers-hub__chips" aria-label="<?php esc_attr_e( 'Offers by category', 'oria' ); ?>">
			<a class="oh-offers-hub__chip" href="<?php echo esc_url( Hub\url() ); ?>"<?php echo '' === $oria_type ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'oria' ); ?> <span><?php echo esc_html( (string) $oria_total ); ?></span></a>
			<?php foreach ( $oria_chips as $oria_c ) : ?>
				<a class="oh-offers-hub__chip" href="<?php echo esc_url( Hub\url( $oria_c['slug'] ) ); ?>"<?php echo $oria_type === $oria_c['slug'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $oria_c['name'] ); ?> <span><?php echo esc_html( (string) $oria_c['count'] ); ?></span></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( $oria_promo ) : ?>
		<section class="oh-offers-hub__promo" aria-labelledby="oh-hub-promo">
			<div class="oh-offers-hub__band-head">
				<h2 class="h4" id="oh-hub-promo"><?php esc_html_e( 'Promoted offers', 'oria' ); ?></h2>
				<p><?php esc_html_e( 'These businesses pay for this spot. The offers are checked like every other one here.', 'oria' ); ?></p>
			</div>
			<ul class="oh-category-offers__grid">
				<?php foreach ( $oria_promo as $oria_r ) : ?>
					<?php get_template_part( 'template-parts/offer-card', null, array( 'id' => (int) $oria_r['id'], 'offer' => $oria_r['offer'], 'place' => 'hub_promoted', 'promo' => $oria_r['promo'] ) ); ?>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $oria_rest ) : ?>
		<ul class="oh-category-offers__grid oh-offers-hub__grid">
			<?php foreach ( $oria_rest as $oria_r ) : ?>
				<?php get_template_part( 'template-parts/offer-card', null, array( 'id' => (int) $oria_r['id'], 'offer' => $oria_r['offer'], 'place' => 'hub' ) ); ?>
			<?php endforeach; ?>
		</ul>
	<?php elseif ( ! $oria_promo ) : ?>
		<p class="oh-offers-hub__empty"><?php esc_html_e( 'No offers in this category right now.', 'oria' ); ?> <a href="<?php echo esc_url( Hub\url() ); ?>"><?php esc_html_e( 'See every offer', 'oria' ); ?></a></p>
	<?php endif; ?>

	<div class="oh-offers-hub__signup">
		<?php get_template_part( 'template-parts/offers-signup', null, array( 'source' => 'category', 'interest' => $oria_type ) ); ?>
	</div>
</section>

<?php
get_footer();
