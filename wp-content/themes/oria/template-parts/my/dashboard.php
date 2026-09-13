<?php
/**
 * My Oria dashboard: what have I saved, what should I try next, what have
 * I explored. Four blocks, each of which stands down when there is nothing
 * to show and says what would fill it.
 */

declare(strict_types=1);

use Oria\Core\Activity;
use Oria\Core\MyOria;
use Oria\Core\Passport;
use Oria\Core\Recommend;

$oria_uid    = get_current_user_id();
$oria_name   = MyOria\first_name( $oria_uid );
$oria_sum    = MyOria\summary( $oria_uid );
$oria_saved  = Activity\ids( $oria_uid, Activity\SAVED );
$oria_upnext = array_slice( $oria_saved, 0, 3 );
$oria_badges = Passport\evaluate( $oria_uid );
$oria_earned = array_values( array_filter( $oria_badges, static fn( $b ) => $b['earned'] ) );
$oria_next   = Passport\next_up( $oria_uid );
$oria_stats  = Passport\stats( $oria_uid );
$oria_prefs  = Recommend\has_prefs( $oria_uid );
$oria_recs   = Recommend\for_user( $oria_uid, 6 );
if ( function_exists( '\Oria\Theme\prime_listings' ) ) {
	\Oria\Theme\prime_listings( array_merge( $oria_upnext, $oria_recs ) );
}
?>
<section class="wrap my">
	<div class="my__hello">
		<span class="micro"><?php esc_html_e( 'My Oria', 'oria' ); ?></span>
		<h1 class="h1"><?php echo esc_html( $oria_name ? sprintf( /* translators: %s: first name */ __( 'Welcome back, %s', 'oria' ), $oria_name ) : __( 'Welcome back', 'oria' ) ); ?></h1>
		<p class="lede"><?php esc_html_e( 'Your saved places, experiences and wellness journey — all in one place.', 'oria' ); ?></p>
	</div>

	<div class="mystats">
		<a class="mystat" href="<?php echo esc_url( MyOria\url( 'saved' ) ); ?>">
			<span class="mystat__n" data-my-count="saved"><?php echo esc_html( number_format_i18n( $oria_sum['saved'] ) ); ?></span>
			<span class="mystat__l"><?php echo esc_html( _n( 'Saved place', 'Saved places', $oria_sum['saved'], 'oria' ) ); ?></span>
		</a>
		<a class="mystat" href="<?php echo esc_url( MyOria\url( 'passport' ) ); ?>">
			<span class="mystat__n" data-my-count="tried"><?php echo esc_html( number_format_i18n( $oria_sum['tried'] ) ); ?></span>
			<span class="mystat__l"><?php echo esc_html( _n( 'Experience tried', 'Experiences tried', $oria_sum['tried'], 'oria' ) ); ?></span>
		</a>
		<a class="mystat" href="<?php echo esc_url( MyOria\url( 'passport' ) . '#badges' ); ?>">
			<span class="mystat__n" data-my-count="badges"><?php echo esc_html( number_format_i18n( $oria_sum['badges'] ) ); ?></span>
			<span class="mystat__l"><?php echo esc_html( _n( 'Passport badge', 'Passport badges', $oria_sum['badges'], 'oria' ) ); ?></span>
		</a>
	</div>
</section>

