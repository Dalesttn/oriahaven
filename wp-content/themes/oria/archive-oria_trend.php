<?php
/**
 * /trends/ -- Wellness Trends Worth Understanding.
 *
 * Editorial and calm, not a social wall: no embeds here at all, only
 * Oria-owned pictures on the cards. The brief's order: the promise, one
 * featured trend, browse by how you want to feel (the site's own goal
 * chips, filtering the cards in place), the latest explanations, a way to
 * ask about something not covered yet (Ask Oria), and how we handle claims.
 *
 * "Popular in Perth" is deliberately absent: the brief allows it only on
 * real analytics or an editor's choice, and there is neither yet.
 *
 * @package Oria
 */

declare(strict_types=1);

use Oria\Core\Trends;

get_header();

$oria_all = array();
while ( have_posts() ) {
	the_post();
	$oria_all[] = get_post();
}
$oria_feat = null;
foreach ( $oria_all as $oria_t ) {
	if ( get_field( 'featured_trend', $oria_t->ID ) ) {
		$oria_feat = $oria_t;
		break;
	}
}
$oria_feat = $oria_feat ?: ( $oria_all[0] ?? null );
$oria_rest = array_values( array_filter( $oria_all, static fn( $t ) => ! $oria_feat || $t->ID !== $oria_feat->ID ) );

// Only goals some trend here actually carries: a chip that filters to
// nothing is a dead end.
$oria_used = array();
foreach ( $oria_all as $oria_t ) {
	foreach ( Trends\goals( $oria_t->ID ) as $oria_g ) {
		$oria_used[ $oria_g['slug'] ] = $oria_g;
	}
}
?>

<section class="wrap bohero trendhub">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Trends', 'oria' ); ?></span>
	</nav>
	<span class="bohero__eyebrow"><?php esc_html_e( 'Trends to Try', 'oria' ); ?></span>
	<h1 class="bohero__title"><?php echo esc_html( Trends\HUB_TITLE ); ?></h1>
	<p class="bohero__lede"><?php esc_html_e( 'Seen something interesting in your feed? Oria Haven explains popular wellness trends, what to expect and where you may be able to try them around Perth.', 'oria' ); ?></p>
	<?php if ( count( $oria_all ) > 3 ) : ?>
		<div class="trendhub__search">
			<label class="micro" for="trendq"><?php esc_html_e( 'What have you seen online?', 'oria' ); ?></label>
			<input type="search" id="trendq" class="trendhub__input" placeholder="<?php esc_attr_e( 'e.g. cold plunge, head spa', 'oria' ); ?>" autocomplete="off" data-trend-search>
		</div>
	<?php endif; ?>
</section>

<?php if ( ! $oria_all ) : ?>
	<section class="wrap bosection bosection--last">
		<p class="lede"><?php esc_html_e( 'The first trend explainers are being researched and checked. In the meantime, tell us what you have seen and we will look through every listing for you.', 'oria' ); ?></p>
		<p><a class="btn btn--dark" href="<?php echo esc_url( home_url( '/ask/' ) ); ?>"><?php esc_html_e( 'Ask Oria', 'oria' ); ?><span aria-hidden="true"><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></span></a></p>
	</section>
<?php else : ?>

	<?php if ( $oria_feat ) : ?>
		<section class="wrap bosection" aria-labelledby="trendfeat-h">
			<p class="micro" id="trendfeat-h"><?php esc_html_e( 'Trend to Try', 'oria' ); ?></p>
			<?php get_template_part( 'template-parts/trend-card', null, array( 'post' => $oria_feat, 'feature' => true, 'location' => 'hub_featured' ) ); ?>
		</section>
	<?php endif; ?>

	<?php if ( $oria_rest ) : ?>
		<section class="wrap bosection" aria-labelledby="trendlist-h">
			<div class="suphead">
				<div class="suphead__text">
					<h2 class="suphead__title" id="trendlist-h"><?php esc_html_e( 'Latest trend explanations', 'oria' ); ?></h2>
				</div>
			</div>
			<?php if ( count( $oria_used ) > 1 ) : ?>
				<div class="trendgoals" role="group" aria-label="<?php esc_attr_e( 'Browse by how you want to feel', 'oria' ); ?>">
					<span class="micro"><?php esc_html_e( 'How do you want to feel?', 'oria' ); ?></span>
					<?php foreach ( $oria_used as $oria_g ) : ?>
						<button type="button" class="quickf__chip" aria-pressed="false" data-trend-goal="<?php echo esc_attr( $oria_g['slug'] ); ?>" style="--gf:<?php echo esc_attr( $oria_g['color'] ); ?>"><?php echo esc_html( $oria_g['label'] ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="trendgrid" data-trend-grid>
				<?php foreach ( $oria_rest as $oria_t ) : ?>
					<?php get_template_part( 'template-parts/trend-card', null, array( 'post' => $oria_t, 'location' => 'hub' ) ); ?>
				<?php endforeach; ?>
			</div>
			<p class="trendgrid__none" data-trend-none hidden><?php esc_html_e( 'Nothing here matches yet.', 'oria' ); ?> <a href="<?php echo esc_url( home_url( '/ask/' ) ); ?>"><?php esc_html_e( 'Ask Oria instead', 'oria' ); ?> &rarr;</a></p>
		</section>
	<?php endif; ?>
<?php endif; ?>

<section class="wrap bosection">
	<aside class="askband" aria-labelledby="trendask-title">
		<?php get_template_part( 'template-parts/oria-orb', null, array( 'class' => 'askband__orb', 'uid' => 'trends' ) ); ?>
		<div class="askband__text">
			<p class="micro askband__eyebrow"><?php esc_html_e( 'Seen something in your feed?', 'oria' ); ?></p>
			<h2 class="askband__title" id="trendask-title"><?php esc_html_e( 'Not covered here yet?', 'oria' ); ?></h2>
			<p class="askband__lede"><?php esc_html_e( 'Describe the kind of experience you saw — where, how it looked, what it might cost — and we’ll look for real places around Perth.', 'oria' ); ?></p>
		</div>
		<div class="askband__act">
			<form class="askband__form" action="<?php echo esc_url( home_url( '/ask/' ) ); ?>" method="get">
				<label class="sr-only" for="trendask-q"><?php esc_html_e( 'Describe what you saw', 'oria' ); ?></label>
				<input class="askband__input" type="text" id="trendask-q" name="q" maxlength="400" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. a sauna with a cold plunge near the beach', 'oria' ); ?>">
				<button class="btn btn--dark askband__go" type="submit"><?php esc_html_e( 'Ask Oria', 'oria' ); ?><span aria-hidden="true"><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></span></button>
			</form>
		</div>
	</aside>
</section>

<section class="wrap bosection bosection--last">
	<div class="trendnote">
		<h2 class="trendnote__h"><?php esc_html_e( 'How we approach wellness claims', 'oria' ); ?></h2>
		<p><?php esc_html_e( 'A trend being popular is not evidence that it works. Each page separates what people enjoy, what practitioners say and what research shows, links its sources, and says plainly what is still uncertain. The evidence label on each trend is our editorial reading of those sources — not a certification — and nothing here is medical advice. Reels are shown through Instagram’s own embed, credited to their creators, who are not affiliated with Oria Haven.', 'oria' ); ?></p>
	</div>
</section>

<?php
get_footer();
