<?php
/**
 * v4 listing profile -- the "experience page" (design test, child theme).
 *
 * Overrides the parent's single-listing.php. Every data read, guard, form,
 * analytics attribute and template part below comes from the parent file;
 * what changes is the order and the wrapping:
 *
 *   1. Visual intro   -- real photos (never a stand-in: no photo = a pine
 *                        panel), category · suburb · Best Of, the H1, and
 *                        the first sentence of the listing's own excerpt.
 *   2. Story + rail   -- story sections on the left, each only when its data
 *                        exists; a sticky action rail on the right.
 *   3. Similar        -- the parent's Similar\listings_for() as cards.
 *   4. Claim          -- the parent's claim form, unclaimed listings only.
 *   5. Phone          -- a persistent bottom action bar.
 *
 * Nothing on this page is written for the listing: every sentence is a
 * stored field or a label. A section with no data behind it does not render.
 *
 * Styles: assets/css/v4-listing.css (handle oria-v4-listing). All new classes
 * are prefixed .xp- so nothing collides with the parent's own .xp / .xp__*.
 */

declare(strict_types=1);

use function Oria\Theme\arrow;

get_header();

$oria_star = '<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>';

while ( have_posts() ) :
	the_post();

	/* ---------------------------------------------------------------------
	 * Data. Read exactly as the parent reads it.
	 * ------------------------------------------------------------------- */

	$oria_id     = get_the_ID();
	$oria_status = \Oria\Theme\claim_status( $oria_id );

	$oria_areas  = wp_get_post_terms( $oria_id, 'area' );
	$oria_suburb = null;
	$oria_region = null;
	foreach ( ( is_wp_error( $oria_areas ) ? array() : $oria_areas ) as $oria_t ) {
		if ( $oria_t->parent ) {
			$oria_suburb = $oria_t;
			$oria_region = \Oria\Core\Taxonomies\region_for( $oria_t );
		} elseif ( ! $oria_region ) {
			$oria_region = $oria_t;
		}
	}
	// Ranked by the category plan -- never $practices[0]. See Categories\primary_for().
	$oria_practice = function_exists( '\Oria\Core\Categories\primary_for' )
		? \Oria\Core\Categories\primary_for( $oria_id )
		: ( wp_get_post_terms( $oria_id, 'practice' )[0] ?? null );

	$oria_address  = (string) get_field( 'address', $oria_id );
	$oria_phone    = (string) get_field( 'phone', $oria_id );
	$oria_email    = (string) get_field( 'email', $oria_id );
	$oria_slugname = (string) get_post_field( 'post_name', $oria_id );
	$oria_website  = \Oria\Theme\outbound( (string) get_field( 'website', $oria_id ), $oria_slugname );
	$oria_booking  = \Oria\Theme\outbound( (string) get_field( 'booking_url', $oria_id ), $oria_slugname );
	$oria_services = \Oria\Theme\rows( 'services', array(), $oria_id );
	$oria_paid     = function_exists( '\Oria\Core\Ownership\is_paid' ) ? \Oria\Core\Ownership\is_paid( $oria_id ) : false;
	$oria_packages = $oria_paid ? \Oria\Theme\rows( 'packages', array(), $oria_id ) : array();
	$oria_verified = (string) get_field( 'verified_at', $oria_id );
	$oria_words    = \Oria\Theme\words( $oria_id );
	// A beach has no phone, no inbox and no owner: contact and claim blocks go.
	$oria_contactless = '' !== (string) ( $oria_words['contactless'] ?? '' );
	$oria_price_from  = get_field( 'price_from', $oria_id );
	$oria_format      = (string) ( get_field( 'format', $oria_id ) ?: 'in-person' );
	$oria_next        = (string) get_field( 'next_session', $oria_id );
	$oria_good_for    = (string) get_field( 'good_for', $oria_id );
	$oria_hours       = \Oria\Theme\rows( 'opening_hours', array(), $oria_id );
	$oria_transit     = (string) get_field( 'transit', $oria_id );
	$oria_parking     = (string) get_field( 'parking', $oria_id );
	$oria_reviews     = \Oria\Core\Places\reviews_for( $oria_id );
	$oria_display     = \Oria\Theme\display_status( $oria_id );
	$oria_claimed_by  = (int) get_post_meta( $oria_id, 'claimed_by', true );
	$oria_title       = \Oria\Theme\ptitle( $oria_id );

	$oria_format_label = 'both' === $oria_format
		? __( 'In person & online', 'oria' )
		: ( 'online' === $oria_format ? __( 'Online', 'oria' ) : __( 'In person', 'oria' ) );

	$oria_lcity = function_exists( '\Oria\Core\Cities\current' ) ? \Oria\Core\Cities\current() : null;
	$oria_lname = $oria_lcity ? \Oria\Core\Cities\name( $oria_lcity ) : '';

	/*
	 * Photos: the listing's own, else its featured image, else its Google
	 * Places photos (credited). Unlike the parent there is no placeholder
	 * scene at the end of the chain -- no photo means the pine panel, never
	 * a stand-in picture of somewhere else.
	 */
	$oria_places_attr = array();
	$oria_gallery     = array_values( array_filter( array_map(
		static fn( $gid ) => wp_get_attachment_image_url( (int) $gid, 'oria-wide' ),
		\Oria\Theme\rows( 'gallery', array(), $oria_id )
	) ) );
	// The plan caps what is published, not what is stored.
	$oria_gcap = function_exists( '\Oria\Core\Tiers\gallery_limit' ) ? \Oria\Core\Tiers\gallery_limit( $oria_id ) : 0;
	if ( $oria_gcap > 0 && count( $oria_gallery ) > $oria_gcap ) {
		$oria_gallery = array_slice( $oria_gallery, 0, $oria_gcap );
	}
	if ( ! $oria_gallery && has_post_thumbnail( $oria_id ) ) {
		$oria_gallery = array_filter( array( (string) get_the_post_thumbnail_url( $oria_id, 'oria-wide' ) ) );
	}
	if ( ! $oria_gallery ) {
		$oria_places = \Oria\Core\Places\photos_for( $oria_id );
		if ( ! empty( $oria_places['urls'] ) ) {
			$oria_gallery     = array_values( $oria_places['urls'] );
			$oria_places_attr = (array) $oria_places['attributions'];
		}
	}
	$oria_gallery = array_values( $oria_gallery );
	$oria_photos  = count( $oria_gallery );

	// Google photo URLs take a size suffix; ask for the size the slot needs.
	$oria_gsz = static function ( string $oria_gu, int $oria_gw ): string {
		return false !== strpos( $oria_gu, 'googleusercontent.com' )
			? (string) preg_replace( '/=[a-z0-9-]+$/i', '=w' . $oria_gw, $oria_gu )
			: $oria_gu;
	};

	/*
	 * The one-sentence reason to go: the first sentence of the listing's own
	 * excerpt, cut and never reworded. The remainder opens "What it's like"
	 * when the listing has no long description, so nothing is said twice.
	 */
	$oria_excerpt = trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $oria_id ) ) );
	$oria_lede    = $oria_excerpt;
	$oria_ex_rest = '';
	if ( '' !== $oria_excerpt && preg_match( '/^(.{40,}?[.!?])\s+(?=[\p{Lu}"\x{201C}\x{2018}(])/su', $oria_excerpt, $oria_xm ) ) {
		$oria_lede    = trim( $oria_xm[1] );
		$oria_ex_rest = trim( substr( $oria_excerpt, strlen( $oria_xm[0] ) ) );
	}
	$oria_body = trim( (string) apply_filters( 'the_content', get_the_content() ) );

	// Best Of: every guide that picked this listing, and the one badge a card may carry.
	$oria_best_in    = function_exists( '\Oria\Core\BestOf\guides_for_listing' ) ? \Oria\Core\BestOf\guides_for_listing( $oria_id ) : array();
	$oria_best_badge = function_exists( '\Oria\Core\BestOf\card_badge' ) ? \Oria\Core\BestOf\card_badge( $oria_id ) : null;

	// The experience profile, as the parent assembles it.
	$oria_wants  = function_exists( '\Oria\Core\GoodFor\for_listing' ) ? \Oria\Core\GoodFor\for_listing( $oria_id ) : array();
	$oria_expect = function_exists( '\Oria\Theme\expect_chips' ) ? \Oria\Theme\expect_chips( $oria_id ) : array();
	$oria_echips = array();
	foreach ( $oria_expect as $oria_e ) {
		// Price and distance live in the rail's facts; the rest stay chips.
		if ( ! in_array( $oria_e['kind'], array( 'price', 'where' ), true ) ) {
			$oria_echips[] = $oria_e;
		}
	}
	$oria_likely  = function_exists( '\Oria\Theme\likely_line' ) ? \Oria\Theme\likely_line( $oria_id ) : '';
	$oria_dna_on  = ! function_exists( '\Oria\Core\Dna\profile_enabled' ) || \Oria\Core\Dna\profile_enabled();
	$oria_like_on = ! function_exists( '\Oria\Core\Dna\feels_like_enabled' ) || \Oria\Core\Dna\feels_like_enabled();
	$oria_dna     = $oria_dna_on && function_exists( '\Oria\Core\Dna\bars' ) ? \Oria\Core\Dna\bars( $oria_id ) : array();
	$oria_dnax    = $oria_dna && function_exists( '\Oria\Core\Dna\experience_for' ) ? \Oria\Core\Dna\experience_for( $oria_id ) : null;
	$oria_feel    = $oria_dnax ? \Oria\Core\Dna\summary( $oria_dna ) : '';
	$oria_like    = $oria_dnax && $oria_like_on ? \Oria\Core\Dna\feels_like( $oria_dnax, 3 ) : array();

	// Rating: our own reviews first, else Google's, labelled and linked as Google's.
	$oria_rate = \Oria\Theme\effective_rating( $oria_id );
	$oria_grat = \Oria\Core\Places\rating_for( $oria_id );

	// Price: an exact "from" figure, else the band in words.
	$oria_price_num  = (int) $oria_price_from;
	$oria_band_label = '';
	if ( $oria_price_num <= 0 ) {
		$oria_band_raw   = trim( (string) get_field( 'price_band', $oria_id ) );
		$oria_band_label = ( '' !== $oria_band_raw && function_exists( '\Oria\Core\Answer\band_label' ) )
			? \Oria\Core\Answer\band_label( $oria_band_raw )
			: '';
	}

	// Special offer (paid; hides itself when expired or unclaimed).
	$oria_offer = \Oria\Theme\active_offer( $oria_id );

	// Hours: the owner's own rows, else today's line from Google.
	$oria_hbits = array();
	foreach ( $oria_hours as $oria_hr ) {
		$oria_hline = trim( trim( (string) ( $oria_hr['days'] ?? '' ) ) . ' ' . trim( (string) ( $oria_hr['hours'] ?? '' ) ) );
		if ( '' !== $oria_hline ) {
			$oria_hbits[] = $oria_hline;
		}
	}
	$oria_wk    = function_exists( '\Oria\Core\Places\hours_for' ) ? \Oria\Core\Places\hours_for( $oria_id ) : array();
	$oria_today = '';
	if ( ! $oria_hbits ) {
		$oria_todayw = (string) wp_date( 'l' );
		foreach ( $oria_wk as $oria_ghl ) {
			if ( 0 === stripos( $oria_ghl, $oria_todayw ) ) {
				$oria_today = trim( (string) preg_replace( '/^[^:]+:\s*/u', '', $oria_ghl ) );
				break;
			}
		}
	}

	$oria_amen = get_field( 'amenities', $oria_id );
	if ( is_string( $oria_amen ) && '' !== trim( $oria_amen ) ) {
		$oria_amen = array_map( 'trim', explode( ',', $oria_amen ) );
	}
	$oria_amen = is_array( $oria_amen ) ? array_filter( array_map( 'strval', $oria_amen ) ) : array();

	$oria_show_email = $oria_email && ( ! function_exists( '\Oria\Core\Tiers\shows_email' ) || \Oria\Core\Tiers\shows_email( $oria_id ) );
	$oria_can_enq    = ! $oria_contactless && $oria_email && function_exists( '\Oria\Core\Leads\eligible' ) && \Oria\Core\Leads\eligible( $oria_id );
	$oria_owns_this  = is_user_logged_in() && $oria_claimed_by === get_current_user_id();
	$oria_dir_url    = $oria_address ? \Oria\Theme\map_directions_url( $oria_address ) : '';
	$oria_tel        = $oria_phone && ! $oria_contactless ? (string) preg_replace( '/[^0-9+]/', '', $oria_phone ) : '';
	$oria_primary    = $oria_booking
		? array( 'url' => $oria_booking, 'track' => 'book', 'label' => __( 'Book a session', 'oria' ), 'short' => __( 'Book', 'oria' ) )
		: ( $oria_website ? array( 'url' => $oria_website, 'track' => 'web', 'label' => __( 'Visit their website', 'oria' ), 'short' => __( 'Visit website', 'oria' ) ) : null );

	// First-visit facts: only the fields a listing can actually carry.
	$oria_first  = array();
	$oria_bring  = trim( (string) get_field( 'what_to_bring', $oria_id ) );
	$oria_mins   = (int) get_field( 'duration_min', $oria_id );
	$oria_group  = (string) get_field( 'group_size', $oria_id );
	$oria_groups = array(
		'one-to-one' => __( 'One to one', 'oria' ),
		'small'      => __( 'Small groups (under 12)', 'oria' ),
		'class'      => __( 'Class sized (12+)', 'oria' ),
		'solo'       => __( 'On your own (a room or a machine)', 'oria' ),
	);
	if ( '' !== $oria_bring ) {
		$oria_first[] = array( __( 'Before you go', 'oria' ), $oria_bring );
	}
	if ( $oria_mins > 0 ) {
		/* translators: %d: minutes */
		$oria_first[] = array( __( 'Typical session', 'oria' ), sprintf( _n( '%d minute', '%d minutes', $oria_mins, 'oria' ), $oria_mins ) );
	}
	if ( isset( $oria_groups[ $oria_group ] ) ) {
		$oria_first[] = array( __( 'Group size', 'oria' ), $oria_groups[ $oria_group ] );
	}

	// Services heading: "Classes" where the listing runs a class timetable or a class-led category.
	$oria_week       = function_exists( '\Oria\Core\Classes\timetable_for' ) ? \Oria\Core\Classes\timetable_for( $oria_id ) : array();
	$oria_class_cats = array( 'yoga' ); // Fitness mixes classes with hikes and PTs; "What you'll find here" suits those.
	$oria_is_classes = $oria_week || ( $oria_practice instanceof WP_Term && in_array( $oria_practice->slug, $oria_class_cats, true ) );

	$oria_sec = 0; // Heading ids, so every section can be aria-labelledby.
	?>

