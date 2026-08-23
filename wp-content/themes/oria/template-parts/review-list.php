<?php
/**
 * Approved Oria Haven reviews on a listing.
 *
 * Every one is labelled as an Oria Haven review. The Google block that
 * follows keeps its own "on Google" heading, so the two sources sit next to
 * each other without ever being mistaken for one another — different
 * populations, collected differently, never averaged together.
 *
 * @var array $args {
 *     @type int $listing_id
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oria_listing_id = (int) ( $args['listing_id'] ?? get_the_ID() );

if ( ! function_exists( '\Oria\Core\Reviews\approved' ) ) {
	return;
}

$oria_list = \Oria\Core\Reviews\approved( $oria_listing_id );

if ( ! $oria_list ) {
	return;
}

$oria_may_reply = function_exists( '\Oria\Core\Replies\viewer_may_reply' )
	&& \Oria\Core\Replies\viewer_may_reply( $oria_listing_id );

$oria_star = '<svg class="rating__star" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1.6l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.4l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.6z"/></svg>';
?>

<div class="oriareviews">
	<h3 class="h3 oriareviews__title">
		<?php
		printf(
			/* translators: %d: number of reviews */
			esc_html( _n( '%d Oria Haven review', '%d Oria Haven reviews', count( $oria_list ), 'oria' ) ),
			count( $oria_list )
		);
		?>
	</h3>
	<p class="hint oriareviews__lede"><?php esc_html_e( 'From members who told us what they tried. Read before publishing; never paid for.', 'oria' ); ?></p>

	<?php
	foreach ( $oria_list as $oria_review ) :
		$oria_d       = \Oria\Core\Reviews\details( (int) $oria_review->comment_ID );
		$oria_service = $oria_d['service'] > 0 ? get_term( $oria_d['service'] ) : null;
		?>
		<article class="reviewitem reviewitem--oria">
			<div class="reviewitem__head">
				<div class="reviewitem__who">
					<strong><?php echo esc_html( $oria_review->comment_author ); ?></strong>
					<span class="badge badge--claimed"><?php esc_html_e( 'Oria Haven review', 'oria' ); ?></span>
				</div>
				<span class="rating">
					<?php echo $oria_star; // phpcs:ignore WordPress.Security.EscapeOutput -- literal markup above. ?>
					<?php echo esc_html( \Oria\Core\Reviews\rating_label( (float) $oria_d['rating'] ) ); ?>
				</span>
			</div>

			<div class="reviewitem__when">
				<?php
				$oria_bits = array();
				if ( $oria_service instanceof WP_Term ) {
					$oria_bits[] = \Oria\Theme\tname( $oria_service );
				}
				if ( '' !== $oria_d['visit_month'] ) {
					$oria_bits[] = date_i18n( 'F Y', (int) strtotime( $oria_d['visit_month'] . '-01' ) );
				}
				if ( '' !== $oria_d['experience'] ) {
					$oria_levels = \Oria\Core\Reviews\experience_levels();
					$oria_bits[] = (string) ( $oria_levels[ $oria_d['experience'] ] ?? '' );
				}
				echo esc_html( implode( ' · ', array_filter( $oria_bits ) ) );
				?>
			</div>

			<?php if ( '' !== trim( (string) $oria_review->comment_content ) ) : ?>
				<p class="reviewitem__body"><?php echo esc_html( $oria_review->comment_content ); ?></p>
			<?php endif; ?>

			<?php if ( $oria_d['best_for'] ) : ?>
				<div class="reviewitem__tags">
					<span class="micro"><?php esc_html_e( 'Good for', 'oria' ); ?></span>
					<?php
					foreach ( $oria_d['best_for'] as $oria_term_id ) :
						$oria_aud = get_term( $oria_term_id );
						if ( ! $oria_aud instanceof WP_Term ) {
							continue;
						}
						?>
						<span class="pill"><?php echo esc_html( \Oria\Theme\tname( $oria_aud ) ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( null !== $oria_d['recommend'] || null !== $oria_d['would_return'] ) : ?>
				<p class="reviewitem__verdict">
					<?php
					$oria_says = array();
					if ( null !== $oria_d['recommend'] ) {
						$oria_says[] = $oria_d['recommend']
							? __( 'Would recommend it', 'oria' )
							: __( 'Would not recommend it', 'oria' );
					}
					if ( null !== $oria_d['would_return'] ) {
						$oria_says[] = $oria_d['would_return']
							? __( 'would go again', 'oria' )
							: __( 'would not go again', 'oria' );
					}
					echo esc_html( implode( ' · ', $oria_says ) );
					?>
				</p>
			<?php endif; ?>
			<?php
			// The practice's answer, where it has given one and it has been
			// approved. Shown as part of the review, not beneath the fold.
			$oria_reply = function_exists( '\Oria\Core\Replies\published_for' )
				? \Oria\Core\Replies\published_for( (int) $oria_review->comment_ID )
				: null;
			?>
			<?php if ( null !== $oria_reply ) : ?>
				<div class="reviewreply">
					<div class="reviewreply__who">
						<strong><?php echo esc_html( $oria_reply->comment_author ); ?></strong>
						<span class="badge"><?php esc_html_e( 'Reply from the practice', 'oria' ); ?></span>
					</div>
					<p class="reviewreply__body"><?php echo esc_html( $oria_reply->comment_content ); ?></p>
				</div>
			<?php endif; ?>

			<div class="reviewitem__actions">
				<?php
				// The owner sees a reply box, once, while the listing is claimed.
				$oria_can_reply = $oria_may_reply
					&& null === $oria_reply
					&& function_exists( '\Oria\Core\Replies\can_reply' )
					&& true === \Oria\Core\Replies\can_reply( get_current_user_id(), (int) $oria_review->comment_ID );
				?>
				<?php if ( $oria_can_reply ) : ?>
					<details class="reviewaction">
						<summary><?php esc_html_e( 'Reply to this review', 'oria' ); ?></summary>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="reviewaction__form">
							<input type="hidden" name="action" value="oria_review_reply">
							<input type="hidden" name="review" value="<?php echo esc_attr( (string) $oria_review->comment_ID ); ?>">
							<?php wp_nonce_field( 'oria_reply_' . (int) $oria_review->comment_ID, 'oria_reply_nonce' ); ?>
							<label class="sr-only" for="oria-reply-<?php echo esc_attr( (string) $oria_review->comment_ID ); ?>"><?php esc_html_e( 'Your reply', 'oria' ); ?></label>
							<textarea class="input" id="oria-reply-<?php echo esc_attr( (string) $oria_review->comment_ID ); ?>" name="reply" rows="3" maxlength="1200" required
								placeholder="<?php esc_attr_e( 'Answer plainly. One reply per review, and we read it before it appears.', 'oria' ); ?>"></textarea>
							<button class="btn btn--sm btn--dark" type="submit"><?php esc_html_e( 'Send reply', 'oria' ); ?></button>
						</form>
					</details>
				<?php endif; ?>

				<?php if ( function_exists( '\Oria\Core\Reports\reasons' ) ) : ?>
					<details class="reviewaction reviewaction--report">
						<summary><?php esc_html_e( 'Report', 'oria' ); ?></summary>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="reviewaction__form">
							<input type="hidden" name="action" value="oria_report_review">
							<input type="hidden" name="review" value="<?php echo esc_attr( (string) $oria_review->comment_ID ); ?>">
							<?php wp_nonce_field( 'oria_report_' . (int) $oria_review->comment_ID, 'oria_report_nonce' ); ?>
							<p class="hint">
								<?php
								printf(
									/* translators: %s: link to the reviews policy */
									esc_html__( 'Reporting asks us to look again. It does not hide the review. %s', 'oria' ),
									'<a href="' . esc_url( home_url( '/reviews-policy/' ) ) . '">' . esc_html__( 'What we publish', 'oria' ) . '</a>'
								);
								?>
							</p>
							<?php foreach ( \Oria\Core\Reports\reasons() as $oria_key => $oria_label ) : ?>
								<label class="check">
									<input type="radio" name="reason" value="<?php echo esc_attr( (string) $oria_key ); ?>" required>
									<span><?php echo esc_html( $oria_label ); ?></span>
								</label>
							<?php endforeach; ?>
							<label class="sr-only" for="oria-detail-<?php echo esc_attr( (string) $oria_review->comment_ID ); ?>"><?php esc_html_e( 'Anything else we should know?', 'oria' ); ?></label>
							<textarea class="input" id="oria-detail-<?php echo esc_attr( (string) $oria_review->comment_ID ); ?>" name="detail" rows="2" maxlength="500"
								placeholder="<?php esc_attr_e( 'Anything else we should know? (optional)', 'oria' ); ?>"></textarea>
							<button class="btn btn--sm btn--ghost" type="submit"><?php esc_html_e( 'Send report', 'oria' ); ?></button>
						</form>
					</details>
				<?php endif; ?>
			</div>
		</article>
	<?php endforeach; ?>
</div>
