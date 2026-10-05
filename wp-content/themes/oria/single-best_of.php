<?php
/**
 * One Best Of guide.
 *
 * In reading order: the head with a byline; the quick answer and the
 * spotlights; a facts strip with links into the page; the side-by-side
 * table (the thing a reader cannot get from any one listing, so it comes
 * before the long list); the picks; how they were chosen; the planner;
 * which to choose; questions; where to go next. Every guide-specific word
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
/*
 * Typed links go through the same rewriter as guide copy, so one pointing
 * at a retired or twin address lands on the indexed page. Then the facet
 * pages this guide should lead on to (data/best-of-browse.json), where the
 * list doesn't already have them.
 */
if ( function_exists( '\Oria\Core\PracticesIndex\rewrite_url' ) ) {
	foreach ( $oria_links as $oria_k => $oria_lk ) {
		$oria_fixed = \Oria\Core\PracticesIndex\rewrite_url( (string) $oria_lk['url'] );
		// A post typed by its bare slug (/sauna-ice-bath-or-float/) 301s to
		// /journal/...; link the address it lands on.
		if ( '' === $oria_fixed && 0 === strpos( (string) $oria_lk['url'], home_url( '/' ) ) ) {
			$oria_pid = url_to_postid( (string) $oria_lk['url'] );
			$oria_pl  = $oria_pid ? (string) get_permalink( $oria_pid ) : '';
			if ( '' !== $oria_pl && untrailingslashit( $oria_pl ) !== untrailingslashit( (string) $oria_lk['url'] ) ) {
				$oria_fixed = $oria_pl;
			}
		}
		if ( '' !== $oria_fixed ) {
			$oria_links[ $oria_k ]['url'] = $oria_fixed;
		}
	}
}
// The category link sits first on its own; a typed copy of it is dropped.
if ( $oria_prac ) {
	$oria_cat_url = untrailingslashit( (string) get_term_link( $oria_prac ) );
	$oria_links   = array_values( array_filter( $oria_links, static fn( $r ) => untrailingslashit( (string) $r['url'] ) !== $oria_cat_url ) );
}
if ( function_exists( '\Oria\Core\FacetGuides\best_of_links' ) ) {
	$oria_have = array_flip( array_map( static fn( $r ) => untrailingslashit( (string) $r['url'] ), $oria_links ) );
	foreach ( \Oria\Core\FacetGuides\best_of_links( (string) get_post_field( 'post_name', $oria_id ) ) as $oria_bl ) {
		if ( ! isset( $oria_have[ untrailingslashit( $oria_bl['url'] ) ] ) ) {
			$oria_links[] = $oria_bl;
			$oria_have[ untrailingslashit( $oria_bl['url'] ) ] = true;
		}
	}
}
$oria_related = BestOf\related( $oria_id, 3 );
$oria_qa      = BestOf\quick_answer( $oria_id );
$oria_spots   = BestOf\spotlights( $oria_entries );
$oria_choose  = BestOf\choose( $oria_id );
$oria_facts   = BestOf\facts( $oria_id );
$oria_by      = BestOf\byline( $oria_id );
$oria_table   = $oria_n >= 3;
$oria_planner = function_exists( '\Oria\Core\DayDesigner\active' ) && \Oria\Core\DayDesigner\active();

// The in-page links, in page order. Only sections that exist.
$oria_jump = array_filter(
	array(
		$oria_table ? array( '#compare', __( 'Compare', 'oria' ) ) : null,
		$oria_n ? array( '#picks', __( 'The picks', 'oria' ) ) : null,
		'' !== $oria_method ? array( '#how', __( 'How we chose', 'oria' ) ) : null,
		$oria_planner ? array( '#day-designer', __( 'Plan a day', 'oria' ) ) : null,
		$oria_faq ? array( '#faq', __( 'Questions', 'oria' ) ) : null,
	)
);
?>

<article>
<?php
/*
 * The guide wears its own featured image, painted by CSS through the
 * shared .heroband block rather than carried as an <img>: the picture is
 * decoration, the headline already says what the page is, and a
 * background is neither announced to a screen reader nor downloaded
 * where it is never shown.
 */
$oria_hero = get_post_thumbnail_id( $oria_id ) ? (string) wp_get_attachment_image_url( get_post_thumbnail_id( $oria_id ), 'oria-wide' ) : '';
?>
<div class="heroband heroband--stack<?php echo '' !== $oria_hero ? '' : ' heroband--bare'; ?>"
	<?php if ( '' !== $oria_hero ) : ?>style="--heroband-img:url('<?php echo esc_url( $oria_hero ); ?>')"<?php endif; ?>>
