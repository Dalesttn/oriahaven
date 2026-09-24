<?php
/**
 * The neighbourhood card: a next step, not an internal advert.
 *
 * The strip this replaces on the listing page was one sentence stretched
 * across a full-width box with a thick gold rule down its left edge, which
 * is the site's alert treatment, and a link that read as body text. It was
 * mostly air, and the one thing it said with any force was the suburb name
 * the visitor had just read three lines above.
 *
 * This says the useful thing instead: how many places are round the corner,
 * and what the area page will actually give you when you get there.
 *
 * WHAT IT PROMISES DEPENDS ON WHAT EXISTS. The sentence it replaces offered
 * "local day plans and how to get around", and that was true where it ran:
 * the strip's default lead was only ever used by the v4 listing page, and
 * day plans are drawn by Oria\V4\Area\plans(), which is a v4 file. The check
 * below is therefore not fixing a live lie -- it is making sure this card
 * stays honest if it is ever dropped into a template the parent theme
 * serves, where there are no plans to send anybody to.
 *
 * TWO VARIANTS, ONE COMPONENT. With the suburb's own photograph it is a
 * two-column card; without one it is the same card with a small compass
 * mark instead. Thirteen of a hundred and fifty-nine areas have their own
 * picture, so text-led is the ordinary case rather than the degraded one.
 *
 * The card is not itself a link. A clickable container with a button inside
 * it is either a nested control or a div pretending to be one, and the
 * anchor here is the whole affordance.
 *
 * @var array $args {
 *     @type array  $area   From \Oria\Core\AreaContext.
 *     @type string $source Where this card is, for analytics.
 * }
 *
 * @package Oria
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$oria_ad = (array) ( $args['area'] ?? array() );

$oria_ad_url  = (string) ( $oria_ad['url'] ?? '' );
$oria_ad_name = (string) ( $oria_ad['name'] ?? '' );
$oria_ad_n    = (int) ( $oria_ad['places'] ?? 0 );

/*
 * No name, no working URL, or nothing to go and see: render nothing. An
 * "explore the area" card over an empty area page is worse than silence.
 */
if ( '' === $oria_ad_url || '' === $oria_ad_name || $oria_ad_n < 1 ) {
	return;
}

$oria_ad_src    = (string) ( $args['source'] ?? 'listing-area' );
$oria_ad_region = (string) ( $oria_ad['region'] ?? '' );

/*
 * Does the area page really have the guide this sentence would promise?
 * plans() lives in the v4 theme, so its presence is the same question as
 * "is the theme that draws day plans the one serving this request".
 */
$oria_ad_guide = function_exists( '\Oria\V4\Area\plans' );

/*
 * The photograph, but only if it is this suburb's own. AreaContext\image()
 * falls back to the region's hero, which means every listing across Perth
 * Central would show the same picture beside a different suburb's name --
 * decoration pretending to be a place. has_own_image() is the question
 * worth asking, and where the answer is no the card is text-led instead.
 */
$oria_ad_img  = array( 'src' => '', 'alt' => '' );
$oria_ad_term = ( isset( $oria_ad['term'] ) && $oria_ad['term'] instanceof WP_Term ) ? $oria_ad['term'] : null;

if ( $oria_ad_term
	&& function_exists( '\Oria\Core\AreaContext\has_own_image' )
	&& function_exists( '\Oria\Core\AreaContext\image' )
	&& \Oria\Core\AreaContext\has_own_image( $oria_ad_term )
) {
	$oria_ad_img = \Oria\Core\AreaContext\image( $oria_ad_term );
}

$oria_ad_has_img = '' !== (string) $oria_ad_img['src'];

$oria_ad_lead = $oria_ad_guide
	? sprintf(
		/* translators: %s: number of wellness places */
		_n(
			'Discover %s local place, plus neighbourhood tips and an easy wellness day plan.',
			'Discover %s local places, plus neighbourhood tips and an easy wellness day plan.',
			$oria_ad_n,
			'oria'
		),
		number_format_i18n( $oria_ad_n )
	)
	: sprintf(
		/* translators: %s: number of wellness places */
		_n(
			'Discover %s local wellness place and find something that suits how you want to feel.',
			'Discover %s local wellness places and find something that suits how you want to feel.',
			$oria_ad_n,
			'oria'
		),
		number_format_i18n( $oria_ad_n )
	);

