<?php
/**
 * The local Work navigation: Find a job / Shifts & cover / Practitioners.
 * Separate pages, so these are links with aria-current -- not ARIA tabs.
 *
 * $args: view, dark (bool: on the forest hero rather than the sage one)
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_v = (string) ( $args['view'] ?? '' );
$oria_links = array(
	'jobs'   => array( Work\list_url( 'jobs' ), __( 'Find a job', 'oria' ), array( 'jobs' ) ),
	'shifts' => array( Work\list_url( 'shifts' ), __( 'Shifts & cover', 'oria' ), array( 'shifts' ) ),
	'pros'   => array( Work\list_url( 'pros' ), __( 'Practitioners', 'oria' ), array( 'pros', 'available' ) ),
);
?>
<nav class="wknav<?php echo empty( $args['dark'] ) ? '' : ' wknav--dark'; ?>" aria-label="<?php esc_attr_e( 'Work in wellness', 'oria' ); ?>">
	<?php foreach ( $oria_links as $oria_l ) : ?>
		<a href="<?php echo esc_url( $oria_l[0] ); ?>"<?php echo in_array( $oria_v, $oria_l[2], true ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $oria_l[1] ); ?></a>
	<?php endforeach; ?>
</nav>
