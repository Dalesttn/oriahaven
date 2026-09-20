<?php
/**
 * My Oria -- /my-oria/ and its views.
 *
 * One template, one part per view under template-parts/my/. The private
 * views share the app shell; the sign-in views get their own narrow one.
 * Everything shown is the signed-in member's own, read server-side from
 * their id.
 *
 * The shell is header-my.php / footer-my.php rather than the public pair.
 * A member who has already signed in does not need the site sold to them,
 * and the public header, region strip, breadcrumb and directory footer
 * were about a thousand pixels of a 5,897px page before any of their own
 * content began.
 */

declare(strict_types=1);

use Oria\Core\MyOria;

$oria_view    = MyOria\view();
$oria_private = MyOria\VIEWS[ $oria_view ] ?? true;
$oria_part    = '' === $oria_view ? 'dashboard' : $oria_view;
$oria_notice  = MyOria\notice();

get_header( 'my' );
?>

<?php if ( $oria_notice ) : ?>
	<div class="mynotice notice notice--<?php echo esc_attr( $oria_notice['type'] ); ?>" role="status"><?php echo esc_html( $oria_notice['text'] ); ?></div>
<?php endif; ?>

<?php get_template_part( 'template-parts/my/' . $oria_part, null, array( 'view' => $oria_view ) ); ?>

<?php
get_footer( 'my' );
