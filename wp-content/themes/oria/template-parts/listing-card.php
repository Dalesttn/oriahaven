<?php
/**
 * One listing card — the server-rendered twin of the card app.js builds.
 * Keep the markup in step with the card() function in assets/js/app.js.
 */

declare(strict_types=1);

$oria_id     = get_the_ID();
if ( ! $oria_id ) {
	// A null post in the loop (seen locally when a broken row enters a
	// query) must skip a card, not fatal the page under strict_types.
	return;
}
$oria_status = \Oria\Theme\display_status( $oria_id );

$oria_areas  = wp_get_post_terms( $oria_id, 'area' );
$oria_suburb = null;
$oria_region = null;
foreach ( $oria_areas as $oria_t ) {
	if ( $oria_t->parent ) {
		$oria_suburb = $oria_t;
		$oria_region = \Oria\Core\Taxonomies\region_for( $oria_t );
	} elseif ( ! $oria_region ) {
		$oria_region = $oria_t;
	}
}

$oria_rated      = \Oria\Theme\effective_rating( $oria_id );
$oria_price_from = get_field( 'price_from', $oria_id );
$oria_format     = (string) get_field( 'format', $oria_id );

/*
 * Featured and Claimed only.
 *
 * "Unclaimed" used to sit over the photograph on nearly every card — 307 of
 * 314 listings — and a label carried by 98% of a set tells a reader nothing.
 * It also read as a mark against the practice, when in most cases the
 * practice does not yet know the listing exists: unfair to them, and it made
 * the listing look thinner than it is.
 *
 * The transparency it stood for is kept, and kept where it does honest work.
 * The listing page still says the profile was built from public information
 * and invites the practice to take it over. On a profile that is disclosure.
 * On a card in a scan list it was noise.
 */
/*
 * One editorial badge at most, linked to the guide that awarded it. Read
 * from the Best Of guides, never stored here -- see BestOf\index(). Mirrors
 * the `best` branch in app.js card().
 */
$oria_best = function_exists( '\Oria\Core\BestOf\card_badge' ) ? \Oria\Core\BestOf\card_badge( $oria_id ) : null;

