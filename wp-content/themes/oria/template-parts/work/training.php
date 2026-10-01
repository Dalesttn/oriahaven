<?php
/**
 * /jobs/training/ -- training and certification (brief section 53).
 *
 * Only courses Oria staff have added and checked. Prices are the provider's
 * own words; affiliate links say so. The lifecycle line (learn, qualify,
 * find work) points onward to jobs for the same profession.
 */

declare(strict_types=1);

use Oria\Core\Work;
use Oria\Core\Work\Market;

$oria_prof    = sanitize_title( wp_unslash( (string) ( $_GET['profession'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$oria_courses = Market\courses( $oria_prof );
$oria_all     = $oria_prof ? Market\courses() : $oria_courses;
$oria_profs   = array();
foreach ( $oria_all as $oria_c ) {
	foreach ( wp_get_post_terms( $oria_c, Work\PROFESSION ) as $oria_t ) {
		$oria_profs[ $oria_t->slug ] = $oria_t->name;
	}
}
asort( $oria_profs );
?>
<header class="wkhero">
	<div class="wrap">
		<p class="micro wkhero__kicker"><?php esc_html_e( 'Learn · Get qualified · Find work', 'oria' ); ?></p>
		<h1 class="wkhero__title"><?php echo esc_html( Work\heading() ); ?></h1>
		<p class="lede wkhero__lede"><?php esc_html_e( 'Courses checked by Oria Haven: yoga teacher training, Pilates certification, massage, breathwork, first aid and more. Prices are the provider\'s own.', 'oria' ); ?></p>
	</div>
</header>

<section class="wrap wksec">
	<?php if ( $oria_profs ) : ?>
		<nav class="wksubnav" aria-label="<?php esc_attr_e( 'Courses by profession', 'oria' ); ?>">
			<a href="<?php echo esc_url( Market\url( 'training' ) ); ?>"<?php echo '' === $oria_prof ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'oria' ); ?></a>
			<?php foreach ( $oria_profs as $oria_slug => $oria_name ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'profession', $oria_slug, Market\url( 'training' ) ) ); ?>"<?php echo $oria_prof === $oria_slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $oria_name ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( ! $oria_courses ) : ?>
		<div class="wkempty"><p><strong><?php esc_html_e( 'Our course list is on its way.', 'oria' ); ?></strong> <?php esc_html_e( 'We only list training we have checked, so it is growing slowly on purpose. Run a course? Let us know.', 'oria' ); ?></p>
			<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'Get in touch', 'oria' ); ?></a></div>
	<?php else : ?>
		<ul class="wkcourses">
			<?php foreach ( $oria_courses as $oria_c ) : ?>
				<?php
				$oria_m   = static fn( string $k ) => (string) Work\meta( $oria_c, 'c_' . $k );
				$oria_tag = wp_get_post_terms( $oria_c, Work\PROFESSION );
				?>
				<li class="wkcourse<?php echo $oria_m( 'featured' ) ? ' is-featured' : ''; ?>">
					<div class="wkcourse__body">
						<?php if ( $oria_m( 'featured' ) ) : ?><span class="wkbadge wkbadge--gold"><?php esc_html_e( 'Featured', 'oria' ); ?></span><?php endif; ?>
						<h2 class="wkcard__title"><?php echo esc_html( get_the_title( $oria_c ) ); ?></h2>
						<p class="wkcard__org"><?php echo esc_html( implode( ' · ', array_filter( array( $oria_m( 'provider' ), $oria_m( 'mode' ), $oria_m( 'place' ), $oria_m( 'length' ) ) ) ) ); ?></p>
						<div class="wkprose"><?php echo wp_kses_post( wpautop( wp_trim_words( (string) get_post_field( 'post_content', $oria_c ), 60 ) ) ); ?></div>
						<p class="wkcard__facts">
							<?php if ( '' !== $oria_m( 'price' ) ) : ?><span class="wkchip wkchip--pay"><?php echo esc_html( $oria_m( 'price' ) ); ?></span><?php endif; ?>
							<?php foreach ( $oria_tag as $oria_t ) : ?>
								<a class="wkchip" href="<?php echo esc_url( Work\list_url( 'jobs', '', $oria_t->slug ) ); ?>"><?php echo esc_html( sprintf( __( '%s jobs', 'oria' ), $oria_t->name ) ); ?></a>
							<?php endforeach; ?>
						</p>
					</div>
					<div class="wkcourse__go">
						<?php if ( '' !== $oria_m( 'url' ) ) : ?>
							<a class="btn btn--dark btn--sm" href="<?php echo esc_url( $oria_m( 'url' ) ); ?>" target="_blank" rel="<?php echo $oria_m( 'aff' ) ? 'sponsored noopener' : 'noopener'; ?>" data-wk-event="course_click"><?php esc_html_e( 'See the course', 'oria' ); ?></a>
						<?php endif; ?>
						<?php if ( $oria_m( 'aff' ) ) : ?><p class="hint"><?php esc_html_e( 'Affiliate link', 'oria' ); ?></p><?php endif; ?>
						<?php if ( '' !== $oria_m( 'checked' ) ) : ?><p class="hint"><?php echo esc_html( sprintf( __( 'Checked %s', 'oria' ), mysql2date( 'M Y', $oria_m( 'checked' ) ) ) ); ?></p><?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
