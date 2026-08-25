<?php
/**
 * /compare/ — two to four experiences side by side. See Oria\Core\Compare.
 *
 * Everything renders server-side: the picker is a plain GET form and the
 * tables are HTML, so the page works with scripting off and a crawler
 * sees the whole comparison. The dots are text with an aria-label, not
 * images, for the same reason.
 */

declare(strict_types=1);

use function Oria\Core\Compare\experiences;
use function Oria\Core\Compare\picked;
use function Oria\Core\Compare\sections;
use function Oria\Core\Compare\summary;

get_header();

$oria_all    = experiences();
$oria_picked = picked();
$oria_ids    = array_map( static fn( array $e ): string => (string) $e['id'], $oria_picked );

/** Five dots, filled to the score — text so it reads without CSS. */
$oria_dots = static function ( int $n ): string {
	$n = max( 1, min( 5, $n ) );
	return '<span class="cmp__dots" role="img" aria-label="' . esc_attr( sprintf( __( '%d out of 5', 'oria' ), $n ) ) . '">'
		. str_repeat( '<b>●</b>', $n ) . str_repeat( '<i>●</i>', 5 - $n )
		. '</span>';
};
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Compare', 'oria' ); ?></span>
	</nav>
	<div style="margin-top:1rem;max-width:44rem">
		<h1 class="h1 pagehead__title"><?php esc_html_e( 'Compare wellness experiences', 'oria' ); ?></h1>
		<p class="lede">
			<?php esc_html_e( 'Pick two to four and see them side by side — how hard the body works, what happens in the room, who else is there, and what it costs. Facts about the session, not promises about you.', 'oria' ); ?>
		</p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<form class="cmp__picker" method="get" action="<?php echo esc_url( home_url( '/compare/' ) ); ?>" aria-label="<?php esc_attr_e( 'Choose experiences to compare', 'oria' ); ?>">
		<?php // The ids travel as one comma list, assembled from the checkboxes on submit. ?>
		<div class="cmp__grid" data-compare-picker data-max="4">
			<?php foreach ( $oria_all as $oria_e ) : ?>
				<label class="check cmp__pick">
					<input type="checkbox" name="pick[]" value="<?php echo esc_attr( (string) $oria_e['id'] ); ?>" <?php checked( in_array( (string) $oria_e['id'], $oria_ids, true ) ); ?>>
					<span><?php echo esc_html( (string) $oria_e['label'] ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<button class="btn btn--primary" type="submit" style="margin-top:1rem"><?php esc_html_e( 'Compare', 'oria' ); ?></button>
		<?php if ( $oria_picked ) : ?>
			<a class="btn btn--ghost" style="margin-top:1rem" href="<?php echo esc_url( home_url( '/compare/' ) ); ?>"><?php esc_html_e( 'Start again', 'oria' ); ?></a>
		<?php endif; ?>
	</form>
</section>

<?php if ( $oria_picked ) : ?>
	<section class="wrap section" id="result">
		<?php foreach ( sections() as $oria_sec ) : ?>
			<h2 class="h3" style="margin-top:2rem"><?php echo esc_html( (string) $oria_sec['label'] ); ?></h2>
			<div class="cmp__scroll">
				<table class="cmp__table">
					<thead>
						<tr>
							<th scope="col" class="cmp__attr"><span class="sr-only"><?php esc_html_e( 'Attribute', 'oria' ); ?></span></th>
							<?php foreach ( $oria_picked as $oria_e ) : ?>
								<th scope="col"><a href="<?php echo esc_url( home_url( (string) $oria_e['url'] ) ); ?>"><?php echo esc_html( (string) $oria_e['label'] ); ?></a></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( (array) $oria_sec['items'] as $oria_key => $oria_def ) : ?>
							<tr>
								<th scope="row" class="cmp__attr">
									<?php echo esc_html( (string) $oria_def['label'] ); ?>
									<?php if ( ! empty( $oria_def['hint'] ) ) : ?>
										<small><?php echo esc_html( (string) $oria_def['hint'] ); ?></small>
									<?php endif; ?>
								</th>
								<?php foreach ( $oria_picked as $oria_e ) : ?>
									<?php $oria_v = $oria_e['attributes'][ $oria_key ] ?? ''; ?>
									<td>
										<?php
										if ( 'scale' === ( $oria_def['type'] ?? '' ) ) {
											echo $oria_dots( (int) $oria_v ); // phpcs:ignore WordPress.Security.EscapeOutput -- built above from literals.
										} else {
											echo esc_html( (string) $oria_v );
										}
										?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>

		<?php $oria_lines = summary( $oria_picked ); ?>
		<?php if ( $oria_lines ) : ?>
			<div class="cmp__reading">
				<h2 class="h3"><?php esc_html_e( 'Reading the table', 'oria' ); ?></h2>
				<ul>
					<?php foreach ( $oria_lines as $oria_line ) : ?>
						<li><?php echo esc_html( $oria_line ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="hint"><?php esc_html_e( 'Scores describe a typical Perth session — what the hour is like, not what it will do for you. Individual studios vary; their listings carry the specifics.', 'oria' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="cmp__next">
			<h2 class="h3"><?php esc_html_e( 'See who runs each one', 'oria' ); ?></h2>
			<div class="chips" style="margin-top:.6rem">
				<?php foreach ( $oria_picked as $oria_e ) : ?>
					<a class="pill" href="<?php echo esc_url( home_url( (string) $oria_e['url'] ) ); ?>">
						<?php
						/* translators: %s: experience name */
						printf( esc_html__( '%s in Perth', 'oria' ), esc_html( (string) $oria_e['label'] ) );
						?>
					</a>
				<?php endforeach; ?>
			</div>
			<p class="hint" style="margin-top:1rem">
				<?php esc_html_e( 'Still deciding? The Wellness Finder asks four questions and narrows it down for you.', 'oria' ); ?>
				<a href="<?php echo esc_url( home_url( '/wellness-finder/' ) ); ?>"><?php esc_html_e( 'Find my match', 'oria' ); ?></a>
			</p>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
