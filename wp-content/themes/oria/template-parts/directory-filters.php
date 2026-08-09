<?php
/**
 * The frosted filter sidebar, generated from live terms so a new practice or
 * suburb appears here the moment it exists.
 */

declare(strict_types=1);

$oria_practices   = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false ) );
$oria_regions     = get_terms( array( 'taxonomy' => 'area', 'parent' => 0, 'hide_empty' => false ) );
$oria_specialties = get_terms(
	array(
		'taxonomy'   => 'specialty',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 16,
	)
);

$oria_prices = array(
	'Free' => __( 'Free or by donation', 'oria' ),
	'$'    => __( 'Under $25', 'oria' ),
	'$$'   => __( '$25–$60', 'oria' ),
	'$$$'  => __( '$60–$200', 'oria' ),
	'$$$$' => __( '$200+', 'oria' ),
);
?>
<div class="filters" id="dirFilters">
	<?php if ( ! is_wp_error( $oria_practices ) && $oria_practices ) : ?>
	<div class="filterbox">
		<h3><?php esc_html_e( 'Practice', 'oria' ); ?></h3>
		<?php foreach ( $oria_practices as $oria_term ) : ?>
			<label class="check"><input type="checkbox" data-filter="cat" value="<?php echo esc_attr( $oria_term->slug ); ?>"><span><?php echo esc_html( \Oria\Theme\tname( $oria_term ) ); ?></span></label>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if ( ! is_wp_error( $oria_regions ) && $oria_regions ) : ?>
	<div class="filterbox">
		<h3><?php esc_html_e( 'Area', 'oria' ); ?></h3>
		<?php foreach ( $oria_regions as $oria_term ) : ?>
			<label class="check"><input type="checkbox" data-filter="region" value="<?php echo esc_attr( $oria_term->slug ); ?>"><span><?php echo esc_html( \Oria\Theme\tname( $oria_term ) ); ?></span></label>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if ( ! is_wp_error( $oria_specialties ) && $oria_specialties && ! is_tax( 'specialty' ) ) : ?>
	<div class="filterbox">
		<h3><?php esc_html_e( 'Specialty', 'oria' ); ?></h3>
		<?php foreach ( $oria_specialties as $oria_term ) : ?>
			<label class="check"><input type="checkbox" data-filter="spec" value="<?php echo esc_attr( $oria_term->slug ); ?>"><span><?php echo esc_html( \Oria\Theme\tname( $oria_term ) ); ?></span></label>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<div class="filterbox">
		<h3><?php esc_html_e( 'Price per session', 'oria' ); ?></h3>
		<?php foreach ( $oria_prices as $oria_value => $oria_label ) : ?>
			<label class="check"><input type="checkbox" data-filter="price" value="<?php echo esc_attr( $oria_value ); ?>"><span><?php echo esc_html( $oria_label ); ?></span></label>
		<?php endforeach; ?>
	</div>

	<div class="filterbox">
		<h3><?php esc_html_e( 'Format', 'oria' ); ?></h3>
		<label class="check"><input type="checkbox" data-filter="format" value="in-person"><span><?php esc_html_e( 'In person', 'oria' ); ?></span></label>
		<label class="check"><input type="checkbox" data-filter="format" value="online"><span><?php esc_html_e( 'Online available', 'oria' ); ?></span></label>
	</div>

	<div class="filterbox">
		<h3><?php esc_html_e( 'Rating', 'oria' ); ?></h3>
		<label class="check"><input type="checkbox" data-filter="rating" value="4.5"><span><?php esc_html_e( '4.5 and above', 'oria' ); ?></span></label>
		<label class="check"><input type="checkbox" data-filter="rating" value="4.8"><span><?php esc_html_e( '4.8 and above', 'oria' ); ?></span></label>
	</div>
</div>
