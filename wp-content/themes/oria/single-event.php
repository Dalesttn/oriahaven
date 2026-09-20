<?php
/**
 * A single event.
 */

declare(strict_types=1);

use function Oria\Theme\arrow;

get_header();

while ( have_posts() ) :
	the_post();
	$oria_start   = (string) get_field( 'event_start' );
	$oria_end     = (string) get_field( 'event_end' );
	$oria_ts      = $oria_start ? strtotime( $oria_start ) : false;
	$oria_te      = $oria_end ? strtotime( $oria_end ) : false;
	$oria_price   = (string) get_field( 'price' );
	$oria_venue   = (string) get_field( 'venue' );
	$oria_booking = (string) get_field( 'booking_url' );
	$oria_listing = get_field( 'listing' );
	$oria_desc    = (string) get_field( 'event_description' );
	$oria_gallery = array_values( array_filter( array_map( 'intval', (array) get_field( 'event_gallery' ) ) ) );

	// The hero: the main photo, or failing that the gallery's lead image.
	$oria_hero_id = has_post_thumbnail() ? (int) get_post_thumbnail_id() : ( $oria_gallery[0] ?? 0 );
	$oria_grid    = array_values( array_diff( $oria_gallery, array( $oria_hero_id ) ) );
	?>

	<section class="wrap pagehead">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/events/' ) ); ?>"><?php esc_html_e( "What's On", 'oria' ); ?></a>
			<span aria-hidden="true">/</span><span><?php the_title(); ?></span>
		</nav>
		<?php
		/*
		 * Finished events keep their page: a recurring series, a host worth
		 * finding, and a link somebody followed all still lead here. What
		 * the page must not do is let it read as upcoming.
		 */
		$oria_over = function_exists( '\Oria\Core\Events\is_past' ) && \Oria\Core\Events\is_past( get_the_ID() );
		if ( $oria_over ) :
			$oria_host_id_for_next = (int) get_field( 'listing' );
			?>
			<p class="evstatus evstatus--over" role="status">
				<b><?php esc_html_e( 'This event has finished.', 'oria' ); ?></b>
				<span>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ) ); ?>"><?php esc_html_e( "See what's on now", 'oria' ); ?></a>
					<?php if ( $oria_host_id_for_next ) : ?>
						· <a href="<?php echo esc_url( get_permalink( $oria_host_id_for_next ) ); ?>"><?php esc_html_e( 'Visit the practice that ran it', 'oria' ); ?></a>
					<?php endif; ?>
				</span>
			</p>
		<?php endif; ?>
		<?php
		$oria_status = function_exists( '\Oria\Core\Events\status' ) ? \Oria\Core\Events\status( get_the_ID() ) : '';
		if ( '' !== $oria_status ) :
			$oria_status_words = array(
				'cancelled' => __( 'This event has been cancelled.', 'oria' ),
				'postponed' => __( 'This event has been postponed.', 'oria' ),
				'sold-out'  => __( 'This event is sold out.', 'oria' ),
			);
			?>
			<p class="evstatus evstatus--<?php echo esc_attr( $oria_status ); ?>" role="status">
				<b><?php echo esc_html( $oria_status_words[ $oria_status ] ?? '' ); ?></b>
				<?php if ( 'sold-out' !== $oria_status ) : ?>
					<span><?php esc_html_e( 'Check with the organiser before making plans around it.', 'oria' ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<div class="row-between" style="align-items:flex-end;margin-top:1rem">
			<div>
				<?php if ( $oria_ts ) : ?>
					<span class="micro"><?php echo esc_html( gmdate( 'l j F Y', $oria_ts ) ); ?></span>
				<?php endif; ?>
				<h1 class="h1 pagehead__title"><?php the_title(); ?></h1>
				<?php
				/*
				 * Each chip is a fact already stored — the price field, the start
				 * and end times, or an audience tag on the linked practice that was
				 * only applied against a source and a quote. The title attribute
				 * carries where it came from, because a claim on somebody else's
				 * event should be able to say why it is there.
				 */
				$oria_signals = function_exists( '\Oria\Ingest\Context\signals' )
					? \Oria\Ingest\Context\signals( get_the_ID() )
					: array();
				if ( $oria_signals ) :
					?>
					<ul class="chips evsignals">
						<?php foreach ( $oria_signals as $oria_sig ) : ?>
							<li><span class="chip" title="<?php echo esc_attr( $oria_sig['why'] ); ?>"><?php echo esc_html( $oria_sig['label'] ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="wrap" style="padding-bottom:var(--s-6)">
		<?php if ( $oria_hero_id ) : ?>
			<div class="evhero"><?php echo wp_get_attachment_image( $oria_hero_id, 'oria-wide' ); ?></div>
			<?php
			/*
			 * Where the picture came from. An event image is the
			 * organiser's, published by them on the page we link to, and
			 * saying so is both the decent thing and the practical one:
			 * anyone who wants it taken down can see at a glance that it
			 * is theirs, and one query finds every image from a source.
			 */
			$oria_img_from = (string) get_post_meta( (int) $oria_hero_id, '_oria_img_origin', true );
			if ( '' !== $oria_img_from ) :
				$oria_img_host = (string) wp_parse_url( $oria_img_from, PHP_URL_HOST );
				?>
				<p class="evhero__credit">
					<?php
					printf(
						/* translators: %s: linked source */
						esc_html__( 'Event image via %s', 'oria' ),
						'<a href="' . esc_url( $oria_img_from ) . '" rel="nofollow noopener" target="_blank">' . esc_html( (string) preg_replace( '/^www\./', '', $oria_img_host ) ) . '</a>'
					);
					?>
				</p>
			<?php endif; ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/event', 'art', array( 'event_id' => get_the_ID() ) ); ?>
		<?php endif; ?>
	</section>

	<section class="wrap section section--top-flush">
		<div class="profile">
			<div class="stack-lg">
				<div class="prose">
					<?php the_content(); ?>
					<?php if ( '' !== trim( $oria_desc ) ) : ?>
						<?php echo wp_kses_post( $oria_desc ); ?>
					<?php endif; ?>
				</div>
				<?php if ( $oria_grid ) : ?>
					<div class="evgallery">
						<?php foreach ( $oria_grid as $oria_gid ) : ?>
							<?php echo wp_get_attachment_image( $oria_gid, 'oria-card', false, array( 'loading' => 'lazy' ) ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<aside class="aside">
				<div class="keyfacts" style="grid-template-columns:1fr 1fr">
					<?php if ( $oria_ts ) : ?>
						<div><div class="keyfact__k"><?php esc_html_e( 'When', 'oria' ); ?></div><div class="keyfact__v"><?php echo esc_html( gmdate( 'D j M, g.ia', $oria_ts ) . ( $oria_te ? '–' . gmdate( 'g.ia', $oria_te ) : '' ) ); ?></div></div>
					<?php endif; ?>
					<?php if ( $oria_price ) : ?>
						<div><div class="keyfact__k"><?php esc_html_e( 'Price', 'oria' ); ?></div><div class="keyfact__v"><?php echo esc_html( $oria_price ); ?></div></div>
					<?php endif; ?>
					<?php if ( $oria_venue ) : ?>
						<div><div class="keyfact__k"><?php esc_html_e( 'Where', 'oria' ); ?></div>
							<div class="keyfact__v">
								<?php echo esc_html( $oria_venue ); ?>
								<?php
								/*
								 * The street, when the organiser published
								 * one and it says more than the venue line
								 * already does.
								 */
								$oria_street = function_exists( '\Oria\Ingest\Enrich\street' ) ? \Oria\Ingest\Enrich\street( (int) get_the_ID() ) : '';
								if ( '' !== $oria_street && false === stripos( $oria_venue, (string) strtok( $oria_street, ',' ) ) ) :
									?>
									<span class="keyfact__street"><?php echo esc_html( (string) preg_replace( '/,\s*Australia$/i', '', $oria_street ) ); ?></span>
								<?php endif; ?>
								<?php
								/*
								 * The suburb as a way in, not just an address.
								 * Its own link, outside the booking link, so
								 * nothing is nested inside anything.
								 */
								$oria_ev_area = function_exists( '\Oria\Core\AreaContext\for_post' )
									? \Oria\Core\AreaContext\for_post( (int) get_the_ID() )
									: null;
								if ( $oria_ev_area ) :
									?>
									<a class="keyfact__area" href="<?php echo esc_url( (string) $oria_ev_area['url'] ); ?>"
										data-area-promo="event-fact" data-area-slug="<?php echo esc_attr( (string) $oria_ev_area['slug'] ); ?>">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: suburb */
												__( '%s neighbourhood guide', 'oria' ),
												(string) $oria_ev_area['name']
											)
										);
										?>
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
					<?php
					/*
					 * Only for events that run longer than a day. Anything
					 * shorter already shows its length as a chip under the
					 * title (Ingest\Context\signals), and saying it twice
					 * on one screen is just noise -- but a weekend retreat
					 * or a four-Saturday course says nothing without this.
					 */
					$oria_duration = ( $oria_ts && $oria_te && $oria_te - $oria_ts > DAY_IN_SECONDS && function_exists( '\Oria\Core\Events\duration' ) )
						? \Oria\Core\Events\duration( get_the_ID() )
						: '';
					if ( '' !== $oria_duration ) :
						?>
						<div><div class="keyfact__k"><?php esc_html_e( 'How long', 'oria' ); ?></div><div class="keyfact__v"><?php echo esc_html( $oria_duration ); ?></div></div>
					<?php endif; ?>
				</div>

				<?php
				/*
				 * What the organiser publishes about tickets, in their own
				 * words and their own numbers. "Pay what you can / $33
				 * concession / $55 general" answers the question "can I
				 * afford this" that a single "from $0" leaves open.
				 *
				 * Every line here was read from the booking page's own
				 * structured data, which is why the block says when. None
				 * of it is inferred, and a tier nobody published is not
				 * shown.
				 */
				$oria_tiers  = function_exists( '\Oria\Ingest\Enrich\tiers' ) ? \Oria\Ingest\Enrich\tiers( (int) get_the_ID() ) : array();
				$oria_avail  = function_exists( '\Oria\Ingest\Enrich\availability' ) ? \Oria\Ingest\Enrich\availability( (int) get_the_ID() ) : '';
				$oria_e_when = function_exists( '\Oria\Ingest\Enrich\checked_on' ) ? \Oria\Ingest\Enrich\checked_on( (int) get_the_ID() ) : '';
				?>
				<?php if ( $oria_tiers && ! $oria_over ) : ?>
					<div class="evtiers">
						<p class="evtiers__head"><?php esc_html_e( 'Ticket options', 'oria' ); ?></p>
						<ul class="evtiers__list">
							<?php foreach ( $oria_tiers as $oria_tier ) : ?>
								<li>
									<span class="evtiers__name"><?php echo esc_html( (string) $oria_tier['name'] ); ?></span>
									<span class="evtiers__price">
										<?php
										$oria_money = static function ( float $n ): string {
											return $n > 0
												? '$' . rtrim( rtrim( number_format( $n, 2, '.', '' ), '0' ), '.' )
												: __( 'free', 'oria' );
										};
										$oria_lo    = (float) $oria_tier['price'];
										$oria_hi    = (float) ( $oria_tier['high'] ?? $oria_tier['price'] );
										echo esc_html( $oria_hi > $oria_lo ? $oria_money( $oria_lo ) . '–' . $oria_money( $oria_hi ) : $oria_money( $oria_lo ) );
										?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php if ( '' !== $oria_e_when ) : ?>
							<p class="evtiers__note">
								<?php
								printf(
									/* translators: %s: date */
									esc_html__( 'From the organiser’s booking page, read %s. Prices are theirs to change.', 'oria' ),
									esc_html( $oria_e_when )
								);
								?>
							</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( 'SoldOut' === $oria_avail && ! $oria_over ) : ?>
					<p class="evsold"><?php esc_html_e( 'Sold out when we last checked.', 'oria' ); ?></p>
				<?php endif; ?>

				<?php if ( $oria_booking ) : ?>
					<a class="btn btn--dark btn--block" href="<?php echo esc_url( $oria_booking ); ?>" rel="nofollow noopener" target="_blank"
						data-oria-track="book" data-oria-id="<?php echo (int) get_the_ID(); ?>"><?php esc_html_e( 'Book / details', 'oria' ); ?><?php echo arrow(); // phpcs:ignore ?></a>
				<?php endif; ?>

				<?php
				/*
				 * Keep it, or put it in the calendar you already use. The
				 * .ics is a plain file rather than a row of Google/Apple/
				 * Outlook buttons: it works on every device, hands nothing
				 * to a third party, and suits the person whose calendar is
				 * none of those three. Both controls are hidden once the
				 * event is over -- there is nothing to plan for.
				 */
				if ( ! $oria_over ) :
					?>
					<div class="evactions">
						<?php if ( $oria_ts && function_exists( '\Oria\Core\EventIcs\url' ) ) : ?>
							<a class="btn btn--sm" href="<?php echo esc_url( \Oria\Core\EventIcs\url( (int) get_the_ID() ) ); ?>"
								data-oria-track="cal" data-oria-id="<?php echo (int) get_the_ID(); ?>">
								<?php esc_html_e( 'Add to calendar', 'oria' ); ?>
							</a>
						<?php endif; ?>
						<button class="btn btn--sm savebtn" type="button" aria-pressed="false"
							data-save-event="<?php echo (int) get_the_ID(); ?>"
							data-title="<?php echo esc_attr( wp_specialchars_decode( get_the_title(), ENT_QUOTES ) ); ?>"
							data-url="<?php echo esc_url( (string) get_permalink() ); ?>"
							data-when="<?php echo esc_attr( $oria_ts ? gmdate( 'D j M · g.ia', $oria_ts ) : '' ); ?>"
							data-where="<?php echo esc_attr( $oria_venue ); ?>">
							<span class="savebtn__label"><?php esc_html_e( 'Save', 'oria' ); ?></span>
						</button>
						<?php
						/*
						 * The kit, for whoever is running this. Offered on the
						 * page rather than only by email, because the person
						 * most likely to promote an event is looking at it.
						 */
						if ( function_exists( '\Oria\Core\EventShare\url' ) ) :
							?>
							<a class="btn btn--sm" href="<?php echo esc_url( \Oria\Core\EventShare\url( (int) get_the_ID() ) ); ?>"><?php esc_html_e( 'Share', 'oria' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php
				/*
				 * Who runs this: the linked listing as a small card back into
				 * the directory.
				 */
				if ( $oria_listing ) :
					$oria_host_id  = (int) $oria_listing;
					$oria_host_img = \Oria\Theme\listing_image( $oria_host_id );
					$oria_host_bits = array();
					foreach ( wp_get_post_terms( $oria_host_id, 'area' ) as $oria_ht ) {
						if ( $oria_ht->parent ) {
							$oria_host_bits[] = \Oria\Theme\tname( $oria_ht );
							break;
						}
					}
					$oria_host_p = wp_get_post_terms( $oria_host_id, 'practice' );
					if ( ! is_wp_error( $oria_host_p ) && $oria_host_p ) {
						$oria_host_bits[] = \Oria\Theme\tname( $oria_host_p[0] );
					}
					?>
					<a class="hostcard" href="<?php echo esc_url( get_permalink( $oria_host_id ) ); ?>">
						<?php if ( $oria_host_img ) : ?>
							<img class="hostcard__img" src="<?php echo esc_url( $oria_host_img ); ?>" alt="<?php echo esc_attr( \Oria\Theme\ptitle( get_post( $oria_host_id ) ) ); ?>" loading="lazy" onerror="this.style.display='none'">
						<?php endif; ?>
						<span class="hostcard__body">
							<span class="micro"><?php esc_html_e( 'Run by', 'oria' ); ?></span>
							<b class="hostcard__name"><?php echo esc_html( \Oria\Theme\ptitle( $oria_host_id ) ); ?></b>
							<?php if ( $oria_host_bits ) : ?>
								<span class="hostcard__meta"><?php echo esc_html( implode( ' · ', $oria_host_bits ) ); ?></span>
							<?php endif; ?>
						</span>
						<span class="hostcard__go" aria-hidden="true"><?php echo arrow(); // phpcs:ignore ?></span>
					</a>
					<?php
					// Only offered when the host really has others coming up.
					$oria_host_more = function_exists( '\Oria\Core\Events\for_listing' )
						? \Oria\Core\Events\for_listing( $oria_host_id, 4 )
						: array();
					$oria_host_more = array_values( array_diff( $oria_host_more, array( get_the_ID() ) ) );
					if ( $oria_host_more ) :
						?>
						<p class="evaside__link">
							<a href="<?php echo esc_url( get_permalink( $oria_host_id ) . '#events' ); ?>">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of other events */
										_n( '%d more event from this host', '%d more events from this host', count( $oria_host_more ), 'oria' ),
										count( $oria_host_more )
									)
								);
								?>
							</a>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<?php
					/*
					 * No linked practice. Say who runs it if the source told
					 * us, and invite them to connect it -- without implying
					 * they already own anything here.
					 */
					$oria_org = (string) get_post_meta( get_the_ID(), '_oria_organiser', true );
					?>
					<div class="hostcard hostcard--plain">
						<span class="hostcard__body">
							<span class="micro"><?php esc_html_e( 'Run by', 'oria' ); ?></span>
							<b class="hostcard__name"><?php echo esc_html( '' !== $oria_org ? $oria_org : __( 'Not listed with us yet', 'oria' ) ); ?></b>
							<span class="hostcard__meta">
								<a href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'Are you the organiser? Connect your practice.', 'oria' ); ?></a>
							</span>
						</span>
					</div>
				<?php endif; ?>

				<?php
				/*
				 * Where these details came from and when they were last
				 * checked. A directory that repeats somebody else's times
				 * owes the reader both, and a way to tell us when they are
				 * wrong. The source stays the source of truth.
				 */
				$oria_src_info = function_exists( '\Oria\Core\Events\source' ) ? \Oria\Core\Events\source( get_the_ID() ) : null;
				$oria_checked  = function_exists( '\Oria\Core\Events\verified' ) ? \Oria\Core\Events\verified( get_the_ID() ) : 0;
				if ( $oria_src_info || $oria_checked ) :
					?>
					<div class="evsource">
						<?php if ( $oria_src_info ) : ?>
							<p>
								<?php esc_html_e( 'Details from', 'oria' ); ?>
								<a href="<?php echo esc_url( $oria_src_info['url'] ); ?>" rel="nofollow noopener" target="_blank"><?php echo esc_html( $oria_src_info['host'] ); ?></a>
							</p>
						<?php endif; ?>
						<?php if ( $oria_checked ) : ?>
							<p><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Last checked %s', 'oria' ), gmdate( 'j F Y', $oria_checked ) ) ); ?></p>
						<?php endif; ?>
						<p>
							<a href="<?php echo esc_url( home_url( '/about/#oform-contact' ) ); ?>"><?php esc_html_e( 'Report incorrect information', 'oria' ); ?></a>
						</p>
					</div>
				<?php endif; ?>
			</aside>
		</div>
	</section>
	<?php
	/*
	 * What else is on. Ranked rather than filtered — same type scores
	 * highest, then venue, then host — so a night with nothing of the same
	 * kind still offers something rather than an empty heading.
	 */
	/*
	 * Related, in the order the brief asks for: this host's other events
	 * first -- someone who liked the look of this one most often wants
	 * another from the same people -- then the ranked similar events.
	 */
	$oria_similar = function_exists( '\Oria\Ingest\Context\similar' )
		? \Oria\Ingest\Context\similar( get_the_ID(), 4 )
		: array();
	if ( $oria_listing && function_exists( '\Oria\Core\Events\for_listing' ) ) {
		$oria_host_events = array_diff( \Oria\Core\Events\for_listing( (int) $oria_listing, 4 ), array( get_the_ID() ) );
		$oria_similar     = array_slice( array_unique( array_merge( $oria_host_events, $oria_similar ) ), 0, 4 );
	}
	/*
	 * Make a day of it: the neighbourhood, before the Perth-wide list.
	 * Somebody who has just read one Fremantle event is closer to wanting
	 * Fremantle than to wanting Perth.
	 */
	$oria_day_area = function_exists( '\Oria\Core\AreaContext\for_post' )
		? \Oria\Core\AreaContext\for_post( (int) get_the_ID() )
		: null;

	// The card is for people who can still go; the ordering below it
	// helps either way, so the area is resolved before this gate.
	if ( ! $oria_over ) {
		if ( $oria_day_area ) {
			echo '<section class="wrap section section--top-flush">';
			get_template_part(
				'template-parts/area/area-card',
				null,
				array(
					'area'    => $oria_day_area,
					'eyebrow' => __( 'Make a day of it', 'oria' ),
					'cta'     => sprintf( /* translators: %s: suburb */ __( 'Explore %s', 'oria' ), (string) $oria_day_area['name'] ),
					'source'  => 'event-day',
					// A way out of the page, not a second hero for it.
					'variant' => 'band',
				)
			);
			echo '</section>';
		}
	}

	/*
	 * Near first, then the rest of the city. "Also on in Perth" answered
	 * a question nobody asked -- somebody reading a Fremantle event is
	 * closer to wanting Fremantle than to wanting Perth. When the suburb
	 * itself has nothing else on, the region's other suburbs are tried
	 * before falling back, so the heading is never over an empty grid.
	 */
	$oria_near = array();
	if ( ! empty( $oria_day_area ) && function_exists( '\Oria\Core\Events\for_area' ) ) {
		$oria_near = array_diff(
			\Oria\Core\Events\for_area( (string) $oria_day_area['slug'], 4 ),
			array( get_the_ID() )
		);
		if ( count( $oria_near ) < 2 && function_exists( '\Oria\Core\AreaContext\nearby' ) ) {
			foreach ( \Oria\Core\AreaContext\nearby( $oria_day_area['term'], 4 ) as $oria_nb ) {
				$oria_near = array_merge( $oria_near, \Oria\Core\Events\for_area( (string) $oria_nb['slug'], 2 ) );
				if ( count( $oria_near ) >= 3 ) {
					break;
				}
			}
			$oria_near = array_diff( $oria_near, array( get_the_ID() ) );
		}
		$oria_near = array_slice( array_unique( $oria_near ), 0, 3 );
	}

	// Nothing appears twice: what is near is not also "across Perth".
	$oria_similar = array_slice( array_values( array_diff( $oria_similar, $oria_near ) ), 0, 4 );

	$oria_groups = array();
	if ( $oria_near ) {
		$oria_groups[] = array(
			/* translators: %s: suburb */
			'heading' => sprintf( __( 'Also on near %s', 'oria' ), (string) $oria_day_area['name'] ),
			'ids'     => $oria_near,
		);
	}
	if ( $oria_similar ) {
		$oria_groups[] = array(
			'heading' => $oria_near ? __( 'More events across Perth', 'oria' ) : __( 'Also on in Perth', 'oria' ),
			'ids'     => $oria_similar,
		);
	}

	foreach ( $oria_groups as $oria_group ) :
		?>
	<section class="wrap section section--top-flush">
		<h2 class="micro" style="margin-bottom:1rem"><?php echo esc_html( $oria_group['heading'] ); ?></h2>
		<div class="evgrid">
			<?php
			foreach ( $oria_group['ids'] as $oria_sid ) :
				$oria_when = (string) get_field( 'event_start', $oria_sid );
				$oria_where = (string) get_field( 'venue', $oria_sid );
				$oria_types = wp_get_post_terms( $oria_sid, 'event_type' );
				?>
				<a class="evcard" href="<?php echo esc_url( (string) get_permalink( $oria_sid ) ); ?>">
					<?php if ( $oria_when ) : ?>
						<span class="micro"><?php echo esc_html( date_i18n( 'D j M', strtotime( $oria_when ) ) ); ?></span>
					<?php endif; ?>
					<b class="evcard__name"><?php echo esc_html( \Oria\Theme\ptitle( $oria_sid ) ); ?></b>
					<span class="evcard__meta">
						<?php
						$oria_bits = array();
						if ( ! is_wp_error( $oria_types ) && $oria_types ) {
							$oria_bits[] = \Oria\Theme\tname( $oria_types[0] );
						}
						if ( $oria_where ) {
							$oria_bits[] = $oria_where;
						}
						echo esc_html( implode( ' · ', $oria_bits ) );
						?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
		<?php
	endforeach;

	/*
	 * The page's own id, for the view counter and the analytics layer.
	 * Printed rather than inferred, so a page served from the cache still
	 * reports itself -- the same reason listing views moved to a beacon.
	 */
	printf(
		'<script>window.ORIA_EVENT=%s;</script>',
		wp_json_encode( array( 'id' => get_the_ID() ) )
	);
endwhile;

get_footer();
