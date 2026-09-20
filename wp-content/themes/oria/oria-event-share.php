<?php
/**
 * The share kit for one event (/events/{slug}/share/).
 *
 * Same shape as the listing kit and the same argument: the buttons earn
 * visits, the badge at the end earns the link. An organiser publicising
 * an event is the most willing linker this directory will ever meet, and
 * this page exists so that pasting the link takes one tap rather than an
 * afternoon.
 */

declare(strict_types=1);

use Oria\Core\EventShare;
use Oria\Core\Badge;

get_header();

$oria_id    = (int) get_queried_object_id();
$oria_name  = \Oria\Theme\ptitle( $oria_id );
$oria_card  = EventShare\card_url( $oria_id );
$oria_post  = EventShare\suggested_post( $oria_id );
$oria_links = EventShare\share_links( $oria_id );
$oria_live  = EventShare\shareable( $oria_id );
$oria_facts = EventShare\card_text( $oria_id );
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>"><?php echo esc_html( $oria_name ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Share', 'oria' ); ?></span>
	</nav>
	<div style="margin-top:1rem;max-width:44rem">
		<span class="micro"><?php esc_html_e( 'Share kit', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title"><?php esc_html_e( 'Tell people it is on', 'oria' ); ?></h1>
		<p class="lede pagehead__lede">
			<?php
			printf(
				/* translators: %s: event name */
				esc_html__( 'Everything here is ready to use for %s — the image is made, the words are written, and there is a badge for your own website at the end. Free, and nothing about the listing depends on using it.', 'oria' ),
				esc_html( $oria_name )
			);
			?>
		</p>
		<p style="margin-top:.6rem;color:var(--text-soft)"><?php echo esc_html( $oria_facts['meta'] ); ?></p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<?php if ( ! $oria_live ) : ?>
		<div class="evstatus evstatus--over" style="margin-bottom:1.5rem" role="status">
			<b><?php esc_html_e( 'This event is finished or cancelled.', 'oria' ); ?></b>
			<span><?php esc_html_e( 'The kit still works, but there is probably nothing left to promote.', 'oria' ); ?></span>
		</div>
	<?php endif; ?>

	<div class="sharekit">
		<div class="sharekit__main">
			<h2 class="h3"><?php esc_html_e( '1. Post it', 'oria' ); ?></h2>
			<p class="hint" style="margin-bottom:1rem"><?php esc_html_e( 'One tap. The link carries a tag, so you can see the visits it brings you.', 'oria' ); ?></p>

			<div class="chips" style="margin-bottom:2rem">
				<?php foreach ( $oria_links as $oria_label => $oria_href ) : ?>
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( $oria_href ); ?>" target="_blank" rel="noopener"
						data-oria-share="<?php echo esc_attr( strtolower( $oria_label ) ); ?>"><?php echo esc_html( $oria_label ); ?></a>
				<?php endforeach; ?>
			</div>

			<h2 class="h3"><?php esc_html_e( '2. Or copy the words', 'oria' ); ?></h2>
			<p class="hint" style="margin-bottom:1rem"><?php esc_html_e( 'Written to be posted as it is. Change anything you like.', 'oria' ); ?></p>
			<textarea id="shareCopy" class="textarea" rows="5" readonly><?php echo esc_textarea( $oria_post ); ?></textarea>
			<button class="btn btn--sm" type="button" data-copy-target="#shareCopy" style="margin-top:.6rem"><?php esc_html_e( 'Copy', 'oria' ); ?></button>

			<?php
			/*
			 * The badge. The only thing on this page a search engine counts,
			 * and the reason the page is worth building: a link from the
			 * organiser's own site, with the event's own name as the anchor.
			 */
			$oria_badges = array();
			if ( function_exists( '\Oria\Core\Badge\variants' ) ) {
				foreach ( Badge\variants() as $oria_v => $oria_vlabel ) {
					$oria_img = Badge\image_url( $oria_v );
					if ( '' !== $oria_img ) {
						$oria_badges[ $oria_v ] = array( 'url' => $oria_img, 'label' => $oria_vlabel );
					}
				}
			}
			?>
			<?php if ( $oria_badges ) : ?>
				<h2 class="h3" id="website" style="margin-top:2.5rem"><?php esc_html_e( '3. And one for your own website', 'oria' ); ?></h2>
				<p class="hint" style="margin-bottom:1rem">
					<?php esc_html_e( 'Paste this where you already mention the event. Anyone reading your site can then see the full details, and it is a normal link — nothing hidden, nothing required.', 'oria' ); ?>
				</p>
				<?php foreach ( $oria_badges as $oria_v => $oria_b ) : ?>
					<div class="badgekit">
						<div class="badgekit__preview badgekit__preview--<?php echo esc_attr( $oria_v ); ?>">
							<img src="<?php echo esc_url( $oria_b['url'] ); ?>"
								alt="<?php echo esc_attr( sprintf( /* translators: %s: which background the badge suits */ __( 'The Oria Haven badge, %s', 'oria' ), $oria_b['label'] ) ); ?>"
								width="210" height="62" loading="lazy">
						</div>
						<textarea id="badgeCode-<?php echo esc_attr( $oria_v ); ?>" class="textarea" rows="4" readonly><?php echo esc_textarea( Badge\snippet( $oria_id, $oria_v ) ); ?></textarea>
						<button class="btn btn--sm" type="button" data-copy-target="#badgeCode-<?php echo esc_attr( $oria_v ); ?>" style="margin-top:.6rem"><?php esc_html_e( 'Copy the code', 'oria' ); ?></button>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<aside class="sharekit__side">
			<?php if ( '' !== $oria_card ) : ?>
				<h2 class="h3"><?php esc_html_e( 'The image', 'oria' ); ?></h2>
				<p class="hint" style="margin-bottom:1rem"><?php esc_html_e( 'Made for this event. Right-click to save, or it appears on its own when the link is pasted.', 'oria' ); ?></p>
				<img src="<?php echo esc_url( $oria_card ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: event name */ __( 'Share card for %s', 'oria' ), $oria_name ) ); ?>"
					style="width:100%;height:auto;border-radius:var(--r-md);border:1px solid var(--line)" loading="lazy">
			<?php endif; ?>

			<p style="margin-top:1.2rem">
				<a class="btn btn--sm" href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>"><?php esc_html_e( 'Back to the event', 'oria' ); ?></a>
			</p>
		</aside>
	</div>
</section>

<?php
get_footer();
