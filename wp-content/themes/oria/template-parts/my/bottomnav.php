<?php
/**
 * The phone's bottom bar.
 *
 * The four built destinations, which is under the five the brief allows,
 * so nothing is squeezed. It sits above the home indicator rather than
 * under it, and the label is always there -- an icon alone asks somebody
 * to guess.
 *
 * $args: view
 */

declare(strict_types=1);

use Oria\Core\MyOria;

$oria_now = (string) ( $args['view'] ?? '' );

$oria_icon = static function ( string $slug ): string {
	$paths = array(
		''         => '<path d="M3 9.5 10 4l7 5.5V16a1 1 0 0 1-1 1h-3.5v-4.5h-5V17H4a1 1 0 0 1-1-1Z"/>',
		'saved'    => '<path d="M5 3.5h10a.5.5 0 0 1 .5.5v12.4a.3.3 0 0 1-.47.25L10 13.6l-5.03 3.05A.3.3 0 0 1 4.5 16.4V4a.5.5 0 0 1 .5-.5Z"/>',
		'passport' => '<circle cx="10" cy="10" r="6.5"/><path d="M10 3.5v13M3.5 10h13"/>',
		'profile'  => '<circle cx="10" cy="7" r="3.2"/><path d="M4 16.5c.8-3 3.1-4.5 6-4.5s5.2 1.5 6 4.5"/>',
	);
	return '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $slug ] . '</svg>';
};
?>
<nav class="mybottom" aria-label="<?php esc_attr_e( 'My Oria', 'oria' ); ?>">
	<?php foreach ( MyOria\tabs() as $oria_tab ) : ?>
		<?php $oria_on = $oria_tab['slug'] === $oria_now; ?>
		<a class="mybottom__item<?php echo $oria_on ? ' is-on' : ''; ?>"
			href="<?php echo esc_url( $oria_tab['url'] ); ?>"<?php echo $oria_on ? ' aria-current="page"' : ''; ?>>
			<?php echo $oria_icon( (string) $oria_tab['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup above. ?>
			<span><?php echo esc_html( 'Dashboard' === $oria_tab['label'] ? __( 'Home', 'oria' ) : $oria_tab['label'] ); ?></span>
		</a>
	<?php endforeach; ?>
</nav>
