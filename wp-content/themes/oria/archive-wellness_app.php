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

/*
 * The goal a visitor picked, if any.
 *
 * A query parameter rather than a URL of its own, and that is the whole
 * reason "best for" is a field instead of a taxonomy: sixteen of these as
 * real pages would be sixteen near-identical lists on day one. So the
 * filter narrows the page in place, and Pages\ tells crawlers to index the
 * hub rather than the filtered view of it.
 *
 * Validated against the vocabulary, so ?for= anything else simply shows
 * the whole hub rather than an empty page.
 */
$oria_for = isset( $_GET['for'] ) ? sanitize_key( wp_unslash( $_GET['for'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( '' !== $oria_for && ! isset( Data\BEST_FOR[ $oria_for ] ) ) {
	$oria_for = '';
}

// The goals and categories above keep counting the whole catalogue: they
// are how you change your mind, so they must not narrow with the page.
$oria_shown = '' !== $oria_for ? Engine\by_best_for( $oria_for ) : $oria_apps;
?>

<?php
/*
 * The header picture comes from the stylesheet rather than from a post,
 * the way the singing bowls hub supplies its own -- a hub has no featured
 * image to wear. The copy moves into .pagehead__copy because that is the
 * box the band narrows to make room for the photograph.
 */
?>
<div class="heroband heroband--stack appshero-band">
<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Apps', 'oria' ); ?></span>
	</nav>
	<div class="pagehead__copy">
		<span class="micro"><?php esc_html_e( 'Wellness at home', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title"><?php esc_html_e( 'Wellness apps & digital tools', 'oria' ); ?></h1>
		<p class="lede pagehead__lede">
			<?php esc_html_e( 'Apps for meditation, sleep, movement and everyday wellbeing — chosen by us, with what each one costs and who it actually suits. A companion to the practices in the directory, not a replacement for them.', 'oria' ); ?>
		</p>
	</div>
</section>
</div>

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
				<?php $oria_on = ( (string) $oria_key === $oria_for ); ?>
				<a class="appgoal reveal<?php echo $oria_on ? ' is-on' : ''; ?>"
					href="<?php echo esc_url( $oria_on ? $oria_hub . '#all' : add_query_arg( 'for', $oria_key, $oria_hub ) . '#all' ); ?>"
					<?php echo $oria_on ? 'aria-current="true"' : ''; ?>>
					<span class="appgoal__mark"><?php echo Render\goal_icon( (string) $oria_key ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="appgoal__text">
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
					</span>
					<span class="appgoal__go" aria-hidden="true">
						<svg viewBox="0 0 14 14" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 7h9M8 3.5 11.5 7 8 10.5"/></svg>
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

<?php
/*
 * The editorial shortlists. Above "all apps" on purpose: somebody who
 * wanted to browse is already scrolling, and somebody who wanted to be
 * told which one to get is better served by a list that says so.
 */
$oria_guides = function_exists( '\Oria\Apps\Guides\all' ) ? \Oria\Apps\Guides\all( 4 ) : array();
?>
<?php if ( $oria_guides ) : ?>
	<section class="wrap section section--top-flush">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'Told, not browsed', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'Best wellness apps', 'oria' ); ?></h2>
			</div>
		</div>
		<div class="gdlist">
			<?php foreach ( $oria_guides as $oria_gid ) : ?>
				<?php $oria_g = \Oria\Apps\Guides\guide( (int) $oria_gid ); ?>
				<article class="gdcard">
					<a class="gdcard__link" href="<?php echo esc_url( (string) $oria_g['url'] ); ?>">
						<h3 class="h3 gdcard__title"><?php echo esc_html( (string) $oria_g['title'] ); ?></h3>
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
						?>
					</p>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<section class="wrap section section--top-flush" id="all">
	<div class="sec-head reveal">
		<div class="sec-head__text">
			<?php if ( '' !== $oria_for ) : ?>
				<span class="micro"><?php esc_html_e( 'Filtered', 'oria' ); ?></span>
				<h2 class="h2">
					<?php
					printf(
						/* translators: %s: a goal, e.g. better sleep */
						esc_html__( 'Apps for %s', 'oria' ),
						esc_html( strtolower( Data\label( 'best_for', $oria_for ) ) )
					);
					?>
				</h2>
			<?php else : ?>
				<span class="micro"><?php esc_html_e( 'Everything we have reviewed', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'All wellness apps', 'oria' ); ?></h2>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $oria_for ) : ?>
			<a class="btn btn--ghost" href="<?php echo esc_url( $oria_hub . '#all' ); ?>">
				<?php
				printf(
					/* translators: %d: how many apps there are in total */
					esc_html__( 'Show all %d', 'oria' ),
					(int) count( $oria_apps )
				);
				?>
			</a>
		<?php endif; ?>
	</div>
	<div class="appgrid">
		<?php
		foreach ( $oria_shown as $oria_row ) {
			echo Render\card( $oria_row ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
	</div>
	<?php if ( Render\affiliate_in( $oria_shown ) ) : ?>
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