$oria_ad_label = sprintf( /* translators: %s: suburb */ __( 'Explore %s', 'oria' ), $oria_ad_name );
?>

<aside class="areadisc areadisc--<?php echo $oria_ad_has_img ? 'img' : 'text'; ?>"
	aria-label="<?php echo esc_attr( sprintf( /* translators: %s: suburb */ __( 'More wellness in %s', 'oria' ), $oria_ad_name ) ); ?>">

	<?php
	/*
	 * Two arrangements, because they are two different shapes and faking
	 * one with the other's markup costs more than the branch does. With a
	 * photograph it reads as a card: words beside a picture. Without one it
	 * reads as a row -- mark, words, action -- which fills the width it
	 * occupies instead of stacking in the left third and leaving the rest
	 * of the line empty, which is what the strip before it did.
	 */
	?>

	<?php if ( ! $oria_ad_has_img ) : ?>
		<?php // A compass, in the site's own open round-capped stroke, standing where the photograph would be. ?>
		<span class="areadisc__mark" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" focusable="false">
				<circle cx="12" cy="12" r="8.2"/>
				<path d="m15.1 8.9-1.6 4.6-4.6 1.6 1.6-4.6Z"/>
			</svg>
		</span>
	<?php endif; ?>

	<div class="areadisc__text">
		<p class="areadisc__eyebrow"><?php esc_html_e( 'Explore the area', 'oria' ); ?></p>

		<?php // h3: the section this sits in already owns the h2, and a card does not outrank its section. ?>
		<h3 class="areadisc__head">
			<?php
			/* translators: %s: suburb */
			printf( esc_html__( 'More wellness in %s', 'oria' ), esc_html( $oria_ad_name ) );
			?>
		</h3>

		<p class="areadisc__lead"><?php echo esc_html( $oria_ad_lead ); ?></p>

		<?php
		/*
		 * One line of real metadata, and only the half of it that is true.
		 * The region comes from the term's own parent; "Local guide" is
		 * claimed only where a guide is actually drawn.
		 */
		$oria_ad_bits = array_values(
			array_filter(
				array(
					$oria_ad_region,
					$oria_ad_guide ? __( 'Local guide', 'oria' ) : '',
				)
			)
		);
		?>
		<?php if ( $oria_ad_bits ) : ?>
			<p class="areadisc__meta"><?php echo esc_html( implode( ' · ', $oria_ad_bits ) ); ?></p>
		<?php endif; ?>
	</div>

	<a class="btn btn--dark btn--sm areadisc__cta"
		href="<?php echo esc_url( $oria_ad_url ); ?>"
		data-area-promo="<?php echo esc_attr( $oria_ad_src ); ?>"
		data-area-slug="<?php echo esc_attr( (string) ( $oria_ad['slug'] ?? '' ) ); ?>"
		data-area-count="<?php echo esc_attr( (string) $oria_ad_n ); ?>"
		data-area-variant="<?php echo $oria_ad_has_img ? 'image' : 'text'; ?>">
		<span><?php echo esc_html( $oria_ad_label ); ?></span>
		<span class="areadisc__arrow" aria-hidden="true">&rarr;</span>
	</a>

	<?php if ( $oria_ad_has_img ) : ?>
		<?php
		/*
		 * Decorative: the heading beside it already names the suburb, so an
		 * alt text would be read out twice. Dimensions are stated so the
		 * card does not reflow when it arrives.
		 */
		?>
		<div class="areadisc__img">
			<img src="<?php echo esc_url( (string) $oria_ad_img['src'] ); ?>" alt="" width="480" height="360" loading="lazy" decoding="async">
		</div>
	<?php endif; ?>
</aside>
