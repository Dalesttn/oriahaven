<?php
/**
 * One listing card — the server-rendered twin of the card app.js builds.
 * Keep the markup in step with the card() function in assets/js/app.js.
 */

declare(strict_types=1);

$oria_id     = get_the_ID();
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

$oria_practice   = wp_get_post_terms( $oria_id, 'practice' )[0] ?? null;
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
$oria_badges = array(
	'featured' => '<span class="badge badge--featured"><span class="badge-dot"></span>' . esc_html__( 'Featured', 'oria' ) . '</span>',
	'claimed'  => '<span class="badge badge--claimed"><span class="badge-dot"></span>' . esc_html__( 'Claimed', 'oria' ) . '</span>',
);
?>
<article class="listing<?php echo 'featured' === $oria_status ? ' listing--featured' : ''; ?>">
	<div class="listing__media">
		<img src="<?php echo esc_url( \Oria\Theme\listing_image( $oria_id ) ); ?>" alt="<?php echo esc_attr( \Oria\Theme\listing_alt( $oria_id ) ); ?>" loading="lazy"
			onerror="this.onerror=null;this.src='<?php echo esc_js( \Oria\Theme\listing_scene( $oria_id ) ); ?>'">
		<?php if ( isset( $oria_badges[ $oria_status ] ) ) : ?>
			<div class="listing__flag"><?php echo $oria_badges[ $oria_status ]; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php endif; ?>
	</div>
	<div class="listing__body">
		<?php
		/*
		 * Category first. It answers "what kind of place is this" before the
		 * name does, which is what somebody scanning a long list is actually
		 * asking. Attribute pills — online, offer, next session — stay under
		 * the description: they say what is available rather than what this
		 * is, and moving all of them up here made a row nobody could scan.
		 *
		 * top_for() walks to the top level rather than printing whichever
		 * practice term came back first. That mattered the moment categories
		 * gained parents: a meditation studio would have shown "Meditation
		 * classes" where the sidebar and the URL both say "Mind & Mental
		 * Wellbeing".
		 */
		$oria_cats = function_exists( '\Oria\Core\Categories\top_for' )
			? \Oria\Core\Categories\top_for( $oria_id )
			: array();
		?>
		<?php if ( $oria_cats ) : ?>
			<div class="listing__cats">
				<?php foreach ( $oria_cats as $oria_cat ) : ?>
					<a class="pill pill--cat pill--cat-<?php echo esc_attr( $oria_cat['term']->slug ); ?>" href="<?php echo esc_url( (string) get_term_link( $oria_cat['term'] ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_cat['term'] ) ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
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
			<?php if ( $oria_rated['rating'] > 0 ) : ?>
			<span class="rating">
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

		<p class="listing__desc"><?php echo esc_html( get_the_excerpt() ); ?></p>

		<div class="listing__tags">
			<?php if ( 'in-person' !== $oria_format ) : ?>
				<span class="pill"><?php esc_html_e( 'Online available', 'oria' ); ?></span>
			<?php endif; ?>
			<?php if ( \Oria\Theme\active_offer( $oria_id ) ) : ?>
				<span class="pill" style="background:var(--gold-soft);border-color:transparent;color:#7A5A12;font-weight:700"><?php esc_html_e( 'Special offer', 'oria' ); ?></span>
			<?php endif; ?>
			<?php $oria_next = (string) get_field( 'next_session', $oria_id ); ?>
			<?php if ( $oria_next ) : ?>
				<span class="pill"><?php printf( esc_html__( 'Next: %s', 'oria' ), esc_html( $oria_next ) ); ?></span>
			<?php endif; ?>
		</div>

		<div class="listing__foot">
			<span class="listing__price">
				<?php if ( (int) $oria_price_from > 0 ) : ?>
					$<?php echo esc_html( (string) (int) $oria_price_from ); ?> <span>/ <?php esc_html_e( 'session', 'oria' ); ?></span>
				<?php else : ?>
					&nbsp;
				<?php endif; ?>
			</span>
			<a class="btn btn--sm btn--dark" href="<?php the_permalink(); ?>"><?php esc_html_e( 'View profile', 'oria' ); ?><span class="btn__dot"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 11 3M5 3h6v6"/></svg></span></a>
		</div>
	</div>
</article>
