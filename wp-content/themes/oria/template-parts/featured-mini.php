<?php
/**
 * Featured listings as small blocks, for the column beside the guides.
 *
 * The full card band is a wide, image-led row that wants a section of its
 * own. Under the answer block it would dominate a space meant to be read
 * quickly, and it left a large hole on the left of the page while the
 * guides column ran on down the right.
 *
 * So: the same posts, a third of the size, filling the space the answer
 * text does not use.
 *
 * This is paid placement, which is why the Featured badge stays on it. A
 * promoted result that does not say it is promoted is the one thing a
 * directory cannot do quietly.
 *
 * @var array $args {
 *     @type WP_Post[] $posts   Featured listings.
 *     @type string    $heading Label above the blocks.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oria_mini = isset( $args['posts'] ) && is_array( $args['posts'] ) ? $args['posts'] : array();

if ( ! $oria_mini ) {
	return;
}

$oria_mini_heading = (string) ( $args['heading'] ?? __( 'Featured practices', 'oria' ) );
?>

<div class="featmini">
	<h2 class="featmini__label">
		<span class="badge-dot" aria-hidden="true"></span>
		<span class="micro"><?php echo esc_html( $oria_mini_heading ); ?></span>
	</h2>

	<ul class="featmini__grid">
		<?php
		foreach ( $oria_mini as $oria_p ) :
			$oria_pid = (int) $oria_p->ID;

			// Suburb, not region: on a category page the region is usually the
			// same for several of them and tells the reader nothing.
			$oria_where = '';
			foreach ( wp_get_post_terms( $oria_pid, 'area' ) as $oria_t ) {
				if ( \Oria\Core\Taxonomies\is_suburb( $oria_t ) ) {
					$oria_where = \Oria\Theme\tname( $oria_t );
					break;
				}
			}

			$oria_from = (float) get_field( 'price_from', $oria_pid );
			?>
			<li class="featmini__item">
				<a class="featmini__link" href="<?php echo esc_url( (string) get_permalink( $oria_p ) ); ?>">
					<span class="featmini__media" aria-hidden="true">
						<img src="<?php echo esc_url( \Oria\Theme\listing_image( $oria_pid ) ); ?>" alt="" loading="lazy" decoding="async"
							onerror="this.onerror=null;this.src='<?php echo esc_js( \Oria\Theme\listing_scene( $oria_pid ) ); ?>'">
					</span>
					<span class="featmini__body">
						<span class="featmini__name"><?php echo esc_html( \Oria\Theme\ptitle( $oria_p ) ); ?></span>
						<span class="featmini__meta">
							<?php
							$oria_bits = array_filter(
								array(
									$oria_where,
									$oria_from > 0 ? sprintf( __( 'from $%s', 'oria' ), number_format_i18n( round( $oria_from ) ) ) : '',
								)
							);
							echo esc_html( implode( ' · ', $oria_bits ) );
							?>
						</span>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
