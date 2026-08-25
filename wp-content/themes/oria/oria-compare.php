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

use function Oria\Core\Compare\picked;
use function Oria\Core\Compare\sections;
use function Oria\Core\Compare\experiences_in;
use function Oria\Core\Compare\current_group;
use function Oria\Core\Compare\summary;

get_header();

$oria_group  = current_group();
$oria_ginfo  = \Oria\Core\Compare\group( $oria_group );
$oria_all    = experiences_in( $oria_group );
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
		<h1 class="h1 pagehead__title"><?php echo esc_html( \Oria\Core\Compare\heading() ); ?></h1>
		<p class="lede">
			<?php
			if ( $oria_ginfo ) {
				echo esc_html( (string) $oria_ginfo['blurb'] );
			} else {
				esc_html_e( 'Pick two to four and see them side by side — how hard the body works, what happens in the room, who else is there, and what it costs. Facts about the session, not promises about you.', 'oria' );
			}
			?>
		</p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<form class="cmp__picker" method="get" action="<?php echo esc_url( home_url( '/compare/' ) ); ?>" aria-label="<?php esc_attr_e( 'Choose experiences to compare', 'oria' ); ?>">
		<?php // The ids travel as one comma list, assembled from the checkboxes on submit. ?>
		<?php if ( '' !== $oria_group ) : ?>
			<?php // So an under-filled submit lands back on this group's picker, not the top-level one. ?>
			<input type="hidden" name="group" value="<?php echo esc_attr( $oria_group ); ?>">
		<?php endif; ?>
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

	<?php
	/*
	 * Two scales of comparison, and they are different questions: which
	 * practice, versus which kind of that practice. Each group scores its
	 * own attributes, so they are separate tables rather than one long
	 * picker -- and this is the only signpost between them.
	 */
	?>
	<div class="cmp__switch">
		<?php if ( '' !== $oria_group ) : ?>
			<a href="<?php echo esc_url( home_url( '/compare/' ) ); ?>">
				<span aria-hidden="true">&larr;</span>
				<?php esc_html_e( 'Compare whole categories instead', 'oria' ); ?>
			</a>
		<?php else : ?>
			<?php foreach ( \Oria\Core\Compare\groups() as $oria_gid => $oria_g ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'group', $oria_gid, home_url( '/compare/' ) ) ); ?>">
					<?php
					/* translators: %s: group name, e.g. "types of massage" */
					printf( esc_html__( 'Or compare %s', 'oria' ), esc_html( lcfirst( (string) $oria_g['label'] ) ) );
					?>
					<span aria-hidden="true">&rarr;</span>
				</a>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<?php
	/*
	 * Submitting reloads the page at the top, so on most screens the table
	 * the visitor just asked for is below the fold with nothing to say it
	 * arrived. This points at it, and clicking jumps there.
	 */
	?>
	<?php if ( $oria_picked ) : ?>
		<a class="cmp__jump" href="#result">
			<span class="cmp__jump__ring" aria-hidden="true">
				<svg viewBox="0 0 24 24" focusable="false"><path d="M12 4v13M5.5 11.5 12 18l6.5-6.5"/></svg>
			</span>
			<span><?php esc_html_e( 'Your comparison is below', 'oria' ); ?></span>
		</a>
	<?php endif; ?>
</section>

<?php if ( $oria_picked ) : ?>
	<section class="wrap section" id="result">
		<?php foreach ( sections( \Oria\Core\Compare\group_of( $oria_picked ) ) as $oria_sec ) : ?>
			<h2 class="h3 cmp__h cmp__sech"><?php echo esc_html( (string) $oria_sec['label'] ); ?></h2>
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
				<h2 class="h3 cmp__h"><?php esc_html_e( 'Reading the table', 'oria' ); ?></h2>
				<ul>
					<?php foreach ( $oria_lines as $oria_line ) : ?>
						<li><?php echo esc_html( $oria_line ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="hint"><?php esc_html_e( 'Scores describe a typical Perth session — what the hour is like, not what it will do for you. Individual studios vary; their listings carry the specifics.', 'oria' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="cmp__next">
			<h2 class="h3 cmp__h"><?php esc_html_e( 'See who runs each one', 'oria' ); ?></h2>
			<div class="chips cmp__chips">
				<?php foreach ( $oria_picked as $oria_e ) : ?>
					<a class="pill" href="<?php echo esc_url( home_url( (string) $oria_e['url'] ) ); ?>">
						<?php
						/* translators: %s: experience name */
						printf( esc_html__( '%s in Perth', 'oria' ), esc_html( (string) $oria_e['label'] ) );
						?>
						<span aria-hidden="true">&rarr;</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<?php
		/*
		 * Four, not three: the cards sit two to a line, and an odd number
		 * leaves one hanging on its own row.
		 */
		?>
		<?php $oria_try = \Oria\Core\Compare\try_listings( $oria_picked, 4 ); ?>
		<?php if ( $oria_try ) : ?>
			<div class="cmp__trybox">
				<h2 class="h3 cmp__h"><?php esc_html_e( 'Try them for yourself', 'oria' ); ?></h2>
				<p class="muted cmp__trynote">
					<?php
					if ( '' !== \Oria\Core\Compare\group_of( $oria_picked ) ) {
						esc_html_e( 'A few places offering what you compared — somewhere to start, not a shortlist.', 'oria' );
					} else {
						esc_html_e( 'A few places from the categories you compared — somewhere to start, not a shortlist.', 'oria' );
					}
					?>
				</p>
				<?php // dir__results--wide is the directory's own two-up card; the plain one is built for full page width. ?>
				<div class="cmp__trygrid dir__results dir__results--wide">
					<?php
					global $post;
					foreach ( $oria_try as $oria_tid ) :
						$post = get_post( $oria_tid ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						if ( ! $post instanceof WP_Post ) {
							continue;
						}
						setup_postdata( $post );
						get_template_part( 'template-parts/listing', 'card' );
					endforeach;
					wp_reset_postdata();
					?>
				</div>
			</div>
		<?php endif; ?>

		<div class="cmp__finder">
			<p class="muted"><?php esc_html_e( 'Still deciding? The Wellness Finder asks four questions and narrows it down for you.', 'oria' ); ?></p>
			<a class="btn btn--primary" href="<?php echo esc_url( home_url( '/wellness-finder/' ) ); ?>"><?php esc_html_e( 'Find my match', 'oria' ); ?></a>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
