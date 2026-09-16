<?php
/**
 * /apps/ — the Apps & Digital Wellness hub.
 *
 * Arranged by what somebody wants rather than by what we have: goals
 * first, then categories, then the handful we would hand you ourselves.
 * Every section is built from the catalogue, so a section with nothing in
 * it does not render at all rather than sitting there empty.
 */

declare(strict_types=1);

use Oria\Apps\Data;
use Oria\Apps\Engine;
use Oria\Apps\Render;

get_header();

$oria_apps = Engine\apps();
$oria_hub  = (string) get_post_type_archive_link( Data\CPT );

// Goals, counted from the catalogue: a goal with nothing behind it is a
// promise the page cannot keep.
$oria_goals = array();
foreach ( $oria_apps as $oria_row ) {
	foreach ( (array) $oria_row['best_for'] as $oria_key ) {
		$oria_goals[ $oria_key ] = ( $oria_goals[ $oria_key ] ?? 0 ) + 1;
	}
}
arsort( $oria_goals );

// Categories, same rule.
$oria_cats = array();
foreach ( $oria_apps as $oria_row ) {
	foreach ( (array) $oria_row['cats'] as $oria_term ) {
		$oria_cats[ $oria_term->slug ] = $oria_cats[ $oria_term->slug ] ?? array( 'term' => $oria_term, 'n' => 0 );
		++$oria_cats[ $oria_term->slug ]['n'];
	}
}
uasort( $oria_cats, static fn( array $a, array $b ): int => $b['n'] <=> $a['n'] );

$oria_picks = Engine\picks( 4 );
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Apps', 'oria' ); ?></span>
	</nav>
	<span class="micro" style="display:block;margin-top:1rem;color:var(--moss)"><?php esc_html_e( 'Wellness at home', 'oria' ); ?></span>
	<h1 class="h1 pagehead__title" style="margin-top:.4rem"><?php esc_html_e( 'Wellness apps & digital tools', 'oria' ); ?></h1>
	<p class="lede" style="max-width:56ch;margin-top:1rem">
		<?php esc_html_e( 'Apps for meditation, sleep, movement and everyday wellbeing — chosen by us, with what each one costs and who it actually suits. A companion to the practices in the directory, not a replacement for them.', 'oria' ); ?>
	</p>
</section>

<?php if ( ! $oria_apps ) : ?>
	<section class="wrap section section--top-flush">
		<div class="dir__empty">
			<h2 class="h3"><?php esc_html_e( 'We’re reviewing the first apps now', 'oria' ); ?></h2>
			<p class="muted" style="margin-top:.5rem"><?php esc_html_e( 'Each one is checked against its own store listing and pricing page before it goes up. Check back shortly.', 'oria' ); ?></p>
		</div>
	</section>
	<?php
	get_footer();
	return;
endif;
?>

<?php if ( count( $oria_goals ) > 2 ) : ?>
	<section class="wrap section section--top-flush">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'Start with what you want', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'Browse by goal', 'oria' ); ?></h2>
			</div>
		</div>
		<div class="appgoals">
			<?php foreach ( array_slice( $oria_goals, 0, 8, true ) as $oria_key => $oria_n ) : ?>
				<a class="appgoal reveal" href="<?php echo esc_url( add_query_arg( 'for', $oria_key, $oria_hub ) ); ?>">
					<span class="appgoal__label"><?php echo esc_html( Data\label( 'best_for', (string) $oria_key ) ); ?></span>
					<span class="appgoal__n">
						<?php
						printf(
							/* translators: %d: number of apps */
							esc_html( _n( '%d app', '%d apps', (int) $oria_n, 'oria' ) ),
							(int) $oria_n
						);
						?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php if ( $oria_picks ) : ?>
	<?php
	echo Render\band( // phpcs:ignore WordPress.Security.EscapeOutput
		$oria_picks,
		__( 'Oria picks', 'oria' ),
		__( 'The ones we would hand you first. Chosen by us, never by whoever pays a commission.', 'oria' )
	);
	?>
<?php endif; ?>

<?php if ( count( $oria_cats ) > 1 ) : ?>
	<section class="wrap section section--top-flush">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'By kind', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'App categories', 'oria' ); ?></h2>
			</div>
		</div>
		<div class="appcats">
			<?php foreach ( $oria_cats as $oria_row ) : ?>
				<a class="fchip" href="<?php echo esc_url( (string) get_term_link( $oria_row['term'] ) ); ?>">
					<?php echo esc_html( wp_specialchars_decode( $oria_row['term']->name ) ); ?>
					<span class="fchip__n"><?php echo esc_html( (string) $oria_row['n'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<section class="wrap section section--top-flush" id="all">
	<div class="sec-head reveal">
		<div class="sec-head__text">
			<span class="micro"><?php esc_html_e( 'Everything we have reviewed', 'oria' ); ?></span>
			<h2 class="h2"><?php esc_html_e( 'All wellness apps', 'oria' ); ?></h2>
		</div>
	</div>
	<div class="appgrid">
		<?php
		foreach ( $oria_apps as $oria_row ) {
			echo Render\card( $oria_row ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
	</div>
	<?php if ( Render\affiliate_in( $oria_apps ) ) : ?>
		<p class="appband__disclosure"><?php echo esc_html( Data\disclosure() ); ?></p>
	<?php endif; ?>
</section>

<section class="wrap section section--top-flush">
	<div class="appmethod appmethod--wide reveal">
		<h2 class="h3 appmethod__head"><?php esc_html_e( 'How we review apps', 'oria' ); ?></h2>
		<p><?php echo esc_html( Data\method() ); ?></p>
	</div>
</section>

<?php
get_footer();
