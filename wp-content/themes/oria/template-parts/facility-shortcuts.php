<?php
/**
 * Shortcuts for a facility page, and the facts app.js needs to honour them.
 *
 * Each shortcut is drawn only when the saved data can answer it -- no
 * "quiet sessions" button when no venue publishes one. The payload below
 * carries every card's verified summary, so the client can show it, sort on
 * the facility's own price, and let a venue from another category (a leisure
 * centre) into a Spa & Recovery page.
 *
 * @var array $args { sums: array<int, array>, ids: int[], facility: string }
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_sums = (array) ( $args['sums'] ?? array() );
$oria_ids  = array_map( 'intval', (array) ( $args['ids'] ?? array() ) );

$oria_tests = array(
	'under25' => array( __( 'Under $25', 'oria' ), static fn( array $s ): bool => null !== $s['price'] && $s['price'] > 0 && $s['price'] < 25 ),
	'casual'  => array( __( 'Casual entry', 'oria' ), static fn( array $s ): bool => 'yes' === $s['casual'] ),
	'quiet'   => array( __( 'Quiet sessions', 'oria' ), static fn( array $s ): bool => '' !== (string) $s['quiet'] ),
	'cold'    => array( __( 'Steam and cold plunge', 'oria' ), static fn( array $s ): bool => ! empty( $s['cold'] ) ),
);

$oria_slug = static fn( int $id ): string => (string) get_post_field( 'post_name', $id );

$oria_payload = array( 'facility' => (string) ( $args['facility'] ?? '' ), 'ids' => array(), 'sums' => array(), 'tests' => array() );
foreach ( $oria_ids as $oria_id ) {
	$oria_payload['ids'][] = $oria_slug( $oria_id );
}
$oria_counts = array_fill_keys( array_keys( $oria_tests ), 0 );
foreach ( $oria_sums as $oria_id => $oria_s ) {
	$oria_key = $oria_slug( (int) $oria_id );
	$oria_payload['sums'][ $oria_key ] = array_intersect_key( $oria_s, array_flip( array( 'price', 'price_text', 'product', 'duration', 'conditions', 'access', 'includes', 'essentials', 'quiet', 'notice', 'book_url', 'source', 'checked' ) ) );
	foreach ( $oria_tests as $oria_t => $oria_def ) {
		if ( $oria_def[1]( $oria_s ) ) {
			$oria_payload['tests'][ $oria_t ][] = $oria_key;
			++$oria_counts[ $oria_t ];
		}
	}
}
$oria_shown = array_filter( $oria_counts );
?>
<script>window.ORIA_FACILITY = <?php echo wp_json_encode( $oria_payload ); ?>;</script>
<?php if ( $oria_shown ) : ?>
	<div class="xc-refine fac-short" role="group" aria-label="<?php esc_attr_e( 'Narrow the list', 'oria' ); ?>">
		<?php foreach ( $oria_shown as $oria_t => $oria_n ) : ?>
			<button type="button" class="xc-refine__btn" data-fac-short="<?php echo esc_attr( $oria_t ); ?>" aria-pressed="false">
				<?php echo esc_html( $oria_tests[ $oria_t ][0] ); ?>
				<span class="xc-refine__n"><?php echo esc_html( number_format_i18n( $oria_n ) ); ?></span>
			</button>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
