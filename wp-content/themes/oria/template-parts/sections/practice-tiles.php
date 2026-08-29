<?php
/**
 * Section: the categories, grouped into six families.
 *
 * This used to be eight rotating photo tiles drawn from 23 categories in a
 * random order — handsome, but it asked a first-time visitor to recognise
 * "Biohacking & Human Optimisation" as a thing they might want, and it
 * showed a different eight on every load, so nothing could be found twice.
 *
 * Six families (data/families.json) is a menu rather than a filing cabinet.
 * Nothing about the taxonomy changes: each category keeps its own term, URL
 * and live count, so the internal links, the sitemap and the SEO are exactly
 * as they were — only the presentation changes.
 *
 * The ACF heading, eyebrow and aside still come from the section, so the
 * editor keeps the words even though the layout is now code.
 */

declare(strict_types=1);

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

$oria_fams = function_exists( '\Oria\Core\Categories\families' ) ? \Oria\Core\Categories\families() : array();

/*
 * Colour comes from the wants, not from a second palette: each family names
 * one, and the card wears its colour. Chips, card tags and these cards then
 * cannot drift apart — retint a want in goodfor.json and all three follow.
 */
$oria_want_colour = array();
if ( function_exists( '\Oria\Core\GoodFor\labels' ) ) {
	foreach ( \Oria\Core\GoodFor\labels() as $oria_w ) {
		$oria_want_colour[ $oria_w['slug'] ] = $oria_w['color'];
	}
}

// Every category with listings, by slug, so a family can name what it holds.
$oria_by_slug = array();
if ( function_exists( '\Oria\Core\PracticesIndex\practices' ) ) {
	foreach ( \Oria\Core\PracticesIndex\practices() as $oria_term ) {
		$oria_n = function_exists( '\Oria\Core\Intents\listings_in' )
			? count( \Oria\Core\Intents\listings_in( $oria_term ) )
			: (int) $oria_term->count;
		if ( $oria_n > 0 ) {
			$oria_by_slug[ $oria_term->slug ] = array( 'term' => $oria_term, 'n' => $oria_n );
		}
	}
}

/*
 * A category the file forgot still has to be reachable, so it falls into a
 * final "More" group rather than vanishing. Silence here would mean a new
 * category quietly losing its only front-page link.
 */
$oria_filed = array();
foreach ( $oria_fams as $oria_f ) {
	foreach ( $oria_f['cats'] as $oria_c ) {
		$oria_filed[ $oria_c ] = true;
	}
}
$oria_rest = array_keys( array_diff_key( $oria_by_slug, $oria_filed ) );
if ( $oria_rest ) {
	$oria_fams[] = array(
		'slug' => 'more',
		'name' => __( 'More', 'oria' ),
		'line' => __( 'Everything else we list', 'oria' ),
		'cats' => $oria_rest,
	);
}

if ( ! $oria_fams || ! $oria_by_slug ) {
	return;
}

/*
 * One guide and one upcoming event per family, so each card offers
 * something to read and something to turn up to — not just a list of
 * doors. Both are looked up across the family's own categories and both
 * are optional: a family with neither simply shows its categories.
 */
$oria_fam_guide = array();
$oria_fam_event = array();

