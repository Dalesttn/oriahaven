<?php
/**
 * One Trend to Try.
 *
 * The brief's order: the short answer and the facts first, the Reel as a
 * way in, then Oria's own explanation -- what it is, why it is everywhere,
 * what to expect, what the Reel gets right and what needs context, what
 * may help and what remains uncertain, tips, safety, cost -- then where to
 * try it around Perth, what to ask before booking, the verdict, and the
 * sources with the date they were reviewed.
 *
 * Every section is a field and prints only when written, so the page has
 * no empty headings. It cannot go public half-written: Core\Trends\guard()
 * returns an incomplete trend to draft.
 *
 * @package Oria
 */

declare(strict_types=1);

use Oria\Core\Trends;

get_header();
the_post();

$oria_id       = (int) get_the_ID();
$oria_slug     = (string) get_post_field( 'post_name', $oria_id );
$oria_goals    = Trends\goals( $oria_id );
$oria_ev       = Trends\evidence( $oria_id );
$oria_beg      = Trends\beginner( $oria_id );
$oria_price    = Trends\price( $oria_id );
$oria_dur      = trim( (string) get_field( 'typical_duration', $oria_id ) );
$oria_reel     = Trends\reel( $oria_id );
$oria_listings = Trends\related( $oria_id, 'related_listings' );
$oria_events   = Trends\related( $oria_id, 'related_events' );
$oria_guides   = Trends\related( $oria_id, 'related_guides' );
$oria_products = Trends\related( $oria_id, 'related_products' );
$oria_apps     = Trends\related( $oria_id, 'related_apps' );
$oria_trends   = Trends\related( $oria_id, 'related_trends' );
$oria_cats     = Trends\practices( $oria_id );
$oria_sources  = Trends\sources( $oria_id );
$oria_tips     = array_values( array_filter( array_map( static fn( $r ) => trim( (string) ( $r['tip'] ?? '' ) ), (array) get_field( 'beginner_tips', $oria_id ) ) ) );
$oria_asks     = array_values( array_filter( array_map( static fn( $r ) => trim( (string) ( $r['question'] ?? '' ) ), (array) get_field( 'questions_to_ask_provider', $oria_id ) ) ) );
$oria_cta_l    = trim( (string) get_field( 'primary_cta_label', $oria_id ) );
$oria_cta_u    = (string) get_field( 'primary_cta_url', $oria_id );
$oria_cmp_l    = trim( (string) get_field( 'compare_label', $oria_id ) );
$oria_cmp_u    = (string) get_field( 'compare_url', $oria_id );
$oria_sponsor  = (bool) get_field( 'is_sponsored', $oria_id );
$oria_affil    = (bool) get_field( 'contains_affiliate_links', $oria_id );
$oria_cover    = get_post_thumbnail_id( $oria_id ) ? (string) wp_get_attachment_image_url( get_post_thumbnail_id( $oria_id ), 'oria-wide' ) : '';

/** A rich section: heading and the field's HTML, or nothing. */
$oria_section = static function ( string $field, string $heading, string $id = '' ) use ( $oria_id ): void {
	$html = trim( (string) get_field( $field, $oria_id ) );
	if ( '' === wp_strip_all_tags( $html ) ) {
		return;
	}
	?>
	<section class="trendsec"<?php echo '' !== $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?>>
		<h2 class="trendsec__h"><?php echo esc_html( $heading ); ?></h2>
		<div class="trendsec__body prose"><?php echo wp_kses_post( $html ); ?></div>
	</section>
	<?php
};
?>

<article class="trend" data-trend-page="<?php echo esc_attr( $oria_slug ); ?>" data-trend-id="<?php echo (int) $oria_id; ?>">
<div class="heroband heroband--stack<?php echo '' !== $oria_cover ? '' : ' heroband--bare'; ?>"
	<?php if ( '' !== $oria_cover ) : ?>style="--heroband-img:url('<?php echo esc_url( $oria_cover ); ?>')"<?php endif; ?>>
