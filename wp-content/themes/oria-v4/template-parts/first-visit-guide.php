<?php
/**
 * The first-visit guide, v4 category pages (October 2026 redesign,
 * DESIGN/October 2026/oria-haven-first-visit-redesign-claude.md).
 *
 * A small editorial guide in the order a first-timer needs it: what it is
 * like, four quick facts, the visit in three steps, what to prepare and
 * what to ask, the guidance worth keeping in view, and the way back to
 * the places.
 *
 * Content lives in oria-core's data/first-visit.json, read through
 * FirstVisit\for_context. A guide with the visit-guide fields (heading,
 * quick, visit, prep, ask, note) gets them; an older guide is laid out
 * from its lede/arrive/wear/bring/steps/worry, and whatever it lacks
 * simply isn't drawn. The parent theme's first-visit.php is untouched:
 * the specialty archive still uses it.
 *
 * Args: guide (array), id (string), topic (e.g. "infrared saunas"),
 *       prices (bool: the page has a Prices tab).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$fvg = (array) ( $args['guide'] ?? array() );
if ( ! $fvg || ( '' === (string) ( $fvg['lede'] ?? '' ) && '' === (string) ( $fvg['intro'] ?? '' ) ) ) {
	return;
}
$fvg_id     = (string) ( $args['id'] ?? 'first-visit' );
$fvg_topic  = (string) ( $args['topic'] ?? '' );
$fvg_prices = ! empty( $args['prices'] );
$fvg_title  = strtolower( (string) ( $fvg['title'] ?? '' ) );

/** A small line icon. Decorative: every one sits beside its own words. */
$fvg_icon = static function ( string $name ): string {
	$d = array(
		'clock'  => '<circle cx="12" cy="12" r="8.2"/><path d="M12 7.6V12l3 1.9"/>',
		'door'   => '<path d="M6.5 20V5.2c0-.7.5-1.2 1.2-1.2h8.6c.7 0 1.2.5 1.2 1.2V20"/><path d="M4.5 20h15"/><circle cx="14.4" cy="12.4" r=".6"/>',
		'spark'  => '<path d="M12 3.8c0 4.6 1.6 6.2 6.2 6.2-4.6 0-6.2 1.6-6.2 6.2 0-4.6-1.6-6.2-6.2-6.2 4.6 0 6.2-1.6 6.2-6.2Z"/><path d="M18.4 15.6v3.6M16.6 17.4h3.6"/>',
		'tag'    => '<path d="M3.9 12.6 11.3 5.2c.3-.3.7-.5 1.1-.5h5.4c.8 0 1.5.7 1.5 1.5v5.4c0 .4-.2.8-.5 1.1l-7.4 7.4c-.6.6-1.5.6-2.1 0l-5.4-5.4c-.6-.6-.6-1.5 0-2.1Z"/><circle cx="15.6" cy="8.4" r="1.2"/>',
		'people' => '<circle cx="9" cy="8.6" r="2.8"/><path d="M3.8 19c.5-3 2.6-4.8 5.2-4.8s4.7 1.8 5.2 4.8"/><circle cx="16.6" cy="9.6" r="2.2"/><path d="M15.8 14.3c2.3-.2 4 1.3 4.5 3.9"/>',
		'shirt'  => '<path d="M8.6 4.2 4.2 6.8l1.6 3.6 2.2-.9V20h8V9.5l2.2.9 1.6-3.6-4.4-2.6c-.5 1.4-1.8 2.2-3.4 2.2s-2.9-.8-3.4-2.2Z"/>',
		'towel'  => '<rect x="5" y="4" width="14" height="16" rx="2"/><path d="M5 9h14M9 4v16"/>',
		'bag'    => '<path d="M5.4 8.4h13.2l-1 11.6H6.4Z"/><path d="M9 8.4V7a3 3 0 0 1 6 0v1.4"/>',
		'ticket' => '<path d="M4 8.2V6.6c0-.6.4-1 1-1h14c.6 0 1 .4 1 1v1.6a2.4 2.4 0 0 0 0 4.8v1.6c0 .6-.4 1-1 1H5c-.6 0-1-.4-1-1V13a2.4 2.4 0 0 0 0-4.8Z" transform="translate(0 1.5)"/><path d="M14.5 7.6v1.4M14.5 11.4v1.4M14.5 15.2v1.4"/>',
		'info'   => '<circle cx="12" cy="12" r="8.4"/><path d="M12 11v5"/><circle cx="12" cy="7.9" r=".6" fill="currentColor"/>',
		'check'  => '<path d="m5.5 12.5 4 4 9-9.5"/>',
	);
	return '<svg class="fvg__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $d[ $name ] ?? $d['check'] ) . '</svg>';
};

