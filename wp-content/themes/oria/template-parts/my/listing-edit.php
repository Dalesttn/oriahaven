<?php
/**
 * The listing editor: one section at a time.
 *
 * The old screen put every field on one page behind a column of tabs, and
 * the Save button in a box on the right that scrolled away. Here there is
 * one section in front of you, a rail that says how far through you are,
 * and a Save bar that does not move.
 *
 * Saving is per-section on purpose. The owner pressed Save on the page
 * they can see; a handler that wrote the whole registry would quietly
 * blank every field this page never drew.
 */

declare(strict_types=1);

use Oria\Core\ListingEditor as Ed;
use Oria\Core\MyOria;

$oria_listing = Ed\listing_for( get_current_user_id() );
if ( ! $oria_listing ) {
	return;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading which section to draw.
$oria_slug = isset( $_GET['section'] ) ? sanitize_key( (string) wp_unslash( $_GET['section'] ) ) : '';
$oria_done = isset( $_GET['saved'] ) ? sanitize_key( (string) wp_unslash( $_GET['saved'] ) ) : '';
// phpcs:enable

if ( ! $oria_slug || ! Ed\section( $oria_slug ) ) {
	$oria_slug = Ed\first_section();
}
$oria_sec   = Ed\section( $oria_slug );
$oria_all   = Ed\sections();
$oria_score = Ed\score( $oria_listing );

$oria_keys = array_keys( $oria_all );
$oria_at   = (int) array_search( $oria_slug, $oria_keys, true );
$oria_next = $oria_keys[ $oria_at + 1 ] ?? '';
?>

<section class="my myedit">

	<nav class="myedit__top" aria-label="<?php esc_attr_e( 'Listing', 'oria' ); ?>">
		<a class="myedit__back" href="<?php echo esc_url( MyOria\url( 'listing' ) ); ?>">
			<span aria-hidden="true">&larr;</span> <?php esc_html_e( 'Back to my listing', 'oria' ); ?>
		</a>
		<p class="myedit__name">
			<?php echo esc_html( get_the_title( $oria_listing ) ); ?>
			<span class="myedit__pct"><?php echo esc_html( sprintf( __( '%d%% complete', 'oria' ), $oria_score['pct'] ) ); ?></span>
		</p>
		<a class="myedit__live" href="<?php echo esc_url( get_permalink( $oria_listing ) ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'View live profile', 'oria' ); ?> <span aria-hidden="true">&#8599;</span>
		</a>
	</nav>

	<?php if ( 'ok' === $oria_done ) : ?>
		<p class="myflash myflash--ok" role="status"><?php esc_html_e( 'Saved. Your profile is updated.', 'oria' ); ?></p>
	<?php elseif ( 'review' === $oria_done ) : ?>
		<p class="myflash myflash--wait" role="status"><?php esc_html_e( 'Saved. We will check that change before it goes live -- your listing stays exactly as it is in the meantime.', 'oria' ); ?></p>
	<?php elseif ( 'none' === $oria_done ) : ?>
		<p class="myflash" role="status"><?php esc_html_e( 'Nothing had changed, so there was nothing to save.', 'oria' ); ?></p>
	<?php endif; ?>

	<div class="myedit__body">

		<?php
		/*
		 * The rail. On a phone it becomes a horizontal strip above the form
		 * rather than a drawer: with nine short labels a strip is one swipe,
		 * and a drawer is two taps for the same thing.
		 */
		?>
		<nav class="myrail2" aria-label="<?php esc_attr_e( 'Sections', 'oria' ); ?>">
			<ol class="myrail2__list">
				<?php foreach ( $oria_all as $oria_k => $oria_s ) : ?>
					<?php
					$oria_state = Ed\section_state( $oria_listing, $oria_k );
					$oria_on    = $oria_k === $oria_slug;
					?>
					<li>
						<a class="myrail2__item is-<?php echo esc_attr( $oria_state ); ?><?php echo $oria_on ? ' is-on' : ''; ?>"
							href="<?php echo esc_url( add_query_arg( 'section', $oria_k, MyOria\url( 'listing-edit' ) ) ); ?>"
							<?php echo $oria_on ? ' aria-current="page"' : ''; ?>>
							<span class="myrail2__dot" aria-hidden="true"></span>
							<span class="myrail2__label"><?php echo esc_html( $oria_s['label'] ); ?></span>
							<span class="sr-only">
								<?php
								$oria_w = array(
									'done'   => __( '(complete)', 'oria' ),
									'part'   => __( '(partly done)', 'oria' ),
									'empty'  => __( '(not started)', 'oria' ),
									'locked' => __( '(on a paid plan)', 'oria' ),
								);
								echo esc_html( $oria_w[ $oria_state ] ?? '' );
								?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>

		<form class="myedit__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-myedit>
			<input type="hidden" name="action" value="oria_listing_save">
			<input type="hidden" name="section" value="<?php echo esc_attr( $oria_slug ); ?>">
			<?php wp_nonce_field( 'oria_listing_save_' . $oria_slug ); ?>

			<header class="myedit__head">
				<h1 class="myedit__h"><?php echo esc_html( $oria_sec['label'] ); ?></h1>
				<p class="myedit__blurb"><?php echo esc_html( $oria_sec['blurb'] ); ?></p>
			</header>

			<div class="myedit__fields">
				<?php foreach ( $oria_sec['fields'] as $oria_field ) : ?>
					<?php
					get_template_part(
						'template-parts/my/field',
						null,
						array(
							'field'   => $oria_field,
							'listing' => $oria_listing,
						)
					);
					?>
				<?php endforeach; ?>
			</div>

			<?php
			/*
			 * The save bar. Sticky to the bottom of the viewport so it is
			 * reachable from anywhere in a long section, and it carries the
			 * one thing somebody wants to know after pressing it -- whether
			 * anything is still unsaved.
			 */
			?>
			<div class="mysave">
				<p class="mysave__state" data-myedit-state aria-live="polite"
						data-unsaved="<?php esc_attr_e( 'Not saved yet', 'oria' ); ?>"
						data-saving="<?php esc_attr_e( 'Saving…', 'oria' ); ?>"></p>
				<div class="mysave__acts">
					<?php if ( $oria_next ) : ?>
						<button class="btn btn--ghost" type="submit" name="next" value="<?php echo esc_attr( $oria_next ); ?>">
							<?php esc_html_e( 'Save and continue', 'oria' ); ?>
						</button>
					<?php endif; ?>
					<button class="btn btn--dark" type="submit"><?php esc_html_e( 'Save', 'oria' ); ?></button>
				</div>
			</div>
		</form>
	</div>
</section>
