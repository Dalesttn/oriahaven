<?php
/**
 * A listing's "Sessions & prices" section: the visit the venue sells, what
 * it includes and how to check a session, then the other ways in.
 *
 * One section however many facility rows the listing has: rows selling the
 * same visit are merged by FacilityAccess\profile(), so a bathhouse
 * researched under both steam room and sauna no longer prints its session
 * twice. Every value is a saved, sourced fact from the listing's Facility
 * access box; an unknown is left out, never guessed. Styles: .osp in
 * oria-v4/assets/css/v4-listing.css (design brief, October 2026).
 *
 * @var array $args { id: int, sec: int }
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\FacilityAccess as FA;

$oria_id  = (int) ( $args['id'] ?? 0 );
$oria_sec = (int) ( $args['sec'] ?? 0 );
$oria_p   = FA\profile( $oria_id );
if ( ! $oria_p['mains'] ) {
	return;
}

$oria_tick = '<svg class="osp__tick" viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3.5 8.5l3 3 6-7"/></svg>';

/*
 * Where the button goes, in words. A booking platform is the venue's booking
 * site; the venue's own domain is its website.
 */
$oria_dest = static function ( string $url ): string {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	return preg_match( '/(fresha|mindbody|momence|cliniko|timely|gettimely|setmore|acuity|as\.me|bookwhen|vagaro|square|zenoti|phorest|kitomba|halaxy|glofox|marianatek)/i', $host )
		? __( 'Opens the venue’s booking site', 'oria' )
		: __( 'Opens the venue’s website', 'oria' );
};