// 1. The opening.
$fvg_heading = (string) ( $fvg['heading'] ?? '' );
if ( '' === $fvg_heading ) {
	/* translators: %s: the kind of session, lowercased */
	$fvg_heading = sprintf( __( 'What to expect at your first %s session', 'oria' ), $fvg_title );
}
$fvg_intro = '' !== (string) ( $fvg['intro'] ?? '' ) ? (string) $fvg['intro'] : (string) $fvg['lede'];

// 2. Four quick facts. A guide's own list wins; otherwise the Compare
// registry's when, who and experience, and a real price destination in
// place of a "$$" that tells nobody anything.
$fvg_quick = (array) ( $fvg['quick'] ?? array() );
if ( ! $fvg_quick ) {
	$fvg_map = array(
		'How long'          => array( 'clock', __( 'Session length', 'oria' ) ),
		'How many people'   => array( 'people', __( 'Group size', 'oria' ) ),
		'Experience needed' => array( 'spark', __( 'Experience needed', 'oria' ) ),
	);
	foreach ( (array) ( $fvg['facts'] ?? array() ) as $fvg_f ) {
		$fvg_k = (string) ( $fvg_f['label'] ?? '' );
		if ( isset( $fvg_map[ $fvg_k ] ) ) {
			$fvg_quick[] = array( 'icon' => $fvg_map[ $fvg_k ][0], 'label' => $fvg_map[ $fvg_k ][1], 'value' => (string) $fvg_f['value'] );
		}
	}
	if ( $fvg_prices ) {
		$fvg_quick[] = array( 'icon' => 'tag', 'label' => __( 'What it costs', 'oria' ), 'value' => __( 'See published prices', 'oria' ), 'link' => 'prices' );
	}
}
$fvg_href = static function ( string $link ) use ( $fvg_prices ): string {
	if ( 'prices' === $link ) {
		return $fvg_prices ? '#xcInsights' : '';
	}
	return 'results' === $link ? '#results' : (string) esc_url( $link );
};

// 3. The visit in order. An older guide has no "after": two steps then.
$fvg_visit = (array) ( $fvg['visit'] ?? array() );
$fvg_steps = array();
if ( $fvg_visit ) {
	$fvg_names = array(
		'before' => __( 'Before your visit', 'oria' ),
		'arrive' => __( 'When you arrive', 'oria' ),
		'leave'  => __( 'Before you leave', 'oria' ),
	);
	foreach ( $fvg_names as $fvg_k => $fvg_n ) {
		if ( ! empty( $fvg_visit[ $fvg_k ] ) ) {
			$fvg_steps[] = array( 'h' => $fvg_n, 'items' => (array) $fvg_visit[ $fvg_k ] );
		}
	}
} else {
	$fvg_before = array_values( array_filter( array( (string) ( $fvg['arrive'] ?? '' ), (string) ( $fvg['wear'] ?? '' ) ) ) );
	if ( $fvg_before ) {
		$fvg_steps[] = array( 'h' => __( 'Before your visit', 'oria' ), 'items' => $fvg_before );
	}
	if ( ! empty( $fvg['steps'] ) ) {
		$fvg_steps[] = array( 'h' => __( 'During the session', 'oria' ), 'items' => (array) $fvg['steps'] );
	}
}

