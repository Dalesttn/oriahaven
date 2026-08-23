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
	$oria_practice = wp_get_post_terms( $oria_id, 'practice' )[0] ?? null;

	$oria_address    = (string) get_field( 'address', $oria_id );
	$oria_phone      = (string) get_field( 'phone', $oria_id );
	$oria_email      = (string) get_field( 'email', $oria_id );
	$oria_website    = (string) get_field( 'website', $oria_id );
	$oria_booking    = (string) get_field( 'booking_url', $oria_id );
	$oria_rating     = (float) get_field( 'rating', $oria_id );
	$oria_rcount     = (int) get_field( 'review_count', $oria_id );
	// rows() rather than an (array) cast: ACF returns false for an empty
	// repeater, and (array) false is [false] — a truthy one-element array
	// that renders a heading with a blank row under it.
	$oria_services   = \Oria\Theme\rows( 'services', array(), $oria_id );
	$oria_timetable  = \Oria\Theme\rows( 'timetable', array(), $oria_id );
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
							<?php if ( $oria_address || $oria_region ) : ?>
							<p class="listing__where" style="font-size:.9375rem;margin-top:.75rem">
								<?php echo $oria_pin; // phpcs:ignore ?>
								<?php echo esc_html( trim( $oria_address . ( $oria_region ? ' · ' . \Oria\Theme\tname( $oria_region ) : '' ), ' ·' ) ); ?>
							</p>
							<?php endif; ?>
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

				<!-- Key facts -->
				<?php if ( (int) $oria_price_from > 0 || $oria_next || $oria_good_for ) : ?>
				<div class="keyfacts">
					<?php if ( (int) $oria_price_from > 0 ) : ?>
						<div><div class="keyfact__k"><?php esc_html_e( 'From', 'oria' ); ?></div><div class="keyfact__v"><?php echo '$' . esc_html( (string) (int) $oria_price_from ) . ' ' . esc_html__( 'a session', 'oria' ); ?></div></div>
					<?php endif; ?>
					<?php if ( $oria_next ) : ?>
						<div><div class="keyfact__k"><?php esc_html_e( 'Next session', 'oria' ); ?></div><div class="keyfact__v"><?php echo esc_html( $oria_next ); ?></div></div>
					<?php endif; ?>
					<div><div class="keyfact__k"><?php esc_html_e( 'Format', 'oria' ); ?></div><div class="keyfact__v"><?php echo esc_html( $oria_format_label ); ?></div></div>
					<?php if ( $oria_good_for ) : ?>
						<div><div class="keyfact__k"><?php esc_html_e( 'Good for', 'oria' ); ?></div><div class="keyfact__v"><?php echo esc_html( $oria_good_for ); ?></div></div>
					<?php endif; ?>
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

				<!-- Services -->
				<?php if ( $oria_services ) : ?>
				<div>
					<h2 class="h3" style="margin-bottom:.85rem"><?php esc_html_e( 'What they offer', 'oria' ); ?></h2>
					<div class="listing__tags">
						<?php foreach ( $oria_services as $oria_service ) : ?>
							<span class="pill pill--sand"><?php echo esc_html( (string) ( $oria_service['name'] ?? '' ) ); ?></span>
						<?php endforeach; ?>
					</div>
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
					<div class="listing__tags">
						<?php foreach ( $oria_specs as $oria_spec ) : ?>
							<a class="pill" href="<?php echo esc_url( function_exists( '\Oria\Core\PracticesIndex\specialty_url' ) ? \Oria\Core\PracticesIndex\specialty_url( $oria_spec ) : (string) get_term_link( $oria_spec ) ); ?>">
								<?php
								printf(
									/* translators: %s: modality name */
									esc_html__( '%s in Perth', 'oria' ),
									esc_html( \Oria\Theme\tname( $oria_spec ) )
								);
								?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php // The people behind the listing, before the timetable: who you see matters more than when. ?>
				<?php get_template_part( 'template-parts/team', null, array( 'listing_id' => $oria_id ) ); ?>

				<!-- Timetable -->
				<?php if ( $oria_timetable ) : ?>
				<div>
					<h2 class="h3" style="margin-bottom:1rem"><?php esc_html_e( 'Timetable', 'oria' ); ?></h2>
					<table class="timetable">
						<thead><tr><th><?php esc_html_e( 'When', 'oria' ); ?></th><th><?php esc_html_e( 'Session', 'oria' ); ?></th><th style="text-align:right"><?php esc_html_e( 'Price', 'oria' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $oria_timetable as $oria_row ) : ?>
							<tr>
								<td><?php echo esc_html( (string) ( $oria_row['when'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $oria_row['session'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $oria_row['price'] ?? '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( $oria_verified ) : ?>
						<p class="hint"><?php printf( esc_html__( 'Timetable confirmed %s. Public holidays excepted — check before you travel.', 'oria' ), esc_html( mysql2date( 'j F Y', $oria_verified ) ) ); ?></p>
					<?php endif; ?>
				</div>
				<?php endif; ?>

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

				<!-- Getting there -->
				<?php
				$oria_map = $oria_address ? \Oria\Theme\map_embed_url( $oria_address ) : '';
				if ( $oria_address || $oria_transit || $oria_parking ) :
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
				// Nearby: other listings in the same region.
				if ( $oria_region ) :
					$oria_nearby = get_posts(
						array(
							'post_type'      => 'listing',
							'posts_per_page' => 3,
							'post__not_in'   => array( $oria_id ),
							'orderby'        => 'rand',
							'tax_query'      => array(
								array(
									'taxonomy'         => 'area',
									'field'            => 'term_id',
									'terms'            => $oria_region->term_id,
									'include_children' => true,
								),
							),
						)
					);
					if ( $oria_nearby ) :
						?>
				<div class="card">
					<div class="card__body">
						<h3 class="h3" style="font-size:1.05rem;margin-bottom:1rem"><?php printf( esc_html__( 'Nearby in %s', 'oria' ), esc_html( \Oria\Theme\tname( $oria_region ) ) ); ?></h3>
						<div class="stack" style="font-size:.9375rem">
							<?php foreach ( $oria_nearby as $oria_k => $oria_n ) :
								$oria_n_sub  = wp_get_post_terms( $oria_n->ID, 'area' )[0] ?? null;
								$oria_n_svc  = \Oria\Theme\rows( 'services', array(), $oria_n->ID )[0]['name'] ?? '';
								$oria_n_rate = (float) get_field( 'rating', $oria_n->ID );
								?>
								<?php if ( $oria_k > 0 ) : ?><hr class="hr"><?php endif; ?>
								<a class="row-between" href="<?php echo esc_url( get_permalink( $oria_n ) ); ?>">
									<span><b><?php echo esc_html( \Oria\Theme\ptitle( $oria_n ) ); ?></b><br>
									<span class="muted" style="font-size:.8125rem"><?php echo esc_html( trim( \Oria\Theme\tname( $oria_n_sub ) . ( $oria_n_svc ? ' · ' . $oria_n_svc : '' ), ' ·' ) ); ?></span></span>
									<?php if ( $oria_n_rate > 0 ) : ?>
										<span class="rating"><?php echo $oria_star; // phpcs:ignore ?><?php echo esc_html( number_format_i18n( $oria_n_rate, 1 ) ); ?></span>
									<?php endif; ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
					<?php endif; ?>
				<?php endif; ?>
			</aside>
		</div>
	</section>

	<?php $oria_shop = function_exists( '\Oria\Shop\Render\auto_band' ) ? \Oria\Shop\Render\auto_band() : ''; ?>
	<?php if ( $oria_shop ) : ?>
	<section class="wrap section section--top-flush"><?php echo $oria_shop; // phpcs:ignore WordPress.Security.EscapeOutput ?></section>
	<?php endif; ?>
	<?php
endwhile;

get_footer();
