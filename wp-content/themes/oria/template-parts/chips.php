<?php
/**
 * A list of links with their counts kept quiet.
 *
 * The counts used to sit inside the label -- "Yoga (51)" -- which made a
 * row of them read as a table of numbers with words attached. Here the
 * name comes first and the number follows it in smaller, softer type.
 *
 * Past `limit` the rest fold into a <details>. Every link is still in the
 * HTML either way, which is the point: this is the internal-linking
 * layer, and hiding half of it from a crawler to tidy a page would be a
 * poor trade.
 *
 * @var array $args {
 *     @type array  $items   Rows of [url, label, count|null].
 *     @type string $heading Optional heading above the list.
 *     @type string $id      Heading id.
 *     @type int    $limit   How many before the rest fold away. 0 = never.
 *     @type string $more    The summary text for the folded rest.
 *     @type string $event   Optional data-oria-event on each link.
 * }
 */

declare(strict_types=1);

$oria_items = array_values( (array) ( $args['items'] ?? array() ) );
if ( ! $oria_items ) {
	return;
}

$oria_limit = (int) ( $args['limit'] ?? 0 );
$oria_event = (string) ( $args['event'] ?? '' );
$oria_head  = $oria_limit > 0 && count( $oria_items ) > $oria_limit
	? array_slice( $oria_items, 0, $oria_limit )
	: $oria_items;
$oria_rest  = array_slice( $oria_items, count( $oria_head ) );

/**
 * One link.
 *
 * @param array  $item  [url, label, count|null].
 * @param string $event Analytics name, or ''.
 */
$oria_chip = static function ( array $item, string $event ) {
	$count = $item[2] ?? null;
	?>
	<a class="pill pill--count" href="<?php echo esc_url( (string) $item[0] ); ?>"<?php echo '' !== $event ? ' data-oria-event="' . esc_attr( $event ) . '"' : ''; ?>>
		<?php echo esc_html( (string) $item[1] ); ?>
		<?php if ( null !== $count ) : ?>
			<span class="pill__n">
				<?php
				printf(
					/* translators: %s: number of places */
					esc_html( _n( '%s place', '%s places', (int) $count, 'oria' ) ),
					esc_html( number_format_i18n( (int) $count ) )
				);
				?>
			</span>
		<?php endif; ?>
	</a>
	<?php
};
?>
<?php if ( ! empty( $args['heading'] ) ) : ?>
	<h2 class="h4 xc-mesh__title"<?php echo ! empty( $args['id'] ) ? ' id="' . esc_attr( (string) $args['id'] ) . '"' : ''; ?>><?php echo esc_html( (string) $args['heading'] ); ?></h2>
<?php endif; ?>
<div class="chips xc-mesh__chips">
	<?php
	foreach ( $oria_head as $oria_item ) {
		$oria_chip( $oria_item, $oria_event );
	}
	?>
</div>
<?php if ( $oria_rest ) : ?>
	<details class="chipmore">
		<summary class="chipmore__toggle">
			<span>
				<?php
				printf(
					/* translators: %s: how many more */
					esc_html( (string) ( $args['more'] ?? __( 'Show %s more', 'oria' ) ) ),
					esc_html( number_format_i18n( count( $oria_rest ) ) )
				);
				?>
			</span>
			<span class="chipmore__mark" aria-hidden="true">&rarr;</span>
		</summary>
		<div class="chips xc-mesh__chips chipmore__chips">
			<?php
			foreach ( $oria_rest as $oria_item ) {
				$oria_chip( $oria_item, $oria_event );
			}
			?>
		</div>
	</details>
<?php endif; ?>
