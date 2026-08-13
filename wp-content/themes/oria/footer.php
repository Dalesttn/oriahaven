<?php
/**
 * Site footer — the deep-teal slab from the prototype, with the SEO link
 * cloud generated from real suburb terms instead of hardcoded anchors.
 */

declare(strict_types=1);

$oria_practices = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false ) );
$oria_suburbs   = function_exists( '\Oria\Core\Taxonomies\suburbs' ) ? \Oria\Core\Taxonomies\suburbs() : array();
?>
</main>

<footer class="foot">
	<div class="wrap foot__inner">
		<div class="foot__grid">
			<div>
				<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php echo \Oria\Theme\mark( 'small', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<b>Oria</b><i>&thinsp;Haven</i>
				</a>
				<p style="margin-top:1rem;font-size:.9375rem;max-width:30ch"><?php echo esc_html( \Oria\Theme\opt( 'footer_tagline', "Perth's meditation and wellness directory. Built in Fremantle, one listing at a time." ) ); ?></p>

				<?php
				/*
				 * Name, address and contact, in the same words as the
				 * Organization schema — they are drawn from one constant so
				 * they cannot drift, which is the entire point of a NAP.
				 * A service-area business, so the locality is the metro and
				 * there is no street line.
				 */
				$oria_nap = \Oria\Core\Schema\NAP;
				?>
				<address class="foot__nap">
					<span><?php echo esc_html( $oria_nap['locality'] . ', ' . $oria_nap['region'] ); ?></span>
					<?php if ( $oria_nap['phone'] ) : ?>
						<?php // Reads as a human wrote it, dials as E.164. ?>
						<a href="tel:<?php echo esc_attr( $oria_nap['phone_e164'] ); ?>"><?php echo esc_html( $oria_nap['phone'] ); ?></a>
					<?php endif; ?>
					<a href="mailto:<?php echo esc_attr( $oria_nap['email'] ); ?>"><?php echo esc_html( $oria_nap['email'] ); ?></a>
					<?php if ( $oria_nap['abn'] ) : ?>
						<span><?php printf( /* translators: %s: ABN */ esc_html__( 'ABN %s', 'oria' ), esc_html( $oria_nap['abn'] ) ); ?></span>
					<?php endif; ?>
				</address>

				<?php
				// The same list that feeds sameAs in the Organization schema,
				// so a new profile is added once and appears in both.
				$oria_profiles = \Oria\Core\Schema\profiles();
				?>
				<?php if ( $oria_profiles ) : ?>
					<div class="foot__social">
						<?php foreach ( $oria_profiles as $oria_profile ) : ?>
							<?php $oria_meta = \Oria\Theme\social_link( $oria_profile ); ?>
							<?php if ( $oria_meta ) : ?>
								<a href="<?php echo esc_url( $oria_profile ); ?>" rel="me noopener" target="_blank" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: network name */ __( 'Oria Haven on %s', 'oria' ), $oria_meta['label'] ) ); ?>">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $oria_meta['icon']; // phpcs:ignore WordPress.Security.EscapeOutput ?></svg>
								</a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div>
				<h4><?php esc_html_e( 'Practices', 'oria' ); ?></h4>
				<ul class="foot__list">
					<?php if ( ! is_wp_error( $oria_practices ) ) : ?>
						<?php foreach ( $oria_practices as $oria_term ) : ?>
							<li><a href="<?php echo esc_url( (string) get_term_link( $oria_term ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_term ) ); ?></a></li>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>
			</div>

			<div>
				<h4><?php esc_html_e( 'Explore', 'oria' ); ?></h4>
				<ul class="foot__list">
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Full directory', 'oria' ); ?></a></li>
					<?php // Sitewide, so every practice, modality and suburb page sits two clicks from anywhere on the site. ?>
					<li><a href="<?php echo esc_url( home_url( '/perth/' ) ); ?>"><?php esc_html_e( 'Browse all of Perth', 'oria' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/wellness-finder/' ) ); ?>"><?php esc_html_e( 'Wellness Finder', 'oria' ); ?></a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ); ?>"><?php esc_html_e( 'Workshops/Events', 'oria' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/this-weekend/' ) ); ?>"><?php esc_html_e( 'This weekend', 'oria' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'The Journal', 'oria' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Shop wellness products', 'oria' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About us', 'oria' ); ?></a></li>
				</ul>
			</div>

			<div>
				<h4><?php esc_html_e( 'Practitioners', 'oria' ); ?></h4>
				<ul class="foot__list">
					<li><a href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'Claim your listing', 'oria' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/claim/#pricing' ) ); ?>"><?php esc_html_e( 'Featured listings', 'oria' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/about/#contact' ) ); ?>"><?php esc_html_e( 'Remove a listing', 'oria' ); ?></a></li>
				</ul>
			</div>
		</div>

		<?php if ( $oria_suburbs ) : ?>
		<div style="margin-top:3rem">
			<h4><?php esc_html_e( 'Popular suburbs', 'oria' ); ?></h4>
			<div class="linkcloud">
				<?php foreach ( array_slice( $oria_suburbs, 0, 12 ) as $oria_suburb ) : ?>
					<a href="<?php echo esc_url( (string) get_term_link( $oria_suburb ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_suburb ) ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="foot__bottom">
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Oria Haven. Perth, Western Australia. <?php echo esc_html( \Oria\Theme\opt( 'acknowledgement', 'We acknowledge the Whadjuk Noongar people as the traditional custodians of the land this site is made on.' ) ); ?></span>
			<?php
			/*
			 * Legal links appear once the pages exist, so the footer can
			 * never advertise a 404 — the drafts are in /legal/ waiting to
			 * be pasted in, and these light up by themselves when they are.
			 */
			$oria_legal = array(
				'privacy-policy' => __( 'Privacy', 'oria' ),
				'terms'          => __( 'Terms', 'oria' ),
			);
			$oria_links = array();
			foreach ( $oria_legal as $oria_slug => $oria_label ) {
				$oria_page = get_page_by_path( $oria_slug );
				if ( $oria_page instanceof WP_Post && 'publish' === $oria_page->post_status ) {
					$oria_links[] = '<a href="' . esc_url( (string) get_permalink( $oria_page ) ) . '">' . esc_html( $oria_label ) . '</a>';
				}
			}
			?>
			<?php if ( $oria_links ) : ?>
				<span class="foot__legal"><?php echo implode( ' · ', $oria_links ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<?php endif; ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