<div class="xp-page">

	<div class="wrap xp-crumbs">
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
			<span aria-hidden="true">/</span><span aria-current="page"><?php the_title(); ?></span>
		</nav>
	</div>

	<!-- 1. Visual intro -->
	<section class="xp-hero" aria-labelledby="xp-title">
		<?php
		$oria_lightbox = $oria_photos >= 2;
		$oria_panel_cl = 'xp-hero__panel xp-hero__panel--n' . min( 3, $oria_photos );
		if ( $oria_lightbox ) {
			// .gallery[data-lightbox] is the hook app.js's lightbox looks for.
			$oria_panel_cl .= ' gallery';
		}
		// Places photo URIs are short-lived; one that expires simply drops out, leaving the pine panel.
		$oria_fb = "this.closest('.xp-hero__shot').hidden=true";
		?>
		<div class="<?php echo esc_attr( $oria_panel_cl ); ?>"<?php echo $oria_lightbox ? ' data-lightbox' : ''; ?>>
			<?php if ( $oria_photos ) : ?>
				<div class="xp-hero__media">
					<?php foreach ( array_slice( $oria_gallery, 0, 3 ) as $oria_pi => $oria_pu ) : ?>
						<?php
						$oria_palt = 0 === $oria_pi
							? $oria_title
							/* translators: 1: listing name, 2: photo number */
							: sprintf( __( '%1$s, photo %2$d', 'oria' ), $oria_title, $oria_pi + 1 );
						$oria_img  = sprintf(
							'<img src="%1$s" srcset="%2$s" sizes="%3$s" alt="%4$s"%5$s onerror="%6$s">',
							esc_url( $oria_gsz( $oria_pu, 0 === $oria_pi ? 1200 : 800 ) ),
							esc_attr( $oria_gsz( $oria_pu, 800 ) . ' 800w, ' . $oria_gsz( $oria_pu, 1600 ) . ' 1600w' ),
							esc_attr( 0 === $oria_pi ? '(max-width: 60rem) 100vw, 66vw' : '(max-width: 60rem) 0px, 33vw' ),
							esc_attr( $oria_palt ),
							0 === $oria_pi ? ' fetchpriority="high"' : ' loading="lazy"',
							esc_attr( $oria_fb )
						);
						?>
						<?php if ( $oria_lightbox ) : ?>
							<button type="button" class="xp-hero__shot xp-hero__shot--<?php echo (int) $oria_pi; ?>" data-lb="<?php echo (int) $oria_pi; ?>"
								aria-label="<?php echo esc_attr( sprintf( /* translators: 1: photo number, 2: photo count */ __( 'Open photo %1$d of %2$d', 'oria' ), $oria_pi + 1, $oria_photos ) ); ?>">
								<?php echo $oria_img; // phpcs:ignore WordPress.Security.EscapeOutput -- every attribute escaped above. ?>
							</button>
						<?php else : ?>
							<div class="xp-hero__shot xp-hero__shot--0"><?php echo $oria_img; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<div class="xp-hero__shade" aria-hidden="true"></div>
			<?php endif; ?>

			<div class="xp-hero__text">
				<?php
				$oria_eyebrow = array();
				if ( $oria_practice instanceof WP_Term ) {
					$oria_eyebrow[] = '<a href="' . esc_url( (string) get_term_link( $oria_practice ) ) . '">' . esc_html( \Oria\Theme\tname( $oria_practice ) ) . '</a>';
				}
				if ( $oria_suburb instanceof WP_Term ) {
					$oria_eyebrow[] = esc_html( \Oria\Theme\tname( $oria_suburb ) );
				} elseif ( $oria_region instanceof WP_Term ) {
					$oria_eyebrow[] = esc_html( \Oria\Theme\tname( $oria_region ) );
				}
				if ( $oria_best_badge ) {
					$oria_eyebrow[] = '<a class="xp-hero__best" href="' . esc_url( $oria_best_badge['url'] ) . '" title="' . esc_attr__( 'See the Best Of guide this comes from', 'oria' ) . '"><span aria-hidden="true">&#10022;</span> ' . esc_html( $oria_best_badge['label'] ) . '</a>';
				}
				?>
				<?php if ( $oria_eyebrow ) : ?>
					<p class="xp-hero__eyebrow"><?php echo implode( ' <span class="xp-hero__sep" aria-hidden="true">&middot;</span> ', $oria_eyebrow ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?></p>
				<?php endif; ?>

				<h1 class="h1 xp-hero__title" id="xp-title"><?php the_title(); ?></h1>

				<?php if ( '' !== $oria_lede ) : ?>
					<p class="xp-hero__lede"><?php echo esc_html( $oria_lede ); ?></p>
				<?php endif; ?>

				<?php
				// The parent's status badges, same guards: Featured/Claimed/Unclaimed, and the verified seal for paid tiers.
				$oria_pills = array();
				if ( 'featured' === $oria_display ) {
					$oria_pills[] = array( __( 'Featured', 'oria' ), '' );
				} elseif ( 'claimed' === $oria_display && 'unclaimed' === $oria_status ) {
					$oria_pills[] = array( __( 'Claimed', 'oria' ), '' );
				} elseif ( 'unclaimed' === $oria_display ) {
					$oria_pills[] = array( __( 'Unclaimed', 'oria' ), '' );
				}
				if ( 'unclaimed' !== $oria_status ) {
					$oria_pills[] = array(
						__( 'Verified', 'oria' ),
						/* translators: %s: date */
						$oria_verified ? sprintf( __( 'Details verified by the owner on %s', 'oria' ), mysql2date( 'j F Y', $oria_verified ) ) : __( 'Details verified by the owner', 'oria' ),
					);
				}
				?>
				<?php if ( $oria_pills ) : ?>
					<ul class="xp-hero__status" aria-label="<?php esc_attr_e( 'Listing status', 'oria' ); ?>">
						<?php foreach ( $oria_pills as $oria_pl ) : ?>
							<li class="xp-hero__pill"<?php echo '' !== $oria_pl[1] ? ' title="' . esc_attr( $oria_pl[1] ) . '"' : ''; ?>><?php echo esc_html( $oria_pl[0] ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<?php if ( $oria_lightbox ) : ?>
				<button type="button" class="xp-hero__all" data-lb="0">
					<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4" width="15" height="12" rx="2"/><circle cx="7.5" cy="8.5" r="1.4"/><path d="m3 15 4.5-4.5 3 3 2.5-2.5 4 4"/></svg>
					<?php
					/* translators: %d: number of photos */
					echo esc_html( sprintf( _n( '%d photo', 'All %d photos', $oria_photos, 'oria' ), $oria_photos ) );
					?>
				</button>
				<?php $oria_lb = array_map( static fn( string $oria_gu ): string => $oria_gsz( $oria_gu, 1600 ), $oria_gallery ); ?>
				<script type="application/json" data-lightbox-set><?php echo wp_json_encode( array_values( $oria_lb ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
			<?php endif; ?>
		</div>

		<?php if ( $oria_places_attr ) : // Google's terms require crediting photo contributors. ?>
			<p class="xp-hero__credit">
				<?php esc_html_e( 'Photos via Google', 'oria' ); ?> &mdash;
				<?php
				$oria_links = array();
				foreach ( $oria_places_attr as $oria_a ) {
					$oria_links[] = ! empty( $oria_a['uri'] )
						? '<a href="' . esc_url( $oria_a['uri'] ) . '" rel="nofollow noopener" target="_blank">' . esc_html( (string) $oria_a['name'] ) . '</a>'
						: esc_html( (string) ( $oria_a['name'] ?? '' ) );
				}
				echo implode( ', ', $oria_links ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</p>
		<?php endif; ?>
	</section>

	<?php
	/*
	 * Phone only: the three facts a reader checks first, straight under the
	 * picture. The full list is in the rail, which on a phone follows the
	 * story. Hidden from 60rem up, where the rail sits beside the story.
	 */
	$oria_glance_where = $oria_address ?: ( $oria_suburb instanceof WP_Term ? \Oria\Theme\tname( $oria_suburb ) : '' );
	$oria_glance_line  = array();
	if ( $oria_rate['rating'] > 0 ) {
		$oria_glance_line[] = number_format_i18n( (float) $oria_rate['rating'], 1 ) . ( $oria_rate['count'] > 0
			? ' · ' . sprintf(
				'google' === $oria_rate['source']
					/* translators: %d: number of reviews */
					? _n( '%d Google review', '%d Google reviews', (int) $oria_rate['count'], 'oria' )
					/* translators: %d: number of reviews */
					: _n( '%d Oria Haven review', '%d Oria Haven reviews', (int) $oria_rate['count'], 'oria' ),
				(int) $oria_rate['count']
			)
			: '' );
	}
	if ( '' !== $oria_band_label ) {
		$oria_glance_line[] = $oria_band_label;
	}
	$oria_has_top = $oria_offer || $oria_price_num > 0;
	?>
	<?php if ( $oria_has_top || '' !== $oria_glance_where || $oria_glance_line ) : ?>
		<div class="wrap xp-glance">
			<div class="xp-glance__card">
				<?php if ( $oria_offer ) : ?>
					<p class="xp-label"><?php esc_html_e( 'Offer', 'oria' ); ?></p>
					<p class="xp-glance__big"><?php echo esc_html( $oria_offer['title'] ); ?></p>
					<?php if ( $oria_offer['text'] ) : ?><p class="xp-glance__sub"><?php echo esc_html( $oria_offer['text'] ); ?></p><?php endif; ?>
				<?php elseif ( $oria_price_num > 0 ) : ?>
					<p class="xp-label"><?php esc_html_e( 'Price from', 'oria' ); ?></p>
					<p class="xp-glance__big"><?php echo esc_html( '$' . $oria_price_num ); ?> <span class="xp-glance__unit"><?php esc_html_e( 'a session', 'oria' ); ?></span></p>
				<?php endif; ?>
				<?php if ( '' !== $oria_glance_where || $oria_glance_line ) : ?>
					<p class="xp-glance__facts">
						<?php echo esc_html( $oria_glance_where ); ?>
						<?php if ( '' !== $oria_glance_where && $oria_glance_line ) : ?><br><?php endif; ?>
						<?php echo esc_html( implode( ' · ', $oria_glance_line ) ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- 2. Story (left) and the action rail (right) -->
	<div class="wrap xp-body">
		<div class="xp-story">

			<?php
			// Straight off the one-click link in an invitation email.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $oria_owns_this && isset( $_GET['oria_claimed'] ) ) :
				?>
				<div class="claimprompt xp-owner">
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
			/* --- What it's like -------------------------------------------
			 * The listing's own description, then the parent's experience
			 * profile (good for, the feel, DNA bars, why it might suit you)
			 * and good_for. Every piece is conditional on its own field. */
			$oria_about_html = '';
			if ( '' !== $oria_body ) {
				$oria_about_html = wp_kses_post( $oria_body );
			} elseif ( '' !== $oria_ex_rest ) {
				$oria_about_html = '<p>' . esc_html( $oria_ex_rest ) . '</p>';
			}
			$oria_has_about = '' !== $oria_about_html || $oria_wants || $oria_echips || '' !== $oria_feel || $oria_like || $oria_dna || '' !== $oria_likely || '' !== trim( $oria_good_for );
			?>
			<?php if ( $oria_has_about ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'What it’s like', 'oria' ); ?></h2>

					<?php if ( '' !== $oria_about_html ) : ?>
						<div class="prose xp-prose"><?php echo $oria_about_html; // phpcs:ignore WordPress.Security.EscapeOutput -- kses'd / escaped above. ?></div>
					<?php endif; ?>

					<?php if ( $oria_echips ) : ?>
						<ul class="xp-tags" aria-label="<?php esc_attr_e( 'What to expect', 'oria' ); ?>">
							<?php foreach ( $oria_echips as $oria_e ) : ?>
								<li class="xp-tag xp-tag--<?php echo esc_attr( $oria_e['kind'] ); ?>"><?php echo esc_html( $oria_e['label'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( $oria_wants || '' !== $oria_feel || $oria_like || $oria_dna || '' !== $oria_likely ) : ?>
						<div class="xp xp-profile">
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
									<details class="xp__how">
										<summary><?php esc_html_e( 'How these ratings work', 'oria' ); ?></summary>
										<p><?php echo esc_html( sprintf( /* translators: %s: the kind of session, e.g. Reiki & energy work */ __( 'A guide, not a measurement. The bars start from how %s tends to run as a kind of session, then narrow to what this listing itself states about its price and group size. They describe the room — how quiet, how physical, how many people — and never what a session is supposed to do for you.', 'oria' ), (string) $oria_dnax['label'] ) ); ?></p>
									</details>
								</div>
							<?php endif; ?>

							<?php if ( '' !== $oria_likely ) : ?>
								<div class="xp__b xp__b--rule">
									<span class="micro rowlabel"><?php esc_html_e( 'Why it might suit you', 'oria' ); ?></span>
									<p class="likely"><?php echo esc_html( $oria_likely ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( '' !== trim( $oria_good_for ) ) : ?>
						<div class="xp-sub">
							<h3 class="h3 xp-sub__title"><?php esc_html_e( "What they're good at", 'oria' ); ?></h3>
							<div class="prose xp-prose"><?php echo wp_kses_post( wpautop( esc_html( $oria_good_for ) ) ); ?></div>
							<?php if ( $oria_claimed_by ) : ?>
								<p class="hint"><?php esc_html_e( 'Told to us by the practice.', 'oria' ); ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php
			/* --- Classes / services you'll find here ----------------------
			 * The parent's services block, logic unchanged: a service with a
			 * live facet page becomes a card that goes there; anything else
			 * keeps its own words as a pill. Then "People come here for" and
			 * "Why people come here", the practice's own ticks. */
			$oria_comefor = function_exists( '\Oria\Core\ComeFor\for_listing' ) ? \Oria\Core\ComeFor\for_listing( $oria_id ) : array();
			$oria_reasons = function_exists( '\Oria\Core\Reasons\flat' ) ? \Oria\Core\Reasons\flat( $oria_id ) : array();
			$oria_cards   = array();
			$oria_srest   = array();
			$oria_taken   = array();
			foreach ( $oria_services as $oria_service ) {
				$oria_sname = trim( (string) ( $oria_service['name'] ?? '' ) );
				if ( '' === $oria_sname ) {
					continue;
				}
				$oria_slugs = function_exists( '\Oria\Core\Services\resolve_all' ) ? \Oria\Core\Services\resolve_all( $oria_sname ) : array();
				$oria_url   = '';
				$oria_slug  = '';
				if ( $oria_slugs && function_exists( '\Oria\Core\PracticesIndex\service_url' ) ) {
					$oria_slug = (string) $oria_slugs[0];
					$oria_url  = \Oria\Core\PracticesIndex\service_url( $oria_slug );
				}
				if ( '' === $oria_url || isset( $oria_taken[ $oria_url ] ) ) {
					$oria_srest[] = $oria_sname;
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
			<?php if ( $oria_cards || $oria_srest || $oria_comefor || $oria_reasons ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php echo esc_html( $oria_is_classes ? __( 'Classes you’ll find here', 'oria' ) : __( 'What you’ll find here', 'oria' ) ); ?></h2>
					<?php if ( $oria_cards ) : ?>
						<p class="xp-sec__hint"><?php printf( esc_html__( 'Each one leads to everywhere else in %s that offers it.', 'oria' ), esc_html( '' !== $oria_lname ? $oria_lname : __( 'Perth', 'oria' ) ) ); ?></p>
						<div class="offergrid xp-offers">
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
									<span class="offercard__more"><span class="offercard__morein">
									<?php if ( ! empty( $oria_ccard['traits'] ) ) : ?>
										<span class="offercard__traits">
											<span class="offercard__traitshead"><?php esc_html_e( 'Good to know:', 'oria' ); ?></span>
											<?php foreach ( $oria_ccard['traits'] as $oria_tr ) : ?>
												<span class="offercard__trait"><span class="offercard__tick" aria-hidden="true">&#10003;</span><?php echo esc_html( $oria_tr ); ?></span>
											<?php endforeach; ?>
										</span>
									<?php endif; ?>
									<?php if ( ( $oria_ccard['intensity'] ?? 0 ) > 0 ) : ?>
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
					<?php if ( $oria_srest ) : ?>
						<ul class="xp-tags xp-tags--services">
							<?php foreach ( $oria_srest as $oria_r ) : ?>
								<li class="xp-tag"><?php echo esc_html( $oria_r ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( $oria_comefor ) : ?>
						<div class="xp-sub">
							<h3 class="h3 xp-sub__title"><?php esc_html_e( 'People come here for', 'oria' ); ?></h3>
							<ul class="chips">
								<?php foreach ( $oria_comefor as $oria_cf ) : ?>
									<li><span class="chip"><?php echo esc_html( $oria_cf['label'] ); ?></span></li>
								<?php endforeach; ?>
							</ul>
							<p class="hint"><?php esc_html_e( 'Told to us by the practice. Reasons people book in — not a statement about what treatment achieves.', 'oria' ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $oria_reasons ) : ?>
						<div class="xp-sub">
							<h3 class="h3 xp-sub__title"><?php esc_html_e( 'Why people come here', 'oria' ); ?></h3>
							<ul class="chips">
								<?php foreach ( $oria_reasons as $oria_rn ) : ?>
									<li><span class="chip"><?php echo esc_html( $oria_rn['label'] ); ?></span></li>
								<?php endforeach; ?>
							</ul>
							<p class="hint"><?php esc_html_e( 'Told to us by the practice.', 'oria' ); ?></p>
						</div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php /* --- The week, from the Classes repeater --------------------- */ ?>
			<?php if ( $oria_week ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Weekly timetable', 'oria' ); ?></h2>
					<p class="xp-sec__hint"><?php esc_html_e( 'Published by the practice. Public holidays excepted — check before you travel.', 'oria' ); ?></p>
					<?php get_template_part( 'template-parts/listing-week', null, array( 'sessions' => $oria_week ) ); ?>
				</section>
			<?php endif; ?>

			<?php /* --- Packages (paid plans only) ------------------------------ */ ?>
			<?php if ( $oria_packages ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Packages', 'oria' ); ?></h2>
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
				</section>
			<?php endif; ?>

			<?php
			/* --- Why our editors picked it --------------------------------
			 * Best Of guides that picked this listing, with the editor's own
			 * reason. Replaces template-parts/best-featured-in.php here (same
			 * data: BestOf\guides_for_listing), so it keeps that part's
			 * editorial disclaimer and the seal download. */
			?>
			<?php if ( $oria_best_in ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec xp-picks" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Why our editors picked it', 'oria' ); ?></h2>
					<?php
					// The lead pick first, as the badge in the intro shows it.
					usort( $oria_best_in, static fn( $a, $b ) => (int) ! empty( $b['lead'] ) <=> (int) ! empty( $a['lead'] ) );
					?>
					<div class="xp-picks__list">
						<?php foreach ( $oria_best_in as $oria_pk => $oria_row ) : ?>
							<?php $oria_png = \Oria\Core\BestOf\seal_url( (string) $oria_row['award'], 'png', false ); ?>
							<figure class="xp-pick<?php echo 0 === $oria_pk ? ' xp-pick--lead' : ''; ?>">
								<figcaption class="xp-pick__head">
									<span class="xp-pick__award"><span class="xp-pick__mark" aria-hidden="true">&#10022;</span> <?php echo esc_html( (string) $oria_row['label'] ); ?></span>
									<span class="xp-pick__sep" aria-hidden="true">&middot;</span>
									<a class="xp-pick__guide" href="<?php echo esc_url( (string) get_permalink( $oria_row['guide'] ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_row['guide'] ) ) ); ?></a>
								</figcaption>
								<?php if ( ! empty( $oria_row['reason'] ) ) : ?>
									<blockquote class="xp-pick__why"><p>&ldquo;<?php echo esc_html( (string) $oria_row['reason'] ); ?>&rdquo;</p></blockquote>
								<?php endif; ?>
								<?php if ( $oria_png ) : ?>
									<a class="xp-pick__seal" href="<?php echo esc_url( $oria_png ); ?>" download><?php esc_html_e( 'Run this practice? Download the seal for your website', 'oria' ); ?></a>
								<?php endif; ?>
							</figure>
						<?php endforeach; ?>
					</div>
					<p class="hint"><?php esc_html_e( 'Best Of guides are an editorial selection. A practice cannot pay to be in one.', 'oria' ); ?></p>
				</section>
			<?php endif; ?>

			<?php /* --- Your first visit: stored first-visit fields only ------ */ ?>
			<?php if ( $oria_first ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Your first visit', 'oria' ); ?></h2>
					<dl class="xp-first">
						<?php foreach ( $oria_first as $oria_fv ) : ?>
							<div class="xp-first__item">
								<dt><?php echo esc_html( $oria_fv[0] ); ?></dt>
								<dd><?php echo esc_html( $oria_fv[1] ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</section>
			<?php endif; ?>

			<?php
			/* --- Who you'll see ------------------------------------------
			 * Both of the parent's team renderers: the listing's own team
			 * repeater, and template-parts/team (Team\visible), which opens
			 * its own section. */
			$oria_team = \Oria\Theme\rows( 'team', array(), $oria_id );
			$oria_team = array_values( array_filter( (array) $oria_team, static function ( $oria_tr ) {
				return is_array( $oria_tr ) && '' !== trim( (string) ( $oria_tr['name'] ?? '' ) );
			} ) );
			?>
			<?php if ( $oria_team ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Who you\'ll see', 'oria' ); ?></h2>
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
				</section>
			<?php endif; ?>

			<div class="xp-part"><?php get_template_part( 'template-parts/team', null, array( 'listing_id' => $oria_id ) ); ?></div>

			<?php
			/* --- Upcoming events run by this listing ----------------------- */
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
			?>
			<?php if ( $oria_events ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<div class="xp-sec__headrow">
						<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Upcoming events', 'oria' ); ?></h2>
						<a class="xp-link" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ); ?>"><?php esc_html_e( 'All events', 'oria' ); ?></a>
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
									<a class="btn btn--sm btn--dark" href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>"><?php esc_html_e( 'Details', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></a>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php
			/* --- What is here: ticked amenities only ----------------------- */
			$oria_amenities = function_exists( '\Oria\Core\Amenities\for_listing' ) ? \Oria\Core\Amenities\for_listing( $oria_id ) : array();
			?>
			<?php if ( $oria_amenities ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" id="amenities" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'What is here', 'oria' ); ?></h2>
					<div class="amenity">
						<?php foreach ( $oria_amenities as $oria_grp ) : ?>
							<div class="amenity__group">
								<h3 class="micro amenity__label"><?php echo esc_html( $oria_grp['label'] ); ?></h3>
								<ul class="amenity__list">
									<?php foreach ( $oria_grp['items'] as $oria_item ) : ?>
										<li>
											<?php echo \Oria\Theme\amenity_icon( (string) $oria_item['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- built SVG, no user input. ?>
											<span><?php echo esc_html( (string) $oria_item['label'] ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="hint" style="margin-top:.9rem"><?php esc_html_e( 'Listed by the practice itself. Anything not shown has not been told to us either way.', 'oria' ); ?></p>
				</section>
			<?php endif; ?>

			<?php
			/* --- Reviews ---------------------------------------------------
			 * Ours first (template-parts/review-list), then one Google line
			 * set large, then Google's own, attributed and linked; then the
			 * review form. #reviews stays on the wrapper. */
			$oria_quote = null;
			foreach ( $oria_reviews as $oria_qrv ) {
				$oria_qt = trim( (string) ( $oria_qrv['text'] ?? '' ) );
				if ( strlen( $oria_qt ) >= 60 && (float) ( $oria_qrv['rating'] ?? 0 ) >= 4 ) {
					if ( preg_match( '/^(.{50,180}?[.!?])(\s|$)/u', $oria_qt, $oria_qm ) ) {
						$oria_qt = $oria_qm[1];
					} elseif ( preg_match( '/^.{180}/us', $oria_qt, $oria_qm ) ) {
						$oria_qt = rtrim( $oria_qm[0] ) . '…';
					}
					$oria_quote = array( 'text' => $oria_qt, 'by' => (string) ( $oria_qrv['author'] ?? '' ) );
					break;
				}
			}
			$oria_sec++;
			?>
			<section class="xp-sec" id="reviews" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
				<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Reviews', 'oria' ); ?></h2>

				<?php get_template_part( 'template-parts/review', 'list', array( 'listing_id' => $oria_id ) ); ?>

				<?php if ( $oria_quote ) : ?>
					<figure class="gquote xp-gquote">
						<blockquote>“<?php echo esc_html( $oria_quote['text'] ); ?>”</blockquote>
						<figcaption>— <?php echo esc_html( $oria_quote['by'] ); ?>, <?php esc_html_e( 'on Google', 'oria' ); ?></figcaption>
					</figure>
				<?php endif; ?>

				<?php if ( $oria_reviews ) : ?>
					<div class="xp-greviews">
						<div class="xp-sec__headrow">
							<h3 class="h3 xp-sub__title"><?php esc_html_e( 'From Google', 'oria' ); ?></h3>
							<?php if ( ! empty( $oria_grat['uri'] ) ) : ?>
								<a class="xp-link" href="<?php echo esc_url( $oria_grat['uri'] ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'Read all on Google', 'oria' ); ?></a>
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
			</section>

			<?php /* --- Quick answers: the FAQPage schema reads the same helper -- */ ?>
			<?php $oria_faq = function_exists( '\Oria\Core\Schema\listing_faq' ) ? \Oria\Core\Schema\listing_faq( $oria_id ) : array(); ?>
			<?php if ( $oria_faq ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Quick answers', 'oria' ); ?></h2>
					<div class="qanda">
						<?php foreach ( $oria_faq as $oria_i => $oria_qa ) : ?>
							<details class="qanda__item"<?php echo 0 === $oria_i ? ' open' : ''; ?>>
								<summary class="qanda__q"><?php echo esc_html( $oria_qa['q'] ); ?></summary>
								<div class="qanda__a"><?php echo wp_kses_post( \Oria\Theme\qanda_html( (string) $oria_qa['a'] ) ); ?></div>
							</details>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php
			/* --- Getting there --------------------------------------------- */
			$oria_map   = $oria_address ? \Oria\Theme\map_embed_url( $oria_address ) : '';
			$oria_geo   = function_exists( '\Oria\Core\Geo\position' ) ? \Oria\Core\Geo\position( $oria_id ) : null;
			$oria_kmlab = $oria_geo ? \Oria\Core\Geo\label( $oria_id ) : '';
			?>
			<?php if ( $oria_address || $oria_transit || $oria_parking || '' !== $oria_kmlab ) : ?>
				<?php $oria_sec++; ?>
				<section class="xp-sec" id="getting-there" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Getting there', 'oria' ); ?></h2>
					<div class="card xp-there">
						<?php if ( $oria_map ) : ?>
							<button type="button" class="mapfacade" data-map-src="<?php echo esc_url( $oria_map ); ?>"
								data-map-title="<?php printf( esc_attr__( 'Map showing %s', 'oria' ), esc_attr( $oria_title ) ); ?>">
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
										<a class="btn btn--ghost btn--sm btn--plain xp-tap" href="<?php echo esc_url( $oria_dir_url ); ?>" rel="noopener" target="_blank" data-oria-track="dir" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Get directions', 'oria' ); ?></a>
									</div>
								<?php endif; ?>
								<?php if ( $oria_transit ) : ?>
									<div><div class="keyfact__k"><?php esc_html_e( 'Public transport', 'oria' ); ?></div><div class="keyfact__v" style="font-weight:500"><?php echo esc_html( $oria_transit ); ?></div></div>
								<?php endif; ?>
								<?php if ( $oria_parking ) : ?>
									<div><div class="keyfact__k"><?php esc_html_e( 'Parking', 'oria' ); ?></div><div class="keyfact__v" style="font-weight:500"><?php echo esc_html( $oria_parking ); ?></div></div>
								<?php endif; ?>
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
				</section>
			<?php endif; ?>

			<?php
			/* --- Send an enquiry ---------------------------------------------
			 * The parent's enquiry form, logic unchanged (stored, forwarded
			 * with Reply-To -- Oria\Core\Leads). Out of its dark rail card and
			 * into the story, so the sticky rail never grows past the screen;
			 * the rail links here. */
			?>
			<?php if ( $oria_can_enq ) : ?>
				<?php
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
				$oria_lead_state = isset( $_GET['olead'] ) ? (string) $_GET['olead'] : '';
				// phpcs:enable
				$oria_sec++;
				?>
				<section class="xp-sec xp-enquire" id="enquire" aria-labelledby="xp-s<?php echo (int) $oria_sec; ?>">
					<h2 class="h2 xp-sec__title" id="xp-s<?php echo (int) $oria_sec; ?>"><?php esc_html_e( 'Send an enquiry', 'oria' ); ?></h2>
					<?php if ( 'sent' === $oria_lead_state ) : ?>
						<div class="notice" role="status">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" style="width:20px;height:20px;flex:none" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>
							<span><b><?php esc_html_e( 'Enquiry sent.', 'oria' ); ?></b> <?php esc_html_e( 'It went straight to the practice — check your email for a copy.', 'oria' ); ?></span>
						</div>
					<?php else : ?>
						<form class="xp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-oria-event="enquiry_started">
							<input type="hidden" name="action" value="oria_enquiry">
							<input type="hidden" name="listing_id" value="<?php echo (int) $oria_id; ?>">
							<input type="hidden" name="oform_ts" value="<?php echo esc_attr( (string) time() ); ?>">
							<?php wp_nonce_field( 'oria_enquiry_' . $oria_id, 'oform_nonce' ); ?>
							<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
							<?php if ( 'error' === $oria_lead_state ) : ?>
								<p class="xp-form__error" role="alert"><?php esc_html_e( 'That didn\'t send — check your name and email and try again.', 'oria' ); ?></p>
							<?php endif; ?>
							<div class="xp-form__row">
								<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span>
									<input class="input" type="text" name="lead_name" autocomplete="name" required></label>
								<label class="field"><span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
									<input class="input" type="email" name="lead_email" autocomplete="email" required></label>
							</div>
							<label class="field"><span class="field__label"><?php esc_html_e( 'Phone', 'oria' ); ?> <span class="xp-form__opt">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
								<input class="input" type="tel" name="lead_phone" autocomplete="tel"></label>
							<label class="field"><span class="field__label"><?php esc_html_e( 'Message', 'oria' ); ?></span>
								<textarea class="textarea" name="lead_notes" style="min-height:96px" maxlength="600" required placeholder="<?php esc_attr_e( 'e.g. availability, prices, what a first visit looks like — please don\'t include medical details', 'oria' ); ?>"></textarea></label>
							<button class="btn btn--dark xp-tap" type="submit"><?php esc_html_e( 'Send to the practice', 'oria' ); ?></button>
						</form>
					<?php endif; ?>
					<?php if ( '' !== $oria_words['enquiry_note'] ) : ?>
						<p class="hint"><?php
							/* translators: %s: "Enquiries go straight to the practice" or "... business" */
							printf( esc_html__( '%s — you\'ll get a copy by email. We never take a cut of bookings.', 'oria' ), esc_html( $oria_words['enquiry_note'] ) );
						?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php
			/* --- Share and follow ------------------------------------------ */
			// Social links are a paid feature: shown only while claimed AND filled in.
			$oria_ig  = 'unclaimed' !== $oria_status ? trim( (string) get_field( 'instagram_url', $oria_id ) ) : '';
			$oria_fbk = 'unclaimed' !== $oria_status ? trim( (string) get_field( 'facebook_url', $oria_id ) ) : '';
			?>
			<div class="xp-extras">
				<?php if ( ! $oria_owns_this ) : ?>
					<?php get_template_part( 'template-parts/share-box', null, array( 'id' => $oria_id, 'owner' => false, 'share_label' => $oria_words['share'] ) ); ?>
				<?php endif; ?>

				<?php if ( $oria_ig || $oria_fbk ) : ?>
					<div class="card">
						<div class="card__body">
							<h2 class="h3" style="font-size:1.05rem;margin-bottom:.75rem"><?php esc_html_e( 'Follow along', 'oria' ); ?></h2>
							<div class="stack" style="font-size:.9375rem">
								<?php if ( $oria_ig ) : ?>
									<a class="row" style="gap:.65rem" href="<?php echo esc_url( $oria_ig ); ?>" rel="nofollow noopener" target="_blank">
										<span class="featurerow__icon" style="width:34px;height:34px;border-radius:10px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="width:16px;height:16px" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1" fill="currentColor" stroke="none"/></svg></span>
										<span><b><?php esc_html_e( 'Instagram', 'oria' ); ?></b><br><span class="muted" style="font-size:.8125rem"><?php echo esc_html( '@' . trim( (string) wp_parse_url( $oria_ig, PHP_URL_PATH ), '/' ) ); ?></span></span>
									</a>
								<?php endif; ?>
								<?php if ( $oria_ig && $oria_fbk ) : ?><hr class="hr"><?php endif; ?>
								<?php if ( $oria_fbk ) : ?>
									<a class="row" style="gap:.65rem" href="<?php echo esc_url( $oria_fbk ); ?>" rel="nofollow noopener" target="_blank">
										<span class="featurerow__icon" style="width:34px;height:34px;border-radius:10px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="width:16px;height:16px" aria-hidden="true"><path d="M15 8h2.5V5H15c-2 0-3.5 1.5-3.5 3.5V11H9v3h2.5v7h3v-7H17l.5-3h-3V8.7c0-.4.3-.7.5-.7Z"/></svg></span>
										<span><b><?php esc_html_e( 'Facebook', 'oria' ); ?></b><br><span class="muted" style="font-size:.8125rem"><?php echo esc_html( trim( (string) wp_parse_url( $oria_fbk, PHP_URL_PATH ), '/' ) ?: __( 'Page', 'oria' ) ); ?></span></span>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! $oria_contactless && ! ( 'unclaimed' === $oria_status && ! $oria_claimed_by ) ) : ?>
					<div class="claimprompt">
						<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'Something out of date?', 'oria' ); ?></b>
						<p style="font-size:.875rem;color:var(--text-soft)">
							<?php esc_html_e( 'This listing is managed by the owner.', 'oria' ); ?>
							<a href="<?php echo esc_url( home_url( '/about/#contact' ) ); ?>" style="text-decoration:underline;text-underline-offset:3px"><?php esc_html_e( 'Let us know', 'oria' ); ?></a>
						</p>
					</div>
				<?php endif; ?>
			</div>

		</div><!-- .xp-story -->

		<!-- The action rail -->
		<aside class="xp-rail" aria-labelledby="xp-rail-title">
			<div class="xp-rail__card">
				<h2 class="xp-rail__title" id="xp-rail-title"><?php esc_html_e( 'The practical bits', 'oria' ); ?></h2>

				<?php if ( $oria_offer ) : ?>
					<div class="xp-rail__top">
						<p class="xp-label"><?php esc_html_e( 'Offer', 'oria' ); ?></p>
						<p class="xp-rail__big"><?php echo esc_html( $oria_offer['title'] ); ?></p>
						<?php if ( $oria_offer['text'] ) : ?>
							<p class="xp-rail__sub"><?php echo esc_html( $oria_offer['text'] ); ?></p>
						<?php endif; ?>
						<?php if ( $oria_offer['until'] ) : ?>
							<p class="xp-rail__sub"><?php printf( esc_html__( 'Until %s', 'oria' ), esc_html( mysql2date( 'j F Y', $oria_offer['until'] ) ) ); ?></p>
						<?php endif; ?>
					</div>
				<?php elseif ( $oria_price_num > 0 ) : ?>
					<div class="xp-rail__top">
						<p class="xp-label"><?php esc_html_e( 'Price from', 'oria' ); ?></p>
						<p class="xp-rail__big"><?php echo esc_html( '$' . $oria_price_num ); ?> <span class="xp-rail__unit"><?php esc_html_e( 'a session', 'oria' ); ?></span></p>
					</div>
				<?php endif; ?>

				<?php if ( $oria_primary || ( $oria_booking && $oria_website ) || $oria_tel || $oria_can_enq ) : ?>
					<div class="xp-rail__ctas">
						<?php if ( $oria_primary ) : ?>
							<a class="btn btn--dark btn--block xp-tap" href="<?php echo esc_url( $oria_primary['url'] ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="<?php echo esc_attr( $oria_primary['track'] ); ?>" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo esc_html( $oria_primary['label'] ); ?><?php echo arrow(); // phpcs:ignore ?></a>
						<?php endif; ?>
						<?php if ( $oria_booking && $oria_website ) : ?>
							<a class="btn btn--ghost btn--block xp-tap" href="<?php echo esc_url( $oria_website ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="web" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Visit their website', 'oria' ); ?></a>
						<?php endif; ?>
						<?php if ( $oria_tel ) : ?>
							<a class="btn btn--ghost btn--block xp-tap" href="tel:<?php echo esc_attr( $oria_tel ); ?>" data-oria-track="tel" data-oria-id="<?php echo (int) $oria_id; ?>">
								<?php
								/* translators: %s: phone number */
								echo esc_html( sprintf( __( 'Call %s', 'oria' ), $oria_phone ) );
								?>
							</a>
						<?php endif; ?>
						<?php if ( $oria_can_enq ) : ?>
							<a class="btn btn--ghost btn--block xp-tap" href="#enquire"><?php esc_html_e( 'Send an enquiry', 'oria' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php
				// Facts: only rows with something behind them.
				$oria_facts = array();
				if ( $oria_address ) {
					$oria_facts[] = array( __( 'Where', 'oria' ), esc_html( $oria_address ) );
				} elseif ( $oria_suburb instanceof WP_Term || $oria_region instanceof WP_Term ) {
					$oria_facts[] = array( __( 'Where', 'oria' ), esc_html( \Oria\Theme\tname( $oria_suburb instanceof WP_Term ? $oria_suburb : $oria_region ) ) );
				}
				if ( $oria_offer && $oria_price_num > 0 ) {
					$oria_facts[] = array( __( 'Price', 'oria' ), esc_html( sprintf( __( 'From $%d a session', 'oria' ), $oria_price_num ) ) );
				} elseif ( '' !== $oria_band_label ) {
					$oria_facts[] = array( __( 'Typical price', 'oria' ), esc_html( $oria_band_label ) );
				}
				$oria_facts[] = array( __( 'Format', 'oria' ), esc_html( $oria_format_label ) );
				if ( $oria_rate['rating'] > 0 ) {
					$oria_rv_txt = number_format_i18n( (float) $oria_rate['rating'], 1 );
					if ( 'google' === $oria_rate['source'] ) {
						$oria_rv_txt .= ' · ' . ( $oria_rate['count'] > 0
							/* translators: %d: number of Google reviews */
							? sprintf( _n( '%d Google review', '%d Google reviews', (int) $oria_rate['count'], 'oria' ), (int) $oria_rate['count'] )
							: __( 'Rating on Google', 'oria' ) );
						$oria_rv_html = '<a class="xp-fact__link" href="' . esc_url( ! empty( $oria_grat['uri'] ) ? $oria_grat['uri'] : 'https://www.google.com/maps' ) . '" rel="nofollow noopener" target="_blank">' . $oria_star . ' ' . esc_html( $oria_rv_txt ) . '</a>';
					} else {
						if ( $oria_rate['count'] > 0 ) {
							/* translators: %d: number of reviews */
							$oria_rv_txt .= ' · ' . sprintf( _n( '%d Oria Haven review', '%d Oria Haven reviews', (int) $oria_rate['count'], 'oria' ), (int) $oria_rate['count'] );
						}
						$oria_rv_html = '<a class="xp-fact__link" href="#reviews">' . $oria_star . ' ' . esc_html( $oria_rv_txt ) . '</a>';
					}
					$oria_facts[] = array( __( 'Rating', 'oria' ), $oria_rv_html );
				}
				if ( $oria_hbits ) {
					$oria_facts[] = array( __( 'Hours', 'oria' ), implode( '<br>', array_map( 'esc_html', $oria_hbits ) ) );
				} elseif ( '' !== $oria_today ) {
					$oria_facts[] = array( __( 'Hours today', 'oria' ), esc_html( $oria_today ) . ( $oria_wk ? ' <a class="xp-fact__more" href="#getting-there">' . esc_html__( 'All week', 'oria' ) . '</a>' : '' ) );
				}
				if ( '' !== trim( $oria_next ) ) {
					$oria_facts[] = array( __( 'Next session', 'oria' ), esc_html( $oria_next ) );
				}
				if ( $oria_show_email && ! $oria_contactless ) {
					$oria_facts[] = array( __( 'Email', 'oria' ), '<a class="xp-fact__link" href="mailto:' . esc_attr( $oria_email ) . '" data-oria-track="mail" data-oria-id="' . (int) $oria_id . '">' . esc_html( $oria_email ) . '</a>' );
				}
				if ( $oria_amen ) {
					$oria_facts[] = array( __( 'Amenities', 'oria' ), esc_html( implode( ', ', array_slice( $oria_amen, 0, 4 ) ) ) );
				}
				if ( $oria_verified ) {
					$oria_facts[] = array( __( 'Verified', 'oria' ), esc_html( mysql2date( 'j M Y', $oria_verified ) ) );
				}
				?>
				<dl class="xp-facts">
					<?php foreach ( $oria_facts as $oria_f ) : ?>
						<div class="xp-fact">
							<dt><?php echo esc_html( $oria_f[0] ); ?></dt>
							<dd><?php echo $oria_f[1]; // phpcs:ignore WordPress.Security.EscapeOutput -- every value escaped as it was built. ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>

				<div class="xp-rail__foot">
					<?php if ( $oria_dir_url ) : ?>
						<a class="xp-link xp-tap" href="<?php echo esc_url( $oria_dir_url ); ?>" rel="noopener" target="_blank" data-oria-track="dir" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Get directions', 'oria' ); ?></a>
					<?php endif; ?>
					<?php
					/*
					 * Rendered as the unsaved state and corrected by app.js
					 * on load: what this browser has saved lives on the device.
					 */
					?>
					<button class="xp-save savebtn xp-tap" type="button"
						data-save="<?php echo esc_attr( $oria_slugname ); ?>"
						data-save-name="<?php echo esc_attr( $oria_title ); ?>"
						aria-pressed="false">
						<span class="savebtn__on" aria-hidden="true">&#9829;</span><span class="savebtn__off" aria-hidden="true">&#9825;</span>
						<span class="savebtn__label"><?php esc_html_e( 'Save', 'oria' ); ?></span>
					</button>
				</div>
			</div>
		</aside>
	</div><!-- .xp-body -->

	<?php
	/* --- 3. If you like the sound of this ---------------------------------
	 * Similar practices, scored on shared category, shared services and
	 * actual kilometres -- see Oria\Core\Similar. Then the specialty pages
	 * this listing is tagged with ("Find more like this"). */
	$oria_similar = function_exists( '\Oria\Core\Similar\listings_for' ) ? \Oria\Core\Similar\listings_for( $oria_id, 3 ) : array();

	$oria_specs     = wp_get_post_terms( $oria_id, 'specialty' );
	$oria_specs     = is_wp_error( $oria_specs ) ? array() : $oria_specs;
	$oria_speccards = array();
	foreach ( $oria_specs as $oria_spec ) {
		$oria_scount = (int) $oria_spec->count;
		// Only where the destination actually holds something in this city.
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
			$oria_scount = count( $oria_srows );
		}
		$oria_speccards[] = array( 'term' => $oria_spec, 'count' => $oria_scount );
	}
	?>
	<?php if ( $oria_similar || $oria_speccards ) : ?>
		<section class="wrap xp-more" aria-labelledby="xp-more-title">
			<?php if ( $oria_similar ) : ?>
				<h2 class="h2 xp-sec__title" id="xp-more-title"><?php esc_html_e( 'If you like the sound of this', 'oria' ); ?></h2>
				<ul class="xp-cards">
					<?php
					foreach ( $oria_similar as $oria_nid ) :
						$oria_nid   = (int) $oria_nid;
						$oria_n_sub = null;
						$oria_n_ar  = wp_get_post_terms( $oria_nid, 'area' );
						foreach ( ( is_wp_error( $oria_n_ar ) ? array() : $oria_n_ar ) as $oria_at ) {
							if ( $oria_at->parent ) {
								$oria_n_sub = $oria_at;
								break;
							}
						}
						$oria_n_cat  = function_exists( '\Oria\Core\Categories\primary_for' ) ? \Oria\Core\Categories\primary_for( $oria_nid ) : null;
						$oria_n_km   = function_exists( '\Oria\Core\Similar\km_between' ) ? \Oria\Core\Similar\km_between( $oria_id, $oria_nid ) : null;
						$oria_n_meta = array_filter( array(
							$oria_n_sub ? \Oria\Theme\tname( $oria_n_sub ) : '',
							( null !== $oria_n_km && $oria_n_km >= 0.4 )
								? sprintf( __( '%s km away', 'oria' ), number_format( $oria_n_km, $oria_n_km < 10 ? 1 : 0 ) )
								: '',
						) );
						$oria_n_rate = \Oria\Theme\effective_rating( $oria_nid );
						// Their own excerpt's first sentence, cut, never reworded.
						$oria_n_ex = trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $oria_nid ) ) );
						if ( '' !== $oria_n_ex && preg_match( '/^(.{40,}?[.!?])\s+(?=[\p{Lu}"\x{201C}\x{2018}(])/su', $oria_n_ex, $oria_nm ) ) {
							$oria_n_ex = trim( $oria_nm[1] );
						}
						?>
						<li class="xp-card">
							<?php if ( $oria_n_cat instanceof WP_Term ) : ?>
								<p class="xp-label"><?php echo esc_html( \Oria\Theme\tname( $oria_n_cat ) ); ?></p>
							<?php endif; ?>
							<h3 class="xp-card__title"><a class="xp-card__link" href="<?php echo esc_url( (string) get_permalink( $oria_nid ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( $oria_nid ) ); ?></a></h3>
							<?php if ( '' !== $oria_n_ex ) : ?>
								<p class="xp-card__text"><?php echo esc_html( $oria_n_ex ); ?></p>
							<?php endif; ?>
							<?php if ( $oria_n_meta || ( $oria_n_rate['rating'] ?? 0 ) > 0 ) : ?>
								<p class="xp-card__meta">
									<?php echo esc_html( implode( ' · ', $oria_n_meta ) ); ?>
									<?php if ( ( $oria_n_rate['rating'] ?? 0 ) > 0 ) : ?>
										<span class="rating"><?php echo $oria_star; // phpcs:ignore ?><?php echo esc_html( number_format_i18n( (float) $oria_n_rate['rating'], 1 ) ); ?></span>
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $oria_speccards ) : ?>
				<div class="xp-more__specs">
					<h2 class="h3 xp-sub__title"<?php echo $oria_similar ? '' : ' id="xp-more-title"'; ?>><?php esc_html_e( 'Find more like this', 'oria' ); ?></h2>
					<div class="speccards">
						<?php foreach ( $oria_speccards as $oria_sc ) : ?>
							<?php
							$oria_spec    = $oria_sc['term'];
							$oria_stile   = \Oria\Theme\term_tile( $oria_spec );
							$oria_sparent = \Oria\Theme\specialty_parent( $oria_spec );
							?>
							<a class="speccard" href="<?php echo esc_url( function_exists( '\Oria\Core\PracticesIndex\specialty_url' ) ? \Oria\Core\PracticesIndex\specialty_url( $oria_spec ) : (string) get_term_link( $oria_spec ) ); ?>">
								<?php if ( '' !== $oria_stile ) : ?>
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
									<?php if ( $oria_sc['count'] > 0 ) : ?>
										<span class="speccard__count"><?php printf( esc_html( _n( '%s place', '%s places', (int) $oria_sc['count'], 'oria' ) ), esc_html( number_format_i18n( (int) $oria_sc['count'] ) ) ); ?></span>
									<?php endif; ?>
								</span>
								<span class="speccard__go" aria-hidden="true">&rarr;</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php
	/* --- 4. Claim: unclaimed, ownerless, contactable listings only ----------
	 * The parent's claim form, unchanged. Folded behind a "Claim this page"
	 * button unless the reader arrived at #claim or is mid-claim. */
	?>
	<?php if ( ! $oria_contactless && 'unclaimed' === $oria_status && ! $oria_claimed_by ) : // Free-plan listings have an owner -- don't invite rival claims. ?>
		<?php
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$oria_claim_state = isset( $_GET['oria_claim'] ) ? (string) $_GET['oria_claim'] : '';
		// phpcs:enable
		?>
		<section class="wrap xp-claimwrap" aria-labelledby="xp-claim-title">
			<div class="xp-claim on-deep" id="claim">
				<div class="xp-claim__intro">
					<h2 class="xp-claim__title" id="xp-claim-title"><?php echo esc_html( '' !== $oria_words['claim_head'] ? $oria_words['claim_head'] : __( 'Is this your business?', 'oria' ) ); ?></h2>
					<?php if ( 'received' !== $oria_claim_state ) : ?>
						<p class="xp-claim__text"><?php esc_html_e( 'This listing was built from public information. Request to claim it and, once we\'ve confirmed it\'s you, you can edit every detail yourself.', 'oria' ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( 'received' === $oria_claim_state ) : ?>
					<div class="notice xp-claim__done" role="status" data-oria-event="claim_completed">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>
						<span><b><?php esc_html_e( 'Request received.', 'oria' ); ?></b> <?php esc_html_e( 'We check every claim by hand — you\'ll get an email with your log-in once it\'s approved.', 'oria' ); ?></span>
					</div>
				<?php else : ?>
					<details class="xp-claim__fold"<?php echo 'error' === $oria_claim_state ? ' open' : ''; ?>>
						<summary class="btn btn--light xp-tap xp-claim__open"><?php esc_html_e( 'Claim this page', 'oria' ); ?></summary>
						<div class="xp-claim__form">
							<?php if ( 'error' === $oria_claim_state ) : ?>
								<p class="xp-form__error" role="alert"><?php esc_html_e( 'That didn\'t send — check the name and email and try again.', 'oria' ); ?></p>
							<?php endif; ?>
							<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="xp-form" data-oria-event="claim_started">
								<input type="hidden" name="action" value="oria_claim">
								<input type="hidden" name="listing_id" value="<?php echo (int) $oria_id; ?>">
								<?php wp_nonce_field( 'oria_claim', 'oria_claim_nonce' ); ?>
								<input type="text" name="oria_website_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
								<div class="xp-form__row">
									<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span><input class="input" type="text" name="claimant_name" autocomplete="name" required></label>
									<label class="field"><span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span><input class="input" type="email" name="claimant_email" autocomplete="email" required placeholder="<?php esc_attr_e( 'Ideally the one on your website', 'oria' ); ?>"></label>
								</div>
								<label class="field"><span class="field__label"><?php esc_html_e( 'Phone (optional)', 'oria' ); ?></span><input class="input" type="text" name="claimant_phone" autocomplete="tel"></label>
								<label class="field"><span class="field__label"><?php esc_html_e( 'Anything that helps us verify you', 'oria' ); ?></span><textarea class="textarea" name="claimant_note" style="min-height:70px" placeholder="<?php esc_attr_e( 'e.g. your role, or where we can confirm your details', 'oria' ); ?>"></textarea></label>
								<button class="btn btn--light xp-tap" type="submit"><?php esc_html_e( 'Request to claim', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></button>
							</form>
						</div>
					</details>
					<script>
					/* Arriving at #claim (the share page links here) opens the form. */
					(function () { if (location.hash === '#claim') { var d = document.querySelector('#claim details'); if (d) { d.open = true; } } })();
					</script>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	/* --- 5. Phone: the persistent action bar ---------------------------------
	 * Replaces the parent's scroll-revealed .stickybar: on a phone it is
	 * always there; from 60rem up the sticky rail does the same job and the
	 * bar is not shown. Same tracking attributes as every other button. */
	?>
	<div class="xp-bar" role="group" aria-label="<?php esc_attr_e( 'Quick actions', 'oria' ); ?>">
		<?php if ( $oria_primary ) : ?>
			<a class="btn btn--dark xp-bar__main" href="<?php echo esc_url( $oria_primary['url'] ); ?>" rel="nofollow noopener" target="_blank" data-oria-track="<?php echo esc_attr( $oria_primary['track'] ); ?>" data-oria-id="<?php echo (int) $oria_id; ?>"><?php echo esc_html( $oria_primary['short'] ); ?></a>
		<?php elseif ( $oria_dir_url ) : ?>
			<a class="btn btn--dark xp-bar__main" href="<?php echo esc_url( $oria_dir_url ); ?>" rel="noopener" target="_blank" data-oria-track="dir" data-oria-id="<?php echo (int) $oria_id; ?>"><?php esc_html_e( 'Get directions', 'oria' ); ?></a>
		<?php endif; ?>
		<?php if ( $oria_tel ) : ?>
			<a class="xp-bar__icon" href="tel:<?php echo esc_attr( $oria_tel ); ?>" data-oria-track="tel" data-oria-id="<?php echo (int) $oria_id; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Call %s', 'oria' ), $oria_phone ) ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
			</a>
		<?php endif; ?>
		<button class="xp-bar__icon savebtn" type="button"
			data-save="<?php echo esc_attr( $oria_slugname ); ?>"
			data-save-name="<?php echo esc_attr( $oria_title ); ?>"
			aria-pressed="false">
			<span class="savebtn__on" aria-hidden="true">&#9829;</span><span class="savebtn__off" aria-hidden="true">&#9825;</span>
			<span class="savebtn__label xp-vh"><?php esc_html_e( 'Save', 'oria' ); ?></span>
		</button>
	</div>

</div><!-- .xp-page -->

	<?php $oria_shop = function_exists( '\Oria\Shop\Render\auto_band' ) ? \Oria\Shop\Render\auto_band() : ''; ?>
	<?php if ( $oria_shop ) : ?>
	<section class="wrap section section--top-flush"><?php echo $oria_shop; // phpcs:ignore WordPress.Security.EscapeOutput ?></section>
	<?php endif; ?>
	<?php
endwhile;

get_footer();
