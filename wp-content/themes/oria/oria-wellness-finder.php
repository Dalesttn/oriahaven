<?php
/**
 * The Wellness Finder.
 *
 * Rendered two ways from one template. Unanswered, it is the questionnaire
 * plus the writing that gives the page something to rank for. Answered, it
 * is the results — and because the answers travel in the query string, a
 * set of results is a real URL someone can bookmark or send to a friend.
 *
 * The wizard is progressive enhancement, not a requirement. Without
 * JavaScript every question is on the page at once and the form submits
 * normally; with it, app.js folds the same markup into one-question-at-a-
 * time with a progress bar. Nobody gets a broken page, and there is no
 * second implementation to keep in step.
 *
 * @package Oria
 */

declare(strict_types=1);

use function Oria\Theme\arrow;
use function Oria\Theme\tname;

$oria_answers   = \Oria\Core\Finder\answers();
$oria_questions = \Oria\Core\Finder\questions();
$oria_showing   = \Oria\Core\Finder\answered();
$oria_results   = $oria_showing ? \Oria\Core\Finder\results( $oria_answers ) : array();

get_header();
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<span><?php esc_html_e( 'Wellness Finder', 'oria' ); ?></span>
	</nav>
	<span class="micro"><?php esc_html_e( 'Free tool', 'oria' ); ?></span>
	<h1 class="h1 pagehead__title">
		<?php echo esc_html( $oria_showing ? __( 'Here\'s what we\'d look at.', 'oria' ) : __( 'Not sure where to start?', 'oria' ) ); ?>
	</h1>
	<p class="lede pagehead__lede">
		<?php
		echo esc_html(
			$oria_showing
				? __( 'Drawn from practices we\'ve checked by hand. Nothing here is sponsored, and we never take a cut of a booking.', 'oria' )
				: __( 'Four questions, about a minute. We\'ll show you the practices, people and events in Perth that fit — from a directory checked by hand, not an algorithm guessing.', 'oria' )
		);
		?>
	</p>
</section>

