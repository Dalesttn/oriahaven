<?php
/**
 * The share kit for one listing (/listing/{slug}/share/).
 *
 * Public by design — see the note in Oria\Core\Share. The page's whole
 * job is to remove every excuse not to post: the card is already made,
 * the words are already written, and the buttons are one tap.
 */

declare(strict_types=1);

use Oria\Core\Share;

get_header();

$oria_id    = get_queried_object_id();
$oria_name  = \Oria\Theme\ptitle( $oria_id );
$oria_card  = Share\card_url( $oria_id, 'card' );
$oria_sq    = Share\card_url( $oria_id, 'square' );
$oria_story = Share\card_url( $oria_id, 'story' );
$oria_post  = Share\suggested_post( $oria_id );
$oria_links = Share\share_links( $oria_id );
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
		<h1 class="h1 pagehead__title"><?php esc_html_e( "You're listed. Here's how to tell people.", 'oria' ); ?></h1>
		<p class="lede pagehead__lede">
			<?php
			printf(
				esc_html__( 'Everything below is ready to use for %s — the image is made, the words are written. Nothing here costs anything, and we never take a cut of a booking.', 'oria' ),
				esc_html( $oria_name )
			);
			?>
		</p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<div class="sharekit">

		<div class="sharekit__main">
			<h2 class="h3"><?php esc_html_e( '1. Post it', 'oria' ); ?></h2>
			<p class="hint" style="margin-bottom:1rem"><?php esc_html_e( 'One tap. The link carries a tag so you can see the visits it brings you.', 'oria' ); ?></p>

			<div class="chips" style="margin-bottom:2rem">
				<?php foreach ( $oria_links as $oria_label => $oria_href ) : ?>
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( $oria_href ); ?>" target="_blank" rel="noopener"
						data-oria-share="<?php echo esc_attr( strtolower( $oria_label ) ); ?>"><?php echo esc_html( $oria_label ); ?></a>
				<?php endforeach; ?>
			</div>

			<h2 class="h3"><?php esc_html_e( '2. Or copy the words', 'oria' ); ?></h2>
			<p class="hint" style="margin-bottom:1rem"><?php esc_html_e( 'Written for you. Change anything you like — it should sound like you, not like us.', 'oria' ); ?></p>

			<div class="sharekit__copy">
				<textarea id="shareCopy" class="textarea" rows="5" readonly><?php echo esc_textarea( $oria_post ); ?></textarea>
				<button class="btn btn--dark btn--block" type="button" data-copy-target="#shareCopy" style="margin-top:.75rem">
					<?php esc_html_e( 'Copy this post', 'oria' ); ?>
				</button>
			</div>

			<h2 class="h3" style="margin-top:2.5rem"><?php esc_html_e( '3. Images for Instagram', 'oria' ); ?></h2>
			<p class="hint" style="margin-bottom:1rem"><?php esc_html_e( 'Right-click or long-press to save, then post with the words above.', 'oria' ); ?></p>

			<div class="sharekit__images">
				<?php if ( $oria_sq ) : ?>
					<a class="sharekit__thumb" href="<?php echo esc_url( $oria_sq ); ?>" download>
						<img src="<?php echo esc_url( $oria_sq ); ?>" alt="<?php esc_attr_e( 'Square image for a post', 'oria' ); ?>" loading="lazy" width="1080" height="1080">
						<span><?php esc_html_e( 'Square — for a post', 'oria' ); ?></span>
					</a>
				<?php endif; ?>
				<?php if ( $oria_story ) : ?>
					<a class="sharekit__thumb sharekit__thumb--tall" href="<?php echo esc_url( $oria_story ); ?>" download>
						<img src="<?php echo esc_url( $oria_story ); ?>" alt="<?php esc_attr_e( 'Tall image for a story', 'oria' ); ?>" loading="lazy" width="1080" height="1920">
						<span><?php esc_html_e( 'Tall — for a story', 'oria' ); ?></span>
					</a>
				<?php endif; ?>
			</div>

			<?php
			/*
			 * The only part of this page that earns a link rather than a
			 * visit. Everything above is a share, and a search engine counts
			 * none of those. A badge on the practice's own site is the one
			 * thing here that says these two pages belong together.
			 *
			 * Never phrased as a condition of anything — see the note in
			 * Oria\Core\Badge. It is a thank-you, not a trade.
			 */
			$oria_badges = array();
			foreach ( \Oria\Core\Badge\variants() as $oria_v => $oria_vlabel ) {
				$oria_img = \Oria\Core\Badge\image_url( $oria_v );
				if ( '' !== $oria_img ) {
					$oria_badges[ $oria_v ] = array( 'url' => $oria_img, 'label' => $oria_vlabel );
				}
			}
			?>
			<?php if ( $oria_badges ) : ?>
				<h2 class="h3" style="margin-top:2.5rem"><?php esc_html_e( '4. Add it to your website', 'oria' ); ?></h2>
				<p class="hint" style="margin-bottom:1.25rem">
					<?php esc_html_e( 'Paste this into your footer or your about page. It links back to your profile, so anyone who finds you there can see your full listing. Optional, always — nothing about your listing depends on it.', 'oria' ); ?>
				</p>

				<?php foreach ( $oria_badges as $oria_v => $oria_b ) : ?>
					<div class="badgekit">
						<div class="badgekit__preview badgekit__preview--<?php echo esc_attr( $oria_v ); ?>">
							<img src="<?php echo esc_url( $oria_b['url'] ); ?>"
								alt="<?php echo esc_attr( sprintf( /* translators: %s: which background the badge suits */ __( 'The Oria Haven badge, %s', 'oria' ), $oria_b['label'] ) ); ?>"
								width="<?php echo (int) \Oria\Core\Badge\W; ?>" height="<?php echo (int) \Oria\Core\Badge\H; ?>">
						</div>
						<span class="micro" style="display:block;margin:.8rem 0 .5rem"><?php echo esc_html( $oria_b['label'] ); ?></span>
						<textarea id="badgeCode-<?php echo esc_attr( $oria_v ); ?>" class="textarea" rows="4" readonly><?php echo esc_textarea( \Oria\Core\Badge\snippet( $oria_id, $oria_v ) ); ?></textarea>
						<button class="btn btn--ghost btn--block btn--sm" type="button"
							data-copy-target="#badgeCode-<?php echo esc_attr( $oria_v ); ?>" style="margin-top:.6rem">
							<?php esc_html_e( 'Copy the code', 'oria' ); ?>
						</button>
					</div>
				<?php endforeach; ?>

				<p class="hint" style="margin-top:1.25rem">
					<?php esc_html_e( 'On Wix, Squarespace or Shopify, look for an embed or custom HTML block and paste it there.', 'oria' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<aside class="sharekit__side">
			<div class="card"><div class="card__body">
				<span class="micro"><?php esc_html_e( 'How your link looks', 'oria' ); ?></span>
				<?php if ( $oria_card ) : ?>
					<img class="sharekit__preview" src="<?php echo esc_url( $oria_card ); ?>" alt="<?php esc_attr_e( 'Link preview image', 'oria' ); ?>" loading="lazy" width="1200" height="630">
				<?php endif; ?>
				<p class="hint" style="margin-top:.85rem">
					<?php esc_html_e( 'This is what appears when you paste your profile link into Facebook, LinkedIn or a message.', 'oria' ); ?>
				</p>
				<a class="btn btn--ghost btn--block btn--sm" href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>" style="margin-top:1rem">
					<?php esc_html_e( 'View your profile', 'oria' ); ?>
				</a>
			</div></div>

			<?php if ( 'unclaimed' === \Oria\Theme\claim_status( $oria_id ) && ! (int) get_post_meta( $oria_id, 'claimed_by', true ) ) : ?>
				<div class="card" style="margin-top:1.25rem"><div class="card__body">
					<b style="display:block;margin-bottom:.4rem"><?php esc_html_e( 'Is this your practice?', 'oria' ); ?></b>
					<p style="font-size:.875rem;color:var(--text-soft)">
						<?php esc_html_e( 'This listing was built from public information. Claim it and you can edit every detail yourself.', 'oria' ); ?>
					</p>
					<a class="btn btn--dark btn--block btn--sm" href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>#claim" style="margin-top:.75rem">
						<?php esc_html_e( 'Claim this listing', 'oria' ); ?>
					</a>
				</div></div>
			<?php endif; ?>
		</aside>

	</div>
</section>

<?php
get_footer();
