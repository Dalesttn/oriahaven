<?php
/**
 * A listing profile — full parity with the designed page: gallery, rating,
 * key facts, about, services, timetable, reviews, getting there, and the
 * contact / hours / claim / nearby rail.
 */

declare(strict_types=1);

use function Oria\Theme\listing_image;
use function Oria\Theme\arrow;

get_header();

$oria_star = '<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>';
$oria_pin  = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M8 14.5s5-4.2 5-8a5 5 0 1 0-10 0c0 3.8 5 8 5 8Z"/><circle cx="8" cy="6.4" r="1.9"/></svg>';

while ( have_posts() ) :
	the_post();

	$oria_id     = get_the_ID();
	$oria_status = \Oria\Theme\claim_status( $oria_id );

	$oria_areas   = wp_get_post_terms( $oria_id, 'area' );
	$oria_suburb  = null;
	$oria_region  = null;
	foreach ( $oria_areas as $oria_t ) {
		if ( $oria_t->parent ) {
			$oria_suburb = $oria_t;
			$oria_region = \Oria\Core\Taxonomies\region_for( $oria_t );
		} elseif ( ! $oria_region ) {
			$oria_region = $oria_t;
		}
	}
	// Ranked by the category plan, not by the alphabet — see
	// Oria\Core\Categories\primary_for(). The breadcrumb and the pills on
	// this listing's own card now name the same category.
	$oria_practice = function_exists( '\Oria\Core\Categories\primary_for' )
		? \Oria\Core\Categories\primary_for( $oria_id )
		: ( wp_get_post_terms( $oria_id, 'practice' )[0] ?? null );

	$oria_address    = (string) get_field( 'address', $oria_id );
	$oria_phone      = (string) get_field( 'phone', $oria_id );
	$oria_email      = (string) get_field( 'email', $oria_id );
	// Tagged once here, so every button and row below sends the same
	// utm_source=oriahaven -- see Theme\outbound().
	$oria_slugname   = (string) get_post_field( 'post_name', $oria_id );
	$oria_website    = \Oria\Theme\outbound( (string) get_field( 'website', $oria_id ), $oria_slugname );
	$oria_booking    = \Oria\Theme\outbound( (string) get_field( 'booking_url', $oria_id ), $oria_slugname );
	$oria_rating     = (float) get_field( 'rating', $oria_id );
	$oria_rcount     = (int) get_field( 'review_count', $oria_id );
	// rows() rather than an (array) cast: ACF returns false for an empty
	// repeater, and (array) false is [false] — a truthy one-element array
	// that renders a heading with a blank row under it.
	$oria_services   = \Oria\Theme\rows( 'services', array(), $oria_id );
	/*
	 * Classes and packages publish only while the listing is on a paid
	 * plan. A listing that lapses keeps everything it typed and simply
	 * stops showing it -- deleting somebody's class list because a card
	 * expired would be the wrong way round.
	 */
	$oria_paid       = function_exists( '\Oria\Core\Ownership\is_paid' )
		? \Oria\Core\Ownership\is_paid( $oria_id )
		: false;
	$oria_packages   = $oria_paid ? \Oria\Theme\rows( 'packages', array(), $oria_id ) : array();
	$oria_verified   = (string) get_field( 'verified_at', $oria_id );
	/* practice or place -- see \Oria\Theme\words(). */
	$oria_words      = \Oria\Theme\words( $oria_id );
	/*
	 * A beach has no phone, no inbox and no owner. Rewording the contact
	 * and claim blocks was not enough -- an empty "Getting there" card and
	 * a claim form asking who you are are both worse than absent. The
	 * blocks come out entirely.
	 */
	$oria_contactless = '' !== (string) ( $oria_words['contactless'] ?? '' );
	$oria_price_from = get_field( 'price_from', $oria_id );
	$oria_format     = (string) ( get_field( 'format', $oria_id ) ?: 'in-person' );
	$oria_next       = (string) get_field( 'next_session', $oria_id );
	$oria_good_for   = (string) get_field( 'good_for', $oria_id );
	$oria_hours      = \Oria\Theme\rows( 'opening_hours', array(), $oria_id );
	$oria_transit    = (string) get_field( 'transit', $oria_id );
	$oria_parking    = (string) get_field( 'parking', $oria_id );
	$oria_reviews    = \Oria\Core\Places\reviews_for( $oria_id );

	$oria_format_label = 'both' === $oria_format
		? __( 'In person & online', 'oria' )
		: ( 'online' === $oria_format ? __( 'Online', 'oria' ) : __( 'In person', 'oria' ) );

	// Gallery precedence: the listing's own photos, else its Google Places
	// photos (with attribution), else the art-directed placeholder scene.
	$oria_places_attr = array();
	$oria_gallery     = array_values( array_filter( array_map(
		static fn( $gid ) => wp_get_attachment_image_url( (int) $gid, 'oria-wide' ),
		\Oria\Theme\rows( 'gallery', array(), $oria_id )
	) ) );
	if ( ! $oria_gallery && has_post_thumbnail( $oria_id ) ) {
		$oria_gallery = array( (string) get_the_post_thumbnail_url( $oria_id, 'oria-wide' ) );
	}
	if ( ! $oria_gallery ) {
		$oria_places = \Oria\Core\Places\photos_for( $oria_id );
		if ( $oria_places['urls'] ) {
			$oria_gallery     = $oria_places['urls'];
			$oria_places_attr = $oria_places['attributions'];
		}
	}
	if ( ! $oria_gallery ) {
		$oria_gallery = array( listing_image( $oria_id, 'oria-wide' ) );
	}

	/*
	 * Google place photos arrive with a size suffix the CDN honours --
	 * the live pages were pulling s4800-class originals for a 640px slot.
	 * Rewriting the suffix costs nothing and only ever touches Google's
	 * own URLs; uploaded images pass through untouched.
	 */
	$oria_gsz = static function ( string $oria_gu, int $oria_gw ): string {
		return false !== strpos( $oria_gu, 'googleusercontent.com' )
			? (string) preg_replace( '/=[a-z0-9-]+$/i', '=w' . $oria_gw, $oria_gu )
			: $oria_gu;
	};

	// LocalBusiness schema is emitted once by Oria\Core\Schema\listing_schema(),
	// which carries the @id, price band, website and rating this used to duplicate.
	?>
	<section class="wrap" style="padding-top:1.75rem">
		<?php
		// Where this listing actually is. current() reads it off the post's
		// own area terms, so a southern profile no longer sits under Perth.
		$oria_lcity = function_exists( '\Oria\Core\Cities\current' ) ? \Oria\Core\Cities\current() : null;
		$oria_lname = $oria_lcity ? \Oria\Core\Cities\name( $oria_lcity ) : '';
		?>
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Explore', 'oria' ); ?></a>
			<?php if ( '' !== $oria_lname && function_exists( '\Oria\Core\Explore\base_url' ) ) : ?>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( \Oria\Core\Explore\base_url( $oria_lcity ) ); ?>"><?php echo esc_html( $oria_lname ); ?></a>
			<?php endif; ?>
			<?php if ( $oria_practice ) : ?>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( (string) get_term_link( $oria_practice ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_practice ) ); ?></a>
			<?php endif; ?>
			<?php if ( $oria_suburb ) : ?>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( (string) get_term_link( $oria_suburb ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_suburb ) ); ?></a>
			<?php endif; ?>
			<span aria-hidden="true">/</span><span><?php the_title(); ?></span>
		</nav>
	</section>

	<!-- Gallery -->
	<section class="wrap" style="padding-top:1.5rem">
		<?php
		// Places photo URIs are short-lived by Google's definition; if one
		// expires inside our cache window, fall back to the scene rather
		// than showing a broken image.
		$oria_fb = 'this.onerror=null;this.src=\'' . esc_js( \Oria\Theme\listing_scene( $oria_id ) ) . '\'';
		?>
		<?php if ( count( $oria_gallery ) >= 3 ) : ?>
			<div class="gallery" data-lightbox>
				<div class="gallery__mainwrap">
					<img class="gallery__main" src="<?php echo esc_url( $oria_gsz( $oria_gallery[0], 1200 ) ); ?>"
					srcset="<?php echo esc_attr( $oria_gsz( $oria_gallery[0], 800 ) . ' 800w, ' . $oria_gsz( $oria_gallery[0], 1600 ) . ' 1600w' ); ?>"
					sizes="(max-width: 50rem) 100vw, 66vw"
					alt="<?php echo esc_attr( \Oria\Theme\ptitle() ); ?>" data-lb="0" onerror="<?php echo esc_attr( $oria_fb ); ?>">
				</div>
				<div class="gallery__side">
					<img src="<?php echo esc_url( $oria_gsz( $oria_gallery[1], 800 ) ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: practice name */ __( '%s, second photo', 'oria' ), \Oria\Theme\ptitle() ) ); ?>" data-lb="1" onerror="<?php echo esc_attr( $oria_fb ); ?>">
					<img src="<?php echo esc_url( $oria_gsz( $oria_gallery[2], 800 ) ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: practice name */ __( '%s, third photo', 'oria' ), \Oria\Theme\ptitle() ) ); ?>" data-lb="2" onerror="<?php echo esc_attr( $oria_fb ); ?>">
				</div>
				<?php
				// Every photo, not just the three shown -- the lightbox pages
				// through the lot, which is where the rest finally go.
				$oria_lb = array_map( static fn( string $oria_gu ): string => $oria_gsz( $oria_gu, 1600 ), $oria_gallery );
				?>
				<script type="application/json" data-lightbox-set><?php echo wp_json_encode( array_values( $oria_lb ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
			</div>
		<?php else : ?>
			<div style="border-radius:var(--r-lg);overflow:hidden">
				<img src="<?php echo esc_url( $oria_gallery[0] ); ?>" alt="<?php echo esc_attr( \Oria\Theme\ptitle() ); ?>" style="width:100%;aspect-ratio:21/9;object-fit:cover" onerror="<?php echo esc_attr( $oria_fb ); ?>">
			</div>
		<?php endif; ?>
		<?php if ( $oria_places_attr ) : // Google's terms require crediting photo contributors. ?>
			<p class="hint" style="margin-top:.5rem">
				<?php esc_html_e( 'Photos via Google', 'oria' ); ?> —
				<?php
				$oria_links = array();
				foreach ( $oria_places_attr as $oria_a ) {
					$oria_links[] = $oria_a['uri']
						? '<a href="' . esc_url( $oria_a['uri'] ) . '" rel="nofollow noopener" target="_blank">' . esc_html( $oria_a['name'] ) . '</a>'
						: esc_html( $oria_a['name'] );
				}
				echo implode( ', ', $oria_links ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</p>
		<?php endif; ?>
	</section>

	<section class="wrap section section--tight">
		<div class="profile">
			<div class="stack-lg">

				<!-- Title, badges, rating -->
				<div>
					<div class="profile__title">
						<div>
							<?php $oria_display = \Oria\Theme\display_status( $oria_id ); ?>
							<div class="row" style="margin-bottom:.85rem;gap:.6rem">
								<?php if ( 'featured' === $oria_display ) : ?>
									<span class="badge badge--featured"><span class="badge-dot"></span><?php esc_html_e( 'Featured', 'oria' ); ?></span>
								<?php elseif ( 'claimed' === $oria_display && 'unclaimed' === $oria_status ) : ?>
									<span class="badge badge--claimed"><span class="badge-dot"></span><?php esc_html_e( 'Claimed', 'oria' ); ?></span>
								<?php elseif ( 'unclaimed' === $oria_display ) : ?>
									<span class="badge badge--unclaimed"><?php esc_html_e( 'Unclaimed', 'oria' ); ?></span>
								<?php endif; ?>
								<?php if ( 'unclaimed' !== $oria_status ) : ?>
									<span class="verified" title="<?php echo esc_attr( $oria_verified ? sprintf( __( 'Details verified by the owner on %s', 'oria' ), mysql2date( 'j F Y', $oria_verified ) ) : __( 'Details verified by the owner', 'oria' ) ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path class="verified__seal" d="M12 1.7l2.4 1.8 2.9-.5 1.1 2.8 2.8 1.1-.5 2.9L22.5 12l-1.8 2.4.5 2.9-2.8 1.1-1.1 2.8-2.9-.5L12 22.3l-2.4-1.8-2.9.5-1.1-2.8-2.8-1.1.5-2.9L1.5 12l1.8-2.4-.5-2.9 2.8-1.1 1.1-2.8 2.9.5z"/><path class="verified__tick" d="M8.3 12.2l2.4 2.4 4.9-5"/></svg><?php esc_html_e( 'Verified', 'oria' ); ?></span>
								<?php endif; ?>
							</div>
							<h1 class="h1"><?php the_title(); ?></h1>

							<?php
							/*
							 * The rating sits directly under the name -- it is the
							 * strongest trust signal on the page and it used to
							 * arrive after the ask, below the buttons.
							 */
							?>
						<?php
						/*
						 * Ratings, in order of preference: reviews collected
						 * here; else the listing's Google rating, labelled as
						 * Google's and linked to the source — never presented
						 * as our own.
						 */
						if ( $oria_rating > 0 ) :
							?>
						<div class="profile__title__rating">
							<span class="rating">
								<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>
								<?php echo esc_html( number_format_i18n( $oria_rating, 1 ) ); ?>
							</span>
							<?php if ( $oria_rcount > 0 ) : ?>
								<span class="profile__title__sub"><?php printf( esc_html( _n( '%d Oria Haven review', '%d Oria Haven reviews', $oria_rcount, 'oria' ) ), (int) $oria_rcount ); ?></span>
							<?php endif; ?>
						</div>
						<?php
						else :
							$oria_g = \Oria\Core\Places\rating_for( $oria_id );
							if ( $oria_g['rating'] > 0 ) :
								?>
						<div class="profile__title__rating">
							<a class="rating" href="<?php echo esc_url( $oria_g['uri'] ?: 'https://www.google.com/maps' ); ?>" rel="nofollow noopener" target="_blank">
								<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>
								<?php echo esc_html( number_format_i18n( $oria_g['rating'], 1 ) ); ?>
							</a>
							<span class="profile__title__sub">
								<?php
								if ( $oria_g['count'] > 0 ) {
									/* translators: %d: number of Google reviews */
									printf( esc_html__( '%d Google reviews', 'oria' ), (int) $oria_g['count'] );
								} else {
									esc_html_e( 'Rating on Google', 'oria' );
								}
								?>
							</span>
						</div>
							<?php endif; ?>
						<?php endif; ?>

						<?php
						/*
						 * Where it is, directly under the name and rating.
						 *
						 * "Is it near me" gets asked at the same moment as "is it any
						 * good", so the answer belongs beside the rating rather than at
						 * the foot of the experience panel, where it was reading as a
						 * footnote to the scores.
						 *
						 * Still a one-line locator, not a location block: the address,
						 * the directions link, public transport and parking all have a
						 * card of their own further down, and two of them would be one
						 * too many.
						 */
						?>
						<?php if ( $oria_address || $oria_region ) : ?>
							<p class="listing__where profile__where">
								<?php echo $oria_pin; // phpcs:ignore ?>
								<?php echo esc_html( trim( $oria_address . ( $oria_region ? ' · ' . \Oria\Theme\tname( $oria_region ) : '' ), ' ·' ) ); ?>
							</p>
						<?php endif; ?>

							<?php
							/*
							 * The experience profile.
							 *
							 * One panel answering, in order: what is this good for, what
							 * does it cost and where is it, what does it FEEL like, how
							 * does it score, what can you actually do here, and why it
							 * might suit you. The order is the point -- the feel comes
							 * before the scores, because a reader decides on the sentence
							 * and only then checks the bars.
							 *
							 * Nothing here is stored per listing and nothing is claimed on
							 * the practice's behalf. Every block reads fields the listing
							 * already carries, and any block whose field is empty does not
							 * render at all rather than showing a blank slot.
							 */

							// Good for: derived from this practice's own services and
							// specialties exactly as the directory cards derive them.
							$oria_wants = function_exists( '\Oria\Core\GoodFor\for_listing' ) ? \Oria\Core\GoodFor\for_listing( $oria_id ) : array();

							/*
							 * What to expect, split in two. Price and distance are the two
							 * facts a reader checks before anything else, so they lead as a
							 * plain metadata line; the rest stay chips. Same source, same
							 * data, read in the order people actually read it.
							 */
							$oria_expect = function_exists( '\Oria\Theme\expect_chips' ) ? \Oria\Theme\expect_chips( $oria_id ) : array();
							$oria_meta   = array();
							$oria_rest   = array();
							foreach ( $oria_expect as $oria_e ) {
								if ( in_array( $oria_e['kind'], array( 'price', 'where' ), true ) ) {
									$oria_meta[] = $oria_e;
								} else {
									$oria_rest[] = $oria_e;
								}
							}

							// Assembled from stored fields, never written.
							$oria_likely = function_exists( '\Oria\Theme\likely_line' ) ? \Oria\Theme\likely_line( $oria_id ) : '';

							/*
							 * Experience DNA. Six bars read from the Compare registry's
							 * score for this KIND of session, narrowed by the listing's own
							 * price band, group size and beginners tag. Renders nothing for
							 * a listing whose kind is not in the registry -- a consultation
							 * is not a session -- rather than a guess. See includes/dna.php
							 * for why there is no "Spiritual".
							 */
							$oria_dna  = function_exists( '\Oria\Core\Dna\bars' ) ? \Oria\Core\Dna\bars( $oria_id ) : array();
							$oria_dnax = $oria_dna && function_exists( '\Oria\Core\Dna\experience_for' ) ? \Oria\Core\Dna\experience_for( $oria_id ) : null;
							$oria_feel = $oria_dnax ? \Oria\Core\Dna\summary( $oria_dna ) : '';
							$oria_like = $oria_dnax ? \Oria\Core\Dna\feels_like( $oria_dnax, 3 ) : array();

							/*
							 * What they do, as chips. Services first, not specialties:
							 * Fremantle Yoga Centre carries one specialty -- Sound healing
							 * -- and four services, so leading with the specialty put
							 * "Sound healing" alone under the name of a yoga studio.
							 *
							 * A chip links only when a page for it genuinely exists. The
							 * name is matched against the specialty taxonomy, whose term
							 * links are rewritten to /explore/{city}/{category}/{specialty}/;
							 * a service with no specialty of the same name -- "Restorative
							 * yoga", "Facials" -- stays plain text rather than pointing at
							 * the ugly ?taxonomy= form or a page that is not there.
							 */
							$oria_chips      = array();
							$oria_chip_terms = wp_get_post_terms( $oria_id, 'service' );
							$oria_chip_terms = is_wp_error( $oria_chip_terms ) ? array() : $oria_chip_terms;
							$oria_spec_terms = wp_get_post_terms( $oria_id, 'specialty' );
							$oria_chip_terms = array_merge( $oria_chip_terms, is_wp_error( $oria_spec_terms ) ? array() : $oria_spec_terms );

							// A chip repeating the category says nothing new --
							// "Yoga" under a yoga studio is the page you are on.
							$oria_chip_skip = array();
							if ( $oria_practice instanceof WP_Term ) {
								$oria_chip_skip[] = strtolower( \Oria\Theme\tname( $oria_practice ) );
								$oria_chip_skip[] = $oria_practice->slug;
							}

							$oria_seen_chip = array();
							foreach ( $oria_chip_terms as $oria_ct ) {
								if ( ! $oria_ct instanceof WP_Term || count( $oria_chips ) >= 6 ) {
									continue;
								}
								$oria_cname = \Oria\Theme\tname( $oria_ct );
								$oria_ckey  = strtolower( $oria_cname );
								if ( isset( $oria_seen_chip[ $oria_ckey ] )
									|| in_array( $oria_ckey, $oria_chip_skip, true )
									|| in_array( $oria_ct->slug, $oria_chip_skip, true ) ) {
									continue;
								}
								$oria_seen_chip[ $oria_ckey ] = true;

								$oria_curl  = '';
								$oria_cspec = get_term_by( 'slug', $oria_ct->slug, 'specialty' );
								if ( $oria_cspec instanceof WP_Term ) {
									$oria_clink = get_term_link( $oria_cspec );
									if ( ! is_wp_error( $oria_clink ) ) {
										$oria_curl = (string) $oria_clink;
									}
								}
								$oria_chips[] = array( 'label' => $oria_cname, 'url' => $oria_curl );
							}
							if ( 'in-person' !== $oria_format && '' !== $oria_format ) {
								$oria_chips[] = array( 'label' => $oria_format_label, 'url' => '' );
							}
							?>

							<div class="xp">

								<?php if ( $oria_wants ) : ?>
									<div class="xp__b">
										<span class="micro rowlabel"><?php esc_html_e( 'Good for', 'oria' ); ?></span>
										<div class="profile__wants">
											<?php foreach ( $oria_wants as $oria_w ) : ?>
												<span class="pill pill--gf" style="--gf:<?php echo esc_attr( $oria_w['color'] ); ?>"><?php echo esc_html( $oria_w['label'] ); ?></span>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endif; ?>

								<?php if ( $oria_meta ) : ?>
									<p class="xp__meta">
										<?php foreach ( $oria_meta as $oria_n => $oria_m ) : ?>
											<?php if ( $oria_n ) : ?><span class="xp__dot" aria-hidden="true">·</span><?php endif; ?>
											<span class="xp__meta-<?php echo esc_attr( $oria_m['kind'] ); ?>"><?php echo esc_html( $oria_m['label'] ); ?></span>
										<?php endforeach; ?>
									</p>
								<?php endif; ?>

								<?php if ( $oria_rest ) : ?>
									<div class="expect" role="group" aria-label="<?php esc_attr_e( 'What to expect', 'oria' ); ?>">
										<?php foreach ( $oria_rest as $oria_e ) : ?>
											<span class="expect__chip expect__chip--<?php echo esc_attr( $oria_e['kind'] ); ?>"><?php echo esc_html( $oria_e['label'] ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<?php // The feel leads. A reader decides on the sentence and checks the bars afterwards. ?>
								<?php if ( '' !== $oria_feel || $oria_like ) : ?>
									<div class="xp__b xp__b--rule">
										<span class="micro rowlabel"><?php esc_html_e( 'The experience', 'oria' ); ?></span>
										<?php if ( '' !== $oria_feel ) : ?>
											<p class="xp__statement"><?php echo esc_html( $oria_feel ); ?></p>
										<?php endif; ?>
										<?php if ( $oria_like ) : ?>
											<p class="xp__like">
												<span class="xp__like-k"><?php esc_html_e( 'Feels like', 'oria' ); ?></span>
												<?php foreach ( $oria_like as $oria_n => $oria_l ) : ?>
													<a href="<?php echo esc_url( $oria_l['url'] ); ?>"><?php echo esc_html( $oria_l['label'] ); ?></a><?php echo $oria_n < count( $oria_like ) - 1 ? '<span class="xp__dot" aria-hidden="true">·</span>' : ''; ?>
												<?php endforeach; ?>
											</p>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<?php if ( $oria_dna ) : ?>
									<div class="xp__b xp__b--rule">
										<span class="micro rowlabel"><?php esc_html_e( 'Experience DNA', 'oria' ); ?></span>
										<p class="xp__lede"><?php esc_html_e( 'A quick feel for what a session here is like.', 'oria' ); ?></p>
										<dl class="dna__bars">
											<?php foreach ( $oria_dna as $oria_b ) : ?>
												<div class="dna__row">
													<dt class="dna__label"><?php echo esc_html( $oria_b['label'] ); ?></dt>
													<dd class="dna__val">
														<span class="dna__track" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: dimension, 2: score, 3: score in words */ __( '%1$s: %2$d out of 5, %3$s', 'oria' ), $oria_b['label'], $oria_b['score'], $oria_b['word'] ) ); ?>">
															<?php for ( $oria_i = 1; $oria_i <= 5; $oria_i++ ) : ?>
																<i class="dna__seg<?php echo $oria_i <= $oria_b['score'] ? ' is-on' : ''; ?>"></i>
															<?php endfor; ?>
														</span>
														<small class="dna__word"><?php echo esc_html( $oria_b['word'] ); ?></small>
													</dd>
												</div>
											<?php endforeach; ?>
										</dl>
										<?php
										/*
										 * The methodology, folded away. It has to be readable --
										 * a scored profile with no stated basis is worse than no
										 * profile -- but it is a footnote, and it was reading as
										 * a paragraph of the page.
										 */
										?>
										<details class="xp__how">
											<summary><?php esc_html_e( 'How these ratings work', 'oria' ); ?></summary>
											<p><?php echo esc_html( sprintf( /* translators: %s: the kind of session, e.g. Reiki & energy work */ __( 'A guide, not a measurement. The bars start from how %s tends to run as a kind of session, then narrow to what this listing itself states about its price and group size. They describe the room — how quiet, how physical, how many people — and never what a session is supposed to do for you.', 'oria' ), (string) $oria_dnax['label'] ) ); ?></p>
										</details>
									</div>
								<?php endif; ?>

								<?php if ( $oria_chips ) : ?>
									<div class="xp__b xp__b--rule">
										<?php
										/*
										 * Not "What you can do here": the services block
										 * further down already owns that question, and owns it
										 * properly -- it lists every service the practice
										 * states, each one leading on to everywhere else in
										 * the city that offers it. This is the glance version,
										 * six terms at most, so it says what is offered rather
										 * than promising the full account of it.
										 */
										?>
										<span class="micro rowlabel"><?php esc_html_e( 'What they offer', 'oria' ); ?></span>
										<ul class="chips xp__chips">
											<?php foreach ( $oria_chips as $oria_chip ) : ?>
												<li>
													<?php if ( '' !== $oria_chip['url'] ) : ?>
														<a class="chip chip--link" href="<?php echo esc_url( $oria_chip['url'] ); ?>"><?php echo esc_html( $oria_chip['label'] ); ?></a>
													<?php else : ?>
														<span class="chip"><?php echo esc_html( $oria_chip['label'] ); ?></span>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>

								<?php // Already assembled above; it is the interpretation, so it closes the panel. ?>
								<?php if ( '' !== $oria_likely ) : ?>
									<div class="xp__b xp__b--rule">
										<span class="micro rowlabel"><?php esc_html_e( 'Why it might suit you', 'oria' ); ?></span>
										<p class="likely"><?php echo esc_html( $oria_likely ); ?></p>
									</div>
								<?php endif; ?>

							</div>

							<?php
							/*
							 * The next step, at the top rather than in a
							 * sidebar card two-thirds down the page.
							 *
							 * No "Book a class": booking_url is empty on every
							 * one of the 356 listings, and a button that
							 * cannot book is a worse answer than a link that
							 * goes where booking actually lives.
							 */
							?>
							<div class="row profile__cta">
								<?php if ( $oria_booking ) : ?>
									<a class="btn btn--dark" href="<?php echo esc_url( $oria_booking ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="book" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Book a session', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></a>
								<?php elseif ( $oria_website ) : ?>
									<a class="btn btn--dark" href="<?php echo esc_url( $oria_website ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="web" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Visit their website', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></a>
								<?php endif; ?>
								<?php if ( ! $oria_contactless ) : ?>
									<a class="btn btn--ghost btn--plain" href="#enquire"><?php esc_html_e( 'Send an enquiry', 'oria' ); ?></a>
								<?php endif; ?>
								<?php
								/*
								 * Rendered as the unsaved state and corrected
								 * by app.js on load. The alternative is asking
								 * the server what this browser has saved, and
								 * the server does not know — which is the
								 * point of keeping it on the device.
								 */
								?>
								<button class="btn btn--ghost btn--plain savebtn" type="button"
									data-save="<?php echo esc_attr( (string) get_post_field( 'post_name', $oria_id ) ); ?>"
									data-save-name="<?php echo esc_attr( \Oria\Theme\ptitle( $oria_id ) ); ?>"
									aria-pressed="false">
									<span class="savebtn__on" aria-hidden="true">&#9829;</span><span class="savebtn__off" aria-hidden="true">&#9825;</span>
									<span class="savebtn__label"><?php esc_html_e( 'Save', 'oria' ); ?></span>
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- At a glance -->
				<?php
				/*
				 * Built as a list rather than written out row by row, so a
				 * fact with nothing behind it is simply never added. The old
				 * block gated the whole panel on price_from, next_session and
				 * good_for — 20%, 0% and 7% of listings — which took Format
				 * down with it on the other 300.
				 *
				 * Ordered by what somebody deciding asks first: what is it,
				 * where, how far, what does it cost, is it any good.
				 */
				$oria_glance = array();

				if ( $oria_practice instanceof WP_Term ) {
					$oria_glance[] = array( __( 'Practice', 'oria' ), \Oria\Theme\tname( $oria_practice ) );
				}
				if ( $oria_suburb instanceof WP_Term ) {
					$oria_glance[] = array( __( 'Where', 'oria' ), \Oria\Theme\tname( $oria_suburb ) );
				}

				// No distance row either: "Getting there" further down already
				// gives km from the CBD, with the precision caveat beside it.

				$oria_glance[] = array( __( 'Format', 'oria' ), $oria_format_label );

				if ( (int) $oria_price_from > 0 ) {
					$oria_glance[] = array( __( 'From', 'oria' ), '$' . (int) $oria_price_from . ' ' . __( 'a session', 'oria' ) );
				} elseif ( '' !== trim( (string) get_field( 'price_band', $oria_id ) ) ) {
					/*
					 * The band is on 80% of listings where an exact figure is
					 * on 20%, so it is worth showing — but stored as "$$",
					 * which means nothing to a reader. Answer\band_label()
					 * is where the site already turns those into ranges, and
					 * reusing it keeps this panel saying the same thing as
					 * the filters and the category answer blocks.
					 */
					$oria_band = trim( (string) get_field( 'price_band', $oria_id ) );
					$oria_band = function_exists( '\Oria\Core\Answer\band_label' )
						? \Oria\Core\Answer\band_label( $oria_band )
						: '';
					if ( '' !== $oria_band ) {
						$oria_glance[] = array( __( 'Typical price', 'oria' ), $oria_band );
					}
				}

				// No rating row: the title block above already leads with it,
				// attributed and linked. Repeating it here would be padding.

				if ( $oria_next ) {
					$oria_glance[] = array( __( 'Next session', 'oria' ), $oria_next );
				}
				// good_for now has its own headed section below About — a
				// paragraph doesn't belong in a keyfacts row as well.

				/*
				 * Hours, amenities and the verified date, each only when the
				 * listing actually carries them. Hours sit on almost none of
				 * the imported listings yet -- the row exists for the owners
				 * who add them, never as dressing.
				 */
				$oria_hrows = \Oria\Theme\rows( 'opening_hours', array(), $oria_id );
				if ( $oria_hrows ) {
					$oria_hbits = array();
					foreach ( $oria_hrows as $oria_hr ) {
						$oria_hline = trim( trim( (string) ( $oria_hr['days'] ?? '' ) ) . ' ' . trim( (string) ( $oria_hr['hours'] ?? '' ) ) );
						if ( '' !== $oria_hline ) {
							$oria_hbits[] = $oria_hline;
						}
					}
					if ( $oria_hbits ) {
						$oria_glance[] = array( __( 'Hours', 'oria' ), implode( ' · ', array_slice( $oria_hbits, 0, 3 ) ) );
					}
				}
				/*
				 * No owner-entered hours: today's line from the Google record
				 * the page already holds for rating and photos. The full week
				 * sits in "Getting there"; the panel answers only "can I go
				 * now", which is the question a glance is for.
				 */
				if ( ! $oria_hrows && function_exists( '\Oria\Core\Places\hours_for' ) ) {
					$oria_today = (string) wp_date( 'l' );
					foreach ( \Oria\Core\Places\hours_for( $oria_id ) as $oria_ghl ) {
						if ( 0 === stripos( $oria_ghl, $oria_today ) ) {
							$oria_glance[] = array( __( 'Hours today', 'oria' ), trim( (string) preg_replace( '/^[^:]+:\s*/u', '', $oria_ghl ) ) );
							break;
						}
					}
				}
				$oria_amen = get_field( 'amenities', $oria_id );
				if ( is_string( $oria_amen ) && '' !== trim( $oria_amen ) ) {
					$oria_amen = array_map( 'trim', explode( ',', $oria_amen ) );
				}
				if ( is_array( $oria_amen ) && $oria_amen ) {
					$oria_glance[] = array( __( 'Amenities', 'oria' ), implode( ', ', array_slice( array_map( 'strval', $oria_amen ), 0, 4 ) ) );
				}
				if ( $oria_verified ) {
					$oria_glance[] = array( __( 'Verified', 'oria' ), mysql2date( 'j M Y', $oria_verified ) );
				}

				// Three is the point where a panel reads as a summary rather
				// than as two orphaned facts in a box.
				if ( count( $oria_glance ) >= 3 ) :
					?>
				<h2 class="sr-only"><?php esc_html_e( 'At a glance', 'oria' ); ?></h2>
				<div class="keyfacts reveal">
					<?php foreach ( $oria_glance as $oria_gf ) : ?>
						<div>
							<div class="keyfact__k"><?php echo esc_html( $oria_gf[0] ); ?></div>
							<div class="keyfact__v"><?php echo esc_html( $oria_gf[1] ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<!-- Special offer (paid feature; hides itself when expired or unclaimed) -->
				<?php $oria_offer = \Oria\Theme\active_offer( $oria_id ); ?>
				<?php if ( $oria_offer ) : ?>
				<div class="notice" style="background:var(--gold-soft);border-color:transparent">
					<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#7A5A12"><path d="M3 10.5V4a1 1 0 0 1 1-1h6.5L17 9.5a1.4 1.4 0 0 1 0 2L11.5 17a1.4 1.4 0 0 1-2 0L3 10.5Z"/><circle cx="7.2" cy="7.2" r="1.2"/></svg>
					<span>
						<b><?php echo esc_html( $oria_offer['title'] ); ?></b>
						<?php if ( $oria_offer['text'] ) : ?> — <?php echo esc_html( $oria_offer['text'] ); ?><?php endif; ?>
						<?php if ( $oria_offer['until'] ) : ?>
							<span style="display:block;font-size:.8125rem;color:#7A5A12;margin-top:.25rem"><?php printf( esc_html__( 'Until %s', 'oria' ), esc_html( mysql2date( 'j F Y', $oria_offer['until'] ) ) ); ?></span>
						<?php endif; ?>
					</span>
				</div>
				<?php endif; ?>

				<!-- About: full content, or the imported blurb until one is written -->
				<?php $oria_body = trim( (string) apply_filters( 'the_content', get_the_content() ) ); ?>
				<div class="reveal">
					<h2 class="h3" style="margin-bottom:.85rem"><?php esc_html_e( 'About', 'oria' ); ?></h2>
					<div class="prose">
						<?php echo $oria_body ? wp_kses_post( $oria_body ) : '<p>' . esc_html( get_the_excerpt() ) . '</p>'; // phpcs:ignore ?>
					</div>
				</div>

				<!-- What they're good at: the good_for field, in its own section.
				     Renders nothing when empty — most listings haven't been asked
				     yet, and an empty heading would read as a blank report card. -->
				<?php if ( '' !== trim( $oria_good_for ) ) : ?>
				<div>
					<h2 class="h3" style="margin-bottom:.85rem"><?php esc_html_e( "What they're good at", 'oria' ); ?></h2>
					<div class="prose">
						<?php echo wp_kses_post( wpautop( esc_html( $oria_good_for ) ) ); ?>
					</div>
					<?php if ( (int) get_post_meta( $oria_id, 'claimed_by', true ) ) : ?>
						<p class="hint"><?php esc_html_e( 'Told to us by the practice.', 'oria' ); ?></p>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<!-- The week, as a timetable, straight from the Classes repeater
				     the practitioner or admin keeps in the backend. -->
				<?php $oria_week = function_exists( '\Oria\Core\Classes\timetable_for' ) ? \Oria\Core\Classes\timetable_for( $oria_id ) : array(); ?>
				<?php if ( $oria_week ) : ?>
				<div class="reveal">
					<h2 class="h3" style="margin-bottom:.25rem"><?php esc_html_e( 'Weekly timetable', 'oria' ); ?></h2>
					<p class="hint" style="margin-bottom:1.1rem"><?php esc_html_e( 'Published by the practice. Public holidays excepted — check before you travel.', 'oria' ); ?></p>
					<?php get_template_part( 'template-parts/listing-week', null, array( 'sessions' => $oria_week ) ); ?>
				</div>
				<?php endif; ?>

<?php // The week grid above is the classes renderer now; the old per-class card list retired with it. ?>

				<!-- Packages -->
				<?php if ( $oria_packages ) : ?>
				<div>
					<h2 class="h3" style="margin-bottom:1.1rem"><?php esc_html_e( 'Packages', 'oria' ); ?></h2>
					<div class="pkgs">
						<?php
						foreach ( $oria_packages as $oria_pkg ) :
							$oria_ptitle = trim( (string) ( $oria_pkg['title'] ?? '' ) );
							if ( '' === $oria_ptitle ) {
								continue;
							}
							$oria_pdesc  = trim( (string) ( $oria_pkg['description'] ?? '' ) );
							$oria_pprice = trim( (string) ( $oria_pkg['price'] ?? '' ) );
							$oria_pimg   = (int) ( $oria_pkg['image'] ?? 0 );
							// Tagged like every outbound link, so the click
							// shows as oriahaven in the practice's analytics.
							$oria_purl   = \Oria\Theme\outbound( trim( (string) ( $oria_pkg['booking_url'] ?? '' ) ), $oria_slugname );
							?>
							<article class="pkgcard">
								<?php if ( $oria_pimg ) : ?>
									<div class="pkgcard__media">
										<?php echo wp_get_attachment_image( $oria_pimg, 'medium_large', false, array( 'class' => 'pkgcard__img', 'loading' => 'lazy', 'alt' => esc_attr( $oria_ptitle ) ) ); ?>
									</div>
								<?php endif; ?>
								<div class="pkgcard__body">
									<h3 class="pkgcard__title"><?php echo esc_html( $oria_ptitle ); ?></h3>
									<?php if ( $oria_pdesc ) : ?>
										<p class="pkgcard__desc"><?php echo esc_html( $oria_pdesc ); ?></p>
									<?php endif; ?>
									<?php if ( $oria_pprice ) : ?>
										<span class="pkgcard__price"><?php echo esc_html( $oria_pprice ); ?></span>
									<?php endif; ?>
									<?php if ( $oria_purl ) : ?>
										<a class="pkgcard__cta" href="<?php echo esc_url( $oria_purl ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="book" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Book', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Who you'll see: the practice's own people, when they've told us -->
				<?php
				$oria_team = \Oria\Theme\rows( 'team', array(), $oria_id );
				$oria_team = array_values( array_filter( (array) $oria_team, static function ( $oria_tr ) {
					return is_array( $oria_tr ) && '' !== trim( (string) ( $oria_tr['name'] ?? '' ) );
				} ) );
				?>
				<?php if ( $oria_team ) : ?>
				<div class="reveal">
					<h2 class="h3" style="margin-bottom:.85rem"><?php esc_html_e( 'Who you\'ll see', 'oria' ); ?></h2>
					<div class="teamrow">
						<?php foreach ( array_slice( $oria_team, 0, 6 ) as $oria_tm ) : ?>
							<?php
							$oria_tphoto = $oria_tm['photo'] ?? null;
							if ( is_array( $oria_tphoto ) ) {
								$oria_tphoto = $oria_tphoto['sizes']['thumbnail'] ?? ( $oria_tphoto['url'] ?? '' );
							} elseif ( is_numeric( $oria_tphoto ) ) {
								$oria_tphoto = (string) wp_get_attachment_image_url( (int) $oria_tphoto, 'thumbnail' );
							} else {
								$oria_tphoto = '';
							}
							?>
							<div class="teamcard">
								<?php if ( $oria_tphoto ) : ?>
									<img class="teamcard__photo" src="<?php echo esc_url( $oria_tphoto ); ?>" alt="" width="56" height="56" loading="lazy">
								<?php endif; ?>
								<div>
									<b class="teamcard__name"><?php echo esc_html( (string) $oria_tm['name'] ); ?></b>
									<?php if ( ! empty( $oria_tm['role'] ) ) : ?>
										<span class="teamcard__role"><?php echo esc_html( (string) $oria_tm['role'] ); ?></span>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="hint" style="margin-top:.6rem"><?php esc_html_e( 'Told to us by the practice.', 'oria' ); ?></p>
				</div>
				<?php endif; ?>

				<!-- Services -->
				<?php if ( $oria_services ) : ?>
				<div>
					<h2 class="h3" style="margin-bottom:.25rem"><?php esc_html_e( 'What can I do here?', 'oria' ); ?></h2>
					<p class="hint" style="margin-bottom:1.1rem"><?php printf( esc_html__( 'Each one leads to everywhere else in %s that offers it.', 'oria' ), esc_html( '' !== $oria_lname ? $oria_lname : __( 'Perth', 'oria' ) ) ); ?></p>
					<?php
					/*
					 * Two groups. A service that resolves to a canonical term
					 * with a live facet page becomes a card that goes there;
					 * anything else keeps its own words as a pill.
					 *
					 * Deduplicated on the destination, not the wording — a
					 * studio listing both "Morning vinyasa" and "Vinyasa
					 * classes" means one thing and should not produce two
					 * cards to the same page.
					 */
					$oria_cards = array();
					$oria_rest  = array();
					$oria_taken = array();

					foreach ( $oria_services as $oria_service ) {
						$oria_sname = trim( (string) ( $oria_service['name'] ?? '' ) );
						if ( '' === $oria_sname ) {
							continue;
						}

						$oria_slugs = function_exists( '\Oria\Core\Services\resolve_all' )
							? \Oria\Core\Services\resolve_all( $oria_sname )
							: array();
						$oria_url = '';
						$oria_slug = '';
						if ( $oria_slugs && function_exists( '\Oria\Core\PracticesIndex\service_url' ) ) {
							$oria_slug = (string) $oria_slugs[0];
							$oria_url  = \Oria\Core\PracticesIndex\service_url( $oria_slug );
						}

						if ( '' === $oria_url || isset( $oria_taken[ $oria_url ] ) ) {
							$oria_rest[] = $oria_sname;
							continue;
						}
						$oria_taken[ $oria_url ] = true;
						$oria_cards[]            = array(
							'label' => $oria_sname,
							'url'   => $oria_url,
							'note'  => function_exists( '\Oria\Core\Services\note_any' ) ? \Oria\Core\Services\note_any( $oria_slug ) : '',
							'slug'  => $oria_slug,
						);
					}
					?>
					<?php if ( $oria_cards ) : ?>
						<div class="offergrid">
							<?php foreach ( $oria_cards as $oria_c ) : ?>
								<?php
								$oria_cimg  = \Oria\Theme\facet_image( (string) $oria_c['slug'] );
								$oria_ccard = function_exists( '\Oria\Core\Services\card' )
									? \Oria\Core\Services\card( (string) $oria_c['slug'] )
									: array( 'traits' => array(), 'intensity' => 0 );
								?>
								<a class="offercard<?php echo $oria_cimg ? ' offercard--img' : ''; ?>" href="<?php echo esc_url( $oria_c['url'] ); ?>">
									<b class="offercard__name"><?php echo esc_html( $oria_c['label'] ); ?></b>
									<?php if ( '' !== $oria_c['note'] ) : ?>
										<span class="offercard__note"><?php echo esc_html( $oria_c['note'] ); ?></span>
									<?php endif; ?>
									<?php
									/*
									 * Everything from here to the go-line
									 * reveals together on hover. One wrapper,
									 * so the image, ticks and meter can never
									 * animate out of step. Not aria-hidden:
									 * the ticks are real information, and a
									 * collapsed grid row still reads.
									 */
									?>
									<span class="offercard__more"><span class="offercard__morein">
									<?php if ( $oria_ccard['traits'] ) : ?>
										<span class="offercard__traits">
											<span class="offercard__traitshead"><?php esc_html_e( 'Good to know:', 'oria' ); ?></span>
											<?php foreach ( $oria_ccard['traits'] as $oria_t ) : ?>
												<span class="offercard__trait"><span class="offercard__tick" aria-hidden="true">&#10003;</span><?php echo esc_html( $oria_t ); ?></span>
											<?php endforeach; ?>
										</span>
									<?php endif; ?>
									<?php
									/*
									 * Effort, 1-5, only where the registry says
									 * effort is a real property of the service.
									 * The dots are aria-hidden and the words
									 * carry the value -- "Intensity 4 of 5"
									 * reads; five filled-or-hollow circles do
									 * not.
									 */
									?>
									<?php if ( $oria_ccard['intensity'] > 0 ) : ?>
										<span class="offercard__meter">
											<span class="offercard__meterlabel"><?php esc_html_e( 'Intensity', 'oria' ); ?></span>
											<span class="offercard__dots" role="img" aria-label="<?php echo esc_attr( sprintf( __( 'Intensity %1$d of 5', 'oria' ), $oria_ccard['intensity'] ) ); ?>"><?php
											for ( $oria_i = 1; $oria_i <= 5; $oria_i++ ) {
												echo '<span class="offercard__dot' . ( $oria_i <= $oria_ccard['intensity'] ? ' is-on' : '' ) . '" aria-hidden="true"></span>';
											}
											?></span>
										</span>
									<?php endif; ?>
									<?php if ( $oria_cimg ) : ?>
										<?php // Decorative: the card already says all of this in words. ?>
										<span class="offercard__media" aria-hidden="true">
											<img class="offercard__img" src="<?php echo esc_url( $oria_cimg ); ?>" alt="" loading="lazy" decoding="async" width="800" height="450">
										</span>
									<?php endif; ?>
									</span></span>
									<span class="offercard__go"><?php echo esc_html( sprintf( __( 'Explore %s', 'oria' ), $oria_c['label'] ) ); ?> <span aria-hidden="true">&rarr;</span></span>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $oria_rest ) : ?>
						<div class="listing__tags"<?php echo $oria_cards ? ' style="margin-top:1rem"' : ''; ?>>
							<?php foreach ( $oria_rest as $oria_r ) : ?>
								<span class="pill pill--sand"><?php echo esc_html( $oria_r ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php
					/*
					 * Why people come here — the practice's own ticks, from
					 * Oria\Core\Reasons. Renders nothing at all when nothing
					 * has been ticked: an empty set means nobody has been
					 * asked, and it must never read as a list of noes.
					 */
					$oria_reasons = function_exists( '\Oria\Core\Reasons\flat' )
						? \Oria\Core\Reasons\flat( $oria_id )
						: array();
					?>
					<?php
					/*
					 * "People come here for" — the practice's own answer about
					 * why people book in. Renders nothing when nothing is
					 * ticked: an empty set means nobody has been asked, and it
					 * must never read as a list of things they do not do.
					 */
					$oria_comefor = function_exists( '\Oria\Core\ComeFor\for_listing' )
						? \Oria\Core\ComeFor\for_listing( $oria_id )
						: array();
					?>
					<?php if ( $oria_comefor ) : ?>
						<div style="margin-top:var(--s-5)">
							<h3 class="h4" style="margin-bottom:.75rem"><?php esc_html_e( 'People come here for', 'oria' ); ?></h3>
							<ul class="chips">
								<?php foreach ( $oria_comefor as $oria_cf ) : ?>
									<li><span class="chip"><?php echo esc_html( $oria_cf['label'] ); ?></span></li>
								<?php endforeach; ?>
							</ul>
							<p class="hint"><?php esc_html_e( 'Told to us by the practice. Reasons people book in — not a statement about what treatment achieves.', 'oria' ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $oria_reasons ) : ?>
						<div style="margin-top:var(--s-5)">
							<h3 class="h4" style="margin-bottom:.75rem"><?php esc_html_e( 'Why people come here', 'oria' ); ?></h3>
							<ul class="chips">
								<?php foreach ( $oria_reasons as $oria_rn ) : ?>
									<li><span class="chip"><?php echo esc_html( $oria_rn['label'] ); ?></span></li>
								<?php endforeach; ?>
							</ul>
							<p class="hint"><?php esc_html_e( 'Told to us by the practice.', 'oria' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<?php
				/*
				 * The modalities this practice is tagged with, linked to their
				 * own pages.
				 *
				 * Those 78 pages are the most commercially useful long-tail
				 * pages on the site — "remedial massage in Perth" is a real
				 * search, "the directory" is not — and every one of them had
				 * exactly one link pointing at it, from the hub. A practice
				 * offering remedial massage is the most relevant page on the
				 * internet to link that phrase from, so it does now, and the
				 * reader gets a way to see who else offers the same thing.
				 */
				$oria_specs = wp_get_post_terms( $oria_id, 'specialty' );
				$oria_specs = is_wp_error( $oria_specs ) ? array() : $oria_specs;
				?>
				<?php if ( $oria_specs ) : ?>
				<div>
					<h2 class="h3" style="margin-bottom:.85rem"><?php esc_html_e( 'Find more like this', 'oria' ); ?></h2>
					<?php
					/*
					 * Cards rather than pills, because these are destinations
					 * and a pill reads as a tag. The picture is the parent
					 * category's tile — two modalities from the same category
					 * will share one, which is honest: the eyebrow says so, and
					 * the count is what tells them apart.
					 */
					?>
					<div class="speccards">
						<?php
						foreach ( $oria_specs as $oria_spec ) :
							$oria_scount = (int) $oria_spec->count;

							/*
							 * Only where the destination actually holds something in
							 * this city. A southern listing carries the specialty term
							 * but not the matching service term, and the facet resolves
							 * through services first -- so the card pointed at
							 * /explore/margaret-river/recovery/traditional-sauna/ and
							 * that page is a 404 by design. Skip rather than link.
							 */
							if ( function_exists( '\Oria\Core\PracticesIndex\specialty_home' ) && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
								$oria_shome = \Oria\Core\PracticesIndex\specialty_home( $oria_spec->slug );
								$oria_scat  = '' !== $oria_shome ? get_term_by( 'slug', $oria_shome, \Oria\Core\Taxonomies\PRACTICE ) : null;
								if ( ! $oria_scat instanceof WP_Term ) {
									continue;
								}
								$oria_sfacet = \Oria\Core\PracticesIndex\resolve_facet( $oria_scat, $oria_spec->slug );
								if ( ! is_array( $oria_sfacet ) ) {
									continue;
								}
								$oria_srows = \Oria\Core\Cities\filter_ids( \Oria\Core\PracticesIndex\facet_ids( $oria_scat, $oria_sfacet ) );
								if ( ! $oria_srows ) {
									continue;
								}
								// The count of what the card links to. $oria_spec->count is
								// the whole corpus, and read "32 places" on a page whose
								// destination holds four.
								$oria_scount = count( $oria_srows );
							}

							$oria_stile   = \Oria\Theme\term_tile( $oria_spec );
							$oria_sparent = \Oria\Theme\specialty_parent( $oria_spec );
							?>
							<a class="speccard" href="<?php echo esc_url( function_exists( '\Oria\Core\PracticesIndex\specialty_url' ) ? \Oria\Core\PracticesIndex\specialty_url( $oria_spec ) : (string) get_term_link( $oria_spec ) ); ?>">
								<?php if ( '' !== $oria_stile ) : ?>
									<?php // Decorative: the name below already says what it is. ?>
									<img class="speccard__img" src="<?php echo esc_url( $oria_stile ); ?>" alt="" loading="lazy" width="320" height="200">
								<?php endif; ?>
								<span class="speccard__body">
									<?php if ( $oria_sparent ) : ?>
										<span class="speccard__eyebrow"><?php echo esc_html( \Oria\Theme\tname( $oria_sparent ) ); ?></span>
									<?php endif; ?>
									<span class="speccard__name">
										<?php
										printf(
											/* translators: 1: modality name, 2: city name. */
											esc_html__( '%1$s in %2$s', 'oria' ),
											esc_html( \Oria\Theme\tname( $oria_spec ) ),
											esc_html( '' !== $oria_lname ? $oria_lname : __( 'Perth', 'oria' ) )
										);
										?>
									</span>
									<?php if ( $oria_scount > 0 ) : ?>
										<span class="speccard__count">
											<?php
											printf(
												esc_html( _n( '%s place', '%s places', $oria_scount, 'oria' ) ),
												esc_html( number_format_i18n( $oria_scount ) )
											);
											?>
										</span>
									<?php endif; ?>
								</span>
								<span class="speccard__go" aria-hidden="true">&rarr;</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php // The people behind the listing, before the timetable: who you see matters more than when. ?>
				<?php get_template_part( 'template-parts/team', null, array( 'listing_id' => $oria_id ) ); ?>

				<!-- Upcoming events run by this listing -->
				<?php
				$oria_events = get_posts(
					array(
						'post_type'      => 'event',
						'post_status'    => 'publish',
						'posts_per_page' => 6,
						'meta_key'       => 'event_start',
						'orderby'        => 'meta_value',
						'order'          => 'ASC',
						'meta_query'     => array(
							array( 'key' => 'listing', 'value' => $oria_id ),
							array( 'key' => 'event_start', 'value' => current_time( 'Y-m-d H:i:s' ), 'compare' => '>=', 'type' => 'DATETIME' ),
						),
					)
				);
				if ( $oria_events ) :
					?>
				<div>
					<div class="row-between" style="margin-bottom:1rem">
						<h2 class="h3" style="margin:0"><?php esc_html_e( 'Upcoming events', 'oria' ); ?></h2>
						<a class="btn btn--ghost btn--sm btn--plain" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ); ?>"><?php esc_html_e( 'All events', 'oria' ); ?></a>
					</div>
					<div class="stack-md">
						<?php
						foreach ( $oria_events as $oria_ev ) :
							$oria_ev_start = (string) get_field( 'event_start', $oria_ev->ID );
							$oria_ev_ts    = $oria_ev_start ? strtotime( $oria_ev_start ) : false;
							$oria_ev_price = (string) get_field( 'price', $oria_ev->ID );
							$oria_ev_venue = (string) get_field( 'venue', $oria_ev->ID );
							?>
						<article class="eventrow">
							<div class="eventdate">
								<b><?php echo esc_html( $oria_ev_ts ? gmdate( 'd', $oria_ev_ts ) : '—' ); ?></b>
								<span><?php echo esc_html( $oria_ev_ts ? gmdate( 'M', $oria_ev_ts ) : '' ); ?></span>
							</div>
							<div>
								<?php if ( $oria_ev_ts ) : ?>
									<span class="muted" style="font-size:.8125rem"><?php echo esc_html( gmdate( 'D, g.ia', $oria_ev_ts ) ); ?></span>
								<?php endif; ?>
								<h3 class="h3" style="font-size:1.15rem;margin-top:.25rem"><a href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev ) ); ?></a></h3>
								<?php if ( $oria_ev_venue ) : ?>
									<p class="muted" style="font-size:.875rem;margin-top:.25rem"><?php echo esc_html( $oria_ev_venue ); ?></p>
								<?php endif; ?>
							</div>
							<div class="eventrow__cta" style="text-align:right">
								<?php if ( $oria_ev_price ) : ?>
									<div class="listing__price" style="margin-bottom:.6rem"><?php echo esc_html( $oria_ev_price ); ?></div>
								<?php endif; ?>
								<a class="btn btn--sm btn--dark" href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>"><?php esc_html_e( 'Details', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore ?></a>
							</div>
						</article>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php
				/*
				 * Reviews, in two clearly separated blocks: ours first, labelled as
				 * Oria Haven reviews, then Google's under their own heading. The
				 * anchor lives on the wrapper so a listing with only Google reviews,
				 * only ours, or neither still has somewhere for #reviews to land.
				 */
				?>
				<?php
				/*
				 * Amenities, and only the ones ticked. There is no "no showers"
				 * row and there never should be: an empty set means the
				 * practitioner has not filled this in, not that the building
				 * lacks the thing. Seeded listings therefore render nothing
				 * here at all.
				 */
				$oria_amenities = function_exists( '\Oria\Core\Amenities\for_listing' )
					? \Oria\Core\Amenities\for_listing( $oria_id )
					: array();
				?>
				<?php if ( $oria_amenities ) : ?>
					<div class="section" id="amenities">
						<h2 class="h3" style="margin-bottom:1rem"><?php esc_html_e( 'What is here', 'oria' ); ?></h2>
						<div class="amenity">
							<?php foreach ( $oria_amenities as $oria_grp ) : ?>
								<div class="amenity__group">
									<h3 class="micro amenity__label"><?php echo esc_html( $oria_grp['label'] ); ?></h3>
									<ul class="amenity__list">
										<?php foreach ( $oria_grp['items'] as $oria_item ) : ?>
											<li>
												<?php // Decorative: the label beside it carries the meaning. ?>
												<?php echo \Oria\Theme\amenity_icon( (string) $oria_item['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- built SVG, no user input. ?>
												<span><?php echo esc_html( (string) $oria_item['label'] ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endforeach; ?>
						</div>
						<p class="hint" style="margin-top:.9rem">
							<?php esc_html_e( 'Listed by the practice itself. Anything not shown has not been told to us either way.', 'oria' ); ?>
						</p>
					</div>
				<?php endif; ?>

				<div id="reviews">
				<?php get_template_part( 'template-parts/review', 'list', array( 'listing_id' => $oria_id ) ); ?>

				<?php
				/*
				 * One line from the reviews, set large on the page's single
				 * dark moment. Google's words, attributed and linked exactly
				 * as the cards below are -- shortened with an ellipsis, which
				 * their display rules allow, never reworded.
				 */
				$oria_quote = null;
				foreach ( $oria_reviews as $oria_qrv ) {
					$oria_qt = trim( (string) ( $oria_qrv['text'] ?? '' ) );
					if ( mb_strlen( $oria_qt ) >= 60 && (float) ( $oria_qrv['rating'] ?? 0 ) >= 4 ) {
						if ( preg_match( '/^(.{50,180}?[.!?])(\s|$)/u', $oria_qt, $oria_qm ) ) {
							$oria_qt = $oria_qm[1];
						} elseif ( mb_strlen( $oria_qt ) > 180 ) {
							$oria_qt = rtrim( mb_substr( $oria_qt, 0, 180 ) ) . '…';
						}
						$oria_quote = array( 'text' => $oria_qt, 'by' => (string) ( $oria_qrv['author'] ?? '' ) );
						break;
					}
				}
				?>
				<?php if ( $oria_quote ) : ?>
				<figure class="gquote reveal">
					<blockquote>“<?php echo esc_html( $oria_quote['text'] ); ?>”</blockquote>
					<figcaption>— <?php echo esc_html( $oria_quote['by'] ); ?>, <?php esc_html_e( 'on Google', 'oria' ); ?></figcaption>
				</figure>
				<?php endif; ?>

				<!-- Reviews (Google's, attributed and linked as their terms require) -->
				<?php if ( $oria_reviews ) : ?>
				<div>
					<div class="row-between" style="margin-bottom:1rem">
						<h2 class="h3" style="margin:0"><?php esc_html_e( 'Reviews', 'oria' ); ?>
							<span class="muted" style="font-weight:400;font-size:.875rem"><?php esc_html_e( 'from Google', 'oria' ); ?></span>
						</h2>
						<?php $oria_gr = \Oria\Core\Places\rating_for( $oria_id ); ?>
						<?php if ( $oria_gr['uri'] ) : ?>
							<a class="btn btn--ghost btn--sm btn--plain" href="<?php echo esc_url( $oria_gr['uri'] ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'Read all on Google', 'oria' ); ?></a>
						<?php endif; ?>
					</div>

					<?php foreach ( $oria_reviews as $oria_rv ) : ?>
						<div class="reviewitem">
							<div class="reviewitem__head">
								<div class="row" style="gap:.75rem">
									<?php if ( ! empty( $oria_rv['avatar'] ) ) : ?>
										<img src="<?php echo esc_url( $oria_rv['avatar'] ); ?>" alt="" aria-hidden="true" width="36" height="36" loading="lazy"
											style="border-radius:50%;flex:none" onerror="this.style.display='none'">
									<?php endif; ?>
									<div>
										<div class="reviewitem__who">
											<?php if ( ! empty( $oria_rv['author_uri'] ) ) : ?>
												<a href="<?php echo esc_url( $oria_rv['author_uri'] ); ?>" rel="nofollow noopener" target="_blank"><?php echo esc_html( $oria_rv['author'] ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $oria_rv['author'] ); ?>
											<?php endif; ?>
										</div>
										<div class="reviewitem__when"><?php echo esc_html( '' !== $oria_rv['when'] ? $oria_rv['when'] . ' · ' : '' ); ?><?php esc_html_e( 'on Google', 'oria' ); ?></div>
									</div>
								</div>
								<?php if ( $oria_rv['rating'] > 0 ) : ?>
									<span class="rating"><?php echo $oria_star; // phpcs:ignore ?> <?php echo esc_html( number_format_i18n( (float) $oria_rv['rating'], 1 ) ); ?></span>
								<?php endif; ?>
							</div>
							<p class="muted" style="font-size:.9375rem"><?php echo esc_html( $oria_rv['text'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php get_template_part( 'template-parts/review', 'form', array( 'listing_id' => $oria_id ) ); ?>
				</div><?php // #reviews ?>

				<!-- Quick answers: the FAQPage schema reads the same helper -->
				<?php $oria_faq = function_exists( '\Oria\Core\Schema\listing_faq' ) ? \Oria\Core\Schema\listing_faq( $oria_id ) : array(); ?>
				<?php if ( $oria_faq ) : ?>
				<div>
					<h2 class="h3" style="margin-bottom:1rem"><?php esc_html_e( 'Quick answers', 'oria' ); ?></h2>
					<div class="qanda">
						<?php foreach ( $oria_faq as $oria_i => $oria_qa ) : ?>
							<?php // Native accordion: crawler-visible collapsed, no JS. First stands open. ?>
							<details class="qanda__item"<?php echo 0 === $oria_i ? ' open' : ''; ?>>
								<summary class="qanda__q"><?php echo esc_html( $oria_qa['q'] ); ?></summary>
								<div class="qanda__a"><?php echo wp_kses_post( \Oria\Theme\qanda_html( (string) $oria_qa['a'] ) ); ?></div>
							</details>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Getting there -->
				<?php
				$oria_map = $oria_address ? \Oria\Theme\map_embed_url( $oria_address ) : '';
				/*
				 * Coordinates count toward showing this section. Fifty-odd
				 * listings carry no street address at all, and those are
				 * exactly the ones where "12 km from the CBD" is the most
				 * useful thing on the page — without this they were the only
				 * listings that got no distance.
				 */
				$oria_geo   = function_exists( '\Oria\Core\Geo\position' ) ? \Oria\Core\Geo\position( $oria_id ) : null;
				$oria_kmlab = $oria_geo ? \Oria\Core\Geo\label( $oria_id ) : '';
				if ( $oria_address || $oria_transit || $oria_parking || '' !== $oria_kmlab ) :
					?>
				<div class="reveal">
					<h2 class="h3" style="margin-bottom:1rem"><?php esc_html_e( 'Getting there', 'oria' ); ?></h2>
					<div class="card" style="overflow:hidden">
						<?php if ( $oria_map ) : ?>
							<?php
							/*
							 * The live embed loads on request, not on page load: a
							 * Google Maps iframe is the heaviest thing on a page
							 * this long, and most readers only want the address.
							 */
							?>
							<button type="button" class="mapfacade" data-map-src="<?php echo esc_url( $oria_map ); ?>"
								data-map-title="<?php printf( esc_attr__( 'Map showing %s', 'oria' ), esc_attr( \Oria\Theme\ptitle() ) ); ?>">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-5.3-7-11a7 7 0 0 1 14 0c0 5.7-7 11-7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg>
								<span><?php esc_html_e( 'Show the map', 'oria' ); ?></span>
							</button>
						<?php endif; ?>
						<div class="card__body">
							<div class="grid grid-2" style="gap:1rem">
								<?php if ( $oria_address ) : ?>
									<div>
										<div class="keyfact__k"><?php esc_html_e( 'Address', 'oria' ); ?></div>
										<div class="keyfact__v" style="font-weight:500"><?php echo esc_html( $oria_address ); ?></div>
									</div>
									<div style="align-self:center;justify-self:start">
										<a class="btn btn--ghost btn--sm btn--plain" href="<?php echo esc_url( \Oria\Theme\map_directions_url( $oria_address ) ); ?>" rel="noopener" target="_blank" data-oria-track="dir" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Get directions', 'oria' ); ?></a>
									</div>
								<?php endif; ?>
								<?php if ( $oria_transit ) : ?>
									<div><div class="keyfact__k"><?php esc_html_e( 'Public transport', 'oria' ); ?></div><div class="keyfact__v" style="font-weight:500"><?php echo esc_html( $oria_transit ); ?></div></div>
								<?php endif; ?>
								<?php if ( $oria_parking ) : ?>
									<div><div class="keyfact__k"><?php esc_html_e( 'Parking', 'oria' ); ?></div><div class="keyfact__v" style="font-weight:500"><?php echo esc_html( $oria_parking ); ?></div></div>
								<?php endif; ?>
								<?php
								/*
								 * Distance from the CBD, and how much to trust it.
								 * A listing placed on its suburb centroid says so —
								 * the alternative is a figure that looks like it was
								 * measured to the door when it was not. Both values
								 * are resolved above, where the section guard needs
								 * them too.
								 */
								?>
								<?php if ( '' !== $oria_kmlab ) : ?>
									<div data-oria-distance data-lat="<?php echo esc_attr( (string) $oria_geo['lat'] ); ?>" data-lng="<?php echo esc_attr( (string) $oria_geo['lng'] ); ?>">
										<div class="keyfact__k"><?php esc_html_e( 'Distance', 'oria' ); ?></div>
										<div class="keyfact__v" style="font-weight:500">
											<span data-oria-distance-value><?php echo esc_html( $oria_kmlab ); ?></span>
											<?php if ( 'suburb' === $oria_geo['precision'] ) : ?>
												<small style="display:block;font-weight:400;opacity:.7"><?php esc_html_e( 'measured to the suburb, not the door', 'oria' ); ?></small>
											<?php endif; ?>
											<?php // ODbL requires OpenStreetMap to be credited wherever a derived distance is shown. ?>
											<small style="display:block;font-weight:400;opacity:.55;margin-top:.25rem"><?php echo esc_html( \Oria\Core\Geo\attribution() ); ?></small>
										</div>
									</div>
								<?php endif; ?>
							</div>

							<?php $oria_wk = function_exists( '\Oria\Core\Places\hours_for' ) ? \Oria\Core\Places\hours_for( $oria_id ) : array(); ?>
							<?php if ( $oria_wk ) : ?>
								<div style="margin-top:1.1rem">
									<div class="keyfact__k"><?php esc_html_e( 'Opening hours', 'oria' ); ?></div>
									<ul class="hourslist">
										<?php $oria_todayw = (string) wp_date( 'l' ); ?>
										<?php foreach ( $oria_wk as $oria_hl ) : ?>
											<li<?php echo 0 === stripos( $oria_hl, $oria_todayw ) ? ' class="is-today"' : ''; ?>><?php echo esc_html( $oria_hl ); ?></li>
										<?php endforeach; ?>
									</ul>
									<p class="hint" style="margin-top:.45rem"><?php esc_html_e( 'Hours via Google — worth a check before a special trip.', 'oria' ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>

			</div>

			<!-- Rail -->
			<aside class="aside">
				<?php if ( ! $oria_contactless ) : ?>
				<div class="contactcard on-deep">
					<span class="micro"><?php esc_html_e( 'Get in touch', 'oria' ); ?></span>
					<h2 class="h3" style="color:#fff;margin-top:.6rem"><?php echo esc_html( $oria_words['contact_head'] ); ?></h2>
					<div class="contactcard__rows">
						<?php if ( $oria_phone ) : ?>
						<div class="contactcard__row"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M3 4.5c0 5 3.5 8.5 8.5 8.5l1.5-2-2.6-1.4-1.4 1.2A9.5 9.5 0 0 1 5.7 7l1.2-1.4L5.5 3 3.5 3Z"/></svg><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $oria_phone ) ); ?>" data-oria-track="tel" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo esc_html( $oria_phone ); ?></a></div>
						<?php endif; ?>
						<?php
						/*
						 * Only a paid listing publishes its address. Everything
						 * else keeps $oria_email — the enquiry form below needs
						 * it to know there is somewhere to send to, and the
						 * delivery reads it again server-side — but never
						 * prints it. Blanking the variable here would take the
						 * form down with it and leave the practice unreachable.
						 */
						if ( $oria_email && ( ! function_exists( '\Oria\Core\Tiers\shows_email' ) || \Oria\Core\Tiers\shows_email( $oria_id ) ) ) :
							?>
						<div class="contactcard__row"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="2" y="3.5" width="12" height="9" rx="2"/><path d="m2.6 4.5 5.4 3.6 5.4-3.6"/></svg><a href="mailto:<?php echo esc_attr( $oria_email ); ?>" data-oria-track="mail" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo esc_html( $oria_email ); ?></a></div>
						<?php endif; ?>
						<?php if ( $oria_website ) : ?>
						<div class="contactcard__row"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="8" cy="8" r="6"/><path d="M2 8h12M8 2c1.8 2 1.8 10 0 12M8 2C6.2 4 6.2 12 8 14"/></svg><a href="<?php echo esc_url( $oria_website ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="web" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo esc_html( \Oria\Theme\link_label( $oria_website ) ); ?></a></div>
						<?php endif; ?>
					</div>
					<?php if ( $oria_booking ) : ?>
						<a class="btn btn--light btn--block" href="<?php echo esc_url( $oria_booking ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="book" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Book on their site', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></a>
					<?php endif; ?>

					<?php
					/*
					 * The enquiry form. This used to be a mailto: link — the
					 * conversation left the site with no record it happened,
					 * so a practitioner could never be shown what their
					 * listing sends them. Now the enquiry is captured, stored
					 * and forwarded with Reply-To set to the visitor
					 * (Oria\Core\Leads).
					 */
					if ( $oria_email && function_exists( '\Oria\Core\Leads\eligible' ) && \Oria\Core\Leads\eligible( $oria_id ) ) :
						// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
						$oria_lead_state = isset( $_GET['olead'] ) ? (string) $_GET['olead'] : '';
						// phpcs:enable
						?>
						<div id="enquire">
						<?php if ( 'sent' === $oria_lead_state ) : ?>
							<div class="notice" style="background:rgba(255,255,255,.95);margin-top:.75rem">
								<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" style="width:20px;height:20px;flex:none"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>
								<span><b><?php esc_html_e( 'Enquiry sent.', 'oria' ); ?></b> <?php esc_html_e( 'It went straight to the practice — check your email for a copy.', 'oria' ); ?></span>
							</div>
						<?php else : ?>
							<details class="enqform" <?php echo 'error' === $oria_lead_state ? 'open' : ''; ?>>
								<summary class="btn btn--light btn--block enqform__open"><?php esc_html_e( 'Send an enquiry', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></summary>
								<form class="stack" style="gap:.7rem;margin-top:.9rem" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-oria-event="enquiry_started">
									<input type="hidden" name="action" value="oria_enquiry">
									<input type="hidden" name="listing_id" value="<?php echo (int) $oria_id; ?>">
									<input type="hidden" name="oform_ts" value="<?php echo esc_attr( (string) time() ); ?>">
									<?php wp_nonce_field( 'oria_enquiry_' . $oria_id, 'oform_nonce' ); ?>
									<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
									<?php if ( 'error' === $oria_lead_state ) : ?>
										<p style="font-size:.8125rem;color:#ffb4a2"><?php esc_html_e( 'That didn\'t send — check your name and email and try again.', 'oria' ); ?></p>
									<?php endif; ?>
									<label class="field"><span class="field__label enqform__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span>
										<input class="input" type="text" name="lead_name" required></label>
									<label class="field"><span class="field__label enqform__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
										<input class="input" type="email" name="lead_email" required></label>
									<label class="field"><span class="field__label enqform__label"><?php esc_html_e( 'Phone', 'oria' ); ?> <span style="color:var(--mist);font-weight:400">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
										<input class="input" type="tel" name="lead_phone"></label>
									<label class="field"><span class="field__label enqform__label"><?php esc_html_e( 'Message', 'oria' ); ?></span>
										<textarea class="textarea" name="lead_notes" style="min-height:72px" maxlength="600" required placeholder="<?php esc_attr_e( 'e.g. availability, prices, what a first visit looks like — please don\'t include medical details', 'oria' ); ?>"></textarea></label>
									<button class="btn btn--light btn--block" type="submit"><?php esc_html_e( 'Send to the practice', 'oria' ); ?></button>
								</form>
							</details>
						<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $oria_words['enquiry_note'] ) : ?>
					<p class="hint" style="color:var(--mist)"><?php
						/* translators: %s: "Enquiries go straight to the practice" or "... business" */
						printf( esc_html__( '%s — you\'ll get a copy by email. We never take a cut of bookings.', 'oria' ), esc_html( $oria_words['enquiry_note'] ) );
					?></p>
					<?php endif; ?>
				</div>
				<?php endif; // contact card ?>

				<?php
				// An owner should meet their share kit before anything else in
				// the rail; a visitor meets a quieter version further down.
				$oria_owns_this = is_user_logged_in()
					&& (int) get_post_meta( $oria_id, 'claimed_by', true ) === get_current_user_id();
				?>
				<?php
				// Straight off the one-click link in an invitation email: they
				// are logged in and own this now, and nothing on the page would
				// say so otherwise.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( $oria_owns_this && isset( $_GET['oria_claimed'] ) ) :
					?>
					<div class="claimprompt" style="background:var(--white)">
						<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'This listing is yours now.', 'oria' ); ?></b>
						<p style="font-size:.875rem;color:var(--text-soft)">
							<?php esc_html_e( 'We\'ve emailed you a link to set a password. After that you can keep your address, contact details, prices and format up to date whenever you like.', 'oria' ); ?>
						</p>
						<p style="margin-top:1rem">
							<a class="btn btn--dark btn--sm" href="<?php echo esc_url( get_edit_post_link( $oria_id ) ?: admin_url() ); ?>"><?php esc_html_e( 'Edit my listing', 'oria' ); ?></a>
						</p>
					</div>
				<?php endif; ?>

				<?php if ( $oria_owns_this ) : ?>
					<?php get_template_part( 'template-parts/share-box', null, array( 'id' => $oria_id, 'owner' => true, 'share_label' => $oria_words['share'] ) ); ?>
				<?php endif; ?>

				<?php
				// Social links are a paid feature: their own rail box, shown
				// only while the listing is claimed AND at least one link is
				// filled in — otherwise nothing renders at all.
				$oria_ig  = 'unclaimed' !== $oria_status ? trim( (string) get_field( 'instagram_url', $oria_id ) ) : '';
				$oria_fbk = 'unclaimed' !== $oria_status ? trim( (string) get_field( 'facebook_url', $oria_id ) ) : '';
				?>
				<?php if ( $oria_ig || $oria_fbk ) : ?>
				<div class="card">
					<div class="card__body">
						<h3 class="h3" style="font-size:1.05rem;margin-bottom:.75rem"><?php esc_html_e( 'Follow along', 'oria' ); ?></h3>
						<div class="stack" style="font-size:.9375rem">
							<?php if ( $oria_ig ) : ?>
							<a class="row" style="gap:.65rem" href="<?php echo esc_url( $oria_ig ); ?>" rel="nofollow noopener" target="_blank">
								<span class="featurerow__icon" style="width:34px;height:34px;border-radius:10px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="width:16px;height:16px"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1" fill="currentColor" stroke="none"/></svg></span>
								<span><b><?php esc_html_e( 'Instagram', 'oria' ); ?></b><br><span class="muted" style="font-size:.8125rem"><?php echo esc_html( '@' . trim( (string) wp_parse_url( $oria_ig, PHP_URL_PATH ), '/' ) ); ?></span></span>
							</a>
							<?php endif; ?>
							<?php if ( $oria_ig && $oria_fbk ) : ?><hr class="hr"><?php endif; ?>
							<?php if ( $oria_fbk ) : ?>
							<a class="row" style="gap:.65rem" href="<?php echo esc_url( $oria_fbk ); ?>" rel="nofollow noopener" target="_blank">
								<span class="featurerow__icon" style="width:34px;height:34px;border-radius:10px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="width:16px;height:16px"><path d="M15 8h2.5V5H15c-2 0-3.5 1.5-3.5 3.5V11H9v3h2.5v7h3v-7H17l.5-3h-3V8.7c0-.4.3-.7.5-.7Z"/></svg></span>
								<span><b><?php esc_html_e( 'Facebook', 'oria' ); ?></b><br><span class="muted" style="font-size:.8125rem"><?php echo esc_html( trim( (string) wp_parse_url( $oria_fbk, PHP_URL_PATH ), '/' ) ?: __( 'Page', 'oria' ) ); ?></span></span>
							</a>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( $oria_hours ) : ?>
				<div class="card">
					<div class="card__body">
						<h3 class="h3" style="font-size:1.05rem;margin-bottom:.75rem"><?php esc_html_e( 'Opening hours', 'oria' ); ?></h3>
						<div class="stack" style="font-size:.9375rem">
							<?php foreach ( $oria_hours as $oria_h ) : ?>
								<div class="row-between"><span class="muted"><?php echo esc_html( (string) ( $oria_h['days'] ?? '' ) ); ?></span><b><?php echo esc_html( (string) ( $oria_h['hours'] ?? '' ) ); ?></b></div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( ! $oria_owns_this ) : ?>
					<?php get_template_part( 'template-parts/share-box', null, array( 'id' => $oria_id, 'owner' => false, 'share_label' => $oria_words['share'] ) ); ?>
				<?php endif; ?>

				<?php if ( $oria_contactless ) : // nobody owns a beach, so neither prompt applies ?>
				<?php elseif ( 'unclaimed' === $oria_status && ! (int) get_post_meta( $oria_id, 'claimed_by', true ) ) : // Free-plan listings have an owner — don't invite rival claims. ?>
				<div class="claimprompt" id="claim">
					<?php if ( '' !== $oria_words['claim_head'] ) : ?>
					<b style="display:block;margin-bottom:.4rem"><?php echo esc_html( $oria_words['claim_head'] ); ?></b>
					<?php endif; ?>
					<?php if ( isset( $_GET['oria_claim'] ) && 'received' === $_GET['oria_claim'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
						<div class="notice" style="background:var(--white)" data-oria-event="claim_completed">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>
							<span><b><?php esc_html_e( 'Request received.', 'oria' ); ?></b> <?php esc_html_e( 'We check every claim by hand — you\'ll get an email with your log-in once it\'s approved.', 'oria' ); ?></span>
						</div>
					<?php else : ?>
						<p style="font-size:.875rem;color:var(--text-soft);margin-bottom:1rem">
							<?php esc_html_e( 'This listing was built from public information. Request to claim it and, once we\'ve confirmed it\'s you, you can edit every detail yourself.', 'oria' ); ?>
						</p>
						<?php if ( isset( $_GET['oria_claim'] ) && 'error' === $_GET['oria_claim'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
							<p style="font-size:.8125rem;color:#9b2c2c;margin-bottom:.75rem"><?php esc_html_e( 'That didn\'t send — check the name and email and try again.', 'oria' ); ?></p>
						<?php endif; ?>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="stack" style="gap:.75rem" data-oria-event="claim_started">
							<input type="hidden" name="action" value="oria_claim">
							<input type="hidden" name="listing_id" value="<?php echo (int) $oria_id; ?>">
							<?php wp_nonce_field( 'oria_claim', 'oria_claim_nonce' ); ?>
							<input type="text" name="oria_website_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
							<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span><input class="input" type="text" name="claimant_name" required></label>
							<label class="field"><span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span><input class="input" type="email" name="claimant_email" required placeholder="<?php esc_attr_e( 'Ideally the one on your website', 'oria' ); ?>"></label>
							<label class="field"><span class="field__label"><?php esc_html_e( 'Phone (optional)', 'oria' ); ?></span><input class="input" type="text" name="claimant_phone"></label>
							<label class="field"><span class="field__label"><?php esc_html_e( 'Anything that helps us verify you', 'oria' ); ?></span><textarea class="textarea" name="claimant_note" style="min-height:70px" placeholder="<?php esc_attr_e( 'e.g. your role, or where we can confirm your details', 'oria' ); ?>"></textarea></label>
							<button class="btn btn--dark btn--block" type="submit"><?php esc_html_e( 'Request to claim', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></button>
						</form>
					<?php endif; ?>
				</div>
				<?php else : ?>
				<div class="claimprompt">
					<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'Something out of date?', 'oria' ); ?></b>
					<p style="font-size:.875rem;color:var(--text-soft)">
						<?php esc_html_e( 'This listing is managed by the owner.', 'oria' ); ?>
						<a href="<?php echo esc_url( home_url( '/about/#contact' ) ); ?>" style="text-decoration:underline;text-underline-offset:3px"><?php esc_html_e( 'Let us know', 'oria' ); ?></a>
					</p>
				</div>
				<?php endif; ?>

				<?php
				/*
				 * Similar practices, scored on shared category, shared
				 * services and actual kilometres — see Oria\Core\Similar.
				 * This rail used to be orderby => rand inside the region,
				 * which put acupuncture clinics under yoga studios.
				 */
				$oria_similar = function_exists( '\Oria\Core\Similar\listings_for' )
					? \Oria\Core\Similar\listings_for( $oria_id, 3 )
					: array();
				if ( $oria_similar ) :
					?>
				<div class="card">
					<div class="card__body">
						<h3 class="h3" style="font-size:1.05rem;margin-bottom:1rem"><?php echo esc_html( $oria_words['similar'] ); ?></h3>
						<div class="stack" style="font-size:.9375rem">
							<?php
							foreach ( $oria_similar as $oria_k => $oria_nid ) :
								$oria_n_sub = null;
								foreach ( wp_get_post_terms( $oria_nid, 'area' ) as $oria_at ) {
									if ( $oria_at->parent ) {
										$oria_n_sub = $oria_at;
										break;
									}
								}
								// Distance rather than a service name: it is the
								// thing a reader cannot work out for themselves,
								// and it is on every listing.
								$oria_n_km   = function_exists( '\Oria\Core\Similar\km_between' )
									? \Oria\Core\Similar\km_between( $oria_id, (int) $oria_nid )
									: null;
								$oria_n_meta = array_filter( array(
									$oria_n_sub ? \Oria\Theme\tname( $oria_n_sub ) : '',
									( null !== $oria_n_km && $oria_n_km >= 0.4 )
										? sprintf( __( '%s km away', 'oria' ), number_format( $oria_n_km, $oria_n_km < 10 ? 1 : 0 ) )
										: '',
								) );
								// effective_rating(), not get_field('rating') —
								// the native field is empty on every listing, so
								// this star has never rendered until now.
								$oria_n_rate = \Oria\Theme\effective_rating( (int) $oria_nid );
								?>
								<?php if ( $oria_k > 0 ) : ?><hr class="hr"><?php endif; ?>
								<a class="row-between" href="<?php echo esc_url( (string) get_permalink( $oria_nid ) ); ?>">
									<span><b><?php echo esc_html( \Oria\Theme\ptitle( $oria_nid ) ); ?></b><br>
									<span class="muted" style="font-size:.8125rem"><?php echo esc_html( implode( ' · ', $oria_n_meta ) ); ?></span></span>
									<?php if ( ( $oria_n_rate['rating'] ?? 0 ) > 0 ) : ?>
										<span class="rating"><?php echo $oria_star; // phpcs:ignore ?><?php echo esc_html( number_format_i18n( (float) $oria_n_rate['rating'], 1 ) ); ?></span>
									<?php endif; ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</aside>
		</div>
	</section>

	<?php
	/*
	 * The sticky bar. Rendered once, after the article, and revealed by
	 * app.js only when the hero's own buttons have scrolled out of view.
	 *
	 * inert as well as aria-hidden while it is down: it repeats controls that
	 * exist elsewhere on the page, so without it a keyboard user tabs into a
	 * bar they cannot see.
	 */
	?>
	<div class="stickybar" data-sticky-cta inert aria-hidden="true">
		<div class="stickybar__inner">
			<div class="stickybar__id">
				<b><?php echo esc_html( \Oria\Theme\ptitle( $oria_id ) ); ?></b>
				<span>
					<?php
					$oria_sb = array();
					$oria_sr = \Oria\Theme\effective_rating( $oria_id );
					if ( ( $oria_sr['rating'] ?? 0 ) > 0 ) {
						$oria_sb[] = '★ ' . number_format_i18n( (float) $oria_sr['rating'], 1 );
					}
					if ( $oria_suburb instanceof WP_Term ) {
						$oria_sb[] = \Oria\Theme\tname( $oria_suburb );
					}
					echo esc_html( implode( ' · ', $oria_sb ) );
					?>
				</span>
			</div>
			<div class="stickybar__actions">
				<?php if ( $oria_address ) : ?>
					<a class="btn btn--ghost btn--sm btn--plain stickybar__hide-sm" href="<?php echo esc_url( \Oria\Theme\map_directions_url( $oria_address ) ); ?>" rel="noopener" target="_blank" data-oria-track="dir" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Directions', 'oria' ); ?></a>
				<?php endif; ?>
				<button class="btn btn--ghost btn--sm btn--plain savebtn stickybar__hide-sm" type="button"
					data-save="<?php echo esc_attr( (string) get_post_field( 'post_name', $oria_id ) ); ?>"
					data-save-name="<?php echo esc_attr( \Oria\Theme\ptitle( $oria_id ) ); ?>"
					aria-pressed="false">
					<span class="savebtn__on" aria-hidden="true">&#9829;</span><span class="savebtn__off" aria-hidden="true">&#9825;</span>
					<span class="savebtn__label"><?php esc_html_e( 'Save', 'oria' ); ?></span>
				</button>
				<?php if ( ! $oria_contactless ) : ?>
					<a class="btn btn--ghost btn--sm btn--plain" href="#enquire" data-sticky-enquire><?php esc_html_e( 'Enquire', 'oria' ); ?></a>
				<?php endif; ?>
				<?php if ( $oria_booking ) : ?>
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( $oria_booking ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="book" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Book', 'oria' ); ?></a>
				<?php elseif ( $oria_website ) : ?>
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( $oria_website ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="web" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Website', 'oria' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php $oria_shop = function_exists( '\Oria\Shop\Render\auto_band' ) ? \Oria\Shop\Render\auto_band() : ''; ?>
	<?php if ( $oria_shop ) : ?>
	<section class="wrap section section--top-flush"><?php echo $oria_shop; // phpcs:ignore WordPress.Security.EscapeOutput ?></section>
	<?php endif; ?>
	<?php
endwhile;

get_footer();
