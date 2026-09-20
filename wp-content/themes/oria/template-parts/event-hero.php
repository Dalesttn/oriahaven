<?php
/**
 * The header band for the events pages.
 *
 * One photograph, one component, shared by the archive, the weekend page and
 * the landing pages, so there is one place to change how these headers look.
 *
 * Deliberately short — a 16:6 band rather than a screen-filling hero. The
 * brief is explicit that a decorative header must not push the first event
 * down the page, and an events page that opens with no events is a poster,
 * not a listing.
 *
 * The photograph is decorative: the heading beside it says what the page
 * is, so the alt stays empty. Text never relies on the picture for its
 * contrast — a scrim sits between them, and the copy is legible with the
 * image missing entirely.
 *
 * @var array $args {
 *     @type string $img     Base name in assets/img/event/ (e.g. 'hero'), or
 *                           a full URL for a per-page image.
 *     @type string $eyebrow Small line above the title.
 *     @type string $title   The h1.
 *     @type string $lede    One sentence under it.
 *     @type array  $crumbs  [label => url|''] — the last one is plain text.
 *     @type string $actions Rendered buttons, already escaped.
 *     @type string $shade   'dim' for a photograph that is already dark and
 *                           would be flattened by the standard scrim.
 * }
 */

declare(strict_types=1);

$oria_img  = (string) ( $args['img'] ?? 'hero' );
$oria_dir  = get_stylesheet_directory() . '/assets/img/event/';
$oria_uri  = get_stylesheet_directory_uri() . '/assets/img/event/';

$oria_src    = '';
$oria_srcset = '';
if ( 0 === strpos( $oria_img, 'http' ) ) {
	$oria_src = $oria_img;
} elseif ( is_readable( $oria_dir . $oria_img . '-1600.webp' ) ) {
	$oria_src    = $oria_uri . $oria_img . '-1600.webp';
	$oria_srcset = is_readable( $oria_dir . $oria_img . '-960.webp' )
		? $oria_uri . $oria_img . '-960.webp 960w, ' . $oria_src . ' 1600w'
		: '';
}
?>
<section class="evhead<?php echo '' === $oria_src ? ' evhead--bare' : ''; ?><?php echo 'dim' === (string) ( $args['shade'] ?? '' ) ? ' evhead--dim' : ''; ?>">
	<?php if ( '' !== $oria_src ) : ?>
		<img class="evhead__img" src="<?php echo esc_url( $oria_src ); ?>"
			<?php echo '' !== $oria_srcset ? 'srcset="' . esc_attr( $oria_srcset ) . '" sizes="100vw"' : ''; ?>
			width="1600" height="600" alt="" fetchpriority="high" decoding="async">
		<span class="evhead__shade" aria-hidden="true"></span>
	<?php endif; ?>
	<div class="wrap evhead__inner">
		<?php if ( ! empty( $args['crumbs'] ) ) : ?>
			<nav class="crumbs evhead__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
				<?php
				$oria_last = array_key_last( (array) $args['crumbs'] );
				foreach ( (array) $args['crumbs'] as $oria_label => $oria_href ) :
					?>
					<?php if ( '' !== $oria_href && $oria_label !== $oria_last ) : ?>
						<a href="<?php echo esc_url( (string) $oria_href ); ?>"><?php echo esc_html( (string) $oria_label ); ?></a>
					<?php else : ?>
						<span><?php echo esc_html( (string) $oria_label ); ?></span>
					<?php endif; ?>
					<?php if ( $oria_label !== $oria_last ) : ?>
						<span aria-hidden="true">/</span>
					<?php endif; ?>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( ! empty( $args['eyebrow'] ) ) : ?>
			<span class="micro evhead__eyebrow"><?php echo esc_html( (string) $args['eyebrow'] ); ?></span>
		<?php endif; ?>

		<h1 class="h1 evhead__title"><?php echo esc_html( (string) ( $args['title'] ?? '' ) ); ?></h1>

		<?php if ( ! empty( $args['lede'] ) ) : ?>
			<p class="lede evhead__lede"><?php echo esc_html( (string) $args['lede'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $args['actions'] ) ) : ?>
			<div class="evhead__acts"><?php echo wp_kses_post( (string) $args['actions'] ); ?></div>
		<?php endif; ?>
	</div>
</section>
