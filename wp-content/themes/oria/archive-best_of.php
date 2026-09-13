<?php
/**
 * Best Of hub -- /best/.
 *
 * Every published guide, the featured ones large, the rest grouped by what a
 * reader is looking for. Built to the journal's editorial language rather
 * than the directory grid: a hub of shortlists is read, not filtered.
 */

declare(strict_types=1);

use Oria\Core\BestOf;

get_header();

$oria_all  = BestOf\guides();
$oria_feat = array_values( array_filter( $oria_all, static fn( $g ) => BestOf\is_featured( $g->ID ) ) );
if ( ! $oria_feat ) {
	// Nothing flagged yet: the first three stand in, so the hub never opens flat.
	$oria_feat = array_slice( $oria_all, 0, 3 );
}
$oria_feat_ids = array_map( static fn( $g ) => $g->ID, $oria_feat );

// The rest, grouped by category in the order CATEGORIES declares.
$oria_groups = array();
foreach ( $oria_all as $oria_g ) {
	if ( in_array( $oria_g->ID, $oria_feat_ids, true ) ) {
		continue;
	}
	$oria_groups[ BestOf\category( $oria_g->ID ) ][] = $oria_g;
}
$oria_order = array_merge( array_keys( BestOf\CATEGORIES ), array( '' ) );
uksort( $oria_groups, static fn( $a, $b ) => array_search( $a, $oria_order, true ) <=> array_search( $b, $oria_order, true ) );
?>

<section class="wrap bohero">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Best Of', 'oria' ); ?></span>
	</nav>
	<span class="bohero__eyebrow"><?php esc_html_e( 'Best of Perth', 'oria' ); ?></span>
	<h1 class="bohero__title"><?php echo esc_html( BestOf\hub_heading() ); ?></h1>
	<p class="bohero__lede"><?php echo esc_html( BestOf\hub_lede() ); ?></p>
	<?php if ( $oria_all ) : ?>
		<p class="bohero__meta">
			<?php
			printf(
				/* translators: %s: number of guides */
				esc_html( _n( '%s guide', '%s guides', count( $oria_all ), 'oria' ) ),
				esc_html( number_format_i18n( count( $oria_all ) ) )
			);
			echo ' &middot; ' . esc_html__( 'Chosen by our editors, never paid for', 'oria' );
			?>
		</p>
	<?php endif; ?>
</section>

<?php if ( $oria_feat ) : ?>
	<section class="wrap bosection">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'Start here', 'oria' ); ?></span>
			</div>
		</div>
		<?php if ( 1 === count( $oria_feat ) ) : ?>
			<?php get_template_part( 'template-parts/best-guide-card', null, array( 'post' => $oria_feat[0], 'feature' => true ) ); ?>
		<?php else : ?>
			<div class="bogrid bogrid--3">
				<?php foreach ( $oria_feat as $oria_g ) : ?>
					<?php get_template_part( 'template-parts/best-guide-card', null, array( 'post' => $oria_g ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $oria_groups ) : ?>
	<section class="wrap bosection">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'Browse', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( "By what you're looking for", 'oria' ); ?></h2>
			</div>
		</div>
		<?php foreach ( $oria_groups as $oria_cat => $oria_list ) : ?>
			<div class="bogroup">
				<h3 class="bogroup__title"><?php echo esc_html( BestOf\category_label( (string) $oria_cat ) ?: __( 'More guides', 'oria' ) ); ?></h3>
				<div class="bogrid bogrid--3">
					<?php foreach ( $oria_list as $oria_g ) : ?>
						<?php get_template_part( 'template-parts/best-guide-card', null, array( 'post' => $oria_g ) ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</section>
<?php endif; ?>

<?php if ( ! $oria_all ) : ?>
	<section class="wrap bosection">
		<p class="muted"><?php esc_html_e( 'The first guides are being written. In the meantime, the directory has every practice they will draw from.', 'oria' ); ?></p>
		<p><a class="btn btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Explore the directory', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></p>
	</section>
<?php endif; ?>

<section class="wrap bosection bosection--last">
	<?php
	/*
	 * Three facts, not three steps. What a reader needs to know is what a
	 * guide is NOT: not an advert, not a list of every studio, not a page
	 * that rots.
	 */
	?>
	<div class="bonote reveal">
		<span class="micro"><?php esc_html_e( 'How a Best Of guide works', 'oria' ); ?></span>
		<dl class="bonote__list">
			<div>
				<dt><?php esc_html_e( 'Chosen, not bought', 'oria' ); ?></dt>
				<dd><?php esc_html_e( 'Our editors shortlist each place for one specific need. A practice cannot pay to be in a guide. Paid placements on Oria Haven are always labelled Featured, which is a different thing.', 'oria' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'A shortlist, on purpose', 'oria' ); ?></dt>
				<dd><?php esc_html_e( 'The directory has every practice we know of. A guide has a handful, each with the reason it made the list, so you can pick one and go.', 'oria' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'It keeps itself current', 'oria' ); ?></dt>
				<dd><?php esc_html_e( 'Every pick reads its listing when the page loads. When a studio moves, changes its prices or closes, the guide follows.', 'oria' ); ?></dd>
			</div>
		</dl>
	</div>
</section>

<?php
get_footer();
