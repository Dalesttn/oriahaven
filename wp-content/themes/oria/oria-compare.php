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

$oria_places = \Oria\Core\Compare\places();
$oria_scope  = \Oria\Core\Compare\place_scope();
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
			if ( $oria_places || $oria_scope ) {
				esc_html_e( 'The details we hold on each, side by side. Nothing here is scored or ranked, and a blank means nobody has told us — not that the answer is no.', 'oria' );
			} elseif ( $oria_ginfo ) {
				echo esc_html( (string) $oria_ginfo['blurb'] );
			} else {
				esc_html_e( 'Pick two to four and see them side by side — how hard the body works, what happens in the room, who else is there, and what it costs. Facts about the session, not promises about you.', 'oria' );
			}
			?>
		</p>
	</div>
</section>

<?php if ( ! $oria_places && ! $oria_scope ) : ?>
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
		<?php
		/*
		 * btn--plain on both: the base .btn reserves a right-hand gap for an
		 * arrow dot, and neither of these carries one, so without it the label
		 * sits off-centre in the pill.
		 */
		?>
		<div class="cmp__actions">
			<button class="btn btn--dark btn--plain cmp__go" type="submit" data-compare-go>
				<span data-compare-label><?php esc_html_e( 'Compare', 'oria' ); ?></span>
			</button>
			<?php if ( $oria_picked ) : ?>
				<a class="btn btn--ghost btn--plain" href="<?php echo esc_url( home_url( '/compare/' ) ); ?>"><?php esc_html_e( 'Start again', 'oria' ); ?></a>
			<?php endif; ?>
			<?php // Hidden until JS runs; without scripting the button is simply always live. ?>
			<p class="cmp__pickhint" data-compare-hint hidden><?php esc_html_e( 'Pick at least two', 'oria' ); ?></p>
		</div>
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

<?php endif; ?>

<?php
/*
 * The places view: real Perth businesses, side by side.
 *
 * Every cell is a field we hold. Nothing here is scored, and nothing is
 * crossed out -- an empty field says "Not listed", because most of these
 * businesses have never told us anything and an absence is our gap, not
 * their shortcoming. There is deliberately no winner: the site promises
 * "it counts, it never ranks" on every category page.
 */
?>
<?php if ( $oria_scope && ! $oria_places ) : ?>
	<?php $oria_pool = \Oria\Core\Compare\scope_listings( $oria_scope ); ?>
	<section class="wrap section section--top-flush">
		<?php if ( ! $oria_pool ) : ?>
			<p class="muted"><?php esc_html_e( 'Nothing is listed in this category yet.', 'oria' ); ?></p>
		<?php else : ?>
			<form class="cmp__picker" method="get" action="<?php echo esc_url( home_url( '/compare/' ) ); ?>" aria-label="<?php esc_attr_e( 'Choose places to compare', 'oria' ); ?>">
				<div class="cmp__grid cmp__grid--places" data-compare-picker data-max="4">
					<?php foreach ( $oria_pool as $oria_pl ) : ?>
						<?php $oria_ar = wp_get_post_terms( (int) $oria_pl->ID, 'area', array( 'fields' => 'names' ) ); ?>
						<label class="check cmp__pick">
							<input type="checkbox" name="place[]" value="<?php echo esc_attr( $oria_pl->post_name ); ?>">
							<span>
								<?php echo esc_html( get_the_title( $oria_pl ) ); ?>
								<?php if ( ! is_wp_error( $oria_ar ) && $oria_ar ) : ?>
									<em><?php echo esc_html( (string) $oria_ar[0] ); ?></em>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
				<div class="cmp__actions">
					<button class="btn btn--dark btn--plain cmp__go" type="submit" data-compare-go>
						<span data-compare-label><?php esc_html_e( 'Compare', 'oria' ); ?></span>
					</button>
					<p class="cmp__pickhint" data-compare-hint hidden><?php esc_html_e( 'Pick at least two', 'oria' ); ?></p>
				</div>
			</form>
		<?php endif; ?>
		<div class="cmp__switch">
			<a href="<?php echo esc_url( home_url( '/compare/' ) ); ?>">
				<span aria-hidden="true">&larr;</span>
				<?php esc_html_e( 'Compare practices instead of places', 'oria' ); ?>
			</a>
		</div>
	</section>