<section class="wrap bohero">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( BestOf\hub_url() ); ?>"><?php esc_html_e( 'Best Of', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php the_title(); ?></span>
	</nav>
	<div class="bohero__copy">
		<span class="bohero__eyebrow"><?php echo esc_html( implode( ' · ', array_filter( array( __( 'Best of Perth', 'oria' ), $oria_cat ) ) ) ); ?></span>
		<h1 class="bohero__title"><?php the_title(); ?></h1>
		<?php if ( BestOf\intro( $oria_id ) ) : ?>
			<p class="bohero__lede"><?php echo esc_html( BestOf\intro( $oria_id ) ); ?></p>
		<?php endif; ?>
		<p class="bohero__meta bobyline">
			<span><?php esc_html_e( 'By the Oria Haven editors', 'oria' ); ?></span>
			<span aria-hidden="true">·</span>
			<span><?php echo esc_html( sprintf( $oria_by['reviewed'] ? /* translators: %s: date */ __( 'Prices checked %s', 'oria' ) : __( 'Updated %s', 'oria' ), $oria_by['checked'] ) ); ?></span>
			<?php if ( $oria_by['reviewed'] ) : ?>
				<span aria-hidden="true">·</span>
				<span><?php echo esc_html( sprintf( /* translators: %s: month and year */ __( 'Next check due %s', 'oria' ), $oria_by['next'] ) ); ?></span>
			<?php endif; ?>
			<span aria-hidden="true">·</span>
			<span><?php esc_html_e( 'Editorial, never paid', 'oria' ); ?></span>
		</p>
	</div>
</section>
</div>

<?php
/*
 * The Oria shortlist: a heading and one line, the guide's facts as a quiet
 * row, up to three image-led cards for the spotlighted picks (first one
 * featured), the quick answer with its names linked into the guide, and a
 * slim row of links to each part of the page. Every card goes to its pick's
 * own section further down, so the cards are a shortcut, not a detour.
 */
