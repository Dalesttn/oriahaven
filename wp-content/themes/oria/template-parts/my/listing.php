<?php
/**
 * The owner's listing at a glance.
 *
 * Four questions, in the order somebody actually asks them: what does my
 * listing look like, what is missing, what should I do next, and did my
 * last change go through. Everything on this page answers one of those;
 * anything that answered none of them is not here.
 *
 * Deliberately not a form. An owner who opens this twice a year should
 * land on something they can read, not on eighty fields.
 */

declare(strict_types=1);

use Oria\Core\ListingEditor as Ed;
use Oria\Core\MyOria;
use Oria\Core\Tiers;

$oria_listing = Ed\listing_for( get_current_user_id() );
if ( ! $oria_listing ) {
	return;
}

$oria_score   = Ed\score( $oria_listing );
$oria_status  = Ed\status( $oria_listing );
$oria_nudges  = array_slice( $oria_score['missing'], 0, Ed\MAX_NUDGES );
$oria_pending = Ed\pending( $oria_listing );
$oria_stats   = Ed\stats( $oria_listing );
$oria_note    = Ed\stats_note( $oria_listing, $oria_stats );
$oria_views   = (int) ( array_column( $oria_stats, 'n', 'key' )['view'] ?? 0 );

$oria_area  = get_the_terms( $oria_listing, 'area' );
$oria_area  = ( is_array( $oria_area ) && $oria_area ) ? $oria_area[ count( $oria_area ) - 1 ]->name : '';
$oria_prac  = get_the_terms( $oria_listing, 'practice' );
$oria_prac  = ( is_array( $oria_prac ) && $oria_prac ) ? $oria_prac[0]->name : '';
$oria_thumb = get_the_post_thumbnail_url( $oria_listing, 'medium' );
$oria_tier  = Tiers\tier( $oria_listing );
?>