<?php if ( ! $oria_prefs ) : ?>
	<section class="wrap my">
		<?php
		/*
		 * The optional second step of registration, here rather than in the
		 * way of it. Two questions, tick what fits, or ignore the block.
		 */
		?>
		<form class="myprefs reveal" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_my_profile">
			<input type="hidden" name="first_name" value="<?php echo esc_attr( $oria_name ); ?>">
			<input type="hidden" name="return" value="dashboard">
			<?php wp_nonce_field( 'oria_my_profile', 'oria_my_nonce' ); ?>
			<div class="myprefs__q">
				<span class="micro"><?php esc_html_e( 'Optional', 'oria' ); ?></span>
				<h2 class="h3"><?php esc_html_e( 'What are you looking for?', 'oria' ); ?></h2>
				<div class="mychips">
					<?php foreach ( Recommend\INTENTS as $oria_k => $oria_l ) : ?>
						<label class="mychip"><input type="checkbox" name="intents[]" value="<?php echo esc_attr( $oria_k ); ?>"><span><?php echo esc_html( $oria_l ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="myprefs__q">
				<h2 class="h3"><?php esc_html_e( 'What would you like to explore?', 'oria' ); ?></h2>
				<div class="mychips">
					<?php foreach ( Recommend\INTERESTS as $oria_k => $oria_i ) : ?>
						<label class="mychip"><input type="checkbox" name="interests[]" value="<?php echo esc_attr( $oria_k ); ?>"><span><?php echo esc_html( $oria_i['label'] ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="myprefs__acts">
				<button class="btn btn--dark" type="submit"><?php esc_html_e( 'Save my interests', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				<span class="hint"><?php esc_html_e( 'These only shape what we suggest. You can change them on your profile any time.', 'oria' ); ?></span>
			</div>
		</form>
	</section>
<?php endif; ?>

<section class="wrap my" data-my-section="upnext">
	<div class="sec-head reveal">
		<div class="sec-head__text">
			<span class="micro"><?php esc_html_e( 'Up next', 'oria' ); ?></span>
			<h2 class="h2"><?php echo $oria_saved ? esc_html( sprintf( /* translators: %s: count */ _n( '%s place you want to try', '%s places you want to try', count( $oria_saved ), 'oria' ), number_format_i18n( count( $oria_saved ) ) ) ) : esc_html__( 'Nothing saved yet', 'oria' ); ?></h2>
		</div>
		<?php if ( $oria_saved ) : ?>
			<a class="btn btn--ghost" href="<?php echo esc_url( MyOria\url( 'saved' ) ); ?>"><?php esc_html_e( 'See my saved places', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		<?php endif; ?>
	</div>
	<?php if ( $oria_upnext ) : ?>
		<div class="mygrid">
			<?php foreach ( $oria_upnext as $oria_id ) : ?>
				<?php get_template_part( 'template-parts/my/place', null, array( 'id' => $oria_id, 'mode' => 'saved' ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="myempty">
			<p><?php esc_html_e( 'Explore wellness experiences around Perth and tap the heart to save anything you’d like to try.', 'oria' ); ?></p>
			<a class="btn btn--dark" href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Explore practices', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		</div>
	<?php endif; ?>
</section>

<section class="wrap my">
	<div class="mypass reveal">
		<div class="mypass__text">
			<span class="micro"><?php esc_html_e( 'Your passport', 'oria' ); ?></span>
			<?php if ( $oria_stats['experiences'] ) : ?>
				<p class="mypass__line">
					<?php
					echo esc_html( implode( ' · ', array(
						sprintf( _n( '%s experience explored', '%s experiences explored', $oria_stats['experiences'], 'oria' ), number_format_i18n( $oria_stats['experiences'] ) ),
						sprintf( _n( '%s kind of practice', '%s kinds of practice', $oria_stats['types'], 'oria' ), number_format_i18n( $oria_stats['types'] ) ),
						sprintf( _n( '%s badge earned', '%s badges earned', count( $oria_earned ), 'oria' ), number_format_i18n( count( $oria_earned ) ) ),
					) ) );
					?>
				</p>
				<ol class="mydots" aria-hidden="true">
					<?php for ( $oria_i = 0; $oria_i < 10; $oria_i++ ) : ?>
						<li class="<?php echo $oria_i < $oria_stats['experiences'] ? 'is-on' : ''; ?>"></li>
					<?php endfor; ?>
				</ol>
			<?php else : ?>
				<p class="mypass__line"><?php esc_html_e( 'Your Wellness Passport is waiting for its first stamp. When you try a place listed here, mark it as tried and it appears in your passport.', 'oria' ); ?></p>
			<?php endif; ?>
			<?php if ( $oria_next ) : ?>
				<p class="mypass__next"><span><?php esc_html_e( 'Next badge:', 'oria' ); ?></span> <?php echo esc_html( $oria_next['label'] ); ?> — <?php echo esc_html( $oria_next['hint'] ); ?></p>
			<?php endif; ?>
		</div>
		<a class="btn btn--light" href="<?php echo esc_url( MyOria\url( 'passport' ) ); ?>"><?php esc_html_e( 'Open my passport', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</div>
</section>

<?php if ( $oria_recs ) : ?>
	<section class="wrap my">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'You might like', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'Places to try next', 'oria' ); ?></h2>
			</div>
			<p class="sec-head__aside muted"><?php echo $oria_prefs ? esc_html__( "Based on what you're interested in and the places you've saved.", 'oria' ) : esc_html__( 'Based on the places you’ve saved and our editors’ picks. Tell us what you’re after on your profile for better ones.', 'oria' ); ?></p>
		</div>
		<div class="grid grid-3 mygrid--cards">
			<?php
			global $post;
			foreach ( $oria_recs as $oria_id ) {
				$post = get_post( $oria_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				if ( ! $post ) {
					continue;
				}
				setup_postdata( $post );
				get_template_part( 'template-parts/listing-card' );
			}
			wp_reset_postdata();
			?>
		</div>
	</section>
<?php endif; ?>

<section class="wrap my my--last" id="badges">
	<div class="sec-head reveal">
		<div class="sec-head__text">
			<span class="micro"><?php esc_html_e( 'Your badges', 'oria' ); ?></span>
			<h2 class="h2"><?php echo $oria_earned ? esc_html( sprintf( _n( '%s badge earned', '%s badges earned', count( $oria_earned ), 'oria' ), number_format_i18n( count( $oria_earned ) ) ) ) : esc_html__( 'Your first badge is closer than you think', 'oria' ); ?></h2>
		</div>
		<a class="btn btn--ghost" href="<?php echo esc_url( MyOria\url( 'passport' ) . '#badges' ); ?>"><?php esc_html_e( 'All badges', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</div>
	<?php if ( $oria_earned ) : ?>
		<div class="pbadges pbadges--row">
			<?php foreach ( $oria_earned as $oria_b ) : ?>
				<?php get_template_part( 'template-parts/my/badge', null, array( 'badge' => $oria_b, 'compact' => true ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="muted"><?php esc_html_e( 'Try your first wellness experience and mark it as tried to earn First Step.', 'oria' ); ?></p>
	<?php endif; ?>
</section>
