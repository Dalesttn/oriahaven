<?php
/**
 * Section: free text block
 */

declare(strict_types=1);

use function Oria\Theme\srows;
use function Oria\Theme\simg;
use function Oria\Theme\sband;
use function Oria\Theme\arrow;

$s = isset( $args['s'] ) && is_array( $args['s'] ) ? $args['s'] : array();
$t = static fn( string $k ): string => (string) ( $s[ $k ] ?? '' );

?>
<section class="section<?php echo esc_attr( sband( $s ) ); ?> section--tight">
	<div class="wrap">
		<div class="prose" style="max-width:44rem"><?php echo wp_kses_post( (string) ( $s['content'] ?? '' ) ); ?></div>
	</div>
</section>
