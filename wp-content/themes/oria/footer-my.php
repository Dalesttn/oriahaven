<?php
/**
 * The foot of the app shell.
 *
 * On a desk this is almost nothing -- the rail holds the navigation, so
 * there is no reason to repeat it. On a phone it is the bottom bar, which
 * is the navigation, and it sits above the home indicator rather than
 * under it.
 *
 * @package Oria
 */

declare(strict_types=1);

use Oria\Core\MyOria;

$oria_view    = function_exists( '\Oria\Core\MyOria\view' ) ? MyOria\view() : '';
$oria_private = (bool) ( MyOria\VIEWS[ $oria_view ] ?? true );
?>
<?php if ( ! $oria_private ) : ?>
		</main>
		<p class="myauth__back">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to Oria Haven', 'oria' ); ?></a>
		</p>
	</div>
<?php else : ?>
			</main>

			<p class="myapp__foot">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Explore Oria Haven', 'oria' ); ?> <span aria-hidden="true">&#8599;</span></a>
				<span class="myapp__footsep" aria-hidden="true">·</span>
				<a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'oria' ); ?></a>
			</p>
		</div>

		<?php get_template_part( 'template-parts/my/bottomnav', null, array( 'view' => $oria_view ) ); ?>
	</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