$oria_badges = array(
	'featured' => '<span class="badge badge--featured"><span class="badge-dot"></span>' . esc_html__( 'Featured', 'oria' ) . '</span>',
	'claimed'  => '<span class="badge badge--claimed"><span class="badge-dot"></span>' . esc_html__( 'Claimed', 'oria' ) . '</span>',
);
?>
<article class="listing<?php echo 'featured' === $oria_status ? ' listing--featured' : ''; ?>">
	<div class="listing__media">
		<?php // 4:3, stated so the browser reserves the frame before the photo lands. The CSS decides the real size; these only set the ratio. ?>
		<img src="<?php echo esc_url( \Oria\Theme\listing_image( $oria_id ) ); ?>" alt="<?php echo esc_attr( \Oria\Theme\listing_alt( $oria_id ) ); ?>" width="640" height="480" loading="lazy" decoding="async"
			onerror="this.onerror=null;this.src='<?php echo esc_js( \Oria\Theme\listing_scene( $oria_id ) ); ?>'">
		<?php if ( isset( $oria_badges[ $oria_status ] ) ) : ?>
			<div class="listing__flag"><?php echo $oria_badges[ $oria_status ]; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php endif; ?>
		<?php
		/*
		 * The heart, as on the JS-built card. Rendered unsaved and corrected
		 * by app.js on load, which knows the device's or the account's list.
		 */
		?>
		<div class="listing__quick">
			<button class="qact" type="button" data-card-save="<?php echo esc_attr( (string) get_post_field( 'post_name', $oria_id ) ); ?>" aria-pressed="false" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: practice name */ __( 'Save %s', 'oria' ), \Oria\Theme\ptitle( $oria_id ) ) ); ?>" title="<?php esc_attr_e( 'Save', 'oria' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 8.6a4.9 4.9 0 0 0-8.8-3A4.9 4.9 0 0 0 3.2 8.6c0 4.9 8.8 10.2 8.8 10.2s8.8-5.3 8.8-10.2Z"/></svg>
			</button>
		</div>
		<?php
		/*
		 * The strip along the foot of the picture: the award on the left,
		 * the rating on the right. One row rather than two things pinned to
		 * opposite corners -- pinned separately they overlapped by 23px on a
		 * phone, where a long award label and a rating carrying its review
		 * count together want more than the picture is wide. As a row they
		 * cannot: the award gives way and ellipses, the rating never shrinks,
		 * because the number is the thing being compared.
		 */
		?>
		<?php if ( $oria_best || $oria_rated['rating'] > 0 ) : ?>
		<div class="listing__onmedia">
			<?php if ( $oria_best ) : ?>
				<div class="listing__best"><?php echo \Oria\Core\BestOf\badge_html( $oria_best['label'], $oria_best['url'], '', (string) ( $oria_best['year'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<?php endif; ?>
			<?php if ( $oria_rated['rating'] > 0 ) : ?>
				<span class="rating rating--onmedia">
					<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>
					<?php echo esc_html( number_format_i18n( $oria_rated['rating'], 1 ) ); ?>
					<?php if ( $oria_rated['count'] > 0 ) : ?>
						<?php
						// Never an unattributed star: a rating is either ours or
						// Google's, and it always says which.
						$oria_rating_src = 'google' === $oria_rated['source']
							? __( 'Google', 'oria' )
							: __( 'Oria Haven', 'oria' );
						?>
						<span class="rating__count">(<?php echo esc_html( (string) $oria_rated['count'] ); ?> · <?php echo esc_html( $oria_rating_src ); ?>)</span>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<div class="listing__body">
		<div class="listing__head">
			<div>
				<h3 class="listing__name"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<p class="listing__where">
					<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M8 14.5s5-4.2 5-8a5 5 0 1 0-10 0c0 3.8 5 8 5 8Z"/><circle cx="8" cy="6.4" r="1.9"/></svg>
					<?php
					/*
					 * The suburb was plain text on every card on the site, which left
					 * suburb pages with almost nothing pointing at them. It is also the
					 * most natural link on a card: somebody reading "Kalamunda" often
					 * wants everything in Kalamunda. Safe to nest here — the card links
					 * from its title, not from its whole body.
					 */
					$oria_where = array();
					if ( $oria_suburb instanceof WP_Term ) {
						$oria_where[] = '<a href="' . esc_url( (string) get_term_link( $oria_suburb ) ) . '">' . esc_html( \Oria\Theme\tname( $oria_suburb ) ) . '</a>';
					}
					if ( $oria_region instanceof WP_Term ) {
						$oria_where[] = '<a href="' . esc_url( (string) get_term_link( $oria_region ) ) . '">' . esc_html( \Oria\Theme\tname( $oria_region ) ) . '</a>';
					}
					echo implode( ' &middot; ', $oria_where ); // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</p>
			</div>
		</div>

		<?php
		/*
		 * At most two tags, practical ones first -- beginner friendly,
		 * online, free, a live offer -- then the wellness-goal tags, then
		 * the category as the fallback. Mirrors cardTags() in app.js, which
		 * redraws every one of these cards on a directory page. The UX audit
		 * found up to six pills competing with the practice's own name.
		 */
		$oria_tags = array();
		$oria_auds = wp_get_post_terms( $oria_id, 'audience', array( 'fields' => 'slugs' ) );
		if ( ! is_wp_error( $oria_auds ) && in_array( 'beginners', $oria_auds, true ) ) {
			$oria_tags[] = '<span class="pill">' . esc_html__( 'Beginner friendly', 'oria' ) . '</span>';
		}
		if ( '' !== $oria_format && 'in-person' !== $oria_format ) {
			$oria_tags[] = '<span class="pill">' . esc_html__( 'Online available', 'oria' ) . '</span>';
		}
		if ( 'Free' === (string) get_field( 'price_band', $oria_id ) ) {
			$oria_tags[] = '<span class="pill">' . esc_html__( 'Free', 'oria' ) . '</span>';
		}
		if ( \Oria\Theme\active_offer( $oria_id ) ) {
			$oria_tags[] = '<span class="pill pill--offer">' . esc_html__( 'Special offer', 'oria' ) . '</span>';
		}
		$oria_gf = function_exists( '\Oria\Core\GoodFor\for_listing' ) ? \Oria\Core\GoodFor\for_listing( $oria_id ) : array();
		foreach ( $oria_gf as $oria_g ) {
			$oria_tags[] = '<span class="pill pill--gf" style="--gf:' . esc_attr( $oria_g['color'] ) . '">' . esc_html( $oria_g['label'] ) . '</span>';
		}
		if ( ! $oria_tags && function_exists( '\Oria\Core\Categories\top_for' ) ) {
			foreach ( \Oria\Core\Categories\top_for( $oria_id ) as $oria_cat ) {
				$oria_tags[] = '<a class="pill pill--cat pill--cat-' . esc_attr( $oria_cat['term']->slug ) . '" href="' . esc_url( (string) get_term_link( $oria_cat['term'] ) ) . '">' . esc_html( \Oria\Theme\tname( $oria_cat['term'] ) ) . '</a>';
			}
		}
		?>
		<?php if ( $oria_tags ) : ?>
			<div class="listing__tags"><?php echo implode( '', array_slice( $oria_tags, 0, 2 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above ?></div>
		<?php endif; ?>

		<p class="listing__desc"><?php echo esc_html( get_the_excerpt() ); ?></p>

		<div class="listing__foot">
			<span class="listing__price">
				<?php if ( (int) $oria_price_from > 0 ) : ?>
					$<?php echo esc_html( (string) (int) $oria_price_from ); ?> <span>/ <?php esc_html_e( 'session', 'oria' ); ?></span>
				<?php else : ?>
					<span class="listing__price--none"><?php esc_html_e( 'Price not published', 'oria' ); ?></span>
				<?php endif; ?>
				<?php $oria_next = (string) get_field( 'next_session', $oria_id ); ?>
				<?php if ( $oria_next ) : ?>
					<span class="listing__next"><?php printf( esc_html__( 'Next: %s', 'oria' ), esc_html( $oria_next ) ); ?></span>
				<?php endif; ?>
			</span>
			<?php
			/*
			 * The compare toggle. Drawn here AND in app.js card(), because that
			 * function re-renders every card the server already drew -- the same
			 * trap the status badge fell into. Pressed state lives in
			 * localStorage, not the DOM, so a selection survives filtering,
			 * infinite scroll and moving between categories.
			 *
			 * It is a button, not a link: without scripting there is nothing for
			 * it to do, so JS reveals it and no-JS visitors never see a dead
			 * control.
			 */
			?>
			<span class="listing__acts">
				<button class="cmpbtn" type="button" hidden
					data-compare-toggle
					data-slug="<?php echo esc_attr( get_post_field( 'post_name', $oria_id ) ); ?>"
					aria-pressed="false">
					<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M8 2v12M3 5h10M4.5 5 2.5 9.5h4zM11.5 5 9.5 9.5h4z"/>
					</svg>
					<span data-compare-word><?php esc_html_e( 'Compare', 'oria' ); ?></span>
				</button>
				<a class="btn btn--sm btn--dark" href="<?php the_permalink(); ?>"><?php esc_html_e( 'View profile', 'oria' ); ?><span class="btn__dot"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 11 3M5 3h6v6"/></svg></span></a>
			</span>
		</div>
	</div>
</article>
