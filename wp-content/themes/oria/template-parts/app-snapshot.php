<?php
/**
 * The facts about an app, at a glance.
 *
 * A definition list rather than a table: the same information, but it
 * stacks on a phone without a horizontal scrollbar, and a screen reader
 * reads each label with its value rather than announcing a grid.
 *
 * $args: app (array from Engine\row())
 */

declare(strict_types=1);

use Oria\Apps\Data;
use Oria\Apps\Engine;

$oria_app = isset( $args['app'] ) && is_array( $args['app'] ) ? $args['app'] : null;
if ( ! $oria_app ) {
	return;
}

$oria_rows = array();

if ( $oria_app['best_for'] ) {
	$oria_labels = array_map(
		static fn( string $k ): string => Data\label( 'best_for', $k ),
		array_slice( (array) $oria_app['best_for'], 0, 3 )
	);
	$oria_rows[ __( 'Best for', 'oria' ) ] = implode( ', ', $oria_labels );
}

if ( $oria_app['cat_names'] ) {
	$oria_rows[ __( 'Focus', 'oria' ) ] = implode( ', ', array_slice( (array) $oria_app['cat_names'], 0, 3 ) );
}

$oria_rows[ __( 'Cost', 'oria' ) ] = Engine\price_line( $oria_app );

if ( '' !== (string) $oria_app['trial'] ) {
	$oria_rows[ __( 'Free trial', 'oria' ) ] = $oria_app['trial'];
}

if ( $oria_app['platforms'] ) {
	$oria_names = array_map(
		static fn( string $k ): string => Data\label( 'platform', $k ),
		(array) $oria_app['platforms']
	);
	$oria_rows[ __( 'Runs on', 'oria' ) ] = implode( ', ', $oria_names );
}

if ( '' !== (string) $oria_app['developer'] ) {
	$oria_rows[ __( 'Made by', 'oria' ) ] = $oria_app['developer'];
}

if ( ! $oria_rows ) {
	return;
}
?>
<section class="wrap section section--top-flush">
	<dl class="appsnap reveal">
		<?php foreach ( $oria_rows as $oria_label => $oria_value ) : ?>
			<div class="appsnap__row">
				<dt><?php echo esc_html( (string) $oria_label ); ?></dt>
				<dd><?php echo esc_html( (string) $oria_value ); ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
</section>
