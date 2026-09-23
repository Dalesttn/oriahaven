<?php
/**
 * My Oria: the dashboard.
 *
 * Three questions in order, which is the whole layout: what matters to me
 * today, what have I saved, and what might I try next. Everything on it is
 * read from this member's own rows -- no invented metrics, no progress bar
 * over browsing, and no module that appears before it has something true
 * to say.
 *
 * Deliberately absent: saved events, because those live on the device and
 * not the account, so this cannot honestly list them; plans and journeys,
 * because no such data exists yet; and anything resembling a score.
 */

declare(strict_types=1);

use Oria\Core\Activity;
use Oria\Core\MyOria;
use Oria\Core\Passport;
use Oria\Core\Recommend;

$oria_uid   = get_current_user_id();
$oria_name  = MyOria\first_name( $oria_uid );
$oria_sum   = MyOria\summary( $oria_uid );
$oria_saved = Activity\ids( $oria_uid, Activity\SAVED );
$oria_tried = Activity\ids( $oria_uid, Activity\TRIED );
$oria_next  = Passport\next_up( $oria_uid );
$oria_stats = Passport\stats( $oria_uid );
$oria_prefs = Recommend\prefs( $oria_uid );
$oria_hasp  = Recommend\has_prefs( $oria_uid );
$oria_recs  = Recommend\for_user( $oria_uid, 3 );
$oria_dir   = get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' );

if ( function_exists( '\Oria\Theme\prime_listings' ) ) {
	\Oria\Theme\prime_listings( array_merge( array_slice( $oria_saved, 0, 4 ), $oria_recs ) );
}

/*
 * The one thing worth doing next, ordered by how much it is actually
 * worth: somebody with nothing saved needs a first save far more than
 * somebody with four needs a badge. Every branch leads somewhere real --
 * none of these is a button that does nothing.
 */
if ( ! $oria_saved ) {
	$oria_continue = array(
		'eyebrow' => __( 'Start here', 'oria' ),
		'title'   => __( 'Your next favourite place can live here', 'oria' ),
		'line'    => __( 'Save places while you explore and they will be ready when you are.', 'oria' ),
		'cta'     => __( 'Explore wellness places', 'oria' ),
		'url'     => $oria_dir,
		'kind'    => 'first-save',
	);
} elseif ( ! $oria_hasp ) {
	$oria_continue = array(
		'eyebrow' => __( 'Two questions', 'oria' ),
		'title'   => __( 'Tell us what you are after', 'oria' ),
		'line'    => __( 'Pick a couple of interests and a part of Perth, and what we suggest gets closer to what you want. You can change them any time.', 'oria' ),
		'cta'     => __( 'Set my interests', 'oria' ),
		'url'     => MyOria\url( 'profile' ),
		'kind'    => 'prefs',
	);
} elseif ( $oria_next ) {
	$oria_continue = array(
		'eyebrow' => __( 'Your passport', 'oria' ),
		'title'   => (string) $oria_next['label'],
		'line'    => (string) $oria_next['hint'],
		'cta'     => __( 'Open my passport', 'oria' ),
		'url'     => MyOria\url( 'passport' ),
		'kind'    => 'passport',
	);
} else {
	$oria_continue = array(
		'eyebrow' => __( 'Where you left off', 'oria' ),
		'title'   => __( 'Pick up your saved places', 'oria' ),
		'line'    => __( 'Everything you have kept, in one list, with what you have already tried marked off.', 'oria' ),
		'cta'     => __( 'Open my saved places', 'oria' ),
		'url'     => MyOria\url( 'saved' ),
		'kind'    => 'saved',
	);
}

/*
 * Saved but not yet tried. This is the honest "this week": not a calendar,
 * which would imply Oria had booked something on somebody's behalf, but
 * the places they meant to get to and have not.
 */