// Upcoming events, soonest first, indexed by the category they sit under.
$oria_ev_by_cat = array();
$oria_ev_ids    = get_posts(
	array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 60,
		'fields'         => 'ids',
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => array(
			array(
				'key'     => 'event_start',
				'value'   => current_time( 'mysql' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
		),
	)
);
foreach ( $oria_ev_ids as $oria_eid ) {
	if ( ! $oria_eid ) {
		continue; // a broken row can never become a card
	}
	foreach ( wp_get_post_terms( (int) $oria_eid, 'practice' ) as $oria_et ) {
		$oria_ev_by_cat[ $oria_et->slug ][] = (int) $oria_eid;
	}
}

/*
 * Pool everything a family could show, then take one at random per load,
 * so a guide or event that happens to sit behind another still gets its
 * turn. (On production the page cache holds one roll until it is purged —
 * the variety arrives across cache lifetimes, not across refreshes.)
 * Nothing repeats within a render: two cards pointing at the same article
 * is a coincidence that reads as a mistake.
 */
$oria_used_guide = array();
$oria_used_event = array();
foreach ( $oria_fams as $oria_f ) {
	$oria_gpool = array();
	$oria_epool = array();
	foreach ( $oria_f['cats'] as $oria_slug ) {
		if ( ! isset( $oria_by_slug[ $oria_slug ] ) ) {
			continue;
		}
		if ( function_exists( '\Oria\Core\Guides\for_term' ) ) {
			foreach ( \Oria\Core\Guides\for_term( $oria_by_slug[ $oria_slug ]['term'], 6 ) as $oria_g ) {
				$oria_gid = $oria_g instanceof WP_Post ? $oria_g->ID : (int) $oria_g;
				if ( $oria_gid ) {
					$oria_gpool[ $oria_gid ] = true;
				}
			}
		}
		foreach ( (array) ( $oria_ev_by_cat[ $oria_slug ] ?? array() ) as $oria_eid ) {
			$oria_epool[ $oria_eid ] = true;
		}
	}

	$oria_gpool = array_keys( array_diff_key( $oria_gpool, $oria_used_guide ) );
	if ( $oria_gpool ) {
		$oria_pick                         = $oria_gpool[ array_rand( $oria_gpool ) ];
		$oria_fam_guide[ $oria_f['slug'] ] = $oria_pick;
		$oria_used_guide[ $oria_pick ]     = true;
	}

	$oria_epool = array_keys( array_diff_key( $oria_epool, $oria_used_event ) );
	if ( $oria_epool ) {
		$oria_pick                         = $oria_epool[ array_rand( $oria_epool ) ];
		$oria_fam_event[ $oria_f['slug'] ] = $oria_pick;
		$oria_used_event[ $oria_pick ]     = true;
	}
}
?>
<section class="section">
	<div class="wrap">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<?php if ( $t('eyebrow') ) : ?><span class="micro"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
				<h2 class="h2"><?php echo esc_html( $t('heading') ); ?></h2>
			</div>
			<?php if ( $t('aside') ) : ?><p class="sec-head__aside muted"><?php echo esc_html( $t('aside') ); ?></p><?php endif; ?>
		</div>

		<div class="fams">
			<?php foreach ( $oria_fams as $oria_i => $oria_f ) : ?>
				<?php
				$oria_rows = array();
				foreach ( $oria_f['cats'] as $oria_slug ) {
					if ( isset( $oria_by_slug[ $oria_slug ] ) ) {
						$oria_rows[] = $oria_by_slug[ $oria_slug ];
					}
				}
				if ( ! $oria_rows ) {
					continue;
				}
				?>
				<?php $oria_col = $oria_want_colour[ $oria_f['want'] ?? '' ] ?? '#0E3B38'; ?>
				<div class="fam reveal" style="--i:<?php echo (int) $oria_i; ?>;--fam:<?php echo esc_attr( $oria_col ); ?>">
					<div class="fam__cap">
						<h3 class="fam__name">
							<?php if ( ! empty( $oria_f['want'] ) ) : ?>
								<?php // The same generated glyph the want chip wears, so the two are visibly one thing. ?>
								<img class="fam__icon" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/goodfor/' . $oria_f['want'] . '.webp' ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
							<?php endif; ?>
							<?php echo esc_html( $oria_f['name'] ); ?>
						</h3>
						<?php if ( $oria_f['line'] ) : ?>
							<p class="fam__line"><?php echo esc_html( $oria_f['line'] ); ?></p>
						<?php endif; ?>
					</div>
					<ul class="fam__list">
						<?php foreach ( $oria_rows as $oria_r ) : ?>
							<li>
								<a href="<?php echo esc_url( (string) get_term_link( $oria_r['term'] ) ); ?>">
									<span><?php echo esc_html( \Oria\Theme\tname( $oria_r['term'] ) ); ?></span>
									<b><?php echo esc_html( number_format_i18n( $oria_r['n'] ) ); ?></b>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>

					<?php
					$oria_gid = $oria_fam_guide[ $oria_f['slug'] ] ?? 0;
					$oria_eid = $oria_fam_event[ $oria_f['slug'] ] ?? 0;
					?>
					<?php if ( $oria_gid || $oria_eid ) : ?>
						<div class="fam__extras">
							<?php if ( $oria_gid ) : ?>
								<a class="fam__guide" href="<?php echo esc_url( (string) get_permalink( $oria_gid ) ); ?>">
									<?php if ( has_post_thumbnail( $oria_gid ) ) : ?>
										<span class="fam__evimg"><?php echo get_the_post_thumbnail( $oria_gid, 'oria-card', array( 'loading' => 'lazy', 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
									<?php endif; ?>
									<span class="fam__evtext">
										<span class="fam__kicker"><?php esc_html_e( 'Read', 'oria' ); ?></span>
										<span class="fam__gtitle"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_gid ) ) ); ?></span>
									</span>
								</a>
							<?php endif; ?>
							<?php if ( $oria_eid ) : ?>
								<a class="fam__event" href="<?php echo esc_url( (string) get_permalink( $oria_eid ) ); ?>">
									<?php if ( has_post_thumbnail( $oria_eid ) ) : ?>
										<span class="fam__evimg"><?php echo get_the_post_thumbnail( $oria_eid, 'oria-card', array( 'loading' => 'lazy', 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
									<?php endif; ?>
									<span class="fam__evtext">
										<span class="fam__kicker"><?php esc_html_e( 'Coming up', 'oria' ); ?></span>
										<span class="fam__evtitle"><?php echo esc_html( wp_specialchars_decode( (string) get_the_title( $oria_eid ), ENT_QUOTES ) ); ?></span>
									</span>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php
		/*
		 * The close of the categories: the visitor has just seen the whole
		 * breadth of the directory, which is exactly the moment it stops being
		 * obvious which one is theirs.
		 */
		?>
		<?php if ( function_exists( '\Oria\Core\Compare\bootstrap' ) ) : ?>
			<p class="cats__compare reveal">
				<a href="<?php echo esc_url( home_url( '/compare/' ) ); ?>" data-oria-event="home_compare">
					<?php esc_html_e( 'Not sure which is yours? Put them side by side', 'oria' ); ?>
					<span aria-hidden="true">&rarr;</span>
				</a>
			</p>
		<?php endif; ?>
	</div>
</section>