<section class="my mylist">

	<header class="mylhead">
		<?php if ( $oria_thumb ) : ?>
			<img class="mylhead__img" src="<?php echo esc_url( $oria_thumb ); ?>" alt="" width="96" height="96" loading="lazy" decoding="async">
		<?php else : ?>
			<span class="mylhead__img mylhead__img--none" aria-hidden="true"><?php echo esc_html( mb_substr( get_the_title( $oria_listing ), 0, 1 ) ); ?></span>
		<?php endif; ?>

		<div class="mylhead__text">
			<span class="mystatus mystatus--<?php echo esc_attr( $oria_status['key'] ); ?>"><?php echo esc_html( $oria_status['label'] ); ?></span>
			<h1 class="mylhead__name"><?php echo esc_html( get_the_title( $oria_listing ) ); ?></h1>
			<p class="mylhead__where">
				<?php
				$oria_bits = array_filter( array( $oria_prac, $oria_area ) );
				echo esc_html( $oria_bits ? implode( ' · ', $oria_bits ) : __( 'Your practice', 'oria' ) );
				?>
			</p>
			<p class="mylhead__note"><?php echo esc_html( $oria_status['note'] ); ?></p>
		</div>

		<div class="mylhead__acts">
			<a class="btn btn--dark" href="<?php echo esc_url( MyOria\url( 'listing-edit' ) ); ?>"><?php esc_html_e( 'Edit my listing', 'oria' ); ?></a>
			<a class="btn btn--ghost" href="<?php echo esc_url( get_permalink( $oria_listing ) ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'View live profile', 'oria' ); ?> <span aria-hidden="true">&#8599;</span>
			</a>
		</div>
	</header>

	<?php if ( $oria_pending ) : ?>
		<?php
		/*
		 * Something is waiting on us. The live value has not moved, and the
		 * owner can take the proposal back -- a change you cannot withdraw
		 * is a change you have to ring somebody about.
		 */
		$oria_writable = Ed\writable_fields();
		?>
		<div class="mylwait">
			<h2 class="mylwait__h"><?php esc_html_e( 'Waiting to be checked', 'oria' ); ?></h2>
			<p class="mylwait__p"><?php esc_html_e( 'Your listing is live exactly as it was. We will publish these once we have looked at them, usually within two working days.', 'oria' ); ?></p>
			<ul class="mylwait__list">
				<?php foreach ( $oria_pending as $oria_field => $oria_prop ) : ?>
					<li class="mylwait__item">
						<span class="mylwait__label"><?php echo esc_html( $oria_writable[ $oria_field ]['label'] ?? $oria_field ); ?></span>
						<span class="mylwait__val"><?php echo esc_html( is_array( $oria_prop['value'] ) ? __( 'Updated', 'oria' ) : (string) $oria_prop['value'] ); ?></span>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="oria_listing_withdraw">
							<input type="hidden" name="field" value="<?php echo esc_attr( $oria_field ); ?>">
							<?php wp_nonce_field( 'oria_listing_withdraw' ); ?>
							<button class="mylwait__undo" type="submit"><?php esc_html_e( 'Withdraw', 'oria' ); ?></button>
						</form>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="mylgrid">

		<?php
		/*
		 * Profile strength. A number, then the two or three jobs that would
		 * move it -- never a leaderboard, and never a promise that finishing
		 * it buys placement. The bar is labelled in words as well as drawn,
		 * because a colour is not a value.
		 */
		?>
		<section class="mylcard mylstrength">
			<h2 class="mylcard__h"><?php esc_html_e( 'Profile strength', 'oria' ); ?></h2>

			<p class="mylstrength__n">
				<b><?php echo esc_html( (string) $oria_score['pct'] ); ?>%</b>
				<span><?php esc_html_e( 'complete', 'oria' ); ?></span>
			</p>
			<div class="mylbar" role="img"
				aria-label="<?php echo esc_attr( sprintf( __( 'Your listing is %d%% complete.', 'oria' ), $oria_score['pct'] ) ); ?>">
				<span class="mylbar__fill" style="width:<?php echo esc_attr( (string) max( 2, $oria_score['pct'] ) ); ?>%"></span>
			</div>

			<?php if ( $oria_nudges ) : ?>
				<p class="mylstrength__p">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: how many suggestions follow */
							_n( 'One thing could help more people choose you.', '%d things could help more people choose you.', count( $oria_nudges ), 'oria' ),
							count( $oria_nudges )
						)
					);
					?>
				</p>
				<ul class="myltodo">
					<?php foreach ( $oria_nudges as $oria_job ) : ?>
						<?php
						/*
						 * Photos and practitioners have no section here yet, so
						 * their job sends the owner to the screen that does have
						 * one. A suggestion that leads nowhere is worse than no
						 * suggestion at all.
						 */
						$oria_href = $oria_job['section']
							? add_query_arg( 'section', $oria_job['section'], MyOria\url( 'listing-edit' ) )
							: (string) get_edit_post_link( $oria_listing, 'raw' );
						?>
						<li>
							<a class="myltodo__a" href="<?php echo esc_url( $oria_href ); ?>">
								<span class="myltodo__label"><?php echo esc_html( $oria_job['label'] ); ?></span>
								<span class="myltodo__go" aria-hidden="true">&rarr;</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="mylstrength__p"><?php esc_html_e( 'Everything we ask for is filled in. Keep your hours and prices current and you are done.', 'oria' ); ?></p>
			<?php endif; ?>

			<details class="mylhow">
				<summary><?php esc_html_e( 'How this is worked out', 'oria' ); ?></summary>
				<p><?php esc_html_e( 'We count the things a visitor needs in order to choose you: what you offer, what it costs, when you are open, where you are, how to reach you, and what the place looks like. Anything your plan does not include is left out of the total rather than counted against you. A full profile is not a promise of more views or a better position -- it just means nobody has to guess.', 'oria' ); ?></p>
			</details>
		</section>

		<?php
		/*
		 * Performance. With almost no traffic yet a wall of zeros reads as
		 * a verdict on the business, which it is not -- so under ten views
		 * the panel says what it is waiting for and points back at the
		 * checklist instead.
		 */
		?>
		<section class="mylcard mylperf">
			<h2 class="mylcard__h"><?php esc_html_e( 'The last 30 days', 'oria' ); ?></h2>

			<?php if ( ! Tiers\allows( $oria_listing, 'analytics' ) ) : ?>
				<p class="mylperf__none"><?php esc_html_e( 'See how many people find your profile, and what they do next, on the Claimed plan.', 'oria' ); ?></p>
			<?php elseif ( $oria_views < 10 ) : ?>
				<p class="mylperf__none"><?php esc_html_e( 'Your numbers will appear here once people start finding your profile. In the meantime, the list beside this is the fastest way to help them.', 'oria' ); ?></p>
			<?php else : ?>
				<ul class="mylstats">
					<?php foreach ( $oria_stats as $oria_stat ) : ?>
						<li class="mylstat">
							<b class="mylstat__n"><?php echo esc_html( number_format_i18n( $oria_stat['n'] ) ); ?></b>
							<span class="mylstat__l"><?php echo esc_html( $oria_stat['label'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $oria_note ) : ?>
					<p class="mylperf__note"><?php echo esc_html( $oria_note ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>
	</div>

	<?php
	/*
	 * Every section, with how far through it is. This is the map -- the
	 * editor's own rail repeats it, but somebody arriving here cold needs
	 * to see the whole shape of the job before they start it.
	 */
	?>
	<section class="mylsecs">
		<h2 class="mylsecs__h"><?php esc_html_e( 'Your profile, section by section', 'oria' ); ?></h2>
		<ul class="mylsecs__list">
			<?php foreach ( Ed\sections() as $oria_slug => $oria_sec ) : ?>
				<?php $oria_state = Ed\section_state( $oria_listing, $oria_slug ); ?>
				<li>
					<a class="mylsec is-<?php echo esc_attr( $oria_state ); ?>"
						href="<?php echo esc_url( add_query_arg( 'section', $oria_slug, MyOria\url( 'listing-edit' ) ) ); ?>">
						<span class="mylsec__dot" aria-hidden="true"></span>
						<span class="mylsec__text">
							<b class="mylsec__name"><?php echo esc_html( $oria_sec['label'] ); ?></b>
							<span class="mylsec__blurb"><?php echo esc_html( $oria_sec['blurb'] ); ?></span>
						</span>
						<span class="mylsec__state">
							<?php
							$oria_words = array(
								'done'   => __( 'Complete', 'oria' ),
								'part'   => __( 'Partly done', 'oria' ),
								'empty'  => __( 'Not started', 'oria' ),
								'locked' => __( 'On a paid plan', 'oria' ),
							);
							echo esc_html( $oria_words[ $oria_state ] ?? '' );
							?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php
		/*
		 * Photographs and practitioners are still edited in the old screen.
		 * Saying so, with the door open, is better than a section that
		 * looks editable and is not.
		 */
		?>
		<p class="mylsecs__more">
			<?php esc_html_e( 'Photos are still added in the older screen for now.', 'oria' ); ?>
			<a href="<?php echo esc_url( get_edit_post_link( $oria_listing ) ); ?>"><?php esc_html_e( 'Open it', 'oria' ); ?></a>
		</p>
	</section>

	<?php if ( 'featured' !== $oria_tier ) : ?>
		<p class="mylplan">
			<?php
			printf(
				/* translators: %s: the plan name */
				esc_html__( 'You are on the %s plan.', 'oria' ),
				esc_html( ucfirst( $oria_tier ) )
			);
			?>
		</p>
	<?php endif; ?>

</section>
