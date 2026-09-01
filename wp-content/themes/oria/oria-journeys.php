<?php
/**
 * Wellness Journeys index.
 *
 * Built to the journal's design language rather than a parallel one: the same
 * Caveat hand marker, the same warm sand panel and single dark moment, the
 * same reveal. What differs is what an index has to do that an article does
 * not -- it has to make a format nobody has seen before legible in one screen.
 *
 * Hence the shape: one journey given room to explain itself, the rest as a
 * pair, then the three facts that separate a journey from a listicle, then the
 * way out. Every number on the page is read from the steps, never typed twice.
 */

declare(strict_types=1);

use Oria\Core\Journeys;

get_header();

$oria_split   = Journeys\split();
$oria_feature = $oria_split['feature'];
$oria_rest    = $oria_split['rest'];
$oria_count   = ( $oria_feature ? 1 : 0 ) + count( $oria_rest );

/** One card, two sizes. The feature also gets the hour rail. */
$oria_card = static function ( WP_Post $post, bool $feature = false ): void {
	$shape = Journeys\shape( $post->ID );
	$rail  = Journeys\rail( $shape['times'] );
	$meta  = array_filter(
		array(
			$shape['stops'] ? sprintf(
				/* translators: %s: number of stops in the day. */
				_n( '%s stop', '%s stops', $shape['stops'], 'oria' ),
				number_format_i18n( $shape['stops'] )
			) : '',
			$shape['span'],
		)
	);
	?>
	<a class="jcard<?php echo $feature ? ' jcard--feature' : ''; ?> reveal" href="<?php echo esc_url( (string) get_permalink( $post ) ); ?>">
		<?php if ( has_post_thumbnail( $post ) ) : ?>
			<?php
			/*
			 * 'large', not 'oria-card'. oria-card is a hard 4:3 crop, and a
			 * journey cover is artwork with the article's title set into it --
			 * cropping one to 4:3 and then again in CSS took the first and last
			 * words off the title. This size is scaled, never cropped.
			 */
			?>
			<div class="jcard__img"><?php echo get_the_post_thumbnail( $post, 'large', array( 'loading' => $feature ? 'eager' : 'lazy' ) ); ?></div>
		<?php else : ?>
			<div class="jcard__img jcard__img--empty" aria-hidden="true"></div>
		<?php endif; ?>

		<div class="jcard__body">
			<?php if ( $meta ) : ?>
				<span class="jcard__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></span>
			<?php endif; ?>
			<h3 class="jcard__title"><?php echo esc_html( \Oria\Theme\ptitle( $post ) ); ?></h3>
			<?php if ( $post->post_excerpt ) : ?>
				<p class="jcard__excerpt"><?php echo esc_html( wp_trim_words( $post->post_excerpt, $feature ? 40 : 24 ) ); ?></p>
			<?php endif; ?>

			<?php if ( $feature && count( $rail['times'] ) > 1 ) : ?>
				<?php // The hours: the one honest sequence on a page of unordered days. ?>
				<ol class="jrail">
					<?php foreach ( $rail['times'] as $oria_i => $oria_t ) : ?>
						<li class="jrail__t"><?php echo esc_html( $oria_t ); ?></li>
						<?php if ( $rail['trimmed'] && (int) floor( count( $rail['times'] ) / 2 ) - 1 === $oria_i ) : ?>
							<li class="jrail__gap" aria-hidden="true">&middot;&middot;&middot;</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<span class="jcard__go"><?php esc_html_e( 'Read the day', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		</div>
	</a>
	<?php
};
?>

<section class="wrap jhero">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( function_exists( '\Oria\Core\PracticesIndex\url' ) ? \Oria\Core\PracticesIndex\url() : home_url( '/practices/' ) ); ?>"><?php esc_html_e( 'Experiences', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php echo esc_html( Journeys\heading() ); ?></span>
	</nav>

	<span class="jhero__hand"><?php esc_html_e( 'a whole day, planned', 'oria' ); ?></span>
	<h1 class="jhero__title"><?php echo esc_html( Journeys\heading() ); ?></h1>
	<p class="jhero__lede"><?php echo esc_html( Journeys\lede() ); ?></p>

	<?php if ( $oria_count ) : ?>
		<p class="jhero__meta">
			<?php
			printf(
				/* translators: %s: number of published journeys. */
				esc_html( _n( '%s journey', '%s journeys', $oria_count, 'oria' ) ),
				esc_html( number_format_i18n( $oria_count ) )
			);
			echo ' &middot; ' . esc_html__( 'Perth and the coast', 'oria' );
			?>
		</p>
	<?php endif; ?>
</section>

<?php if ( $oria_feature ) : ?>
	<section class="wrap jsection">
		<?php $oria_card( $oria_feature, true ); ?>
	</section>
<?php endif; ?>

<?php if ( $oria_rest ) : ?>
	<section class="wrap jsection">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'More days', 'oria' ); ?></span>
			</div>
		</div>
		<div class="jgrid">
			<?php foreach ( $oria_rest as $oria_post ) : ?>
				<?php $oria_card( $oria_post ); ?>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php if ( ! $oria_count ) : ?>
	<section class="wrap jsection">
		<?php
		/*
		 * An empty index is a real state on a new site, and the honest thing is
		 * to send people somewhere rather than apologise at them.
		 */
		?>
		<p class="muted"><?php esc_html_e( 'The first journeys are being written. In the meantime, the journal has the guides they are built from.', 'oria' ); ?></p>
		<p><a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'Read the journal', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></p>
	</section>
<?php endif; ?>

<section class="wrap jsection">
	<?php
	/*
	 * Three facts, not three steps -- so no numerals. What a reader needs here
	 * is to understand what a journey is not: not a package, not a booking,
	 * and not a list that rots.
	 */
	?>
	<div class="jnote reveal">
		<span class="micro"><?php esc_html_e( 'How a journey works', 'oria' ); ?></span>
		<dl class="jnote__list">
			<div>
				<dt><?php esc_html_e( 'Every stop is a real place', 'oria' ); ?></dt>
				<dd><?php esc_html_e( 'Each one is a listing in the directory with its own hours, prices and address. Nothing here is invented to fill an hour.', 'oria' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'The times are a suggestion', 'oria' ); ?></dt>
				<dd><?php esc_html_e( 'A journey is not a package and nothing is booked for you. Take the parts that suit the day you actually have.', 'oria' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'It keeps itself honest', 'oria' ); ?></dt>
				<dd><?php esc_html_e( 'Each stop reads its listing when the page loads, so when somewhere moves, changes its prices or closes, the day corrects itself.', 'oria' ); ?></dd>
			</div>
		</dl>
	</div>
</section>

<section class="wrap jsection jsection--last">
	<div class="jout reveal">
		<div class="jout__text">
			<h2 class="jout__title"><?php esc_html_e( 'Want to build your own?', 'oria' ); ?></h2>
			<p><?php esc_html_e( 'Every stop in every journey came out of the directory. Swap any of them for something nearer you, or answer four questions and let the finder do it.', 'oria' ); ?></p>
		</div>
		<div class="jout__acts">
			<a class="btn btn--light" href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Explore', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			<a class="btn btn--ghost jout__alt" href="<?php echo esc_url( home_url( '/wellness-finder/' ) ); ?>"><?php esc_html_e( 'Try the Wellness Finder', 'oria' ); ?></a>
		</div>
	</div>
</section>

<?php
get_footer();
