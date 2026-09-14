<?php
/**
 * One Best Of guide.
 *
 * Head, the picks, how they were chosen, a side-by-side table once there are
 * enough to compare, questions, where to go next. Every guide-specific word
 * comes from the post and its fields; this file knows nothing about yoga.
 */

declare(strict_types=1);

use Oria\Core\BestOf;

get_header();
the_post();

$oria_id      = (int) get_the_ID();
$oria_entries = BestOf\entries( $oria_id );
$oria_n       = count( $oria_entries );
$oria_cat     = BestOf\category_label( BestOf\category( $oria_id ) );
$oria_prac    = BestOf\practice( $oria_id );
$oria_method  = trim( (string) get_field( 'methodology', $oria_id ) );
$oria_note    = trim( (string) get_field( 'editor_note', $oria_id ) );
$oria_faq     = array_values( array_filter( (array) get_field( 'guide_faq', $oria_id ), static fn( $r ) => ! empty( $r['question'] ) && ! empty( $r['answer'] ) ) );
$oria_links   = array_values( array_filter( (array) get_field( 'guide_links', $oria_id ), static fn( $r ) => ! empty( $r['label'] ) && ! empty( $r['url'] ) ) );
$oria_related = BestOf\related( $oria_id, 3 );
$oria_qa      = BestOf\quick_answer( $oria_id );
$oria_spots   = BestOf\spotlights( $oria_entries );
$oria_choose  = BestOf\choose( $oria_id );
?>

<article>
<section class="wrap bohero">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( BestOf\hub_url() ); ?>"><?php esc_html_e( 'Best Of', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php the_title(); ?></span>
	</nav>
	<span class="bohero__eyebrow"><?php echo esc_html( implode( ' · ', array_filter( array( __( 'Best of Perth', 'oria' ), $oria_cat ) ) ) ); ?></span>
	<h1 class="bohero__title"><?php the_title(); ?></h1>
	<?php if ( BestOf\intro( $oria_id ) ) : ?>
		<p class="bohero__lede"><?php echo esc_html( BestOf\intro( $oria_id ) ); ?></p>
	<?php endif; ?>
	<p class="bohero__meta">
		<?php
		$oria_bits = array();
		if ( $oria_n ) {
			/* translators: %s: number of picks */
			$oria_bits[] = sprintf( _n( '%s pick', '%s picks', $oria_n, 'oria' ), number_format_i18n( $oria_n ) );
		}
		$oria_bits[] = BestOf\updated( $oria_id );
		$oria_bits[] = __( 'Editorial selection', 'oria' );
		echo esc_html( implode( ' · ', $oria_bits ) );
		?>
	</p>
</section>

