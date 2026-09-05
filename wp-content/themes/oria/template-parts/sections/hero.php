<?php
/**
 * Section: full-screen hero
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

$oria_practices = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false ) );
$oria_practices = is_wp_error( $oria_practices ) ? array() : $oria_practices;
?>
<section class="hero">
	<?php // Backdrop behind the headline: decorative, so an empty alt is correct — a screen reader should not announce it. ?>
	<div class="hero__bg" aria-hidden="true"><img src="<?php echo esc_url( simg( $s, 'image', 'hero-meditation.webp' ) ); ?>" alt="" fetchpriority="high" decoding="async"></div>
	<div class="hero__inner on-deep">
		<?php if ( $t('eyebrow') ) : ?><span class="hero__eyebrow pill pill--glass"><?php echo esc_html( $t('eyebrow') ); ?></span><?php endif; ?>
		<h1 class="display hero__title"><?php echo esc_html( $t('heading') ?: \Oria\Theme\ptitle() ); ?></h1>
		<?php if ( $t('sub') ) : ?><p class="lede hero__sub"><?php echo esc_html( $t('sub') ); ?></p><?php endif; ?>

		<?php if ( ! empty( $s['show_search'] ) ) : ?>
		<form class="searchbar" id="heroSearch" role="search" aria-label="<?php esc_attr_e( 'Find a practice', 'oria' ); ?>">
			<div class="searchbar__cell">
				<svg class="searchbar__icon" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="5.5"/><path d="M12.2 12.2 16 16"/></svg>
				<span class="searchbar__field">
					<label for="heroCat"><?php esc_html_e( "What you're after", 'oria' ); ?></label>
					<input id="heroCat" type="text" autocomplete="off"
						placeholder="<?php esc_attr_e( 'Cryotherapy, pilates, reiki…', 'oria' ); ?>"
						data-oria-search role="combobox" aria-autocomplete="list" aria-expanded="false"
						aria-controls="heroCatList">
					<span class="osearch" id="heroCatList" data-oria-search-panel hidden></span>
				</span>
			</div>
			<div class="searchbar__cell">
				<svg class="searchbar__icon" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 16.5s5.5-4.7 5.5-9a5.5 5.5 0 0 0-11 0c0 4.3 5.5 9 5.5 9Z"/><circle cx="9" cy="7.2" r="2"/></svg>
				<span class="searchbar__field">
					<label for="heroWhere"><?php esc_html_e( 'Where', 'oria' ); ?></label>
					<input id="heroWhere" type="text" placeholder="<?php esc_attr_e( 'Suburb, e.g. Fremantle', 'oria' ); ?>" autocomplete="off">
				</span>
			</div>
			<div class="searchbar__go">
				<button class="btn btn--dark" type="submit"><?php esc_html_e( 'Search', 'oria' ); ?><span class="btn__dot"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h10M8 3l4 4-4 4"/></svg></span></button>
			</div>
		</form>
		<?php endif; ?>

		<?php
		/*
		 * The want row, the same one the directory carries — the homepage's
		 * answer to "I don't know what it's called, I know what I'm after".
		 * Links rather than filter toggles: there is no results engine on
		 * this page, and a crawlable link is worth more here anyway.
		 */
		get_template_part( 'template-parts/directory', 'goodfor', array( 'links' => true, 'class' => 'goodfor--hero' ) );
		?>

		<?php
		$oria_tags = srows( $s, 'tags' );

		/*
		 * "Popular right now", meaning it.
		 *
		 * These pills were whatever an editor last typed into the repeater,
		 * which is a claim about popularity with nothing behind it. Ranked
		 * intents replace them once enough people have actually chosen one.
		 * IntentStats holds a floor, so a pill on three clicks never makes
		 * it and the editorial list stays.
		 *
		 * Not either/or: the ranking fills what it can and the typed rows
		 * top up the rest, so the row is never short and never padded with
		 * noise.
		 */
		if ( function_exists( '\Oria\Core\Intents\popular' ) ) {
			$oria_ranked = \Oria\Core\Intents\popular( 5 );

			if ( $oria_ranked ) {
				$oria_topup = array_slice( $oria_tags, 0, max( 0, 5 - count( $oria_ranked ) ) );

				$oria_tags = array_merge(
					array_map(
						static fn( array $r ): array => array(
							'label' => $r['label'],
							'url'   => $r['url'],
						),
						$oria_ranked
					),
					$oria_topup
				);
			}
		}

		// Ratings card: shown unless the editor turns it off. Pages saved
		// before the toggle existed have no key at all — treat that as on.
		$oria_show_trust = array_key_exists( 'show_trust', $s ) ? (bool) $s['show_trust'] : true;

		// The average is live: the mean rating across every listing that has
		// one, so the number can never drift from the directory it fronts.
		$oria_avg = 0.0;
		if ( $oria_show_trust ) {
			$oria_rated = array_filter(
				array_column( \Oria\Theme\listing_data()['listings'], 'rating' ),
				static fn( $r ): bool => (float) $r > 0
			);
			$oria_avg   = $oria_rated ? round( array_sum( $oria_rated ) / count( $oria_rated ), 1 ) : 0.0;
		}

		$oria_trust_text = $t( 'trust_text' ) ?: __( 'Across every practice listed here. We read the reviews before a listing goes live.', 'oria' );

		// Featured listings grouped by suburb: the hero's rotating local
		// spotlight ("Featured in Claremont"), a new area every 30 seconds.
		$oria_feat_groups = array();
		foreach ( \Oria\Theme\featured_listings( 24 ) as $oria_fp ) {
			$oria_ft = null;
			foreach ( wp_get_post_terms( $oria_fp->ID, 'area' ) as $oria_at ) {
				if ( $oria_at->parent ) {
					$oria_ft = $oria_at;
					break;
				}
				$oria_ft = $oria_ft ?: $oria_at;
			}
			if ( $oria_ft instanceof WP_Term ) {
				$oria_feat_groups[ $oria_ft->term_id ]['term']    = $oria_ft;
				$oria_feat_groups[ $oria_ft->term_id ]['posts'][] = $oria_fp;
			}
		}
		$oria_feat_groups = array_values( $oria_feat_groups );
		shuffle( $oria_feat_groups );
		$oria_feat_groups = array_slice( $oria_feat_groups, 0, 8 );

		// Two side-by-side cards, dealt alternate areas; the second starts
		// half a cycle later so the hero never blinks both at once.
		$oria_feat_cards = array();
		foreach ( $oria_feat_groups as $oria_gi => $oria_g ) {
			$oria_feat_cards[ $oria_gi % 2 ][] = $oria_g;
		}
		?>
		<?php if ( $oria_tags || ( $oria_show_trust && $oria_avg > 0 ) || $oria_feat_cards ) : ?>
		<div class="hero__foot">
			<div class="hero__tags">
				<?php if ( $oria_tags ) : ?>
					<span class="micro" style="margin-right:.35rem"><?php esc_html_e( 'Popular right now', 'oria' ); ?></span>
					<?php foreach ( $oria_tags as $oria_tag ) : ?>
						<?php $oria_tag_url = (string) ( $oria_tag['url'] ?? '#' ); ?>
						<?php $oria_tag_url = function_exists( '\Oria\Core\PracticesIndex\rewrite_url' ) ? ( \Oria\Core\PracticesIndex\rewrite_url( $oria_tag_url ) ?: $oria_tag_url ) : $oria_tag_url; ?>
						<a class="pill pill--glass" href="<?php echo esc_url( $oria_tag_url ); ?>"><?php echo esc_html( (string) ( $oria_tag['label'] ?? '' ) ); ?></a>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="hero__cards">
			<?php foreach ( $oria_feat_cards as $oria_ci => $oria_card_groups ) : ?>
			<div class="glass herofeat featrotator" data-rotate="30000" data-offset="<?php echo (int) ( $oria_ci * 15000 ); ?>">
				<?php foreach ( $oria_card_groups as $oria_gi => $oria_g ) : ?>
				<div class="featrotator__group<?php echo 0 === $oria_gi ? ' is-active' : ''; ?>"<?php echo 0 !== $oria_gi ? ' hidden' : ''; ?>>
					<p class="herofeat__head micro"><span class="badge-dot" aria-hidden="true"></span><?php printf( esc_html__( 'Featured in %s', 'oria' ), esc_html( \Oria\Theme\tname( $oria_g['term'] ) ) ); ?></p>
					<?php foreach ( array_slice( $oria_g['posts'], 0, 2 ) as $oria_fp ) : ?>
						<?php $oria_fpr = wp_get_post_terms( $oria_fp->ID, 'practice' ); ?>
						<a class="herofeat__row" href="<?php echo esc_url( get_permalink( $oria_fp ) ); ?>">
							<img src="<?php echo esc_url( \Oria\Theme\listing_image( $oria_fp->ID ) ); ?>" alt="<?php echo esc_attr( \Oria\Theme\listing_alt( $oria_fp->ID ) ); ?>" loading="lazy" onerror="this.onerror=null;this.src='<?php echo esc_js( \Oria\Theme\listing_scene( $oria_fp->ID ) ); ?>'">
							<span>
								<b><?php echo esc_html( \Oria\Theme\ptitle( $oria_fp ) ); ?></b>
								<em><?php echo esc_html( ! is_wp_error( $oria_fpr ) && $oria_fpr ? \Oria\Theme\tname( $oria_fpr[0] ) : '' ); ?></em>
							</span>
						</a>
					<?php endforeach; ?>
					<a class="herofeat__more" href="<?php echo esc_url( (string) get_term_link( $oria_g['term'] ) ); ?>"><?php printf( esc_html__( 'Explore %s', 'oria' ), esc_html( \Oria\Theme\tname( $oria_g['term'] ) ) ); ?> &rarr;</a>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>

			<?php
			/*
			 * The match card: the trust card's slot now sells the matching
			 * service instead — the 4.8-average line moves into the card's
			 * body copy so the social proof isn't lost, just demoted. Only
			 * offered when the leads module is live to receive the request.
			 * The button opens the desktop dialog; on mobile (or without
			 * JS) it falls through to the in-page band at #enquire.
			 */
			?>
			<?php if ( $oria_show_trust ) : ?>
			<?php
			/*
			 * Oria, above the fold.
			 *
			 * This slot used to hold the "free matching service" card, whose
			 * button was the ONLY [data-match-open] trigger on the page -- and
			 * .matchband-section is display:none above 901px, so removing the
			 * card without doing anything else would have left the lead form
			 * unreachable on desktop. The band is now shown at every width
			 * instead (see pages.css), which demotes that funnel down the page
			 * rather than deleting it.
			 *
			 * A plain GET form, exactly as on the front page band: it carries
			 * the sentence to /ask/ and does no reading of its own, so nobody
			 * can spend their three daily readings before they arrive.
			 */
			?>
			<div class="glass trustcard hero__trust askcard">
				<div class="trustcard__top">
					<span class="micro"><?php esc_html_e( 'Ask Oria', 'oria' ); ?></span>
					<span class="pill pill--glass"><?php esc_html_e( 'Checked by hand', 'oria' ); ?></span>
				</div>

				<div class="askcard__head">
					<?php get_template_part( 'template-parts/oria-orb', null, array( 'uid' => 'hero' ) ); ?>
					<p class="askcard__title"><?php esc_html_e( 'Not sure what to search for?', 'oria' ); ?></p>
				</div>

				<p>
					<?php
					echo esc_html(
						$oria_avg > 0
							/* translators: %s: average Google rating across the directory. */
							? sprintf( __( 'Describe it and we will find real places, rated %s by their own Google reviewers.', 'oria' ), number_format_i18n( $oria_avg, 1 ) )
							: __( 'Describe it and we will find real places across Perth.', 'oria' )
					);
					?>
				</p>

				<form class="askcard__form" action="<?php echo esc_url( home_url( '/ask/' ) ); ?>" method="get">
					<label class="sr-only" for="askcard-q"><?php esc_html_e( 'Describe what you are looking for', 'oria' ); ?></label>
					<input class="askcard__input" type="text" id="askcard-q" name="q" maxlength="400" autocomplete="off"
						placeholder="<?php esc_attr_e( 'Quiet, hands-on, near the city…', 'oria' ); ?>">
					<button class="askcard__go" type="submit" aria-label="<?php esc_attr_e( 'Ask Oria', 'oria' ); ?>">
						<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
							<path d="M3 10h13M11 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.9"
								stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</button>
				</form>
			</div>
			<?php elseif ( $oria_show_trust && $oria_avg > 0 ) : ?>
			<div class="glass trustcard hero__trust">
				<div class="trustcard__top">
					<div class="faces" aria-hidden="true">
						<span style="background:#2A6155">JM</span>
						<span style="background:#4E7F70">TS</span>
						<span style="background:#8C8574">RK</span>
						<span style="background:#16544E">+</span>
					</div>
					<span class="pill pill--glass"><?php esc_html_e( 'Checked by hand', 'oria' ); ?></span>
				</div>
				<div class="trustcard__score">
					<b><?php echo esc_html( number_format_i18n( $oria_avg, 1 ) ); ?></b>
					<span>
						<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor" style="width:15px;height:15px"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>
						<span class="micro" style="display:block;margin-top:2px"><?php esc_html_e( 'Average', 'oria' ); ?></span>
					</span>
				</div>
				<p><?php echo esc_html( $oria_trust_text ); ?></p>
			</div>
			<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
</section>