<section class="wrap bohero">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( Trends\hub_url() ); ?>"><?php esc_html_e( 'Trends', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php the_title(); ?></span>
	</nav>
	<div class="bohero__copy">
		<span class="bohero__eyebrow"><?php esc_html_e( 'Trend to Try', 'oria' ); ?></span>
		<h1 class="bohero__title"><?php the_title(); ?></h1>
		<p class="bohero__meta">
			<?php
			/* translators: %s: date */
			echo esc_html( sprintf( __( 'Reviewed %s', 'oria' ), Trends\reviewed( $oria_id ) ) . ' · ' . __( 'Editorial explainer, not medical advice', 'oria' ) );
			?>
		</p>
	</div>
</section>
</div>

<div class="wrap trendbody">

	<?php if ( $oria_sponsor ) : ?>
		<p class="trenddisclose trenddisclose--sponsor">
			<b><?php esc_html_e( 'Sponsored', 'oria' ); ?></b>
			<?php
			echo esc_html( trim( (string) get_field( 'sponsorship_disclosure', $oria_id ) ) );
			$oria_sn = trim( (string) get_field( 'sponsor_name', $oria_id ) );
			/* translators: %s: sponsor */
			echo '' !== $oria_sn ? ' ' . esc_html( sprintf( __( 'Sponsor: %s.', 'oria' ), $oria_sn ) ) : '';
			?>
			<?php esc_html_e( 'Sponsorship does not change what this page says about evidence or safety.', 'oria' ); ?>
		</p>
	<?php endif; ?>

	<?php $oria_short = trim( (string) get_field( 'short_answer', $oria_id ) ); ?>
	<?php if ( '' !== $oria_short ) : ?>
		<div class="boqa trendanswer">
			<span class="micro"><?php esc_html_e( 'The short answer', 'oria' ); ?></span>
			<p><?php echo esc_html( $oria_short ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	/*
	 * Quick facts. Only what has been written for this trend: a row with
	 * nothing researched behind it is left out rather than filled with a
	 * typical-sounding guess.
	 */
	$oria_facts = array();
	if ( $oria_goals ) {
		$oria_facts[] = array( __( 'May suit', 'oria' ), implode( ', ', wp_list_pluck( $oria_goals, 'label' ) ), '' );
	}
	if ( '' !== $oria_beg ) {
		$oria_facts[] = array( __( 'Beginners', 'oria' ), $oria_beg, '' );
	}
	if ( '' !== $oria_dur ) {
		$oria_facts[] = array( __( 'Typical session', 'oria' ), $oria_dur, '' );
	}
	if ( $oria_price ) {
		/* translators: 1: price, 2: month checked */
		$oria_facts[] = array( __( 'Indicative Perth price', 'oria' ), sprintf( __( '%1$s (checked %2$s)', 'oria' ), $oria_price['text'], $oria_price['checked'] ), '' );
	}
	if ( $oria_ev ) {
		$oria_facts[] = array( __( 'Evidence position', 'oria' ), $oria_ev['label'], $oria_ev['explain'] );
	}
	if ( $oria_listings ) {
		/* translators: %s: number of listings */
		$oria_facts[] = array( __( 'In Perth', 'oria' ), sprintf( _n( '%s place we have confirmed', '%s places we have confirmed', count( $oria_listings ), 'oria' ), number_format_i18n( count( $oria_listings ) ) ), '#where' );
	}
	?>
	<?php if ( $oria_facts ) : ?>
		<dl class="trendfacts" aria-label="<?php esc_attr_e( 'Quick facts', 'oria' ); ?>">
			<?php foreach ( $oria_facts as $oria_f ) : ?>
				<div class="trendfacts__row">
					<dt><?php echo esc_html( $oria_f[0] ); ?></dt>
					<dd>
						<?php if ( '#where' === $oria_f[2] ) : ?>
							<a href="#where"><?php echo esc_html( $oria_f[1] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $oria_f[1] ); ?>
							<?php if ( '' !== $oria_f[2] ) : ?>
								<span class="trendfacts__note"><?php echo esc_html( $oria_f[2] ); ?> <a href="#how"><?php esc_html_e( 'How we judge this', 'oria' ); ?></a></span>
							<?php endif; ?>
						<?php endif; ?>
					</dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/reel-card', null, array( 'id' => $oria_id, 'variant' => 'full', 'location' => 'trend_page' ) ); ?>

	<div class="trendcols">
		<div class="trendmain">
			<?php
			$oria_section( 'what_it_is', __( 'What is it?', 'oria' ) );
			$oria_section( 'why_trending', __( 'Why is it appearing everywhere?', 'oria' ) );
			$oria_section( 'what_to_expect', __( 'What to expect when you try it', 'oria' ) );
			if ( $oria_reel ) {
				$oria_section( 'reel_context', __( 'What the Reel gets right — and what needs context', 'oria' ) );
			}
			$oria_section( 'possible_benefits', __( 'What people may find beneficial', 'oria' ) );
			$oria_section( 'limitations_uncertainties', __( 'What remains uncertain or overstated', 'oria' ) );
			?>

			<?php if ( $oria_tips ) : ?>
				<section class="trendsec">
					<h2 class="trendsec__h"><?php esc_html_e( 'Tips for your first time', 'oria' ); ?></h2>
					<ol class="trendtips">
						<?php foreach ( $oria_tips as $oria_t ) : ?>
							<li><?php echo esc_html( $oria_t ); ?></li>
						<?php endforeach; ?>
					</ol>
				</section>
			<?php endif; ?>

			<?php
			$oria_safe = trim( (string) get_field( 'safety_considerations', $oria_id ) );
			$oria_pro  = trim( (string) get_field( 'who_may_need_professional_advice', $oria_id ) );
			?>
			<?php if ( '' !== wp_strip_all_tags( $oria_safe . $oria_pro ) ) : ?>
				<section class="trendsec trendsafety" id="safety">
					<h2 class="trendsec__h"><?php esc_html_e( 'Before you try it: safety', 'oria' ); ?></h2>
					<?php if ( '' !== wp_strip_all_tags( $oria_safe ) ) : ?>
						<div class="prose"><?php echo wp_kses_post( $oria_safe ); ?></div>
					<?php endif; ?>
					<?php if ( '' !== wp_strip_all_tags( $oria_pro ) ) : ?>
						<h3 class="trendsafety__h3"><?php esc_html_e( 'Talk to a health professional first if…', 'oria' ); ?></h3>
						<div class="prose"><?php echo wp_kses_post( $oria_pro ); ?></div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php $oria_pnotes = trim( (string) get_field( 'price_notes', $oria_id ) ); ?>
			<?php if ( $oria_price || '' !== $oria_dur || '' !== $oria_pnotes ) : ?>
				<section class="trendsec">
					<h2 class="trendsec__h"><?php esc_html_e( 'What it typically costs in Perth', 'oria' ); ?></h2>
					<p>
						<?php
						$oria_bits = array();
						if ( $oria_price ) {
							/* translators: 1: price range, 2: month */
							$oria_bits[] = sprintf( __( 'Usually %1$s a session (prices checked %2$s — confirm with the venue).', 'oria' ), $oria_price['text'], $oria_price['checked'] );
						}
						if ( '' !== $oria_dur ) {
							/* translators: %s: duration */
							$oria_bits[] = sprintf( __( 'Sessions typically run %s.', 'oria' ), $oria_dur );
						}
						echo esc_html( implode( ' ', $oria_bits ) );
						?>
					</p>
					<?php if ( '' !== $oria_pnotes ) : ?>
						<p class="hint"><?php echo esc_html( $oria_pnotes ); ?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>
		</div>
	</div>

	<?php
	/*
	 * Where to try it. Only places an editor confirmed actually offer this
	 * (never inferred from a name). None yet: said plainly, with the
	 * categories to browse instead of a row of empty cards.
	 */
	?>
	<section class="trendwhere" id="where">
		<div class="suphead">
			<div class="suphead__text">
				<h2 class="suphead__title"><?php esc_html_e( 'Where to try it around Perth', 'oria' ); ?></h2>
				<p class="suphead__desc">
					<?php
					echo esc_html(
						$oria_listings
							? __( 'Places we have confirmed offer this. Listed in no paid order.', 'oria' )
							: __( 'We have not confirmed a Perth listing for this yet. These categories are the closest places to look.', 'oria' )
					);
					?>
				</p>
			</div>
			<?php if ( '' !== $oria_cta_l && '' !== $oria_cta_u ) : ?>
				<a class="btn btn--dark btn--sm suphead__cta" href="<?php echo esc_url( $oria_cta_u ); ?>" data-trend-cta="cta" data-trend-where="where"><?php echo esc_html( $oria_cta_l ); ?><span aria-hidden="true"><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></span></a>
			<?php endif; ?>
		</div>

		<?php if ( $oria_listings ) : ?>
			<div class="trendwhere__grid">
				<?php
				global $post;
				foreach ( $oria_listings as $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$post = get_post( $post ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					setup_postdata( $post );
					echo '<div data-trend-cta-wrap="listing">';
					get_template_part( 'template-parts/listing', 'card' );
					echo '</div>';
				}
				wp_reset_postdata();
				?>
			</div>
		<?php endif; ?>

		<?php if ( $oria_cats || ( '' !== $oria_cmp_l && '' !== $oria_cmp_u ) ) : ?>
			<p class="trendwhere__more">
				<?php foreach ( $oria_cats as $oria_c ) : ?>
					<a class="pill" href="<?php echo esc_url( function_exists( '\Oria\Core\PracticesIndex\category_url' ) ? \Oria\Core\PracticesIndex\category_url( $oria_c ) : (string) get_term_link( $oria_c ) ); ?>" data-trend-cta="category" data-trend-where="where"><?php echo esc_html( \Oria\Theme\tname( $oria_c ) ); ?></a>
				<?php endforeach; ?>
				<?php if ( '' !== $oria_cmp_l && '' !== $oria_cmp_u ) : ?>
					<a class="pill pill--compare" href="<?php echo esc_url( $oria_cmp_u ); ?>" data-trend-cta="compare" data-trend-where="where"><?php echo esc_html( $oria_cmp_l ); ?> &rarr;</a>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( $oria_events ) : ?>
			<h3 class="trendwhere__h3"><?php esc_html_e( 'Coming up', 'oria' ); ?></h3>
			<ul class="trendevents">
				<?php foreach ( $oria_events as $oria_e ) : ?>
					<li><a href="<?php echo esc_url( (string) get_permalink( $oria_e ) ); ?>" data-trend-cta="event" data-trend-where="where"><?php echo esc_html( get_the_title( $oria_e ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<div class="trendcols">
		<div class="trendmain">
			<?php if ( $oria_asks ) : ?>
				<section class="trendsec">
					<h2 class="trendsec__h"><?php esc_html_e( 'Questions to ask before you book', 'oria' ); ?></h2>
					<ul class="trendasks">
						<?php foreach ( $oria_asks as $oria_q ) : ?>
							<li><?php echo esc_html( $oria_q ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php $oria_verdict = trim( (string) get_field( 'oria_verdict', $oria_id ) ); ?>
			<?php if ( '' !== $oria_verdict ) : ?>
				<section class="trendverdict">
					<p class="micro"><?php esc_html_e( 'Oria’s verdict', 'oria' ); ?></p>
					<p><?php echo esc_html( $oria_verdict ); ?></p>
				</section>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $oria_guides || $oria_trends ) : ?>
		<section class="trendrelated">
			<?php if ( $oria_guides ) : ?>
				<h2 class="trendsec__h"><?php esc_html_e( 'Read next', 'oria' ); ?></h2>
				<ul class="trendreadnext">
					<?php foreach ( $oria_guides as $oria_g ) : ?>
						<li><a href="<?php echo esc_url( (string) get_permalink( $oria_g ) ); ?>" data-trend-cta="guide" data-trend-where="related"><?php echo esc_html( get_the_title( $oria_g ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $oria_trends ) : ?>
				<h2 class="trendsec__h"><?php esc_html_e( 'More trends to understand', 'oria' ); ?></h2>
				<div class="trendgrid">
					<?php foreach ( $oria_trends as $oria_rt ) : ?>
						<?php get_template_part( 'template-parts/trend-card', null, array( 'post' => get_post( $oria_rt ), 'location' => 'related' ) ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * Products and apps last, and only when an editor chose them: the
	 * plugins' own cards and disclosures, so an affiliate link always
	 * arrives with its explanation.
	 */
	$oria_prod_rows = array();
	if ( $oria_products && function_exists( '\Oria\Shop\Engine\build_rows' ) ) {
		$oria_seen      = array();
		$oria_prod_rows = \Oria\Shop\Engine\build_rows( array_map( 'get_post', $oria_products ), 3, $oria_seen );
	}
	?>
	<?php if ( $oria_prod_rows && function_exists( '\Oria\Shop\Render\card' ) ) : ?>
		<section class="trendrelated" data-trend-cta-wrap="product">
			<h2 class="trendsec__h"><?php esc_html_e( 'Useful to have', 'oria' ); ?></h2>
			<div class="prodgrid supgrid"><?php foreach ( $oria_prod_rows as $oria_pr ) { echo \Oria\Shop\Render\card( $oria_pr ); } // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></div>
			<p class="supband__disclosure"><?php echo esc_html( \Oria\Shop\Data\disclosure() ); ?></p>
		</section>
	<?php endif; ?>

	<footer class="trendfoot" id="how">
		<?php if ( $oria_sources ) : ?>
			<h2 class="trendsec__h"><?php esc_html_e( 'Sources', 'oria' ); ?></h2>
			<ol class="trendsources">
				<?php foreach ( $oria_sources as $oria_s ) : ?>
					<li>
						<a href="<?php echo esc_url( $oria_s['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $oria_s['title'] ); ?></a>
						<?php echo '' !== $oria_s['publisher'] ? ' — ' . esc_html( $oria_s['publisher'] ) : ''; ?>
						<?php
						if ( '' !== $oria_s['accessed'] ) {
							/* translators: %s: date */
							echo ' <span class="hint">' . esc_html( sprintf( __( '(checked %s)', 'oria' ), date_i18n( 'M Y', (int) strtotime( $oria_s['accessed'] ) ) ) ) . '</span>';
						}
						?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
		<p class="trendfoot__how">
			<b><?php esc_html_e( 'How we write these.', 'oria' ); ?></b>
			<?php esc_html_e( 'Oria Haven explains trends people meet online; it does not endorse them. The evidence position is our editorial reading of the sources above, not a scientific certification, and a popular Reel is not evidence that a claim is true. This page is general information, not medical advice — talk to a GP or other health professional about your own situation.', 'oria' ); ?>
		</p>
		<p class="trendfoot__meta">
			<?php
			/* translators: 1: author, 2: date */
			echo esc_html( sprintf( __( 'Written by %1$s · Reviewed %2$s', 'oria' ), get_the_author(), Trends\reviewed( $oria_id ) ) );
			if ( $oria_affil ) {
				echo ' · ' . esc_html__( 'This page contains affiliate links; we may earn a commission at no extra cost to you.', 'oria' );
			}
			?>
		</p>
	</footer>
</div>
</article>

<?php
get_footer();
