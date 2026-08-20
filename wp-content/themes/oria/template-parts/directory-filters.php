<?php
/**
 * The frosted filter sidebar, generated from live terms so a new practice or
 * suburb appears here the moment it exists.
 */

declare(strict_types=1);

$oria_practices   = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false ) );
$oria_regions     = \Oria\Core\Taxonomies\regions();
// All of them, commonest first. The list runs past seventy terms, so only
// the first dozen show until "Show all" is tapped — the rest are in the
// markup so the filter still works without JavaScript.
$oria_specialties = get_terms(
	array(
		'taxonomy'   => 'specialty',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
const ORIA_SPEC_SHOWN = 12;

$oria_prices = array(
	'Free' => __( 'Free or by donation', 'oria' ),
	'$'    => __( 'Under $25', 'oria' ),
	'$$'   => __( '$25–$60', 'oria' ),
	'$$$'  => __( '$60–$200', 'oria' ),
	'$$$$' => __( '$200+', 'oria' ),
);
?>
<div class="filters" id="dirFilters">
	<?php // Sheet furniture: hidden on desktop, the way out on a phone. ?>
	<div class="filters__head">
		<span class="filters__title"><?php esc_html_e( 'Filters', 'oria' ); ?></span>
		<button class="filters__close" type="button" data-sheet-close aria-label="<?php esc_attr_e( 'Close filters', 'oria' ); ?>">
			<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
		</button>
	</div>
	<?php
	/*
	 * Practice is navigation, not a filter.
	 *
	 * These seventeen categories are the only facet with a landing page of
	 * its own — an introduction, FAQs and internal links that a filtered
	 * view of the same listings does not have. As checkboxes they sent
	 * people to /directory/?cat=breathwork and left /practice/breathwork/
	 * reachable only from the hub, which wasted the better page and made
	 * parameter URLs the ones getting shared. As links they carry every
	 * reader to the page written for them, and give each landing page an
	 * internal link from every directory view on the site.
	 *
	 * The cost is multi-select on this one facet. Specialty is the fine
	 * grained axis and keeps it, as do area, price, format and rating.
	 * An old ?cat= link still filters correctly — the JS reads it from the
	 * URL, and its chip labels come from the data payload rather than
	 * from these inputs.
	 */
	$oria_current = is_tax( 'practice' ) ? (string) ( get_queried_object()->slug ?? '' ) : '';
	?>
	<?php
	/*
	 * Top-level categories only, deepest first, from the plan rather than
	 * from a flat get_terms(). The flat list put "Mind & Mental Wellbeing"
	 * in the same alphabetical run as Breathwork, Meditation classes and
	 * Mindfulness coaching — its own children — and showed categories being
	 * held back for want of listings. navigation() answers the question the
	 * sidebar is actually asking.
	 *
	 * Labelled "Categories" because that is what a visitor calls them. The
	 * taxonomy is still `practice` and /practice/{slug}/ is untouched.
	 */
	$oria_cats = function_exists( '\Oria\Core\Categories\navigation' )
		? \Oria\Core\Categories\navigation()
		: array();
	?>
	<?php if ( $oria_cats ) : ?>
	<nav class="filterbox catrail" aria-labelledby="filt-cat">
		<span class="filterbox__label" id="filt-cat"><?php esc_html_e( 'Categories', 'oria' ); ?></span>
		<?php
		foreach ( $oria_cats as $oria_cat ) :
			$oria_t       = $oria_cat['term'];
			$oria_is_here = $oria_current === $oria_t->slug;
			?>
			<a class="catrail__row cat-<?php echo esc_attr( $oria_t->slug ); ?><?php echo $oria_is_here ? ' is-here' : ''; ?>"
				href="<?php echo esc_url( (string) get_term_link( $oria_t ) ); ?>"
				<?php echo $oria_is_here ? 'aria-current="page"' : ''; ?>>
				<span class="catrail__dot"><?php echo \Oria\Core\Categories\icon( $oria_t->slug ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<span class="catrail__name"><?php echo esc_html( \Oria\Theme\tname( $oria_t ) ); ?></span>
				<span class="catrail__n"><?php echo esc_html( (string) $oria_cat['count'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php endif; ?>

	<?php if ( ! is_wp_error( $oria_regions ) && $oria_regions ) : ?>
	<div class="filterbox" role="group" aria-labelledby="filt-area">
		<span class="filterbox__label" id="filt-area"><?php esc_html_e( 'Area', 'oria' ); ?></span>
		<?php foreach ( $oria_regions as $oria_term ) : ?>
			<label class="check"><input type="checkbox" data-filter="region" value="<?php echo esc_attr( $oria_term->slug ); ?>"><span><?php echo esc_html( \Oria\Theme\tname( $oria_term ) ); ?></span></label>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if ( ! is_wp_error( $oria_specialties ) && $oria_specialties && ! is_tax( 'specialty' ) ) : ?>
	<div class="filterbox" role="group" aria-labelledby="filt-specialty" data-collapsible>
		<span class="filterbox__label" id="filt-specialty"><?php esc_html_e( 'Specialty', 'oria' ); ?></span>
		<?php foreach ( array_values( $oria_specialties ) as $oria_i => $oria_term ) : ?>
			<label class="check<?php echo $oria_i >= ORIA_SPEC_SHOWN ? ' is-extra' : ''; ?>"><input type="checkbox" data-filter="spec" value="<?php echo esc_attr( $oria_term->slug ); ?>"><span><?php echo esc_html( \Oria\Theme\tname( $oria_term ) ); ?></span></label>
		<?php endforeach; ?>
		<?php if ( count( $oria_specialties ) > ORIA_SPEC_SHOWN ) : ?>
			<button class="filtermore" type="button" data-filter-more
				data-more="<?php echo esc_attr( sprintf( __( 'Show all %d', 'oria' ), count( $oria_specialties ) ) ); ?>"
				data-less="<?php esc_attr_e( 'Show fewer', 'oria' ); ?>"><?php echo esc_html( sprintf( __( 'Show all %d', 'oria' ), count( $oria_specialties ) ) ); ?></button>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="filterbox" role="group" aria-labelledby="filt-price-per-session">
		<span class="filterbox__label" id="filt-price-per-session"><?php esc_html_e( 'Price per session', 'oria' ); ?></span>
		<?php foreach ( $oria_prices as $oria_value => $oria_label ) : ?>
			<label class="check"><input type="checkbox" data-filter="price" value="<?php echo esc_attr( $oria_value ); ?>"><span><?php echo esc_html( $oria_label ); ?></span></label>
		<?php endforeach; ?>
	</div>

	<div class="filterbox" role="group" aria-labelledby="filt-format">
		<span class="filterbox__label" id="filt-format"><?php esc_html_e( 'Format', 'oria' ); ?></span>
		<label class="check"><input type="checkbox" data-filter="format" value="in-person"><span><?php esc_html_e( 'In person', 'oria' ); ?></span></label>
		<label class="check"><input type="checkbox" data-filter="format" value="online"><span><?php esc_html_e( 'Online available', 'oria' ); ?></span></label>
	</div>

	<div class="filterbox" role="group" aria-labelledby="filt-rating">
		<span class="filterbox__label" id="filt-rating"><?php esc_html_e( 'Rating', 'oria' ); ?></span>
		<label class="check"><input type="checkbox" data-filter="rating" value="4.5"><span><?php esc_html_e( '4.5 and above', 'oria' ); ?></span></label>
		<label class="check"><input type="checkbox" data-filter="rating" value="4.8"><span><?php esc_html_e( '4.8 and above', 'oria' ); ?></span></label>
	</div>
</div>
