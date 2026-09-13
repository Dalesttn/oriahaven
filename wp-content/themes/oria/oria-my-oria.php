<?php
/**
 * My Oria -- /my-oria/ and its views.
 *
 * One template, one part per view under template-parts/my/. The private
 * views share the account tabs; the sign-in views stand alone. Everything
 * shown is the signed-in member's own, read server-side from their id.
 */

declare(strict_types=1);

use Oria\Core\MyOria;

get_header();

$oria_view    = MyOria\view();
$oria_private = MyOria\VIEWS[ $oria_view ] ?? true;
$oria_part    = '' === $oria_view ? 'dashboard' : $oria_view;
$oria_notice  = MyOria\notice();
?>

<section class="wrap pagehead myhead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<?php if ( '' === $oria_view ) : ?>
			<span><?php esc_html_e( 'My Oria', 'oria' ); ?></span>
		<?php else : ?>
			<a href="<?php echo esc_url( MyOria\url() ); ?>"><?php esc_html_e( 'My Oria', 'oria' ); ?></a>
			<span aria-hidden="true">/</span><span><?php echo esc_html( MyOria\heading() ); ?></span>
		<?php endif; ?>
	</nav>
	<?php if ( $oria_private ) : ?>
		<?php get_template_part( 'template-parts/my/nav', null, array( 'view' => $oria_view ) ); ?>
	<?php endif; ?>
</section>

<?php if ( $oria_notice ) : ?>
	<div class="wrap">
		<div class="notice notice--<?php echo esc_attr( $oria_notice['type'] ); ?> mynotice" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
	</div>
<?php endif; ?>

<?php get_template_part( 'template-parts/my/' . $oria_part, null, array( 'view' => $oria_view ) ); ?>

<?php
get_footer();
