<?php
/**
 * A Journey.
 *
 * Two kinds share the type. A Micro Reset is a guided hour and gets the
 * layout below: hero, the prompts, and the practical detail underneath. A
 * day out is a piece of writing with an itinerary in it, and keeps the
 * article layout it already had.
 *
 * The order is deliberate. The guided part sits directly under the hero,
 * above the prose and the practical panel, because somebody opening this
 * page is usually standing in the park with their phone out. A long
 * introduction above the start button would be written for a search engine
 * at the expense of the person who is already here.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\Reset;

if ( ! function_exists( '\Oria\Core\Reset\is_reset' ) || ! Reset\is_reset( get_the_ID() ) ) {
	// Not a reset: the article layout, unchanged.
	require locate_template( 'single.php' );
	return;
}

the_post();

$oria_id     = (int) get_the_ID();
$oria_r      = Reset\data( $oria_id );
$oria_stages = $oria_r['stages'];
$oria_panel  = Reset\panel( $oria_id );
$oria_rows   = Reset\practical( $oria_id );

get_header();
?>

<article class="reset" data-reset="<?php echo esc_attr( (string) $oria_id ); ?>" data-reset-slug="<?php echo esc_attr( get_post_field( 'post_name', $oria_id ) ); ?>">

	<?php
	/*
	 * The hero. The image is the page's LCP, so it is the one thing that
	 * loads eagerly and at a real size; everything below it waits.
	 */
	?>
	<header class="rhero<?php echo has_post_thumbnail( $oria_id ) ? ' rhero--img' : ''; ?>">
		<?php if ( has_post_thumbnail( $oria_id ) ) : ?>
			<div class="rhero__img">
				<?php echo get_the_post_thumbnail( $oria_id, 'full', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'alt' => '' ) ); ?>
			</div>
		<?php endif; ?>
		<div class="rhero__in wrap">
			<?php if ( '' !== $oria_r['eyebrow'] ) : ?>
				<p class="micro rhero__eyebrow"><?php echo esc_html( $oria_r['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="rhero__title"><?php the_title(); ?></h1>
			<?php if ( '' !== $oria_r['hook'] ) : ?>
				<p class="rhero__hook"><?php echo esc_html( $oria_r['hook'] ); ?></p>
			<?php endif; ?>
			<?php if ( $oria_stages ) : ?>
				<p class="rhero__acts">
					<a class="btn btn--light" href="#reset-start" data-reset-begin><?php esc_html_e( 'Start my reset', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
					<?php if ( $oria_rows ) : ?>
						<a class="rhero__alt" href="#plan"><?php esc_html_e( 'View route details', 'oria' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	</header>

	<?php
	/*
	 * At a glance. Only the facts that have been filled in -- an empty row
	 * is not drawn, so the strip never carries a label with nothing after it.
	 */
	?>
	<?php if ( $oria_r['glance'] ) : ?>
		<section class="wrap rglance" aria-label="<?php esc_attr_e( 'At a glance', 'oria' ); ?>">
			<dl class="rglance__list">
				<?php foreach ( $oria_r['glance'] as $oria_g ) : ?>
					<div class="rglance__item">
						<dt><?php echo esc_html( $oria_g['label'] ); ?></dt>
						<dd><?php echo esc_html( $oria_g['value'] ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		</section>
	<?php endif; ?>

	<?php if ( '' !== $oria_r['intro'] ) : ?>
		<section class="wrap rintro">
			<?php foreach ( preg_split( '/\n\s*\n/', $oria_r['intro'] ) as $oria_p ) : ?>
				<?php if ( '' !== trim( (string) $oria_p ) ) : ?>
					<p><?php echo esc_html( trim( (string) $oria_p ) ); ?></p>
				<?php endif; ?>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * The seasonal panel, or the evergreen one that replaces it. Which of
	 * the two appears is decided on the server in Perth time, so the page
	 * cannot sit in a cache telling somebody a festival is on after it has
	 * closed. See Reset\panel().
	 */
	?>
	<?php if ( $oria_panel ) : ?>
		<section class="wrap rseason rseason--<?php echo esc_attr( $oria_panel['kind'] ); ?>">
			<div class="rseason__card">
				<h2 class="rseason__title"><?php echo esc_html( $oria_panel['title'] ); ?></h2>
				<?php if ( '' !== $oria_panel['body'] ) : ?>
					<p class="rseason__body"><?php echo esc_html( $oria_panel['body'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $oria_panel['cta_url'] && '' !== $oria_panel['cta_label'] ) : ?>
					<p class="rseason__acts">
						<a class="btn btn--sm btn--dark" href="<?php echo esc_url( $oria_panel['cta_url'] ); ?>" target="_blank" rel="noopener" data-oria-event="micro_reset_festival_click">
							<?php echo esc_html( $oria_panel['cta_label'] ); ?>
							<span class="sr-only"> <?php esc_html_e( '(opens in a new tab)', 'oria' ); ?></span>
						</a>
						<?php if ( $oria_stages ) : ?>
							<a class="rseason__alt" href="#reset-start" data-reset-begin><?php esc_html_e( 'Start the reset', 'oria' ); ?></a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * The reset itself.
	 *
	 * Every stage is rendered open and readable with no scripting at all --
	 * the page is a complete set of instructions on its own. The script adds
	 * the progress, the timers and the resume; it never supplies the words.
	 */
	?>
	<?php if ( $oria_stages ) : ?>
		<section class="wrap rstages" id="reset-start" aria-labelledby="reset-stages-h">
			<h2 class="rstages__h" id="reset-stages-h"><?php esc_html_e( 'Your reset, step by step', 'oria' ); ?></h2>

			<p class="rstages__progress" data-reset-progress hidden>
				<span data-reset-progress-text></span>
				<button type="button" class="rstages__restart" data-reset-restart><?php esc_html_e( 'Start again', 'oria' ); ?></button>
			</p>

			<ol class="rstages__list">
				<?php foreach ( $oria_stages as $oria_i => $oria_s ) : ?>
					<li class="rstage" data-stage="<?php echo (int) ( $oria_i + 1 ); ?>">
						<div class="rstage__head">
							<p class="rstage__when">
								<span class="rstage__n"><?php echo esc_html( sprintf( /* translators: 1: stage number, 2: total */ __( 'Stage %1$d of %2$d', 'oria' ), $oria_i + 1, count( $oria_stages ) ) ); ?></span>
								<?php if ( '' !== $oria_s['when'] ) : ?>
									<span class="rstage__mins"><?php echo esc_html( $oria_s['when'] ); ?></span>
								<?php endif; ?>
							</p>
							<h3 class="rstage__title"><?php echo esc_html( $oria_s['title'] ); ?></h3>
						</div>

						<?php if ( '' !== $oria_s['body'] ) : ?>
							<p class="rstage__body"><?php echo esc_html( $oria_s['body'] ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $oria_s['action'] ) : ?>
							<p class="rstage__action"><?php echo esc_html( $oria_s['action'] ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $oria_s['safety'] ) : ?>
							<p class="rstage__safety"><?php echo esc_html( $oria_s['safety'] ); ?></p>
						<?php endif; ?>

						<?php if ( $oria_s['timer'] > 0 ) : ?>
							<?php
							/*
							 * A timer nobody has to obey. It counts down in
							 * silence, it can be paused or ended at any point,
							 * and nothing is withheld if it never finishes.
							 */
							?>
							<div class="rtimer" data-timer="<?php echo (int) $oria_s['timer']; ?>">
								<p class="rtimer__face" data-timer-face aria-live="off"><?php echo esc_html( Reset\clock( $oria_s['timer'] ) ); ?></p>
								<p class="rtimer__acts">
									<button type="button" class="btn btn--sm btn--ghost" data-timer-start><?php esc_html_e( 'Start the timer', 'oria' ); ?></button>
									<button type="button" class="btn btn--sm btn--ghost" data-timer-pause hidden><?php esc_html_e( 'Pause', 'oria' ); ?></button>
									<button type="button" class="rtimer__end" data-timer-end hidden><?php esc_html_e( 'End early', 'oria' ); ?></button>
								</p>
							</div>
						<?php endif; ?>

						<?php if ( $oria_s['responses'] ) : ?>
							<?php
							/*
							 * The reader's own word for how they feel. It stays
							 * in their browser: it is never posted, never saved
							 * against them, and never sent to analytics.
							 */
							?>
							<fieldset class="rpick" data-reset-pick>
								<legend class="rpick__legend"><?php esc_html_e( 'If you want to, choose one. This stays on your phone.', 'oria' ); ?></legend>
								<div class="rpick__opts">
									<?php foreach ( $oria_s['responses'] as $oria_o ) : ?>
										<button type="button" class="rpick__opt" data-reset-word="<?php echo esc_attr( $oria_o ); ?>" aria-pressed="false"><?php echo esc_html( $oria_o ); ?></button>
									<?php endforeach; ?>
								</div>
							</fieldset>
						<?php endif; ?>

						<p class="rstage__done">
							<button type="button" class="btn btn--sm btn--dark" data-reset-done><?php esc_html_e( 'Done, next', 'oria' ); ?></button>
						</p>
					</li>
				<?php endforeach; ?>
			</ol>

			<div class="rdone" data-reset-complete hidden>
				<h3 class="rdone__h"><?php esc_html_e( 'Your reset is complete.', 'oria' ); ?></h3>
				<?php if ( '' !== $oria_r['complete'] ) : ?>
					<p class="rdone__body"><?php echo esc_html( $oria_r['complete'] ); ?></p>
				<?php endif; ?>
				<p class="rdone__acts">
					<button type="button" class="btn btn--sm btn--dark" data-reset-save><?php esc_html_e( 'Save this reset', 'oria' ); ?></button>
					<a class="rdone__alt" href="<?php echo esc_url( home_url( '/journeys/' ) ); ?>"><?php esc_html_e( 'Try another Perth reset', 'oria' ); ?></a>
				</p>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( trim( (string) get_the_content() ) !== '' ) : ?>
		<section class="wrap rprose prose">
			<?php the_content(); ?>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * Plan your visit. Every row here has been walked and measured by a
	 * person; anything that has not is simply absent. Reset\practical()
	 * drops empty values rather than printing "TBC", so the panel is short
	 * and true early on and grows as the route is checked.
	 */
	?>
	<?php if ( $oria_rows ) : ?>
		<section class="wrap rplan" id="plan" aria-labelledby="rplan-h">
			<h2 class="rplan__h" id="rplan-h"><?php esc_html_e( 'Plan your walk', 'oria' ); ?></h2>
			<dl class="rplan__list">
				<?php foreach ( $oria_rows as $oria_row ) : ?>
					<div class="rplan__row">
						<dt><?php echo esc_html( $oria_row['label'] ); ?></dt>
						<dd>
							<?php if ( '' !== $oria_row['url'] ) : ?>
								<a href="<?php echo esc_url( $oria_row['url'] ); ?>" target="_blank" rel="noopener" data-oria-event="micro_reset_map_click"><?php echo esc_html( $oria_row['value'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $oria_row['value'] ); ?>
							<?php endif; ?>
						</dd>
					</div>
				<?php endforeach; ?>
			</dl>
			<?php if ( '' !== $oria_r['verified'] ) : ?>
				<p class="rplan__checked"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Route last checked %s.', 'oria' ), $oria_r['verified'] ) ); ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * Safety, in the open. This is the section that says what the reset is
	 * not: it is a walk somebody chooses to take, not care, and nothing on
	 * this page treats anything.
	 */
	?>
	<section class="wrap rcare" aria-labelledby="rcare-h">
		<h2 class="rcare__h" id="rcare-h"><?php esc_html_e( 'Walk gently, and leave the wildflowers where they grow', 'oria' ); ?></h2>
		<ul class="rcare__list">
			<li><?php esc_html_e( 'Stay on the marked paths, and never pick or take anything.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Look at textures rather than touching plants you do not know.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Carry water, and cover up — there is not much shade in the middle of the day.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Check the weather, closures and current park notices before you set out.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'If pollen or insects affect you, keep your distance and take it at your own pace.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'Pick a route that suits how you move today, and keep children close.', 'oria' ); ?></li>
			<li><?php esc_html_e( 'This is a walk to enjoy, not treatment or mental health care. If you are struggling, please talk to your GP — and in an emergency call 000.', 'oria' ); ?></li>
		</ul>
	</section>

	<?php if ( $oria_r['related'] ) : ?>
		<section class="wrap rnext" aria-labelledby="rnext-h">
			<h2 class="rnext__h" id="rnext-h"><?php esc_html_e( 'Keep exploring, gently', 'oria' ); ?></h2>
			<?php if ( '' !== $oria_r['related_reason'] ) : ?>
				<p class="rnext__why"><?php echo esc_html( $oria_r['related_reason'] ); ?></p>
			<?php endif; ?>
			<div class="rnext__grid">
				<?php foreach ( $oria_r['related'] as $oria_rid ) : ?>
					<a class="rnext__card" href="<?php echo esc_url( (string) get_permalink( $oria_rid ) ); ?>" data-oria-event="micro_reset_listing_click">
						<span class="rnext__name"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_rid ) ) ); ?></span>
						<?php
						$oria_sub = '';
						foreach ( \Oria\Theme\oria_terms_of( (int) $oria_rid, 'area' ) as $oria_at ) {
							if ( $oria_at->parent ) {
								$oria_sub = \Oria\Theme\tname( $oria_at );
								break;
							}
						}
						?>
						<?php if ( '' !== $oria_sub ) : ?>
							<span class="rnext__sub"><?php echo esc_html( $oria_sub ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * Where this came from. A page that tells somebody what a park is like
	 * owes them the date it was checked and the official source, so they can
	 * judge how much to trust it before they drive there.
	 */
	?>
	<?php if ( '' !== $oria_r['conditions_url'] ) : ?>
		<section class="wrap rsource">
			<p>
				<?php esc_html_e( 'Conditions change. Check the official park information before you go:', 'oria' ); ?>
				<a href="<?php echo esc_url( $oria_r['conditions_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'current visitor information', 'oria' ); ?><span class="sr-only"> <?php esc_html_e( '(opens in a new tab)', 'oria' ); ?></span></a>.
			</p>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * The sticky bar, on phones, once the reset has begun. It is the only
	 * thing that follows the reader down the page, and it says where they
	 * are rather than nagging them onward.
	 */
	?>
	<?php if ( $oria_stages ) : ?>
		<div class="rbar" data-reset-bar hidden>
			<span class="rbar__where" data-reset-bar-text></span>
			<button type="button" class="rbar__go" data-reset-continue><?php esc_html_e( 'Continue', 'oria' ); ?></button>
		</div>
	<?php endif; ?>

</article>

<?php
get_footer();
