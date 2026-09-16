<?php
/**
 * One category of app: /apps/category/meditation/.
 *
 * The same cards as the hub, headed by whatever an editor has written
 * about choosing in this category. Pages\CATEGORY_MIN decides whether the
 * page is indexable; this template renders it either way, because a
 * visitor who arrives from the hub should never meet a blank.
 */

declare(strict_types=1);

use Oria\Apps\Data;
use Oria\Apps\Engine;
use Oria\Apps\Render;

get_header();

$oria_term = get_queried_object();
$oria_rows = $oria_term instanceof WP_Term ? Engine\apps( array( $oria_term->slug ) ) : array();
$oria_name = $oria_term instanceof WP_Term ? wp_specialchars_decode( $oria_term->name ) : '';
$oria_intro = $oria_term instanceof WP_Term ? trim( (string) get_term_meta( $oria_term->term_id, 'intro', true ) ) : '';
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( (string) get_post_type_archive_link( Data\CPT ) ); ?>"><?php esc_html_e( 'Apps', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_name ); ?></span>
	</nav>
	<h1 class="h1 pagehead__title" style="margin-top:1rem">
		<?php
		printf(
			/* translators: %s: category name, e.g. Meditation */
			esc_html__( '%s apps', 'oria' ),
			esc_html( $oria_name )
		);
		?>
	</h1>
	<p class="lede" style="max-width:56ch;margin-top:1rem">
		<?php
		echo '' !== $oria_intro
			? esc_html( $oria_intro )
			: esc_html(
				sprintf(
					/* translators: %s: category name, lowercased */
					__( 'The %s apps we have reviewed, with what each one costs, who it suits and where to do the same thing in person.', 'oria' ),
					strtolower( $oria_name )
				)
			);
		?>
	</p>
</section>

<?php if ( ! $oria_rows ) : ?>
	<section class="wrap section section--top-flush">
		<div class="dir__empty">
			<h2 class="h3"><?php esc_html_e( 'Nothing here yet', 'oria' ); ?></h2>
			<p class="muted" style="margin-top:.5rem"><?php esc_html_e( 'We add an app once we have checked it properly.', 'oria' ); ?></p>
			<a class="btn btn--ghost btn--sm" style="margin-top:1rem" href="<?php echo esc_url( (string) get_post_type_archive_link( Data\CPT ) ); ?>"><?php esc_html_e( 'All wellness apps', 'oria' ); ?></a>
		</div>
	</section>
	<?php
	get_footer();
	return;
endif;
?>

<section class="wrap section section--top-flush">
	<div class="appgrid">
		<?php
		foreach ( $oria_rows as $oria_row ) {
			echo Render\card( $oria_row ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
	</div>
	<?php if ( Render\affiliate_in( $oria_rows ) ) : ?>
		<p class="appband__disclosure"><?php echo esc_html( Data\disclosure() ); ?></p>
	<?php endif; ?>
</section>

<?php
/*
 * The way back into the directory, from the category rather than from one
 * app: somebody browsing meditation apps is a good candidate for a
 * meditation class.
 */
$oria_practices = $oria_term instanceof WP_Term ? Engine\practices_for( array( $oria_term->slug ) ) : array();
?>
<?php if ( $oria_practices ) : ?>
	<section class="wrap section section--top-flush">
		<div class="appoffline reveal">
			<div class="appoffline__copy">
				<span class="micro"><?php esc_html_e( 'Take it offline', 'oria' ); ?></span>
				<h2 class="h3"><?php esc_html_e( 'The same thing, in a room', 'oria' ); ?></h2>
				<p class="muted"><?php esc_html_e( 'Perth practices offering this in person, from the directory.', 'oria' ); ?></p>
			</div>
			<div class="appoffline__links">
				<?php foreach ( $oria_practices as $oria_practice ) : ?>
					<a class="fchip" href="<?php echo esc_url( $oria_practice['url'] ); ?>"><?php echo esc_html( $oria_practice['name'] ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="wrap section section--top-flush">
	<p style="text-align:center">
		<a class="btn btn--ghost" href="<?php echo esc_url( (string) get_post_type_archive_link( Data\CPT ) ); ?>"><?php esc_html_e( 'All wellness apps', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</p>
</section>

<?php
get_footer();
