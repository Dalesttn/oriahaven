<?php
/**
 * The Wellness Passport: places explored, and the badges they add up to.
 *
 * Counts and dots, never a percentage -- there is no total to be a fraction
 * of. Language is explore, try, discover; nothing here measures a person.
 */

declare(strict_types=1);

use Oria\Core\Activity;
use Oria\Core\Passport;

$oria_uid    = get_current_user_id();
$oria_tried  = Activity\ids( $oria_uid, Activity\TRIED );
$oria_stats  = Passport\stats( $oria_uid );
$oria_badges = Passport\evaluate( $oria_uid );
$oria_earned = count( array_filter( $oria_badges, static fn( $b ) => $b['earned'] ) );
if ( function_exists( '\Oria\Theme\prime_listings' ) ) {
	\Oria\Theme\prime_listings( $oria_tried );
}
?>
<section class="wrap my">
	<div class="passhead reveal">
		<span class="passhead__eyebrow"><?php esc_html_e( 'My Wellness Passport', 'oria' ); ?></span>
		<h1 class="h1 passhead__title"><?php esc_html_e( 'Explore Perth your way', 'oria' ); ?></h1>
		<p class="passhead__motto" aria-hidden="true"><?php esc_html_e( 'Explore • Experience • Discover', 'oria' ); ?></p>
		<p class="lede"><?php esc_html_e( 'Try new wellness experiences and build your Oria Passport as you go. Every place you mark as tried is a stamp; the badges follow on their own.', 'oria' ); ?></p>
	</div>

	<div class="passprog reveal">
		<div class="passprog__n"><b data-my-count="tried"><?php echo esc_html( number_format_i18n( $oria_stats['experiences'] ) ); ?></b><span><?php echo esc_html( _n( 'experience explored', 'experiences explored', $oria_stats['experiences'], 'oria' ) ); ?></span></div>
		<div class="passprog__n"><b><?php echo esc_html( number_format_i18n( $oria_stats['types'] ) ); ?></b><span><?php echo esc_html( _n( 'kind of practice tried', 'kinds of practice tried', $oria_stats['types'], 'oria' ) ); ?></span></div>
		<div class="passprog__n"><b data-my-count="badges"><?php echo esc_html( number_format_i18n( $oria_earned ) ); ?></b><span><?php echo esc_html( _n( 'badge earned', 'badges earned', $oria_earned, 'oria' ) ); ?></span></div>
		<ol class="mydots passprog__dots" aria-hidden="true">
			<?php for ( $oria_i = 0; $oria_i < 10; $oria_i++ ) : ?>
				<li class="<?php echo $oria_i < $oria_stats['experiences'] ? 'is-on' : ''; ?>"></li>
			<?php endfor; ?>
		</ol>
	</div>
</section>

<section class="wrap my">
	<div class="sec-head reveal">
		<div class="sec-head__text">
			<span class="micro"><?php esc_html_e( 'Stamps', 'oria' ); ?></span>
			<h2 class="h2"><?php esc_html_e( 'Places you have tried', 'oria' ); ?></h2>
		</div>
	</div>
	<div class="myempty" data-my-empty<?php echo $oria_tried ? ' hidden' : ''; ?>>
		<h3 class="h3"><?php esc_html_e( 'Your Wellness Passport is waiting for its first stamp.', 'oria' ); ?></h3>
		<p><?php esc_html_e( 'When you try an Oria-listed experience, mark it as tried and it will appear here.', 'oria' ); ?></p>
		<a class="btn btn--dark" href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Find something to try', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</div>
	<?php if ( $oria_tried ) : ?>
		<div class="mygrid" data-my-list>
			<?php foreach ( $oria_tried as $oria_id ) : ?>
				<?php get_template_part( 'template-parts/my/place', null, array( 'id' => $oria_id, 'mode' => 'tried' ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<section class="wrap my my--last" id="badges">
	<div class="sec-head reveal">
		<div class="sec-head__text">
			<span class="micro"><?php esc_html_e( 'Passport badges', 'oria' ); ?></span>
			<h2 class="h2"><?php echo $oria_earned ? esc_html( sprintf( /* translators: 1: earned, 2: total */ __( '%1$s of %2$s earned', 'oria' ), number_format_i18n( $oria_earned ), number_format_i18n( count( $oria_badges ) ) ) ) : esc_html__( 'Your first badge is closer than you think', 'oria' ); ?></h2>
		</div>
		<p class="sec-head__aside muted"><?php esc_html_e( 'Badges mark exploring, not effort: a first visit, a new kind of practice, a new suburb. Once earned, they stay.', 'oria' ); ?></p>
	</div>
	<div class="pbadges">
		<?php foreach ( $oria_badges as $oria_b ) : ?>
			<?php get_template_part( 'template-parts/my/badge', null, array( 'badge' => $oria_b ) ); ?>
		<?php endforeach; ?>
	</div>
</section>
