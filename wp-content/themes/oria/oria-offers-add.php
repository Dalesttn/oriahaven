<?php
/**
 * /offers/add/ -- how a business gets its offer onto Oria Haven
 * (oria-core/includes/owner-offers.php). A signed-in owner never sees this:
 * they are sent straight to the offer form in My Oria. Everyone else gets
 * the three steps, what makes a good offer, and an example card taken from
 * the live offers page, drawn by the same template.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\OwnerOffers as OO;

wp_enqueue_style( 'oria-offers', get_template_directory_uri() . '/assets/css/offers.css', array(), (string) filemtime( get_theme_file_path( 'assets/css/offers.css' ) ) );

$oria_rows    = function_exists( '\Oria\Core\OffersHub\rows' ) ? \Oria\Core\OffersHub\rows() : array();
$oria_example = null;
foreach ( $oria_rows as $oria_r ) {
	// A clear one: a fixed price, things included, and a photo.
	if ( ! $oria_r['promo'] && null !== $oria_r['amount'] && ! empty( $oria_r['offer']['extra']['includes'] ) && ( has_post_thumbnail( $oria_r['id'] ) || ( function_exists( '\Oria\Core\Places\card_photo' ) && '' !== \Oria\Core\Places\card_photo( $oria_r['id'] ) ) ) ) {
		$oria_example = $oria_r;
		break;
	}
}
$oria_faq = array(
	array( 'q' => __( 'Does it cost anything?', 'oria' ), 'a' => __( 'No. Claiming your listing is free, and so is adding an offer to it.', 'oria' ) ),
	array( 'q' => __( 'How long until it shows?', 'oria' ), 'a' => __( 'We check a business’s first offer before it goes live, usually within two working days, and email you either way. After that, changes you make show straight away.', 'oria' ) ),
	array( 'q' => __( 'How do I change or take it down?', 'oria' ), 'a' => __( 'In My Oria, under Offers and social. Empty the first line and save to take it down; that happens straight away. An offer with an end date comes off the day after it ends.', 'oria' ) ),
	array( 'q' => __( 'Oria Haven already shows an offer from my website. What happens to it?', 'oria' ), 'a' => __( 'We list offers that businesses advertise on their own sites, with a link back. Once you’ve claimed your listing, the offer is yours to edit, replace or remove.', 'oria' ) ),
	array( 'q' => __( 'Can I promote my offer?', 'oria' ), 'a' => __( 'Promoted placements at the top of the offers pages are paid and always labelled “Promoted”. Email hello@oriahaven.com.au to ask about them.', 'oria' ) ),
);

get_header();
?>

<div class="ofx ofx--add">
	<div class="ofx__wrap">
		<nav class="crumbs ofx__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( home_url( '/offers/' ) ); ?>"><?php esc_html_e( 'Offers', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<span aria-current="page"><?php esc_html_e( 'Add your offer', 'oria' ); ?></span>
		</nav>

		<div class="ofx-add">
			<header class="ofx-hero">
				<p class="ofx-hero__eyebrow"><?php esc_html_e( 'For Perth wellness businesses', 'oria' ); ?></p>
				<h1 class="ofx-hero__title"><?php esc_html_e( 'Add your offer to Oria Haven', 'oria' ); ?></h1>
				<p class="ofx-hero__lede"><?php esc_html_e( 'It’s free. Your offer shows on your profile, on the category pages you appear in, and on the Oria Haven offers pages, where people compare intro passes and specials across Perth.', 'oria' ); ?></p>
				<p class="ofx-add__ctas">
					<a class="ofx-btn" href="<?php echo esc_url( OO\editor_url() ); ?>" data-oria-event="offers_add_start"><?php esc_html_e( 'Sign in and add your offer', 'oria' ); ?></a>
					<a class="ofx-add__alt" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>" data-oria-event="offers_add_claim"><?php esc_html_e( 'Not claimed your listing yet? Claim it free', 'oria' ); ?></a>
				</p>
			</header>

			<?php if ( $oria_example ) : ?>
				<aside class="ofx-add__example" aria-labelledby="ofx-add-ex">
					<p class="ofx-add__exlabel" id="ofx-add-ex"><?php esc_html_e( 'How an offer looks on the offers page', 'oria' ); ?></p>
					<ul class="ofx-grid ofx-grid--one">
						<?php get_template_part( 'template-parts/offers/card', null, array( 'row' => $oria_example, 'place' => 'offers_add_example', 'eager' => true ) ); ?>
					</ul>
				</aside>
			<?php endif; ?>
		</div>

		<section aria-labelledby="ofx-add-steps">
			<h2 class="ofx-section__title" id="ofx-add-steps"><?php esc_html_e( 'Three steps', 'oria' ); ?></h2>
			<ol class="ofx-steps">
				<li>
					<h3><?php esc_html_e( 'Claim your listing', 'oria' ); ?></h3>
					<p><?php esc_html_e( 'Find your business on Oria Haven and confirm it’s yours. It’s free, and it lets you edit everything on your profile.', 'oria' ); ?></p>
				</li>
				<li>
					<h3><?php esc_html_e( 'Add the offer in My Oria', 'oria' ); ?></h3>
					<p><?php esc_html_e( 'Under Offers and social: the price, what’s included, who can buy it, the terms, an end date and a link to book.', 'oria' ); ?></p>
				</li>
				<li>
					<h3><?php esc_html_e( 'We check it, then it’s live', 'oria' ); ?></h3>
					<p><?php esc_html_e( 'We look at your first offer before it shows, usually within two working days. After that, your changes go live straight away.', 'oria' ); ?></p>
				</li>
			</ol>
		</section>

		<div class="ofx-guide__grid">
			<section class="ofx-guide__item" aria-labelledby="ofx-add-good">
				<h3 id="ofx-add-good"><?php esc_html_e( 'What makes an offer people choose', 'oria' ); ?></h3>
				<ul class="ofx-add__list">
					<li><?php esc_html_e( 'A clear upfront price, or the exact discount', 'oria' ); ?></li>
					<li><?php esc_html_e( 'What’s included, in two or three short lines', 'oria' ); ?></li>
					<li><?php esc_html_e( 'Who can buy it, such as new customers only', 'oria' ); ?></li>
					<li><?php esc_html_e( 'An end date, if it has one', 'oria' ); ?></li>
					<li><?php esc_html_e( 'A link straight to where they can buy or book it', 'oria' ); ?></li>
				</ul>
			</section>
			<section class="ofx-guide__item" aria-labelledby="ofx-add-rules">
				<h3 id="ofx-add-rules"><?php esc_html_e( 'What we can publish', 'oria' ); ?></h3>
				<ul class="ofx-add__list">
					<li><?php esc_html_e( 'A real offer you’re running now, at the price somebody pays at the desk', 'oria' ); ?></li>
					<li><?php esc_html_e( 'Conditions stated plainly, nothing hidden', 'oria' ); ?></li>
					<li><?php esc_html_e( 'No claims about treating health conditions', 'oria' ); ?></li>
					<li><?php esc_html_e( 'One offer per listing at a time', 'oria' ); ?></li>
				</ul>
			</section>
		</div>

		<div class="ofx-faq">
			<?php get_template_part( 'template-parts/faq', null, array( 'faqs' => $oria_faq, 'heading' => __( 'Questions from businesses', 'oria' ), 'id' => 'ofx-add-faq' ) ); ?>
		</div>
	</div>
</div>

<?php
get_footer();