<?php if ( ! $oria_showing ) : ?>

	<!-- ------------------------------------------------------ questionnaire -->
	<section class="wrap section section--top-flush">
		<form class="finder" method="get" action="<?php echo esc_url( \Oria\Core\Finder\url() ); ?>" data-finder data-oria-event="finder_start">
			<div class="finder__progress" data-finder-progress hidden>
				<div class="finder__progress__bar"><span data-finder-fill></span></div>
				<span class="finder__progress__label" data-finder-count></span>
			</div>

			<?php $oria_i = 0; ?>
			<?php foreach ( $oria_questions as $oria_key => $oria_q ) : ?>
				<?php $oria_i++; ?>
				<fieldset class="finder__step" data-finder-step="<?php echo (int) $oria_i; ?>">
					<legend class="finder__q">
						<span class="finder__q__num"><?php echo esc_html( sprintf( '%02d', $oria_i ) ); ?></span>
						<span>
							<span class="finder__q__label"><?php echo esc_html( $oria_q['label'] ); ?></span>
							<span class="finder__q__hint"><?php echo esc_html( $oria_q['hint'] ); ?></span>
						</span>
					</legend>

					<div class="finder__options">
						<?php foreach ( $oria_q['options'] as $oria_val => $oria_label ) : ?>
							<label class="finder__opt">
								<input
									type="radio"
									name="<?php echo esc_attr( $oria_key ); ?>"
									value="<?php echo esc_attr( $oria_val ); ?>"
									<?php checked( $oria_answers[ $oria_key ] ?? '', $oria_val ); ?>
								>
								<span class="finder__opt__face"><?php echo esc_html( $oria_label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>

					<?php // btn--plain: no arrow dot, so no right-hand gap to reserve for one. ?>
					<div class="finder__nav" data-finder-nav hidden>
						<button class="btn btn--ghost btn--sm btn--plain" type="button" data-finder-back><?php esc_html_e( 'Back', 'oria' ); ?></button>
						<button class="btn btn--ghost btn--sm btn--plain" type="button" data-finder-skip><?php esc_html_e( 'Skip', 'oria' ); ?></button>
					</div>
				</fieldset>
			<?php endforeach; ?>

			<div class="finder__submit">
				<button class="btn btn--dark" type="submit"><?php esc_html_e( 'Show me', 'oria' ); ?><?php echo arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<p class="hint"><?php esc_html_e( 'No sign-up, no email needed. We don\'t keep your answers.', 'oria' ); ?></p>
			</div>
		</form>
	</section>

	<!-- ------------------------------------------------------------ content -->
	<section class="wrap section">
		<div class="prose" style="max-width:60ch">
			<h2><?php esc_html_e( 'How the Wellness Finder works', 'oria' ); ?></h2>
			<p><?php esc_html_e( 'It doesn\'t guess. Every suggestion is a practice, event or article already in the Oria Haven directory, matched against your answers on what you\'re after, how you like to do things and where you are. Practices we\'ve checked by hand, with real prices and real contact details.', 'oria' ); ?></p>
			<p><?php esc_html_e( 'Nothing is sponsored. A practice cannot pay to appear in your results, and where two are an equally good fit we simply show the one that keeps its listing up to date first.', 'oria' ); ?></p>

			<h2><?php esc_html_e( 'How to choose a wellness practice', 'oria' ); ?></h2>
			<p><?php esc_html_e( 'Most people over-think the first booking. The honest advice is to pick the thing you\'ll actually turn up to: something close enough to get to on a bad week, at a price you won\'t resent, in a format that suits you. A class you enjoy beats a discipline you admire.', 'oria' ); ?></p>
			<p><?php esc_html_e( 'It\'s also worth knowing that a first session is often not typical. Practitioners spend it asking questions and finding out what you want. If you\'re unsure after one visit, that\'s normal — two or three is a fairer test.', 'oria' ); ?></p>
			<p><?php esc_html_e( 'If you have a health concern, start with your GP or a registered practitioner. Some practices here are delivered by AHPRA-registered professionals and some are not; each listing says what it is, and it\'s always fair to ask about training and qualifications before you book.', 'oria' ); ?></p>

			<h2><?php esc_html_e( 'What\'s available in Perth', 'oria' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: number of listings, 2: number of categories */
					esc_html__( 'The directory holds %1$d practices across %2$d categories, from meditation and yoga to remedial massage, breathwork, sound and float, allied health and outdoor wellness — spread from Fremantle to the Hills and the northern suburbs.', 'oria' ),
					(int) wp_count_posts( 'listing' )->publish,
					(int) wp_count_terms( array( 'taxonomy' => 'practice', 'hide_empty' => true ) )
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( home_url( '/perth/' ) ); ?>" style="text-decoration:underline;text-underline-offset:3px"><?php esc_html_e( 'Browse everything by practice, modality or suburb', 'oria' ); ?></a>
				<?php esc_html_e( ' — or answer the four questions above and let the Finder narrow it down.', 'oria' ); ?>
			</p>
		</div>
	</section>

<?php else : ?>

	<!-- ------------------------------------------------------------ results -->
	<section class="wrap section section--top-flush section--bottom-flush">
		<div class="finder__recap">
			<?php
			foreach ( $oria_answers as $oria_key => $oria_val ) :
				$oria_label = $oria_questions[ $oria_key ]['options'][ $oria_val ] ?? '';
				if ( ! $oria_label ) {
					continue;
				}
				?>
				<span class="pill pill--sand"><?php echo esc_html( $oria_label ); ?></span>
			<?php endforeach; ?>
			<a class="finder__recap__redo" href="<?php echo esc_url( \Oria\Core\Finder\url() ); ?>"><?php esc_html_e( 'Start again', 'oria' ); ?></a>
		</div>

		<?php if ( $oria_results['widened'] ) : ?>
			<p class="notice" style="margin-top:1.5rem">
				<?php esc_html_e( 'Nothing matched in that area, so we\'ve looked across the whole of Perth instead.', 'oria' ); ?>
			</p>
		<?php endif; ?>
	</section>

	<?php if ( $oria_results['unsure'] ) : ?>
		<section class="wrap section section--top-flush">
			<h2 class="h2"><?php esc_html_e( 'That\'s okay — most people aren\'t sure', 'oria' ); ?></h2>
			<p class="lede" style="margin-top:.75rem;max-width:56ch"><?php esc_html_e( 'Here are a few genuinely different ways in. You don\'t have to pick the right one, only an interesting one.', 'oria' ); ?></p>
		</section>
	<?php endif; ?>

	<?php if ( $oria_results['practices'] ) : ?>
	<section class="wrap section<?php echo $oria_results['unsure'] ? ' section--top-flush' : ''; ?>">
		<?php if ( ! $oria_results['unsure'] ) : ?>
			<h2 class="h2"><?php esc_html_e( 'Worth exploring', 'oria' ); ?></h2>
		<?php endif; ?>
		<div class="finder__practices">
			<?php foreach ( $oria_results['practices'] as $oria_p ) : ?>
				<?php
				$oria_term  = $oria_p['term'];
				$oria_blurb = (string) ( get_field( 'tile_blurb', 'practice_' . $oria_term->term_id ) ?: $oria_term->description );
				?>
				<article class="finder__practice">
					<h3 class="finder__practice__name"><?php echo esc_html( tname( $oria_term ) ); ?></h3>
					<?php if ( $oria_blurb ) : ?>
						<p class="finder__practice__blurb"><?php echo esc_html( wp_trim_words( $oria_blurb, 26 ) ); ?></p>
					<?php elseif ( ! empty( $oria_p['includes'] ) ) : ?>
						<?php // Term names are capitalised, so they read as labels, not a sentence. ?>
						<ul class="finder__practice__list">
							<?php foreach ( $oria_p['includes'] as $oria_inc ) : ?>
								<li><?php echo esc_html( $oria_inc ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="finder__practice__count">
						<?php
						printf(
							/* translators: %d: number of places */
							esc_html( _n( '%d place in the directory', '%d places in the directory', $oria_p['count'], 'oria' ) ),
							(int) $oria_p['count']
						);
						?>
					</p>
					<?php $oria_term_url = ( 'specialty' === $oria_term->taxonomy && function_exists( '\Oria\Core\PracticesIndex\specialty_url' ) ) ? \Oria\Core\PracticesIndex\specialty_url( $oria_term ) : (string) get_term_link( $oria_term ); ?>
					<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $oria_term_url ); ?>" data-oria-event="finder_practice">
						<?php
						/* translators: %s: practice name */
						printf( esc_html__( 'Explore %s', 'oria' ), esc_html( tname( $oria_term ) ) );
						?>
					</a>
				</article>
			<?php endforeach; ?>
		</div>

		<?php
		/*
		 * The highest-intent moment on the site: someone has just been handed
		 * two or three practices and now has to choose between them. Pre-filled
		 * with the ones they were actually shown, so the link explains itself.
		 */
		$oria_cmp = function_exists( '\Oria\Core\Compare\prompt_for_terms' )
			? \Oria\Core\Compare\prompt_for_terms( array_column( $oria_results['practices'], 'term' ) )
			: null;
		?>
		<?php if ( $oria_cmp ) : ?>
			<p class="finder__compare">
				<a href="<?php echo esc_url( $oria_cmp['url'] ); ?>" data-oria-event="finder_compare">
					<?php
					/* translators: %s: the practices shown, e.g. "Yoga, Breathwork and Meditation" */
					printf(
						esc_html__( 'Not sure between them? Compare %s side by side', 'oria' ),
						esc_html( \Oria\Core\Compare\join_labels( $oria_cmp['labels'] ) )
					);
					?>
					<span aria-hidden="true">&rarr;</span>
				</a>
			</p>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ( $oria_results['listings'] ) : ?>
	<section class="wrap section">
		<h2 class="h2"><?php esc_html_e( 'Places to look at', 'oria' ); ?></h2>
		<?php
		$oria_ids   = array_map( static fn( array $r ): int => $r['id'], $oria_results['listings'] );
		$oria_near  = array_column( $oria_results['listings'], 'near', 'id' );
		$oria_q     = new WP_Query(
			array(
				'post_type'           => 'listing',
				'post__in'            => $oria_ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $oria_ids ),
				'ignore_sticky_posts' => true,
			)
		);
		$oria_split = false;
		?>
		<?php // Listing cards are horizontal — 210px of photo plus text — so they ?>
		<?php // want the directory's single-column rail, not a card grid. ?>
		<div class="dir__results" style="margin-top:1.75rem">
			<?php
			while ( $oria_q->have_posts() ) :
				$oria_q->the_post();
				if ( ! $oria_split && empty( $oria_near[ get_the_ID() ] ) ) :
					$oria_split = true;
					?>
					<p class="finder__further"><?php esc_html_e( 'A little further out, but a close match:', 'oria' ); ?></p>
					<?php
				endif;
				get_template_part( 'template-parts/listing-card' );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $oria_results['events'] ) : ?>
	<section class="wrap section">
		<h2 class="h2"><?php esc_html_e( 'Coming up', 'oria' ); ?></h2>
		<div class="stack" style="margin-top:1.5rem;gap:.75rem">
			<?php foreach ( $oria_results['events'] as $oria_eid ) : ?>
				<?php
				$oria_start  = (string) get_post_meta( $oria_eid, 'event_start', true );
				$oria_venue  = (string) get_post_meta( $oria_eid, 'venue', true );
				$oria_when   = $oria_start ? wp_date( 'D j M', (int) strtotime( $oria_start ) ) : '';
				$oria_eprice = (string) get_post_meta( $oria_eid, 'price', true );
				?>
				<a class="finder__event" href="<?php echo esc_url( (string) get_permalink( $oria_eid ) ); ?>" data-oria-event="finder_event">
					<span class="finder__event__date"><?php echo esc_html( $oria_when ); ?></span>
					<span class="finder__event__body">
						<b><?php echo esc_html( get_the_title( $oria_eid ) ); ?></b>
						<span class="finder__event__meta"><?php echo esc_html( trim( implode( '  ·  ', array_filter( array( $oria_venue, $oria_eprice ) ) ) ) ); ?></span>
					</span>
					<?php echo arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:1rem">
			<a href="<?php echo esc_url( (string) get_post_type_archive_link( 'event' ) ); ?>" style="text-decoration:underline;text-underline-offset:3px"><?php esc_html_e( 'See everything on in Perth', 'oria' ); ?></a>
		</p>
	</section>
	<?php endif; ?>

	<?php if ( $oria_results['articles'] ) : ?>
	<section class="wrap section">
		<h2 class="h2"><?php esc_html_e( 'Read a bit first', 'oria' ); ?></h2>
		<div class="grid grid-3" style="margin-top:1.5rem">
			<?php foreach ( $oria_results['articles'] as $oria_aid ) : ?>
				<a class="article" href="<?php echo esc_url( (string) get_permalink( $oria_aid ) ); ?>" data-oria-event="finder_article">
					<?php if ( has_post_thumbnail( $oria_aid ) ) : ?>
						<div class="article__img"><?php echo get_the_post_thumbnail( $oria_aid, 'oria-card' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<?php endif; ?>
					<h3 class="article__title"><?php echo esc_html( get_the_title( $oria_aid ) ); ?></h3>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php
	// Nothing matched at all — rare, but it must not be a blank page.
	if ( ! $oria_results['practices'] && ! $oria_results['listings'] ) :
		?>
	<section class="wrap section">
		<div class="dir__empty">
			<b><?php esc_html_e( 'Nothing quite fits that combination yet.', 'oria' ); ?></b>
			<p style="margin-top:.5rem"><?php esc_html_e( 'The directory is still growing. Try widening the area, or browse everything and see what catches your eye.', 'oria' ); ?></p>
			<p style="margin-top:1rem">
				<a class="btn btn--dark btn--sm" href="<?php echo esc_url( \Oria\Core\Finder\url() ); ?>"><?php esc_html_e( 'Start again', 'oria' ); ?></a>
				<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( (string) get_post_type_archive_link( 'listing' ) ); ?>"><?php esc_html_e( 'Browse everything', 'oria' ); ?></a>
			</p>
		</div>
	</section>
	<?php endif; ?>

	<!-- ------------------------------------------------- optional hand-off -->
	<section class="wrap section">
		<div class="finder__handoff">
			<h2 class="h3"><?php esc_html_e( 'Would you rather we just introduced you?', 'oria' ); ?></h2>
			<p><?php esc_html_e( 'Tell us what you\'re after and we\'ll pass it to a few practices that fit. They come back to you directly — there\'s no fee, and you\'re not committing to anything.', 'oria' ); ?></p>
			<?php
			// Hand the form what they've already told us rather than asking twice.
			$oria_top   = $oria_results['practices'][0]['term'] ?? null;
			$oria_place = (string) ( $oria_answers['where'] ?? '' );
			$oria_area  = ( $oria_place && ! in_array( $oria_place, array( 'any', 'online' ), true ) )
				? get_term_by( 'slug', $oria_place, 'area' )
				: null;

			get_template_part(
				'template-parts/match-form',
				null,
				array(
					'service'      => $oria_top instanceof WP_Term ? tname( $oria_top ) : '',
					'service_slug' => $oria_top instanceof WP_Term ? $oria_top->slug : '',
					'area'         => $oria_area instanceof WP_Term ? sprintf( __( 'All of %s', 'oria' ), tname( $oria_area ) ) : '',
					'area_slug'    => $oria_area instanceof WP_Term ? $oria_area->slug : '',
				)
			);
			?>
		</div>
	</section>

	<p class="wrap finder__disclaimer">
		<?php esc_html_e( 'Oria Haven is a directory, not a health service. These are suggestions of places to look at, not advice about what will help you. If you have a health concern, please speak with your GP or a registered practitioner.', 'oria' ); ?>
	</p>

<?php endif; ?>

<?php
get_footer();