<?php endif; ?>

<?php if ( $oria_places ) : ?>
	<?php $oria_head = \Oria\Core\Compare\place_header( $oria_places ); ?>
	<section class="wrap section section--top-flush" id="result">

	<?php
	/*
	 * One semantic table, restyled -- not a table for desktop plus a second
	 * copy of the same facts for phones. The mobile treatment is a sticky
	 * label column and a sideways scroll, so there is one set of markup for
	 * crawlers and screen readers alike.
	 */
	?>
	<div class="cmpx">
		<div class="cmpx__scroll" tabindex="0" role="group" aria-label="<?php esc_attr_e( 'Comparison table, scrolls sideways', 'oria' ); ?>">
			<table class="cmpx__table">
				<caption class="sr-only"><?php esc_html_e( 'The details on file for each place being compared', 'oria' ); ?></caption>
				<thead>
					<tr>
						<th scope="col" class="cmpx__corner"><span class="sr-only"><?php esc_html_e( 'Detail', 'oria' ); ?></span></th>
						<?php foreach ( $oria_head as $oria_h ) : ?>
							<th scope="col" class="cmpx__col">
								<a class="cmpx__profile" href="<?php echo esc_url( (string) $oria_h['url'] ); ?>">
									<?php if ( '' !== (string) $oria_h['image'] ) : ?>
										<img class="cmpx__shot" src="<?php echo esc_url( (string) $oria_h['image'] ); ?>" alt="" loading="lazy" width="320" height="200">
									<?php endif; ?>
									<span class="cmpx__name"><?php echo esc_html( (string) $oria_h['name'] ); ?></span>
								</a>
								<?php if ( '' !== (string) $oria_h['area'] ) : ?>
									<span class="cmpx__area"><?php echo esc_html( (string) $oria_h['area'] ); ?></span>
								<?php endif; ?>
								<?php if ( $oria_h['rating'] > 0 ) : ?>
									<span class="cmpx__rate">
										<b><span class="cmpx__star" aria-hidden="true">&#9733;</span><?php echo esc_html( number_format_i18n( (float) $oria_h['rating'], 1 ) ); ?></b>
										<small>
											<?php
											/* translators: %s: number of reviews */
											printf( esc_html__( '%s Google reviews', 'oria' ), esc_html( number_format_i18n( (int) $oria_h['reviews'] ) ) );
											?>
										</small>
									</span>
								<?php endif; ?>
								<?php if ( $oria_h['badges'] ) : ?>
									<span class="cmpx__badges">
										<?php foreach ( $oria_h['badges'] as $oria_b ) : ?>
											<span class="cmpx__badge"><?php echo esc_html( (string) $oria_b ); ?></span>
										<?php endforeach; ?>
									</span>
								<?php endif; ?>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( \Oria\Core\Compare\place_rows( $oria_places ) as $oria_row ) : ?>
						<tr>
							<th scope="row" class="cmpx__label">
								<?php echo esc_html( (string) $oria_row['label'] ); ?>
								<?php if ( '' !== (string) $oria_row['hint'] ) : ?>
									<small><?php echo esc_html( (string) $oria_row['hint'] ); ?></small>
								<?php endif; ?>
							</th>
							<?php foreach ( (array) $oria_row['values'] as $oria_v ) : ?>
								<?php $oria_blank = ( (string) $oria_v === \Oria\Core\Compare\unknown() ); ?>
								<td class="cmpx__cell<?php echo $oria_blank ? ' is-blank' : ''; ?>">
									<?php if ( $oria_blank ) : ?>
										<?php // The dash is decoration; the words carry the accessible name. ?>
										<span aria-hidden="true">&mdash;</span>
										<span class="sr-only"><?php echo esc_html( \Oria\Core\Compare\unknown_spoken() ); ?></span>
									<?php elseif ( 'rating' === $oria_row['type'] ) : ?>
										<?php $oria_bits = explode( '|', (string) $oria_v ); ?>
										<span class="cmpx__rate cmpx__rate--cell">
											<b><span class="cmpx__star" aria-hidden="true">&#9733;</span><?php echo esc_html( $oria_bits[0] ); ?></b>
											<small><?php echo esc_html( $oria_bits[1] ?? '' ); ?></small>
										</span>
									<?php elseif ( 'format' === $oria_row['type'] ) : ?>
										<span class="cmpx__dot" aria-hidden="true"></span><?php echo esc_html( (string) $oria_v ); ?>
									<?php elseif ( 'confirm' === $oria_row['type'] ) : ?>
										<span class="cmpx__confirm<?php echo str_starts_with( (string) $oria_v, 'Confirmed' ) ? ' is-yes' : ''; ?>"><?php echo esc_html( (string) $oria_v ); ?></span>
									<?php else : ?>
										<?php echo esc_html( str_replace( ', ', ' · ', (string) $oria_v ) ); ?>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td class="cmpx__label"></td>
						<?php foreach ( $oria_head as $oria_h ) : ?>
							<td class="cmpx__cell">
								<a class="cmpx__cta" href="<?php echo esc_url( (string) $oria_h['url'] ); ?>">
									<?php esc_html_e( 'Explore place', 'oria' ); ?>
									<span class="sr-only"><?php echo esc_html( (string) $oria_h['name'] ); ?></span>
									<span class="cmpx__ctaarrow" aria-hidden="true">&rarr;</span>
								</a>
							</td>
						<?php endforeach; ?>
					</tr>
				</tfoot>
			</table>
		</div>
		<p class="cmpx__scrollhint"><?php esc_html_e( 'Scroll sideways for the rest', 'oria' ); ?></p>
	</div>

	<?php $oria_ins = \Oria\Core\Compare\place_insights( $oria_places ); ?>
	<?php if ( $oria_ins ) : ?>
		<div class="cmpx__insights">
			<h2 class="h3 cmp__h"><?php esc_html_e( 'Oria Haven insights', 'oria' ); ?></h2>
			<p class="muted cmpx__insnote">
				<?php esc_html_e( 'Differences the data actually shows. Not a verdict on which is better — we do not rank the places we list.', 'oria' ); ?>
			</p>
			<div class="cmpx__insgrid">
				<?php foreach ( $oria_ins as $oria_in ) : ?>
					<div class="cmpx__ins">
						<span class="cmpx__inslabel"><?php echo esc_html( (string) $oria_in['label'] ); ?></span>
						<strong class="cmpx__insname"><?php echo esc_html( (string) $oria_in['name'] ); ?></strong>
						<span class="cmpx__insdetail"><?php echo esc_html( (string) $oria_in['detail'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php $oria_why = \Oria\Core\Compare\place_reasons( $oria_places ); ?>
	<?php if ( $oria_why ) : ?>
		<div class="cmpx__why">
			<h2 class="h3 cmp__h"><?php esc_html_e( 'Why you might choose them', 'oria' ); ?></h2>
			<dl class="cmpx__whylist">
				<?php foreach ( $oria_why as $oria_w ) : ?>
					<div>
						<dt><a href="<?php echo esc_url( (string) $oria_w['url'] ); ?>"><?php echo esc_html( (string) $oria_w['name'] ); ?></a></dt>
						<dd><?php echo esc_html( (string) $oria_w['line'] ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		</div>
	<?php endif; ?>

	<?php $oria_plines = \Oria\Core\Compare\place_summary( $oria_places ); ?>
	<?php if ( $oria_plines ) : ?>
		<div class="cmp__reading">
			<h2 class="h3 cmp__h"><?php esc_html_e( 'Reading the table', 'oria' ); ?></h2>
			<ul>
				<?php foreach ( $oria_plines as $oria_pline ) : ?>
					<li><?php echo esc_html( $oria_pline ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="hint">
				<?php esc_html_e( 'These are the details on file, not a verdict. We do not rank businesses and nobody can pay to appear here — where a row shows a dash, ask them; it usually means nobody has told us either way.', 'oria' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="cmp__next">
		<h2 class="h3 cmp__h"><?php esc_html_e( 'Look at them properly', 'oria' ); ?></h2>
		<p class="muted cmp__trynote">
			<?php esc_html_e( 'The table is only what fits in a table. The photographs, the write-ups and the contact details are on the listings.', 'oria' ); ?>
		</p>
		<div class="cmp__trygrid dir__results dir__results--wide">
			<?php
			global $post;
			foreach ( $oria_places as $oria_pl ) :
				$post = $oria_pl; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $post );
				get_template_part( 'template-parts/listing', 'card' );
			endforeach;
			wp_reset_postdata();
			?>
		</div>
	</div>

	<div class="cmp__finder">
		<p class="muted"><?php esc_html_e( 'Run one of these? Claim the listing and fill in the blanks yourself — it is free, and it is your answer rather than ours.', 'oria' ); ?></p>
		<a class="btn btn--dark btn--plain" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'Claim a listing', 'oria' ); ?></a>
	</div>
	</section>
<?php endif; ?>


<?php if ( $oria_picked ) : ?>
	<?php
	$oria_g     = \Oria\Core\Compare\group_of( $oria_picked );
	$oria_glance = \Oria\Core\Compare\glance_rows( $oria_picked, $oria_g );
	$oria_prefs  = \Oria\Core\Compare\preference_bullets( $oria_picked, $oria_g );
	$oria_lines  = summary( $oria_picked );
	?>

	<?php /* ---------------------------------------- hero cards */ ?>
	<section class="wrap section section--top-flush" id="result">
		<div class="xp__heroes" data-n="<?php echo (int) count( $oria_picked ); ?>">
			<?php foreach ( $oria_picked as $oria_e ) : ?>
				<?php $oria_tr = \Oria\Core\Compare\traits_of( $oria_e, $oria_g ); ?>
				<article class="xp__hero">
					<h2 class="xp__heroname"><?php echo esc_html( (string) $oria_e['label'] ); ?></h2>
					<?php if ( $oria_tr ) : ?>
						<p class="xp__traits">
							<?php foreach ( $oria_tr as $oria_i => $oria_t ) : ?>
								<?php if ( $oria_i ) : ?><span aria-hidden="true"> · </span><?php endif; ?>
								<span><?php echo esc_html( $oria_t ); ?></span>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>
					<?php
					$oria_dur = (string) ( $oria_e['attributes']['duration'] ?? '' );
					$oria_prc = (string) ( $oria_e['attributes']['price'] ?? '' );
					?>
					<?php if ( '' !== $oria_dur || '' !== $oria_prc ) : ?>
						<p class="xp__facts">
							<?php if ( '' !== $oria_dur ) : ?><span class="xp__fact"><?php echo esc_html( $oria_dur ); ?></span><?php endif; ?>
							<?php if ( '' !== $oria_prc ) : ?><span class="xp__fact"><?php echo esc_html( $oria_prc ); ?></span><?php endif; ?>
						</p>
					<?php endif; ?>
					<?php $oria_note = (string) ( $oria_e['note'] ?? '' ); ?>
					<?php if ( '' !== $oria_note ) : ?>
						<p class="xp__note"><?php echo esc_html( $oria_note ); ?></p>
					<?php endif; ?>
					<a class="xp__explore" href="<?php echo esc_url( home_url( (string) $oria_e['url'] ) ); ?>">
						<?php
						/* translators: %s: experience name */
						printf( esc_html__( 'Explore %s', 'oria' ), esc_html( (string) $oria_e['label'] ) );
						?>
						<span class="xp__arrow" aria-hidden="true">&rarr;</span>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<?php /* ---------------------------------------- at a glance */ ?>
	<?php if ( $oria_glance ) : ?>
		<section class="wrap section">
			<h2 class="h3 cmp__h"><?php esc_html_e( 'At a glance', 'oria' ); ?></h2>
			<p class="muted xp__lede"><?php esc_html_e( 'Where these differ most. Everything is a description of the session, never a promise about you.', 'oria' ); ?></p>
			<div class="cmpx__scroll" tabindex="0" role="group" aria-label="<?php esc_attr_e( 'Comparison at a glance', 'oria' ); ?>">
				<table class="cmpx__table xp__glance">
					<thead>
						<tr>
							<th scope="col" class="cmpx__corner"><span class="sr-only"><?php esc_html_e( 'Attribute', 'oria' ); ?></span></th>
							<?php foreach ( $oria_picked as $oria_e ) : ?>
								<th scope="col" class="cmpx__col xp__gcol"><?php echo esc_html( (string) $oria_e['label'] ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $oria_glance as $oria_row ) : ?>
							<tr>
								<th scope="row" class="cmpx__label"><?php echo esc_html( (string) $oria_row['label'] ); ?></th>
								<?php foreach ( (array) $oria_row['values'] as $oria_v ) : ?>
									<td class="cmpx__cell">
										<?php if ( 'scale' === $oria_row['type'] ) : ?>
											<span class="xp__scale">
												<?php echo $oria_dots( (int) $oria_v ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from literals. ?>
												<b><?php printf( '%d/5', (int) $oria_v ); ?></b>
												<small><?php echo esc_html( \Oria\Core\Compare\scale_word( (int) $oria_v ) ); ?></small>
											</span>
										<?php else : ?>
											<?php echo esc_html( (string) $oria_v ); ?>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="cmpx__scrollhint"><?php esc_html_e( 'Scroll sideways for the rest', 'oria' ); ?></p>
		</section>
	<?php endif; ?>

	<?php /* ------------------------------- the insight, given room */ ?>
	<?php if ( $oria_lines ) : ?>
		<section class="wrap section">
			<div class="xp__insight">
				<span class="xp__insmark" aria-hidden="true">&#10022;</span>
				<h2 class="h3 xp__inshead"><?php esc_html_e( 'Oria Haven insight', 'oria' ); ?></h2>
				<ul class="xp__inslist">
					<?php foreach ( $oria_lines as $oria_line ) : ?>
						<li><?php echo esc_html( $oria_line ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="xp__insfoot"><?php esc_html_e( 'Scores describe a typical Perth session — what the hour is like, not what it will do for you. Individual studios vary; their listings carry the specifics.', 'oria' ); ?></p>
			</div>
		</section>
	<?php endif; ?>

	<?php /* -------------------------- the full tables, section by section */ ?>
	<section class="wrap section">
		<?php foreach ( sections( $oria_g ) as $oria_key => $oria_sec ) : ?>
			<h2 class="h3 cmp__h cmp__sech"><?php echo esc_html( \Oria\Core\Compare\section_heading( (string) $oria_key, (string) $oria_sec['label'], $oria_g ) ); ?></h2>
			<div class="cmpx__scroll" tabindex="0" role="group" aria-label="<?php echo esc_attr( (string) $oria_sec['label'] ); ?>">
				<table class="cmpx__table">
					<thead>
						<tr>
							<th scope="col" class="cmpx__corner"><span class="sr-only"><?php esc_html_e( 'Attribute', 'oria' ); ?></span></th>
							<?php foreach ( $oria_picked as $oria_e ) : ?>
								<th scope="col" class="cmpx__col xp__gcol"><a href="<?php echo esc_url( home_url( (string) $oria_e['url'] ) ); ?>"><?php echo esc_html( (string) $oria_e['label'] ); ?></a></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( (array) $oria_sec['items'] as $oria_ak => $oria_def ) : ?>
							<tr>
								<th scope="row" class="cmpx__label">
									<?php echo esc_html( (string) $oria_def['label'] ); ?>
									<?php if ( ! empty( $oria_def['hint'] ) ) : ?>
										<small><?php echo esc_html( (string) $oria_def['hint'] ); ?></small>
									<?php endif; ?>
								</th>
								<?php foreach ( $oria_picked as $oria_e ) : ?>
									<?php $oria_v = $oria_e['attributes'][ $oria_ak ] ?? ''; ?>
									<td class="cmpx__cell<?php echo '' === $oria_v ? ' is-blank' : ''; ?>">
										<?php if ( '' === $oria_v ) : ?>
											<span aria-hidden="true">&mdash;</span><span class="sr-only"><?php echo esc_html( \Oria\Core\Compare\unknown_spoken() ); ?></span>
										<?php elseif ( 'scale' === ( $oria_def['type'] ?? '' ) ) : ?>
											<span class="xp__scale">
												<?php echo $oria_dots( (int) $oria_v ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from literals. ?>
												<b><?php printf( '%d/5', (int) $oria_v ); ?></b>
												<small><?php echo esc_html( \Oria\Core\Compare\scale_word( (int) $oria_v ) ); ?></small>
											</span>
										<?php else : ?>
											<?php echo esc_html( (string) $oria_v ); ?>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>
	</section>

	<?php /* ------------------------- which one sounds more like you */ ?>
	<?php if ( $oria_prefs ) : ?>
		<section class="wrap section">
			<h2 class="h3 cmp__h"><?php esc_html_e( 'Which one sounds more like you?', 'oria' ); ?></h2>
			<p class="muted xp__lede"><?php esc_html_e( 'A summary of the differences above, not advice — only you know which you would rather walk into.', 'oria' ); ?></p>
			<div class="xp__prefs">
				<?php foreach ( $oria_prefs as $oria_pf ) : ?>
					<?php if ( ! $oria_pf['wants'] ) { continue; } ?>
					<div class="xp__pref">
						<h3 class="xp__prefhead">
							<?php
							/* translators: %s: experience name */
							printf( esc_html__( 'Choose %s if you want', 'oria' ), esc_html( (string) $oria_pf['label'] ) );
							?>
						</h3>
						<ul class="xp__preflist">
							<?php foreach ( $oria_pf['wants'] as $oria_w ) : ?>
								<li><?php echo esc_html( $oria_w ); ?></li>
							<?php endforeach; ?>
						</ul>
						<a class="xp__explore xp__explore--sm" href="<?php echo esc_url( home_url( (string) $oria_pf['url'] ) ); ?>">
							<?php esc_html_e( 'Explore', 'oria' ); ?>
							<span class="sr-only"><?php echo esc_html( (string) $oria_pf['label'] ); ?></span>
							<span class="xp__arrow" aria-hidden="true">&rarr;</span>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php /* --------------------------------- still unsure -> Finder */ ?>
	<section class="wrap section">
		<div class="xp__finder">
			<h2 class="h3 xp__finderhead"><?php esc_html_e( 'Still not sure?', 'oria' ); ?></h2>
			<p><?php esc_html_e( 'Four questions, about a minute, and the Finder narrows it to what fits — drawn from practices checked by hand.', 'oria' ); ?></p>
			<a class="btn btn--dark btn--plain" href="<?php echo esc_url( home_url( '/wellness-finder/' ) ); ?>"><?php esc_html_e( 'Take the Wellness Finder', 'oria' ); ?></a>
		</div>
	</section>

	<?php /* ------------------------------------ ready to explore */ ?>
	<section class="wrap section">
		<h2 class="h3 cmp__h"><?php esc_html_e( 'Ready to explore?', 'oria' ); ?></h2>
		<div class="xp__explores">
			<?php foreach ( $oria_picked as $oria_e ) : ?>
				<a class="xp__exptile" href="<?php echo esc_url( home_url( (string) $oria_e['url'] ) ); ?>">
					<span class="xp__exptitle">
						<?php
						/* translators: %s: experience name */
						printf( esc_html__( '%s in Perth', 'oria' ), esc_html( (string) $oria_e['label'] ) );
						?>
					</span>
					<span class="xp__arrow" aria-hidden="true">&rarr;</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

	<?php /* -------------------------------- a few actual places */ ?>
	<?php $oria_try = \Oria\Core\Compare\try_listings( $oria_picked, 4 ); ?>
	<?php if ( $oria_try ) : ?>
		<section class="wrap section">
			<h2 class="h3 cmp__h"><?php esc_html_e( 'Try them for yourself', 'oria' ); ?></h2>
			<p class="muted cmp__trynote">
				<?php
				if ( '' !== $oria_g ) {
					esc_html_e( 'A few places offering what you compared — somewhere to start, not a shortlist.', 'oria' );
				} else {
					esc_html_e( 'A few places from the categories you compared — somewhere to start, not a shortlist.', 'oria' );
				}
				?>
			</p>
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
		</section>
	<?php endif; ?>
<?php endif; ?>

<?php
get_footer();
