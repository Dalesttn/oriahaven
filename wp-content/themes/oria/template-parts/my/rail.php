<?php
/**
 * The desktop rail.
 *
 * Four destinations, because four are built. The brief lists eight; the
 * other four -- plan, events, compare, account -- have no feature behind
 * them yet, and a navigation item that leads to an empty room is worse
 * than one that is not there.
 *
 * The active item is a sage capsule rather than a solid block: this is
 * somebody's own quiet corner of the site, not a control panel.
 *
 * $args: view
 */

declare(strict_types=1);

use Oria\Core\MyOria;

$oria_now  = (string) ( $args['view'] ?? '' );
$oria_name = MyOria\first_name( get_current_user_id() );

/** One line icon per destination, drawn rather than imported. */
$oria_icon = static function ( string $slug ): string {
	$paths = array(
		''         => '<path d="M3 9.5 10 4l7 5.5V16a1 1 0 0 1-1 1h-3.5v-4.5h-5V17H4a1 1 0 0 1-1-1Z"/>',
		'saved'    => '<path d="M5 3.5h10a.5.5 0 0 1 .5.5v12.4a.3.3 0 0 1-.47.25L10 13.6l-5.03 3.05A.3.3 0 0 1 4.5 16.4V4a.5.5 0 0 1 .5-.5Z"/>',
			'listing'  => '<path d="M4 7.5h12M4 7.5V16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7.5M4 7.5 5.6 4h8.8l1.6 3.5M8 11h4"/>',
		'passport' => '<circle cx="10" cy="10" r="6.5"/><path d="M10 3.5v13M3.5 10h13"/>',
		'pass'     => '<path d="M3.5 7.2A1.7 1.7 0 0 0 5.2 5.5h9.6a1.7 1.7 0 0 0 1.7 1.7v5.6a1.7 1.7 0 0 0-1.7 1.7H5.2a1.7 1.7 0 0 0-1.7-1.7Z"/><path d="M11.6 5.5v1.6M11.6 9.2v1.6M11.6 12.9v1.6"/>',
		'profile'  => '<circle cx="10" cy="7" r="3.2"/><path d="M4 16.5c.8-3 3.1-4.5 6-4.5s5.2 1.5 6 4.5"/>',
	);
	return '<svg class="myrail__icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $slug ] ?? $paths[''] ) . '</svg>';
};
?>
<nav class="myrail" aria-label="<?php esc_attr_e( 'My Oria', 'oria' ); ?>">
	<a class="myrail__brand" href="<?php echo esc_url( MyOria\url() ); ?>">
		<span class="myrail__mark" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 6.4 2.6" stroke-linecap="round"/></svg>
		</span>
		<span class="myrail__word">Oria <em>Haven</em></span>
	</a>

	<ul class="myrail__list">
		<?php foreach ( MyOria\tabs() as $oria_tab ) : ?>
			<?php $oria_on = $oria_tab['slug'] === $oria_now; ?>
			<li>
				<a class="myrail__item<?php echo $oria_on ? ' is-on' : ''; ?>"
					href="<?php echo esc_url( $oria_tab['url'] ); ?>"<?php echo $oria_on ? ' aria-current="page"' : ''; ?>>
					<?php echo $oria_icon( (string) $oria_tab['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup above. ?>
					<span><?php echo esc_html( 'Dashboard' === $oria_tab['label'] ? __( 'Home', 'oria' ) : $oria_tab['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="myrail__foot">
		<a class="myrail__out" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Explore Oria Haven', 'oria' ); ?> <span aria-hidden="true">&#8599;</span>
		</a>
		<div class="myrail__who">
			<span class="myrail__av" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $oria_name ?: 'O', 0, 1 ) ) ); ?></span>
			<span class="myrail__whoname"><?php echo esc_html( $oria_name ?: __( 'Member', 'oria' ) ); ?></span>
			<a class="myrail__signout" href="<?php echo esc_url( MyOria\logout_url() ); ?>"><?php esc_html_e( 'Log out', 'oria' ); ?></a>
		</div>
	</div>
</nav>
