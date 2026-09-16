<?php
/**
 * The index of app collections.
 *
 * Deliberately plain. While there is one collection this page carries
 * noindex and canonicals to it (see Pages\thin_guide_archive) — the
 * collection is the page that should rank, and an index of one is a
 * duplicate of it. It earns its own place at two.
 */

declare(strict_types=1);

use Oria\Apps\Data;
use Oria\Apps\Guides;

get_header();

$oria_guides = Guides\all();
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( (string) get_post_type_archive_link( Data\CPT ) ); ?>"><?php esc_html_e( 'Apps', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Guides', 'oria' ); ?></span>
	</nav>

	<h1 class="h1"><?php esc_html_e( 'Wellness app guides', 'oria' ); ?></h1>
	<p class="lede"><?php esc_html_e( 'Shortlists of apps worth your time — for sleep, meditation, movement and mindfulness. Chosen by an editor, checked against the apps themselves.', 'oria' ); ?></p>
</section>

<section class="wrap section section--top-flush">
	<?php if ( ! $oria_guides ) : ?>
		<p class="muted"><?php esc_html_e( 'The first guides are being written. In the meantime, every app we have reviewed is in the apps hub.', 'oria' ); ?></p>
		<p><a class="btn btn--dark" href="<?php echo esc_url( (string) get_post_type_archive_link( Data\CPT ) ); ?>"><?php esc_html_e( 'Browse all apps', 'oria' ); ?></a></p>
	<?php else : ?>
		<div class="gdlist">
			<?php foreach ( $oria_guides as $oria_gid ) : ?>
				<?php $oria_g = Guides\guide( (int) $oria_gid ); ?>
				<article class="gdcard">
					<a class="gdcard__link" href="<?php echo esc_url( (string) $oria_g['url'] ); ?>">
						<h2 class="h3 gdcard__title"><?php echo esc_html( (string) $oria_g['title'] ); ?></h2>
					</a>
					<?php if ( '' !== (string) $oria_g['subtitle'] ) : ?>
						<p class="gdcard__sub"><?php echo esc_html( (string) $oria_g['subtitle'] ); ?></p>
					<?php endif; ?>
					<p class="micro gdcard__meta">
						<?php
						printf(
							/* translators: %d: how many apps are in the guide */
							esc_html( _n( '%d app', '%d apps', count( $oria_g['picks'] ), 'oria' ) ),
							(int) count( $oria_g['picks'] )
						);
						if ( '' !== (string) $oria_g['checked'] ) {
							echo ' · ';
							printf(
								/* translators: %s: a date */
								esc_html__( 'checked %s', 'oria' ),
								esc_html( date_i18n( 'M Y', (int) strtotime( (string) $oria_g['checked'] ) ) )
							);
						}
						?>
					</p>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php
get_footer();
