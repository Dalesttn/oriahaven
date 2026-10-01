<?php
/**
 * Previous / Page n of m / Next. Plain links that keep every other filter
 * in the query string, so paging never loses the search.
 *
 * $args: pages, current
 */

declare(strict_types=1);

$oria_pages = (int) ( $args['pages'] ?? 0 );
$oria_cur   = max( 1, (int) ( $args['current'] ?? 1 ) );
if ( $oria_pages < 2 ) {
	return;
}
?>
<nav class="wkpager" aria-label="<?php esc_attr_e( 'More results', 'oria' ); ?>">
	<?php if ( $oria_cur > 1 ) : ?>
		<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( add_query_arg( 'pg', $oria_cur - 1 ) ); ?>" rel="prev"><?php esc_html_e( 'Previous', 'oria' ); ?></a>
	<?php endif; ?>
	<span><?php echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'oria' ), $oria_cur, $oria_pages ) ); ?></span>
	<?php if ( $oria_cur < $oria_pages ) : ?>
		<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( add_query_arg( 'pg', $oria_cur + 1 ) ); ?>" rel="next"><?php esc_html_e( 'Next', 'oria' ); ?></a>
	<?php endif; ?>
</nav>