$oria_todo = array_values( array_diff( $oria_saved, $oria_tried ) );
?>
<div class="myhome">

	<section class="myhello" aria-labelledby="myHelloTitle">
		<h1 class="myhello__h" id="myHelloTitle">
			<?php
			echo esc_html(
				$oria_name
					/* translators: %s: member's first name */
					? sprintf( __( 'Hello, %s', 'oria' ), $oria_name )
					: __( 'Hello', 'oria' )
			);
			?>
		</h1>
		<p class="myhello__q"><?php esc_html_e( 'What would feel good today?', 'oria' ); ?></p>
		<div class="myintents">
			<?php
			/*
			 * Each chip is a real filtered search on the public directory,
			 * using the same intent vocabulary the profile stores -- one
			 * list of intents on this site, not two.
			 */
			foreach ( array_slice( Recommend\INTENTS, 0, 5, true ) as $oria_k => $oria_label ) :
				?>
				<a class="myintent" href="<?php echo esc_url( add_query_arg( 'intent', $oria_k, $oria_dir ) ); ?>"
					data-oria-event="my_oria_intent_click"><?php echo esc_html( $oria_label ); ?></a>
			<?php endforeach; ?>
		</div>
	</section>

	<?php
	/*
	 * Above the split, because a member's credit balance is the thing they
	 * opened this page for. It draws nothing at all for somebody who is not
	 * a member while the Pass is not yet on sale.
	 */
	get_template_part( 'template-parts/my/pass-card' );

	/*
	 * And, for somebody who runs a practice here and has not opened a
	 * place yet, the other side of it. Draws nothing once they have.
	 */
	get_template_part( 'template-parts/pass/banner', null, array( 'audience' => 'practice', 'tone' => 'slim', 'where' => 'dashboard' ) );
	?>

	<div class="mygrid mygrid--split">
		<section class="mycard mycard--feature" aria-labelledby="myContinueTitle">
			<p class="mycard__eyebrow"><?php echo esc_html( $oria_continue['eyebrow'] ); ?></p>
			<h2 class="mycard__title" id="myContinueTitle"><?php echo esc_html( $oria_continue['title'] ); ?></h2>
			<p class="mycard__line"><?php echo esc_html( $oria_continue['line'] ); ?></p>
			<a class="btn btn--light mycard__cta" href="<?php echo esc_url( $oria_continue['url'] ); ?>"
				data-oria-event="my_oria_continue_click" data-kind="<?php echo esc_attr( $oria_continue['kind'] ); ?>">
				<?php echo esc_html( $oria_continue['cta'] ); ?> <span aria-hidden="true">&rarr;</span>
			</a>
		</section>

		<section class="mycard" aria-labelledby="myWeekTitle">
			<p class="mycard__eyebrow"><?php esc_html_e( 'Still waiting', 'oria' ); ?></p>
			<h2 class="mycard__title" id="myWeekTitle">
				<?php
				echo $oria_todo
					? esc_html(
						sprintf(
							/* translators: %s: how many saved places have not been tried */
							_n( '%s place you have not been to yet', '%s places you have not been to yet', count( $oria_todo ), 'oria' ),
							number_format_i18n( count( $oria_todo ) )
						)
					)
					: esc_html__( 'Nothing left on your list', 'oria' );
				?>
			</h2>
			<p class="mycard__line">
				<?php
				echo $oria_todo
					? esc_html__( 'Saved, and not yet marked as tried. No rush — the list keeps.', 'oria' )
					: esc_html__( 'You have been to everything you saved. Somewhere new, perhaps.', 'oria' );
				?>
			</p>
			<a class="mycard__more" href="<?php echo esc_url( $oria_todo ? MyOria\url( 'saved' ) : $oria_dir ); ?>">
				<?php echo esc_html( $oria_todo ? __( 'See the list', 'oria' ) : __( 'Find somewhere new', 'oria' ) ); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</section>
	</div>

	<section class="mysec" aria-labelledby="mySavedTitle">
		<header class="mysec__head">
			<h2 class="mysec__title" id="mySavedTitle"><?php esc_html_e( 'Saved for later', 'oria' ); ?></h2>
			<?php if ( count( $oria_saved ) > 4 ) : ?>
				<a class="mysec__more" href="<?php echo esc_url( MyOria\url( 'saved' ) ); ?>">
					<?php
					printf(
						/* translators: %s: how many saved places */
						esc_html__( 'View all %s', 'oria' ),
						esc_html( number_format_i18n( count( $oria_saved ) ) )
					);
					?>
					<span aria-hidden="true">&rarr;</span>
				</a>
			<?php endif; ?>
		</header>

		<?php if ( $oria_saved ) : ?>
			<div class="myrailcards">
				<?php foreach ( array_slice( $oria_saved, 0, 4 ) as $oria_id ) : ?>
					<?php get_template_part( 'template-parts/my/place', null, array( 'id' => $oria_id, 'mode' => 'saved' ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="mycard mycard--empty">
				<h3 class="mycard__title"><?php esc_html_e( 'Nothing saved yet', 'oria' ); ?></h3>
				<p class="mycard__line"><?php esc_html_e( 'Tap the heart on any place while you are exploring and it will be here waiting.', 'oria' ); ?></p>
				<a class="btn btn--dark" href="<?php echo esc_url( $oria_dir ); ?>" data-oria-event="my_oria_empty_state_cta">
					<?php esc_html_e( 'Explore wellness places', 'oria' ); ?> <span aria-hidden="true">&rarr;</span>
				</a>
			</div>
		<?php endif; ?>
	</section>

	<div class="mygrid mygrid--split">
		<section class="mysec mysec--flush" aria-labelledby="myRecsTitle">
			<header class="mysec__head">
				<div>
					<h2 class="mysec__title" id="myRecsTitle"><?php esc_html_e( 'You might like', 'oria' ); ?></h2>
					<p class="mysec__why">
						<?php
						/*
						 * Why these, in words -- and nothing about health,
						 * mood or anything inferred. Either they told us,
						 * or they saved something.
						 */
						echo $oria_hasp
							? esc_html__( 'Because of the interests on your profile', 'oria' )
							: esc_html__( 'Because of the places you have saved', 'oria' );
						?>
						<a class="mysec__edit" href="<?php echo esc_url( MyOria\url( 'profile' ) ); ?>"><?php esc_html_e( 'Edit', 'oria' ); ?></a>
					</p>
				</div>
			</header>

			<?php if ( $oria_recs ) : ?>
				<ul class="myrecs">
					<?php foreach ( $oria_recs as $oria_id ) : ?>
						<?php
						$oria_r_areas = wp_get_post_terms( (int) $oria_id, 'area' );
						$oria_r_cats  = wp_get_post_terms( (int) $oria_id, 'practice' );
						?>
						<li>
							<a class="myrec" href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>">
								<b class="myrec__name"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_id ) ) ); ?></b>
								<span class="myrec__meta">
									<?php
									echo esc_html(
										implode(
											' · ',
											array_filter(
												array(
													! is_wp_error( $oria_r_areas ) && $oria_r_areas ? \Oria\Theme\tname( $oria_r_areas[0] ) : '',
													! is_wp_error( $oria_r_cats ) && $oria_r_cats ? \Oria\Theme\tname( $oria_r_cats[0] ) : '',
												)
											)
										)
									);
									?>
								</span>
								<span class="myrec__go" aria-hidden="true">&rarr;</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="mysec__none"><?php esc_html_e( 'Save a place or two and suggestions will start appearing here.', 'oria' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="mypassport" aria-labelledby="myPassTitle">
			<span class="mypassport__seal" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 6.4 2.6" stroke-linecap="round"/></svg>
			</span>
			<p class="mypassport__eyebrow"><?php esc_html_e( 'Wellness Passport', 'oria' ); ?></p>
			<h2 class="mypassport__title" id="myPassTitle">
				<?php
				echo $oria_stats['experiences']
					? esc_html(
						sprintf(
							/* translators: %s: how many places tried */
							_n( '%s place explored', '%s places explored', $oria_stats['experiences'], 'oria' ),
							number_format_i18n( $oria_stats['experiences'] )
						)
					)
					: esc_html__( 'It starts with one discovery', 'oria' );
				?>
			</h2>
			<p class="mypassport__line">
				<?php
				if ( $oria_stats['experiences'] ) {
					echo esc_html(
						implode(
							' · ',
							array_filter(
								array(
									sprintf( _n( '%s kind of practice', '%s kinds of practice', $oria_stats['types'], 'oria' ), number_format_i18n( $oria_stats['types'] ) ),
									$oria_sum['badges'] ? sprintf( _n( '%s badge', '%s badges', $oria_sum['badges'], 'oria' ), number_format_i18n( $oria_sum['badges'] ) ) : '',
								)
							)
						)
					);
				} else {
					esc_html_e( 'Mark a place as tried and it is kept here — a private record of where you have been.', 'oria' );
				}
				?>
			</p>
			<a class="mypassport__cta" href="<?php echo esc_url( MyOria\url( 'passport' ) ); ?>">
				<?php echo esc_html( $oria_stats['experiences'] ? __( 'Open my passport', 'oria' ) : __( 'How it works', 'oria' ) ); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</section>
	</div>

	<?php
	/*
	 * Their own corner of Perth, from the region on their profile -- never
	 * from where the browser thinks they are. Left out entirely when they
	 * have not chosen one, rather than guessing at Fremantle.
	 */
	$oria_guide = null;
	if ( '' !== $oria_prefs['area'] && 'anywhere' !== $oria_prefs['area'] && function_exists( '\Oria\Core\AreaContext\for_term' ) ) {
		$oria_region = get_term_by( 'slug', $oria_prefs['area'], 'area' );
		if ( $oria_region instanceof WP_Term ) {
			$oria_guide = \Oria\Core\AreaContext\for_term( $oria_region );
		}
	}
	?>
	<?php if ( $oria_guide ) : ?>
		<section class="mylocal" aria-labelledby="myLocalTitle">
			<p class="mylocal__eyebrow"><?php esc_html_e( 'Your local guide', 'oria' ); ?></p>
			<h2 class="mylocal__title" id="myLocalTitle">
				<?php
				printf(
					/* translators: %s: area name */
					esc_html__( 'A slower day in %s', 'oria' ),
					esc_html( (string) $oria_guide['name'] )
				);
				?>
			</h2>
			<p class="mylocal__line">
				<?php
				printf(
					/* translators: 1: number of places, 2: area name */
					esc_html__( '%1$s hand-checked places in %2$s, what is on, and ideas for the day.', 'oria' ),
					esc_html( number_format_i18n( (int) $oria_guide['places'] ) ),
					esc_html( (string) $oria_guide['name'] )
				);
				?>
			</p>
			<a class="btn btn--light" href="<?php echo esc_url( (string) $oria_guide['url'] ); ?>"
				data-oria-event="my_oria_local_guide_click">
				<?php
				printf(
					/* translators: %s: area name */
					esc_html__( 'Explore %s', 'oria' ),
					esc_html( (string) $oria_guide['name'] )
				);
				?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</section>
	<?php endif; ?>
</div>