$oria_cards = static function ( array $cards, string $placement ): void {
	?>
	<ul class="osp__alts" role="list">
		<?php foreach ( $cards as $oria_c ) : ?>
			<li class="osp__alt">
				<?php if ( '' !== $oria_c['kind'] ) : ?>
					<p class="osp__eyebrow"><?php echo esc_html( $oria_c['kind'] ); ?></p>
				<?php endif; ?>
				<h4 class="osp__alt-title"><?php echo esc_html( $oria_c['title'] ); ?></h4>
				<p class="osp__alt-price">
					<?php if ( '' !== $oria_c['price'] ) : ?>
						<span class="osp__alt-amount"><?php echo esc_html( $oria_c['price'] ); ?></span>
						<?php if ( '' !== $oria_c['basis'] ) : ?>
							<span class="osp__alt-basis"><?php echo esc_html( $oria_c['basis'] ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span class="osp__alt-unknown"><?php echo esc_html( $oria_c['price_alt'] ); ?></span>
					<?php endif; ?>
				</p>
				<?php if ( $oria_c['facts'] ) : ?>
					<p class="osp__alt-facts"><?php echo esc_html( implode( ' · ', $oria_c['facts'] ) ); ?></p>
				<?php endif; ?>
				<?php if ( $oria_c['swimwear'] ) : ?>
					<p class="osp__alt-note"><?php esc_html_e( 'Swimwear required', 'oria' ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $oria_c['line'] ) : ?>
					<p class="osp__alt-line"><?php echo esc_html( $oria_c['line'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $oria_c['book_url'] ) : ?>
					<a class="osp__alt-link" href="<?php echo esc_url( $oria_c['book_url'] ); ?>" rel="nofollow noopener" target="_blank" data-oria-event="facility_check_sessions_click" data-oria-placement="<?php echo esc_attr( $placement ); ?>">
						<?php echo 'listing_sessions_plans' === $placement ? esc_html__( 'Check on the venue’s site', 'oria' ) : esc_html__( 'Check venue sessions', 'oria' ); ?><span aria-hidden="true"> →</span><span class="sr-only"> <?php esc_html_e( '(opens in a new tab)', 'oria' ); ?></span>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
};
?>
<section class="xp-sec osp oria-session-pricing" id="<?php echo esc_attr( $oria_p['anchor'] ); ?>" aria-labelledby="xp-fa-<?php echo esc_attr( (string) $oria_sec ); ?>">
	<header class="osp__intro">
		<p class="osp__kicker"><?php esc_html_e( 'Plan your visit', 'oria' ); ?></p>
		<h2 class="h2 xp-sec__title osp__heading" id="xp-fa-<?php echo esc_attr( (string) $oria_sec ); ?>"><?php esc_html_e( 'Sessions & prices', 'oria' ); ?></h2>
		<p class="osp__lede"><?php esc_html_e( 'See what’s included and choose the visit that suits you. Prices are as the venue publishes them; confirm before you go.', 'oria' ); ?></p>
	</header>

	<?php foreach ( $oria_p['mains'] as $oria_i => $oria_m ) : ?>
		<article class="osp__main"<?php echo $oria_i > 0 ? ' id="' . esc_attr( 'facility-' . $oria_m['facility'] ) . '"' : ''; ?>>
			<div class="osp__details">
				<?php if ( '' !== $oria_m['notice'] ) : ?>
					<p class="osp__notice" role="note"><?php echo esc_html( $oria_m['notice'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $oria_m['label'] ) : ?>
					<p class="osp__label"><?php echo esc_html( $oria_m['label'] ); ?></p>
				<?php endif; ?>
				<h3 class="osp__title"><?php echo esc_html( $oria_m['title'] ); ?></h3>
				<?php if ( $oria_m['meta'] ) : ?>
					<ul class="osp__meta" role="list">
						<?php foreach ( $oria_m['meta'] as $oria_x ) : ?>
							<li><?php echo esc_html( $oria_x ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $oria_m['facts'] ) : ?>
					<p class="osp__facts"><?php echo esc_html( implode( ' · ', $oria_m['facts'] ) ); ?></p>
				<?php endif; ?>

				<?php if ( $oria_m['includes'] ) : ?>
					<div class="osp__group">
						<h4 class="osp__group-title"><?php esc_html_e( 'Included in your visit', 'oria' ); ?></h4>
						<ul class="osp__incl" role="list">
							<?php foreach ( $oria_m['includes'] as $oria_x ) : ?>
								<li><?php echo $oria_tick; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?><span><?php echo esc_html( $oria_x ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( $oria_m['notes'] || '' !== $oria_m['quiet'] ) : ?>
					<div class="osp__notes">
						<?php foreach ( $oria_m['notes'] as $oria_x ) : ?>
							<p><?php echo esc_html( $oria_x ); ?></p>
						<?php endforeach; ?>
						<?php if ( '' !== $oria_m['quiet'] ) : ?>
							<p><?php echo esc_html( $oria_m['quiet'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $oria_m['essentials'] ) : ?>
					<div class="osp__group">
						<h4 class="osp__group-title"><?php esc_html_e( 'Good to know', 'oria' ); ?></h4>
						<ul class="osp__know" role="list">
							<?php foreach ( $oria_m['essentials'] as $oria_x ) : ?>
								<li><?php echo esc_html( $oria_x ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>

			<div class="osp__panel">
				<?php if ( '' !== $oria_m['price'] ) : ?>
					<p class="osp__price"><?php echo esc_html( $oria_m['price'] ); ?></p>
					<?php if ( '' !== $oria_m['basis'] ) : ?>
						<p class="osp__basis"><?php echo esc_html( $oria_m['basis'] ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p class="osp__price osp__price--text"><?php echo esc_html( $oria_m['price_alt'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $oria_m['book_url'] ) : ?>
					<a class="osp__cta" href="<?php echo esc_url( $oria_m['book_url'] ); ?>" rel="nofollow noopener" target="_blank" data-oria-event="facility_check_sessions_click" data-oria-placement="listing_sessions_main">
						<?php esc_html_e( 'Check sessions & prices', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(opens in a new tab)', 'oria' ); ?></span>
					</a>
					<p class="osp__dest"><?php echo esc_html( $oria_dest( $oria_m['book_url'] ) ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( '' !== $oria_m['checked'] || '' !== $oria_m['source'] ) : ?>
				<div class="osp__source"><p class="osp__source-row">
					<?php if ( '' !== $oria_m['checked'] ) : ?>
						<span>
							<?php
							/* translators: %s: date */
							printf( esc_html__( 'Price checked %s', 'oria' ), esc_html( $oria_m['checked'] ) );
							?>
						</span>
					<?php endif; ?>
					<?php if ( '' !== $oria_m['source'] ) : ?>
						<a href="<?php echo esc_url( $oria_m['source'] ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'View source', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(opens in a new tab)', 'oria' ); ?></span></a>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>

	<?php if ( $oria_p['visits'] ) : ?>
		<div class="osp__more">
			<h3 class="osp__more-title"><?php esc_html_e( 'Other ways to visit', 'oria' ); ?></h3>
			<?php $oria_cards( $oria_p['visits'], 'listing_sessions_other' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $oria_p['plans'] ) : ?>
		<div class="osp__more">
			<h3 class="osp__more-title"><?php esc_html_e( 'Packs & memberships', 'oria' ); ?></h3>
			<?php $oria_cards( $oria_p['plans'], 'listing_sessions_plans' ); ?>
		</div>
	<?php endif; ?>
</section>