<?php if ( $oria_qa || $oria_spots ) : ?>
	<section class="wrap bosection">
		<?php if ( $oria_qa ) : ?>
			<?php
			/*
			 * The quick answer: the guide in three sentences, for somebody
			 * (or something) that will not read the rest. It names the picks,
			 * so it is useful on its own and never a teaser.
			 */
			?>
			<div class="boqa reveal">
				<span class="micro"><?php esc_html_e( 'Quick answer', 'oria' ); ?></span>
				<p><?php echo esc_html( $oria_qa ); ?></p>
			</div>
		<?php endif; ?>
		<?php if ( $oria_spots ) : ?>
			<ul class="bospots reveal">
				<?php foreach ( $oria_spots as $oria_s ) : ?>
					<li class="bospot">
						<span class="bospot__label"><?php echo esc_html( $oria_s['spotlight'] ); ?></span>
						<a class="bospot__name" href="<?php echo esc_url( (string) get_permalink( $oria_s['listing'] ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_s['listing'] ) ) ); ?></a>
						<span class="bospot__where"><?php echo esc_html( BestOf\suburb( $oria_s['listing'] ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
<?php endif; ?>

<section class="wrap bosection" id="picks">
	<div class="bopicks-head reveal">
		<div class="sec-head__text">
			<span class="micro"><?php esc_html_e( 'Our picks', 'oria' ); ?></span>
			<?php if ( $oria_n ) : ?>
				<h2 class="h2"><?php echo esc_html( sprintf( /* translators: %s: number of picks */ _n( '%s place worth a first visit', '%s places worth a first visit', $oria_n, 'oria' ), number_format_i18n( $oria_n ) ) ); ?></h2>
			<?php endif; ?>
		</div>
		<?php if ( $oria_note ) : ?>
			<aside class="boednote">
				<span class="micro"><?php esc_html_e( "Editor's note", 'oria' ); ?></span>
				<?php echo esc_html( $oria_note ); ?>
			</aside>
		<?php endif; ?>
	</div>

	<?php if ( $oria_entries ) : ?>
		<ol class="bopicks">
			<?php foreach ( $oria_entries as $oria_i => $oria_e ) : ?>
				<?php get_template_part( 'template-parts/best-pick', null, array( 'entry' => $oria_e, 'rank' => $oria_i + 1 ) ); ?>
			<?php endforeach; ?>
		</ol>
	<?php else : ?>
		<p class="muted"><?php esc_html_e( 'The picks for this guide are being finalised.', 'oria' ); ?></p>
	<?php endif; ?>
</section>

<?php if ( $oria_method ) : ?>
	<section class="wrap bosection">
		<div class="bohow reveal">
			<div>
				<span class="micro"><?php esc_html_e( 'How we chose', 'oria' ); ?></span>
				<h2 class="h3"><?php esc_html_e( 'What we considered', 'oria' ); ?></h2>
			</div>
			<div class="bohow__text">
				<p><?php echo wp_kses( nl2br( esc_html( $oria_method ) ), array( 'br' => array() ) ); ?></p>
				<p class="bohow__fine"><?php esc_html_e( 'Best Of guides are editorial. No practice paid to appear here; paid placements on Oria Haven are always labelled Featured.', 'oria' ); ?></p>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( $oria_n >= 3 ) : ?>
	<section class="wrap bosection">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'Side by side', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'Compare the picks', 'oria' ); ?></h2>
			</div>
		</div>
		<div class="botable-wrap reveal">
			<table class="botable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Practice', 'oria' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Suburb', 'oria' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Best for', 'oria' ); ?></th>
						<th scope="col"><?php esc_html_e( 'From', 'oria' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Time', 'oria' ); ?></th>
						<?php if ( BestOf\any_rebate( $oria_entries ) ) : ?>
							<th scope="col"><?php esc_html_e( 'Private health*', 'oria' ); ?></th>
						<?php endif; ?>
						<th scope="col"><?php esc_html_e( 'Highlights', 'oria' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $oria_entries as $oria_e ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( (string) get_permalink( $oria_e['listing'] ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_e['listing'] ) ) ); ?></a></td>
							<td><?php echo esc_html( BestOf\suburb( $oria_e['listing'] ) ?: '—' ); ?></td>
							<td><?php echo esc_html( $oria_e['best_for'] ?: $oria_e['label'] ); ?></td>
							<td><?php echo esc_html( '' !== $oria_e['price_note'] ? $oria_e['price_note'] : ( BestOf\price_from( $oria_e['listing'] ) ?: '—' ) ); ?></td>
							<td><?php echo esc_html( '' !== $oria_e['sessions'] ? $oria_e['sessions'] : ( BestOf\duration( $oria_e['listing'] ) ?: '—' ) ); ?></td>
							<?php if ( BestOf\any_rebate( $oria_entries ) ) : ?>
								<td><?php echo esc_html( BestOf\rebate_label( $oria_e ) ?: '—' ); ?></td>
							<?php endif; ?>
							<td><?php echo esc_html( $oria_e['highlights'] ? implode( ' · ', $oria_e['highlights'] ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( BestOf\any_rebate( $oria_entries ) ) : ?>
			<p class="botable__note"><?php esc_html_e( '* Private-health rebates depend on the provider, the practitioner and your extras policy. "Available" means the practice says so on its own site; confirm eligibility with the clinic and your insurer before booking.', 'oria' ); ?></p>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $oria_choose ) : ?>
	<section class="wrap bosection">
		<div class="bochoose reveal">
			<span class="micro"><?php esc_html_e( 'Which one should you choose?', 'oria' ); ?></span>
			<h2 class="h2"><?php esc_html_e( 'Pick by your situation', 'oria' ); ?></h2>
			<ul class="bochoose__list">
				<?php foreach ( $oria_choose as $oria_c ) : ?>
					<li>
						<?php esc_html_e( 'Choose', 'oria' ); ?>
						<a href="<?php echo esc_url( (string) get_permalink( $oria_c['listing'] ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_c['listing'] ) ) ); ?></a>
						<?php echo esc_html( sprintf( /* translators: %s: condition */ __( 'if %s', 'oria' ), rtrim( $oria_c['when'], '.' ) ) ); ?>.
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<?php if ( $oria_faq ) : ?>
	<section class="wrap bosection">
		<div class="reveal" style="max-width:56rem">
			<span class="micro"><?php esc_html_e( 'Common questions', 'oria' ); ?></span>
			<h2 class="h2" style="margin-block:.75rem 2rem"><?php esc_html_e( 'Before you go', 'oria' ); ?></h2>
			<div class="acc">
				<?php foreach ( $oria_faq as $oria_i => $oria_q ) : ?>
					<div class="acc__item<?php echo 0 === $oria_i ? ' is-open' : ''; ?>">
						<button class="acc__btn" type="button"><?php echo esc_html( (string) $oria_q['question'] ); ?><span class="acc__sign" aria-hidden="true"></span></button>
						<div class="acc__panel"><div class="acc__inner"><p><?php echo esc_html( (string) $oria_q['answer'] ); ?></p></div></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( $oria_prac || $oria_links ) : ?>
	<section class="wrap bosection">
		<div class="bolinks reveal">
			<span class="micro"><?php esc_html_e( 'Keep exploring', 'oria' ); ?></span>
			<ul class="bolinks__list">
				<?php if ( $oria_prac ) : ?>
					<li><a href="<?php echo esc_url( (string) get_term_link( $oria_prac ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: directory category name */ __( 'Explore all %s in Perth', 'oria' ), strtolower( \Oria\Theme\tname( $oria_prac ) ) ) ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></li>
				<?php endif; ?>
				<?php foreach ( $oria_links as $oria_l ) : ?>
					<li><a href="<?php echo esc_url( (string) $oria_l['url'] ); ?>"><?php echo esc_html( (string) $oria_l['label'] ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<?php if ( $oria_related ) : ?>
	<section class="wrap bosection bosection--last">
		<div class="sec-head reveal">
			<div class="sec-head__text">
				<span class="micro"><?php esc_html_e( 'You might also like', 'oria' ); ?></span>
				<h2 class="h2"><?php esc_html_e( 'More Best Of guides', 'oria' ); ?></h2>
			</div>
			<a class="btn btn--ghost" href="<?php echo esc_url( BestOf\hub_url() ); ?>"><?php esc_html_e( 'All guides', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		</div>
		<div class="bogrid bogrid--3">
			<?php foreach ( $oria_related as $oria_g ) : ?>
				<?php get_template_part( 'template-parts/best-guide-card', null, array( 'post' => $oria_g ) ); ?>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>
</article>

<?php
get_footer();