$oria_sl        = BestOf\shortlist( $oria_id );
$oria_sl_cards  = $oria_sl['cards'];
$oria_qa_html   = BestOf\quick_answer_html( $oria_id );
$oria_sl_facts  = array_values( array_filter( $oria_facts, static fn( array $f ): bool => __( 'Prices checked', 'oria' ) !== $f['label'] ) );
$oria_sl_check  = array_values( array_filter( $oria_facts, static fn( array $f ): bool => __( 'Prices checked', 'oria' ) === $f['label'] ) );
?>
<?php if ( $oria_sl_cards || $oria_qa || $oria_facts || count( $oria_jump ) > 1 ) : ?>
<section class="oria-shortlist" aria-labelledby="oria-shortlist-title">
	<div class="wrap oria-shortlist__inner">
		<header class="oria-shortlist__head">
			<p class="oria-shortlist__eyebrow"><?php esc_html_e( 'The Oria shortlist', 'oria' ); ?></p>
			<h2 class="oria-shortlist__title" id="oria-shortlist-title"><?php echo esc_html( $oria_sl['title'] ); ?></h2>
			<?php if ( '' !== $oria_sl['intro'] ) : ?>
				<p class="oria-shortlist__intro"><?php echo esc_html( $oria_sl['intro'] ); ?></p>
			<?php endif; ?>
			<?php if ( $oria_sl_facts || $oria_sl_check ) : ?>
				<p class="oria-shortlist__facts">
					<?php foreach ( $oria_sl_facts as $oria_fi => $oria_f ) : ?>
						<?php if ( $oria_fi ) : ?><span class="oria-shortlist__dot" aria-hidden="true">·</span><?php endif; ?>
						<span><?php echo esc_html( __( 'Picks', 'oria' ) === $oria_f['label'] || __( 'Suburbs', 'oria' ) === $oria_f['label'] ? $oria_f['value'] . ' ' . mb_strtolower( $oria_f['label'] ) : $oria_f['label'] . ' ' . $oria_f['value'] ); ?></span>
					<?php endforeach; ?>
					<?php if ( $oria_sl_check ) : ?>
						<span class="oria-shortlist__checked"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Prices checked %s', 'oria' ), $oria_sl_check[0]['value'] ) ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</header>

		<?php if ( $oria_sl_cards ) : ?>
			<ul class="oria-shortlist__grid oria-shortlist__grid--<?php echo (int) count( $oria_sl_cards ); ?>">
				<?php foreach ( $oria_sl_cards as $oria_ci => $oria_c ) : ?>
					<?php
					$oria_cl    = (int) $oria_c['listing'];
					$oria_cname = \Oria\Theme\ptitle( get_post( $oria_cl ) );
					$oria_thumb = (int) get_post_thumbnail_id( $oria_cl );
					$oria_photo = $oria_thumb ? '' : ( function_exists( '\Oria\Core\Places\card_photo' ) ? (string) \Oria\Core\Places\card_photo( $oria_cl ) : '' );
					$oria_eager = 0 === $oria_ci;
					?>
					<li class="oria-shortlist__item<?php echo 0 === $oria_ci ? ' oria-shortlist__item--featured' : ''; ?>">
						<a class="oria-shortlist__card" href="#pick-<?php echo (int) $oria_c['rank']; ?>">
							<span class="oria-shortlist__media">
								<?php if ( $oria_thumb ) : ?>
									<?php echo wp_get_attachment_image( $oria_thumb, 'oria-wide', false, array( 'alt' => '', 'loading' => $oria_eager ? 'eager' : 'lazy', 'decoding' => 'async', 'sizes' => $oria_eager ? '(max-width: 1099px) 100vw, 640px' : '(max-width: 699px) 100vw, (max-width: 1099px) 50vw, 320px' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<?php elseif ( '' !== $oria_photo ) : ?>
									<img src="<?php echo esc_url( $oria_photo ); ?>" alt="" width="800" height="600" loading="<?php echo $oria_eager ? 'eager' : 'lazy'; ?>" decoding="async">
								<?php else : ?>
									<span class="oria-shortlist__fallback" aria-hidden="true"><?php echo esc_html( mb_substr( $oria_cname, 0, 1 ) ); ?></span>
								<?php endif; ?>
								<span class="oria-shortlist__badge"><span class="badge--best__mark" aria-hidden="true">&#10022;</span> <?php echo esc_html( $oria_c['spotlight'] ); ?></span>
							</span>
							<span class="oria-shortlist__body">
								<span class="oria-shortlist__name"><?php echo esc_html( $oria_cname ); ?></span>
								<span class="oria-shortlist__where"><?php echo esc_html( implode( ' · ', array_filter( array( BestOf\suburb( $oria_cl ), $oria_c['best_for'] ) ) ) ); ?></span>
								<?php if ( '' !== $oria_c['why'] ) : ?>
									<span class="oria-shortlist__why"><?php echo esc_html( $oria_c['why'] ); ?></span>
								<?php endif; ?>
								<span class="oria-shortlist__go"><?php esc_html_e( 'See why we chose it', 'oria' ); ?> <span class="oria-shortlist__arrow" aria-hidden="true">&rarr;</span></span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( '' !== $oria_qa_html ) : ?>
			<?php
			/*
			 * The quick answer: the guide in three sentences, for somebody
			 * (or something) that will not read the rest. It names the picks,
			 * so it is useful on its own and never a teaser.
			 */
			?>
			<div class="oria-shortlist__qa">
				<p class="oria-shortlist__qa-label"><?php esc_html_e( 'Quick answer', 'oria' ); ?></p>
				<p class="oria-shortlist__qa-text"><?php echo $oria_qa_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in quick_answer_html(). ?></p>
			</div>
		<?php endif; ?>

		<?php if ( count( $oria_jump ) > 1 ) : ?>
			<nav class="oria-shortlist__nav" aria-label="<?php esc_attr_e( 'Explore this guide', 'oria' ); ?>">
				<span class="oria-shortlist__nav-label"><?php esc_html_e( 'Explore this guide', 'oria' ); ?></span>
				<span class="oria-shortlist__nav-links">
					<?php foreach ( $oria_jump as $oria_j ) : ?>
						<a href="<?php echo esc_attr( $oria_j[0] ); ?>"><?php echo esc_html( $oria_j[1] ); ?></a>
					<?php endforeach; ?>
				</span>
			</nav>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php
/*
 * Where the picks actually cluster, when they do. Most of these guides
 * are deliberately spread across the city and this says nothing at all
 * on them -- which is the point.
 */
if ( function_exists( '\Oria\Core\AreaContext\dominant' ) ) {
	$oria_bo_area = \Oria\Core\AreaContext\dominant(
		array_filter( array_map( static fn( array $e ): int => (int) ( $e['listing'] ?? 0 ), $oria_entries ) ),
		0.4,
		3
	);
	if ( $oria_bo_area ) {
		echo '<div class="wrap bosection bosection--flush">';
		get_template_part(
			'template-parts/area/area-strip',
			null,
			array(
				'area'   => $oria_bo_area,
				'lead'   => sprintf(
					/* translators: 1: number of picks, 2: total picks, 3: suburb */
					__( '%1$d of these %2$d picks are in %3$s.', 'oria' ),
					(int) $oria_bo_area['here'],
					$oria_n,
					(string) $oria_bo_area['name']
				),
				'cta'    => sprintf( /* translators: %s: suburb */ __( 'Open the %s guide', 'oria' ), (string) $oria_bo_area['name'] ),
				'source' => 'best-of-cluster',
			)
		);
		echo '</div>';
	}
}
?>

<?php if ( $oria_table ) : ?>
	<section class="wrap bosection" id="compare">
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
					<?php foreach ( $oria_entries as $oria_i => $oria_e ) : ?>
						<?php // data-label: on a phone the table becomes a stack of cards and each cell names its column. ?>
						<tr>
							<td data-label="<?php esc_attr_e( 'Practice', 'oria' ); ?>"><a href="#pick-<?php echo (int) ( $oria_i + 1 ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_e['listing'] ) ) ); ?></a></td>
							<td data-label="<?php esc_attr_e( 'Suburb', 'oria' ); ?>"><?php echo esc_html( BestOf\suburb( $oria_e['listing'] ) ?: '—' ); ?></td>
							<td data-label="<?php esc_attr_e( 'Best for', 'oria' ); ?>"><?php echo esc_html( $oria_e['best_for'] ?: $oria_e['label'] ); ?></td>
							<td data-label="<?php esc_attr_e( 'From', 'oria' ); ?>"><?php echo esc_html( BestOf\table_price( $oria_e ) ?: '—' ); ?></td>
							<td data-label="<?php esc_attr_e( 'Time', 'oria' ); ?>"><?php echo esc_html( BestOf\time_label( $oria_e ) ?: '—' ); ?></td>
							<?php if ( BestOf\any_rebate( $oria_entries ) ) : ?>
								<td data-label="<?php esc_attr_e( 'Private health', 'oria' ); ?>"><?php echo esc_html( BestOf\rebate_label( $oria_e ) ?: '—' ); ?></td>
							<?php endif; ?>
							<td class="botable__hl" data-label="<?php esc_attr_e( 'Highlights', 'oria' ); ?>"><?php echo esc_html( $oria_e['highlights'] ? implode( ' · ', $oria_e['highlights'] ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="botable__note">
			<?php if ( BestOf\any_rebate( $oria_entries ) ) : ?>
				<?php esc_html_e( '* Private-health rebates depend on the provider, the practitioner and your extras policy. "Available" means the practice says so on its own site; confirm eligibility with the clinic and your insurer before booking.', 'oria' ); ?>
			<?php endif; ?>
			<?php esc_html_e( 'A name in the table jumps to that pick below.', 'oria' ); ?>
		</p>
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
				<?php get_template_part( 'template-parts/best-pick', null, array( 'entry' => $oria_e, 'rank' => $oria_i + 1, 'eager' => 0 === $oria_i ) ); ?>
			<?php endforeach; ?>
		</ol>
	<?php else : ?>
		<p class="muted"><?php esc_html_e( 'The picks for this guide are being finalised.', 'oria' ); ?></p>
	<?php endif; ?>
</section>

<?php if ( $oria_method ) : ?>
	<section class="wrap bosection" id="how">
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

<?php
// "Plan a day around this": the Day Designer, on the spa guides it has prices for.
if ( function_exists( '\Oria\Core\DayDesigner\render' ) ) {
	\Oria\Core\DayDesigner\render( array( 'variant' => 'guide' ) );
}
?>

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
	<section class="wrap bosection" id="faq">
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

<?php
/*
 * The retreats guide only: up to three disclosed retreat escapes from
 * near Perth and WA -- related choices, never mixed into the picks above,
 * and never a Bali package in a Perth guide.
 */
if ( 'wellness-retreats-perth' === get_post_field( 'post_name', $oria_id ) && function_exists( '\Oria\Core\Retreats\module' ) ) {
	$oria_ro = array_slice(
		array_merge(
			\Oria\Core\Retreats\active_offers( array( 'destination' => 'near-perth' ) ),
			\Oria\Core\Retreats\active_offers( array( 'destination' => 'wa' ) )
		),
		0,
		3
	);
	if ( $oria_ro ) {
		echo '<div class="wrap">' . \Oria\Core\Retreats\module( $oria_ro, 'best_of', __( 'Further afield: retreat escapes in WA', 'oria' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- module escapes.
	}
}

// Trend to Try, when one is tied to this guide or its category.
$oria_tr = function_exists( '\Oria\Core\Trends\for_guide' ) ? \Oria\Core\Trends\for_guide( $oria_id ) : array();
?>
<?php if ( $oria_tr ) : ?>
	<section class="wrap bosection"><?php get_template_part( 'template-parts/trend-context', null, array( 'trends' => $oria_tr, 'location' => 'best_of' ) ); ?></section>
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
