<?php
/**
 * The account tabs: Dashboard, Saved, Passport, Profile, and the way out.
 * Scrolls sideways on a phone rather than wrapping into a stack.
 *
 * $args: view
 */

declare(strict_types=1);

use Oria\Core\MyOria;

$oria_now = (string) ( $args['view'] ?? '' );
?>
<nav class="mynav" aria-label="<?php esc_attr_e( 'My Oria', 'oria' ); ?>">
	<?php foreach ( MyOria\tabs() as $oria_tab ) : ?>
		<a class="mynav__tab<?php echo $oria_tab['slug'] === $oria_now ? ' is-active' : ''; ?>" href="<?php echo esc_url( $oria_tab['url'] ); ?>"<?php echo $oria_tab['slug'] === $oria_now ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $oria_tab['label'] ); ?></a>
	<?php endforeach; ?>
	<a class="mynav__out" href="<?php echo esc_url( MyOria\logout_url() ); ?>"><?php esc_html_e( 'Log out', 'oria' ); ?></a>
</nav>
