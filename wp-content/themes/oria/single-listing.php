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
	$oria_classes    = $oria_paid ? \Oria\Theme\rows( 'classes', array(), $oria_id ) : array();
	$oria_packages   = $oria_paid ? \Oria\Theme\rows( 'packages', array(), $oria_id ) : array();
	$oria_verified   = (string) get_field( 'verified_at', $oria_id );
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

	// LocalBusiness schema is emitted once by Oria\Core\Schema\listing_schema(),
	// which carries the @id, price band, website and rating this used to duplicate.
	?>
	<section class="wrap" style="padding-top:1.75rem">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Directory', 'oria' ); ?></a>
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
			<div class="gallery">
				<img class="gallery__main" src="<?php echo esc_url( $oria_gallery[0] ); ?>" alt="<?php echo esc_attr( \Oria\Theme\ptitle() ); ?>" onerror="<?php echo esc_attr( $oria_fb ); ?>">
				<div class="gallery__side">
					<img src="<?php echo esc_url( $oria_gallery[1] ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: practice name */ __( '%s, second photo', 'oria' ), \Oria\Theme\ptitle() ) ); ?>" onerror="<?php echo esc_attr( $oria_fb ); ?>">
					<img src="<?php echo esc_url( $oria_gallery[2] ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: practice name */ __( '%s, third photo', 'oria' ), \Oria\Theme\ptitle() ) ); ?>" onerror="<?php echo esc_attr( $oria_fb ); ?>">
				</div>
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
							 * The want-tags, derived from this practice's own services and
							 * specialties exactly as the directory cards derive them — the
							 * same three words a visitor saw on the card that brought them
							 * here. Nothing is stored per listing and nothing is claimed on
							 * the practice's behalf: a tag appears only because the business
							 * genuinely offers what sits behind it.
							 */
							$oria_wants = function_exists( '\Oria\Core\GoodFor\for_listing' ) ? \Oria\Core\GoodFor\for_listing( $oria_id ) : array();
							?>
							<?php if ( $oria_wants ) : ?>
								<div class="profile__wants">
									<?php foreach ( $oria_wants as $oria_w ) : ?>
										<span class="pill pill--gf" style="--gf:<?php echo esc_attr( $oria_w['color'] ); ?>"><?php echo esc_html( $oria_w['label'] ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php
							/*
							 * One line saying what this is, before anything
							 * else. Taken from the blurb's opening sentence,
							 * which is on 100% of listings and was written to
							 * be read first — it just was not being shown
							 * until the About section most of a screen down.
							 */
							$oria_lede = trim( wp_strip_all_tags( (string) get_the_excerpt() ) );
							if ( '' !== $oria_lede ) {
								if ( preg_match( '/^(.{40,190}?[.!?])(\s|$)/u', $oria_lede, $oria_m ) ) {
									$oria_lede = $oria_m[1];
								} elseif ( mb_strlen( $oria_lede ) > 190 ) {
									$oria_lede = rtrim( mb_substr( $oria_lede, 0, 190 ) ) . '…';
								}
							}
							?>
							<?php if ( '' !== $oria_lede ) : ?>
								<p class="lede profile__lede"><?php echo esc_html( $oria_lede ); ?></p>
							<?php endif; ?>

							<?php if ( $oria_address || $oria_region ) : ?>
							<p class="listing__where" style="font-size:.9375rem;margin-top:.75rem">
								<?php echo $oria_pin; // phpcs:ignore ?>
								<?php echo esc_html( trim( $oria_address . ( $oria_region ? ' · ' . \Oria\Theme\tname( $oria_region ) : '' ), ' ·' ) ); ?>
							</p>
							<?php endif; ?>

							<?php
							/*
							 * What they do, as chips. Specialties first — they
							 * are the modality names people search — then the
							 * format. Audience tags would belong here too and
							 * are deliberately absent: they sit on 7% of
							 * listings, and a row that says "Beginners
							 * welcome" on one page and nothing on the next
							 * teaches a reader to stop looking at it.
							 */
							$oria_chips = array();

							/*
							 * Services first, not specialties. Fremantle Yoga
							 * Centre carries one specialty — Sound healing —
							 * and four services: Hatha, Vinyasa, Yin and Yoga.
							 * Leading with the specialty put "Sound healing"
							 * alone under the name of a yoga studio.
							 */
							$oria_chip_terms = wp_get_post_terms( $oria_id, 'service' );
							$oria_chip_terms = is_wp_error( $oria_chip_terms ) ? array() : $oria_chip_terms;
							$oria_spec_terms = wp_get_post_terms( $oria_id, 'specialty' );
							$oria_chip_terms = array_merge( $oria_chip_terms, is_wp_error( $oria_spec_terms ) ? array() : $oria_spec_terms );

							// A chip repeating the category says nothing new —
							// "Yoga" under a yoga studio is the page you are on.
							$oria_chip_skip = array();
							if ( $oria_practice instanceof WP_Term ) {
								$oria_chip_skip[] = strtolower( \Oria\Theme\tname( $oria_practice ) );
								$oria_chip_skip[] = $oria_practice->slug;
							}

							$oria_seen_chip = array();
							foreach ( $oria_chip_terms as $oria_ct ) {
								if ( ! $oria_ct instanceof WP_Term || count( $oria_chips ) >= 4 ) {
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
								$oria_chips[]                 = $oria_cname;
							}
							if ( 'in-person' !== $oria_format && '' !== $oria_format ) {
								$oria_chips[] = $oria_format_label;
							}
							?>
							<?php if ( $oria_chips ) : ?>
								<ul class="chips profile__chips">
									<?php foreach ( $oria_chips as $oria_chip ) : ?>
										<li><span class="chip"><?php echo esc_html( $oria_chip ); ?></span></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

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
								<a class="btn btn--ghost btn--plain" href="#enquire"><?php esc_html_e( 'Send an enquiry', 'oria' ); ?></a>
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

				// Three is the point where a panel reads as a summary rather
				// than as two orphaned facts in a box.
				if ( count( $oria_glance ) >= 3 ) :
					?>
				<h2 class="sr-only"><?php esc_html_e( 'At a glance', 'oria' ); ?></h2>
				<div class="keyfacts">
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
				<div>
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

				<!-- Classes: the practice's own timetable -->
				<?php if ( $oria_classes ) : ?>
				<div>
					<h2 class="h3" style="margin-bottom:.25rem"><?php esc_html_e( 'Classes', 'oria' ); ?></h2>
					<p class="hint" style="margin-bottom:1.1rem"><?php esc_html_e( 'Published by the practice. Public holidays excepted — check before you travel.', 'oria' ); ?></p>
					<?php
					/*
					 * Days come from each session's own day field, so the
					 * filter matches rather than parses. Only days that appear
					 * get a button, and the control is skipped below two: a
					 * Saturdays-only timetable needs reading, not filtering.
					 */
					$oria_cls_days = function_exists( '\Oria\Core\Classes\days_used' )
						? \Oria\Core\Classes\days_used( $oria_classes )
						: array();
					?>
					<div class="classes" data-classes>
						<?php if ( count( $oria_cls_days ) > 1 ) : ?>
							<div class="ttdays" role="group" aria-label="<?php esc_attr_e( 'Filter classes by day', 'oria' ); ?>">
								<button class="fchip is-on" type="button" data-cls-day="all"><?php esc_html_e( 'All days', 'oria' ); ?></button>
								<?php foreach ( $oria_cls_days as $oria_d ) : ?>
									<button class="fchip" type="button" data-cls-day="<?php echo (int) $oria_d; ?>"><?php echo esc_html( \Oria\Core\Classes\label( (int) $oria_d ) ); ?></button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<ul class="classlist">
							<?php
							foreach ( $oria_classes as $oria_row ) :
								$oria_ctitle = trim( (string) ( $oria_row['title'] ?? '' ) );
								if ( '' === $oria_ctitle ) {
									continue;
								}
								$oria_cdesc     = trim( (string) ( $oria_row['description'] ?? '' ) );
								$oria_cprice    = trim( (string) ( $oria_row['price'] ?? '' ) );
								$oria_csessions = is_array( $oria_row['sessions'] ?? null ) ? $oria_row['sessions'] : array();
								?>
								<li class="classrow">
									<div class="classrow__head">
										<h3 class="classrow__title"><?php echo esc_html( $oria_ctitle ); ?></h3>
										<?php // No price is not a free class. An empty cell says nothing, which is right. ?>
										<?php if ( $oria_cprice ) : ?>
											<span class="classrow__price"><?php echo esc_html( $oria_cprice ); ?></span>
										<?php endif; ?>
									</div>
									<?php if ( $oria_cdesc ) : ?>
										<p class="classrow__desc"><?php echo esc_html( $oria_cdesc ); ?></p>
									<?php endif; ?>
									<?php if ( $oria_csessions ) : ?>
										<ul class="sessions">
											<?php
											foreach ( $oria_csessions as $oria_sess ) :
												$oria_sdays = function_exists( '\Oria\Core\Classes\days_of' ) ? \Oria\Core\Classes\days_of( $oria_sess ) : array();
												$oria_stime = trim( (string) ( $oria_sess['time'] ?? '' ) );
												$oria_swith = trim( (string) ( $oria_sess['with'] ?? '' ) );
												?>
												<li class="session" data-cls-days="<?php echo esc_attr( implode( ' ', $oria_sdays ) ); ?>">
													<span class="session__day"><?php echo esc_html( \Oria\Core\Classes\day_summary( $oria_sess ) ); ?></span>
													<?php if ( $oria_stime ) : ?>
														<span class="session__time"><?php echo esc_html( $oria_stime ); ?></span>
													<?php endif; ?>
													<?php if ( $oria_swith ) : ?>
														<span class="session__with"><?php printf( esc_html__( 'with %s', 'oria' ), esc_html( $oria_swith ) ); ?></span>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php else : ?>
										<p class="session session--none"><?php esc_html_e( 'By arrangement — contact the practice.', 'oria' ); ?></p>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="dir__empty" data-cls-empty hidden style="margin-top:1rem"><?php esc_html_e( 'Nothing listed for that day.', 'oria' ); ?></p>
					</div>
				</div>
				<?php endif; ?>

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

				<!-- Services -->
				<?php if ( $oria_services ) : ?>
				<div>
					<h2 class="h3" style="margin-bottom:.25rem"><?php esc_html_e( 'What can I do here?', 'oria' ); ?></h2>
					<p class="hint" style="margin-bottom:1.1rem"><?php esc_html_e( 'Each one leads to everywhere else in Perth that offers it.', 'oria' ); ?></p>
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
											/* translators: %s: modality name */
											esc_html__( '%s in Perth', 'oria' ),
											esc_html( \Oria\Theme\tname( $oria_spec ) )
										);
										?>
									</span>
									<?php if ( (int) $oria_spec->count > 0 ) : ?>
										<span class="speccard__count">
											<?php
											printf(
												esc_html( _n( '%s place', '%s places', (int) $oria_spec->count, 'oria' ) ),
												esc_html( number_format_i18n( (int) $oria_spec->count ) )
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
				<div>
					<h2 class="h3" style="margin-bottom:1rem"><?php esc_html_e( 'Getting there', 'oria' ); ?></h2>
					<div class="card" style="overflow:hidden">
						<?php if ( $oria_map ) : ?>
							<iframe
								src="<?php echo esc_url( $oria_map ); ?>"
								title="<?php printf( esc_attr__( 'Map showing %s', 'oria' ), esc_attr( \Oria\Theme\ptitle() ) ); ?>"
								style="display:block;width:100%;aspect-ratio:16/7;border:0"
								loading="lazy"
								allowfullscreen
								referrerpolicy="no-referrer-when-downgrade"></iframe>
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
						</div>
					</div>
				</div>
				<?php endif; ?>

			</div>

			<!-- Rail -->
			<aside class="aside">
				<div class="contactcard on-deep">
					<span class="micro"><?php esc_html_e( 'Get in touch', 'oria' ); ?></span>
					<h2 class="h3" style="color:#fff;margin-top:.6rem"><?php esc_html_e( 'Book a first session', 'oria' ); ?></h2>
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
						<div class="contactcard__row"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="8" cy="8" r="6"/><path d="M2 8h12M8 2c1.8 2 1.8 10 0 12M8 2C6.2 4 6.2 12 8 14"/></svg><a href="<?php echo esc_url( $oria_website ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="web" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo esc_html( wp_parse_url( $oria_website, PHP_URL_HOST ) ?: $oria_website ); ?></a></div>
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
					<p class="hint" style="color:var(--mist)"><?php esc_html_e( 'Enquiries go straight to the practice — you\'ll get a copy by email. We never take a cut of bookings.', 'oria' ); ?></p>
				</div>

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
					<?php get_template_part( 'template-parts/share-box', null, array( 'id' => $oria_id, 'owner' => true ) ); ?>
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
					<?php get_template_part( 'template-parts/share-box', null, array( 'id' => $oria_id, 'owner' => false ) ); ?>
				<?php endif; ?>

				<?php if ( 'unclaimed' === $oria_status && ! (int) get_post_meta( $oria_id, 'claimed_by', true ) ) : // Free-plan listings have an owner — don't invite rival claims. ?>
				<div class="claimprompt" id="claim">
					<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'Is this your practice?', 'oria' ); ?></b>
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
						<h3 class="h3" style="font-size:1.05rem;margin-bottom:1rem"><?php esc_html_e( 'Similar practices', 'oria' ); ?></h3>
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
				<a class="btn btn--ghost btn--sm btn--plain" href="#enquire" data-sticky-enquire><?php esc_html_e( 'Enquire', 'oria' ); ?></a>
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