// 4. Preparation and questions.
$fvg_prep = (array) ( $fvg['prep'] ?? array() );
if ( ! $fvg_prep && '' !== (string) ( $fvg['bring'] ?? '' ) ) {
	$fvg_prep[] = array( 'icon' => 'bag', 'label' => __( 'What to bring', 'oria' ), 'text' => (string) $fvg['bring'] );
}
$fvg_ask = (array) ( $fvg['ask'] ?? array() );

// 5. The guidance kept in view: the guide's note, else its worry.
$fvg_note_t = (string) ( $fvg['note']['title'] ?? '' );
$fvg_note_x = (string) ( $fvg['note']['text'] ?? '' );
if ( '' === $fvg_note_x ) {
	$fvg_note_t = (string) ( $fvg['worry']['q'] ?? '' );
	$fvg_note_x = (string) ( $fvg['worry']['a'] ?? '' );
}

// 6. Onward: a compare pair page, only when it exists.
$fvg_cmp_url = '';
if ( '' !== (string) ( $fvg['compare']['pair'] ?? '' ) && function_exists( '\Oria\Core\Compare\pair_url' ) ) {
	$fvg_cmp_url = (string) \Oria\Core\Compare\pair_url( (string) $fvg['compare']['pair'] );
}
?>
<section class="fvg wrap" id="<?php echo esc_attr( $fvg_id ); ?>" aria-labelledby="<?php echo esc_attr( $fvg_id ); ?>-title">

	<header class="fvg__intro">
		<p class="fvg__eyebrow"><?php esc_html_e( 'Your first visit', 'oria' ); ?></p>
		<h2 class="fvg__title" id="<?php echo esc_attr( $fvg_id ); ?>-title"><?php echo esc_html( $fvg_heading ); ?></h2>
		<p class="fvg__lede"><?php echo esc_html( $fvg_intro ); ?></p>
	</header>

	<?php if ( $fvg_quick ) : ?>
		<ul class="fvg__quick" style="--fvg-n:<?php echo (int) min( 4, count( $fvg_quick ) ); ?>">
			<?php foreach ( array_slice( $fvg_quick, 0, 4 ) as $fvg_q ) : ?>
				<?php $fvg_to = '' !== (string) ( $fvg_q['link'] ?? '' ) ? $fvg_href( (string) $fvg_q['link'] ) : ''; ?>
				<li class="fvg__fact">
					<?php echo $fvg_icon( (string) ( $fvg_q['icon'] ?? 'check' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup. ?>
					<span class="fvg__fact-l"><?php echo esc_html( (string) $fvg_q['label'] ); ?></span>
					<?php if ( '' !== $fvg_to ) : ?>
						<a class="fvg__fact-v" href="<?php echo esc_attr( $fvg_to ); ?>" data-oria-event="first_visit_link"><?php echo esc_html( (string) $fvg_q['value'] ); ?> <span aria-hidden="true">&rarr;</span></a>
					<?php else : ?>
						<span class="fvg__fact-v"><?php echo esc_html( (string) $fvg_q['value'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== (string) ( $fvg_q['sub'] ?? '' ) ) : ?>
						<span class="fvg__fact-s"><?php echo esc_html( (string) $fvg_q['sub'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $fvg_steps ) : ?>
		<div class="fvg__visit">
			<h3 class="fvg__h3"><?php esc_html_e( 'From arrival to heading home', 'oria' ); ?></h3>
			<?php // Ordered: these genuinely happen in this order. The numerals are drawn, not read. ?>
			<ol class="fvg__steps" style="--fvg-steps:<?php echo (int) count( $fvg_steps ); ?>">
				<?php foreach ( $fvg_steps as $fvg_i => $fvg_s ) : ?>
					<li class="fvg__step">
						<span class="fvg__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $fvg_i + 1 ) ); ?></span>
						<h4 class="fvg__step-h"><?php echo esc_html( $fvg_s['h'] ); ?></h4>
						<ul class="fvg__step-list">
							<?php foreach ( $fvg_s['items'] as $fvg_it ) : ?>
								<li><?php echo esc_html( (string) $fvg_it ); ?></li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	<?php endif; ?>

	<?php if ( $fvg_prep || $fvg_ask ) : ?>
		<div class="fvg__practical<?php echo ( $fvg_prep && $fvg_ask ) ? '' : ' is-single'; ?>">
			<?php if ( $fvg_prep ) : ?>
				<div class="fvg__prep">
					<h3 class="fvg__h3"><?php esc_html_e( 'A little preparation', 'oria' ); ?></h3>
					<ul class="fvg__rows">
						<?php foreach ( $fvg_prep as $fvg_r ) : ?>
							<li>
								<?php echo $fvg_icon( (string) ( $fvg_r['icon'] ?? 'check' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup. ?>
								<p><?php if ( '' !== (string) ( $fvg_r['label'] ?? '' ) ) : ?><b><?php echo esc_html( (string) $fvg_r['label'] ); ?></b> <?php endif; ?><?php echo esc_html( (string) $fvg_r['text'] ); ?></p>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<?php if ( $fvg_ask ) : ?>
				<div class="fvg__ask">
					<h3 class="fvg__h3">
						<?php echo 3 === count( $fvg_ask ) ? esc_html__( 'Three things to check when booking', 'oria' ) : esc_html__( 'Worth checking when booking', 'oria' ); ?>
					</h3>
					<ol class="fvg__qs">
						<?php foreach ( $fvg_ask as $fvg_a ) : ?>
							<li><?php echo esc_html( $fvg_a ); ?></li>
						<?php endforeach; ?>
					</ol>
					<a class="fvg__more" href="#results" data-oria-event="first_visit_link"><?php esc_html_e( 'See venue details', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $fvg_note_x ) : ?>
		<aside class="fvg__note" aria-labelledby="<?php echo esc_attr( $fvg_id ); ?>-note">
			<?php echo $fvg_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup. ?>
			<div>
				<h3 class="fvg__note-h" id="<?php echo esc_attr( $fvg_id ); ?>-note"><?php echo esc_html( '' !== $fvg_note_t ? $fvg_note_t : __( 'Worth knowing', 'oria' ) ); ?></h3>
				<p><?php echo esc_html( $fvg_note_x ); ?></p>
			</div>
		</aside>
	<?php endif; ?>

	<p class="fvg__whose"><?php esc_html_e( 'This describes what these sessions are generally like, not any one venue on this page. Facilities and policies vary, so check anything that matters to you with the venue before you book.', 'oria' ); ?></p>

	<div class="fvg__cta">
		<p class="fvg__cta-h"><?php esc_html_e( 'Find a place that suits your visit.', 'oria' ); ?></p>
		<div class="fvg__cta-act">
			<a class="btn fvg__go" href="#results" data-oria-event="first_visit_explore">
				<?php
				/* translators: %s: topic, e.g. infrared saunas */
				echo esc_html( '' !== $fvg_topic ? sprintf( __( 'Explore %s', 'oria' ), $fvg_topic ) : __( 'Explore places', 'oria' ) );
				?>
				<span aria-hidden="true">&rarr;</span>
			</a>
			<?php if ( '' !== $fvg_cmp_url ) : ?>
				<a class="fvg__cmp" href="<?php echo esc_url( $fvg_cmp_url ); ?>" data-oria-event="category_compare"><?php echo esc_html( '' !== (string) $fvg['compare']['label'] ? (string) $fvg['compare']['label'] : __( 'Compare the options', 'oria' ) ); ?> <span aria-hidden="true">&rarr;</span></a>
			<?php endif; ?>
		</div>
	</div>
</section>
